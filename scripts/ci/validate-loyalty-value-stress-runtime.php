<?php
/** Focused runtime proofs for private loyalty and stored-value stress ledgers. */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

$commerce = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/';
require_once $commerce . 'class-tra-vel-loyalty-value-stress-policy.php';
require_once $commerce . 'class-tra-vel-loyalty-value-stress-factory.php';

$lv_assertions = 0;
function lv_assert( $condition, $message ) {
	global $lv_assertions;
	$lv_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "Loyalty value stress runtime failed: {$message}\n" );
		exit( 1 );
	}
}
function lv_error( $value, $message ) { lv_assert( is_wp_error( $value ), $message ); }
function lv_digest( $character ) { return str_repeat( $character, 64 ); }
function lv_ref( $prefix, $number ) { return $prefix . '_' . str_pad( (string) $number, 16, '0', STR_PAD_LEFT ); }
function lv_clone( $value ) { return json_decode( json_encode( $value ), true ); }
function lv_boundary() {
	return array(
		'server_only' => true, 'public_serialization_allowed' => false, 'simulation_only' => true,
		'execution_authorized' => false, 'account_merge_authorized' => false,
		'accrual_credit_authorized' => false, 'redemption_authorized' => false,
		'voucher_consumption_authorized' => false, 'refund_authorized' => false,
		'supplier_dispatched' => false, 'provider_called' => false, 'processor_called' => false,
		'ledger_mutated' => false, 'message_sent' => false,
	);
}

$factory = new Tra_Vel_Loyalty_Value_Stress_Factory( 'loyalty-stress-test-secret-2026' );
$now = strtotime( '2026-09-01T00:00:00Z' );

function lv_merge_draft() {
	$source_lots = array( lv_ref( 'value_lot', 1 ), lv_ref( 'value_lot', 2 ), lv_ref( 'value_lot', 3 ), lv_ref( 'value_lot', 4 ) );
	$target_lots = array( lv_ref( 'value_lot', 5 ), lv_ref( 'value_lot', 6 ), lv_ref( 'value_lot', 7 ) );
	sort( $source_lots, SORT_STRING ); sort( $target_lots, SORT_STRING );
	$events = array( lv_digest( 'b' ), lv_digest( 'c' ) ); sort( $events, SORT_STRING );
	return array(
		'contract_version' => '1.1.0', 'environment' => 'simulation', 'merge_ref' => '', 'record_digest' => '',
		'idempotency_digest' => lv_digest( 'a' ), 'account_scope_digest' => lv_digest( 'd' ),
		'program_ref' => lv_ref( 'program', 1 ), 'unit_code' => 'demo_points',
		'source_member_ref' => lv_ref( 'member', 1 ), 'target_member_ref' => lv_ref( 'member', 2 ),
		'source_snapshot_digest' => lv_digest( 'b' ), 'target_snapshot_digest' => lv_digest( 'c' ),
		'pre_merge' => array(
			'source' => array( 'member_ref' => lv_ref( 'member', 1 ), 'available_integer' => 1000, 'pending_integer' => 200, 'disputed_integer' => 100, 'expired_integer' => 50, 'lots' => array(
				array( 'lot_ref' => lv_ref( 'value_lot', 1 ), 'state' => 'available', 'amount_integer' => 1000, 'evidence_digest' => lv_digest( '1' ) ),
				array( 'lot_ref' => lv_ref( 'value_lot', 2 ), 'state' => 'pending', 'amount_integer' => 200, 'evidence_digest' => lv_digest( '2' ) ),
				array( 'lot_ref' => lv_ref( 'value_lot', 3 ), 'state' => 'disputed', 'amount_integer' => 100, 'evidence_digest' => lv_digest( '3' ) ),
				array( 'lot_ref' => lv_ref( 'value_lot', 4 ), 'state' => 'expired', 'amount_integer' => 50, 'evidence_digest' => lv_digest( '4' ) ),
			) ),
			'target' => array( 'member_ref' => lv_ref( 'member', 2 ), 'available_integer' => 500, 'pending_integer' => 50, 'disputed_integer' => 0, 'expired_integer' => 10, 'lots' => array(
				array( 'lot_ref' => lv_ref( 'value_lot', 5 ), 'state' => 'available', 'amount_integer' => 500, 'evidence_digest' => lv_digest( '5' ) ),
				array( 'lot_ref' => lv_ref( 'value_lot', 6 ), 'state' => 'pending', 'amount_integer' => 50, 'evidence_digest' => lv_digest( '6' ) ),
				array( 'lot_ref' => lv_ref( 'value_lot', 7 ), 'state' => 'expired', 'amount_integer' => 10, 'evidence_digest' => lv_digest( '7' ) ),
			) ),
		),
		'resolution' => array(
			'survivor_member_ref' => lv_ref( 'member', 2 ), 'retired_member_ref' => lv_ref( 'member', 1 ),
			'transfer_lots' => array(
				array( 'lot_ref' => lv_ref( 'value_lot', 1 ), 'original_member_ref' => lv_ref( 'member', 1 ), 'state' => 'available', 'amount_integer' => 1000, 'evidence_digest' => lv_digest( '1' ) ),
				array( 'lot_ref' => lv_ref( 'value_lot', 2 ), 'original_member_ref' => lv_ref( 'member', 1 ), 'state' => 'pending', 'amount_integer' => 200, 'evidence_digest' => lv_digest( '2' ) ),
				array( 'lot_ref' => lv_ref( 'value_lot', 3 ), 'original_member_ref' => lv_ref( 'member', 1 ), 'state' => 'disputed', 'amount_integer' => 100, 'evidence_digest' => lv_digest( '3' ) ),
				array( 'lot_ref' => lv_ref( 'value_lot', 4 ), 'original_member_ref' => lv_ref( 'member', 1 ), 'state' => 'expired', 'amount_integer' => 50, 'evidence_digest' => lv_digest( '4' ) ),
			),
			'preserved_target_lot_refs' => $target_lots,
			'post_merge_totals' => array( 'available_integer' => 1500, 'pending_integer' => 250, 'disputed_integer' => 100, 'expired_integer' => 60 ),
			'double_credit_integer' => 0, 'duplicate_identity_evidence_digest' => lv_digest( 'e' ),
		),
		'audit_lineage' => array( 'operation_ref' => '', 'sequence' => 1, 'previous_operation_digest' => null, 'source_event_digests' => $events, 'lineage_digest' => '', 'immutable' => true ),
		'created_at' => '2026-07-19T10:00:00Z', 'boundary' => lv_boundary(),
	);
}

$merge = $factory->create_member_merge( lv_merge_draft(), $now );
lv_assert( ! is_wp_error( $merge ), 'duplicate-member merge must validate' );
lv_assert( 0 === $merge['resolution']['double_credit_integer'], 'merge must explicitly conserve zero duplicate credit' );
lv_assert( 1500 === $merge['resolution']['post_merge_totals']['available_integer'], 'available value must be conserved' );
lv_assert( 250 === $merge['resolution']['post_merge_totals']['pending_integer'], 'pending value must be conserved independently' );
lv_assert( 100 === $merge['resolution']['post_merge_totals']['disputed_integer'], 'disputed value must be conserved independently' );
lv_assert( 60 === $merge['resolution']['post_merge_totals']['expired_integer'], 'expired value must be conserved independently' );
lv_assert( true === $merge['audit_lineage']['immutable'] && 2 === count( $merge['audit_lineage']['source_event_digests'] ), 'merge lineage must bind both snapshots' );
$merge_again = $factory->create_member_merge( lv_merge_draft(), $now );
lv_assert( $merge['merge_ref'] === $merge_again['merge_ref'] && $merge['record_digest'] === $merge_again['record_digest'], 'same merge input must be deterministic' );
$merge_other = lv_merge_draft(); $merge_other['idempotency_digest'] = lv_digest( 'f' );
$merge_other = $factory->create_member_merge( $merge_other, $now );
lv_assert( $merge['merge_ref'] !== $merge_other['merge_ref'], 'different idempotency digest must create a different merge reference' );
$bad_merge = lv_merge_draft(); $bad_merge['resolution']['double_credit_integer'] = 1;
lv_error( $factory->create_member_merge( $bad_merge, $now ), 'nonzero double credit must fail' );
$bad_merge = lv_merge_draft(); $bad_merge['resolution']['post_merge_totals']['available_integer']++;
lv_error( $factory->create_member_merge( $bad_merge, $now ), 'value creation during merge must fail' );
$bad_merge = lv_merge_draft(); array_pop( $bad_merge['resolution']['transfer_lots'] );
lv_error( $factory->create_member_merge( $bad_merge, $now ), 'missing source lot must fail' );
$bad_merge = lv_merge_draft(); $bad_merge['resolution']['transfer_lots'][0]['evidence_digest'] = lv_digest( '0' );
lv_error( $factory->create_member_merge( $bad_merge, $now ), 'transfer lot must preserve exact source evidence lineage' );
$bad_merge = lv_merge_draft(); $bad_merge['pre_merge']['source']['lots'][0]['amount_integer'] = 999;
lv_error( $factory->create_member_merge( $bad_merge, $now ), 'source per-lot amounts must reconcile to source state totals' );
$changed_merge_draft = lv_merge_draft(); $changed_merge_draft['resolution']['duplicate_identity_evidence_digest'] = lv_digest( 'f' );
$changed_merge = $factory->create_member_merge( $changed_merge_draft, $now );
lv_assert( ! is_wp_error( $changed_merge ) && $merge['merge_ref'] !== $changed_merge['merge_ref'], 'full immutable input change must create a different deterministic operation reference' );
lv_assert( ! is_wp_error( $factory->create_member_merge( lv_merge_draft(), $now, $merge['merge_ref'] ) ), 'same expected operation reference with identical immutable input must replay deterministically' );
lv_error( $factory->create_member_merge( $changed_merge_draft, $now, $merge['merge_ref'] ), 'same expected operation reference with changed immutable input must fail as an idempotency conflict' );
$tampered_merge = lv_clone( $merge ); $tampered_merge['resolution']['post_merge_totals']['pending_integer']++;
lv_error( Tra_Vel_Loyalty_Value_Stress_Policy::validate_member_merge( $tampered_merge, $now ), 'post-seal merge mutation must fail' );

function lv_accrual_draft( $state ) {
	$observed = 'expired' === $state ? '2026-08-02T09:00:00Z' : '2026-07-19T09:00:00Z';
	$expiry = '2026-08-01T00:00:00Z';
	$amounts = array( 'credited_integer' => 0, 'pending_integer' => 0, 'disputed_integer' => 0, 'expired_integer' => 0, 'rejected_integer' => 0 );
	$amounts[ $state . '_integer' ] = 1000;
	$claim = 'disputed' === $state ? lv_digest( '9' ) : null;
	$deadline = in_array( $state, array( 'pending', 'disputed' ), true ) ? '2026-07-20T09:00:00Z' : null;
	return array(
		'contract_version' => '1.1.0', 'environment' => 'simulation', 'accrual_case_ref' => '', 'record_digest' => '',
		'idempotency_digest' => hash( 'sha256', 'accrual-' . $state ), 'account_scope_digest' => lv_digest( 'a' ),
		'program_ref' => lv_ref( 'program', 2 ), 'member_ref' => lv_ref( 'member', 3 ),
		'purchase_ref' => lv_ref( 'purchase', 1 ), 'order_item_ref' => lv_ref( 'tv_order_item', 1 ), 'unit_code' => 'demo_points',
		'bill' => array( 'state' => 'posted', 'currency' => 'ILS', 'amount_minor' => 50000, 'posted_at' => '2026-07-19T08:00:00Z', 'evidence_digest' => lv_digest( 'b' ) ),
		'accrual' => array_merge( array( 'state' => $state, 'expected_integer' => 1000 ), $amounts, array( 'unit_code' => 'demo_points', 'eligibility_basis_digest' => lv_digest( 'c' ), 'expiry_at' => $expiry ) ),
		'timeline' => array_values( array_filter( array(
			array( 'sequence' => 1, 'state' => 'expected', 'occurred_at' => '2026-07-19T08:05:00Z', 'evidence_digest' => lv_digest( 'd' ) ),
			in_array( $state, array( 'disputed', 'expired' ), true ) ? array( 'sequence' => 2, 'state' => 'pending', 'occurred_at' => '2026-07-19T08:30:00Z', 'evidence_digest' => lv_digest( '8' ) ) : null,
			array( 'sequence' => in_array( $state, array( 'disputed', 'expired' ), true ) ? 3 : 2, 'state' => $state, 'occurred_at' => $observed, 'evidence_digest' => lv_digest( 'e' ) ),
		) ) ),
		'resolution' => array( 'case_ref' => lv_ref( 'service_case', 1 ), 'next_action_code' => 'expired' === $state ? 'review_expired_accrual' : 'reconcile_provider_accrual', 'deadline_at' => $deadline, 'provider_claim_reference_digest' => $claim, 'bill_posted_implies_credit' => false, 'automatic_credit_allowed' => false ),
		'observed_at' => $observed, 'boundary' => lv_boundary(),
	);
}

foreach ( array( 'pending', 'disputed', 'expired' ) as $state ) {
	$case = $factory->create_accrual_case( lv_accrual_draft( $state ), $now );
	lv_assert( ! is_wp_error( $case ), "posted-bill {$state} accrual case must validate" );
	lv_assert( 'posted' === $case['bill']['state'] && 0 === $case['accrual']['credited_integer'], "posted bill must not imply credit for {$state}" );
	lv_assert( false === $case['resolution']['bill_posted_implies_credit'] && false === $case['resolution']['automatic_credit_allowed'], "{$state} resolution must stay non-automatic" );
}
$bad_accrual = lv_accrual_draft( 'pending' ); $bad_accrual['accrual']['credited_integer'] = 1000;
lv_error( $factory->create_accrual_case( $bad_accrual, $now ), 'pending and credited value cannot double count' );
$bad_accrual = lv_accrual_draft( 'disputed' ); $bad_accrual['resolution']['provider_claim_reference_digest'] = null;
lv_error( $factory->create_accrual_case( $bad_accrual, $now ), 'dispute requires opaque claim proof' );
$bad_accrual = lv_accrual_draft( 'pending' ); $bad_accrual['resolution']['deadline_at'] = null;
lv_error( $factory->create_accrual_case( $bad_accrual, $now ), 'open exception requires a future deadline' );
$bad_accrual = lv_accrual_draft( 'expired' ); $bad_accrual['accrual']['expiry_at'] = '2026-09-01T00:00:00Z';
lv_error( $factory->create_accrual_case( $bad_accrual, $now ), 'future expiry cannot be labeled expired' );
$bad_accrual = lv_accrual_draft( 'disputed' ); array_splice( $bad_accrual['timeline'], 1, 1 ); $bad_accrual['timeline'][1]['sequence'] = 2;
lv_error( $factory->create_accrual_case( $bad_accrual, $now ), 'direct expected to disputed transition must fail the closed graph' );
$bad_accrual = lv_accrual_draft( 'disputed' ); $bad_accrual['timeline'][] = array( 'sequence' => 4, 'state' => 'pending', 'occurred_at' => '2026-07-19T09:00:00Z', 'evidence_digest' => lv_digest( '0' ) );
lv_error( $factory->create_accrual_case( $bad_accrual, $now ), 'backward disputed to pending transition must fail' );
$bad_accrual = lv_accrual_draft( 'disputed' ); $bad_accrual['accrual']['disputed_integer'] = 0; $bad_accrual['accrual']['pending_integer'] = 1000;
lv_error( $factory->create_accrual_case( $bad_accrual, $now ), 'current accrual state must own the full value partition without contradiction' );

function lv_redemption_draft() {
	$travelers = array(); $segments = array( lv_ref( 'segment', 1 ), lv_ref( 'segment', 2 ) );
	$components = array(); $traveler_totals = array(); $partitions = array(); $index = 1;
	for ( $i = 1; $i <= 10; $i++ ) {
		$traveler = lv_ref( 'traveler', $i ); $travelers[] = $traveler;
		$segment = $segments[ ( $i - 1 ) % 2 ]; $pair_refs = array();
		foreach ( array( array( 'base_fare', 1000, 1000 ), array( 'tax', 200, 0 ), array( 'service_fee', 100, 0 ) ) as $spec ) {
			$component_ref = lv_ref( 'loyalty_component', $index++ ); $pair_refs[] = $component_ref;
			$components[] = array( 'component_ref' => $component_ref, 'traveler_ref' => $traveler, 'segment_ref' => $segment, 'component_type' => $spec[0], 'cash_minor' => $spec[1], 'points_integer' => $spec[2], 'evidence_digest' => hash( 'sha256', $component_ref ) );
		}
		sort( $pair_refs, SORT_STRING );
		$partitions[] = array( 'traveler_ref' => $traveler, 'segment_ref' => $segment, 'affected_component_refs' => 1 === $i ? $pair_refs : array(), 'preserved_component_refs' => 1 === $i ? array() : $pair_refs );
		$traveler_totals[] = array( 'traveler_ref' => $traveler, 'cash_minor' => 1300, 'points_integer' => 1000 );
	}
	sort( $travelers, SORT_STRING ); sort( $segments, SORT_STRING );
	$affected_travelers = array( $travelers[0] ); $preserved_travelers = array_slice( $travelers, 1 );
	$affected_components = array(); $preserved_components = array(); $refunds = array();
	foreach ( $components as $component ) {
		if ( $component['traveler_ref'] === $travelers[0] ) {
			$affected_components[] = $component['component_ref'];
			$refunds[] = array( 'component_ref' => $component['component_ref'], 'traveler_ref' => $component['traveler_ref'], 'segment_ref' => $component['segment_ref'], 'cash_refund_minor' => $component['cash_minor'], 'points_reversal_integer' => $component['points_integer'], 'evidence_digest' => hash( 'sha256', 'refund-' . $component['component_ref'] ) );
		} else { $preserved_components[] = $component['component_ref']; }
	}
	sort( $affected_components, SORT_STRING ); sort( $preserved_components, SORT_STRING );
	return array(
		'contract_version' => '1.1.0', 'environment' => 'simulation', 'redemption_ref' => '', 'record_digest' => '',
		'idempotency_digest' => lv_digest( '6' ), 'account_scope_digest' => lv_digest( '7' ),
		'trip_ref' => lv_ref( 'trip', 1 ), 'program_ref' => lv_ref( 'program', 3 ), 'unit_code' => 'demo_points', 'currency' => 'ILS',
		'traveler_refs' => $travelers, 'segment_refs' => $segments, 'components' => $components, 'traveler_totals' => $traveler_totals,
		'cancellation_scope' => array( 'affected_traveler_refs' => $affected_travelers, 'preserved_traveler_refs' => $preserved_travelers, 'affected_component_refs' => $affected_components, 'preserved_component_refs' => $preserved_components, 'traveler_segment_partitions' => $partitions, 'refund_components' => $refunds, 'cross_party_reallocation_allowed' => false, 'silent_netting_allowed' => false ),
		'totals' => array( 'cash_minor' => 13000, 'points_integer' => 10000, 'refund_cash_minor' => 1300, 'points_reversal_integer' => 1000 ),
		'created_at' => '2026-07-19T10:00:00Z', 'boundary' => lv_boundary(),
	);
}

$redemption = $factory->create_cash_points_redemption( lv_redemption_draft(), $now );
lv_assert( ! is_wp_error( $redemption ), 'ten-traveler componentized cash plus points plan must validate' );
lv_assert( 10 === count( $redemption['traveler_refs'] ) && 30 === count( $redemption['components'] ), 'ten travelers must retain thirty explicit fare, tax, and fee components' );
lv_assert( 10 === count( $redemption['cancellation_scope']['traveler_segment_partitions'] ), 'every traveler-segment pair must have an explicit partition' );
lv_assert( 1 === count( $redemption['cancellation_scope']['affected_traveler_refs'] ) && 9 === count( $redemption['cancellation_scope']['preserved_traveler_refs'] ), 'partial cancellation must preserve nine travelers' );
lv_assert( false === $redemption['cancellation_scope']['cross_party_reallocation_allowed'] && false === $redemption['cancellation_scope']['silent_netting_allowed'], 'cross-party movement and netting must be prohibited' );
lv_assert( 1300 === $redemption['totals']['refund_cash_minor'] && 1000 === $redemption['totals']['points_reversal_integer'], 'refund cash and points reversal must remain separate' );
$redemption_again = $factory->create_cash_points_redemption( lv_redemption_draft(), $now );
lv_assert( $redemption['redemption_ref'] === $redemption_again['redemption_ref'], 'redemption reference must be deterministic' );
$bad_redemption = lv_redemption_draft(); $bad_redemption['traveler_refs'][] = lv_ref( 'traveler', 11 ); sort( $bad_redemption['traveler_refs'], SORT_STRING );
lv_error( $factory->create_cash_points_redemption( $bad_redemption, $now ), 'more than ten travelers must fail' );
$bad_redemption = lv_redemption_draft(); $bad_redemption['traveler_totals'][0]['cash_minor']++;
lv_error( $factory->create_cash_points_redemption( $bad_redemption, $now ), 'traveler total cannot absorb another component' );
$bad_redemption = lv_redemption_draft(); $bad_redemption['cancellation_scope']['affected_component_refs'][0] = $bad_redemption['cancellation_scope']['preserved_component_refs'][0]; sort( $bad_redemption['cancellation_scope']['affected_component_refs'], SORT_STRING );
lv_error( $factory->create_cash_points_redemption( $bad_redemption, $now ), 'affected component cannot silently cross to a preserved traveler' );
$bad_redemption = lv_redemption_draft(); $bad_redemption['cancellation_scope']['refund_components'][0]['cash_refund_minor'] = 999999;
lv_error( $factory->create_cash_points_redemption( $bad_redemption, $now ), 'refund cannot exceed its exact component' );
$bad_redemption = lv_redemption_draft(); array_pop( $bad_redemption['cancellation_scope']['traveler_segment_partitions'] );
lv_error( $factory->create_cash_points_redemption( $bad_redemption, $now ), 'missing traveler-segment partition must fail' );
$bad_redemption = lv_redemption_draft(); $bad_redemption['segment_refs'][] = lv_ref( 'segment', 3 ); sort( $bad_redemption['segment_refs'], SORT_STRING );
lv_error( $factory->create_cash_points_redemption( $bad_redemption, $now ), 'every listed segment must own at least one component' );
$bad_redemption = lv_redemption_draft(); $second_affected = array_shift( $bad_redemption['cancellation_scope']['preserved_traveler_refs'] ); $bad_redemption['cancellation_scope']['affected_traveler_refs'][] = $second_affected; sort( $bad_redemption['cancellation_scope']['affected_traveler_refs'], SORT_STRING );
lv_error( $factory->create_cash_points_redemption( $bad_redemption, $now ), 'every affected traveler must own at least one affected component' );
$bad_redemption = lv_redemption_draft(); $bad_redemption['cancellation_scope']['traveler_segment_partitions'][9] = $bad_redemption['cancellation_scope']['traveler_segment_partitions'][0];
lv_error( $factory->create_cash_points_redemption( $bad_redemption, $now ), 'traveler-segment partitions must be unique and exhaustive' );

function lv_voucher_draft( $expired = false ) {
	$applied = $expired ? 0 : 3000;
	return array(
		'contract_version' => '1.1.0', 'environment' => 'simulation', 'voucher_ref' => '', 'record_digest' => '',
		'idempotency_digest' => $expired ? lv_digest( '8' ) : lv_digest( '9' ), 'account_scope_digest' => lv_digest( 'a' ),
		'program_ref' => lv_ref( 'program', 4 ), 'issuer_ref' => lv_ref( 'issuer', 1 ),
		'owner_reference_digest' => lv_digest( 'b' ), 'beneficiary_reference_digest' => lv_digest( 'c' ), 'presented_beneficiary_reference_digest' => lv_digest( 'c' ),
		'currency' => 'USD', 'minor_unit_exponent' => 2,
		'fx_basis' => array( 'source_currency' => 'USD', 'settlement_currency' => 'ILS', 'source_minor_unit_exponent' => 2, 'settlement_minor_unit_exponent' => 2, 'rate_numerator' => 365, 'rate_denominator' => 100, 'rounding_mode' => 'floor_minor_unit', 'observed_at' => '2026-07-19T09:00:00Z', 'valid_until' => '2026-09-01T00:00:00Z', 'source_digest' => lv_digest( 'd' ) ),
		'value' => array( 'face_value_minor' => 10000, 'consumed_before_minor' => 2000, 'available_before_minor' => 8000, 'requested_consumption_minor' => 3000, 'applied_consumption_minor' => $applied, 'remaining_after_minor' => 8000 - $applied ),
		'expiry' => array( 'issued_at' => '2026-01-01T00:00:00Z', 'expires_at' => '2026-08-01T00:00:00Z', 'evaluated_at' => $expired ? '2026-08-02T10:00:00Z' : '2026-07-19T10:00:00Z', 'state' => $expired ? 'expired' : 'current' ),
		'restrictions' => array( 'transferability' => 'designated_beneficiary', 'beneficiary_match_required' => true, 'partial_consumption_allowed' => true, 'stacking_allowed' => false, 'permitted_verticals' => array( 'flight' ), 'permitted_supplier_refs' => array( lv_ref( 'supplier', 1 ) ), 'minimum_purchase_minor' => 1000, 'evidence_digest' => lv_digest( 'e' ) ),
		'consumption' => array( 'operation_ref' => '', 'purchase_ref' => lv_ref( 'purchase', 2 ), 'vertical' => 'flight', 'supplier_ref' => lv_ref( 'supplier', 1 ), 'settlement_currency' => 'ILS', 'purchase_total_minor' => 20000, 'source_amount_minor' => $applied, 'settlement_amount_minor' => $expired ? 0 : 10950, 'rounding_remainder_numerator' => 0, 'state' => $expired ? 'blocked_expired' : 'planned', 'blocked_reason' => $expired ? 'voucher_expired' : null, 'consumption_at' => $expired ? null : '2026-07-19T10:00:00Z', 'evidence_digest' => lv_digest( 'f' ) ),
		'audit_lineage' => array( 'operation_ref' => '', 'sequence' => 1, 'previous_operation_digest' => null, 'source_event_digests' => array( lv_digest( '1' ), lv_digest( '2' ) ), 'lineage_digest' => '', 'immutable' => true ),
		'created_at' => $expired ? '2026-08-02T10:00:00Z' : '2026-07-19T10:00:00Z', 'boundary' => lv_boundary(),
	);
}

$voucher = $factory->create_voucher_ledger( lv_voucher_draft(), $now );
lv_assert( ! is_wp_error( $voucher ), 'designated-beneficiary partial voucher plan must validate' );
lv_assert( $voucher['owner_reference_digest'] !== $voucher['beneficiary_reference_digest'], 'owner and beneficiary must remain independently modeled' );
lv_assert( 10000 === $voucher['value']['face_value_minor'] && 5000 === $voucher['value']['remaining_after_minor'], 'face and remaining values must reconcile after partial use' );
lv_assert( 365 === $voucher['fx_basis']['rate_numerator'] && 100 === $voucher['fx_basis']['rate_denominator'], 'FX basis must remain an integer rational' );
lv_assert( 10950 === $voucher['consumption']['settlement_amount_minor'], 'converted settlement value must match exact FX basis' );
lv_assert( $voucher['consumption']['operation_ref'] === $voucher['audit_lineage']['operation_ref'], 'voucher consumption must bind immutable audit lineage' );
$expired_voucher = $factory->create_voucher_ledger( lv_voucher_draft( true ), $now );
lv_assert( ! is_wp_error( $expired_voucher ) && 'blocked_expired' === $expired_voucher['consumption']['state'], 'expired voucher must remain blocked with zero consumption' );
lv_assert( 8000 === $expired_voucher['value']['remaining_after_minor'], 'blocked voucher must preserve all available value' );
$bad_voucher = lv_voucher_draft(); $bad_voucher['presented_beneficiary_reference_digest'] = lv_digest( '7' );
lv_error( $factory->create_voucher_ledger( $bad_voucher, $now ), 'wrong beneficiary cannot use a beneficiary-bound voucher' );
$bad_voucher = lv_voucher_draft(); $bad_voucher['restrictions']['partial_consumption_allowed'] = false;
lv_error( $factory->create_voucher_ledger( $bad_voucher, $now ), 'partial consumption must obey restrictions' );
$bad_voucher = lv_voucher_draft(); $bad_voucher['consumption']['settlement_amount_minor']++;
lv_error( $factory->create_voucher_ledger( $bad_voucher, $now ), 'incorrect FX conversion must fail' );
$bad_voucher = lv_voucher_draft(); $bad_voucher['value']['remaining_after_minor']++;
lv_error( $factory->create_voucher_ledger( $bad_voucher, $now ), 'voucher value cannot be created during consumption' );
$bad_voucher = lv_voucher_draft(); $bad_voucher['restrictions']['transferability'] = 'non_transferable';
lv_error( $factory->create_voucher_ledger( $bad_voucher, $now ), 'non-transferable voucher cannot name a different beneficiary' );
$bad_voucher = lv_voucher_draft(); $bad_voucher['created_at'] = '2026-07-19T10:01:00Z';
lv_error( $factory->create_voucher_ledger( $bad_voucher, $now ), 'planned evaluation creation and consumption must share one coherent pre-expiry clock' );
$bad_voucher = lv_voucher_draft(); $bad_voucher['expiry']['expires_at'] = '2026-07-19T09:59:59Z'; $bad_voucher['expiry']['state'] = 'expired';
lv_error( $factory->create_voucher_ledger( $bad_voucher, $now ), 'planned voucher evaluation creation and consumption must all occur before expiry' );
$bad_voucher = lv_voucher_draft(); $bad_voucher['consumption']['purchase_total_minor'] = 10000;
lv_error( $factory->create_voucher_ledger( $bad_voucher, $now ), 'voucher settlement amount cannot exceed purchase total' );
$bad_voucher = lv_voucher_draft(); $bad_voucher['restrictions']['transferability'] = 'non_transferable'; $bad_voucher['restrictions']['beneficiary_match_required'] = false; $bad_voucher['beneficiary_reference_digest'] = $bad_voucher['owner_reference_digest']; $bad_voucher['presented_beneficiary_reference_digest'] = lv_digest( '7' );
lv_error( $factory->create_voucher_ledger( $bad_voucher, $now ), 'non-transferable voucher presentation must match owner even when beneficiary matching is disabled' );

$jpy_voucher_draft = lv_voucher_draft();
$jpy_voucher_draft['idempotency_digest'] = lv_digest( '0' );
$jpy_voucher_draft['currency'] = 'JPY'; $jpy_voucher_draft['minor_unit_exponent'] = 0;
$jpy_voucher_draft['fx_basis']['source_currency'] = 'JPY'; $jpy_voucher_draft['fx_basis']['source_minor_unit_exponent'] = 0; $jpy_voucher_draft['fx_basis']['settlement_minor_unit_exponent'] = 2; $jpy_voucher_draft['fx_basis']['rate_numerator'] = 5; $jpy_voucher_draft['fx_basis']['rate_denominator'] = 2;
$jpy_voucher_draft['value'] = array( 'face_value_minor' => 1000, 'consumed_before_minor' => 100, 'available_before_minor' => 900, 'requested_consumption_minor' => 100, 'applied_consumption_minor' => 100, 'remaining_after_minor' => 800 );
$jpy_voucher_draft['consumption']['purchase_total_minor'] = 30000; $jpy_voucher_draft['consumption']['source_amount_minor'] = 100; $jpy_voucher_draft['consumption']['settlement_amount_minor'] = 25000; $jpy_voucher_draft['consumption']['rounding_remainder_numerator'] = 0;
$jpy_voucher = $factory->create_voucher_ledger( $jpy_voucher_draft, $now );
lv_assert( ! is_wp_error( $jpy_voucher ) && 25000 === $jpy_voucher['consumption']['settlement_amount_minor'], 'FX must reconcile source exponent zero to settlement exponent two with integer rational math' );
$bad_jpy_voucher = lv_clone( $jpy_voucher_draft ); $bad_jpy_voucher['consumption']['settlement_amount_minor'] = 250;
lv_error( $factory->create_voucher_ledger( $bad_jpy_voucher, $now ), 'FX conversion must not ignore different source and settlement minor-unit exponents' );
$bad_jpy_voucher = lv_clone( $jpy_voucher_draft ); $bad_jpy_voucher['fx_basis']['source_minor_unit_exponent'] = 2;
lv_error( $factory->create_voucher_ledger( $bad_jpy_voucher, $now ), 'root and FX source minor-unit exponents must match' );
$tampered_voucher = lv_clone( $voucher ); $tampered_voucher['value']['remaining_after_minor']++;
lv_error( Tra_Vel_Loyalty_Value_Stress_Policy::validate_voucher_ledger( $tampered_voucher, $now ), 'post-seal voucher mutation must fail' );

foreach ( array( $merge, $redemption, $voucher, $expired_voucher ) as $record ) {
	lv_assert( false === $record['boundary']['execution_authorized'], 'execution authority must stay false' );
	lv_assert( false === $record['boundary']['provider_called'], 'provider call flag must stay false' );
	lv_assert( false === $record['boundary']['ledger_mutated'], 'ledger mutation flag must stay false' );
	lv_assert( false === $record['boundary']['message_sent'], 'message side effect flag must stay false' );
}

echo 'Tra-Vel loyalty value stress runtime passed (' . $lv_assertions . ' assertions; 4 private ledgers; 10-traveler partition; zero authority or side effects).' . PHP_EOL;
