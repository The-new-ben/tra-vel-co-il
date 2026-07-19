<?php
/**
 * Closed vocabulary for the private post-booking servicing projection.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Postbooking_Servicing_Taxonomy {
	const CONTRACT_VERSION = '1.0.0';

	const CHANGE_CLASSES = array(
		'voluntary_change',
		'planned_schedule_change',
		'day_of_travel_disruption',
		'supplier_initiated_lodging_change',
		'traveler_lodging_change',
	);

	const FINANCIAL_OUTCOMES = array(
		'add_collect',
		'refund',
		'residual_value',
		'reusable_value',
		'even_exchange',
	);

	const COUPON_STATES = array(
		'open', 'airport_control', 'checked_in', 'lifted', 'used',
		'exchanged', 'refunded', 'void', 'suspended', 'unknown',
	);

	const TICKET_DOCUMENT_STATES = array(
		'issued', 'partially_exchanged', 'exchanged', 'refunded', 'void', 'unknown',
	);

	const EMD_STATES = array(
		'issued', 'partially_used', 'used', 'exchanged', 'refunded', 'void', 'unknown',
	);

	const ACTION_TYPES = array(
		'retrieve_current_supplier_order',
		'verify_reservation_separately',
		'verify_ticket_issuance',
		'verify_coupon_statuses',
		'verify_emd_fulfillment',
		'verify_servicing_owner',
		'quote_voluntary_change_rules',
		'verify_involuntary_entitlements',
		'preserve_unaffected_flight_scope',
		'retrieve_current_lodging_reservation',
		'reconcile_room_guest_date_occupancy',
		'reconcile_no_show_separately',
		'verify_inventory_restoration',
		'verify_message_delivery',
		'perform_authoritative_message_retrieval',
		'preserve_unaffected_lodging_scope',
		'reconcile_add_collect_separately',
		'reconcile_supplier_refund_separately',
		'reconcile_residual_value_separately',
		'reconcile_reusable_value_separately',
		'record_even_exchange_without_netting',
		'reconcile_traveler_repayment_separately',
		'reconcile_supplier_settlement_separately',
	);

	/**
	 * Validate one scalar vocabulary value.
	 */
	public static function member( $value, $allowed ) {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Validate a unique, ordered subset using canonical vocabulary order.
	 *
	 * @return array|WP_Error
	 */
	public static function ordered_subset( $values, $allowed, $allow_empty = false ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( ! $allow_empty && ! $values ) ) {
			return new WP_Error( 'tra_vel_postbooking_servicing_list_invalid', 'The servicing vocabulary list must be a non-associative array.' );
		}
		$canonical = array();
		foreach ( $allowed as $candidate ) {
			if ( in_array( $candidate, $values, true ) ) {
				$canonical[] = $candidate;
			}
		}
		if ( $canonical !== $values ) {
			return new WP_Error( 'tra_vel_postbooking_servicing_list_order_invalid', 'The servicing vocabulary list must be unique and canonically ordered.' );
		}
		return $canonical;
	}
}
