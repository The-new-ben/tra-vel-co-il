<?php
/**
 * Production selector for a complete authoritative Trip Cockpit snapshot.
 *
 * The callback behind SNAPSHOT_FILTER must join current durable registration,
 * booking, servicing, commerce, Trip Care, loyalty, and offline-pack records.
 * Returning null is an explicit not-ready result; no demo source is substituted.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Customer_Trip_Cockpit_Authoritative_Source_Provider implements Tra_Vel_Customer_Trip_Cockpit_Source_Provider {
	const SNAPSHOT_FILTER = 'tra_vel_customer_trip_cockpit_authoritative_snapshot';

	public function register_hooks() {
		add_filter( Tra_Vel_Customer_Trip_Cockpit_Source_Assembler::PROVIDER_FILTER, array( $this, 'select_provider' ), 20, 2 );
	}

	/** Keep an explicitly installed provider; otherwise select the built-in join boundary. */
	public function select_provider( $provider, $context ) {
		unset( $context );
		return $provider instanceof Tra_Vel_Customer_Trip_Cockpit_Source_Provider ? $provider : $this;
	}

	/** @return array|WP_Error|null */
	public function get_authoritative_snapshot( $context ) {
		if ( ! self::valid_context( $context ) ) {
			return new WP_Error( 'tra_vel_customer_trip_cockpit_authoritative_context_invalid', 'The authoritative snapshot context is invalid.', array( 'status' => 400 ) );
		}
		$snapshot = apply_filters( self::SNAPSHOT_FILTER, null, $context );
		if ( null === $snapshot || is_wp_error( $snapshot ) || is_array( $snapshot ) ) {
			return $snapshot;
		}
		return new WP_Error( 'tra_vel_customer_trip_cockpit_authoritative_snapshot_invalid', 'The authoritative snapshot provider returned an invalid result.', array( 'status' => 503 ) );
	}

	/** True only when a real server repository join callback is installed. */
	public static function is_ready() {
		return function_exists( 'has_filter' ) && false !== has_filter( self::SNAPSHOT_FILTER );
	}

	private static function valid_context( $context ) {
		$keys = array( 'event_ref', 'event_kind', 'owner_user_id', 'account_ref', 'trip_ref' );
		return
			is_array( $context ) &&
			! array_diff( $keys, array_keys( $context ) ) &&
			! array_diff( array_keys( $context ), $keys ) &&
			(int) $context['owner_user_id'] > 0 &&
			Tra_Vel_Traveler_Principal::valid_ref( $context['account_ref'], 'account' ) &&
			Tra_Vel_Traveler_Principal::valid_ref( $context['trip_ref'], 'trip' );
	}
}
