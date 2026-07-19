<?php
/**
 * Focused runtime checks for the deterministic generic commerce provider network.
 */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code, $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/[\x00-\x1F\x7F]/u', '', (string) $value ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

$commerce = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/';
require_once $commerce . 'class-tra-vel-commerce-taxonomy.php';
require_once $commerce . 'class-tra-vel-commerce-money.php';
require_once $commerce . 'class-tra-vel-commerce-policy.php';
require_once $commerce . 'class-tra-vel-commerce-sandbox-network.php';

$assertions = 0;
$temporary_files = array();

function commerce_network_cleanup() {
	global $temporary_files;
	foreach ( $temporary_files as $path ) {
		if ( is_string( $path ) && is_file( $path ) ) {
			unlink( $path );
		}
	}
	$temporary_files = array();
}

function commerce_network_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		commerce_network_cleanup();
		fwrite( STDERR, "Commerce sandbox network runtime failed: {$message}\n" );
		exit( 1 );
	}
}

function commerce_network_fixture_file( $value, $encode = true ) {
	global $temporary_files;
	$path = tempnam( sys_get_temp_dir(), 'tra-vel-commerce-network-' );
	commerce_network_assert( false !== $path, 'temporary fixture path must be created' );
	$contents = $encode ? wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : $value;
	commerce_network_assert( is_string( $contents ) && false !== file_put_contents( $path, $contents ), 'temporary fixture must be written' );
	$temporary_files[] = $path;
	return $path;
}

function commerce_network_find( $descriptors, $provider_id ) {
	foreach ( $descriptors as $descriptor ) {
		if ( $provider_id === $descriptor['provider_id'] ) {
			return $descriptor;
		}
	}
	return null;
}

$fixture_path = __DIR__ . '/../../plugin/tra-vel-agent-core/assets/fixtures/commerce-sandbox/provider-network.json';
$fixture = json_decode( file_get_contents( $fixture_path ), true );
commerce_network_assert( is_array( $fixture ) && JSON_ERROR_NONE === json_last_error(), 'bundled provider network fixture must be valid JSON' );

$network = new Tra_Vel_Commerce_Sandbox_Network();
commerce_network_assert( true === $network->load(), 'default bundled provider network must load' );
$descriptors = $network->all();
commerce_network_assert( is_array( $descriptors ) && 15 === count( $descriptors ), 'network must expose comparison depth plus an owned package/payment orchestrator across every vertical' );

$provider_ids = array_column( $descriptors, 'provider_id' );
foreach ( array( 'flight_supplier_a', 'flight_supplier_b', 'flight_supplier_c' ) as $flight_provider_id ) {
	commerce_network_assert( in_array( $flight_provider_id, $provider_ids, true ), "network must include {$flight_provider_id}" );
}

$covered_verticals = array();
$covered_capabilities = array();
foreach ( $descriptors as $descriptor ) {
	commerce_network_assert( ! is_wp_error( Tra_Vel_Commerce_Policy::provider_descriptor( $descriptor ) ), 'every returned descriptor must satisfy the current Commerce Core policy' );
	commerce_network_assert( 'sandbox' === $descriptor['environment'], 'every provider must remain in the sandbox environment' );
	commerce_network_assert( array( 'simulated' => true, 'real_charge' => false, 'real_booking' => false ) === $descriptor['commercial_truth'], 'every provider must preserve the simulated commercial truth boundary' );
	commerce_network_assert( array( 'configuration', 'status' ) === array_keys( $descriptor['readiness'] ), 'every provider must expose the closed readiness block' );
	commerce_network_assert( array( 'status', 'reference_digest' ) === array_keys( $descriptor['licence'] ), 'every provider must expose the closed licence block' );
	commerce_network_assert( array( 'model', 'commission_bps', 'currency', 'payout_lag_days' ) === array_keys( $descriptor['settlement'] ), 'every provider must expose the closed settlement block' );
	foreach ( $descriptor['verticals'] as $vertical ) {
		$covered_verticals[ $vertical ] = true;
	}
	foreach ( $descriptor['capabilities'] as $capability ) {
		$covered_capabilities[ $capability ] = true;
	}
	if ( 'affiliate' === $descriptor['relationship'] ) {
		commerce_network_assert( in_array( 'report_conversion', $descriptor['capabilities'], true ), 'affiliate conversion reporting must be declared separately' );
		commerce_network_assert( in_array( 'settlement_reconcile', $descriptor['capabilities'], true ), 'affiliate settlement reconciliation must remain a separate declared capability' );
	}
}

$actual_verticals = array_keys( $covered_verticals );
$expected_verticals = Tra_Vel_Commerce_Taxonomy::VERTICALS;
sort( $actual_verticals, SORT_STRING );
sort( $expected_verticals, SORT_STRING );
commerce_network_assert( $expected_verticals === $actual_verticals, 'network must cover every canonical commerce vertical exactly' );

$actual_capabilities = array_keys( $covered_capabilities );
$expected_capabilities = Tra_Vel_Commerce_Taxonomy::CAPABILITIES;
sort( $actual_capabilities, SORT_STRING );
sort( $expected_capabilities, SORT_STRING );
commerce_network_assert( $expected_capabilities === $actual_capabilities, 'network must demonstrate every canonical provider capability' );

$flight_a = commerce_network_find( $descriptors, 'flight_supplier_a' );
commerce_network_assert( is_array( $flight_a ) && in_array( 'confirm', $flight_a['capabilities'], true ) && in_array( 'fulfill', $flight_a['capabilities'], true ), 'confirmation and fulfillment must be distinct flight capabilities' );
commerce_network_assert( in_array( 'webhook', $flight_a['capabilities'], true ) && in_array( 'reconcile', $flight_a['capabilities'], true ), 'transactional flight provider must expose asynchronous event and reconciliation capabilities' );
$insurance = commerce_network_find( $descriptors, 'insurance_supplier_a' );
commerce_network_assert( is_array( $insurance ) && 'sandbox_only' === $insurance['licence']['status'], 'insurance fixture must preserve its regulated sandbox-only licence boundary' );

for ( $index = 1, $count = count( $descriptors ); $index < $count; $index++ ) {
	$previous = $descriptors[ $index - 1 ];
	$current  = $descriptors[ $index ];
	$correct_order = $previous['priority'] > $current['priority'] || ( $previous['priority'] === $current['priority'] && strcmp( $previous['provider_id'], $current['provider_id'] ) < 0 );
	commerce_network_assert( $correct_order, 'descriptors must use deterministic priority-descending and provider-ID ordering' );
}

$signature = $network->signature();
commerce_network_assert( is_string( $signature ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $signature ), 'network signature must be one SHA-256 digest' );
commerce_network_assert( '71add04dcf77505a1f475fbabf648b52841d695a2d3caa3683250cdeb1f9314c' === $signature, 'normalized bundled descriptor digest must remain deterministic' );
commerce_network_assert( $signature === $network->signature(), 'network signature must be stable across repeated reads' );

$reordered_fixture = $fixture;
$reordered_fixture['providers'] = array_reverse( $reordered_fixture['providers'] );
$reordered_network = new Tra_Vel_Commerce_Sandbox_Network( commerce_network_fixture_file( $reordered_fixture ) );
commerce_network_assert( $descriptors === $reordered_network->all(), 'fixture provider order must not alter deterministic descriptors' );
commerce_network_assert( $signature === $reordered_network->signature(), 'fixture provider order must not alter the network signature' );

$unknown_top_level = $fixture;
$unknown_top_level['unexpected'] = true;
$unknown_top_level_result = ( new Tra_Vel_Commerce_Sandbox_Network( commerce_network_fixture_file( $unknown_top_level ) ) )->all();
commerce_network_assert( is_wp_error( $unknown_top_level_result ) && 'tra_vel_commerce_sandbox_network_fixture_shape_invalid' === $unknown_top_level_result->get_error_code(), 'unknown top-level fixture fields must fail closed' );

$unknown_descriptor = $fixture;
$unknown_descriptor['providers'][0]['credential'] = 'must-not-pass';
$unknown_descriptor_result = ( new Tra_Vel_Commerce_Sandbox_Network( commerce_network_fixture_file( $unknown_descriptor ) ) )->all();
commerce_network_assert( is_wp_error( $unknown_descriptor_result ) && 'tra_vel_commerce_provider_shape_invalid' === $unknown_descriptor_result->get_error_code(), 'unknown descriptor fields must be rejected by Commerce Core policy' );

$duplicate_provider = $fixture;
$duplicate_provider['providers'][] = $duplicate_provider['providers'][0];
$duplicate_result = ( new Tra_Vel_Commerce_Sandbox_Network( commerce_network_fixture_file( $duplicate_provider ) ) )->all();
commerce_network_assert( is_wp_error( $duplicate_result ) && 'tra_vel_commerce_sandbox_network_provider_duplicate' === $duplicate_result->get_error_code(), 'duplicate provider IDs must fail closed' );

$live_provider = $fixture;
$live_provider['providers'][0]['environment'] = 'live';
$live_result = ( new Tra_Vel_Commerce_Sandbox_Network( commerce_network_fixture_file( $live_provider ) ) )->all();
commerce_network_assert( is_wp_error( $live_result ) && 'tra_vel_commerce_sandbox_network_provider_environment_invalid' === $live_result->get_error_code(), 'a live provider descriptor must not enter the sandbox network' );

$incomplete_network = $fixture;
$incomplete_network['providers'] = array_values(
	array_filter(
		$incomplete_network['providers'],
		function ( $descriptor ) {
			return 'equipment_supplier_a' !== $descriptor['provider_id'];
		}
	)
);
$incomplete_result = ( new Tra_Vel_Commerce_Sandbox_Network( commerce_network_fixture_file( $incomplete_network ) ) )->all();
commerce_network_assert( is_wp_error( $incomplete_result ) && 'tra_vel_commerce_sandbox_network_vertical_coverage_incomplete' === $incomplete_result->get_error_code(), 'missing canonical vertical coverage must fail closed' );

$malformed_result = ( new Tra_Vel_Commerce_Sandbox_Network( commerce_network_fixture_file( '{not-json', false ) ) )->all();
commerce_network_assert( is_wp_error( $malformed_result ) && 'tra_vel_commerce_sandbox_network_fixture_json_invalid' === $malformed_result->get_error_code(), 'malformed fixture JSON must fail closed' );
$missing_result = ( new Tra_Vel_Commerce_Sandbox_Network( __DIR__ . '/does-not-exist-provider-network.json' ) )->all();
commerce_network_assert( is_wp_error( $missing_result ) && 'tra_vel_commerce_sandbox_network_fixture_unreadable' === $missing_result->get_error_code(), 'missing fixture files must fail closed' );

commerce_network_cleanup();
echo "Commerce sandbox provider network passed ({$assertions} assertions; signature {$signature}).\n";
