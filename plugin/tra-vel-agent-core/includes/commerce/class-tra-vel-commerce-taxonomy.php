<?php
/**
 * Canonical product and operation vocabulary for Commerce Core.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Taxonomy {
	const VERTICALS = array(
		'flight',
		'accommodation',
		'package',
		'transfer',
		'activity',
		'dining',
		'insurance',
		'connectivity',
		'equipment',
	);

	const CAPABILITIES = array(
		'search',
		'revalidate',
		'reserve',
		'confirm',
		'fulfill',
		'change',
		'cancel',
		'refund',
		'payment_authorize',
		'payment_capture',
		'payment_void',
		'payment_refund',
		'webhook',
		'reconcile',
		'report_conversion',
		'settlement_reconcile',
	);

	const CHECKOUT_STATES = array( 'draft', 'quoted', 'awaiting_approval', 'ready', 'expired', 'abandoned' );
	const PAYMENT_STATES = array( 'not_started', 'pending', 'requires_action', 'authorized', 'captured', 'failed', 'voided', 'partially_refunded', 'refunded' );
	const FULFILLMENT_STATES = array( 'selected', 'hold_pending', 'held', 'confirmation_pending', 'confirmed', 'fulfillment_pending', 'fulfilled', 'change_pending', 'changed', 'cancellation_pending', 'cancelled', 'failed', 'reconciliation_required' );
	const SETTLEMENT_STATES = array( 'not_applicable', 'click_recorded', 'conversion_reported', 'eligible', 'payable', 'paid', 'reversed', 'disputed' );
	const OPERATION_STATES = array( 'queued', 'started', 'succeeded', 'failed', 'uncertain', 'reconciled' );

	/**
	 * Return one canonical vertical or an empty string.
	 */
	public static function vertical( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, self::VERTICALS, true ) ? $value : '';
	}

	/**
	 * Translate only documented compatibility aliases at old API boundaries.
	 * Car rental is intentionally not guessed into transfer or equipment.
	 */
	public static function legacy_vertical( $value ) {
		$value = sanitize_key( (string) $value );
		$aliases = array(
			'flights'    => 'flight',
			'hotel'      => 'accommodation',
			'hotels'     => 'accommodation',
			'packages'   => 'package',
			'transfers'  => 'transfer',
			'activities' => 'activity',
			'esim'       => 'connectivity',
		);
		return isset( $aliases[ $value ] ) ? $aliases[ $value ] : self::vertical( $value );
	}

	/**
	 * Normalize a unique, sorted vertical list. Unknown values fail closed.
	 *
	 * @return array|WP_Error
	 */
	public static function verticals( $values, $legacy = false ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ! $values ) {
			return new WP_Error( 'tra_vel_commerce_verticals_invalid', 'At least one canonical commerce vertical is required.', array( 'status' => 400 ) );
		}
		$normalized = array();
		foreach ( $values as $value ) {
			$vertical = $legacy ? self::legacy_vertical( $value ) : self::vertical( $value );
			if ( '' === $vertical ) {
				return new WP_Error( 'tra_vel_commerce_vertical_invalid', 'An unsupported commerce vertical was provided.', array( 'status' => 400 ) );
			}
			$normalized[ $vertical ] = true;
		}
		$normalized = array_keys( $normalized );
		sort( $normalized, SORT_STRING );
		return $normalized;
	}

	/**
	 * Normalize a unique, sorted capability list.
	 *
	 * @return array|WP_Error
	 */
	public static function capabilities( $values ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ! $values ) {
			return new WP_Error( 'tra_vel_commerce_capabilities_invalid', 'At least one commerce capability is required.', array( 'status' => 400 ) );
		}
		$normalized = array();
		foreach ( $values as $value ) {
			$value = sanitize_key( (string) $value );
			if ( ! in_array( $value, self::CAPABILITIES, true ) ) {
				return new WP_Error( 'tra_vel_commerce_capability_invalid', 'An unsupported commerce capability was provided.', array( 'status' => 400 ) );
			}
			$normalized[ $value ] = true;
		}
		$normalized = array_keys( $normalized );
		sort( $normalized, SORT_STRING );
		return $normalized;
	}
}
