<?php
/** Focused runtime checks for the private traveler-profile evidence index. */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

$base = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/vip/';
require_once $base . 'class-tra-vel-traveler-profile-taxonomy.php';
require_once $base . 'class-tra-vel-traveler-profile-policy.php';

$assertions = 0;
function traveler_profile_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "Traveler profile runtime failed: {$message}\n" );
		exit( 1 );
	}
}
function traveler_profile_ref( $kind, $seed ) {
	return 'tv_' . $kind . '_' . substr( hash( 'sha256', $seed ), 0, 24 );
}
function traveler_profile_digest( $seed ) { return hash( 'sha256', $seed ); }
function traveler_profile_boundary() {
	return array(
		'server_only' => true,
		'public_serialization_allowed' => false,
		'vault_pointers_only' => true,
		'raw_identity_data_stored' => false,
		'raw_contact_data_stored' => false,
		'raw_document_data_stored' => false,
		'raw_medical_data_stored' => false,
		'raw_loyalty_credentials_stored' => false,
	);
}
function traveler_profile_field_boundary() {
	return array( 'server_only' => true, 'raw_value_stored' => false, 'vault_pointer_only' => true );
}
function traveler_profile_field( $code, $seed, $overrides = array() ) {
	$class = Tra_Vel_Traveler_Profile_Taxonomy::field_class( $code );
	$source = 'traveler';
	$assurance = 'self_asserted';
	if ( 'travel_document' === $class ) {
		$source = 'document_capture';
		$assurance = 'document_matched';
	} elseif ( 'contact' === $class ) {
		$assurance = 'contact_verified';
	} elseif ( 'authority' === $class ) {
		$source = 'operator';
		$assurance = 'authority_verified';
	} elseif ( 'loyalty' === $class ) {
		$source = 'loyalty_provider';
	}
	$field = array(
		'field_ref' => traveler_profile_ref( 'profile_field', $seed ),
		'field_code' => $code,
		'data_class' => $class,
		'value_digest' => traveler_profile_digest( 'value-' . $seed ),
		'vault_locator_ref' => traveler_profile_ref( 'vault_item', $seed ),
		'source' => $source,
		'assurance' => $assurance,
		'state' => 'current',
		'purposes' => array( 'reservation', 'travel_readiness', 'servicing' ),
		'retention_class' => Tra_Vel_Traveler_Profile_Taxonomy::retention_for_class( $class ),
		'observed_at' => '2026-07-01T09:00:00Z',
		'valid_until' => '2027-07-01T09:00:00Z',
		'source_evidence_digest' => traveler_profile_digest( 'evidence-' . $seed ),
		'supersedes_field_ref' => null,
		'data_boundary' => traveler_profile_field_boundary(),
	);
	return array_replace( $field, $overrides );
}
function traveler_profile_snapshot( $fields, $overrides = array() ) {
	$profile = array(
		'contract_version' => '1.0.0',
		'profile_ref' => traveler_profile_ref( 'profile', 'family-primary' ),
		'account_ref' => traveler_profile_ref( 'account', 'account-primary' ),
		'traveler_ref' => traveler_profile_ref( 'traveler', 'traveler-primary' ),
		'subject_kind' => 'adult',
		'version' => 1,
		'previous_profile_digest' => null,
		'profile_digest' => str_repeat( '0', 64 ),
		'fields' => array_values( $fields ),
		'created_at' => '2026-07-01T09:00:00Z',
		'updated_at' => '2026-07-10T09:00:00Z',
		'data_boundary' => traveler_profile_boundary(),
	);
	$profile = array_replace( $profile, $overrides );
	return Tra_Vel_Traveler_Profile_Policy::seal( $profile );
}
function traveler_profile_error_is( $value, $suffix ) {
	return is_wp_error( $value ) && 'tra_vel_traveler_profile_' . $suffix === $value->get_error_code();
}

$clock = strtotime( '2026-07-19T12:00:00Z' );
$flags = array( 'minor_present' => false, 'dependent_adult_present' => false, 'accessibility_required' => false, 'loyalty_requested' => false );

traveler_profile_assert( 3 === count( Tra_Vel_Traveler_Profile_Taxonomy::SUBJECT_KINDS ), 'all supported traveler subject kinds must be explicit' );
traveler_profile_assert( 9 === count( Tra_Vel_Traveler_Profile_Taxonomy::DATA_CLASSES ), 'all nine sensitive-data classes must be partitioned' );
traveler_profile_assert( count( Tra_Vel_Traveler_Profile_Taxonomy::FIELD_CLASSES ) >= 50, 'the registration profile must cover deep operational fields' );
traveler_profile_assert( 'travel_document' === Tra_Vel_Traveler_Profile_Taxonomy::field_class( 'document_number' ), 'document numbers must map to the travel-document boundary' );
traveler_profile_assert( 'restricted_health' === Tra_Vel_Traveler_Profile_Taxonomy::retention_for_class( 'health' ), 'health evidence must use restricted retention' );

$flight_codes = Tra_Vel_Traveler_Profile_Taxonomy::requirements_for_use_case( 'flight_reservation', $flags );
$flight_fields = array();
foreach ( $flight_codes as $index => $code ) {
	$flight_fields[] = traveler_profile_field( $code, 'flight-' . $index . '-' . $code );
}
$profile = traveler_profile_snapshot( $flight_fields );
traveler_profile_assert( ! is_wp_error( Tra_Vel_Traveler_Profile_Policy::profile( $profile, $clock ) ), 'a complete vault-pointer-only flight profile must validate' );
$ready = Tra_Vel_Traveler_Profile_Policy::readiness( $profile, 'flight_reservation', $flags, $clock );
traveler_profile_assert( ! is_wp_error( $ready ) && true === $ready['ready'], 'complete flight evidence must project as ready' );
traveler_profile_assert( 'none' === $ready['authorization_effect'], 'profile completeness must never grant supplier authority' );
traveler_profile_assert( array() === $ready['missing_field_codes'] && array() === $ready['stale_field_codes'], 'ready evidence must have no missing or stale fields' );
traveler_profile_assert( ! isset( $ready['fields'] ) && ! isset( $ready['vault_locator_ref'] ), 'readiness must not expose profile fields or vault locators' );

$empty = traveler_profile_snapshot( array() );
$missing = Tra_Vel_Traveler_Profile_Policy::readiness( $empty, 'flight_reservation', $flags, $clock );
traveler_profile_assert( false === $missing['ready'] && count( $missing['missing_field_codes'] ) === count( $flight_codes ), 'an empty profile must fail closed with exact missing codes' );

$minor_flags = array_replace( $flags, array( 'minor_present' => true ) );
$minor_requirements = Tra_Vel_Traveler_Profile_Taxonomy::requirements_for_use_case( 'accommodation_reservation', $minor_flags );
traveler_profile_assert( in_array( 'guardian_relationship', $minor_requirements, true ) && in_array( 'guardian_authority_packet', $minor_requirements, true ), 'a minor must add relationship and authority evidence' );
$dependent_flags = array_replace( $flags, array( 'dependent_adult_present' => true, 'accessibility_required' => true, 'loyalty_requested' => true ) );
$dependent_requirements = Tra_Vel_Traveler_Profile_Taxonomy::requirements_for_use_case( 'emergency_ready', $dependent_flags );
foreach ( array( 'dependent_support_plan', 'guardian_authority_packet', 'transfer_assistance', 'loyalty_program_membership' ) as $required_code ) {
	traveler_profile_assert( in_array( $required_code, $dependent_requirements, true ), "{$required_code} must be inferred from party and service flags" );
}
traveler_profile_assert( array() === Tra_Vel_Traveler_Profile_Taxonomy::requirements_for_use_case( 'flight_reservation', array( 'minor_present' => false ) ), 'partial party flags must fail closed' );
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::readiness( $profile, 'unknown_use_case', $flags, $clock ), 'readiness_request_invalid' ), 'unknown readiness use cases must be rejected' );
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::readiness( $profile, 'flight_reservation', $flags, 'bad-clock' ), 'readiness_clock_invalid' ), 'invalid readiness clocks must be rejected' );
$minor_profile = traveler_profile_snapshot( array(), array( 'subject_kind' => 'minor' ) );
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::readiness( $minor_profile, 'accommodation_reservation', $flags, $clock ), 'readiness_subject_flags_invalid' ), 'a minor profile cannot be evaluated with party flags that erase the minor' );
$dependent_profile = traveler_profile_snapshot( array(), array( 'subject_kind' => 'dependent_adult' ) );
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::readiness( $dependent_profile, 'emergency_ready', $flags, $clock ), 'readiness_subject_flags_invalid' ), 'a dependent-adult profile cannot be evaluated with party flags that erase authority needs' );

$historically_current = traveler_profile_field( 'legal_name', 'historic', array( 'observed_at' => '2026-06-01T09:00:00Z', 'valid_until' => '2026-07-01T09:00:00Z' ) );
$historic_profile = traveler_profile_snapshot( array( $historically_current ), array( 'created_at' => '2026-06-01T09:00:00Z', 'updated_at' => '2026-06-15T09:00:00Z' ) );
$historic_ready = Tra_Vel_Traveler_Profile_Policy::readiness( $historic_profile, 'accommodation_reservation', $flags, $clock );
traveler_profile_assert( in_array( 'legal_name', $historic_ready['stale_field_codes'], true ), 'readiness must detect evidence that expired after the snapshot was recorded' );

$raw = $profile;
$raw['passport_number'] = 'sensitive-value';
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::profile( $raw, $clock ), 'sensitive_material_rejected' ), 'raw sensitive keys must be rejected before shape processing' );
$email = $profile;
$email['account_ref'] = 'person@example.test';
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::profile( $email, $clock ), 'sensitive_material_rejected' ), 'embedded email addresses must be rejected' );

$wrong_class = $profile;
$wrong_class['fields'][0]['data_class'] = 'health';
$wrong_class = Tra_Vel_Traveler_Profile_Policy::seal( $wrong_class );
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::profile( $wrong_class, $clock ), 'field_policy_invalid' ), 'field code and data class must agree' );
$duplicate_code = traveler_profile_snapshot( array( traveler_profile_field( 'legal_name', 'dup-a' ), traveler_profile_field( 'legal_name', 'dup-b' ) ) );
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::profile( $duplicate_code, $clock ), 'profile_field_duplicate' ), 'duplicate field codes must be rejected' );
$duplicate_ref_a = traveler_profile_field( 'legal_name', 'same-ref' );
$duplicate_ref_b = traveler_profile_field( 'date_of_birth', 'other-ref', array( 'field_ref' => $duplicate_ref_a['field_ref'] ) );
$duplicate_ref = traveler_profile_snapshot( array( $duplicate_ref_a, $duplicate_ref_b ) );
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::profile( $duplicate_ref, $clock ), 'profile_field_duplicate' ), 'duplicate field references must be rejected' );

$past_current = traveler_profile_snapshot( array( traveler_profile_field( 'legal_name', 'past-current', array( 'valid_until' => '2026-07-05T09:00:00Z' ) ) ) );
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::profile( $past_current, $clock ), 'field_freshness_invalid' ), 'a current field already expired at profile update must be rejected' );
$future_expired = traveler_profile_snapshot( array( traveler_profile_field( 'legal_name', 'future-expired', array( 'state' => 'expired' ) ) ) );
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::profile( $future_expired, $clock ), 'field_expiry_invalid' ), 'expired state must bind a reached validity boundary' );
$bad_assurance = traveler_profile_snapshot( array( traveler_profile_field( 'document_number', 'bad-assurance', array( 'source' => 'traveler', 'assurance' => 'supplier_accepted' ) ) ) );
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::profile( $bad_assurance, $clock ), 'field_assurance_source_invalid' ), 'assurance issuers must be allowlisted' );

$tampered = $profile;
$tampered['version'] = 2;
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::profile( $tampered, $clock ), 'profile_ancestry_invalid' ), 'a successor without predecessor digest must be rejected before accepting stale seals' );
$digest_tamper = $profile;
$digest_tamper['updated_at'] = '2026-07-11T09:00:00Z';
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::profile( $digest_tamper, $clock ), 'profile_digest_mismatch' ), 'snapshot mutations must invalidate the digest' );

$replacement = traveler_profile_field( $flight_fields[0]['field_code'], 'replacement', array( 'supersedes_field_ref' => $flight_fields[0]['field_ref'], 'observed_at' => '2026-07-11T09:00:00Z' ) );
$next_fields = $flight_fields;
$next_fields[0] = $replacement;
$next = traveler_profile_snapshot( $next_fields, array(
	'version' => 2,
	'previous_profile_digest' => $profile['profile_digest'],
	'updated_at' => '2026-07-12T09:00:00Z',
) );
traveler_profile_assert( true === Tra_Vel_Traveler_Profile_Policy::assert_successor( $profile, $next, $clock ), 'an explicitly superseding immutable field revision must advance' );

$rewritten_fields = $flight_fields;
$rewritten_fields[0]['value_digest'] = traveler_profile_digest( 'rewritten-in-place' );
$rewritten = traveler_profile_snapshot( $rewritten_fields, array( 'version' => 2, 'previous_profile_digest' => $profile['profile_digest'], 'updated_at' => '2026-07-12T09:00:00Z' ) );
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::assert_successor( $profile, $rewritten, $clock ), 'successor_field_rewritten' ), 'an existing field reference cannot be rewritten in place' );

$removed_fields = $flight_fields;
array_pop( $removed_fields );
$removed = traveler_profile_snapshot( $removed_fields, array( 'version' => 2, 'previous_profile_digest' => $profile['profile_digest'], 'updated_at' => '2026-07-12T09:00:00Z' ) );
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::assert_successor( $profile, $removed, $clock ), 'successor_field_removed' ), 'profile history cannot silently remove a field' );

$unbound_fields = $flight_fields;
$unbound_fields[0] = traveler_profile_field( $flight_fields[0]['field_code'], 'unbound-replacement' );
$unbound = traveler_profile_snapshot( $unbound_fields, array( 'version' => 2, 'previous_profile_digest' => $profile['profile_digest'], 'updated_at' => '2026-07-12T09:00:00Z' ) );
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::assert_successor( $profile, $unbound, $clock ), 'successor_lineage_invalid' ), 'replacement fields must bind their predecessor' );

$foreign_lineage_fields = $flight_fields;
$foreign_lineage_fields[] = traveler_profile_field( 'preferred_name', 'foreign-lineage', array( 'supersedes_field_ref' => $flight_fields[0]['field_ref'] ) );
$foreign_lineage = traveler_profile_snapshot( $foreign_lineage_fields, array( 'version' => 2, 'previous_profile_digest' => $profile['profile_digest'], 'updated_at' => '2026-07-12T09:00:00Z' ) );
traveler_profile_assert( traveler_profile_error_is( Tra_Vel_Traveler_Profile_Policy::assert_successor( $profile, $foreign_lineage, $clock ), 'successor_lineage_invalid' ), 'a new field code cannot steal another field code lineage' );

$serialized = wp_json_encode( array( 'profile' => $profile, 'readiness' => $ready ) );
foreach ( array( 'example.test', 'passport value', 'card number', 'medical note', 'loyalty password' ) as $forbidden ) {
	traveler_profile_assert( false === stripos( $serialized, $forbidden ), "serialized contract output must not contain {$forbidden}" );
}

fwrite( STDOUT, "Traveler profile runtime passed: {$assertions} assertions; " . count( Tra_Vel_Traveler_Profile_Taxonomy::FIELD_CLASSES ) . " field codes; vault-pointer-only registration evidence.\n" );
