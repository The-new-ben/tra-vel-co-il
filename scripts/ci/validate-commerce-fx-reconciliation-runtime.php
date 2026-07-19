<?php
/**
 * Adversarial runtime gate for private sandbox FX reconciliation.
 */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

$commerce = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/';
require_once $commerce . 'class-tra-vel-commerce-fx-reconciliation-policy.php';
require_once $commerce . 'class-tra-vel-commerce-fx-reconciliation-state-machine.php';

$fx_assertions = 0;

function fx_expect( $condition, $message ) {
	global $fx_assertions;
	$fx_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, 'Tra-Vel FX reconciliation runtime failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function fx_expect_error( $value, $code, $message ) {
	fx_expect( is_wp_error( $value ) && $code === $value->get_error_code(), $message . ( is_wp_error( $value ) ? ' (received ' . $value->get_error_code() . ')' : ' (no error returned)' ) );
}

function fx_digest( $seed ) { return hash( 'sha256', 'tra-vel-fx-sandbox-' . $seed ); }

function fx_rate( $overrides = array() ) {
	$rate = array(
		'source_rate_ref'      => 'fxrate_sandbox_thb_ils_v1',
		'source_rate_version'  => 1,
		'source_rate_digest'   => '',
		'fixture_label'        => 'SIMULATED_THAI_SETTLEMENT_RATE_V1',
		'base_currency'        => 'THB',
		'quote_currency'       => 'ILS',
		'base_exponent'        => 2,
		'quote_exponent'       => 2,
		'numerator'            => 21,
		'denominator'          => 200,
		'observed_at'          => '2026-07-19T10:00:00Z',
		'effective_at'         => '2026-07-19T10:01:00Z',
		'valid_until'          => '2026-07-19T13:00:00Z',
		'simulated'            => true,
		'real_provider_response'=> false,
	);
	foreach ( $overrides as $key => $value ) { $rate[ $key ] = $value; }
	return Tra_Vel_Commerce_Fx_Reconciliation_Policy::seal_source_rate( $rate );
}

function fx_quote( $rate, $overrides = array() ) {
	$quote = array(
		'quote_ref'              => 'fxquote_' . substr( fx_digest( 'quote-ref' ), 0, 32 ),
		'quote_version'          => 1,
		'quote_digest'           => '',
		'source_rate_digest'     => $rate['source_rate_digest'],
		'locked_at'              => '2026-07-19T11:00:00Z',
		'locked_until'           => '2026-07-19T12:30:00Z',
		'direction'              => 'base_to_quote',
		'spread_bps'             => 125,
		'spread_application'     => 'deduct_from_quote',
		'fee_minor'              => 7,
		'fee_application'        => 'add_to_target',
		'rounding_mode'          => 'half_up',
		'residual_policy'        => 'largest_absolute_then_code',
		'refund_rate_policy'     => 'original_locked_rate',
		'reversal_rate_policy'   => 'original_locked_rate',
		'dispute_rate_policy'    => 'original_locked_rate',
		'chargeback_rate_policy' => 'original_locked_rate',
		'fee_refund_policy'      => 'non_refundable',
	);
	foreach ( $overrides as $key => $value ) { $quote[ $key ] = $value; }
	return Tra_Vel_Commerce_Fx_Reconciliation_Policy::seal_locked_quote( $quote );
}

function fx_binding( $overrides = array() ) {
	$binding = array(
		'owner_scope_digest'        => fx_digest( 'owner' ),
		'order_ref'                 => 'tv_order_abcdefghijklmnop',
		'order_item_ref'            => 'tv_order_item_abcdefghijklmnop',
		'funds_flow_binding_digest' => fx_digest( 'funds-flow-binding' ),
		'idempotency_key_digest'    => fx_digest( 'initial-idempotency' ),
		'source_currency'           => 'THB',
		'source_exponent'           => 2,
		'target_currency'           => 'ILS',
		'target_exponent'           => 2,
		'ledger_code'               => 'settlement_obligation',
	);
	foreach ( $overrides as $key => $value ) { $binding[ $key ] = $value; }
	return $binding;
}

function fx_lines() {
	return array(
		array( 'code' => 'supplier_payable', 'source_amount_minor' => 8000 ),
		array( 'code' => 'commission_receivable', 'source_amount_minor' => 1000 ),
		array( 'code' => 'tax_reserve', 'source_amount_minor' => 1001 ),
	);
}

function fx_event( $sequence, $type, $clock, $source, $target, $seed ) {
	return array(
		'sequence'               => $sequence,
		'event_type'             => $type,
		'idempotency_key_digest' => fx_digest( 'event-idempotency-' . $seed ),
		'evidence_digest'        => fx_digest( 'event-evidence-' . $seed ),
		'occurred_at'            => $clock,
		'source_amount_minor'    => $source,
		'target_amount_minor'    => $target,
	);
}

function fx_schema_walk( $node, $path = '#' ) {
	if ( ! is_array( $node ) ) { return; }
	if ( isset( $node['type'] ) && 'object' === $node['type'] ) {
		fx_expect( array_key_exists( 'additionalProperties', $node ) && false === $node['additionalProperties'], $path . ' must reject unknown fields.' );
		fx_expect( isset( $node['properties'], $node['required'] ) && ! array_diff( array_keys( $node['properties'] ), $node['required'] ) && ! array_diff( $node['required'], array_keys( $node['properties'] ) ), $path . ' must require every declared field.' );
	}
	foreach ( $node as $key => $child ) { if ( is_array( $child ) ) { fx_schema_walk( $child, $path . '/' . $key ); } }
}

$schema_path = __DIR__ . '/../../plugin/tra-vel-agent-core/schemas/private/commerce-fx-reconciliation-record.schema.json';
$schema = json_decode( file_get_contents( $schema_path ), true );
fx_expect( is_array( $schema ) && JSON_ERROR_NONE === json_last_error(), 'Private FX schema must parse as JSON.' );
fx_expect( 'https://tra-vel.co.il/schemas/private/commerce-fx-reconciliation-record.schema.json' === $schema['$id'], 'Private FX schema must keep its canonical ID.' );
fx_expect( false === $schema['additionalProperties'] && 'sandbox' === $schema['properties']['environment']['const'], 'Private FX schema must be root-closed and sandbox-only.' );
fx_schema_walk( $schema );

$now = strtotime( '2026-07-19T12:00:00Z' );
$rate = fx_rate();
$quote = fx_quote( $rate );
$initial = Tra_Vel_Commerce_Fx_Reconciliation_Policy::create_initial_snapshot( fx_binding(), $rate, $quote, fx_lines(), $now );
fx_expect( is_array( $initial ), 'A sealed simulated THB-to-ILS settlement conversion must initialize' . ( is_wp_error( $initial ) ? ': ' . $initial->get_error_code() . ' / ' . $initial->get_error_message() : '.' ) );
fx_expect( 'THB' === $initial['source_currency'] && 2 === $initial['source_exponent'] && 'ILS' === $initial['target_currency'] && 2 === $initial['target_exponent'], 'Original THB and target ILS currency/exponent facts must both remain explicit.' );
fx_expect( true === $initial['source_rate']['simulated'] && false === $initial['source_rate']['real_provider_response'] && 0 === strpos( $initial['source_rate']['fixture_label'], 'SIMULATED_' ), 'The deterministic rate must be unmistakably simulated and deny provider authority.' );
fx_expect( 10001 === $initial['ledger']['source_total_minor'] && 1050 === $initial['ledger']['market_target_total_minor'] && 1037 === $initial['ledger']['target_before_fee_minor'] && 1044 === $initial['ledger']['target_total_minor'], 'Market rate, 125 bps deduction, and seven-minor-unit target fee must replay as exact integer totals.' );
fx_expect( -1 === $initial['ledger']['rounding_residual_minor'] && 'supplier_payable' === $initial['ledger']['residual_allocation_code'] && 0 === $initial['ledger']['unallocated_residual_minor'], 'A one-unit component residual must be allocated deterministically to the largest source line.' );
$target_line_sum = 0;
foreach ( $initial['ledger']['lines'] as $line ) { $target_line_sum += $line['target_amount_minor']; }
fx_expect( $initial['ledger']['target_before_fee_minor'] === $target_line_sum, 'Converted components must sum exactly after residual allocation.' );
fx_expect( 829 === $initial['liabilities']['supplier_payable_target_minor'] && 829 === $initial['liabilities']['supplier_payable_outstanding_target_minor'], 'Supplier payable must come from its exact converted component, not the customer total.' );
fx_expect( is_array( Tra_Vel_Commerce_Fx_Reconciliation_Policy::validate_snapshot( $initial, $now ) ), 'The independent policy must replay the sealed initial record.' );

/* Direction, freshness, digest, shape, and overflow adversaries. */
$inverted_rate = fx_rate( array( 'base_currency' => 'ILS', 'quote_currency' => 'THB' ) );
$inverted_quote = fx_quote( $inverted_rate );
fx_expect_error( Tra_Vel_Commerce_Fx_Reconciliation_Policy::create_initial_snapshot( fx_binding(), $inverted_rate, $inverted_quote, fx_lines(), $now ), 'tra_vel_commerce_fx_currency_direction_invalid', 'A caller-bound THB-to-ILS conversion must not infer or accept the inverse direction.' );
$stale_rate = fx_rate( array( 'valid_until' => '2026-07-19T12:00:00Z' ) );
$stale_quote = fx_quote( $stale_rate, array( 'locked_until' => '2026-07-19T12:00:00Z' ) );
fx_expect_error( Tra_Vel_Commerce_Fx_Reconciliation_Policy::create_initial_snapshot( fx_binding(), $stale_rate, $stale_quote, fx_lines(), $now ), 'tra_vel_commerce_fx_source_rate_stale', 'A rate expiring at the creation clock must fail closed.' );
$tampered_rate = $rate;
$tampered_rate['numerator']++;
fx_expect_error( Tra_Vel_Commerce_Fx_Reconciliation_Policy::create_initial_snapshot( fx_binding(), $tampered_rate, $quote, fx_lines(), $now ), 'tra_vel_commerce_fx_source_rate_digest_invalid', 'A changed rational rate must fail its source digest.' );
$tampered_quote = $quote;
$tampered_quote['spread_bps']++;
fx_expect_error( Tra_Vel_Commerce_Fx_Reconciliation_Policy::create_initial_snapshot( fx_binding(), $rate, $tampered_quote, fx_lines(), $now ), 'tra_vel_commerce_fx_locked_quote_digest_invalid', 'A changed spread must fail the locked-quote digest.' );
$unknown = $initial;
$unknown['live_rate'] = 0.105;
fx_expect_error( Tra_Vel_Commerce_Fx_Reconciliation_Policy::validate_snapshot( $unknown, $now ), 'tra_vel_commerce_fx_record_shape_invalid', 'Unknown root fields must fail the closed server contract.' );
$sensitive = $initial;
$sensitive['api_key'] = 'forbidden';
fx_expect_error( Tra_Vel_Commerce_Fx_Reconciliation_Policy::validate_snapshot( $sensitive, $now ), 'tra_vel_commerce_fx_sensitive_material_rejected', 'Credential-shaped material must fail before parsing.' );
$overflow_rate = fx_rate( array( 'numerator' => 100000, 'denominator' => 1 ) );
$overflow_quote = fx_quote( $overflow_rate, array( 'spread_bps' => 0, 'spread_application' => 'none', 'fee_minor' => 0, 'fee_application' => 'none' ) );
$overflow_lines = array( array( 'code' => 'supplier_payable', 'source_amount_minor' => 1000000000000 ) );
fx_expect_error( Tra_Vel_Commerce_Fx_Reconciliation_Policy::create_initial_snapshot( fx_binding(), $overflow_rate, $overflow_quote, $overflow_lines, $now ), 'tra_vel_commerce_fx_ledger_conversion_overflow', 'A mathematically valid but out-of-domain conversion must fail before integer overflow.' );

/* Pro-rata fee servicing converges to the original target total without drift. */
$pro_rata_quote = fx_quote( $rate, array( 'fee_refund_policy' => 'pro_rata' ) );
$pro_rata_initial = Tra_Vel_Commerce_Fx_Reconciliation_Policy::create_initial_snapshot( fx_binding( array( 'idempotency_key_digest' => fx_digest( 'pro-rata-initial' ) ) ), $rate, $pro_rata_quote, fx_lines(), $now );
fx_expect( is_array( $pro_rata_initial ), 'A pro-rata fee quote must initialize under the same explicit locked-rate contract.' );
$pro_rata_first = Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $pro_rata_initial, fx_event( 1, 'refund_accrued', '2026-07-19T13:30:00Z', 3333, null, 'pro-rata-one' ), strtotime( '2026-07-19T14:00:00Z' ) );
fx_expect( is_array( $pro_rata_first ) && 2 === $pro_rata_first['servicing']['fee_refunded_target_minor'] && 348 === $pro_rata_first['servicing']['returned_target_minor'], 'A partial pro-rata return must convert principal and fee separately with half-up integer rounding.' );
$pro_rata_full = Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $pro_rata_first, fx_event( 2, 'refund_accrued', '2026-07-19T13:31:00Z', 6668, null, 'pro-rata-two' ), strtotime( '2026-07-19T14:00:00Z' ) );
fx_expect( is_array( $pro_rata_full ) && $pro_rata_full['ledger']['target_total_minor'] === $pro_rata_full['servicing']['returned_target_minor'] && 7 === $pro_rata_full['servicing']['fee_refunded_target_minor'], 'Fragmented pro-rata returns must converge exactly to the original fee-inclusive target total.' );

/* Cumulative partial returns use the original locked rate after quote expiry. */
$refund_one = fx_event( 1, 'refund_accrued', '2026-07-19T13:30:00Z', 3333, null, 'refund-one' );
$after_refund_one = Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $initial, $refund_one, strtotime( '2026-07-19T14:00:00Z' ) );
fx_expect( is_array( $after_refund_one ) && 3333 === $after_refund_one['servicing']['refunded_source_minor'], 'A partial refund after quote expiry must still use the explicitly frozen original-rate policy.' );
fx_expect( 'original_locked_rate' === $after_refund_one['liabilities']['rate_policy'] && $after_refund_one['servicing']['returned_target_minor'] === $after_refund_one['liabilities']['customer_refund_due_target_minor'], 'Partial refund conversion must create an exact target-currency customer liability.' );
$duplicate = Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $after_refund_one, $refund_one, strtotime( '2026-07-19T14:00:00Z' ) );
fx_expect( $after_refund_one === $duplicate, 'An exact duplicate idempotency replay must return the existing snapshot byte-for-byte.' );
$conflict = $refund_one;
$conflict['evidence_digest'] = fx_digest( 'different-evidence' );
fx_expect_error( Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $after_refund_one, $conflict, strtotime( '2026-07-19T14:00:00Z' ) ), 'tra_vel_commerce_fx_idempotency_conflict', 'Reusing an idempotency digest for different evidence must fail.' );
$out_of_order = fx_event( 3, 'refund_accrued', '2026-07-19T13:31:00Z', 100, null, 'out-of-order' );
fx_expect_error( Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $after_refund_one, $out_of_order, strtotime( '2026-07-19T14:00:00Z' ) ), 'tra_vel_commerce_fx_event_out_of_order', 'A skipped event sequence must fail closed.' );

$refund_two = fx_event( 2, 'refund_accrued', '2026-07-19T13:31:00Z', 3333, null, 'refund-two' );
$after_refund_two = Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $after_refund_one, $refund_two, strtotime( '2026-07-19T14:00:00Z' ) );
$reversal = fx_event( 3, 'reversal_observed', '2026-07-19T13:32:00Z', 3335, null, 'reversal-final' );
$returned = Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $after_refund_two, $reversal, strtotime( '2026-07-19T14:00:00Z' ) );
fx_expect( is_array( $returned ) && 10001 === $returned['servicing']['returned_source_minor'] && 1037 === $returned['servicing']['returned_target_minor'], 'Fragmented partial refund plus reversal must converge exactly to one full locked-rate conversion, excluding the non-refundable fee.' );
fx_expect( $returned['servicing']['refunded_target_minor'] + $returned['servicing']['reversed_target_minor'] === $returned['servicing']['returned_target_minor'], 'Refund and reversal target deltas must reconcile to the cumulative return.' );
fx_expect( 7 === $returned['liabilities']['fx_fee_liability_target_minor'] && 0 === $returned['servicing']['fee_refunded_target_minor'], 'A non-refundable quote fee must remain separate from returned principal.' );

$refund_settled_amount = intdiv( $returned['servicing']['returned_target_minor'], 2 );
$refund_settled = fx_event( 4, 'refund_settled', '2026-07-19T13:33:00Z', null, $refund_settled_amount, 'refund-settled' );
$settled_return = Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $returned, $refund_settled, strtotime( '2026-07-19T14:00:00Z' ) );
fx_expect( $settled_return['liabilities']['customer_refund_due_target_minor'] === 1037 - $refund_settled_amount, 'Observed partial refund settlement must reduce only the target-currency refund liability.' );

/* Dispute, chargeback, recovery, and supplier settlement remain distinct exposures. */
$dispute = fx_event( 5, 'dispute_opened', '2026-07-19T13:34:00Z', 2001, null, 'dispute-open' );
$disputed = Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $settled_return, $dispute, strtotime( '2026-07-19T14:00:00Z' ) );
fx_expect( $disputed['liabilities']['dispute_exposure_target_minor'] > 0, 'A source-currency dispute must expose its exact locked-rate target liability.' );
$dispute_close = fx_event( 6, 'dispute_closed', '2026-07-19T13:35:00Z', 1000, null, 'dispute-close' );
$partly_disputed = Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $disputed, $dispute_close, strtotime( '2026-07-19T14:00:00Z' ) );
fx_expect( 1001 === $partly_disputed['servicing']['open_dispute_source_minor'], 'A partial dispute close must preserve the remaining source exposure.' );
$chargeback = fx_event( 7, 'chargeback_observed', '2026-07-19T13:36:00Z', 1000, null, 'chargeback' );
$charged = Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $partly_disputed, $chargeback, strtotime( '2026-07-19T14:00:00Z' ) );
fx_expect( $charged['liabilities']['chargeback_exposure_target_minor'] > 0, 'Chargeback exposure must be tracked independently from open dispute exposure.' );
$recovery_amount = intdiv( $charged['servicing']['charged_back_target_minor'], 2 );
$recovery = fx_event( 8, 'chargeback_recovered', '2026-07-19T13:37:00Z', null, $recovery_amount, 'chargeback-recovery' );
$recovered = Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $charged, $recovery, strtotime( '2026-07-19T14:00:00Z' ) );
fx_expect( $recovered['liabilities']['chargeback_exposure_target_minor'] === $charged['servicing']['charged_back_target_minor'] - $recovery_amount, 'Recovery must reduce only observed chargeback exposure.' );
$supplier_settlement = fx_event( 9, 'supplier_settlement_observed', '2026-07-19T13:38:00Z', null, 400, 'supplier-settlement' );
$settled = Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $recovered, $supplier_settlement, strtotime( '2026-07-19T14:00:00Z' ) );
fx_expect( 429 === $settled['liabilities']['supplier_payable_outstanding_target_minor'], 'Supplier settlement evidence must reduce only the exact converted supplier payable.' );
fx_expect( 10 === $settled['version'] && 9 === $settled['last_event_sequence'] && 9 === count( $settled['event_history'] ), 'Every accepted evidence event must advance one immutable version and one sequence.' );

$changed_order = $settled;
$changed_order['order_ref'] = 'tv_order_qrstuvwxyzabcdef';
$changed_order = Tra_Vel_Commerce_Fx_Reconciliation_Policy::seal_snapshot( $changed_order );
fx_expect_error( Tra_Vel_Commerce_Fx_Reconciliation_Policy::assert_successor( $recovered, $changed_order, strtotime( '2026-07-19T14:00:00Z' ) ), 'tra_vel_commerce_fx_immutable_field_changed', 'A successor cannot move the FX history to another order.' );

/* The private record has no live rate, network, processor, persistence, or PII path. */
$json = wp_json_encode( $settled );
foreach ( array( 'https://', 'Bearer ', 'sk-', '@', 'card_number', 'passport' ) as $forbidden ) {
	fx_expect( false === stripos( $json, $forbidden ), 'Private FX evidence must not contain forbidden live, credential, or personal material: ' . $forbidden . '.' );
}
fx_expect( true === $settled['sandbox_truth']['simulated_rate'] && false === $settled['sandbox_truth']['real_rate_provider_call'] && false === $settled['sandbox_truth']['real_processor_call'] && false === $settled['sandbox_truth']['real_supplier_payment'] && false === $settled['sandbox_truth']['real_settlement'], 'Every state must deny fabricated live-rate, processor, payment, and settlement authority.' );
$source = file_get_contents( $commerce . 'class-tra-vel-commerce-fx-reconciliation-policy.php' ) . file_get_contents( $commerce . 'class-tra-vel-commerce-fx-reconciliation-state-machine.php' );
foreach ( array( 'wp_remote_', 'curl_', 'fsockopen', 'stream_socket_client', 'file_get_contents(', 'file_put_contents', '$wpdb' ) as $marker ) {
	fx_expect( false === strpos( $source, $marker ), 'FX runtime source must contain no network, file, or database side effect: ' . $marker . '.' );
}

echo 'Tra-Vel commerce FX reconciliation runtime passed (' . $fx_assertions . ' assertions; THB->ILS, residual, servicing, exposure, and adversarial paths).' . PHP_EOL;
