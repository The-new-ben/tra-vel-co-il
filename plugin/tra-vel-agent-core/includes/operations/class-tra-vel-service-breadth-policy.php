<?php
/**
 * Fail-closed policy for the private 360 service breadth registry.
 *
 * This registry proves planning and operational coverage. It does not create
 * inventory, dispatch a supplier, move money, or assert live commercial truth.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Service_Breadth_Policy {
	/**
	 * Build the deterministic private synthetic fixture used by focused CI.
	 *
	 * @return array
	 */
	public static function build_synthetic_fixture() {
		$families   = array_values( Tra_Vel_Service_Breadth_Taxonomy::definitions() );
		$blueprints = Tra_Vel_Service_Breadth_Taxonomy::scenario_blueprints();
		$scenarios  = array();
		$number     = 0;

		foreach ( Tra_Vel_Service_Breadth_Taxonomy::SERVICE_FAMILIES as $family ) {
			foreach ( $blueprints[ $family ] as $slot_index => $blueprint ) {
				$number++;
				$slot = $slot_index + 1;
				$scenarios[] = array(
					'scenario_ref'          => Tra_Vel_Service_Breadth_Taxonomy::synthetic_ref( 'scenario', $family . ':' . $slot ),
					'scenario_number'       => $number,
					'scenario_slot'         => $slot,
					'service_family'        => $family,
					'family_subtype'        => $blueprint['family_subtype'],
					'canonical_vertical'    => Tra_Vel_Service_Breadth_Taxonomy::definition( $family )['canonical_vertical'],
					'trigger'               => $blueprint['trigger'],
					'event_state'           => $blueprint['event_state'],
					'after_hours'           => self::after_hours_contract( $family, $slot, $blueprint ),
					'preservation'          => self::preservation_contract( $family, $slot ),
					'expected_actions'      => $blueprint['expected_actions'],
					'required_deadline_code'=> $blueprint['required_deadline_code'],
					'required_handoff_code' => $blueprint['required_handoff_code'],
					'financial_axes'        => array(
						'payment'                     => 1 === $slot ? 'requires_separate_authorization' : 'existing_authorization_observed',
						'refund'                      => 1 === $slot ? 'quote_required' : 'request_planned',
						'settlement'                  => 1 === $slot ? 'reconciliation_required' : 'accrual_review',
						'netting_prohibited'          => true,
						'independent_ledgers_required'=> true,
					),
					'expected_outcome'      => $blueprint['expected_outcome'],
					'boundary'              => self::scenario_boundary(),
				);
			}
		}

		$fixture = array(
			'contract_version' => Tra_Vel_Service_Breadth_Taxonomy::CONTRACT_VERSION,
			'environment'      => 'sandbox',
			'data_mode'        => 'synthetic_demo',
			'registry_ref'     => Tra_Vel_Service_Breadth_Taxonomy::synthetic_ref( 'registry', 'closed_registry' ),
			'registry_revision'=> 1,
			'family_count'     => count( $families ),
			'scenario_count'   => count( $scenarios ),
			'families'         => $families,
			'scenarios'        => $scenarios,
			'truth_boundary'   => self::truth_boundary(),
			'fixture_digest'   => str_repeat( '0', 64 ),
		);
		$fixture['fixture_digest'] = self::fixture_digest( $fixture );
		return $fixture;
	}

	/**
	 * Validate the full private registry and every adversarial scenario.
	 *
	 * @return array|WP_Error
	 */
	public static function validate_registry( $registry ) {
		$keys = array(
			'contract_version', 'environment', 'data_mode', 'registry_ref',
			'registry_revision', 'family_count', 'scenario_count', 'families',
			'scenarios', 'truth_boundary', 'fixture_digest',
		);
		if ( self::contains_sensitive_material( $registry ) ) {
			return self::error( 'sensitive_material_rejected', 'The breadth registry accepts opaque synthetic references only, never personal data, secrets, supplier payloads, or real identities.' );
		}
		if (
			! self::exact_object( $registry, $keys ) ||
			Tra_Vel_Service_Breadth_Taxonomy::CONTRACT_VERSION !== $registry['contract_version'] ||
			'sandbox' !== $registry['environment'] ||
			'synthetic_demo' !== $registry['data_mode'] ||
			Tra_Vel_Service_Breadth_Taxonomy::synthetic_ref( 'registry', 'closed_registry' ) !== $registry['registry_ref'] ||
			1 !== $registry['registry_revision']
		) {
			return self::error( 'registry_shape_invalid', 'The service breadth registry must match the closed private synthetic contract.' );
		}
		if ( self::canonical_digest( $registry['truth_boundary'] ) !== self::canonical_digest( self::truth_boundary() ) ) {
			return self::error( 'truth_boundary_invalid', 'The registry cannot claim live availability, real prices, supplier identity, dispatch, or commercial authority.' );
		}
		if ( ! is_array( $registry['families'] ) || array_values( $registry['families'] ) !== $registry['families'] || 17 !== count( $registry['families'] ) || 17 !== $registry['family_count'] ) {
			return self::error( 'family_coverage_invalid', 'All 17 explicit service families must be present exactly once.' );
		}

		$expected_families = Tra_Vel_Service_Breadth_Taxonomy::definitions();
		$seen_families     = array();
		foreach ( $registry['families'] as $profile ) {
			$validated = self::validate_family_profile( $profile );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}
			$family = $profile['service_family'];
			if ( isset( $seen_families[ $family ] ) || ! isset( $expected_families[ $family ] ) || self::canonical_digest( $profile ) !== self::canonical_digest( $expected_families[ $family ] ) ) {
				return self::error( 'family_definition_mismatch', 'Each family must match its complete canonical subtype, lifecycle, fact, deadline, handoff, Israel-local, and map profile.' );
			}
			$seen_families[ $family ] = true;
		}
		if ( array_keys( $seen_families ) !== Tra_Vel_Service_Breadth_Taxonomy::SERVICE_FAMILIES ) {
			return self::error( 'family_order_invalid', 'The deterministic registry family order changed.' );
		}

		if ( ! is_array( $registry['scenarios'] ) || array_values( $registry['scenarios'] ) !== $registry['scenarios'] || Tra_Vel_Service_Breadth_Taxonomy::SCENARIO_COUNT !== count( $registry['scenarios'] ) || Tra_Vel_Service_Breadth_Taxonomy::SCENARIO_COUNT !== $registry['scenario_count'] ) {
			return self::error( 'scenario_coverage_invalid', 'The registry requires the exact closed 34-case family-slot matrix.' );
		}
		$seen_refs            = array();
		$seen_numbers         = array();
		$seen_family_slots    = array();
		$seen_route_refs      = array();
		$seen_fallback_routes = array();
		$family_coverage      = array();
		foreach ( Tra_Vel_Service_Breadth_Taxonomy::SERVICE_FAMILIES as $family ) {
			$family_coverage[ $family ] = array( 'slots' => array(), 'states' => array(), 'after_hours' => 0 );
		}
		foreach ( $registry['scenarios'] as $index => $scenario ) {
			$validated = self::validate_scenario( $scenario );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}
			$family_slot = $scenario['service_family'] . ':' . $scenario['scenario_slot'];
			$expected_ref = Tra_Vel_Service_Breadth_Taxonomy::synthetic_ref( 'scenario', $family_slot );
			$expected_family = Tra_Vel_Service_Breadth_Taxonomy::SERVICE_FAMILIES[ intdiv( $index, 2 ) ];
			$expected_slot = ( $index % 2 ) + 1;
			if (
				$scenario['scenario_number'] !== $index + 1 ||
				$scenario['service_family'] !== $expected_family ||
				$scenario['scenario_slot'] !== $expected_slot ||
				$scenario['scenario_ref'] !== $expected_ref ||
				isset( $seen_refs[ $scenario['scenario_ref'] ] ) ||
				isset( $seen_numbers[ $scenario['scenario_number'] ] ) ||
				isset( $seen_family_slots[ $family_slot ] ) ||
				isset( $seen_route_refs[ $scenario['after_hours']['route_ref'] ] ) ||
				isset( $seen_fallback_routes[ $scenario['after_hours']['fallback_route_ref'] ] )
			) {
				return self::error( 'scenario_identity_invalid', 'Scenario number, opaque reference, family-slot, and after-hours routes must be unique and deterministic.' );
			}
			$seen_refs[ $scenario['scenario_ref'] ]        = true;
			$seen_numbers[ $scenario['scenario_number'] ] = true;
			$seen_family_slots[ $family_slot ]             = true;
			$seen_route_refs[ $scenario['after_hours']['route_ref'] ] = true;
			$seen_fallback_routes[ $scenario['after_hours']['fallback_route_ref'] ] = true;
			$coverage = &$family_coverage[ $scenario['service_family'] ];
			$coverage['slots'][ $scenario['scenario_slot'] ] = true;
			$coverage['states'][ $scenario['event_state'] ] = true;
			$coverage['after_hours'] += $scenario['after_hours']['required'] ? 1 : 0;
			unset( $coverage );
		}
		foreach ( $family_coverage as $coverage ) {
			if ( Tra_Vel_Service_Breadth_Taxonomy::SCENARIO_SLOTS !== array_keys( $coverage['slots'] ) || ! isset( $coverage['states']['stale'], $coverage['states']['missed'] ) || 1 !== $coverage['after_hours'] ) {
				return self::error( 'family_scenario_depth_invalid', 'Every family requires one stale case, one missed-event case, and one after-hours escalation.' );
			}
		}

		$expected_digest = self::fixture_digest( $registry );
		if ( ! self::digest( $registry['fixture_digest'] ) || ! hash_equals( $expected_digest, $registry['fixture_digest'] ) ) {
			return self::error( 'fixture_digest_invalid', 'The deterministic synthetic fixture was changed without resealing.' );
		}
		return $registry;
	}

	/** Return the fixture digest with its own field excluded. */
	public static function fixture_digest( $registry ) {
		if ( ! is_array( $registry ) ) {
			return '';
		}
		$basis = $registry;
		unset( $basis['fixture_digest'] );
		return self::canonical_digest( $basis );
	}

	private static function validate_family_profile( $profile ) {
		$keys = array( 'family_ref', 'service_family', 'canonical_vertical', 'family_subtypes', 'crosswalk', 'lifecycle', 'critical_facts', 'critical_deadlines', 'required_handoffs', 'israel_local', 'map' );
		if ( ! self::exact_object( $profile, $keys ) || ! is_string( $profile['service_family'] ) || ! in_array( $profile['service_family'], Tra_Vel_Service_Breadth_Taxonomy::SERVICE_FAMILIES, true ) || ! self::opaque_ref( $profile['family_ref'], 'family' ) || ! in_array( $profile['canonical_vertical'], Tra_Vel_Service_Breadth_Taxonomy::CANONICAL_VERTICALS, true ) ) {
			return self::error( 'family_profile_invalid', 'A service family identity or canonical crosswalk is invalid.' );
		}
		if ( ! self::code_list( $profile['family_subtypes'], 1 ) || ! self::code_list( $profile['critical_facts'], 5 ) || ! self::code_list( $profile['critical_deadlines'], 3 ) || ! self::code_list( $profile['required_handoffs'], 3 ) ) {
			return self::error( 'family_operational_depth_invalid', 'A family needs explicit subtypes plus critical facts, deadlines, and handoffs.' );
		}
		$crosswalk = $profile['crosswalk'];
		if (
			! self::exact_object( $crosswalk, array( 'canonical_taxonomy', 'mapping_kind', 'operation_routing', 'orchestration_adapter', 'equivalence_claimed', 'subtype_preserved' ) ) ||
			'commerce_core_v1' !== $crosswalk['canonical_taxonomy'] ||
			'orchestration_bucket_only' !== $crosswalk['mapping_kind'] ||
			'dedicated_service_family_adapter' !== $crosswalk['operation_routing'] ||
			Tra_Vel_Service_Breadth_Taxonomy::orchestration_adapter( $profile['service_family'] ) !== $crosswalk['orchestration_adapter'] ||
			false !== $crosswalk['equivalence_claimed'] ||
			true !== $crosswalk['subtype_preserved']
		) {
			return self::error( 'family_crosswalk_invalid', 'A canonical vertical is only an orchestration bucket; equivalence is forbidden and subtype preservation is mandatory.' );
		}
		if ( 'entry_document_assistance' === $profile['service_family'] && 'document_assistance_orchestration_adapter_v1' !== $crosswalk['orchestration_adapter'] ) {
			return self::error( 'family_crosswalk_invalid', 'Entry-document work must route through its dedicated assistance adapter, never through the customer-facing activity adapter.' );
		}
		if ( ! self::exact_object( $profile['lifecycle'], Tra_Vel_Service_Breadth_Taxonomy::LIFECYCLE_STAGES ) ) {
			return self::error( 'family_lifecycle_invalid', 'Every family must declare all 12 lifecycle applicability axes.' );
		}
		foreach ( $profile['lifecycle'] as $applicability ) {
			if ( ! in_array( $applicability, Tra_Vel_Service_Breadth_Taxonomy::APPLICABILITY, true ) ) {
				return self::error( 'family_lifecycle_invalid', 'Lifecycle applicability must be required, conditional, or not applicable.' );
			}
		}
		$local = $profile['israel_local'];
		if ( ! self::exact_object( $local, array( 'applicable', 'scope', 'required_fact_codes' ) ) || true !== $local['applicable'] || ! in_array( $local['scope'], Tra_Vel_Service_Breadth_Taxonomy::LOCAL_SCOPES, true ) || ! self::code_list( $local['required_fact_codes'], 3 ) ) {
			return self::error( 'family_israel_local_invalid', 'Each family must expose its exact Israel-local applicability and facts.' );
		}
		$map = $profile['map'];
		$map_keys = array( 'overview_zoom', 'decision_zoom', 'operational_zoom', 'cluster_until_zoom', 'geometry', 'resolution_path', 'detail_surface', 'operational_anchor_codes', 'selection_to_plan_required', 'viewport_padding_required', 'rtl_mobile_safe_area_required', 'source_freshness_required', 'reduced_motion_alternative_required' );
		if (
			! self::exact_object( $map, $map_keys ) ||
			! is_int( $map['overview_zoom'] ) || ! is_int( $map['decision_zoom'] ) || ! is_int( $map['operational_zoom'] ) || ! is_int( $map['cluster_until_zoom'] ) ||
			$map['overview_zoom'] < 1 || $map['operational_zoom'] > 22 ||
			$map['overview_zoom'] >= $map['decision_zoom'] || $map['decision_zoom'] >= $map['operational_zoom'] ||
			$map['cluster_until_zoom'] < $map['overview_zoom'] || $map['cluster_until_zoom'] >= $map['decision_zoom'] ||
			! in_array( $map['geometry'], Tra_Vel_Service_Breadth_Taxonomy::MAP_GEOMETRIES, true ) ||
			Tra_Vel_Service_Breadth_Taxonomy::MAP_RESOLUTION_PATH !== $map['resolution_path'] ||
			'attached_non_occluding_context_panel' !== $map['detail_surface'] ||
			! self::code_list( $map['operational_anchor_codes'], 3 ) ||
			true !== $map['selection_to_plan_required'] || true !== $map['viewport_padding_required'] ||
			true !== $map['rtl_mobile_safe_area_required'] || true !== $map['source_freshness_required'] ||
			true !== $map['reduced_motion_alternative_required']
		) {
			return self::error( 'family_map_invalid', 'Map zooms must progress from overview to decision to operational detail with a canonical geometry.' );
		}

		$priority = Tra_Vel_Service_Breadth_Taxonomy::priority_subtype_operations();
		if ( isset( $priority[ $profile['service_family'] ] ) ) {
			foreach ( $priority[ $profile['service_family'] ] as $subtype => $operation ) {
				if (
					! in_array( $subtype, $profile['family_subtypes'], true ) ||
					! in_array( $operation['critical_fact_code'], $profile['critical_facts'], true ) ||
					! in_array( $operation['deadline_code'], $profile['critical_deadlines'], true ) ||
					! in_array( $operation['handoff_code'], $profile['required_handoffs'], true ) ||
					! in_array( $operation['map_anchor_code'], $map['operational_anchor_codes'], true )
				) {
					return self::error( 'family_subtype_operation_invalid', 'Priority Israel-local subtypes need their own fact, deadline, handoff, and operational map anchor.' );
				}
			}
		}
		return $profile;
	}

	private static function validate_scenario( $scenario ) {
		$keys = array( 'scenario_ref', 'scenario_number', 'scenario_slot', 'service_family', 'family_subtype', 'canonical_vertical', 'trigger', 'event_state', 'after_hours', 'preservation', 'expected_actions', 'required_deadline_code', 'required_handoff_code', 'financial_axes', 'expected_outcome', 'boundary' );
		if ( ! self::exact_object( $scenario, $keys ) || ! self::opaque_ref( $scenario['scenario_ref'], 'scenario' ) || ! is_int( $scenario['scenario_number'] ) || $scenario['scenario_number'] < 1 || ! in_array( $scenario['scenario_slot'], Tra_Vel_Service_Breadth_Taxonomy::SCENARIO_SLOTS, true ) || ! in_array( $scenario['service_family'], Tra_Vel_Service_Breadth_Taxonomy::SERVICE_FAMILIES, true ) ) {
			return self::error( 'scenario_shape_invalid', 'A breadth stress scenario has an invalid closed shape or synthetic identity.' );
		}
		$definition = Tra_Vel_Service_Breadth_Taxonomy::definition( $scenario['service_family'] );
		$blueprints = Tra_Vel_Service_Breadth_Taxonomy::scenario_blueprints();
		$blueprint  = $blueprints[ $scenario['service_family'] ][ $scenario['scenario_slot'] - 1 ];
		foreach ( array( 'family_subtype', 'trigger', 'event_state', 'required_deadline_code', 'required_handoff_code', 'expected_actions', 'expected_outcome' ) as $field ) {
			if ( self::canonical_digest( $scenario[ $field ] ) !== self::canonical_digest( $blueprint[ $field ] ) ) {
				return self::error( 'scenario_blueprint_mismatch', 'The scenario no longer matches its deterministic family-specific stress blueprint.' );
			}
		}
		if ( $scenario['canonical_vertical'] !== $definition['canonical_vertical'] || ! in_array( $scenario['family_subtype'], $definition['family_subtypes'], true ) || ! in_array( $scenario['event_state'], Tra_Vel_Service_Breadth_Taxonomy::EVENT_STATES, true ) || ! in_array( $scenario['required_deadline_code'], $definition['critical_deadlines'], true ) || ! in_array( $scenario['required_handoff_code'], $definition['required_handoffs'], true ) || ! self::code_list( $scenario['expected_actions'], 3 ) || ! in_array( $scenario['expected_outcome'], Tra_Vel_Service_Breadth_Taxonomy::EXPECTED_OUTCOMES, true ) ) {
			return self::error( 'scenario_family_binding_invalid', 'The scenario must bind its exact family subtype, vertical, deadline, handoff, actions, and outcome.' );
		}

		$after_hours = $scenario['after_hours'];
		$expected_after_hours = self::after_hours_contract( $scenario['service_family'], $scenario['scenario_slot'], $blueprint );
		if (
			! self::exact_object( $after_hours, array( 'required', 'route_ref', 'fallback_route_ref', 'coverage_state', 'timezone', 'deadline_code', 'primary_handoff_code', 'acknowledgement_sla_seconds', 'customer_update_sla_seconds', 'fallback_after_seconds', 'escalation_tier', 'safety_check_required', 'evidence_required', 'escalation_state', 'supplier_dispatched' ) ) ||
			! self::opaque_ref( $after_hours['route_ref'], 'route' ) || ! self::opaque_ref( $after_hours['fallback_route_ref'], 'route' ) ||
			$after_hours['route_ref'] === $after_hours['fallback_route_ref'] ||
			self::canonical_digest( $after_hours ) !== self::canonical_digest( $expected_after_hours )
		) {
			return self::error( 'scenario_after_hours_invalid', 'After-hours escalation must be explicit, correctly routed, and never dispatched by this registry.' );
		}

		$preservation = $scenario['preservation'];
		$expected_preservation = self::preservation_contract( $scenario['service_family'], $scenario['scenario_slot'] );
		$preservation_keys = array( 'partition_scope_ref', 'party_scope_refs', 'affected_party_refs', 'unaffected_party_refs', 'service_scope_refs', 'affected_service_refs', 'preserved_service_refs', 'preserve_unaffected', 'partition_complete' );
		if (
			! self::exact_object( $preservation, $preservation_keys ) ||
			! self::opaque_ref( $preservation['partition_scope_ref'], 'partition_scope' ) ||
			$preservation['partition_scope_ref'] !== $expected_preservation['partition_scope_ref'] ||
			true !== $preservation['preserve_unaffected'] || true !== $preservation['partition_complete'] ||
			! self::opaque_ref_list( $preservation['party_scope_refs'], 'party', false ) ||
			! self::opaque_ref_list( $preservation['affected_party_refs'], 'party', true ) ||
			! self::opaque_ref_list( $preservation['unaffected_party_refs'], 'party', true ) ||
			! self::opaque_ref_list( $preservation['service_scope_refs'], 'service', false ) ||
			! self::opaque_ref_list( $preservation['affected_service_refs'], 'service', true ) ||
			! self::opaque_ref_list( $preservation['preserved_service_refs'], 'service', true ) ||
			array_intersect( $preservation['affected_party_refs'], $preservation['unaffected_party_refs'] ) ||
			array_intersect( $preservation['affected_service_refs'], $preservation['preserved_service_refs'] ) ||
			! self::same_ref_set( $preservation['party_scope_refs'], $expected_preservation['party_scope_refs'] ) ||
			! self::same_ref_set( $preservation['service_scope_refs'], $expected_preservation['service_scope_refs'] ) ||
			! self::same_ref_set( $preservation['party_scope_refs'], array_merge( $preservation['affected_party_refs'], $preservation['unaffected_party_refs'] ) ) ||
			! self::same_ref_set( $preservation['service_scope_refs'], array_merge( $preservation['affected_service_refs'], $preservation['preserved_service_refs'] ) ) ||
			( ! $preservation['affected_party_refs'] && ! $preservation['affected_service_refs'] )
		) {
			return self::error( 'scenario_preservation_invalid', 'Affected and unaffected parties/services must be disjoint, scope-bound, and exhaustive; either partition may be empty when the other side remains legitimate.' );
		}

		$financial = $scenario['financial_axes'];
		if ( ! self::exact_object( $financial, array( 'payment', 'refund', 'settlement', 'netting_prohibited', 'independent_ledgers_required' ) ) || ! in_array( $financial['payment'], Tra_Vel_Service_Breadth_Taxonomy::PAYMENT_STATES, true ) || ! in_array( $financial['refund'], Tra_Vel_Service_Breadth_Taxonomy::REFUND_STATES, true ) || ! in_array( $financial['settlement'], Tra_Vel_Service_Breadth_Taxonomy::SETTLEMENT_STATES, true ) || true !== $financial['netting_prohibited'] || true !== $financial['independent_ledgers_required'] ) {
			return self::error( 'scenario_financial_separation_invalid', 'Payment, refund, and settlement must remain independent, explicitly stated, and never netted.' );
		}
		if ( self::canonical_digest( $scenario['boundary'] ) !== self::canonical_digest( self::scenario_boundary() ) ) {
			return self::error( 'scenario_boundary_invalid', 'Stress scenarios are planning-only and cannot dispatch, create commerce state, expose data, or claim real commercial truth.' );
		}
		return $scenario;
	}

	private static function truth_boundary() {
		return array(
			'private_server_only'           => true,
			'synthetic_fixture'             => true,
			'live_availability_claimed'     => false,
			'real_price_claimed'            => false,
			'real_supplier_identity_stored' => false,
			'commercial_authority_granted'  => false,
			'dispatch_allowed'              => false,
		);
	}

	private static function scenario_boundary() {
		return array(
			'planning_only'               => true,
			'public_serialization_allowed'=> false,
			'supplier_dispatched'         => false,
			'processor_called'            => false,
			'order_created'               => false,
			'payment_created'             => false,
			'refund_created'              => false,
			'settlement_created'          => false,
			'raw_pii_stored'              => false,
			'raw_secret_stored'           => false,
			'raw_provider_payload_stored' => false,
			'real_commercial_claimed'     => false,
		);
	}

	private static function after_hours_contract( $family, $slot, $blueprint ) {
		$required = true === $blueprint['after_hours_required'];
		return array(
			'required'                    => $required,
			'route_ref'                   => Tra_Vel_Service_Breadth_Taxonomy::synthetic_ref( 'route', $family . ':' . $slot . ':primary' ),
			'fallback_route_ref'          => Tra_Vel_Service_Breadth_Taxonomy::synthetic_ref( 'route', $family . ':' . $slot . ':fallback' ),
			'coverage_state'              => $required ? 'outside_declared_window' : 'within_declared_window',
			'timezone'                    => 'Asia/Jerusalem',
			'deadline_code'               => $blueprint['required_deadline_code'],
			'primary_handoff_code'        => $blueprint['required_handoff_code'],
			'acknowledgement_sla_seconds' => $required ? 300 : 900,
			'customer_update_sla_seconds' => $required ? 900 : 3600,
			'fallback_after_seconds'      => $required ? 600 : 1800,
			'escalation_tier'             => $required ? 2 : 0,
			'safety_check_required'       => $required,
			'evidence_required'           => true,
			'escalation_state'            => $required ? 'planned' : 'standby',
			'supplier_dispatched'         => false,
		);
	}

	private static function preservation_contract( $family, $slot ) {
		$party_one   = Tra_Vel_Service_Breadth_Taxonomy::synthetic_ref( 'party', $family . ':' . $slot . ':party:1' );
		$party_two   = Tra_Vel_Service_Breadth_Taxonomy::synthetic_ref( 'party', $family . ':' . $slot . ':party:2' );
		$service_one = Tra_Vel_Service_Breadth_Taxonomy::synthetic_ref( 'service', $family . ':' . $slot . ':service:1' );
		$service_two = Tra_Vel_Service_Breadth_Taxonomy::synthetic_ref( 'service', $family . ':' . $slot . ':service:2' );

		if ( 1 === $slot ) {
			$party_scope        = array( $party_one );
			$affected_parties   = array();
			$unaffected_parties = array( $party_one );
			$service_scope      = array( $service_one, $service_two );
			$affected_services  = array( $service_one );
			$preserved_services = array( $service_two );
		} else {
			$party_scope        = array( $party_one, $party_two );
			$affected_parties   = array( $party_one, $party_two );
			$unaffected_parties = array();
			$service_scope      = array( $service_one );
			$affected_services  = array( $service_one );
			$preserved_services = array();
		}

		return array(
			'partition_scope_ref'    => Tra_Vel_Service_Breadth_Taxonomy::synthetic_ref( 'partition_scope', $family . ':' . $slot ),
			'party_scope_refs'       => $party_scope,
			'affected_party_refs'    => $affected_parties,
			'unaffected_party_refs'  => $unaffected_parties,
			'service_scope_refs'     => $service_scope,
			'affected_service_refs'  => $affected_services,
			'preserved_service_refs' => $preserved_services,
			'preserve_unaffected'    => true,
			'partition_complete'     => true,
		);
	}

	private static function opaque_ref_list( $values, $kind, $allow_empty ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( ! $allow_empty && ! $values ) ) {
			return false;
		}
		$seen = array();
		foreach ( $values as $value ) {
			if ( ! self::opaque_ref( $value, $kind ) || isset( $seen[ $value ] ) ) {
				return false;
			}
			$seen[ $value ] = true;
		}
		return true;
	}

	private static function same_ref_set( $left, $right ) {
		if ( ! is_array( $left ) || ! is_array( $right ) ) {
			return false;
		}
		sort( $left, SORT_STRING );
		sort( $right, SORT_STRING );
		return $left === $right;
	}

	private static function code_list( $values, $minimum ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || count( $values ) < $minimum ) {
			return false;
		}
		$seen = array();
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{2,95}$/', $value ) || isset( $seen[ $value ] ) ) {
				return false;
			}
			$seen[ $value ] = true;
		}
		return true;
	}

	private static function opaque_ref( $value, $kind ) {
		if ( ! is_string( $kind ) || ! isset( Tra_Vel_Service_Breadth_Taxonomy::SYNTHETIC_REF_PREFIXES[ $kind ] ) ) {
			return false;
		}
		$prefix = Tra_Vel_Service_Breadth_Taxonomy::SYNTHETIC_REF_PREFIXES[ $kind ];
		return is_string( $value ) && 1 === preg_match( '/^' . preg_quote( $prefix, '/' ) . '_syn_[a-f0-9]{32}$/', $value );
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function contains_sensitive_material( $value, $key = '' ) {
		$key = strtolower( (string) $key );
		$forbidden_keys = array(
			'name', 'customer_name', 'traveler_name', 'email', 'phone', 'address',
			'passport_number', 'document_number', 'card_number', 'pan', 'cvv', 'cvc',
			'password', 'secret', 'api_key', 'access_token', 'refresh_token', 'credential',
			'raw_pii', 'raw_payload', 'provider_payload', 'supplier_payload', 'supplier_name',
			'provider_name', 'property_name',
		);
		if ( in_array( $key, $forbidden_keys, true ) && null !== $value && false !== $value && '' !== $value ) {
			return true;
		}
		if ( ! is_array( $value ) ) {
			return false;
		}
		foreach ( $value as $child_key => $child ) {
			if ( self::contains_sensitive_material( $child, $child_key ) ) {
				return true;
			}
		}
		return false;
	}

	private static function canonical_digest( $value ) {
		$encoded = wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
		if ( ! $is_list ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::canonicalize( $child );
		}
		return $value;
	}

	private static function error( $suffix, $message, $status = 400 ) {
		return new WP_Error( 'tra_vel_service_breadth_' . $suffix, $message, array( 'status' => $status ) );
	}
}
