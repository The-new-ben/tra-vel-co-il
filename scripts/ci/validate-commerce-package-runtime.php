<?php
/**
 * Atomic package-composition runtime checks built on the deterministic search fixture.
 */

require __DIR__ . '/validate-commerce-search-runtime.php';
require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-package-composer.php';

$package_assertions = 0;

function tra_vel_commerce_package_expect( $condition, $message ) {
	global $package_assertions;
	$package_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, 'Tra-Vel commerce package runtime failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function tra_vel_commerce_package_expect_error( $value, $code, $message ) {
	tra_vel_commerce_package_expect( is_wp_error( $value ) && $code === $value->get_error_code(), $message );
}

$all_offers = tra_vel_commerce_all_offers( $result );
$selected_providers = array(
	array( 'flight_supplier_a', 'outbound', 1 ),
	array( 'accommodation_supplier_b', 'stay', 1 ),
	array( 'transfer_supplier_a', 'arrival', 1 ),
	array( 'activity_supplier_a', 'experience', 3 ),
	array( 'dining_supplier_a', 'meal', 3 ),
	array( 'insurance_supplier_a', 'coverage', 1 ),
	array( 'connectivity_supplier_a', 'connection', 1 ),
	array( 'equipment_supplier_a', 'gear', 2 ),
);
$components = array();
$expected_total = 0;
$expected_lines = 0;
foreach ( $selected_providers as $selected_provider ) {
	$offer = tra_vel_commerce_offer_by_provider( $result, $selected_provider[0] );
	tra_vel_commerce_package_expect( is_array( $offer ), 'Every selected provider must expose one server-owned offer.' );
	$components[] = array(
		'offer_ref'     => $offer['offer_ref'],
		'offer_version' => $offer['version'],
		'role'          => $selected_provider[1],
		'required'      => true,
		'day'           => $selected_provider[2],
	);
	$expected_total += $offer['pricing']['total_amount_minor'];
	$expected_lines += count( $offer['pricing']['line_items'] );
}

$selection = array(
	'title'      => 'Thailand family sandbox package',
	'components' => $components,
);
$composer = new Tra_Vel_Commerce_Package_Composer( 'runtime-server-package-secret-0001' );
$package = $composer->compose( $result['session'], $all_offers, $selection, $context );
tra_vel_commerce_package_expect( is_array( $package ), 'A valid multi-vertical package must compose.' );
tra_vel_commerce_package_expect( 'composed' === $package['status'] && 1 === $package['version'], 'A fresh package must expose an immutable composed version.' );
tra_vel_commerce_package_expect( 8 === count( $package['components'] ) && 8 === count( $package['itinerary'] ), 'Every selected offer must become one component and itinerary entry.' );
tra_vel_commerce_package_expect( $expected_total === $package['pricing']['total_amount_minor'], 'Package total must equal the exact sum of component totals.' );
tra_vel_commerce_package_expect( $expected_lines === count( $package['pricing']['line_items'] ), 'Package composition must preserve every evidenced component line item.' );
tra_vel_commerce_package_expect( 'THB' === $package['pricing']['currency'] && 'package_total' === $package['pricing']['price_scope'], 'Package money must retain the shared currency and package-total scope.' );
tra_vel_commerce_package_expect( array( 'discount_status' => 'not_claimed', 'savings_amount_minor' => 0, 'comparator_digest' => null ) === $package['comparison'], 'Package composition must never invent a discount or savings comparator.' );
tra_vel_commerce_package_expect( array( 'mode' => 'atomic', 'all_components_required' => true, 'state' => 'not_started', 'checked_at' => null ) === $package['revalidation'], 'All package components must require one atomic revalidation.' );
tra_vel_commerce_package_expect( 1 === preg_match( '/^tv_package_[A-Za-z0-9_-]{16,96}$/', $package['package_ref'] ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $package['package_digest'] ), 'Package identity and integrity must use opaque and digest references.' );
tra_vel_commerce_package_expect( $package['owner_scope_digest'] === $context['owner_scope_digest'] && $package['search_session_ref'] === $result['session']['session_ref'], 'Package must remain bound to its owner and source search session.' );
tra_vel_commerce_package_expect( $package['sandbox_truth']['simulated_inventory'] && ! $package['sandbox_truth']['real_booking'] && ! $package['data_boundary']['raw_supplier_reference_exposed'], 'Package must preserve the sandbox truth and data boundary.' );

$component_refs = array_column( $package['components'], 'component_ref' );
tra_vel_commerce_package_expect( count( $component_refs ) === count( array_unique( $component_refs ) ), 'Component references must be unique.' );
foreach ( $package['components'] as $index => $component ) {
	tra_vel_commerce_package_expect( $index + 1 === $component['sequence'], 'Component sequence must be dense and deterministic.' );
	tra_vel_commerce_package_expect( 1 === preg_match( '/^tv_component_[A-Za-z0-9_-]{16,96}$/', $component['component_ref'] ), 'Every component reference must be opaque.' );
	tra_vel_commerce_package_expect( 1 === preg_match( '/^[a-f0-9]{64}$/', $component['offer_digest'] ), 'Every component must bind an immutable offer digest.' );
}
foreach ( $package['pricing']['line_items'] as $line ) {
	tra_vel_commerce_package_expect( in_array( $line['component_ref'], $component_refs, true ) && 1 === preg_match( '/^c[1-9][0-9]*_[a-z0-9]+(?:_[a-z0-9]+)*$/', $line['code'] ), 'Every package price line must point to one component with a collision-safe code.' );
}

$repeat = $composer->compose( $result['session'], $all_offers, $selection, $context );
tra_vel_commerce_package_expect( $package === $repeat, 'The same owner, search, selection, clock, and secret must replay deterministically.' );
$other_secret = ( new Tra_Vel_Commerce_Package_Composer( 'runtime-server-package-secret-0002' ) )->compose( $result['session'], $all_offers, $selection, $context );
tra_vel_commerce_package_expect( $other_secret['package_ref'] !== $package['package_ref'] && $other_secret['pricing']['currency'] === $package['pricing']['currency'] && $other_secret['pricing']['total_amount_minor'] === $package['pricing']['total_amount_minor'], 'Secret rotation must change opaque identities without changing package economics.' );

$wrong_owner = $context;
$wrong_owner['owner_scope_digest'] = hash( 'sha256', 'another-owner' );
tra_vel_commerce_package_expect_error( $composer->compose( $result['session'], $all_offers, $selection, $wrong_owner ), 'tra_vel_commerce_package_session_invalid', 'Another owner must not compose from the session.' );

$expired_session = $result['session'];
$expired_session['expires_at'] = gmdate( 'Y-m-d\TH:i:s\Z', $context['now'] );
tra_vel_commerce_package_expect_error( $composer->compose( $expired_session, $all_offers, $selection, $context ), 'tra_vel_commerce_package_session_expired', 'An expired search session must fail package composition.' );

$duplicate = $selection;
$duplicate['components'][] = $duplicate['components'][0];
tra_vel_commerce_package_expect_error( $composer->compose( $result['session'], $all_offers, $duplicate, $context ), 'tra_vel_commerce_package_component_duplicate', 'Selecting the same offer twice must fail.' );

$wrong_role = $selection;
$wrong_role['components'][0]['role'] = 'stay';
tra_vel_commerce_package_expect_error( $composer->compose( $result['session'], $all_offers, $wrong_role, $context ), 'tra_vel_commerce_package_component_role_invalid', 'A role that does not match its vertical must fail.' );

$wrong_version = $selection;
$wrong_version['components'][0]['offer_version']++;
tra_vel_commerce_package_expect_error( $composer->compose( $result['session'], $all_offers, $wrong_version, $context ), 'tra_vel_commerce_package_offer_not_in_session', 'A browser-selected offer version must match the ranked session version.' );

tra_vel_commerce_package_expect( null === tra_vel_commerce_offer_by_provider( $result, 'package_supplier_a' ), 'A package must be composed from immutable components and never enter search as an independently priced offer.' );

$tampered_offers = $all_offers;
$tampered_offers[0]['pricing']['total_amount_minor']++;
tra_vel_commerce_package_expect_error( $composer->compose( $result['session'], $tampered_offers, $selection, $context ), 'tra_vel_commerce_package_offer_money_invalid', 'A tampered offer ledger must fail closed.' );

$unknown_selection = $selection;
$unknown_selection['raw_supplier_reference'] = 'forbidden';
tra_vel_commerce_package_expect_error( $composer->compose( $result['session'], $all_offers, $unknown_selection, $context ), 'tra_vel_commerce_package_selection_invalid', 'Unknown selection fields must fail closed.' );

$encoded_package = wp_json_encode( $package );
tra_vel_commerce_package_expect( false === strpos( $encoded_package, 'private_product_ref' ) && false === strpos( $encoded_package, 'px_' ) && false === strpos( $encoded_package, 'commission' ), 'Package output must expose no private product reference or commission economics.' );

echo 'Tra-Vel commerce package runtime passed (' . $package_assertions . ' atomic assertions).' . PHP_EOL;
