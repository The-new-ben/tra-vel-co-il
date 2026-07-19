<?php
/**
 * Pure deterministic planner for adversarial trip-change stress scenarios.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Trip_Change_Stress_Engine {
	/**
	 * Produce one sealed, non-executing plan.
	 *
	 * @return array|WP_Error
	 */
	public static function plan( $input ) {
		$valid = Tra_Vel_Trip_Change_Stress_Policy::input( $input );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$dependency_order = Tra_Vel_Trip_Change_Stress_Policy::topological_order( $input['components'] );
		if ( is_wp_error( $dependency_order ) ) {
			return $dependency_order;
		}
		$partitions = self::partitions( $input, $dependency_order );
		$candidates = self::candidates( $input, $partitions['components'] );
		$selected_candidate_ref = null;
		foreach ( $candidates as $candidate ) {
			if ( 'selected_for_review' === $candidate['state'] ) {
				$selected_candidate_ref = $candidate['candidate_ref'];
				break;
			}
		}
		$rechecks = self::rechecks( $input, $partitions );
		$actions = self::actions( $input, $partitions, $rechecks );
		$source_gate = self::source_gate( $input['official_transit_source'], $input['scenario_type'] );

		$plan = array(
			'contract_version'       => Tra_Vel_Trip_Change_Stress_Policy::CONTRACT_VERSION,
			'environment'            => 'private_simulation',
			'plan_ref'               => Tra_Vel_Trip_Change_Stress_Policy::ref_value( 'stress_plan', Tra_Vel_Trip_Change_Stress_Policy::canonical_digest( $input ) ),
			'plan_digest'            => str_repeat( '0', 64 ),
			'scenario_ref'           => $input['scenario_ref'],
			'trip_ref'               => $input['trip_ref'],
			'scenario_type'          => $input['scenario_type'],
			'observed_at'            => $input['observed_at'],
			'dependency_order'       => $dependency_order,
			'component_partition'    => $partitions['components'],
			'traveler_partition'     => $partitions['travelers'],
			'recovery_candidates'    => $candidates,
			'selected_candidate_ref' => $selected_candidate_ref,
			'actions'                 => $actions,
			'required_rechecks'       => $rechecks,
			'transit_source_gate'     => $source_gate,
			'summary'                 => array(
				'component_count'            => count( $partitions['components']['universe_refs'] ),
				'affected_component_count'   => count( $partitions['components']['affected_refs'] ),
				'preserved_component_count'  => count( $partitions['components']['preserved_refs'] ),
				'blocked_component_count'    => count( $partitions['components']['blocked_refs'] ),
				'traveler_count'             => count( $partitions['travelers']['universe_refs'] ),
				'affected_traveler_count'    => count( $partitions['travelers']['affected_refs'] ),
				'preserved_traveler_count'   => count( $partitions['travelers']['preserved_refs'] ),
				'blocked_traveler_count'     => count( $partitions['travelers']['blocked_refs'] ),
				'candidate_count'            => count( $candidates ),
				'selected_candidate_count'   => 1,
				'required_recheck_count'     => count( $rechecks ),
				'side_effect_count'          => 0,
			),
			'private_boundary'        => array(
				'server_only'                => true,
				'planning_only'              => true,
				'public_serialization_allowed' => false,
				'storage_written'            => false,
				'rest_route_registered'      => false,
				'network_called'             => false,
				'provider_called'            => false,
				'supplier_dispatched'        => false,
				'payment_executed'           => false,
				'booking_modified'           => false,
				'route_availability_claimed' => false,
				'commercial_authority'       => false,
				'authorization_effect'       => 'none',
				'side_effect_count'          => 0,
			),
		);
		$plan['plan_digest'] = Tra_Vel_Trip_Change_Stress_Policy::plan_digest( $plan );
		return Tra_Vel_Trip_Change_Stress_Policy::plan( $plan, $input );
	}

	private static function partitions( $input, $dependency_order ) {
		$component_universe = array_column( $input['components'], 'component_ref' );
		$traveler_universe = array_column( $input['travelers'], 'traveler_ref' );
		sort( $component_universe, SORT_STRING );
		sort( $traveler_universe, SORT_STRING );
		$direct_components = Tra_Vel_Trip_Change_Stress_Policy::change_scope( $input['changes'], 'component_refs' );
		$direct_travelers = Tra_Vel_Trip_Change_Stress_Policy::change_scope( $input['changes'], 'traveler_refs' );
		$affected_components = $direct_components;
		$blocked_components = array();
		$affected_travelers = $direct_travelers;
		$blocked_travelers = array();

		if ( 'overlapping_connection_disruptions' === $input['scenario_type'] ) {
			$affected_components = self::downstream_scope( $direct_components, $input['components'], $dependency_order );
			$component_map = self::index_by( $input['components'], 'component_ref' );
			$affected_travelers = array();
			foreach ( $affected_components as $component_ref ) {
				$affected_travelers = array_merge( $affected_travelers, $component_map[ $component_ref ]['traveler_refs'] );
			}
		}

		if ( 'five_person_package_constraints' === $input['scenario_type'] ) {
			$traveler_map = self::index_by( $input['travelers'], 'traveler_ref' );
			foreach ( $direct_travelers as $traveler_ref ) {
				if ( Tra_Vel_Trip_Change_Stress_Policy::constraint_blockers( $traveler_map[ $traveler_ref ] ) ) {
					$blocked_travelers[] = $traveler_ref;
				}
			}
			$affected_travelers = array_values( array_diff( $direct_travelers, $blocked_travelers ) );
			$component_map = self::index_by( $input['components'], 'component_ref' );
			foreach ( $direct_components as $component_ref ) {
				if ( array_intersect( $component_map[ $component_ref ]['traveler_refs'], $blocked_travelers ) ) {
					$blocked_components[] = $component_ref;
				}
			}
			$affected_components = array_values( array_diff( $direct_components, $blocked_components ) );
		}

		if ( 'israel_gtfs_degraded' === $input['scenario_type'] ) {
			$blocked_components = $direct_components;
			$affected_components = array();
		}

		foreach ( array( &$affected_components, &$blocked_components, &$affected_travelers, &$blocked_travelers ) as &$refs ) {
			$refs = array_values( array_unique( $refs ) );
			sort( $refs, SORT_STRING );
		}
		unset( $refs );
		$preserved_components = array_values( array_diff( $component_universe, $affected_components, $blocked_components ) );
		$preserved_travelers = array_values( array_diff( $traveler_universe, $affected_travelers, $blocked_travelers ) );
		sort( $preserved_components, SORT_STRING );
		sort( $preserved_travelers, SORT_STRING );

		return array(
			'components' => array(
				'universe_refs'  => $component_universe,
				'affected_refs'  => $affected_components,
				'preserved_refs' => $preserved_components,
				'blocked_refs'   => $blocked_components,
			),
			'travelers'  => array(
				'universe_refs'  => $traveler_universe,
				'affected_refs'  => $affected_travelers,
				'preserved_refs' => $preserved_travelers,
				'blocked_refs'   => $blocked_travelers,
			),
		);
	}

	private static function downstream_scope( $direct_refs, $components, $dependency_order ) {
		$scope = array_fill_keys( $direct_refs, true );
		$map = self::index_by( $components, 'component_ref' );
		foreach ( $dependency_order as $component_ref ) {
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

	private static function candidates( $input, $component_partition ) {
		$scope = array_merge( $component_partition['affected_refs'], $component_partition['blocked_refs'] );
		sort( $scope, SORT_STRING );
		$candidates = array();
		foreach ( Tra_Vel_Trip_Change_Stress_Policy::strategies_for( $input['scenario_type'] ) as $strategy ) {
			$candidates[] = array(
				'candidate_ref'          => Tra_Vel_Trip_Change_Stress_Policy::ref_value( 'recovery_candidate', $input['scenario_ref'] . '|' . $strategy ),
				'strategy'               => $strategy,
				'target_component_refs'  => $scope,
				'state'                  => $strategy === $input['selected_strategy'] ? 'selected_for_review' : 'planning_alternative',
				'execution_authorized'   => false,
				'supplier_action_claimed' => false,
				'commercial_fact_claimed' => false,
				'authorization_effect'   => 'none',
			);
		}
		return $candidates;
	}

	private static function actions( $input, $partitions, $rechecks ) {
		$component_scope = array_merge( $partitions['components']['affected_refs'], $partitions['components']['blocked_refs'] );
		$traveler_scope = array_merge( $partitions['travelers']['affected_refs'], $partitions['travelers']['blocked_refs'] );
		sort( $component_scope, SORT_STRING );
		sort( $traveler_scope, SORT_STRING );
		$actions = array();
		$scenario = $input['scenario_type'];
		if ( 'overlapping_connection_disruptions' === $scenario ) {
			$actions[] = self::action( 'assess_connection_dependency_chain', $component_scope, $traveler_scope, 'planned', array() );
			$actions[] = self::action( 'compare_connection_recovery', $component_scope, $traveler_scope, 'planned', array() );
		}
		if ( 'flight_only_change' === $scenario ) {
			$actions[] = self::action( 'revalidate_changed_flight_only', $component_scope, $traveler_scope, 'planned', array() );
		}
		if ( 'five_person_package_constraints' === $scenario ) {
			$traveler_map = self::index_by( $input['travelers'], 'traveler_ref' );
			$component_map = self::index_by( $input['components'], 'component_ref' );
			$code_map = array(
				'eligibility_not_verified'      => 'review_party_eligibility',
				'consent_pending'                => 'review_party_consent',
				'guardian_authority_missing'     => 'review_guardian_authority',
				'accessibility_ack_pending'      => 'review_accessibility_acknowledgement',
			);
			foreach ( $partitions['travelers']['blocked_refs'] as $traveler_ref ) {
				$owned_components = array();
				foreach ( $partitions['components']['blocked_refs'] as $component_ref ) {
					if ( in_array( $traveler_ref, $component_map[ $component_ref ]['traveler_refs'], true ) ) {
						$owned_components[] = $component_ref;
					}
				}
				sort( $owned_components, SORT_STRING );
				foreach ( Tra_Vel_Trip_Change_Stress_Policy::constraint_blockers( $traveler_map[ $traveler_ref ] ) as $blocker ) {
					$actions[] = self::action( $code_map[ $blocker ], $owned_components, array( $traveler_ref ), 'blocked', array( $blocker ) );
				}
			}
			foreach ( $partitions['travelers']['affected_refs'] as $traveler_ref ) {
				$owned_components = array();
				foreach ( $partitions['components']['affected_refs'] as $component_ref ) {
					if ( in_array( $traveler_ref, $component_map[ $component_ref ]['traveler_refs'], true ) ) {
						$owned_components[] = $component_ref;
					}
				}
				if ( $owned_components ) {
					$actions[] = self::action( 'review_party_eligibility', $owned_components, array( $traveler_ref ), 'informational', array() );
				}
			}
		}
		if ( 'aircraft_or_terminal_change' === $scenario ) {
			$action_map = array(
				'seat_assignment'          => 'recheck_seat_assignment',
				'special_service_request'  => 'recheck_special_service_request',
				'wheelchair_assistance'     => 'recheck_wheelchair_assistance',
				'baggage_allowance'         => 'recheck_baggage_allowance',
				'minimum_connection_time'   => 'recheck_minimum_connection_time',
			);
			foreach ( $rechecks as $recheck ) {
				$actions[] = self::action( $action_map[ $recheck['code'] ], $recheck['component_refs'], $recheck['traveler_refs'], 'blocked', array( 'supplier_verification_pending' ) );
			}
		}
		if ( 'israel_gtfs_degraded' === $scenario ) {
			$blocker = 'stale' === $input['official_transit_source']['state'] ? 'official_source_stale' : 'official_source_unavailable';
			$actions[] = self::action( 'withhold_unverified_transit_route', $component_scope, $traveler_scope, 'blocked', array( $blocker ) );
			$actions[] = self::action( 'consult_official_route_planner', $component_scope, $traveler_scope, 'informational', array() );
			$actions[] = self::action( 'route_to_human_transit_review', $component_scope, $traveler_scope, 'planned', array() );
		}
		usort(
			$actions,
			function ( $left, $right ) {
				return strcmp( $left['action_ref'], $right['action_ref'] );
			}
		);
		return $actions;
	}

	private static function action( $code, $component_refs, $traveler_refs, $state, $blocker_codes ) {
		sort( $component_refs, SORT_STRING );
		sort( $traveler_refs, SORT_STRING );
		return array(
			'action_ref'              => Tra_Vel_Trip_Change_Stress_Policy::ref_value( 'stress_action', $code . '|' . implode( '|', $component_refs ) . '|' . implode( '|', $traveler_refs ) ),
			'code'                    => $code,
			'component_refs'          => $component_refs,
			'traveler_refs'           => $traveler_refs,
			'state'                   => $state,
			'blocker_codes'           => $blocker_codes,
			'authorization_effect'    => 'none',
			'supplier_action_claimed' => false,
		);
	}

	private static function rechecks( $input, $partitions ) {
		if ( 'aircraft_or_terminal_change' !== $input['scenario_type'] ) {
			return array();
		}
		$component_refs = $partitions['components']['affected_refs'];
		$traveler_refs = $partitions['travelers']['affected_refs'];
		$rechecks = array();
		foreach ( Tra_Vel_Trip_Change_Stress_Policy::RECHECK_CODES as $code ) {
			$rechecks[] = array(
				'code'                    => $code,
				'component_refs'          => $component_refs,
				'traveler_refs'           => $traveler_refs,
				'state'                   => 'required',
				'truth_state'             => 'pending_supplier_verification',
				'execution_authorized'    => false,
				'supplier_action_claimed' => false,
			);
		}
		return $rechecks;
	}

	private static function source_gate( $source, $scenario_type ) {
		$degraded = 'israel_gtfs_degraded' === $scenario_type;
		return array(
			'applicability'              => $source['applicability'],
			'source_code'               => $source['source_code'],
			'source_state'              => $source['state'],
			'checked_at'                => $source['checked_at'],
			'expires_at'                => $source['expires_at'],
			'evidence_digest'           => $source['evidence_digest'],
			'freshness_gate_state'      => $degraded ? 'failed_closed' : 'not_applicable',
			'route_claim_allowed'       => false,
			'current_route_claim_present' => false,
			'fallback_channels'         => $degraded ? Tra_Vel_Trip_Change_Stress_Policy::OFFICIAL_FALLBACK_CHANNELS : array(),
			'human_review_required'     => $degraded,
		);
	}

	private static function index_by( $items, $field ) {
		$map = array();
		foreach ( $items as $item ) {
			$map[ $item[ $field ] ] = $item;
		}
		return $map;
	}
}
