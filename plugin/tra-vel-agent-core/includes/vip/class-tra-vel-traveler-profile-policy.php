<?php
/**
 * Private, vault-pointer-only traveler-profile evidence policy.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Traveler_Profile_Policy {
	const MAX_FIELDS = 96;

	/**
	 * Validate one immutable profile-index snapshot.
	 *
	 * @return array|WP_Error
	 */
	public static function profile( $profile, $now = null ) {
		$keys = array( 'contract_version', 'profile_ref', 'account_ref', 'traveler_ref', 'subject_kind', 'version', 'previous_profile_digest', 'profile_digest', 'fields', 'created_at', 'updated_at', 'data_boundary' );
		if ( self::contains_sensitive_material( $profile ) ) {
			return self::error( 'sensitive_material_rejected', 'Traveler profile indexes accept digests and vault pointers only, never raw identity, contact, document, health, or loyalty credential data.' );
		}
		if ( ! self::exact_object( $profile, $keys ) || Tra_Vel_Traveler_Profile_Taxonomy::CONTRACT_VERSION !== $profile['contract_version'] ) {
			return self::error( 'profile_shape_invalid', 'The traveler profile is not the closed private index contract.' );
		}
		if ( ! self::ref( $profile['profile_ref'], 'profile' ) || ! self::ref( $profile['account_ref'], 'account' ) || ! self::ref( $profile['traveler_ref'], 'traveler' ) || ! in_array( $profile['subject_kind'], Tra_Vel_Traveler_Profile_Taxonomy::SUBJECT_KINDS, true ) || ! self::positive_int( $profile['version'] ) || ! self::digest( $profile['profile_digest'] ) || ! self::nullable_digest( $profile['previous_profile_digest'] ) ) {
			return self::error( 'profile_identity_invalid', 'The traveler profile identity, subject, revision, or ancestry is invalid.' );
		}
		if ( ( 1 === $profile['version'] && null !== $profile['previous_profile_digest'] ) || ( $profile['version'] > 1 && ! self::digest( $profile['previous_profile_digest'] ) ) ) {
			return self::error( 'profile_ancestry_invalid', 'A successor traveler profile must bind the exact previous snapshot digest.' );
		}
		$created = self::utc_timestamp( $profile['created_at'] );
		$updated = self::utc_timestamp( $profile['updated_at'] );
		$clock   = null === $now ? time() : ( is_int( $now ) && $now > 0 ? $now : null );
		if ( null === $created || null === $updated || null === $clock || $created > $updated || $updated > $clock ) {
			return self::error( 'profile_chronology_invalid', 'Profile evidence timestamps must be valid UTC observations at or before the validation clock.' );
		}
		if ( ! self::is_list( $profile['fields'] ) || count( $profile['fields'] ) > self::MAX_FIELDS ) {
			return self::error( 'profile_fields_invalid', 'Profile fields must be a bounded list.' );
		}
		$seen_codes = array();
		$seen_refs  = array();
		foreach ( $profile['fields'] as $field ) {
			$validated = self::field( $field, $updated );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}
			if ( isset( $seen_codes[ $field['field_code'] ] ) || isset( $seen_refs[ $field['field_ref'] ] ) ) {
				return self::error( 'profile_field_duplicate', 'A profile snapshot may contain only one current index entry per field code and field reference.' );
			}
			$seen_codes[ $field['field_code'] ] = true;
			$seen_refs[ $field['field_ref'] ]    = true;
		}
		if ( ! self::data_boundary( $profile['data_boundary'] ) ) {
			return self::error( 'profile_boundary_invalid', 'The traveler profile must remain server-only, pointer-only, and free of raw sensitive data.' );
		}
		$expected = self::profile_digest( $profile );
		if ( '' === $expected || ! hash_equals( $profile['profile_digest'], $expected ) ) {
			return self::error( 'profile_digest_mismatch', 'The traveler profile snapshot no longer matches its integrity digest.', 409 );
		}
		return $profile;
	}

	/**
	 * Seal a newly built profile snapshot.
	 */
	public static function seal( $profile ) {
		$profile['profile_digest'] = self::profile_digest( $profile );
		return $profile;
	}

	/**
	 * Return field readiness without disclosing any stored value or vault locator.
	 * This is evidence completeness only and grants no supplier authority.
	 *
	 * @return array|WP_Error
	 */
	public static function readiness( $profile, $use_case, $flags, $now ) {
		$clock = null === $now ? time() : ( is_int( $now ) && $now > 0 ? $now : null );
		if ( null === $clock ) {
			return self::error( 'readiness_clock_invalid', 'Readiness requires a valid observation clock.' );
		}
		$profile = self::profile( $profile, $clock );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}
		$required = Tra_Vel_Traveler_Profile_Taxonomy::requirements_for_use_case( $use_case, $flags );
		if ( ! $required ) {
			return self::error( 'readiness_request_invalid', 'A supported use case and exact party flags are required.' );
		}
		if ( ( 'minor' === $profile['subject_kind'] && true !== $flags['minor_present'] ) || ( 'dependent_adult' === $profile['subject_kind'] && true !== $flags['dependent_adult_present'] ) ) {
			return self::error( 'readiness_subject_flags_invalid', 'Party flags cannot omit the validated traveler subject kind.' );
		}
		$by_code = array();
		foreach ( $profile['fields'] as $field ) {
			$by_code[ $field['field_code'] ] = $field;
		}
		$missing = array();
		$stale   = array();
		foreach ( $required as $code ) {
			if ( ! isset( $by_code[ $code ] ) ) {
				$missing[] = $code;
			} elseif (
				! in_array( $by_code[ $code ]['state'], array( 'current', 'expiring' ), true ) ||
				( null !== $by_code[ $code ]['valid_until'] && self::utc_timestamp( $by_code[ $code ]['valid_until'] ) <= $clock )
			) {
				$stale[] = $code;
			}
		}
		return array(
			'use_case'             => $use_case,
			'profile_digest'        => $profile['profile_digest'],
			'profile_version'       => $profile['version'],
			'required_field_codes'  => $required,
			'missing_field_codes'   => $missing,
			'stale_field_codes'     => $stale,
			'ready'                 => ! $missing && ! $stale,
			'authorization_effect'  => 'none',
		);
	}

	/**
	 * Prove one exact successor while preventing in-place history rewriting.
	 *
	 * @return true|WP_Error
	 */
	public static function assert_successor( $previous, $next, $now ) {
		$previous = self::profile( $previous, $now );
		$next     = self::profile( $next, $now );
		if ( is_wp_error( $previous ) || is_wp_error( $next ) ) {
			return self::error( 'successor_profile_invalid', 'Both traveler profile snapshots must validate at the comparison clock.', 409 );
		}
		if ( $previous['profile_ref'] !== $next['profile_ref'] || $previous['account_ref'] !== $next['account_ref'] || $previous['traveler_ref'] !== $next['traveler_ref'] || $previous['subject_kind'] !== $next['subject_kind'] || $previous['created_at'] !== $next['created_at'] || $next['version'] !== $previous['version'] + 1 || $next['previous_profile_digest'] !== $previous['profile_digest'] || self::utc_timestamp( $next['updated_at'] ) <= self::utc_timestamp( $previous['updated_at'] ) ) {
			return self::error( 'successor_invalid', 'A traveler profile successor must advance exactly once and bind the same subject and predecessor.', 409 );
		}
		$previous_by_code = array();
		$next_by_code     = array();
		foreach ( $previous['fields'] as $field ) {
			$previous_by_code[ $field['field_code'] ] = $field;
		}
		foreach ( $next['fields'] as $field ) {
			$next_by_code[ $field['field_code'] ] = $field;
			if ( ! isset( $previous_by_code[ $field['field_code'] ] ) ) {
				if ( null !== $field['supersedes_field_ref'] ) {
					return self::error( 'successor_lineage_invalid', 'A newly introduced profile field cannot claim another field code\'s lineage.', 409 );
				}
				continue;
			}
			$prior = $previous_by_code[ $field['field_code'] ];
			if ( $prior['field_ref'] === $field['field_ref'] ) {
				if ( self::canonicalize( $prior ) !== self::canonicalize( $field ) ) {
					return self::error( 'successor_field_rewritten', 'An existing profile-field reference is immutable; changed evidence requires a new reference.', 409 );
				}
			} elseif ( $field['supersedes_field_ref'] !== $prior['field_ref'] ) {
				return self::error( 'successor_lineage_invalid', 'A replaced profile field must explicitly supersede the previous field reference.', 409 );
			}
		}
		foreach ( $previous_by_code as $code => $field ) {
			if ( ! isset( $next_by_code[ $code ] ) ) {
				return self::error( 'successor_field_removed', 'A successor must preserve each field code as immutable history or replace it explicitly, including revocations.', 409 );
			}
		}
		return true;
	}

	public static function profile_digest( $profile ) {
		if ( ! is_array( $profile ) ) {
			return '';
		}
		$basis = $profile;
		unset( $basis['profile_digest'] );
		$encoded = wp_json_encode( self::canonicalize( $basis ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
	}

	private static function field( $field, $profile_updated ) {
		$keys = array( 'field_ref', 'field_code', 'data_class', 'value_digest', 'vault_locator_ref', 'source', 'assurance', 'state', 'purposes', 'retention_class', 'observed_at', 'valid_until', 'source_evidence_digest', 'supersedes_field_ref', 'data_boundary' );
		if ( ! self::exact_object( $field, $keys ) || ! self::ref( $field['field_ref'], 'profile_field' ) || ! self::digest( $field['value_digest'] ) || ! self::ref( $field['vault_locator_ref'], 'vault_item' ) || ! self::digest( $field['source_evidence_digest'] ) || ! self::nullable_ref( $field['supersedes_field_ref'], 'profile_field' ) ) {
			return self::error( 'field_identity_invalid', 'A profile field requires typed references, a value digest, and source evidence.' );
		}
		$expected_class = Tra_Vel_Traveler_Profile_Taxonomy::field_class( $field['field_code'] );
		if ( '' === $expected_class || $expected_class !== $field['data_class'] || ! in_array( $field['data_class'], Tra_Vel_Traveler_Profile_Taxonomy::DATA_CLASSES, true ) || ! in_array( $field['source'], Tra_Vel_Traveler_Profile_Taxonomy::SOURCES, true ) || ! in_array( $field['assurance'], Tra_Vel_Traveler_Profile_Taxonomy::ASSURANCE_LEVELS, true ) || ! in_array( $field['state'], Tra_Vel_Traveler_Profile_Taxonomy::FIELD_STATES, true ) || $field['retention_class'] !== Tra_Vel_Traveler_Profile_Taxonomy::retention_for_class( $field['data_class'] ) || ! self::enum_list( $field['purposes'], Tra_Vel_Traveler_Profile_Taxonomy::PURPOSES ) || ! self::field_boundary( $field['data_boundary'] ) ) {
			return self::error( 'field_policy_invalid', 'A profile field has an invalid class, source, assurance, purpose, retention, state, or data boundary.' );
		}
		$observed = self::utc_timestamp( $field['observed_at'] );
		$valid    = null === $field['valid_until'] ? null : self::utc_timestamp( $field['valid_until'] );
		if ( null === $observed || $observed > $profile_updated || ( null !== $field['valid_until'] && null === $valid ) || ( null !== $valid && $valid < $observed ) ) {
			return self::error( 'field_chronology_invalid', 'Profile-field evidence has an invalid observation or validity clock.' );
		}
		if ( in_array( $field['state'], array( 'current', 'expiring' ), true ) && null !== $valid && $valid <= $profile_updated ) {
			return self::error( 'field_freshness_invalid', 'A current or expiring profile field cannot be past its validity boundary.' );
		}
		if ( 'expired' === $field['state'] && ( null === $valid || $valid > $profile_updated ) ) {
			return self::error( 'field_expiry_invalid', 'An expired profile field requires a reached validity boundary.' );
		}
		$source_allowlist = array(
			'contact_verified'  => array( 'traveler', 'operator', 'supplier' ),
			'document_matched'  => array( 'document_capture', 'operator', 'supplier', 'imported_booking' ),
			'authority_verified'=> array( 'operator', 'government_rules_provider', 'supplier' ),
			'supplier_accepted' => array( 'supplier' ),
		);
		if ( isset( $source_allowlist[ $field['assurance'] ] ) && ! in_array( $field['source'], $source_allowlist[ $field['assurance'] ], true ) ) {
			return self::error( 'field_assurance_source_invalid', 'The profile-field assurance cannot be issued by this source.' );
		}
		return $field;
	}

	private static function data_boundary( $value ) {
		$keys = array( 'server_only', 'public_serialization_allowed', 'vault_pointers_only', 'raw_identity_data_stored', 'raw_contact_data_stored', 'raw_document_data_stored', 'raw_medical_data_stored', 'raw_loyalty_credentials_stored' );
		return self::exact_object( $value, $keys ) && true === $value['server_only'] && false === $value['public_serialization_allowed'] && true === $value['vault_pointers_only'] && false === $value['raw_identity_data_stored'] && false === $value['raw_contact_data_stored'] && false === $value['raw_document_data_stored'] && false === $value['raw_medical_data_stored'] && false === $value['raw_loyalty_credentials_stored'];
	}

	private static function field_boundary( $value ) {
		return self::exact_object( $value, array( 'server_only', 'raw_value_stored', 'vault_pointer_only' ) ) && true === $value['server_only'] && false === $value['raw_value_stored'] && true === $value['vault_pointer_only'];
	}

	private static function contains_sensitive_material( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				if ( preg_match( '/(?:^|_)(?:name|first_?name|last_?name|email|phone|mobile|address|passport|document_?number|date_?of_?birth|dob|nationality|diagnosis|medical_?note|allergy|member_?number|loyalty_?password|api_?key|secret|password|token|cvv|cvc|card_?number|pan)(?:$|_)/i', (string) $key ) || self::contains_sensitive_material( $child ) ) {
					return true;
				}
			}
			return false;
		}
		if ( ! is_string( $value ) ) {
			return false;
		}
		if ( preg_match( '/-----BEGIN [A-Z ]*PRIVATE KEY-----|\bBearer\s+[A-Za-z0-9._~+\/=\-]{8,}|\bsk-[A-Za-z0-9_-]{12,}/i', $value ) || preg_match( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value ) ) {
			return true;
		}
		$digits = preg_replace( '/\D+/', '', $value );
		return is_string( $digits ) && strlen( $digits ) >= 12 && strlen( $digits ) <= 19 && 1 === preg_match( '/^[0-9 ()+\-]+$/', $value );
	}

	private static function enum_list( $values, $allowed ) {
		if ( ! self::is_list( $values ) || ! $values || count( $values ) > count( $allowed ) || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! in_array( $value, $allowed, true ) ) {
				return false;
			}
		}
		return true;
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function is_list( $value ) {
		return is_array( $value ) && array_values( $value ) === $value;
	}

	private static function positive_int( $value ) {
		return is_int( $value ) && $value > 0 && $value <= 2147483647;
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function nullable_digest( $value ) {
		return null === $value || self::digest( $value );
	}

	private static function ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private static function nullable_ref( $value, $kind ) {
		return null === $value || self::ref( $value, $kind );
	}

	private static function utc_timestamp( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/', $value, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59 ) {
			return null;
		}
		try {
			return ( new DateTimeImmutable( $value ) )->getTimestamp();
		} catch ( Exception $error ) {
			return null;
		}
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_values( $value ) !== $value ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}
		return $value;
	}

	private static function error( $suffix, $message, $status = 400 ) {
		return new WP_Error( 'tra_vel_traveler_profile_' . $suffix, $message, array( 'status' => $status ) );
	}
}
