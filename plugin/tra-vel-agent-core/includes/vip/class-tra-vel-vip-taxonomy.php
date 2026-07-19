<?php
/**
 * Closed vocabulary for traveler registration and VIP trip servicing.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_VIP_Taxonomy {
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

	const REGISTRATION_GATES = array(
		'discover',
		'personalize',
		'ready_to_quote',
		'ready_to_reserve',
		'ready_to_fulfill',
		'ready_to_travel',
	);

	const REGISTRATION_REQUIREMENTS = array(
		'intent_recorded',
		'travel_window_recorded',
		'party_size_recorded',
		'contact_verified',
		'traveler_roles_declared',
		'traveler_preferences_recorded',
		'loyalty_preferences_recorded',
		'exact_occupancy_recorded',
		'rules_identity_facts_recorded',
		'accessibility_requirements_recorded',
		'dependent_support_plan_recorded',
		'reachable_contact_verified',
		'travel_document_snapshot_verified',
		'guardian_authority_verified',
		'dependent_authority_verified',
		'terms_accepted',
		'booking_questions_complete',
		'payment_session_owner_verified',
		'payment_state_sufficient',
		'supplier_confirmation_verified',
		'mandatory_traveler_data_accepted',
		'traveler_manifest_snapshot_verified',
		'document_admissibility_current',
		'checkin_pickup_instructions_ready',
		'accessibility_supplier_acknowledged',
		'emergency_contacts_offline',
		'service_contact_pack_offline',
		'itinerary_offline',
		'dependency_health_checked',
	);

	const REGISTRATION_TRANSITION_REASONS = array(
		'progress',
		'profile_correction',
		'party_change',
		'document_change',
		'guardian_change',
		'accessibility_change',
		'supplier_change',
		'itinerary_change',
		'policy_refresh',
		'incident_recovery',
	);

	const ROLES = array(
		'account_holder',
		'traveler',
		'booker',
		'payer',
		'guardian',
		'beneficiary',
		'emergency_contact',
		'operator_delegate',
		'supplier_passenger',
	);

	const AUTHORITY_SCOPES = array(
		'trip_view_redacted',
		'incident_report',
		'ordinary_evidence_add',
		'safe_contact_update',
		'case_progress_view',
		'operator_contact_approve',
		'decision_view',
		'service_reserve',
		'service_change',
		'service_cancel',
		'payment_authorize',
		'refund_destination_change',
		'identity_change',
		'guardian_authority_change',
		'sensitive_evidence_disclose',
		'recovery_channel_change',
		'delegate_manage',
	);

	const LOW_RISK_CAPABILITY_SCOPES = array(
		'trip_view_redacted',
		'incident_report',
		'ordinary_evidence_add',
		'safe_contact_update',
		'case_progress_view',
		'operator_contact_approve',
		'decision_view',
	);

	const HIGH_IMPACT_SCOPES = array(
		'service_reserve',
		'service_change',
		'service_cancel',
		'payment_authorize',
		'refund_destination_change',
		'identity_change',
		'guardian_authority_change',
		'sensitive_evidence_disclose',
		'recovery_channel_change',
		'delegate_manage',
	);

	const LIFECYCLE_STATES = array(
		'received',
		'verified',
		'triaged',
		'plan_building',
		'approval_needed',
		'executing',
		'supplier_pending',
		'uncertain',
		'monitoring',
		'evidence_needed',
		'traveler_pending',
		'escalated',
		'safety_handoff',
		'resolved',
		'closed',
		'reopened',
		'closed_with_loss',
	);

	const SEVERITIES = array( 'P0', 'P1', 'P2', 'P3', 'P4' );

	const OUTCOME_AXES = array(
		'safety' => array( 'unassessed', 'at_risk', 'handoff_in_progress', 'handed_off', 'stable', 'closed_with_loss' ),
		'continuity' => array( 'unassessed', 'healthy', 'at_risk', 'disrupted', 'recovery_in_progress', 'restored', 'closed_with_loss' ),
		'supplier' => array( 'not_started', 'pending', 'succeeded', 'failed', 'uncertain', 'reconciled' ),
		'financial' => array( 'not_started', 'pending', 'balanced', 'attention_required', 'disputed', 'closed_with_loss' ),
		'evidence' => array( 'none', 'needed', 'collecting', 'complete', 'restricted' ),
		'communication' => array( 'not_started', 'queued', 'delivered', 'acknowledged', 'failed', 'uncertain' ),
	);

	const OPERATION_STATES = array( 'queued', 'started', 'succeeded', 'failed', 'uncertain', 'reconciled' );

	const EVENT_TYPES = array(
		'case.received',
		'case.verified',
		'case.triaged',
		'case.lifecycle_changed',
		'case.severity_changed',
		'case.escalated',
		'case.reopened',
		'case.resolved',
		'case.closed',
		'deadline.scheduled',
		'deadline.satisfied',
		'deadline.missed',
		'decision.prepared',
		'decision.authorization_recorded',
		'decision.expired',
		'operation.started',
		'operation.succeeded',
		'operation.failed',
		'operation.uncertain',
		'operation.reconciled',
		'supplier.status_observed',
		'evidence.requested',
		'evidence.received',
		'evidence.restricted',
		'communication.queued',
		'communication.delivered',
		'communication.failed',
		'safety.handoff_started',
		'safety.handoff_verified',
	);

	const DEADLINE_TYPES = array(
		'cancellation_cutoff',
		'ticketing_limit',
		'checkin',
		'boarding',
		'connection',
		'pickup',
		'claim_notice',
		'dispute_response',
		'refund_follow_up',
		'internal_sla',
		'evidence_submission',
		'authorization_expiry',
		'reconciliation',
	);

	const DEADLINE_BASES = array(
		'internal_sla',
		'supplier_cutoff',
		'trip_event',
		'legal_policy_notice',
		'vulnerability_escalation',
		'operation_reconciliation',
	);

	const INCIDENTS = array(
		'flight' => array(
			'flight.schedule_change',
			'flight.cancellation',
			'flight.delay',
			'flight.denied_boarding',
			'flight.missed_connection_single_ticket',
			'flight.self_transfer_failure',
			'flight.ticket_not_issued',
			'flight.duplicate_booking',
			'flight.name_document_mismatch',
			'flight.seat_ssr_loss',
			'flight.baggage_delayed',
			'flight.baggage_lost',
			'flight.baggage_damaged',
			'flight.carrier_insolvency',
		),
		'accommodation' => array(
			'accommodation.booking_not_found',
			'accommodation.property_cancellation',
			'accommodation.overbooking_walk',
			'accommodation.room_unavailable',
			'accommodation.invalid_payment_card',
			'accommodation.unexpected_deposit_tax',
			'accommodation.wrong_occupancy',
			'accommodation.bed_mismatch',
			'accommodation.inaccessible_room',
			'accommodation.cleanliness_safety_issue',
			'accommodation.late_arrival',
			'accommodation.early_departure',
			'accommodation.no_show_dispute',
			'accommodation.key_collection_failure',
			'accommodation.host_unreachable',
			'accommodation.refund_disagreement',
		),
		'package' => array(
			'package.component_failure',
			'package.cascading_change',
			'package.partial_confirmation',
			'package.price_policy_mismatch',
			'package.independent_supplier_conflict',
			'package.itinerary_dependency_break',
			'package.partial_cancellation',
			'package.organizer_nonresponse',
		),
		'transfer' => array(
			'transfer.driver_no_show',
			'transfer.traveler_no_show',
			'transfer.late_inbound_flight',
			'transfer.wrong_terminal',
			'transfer.bad_meeting_point',
			'transfer.driver_unreachable',
			'transfer.traveler_unreachable',
			'transfer.vehicle_too_small',
			'transfer.child_seat_missing',
			'transfer.accessibility_incompatible',
			'transfer.unsafe_vehicle_driver',
			'transfer.border_crossing_issue',
			'transfer.address_error',
			'transfer.cancellation_cutoff',
		),
		'activity' => array(
			'activity.stale_availability',
			'activity.manual_confirmation_overdue',
			'activity.supplier_rejection',
			'activity.supplier_cancellation',
			'activity.weather_cancellation',
			'activity.minimum_participant_failure',
			'activity.pickup_failure',
			'activity.voucher_not_accepted',
			'activity.language_mismatch',
			'activity.accessibility_failure',
			'activity.participant_eligibility_issue',
			'activity.customer_illness',
			'activity.partial_party_cancellation',
			'activity.post_service_complaint',
		),
		'dining' => array(
			'dining.venue_closed',
			'dining.reservation_missing',
			'dining.late_arrival',
			'dining.deposit_no_show_charge',
			'dining.party_size_change',
			'dining.dietary_certification_mismatch',
			'dining.allergy_concern',
			'dining.accessibility_failure',
			'dining.special_occasion_request',
			'dining.replacement_needed',
		),
		'insurance' => array(
			'insurance.immediate_danger',
			'insurance.assistance_unreachable',
			'insurance.preauthorization_pending',
			'insurance.preauthorization_declined',
			'insurance.guarantee_of_payment_missing',
			'insurance.claim_evidence_needed',
			'insurance.claim_declined',
			'insurance.claim_payment_delayed',
			'insurance.policy_document_missing',
			'insurance.coverage_question',
			'insurance.medical_provider_change',
			'insurance.supplier_refund_offset',
		),
		'connectivity' => array(
			'connectivity.device_incompatible',
			'connectivity.device_locked',
			'connectivity.activation_code_consumed',
			'connectivity.delivery_delayed',
			'connectivity.activated_too_early',
			'connectivity.installed_inactive',
			'connectivity.no_coverage',
			'connectivity.wrong_apn',
			'connectivity.roaming_disabled',
			'connectivity.wrong_network',
			'connectivity.primary_sim_roaming_risk',
			'connectivity.data_exhausted',
			'connectivity.device_lost',
			'connectivity.traveler_offline',
		),
		'equipment' => array(
			'equipment.size_model_unavailable',
			'equipment.substitution_consent_needed',
			'equipment.delivery_missed',
			'equipment.pickup_location_closed',
			'equipment.unsafe_damaged_item',
			'equipment.certification_mismatch',
			'equipment.deposit_issue',
			'equipment.item_lost_stolen',
			'equipment.return_delayed',
			'equipment.damage_claim',
		),
	);

	const CROSS_TRIP_INCIDENTS = array(
		'cross_trip.contact_channel_lost',
		'cross_trip.document_admissibility_problem',
		'cross_trip.document_wallet_lost_stolen',
		'cross_trip.guardian_authority_dispute',
		'cross_trip.medical_family_emergency',
		'cross_trip.accessibility_assistance_missing',
		'cross_trip.destination_mass_disruption',
		'cross_trip.payment_problem',
		'cross_trip.multi_service_change',
		'cross_trip.supplier_platform_outage',
		'cross_trip.fraud_conflicting_instructions',
	);

	const STRESS_SCENARIOS = array(
		1  => 'booking_timeout_after_provider_confirmation',
		2  => 'booking_malformed_response',
		3  => 'approval_double_click_two_devices',
		4  => 'payment_captured_supplier_rejects',
		5  => 'supplier_confirms_payment_requires_action',
		6  => 'hold_expires_during_step_up',
		7  => 'webhook_duplicated_twenty_times',
		8  => 'out_of_order_cancelled_before_confirmed',
		9  => 'flight_change_breaks_through_connection',
		10 => 'self_transfer_inbound_delay',
		11 => 'first_leg_cancelled_return_active',
		12 => 'replacement_flight_different_airport',
		13 => 'name_typo_after_ticketing',
		14 => 'passport_expiry_blocks_admissibility',
		15 => 'transit_rule_changes_after_booking',
		16 => 'minor_authority_evidence_missing',
		17 => 'rebook_loses_wheelchair_ssr',
		18 => 'wheelchair_damaged',
		19 => 'bag_misses_interline_transfer',
		20 => 'acute_medical_event',
		21 => 'hotel_cancels_before_midnight_checkin',
		22 => 'property_cannot_find_confirmation',
		23 => 'property_collect_card_invalid',
		24 => 'late_flight_causes_hotel_no_show',
		25 => 'confirmed_accessible_room_not_accessible',
		26 => 'transfer_driver_no_show_supplier_unanswered',
		27 => 'flight_lands_wrong_terminal_for_transfer',
		28 => 'required_child_seat_missing',
		29 => 'activity_weather_cancellation',
		30 => 'manual_activity_confirmation_overdue',
		31 => 'partial_group_activity_cancellation',
		32 => 'restaurant_false_no_show',
		33 => 'allergy_disclosed_in_free_text',
		34 => 'insurance_cancellation_invoice_missing',
		35 => 'coverage_question_before_emergency_care',
		36 => 'esim_delivery_delayed_after_purchase',
		37 => 'esim_on_carrier_locked_device',
		38 => 'equipment_delivery_fails_before_activity',
		39 => 'processor_refund_fails_after_supplier_approval',
		40 => 'chargeback_opens_while_refund_pending',
		41 => 'refund_destination_change_in_chat',
		42 => 'supplier_api_and_support_unavailable',
		43 => 'regional_closure_mass_incident',
		44 => 'destination_warning_rises_in_trip',
		45 => 'magic_link_opened_by_scanner',
		46 => 'traveler_phone_stolen',
		47 => 'daylight_saving_shifts_cutoff',
		48 => 'multi_currency_partial_refund_rounding',
		49 => 'conflicting_family_change_instructions',
		50 => 'sensitive_data_in_provider_error',
	);

	/**
	 * Return the complete, unique incident vocabulary.
	 *
	 * @return string[]
	 */
	public static function incident_types() {
		$types = self::CROSS_TRIP_INCIDENTS;
		foreach ( self::INCIDENTS as $vertical_types ) {
			$types = array_merge( $types, $vertical_types );
		}
		return array_values( array_unique( $types ) );
	}

	/**
	 * Return the canonical vertical encoded by an incident, or cross_trip.
	 */
	public static function incident_vertical( $incident_type ) {
		if ( in_array( $incident_type, self::CROSS_TRIP_INCIDENTS, true ) ) {
			return 'cross_trip';
		}
		foreach ( self::INCIDENTS as $vertical => $types ) {
			if ( in_array( $incident_type, $types, true ) ) {
				return $vertical;
			}
		}
		return '';
	}

	public static function is_high_impact_scope( $scope ) {
		return in_array( $scope, self::HIGH_IMPACT_SCOPES, true );
	}
}
