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
	 * Cached supplier repository.
	 *
	 * @var Tra_Vel_V2_Discovery_Repository
	 */
	protected $repository;

	/**
	 * Configure the controller.
	 */
	public function __construct() {
		$this->namespace = 'tra-vel/v2';
		$this->rest_base = 'discovery';
		$this->repository = new Tra_Vel_V2_Discovery_Repository();
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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/cache',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'purge_cache' ),
				'permission_callback' => array( $this, 'can_manage_cache' ),
			)
		);
	}

	/**
	 * Return filtered discovery data for the globe and route comparison UI.
	 */
	public function get_items( $request ) {
		$budget          = (int) $request->get_param( 'budget' );
		$destination     = (string) $request->get_param( 'destination' );
		$focus           = (string) $request->get_param( 'focus' );
		$direct          = (bool) $request->get_param( 'direct' );
		$query           = trim( (string) $request->get_param( 'q' ) );
		$sort            = (string) $request->get_param( 'sort' );
		$limit           = (int) $request->get_param( 'limit' );
		$layer           = (string) $request->get_param( 'layer' );
		$trip            = (string) $request->get_param( 'trip' );
		$max_stops       = (int) $request->get_param( 'max_stops' );
		$max_duration    = (int) $request->get_param( 'max_duration' );
		$allow_overnight = (bool) $request->get_param( 'allow_overnight' );
		$resolved        = $this->repository->get(
			array(
				'budget'          => $budget,
				'destination'     => $destination,
				'direct'          => $direct,
				'q'               => $query,
				'sort'            => $sort,
				'trip'            => $trip,
				'max_stops'       => $max_stops,
				'max_duration'    => $max_duration,
				'allow_overnight' => $allow_overnight,
				'limit'           => $limit,
				'layer'           => $layer,
			)
		);
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$data = $resolved['data'];
		$field_provenance = isset( $data['field_provenance'] ) && is_array( $data['field_provenance'] ) ? $data['field_provenance'] : array();
		$filter_by_budget = ! empty( $field_provenance['deals']['live'] );

		$destinations = array_values(
			array_filter(
				$data['destinations'],
				static function ( $item ) use ( $budget, $direct, $query, $trip, $filter_by_budget ) {
					if ( $filter_by_budget && $budget && (int) $item['deal']['total_per_person'] > $budget ) {
						return false;
					}
					if ( 'short' === $trip && (int) $item['deal']['nights'] > 4 ) {
						return false;
					}
					if ( 'long' === $trip && (int) $item['deal']['nights'] < 7 ) {
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
				if ( 'comfort' === $sort ) {
					$comfort_a = ( ! empty( $a['airport']['direct'] ) ? 1000 : 0 ) + (int) round( (float) $a['hotel']['rating'] * 100 ) - (int) $a['airport']['transfer_minutes'] - (int) round( (int) $a['airport']['flight_minutes'] / 10 );
					$comfort_b = ( ! empty( $b['airport']['direct'] ) ? 1000 : 0 ) + (int) round( (float) $b['hotel']['rating'] * 100 ) - (int) $b['airport']['transfer_minutes'] - (int) round( (int) $b['airport']['flight_minutes'] / 10 );
					return $comfort_b <=> $comfort_a;
				}
				$score_a = abs( (int) $a['deal']['trend_pct'] ) + ( ! empty( $a['airport']['direct'] ) ? 6 : 0 );
				$score_b = abs( (int) $b['deal']['trend_pct'] ) + ( ! empty( $b['airport']['direct'] ) ? 6 : 0 );
				return $score_b <=> $score_a;
			}
		);

		$selection_target = $destination ? $destination : $focus;
		if ( $selection_target ) {
			$requested_index = array_search( $selection_target, array_column( $destinations, 'id' ), true );
			if ( false === $requested_index ) {
				$destinations = $destination ? array() : array_slice( $destinations, 0, $limit );
			} else {
				$requested_destination = $destinations[ $requested_index ];
				$destinations          = array_slice( $destinations, 0, $limit );
				if ( ! in_array( $selection_target, array_column( $destinations, 'id' ), true ) ) {
					if ( count( $destinations ) >= $limit ) {
						array_pop( $destinations );
					}
					$destinations[] = $requested_destination;
				}
			}
		} else {
			$destinations = array_slice( $destinations, 0, $limit );
		}
		$destinations = array_map( array( $this, 'prepare_destination' ), $destinations );

		$destination_ids = array_column( $destinations, 'id' );
		$selected_id     = $selection_target && in_array( $selection_target, $destination_ids, true )
			? $selection_target
			: ( isset( $destination_ids[0] ) ? $destination_ids[0] : '' );
		$routes      = isset( $data['route_sets'][ $selected_id ] ) ? $data['route_sets'][ $selected_id ] : array();
		$routes = array_values(
			array_filter(
				$routes,
				static function ( $route ) use ( $direct, $max_stops, $max_duration, $allow_overnight ) {
					if ( $direct && 0 !== (int) $route['stops'] ) {
						return false;
					}
					if ( (int) $route['stops'] > $max_stops || (int) $route['duration_minutes'] > $max_duration ) {
						return false;
					}
					if ( ! $allow_overnight && ! empty( $route['costs']['overnight'] ) ) {
						return false;
					}
					return true;
				}
			)
		);
		usort(
			$routes,
			static function ( $a, $b ) use ( $sort ) {
				if ( 'price' === $sort ) {
					return (int) $a['costs']['total'] <=> (int) $b['costs']['total'];
				}
				if ( 'time' === $sort ) {
					return (int) $a['duration_minutes'] <=> (int) $b['duration_minutes'];
				}
				if ( 'comfort' === $sort ) {
					$comfort_a = ( 'single' === $a['ticket_mode'] ? 100 : 0 ) - ( (int) $a['stops'] * 20 ) - ( 'low' === $a['risk'] ? 0 : 35 ) - (int) round( (int) $a['duration_minutes'] / 60 );
					$comfort_b = ( 'single' === $b['ticket_mode'] ? 100 : 0 ) - ( (int) $b['stops'] * 20 ) - ( 'low' === $b['risk'] ? 0 : 35 ) - (int) round( (int) $b['duration_minutes'] / 60 );
					return $comfort_b <=> $comfort_a;
				}
				return (int) $b['score'] <=> (int) $a['score'];
			}
		);
		$routes = array_map(
			function ( $route ) use ( $selected_id ) {
				$route                   = $this->prepare_route( $route );
				$route['destination_id'] = $selected_id;
				return $route;
			},
			$routes
		);

		$recommended = isset( $routes[0] ) ? $routes[0] : null;
		$disclaimer = 'Demo data only. Prices and availability are not live or bookable until supplier adapters are connected.';
		if ( 'mixed' === $data['data_mode'] ) {
			$disclaimer = 'Some supplier data is live and some is fallback data. Confirm final price and availability before booking.';
		} elseif ( 'live' === $data['data_mode'] ) {
			$disclaimer = 'Supplier data is live but prices and availability can change until booking is confirmed.';
		}

		$response = new WP_REST_Response(
			array(
				'meta'            => array(
					'contract_version' => $data['contract_version'],
					'data_mode'        => $data['data_mode'],
					'generated_at'     => $resolved['runtime']['generated_at'],
					'cache_ttl'        => $resolved['runtime']['cache_ttl'],
					'stale_ttl'        => $resolved['runtime']['stale_ttl'],
					'cache_state'      => $resolved['runtime']['cache_state'],
					'source'           => 'supplier_registry',
					'disclaimer'       => $disclaimer,
					'active_layer'     => $layer,
					'selected_destination' => $selected_id ? $selected_id : null,
					'result_count'          => count( $destinations ),
					'filters'               => array(
						'budget'          => $budget,
						'budget_applied'  => $filter_by_budget && 0 < $budget,
						'destination'     => $destination,
						'direct'          => $direct,
						'q'               => $query,
						'sort'            => $sort,
						'trip'            => $trip,
						'max_stops'       => $max_stops,
						'max_duration'    => $max_duration,
						'allow_overnight' => $allow_overnight,
					),
				),
				'adapter_status'  => $resolved['runtime']['adapters'],
				'origin'          => $data['origin'],
				'provider_status' => $data['provider_status'],
				'field_provenance' => $field_provenance,
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
		$response->header( 'Cache-Control', sprintf( 'public, max-age=%d, stale-while-revalidate=600', (int) $resolved['runtime']['cache_ttl'] ) );
		$response->header( 'X-Tra-Vel-Data-Mode', $data['data_mode'] );
		$response->header( 'X-Tra-Vel-Cache', $resolved['runtime']['cache_state'] );
		$response->add_link( 'self', rest_url( $this->namespace . '/' . $this->rest_base ) );
		$response->add_link( 'https://tra-vel.co.il/rels/map', home_url( '/travel-map/' ) );

		return $response;
	}

	/**
	 * Return a small provider/contract readiness payload for monitoring.
	 */
	public function get_health() {
		$resolved = $this->repository->get();
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$data = $resolved['data'];

		return rest_ensure_response(
			array(
				'ok'                => true,
				'contract_version'  => $data['contract_version'],
				'data_mode'         => $data['data_mode'],
				'destination_count' => count( $data['destinations'] ),
				'route_set_count'   => count( $data['route_sets'] ),
				'providers'         => $data['provider_status'],
				'field_provenance'  => isset( $data['field_provenance'] ) ? $data['field_provenance'] : array(),
				'adapters'          => $resolved['runtime']['adapters'],
				'cache'             => array(
					'state'     => $resolved['runtime']['cache_state'],
					'ttl'       => $resolved['runtime']['cache_ttl'],
					'stale_ttl' => $resolved['runtime']['stale_ttl'],
				),
			)
		);
	}

	/**
	 * Purge supplier response caches for administrators.
	 */
	public function purge_cache() {
		return rest_ensure_response(
			array(
				'ok'               => true,
				'cache_generation' => $this->repository->purge(),
			)
		);
	}

	/**
	 * Protect cache mutation with a real capability check.
	 */
	public function can_manage_cache() {
		return current_user_can( 'manage_options' );
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
				'validate_callback' => 'rest_validate_request_arg',
			),
			'focus'       => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'direct'      => array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'q'           => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'sort'        => array(
				'type'              => 'string',
				'default'           => 'smart',
				'enum'              => array( 'smart', 'price', 'time', 'comfort' ),
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'trip'        => array(
				'type'              => 'string',
				'default'           => 'all',
				'enum'              => array( 'all', 'short', 'long' ),
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'max_stops'   => array(
				'type'              => 'integer',
				'default'           => 3,
				'minimum'           => 0,
				'maximum'           => 3,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'max_duration' => array(
				'type'              => 'integer',
				'default'           => 3000,
				'minimum'           => 60,
				'maximum'           => 6000,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'allow_overnight' => array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
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
		$total_scope = isset( $item['deal']['total_scope'] ) ? sanitize_key( $item['deal']['total_scope'] ) : 'destination_deal';
		$total_scope = in_array( $total_scope, array( 'destination_deal', 'package_inclusive' ), true ) ? $total_scope : 'destination_deal';
		$item['image']                            = TRA_VEL_V2_URI . '/assets/images/' . rawurlencode( basename( $item['image'] ) );
		$item['url']                              = $this->destination_guide_url( $item['id'] );
		$item['deal']['total_scope']              = $total_scope;
		$item['deal']['headline_formatted']       = $this->format_usd( $item['deal']['headline_price'] );
		$item['deal']['total_formatted']          = 'package_inclusive' === $total_scope ? $this->format_usd( $item['deal']['total_per_person'] ) : 'דורש חיפוש חבילה חי';
		$item['hotel']['nightly_formatted']       = $this->format_usd( $item['hotel']['nightly'] );
		$item['hotel']['per_person_formatted']    = $this->format_usd( $item['hotel']['per_person_total'] );
		$item['airport']['flight_duration_label'] = $this->format_duration( $item['airport']['flight_minutes'] );
		return $item;
	}

	/**
	 * Resolve a globe state to a reviewed, published guide URL.
	 *
	 * Unpublished destinations return the index rather than a faceted/noindex URL.
	 */
	private function destination_guide_url( $destination_id ) {
		static $guide_paths = null;

		if ( null === $guide_paths ) {
			$guide_paths = array();
			$path        = TRA_VEL_V2_PATH . '/assets/data/editorial-directory.json';
			if ( is_readable( $path ) ) {
				$directory = json_decode( (string) file_get_contents( $path ), true );
				$entries   = is_array( $directory ) && isset( $directory['destinations'] ) && is_array( $directory['destinations'] )
					? $directory['destinations']
					: array();
				foreach ( $entries as $entry ) {
					$map_state = isset( $entry['map_state'] ) ? sanitize_key( $entry['map_state'] ) : '';
					$guide_path = isset( $entry['guide_path'] ) ? '/' . ltrim( (string) $entry['guide_path'], '/' ) : '';
					if ( $map_state && 'published' === ( $entry['guide_status'] ?? '' ) && 0 === strpos( $guide_path, '/destinations/' ) ) {
						$guide_paths[ $map_state ] = $guide_path;
					}
				}
			}
		}

		$destination_id = sanitize_key( $destination_id );
		return home_url( isset( $guide_paths[ $destination_id ] ) ? $guide_paths[ $destination_id ] : '/destinations/' );
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
				'adapter_status'  => array( 'type' => 'object', 'readonly' => true ),
				'origin'          => array( 'type' => 'object', 'readonly' => true ),
				'provider_status' => array( 'type' => 'object', 'readonly' => true ),
				'field_provenance' => array( 'type' => 'object', 'readonly' => true ),
				'layers'          => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'destinations'    => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'routes'          => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'recommended'     => array( 'type' => array( 'object', 'null' ), 'readonly' => true ),
			),
		);
		return $this->schema;
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
