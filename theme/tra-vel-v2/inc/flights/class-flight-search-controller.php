<?php
/**
 * Public flight search REST contract.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Flight_Search_Controller extends WP_REST_Controller {
	/** @var Tra_Vel_V2_Flight_Search_Repository */
	private $repository;

	/** @var array|null */
	protected $schema;

	public function __construct() {
		$this->namespace  = 'tra-vel/v2';
		$this->rest_base  = 'flights/search';
		$this->repository = new Tra_Vel_V2_Flight_Search_Repository();
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/flights/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/flights/schema',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_contract_schema' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/flights/cache',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'purge_cache' ),
				'permission_callback' => array( $this, 'can_manage_cache' ),
			)
		);
	}

	public function get_items( $request ) {
		$query = array(
			'origin'         => $request->get_param( 'origin' ),
			'destination'    => $request->get_param( 'destination' ),
			'departure_date' => $request->get_param( 'departure_date' ),
			'return_date'    => $request->get_param( 'return_date' ),
			'adults'         => (int) $request->get_param( 'adults' ),
			'children'       => (int) $request->get_param( 'children' ),
			'infants'        => (int) $request->get_param( 'infants' ),
			'cabin'          => $request->get_param( 'cabin' ),
			'direct'         => (bool) $request->get_param( 'direct' ),
			'max_stops'      => (int) $request->get_param( 'max_stops' ),
			'max_duration'   => (int) $request->get_param( 'max_duration' ),
			'sort'           => $request->get_param( 'sort' ),
			'limit'          => (int) $request->get_param( 'limit' ),
			'currency'       => 'USD',
		);

		$chronology = $this->validate_search_chronology( $query );
		if ( is_wp_error( $chronology ) ) {
			return $chronology;
		}

		$resolved = $this->repository->search( $query );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$data   = $resolved['data'];
		$offers = array_values(
			array_filter(
				$data['offers'],
				static function ( $offer ) use ( $query ) {
					$maximum = $query['direct'] ? 0 : $query['max_stops'];
					return (int) $offer['outbound']['stops'] <= $maximum
						&& (int) $offer['inbound']['stops'] <= $maximum
						&& (int) $offer['outbound']['duration_minutes'] <= $query['max_duration']
						&& (int) $offer['inbound']['duration_minutes'] <= $query['max_duration'];
				}
			)
		);

		usort(
			$offers,
			static function ( $a, $b ) use ( $query ) {
				if ( 'price' === $query['sort'] ) {
					return (int) $a['trip_total']['total'] <=> (int) $b['trip_total']['total'];
				}
				if ( 'duration' === $query['sort'] ) {
					$a_minutes = (int) $a['outbound']['duration_minutes'] + (int) $a['inbound']['duration_minutes'];
					$b_minutes = (int) $b['outbound']['duration_minutes'] + (int) $b['inbound']['duration_minutes'];
					return $a_minutes <=> $b_minutes;
				}
				return (int) $b['score'] <=> (int) $a['score'];
			}
		);

		$offers = array_slice( $offers, 0, $query['limit'] );
		$offers = array_map( array( $this, 'prepare_offer' ), $offers );
		$recommended = $offers ? $offers[0]['id'] : null;

		$disclaimer = 'live' === $data['data_mode']
			? 'Prices and availability come from connected suppliers and must be revalidated before checkout.'
			: ( 'mixed' === $data['data_mode']
				? 'Some results use connected suppliers and some remain clearly marked estimates.'
				: 'Demo offers are not live or bookable until a commercial flight adapter is connected.' );
		$response = new WP_REST_Response(
			array(
				'meta'            => array(
					'contract_version' => $data['contract_version'],
					'data_mode'        => $data['data_mode'],
					'generated_at'     => $resolved['runtime']['generated_at'],
					'cache_state'      => $resolved['runtime']['cache_state'],
					'cache_ttl'        => $resolved['runtime']['cache_ttl'],
					'result_count'     => count( $offers ),
					'disclaimer'       => $disclaimer,
				),
				'search'          => $query,
				'provider_status' => $data['provider_status'],
				'adapter_status'  => $resolved['runtime']['adapters'],
				'airports'        => $data['airports'],
				'offers'          => $offers,
				'recommended'     => $recommended,
			),
			200
		);
		$response->header( 'Cache-Control', sprintf( 'public, max-age=%d, stale-while-revalidate=300', (int) $resolved['runtime']['cache_ttl'] ) );
		$response->header( 'X-Tra-Vel-Data-Mode', $data['data_mode'] );
		$response->header( 'X-Tra-Vel-Cache', $resolved['runtime']['cache_state'] );
		$response->add_link( 'self', rest_url( $this->namespace . '/' . $this->rest_base ) );
		$response->add_link( 'https://tra-vel.co.il/rels/map', home_url( '/travel-map/' ) );
		return $response;
	}

	public function get_health() {
		$query = $this->default_query();
		$result = $this->repository->search( $query );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array(
				'ok'       => true,
				'mode'     => $result['data']['data_mode'],
				'adapters' => $result['runtime']['adapters'],
				'cache'    => array(
					'state' => $result['runtime']['cache_state'],
					'ttl'   => $result['runtime']['cache_ttl'],
				),
			)
		);
	}

	public function get_contract_schema() {
		$path = TRA_VEL_V2_PATH . '/assets/data/flight-search.schema.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_flight_schema_missing', 'Flight schema is unavailable.', array( 'status' => 503 ) );
		}
		$schema = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $schema ) ? rest_ensure_response( $schema ) : new WP_Error( 'tra_vel_flight_schema_invalid', 'Flight schema is invalid.', array( 'status' => 500 ) );
	}

	public function purge_cache() {
		return rest_ensure_response( array( 'ok' => true, 'cache_generation' => $this->repository->purge() ) );
	}

	public function can_manage_cache() {
		return current_user_can( 'manage_options' );
	}

	public function get_collection_params() {
		$defaults = $this->default_query();
		return array(
			'origin'         => array( 'type' => 'string', 'default' => 'TLV', 'pattern' => '^[A-Z]{3}$', 'sanitize_callback' => array( $this, 'sanitize_iata' ), 'validate_callback' => array( $this, 'validate_iata' ) ),
			'destination'    => array( 'type' => 'string', 'default' => 'BKK', 'pattern' => '^[A-Z]{3}$', 'sanitize_callback' => array( $this, 'sanitize_iata' ), 'validate_callback' => array( $this, 'validate_iata' ) ),
			'departure_date' => array( 'type' => 'string', 'format' => 'date', 'default' => $defaults['departure_date'], 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( $this, 'validate_date' ) ),
			'return_date'    => array( 'type' => 'string', 'format' => 'date', 'default' => $defaults['return_date'], 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( $this, 'validate_date' ) ),
			'adults'         => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 9, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'children'       => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 8, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'infants'        => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 4, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'cabin'          => array( 'type' => 'string', 'default' => 'economy', 'enum' => array( 'economy', 'premium_economy', 'business', 'first' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'direct'         => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'max_stops'      => array( 'type' => 'integer', 'default' => 1, 'minimum' => 0, 'maximum' => 3, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'max_duration'   => array( 'type' => 'integer', 'default' => 3000, 'minimum' => 60, 'maximum' => 3000, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'sort'           => array( 'type' => 'string', 'default' => 'smart', 'enum' => array( 'smart', 'price', 'duration' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'limit'          => array( 'type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => 30, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
		);
	}

	public function sanitize_iata( $value ) {
		return strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) );
	}

	public function validate_iata( $value ) {
		return 1 === preg_match( '/^[A-Z]{3}$/', (string) $value );
	}

	public function validate_date( $value ) {
		$date = DateTime::createFromFormat( 'Y-m-d', (string) $value );
		return $date && $date->format( 'Y-m-d' ) === $value;
	}

	public function prepare_offer( $offer ) {
		$offer['outbound']['duration_label'] = $this->format_duration( $offer['outbound']['duration_minutes'] );
		$offer['inbound']['duration_label']  = $this->format_duration( $offer['inbound']['duration_minutes'] );
		$offer['outbound']['stops_label']    = $this->format_stops( $offer['outbound']['stops'] );
		$offer['inbound']['stops_label']     = $this->format_stops( $offer['inbound']['stops'] );
		foreach ( array( 'fare', 'trip_total' ) as $section ) {
			$raw = $offer[ $section ];
			foreach ( $raw as $key => $value ) {
				if ( is_numeric( $value ) ) {
					$offer[ $section ][ $key . '_formatted' ] = '$' . number_format_i18n( (float) $value, 0 );
				}
			}
		}
		return $offer;
	}

	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->schema;
		}
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'tra-vel-flight-search-response',
			'type'       => 'object',
			'properties' => array(
				'meta'            => array( 'type' => 'object', 'readonly' => true ),
				'search'          => array( 'type' => 'object', 'readonly' => true ),
				'provider_status' => array( 'type' => 'object', 'readonly' => true ),
				'adapter_status'  => array( 'type' => 'object', 'readonly' => true ),
				'airports'        => array( 'type' => 'object', 'readonly' => true ),
				'offers'          => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'recommended'     => array( 'type' => array( 'string', 'null' ), 'readonly' => true ),
			),
		);
		return $this->schema;
	}

	private function validate_search_chronology( $query ) {
		if ( $query['origin'] === $query['destination'] ) {
			return new WP_Error( 'tra_vel_same_airport', 'Origin and destination must be different.', array( 'status' => 400 ) );
		}
		if ( $query['departure_date'] < current_time( 'Y-m-d' ) ) {
			return new WP_Error( 'tra_vel_departure_in_past', 'Departure date must not be in the past.', array( 'status' => 400 ) );
		}
		if ( $query['return_date'] <= $query['departure_date'] ) {
			return new WP_Error( 'tra_vel_invalid_return', 'Return date must be after departure date.', array( 'status' => 400 ) );
		}
		if ( $query['infants'] > $query['adults'] ) {
			return new WP_Error( 'tra_vel_too_many_infants', 'Each infant must travel with an adult.', array( 'status' => 400 ) );
		}
		return true;
	}

	private function default_query() {
		$today = current_time( 'timestamp' );
		return array(
			'origin'         => 'TLV',
			'destination'    => 'BKK',
			'departure_date' => wp_date( 'Y-m-d', strtotime( '+30 days', $today ) ),
			'return_date'    => wp_date( 'Y-m-d', strtotime( '+44 days', $today ) ),
			'adults'         => 1,
			'children'       => 0,
			'infants'        => 0,
			'cabin'          => 'economy',
			'direct'         => false,
			'max_stops'      => 1,
			'max_duration'   => 3000,
			'sort'           => 'smart',
			'limit'          => 12,
			'currency'       => 'USD',
		);
	}

	private function format_duration( $minutes ) {
		return sprintf( '%d:%02d', floor( (int) $minutes / 60 ), (int) $minutes % 60 );
	}

	private function format_stops( $stops ) {
		if ( 0 === (int) $stops ) {
			return 'ישיר';
		}
		return 1 === (int) $stops ? 'עצירה אחת' : sprintf( '%d עצירות', (int) $stops );
	}
}

add_action(
	'rest_api_init',
	static function () {
		$controller = new Tra_Vel_V2_Flight_Search_Controller();
		$controller->register_routes();
	}
);
