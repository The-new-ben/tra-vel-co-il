<?php
/**
 * Closed service-family vocabulary for the private 360 service breadth gate.
 *
 * The crosswalk is an orchestration boundary only. A service family always
 * retains its own subtype, operational facts, deadlines, and handoffs.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Service_Breadth_Taxonomy {
	const CONTRACT_VERSION = '1.1.0';
	const SCENARIO_COUNT = 34;
	const SCENARIO_SLOTS = array( 1, 2 );

	const CANONICAL_VERTICALS = array(
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

	const SERVICE_FAMILIES = array(
		'air_transport',
		'lodging',
		'dynamic_package',
		'organized_tour',
		'cruise',
		'ferry',
		'rail',
		'coach_bus',
		'ground_transfer',
		'car_rental',
		'airport_ancillary',
		'experience',
		'dining',
		'travel_protection',
		'connectivity',
		'equipment',
		'entry_document_assistance',
	);

	const LIFECYCLE_STAGES = array(
		'search',
		'revalidate',
		'hold_reserve',
		'confirm',
		'fulfill',
		'change',
		'cancel',
		'refund',
		'incident',
		'reconciliation',
		'settlement',
		'post_service_evidence',
	);

	const APPLICABILITY = array( 'required', 'conditional', 'not_applicable' );
	const LOCAL_SCOPES = array( 'domestic', 'domestic_and_outbound', 'outbound_gateway', 'cross_border_gateway', 'nationwide', 'destination_and_domestic' );
	const MAP_GEOMETRIES = array( 'route', 'point', 'multi_point', 'corridor', 'polygon', 'terminal', 'venue' );
	const EVENT_STATES = array( 'current', 'stale', 'missed' );
	const EXPECTED_OUTCOMES = array( 'evidence_required', 'operator_review', 'approval_required', 'replan_required', 'after_hours_escalation', 'safety_escalation', 'reconciliation_required' );
	const PAYMENT_STATES = array( 'not_started', 'existing_authorization_observed', 'requires_separate_authorization', 'uncertain' );
	const REFUND_STATES = array( 'not_started', 'quote_required', 'request_planned', 'existing_partial_observed', 'existing_full_observed', 'uncertain' );
	const SETTLEMENT_STATES = array( 'not_started', 'accrual_review', 'reconciliation_required', 'disputed', 'uncertain' );
	const MAP_RESOLUTION_PATH = array( 'overview', 'decision', 'operational' );

	const SYNTHETIC_REF_PREFIXES = array(
		'registry'       => 'rg',
		'family'         => 'fm',
		'scenario'       => 'sc',
		'route'          => 'rt',
		'partition_scope' => 'sp',
		'party'          => 'pt',
		'service'        => 'sv',
	);

	const ISRAEL_LOCAL_GROUND_MOBILITY_COVERAGE = array(
		'taxi_ride'            => 'ground_transfer',
		'shared_shuttle'       => 'ground_transfer',
		'private_driver'       => 'ground_transfer',
		'public_local_transit' => 'ground_transfer',
		'rental_car'           => 'car_rental',
		'rail'                 => 'rail',
		'coach'                => 'coach_bus',
		'ferry'                => 'ferry',
	);

	/**
	 * Operational proof codes for Israel-local subtypes that cannot be treated
	 * as interchangeable labels inside a broader family.
	 *
	 * @return array
	 */
	public static function priority_subtype_operations() {
		return array(
			'lodging' => array(
				'city_business_hotel' => self::subtype_operation(
					'city_business_arrival_and_workday_fit',
					'business_hotel_late_arrival_deadline_local',
					'city_hotel_front_desk_handoff',
					'city_hotel_public_transport_anchor'
				),
				'resort_hotel' => self::subtype_operation(
					'resort_facility_schedule_and_fee_scope',
					'resort_facility_reservation_deadline_local',
					'resort_guest_services_handoff',
					'resort_reception_and_facility_anchor'
				),
				'boutique_hotel' => self::subtype_operation(
					'boutique_unit_variance_and_staffing',
					'boutique_staffed_check_in_deadline_local',
					'boutique_on_call_host_handoff',
					'boutique_hotel_entrance_anchor'
				),
				'vacation_apartment_short_term_rental' => self::subtype_operation(
					'short_term_rental_host_key_and_registration_state',
					'short_term_rental_key_release_deadline_local',
					'short_term_rental_host_and_platform_handoff',
					'short_term_rental_key_handoff_anchor'
				),
				'villa' => self::subtype_operation(
					'villa_occupancy_pool_and_whole_property_scope',
					'villa_guest_manifest_deadline_utc',
					'villa_property_manager_handoff',
					'villa_gate_and_safety_anchor'
				),
				'hostel' => self::subtype_operation(
					'hostel_bed_dorm_locker_and_age_rules',
					'hostel_late_arrival_deadline_local',
					'hostel_reception_handoff',
					'hostel_reception_and_bed_anchor'
				),
				'rural_bnb_zimmer' => self::subtype_operation(
					'rural_zimmer_access_shelter_and_host_handoff',
					'rural_zimmer_host_reconfirmation_deadline_local',
					'rural_zimmer_host_handoff',
					'rural_zimmer_access_and_shelter_anchor'
				),
				'kibbutz_holiday_village_guest_accommodation' => self::subtype_operation(
					'kibbutz_guest_unit_gate_dining_and_holiday_access',
					'kibbutz_gate_access_deadline_local',
					'kibbutz_guest_house_handoff',
					'kibbutz_gate_and_guest_unit_anchor'
				),
				'campground_glamping' => self::subtype_operation(
					'campground_pitch_utility_weather_and_fire_rules',
					'campground_arrival_weather_decision_deadline_local',
					'campground_operator_or_ranger_handoff',
					'campground_pitch_and_emergency_anchor'
				),
			),
			'experience' => array(
				'local_guide_tour' => self::subtype_operation(
					'licensed_local_guide_identity_language_and_route',
					'guide_meeting_point_reconfirmation_deadline_local',
					'licensed_local_guide_handoff',
					'guide_meeting_point_anchor'
				),
				'attraction' => self::subtype_operation(
					'attraction_timed_entry_capacity_and_height_rules',
					'attraction_timed_entry_deadline_local',
					'attraction_admission_handoff',
					'attraction_admission_gate_anchor'
				),
				'museum' => self::subtype_operation(
					'museum_gallery_hours_ticket_and_accessibility_route',
					'museum_last_admission_deadline_local',
					'museum_visitor_services_handoff',
					'museum_accessible_entrance_anchor'
				),
				'nature_reserve_park' => self::subtype_operation(
					'nature_reserve_permit_trail_weather_and_closure_state',
					'nature_reserve_entry_and_weather_decision_deadline_local',
					'nature_reserve_ranger_handoff',
					'nature_reserve_trailhead_and_exit_anchor'
				),
				'beach' => self::subtype_operation(
					'beach_lifeguard_water_condition_and_access_route',
					'beach_lifeguard_service_window_deadline_local',
					'beach_safety_operator_handoff',
					'beach_lifeguard_and_access_anchor'
				),
				'spa_wellness' => self::subtype_operation(
					'spa_treatment_slot_health_and_accessibility_fit',
					'spa_treatment_change_deadline_local',
					'spa_reception_and_therapist_handoff',
					'spa_reception_and_treatment_anchor'
				),
				'event' => self::subtype_operation(
					'event_seat_entry_gate_and_cancellation_state',
					'event_gate_and_ticket_claim_deadline_local',
					'event_box_office_and_security_handoff',
					'event_gate_seat_and_exit_anchor'
				),
			),
			'ground_transfer' => array(
				'taxi_ride' => self::subtype_operation(
					'taxi_license_meter_fare_basis_and_pickup_zone',
					'taxi_dispatch_acceptance_deadline_utc',
					'licensed_taxi_dispatch_handoff',
					'licensed_taxi_pickup_zone_anchor'
				),
				'private_driver' => self::subtype_operation(
					'private_driver_identity_vehicle_duty_and_multistop_scope',
					'private_driver_itinerary_reconfirmation_deadline_utc',
					'private_driver_operations_handoff',
					'private_driver_meeting_point_anchor'
				),
				'public_local_transit' => self::subtype_operation(
					'public_transit_agency_route_trip_and_service_calendar',
					'public_transit_last_service_decision_deadline_local',
					'public_transit_operations_handoff',
					'public_transit_boarding_platform_anchor'
				),
			),
		);
	}

	/**
	 * Return all 17 explicit service-family profiles.
	 *
	 * @return array
	 */
	public static function definitions() {
		return array(
			'air_transport' => self::profile(
				'air_transport', 'flight',
				array( 'scheduled_flight', 'charter_flight', 'domestic_flight' ),
				self::lifecycle( 'conditional', 'required', 'required', 'conditional', 'required' ),
				array( 'origin_destination_airport_codes', 'operating_marketing_carrier_roles', 'segment_and_traveler_status', 'fare_brand_and_baggage_rules', 'ticket_coupon_and_emd_status', 'special_service_request_status', 'connection_and_minimum_time' ),
				array( 'offer_expiry_utc', 'ticketing_deadline_utc', 'check_in_cutoff_utc', 'change_cancel_deadline_utc' ),
				array( 'airline_order_servicing_handoff', 'ticketing_consolidator_handoff', 'airport_disruption_desk_handoff' ),
				'domestic_and_outbound', array( 'israel_airport_terminal', 'domestic_airport_pair', 'departure_security_buffer', 'holiday_operating_schedule' ),
				2, 6, 14, 'route'
			),
			'lodging' => self::profile(
				'lodging', 'accommodation',
				array( 'city_business_hotel', 'resort_hotel', 'boutique_hotel', 'vacation_apartment_short_term_rental', 'villa', 'hostel', 'rural_bnb_zimmer', 'kibbutz_holiday_village_guest_accommodation', 'campground_glamping' ),
				self::lifecycle( 'conditional', 'required', 'required', 'conditional', 'required' ),
				array(
					'property_and_unit_geography', 'occupancy_and_child_age_rules', 'room_allocation_and_bedding', 'check_in_and_key_handoff', 'meal_board_and_kosher_scope', 'accessibility_route_and_unit', 'tax_deposit_and_damage_terms',
					'city_business_arrival_and_workday_fit', 'resort_facility_schedule_and_fee_scope', 'boutique_unit_variance_and_staffing', 'short_term_rental_host_key_and_registration_state', 'villa_occupancy_pool_and_whole_property_scope', 'hostel_bed_dorm_locker_and_age_rules', 'rural_zimmer_access_shelter_and_host_handoff', 'kibbutz_guest_unit_gate_dining_and_holiday_access', 'campground_pitch_utility_weather_and_fire_rules',
				),
				array(
					'cancellation_deadline_utc', 'payment_schedule_deadline_utc', 'arrival_notice_deadline_utc', 'check_in_cutoff_local',
					'business_hotel_late_arrival_deadline_local', 'resort_facility_reservation_deadline_local', 'boutique_staffed_check_in_deadline_local', 'short_term_rental_key_release_deadline_local', 'villa_guest_manifest_deadline_utc', 'hostel_late_arrival_deadline_local', 'rural_zimmer_host_reconfirmation_deadline_local', 'kibbutz_gate_access_deadline_local', 'campground_arrival_weather_decision_deadline_local',
				),
				array(
					'property_reservations_handoff', 'property_after_hours_handoff', 'local_lodging_recovery_handoff',
					'city_hotel_front_desk_handoff', 'resort_guest_services_handoff', 'boutique_on_call_host_handoff', 'short_term_rental_host_and_platform_handoff', 'villa_property_manager_handoff', 'hostel_reception_handoff', 'rural_zimmer_host_handoff', 'kibbutz_guest_house_handoff', 'campground_operator_or_ranger_handoff',
				),
				'domestic_and_outbound', array( 'local_tax_treatment', 'local_key_handoff', 'shabbat_holiday_operation', 'kosher_evidence_scope' ),
				4, 11, 18, 'point'
			),
			'dynamic_package' => self::profile(
				'dynamic_package', 'package',
				array( 'flight_lodging_package', 'lodging_activity_package', 'custom_multi_component_package' ),
				self::lifecycle( 'conditional', 'required', 'required', 'conditional', 'required' ),
				array( 'component_dependency_graph', 'package_organizer_and_merchant_roles', 'atomic_price_and_availability_window', 'traveler_and_room_allocation', 'component_terms_and_protections', 'transfer_and_connection_feasibility', 'component_fulfillment_evidence' ),
				array( 'atomic_revalidation_deadline_utc', 'earliest_component_cancellation_utc', 'package_balance_due_utc', 'customer_approval_expiry_utc' ),
				array( 'package_orchestrator_handoff', 'component_supplier_handoff', 'cross_component_recovery_handoff' ),
				'domestic_and_outbound', array( 'domestic_component_scope', 'local_transfer_feasibility', 'local_holiday_constraints' ),
				2, 8, 17, 'multi_point'
			),
			'organized_tour' => self::profile(
				'organized_tour', 'package',
				array( 'guided_group_tour', 'private_group_tour', 'series_departure_tour' ),
				self::lifecycle( 'conditional', 'required', 'required', 'conditional', 'required' ),
				array( 'departure_and_minimum_group_status', 'meeting_points_and_daily_sequence', 'guide_language_and_license_scope', 'included_excluded_services', 'rooming_and_transport_manifest', 'mobility_and_accessibility_fit', 'operator_contingency_plan' ),
				array( 'minimum_group_decision_utc', 'final_manifest_deadline_utc', 'balance_due_utc', 'tour_cancellation_deadline_utc' ),
				array( 'tour_operator_handoff', 'guide_and_ground_team_handoff', 'tour_emergency_handoff' ),
				'domestic_and_outbound', array( 'licensed_israel_guide_scope', 'domestic_pickup_points', 'local_accessibility_route' ),
				3, 9, 17, 'corridor'
			),
			'cruise' => self::profile(
				'cruise', 'package',
				array( 'ocean_cruise', 'river_cruise', 'expedition_cruise' ),
				self::lifecycle( 'conditional', 'required', 'required', 'conditional', 'required' ),
				array( 'embarkation_and_disembarkation_ports', 'ship_sailing_and_cabin_category', 'passenger_manifest_status', 'entry_health_and_document_requirements', 'port_call_and_shore_sequence', 'dining_and_accessibility_requests', 'cruise_inclusions_and_gratuity_terms' ),
				array( 'final_payment_deadline_utc', 'manifest_deadline_utc', 'online_check_in_deadline_utc', 'shore_service_cancellation_utc' ),
				array( 'cruise_operator_handoff', 'port_agent_handoff', 'shipboard_guest_care_handoff' ),
				'outbound_gateway', array( 'israel_embarkation_port', 'israel_port_access', 'outbound_document_buffer' ),
				2, 7, 15, 'route'
			),
			'ferry' => self::profile(
				'ferry', 'transfer',
				array( 'ferry', 'passenger_ferry', 'vehicle_ferry', 'overnight_ferry' ),
				self::lifecycle( 'conditional', 'required', 'required', 'conditional', 'required' ),
				array( 'departure_arrival_port_and_terminal', 'vessel_and_sailing_identity', 'passenger_and_vehicle_manifest', 'boarding_accessibility_and_assistance', 'baggage_vehicle_and_pet_rules', 'cabin_or_seat_allocation', 'border_document_requirements' ),
				array( 'passenger_check_in_cutoff_utc', 'vehicle_check_in_cutoff_utc', 'manifest_submission_deadline_utc', 'ferry_cancellation_deadline_utc' ),
				array( 'ferry_operator_handoff', 'port_terminal_handoff', 'onward_ground_recovery_handoff' ),
				'cross_border_gateway', array( 'israel_port_terminal', 'border_control_buffer', 'onward_local_transport' ),
				5, 10, 16, 'route'
			),
			'rail' => self::profile(
				'rail', 'transfer',
				array( 'rail', 'intercity_rail', 'airport_rail', 'sleeper_rail', 'urban_rail' ),
				self::lifecycle( 'conditional', 'required', 'required', 'conditional', 'conditional' ),
				array( 'station_and_service_identifiers', 'departure_arrival_and_platform_state', 'seat_or_berth_reservation', 'fare_and_exchange_rules', 'accessibility_and_station_assistance', 'baggage_bicycle_and_child_rules', 'connection_and_disruption_state' ),
				array( 'ticketing_deadline_utc', 'seat_release_deadline_utc', 'boarding_cutoff_utc', 'rail_cancellation_deadline_utc' ),
				array( 'rail_inventory_handoff', 'station_assistance_handoff', 'alternate_transport_handoff' ),
				'domestic_and_outbound', array( 'israel_station_access', 'local_service_alert', 'holiday_schedule_revision' ),
				5, 11, 17, 'route'
			),
			'coach_bus' => self::profile(
				'coach_bus', 'transfer',
				array( 'coach', 'scheduled_coach', 'airport_bus', 'tour_coach', 'cross_border_bus' ),
				self::lifecycle( 'conditional', 'required', 'required', 'conditional', 'conditional' ),
				array( 'operator_service_and_stop_identifiers', 'pickup_dropoff_coordinates', 'seat_and_passenger_manifest', 'baggage_and_equipment_rules', 'border_and_document_scope', 'accessibility_and_child_seat_fit', 'service_alert_and_vehicle_state' ),
				array( 'boarding_cutoff_utc', 'baggage_check_deadline_utc', 'coach_cancellation_deadline_utc', 'manifest_submission_deadline_utc' ),
				array( 'coach_operator_handoff', 'stop_or_terminal_handoff', 'border_and_local_recovery_handoff' ),
				'domestic_and_outbound', array( 'israel_stop_geometry', 'domestic_road_alert', 'holiday_service_pattern' ),
				6, 12, 17, 'route'
			),
			'ground_transfer' => self::profile(
				'ground_transfer', 'transfer',
				array( 'airport_private_transfer', 'taxi_ride', 'shared_shuttle', 'private_driver', 'public_local_transit' ),
				self::lifecycle( 'conditional', 'required', 'required', 'conditional', 'required' ),
				array(
					'pickup_dropoff_and_meeting_geometry', 'flight_tracking_binding', 'vehicle_occupancy_and_luggage_fit', 'child_seat_and_accessibility_fit', 'driver_assignment_and_contact_relay', 'waiting_grace_and_no_show_terms', 'toll_parking_and_route_scope',
					'taxi_license_meter_fare_basis_and_pickup_zone', 'taxi_vehicle_driver_assignment_and_accessibility_fit', 'private_driver_identity_vehicle_duty_and_multistop_scope', 'private_driver_waiting_parking_and_route_revision', 'public_transit_agency_route_trip_and_service_calendar', 'public_transit_realtime_freshness_fare_and_accessibility',
				),
				array( 'free_cancellation_deadline_utc', 'pickup_reconfirmation_deadline_utc', 'driver_assignment_deadline_utc', 'no_show_decision_deadline_utc', 'taxi_dispatch_acceptance_deadline_utc', 'private_driver_itinerary_reconfirmation_deadline_utc', 'public_transit_last_service_decision_deadline_local' ),
				array( 'transfer_dispatch_handoff', 'driver_contact_relay_handoff', 'airport_meet_and_greet_handoff', 'licensed_taxi_dispatch_handoff', 'private_driver_operations_handoff', 'public_transit_operations_handoff' ),
				'domestic_and_outbound', array( 'israel_pickup_zone', 'local_child_seat_rule', 'domestic_road_condition' ),
				8, 14, 19, 'route'
			),
			'car_rental' => self::profile(
				'car_rental', 'transfer',
				array( 'rental_car', 'airport_car_rental', 'city_car_rental', 'one_way_car_rental' ),
				self::lifecycle( 'conditional', 'required', 'required', 'conditional', 'required' ),
				array( 'driver_eligibility_and_license_rules', 'vehicle_category_not_model_guarantee', 'pickup_dropoff_office_and_hours', 'deposit_and_payment_instrument_rules', 'fuel_mileage_and_cross_border_rules', 'child_seat_and_accessibility_fit', 'damage_excess_and_roadside_scope' ),
				array( 'free_cancellation_deadline_utc', 'pickup_hold_deadline_utc', 'license_evidence_deadline_utc', 'vehicle_return_deadline_local' ),
				array( 'rental_desk_handoff', 'rental_inventory_and_desk_servicing_handoff', 'roadside_assistance_handoff', 'damage_and_return_handoff' ),
				'domestic_and_outbound', array( 'israel_driver_eligibility', 'local_toll_and_parking_rules', 'domestic_roadside_route' ),
				7, 13, 18, 'corridor'
			),
			'airport_ancillary' => self::profile(
				'airport_ancillary', 'activity',
				array( 'airport_lounge', 'fast_track', 'airport_parking', 'baggage_storage' ),
				self::lifecycle( 'conditional', 'conditional', 'required', 'conditional', 'conditional' ),
				array( 'airport_terminal_and_access_zone', 'service_date_time_and_duration', 'traveler_guest_and_vehicle_scope', 'operating_hours_and_capacity_state', 'accessibility_and_admission_rules', 'baggage_vehicle_and_security_restrictions', 'voucher_or_access_evidence' ),
				array( 'reservation_cancellation_deadline_utc', 'terminal_arrival_deadline_utc', 'parking_entry_deadline_utc', 'storage_collection_deadline_utc' ),
				array( 'airport_service_admission_handoff', 'terminal_operations_handoff', 'parking_or_storage_recovery_handoff' ),
				'outbound_gateway', array( 'israel_terminal_zone', 'airport_security_access_rule', 'local_parking_access' ),
				10, 16, 20, 'terminal'
			),
			'experience' => self::profile(
				'experience', 'activity',
				array( 'local_guide_tour', 'attraction', 'museum', 'nature_reserve_park', 'beach', 'spa_wellness', 'event' ),
				self::lifecycle( 'conditional', 'required', 'required', 'conditional', 'conditional' ),
				array(
					'venue_meeting_point_and_geometry', 'start_time_duration_and_timezone', 'participant_age_and_capacity_rules', 'accessibility_and_mobility_fit', 'weather_and_safety_constraints', 'admission_type_and_fulfillment_method', 'guide_language_and_inclusion_scope',
					'licensed_local_guide_identity_language_and_route', 'attraction_timed_entry_capacity_and_height_rules', 'museum_gallery_hours_ticket_and_accessibility_route', 'nature_reserve_permit_trail_weather_and_closure_state', 'beach_lifeguard_water_condition_and_access_route', 'spa_treatment_slot_health_and_accessibility_fit', 'event_seat_entry_gate_and_cancellation_state',
				),
				array(
					'activity_cancellation_deadline_utc', 'meeting_time_deadline_utc', 'ticket_claim_deadline_utc', 'weather_decision_deadline_utc',
					'guide_meeting_point_reconfirmation_deadline_local', 'attraction_timed_entry_deadline_local', 'museum_last_admission_deadline_local', 'nature_reserve_entry_and_weather_decision_deadline_local', 'beach_lifeguard_service_window_deadline_local', 'spa_treatment_change_deadline_local', 'event_gate_and_ticket_claim_deadline_local',
				),
				array(
					'activity_operator_handoff', 'venue_ticketing_handoff', 'local_safety_and_recovery_handoff',
					'licensed_local_guide_handoff', 'attraction_admission_handoff', 'museum_visitor_services_handoff', 'nature_reserve_ranger_handoff', 'beach_safety_operator_handoff', 'spa_reception_and_therapist_handoff', 'event_box_office_and_security_handoff',
				),
				'destination_and_domestic', array( 'israel_venue_license_scope', 'local_weather_and_security_state', 'domestic_accessibility_route' ),
				6, 13, 19, 'venue'
			),
			'dining' => self::profile(
				'dining', 'dining',
				array( 'restaurant_booking', 'private_dining', 'meal_delivery' ),
				self::lifecycle( 'conditional', 'required', 'required', 'conditional', 'conditional' ),
				array( 'venue_and_seating_time', 'party_size_and_seating_configuration', 'kosher_certificate_and_scope', 'allergen_protocol_and_kitchen_acknowledgement', 'deposit_minimum_spend_and_no_show_terms', 'accessibility_and_children_fit', 'late_arrival_and_service_window' ),
				array( 'reservation_hold_deadline_utc', 'cancellation_no_show_deadline_utc', 'menu_confirmation_deadline_utc', 'arrival_grace_deadline_utc' ),
				array( 'dining_reservation_handoff', 'kitchen_allergen_handoff', 'after_hours_host_handoff' ),
				'destination_and_domestic', array( 'israel_kosher_evidence', 'local_holiday_operation', 'domestic_accessibility_route' ),
				8, 15, 20, 'venue'
			),
			'travel_protection' => self::profile(
				'travel_protection', 'insurance',
				array( 'travel_insurance', 'emergency_assistance', 'medical_assistance', 'baggage_assistance', 'trip_disruption_assistance' ),
				self::lifecycle( 'not_applicable', 'required', 'required', 'conditional', 'required' ),
				array( 'policy_jurisdiction_and_purchase_window', 'insured_trip_and_party_reference_scope', 'coverage_limit_exclusion_evidence', 'declaration_and_eligibility_state', 'assistance_channel_and_case_reference', 'incident_evidence_checklist', 'claim_and_payment_status_separation' ),
				array( 'purchase_eligibility_deadline_utc', 'coverage_start_end_utc', 'incident_notification_deadline_utc', 'claim_evidence_deadline_utc' ),
				array( 'licensed_distribution_handoff', 'emergency_assistance_handoff', 'claims_handler_handoff' ),
				'nationwide', array( 'israel_policy_jurisdiction', 'local_emergency_channel', 'domestic_coverage_scope' ),
				2, 6, 12, 'polygon'
			),
			'connectivity' => self::profile(
				'connectivity', 'connectivity',
				array( 'esim', 'physical_sim', 'pocket_wifi' ),
				self::lifecycle( 'conditional', 'required', 'required', 'conditional', 'required' ),
				array( 'destination_and_network_coverage', 'device_compatibility_and_lock_state', 'data_allowance_and_fair_use', 'activation_method_and_window', 'roaming_and_network_restrictions', 'pickup_delivery_and_return_scope', 'support_and_outage_state' ),
				array( 'activation_deadline_utc', 'pickup_delivery_deadline_utc', 'connectivity_cancellation_deadline_utc', 'device_return_deadline_utc' ),
				array( 'activation_support_handoff', 'carrier_outage_handoff', 'device_return_handoff' ),
				'domestic_and_outbound', array( 'israel_activation_channel', 'domestic_network_scope', 'outbound_roaming_origin' ),
				3, 9, 15, 'polygon'
			),
			'equipment' => self::profile(
				'equipment', 'equipment',
				array( 'equipment_rental', 'equipment_sale', 'mobility_aid', 'child_gear' ),
				self::lifecycle( 'conditional', 'required', 'required', 'conditional', 'required' ),
				array( 'item_specification_size_and_fit', 'inventory_revision_and_condition', 'safety_certificate_or_recall_state', 'pickup_delivery_and_installation', 'deposit_damage_and_loss_terms', 'sanitation_battery_and_maintenance', 'accessibility_and_traveler_fit' ),
				array( 'fitting_confirmation_deadline_utc', 'delivery_installation_deadline_utc', 'equipment_cancellation_deadline_utc', 'rental_return_deadline_utc' ),
				array( 'equipment_vendor_handoff', 'fit_or_accessibility_specialist_handoff', 'replacement_and_recovery_handoff' ),
				'destination_and_domestic', array( 'israel_delivery_zone', 'local_safety_standard', 'domestic_repair_or_replacement_route' ),
				7, 14, 20, 'point'
			),
			'entry_document_assistance' => self::profile(
				'entry_document_assistance', 'activity',
				array( 'visa_assistance', 'eta_assistance', 'entry_document_review' ),
				self::lifecycle( 'not_applicable', 'required', 'required', 'conditional', 'conditional' ),
				array( 'nationality_residency_scope_reference', 'destination_and_transit_rule_scope', 'document_type_validity_and_entry_count', 'official_authority_source_revision', 'application_and_proof_checklist', 'appointment_or_biometric_requirement', 'assistance_not_issuance_authority' ),
				array( 'official_application_deadline_utc', 'appointment_or_biometric_deadline_utc', 'document_submission_deadline_utc', 'departure_safety_buffer_deadline_utc' ),
				array( 'official_information_source_handoff', 'customer_evidence_vault_handoff', 'licensed_specialist_or_consulate_referral' ),
				'outbound_gateway', array( 'israel_residency_scope', 'outbound_border_authority_source', 'israel_document_delivery_route' ),
				2, 6, 13, 'polygon'
			),
		);
	}

	/**
	 * Return two deterministic adversarial blueprints per family.
	 *
	 * @return array
	 */
	public static function scenario_blueprints() {
		return array(
			'air_transport' => array(
				self::blueprint( 'scheduled_flight', 'ticketing_deadline_near', 'stale', false, 'ticketing_deadline_utc', 'ticketing_consolidator_handoff', array( 'refresh_ticketing_evidence', 'protect_ticketing_deadline', 'request_customer_approval' ), 'evidence_required' ),
				self::blueprint( 'domestic_flight', 'schedule_change_event_missed', 'missed', true, 'check_in_cutoff_utc', 'airport_disruption_desk_handoff', array( 'retrieve_authoritative_order', 'protect_connection', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
			'lodging' => array(
				self::blueprint( 'city_business_hotel', 'room_allocation_revision_stale', 'stale', false, 'cancellation_deadline_utc', 'property_reservations_handoff', array( 'refresh_room_allocation', 'preserve_unaffected_rooms', 'request_change_approval' ), 'evidence_required' ),
				self::blueprint( 'vacation_apartment_short_term_rental', 'late_arrival_message_missed', 'missed', true, 'arrival_notice_deadline_utc', 'property_after_hours_handoff', array( 'retrieve_delivery_status', 'protect_key_handoff', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
			'dynamic_package' => array(
				self::blueprint( 'flight_lodging_package', 'component_revalidation_stale', 'stale', false, 'atomic_revalidation_deadline_utc', 'package_orchestrator_handoff', array( 'refresh_all_component_evidence', 'preserve_unchanged_components', 'request_package_approval' ), 'replan_required' ),
				self::blueprint( 'custom_multi_component_package', 'component_failure_event_missed', 'missed', true, 'earliest_component_cancellation_utc', 'cross_component_recovery_handoff', array( 'recover_missing_component_event', 'recalculate_dependency_impact', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
			'organized_tour' => array(
				self::blueprint( 'guided_group_tour', 'minimum_group_status_stale', 'stale', false, 'minimum_group_decision_utc', 'tour_operator_handoff', array( 'refresh_departure_status', 'protect_alternative_window', 'request_operator_review' ), 'evidence_required' ),
				self::blueprint( 'private_group_tour', 'guide_departure_change_missed', 'missed', true, 'final_manifest_deadline_utc', 'guide_and_ground_team_handoff', array( 'recover_guide_change', 'preserve_group_services', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
			'cruise' => array(
				self::blueprint( 'ocean_cruise', 'cabin_assignment_stale', 'stale', false, 'final_payment_deadline_utc', 'cruise_operator_handoff', array( 'refresh_cabin_assignment', 'protect_fare_terms', 'request_cabin_review' ), 'evidence_required' ),
				self::blueprint( 'river_cruise', 'port_call_change_missed', 'missed', true, 'manifest_deadline_utc', 'port_agent_handoff', array( 'recover_port_event', 'revalidate_onward_services', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
			'ferry' => array(
				self::blueprint( 'vehicle_ferry', 'vehicle_manifest_stale', 'stale', false, 'vehicle_check_in_cutoff_utc', 'ferry_operator_handoff', array( 'refresh_vehicle_manifest', 'protect_sailing', 'request_manifest_review' ), 'evidence_required' ),
				self::blueprint( 'passenger_ferry', 'sailing_disruption_missed', 'missed', true, 'passenger_check_in_cutoff_utc', 'port_terminal_handoff', array( 'recover_sailing_event', 'protect_onward_connection', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
			'rail' => array(
				self::blueprint( 'intercity_rail', 'reservation_window_stale', 'stale', false, 'seat_release_deadline_utc', 'rail_inventory_handoff', array( 'refresh_seat_evidence', 'protect_reservation_window', 'request_exchange_review' ), 'evidence_required' ),
				self::blueprint( 'airport_rail', 'platform_change_missed', 'missed', true, 'boarding_cutoff_utc', 'station_assistance_handoff', array( 'recover_service_alert', 'update_station_path', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
			'coach_bus' => array(
				self::blueprint( 'scheduled_coach', 'pickup_manifest_stale', 'stale', false, 'manifest_submission_deadline_utc', 'coach_operator_handoff', array( 'refresh_manifest', 'preserve_confirmed_seats', 'request_operator_review' ), 'evidence_required' ),
				self::blueprint( 'airport_bus', 'stop_change_missed', 'missed', true, 'boarding_cutoff_utc', 'stop_or_terminal_handoff', array( 'recover_stop_event', 'update_pickup_geometry', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
			'ground_transfer' => array(
				self::blueprint( 'airport_private_transfer', 'driver_assignment_stale', 'stale', false, 'driver_assignment_deadline_utc', 'transfer_dispatch_handoff', array( 'refresh_driver_assignment', 'protect_pickup', 'request_dispatch_review' ), 'evidence_required' ),
				self::blueprint( 'shared_shuttle', 'pickup_delay_missed', 'missed', true, 'no_show_decision_deadline_utc', 'driver_contact_relay_handoff', array( 'recover_driver_event', 'protect_no_show_position', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
			'car_rental' => array(
				self::blueprint( 'airport_car_rental', 'driver_eligibility_evidence_stale', 'stale', false, 'license_evidence_deadline_utc', 'rental_desk_handoff', array( 'refresh_eligibility_evidence', 'protect_pickup_hold', 'request_rental_review' ), 'evidence_required' ),
				self::blueprint( 'one_way_car_rental', 'vehicle_substitution_missed', 'missed', true, 'pickup_hold_deadline_utc', 'rental_inventory_and_desk_servicing_handoff', array( 'recover_substitution_event', 'revalidate_fit_and_terms', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
			'airport_ancillary' => array(
				self::blueprint( 'airport_lounge', 'terminal_access_revision_stale', 'stale', false, 'terminal_arrival_deadline_utc', 'airport_service_admission_handoff', array( 'refresh_terminal_access', 'protect_admission', 'request_access_review' ), 'evidence_required' ),
				self::blueprint( 'baggage_storage', 'storage_closure_missed', 'missed', true, 'storage_collection_deadline_utc', 'parking_or_storage_recovery_handoff', array( 'recover_closure_event', 'protect_stored_item_chain', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
			'experience' => array(
				self::blueprint( 'local_guide_tour', 'admission_slot_stale', 'stale', false, 'activity_cancellation_deadline_utc', 'activity_operator_handoff', array( 'refresh_admission_slot', 'protect_party_allocation', 'request_activity_review' ), 'evidence_required' ),
				self::blueprint( 'event', 'event_cancellation_missed', 'missed', true, 'ticket_claim_deadline_utc', 'venue_ticketing_handoff', array( 'recover_cancellation_event', 'preserve_other_activities', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
			'dining' => array(
				self::blueprint( 'restaurant_booking', 'seating_confirmation_stale', 'stale', false, 'reservation_hold_deadline_utc', 'dining_reservation_handoff', array( 'refresh_seating_confirmation', 'protect_dietary_requirements', 'request_dining_review' ), 'evidence_required' ),
				self::blueprint( 'meal_delivery', 'kitchen_closure_missed', 'missed', true, 'arrival_grace_deadline_utc', 'after_hours_host_handoff', array( 'recover_kitchen_event', 'protect_meal_continuity', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
			'travel_protection' => array(
				self::blueprint( 'travel_insurance', 'policy_window_evidence_stale', 'stale', false, 'purchase_eligibility_deadline_utc', 'licensed_distribution_handoff', array( 'refresh_policy_evidence', 'protect_purchase_window', 'request_licensed_review' ), 'evidence_required' ),
				self::blueprint( 'medical_assistance', 'assistance_incident_missed', 'missed', true, 'incident_notification_deadline_utc', 'emergency_assistance_handoff', array( 'recover_incident_notice', 'protect_safety_and_coverage', 'escalate_after_hours' ), 'safety_escalation' ),
			),
			'connectivity' => array(
				self::blueprint( 'esim', 'activation_window_stale', 'stale', false, 'activation_deadline_utc', 'activation_support_handoff', array( 'refresh_activation_window', 'protect_installation_path', 'request_support_review' ), 'evidence_required' ),
				self::blueprint( 'pocket_wifi', 'service_outage_missed', 'missed', true, 'device_return_deadline_utc', 'carrier_outage_handoff', array( 'recover_outage_event', 'protect_connectivity_fallback', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
			'equipment' => array(
				self::blueprint( 'mobility_aid', 'fit_inventory_stale', 'stale', false, 'fitting_confirmation_deadline_utc', 'fit_or_accessibility_specialist_handoff', array( 'refresh_fit_and_inventory', 'protect_accessibility_need', 'request_specialist_review' ), 'evidence_required' ),
				self::blueprint( 'child_gear', 'equipment_recall_missed', 'missed', true, 'delivery_installation_deadline_utc', 'replacement_and_recovery_handoff', array( 'recover_recall_event', 'isolate_affected_item', 'escalate_after_hours' ), 'safety_escalation' ),
			),
			'entry_document_assistance' => array(
				self::blueprint( 'visa_assistance', 'official_requirement_revision_stale', 'stale', false, 'official_application_deadline_utc', 'official_information_source_handoff', array( 'refresh_official_requirement', 'protect_application_window', 'request_specialist_review' ), 'evidence_required' ),
				self::blueprint( 'eta_assistance', 'authority_update_missed', 'missed', true, 'departure_safety_buffer_deadline_utc', 'licensed_specialist_or_consulate_referral', array( 'recover_authority_update', 'reassess_departure_readiness', 'escalate_after_hours' ), 'after_hours_escalation' ),
			),
		);
	}

	/** Return one family profile or an empty array. */
	public static function definition( $family ) {
		$definitions = self::definitions();
		return is_string( $family ) && isset( $definitions[ $family ] ) ? $definitions[ $family ] : array();
	}

	/** Return a deterministic, non-name-bearing synthetic reference. */
	public static function synthetic_ref( $kind, $seed ) {
		if ( ! is_string( $kind ) || ! is_string( $seed ) || '' === $seed || ! isset( self::SYNTHETIC_REF_PREFIXES[ $kind ] ) ) {
			return '';
		}
		return self::SYNTHETIC_REF_PREFIXES[ $kind ] . '_syn_' . substr( hash( 'sha256', 'tra_vel_service_breadth_v1_1|' . $kind . '|' . $seed ), 0, 32 );
	}

	/** Return the dedicated operation adapter for a service family. */
	public static function orchestration_adapter( $family ) {
		if ( ! is_string( $family ) || ! in_array( $family, self::SERVICE_FAMILIES, true ) ) {
			return '';
		}
		return 'entry_document_assistance' === $family
			? 'document_assistance_orchestration_adapter_v1'
			: $family . '_orchestration_adapter_v1';
	}

	private static function lifecycle( $hold, $change, $cancel, $refund, $settlement ) {
		return array(
			'search'                => 'required',
			'revalidate'            => 'required',
			'hold_reserve'          => $hold,
			'confirm'               => 'required',
			'fulfill'               => 'required',
			'change'                => $change,
			'cancel'                => $cancel,
			'refund'                => $refund,
			'incident'              => 'required',
			'reconciliation'        => 'required',
			'settlement'            => $settlement,
			'post_service_evidence' => 'required',
		);
	}

	private static function profile( $family, $vertical, $subtypes, $lifecycle, $facts, $deadlines, $handoffs, $local_scope, $local_facts, $overview_zoom, $decision_zoom, $operational_zoom, $geometry ) {
		return array(
			'family_ref'         => self::synthetic_ref( 'family', $family ),
			'service_family'     => $family,
			'canonical_vertical' => $vertical,
			'family_subtypes'    => $subtypes,
			'crosswalk'          => array(
				'canonical_taxonomy'    => 'commerce_core_v1',
				'mapping_kind'          => 'orchestration_bucket_only',
				'operation_routing'     => 'dedicated_service_family_adapter',
				'orchestration_adapter' => self::orchestration_adapter( $family ),
				'equivalence_claimed'   => false,
				'subtype_preserved'     => true,
			),
			'lifecycle'          => $lifecycle,
			'critical_facts'     => $facts,
			'critical_deadlines' => $deadlines,
			'required_handoffs'  => $handoffs,
			'israel_local'       => array(
				'applicable'          => true,
				'scope'               => $local_scope,
				'required_fact_codes' => $local_facts,
			),
			'map'                => array(
				'overview_zoom'                       => $overview_zoom,
				'decision_zoom'                       => $decision_zoom,
				'operational_zoom'                    => $operational_zoom,
				'cluster_until_zoom'                  => $decision_zoom - 1,
				'geometry'                            => $geometry,
				'resolution_path'                     => self::MAP_RESOLUTION_PATH,
				'detail_surface'                      => 'attached_non_occluding_context_panel',
				'operational_anchor_codes'            => self::map_anchor_codes( $family, $geometry ),
				'selection_to_plan_required'          => true,
				'viewport_padding_required'           => true,
				'rtl_mobile_safe_area_required'       => true,
				'source_freshness_required'           => true,
				'reduced_motion_alternative_required' => true,
			),
		);
	}

	private static function subtype_operation( $fact, $deadline, $handoff, $map_anchor ) {
		return array(
			'critical_fact_code' => $fact,
			'deadline_code'      => $deadline,
			'handoff_code'       => $handoff,
			'map_anchor_code'    => $map_anchor,
		);
	}

	private static function map_anchor_codes( $family, $geometry ) {
		$geometry_anchors = array(
			'route'       => array( 'route_origin_anchor', 'route_destination_anchor', 'route_connection_anchor' ),
			'point'       => array( 'location_centroid_anchor', 'public_entrance_anchor', 'arrival_handoff_anchor' ),
			'multi_point' => array( 'component_origin_anchor', 'component_sequence_anchor', 'component_recovery_anchor' ),
			'corridor'    => array( 'corridor_origin_anchor', 'corridor_waypoint_anchor', 'corridor_destination_anchor' ),
			'polygon'     => array( 'coverage_boundary_anchor', 'service_entry_anchor', 'exception_region_anchor' ),
			'terminal'    => array( 'terminal_entry_anchor', 'service_zone_anchor', 'recovery_desk_anchor' ),
			'venue'       => array( 'venue_entrance_anchor', 'meeting_point_anchor', 'accessible_exit_anchor' ),
		);
		$anchors = isset( $geometry_anchors[ $geometry ] ) ? $geometry_anchors[ $geometry ] : array();
		$priority = self::priority_subtype_operations();
		if ( isset( $priority[ $family ] ) ) {
			foreach ( $priority[ $family ] as $operation ) {
				$anchors[] = $operation['map_anchor_code'];
			}
		}
		return array_values( array_unique( $anchors ) );
	}

	private static function blueprint( $subtype, $trigger, $event_state, $after_hours, $deadline, $handoff, $actions, $outcome ) {
		return array(
			'family_subtype'        => $subtype,
			'trigger'               => $trigger,
			'event_state'           => $event_state,
			'after_hours_required'   => $after_hours,
			'required_deadline_code' => $deadline,
			'required_handoff_code'  => $handoff,
			'expected_actions'       => $actions,
			'expected_outcome'       => $outcome,
		);
	}
}
