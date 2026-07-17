<?php
/**
 * Cached, stale-safe discovery repository.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Discovery_Repository {
	const CACHE_TTL = 300;
	const STALE_TTL = 86400;
	const LOCK_TTL  = 30;

	/** @var Tra_Vel_V2_Supplier_Registry */
	private $registry;

	public function __construct( $registry = null ) {
		$this->registry = $registry instanceof Tra_Vel_V2_Supplier_Registry ? $registry : new Tra_Vel_V2_Supplier_Registry();
	}

	/**
	 * Return a normalized contract and runtime provenance.
	 */
	public function get( $context = array(), $force = false ) {
		$context   = $this->normalize_context( $context );
		$cache_key = $this->cache_key( $context );
		$fresh_key = $cache_key . '_fresh';
		$stale_key = $cache_key . '_stale';
		$lock_key  = $cache_key . '_lock';

		if ( ! $force ) {
			$fresh = get_transient( $fresh_key );
			if ( $this->is_cached_result( $fresh ) ) {
				return $this->with_cache_state( $fresh, 'fresh' );
			}
		}

		$stale = get_transient( $stale_key );
		if ( get_transient( $lock_key ) && $this->is_cached_result( $stale ) ) {
			return $this->with_cache_state( $stale, 'stale_refreshing' );
		}

		set_transient( $lock_key, 1, self::LOCK_TTL );
		try {
			$resolved = $this->registry->resolve( $context );
		} catch ( Throwable $error ) {
			$resolved = new WP_Error( 'tra_vel_repository_exception', 'Discovery refresh failed safely.' );
		}
		delete_transient( $lock_key );

		if ( is_wp_error( $resolved ) ) {
			if ( $this->is_cached_result( $stale ) ) {
				$stale['runtime']['fallback_error'] = $resolved->get_error_code();
				return $this->with_cache_state( $stale, 'stale_error' );
			}
			return $resolved;
		}

		if ( ! empty( $resolved['degraded'] ) && $this->is_cached_result( $stale ) ) {
			$stale['runtime']['adapters']        = $resolved['reports'];
			$stale['runtime']['degraded']        = true;
			$stale['runtime']['failed_adapters'] = $resolved['failed_adapters'];
			$stale['runtime']['fallback_error']  = 'supplier_degraded';
			return $this->with_cache_state( $stale, 'stale_error' );
		}

		$degraded = ! empty( $resolved['degraded'] );
		$cache_ttl = $degraded ? 60 : self::CACHE_TTL;

		$result = array(
			'data'    => $resolved['data'],
			'runtime' => array(
				'generated_at' => gmdate( 'c' ),
				'cache_ttl'    => $cache_ttl,
				'stale_ttl'    => self::STALE_TTL,
				'cache_state'  => $degraded ? 'degraded_fallback' : 'miss',
				'adapters'     => $resolved['reports'],
				'degraded'     => $degraded,
				'failed_adapters' => isset( $resolved['failed_adapters'] ) ? $resolved['failed_adapters'] : array(),
			),
		);

		set_transient( $fresh_key, $result, $cache_ttl );
		set_transient( $stale_key, $result, self::STALE_TTL );
		return $result;
	}

	/**
	 * Invalidate all cache variants without scanning the options table.
	 */
	public function purge() {
		$generation = (int) get_option( 'tra_vel_v2_discovery_cache_generation', 1 );
		update_option( 'tra_vel_v2_discovery_cache_generation', $generation + 1, false );
		return $generation + 1;
	}

	private function normalize_context( $context ) {
		$defaults = array(
			'budget'          => 5000,
			'destination'     => '',
			'direct'          => false,
			'q'               => '',
			'sort'            => 'smart',
			'trip'            => 'all',
			'max_stops'       => 3,
			'max_duration'    => 3000,
			'allow_overnight' => false,
			'limit'           => 24,
			'layer'           => 'deals',
		);
		$context  = wp_parse_args( is_array( $context ) ? $context : array(), $defaults );
		return array(
			'budget'          => absint( $context['budget'] ),
			'destination'     => sanitize_key( $context['destination'] ),
			'direct'          => (bool) $context['direct'],
			'q'               => sanitize_text_field( $context['q'] ),
			'sort'            => in_array( $context['sort'], array( 'smart', 'price', 'time', 'comfort' ), true ) ? $context['sort'] : 'smart',
			'trip'            => in_array( $context['trip'], array( 'all', 'short', 'long' ), true ) ? $context['trip'] : 'all',
			'max_stops'       => min( 3, max( 0, absint( $context['max_stops'] ) ) ),
			'max_duration'    => min( 6000, max( 60, absint( $context['max_duration'] ) ) ),
			'allow_overnight' => (bool) $context['allow_overnight'],
			'limit'           => min( 50, max( 1, absint( $context['limit'] ) ) ),
			'layer'           => in_array( $context['layer'], array( 'deals', 'hotels', 'airports', 'weather' ), true ) ? $context['layer'] : 'deals',
		);
	}

	private function cache_key( $context ) {
		$generation = (int) get_option( 'tra_vel_v2_discovery_cache_generation', 1 );
		$signature  = array(
			'generation' => $generation,
			'adapters'   => $this->registry->get_cache_signature(),
			'context'    => $context,
		);
		return 'tv2_discovery_' . md5( wp_json_encode( $signature ) );
	}

	private function is_cached_result( $value ) {
		return is_array( $value ) && isset( $value['data'], $value['runtime'] );
	}

	private function with_cache_state( $result, $state ) {
		$result['runtime']['cache_state'] = $state;
		return $result;
	}
}
