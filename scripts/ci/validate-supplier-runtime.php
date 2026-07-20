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
	private $message;
	private $data;
	public function __construct( $code, $message = '', $data = array() ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_data() {
		return $this->data;
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
function rest_ensure_response( $value ) {
	return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value );
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

function tv2_utc( $offset_seconds = 0 ) {
	return gmdate( 'Y-m-d\TH:i:s\Z', time() + (int) $offset_seconds );
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

function tv2_find_contract_destination( $contract, $destination_id ) {
	foreach ( isset( $contract['destinations'] ) ? (array) $contract['destinations'] : array() as $destination ) {
		if ( isset( $destination['id'] ) && $destination_id === $destination['id'] ) {
			return $destination;
		}
	}
	return null;
}

function tv2_find_plan_module( $response, $module_id ) {
	if ( empty( $response->data['selected_plan']['modules'] ) ) {
		return null;
	}
	foreach ( $response->data['selected_plan']['modules'] as $module ) {
		if ( $module_id === $module['id'] ) {
			return $module;
		}
	}
	return null;
}

function tv2_find_map_entity( $response, $destination_id ) {
	if ( empty( $response->data['map_entities'] ) ) {
		return null;
	}
	foreach ( $response->data['map_entities'] as $entity ) {
		if ( isset( $entity['destination_id'] ) && $destination_id === $entity['destination_id'] ) {
			return $entity;
		}
	}
	return null;
}

$repository = new Tra_Vel_V2_Discovery_Repository();
$first      = $repository->get();
$second     = $repository->get();

tv2_assert( ! is_wp_error( $first ), 'demo repository returned an error' );
$canonical_destination_count = isset( $first['data']['destinations'] ) && is_array( $first['data']['destinations'] ) ? count( $first['data']['destinations'] ) : 0;
$canonical_destination_ids   = $canonical_destination_count ? array_column( $first['data']['destinations'], 'id' ) : array();
$canonical_exploration_hubs  = isset( $first['data']['exploration_hubs'] ) && is_array( $first['data']['exploration_hubs'] ) ? $first['data']['exploration_hubs'] : array();
$canonical_hub_ids           = $canonical_exploration_hubs ? array_column( $canonical_exploration_hubs, 'id' ) : array();
tv2_assert( 'miss' === $first['runtime']['cache_state'], 'first request must be a cache miss' );
tv2_assert( 'fresh' === $second['runtime']['cache_state'], 'second request must use fresh cache' );
tv2_assert( 0 < $canonical_destination_count, 'canonical discovery contract must expose at least one destination' );
tv2_assert( $canonical_destination_count === count( array_unique( $canonical_destination_ids ) ), 'canonical discovery destination ids must remain unique as the collection expands' );
tv2_assert( count( $canonical_exploration_hubs ) >= 30 && count( $canonical_exploration_hubs ) <= 80, 'canonical discovery must expose the bounded global exploration-hub layer' );
tv2_assert( count( $canonical_exploration_hubs ) === count( array_unique( $canonical_hub_ids ) ), 'canonical exploration-hub ids must remain unique' );
tv2_assert( ! array_intersect( $canonical_destination_ids, $canonical_hub_ids ), 'exploration hubs must not shadow destination ids' );
tv2_assert( 'demo' === $first['data']['data_mode'], 'demo adapter must remain demo mode' );
tv2_assert( false === $first['data']['field_provenance']['deals']['live'], 'editorial deal values were certified as live' );
tv2_assert( false === $first['data']['field_provenance']['weather_current']['live'], 'editorial weather values were certified as live' );
tv2_assert( true === $first['runtime']['adapters']['curated_demo']['healthy'], 'demo adapter is not healthy' );
tv2_assert( false === $first['runtime']['adapters']['open_meteo_commercial']['configured'], 'commercial weather adapter must be opt-in' );

$discovery_controller = new Tra_Vel_V2_Test_Discovery_Controller( $repository );
$bangkok_response      = tv2_discovery_request( $discovery_controller, array( 'destination' => 'bangkok' ) );
$bangkok_hotels        = tv2_discovery_request( $discovery_controller, array( 'destination' => 'bangkok', 'layer' => 'hotels' ) );
$bangkok_airports      = tv2_discovery_request( $discovery_controller, array( 'destination' => 'bangkok', 'layer' => 'airports' ) );
$bangkok_weather       = tv2_discovery_request( $discovery_controller, array( 'destination' => 'bangkok', 'layer' => 'weather' ) );
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
$item_schema           = $discovery_controller->get_item_schema();
$contract_schema_response = $discovery_controller->get_contract_schema();
$bangkok_destination   = tv2_find_destination( $bangkok_response, 'bangkok' );
$budapest_destination  = tv2_find_destination( $bangkok_response, 'budapest' );
$athens_destination    = tv2_find_destination( $bangkok_response, 'athens' );
$tokyo_destination     = tv2_find_destination( $bangkok_response, 'tokyo' );
$bangkok_deal_entity   = tv2_find_map_entity( $bangkok_response, 'bangkok' );
$bangkok_hotel_entity  = tv2_find_map_entity( $bangkok_hotels, 'bangkok' );
$bangkok_airport_entity = tv2_find_map_entity( $bangkok_airports, 'bangkok' );
$bangkok_weather_entity = tv2_find_map_entity( $bangkok_weather, 'bangkok' );

tv2_assert( $bangkok_response instanceof WP_REST_Response, 'discovery controller did not return a REST response' );
tv2_assert( $contract_schema_response instanceof WP_REST_Response && '1.3.0' === $contract_schema_response->data['properties']['contract_version']['const'], 'discovery schema endpoint did not expose the versioned typed-map contract' );
tv2_assert( isset( $contract_schema_response->data['definitions']['mapEntity'], $contract_schema_response->data['definitions']['mapSegment'], $contract_schema_response->data['properties']['airport_registry'] ), 'discovery schema endpoint omitted typed map definitions or airport registry' );
tv2_assert( isset( $contract_schema_response->data['definitions']['explorationHub'], $contract_schema_response->data['properties']['exploration_hubs'] ), 'discovery schema endpoint omitted its closed exploration-hub contract' );
tv2_assert( false === $bangkok_response->data['field_provenance']['deals']['live'], 'REST response certified editorial deal values as live' );
tv2_assert( false === $bangkok_response->data['field_provenance']['routes']['live'], 'REST response certified editorial route values as live' );
tv2_assert( 'bangkok' === $bangkok_response->data['meta']['selected_destination'], 'requested visible destination was not selected' );
tv2_assert( 2 === count( $bangkok_response->data['routes'] ), 'default route filters did not exclude the overnight option' );
tv2_assert( array( 'bangkok' ) === array_values( array_unique( array_column( $bangkok_response->data['routes'], 'destination_id' ) ) ), 'returned routes are not explicitly bound to Bangkok' );
tv2_assert( '1.3.0' === $bangkok_response->data['meta']['contract_version'] && '1.3.0' === $bangkok_response->data['selected_plan']['contract_version'], 'typed map contract version did not reach the response and selected plan' );
tv2_assert( count( $canonical_exploration_hubs ) === count( $bangkok_response->data['exploration_hubs'] ), 'the REST response lost exploration hubs while filtering a destination' );
tv2_assert( $canonical_destination_count === count( $bangkok_response->data['map_entities'] ), 'active deal layer did not expose one typed entity per filtered destination' );
tv2_assert( array( 'deal' ) === array_values( array_unique( array_column( $bangkok_response->data['map_entities'], 'kind' ) ) ), 'deal layer mixed entity kinds' );
tv2_assert( is_array( $bangkok_deal_entity ) && 'demo' === $bangkok_deal_entity['data_mode'] && 'planning' === $bangkok_deal_entity['truth_state'] && 'current' === $bangkok_deal_entity['freshness'], 'demo deal entity was not explicitly labeled as current planning data' );
tv2_assert( 1347.0 === $bangkok_deal_entity['price']['amount'] && 'per_person_total' === $bangkok_deal_entity['price']['basis'] && 'planning' === $bangkok_deal_entity['price']['state'] && false === $bangkok_deal_entity['price']['bookable'], 'demo deal price lost its planning/non-bookable boundary' );
tv2_assert( 'search_packages' === $bangkok_deal_entity['action']['type'] && true === $bangkok_deal_entity['action']['requires_live_search'], 'deal entity does not hand off to a live package search' );
tv2_assert( null === $bangkok_deal_entity['provenance']['observed_at'] && null === $bangkok_deal_entity['provenance']['retrieved_at'] && '2026-07-17' === $bangkok_deal_entity['provenance']['reviewed_on'], 'demo deal entity invented supplier observation provenance' );
tv2_assert( is_array( $bangkok_hotel_entity ) && 'hotel_area' === $bangkok_hotel_entity['kind'] && 'search_hotels' === $bangkok_hotel_entity['action']['type'], 'hotel layer did not expose a typed hotel-area action' );
tv2_assert( 65.0 === $bangkok_hotel_entity['price']['amount'] && 'per_night' === $bangkok_hotel_entity['price']['basis'] && false === $bangkok_hotel_entity['price']['bookable'], 'hotel-area planning price is not explicitly per-night and non-bookable' );
tv2_assert( is_array( $bangkok_airport_entity ) && 'airport' === $bangkok_airport_entity['kind'] && null === $bangkok_airport_entity['price'] && 'compare_routes' === $bangkok_airport_entity['action']['type'], 'airport layer did not expose a price-free route-comparison entity' );
tv2_assert( 13.69 === $bangkok_airport_entity['lat'] && 100.7501 === $bangkok_airport_entity['lng'], 'airport entity used city-center coordinates instead of the canonical BKK point' );
tv2_assert( is_array( $bangkok_weather_entity ) && 'weather' === $bangkok_weather_entity['kind'] && null === $bangkok_weather_entity['price'] && 'plan_for_weather' === $bangkok_weather_entity['action']['type'], 'weather layer did not expose a price-free planning entity' );
$bangkok_deal_action_query = array();
$bangkok_hotel_action_query = array();
$bangkok_airport_action_query = array();
$bangkok_weather_action_query = array();
parse_str( (string) parse_url( $bangkok_deal_entity['action']['href'], PHP_URL_QUERY ), $bangkok_deal_action_query );
parse_str( (string) parse_url( $bangkok_hotel_entity['action']['href'], PHP_URL_QUERY ), $bangkok_hotel_action_query );
parse_str( (string) parse_url( $bangkok_airport_entity['action']['href'], PHP_URL_QUERY ), $bangkok_airport_action_query );
parse_str( (string) parse_url( $bangkok_weather_entity['action']['href'], PHP_URL_QUERY ), $bangkok_weather_action_query );
tv2_assert( 'BKK' === $bangkok_deal_action_query['destination'], 'deal marker did not hand off the airport-code destination required by package search' );
tv2_assert( 'BKK' === $bangkok_hotel_action_query['destination'] && 'Siam' === $bangkok_hotel_action_query['area'], 'hotel-area marker lost its airport code or selected area before hotel search' );
tv2_assert( 'BKK' === $bangkok_airport_action_query['destination'], 'airport marker did not preserve its canonical airport code for route comparison' );
tv2_assert( 'bangkok' === $bangkok_weather_action_query['destination'] && 'destination' === $bangkok_weather_action_query['mode'], 'weather marker did not retain its destination planning context' );
tv2_assert( 2 === count( $bangkok_response->data['map_segments'] ), 'one-stop recommended route did not produce exactly two map segments' );
tv2_assert( array( 1, 2 ) === array_column( $bangkok_response->data['map_segments'], 'sequence' ), 'route segment sequence is not contiguous and one-based' );
tv2_assert( array( 'bangkok-dubai' ) === array_values( array_unique( array_column( $bangkok_response->data['map_segments'], 'route_id' ) ) ), 'map segments are not bound to the recommended route' );
tv2_assert( 'TLV' === $bangkok_response->data['map_segments'][0]['from']['code'] && 'DXB' === $bangkok_response->data['map_segments'][0]['to']['code'] && 'DXB' === $bangkok_response->data['map_segments'][1]['from']['code'] && 'BKK' === $bangkok_response->data['map_segments'][1]['to']['code'], 'recommended route geometry did not follow TLV-DXB-BKK' );
tv2_assert( 32.0114 === $bangkok_response->data['map_segments'][0]['from']['lat'] && 34.8867 === $bangkok_response->data['map_segments'][0]['from']['lng'] && 25.2532 === $bangkok_response->data['map_segments'][0]['to']['lat'] && 55.3657 === $bangkok_response->data['map_segments'][0]['to']['lng'], 'map segment endpoints lost canonical airport coordinates' );
tv2_assert( 'demo' === $bangkok_response->data['map_segments'][0]['truth']['data_mode'] && 'planning' === $bangkok_response->data['map_segments'][0]['truth']['truth_state'] && false === $bangkok_response->data['map_segments'][0]['truth']['bookable'], 'demo map segment was promoted beyond planning state' );
tv2_assert( 3 === count( $bangkok_overnight->data['routes'] ), 'explicit overnight permission did not restore the overnight option' );
tv2_assert( 'budapest' === $budapest_response->data['meta']['selected_destination'], 'Budapest selection was replaced by another visible destination' );
tv2_assert( 2 === count( $budapest_response->data['routes'] ), 'Budapest does not expose its two truthful route candidates' );
tv2_assert( array( 'budapest' ) === array_values( array_unique( array_column( $budapest_response->data['routes'], 'destination_id' ) ) ), 'routes from another destination leaked into Budapest' );
tv2_assert( 'bangkok' === $bangkok_response->data['selected_plan']['destination_id'], 'selected 360 plan is not bound to Bangkok' );
tv2_assert( 12 === $bangkok_response->data['selected_plan']['coverage']['module_count'], 'selected 360 plan does not map all twelve decision areas' );
tv2_assert( 12 === count( $bangkok_response->data['selected_plan']['modules'] ), 'selected 360 plan module list is incomplete' );
tv2_assert( 'needs_search' === $bangkok_response->data['selected_plan']['cost_ledger']['state'], 'demo cost ledger was promoted to live state' );
tv2_assert( null === $bangkok_response->data['selected_plan']['cost_ledger']['total'], 'demo cost ledger exposed an unsupported total' );
tv2_assert( null === $bangkok_response->data['selected_plan']['cost_ledger']['savings'], 'demo cost ledger exposed unsupported savings' );
tv2_assert( ! array_filter( $bangkok_response->data['selected_plan']['cost_ledger']['line_items'], static function ( $item ) { return null !== $item['amount']; } ), 'demo cost ledger leaked sample component prices' );
tv2_assert( 550 === $demo_budget_response->data['meta']['filters']['budget'], 'demo response did not echo the requested budget' );
tv2_assert( false === $demo_budget_response->data['meta']['filters']['budget_applied'], 'demo prices silently activated monetary filtering' );
tv2_assert( $canonical_destination_count === count( $demo_budget_response->data['destinations'] ), 'demo budget excluded editorial destinations' );
// Smart discovery uses traveler friction, not unsourced trend or savings claims: direct service first,
// then shorter flight and airport transfer, with destination id only as a deterministic final tie-breaker.
tv2_assert( array( 'bangkok', 'lisbon', 'tokyo' ) === array_column( $long_trip_response->data['destinations'], 'id' ), 'long-trip smart order did not follow direct-service and travel-time friction' );
tv2_assert( array( 'larnaca', 'athens', 'dubai', 'budapest', 'vienna', 'prague' ) === array_column( $short_trip_response->data['destinations'], 'id' ), 'short-trip smart order did not follow direct-service and travel-time friction' );
tv2_assert( null === $excluded_response->data['meta']['selected_destination'], 'an explicitly filtered destination was silently replaced' );
tv2_assert( array() === $excluded_response->data['destinations'] && array() === $excluded_response->data['routes'], 'an explicitly filtered destination did not return a truthful empty state' );
tv2_assert( 'bangkok' === $limited_response->data['meta']['selected_destination'], 'sorting and limit replaced an explicit visible destination' );
tv2_assert( array( 'bangkok' ) === array_column( $limited_response->data['destinations'], 'id' ), 'explicit destination did not survive the result limit' );
tv2_assert( $comfort_response->data['routes'][0]['id'] === $comfort_response->data['recommended']['id'], 'recommended route does not match comfort ordering' );
tv2_assert( 'bangkok-direct' === $comfort_response->data['recommended']['id'], 'comfort ordering did not recommend the direct route' );
tv2_assert( 1 === count( $comfort_response->data['map_segments'] ) && 'TLV' === $comfort_response->data['map_segments'][0]['from']['code'] && 'BKK' === $comfort_response->data['map_segments'][0]['to']['code'], 'direct recommended route did not collapse to one exact segment' );
tv2_assert( 'tokyo' === $focused_response->data['meta']['selected_destination'], 'transient focus did not preserve the active globe destination' );
tv2_assert( 3 === count( $focused_response->data['destinations'] ), 'transient focus changed the requested global result limit' );
tv2_assert( in_array( 'tokyo', array_column( $focused_response->data['destinations'], 'id' ), true ), 'transient focus did not keep the active globe destination visible' );
tv2_assert( array( 'tokyo' ) === array_values( array_unique( array_column( $focused_response->data['routes'], 'destination_id' ) ) ), 'transient focus leaked routes from another destination' );
tv2_assert( 'bangkok' === $focus_precedence->data['meta']['selected_destination'], 'transient focus overrode an explicit destination' );
tv2_assert( array( 'bangkok' ) === array_values( array_unique( array_column( $focus_precedence->data['routes'], 'destination_id' ) ) ), 'focus precedence leaked routes from another destination' );
tv2_assert( null === $empty_response->data['meta']['selected_destination'], 'zero-result discovery retained a stale selected destination' );
tv2_assert( array() === $empty_response->data['routes'] && null === $empty_response->data['recommended'], 'zero-result discovery retained stale route state' );
tv2_assert( null === $empty_response->data['selected_plan'], 'zero-result discovery retained a stale 360 plan' );
tv2_assert( array() === $empty_response->data['map_entities'] && array() === $empty_response->data['map_segments'], 'zero-result discovery retained stale map entities or route geometry' );
tv2_assert( 'rest_validate_request_arg' === $collection_params['direct']['validate_callback'], 'direct boolean lacks strict REST validation' );
tv2_assert( 'rest_validate_request_arg' === $collection_params['allow_overnight']['validate_callback'], 'overnight boolean lacks strict REST validation' );
tv2_assert( 'sanitize_key' === $collection_params['focus']['sanitize_callback'], 'focus parameter lacks key sanitization' );
tv2_assert( 'rest_validate_request_arg' === $collection_params['focus']['validate_callback'], 'focus parameter lacks strict REST validation' );
tv2_assert( 'rest_validate_request_arg' === $collection_params['destination']['validate_callback'], 'destination parameter lacks strict REST validation' );
tv2_assert( 'rest_validate_request_arg' === $collection_params['q']['validate_callback'], 'query parameter lacks strict REST validation' );
tv2_assert( isset( $item_schema['properties']['selected_plan']['properties']['cost_ledger']['properties']['booking_confirmed'] ), 'selected-plan REST schema is still an untyped object bag' );
tv2_assert( isset( $item_schema['properties']['selected_plan']['properties']['modules']['items']['properties']['provenance'] ), 'selected-plan module provenance is missing from the REST schema' );
$map_entity_schema = isset( $item_schema['properties']['map_entities']['items'] ) ? $item_schema['properties']['map_entities']['items'] : array();
$map_segment_schema = isset( $item_schema['properties']['map_segments']['items'] ) ? $item_schema['properties']['map_segments']['items'] : array();
$required_map_entity_fields = array( 'id', 'kind', 'destination_id', 'lat', 'lng', 'label', 'summary', 'data_mode', 'truth_state', 'freshness', 'action', 'provenance', 'price' );
$required_map_segment_fields = array( 'id', 'route_id', 'destination_id', 'sequence', 'from', 'to', 'truth', 'provenance' );
tv2_assert( ! array_diff( $required_map_entity_fields, isset( $map_entity_schema['required'] ) ? $map_entity_schema['required'] : array() ), 'REST schema does not require the complete typed map entity contract' );
tv2_assert( ! array_diff( $required_map_segment_fields, isset( $map_segment_schema['required'] ) ? $map_segment_schema['required'] : array() ), 'REST schema does not require the complete typed route segment contract' );
tv2_assert( array( 'deal', 'hotel_area', 'airport', 'weather' ) === $map_entity_schema['properties']['kind']['enum'], 'REST schema map kind enum drifted' );
tv2_assert( array( 'search_packages', 'search_hotels', 'compare_routes', 'plan_for_weather' ) === $map_entity_schema['properties']['action']['properties']['type']['enum'], 'REST schema action enum drifted' );
tv2_assert( array( false ) === $map_entity_schema['properties']['price']['properties']['bookable']['enum'] && array( false ) === $map_segment_schema['properties']['truth']['properties']['bookable']['enum'], 'REST schema permits map prices or segments to imply a booking confirmation' );
tv2_assert( is_array( $bangkok_destination ) && 'https://tra-vel.test/destinations/thailand/' === $bangkok_destination['url'], 'Bangkok did not resolve to the published Thailand guide' );
tv2_assert( is_array( $budapest_destination ) && 'https://tra-vel.test/destinations/budapest/' === $budapest_destination['url'], 'Budapest did not resolve to its canonical guide' );
tv2_assert( is_array( $athens_destination ) && 'https://tra-vel.test/destinations/athens/' === $athens_destination['url'], 'Athens did not resolve to its canonical guide' );
tv2_assert( is_array( $tokyo_destination ) && 'https://tra-vel.test/destinations/tokyo/' === $tokyo_destination['url'], 'Tokyo did not resolve to its canonical guide' );
$destination_guide_url_probe = new ReflectionMethod( $discovery_controller, 'destination_guide_url' );
$destination_guide_url_probe->setAccessible( true );
tv2_assert( 'https://tra-vel.test/destinations/' === $destination_guide_url_probe->invoke( $discovery_controller, 'destination-without-published-guide' ), 'unpublished destination emitted a faceted guide URL' );
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
		$destination_ids = array_column( $data['destinations'], 'id' );
		$by_destination  = array();
		foreach ( $destination_ids as $destination_id ) {
			$by_destination[ $destination_id ] = array(
				'source'          => 'context-test',
				'observed_at'     => tv2_utc( -60 ),
				'retrieved_at'    => tv2_utc( -30 ),
				'currency'        => 'USD',
				'cost_components' => array( 'flight', 'baggage', 'hotel', 'transfers', 'insurance', 'overnight' ),
				'total_live'      => false,
				'price_scope'     => 'partial_route_components',
			);
		}
		$data['field_provenance'] = array(
			'deals' => array( 'live' => true, 'source' => 'context-test', 'observed_at' => tv2_utc( -60 ), 'destination_ids' => $destination_ids, 'by_destination' => $by_destination ),
			'routes' => array( 'live' => true, 'source' => 'context-test', 'observed_at' => tv2_utc( -60 ), 'destination_ids' => $destination_ids, 'by_destination' => $by_destination ),
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
	'sort'            => 'smart',
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
tv2_assert( array( 'larnaca', 'athens' ) === array_column( $live_budget->data['destinations'], 'id' ), 'live budget filter returned the wrong destination set' );
tv2_assert( 'partial_live' === $context_first->data['selected_plan']['cost_ledger']['state'], 'live route provenance did not activate the selected-plan ledger' );
tv2_assert( null === $context_first->data['selected_plan']['cost_ledger']['total'], 'partial route components were presented as a full trip total' );
tv2_assert( 12 === count( $context_first->data['selected_plan']['cost_ledger']['line_items'] ), 'live ledger dropped part of the 360-degree cost scope' );
tv2_assert( null === $context_first->data['selected_plan']['cost_ledger']['savings'], 'route totals created an unverified savings claim' );
tv2_assert( false === $context_first->data['selected_plan']['cost_ledger']['comparable_verified'], 'non-comparable route totals were marked verified' );
tv2_assert( false === $context_first->data['selected_plan']['cost_ledger']['booking_confirmed'], 'supplier prices were presented as a confirmed booking' );
$context_bangkok_deal = tv2_find_map_entity( $context_first, 'bangkok' );
tv2_assert( is_array( $context_bangkok_deal ) && 'live' === $context_bangkok_deal['data_mode'] && 'supplier_snapshot' === $context_bangkok_deal['truth_state'] && 'supplier_snapshot' === $context_bangkok_deal['price']['state'], 'current supplier deal was not represented as a field-scoped snapshot' );
tv2_assert( false === $context_bangkok_deal['price']['bookable'] && 'context-test' === $context_bangkok_deal['provenance']['source'] && null === $context_bangkok_deal['provenance']['reviewed_on'], 'supplier map price implied booking or inherited editorial provenance' );

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
tv2_assert( $canonical_destination_count === count( $weather_mixed_budget->data['destinations'] ), 'live weather excluded destinations using hidden editorial prices' );

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
tv2_assert( true === $flag_only['degraded'] && false === $flag_only['reports']['provider_flag_only_test']['healthy'], 'status-only live adapter was reported healthy' );
tv2_assert( 'no_usable_live_fields' === $flag_only['reports']['provider_flag_only_test']['error_code'], 'status-only live adapter lacks an actionable error code' );

$current_weather_registry = new Tra_Vel_V2_Supplier_Registry();
$current_weather_registry->register( new Tra_Vel_V2_Test_Field_Provenance_Adapter( true ) );
$current_weather = $current_weather_registry->resolve( array() );
tv2_assert( true === $current_weather['data']['field_provenance']['weather_current']['live'], 'live current conditions did not receive field provenance' );
tv2_assert( false === $current_weather['data']['field_provenance']['weather_season']['live'], 'editorial season fit inherited live weather provenance' );
tv2_assert( 'mixed' === $current_weather['data']['data_mode'], 'partial live field coverage was not labeled mixed' );
tv2_assert( 'current_weather_test' === $current_weather['data']['field_provenance']['weather_current']['source'], 'live weather provenance lost its source' );
tv2_assert( '2029-04-05T12:00:00Z' === $current_weather['data']['field_provenance']['weather_current']['observed_at'], 'live weather provenance lost its observation time' );

/**
 * Small configurable live adapter used to exercise the real supplier registry.
 */
class Tra_Vel_V2_Test_Fragment_Adapter implements Tra_Vel_V2_Supplier_Adapter {
	private $id;
	private $verticals;
	private $fragment;
	private $version;
	public function __construct( $id, $verticals, $fragment, $version = 'v1' ) {
		$this->id        = $id;
		$this->verticals = $verticals;
		$this->fragment  = $fragment;
		$this->version   = $version;
	}
	public function get_id() { return $this->id; }
	public function get_verticals() { return $this->verticals; }
	public function is_configured() { return true; }
	public function get_mode() { return 'live'; }
	public function get_cache_version() { return $this->version; }
	public function fetch( $context ) {
		unset( $context );
		return $this->fragment;
	}
}

/**
 * Minimal valid normalized route. Overrides make malformed fixtures explicit.
 */
function tv2_live_route_fixture( $id, $overrides = array() ) {
	$route = array(
		'id'               => $id,
		'label'            => 'Live flight option',
		'badge'            => 'Live',
		'duration_minutes' => 780,
		'stops'            => 1,
		'via'              => 'DXB',
		'ticket_mode'      => 'single',
		'risk'             => 'low',
		'emissions_kg'     => 420,
		'score'            => 91,
		'currency'         => 'USD',
		'observed_at'      => tv2_utc( -60 ),
		'costs'            => array(
			'flight'  => 500,
			'baggage' => 50,
			'total'   => 550,
		),
		'pros'             => array( 'One protected ticket' ),
		'cons'             => array( 'One stop' ),
	);
	return array_replace_recursive( $route, $overrides );
}

function tv2_live_deal_fixture( $overrides = array() ) {
	return array_replace(
		array(
			'currency'         => 'USD',
			'headline_price'   => 700,
			'total_per_person' => 1200,
			'nights'           => 7,
			'trend_pct'        => 0,
			'total_scope'      => 'destination_deal',
		),
		$overrides
	);
}

function tv2_live_hotel_fixture( $overrides = array() ) {
	return array_replace(
		array(
			'name'             => 'Supplier hotel',
			'area'             => 'Central district',
			'rating'           => 4.4,
			'nightly'          => 120,
			'nights'           => 7,
			'room_total'       => 840,
			'per_person_total' => 420,
			'currency'         => 'USD',
		),
		$overrides
	);
}

function tv2_live_airport_fixture( $overrides = array() ) {
	return array_replace(
		array(
			'code'             => 'BKK',
			'name'             => 'Suvarnabhumi Airport',
			'direct'           => false,
			'flight_minutes'   => 690,
			'transfer_minutes' => 35,
		),
		$overrides
	);
}

/* Destination-scoped provenance: Bangkok supplier data must never certify Tokyo. */
$bangkok_only_registry = new Tra_Vel_V2_Supplier_Registry();
$bangkok_only_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'bangkok_weather_routes',
		array( 'weather', 'flights' ),
		array(
			'provider_status' => array(
				'weather' => array( 'connected' => true, 'observed_at' => '2030-01-02T03:04:05Z' ),
				'flights' => array( 'connected' => true, 'observed_at' => '2030-01-02T03:04:05Z' ),
			),
			'destinations' => array(
				array(
					'id'      => 'bangkok',
					'weather' => array( 'temperature_c' => 30, 'condition' => 'Clear' ),
				),
			),
			'route_sets' => array(
				'bangkok' => array( tv2_live_route_fixture( 'bangkok-live-scoped' ) ),
			),
		),
		'scoped-v1'
	)
);
$bangkok_only = $bangkok_only_registry->resolve( array() );
tv2_assert( array( 'bangkok' ) === $bangkok_only['data']['field_provenance']['weather_current']['destination_ids'], 'Bangkok-only weather certified another destination' );
tv2_assert( array( 'bangkok' ) === $bangkok_only['data']['field_provenance']['routes']['destination_ids'], 'Bangkok-only routes certified another destination' );
tv2_assert( ! isset( $bangkok_only['data']['field_provenance']['weather_current']['by_destination']['tokyo'] ), 'Tokyo inherited Bangkok weather provenance' );
tv2_assert( ! isset( $bangkok_only['data']['field_provenance']['routes']['by_destination']['tokyo'] ), 'Tokyo inherited Bangkok route provenance' );

$bangkok_only_controller = new Tra_Vel_V2_Test_Discovery_Controller(
	new Tra_Vel_V2_Discovery_Repository( $bangkok_only_registry )
);
$bangkok_only_tokyo = tv2_discovery_request( $bangkok_only_controller, array( 'destination' => 'tokyo' ) );
$bangkok_only_tokyo_route = tv2_find_plan_module( $bangkok_only_tokyo, 'route' );
$bangkok_only_tokyo_weather = tv2_find_plan_module( $bangkok_only_tokyo, 'weather' );
tv2_assert( is_array( $bangkok_only_tokyo_route ) && 'live' !== $bangkok_only_tokyo_route['state'], 'Tokyo route module inherited Bangkok live state' );
tv2_assert( is_array( $bangkok_only_tokyo_weather ) && 'live' !== $bangkok_only_tokyo_weather['state'], 'Tokyo weather module inherited Bangkok live state' );

/* Per-destination source and timestamp survive multiple live adapter merges. */
$multi_source_registry = new Tra_Vel_V2_Supplier_Registry();
$multi_source_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'bangkok_weather_source',
		array( 'weather' ),
		array(
			'provider_status' => array( 'weather' => array( 'connected' => true, 'observed_at' => '2031-02-03T04:05:06Z' ) ),
			'destinations'    => array( array( 'id' => 'bangkok', 'weather' => array( 'temperature_c' => 31, 'condition' => 'Sunny' ) ) ),
		),
		'multi-bangkok-v1'
	)
);
$multi_source_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'tokyo_weather_source',
		array( 'weather' ),
		array(
			'provider_status' => array( 'weather' => array( 'connected' => true, 'observed_at' => '2032-03-04T05:06:07Z' ) ),
			'destinations'    => array( array( 'id' => 'tokyo', 'weather' => array( 'temperature_c' => 18, 'condition' => 'Cloudy' ) ) ),
		),
		'multi-tokyo-v1'
	)
);
$multi_source = $multi_source_registry->resolve( array() );
$multi_weather = $multi_source['data']['field_provenance']['weather_current'];
tv2_assert( array( 'bangkok', 'tokyo' ) === $multi_weather['destination_ids'], 'multiple weather adapters lost destination coverage' );
tv2_assert( 'bangkok_weather_source' === $multi_weather['by_destination']['bangkok']['source'], 'Bangkok weather was attributed to the wrong adapter' );
tv2_assert( '2031-02-03T04:05:06Z' === $multi_weather['by_destination']['bangkok']['observed_at'], 'Bangkok weather lost its own observation time' );
tv2_assert( 'tokyo_weather_source' === $multi_weather['by_destination']['tokyo']['source'], 'Tokyo weather was attributed to the wrong adapter' );
tv2_assert( '2032-03-04T05:06:07Z' === $multi_weather['by_destination']['tokyo']['observed_at'], 'Tokyo weather lost its own observation time' );

/* One adapter may report destination-local observations at different instants. */
$same_adapter_timestamp_registry = new Tra_Vel_V2_Supplier_Registry();
$same_adapter_timestamp_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'destination_weather_clock',
		array( 'weather' ),
		array(
			'destinations' => array(
				array(
					'id'      => 'bangkok',
					'weather' => array(
						'temperature_c' => 31,
						'condition'     => 'Clear',
						'observed_at'   => '2033-04-05T06:07:08Z',
					),
				),
				array(
					'id'      => 'tokyo',
					'weather' => array(
						'temperature_c' => 17,
						'condition'     => 'Cloudy',
						'observed_at'   => '2034-05-06T07:08:09Z',
					),
				),
			),
		),
		'destination-clock-v1'
	)
);
$same_adapter_timestamp = $same_adapter_timestamp_registry->resolve( array() );
$same_adapter_weather   = $same_adapter_timestamp['data']['field_provenance']['weather_current']['by_destination'];
tv2_assert( '2033-04-05T06:07:08Z' === $same_adapter_weather['bangkok']['observed_at'], 'one adapter collapsed Bangkok onto another destination observation time' );
tv2_assert( '2034-05-06T07:08:09Z' === $same_adapter_weather['tokyo']['observed_at'], 'one adapter collapsed Tokyo onto another destination observation time' );

/* Dataset mode reflects destination x field coverage; selected mode remains destination-scoped. */
$coverage_deal_time    = tv2_utc( -65 );
$coverage_hotel_time   = tv2_utc( -64 );
$coverage_airport_time = tv2_utc( -63 );
$coverage_weather_time = tv2_utc( -62 );
$coverage_route_time   = tv2_utc( -61 );
$coverage_registry = new Tra_Vel_V2_Supplier_Registry();
$coverage_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'bangkok_complete_supplier',
		array( 'deals', 'hotels', 'flights', 'weather' ),
		array(
			'destinations' => array(
				array(
					'id'      => 'bangkok',
					'deal'    => tv2_live_deal_fixture( array( 'observed_at' => $coverage_deal_time ) ),
					'hotel'   => tv2_live_hotel_fixture( array( 'observed_at' => $coverage_hotel_time ) ),
					'airport' => tv2_live_airport_fixture( array( 'observed_at' => $coverage_airport_time ) ),
					'weather' => array(
						'temperature_c' => 30,
						'condition'     => 'Clear',
						'season_fit'   => 'Dry season',
						'observed_at'   => $coverage_weather_time,
					),
				),
			),
			'route_sets' => array(
			'bangkok' => array( tv2_live_route_fixture( 'bangkok-complete-route', array( 'observed_at' => $coverage_route_time ) ) ),
			),
		),
		'coverage-v1'
	)
);
$coverage_resolved = $coverage_registry->resolve( array() );
tv2_assert( 'mixed' === $coverage_resolved['data']['data_mode'], 'one fully live destination mislabeled the entire canonical dataset live' );

$coverage_controller = new Tra_Vel_V2_Test_Discovery_Controller(
	new Tra_Vel_V2_Discovery_Repository( $coverage_registry )
);
$coverage_bangkok = tv2_discovery_request( $coverage_controller, array( 'destination' => 'bangkok' ) );
$coverage_tokyo   = tv2_discovery_request( $coverage_controller, array( 'destination' => 'tokyo' ) );
tv2_assert( 'live' === $coverage_bangkok->data['meta']['data_mode'], 'fully covered Bangkok did not receive selected live mode' );
tv2_assert( 'demo' === $coverage_tokyo->data['meta']['data_mode'], 'uncovered Tokyo inherited selected live mode from Bangkok' );
tv2_assert( 'mixed' === $coverage_bangkok->data['meta']['dataset_data_mode'] && 'mixed' === $coverage_tokyo->data['meta']['dataset_data_mode'], 'selected responses lost the dataset-wide mixed mode' );
tv2_assert( false === stripos( $coverage_tokyo->data['meta']['disclaimer'], 'Supplier data is live' ), 'uncovered Tokyo inherited the live supplier disclaimer' );

/* An adapter cannot inject a new destination outside the reviewed contract. */
$unknown_destination_registry = new Tra_Vel_V2_Supplier_Registry();
$unknown_destination_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'unknown_destination_supplier',
		array( 'deals' ),
		array(
			'destinations' => array(
				array(
					'id'   => 'newcity',
					'deal' => tv2_live_deal_fixture(),
				),
			),
		),
		'unknown-destination-v1'
	)
);
$unknown_destination = $unknown_destination_registry->resolve( array() );
tv2_assert( null === tv2_find_contract_destination( $unknown_destination['data'], 'newcity' ), 'unreviewed supplier destination entered the public contract' );
tv2_assert( $canonical_destination_count === count( $unknown_destination['data']['destinations'] ), 'unreviewed supplier destination changed the canonical destination count' );
tv2_assert( array() === $unknown_destination['data']['field_provenance']['deals']['destination_ids'], 'unreviewed supplier destination received live price provenance' );

/* Partial monetary fragments cannot certify inherited demo deal or hotel prices. */
$partial_price_registry = new Tra_Vel_V2_Supplier_Registry();
$partial_price_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'partial_price_fields',
		array( 'deals', 'hotels' ),
		array(
			'destinations' => array(
				array(
					'id'    => 'bangkok',
					'deal'  => array( 'trend_pct' => -99 ),
					'hotel' => array( 'name' => 'Partial hotel identity only' ),
				),
			),
		),
		'partial-price-v1'
	)
);
$partial_price = $partial_price_registry->resolve( array() );
tv2_assert( false === $partial_price['data']['field_provenance']['deals']['live'], 'partial deal fragment certified inherited prices' );
tv2_assert( false === $partial_price['data']['field_provenance']['hotels']['live'], 'partial hotel fragment certified inherited prices' );
tv2_assert( array() === $partial_price['data']['field_provenance']['deals']['destination_ids'], 'partial deal fragment gained destination price coverage' );
tv2_assert( array() === $partial_price['data']['field_provenance']['hotels']['destination_ids'], 'partial hotel fragment gained destination price coverage' );

$partial_price_controller = new Tra_Vel_V2_Test_Discovery_Controller(
	new Tra_Vel_V2_Discovery_Repository( $partial_price_registry )
);
$partial_price_budget = tv2_discovery_request( $partial_price_controller, array( 'budget' => 550, 'destination' => 'bangkok' ) );
$partial_price_stay = tv2_find_plan_module( $partial_price_budget, 'stay' );
tv2_assert( false === $partial_price_budget->data['meta']['filters']['budget_applied'], 'partial deal fragment activated monetary filtering' );
tv2_assert( $canonical_destination_count === count( $partial_price_budget->data['destinations'] ), 'partial deal price excluded editorial destinations' );
tv2_assert( 'bangkok' === $partial_price_budget->data['meta']['selected_destination'], 'partial prices excluded the requested destination' );
tv2_assert( is_array( $partial_price_stay ) && 'live' !== $partial_price_stay['state'], 'partial hotel fragment activated live stay pricing' );
tv2_assert( null === $partial_price_budget->data['selected_plan']['cost_ledger']['total'], 'partial price fragments exposed a full trip total' );

/* Hotel and airport identity, types, and ranges fail closed before fallback merge. */
$invalid_place_registry = new Tra_Vel_V2_Supplier_Registry();
$invalid_place_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'invalid_place_fields',
		array( 'hotels', 'flights' ),
		array(
			'destinations' => array(
				array(
					'id'      => 'bangkok',
					'hotel'   => tv2_live_hotel_fixture(
						array(
							'name'   => array( 'not', 'text' ),
							'area'   => array( 'not', 'text' ),
							'rating' => 9,
							'nights' => 0,
						)
					),
					'airport' => tv2_live_airport_fixture(
						array(
							'name'   => array( 'not', 'text' ),
							'direct' => 'yes',
						)
					),
				),
			),
		),
		'invalid-place-v1'
	)
);
$invalid_place = $invalid_place_registry->resolve( array() );
$invalid_place_bangkok = tv2_find_contract_destination( $invalid_place['data'], 'bangkok' );
$demo_bangkok          = tv2_find_contract_destination( $first['data'], 'bangkok' );
tv2_assert( false === $invalid_place['data']['field_provenance']['hotels']['live'], 'invalid hotel types or ranges received live provenance' );
tv2_assert( false === $invalid_place['data']['field_provenance']['airports']['live'], 'invalid airport types received live provenance' );
tv2_assert( array() === $invalid_place['data']['field_provenance']['hotels']['destination_ids'], 'invalid hotel received destination coverage' );
tv2_assert( array() === $invalid_place['data']['field_provenance']['airports']['destination_ids'], 'invalid airport received destination coverage' );
tv2_assert( $demo_bangkok['hotel'] === $invalid_place_bangkok['hotel'], 'invalid hotel values overlaid reviewed fallback content' );
tv2_assert( $demo_bangkok['airport'] === $invalid_place_bangkok['airport'], 'invalid airport values overlaid reviewed fallback content' );

/* Missing currency and inconsistent totals fail closed at route provenance. */
$missing_currency_route = tv2_live_route_fixture( 'missing-currency' );
unset( $missing_currency_route['currency'] );
$malformed_total_route = tv2_live_route_fixture(
	'malformed-total',
	array( 'costs' => array( 'total' => 999 ) )
);
$malformed_route_registry = new Tra_Vel_V2_Supplier_Registry();
$malformed_route_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'malformed_route_fields',
		array( 'flights' ),
		array(
			'route_sets' => array(
				'bangkok' => array( $missing_currency_route ),
				'tokyo'   => array( $malformed_total_route ),
			),
		),
		'malformed-routes-v1'
	)
);
$malformed_routes = $malformed_route_registry->resolve( array() );
tv2_assert( false === $malformed_routes['data']['field_provenance']['routes']['live'], 'malformed route set received live provenance' );
tv2_assert( array() === $malformed_routes['data']['field_provenance']['routes']['destination_ids'], 'malformed route set received destination coverage' );
tv2_assert( array() === $malformed_routes['data']['field_provenance']['routes']['by_destination'], 'malformed route set received per-destination provenance' );
tv2_assert( array_column( $first['data']['route_sets']['bangkok'], 'id' ) === array_column( $malformed_routes['data']['route_sets']['bangkok'], 'id' ), 'rejected Bangkok route overlay erased reviewed demo routes' );
tv2_assert( array_column( $first['data']['route_sets']['tokyo'], 'id' ) === array_column( $malformed_routes['data']['route_sets']['tokyo'], 'id' ), 'rejected Tokyo route overlay erased reviewed demo routes' );

$invalid_semantic_route = tv2_live_route_fixture(
	'invalid-semantic-route',
	array(
		'ticket_mode'  => 'unprotected-maybe',
		'risk'         => 'magic',
		'emissions_kg' => -10,
		'score'        => 999,
	)
);
$invalid_semantic_registry = new Tra_Vel_V2_Supplier_Registry();
$invalid_semantic_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'invalid_semantic_route_fields',
		array( 'flights' ),
		array( 'route_sets' => array( 'bangkok' => array( $invalid_semantic_route ) ) ),
		'invalid-semantic-route-v1'
	)
);
$invalid_semantic = $invalid_semantic_registry->resolve( array() );
tv2_assert( false === $invalid_semantic['data']['field_provenance']['routes']['live'], 'out-of-range route enums or scores received live provenance' );
tv2_assert( array_column( $first['data']['route_sets']['bangkok'], 'id' ) === array_column( $invalid_semantic['data']['route_sets']['bangkok'], 'id' ), 'semantic route rejection erased reviewed demo routes' );

/* Flight-only route ownership exposes only flight and baggage, never a full trip claim. */
$flight_route_registry = new Tra_Vel_V2_Supplier_Registry();
$flight_route_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'flight_route_components',
		array( 'flights' ),
		array(
			'route_sets' => array(
				'bangkok' => array( tv2_live_route_fixture( 'bangkok-flight-components' ) ),
			),
		),
		'flight-components-v1'
	)
);
$flight_route = $flight_route_registry->resolve( array() );
$flight_route_provenance = $flight_route['data']['field_provenance']['routes']['by_destination']['bangkok'];
tv2_assert( array( 'flight', 'baggage' ) === $flight_route_provenance['cost_components'], 'flight adapter claimed unsupported cost components' );
tv2_assert( ! empty( $flight_route_provenance['observed_at'] ) && 1 === preg_match( '/Z$/', $flight_route_provenance['observed_at'] ), 'live route provenance is missing its canonical UTC observation time' );
tv2_assert( ! empty( $flight_route_provenance['retrieved_at'] ) && false !== strtotime( $flight_route_provenance['retrieved_at'] ), 'route retrieval timestamp is missing or invalid' );
tv2_assert( false === $flight_route_provenance['total_live'], 'flight-only adapter claimed a full route total' );

$flight_route_controller = new Tra_Vel_V2_Test_Discovery_Controller(
	new Tra_Vel_V2_Discovery_Repository( $flight_route_registry )
);
$flight_route_response = tv2_discovery_request( $flight_route_controller, array( 'destination' => 'bangkok' ) );
$flight_ledger = $flight_route_response->data['selected_plan']['cost_ledger'];
$live_line_items = array_values(
	array_filter(
		$flight_ledger['line_items'],
		static function ( $line_item ) { return 'live' === $line_item['state']; }
	)
);
$pending_line_items = array_values(
	array_filter(
		$flight_ledger['line_items'],
		static function ( $line_item ) { return 'needs_search' === $line_item['state']; }
	)
);
tv2_assert( 12 === count( $flight_ledger['line_items'] ), 'flight-only route did not preserve the complete twelve-row cost scope' );
tv2_assert( array( 'flight', 'baggage' ) === array_column( $live_line_items, 'id' ), 'flight-only route activated unsupported ledger rows' );
tv2_assert( 10 === count( $pending_line_items ), 'unsupported flight-route ledger rows were not marked needs_search' );
tv2_assert( null === $flight_ledger['total'], 'flight-only route exposed an incomplete amount as the full total' );
tv2_assert( null === $flight_ledger['savings'], 'flight-only route invented a savings claim' );
tv2_assert( false === $flight_ledger['comparable_verified'], 'flight-only route claimed a verified comparison cohort' );
tv2_assert( 'flight_route_components' === $flight_ledger['source'], 'flight ledger lost its adapter source' );
tv2_assert( ! empty( $flight_ledger['retrieved_at'] ), 'flight ledger lost its retrieval timestamp' );
$live_route_segments = $flight_route_response->data['map_segments'];
tv2_assert( 2 === count( $live_route_segments ) && array( 'bangkok-flight-components' ) === array_values( array_unique( array_column( $live_route_segments, 'route_id' ) ) ), 'live selected route did not expose its exact two-segment geometry' );
tv2_assert( 'live' === $live_route_segments[0]['truth']['data_mode'] && 'supplier_snapshot' === $live_route_segments[0]['truth']['truth_state'] && 'current' === $live_route_segments[0]['truth']['freshness'] && false === $live_route_segments[0]['truth']['bookable'], 'current live route geometry was not labeled as a non-bookable supplier snapshot' );
tv2_assert( 'flight_route_components' === $live_route_segments[0]['provenance']['source'] && $flight_route_provenance['observed_at'] === $live_route_segments[0]['provenance']['observed_at'] && $flight_route_provenance['retrieved_at'] === $live_route_segments[0]['provenance']['retrieved_at'] && null === $live_route_segments[0]['provenance']['reviewed_on'], 'live route segment lost its exact supplier provenance' );

/* A package label cannot turn a thin flight fragment into a complete trip total. */
$thin_package_registry = new Tra_Vel_V2_Supplier_Registry();
$thin_package_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'thin_package_route',
		array( 'packages' ),
		array(
			'route_sets' => array(
				'bangkok' => array( tv2_live_route_fixture( 'bangkok-thin-package' ) ),
			),
		),
		'thin-package-v1'
	)
);
$thin_package = $thin_package_registry->resolve( array() );
$thin_package_provenance = $thin_package['data']['field_provenance']['routes']['by_destination']['bangkok'];
tv2_assert( false === $thin_package_provenance['total_live'], 'thin package route claimed a complete trip total' );
tv2_assert( 'partial_route_components' === $thin_package_provenance['price_scope'], 'thin package route claimed full-trip price scope' );

$thin_package_controller = new Tra_Vel_V2_Test_Discovery_Controller(
	new Tra_Vel_V2_Discovery_Repository( $thin_package_registry )
);
$thin_package_response = tv2_discovery_request( $thin_package_controller, array( 'destination' => 'bangkok' ) );
$thin_package_ledger   = $thin_package_response->data['selected_plan']['cost_ledger'];
tv2_assert( 12 === count( $thin_package_ledger['line_items'] ), 'thin package route changed the canonical twelve-row ledger' );
tv2_assert( null === $thin_package_ledger['total'], 'thin package route exposed flight subtotal as complete trip total' );
tv2_assert( null === $thin_package_ledger['savings'] && false === $thin_package_ledger['comparable_verified'], 'thin package route invented savings or a verified comparison cohort' );

/* A complete package owns all twelve canonical rows and only its arithmetic total. */
$complete_package_costs = array(
	'flight'          => 500,
	'baggage'         => 50,
	'hotel'           => 100,
	'taxes'           => 20,
	'transfers'       => 30,
	'local_transport' => 20,
	'activities'      => 40,
	'dining'          => 50,
	'insurance'       => 30,
	'connectivity'    => 10,
	'equipment'       => 5,
	'entry'           => 15,
	'total'           => 870,
);
$complete_package_registry = new Tra_Vel_V2_Supplier_Registry();
$complete_package_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'complete_package_route',
		array( 'packages' ),
		array(
			'route_sets' => array(
				'bangkok' => array(
					tv2_live_route_fixture(
						'bangkok-complete-package',
						array( 'costs' => $complete_package_costs )
					),
				),
			),
		),
		'complete-package-v1'
	)
);
$complete_package = $complete_package_registry->resolve( array() );
$complete_package_provenance = $complete_package['data']['field_provenance']['routes']['by_destination']['bangkok'];
tv2_assert( true === $complete_package_provenance['total_live'], 'complete package route did not receive verified full-total provenance' );
tv2_assert( 'full_trip_total' === $complete_package_provenance['price_scope'], 'complete package route lost full-trip price scope' );

$complete_package_controller = new Tra_Vel_V2_Test_Discovery_Controller(
	new Tra_Vel_V2_Discovery_Repository( $complete_package_registry )
);
$complete_package_response = tv2_discovery_request( $complete_package_controller, array( 'destination' => 'bangkok' ) );
$complete_package_ledger   = $complete_package_response->data['selected_plan']['cost_ledger'];
$canonical_cost_ids       = array( 'flight', 'baggage', 'stay', 'taxes', 'transfers', 'local_transport', 'activities', 'dining', 'insurance', 'connectivity', 'equipment', 'entry' );
tv2_assert( 12 === count( $complete_package_ledger['line_items'] ), 'complete package did not produce exactly twelve canonical ledger rows' );
tv2_assert( $canonical_cost_ids === array_column( $complete_package_ledger['line_items'], 'id' ), 'complete package ledger IDs or order drifted from the canonical twelve' );
tv2_assert( ! array_filter( $complete_package_ledger['line_items'], static function ( $line_item ) { return 'live' !== $line_item['state']; } ), 'complete package left a canonical ledger row unverified' );
tv2_assert( 870.0 === $complete_package_ledger['total']['amount'], 'complete package exposed a total that did not equal its twelve components' );
tv2_assert( 'complete_live' === $complete_package_ledger['state'], 'complete twelve-row package did not receive complete_live ledger state' );
tv2_assert( null === $complete_package_ledger['savings'], 'complete package invented a savings claim without a comparison cohort' );
tv2_assert( false === $complete_package_ledger['comparable_verified'] && false === $complete_package_ledger['booking_confirmed'], 'complete package invented comparison or booking confirmation' );

/* Filtering every live route leaves an unavailable route module and neutral ledger. */
$filtered_live_routes = tv2_discovery_request(
	$flight_route_controller,
	array(
		'destination'  => 'bangkok',
		'max_duration' => 60,
	)
);
$filtered_route_module = tv2_find_plan_module( $filtered_live_routes, 'route' );
$filtered_ledger       = $filtered_live_routes->data['selected_plan']['cost_ledger'];
tv2_assert( array() === $filtered_live_routes->data['routes'], 'restrictive filters did not remove every live route' );
tv2_assert( is_array( $filtered_route_module ) && 'unavailable' === $filtered_route_module['state'], 'filtered-empty live routes did not produce an unavailable route module' );
tv2_assert( 'needs_search' === $filtered_ledger['state'], 'filtered-empty live routes retained a live ledger state' );
tv2_assert( null === $filtered_ledger['route_id'] && null === $filtered_ledger['total'] && null === $filtered_ledger['savings'], 'filtered-empty live routes retained route price claims' );
tv2_assert( ! array_filter( $filtered_ledger['line_items'], static function ( $line_item ) { return null !== $line_item['amount']; } ), 'filtered-empty live routes retained priced ledger rows' );

/* Supplier timestamps must be strict calendar-valid RFC3339 and canonical UTC. */
$timestamp_registry = new Tra_Vel_V2_Supplier_Registry();
$normalize_timestamp = new ReflectionMethod( $timestamp_registry, 'normalize_rfc3339_utc' );
$normalize_timestamp->setAccessible( true );
foreach ( array( '2030-02-30T12:00:00Z', '2030-01-01T24:00:00Z', '2030-01-01T12:00:60Z', '2030-01-01T12:00Z', '' ) as $invalid_timestamp ) {
	tv2_assert( null === $normalize_timestamp->invoke( $timestamp_registry, $invalid_timestamp ), "invalid supplier time was accepted: {$invalid_timestamp}" );
}
tv2_assert( '2030-01-01T10:00:00Z' === $normalize_timestamp->invoke( $timestamp_registry, '2030-01-01T12:00:00+02:00' ), 'offset supplier timestamp was not normalized to canonical UTC' );

/* Out-of-schema weather cannot overlay reviewed data or receive live provenance. */
$invalid_weather_registry = new Tra_Vel_V2_Supplier_Registry();
$invalid_weather_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'invalid_weather_payload',
		array( 'weather' ),
		array(
			'destinations' => array(
				array( 'id' => 'bangkok', 'weather' => array( 'temperature_c' => 999, 'condition' => 'X', 'observed_at' => tv2_utc( -60 ) ) ),
			),
		),
		'invalid-weather-v1'
	)
);
$invalid_weather = $invalid_weather_registry->resolve( array() );
tv2_assert( false === $invalid_weather['data']['field_provenance']['weather_current']['live'], 'out-of-schema weather received live provenance' );
tv2_assert( $demo_bangkok['weather'] === tv2_find_contract_destination( $invalid_weather['data'], 'bangkok' )['weather'], 'out-of-schema weather overlaid reviewed fallback data' );
tv2_assert( true === $invalid_weather['degraded'] && 'no_usable_live_fields' === $invalid_weather['reports']['invalid_weather_payload']['error_code'], 'fully rejected live payload was reported healthy' );

/* Every live route in a set needs the same explicit observation instant. */
$missing_route_timestamp = tv2_live_route_fixture( 'missing-route-time' );
unset( $missing_route_timestamp['observed_at'] );
$inconsistent_route_registry = new Tra_Vel_V2_Supplier_Registry();
$inconsistent_route_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'inconsistent_route_clock',
		array( 'flights' ),
		array(
			'route_sets' => array(
				'bangkok' => array(
					tv2_live_route_fixture( 'clock-a', array( 'observed_at' => tv2_utc( -60 ) ) ),
					tv2_live_route_fixture( 'clock-b', array( 'observed_at' => tv2_utc( -61 ) ) ),
				),
				'tokyo' => array( $missing_route_timestamp ),
			),
		),
		'inconsistent-clock-v1'
	)
);
$inconsistent_routes = $inconsistent_route_registry->resolve( array() );
tv2_assert( false === $inconsistent_routes['data']['field_provenance']['routes']['live'], 'missing or inconsistent route timestamps received set-level provenance' );
tv2_assert( 'no_usable_live_fields' === $inconsistent_routes['reports']['inconsistent_route_clock']['error_code'], 'timestamp-rejected route adapter was reported healthy' );

/* Hotel/stay aliases and unmodeled overnight totals cannot produce a full-trip total. */
$alias_route = tv2_live_route_fixture(
	'alias-route',
	array( 'costs' => array( 'flight' => 500, 'baggage' => 50, 'stay' => 10, 'total' => 560 ) )
);
$alias_registry = new Tra_Vel_V2_Supplier_Registry();
$alias_registry->register( new Tra_Vel_V2_Test_Fragment_Adapter( 'alias_package', array( 'packages' ), array( 'route_sets' => array( 'bangkok' => array( $alias_route ) ) ), 'alias-v1' ) );
$alias_result = $alias_registry->resolve( array() );
tv2_assert( false === $alias_result['data']['field_provenance']['routes']['live'], 'stay alias entered the canonical hotel cost contract' );

$overnight_costs = $complete_package_costs;
$overnight_costs['overnight'] = 25;
$overnight_costs['total']     = 895;
$overnight_registry = new Tra_Vel_V2_Supplier_Registry();
$overnight_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'overnight_package',
		array( 'packages' ),
		array( 'route_sets' => array( 'bangkok' => array( tv2_live_route_fixture( 'overnight-package-route', array( 'costs' => $overnight_costs ) ) ) ) ),
		'overnight-package-v1'
	)
);
$overnight_resolved = $overnight_registry->resolve( array() );
tv2_assert( false === $overnight_resolved['data']['field_provenance']['routes']['by_destination']['bangkok']['total_live'], 'overnight cost outside the canonical twelve received full-total provenance' );
$overnight_controller = new Tra_Vel_V2_Test_Discovery_Controller( new Tra_Vel_V2_Discovery_Repository( $overnight_registry ) );
$overnight_response = tv2_discovery_request( $overnight_controller, array( 'destination' => 'bangkok', 'allow_overnight' => true ) );
tv2_assert( 12 === count( $overnight_response->data['selected_plan']['cost_ledger']['line_items'] ), 'overnight route created a thirteenth ledger row' );
tv2_assert( null === $overnight_response->data['selected_plan']['cost_ledger']['total'], 'unmodeled overnight cost entered a full-trip total' );

/* Source age/future skew and cache age remain separate freshness dimensions. */
foreach ( array( 'old' => tv2_utc( -7200 ), 'future' => tv2_utc( 600 ) ) as $clock_case => $route_time ) {
	$clock_registry = new Tra_Vel_V2_Supplier_Registry();
	$clock_registry->register(
		new Tra_Vel_V2_Test_Fragment_Adapter(
			"{$clock_case}_route_source",
			array( 'flights' ),
			array( 'route_sets' => array( 'bangkok' => array( tv2_live_route_fixture( "{$clock_case}-route", array( 'observed_at' => $route_time ) ) ) ) ),
			"{$clock_case}-route-v1"
		)
	);
	$clock_controller = new Tra_Vel_V2_Test_Discovery_Controller( new Tra_Vel_V2_Discovery_Repository( $clock_registry ) );
	$clock_response   = tv2_discovery_request( $clock_controller, array( 'destination' => 'bangkok' ) );
	tv2_assert( 'current' === $clock_response->data['meta']['cache_freshness'], "{$clock_case} source changed the cache freshness dimension" );
	tv2_assert( ( 'old' === $clock_case ? 'stale' : 'future' ) === $clock_response->data['meta']['source_freshness'], "{$clock_case} source age was not classified" );
	tv2_assert( 'stale' === $clock_response->data['meta']['freshness'], "{$clock_case} source was exposed as current" );
	tv2_assert( 'stale' === tv2_find_plan_module( $clock_response, 'route' )['state'], "{$clock_case} route module was exposed as live" );
	tv2_assert( 'private, no-store' === $clock_response->headers['Cache-Control'], "{$clock_case} source remained publicly cacheable as current" );
}

/* Budget coverage must disclose partial supplier qualification. */
$partial_budget_registry = new Tra_Vel_V2_Supplier_Registry();
$partial_budget_registry->register(
	new Tra_Vel_V2_Test_Fragment_Adapter(
		'partial_budget_deal',
		array( 'deals' ),
		array( 'destinations' => array( array( 'id' => 'bangkok', 'deal' => tv2_live_deal_fixture( array( 'observed_at' => tv2_utc( -60 ) ) ) ) ) ),
		'partial-budget-v1'
	)
);
$partial_budget_controller = new Tra_Vel_V2_Test_Discovery_Controller( new Tra_Vel_V2_Discovery_Repository( $partial_budget_registry ) );
$partial_budget_response   = tv2_discovery_request( $partial_budget_controller, array( 'budget' => 550 ) );
tv2_assert( 'partial' === $partial_budget_response->data['meta']['filters']['budget_coverage'], 'partial live budget coverage was not disclosed' );
tv2_assert( false === $partial_budget_response->data['meta']['filters']['budget_applied'], 'partial budget qualification was labeled fully applied' );
tv2_assert( true === $partial_budget_response->data['meta']['filters']['budget_filter_active'], 'partial live budget filter did not disclose active filtering' );
tv2_assert( $canonical_destination_count - 1 === count( $partial_budget_response->data['destinations'] ), 'partial budget fixture returned the wrong result set' );
tv2_assert( ! in_array( 'bangkok', array_column( $partial_budget_response->data['destinations'], 'id' ), true ), 'partial live budget qualification did not exclude its over-budget live destination' );

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

$degraded_registry          = new Tra_Vel_V2_Test_Registry();
$degraded_registry->degrade = true;
$degraded_repository        = new Tra_Vel_V2_Discovery_Repository( $degraded_registry );
$degraded_first             = $degraded_repository->get( array( 'budget' => 6123 ) );
$degraded_cached            = $degraded_repository->get( array( 'budget' => 6123 ) );
tv2_assert( 'degraded_fallback' === $degraded_first['runtime']['cache_state'], 'first degraded response lost its fallback state' );
tv2_assert( 'degraded_fallback' === $degraded_cached['runtime']['cache_state'], 'cached degraded response was relabeled fresh' );
tv2_assert( ! empty( $degraded_cached['runtime']['degraded'] ), 'cached degraded response lost its degraded provenance' );

$test_registry->degrade = false;
$fresh_variant          = $test_repository->get( array( 'budget' => 6000 ) );
$test_registry->fail    = true;
$failed_variant         = $test_repository->get( array( 'budget' => 6000 ), true );
tv2_assert( 'miss' === $fresh_variant['runtime']['cache_state'], 'second cache variant was not created' );
tv2_assert( 'stale_error' === $failed_variant['runtime']['cache_state'], 'full supplier error did not use stale data' );
tv2_assert( 'simulated_supplier_failure' === $failed_variant['runtime']['fallback_error'], 'supplier error code is missing' );
tv2_assert( 2 === $test_repository->purge(), 'cache generation did not increment' );

/* Stale-on-error supplier values are last-known data, never confirmed-live motion. */
class Tra_Vel_V2_Test_Fixed_Repository {
	private $resolved;
	public function __construct( $resolved ) {
		$this->resolved = $resolved;
	}
	public function get( $context = array(), $force_refresh = false ) {
		unset( $context, $force_refresh );
		return $this->resolved;
	}
}

/* Invalid or incomplete geometry must fail closed instead of drawing guesses. */
$invalid_coordinate_result = $first;
$invalid_coordinate_result['data']['airport_registry']['BKK']['latitude'] = 91;
$invalid_coordinate_controller = new Tra_Vel_V2_Test_Discovery_Controller( new Tra_Vel_V2_Test_Fixed_Repository( $invalid_coordinate_result ) );
$invalid_coordinate_response = tv2_discovery_request( $invalid_coordinate_controller, array( 'destination' => 'bangkok' ) );
tv2_assert( is_wp_error( $invalid_coordinate_response ) && 'tra_vel_map_contract_invalid' === $invalid_coordinate_response->get_error_code(), 'out-of-range airport coordinates did not fail the map response closed' );
tv2_assert( 500 === $invalid_coordinate_response->get_error_data()['status'], 'invalid map geometry did not return an explicit server contract error status' );

$invalid_via_result = $first;
$invalid_via_result['data']['route_sets']['bangkok'][1]['via'] = 'ZZZ';
$invalid_via_controller = new Tra_Vel_V2_Test_Discovery_Controller( new Tra_Vel_V2_Test_Fixed_Repository( $invalid_via_result ) );
$invalid_via_response = tv2_discovery_request( $invalid_via_controller, array( 'destination' => 'bangkok' ) );
tv2_assert( is_wp_error( $invalid_via_response ) && 'tra_vel_map_contract_invalid' === $invalid_via_response->get_error_code(), 'unregistered route via code did not fail the map response closed' );

$wrong_destination_airport_result = $first;
foreach ( $wrong_destination_airport_result['data']['destinations'] as &$wrong_destination_airport ) {
	if ( 'bangkok' === $wrong_destination_airport['id'] ) {
		$wrong_destination_airport['airport']['code'] = 'DXB';
	}
}
unset( $wrong_destination_airport );
$wrong_destination_airport_controller = new Tra_Vel_V2_Test_Discovery_Controller( new Tra_Vel_V2_Test_Fixed_Repository( $wrong_destination_airport_result ) );
$wrong_destination_airport_response = tv2_discovery_request( $wrong_destination_airport_controller, array( 'destination' => 'bangkok' ) );
tv2_assert( is_wp_error( $wrong_destination_airport_response ) && 'tra_vel_map_contract_invalid' === $wrong_destination_airport_response->get_error_code(), 'known airport code bound to the wrong destination did not fail closed' );

$mismatched_origin_result = $first;
$mismatched_origin_result['data']['origin']['latitude'] = 32.0005;
$mismatched_origin_controller = new Tra_Vel_V2_Test_Discovery_Controller( new Tra_Vel_V2_Test_Fixed_Repository( $mismatched_origin_result ) );
$mismatched_origin_response = tv2_discovery_request( $mismatched_origin_controller );
tv2_assert( is_wp_error( $mismatched_origin_response ) && 'tra_vel_map_contract_invalid' === $mismatched_origin_response->get_error_code(), 'origin/registry coordinate disagreement did not fail the map response closed' );

$stale_controller = new Tra_Vel_V2_Test_Discovery_Controller(
	new Tra_Vel_V2_Test_Fixed_Repository(
		array(
			'data'    => $coverage_resolved['data'],
			'runtime' => array(
				'generated_at'   => tv2_utc( -30 ),
				'cache_ttl'      => 300,
				'stale_ttl'      => 900,
				'cache_state'    => 'stale_error',
				'adapters'       => array(),
				'fallback_error' => 'supplier_degraded',
				'failed_adapters' => array( 'bangkok_complete_supplier' ),
			),
		)
	)
);
$stale_response = tv2_discovery_request( $stale_controller, array( 'destination' => 'bangkok' ) );
$stale_disclaimer  = $stale_response->data['meta']['disclaimer'];
$stale_plan        = $stale_response->data['selected_plan'];
$stale_modules     = array_values(
	array_filter(
		$stale_plan['modules'],
		static function ( $module ) { return 'stale' === $module['state']; }
	)
);
$stale_ledger_rows = array_values(
	array_filter(
		$stale_plan['cost_ledger']['line_items'],
		static function ( $line_item ) { return 'stale' === $line_item['state']; }
	)
);
tv2_assert( 'stale_error' === $stale_response->data['meta']['cache_state'], 'controller lost stale-error cache provenance' );
tv2_assert( 'stale' === $stale_response->data['meta']['freshness'], 'stale-error response did not expose a separate stale freshness dimension' );
tv2_assert( 'stale' === $stale_response->data['meta']['cache_freshness'] && 'current' === $stale_response->data['meta']['source_freshness'], 'stale cache response collapsed cache age into supplier observation age' );
tv2_assert( 'stale' === $stale_response->headers['X-Tra-Vel-Freshness'], 'stale-error response lost its stale freshness header' );
tv2_assert( 'private, no-store' === $stale_response->headers['Cache-Control'], 'stale-error supplier snapshot remained publicly cacheable' );
tv2_assert( false === stripos( $stale_disclaimer, 'Supplier data is live' ), 'stale-error response used the confirmed-live disclaimer' );
tv2_assert( 1 === preg_match( '/(?:last observed|snapshot|stale|cached)/i', $stale_disclaimer ), 'stale-error response did not disclose last-observed snapshot data' );
tv2_assert( 'stale' === $stale_plan['state'] && 'stale' === $stale_plan['freshness'], 'stale-error selected plan retained a current plan state' );
tv2_assert( ! array_filter( $stale_plan['modules'], static function ( $module ) { return 'live' === $module['state']; } ), 'stale-error selected plan retained a live module state' );
tv2_assert( ! array_filter( $stale_plan['cost_ledger']['line_items'], static function ( $line_item ) { return 'live' === $line_item['state']; } ), 'stale-error ledger retained a live line-item state' );
tv2_assert( $stale_modules && $stale_ledger_rows, 'stale-error response discarded its last-observed supplier snapshot instead of labeling it stale' );
tv2_assert( $coverage_route_time === tv2_find_plan_module( $stale_response, 'route' )['provenance']['observed_at'], 'stale route module lost its supplier observation timestamp' );
tv2_assert( $coverage_hotel_time === tv2_find_plan_module( $stale_response, 'stay' )['provenance']['observed_at'], 'stale stay module lost its supplier observation timestamp' );
tv2_assert( $coverage_weather_time === tv2_find_plan_module( $stale_response, 'weather' )['provenance']['observed_at'], 'stale weather module lost its supplier observation timestamp' );
tv2_assert( ! array_filter( $stale_ledger_rows, static function ( $line_item ) use ( $coverage_route_time ) { return $coverage_route_time !== $line_item['observed_at']; } ), 'stale ledger rows lost their supplier observation timestamp' );
$stale_segments = $stale_response->data['map_segments'];
tv2_assert( ! empty( $stale_segments ) && 'live' === $stale_segments[0]['truth']['data_mode'] && 'last_observed' === $stale_segments[0]['truth']['truth_state'] && 'stale' === $stale_segments[0]['truth']['freshness'], 'stale supplier route geometry was presented as a current snapshot' );
tv2_assert( $coverage_route_time === $stale_segments[0]['provenance']['observed_at'] && false === $stale_segments[0]['truth']['bookable'], 'stale route geometry lost provenance or implied booking confirmation' );

$app_javascript = file_get_contents( TRA_VEL_V2_PATH . '/assets/js/app.js' );
$confirmed_motion_expression = array();
tv2_assert( false !== $app_javascript && 1 === preg_match( '/const\s+responseSupportsConfirmedMotion\s*=\s*([^;]+);/', $app_javascript, $confirmed_motion_expression ), 'client confirmation-motion gate is missing' );
tv2_assert(
	false !== strpos( $confirmed_motion_expression[1], 'discoverySnapshotIsCurrent()' ) || false !== strpos( $confirmed_motion_expression[1], "responseState === 'current'" ),
	'client confirmation motion is not gated by current snapshot freshness'
);

/* Open-Meteo observations must be normalized from an explicitly GMT response. */
$open_meteo_source = file_get_contents( TRA_VEL_V2_PATH . '/inc/suppliers/class-open-meteo-supplier-adapter.php' );
tv2_assert( false !== $open_meteo_source && false !== strpos( $open_meteo_source, "'timezone'  => 'GMT'" ), 'Open-Meteo adapter does not request one unambiguous GMT timeline' );
tv2_assert( false === strpos( $open_meteo_source, "'timezone'  => 'auto'" ), 'Open-Meteo adapter still requests destination-local wall times' );
$open_meteo_adapter = new Tra_Vel_V2_Open_Meteo_Supplier_Adapter();
$normalize_weather_time = new ReflectionMethod( $open_meteo_adapter, 'normalize_utc_observed_at' );
$normalize_weather_time->setAccessible( true );
$bangkok_utc = $normalize_weather_time->invoke( $open_meteo_adapter, '2036-07-08T09:10' );
$tokyo_utc   = $normalize_weather_time->invoke( $open_meteo_adapter, '2036-07-08T09:10:00' );
tv2_assert( '2036-07-08T09:10:00Z' === $bangkok_utc && $bangkok_utc === $tokyo_utc, 'Open-Meteo multi-destination observations are not normalized to RFC3339 UTC' );
tv2_assert( null === $normalize_weather_time->invoke( $open_meteo_adapter, '2036-07-08 09:10' ), 'Open-Meteo adapter accepted an ambiguous non-ISO observation time' );

echo "Tra-Vel supplier runtime validation passed (typed map layers and exact segments, fail-closed airport geometry, destination-scoped provenance, planning/non-bookable prices, stale freshness, twelve-row ledgers, cache fallback, purge generation).\n";
