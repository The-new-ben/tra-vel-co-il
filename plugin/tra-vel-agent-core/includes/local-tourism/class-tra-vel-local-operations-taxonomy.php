<?php
/**
 * Closed vocabulary for the Israel local-operations companion layer.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Local_Operations_Taxonomy {
	const CONTRACT_VERSION = '1.0.0';

	const SERVICE_SCOPES = array( 'unit', 'session', 'route' );
	const KEY_HANDOFF_MODES = array( 'reception', 'meet_and_greet', 'lockbox', 'digital_key', 'driver_or_guide', 'not_applicable' );
	const TAX_TREATMENTS = array( 'included', 'excluded_on_arrival', 'conditional_exemption_review', 'not_applicable', 'unknown' );
	const DEPOSIT_TREATMENTS = array( 'none', 'preauthorization', 'captured_refundable', 'pay_on_arrival', 'unknown' );
	const CANCELLATION_TREATMENTS = array( 'free_until_deadline', 'tiered', 'non_refundable', 'supplier_review_required', 'unknown' );
	const TRANSPORT_MODES = array( 'walk', 'private_car', 'rental_car', 'taxi', 'bus', 'rail', 'domestic_flight', 'mixed' );
	const CORRIDORS = array( 'jerusalem_corridor', 'eilat_corridor' );

	const KOSHER_REQUIREMENTS = array( 'kosher_certificate', 'certificate_scope', 'food_classification', 'passover_operation' );
	const SHABBAT_REQUIREMENTS = array( 'shabbat_check_in', 'shabbat_checkout', 'manual_key', 'shabbat_elevator', 'walking_access', 'urn', 'hotplate', 'prearranged_meals', 'holiday_operation' );
	const ACCESSIBILITY_REQUIREMENTS = array( 'step_free_arrival', 'accessible_entrance', 'internal_route', 'lift', 'accessible_unit', 'accessible_bathroom', 'accessible_shower', 'bed_transfer_clearance', 'accessible_parking_route', 'accessible_transport', 'hearing_support', 'visual_alarm', 'tactile_information', 'sensory_support', 'service_animal', 'medical_refrigeration', 'equipment_charging', 'emergency_evacuation' );
	const PARKING_REQUIREMENTS = array( 'on_site', 'off_site', 'accessible_bay', 'reservation_required', 'ev_charging', 'vehicle_height_limit' );
	const ACTIVITY_REQUIREMENTS = array( 'private_guide', 'licensed_guide', 'small_group', 'family_session', 'accessible_route', 'weather_fallback', 'equipment_included' );
	const DINING_REQUIREMENTS = array( 'reservation', 'private_room', 'allergen_protocol', 'children_menu', 'late_service', 'certificate_current' );
	const EQUIPMENT_REQUIREMENTS = array( 'child_seat', 'mobility_aid', 'diving_gear', 'snorkel_gear', 'hiking_gear', 'medical_refrigeration', 'charging_adapter' );

	const BENEFIT_AXES = array( 'airline_inventory_ids', 'program_ids', 'credential_product_ids', 'campaign_revisions' );
	const SOURCE_AUTHORITIES = array( 'official_live_feed', 'official_registry', 'provider_authorized_api', 'signed_supplier_event', 'operator_confirmation', 'customer_report', 'synthetic_test' );
	const DISRUPTION_TYPES = array(
		'occupancy', 'accessibility', 'arrival', 'commercial_terms', 'shabbat', 'party_change',
		'supplier_revision', 'financial', 'benefit', 'weather', 'fire', 'flood', 'security',
		'evacuation', 'road', 'rail', 'utility', 'closure', 'certificate_expiry',
	);
	const SEVERITIES = array( 'P0', 'P1', 'P2', 'P3' );

	const SCENARIO_TRIGGERS = array(
		'occupancy_total_exceeded',
		'child_age_band_changed',
		'room_allocation_invalid',
		'accessible_unit_unavailable',
		'step_free_route_changed',
		'late_arrival',
		'key_handoff_failed',
		'check_in_window_changed',
		'check_out_window_changed',
		'vat_eligibility_changed',
		'deposit_terms_changed',
		'cancellation_deadline_near',
		'shabbat_check_in_conflict',
		'shabbat_key_conflict',
		'kosher_certificate_expired',
		'kosher_scope_conflict',
		'rail_service_disrupted',
		'road_corridor_closed',
		'domestic_flight_disrupted',
		'extreme_heat',
		'flash_flood',
		'wildfire_smoke',
		'security_restriction',
		'evacuation_order',
		'utility_outage',
		'venue_closed',
		'parking_unavailable',
		'ev_charging_unavailable',
		'accessible_route_blocked',
		'attraction_group_minimum_changed',
		'guide_unavailable',
		'equipment_unavailable',
		'activity_weather_cancelled',
		'partial_party_reduced',
		'partial_party_extended',
		'supplier_revision_superseded',
		'supplier_replacement_required',
		'partial_refund_observed',
		'replacement_payment_required',
		'benefit_source_stale',
		'benefit_eligibility_changed',
		'benefit_expired',
		'benefit_reversed',
	);

	const STRATEGIES = array(
		'reallocate_party', 'find_capacity_match', 'preserve_access_requirements',
		'reconfirm_arrival_and_keys', 'revalidate_local_terms', 'protect_deadline',
		'revalidate_shabbat_fit', 'revalidate_kosher_evidence', 'reroute_local_transport',
		'replace_domestic_segment', 'safety_hold_and_human_review', 'restore_utility_or_replace',
		'replace_closed_service', 'replace_parking_or_access_route', 'reconfigure_activity',
		'preserve_partial_party', 'revalidate_supplier_revision', 'propose_supplier_replacement',
		'reconcile_refund_separately', 'authorize_replacement_payment_separately',
		'refresh_benefit_evidence', 'reconcile_benefit_reversal',
	);

	/**
	 * Return a canonical token without inventing a fallback.
	 */
	public static function member( $value, $allowed ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Return a normalized, unique list or WP_Error.
	 *
	 * @return array|WP_Error
	 */
	public static function list_of( $values, $allowed, $allow_empty = false ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( ! $allow_empty && ! $values ) ) {
			return self::error( 'list_invalid', 'A canonical list is required.' );
		}
		$result = array();
		foreach ( $values as $value ) {
			$value = self::member( $value, $allowed );
			if ( '' === $value || isset( $result[ $value ] ) ) {
				return self::error( 'list_member_invalid', 'The list contains an unsupported or duplicate value.' );
			}
			$result[ $value ] = true;
		}
		return array_keys( $result );
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_local_operations_' . $suffix, $message, array( 'status' => 400 ) );
	}
}
