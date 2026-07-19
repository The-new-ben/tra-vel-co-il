<?php
/**
 * Closed vocabulary for privacy-minimized, no-login VIP incident intake.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_VIP_Intake_Taxonomy {
	const CONTRACT_VERSION = '1.0.0';

	const CHANNELS = array( 'web', 'whatsapp', 'sms', 'email', 'voice_relay', 'operator' );

	const ACCESS_MODES = array( 'public_safety', 'public_incident', 'scoped_capability', 'verified_session', 'operator' );

	const CAPABILITY_STATES = array( 'absent', 'initial_get_unexchanged', 'active_scoped_session', 'consumed', 'expired', 'revoked' );

	const SENDER_TRUST_STATES = array( 'anonymous', 'unverified', 'verified_channel', 'conflicted', 'suspected_spoof' );

	const INCIDENT_FAMILIES = array(
		'immediate_danger',
		'flight_disruption',
		'accommodation_problem',
		'package_problem',
		'transfer_problem',
		'activity_problem',
		'dining_problem',
		'insurance_assistance',
		'connectivity_problem',
		'equipment_problem',
		'document_problem',
		'payment_problem',
		'lost_device',
		'cross_trip_problem',
		'unknown',
	);

	const NORMALIZED_INTENTS = array(
		'safety_report',
		'incident_report',
		'evidence_submission',
		'case_progress_request',
		'safe_contact_update',
		'high_impact_request',
		'unclear',
	);

	const AMBIGUITY_STATES = array( 'none', 'unclear_intent', 'ambiguous_trip', 'conflicting_instructions' );

	const RISK_SIGNALS = array( 'safety', 'stranded', 'minor', 'vulnerable', 'deadline', 'fraud', 'offline', 'none' );

	const REPORT_SCOPES = array( 'incident_report', 'ordinary_evidence_add', 'safe_contact_update', 'case_progress_view' );

	const HIGH_IMPACT_SCOPES = array(
		'service_reserve',
		'service_change',
		'service_cancel',
		'payment_authorize',
		'refund_destination_change',
		'identity_change',
		'guardian_authority_change',
		'sensitive_evidence_disclose',
		'recovery_channel_change',
		'delegate_manage',
	);

	const REQUESTABLE_SCOPES = array(
		'trip_view_redacted',
		'incident_report',
		'ordinary_evidence_add',
		'safe_contact_update',
		'case_progress_view',
		'operator_contact_approve',
		'decision_view',
		'service_reserve',
		'service_change',
		'service_cancel',
		'payment_authorize',
		'refund_destination_change',
		'identity_change',
		'guardian_authority_change',
		'sensitive_evidence_disclose',
		'recovery_channel_change',
		'delegate_manage',
	);

	const SAFE_ACTIONS = array(
		'accept_report',
		'create_case',
		'attach_report_metadata',
		'request_clarification',
		'start_safety_handoff',
		'notify_on_call_operator',
		'quarantine_attachment',
		'isolate_restricted_attachment',
		'retry_receipt_delivery',
		'request_step_up',
		'flag_sender_review',
		'review_case_reopen',
		'acknowledge_duplicate',
	);

	const CUSTOMER_STATES = array(
		'case_received',
		'immediate_safety_help',
		'already_received',
		'clarification_needed',
		'security_check_needed',
		'step_up_needed',
		'human_review_started',
		'attachment_quarantined',
		'case_reopen_review',
	);

	public static function has_high_impact_request( $scopes ) {
		return is_array( $scopes ) && (bool) array_intersect( $scopes, self::HIGH_IMPACT_SCOPES );
	}
}
