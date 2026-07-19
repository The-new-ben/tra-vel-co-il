<?php
/**
 * Trusted private read-model provider used by the customer cockpit controller.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

interface Tra_Vel_Customer_Trip_Cockpit_Read_Model_Provider {
	public static function is_ready();

	/** Return the newest authoritative cockpit owned by one signed-in user. */
	public function get_owned_current_projection( $owner_user_id, $now = null );

	/** Return the authoritative whole-trip cockpit for an exact session binding. */
	public function get_bound_projection( $trip_ref, $case_ref, $account_ref, $now = null );

	/** Atomically consume a fixed-window customer-read limit. */
	public function consume_limit( $limit_key, $limit, $expires_at );
}
