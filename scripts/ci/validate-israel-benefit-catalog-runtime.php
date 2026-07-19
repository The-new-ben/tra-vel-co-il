<?php
/**
 * Adversarial runtime checks for the source-backed Israeli benefit catalogue.
 */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code, $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }

$commerce = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/';
require_once $commerce . 'class-tra-vel-benefit-taxonomy.php';
require_once $commerce . 'class-tra-vel-benefit-policy.php';
require_once $commerce . 'class-tra-vel-israel-benefit-catalog-registry.php';

$fixture_path = __DIR__ . '/../../plugin/tra-vel-agent-core/assets/fixtures/israel-benefit-catalog.json';
$fixture      = json_decode( file_get_contents( $fixture_path ), true );
$assertions   = 0;
$scenarios    = 0;

function israel_benefit_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "Israel benefit catalog runtime failed: {$message}\n" );
		exit( 1 );
	}
}

function israel_benefit_scenario() {
	global $scenarios;
	$scenarios++;
}

function israel_benefit_error( $value, $code, $message ) {
	israel_benefit_assert( is_wp_error( $value ), $message . ' (expected WP_Error)' );
	israel_benefit_assert( $code === $value->get_error_code(), $message . ' (unexpected error code: ' . $value->get_error_code() . ')' );
	return $value;
}

function israel_benefit_request( $overrides = array() ) {
	return array_merge(
		array(
			'airline_inventory_id' => null,
			'program_id'            => null,
			'credential_product_id' => null,
			'payment_network_id'    => null,
			'redemption_portal_id'  => null,
			'campaign_id'           => null,
			'campaign_version'      => null,
			'eligibility_claim'     => 'none',
		),
		$overrides
	);
}

function israel_benefit_index( $rows, $field ) {
	$index = array();
	foreach ( $rows as $row ) {
		$index[ $row[ $field ] ] = $row;
	}
	return $index;
}

function israel_benefit_temp_fixture( $fixture, $suffix ) {
	$path = tempnam( sys_get_temp_dir(), 'tra-vel-benefit-' . $suffix . '-' );
	if ( false === $path || false === file_put_contents( $path, json_encode( $fixture, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) ) {
		fwrite( STDERR, "Israel benefit catalog runtime failed: could not create an isolated adversarial fixture.\n" );
		exit( 1 );
	}
	return $path;
}

function israel_benefit_keys( $value, &$keys ) {
	if ( ! is_array( $value ) ) {
		return;
	}
	foreach ( $value as $key => $item ) {
		if ( is_string( $key ) ) {
			$keys[ $key ] = true;
		}
		israel_benefit_keys( $item, $keys );
	}
}

israel_benefit_assert( is_array( $fixture ) && JSON_ERROR_NONE === json_last_error(), 'bundled catalogue must be valid JSON' );

$registry = new Tra_Vel_Israel_Benefit_Catalog_Registry( $fixture_path );
israel_benefit_scenario();
israel_benefit_assert( true === $registry->load(), 'closed source-backed catalogue must load' );
israel_benefit_assert( true === $registry->readiness( '2026-07-19T12:00:00Z' ), 'catalogue must be current during its reviewed freshness window' );
$summary = $registry->summary( '2026-07-19T12:00:00Z' );
israel_benefit_assert( ! is_wp_error( $summary ), 'current catalogue summary must be available' );
israel_benefit_assert( 3 === $summary['counts']['airline_inventory'], 'catalogue must keep EL AL, Arkia, and Israir inventory identities explicit' );
israel_benefit_assert( 3 === $summary['counts']['programs'], 'catalogue must include Matmid, FlyAll, and SKYMAX as distinct programs' );
israel_benefit_assert( 4 === $summary['counts']['payment_networks'], 'catalogue must keep payment rails on their own axis' );
israel_benefit_assert( 8 === $summary['counts']['credential_products'], 'catalogue must contain eight exact issuer/card/rail products' );
israel_benefit_assert( 3 === $summary['counts']['redemption_portals'], 'catalogue must keep three redemption portals independent' );
israel_benefit_assert( 4 === $summary['counts']['campaign_versions'], 'catalogue must expose four immutable current campaign revisions' );
israel_benefit_assert( 1 === $summary['counts']['migrations'], 'catalogue must preserve the Cal FLY CARD transition as a migration record' );
israel_benefit_assert( 1 === preg_match( '/^[a-f0-9]{64}$/', $summary['catalog_digest'] ), 'catalogue must expose a deterministic SHA-256 digest' );
foreach ( $summary['commercial_truth'] as $truth ) {
	israel_benefit_assert( false === $truth, 'catalogue summary must keep every commercial truth flag false' );
}

$airlines    = israel_benefit_index( $fixture['airline_inventory'], 'airline_inventory_id' );
$programs    = israel_benefit_index( $fixture['programs'], 'program_id' );
$credentials = israel_benefit_index( $fixture['credential_products'], 'credential_product_id' );
$networks    = israel_benefit_index( $fixture['payment_networks'], 'payment_network_id' );
$campaigns   = array();
foreach ( $fixture['campaign_versions'] as $campaign ) {
	$campaigns[ $campaign['campaign_id'] . ':' . $campaign['version'] ] = $campaign;
}

israel_benefit_scenario();
israel_benefit_assert( 'points' === $programs['program_elal_matmid']['unit_type'], 'Matmid must remain a points program' );
israel_benefit_assert( 'program_value_minor' === $programs['program_cal_flyall']['unit_type'], 'FlyAll must remain currency-like program value, not Matmid points' );
israel_benefit_assert( 'points' === $programs['program_max_skymax']['unit_type'], 'SKYMAX must remain its own points program' );
israel_benefit_assert( $programs['program_elal_matmid']['program_id'] !== $programs['program_cal_flyall']['program_id'], 'FlyAll and Matmid identities must never collapse' );
foreach ( $fixture['programs'] as $program ) {
	israel_benefit_assert( ! is_wp_error( Tra_Vel_Benefit_Policy::benefit_program( $program ) ), 'every program must satisfy the shared benefit-program contract' );
	israel_benefit_assert( false === $program['commercial_truth']['live_connection'] && false === $program['commercial_truth']['live_redemption'], 'program catalogue rows must never claim connection or redemption' );
}
foreach ( $fixture['credential_products'] as $credential ) {
	israel_benefit_assert( ! is_wp_error( Tra_Vel_Benefit_Policy::credential_product( $credential ) ), 'every exact card product must satisfy the shared credential contract' );
	israel_benefit_assert( isset( $networks[ $credential['network_id'] ] ) && 'network_identity_only' !== $networks[ $credential['network_id'] ]['scope'], 'every card product must bind an exact, non-generic payment rail' );
	israel_benefit_assert( false === $credential['commercial_truth']['live_eligibility_verification'], 'a card identity must not imply live eligibility' );
}
foreach ( $fixture['campaign_versions'] as $campaign ) {
	israel_benefit_assert( ! is_wp_error( Tra_Vel_Benefit_Policy::campaign_version( $campaign ) ), 'every campaign revision must satisfy the shared immutable campaign contract' );
	israel_benefit_assert( false === $campaign['commercial_truth']['provider_quote_available'] && false === $campaign['commercial_truth']['checkout_application_available'], 'campaign evidence must not imply a quote or checkout application' );
}
israel_benefit_assert( 'network_identity_only' === $networks['network_visa']['scope'], 'Visa must remain a network-only identity' );
foreach ( $credentials as $credential ) {
	israel_benefit_assert( 'network_visa' !== $credential['network_id'], 'no generic Visa identity may masquerade as an exact card product' );
}
israel_benefit_assert( '6H' === $airlines['airline_israir']['iata_code'], 'Israir inventory must preserve the first-party 6H airline identity' );
israel_benefit_assert( 'https://www.israir.co.il/Passengers_Info/Seating_Policy' === $airlines['airline_israir']['source']['official_source_url'], 'Israir inventory must retain its reviewed first-party source' );
israel_benefit_assert( false === $airlines['airline_israir']['commercial_truth']['live_inventory'], 'Israir inventory identity must not imply live availability' );

israel_benefit_scenario();
$arkia = $registry->plan(
	israel_benefit_request( array( 'airline_inventory_id' => 'airline_arkia' ) ),
	'2026-07-19T12:00:00Z'
);
israel_benefit_assert( ! is_wp_error( $arkia ), 'Arkia inventory selection must be usable without inventing a loyalty program' );
israel_benefit_assert( 'airline_arkia' === $arkia['resolved_axes']['airline_inventory_id'], 'Arkia must resolve on the inventory axis' );
israel_benefit_assert( null === $arkia['resolved_axes']['program_id'], 'Arkia inventory must not create an Arkia loyalty program' );
israel_benefit_assert( 'choose_benefit_program_optional' === $arkia['next_action']['code'], 'inventory-only selection must make loyalty optional' );

israel_benefit_scenario();
$israir = $registry->plan(
	israel_benefit_request( array( 'airline_inventory_id' => 'airline_israir' ) ),
	'2026-07-19T12:00:00Z'
);
israel_benefit_assert( ! is_wp_error( $israir ), 'Israir inventory selection must be usable without inventing a loyalty program' );
israel_benefit_assert( 'airline_israir' === $israir['resolved_axes']['airline_inventory_id'], 'Israir must resolve only on the airline inventory axis' );
israel_benefit_assert( null === $israir['resolved_axes']['program_id'], 'Israir inventory must not create an Israir loyalty program' );
israel_benefit_assert( 'choose_benefit_program_optional' === $israir['next_action']['code'], 'Israir airline selection must leave a benefit program optional' );

israel_benefit_scenario();
israel_benefit_error(
	$registry->plan( israel_benefit_request( array( 'program_id' => 'program_israir' ) ), '2026-07-19T12:00:00Z' ),
	'tra_vel_israel_benefit_program_unknown',
	'an Israir airline selection must never imply a fabricated Israir program'
);

israel_benefit_scenario();
israel_benefit_error(
	$registry->plan( israel_benefit_request( array( 'program_id' => 'program_arkia' ) ), '2026-07-19T12:00:00Z' ),
	'tra_vel_israel_benefit_program_unknown',
	'an Arkia airline selection must never imply a fabricated Arkia program'
);

israel_benefit_scenario();
$generic_visa = israel_benefit_error(
	$registry->plan(
		israel_benefit_request(
			array(
				'payment_network_id' => 'network_visa',
				'eligibility_claim'  => 'generic_visa_eligible',
			)
		),
		'2026-07-19T12:00:00Z'
	),
	'tra_vel_israel_benefit_generic_visa_eligibility_forbidden',
	'a generic Visa logo must not prove benefit eligibility'
);
israel_benefit_assert( 'choose_exact_issuer_card_campaign' === $generic_visa->get_error_data()['next_action_code'], 'generic Visa rejection must return the smallest corrective action' );

israel_benefit_scenario();
$visa_discovery = $registry->plan(
	israel_benefit_request( array( 'payment_network_id' => 'network_visa' ) ),
	'2026-07-19T12:00:00Z'
);
israel_benefit_assert( ! is_wp_error( $visa_discovery ), 'Visa may remain available as a network discovery identity' );
israel_benefit_assert( 'unknown_requires_action' === $visa_discovery['decision_state'], 'network-only discovery must remain unknown for eligibility' );
israel_benefit_assert( 'choose_exact_issuer_card_campaign' === $visa_discovery['next_action']['code'], 'network-only discovery must request exact issuer/card/campaign' );

israel_benefit_scenario();
$generic_fly_card = israel_benefit_error(
	$registry->plan(
		israel_benefit_request( array( 'eligibility_claim' => 'generic_fly_card_eligible' ) ),
		'2026-07-19T12:00:00Z'
	),
	'tra_vel_israel_benefit_generic_fly_card_eligibility_forbidden',
	'a generic FLY CARD family claim must be rejected'
);
israel_benefit_assert( 'choose_exact_fly_card_product' === $generic_fly_card->get_error_data()['next_action_code'], 'generic FLY CARD rejection must request the exact current product' );

israel_benefit_scenario();
$flyall = $registry->plan(
	israel_benefit_request(
		array(
			'airline_inventory_id' => 'airline_arkia',
			'program_id'            => 'program_cal_flyall',
			'credential_product_id' => 'credential_cal_flyall_mastercard',
			'payment_network_id'    => 'network_mastercard',
			'redemption_portal_id'  => 'portal_flyall',
			'campaign_id'           => 'campaign_cal_flyall_catalog_2026',
			'campaign_version'      => 1,
			'eligibility_claim'     => 'exact_product_customer_asserted',
		)
	),
	'2026-07-19T12:00:00Z'
);
israel_benefit_assert( ! is_wp_error( $flyall ), 'an internally consistent FlyAll planning selection must resolve' );
israel_benefit_assert( 'likely_customer_asserted' === $flyall['decision_state'], 'an exact customer assertion must not become provider-verified eligibility' );
israel_benefit_assert( 'program_cal_flyall' === $flyall['resolved_axes']['program_id'], 'Arkia inventory through FlyAll must remain FlyAll value, never Arkia points' );
israel_benefit_assert( 'verify_eligibility_with_provider' === $flyall['next_action']['code'], 'complete source-catalogued axes must still require provider verification' );
foreach ( $flyall['commercial_truth'] as $truth ) {
	israel_benefit_assert( false === $truth, 'a complete planning selection must never turn any commercial truth flag live' );
}

israel_benefit_scenario();
$flyall_israir = $registry->plan(
	israel_benefit_request(
		array(
			'airline_inventory_id' => 'airline_israir',
			'program_id'            => 'program_cal_flyall',
			'credential_product_id' => 'credential_cal_flyall_mastercard',
			'payment_network_id'    => 'network_mastercard',
			'redemption_portal_id'  => 'portal_flyall',
			'campaign_id'           => 'campaign_cal_flyall_catalog_2026',
			'campaign_version'      => 1,
			'eligibility_claim'     => 'exact_product_customer_asserted',
		)
	),
	'2026-07-19T12:00:00Z'
);
israel_benefit_assert( ! is_wp_error( $flyall_israir ), 'source-catalogued FlyAll portal scope must accept Israir inventory for planning' );
israel_benefit_assert( 'airline_israir' === $flyall_israir['resolved_axes']['airline_inventory_id'], 'FlyAll planning must preserve Israir as the airline inventory identity' );
israel_benefit_assert( 'program_cal_flyall' === $flyall_israir['resolved_axes']['program_id'], 'Israir inventory through FlyAll must remain FlyAll value, never Israir points' );
israel_benefit_assert( 'likely_customer_asserted' === $flyall_israir['decision_state'], 'FlyAll and Israir catalogue linkage must not prove customer eligibility' );
foreach ( $flyall_israir['commercial_truth'] as $truth ) {
	israel_benefit_assert( false === $truth, 'FlyAll and Israir planning must not claim live availability, pricing, discount, redemption, or checkout' );
}

israel_benefit_scenario();
israel_benefit_error(
	$registry->plan(
		israel_benefit_request(
			array(
				'program_id'            => 'program_cal_flyall',
				'credential_product_id' => 'credential_isracard_fly_card_mastercard',
			)
		),
		'2026-07-19T12:00:00Z'
	),
	'tra_vel_israel_benefit_credential_program_conflict',
	'Isracard FLY CARD must not be evaluated as FlyAll value'
);

israel_benefit_scenario();
israel_benefit_error(
	$registry->plan(
		israel_benefit_request(
			array(
				'program_id'           => 'program_cal_flyall',
				'redemption_portal_id' => 'portal_elal_matmid',
			)
		),
		'2026-07-19T12:00:00Z'
	),
	'tra_vel_israel_benefit_program_portal_conflict',
	'FlyAll and Matmid portals must not be interchangeable'
);

israel_benefit_scenario();
israel_benefit_error(
	$registry->plan(
		israel_benefit_request(
			array(
				'credential_product_id' => 'credential_isracard_fly_card_premium_mastercard',
				'payment_network_id'    => 'network_american_express',
			)
		),
		'2026-07-19T12:00:00Z'
	),
	'tra_vel_israel_benefit_credential_network_conflict',
	'an exact Mastercard product must reject an American Express rail'
);

israel_benefit_scenario();
$flyall_unknown_card = $registry->plan(
	israel_benefit_request(
		array(
			'program_id'           => 'program_cal_flyall',
			'redemption_portal_id' => 'portal_flyall',
		)
	),
	'2026-07-19T12:00:00Z'
);
israel_benefit_assert( ! is_wp_error( $flyall_unknown_card ), 'unknown FlyAll card variant must remain a recoverable planning state' );
israel_benefit_assert( 'choose_exact_flyall_card' === $flyall_unknown_card['next_action']['code'], 'unknown FlyAll eligibility must ask only for the exact card variant next' );

israel_benefit_scenario();
$transition = $registry->plan(
	israel_benefit_request(
		array(
			'program_id'       => 'program_elal_matmid',
			'campaign_id'      => 'campaign_cal_flycard_matmid_transition_2026',
			'campaign_version' => 1,
		)
	),
	'2026-07-19T12:00:00Z'
);
israel_benefit_assert( ! is_wp_error( $transition ), 'current Cal FLY CARD transition may be catalogued without pretending an exact legacy card is known' );
israel_benefit_assert( 'choose_exact_cal_fly_card_variant' === $transition['next_action']['code'], 'transition planning must request the exact legacy card variant' );
israel_benefit_assert( false === $fixture['migrations'][0]['commercial_truth']['automatic_migration'], 'migration must never be described as automatic' );
israel_benefit_assert( '2026-12-31T23:59:59Z' === $fixture['migrations'][0]['transition_window']['to_utc'], 'the source-backed 2026 transition end must be preserved' );
israel_benefit_assert( '2027-01-01T00:00:00Z' === $fixture['migrations'][0]['accrual_cutover_at_utc'], 'the migration cutover boundary must remain explicit' );

israel_benefit_scenario();
israel_benefit_error(
	$registry->plan(
		israel_benefit_request(
			array(
				'campaign_id'      => 'campaign_cal_flyall_catalog_2026',
				'campaign_version' => 99,
			)
		),
		'2026-07-19T12:00:00Z'
	),
	'tra_vel_israel_benefit_campaign_revision_unknown',
	'an unknown campaign revision must fail instead of falling forward'
);

israel_benefit_scenario();
israel_benefit_error(
	$registry->plan( israel_benefit_request( array( 'campaign_id' => 'campaign_cal_flyall_catalog_2026' ) ), '2026-07-19T12:00:00Z' ),
	'tra_vel_israel_benefit_campaign_revision_invalid',
	'a campaign ID without its exact immutable version must fail'
);

israel_benefit_scenario();
$extra_axis = israel_benefit_request();
$extra_axis['card_number'] = '4111111111111111';
israel_benefit_error(
	$registry->plan( $extra_axis, '2026-07-19T12:00:00Z' ),
	'tra_vel_israel_benefit_plan_shape_invalid',
	'closed planning input must reject credential or PII-shaped extra fields'
);

israel_benefit_scenario();
israel_benefit_error(
	$registry->plan( israel_benefit_request( array( 'eligibility_claim' => 'exact_product_customer_asserted' ) ), '2026-07-19T12:00:00Z' ),
	'tra_vel_israel_benefit_exact_product_claim_missing_product',
	'an exact-product assertion without an exact product must fail'
);

israel_benefit_scenario();
israel_benefit_error(
	$registry->readiness( '2026-07-20T08:00:01Z' ),
	'tra_vel_israel_benefit_catalog_stale',
	'catalogue use after its freshness deadline must fail closed'
);
israel_benefit_error(
	$registry->readiness( '2026-07-19T07:59:59Z' ),
	'tra_vel_israel_benefit_catalog_not_yet_observed',
	'catalogue use before observation must fail closed'
);

israel_benefit_scenario();
$conflicting = $fixture;
$duplicate   = $conflicting['campaign_versions'][0];
$duplicate['ruleset_digest'] = str_repeat( 'f', 64 );
$conflicting['campaign_versions'][] = $duplicate;
$path = israel_benefit_temp_fixture( $conflicting, 'conflicting-revision' );
$conflicting_registry = new Tra_Vel_Israel_Benefit_Catalog_Registry( $path );
israel_benefit_error(
	$conflicting_registry->load(),
	'tra_vel_israel_benefit_campaign_revision_conflict',
	'two records for the same campaign revision must fail even when only their digests differ'
);
unlink( $path );

israel_benefit_scenario();
$visa_card = $fixture;
$visa_card['credential_products'][0]['network_id'] = 'network_visa';
$path = israel_benefit_temp_fixture( $visa_card, 'generic-visa-card' );
$visa_card_registry = new Tra_Vel_Israel_Benefit_Catalog_Registry( $path );
israel_benefit_error(
	$visa_card_registry->load(),
	'tra_vel_israel_benefit_credential_network_invalid',
	'an exact card must not bind the generic Visa-network identity'
);
unlink( $path );

israel_benefit_scenario();
$automatic = $fixture;
$automatic['migrations'][0]['commercial_truth']['automatic_migration'] = true;
$path = israel_benefit_temp_fixture( $automatic, 'automatic-migration' );
$automatic_registry = new Tra_Vel_Israel_Benefit_Catalog_Registry( $path );
israel_benefit_error(
	$automatic_registry->load(),
	'tra_vel_israel_benefit_migration_record_invalid',
	'a provider transition must not claim automatic customer migration'
);
unlink( $path );

israel_benefit_scenario();
$non_official = $fixture;
$non_official['programs'][0]['source']['authority'] = 'customer_assertion';
$non_official['programs'][0]['source']['official_source_url'] = null;
$path = israel_benefit_temp_fixture( $non_official, 'non-official-source' );
$non_official_registry = new Tra_Vel_Israel_Benefit_Catalog_Registry( $path );
israel_benefit_error(
	$non_official_registry->load(),
	'tra_vel_israel_benefit_source_invalid',
	'a current Israeli identity catalogue must reject customer assertion as source evidence'
);
unlink( $path );

israel_benefit_scenario();
$live = $fixture;
$live['commercial_truth']['live_eligibility'] = true;
$path = israel_benefit_temp_fixture( $live, 'live-truth' );
$live_registry = new Tra_Vel_Israel_Benefit_Catalog_Registry( $path );
israel_benefit_error(
	$live_registry->load(),
	'tra_vel_israel_benefit_commercial_truth_invalid',
	'a source catalogue must never claim live eligibility'
);
unlink( $path );

israel_benefit_scenario();
$unknown_field = $fixture;
$unknown_field['live_supplier_connection'] = false;
$path = israel_benefit_temp_fixture( $unknown_field, 'unknown-field' );
$unknown_registry = new Tra_Vel_Israel_Benefit_Catalog_Registry( $path );
israel_benefit_error(
	$unknown_registry->load(),
	'tra_vel_israel_benefit_fixture_shape_invalid',
	'the catalogue must reject unknown top-level fields'
);
unlink( $path );

israel_benefit_scenario();
$keys = array();
israel_benefit_keys( $fixture, $keys );
foreach ( array( 'password', 'pan', 'cvv', 'otp', 'access_token', 'refresh_token', 'member_number', 'customer_id', 'price_minor', 'discount_minor', 'rate_bps', 'balance_amount' ) as $forbidden_key ) {
	israel_benefit_assert( ! isset( $keys[ $forbidden_key ] ), 'catalogue must not contain credentials, PII, live balances, prices, discounts, or rates: ' . $forbidden_key );
}
foreach ( $fixture['airline_inventory'] as $airline ) {
	israel_benefit_assert( false === $airline['commercial_truth']['loyalty_eligibility_implied'], 'airline inventory must never imply loyalty eligibility' );
}
foreach ( $fixture['portal_inventory_links'] as $link ) {
	israel_benefit_assert( false === $link['commercial_truth']['live_availability'] && false === $link['commercial_truth']['airline_loyalty_program_implied'], 'portal inventory scope must remain non-live and separate from loyalty' );
}

echo sprintf(
	"Israel benefit catalog runtime passed: %d assertions across %d adversarial scenarios; %d programs, %d exact card products, %d campaign revisions.\n",
	$assertions,
	$scenarios,
	count( $fixture['programs'] ),
	count( $fixture['credential_products'] ),
	count( $fixture['campaign_versions'] )
);
