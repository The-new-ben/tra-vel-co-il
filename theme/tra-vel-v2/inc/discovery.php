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
		$data             = $resolved['data'];
		$airport_registry = $this->validated_airport_registry( $data );
		if ( is_wp_error( $airport_registry ) ) {
			return $airport_registry;
		}
		$exploration_hubs = $this->validated_exploration_hubs( $data );
		if ( is_wp_error( $exploration_hubs ) ) {
			return $exploration_hubs;
		}
		$cache_state     = isset( $resolved['runtime']['cache_state'] ) ? sanitize_key( $resolved['runtime']['cache_state'] ) : 'degraded_fallback';
		$cache_freshness = $this->cache_freshness_state( $cache_state );
		$field_provenance = isset( $data['field_provenance'] ) && is_array( $data['field_provenance'] ) ? $data['field_provenance'] : array();
		$live_deal_destination_ids = array_values(
			array_intersect(
				$this->field_live_destination_ids( $field_provenance, 'deals' ),
				$this->field_current_destination_ids( $field_provenance, 'deals' )
			)
		);
		if ( 'current' !== $cache_freshness ) {
			$live_deal_destination_ids = array();
		}
		$total_destination_count = count( isset( $data['destinations'] ) ? (array) $data['destinations'] : array() );
		$budget_coverage = ! $budget || ! $live_deal_destination_ids
			? 'none'
			: ( count( $live_deal_destination_ids ) === $total_destination_count ? 'full' : 'partial' );
		$filter_by_budget = 'none' !== $budget_coverage;

		$destinations = array_values(
			array_filter(
				$data['destinations'],
				static function ( $item ) use ( $budget, $direct, $query, $trip, $live_deal_destination_ids ) {
					if ( $budget && in_array( $item['id'], $live_deal_destination_ids, true ) && (int) $item['deal']['total_per_person'] > $budget ) {
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
				$direct_order = (int) ! empty( $b['airport']['direct'] ) <=> (int) ! empty( $a['airport']['direct'] );
				if ( 0 !== $direct_order ) {
					return $direct_order;
				}

				$flight_order = (int) $a['airport']['flight_minutes'] <=> (int) $b['airport']['flight_minutes'];
				if ( 0 !== $flight_order ) {
					return $flight_order;
				}

				$transfer_order = (int) $a['airport']['transfer_minutes'] <=> (int) $b['airport']['transfer_minutes'];
				if ( 0 !== $transfer_order ) {
					return $transfer_order;
				}

				return strcmp( (string) $a['id'], (string) $b['id'] );
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

		$selected_data_mode = $this->destination_data_mode( $field_provenance, $selected_id );
		$source_freshness  = $this->destination_source_freshness( $field_provenance, $selected_id );
		$freshness         = $this->effective_freshness( $cache_freshness, $source_freshness, $selected_data_mode );
		$recommended       = isset( $routes[0] ) ? $routes[0] : null;
		$selected_plan     = $this->prepare_selected_plan( $selected_id, $destinations, $routes, $data, $field_provenance, $freshness, $cache_freshness, $source_freshness );
		$map_entities      = $this->prepare_map_entities( $layer, $destinations, $airport_registry, $field_provenance, $cache_freshness );
		$map_segments      = $this->prepare_map_segments( $recommended, $selected_id, $destinations, $data, $airport_registry, $field_provenance, $cache_freshness );
		if ( is_wp_error( $map_segments ) ) {
			return $map_segments;
		}
		$disclaimer = 'Demo data only. Prices and availability are not live or bookable until supplier adapters are connected.';
		if ( 'stale' === $cache_freshness ) {
			$disclaimer = 'The latest supplier refresh failed. This is the last observed supplier snapshot, not a current quote. Verify price, availability, and conditions before approval.';
		} elseif ( 'refreshing' === $cache_freshness ) {
			$disclaimer = 'A supplier refresh is in progress. The last observed snapshot remains visible and must be verified before approval.';
		} elseif ( 'fallback' === $cache_freshness ) {
			$disclaimer = 'Supplier data is unavailable. Editorial planning data remains visible; prices and availability require a new live search.';
		} elseif ( in_array( $source_freshness, array( 'stale', 'future', 'unknown' ), true ) ) {
			$disclaimer = 'Supplier data was retrieved, but at least one observation is too old, future-dated, or missing a trustworthy timestamp. Refresh and verify price, availability, and conditions before approval.';
		} elseif ( 'mixed' === $selected_data_mode ) {
			$disclaimer = 'Some supplier data is live and some is fallback data. Confirm final price and availability before booking.';
		} elseif ( 'live' === $selected_data_mode ) {
			$disclaimer = 'Supplier data is live but prices and availability can change until booking is confirmed.';
		}

		$response = new WP_REST_Response(
			array(
				'meta'            => array(
					'contract_version' => $data['contract_version'],
					'data_mode'        => $selected_data_mode,
					'dataset_data_mode' => $data['data_mode'],
					'freshness'        => $freshness,
					'cache_freshness'  => $cache_freshness,
					'source_freshness' => $source_freshness,
					'generated_at'     => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $resolved['runtime']['generated_at'] ) ),
					'cache_ttl'        => $resolved['runtime']['cache_ttl'],
					'stale_ttl'        => $resolved['runtime']['stale_ttl'],
					'cache_state'      => $cache_state,
					'source'           => 'supplier_registry',
					'disclaimer'       => $disclaimer,
					'active_layer'     => $layer,
					'selected_destination' => $selected_id ? $selected_id : null,
					'result_count'          => count( $destinations ),
					'filters'               => array(
						'budget'          => $budget,
						'budget_applied'  => 'full' === $budget_coverage,
						'budget_coverage' => $budget_coverage,
						'budget_filter_active' => $filter_by_budget && 0 < $budget,
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
				'exploration_hubs' => $exploration_hubs,
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
				'map_entities'    => $map_entities,
				'map_segments'    => $map_segments,
				'selected_plan'   => $selected_plan,
			),
			200
		);
		if ( in_array( $freshness, array( 'stale', 'fallback' ), true ) ) {
			$response->header( 'Cache-Control', 'private, no-store' );
		} elseif ( 'refreshing' === $freshness ) {
			$response->header( 'Cache-Control', 'public, max-age=30, stale-while-revalidate=60' );
		} else {
			$response->header( 'Cache-Control', sprintf( 'public, max-age=%d, stale-while-revalidate=600', (int) $resolved['runtime']['cache_ttl'] ) );
		}
		$response->header( 'X-Tra-Vel-Data-Mode', $selected_data_mode );
		$response->header( 'X-Tra-Vel-Cache', $cache_state );
		$response->header( 'X-Tra-Vel-Freshness', $freshness );
		$response->header( 'X-Tra-Vel-Cache-Freshness', $cache_freshness );
		$response->header( 'X-Tra-Vel-Source-Freshness', $source_freshness );
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
	 * Read a fixed, theme-owned JSON contract without exposing arbitrary paths.
	 */
	private function load_json_file( $path ) {
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_discovery_schema_missing', 'The discovery schema is unavailable.', array( 'status' => 503 ) );
		}
		$decoded = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $decoded ) || JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'tra_vel_discovery_schema_invalid', 'The discovery schema is invalid.', array( 'status' => 500 ) );
		}
		return $decoded;
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
	 * Validate the canonical airport geometry before any map response is exposed.
	 *
	 * Airport codes are identifiers, not user-facing copy. They must remain exact
	 * uppercase IATA codes and every route connection must resolve to one owned
	 * coordinate pair. The controller intentionally returns an error instead of
	 * drawing an approximate or incomplete route.
	 */
	private function validated_airport_registry( $data ) {
		if ( empty( $data['airport_registry'] ) || ! is_array( $data['airport_registry'] ) ) {
			return $this->invalid_map_contract();
		}

		$registry = array();
		foreach ( $data['airport_registry'] as $registry_code => $airport ) {
			if ( ! is_string( $registry_code )
				|| ! preg_match( '/^[A-Z]{3}$/', $registry_code )
				|| ! is_array( $airport )
				|| ! isset( $airport['code'], $airport['name'], $airport['latitude'], $airport['longitude'] )
				|| ! array_key_exists( 'destination_id', $airport )
				|| $registry_code !== $airport['code']
				|| ! is_string( $airport['name'] )
				|| strlen( trim( $airport['name'] ) ) < 2
				|| ( null !== $airport['destination_id'] && ( ! is_string( $airport['destination_id'] ) || ! preg_match( '/^[a-z0-9-]{2,60}$/', $airport['destination_id'] ) ) )
				|| ! $this->valid_map_coordinate_pair( $airport['latitude'], $airport['longitude'] )
			) {
				return $this->invalid_map_contract();
			}
			$registry[ $registry_code ] = array(
				'code'      => $registry_code,
				'label'     => sanitize_text_field( $airport['name'] ),
				'destination_id' => null === $airport['destination_id'] ? null : sanitize_key( $airport['destination_id'] ),
				'latitude'  => (float) $airport['latitude'],
				'longitude' => (float) $airport['longitude'],
			);
		}

		$origin = isset( $data['origin'] ) && is_array( $data['origin'] ) ? $data['origin'] : array();
		$origin_code = isset( $origin['code'] ) && is_string( $origin['code'] ) ? $origin['code'] : '';
		if ( ! preg_match( '/^[A-Z]{3}$/', $origin_code )
			|| ! isset( $registry[ $origin_code ] )
			|| null !== $registry[ $origin_code ]['destination_id']
			|| ! isset( $origin['latitude'], $origin['longitude'] )
			|| ! $this->valid_map_coordinate_pair( $origin['latitude'], $origin['longitude'] )
			|| abs( (float) $origin['latitude'] - $registry[ $origin_code ]['latitude'] ) > 0.000001
			|| abs( (float) $origin['longitude'] - $registry[ $origin_code ]['longitude'] ) > 0.000001
		) {
			return $this->invalid_map_contract();
		}

		$destination_airports = array();
		foreach ( isset( $data['destinations'] ) && is_array( $data['destinations'] ) ? $data['destinations'] : array() as $destination ) {
			$destination_id = isset( $destination['id'] ) && is_string( $destination['id'] ) ? $destination['id'] : '';
			$airport_code   = isset( $destination['airport']['code'] ) && is_string( $destination['airport']['code'] ) ? $destination['airport']['code'] : '';
			if ( ! preg_match( '/^[a-z0-9-]{2,60}$/', $destination_id )
				|| ! preg_match( '/^[A-Z]{3}$/', $airport_code )
				|| ! isset( $registry[ $airport_code ] )
				|| $destination_id !== $registry[ $airport_code ]['destination_id']
				|| ! isset( $destination['geo']['latitude'], $destination['geo']['longitude'] )
				|| ! $this->valid_map_coordinate_pair( $destination['geo']['latitude'], $destination['geo']['longitude'] )
			) {
				return $this->invalid_map_contract();
			}
			$destination_airports[ $destination_id ] = $airport_code;
		}

		foreach ( isset( $data['route_sets'] ) && is_array( $data['route_sets'] ) ? $data['route_sets'] : array() as $destination_id => $routes ) {
			if ( ! is_string( $destination_id ) || ! isset( $destination_airports[ $destination_id ] ) || ! is_array( $routes ) ) {
				return $this->invalid_map_contract();
			}
			foreach ( $routes as $route ) {
				if ( ! is_array( $route ) || ! isset( $route['stops'] ) || ! is_int( $route['stops'] ) || $route['stops'] < 0 || $route['stops'] > 1 ) {
					return $this->invalid_map_contract();
				}
				$via = array_key_exists( 'via', $route ) ? $route['via'] : null;
				if ( null !== $via && ( ! is_string( $via ) || ! preg_match( '/^[A-Z]{3}$/', $via ) || ! isset( $registry[ $via ] ) ) ) {
					return $this->invalid_map_contract();
				}
				if ( ( 0 === $route['stops'] && null !== $via )
					|| ( 1 === $route['stops'] && null === $via )
					|| ( null !== $via && in_array( $via, array( $origin_code, $destination_airports[ $destination_id ] ), true ) )
				) {
					return $this->invalid_map_contract();
				}
			}
		}

		return $registry;
	}

	/**
	 * Validate the non-commercial geographic coverage layer.
	 *
	 * Hubs identify a place and the radius in which a raw Earth click may resolve
	 * to it. They never carry inventory, prices, ratings, properties, or supplier
	 * identities; every exposed scope explicitly continues to a contextual search.
	 */
	private function validated_exploration_hubs( $data ) {
		$hubs = isset( $data['exploration_hubs'] ) && is_array( $data['exploration_hubs'] ) ? array_values( $data['exploration_hubs'] ) : array();
		if ( count( $hubs ) < 30 || count( $hubs ) > 80 ) {
			return $this->invalid_map_contract();
		}

		$allowed_keys = array( 'id', 'city', 'country', 'geo', 'radius_km', 'iata_search_code', 'live_search_scopes' );
		$required_scopes = array( 'route', 'stay', 'activities', 'insurance', 'connectivity', 'equipment' );
		$destination_ids = array_values( array_filter( array_map( 'sanitize_key', array_column( isset( $data['destinations'] ) && is_array( $data['destinations'] ) ? $data['destinations'] : array(), 'id' ) ) ) );
		$seen_ids = array();
		$seen_places = array();
		$seen_coordinates = array();
		$seen_codes = array();
		$prepared = array();
		foreach ( $hubs as $hub ) {
			if ( ! is_array( $hub ) || array_diff( array_keys( $hub ), $allowed_keys ) ) {
				return $this->invalid_map_contract();
			}
			$id = isset( $hub['id'] ) && is_string( $hub['id'] ) ? $hub['id'] : '';
			$city = isset( $hub['city'] ) && is_string( $hub['city'] ) ? trim( $hub['city'] ) : '';
			$country = isset( $hub['country'] ) && is_string( $hub['country'] ) ? trim( $hub['country'] ) : '';
			$geo = isset( $hub['geo'] ) && is_array( $hub['geo'] ) ? $hub['geo'] : array();
			$radius = isset( $hub['radius_km'] ) && is_int( $hub['radius_km'] ) ? $hub['radius_km'] : 0;
			$code = isset( $hub['iata_search_code'] ) && is_string( $hub['iata_search_code'] ) ? $hub['iata_search_code'] : '';
			$scopes = isset( $hub['live_search_scopes'] ) && is_array( $hub['live_search_scopes'] ) ? array_values( $hub['live_search_scopes'] ) : array();
			$sorted_scopes = $scopes;
			$sorted_required_scopes = $required_scopes;
			sort( $sorted_scopes );
			sort( $sorted_required_scopes );
			$place_key = $city . '|' . $country;
			$coordinate_key = isset( $geo['latitude'], $geo['longitude'] ) ? (string) $geo['latitude'] . '|' . (string) $geo['longitude'] : '';
			if ( ! preg_match( '/^[a-z0-9-]{2,60}$/', $id )
				|| in_array( $id, $destination_ids, true )
				|| isset( $seen_ids[ $id ] )
				|| strlen( $city ) < 2
				|| strlen( $country ) < 2
				|| isset( $seen_places[ $place_key ] )
				|| array_diff( array_keys( $geo ), array( 'latitude', 'longitude' ) )
				|| ! isset( $geo['latitude'], $geo['longitude'] )
				|| ! $this->valid_map_coordinate_pair( $geo['latitude'], $geo['longitude'] )
				|| isset( $seen_coordinates[ $coordinate_key ] )
				|| $radius < 40
				|| $radius > 750
				|| ( $code && ( ! preg_match( '/^[A-Z]{3}$/', $code ) || isset( $seen_codes[ $code ] ) ) )
				|| $sorted_scopes !== $sorted_required_scopes
			) {
				return $this->invalid_map_contract();
			}

			$seen_ids[ $id ] = true;
			$seen_places[ $place_key ] = true;
			$seen_coordinates[ $coordinate_key ] = true;
			if ( $code ) {
				$seen_codes[ $code ] = true;
			}
			$prepared_hub = array(
				'id'                 => $id,
				'city'               => sanitize_text_field( $city ),
				'country'            => sanitize_text_field( $country ),
				'geo'                => array(
					'latitude'  => (float) $geo['latitude'],
					'longitude' => (float) $geo['longitude'],
				),
				'radius_km'          => $radius,
				'live_search_scopes' => $required_scopes,
			);
			if ( $code ) {
				$prepared_hub['iata_search_code'] = $code;
			}
			$prepared[] = $prepared_hub;
		}

		return $prepared;
	}

	/**
	 * WGS84 coordinates must be finite JSON numbers within the legal ranges.
	 */
	private function valid_map_coordinate_pair( $latitude, $longitude ) {
		$latitude_is_number  = is_int( $latitude ) || is_float( $latitude );
		$longitude_is_number = is_int( $longitude ) || is_float( $longitude );
		return $latitude_is_number
			&& $longitude_is_number
			&& is_finite( (float) $latitude )
			&& is_finite( (float) $longitude )
			&& (float) $latitude >= -90
			&& (float) $latitude <= 90
			&& (float) $longitude >= -180
			&& (float) $longitude <= 180;
	}

	/**
	 * Return one stable error for any geometry or route-reference violation.
	 */
	private function invalid_map_contract() {
		return new WP_Error(
			'tra_vel_map_contract_invalid',
			'The discovery map geometry is unavailable because its typed geometry contract is invalid.',
			array( 'status' => 500 )
		);
	}

	/**
	 * Build the requested map layer from the same filtered destination collection.
	 */
	private function prepare_map_entities( $layer, $destinations, $airport_registry, $field_provenance, $cache_freshness ) {
		$layer_contracts = array(
			'deals'    => array( 'kind' => 'deal', 'field' => 'deals', 'action' => 'search_packages' ),
			'hotels'   => array( 'kind' => 'hotel_area', 'field' => 'hotels', 'action' => 'search_hotels' ),
			'airports' => array( 'kind' => 'airport', 'field' => 'airports', 'action' => 'compare_routes' ),
			'weather'  => array( 'kind' => 'weather', 'field' => 'weather_current', 'action' => 'plan_for_weather' ),
		);
		if ( ! isset( $layer_contracts[ $layer ] ) ) {
			return array();
		}
		$contract = $layer_contracts[ $layer ];
		$entities = array();

		foreach ( (array) $destinations as $destination ) {
			$destination_id = isset( $destination['id'] ) ? sanitize_key( $destination['id'] ) : '';
			$airport_code   = isset( $destination['airport']['code'] ) ? (string) $destination['airport']['code'] : '';
			$hotel_area     = isset( $destination['hotel']['area'] ) ? sanitize_text_field( $destination['hotel']['area'] ) : '';
			$coordinates    = 'airport' === $contract['kind'] && isset( $airport_registry[ $airport_code ] )
				? array( $airport_registry[ $airport_code ]['latitude'], $airport_registry[ $airport_code ]['longitude'] )
				: array( (float) $destination['geo']['latitude'], (float) $destination['geo']['longitude'] );
			$truth = $this->map_truth_context( $contract['field'], $destination_id, $destination, $field_provenance, $cache_freshness );
			$label = isset( $destination['city'] ) ? sanitize_text_field( $destination['city'] ) : $destination_id;
			$summary = $label;
			$price = null;

			if ( 'deal' === $contract['kind'] ) {
				$summary = isset( $destination['deal']['insight'] ) ? sanitize_text_field( $destination['deal']['insight'] ) : $label;
				$price   = $this->prepare_map_price(
					isset( $destination['deal']['total_per_person'] ) ? $destination['deal']['total_per_person'] : null,
					isset( $destination['deal']['currency'] ) ? $destination['deal']['currency'] : null,
					'per_person_total',
					$truth['truth_state']
				);
			} elseif ( 'hotel_area' === $contract['kind'] ) {
				$label   = isset( $destination['hotel']['area'] ) ? sanitize_text_field( $destination['hotel']['area'] ) : $label;
				$summary = trim( $label . ' - ' . ( isset( $destination['city'] ) ? sanitize_text_field( $destination['city'] ) : $destination_id ) );
				$price   = $this->prepare_map_price(
					isset( $destination['hotel']['nightly'] ) ? $destination['hotel']['nightly'] : null,
					isset( $destination['hotel']['currency'] ) ? $destination['hotel']['currency'] : null,
					'per_night',
					$truth['truth_state']
				);
			} elseif ( 'airport' === $contract['kind'] ) {
				$label   = $airport_registry[ $airport_code ]['label'];
				$summary = $airport_code . ' - ' . $label;
			} elseif ( 'weather' === $contract['kind'] ) {
				$condition = isset( $destination['weather']['condition'] ) ? sanitize_text_field( $destination['weather']['condition'] ) : $label;
				$temperature = isset( $destination['weather']['temperature_c'] ) && is_numeric( $destination['weather']['temperature_c'] )
					? (string) (float) $destination['weather']['temperature_c'] . ' C'
					: '';
				$summary = trim( $condition . ( $temperature ? ' - ' . $temperature : '' ) );
			}

			$entities[] = array(
				'id'             => $contract['kind'] . ':' . $destination_id,
				'kind'           => $contract['kind'],
				'destination_id' => $destination_id,
				'lat'            => (float) $coordinates[0],
				'lng'            => (float) $coordinates[1],
				'label'          => $label,
				'summary'        => $summary,
				'data_mode'      => $truth['data_mode'],
				'truth_state'    => $truth['truth_state'],
				'freshness'      => $truth['freshness'],
				'action'         => $this->prepare_map_action( $contract['action'], $destination_id, $airport_code, $hotel_area ),
				'provenance'     => $truth['provenance'],
				'price'          => $price,
			);
		}

		return $entities;
	}

	/**
	 * Build exact point-to-point geometry for the selected route only.
	 */
	private function prepare_map_segments( $route, $destination_id, $destinations, $data, $airport_registry, $field_provenance, $cache_freshness ) {
		if ( ! is_array( $route ) || ! $destination_id ) {
			return array();
		}
		$route_id = isset( $route['id'] ) && is_string( $route['id'] ) ? $route['id'] : '';
		if ( ! preg_match( '/^[a-z0-9-]{2,80}$/', $route_id ) ) {
			return $this->invalid_map_contract();
		}

		$destination = null;
		foreach ( (array) $destinations as $candidate ) {
			if ( isset( $candidate['id'] ) && $destination_id === $candidate['id'] ) {
				$destination = $candidate;
				break;
			}
		}
		if ( ! is_array( $destination ) ) {
			return $this->invalid_map_contract();
		}

		$origin_code      = isset( $data['origin']['code'] ) ? (string) $data['origin']['code'] : '';
		$destination_code = isset( $destination['airport']['code'] ) ? (string) $destination['airport']['code'] : '';
		$via_code         = array_key_exists( 'via', $route ) ? $route['via'] : null;
		$stops            = isset( $route['stops'] ) && is_int( $route['stops'] ) ? $route['stops'] : -1;
		if ( ! isset( $airport_registry[ $origin_code ], $airport_registry[ $destination_code ] )
			|| ( null !== $via_code && ( ! is_string( $via_code ) || ! isset( $airport_registry[ $via_code ] ) ) )
			|| $stops !== ( null === $via_code ? 0 : 1 )
		) {
			return $this->invalid_map_contract();
		}

		$codes = array( $origin_code );
		if ( null !== $via_code ) {
			$codes[] = $via_code;
		}
		$codes[] = $destination_code;
		$truth = $this->map_truth_context( 'routes', $destination_id, $destination, $field_provenance, $cache_freshness );
		$segments = array();
		for ( $index = 0; $index < count( $codes ) - 1; ++$index ) {
			$sequence   = $index + 1;
			$from       = $airport_registry[ $codes[ $index ] ];
			$to         = $airport_registry[ $codes[ $index + 1 ] ];
			$segments[] = array(
				'id'             => $route_id . ':' . $sequence,
				'route_id'       => $route_id,
				'destination_id' => sanitize_key( $destination_id ),
				'sequence'       => $sequence,
				'from'           => $this->prepare_map_endpoint( $from ),
				'to'             => $this->prepare_map_endpoint( $to ),
				'truth'          => array(
					'data_mode'   => $truth['data_mode'],
					'truth_state' => $truth['truth_state'],
					'freshness'   => $truth['freshness'],
					'bookable'    => false,
				),
				'provenance'     => $truth['provenance'],
			);
		}

		return $segments;
	}

	/**
	 * Return field-specific map truth without promoting fallback values to live.
	 */
	private function map_truth_context( $field, $destination_id, $destination, $field_provenance, $cache_freshness ) {
		$is_live          = $this->field_live_for_destination( $field_provenance, $field, $destination_id );
		$source_freshness = $this->field_source_freshness( $field_provenance, $field, $destination_id );
		$data_mode        = $is_live ? 'live' : 'demo';
		$freshness        = $this->effective_freshness( $cache_freshness, $source_freshness, $data_mode );
		$truth_state      = $is_live ? ( 'current' === $freshness ? 'supplier_snapshot' : 'last_observed' ) : 'planning';
		$provenance       = $is_live ? $this->field_provenance_for_destination( $field_provenance, $field, $destination_id ) : array();
		$planning         = isset( $destination['planning'] ) && is_array( $destination['planning'] ) ? $destination['planning'] : array();

		return array(
			'data_mode'   => $data_mode,
			'truth_state' => $truth_state,
			'freshness'   => $freshness,
			'provenance'  => array(
				'source'       => $is_live && ! empty( $provenance['source'] )
					? sanitize_text_field( $provenance['source'] )
					: sanitize_text_field( isset( $planning['source_label'] ) ? $planning['source_label'] : 'Tra-Vel editorial planning profile' ),
				'observed_at'  => $is_live && ! empty( $provenance['observed_at'] ) ? sanitize_text_field( $provenance['observed_at'] ) : null,
				'retrieved_at' => $is_live && ! empty( $provenance['retrieved_at'] ) ? sanitize_text_field( $provenance['retrieved_at'] ) : null,
				'reviewed_on'  => ! $is_live && ! empty( $planning['reviewed_on'] ) ? sanitize_text_field( $planning['reviewed_on'] ) : null,
			),
		);
	}

	/**
	 * Map-layer prices are planning/snapshot context, never booking confirmation.
	 */
	private function prepare_map_price( $amount, $currency, $basis, $truth_state ) {
		if ( ! ( is_int( $amount ) || is_float( $amount ) ) || ! is_finite( (float) $amount ) || (float) $amount < 0 ) {
			return null;
		}
		$currency = is_string( $currency ) ? strtoupper( $currency ) : '';
		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) || ! in_array( $basis, array( 'per_person_total', 'per_night' ), true ) ) {
			return null;
		}
		$state = in_array( $truth_state, array( 'planning', 'supplier_snapshot', 'last_observed' ), true ) ? $truth_state : 'planning';
		return array(
			'amount'    => (float) $amount,
			'currency'  => $currency,
			'formatted' => $this->format_currency( $amount, $currency ),
			'basis'     => $basis,
			'state'     => $state,
			'bookable'  => false,
		);
	}

	/**
	 * Return one typed map action per layer.
	 */
	private function prepare_map_action( $type, $destination_id, $airport_code, $hotel_area = '' ) {
		$actions = array(
			'search_packages' => array( 'label' => 'בנו חבילה', 'path' => '/packages/', 'destination' => $airport_code, 'requires_live_search' => true ),
			'search_hotels'   => array( 'label' => 'השוו מקומות לינה', 'path' => '/hotels/', 'destination' => $airport_code, 'requires_live_search' => true ),
			'compare_routes'  => array( 'label' => 'השוו מסלולי טיסה', 'path' => '/flights/', 'destination' => $airport_code, 'requires_live_search' => true ),
			'plan_for_weather' => array( 'label' => 'תכננו לפי מזג האוויר', 'path' => '/ai-planner/', 'destination' => $destination_id, 'requires_live_search' => false ),
		);
		if ( ! isset( $actions[ $type ] ) ) {
			$type = 'search_packages';
		}
		$action = $actions[ $type ];
		$query  = array(
			'destination' => $action['destination'] ? $action['destination'] : $destination_id,
		);
		if ( 'search_hotels' === $type && $hotel_area ) {
			$query['area'] = $hotel_area;
		}
		if ( 'plan_for_weather' === $type ) {
			$query = array( 'mode' => 'destination' ) + $query;
		}
		return array(
			'type'                 => $type,
			'label'                => $action['label'],
			'href'                 => home_url( $action['path'] ) . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ),
			'requires_live_search' => $action['requires_live_search'],
		);
	}

	/**
	 * Normalize one registry entry for route-segment consumers.
	 */
	private function prepare_map_endpoint( $airport ) {
		return array(
			'code'  => $airport['code'],
			'label' => $airport['label'],
			'lat'   => (float) $airport['latitude'],
			'lng'   => (float) $airport['longitude'],
		);
	}

	/**
	 * Shape destination output and add display-safe derived values.
	 */
	public function prepare_destination( $item ) {
		$total_scope = isset( $item['deal']['total_scope'] ) ? sanitize_key( $item['deal']['total_scope'] ) : 'destination_deal';
		$total_scope = in_array( $total_scope, array( 'destination_deal', 'package_inclusive' ), true ) ? $total_scope : 'destination_deal';
		$deal_currency  = isset( $item['deal']['currency'] ) ? $item['deal']['currency'] : 'USD';
		$hotel_currency = isset( $item['hotel']['currency'] ) ? $item['hotel']['currency'] : $deal_currency;
		$item['image']                            = TRA_VEL_V2_URI . '/assets/images/' . rawurlencode( basename( $item['image'] ) );
		$item['url']                              = $this->destination_guide_url( $item['id'] );
		$item['deal']['total_scope']              = $total_scope;
		$item['deal']['headline_formatted']       = $this->format_currency( $item['deal']['headline_price'], $deal_currency );
		$item['deal']['total_formatted']          = 'package_inclusive' === $total_scope ? $this->format_currency( $item['deal']['total_per_person'], $deal_currency ) : 'דורש חיפוש חבילה חי';
		$item['hotel']['nightly_formatted']       = $this->format_currency( $item['hotel']['nightly'], $hotel_currency );
		$item['hotel']['per_person_formatted']    = $this->format_currency( $item['hotel']['per_person_total'], $hotel_currency );
		$item['airport']['flight_duration_label'] = $this->format_duration( $item['airport']['flight_minutes'] );
		return $item;
	}

	/**
	 * Assemble one truthful, editable 360-degree decision model for the selected destination.
	 *
	 * Editorial modules describe planning coverage only. A module is marked live only when
	 * server-owned field provenance proves that a connected adapter supplied its values.
	 */
	private function prepare_selected_plan( $selected_id, $destinations, $routes, $data, $field_provenance, $freshness = 'current', $cache_freshness = 'current', $source_freshness = 'not_applicable' ) {
		if ( ! $selected_id ) {
			return null;
		}
		$destination = null;
		foreach ( (array) $destinations as $candidate ) {
			if ( isset( $candidate['id'] ) && $selected_id === $candidate['id'] ) {
				$destination = $candidate;
				break;
			}
		}
		if ( ! is_array( $destination ) ) {
			return null;
		}

		$planning       = isset( $destination['planning'] ) && is_array( $destination['planning'] ) ? $destination['planning'] : array();
		$planning_items = isset( $planning['modules'] ) && is_array( $planning['modules'] ) ? $planning['modules'] : array();
		$editorial_source = isset( $planning['source_label'] ) ? sanitize_text_field( $planning['source_label'] ) : 'Tra-Vel editorial planning profile';
		$reviewed_on      = isset( $planning['reviewed_on'] ) ? sanitize_text_field( $planning['reviewed_on'] ) : null;
		$route_provenance  = $this->field_provenance_for_destination( $field_provenance, 'routes', $selected_id );
		$hotel_provenance  = $this->field_provenance_for_destination( $field_provenance, 'hotels', $selected_id );
		$weather_provenance = $this->field_provenance_for_destination( $field_provenance, 'weather_current', $selected_id );
		$route_live       = ! empty( $route_provenance ) && ! empty( $routes );
		$hotel_live       = ! empty( $hotel_provenance );
		$weather_live     = ! empty( $weather_provenance );
		$route_source_freshness = $this->field_source_freshness( $field_provenance, 'routes', $selected_id );
		$hotel_source_freshness = $this->field_source_freshness( $field_provenance, 'hotels', $selected_id );
		$weather_source_freshness = $this->field_source_freshness( $field_provenance, 'weather_current', $selected_id );
		$route_effective_freshness = $this->effective_freshness( $cache_freshness, $route_source_freshness, $route_live ? 'mixed' : 'demo' );
		$hotel_effective_freshness = $this->effective_freshness( $cache_freshness, $hotel_source_freshness, $hotel_live ? 'mixed' : 'demo' );
		$weather_effective_freshness = $this->effective_freshness( $cache_freshness, $weather_source_freshness, $weather_live ? 'mixed' : 'demo' );
		$route_source     = ! empty( $route_provenance['source'] ) ? sanitize_key( $route_provenance['source'] ) : $editorial_source;
		$hotel_source     = ! empty( $hotel_provenance['source'] ) ? sanitize_key( $hotel_provenance['source'] ) : $editorial_source;
		$weather_source   = ! empty( $weather_provenance['source'] ) ? sanitize_key( $weather_provenance['source'] ) : $editorial_source;
		$route_observed   = $route_live && ! empty( $route_provenance['observed_at'] ) ? sanitize_text_field( $route_provenance['observed_at'] ) : ( $route_live ? null : $reviewed_on );
		$hotel_observed   = $hotel_live && ! empty( $hotel_provenance['observed_at'] ) ? sanitize_text_field( $hotel_provenance['observed_at'] ) : ( $hotel_live ? null : $reviewed_on );
		$weather_observed = $weather_live && ! empty( $weather_provenance['observed_at'] ) ? sanitize_text_field( $weather_provenance['observed_at'] ) : ( $weather_live ? null : $reviewed_on );
		$route_retrieved  = $route_live && ! empty( $route_provenance['retrieved_at'] ) ? sanitize_text_field( $route_provenance['retrieved_at'] ) : null;
		$hotel_retrieved  = $hotel_live && ! empty( $hotel_provenance['retrieved_at'] ) ? sanitize_text_field( $hotel_provenance['retrieved_at'] ) : null;
		$weather_retrieved = $weather_live && ! empty( $weather_provenance['retrieved_at'] ) ? sanitize_text_field( $weather_provenance['retrieved_at'] ) : null;
		$city             = sanitize_text_field( $destination['city'] );
		$route_count      = count( $routes );
		$supplier_current = 'current' === $freshness;
		$route_current     = $route_live && 'current' === $route_effective_freshness;
		$hotel_current     = $hotel_live && 'current' === $hotel_effective_freshness;
		$weather_current   = $weather_live && 'current' === $weather_effective_freshness;
		if ( 'refreshing' === $cache_freshness ) {
			$snapshot_detail = 'מוצגת תצפית הספק האחרונה בזמן שרענון חדש מתבצע. המחיר, הזמינות והתנאים דורשים אימות לפני אישור.';
		} elseif ( 'stale' === $cache_freshness || 'fallback' === $cache_freshness ) {
			$snapshot_detail = 'הרענון האחרון נכשל. מוצגת תצפית הספק האחרונה ולא הצעה נוכחית. המחיר, הזמינות והתנאים דורשים אימות מחדש.';
		} else {
			$snapshot_detail = 'התצפית שסיפק המקור ישנה, עתידית או חסרת זמן אמין. נדרש רענון ואימות לפני אישור.';
		}

		$modules = array(
			$this->selected_plan_module(
				'route',
				$route_live ? ( $route_current ? 'live' : 'stale' ) : ( $route_count ? 'editorial' : 'unavailable' ),
				$route_count ? sprintf( '%d דרכים ל%s מוכנות להשוואה', $route_count, $city ) : sprintf( 'נדרש חיפוש דרך ל%s', $city ),
				$route_live ? ( $route_current ? 'מבנה הדרך, הזמן והעצירות התקבלו מהספק. מוצגות כעלויות עדכניות רק עלויות שהספק סיפק במפורש.' : $snapshot_detail ) : 'מבנה המסלולים זמין לתכנון. זמן, מחיר וכבודה ייבדקו מול ספק לפי התאריכים.',
				'השוו טיסות ודרכים',
				$route_source,
				$route_observed,
				$route_retrieved
			),
			$this->selected_plan_module(
				'stay',
				$hotel_live ? ( $hotel_current ? 'live' : 'stale' ) : 'editorial',
				! empty( $destination['hotel']['area'] ) ? sprintf( 'מתחילים באזור %s', sanitize_text_field( $destination['hotel']['area'] ) ) : 'בוחרים אזור לפני מלון',
				$hotel_live ? ( $hotel_current ? 'מחיר החדר וזהות המלון התקבלו מהספק במועד המצוין. מסים, מלאי, ביטול ותנאים עדיין דורשים בדיקה מתוארכת.' : $snapshot_detail ) : 'האזור הוא בסיס תכנוני. מחיר, מלאי וביטול ייבדקו לפי תאריכים והרכב.',
				'השוו אזורים ומלונות',
				$hotel_source,
				$hotel_observed,
				$hotel_retrieved
			),
			$this->planning_profile_module( 'mobility', $planning_items, $editorial_source, $reviewed_on ),
			$this->selected_plan_module( 'activities', 'editorial', sprintf( 'בונים קצב ופעילויות ל%s', $city ), 'המסלול מחבר עוגנים, זמן חופשי, מרחקים וכרטיסים. זמינות ומחיר דורשים תאריך.', 'תכננו פעילויות', $editorial_source, $reviewed_on ),
			$this->planning_profile_module( 'dining', $planning_items, $editorial_source, $reviewed_on ),
			$this->selected_plan_module(
				'weather',
				$weather_live ? ( $weather_current ? 'live' : 'stale' ) : 'editorial',
				$weather_live && isset( $destination['weather']['temperature_c'] ) ? sprintf( '%s°C עכשיו; התחזית תותאם לתאריך', $destination['weather']['temperature_c'] ) : 'מזג האוויר ייבדק לפי מועד הנסיעה',
				$weather_live ? ( $weather_current ? 'התנאים הנוכחיים התקבלו. עונה, ציוד ומסלול עדיין יותאמו לתאריכי הנסיעה.' : $snapshot_detail ) : 'פרופיל עונתי עוזר לתכנון, אך תחזית אינה מוצגת בלי מועד מתאים.',
				'בדקו עונה ותחזית',
				$weather_source,
				$weather_observed,
				$weather_retrieved
			),
			$this->planning_profile_module( 'entry', $planning_items, $editorial_source, $reviewed_on ),
			$this->planning_profile_module( 'connectivity', $planning_items, $editorial_source, $reviewed_on ),
			$this->planning_profile_module( 'accessibility', $planning_items, $editorial_source, $reviewed_on ),
			$this->selected_plan_module( 'insurance', 'needs_details', 'כיסוי לפי הנוסעים והמסלול', 'גיל, מצב רפואי, פעילויות, כבודה וביטול דורשים פרטים לפני התאמה או הצעה.', 'התאימו ביטוח', $editorial_source, $reviewed_on ),
			$this->planning_profile_module( 'equipment', $planning_items, $editorial_source, $reviewed_on ),
			$this->selected_plan_module( 'total', 'needs_search', 'עלות מלאה מכל הרכיבים', 'טיסה, כבודה, לינה, מסים, תחבורה, פעילויות, אוכל, ביטוח, תקשורת וציוד נכנסים לאותו ספר עלויות.', 'הריצו חיפוש מלא', $editorial_source, $reviewed_on ),
		);

		$cost_categories = array( 'flight', 'baggage', 'stay', 'taxes', 'transfers', 'local_transport', 'activities', 'dining', 'insurance', 'connectivity', 'equipment', 'entry' );
		$cost_ledger = $this->prepare_selected_plan_cost_ledger( $routes, $route_provenance, $cost_categories, isset( $data['currency'] ) ? $data['currency'] : 'USD', $route_effective_freshness );
		$mapped_count = count(
			array_filter(
				$modules,
				static function ( $module ) {
					return ! in_array( $module['state'], array( 'unknown', 'unavailable' ), true );
				}
			)
		);

		return array(
			'contract_version' => '1.3.0',
			'destination_id'   => sanitize_key( $selected_id ),
			'state'            => array_filter( $modules, static function ( $module ) { return 'stale' === $module['state']; } ) ? 'stale' : ( array_filter( $modules, static function ( $module ) { return 'live' === $module['state']; } ) ? 'mixed' : 'editorial' ),
			'freshness'        => $freshness,
			'cache_freshness'  => $cache_freshness,
			'source_freshness' => $source_freshness,
			'selection'        => array(
				'granularity' => 'city',
				'latitude'    => (float) $destination['geo']['latitude'],
				'longitude'   => (float) $destination['geo']['longitude'],
			),
			'coverage'         => array(
				'mapped_count' => $mapped_count,
				'module_count' => count( $modules ),
				'label'        => 'decision_areas_mapped',
			),
			'modules'          => $modules,
			'cost_ledger'      => $cost_ledger,
			'truth'            => $supplier_current ? 'Planning coverage is not booking progress. Only fields with live provenance are current supplier data.' : 'Planning coverage is not booking progress. Supplier snapshots marked stale require a new verification before approval.',
		);
	}

	/**
	 * Return sanitized destination coverage for one live field.
	 */
	private function field_live_destination_ids( $field_provenance, $field ) {
		if ( empty( $field_provenance[ $field ]['live'] ) || empty( $field_provenance[ $field ]['destination_ids'] ) || ! is_array( $field_provenance[ $field ]['destination_ids'] ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $field_provenance[ $field ]['destination_ids'] ) ) ) );
	}

	/**
	 * A global adapter flag never certifies fallback values for another destination.
	 */
	private function field_live_for_destination( $field_provenance, $field, $destination_id ) {
		return in_array( sanitize_key( $destination_id ), $this->field_live_destination_ids( $field_provenance, $field ), true );
	}

	/**
	 * Current live coverage excludes observations that are old, future-dated, or unclocked.
	 */
	private function field_current_destination_ids( $field_provenance, $field ) {
		return array_values(
			array_filter(
				$this->field_live_destination_ids( $field_provenance, $field ),
				function ( $destination_id ) use ( $field_provenance, $field ) {
					return 'current' === $this->field_source_freshness( $field_provenance, $field, $destination_id );
				}
			)
		);
	}

	/**
	 * Supplier-field freshness is independent from the server response cache age.
	 */
	private function field_source_freshness( $field_provenance, $field, $destination_id ) {
		$destination_id = sanitize_key( $destination_id );
		if ( ! $this->field_live_for_destination( $field_provenance, $field, $destination_id ) ) {
			return 'not_applicable';
		}
		$entry = isset( $field_provenance[ $field ]['by_destination'][ $destination_id ] ) && is_array( $field_provenance[ $field ]['by_destination'][ $destination_id ] )
			? $field_provenance[ $field ]['by_destination'][ $destination_id ]
			: array();
		$timestamp = ! empty( $entry['observed_at'] ) ? $entry['observed_at'] : ( ! empty( $entry['retrieved_at'] ) ? $entry['retrieved_at'] : null );
		if ( ! is_string( $timestamp ) || false === strtotime( $timestamp ) ) {
			return 'unknown';
		}
		$observed = strtotime( $timestamp );
		$now      = time();
		$max_ages = array(
			'deals'           => 1800,
			'hotels'          => 1800,
			'airports'        => 1800,
			'routes'          => 1800,
			'weather_current' => 7200,
			'weather_season'  => 604800,
		);
		$max_age = isset( $max_ages[ $field ] ) ? $max_ages[ $field ] : 1800;
		if ( $observed > $now + 300 ) {
			return 'future';
		}
		return $now - $observed > $max_age ? 'stale' : 'current';
	}

	/**
	 * Summarize only the selected destination's supplier observations.
	 */
	private function destination_source_freshness( $field_provenance, $destination_id ) {
		if ( ! $destination_id ) {
			return 'not_applicable';
		}
		$states = array();
		foreach ( array( 'deals', 'hotels', 'airports', 'routes', 'weather_current', 'weather_season' ) as $field ) {
			$state = $this->field_source_freshness( $field_provenance, $field, $destination_id );
			if ( 'not_applicable' !== $state ) {
				$states[] = $state;
			}
		}
		if ( ! $states ) {
			return 'not_applicable';
		}
		foreach ( array( 'future', 'stale', 'unknown' ) as $blocking_state ) {
			if ( in_array( $blocking_state, $states, true ) ) {
				return $blocking_state;
			}
		}
		return 'current';
	}

	/**
	 * Keep data origin and freshness separate while exposing one safe UI freshness gate.
	 */
	private function effective_freshness( $cache_freshness, $source_freshness, $data_mode ) {
		if ( 'fallback' === $cache_freshness ) {
			return 'fallback';
		}
		if ( 'stale' === $cache_freshness ) {
			return 'stale';
		}
		if ( 'refreshing' === $cache_freshness ) {
			return 'refreshing';
		}
		if ( 'demo' !== $data_mode && in_array( $source_freshness, array( 'future', 'stale', 'unknown' ), true ) ) {
			return 'stale';
		}
		return 'current';
	}

	/**
	 * Describe live coverage for the selected destination, never for another city.
	 */
	private function destination_data_mode( $field_provenance, $destination_id ) {
		if ( ! $destination_id ) {
			return 'demo';
		}
		$fields = array( 'deals', 'hotels', 'airports', 'routes', 'weather_current', 'weather_season' );
		$live_count = 0;
		foreach ( $fields as $field ) {
			if ( $this->field_live_for_destination( $field_provenance, $field, $destination_id ) ) {
				++$live_count;
			}
		}
		if ( 0 === $live_count ) {
			return 'demo';
		}
		return count( $fields ) === $live_count ? 'live' : 'mixed';
	}

	/**
	 * Separate supplier origin from cache freshness so an old live snapshot is never
	 * presented as a newly refreshed quote.
	 */
	private function cache_freshness_state( $cache_state ) {
		if ( in_array( $cache_state, array( 'fresh', 'miss' ), true ) ) {
			return 'current';
		}
		if ( 'stale_refreshing' === $cache_state ) {
			return 'refreshing';
		}
		if ( 'stale_error' === $cache_state ) {
			return 'stale';
		}
		return 'fallback';
	}

	/**
	 * Read the source and observation time that belong to this exact destination.
	 */
	private function field_provenance_for_destination( $field_provenance, $field, $destination_id ) {
		$destination_id = sanitize_key( $destination_id );
		if ( ! $this->field_live_for_destination( $field_provenance, $field, $destination_id ) || empty( $field_provenance[ $field ]['by_destination'][ $destination_id ] ) || ! is_array( $field_provenance[ $field ]['by_destination'][ $destination_id ] ) ) {
			return array();
		}
		$entry = $field_provenance[ $field ]['by_destination'][ $destination_id ];
		$components = ! empty( $entry['cost_components'] ) && is_array( $entry['cost_components'] )
			? array_values( array_unique( array_filter( array_map( 'sanitize_key', $entry['cost_components'] ) ) ) )
			: array();
		$currency = ! empty( $entry['currency'] ) && preg_match( '/^[A-Z]{3}$/', (string) $entry['currency'] )
			? strtoupper( sanitize_text_field( $entry['currency'] ) )
			: null;
		return array(
			'source'      => ! empty( $entry['source'] ) ? sanitize_key( $entry['source'] ) : null,
			'observed_at' => ! empty( $entry['observed_at'] ) ? sanitize_text_field( $entry['observed_at'] ) : null,
			'retrieved_at' => ! empty( $entry['retrieved_at'] ) ? sanitize_text_field( $entry['retrieved_at'] ) : null,
			'source_freshness' => $this->field_source_freshness( $field_provenance, $field, $destination_id ),
			'currency'     => $currency,
			'cost_components' => $components,
			'total_live'   => ! empty( $entry['total_live'] ),
			'price_scope'  => ! empty( $entry['price_scope'] ) ? sanitize_key( $entry['price_scope'] ) : null,
		);
	}

	/**
	 * Normalize one selected-plan module.
	 */
	private function selected_plan_module( $id, $state, $headline, $detail, $next_action, $source, $observed_at, $retrieved_at = null ) {
		$allowed_states = array( 'live', 'stale', 'editorial', 'needs_details', 'needs_search', 'unknown', 'unavailable' );
		return array(
			'id'          => sanitize_key( $id ),
			'state'       => in_array( $state, $allowed_states, true ) ? $state : 'unknown',
			'headline'    => sanitize_text_field( $headline ),
			'detail'      => sanitize_text_field( $detail ),
			'next_action' => sanitize_text_field( $next_action ),
			'provenance'  => array(
				'source'      => sanitize_text_field( $source ),
				'observed_at' => $observed_at ? sanitize_text_field( $observed_at ) : null,
				'retrieved_at' => $retrieved_at ? sanitize_text_field( $retrieved_at ) : null,
			),
		);
	}

	/**
	 * Read one trusted editorial module from the destination planning profile.
	 */
	private function planning_profile_module( $id, $planning_items, $source, $reviewed_on ) {
		$item = isset( $planning_items[ $id ] ) && is_array( $planning_items[ $id ] ) ? $planning_items[ $id ] : array();
		return $this->selected_plan_module(
			$id,
			isset( $item['state'] ) ? $item['state'] : 'unknown',
			isset( $item['headline'] ) ? $item['headline'] : 'נדרש מידע נוסף',
			isset( $item['detail'] ) ? $item['detail'] : 'הסוכן יאסוף את הפרטים לפני הצעה.',
			isset( $item['next_action'] ) ? $item['next_action'] : 'הוסיפו פרטים',
			$source,
			$reviewed_on
		);
	}

	/**
	 * Build the complete cost scope and overlay only supplier-owned route components.
	 * A route schedule can be live while its package total remains unavailable.
	 */
	private function prepare_selected_plan_cost_ledger( $routes, $route_provenance, $categories, $currency, $freshness = 'current' ) {
		$selected_route = ! empty( $routes[0] ) && is_array( $routes[0] ) ? $routes[0] : array();
		$selected_costs = ! empty( $selected_route['costs'] ) && is_array( $selected_route['costs'] ) ? $selected_route['costs'] : array();
		$verified       = ! empty( $route_provenance['cost_components'] ) && is_array( $route_provenance['cost_components'] )
			? array_values( array_unique( array_filter( array_map( 'sanitize_key', $route_provenance['cost_components'] ) ) ) )
			: array();
		$live_currency  = ! empty( $route_provenance['currency'] ) && preg_match( '/^[A-Z]{3}$/', (string) $route_provenance['currency'] )
			? strtoupper( sanitize_text_field( $route_provenance['currency'] ) )
			: null;
		$currency       = $live_currency ? $live_currency : strtoupper( sanitize_text_field( $currency ) );
		$usable_live_route = ! empty( $selected_route ) && ! empty( $selected_costs ) && $live_currency && ! empty( $verified );
		$category_cost_keys = array( 'stay' => 'hotel' );
		$line_items     = array();
		$live_count     = 0;
		$live_amount_total = 0.0;
		$supplier_current = 'current' === $freshness;

		foreach ( array_values( array_unique( array_map( 'sanitize_key', (array) $categories ) ) ) as $category ) {
			$cost_key = isset( $category_cost_keys[ $category ] ) ? $category_cost_keys[ $category ] : $category;
			$is_live  = $usable_live_route && in_array( $cost_key, $verified, true ) && isset( $selected_costs[ $cost_key ] ) && is_numeric( $selected_costs[ $cost_key ] );
			if ( $is_live ) {
				++$live_count;
				$live_amount_total += (float) $selected_costs[ $cost_key ];
			}
			$line_items[] = array(
				'id'          => $category,
				'state'       => $is_live ? ( $supplier_current ? 'live' : 'stale' ) : 'needs_search',
				'amount'      => $is_live ? (float) $selected_costs[ $cost_key ] : null,
				'formatted'   => $is_live ? $this->format_currency( $selected_costs[ $cost_key ], $currency ) : null,
				'source'      => $is_live && ! empty( $route_provenance['source'] ) ? sanitize_key( $route_provenance['source'] ) : null,
				'observed_at' => $is_live && ! empty( $route_provenance['observed_at'] ) ? sanitize_text_field( $route_provenance['observed_at'] ) : null,
				'retrieved_at' => $is_live && ! empty( $route_provenance['retrieved_at'] ) ? sanitize_text_field( $route_provenance['retrieved_at'] ) : null,
			);
		}

		$total_live = $usable_live_route
			&& ! empty( $route_provenance['total_live'] )
			&& 12 === count( $line_items )
			&& 12 === $live_count
			&& isset( $selected_costs['total'] )
			&& is_numeric( $selected_costs['total'] )
			&& abs( $live_amount_total - (float) $selected_costs['total'] ) <= 0.01;
		$total = $total_live
			? array(
				'amount'    => (float) $selected_costs['total'],
				'formatted' => $this->format_currency( $selected_costs['total'], $currency ),
			)
			: null;

		$ledger_state = 'needs_search';
		if ( $live_count ) {
			$ledger_state = $total_live
				? ( $supplier_current ? 'complete_live' : 'stale_complete' )
				: ( $supplier_current ? 'partial_live' : 'stale_partial' );
		}

		return array(
			'state'               => $ledger_state,
			'freshness'           => $freshness,
			'currency'            => preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : 'USD',
			'route_id'            => ! empty( $selected_route['id'] ) ? sanitize_key( $selected_route['id'] ) : null,
			'scope'               => ! empty( $route_provenance['price_scope'] ) ? sanitize_key( $route_provenance['price_scope'] ) : 'unpriced_trip_scope',
			'source'              => $live_count && ! empty( $route_provenance['source'] ) ? sanitize_key( $route_provenance['source'] ) : null,
			'observed_at'         => $live_count && ! empty( $route_provenance['observed_at'] ) ? sanitize_text_field( $route_provenance['observed_at'] ) : null,
			'retrieved_at'        => $live_count && ! empty( $route_provenance['retrieved_at'] ) ? sanitize_text_field( $route_provenance['retrieved_at'] ) : null,
			'line_items'          => $line_items,
			'total'               => $total,
			'savings'             => null,
			'comparable_verified' => false,
			'booking_confirmed'   => false,
		);
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
		$currency                          = isset( $route['currency'] ) ? $route['currency'] : 'USD';
		$route['duration_label']            = $this->format_duration( $route['duration_minutes'] );
		$raw_costs                          = $route['costs'];
		$route['costs']['total_formatted']  = $this->format_currency( $route['costs']['total'], $currency );
		foreach ( $raw_costs as $key => $value ) {
			if ( is_numeric( $value ) ) {
				$route['costs'][ $key . '_formatted' ] = $this->format_currency( $value, $currency );
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
			'required'   => array( 'meta', 'adapter_status', 'origin', 'provider_status', 'field_provenance', 'layers', 'exploration_hubs', 'destinations', 'routes', 'recommended', 'map_entities', 'map_segments', 'selected_plan' ),
			'properties' => array(
				'meta'            => array( 'type' => 'object', 'readonly' => true ),
				'adapter_status'  => array( 'type' => 'object', 'readonly' => true ),
				'origin'          => array( 'type' => 'object', 'readonly' => true ),
				'provider_status' => array( 'type' => 'object', 'readonly' => true ),
				'field_provenance' => array( 'type' => 'object', 'readonly' => true ),
				'layers'          => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'exploration_hubs' => array( 'type' => 'array', 'minItems' => 30, 'maxItems' => 80, 'items' => $this->exploration_hub_schema(), 'readonly' => true ),
				'destinations'    => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'routes'          => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'recommended'     => array( 'type' => array( 'object', 'null' ), 'readonly' => true ),
				'map_entities'    => array( 'type' => 'array', 'items' => $this->map_entity_schema(), 'readonly' => true ),
				'map_segments'    => array( 'type' => 'array', 'items' => $this->map_segment_schema(), 'readonly' => true ),
				'selected_plan'   => $this->selected_plan_schema(),
			),
		);
		return $this->schema;
	}

	/**
	 * Publish the closed geographic-hub response item.
	 */
	private function exploration_hub_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id', 'city', 'country', 'geo', 'radius_km', 'live_search_scopes' ),
			'properties'           => array(
				'id'                 => array( 'type' => 'string', 'pattern' => '^[a-z0-9-]{2,60}$' ),
				'city'               => array( 'type' => 'string', 'minLength' => 2, 'maxLength' => 100 ),
				'country'            => array( 'type' => 'string', 'minLength' => 2, 'maxLength' => 100 ),
				'geo'                => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'latitude', 'longitude' ),
					'properties'           => array(
						'latitude'  => array( 'type' => 'number', 'minimum' => -90, 'maximum' => 90 ),
						'longitude' => array( 'type' => 'number', 'minimum' => -180, 'maximum' => 180 ),
					),
				),
				'radius_km'          => array( 'type' => 'integer', 'minimum' => 40, 'maximum' => 750 ),
				'iata_search_code'   => array( 'type' => 'string', 'pattern' => '^[A-Z]{3}$' ),
				'live_search_scopes' => array(
					'type'        => 'array',
					'minItems'    => 6,
					'maxItems'    => 6,
					'uniqueItems' => true,
					'items'       => array( 'type' => 'string', 'enum' => array( 'route', 'stay', 'activities', 'insurance', 'connectivity', 'equipment' ) ),
				),
			),
		);
	}

	/**
	 * Publish the selected-plan contract instead of an untyped object bag.
	 */
	private function selected_plan_schema() {
		$nullable_string = array( 'type' => array( 'string', 'null' ) );
		$nullable_number = array( 'type' => array( 'number', 'null' ) );
		$money = array(
			'type'                 => array( 'object', 'null' ),
			'additionalProperties' => false,
			'properties'           => array(
				'amount'    => array( 'type' => 'number' ),
				'formatted' => array( 'type' => 'string' ),
			),
		);
		$module = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id', 'state', 'headline', 'detail', 'next_action', 'provenance' ),
			'properties'           => array(
				'id'          => array( 'type' => 'string' ),
				'state'       => array( 'type' => 'string', 'enum' => array( 'live', 'stale', 'editorial', 'needs_details', 'needs_search', 'unknown', 'unavailable' ) ),
				'headline'    => array( 'type' => 'string' ),
				'detail'      => array( 'type' => 'string' ),
				'next_action' => array( 'type' => 'string' ),
				'provenance'  => array(
					'type'       => 'object',
					'properties' => array(
						'source'       => array( 'type' => 'string' ),
						'observed_at'  => $nullable_string,
						'retrieved_at' => $nullable_string,
					),
				),
			),
		);
		$line_item = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id', 'state', 'amount', 'formatted' ),
			'properties'           => array(
				'id'           => array( 'type' => 'string' ),
				'state'        => array( 'type' => 'string', 'enum' => array( 'live', 'stale', 'needs_search' ) ),
				'amount'       => $nullable_number,
				'formatted'    => $nullable_string,
				'source'       => $nullable_string,
				'observed_at'  => $nullable_string,
				'retrieved_at' => $nullable_string,
			),
		);
		return array(
			'type'                 => array( 'object', 'null' ),
			'readonly'             => true,
			'additionalProperties' => false,
			'required'             => array( 'contract_version', 'destination_id', 'state', 'freshness', 'cache_freshness', 'source_freshness', 'selection', 'coverage', 'modules', 'cost_ledger', 'truth' ),
			'properties'           => array(
				'contract_version' => array( 'type' => 'string', 'enum' => array( '1.3.0' ) ),
				'destination_id'   => array( 'type' => 'string' ),
				'state'            => array( 'type' => 'string', 'enum' => array( 'editorial', 'mixed', 'stale' ) ),
				'freshness'        => array( 'type' => 'string', 'enum' => array( 'current', 'refreshing', 'stale', 'fallback' ) ),
				'cache_freshness'  => array( 'type' => 'string', 'enum' => array( 'current', 'refreshing', 'stale', 'fallback' ) ),
				'source_freshness' => array( 'type' => 'string', 'enum' => array( 'not_applicable', 'current', 'future', 'stale', 'unknown' ) ),
				'selection'        => array(
					'type'       => 'object',
					'properties' => array(
						'granularity' => array( 'type' => 'string', 'enum' => array( 'city', 'area' ) ),
						'latitude'    => array( 'type' => 'number', 'minimum' => -90, 'maximum' => 90 ),
						'longitude'   => array( 'type' => 'number', 'minimum' => -180, 'maximum' => 180 ),
					),
				),
				'coverage'         => array(
					'type'       => 'object',
					'properties' => array(
						'mapped_count' => array( 'type' => 'integer', 'minimum' => 0 ),
						'module_count' => array( 'type' => 'integer', 'minimum' => 0 ),
						'label'        => array( 'type' => 'string' ),
					),
				),
				'modules'          => array( 'type' => 'array', 'items' => $module ),
				'cost_ledger'      => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'state'               => array( 'type' => 'string', 'enum' => array( 'complete_live', 'stale_complete', 'partial_live', 'stale_partial', 'needs_search' ) ),
						'freshness'           => array( 'type' => 'string', 'enum' => array( 'current', 'refreshing', 'stale', 'fallback' ) ),
						'currency'            => array( 'type' => 'string', 'pattern' => '^[A-Z]{3}$' ),
						'route_id'            => $nullable_string,
						'scope'               => array( 'type' => 'string' ),
						'source'              => $nullable_string,
						'observed_at'         => $nullable_string,
						'retrieved_at'        => $nullable_string,
						'line_items'          => array( 'type' => 'array', 'items' => $line_item ),
						'total'               => $money,
						'savings'             => array( 'type' => array( 'object', 'null' ) ),
						'comparable_verified' => array( 'type' => 'boolean' ),
						'booking_confirmed'   => array( 'type' => 'boolean' ),
					),
				),
				'truth'            => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Publish the strict active-layer entity schema used by the globe client.
	 */
	private function map_entity_schema() {
		$nullable_datetime = array( 'type' => array( 'string', 'null' ), 'format' => 'date-time' );
		$provenance = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'source', 'observed_at', 'retrieved_at', 'reviewed_on' ),
			'properties'           => array(
				'source'       => array( 'type' => 'string', 'minLength' => 2 ),
				'observed_at'  => $nullable_datetime,
				'retrieved_at' => $nullable_datetime,
				'reviewed_on'  => array( 'type' => array( 'string', 'null' ), 'format' => 'date' ),
			),
		);
		$action = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'type', 'label', 'href', 'requires_live_search' ),
			'properties'           => array(
				'type'                 => array( 'type' => 'string', 'enum' => array( 'search_packages', 'search_hotels', 'compare_routes', 'plan_for_weather' ) ),
				'label'                => array( 'type' => 'string', 'minLength' => 2 ),
				'href'                 => array( 'type' => 'string', 'format' => 'uri' ),
				'requires_live_search' => array( 'type' => 'boolean' ),
			),
		);
		$price = array(
			'type'                 => array( 'object', 'null' ),
			'additionalProperties' => false,
			'required'             => array( 'amount', 'currency', 'formatted', 'basis', 'state', 'bookable' ),
			'properties'           => array(
				'amount'    => array( 'type' => 'number', 'minimum' => 0 ),
				'currency'  => array( 'type' => 'string', 'pattern' => '^[A-Z]{3}$' ),
				'formatted' => array( 'type' => 'string', 'minLength' => 1 ),
				'basis'     => array( 'type' => 'string', 'enum' => array( 'per_person_total', 'per_night' ) ),
				'state'     => array( 'type' => 'string', 'enum' => array( 'planning', 'supplier_snapshot', 'last_observed' ) ),
				'bookable'  => array( 'type' => 'boolean', 'enum' => array( false ) ),
			),
		);

		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id', 'kind', 'destination_id', 'lat', 'lng', 'label', 'summary', 'data_mode', 'truth_state', 'freshness', 'action', 'provenance', 'price' ),
			'properties'           => array(
				'id'             => array( 'type' => 'string', 'pattern' => '^(deal|hotel_area|airport|weather):[a-z0-9-]{2,60}$' ),
				'kind'           => array( 'type' => 'string', 'enum' => array( 'deal', 'hotel_area', 'airport', 'weather' ) ),
				'destination_id' => array( 'type' => 'string', 'pattern' => '^[a-z0-9-]{2,60}$' ),
				'lat'            => array( 'type' => 'number', 'minimum' => -90, 'maximum' => 90 ),
				'lng'            => array( 'type' => 'number', 'minimum' => -180, 'maximum' => 180 ),
				'label'          => array( 'type' => 'string', 'minLength' => 2 ),
				'summary'        => array( 'type' => 'string', 'minLength' => 2 ),
				'data_mode'      => array( 'type' => 'string', 'enum' => array( 'demo', 'live' ) ),
				'truth_state'    => array( 'type' => 'string', 'enum' => array( 'planning', 'supplier_snapshot', 'last_observed' ) ),
				'freshness'      => array( 'type' => 'string', 'enum' => array( 'current', 'refreshing', 'stale', 'fallback' ) ),
				'action'         => $action,
				'provenance'     => $provenance,
				'price'          => $price,
			),
		);
	}

	/**
	 * Publish exact route-segment geometry and its non-booking truth boundary.
	 */
	private function map_segment_schema() {
		$nullable_datetime = array( 'type' => array( 'string', 'null' ), 'format' => 'date-time' );
		$endpoint = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'code', 'label', 'lat', 'lng' ),
			'properties'           => array(
				'code'  => array( 'type' => 'string', 'pattern' => '^[A-Z]{3}$' ),
				'label' => array( 'type' => 'string', 'minLength' => 2 ),
				'lat'   => array( 'type' => 'number', 'minimum' => -90, 'maximum' => 90 ),
				'lng'   => array( 'type' => 'number', 'minimum' => -180, 'maximum' => 180 ),
			),
		);
		$truth = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'data_mode', 'truth_state', 'freshness', 'bookable' ),
			'properties'           => array(
				'data_mode'   => array( 'type' => 'string', 'enum' => array( 'demo', 'live' ) ),
				'truth_state' => array( 'type' => 'string', 'enum' => array( 'planning', 'supplier_snapshot', 'last_observed' ) ),
				'freshness'   => array( 'type' => 'string', 'enum' => array( 'current', 'refreshing', 'stale', 'fallback' ) ),
				'bookable'    => array( 'type' => 'boolean', 'enum' => array( false ) ),
			),
		);
		$provenance = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'source', 'observed_at', 'retrieved_at', 'reviewed_on' ),
			'properties'           => array(
				'source'       => array( 'type' => 'string', 'minLength' => 2 ),
				'observed_at'  => $nullable_datetime,
				'retrieved_at' => $nullable_datetime,
				'reviewed_on'  => array( 'type' => array( 'string', 'null' ), 'format' => 'date' ),
			),
		);

		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id', 'route_id', 'destination_id', 'sequence', 'from', 'to', 'truth', 'provenance' ),
			'properties'           => array(
				'id'             => array( 'type' => 'string', 'pattern' => '^[a-z0-9-]{2,80}:[1-9][0-9]*$' ),
				'route_id'       => array( 'type' => 'string', 'pattern' => '^[a-z0-9-]{2,80}$' ),
				'destination_id' => array( 'type' => 'string', 'pattern' => '^[a-z0-9-]{2,60}$' ),
				'sequence'       => array( 'type' => 'integer', 'minimum' => 1 ),
				'from'           => $endpoint,
				'to'             => $endpoint,
				'truth'          => $truth,
				'provenance'     => $provenance,
			),
		);
	}

	/**
	 * Format one amount from its explicit ISO currency.
	 */
	private function format_currency( $amount, $currency ) {
		$currency = strtoupper( sanitize_text_field( $currency ) );
		$symbols  = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'ILS' => '₪',
		);
		$prefix = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : $currency . ' ';
		return $prefix . number_format_i18n( (float) $amount, 0 );
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
