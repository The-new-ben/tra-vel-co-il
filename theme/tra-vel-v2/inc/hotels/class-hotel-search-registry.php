<?php
/**
 * Hotel adapter registry with an explicit demo fallback.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Hotel_Search_Registry {
	/** @var Tra_Vel_V2_Hotel_Search_Adapter[] */
	private $adapters = array();

	public function __construct() {
		$adapters = apply_filters( 'tra_vel_v2_hotel_search_adapters', array() );
		if ( is_array( $adapters ) ) {
			foreach ( $adapters as $adapter ) {
				$this->register( $adapter );
			}
		}
		$this->register( new Tra_Vel_V2_Demo_Hotel_Search_Adapter() );
	}

	public function register( $adapter ) {
		if ( ! $adapter instanceof Tra_Vel_V2_Hotel_Search_Adapter ) {
			return false;
		}
		$id = sanitize_key( $adapter->get_id() );
		if ( ! $id || isset( $this->adapters[ $id ] ) ) {
			return false;
		}
		$this->adapters[ $id ] = $adapter;
		return true;
	}

	public function get_cache_signature() {
		$signature = array();
		foreach ( $this->adapters as $id => $adapter ) {
			$signature[ $id ] = array(
				'mode'       => $adapter->get_mode(),
				'configured' => (bool) $adapter->is_configured(),
				'version'    => $adapter->get_cache_version(),
			);
		}
		return md5( wp_json_encode( $signature ) );
	}

	public function resolve( $query ) {
		$reports      = array();
		$failed_live  = array();
		$demo_adapter = null;

		foreach ( $this->adapters as $id => $adapter ) {
			if ( 'demo' === $adapter->get_mode() ) {
				$demo_adapter = $adapter;
				continue;
			}
			$report = array(
				'id'         => $id,
				'mode'       => $adapter->get_mode(),
				'configured' => (bool) $adapter->is_configured(),
				'healthy'    => false,
				'used'       => false,
				'error_code' => null,
			);
			if ( ! $report['configured'] ) {
				$reports[ $id ] = $report;
				continue;
			}
			try {
				$result = $adapter->search( $query );
			} catch ( Throwable $error ) {
				$result = new WP_Error( 'tra_vel_hotel_adapter_exception', 'Hotel adapter failed safely.' );
			}
			if ( is_wp_error( $result ) || empty( $result['properties'] ) ) {
				$report['error_code'] = is_wp_error( $result ) ? $result->get_error_code() : 'invalid_contract';
				$reports[ $id ]       = $report;
				$failed_live[]        = $id;
				continue;
			}
			$report['healthy'] = true;
			$report['used']    = true;
			$reports[ $id ]    = $report;
			$result['data_mode'] = 'live';
			return array(
				'data'            => $result,
				'reports'         => $reports,
				'degraded'        => ! empty( $failed_live ),
				'failed_adapters' => $failed_live,
			);
		}

		if ( ! $demo_adapter ) {
			return new WP_Error( 'tra_vel_hotels_unavailable', 'No hotel search adapter is available.', array( 'status' => 503 ) );
		}
		$demo_id     = sanitize_key( $demo_adapter->get_id() );
		$demo_result = $demo_adapter->search( $query );
		if ( is_wp_error( $demo_result ) ) {
			return $demo_result;
		}
		$reports[ $demo_id ] = array(
			'id'         => $demo_id,
			'mode'       => 'demo',
			'configured' => true,
			'healthy'    => true,
			'used'       => true,
			'error_code' => null,
		);
		return array(
			'data'            => $demo_result,
			'reports'         => $reports,
			'degraded'        => ! empty( $failed_live ),
			'failed_adapters' => $failed_live,
		);
	}
}
