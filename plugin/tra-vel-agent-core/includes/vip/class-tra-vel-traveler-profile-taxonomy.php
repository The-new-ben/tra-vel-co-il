<?php
/**
 * Closed vocabulary for the private traveler-profile evidence index.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Traveler_Profile_Taxonomy {
	const CONTRACT_VERSION = '1.0.0';

	const SUBJECT_KINDS = array( 'adult', 'minor', 'dependent_adult' );
	const DATA_CLASSES  = array( 'identity', 'contact', 'travel_document', 'preference', 'accessibility', 'health', 'authority', 'loyalty', 'emergency' );
	const SOURCES       = array( 'traveler', 'document_capture', 'operator', 'supplier', 'government_rules_provider', 'imported_booking', 'loyalty_provider' );
	const ASSURANCE_LEVELS = array( 'unverified', 'self_asserted', 'contact_verified', 'document_matched', 'authority_verified', 'supplier_accepted' );
	const FIELD_STATES     = array( 'current', 'expiring', 'expired', 'conflicted', 'revoked' );
	const PURPOSES         = array( 'planning', 'quote', 'reservation', 'fulfillment', 'travel_readiness', 'servicing', 'claim', 'benefit_comparison' );
	const RETENTION_CLASSES = array( 'account_identity', 'active_trip_contact', 'travel_document', 'preference', 'accessibility_support', 'restricted_health', 'authority_evidence', 'benefit_membership', 'emergency_support' );

	const FIELD_CLASSES = array(
		'legal_name'                     => 'identity',
		'preferred_name'                 => 'identity',
		'date_of_birth'                  => 'identity',
		'passenger_type'                 => 'identity',
		'nationality'                    => 'identity',
		'residence_country'              => 'identity',
		'sex_marker_if_supplier_required'=> 'identity',
		'primary_email'                  => 'contact',
		'primary_mobile'                 => 'contact',
		'reachable_channel'              => 'contact',
		'postal_address'                 => 'contact',
		'document_type'                  => 'travel_document',
		'document_number'                => 'travel_document',
		'document_legal_name'            => 'travel_document',
		'document_nationality'           => 'travel_document',
		'document_issuing_country'       => 'travel_document',
		'document_issued_at'             => 'travel_document',
		'document_expires_at'            => 'travel_document',
		'communication_language'         => 'preference',
		'seat_preference'                => 'preference',
		'meal_preference'                => 'preference',
		'baggage_preference'             => 'preference',
		'room_preference'                => 'preference',
		'bed_preference'                 => 'preference',
		'dietary_requirement'            => 'preference',
		'trip_pace'                      => 'preference',
		'wheelchair_assistance_level'    => 'accessibility',
		'mobility_aid_specification'     => 'accessibility',
		'battery_specification'          => 'accessibility',
		'transfer_assistance'            => 'accessibility',
		'hearing_assistance'             => 'accessibility',
		'visual_assistance'              => 'accessibility',
		'cognitive_assistance'           => 'accessibility',
		'sensory_support'                => 'accessibility',
		'service_animal'                 => 'accessibility',
		'medical_equipment'              => 'accessibility',
		'medication_refrigeration'       => 'accessibility',
		'accessible_room_requirement'    => 'accessibility',
		'insurance_declaration_packet'   => 'health',
		'medical_clearance'              => 'health',
		'allergy_safety_packet'          => 'health',
		'guardian_relationship'          => 'authority',
		'guardian_authority_packet'      => 'authority',
		'dependent_support_plan'         => 'authority',
		'companion_consent'              => 'authority',
		'loyalty_program_membership'     => 'loyalty',
		'loyalty_member_identifier'      => 'loyalty',
		'loyalty_tier'                   => 'loyalty',
		'card_product_identity'          => 'loyalty',
		'emergency_contact'              => 'emergency',
		'emergency_contact_authority'    => 'emergency',
	);

	const RETENTION_BY_CLASS = array(
		'identity'        => 'account_identity',
		'contact'         => 'active_trip_contact',
		'travel_document' => 'travel_document',
		'preference'      => 'preference',
		'accessibility'   => 'accessibility_support',
		'health'          => 'restricted_health',
		'authority'       => 'authority_evidence',
		'loyalty'         => 'benefit_membership',
		'emergency'       => 'emergency_support',
	);

	const USE_CASE_REQUIREMENTS = array(
		'personalize' => array( 'communication_language' ),
		'flight_reservation' => array( 'legal_name', 'date_of_birth', 'passenger_type', 'nationality', 'primary_email', 'primary_mobile', 'document_type', 'document_number', 'document_legal_name', 'document_nationality', 'document_issuing_country', 'document_expires_at' ),
		'accommodation_reservation' => array( 'legal_name', 'primary_email', 'primary_mobile' ),
		'insurance_quote' => array( 'date_of_birth', 'residence_country' ),
		'emergency_ready' => array( 'primary_mobile', 'reachable_channel', 'emergency_contact' ),
		'benefit_connection' => array( 'loyalty_program_membership', 'loyalty_member_identifier' ),
	);

	public static function field_class( $code ) {
		return is_string( $code ) && isset( self::FIELD_CLASSES[ $code ] ) ? self::FIELD_CLASSES[ $code ] : '';
	}

	public static function retention_for_class( $class ) {
		return is_string( $class ) && isset( self::RETENTION_BY_CLASS[ $class ] ) ? self::RETENTION_BY_CLASS[ $class ] : '';
	}

	public static function requirements_for_use_case( $use_case, $flags ) {
		if ( ! is_string( $use_case ) || ! isset( self::USE_CASE_REQUIREMENTS[ $use_case ] ) || ! self::exact_flags( $flags ) ) {
			return array();
		}
		$requirements = self::USE_CASE_REQUIREMENTS[ $use_case ];
		if ( $flags['minor_present'] ) {
			$requirements[] = 'guardian_relationship';
			$requirements[] = 'guardian_authority_packet';
		}
		if ( $flags['dependent_adult_present'] ) {
			$requirements[] = 'dependent_support_plan';
			$requirements[] = 'guardian_authority_packet';
		}
		if ( $flags['accessibility_required'] ) {
			$requirements[] = 'transfer_assistance';
		}
		if ( $flags['loyalty_requested'] && 'benefit_connection' !== $use_case ) {
			$requirements[] = 'loyalty_program_membership';
		}
		$requirements = array_values( array_unique( $requirements ) );
		sort( $requirements, SORT_STRING );
		return $requirements;
	}

	private static function exact_flags( $flags ) {
		$keys = array( 'minor_present', 'dependent_adult_present', 'accessibility_required', 'loyalty_requested' );
		return is_array( $flags ) && ! array_diff( $keys, array_keys( $flags ) ) && ! array_diff( array_keys( $flags ), $keys ) && count( array_filter( $flags, 'is_bool' ) ) === count( $keys );
	}
}
