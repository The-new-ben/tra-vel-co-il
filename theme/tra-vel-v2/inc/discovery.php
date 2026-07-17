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
		$data        = $resolved['data'];
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

		$selected_data_mode = $this->destination_data_mode( $field_provenance, $selected_id );
		$source_freshness  = $this->destination_source_freshness( $field_provenance, $selected_id );
		$freshness         = $this->effective_freshness( $cache_freshness, $source_freshness, $selected_data_mode );
		$recommended       = isset( $routes[0] ) ? $routes[0] : null;
		$selected_plan     = $this->prepare_selected_plan( $selected_id, $destinations, $routes, $data, $field_provenance, $freshness, $cache_freshness, $source_freshness );
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
				$route_live ? ( $route_current ? 'מבנה הדרך, הזמן והעצירות התקבלו מספק מחובר. רק רכיבי עלות עם בעלות ספק מפורשת מוצגים כחיים.' : $snapshot_detail ) : 'מבנה המסלולים זמין לתכנון. זמן, מחיר וכבודה יאומתו בחיפוש חי.',
				'השוו טיסות ודרכים',
				$route_source,
				$route_observed,
				$route_retrieved
			),
			$this->selected_plan_module(
				'stay',
				$hotel_live ? ( $hotel_current ? 'live' : 'stale' ) : 'editorial',
				! empty( $destination['hotel']['area'] ) ? sprintf( 'מתחילים באזור %s', sanitize_text_field( $destination['hotel']['area'] ) ) : 'בוחרים אזור לפני מלון',
				$hotel_live ? ( $hotel_current ? 'מחיר החדר וזהות המלון התקבלו מספק מחובר. מסים, מלאי, ביטול ותנאים עדיין דורשים הצעה מתוארכת.' : $snapshot_detail ) : 'האזור הוא בסיס תכנוני. מחיר, מלאי וביטול ייבדקו לפי תאריכים והרכב.',
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
			'contract_version' => '1.1.0',
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
				'selected_plan'   => $this->selected_plan_schema(),
			),
		);
		return $this->schema;
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
				'contract_version' => array( 'type' => 'string', 'enum' => array( '1.1.0' ) ),
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
