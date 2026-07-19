<?php
/**
 * Deterministic runtime fixtures for the assisted-proposal publication policy.
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );
defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;

		public function __construct( $code, $message ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code() {
			return $this->code;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) {
		return $value instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

$tra_vel_assisted_proposal_test_user_emails = array(
	7 => ' Traveler@Example.COM ',
);

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $user_id ) {
		global $tra_vel_assisted_proposal_test_user_emails;
		$user_id = absint( $user_id );
		if ( ! array_key_exists( $user_id, $tra_vel_assisted_proposal_test_user_emails ) ) {
			return false;
		}
		return (object) array( 'user_email' => $tra_vel_assisted_proposal_test_user_emails[ $user_id ] );
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? (string) $email : false;
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'test-wordpress-' . (string) $scheme . '-salt';
	}
}

class Tra_Vel_Assisted_Proposal_Test_Wpdb {
	public $prefix = 'wp_';
	public $last_error = '';
	public $rows = array();
	private $last_event_uuid = '';
	private $errors_suppressed = false;

	public function prepare( $query, ...$args ) {
		$this->last_event_uuid = isset( $args[0] ) ? (string) $args[0] : '';
		return (string) $query;
	}

	public function suppress_errors( $suppress = null ) {
		$previous = $this->errors_suppressed;
		if ( null !== $suppress ) {
			$this->errors_suppressed = (bool) $suppress;
		}
		return $previous;
	}

	public function get_row( $query, $format ) {
		unset( $query, $format );
		return $this->rows[ $this->last_event_uuid ] ?? null;
	}
}

$wpdb = new Tra_Vel_Assisted_Proposal_Test_Wpdb();

require_once dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/includes/class-tra-vel-assisted-proposal-policy.php';
require_once dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/includes/class-tra-vel-assisted-proposal-store.php';

$tra_vel_assisted_proposal_assertions = 0;

function tra_vel_assisted_proposal_fail( $message ) {
	fwrite( STDERR, 'Tra-Vel assisted proposal runtime validation failed: ' . $message . PHP_EOL );
	exit( 1 );
}

function tra_vel_assisted_proposal_expect( $condition, $message ) {
	global $tra_vel_assisted_proposal_assertions;
	$tra_vel_assisted_proposal_assertions++;
	if ( ! $condition ) {
		tra_vel_assisted_proposal_fail( $message );
	}
}

function tra_vel_assisted_proposal_expect_error( $value, $code, $message ) {
	tra_vel_assisted_proposal_expect( is_wp_error( $value ), $message . ' Expected WP_Error.' );
	if ( is_wp_error( $value ) ) {
		tra_vel_assisted_proposal_expect( $code === $value->get_error_code(), $message . ' Unexpected error code ' . $value->get_error_code() . '.' );
	}
}

function tra_vel_assisted_proposal_source( $now, $overrides = array() ) {
	return array_merge(
		array(
			'contract_version'      => '1.0.0',
			'source_id'             => '123e4567-e89b-42d3-a456-426614174000',
			'provider_code'         => 'fixture-provider',
			'source_type'           => 'connected_api',
			'relationship'          => 'operator_attested',
			'public_label'          => 'Operator-checked provider evidence',
			'supplier_name'         => 'Fixture supplier',
			'seller_name'           => 'Fixture seller',
			'source_reference'      => 'SRC:FIXTURE-1',
			'source_url'            => null,
			'observed_at'           => gmdate( 'c', $now ),
			'fresh_until'           => gmdate( 'c', $now + HOUR_IN_SECONDS ),
			'evidence_digest'       => str_repeat( 'a', 64 ),
			'requires_revalidation' => true,
		),
		$overrides
	);
}

function tra_vel_assisted_proposal_component( $source_id, $overrides = array() ) {
	return array_merge(
		array(
			'component_key'         => 'outbound-flight',
			'category'              => 'flights',
			'title'                 => 'Sourced flight option',
			'description'           => 'The component is tied to the current immutable evidence revision.',
			'price'                 => array(
				'priced'                => true,
				'total_for_party_minor' => 123400,
				'currency'              => 'ILS',
				'basis'                 => 'trip_total',
				'taxes'                 => 'included',
				'fees'                  => 'included',
			),
			'conditions'            => array(
				'cancellation'         => 'Review the cited source before the final personal quote.',
				'changes'              => 'Changes require a new source check.',
				'baggage_or_inclusions'=> 'The cited source controls the included items.',
			),
			'source_ids'            => array( $source_id ),
			'requires_revalidation' => true,
		),
		$overrides
	);
}

function tra_vel_assisted_proposal_build( $now, $sources, $components, $overrides = array() ) {
	$latest_observed = 0;
	$earliest_expiry = null;
	foreach ( $sources as $source ) {
		$latest_observed = max( $latest_observed, strtotime( $source['observed_at'] ) );
		$source_expiry   = strtotime( $source['fresh_until'] );
		$earliest_expiry = null === $earliest_expiry ? $source_expiry : min( $earliest_expiry, $source_expiry );
	}
	$ledger = Tra_Vel_Assisted_Proposal_Policy::compute_ledger( $components );
	if ( is_wp_error( $ledger ) ) {
		tra_vel_assisted_proposal_fail( 'Fixture builder received components that cannot produce a ledger: ' . $ledger->get_error_code() );
	}

	return array_merge(
		array(
			'contract_version'     => '1.0.0',
			'proposal_id'          => '123e4567-e89b-42d3-a456-426614174001',
			'case_id'              => '123e4567-e89b-42d3-a456-426614174002',
			'reference'            => 'TVP-ABCDEFGH',
			'status'               => 'available',
			'version'              => 1,
			'revision'             => 1,
			'published_revision'   => 1,
			'position'             => 'best_value',
			'addresses'            => array(
				'case_revision' => 2,
				'request_digest'=> str_repeat( 'b', 64 ),
			),
			'title'                => 'Personal sourced proposal',
			'summary'              => 'A non-binding proposal for traveler review.',
			'why_it_fits'          => array( 'It addresses the current case revision.' ),
			'trade_offs'           => array( 'Every commercial fact still requires final revalidation.' ),
			'route'                => array(
				'origin'       => 'Tel Aviv',
				'destinations' => array( 'Athens' ),
				'legs'         => array(),
			),
			'itinerary'            => array(
				array(
					'day'            => 1,
					'place'          => 'Athens',
					'title'          => 'Arrival',
					'component_keys' => array( $components[0]['component_key'] ),
				),
			),
			'components'           => $components,
			'ledger'               => $ledger,
			'sources'              => $sources,
			'source_set_digest'    => Tra_Vel_Assisted_Proposal_Policy::source_set_digest( $sources ),
			'freshness'            => array(
				'checked_at'           => gmdate( 'c', $latest_observed ),
				'expires_at'           => gmdate( 'c', $earliest_expiry ),
				'requires_revalidation'=> true,
			),
			'unresolved_items'     => array(
				array( 'code' => 'availability_revalidation', 'label' => 'Availability requires a new source check.' ),
			),
			'traveler_disposition' => 'awaiting_review',
			'next_actions'         => array( 'review', 'request_changes', 'authorize_contact', 'decline' ),
			'disclosure'           => array(
				'commercial_state'   => 'non_binding_assisted_proposal',
				'final_quote_required'=> true,
				'message'            => Tra_Vel_Assisted_Proposal_Policy::FINAL_QUOTE_DISCLOSURE,
			),
			'created_at'           => gmdate( 'c', $now - 60 ),
			'published_at'         => gmdate( 'c', $latest_observed ),
			'expires_at'           => gmdate( 'c', $earliest_expiry ),
		),
		$overrides
	);
}

function tra_vel_assisted_proposal_context( $overrides = array() ) {
	return array_merge(
		array(
			'case_active'   => true,
			'case_revision' => 2,
			'request_digest'=> str_repeat( 'b', 64 ),
		),
		$overrides
	);
}

$now        = 1760000000;
$source     = tra_vel_assisted_proposal_source( $now );
$component  = tra_vel_assisted_proposal_component( $source['source_id'] );
$proposal   = tra_vel_assisted_proposal_build( $now, array( $source ), array( $component ) );
$context    = tra_vel_assisted_proposal_context();

tra_vel_assisted_proposal_expect(
	true === Tra_Vel_Assisted_Proposal_Policy::validate_publication( $proposal, array( $source ), $context, $now ),
	'A complete sourced publication fixture must pass.'
);

$unverified_private_relationship = tra_vel_assisted_proposal_source( $now, array( 'relationship' => 'contracted' ) );
$unverified_private_proposal = tra_vel_assisted_proposal_build( $now, array( $unverified_private_relationship ), array( tra_vel_assisted_proposal_component( $unverified_private_relationship['source_id'] ) ) );
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $unverified_private_proposal, array( $unverified_private_relationship ), $context, $now ),
	'tra_vel_assisted_proposal_private_relationship_unverified',
	'Manual private evidence must not claim an unverified contracted or affiliate relationship.'
);

$before_check = $proposal;
$before_check['published_at'] = gmdate( 'c', $now - 1 );
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $before_check, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_publication_order_invalid',
	'Publication cannot precede the latest source check.'
);

$after_expiry = $proposal;
$after_expiry['published_at'] = gmdate( 'c', strtotime( $proposal['expires_at'] ) + 1 );
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $after_expiry, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_publication_order_invalid',
	'Publication cannot exceed proposal expiry.'
);

$future_source = tra_vel_assisted_proposal_source(
	$now,
	array(
		'observed_at' => gmdate( 'c', $now + 120 ),
		'fresh_until' => gmdate( 'c', $now + 120 + HOUR_IN_SECONDS ),
	)
);
$future_proposal = tra_vel_assisted_proposal_build( $now, array( $future_source ), array( tra_vel_assisted_proposal_component( $future_source['source_id'] ) ) );
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $future_proposal, array( $future_source ), $context, $now ),
	'tra_vel_assisted_proposal_source_stale',
	'Future-dated evidence must fail closed.'
);

$stale_source = tra_vel_assisted_proposal_source( $now, array( 'fresh_until' => gmdate( 'c', $now + 100 ) ) );
$stale_proposal = tra_vel_assisted_proposal_build( $now, array( $stale_source ), array( tra_vel_assisted_proposal_component( $stale_source['source_id'] ) ) );
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $stale_proposal, array( $stale_source ), $context, $now ),
	'tra_vel_assisted_proposal_source_stale',
	'Stale evidence must fail closed.'
);

$second_component = tra_vel_assisted_proposal_component(
	$source['source_id'],
	array(
		'component_key' => 'hotel',
		'category'      => 'accommodation',
		'price'         => array(
			'priced'                => true,
			'total_for_party_minor' => 50000,
			'currency'              => 'USD',
			'basis'                 => 'stay_total',
			'taxes'                 => 'included',
			'fees'                  => 'included',
		),
	)
);
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::compute_ledger( array( $component, $second_component ) ),
	'tra_vel_assisted_proposal_currency_mixed',
	'Mixed currencies must not produce a proposal ledger.'
);

$official_source = tra_vel_assisted_proposal_source(
	$now,
	array(
		'source_id'        => '123e4567-e89b-42d3-a456-426614174003',
		'provider_code'    => 'israel-government',
		'source_type'      => 'official_information',
		'relationship'     => 'public_reference',
		'source_reference' => '',
		'source_url'       => 'https://www.gov.il/evidence/official',
	)
);
tra_vel_assisted_proposal_expect(
	true === Tra_Vel_Assisted_Proposal_Policy::validate_public_provider_binding( 'booking', 'public_supplier_page', 'public_reference', 'https://www.booking.com/hotel/example' ),
	'A registered public-reference source must accept its exact provider hostname.'
);
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_public_provider_binding( 'booking', 'public_supplier_page', 'affiliate', 'https://www.booking.com/hotel/example' ),
	'tra_vel_assisted_proposal_public_provider_untrusted',
	'A public link must not claim an affiliate relationship without reviewed production registry configuration.'
);
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_public_provider_binding( 'booking', 'public_supplier_page', 'public_reference', 'https://booking.example/hotel/example' ),
	'tra_vel_assisted_proposal_public_provider_untrusted',
	'A provider code must not authenticate an unrelated hostname.'
);
$unpriced_component = tra_vel_assisted_proposal_component(
	$official_source['source_id'],
	array(
		'component_key' => 'local-guidance',
		'category'      => 'activities',
		'price'         => array(
			'priced'                => false,
			'total_for_party_minor' => null,
			'currency'              => null,
			'basis'                 => 'not_priced',
			'taxes'                 => 'unknown',
			'fees'                  => 'unknown',
		),
	)
);
$unpriced_ledger = Tra_Vel_Assisted_Proposal_Policy::compute_ledger( array( $unpriced_component ) );
tra_vel_assisted_proposal_expect( ! is_wp_error( $unpriced_ledger ), 'An explicitly unpriced sourced component must remain representable.' );
tra_vel_assisted_proposal_expect( false === $unpriced_ledger['complete_pricing'] && null === $unpriced_ledger['currency'] && 0 === $unpriced_ledger['priced_total_minor'], 'Unpriced ledger truth must remain incomplete and currency-free.' );
$unpriced_proposal = tra_vel_assisted_proposal_build(
	$now,
	array( $official_source ),
	array( $unpriced_component ),
	array(
		'unresolved_items' => array(
			array( 'code' => 'availability_revalidation', 'label' => 'Availability requires a new source check.' ),
			array( 'code' => 'unpriced_component', 'label' => 'This planning component has no sourced amount.' ),
		),
	)
);
tra_vel_assisted_proposal_expect(
	true === Tra_Vel_Assisted_Proposal_Policy::validate_publication( $unpriced_proposal, array( $official_source ), $context, $now ),
	'An unpriced component must publish only when its gap is explicit.'
);

$undisclosed_unpriced = $unpriced_proposal;
$undisclosed_unpriced['unresolved_items'] = array(
	array( 'code' => 'availability_revalidation', 'label' => 'Availability requires a new source check.' ),
);
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $undisclosed_unpriced, array( $official_source ), $context, $now ),
	'tra_vel_assisted_proposal_gap_undisclosed',
	'An unpriced component cannot hide its incomplete-ledger class.'
);

$official_priced = tra_vel_assisted_proposal_component( $official_source['source_id'] );
$official_priced_proposal = tra_vel_assisted_proposal_build( $now, array( $official_source ), array( $official_priced ) );
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $official_priced_proposal, array( $official_source ), $context, $now ),
	'tra_vel_assisted_proposal_price_unsourced',
	'A numeric price cannot rely only on general information.'
);

$forbidden = $proposal;
$forbidden['booking_id'] = 'forbidden';
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $forbidden, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_transactional_field_forbidden',
	'Transactional outcome fields must be impossible.'
);

$tampered_sources = $proposal;
$tampered_sources['source_set_digest'] = str_repeat( 'f', 64 );
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $tampered_sources, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_source_set_changed',
	'Source-set digest tampering must fail closed.'
);

tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $proposal, array( $source ), tra_vel_assisted_proposal_context( array( 'case_revision' => 3 ) ), $now ),
	'tra_vel_assisted_proposal_case_revision_changed',
	'A case revision mismatch must invalidate publication.'
);
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $proposal, array( $source ), tra_vel_assisted_proposal_context( array( 'request_digest' => str_repeat( 'c', 64 ) ) ), $now ),
	'tra_vel_assisted_proposal_request_changed',
	'A request digest mismatch must invalidate publication.'
);

$invalid_identity = $proposal;
$invalid_identity['proposal_id'] = 'not-a-uuid';
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $invalid_identity, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_identity_invalid',
	'Proposal UUID validation is required.'
);
$invalid_case_identity = $proposal;
$invalid_case_identity['case_id'] = 'not-a-uuid';
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $invalid_case_identity, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_identity_invalid',
	'Case UUID validation is required.'
);
$invalid_reference = $proposal;
$invalid_reference['reference'] = 'TVP-invalid';
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $invalid_reference, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_identity_invalid',
	'Public proposal reference validation is required.'
);
$invalid_revision = $proposal;
$invalid_revision['version'] = 0;
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $invalid_revision, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_revision_invalid',
	'Proposal versions must be positive and revision-bound.'
);
$version_behind_revision = $proposal;
$version_behind_revision['version'] = 1;
$version_behind_revision['revision'] = 2;
$version_behind_revision['published_revision'] = 2;
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $version_behind_revision, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_revision_invalid',
	'Proposal version cannot trail the immutable revision.'
);
$version_ahead_of_revision = $proposal;
$version_ahead_of_revision['version'] = 4;
tra_vel_assisted_proposal_expect(
	true === Tra_Vel_Assisted_Proposal_Policy::validate_publication( $version_ahead_of_revision, array( $source ), $context, $now ),
	'Proposal aggregate version may lead its commercial revision after traveler actions.'
);
$published_revision_mismatch = $proposal;
$published_revision_mismatch['published_revision'] = 2;
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $published_revision_mismatch, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_revision_invalid',
	'Publication must point to the exact proposal revision.'
);

$pre_decided = $proposal;
$pre_decided['traveler_disposition'] = 'reviewed';
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $pre_decided, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_disposition_invalid',
	'An operator publication cannot originate a traveler disposition.'
);
$missing_action = $proposal;
$missing_action['next_actions'] = array( 'review', 'request_changes', 'decline' );
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $missing_action, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_next_actions_invalid',
	'Initial publication must expose all four safe actions.'
);

$unknown_itinerary_component = $proposal;
$unknown_itinerary_component['itinerary'][0]['component_keys'] = array( 'missing-component' );
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $unknown_itinerary_component, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_itinerary_component_missing',
	'Itinerary references cannot escape the immutable component revision.'
);

$hidden_availability_gap = $proposal;
$hidden_availability_gap['unresolved_items'] = array();
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $hidden_availability_gap, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_gap_undisclosed',
	'Every publication must disclose availability revalidation.'
);

$unknown_charge_component = tra_vel_assisted_proposal_component(
	$source['source_id'],
	array(
		'price' => array(
			'priced'                => true,
			'total_for_party_minor' => 123400,
			'currency'              => 'ILS',
			'basis'                 => 'trip_total',
			'taxes'                 => 'unknown',
			'fees'                  => 'excluded',
		),
	)
);
$hidden_charge_gaps = tra_vel_assisted_proposal_build( $now, array( $source ), array( $unknown_charge_component ) );
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $hidden_charge_gaps, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_gap_undisclosed',
	'Unknown taxes and excluded fees must be explicit unresolved classes.'
);
$disclosed_charge_gaps = $hidden_charge_gaps;
$disclosed_charge_gaps['unresolved_items'] = array(
	array( 'code' => 'availability_revalidation', 'label' => 'Availability requires a new source check.' ),
	array( 'code' => 'taxes_unknown', 'label' => 'The final tax amount requires provider revalidation.' ),
	array( 'code' => 'fees_unknown', 'label' => 'The final fee amount requires provider revalidation.' ),
);
tra_vel_assisted_proposal_expect(
	true === Tra_Vel_Assisted_Proposal_Policy::validate_publication( $disclosed_charge_gaps, array( $source ), $context, $now ),
	'A priced component may remain reviewable when every unknown charge class is explicit.'
);

$duplicate_unresolved = $proposal;
$duplicate_unresolved['unresolved_items'][] = array( 'code' => 'availability_revalidation', 'label' => 'Duplicate availability gap.' );
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $duplicate_unresolved, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_unresolved_duplicate',
	'Unresolved disclosure codes must be unique.'
);

$empty_condition_component = $component;
$empty_condition_component['conditions']['cancellation'] = '   ';
$empty_condition_proposal = tra_vel_assisted_proposal_build( $now, array( $source ), array( $empty_condition_component ) );
tra_vel_assisted_proposal_expect_error(
	Tra_Vel_Assisted_Proposal_Policy::validate_publication( $empty_condition_proposal, array( $source ), $context, $now ),
	'tra_vel_assisted_proposal_conditions_incomplete',
	'Every component condition must contain meaningful text.'
);

tra_vel_assisted_proposal_expect( 'expired' === Tra_Vel_Assisted_Proposal_Policy::effective_status( 'available', gmdate( 'c', $now - 1 ), $now ), 'Elapsed available proposals must expire lazily.' );
tra_vel_assisted_proposal_expect( 'available' === Tra_Vel_Assisted_Proposal_Policy::effective_status( 'available', gmdate( 'c', $now + 1 ), $now ), 'Unelapsed available proposals must remain available.' );
tra_vel_assisted_proposal_expect( 'expired' === Tra_Vel_Assisted_Proposal_Policy::effective_status( 'unknown', gmdate( 'c', $now + 1 ), $now ), 'Unknown lifecycle values must fail closed.' );

tra_vel_assisted_proposal_expect( Tra_Vel_Assisted_Proposal_Policy::can_append_revision( 'draft' ), 'Draft proposal heads must accept an immutable revision.' );
tra_vel_assisted_proposal_expect( Tra_Vel_Assisted_Proposal_Policy::can_append_revision( 'available' ), 'Available proposal heads may stage a later immutable revision.' );
foreach ( array( 'withdrawn', 'expired', 'superseded', 'paid' ) as $terminal_status ) {
	tra_vel_assisted_proposal_expect( ! Tra_Vel_Assisted_Proposal_Policy::can_append_revision( $terminal_status ), 'Terminal or forbidden state must not append a revision: ' . $terminal_status . '.' );
}

tra_vel_assisted_proposal_expect(
	array( 'review', 'request_changes', 'authorize_contact', 'decline' ) === Tra_Vel_Assisted_Proposal_Policy::traveler_actions_for( 'available', 'awaiting_review' ),
	'An awaiting-review proposal must expose the exact four safe traveler actions.'
);
tra_vel_assisted_proposal_expect(
	array( 'request_changes', 'authorize_contact', 'decline' ) === Tra_Vel_Assisted_Proposal_Policy::traveler_actions_for( 'available', 'reviewed' ),
	'A reviewed proposal must not offer a second review transition.'
);
tra_vel_assisted_proposal_expect(
	array() === Tra_Vel_Assisted_Proposal_Policy::traveler_actions_for( 'withdrawn', 'awaiting_review' )
	&& array() === Tra_Vel_Assisted_Proposal_Policy::traveler_actions_for( 'available', 'changes_requested' ),
	'Terminal proposal or traveler states must not expose actions.'
);
tra_vel_assisted_proposal_expect(
	'reviewed' === Tra_Vel_Assisted_Proposal_Policy::traveler_action_target( 'available', 'awaiting_review', 'review' )
	&& 'changes_requested' === Tra_Vel_Assisted_Proposal_Policy::traveler_action_target( 'available', 'reviewed', 'request_changes' )
	&& 'contact_authorized' === Tra_Vel_Assisted_Proposal_Policy::traveler_action_target( 'available', 'reviewed', 'authorize_contact' )
	&& 'declined' === Tra_Vel_Assisted_Proposal_Policy::traveler_action_target( 'available', 'awaiting_review', 'decline' ),
	'Every safe traveler action must resolve to its exact non-transactional disposition.'
);
foreach ( array(
	array( 'withdrawn', 'awaiting_review', 'review' ),
	array( 'available', 'reviewed', 'review' ),
	array( 'available', 'changes_requested', 'authorize_contact' ),
	array( 'available', 'contact_authorized', 'request_changes' ),
	array( 'available', 'declined', 'review' ),
	array( 'available', 'awaiting_review', 'book' ),
) as $invalid_action ) {
	tra_vel_assisted_proposal_expect_error(
		Tra_Vel_Assisted_Proposal_Policy::traveler_action_target( $invalid_action[0], $invalid_action[1], $invalid_action[2] ),
		'tra_vel_assisted_proposal_action_conflict',
		'Invalid traveler action/state combinations must fail closed.'
	);
}

// Contact authorization persists an email-specific HMAC as immutable evidence,
// while its idempotent command remains bound only to the stable account target.
$contact_store = new Tra_Vel_Assisted_Proposal_Store();
$stable_contact_consent = array(
	'contract_version' => '1.0.0',
	'consent_version'  => '2026-07-19',
	'affirmed'         => true,
	'purpose'          => 'assisted_proposal_follow_up',
	'channels'         => array( 'email' ),
	'controller_scope' => 'tra_vel',
	'recipient_scope'  => 'tra_vel_assistance_team',
	'contact_target'   => 'account_email',
);
$normalize_contact = new ReflectionMethod( Tra_Vel_Assisted_Proposal_Store::class, 'normalize_contact_consent' );
$normalize_contact->setAccessible( true );
$normalized_contact = $normalize_contact->invoke( $contact_store, 'authorize_contact', $stable_contact_consent, array( 'user_id' => 7 ) );
tra_vel_assisted_proposal_expect( $stable_contact_consent === $normalized_contact, 'The logical consent command must remain bound to the stable account_email target.' );
tra_vel_assisted_proposal_expect( ! array_key_exists( 'contact_target_digest', $normalized_contact ), 'Mutable email evidence must not enter the logical idempotency command.' );
$caller_digest_consent = array_merge( $stable_contact_consent, array( 'contact_target_digest' => str_repeat( 'a', 64 ) ) );
tra_vel_assisted_proposal_expect_error(
	$normalize_contact->invoke( $contact_store, 'authorize_contact', $caller_digest_consent, array( 'user_id' => 7 ) ),
	'tra_vel_assisted_proposal_contact_consent_shape_invalid',
	'Callers must not be able to inject a contact-target digest.'
);

$current_email_digest = new ReflectionMethod( Tra_Vel_Assisted_Proposal_Store::class, 'current_account_email_digest' );
$current_email_digest->setAccessible( true );
$expected_contact_digest = hash_hmac(
	'sha256',
	'wp-user-account:7|account-email:traveler@example.com',
	'test-wordpress-auth-salt|tra-vel-assisted-proposal-contact-target-v1'
);
$derived_contact_digest = $current_email_digest->invoke( $contact_store, 7 );
tra_vel_assisted_proposal_expect( $expected_contact_digest === $derived_contact_digest, 'Contact evidence must HMAC the exact normalized account email with the WordPress auth secret.' );
tra_vel_assisted_proposal_expect( hash( 'sha256', 'wp-user-account:7' ) !== $derived_contact_digest, 'A user-ID-only digest must not stand in for exact-address consent.' );
$state_event_digest = new ReflectionMethod( Tra_Vel_Assisted_Proposal_Store::class, 'state_event_operation_digest' );
$state_event_digest->setAccessible( true );
$stable_command_digest = $state_event_digest->invoke(
	$contact_store,
	'proposal.traveler_action',
	array( 'case_uuid' => '123e4567-e89b-42d3-a456-426614174002' ),
	'123e4567-e89b-42d3-a456-426614174001',
	2,
	'authorize_contact',
	$normalized_contact
);
$tra_vel_assisted_proposal_test_user_emails[7] = 'another-current-address@example.com';
$same_logical_contact = $normalize_contact->invoke( $contact_store, 'authorize_contact', $stable_contact_consent, array( 'user_id' => 7 ) );
tra_vel_assisted_proposal_expect(
	$stable_command_digest === $state_event_digest->invoke( $contact_store, 'proposal.traveler_action', array( 'case_uuid' => '123e4567-e89b-42d3-a456-426614174002' ), '123e4567-e89b-42d3-a456-426614174001', 2, 'authorize_contact', $same_logical_contact ),
	'Account-email changes must not alter the exact logical command or receipt identity.'
);
$tra_vel_assisted_proposal_test_user_emails[7] = ' Traveler@Example.COM ';

$contact_event_uuid = '123e4567-e89b-42d3-a456-426614174010';
$contact_event_payload = array(
	'contract_version' => '1.0.0',
	'action'           => 'authorize_contact',
	'from_status'      => 'available',
	'to_status'        => 'available',
	'from_disposition' => 'reviewed',
	'to_disposition'   => 'contact_authorized',
	'proposal_version' => 3,
	'case_revision'    => 2,
	'request_digest'   => str_repeat( 'b', 64 ),
	'proposal_revision'=> 1,
	'contact_consent'  => array_merge(
		$stable_contact_consent,
		array(
			'contact_target_digest' => $expected_contact_digest,
			'consented_at'           => '2026-07-19T10:00:00Z',
		)
	),
);
$contact_event_json = wp_json_encode( $contact_event_payload );
$wpdb->rows[ $contact_event_uuid ] = array(
	'event_uuid'      => $contact_event_uuid,
	'proposal_id'     => 31,
	'sequence_no'     => 2,
	'proposal_version'=> 3,
	'event_type'      => 'traveler.contact_authorized',
	'action_code'     => 'authorize_contact',
	'from_status'     => 'available',
	'to_status'       => 'available',
	'from_disposition'=> 'reviewed',
	'to_disposition'  => 'contact_authorized',
	'actor_type'      => 'traveler',
	'actor_user_id'   => 7,
	'payload'         => $contact_event_json,
	'payload_digest'  => hash( 'sha256', $contact_event_json ),
	'created_at'      => '2026-07-19 10:00:00',
);
tra_vel_assisted_proposal_expect( true === $contact_store->validate_contact_dispatch_target( $contact_event_uuid, 7 ), 'Dispatch must be authorized while the exact event-bound account email remains current.' );

$tra_vel_assisted_proposal_test_user_emails[7] = 'TRAVELER@example.com';
tra_vel_assisted_proposal_expect( true === $contact_store->validate_contact_dispatch_target( $contact_event_uuid, 7 ), 'Dispatch matching must normalize harmless email casing differences.' );
$tra_vel_assisted_proposal_test_user_emails[7] = 'new-address@example.com';
$historical_contact_event = $contact_store->get_event_by_uuid( $contact_event_uuid );
tra_vel_assisted_proposal_expect( is_array( $historical_contact_event ), 'Historical consent hydration must not depend on the mutable current account email.' );
tra_vel_assisted_proposal_expect_error(
	$contact_store->validate_contact_dispatch_target( $contact_event_uuid, 7 ),
	'tra_vel_assisted_proposal_contact_target_changed',
	'Dispatch must require fresh consent after the current account email changes.'
);
tra_vel_assisted_proposal_expect_error(
	$contact_store->validate_contact_dispatch_target( $contact_event_uuid, 8 ),
	'tra_vel_assisted_proposal_contact_event_forbidden',
	'A contact authorization event must not be replayed for a different account.'
);
$tra_vel_assisted_proposal_test_user_emails[7] = 'not-an-email';
tra_vel_assisted_proposal_expect_error(
	$contact_store->validate_contact_dispatch_target( $contact_event_uuid, 7 ),
	'tra_vel_assisted_proposal_contact_target_unverified',
	'Dispatch must fail closed when the current account email is unavailable or invalid.'
);

$invalid_contact_event_uuid = '123e4567-e89b-42d3-a456-426614174011';
$invalid_contact_payload = $contact_event_payload;
$invalid_contact_payload['contact_consent']['contact_target_digest'] = 'not-a-digest';
$invalid_contact_json = wp_json_encode( $invalid_contact_payload );
$wpdb->rows[ $invalid_contact_event_uuid ] = array_merge(
	$wpdb->rows[ $contact_event_uuid ],
	array(
		'event_uuid'     => $invalid_contact_event_uuid,
		'payload'        => $invalid_contact_json,
		'payload_digest' => hash( 'sha256', $invalid_contact_json ),
	)
);
tra_vel_assisted_proposal_expect_error(
	$contact_store->get_event_by_uuid( $invalid_contact_event_uuid ),
	'tra_vel_assisted_proposal_event_integrity_failed',
	'Historical contact evidence must reject a malformed target HMAC without consulting current account data.'
);
$tra_vel_assisted_proposal_test_user_emails[7] = ' Traveler@Example.COM ';

echo 'Tra-Vel assisted proposal runtime validation passed (' . $tra_vel_assisted_proposal_assertions . ' deterministic assertions).' . PHP_EOL;
