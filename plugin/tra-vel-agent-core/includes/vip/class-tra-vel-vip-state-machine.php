<?php
/**
 * Independent state machines for VIP service cases and side effects.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_VIP_State_Machine {
	const LIFECYCLE_TRANSITIONS = array(
		'received'          => array( 'verify' => 'verified', 'need_evidence' => 'evidence_needed', 'escalate' => 'escalated', 'safety_handoff' => 'safety_handoff' ),
		'verified'          => array( 'triage' => 'triaged', 'need_evidence' => 'evidence_needed', 'await_traveler' => 'traveler_pending', 'escalate' => 'escalated', 'safety_handoff' => 'safety_handoff' ),
		'triaged'           => array( 'build_plan' => 'plan_building', 'need_evidence' => 'evidence_needed', 'await_traveler' => 'traveler_pending', 'escalate' => 'escalated', 'safety_handoff' => 'safety_handoff' ),
		'plan_building'     => array( 'request_approval' => 'approval_needed', 'execute_reversible' => 'executing', 'await_supplier' => 'supplier_pending', 'need_evidence' => 'evidence_needed', 'await_traveler' => 'traveler_pending', 'escalate' => 'escalated', 'safety_handoff' => 'safety_handoff' ),
		'approval_needed'   => array( 'approve' => 'executing', 'await_traveler' => 'traveler_pending', 'need_evidence' => 'evidence_needed', 'escalate' => 'escalated', 'safety_handoff' => 'safety_handoff' ),
		'executing'         => array( 'await_supplier' => 'supplier_pending', 'mark_uncertain' => 'uncertain', 'monitor' => 'monitoring', 'resolve' => 'resolved', 'escalate' => 'escalated', 'safety_handoff' => 'safety_handoff', 'close_with_loss' => 'closed_with_loss' ),
		'supplier_pending'  => array( 'resume_execution' => 'executing', 'mark_uncertain' => 'uncertain', 'monitor' => 'monitoring', 'need_evidence' => 'evidence_needed', 'escalate' => 'escalated', 'safety_handoff' => 'safety_handoff', 'close_with_loss' => 'closed_with_loss' ),
		'uncertain'         => array( 'reconcile_for_execution' => 'executing', 'reconcile_for_monitoring' => 'monitoring', 'await_supplier' => 'supplier_pending', 'escalate' => 'escalated', 'safety_handoff' => 'safety_handoff', 'close_with_loss' => 'closed_with_loss' ),
		'monitoring'        => array( 'resume_execution' => 'executing', 'await_supplier' => 'supplier_pending', 'mark_uncertain' => 'uncertain', 'resolve' => 'resolved', 'need_evidence' => 'evidence_needed', 'await_traveler' => 'traveler_pending', 'escalate' => 'escalated', 'safety_handoff' => 'safety_handoff', 'close_with_loss' => 'closed_with_loss' ),
		'evidence_needed'   => array( 'evidence_received' => 'triaged', 'await_traveler' => 'traveler_pending', 'escalate' => 'escalated', 'safety_handoff' => 'safety_handoff', 'close_with_loss' => 'closed_with_loss' ),
		'traveler_pending'  => array( 'traveler_responded' => 'triaged', 'need_evidence' => 'evidence_needed', 'escalate' => 'escalated', 'safety_handoff' => 'safety_handoff', 'close_with_loss' => 'closed_with_loss' ),
		'escalated'         => array( 'accept_ownership' => 'triaged', 'build_plan' => 'plan_building', 'resume_execution' => 'executing', 'await_supplier' => 'supplier_pending', 'mark_uncertain' => 'uncertain', 'monitor' => 'monitoring', 'safety_handoff' => 'safety_handoff', 'close_with_loss' => 'closed_with_loss' ),
		'safety_handoff'    => array( 'monitor' => 'monitoring', 'escalate' => 'escalated', 'resolve' => 'resolved', 'close_with_loss' => 'closed_with_loss' ),
		'resolved'          => array( 'close' => 'closed', 'reopen' => 'reopened', 'monitor_tail' => 'monitoring' ),
		'closed'            => array( 'reopen' => 'reopened' ),
		'reopened'          => array( 'verify' => 'verified', 'triage' => 'triaged', 'escalate' => 'escalated', 'safety_handoff' => 'safety_handoff' ),
		'closed_with_loss'  => array( 'reopen' => 'reopened' ),
	);

	const OUTCOME_TRANSITIONS = array(
		'safety' => array(
			'unassessed' => array( 'identify_risk' => 'at_risk', 'verify_stable' => 'stable' ),
			'at_risk' => array( 'start_handoff' => 'handoff_in_progress', 'verify_stable' => 'stable', 'close_with_loss' => 'closed_with_loss' ),
			'handoff_in_progress' => array( 'confirm_handoff' => 'handed_off', 'handoff_failed' => 'at_risk' ),
			'handed_off' => array( 'verify_stable' => 'stable', 'risk_returns' => 'at_risk' ),
			'stable' => array( 'risk_returns' => 'at_risk', 'close_with_loss' => 'closed_with_loss' ),
			'closed_with_loss' => array( 'reassess' => 'at_risk' ),
		),
		'continuity' => array(
			'unassessed' => array( 'verify_healthy' => 'healthy', 'identify_risk' => 'at_risk', 'confirm_disruption' => 'disrupted' ),
			'healthy' => array( 'identify_risk' => 'at_risk', 'confirm_disruption' => 'disrupted' ),
			'at_risk' => array( 'verify_healthy' => 'healthy', 'confirm_disruption' => 'disrupted', 'start_recovery' => 'recovery_in_progress' ),
			'disrupted' => array( 'start_recovery' => 'recovery_in_progress', 'close_with_loss' => 'closed_with_loss' ),
			'recovery_in_progress' => array( 'restore' => 'restored', 'confirm_disruption' => 'disrupted', 'close_with_loss' => 'closed_with_loss' ),
			'restored' => array( 'confirm_disruption' => 'disrupted', 'identify_risk' => 'at_risk' ),
			'closed_with_loss' => array( 'start_recovery' => 'recovery_in_progress' ),
		),
		'supplier' => array(
			'not_started' => array( 'start' => 'pending' ),
			'pending' => array( 'succeed' => 'succeeded', 'fail' => 'failed', 'timeout' => 'uncertain' ),
			'succeeded' => array( 'reopen' => 'pending' ),
			'failed' => array( 'retry_authorized' => 'pending', 'require_reconciliation' => 'uncertain' ),
			'uncertain' => array( 'reconcile' => 'reconciled' ),
			'reconciled' => array( 'authoritative_success' => 'succeeded', 'authoritative_failure' => 'failed', 'still_pending' => 'pending' ),
		),
		'financial' => array(
			'not_started' => array( 'start' => 'pending' ),
			'pending' => array( 'balance' => 'balanced', 'require_attention' => 'attention_required', 'dispute' => 'disputed' ),
			'balanced' => array( 'reopen' => 'pending', 'dispute' => 'disputed' ),
			'attention_required' => array( 'resume' => 'pending', 'balance' => 'balanced', 'dispute' => 'disputed', 'close_with_loss' => 'closed_with_loss' ),
			'disputed' => array( 'resume' => 'pending', 'balance' => 'balanced', 'close_with_loss' => 'closed_with_loss' ),
			'closed_with_loss' => array( 'reopen' => 'pending' ),
		),
		'evidence' => array(
			'none' => array( 'request' => 'needed', 'restrict' => 'restricted', 'complete' => 'complete' ),
			'needed' => array( 'start_collecting' => 'collecting', 'restrict' => 'restricted', 'complete' => 'complete' ),
			'collecting' => array( 'complete' => 'complete', 'restrict' => 'restricted', 'request_more' => 'needed' ),
			'complete' => array( 'request_more' => 'needed', 'restrict' => 'restricted' ),
			'restricted' => array( 'request_more' => 'needed', 'complete' => 'complete' ),
		),
		'communication' => array(
			'not_started' => array( 'queue' => 'queued' ),
			'queued' => array( 'deliver' => 'delivered', 'fail' => 'failed', 'timeout' => 'uncertain' ),
			'delivered' => array( 'acknowledge' => 'acknowledged', 'queue_followup' => 'queued' ),
			'acknowledged' => array( 'queue_followup' => 'queued' ),
			'failed' => array( 'retry' => 'queued' ),
			'uncertain' => array( 'reconcile_delivered' => 'delivered', 'reconcile_failed' => 'failed' ),
		),
	);

	const OPERATION_TRANSITIONS = array(
		'queued'     => array( 'start' => 'started' ),
		'started'    => array( 'succeed' => 'succeeded', 'fail' => 'failed', 'timeout' => 'uncertain' ),
		'succeeded'  => array(),
		'failed'     => array(),
		'uncertain'  => array( 'reconcile' => 'reconciled' ),
		'reconciled' => array(),
	);

	/**
	 * Move the lifecycle without changing severity or outcome axes.
	 *
	 * @return string|WP_Error
	 */
	public static function lifecycle_transition( $from, $command ) {
		$from    = self::key( $from );
		$command = self::key( $command );
		if ( ! isset( self::LIFECYCLE_TRANSITIONS[ $from ][ $command ] ) ) {
			return self::error( 'lifecycle_transition_invalid', 'This VIP case lifecycle transition is not allowed.' );
		}
		return self::LIFECYCLE_TRANSITIONS[ $from ][ $command ];
	}

	/**
	 * Validate a severity change while proving lifecycle is independent.
	 *
	 * @return array|WP_Error
	 */
	public static function severity_change( $lifecycle, $from, $to ) {
		if ( ! in_array( $lifecycle, Tra_Vel_VIP_Taxonomy::LIFECYCLE_STATES, true ) || ! in_array( $from, Tra_Vel_VIP_Taxonomy::SEVERITIES, true ) || ! in_array( $to, Tra_Vel_VIP_Taxonomy::SEVERITIES, true ) ) {
			return self::error( 'severity_change_invalid', 'The VIP case severity change is invalid.' );
		}
		return array( 'lifecycle' => $lifecycle, 'severity' => $to );
	}

	/**
	 * Move one outcome axis without rewriting any sibling axis.
	 *
	 * @return string|WP_Error
	 */
	public static function outcome_transition( $axis, $from, $command ) {
		$axis    = self::key( $axis );
		$from    = self::key( $from );
		$command = self::key( $command );
		if ( ! isset( self::OUTCOME_TRANSITIONS[ $axis ][ $from ][ $command ] ) ) {
			return self::error( 'outcome_transition_invalid', 'This VIP outcome transition is not allowed.' );
		}
		return self::OUTCOME_TRANSITIONS[ $axis ][ $from ][ $command ];
	}

	/**
	 * Consequential attempts time out into uncertainty and require reconciliation.
	 *
	 * @return string|WP_Error
	 */
	public static function operation_transition( $from, $command ) {
		$from    = self::key( $from );
		$command = self::key( $command );
		if ( ! isset( self::OPERATION_TRANSITIONS[ $from ][ $command ] ) ) {
			return self::error( 'operation_transition_invalid', 'This VIP operation transition is not allowed.' );
		}
		return self::OPERATION_TRANSITIONS[ $from ][ $command ];
	}

	/**
	 * Produce a calm customer projection without collapsing authoritative state.
	 *
	 * @return string|WP_Error
	 */
	public static function customer_projection( $lifecycle, $severity, $outcomes ) {
		if ( ! in_array( $lifecycle, Tra_Vel_VIP_Taxonomy::LIFECYCLE_STATES, true ) || ! in_array( $severity, Tra_Vel_VIP_Taxonomy::SEVERITIES, true ) || ! is_array( $outcomes ) ) {
			return self::error( 'projection_invalid', 'The VIP case projection input is invalid.' );
		}
		foreach ( Tra_Vel_VIP_Taxonomy::OUTCOME_AXES as $axis => $states ) {
			if ( ! isset( $outcomes[ $axis ] ) || ! in_array( $outcomes[ $axis ], $states, true ) ) {
				return self::error( 'projection_invalid', 'The VIP case projection is missing an authoritative outcome axis.' );
			}
		}
		if ( 'P0' === $severity && ! in_array( $outcomes['safety'], array( 'stable', 'handed_off' ), true ) ) {
			return 'immediate_safety_help';
		}
		if ( in_array( $outcomes['supplier'], array( 'uncertain', 'failed' ), true ) || in_array( $outcomes['financial'], array( 'attention_required', 'disputed' ), true ) ) {
			return 'attention_needed';
		}
		if ( in_array( $lifecycle, array( 'approval_needed', 'traveler_pending', 'evidence_needed' ), true ) ) {
			return 'action_required';
		}
		if ( in_array( $lifecycle, array( 'executing', 'supplier_pending', 'uncertain', 'monitoring', 'escalated', 'safety_handoff' ), true ) ) {
			return 'recovery_underway';
		}
		if ( 'closed_with_loss' === $lifecycle || in_array( 'closed_with_loss', $outcomes, true ) ) {
			return 'resolved_with_loss';
		}
		if ( in_array( $lifecycle, array( 'resolved', 'closed' ), true ) && 'restored' === $outcomes['continuity'] && 'stable' === $outcomes['safety'] ) {
			return 'recovered';
		}
		return 'case_received';
	}

	private static function key( $value ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_vip_' . $suffix, $message, array( 'status' => 409 ) );
	}
}
