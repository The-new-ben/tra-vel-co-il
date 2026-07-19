<?php
/**
 * Runtime proof for server-only order-item to supplier-route binding.
 */

ob_start();
require __DIR__ . '/validate-commerce-order-runtime.php';
$order_output = trim( ob_get_clean() );

require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-operation-factory.php';
require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-supplier-operations-taxonomy.php';
require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-supplier-operations-policy.php';
require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-supplier-operations-state-machine.php';
require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-private-routing-registry.php';

$private_routing_assertions = 0;
$private_routing_temp_files = array();

function tra_vel_private_routing_expect( $condition, $message ) {
	global $private_routing_assertions;
	$private_routing_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, 'Tra-Vel private routing runtime failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function tra_vel_private_routing_expect_error( $value, $code, $message ) {
	tra_vel_private_routing_expect( is_wp_error( $value ) && $code === $value->get_error_code(), $message . ( is_wp_error( $value ) ? ' Received ' . $value->get_error_code() . '.' : '' ) );
}

function tra_vel_private_routing_rehash_order( $snapshot ) {
	$basis = $snapshot;
	unset( $basis['order_digest'] );
	$snapshot['order_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $basis );
	return $snapshot;
}

function tra_vel_private_routing_temp_fixture( $data, $label ) {
	global $private_routing_temp_files;
	$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tra-vel-' . $label . '-' . hash( 'sha256', wp_json_encode( $data ) ) . '.json';
	$encoded = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	tra_vel_private_routing_expect( is_string( $encoded ) && false !== file_put_contents( $path, $encoded ), 'A deterministic adversarial fixture must be writable outside the repository.' );
	$private_routing_temp_files[] = $path;
	return $path;
}

function tra_vel_private_routing_find_record( $records, $provider_id ) {
	foreach ( $records as $record ) {
		if ( $provider_id === $record['provider_id'] ) {
			return $record;
		}
	}
	return null;
}

function tra_vel_private_routing_find_item( $snapshot, $provider_id ) {
	foreach ( $snapshot['fulfillment']['items'] as $item ) {
		if ( $provider_id === $item['provider_id'] ) {
			return $item;
		}
	}
	return null;
}

function tra_vel_private_routing_operation( $factory, $snapshot, $item, $type, $now ) {
	$scope_digest = hash( 'sha256', 'private-routing-operation-scope|' . $type );
	$command = array(
		'type'             => $type,
		'vertical'         => $item['vertical'],
		'provider_id'      => $item['provider_id'],
		'target'           => array(
			'kind'          => 'order_item',
			'ref'           => $item['order_item_ref'],
			'version'       => $snapshot['version'],
			'target_digest' => Tra_Vel_Commerce_Policy::canonical_digest( $item ),
		),
		'scope_digest'     => $scope_digest,
		'input_digest'     => hash( 'sha256', 'private-routing-operation-input|' . $type ),
		'approval'         => array(
			'required'        => true,
			'approval_ref'    => 'tv_approval_abcdefghijklmnop',
			'approval_digest' => hash( 'sha256', 'private-routing-operation-approval|' . $type ),
			'status'          => 'approved',
			'scope_digest'    => $scope_digest,
			'expires_at'      => gmdate( 'Y-m-d\TH:i:s\Z', $now + 300 ),
		),
		'maximum_attempts' => 3,
	);
	$context = array(
		'owner_scope_digest'     => $snapshot['owner_scope_digest'],
		'idempotency_key_digest' => hash( 'sha256', 'private-routing-operation-idempotency|' . $type ),
		'now'                    => $now,
	);
	return $factory->create( $snapshot, $command, $context );
}

function tra_vel_private_routing_schema_forbidden_properties( $value, &$violations, $pointer = '#' ) {
	if ( ! is_array( $value ) ) {
		return;
	}
	$forbidden = array( 'private_product_ref', 'provider_locator_ref', 'credential_ref', 'endpoint_route_ref', 'endpoint_host', 'vault_secret_ref', 'supplier_booking_reference', 'raw_supplier_reference' );
	if ( isset( $value['properties'] ) && is_array( $value['properties'] ) ) {
		foreach ( array_keys( $value['properties'] ) as $property ) {
			if ( in_array( $property, $forbidden, true ) ) {
				$violations[] = $pointer . '/properties/' . $property;
			}
		}
	}
	foreach ( $value as $key => $child ) {
		tra_vel_private_routing_schema_forbidden_properties( $child, $violations, $pointer . '/' . $key );
	}
}

$operation_now = $order_context['now'] + 1;
$flight_item = tra_vel_private_routing_find_item( $order, 'flight_supplier_a' );
$operation_factory = new Tra_Vel_Commerce_Operation_Factory( 'runtime-server-operation-secret-0001' );
$reserve = tra_vel_private_routing_operation( $operation_factory, $order, $flight_item, 'reserve', $operation_now );
$fulfill = tra_vel_private_routing_operation( $operation_factory, $order, $flight_item, 'fulfill', $operation_now );
tra_vel_private_routing_expect( is_array( $reserve ) && is_array( $fulfill ), 'Queue-only reserve and fulfillment operations must exist before applying the stricter product-route gate.' );

$routing_context = array(
	'owner_scope_digest' => $order['owner_scope_digest'],
	'now'                => $operation_now,
);
$routing_secret = 'runtime-server-offer-secret-0001';
$registry = new Tra_Vel_Commerce_Private_Routing_Registry( $catalog, $routing_secret );
$records = $registry->bind_order( $order, $all_offers, $routing_context );
tra_vel_private_routing_expect( is_array( $records ), 'Every selected order item must bind to a private route' . ( is_wp_error( $records ) ? ': ' . $records->get_error_code() . ' / ' . $records->get_error_message() : '.' ) );
tra_vel_private_routing_expect( count( $order['fulfillment']['items'] ) === count( $records ) && 8 === count( $records ), 'Every one of the eight selected package components must receive exactly one private route.' );

$binding_digests = array();
$binding_refs = array();
$profile_fixture_path = __DIR__ . '/../../plugin/tra-vel-agent-core/assets/fixtures/commerce-sandbox/supplier-operations-profiles.json';
$profile_fixture = json_decode( file_get_contents( $profile_fixture_path ), true );
$profiles_by_id = array();
foreach ( $profile_fixture['profiles'] as $profile ) {
	$profiles_by_id[ $profile['supplier_id'] ] = $profile;
}

foreach ( $records as $index => $record ) {
	$item = $order['fulfillment']['items'][ $index ];
	$profile = $profiles_by_id[ $record['provider_id'] ];
	$binding_digests[] = $record['routing_binding_digest'];
	$binding_refs[] = $record['routing_binding_ref'];
	tra_vel_private_routing_expect( $record['owner_scope_digest'] === $order['owner_scope_digest'] && $record['order_ref'] === $order['order_ref'] && $record['order_digest'] === $order['order_digest'] && $record['order_version'] === $order['version'], 'A private route must bind the exact owner and immutable order revision.' );
	tra_vel_private_routing_expect( $record['order_item_ref'] === $item['order_item_ref'] && $record['component_ref'] === $item['component_ref'] && $record['provider_id'] === $item['provider_id'] && $record['provider_reference_digest'] === $item['provider_reference_digest'] && $record['offer_digest'] === $item['offer_digest'], 'A private route must bind the exact order item, provider HMAC, and selected offer digest.' );
	tra_vel_private_routing_expect( 1 === preg_match( '/^tvr_binding_[A-Za-z0-9_-]{16,96}$/', $record['routing_binding_ref'] ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $record['routing_binding_digest'] ), 'Private routing identities must be opaque and digest-bound.' );
	tra_vel_private_routing_expect( $record['supplier_binding']['profile_revision_id'] === $profile['revision_id'] && $record['supplier_binding']['profile_revision_number'] === $profile['revision_number'] && $record['supplier_binding']['profile_content_digest'] === $profile['revision_control']['content_digest'], 'Routing must freeze the exact validated supplier profile revision and immutable configuration digest.' );
	foreach ( array( 'product_revision_digest', 'rate_revision_digest', 'availability_revision_digest', 'terms_revision_digest' ) as $revision_field ) {
		tra_vel_private_routing_expect( $record['supplier_binding']['source_revisions'][ $revision_field ] === $profile['source_controls'][ $revision_field ], 'Routing must freeze source revision ' . $revision_field . '.' );
	}
	tra_vel_private_routing_expect( '1.0.0' === $record['supplier_binding']['adapter_version'] && $record['supplier_binding']['network_signature'] === $profile_fixture['network_signature'], 'Routing must freeze the reconciled adapter and provider-network revision.' );
	$frozen = $record['capability_binding']['frozen_capabilities'];
	$sorted = $frozen;
	sort( $sorted, SORT_STRING );
	tra_vel_private_routing_expect( $frozen === $sorted && count( $frozen ) === count( array_unique( $frozen ) ) && hash_equals( $record['capability_binding']['capability_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $frozen ) ), 'Product capabilities must be unique, sorted, frozen, and digest-bound.' );
	tra_vel_private_routing_expect( 0 === strpos( $record['private_route']['credential_ref'], 'credref_' ) && 0 === strpos( $record['private_route']['endpoint_route_ref'], 'tvr_endpoint_' ) && '.invalid' === substr( $record['private_route']['endpoint_host'], -8 ), 'The private route must carry a vault pointer and exact simulator endpoint route without raw credentials.' );
	$validity_values = array(
		$record['validity']['offer_fresh_until'],
		$record['validity']['order_expires_at'],
		$record['validity']['supplier_terms_valid_until'],
		$record['validity']['credential_expires_at'],
		$record['validity']['service_valid_until'],
	);
	$expected_valid_until = min( array_map( 'strtotime', $validity_values ) );
	tra_vel_private_routing_expect( $expected_valid_until === strtotime( $record['validity']['valid_until'] ) && $expected_valid_until > $routing_context['now'], 'A private route must expire at the earliest offer, order, supplier, credential, or service boundary.' );
	tra_vel_private_routing_expect( true === $record['private_boundary']['server_only'] && false === $record['private_boundary']['public_serialization_allowed'] && false === $record['private_boundary']['raw_credentials_stored'] && true === $record['private_boundary']['vault_pointers_only'], 'Every private record must explicitly deny public serialization and raw credential storage.' );

	$resolved = $catalog->resolve_private_product( $record['provider_id'], $record['provider_reference_digest'], $routing_secret );
	tra_vel_private_routing_expect( is_array( $resolved ) && $resolved['private_product_ref'] === $record['catalog_binding']['private_product_ref'] && Tra_Vel_Commerce_Policy::canonical_digest( $resolved ) === $record['catalog_binding']['private_product_digest'], 'Every selected provider HMAC must reverse to one exact validated private catalog product.' );

	$projection = $registry->public_projection( $record );
	$projection_keys = array( 'routing_binding_digest', 'supplier_profile_revision_digest', 'product_revision_digest', 'rate_revision_digest', 'availability_revision_digest', 'terms_revision_digest', 'capability_digest' );
	tra_vel_private_routing_expect( is_array( $projection ) && $projection_keys === array_keys( $projection ) && ! array_filter( $projection, static function ( $value ) { return ! is_string( $value ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $value ); } ), 'The public routing projection may contain only the binding digest and safe revision digests.' );
}
tra_vel_private_routing_expect( count( $binding_digests ) === count( array_unique( $binding_digests ) ) && count( $binding_refs ) === count( array_unique( $binding_refs ) ), 'Every order-item route must have a unique binding digest and opaque reference.' );

$flight_record = tra_vel_private_routing_find_record( $records, 'flight_supplier_a' );
tra_vel_private_routing_expect( is_array( $flight_record ), 'The selected flight item must have a private route for operation gating.' );
$gate_context = array( 'owner_scope_digest' => $order['owner_scope_digest'], 'now' => $operation_now );
$gate_projection = $registry->gate_queued_order_item_operation( $reserve, $order, $flight_record['routing_binding_digest'], $gate_context );
tra_vel_private_routing_expect( is_array( $gate_projection ) && $gate_projection === $registry->public_projection( $flight_record ), 'A pristine queued reserve operation must pass only as a digest-only routing projection.' );
tra_vel_private_routing_expect( ! $reserve['result']['simulated_side_effect_executed'] && ! $reserve['result']['real_side_effect_executed'] && null === $reserve['dispatched_at'], 'Private routing authorization must not weaken queue-only, no-side-effect operation truth.' );

$executed_operation = $reserve;
$executed_operation['result']['simulated_side_effect_executed'] = true;
tra_vel_private_routing_expect_error( $registry->gate_queued_order_item_operation( $executed_operation, $order, $flight_record['routing_binding_digest'], $gate_context ), 'tra_vel_commerce_private_routing_operation_side_effect_detected', 'An operation already claiming a side effect must fail the routing gate.' );
$wrong_provider_operation = $reserve;
$wrong_provider_operation['provider_id'] = 'flight_supplier_b';
tra_vel_private_routing_expect_error( $registry->gate_queued_order_item_operation( $wrong_provider_operation, $order, $flight_record['routing_binding_digest'], $gate_context ), 'tra_vel_commerce_private_routing_operation_binding_mismatch', 'An operation cannot be rerouted to another provider.' );
$other_record = tra_vel_private_routing_find_record( $records, 'accommodation_supplier_b' );
tra_vel_private_routing_expect_error( $registry->gate_queued_order_item_operation( $reserve, $order, $other_record['routing_binding_digest'], $gate_context ), 'tra_vel_commerce_private_routing_operation_binding_mismatch', 'An operation cannot borrow another order item routing binding.' );
tra_vel_private_routing_expect_error( $registry->gate_queued_order_item_operation( $fulfill, $order, $flight_record['routing_binding_digest'], $gate_context ), 'tra_vel_commerce_private_routing_operation_capability_not_frozen', 'Provider-level fulfillment authority cannot exceed the selected product capability snapshot.' );
$stale_gate_context = $gate_context;
$stale_gate_context['now'] = strtotime( $flight_record['validity']['valid_until'] );
tra_vel_private_routing_expect_error( $registry->gate_queued_order_item_operation( $reserve, $order, $flight_record['routing_binding_digest'], $stale_gate_context ), 'tra_vel_commerce_private_routing_binding_stale', 'A binding expiring at the operation clock must fail closed.' );

$rotating_profile_path = tra_vel_private_routing_temp_fixture( $profile_fixture, 'rotating-supplier-profile' );
$rotating_registry = new Tra_Vel_Commerce_Private_Routing_Registry( $catalog, $routing_secret, null, $rotating_profile_path );
$rotating_records = $rotating_registry->bind_order( $order, $all_offers, $routing_context );
$rotating_flight_record = is_array( $rotating_records ) ? tra_vel_private_routing_find_record( $rotating_records, 'flight_supplier_a' ) : null;
tra_vel_private_routing_expect( is_array( $rotating_flight_record ), 'A current supplier revision must bind before testing revision rotation.' );
$rotated_fixture = $profile_fixture;
$rotated_fixture['profiles'][0]['source_controls']['rate_revision_digest'] = hash( 'sha256', 'rotated-flight-rate-revision' );
$rotated_fixture['profiles'][0]['revision_control']['content_digest'] = Tra_Vel_Supplier_Operations_Policy::configuration_digest( $rotated_fixture['profiles'][0] );
$rotated_json = wp_json_encode( $rotated_fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
tra_vel_private_routing_expect( is_string( $rotated_json ) && false !== file_put_contents( $rotating_profile_path, $rotated_json ), 'The supplier-revision rotation fixture must be replaced atomically for the gate test.' );
tra_vel_private_routing_expect_error( $rotating_registry->gate_queued_order_item_operation( $reserve, $order, $rotating_flight_record['routing_binding_digest'], $gate_context ), 'tra_vel_commerce_private_routing_binding_revision_stale', 'A changed supplier source/profile revision must invalidate an earlier routing binding before dispatch.' );

$wrong_owner = $routing_context;
$wrong_owner['owner_scope_digest'] = hash( 'sha256', 'wrong-routing-owner' );
tra_vel_private_routing_expect_error( $registry->bind_order( $order, $all_offers, $wrong_owner ), 'tra_vel_commerce_private_routing_order_invalid', 'A different owner must not resolve private supplier routes.' );

$missing_offer_list = array_values( array_filter( $all_offers, static function ( $offer ) use ( $order ) { return $offer['offer_ref'] !== $order['fulfillment']['items'][0]['offer_ref']; } ) );
tra_vel_private_routing_expect_error( $registry->bind_order( $order, $missing_offer_list, $routing_context ), 'tra_vel_commerce_private_routing_offer_missing', 'A selected item with no server-owned offer snapshot must fail closed.' );
$duplicate_offer_list = $all_offers;
$duplicate_offer_list[] = tra_vel_commerce_offer_by_provider( $result, $order['fulfillment']['items'][0]['provider_id'] );
tra_vel_private_routing_expect_error( $registry->bind_order( $order, $duplicate_offer_list, $routing_context ), 'tra_vel_commerce_private_routing_offer_ambiguous', 'Duplicate offer snapshots must not create an ambiguous private route.' );

$wrong_offer_digest_order = $order;
$wrong_offer_digest_order['fulfillment']['items'][0]['offer_digest'] = hash( 'sha256', 'wrong-selected-offer' );
$wrong_offer_digest_order = tra_vel_private_routing_rehash_order( $wrong_offer_digest_order );
tra_vel_private_routing_expect_error( $registry->bind_order( $wrong_offer_digest_order, $all_offers, $routing_context ), 'tra_vel_commerce_private_routing_offer_digest_invalid', 'An order item with the wrong offer digest must fail before private resolution.' );

$stale_offers = $all_offers;
$stale_order = $order;
foreach ( $stale_offers as $index => $candidate ) {
	if ( $candidate['offer_ref'] === $stale_order['fulfillment']['items'][0]['offer_ref'] ) {
		$stale_offers[ $index ]['availability']['fresh_until'] = gmdate( 'Y-m-d\TH:i:s\Z', $routing_context['now'] );
		$stale_offers[ $index ]['evidence']['fresh_until'] = gmdate( 'Y-m-d\TH:i:s\Z', $routing_context['now'] );
		$stale_order['fulfillment']['items'][0]['offer_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $stale_offers[ $index ] );
		break;
	}
}
$stale_order = tra_vel_private_routing_rehash_order( $stale_order );
tra_vel_private_routing_expect_error( $registry->bind_order( $stale_order, $stale_offers, $routing_context ), 'tra_vel_commerce_private_routing_offer_stale', 'An offer expiring at the routing clock must fail closed even when its new digest is internally consistent.' );

$wrong_secret_registry = new Tra_Vel_Commerce_Private_Routing_Registry( $catalog, 'runtime-server-offer-secret-9999' );
tra_vel_private_routing_expect_error( $wrong_secret_registry->bind_order( $order, $all_offers, $routing_context ), 'tra_vel_commerce_private_route_not_found', 'A different server secret must not reverse a public provider-reference HMAC.' );
$first_item = $order['fulfillment']['items'][0];
tra_vel_private_routing_expect_error( $catalog->resolve_private_product( 'flight_supplier_b', $first_item['provider_reference_digest'], $routing_secret ), 'tra_vel_commerce_private_route_provider_mismatch', 'A valid provider HMAC must reject a different requested provider.' );
tra_vel_private_routing_expect_error( $catalog->resolve_private_product( $first_item['provider_id'], hash( 'sha256', 'unmapped-provider-reference' ), $routing_secret ), 'tra_vel_commerce_private_route_not_found', 'An unmapped provider HMAC must not guess a private product.' );
tra_vel_private_routing_expect_error( $catalog->resolve_private_product( $first_item['provider_id'] . '!', $first_item['provider_reference_digest'], $routing_secret ), 'tra_vel_commerce_private_route_input_invalid', 'Private route lookup must reject, not normalize, a malformed provider identity.' );

$tampered_record = $flight_record;
$tampered_record['private_route']['credential_ref'] = 'credref_replaced_after_binding';
tra_vel_private_routing_expect_error( $registry->public_projection( $tampered_record ), 'tra_vel_commerce_private_routing_binding_integrity_invalid', 'A private route mutation must invalidate its public digest projection.' );
tra_vel_private_routing_expect_error( $registry->private_record( hash( 'sha256', 'unknown-routing-binding' ) ), 'tra_vel_commerce_private_routing_binding_not_found', 'An unknown binding digest must not resolve to a private route.' );

$duplicate_profile_fixture = $profile_fixture;
$duplicate_profile_fixture['profiles'][] = $duplicate_profile_fixture['profiles'][0];
$duplicate_profile_path = tra_vel_private_routing_temp_fixture( $duplicate_profile_fixture, 'duplicate-supplier-profile' );
$duplicate_profile_registry = new Tra_Vel_Commerce_Private_Routing_Registry( $catalog, $routing_secret, null, $duplicate_profile_path );
tra_vel_private_routing_expect_error( $duplicate_profile_registry->readiness( $routing_context['now'] ), 'tra_vel_commerce_private_routing_profile_duplicate', 'Two active profiles for one provider must fail unique routing.' );

$stale_profile_fixture = $profile_fixture;
$stale_profile_fixture['profiles'][0]['source_controls']['last_verified_at'] = gmdate( 'Y-m-d\TH:i:s\Z', $routing_context['now'] - 7200 );
$stale_profile_fixture['profiles'][0]['revision_control']['content_digest'] = Tra_Vel_Supplier_Operations_Policy::configuration_digest( $stale_profile_fixture['profiles'][0] );
$stale_profile_path = tra_vel_private_routing_temp_fixture( $stale_profile_fixture, 'stale-supplier-profile' );
$stale_profile_registry = new Tra_Vel_Commerce_Private_Routing_Registry( $catalog, $routing_secret, null, $stale_profile_path );
tra_vel_private_routing_expect_error( $stale_profile_registry->readiness( $routing_context['now'] ), 'tra_vel_supplier_operations_source_terms_stale', 'Stale supplier source controls must fail before route creation.' );

$catalog_fixture_path = __DIR__ . '/../../plugin/tra-vel-agent-core/assets/fixtures/commerce-sandbox/product-catalog.json';
$catalog_fixture = json_decode( file_get_contents( $catalog_fixture_path ), true );
$catalog_fixture['products'][] = $catalog_fixture['products'][0];
$duplicate_catalog_path = tra_vel_private_routing_temp_fixture( $catalog_fixture, 'duplicate-private-product' );
$duplicate_catalog = new Tra_Vel_Commerce_Sandbox_Catalog( $duplicate_catalog_path );
tra_vel_private_routing_expect_error( $duplicate_catalog->readiness(), 'tra_vel_commerce_fixture_product_binding_invalid', 'Duplicate private catalog locators must fail before HMAC routing.' );

$private_schema_path = __DIR__ . '/../../plugin/tra-vel-agent-core/schemas/private/commerce-private-routing-record.schema.json';
$private_schema = json_decode( file_get_contents( $private_schema_path ), true );
tra_vel_private_routing_expect( is_array( $private_schema ) && JSON_ERROR_NONE === json_last_error() && false === $private_schema['additionalProperties'] && '1.0.0' === $private_schema['properties']['contract_version']['const'], 'The private routing record must have a closed, versioned JSON schema.' );
tra_vel_private_routing_expect( array() === array_diff( $private_schema['required'], array_keys( $private_schema['properties'] ) ) && array() === array_diff( array_keys( $private_schema['properties'] ), $private_schema['required'] ), 'The private routing schema root must require exactly every declared field.' );

$public_schema_violations = array();
foreach ( glob( __DIR__ . '/../../plugin/tra-vel-agent-core/schemas/commerce-*.schema.json' ) as $schema_path ) {
	$schema_raw = file_get_contents( $schema_path );
	$schema = json_decode( $schema_raw, true );
	tra_vel_private_routing_expect( is_array( $schema ) && JSON_ERROR_NONE === json_last_error(), basename( $schema_path ) . ' must remain valid JSON during the private-reference scan.' );
	tra_vel_private_routing_schema_forbidden_properties( $schema, $public_schema_violations, basename( $schema_path ) );
	tra_vel_private_routing_expect( false === strpos( $schema_raw, 'credref_' ) && false === strpos( $schema_raw, 'tvr_endpoint_' ) && false === strpos( $schema_raw, '"^px_' ) && false === strpos( $schema_raw, 'vault_secret_ref' ), basename( $schema_path ) . ' must define no private locator or credential pattern.' );
}
tra_vel_private_routing_expect( ! $public_schema_violations, 'Public commerce schemas must expose no private, vault, endpoint-route, or provider-native reference property: ' . implode( ', ', $public_schema_violations ) );

$public_payloads = array(
	'search response' => $result,
	'package'         => $fresh_package,
	'order'           => $order,
	'operation'       => $reserve,
	'projection'      => $gate_projection,
);
foreach ( $public_payloads as $label => $payload ) {
	$encoded = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	tra_vel_private_routing_expect( is_string( $encoded ) && false === strpos( $encoded, 'private_product_ref' ) && false === strpos( $encoded, 'credref_' ) && false === strpos( $encoded, 'tvr_endpoint_' ) && false === strpos( $encoded, 'endpoint_host' ) && false === strpos( $encoded, 'vault_') && false === strpos( $encoded, '"raw_supplier_reference":' ) && 1 !== preg_match( '/\bpx_[a-z0-9_]{8,90}\b/', $encoded ), ucfirst( $label ) . ' public JSON must contain no private provider locator, vault pointer, credential, endpoint, or raw supplier reference.' );
}
$private_encoded = wp_json_encode( $flight_record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
tra_vel_private_routing_expect( is_string( $private_encoded ) && false !== strpos( $private_encoded, 'private_product_ref' ) && false !== strpos( $private_encoded, 'credref_' ) && false === strpos( $private_encoded, 'sk-') && false === stripos( $private_encoded, 'bearer ') && false === strpos( $private_encoded, 'password' ), 'The private record must retain route pointers while containing no raw credential material.' );

foreach ( $private_routing_temp_files as $temp_file ) {
	if ( is_file( $temp_file ) ) {
		unlink( $temp_file );
	}
}

if ( '' !== $order_output ) {
	echo $order_output . PHP_EOL;
}
echo 'Tra-Vel private commerce routing runtime passed (' . $private_routing_assertions . ' server-only routing assertions; 8 uniquely bound items).' . PHP_EOL;
