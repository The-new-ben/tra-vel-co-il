<?php
/**
 * Focused adversarial gate for the redacted customer Trip Cockpit view.
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
require_once $vip . 'class-tra-vel-customer-trip-cockpit-customer-view-policy.php';
require_once $vip . 'class-tra-vel-customer-trip-cockpit-customer-view-factory.php';

$cv_assertions = 0;

function cv_expect( $condition, $message ) {
	global $cv_assertions;
	$cv_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, 'Customer Trip Cockpit view runtime failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function cv_expect_error( $value, $code, $message ) {
	cv_expect( is_wp_error( $value ) && $code === $value->get_error_code(), $message . ( is_wp_error( $value ) ? ' (received ' . $value->get_error_code() . ')' : ' (no error returned)' ) );
}

function cv_digest( $seed ) {
	return hash( 'sha256', 'tra-vel-customer-view-test|' . $seed );
}

function cv_action( $code, $priority, $services = array(), $travelers = array(), $deadline = null, $truth = 'verified_current' ) {
	return array(
		'code' => $code,
		'priority' => $priority,
		'service_refs' => $services,
		'traveler_refs' => $travelers,
		'deadline' => $deadline,
		'truth_state' => $truth,
	);
}

function cv_source_fixture() {
	$clock = '2026-07-19T12:00:00Z';
	$flight = 'tv_service_flight_abcdefghijkl';
	$hotel = 'tv_service_hotel_abcdefghijklmn';
	$adult = 'tv_traveler_adult_abcdefghijkl';
	$party = 'tv_traveler_party_abcdefghijkl';
	return array(
		'contract_version' => '1.0.0',
		'environment' => 'sandbox',
		'cockpit_ref' => 'tv_cockpit_abcdefghijklmnop',
		'trip_ref' => 'tv_trip_abcdefghijklmnop',
		'owner_scope_digest' => cv_digest( 'owner' ),
		'revision' => 1,
		'previous_projection_digest' => null,
		'headline' => 'Thailand trip - Bangkok and Phuket',
		'registration' => array(
			'gate' => 'ready_to_travel', 'readiness' => 'ready', 'pending_requirement_codes' => array(),
			'next_action' => null, 'verified_at' => $clock,
		),
		'trip_health' => array(
			'phase' => 'pre_trip', 'health' => 'on_track', 'dependency_projection_ref' => 'tv_graph_abcdefghijklmnop',
			'recovery_projection_ref' => null, 'affected_service_refs' => array(), 'unaffected_service_refs' => array( $flight, $hotel ),
			'next_action' => null, 'verified_at' => $clock,
		),
		'services' => array(
			array(
				'service_ref' => $flight, 'sequence' => 1, 'vertical' => 'flight', 'label' => 'Outbound flight',
				'phase' => 'confirmed', 'health' => 'on_track', 'fulfillment' => array( 'state' => 'confirmed', 'truth_state' => 'verified_current' ),
				'change_state' => 'unchanged', 'protected_codes' => array( 'schedule.monitoring', 'trip.insurance' ), 'next_action' => null,
				'events' => array( array( 'event_ref' => 'tv_timeline_event_flight_abcdefghijkl', 'event_code' => 'flight.confirmed', 'state' => 'confirmed', 'truth_state' => 'verified_current', 'occurred_at' => '2026-07-19T11:40:00Z' ) ),
				'verified_at' => $clock,
			),
			array(
				'service_ref' => $hotel, 'sequence' => 2, 'vertical' => 'accommodation', 'label' => 'Phuket stay',
				'phase' => 'confirmed', 'health' => 'on_track', 'fulfillment' => array( 'state' => 'confirmed', 'truth_state' => 'verified_current' ),
				'change_state' => 'unchanged', 'protected_codes' => array( 'late.arrival.monitoring' ), 'next_action' => null,
				'events' => array( array( 'event_ref' => 'tv_timeline_event_hotel_abcdefghijklmn', 'event_code' => 'stay.confirmed', 'state' => 'confirmed', 'truth_state' => 'verified_current', 'occurred_at' => '2026-07-19T11:45:00Z' ) ),
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
				'order_ref' => 'tv_order_abcdefghijklmnop', 'service_refs' => array( $flight, $hotel ),
				'funds_state' => 'collected', 'funds_truth_state' => 'verified_current',
				'payment_state' => 'captured', 'payment_truth_state' => 'verified_current',
				'fulfillment_state' => 'confirmed', 'fulfillment_truth_state' => 'verified_current',
				'refund_state' => 'not_requested', 'refund_truth_state' => 'verified_current',
				'settlement_state' => 'pending', 'settlement_truth_state' => 'verified_current',
				'next_action' => null, 'verified_at' => $clock,
			),
		),
		'loyalty' => array( 'status' => 'options_ready', 'affected_service_refs' => array( $flight ), 'next_action' => null, 'verified_at' => $clock ),
		'traveler_readiness' => array(
			array( 'traveler_ref' => $adult, 'subject_kind' => 'adult', 'readiness' => 'ready', 'pending_requirement_codes' => array(), 'next_action' => null, 'deadline' => null, 'verified_at' => $clock ),
			array( 'traveler_ref' => $party, 'subject_kind' => 'adult', 'readiness' => 'ready', 'pending_requirement_codes' => array(), 'next_action' => null, 'deadline' => null, 'verified_at' => $clock ),
		),
		'offline_pack' => array( 'status' => 'ready', 'itinerary' => 'ready', 'service_contacts' => 'ready', 'emergency_contacts' => 'ready', 'next_action' => null, 'verified_at' => $clock ),
		'observed_at' => $clock,
		'data_boundary' => array(
			'server_only' => true, 'already_validated_projections_only' => true, 'raw_identity_data_stored' => false,
			'raw_payment_data_stored' => false, 'raw_medical_data_stored' => false, 'raw_provider_payload_stored' => false, 'bearer_secret_stored' => false,
		),
	);
}

function cv_private_fixture() {
	return Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( cv_source_fixture(), strtotime( '2026-07-19T12:30:00Z' ) );
}

function cv_private_action( $code, $source ) {
	$private = cv_private_fixture();
	$private['current']['action_required'] = true;
	$private['urgent_next_action'] = array(
		'code' => $code, 'source' => $source, 'priority' => 'urgent',
		'service_refs' => array( $private['service_timeline'][0]['service_ref'] ), 'traveler_refs' => array(),
		'deadline' => '2026-07-19T13:00:00Z', 'truth_state' => 'verified_current',
	);
	return Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private );
}

function cv_private_partition( $affected_count, $changed_refs ) {
	$private = cv_private_fixture();
	$private['current']['affected_service_count'] = $affected_count;
	$private['current']['unaffected_service_count'] = count( $private['service_timeline'] ) - $affected_count;
	$private['current']['health'] = 0 < $affected_count ? 'watching' : 'on_track';
	$private['changed'] = array();
	if ( $changed_refs ) {
		$private['changed'][] = array(
			'change_ref' => 'tv_change_abcdefghijklmnopqrstuv', 'change_code' => 'schedule.change',
			'affected_service_refs' => $changed_refs, 'truth_state' => 'verified_current', 'observed_at' => '2026-07-19T12:00:00Z',
		);
	}
	return Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private );
}

function cv_rich_private() {
	$source = cv_source_fixture();
	$flight = $source['services'][0]['service_ref'];
	$adult = $source['traveler_readiness'][0]['traveler_ref'];
	$source['approvals'][] = array(
		'approval_ref' => 'tv_approval_abcdefghijklmnop', 'scope_code' => 'approve.flight.change', 'status' => 'pending', 'priority' => 'high',
		'service_refs' => array( $flight ), 'traveler_refs' => array( $adult ), 'deadline' => '2026-07-19T13:30:00Z', 'truth_state' => 'verified_current', 'verified_at' => $source['observed_at'],
	);
	$source['questions'][] = array(
		'question_ref' => 'tv_question_abcdefghijklmnop', 'question_code' => 'confirm.preference', 'status' => 'reopened', 'priority' => 'normal',
		'service_refs' => array( $flight ), 'traveler_refs' => array( $adult ), 'deadline' => null, 'truth_state' => 'verified_current', 'verified_at' => $source['observed_at'],
	);
	$source['vip_cases'][] = array(
		'case_ref' => 'tv_case_abcdefghijklmnop', 'customer_state' => 'action_required', 'severity' => 'P1', 'service_refs' => array( $flight ),
		'next_action' => cv_action( 'provide.trip.update', 'urgent', array( $flight ), array(), '2026-07-19T13:10:00Z' ),
		'deadline' => '2026-07-19T13:10:00Z', 'verified_at' => $source['observed_at'],
	);
	$source['trip_care_receipts'][] = array(
		'receipt_ref' => 'tv_receipt_abcdefghijklmnop', 'customer_state' => 'need_information', 'service_refs' => array( $flight ),
		'next_action' => cv_action( 'provide.trip.details', 'high', array( $flight ), array(), '2026-07-19T13:20:00Z' ),
		'deadline' => '2026-07-19T13:20:00Z', 'verified_at' => $source['observed_at'],
	);
	return Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $source, strtotime( '2026-07-19T12:30:00Z' ) );
}

function cv_sensitive_private() {
	$private = cv_private_fixture();
	$flight = $private['service_timeline'][0]['service_ref'];
	$minor = $private['traveler_readiness'][0]['traveler_ref'];
	$clock = '2026-07-19T12:20:00Z';
	$private['money_status']['payments'][0]['state'] = 'disputed';
	$private['money_status']['payments'][0]['truth_state'] = 'conflict';
	$private['money_status']['payments'][0]['verified_at'] = $clock;
	$private['money_status']['refunds'][0]['state'] = 'pending';
	$private['money_status']['refunds'][0]['truth_state'] = 'stale';
	$private['money_status']['refunds'][0]['verified_at'] = $clock;
	$private['loyalty']['status'] = 'member_connection_needed';
	$private['loyalty']['affected_service_refs'] = array( $flight );
	$private['loyalty']['next_action'] = cv_action( 'redeem.points', 'high', array( $flight ), array( $minor ), '2026-07-19T13:15:00Z' );
	$private['loyalty']['verified_at'] = $clock;
	$private['traveler_readiness'][0]['subject_kind'] = 'minor';
	$private['traveler_readiness'][0]['readiness'] = 'blocked';
	$private['traveler_readiness'][0]['pending_requirement_codes'] = array( 'accessibility.assistance', 'minor.guardian.consent' );
	$private['traveler_readiness'][0]['next_action'] = cv_action( 'traveler.requirement', 'urgent', array( $flight ), array( $minor ), '2026-07-19T13:05:00Z' );
	$private['traveler_readiness'][0]['deadline'] = '2026-07-19T13:05:00Z';
	$private['traveler_readiness'][0]['verified_at'] = $clock;
	$private['current']['registration_readiness'] = 'blocked';
	$private['current']['action_required'] = true;
	$private['current']['verified_at'] = $clock;
	$private['urgent_next_action'] = array(
		'code' => 'traveler.requirement', 'source' => 'traveler', 'priority' => 'urgent',
		'service_refs' => array( $flight ), 'traveler_refs' => array( $minor ),
		'deadline' => '2026-07-19T13:05:00Z', 'truth_state' => 'verified_current',
	);
	$private['last_verified_at'] = $clock;
	return Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private );
}

function cv_view( $private = null, $mode = 'signed_in' ) {
	if ( null === $private ) {
		$private = cv_private_fixture();
	}
	return Tra_Vel_Customer_Trip_Cockpit_Customer_View_Factory::create_view( $private, cv_viewing_context( $private, $mode ), strtotime( '2026-07-19T12:30:00Z' ) );
}

function cv_viewing_context( $private, $mode = 'signed_in', $scopes = null ) {
	if ( null === $scopes ) {
		$scopes = array( 'trip_view_redacted', 'incident_report', 'case_progress_view' );
	}
	return array(
		'mode' => $mode,
		'verified' => true,
		'verified_at' => '2026-07-19T12:15:00Z',
		'trip_ref' => $private['trip_ref'],
		'owner_scope_digest' => 'signed_in' === $mode ? $private['owner_scope_digest'] : null,
		'expires_at' => '2026-07-19T12:40:00Z',
		'scopes' => $scopes,
		'disclosure' => 'trip_redacted',
	);
}

function cv_view_with_context( $private, $context ) {
	return Tra_Vel_Customer_Trip_Cockpit_Customer_View_Factory::create_view( $private, $context, strtotime( '2026-07-19T12:30:00Z' ) );
}

function cv_mutated_view( $mutate ) {
	$view = cv_view();
	$mutate( $view );
	return Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $view, strtotime( '2026-07-19T12:30:00Z' ) );
}

$baseline_private = cv_private_fixture();
cv_expect( is_array( $baseline_private ), 'The private fixture must validate before customer projection.' );
$baseline = cv_view( $baseline_private );
cv_expect( is_array( $baseline ), 'A validated private cockpit must produce a customer view' . ( is_wp_error( $baseline ) ? ': ' . $baseline->get_error_code() . ' / ' . $baseline->get_error_message() : '.' ) );

$schema_path = __DIR__ . '/../../plugin/tra-vel-agent-core/schemas/public/customer-trip-cockpit-customer-view.schema.json';
$schema = json_decode( file_get_contents( $schema_path ), true );
cv_expect( is_array( $schema ) && JSON_ERROR_NONE === json_last_error(), 'The public customer-view Draft-07 schema must parse.' );

$scenarios = array();

$scenarios['signed_in_view'] = function () use ( $baseline ) {
	cv_expect( 'signed_in' === $baseline['audience']['mode'], 'Signed-in viewing context must be explicit.' );
};
$scenarios['scoped_session_view'] = function () {
	$view = cv_view( null, 'scoped_session' );
	cv_expect( is_array( $view ) && 'scoped_session' === $view['audience']['mode'], 'A separately scoped no-login session must receive the same redacted contract.' );
};
$scenarios['forwarded_scoped_link_withholds_money_loyalty_and_travelers'] = function () {
	$private = cv_sensitive_private();
	$view = cv_view_with_context( $private, cv_viewing_context( $private, 'scoped_session' ) );
	cv_expect( is_array( $view ), 'A forwarded scoped link must still receive a useful trip view.' );
	cv_expect( 'withheld_scoped_session' === $view['customer_money']['disclosure'] && array() === $view['customer_money']['payments'] && array() === $view['customer_money']['refunds'], 'A forwarded scoped link must reveal neither purchase presence nor payment or refund state.' );
	cv_expect( 'withheld_scoped_session' === $view['loyalty']['disclosure'] && 'withheld' === $view['loyalty']['status'] && 'withheld' === $view['loyalty']['filter_readiness'] && array() === $view['loyalty']['affected_service_keys'] && null === $view['loyalty']['next_safe_action'] && null === $view['loyalty']['verified_at'], 'A forwarded scoped link must reveal no loyalty connection, readiness, action, or service linkage.' );
	cv_expect( 'withheld_scoped_session' === $view['traveler_readiness_disclosure'] && array() === $view['traveler_readiness'] && 'withheld' === $view['current']['registration_readiness'], 'A forwarded scoped link must reveal no traveler-specific subject, readiness, or requirement facts.' );
};
$scenarios['scoped_minor_accessibility_details_have_no_alias_side_channel'] = function () {
	$private = cv_sensitive_private();
	$view = cv_view_with_context( $private, cv_viewing_context( $private, 'scoped_session' ) );
	$json = wp_json_encode( $view );
	cv_expect( 0 === preg_match( '/(?:traveler|purchase)-[a-f0-9]{32}/', $json ), 'A minor/accessibility-rich source must expose neither traveler nor purchase aliases through a forwarded view.' );
	cv_expect( false === strpos( $json, 'minor' ) && false === strpos( $json, 'dependent_adult' ) && false === strpos( $json, 'accessibility.assistance' ) && false === strpos( $json, 'minor.guardian.consent' ), 'Subject kind and accessibility or guardian requirements must not side-channel through scoped copy or codes.' );
	cv_expect( array() === $view['attention_items'] && null === $view['next_safe_action'] && false === $view['current']['action_required'], 'Scoped attention and urgent-action axes must not reveal withheld traveler work.' );
};
$scenarios['scoped_hidden_axes_do_not_change_freshness_or_output'] = function () {
	$plain = cv_private_fixture();
	$rich = cv_sensitive_private();
	$plain_view = cv_view_with_context( $plain, cv_viewing_context( $plain, 'scoped_session' ) );
	$rich_view = cv_view_with_context( $rich, cv_viewing_context( $rich, 'scoped_session' ) );
	cv_expect( 'scoped_visible_only' === $rich_view['freshness']['basis'], 'Scoped freshness must declare its visible-only basis.' );
	cv_expect( $plain_view['freshness'] === $rich_view['freshness'] && $plain_view['current']['verified_at'] === $rich_view['current']['verified_at'], 'Payment, refund, loyalty, traveler, and private-current clocks must not alter scoped freshness.' );
	cv_expect( $plain_view === $rich_view, 'Changing only withheld money, loyalty, minor/accessibility readiness, and their clocks must not alter the forwarded scoped projection.' );
};
$scenarios['missing_case_scope_withholds_cases_and_receipts'] = function () {
	$private = cv_rich_private();
	$context = cv_viewing_context( $private, 'scoped_session', array( 'trip_view_redacted', 'incident_report' ) );
	$view = cv_view_with_context( $private, $context );
	cv_expect( false === $view['audience']['follow_up_allowed'] && 'withheld_scope_missing' === $view['case_progress_disclosure'] && array() === $view['trip_care_cases'] && array() === $view['trip_care_receipts'], 'Case and receipt progress must be absent unless the verified context has case_progress_view.' );
};
$scenarios['case_scope_releases_only_redacted_progress'] = function () {
	$private = cv_rich_private();
	$context = cv_viewing_context( $private, 'scoped_session', array( 'trip_view_redacted', 'case_progress_view' ) );
	$view = cv_view_with_context( $private, $context );
	cv_expect( true === $view['audience']['follow_up_allowed'] && false === $view['audience']['report_issue_allowed'] && 'case_progress_redacted' === $view['case_progress_disclosure'] && 1 === count( $view['trip_care_cases'] ) && 1 === count( $view['trip_care_receipts'] ), 'case_progress_view must release only the existing redacted case and receipt progress, without granting reporting.' );
	cv_expect( false === $view['audience']['mutation_authorized'] && 'none' === $view['trip_care_cases'][0]['next_safe_action']['execution_effect'], 'Scoped case progress cannot authorize a mutation.' );
};
$scenarios['missing_incident_scope_withholds_reporting_action'] = function () {
	$private = cv_private_action( 'report.issue', 'trip_care' );
	$context = cv_viewing_context( $private, 'scoped_session', array( 'trip_view_redacted' ) );
	$view = cv_view_with_context( $private, $context );
	cv_expect( false === $view['audience']['report_issue_allowed'] && null === $view['next_safe_action'] && false === $view['current']['action_required'], 'Issue reporting and its action must be withheld when incident_report is missing.' );
};
$scenarios['incident_scope_releases_non_executing_reporting'] = function () {
	$private = cv_private_action( 'report.issue', 'trip_care' );
	$context = cv_viewing_context( $private, 'scoped_session', array( 'trip_view_redacted', 'incident_report' ) );
	$view = cv_view_with_context( $private, $context );
	cv_expect( true === $view['audience']['report_issue_allowed'] && false === $view['audience']['follow_up_allowed'] && 'follow_up' === $view['next_safe_action']['requested_effect'] && 'none' === $view['next_safe_action']['execution_effect'], 'incident_report must enable only a non-executing reporting interaction, not case progress or commercial work.' );
};
$scenarios['scoped_commerce_loyalty_and_traveler_actions_withheld'] = function () {
	foreach ( array( array( 'payment.authorize', 'commerce' ), array( 'refund.request', 'commerce' ), array( 'redeem.points', 'loyalty' ), array( 'traveler.requirement', 'traveler' ), array( 'billing.card.requires_action', 'service' ), array( 'flycard.points.connection', 'service' ), array( 'accessibility.assistance', 'service' ) ) as $item ) {
		$private = cv_private_action( $item[0], $item[1] );
		$view = cv_view_with_context( $private, cv_viewing_context( $private, 'scoped_session' ) );
		cv_expect( null === $view['next_safe_action'] && false === $view['current']['action_required'], 'Scoped views must withhold ' . $item[1] . ' actions that reveal restricted state.' );
	}
};
$scenarios['scoped_projection_has_no_mutation_authority'] = function () {
	$private = cv_private_action( 'change.flight', 'service' );
	$view = cv_view_with_context( $private, cv_viewing_context( $private, 'scoped_session' ) );
	cv_expect( false === $view['audience']['mutation_authorized'] && 'none' === $view['authority']['authorization_effect'] && false === $view['authority']['change_started'] && false === $view['authority']['payment_started'], 'No scoped viewing context may authorize or claim any mutation.' );
	cv_expect( 'step_up_required' === $view['next_safe_action']['interaction_mode'] && 'none' === $view['next_safe_action']['execution_effect'], 'A visible high-impact suggestion remains non-executing and requires step-up outside the scoped session.' );
};
$scenarios['scoped_money_injection_rejected'] = function () {
	$private = cv_private_fixture(); $view = cv_view_with_context( $private, cv_viewing_context( $private, 'scoped_session' ) );
	$view['customer_money'] = cv_view( $private )['customer_money'];
	cv_expect_error( Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $view, strtotime( '2026-07-19T12:30:00Z' ) ), 'tra_vel_customer_trip_cockpit_customer_view_money_disclosure_invalid', 'Signed-in payment detail cannot be injected into a forwarded view.' );
};
$scenarios['scoped_traveler_injection_rejected'] = function () {
	$private = cv_private_fixture(); $view = cv_view_with_context( $private, cv_viewing_context( $private, 'scoped_session' ) );
	$signed = cv_view( $private ); $view['traveler_readiness_disclosure'] = 'signed_in_redacted'; $view['traveler_readiness'] = $signed['traveler_readiness'];
	cv_expect_error( Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $view, strtotime( '2026-07-19T12:30:00Z' ) ), 'tra_vel_customer_trip_cockpit_customer_view_traveler_disclosure_invalid', 'Signed-in traveler detail cannot be injected into a forwarded view.' );
};
$scenarios['scoped_loyalty_injection_rejected'] = function () {
	$private = cv_private_fixture(); $view = cv_view_with_context( $private, cv_viewing_context( $private, 'scoped_session' ) );
	$view['loyalty'] = cv_view( $private )['loyalty'];
	cv_expect_error( Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $view, strtotime( '2026-07-19T12:30:00Z' ) ), 'tra_vel_customer_trip_cockpit_customer_view_loyalty_disclosure_invalid', 'Signed-in loyalty detail cannot be injected into a forwarded view.' );
};
$scenarios['case_progress_injection_without_scope_rejected'] = function () {
	$private = cv_rich_private();
	$view = cv_view_with_context( $private, cv_viewing_context( $private, 'scoped_session', array( 'trip_view_redacted' ) ) );
	$signed = cv_view( $private ); $view['case_progress_disclosure'] = 'case_progress_redacted'; $view['trip_care_cases'] = $signed['trip_care_cases'];
	cv_expect_error( Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $view, strtotime( '2026-07-19T12:30:00Z' ) ), 'tra_vel_customer_trip_cockpit_customer_view_case_progress_scope_invalid', 'Case progress cannot be injected when the verified scope-derived audience does not allow it.' );
};
$scenarios['private_owner_digest_omitted'] = function () use ( $baseline ) {
	cv_expect( false === strpos( wp_json_encode( $baseline ), cv_digest( 'owner' ) ), 'The private owner digest must never serialize.' );
};
$scenarios['private_root_refs_omitted'] = function () use ( $baseline ) {
	$json = wp_json_encode( $baseline );
	cv_expect( false === strpos( $json, 'tv_cockpit_' ) && false === strpos( $json, 'tv_trip_' ), 'Private cockpit and trip references must never serialize.' );
};
$scenarios['service_refs_aliased'] = function () use ( $baseline ) {
	cv_expect( 1 === preg_match( '/^service-[a-f0-9]{32}$/', $baseline['service_timeline'][0]['service_key'] ), 'Services must use owner/trip-scoped 128-bit aliases.' );
};
$scenarios['traveler_refs_aliased'] = function () use ( $baseline ) {
	cv_expect( 1 === preg_match( '/^traveler-[a-f0-9]{32}$/', $baseline['traveler_readiness'][0]['traveler_slot'] ), 'Traveler readiness must use identity-free 128-bit aliases.' );
};
$scenarios['order_refs_aliased'] = function () use ( $baseline ) {
	cv_expect( 1 === preg_match( '/^purchase-[a-f0-9]{32}$/', $baseline['customer_money']['payments'][0]['purchase_key'] ), 'Purchases must use non-authorizing 128-bit aliases.' );
};
$scenarios['aliases_stable_across_access_modes'] = function () use ( $baseline_private, $baseline ) {
	$scoped = cv_view( $baseline_private, 'scoped_session' );
	cv_expect( $baseline['service_timeline'][0]['service_key'] === $scoped['service_timeline'][0]['service_key'], 'Viewing context must not change customer aliases.' );
};
$scenarios['payment_refund_axes_separate'] = function () use ( $baseline ) {
	cv_expect( array( 'disclosure', 'payments', 'refunds' ) === array_keys( $baseline['customer_money'] ) && 'signed_in_redacted' === $baseline['customer_money']['disclosure'], 'Only the signed-in disclosure marker and separate customer payment and refund axes may serialize.' );
};
$scenarios['payment_refund_same_purchase'] = function () use ( $baseline ) {
	cv_expect( $baseline['customer_money']['payments'][0]['purchase_key'] === $baseline['customer_money']['refunds'][0]['purchase_key'], 'Payment and refund truth must bind the same customer purchase alias.' );
};
$scenarios['private_financial_axes_not_exposed'] = function () use ( $baseline ) {
	cv_expect( ! isset( $baseline['customer_money']['funds'] ) && ! isset( $baseline['customer_money']['settlements'] ), 'Private financial processing axes must not enter the customer view.' );
};
$scenarios['complete_unaffected_partition'] = function () use ( $baseline ) {
	cv_expect( 'complete' === $baseline['current']['partition_detail'] && 2 === count( array_filter( $baseline['service_timeline'], function ( $item ) { return 'unaffected' === $item['impact_state']; } ) ), 'A zero-impact trip must label the complete unaffected partition.' );
};
$scenarios['complete_partial_trip_partition'] = function () {
	$private = cv_private_fixture();
	$private = cv_private_partition( 1, array( $private['service_timeline'][0]['service_ref'] ) );
	$view = cv_view( $private );
	cv_expect( is_array( $view ) && 'complete' === $view['current']['partition_detail'] && 1 === $view['current']['affected_service_count'] && 1 === $view['current']['unaffected_service_count'], 'A fully declared partial-trip impact must retain exact affected and unaffected counts.' );
};
$scenarios['aggregate_only_partition'] = function () {
	$view = cv_view( cv_private_partition( 1, array() ) );
	cv_expect( is_array( $view ) && 'aggregate_only' === $view['current']['partition_detail'] && 2 === count( array_filter( $view['service_timeline'], function ( $item ) { return 'not_individually_declared' === $item['impact_state']; } ) ), 'Aggregate-only impact must not guess which service is unaffected.' );
};
$scenarios['partially_declared_partition'] = function () {
	$private = cv_private_fixture();
	$view = cv_view( cv_private_partition( 2, array( $private['service_timeline'][0]['service_ref'] ) ) );
	cv_expect( is_array( $view ) && 'partial' === $view['current']['partition_detail'] && 'not_individually_declared' === $view['service_timeline'][1]['impact_state'], 'A partially declared impact must leave non-declared services unresolved.' );
};
$scenarios['change_action_requires_step_up'] = function () {
	$view = cv_view( cv_private_action( 'change.flight', 'service' ) );
	cv_expect( 'change' === $view['next_safe_action']['requested_effect'] && 'step_up_required' === $view['next_safe_action']['interaction_mode'], 'A change action must require step-up.' );
};
$scenarios['cancel_action_requires_step_up'] = function () {
	$view = cv_view( cv_private_action( 'cancel.flight', 'service' ) );
	cv_expect( 'cancel' === $view['next_safe_action']['requested_effect'] && 'step_up_required' === $view['next_safe_action']['interaction_mode'], 'A cancellation action must require step-up.' );
};
$scenarios['payment_action_requires_step_up'] = function () {
	$view = cv_view( cv_private_action( 'payment.authorize', 'commerce' ) );
	cv_expect( 'pay' === $view['next_safe_action']['requested_effect'] && 'step_up_required' === $view['next_safe_action']['interaction_mode'], 'A payment action must require step-up.' );
};
$scenarios['refund_action_requires_step_up'] = function () {
	$view = cv_view( cv_private_action( 'refund.request', 'commerce' ) );
	cv_expect( 'refund' === $view['next_safe_action']['requested_effect'] && 'step_up_required' === $view['next_safe_action']['interaction_mode'], 'A refund action must require step-up.' );
};
$scenarios['approval_attention_requires_step_up'] = function () {
	$view = cv_view( cv_rich_private() );
	cv_expect( 'approval' === $view['attention_items'][0]['kind'] && 'step_up_required' === $view['attention_items'][0]['interaction_mode'], 'A pending approval must remain step-up protected.' );
};
$scenarios['case_follow_up_has_no_effect'] = function () {
	$view = cv_view( cv_rich_private() );
	cv_expect( 'follow_up' === $view['trip_care_cases'][0]['next_safe_action']['interaction_mode'] && 'none' === $view['trip_care_cases'][0]['next_safe_action']['execution_effect'], 'Case follow-up must remain a non-executing customer interaction.' );
};
$scenarios['receipt_follow_up_has_no_effect'] = function () {
	$view = cv_view( cv_rich_private() );
	cv_expect( 'follow_up' === $view['trip_care_receipts'][0]['next_safe_action']['interaction_mode'] && 'none' === $view['trip_care_receipts'][0]['next_safe_action']['execution_effect'], 'Receipt follow-up must remain non-executing.' );
};
$scenarios['case_internal_severity_omitted'] = function () {
	$view = cv_view( cv_rich_private() );
	cv_expect( ! isset( $view['trip_care_cases'][0]['severity'] ), 'Internal case severity/routing must not serialize.' );
};
$scenarios['case_alias_stable'] = function () {
	$private = cv_rich_private();
	cv_expect( cv_view( $private, 'signed_in' )['trip_care_cases'][0]['case_key'] === cv_view( $private, 'scoped_session' )['trip_care_cases'][0]['case_key'], 'Case aliases must remain stable across viewing contexts.' );
};
$scenarios['receipt_alias_stable'] = function () {
	$private = cv_rich_private();
	cv_expect( cv_view( $private, 'signed_in' )['trip_care_receipts'][0]['receipt_key'] === cv_view( $private, 'scoped_session' )['trip_care_receipts'][0]['receipt_key'], 'Receipt aliases must remain stable across viewing contexts.' );
};
$scenarios['event_internal_ref_omitted'] = function () use ( $baseline ) {
	cv_expect( ! isset( $baseline['service_timeline'][0]['events'][0]['event_ref'] ), 'Internal timeline event references must not serialize.' );
};
$scenarios['loyalty_ready_filter'] = function () use ( $baseline ) {
	cv_expect( 'options_ready' === $baseline['loyalty']['status'] && 'ready' === $baseline['loyalty']['filter_readiness'], 'Ready loyalty options must produce ready filters.' );
};
$scenarios['loyalty_stale_filter'] = function () {
	$private = cv_private_fixture();
	$private['loyalty']['status'] = 'stale';
	$private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private );
	$view = cv_view( $private );
	cv_expect( 'refresh_required' === $view['loyalty']['filter_readiness'], 'Stale loyalty truth must require refresh rather than appear ready.' );
};
$scenarios['offline_pack_preserved'] = function () use ( $baseline ) {
	cv_expect( 'ready' === $baseline['offline_pack']['status'] && 'ready' === $baseline['offline_pack']['emergency_contacts'], 'Offline itinerary and contact-pack readiness must remain visible.' );
};
$scenarios['worst_freshness_conflict'] = function () {
	$private = cv_private_fixture();
	$private['money_status']['payments'][0]['truth_state'] = 'conflict';
	$private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private );
	$view = cv_view( $private );
	cv_expect( 'conflict' === $view['freshness']['status'], 'The customer view must retain the worst validated truth state.' );
};
$scenarios['provider_routing_code_redacted'] = function () {
	$private = cv_private_fixture();
	$private['service_timeline'][0]['events'][0]['event_code'] = 'supplier.internal.route';
	$private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private );
	$view = cv_view( $private );
	cv_expect( is_array( $view ) && 'service.event' === $view['service_timeline'][0]['events'][0]['event_code'], 'Unsafe supplier-routing codes must become customer-safe status codes.' );
};
$scenarios['provider_state_code_redacted'] = function () {
	$private = cv_private_fixture();
	$private['service_timeline'][0]['events'][0]['state'] = 'provider.queue.status';
	$private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private );
	$view = cv_view( $private );
	cv_expect( is_array( $view ) && 'status.updated' === $view['service_timeline'][0]['events'][0]['state'], 'Unsafe provider-state routing must be redacted.' );
};
$scenarios['captured_payment_pending_refund_distinct'] = function () {
	$private = cv_private_fixture();
	$private['money_status']['refunds'][0]['state'] = 'pending';
	$private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private );
	$view = cv_view( $private );
	cv_expect( 'captured' === $view['customer_money']['payments'][0]['state'] && 'pending' === $view['customer_money']['refunds'][0]['state'], 'Captured payment must not imply a completed pending refund.' );
};

$scenarios['invalid_access_mode_rejected'] = function () {
	$private = cv_private_fixture();
	$context = cv_viewing_context( $private );
	$context['mode'] = 'anonymous';
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_invalid', 'An unscoped anonymous caller must not receive the view.' );
};
$scenarios['tampered_private_source_rejected'] = function () {
	$private = cv_private_fixture();
	$private['trip_headline'] = 'Tampered after seal';
	cv_expect_error( cv_view( $private ), 'tra_vel_customer_trip_cockpit_customer_view_private_source_invalid', 'The factory must revalidate the private seal before mapping.' );
};
$scenarios['unknown_root_field_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['debug'] = true; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_shape_invalid', 'Unknown public root data must fail closed.' );
};
$scenarios['owner_digest_field_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['owner_scope_digest'] = cv_digest( 'owner' ); } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_shape_invalid', 'An owner digest field must fail closed.' );
};
$scenarios['internal_reference_value_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['trip_headline'] = 'tv_trip_abcdefghijklmnop'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_shape_invalid', 'An internal private reference in customer copy must fail closed.' );
};
$scenarios['email_value_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['trip_headline'] = 'traveler@example.com'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_shape_invalid', 'Email-shaped identity material must fail closed.' );
};
$scenarios['phone_value_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['service_timeline'][0]['label'] = '+972 50 123 4567'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_shape_invalid', 'Phone-shaped identity material must fail closed.' );
};
$scenarios['raw_card_field_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['customer_money']['payments'][0]['card_number'] = '4111111111111111'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_shape_invalid', 'Raw payment data must fail closed.' );
};
$scenarios['medical_field_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['traveler_readiness'][0]['medical_note'] = 'private'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_shape_invalid', 'Raw medical material must fail closed.' );
};
$scenarios['provider_payload_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['service_timeline'][0]['provider_payload'] = array( 'status' => 'ok' ); } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_shape_invalid', 'Raw provider payloads must fail closed.' );
};
$scenarios['bearer_secret_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['trip_headline'] = 'Bearer abcdefghijklmnop'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_shape_invalid', 'Bearer-shaped secrets must fail closed.' );
};
$scenarios['refund_axis_required'] = function () {
	$result = cv_mutated_view( function ( &$view ) { unset( $view['customer_money']['refunds'] ); } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_money_invalid', 'A payment axis may never replace the refund axis.' );
};
$scenarios['settlement_axis_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['customer_money']['settlements'] = array(); } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_shape_invalid', 'Settlement data must not enter the customer view.' );
};
$scenarios['mismatched_purchase_axes_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['customer_money']['refunds'][0]['purchase_key'] = 'purchase-abcdef1234567890abcdef1234567890'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_money_partition_invalid', 'Payment and refund axes must bind the same purchase aliases.' );
};
$scenarios['change_authority_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['authority']['change_started'] = true; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_authority_invalid', 'A view cannot claim a change was started.' );
};
$scenarios['cancellation_authority_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['authority']['cancellation_started'] = true; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_authority_invalid', 'A view cannot claim a cancellation was started.' );
};
$scenarios['payment_authority_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['authority']['payment_started'] = true; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_authority_invalid', 'A view cannot claim a payment was started.' );
};
$scenarios['refund_authority_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['authority']['refund_started'] = true; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_authority_invalid', 'A view cannot claim a refund was started.' );
};
$scenarios['resolution_inference_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['freshness']['resolution_inferred'] = true; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_freshness_invalid', 'A view must never infer resolution.' );
};
$scenarios['high_impact_without_step_up_rejected'] = function () {
	$view = cv_view( cv_private_action( 'cancel.flight', 'service' ) );
	$view['next_safe_action']['interaction_mode'] = 'view';
	$result = Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $view, strtotime( '2026-07-19T12:30:00Z' ) );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_action_authority_invalid', 'High-impact actions cannot be downgraded to view-only.' );
};
$scenarios['follow_up_mislabeled_view_rejected'] = function () {
	$view = cv_view( cv_rich_private() );
	$view['trip_care_cases'][0]['next_safe_action']['interaction_mode'] = 'view';
	$result = Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $view, strtotime( '2026-07-19T12:30:00Z' ) );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_action_authority_invalid', 'Follow-up must remain distinct from passive viewing.' );
};
$scenarios['unknown_action_service_rejected'] = function () {
	$view = cv_view( cv_private_action( 'change.flight', 'service' ) );
	$view['next_safe_action']['service_keys'][] = 'service-deadbeefdeadbeef';
	$result = Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $view, strtotime( '2026-07-19T12:30:00Z' ) );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_action_invalid', 'Actions must bind actual customer service aliases.' );
};
$scenarios['duplicate_service_alias_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['service_timeline'][1]['service_key'] = $view['service_timeline'][0]['service_key']; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_service_invalid', 'Duplicate customer service aliases must fail.' );
};
$scenarios['duplicate_traveler_alias_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['traveler_readiness'][1]['traveler_slot'] = $view['traveler_readiness'][0]['traveler_slot']; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_traveler_invalid', 'Duplicate identity-free traveler slots must fail.' );
};
$scenarios['declared_affected_exceeds_count_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) {
		$view['current']['affected_service_count'] = 0; $view['current']['unaffected_service_count'] = 2;
		$view['current']['declared_affected_service_keys'] = array( $view['service_timeline'][0]['service_key'] );
		$view['current']['partition_detail'] = 'partial'; $view['service_timeline'][0]['impact_state'] = 'affected';
	} );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_partition_invalid', 'Declared impact may not exceed the validated aggregate count.' );
};
$scenarios['complete_partition_mislabel_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['service_timeline'][0]['impact_state'] = 'not_individually_declared'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_complete_partition_invalid', 'A complete partition may not hide an unresolved service.' );
};
$scenarios['future_freshness_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['freshness']['projected_at'] = '2026-07-19T13:00:00Z'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_freshness_invalid', 'A view cannot claim a future projection clock.' );
};
$scenarios['projection_before_source_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['freshness']['projected_at'] = '2026-07-19T11:00:00Z'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_freshness_invalid', 'A customer projection cannot predate the retained source observation.' );
};
$scenarios['loyalty_readiness_mismatch_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['loyalty']['filter_readiness'] = 'unavailable'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_loyalty_readiness_invalid', 'Loyalty filters cannot claim a state different from validated loyalty truth.' );
};
$scenarios['case_severity_injection_rejected'] = function () {
	$view = cv_view( cv_rich_private() );
	$view['trip_care_cases'][0]['severity'] = 'P0';
	$result = Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $view, strtotime( '2026-07-19T12:30:00Z' ) );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_case_invalid', 'Internal incident severity must not be injectable.' );
};
$scenarios['operator_routing_injection_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['attention_items'][] = array( 'operator_routing' => 'desk-a' ); } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_shape_invalid', 'Internal operator routing must fail at the serialization boundary.' );
};
$scenarios['commission_injection_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['customer_money']['commission'] = 10; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_shape_invalid', 'Commission data must fail at the serialization boundary.' );
};
$scenarios['unsafe_customer_code_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['service_timeline'][0]['events'][0]['event_code'] = 'operator.queue.route'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_service_events_invalid', 'Operator-routing codes cannot survive in a customer event.' );
};
$scenarios['unknown_action_field_rejected'] = function () {
	$view = cv_view( cv_private_action( 'change.flight', 'service' ) );
	$view['next_safe_action']['executed'] = true;
	$result = Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $view, strtotime( '2026-07-19T12:30:00Z' ) );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_action_invalid', 'Unknown execution fields must fail closed.' );
};
$scenarios['boundary_owner_exposure_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['data_boundary']['owner_scope_exposed'] = true; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_shape_invalid', 'The owner-scope exposure boundary must remain false.' );
};
$scenarios['boundary_supplier_action_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['authority']['supplier_action_started'] = true; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_authority_invalid', 'The view cannot claim supplier execution.' );
};
$scenarios['boundary_processor_action_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['authority']['processor_action_started'] = true; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_authority_invalid', 'The view cannot claim processor execution.' );
};

$scenarios['unverified_context_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private ); $context['verified'] = false;
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_invalid', 'An unverified viewing context must fail.' );
};
$scenarios['future_context_verification_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private ); $context['verified_at'] = '2026-07-19T13:00:00Z';
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_invalid', 'A viewing context cannot claim future verification.' );
};
$scenarios['mismatched_context_trip_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private ); $context['trip_ref'] = 'tv_trip_otherabcdefghijklmnop';
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_invalid', 'The context must bind the exact private trip.' );
};
$scenarios['mismatched_signed_owner_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private ); $context['owner_scope_digest'] = cv_digest( 'other-owner' );
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_owner_invalid', 'A signed-in context must bind the exact owner digest.' );
};
$scenarios['signed_owner_missing_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private ); $context['owner_scope_digest'] = null;
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_owner_invalid', 'A signed-in context cannot omit owner binding.' );
};
$scenarios['signed_expiry_injection_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private ); $context['expires_at'] = '2026-07-19T13:30:00Z';
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_expiry_invalid', 'A signed-in assertion must reject a far-future expiry.' );
};
$scenarios['expired_scoped_session_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private, 'scoped_session' ); $context['expires_at'] = '2026-07-19T12:30:00Z';
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_expiry_invalid', 'A scoped session must be strictly unexpired.' );
};
$scenarios['scoped_session_expiry_missing_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private, 'scoped_session' ); $context['expires_at'] = null;
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_expiry_invalid', 'A scoped session must carry an expiry.' );
};
$scenarios['signed_expiry_missing_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private ); $context['expires_at'] = null;
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_expiry_invalid', 'A signed-in per-request assertion must carry a bounded expiry.' );
};
$scenarios['scoped_far_future_expiry_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private, 'scoped_session' ); $context['expires_at'] = '2026-07-19T13:30:00Z';
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_expiry_invalid', 'A scoped assertion must reject a far-future expiry.' );
};
$scenarios['scoped_owner_digest_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private, 'scoped_session' ); $context['owner_scope_digest'] = $private['owner_scope_digest'];
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_session_invalid', 'A scoped response context must not carry the private owner digest.' );
};
$scenarios['wrong_disclosure_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private ); $context['disclosure'] = 'trip_full';
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_invalid', 'Only redacted trip disclosure is valid.' );
};
$scenarios['missing_view_scope_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private, 'signed_in', array( 'incident_report' ) );
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_invalid', 'The exact redacted view scope is mandatory.' );
};
$scenarios['unknown_scope_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private, 'signed_in', array( 'trip_view_redacted', 'trip_change' ) );
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_invalid', 'Unknown or high-risk scopes must fail closed.' );
};
$scenarios['duplicate_scope_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private, 'signed_in', array( 'trip_view_redacted', 'trip_view_redacted' ) );
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_invalid', 'Duplicate context scopes must fail closed.' );
};
$scenarios['unknown_context_field_rejected'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private ); $context['token'] = 'not-accepted';
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_invalid', 'The viewing context must be closed.' );
};
$scenarios['string_context_rejected'] = function () {
	$private = cv_private_fixture();
	cv_expect_error( cv_view_with_context( $private, 'signed_in' ), 'tra_vel_customer_trip_cockpit_customer_view_viewing_context_invalid', 'A string access mode cannot replace verified context evidence.' );
};
$scenarios['view_only_scope_derives_capabilities'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private, 'signed_in', array( 'trip_view_redacted' ) ); $view = cv_view_with_context( $private, $context );
	cv_expect( is_array( $view ) && true === $view['audience']['view_allowed'] && false === $view['audience']['report_issue_allowed'] && false === $view['audience']['follow_up_allowed'], 'Audience booleans must derive from exact low-risk scopes.' );
};
$scenarios['report_scope_does_not_grant_follow_up'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private, 'signed_in', array( 'trip_view_redacted', 'incident_report' ) ); $view = cv_view_with_context( $private, $context );
	cv_expect( true === $view['audience']['report_issue_allowed'] && false === $view['audience']['follow_up_allowed'], 'Issue reporting scope must not imply case follow-up.' );
};
$scenarios['follow_up_scope_does_not_grant_reporting'] = function () {
	$private = cv_private_fixture(); $context = cv_viewing_context( $private, 'signed_in', array( 'trip_view_redacted', 'case_progress_view' ) ); $view = cv_view_with_context( $private, $context );
	cv_expect( false === $view['audience']['report_issue_allowed'] && true === $view['audience']['follow_up_allowed'], 'Case follow-up scope must not imply issue reporting.' );
};
$scenarios['private_source_validates_before_context'] = function () {
	$private = cv_private_fixture(); $private['trip_headline'] = 'tampered'; $context = array();
	cv_expect_error( cv_view_with_context( $private, $context ), 'tra_vel_customer_trip_cockpit_customer_view_private_source_invalid', 'Private integrity must validate before viewing-context projection.' );
};

$scenarios['private_headline_never_copied'] = function () {
	$private = cv_private_fixture(); $private['trip_headline'] = '+972 50 123 4567'; $private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private ); $view = cv_view( $private );
	cv_expect( is_array( $view ) && 'Upcoming trip' === $view['trip_headline'] && false === strpos( wp_json_encode( $view ), '+972' ), 'Private free-form trip copy must be replaced by a phase template.' );
};
$scenarios['private_service_label_never_copied'] = function () {
	$private = cv_private_fixture(); $private['service_timeline'][0]['label'] = '+972 50 123 4567'; $private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private ); $view = cv_view( $private );
	cv_expect( is_array( $view ) && 'Flight - confirmed' === $view['service_timeline'][0]['label'] && false === strpos( wp_json_encode( $view ), '+972' ), 'Private free-form service copy must be replaced by vertical/phase templates.' );
};
$scenarios['trip_phase_template_closed'] = function () {
	$private = cv_private_fixture(); $private['current']['phase'] = 'in_trip'; $private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private ); $view = cv_view( $private );
	cv_expect( 'Trip in progress' === $view['trip_headline'], 'Trip headline must derive only from the closed phase.' );
};
$scenarios['service_phase_template_closed'] = function () {
	$private = cv_private_fixture(); $private['service_timeline'][1]['phase'] = 'recovery'; $private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private ); $view = cv_view( $private );
	cv_expect( 'Stay - support in progress' === $view['service_timeline'][1]['label'], 'Service copy must derive only from closed vertical and phase values.' );
};

$scenarios['hmac_alias_matches_owner_trip_scope'] = function () use ( $baseline_private, $baseline ) {
	$ref = $baseline_private['service_timeline'][0]['service_ref']; $message = 'tra-vel-customer-view|service|' . $baseline_private['trip_ref'] . '|' . $ref;
	$expected = 'service-' . substr( hash_hmac( 'sha256', $message, $baseline_private['owner_scope_digest'] ), 0, 32 );
	cv_expect( $expected === $baseline['service_timeline'][0]['service_key'], 'Customer aliases must be 128-bit owner/trip-scoped HMAC projections.' );
};
$scenarios['hmac_alias_isolated_across_trips'] = function () use ( $baseline ) {
	$private = cv_private_fixture(); $private['trip_ref'] = 'tv_trip_otherabcdefghijklmnop'; $private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private ); $view = cv_view( $private );
	cv_expect( $baseline['service_timeline'][0]['service_key'] !== $view['service_timeline'][0]['service_key'], 'The same service reference must alias differently for a different trip.' );
};
$scenarios['hmac_alias_isolated_across_owners'] = function () use ( $baseline ) {
	$private = cv_private_fixture(); $private['owner_scope_digest'] = cv_digest( 'second-owner' ); $private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private ); $view = cv_view( $private );
	cv_expect( $baseline['service_timeline'][0]['service_key'] !== $view['service_timeline'][0]['service_key'], 'The same service reference must alias differently for a different owner.' );
};

$scenarios['provider1_code_maps_to_fallback'] = function () {
	$private = cv_private_fixture(); $private['service_timeline'][0]['events'][0]['event_code'] = 'provider1.queue42.assignment'; $private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private ); $view = cv_view( $private );
	cv_expect( 'service.event' === $view['service_timeline'][0]['events'][0]['event_code'], 'Unknown provider-style codes must map to the safe event fallback.' );
};
$scenarios['supplieralpha_code_maps_to_fallback'] = function () {
	$private = cv_private_fixture(); $private['service_timeline'][0]['events'][0]['state'] = 'supplieralpha.gdsdesk.status'; $private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private ); $view = cv_view( $private );
	cv_expect( 'status.updated' === $view['service_timeline'][0]['events'][0]['state'], 'Unknown supplier-desk codes must map to the safe status fallback.' );
};
$scenarios['settlements_code_maps_to_fallback'] = function () {
	$private = cv_private_fixture(); $private['service_timeline'][0]['events'][0]['state'] = 'settlements.pending'; $private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private ); $view = cv_view( $private );
	cv_expect( 'status.updated' === $view['service_timeline'][0]['events'][0]['state'], 'Unknown settlement-like codes must not serialize.' );
};
$scenarios['provider1_code_mutation_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['service_timeline'][0]['events'][0]['event_code'] = 'provider1.queue42.assignment'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_service_events_invalid', 'Public code mutations outside the allowlist must fail.' );
};
$scenarios['supplieralpha_code_mutation_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['service_timeline'][0]['events'][0]['state'] = 'supplieralpha.gdsdesk.status'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_service_events_invalid', 'Supplier-desk mutations outside the allowlist must fail.' );
};
$scenarios['settlements_code_mutation_rejected'] = function () {
	$result = cv_mutated_view( function ( &$view ) { $view['service_timeline'][0]['events'][0]['state'] = 'settlements.pending'; } );
	cv_expect_error( $result, 'tra_vel_customer_trip_cockpit_customer_view_service_events_invalid', 'Settlement-like mutations outside the allowlist must fail.' );
};
$scenarios['unknown_action_defaults_step_up'] = function () {
	$view = cv_view( cv_private_action( 'do.anything', 'service' ) );
	cv_expect( 'service.review' === $view['next_safe_action']['code'] && 'unknown_high_impact' === $view['next_safe_action']['requested_effect'] && 'step_up_required' === $view['next_safe_action']['interaction_mode'], 'Unknown actions must use safe copy and default to step-up.' );
};
$scenarios['issue_action_requires_step_up'] = function () {
	$view = cv_view( cv_private_action( 'issue.ticket', 'service' ) ); cv_expect( 'issue' === $view['next_safe_action']['requested_effect'] && 'step_up_required' === $view['next_safe_action']['interaction_mode'], 'Issuance must require step-up.' );
};
$scenarios['redeem_action_requires_step_up'] = function () {
	$view = cv_view( cv_private_action( 'redeem.points', 'loyalty' ) ); cv_expect( 'redeem' === $view['next_safe_action']['requested_effect'] && 'step_up_required' === $view['next_safe_action']['interaction_mode'], 'Redemption must require step-up.' );
};
$scenarios['upgrade_action_requires_step_up'] = function () {
	$view = cv_view( cv_private_action( 'upgrade.service', 'service' ) ); cv_expect( 'upgrade' === $view['next_safe_action']['requested_effect'] && 'step_up_required' === $view['next_safe_action']['interaction_mode'], 'Upgrade must require step-up.' );
};
$scenarios['swap_action_requires_step_up'] = function () {
	$view = cv_view( cv_private_action( 'swap.service', 'service' ) ); cv_expect( 'swap' === $view['next_safe_action']['requested_effect'] && 'step_up_required' === $view['next_safe_action']['interaction_mode'], 'Swap must require step-up.' );
};
$scenarios['report_issue_remains_follow_up'] = function () {
	$view = cv_view( cv_private_action( 'report.issue', 'trip_care' ) ); cv_expect( 'follow_up' === $view['next_safe_action']['requested_effect'] && 'follow_up' === $view['next_safe_action']['interaction_mode'], 'Explicit issue reporting remains a low-risk follow-up, not ticket issuance.' );
};
$scenarios['explicit_view_action_remains_view'] = function () {
	$view = cv_view( cv_private_action( 'view.trip_status', 'trip_health' ) ); cv_expect( 'review' === $view['next_safe_action']['requested_effect'] && 'view' === $view['next_safe_action']['interaction_mode'], 'Only explicit allowlisted viewing actions may remain view-only.' );
};

$scenarios['hidden_funds_truth_does_not_change_freshness'] = function () {
	$private = cv_private_fixture(); $private['money_status']['funds'][0]['truth_state'] = 'conflict'; $private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private ); $view = cv_view( $private );
	cv_expect( 'verified_current' === $view['freshness']['status'], 'Hidden funds truth must not contaminate public freshness.' );
};
$scenarios['hidden_settlement_truth_does_not_change_freshness'] = function () {
	$private = cv_private_fixture(); $private['money_status']['settlements'][0]['truth_state'] = 'conflict'; $private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private ); $view = cv_view( $private );
	cv_expect( 'verified_current' === $view['freshness']['status'], 'Hidden settlement truth must not contaminate public freshness.' );
};
$scenarios['hidden_settlement_clock_does_not_change_public_freshness'] = function () use ( $baseline ) {
	$private = cv_private_fixture();
	$private['money_status']['settlements'][0]['truth_state'] = 'conflict';
	$private['money_status']['settlements'][0]['verified_at'] = '2026-07-19T12:20:00Z';
	$private['last_verified_at'] = '2026-07-19T12:20:00Z';
	$private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private );
	$view = cv_view( $private );
	cv_expect( 'verified_current' === $view['freshness']['status'] && $baseline['freshness']['source_verified_at'] === $view['freshness']['source_verified_at'], 'Hidden settlement truth and clocks must change neither public freshness field.' );
};
$scenarios['visible_refund_truth_changes_freshness'] = function () {
	$private = cv_private_fixture(); $private['money_status']['refunds'][0]['truth_state'] = 'stale'; $private = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $private ); $view = cv_view( $private );
	cv_expect( 'stale' === $view['freshness']['status'], 'Visible refund truth must participate in public freshness.' );
};

$scenarios['schema_recursive_forbidden_properties'] = function () use ( $schema ) {
	$forbidden = array( 'cockpit_ref', 'trip_ref', 'owner_scope_digest', 'previous_projection_digest', 'projection_digest', 'service_ref', 'traveler_ref', 'order_ref', 'event_ref', 'case_ref', 'receipt_ref', 'severity', 'settlements', 'commission' );
	$walk = function ( $node ) use ( &$walk, $forbidden ) {
		if ( ! is_array( $node ) ) { return true; }
		if ( isset( $node['properties'] ) && array_intersect( array_keys( $node['properties'] ), $forbidden ) ) { return false; }
		foreach ( $node as $child ) { if ( is_array( $child ) && ! $walk( $child ) ) { return false; } }
		return true;
	};
	cv_expect( $walk( $schema ), 'The public schema must recursively forbid private identity, routing, and hidden financial property names.' );
};
$scenarios['runtime_closes_every_object'] = function () {
	$rich = cv_view( cv_rich_private() );
	$changed_private = cv_private_fixture(); $changed_private = cv_private_partition( 1, array( $changed_private['service_timeline'][0]['service_ref'] ) ); $changed = cv_view( $changed_private );
	$with_action = cv_view( cv_private_action( 'change.flight', 'service' ) );
	$mutations = array(
		function ( &$v ) { $v['unknown'] = true; }, function ( &$v ) { $v['audience']['unknown'] = true; }, function ( &$v ) { $v['current']['unknown'] = true; },
		function ( &$v ) { $v['next_safe_action']['unknown'] = true; }, function ( &$v ) { $v['protections'][0]['unknown'] = true; },
		function ( &$v ) { $v['service_timeline'][0]['unknown'] = true; }, function ( &$v ) { $v['service_timeline'][0]['fulfillment']['unknown'] = true; },
		function ( &$v ) { $v['service_timeline'][0]['events'][0]['unknown'] = true; }, function ( &$v ) { $v['customer_money']['unknown'] = true; },
		function ( &$v ) { $v['customer_money']['payments'][0]['unknown'] = true; }, function ( &$v ) { $v['traveler_readiness'][0]['unknown'] = true; },
		function ( &$v ) { $v['loyalty']['unknown'] = true; }, function ( &$v ) { $v['offline_pack']['unknown'] = true; }, function ( &$v ) { $v['freshness']['unknown'] = true; },
		function ( &$v ) { $v['authority']['unknown'] = true; }, function ( &$v ) { $v['data_boundary']['unknown'] = true; },
	);
	foreach ( $mutations as $index => $mutate ) {
		$view = 3 === $index ? $with_action : $rich; $mutate( $view );
		cv_expect( is_wp_error( Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $view, strtotime( '2026-07-19T12:30:00Z' ) ) ), 'Every materialized object must reject unknown field #' . $index . '.' );
	}
	$changed['changes'][0]['unknown'] = true;
	cv_expect( is_wp_error( Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $changed, strtotime( '2026-07-19T12:30:00Z' ) ) ), 'Change objects must reject unknown fields.' );
	$rich['attention_items'][0]['unknown'] = true;
	cv_expect( is_wp_error( Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $rich, strtotime( '2026-07-19T12:30:00Z' ) ) ), 'Attention objects must reject unknown fields.' );
	$rich = cv_view( cv_rich_private() ); $rich['trip_care_cases'][0]['unknown'] = true;
	cv_expect( is_wp_error( Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $rich, strtotime( '2026-07-19T12:30:00Z' ) ) ), 'Case objects must reject unknown fields.' );
	$rich = cv_view( cv_rich_private() ); $rich['trip_care_receipts'][0]['unknown'] = true;
	cv_expect( is_wp_error( Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $rich, strtotime( '2026-07-19T12:30:00Z' ) ) ), 'Receipt objects must reject unknown fields.' );
};

$scenario_count = 0;
foreach ( $scenarios as $name => $scenario ) {
	$scenario();
	$scenario_count++;
}

cv_expect( $scenario_count >= 30, 'The customer-view runtime must cover at least 30 adversarial scenarios.' );
cv_expect( true === $baseline['audience']['report_issue_allowed'] && true === $baseline['audience']['follow_up_allowed'], 'Reporting and follow-up must remain available in both customer viewing contexts.' );
cv_expect( true === $baseline['audience']['high_impact_step_up_required'], 'High-impact actions must always retain step-up.' );
cv_expect( false === $baseline['authority']['change_started'] && false === $baseline['authority']['cancellation_started'] && false === $baseline['authority']['payment_started'] && false === $baseline['authority']['refund_started'], 'The projection must never claim change, cancellation, payment, or refund execution.' );

echo 'Customer Trip Cockpit customer-view runtime passed (' . $cv_assertions . ' assertions; ' . $scenario_count . ' adversarial scenarios).' . PHP_EOL;
