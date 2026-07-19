<?php
/**
 * Supplier onboarding, circuit-breaker, revision, operation, and settlement states.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Supplier_Operations_State_Machine {
	const TRANSITIONS = array(
		'onboarding' => array(
			'draft'                   => array( 'submit' => 'commercial_review', 'disable' => 'disabled' ),
			'commercial_review'       => array( 'approve' => 'security_review', 'reject' => 'disabled' ),
			'security_review'         => array( 'approve' => 'technical_certification', 'reject' => 'disabled' ),
			'technical_certification' => array( 'certify' => 'operations_review', 'fail' => 'suspended' ),
			'operations_review'       => array( 'approve_sandbox' => 'sandbox_ready', 'fail' => 'suspended' ),
			'sandbox_ready'           => array( 'activate_sandbox' => 'sandbox_active', 'request_live' => 'live_review', 'suspend' => 'suspended', 'disable' => 'disabled' ),
			'sandbox_active'          => array( 'request_live' => 'live_review', 'suspend' => 'suspended', 'disable' => 'disabled' ),
			'live_review'             => array( 'approve_live' => 'live_ready', 'return_sandbox' => 'sandbox_active', 'reject' => 'suspended' ),
			'live_ready'              => array( 'activate_live' => 'live_active', 'suspend' => 'suspended', 'disable' => 'disabled' ),
			'live_active'             => array( 'suspend' => 'suspended', 'begin_migration' => 'migrating', 'disable' => 'disabled' ),
			'suspended'               => array( 'resume_sandbox' => 'sandbox_ready', 'resume_live' => 'live_ready', 'begin_migration' => 'migrating', 'disable' => 'disabled' ),
			'migrating'               => array( 'complete_migration' => 'retired', 'rollback_migration' => 'live_active', 'disable' => 'disabled' ),
			'retired'                 => array(),
			'disabled'                => array( 'reopen' => 'draft' ),
		),
		'health' => array(
			'healthy'   => array( 'degrade' => 'degraded', 'trip' => 'open', 'mark_offline' => 'offline', 'disable' => 'disabled' ),
			'degraded'  => array( 'recover' => 'healthy', 'trip' => 'open', 'mark_offline' => 'offline', 'disable' => 'disabled' ),
			'open'      => array( 'cooldown_elapsed' => 'half_open', 'mark_offline' => 'offline', 'disable' => 'disabled' ),
			'half_open' => array( 'probe_success' => 'healthy', 'probe_failure' => 'open', 'mark_offline' => 'offline', 'disable' => 'disabled' ),
			'offline'   => array( 'probe' => 'half_open', 'disable' => 'disabled' ),
			'disabled'  => array( 'release' => 'offline' ),
		),
		'revision' => array(
			'draft'       => array( 'certify' => 'certified', 'retire' => 'retired' ),
			'certified'   => array( 'activate' => 'active', 'retire' => 'retired' ),
			'active'      => array( 'supersede' => 'superseded', 'rollback' => 'rolled_back', 'retire' => 'retired' ),
			'superseded'  => array( 'restore' => 'active', 'retire' => 'retired' ),
			'rolled_back' => array( 'retire' => 'retired' ),
			'retired'     => array(),
		),
		'operation' => array(
			'queued'     => array( 'start' => 'started' ),
			'started'    => array( 'succeed' => 'succeeded', 'fail' => 'failed', 'timeout' => 'uncertain' ),
			'succeeded'  => array(),
			'failed'     => array(),
			'uncertain'  => array( 'reconcile_success' => 'reconciled', 'reconcile_failure' => 'reconciled' ),
			'reconciled' => array(),
		),
		'settlement' => array(
			'unreported' => array( 'report' => 'reported' ),
			'reported'   => array( 'mark_eligible' => 'eligible', 'reverse' => 'reversed', 'dispute' => 'disputed' ),
			'eligible'   => array( 'mark_payable' => 'payable', 'reverse' => 'reversed', 'dispute' => 'disputed' ),
			'payable'    => array( 'mark_paid' => 'paid', 'reverse' => 'reversed', 'dispute' => 'disputed' ),
			'paid'       => array( 'reverse' => 'reversed', 'dispute' => 'disputed' ),
			'reversed'   => array( 'dispute' => 'disputed' ),
			'disputed'   => array( 'resolve_payable' => 'payable', 'resolve_paid' => 'paid', 'resolve_reversed' => 'reversed' ),
		),
	);

	/**
	 * Return the next state or fail closed.
	 *
	 * @return string|WP_Error
	 */
	public static function transition( $axis, $from, $command ) {
		$axis    = sanitize_key( (string) $axis );
		$from    = sanitize_key( (string) $from );
		$command = sanitize_key( (string) $command );
		if ( ! isset( self::TRANSITIONS[ $axis ], self::TRANSITIONS[ $axis ][ $from ], self::TRANSITIONS[ $axis ][ $from ][ $command ] ) ) {
			return new WP_Error(
				'tra_vel_supplier_operations_transition_invalid',
				'This supplier operation transition is not valid from the current state.',
				array( 'status' => 409, 'axis' => $axis, 'from' => $from, 'command' => $command )
			);
		}
		return self::TRANSITIONS[ $axis ][ $from ][ $command ];
	}

	/**
	 * Prove that a supplier configuration is a new immutable revision rather
	 * than an in-place edit of the active configuration.
	 *
	 * @return true|WP_Error
	 */
	public static function assert_profile_successor( $current, $candidate, $now = null ) {
		$current_valid   = Tra_Vel_Supplier_Operations_Policy::supplier_profile( $current, $now );
		$candidate_valid = Tra_Vel_Supplier_Operations_Policy::supplier_profile( $candidate, $now );
		if ( is_wp_error( $current_valid ) || is_wp_error( $candidate_valid ) ) {
			return self::error( 'profile_successor_record_invalid', 'Both supplier configuration revisions must validate at the comparison clock.' );
		}
		$current_digest = $current_valid['revision_control']['content_digest'];
		if (
			$candidate_valid['supplier_id'] !== $current_valid['supplier_id'] ||
			$candidate_valid['environment'] !== $current_valid['environment'] ||
			$candidate_valid['revision_id'] === $current_valid['revision_id'] ||
			$candidate_valid['revision_number'] !== $current_valid['revision_number'] + 1 ||
			$candidate_valid['previous_revision_digest'] !== $current_digest ||
			$candidate_valid['revision_control']['supersedes_revision_digest'] !== $current_digest ||
			$candidate_valid['created_at'] <= $current_valid['created_at'] ||
			$candidate_valid['effective_at'] < $current_valid['effective_at']
		) {
			return self::error( 'profile_successor_invalid', 'A supplier profile successor must increment once and bind the exact prior immutable configuration digest.' );
		}
		return true;
	}

	/**
	 * Ensure an inventory revision is a new immutable successor, not an edit.
	 *
	 * @return true|WP_Error
	 */
	public static function assert_revision_successor( $current, $candidate, $now = null ) {
		if ( ! is_array( $current ) || ! is_array( $candidate ) || ! isset( $current['supplier_id'], $current['vertical'], $current['revision_number'], $candidate['supplier_id'], $candidate['vertical'], $candidate['revision_number'], $candidate['previous_revision_digest'] ) ) {
			return self::error( 'revision_successor_shape_invalid', 'Revision successor comparison requires two closed inventory records.' );
		}
		$current_valid = Tra_Vel_Supplier_Operations_Policy::inventory_revision( $current, $now );
		$candidate_valid = Tra_Vel_Supplier_Operations_Policy::inventory_revision( $candidate, $now );
		if ( is_wp_error( $current_valid ) || is_wp_error( $candidate_valid ) ) {
			return self::error( 'revision_successor_record_invalid', 'Both inventory revisions must satisfy the closed policy before comparison.' );
		}
		$current_digest = Tra_Vel_Supplier_Operations_Policy::canonical_digest( $current );
		if ( $candidate['supplier_id'] !== $current['supplier_id'] || $candidate['vertical'] !== $current['vertical'] || $candidate['environment'] !== $current['environment'] || ! in_array( $current['state'], array( 'certified', 'active', 'superseded' ), true ) || ! in_array( $candidate['state'], array( 'draft', 'certified' ), true ) || $candidate['revision_number'] !== $current['revision_number'] + 1 || $candidate['previous_revision_digest'] !== $current_digest ) {
			return self::error( 'revision_successor_invalid', 'A supplier inventory successor must increment once and bind the exact prior immutable digest.' );
		}
		return true;
	}

	/**
	 * Gate execution using onboarding, readiness, health, kill-switch, and certification truth.
	 *
	 * @return true|WP_Error
	 */
	public static function can_execute( $profile, $vertical, $capability, $now = null ) {
		if ( ! is_array( $profile ) || ! isset( $profile['environment'], $profile['lifecycle_status'], $profile['readiness'], $profile['health'], $profile['kill_switch'], $profile['capability_claims'] ) ) {
			return self::error( 'execution_profile_invalid', 'A validated supplier profile is required before execution.' );
		}
		$validated = Tra_Vel_Supplier_Operations_Policy::supplier_profile( $profile, $now );
		if ( is_wp_error( $validated ) ) {
			return self::error( 'execution_profile_invalid', 'The supplier profile is stale, future-effective, tampered, or otherwise invalid at the execution clock.' );
		}
		$profile = $validated;
		$vertical   = Tra_Vel_Supplier_Operations_Taxonomy::token( $vertical, Tra_Vel_Supplier_Operations_Taxonomy::VERTICALS );
		$capability = Tra_Vel_Supplier_Operations_Taxonomy::token( $capability, Tra_Vel_Supplier_Operations_Taxonomy::CAPABILITIES );
		if ( '' === $vertical || '' === $capability ) {
			return self::error( 'execution_scope_invalid', 'The requested supplier execution scope is unsupported.' );
		}
		$expected_lifecycle = 'live' === $profile['environment'] ? 'live_active' : 'sandbox_active';
		$expected_readiness = 'live' === $profile['environment'] ? 'live_ready' : 'sandbox_ready';
		if ( $expected_lifecycle !== $profile['lifecycle_status'] || $expected_readiness !== $profile['readiness']['decision'] ) {
			return self::error( 'execution_not_active', 'The supplier is not active and ready in this environment.' );
		}
		if ( ! in_array( $profile['health']['state'], array( 'healthy', 'degraded' ), true ) ) {
			return self::error( 'execution_circuit_open', 'The supplier circuit breaker does not permit execution.' );
		}
		$degraded_safe = array( 'search', 'revalidate', 'webhook', 'reconcile', 'settlement_reconcile' );
		if ( 'degraded' === $profile['health']['state'] && ! in_array( $capability, $degraded_safe, true ) ) {
			return self::error( 'execution_degraded_mutation_blocked', 'A degraded supplier may be observed and reconciled, but consequential mutations require healthy runtime evidence.' );
		}
		if ( 'engaged' === $profile['kill_switch']['state'] && in_array( $capability, $profile['kill_switch']['blocked_capabilities'], true ) ) {
			return self::error( 'execution_killed', 'The supplier capability is disabled by the kill switch.' );
		}
		$required_certification = 'live' === $profile['environment'] ? 'live_certified' : array( 'sandbox_certified', 'live_certified' );
		foreach ( $profile['capability_claims'] as $claim ) {
			if ( $vertical !== $claim['vertical'] || $capability !== $claim['capability'] ) {
				continue;
			}
			$certified = is_array( $required_certification ) ? in_array( $claim['certification_status'], $required_certification, true ) : $required_certification === $claim['certification_status'];
			return $certified ? true : self::error( 'execution_not_certified', 'The supplier capability is not certified for this environment.' );
		}
		return self::error( 'execution_capability_missing', 'The supplier did not declare the requested vertical capability.' );
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_supplier_operations_' . $suffix, $message, array( 'status' => 409 ) );
	}
}
