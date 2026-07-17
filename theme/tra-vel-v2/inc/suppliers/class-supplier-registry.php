<?php
/**
 * Supplier registry and normalized contract merger.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Supplier_Registry {
	/**
	 * Registered adapters keyed by public ID.
	 *
	 * @var Tra_Vel_V2_Supplier_Adapter[]
	 */
	private $adapters = array();

	public function __construct() {
		$this->register(
			new Tra_Vel_V2_Demo_Supplier_Adapter(
				TRA_VEL_V2_PATH . '/assets/data/discovery-demo.json'
			)
		);
		$this->register( new Tra_Vel_V2_Open_Meteo_Supplier_Adapter() );

		$adapters = apply_filters( 'tra_vel_v2_supplier_adapters', array() );
		if ( is_array( $adapters ) ) {
			foreach ( $adapters as $adapter ) {
				$this->register( $adapter );
			}
		}
	}

	/**
	 * Register one adapter when it satisfies the public contract.
	 */
	public function register( $adapter ) {
		if ( ! $adapter instanceof Tra_Vel_V2_Supplier_Adapter ) {
			return false;
		}

		$id = sanitize_key( $adapter->get_id() );
		if ( ! $id || isset( $this->adapters[ $id ] ) ) {
			return false;
		}

		$this->adapters[ $id ] = $adapter;
		return true;
	}

	/**
	 * Stable signature for cache invalidation when adapters change.
	 */
	public function get_cache_signature() {
		$signature = array();
		foreach ( $this->adapters as $id => $adapter ) {
			$signature[ $id ] = array(
				'version'    => (string) $adapter->get_cache_version(),
				'configured' => (bool) $adapter->is_configured(),
				'mode'       => (string) $adapter->get_mode(),
			);
		}
		return md5( wp_json_encode( $signature ) );
	}

	/**
	 * Resolve every configured adapter, keeping the demo adapter as fallback.
	 */
	public function resolve( $context ) {
		$contract         = null;
		$reports          = array();
		$live_failed      = array();
		$field_provenance = $this->empty_field_provenance();

		foreach ( $this->adapters as $id => $adapter ) {
			$report = array(
				'id'         => $id,
				'mode'       => $adapter->get_mode(),
				'verticals'  => array_values( array_map( 'sanitize_key', (array) $adapter->get_verticals() ) ),
				'configured' => (bool) $adapter->is_configured(),
				'healthy'    => false,
				'error_code' => null,
			);

			if ( ! $report['configured'] ) {
				$reports[ $id ] = $report;
				continue;
			}

			try {
				$result = $adapter->fetch( $context );
			} catch ( Throwable $error ) {
				$result = new WP_Error( 'tra_vel_supplier_exception', 'Supplier adapter failed safely.' );
			}

			if ( is_wp_error( $result ) ) {
				$report['error_code'] = $result->get_error_code();
				$reports[ $id ]       = $report;
				if ( 'live' === $adapter->get_mode() ) {
					$live_failed[] = $id;
				}
				continue;
			}

			if ( ! $this->is_contract_fragment( $result ) ) {
				$report['error_code'] = 'invalid_contract';
				$reports[ $id ]       = $report;
				if ( 'live' === $adapter->get_mode() ) {
					$live_failed[] = $id;
				}
				continue;
			}
			if ( 'live' === $adapter->get_mode() ) {
				$known_destination_ids = is_array( $contract ) && ! empty( $contract['destinations'] )
					? array_values( array_filter( array_map( 'sanitize_key', array_column( $contract['destinations'], 'id' ) ) ) )
					: array();
				$result = $this->sanitize_live_fragment( $result, $report['verticals'], $known_destination_ids );
				if ( ! $this->fragment_has_usable_live_fields( $result ) ) {
					$report['error_code'] = 'no_usable_live_fields';
					$reports[ $id ]       = $report;
					$live_failed[]        = $id;
					continue;
				}
			}

			$report['healthy'] = true;
			$reports[ $id ]    = $report;
			if ( 'live' === $adapter->get_mode() ) {
				$field_provenance = $this->merge_field_provenance(
					$field_provenance,
					$this->detect_field_provenance( $id, $adapter, $result )
				);
			}
			$contract          = null === $contract ? $result : $this->merge_contract( $contract, $result );
		}

		if ( ! is_array( $contract ) ) {
			return new WP_Error( 'tra_vel_suppliers_unavailable', 'No discovery supplier is currently available.', array( 'status' => 503 ) );
		}

		$contract['field_provenance'] = $field_provenance;
		$contract['data_mode']        = $this->derive_data_mode( $field_provenance, isset( $contract['destinations'] ) ? $contract['destinations'] : array() );
		return array(
			'data'            => $contract,
			'reports'         => $reports,
			'degraded'        => ! empty( $live_failed ),
			'failed_adapters' => $live_failed,
		);
	}

	/**
	 * Fail-closed field ownership for values merged with the editorial fallback.
	 */
	private function empty_field_provenance() {
		$empty = array(
			'live'            => false,
			'source'          => null,
			'observed_at'     => null,
			'retrieved_at'    => null,
			'destination_ids' => array(),
			'by_destination'  => array(),
		);
		return array(
			'deals'           => $empty,
			'hotels'          => $empty,
			'airports'        => $empty,
			'routes'          => $empty,
			'weather_current' => $empty,
			'weather_season'  => $empty,
		);
	}

	/**
	 * Mark only fields physically supplied by a successful live adapter.
	 * A connected provider flag alone never certifies inherited fallback data.
	 */
	private function detect_field_provenance( $id, $adapter, $fragment ) {
		$provenance = $this->empty_field_provenance();
		$verticals  = array_values( array_map( 'sanitize_key', (array) $adapter->get_verticals() ) );
		$live_value = array(
			'live'            => true,
			'source'          => sanitize_key( $id ),
			'observed_at'     => $this->fragment_observed_at( $fragment ),
			'retrieved_at'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'destination_ids' => array(),
			'by_destination'  => array(),
		);
		$destinations = isset( $fragment['destinations'] ) && is_array( $fragment['destinations'] ) ? $fragment['destinations'] : array();

		foreach ( $destinations as $destination ) {
			$destination_id = isset( $destination['id'] ) ? sanitize_key( $destination['id'] ) : '';
			if ( array_intersect( array( 'deals', 'packages' ), $verticals ) && $this->valid_deal_fragment( isset( $destination['deal'] ) ? $destination['deal'] : null ) ) {
				$provenance['deals'] = $this->mark_field_live(
					$provenance['deals'],
					$this->live_value_for_destination_field( $live_value, $destination, 'deal' ),
					$destination_id,
					array( 'currency' => strtoupper( sanitize_text_field( $destination['deal']['currency'] ) ) )
				);
			}
			if ( in_array( 'hotels', $verticals, true ) && $this->valid_hotel_fragment( isset( $destination['hotel'] ) ? $destination['hotel'] : null ) ) {
				$provenance['hotels'] = $this->mark_field_live(
					$provenance['hotels'],
					$this->live_value_for_destination_field( $live_value, $destination, 'hotel' ),
					$destination_id,
					array( 'currency' => strtoupper( sanitize_text_field( $destination['hotel']['currency'] ) ) )
				);
			}
			if ( in_array( 'flights', $verticals, true ) && $this->valid_airport_fragment( isset( $destination['airport'] ) ? $destination['airport'] : null ) ) {
				$provenance['airports'] = $this->mark_field_live( $provenance['airports'], $this->live_value_for_destination_field( $live_value, $destination, 'airport' ), $destination_id );
			}
			if ( in_array( 'weather', $verticals, true ) && isset( $destination['weather'] ) && is_array( $destination['weather'] ) ) {
				$weather = $destination['weather'];
				if ( $this->valid_weather_current_fragment( $weather ) ) {
					$provenance['weather_current'] = $this->mark_field_live( $provenance['weather_current'], $this->live_value_for_destination_field( $live_value, $destination, 'weather' ), $destination_id );
				}
				if ( $this->valid_weather_season_fragment( $weather ) ) {
					$provenance['weather_season'] = $this->mark_field_live( $provenance['weather_season'], $this->live_value_for_destination_field( $live_value, $destination, 'weather' ), $destination_id );
				}
			}
		}

		if ( array_intersect( array( 'flights', 'packages' ), $verticals ) && ! empty( $fragment['route_sets'] ) && is_array( $fragment['route_sets'] ) ) {
			foreach ( $fragment['route_sets'] as $destination_id => $routes ) {
				if ( $this->valid_route_set_fragment( $routes ) ) {
					$route_live_value = $live_value;
					$route_observed   = $this->route_set_observed_at( $routes );
					if ( $route_observed ) {
						$route_live_value['observed_at'] = $route_observed;
					}
					$provenance['routes'] = $this->mark_field_live(
						$provenance['routes'],
						$route_live_value,
						sanitize_key( $destination_id ),
						$this->route_cost_metadata( $routes, $verticals )
					);
				}
			}
		}

		return $provenance;
	}

	/**
	 * Live price provenance requires the exact monetary fields used by the interface.
	 */
	private function valid_deal_fragment( $deal ) {
		return is_array( $deal )
			&& isset( $deal['currency'] )
			&& is_string( $deal['currency'] )
			&& 'USD' === $deal['currency']
			&& isset( $deal['headline_price'], $deal['total_per_person'], $deal['nights'], $deal['trend_pct'], $deal['total_scope'] )
			&& $this->is_contract_number( $deal['headline_price'] )
			&& $this->is_contract_number( $deal['total_per_person'] )
			&& is_int( $deal['nights'] )
			&& $this->is_contract_number( $deal['trend_pct'] )
			&& is_string( $deal['total_scope'] )
			&& in_array( $deal['total_scope'], array( 'destination_deal', 'package_inclusive' ), true )
			&& (float) $deal['headline_price'] >= 0
			&& (float) $deal['total_per_person'] >= (float) $deal['headline_price']
			&& $deal['nights'] > 0
			&& (float) $deal['trend_pct'] >= -100
			&& (float) $deal['trend_pct'] <= 100
			&& ( ! array_key_exists( 'observed_at', $deal ) || $this->valid_rfc3339_datetime( $deal['observed_at'] ) );
	}

	/**
	 * Hotel coverage is live only when identity and every displayed monetary field are supplied.
	 */
	private function valid_hotel_fragment( $hotel ) {
		$required = array( 'name', 'area', 'rating', 'nightly', 'nights', 'room_total', 'per_person_total', 'currency' );
		if ( ! is_array( $hotel ) || array_diff( $required, array_keys( $hotel ) ) ) {
			return false;
		}
		return is_string( $hotel['name'] )
			&& strlen( trim( $hotel['name'] ) ) >= 2
			&& strlen( trim( $hotel['name'] ) ) <= 160
			&& is_string( $hotel['area'] )
			&& strlen( trim( $hotel['area'] ) ) >= 2
			&& strlen( trim( $hotel['area'] ) ) <= 160
			&& is_string( $hotel['currency'] )
			&& preg_match( '/^[A-Z]{3}$/', $hotel['currency'] )
			&& $this->is_contract_number( $hotel['rating'] )
			&& $this->is_contract_number( $hotel['nightly'] )
			&& is_int( $hotel['nights'] )
			&& $this->is_contract_number( $hotel['room_total'] )
			&& $this->is_contract_number( $hotel['per_person_total'] )
			&& (float) $hotel['rating'] >= 0
			&& (float) $hotel['rating'] <= 5
			&& (float) $hotel['nightly'] >= 0
			&& $hotel['nights'] > 0
			&& (float) $hotel['room_total'] >= 0
			&& (float) $hotel['per_person_total'] >= 0
			&& ( ! array_key_exists( 'observed_at', $hotel ) || $this->valid_rfc3339_datetime( $hotel['observed_at'] ) );
	}

	/**
	 * Airport details cannot inherit code or timing from the editorial fallback.
	 */
	private function valid_airport_fragment( $airport ) {
		$required = array( 'code', 'name', 'direct', 'flight_minutes', 'transfer_minutes' );
		return is_array( $airport )
			&& ! array_diff( $required, array_keys( $airport ) )
			&& is_string( $airport['code'] )
			&& preg_match( '/^[A-Z]{3}$/', $airport['code'] )
			&& is_string( $airport['name'] )
			&& strlen( trim( $airport['name'] ) ) >= 2
			&& strlen( trim( $airport['name'] ) ) <= 160
			&& is_bool( $airport['direct'] )
			&& is_int( $airport['flight_minutes'] )
			&& is_int( $airport['transfer_minutes'] )
			&& $airport['flight_minutes'] > 0
			&& $airport['transfer_minutes'] >= 0
			&& ( ! array_key_exists( 'observed_at', $airport ) || $this->valid_rfc3339_datetime( $airport['observed_at'] ) );
	}

	/**
	 * Current weather must match the durable schema before it can replace editorial data.
	 */
	private function valid_weather_current_fragment( $weather ) {
		if ( ! is_array( $weather )
			|| ! array_key_exists( 'temperature_c', $weather )
			|| ! $this->is_contract_number( $weather['temperature_c'] )
			|| (float) $weather['temperature_c'] < -100
			|| (float) $weather['temperature_c'] > 70
			|| ! isset( $weather['condition'] )
			|| ! is_string( $weather['condition'] )
			|| strlen( trim( $weather['condition'] ) ) < 2
		) {
			return false;
		}

		if ( array_key_exists( 'apparent_temperature_c', $weather )
			&& null !== $weather['apparent_temperature_c']
			&& ( ! $this->is_contract_number( $weather['apparent_temperature_c'] )
				|| (float) $weather['apparent_temperature_c'] < -100
				|| (float) $weather['apparent_temperature_c'] > 80 )
		) {
			return false;
		}
		if ( array_key_exists( 'weather_code', $weather )
			&& ( ! is_int( $weather['weather_code'] ) || $weather['weather_code'] < 0 || $weather['weather_code'] > 99 )
		) {
			return false;
		}
		if ( array_key_exists( 'is_day', $weather ) && ! is_bool( $weather['is_day'] ) ) {
			return false;
		}
		if ( array_key_exists( 'live', $weather ) && ! is_bool( $weather['live'] ) ) {
			return false;
		}
		if ( array_key_exists( 'source', $weather ) && ( ! is_string( $weather['source'] ) || strlen( trim( $weather['source'] ) ) < 2 ) ) {
			return false;
		}
		return ! array_key_exists( 'observed_at', $weather ) || $this->valid_rfc3339_datetime( $weather['observed_at'] );
	}

	/**
	 * Seasonal suitability is a separate provenance field and must be non-empty.
	 */
	private function valid_weather_season_fragment( $weather ) {
		return is_array( $weather )
			&& isset( $weather['season_fit'] )
			&& is_string( $weather['season_fit'] )
			&& '' !== trim( $weather['season_fit'] )
			&& ( ! array_key_exists( 'observed_at', $weather ) || $this->valid_rfc3339_datetime( $weather['observed_at'] ) );
	}

	/**
	 * Contract numbers must be real JSON-style numbers, not coercible strings or booleans.
	 */
	private function is_contract_number( $value ) {
		return ( is_int( $value ) || is_float( $value ) ) && is_finite( (float) $value );
	}

	/**
	 * Supplier observations are accepted only as unambiguous RFC3339 instants.
	 */
	private function valid_rfc3339_datetime( $value ) {
		return null !== $this->normalize_rfc3339_utc( $value );
	}

	/**
	 * Reject normalized/impossible wall times and return one canonical UTC instant.
	 */
	private function normalize_rfc3339_utc( $value ) {
		if ( ! is_string( $value )
			|| 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,6}))?(Z|[+-](\d{2}):(\d{2}))$/', $value, $parts )
		) {
			return null;
		}

		$year          = (int) $parts[1];
		$month         = (int) $parts[2];
		$day           = (int) $parts[3];
		$hour          = (int) $parts[4];
		$minute        = (int) $parts[5];
		$second        = (int) $parts[6];
		$offset_hours  = isset( $parts[9] ) && '' !== $parts[9] ? (int) $parts[9] : 0;
		$offset_minutes = isset( $parts[10] ) && '' !== $parts[10] ? (int) $parts[10] : 0;
		if ( ! checkdate( $month, $day, $year )
			|| $hour > 23
			|| $minute > 59
			|| $second > 59
			|| $offset_hours > 23
			|| $offset_minutes > 59
		) {
			return null;
		}

		try {
			$date = new DateTimeImmutable( $value );
		} catch ( Exception $error ) {
			return null;
		}
		return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
	}

	/**
	 * Copy only deal fields that have an explicit live ownership contract.
	 */
	private function sanitize_deal_fragment( $deal ) {
		$clean = array(
			'headline_price'  => (float) $deal['headline_price'],
			'total_per_person' => (float) $deal['total_per_person'],
			'currency'        => $deal['currency'],
			'trend_pct'       => (float) $deal['trend_pct'],
			'nights'          => (int) $deal['nights'],
			'total_scope'     => $deal['total_scope'],
		);
		if ( array_key_exists( 'observed_at', $deal ) ) {
			$clean['observed_at'] = $this->normalize_rfc3339_utc( $deal['observed_at'] );
		}
		return $clean;
	}

	/**
	 * Copy only hotel identity and price fields that passed the live validator.
	 */
	private function sanitize_hotel_fragment( $hotel ) {
		$clean = array(
			'name'             => sanitize_text_field( $hotel['name'] ),
			'area'             => sanitize_text_field( $hotel['area'] ),
			'rating'           => (float) $hotel['rating'],
			'nightly'          => (float) $hotel['nightly'],
			'nights'           => (int) $hotel['nights'],
			'room_total'       => (float) $hotel['room_total'],
			'per_person_total' => (float) $hotel['per_person_total'],
			'currency'         => $hotel['currency'],
		);
		if ( array_key_exists( 'observed_at', $hotel ) ) {
			$clean['observed_at'] = $this->normalize_rfc3339_utc( $hotel['observed_at'] );
		}
		return $clean;
	}

	/**
	 * Copy only airport fields that passed the live validator.
	 */
	private function sanitize_airport_fragment( $airport ) {
		$clean = array(
			'code'             => $airport['code'],
			'name'             => sanitize_text_field( $airport['name'] ),
			'direct'           => $airport['direct'],
			'flight_minutes'   => $airport['flight_minutes'],
			'transfer_minutes' => $airport['transfer_minutes'],
		);
		if ( array_key_exists( 'observed_at', $airport ) ) {
			$clean['observed_at'] = $this->normalize_rfc3339_utc( $airport['observed_at'] );
		}
		return $clean;
	}

	/**
	 * Reject malformed route fragments before they can certify inherited or invalid totals.
	 */
	private function valid_route_set_fragment( $routes ) {
		if ( ! is_array( $routes ) || ! $routes ) {
			return false;
		}
		$required_route = array( 'id', 'label', 'badge', 'duration_minutes', 'stops', 'ticket_mode', 'risk', 'emissions_kg', 'score', 'currency', 'costs', 'pros', 'cons' );
		$required_route[] = 'observed_at';
		$allowed_route  = array_merge( $required_route, array( 'via' ) );
		$required_costs = array( 'flight', 'baggage', 'total' );
		$allowed_costs  = array( 'flight', 'baggage', 'hotel', 'taxes', 'transfers', 'local_transport', 'activities', 'dining', 'insurance', 'connectivity', 'equipment', 'entry', 'overnight', 'total' );
		$route_currencies = array();
		$route_observed   = array();
		foreach ( $routes as $route ) {
			if ( ! is_array( $route ) || array_diff( $required_route, array_keys( $route ) ) || array_diff( array_keys( $route ), $allowed_route ) || ! is_array( $route['costs'] ) || array_diff( $required_costs, array_keys( $route['costs'] ) ) ) {
				return false;
			}
			if ( array_diff( array_keys( $route['costs'] ), $allowed_costs ) ) {
				return false;
			}
			if ( ! preg_match( '/^[A-Z]{3}$/', (string) $route['currency'] ) || ! preg_match( '/^[a-z0-9-]{2,80}$/', (string) $route['id'] ) || ! is_string( $route['label'] ) || strlen( trim( $route['label'] ) ) < 2 || ! is_string( $route['badge'] ) || strlen( trim( $route['badge'] ) ) < 2 || ! is_array( $route['pros'] ) || ! $route['pros'] || ! is_array( $route['cons'] ) || ! $route['cons'] ) {
				return false;
			}
			if ( array_filter( array_merge( $route['pros'], $route['cons'] ), static function ( $copy ) { return ! is_string( $copy ) || strlen( trim( $copy ) ) < 2; } ) ) {
				return false;
			}
			if ( ! in_array( $route['ticket_mode'], array( 'single', 'separate' ), true ) || ! in_array( $route['risk'], array( 'low', 'medium', 'high' ), true ) || ! $this->is_contract_number( $route['score'] ) || (float) $route['score'] < 0 || (float) $route['score'] > 100 || ! $this->is_contract_number( $route['emissions_kg'] ) || (float) $route['emissions_kg'] < 0 ) {
				return false;
			}
			if ( isset( $route['via'] ) && null !== $route['via'] && ! preg_match( '/^[A-Z]{3}$/', (string) $route['via'] ) ) {
				return false;
			}
			if ( ! $this->valid_rfc3339_datetime( $route['observed_at'] ) ) {
				return false;
			}
			$route_currencies[] = strtoupper( (string) $route['currency'] );
			$route_observed[]   = $this->normalize_rfc3339_utc( $route['observed_at'] );
			$component_total = 0;
			foreach ( $route['costs'] as $cost_key => $cost_value ) {
				if ( ! $this->is_contract_number( $cost_value ) || (float) $cost_value < 0 ) {
					return false;
				}
				if ( 'total' !== $cost_key ) {
					$component_total += (float) $cost_value;
				}
			}
			if ( abs( $component_total - (float) $route['costs']['total'] ) > 0.01 || ! is_int( $route['duration_minutes'] ) || $route['duration_minutes'] <= 0 || ! is_int( $route['stops'] ) || $route['stops'] < 0 ) {
				return false;
			}
		}
		return 1 === count( array_unique( $route_currencies ) )
			&& count( $route_observed ) === count( $routes )
			&& 1 === count( array_unique( $route_observed ) );
	}

	/**
	 * Copy only schema-owned route fields and canonicalize every supplier timestamp.
	 */
	private function sanitize_route_set_fragment( $routes ) {
		$clean_routes = array();
		foreach ( (array) $routes as $route ) {
			$clean = array(
				'id'               => sanitize_key( $route['id'] ),
				'currency'         => strtoupper( sanitize_text_field( $route['currency'] ) ),
				'label'            => sanitize_text_field( $route['label'] ),
				'badge'            => sanitize_text_field( $route['badge'] ),
				'duration_minutes' => (int) $route['duration_minutes'],
				'stops'            => (int) $route['stops'],
				'ticket_mode'      => sanitize_key( $route['ticket_mode'] ),
				'risk'             => sanitize_key( $route['risk'] ),
				'emissions_kg'      => (float) $route['emissions_kg'],
				'score'            => (float) $route['score'],
				'observed_at'      => $this->normalize_rfc3339_utc( $route['observed_at'] ),
				'costs'            => array(),
				'pros'             => array_values( array_map( 'sanitize_text_field', $route['pros'] ) ),
				'cons'             => array_values( array_map( 'sanitize_text_field', $route['cons'] ) ),
			);
			if ( array_key_exists( 'via', $route ) ) {
				$clean['via'] = null === $route['via'] ? null : strtoupper( sanitize_text_field( $route['via'] ) );
			}
			foreach ( $route['costs'] as $key => $value ) {
				$clean['costs'][ sanitize_key( $key ) ] = (float) $value;
			}
			$clean_routes[] = $clean;
		}
		return $clean_routes;
	}

	/**
	 * Describe only the cost components a supplier vertical can legitimately own.
	 * Route timing can be live while prices remain partial or unavailable.
	 */
	private function route_cost_metadata( $routes, $verticals ) {
		$component_permissions = array(
			'flights'   => array( 'flight', 'baggage' ),
			'hotels'    => array( 'hotel' ),
			'transfers' => array( 'transfers' ),
			'insurance' => array( 'insurance' ),
			'packages'  => array( 'flight', 'baggage', 'hotel', 'taxes', 'transfers', 'local_transport', 'activities', 'dining', 'insurance', 'connectivity', 'equipment', 'entry' ),
		);
		$permitted = array();
		foreach ( $verticals as $vertical ) {
			if ( isset( $component_permissions[ $vertical ] ) ) {
				$permitted = array_merge( $permitted, $component_permissions[ $vertical ] );
			}
		}
		$permitted = array_values( array_unique( $permitted ) );
		$currencies = array_values(
			array_unique(
				array_filter(
					array_map(
						function ( $route ) {
							return isset( $route['currency'] ) ? strtoupper( sanitize_text_field( $route['currency'] ) ) : '';
						},
						(array) $routes
					)
				)
			)
		);
		$currency = 1 === count( $currencies ) ? $currencies[0] : null;
		$full_total_components = array( 'flight', 'baggage', 'hotel', 'taxes', 'transfers', 'local_transport', 'activities', 'dining', 'insurance', 'connectivity', 'equipment', 'entry' );
		$full_total_live = $currency && in_array( 'packages', $verticals, true );
		foreach ( (array) $routes as $route ) {
			$cost_keys = isset( $route['costs'] ) ? array_keys( (array) $route['costs'] ) : array();
			if ( array_diff( $full_total_components, $cost_keys )
				|| array_intersect( array( 'stay', 'overnight' ), $cost_keys )
				|| count( array_diff( $cost_keys, array_merge( $full_total_components, array( 'total' ) ) ) )
			) {
				$full_total_live = false;
				break;
			}
		}
		return array(
			'currency'        => $currency,
			'cost_components' => $currency ? $permitted : array(),
			'total_live'      => $full_total_live,
			'price_scope'     => $full_total_live ? 'full_trip_total' : 'partial_route_components',
		);
	}

	/**
	 * Prefer the observation timestamp attached to this exact destination field.
	 */
	private function live_value_for_destination_field( $live_value, $destination, $field ) {
		if ( isset( $destination[ $field ]['observed_at'] ) && $this->valid_rfc3339_datetime( $destination[ $field ]['observed_at'] ) ) {
			$live_value['observed_at'] = $this->normalize_rfc3339_utc( $destination[ $field ]['observed_at'] );
		}
		return $live_value;
	}

	/**
	 * Use a route-set timestamp only when every supplied timestamp agrees.
	 */
	private function route_set_observed_at( $routes ) {
		$routes   = array_values( (array) $routes );
		$observed = array_values(
			array_unique(
				array_filter(
					array_map(
						function ( $route ) {
							return isset( $route['observed_at'] ) && $this->valid_rfc3339_datetime( $route['observed_at'] ) ? $this->normalize_rfc3339_utc( $route['observed_at'] ) : null;
						},
						(array) $routes
					)
				)
			)
		);
		return count( $observed ) === count( $routes ) && 1 === count( array_unique( $observed ) ) ? $observed[0] : null;
	}

	/**
	 * Mark one field as live only for destination IDs physically present in the adapter fragment.
	 */
	private function mark_field_live( $field, $live_value, $destination_id, $metadata = array() ) {
		$destination_ids = isset( $field['destination_ids'] ) && is_array( $field['destination_ids'] ) ? $field['destination_ids'] : array();
		$by_destination  = isset( $field['by_destination'] ) && is_array( $field['by_destination'] ) ? $field['by_destination'] : array();
		if ( $destination_id ) {
			$destination_id                    = sanitize_key( $destination_id );
			$destination_ids[]                 = $destination_id;
			$by_destination[ $destination_id ] = array_merge(
				array(
				'source'      => isset( $live_value['source'] ) ? sanitize_key( $live_value['source'] ) : null,
				'observed_at' => isset( $live_value['observed_at'] ) ? $this->normalize_rfc3339_utc( $live_value['observed_at'] ) : null,
				'retrieved_at' => isset( $live_value['retrieved_at'] ) ? $this->normalize_rfc3339_utc( $live_value['retrieved_at'] ) : null,
				),
				is_array( $metadata ) ? $metadata : array()
			);
		}
		$live_value['destination_ids'] = array_values( array_unique( array_filter( $destination_ids ) ) );
		$live_value['by_destination']  = $by_destination;
		return $live_value;
	}

	/**
	 * Preserve explicit supplier observation time when the fragment provides it.
	 */
	private function fragment_observed_at( $fragment ) {
		foreach ( isset( $fragment['provider_status'] ) ? (array) $fragment['provider_status'] : array() as $status ) {
			if ( is_array( $status ) && isset( $status['observed_at'] ) && $this->valid_rfc3339_datetime( $status['observed_at'] ) ) {
				return $this->normalize_rfc3339_utc( $status['observed_at'] );
			}
		}
		return null;
	}

	/**
	 * Merge successful live coverage without allowing an empty fragment to erase it.
	 */
	private function merge_field_provenance( $base, $overlay ) {
		foreach ( array_keys( $base ) as $field ) {
			if ( ! empty( $overlay[ $field ]['live'] ) ) {
				$destination_ids = array_merge(
					isset( $base[ $field ]['destination_ids'] ) ? (array) $base[ $field ]['destination_ids'] : array(),
					isset( $overlay[ $field ]['destination_ids'] ) ? (array) $overlay[ $field ]['destination_ids'] : array()
				);
				$by_destination = array_replace(
					isset( $base[ $field ]['by_destination'] ) ? (array) $base[ $field ]['by_destination'] : array(),
					isset( $overlay[ $field ]['by_destination'] ) ? (array) $overlay[ $field ]['by_destination'] : array()
				);
				$base[ $field ]                    = $overlay[ $field ];
				$base[ $field ]['destination_ids'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', $destination_ids ) ) ) );
				$base[ $field ]['by_destination']  = $by_destination;
				$sources = array_values( array_unique( array_filter( array_column( $by_destination, 'source' ) ) ) );
				$observed_times = array_values( array_unique( array_filter( array_column( $by_destination, 'observed_at' ) ) ) );
				$retrieved_times = array_values( array_unique( array_filter( array_column( $by_destination, 'retrieved_at' ) ) ) );
				$base[ $field ]['source']       = 1 === count( $sources ) ? sanitize_key( $sources[0] ) : null;
				$base[ $field ]['observed_at']  = 1 === count( $observed_times ) ? $this->normalize_rfc3339_utc( $observed_times[0] ) : null;
				$base[ $field ]['retrieved_at'] = 1 === count( $retrieved_times ) ? $this->normalize_rfc3339_utc( $retrieved_times[0] ) : null;
			}
		}
		return $base;
	}

	private function is_contract_fragment( $fragment ) {
		return is_array( $fragment ) && ( isset( $fragment['destinations'] ) || isset( $fragment['route_sets'] ) || isset( $fragment['provider_status'] ) );
	}

	/**
	 * A status-only or fully rejected live response is degraded, not healthy data.
	 */
	private function fragment_has_usable_live_fields( $fragment ) {
		return is_array( $fragment )
			&& ( ! empty( $fragment['destinations'] ) || ! empty( $fragment['route_sets'] ) );
	}

	/**
	 * Whitelist live adapter fields before they can overlay the editorial contract.
	 * Invalid or out-of-vertical values remain absent and therefore cannot influence
	 * filtering, sorting, copy, or price formatting through fallback inheritance.
	 */
	private function sanitize_live_fragment( $fragment, $verticals, $known_destination_ids ) {
		$verticals = array_values( array_map( 'sanitize_key', (array) $verticals ) );
		$known_destination_ids = array_values( array_filter( array_map( 'sanitize_key', (array) $known_destination_ids ) ) );
		$clean     = array();
		if ( isset( $fragment['provider_status'] ) && is_array( $fragment['provider_status'] ) ) {
			foreach ( $fragment['provider_status'] as $vertical => $status ) {
				if ( ! is_array( $status ) ) {
					continue;
				}
				$clean_status = $status;
				if ( array_key_exists( 'observed_at', $clean_status ) ) {
					$normalized = $this->normalize_rfc3339_utc( $clean_status['observed_at'] );
					if ( null === $normalized ) {
						unset( $clean_status['observed_at'] );
					} else {
						$clean_status['observed_at'] = $normalized;
					}
				}
				$clean['provider_status'][ sanitize_key( $vertical ) ] = $clean_status;
			}
		}

		foreach ( isset( $fragment['destinations'] ) ? (array) $fragment['destinations'] : array() as $destination ) {
			$destination_id = is_array( $destination ) && ! empty( $destination['id'] ) ? sanitize_key( $destination['id'] ) : '';
			if ( ! $destination_id || ! in_array( $destination_id, $known_destination_ids, true ) ) {
				continue;
			}
			$clean_destination = array( 'id' => $destination_id );
			if ( array_intersect( array( 'deals', 'packages' ), $verticals ) && $this->valid_deal_fragment( isset( $destination['deal'] ) ? $destination['deal'] : null ) ) {
				$clean_destination['deal'] = $this->sanitize_deal_fragment( $destination['deal'] );
			}
			if ( in_array( 'hotels', $verticals, true ) && $this->valid_hotel_fragment( isset( $destination['hotel'] ) ? $destination['hotel'] : null ) ) {
				$clean_destination['hotel'] = $this->sanitize_hotel_fragment( $destination['hotel'] );
			}
			if ( in_array( 'flights', $verticals, true ) && $this->valid_airport_fragment( isset( $destination['airport'] ) ? $destination['airport'] : null ) ) {
				$clean_destination['airport'] = $this->sanitize_airport_fragment( $destination['airport'] );
			}
			if ( in_array( 'weather', $verticals, true ) && isset( $destination['weather'] ) && is_array( $destination['weather'] ) ) {
				$weather = array();
				if ( $this->valid_weather_current_fragment( $destination['weather'] ) ) {
					$weather['temperature_c'] = (float) $destination['weather']['temperature_c'];
					$weather['condition']     = sanitize_text_field( $destination['weather']['condition'] );
					if ( array_key_exists( 'apparent_temperature_c', $destination['weather'] ) ) {
						$weather['apparent_temperature_c'] = null === $destination['weather']['apparent_temperature_c'] ? null : (float) $destination['weather']['apparent_temperature_c'];
					}
					if ( array_key_exists( 'weather_code', $destination['weather'] ) ) {
						$weather['weather_code'] = (int) $destination['weather']['weather_code'];
					}
					if ( array_key_exists( 'is_day', $destination['weather'] ) ) {
						$weather['is_day'] = $destination['weather']['is_day'];
					}
					if ( array_key_exists( 'live', $destination['weather'] ) ) {
						$weather['live'] = $destination['weather']['live'];
					}
					if ( array_key_exists( 'source', $destination['weather'] ) ) {
						$weather['source'] = sanitize_text_field( $destination['weather']['source'] );
					}
				}
				if ( $this->valid_weather_season_fragment( $destination['weather'] ) ) {
					$weather['season_fit'] = sanitize_text_field( $destination['weather']['season_fit'] );
				}
				if ( $weather && isset( $destination['weather']['observed_at'] ) && $this->valid_rfc3339_datetime( $destination['weather']['observed_at'] ) ) {
					$weather['observed_at'] = $this->normalize_rfc3339_utc( $destination['weather']['observed_at'] );
				}
				if ( $weather ) {
					$clean_destination['weather'] = $weather;
				}
			}
			if ( count( $clean_destination ) > 1 ) {
				$clean['destinations'][] = $clean_destination;
			}
		}

		if ( array_intersect( array( 'flights', 'packages' ), $verticals ) && isset( $fragment['route_sets'] ) && is_array( $fragment['route_sets'] ) ) {
			foreach ( $fragment['route_sets'] as $destination_id => $routes ) {
				if ( in_array( sanitize_key( $destination_id ), $known_destination_ids, true ) && $this->valid_route_set_fragment( $routes ) ) {
					$clean['route_sets'][ sanitize_key( $destination_id ) ] = $this->sanitize_route_set_fragment( $routes );
				}
			}
		}

		return $clean;
	}

	private function merge_contract( $base, $overlay ) {
		$merged = array_replace_recursive( $base, $overlay );

		if ( isset( $base['destinations'] ) || isset( $overlay['destinations'] ) ) {
			$merged['destinations'] = $this->merge_destinations(
				isset( $base['destinations'] ) ? $base['destinations'] : array(),
				isset( $overlay['destinations'] ) ? $overlay['destinations'] : array()
			);
		}

		if ( isset( $overlay['route_sets'] ) ) {
			$merged['route_sets'] = array_replace(
				isset( $base['route_sets'] ) ? $base['route_sets'] : array(),
				$overlay['route_sets']
			);
		}

		return $merged;
	}

	private function merge_destinations( $base, $overlay ) {
		$indexed = array();
		$order   = array();

		foreach ( (array) $base as $destination ) {
			if ( empty( $destination['id'] ) ) {
				continue;
			}
			$indexed[ $destination['id'] ] = $destination;
			$order[]                       = $destination['id'];
		}

		foreach ( (array) $overlay as $destination ) {
			if ( empty( $destination['id'] ) ) {
				continue;
			}
			$id = $destination['id'];
			if ( ! isset( $indexed[ $id ] ) ) {
				$order[] = $id;
			}
			$indexed[ $id ] = isset( $indexed[ $id ] ) ? array_replace_recursive( $indexed[ $id ], $destination ) : $destination;
		}

		return array_values( array_intersect_key( $indexed, array_flip( $order ) ) );
	}

	private function derive_data_mode( $field_provenance, $destinations ) {
		$destination_ids = array_values( array_filter( array_map( 'sanitize_key', array_column( (array) $destinations, 'id' ) ) ) );
		if ( ! $destination_ids || ! $field_provenance ) {
			return 'demo';
		}
		$live_coverage = 0;
		foreach ( $field_provenance as $field ) {
			$field_destinations = ! empty( $field['destination_ids'] ) && is_array( $field['destination_ids'] ) ? $field['destination_ids'] : array();
			$live_coverage += count( array_intersect( $destination_ids, $field_destinations ) );
		}
		if ( 0 === $live_coverage ) {
			return 'demo';
		}
		$total_coverage = count( $destination_ids ) * count( $field_provenance );
		return $live_coverage === $total_coverage ? 'live' : 'mixed';
	}
}
