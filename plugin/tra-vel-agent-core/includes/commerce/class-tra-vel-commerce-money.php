<?php
/**
 * Integer minor-unit money primitives for Commerce Core.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Money {
	const EXPONENTS = array(
		'AED' => 2,
		'EUR' => 2,
		'GBP' => 2,
		'ILS' => 2,
		'JPY' => 0,
		'THB' => 2,
		'USD' => 2,
	);

	public static function currency( $value ) {
		$value = strtoupper( trim( (string) $value ) );
		return isset( self::EXPONENTS[ $value ] ) ? $value : '';
	}

	public static function exponent( $currency ) {
		$currency = self::currency( $currency );
		return '' === $currency ? null : self::EXPONENTS[ $currency ];
	}

	/**
	 * Validate one non-negative integer amount without numeric coercion.
	 *
	 * @return int|WP_Error
	 */
	public static function amount( $value, $allow_zero = true ) {
		if ( ! is_int( $value ) || $value < 0 || ( ! $allow_zero && 0 === $value ) ) {
			return new WP_Error( 'tra_vel_commerce_money_invalid', 'Commerce money must use a non-negative integer minor-unit amount.', array( 'status' => 400 ) );
		}
		return $value;
	}

	/**
	 * Overflow-safe addition for non-negative minor-unit amounts.
	 *
	 * @return int|WP_Error
	 */
	public static function add( $left, $right ) {
		$left  = self::amount( $left );
		$right = self::amount( $right );
		if ( is_wp_error( $left ) || is_wp_error( $right ) ) {
			return new WP_Error( 'tra_vel_commerce_money_invalid', 'A commerce ledger contains an invalid minor-unit amount.', array( 'status' => 400 ) );
		}
		if ( $right > PHP_INT_MAX - $left ) {
			return new WP_Error( 'tra_vel_commerce_money_overflow', 'The commerce money total exceeds the supported integer range.', array( 'status' => 400 ) );
		}
		return $left + $right;
	}

	/**
	 * Sum a closed list of same-currency ledger lines.
	 *
	 * @param array  $lines    Lines with code, amount_minor and currency.
	 * @param string $currency Expected ISO currency.
	 * @return array|WP_Error
	 */
	public static function ledger( $lines, $currency ) {
		$currency = self::currency( $currency );
		if ( '' === $currency || ! is_array( $lines ) || array_values( $lines ) !== $lines || ! $lines ) {
			return new WP_Error( 'tra_vel_commerce_ledger_invalid', 'A non-empty, supported-currency commerce ledger is required.', array( 'status' => 400 ) );
		}
		$total = 0;
		$codes = array();
		$clean = array();
		foreach ( $lines as $line ) {
			if ( ! is_array( $line ) || array( 'code', 'amount_minor', 'currency' ) !== array_keys( $line ) ) {
				return new WP_Error( 'tra_vel_commerce_ledger_line_invalid', 'Commerce ledger lines must use the exact supported fields.', array( 'status' => 400 ) );
			}
			$code   = sanitize_key( (string) $line['code'] );
			$amount = self::amount( $line['amount_minor'] );
			if ( ! preg_match( '/^[a-z][a-z0-9_]{1,39}$/', $code ) || isset( $codes[ $code ] ) || is_wp_error( $amount ) || $currency !== self::currency( $line['currency'] ) ) {
				return new WP_Error( 'tra_vel_commerce_ledger_line_invalid', 'A commerce ledger line is duplicated, malformed, or uses another currency.', array( 'status' => 400 ) );
			}
			$total = self::add( $total, $amount );
			if ( is_wp_error( $total ) ) {
				return $total;
			}
			$codes[ $code ] = true;
			$clean[] = array( 'code' => $code, 'amount_minor' => $amount, 'currency' => $currency );
		}
		return array(
			'currency'    => $currency,
			'exponent'    => self::EXPONENTS[ $currency ],
			'lines'       => $clean,
			'total_minor' => $total,
		);
	}
}
