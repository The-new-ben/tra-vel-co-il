<?php
/**
 * Deterministic, zero-dispatch recovery planner for Israel local operations.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Local_Operations_Recovery_Planner {
	/**
	 * Build a side-effect-free recovery assessment.
	 *
	 * @return array|WP_Error
	 */
	public static function build( $service_revisions, $context, $event, $now_utc ) {
		$context = Tra_Vel_Local_Operations_Policy::search_context( $context );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$event = Tra_Vel_Local_Operations_Policy::disruption_event( $event, $now_utc );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		if ( ! is_array( $service_revisions ) || array_values( $service_revisions ) !== $service_revisions || ! $service_revisions ) {
			return self::error( 'services_invalid', 'At least one local service revision is required.' );
		}

		$services = array();
		$trip_nodes = array();
		foreach ( $service_revisions as $revision ) {
			$revision = Tra_Vel_Local_Operations_Policy::service_revision( $revision, $now_utc );
			if ( is_wp_error( $revision ) ) {
				return $revision;
			}
			if ( isset( $services[ $revision['service_ref'] ] ) || isset( $trip_nodes[ $revision['commerce_binding']['trip_node_ref'] ] ) ) {
				return self::error( 'service_binding_duplicate', 'Service and trip-node bindings must be unique in one recovery assessment.' );
			}
			$services[ $revision['service_ref'] ] = $revision;
			$trip_nodes[ $revision['commerce_binding']['trip_node_ref'] ] = $revision['service_ref'];
		}

		$affected_services = array_values( $event['affected_service_refs'] );
		$affected_nodes = array_values( $event['affected_trip_node_refs'] );
		foreach ( $affected_services as $service_ref ) {
			if ( ! isset( $services[ $service_ref ] ) || ! in_array( $services[ $service_ref ]['commerce_binding']['trip_node_ref'], $affected_nodes, true ) ) {
				return self::error( 'event_service_binding_missing', 'Every affected service must bind an affected trip node in the assessed set.' );
			}
		}
		foreach ( $affected_nodes as $node_ref ) {
			if ( ! isset( $trip_nodes[ $node_ref ] ) || ! in_array( $trip_nodes[ $node_ref ], $affected_services, true ) ) {
				return self::error( 'event_node_binding_missing', 'Every affected trip node must bind an affected local service.' );
			}
		}

		$preserved_services = array_values( array_diff( array_keys( $services ), $affected_services ) );
		$preserved_nodes = array_values( array_diff( array_keys( $trip_nodes ), $affected_nodes ) );
		sort( $affected_services, SORT_STRING );
		sort( $affected_nodes, SORT_STRING );
		sort( $preserved_services, SORT_STRING );
		sort( $preserved_nodes, SORT_STRING );

		$rule = self::rule( $event['trigger'] );
		if ( is_wp_error( $rule ) ) {
			return $rule;
		}
		if ( $rule['disruption_type'] !== $event['disruption_type'] ) {
			return self::error( 'event_type_mismatch', 'The disruption type does not match the deterministic trigger taxonomy.' );
		}

		$approvals = array( 'human_operator_review' );
		if ( $rule['supplier_change'] ) {
			$approvals[] = 'customer_approval';
			$approvals[] = 'supplier_change_approval';
		}
		if ( $rule['financial_change'] ) {
			$approvals[] = 'customer_approval';
			$approvals[] = 'financial_approval';
		}
		$approvals = array_values( array_unique( $approvals ) );
		sort( $approvals, SORT_STRING );

		$fresh = 'verified_current' === $event['source']['truth_state'] && $event['source']['fresh_until'] > $now_utc && $event['expires_at'] > $now_utc;
		if ( ! $fresh ) {
			$state = 'evidence_required';
		} elseif ( $rule['safety_handoff'] ) {
			$state = 'human_safety_review';
		} else {
			$state = 'ready_for_review';
		}

		$actions = array(
			self::action( 'preserve_unaffected_components', $preserved_services, array(), false, false ),
			self::action( 'verify_disruption_evidence', $affected_services, array( 'human_operator_review' ), false, false ),
			self::action( $rule['strategy'], $affected_services, $approvals, $rule['financial_change'], $rule['supplier_change'] ),
		);
		if ( $rule['financial_change'] ) {
			$actions[] = self::action( 'reconcile_original_refund_separately', $affected_services, array( 'human_operator_review', 'financial_approval' ), true, false );
			$actions[] = self::action( 'authorize_replacement_payment_separately', $affected_services, array( 'customer_approval', 'financial_approval' ), true, false );
		}
		if ( 'not_applicable' !== $rule['benefit_state'] ) {
			$actions[] = self::action( 'refresh_exact_benefit_axes', $affected_services, array( 'human_operator_review' ), false, false );
		}

		$basis = array(
			'context_digest' => $context['context_digest'],
			'event_digest'   => $event['event_digest'],
			'service_digests' => array_values( array_map( static function ( $service ) { return $service['revision_digest']; }, $services ) ),
			'strategy'       => $rule['strategy'],
		);
		$seed = Tra_Vel_Local_Operations_Policy::content_digest( $basis );
		$plan = array(
			'contract_version'          => Tra_Vel_Local_Operations_Taxonomy::CONTRACT_VERSION,
			'environment'               => 'sandbox',
			'data_mode'                 => 'synthetic_demo',
			'recovery_ref'              => 'tv_local_recovery_' . substr( $seed, 0, 24 ),
			'recovery_digest'           => str_repeat( '0', 64 ),
			'context_binding'           => array( 'context_ref' => $context['context_ref'], 'context_version' => $context['context_version'], 'context_digest' => $context['context_digest'] ),
			'event_binding'             => array( 'event_ref' => $event['event_ref'], 'event_version' => $event['event_version'], 'event_digest' => $event['event_digest'] ),
			'affected_service_refs'     => $affected_services,
			'affected_trip_node_refs'   => $affected_nodes,
			'preserved_service_refs'    => $preserved_services,
			'preserved_trip_node_refs'  => $preserved_nodes,
			'strategy'                  => $rule['strategy'],
			'severity'                  => $event['severity'],
			'state'                     => $state,
			'required_approvals'        => $approvals,
			'actions'                   => $actions,
			'financial_separation'      => array(
				'original_refund_state'                 => 'partial_refund_observed' === $event['trigger'] ? 'observed_pending_reconciliation' : ( $rule['financial_change'] ? 'review_required' : 'not_applicable' ),
				'replacement_payment_state'             => $rule['financial_change'] ? 'separate_authorization_required' : 'not_required',
				'netting_prohibited'                    => true,
				'existing_commerce_engine_authoritative' => true,
			),
			'benefit_reconciliation'    => array(
				'state'                    => $rule['benefit_state'],
				'eligibility_created'      => false,
				'checkout_effect'          => 'none',
				'redemption_dispatched'    => false,
			),
			'dispatch'                  => array(
				'state'               => 'not_dispatched',
				'supplier_dispatched' => false,
				'processor_called'    => false,
			),
			'boundary'                  => array(
				'server_only'                  => true,
				'public_serialization_allowed' => false,
				'planning_only'                => true,
				'creates_order'                => false,
				'creates_payment'              => false,
				'creates_refund'               => false,
				'changes_supplier_booking'     => false,
			),
		);
		$plan['recovery_digest'] = Tra_Vel_Local_Operations_Policy::content_digest( $plan, 'recovery_digest' );
		return $plan;
	}

	/**
	 * Return the deterministic rule used by both runtime and the stress matrix.
	 *
	 * @return array|WP_Error
	 */
	public static function rule( $trigger ) {
		$trigger = Tra_Vel_Local_Operations_Taxonomy::member( $trigger, Tra_Vel_Local_Operations_Taxonomy::SCENARIO_TRIGGERS );
		if ( '' === $trigger ) {
			return self::error( 'trigger_invalid', 'The recovery trigger is not supported.' );
		}

		$rules = array(
			'occupancy_total_exceeded'          => array( 'occupancy', 'find_capacity_match', true, false, false, 'not_applicable' ),
			'child_age_band_changed'             => array( 'occupancy', 'reallocate_party', true, false, false, 'not_applicable' ),
			'room_allocation_invalid'            => array( 'occupancy', 'reallocate_party', true, false, false, 'not_applicable' ),
			'accessible_unit_unavailable'        => array( 'accessibility', 'preserve_access_requirements', true, false, false, 'not_applicable' ),
			'step_free_route_changed'            => array( 'accessibility', 'preserve_access_requirements', true, false, false, 'not_applicable' ),
			'late_arrival'                       => array( 'arrival', 'reconfirm_arrival_and_keys', false, false, false, 'not_applicable' ),
			'key_handoff_failed'                 => array( 'arrival', 'reconfirm_arrival_and_keys', true, false, false, 'not_applicable' ),
			'check_in_window_changed'            => array( 'arrival', 'reconfirm_arrival_and_keys', true, false, false, 'not_applicable' ),
			'check_out_window_changed'           => array( 'arrival', 'reconfirm_arrival_and_keys', true, false, false, 'not_applicable' ),
			'vat_eligibility_changed'            => array( 'commercial_terms', 'revalidate_local_terms', false, true, false, 'not_applicable' ),
			'deposit_terms_changed'              => array( 'commercial_terms', 'revalidate_local_terms', false, true, false, 'not_applicable' ),
			'cancellation_deadline_near'         => array( 'commercial_terms', 'protect_deadline', false, false, false, 'not_applicable' ),
			'shabbat_check_in_conflict'          => array( 'shabbat', 'revalidate_shabbat_fit', true, false, false, 'not_applicable' ),
			'shabbat_key_conflict'               => array( 'shabbat', 'revalidate_shabbat_fit', true, false, false, 'not_applicable' ),
			'kosher_certificate_expired'         => array( 'certificate_expiry', 'revalidate_kosher_evidence', true, false, false, 'not_applicable' ),
			'kosher_scope_conflict'              => array( 'certificate_expiry', 'revalidate_kosher_evidence', true, false, false, 'not_applicable' ),
			'rail_service_disrupted'             => array( 'rail', 'reroute_local_transport', true, false, false, 'not_applicable' ),
			'road_corridor_closed'               => array( 'road', 'reroute_local_transport', true, false, false, 'not_applicable' ),
			'domestic_flight_disrupted'          => array( 'closure', 'replace_domestic_segment', true, false, false, 'not_applicable' ),
			'extreme_heat'                       => array( 'weather', 'safety_hold_and_human_review', false, false, true, 'not_applicable' ),
			'flash_flood'                        => array( 'flood', 'safety_hold_and_human_review', false, false, true, 'not_applicable' ),
			'wildfire_smoke'                     => array( 'fire', 'safety_hold_and_human_review', false, false, true, 'not_applicable' ),
			'security_restriction'               => array( 'security', 'safety_hold_and_human_review', false, false, true, 'not_applicable' ),
			'evacuation_order'                   => array( 'evacuation', 'safety_hold_and_human_review', false, false, true, 'not_applicable' ),
			'utility_outage'                     => array( 'utility', 'restore_utility_or_replace', true, false, false, 'not_applicable' ),
			'venue_closed'                       => array( 'closure', 'replace_closed_service', true, false, false, 'not_applicable' ),
			'parking_unavailable'                => array( 'closure', 'replace_parking_or_access_route', true, false, false, 'not_applicable' ),
			'ev_charging_unavailable'            => array( 'closure', 'replace_parking_or_access_route', true, false, false, 'not_applicable' ),
			'accessible_route_blocked'           => array( 'road', 'replace_parking_or_access_route', true, false, false, 'not_applicable' ),
			'attraction_group_minimum_changed'   => array( 'supplier_revision', 'reconfigure_activity', true, false, false, 'not_applicable' ),
			'guide_unavailable'                  => array( 'closure', 'reconfigure_activity', true, false, false, 'not_applicable' ),
			'equipment_unavailable'              => array( 'closure', 'reconfigure_activity', true, false, false, 'not_applicable' ),
			'activity_weather_cancelled'         => array( 'weather', 'reconfigure_activity', true, false, false, 'not_applicable' ),
			'partial_party_reduced'              => array( 'party_change', 'preserve_partial_party', true, false, false, 'not_applicable' ),
			'partial_party_extended'             => array( 'party_change', 'preserve_partial_party', true, false, false, 'not_applicable' ),
			'supplier_revision_superseded'       => array( 'supplier_revision', 'revalidate_supplier_revision', false, false, false, 'not_applicable' ),
			'supplier_replacement_required'      => array( 'supplier_revision', 'propose_supplier_replacement', true, false, false, 'not_applicable' ),
			'partial_refund_observed'            => array( 'financial', 'reconcile_refund_separately', false, true, false, 'not_applicable' ),
			'replacement_payment_required'       => array( 'financial', 'authorize_replacement_payment_separately', false, true, false, 'not_applicable' ),
			'benefit_source_stale'               => array( 'benefit', 'refresh_benefit_evidence', false, false, false, 'refresh_required' ),
			'benefit_eligibility_changed'        => array( 'benefit', 'refresh_benefit_evidence', false, false, false, 'eligibility_recheck_required' ),
			'benefit_expired'                    => array( 'benefit', 'refresh_benefit_evidence', false, false, false, 'expired' ),
			'benefit_reversed'                   => array( 'benefit', 'reconcile_benefit_reversal', false, true, false, 'reversal_reconciliation_required' ),
		);
		$row = $rules[ $trigger ];
		return array(
			'trigger'           => $trigger,
			'disruption_type'   => $row[0],
			'strategy'          => $row[1],
			'supplier_change'   => $row[2],
			'financial_change'  => $row[3],
			'safety_handoff'    => $row[4],
			'benefit_state'     => $row[5],
		);
	}

	private static function action( $type, $service_refs, $approval_codes, $financial_change, $supplier_change ) {
		return array(
			'action_type'                       => $type,
			'target_service_refs'               => array_values( $service_refs ),
			'required_approval_codes'           => array_values( $approval_codes ),
			'requires_financial_approval'       => (bool) $financial_change,
			'requires_supplier_change_approval' => (bool) $supplier_change,
			'execution_state'                   => 'planned',
			'supplier_dispatched'               => false,
			'processor_called'                  => false,
		);
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_local_recovery_' . $suffix, $message, array( 'status' => 400 ) );
	}
}
