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

class WP_REST_Controller {
	protected $namespace;
	protected $rest_base;
}

class WP_REST_Request {
	private $params;
	public function __construct( $params = array() ) {
		$this->params = $params;
	}
	public function get_param( $key ) {
		return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
	}
}

class WP_REST_Response {
	public $data;
	public function __construct( $data, $status = 200 ) {
		unset( $status );
		$this->data = $data;
	}
	public function header( $name, $value ) {
		unset( $name, $value );
	}
	public function add_link( $rel, $href ) {
		unset( $rel, $href );
	}
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
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
function add_action( $hook, $callback ) {
	unset( $hook, $callback );
}
function current_time( $type ) {
	return 'timestamp' === $type ? strtotime( '2029-01-01 00:00:00 UTC' ) : '2029-01-01';
}
function wp_date( $format, $timestamp ) {
	return gmdate( $format, $timestamp );
}
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, $decimals );
}
function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . ltrim( $path, '/' );
}
function home_url( $path = '' ) {
	return 'https://example.test/' . ltrim( $path, '/' );
}
function rest_ensure_response( $data ) {
	return new WP_REST_Response( $data );
}
function current_user_can( $capability ) {
	unset( $capability );
	return true;
}

require TRA_VEL_V2_PATH . '/inc/flights/interface-flight-search-adapter.php';
require TRA_VEL_V2_PATH . '/inc/flights/class-demo-flight-search-adapter.php';
require TRA_VEL_V2_PATH . '/inc/flights/class-flight-search-registry.php';
require TRA_VEL_V2_PATH . '/inc/flights/class-flight-search-repository.php';
require TRA_VEL_V2_PATH . '/inc/flights/class-flight-search-controller.php';

function tv2_flight_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Flight runtime validation failed: {$message}\n" );
		exit( 1 );
	}
}

$query = array(
	'origin' => 'TLV', 'destination' => 'BKK', 'departure_date' => '2030-01-01', 'return_date' => '2030-01-15',
	'adults' => 1, 'children' => 0, 'infants' => 0, 'cabin' => 'economy', 'direct' => false, 'max_stops' => 1, 'max_duration' => 3000, 'sort' => 'smart', 'limit' => 12, 'currency' => 'USD',
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
$duration_variant                 = $query;
$duration_variant['max_duration'] = 960;
$duration_first                   = $repository->search( $duration_variant );
$duration_second                  = $repository->search( $duration_variant );
tv2_flight_assert( 'miss' === $duration_first['runtime']['cache_state'], 'duration preference did not create its own cache variant' );
tv2_flight_assert( 'fresh' === $duration_second['runtime']['cache_state'], 'duration cache variant was not reused' );
$stops_variant              = $query;
$stops_variant['max_stops'] = 3;
$stops_first                = $repository->search( $stops_variant );
$stops_second               = $repository->search( $stops_variant );
tv2_flight_assert( 'miss' === $stops_first['runtime']['cache_state'], 'three-stop preference did not create its own cache variant' );
tv2_flight_assert( 'fresh' === $stops_second['runtime']['cache_state'], 'three-stop cache variant was not reused' );

$controller          = new Tra_Vel_V2_Flight_Search_Controller();
$duration_request    = $query;
$duration_request['max_duration'] = 900;
$duration_response   = $controller->get_items( new WP_REST_Request( $duration_request ) );
tv2_flight_assert( $duration_response instanceof WP_REST_Response, 'duration-constrained request did not return a REST response' );
tv2_flight_assert( 2 === $duration_response->data['meta']['result_count'], 'duration constraint did not remove journeys exceeding the per-direction limit' );
tv2_flight_assert( 900 === $duration_response->data['search']['max_duration'], 'duration preference was not echoed in the REST search context' );
$collection_params = $controller->get_collection_params();
tv2_flight_assert( 60 === $collection_params['max_duration']['minimum'] && 3000 === $collection_params['max_duration']['maximum'], 'duration REST bounds changed' );
tv2_flight_assert( 'rest_validate_request_arg' === $collection_params['max_duration']['validate_callback'], 'duration REST input lacks strict validation' );
tv2_flight_assert( 0 === $collection_params['max_stops']['minimum'] && 3 === $collection_params['max_stops']['maximum'], 'flight stop preference does not match the map 0-3 range' );
tv2_flight_assert( 'rest_validate_request_arg' === $collection_params['max_stops']['validate_callback'], 'stop preference lacks strict REST validation' );
$unsupported_query                = $query;
$unsupported_query['destination'] = 'ATH';
$unsupported                      = ( new Tra_Vel_V2_Demo_Flight_Search_Adapter() )->search( $unsupported_query );
tv2_flight_assert( is_wp_error( $unsupported ) && 'tra_vel_demo_flight_route_unsupported' === $unsupported->get_error_code(), 'demo adapter mislabeled Bangkok inventory as another route' );

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
