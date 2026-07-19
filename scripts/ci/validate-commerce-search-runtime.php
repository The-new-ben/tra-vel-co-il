<?php
/**
 * Deterministic runtime coverage for seeded Commerce Core search.
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code, $message, $data = array() ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) {
		return $value instanceof WP_Error;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ) {
		return strip_tags( (string) $value );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'commerce-runtime-' . (string) $scheme . '-deterministic-secret';
	}
}

require_once dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-taxonomy.php';
require_once dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-money.php';
require_once dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-policy.php';
require_once dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-sandbox-network.php';
require_once dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-sandbox-catalog.php';
require_once dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-commerce-search-engine.php';

$assertions = 0;

function tra_vel_commerce_search_fail( $message ) {
	fwrite( STDERR, 'Tra-Vel commerce search runtime failed: ' . $message . PHP_EOL );
	exit( 1 );
}

function tra_vel_commerce_search_expect( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		tra_vel_commerce_search_fail( $message );
	}
}

function tra_vel_commerce_search_expect_error( $value, $code, $message ) {
	tra_vel_commerce_search_expect( is_wp_error( $value ), $message . ' Expected WP_Error.' );
	if ( is_wp_error( $value ) ) {
		tra_vel_commerce_search_expect( $code === $value->get_error_code(), $message . ' Unexpected code ' . $value->get_error_code() . '.' );
	}
}

function tra_vel_commerce_truth() {
	return array(
		'simulated_inventory' => true,
		'real_supplier_request'=> false,
		'real_inventory_hold'  => false,
		'real_charge'          => false,
		'real_booking'         => false,
		'real_policy_issuance' => false,
		'real_settlement'      => false,
	);
}

function tra_vel_commerce_boundary() {
	return array(
		'raw_supplier_reference_exposed' => false,
		'raw_payment_data_exposed'       => false,
		'medical_data_exposed'           => false,
	);
}

function tra_vel_commerce_request( $overrides = array() ) {
	$request = array(
		'contract_version' => '1.0.0',
		'environment'      => 'sandbox',
		'request_ref'      => 'tv_request_abcdefghijklmnop',
		'request_digest'   => str_repeat( '0', 64 ),
		'verticals'        => array( 'accommodation', 'activity', 'connectivity', 'dining', 'equipment', 'flight', 'insurance', 'package', 'transfer' ),
		'trip'             => array(
			'origin'             => array( 'kind' => 'iata', 'code' => 'TLV', 'label' => 'Tel Aviv', 'latitude' => 32.0055, 'longitude' => 34.8854 ),
			'destination_mode'   => 'fixed',
			'destinations'       => array( array( 'kind' => 'iata', 'code' => 'HKT', 'label' => 'Phuket', 'latitude' => 8.1132, 'longitude' => 98.3169 ) ),
			'date_window'        => array(
				'departure_earliest' => '2026-08-01',
				'departure_latest'   => '2026-08-01',
				'return_earliest'    => '2026-08-08',
				'return_latest'      => '2026-08-08',
				'nights_min'         => 7,
				'nights_max'         => 7,
			),
			'travelers'          => array( 'adults' => 2, 'children' => 2, 'infants' => 0, 'rooms' => 2 ),
			'currency'           => 'THB',
			'budget_limit_minor' => null,
		),
		'preferences'      => array(
			'direct_only'            => false,
			'max_stops'              => 1,
			'priorities'             => array( 'price', 'family' ),
			'vibes'                  => array( 'beach', 'family' ),
			'accessibility_requested'=> false,
		),
		'ranking'         => array(
			'profile'               => 'family',
			'ranking_version'       => '1.0.0',
			'selection_seed_digest' => str_repeat( 'a', 64 ),
		),
		'sandbox_truth'   => tra_vel_commerce_truth(),
		'data_boundary'   => tra_vel_commerce_boundary(),
	);
	foreach ( $overrides as $key => $value ) {
		$request[ $key ] = $value;
	}
	$basis = $request;
	unset( $basis['request_digest'] );
	$request['request_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $basis );
	return $request;
}

function tra_vel_commerce_all_offers( $result ) {
	$offers = array();
	foreach ( $result['groups'] as $group ) {
		foreach ( $group['offers'] as $offer ) {
			$offers[] = $offer;
		}
	}
	return $offers;
}

function tra_vel_commerce_offer_by_provider( $result, $provider_id ) {
	foreach ( tra_vel_commerce_all_offers( $result ) as $offer ) {
		if ( $provider_id === $offer['provider_id'] ) {
			return $offer;
		}
	}
	return null;
}

$fixture_path = dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/assets/fixtures/commerce-sandbox/product-catalog.json';
$catalog = new Tra_Vel_Commerce_Sandbox_Catalog( $fixture_path );
tra_vel_commerce_search_expect( true === $catalog->readiness(), 'The bundled product catalog must pass its complete closed validator.' );
tra_vel_commerce_search_expect( 1 === preg_match( '/^[a-f0-9]{64}$/', $catalog->catalog_digest() ), 'The validated catalog must expose a stable canonical digest.' );

$expected_providers = array(
	'accommodation_supplier_a', 'accommodation_supplier_b', 'activity_supplier_a', 'activity_supplier_b',
	'connectivity_supplier_a', 'dining_supplier_a', 'equipment_supplier_a', 'flight_supplier_a',
	'flight_supplier_b', 'flight_supplier_c', 'insurance_supplier_a', 'insurance_supplier_b',
	'transfer_supplier_a', 'transfer_supplier_b',
);
$catalog_providers = array();
foreach ( Tra_Vel_Commerce_Taxonomy::VERTICALS as $vertical ) {
	$providers = $catalog->provider_ids_for_vertical( $vertical );
	tra_vel_commerce_search_expect( is_array( $providers ) && ( 'package' === $vertical ? empty( $providers ) : ! empty( $providers ) ), 'Each component vertical must have searchable providers while packages remain atomic compositions: ' . $vertical . '.' );
	$catalog_providers = array_merge( $catalog_providers, $providers );
}
$catalog_providers = array_values( array_unique( $catalog_providers ) );
sort( $catalog_providers, SORT_STRING );
tra_vel_commerce_search_expect( $expected_providers === $catalog_providers, 'The catalog must contain every explicitly required seeded provider.' );

$engine = new Tra_Vel_Commerce_Search_Engine( $catalog, 'runtime-server-offer-secret-0001' );
$request = tra_vel_commerce_request();
$context = array( 'owner_scope_digest' => str_repeat( 'c', 64 ), 'now' => strtotime( '2026-07-19T12:00:00Z' ) );
$result = $engine->search( $request, $context );
tra_vel_commerce_search_expect( is_array( $result ), 'A complete family query must return a deterministic search projection.' );
tra_vel_commerce_search_expect( 'complete' === $result['session']['status'], 'All seeded provider runs must complete.' );
tra_vel_commerce_search_expect( 14 === $result['session']['counts']['providers_considered'] && 14 === $result['session']['counts']['providers_succeeded'] && 0 === $result['session']['counts']['providers_failed'], 'The engine must fan out through all fourteen component-inventory providers.' );
tra_vel_commerce_search_expect( 14 === $result['session']['counts']['offers_validated'] && 0 === $result['session']['counts']['offers_rejected'], 'Commercially distinct supplier fare variants must survive deduplication and reach ranking.' );
tra_vel_commerce_search_expect( 8 === count( $result['groups'] ), 'Offers must form same-currency/scope groups for each component vertical; packages are composed later.' );
tra_vel_commerce_search_expect( $result === $engine->search( $request, $context ), 'The same request, owner, time, fixture, and secret must produce byte-stable data.' );

$offers = tra_vel_commerce_all_offers( $result );
tra_vel_commerce_search_expect( 14 === count( $offers ), 'The public projection must contain fourteen commercially distinct component offers.' );
$vertical_counts = array_fill_keys( Tra_Vel_Commerce_Taxonomy::VERTICALS, 0 );
$offer_refs = array();
foreach ( $result['groups'] as $group ) {
	$expected_rank = 1;
	foreach ( $group['offers'] as $offer ) {
		$vertical_counts[ $offer['vertical'] ]++;
		tra_vel_commerce_search_expect( $group['vertical'] === $offer['vertical'] && $group['currency'] === $offer['pricing']['currency'] && $group['price_scope'] === $offer['pricing']['price_scope'], 'Every group must contain only the same vertical, currency, and price scope.' );
		tra_vel_commerce_search_expect( $expected_rank === $offer['ranking']['rank'], 'Ranks must be dense and deterministic inside each comparable group.' );
		tra_vel_commerce_search_expect( 1 === preg_match( '/^tv_offer_[A-Za-z0-9_-]{16,96}$/', $offer['offer_ref'] ) && 1 === preg_match( '/^tv_product_[A-Za-z0-9_-]{16,96}$/', $offer['product']['product_ref'] ), 'Offer and product identities must be opaque server references.' );
		tra_vel_commerce_search_expect( 1 === preg_match( '/^[a-f0-9]{64}$/', $offer['provider_reference_digest'] ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $offer['terms']['terms_digest'] ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $offer['evidence']['evidence_digest'] ), 'Commercial source, terms, and evidence must be digest-bound.' );
		$debits = $offer['pricing']['subtotal_amount_minor'] + $offer['pricing']['tax_amount_minor'] + $offer['pricing']['fee_amount_minor'];
		tra_vel_commerce_search_expect( is_int( $offer['pricing']['total_amount_minor'] ) && $offer['pricing']['total_amount_minor'] === $debits - $offer['pricing']['credit_amount_minor'], 'Every offer total must reconcile in integer minor units.' );
		tra_vel_commerce_search_expect( true === $offer['sandbox_truth']['simulated_inventory'] && false === $offer['sandbox_truth']['real_booking'] && false === $offer['data_boundary']['raw_supplier_reference_exposed'], 'Every offer must retain the non-live sandbox truth boundary.' );
		foreach ( $offer['geometry']['places'] as $place ) {
			tra_vel_commerce_search_expect( 1 === preg_match( '/^tv_place_[A-Za-z0-9_-]{16,96}$/', $place['place_ref'] ), 'Map places must use opaque public references.' );
		}
		$offer_refs[] = $offer['offer_ref'];
		$expected_rank++;
	}
}
tra_vel_commerce_search_expect( count( $offer_refs ) === count( array_unique( $offer_refs ) ), 'Opaque offer references must be unique within a search.' );
tra_vel_commerce_search_expect( 3 === $vertical_counts['flight'] && 2 === $vertical_counts['accommodation'] && 2 === $vertical_counts['transfer'] && 2 === $vertical_counts['activity'] && 2 === $vertical_counts['insurance'], 'Comparison-rich verticals must preserve multiple post-deduplication options, including a distinct affiliate fare.' );
foreach ( Tra_Vel_Commerce_Taxonomy::VERTICALS as $vertical ) {
	tra_vel_commerce_search_expect( 'package' === $vertical ? 0 === $vertical_counts[ $vertical ] : $vertical_counts[ $vertical ] >= 1, 'Component verticals must reach public search while package inventory cannot bypass composition: ' . $vertical . '.' );
}
tra_vel_commerce_search_expect( $result['catalog_digest'] === $result['session']['catalog_digest'] && $result['provider_network_digest'] === $result['session']['provider_network_digest'], 'The persisted session must bind both catalog and canonical provider-network revisions.' );

$encoded = wp_json_encode( $result );
tra_vel_commerce_search_expect( false === strpos( $encoded, 'private_product_ref' ) && false === strpos( $encoded, 'px_' ) && false === strpos( $encoded, 'commission' ), 'Public search output must not expose private fixture references or commission economics.' );
tra_vel_commerce_search_expect( is_array( tra_vel_commerce_offer_by_provider( $result, 'flight_supplier_c' ) ), 'A distinct flexible affiliate fare must remain selectable instead of being erased as an equivalent duplicate.' );

$flight_a = tra_vel_commerce_offer_by_provider( $result, 'flight_supplier_a' );
$flight_b = tra_vel_commerce_offer_by_provider( $result, 'flight_supplier_b' );
$stay_a = tra_vel_commerce_offer_by_provider( $result, 'accommodation_supplier_a' );
$stay_b = tra_vel_commerce_offer_by_provider( $result, 'accommodation_supplier_b' );
$insurance_a = tra_vel_commerce_offer_by_provider( $result, 'insurance_supplier_a' );
tra_vel_commerce_search_expect( 8301800 === $flight_a['pricing']['total_amount_minor'], 'Tiered adult/child flight pricing must compute the exact whole-party total.' );
tra_vel_commerce_search_expect( 7226200 === $flight_b['pricing']['total_amount_minor'], 'The connected flight must independently compute exact party pricing.' );
tra_vel_commerce_search_expect( 11145000 === $stay_a['pricing']['total_amount_minor'] && 6511600 === $stay_b['pricing']['total_amount_minor'], 'Accommodation totals must multiply room count by requested nights before tax, fee, and credit.' );
tra_vel_commerce_search_expect( 345000 === $insurance_a['pricing']['total_amount_minor'], 'Insurance must multiply tiered traveler rates by exact trip days.' );

$direct_request = $request;
$direct_request['preferences']['direct_only'] = true;
$direct_basis = $direct_request;
unset( $direct_basis['request_digest'] );
$direct_request['request_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $direct_basis );
$direct_result = $engine->search( $direct_request, $context );
$direct_flights = array_values( array_filter( tra_vel_commerce_all_offers( $direct_result ), static function ( $offer ) { return 'flight' === $offer['vertical']; } ) );
$direct_flight_providers = array_column( $direct_flights, 'provider_id' );
sort( $direct_flight_providers, SORT_STRING );
tra_vel_commerce_search_expect( array( 'flight_supplier_a', 'flight_supplier_c' ) === $direct_flight_providers, 'A direct-only query must remove connecting inventory while preserving distinct direct commercial alternatives.' );

$changed_trip = $request['trip'];
$changed_trip['travelers'] = array( 'adults' => 2, 'children' => 1, 'infants' => 0, 'rooms' => 1 );
$changed_trip['date_window']['return_earliest'] = '2026-08-11';
$changed_trip['date_window']['return_latest'] = '2026-08-11';
$changed_trip['date_window']['nights_min'] = 10;
$changed_trip['date_window']['nights_max'] = 10;
$changed_request = tra_vel_commerce_request( array( 'trip' => $changed_trip ) );
$changed_result = $engine->search( $changed_request, $context );
$changed_stay_a = tra_vel_commerce_offer_by_provider( $changed_result, 'accommodation_supplier_a' );
$changed_insurance_a = tra_vel_commerce_offer_by_provider( $changed_result, 'insurance_supplier_a' );
tra_vel_commerce_search_expect( $changed_stay_a['pricing']['total_amount_minor'] !== $stay_a['pricing']['total_amount_minor'], 'Room and night changes must deterministically rebuild accommodation totals.' );
tra_vel_commerce_search_expect( $changed_insurance_a['pricing']['total_amount_minor'] !== $insurance_a['pricing']['total_amount_minor'], 'Party and trip-day changes must deterministically rebuild insurance totals.' );

$query_method = new ReflectionMethod( Tra_Vel_Commerce_Search_Engine::class, 'query_from_request' );
$query_method->setAccessible( true );
$internal_query = $query_method->invoke( $engine, $request );
$internal_flight_candidates = $catalog->search_provider( 'flight_supplier_a', 'flight', $internal_query );
tra_vel_commerce_search_expect( 373581 === $internal_flight_candidates[0]['commission']['amount_minor'], 'Gross-total commission basis must produce a deterministic internal minor-unit amount.' );
$internal_equipment = $catalog->search_provider( 'equipment_supplier_a', 'equipment', $internal_query );
tra_vel_commerce_search_expect( 60000 === $internal_equipment[0]['commission']['amount_minor'], 'Fixed-order commission basis must remain independent of party pricing.' );

$other_secret_engine = new Tra_Vel_Commerce_Search_Engine( $catalog, 'runtime-server-offer-secret-0002' );
$other_secret_result = $other_secret_engine->search( $request, $context );
tra_vel_commerce_search_expect( $other_secret_result['session']['session_ref'] !== $result['session']['session_ref'], 'Opaque references must be keyed by a server secret.' );
tra_vel_commerce_search_expect( tra_vel_commerce_offer_by_provider( $other_secret_result, 'flight_supplier_a' )['pricing'] === $flight_a['pricing'], 'Changing the opaque-reference secret must not change deterministic product pricing.' );

$bad_digest = $request;
$bad_digest['trip']['travelers']['children'] = 3;
tra_vel_commerce_search_expect_error( $engine->search( $bad_digest, $context ), 'tra_vel_commerce_search_request_digest_invalid', 'Mutated request data must not pass the canonical request digest.' );
$unknown_request = $request;
$unknown_request['raw_prompt'] = 'must not enter commerce search';
$unknown_basis = $unknown_request;
unset( $unknown_basis['request_digest'] );
$unknown_request['request_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $unknown_basis );
tra_vel_commerce_search_expect_error( $engine->search( $unknown_request, $context ), 'tra_vel_commerce_search_request_invalid', 'Unknown request properties must fail the closed boundary.' );

$fixture = json_decode( file_get_contents( $fixture_path ), true );
$fixture['products'][0]['raw_supplier_reference'] = 'must-not-persist';
$invalid_fixture_path = tempnam( sys_get_temp_dir(), 'travel-commerce-' );
file_put_contents( $invalid_fixture_path, wp_json_encode( $fixture ) );
$invalid_catalog = new Tra_Vel_Commerce_Sandbox_Catalog( $invalid_fixture_path );
tra_vel_commerce_search_expect_error( $invalid_catalog->readiness(), 'tra_vel_commerce_fixture_product_invalid', 'Unknown fixture product fields must fail closed.' );
unlink( $invalid_fixture_path );

echo 'Tra-Vel commerce search runtime passed (' . $assertions . ' deterministic assertions).' . PHP_EOL;
