<?php
/**
 * Focused runtime checks for the non-transactional benefit engine contract.
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

$assertions = 0;
function benefit_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "Benefit engine runtime failed: {$message}\n" );
		exit( 1 );
	}
}

function benefit_digest( $character ) {
	return str_repeat( $character, 64 );
}

function benefit_catalog_source() {
	return array(
		'authority'             => 'official_rules',
		'official_source_url'    => 'https://provider.example/official-rules',
		'source_content_digest'  => benefit_digest( 'a' ),
		'observed_at_utc'        => '2026-07-19T08:00:00Z',
		'fresh_until_utc'        => '2026-07-20T08:00:00Z',
		'locale'                 => 'he-IL',
		'review_state'           => 'reviewed',
	);
}

function benefit_program_fixture() {
	return array(
		'contract_version'     => '1.0.0',
		'program_id'          => 'program_demo_points',
		'owner_id'            => 'benefit_owner_a',
		'display_name'        => 'Demo points program',
		'unit_code'           => 'demo_points',
		'unit_type'           => 'points',
		'supported_operations' => array( 'quote', 'read_balance', 'redeem' ),
		'status'              => 'catalogued',
		'integration_state'   => 'source_catalogued',
		'source'              => benefit_catalog_source(),
		'commercial_truth'    => array( 'live_connection' => false, 'live_redemption' => false ),
	);
}

function benefit_campaign_fixture() {
	return array(
		'contract_version'       => '1.0.0',
		'campaign_id'            => 'campaign_demo_summer',
		'version'                => 2,
		'provider_id'            => 'benefit_provider_a',
		'program_ids'            => array( 'program_demo_points' ),
		'credential_product_ids' => array( 'credential_demo_card' ),
		'benefit_types'          => array( 'cash_plus_points', 'points_redemption' ),
		'windows'                => array(
			'effective'  => array( 'from_utc' => '2026-07-01T00:00:00Z', 'to_utc' => '2026-09-01T00:00:00Z' ),
			'enrollment' => array( 'from_utc' => null, 'to_utc' => null ),
			'booking'    => array( 'from_utc' => '2026-07-01T00:00:00Z', 'to_utc' => '2026-08-01T00:00:00Z' ),
			'travel'     => array( 'from_utc' => '2026-07-01T00:00:00Z', 'to_utc' => '2026-12-31T23:59:59Z' ),
		),
		'inventory_cap_state' => 'unknown',
		'ruleset_digest'      => benefit_digest( 'b' ),
		'review_state'        => 'reviewed',
		'status'              => 'active',
		'supersedes_version'  => 1,
		'integration_state'   => 'source_catalogued',
		'source'              => benefit_catalog_source(),
		'commercial_truth'    => array( 'provider_quote_available' => false, 'checkout_application_available' => false ),
	);
}

function benefit_connection_fixture() {
	return array(
		'contract_version'       => '1.0.0',
		'connection_id'          => 'connection_demo_member',
		'user_reference_digest'  => benefit_digest( 'c' ),
		'program_id'             => 'program_demo_points',
		'mode'                   => 'manual_balance',
		'state'                  => 'connected_read_only',
		'subject_reference_digest' => null,
		'assurance'              => 'customer_asserted',
		'consent'                => array(
			'consent_version'         => '1.0.0',
			'consent_reference_digest' => benefit_digest( 'd' ),
			'purpose'                 => 'balance_comparison',
			'scopes'                  => array( 'read_balance', 'disconnect' ),
			'issued_at_utc'           => '2026-07-19T08:00:00Z',
			'expires_at_utc'          => '2026-08-19T08:00:00Z',
			'retention_until_utc'     => '2026-09-19T08:00:00Z',
			'refresh_permission'      => false,
			'redemption_permission'   => false,
			'revocation_route_id'     => 'revocation_demo_member',
		),
		'freshness'              => array( 'observed_at_utc' => '2026-07-19T08:00:00Z', 'fresh_until_utc' => '2026-07-19T08:15:00Z' ),
		'commercial_truth'       => array( 'provider_connection_live' => false, 'redemption_enabled' => false ),
	);
}

function benefit_quote_fixture() {
	return array(
		'contract_version'              => '1.0.0',
		'benefit_quote_id'              => 'benefit_quote_demo_001',
		'base_offer_snapshot_id'        => 'offer_snapshot_demo_001',
		'base_offer_digest'             => benefit_digest( 'e' ),
		'program_id'                    => 'program_demo_points',
		'campaign_id'                   => 'campaign_demo_summer',
		'campaign_version'              => 2,
		'connection_id'                 => 'connection_demo_member',
		'decision_state'                => 'eligible_verified',
		'reason_codes'                  => array( 'requirements_verified' ),
		'next_action_code'              => null,
		'verified_input_digest'         => benefit_digest( 'f' ),
		'cash_effect'                   => array(
			'currency'                 => 'ILS',
			'immediate_discount_minor' => 1200,
			'future_reward_minor'      => 300,
			'fees_minor'               => 0,
			'payable_now_minor'        => 18800,
			'payable_later_minor'      => 0,
		),
		'points_effects'                => array( array( 'unit_code' => 'demo_points', 'debit_integer' => 5000, 'earn_later_integer' => 0 ) ),
		'quoted_at_utc'                 => '2026-07-19T09:00:00Z',
		'expires_at_utc'                => '2026-07-19T09:05:00Z',
		'provider_quote_reference_digest' => null,
		'source'                        => array(
			'campaign_source_digest'        => benefit_digest( 'a' ),
			'base_offer_revalidated_at_utc' => '2026-07-19T08:59:00Z',
			'observed_at_utc'               => '2026-07-19T08:00:00Z',
			'fresh_until_utc'               => '2026-07-20T08:00:00Z',
			'assurance'                     => 'official_terms_verified',
		),
		'commercial_truth'              => array( 'planning_only' => true, 'may_change_payable_total' => false, 'redemption_available' => false ),
	);
}

benefit_assert( 'eligible_verified' === Tra_Vel_Benefit_Taxonomy::decision_state( 'eligible_verified' ), 'canonical decision state must pass exactly' );
benefit_assert( '' === Tra_Vel_Benefit_Taxonomy::decision_state( 'Eligible_Verified' ), 'decision state must not be normalized silently' );
benefit_assert( 'program_demo_points' === Tra_Vel_Benefit_Taxonomy::identifier( 'program_demo_points', 'program' ), 'canonical program ID must validate' );
benefit_assert( '' === Tra_Vel_Benefit_Taxonomy::identifier( ' Program_demo_points ', 'program' ), 'whitespace and case changes must fail exact IDs' );
benefit_assert( Tra_Vel_Benefit_Taxonomy::nonnegative_integer( 0 ), 'zero is a valid integer amount' );
benefit_assert( ! Tra_Vel_Benefit_Taxonomy::nonnegative_integer( 10.5 ), 'floating-point points and minor units must fail' );
benefit_assert( '2026-07-19T08:00:00Z' === Tra_Vel_Benefit_Taxonomy::utc_datetime( '2026-07-19T11:00:00+03:00' ), 'RFC3339 offsets must canonicalize to UTC' );

$program = benefit_program_fixture();
$program_result = Tra_Vel_Benefit_Policy::benefit_program( $program );
benefit_assert( ! is_wp_error( $program_result ) && false === $program_result['commercial_truth']['live_redemption'], 'source-backed program must remain explicitly non-transactional' );
$program_extra = $program;
$program_extra['current_rate'] = 7;
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::benefit_program( $program_extra ) ), 'unknown or unstable program promises must fail the closed record' );
$program_live = $program;
$program_live['commercial_truth']['live_connection'] = true;
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::benefit_program( $program_live ) ), 'foundation program cannot claim a live connection' );
$announced = $program;
$announced['status'] = 'announced_not_operational';
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::benefit_program( $announced ) ), 'announced status requires matching integration state' );
$announced['integration_state'] = 'announced_not_operational';
benefit_assert( ! is_wp_error( Tra_Vel_Benefit_Policy::benefit_program( $announced ) ), 'explicit announced-not-operational identity must validate without claiming service' );

$credential = array(
	'contract_version'       => '1.0.0',
	'credential_product_id'  => 'credential_demo_card',
	'issuer_id'              => 'issuer_demo',
	'network_id'             => 'network_demo',
	'product_code'           => 'demo_standard',
	'display_name'           => 'Demo standard card',
	'tier'                   => 'standard',
	'residency_scopes'       => array( 'IL' ),
	'effective_window'       => array( 'from_utc' => '2026-01-01T00:00:00Z', 'to_utc' => null ),
	'status'                 => 'catalogued',
	'integration_state'      => 'source_catalogued',
	'source'                 => benefit_catalog_source(),
	'commercial_truth'       => array( 'live_eligibility_verification' => false ),
);
benefit_assert( ! is_wp_error( Tra_Vel_Benefit_Policy::credential_product( $credential ) ), 'exact issuer/network product must validate without member card data' );
$credential_bad_window = $credential;
$credential_bad_window['effective_window'] = array( 'from_utc' => '2026-09-01T00:00:00Z', 'to_utc' => '2026-08-01T00:00:00Z' );
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::credential_product( $credential_bad_window ) ), 'reversed credential-product window must fail' );

$campaign = benefit_campaign_fixture();
benefit_assert( ! is_wp_error( Tra_Vel_Benefit_Policy::campaign_version( $campaign ) ), 'reviewed immutable campaign version must validate' );
benefit_assert( 'open' === Tra_Vel_Benefit_Policy::campaign_window_state( $campaign, 'booking', '2026-07-19T09:00:00Z' ), 'booking window must be open at its evaluated instant' );
benefit_assert( 'after' === Tra_Vel_Benefit_Policy::campaign_window_state( $campaign, 'booking', '2026-08-02T09:00:00Z' ), 'booking window must close deterministically' );
benefit_assert( 'current' === Tra_Vel_Benefit_Policy::source_freshness_state( $campaign['source'], '2026-07-20T07:59:59Z' ), 'source must be current before its freshness deadline' );
benefit_assert( 'stale' === Tra_Vel_Benefit_Policy::source_freshness_state( $campaign['source'], '2026-07-20T08:00:01Z' ), 'source must become stale after its freshness deadline' );
$campaign_bad_lineage = $campaign;
$campaign_bad_lineage['supersedes_version'] = 2;
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::campaign_version( $campaign_bad_lineage ) ), 'campaign cannot supersede itself' );
$campaign_unreviewed = $campaign;
$campaign_unreviewed['review_state'] = 'draft';
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::campaign_version( $campaign_unreviewed ) ), 'active campaign cannot bypass source and rules review' );

$connection = benefit_connection_fixture();
benefit_assert( ! is_wp_error( Tra_Vel_Benefit_Policy::member_connection( $connection ) ), 'manual read-only connection must remain planning-only' );
$read_is_not_redeem = $connection;
$read_is_not_redeem['consent']['redemption_permission'] = true;
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::member_connection( $read_is_not_redeem ) ), 'read-balance consent must never imply redemption permission' );
$manual_redeem = $connection;
$manual_redeem['state'] = 'redemption_authorized';
$manual_redeem['consent']['purpose'] = 'benefit_redemption';
$manual_redeem['consent']['scopes'][] = 'redeem';
$manual_redeem['consent']['redemption_permission'] = true;
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::member_connection( $manual_redeem ) ), 'manual balance cannot authorize a debit' );
$provider_connection = $connection;
$provider_connection['mode'] = 'provider_oauth';
$provider_connection['subject_reference_digest'] = benefit_digest( 'e' );
$provider_connection['assurance'] = 'provider_verified';
$provider_connection['state'] = 'connected_current';
$provider_connection['consent']['scopes'] = array( 'read_balance', 'refresh_balance', 'disconnect' );
$provider_connection['consent']['refresh_permission'] = true;
benefit_assert( ! is_wp_error( Tra_Vel_Benefit_Policy::member_connection( $provider_connection ) ), 'provider connection envelope can be modeled while live truth remains false' );
$raw_secret = $provider_connection;
$raw_secret['password'] = 'forbidden';
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::member_connection( $raw_secret ) ), 'unknown secret-bearing field must fail closed' );

$balance = array(
	'contract_version'   => '1.0.0',
	'snapshot_id'        => 'balance_demo_001',
	'connection_id'      => 'connection_demo_member',
	'program_id'         => 'program_demo_points',
	'amounts'            => array( array( 'unit_code' => 'demo_points', 'unit_type' => 'points', 'amount_integer' => 8000 ) ),
	'expiry_lots'        => array( array( 'unit_code' => 'demo_points', 'amount_integer' => 2500, 'expires_at_utc' => '2026-12-31T23:59:59Z' ) ),
	'assurance'          => 'customer_asserted',
	'declared_freshness' => 'self_reported',
	'source'             => array(
		'authority'               => 'customer_assertion',
		'source_reference_digest' => benefit_digest( 'f' ),
		'observed_at_utc'          => '2026-07-19T08:00:00Z',
		'fresh_until_utc'          => '2026-07-19T08:15:00Z',
	),
	'immutable_digest'    => benefit_digest( '1' ),
	'planning_only'       => true,
);
benefit_assert( ! is_wp_error( Tra_Vel_Benefit_Policy::balance_snapshot( $balance, '2026-07-19T08:10:00Z' ) ), 'self-reported balance can support planning with explicit provenance' );
$balance_float = $balance;
$balance_float['amounts'][0]['amount_integer'] = 8000.5;
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::balance_snapshot( $balance_float ) ), 'floating-point balance must fail' );
$balance_overallocated = $balance;
$balance_overallocated['expiry_lots'][0]['amount_integer'] = 9000;
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::balance_snapshot( $balance_overallocated ) ), 'expiry lots cannot exceed observed balance' );
$provider_balance = $balance;
$provider_balance['assurance'] = 'provider_verified';
$provider_balance['declared_freshness'] = 'current';
$provider_balance['source']['authority'] = 'provider_authorized_api';
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::balance_snapshot( $provider_balance, '2026-07-19T08:16:00Z' ) ), 'stale provider snapshot cannot retain current freshness label' );

$quote = benefit_quote_fixture();
benefit_assert( ! is_wp_error( Tra_Vel_Benefit_Policy::benefit_quote( $quote ) ), 'verified planning quote must validate without changing checkout' );
$quote_float = $quote;
$quote_float['cash_effect']['fees_minor'] = 1.5;
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::benefit_quote( $quote_float ) ), 'floating-point quote values must fail' );
$quote_stale = $quote;
$quote_stale['source']['fresh_until_utc'] = '2026-07-19T08:30:00Z';
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::benefit_quote( $quote_stale ) ), 'verified eligibility cannot rely on source evidence stale at quote time' );
$quote_unknown = $quote;
$quote_unknown['decision_state'] = 'unknown_requires_action';
$quote_unknown['next_action_code'] = null;
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::benefit_quote( $quote_unknown ) ), 'unknown decision must expose a smallest next action' );
$quote_checkout = $quote;
$quote_checkout['commercial_truth']['may_change_payable_total'] = true;
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::benefit_quote( $quote_checkout ) ), 'planning quote cannot silently alter checkout' );

$redemption = array(
	'contract_version'                    => '1.0.0',
	'redemption_operation_id'             => 'redemption_demo_001',
	'idempotency_reference_digest'        => benefit_digest( '2' ),
	'benefit_quote_id'                    => 'benefit_quote_demo_001',
	'connection_id'                       => 'connection_demo_member',
	'program_id'                          => 'program_demo_points',
	'campaign_id'                         => 'campaign_demo_summer',
	'campaign_version'                    => 2,
	'state'                               => 'succeeded',
	'reconciliation_state'                => 'matched',
	'authorization_reference_digest'      => benefit_digest( '3' ),
	'provider_operation_reference_digest' => null,
	'points_debits'                       => array( array( 'unit_code' => 'demo_points', 'amount_integer' => 5000 ) ),
	'cash_effect'                         => array( 'currency' => 'ILS', 'discount_minor' => 1200, 'fees_minor' => 0 ),
	'requested_at_utc'                    => '2026-07-19T09:00:00Z',
	'updated_at_utc'                      => '2026-07-19T09:00:03Z',
	'commercial_truth'                    => array( 'simulated' => true, 'provider_submission' => false, 'real_debit' => false ),
);
benefit_assert( ! is_wp_error( Tra_Vel_Benefit_Policy::redemption_operation( $redemption ) ), 'matched sandbox redemption can exercise the lifecycle without a live debit' );
$redemption_uncertain = $redemption;
$redemption_uncertain['state'] = 'operation_uncertain';
$redemption_uncertain['reconciliation_state'] = 'not_required';
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::redemption_operation( $redemption_uncertain ) ), 'uncertain operation must lock into reconciliation' );
$redemption_provider_ref = $redemption;
$redemption_provider_ref['provider_operation_reference_digest'] = benefit_digest( '4' );
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::redemption_operation( $redemption_provider_ref ) ), 'non-transactional foundation must reject a provider-submission claim' );
$redemption_live = $redemption;
$redemption_live['commercial_truth']['real_debit'] = true;
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::redemption_operation( $redemption_live ) ), 'simulated operation cannot claim a real debit' );
$redemption_float = $redemption;
$redemption_float['points_debits'][0]['amount_integer'] = 5000.25;
benefit_assert( is_wp_error( Tra_Vel_Benefit_Policy::redemption_operation( $redemption_float ) ), 'redemption points must be integers' );

echo "Benefit engine runtime passed ({$assertions} assertions).\n";
