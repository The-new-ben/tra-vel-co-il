<?php
/**
 * Supplier registry and normalized contract merger.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Supplier_Registry {
	/**
	 * Registered adapters keyed by public ID.
	 *
	 * @var Tra_Vel_V2_Supplier_Adapter[]
	 */
	private $adapters = array();

	public function __construct() {
		$this->register(
			new Tra_Vel_V2_Demo_Supplier_Adapter(
				TRA_VEL_V2_PATH . '/assets/data/discovery-demo.json'
			)
		);
		$this->register( new Tra_Vel_V2_Open_Meteo_Supplier_Adapter() );

		$adapters = apply_filters( 'tra_vel_v2_supplier_adapters', array() );
		if ( is_array( $adapters ) ) {
			foreach ( $adapters as $adapter ) {
				$this->register( $adapter );
			}
		}
	}

	/**
	 * Register one adapter when it satisfies the public contract.
	 */
	public function register( $adapter ) {
		if ( ! $adapter instanceof Tra_Vel_V2_Supplier_Adapter ) {
			return false;
		}

		$id = sanitize_key( $adapter->get_id() );
		if ( ! $id || isset( $this->adapters[ $id ] ) ) {
			return false;
		}

		$this->adapters[ $id ] = $adapter;
		return true;
	}

	/**
	 * Stable signature for cache invalidation when adapters change.
	 */
	public function get_cache_signature() {
		$signature = array();
		foreach ( $this->adapters as $id => $adapter ) {
			$signature[ $id ] = array(
				'version'    => (string) $adapter->get_cache_version(),
				'configured' => (bool) $adapter->is_configured(),
				'mode'       => (string) $adapter->get_mode(),
			);
		}
		return md5( wp_json_encode( $signature ) );
	}

	/**
	 * Resolve every configured adapter, keeping the demo adapter as fallback.
	 */
	public function resolve( $context ) {
		$contract = null;
		$reports  = array();
		$live_ok  = 0;
		$live_failed = array();

		foreach ( $this->adapters as $id => $adapter ) {
			$report = array(
				'id'         => $id,
				'mode'       => $adapter->get_mode(),
				'verticals'  => array_values( array_map( 'sanitize_key', (array) $adapter->get_verticals() ) ),
				'configured' => (bool) $adapter->is_configured(),
				'healthy'    => false,
				'error_code' => null,
			);

			if ( ! $report['configured'] ) {
				$reports[ $id ] = $report;
				continue;
			}

			try {
				$result = $adapter->fetch( $context );
			} catch ( Throwable $error ) {
				$result = new WP_Error( 'tra_vel_supplier_exception', 'Supplier adapter failed safely.' );
			}

			if ( is_wp_error( $result ) ) {
				$report['error_code'] = $result->get_error_code();
				$reports[ $id ]       = $report;
				if ( 'live' === $adapter->get_mode() ) {
					$live_failed[] = $id;
				}
				continue;
			}

			if ( ! $this->is_contract_fragment( $result ) ) {
				$report['error_code'] = 'invalid_contract';
				$reports[ $id ]       = $report;
				if ( 'live' === $adapter->get_mode() ) {
					$live_failed[] = $id;
				}
				continue;
			}

			$report['healthy'] = true;
			$reports[ $id ]    = $report;
			$contract          = null === $contract ? $result : $this->merge_contract( $contract, $result );
			if ( 'live' === $adapter->get_mode() ) {
				++$live_ok;
			}
		}

		if ( ! is_array( $contract ) ) {
			return new WP_Error( 'tra_vel_suppliers_unavailable', 'No discovery supplier is currently available.', array( 'status' => 503 ) );
		}

		$contract['data_mode'] = $this->derive_data_mode( $contract, $live_ok );
		return array(
			'data'            => $contract,
			'reports'         => $reports,
			'degraded'        => ! empty( $live_failed ),
			'failed_adapters' => $live_failed,
		);
	}

	private function is_contract_fragment( $fragment ) {
		return is_array( $fragment ) && ( isset( $fragment['destinations'] ) || isset( $fragment['route_sets'] ) || isset( $fragment['provider_status'] ) );
	}

	private function merge_contract( $base, $overlay ) {
		$merged = array_replace_recursive( $base, $overlay );

		if ( isset( $base['destinations'] ) || isset( $overlay['destinations'] ) ) {
			$merged['destinations'] = $this->merge_destinations(
				isset( $base['destinations'] ) ? $base['destinations'] : array(),
				isset( $overlay['destinations'] ) ? $overlay['destinations'] : array()
			);
		}

		if ( isset( $overlay['route_sets'] ) ) {
			$merged['route_sets'] = array_replace(
				isset( $base['route_sets'] ) ? $base['route_sets'] : array(),
				$overlay['route_sets']
			);
		}

		return $merged;
	}

	private function merge_destinations( $base, $overlay ) {
		$indexed = array();
		$order   = array();

		foreach ( (array) $base as $destination ) {
			if ( empty( $destination['id'] ) ) {
				continue;
			}
			$indexed[ $destination['id'] ] = $destination;
			$order[]                       = $destination['id'];
		}

		foreach ( (array) $overlay as $destination ) {
			if ( empty( $destination['id'] ) ) {
				continue;
			}
			$id = $destination['id'];
			if ( ! isset( $indexed[ $id ] ) ) {
				$order[] = $id;
			}
			$indexed[ $id ] = isset( $indexed[ $id ] ) ? array_replace_recursive( $indexed[ $id ], $destination ) : $destination;
		}

		return array_values( array_intersect_key( $indexed, array_flip( $order ) ) );
	}

	private function derive_data_mode( $contract, $live_ok ) {
		if ( 0 === $live_ok ) {
			return 'demo';
		}

		$statuses  = isset( $contract['provider_status'] ) ? $contract['provider_status'] : array();
		$connected = array_filter(
			$statuses,
			static function ( $status ) {
				return ! empty( $status['connected'] );
			}
		);

		return $statuses && count( $connected ) === count( $statuses ) ? 'live' : 'mixed';
	}
}
