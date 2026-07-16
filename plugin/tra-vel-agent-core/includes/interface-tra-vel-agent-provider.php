<?php
/**
 * Provider boundary for natural-language TripRequest interpretation.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

interface Tra_Vel_Agent_Provider {
	/**
	 * Return only safe configuration and capability metadata.
	 *
	 * @return array
	 */
	public function health();

	/**
	 * Interpret one request without performing supplier or booking work.
	 *
	 * @param string $prompt Natural-language request.
	 * @param string $mode   agent|surprise.
	 * @param string $locale Requested locale.
	 * @return array|WP_Error
	 */
	public function interpret( $prompt, $mode, $locale );
}
