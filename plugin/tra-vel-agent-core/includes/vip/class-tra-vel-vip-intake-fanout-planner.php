<?php
/**
 * Deterministic one-message to multi-playbook VIP intake fan-out planner.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_VIP_Intake_Fanout_Planner {
	/**
	 * Create private case seeds from already normalized observations.
	 *
	 * This is a pure projection. It writes no state and performs no supplier,
	 * payment, claim, booking, REST, network, or AI action.
	 *
	 * @return array|WP_Error
	 */
	public static function plan( $binding, $observations, $now ) {
		$valid_binding = Tra_Vel_VIP_Intake_Fanout_Policy::validated_binding( $binding, $now );
		if ( is_wp_error( $valid_binding ) ) {
			return $valid_binding;
		}
		if ( ! is_array( $observations ) || array_values( $observations ) !== $observations || ! $observations || count( $observations ) > Tra_Vel_VIP_Intake_Fanout_Policy::MAX_OBSERVATIONS ) {
			return Tra_Vel_VIP_Intake_Fanout_Policy::error( 'observation_batch_invalid', 'Fan-out requires a bounded ordered list of normalized observations.' );
		}

		$accepted = array();
		$by_ref = array();
		$by_idempotency = array();
		$duplicate_count = 0;
		foreach ( $observations as $observation ) {
			$valid_observation = Tra_Vel_VIP_Intake_Fanout_Policy::normalized_observation( $observation, $binding, $now );
			if ( is_wp_error( $valid_observation ) ) {
				return $valid_observation;
			}
			if ( 'verified' !== $observation['mapping_state'] ) {
				return Tra_Vel_VIP_Intake_Fanout_Policy::error( 'clarification_required', 'Ambiguous or conflicted observations require upstream human clarification before fan-out.' );
			}

			$fingerprint = Tra_Vel_VIP_Intake_Fanout_Policy::observation_digest( $observation );
			if ( isset( $by_ref[ $observation['observation_ref'] ] ) ) {
				if ( ! hash_equals( $by_ref[ $observation['observation_ref'] ], $fingerprint ) ) {
					return Tra_Vel_VIP_Intake_Fanout_Policy::error( 'observation_ref_conflict', 'The same observation reference was replayed with different normalized content.' );
				}
				++$duplicate_count;
				continue;
			}
			if ( isset( $by_idempotency[ $observation['idempotency_digest'] ] ) ) {
				if ( ! hash_equals( $by_idempotency[ $observation['idempotency_digest'] ], $fingerprint ) ) {
					return Tra_Vel_VIP_Intake_Fanout_Policy::error( 'observation_idempotency_conflict', 'An idempotency digest was reused for different normalized content.' );
				}
				++$duplicate_count;
				continue;
			}
			$by_ref[ $observation['observation_ref'] ] = $fingerprint;
			$by_idempotency[ $observation['idempotency_digest'] ] = $fingerprint;
			$accepted[ $observation['observation_ref'] ] = $observation;
		}
		if ( ! $accepted ) {
			return Tra_Vel_VIP_Intake_Fanout_Policy::error( 'no_unique_observations', 'At least one unique verified observation is required.' );
		}
		ksort( $accepted, SORT_STRING );

		$ledger_entries = array();
		$groups = array();
		$evidence_owners = array();
		foreach ( $accepted as $observation ) {
			$ledger_entries[] = array(
				'observation_ref' => $observation['observation_ref'],
				'observation_digest' => Tra_Vel_VIP_Intake_Fanout_Policy::observation_digest( $observation ),
				'idempotency_digest' => $observation['idempotency_digest'],
				'mapped_case_families' => $observation['mapped_case_families'],
			);
			foreach ( $observation['mapped_case_families'] as $family ) {
				if ( ! isset( $groups[ $family ] ) ) {
					$groups[ $family ] = array(
						'source_observation_refs' => array(),
						'risk_signals' => array(),
						'service_refs' => array(),
						'dependency_refs' => array(),
						'evidence_items' => array(),
					);
				}
				$groups[ $family ]['source_observation_refs'][ $observation['observation_ref'] ] = true;
				foreach ( $observation['risk_signals'] as $risk ) {
					$groups[ $family ]['risk_signals'][ $risk ] = true;
				}
				foreach ( $observation['service_refs'] as $service_ref ) {
					$groups[ $family ]['service_refs'][ $service_ref ] = true;
				}
				foreach ( $observation['dependency_refs'] as $dependency_ref ) {
					$groups[ $family ]['dependency_refs'][ $dependency_ref ] = true;
				}
				foreach ( $observation['evidence'] as $evidence ) {
					if ( $family !== $evidence['allowed_case_families'][0] ) {
						continue;
					}
					if ( isset( $evidence_owners[ $evidence['evidence_ref'] ] ) && $family !== $evidence_owners[ $evidence['evidence_ref'] ]['family'] ) {
						return Tra_Vel_VIP_Intake_Fanout_Policy::error( 'evidence_cross_partition_conflict', 'An evidence reference cannot be assigned to different medical, payment, or service cases.' );
					}
					if ( isset( $evidence_owners[ $evidence['evidence_ref'] ] ) && ! hash_equals( $evidence_owners[ $evidence['evidence_ref'] ]['digest'], $evidence['evidence_digest'] ) ) {
						return Tra_Vel_VIP_Intake_Fanout_Policy::error( 'evidence_digest_conflict', 'An evidence reference was reused with a different digest.' );
					}
					$evidence_owners[ $evidence['evidence_ref'] ] = array( 'family' => $family, 'digest' => $evidence['evidence_digest'] );
					$groups[ $family ]['evidence_items'][ $evidence['evidence_ref'] ] = array(
						'evidence_ref' => $evidence['evidence_ref'],
						'evidence_digest' => $evidence['evidence_digest'],
					);
				}
			}
		}

		$ledger = array(
			'accepted' => $ledger_entries,
			'accepted_count' => count( $ledger_entries ),
			'duplicate_count' => $duplicate_count,
			'ledger_digest' => Tra_Vel_VIP_Intake_Fanout_Policy::canonical_digest( $ledger_entries ),
		);
		$case_seeds = array();
		foreach ( Tra_Vel_VIP_Intake_Fanout_Policy::CASE_FAMILIES as $family ) {
			if ( ! isset( $groups[ $family ] ) ) {
				continue;
			}
			$config = Tra_Vel_VIP_Intake_Fanout_Policy::family_config( $family );
			$group = $groups[ $family ];
			$source_refs = array_keys( $group['source_observation_refs'] );
			$service_refs = array_keys( $group['service_refs'] );
			$dependency_refs = array_keys( $group['dependency_refs'] );
			$evidence_items = array_values( $group['evidence_items'] );
			sort( $source_refs, SORT_STRING );
			sort( $service_refs, SORT_STRING );
			sort( $dependency_refs, SORT_STRING );
			usort( $evidence_items, array( __CLASS__, 'compare_evidence' ) );
			$risk_signals = array_keys( $group['risk_signals'] );
			if ( count( $risk_signals ) > 1 ) {
				$risk_signals = array_values( array_diff( $risk_signals, array( 'none' ) ) );
			}
			$risk_signals = Tra_Vel_VIP_Intake_Fanout_Policy::ordered_risks( $risk_signals );
			$priority = Tra_Vel_VIP_Intake_Fanout_Policy::priority_for( $family, $risk_signals );
			$case_seed_ref = Tra_Vel_VIP_Intake_Fanout_Policy::ref_value( 'case_seed', $binding['binding_digest'] . '|' . $family );
			$partition_basis = array(
				'scope' => $config['evidence_scope'],
				'evidence_items' => $evidence_items,
				'restricted' => $config['restricted'],
				'cross_case_disclosure_allowed' => false,
			);
			$seed = array(
				'case_seed_ref' => $case_seed_ref,
				'case_seed_digest' => str_repeat( '0', 64 ),
				'family' => $family,
				'family_ref' => Tra_Vel_VIP_Intake_Fanout_Policy::ref_value( 'case_family', $binding['binding_digest'] . '|' . $family ),
				'family_event_ref' => Tra_Vel_VIP_Intake_Fanout_Policy::ref_value( 'case_event', $case_seed_ref . '|' . implode( '|', $source_refs ) ),
				'source_observation_refs' => $source_refs,
				'risk_signals' => $risk_signals,
				'priority' => $priority,
				'playbook_code' => $config['playbook_code'],
				'evidence_partition' => $partition_basis + array(
					'partition_digest' => Tra_Vel_VIP_Intake_Fanout_Policy::canonical_digest( $partition_basis ),
				),
				'authority' => array(
					'required' => true,
					'requirement_code' => $config['authority_requirement'],
					'state' => 'unverified',
					'execution_authorized' => false,
				),
				'dependencies' => array(
					'service_refs' => $service_refs,
					'dependency_refs' => $dependency_refs,
					'preserve_unaffected_services' => true,
				),
				'routing' => array(
					'after_hours_required' => true,
					'after_hours_route_code' => $config['after_hours_route_code'],
					'safety_handoff_required' => 'P0' === $priority,
					'safety_route_code' => 'P0' === $priority ? 'emergency_services_and_medical_assistance' : null,
					'operator_review_required' => true,
					'dispatch_state' => 'not_dispatched',
				),
				'execution' => array(
					'supplier_action_executed' => false,
					'payment_action_executed' => false,
					'claim_action_executed' => false,
					'booking_action_executed' => false,
					'execution_effect' => 'none',
				),
			);
			$seed['case_seed_digest'] = Tra_Vel_VIP_Intake_Fanout_Policy::case_seed_digest( $seed );
			$case_seeds[] = $seed;
		}
		usort( $case_seeds, array( __CLASS__, 'compare_seeds' ) );

		$family_order = array();
		$p0_count = 0;
		foreach ( $case_seeds as $seed ) {
			$family_order[] = $seed['family'];
			$p0_count += 'P0' === $seed['priority'] ? 1 : 0;
		}
		$record = array(
			'contract_version' => Tra_Vel_VIP_Intake_Fanout_Policy::CONTRACT_VERSION,
			'environment' => 'sandbox',
			'fanout_ref' => Tra_Vel_VIP_Intake_Fanout_Policy::ref_value( 'fanout', $binding['binding_digest'] . '|' . $ledger['ledger_digest'] ),
			'fanout_digest' => str_repeat( '0', 64 ),
			'binding' => $binding,
			'observation_ledger' => $ledger,
			'case_seeds' => $case_seeds,
			'summary' => array(
				'seed_count' => count( $case_seeds ),
				'family_order' => $family_order,
				'p0_seed_count' => $p0_count,
				'safety_seed_count' => $p0_count,
				'duplicate_observation_count' => $duplicate_count,
				'duplicate_playbook_count' => 0,
				'clarification_required' => false,
				'side_effect_count' => 0,
			),
			'created_at' => $binding['validated_at'],
			'private_boundary' => array(
				'server_only' => true,
				'public_serialization_allowed' => false,
				'planning_only' => true,
				'storage_written' => false,
				'rest_route_registered' => false,
				'network_called' => false,
				'ai_called' => false,
				'supplier_dispatched' => false,
				'payment_executed' => false,
				'claim_submitted' => false,
				'booking_executed' => false,
				'authorization_effect' => 'none',
			),
		);
		$record['fanout_digest'] = Tra_Vel_VIP_Intake_Fanout_Policy::fanout_digest( $record );
		return Tra_Vel_VIP_Intake_Fanout_Policy::fanout( $record, $now );
	}

	private static function compare_evidence( $left, $right ) {
		return strcmp( $left['evidence_ref'], $right['evidence_ref'] );
	}

	private static function compare_seeds( $left, $right ) {
		$priority = Tra_Vel_VIP_Intake_Fanout_Policy::priority_rank( $left['priority'] ) - Tra_Vel_VIP_Intake_Fanout_Policy::priority_rank( $right['priority'] );
		if ( 0 !== $priority ) {
			return $priority;
		}
		return array_search( $left['family'], Tra_Vel_VIP_Intake_Fanout_Policy::CASE_FAMILIES, true ) - array_search( $right['family'], Tra_Vel_VIP_Intake_Fanout_Policy::CASE_FAMILIES, true );
	}
}
