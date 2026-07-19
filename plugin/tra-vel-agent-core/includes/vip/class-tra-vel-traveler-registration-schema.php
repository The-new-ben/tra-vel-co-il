<?php
/**
 * Closed request, durable aggregate, and minimized response schemas for
 * progressive traveler registration.
 *
 * This slice indexes only requirement state, digests, and opaque references.
 * Profile/vault references are not dereferenced here and never prove a role,
 * evidence truth, guardian relationship, payment authority, or supplier scope.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Traveler_Registration_Schema {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_POINTERS      = 100;

	/**
	 * Validate and canonicalize one exact create request.
	 *
	 * @return array|WP_Error
	 */
	public static function create_input( $input ) {
		$keys = array( 'trip_ref', 'role_manifest_ref', 'profile_refs', 'vault_item_refs', 'party_flags', 'requirements', 'idempotency_key' );
		if ( ! self::exact_object( $input, $keys ) ) {
			return self::error( 'create_shape_invalid', 'The registration create request must use the exact privacy-safe shape.' );
		}
		$common = self::common_input( $input );
		if ( is_wp_error( $common ) ) {
			return $common;
		}
		return $common;
	}

	/**
	 * Validate and canonicalize one exact successor request.
	 *
	 * @return array|WP_Error
	 */
	public static function update_input( $input ) {
		$keys = array( 'expected_version', 'gate', 'reason', 'role_manifest_ref', 'profile_refs', 'vault_item_refs', 'party_flags', 'requirements', 'idempotency_key' );
		if ( ! self::exact_object( $input, $keys ) || ! is_int( $input['expected_version'] ) || $input['expected_version'] < 1 || $input['expected_version'] >= 2147483647 || ! in_array( $input['gate'], Tra_Vel_VIP_Taxonomy::REGISTRATION_GATES, true ) || ! in_array( $input['reason'], Tra_Vel_VIP_Taxonomy::REGISTRATION_TRANSITION_REASONS, true ) ) {
			return self::error( 'update_shape_invalid', 'The registration successor request is invalid.' );
		}
		$common = self::common_input( $input, false );
		if ( is_wp_error( $common ) ) {
			return $common;
		}
		$common['expected_version'] = $input['expected_version'];
		$common['gate']             = $input['gate'];
		$common['reason']           = $input['reason'];
		return $common;
	}

	/**
	 * Build and validate the private pointer-only aggregate.
	 *
	 * @return array|WP_Error
	 */
	public static function aggregate( $registration, $profile_refs, $vault_item_refs, $now ) {
		if ( ! class_exists( 'Tra_Vel_Traveler_Profile_Taxonomy' ) || ! class_exists( 'Tra_Vel_Traveler_Profile_Policy' ) || Tra_Vel_Traveler_Profile_Taxonomy::CONTRACT_VERSION !== self::CONTRACT_VERSION ) {
			return self::error( 'profile_policy_unavailable', 'The private traveler-profile pointer policy is unavailable.', 503 );
		}
		$registration = Tra_Vel_VIP_Policy::traveler_registration( $registration, $now );
		if ( is_wp_error( $registration ) ) {
			return $registration;
		}
		$profiles = self::reference_list( $profile_refs, 'profile' );
		$vaults   = self::reference_list( $vault_item_refs, 'vault_item' );
		if ( false === $profiles || false === $vaults ) {
			return self::error( 'binding_invalid', 'Registration bindings must contain unique opaque profile and vault-item references only.' );
		}
		$aggregate = array(
			'contract_version'    => self::CONTRACT_VERSION,
			'registration'        => $registration,
			'bindings'            => array(
				'role_manifest_ref' => $registration['role_manifest_ref'],
				'profile_refs'      => $profiles,
				'vault_item_refs'   => $vaults,
			),
			'authorization_effect'=> 'registration_only',
			'executable_scopes'   => array(),
			'side_effects'        => self::no_side_effects(),
			'data_boundary'       => self::storage_boundary(),
		);
		return self::validate_aggregate( $aggregate, $now );
	}

	/**
	 * Validate an aggregate read from durable storage.
	 *
	 * @return array|WP_Error
	 */
	public static function validate_aggregate( $aggregate, $now ) {
		if ( ! class_exists( 'Tra_Vel_Traveler_Profile_Taxonomy' ) || ! class_exists( 'Tra_Vel_Traveler_Profile_Policy' ) || Tra_Vel_Traveler_Profile_Taxonomy::CONTRACT_VERSION !== self::CONTRACT_VERSION ) {
			return self::error( 'profile_policy_unavailable', 'The private traveler-profile pointer policy is unavailable.', 503 );
		}
		$keys = array( 'contract_version', 'registration', 'bindings', 'authorization_effect', 'executable_scopes', 'side_effects', 'data_boundary' );
		if ( ! self::exact_object( $aggregate, $keys ) || self::CONTRACT_VERSION !== $aggregate['contract_version'] || 'registration_only' !== $aggregate['authorization_effect'] || array() !== $aggregate['executable_scopes'] || self::no_side_effects() !== $aggregate['side_effects'] || self::storage_boundary() !== $aggregate['data_boundary'] ) {
			return self::error( 'aggregate_shape_invalid', 'The durable registration aggregate is not the closed zero-authority contract.', 500 );
		}
		$registration = Tra_Vel_VIP_Policy::traveler_registration( $aggregate['registration'], $now );
		if ( is_wp_error( $registration ) ) {
			return self::error( 'aggregate_registration_invalid', 'The durable registration revision failed policy validation.', 500 );
		}
		$bindings = $aggregate['bindings'];
		if ( ! self::exact_object( $bindings, array( 'role_manifest_ref', 'profile_refs', 'vault_item_refs' ) ) || $bindings['role_manifest_ref'] !== $registration['role_manifest_ref'] || ! self::reference( $bindings['role_manifest_ref'], 'manifest' ) || false === self::reference_list( $bindings['profile_refs'], 'profile' ) || false === self::reference_list( $bindings['vault_item_refs'], 'vault_item' ) ) {
			return self::error( 'aggregate_binding_invalid', 'The durable registration bindings are invalid.', 500 );
		}
		return $aggregate;
	}

	/**
	 * Customer-safe projection. Digests and actual private pointers stay server-only.
	 */
	public static function public_projection( $aggregate, $transition_count ) {
		$valid = self::validate_aggregate( $aggregate, gmdate( 'Y-m-d\TH:i:s\Z' ) );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$registration = $valid['registration'];
		$requirements = array();
		foreach ( $registration['requirements'] as $requirement ) {
			$requirements[] = array(
				'code'              => $requirement['code'],
				'status'            => $requirement['status'],
				'evidence_recorded' => 'verified' === $requirement['status'],
				'verified_at'       => $requirement['verified_at'],
			);
		}
		$gate_index = array_search( $registration['gate'], Tra_Vel_VIP_Taxonomy::REGISTRATION_GATES, true );
		$next_gate  = false !== $gate_index && isset( Tra_Vel_VIP_Taxonomy::REGISTRATION_GATES[ $gate_index + 1 ] ) ? Tra_Vel_VIP_Taxonomy::REGISTRATION_GATES[ $gate_index + 1 ] : null;
		return array(
			'contract_version' => self::CONTRACT_VERSION,
			'registration_ref' => $registration['registration_ref'],
			'trip_ref'         => $registration['trip_ref'],
			'gate'             => $registration['gate'],
			'party_flags'      => $registration['party_flags'],
			'requirements'     => $requirements,
			'version'          => $registration['version'],
			'ownership'        => 'account',
			'created_at'       => $registration['created_at'],
			'updated_at'       => $registration['updated_at'],
			'bindings'         => array(
				'role_manifest_bound' => true,
				'profile_ref_count'    => count( $valid['bindings']['profile_refs'] ),
				'vault_item_ref_count' => count( $valid['bindings']['vault_item_refs'] ),
			),
			'readiness'        => array(
				'declared_gate_policy_ready' => true,
				'next_gate'                  => $next_gate,
				'transition_count'           => max( 0, (int) $transition_count ),
			),
			'authorization'    => array(
				'effect'                                      => 'registration_only',
				'executable_scopes'                           => array(),
				'account_ownership_grants_trip_role_authority'=> false,
			),
			'side_effects'     => self::no_side_effects(),
		);
	}

	public static function canonical_digest( $value ) {
		$encoded = wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
	}

	public static function no_side_effects() {
		return array(
			'supplier_action_started'     => false,
			'payment_action_started'      => false,
			'booking_action_started'      => false,
			'cancellation_action_started' => false,
			'refund_action_started'       => false,
		);
	}

	public static function storage_boundary() {
		return array(
			'opaque_references_only'      => true,
			'raw_identity_data_stored'    => false,
			'raw_contact_data_stored'     => false,
			'raw_document_data_stored'    => false,
			'raw_payment_data_stored'     => false,
			'raw_medical_data_stored'     => false,
			'raw_provider_payload_stored' => false,
			'bearer_secret_stored'        => false,
		);
	}

	private static function common_input( $input, $has_trip = true ) {
		if ( $has_trip && ! self::reference( $input['trip_ref'], 'trip' ) ) {
			return self::error( 'trip_ref_invalid', 'A valid opaque trip reference is required.' );
		}
		if ( ! self::reference( $input['role_manifest_ref'], 'manifest' ) ) {
			return self::error( 'role_manifest_ref_invalid', 'A valid opaque role-manifest reference is required.' );
		}
		$profiles    = self::reference_list( $input['profile_refs'], 'profile' );
		$vault_items = self::reference_list( $input['vault_item_refs'], 'vault_item' );
		$flags       = self::party_flags( $input['party_flags'] );
		$requirements= self::requirements( $input['requirements'] );
		$key         = self::idempotency_key( $input['idempotency_key'] );
		if ( false === $profiles || false === $vault_items || false === $flags || false === $requirements || '' === $key ) {
			return self::error( 'privacy_projection_invalid', 'Registration accepts only bounded requirement state, evidence digests, and opaque role/profile/vault references.' );
		}
		$result = array(
			'role_manifest_ref' => $input['role_manifest_ref'],
			'profile_refs'      => $profiles,
			'vault_item_refs'   => $vault_items,
			'party_flags'       => $flags,
			'requirements'      => $requirements,
			'idempotency_key'   => $key,
		);
		if ( $has_trip ) {
			$result['trip_ref'] = $input['trip_ref'];
		}
		return $result;
	}

	private static function party_flags( $flags ) {
		$keys = array( 'minor_present', 'dependent_adult_present', 'accessibility_required' );
		if ( ! self::exact_object( $flags, $keys ) ) {
			return false;
		}
		foreach ( $keys as $key ) {
			if ( ! is_bool( $flags[ $key ] ) ) {
				return false;
			}
		}
		return $flags;
	}

	private static function requirements( $requirements ) {
		if ( ! self::is_list( $requirements ) || count( $requirements ) > 32 ) {
			return false;
		}
		$by_code = array();
		foreach ( $requirements as $requirement ) {
			if ( ! self::exact_object( $requirement, array( 'code', 'status', 'evidence_digest', 'verified_at' ) ) || ! in_array( $requirement['code'], Tra_Vel_VIP_Taxonomy::REGISTRATION_REQUIREMENTS, true ) || isset( $by_code[ $requirement['code'] ] ) || ! in_array( $requirement['status'], array( 'self_asserted', 'verified', 'not_applicable' ), true ) || ( null !== $requirement['evidence_digest'] && ! self::digest( $requirement['evidence_digest'] ) ) || ( null !== $requirement['verified_at'] && ! self::utc( $requirement['verified_at'] ) ) ) {
				return false;
			}
			$by_code[ $requirement['code'] ] = $requirement;
		}
		$ordered = array();
		foreach ( Tra_Vel_VIP_Taxonomy::REGISTRATION_REQUIREMENTS as $code ) {
			if ( isset( $by_code[ $code ] ) ) {
				$ordered[] = $by_code[ $code ];
			}
		}
		return $ordered;
	}

	private static function reference_list( $values, $kind ) {
		if ( ! self::is_list( $values ) || count( $values ) > self::MAX_POINTERS || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! self::reference( $value, $kind ) ) {
				return false;
			}
		}
		sort( $values, SORT_STRING );
		return $values;
	}

	private static function reference( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private static function idempotency_key( $value ) {
		return is_string( $value ) && strlen( $value ) >= 16 && strlen( $value ) <= 100 && 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function utc( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/', $value, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59 ) {
			return false;
		}
		return true;
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function is_list( $value ) {
		return is_array( $value ) && array_values( $value ) === $value;
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_values( $value ) !== $value ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::canonicalize( $child );
		}
		return $value;
	}

	private static function error( $suffix, $message, $status = 400 ) {
		return new WP_Error( 'tra_vel_traveler_registration_' . $suffix, $message, array( 'status' => $status ) );
	}
}
