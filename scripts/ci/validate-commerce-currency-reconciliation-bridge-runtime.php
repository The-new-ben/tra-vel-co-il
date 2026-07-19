<?php
/**
 * Adversarial runtime gate for the private cross-ledger currency bridge.
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
require_once $commerce . 'class-tra-vel-commerce-funds-flow-policy.php';
require_once $commerce . 'class-tra-vel-commerce-funds-flow-state-machine.php';
require_once $commerce . 'class-tra-vel-commerce-fx-reconciliation-policy.php';
require_once $commerce . 'class-tra-vel-commerce-fx-reconciliation-state-machine.php';
require_once $commerce . 'class-tra-vel-commerce-currency-reconciliation-bridge-policy.php';
require_once $commerce . 'class-tra-vel-commerce-currency-reconciliation-bridge-factory.php';

$currency_bridge_assertions = 0;

function currency_bridge_expect( $condition, $message ) {
	global $currency_bridge_assertions;
	$currency_bridge_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, 'Tra-Vel currency bridge runtime failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function currency_bridge_expect_error( $value, $code, $message ) {
	currency_bridge_expect( is_wp_error( $value ) && $code === $value->get_error_code(), $message . ( is_wp_error( $value ) ? ' (received ' . $value->get_error_code() . ')' : ' (no error returned)' ) );
}

function currency_bridge_digest( $seed ) {
	return hash( 'sha256', 'tra-vel-currency-bridge-' . $seed );
}

function currency_bridge_funds_fixture( $collector = 'platform' ) {
	$created = '2026-07-19T12:00:00Z';
	$record = array(
		'contract_version'          => '1.0.0',
		'environment'               => 'sandbox',
		'funds_flow_ref'            => 'fflow_abcdefghijklmnop',
		'funds_flow_binding_digest' => '',
		'version'                   => 1,
		'previous_snapshot_digest'  => null,
		'snapshot_digest'           => '',
		'owner_scope_digest'        => currency_bridge_digest( 'owner' ),
		'order_ref'                 => 'tv_order_abcdefghijklmnop',
		'order_version'             => 1,
		'order_digest'              => currency_bridge_digest( 'order' ),
		'order_item_ref'            => 'tv_order_item_abcdefghijklmnop',
		'offer_digest'              => currency_bridge_digest( 'offer' ),
		'routing_binding_digest'    => currency_bridge_digest( 'routing' ),
		'provider_id'               => 'generic_bridge_supplier',
		'vertical'                  => 'flight',
		'commercial_model'          => 'direct_commission',
		'currency'                  => 'THB',
		'minor_unit_exponent'       => 2,
		'parties'                   => array(
			'merchant_of_record'         => $collector,
			'payment_collector'          => $collector,
			'supplier_payee'             => 'supplier',
			'commission_payee'           => 'platform',
			'refund_liability_party'     => $collector,
			'chargeback_liability_party' => $collector,
			'tax_remitter'               => $collector,
		),
		'commercial_terms'          => array(
			'rate_card_ref'                   => 'ratecard_abcdefghijklmnop',
			'rate_card_revision_digest'       => currency_bridge_digest( 'rate-card' ),
			'source_revision_digest'          => currency_bridge_digest( 'source-revision' ),
			'supplier_config_revision_digest' => currency_bridge_digest( 'supplier-config' ),
			'effective_at'                    => '2026-07-01T00:00:00Z',
			'valid_until'                     => '2027-07-19T00:00:00Z',
			'calculation_basis'               => 'gross_less_commission',
			'commission_bps'                  => 1000,
			'markup_amount_minor'             => 0,
			'tax_treatment'                   => 'included',
			'evidence_digest'                 => currency_bridge_digest( 'terms-evidence' ),
		),
		'pricing'                   => array(
			'customer_total_minor'        => 100000,
			'tax_minor'                   => 17000,
			'supplier_net_minor'          => 90000,
			'commissionable_minor'        => 100000,
			'commission_receivable_minor' => 10000,
			'platform_markup_minor'       => 0,
			'supplier_payable_minor'      => 90000,
			'platform_revenue_minor'      => 10000,
		),
		'payment'                   => array(
			'state'                     => 'not_started',
			'authority'                 => 'platform' === $collector ? 'platform_processor' : 'supplier_reported',
			'authorized_amount_minor'   => 0,
			'captured_amount_minor'     => 0,
			'refunded_amount_minor'     => 0,
			'disputed_amount_minor'     => 0,
			'charged_back_amount_minor' => 0,
			'processor_payment_ref'     => null,
			'latest_event_digest'       => null,
			'updated_at'                => $created,
		),
		'settlement'                => array(
			'state'                         => 'not_started',
			'supplier_due_minor'            => 0,
			'supplier_paid_minor'           => 0,
			'commission_due_minor'          => 0,
			'commission_received_minor'     => 0,
			'chargeback_recovery_due_minor' => 0,
			'chargeback_recovered_minor'    => 0,
			'latest_reconciliation_digest'  => null,
			'due_at'                       => null,
			'updated_at'                   => $created,
		),
		'liabilities'               => array(
			'customer_refund_due_minor'               => 0,
			'supplier_payable_outstanding_minor'      => 0,
			'commission_receivable_outstanding_minor' => 0,
			'chargeback_liability_minor'              => 0,
			'chargeback_liability_party'              => $collector,
		),
		'private_routes'            => array(
			'private_routing_record_ref' => 'tvr_binding_abcdefghijklmnop',
			'payment_route_ref'          => 'payroute_abcdefghijklmnop',
			'settlement_route_ref'       => 'setroute_abcdefghijklmnop',
			'supplier_payable_route_ref' => 'platform' === $collector ? 'payout_abcdefghijklmnop' : null,
		),
		'created_at'                => $created,
		'updated_at'                => $created,
		'last_event_sequence'       => 0,
		'sandbox_truth'             => array(
			'simulated'             => true,
			'real_processor_call'   => false,
			'real_customer_charge'  => false,
			'real_supplier_payment' => false,
			'real_settlement'       => false,
		),
		'data_boundary'             => array(
			'server_only'                  => true,
			'public_serialization_allowed' => false,
			'contains_private_locators'    => true,
			'raw_credentials_stored'       => false,
			'raw_payment_data_stored'      => false,
			'personal_data_stored'         => false,
		),
	);
	return Tra_Vel_Commerce_Funds_Flow_Policy::seal_snapshot( $record );
}

function currency_bridge_payment_event( $amount, $seed ) {
	return array(
		'amount_minor'          => $amount,
		'processor_payment_ref' => 'paytxn_abcdefghijklmnop',
		'evidence_digest'       => currency_bridge_digest( $seed ),
	);
}

function currency_bridge_settlement_event( $supplier, $seed, $due_at = null ) {
	return array(
		'supplier_amount_minor'   => $supplier,
		'commission_amount_minor' => 0,
		'recovery_amount_minor'   => 0,
		'evidence_digest'         => currency_bridge_digest( $seed ),
		'due_at'                  => $due_at,
	);
}

function currency_bridge_exponent( $currency ) {
	return 'JPY' === $currency ? 0 : ( 'KWD' === $currency ? 3 : 2 );
}

function currency_bridge_rate( $source = 'THB', $target = 'ILS' ) {
	$rate = array(
		'source_rate_ref'       => 'fxrate_sandbox_' . strtolower( $source ) . '_' . strtolower( $target ) . '_v1',
		'source_rate_version'   => 1,
		'source_rate_digest'    => '',
		'fixture_label'         => 'SIMULATED_' . $source . '_' . $target . '_BRIDGE_V1',
		'base_currency'         => $source,
		'quote_currency'        => $target,
		'base_exponent'         => currency_bridge_exponent( $source ),
		'quote_exponent'        => currency_bridge_exponent( $target ),
		'numerator'             => 'USD' === $source ? 37 : 21,
		'denominator'           => 'USD' === $source ? 10 : 200,
		'observed_at'           => '2026-07-19T10:00:00Z',
		'effective_at'          => '2026-07-19T10:01:00Z',
		'valid_until'           => '2026-07-19T18:00:00Z',
		'simulated'             => true,
		'real_provider_response'=> false,
	);
	return Tra_Vel_Commerce_Fx_Reconciliation_Policy::seal_source_rate( $rate );
}

function currency_bridge_quote( $rate ) {
	$quote = array(
		'quote_ref'              => 'fxquote_' . substr( currency_bridge_digest( $rate['base_currency'] . '-' . $rate['quote_currency'] . '-quote' ), 0, 32 ),
		'quote_version'          => 1,
		'quote_digest'           => '',
		'source_rate_digest'     => $rate['source_rate_digest'],
		'locked_at'              => '2026-07-19T11:00:00Z',
		'locked_until'           => '2026-07-19T17:00:00Z',
		'direction'              => 'base_to_quote',
		'spread_bps'             => 0,
		'spread_application'     => 'none',
		'fee_minor'              => 0,
		'fee_application'        => 'none',
		'rounding_mode'          => 'half_up',
		'residual_policy'        => 'largest_absolute_then_code',
		'refund_rate_policy'     => 'original_locked_rate',
		'reversal_rate_policy'   => 'original_locked_rate',
		'dispute_rate_policy'    => 'original_locked_rate',
		'chargeback_rate_policy' => 'original_locked_rate',
		'fee_refund_policy'      => 'non_refundable',
	);
	return Tra_Vel_Commerce_Fx_Reconciliation_Policy::seal_locked_quote( $quote );
}

function currency_bridge_fx_fixture( $funds, $overrides = array(), $lines = null, $ledger_code = 'settlement_obligation', $seed = 'primary', $source = 'THB', $target = 'ILS' ) {
	$rate = currency_bridge_rate( $source, $target );
	$quote = currency_bridge_quote( $rate );
	$binding = array(
		'owner_scope_digest'        => $funds['owner_scope_digest'],
		'order_ref'                 => $funds['order_ref'],
		'order_item_ref'            => $funds['order_item_ref'],
		'funds_flow_binding_digest' => $funds['funds_flow_binding_digest'],
		'idempotency_key_digest'    => currency_bridge_digest( 'fx-' . $seed ),
		'source_currency'           => $source,
		'source_exponent'           => currency_bridge_exponent( $source ),
		'target_currency'           => $target,
		'target_exponent'           => currency_bridge_exponent( $target ),
		'ledger_code'               => $ledger_code,
	);
	foreach ( $overrides as $key => $value ) {
		$binding[ $key ] = $value;
	}
	if ( null === $lines ) {
		$lines = array( array( 'code' => 'supplier_payable', 'source_amount_minor' => $funds['pricing']['supplier_payable_minor'] ) );
	}
	return Tra_Vel_Commerce_Fx_Reconciliation_Policy::create_initial_snapshot( $binding, $rate, $quote, $lines, strtotime( '2026-07-19T12:02:00Z' ) );
}

function currency_bridge_fx_event( $sequence, $amount, $clock, $seed ) {
	return array(
		'sequence'               => $sequence,
		'event_type'             => 'supplier_settlement_observed',
		'idempotency_key_digest' => currency_bridge_digest( 'fx-event-idempotency-' . $seed ),
		'evidence_digest'        => currency_bridge_digest( 'fx-event-evidence-' . $seed ),
		'occurred_at'            => $clock,
		'source_amount_minor'    => null,
		'target_amount_minor'    => $amount,
	);
}

function currency_bridge_binding( $funds, $fx, $now ) {
	$projection = Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::project_pair( $funds, $fx, $now );
	return is_wp_error( $projection ) ? $projection : $projection['binding'];
}

function currency_bridge_create( $funds, $fx, $now ) {
	$binding = currency_bridge_binding( $funds, $fx, $now );
	return is_wp_error( $binding ) ? $binding : Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Factory::create_snapshot( $funds, $fx, $binding, $now );
}

function currency_bridge_schema_walk( $node, $path = '#' ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( isset( $node['type'] ) && 'object' === $node['type'] ) {
		currency_bridge_expect( array_key_exists( 'additionalProperties', $node ) && false === $node['additionalProperties'], $path . ' must reject unknown fields.' );
		currency_bridge_expect( isset( $node['properties'], $node['required'] ) && ! array_diff( array_keys( $node['properties'] ), $node['required'] ) && ! array_diff( $node['required'], array_keys( $node['properties'] ) ), $path . ' must require every declared field.' );
	}
	foreach ( $node as $key => $child ) {
		if ( is_array( $child ) ) {
			currency_bridge_schema_walk( $child, $path . '/' . $key );
		}
	}
}

$schema_path = __DIR__ . '/../../plugin/tra-vel-agent-core/schemas/private/commerce-currency-reconciliation-bridge.schema.json';
$schema = json_decode( file_get_contents( $schema_path ), true );
currency_bridge_expect( is_array( $schema ) && JSON_ERROR_NONE === json_last_error(), 'Private currency bridge schema must parse as JSON.' );
currency_bridge_expect( 'https://tra-vel.co.il/schemas/private/commerce-currency-reconciliation-bridge.schema.json' === $schema['$id'], 'Private currency bridge schema must retain its canonical ID.' );
currency_bridge_expect( false === $schema['additionalProperties'] && 'sandbox' === $schema['properties']['environment']['const'], 'The bridge schema must be root-closed and sandbox-only.' );
currency_bridge_schema_walk( $schema );

$initial_now = strtotime( '2026-07-19T12:03:00Z' );
$funds = currency_bridge_funds_fixture();
$fx = currency_bridge_fx_fixture( $funds );
currency_bridge_expect( is_array( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $funds, $initial_now ) ), 'The complete source funds-flow fixture must validate independently.' );
currency_bridge_expect( is_array( Tra_Vel_Commerce_Fx_Reconciliation_Policy::validate_snapshot( $fx, $initial_now ) ), 'The complete FX fixture must validate independently.' );
$initial = currency_bridge_create( $funds, $fx, $initial_now );
currency_bridge_expect( is_array( $initial ), 'A complete current funds-flow and FX pair must materialize a bridge' . ( is_wp_error( $initial ) ? ': ' . $initial->get_error_code() . ' / ' . $initial->get_error_message() : '.' ) );
currency_bridge_expect( 'waiting_customer_funds' === $initial['overall_status'] && 'not_collected' === $initial['source_customer_funds']['status'], 'An untouched order must wait for source-currency customer funds.' );
currency_bridge_expect( 'not_accrued' === $initial['source_supplier_accrual']['status'] && 'not_started' === $initial['target_supplier_settlement']['status'], 'Source accrual and target settlement must remain independent initial states.' );
currency_bridge_expect( 'THB' === $initial['source_customer_funds']['currency'] && 'ILS' === $initial['target_supplier_settlement']['currency'], 'The bridge must preserve both currencies instead of relabeling one amount.' );
currency_bridge_expect( $funds['snapshot_digest'] === $initial['binding']['funds_flow_snapshot_digest'] && $fx['snapshot_digest'] === $initial['binding']['fx_snapshot_digest'], 'The exact current source snapshot digests must be bound.' );
currency_bridge_expect( $funds['routing_binding_digest'] === $initial['binding']['routing_binding_digest'] && $funds['order_digest'] === $initial['binding']['order_digest'], 'Order and private routing revisions must be part of the bridge identity.' );
currency_bridge_expect( $initial === currency_bridge_create( $funds, $fx, $initial_now ), 'The exact pair and injected clock must materialize deterministically.' );
currency_bridge_expect( is_array( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::validate_snapshot( $initial, $funds, $fx, $initial_now ) ), 'The independent bridge policy must replay the complete snapshot.' );

/* Customer funds, accrual, and target settlement advance as separate ledgers. */
$authorized = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $funds, 'authorize', currency_bridge_payment_event( 100000, 'authorize' ), strtotime( '2026-07-19T12:04:00Z' ) );
$partial_capture = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $authorized, 'capture', currency_bridge_payment_event( 50000, 'partial-capture' ), strtotime( '2026-07-19T12:05:00Z' ) );
$partial_bridge = currency_bridge_create( $partial_capture, $fx, strtotime( '2026-07-19T12:06:00Z' ) );
currency_bridge_expect( is_array( $partial_bridge ) && 'partially_collected' === $partial_bridge['source_customer_funds']['status'] && 'waiting_customer_funds' === $partial_bridge['overall_status'], 'Partial source capture must not masquerade as settlement readiness.' );
$captured = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $authorized, 'capture', currency_bridge_payment_event( 100000, 'capture' ), strtotime( '2026-07-19T12:05:00Z' ) );
$captured_bridge = currency_bridge_create( $captured, $fx, strtotime( '2026-07-19T12:06:00Z' ) );
currency_bridge_expect( is_array( $captured_bridge ) && 'collected' === $captured_bridge['source_customer_funds']['status'] && 'waiting_source_accrual' === $captured_bridge['overall_status'], 'Full customer collection must still wait for explicit source accrual.' );
$accrued = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_settlement_event( $captured, 'accrue', currency_bridge_settlement_event( 90000, 'accrue', '2026-07-20T00:00:00Z' ), strtotime( '2026-07-19T12:06:00Z' ) );
$ready = currency_bridge_create( $accrued, $fx, strtotime( '2026-07-19T12:07:00Z' ) );
currency_bridge_expect( is_array( $ready ) && 'accrued' === $ready['source_supplier_accrual']['status'] && 'ready_for_target_settlement' === $ready['overall_status'], 'Collected customer funds plus exact source accrual must become ready for target settlement.' );
currency_bridge_expect( 90000 === $ready['source_supplier_accrual']['supplier_payable_source_minor'] && 0 === $ready['source_supplier_accrual']['supplier_paid_source_minor'], 'Source payable may accrue but target payment must never populate a source paid amount.' );
$target_payable = $fx['liabilities']['supplier_payable_target_minor'];
$partial_target = Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $fx, currency_bridge_fx_event( 1, 4000, '2026-07-19T12:08:00Z', 'partial' ), strtotime( '2026-07-19T12:08:00Z' ) );
$partial_target_bridge = currency_bridge_create( $accrued, $partial_target, strtotime( '2026-07-19T12:09:00Z' ) );
currency_bridge_expect( is_array( $partial_target_bridge ) && 'partially_settled' === $partial_target_bridge['target_supplier_settlement']['status'] && 'partially_target_settled' === $partial_target_bridge['overall_status'], 'Observed target-currency partial settlement must remain a target ledger fact.' );
$final_target = Tra_Vel_Commerce_Fx_Reconciliation_State_Machine::apply_event( $partial_target, currency_bridge_fx_event( 2, $target_payable - 4000, '2026-07-19T12:10:00Z', 'final' ), strtotime( '2026-07-19T12:10:00Z' ) );
$settled_bridge = currency_bridge_create( $accrued, $final_target, strtotime( '2026-07-19T12:11:00Z' ) );
currency_bridge_expect( is_array( $settled_bridge ) && 'settled' === $settled_bridge['target_supplier_settlement']['status'] && 'target_settled' === $settled_bridge['overall_status'], 'Complete target evidence must settle only the target ledger.' );
currency_bridge_expect( $target_payable === $settled_bridge['target_supplier_settlement']['supplier_settled_target_minor'] && 0 === $settled_bridge['source_supplier_accrual']['supplier_paid_source_minor'] && 0 === $accrued['settlement']['supplier_paid_minor'], 'Target settlement must never be copied into source-currency paid fields.' );

/* Exact identity, current digest, currency, scope, and component adversaries. */
$stale_binding = $ready['binding'];
$stale_binding['funds_flow_snapshot_digest'] = currency_bridge_digest( 'stale-funds-snapshot' );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Factory::create_snapshot( $accrued, $fx, $stale_binding, strtotime( '2026-07-19T12:07:00Z' ) ), 'tra_vel_commerce_currency_bridge_factory_current_binding_mismatch', 'A caller cannot bind a stale current funds-flow digest.' );
$stale_fx_binding = $ready['binding'];
$stale_fx_binding['fx_snapshot_digest'] = currency_bridge_digest( 'stale-fx-snapshot' );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Factory::create_snapshot( $accrued, $fx, $stale_fx_binding, strtotime( '2026-07-19T12:07:00Z' ) ), 'tra_vel_commerce_currency_bridge_factory_current_binding_mismatch', 'A caller cannot bind a stale current FX digest.' );
$wrong_route_binding = $ready['binding'];
$wrong_route_binding['routing_binding_digest'] = currency_bridge_digest( 'wrong-route' );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Factory::create_snapshot( $accrued, $fx, $wrong_route_binding, strtotime( '2026-07-19T12:07:00Z' ) ), 'tra_vel_commerce_currency_bridge_factory_current_binding_mismatch', 'The exact private routing binding cannot change outside funds flow.' );
$wrong_order_binding = $ready['binding'];
$wrong_order_binding['order_digest'] = currency_bridge_digest( 'wrong-order' );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Factory::create_snapshot( $accrued, $fx, $wrong_order_binding, strtotime( '2026-07-19T12:07:00Z' ) ), 'tra_vel_commerce_currency_bridge_factory_current_binding_mismatch', 'The exact current order digest cannot be substituted.' );
$unknown_binding = $ready['binding'];
$unknown_binding['note'] = 'not-allowed';
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Factory::create_snapshot( $accrued, $fx, $unknown_binding, strtotime( '2026-07-19T12:07:00Z' ) ), 'tra_vel_commerce_currency_bridge_factory_context_invalid', 'Factory binding input must reject unknown fields.' );

$other_owner_fx = currency_bridge_fx_fixture( $funds, array( 'owner_scope_digest' => currency_bridge_digest( 'other-owner' ) ), null, 'settlement_obligation', 'other-owner' );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::project_pair( $funds, $other_owner_fx, $initial_now ), 'tra_vel_commerce_currency_bridge_cross_ledger_identity_mismatch', 'FX cannot move the payable to another owner.' );
$other_order_fx = currency_bridge_fx_fixture( $funds, array( 'order_ref' => 'tv_order_qrstuvwxyzabcdef' ), null, 'settlement_obligation', 'other-order' );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::project_pair( $funds, $other_order_fx, $initial_now ), 'tra_vel_commerce_currency_bridge_cross_ledger_identity_mismatch', 'FX cannot move the payable to another order.' );
$other_item_fx = currency_bridge_fx_fixture( $funds, array( 'order_item_ref' => 'tv_order_item_qrstuvwxyzabcdef' ), null, 'settlement_obligation', 'other-item' );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::project_pair( $funds, $other_item_fx, $initial_now ), 'tra_vel_commerce_currency_bridge_cross_ledger_identity_mismatch', 'FX cannot move the payable to another item.' );
$other_funds_fx = currency_bridge_fx_fixture( $funds, array( 'funds_flow_binding_digest' => currency_bridge_digest( 'other-funds-flow' ) ), null, 'settlement_obligation', 'other-funds-flow' );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::project_pair( $funds, $other_funds_fx, $initial_now ), 'tra_vel_commerce_currency_bridge_cross_ledger_identity_mismatch', 'FX cannot bind a different immutable funds flow.' );
$wrong_source_fx = currency_bridge_fx_fixture( $funds, array(), null, 'settlement_obligation', 'wrong-source', 'USD', 'ILS' );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::project_pair( $funds, $wrong_source_fx, $initial_now ), 'tra_vel_commerce_currency_bridge_currency_direction_mismatch', 'Funds-flow currency and exponent must equal the explicit FX source.' );

$supplier_collected = currency_bridge_funds_fixture( 'supplier' );
$supplier_collected_fx = currency_bridge_fx_fixture( $supplier_collected, array(), null, 'settlement_obligation', 'supplier-collected' );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::project_pair( $supplier_collected, $supplier_collected_fx, $initial_now ), 'tra_vel_commerce_currency_bridge_unsupported_collection_scope', 'Version one must reject supplier-collected and affiliate scope.' );
$wrong_component_fx = currency_bridge_fx_fixture( $funds, array(), array( array( 'code' => 'supplier_payable', 'source_amount_minor' => 89999 ) ), 'settlement_obligation', 'wrong-component' );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::project_pair( $funds, $wrong_component_fx, $initial_now ), 'tra_vel_commerce_currency_bridge_supplier_payable_component_mismatch', 'The FX supplier-payable source component cannot differ by one minor unit.' );
$mixed_component_fx = currency_bridge_fx_fixture( $funds, array(), array( array( 'code' => 'supplier_payable', 'source_amount_minor' => 90000 ), array( 'code' => 'tax_reserve', 'source_amount_minor' => 1 ) ), 'settlement_obligation', 'mixed-component' );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::project_pair( $funds, $mixed_component_fx, $initial_now ), 'tra_vel_commerce_currency_bridge_supplier_payable_component_mismatch', 'The narrow supplier-payable bridge cannot mix another converted component.' );
$customer_ledger_fx = currency_bridge_fx_fixture( $funds, array(), null, 'customer_pricing', 'customer-ledger' );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::project_pair( $funds, $customer_ledger_fx, $initial_now ), 'tra_vel_commerce_currency_bridge_supplier_payable_component_mismatch', 'The bridge cannot treat a customer-pricing conversion as a settlement obligation.' );

$source_paid = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_settlement_event( $accrued, 'settle', currency_bridge_settlement_event( 90000, 'unsafe-source-settle' ), strtotime( '2026-07-19T12:07:00Z' ) );
currency_bridge_expect( is_array( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $source_paid, strtotime( '2026-07-19T12:12:00Z' ) ) ), 'The legacy one-currency settled snapshot must remain independently valid and unchanged.' );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::project_pair( $source_paid, $final_target, strtotime( '2026-07-19T12:12:00Z' ) ), 'tra_vel_commerce_currency_bridge_source_settlement_contamination', 'A target payment cannot be duplicated as source-currency supplier_paid.' );

/* Projection integrity, stale-view, exception-state, and private-boundary adversaries. */
$premature = currency_bridge_create( $funds, $final_target, strtotime( '2026-07-19T12:11:00Z' ) );
currency_bridge_expect( is_array( $premature ) && 'exception' === $premature['overall_status'], 'Target settlement before source collection and accrual must surface as an exception, not success.' );
$refunded = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $captured, 'refund', currency_bridge_payment_event( 1000, 'refund' ), strtotime( '2026-07-19T12:06:00Z' ) );
$refund_bridge = currency_bridge_create( $refunded, $fx, strtotime( '2026-07-19T12:07:00Z' ) );
currency_bridge_expect( is_array( $refund_bridge ) && 'partially_returned' === $refund_bridge['source_customer_funds']['status'] && 'exception' === $refund_bridge['overall_status'], 'A partial source refund must remain distinct from a complete return and interrupt target readiness visibly.' );
$fully_refunded = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $refunded, 'refund', currency_bridge_payment_event( 99000, 'refund-rest' ), strtotime( '2026-07-19T12:08:00Z' ) );
$full_refund_bridge = currency_bridge_create( $fully_refunded, $fx, strtotime( '2026-07-19T12:09:00Z' ) );
currency_bridge_expect( is_array( $full_refund_bridge ) && 'returned' === $full_refund_bridge['source_customer_funds']['status'] && 0 === $full_refund_bridge['source_customer_funds']['net_customer_funds_source_minor'], 'Only a complete source-currency refund may project customer funds as returned.' );

$tampered_funds = $funds;
$tampered_funds['pricing']['supplier_payable_minor']--;
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::project_pair( $tampered_funds, $fx, $initial_now ), 'tra_vel_commerce_currency_bridge_funds_flow_snapshot_invalid', 'A changed complete funds-flow record must fail its own snapshot digest first.' );
$tampered_fx = $fx;
$tampered_fx['ledger']['lines'][0]['source_amount_minor']--;
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::project_pair( $funds, $tampered_fx, $initial_now ), 'tra_vel_commerce_currency_bridge_fx_snapshot_invalid', 'A changed complete FX record must fail its own snapshot digest first.' );
$stale_view = $initial;
$stale_view['evaluated_at'] = '2026-07-19T12:06:00Z';
$stale_view = Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::seal_snapshot( $stale_view );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::validate_snapshot( $stale_view, $captured, $fx, strtotime( '2026-07-19T12:06:00Z' ) ), 'tra_vel_commerce_currency_bridge_projection_mismatch', 'A bridge bound to an older funds-flow snapshot cannot validate as the current view.' );

$unknown = $initial;
$unknown['browser_note'] = 'forbidden';
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::validate_snapshot( $unknown, $funds, $fx, $initial_now ), 'tra_vel_commerce_currency_bridge_record_shape_invalid', 'Unknown bridge fields must fail closed.' );
$sensitive = $initial;
$sensitive['api_key'] = 'forbidden';
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::validate_snapshot( $sensitive, $funds, $fx, $initial_now ), 'tra_vel_commerce_currency_bridge_sensitive_material_rejected', 'Credential-shaped material must fail before bridge parsing.' );
$wrong_status = $initial;
$wrong_status['overall_status'] = 'target_settled';
$wrong_status = Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::seal_snapshot( $wrong_status );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::validate_snapshot( $wrong_status, $funds, $fx, $initial_now ), 'tra_vel_commerce_currency_bridge_projection_mismatch', 'A caller cannot promote the derived overall status.' );
$wrong_target = $initial;
$wrong_target['target_supplier_settlement']['supplier_settled_target_minor'] = 1;
$wrong_target['target_supplier_settlement']['supplier_outstanding_target_minor']--;
$wrong_target['target_supplier_settlement']['status'] = 'partially_settled';
$wrong_target = Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::seal_snapshot( $wrong_target );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::validate_snapshot( $wrong_target, $funds, $fx, $initial_now ), 'tra_vel_commerce_currency_bridge_projection_mismatch', 'Target settlement amounts must replay from FX evidence only.' );
$wrong_truth = $initial;
$wrong_truth['sandbox_truth']['real_settlement'] = true;
$wrong_truth = Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::seal_snapshot( $wrong_truth );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::validate_snapshot( $wrong_truth, $funds, $fx, $initial_now ), 'tra_vel_commerce_currency_bridge_truth_boundary_invalid', 'A sandbox bridge cannot claim a real settlement.' );
$early_evaluation = $initial;
$early_evaluation['evaluated_at'] = '2026-07-19T12:01:00Z';
$early_evaluation = Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::seal_snapshot( $early_evaluation );
currency_bridge_expect_error( Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::validate_snapshot( $early_evaluation, $funds, $fx, $initial_now ), 'tra_vel_commerce_currency_bridge_evaluation_clock_invalid', 'Evaluation cannot predate either bound current snapshot.' );

$source = file_get_contents( $commerce . 'class-tra-vel-commerce-currency-reconciliation-bridge-policy.php' ) . file_get_contents( $commerce . 'class-tra-vel-commerce-currency-reconciliation-bridge-factory.php' );
foreach ( array( 'wp_remote_', 'curl_', 'mysqli_', '$wpdb', 'register_rest_route', 'wp_insert_post', 'update_option', 'add_option', 'delete_option' ) as $forbidden_api ) {
	currency_bridge_expect( false === stripos( $source, $forbidden_api ), 'The bridge must not contain network, database, REST, or option side effects: ' . $forbidden_api . '.' );
}
currency_bridge_expect( false !== strpos( $source, "'supplier_paid_source_minor'        => 0" ), 'Source paid projection must be hard-zero in the implementation.' );
currency_bridge_expect( false !== strpos( $source, '0 !== $settlement[\'supplier_paid_minor\']' ), 'Policy must reject any source-currency supplier payment in bridge scope.' );
currency_bridge_expect( false !== strpos( $source, "'platform_collected_supplier_payable'" ), 'Version-one platform-collected supplier-payable scope must be explicit.' );

fwrite( STDOUT, 'Tra-Vel currency bridge runtime passed ' . $currency_bridge_assertions . ' assertions.' . PHP_EOL );
