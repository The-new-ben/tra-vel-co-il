<?php
/**
 * Cached, stale-safe total-trip package repository.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Trip_Package_Repository {
	const CACHE_TTL = 300;
	const STALE_TTL = 1800;
	const LOCK_TTL  = 20;

	/** @var Tra_Vel_V2_Trip_Package_Registry */
	private $registry;

	public function __construct( $registry = null ) {
		$this->registry = $registry instanceof Tra_Vel_V2_Trip_Package_Registry ? $registry : new Tra_Vel_V2_Trip_Package_Registry();
	}

	public function search( $query, $force = false ) {
		$key       = $this->cache_key( $query );
		$fresh_key = $key . '_fresh';
		$stale_key = $key . '_stale';
		$lock_key  = $key . '_lock';
		if ( ! $force ) {
			$fresh = get_transient( $fresh_key );
			if ( $this->is_result( $fresh ) ) {
				return $this->with_state( $fresh, 'fresh' );
			}
		}
		$stale = get_transient( $stale_key );
		if ( get_transient( $lock_key ) && $this->is_result( $stale ) ) {
			return $this->with_state( $stale, 'stale_refreshing' );
		}
		set_transient( $lock_key, 1, self::LOCK_TTL );
		try {
			$resolved = $this->registry->resolve( $query );
		} catch ( Throwable $error ) {
			$resolved = new WP_Error( 'tra_vel_package_search_exception', 'Package search failed safely.' );
		}
		delete_transient( $lock_key );
		if ( is_wp_error( $resolved ) ) {
			if ( $this->is_result( $stale ) ) {
				$stale['runtime']['fallback_error'] = $resolved->get_error_code();
				return $this->with_state( $stale, 'stale_error' );
			}
			return $resolved;
		}
		if ( ! empty( $resolved['degraded'] ) && $this->is_result( $stale ) && 'live' === $stale['data']['data_mode'] ) {
			$stale['runtime']['adapters']        = $resolved['reports'];
			$stale['runtime']['failed_adapters'] = $resolved['failed_adapters'];
			$stale['runtime']['fallback_error']  = 'package_supplier_degraded';
			return $this->with_state( $stale, 'stale_error' );
		}
		$degraded = ! empty( $resolved['degraded'] );
		$ttl      = $degraded ? 60 : self::CACHE_TTL;
		$result   = array(
			'data' => $resolved['data'],
			'runtime' => array(
				'generated_at' => gmdate( 'c' ), 'cache_state' => $degraded ? 'degraded_fallback' : 'miss', 'cache_ttl' => $ttl,
				'stale_ttl' => self::STALE_TTL, 'degraded' => $degraded, 'failed_adapters' => $resolved['failed_adapters'], 'adapters' => $resolved['reports'],
			),
		);
		set_transient( $fresh_key, $result, $ttl );
		set_transient( $stale_key, $result, self::STALE_TTL );
		return $result;
	}

	public function purge() {
		$generation = (int) get_option( 'tra_vel_v2_package_cache_generation', 1 );
		update_option( 'tra_vel_v2_package_cache_generation', $generation + 1, false );
		return $generation + 1;
	}

	private function cache_key( $query ) {
		$generation = (int) get_option( 'tra_vel_v2_package_cache_generation', 1 );
		return 'tv2_packages_' . md5( wp_json_encode( array( $generation, $this->registry->get_cache_signature(), $query ) ) );
	}

	private function is_result( $result ) {
		return is_array( $result ) && isset( $result['data'], $result['runtime'] );
	}

	private function with_state( $result, $state ) {
		$result['runtime']['cache_state'] = $state;
		return $result;
	}
}
