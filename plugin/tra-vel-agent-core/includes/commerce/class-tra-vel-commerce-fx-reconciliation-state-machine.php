<?php
/**
 * Evidence-only state transitions for private sandbox FX reconciliation.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Fx_Reconciliation_State_Machine {
	/**
	 * Append one closed servicing observation or replay an exact duplicate.
	 *
	 * No event in this class dispatches a refund, settlement, supplier request,
	 * processor operation, or rate lookup. It records evidence only.
	 *
	 * @param array $current Valid current snapshot.
	 * @param array $command Exact event command without derived target fields.
	 * @param int   $now Positive injected UTC epoch.
	 * @return array|WP_Error
	 */
	public static function apply_event( $current, $command, $now ) {
		if ( ! class_exists( 'Tra_Vel_Commerce_Fx_Reconciliation_Policy' ) ) {
			return self::error( 'dependency_unavailable', 'The closed FX reconciliation policy must be loaded first.' );
		}
		$validated = Tra_Vel_Commerce_Fx_Reconciliation_Policy::validate_snapshot( $current, $now );
		if ( is_wp_error( $validated ) ) {
			return self::error( 'current_invalid', 'A valid current FX snapshot is required before recording evidence.' );
		}
		$keys = array(
			'sequence', 'event_type', 'idempotency_key_digest', 'evidence_digest',
			'occurred_at', 'source_amount_minor', 'target_amount_minor',
		);
		if ( ! is_array( $command ) || array_keys( $command ) !== $keys ) {
			return self::error( 'command_shape_invalid', 'The FX servicing command is not the exact closed event shape.' );
		}

		foreach ( $current['event_history'] as $existing ) {
			if ( $existing['idempotency_key_digest'] !== $command['idempotency_key_digest'] ) {
				continue;
			}
			$same = $existing['sequence'] === $command['sequence'] &&
				$existing['event_type'] === $command['event_type'] &&
				$existing['evidence_digest'] === $command['evidence_digest'] &&
				$existing['occurred_at'] === $command['occurred_at'] &&
				$existing['source_amount_minor'] === $command['source_amount_minor'] &&
				$existing['target_amount_minor'] === $command['target_amount_minor'];
			return $same
				? $current
				: self::error( 'idempotency_conflict', 'An idempotency digest cannot be reused for different FX evidence.' );
		}

		if ( $command['sequence'] !== $current['last_event_sequence'] + 1 ) {
			return self::error( 'event_out_of_order', 'FX evidence must use the one exact next sequence.' );
		}
		if ( count( $current['event_history'] ) >= Tra_Vel_Commerce_Fx_Reconciliation_Policy::MAX_EVENTS ) {
			return self::error( 'event_limit_reached', 'The immutable FX evidence history reached its closed retention limit.' );
		}

		$event = $command;
		$event['effective_target_delta_minor'] = 0;
		$event['rounding_adjustment_minor'] = 0;
		$history = $current['event_history'];
		$history[] = $event;
		$projection = Tra_Vel_Commerce_Fx_Reconciliation_Policy::project_event_history(
			$history,
			$current['ledger'],
			$current['source_rate'],
			$current['locked_quote'],
			strtotime( $current['created_at'] ),
			$now
		);
		if ( is_wp_error( $projection ) ) {
			return $projection;
		}

		$next = $current;
		$next['version'] = $current['version'] + 1;
		$next['previous_snapshot_digest'] = $current['snapshot_digest'];
		$next['event_history'] = $projection['history'];
		$next['servicing'] = $projection['servicing'];
		$next['liabilities'] = Tra_Vel_Commerce_Fx_Reconciliation_Policy::liabilities_for(
			$current['ledger'],
			$projection['servicing'],
			$current['locked_quote']
		);
		$next['last_event_sequence'] = $command['sequence'];
		$next['updated_at'] = $command['occurred_at'];
		$next = Tra_Vel_Commerce_Fx_Reconciliation_Policy::seal_snapshot( $next );
		$successor = Tra_Vel_Commerce_Fx_Reconciliation_Policy::assert_successor( $current, $next, $now );
		return is_wp_error( $successor ) ? $successor : $next;
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_commerce_fx_' . $suffix, $message, array( 'status' => 409 ) );
	}
}
