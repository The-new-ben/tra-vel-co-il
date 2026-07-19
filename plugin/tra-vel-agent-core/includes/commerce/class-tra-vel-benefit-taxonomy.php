<?php
/**
 * Closed vocabulary for the non-transactional loyalty and benefits foundation.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Benefit_Taxonomy {
	const CONTRACT_VERSION = '1.0.0';

	const DECISION_STATES = array(
		'eligible_verified',
		'likely_customer_asserted',
		'unknown_requires_action',
		'ineligible_verified',
	);

	const INTEGRATION_STATES = array(
		'not_configured',
		'source_catalogued',
		'manual_planning_only',
		'announced_not_operational',
		'provider_authorization_required',
	);

	const CONSENT_SCOPES = array(
		'read_balance',
		'refresh_balance',
		'redeem',
		'disconnect',
	);

	const CONNECTION_MODES = array(
		'provider_oauth',
		'licensed_open_finance',
		'provider_deep_link',
		'manual_balance',
		'statement_evidence',
	);

	const CONNECTION_STATES = array(
		'not_connected',
		'authorization_started',
		'connected_read_only',
		'refresh_required',
		'connected_current',
		'redemption_step_up_required',
		'redemption_authorized',
		'disconnected',
	);

	const ASSURANCE_LEVELS = array(
		'provider_verified',
		'authorized_partner_verified',
		'official_terms_verified',
		'customer_evidence',
		'customer_asserted',
		'unknown',
	);

	const SOURCE_AUTHORITIES = array(
		'signed_partner_api',
		'provider_authorized_api',
		'official_rules',
		'official_product_page',
		'provider_support_confirmation',
		'customer_evidence',
		'customer_assertion',
	);

	const UNIT_TYPES = array(
		'points',
		'program_value_minor',
		'status_currency',
		'cashback_minor',
	);

	const BENEFIT_TYPES = array(
		'points_redemption',
		'cash_plus_points',
		'card_linked_discount',
		'cashback_future',
		'conversion',
		'earn_for_later',
		'activation_offer',
	);

	const REDEMPTION_STATES = array(
		'queued',
		'step_up_required',
		'authorized',
		'submitted',
		'operation_uncertain',
		'provider_reconciliation_required',
		'succeeded',
		'failed',
		'reversed',
	);

	const RECONCILIATION_STATES = array(
		'not_required',
		'pending',
		'required',
		'matched',
		'mismatch',
		'reversed',
	);

	const IDENTIFIER_PATTERNS = array(
		'program'              => '/^program_[a-z0-9][a-z0-9_]{2,55}$/',
		'credential_product'   => '/^credential_[a-z0-9][a-z0-9_]{2,51}$/',
		'campaign'             => '/^campaign_[a-z0-9][a-z0-9_]{2,53}$/',
		'connection'           => '/^connection_[a-z0-9][a-z0-9_]{2,51}$/',
		'balance_snapshot'     => '/^balance_[a-z0-9][a-z0-9_]{2,54}$/',
		'benefit_quote'        => '/^benefit_quote_[a-z0-9][a-z0-9_]{2,47}$/',
		'redemption_operation' => '/^redemption_[a-z0-9][a-z0-9_]{2,51}$/',
		'revocation_route'     => '/^revocation_[a-z0-9][a-z0-9_]{2,51}$/',
		'generic'              => '/^[a-z][a-z0-9_]{2,63}$/',
		'unit'                 => '/^[a-z][a-z0-9_]{1,31}$/',
	);

	/**
	 * Return the exact supported value or an empty string. Values are never
	 * silently lowercased or trimmed because identifiers are contract keys.
	 */
	public static function enum_value( $value, $allowed ) {
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : '';
	}

	public static function decision_state( $value ) {
		return self::enum_value( $value, self::DECISION_STATES );
	}

	public static function integration_state( $value ) {
		return self::enum_value( $value, self::INTEGRATION_STATES );
	}

	public static function identifier( $value, $kind = 'generic' ) {
		if ( ! is_string( $value ) || ! isset( self::IDENTIFIER_PATTERNS[ $kind ] ) ) {
			return '';
		}
		return 1 === preg_match( self::IDENTIFIER_PATTERNS[ $kind ], $value ) ? $value : '';
	}

	public static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
	}

	public static function currency( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[A-Z]{3}$/', $value ) ? $value : '';
	}

	public static function country_scope( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^(?:[A-Z]{2}|global)$/', $value ) ? $value : '';
	}

	public static function nonnegative_integer( $value ) {
		return is_int( $value ) && $value >= 0;
	}

	/**
	 * Validate an exact, duplicate-free scope list and return it sorted.
	 *
	 * @return array|WP_Error
	 */
	public static function consent_scopes( $values ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ! $values || count( $values ) > count( self::CONSENT_SCOPES ) ) {
			return new WP_Error( 'tra_vel_benefit_consent_scopes_invalid', 'At least one exact benefit consent scope is required.', array( 'status' => 400 ) );
		}

		$unique = array();
		foreach ( $values as $value ) {
			if ( '' === self::enum_value( $value, self::CONSENT_SCOPES ) || isset( $unique[ $value ] ) ) {
				return new WP_Error( 'tra_vel_benefit_consent_scopes_invalid', 'Benefit consent scopes must be unique and supported.', array( 'status' => 400 ) );
			}
			$unique[ $value ] = true;
		}

		$scopes = array_keys( $unique );
		sort( $scopes, SORT_STRING );
		return $scopes;
	}

	/**
	 * Parse strict RFC3339 and return canonical UTC, or null.
	 */
	public static function utc_datetime( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,6}))?(Z|[+-](\d{2}):(\d{2}))$/', $value, $parts ) ) {
			return null;
		}
		if ( ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59 || ( isset( $parts[9] ) && '' !== $parts[9] && (int) $parts[9] > 23 ) || ( isset( $parts[10] ) && '' !== $parts[10] && (int) $parts[10] > 59 ) ) {
			return null;
		}
		try {
			$date = new DateTimeImmutable( $value );
		} catch ( Exception $error ) {
			return null;
		}
		return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
	}
}
