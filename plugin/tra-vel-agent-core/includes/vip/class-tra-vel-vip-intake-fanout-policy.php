<?php
/**
 * Closed policy for private, side-effect-free VIP intake fan-out plans.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_VIP_Intake_Fanout_Policy {
	const CONTRACT_VERSION = '1.1.0';
	const MAX_OBSERVATIONS = 32;
	const MAX_REFS = 64;

	const CASE_FAMILIES = array(
		'lost_card_payment',
		'lost_baggage_flight',
		'medical_insurance_assistance',
		'esim_connectivity',
		'accessibility_failure',
	);

	const OBSERVATION_TYPES = array(
		'payment.card_lost',
		'flight.baggage_lost',
		'insurance.medical_assistance',
		'connectivity.esim_failure',
		'accessibility.assistance_failure',
	);

	const MAPPING_STATES = array( 'verified', 'ambiguous', 'conflicted' );
	const RISK_SIGNALS = array( 'immediate_danger', 'stranded', 'vulnerable_traveler', 'time_critical', 'fraud_exposure', 'offline', 'none' );
	const PRIORITIES = array( 'P0', 'P1', 'P2', 'P3' );

	/** Validate an immutable unique intake-to-trip binding attested upstream. */
	public static function validated_binding( $binding, $now ) {
		$keys = array( 'contract_version', 'intake_ref', 'trip_ref', 'intake_digest', 'match_evidence_digest', 'match_state', 'validated_at', 'binding_digest', 'data_boundary' );
		if ( ! self::exact_object( $binding, $keys ) || self::CONTRACT_VERSION !== $binding['contract_version'] || ! self::ref( $binding['intake_ref'], 'intake' ) || ! self::ref( $binding['trip_ref'], 'trip' ) || ! self::digest( $binding['intake_digest'] ) || ! self::digest( $binding['match_evidence_digest'] ) || 'verified_unique' !== $binding['match_state'] || ! self::utc_at_or_before( $binding['validated_at'], $now ) || ! self::input_boundary( $binding['data_boundary'] ) ) {
			return self::error( 'binding_invalid', 'Fan-out requires an exact, verified, privacy-minimized intake-to-trip binding.' );
		}
		if ( ! hash_equals( self::binding_digest( $binding ), $binding['binding_digest'] ) ) {
			return self::error( 'binding_digest_invalid', 'The intake-to-trip binding digest does not match its immutable content.' );
		}
		return $binding;
	}

	/** Validate one closed normalized observation against the exact bound trip. */
	public static function normalized_observation( $observation, $binding, $now ) {
		$keys = array( 'contract_version', 'observation_ref', 'trip_ref', 'observed_at', 'type', 'mapping_state', 'mapped_case_families', 'risk_signals', 'service_refs', 'dependency_refs', 'evidence', 'idempotency_digest', 'data_boundary' );
		if ( ! self::exact_object( $observation, $keys ) || self::CONTRACT_VERSION !== $observation['contract_version'] || ! self::ref( $observation['observation_ref'], 'observation' ) || ! is_array( $binding ) || ! isset( $binding['trip_ref'] ) || $observation['trip_ref'] !== $binding['trip_ref'] || ! self::utc_at_or_before( $observation['observed_at'], $now ) || ! in_array( $observation['type'], self::OBSERVATION_TYPES, true ) || ! in_array( $observation['mapping_state'], self::MAPPING_STATES, true ) || ! self::digest( $observation['idempotency_digest'] ) || ! self::input_boundary( $observation['data_boundary'] ) ) {
			return self::error( 'observation_invalid', 'A normalized observation must be closed, current, digest-bound, and attached to the exact trip.' );
		}
		if ( ! self::family_list( $observation['mapped_case_families'], true ) || ! self::risk_list( $observation['risk_signals'] ) || ! self::ref_list( $observation['service_refs'], 'service', true ) || ! self::ref_list( $observation['dependency_refs'], 'dependency', true ) ) {
			return self::error( 'observation_scope_invalid', 'Observation mappings, risks, services, and dependencies must use closed bounded vocabularies.' );
		}

		$expected_family = self::family_for_observation_type( $observation['type'] );
		if ( 'verified' === $observation['mapping_state'] ) {
			if ( array( $expected_family ) !== $observation['mapped_case_families'] ) {
				return self::error( 'implicit_multi_mapping_rejected', 'Each normalized observation must map to exactly one case family; one traveler message may produce several separately scoped observations upstream.' );
			}
		} elseif ( array() !== $observation['mapped_case_families'] || array() !== $observation['evidence'] ) {
			return self::error( 'ambiguous_mapping_invalid', 'Ambiguous or conflicted observations cannot preselect cases or evidence partitions.' );
		}

		$evidence = self::evidence_list( $observation['evidence'], $observation['mapped_case_families'] );
		if ( is_wp_error( $evidence ) ) {
			return $evidence;
		}
		if ( 'verified' === $observation['mapping_state'] ) {
			$covered = array();
			foreach ( $observation['evidence'] as $item ) {
				$covered[ $item['allowed_case_families'][0] ] = true;
			}
			if ( self::ordered_families( array_keys( $covered ) ) !== $observation['mapped_case_families'] ) {
				return self::error( 'evidence_coverage_invalid', 'Every explicit case mapping requires its own non-shared evidence partition.' );
			}
		}
		return $observation;
	}

	/** Validate one complete private fan-out result. */
	public static function fanout( $record, $now ) {
		$keys = array( 'contract_version', 'environment', 'fanout_ref', 'fanout_digest', 'binding', 'observation_ledger', 'case_seeds', 'summary', 'created_at', 'private_boundary' );
		if ( ! self::exact_object( $record, $keys ) || self::CONTRACT_VERSION !== $record['contract_version'] || 'sandbox' !== $record['environment'] || ! self::ref( $record['fanout_ref'], 'fanout' ) || ! self::digest( $record['fanout_digest'] ) || ! self::utc_at_or_before( $record['created_at'], $now ) ) {
			return self::error( 'fanout_shape_invalid', 'The private intake fan-out must use the exact sandbox planning contract.' );
		}
		$binding = self::validated_binding( $record['binding'], $now );
		if ( is_wp_error( $binding ) || $record['created_at'] !== $record['binding']['validated_at'] ) {
			return self::error( 'fanout_binding_invalid', 'The fan-out must retain the exact validated binding and its deterministic creation clock.' );
		}
		$ledger = self::observation_ledger( $record['observation_ledger'] );
		if ( is_wp_error( $ledger ) ) {
			return $ledger;
		}

		$accepted_map = array();
		$expected_families = array();
		foreach ( $record['observation_ledger']['accepted'] as $entry ) {
			$accepted_map[ $entry['observation_ref'] ] = $entry['mapped_case_families'];
			foreach ( $entry['mapped_case_families'] as $family ) {
				$expected_families[ $family ] = true;
			}
		}
		$expected_family_list = self::ordered_families( array_keys( $expected_families ) );
		if ( ! self::is_list( $record['case_seeds'] ) || ! $record['case_seeds'] || count( $record['case_seeds'] ) !== count( $expected_family_list ) ) {
			return self::error( 'case_seed_count_invalid', 'Every mapped family must create exactly one case seed, with no duplicate playbook.' );
		}

		$seen_families = array();
		$seen_family_refs = array();
		$seen_seed_refs = array();
		$seen_event_refs = array();
		$seen_playbooks = array();
		$seen_evidence_refs = array();
		$order = array();
		$p0_count = 0;
		$safety_count = 0;
		foreach ( $record['case_seeds'] as $seed ) {
			$valid_seed = self::case_seed( $seed, $record['binding'], $accepted_map );
			if ( is_wp_error( $valid_seed ) ) {
				return $valid_seed;
			}
			if ( isset( $seen_families[ $seed['family'] ] ) || isset( $seen_family_refs[ $seed['family_ref'] ] ) || isset( $seen_seed_refs[ $seed['case_seed_ref'] ] ) || isset( $seen_event_refs[ $seed['family_event_ref'] ] ) || isset( $seen_playbooks[ $seed['playbook_code'] ] ) ) {
				return self::error( 'case_seed_identity_duplicate', 'Families, seeds, family events, and playbooks must each be unique.' );
			}
			$seen_families[ $seed['family'] ] = true;
			$seen_family_refs[ $seed['family_ref'] ] = true;
			$seen_seed_refs[ $seed['case_seed_ref'] ] = true;
			$seen_event_refs[ $seed['family_event_ref'] ] = true;
			$seen_playbooks[ $seed['playbook_code'] ] = true;
			foreach ( $seed['evidence_partition']['evidence_items'] as $item ) {
				if ( isset( $seen_evidence_refs[ $item['evidence_ref'] ] ) ) {
					return self::error( 'evidence_cross_partition_invalid', 'One evidence reference cannot silently enter more than one case partition.' );
				}
				$seen_evidence_refs[ $item['evidence_ref'] ] = $seed['family'];
			}
			$order[] = self::priority_rank( $seed['priority'] ) . '|' . str_pad( (string) array_search( $seed['family'], self::CASE_FAMILIES, true ), 2, '0', STR_PAD_LEFT );
			$p0_count += 'P0' === $seed['priority'] ? 1 : 0;
			$safety_count += $seed['routing']['safety_handoff_required'] ? 1 : 0;
		}
		$sorted_order = $order;
		sort( $sorted_order, SORT_STRING );
		if ( $order !== $sorted_order || self::ordered_families( array_keys( $seen_families ) ) !== $expected_family_list ) {
			return self::error( 'case_seed_order_invalid', 'P0 safety cases must lead a complete deterministic family fan-out without suppressing later seeds.' );
		}

		$summary_keys = array( 'seed_count', 'family_order', 'p0_seed_count', 'safety_seed_count', 'duplicate_observation_count', 'duplicate_playbook_count', 'clarification_required', 'side_effect_count' );
		$summary = $record['summary'];
		$actual_family_order = array();
		foreach ( $record['case_seeds'] as $seed ) {
			$actual_family_order[] = $seed['family'];
		}
		if ( ! self::exact_object( $summary, $summary_keys ) || count( $record['case_seeds'] ) !== $summary['seed_count'] || $actual_family_order !== $summary['family_order'] || $p0_count !== $summary['p0_seed_count'] || $safety_count !== $summary['safety_seed_count'] || $record['observation_ledger']['duplicate_count'] !== $summary['duplicate_observation_count'] || 0 !== $summary['duplicate_playbook_count'] || false !== $summary['clarification_required'] || 0 !== $summary['side_effect_count'] ) {
			return self::error( 'summary_invalid', 'Fan-out summary counts must match the complete non-executing case set.' );
		}
		if ( ! self::private_boundary( $record['private_boundary'] ) ) {
			return self::error( 'boundary_invalid', 'Fan-out must remain server-only planning with every side effect structurally disabled.' );
		}
		$expected_ref = self::ref_value( 'fanout', $record['binding']['binding_digest'] . '|' . $record['observation_ledger']['ledger_digest'] );
		if ( $expected_ref !== $record['fanout_ref'] || ! hash_equals( self::fanout_digest( $record ), $record['fanout_digest'] ) ) {
			return self::error( 'fanout_digest_invalid', 'Fan-out identity or digest is not deterministic for its unique observations.' );
		}
		return $record;
	}

	/** Return immutable behavior for one closed case family. */
	public static function family_config( $family ) {
		$config = array(
			'lost_card_payment' => array(
				'observation_type' => 'payment.card_lost',
				'playbook_code' => 'secure_payment_instrument_and_reconcile',
				'evidence_scope' => 'payment_restricted',
				'restricted' => true,
				'authority_requirement' => 'payment_instrument_control_verification',
				'after_hours_route_code' => 'payment_security_on_call',
				'default_priority' => 'P1',
			),
			'lost_baggage_flight' => array(
				'observation_type' => 'flight.baggage_lost',
				'playbook_code' => 'trace_baggage_and_protect_flight_trip',
				'evidence_scope' => 'flight_baggage_case',
				'restricted' => false,
				'authority_requirement' => 'carrier_claim_or_service_change_step_up',
				'after_hours_route_code' => 'flight_baggage_on_call',
				'default_priority' => 'P1',
			),
			'medical_insurance_assistance' => array(
				'observation_type' => 'insurance.medical_assistance',
				'playbook_code' => 'medical_safety_and_insurance_assistance',
				'evidence_scope' => 'medical_restricted',
				'restricted' => true,
				'authority_requirement' => 'medical_assistance_consent_and_policy_verification',
				'after_hours_route_code' => 'medical_assistance_on_call',
				'default_priority' => 'P1',
			),
			'esim_connectivity' => array(
				'observation_type' => 'connectivity.esim_failure',
				'playbook_code' => 'restore_esim_and_offline_connectivity',
				'evidence_scope' => 'connectivity_case',
				'restricted' => false,
				'authority_requirement' => 'connectivity_purchase_or_profile_change_step_up',
				'after_hours_route_code' => 'connectivity_support_on_call',
				'default_priority' => 'P2',
			),
			'accessibility_failure' => array(
				'observation_type' => 'accessibility.assistance_failure',
				'playbook_code' => 'restore_accessibility_assistance',
				'evidence_scope' => 'accessibility_restricted',
				'restricted' => true,
				'authority_requirement' => 'accessibility_assistance_consent',
				'after_hours_route_code' => 'accessibility_support_on_call',
				'default_priority' => 'P1',
			),
		);
		return isset( $config[ $family ] ) ? $config[ $family ] : null;
	}

	public static function ordered_families( $families ) {
		return array_values( array_intersect( self::CASE_FAMILIES, array_values( array_unique( $families ) ) ) );
	}

	public static function ordered_risks( $risks ) {
		return array_values( array_intersect( self::RISK_SIGNALS, array_values( array_unique( $risks ) ) ) );
	}

	public static function priority_for( $family, $risk_signals ) {
		if ( in_array( 'immediate_danger', $risk_signals, true ) ) {
			return 'P0';
		}
		$config = self::family_config( $family );
		return null === $config ? null : $config['default_priority'];
	}

	public static function priority_rank( $priority ) {
		$rank = array_search( $priority, self::PRIORITIES, true );
		return false === $rank ? 99 : $rank;
	}

	public static function ref_value( $kind, $seed ) {
		return 'tv_' . $kind . '_' . substr( hash( 'sha256', $seed ), 0, 32 );
	}

	public static function binding_digest( $binding ) {
		$value = $binding;
		unset( $value['binding_digest'] );
		return self::canonical_digest( $value );
	}

	public static function observation_digest( $observation ) {
		return self::canonical_digest( $observation );
	}

	public static function case_seed_digest( $seed ) {
		$value = $seed;
		unset( $value['case_seed_digest'] );
		return self::canonical_digest( $value );
	}

	public static function fanout_digest( $fanout ) {
		$value = $fanout;
		unset( $value['fanout_digest'] );
		return self::canonical_digest( $value );
	}

	public static function canonical_digest( $value ) {
		return hash( 'sha256', wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	public static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_vip_intake_fanout_' . $suffix, $message, array( 'status' => 409 ) );
	}

	private static function observation_ledger( $ledger ) {
		$keys = array( 'accepted', 'accepted_count', 'duplicate_count', 'ledger_digest' );
		if ( ! self::exact_object( $ledger, $keys ) || ! self::is_list( $ledger['accepted'] ) || ! $ledger['accepted'] || count( $ledger['accepted'] ) > self::MAX_OBSERVATIONS || ! is_int( $ledger['accepted_count'] ) || $ledger['accepted_count'] !== count( $ledger['accepted'] ) || ! is_int( $ledger['duplicate_count'] ) || $ledger['duplicate_count'] < 0 || ! self::digest( $ledger['ledger_digest'] ) ) {
			return self::error( 'ledger_invalid', 'The observation ledger must be a bounded exact set of unique normalized observations.' );
		}
		$seen_refs = array();
		$seen_idempotency = array();
		$ordered_refs = array();
		foreach ( $ledger['accepted'] as $entry ) {
			$entry_keys = array( 'observation_ref', 'observation_digest', 'idempotency_digest', 'mapped_case_families' );
			if ( ! self::exact_object( $entry, $entry_keys ) || ! self::ref( $entry['observation_ref'], 'observation' ) || ! self::digest( $entry['observation_digest'] ) || ! self::digest( $entry['idempotency_digest'] ) || ! self::family_list( $entry['mapped_case_families'], false ) || 1 !== count( $entry['mapped_case_families'] ) || isset( $seen_refs[ $entry['observation_ref'] ] ) || isset( $seen_idempotency[ $entry['idempotency_digest'] ] ) ) {
				return self::error( 'ledger_entry_invalid', 'Accepted observations must have unique references, idempotency keys, digests, and explicit families.' );
			}
			$seen_refs[ $entry['observation_ref'] ] = true;
			$seen_idempotency[ $entry['idempotency_digest'] ] = true;
			$ordered_refs[] = $entry['observation_ref'];
		}
		$sorted_refs = $ordered_refs;
		sort( $sorted_refs, SORT_STRING );
		if ( $ordered_refs !== $sorted_refs || ! hash_equals( self::canonical_digest( $ledger['accepted'] ), $ledger['ledger_digest'] ) ) {
			return self::error( 'ledger_order_invalid', 'The unique observation ledger and digest must be canonical and order-independent.' );
		}
		return $ledger;
	}

	private static function case_seed( $seed, $binding, $accepted_map ) {
		$keys = array( 'case_seed_ref', 'case_seed_digest', 'family', 'family_ref', 'family_event_ref', 'source_observation_refs', 'risk_signals', 'priority', 'playbook_code', 'evidence_partition', 'authority', 'dependencies', 'routing', 'execution' );
		if ( ! self::exact_object( $seed, $keys ) || ! self::ref( $seed['case_seed_ref'], 'case_seed' ) || ! self::digest( $seed['case_seed_digest'] ) || ! in_array( $seed['family'], self::CASE_FAMILIES, true ) || ! self::ref( $seed['family_ref'], 'case_family' ) || ! self::ref( $seed['family_event_ref'], 'case_event' ) || ! self::ref_list( $seed['source_observation_refs'], 'observation', true ) || ! self::risk_list( $seed['risk_signals'] ) || ! in_array( $seed['priority'], self::PRIORITIES, true ) ) {
			return self::error( 'case_seed_invalid', 'Each case seed requires unique identity, sources, risk, priority, and a closed family.' );
		}
		$config = self::family_config( $seed['family'] );
		foreach ( $seed['source_observation_refs'] as $observation_ref ) {
			if ( ! isset( $accepted_map[ $observation_ref ] ) || ! in_array( $seed['family'], $accepted_map[ $observation_ref ], true ) ) {
				return self::error( 'case_seed_source_invalid', 'A case seed can use only observations explicitly mapped to its family.' );
			}
		}
		$source_refs = $seed['source_observation_refs'];
		$sorted_sources = $source_refs;
		sort( $sorted_sources, SORT_STRING );
		$expected_family_ref = self::ref_value( 'case_family', $binding['binding_digest'] . '|' . $seed['family'] );
		$expected_seed_ref = self::ref_value( 'case_seed', $binding['binding_digest'] . '|' . $seed['family'] );
		$expected_event_ref = self::ref_value( 'case_event', $expected_seed_ref . '|' . implode( '|', $source_refs ) );
		if ( $source_refs !== $sorted_sources || $expected_family_ref !== $seed['family_ref'] || $expected_seed_ref !== $seed['case_seed_ref'] || $expected_event_ref !== $seed['family_event_ref'] || $config['playbook_code'] !== $seed['playbook_code'] || self::priority_for( $seed['family'], $seed['risk_signals'] ) !== $seed['priority'] ) {
			return self::error( 'case_seed_derivation_invalid', 'Case seed identity, event, priority, and playbook must be deterministic.' );
		}

		$partition = $seed['evidence_partition'];
		$partition_keys = array( 'scope', 'evidence_items', 'restricted', 'cross_case_disclosure_allowed', 'partition_digest' );
		if ( ! self::exact_object( $partition, $partition_keys ) || $config['evidence_scope'] !== $partition['scope'] || $config['restricted'] !== $partition['restricted'] || false !== $partition['cross_case_disclosure_allowed'] || ! self::digest( $partition['partition_digest'] ) || ! self::is_list( $partition['evidence_items'] ) || ! $partition['evidence_items'] || count( $partition['evidence_items'] ) > self::MAX_REFS ) {
			return self::error( 'evidence_partition_invalid', 'Each case requires a non-shared evidence partition with its exact sensitivity scope.' );
		}
		$evidence_refs = array();
		foreach ( $partition['evidence_items'] as $item ) {
			if ( ! self::exact_object( $item, array( 'evidence_ref', 'evidence_digest' ) ) || ! self::ref( $item['evidence_ref'], 'evidence' ) || ! self::digest( $item['evidence_digest'] ) || isset( $evidence_refs[ $item['evidence_ref'] ] ) ) {
				return self::error( 'evidence_item_invalid', 'Evidence items must be unique digest-only references.' );
			}
			$evidence_refs[ $item['evidence_ref'] ] = true;
		}
		$ordered_evidence_refs = array_keys( $evidence_refs );
		$sorted_evidence_refs = $ordered_evidence_refs;
		sort( $sorted_evidence_refs, SORT_STRING );
		$partition_basis = array( 'scope' => $partition['scope'], 'evidence_items' => $partition['evidence_items'], 'restricted' => $partition['restricted'], 'cross_case_disclosure_allowed' => false );
		if ( $ordered_evidence_refs !== $sorted_evidence_refs || ! hash_equals( self::canonical_digest( $partition_basis ), $partition['partition_digest'] ) ) {
			return self::error( 'evidence_partition_digest_invalid', 'Evidence partition ordering and digest must be deterministic.' );
		}

		$authority = $seed['authority'];
		if ( ! self::exact_object( $authority, array( 'required', 'requirement_code', 'state', 'execution_authorized' ) ) || true !== $authority['required'] || $config['authority_requirement'] !== $authority['requirement_code'] || 'unverified' !== $authority['state'] || false !== $authority['execution_authorized'] ) {
			return self::error( 'authority_invalid', 'Every seed requires separate unverified authority and grants no execution.' );
		}
		$dependencies = $seed['dependencies'];
		if ( ! self::exact_object( $dependencies, array( 'service_refs', 'dependency_refs', 'preserve_unaffected_services' ) ) || ! self::ref_list( $dependencies['service_refs'], 'service', true ) || ! self::ref_list( $dependencies['dependency_refs'], 'dependency', true ) || true !== $dependencies['preserve_unaffected_services'] ) {
			return self::error( 'dependencies_invalid', 'Each seed must retain explicit impacted services and dependencies while preserving unaffected services.' );
		}
		$routing = $seed['routing'];
		$expected_safety = 'P0' === $seed['priority'];
		if ( ! self::exact_object( $routing, array( 'after_hours_required', 'after_hours_route_code', 'safety_handoff_required', 'safety_route_code', 'operator_review_required', 'dispatch_state' ) ) || true !== $routing['after_hours_required'] || $config['after_hours_route_code'] !== $routing['after_hours_route_code'] || $expected_safety !== $routing['safety_handoff_required'] || ( $expected_safety ? 'emergency_services_and_medical_assistance' !== $routing['safety_route_code'] : null !== $routing['safety_route_code'] ) || true !== $routing['operator_review_required'] || 'not_dispatched' !== $routing['dispatch_state'] ) {
			return self::error( 'routing_invalid', 'After-hours and P0 safety routing must be explicit while dispatch remains disabled.' );
		}
		$execution = $seed['execution'];
		if ( ! self::exact_object( $execution, array( 'supplier_action_executed', 'payment_action_executed', 'claim_action_executed', 'booking_action_executed', 'execution_effect' ) ) || false !== $execution['supplier_action_executed'] || false !== $execution['payment_action_executed'] || false !== $execution['claim_action_executed'] || false !== $execution['booking_action_executed'] || 'none' !== $execution['execution_effect'] ) {
			return self::error( 'execution_invalid', 'A case seed cannot execute supplier, payment, claim, or booking work.' );
		}
		if ( ! hash_equals( self::case_seed_digest( $seed ), $seed['case_seed_digest'] ) ) {
			return self::error( 'case_seed_digest_invalid', 'The case seed digest does not match its closed content.' );
		}
		return $seed;
	}

	private static function family_for_observation_type( $type ) {
		foreach ( self::CASE_FAMILIES as $family ) {
			$config = self::family_config( $family );
			if ( $config['observation_type'] === $type ) {
				return $family;
			}
		}
		return null;
	}

	private static function evidence_list( $items, $mapped_families ) {
		if ( ! self::is_list( $items ) || count( $items ) > self::MAX_REFS ) {
			return self::error( 'evidence_list_invalid', 'Evidence must be a bounded list of digest-only partitions.' );
		}
		$seen = array();
		foreach ( $items as $item ) {
			$keys = array( 'evidence_ref', 'evidence_digest', 'scope', 'allowed_case_families' );
			if ( ! self::exact_object( $item, $keys ) || ! self::ref( $item['evidence_ref'], 'evidence' ) || ! self::digest( $item['evidence_digest'] ) || isset( $seen[ $item['evidence_ref'] ] ) || ! self::family_list( $item['allowed_case_families'], false ) || 1 !== count( $item['allowed_case_families'] ) ) {
				return self::error( 'evidence_invalid', 'Evidence must have one unique reference, digest, scope, and exact case-family disclosure.' );
			}
			$family = $item['allowed_case_families'][0];
			$config = self::family_config( $family );
			if ( ! in_array( $family, $mapped_families, true ) || $config['evidence_scope'] !== $item['scope'] ) {
				return self::error( 'evidence_scope_invalid', 'Restricted medical, payment, accessibility, and other case evidence cannot bleed across partitions.' );
			}
			$seen[ $item['evidence_ref'] ] = true;
		}
		return $items;
	}

	private static function family_list( $values, $allow_empty ) {
		if ( ! self::is_list( $values ) || ( ! $allow_empty && ! $values ) || count( $values ) > count( self::CASE_FAMILIES ) || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		return self::ordered_families( $values ) === $values;
	}

	private static function risk_list( $values ) {
		if ( ! self::is_list( $values ) || ! $values || count( $values ) > count( self::RISK_SIGNALS ) || count( $values ) !== count( array_unique( $values ) ) || self::ordered_risks( $values ) !== $values ) {
			return false;
		}
		return ! in_array( 'none', $values, true ) || array( 'none' ) === $values;
	}

	private static function ref_list( $values, $kind, $required ) {
		if ( ! self::is_list( $values ) || ( $required && ! $values ) || count( $values ) > self::MAX_REFS || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! self::ref( $value, $kind ) ) {
				return false;
			}
		}
		return true;
	}

	private static function input_boundary( $value ) {
		$keys = array( 'raw_message_present', 'raw_identity_data_present', 'raw_payment_data_present', 'raw_medical_data_present', 'raw_supplier_payload_present', 'bearer_secret_present' );
		if ( ! self::exact_object( $value, $keys ) ) {
			return false;
		}
		foreach ( $keys as $key ) {
			if ( false !== $value[ $key ] ) {
				return false;
			}
		}
		return true;
	}

	private static function private_boundary( $value ) {
		$keys = array( 'server_only', 'public_serialization_allowed', 'planning_only', 'storage_written', 'rest_route_registered', 'network_called', 'ai_called', 'supplier_dispatched', 'payment_executed', 'claim_submitted', 'booking_executed', 'authorization_effect' );
		return self::exact_object( $value, $keys ) && true === $value['server_only'] && false === $value['public_serialization_allowed'] && true === $value['planning_only'] && false === $value['storage_written'] && false === $value['rest_route_registered'] && false === $value['network_called'] && false === $value['ai_called'] && false === $value['supplier_dispatched'] && false === $value['payment_executed'] && false === $value['claim_submitted'] && false === $value['booking_executed'] && 'none' === $value['authorization_effect'];
	}

	private static function ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function utc_at_or_before( $value, $now ) {
		$value_epoch = self::canonical_utc_epoch( $value );
		$now_epoch = self::canonical_utc_epoch( $now );
		return false !== $value_epoch && false !== $now_epoch && $value_epoch <= $now_epoch;
	}

	/** Parse only real canonical UTC calendar timestamps. */
	private static function canonical_utc_epoch( $value ) {
		$matches = array();
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])T([01]\d|2[0-3]):([0-5]\d):([0-5]\d)Z$/', $value, $matches ) ) {
			return false;
		}
		$year = (int) $matches[1];
		$month = (int) $matches[2];
		$day = (int) $matches[3];
		if ( ! checkdate( $month, $day, $year ) ) {
			return false;
		}
		$epoch = gmmktime( (int) $matches[4], (int) $matches[5], (int) $matches[6], $month, $day, $year );
		return false !== $epoch && gmdate( 'Y-m-d\\TH:i:s\\Z', $epoch ) === $value ? $epoch : false;
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! self::is_list( $value ) && array_keys( $value ) === $keys;
	}

	private static function is_list( $value ) {
		return is_array( $value ) && array_values( $value ) === $value;
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
