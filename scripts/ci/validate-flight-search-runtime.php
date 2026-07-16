<?php
/**
 * Deterministic harness for flight adapter selection and stale-safe caching.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_VERSION', 'test' );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );

$GLOBALS['tv2_flight_transients'] = array();
$GLOBALS['tv2_flight_options']    = array();

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = null ) {
		unset( $message, $data );
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
function wp_json_encode( $value ) {
	return json_encode( $value );
}
function apply_filters( $hook, $value ) {
	unset( $hook );
	return $value;
}
function get_transient( $key ) {
	return isset( $GLOBALS['tv2_flight_transients'][ $key ] ) ? $GLOBALS['tv2_flight_transients'][ $key ] : false;
}
function set_transient( $key, $value, $ttl ) {
	unset( $ttl );
	$GLOBALS['tv2_flight_transients'][ $key ] = $value;
	return true;
}
function delete_transient( $key ) {
	unset( $GLOBALS['tv2_flight_transients'][ $key ] );
	return true;
}
function get_option( $key, $default = false ) {
	return isset( $GLOBALS['tv2_flight_options'][ $key ] ) ? $GLOBALS['tv2_flight_options'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
	unset( $autoload );
	$GLOBALS['tv2_flight_options'][ $key ] = $value;
	return true;
}

require TRA_VEL_V2_PATH . '/inc/flights/interface-flight-search-adapter.php';
require TRA_VEL_V2_PATH . '/inc/flights/class-demo-flight-search-adapter.php';
require TRA_VEL_V2_PATH . '/inc/flights/class-flight-search-registry.php';
require TRA_VEL_V2_PATH . '/inc/flights/class-flight-search-repository.php';

function tv2_flight_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Flight runtime validation failed: {$message}\n" );
		exit( 1 );
	}
}

$query = array(
	'origin' => 'TLV', 'destination' => 'BKK', 'departure_date' => '2030-01-01', 'return_date' => '2030-01-15',
	'adults' => 1, 'children' => 0, 'infants' => 0, 'cabin' => 'economy', 'direct' => false, 'max_stops' => 1, 'sort' => 'smart', 'limit' => 12, 'currency' => 'USD',
);
$repository = new Tra_Vel_V2_Flight_Search_Repository();
$first      = $repository->search( $query );
$second     = $repository->search( $query );

tv2_flight_assert( ! is_wp_error( $first ), 'demo repository returned an error' );
tv2_flight_assert( 'miss' === $first['runtime']['cache_state'], 'first request must be a cache miss' );
tv2_flight_assert( 'fresh' === $second['runtime']['cache_state'], 'second request must use fresh cache' );
tv2_flight_assert( 'demo' === $first['data']['data_mode'], 'fallback must remain demo mode' );
tv2_flight_assert( 3 === count( $first['data']['offers'] ), 'demo offer count changed' );
tv2_flight_assert( true === $first['runtime']['adapters']['curated_demo_flights']['healthy'], 'demo adapter is not healthy' );

class Tra_Vel_V2_Test_Flight_Registry extends Tra_Vel_V2_Flight_Search_Registry {
	public $fail = false;
	public $degrade = false;
	public function get_cache_signature() {
		return 'test-flight-registry-v1';
	}
	public function resolve( $context ) {
		if ( $this->fail ) {
			return new WP_Error( 'simulated_flight_failure' );
		}
		if ( $this->degrade ) {
			return array(
				'data' => array( 'data_mode' => 'demo', 'offers' => array( array( 'id' => 'fallback' ) ), 'search' => $context ),
				'reports' => array( 'test-live' => array( 'healthy' => false ) ),
				'degraded' => true,
				'failed_adapters' => array( 'test-live' ),
			);
		}
		return array(
			'data' => array( 'data_mode' => 'live', 'offers' => array( array( 'id' => 'live-offer' ) ), 'search' => $context ),
			'reports' => array( 'test-live' => array( 'healthy' => true ) ),
			'degraded' => false,
			'failed_adapters' => array(),
		);
	}
}

$test_registry   = new Tra_Vel_V2_Test_Flight_Registry();
$test_repository = new Tra_Vel_V2_Flight_Search_Repository( $test_registry );
$live            = $test_repository->search( $query );
$test_registry->degrade = true;
$stale           = $test_repository->search( $query, true );

tv2_flight_assert( 'miss' === $live['runtime']['cache_state'], 'live result was not cached' );
tv2_flight_assert( 'stale_error' === $stale['runtime']['cache_state'], 'degraded supplier did not use stale live data' );
tv2_flight_assert( 'flight_supplier_degraded' === $stale['runtime']['fallback_error'], 'degraded fallback provenance is missing' );
tv2_flight_assert( 'live-offer' === $stale['data']['offers'][0]['id'], 'degraded refresh replaced the last valid live offer' );
tv2_flight_assert( array( 'test-live' ) === $stale['runtime']['failed_adapters'], 'failed adapter IDs are missing' );

$variant = $query;
$variant['adults'] = 2;
$test_registry->degrade = false;
$fresh_variant = $test_repository->search( $variant );
$test_registry->fail = true;
$failed_variant = $test_repository->search( $variant, true );
tv2_flight_assert( 'miss' === $fresh_variant['runtime']['cache_state'], 'second cache variant was not created' );
tv2_flight_assert( 'stale_error' === $failed_variant['runtime']['cache_state'], 'full supplier failure did not use stale data' );
tv2_flight_assert( 'simulated_flight_failure' === $failed_variant['runtime']['fallback_error'], 'supplier error code is missing' );
tv2_flight_assert( 2 === $test_repository->purge(), 'cache generation did not increment' );

echo "Tra-Vel flight runtime validation passed (fresh cache, stale fallback, purge generation).\n";
