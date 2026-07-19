<?php
/**
 * Runtime coverage for owner-bound atomic package revalidation.
 */

require __DIR__ . '/validate-commerce-package-runtime.php';
require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-atomic-revalidator.php';

$revalidation_assertions = 0;

function tra_vel_commerce_revalidation_expect( $condition, $message ) {
	global $revalidation_assertions;
	$revalidation_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, 'Tra-Vel commerce revalidation runtime failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function tra_vel_commerce_revalidation_expect_error( $value, $code, $message ) {
	tra_vel_commerce_revalidation_expect( is_wp_error( $value ), $message . ' Expected WP_Error.' );
	if ( is_wp_error( $value ) ) {
		tra_vel_commerce_revalidation_expect( $code === $value->get_error_code(), $message . ' Unexpected code ' . $value->get_error_code() . '.' );
	}
}

function tra_vel_commerce_revalidation_engine( $fixture ) {
	$path = tempnam( sys_get_temp_dir(), 'travel-revalidation-' );
	tra_vel_commerce_revalidation_expect( false !== $path, 'A temporary fixture path must be available.' );
	$written = file_put_contents( $path, wp_json_encode( $fixture, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	tra_vel_commerce_revalidation_expect( is_int( $written ) && $written > 0, 'A temporary fixture revision must be written.' );
	$catalog = new Tra_Vel_Commerce_Sandbox_Catalog( $path );
	if ( is_file( $path ) ) {
		unlink( $path );
	}
	$readiness = $catalog->readiness();
	tra_vel_commerce_revalidation_expect( true === $readiness, 'Every temporary fixture revision must pass the closed catalog validator.' );
	return new Tra_Vel_Commerce_Search_Engine( $catalog, 'runtime-server-offer-secret-0001' );
}

function tra_vel_commerce_revalidation_product_index( $fixture, $provider_id ) {
	foreach ( $fixture['products'] as $index => $product ) {
		if ( $provider_id === $product['provider_id'] ) {
			return $index;
		}
	}
	return null;
}

function tra_vel_commerce_revalidation_offer_by_ref( $result, $offer_ref ) {
	foreach ( tra_vel_commerce_all_offers( $result ) as $offer ) {
		if ( $offer_ref === $offer['offer_ref'] ) {
			return $offer;
		}
	}
	return null;
}

function tra_vel_commerce_revalidation_expected_total( $package, $result ) {
	$total = 0;
	foreach ( $package['components'] as $component ) {
		$offer = tra_vel_commerce_revalidation_offer_by_ref( $result, $component['offer_ref'] );
		tra_vel_commerce_revalidation_expect( is_array( $offer ), 'Every revalidated component must bind one fresh public offer.' );
		$total += $offer['pricing']['total_amount_minor'];
	}
	return $total;
}

function tra_vel_commerce_revalidation_mutate_offer( $result, $offer_ref ) {
	foreach ( $result['groups'] as $group_index => $group ) {
		foreach ( $group['offers'] as $offer_index => $offer ) {
			if ( $offer_ref === $offer['offer_ref'] ) {
				$result['groups'][ $group_index ]['offers'][ $offer_index ]['product']['title'] .= ' tampered';
				return $result;
			}
		}
	}
	return $result;
}

$catalog_fixture_path = dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/assets/fixtures/commerce-sandbox/product-catalog.json';
$baseline_fixture = json_decode( file_get_contents( $catalog_fixture_path ), true );
tra_vel_commerce_revalidation_expect( is_array( $baseline_fixture ), 'The baseline catalog fixture must decode.' );

$revalidation_context = array(
	'owner_scope_digest'       => $context['owner_scope_digest'],
	'expected_package_version' => $package['version'],
	'expected_package_digest'  => $package['package_digest'],
	'now'                      => $context['now'] + 60,
);
$fresh_engine      = tra_vel_commerce_revalidation_engine( $baseline_fixture );
$fresh_revalidator = new Tra_Vel_Commerce_Atomic_Revalidator( $fresh_engine, $composer );
$fresh_package     = $fresh_revalidator->revalidate( $package, $result, $request, $revalidation_context );
tra_vel_commerce_revalidation_expect( is_array( $fresh_package ), 'An unchanged temporary inventory revision must revalidate atomically.' );
tra_vel_commerce_revalidation_expect( 'composed' === $fresh_package['status'] && 'fresh' === $fresh_package['revalidation']['state'], 'Observation-only reference and timestamp refreshes must remain commercially fresh.' );
tra_vel_commerce_revalidation_expect( $package['package_ref'] === $fresh_package['package_ref'] && $package['version'] + 1 === $fresh_package['version'], 'Revalidation must preserve aggregate identity and increment its version exactly once.' );
tra_vel_commerce_revalidation_expect( $package['created_at'] === $fresh_package['created_at'] && gmdate( 'Y-m-d\TH:i:s\Z', $revalidation_context['now'] ) === $fresh_package['updated_at'] && $fresh_package['updated_at'] === $fresh_package['revalidation']['checked_at'], 'Revalidation must preserve creation time and bind update/check time to the strict UTC clock.' );
tra_vel_commerce_revalidation_expect( $package['search_session_ref'] !== $fresh_package['search_session_ref'], 'Atomic revalidation must execute a new search observation.' );
tra_vel_commerce_revalidation_expect( count( $package['components'] ) === count( $fresh_package['components'] ), 'Fresh revalidation must replace every component snapshot without partial composition.' );
tra_vel_commerce_revalidation_expect( $package['pricing']['total_amount_minor'] === $fresh_package['pricing']['total_amount_minor'], 'A fresh observation must preserve the exact package total.' );
tra_vel_commerce_revalidation_expect( array( 'discount_status' => 'not_claimed', 'savings_amount_minor' => 0, 'comparator_digest' => null ) === $fresh_package['comparison'], 'Revalidation must never invent a savings comparison.' );
$fresh_basis = $fresh_package;
unset( $fresh_basis['package_digest'] );
tra_vel_commerce_revalidation_expect( hash_equals( $fresh_package['package_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $fresh_basis ) ), 'The revalidated revision must carry its exact canonical digest.' );
foreach ( $fresh_package['components'] as $index => $component ) {
	tra_vel_commerce_revalidation_expect( $package['components'][ $index ]['component_ref'] !== $component['component_ref'] && $package['components'][ $index ]['offer_ref'] !== $component['offer_ref'], 'Each component must bind the fresh observation instead of replaying old references.' );
	tra_vel_commerce_revalidation_expect( 1 === preg_match( '/^[a-f0-9]{64}$/', $component['offer_digest'] ), 'Each refreshed component must carry a closed offer digest.' );
	tra_vel_commerce_revalidation_expect( $package['components'][ $index ]['provider_id'] === $component['provider_id'] && hash_equals( $package['components'][ $index ]['provider_reference_digest'], $component['provider_reference_digest'] ), 'Each refreshed component must retain its explicit provider route and stable inventory lineage.' );
}

$deterministic_replay = $fresh_revalidator->revalidate( $package, $result, $request, $revalidation_context );
tra_vel_commerce_revalidation_expect( $fresh_package === $deterministic_replay, 'The same immutable inputs, secret, and clock must replay byte-stably before persistence CAS.' );

$stale_clock = $revalidation_context;
$stale_clock['now'] = strtotime( $package['updated_at'] );
tra_vel_commerce_revalidation_expect_error( $fresh_revalidator->revalidate( $package, $result, $request, $stale_clock ), 'tra_vel_commerce_revalidation_observation_replayed', 'A non-advancing observation clock must be rejected.' );

$wrong_owner = $revalidation_context;
$wrong_owner['owner_scope_digest'] = hash( 'sha256', 'cross-owner-revalidation' );
tra_vel_commerce_revalidation_expect_error( $fresh_revalidator->revalidate( $package, $result, $request, $wrong_owner ), 'tra_vel_commerce_revalidation_owner_conflict', 'A cross-owner package revalidation must fail before search.' );

$wrong_version = $revalidation_context;
$wrong_version['expected_package_version']++;
tra_vel_commerce_revalidation_expect_error( $fresh_revalidator->revalidate( $package, $result, $request, $wrong_version ), 'tra_vel_commerce_revalidation_revision_conflict', 'A stale expected version must fail the compare-and-swap boundary.' );

$wrong_digest = $revalidation_context;
$wrong_digest['expected_package_digest'] = str_repeat( 'd', 64 );
tra_vel_commerce_revalidation_expect_error( $fresh_revalidator->revalidate( $package, $result, $request, $wrong_digest ), 'tra_vel_commerce_revalidation_revision_conflict', 'A stale expected digest must fail the compare-and-swap boundary.' );

$tampered_package = $package;
$tampered_package['title'] .= ' tampered';
tra_vel_commerce_revalidation_expect_error( $fresh_revalidator->revalidate( $tampered_package, $result, $request, $revalidation_context ), 'tra_vel_commerce_revalidation_package_digest_invalid', 'A package mutation beneath its stored digest must fail closed.' );

$tampered_prior = tra_vel_commerce_revalidation_mutate_offer( $result, $package['components'][0]['offer_ref'] );
tra_vel_commerce_revalidation_expect_error( $fresh_revalidator->revalidate( $package, $tampered_prior, $request, $revalidation_context ), 'tra_vel_commerce_revalidation_prior_offer_digest_invalid', 'A prior public offer mutation must fail its component digest binding.' );

$price_fixture = $baseline_fixture;
$price_index = tra_vel_commerce_revalidation_product_index( $price_fixture, 'equipment_supplier_a' );
$price_fixture['products'][ $price_index ]['pricing']['unit_amount_minor'] += 1000;
$price_engine      = tra_vel_commerce_revalidation_engine( $price_fixture );
$price_revalidator = new Tra_Vel_Commerce_Atomic_Revalidator( $price_engine, $composer );
$price_package     = $price_revalidator->revalidate( $package, $result, $request, $revalidation_context );
tra_vel_commerce_revalidation_expect( is_array( $price_package ) && 'revalidation_required' === $price_package['status'] && 'changed' === $price_package['revalidation']['state'], 'A changed component price must require customer revalidation.' );
$price_result = $price_engine->search( $request, array( 'owner_scope_digest' => $context['owner_scope_digest'], 'now' => $revalidation_context['now'] ) );
tra_vel_commerce_revalidation_expect( tra_vel_commerce_revalidation_expected_total( $price_package, $price_result ) === $price_package['pricing']['total_amount_minor'], 'A changed package must use the exact sum of its new component offer ledgers.' );
tra_vel_commerce_revalidation_expect( $price_package['pricing']['total_amount_minor'] > $package['pricing']['total_amount_minor'] && 0 === $price_package['comparison']['savings_amount_minor'], 'A price increase must be explicit without an invented savings claim.' );

$terms_fixture = $baseline_fixture;
$terms_index = tra_vel_commerce_revalidation_product_index( $terms_fixture, 'dining_supplier_a' );
$terms_fixture['products'][ $terms_index ]['terms']['changes'] = 'Reservation-time changes require a fresh seeded table and meal availability check.';
$terms_engine      = tra_vel_commerce_revalidation_engine( $terms_fixture );
$terms_revalidator = new Tra_Vel_Commerce_Atomic_Revalidator( $terms_engine, $composer );
$terms_package     = $terms_revalidator->revalidate( $package, $result, $request, $revalidation_context );
tra_vel_commerce_revalidation_expect( is_array( $terms_package ) && 'changed' === $terms_package['revalidation']['state'], 'Changed supplier terms must be commercially material.' );
tra_vel_commerce_revalidation_expect( $package['pricing']['total_amount_minor'] === $terms_package['pricing']['total_amount_minor'], 'A terms-only revision must preserve the exact component-derived total.' );

$product_fixture = $baseline_fixture;
$product_index = tra_vel_commerce_revalidation_product_index( $product_fixture, 'activity_supplier_a' );
$product_fixture['products'][ $product_index ]['title'] .= ' revised';
$product_fixture['products'][ $product_index ]['geometry']['places'][0]['latitude'] += 0.0001;
$product_fixture['products'][ $product_index ]['capabilities'] = array_values( array_diff( $product_fixture['products'][ $product_index ]['capabilities'], array( 'refund' ) ) );
$product_engine      = tra_vel_commerce_revalidation_engine( $product_fixture );
$product_revalidator = new Tra_Vel_Commerce_Atomic_Revalidator( $product_engine, $composer );
$product_package     = $product_revalidator->revalidate( $package, $result, $request, $revalidation_context );
tra_vel_commerce_revalidation_expect( is_array( $product_package ) && 'changed' === $product_package['revalidation']['state'], 'Product, route geometry, or capability changes must be materially visible.' );

$availability_fixture = $baseline_fixture;
$availability_index = tra_vel_commerce_revalidation_product_index( $availability_fixture, 'equipment_supplier_a' );
$availability_fixture['products'][ $availability_index ]['inventory']['quantity_available'] = 5;
$availability_fixture['products'][ $availability_index ]['inventory']['limited_threshold'] = 2;
$availability_engine      = tra_vel_commerce_revalidation_engine( $availability_fixture );
$availability_revalidator = new Tra_Vel_Commerce_Atomic_Revalidator( $availability_engine, $composer );
$availability_package     = $availability_revalidator->revalidate( $package, $result, $request, $revalidation_context );
tra_vel_commerce_revalidation_expect( is_array( $availability_package ) && 'changed' === $availability_package['revalidation']['state'], 'A still-bookable but newly limited component must require revalidation.' );

$unavailable_fixture = $baseline_fixture;
$unavailable_index = tra_vel_commerce_revalidation_product_index( $unavailable_fixture, 'flight_supplier_a' );
$unavailable_fixture['products'][ $unavailable_index ]['inventory']['quantity_available'] = 1;
$unavailable_fixture['products'][ $unavailable_index ]['inventory']['limited_threshold'] = 1;
$unavailable_engine      = tra_vel_commerce_revalidation_engine( $unavailable_fixture );
$unavailable_revalidator = new Tra_Vel_Commerce_Atomic_Revalidator( $unavailable_engine, $composer );
$unavailable = $unavailable_revalidator->revalidate( $package, $result, $request, $revalidation_context );
tra_vel_commerce_revalidation_expect_error( $unavailable, 'tra_vel_commerce_revalidation_lineage_unavailable', 'An unavailable required lineage must fail the complete atomic operation.' );
$unavailable_data = $unavailable->get_error_data();
tra_vel_commerce_revalidation_expect( array( 'component', 'vertical', 'reason', 'replacement_offer_refs' ) === array_keys( $unavailable_data ), 'A missing-lineage error must expose only the bounded safe recovery contract.' );
tra_vel_commerce_revalidation_expect( 'flight' === $unavailable_data['vertical'] && 'required_lineage_unavailable' === $unavailable_data['reason'] && count( $unavailable_data['replacement_offer_refs'] ) >= 1 && count( $unavailable_data['replacement_offer_refs'] ) <= 5, 'An unavailable flight must return only bounded opaque same-vertical replacements.' );
foreach ( $unavailable_data['replacement_offer_refs'] as $replacement_ref ) {
	tra_vel_commerce_revalidation_expect( 1 === preg_match( '/^tv_offer_[A-Za-z0-9_-]{16,96}$/', $replacement_ref ), 'Every recovery reference must remain opaque.' );
}

$missing_fixture = $baseline_fixture;
$missing_index = tra_vel_commerce_revalidation_product_index( $missing_fixture, 'equipment_supplier_a' );
$missing_fixture['products'][ $missing_index ]['service_window']['available_from'] = '2027-01-01';
$missing_fixture['products'][ $missing_index ]['service_window']['available_until'] = '2027-12-31';
$missing_engine      = tra_vel_commerce_revalidation_engine( $missing_fixture );
$missing_revalidator = new Tra_Vel_Commerce_Atomic_Revalidator( $missing_engine, $composer );
$missing = $missing_revalidator->revalidate( $package, $result, $request, $revalidation_context );
tra_vel_commerce_revalidation_expect_error( $missing, 'tra_vel_commerce_revalidation_lineage_unavailable', 'A removed product lineage must fail closed without partial recomposition.' );
$missing_data = $missing->get_error_data();
tra_vel_commerce_revalidation_expect( 'equipment' === $missing_data['vertical'] && array() === $missing_data['replacement_offer_refs'], 'A missing vertical with no safe replacement must return an empty bounded replacement list.' );

$encoded_fresh = wp_json_encode( $fresh_package );
$encoded_error = wp_json_encode( $unavailable_data );
tra_vel_commerce_revalidation_expect( false === strpos( $encoded_fresh, 'private_product_ref' ) && false === strpos( $encoded_fresh, 'px_' ) && false === strpos( $encoded_fresh, 'commission' ), 'A revalidated package must expose no private fixture reference or commission economics.' );
tra_vel_commerce_revalidation_expect( false === strpos( $encoded_error, 'private_product_ref' ) && false === strpos( $encoded_error, 'px_' ) && false === strpos( $encoded_error, 'commission' ) && false === strpos( $encoded_error, 'provider_id' ), 'A recovery error must expose no private fixture, provider, or commission reference.' );

echo 'Tra-Vel commerce revalidation runtime passed (' . $revalidation_assertions . ' atomic assertions).' . PHP_EOL;
