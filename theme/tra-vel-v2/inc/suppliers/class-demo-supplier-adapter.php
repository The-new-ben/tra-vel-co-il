<?php
/**
 * Safe bundled fallback supplier.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Demo_Supplier_Adapter implements Tra_Vel_V2_Supplier_Adapter {
	/**
	 * Bundled contract path.
	 *
	 * @var string
	 */
	private $path;

	public function __construct( $path ) {
		$this->path = $path;
	}

	public function get_id() {
		return 'curated_demo';
	}

	public function get_verticals() {
		return array( 'flights', 'hotels', 'insurance', 'weather' );
	}

	public function is_configured() {
		return true;
	}

	public function get_mode() {
		return 'demo';
	}

	public function get_cache_version() {
		return TRA_VEL_V2_VERSION . '-discovery-contract-3';
	}

	public function fetch( $context ) {
		unset( $context );
		if ( ! is_readable( $this->path ) ) {
			return new WP_Error( 'tra_vel_demo_missing', 'The bundled discovery fallback is unavailable.', array( 'status' => 503 ) );
		}

		$decoded = json_decode( (string) file_get_contents( $this->path ), true );
		if ( ! is_array( $decoded ) || JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'tra_vel_demo_invalid', 'The bundled discovery fallback is invalid.', array( 'status' => 500 ) );
		}

		return $decoded;
	}
}
