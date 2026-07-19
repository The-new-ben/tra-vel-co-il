<?php
/**
 * Server-keyed traveler principal and trip-owner bindings.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Traveler_Principal {
	/** Return the stable opaque reference used for one WordPress account. */
	public static function account_ref( $owner_user_id ) {
		return self::ref( 'account', $owner_user_id );
	}

	/** Return a stable opaque principal reference without exposing the user id. */
	public static function ref( $kind, $owner_user_id ) {
		$kind          = is_string( $kind ) ? $kind : '';
		$owner_user_id = (int) $owner_user_id;
		if ( $owner_user_id < 1 || 1 !== preg_match( '/^[a-z][a-z0-9_]{0,31}$/', $kind ) ) {
			return '';
		}
		$material = hash_hmac(
			'sha256',
			'traveler-registration|' . get_current_blog_id() . '|' . $owner_user_id . '|' . $kind,
			wp_salt( 'auth' )
		);
		return 'tv_' . $kind . '_' . $material;
	}

	/**
	 * Derive the independent owner/trip binding required by a private cockpit.
	 * This keyed value is authentication evidence; the projection seal alone is not.
	 */
	public static function cockpit_owner_scope_digest( $owner_user_id, $account_ref, $trip_ref ) {
		$owner_user_id = (int) $owner_user_id;
		if (
			$owner_user_id < 1 ||
			! self::valid_ref( $account_ref, 'account' ) ||
			! self::valid_ref( $trip_ref, 'trip' ) ||
			! hash_equals( self::account_ref( $owner_user_id ), $account_ref )
		) {
			return '';
		}
		return hash_hmac(
			'sha256',
			implode( '|', array( 'customer-trip-cockpit-owner-v1', get_current_blog_id(), $owner_user_id, $account_ref, $trip_ref ) ),
			wp_salt( 'secure_auth' )
		);
	}

	public static function valid_ref( $value, $kind ) {
		return is_string( $value ) && is_string( $kind ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}
}
