<?php
/**
 * Closed, server-only FX reconciliation policy for sandbox commerce ledgers.
 *
 * This class performs deterministic integer arithmetic only. It does not fetch a
 * rate, contact a processor, mutate a funds-flow record, or authorize settlement.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Fx_Reconciliation_Policy {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_MONEY_MINOR  = 1000000000000;
	const MAX_EVENTS       = 128;

	const CURRENCY_EXPONENTS = array(
		'EUR' => 2,
		'GBP' => 2,
		'ILS' => 2,
		'JPY' => 0,
		'KWD' => 3,
		'THB' => 2,
		'USD' => 2,
	);

	const EVENT_TYPES = array(
		'refund_accrued',
		'refund_settled',
		'reversal_observed',
		'dispute_opened',
		'dispute_closed',
		'chargeback_observed',
		'chargeback_recovered',
		'supplier_settlement_observed',
	);

	/**
	 * Build and seal an initial immutable conversion record.
	 *
	 * @param array $binding Exact order-item and idempotency binding.
	 * @param array $source_rate Sealed simulated source-rate snapshot.
	 * @param array $locked_quote Sealed quote referencing the source rate.
	 * @param array $source_lines Exact source-currency component lines.
	 * @param int   $now Positive UTC epoch used only as an injected clock.
	 * @return array|WP_Error
	 */
	public static function create_initial_snapshot( $binding, $source_rate, $locked_quote, $source_lines, $now ) {
		$binding_keys = array(
			'owner_scope_digest', 'order_ref', 'order_item_ref', 'funds_flow_binding_digest',
			'idempotency_key_digest', 'source_currency', 'source_exponent',
			'target_currency', 'target_exponent', 'ledger_code',
		);
		if ( ! self::exact_object( $binding, $binding_keys ) || ! is_int( $now ) || $now < 1 ) {
			return self::error( 'initial_context_invalid', 'An exact binding and positive injected clock are required.' );
		}
		if (
			! self::digest( $binding['owner_scope_digest'] ) ||
			! self::public_ref( $binding['order_ref'], 'order' ) ||
			! self::public_ref( $binding['order_item_ref'], 'order_item' ) ||
			! self::digest( $binding['funds_flow_binding_digest'] ) ||
			! self::digest( $binding['idempotency_key_digest'] ) ||
			! self::currency_exponent( $binding['source_currency'], $binding['source_exponent'] ) ||
			! self::currency_exponent( $binding['target_currency'], $binding['target_exponent'] ) ||
			$binding['source_currency'] === $binding['target_currency'] ||
			! in_array( $binding['ledger_code'], array( 'customer_pricing', 'settlement_obligation' ), true )
		) {
			return self::error( 'initial_binding_invalid', 'The initial FX record must bind one exact owner, order item, funds flow, idempotency digest, and ledger role.' );
		}

		$rate = self::validate_source_rate( $source_rate, $now, true );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}
		if (
			$rate['base_currency'] !== $binding['source_currency'] || $rate['base_exponent'] !== $binding['source_exponent'] ||
			$rate['quote_currency'] !== $binding['target_currency'] || $rate['quote_exponent'] !== $binding['target_exponent']
		) {
			return self::error( 'currency_direction_invalid', 'The supplied rate must preserve the caller-bound source-to-target direction exactly; inversion is never inferred.' );
		}
		$quote = self::validate_locked_quote( $locked_quote, $rate, $now, true );
		if ( is_wp_error( $quote ) ) {
			return $quote;
		}
		$ledger = self::calculate_ledger( $binding['ledger_code'], $source_lines, $rate, $quote );
		if ( is_wp_error( $ledger ) ) {
			return $ledger;
		}

		$created_at = gmdate( 'Y-m-d\TH:i:s\Z', $now );
		$identity = implode(
			'|',
			array(
				$binding['owner_scope_digest'],
				$binding['order_ref'],
				$binding['order_item_ref'],
				$binding['funds_flow_binding_digest'],
				$binding['idempotency_key_digest'],
				$rate['source_rate_digest'],
				$quote['quote_digest'],
			)
		);
		$servicing = self::empty_servicing();
		$record = array(
			'contract_version'          => self::CONTRACT_VERSION,
			'environment'               => 'sandbox',
			'reconciliation_ref'        => 'fxrec_' . substr( hash( 'sha256', $identity ), 0, 32 ),
			'version'                   => 1,
			'previous_snapshot_digest'  => null,
			'snapshot_digest'           => '',
			'owner_scope_digest'        => $binding['owner_scope_digest'],
			'order_ref'                 => $binding['order_ref'],
			'order_item_ref'            => $binding['order_item_ref'],
			'funds_flow_binding_digest' => $binding['funds_flow_binding_digest'],
			'idempotency_key_digest'    => $binding['idempotency_key_digest'],
			'source_currency'           => $rate['base_currency'],
			'source_exponent'           => $rate['base_exponent'],
			'target_currency'           => $rate['quote_currency'],
			'target_exponent'           => $rate['quote_exponent'],
			'source_rate'               => $rate,
			'locked_quote'              => $quote,
			'ledger'                    => $ledger,
			'servicing'                 => $servicing,
			'liabilities'               => self::derive_liabilities( $ledger, $servicing, $quote ),
			'event_history'             => array(),
			'created_at'                => $created_at,
			'updated_at'                => $created_at,
			'last_event_sequence'       => 0,
			'sandbox_truth'             => array(
				'simulated_rate'            => true,
				'real_rate_provider_call'   => false,
				'real_processor_call'       => false,
				'real_customer_charge'      => false,
				'real_supplier_payment'     => false,
				'real_settlement'           => false,
			),
			'data_boundary'             => array(
				'server_only'                  => true,
				'public_serialization_allowed' => false,
				'raw_credentials_stored'       => false,
				'raw_payment_data_stored'      => false,
				'personal_data_stored'         => false,
			),
		);
		$record = self::seal_snapshot( $record );
		return self::validate_snapshot( $record, $now );
	}

	/**
	 * Validate a complete immutable snapshot and replay all servicing evidence.
	 *
	 * @param array $record Private reconciliation record.
	 * @param int   $now Injected UTC epoch.
	 * @return array|WP_Error
	 */
	public static function validate_snapshot( $record, $now ) {
		if ( self::contains_sensitive_material( $record ) ) {
			return self::error( 'sensitive_material_rejected', 'FX reconciliation accepts only opaque references, digests, currencies, integer money, and evidence metadata.' );
		}
		$keys = array(
			'contract_version', 'environment', 'reconciliation_ref', 'version',
			'previous_snapshot_digest', 'snapshot_digest', 'owner_scope_digest',
			'order_ref', 'order_item_ref', 'funds_flow_binding_digest',
			'idempotency_key_digest', 'source_currency', 'source_exponent',
			'target_currency', 'target_exponent', 'source_rate', 'locked_quote',
			'ledger', 'servicing', 'liabilities', 'event_history', 'created_at',
			'updated_at', 'last_event_sequence', 'sandbox_truth', 'data_boundary',
		);
		if ( ! self::exact_object( $record, $keys ) || ! is_int( $now ) || $now < 1 ) {
			return self::error( 'record_shape_invalid', 'The FX reconciliation snapshot is not the closed private contract.' );
		}
		if (
			self::CONTRACT_VERSION !== $record['contract_version'] || 'sandbox' !== $record['environment'] ||
			! is_string( $record['reconciliation_ref'] ) || 1 !== preg_match( '/^fxrec_[a-f0-9]{32}$/', $record['reconciliation_ref'] ) ||
			! is_int( $record['version'] ) || $record['version'] < 1 ||
			! self::digest( $record['snapshot_digest'] ) || ! self::digest( $record['owner_scope_digest'] ) ||
			! self::public_ref( $record['order_ref'], 'order' ) || ! self::public_ref( $record['order_item_ref'], 'order_item' ) ||
			! self::digest( $record['funds_flow_binding_digest'] ) || ! self::digest( $record['idempotency_key_digest'] )
		) {
			return self::error( 'identity_invalid', 'The FX record identity, version, order-item binding, or digest is invalid.' );
		}
		if ( 1 === $record['version'] ? null !== $record['previous_snapshot_digest'] : ! self::digest( $record['previous_snapshot_digest'] ) ) {
			return self::error( 'ancestry_invalid', 'Version one has no predecessor; every successor must name one predecessor digest.' );
		}
		$created = self::utc_timestamp( $record['created_at'] );
		$updated = self::utc_timestamp( $record['updated_at'] );
		if ( false === $created || false === $updated || $created > $updated || $updated > $now ) {
			return self::error( 'clock_invalid', 'Created and updated clocks must be valid UTC evidence not later than the injected clock.' );
		}
		if (
			! self::currency_exponent( $record['source_currency'], $record['source_exponent'] ) ||
			! self::currency_exponent( $record['target_currency'], $record['target_exponent'] ) ||
			$record['source_currency'] === $record['target_currency']
		) {
			return self::error( 'currency_pair_invalid', 'Source and target currencies must be distinct supported currency/exponent pairs.' );
		}

		$rate = self::validate_source_rate( $record['source_rate'], $created, false );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}
		if (
			$rate['base_currency'] !== $record['source_currency'] || $rate['base_exponent'] !== $record['source_exponent'] ||
			$rate['quote_currency'] !== $record['target_currency'] || $rate['quote_exponent'] !== $record['target_exponent']
		) {
			return self::error( 'currency_direction_invalid', 'The record must consume the locked base-to-quote direction exactly; automatic inversion is forbidden.' );
		}
		$quote = self::validate_locked_quote( $record['locked_quote'], $rate, $created, false );
		if ( is_wp_error( $quote ) ) {
			return $quote;
		}

		if ( ! is_array( $record['ledger'] ) || ! isset( $record['ledger']['ledger_code'], $record['ledger']['lines'] ) ) {
			return self::error( 'ledger_shape_invalid', 'The converted ledger is missing its exact role or component list.' );
		}
		$source_lines = array();
		foreach ( $record['ledger']['lines'] as $line ) {
			if ( ! is_array( $line ) || ! isset( $line['code'], $line['source_amount_minor'] ) ) {
				return self::error( 'ledger_line_invalid', 'Every converted line must preserve a source component code and integer amount.' );
			}
			$source_lines[] = array( 'code' => $line['code'], 'source_amount_minor' => $line['source_amount_minor'] );
		}
		$expected_ledger = self::calculate_ledger( $record['ledger']['ledger_code'], $source_lines, $rate, $quote );
		if ( is_wp_error( $expected_ledger ) || $expected_ledger !== $record['ledger'] ) {
			return self::error( 'ledger_calculation_invalid', 'Every market conversion, spread, fee, line rounding, and residual allocation must replay exactly.' );
		}

		$projection = self::project_event_history( $record['event_history'], $record['ledger'], $rate, $quote, $created, $now );
		if ( is_wp_error( $projection ) ) {
			return $projection;
		}
		if (
			$projection['history'] !== $record['event_history'] ||
			$projection['servicing'] !== $record['servicing'] ||
			self::derive_liabilities( $record['ledger'], $projection['servicing'], $quote ) !== $record['liabilities']
		) {
			return self::error( 'servicing_projection_invalid', 'Servicing, liabilities, rates, and event evidence do not replay to the stored snapshot.' );
		}
		$last_event_at = $record['created_at'];
		if ( $record['event_history'] ) {
			$last_index = count( $record['event_history'] ) - 1;
			$last_event_at = $record['event_history'][ $last_index ]['occurred_at'];
		}
		if (
			! is_int( $record['last_event_sequence'] ) || $record['last_event_sequence'] !== count( $record['event_history'] ) ||
			$record['version'] !== $record['last_event_sequence'] + 1 ||
			$last_event_at !== $record['updated_at']
		) {
			return self::error( 'event_version_invalid', 'Version, event sequence, and evidence clock must advance together exactly once.' );
		}

		$truth = array(
			'simulated_rate'          => true,
			'real_rate_provider_call' => false,
			'real_processor_call'     => false,
			'real_customer_charge'    => false,
			'real_supplier_payment'   => false,
			'real_settlement'         => false,
		);
		$boundary = array(
			'server_only'                  => true,
			'public_serialization_allowed' => false,
			'raw_credentials_stored'       => false,
			'raw_payment_data_stored'      => false,
			'personal_data_stored'         => false,
		);
		if ( $record['sandbox_truth'] !== $truth || $record['data_boundary'] !== $boundary ) {
			return self::error( 'truth_boundary_invalid', 'The FX record must remain simulated, private, non-authoritative, and free of customer or credential data.' );
		}
		if ( ! hash_equals( self::snapshot_digest( $record ), $record['snapshot_digest'] ) ) {
			return self::error( 'snapshot_digest_invalid', 'The FX snapshot changed after its immutable digest was issued.' );
		}
		return $record;
	}

	/**
	 * Prove that only one evidence event changed between immutable versions.
	 *
	 * @param array $current Valid current snapshot.
	 * @param array $next Candidate successor.
	 * @param int   $now Injected UTC epoch.
	 * @return true|WP_Error
	 */
	public static function assert_successor( $current, $next, $now ) {
		$current_valid = self::validate_snapshot( $current, $now );
		$next_valid    = self::validate_snapshot( $next, $now );
		if ( is_wp_error( $current_valid ) || is_wp_error( $next_valid ) ) {
			return self::error( 'successor_snapshot_invalid', 'Both the current and candidate FX snapshots must independently validate.' );
		}
		$immutable = array(
			'contract_version', 'environment', 'reconciliation_ref', 'owner_scope_digest',
			'order_ref', 'order_item_ref', 'funds_flow_binding_digest', 'idempotency_key_digest',
			'source_currency', 'source_exponent', 'target_currency', 'target_exponent',
			'source_rate', 'locked_quote', 'ledger', 'created_at', 'sandbox_truth', 'data_boundary',
		);
		foreach ( $immutable as $key ) {
			if ( $current[ $key ] !== $next[ $key ] ) {
				return self::error( 'immutable_field_changed', 'A successor cannot rewrite FX identity, currencies, locked evidence, original ledger, or private truth.' );
			}
		}
		if (
			$next['version'] !== $current['version'] + 1 ||
			! hash_equals( $current['snapshot_digest'], $next['previous_snapshot_digest'] ) ||
			$next['last_event_sequence'] !== $current['last_event_sequence'] + 1 ||
			count( $next['event_history'] ) !== count( $current['event_history'] ) + 1 ||
			array_slice( $next['event_history'], 0, count( $current['event_history'] ) ) !== $current['event_history']
		) {
			return self::error( 'successor_chain_invalid', 'A successor must append exactly one event and bind the exact predecessor digest.' );
		}
		return true;
	}

	/** Seal a source-rate fixture with its self-digest. */
	public static function seal_source_rate( $rate ) {
		if ( ! is_array( $rate ) ) {
			return $rate;
		}
		$rate['source_rate_digest'] = self::source_rate_digest( $rate );
		return $rate;
	}

	/** Seal a quote with its self-digest. */
	public static function seal_locked_quote( $quote ) {
		if ( ! is_array( $quote ) ) {
			return $quote;
		}
		$quote['quote_digest'] = self::locked_quote_digest( $quote );
		return $quote;
	}

	/** Seal a complete snapshot. */
	public static function seal_snapshot( $record ) {
		if ( ! is_array( $record ) ) {
			return $record;
		}
		$record['snapshot_digest'] = self::snapshot_digest( $record );
		return $record;
	}

	public static function source_rate_digest( $rate ) {
		$basis = is_array( $rate ) ? $rate : array();
		unset( $basis['source_rate_digest'] );
		return self::canonical_digest( $basis );
	}

	public static function locked_quote_digest( $quote ) {
		$basis = is_array( $quote ) ? $quote : array();
		unset( $basis['quote_digest'] );
		return self::canonical_digest( $basis );
	}

	public static function snapshot_digest( $record ) {
		$basis = is_array( $record ) ? $record : array();
		unset( $basis['snapshot_digest'] );
		return self::canonical_digest( $basis );
	}

	/**
	 * Deterministically replay a closed evidence history.
	 *
	 * @return array|WP_Error
	 */
	public static function project_event_history( $events, $ledger, $rate, $quote, $created, $now ) {
		if ( ! self::is_list( $events ) || count( $events ) > self::MAX_EVENTS ) {
			return self::error( 'event_history_invalid', 'Event history must be an ordered list within the closed retention limit.' );
		}
		$servicing = self::empty_servicing();
		$history = array();
		$seen = array();
		$previous_clock = $created;
		foreach ( $events as $index => $event ) {
			$expected_sequence = $index + 1;
			$event_result = self::apply_projected_event( $servicing, $event, $expected_sequence, $ledger, $rate, $quote, $previous_clock, $now );
			if ( is_wp_error( $event_result ) ) {
				return $event_result;
			}
			if ( isset( $seen[ $event['idempotency_key_digest'] ] ) ) {
				return self::error( 'duplicate_event_invalid', 'An immutable history cannot contain a duplicate idempotency digest.' );
			}
			$seen[ $event['idempotency_key_digest'] ] = true;
			$servicing = $event_result['servicing'];
			$history[] = $event_result['event'];
			$previous_clock = self::utc_timestamp( $event['occurred_at'] );
		}
		return array( 'history' => $history, 'servicing' => $servicing );
	}

	/** Return the exact liability projection for an already replayed state. */
	public static function liabilities_for( $ledger, $servicing, $quote ) {
		return self::derive_liabilities( $ledger, $servicing, $quote );
	}

	private static function validate_source_rate( $rate, $clock, $require_current ) {
		$keys = array(
			'source_rate_ref', 'source_rate_version', 'source_rate_digest', 'fixture_label',
			'base_currency', 'quote_currency', 'base_exponent', 'quote_exponent',
			'numerator', 'denominator', 'observed_at', 'effective_at', 'valid_until',
			'simulated', 'real_provider_response',
		);
		if ( ! self::exact_object( $rate, $keys ) ) {
			return self::error( 'source_rate_shape_invalid', 'The source rate is not the closed sandbox evidence contract.' );
		}
		if (
			! is_string( $rate['source_rate_ref'] ) || 1 !== preg_match( '/^fxrate_[a-z0-9][a-z0-9_-]{7,95}$/', $rate['source_rate_ref'] ) ||
			! is_int( $rate['source_rate_version'] ) || $rate['source_rate_version'] < 1 ||
			! self::digest( $rate['source_rate_digest'] ) ||
			! is_string( $rate['fixture_label'] ) || 1 !== preg_match( '/^SIMULATED_[A-Z0-9_]{8,80}$/', $rate['fixture_label'] ) ||
			! self::currency_exponent( $rate['base_currency'], $rate['base_exponent'] ) ||
			! self::currency_exponent( $rate['quote_currency'], $rate['quote_exponent'] ) ||
			$rate['base_currency'] === $rate['quote_currency'] ||
			! is_int( $rate['numerator'] ) || $rate['numerator'] < 1 || $rate['numerator'] > 100000 ||
			! is_int( $rate['denominator'] ) || $rate['denominator'] < 1 || $rate['denominator'] > 100000 ||
			true !== $rate['simulated'] || false !== $rate['real_provider_response']
		) {
			return self::error( 'source_rate_invalid', 'A rate must be a distinctly labeled simulated rational base-to-quote fixture with exact currency exponents.' );
		}
		$observed = self::utc_timestamp( $rate['observed_at'] );
		$effective = self::utc_timestamp( $rate['effective_at'] );
		$valid_until = self::utc_timestamp( $rate['valid_until'] );
		if ( false === $observed || false === $effective || false === $valid_until || $observed > $effective || $effective >= $valid_until ) {
			return self::error( 'source_rate_clock_invalid', 'Rate observation, effect, and expiry must form a strict UTC interval.' );
		}
		if ( $effective > $clock || $clock >= $valid_until ) {
			return self::error( 'source_rate_stale', 'A new conversion requires a source-rate fixture effective and unexpired at the injected clock.' );
		}
		if ( ! hash_equals( self::source_rate_digest( $rate ), $rate['source_rate_digest'] ) ) {
			return self::error( 'source_rate_digest_invalid', 'The source-rate fixture changed after its digest was issued.' );
		}
		return $rate;
	}

	private static function validate_locked_quote( $quote, $rate, $clock, $require_current ) {
		$keys = array(
			'quote_ref', 'quote_version', 'quote_digest', 'source_rate_digest', 'locked_at',
			'locked_until', 'direction', 'spread_bps', 'spread_application', 'fee_minor',
			'fee_application', 'rounding_mode', 'residual_policy', 'refund_rate_policy',
			'reversal_rate_policy', 'dispute_rate_policy', 'chargeback_rate_policy', 'fee_refund_policy',
		);
		if ( ! self::exact_object( $quote, $keys ) ) {
			return self::error( 'locked_quote_shape_invalid', 'The locked quote is not the closed reconciliation contract.' );
		}
		if (
			! is_string( $quote['quote_ref'] ) || 1 !== preg_match( '/^fxquote_[a-f0-9]{32}$/', $quote['quote_ref'] ) ||
			! is_int( $quote['quote_version'] ) || $quote['quote_version'] < 1 ||
			! self::digest( $quote['quote_digest'] ) || ! self::digest( $quote['source_rate_digest'] ) ||
			! hash_equals( $rate['source_rate_digest'], $quote['source_rate_digest'] ) ||
			'base_to_quote' !== $quote['direction'] ||
			! is_int( $quote['spread_bps'] ) || $quote['spread_bps'] < 0 || $quote['spread_bps'] > 2500 ||
			! in_array( $quote['spread_application'], array( 'none', 'add_to_quote', 'deduct_from_quote' ), true ) ||
			! self::money( $quote['fee_minor'] ) ||
			! in_array( $quote['fee_application'], array( 'none', 'add_to_target', 'deduct_from_target' ), true ) ||
			'half_up' !== $quote['rounding_mode'] || 'largest_absolute_then_code' !== $quote['residual_policy'] ||
			'original_locked_rate' !== $quote['refund_rate_policy'] ||
			'original_locked_rate' !== $quote['reversal_rate_policy'] ||
			'original_locked_rate' !== $quote['dispute_rate_policy'] ||
			'original_locked_rate' !== $quote['chargeback_rate_policy'] ||
			! in_array( $quote['fee_refund_policy'], array( 'non_refundable', 'pro_rata' ), true )
		) {
			return self::error( 'locked_quote_invalid', 'The quote must bind one rate and explicit direction, rounding, spread, fee, and servicing policies.' );
		}
		if ( ( 0 === $quote['spread_bps'] ) !== ( 'none' === $quote['spread_application'] ) || ( 0 === $quote['fee_minor'] ) !== ( 'none' === $quote['fee_application'] ) ) {
			return self::error( 'quote_adjustment_invalid', 'Zero spread and fee require none; non-zero adjustments require an explicit application side.' );
		}
		$locked_at = self::utc_timestamp( $quote['locked_at'] );
		$locked_until = self::utc_timestamp( $quote['locked_until'] );
		$rate_effective = self::utc_timestamp( $rate['effective_at'] );
		$rate_valid_until = self::utc_timestamp( $rate['valid_until'] );
		if (
			false === $locked_at || false === $locked_until || $locked_at < $rate_effective ||
			$locked_at >= $locked_until || $locked_until > $rate_valid_until ||
			$locked_at > $clock || $clock >= $locked_until
		) {
			return self::error( 'locked_quote_stale', 'A new conversion requires a quote locked inside the source-rate validity window and unexpired at the injected clock.' );
		}
		if ( ! hash_equals( self::locked_quote_digest( $quote ), $quote['quote_digest'] ) ) {
			return self::error( 'locked_quote_digest_invalid', 'The locked quote changed after its digest was issued.' );
		}
		return $quote;
	}

	private static function calculate_ledger( $ledger_code, $source_lines, $rate, $quote ) {
		if ( ! in_array( $ledger_code, array( 'customer_pricing', 'settlement_obligation' ), true ) || ! self::is_list( $source_lines ) || ! $source_lines || count( $source_lines ) > 32 ) {
			return self::error( 'source_ledger_invalid', 'One supported ledger role with one to thirty-two source components is required.' );
		}
		$normalized = array();
		$seen = array();
		$source_total = 0;
		foreach ( $source_lines as $line ) {
			if (
				! self::exact_object( $line, array( 'code', 'source_amount_minor' ) ) ||
				! is_string( $line['code'] ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{1,47}$/', $line['code'] ) ||
				isset( $seen[ $line['code'] ] ) || ! self::signed_money( $line['source_amount_minor'] )
			) {
				return self::error( 'source_ledger_line_invalid', 'Source components require unique stable codes and bounded signed integer minor units.' );
			}
			$seen[ $line['code'] ] = true;
			$source_total = self::safe_add( $source_total, $line['source_amount_minor'] );
			if ( is_wp_error( $source_total ) ) {
				return $source_total;
			}
			$normalized[] = $line;
		}
		if ( $source_total <= 0 ) {
			return self::error( 'source_ledger_total_invalid', 'The signed source components must reconcile to a positive bounded total.' );
		}
		usort( $normalized, function ( $left, $right ) { return strcmp( $left['code'], $right['code'] ); } );

		$effective = self::effective_ratio( $rate, $quote );
		if ( is_wp_error( $effective ) ) {
			return $effective;
		}
		$market_total = self::convert_signed( $source_total, $rate['numerator'], $rate['denominator'] );
		$target_before_fee = self::convert_signed( $source_total, $effective['numerator'], $effective['denominator'] );
		if ( is_wp_error( $market_total ) || is_wp_error( $target_before_fee ) || $market_total < 0 || $target_before_fee < 0 ) {
			return self::error( 'ledger_conversion_overflow', 'The converted ledger total is outside the bounded integer-money domain.' );
		}

		$lines = array();
		$line_sum = 0;
		foreach ( $normalized as $line ) {
			$market = self::convert_signed( $line['source_amount_minor'], $rate['numerator'], $rate['denominator'] );
			$quoted = self::convert_signed( $line['source_amount_minor'], $effective['numerator'], $effective['denominator'] );
			if ( is_wp_error( $market ) || is_wp_error( $quoted ) ) {
				return self::error( 'ledger_line_overflow', 'A ledger component cannot be converted within bounded integer money.' );
			}
			$line_sum = self::safe_add( $line_sum, $quoted );
			if ( is_wp_error( $line_sum ) ) {
				return $line_sum;
			}
			$lines[] = array(
				'code'                                => $line['code'],
				'source_amount_minor'                 => $line['source_amount_minor'],
				'market_target_minor'                 => $market,
				'quoted_target_before_residual_minor' => $quoted,
				'residual_adjustment_minor'           => 0,
				'target_amount_minor'                 => $quoted,
			);
		}
		$residual = $target_before_fee - $line_sum;
		if ( abs( $residual ) > count( $lines ) ) {
			return self::error( 'rounding_residual_invalid', 'Component rounding residual exceeds the deterministic per-line bound.' );
		}
		$allocation_code = null;
		if ( 0 !== $residual ) {
			$allocation_index = 0;
			foreach ( $lines as $index => $line ) {
				$current_abs = abs( $lines[ $allocation_index ]['source_amount_minor'] );
				$candidate_abs = abs( $line['source_amount_minor'] );
				if ( $candidate_abs > $current_abs || ( $candidate_abs === $current_abs && strcmp( $line['code'], $lines[ $allocation_index ]['code'] ) < 0 ) ) {
					$allocation_index = $index;
				}
			}
			$adjusted = self::safe_add( $lines[ $allocation_index ]['target_amount_minor'], $residual );
			if ( is_wp_error( $adjusted ) ) {
				return $adjusted;
			}
			$lines[ $allocation_index ]['residual_adjustment_minor'] = $residual;
			$lines[ $allocation_index ]['target_amount_minor'] = $adjusted;
			$allocation_code = $lines[ $allocation_index ]['code'];
		}

		$fee_effect = 0;
		if ( 'add_to_target' === $quote['fee_application'] ) {
			$fee_effect = $quote['fee_minor'];
		} elseif ( 'deduct_from_target' === $quote['fee_application'] ) {
			$fee_effect = -$quote['fee_minor'];
		}
		$target_total = self::safe_add( $target_before_fee, $fee_effect );
		if ( is_wp_error( $target_total ) || $target_total < 0 ) {
			return self::error( 'fee_application_invalid', 'The explicit fee cannot make the bounded target total negative or overflow.' );
		}
		return array(
			'ledger_code'                 => $ledger_code,
			'source_total_minor'          => $source_total,
			'market_target_total_minor'   => $market_total,
			'target_before_fee_minor'     => $target_before_fee,
			'spread_effect_minor'         => $target_before_fee - $market_total,
			'fee_effect_minor'            => $fee_effect,
			'target_total_minor'          => $target_total,
			'rounding_residual_minor'     => $residual,
			'residual_allocation_code'    => $allocation_code,
			'unallocated_residual_minor'  => 0,
			'lines'                       => $lines,
		);
	}

	private static function apply_projected_event( $servicing, $event, $sequence, $ledger, $rate, $quote, $previous_clock, $now ) {
		$keys = array(
			'sequence', 'event_type', 'idempotency_key_digest', 'evidence_digest', 'occurred_at',
			'source_amount_minor', 'target_amount_minor', 'effective_target_delta_minor', 'rounding_adjustment_minor',
		);
		if ( ! self::exact_object( $event, $keys ) || $event['sequence'] !== $sequence || ! in_array( $event['event_type'], self::EVENT_TYPES, true ) || ! self::digest( $event['idempotency_key_digest'] ) || ! self::digest( $event['evidence_digest'] ) ) {
			return self::error( 'event_shape_invalid', 'Every event requires one exact type, next sequence, idempotency digest, and evidence digest.' );
		}
		$occurred = self::utc_timestamp( $event['occurred_at'] );
		if ( false === $occurred || $occurred <= $previous_clock || $occurred > $now ) {
			return self::error( 'event_clock_invalid', 'Events must be strictly ordered UTC evidence not later than the injected clock.' );
		}
		$source_events = array( 'refund_accrued', 'reversal_observed', 'dispute_opened', 'dispute_closed', 'chargeback_observed' );
		$target_events = array( 'refund_settled', 'chargeback_recovered', 'supplier_settlement_observed' );
		if ( in_array( $event['event_type'], $source_events, true ) ) {
			if ( ! self::positive_money( $event['source_amount_minor'] ) || null !== $event['target_amount_minor'] ) {
				return self::error( 'event_amount_invalid', 'This servicing event requires one positive source-currency amount and no supplied target amount.' );
			}
		} elseif ( in_array( $event['event_type'], $target_events, true ) ) {
			if ( null !== $event['source_amount_minor'] || ! self::positive_money( $event['target_amount_minor'] ) ) {
				return self::error( 'event_amount_invalid', 'This observation requires one positive target-currency amount and no supplied source amount.' );
			}
		}

		$effective_delta = 0;
		$rounding_adjustment = 0;
		$type = $event['event_type'];
		if ( 'refund_accrued' === $type || 'reversal_observed' === $type ) {
			$new_returned_source = $servicing['returned_source_minor'] + $event['source_amount_minor'];
			if ( $new_returned_source > $ledger['source_total_minor'] ) {
				return self::error( 'return_exceeds_original', 'Refunds and reversals together cannot exceed the immutable source ledger total.' );
			}
			$new_fee_return = self::fee_return_for_source( $new_returned_source, $ledger, $quote );
			$old_fee_return = self::fee_return_for_source( $servicing['returned_source_minor'], $ledger, $quote );
			$new_returned_target = self::convert_effective_amount( $new_returned_source, $rate, $quote );
			if ( is_wp_error( $new_fee_return ) || is_wp_error( $old_fee_return ) || is_wp_error( $new_returned_target ) ) {
				return self::error( 'return_conversion_invalid', 'The cumulative return cannot be converted safely at the original locked rate.' );
			}
			$new_returned_target = self::safe_add( $new_returned_target, $new_fee_return['effect_minor'] );
			if ( is_wp_error( $new_returned_target ) ) {
				return $new_returned_target;
			}
			if ( $new_returned_target < $servicing['returned_target_minor'] ) {
				return self::error( 'return_not_monotonic', 'A larger cumulative source return cannot reduce the recognized target liability.' );
			}
			$effective_delta = $new_returned_target - $servicing['returned_target_minor'];
			$independent = self::convert_effective_amount( $event['source_amount_minor'], $rate, $quote );
			$fee_delta = $new_fee_return['effect_minor'] - $old_fee_return['effect_minor'];
			if ( is_wp_error( $independent ) ) {
				return $independent;
			}
			$rounding_adjustment = $effective_delta - $independent - $fee_delta;
			$servicing['returned_source_minor'] = $new_returned_source;
			$servicing['returned_target_minor'] = $new_returned_target;
			$servicing['fee_refunded_target_minor'] = $new_fee_return['refunded_minor'];
			if ( 'refund_accrued' === $type ) {
				$servicing['refunded_source_minor'] += $event['source_amount_minor'];
				$servicing['refunded_target_minor'] += $effective_delta;
			} else {
				$servicing['reversed_source_minor'] += $event['source_amount_minor'];
				$servicing['reversed_target_minor'] += $effective_delta;
			}
		} elseif ( 'refund_settled' === $type ) {
			if ( $servicing['refund_settled_target_minor'] + $event['target_amount_minor'] > $servicing['returned_target_minor'] ) {
				return self::error( 'refund_settlement_exceeds_due', 'Observed refund settlement cannot exceed the recognized return liability.' );
			}
			$servicing['refund_settled_target_minor'] += $event['target_amount_minor'];
			$effective_delta = $event['target_amount_minor'];
		} elseif ( 'dispute_opened' === $type ) {
			$new_source = $servicing['open_dispute_source_minor'] + $event['source_amount_minor'];
			if ( $new_source > $ledger['source_total_minor'] ) {
				return self::error( 'dispute_exceeds_original', 'Open dispute exposure cannot exceed the immutable source ledger total.' );
			}
			$new_target = self::convert_effective_amount( $new_source, $rate, $quote );
			$independent = self::convert_effective_amount( $event['source_amount_minor'], $rate, $quote );
			if ( is_wp_error( $new_target ) || is_wp_error( $independent ) ) {
				return self::error( 'dispute_conversion_invalid', 'Dispute exposure cannot be converted safely at the original locked rate.' );
			}
			$effective_delta = $new_target - $servicing['open_dispute_target_minor'];
			$rounding_adjustment = $effective_delta - $independent;
			$servicing['open_dispute_source_minor'] = $new_source;
			$servicing['open_dispute_target_minor'] = $new_target;
		} elseif ( 'dispute_closed' === $type ) {
			if ( $event['source_amount_minor'] > $servicing['open_dispute_source_minor'] ) {
				return self::error( 'dispute_close_exceeds_open', 'A dispute close cannot exceed current open source exposure.' );
			}
			$new_source = $servicing['open_dispute_source_minor'] - $event['source_amount_minor'];
			$new_target = self::convert_effective_amount( $new_source, $rate, $quote );
			$independent = self::convert_effective_amount( $event['source_amount_minor'], $rate, $quote );
			if ( is_wp_error( $new_target ) || is_wp_error( $independent ) ) {
				return self::error( 'dispute_conversion_invalid', 'Dispute exposure cannot be converted safely at the original locked rate.' );
			}
			$effective_delta = $new_target - $servicing['open_dispute_target_minor'];
			$rounding_adjustment = $effective_delta + $independent;
			$servicing['open_dispute_source_minor'] = $new_source;
			$servicing['open_dispute_target_minor'] = $new_target;
		} elseif ( 'chargeback_observed' === $type ) {
			$new_source = $servicing['charged_back_source_minor'] + $event['source_amount_minor'];
			if ( $new_source > $ledger['source_total_minor'] ) {
				return self::error( 'chargeback_exceeds_original', 'Chargeback exposure cannot exceed the immutable source ledger total.' );
			}
			$new_target = self::convert_effective_amount( $new_source, $rate, $quote );
			$independent = self::convert_effective_amount( $event['source_amount_minor'], $rate, $quote );
			if ( is_wp_error( $new_target ) || is_wp_error( $independent ) ) {
				return self::error( 'chargeback_conversion_invalid', 'Chargeback exposure cannot be converted safely at the original locked rate.' );
			}
			$effective_delta = $new_target - $servicing['charged_back_target_minor'];
			$rounding_adjustment = $effective_delta - $independent;
			$servicing['charged_back_source_minor'] = $new_source;
			$servicing['charged_back_target_minor'] = $new_target;
		} elseif ( 'chargeback_recovered' === $type ) {
			if ( $servicing['chargeback_recovered_target_minor'] + $event['target_amount_minor'] > $servicing['charged_back_target_minor'] ) {
				return self::error( 'chargeback_recovery_exceeds_exposure', 'Chargeback recovery cannot exceed observed target-currency exposure.' );
			}
			$servicing['chargeback_recovered_target_minor'] += $event['target_amount_minor'];
			$effective_delta = $event['target_amount_minor'];
		} elseif ( 'supplier_settlement_observed' === $type ) {
			$supplier_payable = self::supplier_payable_target( $ledger );
			if ( $servicing['supplier_settled_target_minor'] + $event['target_amount_minor'] > $supplier_payable ) {
				return self::error( 'supplier_settlement_exceeds_payable', 'Observed supplier settlement cannot exceed the converted supplier payable.' );
			}
			$servicing['supplier_settled_target_minor'] += $event['target_amount_minor'];
			$effective_delta = $event['target_amount_minor'];
		}
		$servicing['cumulative_rounding_adjustment_minor'] += $rounding_adjustment;
		$expected_event = $event;
		$expected_event['effective_target_delta_minor'] = $effective_delta;
		$expected_event['rounding_adjustment_minor'] = $rounding_adjustment;
		return array( 'servicing' => $servicing, 'event' => $expected_event );
	}

	private static function empty_servicing() {
		return array(
			'returned_source_minor'                => 0,
			'returned_target_minor'                => 0,
			'refunded_source_minor'                => 0,
			'refunded_target_minor'                => 0,
			'reversed_source_minor'                => 0,
			'reversed_target_minor'                => 0,
			'refund_settled_target_minor'          => 0,
			'open_dispute_source_minor'            => 0,
			'open_dispute_target_minor'            => 0,
			'charged_back_source_minor'            => 0,
			'charged_back_target_minor'            => 0,
			'chargeback_recovered_target_minor'    => 0,
			'supplier_settled_target_minor'        => 0,
			'fee_refunded_target_minor'            => 0,
			'cumulative_rounding_adjustment_minor' => 0,
		);
	}

	private static function derive_liabilities( $ledger, $servicing, $quote ) {
		$supplier_payable = self::supplier_payable_target( $ledger );
		return array(
			'supplier_payable_target_minor'             => $supplier_payable,
			'supplier_payable_outstanding_target_minor' => max( 0, $supplier_payable - $servicing['supplier_settled_target_minor'] ),
			'customer_refund_due_target_minor'          => max( 0, $servicing['returned_target_minor'] - $servicing['refund_settled_target_minor'] ),
			'dispute_exposure_target_minor'             => $servicing['open_dispute_target_minor'],
			'chargeback_exposure_target_minor'          => max( 0, $servicing['charged_back_target_minor'] - $servicing['chargeback_recovered_target_minor'] ),
			'fx_fee_liability_target_minor'             => max( 0, $quote['fee_minor'] - $servicing['fee_refunded_target_minor'] ),
			'rounding_exposure_target_minor'            => $servicing['cumulative_rounding_adjustment_minor'],
			'rate_policy'                               => 'original_locked_rate',
		);
	}

	private static function supplier_payable_target( $ledger ) {
		foreach ( $ledger['lines'] as $line ) {
			if ( 'supplier_payable' === $line['code'] ) {
				return max( 0, $line['target_amount_minor'] );
			}
		}
		return $ledger['target_total_minor'];
	}

	private static function fee_return_for_source( $source_amount, $ledger, $quote ) {
		if ( 0 === $quote['fee_minor'] || 'none' === $quote['fee_application'] ) {
			return array( 'effect_minor' => 0, 'refunded_minor' => 0 );
		}
		$pro_rata = self::mul_div_round( $source_amount, $quote['fee_minor'], $ledger['source_total_minor'] );
		if ( is_wp_error( $pro_rata ) ) {
			return $pro_rata;
		}
		if ( 'deduct_from_target' === $quote['fee_application'] ) {
			return array( 'effect_minor' => -$pro_rata, 'refunded_minor' => 0 );
		}
		if ( 'pro_rata' === $quote['fee_refund_policy'] ) {
			return array( 'effect_minor' => $pro_rata, 'refunded_minor' => $pro_rata );
		}
		return array( 'effect_minor' => 0, 'refunded_minor' => 0 );
	}

	private static function convert_effective_amount( $amount, $rate, $quote ) {
		$ratio = self::effective_ratio( $rate, $quote );
		return is_wp_error( $ratio ) ? $ratio : self::convert_signed( $amount, $ratio['numerator'], $ratio['denominator'] );
	}

	private static function effective_ratio( $rate, $quote ) {
		$spread_factor = 10000;
		if ( 'add_to_quote' === $quote['spread_application'] ) {
			$spread_factor += $quote['spread_bps'];
		} elseif ( 'deduct_from_quote' === $quote['spread_application'] ) {
			$spread_factor -= $quote['spread_bps'];
		}
		$numerator = $rate['numerator'] * $spread_factor;
		$denominator = $rate['denominator'] * 10000;
		$gcd = self::gcd( $numerator, $denominator );
		$numerator = intdiv( $numerator, $gcd );
		$denominator = intdiv( $denominator, $gcd );
		if ( $numerator < 1 || $denominator < 1 || $numerator > 2000000000 || $denominator > 2000000000 ) {
			return self::error( 'effective_rate_invalid', 'Spread-adjusted rational rate exceeds the deterministic integer arithmetic boundary.' );
		}
		return array( 'numerator' => $numerator, 'denominator' => $denominator );
	}

	private static function convert_signed( $amount, $numerator, $denominator ) {
		if ( ! self::signed_money( $amount ) || ! is_int( $numerator ) || $numerator < 1 || ! is_int( $denominator ) || $denominator < 1 ) {
			return self::error( 'conversion_input_invalid', 'Conversion requires signed bounded minor units and a positive rational rate.' );
		}
		$sign = $amount < 0 ? -1 : 1;
		$rounded = self::mul_div_round( abs( $amount ), $numerator, $denominator );
		if ( is_wp_error( $rounded ) ) {
			return $rounded;
		}
		return $sign * $rounded;
	}

	/** Exact half-up a*b/d without overflowing an intermediate product. */
	private static function mul_div_round( $a, $b, $d ) {
		if ( ! is_int( $a ) || ! is_int( $b ) || ! is_int( $d ) || $a < 0 || $b < 0 || $d < 1 || $a > self::MAX_MONEY_MINOR || $b > self::MAX_MONEY_MINOR ) {
			return self::error( 'arithmetic_input_invalid', 'Bounded non-negative integer multiplication and positive division are required.' );
		}
		$acc_q = 0;
		$acc_r = 0;
		$term_q = intdiv( $a, $d );
		$term_r = $a % $d;
		$multiplier = $b;
		while ( $multiplier > 0 ) {
			if ( 1 === ( $multiplier % 2 ) ) {
				if ( $term_q > self::MAX_MONEY_MINOR - $acc_q ) {
					return self::error( 'arithmetic_overflow', 'Converted integer money exceeds the supported boundary.' );
				}
				$acc_q += $term_q;
				$acc_r += $term_r;
				if ( $acc_r >= $d ) {
					$acc_r -= $d;
					$acc_q++;
				}
				if ( $acc_q > self::MAX_MONEY_MINOR ) {
					return self::error( 'arithmetic_overflow', 'Converted integer money exceeds the supported boundary.' );
				}
			}
			$multiplier = intdiv( $multiplier, 2 );
			if ( 0 === $multiplier ) {
				break;
			}
			if ( $term_q > intdiv( self::MAX_MONEY_MINOR, 2 ) ) {
				return self::error( 'arithmetic_overflow', 'Converted integer money exceeds the supported boundary.' );
			}
			$term_q *= 2;
			$term_r *= 2;
			if ( $term_r >= $d ) {
				$term_r -= $d;
				$term_q++;
			}
			if ( $term_q > self::MAX_MONEY_MINOR ) {
				return self::error( 'arithmetic_overflow', 'Converted integer money exceeds the supported boundary.' );
			}
		}
		if ( $acc_r >= intdiv( $d, 2 ) + ( $d % 2 ) ) {
			$acc_q++;
		}
		return $acc_q <= self::MAX_MONEY_MINOR ? $acc_q : self::error( 'arithmetic_overflow', 'Rounded integer money exceeds the supported boundary.' );
	}

	private static function safe_add( $left, $right ) {
		if ( ! is_int( $left ) || ! is_int( $right ) || $right > 0 && $left > self::MAX_MONEY_MINOR - $right || $right < 0 && $left < -self::MAX_MONEY_MINOR - $right ) {
			return self::error( 'arithmetic_overflow', 'Integer-money addition exceeds the supported boundary.' );
		}
		$result = $left + $right;
		return self::signed_money( $result ) ? $result : self::error( 'arithmetic_overflow', 'Integer-money addition exceeds the supported boundary.' );
	}

	private static function gcd( $left, $right ) {
		while ( 0 !== $right ) {
			$temp = $left % $right;
			$left = $right;
			$right = $temp;
		}
		return max( 1, abs( $left ) );
	}

	private static function currency_exponent( $currency, $exponent ) {
		return is_string( $currency ) && isset( self::CURRENCY_EXPONENTS[ $currency ] ) && self::CURRENCY_EXPONENTS[ $currency ] === $exponent;
	}

	private static function money( $value ) {
		return is_int( $value ) && $value >= 0 && $value <= self::MAX_MONEY_MINOR;
	}

	private static function positive_money( $value ) {
		return self::money( $value ) && $value > 0;
	}

	private static function signed_money( $value ) {
		return is_int( $value ) && $value >= -self::MAX_MONEY_MINOR && $value <= self::MAX_MONEY_MINOR;
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function public_ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private static function utc_timestamp( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01])T(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]Z$/', $value ) ) {
			return false;
		}
		$timestamp = strtotime( $value );
		return false !== $timestamp && gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ) === $value ? $timestamp : false;
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && array_keys( $value ) === $keys;
	}

	private static function is_list( $value ) {
		return is_array( $value ) && ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
	}

	private static function canonical_digest( $value ) {
		$normalized = self::canonicalize( $value );
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $normalized, JSON_UNESCAPED_SLASHES ) : json_encode( $normalized, JSON_UNESCAPED_SLASHES );
		return hash( 'sha256', (string) $json );
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( self::is_list( $value ) ) {
			return array_map( array( __CLASS__, 'canonicalize' ), $value );
		}
		ksort( $value, SORT_STRING );
		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::canonicalize( $child );
		}
		return $value;
	}

	private static function contains_sensitive_material( $value, $key = '' ) {
		if ( false !== $value && null !== $value && is_string( $key ) && 1 === preg_match( '/(?:api[_-]?key|secret|password|credential|access[_-]?token|refresh[_-]?token|card[_-]?number|passport|cvv|\bpan\b)/i', $key ) ) {
			return true;
		}
		if ( is_string( $value ) && ( false !== strpos( $value, '@' ) || 1 === preg_match( '/(?:https?:\/\/|Bearer\s|sk-[A-Za-z0-9])/i', $value ) ) ) {
			return true;
		}
		if ( ! is_array( $value ) ) {
			return false;
		}
		foreach ( $value as $child_key => $child ) {
			if ( self::contains_sensitive_material( $child, (string) $child_key ) ) {
				return true;
			}
		}
		return false;
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_commerce_fx_' . $suffix, $message, array( 'status' => 409 ) );
	}
}
