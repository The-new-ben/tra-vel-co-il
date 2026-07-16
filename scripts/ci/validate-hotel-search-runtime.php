<?php
/**
 * Deterministic harness for hotel adapter selection and stale-safe caching.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_VERSION', 'test' );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );

$GLOBALS['tv2_hotel_transients'] = array();
$GLOBALS['tv2_hotel_options']    = array();

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
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function apply_filters( $hook, $value ) { unset( $hook ); return $value; }
function get_transient( $key ) { return isset( $GLOBALS['tv2_hotel_transients'][ $key ] ) ? $GLOBALS['tv2_hotel_transients'][ $key ] : false; }
function set_transient( $key, $value, $ttl ) { unset( $ttl ); $GLOBALS['tv2_hotel_transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['tv2_hotel_transients'][ $key ] ); return true; }
function get_option( $key, $default = false ) { return isset( $GLOBALS['tv2_hotel_options'][ $key ] ) ? $GLOBALS['tv2_hotel_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { unset( $autoload ); $GLOBALS['tv2_hotel_options'][ $key ] = $value; return true; }

require TRA_VEL_V2_PATH . '/inc/hotels/interface-hotel-search-adapter.php';
require TRA_VEL_V2_PATH . '/inc/hotels/class-demo-hotel-search-adapter.php';
require TRA_VEL_V2_PATH . '/inc/hotels/class-hotel-search-registry.php';
require TRA_VEL_V2_PATH . '/inc/hotels/class-hotel-search-repository.php';

function tv2_hotel_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Hotel runtime validation failed: {$message}\n" );
		exit( 1 );
	}
}

$query = array(
	'destination' => 'BUD', 'checkin' => '2030-02-01', 'checkout' => '2030-02-05', 'nights' => 4,
	'adults' => 2, 'children' => 0, 'rooms' => 1, 'min_price' => 0, 'max_price' => 2000, 'stars' => 0, 'area' => '',
	'free_cancellation' => false, 'breakfast' => false, 'family' => false, 'sort' => 'smart', 'limit' => 12, 'currency' => 'EUR',
);
$repository = new Tra_Vel_V2_Hotel_Search_Repository();
$first      = $repository->search( $query );
$second     = $repository->search( $query );
tv2_hotel_assert( ! is_wp_error( $first ), 'demo repository returned an error' );
tv2_hotel_assert( 'miss' === $first['runtime']['cache_state'], 'first request must be a cache miss' );
tv2_hotel_assert( 'fresh' === $second['runtime']['cache_state'], 'second request must use fresh cache' );
tv2_hotel_assert( 'demo' === $first['data']['data_mode'], 'fallback must remain demo mode' );
tv2_hotel_assert( 4 === count( $first['data']['properties'] ), 'demo property count changed' );
tv2_hotel_assert( 578 === $first['data']['properties'][0]['pricing']['total_stay'], 'four-night total normalization changed' );
tv2_hotel_assert( true === $first['runtime']['adapters']['curated_demo_hotels']['healthy'], 'demo adapter is not healthy' );

$unsupported_query                = $query;
$unsupported_query['destination'] = 'PRG';
$unsupported                      = ( new Tra_Vel_V2_Demo_Hotel_Search_Adapter() )->search( $unsupported_query );
tv2_hotel_assert( is_wp_error( $unsupported ) && 'tra_vel_demo_hotel_destination_unsupported' === $unsupported->get_error_code(), 'demo adapter mislabeled another destination as Budapest' );

$two_room_query = $query;
$two_room_query['rooms'] = 2;
$two_room = $repository->search( $two_room_query );
tv2_hotel_assert( 1156 === $two_room['data']['properties'][0]['pricing']['total_stay'], 'room multiplier is incorrect' );

class Tra_Vel_V2_Test_Hotel_Registry extends Tra_Vel_V2_Hotel_Search_Registry {
	public $fail = false;
	public $degrade = false;
	public function get_cache_signature() { return 'test-hotel-registry-v1'; }
	public function resolve( $context ) {
		if ( $this->fail ) return new WP_Error( 'simulated_hotel_failure' );
		if ( $this->degrade ) {
			return array(
				'data' => array( 'data_mode' => 'demo', 'properties' => array( array( 'id' => 'fallback' ) ), 'search' => $context ),
				'reports' => array( 'test-live' => array( 'healthy' => false ) ), 'degraded' => true, 'failed_adapters' => array( 'test-live' ),
			);
		}
		return array(
			'data' => array( 'data_mode' => 'live', 'properties' => array( array( 'id' => 'live-hotel' ) ), 'search' => $context ),
			'reports' => array( 'test-live' => array( 'healthy' => true ) ), 'degraded' => false, 'failed_adapters' => array(),
		);
	}
}

$test_registry   = new Tra_Vel_V2_Test_Hotel_Registry();
$test_repository = new Tra_Vel_V2_Hotel_Search_Repository( $test_registry );
$live            = $test_repository->search( $query );
$test_registry->degrade = true;
$stale           = $test_repository->search( $query, true );
tv2_hotel_assert( 'miss' === $live['runtime']['cache_state'], 'live result was not cached' );
tv2_hotel_assert( 'stale_error' === $stale['runtime']['cache_state'], 'degraded supplier did not use stale live data' );
tv2_hotel_assert( 'hotel_supplier_degraded' === $stale['runtime']['fallback_error'], 'degraded provenance is missing' );
tv2_hotel_assert( 'live-hotel' === $stale['data']['properties'][0]['id'], 'degraded refresh replaced valid live inventory' );

$variant = $query;
$variant['adults'] = 3;
$test_registry->degrade = false;
$fresh_variant = $test_repository->search( $variant );
$test_registry->fail = true;
$failed_variant = $test_repository->search( $variant, true );
tv2_hotel_assert( 'miss' === $fresh_variant['runtime']['cache_state'], 'second cache variant was not created' );
tv2_hotel_assert( 'stale_error' === $failed_variant['runtime']['cache_state'], 'full supplier failure did not use stale data' );
tv2_hotel_assert( 'simulated_hotel_failure' === $failed_variant['runtime']['fallback_error'], 'supplier error code is missing' );
tv2_hotel_assert( 2 === $test_repository->purge(), 'cache generation did not increment' );

echo "Tra-Vel hotel runtime validation passed (price normalization, fresh cache, stale fallback, purge generation).\n";
