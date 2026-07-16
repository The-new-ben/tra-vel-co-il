<?php
/**
 * Public hotel and neighborhood comparison REST contract.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Hotel_Search_Controller extends WP_REST_Controller {
	/** @var Tra_Vel_V2_Hotel_Search_Repository */
	private $repository;

	/** @var array|null */
	protected $schema;

	public function __construct() {
		$this->namespace  = 'tra-vel/v2';
		$this->rest_base  = 'hotels/search';
		$this->repository = new Tra_Vel_V2_Hotel_Search_Repository();
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
			'/hotels/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace,
			'/hotels/schema',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_contract_schema' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace,
			'/hotels/cache',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'purge_cache' ),
				'permission_callback' => array( $this, 'can_manage_cache' ),
			)
		);
	}

	public function get_items( $request ) {
		$query = array(
			'destination'       => $request->get_param( 'destination' ),
			'checkin'           => $request->get_param( 'checkin' ),
			'checkout'          => $request->get_param( 'checkout' ),
			'adults'            => (int) $request->get_param( 'adults' ),
			'children'          => (int) $request->get_param( 'children' ),
			'rooms'             => (int) $request->get_param( 'rooms' ),
			'min_price'         => (int) $request->get_param( 'min_price' ),
			'max_price'         => (int) $request->get_param( 'max_price' ),
			'stars'             => (int) $request->get_param( 'stars' ),
			'area'              => $request->get_param( 'area' ),
			'free_cancellation' => (bool) $request->get_param( 'free_cancellation' ),
			'breakfast'         => (bool) $request->get_param( 'breakfast' ),
			'family'            => (bool) $request->get_param( 'family' ),
			'sort'              => $request->get_param( 'sort' ),
			'limit'             => (int) $request->get_param( 'limit' ),
			'currency'          => 'EUR',
		);
		$chronology = $this->validate_search( $query );
		if ( is_wp_error( $chronology ) ) {
			return $chronology;
		}
		$query['nights'] = $this->nights_between( $query['checkin'], $query['checkout'] );
		$resolved        = $this->repository->search( $query );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$data       = $resolved['data'];
		$properties = array_values(
			array_filter(
				$data['properties'],
				static function ( $property ) use ( $query ) {
					if ( $query['area'] && $property['area_id'] !== $query['area'] ) {
						return false;
					}
					if ( (int) $property['pricing']['nightly'] < $query['min_price'] || (int) $property['pricing']['nightly'] > $query['max_price'] ) {
						return false;
					}
					if ( (int) $property['stars'] < $query['stars'] ) {
						return false;
					}
					if ( $query['free_cancellation'] && empty( $property['policies']['free_cancellation'] ) ) {
						return false;
					}
					if ( $query['breakfast'] && empty( $property['amenities']['breakfast'] ) ) {
						return false;
					}
					if ( $query['family'] && ( empty( $property['amenities']['family'] ) || (int) $property['room']['sleeps'] * $query['rooms'] < $query['adults'] + $query['children'] ) ) {
						return false;
					}
					return true;
				}
			)
		);
		usort(
			$properties,
			static function ( $a, $b ) use ( $query ) {
				if ( 'price' === $query['sort'] ) {
					return (int) $a['pricing']['total_stay'] <=> (int) $b['pricing']['total_stay'];
				}
				if ( 'location' === $query['sort'] ) {
					return (int) $a['location']['route_minutes'] <=> (int) $b['location']['route_minutes'];
				}
				if ( 'rating' === $query['sort'] ) {
					return (float) $b['guest_score'] <=> (float) $a['guest_score'];
				}
				return (int) $b['score'] <=> (int) $a['score'];
			}
		);
		$properties = array_slice( $properties, 0, $query['limit'] );
		$area_index = array();
		foreach ( $data['areas'] as &$area ) {
			$area['visible_properties'] = count(
				array_filter(
					$properties,
					static function ( $property ) use ( $area ) {
						return $property['area_id'] === $area['id'];
					}
				)
			);
			$area_index[ $area['id'] ] = $area;
		}
		unset( $area );
		$traveler_count = max( 1, $query['adults'] + $query['children'] );
		$properties     = array_map(
			function ( $property ) use ( $area_index, $traveler_count, $query ) {
				return $this->prepare_property( $property, $area_index, $traveler_count, $query['nights'] );
			},
			$properties
		);
		$recommended = $properties ? $properties[0]['id'] : null;
		$disclaimer  = 'live' === $data['data_mode']
			? 'Rates and availability come from connected suppliers and must be revalidated before checkout.'
			: ( 'mixed' === $data['data_mode']
				? 'Some hotel results are live and some remain clearly marked estimates.'
				: 'Demo hotel rates are estimates and cannot be booked until a commercial inventory adapter is connected.' );

		$response = new WP_REST_Response(
			array(
				'meta'            => array(
					'contract_version' => $data['contract_version'],
					'data_mode'        => $data['data_mode'],
					'generated_at'     => $resolved['runtime']['generated_at'],
					'cache_state'      => $resolved['runtime']['cache_state'],
					'cache_ttl'        => $resolved['runtime']['cache_ttl'],
					'result_count'     => count( $properties ),
					'disclaimer'       => $disclaimer,
				),
				'search'          => $query,
				'provider_status' => $data['provider_status'],
				'adapter_status'  => $resolved['runtime']['adapters'],
				'destination'     => $data['destination'],
				'areas'           => $data['areas'],
				'properties'      => $properties,
				'recommended'     => $recommended,
			),
			200
		);
		$response->header( 'Cache-Control', sprintf( 'public, max-age=%d, stale-while-revalidate=300', (int) $resolved['runtime']['cache_ttl'] ) );
		$response->header( 'X-Tra-Vel-Data-Mode', $data['data_mode'] );
		$response->header( 'X-Tra-Vel-Cache', $resolved['runtime']['cache_state'] );
		$response->add_link( 'self', rest_url( $this->namespace . '/' . $this->rest_base ) );
		$response->add_link( 'https://tra-vel.co.il/rels/map', home_url( '/travel-map/?layer=hotels' ) );
		$response->add_link( 'https://tra-vel.co.il/rels/destination', home_url( '/destinations/budapest/' ) );
		return $response;
	}

	public function get_health() {
		$result = $this->repository->search( $this->default_query() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array(
				'ok'       => true,
				'mode'     => $result['data']['data_mode'],
				'adapters' => $result['runtime']['adapters'],
				'cache'    => array( 'state' => $result['runtime']['cache_state'], 'ttl' => $result['runtime']['cache_ttl'] ),
			)
		);
	}

	public function get_contract_schema() {
		$path = TRA_VEL_V2_PATH . '/assets/data/hotel-search.schema.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_hotel_schema_missing', 'Hotel schema is unavailable.', array( 'status' => 503 ) );
		}
		$schema = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $schema ) ? rest_ensure_response( $schema ) : new WP_Error( 'tra_vel_hotel_schema_invalid', 'Hotel schema is invalid.', array( 'status' => 500 ) );
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
			'destination'       => array( 'type' => 'string', 'default' => 'BUD', 'pattern' => '^[A-Z]{3}$', 'sanitize_callback' => array( $this, 'sanitize_destination' ), 'validate_callback' => array( $this, 'validate_destination' ) ),
			'checkin'           => array( 'type' => 'string', 'format' => 'date', 'default' => $defaults['checkin'], 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( $this, 'validate_date' ) ),
			'checkout'          => array( 'type' => 'string', 'format' => 'date', 'default' => $defaults['checkout'], 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( $this, 'validate_date' ) ),
			'adults'            => array( 'type' => 'integer', 'default' => 2, 'minimum' => 1, 'maximum' => 12, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'children'          => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 8, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'rooms'             => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 6, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'min_price'         => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 2000, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'max_price'         => array( 'type' => 'integer', 'default' => 2000, 'minimum' => 1, 'maximum' => 5000, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'stars'             => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 5, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'area'              => array( 'type' => 'string', 'default' => '', 'pattern' => '^[a-z0-9-]*$', 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'free_cancellation' => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'breakfast'         => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'family'            => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'sort'              => array( 'type' => 'string', 'default' => 'smart', 'enum' => array( 'smart', 'price', 'location', 'rating' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'limit'             => array( 'type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => 30, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
		);
	}

	public function sanitize_destination( $value ) {
		return strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) );
	}

	public function validate_destination( $value ) {
		return 1 === preg_match( '/^[A-Z]{3}$/', (string) $value );
	}

	public function validate_date( $value ) {
		$date = DateTime::createFromFormat( 'Y-m-d', (string) $value );
		return $date && $date->format( 'Y-m-d' ) === $value;
	}

	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->schema;
		}
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'tra-vel-hotel-search-response',
			'type'       => 'object',
			'properties' => array(
				'meta'            => array( 'type' => 'object', 'readonly' => true ),
				'search'          => array( 'type' => 'object', 'readonly' => true ),
				'provider_status' => array( 'type' => 'object', 'readonly' => true ),
				'adapter_status'  => array( 'type' => 'object', 'readonly' => true ),
				'destination'     => array( 'type' => 'object', 'readonly' => true ),
				'areas'           => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'properties'      => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'recommended'     => array( 'type' => array( 'string', 'null' ), 'readonly' => true ),
			),
		);
		return $this->schema;
	}

	private function prepare_property( $property, $area_index, $traveler_count, $nights ) {
		$property['area'] = isset( $area_index[ $property['area_id'] ] ) ? $area_index[ $property['area_id'] ] : null;
		foreach ( array( 'nightly', 'base', 'taxes', 'fees', 'total_stay' ) as $key ) {
			$property['pricing'][ $key . '_formatted' ] = '€' . number_format_i18n( (float) $property['pricing'][ $key ], 0 );
		}
		$property['pricing']['per_person']           = (int) ceil( $property['pricing']['total_stay'] / $traveler_count );
		$property['pricing']['per_person_formatted'] = '€' . number_format_i18n( (float) $property['pricing']['per_person'], 0 );
		$property['stay_nights']                     = $nights;
		return $property;
	}

	private function validate_search( $query ) {
		if ( $query['checkin'] < current_time( 'Y-m-d' ) ) {
			return new WP_Error( 'tra_vel_checkin_in_past', 'Check-in date must not be in the past.', array( 'status' => 400 ) );
		}
		if ( $query['checkout'] <= $query['checkin'] ) {
			return new WP_Error( 'tra_vel_invalid_checkout', 'Check-out date must be after check-in.', array( 'status' => 400 ) );
		}
		if ( $query['min_price'] > $query['max_price'] ) {
			return new WP_Error( 'tra_vel_invalid_hotel_budget', 'Minimum price cannot exceed maximum price.', array( 'status' => 400 ) );
		}
		if ( $query['adults'] + $query['children'] > $query['rooms'] * 4 ) {
			return new WP_Error( 'tra_vel_hotel_occupancy', 'The selected party exceeds the supported room occupancy.', array( 'status' => 400 ) );
		}
		return true;
	}

	private function default_query() {
		$today = current_time( 'timestamp' );
		return array(
			'destination' => 'BUD',
			'checkin' => wp_date( 'Y-m-d', strtotime( '+45 days', $today ) ),
			'checkout' => wp_date( 'Y-m-d', strtotime( '+49 days', $today ) ),
			'adults' => 2, 'children' => 0, 'rooms' => 1, 'min_price' => 0, 'max_price' => 2000, 'stars' => 0, 'area' => '',
			'free_cancellation' => false, 'breakfast' => false, 'family' => false, 'sort' => 'smart', 'limit' => 12, 'currency' => 'EUR', 'nights' => 4,
		);
	}

	private function nights_between( $checkin, $checkout ) {
		$start = new DateTime( $checkin );
		$end   = new DateTime( $checkout );
		return max( 1, (int) $start->diff( $end )->days );
	}
}

add_action(
	'rest_api_init',
	static function () {
		$controller = new Tra_Vel_V2_Hotel_Search_Controller();
		$controller->register_routes();
	}
);
