<?php
/**
 * Evidence-only state transitions for private commerce funds-flow snapshots.
 *
 * Applying an event updates a sandbox ledger snapshot. It does not invoke a
 * payment processor, supplier, payout rail, or settlement service.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Funds_Flow_State_Machine {
	/**
	 * Apply one payment observation to a validated snapshot.
	 *
	 * @param array  $record  Current private snapshot.
	 * @param string $command Payment event command.
	 * @param array  $event   Closed event payload.
	 * @param int    $now     Positive UTC epoch strictly after the current update.
	 * @return array|WP_Error
	 */
	public static function apply_payment_event( $record, $command, $event, $now ) {
		$current = Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $record, $now );
		if ( is_wp_error( $current ) ) {
			return self::error( 'current_invalid', 'A valid current funds-flow snapshot is required before applying a payment event.' );
		}
		if ( ! self::event_clock( $current, $now ) ) {
			return self::error( 'event_clock_invalid', 'A financial event must occur strictly after the current snapshot update.' );
		}
		if ( ! self::exact_object( $event, array( 'amount_minor', 'processor_payment_ref', 'evidence_digest' ) ) || ! self::money( $event['amount_minor'] ) || ! self::nullable_ref( $event['processor_payment_ref'], 'paytxn' ) || ! self::digest( $event['evidence_digest'] ) ) {
			return self::error( 'payment_event_invalid', 'Payment events require a closed integer amount, opaque private payment reference and evidence digest.' );
		}
		$command = is_string( $command ) ? strtolower( $command ) : '';
		$state   = $current['payment']['state'];
		$next    = $current;
		$payment = $next['payment'];
		$amount  = $event['amount_minor'];
		$ref     = $event['processor_payment_ref'];

		if ( 'request_authorization' === $command && 'not_started' === $state ) {
			if ( 0 !== $amount || null === $ref ) {
				return self::error( 'authorization_request_invalid', 'Authorization request evidence cannot claim an amount and requires an opaque payment locator.' );
			}
			$payment['state'] = 'authorization_pending';
		} elseif ( 'authorize' === $command && in_array( $state, array( 'not_started', 'authorization_pending' ), true ) ) {
			if ( $amount < 1 || $amount > $current['pricing']['customer_total_minor'] || null === $ref || ( null !== $payment['processor_payment_ref'] && $ref !== $payment['processor_payment_ref'] ) ) {
				return self::error( 'authorization_invalid', 'Authorization must be positive, item-bounded and use the same private payment identity.' );
			}
			$payment['state']                   = 'authorized';
			$payment['authorized_amount_minor'] = $amount;
		} elseif ( 'capture' === $command && in_array( $state, array( 'authorized', 'capture_pending', 'partially_captured' ), true ) ) {
			$new_capture = $payment['captured_amount_minor'] + $amount;
			if ( $amount < 1 || null === $ref || $ref !== $payment['processor_payment_ref'] || $new_capture > $payment['authorized_amount_minor'] ) {
				return self::error( 'capture_invalid', 'Capture must be positive, remain within authorization and retain the private payment identity.' );
			}
			$payment['captured_amount_minor'] = $new_capture;
			$payment['state'] = $new_capture === $current['pricing']['customer_total_minor'] ? 'captured' : 'partially_captured';
		} elseif ( 'refund' === $command && in_array( $state, array( 'captured', 'partially_captured', 'partially_refunded', 'disputed', 'charged_back' ), true ) ) {
			$new_refund = $payment['refunded_amount_minor'] + $amount;
			if ( $amount < 1 || null === $ref || $ref !== $payment['processor_payment_ref'] || $new_refund + $payment['disputed_amount_minor'] > $payment['captured_amount_minor'] || $new_refund + $payment['charged_back_amount_minor'] > $payment['captured_amount_minor'] ) {
				return self::error( 'refund_invalid', 'Refund must be positive, remain within captured funds and retain the private payment identity.' );
			}
			$payment['refunded_amount_minor'] = $new_refund;
			if ( 'charged_back' === $state ) {
				$payment['state'] = 'charged_back';
			} elseif ( 'disputed' === $state ) {
				$payment['state'] = 'disputed';
			} else {
				$payment['state'] = $new_refund === $payment['captured_amount_minor'] ? 'refunded' : 'partially_refunded';
			}
		} elseif ( 'open_dispute' === $command && in_array( $state, array( 'captured', 'partially_captured', 'partially_refunded' ), true ) ) {
			$available = $payment['captured_amount_minor'] - $payment['refunded_amount_minor'];
			if ( $amount < 1 || $amount > $available || null === $ref || $ref !== $payment['processor_payment_ref'] ) {
				return self::error( 'dispute_invalid', 'A dispute must be positive, bounded by unrefunded capture and retain the private payment identity.' );
			}
			$payment['disputed_amount_minor'] = $amount;
			$payment['state'] = 'disputed';
		} elseif ( 'record_chargeback' === $command && in_array( $state, array( 'disputed', 'charged_back' ), true ) ) {
			$new_chargeback = $payment['charged_back_amount_minor'] + $amount;
			if ( $amount < 1 || $new_chargeback > $payment['disputed_amount_minor'] || null === $ref || $ref !== $payment['processor_payment_ref'] ) {
				return self::error( 'chargeback_invalid', 'Chargeback must be positive, dispute-bounded and retain the private payment identity.' );
			}
			$payment['charged_back_amount_minor'] = $new_chargeback;
			$payment['state'] = 'charged_back';
			$next['liabilities']['chargeback_liability_minor'] = $new_chargeback;
		} elseif ( 'fail' === $command && in_array( $state, array( 'not_started', 'authorization_pending' ), true ) ) {
			if ( 0 !== $amount || ( null !== $payment['processor_payment_ref'] && $ref !== $payment['processor_payment_ref'] ) ) {
				return self::error( 'payment_failure_invalid', 'A failed pre-capture observation cannot add financial amounts or change payment identity.' );
			}
			$payment['state'] = 'payment_failed';
		} elseif ( 'mark_uncertain' === $command && in_array( $state, array( 'authorization_pending', 'authorized', 'capture_pending' ), true ) ) {
			if ( 0 !== $amount || null === $ref || ( null !== $payment['processor_payment_ref'] && $ref !== $payment['processor_payment_ref'] ) ) {
				return self::error( 'payment_uncertain_invalid', 'An uncertain observation preserves amounts and the exact private payment identity.' );
			}
			$payment['state'] = 'uncertain';
		} else {
			return self::error( 'payment_transition_invalid', 'This payment event is not valid from the current state.' );
		}

		$payment['processor_payment_ref'] = null === $payment['processor_payment_ref'] ? $ref : $payment['processor_payment_ref'];
		$payment['latest_event_digest']   = $event['evidence_digest'];
		$payment['updated_at']             = gmdate( 'Y-m-d\TH:i:s\Z', $now );
		$next['payment']                   = $payment;
		return self::successor( $current, $next, $now );
	}

	/**
	 * Apply one settlement observation to a validated snapshot.
	 *
	 * @param array  $record  Current snapshot.
	 * @param string $command Settlement command: accrue, settle, or dispute.
	 * @param array  $event   Closed settlement delta.
	 * @param int    $now     Positive UTC epoch.
	 * @return array|WP_Error
	 */
	public static function apply_settlement_event( $record, $command, $event, $now ) {
		$current = Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $record, $now );
		if ( is_wp_error( $current ) ) {
			return self::error( 'current_invalid', 'A valid current funds-flow snapshot is required before applying settlement evidence.' );
		}
		if ( ! self::event_clock( $current, $now ) ) {
			return self::error( 'event_clock_invalid', 'A settlement event must occur strictly after the current snapshot update.' );
		}
		$keys = array( 'supplier_amount_minor', 'commission_amount_minor', 'recovery_amount_minor', 'evidence_digest', 'due_at' );
		if ( ! self::exact_object( $event, $keys ) || ! self::money( $event['supplier_amount_minor'] ) || ! self::money( $event['commission_amount_minor'] ) || ! self::money( $event['recovery_amount_minor'] ) || ! self::digest( $event['evidence_digest'] ) || ! self::nullable_datetime( $event['due_at'] ) ) {
			return self::error( 'settlement_event_invalid', 'Settlement events require closed integer deltas, an evidence digest and an optional UTC due date.' );
		}
		$command    = is_string( $command ) ? strtolower( $command ) : '';
		$state      = $current['settlement']['state'];
		$settlement = $current['settlement'];
		$next       = $current;
		$total_delta = $event['supplier_amount_minor'] + $event['commission_amount_minor'] + $event['recovery_amount_minor'];

		if ( 'accrue' === $command && 'not_started' === $state ) {
			$due_timestamp = self::timestamp( $event['due_at'] );
			if ( 0 === $total_delta || null === $due_timestamp || $due_timestamp < $now ) {
				return self::error( 'settlement_accrual_invalid', 'An accrual needs a positive obligation and a due time at or after the evidence clock.' );
			}
			$settlement['supplier_due_minor']            = $event['supplier_amount_minor'];
			$settlement['commission_due_minor']          = $event['commission_amount_minor'];
			$settlement['chargeback_recovery_due_minor'] = $event['recovery_amount_minor'];
			$settlement['state']                         = 'accrued';
			$settlement['due_at']                        = $event['due_at'];
		} elseif ( 'settle' === $command && in_array( $state, array( 'accrued', 'partially_settled' ), true ) ) {
			if ( 0 === $total_delta || null !== $event['due_at'] ) {
				return self::error( 'settlement_delta_invalid', 'A settlement delta must be positive and cannot rewrite the immutable accrual due date.' );
			}
			$new_supplier_paid = $settlement['supplier_paid_minor'] + $event['supplier_amount_minor'];
			$new_commission_received = $settlement['commission_received_minor'] + $event['commission_amount_minor'];
			$new_recovered = $settlement['chargeback_recovered_minor'] + $event['recovery_amount_minor'];
			if ( $new_supplier_paid > $settlement['supplier_due_minor'] || $new_commission_received > $settlement['commission_due_minor'] || $new_recovered > $settlement['chargeback_recovery_due_minor'] ) {
				return self::error( 'settlement_overpayment', 'A settlement observation cannot exceed any recognized obligation.' );
			}
			$settlement['supplier_paid_minor']       = $new_supplier_paid;
			$settlement['commission_received_minor'] = $new_commission_received;
			$settlement['chargeback_recovered_minor'] = $new_recovered;
			$settlement['state'] = $new_supplier_paid === $settlement['supplier_due_minor'] && $new_commission_received === $settlement['commission_due_minor'] && $new_recovered === $settlement['chargeback_recovery_due_minor'] ? 'settled' : 'partially_settled';
		} elseif ( 'dispute' === $command && in_array( $state, array( 'accrued', 'partially_settled', 'settled' ), true ) ) {
			if ( 0 !== $total_delta || null !== $event['due_at'] || ! in_array( $current['payment']['state'], array( 'disputed', 'charged_back' ), true ) ) {
				return self::error( 'settlement_dispute_invalid', 'Settlement can enter dispute only from payment dispute evidence, without silently changing amounts or due date.' );
			}
			$settlement['state'] = 'disputed';
		} else {
			return self::error( 'settlement_transition_invalid', 'This settlement event is not valid from the current state.' );
		}

		$settlement['latest_reconciliation_digest'] = $event['evidence_digest'];
		$settlement['updated_at'] = gmdate( 'Y-m-d\TH:i:s\Z', $now );
		$next['settlement'] = $settlement;
		$next['liabilities']['supplier_payable_outstanding_minor'] = $settlement['supplier_due_minor'] - $settlement['supplier_paid_minor'];
		$next['liabilities']['commission_receivable_outstanding_minor'] = $settlement['commission_due_minor'] - $settlement['commission_received_minor'];
		return self::successor( $current, $next, $now );
	}

	private static function successor( $current, $candidate, $now ) {
		$candidate['version']                  = $current['version'] + 1;
		$candidate['last_event_sequence']      = $current['last_event_sequence'] + 1;
		$candidate['previous_snapshot_digest'] = $current['snapshot_digest'];
		$candidate['updated_at']               = gmdate( 'Y-m-d\TH:i:s\Z', $now );
		$candidate = Tra_Vel_Commerce_Funds_Flow_Policy::seal_snapshot( $candidate );
		$validated = Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $candidate, $now );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$successor = Tra_Vel_Commerce_Funds_Flow_Policy::assert_successor( $current, $validated, $now );
		return is_wp_error( $successor ) ? $successor : $validated;
	}

	private static function event_clock( $record, $now ) {
		return is_int( $now ) && $now > 0 && $now > self::timestamp( $record['updated_at'] );
	}

	private static function money( $value ) {
		return is_int( $value ) && $value >= 0 && $value <= Tra_Vel_Commerce_Funds_Flow_Policy::MAX_MONEY_MINOR;
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function nullable_ref( $value, $prefix ) {
		return null === $value || ( is_string( $value ) && 1 === preg_match( '/^' . preg_quote( $prefix, '/' ) . '_[A-Za-z0-9][A-Za-z0-9_-]{15,95}$/', $value ) );
	}

	private static function nullable_datetime( $value ) {
		return null === $value || null !== self::timestamp( $value );
	}

	private static function timestamp( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/', $value, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59 ) {
			return null;
		}
		try {
			return ( new DateTimeImmutable( $value ) )->getTimestamp();
		} catch ( Exception $error ) {
			return null;
		}
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_commerce_funds_flow_' . $suffix, $message, array( 'status' => 409 ) );
	}
}
