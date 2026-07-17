<?php
/**
 * Minimal deterministic harness for the supplier registry and cache fallback.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_VERSION', 'test' );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );
define( 'TRA_VEL_V2_URI', 'https://tra-vel.test/wp-content/themes/tra-vel-v2' );

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

class WP_REST_Controller {
	public $namespace;
	public $rest_base;
}

class WP_REST_Response {
	public $data;
	public $status;
	public $headers = array();
	public $links   = array();
	public function __construct( $data, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
	public function header( $name, $value ) {
		$this->headers[ $name ] = $value;
	}
	public function add_link( $rel, $href ) {
		$this->links[ $rel ] = $href;
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
function wp_parse_url( $url ) {
	return parse_url( $url );
}
function esc_url_raw( $url ) {
	return filter_var( $url, FILTER_SANITIZE_URL );
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
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, $decimals, '.', ',' );
}
function home_url( $path = '' ) {
	return 'https://tra-vel.test' . $path;
}
function rest_url( $path = '' ) {
	return 'https://tra-vel.test/wp-json/' . ltrim( $path, '/' );
}
function add_action( $hook, $callback ) {
	unset( $hook, $callback );
}

require TRA_VEL_V2_PATH . '/inc/suppliers/bootstrap.php';
require TRA_VEL_V2_PATH . '/inc/discovery.php';

function tv2_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Supplier runtime validation failed: {$message}\n" );
		exit( 1 );
	}
}

class Tra_Vel_V2_Test_Discovery_Request {
	private $params;
	public function __construct( $params ) {
		$this->params = $params;
	}
	public function get_param( $key ) {
		return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
	}
}

class Tra_Vel_V2_Test_Discovery_Controller extends Tra_Vel_V2_Discovery_Controller {
	public function __construct( $repository ) {
		$this->namespace  = 'tra-vel/v2';
		$this->rest_base  = 'discovery';
		$this->repository = $repository;
	}
}

function tv2_discovery_request( $controller, $overrides = array() ) {
	$params = array_merge(
		array(
			'budget'      => 5000,
			'destination' => '',
			'focus'       => '',
			'direct'      => false,
			'q'           => '',
			'sort'        => 'smart',
			'trip'        => 'all',
			'max_stops'   => 3,
			'max_duration' => 3000,
			'allow_overnight' => false,
			'limit'       => 24,
			'layer'       => 'deals',
		),
		$overrides
	);
	return $controller->get_items( new Tra_Vel_V2_Test_Discovery_Request( $params ) );
}

function tv2_find_destination( $response, $destination_id ) {
	foreach ( $response->data['destinations'] as $destination ) {
		if ( $destination_id === $destination['id'] ) {
			return $destination;
		}
	}
	return null;
}

$repository = new Tra_Vel_V2_Discovery_Repository();
$first      = $repository->get();
$second     = $repository->get();

tv2_assert( ! is_wp_error( $first ), 'demo repository returned an error' );
tv2_assert( 'miss' === $first['runtime']['cache_state'], 'first request must be a cache miss' );
tv2_assert( 'fresh' === $second['runtime']['cache_state'], 'second request must use fresh cache' );
tv2_assert( 6 === count( $first['data']['destinations'] ), 'demo destination count changed' );
tv2_assert( 'demo' === $first['data']['data_mode'], 'demo adapter must remain demo mode' );
tv2_assert( false === $first['data']['field_provenance']['deals']['live'], 'editorial deal values were certified as live' );
tv2_assert( false === $first['data']['field_provenance']['weather_current']['live'], 'editorial weather values were certified as live' );
tv2_assert( true === $first['runtime']['adapters']['curated_demo']['healthy'], 'demo adapter is not healthy' );
tv2_assert( false === $first['runtime']['adapters']['open_meteo_commercial']['configured'], 'commercial weather adapter must be opt-in' );

$discovery_controller = new Tra_Vel_V2_Test_Discovery_Controller( $repository );
$bangkok_response      = tv2_discovery_request( $discovery_controller, array( 'destination' => 'bangkok' ) );
$bangkok_overnight     = tv2_discovery_request( $discovery_controller, array( 'destination' => 'bangkok', 'allow_overnight' => true ) );
$budapest_response     = tv2_discovery_request( $discovery_controller, array( 'destination' => 'budapest' ) );
$demo_budget_response  = tv2_discovery_request( $discovery_controller, array( 'budget' => 550 ) );
$long_trip_response    = tv2_discovery_request( $discovery_controller, array( 'trip' => 'long' ) );
$short_trip_response   = tv2_discovery_request( $discovery_controller, array( 'trip' => 'short' ) );
$excluded_response     = tv2_discovery_request( $discovery_controller, array( 'destination' => 'bangkok', 'trip' => 'short' ) );
$limited_response      = tv2_discovery_request( $discovery_controller, array( 'destination' => 'bangkok', 'sort' => 'price', 'limit' => 1 ) );
$comfort_response      = tv2_discovery_request( $discovery_controller, array( 'destination' => 'bangkok', 'sort' => 'comfort' ) );
$focused_response      = tv2_discovery_request( $discovery_controller, array( 'focus' => 'tokyo', 'sort' => 'price', 'limit' => 3 ) );
$focus_precedence      = tv2_discovery_request( $discovery_controller, array( 'destination' => 'bangkok', 'focus' => 'tokyo' ) );
$empty_response        = tv2_discovery_request( $discovery_controller, array( 'q' => 'no-such-destination' ) );
$collection_params     = $discovery_controller->get_collection_params();
$bangkok_destination   = tv2_find_destination( $bangkok_response, 'bangkok' );
$budapest_destination  = tv2_find_destination( $bangkok_response, 'budapest' );
$athens_destination    = tv2_find_destination( $bangkok_response, 'athens' );
$tokyo_destination     = tv2_find_destination( $bangkok_response, 'tokyo' );

tv2_assert( $bangkok_response instanceof WP_REST_Response, 'discovery controller did not return a REST response' );
tv2_assert( false === $bangkok_response->data['field_provenance']['deals']['live'], 'REST response certified editorial deal values as live' );
tv2_assert( false === $bangkok_response->data['field_provenance']['routes']['live'], 'REST response certified editorial route values as live' );
tv2_assert( 'bangkok' === $bangkok_response->data['meta']['selected_destination'], 'requested visible destination was not selected' );
tv2_assert( 2 === count( $bangkok_response->data['routes'] ), 'default route filters did not exclude the overnight option' );
tv2_assert( array( 'bangkok' ) === array_values( array_unique( array_column( $bangkok_response->data['routes'], 'destination_id' ) ) ), 'returned routes are not explicitly bound to Bangkok' );
tv2_assert( 3 === count( $bangkok_overnight->data['routes'] ), 'explicit overnight permission did not restore the overnight option' );
tv2_assert( 'budapest' === $budapest_response->data['meta']['selected_destination'], 'Budapest selection was replaced by another visible destination' );
tv2_assert( array() === $budapest_response->data['routes'], 'routes from another destination leaked into Budapest' );
tv2_assert( 550 === $demo_budget_response->data['meta']['filters']['budget'], 'demo response did not echo the requested budget' );
tv2_assert( false === $demo_budget_response->data['meta']['filters']['budget_applied'], 'demo prices silently activated monetary filtering' );
tv2_assert( 6 === count( $demo_budget_response->data['destinations'] ), 'demo budget excluded editorial destinations' );
tv2_assert( array( 'bangkok', 'lisbon', 'tokyo' ) === array_column( $long_trip_response->data['destinations'], 'id' ), 'long-trip intent returned the wrong destination set' );
tv2_assert( array( 'budapest', 'dubai', 'athens' ) === array_column( $short_trip_response->data['destinations'], 'id' ), 'short-trip intent returned the wrong destination set' );
tv2_assert( null === $excluded_response->data['meta']['selected_destination'], 'an explicitly filtered destination was silently replaced' );
tv2_assert( array() === $excluded_response->data['destinations'] && array() === $excluded_response->data['routes'], 'an explicitly filtered destination did not return a truthful empty state' );
tv2_assert( 'bangkok' === $limited_response->data['meta']['selected_destination'], 'sorting and limit replaced an explicit visible destination' );
tv2_assert( array( 'bangkok' ) === array_column( $limited_response->data['destinations'], 'id' ), 'explicit destination did not survive the result limit' );
tv2_assert( $comfort_response->data['routes'][0]['id'] === $comfort_response->data['recommended']['id'], 'recommended route does not match comfort ordering' );
tv2_assert( 'bangkok-direct' === $comfort_response->data['recommended']['id'], 'comfort ordering did not recommend the direct route' );
tv2_assert( 'tokyo' === $focused_response->data['meta']['selected_destination'], 'transient focus did not preserve the active globe destination' );
tv2_assert( 3 === count( $focused_response->data['destinations'] ), 'transient focus changed the requested global result limit' );
tv2_assert( in_array( 'tokyo', array_column( $focused_response->data['destinations'], 'id' ), true ), 'transient focus did not keep the active globe destination visible' );
tv2_assert( array() === $focused_response->data['routes'], 'transient focus leaked routes from another destination' );
tv2_assert( 'bangkok' === $focus_precedence->data['meta']['selected_destination'], 'transient focus overrode an explicit destination' );
tv2_assert( array( 'bangkok' ) === array_values( array_unique( array_column( $focus_precedence->data['routes'], 'destination_id' ) ) ), 'focus precedence leaked routes from another destination' );
tv2_assert( null === $empty_response->data['meta']['selected_destination'], 'zero-result discovery retained a stale selected destination' );
tv2_assert( array() === $empty_response->data['routes'] && null === $empty_response->data['recommended'], 'zero-result discovery retained stale route state' );
tv2_assert( 'rest_validate_request_arg' === $collection_params['direct']['validate_callback'], 'direct boolean lacks strict REST validation' );
tv2_assert( 'rest_validate_request_arg' === $collection_params['allow_overnight']['validate_callback'], 'overnight boolean lacks strict REST validation' );
tv2_assert( 'sanitize_key' === $collection_params['focus']['sanitize_callback'], 'focus parameter lacks key sanitization' );
tv2_assert( 'rest_validate_request_arg' === $collection_params['focus']['validate_callback'], 'focus parameter lacks strict REST validation' );
tv2_assert( 'rest_validate_request_arg' === $collection_params['destination']['validate_callback'], 'destination parameter lacks strict REST validation' );
tv2_assert( 'rest_validate_request_arg' === $collection_params['q']['validate_callback'], 'query parameter lacks strict REST validation' );
tv2_assert( is_array( $bangkok_destination ) && 'https://tra-vel.test/destinations/thailand/' === $bangkok_destination['url'], 'Bangkok did not resolve to the published Thailand guide' );
tv2_assert( is_array( $budapest_destination ) && 'https://tra-vel.test/destinations/budapest/' === $budapest_destination['url'], 'Budapest did not resolve to its canonical guide' );
tv2_assert( is_array( $athens_destination ) && 'https://tra-vel.test/destinations/athens/' === $athens_destination['url'], 'Athens did not resolve to its canonical guide' );
tv2_assert( is_array( $tokyo_destination ) && 'https://tra-vel.test/destinations/' === $tokyo_destination['url'], 'unpublished destination emitted a faceted guide URL' );
tv2_assert( 'destination_deal' === $bangkok_destination['deal']['total_scope'], 'destination total lacks a truthful scope' );
tv2_assert( 'דורש חיפוש חבילה חי' === $bangkok_destination['deal']['total_formatted'], 'destination deal amount can be presented as a package-inclusive total' );

class Tra_Vel_V2_Test_Context_Registry extends Tra_Vel_V2_Supplier_Registry {
	public $contexts = array();
	public function __construct() {}
	public function get_cache_signature() {
		return 'context-registry-v1';
	}
	public function resolve( $context ) {
		$this->contexts[] = $context;
		$data             = json_decode( (string) file_get_contents( TRA_VEL_V2_PATH . '/assets/data/discovery-demo.json' ), true );
		$data['data_mode'] = 'live';
		$data['provider_status']['flights']['connected'] = true;
		$data['provider_status']['hotels']['connected']  = true;
		$data['field_provenance'] = array(
			'deals' => array( 'live' => true, 'source' => 'context-test', 'observed_at' => '2029-01-01T00:00:00Z' ),
		);
		return array(
			'data'            => $data,
			'reports'         => array( 'context-test' => array( 'healthy' => true ) ),
			'degraded'        => false,
			'failed_adapters' => array(),
		);
	}
}

$context_registry   = new Tra_Vel_V2_Test_Context_Registry();
$context_repository = new Tra_Vel_V2_Discovery_Repository( $context_registry );
$context_controller = new Tra_Vel_V2_Test_Discovery_Controller( $context_repository );
$context_filters    = array(
	'budget'          => 2000,
	'destination'     => 'bangkok',
	'sort'            => 'comfort',
	'trip'            => 'long',
	'max_stops'       => 1,
	'max_duration'    => 960,
	'allow_overnight' => false,
);
$context_first      = tv2_discovery_request( $context_controller, $context_filters );
$context_repeat     = tv2_discovery_request( $context_controller, $context_filters );
$context_overnight  = tv2_discovery_request( $context_controller, array_merge( $context_filters, array( 'allow_overnight' => true ) ) );
$live_budget        = tv2_discovery_request( $context_controller, array( 'budget' => 550 ) );

tv2_assert( 3 === count( $context_registry->contexts ), 'new supplier filters collided in the discovery cache' );
tv2_assert( 'miss' === $context_first->data['meta']['cache_state'] && 'fresh' === $context_repeat->data['meta']['cache_state'], 'identical supplier context did not reuse cache' );
tv2_assert( 'miss' === $context_overnight->data['meta']['cache_state'], 'overnight preference did not create a cache variant' );
tv2_assert( 'long' === $context_registry->contexts[0]['trip'], 'trip intent did not reach the supplier adapter' );
tv2_assert( 1 === $context_registry->contexts[0]['max_stops'], 'maximum stops did not reach the supplier adapter' );
tv2_assert( 960 === $context_registry->contexts[0]['max_duration'], 'maximum duration did not reach the supplier adapter' );
tv2_assert( false === $context_registry->contexts[0]['allow_overnight'] && true === $context_registry->contexts[1]['allow_overnight'], 'overnight preference was not normalized in supplier context' );
tv2_assert( true === $live_budget->data['meta']['filters']['budget_applied'], 'live supplier budget was not applied' );
tv2_assert( array( 'athens' ) === array_column( $live_budget->data['destinations'], 'id' ), 'live budget filter returned the wrong destination set' );

$focus_context_count = count( $context_registry->contexts );
$context_focus_tokyo = tv2_discovery_request( $context_controller, array( 'focus' => 'tokyo' ) );
$context_focus_lisbon = tv2_discovery_request( $context_controller, array( 'focus' => 'lisbon' ) );
tv2_assert( $focus_context_count + 1 === count( $context_registry->contexts ), 'transient focus created a supplier cache variant' );
tv2_assert( 'tokyo' === $context_focus_tokyo->data['meta']['selected_destination'], 'cached discovery data lost Tokyo focus' );
tv2_assert( 'lisbon' === $context_focus_lisbon->data['meta']['selected_destination'], 'cached discovery data lost Lisbon focus' );

class Tra_Vel_V2_Test_Weather_Mixed_Registry extends Tra_Vel_V2_Supplier_Registry {
	public function get_cache_signature() {
		return 'weather-mixed-registry-v1';
	}
	public function resolve( $context ) {
		unset( $context );
		$data = json_decode( (string) file_get_contents( TRA_VEL_V2_PATH . '/assets/data/discovery-demo.json' ), true );
		$data['data_mode'] = 'mixed';
		$data['provider_status']['weather']['connected'] = true;
		return array(
			'data'            => $data,
			'reports'         => array( 'weather-test' => array( 'healthy' => true ) ),
			'degraded'        => false,
			'failed_adapters' => array(),
		);
	}
}

$weather_mixed_controller = new Tra_Vel_V2_Test_Discovery_Controller(
	new Tra_Vel_V2_Discovery_Repository( new Tra_Vel_V2_Test_Weather_Mixed_Registry() )
);
$weather_mixed_budget = tv2_discovery_request( $weather_mixed_controller, array( 'budget' => 550, 'layer' => 'weather' ) );
tv2_assert( false === $weather_mixed_budget->data['meta']['filters']['budget_applied'], 'live weather activated hidden editorial price filtering' );
tv2_assert( 6 === count( $weather_mixed_budget->data['destinations'] ), 'live weather excluded destinations using hidden editorial prices' );

class Tra_Vel_V2_Test_Field_Provenance_Adapter implements Tra_Vel_V2_Supplier_Adapter {
	private $with_current;
	public function __construct( $with_current ) { $this->with_current = $with_current; }
	public function get_id() { return $this->with_current ? 'current_weather_test' : 'provider_flag_only_test'; }
	public function get_verticals() { return array( 'weather' ); }
	public function is_configured() { return true; }
	public function get_mode() { return 'live'; }
	public function get_cache_version() { return $this->with_current ? 'current-v1' : 'flag-v1'; }
	public function fetch( $context ) {
		unset( $context );
		$fragment = array(
			'provider_status' => array(
				'weather' => array( 'connected' => true, 'observed_at' => '2029-04-05T12:00:00Z' ),
			),
		);
		if ( $this->with_current ) {
			$fragment['destinations'] = array(
				array( 'id' => 'bangkok', 'weather' => array( 'temperature_c' => 31, 'condition' => 'בהיר' ) ),
			);
		}
		return $fragment;
	}
}

$flag_only_registry = new Tra_Vel_V2_Supplier_Registry();
$flag_only_registry->register( new Tra_Vel_V2_Test_Field_Provenance_Adapter( false ) );
$flag_only = $flag_only_registry->resolve( array() );
tv2_assert( false === $flag_only['data']['field_provenance']['weather_current']['live'], 'provider connection alone certified inherited weather values' );
tv2_assert( 'demo' === $flag_only['data']['data_mode'], 'provider connection alone changed the response data mode' );

$current_weather_registry = new Tra_Vel_V2_Supplier_Registry();
$current_weather_registry->register( new Tra_Vel_V2_Test_Field_Provenance_Adapter( true ) );
$current_weather = $current_weather_registry->resolve( array() );
tv2_assert( true === $current_weather['data']['field_provenance']['weather_current']['live'], 'live current conditions did not receive field provenance' );
tv2_assert( false === $current_weather['data']['field_provenance']['weather_season']['live'], 'editorial season fit inherited live weather provenance' );
tv2_assert( 'mixed' === $current_weather['data']['data_mode'], 'partial live field coverage was not labeled mixed' );
tv2_assert( 'current_weather_test' === $current_weather['data']['field_provenance']['weather_current']['source'], 'live weather provenance lost its source' );
tv2_assert( '2029-04-05T12:00:00Z' === $current_weather['data']['field_provenance']['weather_current']['observed_at'], 'live weather provenance lost its observation time' );

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

echo "Tra-Vel supplier runtime validation passed (field provenance, filter context/cache, canonical guides, price truth, destination-route invariants, stale fallback, purge generation).\n";
