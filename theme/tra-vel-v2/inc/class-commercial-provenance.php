<?php
/**
 * Fail-closed validation for supplier responses that want to be labelled live.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_V2_Commercial_Provenance {
	/**
	 * Validate the evidence required before a supplier payload can be called live.
	 *
	 * @param array  $result Adapter payload.
	 * @param string $adapter_id Registered adapter ID.
	 * @param string $collection_key Result collection key.
	 * @param array  $price_path Nested numeric price path within each result.
	 * @param string $commerce_key Booking or purchase object key.
	 * @param string $capability_key Explicit bookable or purchasable flag.
	 * @param string $expected_currency Requested currency.
	 * @param string $expected_price_scope Required price scope.
	 * @param array  $options Additional regulated-product requirements.
	 * @return true|WP_Error
	 */
	public static function validate( $result, $adapter_id, $collection_key, $price_path, $commerce_key, $capability_key, $expected_currency, $expected_price_scope, $options = array() ) {
		if ( ! is_array( $result ) || 'live' !== ( isset( $result['data_mode'] ) ? $result['data_mode'] : '' ) ) {
			return self::error();
		}
		if ( empty( $result['contract_version'] ) || 1 !== preg_match( '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', (string) $result['contract_version'] ) ) {
			return self::error();
		}

		$status = isset( $result['provider_status'] ) && is_array( $result['provider_status'] ) ? $result['provider_status'] : array();
		$provider_id = sanitize_key(
			isset( $status['provider_id'] ) ? $status['provider_id'] : ( isset( $status['adapter'] ) ? $status['adapter'] : '' )
		);
		if ( true !== ( isset( $status['connected'] ) ? $status['connected'] : false ) || ! $provider_id || sanitize_key( $adapter_id ) !== $provider_id || 'demo' === $provider_id ) {
			return self::error();
		}

		$now          = time();
		$retrieved_at = self::timestamp( isset( $status['retrieved_at'] ) ? $status['retrieved_at'] : '' );
		$fresh_until  = self::timestamp( isset( $status['fresh_until'] ) ? $status['fresh_until'] : '' );
		$available_at = self::timestamp( isset( $status['availability_checked_at'] ) ? $status['availability_checked_at'] : '' );
		if ( false === $retrieved_at || false === $fresh_until || false === $available_at ) {
			return self::error();
		}
		if ( $retrieved_at < $now - 3600 || $retrieved_at > $now + 300 || $available_at < $now - 3600 || $available_at > $now + 300 || $fresh_until < $now || $fresh_until < $retrieved_at ) {
			return self::error();
		}

		$currency = strtoupper( isset( $result['currency'] ) ? (string) $result['currency'] : '' );
		if ( 1 !== preg_match( '/^[A-Z]{3}$/', $currency ) || $currency !== strtoupper( (string) $expected_currency ) || $currency !== strtoupper( isset( $status['currency'] ) ? (string) $status['currency'] : '' ) ) {
			return self::error();
		}
		if ( $expected_price_scope !== ( isset( $status['price_scope'] ) ? $status['price_scope'] : '' ) ) {
			return self::error();
		}

		$items = isset( $result[ $collection_key ] ) && is_array( $result[ $collection_key ] ) ? $result[ $collection_key ] : array();
		if ( empty( $items ) ) {
			return self::error();
		}
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				return self::error();
			}
			$price = self::nested_value( $item, $price_path );
			if ( ! is_numeric( $price ) || (float) $price <= 0 ) {
				return self::error();
			}
			$commerce = isset( $item[ $commerce_key ] ) && is_array( $item[ $commerce_key ] ) ? $item[ $commerce_key ] : array();
			$item_provider = sanitize_key( isset( $commerce['provider'] ) ? $commerce['provider'] : '' );
			if ( $item_provider !== $provider_id || true !== ( isset( $commerce[ $capability_key ] ) ? $commerce[ $capability_key ] : false ) ) {
				return self::error();
			}
		}

		if ( ! empty( $options['regulated'] ) ) {
			if ( true !== ( isset( $status['licensed_provider'] ) ? $status['licensed_provider'] : false ) || empty( $status['license_reference'] ) || true !== ( isset( $result['regulated_sale_ready'] ) ? $result['regulated_sale_ready'] : false ) ) {
				return self::error();
			}
		}

		return true;
	}

	private static function nested_value( $item, $path ) {
		$value = $item;
		foreach ( $path as $key ) {
			if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
				return null;
			}
			$value = $value[ $key ];
		}
		return $value;
	}

	private static function timestamp( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return false;
		}
		$timestamp = strtotime( $value );
		return false === $timestamp ? false : $timestamp;
	}

	private static function error() {
		return new WP_Error( 'tra_vel_live_provenance_invalid', 'Supplier response did not include sufficient current commercial provenance.' );
	}
}
