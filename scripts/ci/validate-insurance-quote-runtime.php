<?php
/**
 * Deterministic harness for insurance pricing, adapters and stale-safe caching.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_VERSION', 'test' );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );

$GLOBALS['tv2_insurance_transients'] = array();
$GLOBALS['tv2_insurance_options']    = array();

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = null ) { unset( $message, $data ); $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function apply_filters( $hook, $value ) { unset( $hook ); return $value; }
function get_transient( $key ) { return isset( $GLOBALS['tv2_insurance_transients'][ $key ] ) ? $GLOBALS['tv2_insurance_transients'][ $key ] : false; }
function set_transient( $key, $value, $ttl ) { unset( $ttl ); $GLOBALS['tv2_insurance_transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['tv2_insurance_transients'][ $key ] ); return true; }
function get_option( $key, $default = false ) { return isset( $GLOBALS['tv2_insurance_options'][ $key ] ) ? $GLOBALS['tv2_insurance_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { unset( $autoload ); $GLOBALS['tv2_insurance_options'][ $key ] = $value; return true; }

require TRA_VEL_V2_PATH . '/inc/insurance/interface-insurance-quote-adapter.php';
require TRA_VEL_V2_PATH . '/inc/insurance/class-demo-insurance-quote-adapter.php';
require TRA_VEL_V2_PATH . '/inc/insurance/class-insurance-quote-registry.php';
require TRA_VEL_V2_PATH . '/inc/insurance/class-insurance-quote-repository.php';

function tv2_insurance_assert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "Insurance runtime validation failed: {$message}\n" ); exit( 1 ); }
}

$query = array(
	'destination' => 'europe', 'start_date' => '2030-03-01', 'end_date' => '2030-03-07', 'trip_days' => 7,
	'adults' => 2, 'children' => 0, 'oldest_age' => 35, 'trip_type' => 'city_break', 'baggage' => false, 'cancellation' => false,
	'adventure_sports' => false, 'winter_sports' => false, 'electronics' => false, 'medical_condition' => false, 'pregnancy' => false,
	'sort' => 'smart', 'limit' => 12, 'currency' => 'USD',
);
$repository = new Tra_Vel_V2_Insurance_Quote_Repository();
$first      = $repository->quote( $query );
$second     = $repository->quote( $query );
tv2_insurance_assert( ! is_wp_error( $first ), 'demo repository returned an error' );
tv2_insurance_assert( 'miss' === $first['runtime']['cache_state'], 'first request must be a cache miss' );
tv2_insurance_assert( 'fresh' === $second['runtime']['cache_state'], 'second request must use fresh cache' );
tv2_insurance_assert( 'demo' === $first['data']['data_mode'], 'fallback must remain demo mode' );
tv2_insurance_assert( 3 === count( $first['data']['plans'] ), 'demo plan count changed' );
tv2_insurance_assert( 33.6 === $first['data']['plans'][0]['pricing']['total_trip'], 'seven-day base calculation changed' );
tv2_insurance_assert( false === $first['data']['plans'][0]['purchase']['purchasable'], 'demo plan became purchasable' );

$addons_query = $query;
$addons_query['baggage'] = true;
$addons_query['cancellation'] = true;
$addons = $repository->quote( $addons_query );
tv2_insurance_assert( 58.1 === $addons['data']['plans'][0]['pricing']['total_trip'], 'requested add-on calculation is incorrect' );
tv2_insurance_assert( 67.2 === $addons['data']['plans'][1]['pricing']['total_trip'], 'included baggage was charged twice' );
tv2_insurance_assert( 75.6 === $addons['data']['plans'][2]['pricing']['total_trip'], 'included extended add-ons were charged' );

$medical_query = $query;
$medical_query['medical_condition'] = true;
$transient_count_before = count( $GLOBALS['tv2_insurance_transients'] );
$medical = $repository->quote( $medical_query, false, false );
tv2_insurance_assert( 'medical_assessment_required' === $medical['data']['plans'][0]['eligibility']['status'], 'medical assessment safeguard is missing' );
tv2_insurance_assert( 'bypass_sensitive' === $medical['runtime']['cache_state'], 'medical assessment flags were not excluded from persistent cache' );
tv2_insurance_assert( $transient_count_before === count( $GLOBALS['tv2_insurance_transients'] ), 'sensitive quote created a transient' );

$age_query = $query;
$age_query['oldest_age'] = 65;
$age_adjusted = $repository->quote( $age_query );
tv2_insurance_assert( 53.76 === $age_adjusted['data']['plans'][0]['pricing']['total_trip'], 'age-factor demo calculation changed' );

$unsupported_query = $query;
$unsupported_query['destination'] = 'usa';
$unsupported = ( new Tra_Vel_V2_Demo_Insurance_Quote_Adapter() )->quote( $unsupported_query );
tv2_insurance_assert( is_wp_error( $unsupported ) && 'tra_vel_demo_insurance_destination_unsupported' === $unsupported->get_error_code(), 'demo adapter mislabeled another destination as Europe' );

class Tra_Vel_V2_Test_Insurance_Registry extends Tra_Vel_V2_Insurance_Quote_Registry {
	public $fail = false;
	public $degrade = false;
	public function get_cache_signature() { return 'test-insurance-registry-v1'; }
	public function resolve( $context ) {
		if ( $this->fail ) return new WP_Error( 'simulated_insurance_failure' );
		if ( $this->degrade ) return array( 'data' => array( 'data_mode' => 'demo', 'plans' => array( array( 'id' => 'fallback' ) ), 'query' => $context ), 'reports' => array( 'test-live' => array( 'healthy' => false ) ), 'degraded' => true, 'failed_adapters' => array( 'test-live' ) );
		return array( 'data' => array( 'data_mode' => 'live', 'plans' => array( array( 'id' => 'live-plan' ) ), 'query' => $context ), 'reports' => array( 'test-live' => array( 'healthy' => true ) ), 'degraded' => false, 'failed_adapters' => array() );
	}
}

$test_registry = new Tra_Vel_V2_Test_Insurance_Registry();
$test_repository = new Tra_Vel_V2_Insurance_Quote_Repository( $test_registry );
$live = $test_repository->quote( $query );
$test_registry->degrade = true;
$stale = $test_repository->quote( $query, true );
tv2_insurance_assert( 'miss' === $live['runtime']['cache_state'], 'live result was not cached' );
tv2_insurance_assert( 'stale_error' === $stale['runtime']['cache_state'], 'degraded supplier did not use stale live data' );
tv2_insurance_assert( 'insurance_supplier_degraded' === $stale['runtime']['fallback_error'], 'degraded provenance is missing' );
tv2_insurance_assert( 'live-plan' === $stale['data']['plans'][0]['id'], 'degraded refresh replaced valid live data' );

$variant = $query;
$variant['adults'] = 3;
$test_registry->degrade = false;
$fresh_variant = $test_repository->quote( $variant );
$test_registry->fail = true;
$failed_variant = $test_repository->quote( $variant, true );
tv2_insurance_assert( 'stale_error' === $failed_variant['runtime']['cache_state'], 'full supplier failure did not use stale data' );
tv2_insurance_assert( 'simulated_insurance_failure' === $failed_variant['runtime']['fallback_error'], 'supplier error code is missing' );
tv2_insurance_assert( 2 === $test_repository->purge(), 'cache generation did not increment' );

echo "Tra-Vel insurance runtime validation passed (pricing, underwriting, fresh cache, stale fallback, purge generation).\n";
