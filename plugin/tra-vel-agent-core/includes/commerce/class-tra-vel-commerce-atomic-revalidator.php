<?php
/**
 * Atomic package revalidation against a new server-owned search observation.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Atomic_Revalidator {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_COMPONENTS   = 32;
	const MAX_OFFERS       = 500;
	const MAX_REPLACEMENTS = 5;

	/** @var Tra_Vel_Commerce_Search_Engine|null */
	private $search_engine;

	/** @var Tra_Vel_Commerce_Package_Composer|null */
	private $package_composer;

	/** @var WP_Error|null */
	private $error;

	/**
	 * @param Tra_Vel_Commerce_Search_Engine    $search_engine    Validated server-side search engine.
	 * @param Tra_Vel_Commerce_Package_Composer $package_composer Validated atomic package composer.
	 */
	public function __construct( $search_engine, $package_composer ) {
		if ( ! $search_engine instanceof Tra_Vel_Commerce_Search_Engine || ! $package_composer instanceof Tra_Vel_Commerce_Package_Composer ) {
			$this->error = $this->error( 'dependencies_invalid', 'Validated commerce search and package services are required.', 500 );
			return;
		}
		$this->search_engine    = $search_engine;
		$this->package_composer = $package_composer;
	}

	/**
	 * Revalidate every selected component as one compare-and-swap operation.
	 *
	 * The prior package and public result are server-owned snapshots. The context
	 * supplies the persisted version and digest expected by the caller; storage
	 * must commit the returned version with the same compare-and-swap boundary.
	 *
	 * @param array $prior_package       Latest immutable package snapshot.
	 * @param array $prior_search_result Exact public search snapshot used to compose it.
	 * @param array $search_request      Original closed search request.
	 * @param array $context             Exact owner/version/digest/time boundary.
	 * @return array|WP_Error
	 */
	public function revalidate( $prior_package, $prior_search_result, $search_request, $context ) {
		if ( is_wp_error( $this->error ) ) {
			return $this->error;
		}
		$context = $this->context( $context );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$prior_package = $this->package( $prior_package, $context );
		if ( is_wp_error( $prior_package ) ) {
			return $prior_package;
		}
		$prior = $this->prior_observation( $prior_search_result, $prior_package, $search_request, $context );
		if ( is_wp_error( $prior ) ) {
			return $prior;
		}

		$search_context = array(
			'owner_scope_digest' => $context['owner_scope_digest'],
			'now'                => $context['now'],
		);
		$current_result = $this->search_engine->search( $search_request, $search_context );
		if ( is_wp_error( $current_result ) ) {
			return $this->error( 'search_failed', 'The package could not be checked against a fresh inventory observation.', 409 );
		}
		$current = $this->current_observation( $current_result, $search_request, $context );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		if ( ! hash_equals( $prior['session']['request_digest'], $current['session']['request_digest'] ) || $prior['session']['request_ref'] !== $current['session']['request_ref'] ) {
			return $this->error( 'request_conflict', 'The original search request does not match the package source observation.', 409 );
		}
		if ( $current['session']['session_ref'] === $prior['session']['session_ref'] ) {
			return $this->error( 'observation_replayed', 'Revalidation requires a new inventory observation.', 409 );
		}

		$selection       = array( 'title' => $prior_package['title'], 'components' => array() );
		$expected_routing = array();
		$material_changed = false;
		foreach ( $prior['components'] as $item ) {
			$lineage = $item['component']['provider_reference_digest'];
			if ( ! isset( $current['lineages'][ $lineage ] ) ) {
				return $this->lineage_error( $item['component'], $current['offers'] );
			}
			if ( count( $current['lineages'][ $lineage ] ) !== 1 ) {
				return $this->error( 'lineage_ambiguous', 'A selected inventory lineage did not resolve uniquely.', 409 );
			}
			$new_offer = $current['lineages'][ $lineage ][0];
			if ( $new_offer['vertical'] !== $item['component']['vertical'] || $new_offer['provider_id'] !== $item['component']['provider_id'] ) {
				return $this->error( 'lineage_vertical_changed', 'A selected inventory lineage crossed a commerce vertical.', 409 );
			}
			$old_material = $this->material_offer( $item['offer'] );
			$new_material = $this->material_offer( $new_offer );
			if ( is_wp_error( $old_material ) || is_wp_error( $new_material ) ) {
				return is_wp_error( $old_material ) ? $old_material : $new_material;
			}
			if ( ! hash_equals( Tra_Vel_Commerce_Policy::canonical_digest( $old_material ), Tra_Vel_Commerce_Policy::canonical_digest( $new_material ) ) ) {
				$material_changed = true;
			}
			$selection['components'][] = array(
				'offer_ref'     => $new_offer['offer_ref'],
				'offer_version' => $new_offer['version'],
				'role'          => $item['component']['role'],
				'required'      => $item['component']['required'],
				'day'           => $item['day'],
			);
			$expected_routing[] = array(
				'provider_id'               => $new_offer['provider_id'],
				'provider_reference_digest' => $new_offer['provider_reference_digest'],
				'vertical'                  => $new_offer['vertical'],
			);
		}

		$composed = $this->package_composer->compose( $current['session'], $current['offers'], $selection, $search_context );
		if ( is_wp_error( $composed ) ) {
			return $this->error( 'composition_failed', 'Fresh component snapshots could not be composed atomically.', 409 );
		}
		if ( ! isset( $composed['components'] ) || ! $this->is_list( $composed['components'] ) || count( $composed['components'] ) !== count( $expected_routing ) ) {
			return $this->error( 'composition_routing_invalid', 'Fresh package components lost their provider routing boundary.', 500 );
		}
		foreach ( $composed['components'] as $index => $component ) {
			$route = $expected_routing[ $index ];
			if ( ! isset( $component['provider_id'], $component['provider_reference_digest'], $component['vertical'] ) || ! $this->digest( $component['provider_reference_digest'] ) || $component['provider_id'] !== $route['provider_id'] || $component['vertical'] !== $route['vertical'] || ! hash_equals( $route['provider_reference_digest'], $component['provider_reference_digest'] ) ) {
				return $this->error( 'composition_routing_invalid', 'Fresh package components lost their provider routing boundary.', 500 );
			}
		}
		if ( $prior_package['version'] >= 2147483647 ) {
			return $this->error( 'version_exhausted', 'The package version boundary has been exhausted.', 409 );
		}

		$checked_at                  = gmdate( 'Y-m-d\TH:i:s\Z', $context['now'] );
		$composed['package_ref']      = $prior_package['package_ref'];
		$composed['version']          = $prior_package['version'] + 1;
		$composed['status']           = $material_changed ? 'revalidation_required' : 'composed';
		$composed['created_at']       = $prior_package['created_at'];
		$composed['updated_at']       = $checked_at;
		$composed['revalidation']     = array(
			'mode'                    => 'atomic',
			'all_components_required' => true,
			'state'                   => $material_changed ? 'changed' : 'fresh',
			'checked_at'              => $checked_at,
		);
		$composed['package_digest'] = '';
		$digest_basis = $composed;
		unset( $digest_basis['package_digest'] );
		$composed['package_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $digest_basis );

		$encoded = wp_json_encode( $composed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) || false !== strpos( $encoded, 'private_product_ref' ) || false !== strpos( $encoded, '"commission"' ) || 1 === preg_match( '/\bpx_[a-z0-9_]{8,90}\b/', $encoded ) ) {
			return $this->error( 'projection_failed', 'A private commerce reference reached the revalidated package projection.', 500 );
		}
		return $composed;
	}

	private function context( $context ) {
		$keys = array( 'owner_scope_digest', 'expected_package_version', 'expected_package_digest', 'now' );
		if ( ! $this->exact_object( $context, $keys ) || ! $this->digest( $context['owner_scope_digest'] ) || ! is_int( $context['expected_package_version'] ) || $context['expected_package_version'] < 1 || ! $this->digest( $context['expected_package_digest'] ) || ! is_int( $context['now'] ) || $context['now'] < 1 ) {
			return $this->error( 'context_invalid', 'An exact owner, package revision, and UTC clock are required.', 400 );
		}
		return $context;
	}

	private function package( $package, $context ) {
		$keys = array( 'contract_version', 'environment', 'package_ref', 'version', 'owner_scope_digest', 'search_session_ref', 'status', 'title', 'components', 'pricing', 'comparison', 'revalidation', 'itinerary', 'package_digest', 'created_at', 'updated_at', 'expires_at', 'sandbox_truth', 'data_boundary' );
		if ( ! $this->exact_object( $package, $keys ) || self::CONTRACT_VERSION !== $package['contract_version'] || 'sandbox' !== $package['environment'] || ! $this->opaque_ref( $package['package_ref'], 'package' ) || ! $this->opaque_ref( $package['search_session_ref'], 'session' ) || ! is_int( $package['version'] ) || $package['version'] < 1 || ! $this->digest( $package['owner_scope_digest'] ) || ! $this->digest( $package['package_digest'] ) || ! in_array( $package['status'], array( 'composed', 'revalidation_required', 'expired' ), true ) || ! is_string( $package['title'] ) || ! $this->utc( $package['created_at'] ) || ! $this->utc( $package['updated_at'] ) || ! $this->utc( $package['expires_at'] ) || $package['sandbox_truth'] !== $this->sandbox_truth() || $package['data_boundary'] !== $this->data_boundary() ) {
			return $this->error( 'package_invalid', 'The prior package is not a valid immutable sandbox snapshot.', 400 );
		}
		if ( $package['owner_scope_digest'] !== $context['owner_scope_digest'] ) {
			return $this->error( 'owner_conflict', 'The package belongs to another owner scope.', 403 );
		}
		if ( $package['version'] !== $context['expected_package_version'] || ! hash_equals( $package['package_digest'], $context['expected_package_digest'] ) ) {
			return $this->error( 'revision_conflict', 'The expected package revision is no longer current.', 409 );
		}
		$digest_basis = $package;
		unset( $digest_basis['package_digest'] );
		if ( ! hash_equals( $package['package_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $digest_basis ) ) ) {
			return $this->error( 'package_digest_invalid', 'The prior package failed its immutable digest boundary.', 409 );
		}
		$updated_epoch = strtotime( $package['updated_at'] );
		if ( false === $updated_epoch || $context['now'] <= $updated_epoch ) {
			return $this->error( 'observation_replayed', 'Revalidation requires a clock later than the persisted package revision.', 409 );
		}
		if ( ! $this->is_list( $package['components'] ) || ! $package['components'] || count( $package['components'] ) > self::MAX_COMPONENTS || ! $this->is_list( $package['itinerary'] ) || count( $package['itinerary'] ) !== count( $package['components'] ) ) {
			return $this->error( 'package_components_invalid', 'The prior package component set is incomplete.', 400 );
		}
		return $package;
	}

	private function prior_observation( $result, $package, $request, $context ) {
		$observation = $this->observation( $result, $request, $context );
		if ( is_wp_error( $observation ) ) {
			return $observation;
		}
		if ( $observation['session']['session_ref'] !== $package['search_session_ref'] ) {
			return $this->error( 'prior_session_conflict', 'The prior search snapshot is not the package source observation.', 409 );
		}
		$itineraries = array();
		foreach ( $package['itinerary'] as $item ) {
			if ( ! $this->exact_object( $item, array( 'sequence', 'day', 'component_ref', 'place_refs', 'label' ) ) || ! is_int( $item['sequence'] ) || ! is_int( $item['day'] ) || $item['day'] < 1 || $item['day'] > 365 || ! $this->opaque_ref( $item['component_ref'], 'component' ) || isset( $itineraries[ $item['component_ref'] ] ) ) {
				return $this->error( 'package_itinerary_invalid', 'The prior package itinerary cannot reconstruct component days.', 400 );
			}
			$itineraries[ $item['component_ref'] ] = $item;
		}
		$components = array();
		$seen       = array();
		foreach ( $package['components'] as $index => $component ) {
			$sequence = $index + 1;
			$keys = array( 'component_ref', 'role', 'vertical', 'provider_id', 'provider_reference_digest', 'offer_ref', 'offer_version', 'offer_digest', 'required', 'sequence' );
			if ( ! $this->exact_object( $component, $keys ) || ! $this->opaque_ref( $component['component_ref'], 'component' ) || ! $this->opaque_ref( $component['offer_ref'], 'offer' ) || ! is_string( $component['provider_id'] ) || $component['provider_id'] !== sanitize_key( $component['provider_id'] ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $component['provider_id'] ) || ! $this->digest( $component['provider_reference_digest'] ) || ! is_int( $component['offer_version'] ) || $component['offer_version'] < 1 || ! $this->digest( $component['offer_digest'] ) || ! is_bool( $component['required'] ) || $component['sequence'] !== $sequence || isset( $seen[ $component['component_ref'] ] ) || ! isset( $itineraries[ $component['component_ref'] ] ) || $itineraries[ $component['component_ref'] ]['sequence'] !== $sequence ) {
				return $this->error( 'package_components_invalid', 'The prior package component binding is invalid.', 400 );
			}
			if ( ! isset( $observation['offer_refs'][ $component['offer_ref'] ] ) ) {
				return $this->error( 'prior_offer_missing', 'A package component is absent from its source search snapshot.', 409 );
			}
			$offer = $observation['offer_refs'][ $component['offer_ref'] ];
			if ( $offer['version'] !== $component['offer_version'] || $offer['vertical'] !== $component['vertical'] || $offer['provider_id'] !== $component['provider_id'] || ! hash_equals( $offer['provider_reference_digest'], $component['provider_reference_digest'] ) || ! hash_equals( $component['offer_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $offer ) ) ) {
				return $this->error( 'prior_offer_digest_invalid', 'A package component no longer matches its stored offer snapshot.', 409 );
			}
			$components[] = array(
				'component' => $component,
				'offer'     => $offer,
				'day'       => $itineraries[ $component['component_ref'] ]['day'],
			);
			$seen[ $component['component_ref'] ] = true;
		}
		$observation['components'] = $components;
		return $observation;
	}

	private function current_observation( $result, $request, $context ) {
		return $this->observation( $result, $request, $context );
	}

	private function observation( $result, $request, $context ) {
		$keys = array( 'contract_version', 'environment', 'catalog_digest', 'provider_network_digest', 'session', 'groups', 'sandbox_truth', 'data_boundary' );
		if ( ! $this->exact_object( $result, $keys ) || self::CONTRACT_VERSION !== $result['contract_version'] || 'sandbox' !== $result['environment'] || ! $this->digest( $result['catalog_digest'] ) || ! $this->digest( $result['provider_network_digest'] ) || ! is_array( $result['session'] ) || ! $this->is_list( $result['groups'] ) || $result['sandbox_truth'] !== $this->sandbox_truth() || $result['data_boundary'] !== $this->data_boundary() ) {
			return $this->error( 'observation_invalid', 'A closed public search observation is required.', 400 );
		}
		$session_keys = array( 'contract_version', 'environment', 'session_ref', 'version', 'owner_scope_digest', 'request_ref', 'request_digest', 'verticals', 'status', 'ranking_version', 'selection_seed_digest', 'catalog_digest', 'provider_network_digest', 'provider_runs', 'ranked_offers', 'counts', 'created_at', 'updated_at', 'expires_at', 'sandbox_truth', 'data_boundary' );
		$session = $result['session'];
		if ( ! $this->exact_object( $session, $session_keys ) || self::CONTRACT_VERSION !== $session['contract_version'] || 'sandbox' !== $session['environment'] || ! $this->opaque_ref( $session['session_ref'], 'session' ) || ! $this->digest( $session['request_digest'] ) || ! $this->digest( $session['owner_scope_digest'] ) || $session['owner_scope_digest'] !== $context['owner_scope_digest'] || $session['catalog_digest'] !== $result['catalog_digest'] || $session['provider_network_digest'] !== $result['provider_network_digest'] || $session['sandbox_truth'] !== $this->sandbox_truth() || $session['data_boundary'] !== $this->data_boundary() ) {
			return $this->error( 'observation_session_invalid', 'The search observation session is not owner-bound and closed.', 403 );
		}
		if ( ! is_array( $request ) || ! isset( $request['request_digest'] ) || ! $this->digest( $request['request_digest'] ) ) {
			return $this->error( 'request_conflict', 'The original search request is not digest-bound.', 409 );
		}
		$offers     = array();
		$offer_refs = array();
		$lineages   = array();
		foreach ( $result['groups'] as $group ) {
			if ( ! $this->exact_object( $group, array( 'vertical', 'currency', 'price_scope', 'offers' ) ) || ! is_string( $group['vertical'] ) || ! is_string( $group['currency'] ) || ! is_string( $group['price_scope'] ) || ! $this->is_list( $group['offers'] ) ) {
				return $this->error( 'observation_groups_invalid', 'The search observation contains an invalid offer group.', 400 );
			}
			foreach ( $group['offers'] as $offer ) {
				if ( count( $offers ) >= self::MAX_OFFERS || ! $this->public_offer( $offer, $session, $group ) || isset( $offer_refs[ $offer['offer_ref'] ] ) ) {
					return $this->error( 'observation_offers_invalid', 'The search observation contains an invalid or duplicate public offer.', 400 );
				}
				$offers[] = $offer;
				$offer_refs[ $offer['offer_ref'] ] = $offer;
				if ( ! isset( $lineages[ $offer['provider_reference_digest'] ] ) ) {
					$lineages[ $offer['provider_reference_digest'] ] = array();
				}
				$lineages[ $offer['provider_reference_digest'] ][] = $offer;
			}
		}
		$encoded = wp_json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) || false !== strpos( $encoded, 'private_product_ref' ) || false !== strpos( $encoded, '"commission"' ) || 1 === preg_match( '/\bpx_[a-z0-9_]{8,90}\b/', $encoded ) ) {
			return $this->error( 'observation_projection_invalid', 'The public search observation crossed a private-data boundary.', 500 );
		}
		return array( 'session' => $session, 'offers' => $offers, 'offer_refs' => $offer_refs, 'lineages' => $lineages );
	}

	private function public_offer( $offer, $session, $group ) {
		$keys = array( 'contract_version', 'environment', 'offer_ref', 'version', 'search_session_ref', 'provider_id', 'provider_reference_digest', 'vertical', 'status', 'product', 'geometry', 'pricing', 'availability', 'terms', 'capabilities', 'ranking', 'evidence', 'sandbox_truth', 'data_boundary' );
		return $this->exact_object( $offer, $keys )
			&& self::CONTRACT_VERSION === $offer['contract_version']
			&& 'sandbox' === $offer['environment']
			&& $offer['search_session_ref'] === $session['session_ref']
			&& $this->opaque_ref( $offer['offer_ref'], 'offer' )
			&& is_int( $offer['version'] ) && $offer['version'] >= 1
			&& $this->digest( $offer['provider_reference_digest'] )
			&& $offer['vertical'] === $group['vertical']
			&& isset( $offer['pricing']['currency'], $offer['pricing']['price_scope'] )
			&& $offer['pricing']['currency'] === $group['currency']
			&& $offer['pricing']['price_scope'] === $group['price_scope']
			&& in_array( $offer['status'], array( 'available', 'limited' ), true )
			&& $offer['sandbox_truth'] === $this->sandbox_truth()
			&& $offer['data_boundary'] === $this->data_boundary();
	}

	private function material_offer( $offer ) {
		if ( ! isset( $offer['provider_reference_digest'], $offer['vertical'], $offer['status'], $offer['product'], $offer['geometry'], $offer['pricing'], $offer['availability'], $offer['terms'], $offer['capabilities'] ) || ! $this->digest( $offer['provider_reference_digest'] ) || ! is_array( $offer['product'] ) || ! is_array( $offer['geometry'] ) || ! is_array( $offer['pricing'] ) || ! is_array( $offer['availability'] ) || ! is_array( $offer['terms'] ) || ! $this->is_list( $offer['capabilities'] ) ) {
			return $this->error( 'offer_material_invalid', 'An offer cannot be compared across observations safely.', 400 );
		}
		$product = $offer['product'];
		unset( $product['product_ref'] );
		$pricing = $offer['pricing'];
		if ( isset( $pricing['line_items'] ) && $this->is_list( $pricing['line_items'] ) ) {
			foreach ( $pricing['line_items'] as $index => $line ) {
				if ( is_array( $line ) ) {
					unset( $line['evidence_digest'] );
					$pricing['line_items'][ $index ] = $line;
				}
			}
		}
		$terms = $offer['terms'];
		unset( $terms['terms_digest'] );
		$availability = $offer['availability'];
		$checked_epoch = isset( $availability['checked_at'] ) ? strtotime( $availability['checked_at'] ) : false;
		$fresh_epoch   = isset( $availability['fresh_until'] ) ? strtotime( $availability['fresh_until'] ) : false;
		if ( false === $checked_epoch || false === $fresh_epoch || $fresh_epoch <= $checked_epoch ) {
			return $this->error( 'offer_availability_invalid', 'An offer freshness window is invalid.', 400 );
		}
		$normalized_geometry = $this->material_geometry( $offer['geometry'] );
		if ( is_wp_error( $normalized_geometry ) ) {
			return $normalized_geometry;
		}
		$capabilities = $offer['capabilities'];
		sort( $capabilities, SORT_STRING );
		return array(
			'provider_reference_digest' => $offer['provider_reference_digest'],
			'vertical'                  => $offer['vertical'],
			'status'                    => $offer['status'],
			'product'                   => $product,
			'geometry'                  => $normalized_geometry,
			'pricing'                   => $pricing,
			'availability'              => array(
				'state'              => isset( $availability['state'] ) ? $availability['state'] : null,
				'quantity_remaining' => isset( $availability['quantity_remaining'] ) ? $availability['quantity_remaining'] : null,
				'fresh_ttl_seconds'  => $fresh_epoch - $checked_epoch,
			),
			'terms'                     => $terms,
			'capabilities'              => $capabilities,
		);
	}

	private function material_geometry( $geometry ) {
		if ( ! $this->exact_object( $geometry, array( 'places', 'segments' ) ) || ! $this->is_list( $geometry['places'] ) || ! $this->is_list( $geometry['segments'] ) ) {
			return $this->error( 'offer_geometry_invalid', 'Offer route geometry cannot be compared safely.', 400 );
		}
		$places = array();
		$by_ref = array();
		foreach ( $geometry['places'] as $place ) {
			$keys = array( 'place_ref', 'role', 'label', 'code', 'latitude', 'longitude', 'sequence' );
			if ( ! $this->exact_object( $place, $keys ) || ! $this->opaque_ref( $place['place_ref'], 'place' ) || ! is_int( $place['sequence'] ) || isset( $by_ref[ $place['place_ref'] ] ) ) {
				return $this->error( 'offer_geometry_invalid', 'Offer route geometry cannot be compared safely.', 400 );
			}
			$normalized = array(
				'role'      => $place['role'],
				'label'     => $place['label'],
				'code'      => $place['code'],
				'latitude'  => $place['latitude'],
				'longitude' => $place['longitude'],
				'sequence'  => $place['sequence'],
			);
			$places[] = $normalized;
			$by_ref[ $place['place_ref'] ] = $normalized;
		}
		usort( $places, static function ( $left, $right ) { return $left['sequence'] <=> $right['sequence']; } );
		$segments = array();
		foreach ( $geometry['segments'] as $segment ) {
			$keys = array( 'segment_ref', 'sequence', 'mode', 'from_place_ref', 'to_place_ref', 'duration_minutes' );
			if ( ! $this->exact_object( $segment, $keys ) || ! $this->opaque_ref( $segment['segment_ref'], 'segment' ) || ! is_int( $segment['sequence'] ) || ! isset( $by_ref[ $segment['from_place_ref'] ], $by_ref[ $segment['to_place_ref'] ] ) ) {
				return $this->error( 'offer_geometry_invalid', 'Offer route geometry cannot be compared safely.', 400 );
			}
			$segments[] = array(
				'sequence'         => $segment['sequence'],
				'mode'             => $segment['mode'],
				'from_place'       => $by_ref[ $segment['from_place_ref'] ],
				'to_place'         => $by_ref[ $segment['to_place_ref'] ],
				'duration_minutes' => $segment['duration_minutes'],
			);
		}
		usort( $segments, static function ( $left, $right ) { return $left['sequence'] <=> $right['sequence']; } );
		return array( 'places' => $places, 'segments' => $segments );
	}

	private function lineage_error( $component, $current_offers ) {
		$replacements = array();
		foreach ( $current_offers as $offer ) {
			if ( $offer['vertical'] !== $component['vertical'] || ! $this->opaque_ref( $offer['offer_ref'], 'offer' ) ) {
				continue;
			}
			$replacements[] = $offer['offer_ref'];
			if ( count( $replacements ) >= self::MAX_REPLACEMENTS ) {
				break;
			}
		}
		return new WP_Error(
			'tra_vel_commerce_revalidation_lineage_unavailable',
			'A selected component is no longer available in the fresh observation.',
			array(
				'component'              => $component['component_ref'],
				'vertical'               => $component['vertical'],
				'reason'                 => $component['required'] ? 'required_lineage_unavailable' : 'optional_lineage_unavailable',
				'replacement_offer_refs' => array_values( array_unique( $replacements ) ),
			)
		);
	}

	private function utc( $value ) {
		return is_string( $value ) && null !== Tra_Vel_Commerce_Policy::utc_datetime( $value );
	}

	private function opaque_ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private function sandbox_truth() {
		return array(
			'simulated_inventory'  => true,
			'real_supplier_request' => false,
			'real_inventory_hold'   => false,
			'real_charge'           => false,
			'real_booking'          => false,
			'real_policy_issuance'  => false,
			'real_settlement'       => false,
		);
	}

	private function data_boundary() {
		return array(
			'raw_supplier_reference_exposed' => false,
			'raw_payment_data_exposed'       => false,
			'medical_data_exposed'           => false,
		);
	}

	private function exact_object( $value, $keys ) {
		return is_array( $value ) && ! $this->is_list( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private function is_list( $value ) {
		return is_array( $value ) && ( empty( $value ) || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
	}

	private function error( $suffix, $message, $status ) {
		return new WP_Error( 'tra_vel_commerce_revalidation_' . $suffix, $message, array( 'status' => (int) $status ) );
	}
}
