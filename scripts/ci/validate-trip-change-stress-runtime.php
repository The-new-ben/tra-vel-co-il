<?php
/**
 * Focused runtime proof for the private trip-change stress planner.
 */

define( 'ABSPATH', __DIR__ . '/' );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
}

require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/vip/class-tra-vel-trip-change-stress-policy.php';
require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/vip/class-tra-vel-trip-change-stress-engine.php';

$assertions = 0;
$scenarios = 0;

function stress_assert( $condition, $message ) {
	global $assertions;
	++$assertions;
	if ( ! $condition ) {
		fwrite( STDERR, "Trip-change stress assertion failed: {$message}\n" );
		exit( 1 );
	}
}

function stress_scenario( $label ) {
	global $scenarios;
	++$scenarios;
	echo "- {$label}\n";
}

function stress_hash( $seed ) { return hash( 'sha256', $seed ); }

function stress_traveler( $suffix, $sequence, $overrides = array() ) {
	return array_merge(
		array(
			'traveler_ref'              => 'tv_traveler_' . $suffix,
			'party_sequence'             => $sequence,
			'eligibility_state'          => 'eligible',
			'consent_state'              => 'given',
			'is_minor'                    => false,
			'guardian_authority_state'   => 'not_required',
			'accessibility_need_codes'   => array(),
			'accessibility_ack_state'    => 'not_required',
		),
		$overrides
	);
}

function stress_component( $suffix, $vertical, $sequence, $dependencies, $travelers ) {
	return array(
		'component_ref'  => 'tv_component_' . $suffix,
		'vertical'       => $vertical,
		'sequence'       => $sequence,
		'dependency_refs' => $dependencies,
		'traveler_refs'  => $travelers,
	);
}

function stress_change( $suffix, $type, $components, $travelers, $truth = 'verified_current' ) {
	$current = 'verified_current' === $truth;
	$stale = 'stale' === $truth;
	return array(
		'change_ref'       => 'tv_trip_change_' . $suffix,
		'type'             => $type,
		'component_refs'   => $components,
		'traveler_refs'    => $travelers,
		'truth_state'      => $truth,
		'observed_at'      => '2026-07-19T12:00:00Z',
		'source_expires_at' => $current ? '2026-07-19T13:00:00Z' : ( $stale ? '2026-07-19T11:00:00Z' : null ),
		'evidence_digest'  => ( $current || $stale ) ? stress_hash( 'evidence-' . $suffix ) : null,
	);
}

function stress_source( $state = 'not_applicable' ) {
	if ( 'not_applicable' === $state ) {
		return array(
			'applicability'  => 'not_applicable',
			'source_code'    => 'not_applicable',
			'state'          => 'not_applicable',
			'checked_at'     => '2026-07-19T12:00:00Z',
			'expires_at'     => null,
			'evidence_digest' => null,
		);
	}
	return array(
		'applicability'  => 'required',
		'source_code'    => 'israel_ministry_transport_gtfs',
		'state'          => $state,
		'checked_at'     => '2026-07-19T12:00:00Z',
		'expires_at'     => 'stale' === $state ? '2026-07-19T11:00:00Z' : null,
		'evidence_digest' => 'stale' === $state ? stress_hash( 'official-gtfs-stale' ) : null,
	);
}

function stress_input( $suffix, $type, $components, $travelers, $changes, $strategy, $source = null ) {
	return array(
		'contract_version'       => '1.0.0',
		'environment'            => 'private_simulation',
		'scenario_ref'           => 'tv_stress_scenario_' . $suffix,
		'trip_ref'               => 'tv_trip_' . $suffix,
		'scenario_type'          => $type,
		'observed_at'            => '2026-07-19T12:00:00Z',
		'components'             => $components,
		'travelers'              => $travelers,
		'changes'                => $changes,
		'selected_strategy'      => $strategy,
		'official_transit_source' => null === $source ? stress_source() : $source,
		'input_boundary'         => array(
			'raw_identity_present'       => false,
			'raw_document_present'       => false,
			'raw_medical_present'        => false,
			'raw_supplier_payload_present' => false,
			'payment_data_present'       => false,
			'bearer_secret_present'      => false,
		),
	);
}

function stress_plan( $input ) {
	$plan = Tra_Vel_Trip_Change_Stress_Engine::plan( $input );
	stress_assert( ! is_wp_error( $plan ), is_wp_error( $plan ) ? $plan->get_error_code() . ': ' . $plan->get_error_message() : 'plan must validate' );
	return $plan;
}

function stress_reseal( $plan ) {
	$plan['plan_digest'] = Tra_Vel_Trip_Change_Stress_Policy::plan_digest( $plan );
	return $plan;
}

function stress_expect_plan_error( $plan, $input, $message ) {
	$result = Tra_Vel_Trip_Change_Stress_Policy::plan( stress_reseal( $plan ), $input );
	stress_assert( is_wp_error( $result ), $message );
}

function stress_sort_actions( $actions ) {
	usort(
		$actions,
		function ( $left, $right ) { return strcmp( $left['action_ref'], $right['action_ref'] ); }
	);
	return $actions;
}

function stress_partition_proof( $partition, $label ) {
	$merged = array_merge( $partition['affected_refs'], $partition['preserved_refs'], $partition['blocked_refs'] );
	stress_assert( count( $merged ) === count( array_unique( $merged ) ), "{$label} partitions must be disjoint" );
	sort( $merged, SORT_STRING );
	$universe = $partition['universe_refs'];
	sort( $universe, SORT_STRING );
	stress_assert( $merged === $universe, "{$label} partitions must be exhaustive" );
}

function stress_common_proof( $plan ) {
	stress_partition_proof( $plan['component_partition'], 'component' );
	stress_partition_proof( $plan['traveler_partition'], 'traveler' );
	stress_assert( 1 === count( array_filter( $plan['recovery_candidates'], function ( $item ) { return 'selected_for_review' === $item['state']; } ) ), 'exactly one recovery candidate must be selected' );
	stress_assert( false === $plan['private_boundary']['supplier_dispatched'], 'planner cannot dispatch a supplier' );
	stress_assert( false === $plan['private_boundary']['booking_modified'], 'planner cannot modify a booking' );
	stress_assert( false === $plan['private_boundary']['commercial_authority'], 'planner cannot grant commercial authority' );
	stress_assert( 0 === $plan['private_boundary']['side_effect_count'], 'planner must have zero side effects' );
}

$ta = 'tv_traveler_alpha000';
$tb = 'tv_traveler_bravo000';

stress_scenario( 'three overlapping connection disruptions preserve viable components' );
$overlap_components = array(
	stress_component( 'flightaa00', 'flight', 1, array(), array( $ta ) ),
	stress_component( 'flightbb00', 'flight', 2, array( 'tv_component_flightaa00' ), array( $ta ) ),
	stress_component( 'flightcc00', 'flight', 3, array( 'tv_component_flightbb00' ), array( $ta ) ),
	stress_component( 'hotelxxx00', 'accommodation', 4, array(), array( $ta, $tb ) ),
	stress_component( 'insurance0', 'insurance', 5, array(), array( $ta, $tb ) ),
	stress_component( 'groundxxx0', 'ground', 6, array( 'tv_component_hotelxxx00' ), array( $ta, $tb ) ),
);
$overlap = stress_input(
	'overlap000',
	'overlapping_connection_disruptions',
	$overlap_components,
	array( stress_traveler( 'alpha000', 1 ), stress_traveler( 'bravo000', 2 ) ),
	array(
		stress_change( 'overlap01', 'flight_connection_delayed', array( 'tv_component_flightaa00' ), array( $ta ) ),
		stress_change( 'overlap02', 'flight_connection_missed', array( 'tv_component_flightbb00' ), array( $ta ) ),
		stress_change( 'overlap03', 'flight_connection_cancelled', array( 'tv_component_flightcc00' ), array( $ta ) ),
	),
	'protected_connection_resequence'
);
$overlap_plan = stress_plan( $overlap );
stress_common_proof( $overlap_plan );
stress_assert( array( 'tv_component_flightaa00', 'tv_component_flightbb00', 'tv_component_flightcc00', 'tv_component_hotelxxx00', 'tv_component_insurance0', 'tv_component_groundxxx0' ) === $overlap_plan['dependency_order'], 'connection dependency order must be stable and topological' );
stress_assert( array( 'tv_component_flightaa00', 'tv_component_flightbb00', 'tv_component_flightcc00' ) === $overlap_plan['component_partition']['affected_refs'], 'all three overlapping flight nodes must be affected exactly' );
stress_assert( 3 === count( $overlap_plan['component_partition']['preserved_refs'] ), 'independent hotel, insurance, and ground services must remain preserved' );
stress_assert( $overlap_plan === stress_plan( $overlap ), 'identical input must produce an identical sealed plan' );

stress_scenario( 'flight-only change leaves lodging insurance and ground untouched' );
$flight_only = stress_input(
	'flightonly',
	'flight_only_change',
	array(
		stress_component( 'flightonly', 'flight', 1, array(), array( $ta ) ),
		stress_component( 'hotelonly0', 'accommodation', 2, array( 'tv_component_flightonly' ), array( $ta ) ),
		stress_component( 'insureonly', 'insurance', 3, array(), array( $ta ) ),
		stress_component( 'groundonly', 'ground', 4, array( 'tv_component_hotelonly0' ), array( $ta ) ),
	),
	array( stress_traveler( 'alpha000', 1 ) ),
	array( stress_change( 'flightonly', 'flight_schedule_changed', array( 'tv_component_flightonly' ), array( $ta ) ) ),
	'preserve_unaffected_and_revalidate_flight'
);
$flight_plan = stress_plan( $flight_only );
stress_common_proof( $flight_plan );
stress_assert( array( 'tv_component_flightonly' ) === $flight_plan['component_partition']['affected_refs'], 'only the changed flight may be affected' );
stress_assert( 3 === count( $flight_plan['component_partition']['preserved_refs'] ), 'hotel, insurance, and ground must remain preserved' );
foreach ( $flight_plan['actions'] as $action ) {
	stress_assert( ! array_intersect( $action['component_refs'], $flight_plan['component_partition']['preserved_refs'] ), 'flight action cannot touch a preserved component' );
}

stress_scenario( 'five-person package blocks only necessary people and components' );
$t1 = 'tv_traveler_party001';
$t2 = 'tv_traveler_party002';
$t3 = 'tv_traveler_party003';
$t4 = 'tv_traveler_party004';
$t5 = 'tv_traveler_party005';
$party = stress_input(
	'partyfive0',
	'five_person_package_constraints',
	array(
		stress_component( 'packageaxs', 'package', 1, array(), array( $t4 ) ),
		stress_component( 'packagebad', 'package', 2, array(), array( $t2, $t3 ) ),
		stress_component( 'packageok0', 'package', 3, array(), array( $t1 ) ),
		stress_component( 'hotelparty', 'accommodation', 4, array(), array( $t1, $t2, $t3, $t4, $t5 ) ),
	),
	array(
		stress_traveler( 'party001', 1 ),
		stress_traveler( 'party002', 2, array( 'eligibility_state' => 'unknown' ) ),
		stress_traveler( 'party003', 3, array( 'is_minor' => true, 'guardian_authority_state' => 'missing' ) ),
		stress_traveler( 'party004', 4, array( 'consent_state' => 'pending', 'accessibility_need_codes' => array( 'wheelchair' ), 'accessibility_ack_state' => 'pending' ) ),
		stress_traveler( 'party005', 5 ),
	),
	array( stress_change( 'partyfive0', 'package_party_scope_changed', array( 'tv_component_packageaxs', 'tv_component_packagebad', 'tv_component_packageok0' ), array( $t1, $t2, $t3, $t4 ), 'observed_unverified' ) ),
	'split_constrained_party_scope'
);
$party_plan = stress_plan( $party );
stress_common_proof( $party_plan );
stress_assert( array( $t1 ) === $party_plan['traveler_partition']['affected_refs'], 'eligible targeted traveler must remain actionable' );
stress_assert( array( $t2, $t3, $t4 ) === $party_plan['traveler_partition']['blocked_refs'], 'only travelers with eligibility, guardian, consent, or accessibility blockers may be blocked' );
stress_assert( array( $t5 ) === $party_plan['traveler_partition']['preserved_refs'], 'untargeted fifth traveler must remain preserved' );
stress_assert( array( 'tv_component_packageaxs', 'tv_component_packagebad' ) === $party_plan['component_partition']['blocked_refs'], 'only components containing blocked travelers may be blocked' );
stress_assert( array( 'tv_component_packageok0' ) === $party_plan['component_partition']['affected_refs'], 'eligible traveler component must stay actionable' );
stress_assert( array( 'tv_component_hotelparty' ) === $party_plan['component_partition']['preserved_refs'], 'unaffected lodging must remain preserved' );
foreach ( $party_plan['actions'] as $action ) {
	if ( array( $t2 ) === $action['traveler_refs'] || array( $t3 ) === $action['traveler_refs'] ) {
		stress_assert( array( 'tv_component_packagebad' ) === $action['component_refs'], 'travelers two and three may target only their shared package component' );
	}
	if ( array( $t4 ) === $action['traveler_refs'] ) {
		stress_assert( array( 'tv_component_packageaxs' ) === $action['component_refs'], 'traveler four may target only the accessibility package component they own' );
	}
}

stress_scenario( 'aircraft and terminal change rechecks every dependent service fact' );
$aircraft = stress_input(
	'aircraft00',
	'aircraft_or_terminal_change',
	array(
		stress_component( 'aircraftf1', 'flight', 1, array(), array( $ta ) ),
		stress_component( 'aircraftf2', 'flight', 2, array( 'tv_component_aircraftf1' ), array( $ta ) ),
		stress_component( 'aircrafth0', 'accommodation', 3, array(), array( $ta ) ),
	),
	array( stress_traveler( 'alpha000', 1, array( 'accessibility_need_codes' => array( 'wheelchair' ), 'accessibility_ack_state' => 'verified' ) ) ),
	array(
		stress_change( 'aircraft01', 'flight_aircraft_changed', array( 'tv_component_aircraftf1' ), array( $ta ) ),
		stress_change( 'aircraft02', 'flight_terminal_changed', array( 'tv_component_aircraftf2' ), array( $ta ) ),
	),
	'protected_aircraft_terminal_revalidation'
);
$aircraft_plan = stress_plan( $aircraft );
stress_common_proof( $aircraft_plan );
stress_assert( Tra_Vel_Trip_Change_Stress_Policy::RECHECK_CODES === array_column( $aircraft_plan['required_rechecks'], 'code' ), 'seat, SSR, wheelchair, baggage, and MCT rechecks must all exist in canonical order' );
foreach ( $aircraft_plan['required_rechecks'] as $recheck ) {
	stress_assert( 'pending_supplier_verification' === $recheck['truth_state'], 'recheck cannot claim a supplier result' );
	stress_assert( false === $recheck['supplier_action_claimed'], 'recheck cannot claim supplier execution' );
}
stress_assert( array( 'tv_component_aircrafth0' ) === $aircraft_plan['component_partition']['preserved_refs'], 'aircraft change must preserve unrelated lodging' );

$gtfs_inputs = array();
$gtfs_plans = array();
foreach ( array( 'stale', 'unavailable' ) as $gtfs_state ) {
	stress_scenario( "Israel GTFS {$gtfs_state} fails closed to official and human channels" );
	$gtfs_source = stress_source( $gtfs_state );
	$gtfs_change = stress_change( 'gtfs' . $gtfs_state, 'israel_gtfs_source_degraded', array( 'tv_component_gtfsroute0' ), array( $ta ), $gtfs_state );
	$gtfs_change['source_expires_at'] = $gtfs_source['expires_at'];
	$gtfs_change['evidence_digest'] = $gtfs_source['evidence_digest'];
	$gtfs = stress_input(
		'gtfs' . $gtfs_state,
		'israel_gtfs_degraded',
		array(
			stress_component( 'gtfsroute0', 'ground', 1, array(), array( $ta ) ),
			stress_component( 'gtfshotel0', 'accommodation', 2, array(), array( $ta ) ),
		),
		array( stress_traveler( 'alpha000', 1 ) ),
		array( $gtfs_change ),
		'official_channel_and_human_transit_fallback',
		$gtfs_source
	);
	$gtfs_plan = stress_plan( $gtfs );
	$gtfs_inputs[ $gtfs_state ] = $gtfs;
	$gtfs_plans[ $gtfs_state ] = $gtfs_plan;
	stress_common_proof( $gtfs_plan );
	stress_assert( array( 'tv_component_gtfsroute0' ) === $gtfs_plan['component_partition']['blocked_refs'], 'only route-claim scope may be blocked by degraded official data' );
	stress_assert( array( 'tv_component_gtfshotel0' ) === $gtfs_plan['component_partition']['preserved_refs'], 'local lodging must remain preserved' );
	stress_assert( false === $gtfs_plan['transit_source_gate']['route_claim_allowed'], 'stale or unavailable GTFS cannot produce a route claim' );
	stress_assert( false === $gtfs_plan['transit_source_gate']['current_route_claim_present'], 'planner cannot invent current route availability' );
	stress_assert( Tra_Vel_Trip_Change_Stress_Policy::OFFICIAL_FALLBACK_CHANNELS === $gtfs_plan['transit_source_gate']['fallback_channels'], 'official planner, operator, and human channels must all remain available' );
}

stress_scenario( 'sealed plan rejects authority and partition attacks' );
$tampered = $flight_plan;
$tampered['component_partition']['preserved_refs'][] = 'tv_component_flightonly';
stress_expect_plan_error( $tampered, $flight_only, 'overlapping affected and preserved partitions must fail' );
$tampered = $flight_plan;
$tampered['private_boundary']['supplier_dispatched'] = true;
stress_expect_plan_error( $tampered, $flight_only, 'supplier dispatch claim must fail even after resealing' );
$tampered = $aircraft_plan;
array_pop( $tampered['required_rechecks'] );
$tampered['summary']['required_recheck_count'] = 4;
stress_expect_plan_error( $tampered, $aircraft, 'incomplete aircraft/terminal recheck bundle must fail' );

stress_scenario( 'scenario partitions and traveler ownership reject unrelated scope' );
$tampered = $overlap_plan;
$tampered['component_partition']['preserved_refs'] = array_values( array_diff( $tampered['component_partition']['preserved_refs'], array( 'tv_component_hotelxxx00' ) ) );
$tampered['component_partition']['affected_refs'][] = 'tv_component_hotelxxx00';
sort( $tampered['component_partition']['affected_refs'], SORT_STRING );
foreach ( $tampered['recovery_candidates'] as &$candidate ) {
	$candidate['target_component_refs'][] = 'tv_component_hotelxxx00';
	sort( $candidate['target_component_refs'], SORT_STRING );
}
unset( $candidate );
$tampered['summary']['affected_component_count'] = 4;
$tampered['summary']['preserved_component_count'] = 2;
stress_expect_plan_error( $tampered, $overlap, 'overlap partition cannot move an unrelated hotel into affected scope' );

$tampered = $party_plan;
$tampered['component_partition']['preserved_refs'] = array();
$tampered['component_partition']['affected_refs'][] = 'tv_component_hotelparty';
sort( $tampered['component_partition']['affected_refs'], SORT_STRING );
foreach ( $tampered['recovery_candidates'] as &$candidate ) {
	$candidate['target_component_refs'][] = 'tv_component_hotelparty';
	sort( $candidate['target_component_refs'], SORT_STRING );
}
unset( $candidate );
$tampered['summary']['affected_component_count'] = 2;
$tampered['summary']['preserved_component_count'] = 0;
stress_expect_plan_error( $tampered, $party, 'five-person partition cannot move unrelated lodging into affected scope' );

$tampered = $party_plan;
foreach ( $tampered['actions'] as &$action ) {
	if ( array( $t2 ) === $action['traveler_refs'] ) {
		$action['component_refs'][] = 'tv_component_packageaxs';
		sort( $action['component_refs'], SORT_STRING );
		$action['action_ref'] = Tra_Vel_Trip_Change_Stress_Policy::ref_value( 'stress_action', $action['code'] . '|' . implode( '|', $action['component_refs'] ) . '|' . implode( '|', $action['traveler_refs'] ) );
		break;
	}
}
unset( $action );
$tampered['actions'] = stress_sort_actions( $tampered['actions'] );
stress_expect_plan_error( $tampered, $party, 'one blocked traveler cannot inherit another traveler component' );

stress_scenario( 'false-like type confusion cannot cross zero-authority boundaries' );
foreach ( array( 'execution_authorized', 'supplier_action_claimed', 'commercial_fact_claimed' ) as $field ) {
	$tampered = $overlap_plan;
	$tampered['recovery_candidates'][0][ $field ] = 0;
	stress_expect_plan_error( $tampered, $overlap, "candidate {$field} must be the boolean false" );
}
$tampered = $flight_plan;
$tampered['actions'][0]['supplier_action_claimed'] = 0;
stress_expect_plan_error( $tampered, $flight_only, 'action supplier claim must be the boolean false' );
foreach ( array( 'execution_authorized', 'supplier_action_claimed' ) as $field ) {
	$tampered = $aircraft_plan;
	$tampered['required_rechecks'][0][ $field ] = 0;
	stress_expect_plan_error( $tampered, $aircraft, "recheck {$field} must be the boolean false" );
}
foreach ( array( 'route_claim_allowed', 'current_route_claim_present' ) as $field ) {
	$tampered = $gtfs_plans['stale'];
	$tampered['transit_source_gate'][ $field ] = 0;
	stress_expect_plan_error( $tampered, $gtfs_inputs['stale'], "GTFS {$field} must be the boolean false" );
}
$tampered = $gtfs_plans['stale'];
$tampered['transit_source_gate']['human_review_required'] = 1;
stress_expect_plan_error( $tampered, $gtfs_inputs['stale'], 'GTFS human-review flag must be the boolean true' );
$tampered = $flight_plan;
$tampered['transit_source_gate']['human_review_required'] = 0;
stress_expect_plan_error( $tampered, $flight_only, 'non-transit human-review flag must be the boolean false' );

stress_scenario( 'selected recovery requires the exact impacted scope' );
$tampered = $overlap_plan;
foreach ( $tampered['recovery_candidates'] as &$candidate ) {
	if ( $candidate['candidate_ref'] === $tampered['selected_candidate_ref'] ) {
		array_pop( $candidate['target_component_refs'] );
		break;
	}
}
unset( $candidate );
stress_expect_plan_error( $tampered, $overlap, 'selected recovery cannot omit one impacted connection component' );

stress_scenario( 'canonical ordering and real UTC dates reject ambiguous seals' );
$reversed_input = $overlap;
$reversed_input['changes'] = array_reverse( $reversed_input['changes'] );
stress_assert( is_wp_error( Tra_Vel_Trip_Change_Stress_Policy::input( $reversed_input ) ), 'reversed change set must fail canonical input order' );
$reversed_input = $overlap;
$reversed_input['components'][3]['traveler_refs'] = array_reverse( $reversed_input['components'][3]['traveler_refs'] );
stress_assert( is_wp_error( Tra_Vel_Trip_Change_Stress_Policy::input( $reversed_input ) ), 'reversed component traveler set must fail canonical order' );
$tampered = $overlap_plan;
$tampered['component_partition']['affected_refs'] = array_reverse( $tampered['component_partition']['affected_refs'] );
stress_expect_plan_error( $tampered, $overlap, 'reversed affected partition must fail canonical order' );
$tampered = $overlap_plan;
$tampered['recovery_candidates'] = array_reverse( $tampered['recovery_candidates'] );
stress_expect_plan_error( $tampered, $overlap, 'reversed candidate set must fail canonical strategy order' );
$tampered = $overlap_plan;
$tampered['actions'] = array_reverse( $tampered['actions'] );
stress_expect_plan_error( $tampered, $overlap, 'reversed action set must fail canonical reference order' );
$invalid_date = $flight_only;
$invalid_date['observed_at'] = '2026-02-31T12:00:00Z';
stress_assert( is_wp_error( Tra_Vel_Trip_Change_Stress_Policy::input( $invalid_date ) ), 'February 31 must fail checkdate and UTC round-trip validation' );

stress_scenario( 'GTFS degraded truth is exact and current state is out of scope' );
$mismatched_gtfs = $gtfs_inputs['stale'];
$mismatched_gtfs['changes'][0]['truth_state'] = 'unavailable';
$mismatched_gtfs['changes'][0]['source_expires_at'] = null;
$mismatched_gtfs['changes'][0]['evidence_digest'] = null;
stress_assert( is_wp_error( Tra_Vel_Trip_Change_Stress_Policy::input( $mismatched_gtfs ) ), 'unavailable change truth cannot bind a stale official source' );
$current_gtfs = $gtfs_inputs['stale'];
$current_gtfs['official_transit_source']['state'] = 'current';
$current_gtfs['official_transit_source']['expires_at'] = '2026-07-19T13:00:00Z';
$current_gtfs['official_transit_source']['evidence_digest'] = stress_hash( 'current-gtfs' );
$current_gtfs['changes'][0]['truth_state'] = 'verified_current';
$current_gtfs['changes'][0]['source_expires_at'] = '2026-07-19T13:00:00Z';
$current_gtfs['changes'][0]['evidence_digest'] = stress_hash( 'current-gtfs' );
stress_assert( is_wp_error( Tra_Vel_Trip_Change_Stress_Policy::input( $current_gtfs ) ), 'current GTFS is explicitly outside the degraded-source stress vocabulary' );

$serialized = wp_json_encode( array( $overlap_plan, $flight_plan, $party_plan, $aircraft_plan ), JSON_UNESCAPED_SLASHES );
foreach ( array( 'price', 'amount', 'card_number', 'passport_number', 'raw_supplier_payload', 'bearer_token' ) as $forbidden ) {
	stress_assert( false === strpos( $serialized, $forbidden ), "private plans must not expose {$forbidden}" );
}
stress_assert( $scenarios >= 12, 'runtime must cover five explicit cases, both GTFS degradation states, and focused adversarial attacks' );

echo "Trip-change stress runtime passed ({$assertions} assertions; {$scenarios} scenarios; exhaustive partitions; one selected recovery; zero execution).\n";
