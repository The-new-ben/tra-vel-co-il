<?php
/**
 * Closed validation and canonical integrity helpers for Commerce Core.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Policy {
	const CONTRACT_VERSION = '1.0.0';

	/**
	 * Validate and normalize a provider descriptor without credentials.
	 *
	 * @return array|WP_Error
	 */
	public static function provider_descriptor( $descriptor ) {
		$required = array( 'contract_version', 'provider_id', 'display_name', 'adapter_version', 'environment', 'relationship', 'verticals', 'capabilities', 'priority', 'readiness', 'commercial_truth', 'allowed_hosts', 'jurisdictions', 'licence', 'settlement' );
		if ( ! self::exact_object( $descriptor, $required ) || self::CONTRACT_VERSION !== $descriptor['contract_version'] ) {
			return self::error( 'provider_shape_invalid', 'The commerce provider descriptor is not a closed supported contract.' );
		}

		$provider_id = sanitize_key( (string) $descriptor['provider_id'] );
		$display_name = sanitize_text_field( (string) $descriptor['display_name'] );
		$environment = sanitize_key( (string) $descriptor['environment'] );
		$relationship = sanitize_key( (string) $descriptor['relationship'] );
		if ( ! preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $provider_id ) || strlen( $display_name ) < 3 || strlen( $display_name ) > 80 || ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', (string) $descriptor['adapter_version'] ) || ! in_array( $environment, array( 'sandbox', 'live' ), true ) || ! in_array( $relationship, array( 'owned', 'direct', 'affiliate' ), true ) || ! is_int( $descriptor['priority'] ) || $descriptor['priority'] < 0 || $descriptor['priority'] > 1000 ) {
			return self::error( 'provider_identity_invalid', 'The commerce provider identity or priority is invalid.' );
		}

		$verticals    = Tra_Vel_Commerce_Taxonomy::verticals( $descriptor['verticals'] );
		$capabilities = Tra_Vel_Commerce_Taxonomy::capabilities( $descriptor['capabilities'] );
		if ( is_wp_error( $verticals ) || is_wp_error( $capabilities ) || ! in_array( 'search', $capabilities, true ) ) {
			return self::error( 'provider_capabilities_invalid', 'A commerce provider must expose canonical verticals and search capability.' );
		}
		$required_capabilities = array(
			'payment_capture' => array( 'payment_authorize' ),
			'payment_void'    => array( 'payment_authorize' ),
			'payment_refund'  => array( 'payment_capture' ),
		);
		foreach ( $required_capabilities as $capability => $dependencies ) {
			if ( in_array( $capability, $capabilities, true ) && array_diff( $dependencies, $capabilities ) ) {
				return self::error( 'provider_capabilities_invalid', 'A commerce payment capability is missing a required predecessor capability.' );
			}
		}
		if ( 'affiliate' === $relationship && ( ! in_array( 'report_conversion', $capabilities, true ) || ! in_array( 'settlement_reconcile', $capabilities, true ) ) ) {
			return self::error( 'provider_capabilities_invalid', 'An affiliate commerce provider requires conversion reporting and settlement reconciliation capabilities.' );
		}

		$readiness = $descriptor['readiness'];
		if ( ! self::exact_object( $readiness, array( 'configuration', 'status' ) ) || ! in_array( $readiness['configuration'], array( 'seeded', 'configured', 'missing_credentials', 'disabled' ), true ) || ! in_array( $readiness['status'], array( 'ready', 'degraded', 'offline' ), true ) ) {
			return self::error( 'provider_readiness_invalid', 'The commerce provider readiness block is invalid.' );
		}

		$truth = $descriptor['commercial_truth'];
		if ( ! self::exact_object( $truth, array( 'simulated', 'real_charge', 'real_booking' ) ) || ! is_bool( $truth['simulated'] ) || ! is_bool( $truth['real_charge'] ) || ! is_bool( $truth['real_booking'] ) ) {
			return self::error( 'provider_truth_invalid', 'The commerce provider truth block is invalid.' );
		}
		if ( 'sandbox' === $environment && ( true !== $truth['simulated'] || $truth['real_charge'] || $truth['real_booking'] ) ) {
			return self::error( 'provider_truth_invalid', 'Sandbox providers cannot claim a real charge or booking.' );
		}

		$hosts = self::host_list( $descriptor['allowed_hosts'] );
		$jurisdictions = self::jurisdiction_list( $descriptor['jurisdictions'] );
		if ( is_wp_error( $hosts ) || is_wp_error( $jurisdictions ) || ( 'sandbox' === $environment && $hosts ) ) {
			return self::error( 'provider_boundary_invalid', 'The commerce provider host or jurisdiction boundary is invalid.' );
		}

		$licence = $descriptor['licence'];
		if ( ! self::exact_object( $licence, array( 'status', 'reference_digest' ) ) || ! in_array( $licence['status'], array( 'sandbox_only', 'not_required', 'verified', 'not_configured' ), true ) || ( null !== $licence['reference_digest'] && ! self::is_digest( $licence['reference_digest'] ) ) ) {
			return self::error( 'provider_licence_invalid', 'The commerce provider licence boundary is invalid.' );
		}
		if ( in_array( 'insurance', $verticals, true ) && 'live' === $environment && ( 'verified' !== $licence['status'] || ! self::is_digest( $licence['reference_digest'] ) ) ) {
			return self::error( 'provider_licence_invalid', 'A live insurance provider requires verified licence evidence.' );
		}

		$settlement = $descriptor['settlement'];
		$settlement_models = array( 'owned', 'commission', 'affiliate', 'net_rate' );
		if ( ! self::exact_object( $settlement, array( 'model', 'commission_bps', 'currency', 'payout_lag_days' ) ) || ! in_array( $settlement['model'], $settlement_models, true ) || ! is_int( $settlement['commission_bps'] ) || $settlement['commission_bps'] < 0 || $settlement['commission_bps'] > 10000 || '' === Tra_Vel_Commerce_Money::currency( $settlement['currency'] ) || ! is_int( $settlement['payout_lag_days'] ) || $settlement['payout_lag_days'] < 0 || $settlement['payout_lag_days'] > 365 ) {
			return self::error( 'provider_settlement_invalid', 'The commerce provider settlement descriptor is invalid.' );
		}
		if ( 'affiliate' === $relationship && 'affiliate' !== $settlement['model'] ) {
			return self::error( 'provider_settlement_invalid', 'An affiliate provider requires an affiliate settlement model.' );
		}

		return array(
			'contract_version' => self::CONTRACT_VERSION,
			'provider_id'      => $provider_id,
			'display_name'     => $display_name,
			'adapter_version'  => (string) $descriptor['adapter_version'],
			'environment'      => $environment,
			'relationship'     => $relationship,
			'verticals'        => $verticals,
			'capabilities'     => $capabilities,
			'priority'         => $descriptor['priority'],
			'readiness'        => $readiness,
			'commercial_truth' => $truth,
			'allowed_hosts'    => $hosts,
			'jurisdictions'    => $jurisdictions,
			'licence'          => $licence,
			'settlement'       => array_merge( $settlement, array( 'currency' => Tra_Vel_Commerce_Money::currency( $settlement['currency'] ) ) ),
		);
	}

	public static function canonical_digest( $value ) {
		$value = self::canonicalize( $value );
		return hash( 'sha256', wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Parse one strict RFC3339 instant and return canonical UTC, or null.
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

	private static function host_list( $hosts ) {
		if ( ! is_array( $hosts ) || array_values( $hosts ) !== $hosts ) {
			return self::error( 'provider_hosts_invalid', 'Provider hosts must be a closed list.' );
		}
		$clean = array();
		foreach ( $hosts as $host ) {
			$host = strtolower( trim( (string) $host ) );
			if ( ! preg_match( '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host ) ) {
				return self::error( 'provider_hosts_invalid', 'A provider host is invalid.' );
			}
			$clean[ $host ] = true;
		}
		$clean = array_keys( $clean );
		sort( $clean, SORT_STRING );
		return $clean;
	}

	private static function jurisdiction_list( $values ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ! $values ) {
			return self::error( 'provider_jurisdictions_invalid', 'At least one provider jurisdiction is required.' );
		}
		$clean = array();
		foreach ( $values as $value ) {
			$value = 'global' === strtolower( (string) $value ) ? 'global' : strtoupper( (string) $value );
			if ( 'global' !== $value && ! preg_match( '/^[A-Z]{2}$/', $value ) ) {
				return self::error( 'provider_jurisdictions_invalid', 'A provider jurisdiction is invalid.' );
			}
			$clean[ $value ] = true;
		}
		$clean = array_keys( $clean );
		sort( $clean, SORT_STRING );
		return $clean;
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function is_digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$is_list = array_values( $value ) === $value;
		if ( ! $is_list ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}
		return $value;
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_commerce_' . $suffix, $message, array( 'status' => 400 ) );
	}
}
