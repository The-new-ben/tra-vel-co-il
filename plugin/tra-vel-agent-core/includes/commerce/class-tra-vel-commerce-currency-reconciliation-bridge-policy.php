<?php
/**
 * Closed cross-ledger policy binding funds flow to FX reconciliation.
 *
 * This server-only sandbox projection keeps source-currency customer funds and
 * supplier accrual separate from target-currency supplier settlement. It does
 * not mutate either source snapshot or grant payment or settlement authority.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_MONEY_MINOR  = 1000000000000;

	/**
	 * Validate a complete bridge against the complete current source snapshots.
	 *
	 * @param array $record     Closed bridge snapshot.
	 * @param array $funds_flow Complete funds-flow snapshot.
	 * @param array $fx_record  Complete FX reconciliation snapshot.
	 * @param int   $now        Positive injected UTC epoch.
	 * @return array|WP_Error
	 */
	public static function validate_snapshot( $record, $funds_flow, $fx_record, $now ) {
		$keys = array(
			'contract_version', 'environment', 'bridge_ref', 'bridge_binding_digest',
			'snapshot_digest', 'binding', 'currency_bridge', 'source_customer_funds',
			'source_supplier_accrual', 'target_supplier_settlement', 'overall_status',
			'evaluated_at', 'sandbox_truth', 'data_boundary',
		);
		if ( self::contains_sensitive_material( $record ) ) {
			return self::error( 'sensitive_material_rejected', 'Currency bridges accept financial evidence metadata and opaque digests only, never credentials, payment-card data, or personal data.' );
		}
		if (
			! self::exact_object( $record, $keys ) ||
			self::CONTRACT_VERSION !== $record['contract_version'] ||
			'sandbox' !== $record['environment'] ||
			! is_int( $now ) || $now < 1
		) {
			return self::error( 'record_shape_invalid', 'The currency bridge is not the closed private sandbox contract.' );
		}

		$projection = self::project_pair( $funds_flow, $fx_record, $now );
		if ( is_wp_error( $projection ) ) {
			return $projection;
		}
		if (
			! self::bridge_ref( $record['bridge_ref'] ) ||
			! self::digest( $record['bridge_binding_digest'] ) ||
			! self::digest( $record['snapshot_digest'] )
		) {
			return self::error( 'identity_invalid', 'The bridge identity and integrity digests must be exact opaque values.' );
		}

		$evaluated = self::utc_timestamp( $record['evaluated_at'] );
		$funds_updated = self::utc_timestamp( $funds_flow['updated_at'] );
		$fx_updated = self::utc_timestamp( $fx_record['updated_at'] );
		if ( false === $evaluated || $evaluated > $now || $evaluated < max( $funds_updated, $fx_updated ) ) {
			return self::error( 'evaluation_clock_invalid', 'Bridge evaluation must be at or after both bound snapshots and no later than the injected clock.' );
		}

		foreach ( array( 'binding', 'currency_bridge', 'source_customer_funds', 'source_supplier_accrual', 'target_supplier_settlement', 'overall_status' ) as $key ) {
			$matches = is_array( $projection[ $key ] )
				? self::canonical_digest( $record[ $key ] ) === self::canonical_digest( $projection[ $key ] )
				: $record[ $key ] === $projection[ $key ];
			if ( ! $matches ) {
				return self::error( 'projection_mismatch', 'The stored bridge position must replay exactly from the two bound current snapshots.' );
			}
		}

		$truth = array(
			'deterministic_projection' => true,
			'real_rate_provider_call'  => false,
			'real_processor_call'      => false,
			'real_customer_charge'     => false,
			'real_supplier_payment'    => false,
			'real_settlement'          => false,
			'external_authority'       => false,
		);
		$boundary = array(
			'server_only'                  => true,
			'public_serialization_allowed' => false,
			'raw_credentials_stored'       => false,
			'raw_payment_data_stored'      => false,
			'personal_data_stored'         => false,
		);
		if ( self::canonical_digest( $record['sandbox_truth'] ) !== self::canonical_digest( $truth ) || self::canonical_digest( $record['data_boundary'] ) !== self::canonical_digest( $boundary ) ) {
			return self::error( 'truth_boundary_invalid', 'The bridge must remain deterministic, simulated, private, non-authoritative, and free of sensitive material.' );
		}

		$binding_digest = self::binding_digest( $record );
		if ( '' === $binding_digest || ! hash_equals( $record['bridge_binding_digest'], $binding_digest ) ) {
			return self::error( 'binding_digest_invalid', 'The exact owner, order, item, routing, funds-flow, FX, currency, rate, or quote binding changed.', 409 );
		}
		$expected_ref = 'fxbridge_' . substr( hash( 'sha256', $binding_digest ), 0, 32 );
		if ( ! hash_equals( $expected_ref, $record['bridge_ref'] ) ) {
			return self::error( 'bridge_ref_invalid', 'The bridge reference must be derived from the exact immutable cross-ledger binding.', 409 );
		}
		$snapshot_digest = self::snapshot_digest( $record );
		if ( '' === $snapshot_digest || ! hash_equals( $record['snapshot_digest'], $snapshot_digest ) ) {
			return self::error( 'snapshot_digest_invalid', 'The materialized currency bridge no longer matches its complete integrity digest.', 409 );
		}
		return $record;
	}

	/**
	 * Validate both complete snapshots and derive the only supported bridge view.
	 *
	 * @param array $funds_flow Complete funds-flow snapshot.
	 * @param array $fx_record Complete FX snapshot.
	 * @param int   $now Positive injected UTC epoch.
	 * @return array|WP_Error
	 */
	public static function project_pair( $funds_flow, $fx_record, $now ) {
		if ( ! is_int( $now ) || $now < 1 ) {
			return self::error( 'clock_invalid', 'A positive injected UTC clock is required.' );
		}
		$funds = Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $funds_flow, $now );
		if ( is_wp_error( $funds ) ) {
			return self::error( 'funds_flow_snapshot_invalid', 'A complete valid funds-flow snapshot is required for cross-ledger reconciliation.', 409 );
		}
		$fx = Tra_Vel_Commerce_Fx_Reconciliation_Policy::validate_snapshot( $fx_record, $now );
		if ( is_wp_error( $fx ) ) {
			return self::error( 'fx_snapshot_invalid', 'A complete valid FX reconciliation snapshot is required for cross-ledger reconciliation.', 409 );
		}

		if (
			$funds['owner_scope_digest'] !== $fx['owner_scope_digest'] ||
			$funds['order_ref'] !== $fx['order_ref'] ||
			$funds['order_item_ref'] !== $fx['order_item_ref'] ||
			$funds['funds_flow_binding_digest'] !== $fx['funds_flow_binding_digest']
		) {
			return self::error( 'cross_ledger_identity_mismatch', 'Funds flow and FX must identify the exact same owner, order, item, and immutable funds-flow binding.', 409 );
		}
		if (
			$funds['currency'] !== $fx['source_currency'] ||
			$funds['minor_unit_exponent'] !== $fx['source_exponent'] ||
			$fx['target_currency'] === $funds['currency']
		) {
			return self::error( 'currency_direction_mismatch', 'Funds-flow currency and exponent must equal the FX source; the explicit FX target must remain different.', 409 );
		}
		if (
			'platform' !== $funds['parties']['payment_collector'] ||
			'platform' !== $funds['parties']['merchant_of_record'] ||
			null === $funds['private_routes']['supplier_payable_route_ref'] ||
			$funds['pricing']['supplier_payable_minor'] < 1
		) {
			return self::error( 'unsupported_collection_scope', 'Version one bridges only a positive platform-collected supplier payable with an opaque private payout route.', 409 );
		}
		if (
			'settlement_obligation' !== $fx['ledger']['ledger_code'] ||
			1 !== count( $fx['ledger']['lines'] ) ||
			'supplier_payable' !== $fx['ledger']['lines'][0]['code'] ||
			$funds['pricing']['supplier_payable_minor'] !== $fx['ledger']['lines'][0]['source_amount_minor'] ||
			$funds['pricing']['supplier_payable_minor'] !== $fx['ledger']['source_total_minor']
		) {
			return self::error( 'supplier_payable_component_mismatch', 'The FX settlement ledger must contain only the exact source-currency supplier payable from funds flow.', 409 );
		}

		$settlement = $funds['settlement'];
		if (
			! in_array( $settlement['state'], array( 'not_started', 'accrued', 'disputed' ), true ) ||
			0 !== $settlement['supplier_paid_minor'] ||
			0 !== $settlement['commission_due_minor'] ||
			0 !== $settlement['commission_received_minor'] ||
			0 !== $settlement['chargeback_recovery_due_minor'] ||
			0 !== $settlement['chargeback_recovered_minor']
		) {
			return self::error( 'source_settlement_contamination', 'Target-currency payment must never be recorded as a source-currency supplier payment or mixed with another settlement obligation.', 409 );
		}
		if (
			( 'not_started' === $settlement['state'] && ( 0 !== $settlement['supplier_due_minor'] || 0 !== $funds['liabilities']['supplier_payable_outstanding_minor'] ) ) ||
			( 'not_started' !== $settlement['state'] && ( $funds['pricing']['supplier_payable_minor'] !== $settlement['supplier_due_minor'] || $funds['pricing']['supplier_payable_minor'] !== $funds['liabilities']['supplier_payable_outstanding_minor'] ) )
		) {
			return self::error( 'source_accrual_mismatch', 'Source-currency accrual must be either absent or the complete supplier payable, with zero source-currency payment.', 409 );
		}

		$customer_status = self::customer_funds_status( $funds['payment'], $funds['pricing']['customer_total_minor'] );
		$accrual_status = 'not_started' === $settlement['state'] ? 'not_accrued' : ( 'disputed' === $settlement['state'] ? 'disputed' : 'accrued' );
		$target_payable = $fx['liabilities']['supplier_payable_target_minor'];
		$target_settled = $fx['servicing']['supplier_settled_target_minor'];
		$target_outstanding = $fx['liabilities']['supplier_payable_outstanding_target_minor'];
		$target_status = 0 === $target_settled ? 'not_started' : ( 0 === $target_outstanding ? 'settled' : 'partially_settled' );

		$source_customer = array(
			'currency'                         => $funds['currency'],
			'exponent'                         => $funds['minor_unit_exponent'],
			'payment_state'                    => $funds['payment']['state'],
			'status'                           => $customer_status,
			'authorized_source_minor'          => $funds['payment']['authorized_amount_minor'],
			'captured_source_minor'            => $funds['payment']['captured_amount_minor'],
			'refunded_source_minor'            => $funds['payment']['refunded_amount_minor'],
			'disputed_source_minor'            => $funds['payment']['disputed_amount_minor'],
			'charged_back_source_minor'        => $funds['payment']['charged_back_amount_minor'],
			'net_customer_funds_source_minor'  => $funds['payment']['captured_amount_minor'] - $funds['payment']['refunded_amount_minor'] - $funds['payment']['charged_back_amount_minor'],
		);
		$source_accrual = array(
			'currency'                          => $funds['currency'],
			'exponent'                          => $funds['minor_unit_exponent'],
			'settlement_state'                  => $settlement['state'],
			'status'                            => $accrual_status,
			'supplier_payable_source_minor'     => $funds['pricing']['supplier_payable_minor'],
			'supplier_due_source_minor'         => $settlement['supplier_due_minor'],
			'supplier_paid_source_minor'        => 0,
			'supplier_outstanding_source_minor' => $funds['liabilities']['supplier_payable_outstanding_minor'],
		);
		$target_position = array(
			'currency'                          => $fx['target_currency'],
			'exponent'                          => $fx['target_exponent'],
			'status'                            => $target_status,
			'supplier_payable_target_minor'     => $target_payable,
			'supplier_settled_target_minor'     => $target_settled,
			'supplier_outstanding_target_minor' => $target_outstanding,
		);

		return array(
			'binding' => array(
				'owner_scope_digest'       => $funds['owner_scope_digest'],
				'order_ref'                => $funds['order_ref'],
				'order_version'            => $funds['order_version'],
				'order_digest'             => $funds['order_digest'],
				'order_item_ref'           => $funds['order_item_ref'],
				'offer_digest'             => $funds['offer_digest'],
				'routing_binding_digest'   => $funds['routing_binding_digest'],
				'funds_flow_ref'           => $funds['funds_flow_ref'],
				'funds_flow_binding_digest'=> $funds['funds_flow_binding_digest'],
				'funds_flow_snapshot_digest'=> $funds['snapshot_digest'],
				'fx_reconciliation_ref'    => $fx['reconciliation_ref'],
				'fx_snapshot_digest'       => $fx['snapshot_digest'],
			),
			'currency_bridge' => array(
				'bridge_scope'       => 'platform_collected_supplier_payable',
				'source_currency'    => $funds['currency'],
				'source_exponent'    => $funds['minor_unit_exponent'],
				'target_currency'    => $fx['target_currency'],
				'target_exponent'    => $fx['target_exponent'],
				'source_rate_digest' => $fx['source_rate']['source_rate_digest'],
				'locked_quote_digest'=> $fx['locked_quote']['quote_digest'],
				'fx_ledger_code'     => $fx['ledger']['ledger_code'],
			),
			'source_customer_funds'       => $source_customer,
			'source_supplier_accrual'     => $source_accrual,
			'target_supplier_settlement'  => $target_position,
			'overall_status'              => self::overall_status( $customer_status, $accrual_status, $target_status, $fx ),
		);
	}

	/**
	 * Seal the deterministic binding and complete record digests.
	 *
	 * @param array $record Unsealed bridge record.
	 * @return array
	 */
	public static function seal_snapshot( $record ) {
		$record['bridge_binding_digest'] = self::binding_digest( $record );
		$record['bridge_ref'] = 'fxbridge_' . substr( hash( 'sha256', $record['bridge_binding_digest'] ), 0, 32 );
		$record['snapshot_digest'] = self::snapshot_digest( $record );
		return $record;
	}

	/**
	 * Digest immutable source identities, current snapshot digests, and currency scope.
	 *
	 * @param array $record Bridge record.
	 * @return string
	 */
	public static function binding_digest( $record ) {
		$keys = array( 'contract_version', 'environment', 'binding', 'currency_bridge', 'sandbox_truth', 'data_boundary' );
		if ( ! is_array( $record ) || array_diff( $keys, array_keys( $record ) ) ) {
			return '';
		}
		$basis = array();
		foreach ( $keys as $key ) {
			$basis[ $key ] = $record[ $key ];
		}
		return self::canonical_digest( $basis );
	}

	/**
	 * Digest the complete materialized projection except its own digest field.
	 *
	 * @param array $record Bridge record.
	 * @return string
	 */
	public static function snapshot_digest( $record ) {
		if ( ! is_array( $record ) ) {
			return '';
		}
		$basis = $record;
		unset( $basis['snapshot_digest'] );
		return self::canonical_digest( $basis );
	}

	private static function customer_funds_status( $payment, $customer_total ) {
		if ( 'payment_failed' === $payment['state'] ) {
			return 'failed';
		}
		if ( 'uncertain' === $payment['state'] ) {
			return 'uncertain';
		}
		if ( in_array( $payment['state'], array( 'disputed', 'charged_back' ), true ) ) {
			return 'at_risk';
		}
		if ( $payment['refunded_amount_minor'] > 0 ) {
			return $payment['captured_amount_minor'] - $payment['refunded_amount_minor'] - $payment['charged_back_amount_minor'] > 0
				? 'partially_returned'
				: 'returned';
		}
		if ( $payment['captured_amount_minor'] === $customer_total ) {
			return 'collected';
		}
		if ( $payment['captured_amount_minor'] > 0 ) {
			return 'partially_collected';
		}
		return 'not_collected';
	}

	private static function overall_status( $customer, $accrual, $target, $fx ) {
		$has_fx_exception =
			$fx['liabilities']['customer_refund_due_target_minor'] > 0 ||
			$fx['liabilities']['dispute_exposure_target_minor'] > 0 ||
			$fx['liabilities']['chargeback_exposure_target_minor'] > 0;
		$source_exception = in_array( $customer, array( 'partially_returned', 'returned', 'at_risk', 'failed', 'uncertain' ), true ) || 'disputed' === $accrual;
		$premature_target_settlement = 'not_started' !== $target && ( 'collected' !== $customer || 'accrued' !== $accrual );
		if ( $has_fx_exception || $source_exception || $premature_target_settlement ) {
			return 'exception';
		}
		if ( 'collected' !== $customer ) {
			return 'waiting_customer_funds';
		}
		if ( 'accrued' !== $accrual ) {
			return 'waiting_source_accrual';
		}
		if ( 'not_started' === $target ) {
			return 'ready_for_target_settlement';
		}
		return 'partially_settled' === $target ? 'partially_target_settled' : 'target_settled';
	}

	private static function bridge_ref( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^fxbridge_[a-f0-9]{32}$/', $value );
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function utc_timestamp( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/', $value, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59 ) {
			return false;
		}
		try {
			return ( new DateTimeImmutable( $value ) )->getTimestamp();
		} catch ( Exception $error ) {
			return false;
		}
	}

	private static function contains_sensitive_material( $value, $key = '' ) {
		$key = strtolower( (string) $key );
		$sensitive_key = 1 === preg_match( '/(?:password|secret|api[_-]?key|access[_-]?token|card[_-]?(?:number|pan)|cvv|cvc|passport|email|phone|customer[_-]?name|traveler[_-]?name)/', $key );
		if ( $sensitive_key && null !== $value && false !== $value && '' !== $value ) {
			return true;
		}
		if ( ! is_array( $value ) ) {
			return false;
		}
		foreach ( $value as $child_key => $child ) {
			if ( self::contains_sensitive_material( $child, $child_key ) ) {
				return true;
			}
		}
		return false;
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
		return new WP_Error( 'tra_vel_commerce_currency_bridge_' . $suffix, $message, array( 'status' => $status ) );
	}
}
