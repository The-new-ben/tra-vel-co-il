<?php
/**
 * Runtime coverage for closed, idempotent, provider-bound commerce operations.
 */

ob_start();
require __DIR__ . '/validate-commerce-order-runtime.php';
$order_output = trim( ob_get_clean() );
require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-operation-factory.php';

$operation_assertions = 0;

function tra_vel_commerce_operation_expect( $condition, $message ) {
	global $operation_assertions;
	$operation_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, 'Tra-Vel commerce operation runtime failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function tra_vel_commerce_operation_expect_error( $value, $code, $status, $message ) {
	$data = is_wp_error( $value ) ? $value->get_error_data() : null;
	tra_vel_commerce_operation_expect(
		is_wp_error( $value ) && $code === $value->get_error_code() && is_array( $data ) && $status === $data['status'],
		$message
	);
}

function tra_vel_commerce_operation_rehash_order( $snapshot ) {
	$basis = $snapshot;
	unset( $basis['order_digest'] );
	$snapshot['order_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $basis );
	return $snapshot;
}

function tra_vel_commerce_operation_context( $snapshot, $label, $now ) {
	return array(
		'owner_scope_digest'     => $snapshot['owner_scope_digest'],
		'idempotency_key_digest' => hash( 'sha256', 'operation-idempotency|' . $label ),
		'now'                    => $now,
	);
}

function tra_vel_commerce_operation_approval( $required, $scope_digest, $now ) {
	if ( ! $required ) {
		return array(
			'required'        => false,
			'approval_ref'    => null,
			'approval_digest' => null,
			'status'          => 'not_required',
			'scope_digest'    => null,
			'expires_at'      => null,
		);
	}
	return array(
		'required'        => true,
		'approval_ref'    => 'tv_approval_abcdefghijklmnop',
		'approval_digest' => hash( 'sha256', 'operation-approval|' . $scope_digest ),
		'status'          => 'approved',
		'scope_digest'    => $scope_digest,
		'expires_at'      => gmdate( 'Y-m-d\TH:i:s\Z', $now + 300 ),
	);
}

function tra_vel_commerce_operation_item_target( $snapshot, $item ) {
	return array(
		'kind'          => 'order_item',
		'ref'           => $item['order_item_ref'],
		'version'       => $snapshot['version'],
		'target_digest' => Tra_Vel_Commerce_Policy::canonical_digest( $item ),
	);
}

function tra_vel_commerce_operation_order_target( $snapshot ) {
	return array(
		'kind'          => 'order',
		'ref'           => $snapshot['order_ref'],
		'version'       => $snapshot['version'],
		'target_digest' => $snapshot['order_digest'],
	);
}

function tra_vel_commerce_operation_package_target( $snapshot ) {
	return array(
		'kind'          => 'package',
		'ref'           => $snapshot['selection']['package_ref'],
		'version'       => $snapshot['selection']['package_version'],
		'target_digest' => $snapshot['selection']['package_digest'],
	);
}

function tra_vel_commerce_operation_payment_target( $snapshot ) {
	return array(
		'kind'          => 'payment_intent',
		'ref'           => $snapshot['payment']['payment_intent_ref'],
		'version'       => $snapshot['version'],
		'target_digest' => Tra_Vel_Commerce_Policy::canonical_digest( $snapshot['payment'] ),
	);
}

function tra_vel_commerce_operation_generic_target( $kind, $ref, $version, $label ) {
	return array(
		'kind'          => $kind,
		'ref'           => $ref,
		'version'       => $version,
		'target_digest' => hash( 'sha256', 'operation-target|' . $label ),
	);
}

function tra_vel_commerce_operation_command( $type, $vertical, $provider_id, $target, $required, $now, $label = null ) {
	$label = null === $label ? $type : $label;
	$scope_digest = hash( 'sha256', 'operation-scope|' . $label );
	return array(
		'type'             => $type,
		'vertical'         => $vertical,
		'provider_id'      => $provider_id,
		'target'           => $target,
		'scope_digest'     => $scope_digest,
		'input_digest'     => hash( 'sha256', 'operation-input|' . $label ),
		'approval'         => tra_vel_commerce_operation_approval( $required, $scope_digest, $now ),
		'maximum_attempts' => 3,
	);
}

function tra_vel_commerce_operation_find_item( $snapshot, $provider_id ) {
	foreach ( $snapshot['fulfillment']['items'] as $item ) {
		if ( $provider_id === $item['provider_id'] ) {
			return $item;
		}
	}
	return null;
}

$operation_now = $order_context['now'] + 1;
$factory = new Tra_Vel_Commerce_Operation_Factory( 'runtime-server-operation-secret-0001' );
$flight_item = tra_vel_commerce_operation_find_item( $order, 'flight_supplier_a' );
tra_vel_commerce_operation_expect( is_array( $flight_item ), 'The checkout fixture must expose the provider-bound flight item used by operation tests.' );

/* Build a separate order whose flight component belongs to the one true
 * affiliate provider. Direct suppliers must never be used to make an
 * affiliate-operation test pass. */
$affiliate_offer = tra_vel_commerce_offer_by_provider( $result, 'flight_supplier_c' );
tra_vel_commerce_operation_expect( is_array( $affiliate_offer ), 'The search fixture must expose the true affiliate flight offer.' );
$affiliate_selection = $selection;
$affiliate_selection['title'] = 'Thailand family sandbox package with affiliate flight handoff';
$affiliate_selection['components'][0] = array(
	'offer_ref'     => $affiliate_offer['offer_ref'],
	'offer_version' => $affiliate_offer['version'],
	'role'          => 'outbound',
	'required'      => true,
	'day'           => 1,
);
$affiliate_composer = new Tra_Vel_Commerce_Package_Composer( 'runtime-server-package-affiliate-secret-0001' );
$affiliate_package = $affiliate_composer->compose( $result['session'], $all_offers, $affiliate_selection, $context );
tra_vel_commerce_operation_expect( is_array( $affiliate_package ), 'A package containing the affiliate flight handoff must compose without granting supplier-mutation capability.' );
$affiliate_package['version'] = 2;
$affiliate_package['revalidation'] = array(
	'mode'                    => 'atomic',
	'all_components_required' => true,
	'state'                   => 'fresh',
	'checked_at'              => gmdate( 'Y-m-d\TH:i:s\Z', $context['now'] + 30 ),
);
$affiliate_package['updated_at'] = gmdate( 'Y-m-d\TH:i:s\Z', $context['now'] + 30 );
$affiliate_package = tra_vel_commerce_order_rehash_package( $affiliate_package );
$affiliate_order_context = $order_context;
$affiliate_order_context['idempotency_key_digest'] = hash( 'sha256', 'checkout-attempt-affiliate-0001' );
$affiliate_order = ( new Tra_Vel_Commerce_Order_Factory( 'runtime-server-order-affiliate-secret-0001' ) )->create_from_package( $affiliate_package, $approval, $affiliate_order_context );
tra_vel_commerce_operation_expect( is_array( $affiliate_order ), 'A valid affiliate-handoff component must remain representable in a checkout order.' );
$affiliate_item = tra_vel_commerce_operation_find_item( $affiliate_order, 'flight_supplier_c' );
tra_vel_commerce_operation_expect( is_array( $affiliate_item ), 'The affiliate checkout must preserve its exact provider-bound flight item.' );

$created_by_type = array();
$item_types = array( 'reserve', 'confirm', 'fulfill', 'change', 'cancel', 'refund' );
foreach ( $item_types as $type ) {
	$command = tra_vel_commerce_operation_command(
		$type,
		$flight_item['vertical'],
		$flight_item['provider_id'],
		tra_vel_commerce_operation_item_target( $order, $flight_item ),
		true,
		$operation_now,
		'item-' . $type
	);
	$operation_context = tra_vel_commerce_operation_context( $order, 'item-' . $type, $operation_now );
	$operation = $factory->create( $order, $command, $operation_context );
	tra_vel_commerce_operation_expect(
		is_array( $operation ),
		'An approved ' . $type . ' command must create a queued provider-bound operation' . ( is_wp_error( $operation ) ? ': ' . $operation->get_error_code() . ' / ' . $operation->get_error_message() : '.' )
	);
	$created_by_type[ $type ] = $operation;
	tra_vel_commerce_operation_expect( $type === $operation['type'] && 'order_item' === $operation['target']['kind'] && $flight_item['order_item_ref'] === $operation['target']['ref'], ucfirst( $type ) . ' must remain bound to the exact order item.' );
	tra_vel_commerce_operation_expect( $flight_item['vertical'] === $operation['vertical'] && $flight_item['provider_id'] === $operation['provider_id'], ucfirst( $type ) . ' must remain bound to the item vertical and provider.' );
}

$reserve = $created_by_type['reserve'];
$reserve_command = tra_vel_commerce_operation_command(
	'reserve',
	$flight_item['vertical'],
	$flight_item['provider_id'],
	tra_vel_commerce_operation_item_target( $order, $flight_item ),
	true,
	$operation_now,
	'item-reserve'
);
$reserve_context = tra_vel_commerce_operation_context( $order, 'item-reserve', $operation_now );
$expected_operation_keys = array(
	'contract_version',
	'environment',
	'operation_ref',
	'order_ref',
	'expected_order_version',
	'type',
	'vertical',
	'provider_id',
	'target',
	'state',
	'idempotency_key_digest',
	'request_digest',
	'scope_digest',
	'approval',
	'attempt',
	'result',
	'created_at',
	'updated_at',
	'dispatched_at',
	'completed_at',
	'sandbox_truth',
	'data_boundary',
);
tra_vel_commerce_operation_expect( $expected_operation_keys === array_keys( $reserve ), 'Operation output must be an exact closed projection.' );
tra_vel_commerce_operation_expect( '1.0.0' === $reserve['contract_version'] && 'sandbox' === $reserve['environment'] && 'queued' === $reserve['state'], 'A factory result must remain a queued sandbox operation and must not claim execution.' );
tra_vel_commerce_operation_expect( 1 === preg_match( '/^tv_operation_[A-Za-z0-9_-]{16,96}$/', $reserve['operation_ref'] ), 'Operation identity must be opaque and typed.' );
tra_vel_commerce_operation_expect( $order['order_ref'] === $reserve['order_ref'] && $order['version'] === $reserve['expected_order_version'], 'Operation output must bind the exact order identity and revision.' );
tra_vel_commerce_operation_expect( $reserve_context['idempotency_key_digest'] === $reserve['idempotency_key_digest'] && $reserve_command['scope_digest'] === $reserve['scope_digest'], 'Operation output must preserve its idempotency and approval scope digests.' );
tra_vel_commerce_operation_expect( array( 'required', 'approval_ref', 'approval_digest', 'status' ) === array_keys( $reserve['approval'] ), 'Operation approval output must expose only required, reference, digest, and status.' );
tra_vel_commerce_operation_expect( ! array_key_exists( 'scope_digest', $reserve['approval'] ) && ! array_key_exists( 'expires_at', $reserve['approval'] ), 'Command-only approval scope and expiry must not leak into the operation projection.' );
tra_vel_commerce_operation_expect( true === $reserve['approval']['required'] && 'approved' === $reserve['approval']['status'], 'A consequential operation must retain approved evidence in its public projection.' );
tra_vel_commerce_operation_expect( array( 'number' => 0, 'maximum' => 3, 'reconciliation_count' => 0 ) === $reserve['attempt'], 'A new operation must begin before its first bounded attempt or reconciliation.' );
tra_vel_commerce_operation_expect(
	array(
		'receipt_digest'                => null,
		'error_code'                   => null,
		'retryable'                    => false,
		'simulated_side_effect_executed'=> false,
		'real_side_effect_executed'    => false,
		'reconciled'                   => false,
	) === $reserve['result'],
	'Operation creation must not fabricate a provider receipt, execution, retry, or reconciliation.'
);
tra_vel_commerce_operation_expect( gmdate( 'Y-m-d\TH:i:s\Z', $operation_now ) === $reserve['created_at'] && $reserve['created_at'] === $reserve['updated_at'] && null === $reserve['dispatched_at'] && null === $reserve['completed_at'], 'Operation timestamps must reflect creation only.' );
tra_vel_commerce_operation_expect( $order['sandbox_truth'] === $reserve['sandbox_truth'] && $order['data_boundary'] === $reserve['data_boundary'], 'Operation output must preserve the sandbox truth and private-data boundary.' );
$request_basis = array(
	'type'         => $reserve_command['type'],
	'vertical'     => $reserve_command['vertical'],
	'provider_id'  => $reserve_command['provider_id'],
	'target'       => $reserve_command['target'],
	'scope_digest' => $reserve_command['scope_digest'],
	'input_digest' => $reserve_command['input_digest'],
);
tra_vel_commerce_operation_expect( hash_equals( $reserve['request_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $request_basis ) ), 'Request digest must bind the full provider command without exposing its private input digest.' );

$repeat = $factory->create( $order, $reserve_command, $reserve_context );
tra_vel_commerce_operation_expect( $reserve === $repeat, 'The same order, command, context, clock, idempotency key, and secret must replay deterministically.' );
$distinct_context = $reserve_context;
$distinct_context['idempotency_key_digest'] = hash( 'sha256', 'operation-idempotency|distinct-reservation' );
$distinct = $factory->create( $order, $reserve_command, $distinct_context );
tra_vel_commerce_operation_expect( is_array( $distinct ) && $reserve['operation_ref'] !== $distinct['operation_ref'] && $reserve['request_digest'] === $distinct['request_digest'], 'A distinct idempotency key must receive a distinct identity without changing the underlying request.' );
$other_secret = ( new Tra_Vel_Commerce_Operation_Factory( 'runtime-server-operation-secret-0002' ) )->create( $order, $reserve_command, $reserve_context );
tra_vel_commerce_operation_expect( is_array( $other_secret ) && $reserve['operation_ref'] !== $other_secret['operation_ref'] && $reserve['request_digest'] === $other_secret['request_digest'], 'Secret rotation must change opaque operation identity without changing request semantics.' );

$payment_order = $order;
$payment_order['payment']['payment_intent_ref'] = 'tv_payment_abcdefghijklmnop';
$payment_order = tra_vel_commerce_operation_rehash_order( $payment_order );
$authorize_command = tra_vel_commerce_operation_command( 'authorize_payment', 'package', 'package_supplier_a', tra_vel_commerce_operation_order_target( $order ), true, $operation_now, 'payment-authorize' );
$authorize_context = tra_vel_commerce_operation_context( $order, 'payment-authorize', $operation_now );
$authorize_operation = $factory->create( $order, $authorize_command, $authorize_context );
tra_vel_commerce_operation_expect( is_array( $authorize_operation ) && 'order' === $authorize_operation['target']['kind'] && $order['order_ref'] === $authorize_operation['target']['ref'], 'Payment authorization must bind the package payment provider to the exact order revision.' );
$created_by_type['authorize_payment'] = $authorize_operation;
foreach ( array( 'capture_payment', 'void_payment' ) as $type ) {
	$command = tra_vel_commerce_operation_command( $type, 'package', 'package_supplier_a', tra_vel_commerce_operation_payment_target( $payment_order ), true, $operation_now, 'payment-' . $type );
	$operation_context = tra_vel_commerce_operation_context( $payment_order, 'payment-' . $type, $operation_now );
	$operation = $factory->create( $payment_order, $command, $operation_context );
	tra_vel_commerce_operation_expect( is_array( $operation ) && 'payment_intent' === $operation['target']['kind'] && $payment_order['payment']['payment_intent_ref'] === $operation['target']['ref'], $type . ' must bind the package payment provider to the exact payment-intent snapshot.' );
	$created_by_type[ $type ] = $operation;
}

$payment_refund = tra_vel_commerce_operation_command( 'refund', 'package', 'package_supplier_a', tra_vel_commerce_operation_payment_target( $payment_order ), true, $operation_now, 'payment-refund' );
$payment_refund_context = tra_vel_commerce_operation_context( $payment_order, 'payment-refund', $operation_now );
$payment_refund_operation = $factory->create( $payment_order, $payment_refund, $payment_refund_context );
tra_vel_commerce_operation_expect( is_array( $payment_refund_operation ) && 'payment_intent' === $payment_refund_operation['target']['kind'], 'A payment refund must bind the exact current payment-intent snapshot and payment_refund capability.' );

$non_consequential_specs = array(
	array( 'revalidate', 'package', 'package_supplier_a', tra_vel_commerce_operation_package_target( $order ), $order ),
	array( 'prepare_checkout', 'package', 'package_supplier_a', tra_vel_commerce_operation_order_target( $order ), $order ),
	array( 'record_affiliate_click', $affiliate_item['vertical'], $affiliate_item['provider_id'], tra_vel_commerce_operation_item_target( $affiliate_order, $affiliate_item ), $affiliate_order ),
	array( 'report_conversion', $affiliate_item['vertical'], $affiliate_item['provider_id'], tra_vel_commerce_operation_item_target( $affiliate_order, $affiliate_item ), $affiliate_order ),
	array( 'ingest_webhook', $flight_item['vertical'], $flight_item['provider_id'], tra_vel_commerce_operation_item_target( $order, $flight_item ), $order ),
	array( 'reconcile', $flight_item['vertical'], $flight_item['provider_id'], tra_vel_commerce_operation_item_target( $order, $flight_item ), $order ),
);
foreach ( $non_consequential_specs as $spec ) {
	$type = $spec[0];
	$command = tra_vel_commerce_operation_command( $type, $spec[1], $spec[2], $spec[3], false, $operation_now, 'non-consequential-' . $type );
	$operation_order = $spec[4];
	$operation_context = tra_vel_commerce_operation_context( $operation_order, 'non-consequential-' . $type, $operation_now );
	$operation = $factory->create( $operation_order, $command, $operation_context );
	tra_vel_commerce_operation_expect( is_array( $operation ) && false === $operation['approval']['required'] && 'not_required' === $operation['approval']['status'], $type . ' must use its exact provider capability without inventing approval evidence.' );
	$created_by_type[ $type ] = $operation;
}

$settlement_ref = 'tv_settlement_abcdefghijklmnop';
$settlement_order = $affiliate_order;
$settlement_order['settlement'] = array( 'state' => 'click_recorded', 'settlement_refs' => array( $settlement_ref ) );
$settlement_order = tra_vel_commerce_operation_rehash_order( $settlement_order );
$settlement_command = tra_vel_commerce_operation_command( 'reconcile_settlement', 'flight', 'flight_supplier_c', tra_vel_commerce_operation_generic_target( 'settlement', $settlement_ref, 1, 'settlement-revision-1' ), false, $operation_now, 'settlement-reconciliation' );
$settlement_context = tra_vel_commerce_operation_context( $settlement_order, 'settlement-reconciliation', $operation_now );
$settlement_operation = $factory->create( $settlement_order, $settlement_command, $settlement_context );
tra_vel_commerce_operation_expect( is_array( $settlement_operation ) && 'settlement' === $settlement_operation['target']['kind'] && $settlement_ref === $settlement_operation['target']['ref'], 'Settlement reconciliation must target a typed settlement already bound to this order.' );
$created_by_type['reconcile_settlement'] = $settlement_operation;

$expected_types = array_merge( array_keys( Tra_Vel_Commerce_Operation_Factory::TYPE_CAPABILITIES ), array( 'refund' ) );
sort( $expected_types, SORT_STRING );
$actual_types = array_keys( $created_by_type );
sort( $actual_types, SORT_STRING );
tra_vel_commerce_operation_expect( $expected_types === $actual_types, 'Runtime coverage must create every supported operation type at least once.' );

$bad = $reserve_command;
$bad['browser_note'] = 'forbidden';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_command_invalid', 400, 'Unknown command fields must fail the closed contract.' );
$bad = $reserve_command;
unset( $bad['input_digest'] );
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_command_invalid', 400, 'Missing command fields must fail the closed contract.' );
$bad = $reserve_command;
$bad['type'] = 'book_everything';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_command_invalid', 400, 'Unsupported command types must fail closed.' );
$bad = $reserve_command;
$bad['vertical'] = 'car_rental';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_command_invalid', 400, 'Unsupported verticals must not be guessed into another product.' );
$bad = $reserve_command;
$bad['provider_id'] = 'x';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_command_invalid', 400, 'Malformed provider identities must fail closed.' );
$bad = $reserve_command;
$bad['scope_digest'] = 'not-a-digest';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_command_invalid', 400, 'Malformed scope digests must fail closed.' );
$bad = $reserve_command;
$bad['input_digest'] = hash( 'sha1', 'short-input' );
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_command_invalid', 400, 'Malformed input digests must fail closed.' );
foreach ( array( 0, 21, '3' ) as $bad_maximum ) {
	$bad = $reserve_command;
	$bad['maximum_attempts'] = $bad_maximum;
	tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_command_invalid', 400, 'Retry bounds must be integer values from one through twenty.' );
}

$bad_context = $reserve_context;
$bad_context['ip'] = '192.0.2.1';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $reserve_command, $bad_context ), 'tra_vel_commerce_operation_context_invalid', 400, 'Unknown context fields must fail the closed owner/idempotency/clock boundary.' );
$bad_context = $reserve_context;
unset( $bad_context['now'] );
tra_vel_commerce_operation_expect_error( $factory->create( $order, $reserve_command, $bad_context ), 'tra_vel_commerce_operation_context_invalid', 400, 'Missing context fields must fail closed.' );
$bad_context = $reserve_context;
$bad_context['owner_scope_digest'] = 'invalid-owner';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $reserve_command, $bad_context ), 'tra_vel_commerce_operation_context_invalid', 400, 'Malformed owner context must fail closed.' );
$bad_context = $reserve_context;
$bad_context['idempotency_key_digest'] = 'raw-idempotency-key';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $reserve_command, $bad_context ), 'tra_vel_commerce_operation_context_invalid', 400, 'Raw idempotency keys must never cross the context boundary.' );
foreach ( array( 0, '1', 1.0 ) as $bad_now ) {
	$bad_context = $reserve_context;
	$bad_context['now'] = $bad_now;
	tra_vel_commerce_operation_expect_error( $factory->create( $order, $reserve_command, $bad_context ), 'tra_vel_commerce_operation_context_invalid', 400, 'The operation clock must be a positive integer UTC timestamp.' );
}

$wrong_owner_context = $reserve_context;
$wrong_owner_context['owner_scope_digest'] = hash( 'sha256', 'different-operation-owner' );
tra_vel_commerce_operation_expect_error( $factory->create( $order, $reserve_command, $wrong_owner_context ), 'tra_vel_commerce_operation_order_invalid', 400, 'A different owner must not create an operation for this order.' );
$tampered_order = $order;
$tampered_order['pricing']['total_amount_minor']++;
tra_vel_commerce_operation_expect_error( $factory->create( $tampered_order, $reserve_command, $reserve_context ), 'tra_vel_commerce_operation_order_digest_invalid', 409, 'Order mutations must fail the immutable digest.' );
$bad_digest_order = $order;
$bad_digest_order['order_digest'] = 'not-a-digest';
tra_vel_commerce_operation_expect_error( $factory->create( $bad_digest_order, $reserve_command, $reserve_context ), 'tra_vel_commerce_operation_order_invalid', 400, 'Malformed order digests must fail before target evaluation.' );
$bad_version_order = $order;
$bad_version_order['version'] = 0;
tra_vel_commerce_operation_expect_error( $factory->create( $bad_version_order, $reserve_command, $reserve_context ), 'tra_vel_commerce_operation_order_invalid', 400, 'Invalid order versions must fail closed.' );
$expired_order = $order;
$expired_order['expires_at'] = gmdate( 'Y-m-d\TH:i:s\Z', $operation_now );
$expired_order = tra_vel_commerce_operation_rehash_order( $expired_order );
tra_vel_commerce_operation_expect_error( $factory->create( $expired_order, $reserve_command, $reserve_context ), 'tra_vel_commerce_operation_order_expired', 409, 'An order expiring at the operation clock must not authorize work.' );
$expired_confirm = tra_vel_commerce_operation_command( 'confirm', $flight_item['vertical'], $flight_item['provider_id'], tra_vel_commerce_operation_item_target( $expired_order, $flight_item ), true, $operation_now, 'expired-confirm' );
tra_vel_commerce_operation_expect_error( $factory->create( $expired_order, $expired_confirm, tra_vel_commerce_operation_context( $expired_order, 'expired-confirm', $operation_now ) ), 'tra_vel_commerce_operation_order_expired', 409, 'Supplier confirmation remains quote-bound and must fail after order expiry.' );
$expired_authorize = tra_vel_commerce_operation_command( 'authorize_payment', 'package', 'package_supplier_a', tra_vel_commerce_operation_order_target( $expired_order ), true, $operation_now, 'expired-authorize' );
tra_vel_commerce_operation_expect_error( $factory->create( $expired_order, $expired_authorize, tra_vel_commerce_operation_context( $expired_order, 'expired-authorize', $operation_now ) ), 'tra_vel_commerce_operation_order_expired', 409, 'A new payment authorization remains quote-bound and must fail after order expiry.' );
foreach ( array( 'fulfill', 'change', 'cancel', 'refund' ) as $repair_type ) {
	$repair_command = tra_vel_commerce_operation_command( $repair_type, $flight_item['vertical'], $flight_item['provider_id'], tra_vel_commerce_operation_item_target( $expired_order, $flight_item ), true, $operation_now, 'post-expiry-' . $repair_type );
	$repair_operation = $factory->create( $expired_order, $repair_command, tra_vel_commerce_operation_context( $expired_order, 'post-expiry-' . $repair_type, $operation_now ) );
	tra_vel_commerce_operation_expect( is_array( $repair_operation ) && $repair_type === $repair_operation['type'], 'An expired shopping quote must not block already-purchased trip ' . $repair_type . ' operations.' );
}
$not_ready_order = $order;
$not_ready_order['checkout']['state'] = 'awaiting_approval';
$not_ready_order = tra_vel_commerce_operation_rehash_order( $not_ready_order );
tra_vel_commerce_operation_expect_error( $factory->create( $not_ready_order, $reserve_command, $reserve_context ), 'tra_vel_commerce_operation_checkout_not_ready', 409, 'An order that is not checkout-ready must not authorize operations.' );
$unknown_order = $order;
$unknown_order['private_note'] = 'forbidden';
$unknown_order = tra_vel_commerce_operation_rehash_order( $unknown_order );
tra_vel_commerce_operation_expect_error( $factory->create( $unknown_order, $reserve_command, $reserve_context ), 'tra_vel_commerce_operation_order_invalid', 400, 'Unknown order fields must fail the closed operation boundary.' );
$next_order = $order;
$next_order['version']++;
$next_order = tra_vel_commerce_operation_rehash_order( $next_order );
tra_vel_commerce_operation_expect_error( $factory->create( $next_order, $reserve_command, $reserve_context ), 'tra_vel_commerce_operation_target_mismatch', 409, 'A command for an earlier order version must fail against a newer authoritative revision.' );

$bad = $reserve_command;
$bad['target']['ref'] = 'tv_order_item_zzzzzzzzzzzzzzzz';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_target_mismatch', 409, 'A different order-item reference must fail target binding.' );
$bad = $reserve_command;
$bad['target']['ref'] = $order['order_ref'];
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_target_invalid', 400, 'A reference of the wrong aggregate kind must fail typed target validation.' );
$bad = $reserve_command;
$bad['target']['version']++;
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_target_mismatch', 409, 'A stale or future item target version must fail.' );
$bad = $reserve_command;
$bad['target']['target_digest'] = hash( 'sha256', 'different-item-snapshot' );
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_target_mismatch', 409, 'A target digest for another item snapshot must fail.' );
$bad = $reserve_command;
$bad['target']['kind'] = 'order';
$bad['target']['ref'] = $order['order_ref'];
$bad['target']['target_digest'] = $order['order_digest'];
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_target_invalid', 400, 'A fulfillment command must target one item, never the aggregate order.' );
$bad = $reserve_command;
$bad['target']['raw_supplier_reference'] = 'supplier-private-123';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_target_invalid', 400, 'A target cannot smuggle a raw supplier reference.' );
$bad = $reserve_command;
$bad['provider_id'] = 'flight_supplier_b';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_target_mismatch', 409, 'An order item cannot be rerouted to a different provider.' );
$bad = $reserve_command;
$bad['vertical'] = 'accommodation';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_target_mismatch', 409, 'An order item cannot be relabeled as another vertical.' );

$incapable_order = $order;
$incapable_order['fulfillment']['items'][0]['provider_id'] = 'flight_supplier_c';
$incapable_order['fulfillment']['items'][0]['vertical'] = 'flight';
$incapable_order = tra_vel_commerce_operation_rehash_order( $incapable_order );
$incapable_item = $incapable_order['fulfillment']['items'][0];
$incapable_command = tra_vel_commerce_operation_command( 'reserve', 'flight', 'flight_supplier_c', tra_vel_commerce_operation_item_target( $incapable_order, $incapable_item ), true, $operation_now, 'incapable-reserve' );
$incapable_context = tra_vel_commerce_operation_context( $incapable_order, 'incapable-reserve', $operation_now );
tra_vel_commerce_operation_expect_error( $factory->create( $incapable_order, $incapable_command, $incapable_context ), 'tra_vel_commerce_operation_capability_unavailable', 409, 'A ready provider without reserve capability must not receive a reserve operation.' );
$unknown_provider = tra_vel_commerce_operation_command( 'revalidate', 'flight', 'unknown_supplier_a', tra_vel_commerce_operation_package_target( $order ), false, $operation_now, 'unknown-provider' );
tra_vel_commerce_operation_expect_error( $factory->create( $order, $unknown_provider, tra_vel_commerce_operation_context( $order, 'unknown-provider', $operation_now ) ), 'tra_vel_commerce_operation_provider_unavailable', 409, 'An unknown provider must not receive an operation.' );
$unknown_settlement = $settlement_command;
$unknown_settlement['target']['ref'] = 'tv_settlement_zzzzzzzzzzzzzzzz';
tra_vel_commerce_operation_expect_error( $factory->create( $settlement_order, $unknown_settlement, $settlement_context ), 'tra_vel_commerce_operation_target_mismatch', 409, 'Settlement reconciliation cannot target a settlement that is not bound to this order.' );

$payment_command = tra_vel_commerce_operation_command( 'authorize_payment', 'package', 'package_supplier_a', tra_vel_commerce_operation_order_target( $order ), true, $operation_now, 'payment-authorize-binding' );
$payment_context = tra_vel_commerce_operation_context( $order, 'payment-authorize-binding', $operation_now );
$bad = $payment_command;
$bad['target']['ref'] = 'tv_order_zzzzzzzzzzzzzzzz';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $payment_context ), 'tra_vel_commerce_operation_target_mismatch', 409, 'Payment authorization must target this exact order reference.' );
$bad = $payment_command;
$bad['target']['version']++;
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $payment_context ), 'tra_vel_commerce_operation_target_mismatch', 409, 'Payment authorization must target the current order version.' );
$bad = $payment_command;
$bad['target']['target_digest'] = hash( 'sha256', 'different-order-snapshot' );
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $payment_context ), 'tra_vel_commerce_operation_target_mismatch', 409, 'Payment authorization must bind the exact order digest.' );
$bad = $payment_command;
$bad['target'] = tra_vel_commerce_operation_item_target( $order, $flight_item );
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $payment_context ), 'tra_vel_commerce_operation_target_invalid', 400, 'Payment authorization cannot target a fulfillment item.' );
$bad = $payment_command;
$bad['provider_id'] = 'flight_supplier_a';
$bad['vertical'] = 'flight';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $payment_context ), 'tra_vel_commerce_operation_capability_unavailable', 409, 'A provider without payment authorization capability must not receive a payment command.' );

$bad = $reserve_command;
$bad['approval']['required'] = false;
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_approval_invalid', 400, 'A consequential command cannot disable its approval requirement.' );
$bad = $reserve_command;
$bad['approval']['status'] = 'pending';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_approval_invalid', 403, 'Pending approval cannot authorize a consequential command.' );
$bad = $reserve_command;
$bad['approval']['scope_digest'] = hash( 'sha256', 'different-operation-scope' );
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_approval_invalid', 403, 'Approval for a different scope cannot authorize the command.' );
$bad = $reserve_command;
$bad['approval']['expires_at'] = gmdate( 'Y-m-d\TH:i:s\Z', $operation_now );
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_approval_invalid', 403, 'Approval expiring at the operation clock must fail.' );
$bad = $reserve_command;
$bad['approval']['approval_ref'] = 'approval-raw-reference';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_approval_invalid', 403, 'Raw approval references must fail typed validation.' );
$bad = $reserve_command;
$bad['approval']['approval_digest'] = 'not-a-digest';
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_approval_invalid', 403, 'Malformed approval digests must fail.' );
$bad = $reserve_command;
$bad['approval']['decision_digest'] = hash( 'sha256', 'unexpected-decision-field' );
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, $reserve_context ), 'tra_vel_commerce_operation_approval_invalid', 400, 'Unknown approval fields must fail the closed command boundary.' );
$bad = tra_vel_commerce_operation_command( 'revalidate', 'package', 'package_supplier_a', tra_vel_commerce_operation_package_target( $order ), false, $operation_now, 'evidence-on-non-consequential' );
$bad['approval'] = tra_vel_commerce_operation_approval( true, $bad['scope_digest'], $operation_now );
tra_vel_commerce_operation_expect_error( $factory->create( $order, $bad, tra_vel_commerce_operation_context( $order, 'evidence-on-non-consequential', $operation_now ) ), 'tra_vel_commerce_operation_approval_invalid', 400, 'A non-consequential command cannot carry approval evidence.' );

$short_secret_factory = new Tra_Vel_Commerce_Operation_Factory( 'too-short' );
tra_vel_commerce_operation_expect_error( $short_secret_factory->create( $order, $reserve_command, $reserve_context ), 'tra_vel_commerce_operation_secret_unavailable', 503, 'A short server secret must never issue predictable operation references.' );
$invalid_network_factory = new Tra_Vel_Commerce_Operation_Factory( 'runtime-server-operation-secret-0001', new stdClass() );
tra_vel_commerce_operation_expect_error( $invalid_network_factory->create( $order, $reserve_command, $reserve_context ), 'tra_vel_commerce_operation_network_invalid', 500, 'Operation creation must fail closed when its provider network has the wrong runtime type.' );

$encoded_operation = wp_json_encode( $reserve, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
tra_vel_commerce_operation_expect( is_string( $encoded_operation ) && false === strpos( $encoded_operation, 'private_product_ref' ) && false === strpos( $encoded_operation, '"raw_supplier_reference":' ) && false === strpos( $encoded_operation, 'payment_token' ) && false === strpos( $encoded_operation, 'card_number' ) && false === strpos( $encoded_operation, $reserve_command['input_digest'] ), 'Operation output must expose no fixture identity, raw supplier/payment data, or private command input digest.' );
tra_vel_commerce_operation_expect( ! $reserve['result']['simulated_side_effect_executed'] && ! $reserve['result']['real_side_effect_executed'] && ! $reserve['sandbox_truth']['real_supplier_request'] && ! $reserve['sandbox_truth']['real_charge'] && ! $reserve['sandbox_truth']['real_booking'], 'A queued operation must be truthful about every simulated and real side-effect boundary.' );

if ( '' !== $order_output ) {
	echo $order_output . PHP_EOL;
}
echo 'Tra-Vel commerce operation runtime passed (' . $operation_assertions . ' closed-command assertions).' . PHP_EOL;
