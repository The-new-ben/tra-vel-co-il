<?php
/**
 * Closed validation policy for private, non-executing trip-change stress plans.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Trip_Change_Stress_Policy {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_COMPONENTS = 48;
	const MAX_TRAVELERS = 12;
	const MAX_CHANGES = 12;

	const SCENARIO_TYPES = array(
		'overlapping_connection_disruptions',
		'flight_only_change',
		'five_person_package_constraints',
		'aircraft_or_terminal_change',
		'israel_gtfs_degraded',
	);

	const VERTICALS = array( 'flight', 'accommodation', 'insurance', 'ground', 'package', 'activity' );
	const CHANGE_TYPES = array(
		'flight_connection_delayed',
		'flight_connection_missed',
		'flight_connection_cancelled',
		'flight_schedule_changed',
		'package_party_scope_changed',
		'flight_aircraft_changed',
		'flight_terminal_changed',
		'israel_gtfs_source_degraded',
	);
	const TRUTH_STATES = array( 'verified_current', 'observed_unverified', 'stale', 'unavailable' );
	const ELIGIBILITY_STATES = array( 'eligible', 'ineligible', 'unknown' );
	const CONSENT_STATES = array( 'given', 'pending', 'not_required' );
	const AUTHORITY_STATES = array( 'verified', 'missing', 'not_required' );
	const ACCESSIBILITY_ACK_STATES = array( 'verified', 'pending', 'not_required' );
	const ACCESSIBILITY_CODES = array( 'wheelchair', 'mobility_assistance', 'visual_assistance', 'hearing_assistance', 'cognitive_assistance' );

	const STRATEGIES = array(
		'protected_connection_resequence',
		'independent_connection_rebook_review',
		'preserve_unaffected_and_revalidate_flight',
		'split_constrained_party_scope',
		'hold_package_for_party_clearance',
		'protected_aircraft_terminal_revalidation',
		'official_channel_and_human_transit_fallback',
	);

	const ACTION_CODES = array(
		'assess_connection_dependency_chain',
		'compare_connection_recovery',
		'revalidate_changed_flight_only',
		'review_party_eligibility',
		'review_party_consent',
		'review_guardian_authority',
		'review_accessibility_acknowledgement',
		'recheck_seat_assignment',
		'recheck_special_service_request',
		'recheck_wheelchair_assistance',
		'recheck_baggage_allowance',
		'recheck_minimum_connection_time',
		'withhold_unverified_transit_route',
		'consult_official_route_planner',
		'route_to_human_transit_review',
	);

	const BLOCKER_CODES = array(
		'eligibility_not_verified',
		'consent_pending',
		'guardian_authority_missing',
		'accessibility_ack_pending',
		'official_source_stale',
		'official_source_unavailable',
		'supplier_verification_pending',
	);

	const RECHECK_CODES = array(
		'seat_assignment',
		'special_service_request',
		'wheelchair_assistance',
		'baggage_allowance',
		'minimum_connection_time',
	);

	const OFFICIAL_FALLBACK_CHANNELS = array(
		'israel_official_route_planner',
		'official_transport_operator_channel',
		'human_travel_agent',
	);

	/**
	 * Validate a normalized, privacy-minimized stress input.
	 *
	 * @return array|WP_Error
	 */
	public static function input( $input ) {
		$keys = array( 'contract_version', 'environment', 'scenario_ref', 'trip_ref', 'scenario_type', 'observed_at', 'components', 'travelers', 'changes', 'selected_strategy', 'official_transit_source', 'input_boundary' );
		if ( ! self::exact_object( $input, $keys ) || self::CONTRACT_VERSION !== $input['contract_version'] || 'private_simulation' !== $input['environment'] || ! self::ref( $input['scenario_ref'], 'stress_scenario' ) || ! self::ref( $input['trip_ref'], 'trip' ) || ! in_array( $input['scenario_type'], self::SCENARIO_TYPES, true ) || ! self::utc( $input['observed_at'] ) || ! in_array( $input['selected_strategy'], self::strategies_for( $input['scenario_type'] ), true ) || ! self::input_boundary( $input['input_boundary'] ) ) {
			return self::error( 'input_shape_invalid', 'The trip-change stress input is not a closed private simulation contract.' );
		}

		$travelers = self::travelers( $input['travelers'] );
		if ( is_wp_error( $travelers ) ) {
			return $travelers;
		}
		$components = self::components( $input['components'], $travelers );
		if ( is_wp_error( $components ) ) {
			return $components;
		}
		$changes = self::changes( $input['changes'], $components, $travelers, $input['observed_at'] );
		if ( is_wp_error( $changes ) ) {
			return $changes;
		}
		$source = self::source_input( $input['official_transit_source'], $input['scenario_type'], $input['observed_at'] );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		$scenario = self::scenario_input_rules( $input, $components, $travelers );
		if ( is_wp_error( $scenario ) ) {
			return $scenario;
		}
		return $input;
	}

	/**
	 * Validate a complete deterministic plan against its normalized input.
	 *
	 * @return array|WP_Error
	 */
	public static function plan( $plan, $input ) {
		$valid_input = self::input( $input );
		if ( is_wp_error( $valid_input ) ) {
			return $valid_input;
		}
		$keys = array( 'contract_version', 'environment', 'plan_ref', 'plan_digest', 'scenario_ref', 'trip_ref', 'scenario_type', 'observed_at', 'dependency_order', 'component_partition', 'traveler_partition', 'recovery_candidates', 'selected_candidate_ref', 'actions', 'required_rechecks', 'transit_source_gate', 'summary', 'private_boundary' );
		if ( ! self::exact_object( $plan, $keys ) || self::CONTRACT_VERSION !== $plan['contract_version'] || 'private_simulation' !== $plan['environment'] || ! self::ref( $plan['plan_ref'], 'stress_plan' ) || ! self::digest( $plan['plan_digest'] ) || $plan['scenario_ref'] !== $input['scenario_ref'] || $plan['trip_ref'] !== $input['trip_ref'] || $plan['scenario_type'] !== $input['scenario_type'] || $plan['observed_at'] !== $input['observed_at'] || ! self::private_boundary( $plan['private_boundary'] ) ) {
			return self::error( 'plan_shape_invalid', 'The trip-change output is not a sealed planning-only private contract.' );
		}

		$component_refs = self::ordered_refs( $input['components'], 'component_ref' );
		$traveler_refs = self::ordered_refs( $input['travelers'], 'traveler_ref' );
		if ( ! self::same_members( $plan['dependency_order'], $component_refs ) || ! self::dependency_order_valid( $plan['dependency_order'], $input['components'] ) ) {
			return self::error( 'dependency_order_invalid', 'Dependency order must be a deterministic topological ordering of every component.' );
		}
		$component_partition = self::partition( $plan['component_partition'], $component_refs, 'component' );
		if ( is_wp_error( $component_partition ) ) {
			return $component_partition;
		}
		$traveler_partition = self::partition( $plan['traveler_partition'], $traveler_refs, 'traveler' );
		if ( is_wp_error( $traveler_partition ) ) {
			return $traveler_partition;
		}

		$candidates = self::candidates( $plan['recovery_candidates'], $plan['selected_candidate_ref'], $input, $plan['component_partition'] );
		if ( is_wp_error( $candidates ) ) {
			return $candidates;
		}
		$actions = self::actions( $plan['actions'], $component_refs, $traveler_refs, $plan['component_partition'], $plan['traveler_partition'] );
		if ( is_wp_error( $actions ) ) {
			return $actions;
		}
		$rechecks = self::rechecks( $plan['required_rechecks'], $input, $plan['component_partition'] );
		if ( is_wp_error( $rechecks ) ) {
			return $rechecks;
		}
		$source_gate = self::source_gate( $plan['transit_source_gate'], $input['official_transit_source'], $input['scenario_type'] );
		if ( is_wp_error( $source_gate ) ) {
			return $source_gate;
		}
		$scenario = self::scenario_plan_rules( $plan, $input );
		if ( is_wp_error( $scenario ) ) {
			return $scenario;
		}
		if ( ! self::summary( $plan['summary'], $plan ) ) {
			return self::error( 'summary_invalid', 'Plan summary must exactly count the exhaustive partitions, candidates, rechecks, and zero side effects.' );
		}
		$expected_ref = self::ref_value( 'stress_plan', self::canonical_digest( $input ) );
		if ( $expected_ref !== $plan['plan_ref'] || ! hash_equals( self::plan_digest( $plan ), $plan['plan_digest'] ) ) {
			return self::error( 'plan_seal_invalid', 'Trip-change plan identity or immutable digest is not deterministic.' );
		}
		return $plan;
	}

	public static function strategies_for( $scenario_type ) {
		$map = array(
			'overlapping_connection_disruptions' => array( 'protected_connection_resequence', 'independent_connection_rebook_review' ),
			'flight_only_change' => array( 'preserve_unaffected_and_revalidate_flight' ),
			'five_person_package_constraints' => array( 'split_constrained_party_scope', 'hold_package_for_party_clearance' ),
			'aircraft_or_terminal_change' => array( 'protected_aircraft_terminal_revalidation' ),
			'israel_gtfs_degraded' => array( 'official_channel_and_human_transit_fallback' ),
		);
		return isset( $map[ $scenario_type ] ) ? $map[ $scenario_type ] : array();
	}

	public static function canonical_digest( $value ) {
		return hash( 'sha256', wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	public static function plan_digest( $plan ) {
		$value = $plan;
		unset( $value['plan_digest'] );
		return self::canonical_digest( $value );
	}

	public static function ref_value( $kind, $seed ) {
		return 'tv_' . $kind . '_' . substr( hash( 'sha256', $seed ), 0, 32 );
	}

	public static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_trip_change_stress_' . $suffix, $message, array( 'status' => 409 ) );
	}

	private static function travelers( $travelers ) {
		if ( ! self::is_list( $travelers ) || ! $travelers || count( $travelers ) > self::MAX_TRAVELERS ) {
			return self::error( 'travelers_invalid', 'Traveler universe must be a bounded ordered list.' );
		}
		$refs = array();
		$sequences = array();
		$previous_sequence = 0;
		foreach ( $travelers as $traveler ) {
			$keys = array( 'traveler_ref', 'party_sequence', 'eligibility_state', 'consent_state', 'is_minor', 'guardian_authority_state', 'accessibility_need_codes', 'accessibility_ack_state' );
			if ( ! self::exact_object( $traveler, $keys ) || ! self::ref( $traveler['traveler_ref'], 'traveler' ) || ! self::positive_int( $traveler['party_sequence'] ) || isset( $refs[ $traveler['traveler_ref'] ] ) || isset( $sequences[ $traveler['party_sequence'] ] ) || ! in_array( $traveler['eligibility_state'], self::ELIGIBILITY_STATES, true ) || ! in_array( $traveler['consent_state'], self::CONSENT_STATES, true ) || ! is_bool( $traveler['is_minor'] ) || ! in_array( $traveler['guardian_authority_state'], self::AUTHORITY_STATES, true ) || ! self::closed_list( $traveler['accessibility_need_codes'], self::ACCESSIBILITY_CODES, true ) || ! in_array( $traveler['accessibility_ack_state'], self::ACCESSIBILITY_ACK_STATES, true ) ) {
				return self::error( 'traveler_invalid', 'Traveler constraints must use unique identity and a closed eligibility, consent, authority, and accessibility vocabulary.' );
			}
			if ( $traveler['party_sequence'] <= $previous_sequence ) {
				return self::error( 'traveler_order_invalid', 'Travelers must be sealed in strictly increasing party-sequence order.' );
			}
			if ( $traveler['is_minor'] && 'not_required' === $traveler['guardian_authority_state'] ) {
				return self::error( 'minor_authority_invalid', 'A minor cannot silently bypass guardian authority.' );
			}
			if ( ! $traveler['is_minor'] && 'missing' === $traveler['guardian_authority_state'] ) {
				return self::error( 'adult_authority_invalid', 'Guardian authority cannot be missing for an adult traveler.' );
			}
			if ( ! $traveler['accessibility_need_codes'] && 'not_required' !== $traveler['accessibility_ack_state'] ) {
				return self::error( 'accessibility_ack_orphaned', 'Accessibility acknowledgement cannot exist without a declared minimized need code.' );
			}
			if ( $traveler['accessibility_need_codes'] && 'not_required' === $traveler['accessibility_ack_state'] ) {
				return self::error( 'accessibility_ack_missing', 'A declared accessibility need requires an explicit acknowledgement state.' );
			}
			$refs[ $traveler['traveler_ref'] ] = true;
			$sequences[ $traveler['party_sequence'] ] = true;
			$previous_sequence = $traveler['party_sequence'];
		}
		return $refs;
	}

	private static function components( $components, $travelers ) {
		if ( ! self::is_list( $components ) || ! $components || count( $components ) > self::MAX_COMPONENTS ) {
			return self::error( 'components_invalid', 'Component universe must be a bounded ordered list.' );
		}
		$map = array();
		$sequences = array();
		$previous_sequence = 0;
		foreach ( $components as $component ) {
			$keys = array( 'component_ref', 'vertical', 'sequence', 'dependency_refs', 'traveler_refs' );
			if ( ! self::exact_object( $component, $keys ) || ! self::ref( $component['component_ref'], 'component' ) || isset( $map[ $component['component_ref'] ] ) || ! in_array( $component['vertical'], self::VERTICALS, true ) || ! self::positive_int( $component['sequence'] ) || isset( $sequences[ $component['sequence'] ] ) || ! self::ref_list( $component['dependency_refs'], 'component', false ) || ! self::ref_list( $component['traveler_refs'], 'traveler', true ) ) {
				return self::error( 'component_invalid', 'Itinerary components must be unique, bounded, and use closed verticals and references.' );
			}
			if ( $component['sequence'] <= $previous_sequence ) {
				return self::error( 'component_order_invalid', 'Components must be sealed in strictly increasing itinerary-sequence order.' );
			}
			foreach ( $component['traveler_refs'] as $traveler_ref ) {
				if ( ! isset( $travelers[ $traveler_ref ] ) ) {
					return self::error( 'component_traveler_unknown', 'A component cannot refer to a traveler outside the exact party universe.' );
				}
			}
			$map[ $component['component_ref'] ] = $component;
			$sequences[ $component['sequence'] ] = true;
			$previous_sequence = $component['sequence'];
		}
		foreach ( $map as $component ) {
			foreach ( $component['dependency_refs'] as $dependency_ref ) {
				if ( ! isset( $map[ $dependency_ref ] ) || $dependency_ref === $component['component_ref'] ) {
					return self::error( 'component_dependency_invalid', 'Dependencies must refer to another component inside the exact trip.' );
				}
			}
		}
		$order = self::topological_order( array_values( $map ) );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		return $map;
	}

	private static function changes( $changes, $components, $travelers, $now ) {
		if ( ! self::is_list( $changes ) || ! $changes || count( $changes ) > self::MAX_CHANGES ) {
			return self::error( 'changes_invalid', 'Change observations must be a non-empty bounded ordered list.' );
		}
		$refs = array();
		$ordered_change_refs = array();
		foreach ( $changes as $change ) {
			$keys = array( 'change_ref', 'type', 'component_refs', 'traveler_refs', 'truth_state', 'observed_at', 'source_expires_at', 'evidence_digest' );
			if ( ! self::exact_object( $change, $keys ) || ! self::ref( $change['change_ref'], 'trip_change' ) || isset( $refs[ $change['change_ref'] ] ) || ! in_array( $change['type'], self::CHANGE_TYPES, true ) || ! self::ref_list( $change['component_refs'], 'component', true ) || ! self::ref_list( $change['traveler_refs'], 'traveler', true ) || ! in_array( $change['truth_state'], self::TRUTH_STATES, true ) || ! self::utc( $change['observed_at'] ) || $change['observed_at'] > $now || ( null !== $change['source_expires_at'] && ! self::utc( $change['source_expires_at'] ) ) || ! self::nullable_digest( $change['evidence_digest'] ) ) {
				return self::error( 'change_invalid', 'A change observation is malformed, future-dated, duplicated, or outside the closed truth vocabulary.' );
			}
			foreach ( $change['component_refs'] as $component_ref ) {
				if ( ! isset( $components[ $component_ref ] ) ) {
					return self::error( 'change_component_unknown', 'A change cannot touch a component outside the exact trip.' );
				}
			}
			foreach ( $change['traveler_refs'] as $traveler_ref ) {
				if ( ! isset( $travelers[ $traveler_ref ] ) ) {
					return self::error( 'change_traveler_unknown', 'A change cannot touch a traveler outside the exact party.' );
				}
				$owned = false;
				foreach ( $change['component_refs'] as $component_ref ) {
					if ( in_array( $traveler_ref, $components[ $component_ref ]['traveler_refs'], true ) ) {
						$owned = true;
						break;
					}
				}
				if ( ! $owned ) {
					return self::error( 'change_traveler_ownership_invalid', 'A changed traveler must belong to at least one component in that exact change scope.' );
				}
			}
			if ( 'verified_current' === $change['truth_state'] && ( ! self::digest( $change['evidence_digest'] ) || null === $change['source_expires_at'] || $change['source_expires_at'] <= $now ) ) {
				return self::error( 'change_current_evidence_invalid', 'Current supplier truth requires unexpired digest evidence.' );
			}
			if ( 'stale' === $change['truth_state'] && ( ! self::digest( $change['evidence_digest'] ) || null === $change['source_expires_at'] || $change['source_expires_at'] > $now ) ) {
				return self::error( 'change_stale_evidence_invalid', 'Stale truth must retain proof of its expired source window.' );
			}
			if ( in_array( $change['truth_state'], array( 'observed_unverified', 'unavailable' ), true ) && ( null !== $change['source_expires_at'] || null !== $change['evidence_digest'] ) ) {
				return self::error( 'change_unverified_evidence_invalid', 'Unverified or unavailable observations cannot carry authoritative expiry or evidence.' );
			}
			$refs[ $change['change_ref'] ] = true;
			$ordered_change_refs[] = $change['change_ref'];
		}
		$sorted_change_refs = $ordered_change_refs;
		sort( $sorted_change_refs, SORT_STRING );
		if ( $ordered_change_refs !== $sorted_change_refs ) {
			return self::error( 'change_order_invalid', 'Change observations must be sealed in canonical reference order.' );
		}
		return $refs;
	}

	private static function source_input( $source, $scenario_type, $now ) {
		$keys = array( 'applicability', 'source_code', 'state', 'checked_at', 'expires_at', 'evidence_digest' );
		if ( ! self::exact_object( $source, $keys ) || ! in_array( $source['applicability'], array( 'required', 'not_applicable' ), true ) || ! in_array( $source['state'], array( 'stale', 'unavailable', 'not_applicable' ), true ) || ! self::utc( $source['checked_at'] ) || $source['checked_at'] > $now || ( null !== $source['expires_at'] && ! self::utc( $source['expires_at'] ) ) || ! self::nullable_digest( $source['evidence_digest'] ) ) {
			return self::error( 'source_input_invalid', 'Official transit source input must use a closed freshness contract.' );
		}
		if ( 'israel_gtfs_degraded' !== $scenario_type ) {
			if ( 'not_applicable' !== $source['applicability'] || 'not_applicable' !== $source['source_code'] || 'not_applicable' !== $source['state'] || null !== $source['expires_at'] || null !== $source['evidence_digest'] ) {
				return self::error( 'source_input_orphaned', 'Non-transit scenarios cannot carry an official GTFS source assertion.' );
			}
			return $source;
		}
		if ( 'required' !== $source['applicability'] || 'israel_ministry_transport_gtfs' !== $source['source_code'] || ! in_array( $source['state'], array( 'stale', 'unavailable' ), true ) ) {
			return self::error( 'gtfs_source_required', 'Israel transit degradation requires an explicit stale or unavailable official GTFS source state.' );
		}
		if ( 'stale' === $source['state'] && ( ! self::digest( $source['evidence_digest'] ) || null === $source['expires_at'] || $source['expires_at'] > $source['checked_at'] ) ) {
			return self::error( 'gtfs_stale_evidence_invalid', 'A stale GTFS state must prove an expired official-source window.' );
		}
		if ( 'unavailable' === $source['state'] && ( null !== $source['expires_at'] || null !== $source['evidence_digest'] ) ) {
			return self::error( 'gtfs_unavailable_evidence_invalid', 'An unavailable GTFS state cannot invent source evidence or an expiry window.' );
		}
		return $source;
	}

	private static function scenario_input_rules( $input, $components, $travelers ) {
		$types = array_column( $input['changes'], 'type' );
		$scenario = $input['scenario_type'];
		if ( 'overlapping_connection_disruptions' === $scenario ) {
			$expected = array( 'flight_connection_delayed', 'flight_connection_missed', 'flight_connection_cancelled' );
			$actual = $types;
			sort( $expected, SORT_STRING );
			sort( $actual, SORT_STRING );
			$direct_components = self::change_scope( $input['changes'], 'component_refs' );
			if ( 3 !== count( $input['changes'] ) || 3 !== count( $direct_components ) || $actual !== $expected ) {
				return self::error( 'overlap_changes_invalid', 'The overlap stress case requires exactly three distinct connection disruptions.' );
			}
			foreach ( $input['changes'] as $change ) {
				if ( 1 !== count( $change['component_refs'] ) ) {
					return self::error( 'overlap_change_scope_invalid', 'Each overlapping disruption must bind one distinct flight component.' );
				}
				foreach ( $change['component_refs'] as $ref ) {
					if ( 'flight' !== $components[ $ref ]['vertical'] ) {
						return self::error( 'overlap_vertical_invalid', 'Connection disruption observations may target only flight components.' );
					}
				}
			}
		}
		if ( 'flight_only_change' === $scenario ) {
			if ( 1 !== count( $input['changes'] ) || array( 'flight_schedule_changed' ) !== $types ) {
				return self::error( 'flight_only_change_invalid', 'Flight-only stress requires exactly one schedule change.' );
			}
			foreach ( $input['changes'][0]['component_refs'] as $ref ) {
				if ( 'flight' !== $components[ $ref ]['vertical'] ) {
					return self::error( 'flight_only_scope_invalid', 'A flight-only change cannot target lodging, insurance, ground, or another vertical.' );
				}
			}
		}
		if ( 'five_person_package_constraints' === $scenario ) {
			if ( 5 !== count( $travelers ) || 1 !== count( $input['changes'] ) || array( 'package_party_scope_changed' ) !== $types ) {
				return self::error( 'five_person_scope_invalid', 'The party constraint case requires exactly five travelers and one explicit package-scope change.' );
			}
		}
		if ( 'aircraft_or_terminal_change' === $scenario ) {
			$allowed = array( 'flight_aircraft_changed', 'flight_terminal_changed' );
			if ( ! array_diff( $types, $allowed ) && ! array_diff( $allowed, $types ) && 2 === count( $types ) ) {
				return true;
			}
			return self::error( 'aircraft_terminal_change_invalid', 'Aircraft and terminal stress requires both explicit change observations.' );
		}
		if ( 'israel_gtfs_degraded' === $scenario ) {
			if ( 1 !== count( $types ) || array( 'israel_gtfs_source_degraded' ) !== $types ) {
				return self::error( 'gtfs_change_invalid', 'GTFS degradation requires one explicit official-source change observation.' );
			}
			$change = $input['changes'][0];
			$source = $input['official_transit_source'];
			if ( $change['truth_state'] !== $source['state'] || $change['observed_at'] !== $source['checked_at'] || $change['source_expires_at'] !== $source['expires_at'] || $change['evidence_digest'] !== $source['evidence_digest'] ) {
				return self::error( 'gtfs_truth_binding_invalid', 'GTFS change truth must bind the exact stale or unavailable official-source observation.' );
			}
		}
		return true;
	}

	private static function partition( $partition, $expected_universe, $kind ) {
		$keys = array( 'universe_refs', 'affected_refs', 'preserved_refs', 'blocked_refs' );
		$prefix = 'component' === $kind ? 'component' : 'traveler';
		if ( ! self::exact_object( $partition, $keys ) || ! self::ref_list( $partition['universe_refs'], $prefix, true ) || ! self::ref_list( $partition['affected_refs'], $prefix, false ) || ! self::ref_list( $partition['preserved_refs'], $prefix, false ) || ! self::ref_list( $partition['blocked_refs'], $prefix, false ) || ! self::same_members( $partition['universe_refs'], $expected_universe ) ) {
			return self::error( $kind . '_partition_invalid', 'The ' . $kind . ' partition is malformed or does not bind the complete universe.' );
		}
		$merged = array_merge( $partition['affected_refs'], $partition['preserved_refs'], $partition['blocked_refs'] );
		if ( count( $merged ) !== count( array_unique( $merged ) ) || ! self::same_members( $merged, $partition['universe_refs'] ) ) {
			return self::error( $kind . '_partition_overlap', 'Affected, preserved, and blocked ' . $kind . ' references must be disjoint and exhaustive.' );
		}
		return $partition;
	}

	private static function candidates( $candidates, $selected_ref, $input, $component_partition ) {
		if ( ! self::is_list( $candidates ) || ! $candidates || count( $candidates ) !== count( self::strategies_for( $input['scenario_type'] ) ) || ! self::ref( $selected_ref, 'recovery_candidate' ) ) {
			return self::error( 'candidate_set_invalid', 'Every scenario requires its exact bounded recovery candidate set and one selected candidate.' );
		}
		$seen = array();
		$selected_count = 0;
		$strategies = array();
		$scope = array_merge( $component_partition['affected_refs'], $component_partition['blocked_refs'] );
		foreach ( $candidates as $candidate ) {
			$keys = array( 'candidate_ref', 'strategy', 'target_component_refs', 'state', 'execution_authorized', 'supplier_action_claimed', 'commercial_fact_claimed', 'authorization_effect' );
			if ( ! self::exact_object( $candidate, $keys ) || ! self::ref( $candidate['candidate_ref'], 'recovery_candidate' ) || isset( $seen[ $candidate['candidate_ref'] ] ) || ! in_array( $candidate['strategy'], self::strategies_for( $input['scenario_type'] ), true ) || ! self::ref_list( $candidate['target_component_refs'], 'component', true ) || ! self::same_members( $candidate['target_component_refs'], $scope ) || ! in_array( $candidate['state'], array( 'selected_for_review', 'planning_alternative' ), true ) || false !== $candidate['execution_authorized'] || false !== $candidate['supplier_action_claimed'] || false !== $candidate['commercial_fact_claimed'] || 'none' !== $candidate['authorization_effect'] ) {
				return self::error( 'candidate_invalid', 'Recovery candidates must be deterministic, scoped, planning-only, and commercially inert.' );
			}
			$expected_ref = self::ref_value( 'recovery_candidate', $input['scenario_ref'] . '|' . $candidate['strategy'] );
			if ( $expected_ref !== $candidate['candidate_ref'] ) {
				return self::error( 'candidate_identity_invalid', 'Candidate identity must derive from scenario and strategy.' );
			}
			$is_selected = 'selected_for_review' === $candidate['state'];
			$selected_count += $is_selected ? 1 : 0;
			if ( $is_selected && ( $candidate['candidate_ref'] !== $selected_ref || $candidate['strategy'] !== $input['selected_strategy'] ) ) {
				return self::error( 'candidate_selection_invalid', 'The sole selected candidate must match the explicit planning strategy.' );
			}
			$seen[ $candidate['candidate_ref'] ] = true;
			$strategies[] = $candidate['strategy'];
		}
		if ( 1 !== $selected_count || $strategies !== self::strategies_for( $input['scenario_type'] ) ) {
			return self::error( 'candidate_selection_count_invalid', 'Exactly one complete recovery option must be selected for review.' );
		}
		return $candidates;
	}

	private static function actions( $actions, $component_refs, $traveler_refs, $component_partition, $traveler_partition ) {
		if ( ! self::is_list( $actions ) || ! $actions || count( $actions ) > 24 ) {
			return self::error( 'actions_invalid', 'Action plan must be non-empty and bounded.' );
		}
		$seen = array();
		$ordered_action_refs = array();
		$mutable_components = array_merge( $component_partition['affected_refs'], $component_partition['blocked_refs'] );
		$mutable_travelers = array_merge( $traveler_partition['affected_refs'], $traveler_partition['blocked_refs'] );
		foreach ( $actions as $action ) {
			$keys = array( 'action_ref', 'code', 'component_refs', 'traveler_refs', 'state', 'blocker_codes', 'authorization_effect', 'supplier_action_claimed' );
			if ( ! self::exact_object( $action, $keys ) || ! self::ref( $action['action_ref'], 'stress_action' ) || isset( $seen[ $action['action_ref'] ] ) || ! in_array( $action['code'], self::ACTION_CODES, true ) || ! self::ref_list( $action['component_refs'], 'component', false ) || ! self::ref_list( $action['traveler_refs'], 'traveler', false ) || ! self::subset( $action['component_refs'], $component_refs ) || ! self::subset( $action['traveler_refs'], $traveler_refs ) || ! self::subset( $action['component_refs'], $mutable_components ) || ! self::subset( $action['traveler_refs'], $mutable_travelers ) || ! in_array( $action['state'], array( 'planned', 'blocked', 'informational' ), true ) || ! self::closed_list( $action['blocker_codes'], self::BLOCKER_CODES, true ) || 'none' !== $action['authorization_effect'] || false !== $action['supplier_action_claimed'] ) {
				return self::error( 'action_invalid', 'Actions must remain inside affected or blocked scope with a closed, non-executing state.' );
			}
			if ( 'blocked' === $action['state'] xor ! empty( $action['blocker_codes'] ) ) {
				return self::error( 'action_blocker_invalid', 'Only a blocked action may carry one or more explicit blocker codes.' );
			}
			$expected_ref = self::ref_value( 'stress_action', $action['code'] . '|' . implode( '|', $action['component_refs'] ) . '|' . implode( '|', $action['traveler_refs'] ) );
			if ( $expected_ref !== $action['action_ref'] ) {
				return self::error( 'action_identity_invalid', 'Action identity must derive from its exact minimized scope.' );
			}
			$seen[ $action['action_ref'] ] = true;
			$ordered_action_refs[] = $action['action_ref'];
		}
		$sorted_action_refs = $ordered_action_refs;
		sort( $sorted_action_refs, SORT_STRING );
		if ( $ordered_action_refs !== $sorted_action_refs ) {
			return self::error( 'action_order_invalid', 'Actions must use canonical reference order before sealing.' );
		}
		return $actions;
	}

	private static function rechecks( $rechecks, $input, $partition ) {
		if ( ! self::is_list( $rechecks ) ) {
			return self::error( 'rechecks_invalid', 'Required rechecks must be an ordered list.' );
		}
		if ( 'aircraft_or_terminal_change' !== $input['scenario_type'] ) {
			return empty( $rechecks ) ? $rechecks : self::error( 'rechecks_orphaned', 'Only an aircraft or terminal change may create this exact recheck bundle.' );
		}
		$codes = array();
		foreach ( $rechecks as $recheck ) {
			$keys = array( 'code', 'component_refs', 'traveler_refs', 'state', 'truth_state', 'execution_authorized', 'supplier_action_claimed' );
			if ( ! self::exact_object( $recheck, $keys ) || ! in_array( $recheck['code'], self::RECHECK_CODES, true ) || ! self::ref_list( $recheck['component_refs'], 'component', true ) || ! self::ref_list( $recheck['traveler_refs'], 'traveler', true ) || ! self::same_members( $recheck['component_refs'], $partition['affected_refs'] ) || 'required' !== $recheck['state'] || 'pending_supplier_verification' !== $recheck['truth_state'] || false !== $recheck['execution_authorized'] || false !== $recheck['supplier_action_claimed'] ) {
				return self::error( 'recheck_invalid', 'Aircraft and terminal consequences must remain a complete pending-verification bundle.' );
			}
			$codes[] = $recheck['code'];
		}
		if ( $codes !== self::RECHECK_CODES ) {
			return self::error( 'recheck_bundle_incomplete', 'Seat, SSR, wheelchair, baggage, and minimum connection time must all be rechecked in canonical order.' );
		}
		return $rechecks;
	}

	private static function source_gate( $gate, $source, $scenario_type ) {
		$keys = array( 'applicability', 'source_code', 'source_state', 'checked_at', 'expires_at', 'evidence_digest', 'freshness_gate_state', 'route_claim_allowed', 'current_route_claim_present', 'fallback_channels', 'human_review_required' );
		if ( ! self::exact_object( $gate, $keys ) || $gate['applicability'] !== $source['applicability'] || $gate['source_code'] !== $source['source_code'] || $gate['source_state'] !== $source['state'] || $gate['checked_at'] !== $source['checked_at'] || $gate['expires_at'] !== $source['expires_at'] || $gate['evidence_digest'] !== $source['evidence_digest'] || false !== $gate['current_route_claim_present'] ) {
			return self::error( 'source_gate_invalid', 'Transit source gate must preserve the exact official-source observation without inventing a route.' );
		}
		if ( 'israel_gtfs_degraded' !== $scenario_type ) {
			if ( 'not_applicable' !== $gate['freshness_gate_state'] || false !== $gate['route_claim_allowed'] || array() !== $gate['fallback_channels'] || false !== $gate['human_review_required'] ) {
				return self::error( 'source_gate_orphaned', 'Non-transit plans cannot carry transit routing consequences.' );
			}
			return $gate;
		}
		$blocker = 'stale' === $source['state'] ? 'official_source_stale' : 'official_source_unavailable';
		if ( 'failed_closed' !== $gate['freshness_gate_state'] || false !== $gate['route_claim_allowed'] || self::OFFICIAL_FALLBACK_CHANNELS !== $gate['fallback_channels'] || true !== $gate['human_review_required'] || ! in_array( $blocker, self::BLOCKER_CODES, true ) ) {
			return self::error( 'gtfs_source_gate_invalid', 'Stale or unavailable GTFS must fail closed and expose official/human fallback channels without route claims.' );
		}
		return $gate;
	}

	private static function scenario_plan_rules( $plan, $input ) {
		$direct_components = self::change_scope( $input['changes'], 'component_refs' );
		$direct_travelers = self::change_scope( $input['changes'], 'traveler_refs' );
		$scenario = $input['scenario_type'];
		if ( 'flight_only_change' === $scenario ) {
			if ( ! self::same_members( $plan['component_partition']['affected_refs'], $direct_components ) || $plan['component_partition']['blocked_refs'] || ! self::same_members( $plan['traveler_partition']['affected_refs'], $direct_travelers ) || $plan['traveler_partition']['blocked_refs'] ) {
				return self::error( 'flight_only_partition_invalid', 'A flight-only change must affect only its exact flight and travelers.' );
			}
			$components = self::index_by( $input['components'], 'component_ref' );
			foreach ( array_merge( $plan['component_partition']['affected_refs'], $plan['component_partition']['blocked_refs'] ) as $ref ) {
				if ( 'flight' !== $components[ $ref ]['vertical'] ) {
					return self::error( 'flight_only_cross_vertical_invalid', 'Flight-only planning cannot touch hotel, insurance, ground, or any other vertical.' );
				}
			}
		}
		if ( 'overlapping_connection_disruptions' === $scenario ) {
			$expected_affected_components = self::downstream_scope( $direct_components, $input['components'] );
			$expected_preserved_components = array_values( array_diff( self::ordered_refs( $input['components'], 'component_ref' ), $expected_affected_components ) );
			$expected_affected_travelers = self::traveler_refs_for_components( $expected_affected_components, $input['components'] );
			$expected_preserved_travelers = array_values( array_diff( self::ordered_refs( $input['travelers'], 'traveler_ref' ), $expected_affected_travelers ) );
			if ( $plan['component_partition']['blocked_refs'] || $plan['traveler_partition']['blocked_refs'] || ! self::same_members( $plan['component_partition']['affected_refs'], $expected_affected_components ) || ! self::same_members( $plan['component_partition']['preserved_refs'], $expected_preserved_components ) || ! self::same_members( $plan['traveler_partition']['affected_refs'], $expected_affected_travelers ) || ! self::same_members( $plan['traveler_partition']['preserved_refs'], $expected_preserved_travelers ) ) {
				return self::error( 'overlap_partition_invalid', 'Connection recovery must preserve viable components and avoid inventing blocked execution.' );
			}
		}
		if ( 'five_person_package_constraints' === $scenario ) {
			$expected_blocked = self::constraint_blocked_travelers( $input['travelers'], $direct_travelers );
			$expected_affected = array_values( array_diff( $direct_travelers, $expected_blocked ) );
			$expected_preserved = array_values( array_diff( self::ordered_refs( $input['travelers'], 'traveler_ref' ), $direct_travelers ) );
			$component_map = self::index_by( $input['components'], 'component_ref' );
			$expected_blocked_components = array();
			foreach ( $direct_components as $component_ref ) {
				if ( array_intersect( $component_map[ $component_ref ]['traveler_refs'], $expected_blocked ) ) {
					$expected_blocked_components[] = $component_ref;
				}
			}
			sort( $expected_blocked_components, SORT_STRING );
			$expected_affected_components = array_values( array_diff( $direct_components, $expected_blocked_components ) );
			$expected_preserved_components = array_values( array_diff( self::ordered_refs( $input['components'], 'component_ref' ), $direct_components ) );
			if ( ! self::same_members( $plan['traveler_partition']['blocked_refs'], $expected_blocked ) || ! self::same_members( $plan['traveler_partition']['affected_refs'], $expected_affected ) || ! self::same_members( $plan['traveler_partition']['preserved_refs'], $expected_preserved ) || ! self::same_members( $plan['component_partition']['blocked_refs'], $expected_blocked_components ) || ! self::same_members( $plan['component_partition']['affected_refs'], $expected_affected_components ) || ! self::same_members( $plan['component_partition']['preserved_refs'], $expected_preserved_components ) ) {
				return self::error( 'party_constraint_partition_invalid', 'Only targeted travelers with an actual constraint may be blocked; all others must remain affected or preserved exactly.' );
			}
			$action_check = self::five_person_action_scope( $plan['actions'], $input, $expected_affected_components, $expected_blocked_components, $expected_affected, $expected_blocked );
			if ( is_wp_error( $action_check ) ) {
				return $action_check;
			}
		}
		if ( 'aircraft_or_terminal_change' === $scenario && ( ! self::same_members( $plan['component_partition']['affected_refs'], $direct_components ) || $plan['component_partition']['blocked_refs'] ) ) {
			return self::error( 'aircraft_terminal_partition_invalid', 'Aircraft or terminal changes require verification, not a supplier-execution claim or unrelated impact.' );
		}
		if ( 'israel_gtfs_degraded' === $scenario && ( ! self::same_members( $plan['component_partition']['blocked_refs'], $direct_components ) || $plan['component_partition']['affected_refs'] ) ) {
			return self::error( 'gtfs_partition_invalid', 'Degraded official transit data must block only the exact route claim scope.' );
		}
		return true;
	}

	private static function summary( $summary, $plan ) {
		$keys = array( 'component_count', 'affected_component_count', 'preserved_component_count', 'blocked_component_count', 'traveler_count', 'affected_traveler_count', 'preserved_traveler_count', 'blocked_traveler_count', 'candidate_count', 'selected_candidate_count', 'required_recheck_count', 'side_effect_count' );
		return self::exact_object( $summary, $keys )
			&& count( $plan['component_partition']['universe_refs'] ) === $summary['component_count']
			&& count( $plan['component_partition']['affected_refs'] ) === $summary['affected_component_count']
			&& count( $plan['component_partition']['preserved_refs'] ) === $summary['preserved_component_count']
			&& count( $plan['component_partition']['blocked_refs'] ) === $summary['blocked_component_count']
			&& count( $plan['traveler_partition']['universe_refs'] ) === $summary['traveler_count']
			&& count( $plan['traveler_partition']['affected_refs'] ) === $summary['affected_traveler_count']
			&& count( $plan['traveler_partition']['preserved_refs'] ) === $summary['preserved_traveler_count']
			&& count( $plan['traveler_partition']['blocked_refs'] ) === $summary['blocked_traveler_count']
			&& count( $plan['recovery_candidates'] ) === $summary['candidate_count']
			&& 1 === $summary['selected_candidate_count']
			&& count( $plan['required_rechecks'] ) === $summary['required_recheck_count']
			&& 0 === $summary['side_effect_count'];
	}

	private static function five_person_action_scope( $actions, $input, $affected_components, $blocked_components, $affected_travelers, $blocked_travelers ) {
		$traveler_map = self::index_by( $input['travelers'], 'traveler_ref' );
		$code_map = array(
			'eligibility_not_verified'  => 'review_party_eligibility',
			'consent_pending'            => 'review_party_consent',
			'guardian_authority_missing' => 'review_guardian_authority',
			'accessibility_ack_pending'  => 'review_accessibility_acknowledgement',
		);
		$expected = array();
		foreach ( $blocked_travelers as $traveler_ref ) {
			$owned_components = self::component_refs_for_traveler( $blocked_components, $input['components'], $traveler_ref );
			foreach ( self::constraint_blockers( $traveler_map[ $traveler_ref ] ) as $blocker ) {
				$expected[] = self::action_scope_signature( $code_map[ $blocker ], $owned_components, array( $traveler_ref ), 'blocked', array( $blocker ) );
			}
		}
		foreach ( $affected_travelers as $traveler_ref ) {
			$owned_components = self::component_refs_for_traveler( $affected_components, $input['components'], $traveler_ref );
			if ( $owned_components ) {
				$expected[] = self::action_scope_signature( 'review_party_eligibility', $owned_components, array( $traveler_ref ), 'informational', array() );
			}
		}
		$actual = array();
		foreach ( $actions as $action ) {
			$actual[] = self::action_scope_signature( $action['code'], $action['component_refs'], $action['traveler_refs'], $action['state'], $action['blocker_codes'] );
		}
		sort( $expected, SORT_STRING );
		sort( $actual, SORT_STRING );
		if ( $actual !== $expected ) {
			return self::error( 'party_action_ownership_invalid', 'Each five-person action must target only the exact components owned by its one traveler and actual constraint.' );
		}
		return true;
	}

	private static function action_scope_signature( $code, $component_refs, $traveler_refs, $state, $blocker_codes ) {
		return $code . '|' . implode( ',', $component_refs ) . '|' . implode( ',', $traveler_refs ) . '|' . $state . '|' . implode( ',', $blocker_codes );
	}

	private static function downstream_scope( $direct_refs, $components ) {
		$order = self::topological_order( $components );
		if ( is_wp_error( $order ) ) {
			return array();
		}
		$map = self::index_by( $components, 'component_ref' );
		$scope = array_fill_keys( $direct_refs, true );
		foreach ( $order as $component_ref ) {
			if ( isset( $scope[ $component_ref ] ) ) {
				continue;
			}
			foreach ( $map[ $component_ref ]['dependency_refs'] as $dependency_ref ) {
				if ( isset( $scope[ $dependency_ref ] ) ) {
					$scope[ $component_ref ] = true;
					break;
				}
			}
		}
		$refs = array_keys( $scope );
		sort( $refs, SORT_STRING );
		return $refs;
	}

	private static function traveler_refs_for_components( $component_refs, $components ) {
		$map = self::index_by( $components, 'component_ref' );
		$refs = array();
		foreach ( $component_refs as $component_ref ) {
			$refs = array_merge( $refs, $map[ $component_ref ]['traveler_refs'] );
		}
		$refs = array_values( array_unique( $refs ) );
		sort( $refs, SORT_STRING );
		return $refs;
	}

	private static function component_refs_for_traveler( $component_refs, $components, $traveler_ref ) {
		$map = self::index_by( $components, 'component_ref' );
		$owned = array();
		foreach ( $component_refs as $component_ref ) {
			if ( in_array( $traveler_ref, $map[ $component_ref ]['traveler_refs'], true ) ) {
				$owned[] = $component_ref;
			}
		}
		sort( $owned, SORT_STRING );
		return $owned;
	}

	private static function input_boundary( $boundary ) {
		$keys = array( 'raw_identity_present', 'raw_document_present', 'raw_medical_present', 'raw_supplier_payload_present', 'payment_data_present', 'bearer_secret_present' );
		if ( ! self::exact_object( $boundary, $keys ) ) {
			return false;
		}
		foreach ( $keys as $key ) {
			if ( false !== $boundary[ $key ] ) {
				return false;
			}
		}
		return true;
	}

	private static function private_boundary( $boundary ) {
		$keys = array( 'server_only', 'planning_only', 'public_serialization_allowed', 'storage_written', 'rest_route_registered', 'network_called', 'provider_called', 'supplier_dispatched', 'payment_executed', 'booking_modified', 'route_availability_claimed', 'commercial_authority', 'authorization_effect', 'side_effect_count' );
		return self::exact_object( $boundary, $keys )
			&& true === $boundary['server_only']
			&& true === $boundary['planning_only']
			&& false === $boundary['public_serialization_allowed']
			&& false === $boundary['storage_written']
			&& false === $boundary['rest_route_registered']
			&& false === $boundary['network_called']
			&& false === $boundary['provider_called']
			&& false === $boundary['supplier_dispatched']
			&& false === $boundary['payment_executed']
			&& false === $boundary['booking_modified']
			&& false === $boundary['route_availability_claimed']
			&& false === $boundary['commercial_authority']
			&& 'none' === $boundary['authorization_effect']
			&& 0 === $boundary['side_effect_count'];
	}

	public static function topological_order( $components ) {
		$map = self::index_by( $components, 'component_ref' );
		$indegree = array();
		$dependents = array();
		foreach ( $components as $component ) {
			$indegree[ $component['component_ref'] ] = count( $component['dependency_refs'] );
			foreach ( $component['dependency_refs'] as $dependency_ref ) {
				if ( ! isset( $dependents[ $dependency_ref ] ) ) {
					$dependents[ $dependency_ref ] = array();
				}
				$dependents[ $dependency_ref ][] = $component['component_ref'];
			}
		}
		$order = array();
		while ( count( $order ) < count( $components ) ) {
			$ready = array();
			foreach ( $indegree as $ref => $value ) {
				if ( 0 === $value && ! in_array( $ref, $order, true ) ) {
					$ready[] = $ref;
				}
			}
			if ( ! $ready ) {
				return self::error( 'component_dependency_cycle', 'Trip component dependencies cannot contain a cycle.' );
			}
			usort(
				$ready,
				function ( $left, $right ) use ( $map ) {
					if ( $map[ $left ]['sequence'] === $map[ $right ]['sequence'] ) {
						return strcmp( $left, $right );
					}
					return $map[ $left ]['sequence'] < $map[ $right ]['sequence'] ? -1 : 1;
				}
			);
			$next = $ready[0];
			$order[] = $next;
			foreach ( isset( $dependents[ $next ] ) ? $dependents[ $next ] : array() as $dependent ) {
				--$indegree[ $dependent ];
			}
		}
		return $order;
	}

	private static function dependency_order_valid( $order, $components ) {
		$positions = array_flip( $order );
		foreach ( $components as $component ) {
			foreach ( $component['dependency_refs'] as $dependency_ref ) {
				if ( $positions[ $dependency_ref ] >= $positions[ $component['component_ref'] ] ) {
					return false;
				}
			}
		}
		return $order === self::topological_order( $components );
	}

	public static function change_scope( $changes, $field ) {
		$refs = array();
		foreach ( $changes as $change ) {
			$refs = array_merge( $refs, $change[ $field ] );
		}
		$refs = array_values( array_unique( $refs ) );
		sort( $refs, SORT_STRING );
		return $refs;
	}

	public static function constraint_blockers( $traveler ) {
		$blockers = array();
		if ( 'eligible' !== $traveler['eligibility_state'] ) {
			$blockers[] = 'eligibility_not_verified';
		}
		if ( 'pending' === $traveler['consent_state'] ) {
			$blockers[] = 'consent_pending';
		}
		if ( $traveler['is_minor'] && 'verified' !== $traveler['guardian_authority_state'] ) {
			$blockers[] = 'guardian_authority_missing';
		}
		if ( $traveler['accessibility_need_codes'] && 'verified' !== $traveler['accessibility_ack_state'] ) {
			$blockers[] = 'accessibility_ack_pending';
		}
		return $blockers;
	}

	private static function constraint_blocked_travelers( $travelers, $target_refs ) {
		$blocked = array();
		foreach ( $travelers as $traveler ) {
			if ( in_array( $traveler['traveler_ref'], $target_refs, true ) && self::constraint_blockers( $traveler ) ) {
				$blocked[] = $traveler['traveler_ref'];
			}
		}
		sort( $blocked, SORT_STRING );
		return $blocked;
	}

	private static function ordered_refs( $items, $field ) {
		$refs = array_column( $items, $field );
		sort( $refs, SORT_STRING );
		return $refs;
	}

	private static function index_by( $items, $field ) {
		$map = array();
		foreach ( $items as $item ) {
			$map[ $item[ $field ] ] = $item;
		}
		return $map;
	}

	private static function same_members( $left, $right ) {
		if ( ! self::is_list( $left ) || ! self::is_list( $right ) || count( $left ) !== count( array_unique( $left ) ) || count( $right ) !== count( array_unique( $right ) ) ) {
			return false;
		}
		$left_copy = $left;
		$right_copy = $right;
		sort( $left_copy, SORT_STRING );
		sort( $right_copy, SORT_STRING );
		return $left_copy === $right_copy;
	}

	private static function subset( $candidate, $universe ) {
		return ! array_diff( $candidate, $universe );
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! self::is_list( $value ) && array_keys( $value ) === $keys;
	}

	private static function is_list( $value ) {
		return is_array( $value ) && array_values( $value ) === $value;
	}

	private static function ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[a-z0-9]{8,48}$/', $value );
	}

	private static function ref_list( $values, $kind, $required ) {
		if ( ! self::is_list( $values ) || ( $required && ! $values ) || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! self::ref( $value, $kind ) ) {
				return false;
			}
		}
		$sorted = $values;
		sort( $sorted, SORT_STRING );
		return $values === $sorted;
	}

	private static function closed_list( $values, $allowed, $may_be_empty ) {
		return self::is_list( $values )
			&& ( $may_be_empty || ! empty( $values ) )
			&& count( $values ) === count( array_unique( $values ) )
			&& ! array_diff( $values, $allowed )
			&& $values === array_values( array_intersect( $allowed, $values ) );
	}

	private static function positive_int( $value ) {
		return is_int( $value ) && $value > 0;
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function nullable_digest( $value ) {
		return null === $value || self::digest( $value );
	}

	private static function utc( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/', $value, $parts ) ) {
			return false;
		}
		if ( ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
			return false;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s\Z', $value, new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();
		if ( false === $date || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
			return false;
		}
		return $date->format( 'Y-m-d\TH:i:s\Z' ) === $value;
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( self::is_list( $value ) ) {
			return array_map( array( __CLASS__, 'canonicalize' ), $value );
		}
		ksort( $value, SORT_STRING );
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}
		return $value;
	}
}
