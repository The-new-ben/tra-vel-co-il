<?php
/**
 * Adversarial runtime proof for exact per-item funds-flow construction.
 */

ob_start();
require __DIR__ . '/validate-commerce-private-routing-runtime.php';
$funds_flow_factory_dependency_output = trim( ob_get_clean() );

require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-funds-flow-policy.php';
require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-funds-flow-factory.php';

$funds_flow_factory_assertions = 0;

function funds_flow_factory_expect( $condition, $message ) {
	global $funds_flow_factory_assertions;
	$funds_flow_factory_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, 'Tra-Vel funds-flow factory runtime failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function funds_flow_factory_expect_error( $value, $code, $message ) {
	funds_flow_factory_expect( is_wp_error( $value ) && $code === $value->get_error_code(), $message . ( is_wp_error( $value ) ? ' Received ' . $value->get_error_code() . '.' : ' No error returned.' ) );
}

function funds_flow_factory_find_offer( $offers, $provider_id ) {
	foreach ( $offers as $offer ) {
		if ( $provider_id === $offer['provider_id'] ) {
			return $offer;
		}
	}
	return null;
}

function funds_flow_factory_find_item( $order, $provider_id ) {
	foreach ( $order['fulfillment']['items'] as $item ) {
		if ( $provider_id === $item['provider_id'] ) {
			return $item;
		}
	}
	return null;
}

function funds_flow_factory_find_route( $records, $provider_id ) {
	foreach ( $records as $record ) {
		if ( $provider_id === $record['provider_id'] ) {
			return $record;
		}
	}
	return null;
}

function funds_flow_factory_reseal_route( $record ) {
	$basis = $record;
	unset( $basis['routing_binding_digest'] );
	$record['routing_binding_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $basis );
	return $record;
}

function funds_flow_factory_bundle( $provider_id, $seed ) {
	global $result, $all_offers, $context, $approval, $catalog, $routing_secret;
	$selected = array(
		array( $provider_id, 'outbound', 1 ),
		array( 'accommodation_supplier_b', 'stay', 1 ),
		array( 'transfer_supplier_a', 'arrival', 1 ),
		array( 'activity_supplier_a', 'experience', 3 ),
		array( 'dining_supplier_a', 'meal', 3 ),
		array( 'insurance_supplier_a', 'coverage', 1 ),
		array( 'connectivity_supplier_a', 'connection', 1 ),
		array( 'equipment_supplier_a', 'gear', 2 ),
	);
	$components = array();
	foreach ( $selected as $selection ) {
		$offer = funds_flow_factory_find_offer( $all_offers, $selection[0] );
		if ( ! is_array( $offer ) ) {
			return new WP_Error( 'test_offer_missing', 'The requested provider offer is missing.' );
		}
		$components[] = array(
			'offer_ref'     => $offer['offer_ref'],
			'offer_version' => $offer['version'],
			'role'          => $selection[1],
			'required'      => true,
			'day'           => $selection[2],
		);
	}
	$composer = new Tra_Vel_Commerce_Package_Composer( 'runtime-server-package-secret-' . $seed );
	$package = $composer->compose(
		$result['session'],
		$all_offers,
		array( 'title' => 'Funds-flow factory package ' . $seed, 'components' => $components ),
		$context
	);
	if ( is_wp_error( $package ) ) {
		return $package;
	}
	$package['version'] = 2;
	$package['revalidation'] = array(
		'mode'                    => 'atomic',
		'all_components_required' => true,
		'state'                   => 'fresh',
		'checked_at'              => gmdate( 'Y-m-d\TH:i:s\Z', $context['now'] + 30 ),
	);
	$package['updated_at'] = gmdate( 'Y-m-d\TH:i:s\Z', $context['now'] + 30 );
	$package_basis = $package;
	unset( $package_basis['package_digest'] );
	$package['package_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $package_basis );

	$order_context = array(
		'owner_scope_digest'     => $context['owner_scope_digest'],
		'idempotency_key_digest' => hash( 'sha256', 'funds-flow-order-' . $seed ),
		'now'                    => $context['now'] + 60,
	);
	$order_factory = new Tra_Vel_Commerce_Order_Factory( 'runtime-server-order-secret-' . $seed );
	$order = $order_factory->create_from_package( $package, $approval, $order_context );
	if ( is_wp_error( $order ) ) {
		return $order;
	}
	$routing_context = array( 'owner_scope_digest' => $order['owner_scope_digest'], 'now' => $order_context['now'] + 1 );
	$registry = new Tra_Vel_Commerce_Private_Routing_Registry( $catalog, $routing_secret );
	$routes = $registry->bind_order( $order, $all_offers, $routing_context );
	if ( is_wp_error( $routes ) ) {
		return $routes;
	}
	return array(
		'order'   => $order,
		'item'    => funds_flow_factory_find_item( $order, $provider_id ),
		'offer'   => funds_flow_factory_find_offer( $all_offers, $provider_id ),
		'route'   => funds_flow_factory_find_route( $routes, $provider_id ),
		'now'     => $routing_context['now'],
	);
}

function funds_flow_factory_reconcile_currency( $bundle, $provider, $profile, $collector = null ) {
	$currency = $bundle['offer']['pricing']['currency'];
	$provider['settlement']['currency'] = $currency;
	$profile['settlement']['currency'] = $currency;
	if ( null !== $collector ) {
		$profile['relationship']['merchant_of_record'] = $collector;
		$profile['settlement']['customer_funds_owner'] = $collector;
		$profile['settlement']['chargeback_owner'] = $collector;
		$profile['settlement']['tax_owner'] = $collector;
	}
	$profile['revision_control']['content_digest'] = Tra_Vel_Supplier_Operations_Policy::configuration_digest( $profile );
	$bundle['route']['supplier_binding']['profile_content_digest'] = $profile['revision_control']['content_digest'];
	$bundle['route'] = funds_flow_factory_reseal_route( $bundle['route'] );
	return array( $bundle, $provider, $profile );
}

function funds_flow_factory_model( $provider ) {
	if ( 'affiliate' === $provider['relationship'] ) {
		return 'affiliate_handoff';
	}
	return 'net_rate' === $provider['settlement']['model'] ? 'net_rate_markup' : 'direct_commission';
}

function funds_flow_factory_configuration( $bundle, $provider, $profile, $model = null ) {
	$model = null === $model ? funds_flow_factory_model( $provider ) : $model;
	$source = $bundle['route']['supplier_binding']['source_revisions'];
	$valid_until = $bundle['route']['validity']['supplier_terms_valid_until'];
	if ( null !== $profile['relationship']['expires_at'] && strtotime( $profile['relationship']['expires_at'] ) < strtotime( $valid_until ) ) {
		$valid_until = $profile['relationship']['expires_at'];
	}
	$total = $bundle['offer']['pricing']['total_amount_minor'];
	$markup = 'net_rate_markup' === $model ? max( 1, intdiv( $total, 10 ) ) : 0;
	$configuration = array(
		'contract_version'                 => '1.0.0',
		'environment'                      => 'sandbox',
		'authority'                        => 'sandbox_reconciled_configuration',
		'provider_id'                     => $provider['provider_id'],
		'vertical'                        => $bundle['offer']['vertical'],
		'provider_network_signature'      => $bundle['route']['supplier_binding']['network_signature'],
		'provider_descriptor_digest'      => Tra_Vel_Commerce_Policy::canonical_digest( Tra_Vel_Commerce_Policy::provider_descriptor( $provider ) ),
		'supplier_config_revision_digest' => $profile['revision_control']['content_digest'],
		'product_revision_digest'         => $source['product_revision_digest'],
		'rate_revision_digest'            => $source['rate_revision_digest'],
		'availability_revision_digest'    => $source['availability_revision_digest'],
		'terms_revision_digest'           => $source['terms_revision_digest'],
		'offer_evidence_digest'           => $bundle['offer']['evidence']['evidence_digest'],
		'commercial_model'                => $model,
		'currency'                        => $bundle['offer']['pricing']['currency'],
		'minor_unit_exponent'             => $bundle['offer']['pricing']['minor_unit'],
		'effective_at'                    => $profile['relationship']['effective_at'],
		'valid_until'                     => $valid_until,
		'commissionable_basis'            => 'net_rate_markup' === $model ? 'not_applicable' : 'customer_total',
		'commission_bps'                  => 'net_rate_markup' === $model ? null : $profile['settlement']['commission_bps'],
		'supplier_net_minor'              => 'net_rate_markup' === $model ? $total - $markup : null,
		'markup_amount_minor'             => $markup,
		'affiliate_collector'             => 'affiliate_handoff' === $model ? 'supplier' : null,
		'tax_treatment'                   => $bundle['offer']['pricing']['tax_state'],
		'configuration_digest'            => '',
	);
	$configuration['configuration_digest'] = Tra_Vel_Commerce_Funds_Flow_Factory::commercial_configuration_digest( $configuration );
	return $configuration;
}

function funds_flow_factory_context( $bundle, $seed = 'primary' ) {
	return array(
		'owner_scope_digest'     => $bundle['route']['owner_scope_digest'],
		'idempotency_key_digest' => hash( 'sha256', 'funds-flow-factory-' . $seed ),
		'now'                    => $bundle['now'],
	);
}

$provider_network = new Tra_Vel_Commerce_Sandbox_Network();
$providers = $provider_network->all();
funds_flow_factory_expect( is_array( $providers ), 'The reconciled provider network must load for factory tests.' );
$providers_by_id = array();
foreach ( $providers as $provider ) {
	$providers_by_id[ $provider['provider_id'] ] = $provider;
}

$platform_bundle = funds_flow_factory_bundle( 'flight_supplier_a', 'platform-commission' );
$net_bundle = funds_flow_factory_bundle( 'flight_supplier_b', 'net-rate' );
$affiliate_bundle = funds_flow_factory_bundle( 'flight_supplier_c', 'affiliate' );
foreach ( array( 'platform' => $platform_bundle, 'net' => $net_bundle, 'affiliate' => $affiliate_bundle ) as $label => $bundle ) {
	funds_flow_factory_expect( is_array( $bundle ) && is_array( $bundle['item'] ) && is_array( $bundle['offer'] ) && is_array( $bundle['route'] ), ucfirst( $label ) . ' bundle must contain one exact item, offer, and private route.' );
}

$factory = new Tra_Vel_Commerce_Funds_Flow_Factory( $routing_secret );

/* The current fixture currency gap must be visible and fail closed. */
$unreconciled_provider = $providers_by_id['flight_supplier_a'];
$unreconciled_profile = $profiles_by_id['flight_supplier_a'];
$unreconciled_configuration = funds_flow_factory_configuration( $platform_bundle, $unreconciled_provider, $unreconciled_profile );
$unreconciled = $factory->create_initial_snapshot(
	$platform_bundle['item'],
	$platform_bundle['offer'],
	$platform_bundle['route'],
	$unreconciled_provider,
	$unreconciled_profile,
	$unreconciled_configuration,
	funds_flow_factory_context( $platform_bundle, 'unreconciled' )
);
funds_flow_factory_expect_error( $unreconciled, 'tra_vel_commerce_funds_flow_factory_commercial_currency_mismatch', 'A THB offer cannot silently use the current ILS settlement configuration.' );

list( $platform_bundle, $platform_provider, $platform_profile ) = funds_flow_factory_reconcile_currency(
	$platform_bundle,
	$providers_by_id['flight_supplier_a'],
	$profiles_by_id['flight_supplier_a']
);
$platform_configuration = funds_flow_factory_configuration( $platform_bundle, $platform_provider, $platform_profile );
$platform_context = funds_flow_factory_context( $platform_bundle, 'platform' );
$platform_snapshot = $factory->create_initial_snapshot( $platform_bundle['item'], $platform_bundle['offer'], $platform_bundle['route'], $platform_provider, $platform_profile, $platform_configuration, $platform_context );
funds_flow_factory_expect( is_array( $platform_snapshot ), 'A reconciled platform-collected commission item must create one initial snapshot' . ( is_wp_error( $platform_snapshot ) ? ': ' . $platform_snapshot->get_error_code() . ' / ' . $platform_snapshot->get_error_message() : '.' ) );
funds_flow_factory_expect( 'direct_commission' === $platform_snapshot['commercial_model'] && 'platform' === $platform_snapshot['parties']['merchant_of_record'] && 'platform' === $platform_snapshot['parties']['payment_collector'], 'Platform-collected commission must derive platform MOR and collector explicitly.' );
funds_flow_factory_expect( 'platform_processor' === $platform_snapshot['payment']['authority'] && $platform_profile['settlement']['payout_route_ref'] === $platform_snapshot['private_routes']['supplier_payable_route_ref'], 'Platform collection must bind processor observation authority and the exact opaque supplier payout route.' );
$expected_commission = intdiv( ( $platform_snapshot['pricing']['customer_total_minor'] * $platform_profile['settlement']['commission_bps'] ) + 5000, 10000 );
funds_flow_factory_expect( $expected_commission === $platform_snapshot['pricing']['commission_receivable_minor'] && $platform_snapshot['pricing']['customer_total_minor'] === $platform_snapshot['pricing']['supplier_payable_minor'] + $expected_commission, 'Direct commission must reconcile exact basis points into supplier payable and platform revenue.' );
funds_flow_factory_expect( is_array( Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $platform_snapshot, $platform_context['now'] ) ), 'Factory output must pass the independent closed funds-flow policy.' );

$replay = $factory->create_initial_snapshot( $platform_bundle['item'], $platform_bundle['offer'], $platform_bundle['route'], $platform_provider, $platform_profile, $platform_configuration, $platform_context );
funds_flow_factory_expect( $platform_snapshot === $replay, 'The exact same evidence, clock, idempotency digest, and secret must replay deterministically.' );
$other_context = funds_flow_factory_context( $platform_bundle, 'other-attempt' );
$other_attempt = $factory->create_initial_snapshot( $platform_bundle['item'], $platform_bundle['offer'], $platform_bundle['route'], $platform_provider, $platform_profile, $platform_configuration, $other_context );
funds_flow_factory_expect( is_array( $other_attempt ) && $other_attempt['funds_flow_ref'] !== $platform_snapshot['funds_flow_ref'] && $other_attempt['pricing'] === $platform_snapshot['pricing'], 'A different idempotency digest must change private identity without changing economics.' );

/* Supplier-collected direct commission uses the same exact policy without a payout route. */
list( $supplier_bundle, $supplier_provider, $supplier_profile ) = funds_flow_factory_reconcile_currency(
	$platform_bundle,
	$providers_by_id['flight_supplier_a'],
	$profiles_by_id['flight_supplier_a'],
	'supplier'
);
$supplier_configuration = funds_flow_factory_configuration( $supplier_bundle, $supplier_provider, $supplier_profile );
$supplier_context = funds_flow_factory_context( $supplier_bundle, 'supplier-collected' );
$supplier_snapshot = $factory->create_initial_snapshot( $supplier_bundle['item'], $supplier_bundle['offer'], $supplier_bundle['route'], $supplier_provider, $supplier_profile, $supplier_configuration, $supplier_context );
funds_flow_factory_expect( is_array( $supplier_snapshot ), 'A reconciled supplier-collected direct commission item must create one initial snapshot' . ( is_wp_error( $supplier_snapshot ) ? ': ' . $supplier_snapshot->get_error_code() . ' / ' . $supplier_snapshot->get_error_message() : '.' ) );
funds_flow_factory_expect( 'supplier' === $supplier_snapshot['parties']['merchant_of_record'] && 'supplier' === $supplier_snapshot['parties']['payment_collector'] && 'supplier_reported' === $supplier_snapshot['payment']['authority'], 'Supplier-collected commission must derive supplier MOR, collector, and reported payment authority.' );
funds_flow_factory_expect( null === $supplier_snapshot['private_routes']['supplier_payable_route_ref'] && $supplier_snapshot['pricing'] === $platform_snapshot['pricing'], 'Supplier collection must not invent a platform payout route or alter exact item economics.' );

/* Net rate freezes supplier net and markup separately. */
list( $net_bundle, $net_provider, $net_profile ) = funds_flow_factory_reconcile_currency( $net_bundle, $providers_by_id['flight_supplier_b'], $profiles_by_id['flight_supplier_b'] );
$net_configuration = funds_flow_factory_configuration( $net_bundle, $net_provider, $net_profile );
$net_context = funds_flow_factory_context( $net_bundle, 'net' );
$net_snapshot = $factory->create_initial_snapshot( $net_bundle['item'], $net_bundle['offer'], $net_bundle['route'], $net_provider, $net_profile, $net_configuration, $net_context );
funds_flow_factory_expect( is_array( $net_snapshot ), 'A reconciled net-rate item must create one initial snapshot' . ( is_wp_error( $net_snapshot ) ? ': ' . $net_snapshot->get_error_code() . ' / ' . $net_snapshot->get_error_message() : '.' ) );
funds_flow_factory_expect( 'net_rate_markup' === $net_snapshot['commercial_model'] && $net_configuration['supplier_net_minor'] === $net_snapshot['pricing']['supplier_net_minor'] && $net_configuration['markup_amount_minor'] === $net_snapshot['pricing']['platform_markup_minor'], 'Net rate must freeze exact supplier net and platform markup independently.' );
funds_flow_factory_expect( $net_snapshot['pricing']['customer_total_minor'] === $net_snapshot['pricing']['supplier_net_minor'] + $net_snapshot['pricing']['platform_markup_minor'] && 'not_applicable' === $net_snapshot['parties']['commission_payee'], 'Net-rate customer total must reconcile without commission.' );

/* Affiliate handoff records commission only and never platform-collected supplier funds. */
list( $affiliate_bundle, $affiliate_provider, $affiliate_profile ) = funds_flow_factory_reconcile_currency( $affiliate_bundle, $providers_by_id['flight_supplier_c'], $profiles_by_id['flight_supplier_c'] );
$affiliate_configuration = funds_flow_factory_configuration( $affiliate_bundle, $affiliate_provider, $affiliate_profile );
$affiliate_context = funds_flow_factory_context( $affiliate_bundle, 'affiliate' );
$affiliate_snapshot = $factory->create_initial_snapshot( $affiliate_bundle['item'], $affiliate_bundle['offer'], $affiliate_bundle['route'], $affiliate_provider, $affiliate_profile, $affiliate_configuration, $affiliate_context );
funds_flow_factory_expect( is_array( $affiliate_snapshot ), 'A reconciled affiliate handoff must create one initial snapshot' . ( is_wp_error( $affiliate_snapshot ) ? ': ' . $affiliate_snapshot->get_error_code() . ' / ' . $affiliate_snapshot->get_error_message() : '.' ) );
funds_flow_factory_expect( 'affiliate_handoff' === $affiliate_snapshot['commercial_model'] && 'supplier' === $affiliate_snapshot['parties']['merchant_of_record'] && 'supplier_reported' === $affiliate_snapshot['payment']['authority'], 'Affiliate handoff must derive an external merchant and supplier-reported payment evidence.' );
funds_flow_factory_expect( 0 === $affiliate_snapshot['pricing']['supplier_net_minor'] && 0 === $affiliate_snapshot['pricing']['supplier_payable_minor'] && null === $affiliate_snapshot['private_routes']['supplier_payable_route_ref'], 'Affiliate handoff must not invent platform-held supplier funds, net rate, or payout authority.' );

/* Exact identity, revision, money, and authority adversaries. */
$wrong_offer = $platform_bundle['offer'];
$wrong_offer['provider_id'] = 'flight_supplier_b';
funds_flow_factory_expect_error( $factory->create_initial_snapshot( $platform_bundle['item'], $wrong_offer, $platform_bundle['route'], $platform_provider, $platform_profile, $platform_configuration, $platform_context ), 'tra_vel_commerce_funds_flow_factory_offer_binding_mismatch', 'A selected offer cannot move to another provider.' );

$wrong_item = $platform_bundle['item'];
$wrong_item['offer_digest'] = hash( 'sha256', 'wrong-offer-digest' );
funds_flow_factory_expect_error( $factory->create_initial_snapshot( $wrong_item, $platform_bundle['offer'], $platform_bundle['route'], $platform_provider, $platform_profile, $platform_configuration, $platform_context ), 'tra_vel_commerce_funds_flow_factory_offer_binding_mismatch', 'An order-item offer digest mismatch must fail before financial construction.' );

$tampered_route = $platform_bundle['route'];
$tampered_route['private_route']['credential_ref'] = 'credref_tampered_private_route';
funds_flow_factory_expect_error( $factory->create_initial_snapshot( $platform_bundle['item'], $platform_bundle['offer'], $tampered_route, $platform_provider, $platform_profile, $platform_configuration, $platform_context ), 'tra_vel_commerce_funds_flow_factory_routing_digest_invalid', 'A private route mutation without a new binding digest must fail.' );

$wrong_secret_factory = new Tra_Vel_Commerce_Funds_Flow_Factory( 'runtime-server-offer-secret-9999' );
funds_flow_factory_expect_error( $wrong_secret_factory->create_initial_snapshot( $platform_bundle['item'], $platform_bundle['offer'], $platform_bundle['route'], $platform_provider, $platform_profile, $platform_configuration, $platform_context ), 'tra_vel_commerce_funds_flow_factory_routing_reference_invalid', 'A different server secret cannot accept another routing registry reference.' );

$wrong_revision_configuration = $platform_configuration;
$wrong_revision_configuration['rate_revision_digest'] = hash( 'sha256', 'wrong-rate-revision' );
$wrong_revision_configuration['configuration_digest'] = Tra_Vel_Commerce_Funds_Flow_Factory::commercial_configuration_digest( $wrong_revision_configuration );
funds_flow_factory_expect_error( $factory->create_initial_snapshot( $platform_bundle['item'], $platform_bundle['offer'], $platform_bundle['route'], $platform_provider, $platform_profile, $wrong_revision_configuration, $platform_context ), 'tra_vel_commerce_funds_flow_factory_commercial_revision_mismatch', 'A commercial rate revision cannot differ from the exact private route.' );

$bad_self_digest = $platform_configuration;
$bad_self_digest['configuration_digest'] = hash( 'sha256', 'tampered-commercial-config' );
funds_flow_factory_expect_error( $factory->create_initial_snapshot( $platform_bundle['item'], $platform_bundle['offer'], $platform_bundle['route'], $platform_provider, $platform_profile, $bad_self_digest, $platform_context ), 'tra_vel_commerce_funds_flow_factory_commercial_configuration_digest_invalid', 'A changed commercial configuration must fail its self-digest.' );

$wrong_currency = $platform_configuration;
$wrong_currency['currency'] = 'USD';
$wrong_currency['configuration_digest'] = Tra_Vel_Commerce_Funds_Flow_Factory::commercial_configuration_digest( $wrong_currency );
funds_flow_factory_expect_error( $factory->create_initial_snapshot( $platform_bundle['item'], $platform_bundle['offer'], $platform_bundle['route'], $platform_provider, $platform_profile, $wrong_currency, $platform_context ), 'tra_vel_commerce_funds_flow_factory_commercial_currency_mismatch', 'No implicit FX or currency substitution may enter funds flow.' );

$wrong_commission = $platform_configuration;
$wrong_commission['commission_bps']++;
$wrong_commission['configuration_digest'] = Tra_Vel_Commerce_Funds_Flow_Factory::commercial_configuration_digest( $wrong_commission );
funds_flow_factory_expect_error( $factory->create_initial_snapshot( $platform_bundle['item'], $platform_bundle['offer'], $platform_bundle['route'], $platform_provider, $platform_profile, $wrong_commission, $platform_context ), 'tra_vel_commerce_funds_flow_factory_commercial_commission_mismatch', 'Commission basis points must match provider and supplier revisions exactly.' );

$wrong_net = $net_configuration;
$wrong_net['supplier_net_minor']--;
$wrong_net['configuration_digest'] = Tra_Vel_Commerce_Funds_Flow_Factory::commercial_configuration_digest( $wrong_net );
funds_flow_factory_expect_error( $factory->create_initial_snapshot( $net_bundle['item'], $net_bundle['offer'], $net_bundle['route'], $net_provider, $net_profile, $wrong_net, $net_context ), 'tra_vel_commerce_funds_flow_factory_commercial_net_rate_mismatch', 'Net and markup cannot miss the selected customer total by one minor unit.' );

$expired_configuration = $platform_configuration;
$expired_configuration['valid_until'] = gmdate( 'Y-m-d\TH:i:s\Z', $platform_context['now'] );
$expired_configuration['configuration_digest'] = Tra_Vel_Commerce_Funds_Flow_Factory::commercial_configuration_digest( $expired_configuration );
funds_flow_factory_expect_error( $factory->create_initial_snapshot( $platform_bundle['item'], $platform_bundle['offer'], $platform_bundle['route'], $platform_provider, $platform_profile, $expired_configuration, $platform_context ), 'tra_vel_commerce_funds_flow_factory_commercial_terms_stale', 'A rate card expiring at the construction clock must fail closed.' );

$wrong_tax = $platform_configuration;
$wrong_tax['tax_treatment'] = 'excluded';
$wrong_tax['configuration_digest'] = Tra_Vel_Commerce_Funds_Flow_Factory::commercial_configuration_digest( $wrong_tax );
funds_flow_factory_expect_error( $factory->create_initial_snapshot( $platform_bundle['item'], $platform_bundle['offer'], $platform_bundle['route'], $platform_provider, $platform_profile, $wrong_tax, $platform_context ), 'tra_vel_commerce_funds_flow_factory_commercial_tax_mismatch', 'Tax treatment must match the exact offer ledger.' );

$unknown_configuration = $platform_configuration;
$unknown_configuration['processor_secret'] = 'forbidden';
funds_flow_factory_expect_error( $factory->create_initial_snapshot( $platform_bundle['item'], $platform_bundle['offer'], $platform_bundle['route'], $platform_provider, $platform_profile, $unknown_configuration, $platform_context ), 'tra_vel_commerce_funds_flow_factory_commercial_configuration_invalid', 'Unknown or credential-shaped commercial fields must fail the closed configuration boundary.' );

$wrong_owner_context = $platform_context;
$wrong_owner_context['owner_scope_digest'] = hash( 'sha256', 'wrong-funds-flow-owner' );
funds_flow_factory_expect_error( $factory->create_initial_snapshot( $platform_bundle['item'], $platform_bundle['offer'], $platform_bundle['route'], $platform_provider, $platform_profile, $platform_configuration, $wrong_owner_context ), 'tra_vel_commerce_funds_flow_factory_routing_identity_mismatch', 'Another owner cannot initialize funds flow from this route.' );

$changed_provider = $platform_provider;
$changed_provider['settlement']['commission_bps']++;
funds_flow_factory_expect_error( $factory->create_initial_snapshot( $platform_bundle['item'], $platform_bundle['offer'], $platform_bundle['route'], $changed_provider, $platform_profile, $platform_configuration, $platform_context ), 'tra_vel_commerce_funds_flow_factory_provider_supplier_mismatch', 'Provider and supplier commercial models cannot drift apart.' );

/* Projection and static source inspection prove the private/no-side-effect boundary. */
$projection = Tra_Vel_Commerce_Funds_Flow_Policy::public_projection( $platform_snapshot, $platform_context['now'] );
funds_flow_factory_expect( is_array( $projection ), 'A validated initial snapshot must expose only the independent digest projection.' );
$projection_json = wp_json_encode( $projection );
foreach ( array( 'provider_id', 'commercial_model', 'commission_bps', 'supplier_net_minor', 'ratecard_', 'payroute_', 'setroute_', 'payout_', 'tvr_binding_' ) as $forbidden ) {
	funds_flow_factory_expect( false === strpos( $projection_json, $forbidden ), 'Public projection must not expose private factory material: ' . $forbidden . '.' );
}

foreach ( array( $platform_snapshot, $supplier_snapshot, $net_snapshot, $affiliate_snapshot ) as $snapshot ) {
	$private_json = wp_json_encode( $snapshot );
	funds_flow_factory_expect( is_string( $private_json ) && false === strpos( $private_json, 'https://' ) && false === strpos( $private_json, 'sk-' ) && false === stripos( $private_json, 'Bearer ') && false === strpos( $private_json, '@' ), 'Private snapshots may contain only opaque locators and digests, never endpoints, credentials, or personal data.' );
	funds_flow_factory_expect( true === $snapshot['sandbox_truth']['simulated'] && false === $snapshot['sandbox_truth']['real_processor_call'] && false === $snapshot['sandbox_truth']['real_customer_charge'] && false === $snapshot['sandbox_truth']['real_supplier_payment'] && false === $snapshot['sandbox_truth']['real_settlement'], 'Every initial snapshot must deny invented processor, charge, supplier-payment, and settlement authority.' );
}

$factory_source = file_get_contents( __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-funds-flow-factory.php' );
foreach ( array( 'wp_remote_', 'curl_', 'fsockopen', 'stream_socket_client', 'file_put_contents', '$wpdb' ) as $side_effect_marker ) {
	funds_flow_factory_expect( false === strpos( $factory_source, $side_effect_marker ), 'Factory source must contain no dispatch, network, persistence, or database path: ' . $side_effect_marker . '.' );
}

if ( '' !== $funds_flow_factory_dependency_output ) {
	echo $funds_flow_factory_dependency_output . PHP_EOL;
}
echo 'Tra-Vel commerce funds-flow factory runtime passed (' . $funds_flow_factory_assertions . ' exact-binding assertions; four collection/model paths).' . PHP_EOL;
