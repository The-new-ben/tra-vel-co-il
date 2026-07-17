<?php
/**
 * Contract implemented by every Tra-Vel discovery supplier.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

interface Tra_Vel_V2_Supplier_Adapter {
	/**
	 * Stable, public adapter identifier. Never include credentials.
	 */
	public function get_id();

	/**
	 * Supported verticals: deals, packages, flights, hotels, insurance, or weather.
	 */
	public function get_verticals();

	/**
	 * Whether required configuration is present.
	 */
	public function is_configured();

	/**
	 * Adapter mode: demo or live.
	 */
	public function get_mode();

	/**
	 * Version used to invalidate normalized response caches.
	 */
	public function get_cache_version();

	/**
	 * Return a normalized full or partial discovery contract, or WP_Error.
	 *
	 * @param array $context Sanitized discovery query context.
	 */
	public function fetch( $context );
}
