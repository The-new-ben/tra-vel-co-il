<?php
/**
 * Deterministic stress gate for the private customer Trip Cockpit read model.
 */

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

$vip = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/vip/';
require_once $vip . 'class-tra-vel-vip-taxonomy.php';
require_once $vip . 'class-tra-vel-customer-trip-cockpit-policy.php';
require_once $vip . 'class-tra-vel-customer-trip-cockpit-factory.php';

$cockpit_assertions = 0;

function cockpit_expect( $condition, $message ) {
	global $cockpit_assertions;
	$cockpit_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, 'Tra-Vel customer Trip Cockpit runtime failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function cockpit_expect_error( $value, $code, $message ) {
	cockpit_expect( is_wp_error( $value ) && $code === $value->get_error_code(), $message . ( is_wp_error( $value ) ? ' (received ' . $value->get_error_code() . ')' : ' (no error returned)' ) );
}

function cockpit_digest( $seed ) {
	return hash( 'sha256', 'tra-vel-cockpit-' . $seed );
}

function cockpit_action( $code, $priority, $services = array(), $travelers = array(), $deadline = null, $truth = 'verified_current' ) {
	return array(
		'code' => $code,
		'priority' => $priority,
		'service_refs' => $services,
		'traveler_refs' => $travelers,
		'deadline' => $deadline,
		'truth_state' => $truth,
	);
}

function cockpit_source_fixture() {
	$clock = '2026-07-19T12:00:00Z';
	$flight = 'tv_service_flight_abcdefghijkl';
	$hotel = 'tv_service_hotel_abcdefghijklmn';
	$traveler_a = 'tv_traveler_adult_abcdefghijkl';
	$traveler_b = 'tv_traveler_party_abcdefghijkl';
	return array(
		'contract_version' => '1.0.0',
		'environment' => 'sandbox',
		'cockpit_ref' => 'tv_cockpit_abcdefghijklmnop',
		'trip_ref' => 'tv_trip_abcdefghijklmnop',
		'owner_scope_digest' => cockpit_digest( 'owner' ),
		'revision' => 1,
		'previous_projection_digest' => null,
		'headline' => 'Thailand trip · Bangkok and Phuket',
		'registration' => array(
			'gate' => 'ready_to_travel',
			'readiness' => 'ready',
			'pending_requirement_codes' => array(),
			'next_action' => null,
			'verified_at' => $clock,
		),
		'trip_health' => array(
			'phase' => 'pre_trip',
			'health' => 'on_track',
			'dependency_projection_ref' => 'tv_graph_abcdefghijklmnop',
			'recovery_projection_ref' => null,
			'affected_service_refs' => array(),
			'unaffected_service_refs' => array( $flight, $hotel ),
			'next_action' => null,
			'verified_at' => $clock,
		),
		'services' => array(
			array(
				'service_ref' => $flight,
				'sequence' => 1,
				'vertical' => 'flight',
				'label' => 'Outbound flight',
				'phase' => 'confirmed',
				'health' => 'on_track',
				'fulfillment' => array( 'state' => 'confirmed', 'truth_state' => 'verified_current' ),
				'change_state' => 'unchanged',
				'protected_codes' => array( 'schedule.monitoring', 'trip.insurance' ),
				'next_action' => null,
				'events' => array(
					array( 'event_ref' => 'tv_timeline_event_flight_abcdefghijkl', 'event_code' => 'flight.confirmed', 'state' => 'confirmed', 'truth_state' => 'verified_current', 'occurred_at' => '2026-07-19T11:40:00Z' ),
				),
				'verified_at' => $clock,
			),
			array(
				'service_ref' => $hotel,
				'sequence' => 2,
				'vertical' => 'accommodation',
				'label' => 'Phuket stay',
				'phase' => 'confirmed',
				'health' => 'on_track',
				'fulfillment' => array( 'state' => 'confirmed', 'truth_state' => 'verified_current' ),
				'change_state' => 'unchanged',
				'protected_codes' => array( 'late.arrival.monitoring' ),
				'next_action' => null,
				'events' => array(
					array( 'event_ref' => 'tv_timeline_event_hotel_abcdefghijklmn', 'event_code' => 'stay.confirmed', 'state' => 'confirmed', 'truth_state' => 'verified_current', 'occurred_at' => '2026-07-19T11:45:00Z' ),
				),
				'verified_at' => $clock,
			),
		),
		'protections' => array(
			array( 'protection_code' => 'trip.insurance', 'service_refs' => array( $flight, $hotel ), 'state' => 'active', 'verified_at' => $clock ),
			array( 'protection_code' => 'schedule.monitoring', 'service_refs' => array( $flight ), 'state' => 'active', 'verified_at' => $clock ),
		),
		'changes' => array(),
		'approvals' => array(),
		'questions' => array(),
		'vip_cases' => array(),
		'trip_care_receipts' => array(),
		'commerce_orders' => array(
			array(
				'order_ref' => 'tv_order_abcdefghijklmnop',
				'service_refs' => array( $flight, $hotel ),
				'funds_state' => 'collected',
				'funds_truth_state' => 'verified_current',
				'payment_state' => 'captured',
				'payment_truth_state' => 'verified_current',
				'fulfillment_state' => 'confirmed',
				'fulfillment_truth_state' => 'verified_current',
				'refund_state' => 'not_requested',
				'refund_truth_state' => 'verified_current',
				'settlement_state' => 'pending',
				'settlement_truth_state' => 'verified_current',
				'next_action' => null,
				'verified_at' => $clock,
			),
		),
		'loyalty' => array(
			'status' => 'options_ready',
			'affected_service_refs' => array( $flight ),
			'next_action' => null,
			'verified_at' => $clock,
		),
		'traveler_readiness' => array(
			array( 'traveler_ref' => $traveler_a, 'subject_kind' => 'adult', 'readiness' => 'ready', 'pending_requirement_codes' => array(), 'next_action' => null, 'deadline' => null, 'verified_at' => $clock ),
			array( 'traveler_ref' => $traveler_b, 'subject_kind' => 'adult', 'readiness' => 'ready', 'pending_requirement_codes' => array(), 'next_action' => null, 'deadline' => null, 'verified_at' => $clock ),
		),
		'offline_pack' => array(
			'status' => 'ready',
			'itinerary' => 'ready',
			'service_contacts' => 'ready',
			'emergency_contacts' => 'ready',
			'next_action' => null,
			'verified_at' => $clock,
		),
		'observed_at' => $clock,
		'data_boundary' => array(
			'server_only' => true,
			'already_validated_projections_only' => true,
			'raw_identity_data_stored' => false,
			'raw_payment_data_stored' => false,
			'raw_medical_data_stored' => false,
			'raw_provider_payload_stored' => false,
			'bearer_secret_stored' => false,
		),
	);
}

function cockpit_refs( $source ) {
	return array(
		'flight' => $source['services'][0]['service_ref'],
		'hotel' => $source['services'][1]['service_ref'],
		'adult' => $source['traveler_readiness'][0]['traveler_ref'],
		'party' => $source['traveler_readiness'][1]['traveler_ref'],
	);
}

function cockpit_mark_change( &$source, $code, $affected, $health = 'disrupted', $phase = 'in_trip', $truth = 'verified_current' ) {
	$all = array_column( $source['services'], 'service_ref' );
	$source['trip_health']['phase'] = $phase;
	$source['trip_health']['health'] = $health;
	$source['trip_health']['affected_service_refs'] = $affected;
	$source['trip_health']['unaffected_service_refs'] = array_values( array_diff( $all, $affected ) );
	$source['changes'][] = array(
		'change_ref' => 'tv_change_' . substr( hash( 'sha256', $code ), 0, 24 ),
		'change_code' => $code,
		'affected_service_refs' => $affected,
		'truth_state' => $truth,
		'observed_at' => $source['observed_at'],
	);
}

function cockpit_schema_walk( $node, $path = '#' ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( isset( $node['type'] ) && 'object' === $node['type'] ) {
		cockpit_expect( array_key_exists( 'additionalProperties', $node ) && false === $node['additionalProperties'], $path . ' must reject unknown fields.' );
		cockpit_expect( isset( $node['properties'], $node['required'] ) && ! array_diff( array_keys( $node['properties'] ), $node['required'] ) && ! array_diff( $node['required'], array_keys( $node['properties'] ) ), $path . ' must require every declared field.' );
	}
	foreach ( $node as $key => $child ) {
		if ( is_array( $child ) ) {
			cockpit_schema_walk( $child, $path . '/' . $key );
		}
	}
}

$schema_path = __DIR__ . '/../../plugin/tra-vel-agent-core/schemas/private/customer-trip-cockpit-read-model.schema.json';
$schema = json_decode( file_get_contents( $schema_path ), true );
cockpit_expect( is_array( $schema ) && JSON_ERROR_NONE === json_last_error(), 'The private Trip Cockpit schema must parse as JSON.' );
cockpit_expect( 'https://tra-vel.co.il/schemas/private/customer-trip-cockpit-read-model.schema.json' === $schema['$id'], 'The private schema must retain its canonical identity.' );
cockpit_expect( false === $schema['additionalProperties'] && 'sandbox' === $schema['properties']['environment']['const'], 'The private schema must be root-closed and sandbox-only.' );
cockpit_schema_walk( $schema );

$now = strtotime( '2026-07-19T13:00:00Z' );
$baseline = cockpit_source_fixture();
$projection = Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $baseline, $now );
cockpit_expect( is_array( $projection ), 'A complete baseline cockpit source must materialize' . ( is_wp_error( $projection ) ? ': ' . $projection->get_error_code() . ' / ' . $projection->get_error_message() : '.' ) );
cockpit_expect( 2 === count( $projection['service_timeline'] ) && 7 > count( array_unique( array_column( $projection['service_timeline'], 'vertical' ) ) ), 'A partial trip must remain valid without fabricating every vertical.' );
cockpit_expect( array( 'funds', 'payments', 'refunds', 'settlements' ) === array_keys( $projection['money_status'] ), 'Funds, payment, refund, and settlement axes must never collapse.' );
cockpit_expect( false === $projection['authority']['resolution_inferred'] && false === $projection['authority']['combined_booking_status_exposed'], 'The cockpit must never infer resolution or emit one misleading booked status.' );
cockpit_expect( $projection === Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $baseline, $now ), 'The exact source and injected clock must materialize deterministically.' );

$scenarios = array();
$scenarios['baseline_partial_trip'] = function ( &$source ) {};
$scenarios['mid_trip_cascading_change'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	cockpit_mark_change( $source, 'flight.change.breaks.hotel.arrival', array( $r['flight'], $r['hotel'] ) );
	$source['trip_health']['recovery_projection_ref'] = 'tv_recovery_abcdefghijklmnop';
	$source['trip_health']['next_action'] = cockpit_action( 'review_recovery_plan', 'urgent', array( $r['flight'], $r['hotel'] ), array(), '2026-07-19T13:30:00Z' );
	$source['services'][0]['phase'] = 'recovery';
	$source['services'][0]['health'] = 'recovery_in_progress';
	$source['services'][0]['change_state'] = 'change_pending';
	$source['services'][1]['health'] = 'watching';
	$source['services'][1]['change_state'] = 'change_observed';
};
$scenarios['split_party_readiness'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	$source['traveler_readiness'][1]['readiness'] = 'attention_required';
	$source['traveler_readiness'][1]['pending_requirement_codes'] = array( 'split_party_contact_confirmed' );
	$source['traveler_readiness'][1]['next_action'] = cockpit_action( 'confirm_split_party_contact', 'high', array(), array( $r['party'] ), '2026-07-19T15:00:00Z' );
};
$scenarios['partial_service_issue'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	cockpit_mark_change( $source, 'flight.schedule.change', array( $r['flight'] ), 'watching', 'pre_trip' );
	$source['services'][0]['health'] = 'watching';
	$source['services'][0]['change_state'] = 'change_observed';
};
$scenarios['partial_refund'] = function ( &$source ) {
	$source['commerce_orders'][0]['payment_state'] = 'partially_refunded';
	$source['commerce_orders'][0]['funds_state'] = 'partially_returned';
	$source['commerce_orders'][0]['refund_state'] = 'partially_refunded';
	$source['commerce_orders'][0]['settlement_state'] = 'pending';
};
$scenarios['lost_connectivity'] = function ( &$source ) {
	$source['offline_pack']['status'] = 'stale';
	$source['offline_pack']['service_contacts'] = 'stale';
	$source['offline_pack']['next_action'] = cockpit_action( 'refresh_offline_contact_pack', 'high', array(), array(), null, 'stale' );
};
$scenarios['accessibility_acknowledgement'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	$source['traveler_readiness'][0]['readiness'] = 'blocked';
	$source['traveler_readiness'][0]['pending_requirement_codes'] = array( 'accessibility_supplier_acknowledged' );
	$source['traveler_readiness'][0]['next_action'] = cockpit_action( 'verify_accessibility_acknowledgement', 'urgent', array( $r['flight'], $r['hotel'] ), array( $r['adult'] ), '2026-07-19T13:20:00Z' );
};
$scenarios['minor_authority'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	$source['traveler_readiness'][1]['subject_kind'] = 'minor';
	$source['traveler_readiness'][1]['readiness'] = 'blocked';
	$source['traveler_readiness'][1]['pending_requirement_codes'] = array( 'guardian_authority_verified' );
	$source['traveler_readiness'][1]['next_action'] = cockpit_action( 'verify_guardian_authority', 'urgent', array(), array( $r['party'] ), '2026-07-19T13:15:00Z' );
};
$scenarios['loyalty_stale'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	$source['loyalty']['status'] = 'stale';
	$source['loyalty']['next_action'] = cockpit_action( 'refresh_loyalty_options', 'normal', array( $r['flight'] ), array(), null, 'stale' );
};
$scenarios['supplier_uncertainty'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	cockpit_mark_change( $source, 'stay.confirmation.uncertain', array( $r['hotel'] ), 'uncertain', 'pre_trip', 'uncertain' );
	$source['services'][1]['health'] = 'uncertain';
	$source['services'][1]['fulfillment'] = array( 'state' => 'reconciliation_required', 'truth_state' => 'uncertain' );
	$source['services'][1]['change_state'] = 'uncertain';
	$source['trip_health']['next_action'] = cockpit_action( 'reconcile_stay_confirmation', 'high', array( $r['hotel'] ), array(), '2026-07-19T14:00:00Z', 'uncertain' );
	$source['commerce_orders'][0]['fulfillment_state'] = 'reconciliation_required';
	$source['commerce_orders'][0]['fulfillment_truth_state'] = 'uncertain';
};
$scenarios['payment_requires_action'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	$source['commerce_orders'][0]['payment_state'] = 'requires_action';
	$source['commerce_orders'][0]['refund_state'] = 'not_requested';
	$source['commerce_orders'][0]['next_action'] = cockpit_action( 'complete_payment_step_up', 'high', array( $r['flight'], $r['hotel'] ), array(), '2026-07-19T13:45:00Z' );
};
$scenarios['settlement_disputed'] = function ( &$source ) {
	$source['commerce_orders'][0]['settlement_state'] = 'disputed';
	$source['commerce_orders'][0]['settlement_truth_state'] = 'conflict';
};
$scenarios['vip_immediate_safety'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	$source['vip_cases'][] = array( 'case_ref' => 'tv_case_safety_abcdefghijkl', 'customer_state' => 'immediate_safety_help', 'severity' => 'P0', 'service_refs' => array( $r['hotel'] ), 'next_action' => cockpit_action( 'follow_safety_handoff', 'urgent', array( $r['hotel'] ), array(), '2026-07-19T13:05:00Z' ), 'deadline' => '2026-07-19T13:05:00Z', 'verified_at' => $source['observed_at'] );
};
$scenarios['approval_deadline'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	$source['approvals'][] = array( 'approval_ref' => 'tv_approval_change_abcdefghijkl', 'scope_code' => 'service.change', 'status' => 'pending', 'priority' => 'high', 'service_refs' => array( $r['flight'] ), 'traveler_refs' => array( $r['adult'] ), 'deadline' => '2026-07-19T13:25:00Z', 'truth_state' => 'verified_current', 'verified_at' => $source['observed_at'] );
};
$scenarios['expired_approval'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	$source['approvals'][] = array( 'approval_ref' => 'tv_approval_expired_abcdefghijkl', 'scope_code' => 'service.change', 'status' => 'expired', 'priority' => 'urgent', 'service_refs' => array( $r['flight'] ), 'traveler_refs' => array( $r['adult'] ), 'deadline' => '2026-07-19T11:50:00Z', 'truth_state' => 'verified_current', 'verified_at' => $source['observed_at'] );
};
$scenarios['unanswered_question'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	$source['questions'][] = array( 'question_ref' => 'tv_question_pickup_abcdefghijkl', 'question_code' => 'confirm_pickup_time', 'status' => 'pending', 'priority' => 'normal', 'service_refs' => array( $r['hotel'] ), 'traveler_refs' => array(), 'deadline' => '2026-07-20T08:00:00Z', 'truth_state' => 'verified_current', 'verified_at' => $source['observed_at'] );
};
$scenarios['trip_care_checking'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	$source['trip_care_receipts'][] = array( 'receipt_ref' => 'tv_receipt_checking_abcdefghijkl', 'customer_state' => 'checking', 'service_refs' => array( $r['flight'] ), 'next_action' => null, 'deadline' => null, 'verified_at' => $source['observed_at'] );
};
$scenarios['trip_care_need_information'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	$source['trip_care_receipts'][] = array( 'receipt_ref' => 'tv_receipt_information_abcdefghij', 'customer_state' => 'need_information', 'service_refs' => array( $r['hotel'] ), 'next_action' => cockpit_action( 'add_trip_care_information', 'high', array( $r['hotel'] ), array(), '2026-07-19T14:30:00Z' ), 'deadline' => '2026-07-19T14:30:00Z', 'verified_at' => $source['observed_at'] );
};
$scenarios['planning_without_order'] = function ( &$source ) {
	$source['commerce_orders'] = array();
};
$scenarios['post_trip_refund_pending'] = function ( &$source ) {
	$source['trip_health']['phase'] = 'post_trip';
	$source['trip_health']['health'] = 'watching';
	foreach ( $source['services'] as &$service ) { $service['phase'] = 'completed'; }
	unset( $service );
	$source['commerce_orders'][0]['payment_state'] = 'captured';
	$source['commerce_orders'][0]['refund_state'] = 'pending';
};
$scenarios['single_service_cancellation'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	cockpit_mark_change( $source, 'stay.cancelled', array( $r['hotel'] ), 'action_required', 'pre_trip' );
	$source['services'][1]['phase'] = 'cancelled';
	$source['services'][1]['health'] = 'action_required';
	$source['services'][1]['fulfillment'] = array( 'state' => 'cancelled', 'truth_state' => 'verified_current' );
	$source['services'][1]['change_state'] = 'cancelled';
	$source['trip_health']['next_action'] = cockpit_action( 'choose_replacement_stay', 'high', array( $r['hotel'] ), array(), '2026-07-19T15:00:00Z' );
};
$scenarios['recovery_in_progress'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	cockpit_mark_change( $source, 'flight.recovery.started', array( $r['flight'] ), 'recovery_in_progress' );
	$source['trip_health']['recovery_projection_ref'] = 'tv_recovery_progress_abcdefghij';
	$source['trip_health']['next_action'] = cockpit_action( 'monitor_recovery_progress', 'high', array( $r['flight'] ), array(), '2026-07-19T14:00:00Z' );
	$source['services'][0]['phase'] = 'recovery';
	$source['services'][0]['health'] = 'recovery_in_progress';
	$source['services'][0]['change_state'] = 'change_pending';
};
$scenarios['registration_blocked'] = function ( &$source ) {
	$source['registration']['gate'] = 'ready_to_reserve';
	$source['registration']['readiness'] = 'blocked';
	$source['registration']['pending_requirement_codes'] = array( 'terms_accepted' );
	$source['registration']['next_action'] = cockpit_action( 'review_terms', 'normal', array(), array(), null );
};
$scenarios['offline_pack_unavailable'] = function ( &$source ) {
	$source['offline_pack']['status'] = 'unavailable';
	$source['offline_pack']['itinerary'] = 'missing';
	$source['offline_pack']['service_contacts'] = 'missing';
	$source['offline_pack']['emergency_contacts'] = 'missing';
	$source['offline_pack']['next_action'] = cockpit_action( 'build_offline_pack', 'high', array(), array(), null );
};
$scenarios['loyalty_member_connection'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	$source['loyalty']['status'] = 'member_connection_needed';
	$source['loyalty']['next_action'] = cockpit_action( 'connect_loyalty_membership', 'low', array( $r['flight'] ), array( $r['adult'] ), null );
};
$scenarios['multiple_trip_care_receipts'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	$source['trip_care_receipts'][] = array( 'receipt_ref' => 'tv_receipt_first_abcdefghijklmn', 'customer_state' => 'received', 'service_refs' => array( $r['flight'] ), 'next_action' => null, 'deadline' => null, 'verified_at' => $source['observed_at'] );
	$source['trip_care_receipts'][] = array( 'receipt_ref' => 'tv_receipt_second_abcdefghijklm', 'customer_state' => 'human_review', 'service_refs' => array( $r['hotel'] ), 'next_action' => null, 'deadline' => null, 'verified_at' => $source['observed_at'] );
};
$scenarios['refund_uncertain_payment_captured'] = function ( &$source ) {
	$source['commerce_orders'][0]['payment_state'] = 'captured';
	$source['commerce_orders'][0]['payment_truth_state'] = 'verified_current';
	$source['commerce_orders'][0]['refund_state'] = 'uncertain';
	$source['commerce_orders'][0]['refund_truth_state'] = 'uncertain';
};
$scenarios['deadline_priority_ordering'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	$source['questions'][] = array( 'question_ref' => 'tv_question_later_abcdefghijklmn', 'question_code' => 'later_question', 'status' => 'pending', 'priority' => 'high', 'service_refs' => array( $r['hotel'] ), 'traveler_refs' => array(), 'deadline' => '2026-07-19T15:00:00Z', 'truth_state' => 'verified_current', 'verified_at' => $source['observed_at'] );
	$source['approvals'][] = array( 'approval_ref' => 'tv_approval_earlier_abcdefghijklm', 'scope_code' => 'service.change', 'status' => 'pending', 'priority' => 'high', 'service_refs' => array( $r['flight'] ), 'traveler_refs' => array(), 'deadline' => '2026-07-19T14:00:00Z', 'truth_state' => 'verified_current', 'verified_at' => $source['observed_at'] );
};
$scenarios['completed_with_issue'] = function ( &$source ) {
	$r = cockpit_refs( $source );
	cockpit_mark_change( $source, 'bag.delay.post.trip', array( $r['flight'] ), 'completed_with_issue', 'post_trip' );
	$source['services'][0]['phase'] = 'completed';
	$source['services'][0]['health'] = 'completed_with_issue';
	$source['services'][0]['change_state'] = 'change_observed';
};
$scenarios['registration_refresh_is_latest'] = function ( &$source ) {
	$source['registration']['verified_at'] = '2026-07-19T12:10:00Z';
	$source['observed_at'] = '2026-07-19T12:10:00Z';
};
$scenarios['trip_health_refresh_is_latest'] = function ( &$source ) {
	$source['trip_health']['verified_at'] = '2026-07-19T12:12:00Z';
	$source['observed_at'] = '2026-07-19T12:12:00Z';
};
$scenarios['hebrew_customer_labels'] = function ( &$source ) {
	$source['headline'] = 'הטיול לתאילנד · בנגקוק ופוקט';
	$source['services'][0]['label'] = 'טיסת הלוך';
	$source['services'][1]['label'] = 'השהייה בפוקט';
};

$scenario_count = 0;
foreach ( $scenarios as $name => $mutate ) {
	$source = cockpit_source_fixture();
	$mutate( $source );
	$result = Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $source, $now );
	cockpit_expect( is_array( $result ), 'Stress scenario ' . $name . ' must materialize without collapsing independent state' . ( is_wp_error( $result ) ? ': ' . $result->get_error_code() . ' / ' . $result->get_error_message() : '.' ) );
	cockpit_expect( count( $result['service_timeline'] ) === count( $source['services'] ), 'Stress scenario ' . $name . ' must preserve every actual service and no imaginary vertical.' );
	cockpit_expect( false === $result['authority']['supplier_action_started'] && false === $result['authority']['processor_action_started'], 'Stress scenario ' . $name . ' must remain a non-executing read model.' );
		cockpit_expect( isset( $result['money_status']['funds'], $result['money_status']['payments'], $result['money_status']['refunds'], $result['money_status']['settlements'] ), 'Stress scenario ' . $name . ' must retain all financial axes.' );
	$scenario_count++;
}
cockpit_expect( $scenario_count >= 24, 'Stress coverage must include at least 24 combined real-world scenarios.' );

/* Explicit incident, deadline, benefit, and financial-separation assertions. */
$source = cockpit_source_fixture();
$r = cockpit_refs( $source );
$source['vip_cases'][] = array( 'case_ref' => 'tv_case_priority_abcdefghijkl', 'customer_state' => 'action_required', 'severity' => 'P1', 'service_refs' => array( $r['flight'] ), 'next_action' => cockpit_action( 'incident_deadline_action', 'urgent', array( $r['flight'] ), array(), '2026-07-19T13:10:00Z' ), 'deadline' => '2026-07-19T13:10:00Z', 'verified_at' => $source['observed_at'] );
$source['loyalty']['status'] = 'stale';
$source['loyalty']['next_action'] = cockpit_action( 'refresh_loyalty_options', 'normal', array( $r['flight'] ), array(), null, 'stale' );
$source['commerce_orders'][0]['payment_state'] = 'captured';
$source['commerce_orders'][0]['refund_state'] = 'pending';
$source['commerce_orders'][0]['settlement_state'] = 'disputed';
$separated = Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $source, $now );
cockpit_expect( 'vip_case' === $separated['urgent_next_action']['source'] && '2026-07-19T13:10:00Z' === $separated['urgent_next_action']['deadline'], 'Incident deadline must outrank a lower-priority stale benefit action.' );
cockpit_expect( 'action_required' === $separated['trip_care_cases'][0]['customer_state'] && '2026-07-19T13:10:00Z' === $separated['trip_care_cases'][0]['deadline'], 'The customer-safe VIP case state and deadline must remain visible beside its receipt stream.' );
cockpit_expect( 'stale' === $separated['loyalty']['status'] && 'collected' === $separated['money_status']['funds'][0]['state'] && 'captured' === $separated['money_status']['payments'][0]['state'] && 'pending' === $separated['money_status']['refunds'][0]['state'] && 'disputed' === $separated['money_status']['settlements'][0]['state'], 'Benefit, funds, payment, refund, and settlement truth must remain independently visible.' );

/* Monotonic successor preserves all services and only permits declared impact. */
$previous = Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( cockpit_source_fixture(), $now );
$next_source = cockpit_source_fixture();
$next_source['revision'] = 2;
$next_source['previous_projection_digest'] = $previous['projection_digest'];
$r = cockpit_refs( $next_source );
cockpit_mark_change( $next_source, 'flight.schedule.change', array( $r['flight'] ), 'watching', 'pre_trip' );
$next_source['services'][0]['health'] = 'watching';
$next_source['services'][0]['change_state'] = 'change_observed';
$next = Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $next_source, $now );
cockpit_expect( true === Tra_Vel_Customer_Trip_Cockpit_Policy::assert_successor( $previous, $next, $now ), 'A declared single-service change must preserve the unaffected hotel and advance exact ancestry.' );

$bad_next_source = $next_source;
$bad_next_source['services'][1]['health'] = 'watching';
$bad_next = Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $bad_next_source, $now );
cockpit_expect_error( Tra_Vel_Customer_Trip_Cockpit_Policy::assert_successor( $previous, $bad_next, $now ), 'tra_vel_customer_trip_cockpit_successor_unaffected_service_changed', 'An undeclared hotel change must fail even when the flight legitimately changed.' );

$removed = $next;
$removed['service_timeline'] = array( $removed['service_timeline'][0] );
$removed['protected'] = array_values( array_filter( $removed['protected'], function ( $item ) use ( $r ) { return ! in_array( $r['hotel'], $item['service_refs'], true ); } ) );
$removed['current']['unaffected_service_count'] = 0;
$removed = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $removed );
cockpit_expect( is_array( Tra_Vel_Customer_Trip_Cockpit_Policy::validate_projection( $removed, $now ) ), 'A structurally valid reduced snapshot is required to exercise successor removal protection.' );
cockpit_expect_error( Tra_Vel_Customer_Trip_Cockpit_Policy::assert_successor( $previous, $removed, $now ), 'tra_vel_customer_trip_cockpit_successor_service_removed', 'A successor cannot silently remove an unaffected service.' );

/* Closed/privacy/authority/clock adversarial checks. */
$dirty = cockpit_source_fixture();
$dirty['provider_payload'] = array( 'status' => 'confirmed' );
cockpit_expect_error( Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $dirty, $now ), 'tra_vel_customer_trip_cockpit_source_shape_invalid', 'Raw or unknown provider fields must fail closed.' );

$dirty = cockpit_source_fixture();
$dirty['headline'] = 'traveler@example.com';
cockpit_expect_error( Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $dirty, $now ), 'tra_vel_customer_trip_cockpit_source_shape_invalid', 'Email-shaped identity material must be rejected even in display copy.' );

$dirty = cockpit_source_fixture();
$dirty['data_boundary']['raw_payment_data_stored'] = true;
cockpit_expect_error( Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $dirty, $now ), 'tra_vel_customer_trip_cockpit_source_shape_invalid', 'A source claiming raw payment storage must fail before projection.' );

$dirty = cockpit_source_fixture();
$dirty['trip_health']['affected_service_refs'] = array( $dirty['services'][0]['service_ref'] );
$dirty['trip_health']['unaffected_service_refs'] = array();
cockpit_expect_error( Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $dirty, $now ), 'tra_vel_customer_trip_cockpit_trip_health_partition_invalid', 'Affected/unaffected lists must exactly partition the actual partial trip.' );

$dirty = cockpit_source_fixture();
$dirty['observed_at'] = '2026-07-19T12:30:00Z';
cockpit_expect_error( Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $dirty, $now ), 'tra_vel_customer_trip_cockpit_source_clock_invalid', 'A source cannot claim a last-verified time newer than every retained fact.' );

$dirty = cockpit_source_fixture();
$r = cockpit_refs( $dirty );
cockpit_mark_change( $dirty, 'flight.disrupted', array( $r['flight'] ), 'disrupted' );
cockpit_expect_error( Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $dirty, $now ), 'tra_vel_customer_trip_cockpit_trip_health_action_missing', 'A disruption cannot leave the customer with an alarming status and no explicit action.' );

$dirty = cockpit_source_fixture();
$r = cockpit_refs( $dirty );
$dirty['trip_care_receipts'][] = array( 'receipt_ref' => 'tv_receipt_missing_action_abcdefgh', 'customer_state' => 'need_information', 'service_refs' => array( $r['flight'] ), 'next_action' => null, 'deadline' => '2026-07-19T14:00:00Z', 'verified_at' => $dirty['observed_at'] );
cockpit_expect_error( Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $dirty, $now ), 'tra_vel_customer_trip_cockpit_trip_care_receipt_action_missing', 'A receipt asking for information must say what the traveler should do.' );

$tampered = $projection;
$tampered['overall_booking_status'] = 'booked';
cockpit_expect_error( Tra_Vel_Customer_Trip_Cockpit_Policy::validate_projection( $tampered, $now ), 'tra_vel_customer_trip_cockpit_projection_shape_invalid', 'A single misleading booked/resolved axis must be rejected as an unknown field.' );

$tampered = $projection;
$tampered['authority']['supplier_action_started'] = true;
cockpit_expect_error( Tra_Vel_Customer_Trip_Cockpit_Policy::validate_projection( $tampered, $now ), 'tra_vel_customer_trip_cockpit_projection_identity_invalid', 'The read model cannot claim supplier execution.' );

$tampered = $projection;
unset( $tampered['money_status']['refunds'] );
cockpit_expect_error( Tra_Vel_Customer_Trip_Cockpit_Policy::validate_projection( $tampered, $now ), 'tra_vel_customer_trip_cockpit_money_status_invalid', 'Payment truth cannot replace or hide the refund axis.' );

$tampered = $projection;
$tampered['projection_digest'] = cockpit_digest( 'tampered' );
cockpit_expect_error( Tra_Vel_Customer_Trip_Cockpit_Policy::validate_projection( $tampered, $now ), 'tra_vel_customer_trip_cockpit_projection_digest_invalid', 'A post-seal cockpit mutation must invalidate integrity.' );

echo 'Customer Trip Cockpit runtime passed (' . $cockpit_assertions . ' assertions; ' . $scenario_count . ' combined stress scenarios).' . PHP_EOL;
