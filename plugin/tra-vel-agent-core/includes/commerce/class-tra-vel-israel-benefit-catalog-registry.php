<?php
/**
 * Source-backed Israeli loyalty, card, portal, and campaign catalogue planner.
 *
 * The catalogue is deliberately non-transactional. It keeps travel inventory,
 * loyalty programs, issuer products, payment rails, redemption portals, and
 * immutable campaign versions on independent axes. A catalogue relationship
 * never proves a traveler's eligibility, balance, price, or availability.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Israel_Benefit_Catalog_Registry {
	const CONTRACT_VERSION  = '1.0.0';
	const CATALOG_ID        = 'israel_benefits_2026_07_19';
	const MAX_FIXTURE_BYTES = 1048576;

	/** @var string */
	private $fixture_path;

	/** @var array|null */
	private $catalog;

	/** @var true|WP_Error|null */
	private $load_result;

	/** @var array<string,array> */
	private $airlines = array();

	/** @var array<string,array> */
	private $programs = array();

	/** @var array<string,array> */
	private $networks = array();

	/** @var array<string,array> */
	private $credentials = array();

	/** @var array<string,array> */
	private $portals = array();

	/** @var array<string,array<int,array>> */
	private $campaigns = array();

	/** @var array<string,true> */
	private $program_portal_links = array();

	/** @var array<string,true> */
	private $credential_program_links = array();

	/** @var array<string,true> */
	private $portal_inventory_links = array();

	/**
	 * @param string|null $fixture_path Explicit fixture path for deterministic tests.
	 */
	public function __construct( $fixture_path = null ) {
		$this->fixture_path = null === $fixture_path
			? dirname( __DIR__, 2 ) . '/assets/fixtures/israel-benefit-catalog.json'
			: (string) $fixture_path;
	}

	/**
	 * Load and validate the complete closed catalogue once.
	 *
	 * @return true|WP_Error
	 */
	public function load() {
		if ( true === $this->load_result || is_wp_error( $this->load_result ) ) {
			return $this->load_result;
		}
		if ( '' === trim( $this->fixture_path ) || ! is_file( $this->fixture_path ) || ! is_readable( $this->fixture_path ) ) {
			return $this->fail( 'fixture_unreadable', 'The Israeli benefit catalogue fixture is not readable.' );
		}

		$size = filesize( $this->fixture_path );
		if ( false === $size || $size < 2 || $size > self::MAX_FIXTURE_BYTES ) {
			return $this->fail( 'fixture_size_invalid', 'The Israeli benefit catalogue fixture exceeds its closed size boundary.' );
		}

		$contents = file_get_contents( $this->fixture_path );
		if ( false === $contents ) {
			return $this->fail( 'fixture_unreadable', 'The Israeli benefit catalogue fixture could not be read.' );
		}
		$catalog = json_decode( $contents, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $catalog ) ) {
			return $this->fail( 'fixture_json_invalid', 'The Israeli benefit catalogue fixture is not valid JSON.' );
		}

		$top_keys = array(
			'contract_version',
			'catalog_id',
			'observed_at_utc',
			'fresh_until_utc',
			'airline_inventory',
			'programs',
			'payment_networks',
			'credential_products',
			'redemption_portals',
			'campaign_versions',
			'program_portal_links',
			'credential_program_links',
			'portal_inventory_links',
			'migrations',
			'commercial_truth',
		);
		if ( ! self::exact_object( $catalog, $top_keys ) ) {
			return $this->fail( 'fixture_shape_invalid', 'The Israeli benefit catalogue has missing or unknown top-level fields.' );
		}
		if ( self::CONTRACT_VERSION !== $catalog['contract_version'] || self::CATALOG_ID !== $catalog['catalog_id'] ) {
			return $this->fail( 'fixture_identity_invalid', 'The Israeli benefit catalogue identity or contract version is unsupported.' );
		}

		$observed = Tra_Vel_Benefit_Taxonomy::utc_datetime( $catalog['observed_at_utc'] );
		$fresh    = Tra_Vel_Benefit_Taxonomy::utc_datetime( $catalog['fresh_until_utc'] );
		if ( null === $observed || null === $fresh || strcmp( $observed, $fresh ) > 0 ) {
			return $this->fail( 'fixture_freshness_invalid', 'The Israeli benefit catalogue freshness interval is invalid.' );
		}
		if ( ! self::false_truth(
			$catalog['commercial_truth'],
			array( 'live_provider_connection', 'live_balance', 'live_eligibility', 'live_inventory', 'live_price', 'live_discount', 'live_redemption', 'live_checkout' )
		) ) {
			return $this->fail( 'commercial_truth_invalid', 'The Israeli benefit catalogue must remain explicitly non-live.' );
		}

		$catalog['observed_at_utc']  = $observed;
		$catalog['fresh_until_utc'] = $fresh;

		$result = $this->load_airlines( $catalog['airline_inventory'], $catalog );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result = $this->load_programs( $catalog['programs'], $catalog );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result = $this->load_networks( $catalog['payment_networks'], $catalog );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result = $this->load_credentials( $catalog['credential_products'], $catalog );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result = $this->load_portals( $catalog['redemption_portals'], $catalog );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result = $this->load_campaigns( $catalog['campaign_versions'], $catalog );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result = $this->load_links( $catalog );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result = $this->validate_migrations( $catalog['migrations'], $catalog );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->catalog     = $catalog;
		$this->load_result = true;
		return true;
	}

	/**
	 * Check the catalogue-wide review clock without inferring commercial truth.
	 *
	 * @return true|WP_Error
	 */
	public function readiness( $evaluated_at_utc = null ) {
		$result = $this->load();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$at = null === $evaluated_at_utc
			? gmdate( 'Y-m-d\TH:i:s\Z' )
			: Tra_Vel_Benefit_Taxonomy::utc_datetime( $evaluated_at_utc );
		if ( null === $at ) {
			return $this->error( 'evaluation_time_invalid', 'A strict RFC3339 evaluation time is required.' );
		}
		$state = self::freshness_state( $this->catalog['observed_at_utc'], $this->catalog['fresh_until_utc'], $at );
		if ( 'current' !== $state ) {
			return $this->error(
				'catalog_' . $state,
				'The Israeli benefit catalogue is outside its reviewed freshness window.',
				409,
				array( 'next_action_code' => 'refresh_official_sources' )
			);
		}
		return true;
	}

	/**
	 * Stable digest of the validated catalogue.
	 *
	 * @return string|WP_Error
	 */
	public function catalog_digest() {
		$result = $this->load();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$encoded = json_encode( self::canonicalize( $this->catalog ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION );
		if ( false === $encoded ) {
			return $this->error( 'catalog_digest_failed', 'The validated catalogue could not be canonicalized.', 500 );
		}
		return hash( 'sha256', $encoded );
	}

	/**
	 * Return safe catalogue counts and the explicit non-live boundary.
	 *
	 * @return array|WP_Error
	 */
	public function summary( $evaluated_at_utc ) {
		$result = $this->readiness( $evaluated_at_utc );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$digest = $this->catalog_digest();
		if ( is_wp_error( $digest ) ) {
			return $digest;
		}
		return array(
			'contract_version' => self::CONTRACT_VERSION,
			'catalog_id'       => self::CATALOG_ID,
			'catalog_digest'   => $digest,
			'counts'           => array(
				'airline_inventory'   => count( $this->airlines ),
				'programs'             => count( $this->programs ),
				'payment_networks'      => count( $this->networks ),
				'credential_products'   => count( $this->credentials ),
				'redemption_portals'    => count( $this->portals ),
				'campaign_versions'     => $this->campaign_version_count(),
				'migrations'            => count( $this->catalog['migrations'] ),
			),
			'commercial_truth' => $this->catalog['commercial_truth'],
		);
	}

	/**
	 * Return the source-reviewed identities needed to build a dependent benefit
	 * picker. Source evidence and internal catalogue records stay server-side;
	 * the response contains no member data or commercial values.
	 *
	 * @param string $evaluated_at_utc Strict RFC3339 evaluation instant.
	 * @return array|WP_Error
	 */
	public function public_options( $evaluated_at_utc ) {
		$result = $this->load();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$at     = Tra_Vel_Benefit_Taxonomy::utc_datetime( $evaluated_at_utc );
		if ( null === $at ) {
			return $this->error( 'evaluation_time_invalid', 'A strict RFC3339 evaluation time is required.' );
		}
		$review_state = self::freshness_state( $this->catalog['observed_at_utc'], $this->catalog['fresh_until_utc'], $at );
		$digest = $this->catalog_digest();
		if ( is_wp_error( $digest ) ) {
			return $digest;
		}

		$airlines = array();
		foreach ( $this->airlines as $record ) {
			$airlines[] = array(
				'airline_inventory_id' => $record['airline_inventory_id'],
				'display_name'         => $record['display_name'],
				'iata_code'            => $record['iata_code'],
			);
		}

		$programs = array();
		foreach ( $this->programs as $record ) {
			$programs[] = array(
				'program_id'           => $record['program_id'],
				'display_name'         => $record['display_name'],
				'unit_type'            => $record['unit_type'],
				'supported_operations' => $record['supported_operations'],
			);
		}

		$networks = array();
		foreach ( $this->networks as $record ) {
			$networks[] = array(
				'payment_network_id' => $record['payment_network_id'],
				'display_name'       => $record['display_name'],
				'scope'              => $record['scope'],
			);
		}

		$credentials = array();
		foreach ( $this->credentials as $record ) {
			$credentials[] = array(
				'credential_product_id' => $record['credential_product_id'],
				'issuer_id'             => $record['issuer_id'],
				'network_id'            => $record['network_id'],
				'display_name'          => $record['display_name'],
				'tier'                  => $record['tier'],
			);
		}

		$portals = array();
		foreach ( $this->portals as $record ) {
			$portals[] = array(
				'redemption_portal_id' => $record['redemption_portal_id'],
				'display_name'         => $record['display_name'],
			);
		}

		$campaigns = array();
		foreach ( $this->campaigns as $versions ) {
			foreach ( $versions as $record ) {
				$campaigns[] = array(
					'campaign_id'           => $record['campaign_id'],
					'version'               => $record['version'],
					'program_ids'           => $record['program_ids'],
					'credential_product_ids'=> $record['credential_product_ids'],
					'benefit_types'         => $record['benefit_types'],
					'status'                => $record['status'],
				);
			}
		}

		return array(
			'contract_version' => self::CONTRACT_VERSION,
			'catalog_id'       => self::CATALOG_ID,
			'catalog_digest'   => $digest,
			'evaluated_at_utc' => $at,
			'review'           => array(
				'state'           => $review_state,
				'planning_state'  => 'current' === $review_state ? 'identity_selection_ready' : 'source_refresh_required',
				'observed_at_utc' => $this->catalog['observed_at_utc'],
				'fresh_until_utc' => $this->catalog['fresh_until_utc'],
			),
			'options'          => array(
				'airline_inventory' => $airlines,
				'programs'          => $programs,
				'payment_networks'   => $networks,
				'credential_products'=> $credentials,
				'redemption_portals' => $portals,
				'campaign_versions'  => $campaigns,
			),
			'relationships'    => array(
				'program_portal'     => $this->catalog['program_portal_links'],
				'credential_program' => $this->catalog['credential_program_links'],
				'portal_inventory'   => $this->catalog['portal_inventory_links'],
			),
			'next_action_order'=> array(
				'choose_airline_inventory_optional',
				'choose_benefit_program_optional',
				'choose_exact_issuer_card',
				'choose_program_redemption_portal',
				'choose_current_campaign_revision',
				'connect_or_enter_planning_balance',
				'verify_with_provider',
			),
			'commercial_truth' => array(
				'provider_verified_eligibility' => false,
				'live_balance'                  => false,
				'live_inventory'                => false,
				'live_price'                    => false,
				'live_discount'                 => false,
				'live_redemption'               => false,
				'live_checkout'                 => false,
			),
		);
	}

	/**
	 * Build a non-transactional next-action plan across independent axes.
	 *
	 * @return array|WP_Error
	 */
	public function plan( $request, $evaluated_at_utc ) {
		$result = $this->readiness( $evaluated_at_utc );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$keys = array(
			'airline_inventory_id',
			'program_id',
			'credential_product_id',
			'payment_network_id',
			'redemption_portal_id',
			'campaign_id',
			'campaign_version',
			'eligibility_claim',
		);
		if ( ! self::exact_object( $request, $keys ) ) {
			return $this->error( 'plan_shape_invalid', 'The benefit plan request must contain only the exact supported axes.' );
		}
		$claim = Tra_Vel_Benefit_Taxonomy::enum_value(
			$request['eligibility_claim'],
			array( 'none', 'generic_visa_eligible', 'generic_fly_card_eligible', 'exact_product_customer_asserted' )
		);
		if ( '' === $claim ) {
			return $this->error( 'eligibility_claim_invalid', 'The eligibility claim is outside the closed planning vocabulary.' );
		}

		foreach ( array( 'airline_inventory_id', 'program_id', 'credential_product_id', 'payment_network_id', 'redemption_portal_id', 'campaign_id' ) as $field ) {
			if ( null !== $request[ $field ] && '' === Tra_Vel_Benefit_Taxonomy::identifier( $request[ $field ], 'campaign_id' === $field ? 'campaign' : 'generic' ) ) {
				return $this->error( 'plan_axis_invalid', 'A selected benefit axis identifier is invalid.', 400, array( 'axis' => $field ) );
			}
		}
		if ( ( null === $request['campaign_id'] ) !== ( null === $request['campaign_version'] ) || ( null !== $request['campaign_version'] && ( ! is_int( $request['campaign_version'] ) || $request['campaign_version'] < 1 ) ) ) {
			return $this->error( 'campaign_revision_invalid', 'Campaign ID and positive integer version must be selected together.' );
		}

		if ( 'generic_visa_eligible' === $claim ) {
			if ( 'network_visa' !== $request['payment_network_id'] ) {
				return $this->error( 'generic_claim_axis_mismatch', 'A generic Visa claim must identify only the Visa network axis.' );
			}
			return $this->unknown_claim_error(
				'generic_visa_eligibility_forbidden',
				'A Visa network identity never proves issuer, product, or campaign eligibility.',
				'choose_exact_issuer_card_campaign'
			);
		}
		if ( 'generic_fly_card_eligible' === $claim ) {
			return $this->unknown_claim_error(
				'generic_fly_card_eligibility_forbidden',
				'FLY CARD is a family name, not an exact issuer, product, rail, or campaign revision.',
				'choose_exact_fly_card_product'
			);
		}

		$airline   = $this->selected_record( $this->airlines, $request['airline_inventory_id'], 'airline_inventory' );
		$program   = $this->selected_record( $this->programs, $request['program_id'], 'program' );
		$network   = $this->selected_record( $this->networks, $request['payment_network_id'], 'payment_network' );
		$credential = $this->selected_record( $this->credentials, $request['credential_product_id'], 'credential_product' );
		$portal    = $this->selected_record( $this->portals, $request['redemption_portal_id'], 'redemption_portal' );
		foreach ( array( $airline, $program, $network, $credential, $portal ) as $selected ) {
			if ( is_wp_error( $selected ) ) {
				return $selected;
			}
		}

		$campaign = null;
		if ( null !== $request['campaign_id'] ) {
			if ( ! isset( $this->campaigns[ $request['campaign_id'] ][ $request['campaign_version'] ] ) ) {
				return $this->error(
					'campaign_revision_unknown',
					'The requested immutable campaign revision is not catalogued.',
					409,
					array( 'next_action_code' => 'choose_current_campaign_revision' )
				);
			}
			$campaign = $this->campaigns[ $request['campaign_id'] ][ $request['campaign_version'] ];
		}

		$at = Tra_Vel_Benefit_Taxonomy::utc_datetime( $evaluated_at_utc );
		foreach ( array( $airline, $program, $network, $credential, $portal, $campaign ) as $selected ) {
			if ( null !== $selected ) {
				$current = $this->source_current( $selected['source'], $at );
				if ( is_wp_error( $current ) ) {
					return $current;
				}
			}
		}

		if ( null !== $credential ) {
			$window_state = self::window_state( $credential['effective_window'], $at );
			if ( 'open' !== $window_state ) {
				return $this->error( 'credential_not_current', 'The selected card product is outside its catalogued effective window.', 409, array( 'next_action_code' => 'choose_current_card_product' ) );
			}
			if ( null !== $network && $credential['network_id'] !== $network['payment_network_id'] ) {
				return $this->axis_conflict( 'credential_network_conflict', 'The exact card product does not use the selected payment rail.', 'choose_product_payment_rail' );
			}
		}

		if ( null !== $credential && null !== $program && ! isset( $this->credential_program_links[ $credential['credential_product_id'] . '|' . $program['program_id'] ] ) ) {
			return $this->axis_conflict( 'credential_program_conflict', 'The exact card product is not source-linked to the selected loyalty program.', 'choose_linked_program' );
		}
		if ( null !== $program && null !== $portal && ! isset( $this->program_portal_links[ $program['program_id'] . '|' . $portal['redemption_portal_id'] ] ) ) {
			return $this->axis_conflict( 'program_portal_conflict', 'FlyAll, Matmid, and SKYMAX redemption portals are independent and cannot be interchanged.', 'choose_program_redemption_portal' );
		}
		if ( null !== $airline && null !== $portal && ! isset( $this->portal_inventory_links[ $portal['redemption_portal_id'] . '|' . $airline['airline_inventory_id'] ] ) ) {
			return $this->axis_conflict( 'portal_inventory_scope_unknown', 'The selected portal has no current source-catalogued scope for this airline inventory.', 'check_current_portal_inventory_scope' );
		}

		if ( null !== $campaign ) {
			$state = Tra_Vel_Benefit_Policy::campaign_window_state( $campaign, 'effective', $at );
			if ( is_wp_error( $state ) || 'open' !== $state || 'active' !== $campaign['status'] ) {
				return $this->error( 'campaign_revision_not_current', 'The selected campaign revision is not current at the evaluation time.', 409, array( 'next_action_code' => 'choose_current_campaign_revision' ) );
			}
			if ( null !== $program && ! in_array( $program['program_id'], $campaign['program_ids'], true ) ) {
				return $this->axis_conflict( 'campaign_program_conflict', 'The campaign revision does not belong to the selected loyalty program.', 'choose_matching_campaign_revision' );
			}
			if ( null !== $credential && ! in_array( $credential['credential_product_id'], $campaign['credential_product_ids'], true ) ) {
				return $this->axis_conflict( 'campaign_credential_conflict', 'The campaign revision does not catalogue the selected exact card product.', 'choose_matching_campaign_revision' );
			}
		}

		if ( 'exact_product_customer_asserted' === $claim && null === $credential ) {
			return $this->error( 'exact_product_claim_missing_product', 'An exact-product assertion requires an exact catalogued credential product.' );
		}

		$next_action = $this->smallest_next_action( $request, $program, $credential, $portal, $campaign );
		$digest      = $this->catalog_digest();
		if ( is_wp_error( $digest ) ) {
			return $digest;
		}

		return array(
			'contract_version' => self::CONTRACT_VERSION,
			'catalog_id'       => self::CATALOG_ID,
			'catalog_digest'   => $digest,
			'evaluated_at_utc' => $at,
			'decision_state'   => 'exact_product_customer_asserted' === $claim ? 'likely_customer_asserted' : 'unknown_requires_action',
			'reason_codes'     => array( 'catalog_identity_only', 'live_eligibility_not_verified' ),
			'next_action'      => array(
				'code'        => $next_action[0],
				'target_axis' => $next_action[1],
			),
			'resolved_axes'    => array(
				'airline_inventory_id' => null === $airline ? null : $airline['airline_inventory_id'],
				'program_id'           => null === $program ? null : $program['program_id'],
				'credential_product_id' => null === $credential ? null : $credential['credential_product_id'],
				'payment_network_id'   => null === $network ? null : $network['payment_network_id'],
				'redemption_portal_id' => null === $portal ? null : $portal['redemption_portal_id'],
				'campaign_id'          => null === $campaign ? null : $campaign['campaign_id'],
				'campaign_version'     => null === $campaign ? null : $campaign['version'],
			),
			'commercial_truth' => array(
				'provider_verified_eligibility' => false,
				'live_balance'                  => false,
				'live_inventory'                => false,
				'live_price'                    => false,
				'live_discount'                 => false,
				'live_redemption'               => false,
				'payable_total_changed'         => false,
			),
		);
	}

	private function load_airlines( $records, &$catalog ) {
		if ( ! self::bounded_list( $records, 1, 50 ) ) {
			return $this->fail( 'airlines_invalid', 'The catalogue requires a bounded airline-inventory list.' );
		}
		$normalized = array();
		foreach ( $records as $index => $record ) {
			if ( ! self::exact_object( $record, array( 'airline_inventory_id', 'display_name', 'iata_code', 'source', 'commercial_truth' ) ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['airline_inventory_id'] ) || ! self::plain_text( $record['display_name'], 2, 100 ) || ! is_string( $record['iata_code'] ) || 1 !== preg_match( '/^[A-Z0-9]{2}$/', $record['iata_code'] ) || ! self::false_truth( $record['commercial_truth'], array( 'live_inventory', 'loyalty_eligibility_implied' ) ) ) {
				return $this->fail( 'airline_record_invalid', 'An airline-inventory identity is invalid.', array( 'record_index' => $index ) );
			}
			$source = $this->catalog_source( $record['source'], $catalog );
			if ( is_wp_error( $source ) ) {
				return $source;
			}
			$id = $record['airline_inventory_id'];
			if ( isset( $this->airlines[ $id ] ) ) {
				return $this->fail( 'airline_duplicate', 'An airline-inventory identity appears more than once.', array( 'airline_inventory_id' => $id ) );
			}
			$record['source']      = $source;
			$this->airlines[ $id ] = $record;
			$normalized[]          = $record;
		}
		$catalog['airline_inventory'] = $normalized;
		return true;
	}

	private function load_programs( $records, &$catalog ) {
		if ( ! self::bounded_list( $records, 1, 100 ) ) {
			return $this->fail( 'programs_invalid', 'The catalogue requires a bounded benefit-program list.' );
		}
		$normalized = array();
		foreach ( $records as $index => $record ) {
			$program = Tra_Vel_Benefit_Policy::benefit_program( $record );
			if ( is_wp_error( $program ) ) {
				return $this->fail( 'program_record_invalid', 'A benefit program failed the shared closed policy.', array( 'record_index' => $index, 'policy_code' => $program->get_error_code() ) );
			}
			$source = $this->catalog_source( $program['source'], $catalog );
			if ( is_wp_error( $source ) ) {
				return $source;
			}
			$program['source'] = $source;
			$id = $program['program_id'];
			if ( isset( $this->programs[ $id ] ) ) {
				return $this->fail( 'program_duplicate', 'A benefit program ID appears more than once.', array( 'program_id' => $id ) );
			}
			$this->programs[ $id ] = $program;
			$normalized[]          = $program;
		}
		$catalog['programs'] = $normalized;
		return true;
	}

	private function load_networks( $records, &$catalog ) {
		if ( ! self::bounded_list( $records, 1, 50 ) ) {
			return $this->fail( 'networks_invalid', 'The catalogue requires a bounded payment-network list.' );
		}
		$normalized = array();
		foreach ( $records as $index => $record ) {
			if ( ! self::exact_object( $record, array( 'payment_network_id', 'display_name', 'scope', 'source', 'commercial_truth' ) ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['payment_network_id'] ) || ! self::plain_text( $record['display_name'], 2, 100 ) || ! in_array( $record['scope'], array( 'network_identity_only', 'exact_product_rail', 'exact_dual_rail_product' ), true ) || ! self::false_truth( $record['commercial_truth'], array( 'issuer_eligibility_proven', 'campaign_eligibility_proven' ) ) ) {
				return $this->fail( 'network_record_invalid', 'A payment-network identity is invalid.', array( 'record_index' => $index ) );
			}
			$source = $this->catalog_source( $record['source'], $catalog );
			if ( is_wp_error( $source ) ) {
				return $source;
			}
			$id = $record['payment_network_id'];
			if ( isset( $this->networks[ $id ] ) ) {
				return $this->fail( 'network_duplicate', 'A payment-network identity appears more than once.', array( 'payment_network_id' => $id ) );
			}
			if ( 'network_visa' === $id && 'network_identity_only' !== $record['scope'] ) {
				return $this->fail( 'visa_scope_invalid', 'Visa may be catalogued only as a generic network identity without eligibility.' );
			}
			$record['source']     = $source;
			$this->networks[ $id ] = $record;
			$normalized[]         = $record;
		}
		$catalog['payment_networks'] = $normalized;
		return true;
	}

	private function load_credentials( $records, &$catalog ) {
		if ( ! self::bounded_list( $records, 1, 200 ) ) {
			return $this->fail( 'credentials_invalid', 'The catalogue requires a bounded exact credential-product list.' );
		}
		$normalized = array();
		foreach ( $records as $index => $record ) {
			$credential = Tra_Vel_Benefit_Policy::credential_product( $record );
			if ( is_wp_error( $credential ) ) {
				return $this->fail( 'credential_record_invalid', 'A credential product failed the shared closed policy.', array( 'record_index' => $index, 'policy_code' => $credential->get_error_code() ) );
			}
			$source = $this->catalog_source( $credential['source'], $catalog );
			if ( is_wp_error( $source ) ) {
				return $source;
			}
			$credential['source'] = $source;
			$id = $credential['credential_product_id'];
			if ( isset( $this->credentials[ $id ] ) ) {
				return $this->fail( 'credential_duplicate', 'An exact credential-product ID appears more than once.', array( 'credential_product_id' => $id ) );
			}
			if ( ! isset( $this->networks[ $credential['network_id'] ] ) || 'network_identity_only' === $this->networks[ $credential['network_id'] ]['scope'] ) {
				return $this->fail( 'credential_network_invalid', 'An exact card product must reference an exact catalogued payment rail.', array( 'credential_product_id' => $id ) );
			}
			$this->credentials[ $id ] = $credential;
			$normalized[]             = $credential;
		}
		$catalog['credential_products'] = $normalized;
		return true;
	}

	private function load_portals( $records, &$catalog ) {
		if ( ! self::bounded_list( $records, 1, 50 ) ) {
			return $this->fail( 'portals_invalid', 'The catalogue requires a bounded redemption-portal list.' );
		}
		$normalized = array();
		foreach ( $records as $index => $record ) {
			if ( ! self::exact_object( $record, array( 'redemption_portal_id', 'operator_id', 'display_name', 'source', 'commercial_truth' ) ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['redemption_portal_id'] ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['operator_id'] ) || ! self::plain_text( $record['display_name'], 2, 100 ) || ! self::false_truth( $record['commercial_truth'], array( 'live_quote', 'live_redemption' ) ) ) {
				return $this->fail( 'portal_record_invalid', 'A redemption-portal identity is invalid.', array( 'record_index' => $index ) );
			}
			$source = $this->catalog_source( $record['source'], $catalog );
			if ( is_wp_error( $source ) ) {
				return $source;
			}
			$id = $record['redemption_portal_id'];
			if ( isset( $this->portals[ $id ] ) ) {
				return $this->fail( 'portal_duplicate', 'A redemption-portal identity appears more than once.', array( 'redemption_portal_id' => $id ) );
			}
			$record['source']    = $source;
			$this->portals[ $id ] = $record;
			$normalized[]        = $record;
		}
		$catalog['redemption_portals'] = $normalized;
		return true;
	}

	private function load_campaigns( $records, &$catalog ) {
		if ( ! self::bounded_list( $records, 1, 200 ) ) {
			return $this->fail( 'campaigns_invalid', 'The catalogue requires a bounded immutable campaign-version list.' );
		}
		$normalized = array();
		foreach ( $records as $index => $record ) {
			$campaign = Tra_Vel_Benefit_Policy::campaign_version( $record );
			if ( is_wp_error( $campaign ) ) {
				return $this->fail( 'campaign_record_invalid', 'A campaign version failed the shared closed policy.', array( 'record_index' => $index, 'policy_code' => $campaign->get_error_code() ) );
			}
			$source = $this->catalog_source( $campaign['source'], $catalog );
			if ( is_wp_error( $source ) ) {
				return $source;
			}
			$campaign['source'] = $source;
			$id      = $campaign['campaign_id'];
			$version = $campaign['version'];
			if ( isset( $this->campaigns[ $id ][ $version ] ) ) {
				return $this->fail( 'campaign_revision_conflict', 'A campaign ID and version pair has conflicting duplicate records.', array( 'campaign_id' => $id, 'version' => $version ) );
			}
			foreach ( $campaign['program_ids'] as $program_id ) {
				if ( ! isset( $this->programs[ $program_id ] ) ) {
					return $this->fail( 'campaign_program_unknown', 'A campaign revision references an unknown benefit program.', array( 'campaign_id' => $id, 'program_id' => $program_id ) );
				}
			}
			foreach ( $campaign['credential_product_ids'] as $credential_id ) {
				if ( ! isset( $this->credentials[ $credential_id ] ) ) {
					return $this->fail( 'campaign_credential_unknown', 'A campaign revision references an unknown exact card product.', array( 'campaign_id' => $id, 'credential_product_id' => $credential_id ) );
				}
			}
			if ( ! isset( $this->campaigns[ $id ] ) ) {
				$this->campaigns[ $id ] = array();
			}
			$this->campaigns[ $id ][ $version ] = $campaign;
			$normalized[]                       = $campaign;
		}

		foreach ( $this->campaigns as $campaign_id => &$versions ) {
			ksort( $versions, SORT_NUMERIC );
			$previous = null;
			foreach ( $versions as $version => $campaign ) {
				if ( null !== $previous && $campaign['supersedes_version'] !== $previous ) {
					return $this->fail( 'campaign_lineage_conflict', 'Every later campaign revision must supersede the immediately preceding catalogued revision.', array( 'campaign_id' => $campaign_id, 'version' => $version ) );
				}
				$previous = $version;
			}
		}
		unset( $versions );
		$catalog['campaign_versions'] = $normalized;
		return true;
	}

	private function load_links( &$catalog ) {
		if ( ! self::bounded_list( $catalog['program_portal_links'], 1, 100 ) || ! self::bounded_list( $catalog['credential_program_links'], 1, 300 ) || ! self::bounded_list( $catalog['portal_inventory_links'], 1, 300 ) ) {
			return $this->fail( 'links_invalid', 'Catalogue axis links must be bounded non-empty lists.' );
		}

		foreach ( $catalog['program_portal_links'] as $index => $link ) {
			if ( ! self::exact_object( $link, array( 'program_id', 'redemption_portal_id', 'relationship', 'eligibility_effect' ) ) || 'provider_redemption_channel' !== $link['relationship'] || 'none' !== $link['eligibility_effect'] || ! isset( $this->programs[ $link['program_id'] ], $this->portals[ $link['redemption_portal_id'] ] ) ) {
				return $this->fail( 'program_portal_link_invalid', 'A program-to-portal link is invalid or implies eligibility.', array( 'record_index' => $index ) );
			}
			$key = $link['program_id'] . '|' . $link['redemption_portal_id'];
			if ( isset( $this->program_portal_links[ $key ] ) ) {
				return $this->fail( 'program_portal_link_duplicate', 'A program-to-portal link appears more than once.' );
			}
			$this->program_portal_links[ $key ] = true;
		}

		$linked_credentials = array();
		foreach ( $catalog['credential_program_links'] as $index => $link ) {
			if ( ! self::exact_object( $link, array( 'credential_product_id', 'program_id', 'relationship', 'eligibility_effect' ) ) || 'earns_program_value' !== $link['relationship'] || 'none' !== $link['eligibility_effect'] || ! isset( $this->credentials[ $link['credential_product_id'] ], $this->programs[ $link['program_id'] ] ) ) {
				return $this->fail( 'credential_program_link_invalid', 'A card-to-program link is invalid or implies eligibility.', array( 'record_index' => $index ) );
			}
			$key = $link['credential_product_id'] . '|' . $link['program_id'];
			if ( isset( $this->credential_program_links[ $key ], $linked_credentials[ $link['credential_product_id'] ] ) ) {
				return $this->fail( 'credential_program_link_conflict', 'An exact card product must have one unambiguous program relationship.' );
			}
			$this->credential_program_links[ $key ]       = true;
			$linked_credentials[ $link['credential_product_id'] ] = true;
		}
		if ( count( $linked_credentials ) !== count( $this->credentials ) ) {
			return $this->fail( 'credential_program_coverage_incomplete', 'Every exact card product requires one source-catalogued program relationship.' );
		}

		foreach ( $catalog['portal_inventory_links'] as $index => $link ) {
			if ( ! self::exact_object( $link, array( 'redemption_portal_id', 'airline_inventory_id', 'relationship', 'commercial_truth' ) ) || 'source_catalogued_inventory_scope' !== $link['relationship'] || ! self::false_truth( $link['commercial_truth'], array( 'live_availability', 'airline_loyalty_program_implied' ) ) || ! isset( $this->portals[ $link['redemption_portal_id'] ], $this->airlines[ $link['airline_inventory_id'] ] ) ) {
				return $this->fail( 'portal_inventory_link_invalid', 'A portal inventory-scope link is invalid or conflates inventory with loyalty.', array( 'record_index' => $index ) );
			}
			$key = $link['redemption_portal_id'] . '|' . $link['airline_inventory_id'];
			if ( isset( $this->portal_inventory_links[ $key ] ) ) {
				return $this->fail( 'portal_inventory_link_duplicate', 'A portal inventory-scope link appears more than once.' );
			}
			$this->portal_inventory_links[ $key ] = true;
		}
		return true;
	}

	private function validate_migrations( $records, &$catalog ) {
		if ( ! self::bounded_list( $records, 1, 50 ) ) {
			return $this->fail( 'migrations_invalid', 'The catalogue requires a bounded migration list.' );
		}
		$normalized = array();
		$ids        = array();
		foreach ( $records as $index => $record ) {
			$keys = array( 'migration_id', 'from_issuer_id', 'from_card_family_id', 'from_program_id', 'to_program_id', 'to_credential_product_ids', 'transition_window', 'accrual_cutover_at_utc', 'migration_mode', 'post_cutover_state', 'source', 'commercial_truth' );
			if ( ! self::exact_object( $record, $keys ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['migration_id'] ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['from_issuer_id'] ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['from_card_family_id'] ) || ! isset( $this->programs[ $record['from_program_id'] ], $this->programs[ $record['to_program_id'] ] ) || $record['from_program_id'] === $record['to_program_id'] || 'customer_choice_before_cutover' !== $record['migration_mode'] || 'requires_current_provider_terms' !== $record['post_cutover_state'] || ! self::false_truth( $record['commercial_truth'], array( 'automatic_migration', 'eligibility_proven' ) ) ) {
				return $this->fail( 'migration_record_invalid', 'A migration record is invalid or overstates its commercial effect.', array( 'record_index' => $index ) );
			}
			if ( isset( $ids[ $record['migration_id'] ] ) ) {
				return $this->fail( 'migration_duplicate', 'A migration identity appears more than once.' );
			}
			$targets = self::identifier_list( $record['to_credential_product_ids'], 1, 20 );
			if ( is_wp_error( $targets ) ) {
				return $targets;
			}
			foreach ( $targets as $credential_id ) {
				if ( ! isset( $this->credentials[ $credential_id ], $this->credential_program_links[ $credential_id . '|' . $record['to_program_id'] ] ) ) {
					return $this->fail( 'migration_target_invalid', 'A migration target must be an exact card linked to the target program.', array( 'credential_product_id' => $credential_id ) );
				}
			}
			$window = self::window( $record['transition_window'] );
			$cutover = Tra_Vel_Benefit_Taxonomy::utc_datetime( $record['accrual_cutover_at_utc'] );
			$source = $this->catalog_source( $record['source'], $catalog );
			if ( is_wp_error( $window ) || null === $cutover || is_wp_error( $source ) || ( null !== $window['to_utc'] && strcmp( $window['to_utc'], $cutover ) >= 0 ) ) {
				return $this->fail( 'migration_timing_invalid', 'Migration windows, cutover, and source evidence must be explicit and ordered.' );
			}
			$record['to_credential_product_ids'] = $targets;
			$record['transition_window']         = $window;
			$record['accrual_cutover_at_utc']    = $cutover;
			$record['source']                    = $source;
			$ids[ $record['migration_id'] ]      = true;
			$normalized[]                        = $record;
		}
		$catalog['migrations'] = $normalized;
		return true;
	}

	private function catalog_source( $source, $catalog ) {
		$keys = array( 'authority', 'official_source_url', 'source_content_digest', 'observed_at_utc', 'fresh_until_utc', 'locale', 'review_state' );
		if ( ! self::exact_object( $source, $keys ) || ! in_array( $source['authority'], array( 'official_rules', 'official_product_page' ), true ) || ! self::https_url( $source['official_source_url'] ) || '' === Tra_Vel_Benefit_Taxonomy::digest( $source['source_content_digest'] ) || ! is_string( $source['locale'] ) || 1 !== preg_match( '/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $source['locale'] ) || 'reviewed' !== $source['review_state'] ) {
			return $this->fail( 'source_invalid', 'Every catalogue identity requires reviewed first-party HTTPS source evidence.' );
		}
		$observed = Tra_Vel_Benefit_Taxonomy::utc_datetime( $source['observed_at_utc'] );
		$fresh    = Tra_Vel_Benefit_Taxonomy::utc_datetime( $source['fresh_until_utc'] );
		if ( null === $observed || null === $fresh || strcmp( $observed, $fresh ) > 0 || strcmp( $observed, $catalog['observed_at_utc'] ) > 0 || strcmp( $fresh, $catalog['observed_at_utc'] ) < 0 ) {
			return $this->fail( 'source_freshness_invalid', 'Catalogue source evidence is not current at the catalogue observation time.' );
		}
		$source['observed_at_utc']  = $observed;
		$source['fresh_until_utc'] = $fresh;
		return $source;
	}

	private function source_current( $source, $at ) {
		$state = self::freshness_state( $source['observed_at_utc'], $source['fresh_until_utc'], $at );
		if ( 'current' !== $state ) {
			return $this->error( 'selected_source_' . $state, 'A selected benefit identity has stale or not-yet-observed source evidence.', 409, array( 'next_action_code' => 'refresh_selected_official_source' ) );
		}
		return true;
	}

	private function selected_record( $index, $id, $axis ) {
		if ( null === $id ) {
			return null;
		}
		if ( ! isset( $index[ $id ] ) ) {
			return $this->error( $axis . '_unknown', 'The selected benefit axis identity is not catalogued.', 404, array( 'axis' => $axis, 'next_action_code' => 'choose_catalogued_' . $axis ) );
		}
		return $index[ $id ];
	}

	private function smallest_next_action( $request, $program, $credential, $portal, $campaign ) {
		if ( 'network_visa' === $request['payment_network_id'] && null === $credential ) {
			return array( 'choose_exact_issuer_card_campaign', 'credential_product' );
		}
		if ( null === $credential ) {
			if ( 'campaign_cal_flycard_matmid_transition_2026' === $request['campaign_id'] ) {
				return array( 'choose_exact_cal_fly_card_variant', 'credential_product' );
			}
			if ( null !== $program && 'program_cal_flyall' === $program['program_id'] ) {
				return array( 'choose_exact_flyall_card', 'credential_product' );
			}
			if ( null !== $program && 'program_max_skymax' === $program['program_id'] ) {
				return array( 'check_exact_max_card_with_provider', 'credential_product' );
			}
			if ( null !== $campaign && ! empty( $campaign['credential_product_ids'] ) ) {
				return array( 'choose_exact_campaign_card', 'credential_product' );
			}
		}
		if ( null === $program ) {
			return null !== $credential
				? array( 'choose_linked_program', 'program' )
				: array( 'choose_benefit_program_optional', 'program' );
		}
		if ( null === $portal ) {
			return array( 'choose_program_redemption_portal', 'redemption_portal' );
		}
		if ( null === $campaign ) {
			return array( 'choose_current_campaign_revision', 'campaign_version' );
		}
		if ( 'program_elal_matmid' === $program['program_id'] ) {
			return array( 'connect_matmid_or_enter_planning_balance', 'member_connection' );
		}
		return array( 'verify_eligibility_with_provider', 'provider_verification' );
	}

	private function unknown_claim_error( $suffix, $message, $next_action_code ) {
		return $this->error(
			$suffix,
			$message,
			422,
			array(
				'decision_state'   => 'unknown_requires_action',
				'next_action_code' => $next_action_code,
			)
		);
	}

	private function axis_conflict( $suffix, $message, $next_action_code ) {
		return $this->error(
			$suffix,
			$message,
			409,
			array(
				'decision_state'   => 'unknown_requires_action',
				'next_action_code' => $next_action_code,
			)
		);
	}

	private function campaign_version_count() {
		$count = 0;
		foreach ( $this->campaigns as $versions ) {
			$count += count( $versions );
		}
		return $count;
	}

	private static function freshness_state( $observed, $fresh, $at ) {
		if ( strcmp( $at, $observed ) < 0 ) {
			return 'not_yet_observed';
		}
		return strcmp( $at, $fresh ) <= 0 ? 'current' : 'stale';
	}

	private static function window_state( $window, $at ) {
		if ( null !== $window['from_utc'] && strcmp( $at, $window['from_utc'] ) < 0 ) {
			return 'before';
		}
		if ( null !== $window['to_utc'] && strcmp( $at, $window['to_utc'] ) > 0 ) {
			return 'after';
		}
		return 'open';
	}

	private static function window( $window ) {
		if ( ! self::exact_object( $window, array( 'from_utc', 'to_utc' ) ) ) {
			return new WP_Error( 'tra_vel_israel_benefit_window_invalid', 'A migration window must contain exact from and to fields.', array( 'status' => 400 ) );
		}
		$from = null === $window['from_utc'] ? null : Tra_Vel_Benefit_Taxonomy::utc_datetime( $window['from_utc'] );
		$to   = null === $window['to_utc'] ? null : Tra_Vel_Benefit_Taxonomy::utc_datetime( $window['to_utc'] );
		if ( ( null !== $window['from_utc'] && null === $from ) || ( null !== $window['to_utc'] && null === $to ) || ( null !== $from && null !== $to && strcmp( $from, $to ) > 0 ) ) {
			return new WP_Error( 'tra_vel_israel_benefit_window_invalid', 'A migration window is invalid or reversed.', array( 'status' => 400 ) );
		}
		return array( 'from_utc' => $from, 'to_utc' => $to );
	}

	private static function identifier_list( $values, $minimum, $maximum ) {
		if ( ! self::bounded_list( $values, $minimum, $maximum ) ) {
			return new WP_Error( 'tra_vel_israel_benefit_identifier_list_invalid', 'An exact bounded identifier list is required.', array( 'status' => 400 ) );
		}
		$unique = array();
		foreach ( $values as $value ) {
			if ( '' === Tra_Vel_Benefit_Taxonomy::identifier( $value ) || isset( $unique[ $value ] ) ) {
				return new WP_Error( 'tra_vel_israel_benefit_identifier_list_invalid', 'Identifiers must be exact and unique.', array( 'status' => 400 ) );
			}
			$unique[ $value ] = true;
		}
		$values = array_keys( $unique );
		sort( $values, SORT_STRING );
		return $values;
	}

	private static function https_url( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > 2048 || false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$parts = parse_url( $value );
		return is_array( $parts ) && isset( $parts['scheme'], $parts['host'] ) && 'https' === strtolower( $parts['scheme'] ) && '' !== $parts['host'] && ! isset( $parts['user'] ) && ! isset( $parts['pass'] );
	}

	private static function plain_text( $value, $minimum, $maximum ) {
		return is_string( $value ) && $value === trim( $value ) && strlen( $value ) >= $minimum && strlen( $value ) <= $maximum && 0 === preg_match( '/[\x00-\x1F\x7F]/u', $value );
	}

	private static function false_truth( $truth, $keys ) {
		if ( ! self::exact_object( $truth, $keys ) ) {
			return false;
		}
		foreach ( $keys as $key ) {
			if ( false !== $truth[ $key ] ) {
				return false;
			}
		}
		return true;
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! self::is_list( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function bounded_list( $value, $minimum, $maximum ) {
		return self::is_list( $value ) && count( $value ) >= $minimum && count( $value ) <= $maximum;
	}

	private static function is_list( $value ) {
		return is_array( $value ) && ( empty( $value ) || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( self::is_list( $value ) ) {
			return array_map( array( __CLASS__, 'canonicalize' ), $value );
		}
		ksort( $value, SORT_STRING );
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}
		return $value;
	}

	private function fail( $suffix, $message, $data = array() ) {
		$this->load_result = $this->error( $suffix, $message, 400, $data );
		return $this->load_result;
	}

	private function error( $suffix, $message, $status = 400, $data = array() ) {
		return new WP_Error(
			'tra_vel_israel_benefit_' . $suffix,
			$message,
			array_merge( array( 'status' => (int) $status ), $data )
		);
	}
}
