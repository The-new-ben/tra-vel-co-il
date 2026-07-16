<?php
/**
 * Non-bookable hotel inventory fallback.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Demo_Hotel_Search_Adapter implements Tra_Vel_V2_Hotel_Search_Adapter {
	public function get_id() {
		return 'curated_demo_hotels';
	}

	public function is_configured() {
		return true;
	}

	public function get_mode() {
		return 'demo';
	}

	public function get_cache_version() {
		return TRA_VEL_V2_VERSION . '-hotel-contract-1';
	}

	public function search( $query ) {
		$path = TRA_VEL_V2_PATH . '/assets/data/hotel-search-demo.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_demo_hotels_missing', 'Demo hotel inventory is unavailable.', array( 'status' => 503 ) );
		}
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) || empty( $data['properties'] ) ) {
			return new WP_Error( 'tra_vel_demo_hotels_invalid', 'Demo hotel inventory is invalid.', array( 'status' => 500 ) );
		}
		if ( strtoupper( (string) $query['destination'] ) !== $data['destination']['code'] ) {
			return new WP_Error( 'tra_vel_demo_hotel_destination_unsupported', 'The demo hotel adapter supports Budapest only.', array( 'status' => 422 ) );
		}
		$nights = max( 1, (int) $query['nights'] );
		$rooms  = max( 1, (int) $query['rooms'] );
		foreach ( $data['properties'] as &$property ) {
			$nightly                         = (int) $property['pricing']['nightly'];
			$taxes                           = (int) $property['pricing']['taxes'] * $rooms;
			$fees                            = (int) $property['pricing']['fees'] * $rooms;
			$property['pricing']['base']      = $nightly * $nights * $rooms;
			$property['pricing']['taxes']     = $taxes;
			$property['pricing']['fees']      = $fees;
			$property['pricing']['total_stay'] = $property['pricing']['base'] + $taxes + $fees;
		}
		unset( $property );
		$data['search'] = $query;
		$data['stay']   = array( 'nights' => $nights, 'rooms' => $rooms );
		return $data;
	}
}
