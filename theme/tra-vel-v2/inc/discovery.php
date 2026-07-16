<?php
/**
 * Supplier-ready public discovery API for the Tra-Vel globe.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Discovery_Controller extends WP_REST_Controller {
	/**
	 * Cached response schema.
	 *
	 * @var array|null
	 */
	protected $schema;

	/**
	 * Configure the controller.
	 */
	public function __construct() {
		$this->namespace = 'tra-vel/v2';
		$this->rest_base = 'discovery';
	}

	/**
	 * Register public read-only routes.
	 */
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
			'/' . $this->rest_base . '/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/schema',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_contract_schema' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return filtered discovery data for the globe and route comparison UI.
	 */
	public function get_items( $request ) {
		$data = $this->load_json_file( TRA_VEL_V2_PATH . '/assets/data/discovery-demo.json' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$budget      = (int) $request->get_param( 'budget' );
		$destination = (string) $request->get_param( 'destination' );
		$direct      = (bool) $request->get_param( 'direct' );
		$query       = trim( (string) $request->get_param( 'q' ) );
		$sort        = (string) $request->get_param( 'sort' );
		$limit       = (int) $request->get_param( 'limit' );
		$layer       = (string) $request->get_param( 'layer' );

		$destinations = array_values(
			array_filter(
				$data['destinations'],
				static function ( $item ) use ( $budget, $direct, $query ) {
					if ( $budget && (int) $item['deal']['total_per_person'] > $budget ) {
						return false;
					}
					if ( $direct && empty( $item['airport']['direct'] ) ) {
						return false;
					}
					if ( $query ) {
						$haystack = strtolower( implode( ' ', array( $item['id'], $item['city'], $item['country'], $item['airport']['code'], $item['airport']['name'] ) ) );
						if ( false === strpos( $haystack, strtolower( $query ) ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);

		usort(
			$destinations,
			static function ( $a, $b ) use ( $sort ) {
				if ( 'price' === $sort ) {
					return (int) $a['deal']['total_per_person'] <=> (int) $b['deal']['total_per_person'];
				}
				if ( 'time' === $sort ) {
					return (int) $a['airport']['flight_minutes'] <=> (int) $b['airport']['flight_minutes'];
				}
				$score_a = abs( (int) $a['deal']['trend_pct'] ) + ( ! empty( $a['airport']['direct'] ) ? 6 : 0 );
				$score_b = abs( (int) $b['deal']['trend_pct'] ) + ( ! empty( $b['airport']['direct'] ) ? 6 : 0 );
				return $score_b <=> $score_a;
			}
		);

		$destinations = array_slice( $destinations, 0, $limit );
		$destinations = array_map( array( $this, 'prepare_destination' ), $destinations );

		$selected_id = $destination ? $destination : 'bangkok';
		$routes      = isset( $data['route_sets'][ $selected_id ] ) ? $data['route_sets'][ $selected_id ] : array();
		if ( $direct ) {
			$routes = array_values(
				array_filter(
					$routes,
					static function ( $route ) {
						return 0 === (int) $route['stops'];
					}
				)
			);
		}
		usort(
			$routes,
			static function ( $a, $b ) use ( $sort ) {
				if ( 'price' === $sort ) {
					return (int) $a['costs']['total'] <=> (int) $b['costs']['total'];
				}
				if ( 'time' === $sort ) {
					return (int) $a['duration_minutes'] <=> (int) $b['duration_minutes'];
				}
				return (int) $b['score'] <=> (int) $a['score'];
			}
		);
		$routes = array_map( array( $this, 'prepare_route' ), $routes );

		$recommended = null;
		foreach ( $routes as $route ) {
			if ( null === $recommended || $route['score'] > $recommended['score'] ) {
				$recommended = $route;
			}
		}

		$response = new WP_REST_Response(
			array(
				'meta'            => array(
					'contract_version' => $data['contract_version'],
					'data_mode'        => $data['data_mode'],
					'generated_at'     => gmdate( 'c' ),
					'cache_ttl'        => 300,
					'source'           => 'curated_demo_contract',
					'disclaimer'       => 'Demo data only. Prices and availability are not live or bookable until supplier adapters are connected.',
					'active_layer'     => $layer,
					'result_count'     => count( $destinations ),
					'filters'          => array(
						'budget'      => $budget,
						'destination' => $destination,
						'direct'      => $direct,
						'q'           => $query,
						'sort'        => $sort,
					),
				),
				'origin'          => $data['origin'],
				'provider_status' => $data['provider_status'],
				'layers'          => array(
					array( 'id' => 'deals', 'label' => 'מחירים', 'available' => true ),
					array( 'id' => 'hotels', 'label' => 'מלונות', 'available' => true ),
					array( 'id' => 'airports', 'label' => 'שדות תעופה', 'available' => true ),
					array( 'id' => 'weather', 'label' => 'מזג אוויר', 'available' => true ),
				),
				'destinations'    => $destinations,
				'routes'          => $routes,
				'recommended'     => $recommended,
			),
			200
		);
		$response->header( 'Cache-Control', 'public, max-age=300, stale-while-revalidate=600' );
		$response->header( 'X-Tra-Vel-Data-Mode', $data['data_mode'] );
		$response->add_link( 'self', rest_url( $this->namespace . '/' . $this->rest_base ) );
		$response->add_link( 'https://tra-vel.co.il/rels/map', home_url( '/travel-map/' ) );

		return $response;
	}

	/**
	 * Return a small provider/contract readiness payload for monitoring.
	 */
	public function get_health() {
		$data = $this->load_json_file( TRA_VEL_V2_PATH . '/assets/data/discovery-demo.json' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return rest_ensure_response(
			array(
				'ok'                => true,
				'contract_version'  => $data['contract_version'],
				'data_mode'         => $data['data_mode'],
				'destination_count' => count( $data['destinations'] ),
				'route_set_count'   => count( $data['route_sets'] ),
				'providers'         => $data['provider_status'],
			)
		);
	}

	/**
	 * Return the durable JSON contract used by supplier adapters.
	 */
	public function get_contract_schema() {
		$schema = $this->load_json_file( TRA_VEL_V2_PATH . '/assets/data/discovery.schema.json' );
		return is_wp_error( $schema ) ? $schema : rest_ensure_response( $schema );
	}

	/**
	 * Register query parameter validation and defaults.
	 */
	public function get_collection_params() {
		return array(
			'budget'      => array(
				'type'              => 'integer',
				'default'           => 5000,
				'minimum'           => 200,
				'maximum'           => 25000,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'layer'       => array(
				'type'              => 'string',
				'default'           => 'deals',
				'enum'              => array( 'deals', 'hotels', 'airports', 'weather' ),
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'destination' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
			),
			'direct'      => array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'q'           => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'sort'        => array(
				'type'              => 'string',
				'default'           => 'smart',
				'enum'              => array( 'smart', 'price', 'time' ),
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'limit'       => array(
				'type'              => 'integer',
				'default'           => 24,
				'minimum'           => 1,
				'maximum'           => 50,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}

	/**
	 * Shape destination output and add display-safe derived values.
	 */
	public function prepare_destination( $item ) {
		$item['image']                            = TRA_VEL_V2_URI . '/assets/images/' . rawurlencode( basename( $item['image'] ) );
		$item['url']                              = home_url( '/' . $item['id'] . '/' );
		$item['deal']['headline_formatted']       = $this->format_usd( $item['deal']['headline_price'] );
		$item['deal']['total_formatted']          = $this->format_usd( $item['deal']['total_per_person'] );
		$item['hotel']['nightly_formatted']       = $this->format_usd( $item['hotel']['nightly'] );
		$item['hotel']['per_person_formatted']    = $this->format_usd( $item['hotel']['per_person_total'] );
		$item['airport']['flight_duration_label'] = $this->format_duration( $item['airport']['flight_minutes'] );
		return $item;
	}

	/**
	 * Add formatted cost/duration values to a route option.
	 */
	public function prepare_route( $route ) {
		$route['duration_label']           = $this->format_duration( $route['duration_minutes'] );
		$raw_costs                         = $route['costs'];
		$route['costs']['total_formatted'] = $this->format_usd( $route['costs']['total'] );
		foreach ( $raw_costs as $key => $value ) {
			if ( is_numeric( $value ) ) {
				$route['costs'][ $key . '_formatted' ] = $this->format_usd( $value );
			}
		}
		return $route;
	}

	/**
	 * Expose a concise response schema through OPTIONS.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->schema;
		}
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'tra-vel-discovery',
			'type'       => 'object',
			'properties' => array(
				'meta'            => array( 'type' => 'object', 'readonly' => true ),
				'origin'          => array( 'type' => 'object', 'readonly' => true ),
				'provider_status' => array( 'type' => 'object', 'readonly' => true ),
				'layers'          => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'destinations'    => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'routes'          => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'recommended'     => array( 'type' => array( 'object', 'null' ), 'readonly' => true ),
			),
		);
		return $this->schema;
	}

	/**
	 * Read and decode a bundled JSON contract.
	 */
	private function load_json_file( $path ) {
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_discovery_missing', 'Discovery contract is unavailable.', array( 'status' => 503 ) );
		}
		$decoded = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $decoded ) || JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'tra_vel_discovery_invalid', 'Discovery contract is invalid.', array( 'status' => 500 ) );
		}
		return $decoded;
	}

	/**
	 * Format USD demo values consistently.
	 */
	private function format_usd( $amount ) {
		return '$' . number_format_i18n( (float) $amount, 0 );
	}

	/**
	 * Format minute durations without locale ambiguity.
	 */
	private function format_duration( $minutes ) {
		$hours = floor( (int) $minutes / 60 );
		$mins  = (int) $minutes % 60;
		return sprintf( '%d:%02d', $hours, $mins );
	}
}

add_action(
	'rest_api_init',
	static function () {
		$controller = new Tra_Vel_V2_Discovery_Controller();
		$controller->register_routes();
	}
);
