<?php
/**
 * Deterministic, side-effect-free blast-radius and recovery-option planner.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Trip_Recovery_Planner {
	/**
	 * Assess accepted incident observations against one immutable itinerary graph.
	 *
	 * Duplicate observations are idempotently ignored. Events that arrive behind
	 * the accepted sequence watermark are quarantined for human reconciliation
	 * and cannot mutate impact or recovery options.
	 *
	 * @return array|WP_Error
	 */
	public static function assess( $graph, $incoming_events, $now ) {
		$valid_graph = Tra_Vel_Trip_Dependency_Policy::graph( $graph, $now );
		if ( is_wp_error( $valid_graph ) ) {
			return $valid_graph;
		}
		if ( ! is_array( $incoming_events ) || array_values( $incoming_events ) !== $incoming_events || ! $incoming_events || count( $incoming_events ) > Tra_Vel_Trip_Dependency_Policy::MAX_EVENTS ) {
			return self::error( 'event_batch_invalid', 'Recovery assessment requires a bounded ordered event batch.' );
		}

		$accepted = array();
		$refs = array();
		$idempotency = array();
		$duplicates = 0;
		$out_of_order = array();
		$stale = array();
		$watermark = 0;
		foreach ( $incoming_events as $event ) {
			$valid = Tra_Vel_Trip_Dependency_Policy::event( $event, $graph, $now );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
			$fingerprint = Tra_Vel_Trip_Dependency_Policy::event_fingerprint( $event );
			if ( isset( $refs[ $event['event_ref'] ] ) ) {
				if ( ! hash_equals( $refs[ $event['event_ref'] ], $fingerprint ) ) {
					return self::error( 'event_ref_conflict', 'The same event reference was replayed with different content.' );
				}
				++$duplicates;
				continue;
			}
			if ( isset( $idempotency[ $event['idempotency_digest'] ] ) ) {
				if ( ! hash_equals( $idempotency[ $event['idempotency_digest'] ], $fingerprint ) ) {
					return self::error( 'event_idempotency_conflict', 'The same idempotency key was reused for a different incident observation.' );
				}
				++$duplicates;
				continue;
			}
			$refs[ $event['event_ref'] ] = $fingerprint;
			$idempotency[ $event['idempotency_digest'] ] = $fingerprint;
			if ( $event['sequence'] <= $watermark ) {
				$out_of_order[] = $event['event_ref'];
				continue;
			}
			$watermark = $event['sequence'];
			$accepted[] = $event;
			if ( 'stale' === $event['source']['truth_state'] || 'supplier.response_stale' === $event['type'] ) {
				$stale[] = $event['event_ref'];
			}
		}
		if ( ! $accepted ) {
			return self::error( 'event_batch_no_accepted_observation', 'No unique in-order incident observation remained for assessment.' );
		}

		$node_map = array();
		foreach ( $graph['nodes'] as $node ) {
			$node_map[ $node['node_ref'] ] = $node;
		}
		$direct = array();
		foreach ( $accepted as $event ) {
			foreach ( $event['affected_node_refs'] as $node_ref ) {
				$direct[ $node_ref ] = true;
			}
		}
		$transitive = self::downstream( array_keys( $direct ), $graph['edges'] );
		foreach ( array_keys( $direct ) as $node_ref ) {
			unset( $transitive[ $node_ref ] );
		}
		$affected = $direct + $transitive;
		$unaffected = array();
		$verticals = array();
		$deadlines = array();
		foreach ( $node_map as $node_ref => $node ) {
			if ( isset( $affected[ $node_ref ] ) ) {
				$verticals[ $node['vertical'] ] = true;
				foreach ( $node['deadlines'] as $deadline ) {
					if ( 'open' === $deadline['state'] ) {
						$deadlines[ $deadline['deadline_ref'] ] = true;
					}
				}
			} else {
				$unaffected[] = $node_ref;
			}
		}
		$direct_refs = array_keys( $direct );
		$transitive_refs = array_keys( $transitive );
		sort( $direct_refs, SORT_STRING );
		sort( $transitive_refs, SORT_STRING );
		sort( $unaffected, SORT_STRING );
		$affected_verticals = self::ordered_verticals( array_keys( $verticals ) );
		$deadline_refs = array_keys( $deadlines );
		sort( $deadline_refs, SORT_STRING );

		$context = self::triage_context( $graph, $accepted, $direct_refs, $transitive_refs, $stale, $out_of_order );
		$gates = self::gates( $graph, $accepted, $context, $now );
		$candidates = self::candidates( $accepted, array_merge( $direct_refs, $transitive_refs ), $direct_refs, $gates, $context, $now );
		$recovery_seed = $graph['graph_ref'] . '|' . $graph['graph_digest'] . '|' . Tra_Vel_Trip_Dependency_Policy::canonical_digest( $accepted );
		$recovery = array(
			'contract_version' => Tra_Vel_Trip_Dependency_Taxonomy::CONTRACT_VERSION,
			'environment' => 'sandbox',
			'recovery_ref' => self::ref( 'recovery', $recovery_seed ),
			'recovery_version' => 1,
			'previous_recovery_digest' => null,
			'recovery_digest' => str_repeat( '0', 64 ),
			'graph_binding' => array(
				'graph_ref' => $graph['graph_ref'],
				'graph_version' => $graph['graph_version'],
				'graph_digest' => $graph['graph_digest'],
				'trip_ref' => $graph['trip_ref'],
			),
			'event_ledger' => array(
				'events' => $accepted,
				'ledger_digest' => Tra_Vel_Trip_Dependency_Policy::canonical_digest( $accepted ),
				'duplicate_count' => $duplicates,
				'out_of_order_event_refs' => array_values( array_unique( $out_of_order ) ),
				'stale_response_event_refs' => array_values( array_unique( $stale ) ),
			),
			'impact' => array(
				'direct_node_refs' => $direct_refs,
				'transitive_node_refs' => $transitive_refs,
				'unaffected_node_refs' => $unaffected,
				'affected_verticals' => $affected_verticals,
				'critical_deadline_refs' => $deadline_refs,
				'blast_radius_count' => count( $affected ),
			),
			'triage' => array(
				'severity' => $context['severity'],
				'state' => $context['triage_state'],
				'reason_codes' => $context['reason_codes'],
				'human_escalation_required' => $context['human_required'],
				'safety_handoff_required' => $context['safety_handoff'],
				'next_review_at' => self::plus_seconds( $now, $context['review_seconds'] ),
			),
			'gates' => $gates,
			'candidates' => $candidates,
			'selected_candidate_ref' => null,
			'plan_state' => ( $stale || $out_of_order || $context['hard_block'] ) ? 'blocked' : 'proposed',
			'completion' => array(
				'state' => 'not_started',
				'restored_node_refs' => array(),
				'closed_loss_node_refs' => array(),
				'supplier_verification_digest' => null,
				'itinerary_revalidation_digest' => null,
				'deadline_outcomes_digest' => null,
				'financial_state' => 'pending',
				'financial_evidence_digest' => null,
				'customer_notification_digest' => null,
				'verified_at' => null,
				'authorization_effect' => 'none',
				'supplier_action_claimed' => false,
			),
			'created_at' => $now,
			'updated_at' => $now,
			'private_boundary' => array(
				'server_only' => true,
				'public_serialization_allowed' => false,
				'planning_only' => true,
				'execution_dispatched' => false,
				'processor_called' => false,
				'raw_identity_data_stored' => false,
				'raw_payment_data_stored' => false,
				'raw_medical_data_stored' => false,
				'raw_supplier_payload_stored' => false,
				'bearer_secret_stored' => false,
				'supplier_action_claimed' => false,
			),
		);
		$recovery['recovery_digest'] = Tra_Vel_Trip_Dependency_Policy::recovery_digest( $recovery );
		return Tra_Vel_Trip_Dependency_Policy::recovery( $recovery, $graph, $now );
	}

	private static function downstream( $starts, $edges ) {
		$adjacency = array();
		foreach ( $edges as $edge ) {
			if ( ! $edge['active'] ) {
				continue;
			}
			if ( ! isset( $adjacency[ $edge['from_node_ref'] ] ) ) {
				$adjacency[ $edge['from_node_ref'] ] = array();
			}
			$adjacency[ $edge['from_node_ref'] ][] = $edge['to_node_ref'];
		}
		$found = array();
		$queue = array_values( $starts );
		while ( $queue ) {
			$current = array_shift( $queue );
			if ( ! isset( $adjacency[ $current ] ) ) {
				continue;
			}
			foreach ( $adjacency[ $current ] as $next ) {
				if ( ! isset( $found[ $next ] ) ) {
					$found[ $next ] = true;
					$queue[] = $next;
				}
			}
		}
		return $found;
	}

	private static function triage_context( $graph, $events, $direct, $transitive, $stale, $out_of_order ) {
		$event_types = array();
		$financial_uncertain = false;
		$safety_handoff = false;
		$high_risk = false;
		foreach ( $events as $event ) {
			$event_types[ $event['type'] ] = true;
			if ( 'insurance.incident_reported' === $event['type'] ) {
				$safety_handoff = true;
			}
			if ( in_array( $event['type'], Tra_Vel_Trip_Dependency_Taxonomy::HIGH_RISK_EVENT_TYPES, true ) ) {
				$high_risk = true;
			}
			if ( in_array( $event['financial_state']['state'], array( 'pending', 'partial_refund_observed', 'uncertain', 'disputed' ), true ) ) {
				$financial_uncertain = true;
			}
		}
		$affected = array_fill_keys( array_merge( $direct, $transitive ), true );
		$vulnerability = false;
		$hard_block = false;
		foreach ( $graph['traveler_constraints'] as $constraint ) {
			if ( ! array_intersect( array_keys( $affected ), $constraint['required_node_refs'] ) ) {
				continue;
			}
			if ( in_array( $constraint['type'], array( 'minor_guardian_authority', 'dependent_adult_authority', 'accessibility_match' ), true ) ) {
				$vulnerability = true;
			}
			if ( in_array( $constraint['state'], array( 'missing', 'conflict', 'stale' ), true ) ) {
				$hard_block = true;
			}
		}
		$concurrent = count( $event_types ) > 1;
		$human = $safety_handoff || $high_risk || $financial_uncertain || $vulnerability || $concurrent || $stale || $out_of_order || $hard_block;
		$reasons = array();
		if ( $safety_handoff ) {
			$reasons[] = 'immediate_assistance_handoff';
		}
		if ( $high_risk ) {
			$reasons[] = 'trip_critical_disruption';
		}
		if ( $financial_uncertain ) {
			$reasons[] = 'financial_state_unresolved';
		}
		if ( $vulnerability ) {
			$reasons[] = 'vulnerable_traveler_constraint';
		}
		if ( $concurrent ) {
			$reasons[] = 'concurrent_incidents';
		}
		if ( $stale ) {
			$reasons[] = 'stale_supplier_response';
		}
		if ( $out_of_order ) {
			$reasons[] = 'out_of_order_event_quarantined';
		}
		if ( $hard_block ) {
			$reasons[] = 'traveler_constraint_blocked';
		}
		if ( count( $direct ) + count( $transitive ) > count( $direct ) ) {
			$reasons[] = 'dependency_blast_radius';
		}
		if ( ! $reasons ) {
			$reasons[] = 'bounded_service_disruption';
		}
		$reasons = array_values( array_unique( $reasons ) );
		sort( $reasons, SORT_STRING );
		return array(
			'severity' => $safety_handoff ? 'P0' : ( ( $high_risk || $vulnerability || $concurrent ) ? 'P1' : 'P2' ),
			'triage_state' => $safety_handoff ? 'safety_handoff_required' : ( $human ? 'human_review_required' : 'assessed' ),
			'human_required' => (bool) $human,
			'safety_handoff' => $safety_handoff,
			'financial_uncertain' => $financial_uncertain,
			'hard_block' => $hard_block,
			'reason_codes' => $reasons,
			'review_seconds' => $safety_handoff ? 300 : ( ( $high_risk || $vulnerability || $concurrent ) ? 900 : 3600 ),
		);
	}

	private static function gates( $graph, $events, $context, $now ) {
		$needed = array( 'supplier_truth' => true );
		foreach ( $events as $event ) {
			foreach ( Tra_Vel_Trip_Dependency_Taxonomy::recovery_recipe( $event['type'] ) as $recipe ) {
				foreach ( $recipe[1] as $gate ) {
					$needed[ $gate ] = true;
				}
			}
		}
		if ( ! $graph['party_flags']['minor_present'] && ! $graph['party_flags']['dependent_adult_present'] ) {
			unset( $needed['guardian_or_dependent_authority'] );
		}
		if ( ! $graph['party_flags']['accessibility_required'] ) {
			unset( $needed['accessibility_supplier_ack'] );
		}
		if ( $graph['party_flags']['minor_present'] || $graph['party_flags']['dependent_adult_present'] ) {
			$needed['guardian_or_dependent_authority'] = true;
		}
		if ( $graph['party_flags']['accessibility_required'] ) {
			$needed['accessibility_supplier_ack'] = true;
		}
		if ( $context['human_required'] ) {
			$needed['human_operator_review'] = true;
		}
		if ( $context['financial_uncertain'] ) {
			$needed['financial_authority'] = true;
		}
		$constraint_gate_types = array(
			'document_admissibility' => array( 'document_admissibility' ),
			'guardian_or_dependent_authority' => array( 'minor_guardian_authority', 'dependent_adult_authority' ),
			'accessibility_supplier_ack' => array( 'accessibility_match' ),
		);
		$gates = array();
		foreach ( Tra_Vel_Trip_Dependency_Taxonomy::GATE_CODES as $code ) {
			$state = isset( $needed[ $code ] ) ? 'required' : 'not_applicable';
			$evidence = null;
			$verified_at = null;
			if ( isset( $needed[ $code ], $constraint_gate_types[ $code ] ) ) {
				$projection = self::constraint_gate( $graph['traveler_constraints'], $constraint_gate_types[ $code ] );
				$state = $projection['state'];
				$evidence = $projection['evidence_digest'];
				$verified_at = $projection['verified_at'];
			}
			if ( 'supplier_truth' === $code ) {
				$stale_events = array();
				foreach ( $events as $event ) {
					if ( 'stale' === $event['source']['truth_state'] || 'supplier.response_stale' === $event['type'] ) {
						$stale_events[] = $event['event_ref'];
					}
				}
				if ( $stale_events ) {
					$state = 'stale';
					$evidence = Tra_Vel_Trip_Dependency_Policy::canonical_digest( $stale_events );
				}
			}
			$gates[] = array( 'code' => $code, 'state' => $state, 'evidence_digest' => $evidence, 'verified_at' => $verified_at );
		}
		return $gates;
	}

	private static function constraint_gate( $constraints, $types ) {
		$found = array();
		foreach ( $constraints as $constraint ) {
			if ( in_array( $constraint['type'], $types, true ) ) {
				$found[] = $constraint;
			}
		}
		if ( ! $found ) {
			return array( 'state' => 'blocked', 'evidence_digest' => null, 'verified_at' => null );
		}
		$precedence = array( 'conflict' => 'conflict', 'missing' => 'blocked', 'stale' => 'stale', 'required' => 'required', 'verified_current' => 'satisfied', 'not_applicable' => 'not_applicable' );
		$rank = array( 'conflict' => 6, 'missing' => 5, 'stale' => 4, 'required' => 3, 'verified_current' => 2, 'not_applicable' => 1 );
		$worst = $found[0];
		foreach ( $found as $constraint ) {
			if ( $rank[ $constraint['state'] ] > $rank[ $worst['state'] ] ) {
				$worst = $constraint;
			}
		}
		$evidence = null;
		$verified_at = null;
		if ( in_array( $worst['state'], array( 'verified_current', 'stale', 'conflict' ), true ) ) {
			$digests = array();
			$verified_times = array();
			foreach ( $found as $constraint ) {
				if ( null !== $constraint['evidence_digest'] ) {
					$digests[] = $constraint['evidence_digest'];
				}
				if ( null !== $constraint['verified_at'] ) {
					$verified_times[] = $constraint['verified_at'];
				}
			}
			sort( $digests, SORT_STRING );
			sort( $verified_times, SORT_STRING );
			$evidence = Tra_Vel_Trip_Dependency_Policy::canonical_digest( $digests );
			$verified_at = 'verified_current' === $worst['state'] && $verified_times ? end( $verified_times ) : null;
		}
		return array(
			'state' => $precedence[ $worst['state'] ],
			'evidence_digest' => $evidence,
			'verified_at' => $verified_at,
		);
	}

	private static function candidates( $events, $affected, $direct, $gates, $context, $now ) {
		$gate_states = array();
		foreach ( $gates as $gate ) {
			$gate_states[ $gate['code'] ] = $gate['state'];
		}
		$recipes = array();
		foreach ( $events as $event ) {
			foreach ( Tra_Vel_Trip_Dependency_Taxonomy::recovery_recipe( $event['type'] ) as $recipe ) {
				$strategy = $recipe[0];
				if ( ! isset( $recipes[ $strategy ] ) ) {
					$recipes[ $strategy ] = array();
				}
				$recipes[ $strategy ] = array_values( array_unique( array_merge( $recipes[ $strategy ], $recipe[1] ) ) );
			}
		}
		$candidates = array();
		foreach ( $recipes as $strategy => $required_gates ) {
			if ( ! isset( $gate_states['guardian_or_dependent_authority'] ) || 'not_applicable' === $gate_states['guardian_or_dependent_authority'] ) {
				$required_gates = array_values( array_diff( $required_gates, array( 'guardian_or_dependent_authority' ) ) );
			}
			if ( ! isset( $gate_states['accessibility_supplier_ack'] ) || 'not_applicable' === $gate_states['accessibility_supplier_ack'] ) {
				$required_gates = array_values( array_diff( $required_gates, array( 'accessibility_supplier_ack' ) ) );
			}
			if ( $context['human_required'] && ! in_array( 'human_operator_review', $required_gates, true ) ) {
				$required_gates[] = 'human_operator_review';
			}
			$required_gates = self::ordered_gates( array_values( array_unique( $required_gates ) ) );
			$blocked = false;
			foreach ( $required_gates as $gate_code ) {
				if ( in_array( $gate_states[ $gate_code ], array( 'blocked', 'conflict' ), true ) ) {
					$blocked = true;
				}
				if ( 'stale' === $gate_states[ $gate_code ] ) {
					$blocked = true;
				}
			}
			$risks = array( 'requires_verified_external_response' );
			if ( $context['financial_uncertain'] ) {
				$risks[] = 'financial_reconciliation_required';
			}
			if ( $context['human_required'] ) {
				$risks[] = 'human_review_required';
			}
			if ( $context['safety_handoff'] ) {
				$risks[] = 'safety_handoff_not_supplier_booking';
			}
			$seed = $strategy . '|' . implode( '|', $affected ) . '|' . implode( '|', $required_gates );
			$candidates[] = array(
				'candidate_ref' => self::ref( 'candidate', $seed ),
				'strategy' => $strategy,
				'state' => $blocked ? 'blocked' : 'eligible_for_review',
				'restores_node_refs' => $affected,
				'changes_node_refs' => $direct,
				'required_gate_codes' => $required_gates,
				'risk_codes' => array_values( array_unique( $risks ) ),
				'planning_evidence_digest' => Tra_Vel_Trip_Dependency_Policy::canonical_digest( array( 'strategy' => $strategy, 'affected' => $affected, 'gates' => $required_gates ) ),
				'evidence_valid_until' => self::plus_seconds( $now, 1800 ),
				'financial_effect' => array( 'state' => in_array( 'financial_authority', $required_gates, true ) ? 'pending' : 'not_required', 'evidence_digest' => null ),
				'authorization_effect' => 'none',
				'supplier_action_claimed' => false,
			);
		}
		return $candidates;
	}

	private static function ordered_verticals( $values ) {
		return array_values( array_intersect( Tra_Vel_Trip_Dependency_Taxonomy::VERTICALS, $values ) );
	}

	private static function ordered_gates( $values ) {
		return array_values( array_intersect( Tra_Vel_Trip_Dependency_Taxonomy::GATE_CODES, $values ) );
	}

	private static function ref( $kind, $seed ) {
		return 'tv_' . $kind . '_' . substr( hash( 'sha256', $seed ), 0, 32 );
	}

	private static function plus_seconds( $utc, $seconds ) {
		$date = new DateTimeImmutable( $utc, new DateTimeZone( 'UTC' ) );
		return $date->modify( '+' . (int) $seconds . ' seconds' )->format( 'Y-m-d\TH:i:s\Z' );
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_trip_recovery_' . $suffix, $message, array( 'status' => 409 ) );
	}
}
