<?php
/**
 * Non-bookable flight search fallback.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Demo_Flight_Search_Adapter implements Tra_Vel_V2_Flight_Search_Adapter {
	public function get_id() {
		return 'curated_demo_flights';
	}

	public function is_configured() {
		return true;
	}

	public function get_mode() {
		return 'demo';
	}

	public function get_cache_version() {
		return TRA_VEL_V2_VERSION . '-flight-contract-1';
	}

	public function search( $query ) {
		if ( 'TLV' !== strtoupper( (string) $query['origin'] ) || 'BKK' !== strtoupper( (string) $query['destination'] ) ) {
			return new WP_Error( 'tra_vel_demo_flight_route_unsupported', 'The demo flight adapter supports TLV to Bangkok only.', array( 'status' => 422 ) );
		}
		$path = TRA_VEL_V2_PATH . '/assets/data/flight-search-demo.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_demo_flights_missing', 'Demo flight offers are unavailable.', array( 'status' => 503 ) );
		}
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) || empty( $data['offers'] ) ) {
			return new WP_Error( 'tra_vel_demo_flights_invalid', 'Demo flight offers are invalid.', array( 'status' => 500 ) );
		}
		$data['search'] = $query;
		return $data;
	}
}
