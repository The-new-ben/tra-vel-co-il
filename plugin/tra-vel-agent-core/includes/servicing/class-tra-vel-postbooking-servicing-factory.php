<?php
/**
 * Deterministic zero-dispatch post-booking servicing plan factory.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Postbooking_Servicing_Factory {
	/**
	 * Build an immutable review plan from one exact current snapshot.
	 *
	 * @param array $snapshot         Complete immutable source snapshot.
	 * @param array $expected_binding Caller-observed current identity/digests.
	 * @param int   $now              Positive injected UTC epoch.
	 * @return array|WP_Error
	 */
	public static function create_plan( $snapshot, $expected_binding, $now ) {
		$snapshot = Tra_Vel_Postbooking_Servicing_Policy::validate_snapshot( $snapshot, $now );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		$binding = Tra_Vel_Postbooking_Servicing_Policy::expected_binding( $snapshot );
		if ( ! self::exact_object( $expected_binding, array_keys( $binding ) ) || self::canonical_digest( $expected_binding ) !== self::canonical_digest( $binding ) ) {
			return self::error( 'current_binding_mismatch', 'The factory requires exact current owner, trip, supplier-order, commerce-order and servicing snapshot digests.', 409 );
		}
		$projection = Tra_Vel_Postbooking_Servicing_Policy::project_snapshot( $snapshot, $now );
		if ( is_wp_error( $projection ) ) {
			return $projection;
		}
		$plan = array(
			'contract_version'             => Tra_Vel_Postbooking_Servicing_Taxonomy::CONTRACT_VERSION,
			'environment'                  => 'sandbox',
			'data_mode'                    => 'synthetic_demo',
			'plan_ref'                     => 'tv_servicing_plan_' . substr( self::canonical_digest( $binding ), 0, 24 ),
			'plan_digest'                  => str_repeat( '0', 64 ),
			'input_binding'                => $binding,
			'change_class'                 => $projection['change_class'],
			'plan_state'                   => $projection['plan_state'],
			'scope_resolution'             => $projection['scope_resolution'],
			'truth_checks'                 => $projection['truth_checks'],
			'action_queue'                 => $projection['action_queue'],
			'financial_handling'           => $projection['financial_handling'],
			'state_axes'                   => $projection['state_axes'],
			'lodging_reconciliation'       => $projection['lodging_reconciliation'],
			'communication_reconciliation' => $projection['communication_reconciliation'],
			'required_approvals'           => $projection['required_approvals'],
			'evaluated_at'                 => gmdate( 'Y-m-d\TH:i:s\Z', $now ),
			'boundary'                     => array(
				'server_only'                  => true,
				'public_serialization_allowed' => false,
				'planning_only'                => true,
				'creates_order'                => false,
				'issues_ticket_or_emd'          => false,
				'changes_supplier_booking'      => false,
				'changes_inventory'             => false,
				'creates_payment'               => false,
				'creates_refund'                => false,
				'creates_settlement'            => false,
				'supplier_dispatched'           => false,
				'processor_called'              => false,
				'message_sent'                  => false,
			),
		);
		$plan = Tra_Vel_Postbooking_Servicing_Policy::seal_plan( $plan );
		return Tra_Vel_Postbooking_Servicing_Policy::validate_plan( $plan, $snapshot, $expected_binding, $now );
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function canonical_digest( $value ) {
		$encoded = wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
		if ( ! $is_list ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::canonicalize( $child );
		}
		return $value;
	}

	private static function error( $suffix, $message, $status = 400 ) {
		return new WP_Error( 'tra_vel_postbooking_servicing_factory_' . $suffix, $message, array( 'status' => $status ) );
	}
}
