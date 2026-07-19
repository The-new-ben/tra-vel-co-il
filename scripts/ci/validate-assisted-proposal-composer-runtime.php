<?php
/**
 * Deterministic runtime coverage for server-owned proposal composition.
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) );
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message = '', $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function esc_url_raw( $value, $protocols = null ) {
	$value = filter_var( (string) $value, FILTER_SANITIZE_URL );
	return 0 === strpos( $value, 'https://' ) ? $value : '';
}
function wp_generate_uuid4() { return 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'; }
function wp_salt( $scheme = 'auth' ) { return 'assisted-proposal-runtime-salt-' . $scheme; }

require_once dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/includes/class-tra-vel-assisted-proposal-policy.php';
require_once dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/includes/class-tra-vel-assisted-proposal-composer.php';

$assertions = 0;
function tra_vel_composer_expect( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, 'Tra-Vel assisted proposal composer runtime failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}
function tra_vel_composer_expect_error( $value, $code, $message ) {
	tra_vel_composer_expect( is_wp_error( $value ), $message . ' Expected WP_Error.' );
	if ( is_wp_error( $value ) ) {
		tra_vel_composer_expect( $code === $value->get_error_code(), $message . ' Unexpected ' . $value->get_error_code() . '.' );
	}
}
function tra_vel_composer_has_gap( $proposal, $code ) {
	foreach ( (array) ( $proposal['unresolved_items'] ?? array() ) as $item ) {
		if ( $code === ( $item['code'] ?? '' ) ) { return true; }
	}
	return false;
}
function tra_vel_composer_compose( $input, $case, $now, $identity, $operator_user_id = 7 ) {
	$unsigned = $input;
	unset( $unsigned['evidence_attestation_token'] );
	$attestation = Tra_Vel_Assisted_Proposal_Composer::issue_evidence_attestation( $unsigned, $case, $operator_user_id, $now );
	if ( is_wp_error( $attestation ) ) { return $attestation; }
	$input['evidence_attestation_token'] = $attestation['attestation_token'];
	$verified = Tra_Vel_Assisted_Proposal_Composer::verify_evidence_attestation( $input, $case, $operator_user_id, $now );
	if ( is_wp_error( $verified ) ) { return $verified; }
	$identity['evidence_checked_at'] = (int) $verified['checked_at'];
	return Tra_Vel_Assisted_Proposal_Composer::compose( $input, $case, $now, $identity );
}

$now = 1784450000;
$case = array(
	'id'                    => 17,
	'case_uuid'             => '123e4567-e89b-42d3-a456-426614174002',
	'status'                => 'ready_for_assistance',
	'case_version'          => 4,
	'current_revision'      => 2,
	'latest_request_digest' => str_repeat( 'b', 64 ),
	'assigned_user_id'      => 7,
	'retention_until'       => gmdate( 'Y-m-d H:i:s', $now + DAY_IN_SECONDS ),
);
$identity = array(
	'proposal_id' => '123e4567-e89b-42d3-a456-426614174001',
	'reference'   => 'TVP-AB12CD34',
	'source_ids' => array( '123e4567-e89b-42d3-a456-426614174000', '223e4567-e89b-42d3-a456-426614174000' ),
);
$source = array(
	'provider_code'      => 'fixture-provider',
	'source_type'        => 'connected_api',
	'relationship'       => 'operator_attested',
	'public_label'       => 'Current operator-checked provider response',
	'supplier_name'      => 'Fixture supplier',
	'seller_name'        => 'Fixture seller',
	'source_reference'   => 'SRC:FIXTURE-1',
	'source_url'         => '',
	'freshness_minutes'  => 60,
	'revalidated_now'    => true,
);
$component = array(
	'component_key' => 'outbound-flight',
	'category'      => 'flights',
	'title'         => 'Sourced flight option',
	'description'   => 'A provider-backed option for personal review.',
	'price'         => array(
		'priced'                => true,
		'total_for_party_minor' => 123400,
		'currency'              => 'ILS',
		'basis'                 => 'trip_total',
		'taxes'                 => 'included',
		'fees'                  => 'included',
	),
	'conditions' => array(
		'cancellation'          => 'Revalidate the cited cancellation policy before purchase.',
		'changes'               => 'Changes require a new source check.',
		'baggage_or_inclusions' => 'The cited source controls the exact inclusions.',
	),
	'source_indexes' => array( 0 ),
);
$composition = array(
	'position'         => 'best_value',
	'title'            => 'Personal sourced proposal',
	'summary'          => 'A non-binding proposal for traveler review.',
	'why_it_fits'      => array( 'It addresses the current route and request.' ),
	'trade_offs'       => array( 'Every commercial fact still requires final revalidation.' ),
	'route'            => array( 'origin' => 'Tel Aviv', 'destinations' => array( 'Athens' ), 'legs' => array() ),
	'itinerary'        => array( array( 'day' => 1, 'place' => 'Athens', 'title' => 'Arrival', 'component_keys' => array( 'outbound-flight' ) ) ),
	'components'       => array( $component ),
	'sources'          => array( $source ),
	'unresolved_items' => array(),
);

$proposal = tra_vel_composer_compose( $composition, $case, $now, $identity );
tra_vel_composer_expect( is_array( $proposal ), 'A minimum real-source composition must produce a proposal.' );
tra_vel_composer_expect( $identity['proposal_id'] === $proposal['proposal_id'] && $case['case_uuid'] === $proposal['case_id'], 'Identity and case binding must be server-owned.' );
tra_vel_composer_expect( 2 === $proposal['addresses']['case_revision'] && $case['latest_request_digest'] === $proposal['addresses']['request_digest'], 'The current immutable request binding must be derived from the case.' );
tra_vel_composer_expect( 123400 === $proposal['ledger']['priced_total_minor'] && true === $proposal['ledger']['complete_pricing'], 'The server must compute an exact complete ledger.' );
tra_vel_composer_expect( tra_vel_composer_has_gap( $proposal, 'availability_revalidation' ), 'Availability revalidation must always be added.' );
tra_vel_composer_expect( Tra_Vel_Assisted_Proposal_Policy::FINAL_QUOTE_DISCLOSURE === $proposal['disclosure']['message'], 'The final-quote boundary must be immutable.' );
tra_vel_composer_expect( str_repeat( 'a', 64 ) !== $proposal['sources'][0]['evidence_digest'] && 64 === strlen( $proposal['sources'][0]['evidence_digest'] ), 'Evidence digests must be derived by the server.' );
tra_vel_composer_expect( true === Tra_Vel_Assisted_Proposal_Policy::validate_publication( $proposal, $proposal['sources'], array( 'case_active' => true, 'case_revision' => 2, 'request_digest' => str_repeat( 'b', 64 ) ), $now ), 'Composed output must pass the authoritative publication policy.' );
$generated_reference_identity = $identity;
unset( $generated_reference_identity['reference'] );
$generated_reference = tra_vel_composer_compose( $composition, $case, $now, $generated_reference_identity );
tra_vel_composer_expect( is_array( $generated_reference ) && 1 === preg_match( '/^TVP-[A-Z0-9]{12}$/', $generated_reference['reference'] ), 'New proposal references must use a 48-bit suffix while legacy eight-character references remain readable.' );

$revision_identity = array_merge( $identity, array( 'position' => 'best_value', 'revision' => 2, 'version' => 4 ) );
$revision_proposal = tra_vel_composer_compose( $composition, $case, $now, $revision_identity );
tra_vel_composer_expect( is_array( $revision_proposal ) && 4 === $revision_proposal['version'] && 2 === $revision_proposal['revision'] && 2 === $revision_proposal['published_revision'] && $proposal['proposal_id'] === $revision_proposal['proposal_id'], 'A server-authorized revision must preserve identity while state version remains monotonic after intervening actions.' );
$changed_position = $composition;
$changed_position['position'] = 'most_flexible';
tra_vel_composer_expect_error( tra_vel_composer_compose( $changed_position, $case, $now, $revision_identity ), 'tra_vel_assisted_composition_identity_changed', 'A revision cannot change immutable proposal position.' );

$unpriced = $composition;
$unpriced['components'][0]['price'] = array( 'priced' => false, 'total_for_party_minor' => null, 'currency' => null, 'basis' => 'not_priced', 'taxes' => 'unknown', 'fees' => 'unknown' );
$unpriced_proposal = tra_vel_composer_compose( $unpriced, $case, $now, $identity );
tra_vel_composer_expect( is_array( $unpriced_proposal ) && tra_vel_composer_has_gap( $unpriced_proposal, 'unpriced_component' ) && false === $unpriced_proposal['ledger']['complete_pricing'], 'Unpriced components must add a visible gap without a fictional amount.' );

$fees_unknown = $composition;
$fees_unknown['components'][0]['price']['fees'] = 'unknown';
$fees_unknown_proposal = tra_vel_composer_compose( $fees_unknown, $case, $now, $identity );
tra_vel_composer_expect( is_array( $fees_unknown_proposal ) && tra_vel_composer_has_gap( $fees_unknown_proposal, 'fees_unknown' ) && false === $fees_unknown_proposal['ledger']['complete_pricing'], 'Unknown fees must produce a valid partial ledger and explicit gap.' );

$official_only = $composition;
$official_only['sources'][0] = array_merge( $source, array( 'provider_code' => 'israel-government', 'source_type' => 'official_information', 'relationship' => 'public_reference', 'source_reference' => '', 'source_url' => 'https://www.gov.il/travel', 'freshness_minutes' => 60 ) );
tra_vel_composer_expect_error( tra_vel_composer_compose( $official_only, $case, $now, $identity ), 'tra_vel_assisted_proposal_price_unsourced', 'General official information alone cannot support a numeric price.' );

$unregistered_public_source = $official_only;
$unregistered_public_source['components'][0]['price'] = array( 'priced' => false, 'total_for_party_minor' => null, 'currency' => null, 'basis' => 'not_priced', 'taxes' => 'unknown', 'fees' => 'unknown' );
$unregistered_public_source['sources'][0]['provider_code'] = 'fixture-provider';
tra_vel_composer_expect_error( tra_vel_composer_compose( $unregistered_public_source, $case, $now, $identity ), 'tra_vel_assisted_proposal_public_provider_untrusted', 'An operator-entered provider code cannot turn an arbitrary public link into trusted evidence.' );

$wrong_public_host = $unregistered_public_source;
$wrong_public_host['sources'][0]['provider_code'] = 'israel-government';
$wrong_public_host['sources'][0]['source_url'] = 'https://government.example/travel';
tra_vel_composer_expect_error( tra_vel_composer_compose( $wrong_public_host, $case, $now, $identity ), 'tra_vel_assisted_proposal_public_provider_untrusted', 'A registered provider code cannot be paired with an unregistered hostname.' );

$unknown = $composition;
$unknown['sources'][0]['raw_evidence'] = 'must-not-enter-the-contract';
tra_vel_composer_expect_error( tra_vel_composer_compose( $unknown, $case, $now, $identity ), 'tra_vel_assisted_composition_shape_invalid', 'Unknown or raw evidence fields must fail closed.' );

$unverified_private_relationship = $composition;
$unverified_private_relationship['sources'][0]['relationship'] = 'contracted';
tra_vel_composer_expect_error( tra_vel_composer_compose( $unverified_private_relationship, $case, $now, $identity ), 'tra_vel_assisted_proposal_private_relationship_unverified', 'Manual private evidence must not claim a contracted or affiliate relationship that no server registry proves.' );

$unsafe_url = $official_only;
$unsafe_url['components'][0]['price'] = array( 'priced' => false, 'total_for_party_minor' => null, 'currency' => null, 'basis' => 'not_priced', 'taxes' => 'unknown', 'fees' => 'unknown' );
$unsafe_url['sources'][0]['source_url'] = 'https://www.gov.il/travel?token=secret';
tra_vel_composer_expect_error( tra_vel_composer_compose( $unsafe_url, $case, $now, $identity ), 'tra_vel_assisted_proposal_source_url_invalid', 'Credential-bearing or query URLs must fail closed.' );

$unsafe_port = $unsafe_url;
$unsafe_port['sources'][0]['source_url'] = 'https://www.gov.il:444/travel';
tra_vel_composer_expect_error( tra_vel_composer_compose( $unsafe_port, $case, $now, $identity ), 'tra_vel_assisted_proposal_source_url_invalid', 'Public evidence must not link to an unregistered non-default HTTPS service port.' );

$ttl = $composition;
$ttl['sources'][0]['freshness_minutes'] = 61;
tra_vel_composer_expect_error( tra_vel_composer_compose( $ttl, $case, $now, $identity ), 'tra_vel_assisted_composition_source_policy_invalid', 'Connected evidence cannot exceed its one-hour freshness policy.' );

$not_revalidated = $composition;
$not_revalidated['sources'][0]['revalidated_now'] = false;
tra_vel_composer_expect_error( tra_vel_composer_compose( $not_revalidated, $case, $now, $identity ), 'tra_vel_assisted_composition_source_revalidation_required', 'A source without an explicit current operator recheck must fail closed.' );

$duplicate_day = $composition;
$duplicate_day['itinerary'][] = $duplicate_day['itinerary'][0];
tra_vel_composer_expect_error( tra_vel_composer_compose( $duplicate_day, $case, $now, $identity ), 'tra_vel_assisted_composition_itinerary_day_invalid', 'Duplicate itinerary day numbers must fail closed.' );

$short_retention = array_merge( $case, array( 'retention_until' => gmdate( 'Y-m-d H:i:s', $now + 1800 ) ) );
tra_vel_composer_expect_error( tra_vel_composer_compose( $composition, $short_retention, $now, $identity ), 'tra_vel_assisted_composition_retention_conflict', 'Source freshness cannot extend beyond parent retention.' );

$second = $component;
$second['component_key'] = 'hotel-stay';
$second['category'] = 'accommodation';
$second['price']['currency'] = 'USD';
$second['source_indexes'] = array( 0 );
$mixed = $composition;
$mixed['components'][] = $second;
$mixed['itinerary'][0]['component_keys'][] = 'hotel-stay';
tra_vel_composer_expect_error( tra_vel_composer_compose( $mixed, $case, $now, $identity ), 'tra_vel_assisted_proposal_currency_mixed', 'Mixed-currency proposal ledgers must fail closed.' );

$attestation = Tra_Vel_Assisted_Proposal_Composer::issue_evidence_attestation( $composition, $case, 7, $now );
tra_vel_composer_expect( is_array( $attestation ) && ! empty( $attestation['attestation_token'] ), 'A complete evidence command must receive a short-lived signed attestation.' );
$attested = $composition;
$attested['evidence_attestation_token'] = $attestation['attestation_token'];
$verified_attestation = Tra_Vel_Assisted_Proposal_Composer::verify_evidence_attestation( $attested, $case, 7, $now + 120 );
tra_vel_composer_expect( is_array( $verified_attestation ) && $now === $verified_attestation['checked_at'], 'Attestation verification must preserve the actual check time rather than submission time.' );
$tampered_attestation = $attested;
$tampered_attestation['title'] = 'Changed after the evidence check';
tra_vel_composer_expect_error( Tra_Vel_Assisted_Proposal_Composer::verify_evidence_attestation( $tampered_attestation, $case, 7, $now + 120 ), 'tra_vel_assisted_composition_attestation_stale', 'Any final command edit must invalidate its evidence attestation.' );
tra_vel_composer_expect_error( Tra_Vel_Assisted_Proposal_Composer::verify_evidence_attestation( $attested, $case, 8, $now + 120 ), 'tra_vel_assisted_composition_attestation_stale', 'An attestation must be bound to the exact operator.' );
tra_vel_composer_expect_error( Tra_Vel_Assisted_Proposal_Composer::verify_evidence_attestation( $attested, $case, 7, $now + 301 ), 'tra_vel_assisted_composition_attestation_stale', 'An evidence attestation must expire after five minutes.' );

echo 'Tra-Vel assisted proposal composer runtime passed (' . $assertions . ' deterministic assertions).' . PHP_EOL;
