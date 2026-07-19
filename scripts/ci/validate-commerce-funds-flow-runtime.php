<?php
/**
 * Adversarial runtime validation for the private per-item funds-flow contract.
 */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code, $message = '', $data = null ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

$commerce_path = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/';
require_once $commerce_path . 'class-tra-vel-commerce-funds-flow-policy.php';
require_once $commerce_path . 'class-tra-vel-commerce-funds-flow-state-machine.php';

$funds_flow_assertions = 0;

function funds_flow_expect( $condition, $message ) {
	global $funds_flow_assertions;
	$funds_flow_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, 'Tra-Vel commerce funds-flow runtime failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function funds_flow_expect_error( $value, $code, $message ) {
	funds_flow_expect( is_wp_error( $value ) && $code === $value->get_error_code(), $message . ( is_wp_error( $value ) ? ' (received ' . $value->get_error_code() . ')' : ' (no error returned)' ) );
}

function funds_flow_digest( $seed ) {
	return hash( 'sha256', 'tra-vel-funds-flow-' . $seed );
}

function funds_flow_fixture( $model, $collector = null ) {
	$created = '2026-07-19T12:00:00Z';
	$parties = array();
	$terms = array(
		'rate_card_ref'                  => 'ratecard_abcdefghijklmnop',
		'rate_card_revision_digest'      => funds_flow_digest( $model . '-rate' ),
		'source_revision_digest'         => funds_flow_digest( $model . '-source' ),
		'supplier_config_revision_digest'=> funds_flow_digest( $model . '-config' ),
		'effective_at'                   => '2026-07-01T00:00:00Z',
		'valid_until'                    => '2027-07-19T00:00:00Z',
		'calculation_basis'              => '',
		'commission_bps'                 => null,
		'markup_amount_minor'            => 0,
		'tax_treatment'                  => 'included',
		'evidence_digest'                => funds_flow_digest( $model . '-terms' ),
	);
	$pricing = array(
		'customer_total_minor'          => 100000,
		'tax_minor'                     => 17000,
		'supplier_net_minor'            => 0,
		'commissionable_minor'          => 0,
		'commission_receivable_minor'   => 0,
		'platform_markup_minor'         => 0,
		'supplier_payable_minor'        => 0,
		'platform_revenue_minor'        => 0,
	);

	if ( 'affiliate_handoff' === $model ) {
		$collector = null === $collector ? 'supplier' : $collector;
		$parties = array(
			'merchant_of_record'         => $collector,
			'payment_collector'          => $collector,
			'supplier_payee'             => 'supplier',
			'commission_payee'           => 'platform',
			'refund_liability_party'     => $collector,
			'chargeback_liability_party' => $collector,
			'tax_remitter'               => $collector,
		);
		$terms['calculation_basis'] = 'affiliate_commission';
		$terms['commission_bps'] = 700;
		$pricing['commissionable_minor'] = 100000;
		$pricing['commission_receivable_minor'] = 7000;
		$pricing['platform_revenue_minor'] = 7000;
	} elseif ( 'direct_commission' === $model ) {
		$collector = null === $collector ? 'platform' : $collector;
		$parties = array(
			'merchant_of_record'         => $collector,
			'payment_collector'          => $collector,
			'supplier_payee'             => 'supplier',
			'commission_payee'           => 'platform',
			'refund_liability_party'     => $collector,
			'chargeback_liability_party' => $collector,
			'tax_remitter'               => $collector,
		);
		$terms['calculation_basis'] = 'gross_less_commission';
		$terms['commission_bps'] = 1000;
		$pricing['supplier_net_minor'] = 90000;
		$pricing['commissionable_minor'] = 100000;
		$pricing['commission_receivable_minor'] = 10000;
		$pricing['supplier_payable_minor'] = 90000;
		$pricing['platform_revenue_minor'] = 10000;
	} else {
		$collector = 'platform';
		$parties = array(
			'merchant_of_record'         => 'platform',
			'payment_collector'          => 'platform',
			'supplier_payee'             => 'supplier',
			'commission_payee'           => 'not_applicable',
			'refund_liability_party'     => 'platform',
			'chargeback_liability_party' => 'platform',
			'tax_remitter'               => 'platform',
		);
		$terms['calculation_basis'] = 'supplier_net_plus_markup';
		$terms['markup_amount_minor'] = 15000;
		$pricing['supplier_net_minor'] = 85000;
		$pricing['platform_markup_minor'] = 15000;
		$pricing['supplier_payable_minor'] = 85000;
		$pricing['platform_revenue_minor'] = 15000;
	}

	$record = array(
		'contract_version'          => '1.0.0',
		'environment'               => 'sandbox',
		'funds_flow_ref'            => 'fflow_abcdefghijklmnop',
		'funds_flow_binding_digest' => '',
		'version'                   => 1,
		'previous_snapshot_digest'  => null,
		'snapshot_digest'           => '',
		'owner_scope_digest'        => funds_flow_digest( 'owner' ),
		'order_ref'                 => 'tv_order_abcdefghijklmnop',
		'order_version'             => 1,
		'order_digest'              => funds_flow_digest( 'order' ),
		'order_item_ref'            => 'tv_order_item_abcdefghijklmnop',
		'offer_digest'              => funds_flow_digest( 'offer' ),
		'routing_binding_digest'    => funds_flow_digest( 'routing' ),
		'provider_id'               => 'generic_' . $model . '_supplier',
		'vertical'                  => 'flight',
		'commercial_model'          => $model,
		'currency'                  => 'ILS',
		'minor_unit_exponent'       => 2,
		'parties'                   => $parties,
		'commercial_terms'          => $terms,
		'pricing'                   => $pricing,
		'payment'                   => array(
			'state'                       => 'not_started',
			'authority'                   => 'platform' === $collector ? 'platform_processor' : 'supplier_reported',
			'authorized_amount_minor'     => 0,
			'captured_amount_minor'       => 0,
			'refunded_amount_minor'       => 0,
			'disputed_amount_minor'       => 0,
			'charged_back_amount_minor'   => 0,
			'processor_payment_ref'       => null,
			'latest_event_digest'         => null,
			'updated_at'                  => $created,
		),
		'settlement'                => array(
			'state'                            => 'not_started',
			'supplier_due_minor'               => 0,
			'supplier_paid_minor'              => 0,
			'commission_due_minor'             => 0,
			'commission_received_minor'        => 0,
			'chargeback_recovery_due_minor'    => 0,
			'chargeback_recovered_minor'       => 0,
			'latest_reconciliation_digest'     => null,
			'due_at'                          => null,
			'updated_at'                      => $created,
		),
		'liabilities'               => array(
			'customer_refund_due_minor'              => 0,
			'supplier_payable_outstanding_minor'     => 0,
			'commission_receivable_outstanding_minor'=> 0,
			'chargeback_liability_minor'             => 0,
			'chargeback_liability_party'             => $parties['chargeback_liability_party'],
		),
		'private_routes'            => array(
			'private_routing_record_ref' => 'tvr_binding_abcdefghijklmnop',
			'payment_route_ref'         => 'payroute_abcdefghijklmnop',
			'settlement_route_ref'      => 'setroute_abcdefghijklmnop',
			'supplier_payable_route_ref'=> 'platform' === $collector && 'affiliate_handoff' !== $model ? 'payout_abcdefghijklmnop' : null,
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
		'data_boundary'            => array(
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

function funds_flow_reseal( $record ) {
	return Tra_Vel_Commerce_Funds_Flow_Policy::seal_snapshot( $record );
}

function funds_flow_event( $amount, $seed, $payment_ref = 'paytxn_abcdefghijklmnop' ) {
	return array(
		'amount_minor'         => $amount,
		'processor_payment_ref'=> $payment_ref,
		'evidence_digest'      => funds_flow_digest( $seed ),
	);
}

function funds_flow_settlement_event( $supplier, $commission, $recovery, $seed, $due_at = null ) {
	return array(
		'supplier_amount_minor'   => $supplier,
		'commission_amount_minor' => $commission,
		'recovery_amount_minor'   => $recovery,
		'evidence_digest'         => funds_flow_digest( $seed ),
		'due_at'                  => $due_at,
	);
}

function funds_flow_schema_walk( $node, $path = '#' ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( isset( $node['type'] ) && 'object' === $node['type'] ) {
		funds_flow_expect( array_key_exists( 'additionalProperties', $node ) && false === $node['additionalProperties'], $path . ' object must reject unknown properties.' );
		funds_flow_expect( isset( $node['properties'], $node['required'] ) && is_array( $node['properties'] ) && is_array( $node['required'] ) && ! array_diff( array_keys( $node['properties'] ), $node['required'] ) && ! array_diff( $node['required'], array_keys( $node['properties'] ) ), $path . ' object must require every declared property.' );
	}
	foreach ( $node as $key => $child ) {
		if ( is_array( $child ) ) {
			funds_flow_schema_walk( $child, $path . '/' . $key );
		}
	}
}

$now = strtotime( '2026-07-19T15:00:00Z' );
$schema_path = __DIR__ . '/../../plugin/tra-vel-agent-core/schemas/private/commerce-funds-flow-record.schema.json';
$schema = json_decode( file_get_contents( $schema_path ), true );
funds_flow_expect( is_array( $schema ) && JSON_ERROR_NONE === json_last_error(), 'Private funds-flow schema must be valid JSON.' );
funds_flow_expect( false === $schema['additionalProperties'] && 'sandbox' === $schema['properties']['environment']['const'], 'Private schema must be root-closed and sandbox-only.' );
funds_flow_schema_walk( $schema );
foreach ( array( 'affiliate_handoff', 'direct_commission', 'net_rate_markup' ) as $model ) {
	funds_flow_expect( in_array( $model, $schema['definitions']['commercialModel']['enum'], true ), 'Schema must enumerate commercial model ' . $model . '.' );
}

$affiliate = funds_flow_fixture( 'affiliate_handoff' );
$commission = funds_flow_fixture( 'direct_commission' );
$supplier_collected_commission = funds_flow_fixture( 'direct_commission', 'supplier' );
$net_rate = funds_flow_fixture( 'net_rate_markup' );
foreach ( array( $affiliate, $commission, $supplier_collected_commission, $net_rate ) as $index => $fixture ) {
	$validated = Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $fixture, $now );
	funds_flow_expect( is_array( $validated ), 'Commercial funds-flow fixture ' . $index . ' must validate' . ( is_wp_error( $validated ) ? ': ' . $validated->get_error_code() . ' / ' . $validated->get_error_message() : '.' ) );
	funds_flow_expect( hash_equals( $fixture['funds_flow_binding_digest'], Tra_Vel_Commerce_Funds_Flow_Policy::binding_digest( $fixture ) ) && hash_equals( $fixture['snapshot_digest'], Tra_Vel_Commerce_Funds_Flow_Policy::snapshot_digest( $fixture ) ), 'Binding and snapshot digests must cover their exact canonical scopes.' );
}
funds_flow_expect( 'supplier_reported' === $affiliate['payment']['authority'] && null === $affiliate['private_routes']['supplier_payable_route_ref'], 'Affiliate handoff must be supplier-reported and cannot invent a platform supplier payout route.' );
funds_flow_expect( 'platform_processor' === $commission['payment']['authority'] && null !== $commission['private_routes']['supplier_payable_route_ref'], 'Platform-collected direct commission must bind its private supplier payout route.' );
funds_flow_expect( 'supplier_reported' === $supplier_collected_commission['payment']['authority'] && null === $supplier_collected_commission['private_routes']['supplier_payable_route_ref'], 'Supplier-collected direct commission must settle commission without a platform supplier payout route.' );
funds_flow_expect( 85000 === $net_rate['pricing']['supplier_net_minor'] && 15000 === $net_rate['pricing']['platform_markup_minor'], 'Net-rate markup must preserve distinct supplier net and platform margin.' );

$projection = Tra_Vel_Commerce_Funds_Flow_Policy::public_projection( $commission, $now );
funds_flow_expect( is_array( $projection ) && array( 'contract_version', 'environment', 'order_ref', 'order_version', 'order_item_ref', 'currency', 'funds_flow_binding_digest', 'snapshot_digest', 'rate_card_revision_digest', 'source_revision_digest', 'supplier_config_revision_digest', 'payment_state', 'settlement_state', 'updated_at' ) === array_keys( $projection ), 'Public projection must have the exact digest-only allowlist.' );
$projection_json = wp_json_encode( $projection );
foreach ( array( 'provider_id', 'commercial_model', 'ratecard_', 'payroute_', 'setroute_', 'payout_', 'commission_bps', 'supplier_net_minor', 'private_routes' ) as $forbidden ) {
	funds_flow_expect( false === strpos( $projection_json, $forbidden ), 'Public projection must not expose private funds-flow material: ' . $forbidden . '.' );
}

$bad = $commission;
$bad['browser_note'] = 'forbidden';
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_record_shape_invalid', 'Unknown root fields must fail closed.' );
$bad = $commission;
$bad['api_key'] = 'not-allowed';
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_sensitive_material_rejected', 'Credential-shaped fields must be rejected before parsing.' );
$bad = $commission;
$bad['provider_id'] = 'person@example.com';
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_sensitive_material_rejected', 'Email-like personal data must be rejected from the private financial contract.' );
$bad = funds_flow_reseal( array_replace( $commission, array( 'environment' => 'live' ) ) );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_record_shape_invalid', 'This foundation cannot be relabelled as live.' );
$bad = $commission;
$bad['funds_flow_ref'] = 'raw-reference';
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_identity_invalid', 'Funds-flow identity must be opaque and typed.' );
$bad = $commission;
$bad['version'] = 2;
$bad['last_event_sequence'] = 1;
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_snapshot_ancestry_invalid', 'A successor version cannot omit predecessor digest.' );
$bad = $commission;
$bad['last_event_sequence'] = 4;
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_identity_invalid', 'Event sequence must equal version minus one.' );
$bad = $commission;
$bad['updated_at'] = '2026-07-19T16:00:00Z';
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_chronology_invalid', 'Future financial snapshots must fail.' );
$bad = $commission;
$bad['minor_unit_exponent'] = 0;
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_currency_minor_unit_invalid', 'ILS cannot silently switch from two-decimal minor units.' );
$yen = $commission;
$yen['currency'] = 'JPY';
$yen['minor_unit_exponent'] = 0;
$yen = funds_flow_reseal( $yen );
funds_flow_expect( is_array( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $yen, $now ) ), 'Zero-decimal travel currency JPY must use its exact exponent.' );
$dinar = $commission;
$dinar['currency'] = 'KWD';
$dinar['minor_unit_exponent'] = 3;
$dinar = funds_flow_reseal( $dinar );
funds_flow_expect( is_array( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $dinar, $now ) ), 'Three-decimal travel currency KWD must use its exact exponent.' );

$bad = $affiliate;
$bad['parties']['merchant_of_record'] = 'platform';
$bad['parties']['payment_collector'] = 'platform';
$bad['parties']['refund_liability_party'] = 'platform';
$bad['parties']['chargeback_liability_party'] = 'platform';
$bad['parties']['tax_remitter'] = 'platform';
$bad['payment']['authority'] = 'platform_processor';
$bad['liabilities']['chargeback_liability_party'] = 'platform';
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_affiliate_parties_invalid', 'Affiliate handoff cannot make the platform merchant or collector.' );
$bad = $commission;
$bad['parties']['payment_collector'] = 'supplier';
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_collector_mor_mismatch', 'Collector and merchant cannot be silently split in this contract.' );
$bad = $net_rate;
$bad['parties']['merchant_of_record'] = 'supplier';
$bad['parties']['payment_collector'] = 'supplier';
$bad['parties']['refund_liability_party'] = 'supplier';
$bad['parties']['chargeback_liability_party'] = 'supplier';
$bad['parties']['tax_remitter'] = 'supplier';
$bad['payment']['authority'] = 'supplier_reported';
$bad['liabilities']['chargeback_liability_party'] = 'supplier';
$bad['private_routes']['supplier_payable_route_ref'] = null;
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_net_rate_parties_invalid', 'Net-rate markup cannot shift merchant and collector liability to the supplier.' );

$bad = $commission;
$bad['commercial_terms']['valid_until'] = '2026-07-19T12:00:00Z';
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_commercial_terms_chronology_invalid', 'A rate card expiring when the funds binding was created was never valid for that item.' );
$historical_terms = $commission;
$historical_terms['commercial_terms']['valid_until'] = '2026-07-20T00:00:00Z';
$historical_terms = funds_flow_reseal( $historical_terms );
funds_flow_expect( is_array( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $historical_terms, strtotime( '2027-01-01T00:00:00Z' ) ) ), 'Expired historical rate-card evidence must remain serviceable after travel for refunds, chargebacks and settlement.' );
$bad = $commission;
$bad['commercial_terms']['calculation_basis'] = 'supplier_net_plus_markup';
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_calculation_basis_mismatch', 'Calculation basis must match commercial model.' );
$bad = $net_rate;
$bad['commercial_terms']['commission_bps'] = 500;
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_net_rate_commission_invalid', 'Net-rate markup cannot also charge commission.' );
$bad = $commission;
$bad['pricing']['commission_receivable_minor']++;
$bad['pricing']['platform_revenue_minor']++;
$bad['pricing']['supplier_payable_minor']--;
$bad['pricing']['supplier_net_minor']--;
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_commission_pricing_invalid', 'Commission must exactly follow immutable basis points.' );
$bad = $affiliate;
$bad['pricing']['supplier_payable_minor'] = 93000;
$bad['pricing']['supplier_net_minor'] = 93000;
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_affiliate_pricing_invalid', 'Affiliate handoff cannot invent platform supplier payables.' );
$bad = $net_rate;
$bad['pricing']['platform_markup_minor'] = 14000;
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_net_rate_pricing_invalid', 'Net rate plus markup must reconcile exactly to customer total.' );

$bad = $commission;
$bad['payment']['authority'] = 'supplier_reported';
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_payment_authority_invalid', 'Payment authority must match collector.' );
$bad = $commission;
$bad['payment']['state'] = 'captured';
$bad['payment']['authorized_amount_minor'] = 100000;
$bad['payment']['captured_amount_minor'] = 110000;
$bad['payment']['processor_payment_ref'] = 'paytxn_abcdefghijklmnop';
$bad['payment']['latest_event_digest'] = funds_flow_digest( 'bad-capture' );
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_payment_amount_order_invalid', 'Capture cannot exceed authorization or item total.' );
$bad = $commission;
$bad['payment']['state'] = 'captured';
$bad['payment']['processor_payment_ref'] = 'paytxn_abcdefghijklmnop';
$bad['payment']['latest_event_digest'] = funds_flow_digest( 'empty-capture' );
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_payment_state_amount_mismatch', 'Captured state cannot carry zero capture.' );

$bad = $commission;
$bad['settlement']['state'] = 'accrued';
$bad['settlement']['commission_due_minor'] = 10000;
$bad['settlement']['latest_reconciliation_digest'] = funds_flow_digest( 'wrong-settlement-side' );
$bad['settlement']['due_at'] = '2026-08-01T00:00:00Z';
$bad['liabilities']['commission_receivable_outstanding_minor'] = 10000;
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_platform_collection_settlement_invalid', 'Platform collection cannot also accrue external commission receivable.' );
$bad = $affiliate;
$bad['settlement']['state'] = 'accrued';
$bad['settlement']['supplier_due_minor'] = 1000;
$bad['settlement']['latest_reconciliation_digest'] = funds_flow_digest( 'affiliate-supplier-due' );
$bad['settlement']['due_at'] = '2026-08-01T00:00:00Z';
$bad['liabilities']['supplier_payable_outstanding_minor'] = 1000;
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_external_collection_settlement_invalid', 'Affiliate collection cannot create platform supplier payable.' );
$bad = $commission;
$bad['liabilities']['supplier_payable_outstanding_minor'] = 1;
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_liability_reconciliation_invalid', 'Liability ledger must reconcile exactly to settlement.' );
$bad = $commission;
$bad['private_routes']['supplier_payable_route_ref'] = null;
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_supplier_payable_route_invalid', 'Platform-collected direct funds require a private supplier payout route.' );
$bad = $affiliate;
$bad['private_routes']['supplier_payable_route_ref'] = 'payout_abcdefghijklmnop';
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_supplier_payable_route_invalid', 'Affiliate handoff cannot carry a platform supplier payout route.' );
$bad = $commission;
$bad['sandbox_truth']['real_customer_charge'] = true;
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_boundary_invalid', 'Sandbox snapshot cannot claim a real customer charge.' );
$bad = $commission;
$bad['data_boundary']['raw_payment_data_stored'] = true;
$bad = funds_flow_reseal( $bad );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_boundary_invalid', 'Raw payment data cannot enter the record.' );
$bad = $commission;
$bad['commercial_terms']['evidence_digest'] = funds_flow_digest( 'tampered-terms' );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_binding_digest_mismatch', 'In-place commercial terms tampering must break immutable binding digest.' );
$bad = $commission;
$bad['payment']['updated_at'] = '2026-07-19T12:00:01Z';
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_payment_evidence_invalid', 'Mutable payment tampering without a new snapshot must fail integrity or chronology.' );
$bad = $commission;
$bad['payment']['state'] = 'payment_failed';
$bad['payment']['latest_event_digest'] = funds_flow_digest( 'unsealed-payment-failure' );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $bad, $now ), 'tra_vel_commerce_funds_flow_snapshot_digest_mismatch', 'A semantically possible mutable-state edit still requires a new immutable snapshot digest.' );

$pending = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $commission, 'request_authorization', funds_flow_event( 0, 'authorization-request' ), strtotime( '2026-07-19T12:01:00Z' ) );
funds_flow_expect( is_array( $pending ) && 'authorization_pending' === $pending['payment']['state'] && 2 === $pending['version'], 'Authorization request must create one evidence-bound successor without financial amounts.' );
$authorized = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $pending, 'authorize', funds_flow_event( 100000, 'authorized' ), strtotime( '2026-07-19T12:02:00Z' ) );
funds_flow_expect( is_array( $authorized ) && 'authorized' === $authorized['payment']['state'] && 100000 === $authorized['payment']['authorized_amount_minor'], 'Authorization observation must record the exact item-bounded amount.' );
$partial_capture = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $authorized, 'capture', funds_flow_event( 40000, 'capture-one' ), strtotime( '2026-07-19T12:03:00Z' ) );
funds_flow_expect( is_array( $partial_capture ) && 'partially_captured' === $partial_capture['payment']['state'] && 40000 === $partial_capture['payment']['captured_amount_minor'], 'Partial capture must remain distinct from full capture.' );
$captured = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $partial_capture, 'capture', funds_flow_event( 60000, 'capture-two' ), strtotime( '2026-07-19T12:04:00Z' ) );
funds_flow_expect( is_array( $captured ) && 'captured' === $captured['payment']['state'] && 100000 === $captured['payment']['captured_amount_minor'], 'Cumulative capture equal to item total must become captured.' );
$partial_refund = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $captured, 'refund', funds_flow_event( 10000, 'refund-one' ), strtotime( '2026-07-19T12:05:00Z' ) );
funds_flow_expect( is_array( $partial_refund ) && 'partially_refunded' === $partial_refund['payment']['state'] && 10000 === $partial_refund['payment']['refunded_amount_minor'], 'Partial refund must preserve captured and refunded amounts separately.' );
$disputed = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $partial_refund, 'open_dispute', funds_flow_event( 20000, 'dispute-one' ), strtotime( '2026-07-19T12:06:00Z' ) );
funds_flow_expect( is_array( $disputed ) && 'disputed' === $disputed['payment']['state'] && 20000 === $disputed['payment']['disputed_amount_minor'], 'Dispute must be bounded by unrefunded captured funds.' );
$charged_back = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $disputed, 'record_chargeback', funds_flow_event( 5000, 'chargeback-one' ), strtotime( '2026-07-19T12:07:00Z' ) );
funds_flow_expect( is_array( $charged_back ) && 'charged_back' === $charged_back['payment']['state'] && 5000 === $charged_back['liabilities']['chargeback_liability_minor'], 'Chargeback must create an exactly matched liability with its declared owner.' );
funds_flow_expect( 8 === $charged_back['version'] && 7 === $charged_back['last_event_sequence'] && $disputed['snapshot_digest'] === $charged_back['previous_snapshot_digest'], 'Every payment event must append exactly one immutable successor.' );
funds_flow_expect( ! $charged_back['sandbox_truth']['real_processor_call'] && ! $charged_back['sandbox_truth']['real_customer_charge'], 'State transitions must never claim a processor call or real charge.' );
$post_dispute_refund = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $disputed, 'refund', funds_flow_event( 5000, 'post-dispute-refund' ), strtotime( '2026-07-19T12:07:00Z' ) );
funds_flow_expect( is_array( $post_dispute_refund ) && 'disputed' === $post_dispute_refund['payment']['state'] && 15000 === $post_dispute_refund['payment']['refunded_amount_minor'], 'A non-overlapping refund after dispute must preserve the dispute while updating refund truth.' );
$second_chargeback = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $charged_back, 'record_chargeback', funds_flow_event( 5000, 'chargeback-two' ), strtotime( '2026-07-19T12:08:00Z' ) );
funds_flow_expect( is_array( $second_chargeback ) && 10000 === $second_chargeback['payment']['charged_back_amount_minor'] && 10000 === $second_chargeback['liabilities']['chargeback_liability_minor'], 'Multiple chargeback observations must accumulate only within the open dispute.' );

$chargeback_recovery = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_settlement_event( $charged_back, 'accrue', funds_flow_settlement_event( 0, 0, 5000, 'chargeback-recovery-accrued', '2026-08-01T00:00:00Z' ), strtotime( '2026-07-19T12:08:00Z' ) );
funds_flow_expect( is_array( $chargeback_recovery ) && 5000 === $chargeback_recovery['settlement']['chargeback_recovery_due_minor'], 'A chargeback may create a separately evidenced recovery receivable without changing the chargeback liability owner.' );
$chargeback_recovered = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_settlement_event( $chargeback_recovery, 'settle', funds_flow_settlement_event( 0, 0, 5000, 'chargeback-recovery-received' ), strtotime( '2026-07-19T12:09:00Z' ) );
funds_flow_expect( is_array( $chargeback_recovered ) && 'settled' === $chargeback_recovered['settlement']['state'] && 5000 === $chargeback_recovered['settlement']['chargeback_recovered_minor'], 'Chargeback recovery must settle independently from authorization, capture and customer refund amounts.' );

funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $authorized, 'capture', funds_flow_event( 100001, 'overcapture' ), strtotime( '2026-07-19T12:03:00Z' ) ), 'tra_vel_commerce_funds_flow_capture_invalid', 'State machine must reject over-capture.' );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $captured, 'refund', funds_flow_event( 100001, 'overrefund' ), strtotime( '2026-07-19T12:05:00Z' ) ), 'tra_vel_commerce_funds_flow_refund_invalid', 'State machine must reject over-refund.' );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $disputed, 'record_chargeback', funds_flow_event( 20001, 'overchargeback' ), strtotime( '2026-07-19T12:07:00Z' ) ), 'tra_vel_commerce_funds_flow_chargeback_invalid', 'State machine must reject chargeback beyond dispute.' );
$bad_event = funds_flow_event( 100000, 'unknown-event-field' );
$bad_event['redirect_url'] = 'https://example.invalid';
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $pending, 'authorize', $bad_event, strtotime( '2026-07-19T12:02:00Z' ) ), 'tra_vel_commerce_funds_flow_payment_event_invalid', 'Unknown payment event fields must fail closed.' );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $pending, 'authorize', funds_flow_event( 100000, 'reused-clock' ), strtotime( '2026-07-19T12:01:00Z' ) ), 'tra_vel_commerce_funds_flow_event_clock_invalid', 'Events cannot reuse or move behind the current snapshot clock.' );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $captured, 'authorize', funds_flow_event( 100000, 'bad-transition' ), strtotime( '2026-07-19T12:05:00Z' ) ), 'tra_vel_commerce_funds_flow_payment_transition_invalid', 'Authorization cannot be repeated after capture.' );

$accrued = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_settlement_event( $captured, 'accrue', funds_flow_settlement_event( 90000, 0, 0, 'supplier-accrual', '2026-08-01T00:00:00Z' ), strtotime( '2026-07-19T12:05:00Z' ) );
funds_flow_expect( is_array( $accrued ) && 'accrued' === $accrued['settlement']['state'] && 90000 === $accrued['liabilities']['supplier_payable_outstanding_minor'], 'Platform collection must accrue the exact supplier payable and liability.' );
$part_settled = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_settlement_event( $accrued, 'settle', funds_flow_settlement_event( 45000, 0, 0, 'supplier-paid-one' ), strtotime( '2026-07-19T12:06:00Z' ) );
funds_flow_expect( is_array( $part_settled ) && 'partially_settled' === $part_settled['settlement']['state'] && 45000 === $part_settled['liabilities']['supplier_payable_outstanding_minor'], 'Partial supplier settlement must reduce, not erase, outstanding liability.' );
$settled = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_settlement_event( $part_settled, 'settle', funds_flow_settlement_event( 45000, 0, 0, 'supplier-paid-two' ), strtotime( '2026-07-19T12:07:00Z' ) );
funds_flow_expect( is_array( $settled ) && 'settled' === $settled['settlement']['state'] && 0 === $settled['liabilities']['supplier_payable_outstanding_minor'], 'Full supplier settlement must close the exact liability.' );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_settlement_event( $accrued, 'settle', funds_flow_settlement_event( 90001, 0, 0, 'supplier-overpaid' ), strtotime( '2026-07-19T12:06:00Z' ) ), 'tra_vel_commerce_funds_flow_settlement_overpayment', 'Settlement cannot exceed recognized supplier due.' );

$refund_due = $captured;
$refund_due['liabilities']['customer_refund_due_minor'] = 25000;
$refund_due = funds_flow_reseal( $refund_due );
funds_flow_expect( is_array( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $refund_due, $now ) ), 'A pending customer refund liability may be recorded separately from an executed refund.' );
$refund_due['liabilities']['customer_refund_due_minor'] = 100001;
$refund_due = funds_flow_reseal( $refund_due );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $refund_due, $now ), 'tra_vel_commerce_funds_flow_liability_reconciliation_invalid', 'Customer refund liability cannot exceed captured funds still available.' );

$affiliate_authorized = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $affiliate, 'authorize', funds_flow_event( 100000, 'affiliate-sale-authorized' ), strtotime( '2026-07-19T12:01:00Z' ) );
$affiliate_captured = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_payment_event( $affiliate_authorized, 'capture', funds_flow_event( 100000, 'affiliate-sale-captured' ), strtotime( '2026-07-19T12:02:00Z' ) );
$affiliate_accrued = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_settlement_event( $affiliate_captured, 'accrue', funds_flow_settlement_event( 0, 7000, 0, 'affiliate-commission-accrued', '2026-08-10T00:00:00Z' ), strtotime( '2026-07-19T12:03:00Z' ) );
funds_flow_expect( is_array( $affiliate_accrued ) && 7000 === $affiliate_accrued['liabilities']['commission_receivable_outstanding_minor'] && 0 === $affiliate_accrued['liabilities']['supplier_payable_outstanding_minor'], 'Affiliate sale must accrue commission receivable, never supplier payable.' );
$affiliate_paid = Tra_Vel_Commerce_Funds_Flow_State_Machine::apply_settlement_event( $affiliate_accrued, 'settle', funds_flow_settlement_event( 0, 7000, 0, 'affiliate-commission-received' ), strtotime( '2026-07-19T12:04:00Z' ) );
funds_flow_expect( is_array( $affiliate_paid ) && 'settled' === $affiliate_paid['settlement']['state'] && 0 === $affiliate_paid['liabilities']['commission_receivable_outstanding_minor'], 'Affiliate commission receipt must close commission receivable.' );

$tampered_successor = $authorized;
$tampered_successor['commercial_terms']['rate_card_revision_digest'] = funds_flow_digest( 'different-rate-card' );
$tampered_successor['version']++;
$tampered_successor['last_event_sequence']++;
$tampered_successor['previous_snapshot_digest'] = $authorized['snapshot_digest'];
$tampered_successor['updated_at'] = '2026-07-19T12:03:00Z';
$tampered_successor['payment']['updated_at'] = '2026-07-19T12:03:00Z';
$tampered_successor = funds_flow_reseal( $tampered_successor );
funds_flow_expect_error( Tra_Vel_Commerce_Funds_Flow_Policy::assert_successor( $authorized, $tampered_successor, $now ), 'tra_vel_commerce_funds_flow_successor_invalid', 'A successor cannot swap rate-card revision while preserving the same funds-flow identity.' );

$private_json = wp_json_encode( $affiliate_paid );
funds_flow_expect( false === strpos( $private_json, 'sk-' ) && false === strpos( $private_json, 'Bearer ') && false === strpos( $private_json, '@') && false === strpos( $private_json, 'card_number'), 'Even private snapshots must contain no raw credentials, bearer material, email, or card number.' );
funds_flow_expect( false === strpos( file_get_contents( __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-funds-flow-state-machine.php' ), 'wp_remote_' ), 'Funds-flow state machine must have no HTTP dispatch path.' );

echo 'Tra-Vel commerce funds-flow runtime passed (' . $funds_flow_assertions . ' closed-ledger assertions).' . PHP_EOL;
