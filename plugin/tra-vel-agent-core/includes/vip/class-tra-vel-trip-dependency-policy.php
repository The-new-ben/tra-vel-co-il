<?php
/**
 * Fail-closed validation for immutable private trip graphs and recovery plans.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Trip_Dependency_Policy {
	const MAX_NODES = 128;
	const MAX_EDGES = 384;
	const MAX_EVENTS = 64;

	/**
	 * Validate and verify the self-seal of one immutable graph revision.
	 *
	 * @return array|WP_Error
	 */
	public static function graph( $graph, $now = null ) {
		$keys = array( 'contract_version', 'environment', 'graph_ref', 'graph_version', 'previous_graph_digest', 'graph_digest', 'trip_ref', 'owner_scope_digest', 'party_flags', 'nodes', 'edges', 'traveler_constraints', 'created_at', 'updated_at', 'private_boundary' );
		if ( ! self::exact_object( $graph, $keys ) || Tra_Vel_Trip_Dependency_Taxonomy::CONTRACT_VERSION !== $graph['contract_version'] || 'sandbox' !== $graph['environment'] || ! self::privacy_safe( $graph ) ) {
			return self::error( 'graph_shape_invalid', 'The trip dependency graph is not a closed private sandbox contract.' );
		}
		if ( ! self::ref( $graph['graph_ref'], 'graph' ) || ! self::positive_int( $graph['graph_version'] ) || ! self::nullable_digest( $graph['previous_graph_digest'] ) || ! self::digest( $graph['graph_digest'] ) || ! self::ref( $graph['trip_ref'], 'trip' ) || ! self::digest( $graph['owner_scope_digest'] ) || ! self::utc( $graph['created_at'] ) || ! self::utc( $graph['updated_at'] ) || $graph['updated_at'] < $graph['created_at'] || ( null !== $now && ( ! self::utc( $now ) || $graph['updated_at'] > $now ) ) ) {
			return self::error( 'graph_identity_invalid', 'The graph identity, revision, digest ancestry, or clock is invalid.' );
		}
		if ( 1 === $graph['graph_version'] && null !== $graph['previous_graph_digest'] ) {
			return self::error( 'graph_ancestry_invalid', 'The first graph revision cannot claim a predecessor.' );
		}
		if ( $graph['graph_version'] > 1 && null === $graph['previous_graph_digest'] ) {
			return self::error( 'graph_ancestry_invalid', 'A later graph revision must bind its predecessor digest.' );
		}
		if ( ! self::exact_flags( $graph['party_flags'] ) || ! self::private_boundary( $graph['private_boundary'] ) ) {
			return self::error( 'graph_boundary_invalid', 'The graph party flags or private data boundary is invalid.' );
		}

		$nodes = self::nodes( $graph['nodes'], $now );
		if ( is_wp_error( $nodes ) ) {
			return $nodes;
		}
		$edges = self::edges( $graph['edges'], $nodes );
		if ( is_wp_error( $edges ) ) {
			return $edges;
		}
		$constraints = self::constraints( $graph['traveler_constraints'], $nodes, $graph['party_flags'], $now );
		if ( is_wp_error( $constraints ) ) {
			return $constraints;
		}
		if ( ! hash_equals( self::graph_digest( $graph ), $graph['graph_digest'] ) ) {
			return self::error( 'graph_digest_invalid', 'The graph revision digest does not match its complete immutable payload.' );
		}
		return $graph;
	}

	/**
	 * Validate a normalized, non-executing incident observation.
	 *
	 * @return array|WP_Error
	 */
	public static function event( $event, $graph = null, $now = null ) {
		$keys = array( 'contract_version', 'event_ref', 'sequence', 'occurred_at', 'observed_at', 'type', 'source', 'affected_node_refs', 'idempotency_digest', 'supersedes_event_ref', 'financial_state', 'data_boundary' );
		if ( ! self::exact_object( $event, $keys ) || Tra_Vel_Trip_Dependency_Taxonomy::CONTRACT_VERSION !== $event['contract_version'] || ! self::privacy_safe( $event ) || ! self::ref( $event['event_ref'], 'trip_event' ) || ! self::positive_int( $event['sequence'] ) || ! self::utc( $event['occurred_at'] ) || ! self::utc( $event['observed_at'] ) || $event['observed_at'] < $event['occurred_at'] || ( null !== $now && ( ! self::utc( $now ) || $event['observed_at'] > $now ) ) || ! in_array( $event['type'], Tra_Vel_Trip_Dependency_Taxonomy::EVENT_TYPES, true ) || ! self::ref_list( $event['affected_node_refs'], 'node', true ) || ! self::digest( $event['idempotency_digest'] ) || ( null !== $event['supersedes_event_ref'] && ( ! self::ref( $event['supersedes_event_ref'], 'trip_event' ) || $event['supersedes_event_ref'] === $event['event_ref'] ) ) ) {
			return self::error( 'event_invalid', 'The trip incident observation is malformed, future-dated, or unsupported.' );
		}
		$source = $event['source'];
		if ( ! self::exact_object( $source, array( 'kind', 'truth_state', 'evidence_digest', 'response_expires_at' ) ) || ! in_array( $source['kind'], Tra_Vel_Trip_Dependency_Taxonomy::EVENT_SOURCE_KINDS, true ) || ! in_array( $source['truth_state'], Tra_Vel_Trip_Dependency_Taxonomy::EVENT_TRUTH_STATES, true ) || ! self::nullable_digest( $source['evidence_digest'] ) || ( null !== $source['response_expires_at'] && ! self::utc( $source['response_expires_at'] ) ) ) {
			return self::error( 'event_source_invalid', 'The event evidence and supplier truth state are invalid.' );
		}
		if ( 'verified_current' === $source['truth_state'] && ( ! self::digest( $source['evidence_digest'] ) || null === $source['response_expires_at'] || $source['response_expires_at'] <= $event['observed_at'] || ( null !== $now && $source['response_expires_at'] <= $now ) ) ) {
			return self::error( 'event_current_evidence_invalid', 'A current event requires unexpired evidence.' );
		}
		if ( 'stale' === $source['truth_state'] && ( ! self::digest( $source['evidence_digest'] ) || null === $source['response_expires_at'] || ( null !== $now && $source['response_expires_at'] > $now ) ) ) {
			return self::error( 'event_stale_evidence_invalid', 'A stale event must prove its expired evidence boundary.' );
		}
		if ( in_array( $source['truth_state'], array( 'observed_unverified', 'rejected' ), true ) && ( null !== $source['response_expires_at'] || ( 'rejected' === $source['truth_state'] && null === $source['evidence_digest'] ) ) ) {
			return self::error( 'event_evidence_orphaned', 'An unverified or rejected observation carries inconsistent evidence.' );
		}
		if ( 'customer_report' === $source['kind'] && 'observed_unverified' !== $source['truth_state'] ) {
			return self::error( 'event_customer_truth_invalid', 'A customer report cannot be promoted to authoritative supplier truth.' );
		}
		$financial = $event['financial_state'];
		if ( ! self::exact_object( $financial, array( 'state', 'evidence_digest' ) ) || ! in_array( $financial['state'], array( 'no_impact', 'pending', 'partial_refund_observed', 'uncertain', 'disputed', 'balanced' ), true ) || ! self::nullable_digest( $financial['evidence_digest'] ) || ( in_array( $financial['state'], array( 'partial_refund_observed', 'balanced', 'disputed' ), true ) && ! self::digest( $financial['evidence_digest'] ) ) || ( in_array( $financial['state'], array( 'no_impact', 'pending', 'uncertain' ), true ) && null !== $financial['evidence_digest'] ) ) {
			return self::error( 'event_financial_invalid', 'The event financial observation is inconsistent or overclaims certainty.' );
		}
		if ( ! self::event_boundary( $event['data_boundary'] ) ) {
			return self::error( 'event_boundary_invalid', 'Incident observations cannot expose raw data or authorize supplier actions.' );
		}
		if ( null !== $graph ) {
			$valid_graph = self::graph( $graph, $now );
			if ( is_wp_error( $valid_graph ) ) {
				return $valid_graph;
			}
			$known = array();
			foreach ( $graph['nodes'] as $node ) {
				$known[ $node['node_ref'] ] = true;
			}
			foreach ( $event['affected_node_refs'] as $node_ref ) {
				if ( ! isset( $known[ $node_ref ] ) ) {
					return self::error( 'event_node_unknown', 'An incident references a node outside the immutable graph revision.' );
				}
			}
		}
		return $event;
	}

	/**
	 * Validate a complete private recovery assessment and its self-seal.
	 *
	 * @return array|WP_Error
	 */
	public static function recovery( $recovery, $graph, $now = null ) {
		$keys = array( 'contract_version', 'environment', 'recovery_ref', 'recovery_version', 'previous_recovery_digest', 'recovery_digest', 'graph_binding', 'event_ledger', 'impact', 'triage', 'gates', 'candidates', 'selected_candidate_ref', 'plan_state', 'completion', 'created_at', 'updated_at', 'private_boundary' );
		$valid_graph = self::graph( $graph, $now );
		if ( is_wp_error( $valid_graph ) ) {
			return $valid_graph;
		}
		if ( ! self::exact_object( $recovery, $keys ) || Tra_Vel_Trip_Dependency_Taxonomy::CONTRACT_VERSION !== $recovery['contract_version'] || 'sandbox' !== $recovery['environment'] || ! self::privacy_safe( $recovery ) || ! self::ref( $recovery['recovery_ref'], 'recovery' ) || ! self::positive_int( $recovery['recovery_version'] ) || ! self::nullable_digest( $recovery['previous_recovery_digest'] ) || ! self::digest( $recovery['recovery_digest'] ) || ! self::utc( $recovery['created_at'] ) || ! self::utc( $recovery['updated_at'] ) || $recovery['updated_at'] < $recovery['created_at'] || ( null !== $now && ( ! self::utc( $now ) || $recovery['updated_at'] > $now ) ) || ! in_array( $recovery['plan_state'], Tra_Vel_Trip_Dependency_Taxonomy::PLAN_STATES, true ) || ! self::recovery_boundary( $recovery['private_boundary'] ) ) {
			return self::error( 'recovery_shape_invalid', 'The recovery snapshot is not a closed, planning-only private contract.' );
		}
		if ( 1 === $recovery['recovery_version'] && null !== $recovery['previous_recovery_digest'] ) {
			return self::error( 'recovery_ancestry_invalid', 'The first recovery revision cannot claim a predecessor.' );
		}
		if ( $recovery['recovery_version'] > 1 && null === $recovery['previous_recovery_digest'] ) {
			return self::error( 'recovery_ancestry_invalid', 'A later recovery revision must bind its predecessor digest.' );
		}

		$binding = $recovery['graph_binding'];
		if ( ! self::exact_object( $binding, array( 'graph_ref', 'graph_version', 'graph_digest', 'trip_ref' ) ) || $binding['graph_ref'] !== $graph['graph_ref'] || $binding['graph_version'] !== $graph['graph_version'] || ! hash_equals( $binding['graph_digest'], $graph['graph_digest'] ) || $binding['trip_ref'] !== $graph['trip_ref'] ) {
			return self::error( 'recovery_graph_binding_invalid', 'Recovery planning must bind the exact immutable graph revision.' );
		}

		$ledger = self::ledger( $recovery['event_ledger'], $graph, $now );
		if ( is_wp_error( $ledger ) ) {
			return $ledger;
		}
		$node_map = array();
		foreach ( $graph['nodes'] as $node ) {
			$node_map[ $node['node_ref'] ] = $node;
		}
		$impact = self::impact( $recovery['impact'], $node_map );
		if ( is_wp_error( $impact ) ) {
			return $impact;
		}
		$derived_impact = self::derived_impact( $recovery['impact'], $recovery['event_ledger']['events'], $graph['edges'], $node_map );
		if ( is_wp_error( $derived_impact ) ) {
			return $derived_impact;
		}
		$triage = self::triage( $recovery['triage'], $recovery['impact'], $recovery['event_ledger'], $recovery['plan_state'], $now );
		if ( is_wp_error( $triage ) ) {
			return $triage;
		}
		$gates = self::gates( $recovery['gates'], $now );
		if ( is_wp_error( $gates ) ) {
			return $gates;
		}
		$candidates = self::candidates( $recovery['candidates'], $recovery['impact'], $gates, $now );
		if ( is_wp_error( $candidates ) ) {
			return $candidates;
		}
		if ( null !== $recovery['selected_candidate_ref'] && ! isset( $candidates[ $recovery['selected_candidate_ref'] ] ) ) {
			return self::error( 'recovery_selection_invalid', 'The selected recovery candidate is outside this plan revision.' );
		}
		if ( in_array( $recovery['plan_state'], array( 'authorized', 'execution_observed', 'verification_pending', 'verified', 'closed_with_loss' ), true ) && null === $recovery['selected_candidate_ref'] ) {
			return self::error( 'recovery_selection_required', 'A progressed recovery requires an explicitly selected candidate.' );
		}
		if ( in_array( $recovery['plan_state'], array( 'ready_for_authorization', 'authorized', 'execution_observed', 'verification_pending', 'verified' ), true ) ) {
			foreach ( $gates as $gate ) {
				if ( ! in_array( $gate['state'], array( 'satisfied', 'not_applicable' ), true ) ) {
					return self::error( 'recovery_gate_blocked', 'A recovery cannot progress while a required gate remains unresolved.' );
				}
			}
		}
		$completion = self::completion( $recovery['completion'], $recovery['impact'], $recovery['selected_candidate_ref'], $recovery['plan_state'], $gates, $recovery['updated_at'] );
		if ( is_wp_error( $completion ) ) {
			return $completion;
		}
		if ( ! hash_equals( self::recovery_digest( $recovery ), $recovery['recovery_digest'] ) ) {
			return self::error( 'recovery_digest_invalid', 'The recovery digest does not match its complete immutable payload.' );
		}
		return $recovery;
	}

	/**
	 * Prove revision ancestry without mutating the predecessor.
	 *
	 * @return true|WP_Error
	 */
	public static function graph_successor( $previous, $next, $now = null ) {
		$previous_valid = self::graph( $previous, isset( $previous['updated_at'] ) ? $previous['updated_at'] : $now );
		$next_valid     = self::graph( $next, isset( $next['updated_at'] ) ? $next['updated_at'] : $now );
		if ( is_wp_error( $previous_valid ) ) {
			return $previous_valid;
		}
		if ( is_wp_error( $next_valid ) ) {
			return $next_valid;
		}
		if ( $next['graph_ref'] !== $previous['graph_ref'] || $next['trip_ref'] !== $previous['trip_ref'] || $next['owner_scope_digest'] !== $previous['owner_scope_digest'] || $next['graph_version'] !== $previous['graph_version'] + 1 || ! hash_equals( $next['previous_graph_digest'], $previous['graph_digest'] ) || $next['created_at'] !== $previous['created_at'] || $next['updated_at'] <= $previous['updated_at'] ) {
			return self::error( 'graph_successor_invalid', 'The graph successor does not preserve identity, monotonic versioning, or exact ancestry.' );
		}
		$next_refs = array();
		foreach ( $next['nodes'] as $node ) {
			$next_refs[ $node['node_ref'] ] = true;
		}
		foreach ( $previous['nodes'] as $node ) {
			if ( ! isset( $next_refs[ $node['node_ref'] ] ) ) {
				return self::error( 'graph_node_removed', 'A graph successor cannot silently remove a previously referenced itinerary component.' );
			}
		}
		return true;
	}

	/**
	 * Prove recovery revision ancestry.
	 *
	 * @return true|WP_Error
	 */
	public static function recovery_successor( $previous, $next, $graph, $now = null ) {
		$previous_valid = self::recovery( $previous, $graph, isset( $previous['updated_at'] ) ? $previous['updated_at'] : $now );
		$next_valid     = self::recovery( $next, $graph, isset( $next['updated_at'] ) ? $next['updated_at'] : $now );
		if ( is_wp_error( $previous_valid ) ) {
			return $previous_valid;
		}
		if ( is_wp_error( $next_valid ) ) {
			return $next_valid;
		}
		if ( $next['recovery_ref'] !== $previous['recovery_ref'] || $next['graph_binding'] !== $previous['graph_binding'] || $next['recovery_version'] !== $previous['recovery_version'] + 1 || ! hash_equals( $next['previous_recovery_digest'], $previous['recovery_digest'] ) || $next['created_at'] !== $previous['created_at'] || $next['updated_at'] <= $previous['updated_at'] ) {
			return self::error( 'recovery_successor_invalid', 'The recovery successor does not preserve identity, graph binding, or exact revision ancestry.' );
		}
		$allowed = array(
			'proposed'                => array( 'proposed', 'blocked', 'ready_for_authorization' ),
			'blocked'                 => array( 'blocked', 'proposed', 'ready_for_authorization' ),
			'ready_for_authorization' => array( 'ready_for_authorization', 'authorized', 'blocked' ),
			'authorized'              => array( 'authorized', 'execution_observed', 'blocked' ),
			'execution_observed'      => array( 'execution_observed', 'verification_pending', 'blocked' ),
			'verification_pending'    => array( 'verification_pending', 'execution_observed', 'verified', 'closed_with_loss' ),
			'verified'                => array( 'verified' ),
			'closed_with_loss'        => array( 'closed_with_loss' ),
		);
		if ( ! isset( $allowed[ $previous['plan_state'] ] ) || ! in_array( $next['plan_state'], $allowed[ $previous['plan_state'] ], true ) ) {
			return self::error( 'recovery_state_transition_invalid', 'Recovery revisions cannot skip authorization, observed execution, or verification states.' );
		}
		$previous_events = $previous['event_ledger']['events'];
		$next_events     = $next['event_ledger']['events'];
		if ( count( $next_events ) < count( $previous_events ) ) {
			return self::error( 'recovery_event_history_removed', 'A recovery successor cannot remove an accepted event.' );
		}
		foreach ( $previous_events as $index => $event ) {
			if ( ! isset( $next_events[ $index ] ) || ! hash_equals( self::event_fingerprint( $event ), self::event_fingerprint( $next_events[ $index ] ) ) ) {
				return self::error( 'recovery_event_history_rewritten', 'Accepted events must remain an immutable prefix of every recovery successor.' );
			}
		}
		return true;
	}

	/**
	 * Safe customer-facing status, intentionally excluding suppliers, amounts,
	 * evidence, private node locators, and authority artifacts.
	 *
	 * @return array|WP_Error
	 */
	public static function customer_projection( $recovery, $graph, $now = null ) {
		$valid = self::recovery( $recovery, $graph, $now );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		return array(
			'contract_version'          => Tra_Vel_Trip_Dependency_Taxonomy::CONTRACT_VERSION,
			'case_ref'                  => strtoupper( substr( $recovery['recovery_digest'], 0, 12 ) ),
			'plan_state'                => $recovery['plan_state'],
			'severity'                  => $recovery['triage']['severity'],
			'affected_service_count'    => $recovery['impact']['blast_radius_count'],
			'affected_verticals'        => $recovery['impact']['affected_verticals'],
			'options_ready'             => count( $recovery['candidates'] ),
			'action_required'           => self::projection_action( $recovery['gates'] ),
			'human_agent_involved'      => $recovery['triage']['human_escalation_required'],
			'safety_handoff_required'   => $recovery['triage']['safety_handoff_required'],
			'completion_state'          => $recovery['completion']['state'],
			'authorization_effect'      => 'none',
		);
	}

	public static function graph_digest( $graph ) {
		$copy = $graph;
		unset( $copy['graph_digest'] );
		return self::canonical_digest( $copy );
	}

	public static function recovery_digest( $recovery ) {
		$copy = $recovery;
		unset( $copy['recovery_digest'] );
		return self::canonical_digest( $copy );
	}

	public static function event_fingerprint( $event ) {
		return self::canonical_digest( $event );
	}

	public static function canonical_digest( $value ) {
		return hash( 'sha256', wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	private static function nodes( $nodes, $now ) {
		if ( ! is_array( $nodes ) || array_values( $nodes ) !== $nodes || ! $nodes || count( $nodes ) > self::MAX_NODES ) {
			return self::error( 'graph_nodes_invalid', 'A trip graph requires a bounded ordered itinerary node list.' );
		}
		$map = array();
		foreach ( $nodes as $node ) {
			$keys = array( 'node_ref', 'vertical', 'kind', 'state', 'service_window', 'geography', 'supplier', 'deadlines', 'artifact_digests' );
			if ( ! self::exact_object( $node, $keys ) || ! self::ref( $node['node_ref'], 'node' ) || isset( $map[ $node['node_ref'] ] ) || ! in_array( $node['vertical'], Tra_Vel_Trip_Dependency_Taxonomy::VERTICALS, true ) || Tra_Vel_Trip_Dependency_Taxonomy::node_kind_for_vertical( $node['vertical'] ) !== $node['kind'] || ! in_array( $node['state'], Tra_Vel_Trip_Dependency_Taxonomy::NODE_STATES, true ) || ! self::digest_list( $node['artifact_digests'], true ) ) {
				return self::error( 'graph_node_invalid', 'An itinerary node is malformed, duplicated, or uses an impossible vertical/kind pair.' );
			}
			$window = $node['service_window'];
			if ( ! self::exact_object( $window, array( 'starts_at', 'ends_at', 'timezone' ) ) || ! self::utc( $window['starts_at'] ) || ! self::utc( $window['ends_at'] ) || $window['ends_at'] <= $window['starts_at'] || ! self::timezone( $window['timezone'] ) ) {
				return self::error( 'graph_node_window_invalid', 'Every itinerary node requires a valid UTC interval and IANA timezone.' );
			}
			$geo = $node['geography'];
			if ( ! self::exact_object( $geo, array( 'origin_location_digest', 'destination_location_digest', 'country_code', 'local_tourism' ) ) || ! self::digest( $geo['origin_location_digest'] ) || ! self::digest( $geo['destination_location_digest'] ) || ! is_string( $geo['country_code'] ) || 1 !== preg_match( '/^[A-Z]{2}$/', $geo['country_code'] ) || ! is_bool( $geo['local_tourism'] ) ) {
				return self::error( 'graph_node_geography_invalid', 'Itinerary geography must remain digest-only and country-scoped.' );
			}
			$supplier = $node['supplier'];
			if ( ! self::exact_object( $supplier, array( 'provider_id', 'order_item_ref', 'confirmation_state', 'confirmation_digest', 'observed_at', 'valid_until' ) ) || ! self::provider_id( $supplier['provider_id'] ) || ! self::ref( $supplier['order_item_ref'], 'order_item' ) || ! in_array( $supplier['confirmation_state'], Tra_Vel_Trip_Dependency_Taxonomy::CONFIRMATION_STATES, true ) || ! self::nullable_digest( $supplier['confirmation_digest'] ) || ( null !== $supplier['observed_at'] && ! self::utc( $supplier['observed_at'] ) ) || ( null !== $supplier['valid_until'] && ! self::utc( $supplier['valid_until'] ) ) ) {
				return self::error( 'graph_node_supplier_invalid', 'The supplier confirmation projection is malformed.' );
			}
			if ( 'verified_current' === $supplier['confirmation_state'] && ( ! self::digest( $supplier['confirmation_digest'] ) || null === $supplier['observed_at'] || null === $supplier['valid_until'] || $supplier['valid_until'] <= $supplier['observed_at'] || ( null !== $now && $supplier['valid_until'] <= $now ) ) ) {
				return self::error( 'graph_node_confirmation_invalid', 'A current supplier confirmation requires current digest evidence and validity.' );
			}
			if ( 'stale' === $supplier['confirmation_state'] && ( ! self::digest( $supplier['confirmation_digest'] ) || null === $supplier['observed_at'] || null === $supplier['valid_until'] || ( null !== $now && $supplier['valid_until'] > $now ) ) ) {
				return self::error( 'graph_node_confirmation_stale_invalid', 'A stale supplier confirmation must prove its expired validity.' );
			}
			if ( in_array( $supplier['confirmation_state'], array( 'unverified', 'pending', 'unavailable', 'uncertain' ), true ) && ( null !== $supplier['confirmation_digest'] || null !== $supplier['observed_at'] || null !== $supplier['valid_until'] ) ) {
				return self::error( 'graph_node_confirmation_orphaned', 'An unverified supplier state cannot carry confirmation evidence.' );
			}
			$deadline_result = self::deadlines( $node['deadlines'], $node['service_window'] );
			if ( is_wp_error( $deadline_result ) ) {
				return $deadline_result;
			}
			$map[ $node['node_ref'] ] = $node;
		}
		return $map;
	}

	private static function deadlines( $deadlines, $window ) {
		if ( ! is_array( $deadlines ) || array_values( $deadlines ) !== $deadlines || count( $deadlines ) > 16 ) {
			return self::error( 'graph_deadlines_invalid', 'Node deadlines must be a bounded ordered list.' );
		}
		$seen = array();
		foreach ( $deadlines as $deadline ) {
			if ( ! self::exact_object( $deadline, array( 'deadline_ref', 'type', 'due_at', 'state', 'evidence_digest' ) ) || ! self::ref( $deadline['deadline_ref'], 'deadline' ) || isset( $seen[ $deadline['deadline_ref'] ] ) || ! in_array( $deadline['type'], Tra_Vel_Trip_Dependency_Taxonomy::DEADLINE_TYPES, true ) || ! self::utc( $deadline['due_at'] ) || ! in_array( $deadline['state'], Tra_Vel_Trip_Dependency_Taxonomy::DEADLINE_STATES, true ) || ! self::nullable_digest( $deadline['evidence_digest'] ) ) {
				return self::error( 'graph_deadline_invalid', 'A node deadline is malformed or duplicated.' );
			}
			if ( in_array( $deadline['state'], array( 'satisfied', 'missed', 'superseded' ), true ) && ! self::digest( $deadline['evidence_digest'] ) ) {
				return self::error( 'graph_deadline_evidence_invalid', 'A terminal deadline state requires evidence.' );
			}
			if ( in_array( $deadline['state'], array( 'open', 'uncertain' ), true ) && null !== $deadline['evidence_digest'] ) {
				return self::error( 'graph_deadline_orphan_evidence', 'An open or uncertain deadline cannot claim terminal evidence.' );
			}
			$seen[ $deadline['deadline_ref'] ] = true;
		}
		return true;
	}

	private static function edges( $edges, $nodes ) {
		if ( ! is_array( $edges ) || array_values( $edges ) !== $edges || count( $edges ) > self::MAX_EDGES ) {
			return self::error( 'graph_edges_invalid', 'A trip graph requires a bounded dependency edge list.' );
		}
		$seen = array();
		$adjacency = array();
		foreach ( $nodes as $node_ref => $node ) {
			$adjacency[ $node_ref ] = array();
		}
		foreach ( $edges as $edge ) {
			$keys = array( 'edge_ref', 'from_node_ref', 'to_node_ref', 'type', 'criticality', 'min_buffer_minutes', 'geographic_handoff', 'constraint_codes', 'active' );
			if ( ! self::exact_object( $edge, $keys ) || ! self::ref( $edge['edge_ref'], 'edge' ) || isset( $seen[ $edge['edge_ref'] ] ) || ! isset( $nodes[ $edge['from_node_ref'] ], $nodes[ $edge['to_node_ref'] ] ) || $edge['from_node_ref'] === $edge['to_node_ref'] || ! in_array( $edge['type'], Tra_Vel_Trip_Dependency_Taxonomy::DEPENDENCY_TYPES, true ) || ! in_array( $edge['criticality'], Tra_Vel_Trip_Dependency_Taxonomy::CRITICALITIES, true ) || ! is_int( $edge['min_buffer_minutes'] ) || $edge['min_buffer_minutes'] < 0 || $edge['min_buffer_minutes'] > 10080 || ! self::code_list( $edge['constraint_codes'], false ) || ! is_bool( $edge['active'] ) ) {
				return self::error( 'graph_edge_invalid', 'A dependency edge is malformed, duplicated, or references an unknown node.' );
			}
			$buffer_types = array( 'temporal_buffer', 'geographic_handoff', 'arrival_enables', 'departure_requires' );
			if ( in_array( $edge['type'], $buffer_types, true ) && $edge['min_buffer_minutes'] < 1 ) {
				return self::error( 'graph_edge_buffer_invalid', 'A timed handoff requires a positive safety buffer.' );
			}
			if ( ! in_array( $edge['type'], $buffer_types, true ) && 0 !== $edge['min_buffer_minutes'] ) {
				return self::error( 'graph_edge_buffer_orphaned', 'A non-temporal dependency cannot carry a timing buffer.' );
			}
			if ( 'geographic_handoff' === $edge['type'] ) {
				$handoff = $edge['geographic_handoff'];
				if ( ! self::exact_object( $handoff, array( 'departure_location_digest', 'arrival_location_digest', 'mode', 'feasibility_state', 'evidence_digest' ) ) || ! self::digest( $handoff['departure_location_digest'] ) || ! self::digest( $handoff['arrival_location_digest'] ) || ! self::code( $handoff['mode'] ) || ! in_array( $handoff['feasibility_state'], Tra_Vel_Trip_Dependency_Taxonomy::FEASIBILITY_STATES, true ) || ! self::nullable_digest( $handoff['evidence_digest'] ) || $handoff['departure_location_digest'] !== $nodes[ $edge['from_node_ref'] ]['geography']['destination_location_digest'] || $handoff['arrival_location_digest'] !== $nodes[ $edge['to_node_ref'] ]['geography']['origin_location_digest'] || ( 'verified' === $handoff['feasibility_state'] && ! self::digest( $handoff['evidence_digest'] ) ) || ( 'verified' !== $handoff['feasibility_state'] && null !== $handoff['evidence_digest'] ) ) {
					return self::error( 'graph_handoff_invalid', 'A geographic handoff must bind exact node endpoints and evidence.' );
				}
			} elseif ( null !== $edge['geographic_handoff'] ) {
				return self::error( 'graph_handoff_orphaned', 'Only a geographic handoff may carry geographic evidence.' );
			}
			if ( in_array( $edge['type'], array( 'temporal_before', 'temporal_buffer', 'geographic_handoff', 'arrival_enables', 'departure_requires' ), true ) ) {
				$available = ( strtotime( $nodes[ $edge['to_node_ref'] ]['service_window']['starts_at'] ) - strtotime( $nodes[ $edge['from_node_ref'] ]['service_window']['ends_at'] ) ) / 60;
				if ( $available < $edge['min_buffer_minutes'] ) {
					return self::error( 'graph_temporal_impossible', 'A dependency claims more transfer buffer than the itinerary actually contains.' );
				}
			}
			$seen[ $edge['edge_ref'] ] = true;
			if ( $edge['active'] ) {
				$adjacency[ $edge['from_node_ref'] ][] = $edge['to_node_ref'];
			}
		}
		if ( self::has_cycle( $adjacency ) ) {
			return self::error( 'graph_cycle_invalid', 'The active itinerary dependency graph must be acyclic.' );
		}
		return $edges;
	}

	private static function constraints( $constraints, $nodes, $flags, $now ) {
		if ( ! is_array( $constraints ) || array_values( $constraints ) !== $constraints || ! $constraints || count( $constraints ) > 64 ) {
			return self::error( 'graph_constraints_invalid', 'A full trip graph requires bounded traveler and authority constraints.' );
		}
		$seen = array();
		$types = array();
		foreach ( $constraints as $constraint ) {
			$keys = array( 'constraint_ref', 'subject_ref', 'type', 'required_node_refs', 'state', 'evidence_digest', 'verified_at', 'valid_until', 'vault_pointer_ref' );
			if ( ! self::exact_object( $constraint, $keys ) || ! self::ref( $constraint['constraint_ref'], 'constraint' ) || isset( $seen[ $constraint['constraint_ref'] ] ) || ! self::ref( $constraint['subject_ref'], 'subject' ) || ! in_array( $constraint['type'], Tra_Vel_Trip_Dependency_Taxonomy::CONSTRAINT_TYPES, true ) || ! self::ref_list( $constraint['required_node_refs'], 'node', true ) || ! in_array( $constraint['state'], Tra_Vel_Trip_Dependency_Taxonomy::CONSTRAINT_STATES, true ) || ! self::nullable_digest( $constraint['evidence_digest'] ) || ( null !== $constraint['verified_at'] && ! self::utc( $constraint['verified_at'] ) ) || ( null !== $constraint['valid_until'] && ! self::utc( $constraint['valid_until'] ) ) || ( null !== $constraint['vault_pointer_ref'] && ! self::vault_ref( $constraint['vault_pointer_ref'] ) ) ) {
				return self::error( 'graph_constraint_invalid', 'A traveler constraint is malformed, duplicated, or unsupported.' );
			}
			foreach ( $constraint['required_node_refs'] as $node_ref ) {
				if ( ! isset( $nodes[ $node_ref ] ) ) {
					return self::error( 'graph_constraint_node_unknown', 'A traveler constraint references a node outside the graph.' );
				}
			}
			if ( 'verified_current' === $constraint['state'] && ( ! self::digest( $constraint['evidence_digest'] ) || null === $constraint['verified_at'] || null === $constraint['valid_until'] || $constraint['valid_until'] <= $constraint['verified_at'] || ( null !== $now && $constraint['valid_until'] <= $now ) ) ) {
				return self::error( 'graph_constraint_evidence_invalid', 'A current traveler constraint requires current evidence.' );
			}
			if ( 'stale' === $constraint['state'] && ( ! self::digest( $constraint['evidence_digest'] ) || null === $constraint['verified_at'] || null === $constraint['valid_until'] || ( null !== $now && $constraint['valid_until'] > $now ) ) ) {
				return self::error( 'graph_constraint_stale_invalid', 'A stale constraint must prove its expired evidence boundary.' );
			}
			if ( in_array( $constraint['state'], array( 'required', 'missing', 'not_applicable' ), true ) && ( null !== $constraint['evidence_digest'] || null !== $constraint['verified_at'] || null !== $constraint['valid_until'] ) ) {
				return self::error( 'graph_constraint_orphan_evidence', 'An unverified traveler constraint cannot carry verification evidence.' );
			}
			if ( 'restricted_health_evidence' === $constraint['type'] && null === $constraint['vault_pointer_ref'] ) {
				return self::error( 'graph_constraint_vault_required', 'Restricted health evidence must remain behind an opaque vault pointer.' );
			}
			$seen[ $constraint['constraint_ref'] ] = true;
			$types[ $constraint['type'] ] = true;
		}
		$required = array( 'traveler_manifest' );
		if ( $flags['minor_present'] ) {
			$required[] = 'minor_guardian_authority';
		}
		if ( $flags['dependent_adult_present'] ) {
			$required[] = 'dependent_adult_authority';
		}
		if ( $flags['accessibility_required'] ) {
			$required[] = 'accessibility_match';
		}
		foreach ( $required as $type ) {
			if ( ! isset( $types[ $type ] ) ) {
				return self::error( 'graph_constraint_coverage_incomplete', 'Party flags require explicit manifest, authority, and accessibility constraints.' );
			}
		}
		return $constraints;
	}

	private static function ledger( $ledger, $graph, $now ) {
		$keys = array( 'events', 'ledger_digest', 'duplicate_count', 'out_of_order_event_refs', 'stale_response_event_refs' );
		if ( ! self::exact_object( $ledger, $keys ) || ! is_array( $ledger['events'] ) || array_values( $ledger['events'] ) !== $ledger['events'] || ! $ledger['events'] || count( $ledger['events'] ) > self::MAX_EVENTS || ! self::digest( $ledger['ledger_digest'] ) || ! is_int( $ledger['duplicate_count'] ) || $ledger['duplicate_count'] < 0 || ! self::ref_list( $ledger['out_of_order_event_refs'], 'trip_event', false ) || ! self::ref_list( $ledger['stale_response_event_refs'], 'trip_event', false ) ) {
			return self::error( 'recovery_ledger_invalid', 'The recovery event ledger is malformed.' );
		}
		$refs = array();
		$idempotency = array();
		$last_sequence = 0;
		foreach ( $ledger['events'] as $event ) {
			$valid = self::event( $event, $graph, $now );
			if ( is_wp_error( $valid ) || isset( $refs[ $event['event_ref'] ] ) || isset( $idempotency[ $event['idempotency_digest'] ] ) || $event['sequence'] <= $last_sequence ) {
				return self::error( 'recovery_ledger_event_invalid', 'Accepted recovery events must be valid, unique, and monotonically ordered.' );
			}
			$refs[ $event['event_ref'] ] = true;
			$idempotency[ $event['idempotency_digest'] ] = true;
			$last_sequence = $event['sequence'];
		}
		foreach ( array_merge( $ledger['out_of_order_event_refs'], $ledger['stale_response_event_refs'] ) as $ref ) {
			if ( ! isset( $refs[ $ref ] ) && in_array( $ref, $ledger['stale_response_event_refs'], true ) ) {
				return self::error( 'recovery_ledger_reference_invalid', 'A stale-response marker must refer to an accepted event.' );
			}
		}
		if ( ! hash_equals( self::canonical_digest( $ledger['events'] ), $ledger['ledger_digest'] ) ) {
			return self::error( 'recovery_ledger_digest_invalid', 'The immutable event ledger digest does not match its accepted events.' );
		}
		return true;
	}

	private static function impact( $impact, $nodes ) {
		$keys = array( 'direct_node_refs', 'transitive_node_refs', 'unaffected_node_refs', 'affected_verticals', 'critical_deadline_refs', 'blast_radius_count' );
		if ( ! self::exact_object( $impact, $keys ) || ! self::ref_list( $impact['direct_node_refs'], 'node', true ) || ! self::ref_list( $impact['transitive_node_refs'], 'node', false ) || ! self::ref_list( $impact['unaffected_node_refs'], 'node', false ) || ! self::enum_list( $impact['affected_verticals'], Tra_Vel_Trip_Dependency_Taxonomy::VERTICALS, true ) || ! self::ref_list( $impact['critical_deadline_refs'], 'deadline', false ) || ! is_int( $impact['blast_radius_count'] ) || $impact['blast_radius_count'] < 1 ) {
			return self::error( 'recovery_impact_invalid', 'The calculated incident blast radius is malformed.' );
		}
		$all_affected = array_merge( $impact['direct_node_refs'], $impact['transitive_node_refs'] );
		if ( count( $all_affected ) !== count( array_unique( $all_affected ) ) || $impact['blast_radius_count'] !== count( $all_affected ) || array_intersect( $all_affected, $impact['unaffected_node_refs'] ) ) {
			return self::error( 'recovery_impact_overlap', 'Direct, transitive, and unaffected node sets must be disjoint.' );
		}
		$partition = array_merge( $all_affected, $impact['unaffected_node_refs'] );
		if ( count( $partition ) !== count( $nodes ) || array_diff( array_keys( $nodes ), $partition ) || array_diff( $partition, array_keys( $nodes ) ) ) {
			return self::error( 'recovery_impact_partition_invalid', 'The blast-radius partition must account for every graph node exactly once.' );
		}
		$verticals = array();
		$deadlines = array();
		foreach ( $all_affected as $node_ref ) {
			$verticals[ $nodes[ $node_ref ]['vertical'] ] = true;
			foreach ( $nodes[ $node_ref ]['deadlines'] as $deadline ) {
				if ( 'open' === $deadline['state'] ) {
					$deadlines[ $deadline['deadline_ref'] ] = true;
				}
			}
		}
		if ( array_diff( $impact['affected_verticals'], array_keys( $verticals ) ) || array_diff( array_keys( $verticals ), $impact['affected_verticals'] ) || array_diff( $impact['critical_deadline_refs'], array_keys( $deadlines ) ) ) {
			return self::error( 'recovery_impact_projection_invalid', 'Affected verticals and deadlines must derive from the impacted nodes.' );
		}
		return true;
	}

	private static function derived_impact( $impact, $events, $edges, $nodes ) {
		$direct = array();
		foreach ( $events as $event ) {
			foreach ( $event['affected_node_refs'] as $node_ref ) {
				$direct[ $node_ref ] = true;
			}
		}
		$adjacency = array();
		foreach ( array_keys( $nodes ) as $node_ref ) {
			$adjacency[ $node_ref ] = array();
		}
		foreach ( $edges as $edge ) {
			if ( $edge['active'] ) {
				$adjacency[ $edge['from_node_ref'] ][] = $edge['to_node_ref'];
			}
		}
		$transitive = array();
		$queue = array_keys( $direct );
		while ( $queue ) {
			$current = array_shift( $queue );
			foreach ( $adjacency[ $current ] as $next ) {
				if ( ! isset( $direct[ $next ] ) && ! isset( $transitive[ $next ] ) ) {
					$transitive[ $next ] = true;
					$queue[] = $next;
				}
			}
		}
		$unaffected = array_diff( array_keys( $nodes ), array_keys( $direct + $transitive ) );
		$expected_direct = array_keys( $direct );
		$expected_transitive = array_keys( $transitive );
		sort( $expected_direct, SORT_STRING );
		sort( $expected_transitive, SORT_STRING );
		sort( $unaffected, SORT_STRING );
		$observed_direct = $impact['direct_node_refs'];
		$observed_transitive = $impact['transitive_node_refs'];
		$observed_unaffected = $impact['unaffected_node_refs'];
		sort( $observed_direct, SORT_STRING );
		sort( $observed_transitive, SORT_STRING );
		sort( $observed_unaffected, SORT_STRING );
		if ( $expected_direct !== $observed_direct || $expected_transitive !== $observed_transitive || $unaffected !== $observed_unaffected ) {
			return self::error( 'recovery_impact_not_derived', 'The declared blast radius does not derive from accepted events and active graph edges.' );
		}
		return true;
	}

	private static function triage( $triage, $impact, $ledger, $plan_state, $now ) {
		$keys = array( 'severity', 'state', 'reason_codes', 'human_escalation_required', 'safety_handoff_required', 'next_review_at' );
		$terminal = in_array( $plan_state, array( 'verified', 'closed_with_loss' ), true );
		if ( ! self::exact_object( $triage, $keys ) || ! in_array( $triage['severity'], Tra_Vel_Trip_Dependency_Taxonomy::TRIAGE_SEVERITIES, true ) || ! in_array( $triage['state'], Tra_Vel_Trip_Dependency_Taxonomy::TRIAGE_STATES, true ) || ! self::code_list( $triage['reason_codes'], true ) || ! is_bool( $triage['human_escalation_required'] ) || ! is_bool( $triage['safety_handoff_required'] ) || ! self::utc( $triage['next_review_at'] ) || ( null !== $now && ! $terminal && $triage['next_review_at'] <= $now ) ) {
			return self::error( 'recovery_triage_invalid', 'The recovery triage projection is invalid or has no future review deadline.' );
		}
		if ( $triage['safety_handoff_required'] && ( 'P0' !== $triage['severity'] || 'safety_handoff_required' !== $triage['state'] || ! $triage['human_escalation_required'] ) ) {
			return self::error( 'recovery_safety_triage_invalid', 'Safety handoff requires P0 priority and human escalation.' );
		}
		if ( ( $ledger['out_of_order_event_refs'] || $ledger['stale_response_event_refs'] ) && ! $triage['human_escalation_required'] ) {
			return self::error( 'recovery_uncertainty_escalation_missing', 'Out-of-order or stale supplier evidence requires human review.' );
		}
		return true;
	}

	private static function gates( $gates, $now ) {
		if ( ! is_array( $gates ) || array_values( $gates ) !== $gates || count( $gates ) !== count( Tra_Vel_Trip_Dependency_Taxonomy::GATE_CODES ) ) {
			return self::error( 'recovery_gates_invalid', 'Every independent customer, financial, document, authority, accessibility, supplier, and human gate is required.' );
		}
		$map = array();
		foreach ( $gates as $gate ) {
			if ( ! self::exact_object( $gate, array( 'code', 'state', 'evidence_digest', 'verified_at' ) ) || ! in_array( $gate['code'], Tra_Vel_Trip_Dependency_Taxonomy::GATE_CODES, true ) || isset( $map[ $gate['code'] ] ) || ! in_array( $gate['state'], Tra_Vel_Trip_Dependency_Taxonomy::GATE_STATES, true ) || ! self::nullable_digest( $gate['evidence_digest'] ) || ( null !== $gate['verified_at'] && ! self::utc( $gate['verified_at'] ) ) ) {
				return self::error( 'recovery_gate_invalid', 'A recovery gate is malformed or duplicated.' );
			}
			if ( 'satisfied' === $gate['state'] && ( ! self::digest( $gate['evidence_digest'] ) || null === $gate['verified_at'] || ( null !== $now && $gate['verified_at'] > $now ) ) ) {
				return self::error( 'recovery_gate_evidence_invalid', 'A satisfied gate requires current immutable evidence.' );
			}
			if ( 'satisfied' !== $gate['state'] && 'stale' !== $gate['state'] && 'conflict' !== $gate['state'] && ( null !== $gate['evidence_digest'] || null !== $gate['verified_at'] ) ) {
				return self::error( 'recovery_gate_orphan_evidence', 'An unresolved gate cannot claim satisfaction evidence.' );
			}
			if ( in_array( $gate['state'], array( 'stale', 'conflict' ), true ) && ! self::digest( $gate['evidence_digest'] ) ) {
				return self::error( 'recovery_gate_conflict_evidence_missing', 'Stale or conflicting gate state requires evidence for human review.' );
			}
			$map[ $gate['code'] ] = $gate;
		}
		foreach ( Tra_Vel_Trip_Dependency_Taxonomy::GATE_CODES as $code ) {
			if ( ! isset( $map[ $code ] ) ) {
				return self::error( 'recovery_gate_coverage_incomplete', 'A mandatory independent recovery gate is absent.' );
			}
		}
		return $map;
	}

	private static function candidates( $candidates, $impact, $gates, $now ) {
		if ( ! is_array( $candidates ) || array_values( $candidates ) !== $candidates || ! $candidates || count( $candidates ) > 32 ) {
			return self::error( 'recovery_candidates_invalid', 'Recovery planning requires a bounded option set.' );
		}
		$map = array();
		$affected = array_merge( $impact['direct_node_refs'], $impact['transitive_node_refs'] );
		foreach ( $candidates as $candidate ) {
			$keys = array( 'candidate_ref', 'strategy', 'state', 'restores_node_refs', 'changes_node_refs', 'required_gate_codes', 'risk_codes', 'planning_evidence_digest', 'evidence_valid_until', 'financial_effect', 'authorization_effect', 'supplier_action_claimed' );
			if ( ! self::exact_object( $candidate, $keys ) || ! self::ref( $candidate['candidate_ref'], 'candidate' ) || isset( $map[ $candidate['candidate_ref'] ] ) || ! in_array( $candidate['strategy'], Tra_Vel_Trip_Dependency_Taxonomy::STRATEGIES, true ) || ! in_array( $candidate['state'], Tra_Vel_Trip_Dependency_Taxonomy::CANDIDATE_STATES, true ) || ! self::ref_list( $candidate['restores_node_refs'], 'node', true ) || ! self::ref_list( $candidate['changes_node_refs'], 'node', true ) || ! self::enum_list( $candidate['required_gate_codes'], Tra_Vel_Trip_Dependency_Taxonomy::GATE_CODES, true ) || ! self::code_list( $candidate['risk_codes'], true ) || ! self::digest( $candidate['planning_evidence_digest'] ) || ! self::utc( $candidate['evidence_valid_until'] ) || 'none' !== $candidate['authorization_effect'] || false !== $candidate['supplier_action_claimed'] ) {
				return self::error( 'recovery_candidate_invalid', 'A recovery option is malformed or improperly claims authorization or supplier action.' );
			}
			if ( array_diff( $candidate['restores_node_refs'], $affected ) || array_diff( $candidate['changes_node_refs'], $affected ) ) {
				return self::error( 'recovery_candidate_scope_invalid', 'A recovery candidate can only restore or change nodes inside the calculated blast radius.' );
			}
			foreach ( $candidate['required_gate_codes'] as $gate_code ) {
				if ( ! isset( $gates[ $gate_code ] ) || 'not_applicable' === $gates[ $gate_code ]['state'] ) {
					return self::error( 'recovery_candidate_gate_invalid', 'A candidate cannot require a gate declared not applicable by the plan.' );
				}
			}
			if ( null !== $now && 'stale' === $candidate['state'] && $candidate['evidence_valid_until'] > $now ) {
				return self::error( 'recovery_candidate_stale_invalid', 'A stale candidate must have an expired planning-evidence window.' );
			}
			if ( null !== $now && 'eligible_for_review' === $candidate['state'] && $candidate['evidence_valid_until'] <= $now ) {
				return self::error( 'recovery_candidate_freshness_invalid', 'An eligible candidate cannot rely on expired planning evidence.' );
			}
			$financial = $candidate['financial_effect'];
			if ( ! self::exact_object( $financial, array( 'state', 'evidence_digest' ) ) || ! in_array( $financial['state'], Tra_Vel_Trip_Dependency_Taxonomy::FINANCIAL_STATES, true ) || ! self::nullable_digest( $financial['evidence_digest'] ) || ( in_array( $financial['state'], array( 'balanced', 'disputed', 'closed_with_loss' ), true ) && ! self::digest( $financial['evidence_digest'] ) ) || ( in_array( $financial['state'], array( 'not_required', 'pending', 'uncertain' ), true ) && null !== $financial['evidence_digest'] ) ) {
				return self::error( 'recovery_candidate_financial_invalid', 'A candidate financial effect cannot claim an unsupported amount or resolution.' );
			}
			$map[ $candidate['candidate_ref'] ] = $candidate;
		}
		return $map;
	}

	private static function completion( $completion, $impact, $selected_ref, $plan_state, $gates, $updated_at ) {
		$keys = array( 'state', 'restored_node_refs', 'closed_loss_node_refs', 'supplier_verification_digest', 'itinerary_revalidation_digest', 'deadline_outcomes_digest', 'financial_state', 'financial_evidence_digest', 'customer_notification_digest', 'verified_at', 'authorization_effect', 'supplier_action_claimed' );
		if ( ! self::exact_object( $completion, $keys ) || ! in_array( $completion['state'], Tra_Vel_Trip_Dependency_Taxonomy::COMPLETION_STATES, true ) || ! self::ref_list( $completion['restored_node_refs'], 'node', false ) || ! self::ref_list( $completion['closed_loss_node_refs'], 'node', false ) || ! self::nullable_digest( $completion['supplier_verification_digest'] ) || ! self::nullable_digest( $completion['itinerary_revalidation_digest'] ) || ! self::nullable_digest( $completion['deadline_outcomes_digest'] ) || ! in_array( $completion['financial_state'], Tra_Vel_Trip_Dependency_Taxonomy::FINANCIAL_STATES, true ) || ! self::nullable_digest( $completion['financial_evidence_digest'] ) || ! self::nullable_digest( $completion['customer_notification_digest'] ) || ( null !== $completion['verified_at'] && ! self::utc( $completion['verified_at'] ) ) || 'none' !== $completion['authorization_effect'] || false !== $completion['supplier_action_claimed'] ) {
			return self::error( 'recovery_completion_invalid', 'The recovery completion proof is malformed or overclaims execution.' );
		}
		$terminal = in_array( $completion['state'], array( 'verified', 'closed_with_loss' ), true );
		if ( ! $terminal ) {
			if ( $completion['restored_node_refs'] || $completion['closed_loss_node_refs'] || null !== $completion['supplier_verification_digest'] || null !== $completion['itinerary_revalidation_digest'] || null !== $completion['deadline_outcomes_digest'] || null !== $completion['financial_evidence_digest'] || null !== $completion['customer_notification_digest'] || null !== $completion['verified_at'] ) {
				return self::error( 'recovery_completion_orphan_evidence', 'A non-terminal recovery cannot carry completion proof.' );
			}
			return true;
		}
		if ( null === $selected_ref || ( 'verified' === $completion['state'] && 'verified' !== $plan_state ) || ( 'closed_with_loss' === $completion['state'] && 'closed_with_loss' !== $plan_state ) ) {
			return self::error( 'recovery_completion_state_invalid', 'Terminal completion must match the selected plan state.' );
		}
		foreach ( $gates as $gate ) {
			if ( ! in_array( $gate['state'], array( 'satisfied', 'not_applicable' ), true ) ) {
				return self::error( 'recovery_completion_gate_invalid', 'Recovery completion cannot be verified while a gate is unresolved.' );
			}
		}
		$affected = array_merge( $impact['direct_node_refs'], $impact['transitive_node_refs'] );
		$resolved = array_merge( $completion['restored_node_refs'], $completion['closed_loss_node_refs'] );
		if ( count( $resolved ) !== count( array_unique( $resolved ) ) || array_diff( $affected, $resolved ) || array_diff( $resolved, $affected ) || ( 'verified' === $completion['state'] && $completion['closed_loss_node_refs'] ) ) {
			return self::error( 'recovery_completion_partition_invalid', 'Every impacted node must be independently restored or explicitly closed with loss.' );
		}
		if ( ! self::digest( $completion['supplier_verification_digest'] ) || ! self::digest( $completion['itinerary_revalidation_digest'] ) || ! self::digest( $completion['deadline_outcomes_digest'] ) || ! self::digest( $completion['customer_notification_digest'] ) || ! self::utc( $completion['verified_at'] ) || $completion['verified_at'] < $updated_at ) {
			return self::error( 'recovery_completion_proof_invalid', 'Terminal recovery requires supplier, itinerary, deadline, notification, and clock proof.' );
		}
		if ( ! in_array( $completion['financial_state'], array( 'not_required', 'balanced', 'closed_with_loss' ), true ) || ( 'not_required' === $completion['financial_state'] && null !== $completion['financial_evidence_digest'] ) || ( 'not_required' !== $completion['financial_state'] && ! self::digest( $completion['financial_evidence_digest'] ) ) ) {
			return self::error( 'recovery_completion_financial_invalid', 'Terminal recovery requires a final, evidenced financial outcome.' );
		}
		return true;
	}

	private static function projection_action( $gates ) {
		$priority = array( 'human_operator_review', 'guardian_or_dependent_authority', 'document_admissibility', 'accessibility_supplier_ack', 'financial_authority', 'customer_consent', 'supplier_truth' );
		$states = array();
		foreach ( $gates as $gate ) {
			$states[ $gate['code'] ] = $gate['state'];
		}
		foreach ( $priority as $code ) {
			if ( isset( $states[ $code ] ) && ! in_array( $states[ $code ], array( 'satisfied', 'not_applicable' ), true ) ) {
				return $code;
			}
		}
		return 'none';
	}

	private static function has_cycle( $adjacency ) {
		$state = array();
		$visit = function ( $node ) use ( &$visit, &$state, $adjacency ) {
			if ( isset( $state[ $node ] ) && 1 === $state[ $node ] ) {
				return true;
			}
			if ( isset( $state[ $node ] ) && 2 === $state[ $node ] ) {
				return false;
			}
			$state[ $node ] = 1;
			foreach ( $adjacency[ $node ] as $next ) {
				if ( $visit( $next ) ) {
					return true;
				}
			}
			$state[ $node ] = 2;
			return false;
		};
		foreach ( array_keys( $adjacency ) as $node ) {
			if ( $visit( $node ) ) {
				return true;
			}
		}
		return false;
	}

	private static function exact_flags( $flags ) {
		if ( ! self::exact_object( $flags, Tra_Vel_Trip_Dependency_Taxonomy::PARTY_FLAGS ) ) {
			return false;
		}
		foreach ( $flags as $flag ) {
			if ( ! is_bool( $flag ) ) {
				return false;
			}
		}
		return true;
	}

	private static function private_boundary( $boundary ) {
		$keys = array( 'server_only', 'public_serialization_allowed', 'raw_identity_data_stored', 'raw_payment_data_stored', 'raw_medical_data_stored', 'raw_supplier_payload_stored', 'bearer_secret_stored', 'vault_pointers_only', 'supplier_action_claimed' );
		return self::exact_object( $boundary, $keys ) && true === $boundary['server_only'] && false === $boundary['public_serialization_allowed'] && false === $boundary['raw_identity_data_stored'] && false === $boundary['raw_payment_data_stored'] && false === $boundary['raw_medical_data_stored'] && false === $boundary['raw_supplier_payload_stored'] && false === $boundary['bearer_secret_stored'] && true === $boundary['vault_pointers_only'] && false === $boundary['supplier_action_claimed'];
	}

	private static function event_boundary( $boundary ) {
		$keys = array( 'raw_message_stored', 'raw_identity_data_stored', 'raw_payment_data_stored', 'raw_medical_data_stored', 'raw_supplier_payload_stored', 'bearer_secret_stored', 'authorization_effect', 'supplier_action_claimed' );
		return self::exact_object( $boundary, $keys ) && false === $boundary['raw_message_stored'] && false === $boundary['raw_identity_data_stored'] && false === $boundary['raw_payment_data_stored'] && false === $boundary['raw_medical_data_stored'] && false === $boundary['raw_supplier_payload_stored'] && false === $boundary['bearer_secret_stored'] && 'none' === $boundary['authorization_effect'] && false === $boundary['supplier_action_claimed'];
	}

	private static function recovery_boundary( $boundary ) {
		$keys = array( 'server_only', 'public_serialization_allowed', 'planning_only', 'execution_dispatched', 'processor_called', 'raw_identity_data_stored', 'raw_payment_data_stored', 'raw_medical_data_stored', 'raw_supplier_payload_stored', 'bearer_secret_stored', 'supplier_action_claimed' );
		return self::exact_object( $boundary, $keys ) && true === $boundary['server_only'] && false === $boundary['public_serialization_allowed'] && true === $boundary['planning_only'] && false === $boundary['execution_dispatched'] && false === $boundary['processor_called'] && false === $boundary['raw_identity_data_stored'] && false === $boundary['raw_payment_data_stored'] && false === $boundary['raw_medical_data_stored'] && false === $boundary['raw_supplier_payload_stored'] && false === $boundary['bearer_secret_stored'] && false === $boundary['supplier_action_claimed'];
	}

	private static function privacy_safe( $value ) {
		$forbidden = '/^(?:name|first_name|last_name|email|phone|address|passport|passport_number|identity_number|diagnosis|medical_history|medical_narrative|card_number|card_pan|pan|cvv|cvc|password|secret|token|bearer_token|activation_code|iccid|raw_message|raw_provider_payload|raw_supplier_payload|raw_payment_data)$/i';
		if ( ! is_array( $value ) ) {
			return true;
		}
		foreach ( $value as $key => $child ) {
			if ( is_string( $key ) && preg_match( $forbidden, $key ) ) {
				return false;
			}
			if ( ! self::privacy_safe( $child ) ) {
				return false;
			}
		}
		return true;
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private static function vault_ref( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^vaultptr_[a-z0-9_]{16,120}$/', $value );
	}

	private static function ref_list( $values, $kind, $required ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( $required && ! $values ) || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! self::ref( $value, $kind ) ) {
				return false;
			}
		}
		return true;
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function nullable_digest( $value ) {
		return null === $value || self::digest( $value );
	}

	private static function digest_list( $values, $required ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( $required && ! $values ) || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! self::digest( $value ) ) {
				return false;
			}
		}
		return true;
	}

	private static function enum_list( $values, $allowed, $required ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( $required && ! $values ) || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! in_array( $value, $allowed, true ) ) {
				return false;
			}
		}
		return true;
	}

	private static function code_list( $values, $required ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( $required && ! $values ) || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! self::code( $value ) ) {
				return false;
			}
		}
		return true;
	}

	private static function code( $value ) {
		return is_string( $value ) && strlen( $value ) <= 96 && 1 === preg_match( '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $value );
	}

	private static function provider_id( $value ) {
		return is_string( $value ) && strlen( $value ) <= 64 && 1 === preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $value );
	}

	private static function utc( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value ) ) {
			return false;
		}
		try {
			$date = new DateTimeImmutable( $value );
		} catch ( Exception $error ) {
			return false;
		}
		return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' ) === $value;
	}

	private static function timezone( $value ) {
		return is_string( $value ) && in_array( $value, timezone_identifiers_list(), true );
	}

	private static function positive_int( $value ) {
		return is_int( $value ) && $value > 0;
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_values( $value ) !== $value ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::canonicalize( $child );
		}
		return $value;
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_trip_dependency_' . $suffix, $message, array( 'status' => 400 ) );
	}
}
