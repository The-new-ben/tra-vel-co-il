<?php
/**
 * Runtime coverage for deterministic order creation from a fresh package.
 */

ob_start();
require __DIR__ . '/validate-commerce-package-runtime.php';
$package_output = trim( ob_get_clean() );
require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-order-factory.php';

$order_assertions = 0;

function tra_vel_commerce_order_expect( $condition, $message ) {
	global $order_assertions;
	$order_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, 'Tra-Vel commerce order runtime failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function tra_vel_commerce_order_expect_error( $value, $code, $message ) {
	tra_vel_commerce_order_expect( is_wp_error( $value ) && $code === $value->get_error_code(), $message );
}

function tra_vel_commerce_order_rehash_package( $package ) {
	$basis = $package;
	unset( $basis['package_digest'] );
	$package['package_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $basis );
	return $package;
}

$fresh_package = $package;
$fresh_package['version'] = 2;
$fresh_package['revalidation'] = array(
	'mode'                    => 'atomic',
	'all_components_required' => true,
	'state'                   => 'fresh',
	'checked_at'              => gmdate( 'Y-m-d\TH:i:s\Z', $context['now'] + 30 ),
);
$fresh_package['updated_at'] = gmdate( 'Y-m-d\TH:i:s\Z', $context['now'] + 30 );
$fresh_package = tra_vel_commerce_order_rehash_package( $fresh_package );

$order_context = array(
	'owner_scope_digest'     => $context['owner_scope_digest'],
	'idempotency_key_digest' => hash( 'sha256', 'checkout-attempt-0001' ),
	'now'                    => $context['now'] + 60,
);
$approval = array(
	'status'          => 'approved',
	'approval_ref'    => 'tv_approval_abcdefghijklmnop',
	'scope_digest'    => hash( 'sha256', 'exact-package-payment-scope' ),
	'decision_digest' => hash( 'sha256', 'approved-package-decision' ),
	'expires_at'      => gmdate( 'Y-m-d\TH:i:s\Z', $context['now'] + 300 ),
);
$factory = new Tra_Vel_Commerce_Order_Factory( 'runtime-server-order-secret-0001' );
$order = $factory->create_from_package( $fresh_package, $approval, $order_context );

tra_vel_commerce_order_expect( is_array( $order ), 'A fresh owner-bound package and current approval must create an order' . ( is_wp_error( $order ) ? ': ' . $order->get_error_code() . ' / ' . $order->get_error_message() : '.' ) );
tra_vel_commerce_order_expect( 1 === preg_match( '/^tv_order_[A-Za-z0-9_-]{16,96}$/', $order['order_ref'] ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $order['order_digest'] ), 'Order identity and integrity must be opaque and digest-bound.' );
tra_vel_commerce_order_expect( 1 === $order['version'] && $order['owner_scope_digest'] === $order_context['owner_scope_digest'] && $order['idempotency_key_digest'] === $order_context['idempotency_key_digest'], 'Order creation must bind version, owner, and idempotency identity.' );
tra_vel_commerce_order_expect( 'package' === $order['selection']['kind'] && $fresh_package['package_ref'] === $order['selection']['package_ref'] && 2 === $order['selection']['package_version'] && $fresh_package['package_digest'] === $order['selection']['package_digest'], 'The order must bind the exact revalidated package revision.' );
tra_vel_commerce_order_expect( 'ready' === $order['checkout']['state'] && 'planning' === $order['overall_state'] && $fresh_package['package_digest'] === $order['checkout']['quote_digest'], 'Approved checkout must be ready while supplier/payment work remains unstarted.' );
tra_vel_commerce_order_expect( 'not_started' === $order['payment']['state'] && 0 === $order['payment']['authorized_amount_minor'] && 0 === $order['payment']['captured_amount_minor'] && null === $order['payment']['payment_intent_ref'], 'Creating an order must not imply authorization, capture, or a payment intent.' );
tra_vel_commerce_order_expect( count( $fresh_package['components'] ) === count( $order['fulfillment']['items'] ) && 'selected' === $order['fulfillment']['summary_state'], 'Every package component must become an independently selected fulfillment item.' );
tra_vel_commerce_order_expect( $fresh_package['pricing']['total_amount_minor'] === $order['pricing']['total_amount_minor'] && $fresh_package['pricing']['currency'] === $order['payment']['currency'], 'Order and payment ledgers must preserve exact package economics.' );
tra_vel_commerce_order_expect( 0 === $order['last_event_sequence'] && 'not_applicable' === $order['settlement']['state'], 'A newly created order must have no fabricated event or settlement history.' );
tra_vel_commerce_order_expect( $order['sandbox_truth']['simulated_inventory'] && ! $order['sandbox_truth']['real_charge'] && ! $order['sandbox_truth']['real_booking'] && ! $order['data_boundary']['raw_payment_data_exposed'], 'Order creation must preserve the non-live and private-data boundary.' );

$component_refs = array_column( $fresh_package['components'], 'component_ref' );
$order_item_refs = array();
foreach ( $order['fulfillment']['items'] as $index => $item ) {
	$order_item_refs[] = $item['order_item_ref'];
	tra_vel_commerce_order_expect( 1 === preg_match( '/^tv_order_item_[A-Za-z0-9_-]{16,96}$/', $item['order_item_ref'] ) && $item['offer_ref'] === $fresh_package['components'][ $index ]['offer_ref'] && 'selected' === $item['state'], 'Each order item must bind one exact component offer without claiming fulfillment.' );
	tra_vel_commerce_order_expect( $item['component_ref'] === $fresh_package['components'][ $index ]['component_ref'] && $item['role'] === $fresh_package['components'][ $index ]['role'] && $item['required'] === $fresh_package['components'][ $index ]['required'] && $item['sequence'] === $index + 1, 'Each order item must preserve component role, requiredness, and sequence for orchestration and recovery.' );
	tra_vel_commerce_order_expect( isset( $item['offer_digest'] ) && hash_equals( $item['offer_digest'], $fresh_package['components'][ $index ]['offer_digest'] ), 'Each order item must preserve the exact selected offer digest for server-only routing resolution.' );
}
tra_vel_commerce_order_expect( count( $order_item_refs ) === count( array_unique( $order_item_refs ) ), 'Order item references must be unique.' );
foreach ( $order['pricing']['line_items'] as $line ) {
	tra_vel_commerce_order_expect( in_array( $line['source_ref'], $component_refs, true ) && ! isset( $line['component_ref'] ), 'Every order price line must use its component as a typed source reference.' );
}

$digest_basis = $order;
unset( $digest_basis['order_digest'] );
tra_vel_commerce_order_expect( hash_equals( $order['order_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $digest_basis ) ), 'Order digest must cover the complete closed snapshot.' );

$repeat = $factory->create_from_package( $fresh_package, $approval, $order_context );
tra_vel_commerce_order_expect( $order === $repeat, 'The same package, owner, approval, clock, idempotency key, and secret must replay deterministically.' );
$other_attempt = $order_context;
$other_attempt['idempotency_key_digest'] = hash( 'sha256', 'checkout-attempt-0002' );
$other_order = $factory->create_from_package( $fresh_package, $approval, $other_attempt );
tra_vel_commerce_order_expect( $other_order['order_ref'] !== $order['order_ref'] && $other_order['pricing'] === $order['pricing'], 'A distinct checkout attempt must receive a distinct identity without changing economics.' );
$other_secret_order = ( new Tra_Vel_Commerce_Order_Factory( 'runtime-server-order-secret-0002' ) )->create_from_package( $fresh_package, $approval, $order_context );
tra_vel_commerce_order_expect( $other_secret_order['order_ref'] !== $order['order_ref'] && $other_secret_order['pricing'] === $order['pricing'], 'Secret rotation must change opaque order identities only.' );

$pending = $approval;
$pending['status'] = 'pending';
$pending_order = $factory->create_from_package( $fresh_package, $pending, $order_context );
tra_vel_commerce_order_expect( 'awaiting_approval' === $pending_order['checkout']['state'] && 'awaiting_approval' === $pending_order['overall_state'], 'Pending approval must prevent a ready checkout projection.' );
$not_required = array( 'status' => 'not_required', 'approval_ref' => null, 'scope_digest' => null, 'decision_digest' => null, 'expires_at' => null );
$not_required_order = $factory->create_from_package( $fresh_package, $not_required, $order_context );
tra_vel_commerce_order_expect( 'ready' === $not_required_order['checkout']['state'], 'A server-authorized not-required policy may create a ready checkout without fake approval evidence.' );

$wrong_owner = $order_context;
$wrong_owner['owner_scope_digest'] = hash( 'sha256', 'different-order-owner' );
tra_vel_commerce_order_expect_error( $factory->create_from_package( $fresh_package, $approval, $wrong_owner ), 'tra_vel_commerce_order_package_invalid', 'A different owner must not order the package.' );
$tampered_digest = $fresh_package;
$tampered_digest['title'] = 'Tampered title';
tra_vel_commerce_order_expect_error( $factory->create_from_package( $tampered_digest, $approval, $order_context ), 'tra_vel_commerce_order_package_digest_invalid', 'Package mutations must fail the immutable digest.' );
$not_fresh = $fresh_package;
$not_fresh['revalidation']['state'] = 'changed';
$not_fresh = tra_vel_commerce_order_rehash_package( $not_fresh );
tra_vel_commerce_order_expect_error( $factory->create_from_package( $not_fresh, $approval, $order_context ), 'tra_vel_commerce_order_package_revalidation_required', 'A materially changed package must require acceptance before checkout.' );
$expired = $fresh_package;
$expired['expires_at'] = gmdate( 'Y-m-d\TH:i:s\Z', $order_context['now'] );
$expired = tra_vel_commerce_order_rehash_package( $expired );
tra_vel_commerce_order_expect_error( $factory->create_from_package( $expired, $approval, $order_context ), 'tra_vel_commerce_order_package_expired', 'An expired revalidated package must not reach checkout.' );
$detached_line = $fresh_package;
$detached_line['pricing']['line_items'][0]['component_ref'] = 'tv_component_zzzzzzzzzzzzzzzz';
$detached_line = tra_vel_commerce_order_rehash_package( $detached_line );
tra_vel_commerce_order_expect_error( $factory->create_from_package( $detached_line, $approval, $order_context ), 'tra_vel_commerce_order_package_money_invalid', 'A detached package money line must fail closed.' );
$unknown_package = $fresh_package;
$unknown_package['browser_note'] = 'forbidden';
$unknown_package = tra_vel_commerce_order_rehash_package( $unknown_package );
tra_vel_commerce_order_expect_error( $factory->create_from_package( $unknown_package, $approval, $order_context ), 'tra_vel_commerce_order_package_invalid', 'Unknown package fields must fail the closed order boundary.' );
$expired_approval = $approval;
$expired_approval['expires_at'] = gmdate( 'Y-m-d\TH:i:s\Z', $order_context['now'] );
tra_vel_commerce_order_expect_error( $factory->create_from_package( $fresh_package, $expired_approval, $order_context ), 'tra_vel_commerce_order_approval_invalid', 'Expired approval evidence must not authorize checkout.' );
$bad_not_required = $not_required;
$bad_not_required['decision_digest'] = hash( 'sha256', 'orphan-evidence' );
tra_vel_commerce_order_expect_error( $factory->create_from_package( $fresh_package, $bad_not_required, $order_context ), 'tra_vel_commerce_order_approval_invalid', 'Not-required approval cannot smuggle orphan authorization evidence.' );
$unknown_context = $order_context;
$unknown_context['ip'] = '192.0.2.1';
tra_vel_commerce_order_expect_error( $factory->create_from_package( $fresh_package, $approval, $unknown_context ), 'tra_vel_commerce_order_context_invalid', 'Unknown context fields must fail the closed owner/idempotency boundary.' );

$encoded_order = wp_json_encode( $order );
tra_vel_commerce_order_expect( false === strpos( $encoded_order, 'private_product_ref' ) && false === strpos( $encoded_order, 'px_' ) && false === strpos( $encoded_order, 'commission' ) && false === strpos( $encoded_order, '"raw_supplier_reference":' ), 'Order output must expose no fixture, commission, or raw supplier data.' );

if ( '' !== $package_output ) {
	echo $package_output . PHP_EOL;
}
echo 'Tra-Vel commerce order runtime passed (' . $order_assertions . ' checkout-boundary assertions).' . PHP_EOL;
