<?php
/**
 * Deterministic factory for the private cross-ledger currency bridge.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Factory {
	/**
	 * Materialize one immutable bridge from exact current snapshot digests.
	 *
	 * @param array $funds_flow       Complete funds-flow snapshot.
	 * @param array $fx_record        Complete FX reconciliation snapshot.
	 * @param array $expected_binding Caller-observed current identity and digests.
	 * @param int   $now              Positive injected UTC epoch.
	 * @return array|WP_Error
	 */
	public static function create_snapshot( $funds_flow, $fx_record, $expected_binding, $now ) {
		$binding_keys = array(
			'owner_scope_digest', 'order_ref', 'order_version', 'order_digest',
			'order_item_ref', 'offer_digest', 'routing_binding_digest',
			'funds_flow_ref', 'funds_flow_binding_digest', 'funds_flow_snapshot_digest',
			'fx_reconciliation_ref', 'fx_snapshot_digest',
		);
		if ( ! self::exact_object( $expected_binding, $binding_keys ) || ! is_int( $now ) || $now < 1 ) {
			return self::error( 'context_invalid', 'The factory requires the exact current owner, order, item, routing, funds-flow and FX binding plus a positive injected clock.' );
		}

		$projection = Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::project_pair( $funds_flow, $fx_record, $now );
		if ( is_wp_error( $projection ) ) {
			return $projection;
		}
		if ( ! self::same_object_values( $expected_binding, $projection['binding'], $binding_keys ) ) {
			return self::error( 'current_binding_mismatch', 'The supplied current snapshot digests or cross-ledger identity no longer match the validated records.', 409 );
		}

		$record = array(
			'contract_version'             => Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::CONTRACT_VERSION,
			'environment'                  => 'sandbox',
			'bridge_ref'                   => '',
			'bridge_binding_digest'        => '',
			'snapshot_digest'              => '',
			'binding'                      => $projection['binding'],
			'currency_bridge'              => $projection['currency_bridge'],
			'source_customer_funds'        => $projection['source_customer_funds'],
			'source_supplier_accrual'      => $projection['source_supplier_accrual'],
			'target_supplier_settlement'   => $projection['target_supplier_settlement'],
			'overall_status'               => $projection['overall_status'],
			'evaluated_at'                 => gmdate( 'Y-m-d\TH:i:s\Z', $now ),
			'sandbox_truth'                => array(
				'deterministic_projection' => true,
				'real_rate_provider_call'  => false,
				'real_processor_call'      => false,
				'real_customer_charge'     => false,
				'real_supplier_payment'    => false,
				'real_settlement'          => false,
				'external_authority'       => false,
			),
			'data_boundary'                => array(
				'server_only'                  => true,
				'public_serialization_allowed' => false,
				'raw_credentials_stored'       => false,
				'raw_payment_data_stored'      => false,
				'personal_data_stored'         => false,
			),
		);
		$record = Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::seal_snapshot( $record );
		return Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::validate_snapshot( $record, $funds_flow, $fx_record, $now );
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function same_object_values( $left, $right, $keys ) {
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $left ) || ! array_key_exists( $key, $right ) || $left[ $key ] !== $right[ $key ] ) {
				return false;
			}
		}
		return true;
	}

	private static function error( $suffix, $message, $status = 400 ) {
		return new WP_Error( 'tra_vel_commerce_currency_bridge_factory_' . $suffix, $message, array( 'status' => $status ) );
	}
}
