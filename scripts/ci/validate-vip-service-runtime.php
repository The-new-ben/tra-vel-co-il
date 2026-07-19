<?php
/** Focused runtime checks for the VIP traveler and service-case contracts. */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

$base = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/vip/';
require_once $base . 'class-tra-vel-vip-taxonomy.php';
require_once $base . 'class-tra-vel-vip-state-machine.php';
require_once $base . 'class-tra-vel-vip-policy.php';

$assertions = 0;
function vip_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "VIP service runtime failed: {$message}\n" );
		exit( 1 );
	}
}
function vip_ref( $kind, $suffix ) { return 'tv_' . $kind . '_' . str_pad( $suffix, 16, 'x' ); }
function vip_digest( $seed ) { return hash( 'sha256', $seed ); }
function vip_boundary() {
	return array(
		'raw_identity_data_exposed' => false,
		'raw_payment_data_exposed' => false,
		'raw_medical_data_exposed' => false,
		'raw_provider_payload_exposed' => false,
		'bearer_secret_exposed' => false,
	);
}

vip_assert( 9 === count( Tra_Vel_VIP_Taxonomy::VERTICALS ), 'nine canonical product/service verticals must be preserved' );
vip_assert( 50 === count( Tra_Vel_VIP_Taxonomy::STRESS_SCENARIOS ), 'stress vocabulary must contain exactly 50 scenarios' );
vip_assert( range( 1, 50 ) === array_keys( Tra_Vel_VIP_Taxonomy::STRESS_SCENARIOS ), 'scenario numbers must be continuous from 1 through 50' );
vip_assert( 50 === count( array_unique( Tra_Vel_VIP_Taxonomy::STRESS_SCENARIOS ) ), 'scenario codes must be unique' );
foreach ( Tra_Vel_VIP_Taxonomy::VERTICALS as $vertical ) {
	vip_assert( isset( Tra_Vel_VIP_Taxonomy::INCIDENTS[ $vertical ] ) && count( Tra_Vel_VIP_Taxonomy::INCIDENTS[ $vertical ] ) >= 8, "{$vertical} must have a deep incident vocabulary" );
	foreach ( Tra_Vel_VIP_Taxonomy::INCIDENTS[ $vertical ] as $incident_type ) {
		vip_assert( $vertical === Tra_Vel_VIP_Taxonomy::incident_vertical( $incident_type ), "{$incident_type} must resolve to {$vertical}" );
	}
}

$flags = array( 'minor_present' => false, 'dependent_adult_present' => false, 'accessibility_required' => false );
$requirements = array();
$verified = array( 'contact_verified', 'reachable_contact_verified', 'travel_document_snapshot_verified', 'payment_session_owner_verified', 'payment_state_sufficient', 'supplier_confirmation_verified', 'mandatory_traveler_data_accepted', 'traveler_manifest_snapshot_verified', 'document_admissibility_current', 'service_contact_pack_offline', 'dependency_health_checked' );
foreach ( Tra_Vel_VIP_Policy::requirements_for_gate( 'ready_to_travel', $flags ) as $code ) {
	$status = in_array( $code, $verified, true ) ? 'verified' : 'self_asserted';
	$requirements[] = array(
		'code' => $code,
		'status' => $status,
		'evidence_digest' => 'verified' === $status ? vip_digest( 'registration-' . $code ) : null,
		'verified_at' => 'verified' === $status ? '2026-07-19T09:00:00Z' : null,
	);
}
$registration = array(
	'contract_version' => '1.0.0',
	'registration_ref' => vip_ref( 'registration', 'ready-travel' ),
	'trip_ref' => vip_ref( 'trip', 'thailand-trip' ),
	'account_ref' => vip_ref( 'account', 'primary-account' ),
	'gate' => 'ready_to_travel',
	'party_flags' => $flags,
	'requirements' => $requirements,
	'role_manifest_ref' => vip_ref( 'manifest', 'family-manifest' ),
	'version' => 3,
	'created_at' => '2026-07-19T08:00:00Z',
	'updated_at' => '2026-07-19T10:00:00Z',
	'data_boundary' => vip_boundary(),
);
vip_assert( ! is_wp_error( Tra_Vel_VIP_Policy::traveler_registration( $registration ) ), 'complete progressive registration must reach ready_to_travel' );
$blocked_registration = $registration;
array_pop( $blocked_registration['requirements'] );
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::traveler_registration( $blocked_registration ) ), 'a declared gate must fail when one cumulative requirement is missing' );
$unknown_registration = $registration;
$unknown_registration['notes'] = 'not allowed';
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::traveler_registration( $unknown_registration ) ), 'unknown registration fields must fail closed' );

$orphan_registration = $registration;
$orphan_registration['requirements'][0]['evidence_digest'] = vip_digest( 'orphan-self-asserted-evidence' );
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::traveler_registration( $orphan_registration ) ), 'self-asserted registration facts must not smuggle verification evidence' );
$future_evidence_registration = $registration;
foreach ( $future_evidence_registration['requirements'] as $index => $requirement ) {
	if ( 'contact_verified' === $requirement['code'] ) {
		$future_evidence_registration['requirements'][ $index ]['verified_at'] = '2026-07-19T10:01:00Z';
		break;
	}
}
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::traveler_registration( $future_evidence_registration, '2026-07-19T10:05:00Z' ) ), 'verification evidence cannot post-date the registration revision it supports' );
$no_loyalty_registration = $registration;
foreach ( $no_loyalty_registration['requirements'] as $index => $requirement ) {
	if ( 'loyalty_preferences_recorded' === $requirement['code'] ) {
		$no_loyalty_registration['requirements'][ $index ]['status'] = 'not_applicable';
		break;
	}
}
vip_assert( ! is_wp_error( Tra_Vel_VIP_Policy::traveler_registration( $no_loyalty_registration ) ), 'a traveler without a loyalty program must not be blocked from booking readiness' );
$dependent_flags = array( 'minor_present' => false, 'dependent_adult_present' => true, 'accessibility_required' => false );
$dependent_requirements = Tra_Vel_VIP_Policy::requirements_for_gate( 'ready_to_reserve', $dependent_flags );
vip_assert( in_array( 'dependent_support_plan_recorded', $dependent_requirements, true ) && in_array( 'dependent_authority_verified', $dependent_requirements, true ), 'dependent-adult travel must require both a support plan and verified authority before reservation' );

$previous_registration = $registration;
$previous_registration['gate'] = 'ready_to_fulfill';
$previous_registration['version'] = 2;
$previous_registration['updated_at'] = '2026-07-19T09:30:00Z';
$previous_required = Tra_Vel_VIP_Policy::requirements_for_gate( 'ready_to_fulfill', $flags );
$previous_registration['requirements'] = array_values( array_filter( $previous_registration['requirements'], static function ( $requirement ) use ( $previous_required ) { return in_array( $requirement['code'], $previous_required, true ); } ) );
$progress_changed = array_values( array_diff( array_column( $registration['requirements'], 'code' ), array_column( $previous_registration['requirements'], 'code' ) ) );
$progress_transition = array(
	'contract_version' => '1.0.0',
	'transition_ref' => vip_ref( 'registration_transition', 'progress-ready' ),
	'registration_ref' => $registration['registration_ref'],
	'trip_ref' => $registration['trip_ref'],
	'from_version' => 2,
	'to_version' => 3,
	'from_gate' => 'ready_to_fulfill',
	'to_gate' => 'ready_to_travel',
	'reason' => 'progress',
	'changed_requirements' => $progress_changed,
	'invalidated_requirements' => array(),
	'actor_ref' => vip_ref( 'principal', 'registration-owner' ),
	'authority_digest' => vip_digest( 'registration-progress-authority' ),
	'evidence_digest' => vip_digest( 'registration-progress-evidence' ),
	'occurred_at' => '2026-07-19T10:00:00Z',
	'authorization_effect' => 'registration_only',
	'data_boundary' => vip_boundary(),
);
vip_assert( ! is_wp_error( Tra_Vel_VIP_Policy::registration_successor( $previous_registration, $registration, $progress_transition, '2026-07-19T10:05:00Z' ) ), 'one evidence-bound progressive transition may advance exactly one registration gate' );
$skipped_gate_transition = $progress_transition;
$skipped_gate_previous = $previous_registration;
$skipped_gate_previous['gate'] = 'ready_to_quote';
$skipped_gate_previous['requirements'] = array_values( array_filter( $skipped_gate_previous['requirements'], static function ( $requirement ) use ( $flags ) { return in_array( $requirement['code'], Tra_Vel_VIP_Policy::requirements_for_gate( 'ready_to_quote', $flags ), true ); } ) );
$skipped_gate_transition['from_gate'] = 'ready_to_quote';
$skipped_gate_transition['changed_requirements'] = array_values( array_diff( array_column( $registration['requirements'], 'code' ), array_column( $skipped_gate_previous['requirements'], 'code' ) ) );
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::registration_successor( $skipped_gate_previous, $registration, $skipped_gate_transition, '2026-07-19T10:05:00Z' ) ), 'progress cannot jump over unreviewed registration gates' );

$document_change_registration = $registration;
$document_change_registration['version'] = 4;
$document_change_registration['gate'] = 'ready_to_quote';
$document_change_registration['updated_at'] = '2026-07-19T10:10:00Z';
$quote_required = Tra_Vel_VIP_Policy::requirements_for_gate( 'ready_to_quote', $flags );
$document_change_registration['requirements'] = array_values( array_filter( $document_change_registration['requirements'], static function ( $requirement ) use ( $quote_required ) { return in_array( $requirement['code'], $quote_required, true ); } ) );
$document_invalidated = array_values( array_diff( array_column( $registration['requirements'], 'code' ), array_column( $document_change_registration['requirements'], 'code' ) ) );
$document_transition = array(
	'contract_version' => '1.0.0',
	'transition_ref' => vip_ref( 'registration_transition', 'document-change' ),
	'registration_ref' => $registration['registration_ref'],
	'trip_ref' => $registration['trip_ref'],
	'from_version' => 3,
	'to_version' => 4,
	'from_gate' => 'ready_to_travel',
	'to_gate' => 'ready_to_quote',
	'reason' => 'document_change',
	'changed_requirements' => $document_invalidated,
	'invalidated_requirements' => $document_invalidated,
	'actor_ref' => vip_ref( 'principal', 'registration-owner' ),
	'authority_digest' => vip_digest( 'document-change-authority' ),
	'evidence_digest' => vip_digest( 'document-change-evidence' ),
	'occurred_at' => '2026-07-19T10:10:00Z',
	'authorization_effect' => 'registration_only',
	'data_boundary' => vip_boundary(),
);
vip_assert( ! is_wp_error( Tra_Vel_VIP_Policy::registration_successor( $registration, $document_change_registration, $document_transition, '2026-07-19T10:15:00Z' ) ), 'a changed travel document must explicitly invalidate downstream supplier, manifest, and departure readiness' );
$hidden_invalidation = $document_transition;
array_pop( $hidden_invalidation['invalidated_requirements'] );
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::registration_successor( $registration, $document_change_registration, $hidden_invalidation, '2026-07-19T10:15:00Z' ) ), 'a successor transition cannot hide one invalidated readiness requirement' );
$false_progress = $document_transition;
$false_progress['reason'] = 'progress';
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::registration_successor( $registration, $document_change_registration, $false_progress, '2026-07-19T10:15:00Z' ) ), 'a readiness regression cannot be mislabeled as positive progress' );
$supplier_authority_transition = $progress_transition;
$supplier_authority_transition['authorization_effect'] = 'supplier_change';
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::registration_successor( $previous_registration, $registration, $supplier_authority_transition, '2026-07-19T10:05:00Z' ) ), 'registration evidence must never authorize a supplier or payment action' );

$principal_ref = vip_ref( 'principal', 'traveler-owner' );
$traveler_ref = vip_ref( 'traveler', 'traveler-one' );
$manifest = array(
	'contract_version' => '1.0.0',
	'manifest_ref' => vip_ref( 'manifest', 'family-manifest' ),
	'trip_ref' => vip_ref( 'trip', 'thailand-trip' ),
	'version' => 1,
	'principals' => array(
		array(
			'principal_ref' => $principal_ref,
			'roles' => array( 'traveler', 'booker', 'payer' ),
			'traveler_refs' => array( $traveler_ref ),
			'authority_scopes' => array( 'trip_view_redacted', 'incident_report', 'service_change', 'service_cancel', 'payment_authorize' ),
			'authority_source' => 'self',
			'authority_evidence_digest' => vip_digest( 'authority-evidence' ),
			'valid_from' => '2026-07-19T08:00:00Z',
			'valid_until' => '2026-07-20T08:00:00Z',
		),
	),
	'created_at' => '2026-07-19T08:00:00Z',
	'data_boundary' => vip_boundary(),
);
vip_assert( ! is_wp_error( Tra_Vel_VIP_Policy::role_manifest( $manifest ) ), 'explicit traveler/booker/payer authority must validate' );
$bad_manifest = $manifest;
$bad_manifest['principals'][0]['roles'] = array( 'emergency_contact' );
$bad_manifest['principals'][0]['authority_scopes'] = array( 'service_cancel' );
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::role_manifest( $bad_manifest ) ), 'emergency-contact status must never imply cancellation authority' );

$weak_assurance = array(
	'level' => 'recent_auth',
	'verified_at' => '2026-07-19T09:55:00Z',
	'expires_at' => '2026-07-19T10:15:00Z',
	'method_reference_digest' => vip_digest( 'recent-auth' ),
);
$strong_assurance = $weak_assurance;
$strong_assurance['level'] = 'strong_reauth';
$strong_assurance['method_reference_digest'] = vip_digest( 'strong-auth' );
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::authorize_scope( $manifest, $principal_ref, $traveler_ref, 'service_cancel', $weak_assurance, '2026-07-19T10:00:00Z' ) ), 'high-impact scope must reject ordinary recent authentication' );
vip_assert( true === Tra_Vel_VIP_Policy::authorize_scope( $manifest, $principal_ref, $traveler_ref, 'service_cancel', $strong_assurance, '2026-07-19T10:00:00Z' ), 'high-impact scope may proceed only with explicit authority and strong step-up' );
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::authorize_scope( $manifest, $principal_ref, vip_ref( 'traveler', 'someone-else' ), 'service_cancel', $strong_assurance, '2026-07-19T10:00:00Z' ) ), 'authority must stay bound to the named traveler scope' );

$grant = array(
	'contract_version' => '1.0.0',
	'capability_ref' => vip_ref( 'capability', 'incident-link' ),
	'capability_digest' => vip_digest( 'keyed-capability-digest' ),
	'trip_ref' => vip_ref( 'trip', 'thailand-trip' ),
	'case_ref' => vip_ref( 'case', 'flight-change' ),
	'account_ref' => vip_ref( 'account', 'primary-account' ),
	'issue_reason' => 'incident_intake',
	'channel' => 'email',
	'allowed_scopes' => array( 'trip_view_redacted', 'incident_report', 'case_progress_view' ),
	'disclosure_classes' => array( 'trip_redacted', 'case_progress' ),
	'one_time' => true,
	'consumed_at' => null,
	'issued_at' => '2026-07-19T09:00:00Z',
	'expires_at' => '2026-07-19T10:00:00Z',
	'revoked_at' => null,
	'rotation_generation' => 1,
	'scanner_safe_initial_get' => true,
	'session_exchange_required' => true,
	'security' => array( 'http_only' => true, 'secure' => true, 'same_site' => 'Strict', 'referrer_policy' => 'no-referrer', 'third_party_scripts' => false, 'indexable' => false ),
	'data_boundary' => vip_boundary(),
);
vip_assert( ! is_wp_error( Tra_Vel_VIP_Policy::capability_grant( $grant ) ), 'digest-only low-risk capability grant must validate' );
$high_impact_grant = $grant;
$high_impact_grant['allowed_scopes'][] = 'payment_authorize';
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::capability_grant( $high_impact_grant ) ), 'a magic link must never grant payment authority' );
$bearer_leak = $grant;
$bearer_leak['bearer_token'] = 'not-storable';
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::capability_grant( $bearer_leak ) ), 'a bearer value must never enter the stored grant projection' );

$deadline = array(
	'contract_version' => '1.0.0',
	'deadline_ref' => vip_ref( 'deadline', 'supplier-cutoff' ),
	'case_ref' => vip_ref( 'case', 'flight-change' ),
	'type' => 'cancellation_cutoff',
	'basis' => 'supplier_cutoff',
	'due_at' => '2026-07-19T10:00:00Z',
	'local_due_at' => '2026-07-19T13:00:00+03:00',
	'local_timezone' => 'Asia/Jerusalem',
	'safety_margin_seconds' => 900,
	'owner_ref' => vip_ref( 'principal', 'case-owner' ),
	'escalation_ref' => vip_ref( 'principal', 'duty-manager' ),
	'state' => 'scheduled',
	'source_version_digest' => vip_digest( 'supplier-policy-version' ),
	'computed_at' => '2026-07-19T09:00:00Z',
	'satisfied_at' => null,
	'data_boundary' => vip_boundary(),
);
vip_assert( ! is_wp_error( Tra_Vel_VIP_Policy::deadline( $deadline ) ), 'deadline must preserve equivalent supplier-local and UTC instants' );
$bad_clock = $deadline;
$bad_clock['local_due_at'] = '2026-07-19T14:00:00+03:00';
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::deadline( $bad_clock ) ), 'a mismatched local deadline clock must fail closed' );

$decision = array(
	'contract_version' => '1.0.0',
	'decision_ref' => vip_ref( 'decision', 'cancel-option' ),
	'case_ref' => vip_ref( 'case', 'flight-change' ),
	'version' => 2,
	'status' => 'authorized',
	'option_refs' => array( vip_ref( 'option', 'keep-service' ), vip_ref( 'option', 'cancel-service' ) ),
	'recommended_option_ref' => vip_ref( 'option', 'cancel-service' ),
	'impact_scopes' => array( 'service_cancel' ),
	'required_approver' => array( 'principal_ref' => $principal_ref, 'role' => 'booker', 'authority_scope' => 'service_cancel' ),
	'quote_expires_at' => '2026-07-19T11:00:00Z',
	'decision_expires_at' => '2026-07-19T10:55:00Z',
	'policy_snapshot_digest' => vip_digest( 'policy-snapshot' ),
	'request_digest' => vip_digest( 'cancel-request-v2' ),
	'authorization' => array(
		'authorization_ref' => vip_ref( 'authorization', 'cancel-auth' ),
		'actor_ref' => $principal_ref,
		'authority_evidence_digest' => vip_digest( 'authority-evidence' ),
		'authorized_scopes' => array( 'service_cancel' ),
		'expected_decision_version' => 2,
		'request_digest' => vip_digest( 'cancel-request-v2' ),
		'authorized_at' => '2026-07-19T10:00:00Z',
		'expires_at' => '2026-07-19T10:45:00Z',
		'step_up' => array( 'level' => 'strong_reauth', 'verified_at' => '2026-07-19T09:59:00Z', 'expires_at' => '2026-07-19T10:15:00Z', 'method_reference_digest' => vip_digest( 'strong-auth' ) ),
	),
	'created_at' => '2026-07-19T09:45:00Z',
	'data_boundary' => vip_boundary(),
);
vip_assert( ! is_wp_error( Tra_Vel_VIP_Policy::decision( $decision ) ), 'current decision version plus strong step-up authorization must validate' );
$weak_decision = $decision;
$weak_decision['authorization']['step_up']['level'] = 'recent_auth';
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::decision( $weak_decision ) ), 'high-impact decision must reject weak step-up' );
$stale_decision = $decision;
$stale_decision['authorization']['expected_decision_version'] = 1;
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::decision( $stale_decision ) ), 'stale decision authorization must fail closed' );

$outcomes = array( 'safety' => 'stable', 'continuity' => 'at_risk', 'supplier' => 'pending', 'financial' => 'not_started', 'evidence' => 'collecting', 'communication' => 'delivered' );
$case = array(
	'contract_version' => '1.0.0',
	'case_ref' => vip_ref( 'case', 'flight-change' ),
	'public_case_ref' => 'TV-A1B2C3D4',
	'trip_ref' => vip_ref( 'trip', 'thailand-trip' ),
	'intake_access' => 'scoped_trip_link',
	'incident' => array(
		'type' => 'flight.schedule_change',
		'affected_services' => array( 'flight', 'transfer', 'accommodation' ),
		'people_at_risk' => array( 'traveler_count' => 2, 'minor_count' => 0, 'vulnerable_count' => 0, 'stranded_count' => 0, 'immediate_danger' => false ),
		'current_location_ref' => vip_ref( 'location', 'coarse-location' ),
		'next_critical_event_ref' => vip_ref( 'event', 'departure-event' ),
		'evidence_status' => 'collecting',
		'authorization_status' => 'pending',
		'supplier_contactability' => 'reachable',
		'financial_exposure' => 'material',
	),
	'lifecycle' => 'triaged',
	'severity' => 'P1',
	'operational_health' => 'at_risk',
	'owner_ref' => vip_ref( 'principal', 'case-owner' ),
	'deadline_refs' => array( vip_ref( 'deadline', 'supplier-cutoff' ) ),
	'decision_refs' => array( vip_ref( 'decision', 'cancel-option' ) ),
	'operation_refs' => array(),
	'outcomes' => $outcomes,
	'resolution' => null,
	'version' => 4,
	'opened_at' => '2026-07-19T09:00:00Z',
	'updated_at' => '2026-07-19T09:30:00Z',
	'data_boundary' => vip_boundary(),
);
vip_assert( ! is_wp_error( Tra_Vel_VIP_Policy::service_case( $case ) ), 'deep flight disruption case projection must validate' );
$danger_case = $case;
$danger_case['incident']['people_at_risk']['immediate_danger'] = true;
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::service_case( $danger_case ) ), 'immediate danger cannot be understated below P0' );
$unknown_case = $case;
$unknown_case['free_text'] = 'raw narrative';
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::service_case( $unknown_case ) ), 'service cases must reject untyped free text fields' );

$event = array(
	'contract_version' => '1.0.0',
	'event_ref' => vip_ref( 'event', 'verify-event' ),
	'case_ref' => vip_ref( 'case', 'flight-change' ),
	'sequence' => 2,
	'expected_case_version' => 1,
	'new_case_version' => 2,
	'type' => 'case.verified',
	'lifecycle_before' => 'received',
	'lifecycle_after' => 'verified',
	'lifecycle_command' => 'verify',
	'severity_before' => 'P1',
	'severity_after' => 'P1',
	'outcome_change' => null,
	'actor' => array( 'type' => 'operator', 'principal_ref' => vip_ref( 'principal', 'case-owner' ), 'authority_scope' => null, 'authority_evidence_digest' => null ),
	'correlation_ref' => vip_ref( 'correlation', 'case-correlation' ),
	'causation_ref' => null,
	'operation_ref' => null,
	'payload_digest' => vip_digest( 'verify-event-payload' ),
	'evidence_digests' => array( vip_digest( 'verification-evidence' ) ),
	'redaction_classification' => 'operator',
	'occurred_at' => '2026-07-19T09:05:00Z',
	'received_at' => '2026-07-19T09:05:01Z',
	'data_boundary' => vip_boundary(),
);
$accepted = Tra_Vel_VIP_Policy::service_case_event( $event );
vip_assert( ! is_wp_error( $accepted ) && false === $accepted['replay'], 'new append-only event must be accepted once' );
$replayed = Tra_Vel_VIP_Policy::service_case_event( $event, array( $event['event_ref'] => $accepted['fingerprint'] ) );
vip_assert( ! is_wp_error( $replayed ) && true === $replayed['replay'], 'identical event replay must deduplicate deterministically' );
$conflicting_replay = $event;
$conflicting_replay['payload_digest'] = vip_digest( 'different-payload' );
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::service_case_event( $conflicting_replay, array( $event['event_ref'] => $accepted['fingerprint'] ) ) ), 'same event reference with different immutable envelope must conflict' );
$unknown_event = $event;
$unknown_event['provider_response'] = array( 'raw' => true );
vip_assert( is_wp_error( Tra_Vel_VIP_Policy::service_case_event( $unknown_event ) ), 'unknown or raw provider event fields must fail closed' );

vip_assert( 'verified' === Tra_Vel_VIP_State_Machine::lifecycle_transition( 'received', 'verify' ), 'received case must verify before triage' );
vip_assert( is_wp_error( Tra_Vel_VIP_State_Machine::lifecycle_transition( 'received', 'resolve' ) ), 'received case cannot jump directly to resolved' );
$severity_change = Tra_Vel_VIP_State_Machine::severity_change( 'supplier_pending', 'P3', 'P0' );
vip_assert( ! is_wp_error( $severity_change ) && 'supplier_pending' === $severity_change['lifecycle'] && 'P0' === $severity_change['severity'], 'severity must change independently while lifecycle stays supplier_pending' );
vip_assert( 'uncertain' === Tra_Vel_VIP_State_Machine::operation_transition( 'started', 'timeout' ), 'ambiguous side effect must become uncertain' );
vip_assert( is_wp_error( Tra_Vel_VIP_State_Machine::operation_transition( 'uncertain', 'succeed' ) ), 'uncertain side effect cannot claim success without reconciliation' );
vip_assert( 'reconciled' === Tra_Vel_VIP_State_Machine::operation_transition( 'uncertain', 'reconcile' ), 'uncertain side effect must reconcile explicitly' );
vip_assert( 'uncertain' === Tra_Vel_VIP_State_Machine::outcome_transition( 'supplier', 'pending', 'timeout' ), 'supplier timeout must remain uncertain on its own outcome axis' );
vip_assert( 'attention_needed' === Tra_Vel_VIP_State_Machine::customer_projection( 'monitoring', 'P2', array( 'safety' => 'stable', 'continuity' => 'recovery_in_progress', 'supplier' => 'uncertain', 'financial' => 'pending', 'evidence' => 'collecting', 'communication' => 'delivered' ) ), 'customer projection must expose attention while supplier truth is uncertain' );

$invariants = array( 'no_duplicate_side_effect', 'no_false_success', 'current_authority_required', 'constraints_preserved', 'no_hidden_partial_failure', 'no_sensitive_general_events', 'owner_and_timer_required', 'money_balanced_by_currency', 'local_timezone_visible', 'event_replay_deterministic' );
$vertical_count = count( Tra_Vel_VIP_Taxonomy::VERTICALS );
foreach ( Tra_Vel_VIP_Taxonomy::STRESS_SCENARIOS as $number => $code ) {
	$vertical = Tra_Vel_VIP_Taxonomy::VERTICALS[ ( $number - 1 ) % $vertical_count ];
	$scenario = array(
		'contract_version' => '1.0.0',
		'scenario_ref' => vip_ref( 'scenario', 'scenario-' . $number ),
		'scenario_number' => $number,
		'scenario_code' => $code,
		'affected_verticals' => array( $vertical ),
		'injected_condition_codes' => array( 'fixture.' . $code ),
		'expected_event_types' => array( 'operation.uncertain', 'operation.reconciled' ),
		'expected_customer_projection' => 'attention_needed',
		'expected_case_severity' => 'P1',
		'expected_lifecycle' => 'uncertain',
		'expected_outcomes' => array( 'safety' => 'stable', 'continuity' => 'at_risk', 'supplier' => 'uncertain', 'financial' => 'pending', 'evidence' => 'collecting', 'communication' => 'delivered' ),
		'invariant_codes' => $invariants,
		'clock' => array( 'started_at' => '2026-07-19T09:00:00Z', 'timezone' => 'Asia/Jerusalem' ),
		'provider_script_digests' => array( vip_digest( 'provider-script-' . $number ) ),
		'expected_operator_task_codes' => array( 'reconcile.authoritative_state', 'protect.next_deadline' ),
		'data_boundary' => vip_boundary(),
	);
	vip_assert( ! is_wp_error( Tra_Vel_VIP_Policy::disruption_scenario( $scenario ) ), "stress scenario {$number} must validate against the closed vocabulary" );
}

echo "VIP service runtime passed ({$assertions} assertions; 50 scenarios; 9 verticals).\n";
