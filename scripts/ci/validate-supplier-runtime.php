<?php
/**
 * Minimal deterministic harness for the supplier registry and cache fallback.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_VERSION', 'test' );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );

$GLOBALS['tv2_transients'] = array();
$GLOBALS['tv2_options']    = array();

class WP_Error {
	private $code;
	public function __construct( $code ) {
		$this->code = $code;
	}
	public function get_error_code() {
		return $this->code;
	}
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function absint( $value ) {
	return abs( (int) $value );
}
function wp_json_encode( $value ) {
	return json_encode( $value );
}
function apply_filters( $hook, $value ) {
	unset( $hook );
	return $value;
}
function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, $args );
}
function get_transient( $key ) {
	return isset( $GLOBALS['tv2_transients'][ $key ] ) ? $GLOBALS['tv2_transients'][ $key ] : false;
}
function set_transient( $key, $value, $ttl ) {
	unset( $ttl );
	$GLOBALS['tv2_transients'][ $key ] = $value;
	return true;
}
function delete_transient( $key ) {
	unset( $GLOBALS['tv2_transients'][ $key ] );
	return true;
}
function get_option( $key, $default = false ) {
	return isset( $GLOBALS['tv2_options'][ $key ] ) ? $GLOBALS['tv2_options'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
	unset( $autoload );
	$GLOBALS['tv2_options'][ $key ] = $value;
	return true;
}

require TRA_VEL_V2_PATH . '/inc/suppliers/bootstrap.php';

function tv2_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Supplier runtime validation failed: {$message}\n" );
		exit( 1 );
	}
}

$repository = new Tra_Vel_V2_Discovery_Repository();
$first      = $repository->get();
$second     = $repository->get();

tv2_assert( ! is_wp_error( $first ), 'demo repository returned an error' );
tv2_assert( 'miss' === $first['runtime']['cache_state'], 'first request must be a cache miss' );
tv2_assert( 'fresh' === $second['runtime']['cache_state'], 'second request must use fresh cache' );
tv2_assert( 6 === count( $first['data']['destinations'] ), 'demo destination count changed' );
tv2_assert( 'demo' === $first['data']['data_mode'], 'demo adapter must remain demo mode' );
tv2_assert( true === $first['runtime']['adapters']['curated_demo']['healthy'], 'demo adapter is not healthy' );

class Tra_Vel_V2_Test_Registry extends Tra_Vel_V2_Supplier_Registry {
	public $fail = false;
	public $degrade = false;
	public function get_cache_signature() {
		return 'test-registry-v1';
	}
	public function resolve( $context ) {
		unset( $context );
		if ( $this->fail ) {
			return new WP_Error( 'simulated_supplier_failure' );
		}
		if ( $this->degrade ) {
			return array(
				'data'            => array(
					'data_mode'       => 'demo',
					'destinations'    => array( array( 'id' => 'fallback' ) ),
					'route_sets'      => array(),
					'provider_status' => array( 'flights' => array( 'connected' => false ) ),
				),
				'reports'         => array( 'test-live' => array( 'healthy' => false ) ),
				'degraded'        => true,
				'failed_adapters' => array( 'test-live' ),
			);
		}
		return array(
			'data'    => array(
				'data_mode'        => 'live',
				'destinations'     => array( array( 'id' => 'test' ) ),
				'route_sets'       => array(),
				'provider_status'  => array( 'flights' => array( 'connected' => true ) ),
			),
			'reports'         => array( 'test' => array( 'healthy' => true ) ),
			'degraded'        => false,
			'failed_adapters' => array(),
		);
	}
}

$test_registry   = new Tra_Vel_V2_Test_Registry();
$test_repository = new Tra_Vel_V2_Discovery_Repository( $test_registry );
$live            = $test_repository->get();
$test_registry->degrade = true;
$stale           = $test_repository->get( array(), true );

tv2_assert( 'miss' === $live['runtime']['cache_state'], 'test live result was not cached' );
tv2_assert( 'stale_error' === $stale['runtime']['cache_state'], 'supplier error did not use stale data' );
tv2_assert( 'supplier_degraded' === $stale['runtime']['fallback_error'], 'degraded fallback provenance is missing' );
tv2_assert( 'test' === $stale['data']['destinations'][0]['id'], 'degraded refresh replaced the last valid live data' );
tv2_assert( array( 'test-live' ) === $stale['runtime']['failed_adapters'], 'failed adapter IDs are missing' );

$test_registry->degrade = false;
$fresh_variant          = $test_repository->get( array( 'budget' => 6000 ) );
$test_registry->fail    = true;
$failed_variant         = $test_repository->get( array( 'budget' => 6000 ), true );
tv2_assert( 'miss' === $fresh_variant['runtime']['cache_state'], 'second cache variant was not created' );
tv2_assert( 'stale_error' === $failed_variant['runtime']['cache_state'], 'full supplier error did not use stale data' );
tv2_assert( 'simulated_supplier_failure' === $failed_variant['runtime']['fallback_error'], 'supplier error code is missing' );
tv2_assert( 2 === $test_repository->purge(), 'cache generation did not increment' );

echo "Tra-Vel supplier runtime validation passed (fresh cache, stale fallback, purge generation).\n";
