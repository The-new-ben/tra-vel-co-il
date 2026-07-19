<?php
/**
 * Canonical Israel local-tourism and map vocabulary.
 *
 * This file intentionally has no registration side effects. It can be loaded by
 * a future plugin bootstrap after the contracts and adapters are ready.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Local_Tourism_Taxonomy {
	const INVENTORY_TYPES = array(
		'hotel',
		'boutique_hotel',
		'apartment',
		'short_rental',
		'villa',
		'hostel',
		'guesthouse_bnb',
		'kibbutz_rural_lodging',
		'campsite',
		'glamping',
		'attraction',
		'tour',
		'dining',
		'mobility_transport',
	);

	const ACCOMMODATION_TYPES = array(
		'hotel',
		'boutique_hotel',
		'apartment',
		'short_rental',
		'villa',
		'hostel',
		'guesthouse_bnb',
		'kibbutz_rural_lodging',
		'campsite',
		'glamping',
	);

	const GEOGRAPHY_LEVELS = array( 'country', 'district', 'municipality', 'locality', 'neighborhood', 'address' );
	const COORDINATE_STATES = array( 'verified', 'approximate', 'unknown', 'conflict' );
	const DATA_MODES = array( 'planning_only', 'synthetic_demo', 'live_provider_response' );
	const SOURCE_AUTHORITIES = array(
		'signed_partner_response',
		'provider_authorized_api',
		'official_live_feed',
		'official_registry',
		'official_information',
		'operator_confirmation',
		'customer_report',
		'synthetic_test',
	);
	const SOURCE_KINDS = array( 'api_response', 'registry_record', 'live_feed', 'document', 'operator_message', 'customer_report', 'synthetic_contract' );
	const FRESHNESS_STATES = array( 'current', 'stale', 'unknown', 'conflict' );

	const FACT_STATES = array( 'verified_true', 'verified_false', 'unknown', 'conflict', 'not_applicable' );
	const FACT_GROUP_STATES = array( 'evidence_current', 'evidence_partial', 'unknown', 'conflict', 'not_applicable' );
	const FACT_GROUPS = array( 'kosher', 'shabbat', 'accessibility', 'family', 'parking', 'shelter', 'seasonal_operation' );
	const FACT_CODES = array(
		'kosher' => array( 'kosher_certificate', 'certificate_scope', 'food_classification', 'passover_operation' ),
		'shabbat' => array( 'shabbat_check_in', 'shabbat_checkout', 'manual_key', 'shabbat_elevator', 'walking_access', 'urn', 'hotplate', 'prearranged_meals', 'holiday_operation' ),
		'accessibility' => array( 'step_free_arrival', 'accessible_entrance', 'internal_route', 'lift', 'accessible_unit', 'accessible_bathroom', 'accessible_shower', 'bed_transfer_clearance', 'accessible_parking_route', 'accessible_transport', 'hearing_support', 'visual_alarm', 'tactile_information', 'sensory_support', 'service_animal', 'medical_refrigeration', 'equipment_charging', 'emergency_evacuation' ),
		'family' => array( 'children_allowed', 'child_age_policy', 'crib', 'extra_bed', 'connecting_units', 'high_chair', 'stroller_access', 'family_capacity' ),
		'parking' => array( 'on_site', 'off_site', 'accessible_bay', 'reservation_required', 'ev_charging', 'vehicle_height_limit' ),
		'shelter' => array( 'protected_space_on_site', 'route_confirmed', 'distance_confirmed', 'capacity_confirmed', 'accessible_route', 'last_property_confirmation' ),
		'seasonal_operation' => array( 'current_operation', 'weather_dependency', 'holiday_operation', 'live_closure', 'road_access', 'utility_state' ),
	);

	const AVAILABILITY_STATES = array( 'unknown', 'checking', 'available_verified', 'limited_verified', 'unavailable_verified', 'stale' );
	const PRICE_STATES = array( 'not_requested', 'checking', 'verified_quote', 'expired_quote', 'unavailable' );
	const OPERATING_STATES = array( 'open_verified', 'closed_verified', 'unknown', 'conflict' );
	const HOURS_STATES = array( 'current_verified', 'stale', 'unknown', 'conflict', 'not_applicable' );
	const OFFICIAL_CLASSIFICATION_STATES = array( 'ranked', 'unranked', 'expired', 'unknown', 'conflict', 'not_applicable' );

	const NAVIGATION_STATES = array( 'world_globe', 'country_focus', 'israel_region_overview', 'local_high_res_map', 'place_or_route_detail', 'itinerary_assembly', 'revalidated_proposal' );
	const RENDER_STATES = array( 'globe_ready', 'descending', 'local_tiles_loading', 'local_ready', 'degraded_tiles', 'offline_cached' );
	const LOCAL_MAP_LEVELS = array( 'world', 'country', 'region', 'city_or_rural', 'neighborhood', 'venue' );
	const VIEWPORT_CLASSES = array( 'mobile', 'desktop' );
	const READING_DIRECTIONS = array( 'rtl', 'ltr' );
	const MOTION_PREFERENCES = array( 'full', 'reduced' );
	const CONNECTIVITY_STATES = array( 'online', 'offline' );
	const BOTTOM_SHEET_STATES = array( 'absent', 'peek', 'half', 'full' );
	const SIDE_RAIL_STATES = array( 'absent', 'collapsed', 'open' );

	const STRESS_TRIGGERS = array(
		'missing_geo',
		'stale_hours',
		'conflicting_accessibility',
		'conflicting_kashrut',
		'unavailable_property',
		'map_tile_failure',
		'reduced_motion',
		'offline',
	);

	/**
	 * Normalize a member of a closed vocabulary or return an empty string.
	 */
	public static function member( $value, $allowed ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Return a canonical inventory type.
	 */
	public static function inventory_type( $value ) {
		return self::member( $value, self::INVENTORY_TYPES );
	}

	/**
	 * Return the broad commerce family without collapsing the sellable type.
	 */
	public static function inventory_family( $value ) {
		$value = self::inventory_type( $value );
		if ( in_array( $value, self::ACCOMMODATION_TYPES, true ) ) {
			return 'accommodation';
		}
		if ( in_array( $value, array( 'attraction', 'tour' ), true ) ) {
			return 'activity';
		}
		if ( 'dining' === $value ) {
			return 'dining';
		}
		if ( 'mobility_transport' === $value ) {
			return 'transfer';
		}
		return '';
	}

	/**
	 * Validate a fact code in its group. Codes are never accepted globally.
	 */
	public static function fact_code( $group, $code ) {
		$group = self::member( $group, self::FACT_GROUPS );
		$code  = sanitize_key( (string) $code );
		return $group && in_array( $code, self::FACT_CODES[ $group ], true ) ? $code : '';
	}

	/**
	 * Return a canonical stress trigger.
	 */
	public static function stress_trigger( $value ) {
		return self::member( $value, self::STRESS_TRIGGERS );
	}
}
