<?php
/**
 * Insurance adapter registry with explicit demo fallback.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/class-commercial-provenance.php';

class Tra_Vel_V2_Insurance_Quote_Registry {
	/** @var Tra_Vel_V2_Insurance_Quote_Adapter[] */
	private $adapters = array();

	public function __construct() {
		$adapters = apply_filters( 'tra_vel_v2_insurance_quote_adapters', array() );
		if ( is_array( $adapters ) ) {
			foreach ( $adapters as $adapter ) {
				$this->register( $adapter );
			}
		}
		$this->register( new Tra_Vel_V2_Demo_Insurance_Quote_Adapter() );
	}

	public function register( $adapter ) {
		if ( ! $adapter instanceof Tra_Vel_V2_Insurance_Quote_Adapter ) {
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
			$signature[ $id ] = array( 'mode' => $adapter->get_mode(), 'configured' => (bool) $adapter->is_configured(), 'version' => $adapter->get_cache_version() );
		}
		return md5( wp_json_encode( $signature ) );
	}

	public function resolve( $query ) {
		$reports = array();
		$failed_live = array();
		$demo_adapter = null;
		foreach ( $this->adapters as $id => $adapter ) {
			if ( 'demo' === $adapter->get_mode() ) {
				$demo_adapter = $adapter;
				continue;
			}
			$report = array( 'id' => $id, 'mode' => $adapter->get_mode(), 'configured' => (bool) $adapter->is_configured(), 'healthy' => false, 'used' => false, 'error_code' => null );
			if ( ! $report['configured'] ) {
				$reports[ $id ] = $report;
				continue;
			}
			try {
				$result = $adapter->quote( $query );
			} catch ( Throwable $error ) {
				$result = new WP_Error( 'tra_vel_insurance_adapter_exception', 'Insurance adapter failed safely.' );
			}
			$validation = is_wp_error( $result ) ? $result : Tra_Vel_V2_Commercial_Provenance::validate(
				$result,
				$id,
				'plans',
				array( 'pricing', 'total_trip' ),
				'purchase',
				'purchasable',
				isset( $query['currency'] ) ? $query['currency'] : '',
				'whole_policy_period',
				array( 'regulated' => true )
			);
			if ( is_wp_error( $validation ) ) {
				$report['error_code'] = $validation->get_error_code();
				$reports[ $id ] = $report;
				$failed_live[] = $id;
				continue;
			}
			$report['healthy'] = true;
			$report['used'] = true;
			$reports[ $id ] = $report;
			return array( 'data' => $result, 'reports' => $reports, 'degraded' => ! empty( $failed_live ), 'failed_adapters' => $failed_live );
		}
		if ( ! $demo_adapter ) {
			return new WP_Error( 'tra_vel_insurance_unavailable', 'No insurance quote adapter is available.', array( 'status' => 503 ) );
		}
		$demo_id = sanitize_key( $demo_adapter->get_id() );
		$demo_result = $demo_adapter->quote( $query );
		if ( is_wp_error( $demo_result ) ) {
			return $demo_result;
		}
		$reports[ $demo_id ] = array( 'id' => $demo_id, 'mode' => 'demo', 'configured' => true, 'healthy' => true, 'used' => true, 'error_code' => null );
		return array( 'data' => $demo_result, 'reports' => $reports, 'degraded' => ! empty( $failed_live ), 'failed_adapters' => $failed_live );
	}
}
