<?php
/**
 * Fail-closed policy for scanner-safe VIP capability-session exchange.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_VIP_Capability_Session_Policy {
	const CONTRACT_VERSION    = '1.0.0';
	const SESSION_TTL_SECONDS = 1800;
	const MAX_ROTATION_GENERATION = 2147483647;

	const DISCLOSURE_CLASSES = array(
		'public_emergency_guidance',
		'trip_redacted',
		'case_progress',
		'ordinary_evidence_metadata',
		'safe_contact_location',
	);

	/**
	 * Validate the private server-side issuance request. No public route calls it.
	 *
	 * @return array|WP_Error
	 */
	public static function issuance_request( $request ) {
		$keys = array( 'trip_ref', 'case_ref', 'account_ref', 'issue_reason', 'channel', 'allowed_scopes', 'disclosure_classes', 'lifetime_seconds', 'rotation_generation' );
		if ( ! self::exact_object( $request, $keys ) || ! self::privacy_safe( $request ) ) {
			return self::error( 'issuance_shape_invalid', 'The capability issuance request is not a closed private contract.', 400 );
		}
		if ( ! self::ref( $request['trip_ref'], 'trip' ) || ( null !== $request['case_ref'] && ! self::ref( $request['case_ref'], 'case' ) ) || ( null !== $request['account_ref'] && ! self::ref( $request['account_ref'], 'account' ) ) ) {
			return self::error( 'issuance_binding_invalid', 'The capability issuance binding is invalid.', 400 );
		}
		if ( ! in_array( $request['issue_reason'], array( 'trip_access', 'incident_intake', 'case_progress', 'lost_phone_recovery', 'operator_contact' ), true ) || ! in_array( $request['channel'], array( 'email', 'sms', 'whatsapp', 'manual_recovery' ), true ) ) {
			return self::error( 'issuance_purpose_invalid', 'The capability issuance purpose is invalid.', 400 );
		}
		if ( ! self::enum_list( $request['allowed_scopes'], Tra_Vel_VIP_Taxonomy::LOW_RISK_CAPABILITY_SCOPES, true ) || array_intersect( $request['allowed_scopes'], Tra_Vel_VIP_Taxonomy::HIGH_IMPACT_SCOPES ) ) {
			return self::error( 'issuance_scope_invalid', 'Capability links may contain only explicitly low-risk scopes.', 403 );
		}
		if ( ! self::enum_list( $request['disclosure_classes'], self::DISCLOSURE_CLASSES, true ) || ! is_int( $request['lifetime_seconds'] ) || $request['lifetime_seconds'] < 60 || $request['lifetime_seconds'] > Tra_Vel_VIP_Policy::MAX_CAPABILITY_LIFETIME_SECONDS || ! is_int( $request['rotation_generation'] ) || $request['rotation_generation'] < 1 || $request['rotation_generation'] > self::MAX_ROTATION_GENERATION ) {
			return self::error( 'issuance_security_invalid', 'The capability lifetime, rotation, or disclosure boundary is invalid.', 400 );
		}
		return $request;
	}

	/**
	 * Validate the digest-only private session projection.
	 *
	 * @return array|WP_Error
	 */
	public static function session( $session, $now = null ) {
		$keys = array( 'contract_version', 'session_ref', 'session_digest', 'capability_ref', 'capability_digest', 'trip_ref', 'case_ref', 'account_ref', 'allowed_scopes', 'disclosure_classes', 'rotation_generation', 'state', 'created_at', 'expires_at', 'revoked_at', 'authorization_effect', 'data_boundary' );
		if ( ! self::exact_object( $session, $keys ) || self::CONTRACT_VERSION !== (string) $session['contract_version'] || ! self::privacy_safe( $session ) ) {
			return self::error( 'session_shape_invalid', 'The capability session is not a closed privacy-safe contract.', 400 );
		}
		if ( ! self::ref( $session['session_ref'], 'capability_session' ) || ! self::digest( $session['session_digest'] ) || ! self::ref( $session['capability_ref'], 'capability' ) || ! self::digest( $session['capability_digest'] ) || ! self::ref( $session['trip_ref'], 'trip' ) || ( null !== $session['case_ref'] && ! self::ref( $session['case_ref'], 'case' ) ) || ( null !== $session['account_ref'] && ! self::ref( $session['account_ref'], 'account' ) ) ) {
			return self::error( 'session_binding_invalid', 'The capability session binding is invalid.', 400 );
		}
		if ( ! self::enum_list( $session['allowed_scopes'], Tra_Vel_VIP_Taxonomy::LOW_RISK_CAPABILITY_SCOPES, true ) || array_intersect( $session['allowed_scopes'], Tra_Vel_VIP_Taxonomy::HIGH_IMPACT_SCOPES ) || ! self::enum_list( $session['disclosure_classes'], self::DISCLOSURE_CLASSES, true ) ) {
			return self::error( 'session_scope_invalid', 'The capability session exceeds its low-risk disclosure boundary.', 403 );
		}
		if ( ! is_int( $session['rotation_generation'] ) || $session['rotation_generation'] < 1 || $session['rotation_generation'] > self::MAX_ROTATION_GENERATION || ! in_array( $session['state'], array( 'active', 'revoked', 'expired' ), true ) || 'low_risk_capability_only' !== $session['authorization_effect'] || ! self::utc( $session['created_at'] ) || ! self::utc( $session['expires_at'] ) || $session['expires_at'] <= $session['created_at'] || strtotime( $session['expires_at'] ) - strtotime( $session['created_at'] ) > self::SESSION_TTL_SECONDS || ! self::data_boundary_valid( $session['data_boundary'] ) ) {
			return self::error( 'session_security_invalid', 'The capability session security boundary is invalid.', 400 );
		}
		if ( ( 'revoked' === $session['state'] ) !== ( null !== $session['revoked_at'] ) || ( null !== $session['revoked_at'] && ( ! self::utc( $session['revoked_at'] ) || $session['revoked_at'] < $session['created_at'] ) ) ) {
			return self::error( 'session_revocation_invalid', 'The capability session revocation clock is invalid.', 400 );
		}
		if ( null !== $now ) {
			if ( ! self::utc( $now ) ) {
				return self::error( 'session_clock_invalid', 'The capability session clock is invalid.', 400 );
			}
			if ( 'active' === $session['state'] && $session['expires_at'] <= $now ) {
				return self::error( 'session_expired', 'The capability session is unavailable.', 401 );
			}
		}
		return $session;
	}

	/**
	 * Constant scanner response. Never consult a grant, cookie, trip, or account.
	 */
	public static function scanner_probe() {
		return array(
			'contract_version'          => self::CONTRACT_VERSION,
			'exchange_required'         => true,
			'mutation_performed'        => false,
			'capability_state_disclosed'=> false,
			'trip_state_disclosed'      => false,
			'session_state_disclosed'   => false,
			'next_operation'            => 'same_origin_post_exchange',
		);
	}

	/**
	 * Public owner-scoped projection. Private bindings and all digests stay server-side.
	 *
	 * @return array|WP_Error
	 */
	public static function public_session( $session, $now = null ) {
		$now = null === $now ? gmdate( 'Y-m-d\TH:i:s\Z' ) : $now;
		$valid = self::session( $session, $now );
		if ( is_wp_error( $valid ) || 'active' !== $session['state'] ) {
			return self::error( 'session_unavailable', 'The capability session is unavailable.', 404 );
		}
		return array(
			'contract_version'       => self::CONTRACT_VERSION,
			'session_ref'            => (string) $session['session_ref'],
			'state'                  => 'active',
			'allowed_scopes'         => array_values( $session['allowed_scopes'] ),
			'disclosure_classes'     => array_values( $session['disclosure_classes'] ),
			'expires_at'             => (string) $session['expires_at'],
			'capability_binding'     => 'current_private_browser',
			'authorization_effect'   => 'low_risk_capability_only',
			'denied_high_impact_scopes' => array_values( Tra_Vel_VIP_Taxonomy::HIGH_IMPACT_SCOPES ),
			'supplier_action_started'=> false,
			'payment_action_started' => false,
		);
	}

	public static function canonical_digest( $value ) {
		$canonical = self::canonicalize( $value );
		return hash( 'sha256', (string) wp_json_encode( $canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	public static function safe_data_boundary() {
		return array(
			'raw_identity_data_exposed' => false,
			'raw_payment_data_exposed' => false,
			'raw_medical_data_exposed' => false,
			'raw_provider_payload_exposed' => false,
			'bearer_secret_exposed' => false,
		);
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function enum_list( $values, $allowed, $required ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( $required && ! $values ) || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! in_array( $value, $allowed, true ) ) {
				return false;
			}
		}
		return true;
	}

	private static function utc( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value ) ) {
			return false;
		}
		$time = strtotime( $value );
		return false !== $time && gmdate( 'Y-m-d\TH:i:s\Z', $time ) === $value;
	}

	private static function data_boundary_valid( $value ) {
		return self::safe_data_boundary() === $value;
	}

	private static function privacy_safe( $value ) {
		$forbidden = '/^(?:bearer_token|token|secret|password|cvv|cvc|card_number|card_pan|pan|passport|passport_number|identity_number|diagnosis|medical_history|medical_narrative|raw_provider_payload|raw_payment_data|payment_token|activation_code|iccid)$/i';
		if ( ! is_array( $value ) ) {
			return true;
		}
		foreach ( $value as $key => $child ) {
			if ( is_string( $key ) && preg_match( $forbidden, $key ) ) {
				return false;
			}
			if ( ! self::privacy_safe( $child ) ) {
				return false;
			}
		}
		return true;
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

	private static function error( $suffix, $message, $status ) {
		return new WP_Error( 'tra_vel_vip_capability_' . $suffix, $message, array( 'status' => (int) $status ) );
	}
}
