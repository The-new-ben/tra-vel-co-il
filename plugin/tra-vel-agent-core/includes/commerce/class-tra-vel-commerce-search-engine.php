<?php
/**
 * Deterministic fan-out, deduplication, grouping, and ranking for sandbox offers.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Search_Engine {
	const CONTRACT_VERSION = '1.0.0';
	const RANKING_VERSION  = '1.0.0';
	const MAX_OFFERS       = 500;

	/** @var Tra_Vel_Commerce_Sandbox_Catalog|null */
	private $catalog;

	/** @var Tra_Vel_Commerce_Sandbox_Network|null */
	private $network;

	/** @var array<string,array> */
	private $provider_descriptors = array();

	/** @var string */
	private $network_signature = '';

	/** @var string */
	private $secret = '';

	/** @var WP_Error|null */
	private $error;

	/**
	 * @param Tra_Vel_Commerce_Sandbox_Catalog $catalog Validated fixture catalog.
	 * @param string|null                       $secret  Explicit test secret or WP auth salt.
	 * @param Tra_Vel_Commerce_Sandbox_Network|null $network Canonical provider network.
	 */
	public function __construct( $catalog, $secret = null, $network = null ) {
		if ( ! $catalog instanceof Tra_Vel_Commerce_Sandbox_Catalog ) {
			$this->error = $this->error( 'search_catalog_invalid', 'A validated sandbox catalog is required.', 500 );
			return;
		}
		$ready = $catalog->readiness();
		if ( is_wp_error( $ready ) ) {
			$this->error = $ready;
			return;
		}
		if ( null === $network ) {
			if ( ! class_exists( 'Tra_Vel_Commerce_Sandbox_Network' ) ) {
				$this->error = $this->error( 'search_network_unavailable', 'The canonical provider network is unavailable.', 503 );
				return;
			}
			$network = new Tra_Vel_Commerce_Sandbox_Network();
		}
		if ( ! $network instanceof Tra_Vel_Commerce_Sandbox_Network ) {
			$this->error = $this->error( 'search_network_invalid', 'A validated canonical provider network is required.', 500 );
			return;
		}
		$descriptors = $network->all();
		$signature   = $network->signature();
		if ( is_wp_error( $descriptors ) || is_wp_error( $signature ) || ! $this->digest( $signature ) ) {
			$this->error = is_wp_error( $descriptors ) ? $descriptors : ( is_wp_error( $signature ) ? $signature : $this->error( 'search_network_invalid', 'The canonical provider-network signature is invalid.', 500 ) );
			return;
		}
		foreach ( $descriptors as $descriptor ) {
			$this->provider_descriptors[ $descriptor['provider_id'] ] = $descriptor;
		}
		if ( null === $secret ) {
			if ( ! function_exists( 'wp_salt' ) ) {
				$this->error = $this->error( 'search_secret_unavailable', 'The commerce offer-reference secret is unavailable.', 503 );
				return;
			}
			$secret = (string) wp_salt( 'auth' );
		}
		if ( ! is_string( $secret ) || strlen( $secret ) < 16 ) {
			$this->error = $this->error( 'search_secret_unavailable', 'The commerce offer-reference secret is unavailable.', 503 );
			return;
		}
		$this->catalog           = $catalog;
		$this->network           = $network;
		$this->network_signature = $signature;
		$this->secret            = $secret . '|tra-vel-commerce-search-v1';
	}

	/**
	 * Execute one deterministic owner-bound sandbox search.
	 *
	 * @param array $request Canonical commerce-search-request contract.
	 * @param array $context Exact owner_scope_digest and integer UTC epoch.
	 * @return array|WP_Error
	 */
	public function search( $request, $context ) {
		if ( is_wp_error( $this->error ) ) {
			return $this->error;
		}
		$request = $this->normalize_request( $request );
		$context = $this->normalize_context( $context );
		if ( is_wp_error( $request ) || is_wp_error( $context ) ) {
			return is_wp_error( $request ) ? $request : $context;
		}
		$query          = $this->query_from_request( $request );
		$catalog_digest = $this->catalog->catalog_digest();
		$session_ref    = $this->opaque_ref(
			'session',
			array(
				$context['owner_scope_digest'],
				$request['request_digest'],
				$request['ranking']['ranking_version'],
				$request['ranking']['selection_seed_digest'],
				$catalog_digest,
				$this->network_signature,
				$context['now'],
			)
		);
		$now_iso     = gmdate( 'Y-m-d\TH:i:s\Z', $context['now'] );
		$provider_runs = array();
		$candidates    = array();
		$providers_considered = 0;
		$providers_succeeded  = 0;
		$providers_failed     = 0;

		foreach ( $request['verticals'] as $vertical ) {
			$provider_ids = $this->searchable_provider_ids( $vertical );
			if ( is_wp_error( $provider_ids ) ) {
				return $provider_ids;
			}
			foreach ( $provider_ids as $provider_id ) {
				$providers_considered++;
				$found = $this->catalog->search_provider( $provider_id, $vertical, $query );
				$failed = is_wp_error( $found );
				if ( $failed ) {
					$providers_failed++;
				} else {
					$providers_succeeded++;
					foreach ( $found as $candidate ) {
						$capability_error = $this->candidate_capabilities_valid( $candidate );
						if ( is_wp_error( $capability_error ) ) {
							return $capability_error;
						}
						$candidates[] = $candidate;
					}
				}
				$provider_runs[] = array(
					'provider_run_ref' => $this->opaque_ref( 'run', array( $session_ref, $provider_id, $vertical ) ),
					'provider_id'      => $provider_id,
					'vertical'         => $vertical,
					'status'           => $failed ? 'failed' : 'succeeded',
					'result_count'     => $failed ? 0 : count( $found ),
					'error_code'       => $failed ? sanitize_key( (string) $found->get_error_code() ) : null,
					'started_at'       => $now_iso,
					'completed_at'     => $now_iso,
				);
			}
		}

		$raw_count = count( $candidates );
		$candidates = $this->dedupe_candidates( $candidates );
		if ( count( $candidates ) > self::MAX_OFFERS ) {
			$candidates = array_slice( $candidates, 0, self::MAX_OFFERS );
		}
		$grouped = $this->group_and_rank( $candidates, $request );
		$groups = array();
		$ranked_offer_refs = array();
		$earliest_expiry = null;
		foreach ( $grouped as $group ) {
			$offers = array();
			foreach ( $group['candidates'] as $candidate ) {
				$offer = $this->project_offer( $candidate, $request, $session_ref, $context['now'] );
				$offers[] = $offer;
				$expiry = strtotime( $offer['availability']['fresh_until'] );
				$earliest_expiry = null === $earliest_expiry ? $expiry : min( $earliest_expiry, $expiry );
				$ranked_offer_refs[] = array(
					'offer_ref'     => $offer['offer_ref'],
					'offer_version' => $offer['version'],
					'vertical'      => $offer['vertical'],
					'currency'      => $offer['pricing']['currency'],
					'price_scope'   => $offer['pricing']['price_scope'],
					'rank'          => $offer['ranking']['rank'],
					'score_bps'     => $offer['ranking']['score_bps'],
					'reasons'       => $offer['ranking']['reasons'],
				);
			}
			$groups[] = array(
				'vertical'    => $group['vertical'],
				'currency'    => $group['currency'],
				'price_scope' => $group['price_scope'],
				'offers'      => $offers,
			);
		}
		$expires_at = null === $earliest_expiry ? $context['now'] + 300 : $earliest_expiry;
		$status = $providers_failed > 0 ? ( $providers_succeeded > 0 ? 'partial' : 'failed' ) : 'complete';
		$truth = $this->sandbox_truth();
		$boundary = $this->data_boundary();
		$result = array(
			'contract_version' => self::CONTRACT_VERSION,
			'environment'      => 'sandbox',
			'catalog_digest'   => $catalog_digest,
			'provider_network_digest' => $this->network_signature,
			'session'          => array(
				'contract_version'     => self::CONTRACT_VERSION,
				'environment'          => 'sandbox',
				'session_ref'          => $session_ref,
				'version'              => 1,
				'owner_scope_digest'   => $context['owner_scope_digest'],
				'request_ref'          => $request['request_ref'],
				'request_digest'       => $request['request_digest'],
				'verticals'            => $request['verticals'],
				'status'               => $status,
				'ranking_version'      => $request['ranking']['ranking_version'],
				'selection_seed_digest'=> $request['ranking']['selection_seed_digest'],
				'catalog_digest'       => $catalog_digest,
				'provider_network_digest' => $this->network_signature,
				'provider_runs'        => $provider_runs,
				'ranked_offers'        => $ranked_offer_refs,
				'counts'               => array(
					'providers_considered' => $providers_considered,
					'providers_succeeded'  => $providers_succeeded,
					'providers_failed'     => $providers_failed,
					'offers_validated'     => count( $candidates ),
					'offers_rejected'      => max( 0, $raw_count - count( $candidates ) ),
				),
				'created_at'           => $now_iso,
				'updated_at'           => $now_iso,
				'expires_at'           => gmdate( 'Y-m-d\TH:i:s\Z', $expires_at ),
				'sandbox_truth'        => $truth,
				'data_boundary'        => $boundary,
			),
			'groups'           => $groups,
			'sandbox_truth'    => $truth,
			'data_boundary'    => $boundary,
		);
		$encoded = wp_json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) || false !== strpos( $encoded, 'private_product_ref' ) || 1 === preg_match( '/\bpx_[a-z0-9_]{8,90}\b/', $encoded ) ) {
			return $this->error( 'search_projection_failed', 'A private catalog reference reached the public offer projection.', 500 );
		}
		return $result;
	}

	private function normalize_request( $request ) {
		$required = array( 'contract_version', 'environment', 'request_ref', 'request_digest', 'verticals', 'trip', 'preferences', 'ranking', 'sandbox_truth', 'data_boundary' );
		if ( ! $this->exact_object( $request, $required ) || self::CONTRACT_VERSION !== $request['contract_version'] || 'sandbox' !== $request['environment'] || ! $this->opaque_ref_valid( $request['request_ref'] ) || ! $this->digest( $request['request_digest'] ) || ! $this->sandbox_truth_valid( $request['sandbox_truth'] ) || ! $this->data_boundary_valid( $request['data_boundary'] ) ) {
			return $this->error( 'search_request_invalid', 'The commerce search request is not a closed sandbox contract.', 400 );
		}
		$raw_basis = $request;
		unset( $raw_basis['request_digest'] );
		if ( ! hash_equals( $request['request_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $raw_basis ) ) ) {
			return $this->error( 'search_request_digest_invalid', 'The commerce search request digest does not match its canonical data.', 400 );
		}
		$verticals = Tra_Vel_Commerce_Taxonomy::verticals( $request['verticals'] );
		if ( is_wp_error( $verticals ) || $verticals !== $request['verticals'] ) {
			return $this->error( 'search_verticals_invalid', 'Search verticals must be canonical, unique, and sorted.', 400 );
		}
		$trip = $this->normalize_trip( $request['trip'] );
		$preferences = $this->normalize_preferences( $request['preferences'] );
		$ranking = $this->normalize_ranking( $request['ranking'] );
		if ( is_wp_error( $trip ) || is_wp_error( $preferences ) || is_wp_error( $ranking ) ) {
			return is_wp_error( $trip ) ? $trip : ( is_wp_error( $preferences ) ? $preferences : $ranking );
		}
		$request['verticals']  = $verticals;
		$request['trip']       = $trip;
		$request['preferences']= $preferences;
		$request['ranking']    = $ranking;
		$normalized_basis = $request;
		unset( $normalized_basis['request_digest'] );
		$request['request_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $normalized_basis );
		return $request;
	}

	private function normalize_context( $context ) {
		if ( ! $this->exact_object( $context, array( 'owner_scope_digest', 'now' ) ) || ! $this->digest( $context['owner_scope_digest'] ) || ! is_int( $context['now'] ) || $context['now'] < 1 ) {
			return $this->error( 'search_context_invalid', 'An exact owner-bound search context is required.', 400 );
		}
		return $context;
	}

	private function normalize_trip( $trip ) {
		$required = array( 'origin', 'destination_mode', 'destinations', 'date_window', 'travelers', 'currency', 'budget_limit_minor' );
		if ( ! $this->exact_object( $trip, $required ) || ! in_array( $trip['destination_mode'], array( 'fixed', 'flexible', 'anywhere' ), true ) || ! $this->is_list( $trip['destinations'] ) || count( $trip['destinations'] ) > 8 || ( 'fixed' === $trip['destination_mode'] && ! $trip['destinations'] ) || ! $this->place_query( $trip['origin'] ) ) {
			return $this->error( 'search_trip_invalid', 'The commerce trip query is invalid.', 400 );
		}
		foreach ( $trip['destinations'] as $destination ) {
			if ( ! $this->place_query( $destination ) ) {
				return $this->error( 'search_trip_invalid', 'A commerce destination query is invalid.', 400 );
			}
		}
		$window = $trip['date_window'];
		if ( ! $this->exact_object( $window, array( 'departure_earliest', 'departure_latest', 'return_earliest', 'return_latest', 'nights_min', 'nights_max' ) ) ) {
			return $this->error( 'search_dates_invalid', 'The commerce date window is invalid.', 400 );
		}
		foreach ( array( 'departure_earliest', 'departure_latest', 'return_earliest', 'return_latest' ) as $field ) {
			if ( null !== $window[ $field ] && ! $this->date( $window[ $field ] ) ) {
				return $this->error( 'search_dates_invalid', 'A commerce date is invalid.', 400 );
			}
		}
		if ( ! is_int( $window['nights_min'] ) || ! is_int( $window['nights_max'] ) || $window['nights_min'] < 0 || $window['nights_max'] < $window['nights_min'] || $window['nights_max'] > 365 || ( null !== $window['departure_earliest'] && null !== $window['departure_latest'] && $window['departure_earliest'] > $window['departure_latest'] ) || ( null !== $window['return_earliest'] && null !== $window['return_latest'] && $window['return_earliest'] > $window['return_latest'] ) || ( null !== $window['departure_earliest'] && null !== $window['return_latest'] && $window['return_latest'] < $window['departure_earliest'] ) ) {
			return $this->error( 'search_dates_invalid', 'The commerce date range is invalid.', 400 );
		}
		$departure_exact = null !== $window['departure_earliest'] && $window['departure_earliest'] === $window['departure_latest'];
		$return_exact    = null !== $window['return_earliest'] && $window['return_earliest'] === $window['return_latest'];
		if ( $departure_exact && $return_exact ) {
			$departure = new DateTimeImmutable( $window['departure_earliest'], new DateTimeZone( 'UTC' ) );
			$return     = new DateTimeImmutable( $window['return_earliest'], new DateTimeZone( 'UTC' ) );
			$nights     = (int) $departure->diff( $return )->format( '%r%a' );
			if ( $nights < 0 || $nights < $window['nights_min'] || $nights > $window['nights_max'] ) {
				return $this->error( 'search_dates_invalid', 'Exact travel dates must agree with the declared night range.', 400 );
			}
		}
		$travelers = $trip['travelers'];
		if ( ! $this->exact_object( $travelers, array( 'adults', 'children', 'infants', 'rooms' ) ) ) {
			return $this->error( 'search_travelers_invalid', 'The traveler quantities are invalid.', 400 );
		}
		foreach ( array( 'adults', 'children', 'infants', 'rooms' ) as $field ) {
			if ( ! is_int( $travelers[ $field ] ) ) {
				return $this->error( 'search_travelers_invalid', 'Traveler quantities must be integers.', 400 );
			}
		}
		if ( $travelers['adults'] < 1 || $travelers['adults'] > 20 || $travelers['children'] < 0 || $travelers['children'] > 20 || $travelers['infants'] < 0 || $travelers['infants'] > 10 || $travelers['rooms'] < 1 || $travelers['rooms'] > 10 || '' === Tra_Vel_Commerce_Money::currency( $trip['currency'] ) || ( null !== $trip['budget_limit_minor'] && ( is_wp_error( Tra_Vel_Commerce_Money::amount( $trip['budget_limit_minor'] ) ) || $trip['budget_limit_minor'] > 1000000000000 ) ) ) {
			return $this->error( 'search_trip_invalid', 'The commerce travelers, currency, or budget are invalid.', 400 );
		}
		return $trip;
	}

	private function normalize_preferences( $preferences ) {
		$required = array( 'direct_only', 'max_stops', 'priorities', 'vibes', 'accessibility_requested' );
		$allowed_priorities = array( 'price', 'duration', 'comfort', 'flexibility', 'location', 'simplicity', 'family', 'accessibility' );
		$allowed_vibes = array( 'city', 'beach', 'nature', 'romantic', 'family', 'adventure', 'food', 'wellness', 'nightlife', 'surprise' );
		if ( ! $this->exact_object( $preferences, $required ) || ! is_bool( $preferences['direct_only'] ) || ! is_int( $preferences['max_stops'] ) || $preferences['max_stops'] < 0 || $preferences['max_stops'] > 3 || ! is_bool( $preferences['accessibility_requested'] ) || ! $this->closed_string_list( $preferences['priorities'], $allowed_priorities, 8 ) || ! $this->closed_string_list( $preferences['vibes'], $allowed_vibes, 8 ) ) {
			return $this->error( 'search_preferences_invalid', 'The commerce search preferences are invalid.', 400 );
		}
		$normalized = $preferences;
		sort( $normalized['priorities'], SORT_STRING );
		sort( $normalized['vibes'], SORT_STRING );
		return $normalized;
	}

	private function normalize_ranking( $ranking ) {
		if ( ! $this->exact_object( $ranking, array( 'profile', 'ranking_version', 'selection_seed_digest' ) ) || ! in_array( $ranking['profile'], array( 'value', 'fastest', 'flexible', 'family', 'surprise' ), true ) || self::RANKING_VERSION !== $ranking['ranking_version'] || ! $this->digest( $ranking['selection_seed_digest'] ) ) {
			return $this->error( 'search_ranking_invalid', 'The commerce ranking contract is invalid.', 400 );
		}
		return $ranking;
	}

	private function query_from_request( $request ) {
		$trip = $request['trip'];
		$window = $trip['date_window'];
		$nights = max( 1, $window['nights_min'] );
		if ( null !== $window['departure_earliest'] && null !== $window['return_earliest'] ) {
			$departure = new DateTimeImmutable( $window['departure_earliest'], new DateTimeZone( 'UTC' ) );
			$return    = new DateTimeImmutable( $window['return_earliest'], new DateTimeZone( 'UTC' ) );
			$actual    = (int) $departure->diff( $return )->format( '%r%a' );
			if ( $actual >= 1 && $actual >= $window['nights_min'] && $actual <= $window['nights_max'] ) {
				$nights = $actual;
			}
		}
		$origin_codes = $this->place_codes( array( $trip['origin'] ) );
		$destination_codes = 'anywhere' === $trip['destination_mode'] ? array() : $this->place_codes( $trip['destinations'] );
		$travelers = $trip['travelers'];
		return array(
			'adults'                 => $travelers['adults'],
			'children'               => $travelers['children'],
			'infants'                => $travelers['infants'],
			'rooms'                  => $travelers['rooms'],
			'party_size'             => $travelers['adults'] + $travelers['children'] + $travelers['infants'],
			'nights'                 => $nights,
			'trip_days'              => $nights + 1,
			'currency'               => $trip['currency'],
			'budget_limit_minor'     => $trip['budget_limit_minor'],
			'origin_codes'           => $origin_codes,
			'destination_codes'      => $destination_codes,
			'departure_date'         => $window['departure_earliest'],
			'return_date'            => $window['return_earliest'],
			'direct_only'            => $request['preferences']['direct_only'],
			'max_stops'              => $request['preferences']['max_stops'],
			'accessibility_requested'=> $request['preferences']['accessibility_requested'],
			'vibes'                  => $request['preferences']['vibes'],
		);
	}

	/**
	 * Resolve catalog providers through the canonical, readiness-aware network.
	 * Network order is authoritative so provider priority has one meaning.
	 *
	 * @return array|WP_Error
	 */
	private function searchable_provider_ids( $vertical ) {
		$catalog_ids = $this->catalog->provider_ids_for_vertical( $vertical );
		if ( is_wp_error( $catalog_ids ) ) {
			return $catalog_ids;
		}
		$catalog_lookup = array_fill_keys( $catalog_ids, true );
		foreach ( $catalog_ids as $provider_id ) {
			if ( ! isset( $this->provider_descriptors[ $provider_id ] ) ) {
				return $this->error( 'search_provider_descriptor_missing', 'A seeded catalog provider has no canonical provider descriptor.', 500 );
			}
			$descriptor = $this->provider_descriptors[ $provider_id ];
			if ( ! in_array( $vertical, $descriptor['verticals'], true ) || ! in_array( 'search', $descriptor['capabilities'], true ) ) {
				return $this->error( 'search_provider_contract_mismatch', 'A seeded catalog provider exceeds its canonical provider contract.', 500 );
			}
		}

		$eligible = array();
		foreach ( $this->provider_descriptors as $provider_id => $descriptor ) {
			if ( isset( $catalog_lookup[ $provider_id ] ) && 'ready' === $descriptor['readiness']['status'] ) {
				$eligible[] = $provider_id;
			}
		}
		return $eligible;
	}

	/**
	 * Fail closed when a product promises an operation its provider cannot run.
	 *
	 * @return true|WP_Error
	 */
	private function candidate_capabilities_valid( $candidate ) {
		if ( ! is_array( $candidate ) || ! isset( $candidate['provider_id'], $candidate['capabilities'] ) || ! is_array( $candidate['capabilities'] ) || ! isset( $this->provider_descriptors[ $candidate['provider_id'] ] ) ) {
			return $this->error( 'search_candidate_contract_invalid', 'A seeded offer candidate has no canonical provider contract.', 500 );
		}
		$missing = array_diff( $candidate['capabilities'], $this->provider_descriptors[ $candidate['provider_id'] ]['capabilities'] );
		if ( $missing ) {
			return $this->error( 'search_candidate_capability_mismatch', 'A seeded offer candidate exceeds its provider capabilities.', 500 );
		}
		return true;
	}

	private function dedupe_candidates( $candidates ) {
		$deduped = array();
		foreach ( $candidates as $candidate ) {
			$key = implode( '|', array( $candidate['vertical'], $candidate['pricing']['currency'], $candidate['pricing']['price_scope'], $candidate['dedupe_key'] ) );
			if ( ! isset( $deduped[ $key ] ) || $this->candidate_precedes( $candidate, $deduped[ $key ] ) ) {
				$deduped[ $key ] = $candidate;
			}
		}
		ksort( $deduped, SORT_STRING );
		return array_values( $deduped );
	}

	private function candidate_precedes( $left, $right ) {
		$price = (int) $left['pricing']['total_amount_minor'] <=> (int) $right['pricing']['total_amount_minor'];
		if ( 0 !== $price ) {
			return $price < 0;
		}
		$flexibility = (int) $right['attributes']['flexibility_score_bps'] <=> (int) $left['attributes']['flexibility_score_bps'];
		if ( 0 !== $flexibility ) {
			return $flexibility < 0;
		}
		$provider = strcmp( $left['provider_id'], $right['provider_id'] );
		return 0 !== $provider ? $provider < 0 : strcmp( $left['product_digest'], $right['product_digest'] ) < 0;
	}

	private function group_and_rank( $candidates, $request ) {
		$groups = array();
		foreach ( $candidates as $candidate ) {
			$key = implode( '|', array( $candidate['vertical'], $candidate['pricing']['currency'], $candidate['pricing']['price_scope'] ) );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'vertical'    => $candidate['vertical'],
					'currency'    => $candidate['pricing']['currency'],
					'price_scope' => $candidate['pricing']['price_scope'],
					'candidates'  => array(),
				);
			}
			$groups[ $key ]['candidates'][] = $candidate;
		}
		ksort( $groups, SORT_STRING );
		foreach ( $groups as $key => $group ) {
			$groups[ $key ]['candidates'] = $this->rank_group( $group['candidates'], $request );
		}
		return array_values( $groups );
	}

	private function rank_group( $candidates, $request ) {
		$prices = array_column( array_column( $candidates, 'pricing' ), 'total_amount_minor' );
		$durations = array_column( array_column( $candidates, 'attributes' ), 'duration_minutes' );
		$min_price = min( $prices );
		$max_price = max( $prices );
		$min_duration = min( $durations );
		$max_duration = max( $durations );
		foreach ( $candidates as $index => $candidate ) {
			$price_score = $max_price === $min_price ? 10000 : intdiv( ( $max_price - $candidate['pricing']['total_amount_minor'] ) * 10000, $max_price - $min_price );
			$duration_score = $max_duration === $min_duration ? 10000 : intdiv( ( $max_duration - $candidate['attributes']['duration_minutes'] ) * 10000, $max_duration - $min_duration );
			$vibe_score = empty( $request['preferences']['vibes'] ) ? 5000 : ( array_intersect( $request['preferences']['vibes'], $candidate['attributes']['vibes'] ) ? 10000 : 0 );
			$seed_score = hexdec( substr( hash( 'sha256', $request['ranking']['selection_seed_digest'] . '|' . $candidate['product_digest'] ), 0, 8 ) ) % 10001;
			$dimensions = array(
				'price'       => $price_score,
				'duration'    => $duration_score,
				'flexibility' => $candidate['attributes']['flexibility_score_bps'],
				'location'    => $candidate['attributes']['location_score_bps'],
				'friction'    => $candidate['attributes']['friction_score_bps'],
				'family'      => $candidate['attributes']['family_score_bps'],
				'vibe'        => $vibe_score,
				'seed'        => $seed_score,
			);
			$weights = $this->profile_weights( $request['ranking']['profile'] );
			$weighted = 0;
			foreach ( $weights as $dimension => $weight ) {
				$weighted += $dimensions[ $dimension ] * $weight;
			}
			$candidate['ranking_score_bps'] = intdiv( $weighted, 100 );
			$candidate['ranking_reasons'] = $this->ranking_reasons( $candidate, $request, $min_price, $min_duration, $vibe_score );
			$candidates[ $index ] = $candidate;
		}
		usort(
			$candidates,
			static function ( $left, $right ) {
				$score = (int) $right['ranking_score_bps'] <=> (int) $left['ranking_score_bps'];
				if ( 0 !== $score ) {
					return $score;
				}
				$price = (int) $left['pricing']['total_amount_minor'] <=> (int) $right['pricing']['total_amount_minor'];
				if ( 0 !== $price ) {
					return $price;
				}
				$provider = strcmp( $left['provider_id'], $right['provider_id'] );
				return 0 !== $provider ? $provider : strcmp( $left['product_digest'], $right['product_digest'] );
			}
		);
		foreach ( $candidates as $index => $candidate ) {
			$candidates[ $index ]['ranking_rank'] = $index + 1;
		}
		return $candidates;
	}

	private function profile_weights( $profile ) {
		$profiles = array(
			'value'    => array( 'price' => 45, 'duration' => 10, 'flexibility' => 10, 'location' => 10, 'friction' => 10, 'family' => 10, 'vibe' => 5, 'seed' => 0 ),
			'fastest'  => array( 'price' => 15, 'duration' => 50, 'flexibility' => 5, 'location' => 10, 'friction' => 10, 'family' => 5, 'vibe' => 5, 'seed' => 0 ),
			'flexible' => array( 'price' => 15, 'duration' => 5, 'flexibility' => 45, 'location' => 10, 'friction' => 10, 'family' => 10, 'vibe' => 5, 'seed' => 0 ),
			'family'   => array( 'price' => 20, 'duration' => 10, 'flexibility' => 10, 'location' => 10, 'friction' => 10, 'family' => 35, 'vibe' => 5, 'seed' => 0 ),
			'surprise' => array( 'price' => 20, 'duration' => 5, 'flexibility' => 5, 'location' => 5, 'friction' => 10, 'family' => 10, 'vibe' => 20, 'seed' => 25 ),
		);
		return $profiles[ $profile ];
	}

	private function ranking_reasons( $candidate, $request, $min_price, $min_duration, $vibe_score ) {
		$reasons = array();
		if ( $candidate['pricing']['total_amount_minor'] === $min_price ) {
			$reasons[] = 'lowest_total';
		}
		if ( $candidate['attributes']['duration_minutes'] === $min_duration ) {
			$reasons[] = 'shorter_duration';
		}
		if ( $candidate['attributes']['flexibility_score_bps'] >= 8000 ) {
			$reasons[] = 'higher_flexibility';
		}
		if ( $candidate['attributes']['location_score_bps'] >= 8000 ) {
			$reasons[] = 'better_location';
		}
		if ( $candidate['attributes']['friction_score_bps'] >= 8000 ) {
			$reasons[] = 'lower_friction';
		}
		if ( $request['trip']['travelers']['children'] > 0 && $candidate['attributes']['family_score_bps'] >= 7500 ) {
			$reasons[] = 'family_fit';
		}
		if ( 10000 === $vibe_score ) {
			$reasons[] = 'vibe_fit';
		}
		if ( 'surprise' === $request['ranking']['profile'] ) {
			$reasons[] = 'seeded_surprise';
		}
		$reasons = array_values( array_unique( $reasons ) );
		return $reasons ? array_slice( $reasons, 0, 8 ) : array( 'lower_friction' );
	}

	private function project_offer( $candidate, $request, $session_ref, $now ) {
		$product_ref = $this->opaque_ref( 'product', array( $session_ref, $candidate['provider_id'], $candidate['private_product_ref'] ) );
		$offer_ref = $this->opaque_ref( 'offer', array( $session_ref, $candidate['product_digest'], $candidate['pricing']['total_amount_minor'], $candidate['ranking_rank'] ) );
		$checked_at  = gmdate( 'Y-m-d\TH:i:s\Z', $now );
		$fresh_until = gmdate( 'Y-m-d\TH:i:s\Z', $now + ( $candidate['availability']['hold_minutes'] * 60 ) );
		$geometry = $this->project_geometry( $candidate['geometry'], $product_ref );
		$terms = array(
			'terms_digest'         => Tra_Vel_Commerce_Policy::canonical_digest( $candidate['terms'] ),
			'cancellation'         => $candidate['terms']['cancellation'] . ' Refund: ' . $candidate['terms']['refund'],
			'changes'              => $candidate['terms']['changes'],
			'inclusions'           => 'Includes: ' . implode( ', ', $candidate['terms']['inclusions'] ) . '. Excludes: ' . implode( ', ', $candidate['terms']['exclusions'] ) . '.',
			'requires_revalidation'=> true,
		);
		$evidence_basis = array(
			'product_digest' => $candidate['product_digest'],
			'request_digest' => $request['request_digest'],
			'pricing'        => $candidate['pricing'],
			'availability'   => $candidate['availability'],
			'retrieved_at'   => $checked_at,
			'fresh_until'    => $fresh_until,
		);
		return array(
			'contract_version'          => self::CONTRACT_VERSION,
			'environment'               => 'sandbox',
			'offer_ref'                 => $offer_ref,
			'version'                   => 1,
			'search_session_ref'        => $session_ref,
			'provider_id'               => $candidate['provider_id'],
			'provider_reference_digest' => hash_hmac( 'sha256', 'provider-reference|' . $candidate['provider_id'] . '|' . $candidate['private_product_ref'], $this->secret ),
			'vertical'                  => $candidate['vertical'],
			'status'                    => $candidate['availability']['state'],
			'product'                   => array(
				'product_ref' => $product_ref,
				'title'       => $candidate['title'],
				'subtitle'    => $candidate['subtitle'],
				'badges'      => $candidate['badges'],
				'media'       => $candidate['media'],
				'facts'       => $candidate['facts'],
			),
			'geometry'                  => $geometry,
			'pricing'                   => $candidate['pricing'],
			'availability'              => array(
				'state'              => $candidate['availability']['state'],
				'quantity_remaining' => $candidate['availability']['quantity_remaining'],
				'checked_at'         => $checked_at,
				'fresh_until'        => $fresh_until,
			),
			'terms'                     => $terms,
			'capabilities'              => $candidate['capabilities'],
			'ranking'                  => array(
				'ranking_version' => $request['ranking']['ranking_version'],
				'profile'         => $request['ranking']['profile'],
				'score_bps'       => $candidate['ranking_score_bps'],
				'rank'            => $candidate['ranking_rank'],
				'reasons'         => $candidate['ranking_reasons'],
			),
			'evidence'                  => array(
				'adapter_version' => $this->provider_descriptors[ $candidate['provider_id'] ]['adapter_version'] . '-sandbox',
				'evidence_digest'=> Tra_Vel_Commerce_Policy::canonical_digest( $evidence_basis ),
				'retrieved_at'   => $checked_at,
				'fresh_until'    => $fresh_until,
			),
			'sandbox_truth'             => $this->sandbox_truth(),
			'data_boundary'             => $this->data_boundary(),
		);
	}

	private function project_geometry( $geometry, $product_ref ) {
		$places = array();
		$place_refs = array();
		foreach ( $geometry['places'] as $place ) {
			$place_ref = $this->opaque_ref( 'place', array( $product_ref, $place['place_key'] ) );
			$place_refs[ $place['place_key'] ] = $place_ref;
			$places[] = array(
				'place_ref' => $place_ref,
				'role'      => $place['role'],
				'label'     => $place['label'],
				'code'      => $place['code'],
				'latitude'  => $place['latitude'],
				'longitude' => $place['longitude'],
				'sequence'  => $place['sequence'],
			);
		}
		$segments = array();
		foreach ( $geometry['segments'] as $segment ) {
			$segments[] = array(
				'segment_ref'    => $this->opaque_ref( 'segment', array( $product_ref, $segment['segment_key'] ) ),
				'sequence'       => $segment['sequence'],
				'mode'           => $segment['mode'],
				'from_place_ref' => $place_refs[ $segment['from_place_key'] ],
				'to_place_ref'   => $place_refs[ $segment['to_place_key'] ],
				'duration_minutes'=> $segment['duration_minutes'],
			);
		}
		return array( 'places' => $places, 'segments' => $segments );
	}

	private function place_codes( $places ) {
		$codes = array();
		foreach ( $places as $place ) {
			if ( null !== $place['code'] ) {
				$codes[ strtoupper( $place['code'] ) ] = true;
			}
			if ( null !== $place['label'] ) {
				$label = strtoupper( preg_replace( '/[^A-Za-z0-9]+/', '', $place['label'] ) );
				if ( strlen( $label ) >= 2 && strlen( $label ) <= 16 ) {
					$codes[ $label ] = true;
				}
			}
		}
		$codes = array_keys( $codes );
		sort( $codes, SORT_STRING );
		return $codes;
	}

	private function place_query( $place ) {
		if ( ! $this->exact_object( $place, array( 'kind', 'code', 'label', 'latitude', 'longitude' ) ) || ! in_array( $place['kind'], array( 'iata', 'place', 'geo', 'open' ), true ) || ( null !== $place['code'] && ( ! is_string( $place['code'] ) || ! preg_match( '/^[A-Z0-9-]{2,12}$/', $place['code'] ) ) ) || ( null !== $place['label'] && ( ! is_string( $place['label'] ) || strlen( $place['label'] ) < 1 || strlen( $place['label'] ) > 120 || $place['label'] !== wp_strip_all_tags( $place['label'] ) ) ) ) {
			return false;
		}
		foreach ( array( 'latitude' => array( -90, 90 ), 'longitude' => array( -180, 180 ) ) as $field => $bounds ) {
			if ( null !== $place[ $field ] && ( ( ! is_int( $place[ $field ] ) && ! is_float( $place[ $field ] ) ) || $place[ $field ] < $bounds[0] || $place[ $field ] > $bounds[1] ) ) {
				return false;
			}
		}
		return null !== $place['code'] || null !== $place['label'] || ( null !== $place['latitude'] && null !== $place['longitude'] );
	}

	private function closed_string_list( $values, $allowed, $maximum ) {
		return $this->is_list( $values ) && count( $values ) <= $maximum && count( $values ) === count( array_unique( $values ) ) && ! array_diff( $values, $allowed );
	}

	private function opaque_ref( $kind, $basis ) {
		$token = rtrim( strtr( base64_encode( hash_hmac( 'sha256', $kind . '|' . Tra_Vel_Commerce_Policy::canonical_digest( $basis ), $this->secret, true ) ), '+/', '-_' ), '=' );
		return 'tv_' . $kind . '_' . $token;
	}

	private function opaque_ref_valid( $value ) {
		return is_string( $value ) && strlen( $value ) <= 120 && 1 === preg_match( '/^tv_[a-z]+_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private function date( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
			return false;
		}
		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
	}

	private function sandbox_truth_valid( $truth ) {
		return $truth === $this->sandbox_truth();
	}

	private function data_boundary_valid( $boundary ) {
		return $boundary === $this->data_boundary();
	}

	private function sandbox_truth() {
		return array(
			'simulated_inventory' => true,
			'real_supplier_request'=> false,
			'real_inventory_hold'  => false,
			'real_charge'          => false,
			'real_booking'         => false,
			'real_policy_issuance' => false,
			'real_settlement'      => false,
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
		return new WP_Error( 'tra_vel_commerce_' . $suffix, $message, array( 'status' => (int) $status ) );
	}
}
