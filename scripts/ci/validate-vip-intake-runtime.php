<?php
/** Focused deterministic runtime checks for privacy-minimized no-login VIP intake. */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

$base = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/vip/';
require_once $base . 'class-tra-vel-vip-intake-taxonomy.php';
require_once $base . 'class-tra-vel-vip-intake-policy.php';
require_once $base . 'class-tra-vel-vip-intake-state-projection.php';

$assertions = 0;
$scenarios  = 0;
function intake_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "VIP no-login intake runtime failed: {$message}\n" );
		exit( 1 );
	}
}
function intake_scenario( $name ) {
	global $scenarios;
	$scenarios++;
	return $name;
}
function intake_ref( $kind, $suffix ) { return 'tv_' . $kind . '_' . str_pad( $suffix, 16, 'x' ); }
function intake_digest( $seed ) { return hash( 'sha256', $seed ); }
function intake_boundary() {
	return array(
		'raw_message_exposed' => false,
		'raw_contact_data_exposed' => false,
		'raw_attachment_exposed' => false,
		'raw_identity_data_exposed' => false,
		'raw_payment_data_exposed' => false,
		'raw_medical_data_exposed' => false,
		'raw_provider_payload_exposed' => false,
		'bearer_secret_exposed' => false,
	);
}
function intake_fixture( $suffix ) {
	return array(
		'contract_version' => '1.0.0',
		'intake_ref' => intake_ref( 'intake', $suffix ),
		'public_receipt_ref' => 'TVR-' . strtoupper( substr( hash( 'sha256', $suffix ), 0, 10 ) ),
		'idempotency_digest' => intake_digest( 'idempotency-' . $suffix ),
		'correlation_digest' => intake_digest( 'correlation-' . $suffix ),
		'content' => array(
			'message_digest' => intake_digest( 'message-' . $suffix ),
			'message_vault_ref' => intake_ref( 'vault', 'message-' . $suffix ),
			'language_tag' => 'he-IL',
			'semantic_summary_codes' => array( 'flight.delay.reported' ),
			'attachments' => array(),
		),
		'source' => array(
			'channel' => 'web',
			'channel_event_digest' => intake_digest( 'channel-' . $suffix ),
			'sender_assertion_digest' => null,
			'sender_trust' => 'anonymous',
			'transport_integrity' => 'not_available',
			'device_risk' => 'none',
			'scanner_opened' => false,
		),
		'access' => array(
			'mode' => 'public_incident',
			'capability_ref' => null,
			'capability_digest' => null,
			'capability_state' => 'absent',
			'requested_scopes' => array( 'incident_report' ),
			'permitted_intake_scopes' => array( 'incident_report' ),
			'executable_scopes' => array(),
			'authorization_effect' => 'none',
			'session_evidence_digest' => null,
		),
		'trip_match' => array(
			'status' => 'no_trip_claimed',
			'trip_ref' => null,
			'case_ref' => null,
			'case_state' => 'none',
			'candidate_count' => 0,
			'match_evidence_digest' => null,
		),
		'classification' => array(
			'normalized_intent' => 'incident_report',
			'incident_family' => 'flight_disruption',
			'immediate_danger' => false,
			'ambiguity' => 'none',
			'priority' => 'P2',
			'risk_signals' => array( 'none' ),
		),
		'timing' => array(
			'reported_at' => '2026-07-19T10:00:00Z',
			'received_at' => '2026-07-19T10:05:00Z',
			'normalized_at' => '2026-07-19T10:05:01Z',
			'delay_class' => 'current',
			'sla_started_at' => '2026-07-19T10:05:00Z',
		),
		'receipt' => array(
			'status' => 'queued',
			'delivery_attempt_digest' => null,
			'next_retry_at' => null,
			'calm_receipt' => true,
			'login_required' => false,
		),
		'data_boundary' => intake_boundary(),
	);
}
function intake_attachment( $suffix, $scan_status, $sensitivity, $handling ) {
	return array(
		'attachment_ref' => intake_ref( 'attachment', $suffix ),
		'blob_digest' => intake_digest( 'blob-' . $suffix ),
		'vault_ref' => intake_ref( 'vault', 'attachment-' . $suffix ),
		'media_class' => 'document',
		'scan_status' => $scan_status,
		'sensitivity' => $sensitivity,
		'handling' => $handling,
	);
}
function intake_accept( $envelope, $indexes = array() ) {
	$result = Tra_Vel_VIP_Intake_Policy::intake( $envelope, $indexes );
	intake_assert( ! is_wp_error( $result ), 'expected a valid intake result, got ' . ( is_wp_error( $result ) ? $result->get_error_code() : 'non-error' ) );
	return $result;
}
function intake_project( $result ) {
	$projection = Tra_Vel_VIP_Intake_State_Projection::project( $result );
	intake_assert( ! is_wp_error( $projection ), 'expected a valid intake projection' );
	intake_assert( 'none' === $projection['authorization_effect'] && array() === $projection['executable_scopes'], 'every customer intake projection must have zero execution authority' );
	intake_assert( false === $projection['login_required'] && true === $projection['intake_accepted'], 'reporting and receiving a calm receipt must not require login' );
	return $projection;
}

intake_scenario( 'ordinary anonymous report' );
$ordinary = intake_accept( intake_fixture( 'ordinary' ) );
$ordinary_projection = intake_project( $ordinary );
intake_assert( 'case_received' === $ordinary_projection['customer_state'], 'ordinary anonymous report should receive a calm case-received state' );
intake_assert( in_array( 'create_case', $ordinary_projection['safe_actions'], true ), 'unmatched ordinary report should create a case without executing a supplier action' );
$ordinary_receipt = Tra_Vel_VIP_Intake_State_Projection::customer_receipt( $ordinary );
intake_assert( ! is_wp_error( $ordinary_receipt ) && array( 'public_receipt_ref', 'customer_state', 'message_code', 'intake_accepted', 'login_required' ) === array_keys( $ordinary_receipt ), 'customer receipt must use an exact public-only shape' );
intake_assert( ! isset( $ordinary_receipt['intake_ref'] ) && ! isset( $ordinary_receipt['safe_actions'] ) && ! isset( $ordinary_receipt['operator_review_required'] ), 'customer receipt must not leak internal matching or work state' );

intake_scenario( 'anonymous immediate danger without trip' );
$danger = intake_fixture( 'danger' );
$danger['access']['mode'] = 'public_safety';
$danger['classification'] = array( 'normalized_intent' => 'safety_report', 'incident_family' => 'immediate_danger', 'immediate_danger' => true, 'ambiguity' => 'none', 'priority' => 'P0', 'risk_signals' => array( 'safety', 'stranded' ) );
$danger_result = intake_accept( $danger );
$danger_projection = intake_project( $danger_result );
intake_assert( 'immediate_safety_help' === $danger_projection['customer_state'], 'anonymous danger must immediately enter the safety-help state' );
intake_assert( true === $danger_projection['safety_handoff_required'] && in_array( 'start_safety_handoff', $danger_projection['safe_actions'], true ) && in_array( 'notify_on_call_operator', $danger_projection['safe_actions'], true ), 'immediate safety reporting must never wait for login or a trip match' );
$danger_receipt = Tra_Vel_VIP_Intake_State_Projection::customer_receipt( $danger_result );
intake_assert( 'safety_help_started' === $danger_receipt['message_code'] && false === $danger_receipt['login_required'], 'public danger receipt must calmly confirm help without a login demand' );

intake_scenario( 'scoped magic-link session' );
$scoped = intake_fixture( 'scoped-link' );
$scoped['source']['channel'] = 'whatsapp';
$scoped['source']['sender_assertion_digest'] = intake_digest( 'verified-whatsapp-sender' );
$scoped['source']['sender_trust'] = 'verified_channel';
$scoped['source']['transport_integrity'] = 'verified';
$scoped['access'] = array(
	'mode' => 'scoped_capability',
	'capability_ref' => intake_ref( 'capability', 'scoped-link' ),
	'capability_digest' => intake_digest( 'capability-scoped-link' ),
	'capability_state' => 'active_scoped_session',
	'requested_scopes' => array( 'incident_report', 'ordinary_evidence_add', 'case_progress_view' ),
	'permitted_intake_scopes' => array( 'incident_report', 'ordinary_evidence_add', 'case_progress_view' ),
	'executable_scopes' => array(),
	'authorization_effect' => 'none',
	'session_evidence_digest' => intake_digest( 'session-scoped-link' ),
);
$scoped['trip_match'] = array( 'status' => 'unique', 'trip_ref' => intake_ref( 'trip', 'thai-trip' ), 'case_ref' => intake_ref( 'case', 'delay-case' ), 'case_state' => 'open', 'candidate_count' => 1, 'match_evidence_digest' => intake_digest( 'unique-trip-match' ) );
$scoped_projection = intake_project( intake_accept( $scoped ) );
intake_assert( in_array( 'attach_report_metadata', $scoped_projection['safe_actions'], true ), 'active scoped session may attach report metadata to its uniquely matched open case' );
intake_assert( ! array_intersect( $scoped['access']['permitted_intake_scopes'], Tra_Vel_VIP_Intake_Taxonomy::HIGH_IMPACT_SCOPES ), 'scoped magic link must contain no high-impact capability' );

intake_scenario( 'scanner-opened magic link' );
$scanner = $scoped;
$scanner['intake_ref'] = intake_ref( 'intake', 'scanner-open' );
$scanner['public_receipt_ref'] = 'TVR-SCANNER001';
$scanner['idempotency_digest'] = intake_digest( 'scanner-idempotency' );
$scanner['correlation_digest'] = intake_digest( 'scanner-correlation' );
$scanner['content']['message_digest'] = intake_digest( 'scanner-message' );
$scanner['source']['channel_event_digest'] = intake_digest( 'scanner-event' );
$scanner['source']['scanner_opened'] = true;
$scanner['access']['capability_state'] = 'initial_get_unexchanged';
$scanner['access']['permitted_intake_scopes'] = array();
$scanner['access']['session_evidence_digest'] = null;
$scanner_projection = intake_project( intake_accept( $scanner ) );
intake_assert( 'security_check_needed' === $scanner_projection['customer_state'] && in_array( 'flag_sender_review', $scanner_projection['safe_actions'], true ), 'scanner GET must not consume or activate a magic-link capability' );
$scanner_escalation = $scanner;
$scanner_escalation['access']['permitted_intake_scopes'] = array( 'incident_report' );
intake_assert( is_wp_error( Tra_Vel_VIP_Intake_Policy::intake( $scanner_escalation ) ), 'scanner-opened link must reject a claimed permitted scope before session exchange' );

intake_scenario( 'exact SMS replay' );
$sms = intake_fixture( 'sms-replay' );
$sms['source']['channel'] = 'sms';
$sms_result = intake_accept( $sms );
$sms_indexes = Tra_Vel_VIP_Intake_Policy::index_accepted( array(), $sms_result );
intake_assert( ! is_wp_error( $sms_indexes ), 'accepted SMS intake must be indexable' );
$sms_replay = intake_accept( $sms, $sms_indexes );
intake_assert( true === $sms_replay['replay'] && true === $sms_replay['duplicate'], 'exact SMS replay must be deterministic and deduplicated' );
$sms_replay_projection = intake_project( $sms_replay );
intake_assert( 'already_received' === $sms_replay_projection['customer_state'] && array( 'acknowledge_duplicate' ) === $sms_replay_projection['safe_actions'], 'exact replay must not repeat case or supplier side effects' );

intake_scenario( 'conflicting replay payload' );
$sms_conflict = $sms;
$sms_conflict['content']['message_digest'] = intake_digest( 'different-message' );
intake_assert( is_wp_error( Tra_Vel_VIP_Intake_Policy::intake( $sms_conflict, $sms_indexes ) ), 'same intake reference with changed content must fail closed' );

intake_scenario( 'WhatsApp SMS and email duplicate' );
$whatsapp = intake_fixture( 'cross-channel-origin' );
$whatsapp['source']['channel'] = 'whatsapp';
$origin = intake_accept( $whatsapp );
$cross_indexes = Tra_Vel_VIP_Intake_Policy::index_accepted( array(), $origin );
foreach ( array( 'sms', 'email' ) as $channel ) {
	$copy = $whatsapp;
	$copy['intake_ref'] = intake_ref( 'intake', 'cross-' . $channel );
	$copy['public_receipt_ref'] = 'TVR-' . strtoupper( substr( hash( 'sha256', 'cross-' . $channel ), 0, 10 ) );
	$copy['idempotency_digest'] = intake_digest( 'cross-idempotency-' . $channel );
	$copy['source']['channel'] = $channel;
	$copy['source']['channel_event_digest'] = intake_digest( 'cross-event-' . $channel );
	$duplicate = intake_accept( $copy, $cross_indexes );
	intake_assert( false === $duplicate['replay'] && true === $duplicate['duplicate'] && $whatsapp['intake_ref'] === $duplicate['duplicate_of_intake_ref'], "{$channel} copy must deduplicate against the prior WhatsApp report" );
	intake_assert( 'already_received' === intake_project( $duplicate )['customer_state'], "{$channel} duplicate must receive an already-received receipt" );
}

intake_scenario( 'spoofed sender' );
$spoof = intake_fixture( 'spoofed-sender' );
$spoof['source']['channel'] = 'email';
$spoof['source']['sender_assertion_digest'] = intake_digest( 'spoofed-assertion' );
$spoof['source']['sender_trust'] = 'suspected_spoof';
$spoof['source']['transport_integrity'] = 'failed';
$spoof_projection = intake_project( intake_accept( $spoof ) );
intake_assert( 'security_check_needed' === $spoof_projection['customer_state'], 'spoofed sender must enter security review without losing the report' );
intake_assert( ! in_array( 'attach_report_metadata', $spoof_projection['safe_actions'], true ), 'spoofed sender must not attach instructions to a trip case' );

intake_scenario( 'stolen phone high-impact request' );
$stolen = intake_fixture( 'stolen-phone' );
$stolen['source']['channel'] = 'sms';
$stolen['source']['device_risk'] = 'lost_or_stolen';
$stolen['access']['requested_scopes'] = array( 'incident_report', 'service_cancel', 'refund_destination_change', 'recovery_channel_change' );
$stolen['classification'] = array( 'normalized_intent' => 'high_impact_request', 'incident_family' => 'lost_device', 'immediate_danger' => false, 'ambiguity' => 'none', 'priority' => 'P1', 'risk_signals' => array( 'fraud' ) );
$stolen_projection = intake_project( intake_accept( $stolen ) );
intake_assert( 'security_check_needed' === $stolen_projection['customer_state'], 'stolen device risk must take precedence over a requested account change' );
intake_assert( array() === $stolen_projection['executable_scopes'] && ! array_intersect( Tra_Vel_VIP_Intake_Taxonomy::HIGH_IMPACT_SCOPES, $stolen['access']['permitted_intake_scopes'] ), 'stolen phone cannot cancel, redirect a refund, or change recovery from channel possession alone' );

intake_scenario( 'anonymous high-impact request' );
$high = intake_fixture( 'anonymous-cancel' );
$high['access']['requested_scopes'] = array( 'incident_report', 'service_cancel', 'payment_authorize' );
$high['classification']['normalized_intent'] = 'high_impact_request';
$high_projection = intake_project( intake_accept( $high ) );
intake_assert( 'step_up_needed' === $high_projection['customer_state'] && in_array( 'request_step_up', $high_projection['safe_actions'], true ), 'anonymous cancellation/payment request must become a step-up task, not an authorization' );
$high_exec = $high;
$high_exec['access']['executable_scopes'] = array( 'service_cancel' );
intake_assert( is_wp_error( Tra_Vel_VIP_Intake_Policy::intake( $high_exec ) ), 'no-login envelope must structurally reject any executable scope' );
foreach ( Tra_Vel_VIP_Intake_Taxonomy::HIGH_IMPACT_SCOPES as $scope ) {
	$scope_fixture = intake_fixture( 'scope-' . $scope );
	$scope_fixture['access']['requested_scopes'] = array( 'incident_report', $scope );
	$scope_fixture['classification']['normalized_intent'] = 'high_impact_request';
	$scope_projection = intake_project( intake_accept( $scope_fixture ) );
	intake_assert( 'step_up_needed' === $scope_projection['customer_state'], "anonymous {$scope} request must require a separate verified decision" );
	intake_assert( array() === $scope_projection['executable_scopes'] && 'none' === $scope_projection['authorization_effect'], "anonymous {$scope} request must never become executable" );
}

intake_scenario( 'ambiguous trip match' );
$ambiguous = intake_fixture( 'ambiguous-trip' );
$ambiguous['trip_match'] = array( 'status' => 'ambiguous', 'trip_ref' => null, 'case_ref' => null, 'case_state' => 'none', 'candidate_count' => 3, 'match_evidence_digest' => intake_digest( 'ambiguous-trip-evidence' ) );
$ambiguous['classification']['ambiguity'] = 'ambiguous_trip';
$ambiguous_projection = intake_project( intake_accept( $ambiguous ) );
intake_assert( 'clarification_needed' === $ambiguous_projection['customer_state'] && in_array( 'request_clarification', $ambiguous_projection['safe_actions'], true ), 'ambiguous trip match must not guess a booking' );
intake_assert( ! in_array( 'attach_report_metadata', $ambiguous_projection['safe_actions'], true ), 'ambiguous trip match must not attach to a candidate case' );

intake_scenario( 'malware attachment' );
$malware = intake_fixture( 'malware-file' );
$malware['content']['attachments'][] = intake_attachment( 'malware-file', 'malware_detected', 'ordinary', 'quarantine' );
$malware_projection = intake_project( intake_accept( $malware ) );
intake_assert( 'attachment_quarantined' === $malware_projection['customer_state'] && in_array( 'quarantine_attachment', $malware_projection['safe_actions'], true ), 'malware attachment must be quarantined without rejecting the incident report' );
$malware_leak = $malware;
$malware_leak['content']['attachments'][0]['handling'] = 'allow_metadata';
intake_assert( is_wp_error( Tra_Vel_VIP_Intake_Policy::intake( $malware_leak ) ), 'malware metadata cannot be released to a general case projection' );

intake_scenario( 'restricted attachment' );
$restricted = intake_fixture( 'restricted-file' );
$restricted['content']['attachments'][] = intake_attachment( 'restricted-file', 'clean', 'restricted_medical', 'restricted_vault' );
$restricted_projection = intake_project( intake_accept( $restricted ) );
intake_assert( in_array( 'isolate_restricted_attachment', $restricted_projection['safe_actions'], true ) && true === $restricted_projection['operator_review_required'], 'restricted evidence must stay isolated while its report is accepted' );
$restricted_leak = $restricted;
$restricted_leak['content']['attachments'][0]['handling'] = 'allow_metadata';
intake_assert( is_wp_error( Tra_Vel_VIP_Intake_Policy::intake( $restricted_leak ) ), 'restricted evidence must never enter ordinary attachment handling' );

intake_scenario( 'free-language ambiguity' );
$unclear = intake_fixture( 'unclear-message' );
$unclear['classification']['normalized_intent'] = 'unclear';
$unclear['classification']['incident_family'] = 'unknown';
$unclear['classification']['ambiguity'] = 'unclear_intent';
$unclear['content']['semantic_summary_codes'] = array( 'intent.unclear', 'clarification.required' );
$unclear_projection = intake_project( intake_accept( $unclear ) );
intake_assert( 'clarification_needed' === $unclear_projection['customer_state'], 'ambiguous free-language message must get a simple clarification path' );

intake_scenario( 'conflicting family instructions' );
$family = intake_fixture( 'family-conflict' );
$family['classification']['ambiguity'] = 'conflicting_instructions';
$family['classification']['risk_signals'] = array( 'fraud' );
$family['content']['semantic_summary_codes'] = array( 'instructions.conflict', 'family.authority.unresolved' );
$family_projection = intake_project( intake_accept( $family ) );
intake_assert( 'human_review_started' === $family_projection['customer_state'], 'conflicting family instructions require human authority review' );
intake_assert( ! in_array( 'attach_report_metadata', $family_projection['safe_actions'], true ), 'conflicting family instructions must not mutate the case automatically' );

intake_scenario( 'delayed offline message' );
$offline = intake_fixture( 'offline-message' );
$offline['timing']['reported_at'] = '2026-07-17T09:00:00Z';
$offline['timing']['delay_class'] = 'offline_replay';
$offline['classification']['risk_signals'] = array( 'offline', 'deadline' );
$offline_projection = intake_project( intake_accept( $offline ) );
intake_assert( true === $offline_projection['operator_review_required'], 'offline replay must alert an operator to stale deadlines' );
intake_assert( $offline['timing']['received_at'] === $offline['timing']['sla_started_at'], 'SLA must start at receipt rather than a claimant-controlled report time' );

intake_scenario( 'delayed channel delivery' );
$delayed = intake_fixture( 'delayed-message' );
$delayed['timing']['reported_at'] = '2026-07-19T09:00:00Z';
$delayed['timing']['delay_class'] = 'delayed';
$delayed_projection = intake_project( intake_accept( $delayed ) );
intake_assert( 'case_received' === $delayed_projection['customer_state'], 'a delayed but valid report must still be accepted' );
intake_assert( false === $delayed_projection['login_required'], 'delayed delivery must not create a login gate' );

intake_scenario( 'closed-case reopen request' );
$closed = intake_fixture( 'closed-case' );
$closed['trip_match'] = array( 'status' => 'unique', 'trip_ref' => intake_ref( 'trip', 'closed-trip' ), 'case_ref' => intake_ref( 'case', 'closed-case' ), 'case_state' => 'closed', 'candidate_count' => 1, 'match_evidence_digest' => intake_digest( 'closed-match' ) );
$closed_projection = intake_project( intake_accept( $closed ) );
intake_assert( 'case_reopen_review' === $closed_projection['customer_state'] && in_array( 'review_case_reopen', $closed_projection['safe_actions'], true ), 'new report on a closed case must create a controlled reopen review' );
intake_assert( ! in_array( 'attach_report_metadata', $closed_projection['safe_actions'], true ), 'closed case must not silently reopen or mutate' );

intake_scenario( 'receipt delivery failure' );
$delivery = intake_fixture( 'receipt-failed' );
$delivery['receipt'] = array( 'status' => 'failed', 'delivery_attempt_digest' => intake_digest( 'failed-delivery-attempt' ), 'next_retry_at' => '2026-07-19T10:10:00Z', 'calm_receipt' => true, 'login_required' => false );
$delivery_projection = intake_project( intake_accept( $delivery ) );
intake_assert( 'case_received' === $delivery_projection['customer_state'] && in_array( 'retry_receipt_delivery', $delivery_projection['safe_actions'], true ), 'receipt delivery failure must not roll back accepted intake and must schedule recovery' );

intake_scenario( 'immediate danger with malicious attachment' );
$danger_file = $danger;
$danger_file['intake_ref'] = intake_ref( 'intake', 'danger-malware' );
$danger_file['public_receipt_ref'] = 'TVR-DANGER0001';
$danger_file['idempotency_digest'] = intake_digest( 'danger-malware-idempotency' );
$danger_file['correlation_digest'] = intake_digest( 'danger-malware-correlation' );
$danger_file['content']['message_digest'] = intake_digest( 'danger-malware-message' );
$danger_file['source']['channel_event_digest'] = intake_digest( 'danger-malware-channel' );
$danger_file['content']['attachments'][] = intake_attachment( 'danger-malware', 'malware_detected', 'ordinary', 'quarantine' );
$danger_file_projection = intake_project( intake_accept( $danger_file ) );
intake_assert( 'immediate_safety_help' === $danger_file_projection['customer_state'], 'attachment quarantine must never delay immediate safety help' );
intake_assert( in_array( 'quarantine_attachment', $danger_file_projection['safe_actions'], true ) && in_array( 'start_safety_handoff', $danger_file_projection['safe_actions'], true ), 'safety handoff and malware quarantine must run as independent safe work' );

intake_scenario( 'verified-channel claim without evidence' );
$bad_verified = intake_fixture( 'bad-verified-channel' );
$bad_verified['source']['sender_trust'] = 'verified_channel';
$bad_verified['source']['transport_integrity'] = 'verified';
intake_assert( is_wp_error( Tra_Vel_VIP_Intake_Policy::intake( $bad_verified ) ), 'verified sender label without evidence digest must fail closed' );

intake_scenario( 'raw PII and raw payload rejection' );
$raw_message = intake_fixture( 'raw-message' );
$raw_message['content']['free_text'] = 'name and phone would be raw here';
intake_assert( is_wp_error( Tra_Vel_VIP_Intake_Policy::intake( $raw_message ) ), 'raw free-text field must be rejected' );
$raw_contact = intake_fixture( 'raw-contact' );
$raw_contact['source']['phone_number'] = '+000000000';
intake_assert( is_wp_error( Tra_Vel_VIP_Intake_Policy::intake( $raw_contact ) ), 'raw phone field must be rejected' );
$raw_payload = intake_fixture( 'raw-provider' );
$raw_payload['raw_provider_payload'] = array( 'status' => 'anything' );
intake_assert( is_wp_error( Tra_Vel_VIP_Intake_Policy::intake( $raw_payload ) ), 'raw provider payload must be rejected' );
$boundary_leak = intake_fixture( 'boundary-leak' );
$boundary_leak['data_boundary']['raw_message_exposed'] = true;
intake_assert( is_wp_error( Tra_Vel_VIP_Intake_Policy::intake( $boundary_leak ) ), 'a raw-message boundary leak must fail closed' );
$boundary_type = intake_fixture( 'boundary-type' );
$boundary_type['data_boundary']['raw_message_exposed'] = 'false';
intake_assert( is_wp_error( Tra_Vel_VIP_Intake_Policy::intake( $boundary_type ) ), 'privacy boundary flags must be strict booleans, not truthy labels' );

intake_scenario( 'forged validated result projection' );
$forged = $ordinary;
$forged['fingerprint'] = intake_digest( 'forged-fingerprint' );
intake_assert( is_wp_error( Tra_Vel_VIP_Intake_State_Projection::project( $forged ) ), 'state projector must reject a forged policy result' );

intake_assert( 10 === count( Tra_Vel_VIP_Intake_Taxonomy::HIGH_IMPACT_SCOPES ), 'all ten consequential VIP scopes must remain explicitly non-executable at intake' );
intake_assert( $scenarios >= 20, 'runtime must cover at least twenty actual intake edge scenarios' );

echo "VIP no-login intake runtime passed ({$assertions} assertions; {$scenarios} scenarios; 6 channels; zero intake authorization).\n";
