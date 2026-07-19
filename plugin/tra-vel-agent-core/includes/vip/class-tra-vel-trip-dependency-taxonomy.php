<?php
/**
 * Closed vocabulary for private itinerary dependency and recovery planning.
 *
 * This contract intentionally has no hooks or external side effects. It models
 * what the platform knows, what remains uncertain, and which gates a future
 * operator-controlled execution would have to satisfy.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Trip_Dependency_Taxonomy {
	const CONTRACT_VERSION = '1.0.0';

	const VERTICALS = array(
		'flight',
		'accommodation',
		'package',
		'transfer',
		'activity',
		'dining',
		'insurance',
		'connectivity',
		'equipment',
	);

	const NODE_KINDS = array(
		'transport_segment',
		'stay',
		'bundled_service',
		'ground_mobility',
		'reserved_experience',
		'reserved_meal',
		'coverage',
		'connectivity_plan',
		'equipment_service',
	);

	const NODE_STATES = array(
		'planned',
		'held',
		'confirmed',
		'fulfilled',
		'at_risk',
		'disrupted',
		'cancelled',
		'uncertain',
		'superseded',
		'closed_with_loss',
	);

	const CONFIRMATION_STATES = array(
		'unverified',
		'pending',
		'verified_current',
		'stale',
		'rejected',
		'unavailable',
		'uncertain',
	);

	const DEADLINE_TYPES = array(
		'ticketing_limit',
		'free_cancellation_cutoff',
		'change_cutoff',
		'checkin_open',
		'checkin_close',
		'boarding_close',
		'arrival_notification',
		'pickup_window',
		'activity_start',
		'claim_notice',
		'refund_follow_up',
		'dispute_response',
		'equipment_return',
		'connectivity_activation',
		'operator_sla',
	);

	const DEADLINE_STATES = array( 'open', 'satisfied', 'missed', 'superseded', 'uncertain' );

	const DEPENDENCY_TYPES = array(
		'temporal_before',
		'temporal_buffer',
		'geographic_handoff',
		'arrival_enables',
		'departure_requires',
		'inventory_bundle',
		'traveler_presence',
		'document_required',
		'authority_required',
		'accessibility_required',
		'financial_required',
		'supplier_confirmation_required',
		'local_availability_required',
		'weather_window',
		'connectivity_required',
		'equipment_required',
		'insurance_coverage',
	);

	const CRITICALITIES = array( 'advisory', 'important', 'trip_critical', 'safety_critical' );
	const FEASIBILITY_STATES = array( 'verified', 'at_risk', 'unknown', 'impossible' );

	const CONSTRAINT_TYPES = array(
		'traveler_manifest',
		'minor_guardian_authority',
		'dependent_adult_authority',
		'accessibility_match',
		'document_admissibility',
		'customer_consent_scope',
		'payment_authority',
		'refund_destination_authority',
		'restricted_health_evidence',
		'emergency_contact_route',
	);

	const CONSTRAINT_STATES = array( 'verified_current', 'required', 'stale', 'conflict', 'missing', 'not_applicable' );

	const PARTY_FLAGS = array( 'minor_present', 'dependent_adult_present', 'accessibility_required' );

	const EVENT_TYPES = array(
		'flight.cancelled',
		'flight.delayed_connection_at_risk',
		'flight.missed_connection',
		'accommodation.overbooked',
		'accommodation.late_arrival_risk',
		'transfer.failed',
		'activity.weather_closed',
		'dining.closed',
		'insurance.incident_reported',
		'connectivity.outage',
		'equipment.lost',
		'local_tourism.official_closure',
		'traveler.document_changed',
		'traveler.authority_changed',
		'traveler.accessibility_ack_lost',
		'financial.partial_refund_observed',
		'financial.payment_state_uncertain',
		'supplier.response_stale',
	);

	const EVENT_SOURCE_KINDS = array(
		'signed_supplier_event',
		'provider_authorized_api',
		'official_live_feed',
		'operator_observation',
		'customer_report',
		'synthetic_test',
	);

	const EVENT_TRUTH_STATES = array( 'observed_unverified', 'verified_current', 'stale', 'conflict', 'rejected' );

	const GATE_CODES = array(
		'customer_consent',
		'financial_authority',
		'document_admissibility',
		'guardian_or_dependent_authority',
		'accessibility_supplier_ack',
		'supplier_truth',
		'human_operator_review',
	);

	const GATE_STATES = array( 'not_applicable', 'required', 'satisfied', 'stale', 'conflict', 'blocked' );

	const TRIAGE_SEVERITIES = array( 'P0', 'P1', 'P2', 'P3' );
	const TRIAGE_STATES = array( 'assessed', 'evidence_needed', 'human_review_required', 'safety_handoff_required' );

	const STRATEGIES = array(
		'protected_flight_rebook',
		'route_resequence_with_buffer',
		'preserve_unaffected_components',
		'replacement_stay_with_arrival_protection',
		'late_arrival_reconfirmation',
		'replacement_transfer',
		'reschedule_weather_window',
		'replacement_activity_or_local_option',
		'replacement_dining',
		'emergency_assistance_handoff',
		'coverage_preauthorization_review',
		'alternate_connectivity_with_offline_pack',
		'emergency_equipment_replacement',
		'loss_or_damage_claim_review',
		'local_closure_reroute',
		'document_and_itinerary_recheck',
		'authority_reverification',
		'accessibility_preserving_replan',
		'financial_reconciliation',
		'supplier_reverification',
		'safe_hold_and_human_review',
	);

	const CANDIDATE_STATES = array( 'eligible_for_review', 'blocked', 'stale', 'rejected' );
	const PLAN_STATES = array( 'proposed', 'blocked', 'ready_for_authorization', 'authorized', 'execution_observed', 'verification_pending', 'verified', 'closed_with_loss' );
	const COMPLETION_STATES = array( 'not_started', 'in_progress', 'pending_verification', 'verified', 'failed', 'closed_with_loss' );
	const FINANCIAL_STATES = array( 'not_required', 'pending', 'balanced', 'uncertain', 'disputed', 'closed_with_loss' );

	const HIGH_RISK_EVENT_TYPES = array(
		'flight.cancelled',
		'flight.missed_connection',
		'accommodation.overbooked',
		'insurance.incident_reported',
		'traveler.authority_changed',
		'traveler.accessibility_ack_lost',
		'financial.payment_state_uncertain',
	);

	/**
	 * Candidate strategies and hard gates by event family.
	 *
	 * The map is planning-only; none of these labels assert that an external
	 * supplier accepted or performed a change.
	 *
	 * @return array
	 */
	public static function recovery_recipe( $event_type ) {
		$recipes = array(
			'flight.cancelled' => array( array( 'protected_flight_rebook', array( 'customer_consent', 'financial_authority', 'document_admissibility', 'supplier_truth' ) ), array( 'route_resequence_with_buffer', array( 'customer_consent', 'document_admissibility', 'supplier_truth' ) ), array( 'preserve_unaffected_components', array( 'supplier_truth' ) ) ),
			'flight.delayed_connection_at_risk' => array( array( 'route_resequence_with_buffer', array( 'customer_consent', 'supplier_truth' ) ), array( 'preserve_unaffected_components', array( 'supplier_truth' ) ) ),
			'flight.missed_connection' => array( array( 'protected_flight_rebook', array( 'customer_consent', 'financial_authority', 'document_admissibility', 'supplier_truth' ) ), array( 'preserve_unaffected_components', array( 'supplier_truth' ) ) ),
			'accommodation.overbooked' => array( array( 'replacement_stay_with_arrival_protection', array( 'customer_consent', 'financial_authority', 'accessibility_supplier_ack', 'supplier_truth' ) ), array( 'preserve_unaffected_components', array( 'supplier_truth' ) ) ),
			'accommodation.late_arrival_risk' => array( array( 'late_arrival_reconfirmation', array( 'supplier_truth' ) ), array( 'replacement_stay_with_arrival_protection', array( 'customer_consent', 'financial_authority', 'accessibility_supplier_ack', 'supplier_truth' ) ) ),
			'transfer.failed' => array( array( 'replacement_transfer', array( 'customer_consent', 'financial_authority', 'accessibility_supplier_ack', 'supplier_truth' ) ) ),
			'activity.weather_closed' => array( array( 'reschedule_weather_window', array( 'customer_consent', 'supplier_truth' ) ), array( 'replacement_activity_or_local_option', array( 'customer_consent', 'financial_authority', 'accessibility_supplier_ack', 'supplier_truth' ) ) ),
			'dining.closed' => array( array( 'replacement_dining', array( 'customer_consent', 'financial_authority', 'accessibility_supplier_ack', 'supplier_truth' ) ) ),
			'insurance.incident_reported' => array( array( 'emergency_assistance_handoff', array( 'human_operator_review', 'supplier_truth' ) ), array( 'coverage_preauthorization_review', array( 'customer_consent', 'human_operator_review', 'supplier_truth' ) ) ),
			'connectivity.outage' => array( array( 'alternate_connectivity_with_offline_pack', array( 'customer_consent', 'financial_authority', 'supplier_truth' ) ) ),
			'equipment.lost' => array( array( 'emergency_equipment_replacement', array( 'customer_consent', 'financial_authority', 'supplier_truth' ) ), array( 'loss_or_damage_claim_review', array( 'customer_consent', 'human_operator_review', 'supplier_truth' ) ) ),
			'local_tourism.official_closure' => array( array( 'local_closure_reroute', array( 'customer_consent', 'financial_authority', 'accessibility_supplier_ack', 'supplier_truth' ) ), array( 'replacement_activity_or_local_option', array( 'customer_consent', 'supplier_truth' ) ) ),
			'traveler.document_changed' => array( array( 'document_and_itinerary_recheck', array( 'document_admissibility', 'human_operator_review', 'supplier_truth' ) ) ),
			'traveler.authority_changed' => array( array( 'authority_reverification', array( 'guardian_or_dependent_authority', 'human_operator_review' ) ) ),
			'traveler.accessibility_ack_lost' => array( array( 'accessibility_preserving_replan', array( 'customer_consent', 'accessibility_supplier_ack', 'human_operator_review', 'supplier_truth' ) ) ),
			'financial.partial_refund_observed' => array( array( 'financial_reconciliation', array( 'financial_authority', 'human_operator_review', 'supplier_truth' ) ) ),
			'financial.payment_state_uncertain' => array( array( 'financial_reconciliation', array( 'financial_authority', 'human_operator_review', 'supplier_truth' ) ) ),
			'supplier.response_stale' => array( array( 'supplier_reverification', array( 'human_operator_review', 'supplier_truth' ) ) ),
		);
		return isset( $recipes[ $event_type ] ) ? $recipes[ $event_type ] : array( array( 'safe_hold_and_human_review', array( 'human_operator_review', 'supplier_truth' ) ) );
	}

	public static function node_kind_for_vertical( $vertical ) {
		$map = array(
			'flight' => 'transport_segment',
			'accommodation' => 'stay',
			'package' => 'bundled_service',
			'transfer' => 'ground_mobility',
			'activity' => 'reserved_experience',
			'dining' => 'reserved_meal',
			'insurance' => 'coverage',
			'connectivity' => 'connectivity_plan',
			'equipment' => 'equipment_service',
		);
		return isset( $map[ $vertical ] ) ? $map[ $vertical ] : '';
	}
}
