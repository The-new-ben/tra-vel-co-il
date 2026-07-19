<?php
/**
 * Independent checkout, payment, fulfillment, settlement and operation states.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_State_Machine {
	const TRANSITIONS = array(
		'checkout' => array(
			'draft'             => array( 'quote' => 'quoted', 'expire' => 'expired', 'abandon' => 'abandoned' ),
			'quoted'            => array( 'reprice' => 'quoted', 'request_approval' => 'awaiting_approval', 'expire' => 'expired', 'abandon' => 'abandoned' ),
			'awaiting_approval' => array( 'approve' => 'ready', 'reject' => 'abandoned', 'reprice' => 'quoted', 'expire' => 'expired', 'abandon' => 'abandoned' ),
			'ready'             => array( 'reprice' => 'quoted', 'expire' => 'expired', 'abandon' => 'abandoned' ),
			'expired'           => array(),
			'abandoned'         => array(),
		),
		'payment' => array(
			'not_started'       => array( 'start' => 'pending' ),
			'pending'           => array( 'require_action' => 'requires_action', 'authorize' => 'authorized', 'fail' => 'failed' ),
			'requires_action'   => array( 'authorize' => 'authorized', 'fail' => 'failed' ),
			'authorized'        => array( 'capture' => 'captured', 'void' => 'voided' ),
			'captured'          => array( 'partial_refund' => 'partially_refunded', 'refund' => 'refunded' ),
			'failed'            => array( 'retry' => 'pending' ),
			'voided'            => array(),
			'partially_refunded'=> array( 'partial_refund' => 'partially_refunded', 'refund' => 'refunded' ),
			'refunded'          => array(),
		),
		'fulfillment' => array(
			'selected'                => array( 'start_hold' => 'hold_pending', 'fail' => 'failed' ),
			'hold_pending'            => array( 'hold' => 'held', 'fail' => 'failed', 'uncertain' => 'reconciliation_required' ),
			'held'                    => array( 'start_confirmation' => 'confirmation_pending', 'start_cancellation' => 'cancellation_pending', 'expire_hold' => 'failed' ),
			'confirmation_pending'    => array( 'confirm' => 'confirmed', 'fail' => 'failed', 'uncertain' => 'reconciliation_required' ),
			'confirmed'               => array( 'start_fulfillment' => 'fulfillment_pending', 'start_change' => 'change_pending', 'start_cancellation' => 'cancellation_pending' ),
			'fulfillment_pending'     => array( 'fulfill' => 'fulfilled', 'fail' => 'failed', 'uncertain' => 'reconciliation_required' ),
			'fulfilled'               => array( 'start_change' => 'change_pending', 'start_cancellation' => 'cancellation_pending' ),
			'change_pending'          => array( 'change' => 'changed', 'fail' => 'failed', 'uncertain' => 'reconciliation_required' ),
			'changed'                 => array( 'start_fulfillment' => 'fulfillment_pending', 'start_change' => 'change_pending', 'start_cancellation' => 'cancellation_pending' ),
			'cancellation_pending'    => array( 'cancel' => 'cancelled', 'fail' => 'failed', 'uncertain' => 'reconciliation_required' ),
			'cancelled'               => array(),
			'failed'                  => array( 'reconcile' => 'reconciliation_required' ),
			'reconciliation_required' => array( 'reconcile_confirmed' => 'confirmed', 'reconcile_fulfilled' => 'fulfilled', 'reconcile_changed' => 'changed', 'reconcile_cancelled' => 'cancelled', 'reconcile_failed' => 'failed' ),
		),
		'settlement' => array(
			'not_applicable'     => array( 'record_click' => 'click_recorded', 'record_conversion' => 'conversion_reported' ),
			'click_recorded'     => array( 'record_conversion' => 'conversion_reported', 'reverse' => 'reversed' ),
			'conversion_reported'=> array( 'mark_eligible' => 'eligible', 'reverse' => 'reversed', 'dispute' => 'disputed' ),
			'eligible'           => array( 'mark_payable' => 'payable', 'reverse' => 'reversed', 'dispute' => 'disputed' ),
			'payable'            => array( 'mark_paid' => 'paid', 'reverse' => 'reversed', 'dispute' => 'disputed' ),
			'paid'               => array( 'reverse' => 'reversed', 'dispute' => 'disputed' ),
			'reversed'           => array(),
			'disputed'           => array( 'resolve_payable' => 'payable', 'resolve_reversed' => 'reversed' ),
		),
		'operation' => array(
			'queued'     => array( 'start' => 'started' ),
			'started'    => array( 'succeed' => 'succeeded', 'fail' => 'failed', 'timeout' => 'uncertain' ),
			'succeeded'  => array(),
			'failed'     => array(),
			'uncertain'  => array( 'reconcile' => 'reconciled' ),
			'reconciled' => array(),
		),
	);

	/**
	 * Return the next state or a fail-closed WP_Error.
	 *
	 * @return string|WP_Error
	 */
	public static function transition( $axis, $from, $command ) {
		$axis    = sanitize_key( (string) $axis );
		$from    = sanitize_key( (string) $from );
		$command = sanitize_key( (string) $command );
		if ( ! isset( self::TRANSITIONS[ $axis ], self::TRANSITIONS[ $axis ][ $from ], self::TRANSITIONS[ $axis ][ $from ][ $command ] ) ) {
			return new WP_Error( 'tra_vel_commerce_transition_invalid', 'This commerce transition is not valid from the current state.', array( 'status' => 409, 'axis' => $axis, 'from' => $from, 'command' => $command ) );
		}
		return self::TRANSITIONS[ $axis ][ $from ][ $command ];
	}

	/**
	 * Project a traveler-facing summary without collapsing the authoritative axes.
	 *
	 * @return string|WP_Error
	 */
	public static function project( $checkout, $payment, $fulfillments, $settlements ) {
		if ( ! in_array( $checkout, Tra_Vel_Commerce_Taxonomy::CHECKOUT_STATES, true ) || ! in_array( $payment, Tra_Vel_Commerce_Taxonomy::PAYMENT_STATES, true ) || ! self::valid_state_list( $fulfillments, Tra_Vel_Commerce_Taxonomy::FULFILLMENT_STATES ) || ! self::valid_state_list( $settlements, Tra_Vel_Commerce_Taxonomy::SETTLEMENT_STATES ) ) {
			return new WP_Error( 'tra_vel_commerce_state_projection_invalid', 'The commerce aggregate contains an unsupported state.', array( 'status' => 500 ) );
		}
		if ( in_array( 'reconciliation_required', $fulfillments, true ) || in_array( 'disputed', $settlements, true ) || in_array( 'failed', $fulfillments, true ) || 'failed' === $payment ) {
			return 'attention_required';
		}
		if ( $fulfillments && count( array_unique( $fulfillments ) ) === 1 && 'cancelled' === $fulfillments[0] && in_array( $payment, array( 'voided', 'refunded' ), true ) ) {
			return 'cancelled_and_resolved';
		}
		if ( $fulfillments && ! array_diff( $fulfillments, array( 'fulfilled' ) ) && 'captured' === $payment ) {
			return 'trip_confirmed';
		}
		if ( in_array( 'confirmation_pending', $fulfillments, true ) || in_array( 'confirmed', $fulfillments, true ) || in_array( 'fulfillment_pending', $fulfillments, true ) || in_array( 'changed', $fulfillments, true ) || in_array( 'hold_pending', $fulfillments, true ) || 'pending' === $payment || 'requires_action' === $payment ) {
			return 'processing';
		}
		if ( $fulfillments && ! array_diff( $fulfillments, array( 'held' ) ) ) {
			return 'inventory_held';
		}
		if ( 'ready' === $checkout ) {
			return 'ready_for_payment';
		}
		if ( 'expired' === $checkout ) {
			return 'quote_expired';
		}
		if ( 'abandoned' === $checkout ) {
			return 'abandoned';
		}
		return 'building_trip';
	}

	private static function valid_state_list( $states, $allowed ) {
		if ( ! is_array( $states ) || array_values( $states ) !== $states ) {
			return false;
		}
		foreach ( $states as $state ) {
			if ( ! in_array( $state, $allowed, true ) ) {
				return false;
			}
		}
		return true;
	}
}
