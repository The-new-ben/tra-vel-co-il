<?php
/**
 * Deterministic harness for package composition, pricing and stale-safe caching.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_VERSION', 'test' );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );

$GLOBALS['tv2_package_transients'] = array();
$GLOBALS['tv2_package_options']    = array();

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = null ) { unset( $message, $data ); $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function apply_filters( $hook, $value ) { unset( $hook ); return $value; }
function get_transient( $key ) { return isset( $GLOBALS['tv2_package_transients'][ $key ] ) ? $GLOBALS['tv2_package_transients'][ $key ] : false; }
function set_transient( $key, $value, $ttl ) { unset( $ttl ); $GLOBALS['tv2_package_transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['tv2_package_transients'][ $key ] ); return true; }
function get_option( $key, $default = false ) { return isset( $GLOBALS['tv2_package_options'][ $key ] ) ? $GLOBALS['tv2_package_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { unset( $autoload ); $GLOBALS['tv2_package_options'][ $key ] = $value; return true; }

require TRA_VEL_V2_PATH . '/inc/packages/interface-trip-package-adapter.php';
require TRA_VEL_V2_PATH . '/inc/packages/class-demo-trip-package-adapter.php';
require TRA_VEL_V2_PATH . '/inc/packages/class-trip-package-registry.php';
require TRA_VEL_V2_PATH . '/inc/packages/class-trip-package-repository.php';

function tv2_package_assert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "Package runtime validation failed: {$message}\n" ); exit( 1 ); }
}

$query = array(
	'origin' => 'TLV', 'destination' => 'BUD', 'departure_date' => '2030-04-01', 'return_date' => '2030-04-05', 'nights' => 4, 'trip_days' => 5,
	'adults' => 2, 'children' => 0, 'rooms' => 1, 'trip_style' => 'city', 'baggage' => true, 'breakfast' => true,
	'free_cancellation' => true, 'transfers' => true, 'direct_only' => true, 'insurance_tier' => 'auto', 'max_total' => 0,
	'sort' => 'smart', 'limit' => 12, 'currency' => 'USD',
);
$repository = new Tra_Vel_V2_Trip_Package_Repository();
$first      = $repository->search( $query );
$second     = $repository->search( $query );
tv2_package_assert( ! is_wp_error( $first ), 'demo repository returned an error' );
tv2_package_assert( 'miss' === $first['runtime']['cache_state'], 'first request must be a cache miss' );
tv2_package_assert( 'fresh' === $second['runtime']['cache_state'], 'second request must use fresh cache' );
tv2_package_assert( 'demo' === $first['data']['data_mode'], 'fallback must remain demo mode' );
tv2_package_assert( 4 === count( $first['data']['packages'] ), 'package archetype count changed' );
tv2_package_assert( 1304.0 === $first['data']['packages'][0]['pricing']['total_party'], 'smart package component sum changed' );
tv2_package_assert( 1144.0 === $first['data']['packages'][1]['pricing']['total_party'], 'value package add-on sum changed' );
tv2_package_assert( false === $first['data']['packages'][0]['pricing']['bundle_discount_verified'], 'demo package claimed an unverified discount' );
tv2_package_assert( null === $first['data']['packages'][0]['pricing']['savings'], 'demo package exposed fictional savings' );
tv2_package_assert( false === $first['data']['packages'][0]['booking']['bookable'], 'demo package became bookable' );

$no_extras_query = $query;
$no_extras_query['baggage'] = false;
$no_extras_query['breakfast'] = false;
$no_extras_query['transfers'] = false;
$no_extras_query['insurance_tier'] = 'none';
$no_extras = $repository->search( $no_extras_query );
tv2_package_assert( 864.0 === $no_extras['data']['packages'][1]['pricing']['total_party'], 'optional components were not removed from the value package' );

$family_query = $query;
$family_query['adults'] = 2;
$family_query['children'] = 2;
$family_query['trip_style'] = 'family';
$family = $repository->search( $family_query );
$family_fixture = array_values( array_filter( $family['data']['packages'], static function ( $item ) { return 'budapest-family-demo' === $item['id']; } ) )[0];
tv2_package_assert( 100 === $family_fixture['score'], 'family profile did not promote the family package' );
tv2_package_assert( true === $family_fixture['stay']['party_fits'], 'family apartment no longer fits four travelers' );

$unsupported_query = $query;
$unsupported_query['destination'] = 'PRG';
$unsupported = ( new Tra_Vel_V2_Demo_Trip_Package_Adapter() )->search( $unsupported_query );
tv2_package_assert( is_wp_error( $unsupported ) && 'tra_vel_demo_package_route_unsupported' === $unsupported->get_error_code(), 'demo adapter mislabeled another route as Budapest' );

class Tra_Vel_V2_Test_Package_Registry extends Tra_Vel_V2_Trip_Package_Registry {
	public $fail = false;
	public $degrade = false;
	public function get_cache_signature() { return 'test-package-registry-v1'; }
	public function resolve( $context ) {
		if ( $this->fail ) return new WP_Error( 'simulated_package_failure' );
		if ( $this->degrade ) return array( 'data' => array( 'data_mode' => 'demo', 'packages' => array( array( 'id' => 'fallback' ) ), 'search' => $context ), 'reports' => array( 'test-live' => array( 'healthy' => false ) ), 'degraded' => true, 'failed_adapters' => array( 'test-live' ) );
		return array( 'data' => array( 'data_mode' => 'live', 'packages' => array( array( 'id' => 'live-package' ) ), 'search' => $context ), 'reports' => array( 'test-live' => array( 'healthy' => true ) ), 'degraded' => false, 'failed_adapters' => array() );
	}
}

$test_registry = new Tra_Vel_V2_Test_Package_Registry();
$test_repository = new Tra_Vel_V2_Trip_Package_Repository( $test_registry );
$live = $test_repository->search( $query );
$test_registry->degrade = true;
$stale = $test_repository->search( $query, true );
tv2_package_assert( 'stale_error' === $stale['runtime']['cache_state'], 'degraded supplier did not use stale live data' );
tv2_package_assert( 'package_supplier_degraded' === $stale['runtime']['fallback_error'], 'degraded package provenance is missing' );
tv2_package_assert( 'live-package' === $stale['data']['packages'][0]['id'], 'degraded refresh replaced valid live package data' );
$variant = $query;
$variant['adults'] = 3;
$test_registry->degrade = false;
$test_repository->search( $variant );
$test_registry->fail = true;
$failed_variant = $test_repository->search( $variant, true );
tv2_package_assert( 'stale_error' === $failed_variant['runtime']['cache_state'], 'full supplier failure did not use stale package data' );
tv2_package_assert( 'simulated_package_failure' === $failed_variant['runtime']['fallback_error'], 'supplier error code is missing' );
tv2_package_assert( 2 === $test_repository->purge(), 'cache generation did not increment' );

echo "Tra-Vel trip package runtime validation passed (component pricing, profile fit, no false savings, stale fallback).\n";
