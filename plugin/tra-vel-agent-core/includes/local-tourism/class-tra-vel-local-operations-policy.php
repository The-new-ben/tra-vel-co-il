<?php
/**
 * Fail-closed policy for private Israel local-service operations records.
 *
 * This companion layer binds local facts to the existing commerce and trip
 * engines. It neither creates inventory nor dispatches supplier or processor
 * operations.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Local_Operations_Policy {
	/**
	 * Validate an immutable local service revision.
	 *
	 * @return array|WP_Error
	 */
	public static function service_revision( $revision, $now_utc ) {
		$keys = array(
			'contract_version', 'environment', 'data_mode', 'service_revision_ref', 'service_ref',
			'revision_number', 'previous_revision_digest', 'revision_digest', 'supplier_ref',
			'inventory_type', 'sellable', 'occupancy', 'arrival', 'terms', 'after_hours_support',
			'provenance', 'commerce_binding', 'created_at', 'effective_at', 'boundary',
		);
		if ( ! self::exact_object( $revision, $keys ) || Tra_Vel_Local_Operations_Taxonomy::CONTRACT_VERSION !== $revision['contract_version'] || 'sandbox' !== $revision['environment'] || 'synthetic_demo' !== $revision['data_mode'] ) {
			return self::error( 'service_shape_invalid', 'The local service revision must match the closed synthetic-demo contract.' );
		}
		if ( ! self::utc( $now_utc ) || ! self::ref( $revision['service_revision_ref'], 'local_service_rev' ) || ! self::ref( $revision['service_ref'], 'local_service' ) || ! preg_match( '/^party_synthetic_demo_[a-z0-9]{8,48}$/', (string) $revision['supplier_ref'] ) || ! is_int( $revision['revision_number'] ) || $revision['revision_number'] < 1 || ! self::nullable_digest( $revision['previous_revision_digest'] ) || ! self::digest( $revision['revision_digest'] ) ) {
			return self::error( 'service_identity_invalid', 'Local service identities, lineage, and digests must be opaque and immutable.' );
		}
		if ( ( 1 === $revision['revision_number'] && null !== $revision['previous_revision_digest'] ) || ( $revision['revision_number'] > 1 && null === $revision['previous_revision_digest'] ) ) {
			return self::error( 'service_lineage_invalid', 'The service revision lineage does not match its revision number.' );
		}
		if ( '' === Tra_Vel_Local_Tourism_Taxonomy::inventory_type( $revision['inventory_type'] ) ) {
			return self::error( 'service_inventory_type_invalid', 'The local sellable type is not canonical.' );
		}

		$sellable = self::sellable( $revision['sellable'] );
		if ( is_wp_error( $sellable ) ) {
			return $sellable;
		}
		$occupancy = self::occupancy( $revision['occupancy'] );
		if ( is_wp_error( $occupancy ) ) {
			return $occupancy;
		}
		$arrival = self::arrival( $revision['arrival'] );
		if ( is_wp_error( $arrival ) ) {
			return $arrival;
		}
		$terms = self::terms( $revision['terms'] );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		$after_hours = self::after_hours( $revision['after_hours_support'] );
		if ( is_wp_error( $after_hours ) ) {
			return $after_hours;
		}
		$source = self::provenance( $revision['provenance'], $now_utc );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		$binding = $revision['commerce_binding'];
		if ( ! self::exact_object( $binding, array( 'commerce_offer_ref', 'trip_node_ref', 'offer_version', 'offer_digest' ) ) || ! self::ref( $binding['commerce_offer_ref'], 'offer' ) || ! preg_match( '/^tv_node_[A-Za-z0-9_-]{16,96}$/', (string) $binding['trip_node_ref'] ) || ! is_int( $binding['offer_version'] ) || $binding['offer_version'] < 1 || ! self::digest( $binding['offer_digest'] ) ) {
			return self::error( 'service_commerce_binding_invalid', 'A service revision must bind one exact existing offer and trip node.' );
		}
		$created = self::timestamp( $revision['created_at'] );
		$effective = self::timestamp( $revision['effective_at'] );
		$now = self::timestamp( $now_utc );
		if ( null === $created || null === $effective || $created > $now || $effective < $created ) {
			return self::error( 'service_time_invalid', 'The immutable service chronology is invalid.' );
		}
		if ( ! self::exact_object( $revision['boundary'], array( 'server_only', 'public_serialization_allowed', 'synthetic_demo', 'live_availability_claimed', 'checkout_created', 'supplier_dispatched', 'processor_called', 'raw_pii_stored', 'raw_payment_data_stored' ) ) || true !== $revision['boundary']['server_only'] || false !== $revision['boundary']['public_serialization_allowed'] || true !== $revision['boundary']['synthetic_demo'] || false !== $revision['boundary']['live_availability_claimed'] || false !== $revision['boundary']['checkout_created'] || false !== $revision['boundary']['supplier_dispatched'] || false !== $revision['boundary']['processor_called'] || false !== $revision['boundary']['raw_pii_stored'] || false !== $revision['boundary']['raw_payment_data_stored'] ) {
			return self::error( 'service_boundary_invalid', 'The local service record must remain private, synthetic, and side-effect free.' );
		}
		if ( ! hash_equals( $revision['revision_digest'], self::content_digest( $revision, 'revision_digest' ) ) ) {
			return self::error( 'service_digest_mismatch', 'The immutable service revision digest does not match its content.' );
		}
		$revision['sellable']            = $sellable;
		$revision['occupancy']           = $occupancy;
		$revision['arrival']             = $arrival;
		$revision['terms']               = $terms;
		$revision['after_hours_support'] = $after_hours;
		$revision['provenance']          = $source;
		return $revision;
	}

	/**
	 * Validate an immutable local search context with exact fit and benefit axes.
	 *
	 * @return array|WP_Error
	 */
	public static function search_context( $context ) {
		$keys = array(
			'contract_version', 'environment', 'data_mode', 'context_ref', 'context_version',
			'previous_context_digest', 'context_digest', 'created_at', 'dates', 'party',
			'room_allocations', 'geography', 'transport_modes', 'requirements', 'product_intents',
			'benefit_filters', 'boundary',
		);
		if ( ! self::exact_object( $context, $keys ) || Tra_Vel_Local_Operations_Taxonomy::CONTRACT_VERSION !== $context['contract_version'] || 'sandbox' !== $context['environment'] || 'synthetic_demo' !== $context['data_mode'] || ! self::ref( $context['context_ref'], 'local_search') || ! is_int( $context['context_version'] ) || $context['context_version'] < 1 || ! self::nullable_digest( $context['previous_context_digest'] ) || ! self::digest( $context['context_digest'] ) || ! self::utc( $context['created_at'] ) ) {
			return self::error( 'search_shape_invalid', 'The local search context must match the closed immutable contract.' );
		}
		if ( ( 1 === $context['context_version'] && null !== $context['previous_context_digest'] ) || ( $context['context_version'] > 1 && null === $context['previous_context_digest'] ) ) {
			return self::error( 'search_lineage_invalid', 'Search context ancestry must be explicit.' );
		}
		$dates = $context['dates'];
		if ( ! self::exact_object( $dates, array( 'start_date', 'end_date', 'timezone' ) ) || ! self::date_value( $dates['start_date'] ) || ! self::date_value( $dates['end_date'] ) || $dates['end_date'] < $dates['start_date'] || 'Asia/Jerusalem' !== $dates['timezone'] ) {
			return self::error( 'search_dates_invalid', 'Local dates must be chronological and use the Israel timezone.' );
		}
		$party = $context['party'];
		if ( ! self::exact_object( $party, array( 'adult_count', 'child_ages' ) ) || ! is_int( $party['adult_count'] ) || $party['adult_count'] < 1 || $party['adult_count'] > 32 || ! self::integer_list( $party['child_ages'], 0, 17, true ) || count( $party['child_ages'] ) > 32 ) {
			return self::error( 'search_party_invalid', 'The party must include adults and exact child ages.' );
		}
		$allocation = self::room_allocations( $context['room_allocations'], $party );
		if ( is_wp_error( $allocation ) ) {
			return $allocation;
		}
		$geography = $context['geography'];
		if ( ! self::exact_object( $geography, array( 'corridor', 'origin_geo_ref', 'destination_geo_refs', 'drive_time_limit_minutes' ) ) || '' === Tra_Vel_Local_Operations_Taxonomy::member( $geography['corridor'], Tra_Vel_Local_Operations_Taxonomy::CORRIDORS ) || ( null !== $geography['origin_geo_ref'] && ! self::ref( $geography['origin_geo_ref'], 'geo' ) ) || ! self::ref_list( $geography['destination_geo_refs'], 'geo', false ) || ( null !== $geography['drive_time_limit_minutes'] && ( ! is_int( $geography['drive_time_limit_minutes'] ) || $geography['drive_time_limit_minutes'] < 1 || $geography['drive_time_limit_minutes'] > 1440 ) ) ) {
			return self::error( 'search_geography_invalid', 'The search geography and drive-time preference are invalid.' );
		}
		$transport = Tra_Vel_Local_Operations_Taxonomy::list_of( $context['transport_modes'], Tra_Vel_Local_Operations_Taxonomy::TRANSPORT_MODES );
		if ( is_wp_error( $transport ) ) {
			return $transport;
		}
		$requirements = self::requirements( $context['requirements'] );
		if ( is_wp_error( $requirements ) ) {
			return $requirements;
		}
		$intents = self::product_intents( $context['product_intents'] );
		if ( is_wp_error( $intents ) ) {
			return $intents;
		}
		$benefits = self::benefit_filters( $context['benefit_filters'] );
		if ( is_wp_error( $benefits ) ) {
			return $benefits;
		}
		if ( ! self::exact_object( $context['boundary'], array( 'server_only', 'public_serialization_allowed', 'contains_raw_identity_data', 'contains_card_number', 'contains_loyalty_credentials', 'creates_eligibility', 'creates_availability', 'creates_price' ) ) || true !== $context['boundary']['server_only'] || false !== $context['boundary']['public_serialization_allowed'] || false !== $context['boundary']['contains_raw_identity_data'] || false !== $context['boundary']['contains_card_number'] || false !== $context['boundary']['contains_loyalty_credentials'] || false !== $context['boundary']['creates_eligibility'] || false !== $context['boundary']['creates_availability'] || false !== $context['boundary']['creates_price'] ) {
			return self::error( 'search_boundary_invalid', 'Search context cannot create commercial or eligibility truth.' );
		}
		if ( ! hash_equals( $context['context_digest'], self::content_digest( $context, 'context_digest' ) ) ) {
			return self::error( 'search_digest_mismatch', 'The immutable search context digest does not match its content.' );
		}
		$context['room_allocations'] = $allocation;
		$context['transport_modes']  = $transport;
		$context['requirements']     = $requirements;
		$context['product_intents']  = $intents;
		$context['benefit_filters']  = $benefits;
		return $context;
	}

	/**
	 * Validate one immutable, source-bound local disruption event.
	 *
	 * @return array|WP_Error
	 */
	public static function disruption_event( $event, $now_utc ) {
		$keys = array(
			'contract_version', 'environment', 'data_mode', 'event_ref', 'event_version',
			'supersedes_event_digest', 'event_digest', 'trigger', 'disruption_type', 'severity',
			'source', 'geometry', 'issued_at', 'effective_at', 'expires_at', 'affected_service_refs',
			'affected_trip_node_refs', 'boundary',
		);
		if ( ! self::exact_object( $event, $keys ) || Tra_Vel_Local_Operations_Taxonomy::CONTRACT_VERSION !== $event['contract_version'] || 'sandbox' !== $event['environment'] || 'synthetic_demo' !== $event['data_mode'] || ! self::ref( $event['event_ref'], 'local_event' ) || ! is_int( $event['event_version'] ) || $event['event_version'] < 1 || ! self::nullable_digest( $event['supersedes_event_digest'] ) || ! self::digest( $event['event_digest'] ) ) {
			return self::error( 'event_shape_invalid', 'The disruption event must match the closed immutable contract.' );
		}
		if ( ( 1 === $event['event_version'] && null !== $event['supersedes_event_digest'] ) || ( $event['event_version'] > 1 && null === $event['supersedes_event_digest'] ) || '' === Tra_Vel_Local_Operations_Taxonomy::member( $event['trigger'], Tra_Vel_Local_Operations_Taxonomy::SCENARIO_TRIGGERS ) || '' === Tra_Vel_Local_Operations_Taxonomy::member( $event['disruption_type'], Tra_Vel_Local_Operations_Taxonomy::DISRUPTION_TYPES ) || ! in_array( $event['severity'], Tra_Vel_Local_Operations_Taxonomy::SEVERITIES, true ) ) {
			return self::error( 'event_taxonomy_invalid', 'The event lineage, trigger, type, or severity is invalid.' );
		}
		$source = $event['source'];
		if ( ! self::exact_object( $source, array( 'authority', 'source_ref', 'evidence_digest', 'observed_at', 'fresh_until', 'truth_state' ) ) || '' === Tra_Vel_Local_Operations_Taxonomy::member( $source['authority'], Tra_Vel_Local_Operations_Taxonomy::SOURCE_AUTHORITIES ) || ! self::ref( $source['source_ref'], 'source' ) || ! self::digest( $source['evidence_digest'] ) || ! self::utc( $source['observed_at'] ) || ! self::utc( $source['fresh_until'] ) || ! in_array( $source['truth_state'], array( 'verified_current', 'observed_unverified', 'stale', 'conflict' ), true ) || $source['fresh_until'] <= $source['observed_at'] ) {
			return self::error( 'event_source_invalid', 'The disruption source is incomplete or stale by construction.' );
		}
		$geometry = $event['geometry'];
		if ( ! self::exact_object( $geometry, array( 'geometry_type', 'geometry_ref', 'geometry_digest', 'corridor' ) ) || ! in_array( $geometry['geometry_type'], array( 'point', 'bounds', 'route', 'corridor', 'municipality' ), true ) || ! self::ref( $geometry['geometry_ref'], 'geometry' ) || ! self::digest( $geometry['geometry_digest'] ) || '' === Tra_Vel_Local_Operations_Taxonomy::member( $geometry['corridor'], Tra_Vel_Local_Operations_Taxonomy::CORRIDORS ) ) {
			return self::error( 'event_geometry_invalid', 'A disruption requires an exact source-bound geometry reference.' );
		}
		$issued = self::timestamp( $event['issued_at'] );
		$effective = self::timestamp( $event['effective_at'] );
		$expires = self::timestamp( $event['expires_at'] );
		if ( null === $issued || null === $effective || null === $expires || $expires <= $issued || $expires <= $effective || $issued > self::timestamp( $now_utc ) || ! self::utc( $now_utc ) ) {
			return self::error( 'event_window_invalid', 'The disruption issue, effective, and expiry window is invalid.' );
		}
		if ( ! self::ref_list( $event['affected_service_refs'], 'local_service', false ) || ! self::pattern_list( $event['affected_trip_node_refs'], '/^tv_node_[A-Za-z0-9_-]{16,96}$/', false ) ) {
			return self::error( 'event_scope_invalid', 'A disruption must name affected service and trip-node references.' );
		}
		if ( ! self::exact_object( $event['boundary'], array( 'server_only', 'public_serialization_allowed', 'planning_only', 'supplier_dispatched', 'processor_called', 'financial_state_changed', 'raw_supplier_payload_stored' ) ) || true !== $event['boundary']['server_only'] || false !== $event['boundary']['public_serialization_allowed'] || true !== $event['boundary']['planning_only'] || false !== $event['boundary']['supplier_dispatched'] || false !== $event['boundary']['processor_called'] || false !== $event['boundary']['financial_state_changed'] || false !== $event['boundary']['raw_supplier_payload_stored'] ) {
			return self::error( 'event_boundary_invalid', 'A disruption event is evidence, not an execution or financial mutation.' );
		}
		if ( ! hash_equals( $event['event_digest'], self::content_digest( $event, 'event_digest' ) ) ) {
			return self::error( 'event_digest_mismatch', 'The immutable disruption digest does not match its content.' );
		}
		$event['source'] = $source;
		return $event;
	}

	/**
	 * Canonical SHA-256 digest for immutable test and runtime records.
	 */
	public static function content_digest( $record, $omit_key = null ) {
		$copy = $record;
		if ( null !== $omit_key && is_array( $copy ) ) {
			unset( $copy[ $omit_key ] );
		}
		$copy = self::canonicalize( $copy );
		return hash( 'sha256', function_exists( 'wp_json_encode' ) ? wp_json_encode( $copy, JSON_UNESCAPED_SLASHES ) : json_encode( $copy, JSON_UNESCAPED_SLASHES ) );
	}

	private static function sellable( $sellable ) {
		if ( ! self::exact_object( $sellable, array( 'scope', 'product_ref', 'unit_ref', 'session_ref', 'route_ref' ) ) || '' === Tra_Vel_Local_Operations_Taxonomy::member( $sellable['scope'], Tra_Vel_Local_Operations_Taxonomy::SERVICE_SCOPES ) || ! self::ref( $sellable['product_ref'], 'product' ) ) {
			return self::error( 'sellable_invalid', 'A local service must identify one exact unit, session, or route.' );
		}
		foreach ( array( 'unit', 'session', 'route' ) as $scope ) {
			$key = $scope . '_ref';
			if ( $scope === $sellable['scope'] ) {
				if ( ! self::ref( $sellable[ $key ], $scope ) ) {
					return self::error( 'sellable_scope_ref_invalid', 'The selected sellable scope lacks its exact reference.' );
				}
			} elseif ( null !== $sellable[ $key ] ) {
				return self::error( 'sellable_scope_ambiguous', 'Only the selected sellable scope may have a reference.' );
			}
		}
		return $sellable;
	}

	private static function occupancy( $occupancy ) {
		if ( ! self::exact_object( $occupancy, array( 'max_occupancy', 'max_adults', 'max_children', 'child_age_rule_ref', 'child_age_bands' ) ) || ! is_int( $occupancy['max_occupancy'] ) || ! is_int( $occupancy['max_adults'] ) || ! is_int( $occupancy['max_children'] ) || $occupancy['max_occupancy'] < 1 || $occupancy['max_occupancy'] > 64 || $occupancy['max_adults'] < 1 || $occupancy['max_children'] < 0 || $occupancy['max_adults'] > $occupancy['max_occupancy'] || $occupancy['max_children'] > $occupancy['max_occupancy'] || ! self::ref( $occupancy['child_age_rule_ref'], 'child_age_rule' ) || ! is_array( $occupancy['child_age_bands'] ) || array_values( $occupancy['child_age_bands'] ) !== $occupancy['child_age_bands'] || ! $occupancy['child_age_bands'] ) {
			return self::error( 'occupancy_invalid', 'Occupancy and child-age rules must be exact.' );
		}
		$next_age = 0;
		$seen = array();
		foreach ( $occupancy['child_age_bands'] as $band ) {
			if ( ! self::exact_object( $band, array( 'band_code', 'min_age', 'max_age', 'counts_as_occupant' ) ) || ! preg_match( '/^[a-z][a-z0-9_]{1,31}$/', (string) $band['band_code'] ) || isset( $seen[ $band['band_code'] ] ) || ! is_int( $band['min_age'] ) || ! is_int( $band['max_age'] ) || $band['min_age'] !== $next_age || $band['max_age'] < $band['min_age'] || $band['max_age'] > 17 || ! is_bool( $band['counts_as_occupant'] ) ) {
				return self::error( 'child_age_band_invalid', 'Child-age bands must uniquely and continuously cover ages 0 through 17.' );
			}
			$seen[ $band['band_code'] ] = true;
			$next_age = $band['max_age'] + 1;
		}
		if ( 18 !== $next_age ) {
			return self::error( 'child_age_coverage_invalid', 'Child-age rules must cover every age from 0 through 17.' );
		}
		return $occupancy;
	}

	private static function arrival( $arrival ) {
		if ( ! self::exact_object( $arrival, array( 'check_in_local', 'check_out_local', 'key_handoff_mode', 'key_handoff_ref', 'late_arrival_notice_minutes', 'arrival_instruction_ref' ) ) || ! self::local_time_or_null( $arrival['check_in_local'] ) || ! self::local_time_or_null( $arrival['check_out_local'] ) || '' === Tra_Vel_Local_Operations_Taxonomy::member( $arrival['key_handoff_mode'], Tra_Vel_Local_Operations_Taxonomy::KEY_HANDOFF_MODES ) || ! self::ref( $arrival['key_handoff_ref'], 'handoff' ) || ! is_int( $arrival['late_arrival_notice_minutes'] ) || $arrival['late_arrival_notice_minutes'] < 0 || $arrival['late_arrival_notice_minutes'] > 10080 || ! self::ref( $arrival['arrival_instruction_ref'], 'arrival' ) ) {
			return self::error( 'arrival_invalid', 'Check-in, check-out, key handoff, and late-arrival rules must be exact.' );
		}
		return $arrival;
	}

	private static function terms( $terms ) {
		if ( ! self::exact_object( $terms, array( 'tax_treatment', 'tax_terms_ref', 'deposit_treatment', 'deposit_terms_ref', 'cancellation_treatment', 'cancellation_terms_ref', 'cancellation_deadline_utc' ) ) || '' === Tra_Vel_Local_Operations_Taxonomy::member( $terms['tax_treatment'], Tra_Vel_Local_Operations_Taxonomy::TAX_TREATMENTS ) || ! self::ref( $terms['tax_terms_ref'], 'terms' ) || '' === Tra_Vel_Local_Operations_Taxonomy::member( $terms['deposit_treatment'], Tra_Vel_Local_Operations_Taxonomy::DEPOSIT_TREATMENTS ) || ! self::ref( $terms['deposit_terms_ref'], 'terms' ) || '' === Tra_Vel_Local_Operations_Taxonomy::member( $terms['cancellation_treatment'], Tra_Vel_Local_Operations_Taxonomy::CANCELLATION_TREATMENTS ) || ! self::ref( $terms['cancellation_terms_ref'], 'terms' ) || ! self::utc_or_null( $terms['cancellation_deadline_utc'] ) ) {
			return self::error( 'terms_invalid', 'Tax, deposit, and cancellation semantics require exact terms references.' );
		}
		return $terms;
	}

	private static function after_hours( $support ) {
		if ( ! self::exact_object( $support, array( 'available', 'owner_ref', 'channel_ref', 'response_target_minutes', 'handoff_instruction_ref' ) ) || ! is_bool( $support['available'] ) || ! preg_match( '/^party_synthetic_demo_[a-z0-9]{8,48}$/', (string) $support['owner_ref'] ) || ! self::ref( $support['channel_ref'], 'channel' ) || ! is_int( $support['response_target_minutes'] ) || $support['response_target_minutes'] < 1 || $support['response_target_minutes'] > 1440 || ! self::ref( $support['handoff_instruction_ref'], 'handoff_instruction' ) ) {
			return self::error( 'after_hours_invalid', 'After-hours support requires an owner, channel, and bounded response target.' );
		}
		return $support;
	}

	private static function provenance( $source, $now_utc ) {
		if ( ! self::exact_object( $source, array( 'authority', 'source_ref', 'evidence_digest', 'observed_at', 'reviewed_at', 'expires_at', 'review_state' ) ) || '' === Tra_Vel_Local_Operations_Taxonomy::member( $source['authority'], Tra_Vel_Local_Operations_Taxonomy::SOURCE_AUTHORITIES ) || ! self::ref( $source['source_ref'], 'source' ) || ! self::digest( $source['evidence_digest'] ) || ! self::utc( $source['observed_at'] ) || ! self::utc( $source['reviewed_at'] ) || ! self::utc( $source['expires_at'] ) || ! in_array( $source['review_state'], array( 'reviewed', 'quarantined', 'superseded' ), true ) || $source['reviewed_at'] < $source['observed_at'] || $source['expires_at'] <= $source['reviewed_at'] || ( 'reviewed' === $source['review_state'] && $source['expires_at'] <= $now_utc ) ) {
			return self::error( 'provenance_invalid', 'Local service provenance must be reviewed and current at evaluation time.' );
		}
		return $source;
	}

	private static function room_allocations( $allocations, $party ) {
		if ( ! is_array( $allocations ) || array_values( $allocations ) !== $allocations || ! $allocations || count( $allocations ) > 16 ) {
			return self::error( 'room_allocations_invalid', 'At least one bounded room allocation is required.' );
		}
		$adult_total = 0;
		$child_indexes = array();
		$refs = array();
		foreach ( $allocations as $allocation ) {
			if ( ! self::exact_object( $allocation, array( 'allocation_ref', 'adult_count', 'child_age_indexes' ) ) || ! self::ref( $allocation['allocation_ref'], 'room_allocation' ) || isset( $refs[ $allocation['allocation_ref'] ] ) || ! is_int( $allocation['adult_count'] ) || $allocation['adult_count'] < 1 || ! self::integer_list( $allocation['child_age_indexes'], 0, max( 0, count( $party['child_ages'] ) - 1 ), true ) ) {
				return self::error( 'room_allocation_invalid', 'Each room allocation must bind adults and child-age indexes exactly once.' );
			}
			$refs[ $allocation['allocation_ref'] ] = true;
			$adult_total += $allocation['adult_count'];
			foreach ( $allocation['child_age_indexes'] as $index ) {
				if ( isset( $child_indexes[ $index ] ) ) {
					return self::error( 'room_child_duplicate', 'A child cannot appear in more than one room allocation.' );
				}
				$child_indexes[ $index ] = true;
			}
		}
		if ( $adult_total !== $party['adult_count'] || count( $child_indexes ) !== count( $party['child_ages'] ) ) {
			return self::error( 'room_party_mismatch', 'Room allocations must account for the complete party.' );
		}
		return $allocations;
	}

	private static function requirements( $requirements ) {
		$groups = array(
			'kosher'        => Tra_Vel_Local_Operations_Taxonomy::KOSHER_REQUIREMENTS,
			'shabbat'       => Tra_Vel_Local_Operations_Taxonomy::SHABBAT_REQUIREMENTS,
			'accessibility' => Tra_Vel_Local_Operations_Taxonomy::ACCESSIBILITY_REQUIREMENTS,
			'parking'       => Tra_Vel_Local_Operations_Taxonomy::PARKING_REQUIREMENTS,
		);
		if ( ! self::exact_object( $requirements, array_keys( $groups ) ) ) {
			return self::error( 'requirements_shape_invalid', 'Exact kosher, Shabbat, accessibility, and parking axes are required.' );
		}
		foreach ( $groups as $group => $allowed ) {
			$list = Tra_Vel_Local_Operations_Taxonomy::list_of( $requirements[ $group ], $allowed, true );
			if ( is_wp_error( $list ) ) {
				return $list;
			}
			$requirements[ $group ] = $list;
		}
		return $requirements;
	}

	private static function product_intents( $intents ) {
		$groups = array(
			'activities' => Tra_Vel_Local_Operations_Taxonomy::ACTIVITY_REQUIREMENTS,
			'dining'     => Tra_Vel_Local_Operations_Taxonomy::DINING_REQUIREMENTS,
			'equipment'  => Tra_Vel_Local_Operations_Taxonomy::EQUIPMENT_REQUIREMENTS,
		);
		if ( ! self::exact_object( $intents, array_keys( $groups ) ) ) {
			return self::error( 'product_intents_shape_invalid', 'Activity, dining, and equipment intent axes are required.' );
		}
		foreach ( $groups as $group => $allowed ) {
			$list = Tra_Vel_Local_Operations_Taxonomy::list_of( $intents[ $group ], $allowed, true );
			if ( is_wp_error( $list ) ) {
				return $list;
			}
			$intents[ $group ] = $list;
		}
		return $intents;
	}

	private static function benefit_filters( $filters ) {
		if ( ! self::exact_object( $filters, array( 'filter_mode', 'airline_inventory_ids', 'program_ids', 'credential_product_ids', 'campaign_revisions' ) ) || ! in_array( $filters['filter_mode'], array( 'all', 'any' ), true ) || ! self::pattern_list( $filters['airline_inventory_ids'], '/^airline_[a-z0-9][a-z0-9_]{2,55}$/', true ) || ! self::pattern_list( $filters['program_ids'], '/^program_[a-z0-9][a-z0-9_]{2,55}$/', true ) || ! self::pattern_list( $filters['credential_product_ids'], '/^credential_[a-z0-9][a-z0-9_]{2,51}$/', true ) || ! is_array( $filters['campaign_revisions'] ) || array_values( $filters['campaign_revisions'] ) !== $filters['campaign_revisions'] ) {
			return self::error( 'benefit_filters_invalid', 'Benefit filtering must use exact airline, program, card-product, and campaign-revision axes.' );
		}
		$seen = array();
		foreach ( $filters['campaign_revisions'] as $revision ) {
			if ( ! self::exact_object( $revision, array( 'campaign_id', 'version' ) ) || ! preg_match( '/^campaign_[a-z0-9][a-z0-9_]{2,53}$/', (string) $revision['campaign_id'] ) || ! is_int( $revision['version'] ) || $revision['version'] < 1 || isset( $seen[ $revision['campaign_id'] . ':' . $revision['version'] ] ) ) {
				return self::error( 'campaign_revision_filter_invalid', 'Campaign filtering requires exact unique campaign ID and version pairs.' );
			}
			$seen[ $revision['campaign_id'] . ':' . $revision['version'] ] = true;
		}
		return $filters;
	}

	private static function exact_object( $value, $keys ) {
		if ( ! is_array( $value ) || array_values( $value ) === $value ) {
			return false;
		}
		$actual = array_keys( $value );
		sort( $actual, SORT_STRING );
		sort( $keys, SORT_STRING );
		return $actual === $keys;
	}

	private static function ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private static function ref_list( $values, $kind, $allow_empty ) {
		return self::validated_list( $values, static function ( $value ) use ( $kind ) { return self::ref( $value, $kind ); }, $allow_empty );
	}

	private static function pattern_list( $values, $pattern, $allow_empty ) {
		return self::validated_list( $values, static function ( $value ) use ( $pattern ) { return is_string( $value ) && 1 === preg_match( $pattern, $value ); }, $allow_empty );
	}

	private static function integer_list( $values, $minimum, $maximum, $allow_empty ) {
		return self::validated_list( $values, static function ( $value ) use ( $minimum, $maximum ) { return is_int( $value ) && $value >= $minimum && $value <= $maximum; }, $allow_empty );
	}

	private static function validated_list( $values, $validator, $allow_empty ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( ! $allow_empty && ! $values ) ) {
			return false;
		}
		$seen = array();
		foreach ( $values as $value ) {
			$key = is_scalar( $value ) ? (string) $value : serialize( $value );
			if ( ! call_user_func( $validator, $value ) || isset( $seen[ $key ] ) ) {
				return false;
			}
			$seen[ $key ] = true;
		}
		return true;
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function nullable_digest( $value ) {
		return null === $value || self::digest( $value );
	}

	private static function utc( $value ) {
		return null !== self::timestamp( $value );
	}

	private static function utc_or_null( $value ) {
		return null === $value || self::utc( $value );
	}

	private static function timestamp( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01])T(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]Z$/', $value ) ) {
			return null;
		}
		$timestamp = strtotime( $value );
		return false === $timestamp ? null : $timestamp;
	}

	private static function date_value( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01])$/', $value ) ) {
			return false;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value;
	}

	private static function local_time_or_null( $value ) {
		return null === $value || ( is_string( $value ) && 1 === preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $value ) );
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_values( $value ) === $value ) {
			return array_map( array( __CLASS__, 'canonicalize' ), $value );
		}
		ksort( $value, SORT_STRING );
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}
		return $value;
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_local_operations_' . $suffix, $message, array( 'status' => 400 ) );
	}
}
