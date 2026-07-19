<?php
/**
 * Closed, deterministic Commerce Core sandbox product catalog.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Sandbox_Catalog {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_FIXTURE_BYTES = 2097152;
	const MAX_PRODUCTS      = 500;

	/** @var string */
	private $path;

	/** @var array|null */
	private $catalog;

	/** @var WP_Error|null */
	private $error;

	/**
	 * @param string|null $path An explicit fixture path for deterministic tests.
	 */
	public function __construct( $path = null ) {
		$this->path = null === $path
			? dirname( __DIR__, 2 ) . '/assets/fixtures/commerce-sandbox/product-catalog.json'
			: (string) $path;
		$loaded = $this->load_fixture();
		if ( is_wp_error( $loaded ) ) {
			$this->error = $loaded;
		} else {
			$this->catalog = $loaded;
		}
	}

	/**
	 * Return fixture readiness without leaking filesystem details.
	 *
	 * @return true|WP_Error
	 */
	public function readiness() {
		return is_wp_error( $this->error ) ? $this->error : true;
	}

	/**
	 * Return a stable digest of the strictly validated fixture.
	 */
	public function catalog_digest() {
		return is_array( $this->catalog ) ? Tra_Vel_Commerce_Policy::canonical_digest( $this->catalog ) : '';
	}

	/**
	 * Return provider IDs for one vertical in deterministic order.
	 */
	public function provider_ids_for_vertical( $vertical ) {
		if ( is_wp_error( $this->error ) ) {
			return $this->error;
		}
		$vertical = Tra_Vel_Commerce_Taxonomy::vertical( $vertical );
		if ( '' === $vertical ) {
			return new WP_Error( 'tra_vel_commerce_catalog_vertical_invalid', 'A canonical commerce vertical is required.', array( 'status' => 400 ) );
		}
		$providers = array();
		foreach ( $this->catalog['providers'] as $provider ) {
			if ( in_array( $vertical, $provider['verticals'], true ) ) {
				$providers[] = $provider['provider_id'];
			}
		}
		sort( $providers, SORT_STRING );
		return $providers;
	}

	/**
	 * Resolve a public provider-reference HMAC to exactly one private catalog row.
	 *
	 * This is a server-only boundary for routing preparation. The returned row
	 * contains the private fixture locator and must never be serialized into a
	 * public order, operation, REST response, event, or log context.
	 *
	 * @param string $provider_id                Expected canonical provider ID.
	 * @param string $provider_reference_digest Public HMAC emitted by search.
	 * @param string $secret                     The same injected base secret used by search.
	 * @return array|WP_Error
	 */
	public function resolve_private_product( $provider_id, $provider_reference_digest, $secret ) {
		if ( is_wp_error( $this->error ) ) {
			return $this->error;
		}
		$raw_provider_id = (string) $provider_id;
		$provider_id = sanitize_key( $raw_provider_id );
		if ( $raw_provider_id !== $provider_id || ! preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $provider_id ) || ! is_string( $provider_reference_digest ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $provider_reference_digest ) || ! is_string( $secret ) || strlen( $secret ) < 16 ) {
			return $this->error( 'private_route_input_invalid', 'The private catalog route requires a canonical provider, digest, and server secret.', 400 );
		}

		$hmac_secret = $secret . '|tra-vel-commerce-search-v1';
		$matches = array();
		foreach ( $this->catalog['products'] as $product ) {
			$expected = hash_hmac( 'sha256', 'provider-reference|' . $product['provider_id'] . '|' . $product['private_product_ref'], $hmac_secret );
			if ( hash_equals( $provider_reference_digest, $expected ) ) {
				$matches[] = $product;
			}
		}
		if ( ! $matches ) {
			return $this->error( 'private_route_not_found', 'The provider-reference digest does not resolve to current server inventory.', 409 );
		}
		if ( 1 !== count( $matches ) ) {
			return $this->error( 'private_route_ambiguous', 'The provider-reference digest resolves to more than one private catalog row.', 409 );
		}
		if ( ! hash_equals( $matches[0]['provider_id'], $provider_id ) ) {
			return $this->error( 'private_route_provider_mismatch', 'The private catalog row belongs to a different provider.', 409 );
		}
		return $matches[0];
	}

	/**
	 * Search one seeded provider. Returned candidates are server-internal and
	 * contain private fixture references; callers must project them before use.
	 *
	 * @return array|WP_Error
	 */
	public function search_provider( $provider_id, $vertical, $query ) {
		if ( is_wp_error( $this->error ) ) {
			return $this->error;
		}
		$provider_id = sanitize_key( (string) $provider_id );
		$vertical    = Tra_Vel_Commerce_Taxonomy::vertical( $vertical );
		$query       = $this->normalize_query( $query );
		if ( '' === $vertical || ! preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $provider_id ) || is_wp_error( $query ) ) {
			return is_wp_error( $query ) ? $query : new WP_Error( 'tra_vel_commerce_catalog_query_invalid', 'The sandbox catalog query is invalid.', array( 'status' => 400 ) );
		}
		$eligible_provider = false;
		foreach ( $this->catalog['providers'] as $provider ) {
			if ( $provider_id === $provider['provider_id'] && in_array( $vertical, $provider['verticals'], true ) ) {
				$eligible_provider = true;
				break;
			}
		}
		if ( ! $eligible_provider ) {
			return array();
		}

		$candidates = array();
		foreach ( $this->catalog['products'] as $product ) {
			if ( $provider_id !== $product['provider_id'] || $vertical !== $product['vertical'] || $query['currency'] !== $product['pricing']['currency'] || ! $this->matches_query( $product, $query ) ) {
				continue;
			}
			$ledger = $this->price_product( $product, $query );
			if ( is_wp_error( $ledger ) ) {
				return $ledger;
			}
			if ( null !== $query['budget_limit_minor'] && $ledger['total_amount_minor'] > $query['budget_limit_minor'] ) {
				continue;
			}
			$inventory_quantity = $this->inventory_quantity( $product['pricing']['quantity_rule'], $product['pricing']['capacity_per_unit'], $query );
			if ( is_wp_error( $inventory_quantity ) ) {
				return $inventory_quantity;
			}
			if ( $inventory_quantity > $product['inventory']['quantity_available'] ) {
				continue;
			}
			$remaining = $product['inventory']['quantity_available'] - $inventory_quantity;
			$commission = $this->commission_projection( $product['commission'], $ledger );
			if ( is_wp_error( $commission ) ) {
				return $commission;
			}
			$candidates[] = array(
				'private_product_ref' => $product['private_product_ref'],
				'dedupe_key'          => $product['dedupe_key'],
				'provider_id'         => $product['provider_id'],
				'vertical'            => $product['vertical'],
				'title'               => $product['title'],
				'subtitle'            => $product['subtitle'],
				'badges'              => $product['badges'],
				'media'               => $product['media'],
				'facts'               => $product['facts'],
				'geometry'            => $product['geometry'],
				'pricing'             => $ledger,
				'availability'        => array(
					'state'              => $remaining <= $product['inventory']['limited_threshold'] ? 'limited' : 'available',
					'quantity_remaining' => $remaining,
					'hold_minutes'       => $product['inventory']['hold_minutes'],
				),
				'terms'               => $product['terms'],
				'capabilities'        => $product['capabilities'],
				'attributes'          => $product['attributes'],
				'commission'          => $commission,
				'product_digest'      => Tra_Vel_Commerce_Policy::canonical_digest( $product ),
			);
		}
		usort(
			$candidates,
			static function ( $left, $right ) {
				$price = (int) $left['pricing']['total_amount_minor'] <=> (int) $right['pricing']['total_amount_minor'];
				return 0 !== $price ? $price : strcmp( $left['private_product_ref'], $right['private_product_ref'] );
			}
		);
		return $candidates;
	}

	/**
	 * Read, decode, and validate the complete fixture before accepting any row.
	 *
	 * @return array|WP_Error
	 */
	private function load_fixture() {
		if ( ! is_file( $this->path ) || ! is_readable( $this->path ) ) {
			return $this->error( 'fixture_unavailable', 'The commerce sandbox fixture is unavailable.', 503 );
		}
		$size = filesize( $this->path );
		if ( false === $size || $size < 2 || $size > self::MAX_FIXTURE_BYTES ) {
			return $this->error( 'fixture_size_invalid', 'The commerce sandbox fixture has an unsafe size.', 500 );
		}
		$json = file_get_contents( $this->path );
		if ( false === $json || strlen( $json ) !== $size ) {
			return $this->error( 'fixture_read_failed', 'The commerce sandbox fixture could not be read completely.', 500 );
		}
		$fixture = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $fixture ) ) {
			return $this->error( 'fixture_json_invalid', 'The commerce sandbox fixture is not valid JSON.', 500 );
		}
		return $this->normalize_fixture( $fixture );
	}

	/**
	 * @return array|WP_Error
	 */
	private function normalize_fixture( $fixture ) {
		$required = array( 'contract_version', 'environment', 'catalog_id', 'scenario', 'providers', 'products', 'sandbox_truth', 'data_boundary' );
		if ( ! $this->exact_object( $fixture, $required ) || self::CONTRACT_VERSION !== $fixture['contract_version'] || 'sandbox' !== $fixture['environment'] || ! is_string( $fixture['catalog_id'] ) || ! preg_match( '/^[a-z][a-z0-9_]{7,79}$/', $fixture['catalog_id'] ) ) {
			return $this->error( 'fixture_shape_invalid', 'The commerce sandbox fixture root is not a closed supported contract.', 500 );
		}
		$scenario = $this->normalize_scenario( $fixture['scenario'] );
		if ( is_wp_error( $scenario ) || ! $this->sandbox_truth( $fixture['sandbox_truth'] ) || ! $this->data_boundary( $fixture['data_boundary'] ) ) {
			return is_wp_error( $scenario ) ? $scenario : $this->error( 'fixture_truth_invalid', 'The commerce sandbox truth boundary is invalid.', 500 );
		}
		if ( ! $this->is_list( $fixture['providers'] ) || ! $fixture['providers'] || count( $fixture['providers'] ) > 50 || ! $this->is_list( $fixture['products'] ) || ! $fixture['products'] || count( $fixture['products'] ) > self::MAX_PRODUCTS ) {
			return $this->error( 'fixture_collection_invalid', 'The commerce sandbox provider or product collection is invalid.', 500 );
		}
		$providers = array();
		$provider_map = array();
		foreach ( $fixture['providers'] as $provider ) {
			$provider = $this->normalize_provider( $provider );
			if ( is_wp_error( $provider ) ) {
				return $provider;
			}
			if ( isset( $provider_map[ $provider['provider_id'] ] ) ) {
				return $this->error( 'fixture_provider_duplicate', 'A sandbox provider ID is duplicated.', 500 );
			}
			$provider_map[ $provider['provider_id'] ] = $provider;
			$providers[] = $provider;
		}
		usort( $providers, static function ( $left, $right ) { return strcmp( $left['provider_id'], $right['provider_id'] ); } );

		$products = array();
		$product_refs = array();
		$providers_with_products = array();
		$verticals_with_products = array();
		foreach ( $fixture['products'] as $product ) {
			$product = $this->normalize_product( $product );
			if ( is_wp_error( $product ) ) {
				return $product;
			}
			if ( isset( $product_refs[ $product['private_product_ref'] ] ) || ! isset( $provider_map[ $product['provider_id'] ] ) || ! in_array( $product['vertical'], $provider_map[ $product['provider_id'] ]['verticals'], true ) ) {
				return $this->error( 'fixture_product_binding_invalid', 'A sandbox product reference or provider binding is invalid.', 500 );
			}
			$product_refs[ $product['private_product_ref'] ] = true;
			$providers_with_products[ $product['provider_id'] ] = true;
			$verticals_with_products[ $product['vertical'] ] = true;
			$products[] = $product;
		}
		foreach ( array_keys( $provider_map ) as $provider_id ) {
			if ( ! isset( $providers_with_products[ $provider_id ] ) ) {
				return $this->error( 'fixture_provider_empty', 'Every sandbox provider must own at least one product.', 500 );
			}
		}
		foreach ( Tra_Vel_Commerce_Taxonomy::VERTICALS as $vertical ) {
			if ( 'package' === $vertical ) {
				continue;
			}
			if ( ! isset( $verticals_with_products[ $vertical ] ) ) {
				return $this->error( 'fixture_vertical_missing', 'Every component commerce vertical requires seeded inventory.', 500 );
			}
		}
		usort(
			$products,
			static function ( $left, $right ) {
				$vertical = strcmp( $left['vertical'], $right['vertical'] );
				return 0 !== $vertical ? $vertical : strcmp( $left['private_product_ref'], $right['private_product_ref'] );
			}
		);
		return array(
			'contract_version' => self::CONTRACT_VERSION,
			'environment'      => 'sandbox',
			'catalog_id'       => $fixture['catalog_id'],
			'scenario'         => $scenario,
			'providers'        => $providers,
			'products'         => $products,
			'sandbox_truth'    => $fixture['sandbox_truth'],
			'data_boundary'    => $fixture['data_boundary'],
		);
	}

	private function normalize_scenario( $scenario ) {
		$required = array( 'name', 'destination_codes', 'default_origin_codes', 'default_nights', 'default_trip_days' );
		if ( ! $this->exact_object( $scenario, $required ) || ! $this->text( $scenario['name'], 3, 120 ) || ! $this->code_list( $scenario['destination_codes'], false ) || ! $this->code_list( $scenario['default_origin_codes'], false ) || ! is_int( $scenario['default_nights'] ) || $scenario['default_nights'] < 1 || $scenario['default_nights'] > 365 || ! is_int( $scenario['default_trip_days'] ) || $scenario['default_trip_days'] !== $scenario['default_nights'] + 1 ) {
			return $this->error( 'fixture_scenario_invalid', 'The commerce sandbox scenario is invalid.', 500 );
		}
		return $scenario;
	}

	private function normalize_provider( $provider ) {
		if ( ! $this->exact_object( $provider, array( 'provider_id', 'verticals' ) ) || ! is_string( $provider['provider_id'] ) || $provider['provider_id'] !== sanitize_key( $provider['provider_id'] ) || ! preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $provider['provider_id'] ) ) {
			return $this->error( 'fixture_provider_invalid', 'A commerce sandbox provider descriptor is invalid.', 500 );
		}
		$verticals = Tra_Vel_Commerce_Taxonomy::verticals( $provider['verticals'] );
		if ( is_wp_error( $verticals ) || $verticals !== $provider['verticals'] ) {
			return $this->error( 'fixture_provider_invalid', 'Sandbox provider verticals must be canonical, unique, and sorted.', 500 );
		}
		return array( 'provider_id' => $provider['provider_id'], 'verticals' => $verticals );
	}

	private function normalize_product( $product ) {
		$required = array( 'private_product_ref', 'dedupe_key', 'provider_id', 'vertical', 'title', 'subtitle', 'destination_codes', 'origin_codes', 'service_window', 'badges', 'media', 'facts', 'pricing', 'inventory', 'terms', 'geometry', 'attributes', 'commission', 'capabilities' );
		if ( ! $this->exact_object( $product, $required ) || ! is_string( $product['private_product_ref'] ) || ! preg_match( '/^px_[a-z0-9_]{8,90}$/', $product['private_product_ref'] ) || ! is_string( $product['dedupe_key'] ) || ! preg_match( '/^[a-z0-9][a-z0-9_]{7,100}$/', $product['dedupe_key'] ) || ! is_string( $product['provider_id'] ) || $product['provider_id'] !== sanitize_key( $product['provider_id'] ) || ! preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $product['provider_id'] ) || '' === Tra_Vel_Commerce_Taxonomy::vertical( $product['vertical'] ) || ! $this->text( $product['title'], 3, 160 ) || ! $this->text( $product['subtitle'], 0, 240 ) || ! $this->code_list( $product['destination_codes'], false ) || ! $this->code_list( $product['origin_codes'], true ) ) {
			return $this->error( 'fixture_product_invalid', 'A commerce sandbox product identity or route binding is invalid.', 500 );
		}
		if ( ! $this->service_window( $product['service_window'] ) || ! $this->string_list( $product['badges'], 0, 8, 60 ) || ! $this->media( $product['media'] ) || ! $this->facts( $product['facts'] ) || ! $this->pricing( $product['pricing'], $product['vertical'] ) || ! $this->inventory( $product['inventory'] ) || ! $this->terms( $product['terms'] ) || ! $this->geometry( $product['geometry'] ) || ! $this->attributes( $product['attributes'] ) || ! $this->commission( $product['commission'], $product['pricing']['currency'] ) || ! $this->capabilities( $product['capabilities'] ) ) {
			return $this->error( 'fixture_product_invalid', 'A commerce sandbox product contains invalid closed commercial data.', 500 );
		}
		return $product;
	}

	private function service_window( $window ) {
		return $this->exact_object( $window, array( 'available_from', 'available_until' ) ) && $this->date( $window['available_from'] ) && $this->date( $window['available_until'] ) && $window['available_from'] <= $window['available_until'];
	}

	private function media( $media ) {
		if ( ! $this->is_list( $media ) || count( $media ) > 12 ) {
			return false;
		}
		foreach ( $media as $item ) {
			if ( ! $this->exact_object( $item, array( 'asset_ref', 'kind', 'alt' ) ) || ! is_string( $item['asset_ref'] ) || ! preg_match( '/^asset:[A-Za-z0-9._-]{1,100}$/', $item['asset_ref'] ) || ! in_array( $item['kind'], array( 'image', 'panorama', 'model_preview' ), true ) || ! $this->text( $item['alt'], 1, 180 ) ) {
				return false;
			}
		}
		return true;
	}

	private function facts( $facts ) {
		if ( ! $this->is_list( $facts ) || count( $facts ) > 32 ) {
			return false;
		}
		$keys = array();
		foreach ( $facts as $fact ) {
			if ( ! $this->exact_object( $fact, array( 'key', 'label', 'value' ) ) || ! is_string( $fact['key'] ) || ! preg_match( '/^[a-z0-9]+(?:_[a-z0-9]+)*$/', $fact['key'] ) || strlen( $fact['key'] ) > 60 || isset( $keys[ $fact['key'] ] ) || ! $this->text( $fact['label'], 1, 80 ) || ! $this->text( $fact['value'], 1, 240 ) ) {
				return false;
			}
			$keys[ $fact['key'] ] = true;
		}
		return true;
	}

	private function pricing( $pricing, $vertical ) {
		$required = array( 'currency', 'price_scope', 'quantity_rule', 'unit_amount_minor', 'adult_amount_minor', 'child_amount_minor', 'infant_amount_minor', 'capacity_per_unit', 'tax_bps', 'fee_amount_minor', 'credit_amount_minor' );
		$scope_by_vertical = array(
			'flight'        => array( 'person_round_trip', 'whole_party_round_trip' ),
			'accommodation' => array( 'stay_total' ),
			'package'       => array( 'package_total' ),
			'transfer'      => array( 'transfer_total' ),
			'activity'      => array( 'activity_total' ),
			'dining'        => array( 'meal_total' ),
			'insurance'     => array( 'policy_period' ),
			'connectivity'  => array( 'connectivity_period' ),
			'equipment'     => array( 'item_total' ),
		);
		$rules = array( 'traveler_tiered', 'traveler_day_tiered', 'room_night', 'room_stay', 'party_capacity', 'booking', 'traveler', 'item_party' );
		if ( ! $this->exact_object( $pricing, $required ) || '' === Tra_Vel_Commerce_Money::currency( $pricing['currency'] ) || ! in_array( $pricing['price_scope'], $scope_by_vertical[ $vertical ], true ) || ! in_array( $pricing['quantity_rule'], $rules, true ) ) {
			return false;
		}
		foreach ( array( 'unit_amount_minor', 'adult_amount_minor', 'child_amount_minor', 'infant_amount_minor', 'fee_amount_minor', 'credit_amount_minor' ) as $field ) {
			if ( is_wp_error( Tra_Vel_Commerce_Money::amount( $pricing[ $field ] ) ) || $pricing[ $field ] > 1000000000000 ) {
				return false;
			}
		}
		if ( ! is_int( $pricing['capacity_per_unit'] ) || $pricing['capacity_per_unit'] < 0 || $pricing['capacity_per_unit'] > 100 || ( 'party_capacity' === $pricing['quantity_rule'] && $pricing['capacity_per_unit'] < 1 ) || ! is_int( $pricing['tax_bps'] ) || $pricing['tax_bps'] < 0 || $pricing['tax_bps'] > 10000 ) {
			return false;
		}
		if ( in_array( $pricing['quantity_rule'], array( 'traveler_tiered', 'traveler_day_tiered' ), true ) && $pricing['adult_amount_minor'] < 1 ) {
			return false;
		}
		if ( ! in_array( $pricing['quantity_rule'], array( 'traveler_tiered', 'traveler_day_tiered' ), true ) && $pricing['unit_amount_minor'] < 1 ) {
			return false;
		}
		if ( in_array( $pricing['quantity_rule'], array( 'traveler_tiered', 'traveler_day_tiered' ), true ) && ( 0 !== $pricing['unit_amount_minor'] || 0 !== $pricing['capacity_per_unit'] ) ) {
			return false;
		}
		if ( ! in_array( $pricing['quantity_rule'], array( 'traveler_tiered', 'traveler_day_tiered' ), true ) && ( 0 !== $pricing['adult_amount_minor'] || 0 !== $pricing['child_amount_minor'] || 0 !== $pricing['infant_amount_minor'] ) ) {
			return false;
		}
		if ( 'party_capacity' !== $pricing['quantity_rule'] && 0 !== $pricing['capacity_per_unit'] ) {
			return false;
		}
		return true;
	}

	private function inventory( $inventory ) {
		return $this->exact_object( $inventory, array( 'quantity_available', 'limited_threshold', 'hold_minutes' ) )
			&& is_int( $inventory['quantity_available'] ) && $inventory['quantity_available'] > 0 && $inventory['quantity_available'] <= 1000000
			&& is_int( $inventory['limited_threshold'] ) && $inventory['limited_threshold'] >= 0 && $inventory['limited_threshold'] <= $inventory['quantity_available']
			&& is_int( $inventory['hold_minutes'] ) && $inventory['hold_minutes'] >= 1 && $inventory['hold_minutes'] <= 1440;
	}

	private function terms( $terms ) {
		if ( ! $this->exact_object( $terms, array( 'cancellation', 'changes', 'refund', 'inclusions', 'exclusions', 'requires_revalidation' ) ) || ! $this->text( $terms['cancellation'], 1, 280 ) || ! $this->text( $terms['changes'], 1, 280 ) || ! $this->text( $terms['refund'], 1, 280 ) || true !== $terms['requires_revalidation'] || ! $this->string_list( $terms['inclusions'], 1, 12, 100 ) || ! $this->string_list( $terms['exclusions'], 1, 12, 100 ) ) {
			return false;
		}
		return strlen( $terms['cancellation'] . ' Refund: ' . $terms['refund'] ) <= 500 && strlen( 'Includes: ' . implode( ', ', $terms['inclusions'] ) . '. Excludes: ' . implode( ', ', $terms['exclusions'] ) . '.' ) <= 500;
	}

	private function geometry( $geometry ) {
		if ( ! $this->exact_object( $geometry, array( 'places', 'segments' ) ) || ! $this->is_list( $geometry['places'] ) || ! $geometry['places'] || count( $geometry['places'] ) > 64 || ! $this->is_list( $geometry['segments'] ) || count( $geometry['segments'] ) > 64 ) {
			return false;
		}
		$places = array();
		$sequences = array();
		foreach ( $geometry['places'] as $place ) {
			if ( ! $this->exact_object( $place, array( 'place_key', 'role', 'label', 'code', 'latitude', 'longitude', 'sequence' ) ) || ! is_string( $place['place_key'] ) || ! preg_match( '/^[a-z0-9][a-z0-9_]{2,50}$/', $place['place_key'] ) || isset( $places[ $place['place_key'] ] ) || ! in_array( $place['role'], array( 'origin', 'connection', 'destination', 'property', 'meeting_point', 'venue', 'pickup', 'dropoff' ), true ) || ! $this->text( $place['label'], 1, 160 ) || ( null !== $place['code'] && ( ! is_string( $place['code'] ) || ! preg_match( '/^[A-Z0-9-]{2,12}$/', $place['code'] ) ) ) || ! is_float( $place['latitude'] ) && ! is_int( $place['latitude'] ) || ! is_float( $place['longitude'] ) && ! is_int( $place['longitude'] ) || $place['latitude'] < -90 || $place['latitude'] > 90 || $place['longitude'] < -180 || $place['longitude'] > 180 || ! is_int( $place['sequence'] ) || $place['sequence'] < 1 || $place['sequence'] > 64 || isset( $sequences[ $place['sequence'] ] ) ) {
				return false;
			}
			$places[ $place['place_key'] ] = true;
			$sequences[ $place['sequence'] ] = true;
		}
		$segments = array();
		$segment_sequences = array();
		foreach ( $geometry['segments'] as $segment ) {
			if ( ! $this->exact_object( $segment, array( 'segment_key', 'sequence', 'mode', 'from_place_key', 'to_place_key', 'duration_minutes' ) ) || ! is_string( $segment['segment_key'] ) || ! preg_match( '/^[a-z0-9][a-z0-9_]{2,50}$/', $segment['segment_key'] ) || isset( $segments[ $segment['segment_key'] ] ) || ! is_int( $segment['sequence'] ) || $segment['sequence'] < 1 || $segment['sequence'] > 64 || isset( $segment_sequences[ $segment['sequence'] ] ) || ! in_array( $segment['mode'], array( 'flight', 'rail', 'road', 'ferry', 'walk', 'onsite', 'digital' ), true ) || ! isset( $places[ $segment['from_place_key'] ] ) || ! isset( $places[ $segment['to_place_key'] ] ) || ( null !== $segment['duration_minutes'] && ( ! is_int( $segment['duration_minutes'] ) || $segment['duration_minutes'] < 0 || $segment['duration_minutes'] > 100000 ) ) ) {
				return false;
			}
			$segments[ $segment['segment_key'] ] = true;
			$segment_sequences[ $segment['sequence'] ] = true;
		}
		return true;
	}

	private function attributes( $attributes ) {
		if ( ! $this->exact_object( $attributes, array( 'duration_minutes', 'flexibility_score_bps', 'location_score_bps', 'friction_score_bps', 'family_score_bps', 'vibes', 'direct', 'stops', 'accessible' ) ) || ! is_int( $attributes['duration_minutes'] ) || $attributes['duration_minutes'] < 0 || $attributes['duration_minutes'] > 100000 || ! is_bool( $attributes['direct'] ) || ! is_int( $attributes['stops'] ) || $attributes['stops'] < 0 || $attributes['stops'] > 3 || ( $attributes['direct'] && 0 !== $attributes['stops'] ) || ! is_bool( $attributes['accessible'] ) || ! $this->string_list( $attributes['vibes'], 1, 8, 20 ) ) {
			return false;
		}
		foreach ( array( 'flexibility_score_bps', 'location_score_bps', 'friction_score_bps', 'family_score_bps' ) as $field ) {
			if ( ! is_int( $attributes[ $field ] ) || $attributes[ $field ] < 0 || $attributes[ $field ] > 10000 ) {
				return false;
			}
		}
		$allowed_vibes = array( 'city', 'beach', 'nature', 'romantic', 'family', 'adventure', 'food', 'wellness', 'nightlife', 'surprise' );
		return ! array_diff( $attributes['vibes'], $allowed_vibes ) && count( $attributes['vibes'] ) === count( array_unique( $attributes['vibes'] ) );
	}

	private function commission( $commission, $currency ) {
		$valid = $this->exact_object( $commission, array( 'basis', 'rate_bps', 'fixed_amount_minor', 'currency' ) )
			&& in_array( $commission['basis'], array( 'gross_total', 'net_subtotal', 'fixed_order' ), true )
			&& is_int( $commission['rate_bps'] ) && $commission['rate_bps'] >= 0 && $commission['rate_bps'] <= 10000
			&& ! is_wp_error( Tra_Vel_Commerce_Money::amount( $commission['fixed_amount_minor'] ) )
			&& $commission['fixed_amount_minor'] <= 1000000000000
			&& $currency === $commission['currency'];
		if ( ! $valid ) {
			return false;
		}
		return 'fixed_order' === $commission['basis']
			? 0 === $commission['rate_bps'] && $commission['fixed_amount_minor'] > 0
			: $commission['rate_bps'] > 0 && 0 === $commission['fixed_amount_minor'];
	}

	private function capabilities( $capabilities ) {
		$allowed = array( 'revalidate', 'reserve', 'confirm', 'change', 'cancel', 'refund', 'affiliate_handoff' );
		return $this->is_list( $capabilities ) && ! empty( $capabilities ) && ! array_diff( $capabilities, $allowed ) && count( $capabilities ) === count( array_unique( $capabilities ) );
	}

	private function normalize_query( $query ) {
		$required = array( 'adults', 'children', 'infants', 'rooms', 'party_size', 'nights', 'trip_days', 'currency', 'budget_limit_minor', 'origin_codes', 'destination_codes', 'departure_date', 'return_date', 'direct_only', 'max_stops', 'accessibility_requested', 'vibes' );
		if ( ! $this->exact_object( $query, $required ) ) {
			return $this->error( 'catalog_query_invalid', 'The catalog query must use the exact supported fields.', 400 );
		}
		foreach ( array( 'adults', 'children', 'infants', 'rooms', 'party_size', 'nights', 'trip_days', 'max_stops' ) as $field ) {
			if ( ! is_int( $query[ $field ] ) ) {
				return $this->error( 'catalog_query_invalid', 'Catalog query quantities must be integers.', 400 );
			}
		}
		if ( $query['adults'] < 1 || $query['children'] < 0 || $query['infants'] < 0 || $query['rooms'] < 1 || $query['party_size'] !== $query['adults'] + $query['children'] + $query['infants'] || $query['party_size'] > 50 || $query['nights'] < 1 || $query['nights'] > 365 || $query['trip_days'] !== $query['nights'] + 1 || $query['max_stops'] < 0 || $query['max_stops'] > 3 || '' === Tra_Vel_Commerce_Money::currency( $query['currency'] ) || ( null !== $query['budget_limit_minor'] && ( is_wp_error( Tra_Vel_Commerce_Money::amount( $query['budget_limit_minor'] ) ) || $query['budget_limit_minor'] > 1000000000000 ) ) || ! $this->code_list( $query['origin_codes'], true ) || ! $this->code_list( $query['destination_codes'], true ) || ( null !== $query['departure_date'] && ! $this->date( $query['departure_date'] ) ) || ( null !== $query['return_date'] && ! $this->date( $query['return_date'] ) ) || ! is_bool( $query['direct_only'] ) || ! is_bool( $query['accessibility_requested'] ) || ! $this->string_list( $query['vibes'], 0, 8, 20 ) ) {
			return $this->error( 'catalog_query_invalid', 'The catalog query contains invalid values.', 400 );
		}
		return $query;
	}

	private function matches_query( $product, $query ) {
		if ( $query['origin_codes'] && $product['origin_codes'] && ! array_intersect( $query['origin_codes'], $product['origin_codes'] ) ) {
			return false;
		}
		if ( $query['destination_codes'] && ! array_intersect( $query['destination_codes'], $product['destination_codes'] ) ) {
			return false;
		}
		if ( null !== $query['departure_date'] && ( $query['departure_date'] < $product['service_window']['available_from'] || $query['departure_date'] > $product['service_window']['available_until'] ) ) {
			return false;
		}
		if ( null !== $query['return_date'] && ( $query['return_date'] < $product['service_window']['available_from'] || $query['return_date'] > $product['service_window']['available_until'] ) ) {
			return false;
		}
		if ( $query['direct_only'] && ! $product['attributes']['direct'] ) {
			return false;
		}
		if ( $product['attributes']['stops'] > $query['max_stops'] || ( $query['accessibility_requested'] && ! $product['attributes']['accessible'] ) ) {
			return false;
		}
		return true;
	}

	private function price_product( $product, $query ) {
		$pricing  = $product['pricing'];
		$currency = $pricing['currency'];
		$base     = $this->base_amount( $pricing, $query );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$tax_basis = $this->safe_multiply( $base, $pricing['tax_bps'] );
		if ( is_wp_error( $tax_basis ) ) {
			return $tax_basis;
		}
		$tax = intdiv( $tax_basis, 10000 );
		$debits = Tra_Vel_Commerce_Money::add( $base, $tax );
		$debits = is_wp_error( $debits ) ? $debits : Tra_Vel_Commerce_Money::add( $debits, $pricing['fee_amount_minor'] );
		if ( is_wp_error( $debits ) || $pricing['credit_amount_minor'] > $debits ) {
			return $this->error( 'catalog_price_invalid', 'A seeded product price cannot be totaled safely.', 500 );
		}
		$total = $debits - $pricing['credit_amount_minor'];
		foreach ( array( $base, $tax, $pricing['fee_amount_minor'], $pricing['credit_amount_minor'], $total ) as $amount ) {
			if ( $amount > 1000000000000 ) {
				return $this->error( 'catalog_price_invalid', 'A seeded product price exceeds the public offer limit.', 500 );
			}
		}
		$basis = array(
			'product' => $product['private_product_ref'],
			'party'   => array( $query['adults'], $query['children'], $query['infants'], $query['rooms'] ),
			'nights'  => $query['nights'],
			'days'    => $query['trip_days'],
		);
		$lines = array(
			$this->line_item( 'base_price', 'Base price', 'base', 'debit', $base, $currency, $basis ),
		);
		if ( $tax > 0 ) {
			$lines[] = $this->line_item( 'taxes', 'Taxes', 'tax', 'debit', $tax, $currency, $basis );
		}
		if ( $pricing['fee_amount_minor'] > 0 ) {
			$lines[] = $this->line_item( 'service_fee', 'Service fee', 'fee', 'debit', $pricing['fee_amount_minor'], $currency, $basis );
		}
		if ( $pricing['credit_amount_minor'] > 0 ) {
			$lines[] = $this->line_item( 'sandbox_credit', 'Sandbox promotion', 'credit', 'credit', $pricing['credit_amount_minor'], $currency, $basis );
		}
		return array(
			'currency'              => $currency,
			'minor_unit'            => Tra_Vel_Commerce_Money::exponent( $currency ),
			'price_scope'           => $pricing['price_scope'],
			'line_items'            => $lines,
			'subtotal_amount_minor' => $base,
			'tax_amount_minor'      => $tax,
			'fee_amount_minor'      => $pricing['fee_amount_minor'],
			'credit_amount_minor'   => $pricing['credit_amount_minor'],
			'total_amount_minor'    => $total,
			'tax_state'             => 'included',
			'fee_state'             => 'included',
		);
	}

	private function base_amount( $pricing, $query ) {
		switch ( $pricing['quantity_rule'] ) {
			case 'traveler_tiered':
				return $this->tiered_amount( $pricing, $query, 1 );
			case 'traveler_day_tiered':
				return $this->tiered_amount( $pricing, $query, $query['trip_days'] );
			case 'room_night':
				return $this->safe_multiply( $pricing['unit_amount_minor'], $query['rooms'] * $query['nights'] );
			case 'room_stay':
				return $this->safe_multiply( $pricing['unit_amount_minor'], $query['rooms'] );
			case 'party_capacity':
				$units = intdiv( $query['party_size'] + $pricing['capacity_per_unit'] - 1, $pricing['capacity_per_unit'] );
				return $this->safe_multiply( $pricing['unit_amount_minor'], $units );
			case 'traveler':
			case 'item_party':
				return $this->safe_multiply( $pricing['unit_amount_minor'], $query['party_size'] );
			case 'booking':
				return $pricing['unit_amount_minor'];
		}
		return $this->error( 'catalog_quantity_rule_invalid', 'The seeded quantity rule is unsupported.', 500 );
	}

	private function tiered_amount( $pricing, $query, $multiplier ) {
		$adult = $this->safe_multiply( $pricing['adult_amount_minor'], $query['adults'] );
		$child = $this->safe_multiply( $pricing['child_amount_minor'], $query['children'] );
		$infant = $this->safe_multiply( $pricing['infant_amount_minor'], $query['infants'] );
		if ( is_wp_error( $adult ) || is_wp_error( $child ) || is_wp_error( $infant ) ) {
			return $this->error( 'catalog_price_overflow', 'A seeded tiered price exceeds the supported integer range.', 500 );
		}
		$total = Tra_Vel_Commerce_Money::add( $adult, $child );
		$total = is_wp_error( $total ) ? $total : Tra_Vel_Commerce_Money::add( $total, $infant );
		return is_wp_error( $total ) ? $total : $this->safe_multiply( $total, $multiplier );
	}

	private function inventory_quantity( $rule, $capacity, $query ) {
		switch ( $rule ) {
			case 'room_night':
			case 'room_stay':
				return $query['rooms'];
			case 'party_capacity':
				return intdiv( $query['party_size'] + $capacity - 1, $capacity );
			case 'booking':
				return 1;
			default:
				return $query['party_size'];
		}
	}

	private function commission_projection( $commission, $ledger ) {
		$basis = 'gross_total' === $commission['basis'] ? $ledger['total_amount_minor'] : $ledger['subtotal_amount_minor'];
		if ( 'fixed_order' === $commission['basis'] ) {
			$amount = $commission['fixed_amount_minor'];
		} else {
			$commission_basis = $this->safe_multiply( $basis, $commission['rate_bps'] );
			if ( is_wp_error( $commission_basis ) ) {
				return $commission_basis;
			}
			$amount = intdiv( $commission_basis, 10000 );
		}
		if ( $amount > 1000000000000 ) {
			return $this->error( 'catalog_commission_invalid', 'A seeded commission exceeds the supported integer range.', 500 );
		}
		return array(
			'basis'        => $commission['basis'],
			'currency'     => $commission['currency'],
			'amount_minor' => $amount,
		);
	}

	private function line_item( $code, $label, $kind, $direction, $amount, $currency, $basis ) {
		return array(
			'code'            => $code,
			'label'           => $label,
			'kind'            => $kind,
			'direction'       => $direction,
			'amount_minor'    => $amount,
			'evidence_digest' => Tra_Vel_Commerce_Policy::canonical_digest( array( 'currency' => $currency, 'line' => $code, 'amount' => $amount, 'basis' => $basis ) ),
		);
	}

	private function safe_multiply( $left, $right ) {
		if ( ! is_int( $left ) || ! is_int( $right ) || $left < 0 || $right < 0 || ( $right > 0 && $left > intdiv( PHP_INT_MAX, $right ) ) ) {
			return $this->error( 'catalog_price_overflow', 'A seeded price exceeds the supported integer range.', 500 );
		}
		return $left * $right;
	}

	private function sandbox_truth( $truth ) {
		return $this->exact_object( $truth, array( 'simulated_inventory', 'real_supplier_request', 'real_inventory_hold', 'real_charge', 'real_booking', 'real_policy_issuance', 'real_settlement' ) )
			&& true === $truth['simulated_inventory'] && false === $truth['real_supplier_request'] && false === $truth['real_inventory_hold'] && false === $truth['real_charge'] && false === $truth['real_booking'] && false === $truth['real_policy_issuance'] && false === $truth['real_settlement'];
	}

	private function data_boundary( $boundary ) {
		return $this->exact_object( $boundary, array( 'raw_supplier_reference_exposed', 'raw_payment_data_exposed', 'medical_data_exposed' ) )
			&& false === $boundary['raw_supplier_reference_exposed'] && false === $boundary['raw_payment_data_exposed'] && false === $boundary['medical_data_exposed'];
	}

	private function date( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
			return false;
		}
		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
	}

	private function code_list( $values, $allow_empty ) {
		if ( ! $this->is_list( $values ) || ( ! $allow_empty && ! $values ) || count( $values ) > 16 || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) || ! preg_match( '/^[A-Z0-9-]{2,16}$/', $value ) ) {
				return false;
			}
		}
		return true;
	}

	private function string_list( $values, $minimum, $maximum, $max_length ) {
		if ( ! $this->is_list( $values ) || count( $values ) < $minimum || count( $values ) > $maximum || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! $this->text( $value, 1, $max_length ) ) {
				return false;
			}
		}
		return true;
	}

	private function text( $value, $minimum, $maximum ) {
		return is_string( $value ) && strlen( $value ) >= $minimum && strlen( $value ) <= $maximum && 0 === preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value ) && $value === wp_strip_all_tags( $value );
	}

	private function exact_object( $value, $keys ) {
		return is_array( $value ) && ! $this->is_list( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private function is_list( $value ) {
		return is_array( $value ) && ( empty( $value ) || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
	}

	private function error( $suffix, $message, $status ) {
		return new WP_Error( 'tra_vel_commerce_' . $suffix, $message, array( 'status' => (int) $status ) );
	}
}
