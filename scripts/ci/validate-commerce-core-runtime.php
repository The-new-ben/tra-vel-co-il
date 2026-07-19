<?php
/**
 * Deterministic runtime checks for the Commerce Core contract primitives.
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
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/[\x00-\x1F\x7F]/u', '', (string) $value ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function apply_filters( $name, $value ) { return $value; }

$commerce = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/';
require_once $commerce . 'class-tra-vel-commerce-taxonomy.php';
require_once $commerce . 'class-tra-vel-commerce-money.php';
require_once $commerce . 'class-tra-vel-commerce-policy.php';
require_once $commerce . 'interface-tra-vel-commerce-provider-adapter.php';
require_once $commerce . 'interface-tra-vel-commerce-search-adapter.php';
require_once $commerce . 'interface-tra-vel-commerce-quote-adapter.php';
require_once $commerce . 'interface-tra-vel-commerce-fulfillment-adapter.php';
require_once $commerce . 'interface-tra-vel-commerce-webhook-adapter.php';
require_once $commerce . 'interface-tra-vel-commerce-reconciliation-adapter.php';
require_once $commerce . 'interface-tra-vel-commerce-payment-adapter.php';
require_once $commerce . 'interface-tra-vel-commerce-settlement-adapter.php';
require_once $commerce . 'interface-tra-vel-commerce-affiliate-reporter.php';
require_once $commerce . 'class-tra-vel-commerce-provider-registry.php';

$assertions = 0;
function commerce_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "Commerce Core runtime failed: {$message}\n" );
		exit( 1 );
	}
}

function commerce_descriptor( $id, $priority = 100, $capabilities = array( 'search' ), $relationship = 'direct', $verticals = array( 'flight' ) ) {
	return array(
		'contract_version' => '1.0.0',
		'provider_id' => $id,
		'display_name' => ucwords( str_replace( '_', ' ', $id ) ),
		'adapter_version' => '1.0.0',
		'environment' => 'sandbox',
		'relationship' => $relationship,
		'verticals' => $verticals,
		'capabilities' => $capabilities,
		'priority' => $priority,
		'readiness' => array( 'configuration' => 'seeded', 'status' => 'ready' ),
		'commercial_truth' => array( 'simulated' => true, 'real_charge' => false, 'real_booking' => false ),
		'allowed_hosts' => array(),
		'jurisdictions' => array( 'global' ),
		'licence' => array( 'status' => 'sandbox_only', 'reference_digest' => null ),
		'settlement' => array( 'model' => 'affiliate' === $relationship ? 'affiliate' : 'commission', 'commission_bps' => 650, 'currency' => 'USD', 'payout_lag_days' => 30 ),
	);
}

class Commerce_Search_Adapter implements Tra_Vel_Commerce_Search_Adapter {
	protected $descriptor;
	public function __construct( $descriptor ) { $this->descriptor = $descriptor; }
	public function get_descriptor() { return $this->descriptor; }
	public function search( $request, $context ) { return array(); }
}

class Commerce_Misdeclared_Adapter extends Commerce_Search_Adapter {}

class Commerce_Full_Adapter extends Commerce_Search_Adapter implements Tra_Vel_Commerce_Quote_Adapter, Tra_Vel_Commerce_Fulfillment_Adapter, Tra_Vel_Commerce_Webhook_Adapter, Tra_Vel_Commerce_Reconciliation_Adapter, Tra_Vel_Commerce_Settlement_Adapter {
	public function revalidate( $offer, $context ) { return array(); }
	public function reserve( $offer, $command ) { return array(); }
	public function confirm( $hold, $command ) { return array(); }
	public function fulfill( $confirmation, $command ) { return array(); }
	public function quote_change( $fulfillment, $command ) { return array(); }
	public function change( $change_quote, $command ) { return array(); }
	public function quote_cancellation( $fulfillment, $command ) { return array(); }
	public function cancel( $cancellation_quote, $command ) { return array(); }
	public function refund( $fulfillment, $command ) { return array(); }
	public function verify_webhook( $headers, $body ) { return array(); }
	public function apply_webhook( $event, $context ) { return array(); }
	public function reconcile_resource( $resource_type, $resource_reference, $context ) { return array(); }
	public function reconcile_settlement( $period, $context ) { return array(); }
}

class Commerce_Affiliate_Adapter extends Commerce_Full_Adapter implements Tra_Vel_Commerce_Affiliate_Reporter {
	public function report_conversion( $order, $context ) { return array(); }
}

class Commerce_Payment_Adapter extends Commerce_Full_Adapter implements Tra_Vel_Commerce_Payment_Adapter {
	public function create_intent( $order, $command ) { return array(); }
	public function confirm_intent( $intent, $command ) { return array(); }
	public function capture( $intent, $command ) { return array(); }
	public function void( $intent, $command ) { return array(); }
	public function refund_payment( $intent, $command ) { return array(); }
}

commerce_assert( 'flight' === Tra_Vel_Commerce_Taxonomy::vertical( 'flight' ), 'canonical flight vertical must normalize' );
commerce_assert( '' === Tra_Vel_Commerce_Taxonomy::vertical( 'flights' ), 'plural aliases must not leak into canonical contracts' );
commerce_assert( 'accommodation' === Tra_Vel_Commerce_Taxonomy::legacy_vertical( 'hotel' ), 'hotel compatibility alias must be explicit' );
commerce_assert( '' === Tra_Vel_Commerce_Taxonomy::legacy_vertical( 'car' ), 'car must not be guessed into another vertical' );
commerce_assert( is_wp_error( Tra_Vel_Commerce_Taxonomy::verticals( array( 'flight', 'car' ) ) ), 'unknown vertical list must fail closed' );

$ledger = Tra_Vel_Commerce_Money::ledger(
	array(
		array( 'code' => 'base', 'amount_minor' => 10000, 'currency' => 'USD' ),
		array( 'code' => 'taxes', 'amount_minor' => 1750, 'currency' => 'USD' ),
		array( 'code' => 'fees', 'amount_minor' => 250, 'currency' => 'USD' ),
	),
	'USD'
);
commerce_assert( ! is_wp_error( $ledger ) && 12000 === $ledger['total_minor'] && 2 === $ledger['exponent'], 'same-currency integer ledger must total exactly' );
$mixed = Tra_Vel_Commerce_Money::ledger( array( array( 'code' => 'base', 'amount_minor' => 100, 'currency' => 'EUR' ) ), 'USD' );
commerce_assert( is_wp_error( $mixed ), 'cross-currency ledger must fail without evidenced FX' );
$float = Tra_Vel_Commerce_Money::ledger( array( array( 'code' => 'base', 'amount_minor' => 10.5, 'currency' => 'USD' ) ), 'USD' );
commerce_assert( is_wp_error( $float ), 'floating-point money must never enter the commerce ledger' );
commerce_assert( is_wp_error( Tra_Vel_Commerce_Money::add( PHP_INT_MAX, 1 ) ), 'minor-unit addition must fail on overflow' );

$descriptor = commerce_descriptor( 'flight_supplier_a' );
$normalized = Tra_Vel_Commerce_Policy::provider_descriptor( $descriptor );
commerce_assert( ! is_wp_error( $normalized ) && 'flight_supplier_a' === $normalized['provider_id'], 'valid sandbox descriptor must normalize' );
$unknown = $descriptor;
$unknown['secret'] = 'must-not-pass';
commerce_assert( is_wp_error( Tra_Vel_Commerce_Policy::provider_descriptor( $unknown ) ), 'unknown descriptor fields must fail closed' );
$fake_live = $descriptor;
$fake_live['commercial_truth']['real_booking'] = true;
commerce_assert( is_wp_error( Tra_Vel_Commerce_Policy::provider_descriptor( $fake_live ) ), 'sandbox descriptor cannot claim real booking' );
$sandbox_host = $descriptor;
$sandbox_host['allowed_hosts'] = array( 'supplier.example' );
commerce_assert( is_wp_error( Tra_Vel_Commerce_Policy::provider_descriptor( $sandbox_host ) ), 'sandbox descriptor cannot open outbound provider hosts' );
$live_insurance = commerce_descriptor( 'insurance_supplier_a', 100, array( 'search' ), 'direct', array( 'insurance' ) );
$live_insurance['environment'] = 'live';
$live_insurance['commercial_truth'] = array( 'simulated' => false, 'real_charge' => false, 'real_booking' => false );
$live_insurance['readiness'] = array( 'configuration' => 'configured', 'status' => 'ready' );
$live_insurance['allowed_hosts'] = array( 'api.insurance.example' );
$live_insurance['licence'] = array( 'status' => 'not_configured', 'reference_digest' => null );
commerce_assert( is_wp_error( Tra_Vel_Commerce_Policy::provider_descriptor( $live_insurance ) ), 'live insurance requires verified licence evidence' );
commerce_assert( '2026-07-19T08:30:00Z' === Tra_Vel_Commerce_Policy::utc_datetime( '2026-07-19T11:30:00+03:00' ), 'RFC3339 time must normalize to UTC' );
commerce_assert( null === Tra_Vel_Commerce_Policy::utc_datetime( '2026-02-30T08:30:00Z' ), 'impossible RFC3339 dates must fail' );
commerce_assert( Tra_Vel_Commerce_Policy::canonical_digest( array( 'b' => 2, 'a' => 1 ) ) === Tra_Vel_Commerce_Policy::canonical_digest( array( 'a' => 1, 'b' => 2 ) ), 'object key order must not alter canonical digest' );

$low  = new Commerce_Search_Adapter( commerce_descriptor( 'flight_supplier_b', 50 ) );
$high = new Commerce_Search_Adapter( commerce_descriptor( 'flight_supplier_a', 200 ) );
$registry_a = new Tra_Vel_Commerce_Provider_Registry( array( $low, $high ) );
$registry_b = new Tra_Vel_Commerce_Provider_Registry( array( $high, $low ) );
commerce_assert( $registry_a->signature() === $registry_b->signature(), 'registry signature must not depend on registration order' );
commerce_assert( 'flight_supplier_a' === $registry_a->all()[0]['provider_id'], 'higher provider priority must sort first' );
$eligible = $registry_a->eligible( 'flight', 'search', 'sandbox' );
commerce_assert( 2 === count( $eligible ) && 'flight_supplier_a' === $eligible[0]['descriptor']['provider_id'], 'eligible providers must be complete and deterministic' );
commerce_assert( array() === $registry_a->eligible( 'car', 'search', 'sandbox' ), 'unknown vertical must have no eligible providers' );
commerce_assert( is_wp_error( $registry_a->register( new Commerce_Search_Adapter( commerce_descriptor( 'flight_supplier_a', 1 ) ) ) ), 'duplicate provider IDs must be rejected' );
$misdeclared = commerce_descriptor( 'flight_supplier_c', 100, array( 'search', 'revalidate' ) );
commerce_assert( is_wp_error( ( new Tra_Vel_Commerce_Provider_Registry( array() ) )->register( new Commerce_Misdeclared_Adapter( $misdeclared ) ) ), 'declared capability must have a matching interface' );
$affiliate = commerce_descriptor( 'activity_supplier_a', 100, array( 'search' ), 'affiliate', array( 'activity' ) );
commerce_assert( is_wp_error( ( new Tra_Vel_Commerce_Provider_Registry( array() ) )->register( new Commerce_Search_Adapter( $affiliate ) ) ), 'affiliate provider must implement reporting' );
$affiliate['capabilities'] = array( 'search', 'revalidate', 'reserve', 'confirm', 'fulfill', 'change', 'cancel', 'refund', 'webhook', 'reconcile', 'report_conversion', 'settlement_reconcile' );
commerce_assert( true === ( new Tra_Vel_Commerce_Provider_Registry( array() ) )->register( new Commerce_Affiliate_Adapter( $affiliate ) ), 'complete affiliate adapter must register' );

$capture_without_authorize = commerce_descriptor( 'package_supplier_b', 100, array( 'search', 'payment_capture' ), 'owned', array( 'package' ) );
commerce_assert( is_wp_error( Tra_Vel_Commerce_Policy::provider_descriptor( $capture_without_authorize ) ), 'payment capture must require authorization capability' );
$payment_capabilities = array( 'search', 'payment_authorize', 'payment_capture', 'payment_void', 'payment_refund' );
$payment_descriptor = commerce_descriptor( 'package_supplier_c', 100, $payment_capabilities, 'owned', array( 'package' ) );
commerce_assert( is_wp_error( ( new Tra_Vel_Commerce_Provider_Registry( array() ) )->register( new Commerce_Full_Adapter( $payment_descriptor ) ) ), 'payment capabilities must require the payment adapter interface' );
commerce_assert( true === ( new Tra_Vel_Commerce_Provider_Registry( array() ) )->register( new Commerce_Payment_Adapter( $payment_descriptor ) ), 'complete payment adapter must register' );

echo "Commerce Core primitives passed ({$assertions} assertions).\n";
