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
		$contract         = null;
		$reports          = array();
		$live_failed      = array();
		$field_provenance = $this->empty_field_provenance();

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
			if ( 'live' === $adapter->get_mode() ) {
				$field_provenance = $this->merge_field_provenance(
					$field_provenance,
					$this->detect_field_provenance( $id, $adapter, $result )
				);
			}
			$contract          = null === $contract ? $result : $this->merge_contract( $contract, $result );
		}

		if ( ! is_array( $contract ) ) {
			return new WP_Error( 'tra_vel_suppliers_unavailable', 'No discovery supplier is currently available.', array( 'status' => 503 ) );
		}

		$contract['field_provenance'] = $field_provenance;
		$contract['data_mode']        = $this->derive_data_mode( $field_provenance );
		return array(
			'data'            => $contract,
			'reports'         => $reports,
			'degraded'        => ! empty( $live_failed ),
			'failed_adapters' => $live_failed,
		);
	}

	/**
	 * Fail-closed field ownership for values merged with the editorial fallback.
	 */
	private function empty_field_provenance() {
		$empty = array(
			'live'        => false,
			'source'      => null,
			'observed_at' => null,
		);
		return array(
			'deals'           => $empty,
			'hotels'          => $empty,
			'airports'        => $empty,
			'routes'          => $empty,
			'weather_current' => $empty,
			'weather_season'  => $empty,
		);
	}

	/**
	 * Mark only fields physically supplied by a successful live adapter.
	 * A connected provider flag alone never certifies inherited fallback data.
	 */
	private function detect_field_provenance( $id, $adapter, $fragment ) {
		$provenance = $this->empty_field_provenance();
		$verticals  = array_values( array_map( 'sanitize_key', (array) $adapter->get_verticals() ) );
		$live_value = array(
			'live'        => true,
			'source'      => sanitize_key( $id ),
			'observed_at' => $this->fragment_observed_at( $fragment ),
		);
		$destinations = isset( $fragment['destinations'] ) && is_array( $fragment['destinations'] ) ? $fragment['destinations'] : array();

		foreach ( $destinations as $destination ) {
			if ( array_intersect( array( 'deals', 'packages' ), $verticals ) && isset( $destination['deal'] ) && is_array( $destination['deal'] ) && $destination['deal'] ) {
				$provenance['deals'] = $live_value;
			}
			if ( in_array( 'hotels', $verticals, true ) && isset( $destination['hotel'] ) && is_array( $destination['hotel'] ) && $destination['hotel'] ) {
				$provenance['hotels'] = $live_value;
			}
			if ( in_array( 'flights', $verticals, true ) && isset( $destination['airport'] ) && is_array( $destination['airport'] ) && $destination['airport'] ) {
				$provenance['airports'] = $live_value;
			}
			if ( in_array( 'weather', $verticals, true ) && isset( $destination['weather'] ) && is_array( $destination['weather'] ) ) {
				$weather = $destination['weather'];
				if ( array_key_exists( 'temperature_c', $weather ) || array_key_exists( 'condition', $weather ) ) {
					$provenance['weather_current'] = $live_value;
				}
				if ( array_key_exists( 'season_fit', $weather ) ) {
					$provenance['weather_season'] = $live_value;
				}
			}
		}

		if ( in_array( 'flights', $verticals, true ) && ! empty( $fragment['route_sets'] ) && is_array( $fragment['route_sets'] ) ) {
			foreach ( $fragment['route_sets'] as $routes ) {
				if ( is_array( $routes ) && $routes ) {
					$provenance['routes'] = $live_value;
					break;
				}
			}
		}

		return $provenance;
	}

	/**
	 * Preserve explicit supplier observation time when the fragment provides it.
	 */
	private function fragment_observed_at( $fragment ) {
		foreach ( isset( $fragment['provider_status'] ) ? (array) $fragment['provider_status'] : array() as $status ) {
			if ( is_array( $status ) && ! empty( $status['observed_at'] ) ) {
				return sanitize_text_field( $status['observed_at'] );
			}
		}
		foreach ( isset( $fragment['destinations'] ) ? (array) $fragment['destinations'] : array() as $destination ) {
			foreach ( array( 'deal', 'hotel', 'airport', 'weather' ) as $field ) {
				if ( ! empty( $destination[ $field ]['observed_at'] ) ) {
					return sanitize_text_field( $destination[ $field ]['observed_at'] );
				}
			}
		}
		return null;
	}

	/**
	 * Merge successful live coverage without allowing an empty fragment to erase it.
	 */
	private function merge_field_provenance( $base, $overlay ) {
		foreach ( array_keys( $base ) as $field ) {
			if ( ! empty( $overlay[ $field ]['live'] ) ) {
				$base[ $field ] = $overlay[ $field ];
			}
		}
		return $base;
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

	private function derive_data_mode( $field_provenance ) {
		$live_fields = array_filter(
			$field_provenance,
			static function ( $field ) {
				return ! empty( $field['live'] );
			}
		);
		if ( ! $live_fields ) {
			return 'demo';
		}
		return count( $live_fields ) === count( $field_provenance ) ? 'live' : 'mixed';
	}
}
