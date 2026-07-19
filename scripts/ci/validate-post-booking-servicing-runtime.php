<?php
/**
 * Focused runtime and 36-scenario adversarial checks for post-booking service.
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message, $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

$base = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/servicing/';
require_once $base . 'class-tra-vel-postbooking-servicing-taxonomy.php';
require_once $base . 'class-tra-vel-postbooking-servicing-policy.php';
require_once $base . 'class-tra-vel-postbooking-servicing-factory.php';

$postbooking_assertions = 0;
function postbooking_assert( $condition, $message ) {
	global $postbooking_assertions;
	$postbooking_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "Post-booking servicing runtime failed: {$message}\n" );
		exit( 1 );
	}
}
function postbooking_error( $value, $code, $message ) {
	postbooking_assert( is_wp_error( $value ), $message . ' (expected WP_Error)' );
	postbooking_assert( $code === $value->get_error_code(), $message . ' (unexpected code: ' . ( is_wp_error( $value ) ? $value->get_error_code() : 'not-error' ) . ')' );
}
function postbooking_clone( $value ) { return json_decode( json_encode( $value ), true ); }
function postbooking_digest( $character ) { return str_repeat( $character, 64 ); }
function postbooking_refs( $prefix, $count ) {
	$refs = array();
	for ( $index = 1; $index <= $count; $index++ ) {
		$refs[] = 'tv_' . $prefix . '_syntheticreference' . str_pad( (string) $index, 2, '0', STR_PAD_LEFT );
	}
	sort( $refs, SORT_STRING );
	return $refs;
}
function postbooking_has_action( $plan, $type ) {
	foreach ( $plan['action_queue'] as $action ) {
		if ( $type === $action['action_type'] ) { return true; }
	}
	return false;
}
function postbooking_truth_codes( $plan ) {
	return array_map( static function ( $check ) { return $check['truth_code']; }, $plan['truth_checks'] );
}

function postbooking_snapshot( $scenario ) {
	$travelers = $scenario['flight_applicable'] ? postbooking_refs( 'traveler', 4 ) : array();
	$segments = $scenario['flight_applicable'] ? postbooking_refs( 'segment', 4 ) : array();
	$items = postbooking_refs( 'order_item', 4 );
	$rooms = $scenario['lodging_applicable'] ? postbooking_refs( 'room', 2 ) : array();
	$guests = $scenario['lodging_applicable'] ? postbooking_refs( 'guest', 4 ) : array();
	$scope = array(
		'all_traveler_refs'        => $travelers,
		'affected_traveler_refs'   => array_slice( $travelers, 0, $scenario['affected_travelers'] ),
		'all_segment_refs'         => $segments,
		'affected_segment_refs'    => array_slice( $segments, 0, $scenario['affected_segments'] ),
		'all_order_item_refs'      => $items,
		'affected_order_item_refs' => array_slice( $items, 0, $scenario['affected_items'] ),
		'all_room_refs'            => $rooms,
		'affected_room_refs'       => array_slice( $rooms, 0, $scenario['affected_rooms'] ),
		'all_guest_refs'           => $guests,
		'affected_guest_refs'      => array_slice( $guests, 0, $scenario['affected_guests'] ),
		'date_scope'               => $scenario['lodging_applicable'] ? array( 'check_in_date' => '2026-08-01', 'check_out_date' => '2026-08-05' ) : array( 'check_in_date' => null, 'check_out_date' => null ),
	);

	if ( $scenario['flight_applicable'] ) {
		$tickets = array();
		foreach ( $travelers as $traveler_index => $traveler_ref ) {
			$coupons = array();
			foreach ( $segments as $segment_index => $segment_ref ) {
				$coupons[] = array( 'coupon_ref' => 'tv_coupon_synthetic' . $traveler_index . $segment_index . 'reference', 'segment_ref' => $segment_ref, 'state' => 'open' );
			}
			$tickets[] = array( 'ticket_ref' => 'tv_ticket_syntheticreference' . $traveler_index, 'traveler_ref' => $traveler_ref, 'document_state' => 'issued', 'coupons' => $coupons );
		}
		$flight = array(
			'applicable'  => true,
			'reservation' => array( 'state' => 'confirmed', 'pnr_ref' => 'tv_pnr_syntheticreference', 'airline_order_ref' => 'tv_airline_order_syntheticreference', 'ticketing_deadline_utc' => '2026-07-25T12:00:00Z' ),
			'ticketing'   => array( 'issuance_state' => 'issued', 'validating_carrier_ref' => 'party_synthetic_validatingcarrier', 'ticket_stock_ref' => 'tv_ticket_stock_syntheticreference', 'consolidator_owner_ref' => 'party_synthetic_consolidatorowner', 'servicing_owner_ref' => 'party_synthetic_consolidatorowner', 'servicing_channel' => 'consolidator' ),
			'tickets'     => $tickets,
			'emds'        => array( array( 'emd_ref' => 'tv_emd_syntheticreference', 'traveler_ref' => $travelers[0], 'order_item_ref' => $items[0], 'service_ref' => 'tv_service_syntheticbaggageservice', 'document_state' => 'issued' ) ),
		);
	} else {
		$flight = array(
			'applicable'  => false,
			'reservation' => array( 'state' => 'not_applicable', 'pnr_ref' => null, 'airline_order_ref' => null, 'ticketing_deadline_utc' => null ),
			'ticketing'   => array( 'issuance_state' => 'not_applicable', 'validating_carrier_ref' => null, 'ticket_stock_ref' => null, 'consolidator_owner_ref' => null, 'servicing_owner_ref' => null, 'servicing_channel' => 'none' ),
			'tickets'     => array(),
			'emds'        => array(),
		);
	}

	if ( $scenario['lodging_applicable'] ) {
		$lodging = array(
			'applicable'        => true,
			'reservation_ref'   => 'tv_lodging_reservation_syntheticreference',
			'reservation_state' => 'confirmed',
			'rooms'             => array(
				array( 'room_ref' => $rooms[0], 'guest_refs' => array( $guests[0], $guests[1] ), 'check_in_date' => '2026-08-01', 'check_out_date' => '2026-08-05', 'adult_count' => 1, 'child_count' => 1, 'room_state' => 'changed', 'no_show_state' => 'partial', 'inventory_restoration_state' => 'pending' ),
				array( 'room_ref' => $rooms[1], 'guest_refs' => array( $guests[2], $guests[3] ), 'check_in_date' => '2026-08-01', 'check_out_date' => '2026-08-05', 'adult_count' => 2, 'child_count' => 0, 'room_state' => 'confirmed', 'no_show_state' => 'none', 'inventory_restoration_state' => 'not_applicable' ),
			),
		);
	} else {
		$lodging = array( 'applicable' => false, 'reservation_ref' => null, 'reservation_state' => 'not_applicable', 'rooms' => array() );
	}

	$differentials = array();
	if ( $scenario['financial_outcomes'] ) {
		$outcomes = $scenario['financial_outcomes'];
		$even = array( 'even_exchange' ) === $outcomes;
		$has = static function ( $outcome ) use ( $outcomes ) { return in_array( $outcome, $outcomes, true ); };
		$differentials[] = array(
			'differential_ref'              => 'tv_servicing_diff_syntheticreference',
			'order_item_ref'                => $scope['affected_order_item_refs'][0],
			'currency'                      => 'ILS',
			'minor_unit_exponent'           => 2,
			'outcome_types'                 => $outcomes,
			'old_item_amount_minor'         => 10000,
			'replacement_item_amount_minor' => $even ? 10000 : 12000,
			'penalty_minor'                 => $even ? 0 : 500,
			'tax_difference_minor'          => $even ? 0 : 200,
			'add_collect_minor'             => $has( 'add_collect' ) ? 5000 : 0,
			'supplier_refund_minor'         => $has( 'refund' ) ? 4000 : 0,
			'residual_value_minor'          => $has( 'residual_value' ) ? 3000 : 0,
			'reusable_value_minor'          => $has( 'reusable_value' ) ? 2000 : 0,
			'traveler_repayment_minor'      => $has( 'refund' ) ? 3500 : 0,
			'settlement_adjustment_minor'   => $even ? 0 : 100,
			'states'                        => array(
				'add_collect_state'        => $has( 'add_collect' ) ? 'proposed' : 'not_applicable',
				'supplier_refund_state'    => $has( 'refund' ) ? 'pending' : 'not_applicable',
				'residual_value_state'     => $has( 'residual_value' ) ? 'proposed' : 'not_applicable',
				'reusable_value_state'     => $has( 'reusable_value' ) ? 'proposed' : 'not_applicable',
				'traveler_repayment_state' => $has( 'refund' ) ? 'not_started' : 'not_applicable',
				'settlement_state'         => $even ? 'not_started' : 'accrued',
			),
		);
	}
	$refund_present = in_array( 'refund', $scenario['financial_outcomes'], true );
	$evidence_required = 'evidence_required' === $scenario['expected_plan_state'];
	$snapshot = array(
		'contract_version'         => '1.0.0',
		'environment'              => 'sandbox',
		'data_mode'                => 'synthetic_demo',
		'servicing_case_ref'       => 'tv_servicing_case_syntheticreference',
		'snapshot_version'         => 1,
		'previous_snapshot_digest' => null,
		'snapshot_digest'          => postbooking_digest( '0' ),
		'owner_scope_digest'       => postbooking_digest( 'a' ),
		'bindings'                 => array(
			'trip_ref'              => 'tv_trip_syntheticreference',
			'trip_digest'           => postbooking_digest( 'b' ),
			'supplier_order_ref'    => 'tv_supplier_order_syntheticreference',
			'supplier_order_digest' => postbooking_digest( 'c' ),
			'commerce_order_ref'    => 'tv_order_syntheticreference',
			'commerce_order_digest' => postbooking_digest( 'd' ),
		),
		'change_class'             => $scenario['change_class'],
		'affected_scope'           => $scope,
		'flight_state'             => $flight,
		'lodging_state'            => $lodging,
		'financial_differentials'  => $differentials,
		'independent_states'       => array(
			'supplier_reservation_state' => 'confirmed',
			'supplier_fulfillment_state' => 'fulfilled',
			'customer_payment_state'      => 'captured',
			'supplier_refund_state'       => $refund_present ? 'pending' : 'not_applicable',
			'customer_refund_state'       => $refund_present ? 'not_started' : 'not_applicable',
			'supplier_settlement_state'   => $differentials ? 'accrued' : 'not_started',
			'reconciliation_state'        => $differentials ? 'pending' : 'matched',
		),
		'message_delivery'         => array(
			'webhook_health'               => $evidence_required ? 'degraded' : 'healthy',
			'poll_cursor_ref'               => 'tv_message_cursor_syntheticreference',
			'authoritative_retrieval_state'=> $evidence_required ? 'stale' : 'current',
			'messages'                      => array(
				array( 'message_ref' => 'tv_supplier_message_syntheticreference', 'message_type' => 'modification', 'channel' => 'webhook', 'delivery_state' => 'acknowledged', 'acknowledged_at' => '2026-07-19T09:15:00Z', 'authoritative_retrieval_state' => $evidence_required ? 'stale' : 'current', 'source_digest' => postbooking_digest( 'e' ), 'observed_at' => '2026-07-19T09:00:00Z' ),
			),
		),
		'observed_at'              => '2026-07-19T09:30:00Z',
		'boundary'                 => array(
			'server_only' => true, 'public_serialization_allowed' => false, 'planning_only' => true, 'synthetic_demo' => true,
			'creates_reservation' => false, 'issues_ticket_or_emd' => false, 'changes_coupon' => false, 'changes_lodging_inventory' => false,
			'creates_payment' => false, 'creates_refund' => false, 'creates_settlement' => false, 'supplier_dispatched' => false, 'processor_called' => false,
		),
	);
	return Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $snapshot );
}

$matrix_path = __DIR__ . '/../../plugin/tra-vel-agent-core/assets/fixtures/post-booking-servicing-stress-matrix.json';
$matrix = json_decode( file_get_contents( $matrix_path ), true );
postbooking_assert( is_array( $matrix ) && JSON_ERROR_NONE === json_last_error(), 'stress matrix must parse' );
postbooking_assert( 36 === $matrix['scenario_count'] && 36 === count( $matrix['scenarios'] ), 'stress matrix must contain exactly 36 declared scenarios' );
$now = strtotime( '2026-07-19T10:00:00Z' );
$scenario_ids = array();
$families = array();
foreach ( $matrix['scenarios'] as $scenario ) {
	$scenario_ids[] = $scenario['scenario_id'];
	$families[ $scenario['family'] ] = true;
	$snapshot = postbooking_snapshot( $scenario );
	$validated = Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $snapshot, $now );
	postbooking_assert( ! is_wp_error( $validated ), $scenario['scenario_id'] . ' source snapshot must validate' );
	$binding = Tra_Vel_Postbooking_Servicing_Policy::expected_binding( $snapshot );
	$plan = Tra_Vel_Postbooking_Servicing_Factory::create_plan( $snapshot, $binding, $now );
	postbooking_assert( ! is_wp_error( $plan ), $scenario['scenario_id'] . ' deterministic plan must build' );
	postbooking_assert( $scenario['expected_plan_state'] === $plan['plan_state'], $scenario['scenario_id'] . ' expected evidence/review state must match' );
	postbooking_assert( $scenario['affected_travelers'] === count( $plan['scope_resolution']['affected_traveler_refs'] ) && $scenario['affected_segments'] === count( $plan['scope_resolution']['affected_segment_refs'] ) && $scenario['affected_items'] === count( $plan['scope_resolution']['affected_order_item_refs'] ), $scenario['scenario_id'] . ' flight and item affected scope must remain exact' );
	postbooking_assert( $scenario['affected_rooms'] === count( $plan['scope_resolution']['affected_room_refs'] ) && $scenario['affected_guests'] === count( $plan['scope_resolution']['affected_guest_refs'] ), $scenario['scenario_id'] . ' room and guest affected scope must remain exact' );
	postbooking_assert( $snapshot['independent_states'] === $plan['state_axes'], $scenario['scenario_id'] . ' all independent lifecycle axes must be copied without inference' );
	postbooking_assert( $scenario['financial_outcomes'] === $plan['financial_handling']['outcomes_present'], $scenario['scenario_id'] . ' financial outcome components must remain separate and ordered' );
	postbooking_assert( true === $plan['financial_handling']['netting_prohibited'] && true === $plan['financial_handling']['no_derived_net_amount'], $scenario['scenario_id'] . ' must prohibit netting and derived net amounts' );
	postbooking_assert( false === $plan['boundary']['supplier_dispatched'] && false === $plan['boundary']['processor_called'] && false === $plan['boundary']['message_sent'], $scenario['scenario_id'] . ' plan boundary must remain zero-dispatch' );
	foreach ( $plan['action_queue'] as $action ) {
		postbooking_assert( 'planned' === $action['execution_state'] && false === $action['supplier_dispatched'] && false === $action['processor_called'] && false === $action['ledger_mutated'] && false === $action['message_sent'], $scenario['scenario_id'] . ' every action must remain review-only' );
	}
	$truth = postbooking_truth_codes( $plan );
	foreach ( $scenario['required_proofs'] as $proof ) {
		switch ( $proof ) {
			case 'reservation_not_ticket':
				postbooking_assert( in_array( 'flight_reservation', $truth, true ) && in_array( 'ticket_issuance', $truth, true ), $scenario['scenario_id'] . ' must verify reservation and issuance independently' );
				break;
			case 'servicing_owner_explicit':
				postbooking_assert( null !== $snapshot['flight_state']['ticketing']['servicing_owner_ref'] && postbooking_has_action( $plan, 'verify_servicing_owner' ), $scenario['scenario_id'] . ' must preserve explicit servicing ownership' );
				break;
			case 'coupon_scope_preserved':
				postbooking_assert( postbooking_has_action( $plan, 'verify_coupon_statuses' ) && isset( $plan['scope_resolution']['preserved_segment_refs'] ), $scenario['scenario_id'] . ' must preserve coupon and unaffected segment scope' );
				break;
			case 'emd_separate':
				postbooking_assert( in_array( 'emd_fulfillment', $truth, true ) && postbooking_has_action( $plan, 'verify_emd_fulfillment' ), $scenario['scenario_id'] . ' must reconcile EMDs independently' );
				break;
			case 'voluntary_involuntary_separate':
				$expected_action = 'voluntary_change' === $scenario['change_class'] ? 'quote_voluntary_change_rules' : 'verify_involuntary_entitlements';
				postbooking_assert( postbooking_has_action( $plan, $expected_action ), $scenario['scenario_id'] . ' must use the correct voluntary/involuntary lane' );
				break;
			case 'no_netting':
				postbooking_assert( true === $plan['financial_handling']['netting_prohibited'] && ! array_key_exists( 'net_amount_minor', $plan['financial_handling'] ), $scenario['scenario_id'] . ' must never collapse service into one signed net' );
				break;
			case 'combined_financial_components':
				postbooking_assert( count( $plan['financial_handling']['outcomes_present'] ) > 1, $scenario['scenario_id'] . ' must preserve combined outcomes as components' );
				break;
			case 'supplier_customer_refund_separate':
				postbooking_assert( 'pending' === $plan['state_axes']['supplier_refund_state'] && 'not_started' === $plan['state_axes']['customer_refund_state'], $scenario['scenario_id'] . ' supplier refund cannot imply customer repayment' );
				break;
			case 'settlement_separate':
				postbooking_assert( $snapshot['independent_states']['supplier_settlement_state'] === $plan['financial_handling']['supplier_settlement_state'], $scenario['scenario_id'] . ' settlement must remain its own ledger state' );
				break;
			case 'room_guest_date_occupancy_separate':
				postbooking_assert( 'independent_review_required' === $plan['lodging_reconciliation']['room_state'] && 'independent_review_required' === $plan['lodging_reconciliation']['occupancy_state'], $scenario['scenario_id'] . ' lodging dimensions must remain independently reviewable' );
				break;
			case 'no_show_inventory_separate':
				postbooking_assert( 'partial' === $snapshot['lodging_state']['rooms'][0]['no_show_state'] && 'pending' === $snapshot['lodging_state']['rooms'][0]['inventory_restoration_state'] && 'independent_review_required' === $plan['lodging_reconciliation']['inventory_restoration_state'], $scenario['scenario_id'] . ' no-show cannot imply restored inventory' );
				break;
			case 'message_delivery_not_booking_truth':
				postbooking_assert( true === $plan['communication_reconciliation']['delivery_evidence_only'] && false === $plan['communication_reconciliation']['booking_state_inference_allowed'], $scenario['scenario_id'] . ' delivery cannot become booking truth' );
				break;
			case 'unaffected_scope_preserved':
				postbooking_assert( isset( $plan['scope_resolution']['preserved_order_item_refs'] ) && postbooking_has_action( $plan, $scenario['flight_applicable'] ? 'preserve_unaffected_flight_scope' : 'preserve_unaffected_lodging_scope' ), $scenario['scenario_id'] . ' must preserve unaffected scope explicitly' );
				break;
			case 'evidence_required':
				postbooking_assert( 'evidence_required' === $plan['plan_state'], $scenario['scenario_id'] . ' stale or unknown truth must fail closed to evidence review' );
				break;
		}
	}
}
postbooking_assert( count( array_unique( $scenario_ids ) ) === 36, 'scenario IDs must be unique' );
postbooking_assert( count( $families ) === 7, 'all seven servicing stress families must run' );

/* Focused negative and boundary cases. */
$base_scenario = $matrix['scenarios'][33];
$base_snapshot = postbooking_snapshot( $base_scenario );
$base_binding = Tra_Vel_Postbooking_Servicing_Policy::expected_binding( $base_snapshot );

$tampered = postbooking_clone( $base_snapshot );
$tampered['flight_state']['reservation']['state'] = 'cancelled';
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $tampered, $now ), 'tra_vel_postbooking_servicing_snapshot_digest_mismatch', 'post-digest supplier-state mutation must fail' );

$extra = postbooking_clone( $base_snapshot );
$extra['net_amount_minor'] = 1;
$extra = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $extra );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $extra, $now ), 'tra_vel_postbooking_servicing_snapshot_shape_invalid', 'unknown root fields including net amounts must fail' );

$sensitive = postbooking_clone( $base_snapshot );
$sensitive['passport_number'] = 'raw-secret';
$sensitive = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $sensitive );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $sensitive, $now ), 'tra_vel_postbooking_servicing_sensitive_material_rejected', 'raw identity material must fail before projection' );

$outside = postbooking_clone( $base_snapshot );
$outside['affected_scope']['affected_segment_refs'][0] = 'tv_segment_outsidesyntheticreference';
sort( $outside['affected_scope']['affected_segment_refs'], SORT_STRING );
$outside = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $outside );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $outside, $now ), 'tra_vel_postbooking_servicing_affected_scope_not_subset', 'affected segments must be a subset of full order scope' );

$duplicate = postbooking_clone( $base_snapshot );
$duplicate['affected_scope']['all_traveler_refs'][] = $duplicate['affected_scope']['all_traveler_refs'][0];
$duplicate = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $duplicate );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $duplicate, $now ), 'tra_vel_postbooking_servicing_scope_refs_invalid', 'duplicate scope references must fail' );

$ownerless = postbooking_clone( $base_snapshot );
$ownerless['flight_state']['ticketing']['servicing_owner_ref'] = null;
$ownerless = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $ownerless );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $ownerless, $now ), 'tra_vel_postbooking_servicing_flight_ownership_incomplete', 'flight servicing owner cannot be omitted' );

$unissued = postbooking_clone( $base_snapshot );
$unissued['flight_state']['ticketing']['issuance_state'] = 'not_issued';
$unissued['flight_state']['tickets'] = array();
$unissued = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $unissued );
postbooking_assert( ! is_wp_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $unissued, $now ) ), 'confirmed reservation with no issued ticket must remain a valid distinct state' );

$false_ticket = postbooking_clone( $base_snapshot );
$false_ticket['flight_state']['ticketing']['issuance_state'] = 'not_issued';
$false_ticket = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $false_ticket );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $false_ticket, $now ), 'tra_vel_postbooking_servicing_unissued_ticket_documents_present', 'unissued state cannot retain ticket documents' );

$missing_ticket = postbooking_clone( $base_snapshot );
$missing_ticket['flight_state']['tickets'] = array();
$missing_ticket = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $missing_ticket );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $missing_ticket, $now ), 'tra_vel_postbooking_servicing_issued_ticket_documents_missing', 'issued state requires ticket/coupon evidence' );

$bad_coupon = postbooking_clone( $base_snapshot );
$bad_coupon['flight_state']['tickets'][0]['coupons'][0]['segment_ref'] = 'tv_segment_outsidesyntheticreference';
$bad_coupon = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $bad_coupon );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $bad_coupon, $now ), 'tra_vel_postbooking_servicing_ticket_coupon_invalid', 'coupon must bind a scoped segment' );

$bad_emd = postbooking_clone( $base_snapshot );
$bad_emd['flight_state']['emds'][0]['order_item_ref'] = 'tv_order_item_outsidesyntheticreference';
$bad_emd = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $bad_emd );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $bad_emd, $now ), 'tra_vel_postbooking_servicing_emd_document_invalid', 'EMD must bind a scoped order item' );

$duplicate_guest = postbooking_clone( $base_snapshot );
$duplicate_guest['lodging_state']['rooms'][1]['guest_refs'][0] = $duplicate_guest['lodging_state']['rooms'][0]['guest_refs'][0];
sort( $duplicate_guest['lodging_state']['rooms'][1]['guest_refs'], SORT_STRING );
$duplicate_guest = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $duplicate_guest );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $duplicate_guest, $now ), 'tra_vel_postbooking_servicing_lodging_guest_allocation_invalid', 'one guest cannot occupy two room allocations' );

$bad_occupancy = postbooking_clone( $base_snapshot );
$bad_occupancy['lodging_state']['rooms'][0]['adult_count'] = 2;
$bad_occupancy = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $bad_occupancy );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $bad_occupancy, $now ), 'tra_vel_postbooking_servicing_lodging_room_invalid', 'room occupancy counts must match guest references' );

$bad_date = postbooking_clone( $base_snapshot );
$bad_date['lodging_state']['rooms'][0]['check_out_date'] = '2026-08-06';
$bad_date = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $bad_date );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $bad_date, $now ), 'tra_vel_postbooking_servicing_lodging_room_date_outside_scope', 'room date cannot escape exact stay scope' );

$netted = postbooking_clone( $base_snapshot );
$netted['financial_differentials'][0]['net_amount_minor'] = 1000;
$netted = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $netted );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $netted, $now ), 'tra_vel_postbooking_servicing_financial_identity_invalid', 'per-item financial differential cannot add a net field' );

$wrong_order = postbooking_clone( $base_snapshot );
$wrong_order['financial_differentials'][0]['outcome_types'] = array_reverse( $wrong_order['financial_differentials'][0]['outcome_types'] );
$wrong_order = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $wrong_order );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $wrong_order, $now ), 'tra_vel_postbooking_servicing_financial_outcomes_invalid', 'financial outcome combinations must use canonical order' );

$zero_add = postbooking_clone( $base_snapshot );
$zero_add['financial_differentials'][0]['add_collect_minor'] = 0;
$zero_add = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $zero_add );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $zero_add, $now ), 'tra_vel_postbooking_servicing_financial_component_mismatch', 'add-collect outcome requires its own positive component' );

$even_scenario = $matrix['scenarios'][6];
$bad_even = postbooking_snapshot( $even_scenario );
$bad_even['financial_differentials'][0]['penalty_minor'] = 1;
$bad_even = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $bad_even );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $bad_even, $now ), 'tra_vel_postbooking_servicing_even_exchange_invalid', 'even exchange cannot hide a penalty or another differential' );

$unaffected_diff = postbooking_clone( $base_snapshot );
$unaffected_diff['financial_differentials'][0]['order_item_ref'] = $unaffected_diff['affected_scope']['all_order_item_refs'][3];
$unaffected_diff = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $unaffected_diff );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $unaffected_diff, $now ), 'tra_vel_postbooking_servicing_financial_identity_invalid', 'financial differential cannot target an unaffected item' );

$bad_ack = postbooking_clone( $base_snapshot );
$bad_ack['message_delivery']['messages'][0]['delivery_state'] = 'delivered';
$bad_ack = Tra_Vel_Postbooking_Servicing_Policy::seal_snapshot( $bad_ack );
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $bad_ack, $now ), 'tra_vel_postbooking_servicing_message_acknowledgement_invalid', 'delivery cannot retain an acknowledgement timestamp' );

$stale_owner = postbooking_clone( $base_binding );
$stale_owner['owner_scope_digest'] = postbooking_digest( 'f' );
postbooking_error( Tra_Vel_Postbooking_Servicing_Factory::create_plan( $base_snapshot, $stale_owner, $now ), 'tra_vel_postbooking_servicing_factory_current_binding_mismatch', 'another owner scope must fail optimistic binding' );
$stale_trip = postbooking_clone( $base_binding );
$stale_trip['trip_digest'] = postbooking_digest( 'f' );
postbooking_error( Tra_Vel_Postbooking_Servicing_Factory::create_plan( $base_snapshot, $stale_trip, $now ), 'tra_vel_postbooking_servicing_factory_current_binding_mismatch', 'stale trip digest must fail optimistic binding' );
$stale_supplier = postbooking_clone( $base_binding );
$stale_supplier['supplier_order_digest'] = postbooking_digest( 'f' );
postbooking_error( Tra_Vel_Postbooking_Servicing_Factory::create_plan( $base_snapshot, $stale_supplier, $now ), 'tra_vel_postbooking_servicing_factory_current_binding_mismatch', 'stale supplier-order digest must fail optimistic binding' );
$stale_commerce = postbooking_clone( $base_binding );
$stale_commerce['commerce_order_digest'] = postbooking_digest( 'f' );
postbooking_error( Tra_Vel_Postbooking_Servicing_Factory::create_plan( $base_snapshot, $stale_commerce, $now ), 'tra_vel_postbooking_servicing_factory_current_binding_mismatch', 'stale commerce-order digest must fail optimistic binding' );
$stale_snapshot = postbooking_clone( $base_binding );
$stale_snapshot['snapshot_digest'] = postbooking_digest( 'f' );
postbooking_error( Tra_Vel_Postbooking_Servicing_Factory::create_plan( $base_snapshot, $stale_snapshot, $now ), 'tra_vel_postbooking_servicing_factory_current_binding_mismatch', 'stale servicing snapshot digest must fail optimistic binding' );

$plan = Tra_Vel_Postbooking_Servicing_Factory::create_plan( $base_snapshot, $base_binding, $now );
$tampered_plan = postbooking_clone( $plan );
$tampered_plan['state_axes']['customer_payment_state'] = 'failed';
postbooking_error( Tra_Vel_Postbooking_Servicing_Policy::validate_plan( $tampered_plan, $base_snapshot, $base_binding, $now ), 'tra_vel_postbooking_servicing_plan_projection_mismatch', 'a plan cannot infer payment state from supplier service truth' );

echo 'Tra-Vel post-booking servicing runtime passed (' . $postbooking_assertions . ' assertions; 36 adversarial scenarios; zero dispatch).' . PHP_EOL;
