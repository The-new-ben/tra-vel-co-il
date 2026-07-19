<?php
/** Focused runtime checks for private one-message multi-playbook fan-out. */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

$base = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/vip/';
require_once $base . 'class-tra-vel-vip-intake-fanout-policy.php';
require_once $base . 'class-tra-vel-vip-intake-fanout-planner.php';

$assertions = 0;
$scenarios = 0;
function fanout_assert( $condition, $message ) {
	global $assertions;
	++$assertions;
	if ( ! $condition ) {
		fwrite( STDERR, "VIP intake fan-out runtime failed: {$message}\n" );
		exit( 1 );
	}
}
function fanout_scenario( $name ) {
	global $scenarios;
	++$scenarios;
	return $name;
}
function fanout_ref( $kind, $seed ) { return 'tv_' . $kind . '_' . substr( hash( 'sha256', $seed ), 0, 32 ); }
function fanout_hash( $seed ) { return hash( 'sha256', $seed ); }
function fanout_input_boundary() {
	return array(
		'raw_message_present' => false,
		'raw_identity_data_present' => false,
		'raw_payment_data_present' => false,
		'raw_medical_data_present' => false,
		'raw_supplier_payload_present' => false,
		'bearer_secret_present' => false,
	);
}
function fanout_binding( $suffix = 'presentation' ) {
	$binding = array(
		'contract_version' => Tra_Vel_VIP_Intake_Fanout_Policy::CONTRACT_VERSION,
		'intake_ref' => fanout_ref( 'intake', $suffix ),
		'trip_ref' => fanout_ref( 'trip', 'trip-' . $suffix ),
		'intake_digest' => fanout_hash( 'intake-' . $suffix ),
		'match_evidence_digest' => fanout_hash( 'match-' . $suffix ),
		'match_state' => 'verified_unique',
		'validated_at' => '2026-07-19T10:00:00Z',
		'binding_digest' => str_repeat( '0', 64 ),
		'data_boundary' => fanout_input_boundary(),
	);
	$binding['binding_digest'] = Tra_Vel_VIP_Intake_Fanout_Policy::binding_digest( $binding );
	return $binding;
}
function fanout_observation( $binding, $family, $suffix = '' ) {
	$config = Tra_Vel_VIP_Intake_Fanout_Policy::family_config( $family );
	$seed = $family . ( $suffix ? '-' . $suffix : '' );
	$risks = array( 'none' );
	if ( 'lost_card_payment' === $family ) {
		$risks = array( 'fraud_exposure' );
	} elseif ( 'lost_baggage_flight' === $family ) {
		$risks = array( 'time_critical' );
	} elseif ( 'medical_insurance_assistance' === $family ) {
		$risks = array( 'immediate_danger', 'stranded' );
	} elseif ( 'esim_connectivity' === $family ) {
		$risks = array( 'offline' );
	} elseif ( 'accessibility_failure' === $family ) {
		$risks = array( 'vulnerable_traveler' );
	}
	return array(
		'contract_version' => Tra_Vel_VIP_Intake_Fanout_Policy::CONTRACT_VERSION,
		'observation_ref' => fanout_ref( 'observation', $seed ),
		'trip_ref' => $binding['trip_ref'],
		'observed_at' => '2026-07-19T10:01:00Z',
		'type' => $config['observation_type'],
		'mapping_state' => 'verified',
		'mapped_case_families' => array( $family ),
		'risk_signals' => $risks,
		'service_refs' => array( fanout_ref( 'service', $seed ) ),
		'dependency_refs' => array( fanout_ref( 'dependency', $seed ) ),
		'evidence' => array(
			array(
				'evidence_ref' => fanout_ref( 'evidence', $seed ),
				'evidence_digest' => fanout_hash( 'evidence-' . $seed ),
				'scope' => $config['evidence_scope'],
				'allowed_case_families' => array( $family ),
			),
		),
		'idempotency_digest' => fanout_hash( 'idempotency-' . $seed ),
		'data_boundary' => fanout_input_boundary(),
	);
}
function fanout_plan( $binding, $observations, $now = '2026-07-19T12:00:00Z' ) {
	return Tra_Vel_VIP_Intake_Fanout_Planner::plan( $binding, $observations, $now );
}
function fanout_expect_error( $value, $message, $code_fragment = '' ) {
	fanout_assert( is_wp_error( $value ), $message );
	if ( is_wp_error( $value ) && $code_fragment ) {
		fanout_assert( false !== strpos( $value->get_error_code(), $code_fragment ), $message . ' (wrong error code)' );
	}
}
function fanout_reseal( $plan ) {
	foreach ( $plan['case_seeds'] as $index => $seed ) {
		$basis = array(
			'scope' => $seed['evidence_partition']['scope'],
			'evidence_items' => $seed['evidence_partition']['evidence_items'],
			'restricted' => $seed['evidence_partition']['restricted'],
			'cross_case_disclosure_allowed' => $seed['evidence_partition']['cross_case_disclosure_allowed'],
		);
		$plan['case_seeds'][ $index ]['evidence_partition']['partition_digest'] = Tra_Vel_VIP_Intake_Fanout_Policy::canonical_digest( $basis );
		$plan['case_seeds'][ $index ]['case_seed_digest'] = Tra_Vel_VIP_Intake_Fanout_Policy::case_seed_digest( $plan['case_seeds'][ $index ] );
	}
	$plan['fanout_digest'] = Tra_Vel_VIP_Intake_Fanout_Policy::fanout_digest( $plan );
	return $plan;
}
function fanout_seed_map( $plan ) {
	$map = array();
	foreach ( $plan['case_seeds'] as $seed ) {
		$map[ $seed['family'] ] = $seed;
	}
	return $map;
}

$binding = fanout_binding();
$observations = array(
	fanout_observation( $binding, 'esim_connectivity' ),
	fanout_observation( $binding, 'lost_card_payment' ),
	fanout_observation( $binding, 'accessibility_failure' ),
	fanout_observation( $binding, 'medical_insurance_assistance' ),
	fanout_observation( $binding, 'lost_baggage_flight' ),
);

fanout_scenario( 'one intake creates exactly five isolated case seeds' );
$plan = fanout_plan( $binding, $observations );
fanout_assert( ! is_wp_error( $plan ), 'the presentation intake should create a valid fan-out plan' );
fanout_assert( 5 === count( $plan['case_seeds'] ) && 5 === $plan['summary']['seed_count'], 'one intake must produce exactly five separate case seeds' );
fanout_assert( 5 === $plan['observation_ledger']['accepted_count'] && 0 === $plan['observation_ledger']['duplicate_count'], 'all five unique normalized observations must be accepted once' );
fanout_assert( array( 'medical_insurance_assistance', 'lost_card_payment', 'lost_baggage_flight', 'accessibility_failure', 'esim_connectivity' ) === $plan['summary']['family_order'], 'P0 must lead the stable family order without suppressing any case' );
fanout_assert( 'P0' === $plan['case_seeds'][0]['priority'] && 'medical_insurance_assistance' === $plan['case_seeds'][0]['family'], 'medical immediate danger must be the first P0 safety seed' );
fanout_assert( 1 === $plan['summary']['p0_seed_count'] && 1 === $plan['summary']['safety_seed_count'], 'P0 and safety counts must be exact' );
fanout_assert( 0 === $plan['summary']['side_effect_count'] && 0 === $plan['summary']['duplicate_playbook_count'], 'fan-out must create no side effect or duplicate playbook' );
fanout_assert( ! is_wp_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $plan, '2026-07-19T12:00:00Z' ) ), 'the complete output must pass the closed policy' );
$seed_refs = array();
$family_refs = array();
$event_refs = array();
$playbooks = array();
$evidence_refs = array();
foreach ( $plan['case_seeds'] as $seed ) {
	$config = Tra_Vel_VIP_Intake_Fanout_Policy::family_config( $seed['family'] );
	fanout_assert( ! isset( $seed_refs[ $seed['case_seed_ref'] ] ), 'case seed references must be unique' );
	fanout_assert( ! isset( $family_refs[ $seed['family_ref'] ] ), 'case family references must be unique' );
	fanout_assert( ! isset( $event_refs[ $seed['family_event_ref'] ] ), 'family event references must be unique' );
	fanout_assert( ! isset( $playbooks[ $seed['playbook_code'] ] ), 'playbooks must be unique per family' );
	$seed_refs[ $seed['case_seed_ref'] ] = true;
	$family_refs[ $seed['family_ref'] ] = true;
	$event_refs[ $seed['family_event_ref'] ] = true;
	$playbooks[ $seed['playbook_code'] ] = true;
	fanout_assert( $config['playbook_code'] === $seed['playbook_code'], 'the case must use its exact operational playbook' );
	fanout_assert( $config['evidence_scope'] === $seed['evidence_partition']['scope'] && $config['restricted'] === $seed['evidence_partition']['restricted'], 'the case must use its exact evidence sensitivity scope' );
	fanout_assert( false === $seed['evidence_partition']['cross_case_disclosure_allowed'], 'evidence cannot silently bleed into another case' );
	fanout_assert( true === $seed['authority']['required'] && 'unverified' === $seed['authority']['state'] && false === $seed['authority']['execution_authorized'], 'every case needs separate unverified authority' );
	fanout_assert( ! empty( $seed['dependencies']['service_refs'] ) && ! empty( $seed['dependencies']['dependency_refs'] ) && true === $seed['dependencies']['preserve_unaffected_services'], 'every case must bind services and dependencies while preserving unaffected work' );
	fanout_assert( true === $seed['routing']['after_hours_required'] && true === $seed['routing']['operator_review_required'] && 'not_dispatched' === $seed['routing']['dispatch_state'], 'every case must expose an unexecuted after-hours operator route' );
	foreach ( array( 'supplier_action_executed', 'payment_action_executed', 'claim_action_executed', 'booking_action_executed' ) as $effect ) {
		fanout_assert( false === $seed['execution'][ $effect ], "{$effect} must remain false" );
	}
	foreach ( $seed['evidence_partition']['evidence_items'] as $item ) {
		fanout_assert( ! isset( $evidence_refs[ $item['evidence_ref'] ] ), 'evidence references must be globally partitioned between case seeds' );
		$evidence_refs[ $item['evidence_ref'] ] = $seed['family'];
	}
}
foreach ( array( 'storage_written', 'rest_route_registered', 'network_called', 'ai_called', 'supplier_dispatched', 'payment_executed', 'claim_submitted', 'booking_executed' ) as $effect ) {
	fanout_assert( false === $plan['private_boundary'][ $effect ], "private boundary {$effect} must remain false" );
}

fanout_scenario( 'input order does not change deterministic fan-out' );
$reversed = fanout_plan( $binding, array_reverse( $observations ) );
fanout_assert( ! is_wp_error( $reversed ), 'reversed observations should remain valid' );
fanout_assert( Tra_Vel_VIP_Intake_Fanout_Policy::canonical_digest( $plan ) === Tra_Vel_VIP_Intake_Fanout_Policy::canonical_digest( $reversed ), 'normalized input order must not change any output bit' );

fanout_scenario( 'exact observation replay is idempotently deduplicated' );
$with_duplicate = $observations;
$with_duplicate[] = $observations[0];
$duplicate_plan = fanout_plan( $binding, $with_duplicate );
fanout_assert( ! is_wp_error( $duplicate_plan ), 'an exact replay should not fail the planner' );
fanout_assert( 1 === $duplicate_plan['summary']['duplicate_observation_count'] && 5 === $duplicate_plan['summary']['seed_count'], 'an exact replay must increment only the duplicate count' );
fanout_assert( array_column( $plan['case_seeds'], 'case_seed_ref' ) === array_column( $duplicate_plan['case_seeds'], 'case_seed_ref' ), 'an exact replay must not mint another case seed' );
fanout_assert( array_column( $plan['case_seeds'], 'case_seed_digest' ) === array_column( $duplicate_plan['case_seeds'], 'case_seed_digest' ), 'an exact replay must not alter any case seed content' );

fanout_scenario( 'two unique observations merge into one family playbook' );
$payment_a = fanout_observation( $binding, 'lost_card_payment', 'a' );
$payment_b = fanout_observation( $binding, 'lost_card_payment', 'b' );
$merged = fanout_plan( $binding, array( $payment_b, $payment_a ) );
fanout_assert( ! is_wp_error( $merged ) && 1 === count( $merged['case_seeds'] ), 'two payment observations must merge into one payment playbook' );
fanout_assert( 2 === count( $merged['case_seeds'][0]['source_observation_refs'] ) && 2 === count( $merged['case_seeds'][0]['evidence_partition']['evidence_items'] ), 'the merged seed must retain both source and evidence references' );
fanout_assert( 0 === $merged['summary']['duplicate_playbook_count'], 'family aggregation must never create a duplicate playbook' );

fanout_scenario( 'one intake can produce separately scoped sibling observations' );
$siblings = fanout_plan( $binding, array( fanout_observation( $binding, 'lost_card_payment', 'same-message' ), fanout_observation( $binding, 'lost_baggage_flight', 'same-message' ) ) );
fanout_assert( ! is_wp_error( $siblings ) && 2 === count( $siblings['case_seeds'] ), 'one intake may create two sibling observations and exactly two case seeds' );
$sibling_map = fanout_seed_map( $siblings );
fanout_assert( array( 'fraud_exposure' ) === $sibling_map['lost_card_payment']['risk_signals'] && array( 'time_critical' ) === $sibling_map['lost_baggage_flight']['risk_signals'], 'each sibling case must retain only its family-specific risks' );
fanout_assert( $sibling_map['lost_card_payment']['dependencies']['service_refs'] !== $sibling_map['lost_baggage_flight']['dependencies']['service_refs'] && $sibling_map['lost_card_payment']['dependencies']['dependency_refs'] !== $sibling_map['lost_baggage_flight']['dependencies']['dependency_refs'], 'each sibling case must retain only its family-specific services and dependencies' );
fanout_assert( $sibling_map['lost_card_payment']['evidence_partition']['evidence_items'][0]['evidence_ref'] !== $sibling_map['lost_baggage_flight']['evidence_partition']['evidence_items'][0]['evidence_ref'], 'sibling cases cannot share an evidence reference' );

fanout_scenario( 'implicit multi-playbook mapping fails closed' );
$implicit = fanout_observation( $binding, 'lost_card_payment' );
$implicit['mapped_case_families'][] = 'lost_baggage_flight';
$baggage_config = Tra_Vel_VIP_Intake_Fanout_Policy::family_config( 'lost_baggage_flight' );
$implicit['evidence'][] = array( 'evidence_ref' => fanout_ref( 'evidence', 'implicit-baggage' ), 'evidence_digest' => fanout_hash( 'implicit-baggage' ), 'scope' => $baggage_config['evidence_scope'], 'allowed_case_families' => array( 'lost_baggage_flight' ) );
fanout_expect_error( fanout_plan( $binding, array( $implicit ) ), 'a single-family observation cannot silently add another playbook', 'implicit_multi_mapping' );

fanout_scenario( 'ambiguous and conflicted classification require upstream clarification' );
foreach ( array( 'ambiguous', 'conflicted' ) as $state ) {
	$unclear = fanout_observation( $binding, 'lost_baggage_flight', $state );
	$unclear['mapping_state'] = $state;
	$unclear['mapped_case_families'] = array();
	$unclear['evidence'] = array();
	fanout_expect_error( fanout_plan( $binding, array( $unclear ) ), "{$state} observation must not create a case", 'clarification_required' );
}

fanout_scenario( 'cross-trip observations are rejected' );
$cross_trip = fanout_observation( $binding, 'esim_connectivity', 'cross-trip' );
$cross_trip['trip_ref'] = fanout_ref( 'trip', 'another-trip' );
fanout_expect_error( fanout_plan( $binding, array( $cross_trip ) ), 'an observation from another trip cannot enter this fan-out', 'observation_invalid' );

fanout_scenario( 'restricted evidence cannot bleed between medical and payment cases' );
$medical_leak = fanout_observation( $binding, 'medical_insurance_assistance', 'scope-leak' );
$medical_leak['evidence'][0]['scope'] = 'payment_restricted';
fanout_expect_error( fanout_plan( $binding, array( $medical_leak ) ), 'medical evidence cannot enter the payment scope', 'evidence_scope_invalid' );
$payment_leak = fanout_observation( $binding, 'lost_card_payment', 'family-leak' );
$payment_leak['evidence'][0]['allowed_case_families'] = array( 'medical_insurance_assistance' );
fanout_expect_error( fanout_plan( $binding, array( $payment_leak ) ), 'payment evidence cannot claim medical disclosure', 'evidence_scope_invalid' );

fanout_scenario( 'same evidence reference cannot enter two partitions' );
$payment_shared = fanout_observation( $binding, 'lost_card_payment', 'shared' );
$medical_shared = fanout_observation( $binding, 'medical_insurance_assistance', 'shared' );
$medical_shared['evidence'][0]['evidence_ref'] = $payment_shared['evidence'][0]['evidence_ref'];
fanout_expect_error( fanout_plan( $binding, array( $payment_shared, $medical_shared ) ), 'one evidence reference cannot enter medical and payment seeds', 'evidence_cross_partition_conflict' );

fanout_scenario( 'same-family evidence digest conflicts fail closed' );
$payment_digest_a = fanout_observation( $binding, 'lost_card_payment', 'digest-a' );
$payment_digest_b = fanout_observation( $binding, 'lost_card_payment', 'digest-b' );
$payment_digest_b['evidence'][0]['evidence_ref'] = $payment_digest_a['evidence'][0]['evidence_ref'];
$payment_digest_b['evidence'][0]['evidence_digest'] = fanout_hash( 'changed-evidence' );
fanout_expect_error( fanout_plan( $binding, array( $payment_digest_a, $payment_digest_b ) ), 'one evidence reference cannot change its digest', 'evidence_digest_conflict' );

fanout_scenario( 'observation reference and idempotency conflicts fail closed' );
$conflict_a = fanout_observation( $binding, 'esim_connectivity', 'conflict-a' );
$conflict_b = $conflict_a;
$conflict_b['service_refs'] = array( fanout_ref( 'service', 'changed-service' ) );
fanout_expect_error( fanout_plan( $binding, array( $conflict_a, $conflict_b ) ), 'changed content under one observation reference must fail', 'observation_ref_conflict' );
$idempotency_a = fanout_observation( $binding, 'lost_baggage_flight', 'idem-a' );
$idempotency_b = fanout_observation( $binding, 'lost_baggage_flight', 'idem-b' );
$idempotency_b['idempotency_digest'] = $idempotency_a['idempotency_digest'];
fanout_expect_error( fanout_plan( $binding, array( $idempotency_a, $idempotency_b ) ), 'changed content under one idempotency digest must fail', 'observation_idempotency_conflict' );

fanout_scenario( 'binding validation is immutable and unique' );
$bad_binding = $binding;
$bad_binding['match_state'] = 'ambiguous';
fanout_expect_error( fanout_plan( $bad_binding, array( $observations[0] ) ), 'an ambiguous trip binding cannot fan out', 'binding_invalid' );
$bad_binding = $binding;
$bad_binding['intake_digest'] = fanout_hash( 'altered-intake' );
fanout_expect_error( fanout_plan( $bad_binding, array( $observations[0] ) ), 'a changed binding body must fail its digest', 'binding_digest_invalid' );
$bad_binding = $binding;
$bad_binding['unexpected'] = true;
fanout_expect_error( fanout_plan( $bad_binding, array( $observations[0] ) ), 'unknown binding fields must fail closed', 'binding_invalid' );

fanout_scenario( 'input privacy boundaries reject raw material' );
foreach ( array_keys( fanout_input_boundary() ) as $field ) {
	$raw = fanout_observation( $binding, 'esim_connectivity', 'raw-' . $field );
	$raw['data_boundary'][ $field ] = true;
	fanout_expect_error( fanout_plan( $binding, array( $raw ) ), "raw boundary {$field} must fail closed", 'observation_invalid' );
}

fanout_scenario( 'normalized observations reject open or malformed data' );
$mutations = array();
$mutant = fanout_observation( $binding, 'esim_connectivity', 'extra-root' ); $mutant['free_text'] = 'raw'; $mutations[] = $mutant;
$mutant = fanout_observation( $binding, 'esim_connectivity', 'extra-evidence' ); $mutant['evidence'][0]['provider_name'] = 'supplier'; $mutations[] = $mutant;
$mutant = fanout_observation( $binding, 'esim_connectivity', 'bad-type' ); $mutant['type'] = 'unknown'; $mutations[] = $mutant;
$mutant = fanout_observation( $binding, 'esim_connectivity', 'bad-ref' ); $mutant['observation_ref'] = 'observation-unsafe'; $mutations[] = $mutant;
$mutant = fanout_observation( $binding, 'esim_connectivity', 'bad-digest' ); $mutant['idempotency_digest'] = 'bad'; $mutations[] = $mutant;
$mutant = fanout_observation( $binding, 'esim_connectivity', 'future' ); $mutant['observed_at'] = '2026-07-20T10:00:00Z'; $mutations[] = $mutant;
$mutant = fanout_observation( $binding, 'esim_connectivity', 'no-service' ); $mutant['service_refs'] = array(); $mutations[] = $mutant;
$mutant = fanout_observation( $binding, 'esim_connectivity', 'no-dependency' ); $mutant['dependency_refs'] = array(); $mutations[] = $mutant;
$mutant = fanout_observation( $binding, 'esim_connectivity', 'duplicate-service' ); $mutant['service_refs'][] = $mutant['service_refs'][0]; $mutations[] = $mutant;
$mutant = fanout_observation( $binding, 'esim_connectivity', 'risk-none-mixed' ); $mutant['risk_signals'] = array( 'offline', 'none' ); $mutations[] = $mutant;
$mutant = fanout_observation( $binding, 'medical_insurance_assistance', 'risk-order' ); $mutant['risk_signals'] = array( 'stranded', 'immediate_danger' ); $mutations[] = $mutant;
$mutant = fanout_observation( $binding, 'lost_baggage_flight', 'wrong-family' ); $mutant['mapped_case_families'] = array( 'lost_card_payment' ); $mutations[] = $mutant;
foreach ( $mutations as $index => $mutant ) {
	fanout_expect_error( fanout_plan( $binding, array( $mutant ) ), "malformed normalized observation {$index} must fail closed" );
}
fanout_expect_error( fanout_plan( $binding, array() ), 'an empty observation batch must fail', 'observation_batch_invalid' );
fanout_expect_error( fanout_plan( $binding, array( 'named' => $observations[0] ) ), 'an associative observation batch must fail', 'observation_batch_invalid' );

fanout_scenario( 'impossible calendar timestamps fail closed' );
$impossible_date = fanout_observation( $binding, 'esim_connectivity', 'impossible-date' );
$impossible_date['observed_at'] = '2026-02-31T10:00:00Z';
fanout_expect_error( fanout_plan( $binding, array( $impossible_date ) ), 'a non-calendar UTC date must not be normalized by strtotime', 'observation_invalid' );
$bad_now = fanout_plan( $binding, array( $observations[0] ), '2026-02-31T12:00:00Z' );
fanout_expect_error( $bad_now, 'the validation clock must also be a real canonical UTC date', 'binding_invalid' );
$too_many = array();
for ( $i = 0; $i <= Tra_Vel_VIP_Intake_Fanout_Policy::MAX_OBSERVATIONS; ++$i ) {
	$too_many[] = fanout_observation( $binding, 'esim_connectivity', 'overflow-' . $i );
}
fanout_expect_error( fanout_plan( $binding, $too_many ), 'an oversized observation batch must fail', 'observation_batch_invalid' );

fanout_scenario( 'sealed output rejects side-effect and authority escalation' );
foreach ( array( 'supplier_action_executed', 'payment_action_executed', 'claim_action_executed', 'booking_action_executed' ) as $field ) {
	$tampered = $plan;
	$tampered['case_seeds'][0]['execution'][ $field ] = true;
	$tampered = fanout_reseal( $tampered );
	fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), "sealed output cannot claim {$field}", 'execution_invalid' );
}
$tampered = $plan; $tampered['case_seeds'][0]['authority']['execution_authorized'] = true; $tampered = fanout_reseal( $tampered );
fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), 'sealed output cannot grant execution authority', 'authority_invalid' );
$tampered = $plan; $tampered['case_seeds'][0]['routing']['dispatch_state'] = 'dispatched'; $tampered = fanout_reseal( $tampered );
fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), 'sealed output cannot claim an operator dispatch', 'routing_invalid' );

fanout_scenario( 'sealed output rejects evidence leakage and partition changes' );
$tampered = $plan;
$tampered['case_seeds'][1]['evidence_partition']['evidence_items'] = $tampered['case_seeds'][0]['evidence_partition']['evidence_items'];
$tampered = fanout_reseal( $tampered );
fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), 'sealed output cannot reuse evidence across case seeds', 'evidence_cross_partition_invalid' );
$tampered = $plan; $tampered['case_seeds'][0]['evidence_partition']['scope'] = 'payment_restricted'; $tampered = fanout_reseal( $tampered );
fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), 'sealed output cannot relabel medical evidence as payment evidence', 'evidence_partition_invalid' );
$tampered = $plan; $tampered['case_seeds'][0]['evidence_partition']['cross_case_disclosure_allowed'] = true; $tampered = fanout_reseal( $tampered );
fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), 'sealed output cannot enable cross-case evidence disclosure', 'evidence_partition_invalid' );

fanout_scenario( 'sealed output rejects missing cases and P0 reordering' );
$tampered = $plan;
$first = $tampered['case_seeds'][0];
$tampered['case_seeds'][0] = $tampered['case_seeds'][1];
$tampered['case_seeds'][1] = $first;
$tampered['summary']['family_order'] = array_column( $tampered['case_seeds'], 'family' );
$tampered = fanout_reseal( $tampered );
fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), 'P0 cannot be reordered behind a lower-priority case', 'case_seed_order_invalid' );
$tampered = $plan;
array_pop( $tampered['case_seeds'] );
$tampered['summary']['seed_count'] = 4;
$tampered['summary']['family_order'] = array_column( $tampered['case_seeds'], 'family' );
$tampered = fanout_reseal( $tampered );
fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), 'P0 handling cannot suppress another mapped family', 'case_seed_count_invalid' );

fanout_scenario( 'sealed output boundary rejects every hidden side effect' );
foreach ( array( 'storage_written', 'rest_route_registered', 'network_called', 'ai_called', 'supplier_dispatched', 'payment_executed', 'claim_submitted', 'booking_executed' ) as $field ) {
	$tampered = $plan;
	$tampered['private_boundary'][ $field ] = true;
	$tampered = fanout_reseal( $tampered );
	fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), "private output cannot claim {$field}", 'boundary_invalid' );
}

fanout_scenario( 'sealed output rejects open fields and corrupted seals' );
$tampered = $plan; $tampered['debug'] = true;
fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), 'unknown root output fields must fail', 'fanout_shape_invalid' );
$tampered = $plan; $tampered['case_seeds'][0]['evidence_partition']['evidence_items'][0]['raw_payload'] = 'secret'; $tampered = fanout_reseal( $tampered );
fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), 'unknown evidence output fields must fail', 'evidence_item_invalid' );
$tampered = $plan; $tampered['fanout_digest'] = fanout_hash( 'wrong-fanout' );
fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), 'a corrupted fan-out seal must fail', 'fanout_digest_invalid' );
$tampered = $plan; $tampered['case_seeds'][0]['case_seed_digest'] = fanout_hash( 'wrong-seed' ); $tampered['fanout_digest'] = Tra_Vel_VIP_Intake_Fanout_Policy::fanout_digest( $tampered );
fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), 'a corrupted case seed seal must fail', 'case_seed_digest_invalid' );
$tampered = $plan; $tampered['summary']['side_effect_count'] = 1; $tampered = fanout_reseal( $tampered );
fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), 'summary cannot claim a side effect', 'summary_invalid' );
$tampered = $plan; $tampered['summary']['duplicate_playbook_count'] = 1; $tampered = fanout_reseal( $tampered );
fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), 'summary cannot claim duplicate playbooks', 'summary_invalid' );
$tampered = $plan; $tampered['created_at'] = '2026-07-19T10:00:01Z'; $tampered = fanout_reseal( $tampered );
fanout_expect_error( Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $tampered, '2026-07-19T12:00:00Z' ), 'creation time must remain deterministic from the binding', 'fanout_binding_invalid' );

echo "VIP intake fan-out runtime passed ({$assertions} assertions; {$scenarios} scenarios; exactly five isolated seeds; zero side effects).\n";
