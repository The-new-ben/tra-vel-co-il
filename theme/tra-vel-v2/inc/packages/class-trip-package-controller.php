<?php
/**
 * Public total-trip package comparison REST contract.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Trip_Package_Controller extends WP_REST_Controller {
	/** @var Tra_Vel_V2_Trip_Package_Repository */
	private $repository;

	/** @var array|null */
	protected $schema;

	public function __construct() {
		$this->namespace  = 'tra-vel/v2';
		$this->rest_base  = 'packages/search';
		$this->repository = new Tra_Vel_V2_Trip_Package_Repository();
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
			'/packages/health',
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_health' ), 'permission_callback' => '__return_true' )
		);
		register_rest_route(
			$this->namespace,
			'/packages/schema',
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_contract_schema' ), 'permission_callback' => '__return_true' )
		);
		register_rest_route(
			$this->namespace,
			'/packages/cache',
			array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'purge_cache' ), 'permission_callback' => array( $this, 'can_manage_cache' ) )
		);
	}

	public function get_items( $request ) {
		$query = array(
			'origin'            => $request->get_param( 'origin' ),
			'destination'       => $request->get_param( 'destination' ),
			'departure_date'    => $request->get_param( 'departure_date' ),
			'return_date'       => $request->get_param( 'return_date' ),
			'adults'            => (int) $request->get_param( 'adults' ),
			'children'          => (int) $request->get_param( 'children' ),
			'rooms'             => (int) $request->get_param( 'rooms' ),
			'trip_style'        => $request->get_param( 'trip_style' ),
			'baggage'           => (bool) $request->get_param( 'baggage' ),
			'breakfast'         => (bool) $request->get_param( 'breakfast' ),
			'free_cancellation' => (bool) $request->get_param( 'free_cancellation' ),
			'transfers'         => (bool) $request->get_param( 'transfers' ),
			'direct_only'       => rest_sanitize_boolean( $request->get_param( 'direct_only' ) ),
			'insurance_tier'    => $request->get_param( 'insurance_tier' ),
			'max_total'         => (int) $request->get_param( 'max_total' ),
			'sort'              => $request->get_param( 'sort' ),
			'limit'             => (int) $request->get_param( 'limit' ),
			'currency'          => 'USD',
		);
		$valid = $this->validate_search( $query );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$query['nights']    = $this->nights_between( $query['departure_date'], $query['return_date'] );
		$query['trip_days'] = $query['nights'] + 1;
		$resolved = $this->repository->search( $query );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$data     = $resolved['data'];
		$packages = array_values(
			array_filter(
				$data['packages'],
				static function ( $package ) use ( $query ) {
					if ( empty( $package['stay']['party_fits'] ) ) {
						return false;
					}
					if ( $query['direct_only'] && empty( $package['flight']['direct'] ) ) {
						return false;
					}
					if ( $query['free_cancellation'] && empty( $package['stay']['free_cancellation'] ) ) {
						return false;
					}
					return ! $query['max_total'] || (float) $package['pricing']['total_party'] <= $query['max_total'];
				}
			)
		);
		usort(
			$packages,
			static function ( $a, $b ) use ( $query ) {
				if ( 'price' === $query['sort'] ) {
					return (float) $a['pricing']['total_party'] <=> (float) $b['pricing']['total_party'];
				}
				if ( 'comfort' === $query['sort'] ) {
					return (int) $b['traits']['comfort'] <=> (int) $a['traits']['comfort'];
				}
				if ( 'flexibility' === $query['sort'] ) {
					return (int) $b['traits']['flexibility'] <=> (int) $a['traits']['flexibility'];
				}
				if ( 'location' === $query['sort'] ) {
					return (int) $a['stay']['route_minutes'] <=> (int) $b['stay']['route_minutes'];
				}
				return (int) $b['score'] <=> (int) $a['score'];
			}
		);
		$packages    = array_slice( $packages, 0, $query['limit'] );
		$packages    = array_map( array( $this, 'prepare_package' ), $packages );
		$recommended = $packages ? $packages[0]['id'] : null;
		$response     = new WP_REST_Response(
			array(
				'meta' => array(
					'contract_version' => $data['contract_version'], 'data_mode' => $data['data_mode'], 'generated_at' => $resolved['runtime']['generated_at'],
					'cache_state' => $resolved['runtime']['cache_state'], 'cache_ttl' => $resolved['runtime']['cache_ttl'], 'result_count' => count( $packages ),
					'disclaimer' => 'Demo package components are estimates, not live inventory or a booking offer. Each component must be revalidated before checkout.',
					'total_price_scope' => 'whole_party', 'bundle_discount_verified' => false, 'booking_enabled' => false,
				),
				'search' => $query, 'trip' => $data['trip'], 'origin' => $data['origin'], 'destination' => $data['destination'],
				'provider_status' => $data['provider_status'], 'adapter_status' => $resolved['runtime']['adapters'],
				'packages' => $packages, 'recommended' => $recommended,
			),
			200
		);
		$response->header( 'Cache-Control', sprintf( 'public, max-age=%d, stale-while-revalidate=300', (int) $resolved['runtime']['cache_ttl'] ) );
		$response->header( 'X-Tra-Vel-Data-Mode', $data['data_mode'] );
		$response->header( 'X-Tra-Vel-Cache', $resolved['runtime']['cache_state'] );
		$response->add_link( 'self', rest_url( $this->namespace . '/' . $this->rest_base ) );
		$response->add_link( 'https://tra-vel.co.il/rels/map', home_url( '/travel-map/?layer=deals&destination=budapest' ) );
		$response->add_link( 'https://tra-vel.co.il/rels/destination', home_url( '/budapest/' ) );
		return $response;
	}

	public function get_health() {
		$result = $this->repository->search( $this->default_query() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array( 'ok' => true, 'mode' => $result['data']['data_mode'], 'adapters' => $result['runtime']['adapters'], 'cache' => array( 'state' => $result['runtime']['cache_state'], 'ttl' => $result['runtime']['cache_ttl'] ) )
		);
	}

	public function get_contract_schema() {
		$path = TRA_VEL_V2_PATH . '/assets/data/trip-package.schema.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_package_schema_missing', 'Package schema is unavailable.', array( 'status' => 503 ) );
		}
		$schema = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $schema ) ? rest_ensure_response( $schema ) : new WP_Error( 'tra_vel_package_schema_invalid', 'Package schema is invalid.', array( 'status' => 500 ) );
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
			'origin'            => array( 'type' => 'string', 'default' => 'TLV', 'pattern' => '^[A-Z]{3}$', 'sanitize_callback' => array( $this, 'sanitize_iata' ), 'validate_callback' => array( $this, 'validate_iata' ) ),
			'destination'       => array( 'type' => 'string', 'default' => 'BUD', 'pattern' => '^[A-Z]{3}$', 'sanitize_callback' => array( $this, 'sanitize_iata' ), 'validate_callback' => array( $this, 'validate_iata' ) ),
			'departure_date'    => array( 'type' => 'string', 'format' => 'date', 'default' => $defaults['departure_date'], 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( $this, 'validate_date' ) ),
			'return_date'       => array( 'type' => 'string', 'format' => 'date', 'default' => $defaults['return_date'], 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( $this, 'validate_date' ) ),
			'adults'            => array( 'type' => 'integer', 'default' => 2, 'minimum' => 1, 'maximum' => 8, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'children'          => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 8, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'rooms'             => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 4, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'trip_style'        => array( 'type' => 'string', 'default' => 'city', 'enum' => array( 'city', 'value', 'comfort', 'family', 'romantic', 'adventure' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'baggage'           => array( 'type' => 'boolean', 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'breakfast'         => array( 'type' => 'boolean', 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'free_cancellation' => array( 'type' => 'boolean', 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'transfers'         => array( 'type' => 'boolean', 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'direct_only'       => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean', 'validate_callback' => 'rest_validate_request_arg' ),
			'insurance_tier'    => array( 'type' => 'string', 'default' => 'auto', 'enum' => array( 'auto', 'none', 'essential', 'assisted', 'extended' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'max_total'         => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 50000, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'sort'              => array( 'type' => 'string', 'default' => 'smart', 'enum' => array( 'smart', 'price', 'comfort', 'flexibility', 'location' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'limit'             => array( 'type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => 30, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
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

	public function prepare_package( $package ) {
		$money_fields = array( 'flight', 'stay', 'insurance', 'transfers', 'baggage', 'breakfast', 'addons', 'total_party', 'per_person' );
		foreach ( $money_fields as $field ) {
			$package['pricing'][ $field . '_formatted' ] = '$' . number_format_i18n( (float) $package['pricing'][ $field ], 2 );
		}
		$package['flight']['duration_label'] = sprintf( '%d:%02d', floor( (int) $package['flight']['duration_minutes'] / 60 ), (int) $package['flight']['duration_minutes'] % 60 );
		$package['flight']['stops_label']    = 0 === (int) $package['flight']['stops'] ? 'ישיר' : sprintf( '%d עצירות', (int) $package['flight']['stops'] );
		$package['insurance']['medical_limit_formatted'] = $package['insurance']['medical_limit'] ? '$' . number_format_i18n( (float) $package['insurance']['medical_limit'], 0 ) : 'לא נכלל';
		return $package;
	}

	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->schema;
		}
		$this->schema = array(
			'$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => 'tra-vel-trip-package-response', 'type' => 'object',
			'properties' => array(
				'meta' => array( 'type' => 'object', 'readonly' => true ), 'search' => array( 'type' => 'object', 'readonly' => true ),
				'trip' => array( 'type' => 'object', 'readonly' => true ), 'origin' => array( 'type' => 'object', 'readonly' => true ),
				'destination' => array( 'type' => 'object', 'readonly' => true ), 'provider_status' => array( 'type' => 'object', 'readonly' => true ),
				'adapter_status' => array( 'type' => 'object', 'readonly' => true ), 'packages' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'recommended' => array( 'type' => array( 'string', 'null' ), 'readonly' => true ),
			),
		);
		return $this->schema;
	}

	private function validate_search( $query ) {
		if ( $query['origin'] === $query['destination'] ) {
			return new WP_Error( 'tra_vel_package_same_airport', 'Origin and destination must be different.', array( 'status' => 400 ) );
		}
		if ( $query['departure_date'] < current_time( 'Y-m-d' ) ) {
			return new WP_Error( 'tra_vel_package_departure_in_past', 'Departure date must not be in the past.', array( 'status' => 400 ) );
		}
		if ( $query['return_date'] <= $query['departure_date'] ) {
			return new WP_Error( 'tra_vel_package_invalid_return', 'Return date must be after departure date.', array( 'status' => 400 ) );
		}
		if ( $this->nights_between( $query['departure_date'], $query['return_date'] ) > 30 ) {
			return new WP_Error( 'tra_vel_package_trip_too_long', 'Package comparison supports stays up to 30 nights.', array( 'status' => 400 ) );
		}
		if ( ( $query['adults'] + $query['children'] ) > ( $query['rooms'] * 4 ) ) {
			return new WP_Error( 'tra_vel_package_occupancy', 'The selected party exceeds supported room occupancy.', array( 'status' => 400 ) );
		}
		return true;
	}

	private function default_query() {
		$today = current_time( 'timestamp' );
		return array(
			'origin' => 'TLV', 'destination' => 'BUD', 'departure_date' => wp_date( 'Y-m-d', strtotime( '+30 days', $today ) ), 'return_date' => wp_date( 'Y-m-d', strtotime( '+34 days', $today ) ),
			'adults' => 2, 'children' => 0, 'rooms' => 1, 'trip_style' => 'city', 'baggage' => true, 'breakfast' => true,
			'free_cancellation' => true, 'transfers' => true, 'direct_only' => true, 'insurance_tier' => 'auto', 'max_total' => 0,
			'sort' => 'smart', 'limit' => 12, 'currency' => 'USD', 'nights' => 4, 'trip_days' => 5,
		);
	}

	private function nights_between( $departure_date, $return_date ) {
		$start = new DateTime( $departure_date );
		$end   = new DateTime( $return_date );
		return max( 1, (int) $start->diff( $end )->days );
	}
}

add_action(
	'rest_api_init',
	static function () {
		$controller = new Tra_Vel_V2_Trip_Package_Controller();
		$controller->register_routes();
	}
);
