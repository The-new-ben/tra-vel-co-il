<?php
/**
 * Server-only binding between public commerce selections and private supplier routes.
 *
 * Nothing in this registry dispatches a request, charges a payment method, or
 * changes supplier inventory. It prepares an immutable routing proof that a
 * later executor must pass before it can receive private provider locators.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Private_Routing_Registry {
	const CONTRACT_VERSION  = '1.0.0';
	const MAX_FIXTURE_BYTES = 8388608;
	const MAX_PROFILES      = 100;
	const MAX_OFFERS        = 500;

	/** @var Tra_Vel_Commerce_Sandbox_Catalog|null */
	private $catalog;

	/** @var Tra_Vel_Commerce_Sandbox_Network|null */
	private $network;

	/** @var string */
	private $secret = '';

	/** @var string */
	private $profiles_path = '';

	/** @var array<string,array> */
	private $profiles = array();

	/** @var array<string,array> */
	private $providers = array();

	/** @var array<string,array> */
	private $records = array();

	/** @var string */
	private $network_signature = '';

	/** @var WP_Error|null */
	private $error;

	/**
	 * @param Tra_Vel_Commerce_Sandbox_Catalog      $catalog       Validated private catalog.
	 * @param string|null                            $secret        Same base secret injected into search.
	 * @param Tra_Vel_Commerce_Sandbox_Network|null $network       Reconciled provider network.
	 * @param string|null                            $profiles_path Explicit profile fixture path for tests.
	 */
	public function __construct( $catalog, $secret = null, $network = null, $profiles_path = null ) {
		if ( ! $catalog instanceof Tra_Vel_Commerce_Sandbox_Catalog ) {
			$this->error = $this->error( 'catalog_invalid', 'A validated private commerce catalog is required.', 500 );
			return;
		}
		$catalog_ready = $catalog->readiness();
		if ( is_wp_error( $catalog_ready ) ) {
			$this->error = $catalog_ready;
			return;
		}
		if ( null === $network ) {
			$network = new Tra_Vel_Commerce_Sandbox_Network();
		}
		if ( ! $network instanceof Tra_Vel_Commerce_Sandbox_Network ) {
			$this->error = $this->error( 'network_invalid', 'A reconciled provider network is required.', 500 );
			return;
		}
		if ( null === $secret ) {
			if ( ! function_exists( 'wp_salt' ) ) {
				$this->error = $this->error( 'secret_unavailable', 'The private routing secret is unavailable.', 503 );
				return;
			}
			$secret = (string) wp_salt( 'auth' );
		}
		if ( ! is_string( $secret ) || strlen( $secret ) < 16 ) {
			$this->error = $this->error( 'secret_unavailable', 'The private routing secret is unavailable.', 503 );
			return;
		}

		$this->catalog       = $catalog;
		$this->network       = $network;
		$this->secret        = $secret;
		$this->profiles_path = null === $profiles_path
			? dirname( dirname( __DIR__ ) ) . '/assets/fixtures/commerce-sandbox/supplier-operations-profiles.json'
			: (string) $profiles_path;
	}

	/**
	 * Validate the catalog, network, and complete supplier-profile registry.
	 *
	 * @param int|null $now Positive UTC epoch.
	 * @return true|WP_Error
	 */
	public function readiness( $now = null ) {
		if ( is_wp_error( $this->error ) ) {
			return $this->error;
		}
		$now = null === $now ? time() : $now;
		if ( ! is_int( $now ) || $now < 1 ) {
			return $this->error( 'clock_invalid', 'A positive integer UTC routing clock is required.', 400 );
		}
		$providers = $this->network->all();
		$signature = $this->network->signature();
		if ( is_wp_error( $providers ) || is_wp_error( $signature ) || ! $this->digest( $signature ) ) {
			return is_wp_error( $providers ) ? $providers : ( is_wp_error( $signature ) ? $signature : $this->error( 'network_invalid', 'The provider-network signature is invalid.', 500 ) );
		}
		$this->providers = array();
		foreach ( $providers as $provider ) {
			if ( isset( $this->providers[ $provider['provider_id'] ] ) ) {
				return $this->error( 'provider_duplicate', 'A provider appears more than once in the routing network.', 409 );
			}
			$this->providers[ $provider['provider_id'] ] = $provider;
		}
		$this->network_signature = $signature;
		return $this->load_profiles( $now );
	}

	/**
	 * Bind every selected order item to one immutable server-only route.
	 *
	 * The returned records are private execution inputs. Call public_projection()
	 * when a safe digest-only representation is needed outside this boundary.
	 *
	 * @param array $order   Current authoritative order snapshot.
	 * @param array $offers  Server-owned offer snapshots used by the package.
	 * @param array $context Exact owner scope and integer UTC clock.
	 * @return array|WP_Error
	 */
	public function bind_order( $order, $offers, $context ) {
		$context = $this->context( $context );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$ready = $this->readiness( $context['now'] );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$order = $this->order( $order, $context, true );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$offer_map = $this->offer_map( $offers );
		if ( is_wp_error( $offer_map ) ) {
			return $offer_map;
		}

		$records = array();
		$composite_keys = array();
		foreach ( $order['fulfillment']['items'] as $item ) {
			if ( ! isset( $offer_map[ $item['offer_ref'] ] ) || 1 !== count( $offer_map[ $item['offer_ref'] ] ) ) {
				return $this->error( isset( $offer_map[ $item['offer_ref'] ] ) ? 'offer_ambiguous' : 'offer_missing', 'Every order item must resolve to exactly one server-owned offer snapshot.', 409 );
			}
			$record = $this->bind_item( $order, $item, $offer_map[ $item['offer_ref'] ][0], $context['now'] );
			if ( is_wp_error( $record ) ) {
				return $record;
			}
			$composite = Tra_Vel_Commerce_Policy::canonical_digest(
				array(
					$record['owner_scope_digest'],
					$record['order_ref'],
					$record['order_item_ref'],
					$record['provider_id'],
					$record['provider_reference_digest'],
					$record['offer_digest'],
				)
			);
			if ( isset( $composite_keys[ $composite ] ) ) {
				return $this->error( 'binding_duplicate', 'Two order items produced the same private routing identity.', 409 );
			}
			$composite_keys[ $composite ] = true;
			$this->records[ $record['routing_binding_digest'] ] = $record;
			$records[] = $record;
		}
		return $records;
	}

	/**
	 * Return one server-only record already created by bind_order().
	 *
	 * @return array|WP_Error
	 */
	public function private_record( $routing_binding_digest ) {
		if ( ! $this->digest( $routing_binding_digest ) || ! isset( $this->records[ $routing_binding_digest ] ) ) {
			return $this->error( 'binding_not_found', 'The private routing binding is unavailable.', 404 );
		}
		$record = $this->records[ $routing_binding_digest ];
		return $this->record_valid( $record ) ? $record : $this->error( 'binding_integrity_invalid', 'The private routing binding failed its immutable digest.', 409 );
	}

	/**
	 * Project only safe immutable digests. No route, credential, host, supplier
	 * revision ID, or provider-native locator can cross this boundary.
	 *
	 * @return array|WP_Error
	 */
	public function public_projection( $record ) {
		if ( ! $this->record_valid( $record ) ) {
			return $this->error( 'binding_integrity_invalid', 'The private routing binding failed its immutable digest.', 409 );
		}
		return array(
			'routing_binding_digest'               => $record['routing_binding_digest'],
			'supplier_profile_revision_digest'     => $record['supplier_binding']['profile_content_digest'],
			'product_revision_digest'              => $record['supplier_binding']['source_revisions']['product_revision_digest'],
			'rate_revision_digest'                 => $record['supplier_binding']['source_revisions']['rate_revision_digest'],
			'availability_revision_digest'         => $record['supplier_binding']['source_revisions']['availability_revision_digest'],
			'terms_revision_digest'                => $record['supplier_binding']['source_revisions']['terms_revision_digest'],
			'capability_digest'                    => $record['capability_binding']['capability_digest'],
		);
	}

	/**
	 * Gate a queue-only order-item operation against a private routing binding.
	 *
	 * This method performs no dispatch. It returns only the digest projection an
	 * executor may attach to a private work envelope after all checks pass.
	 *
	 * @return array|WP_Error
	 */
	public function gate_queued_order_item_operation( $operation, $order, $routing_binding_digest, $context ) {
		$context = $this->context( $context );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$record = $this->private_record( $routing_binding_digest );
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		$current = $this->readiness( $context['now'] );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		if ( ! $this->record_is_current( $record ) ) {
			return $this->error( 'binding_revision_stale', 'The catalog, network, adapter, or supplier profile revision changed after route binding.', 409 );
		}
		$order = $this->order( $order, $context, false );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		if ( strtotime( $record['validity']['valid_until'] ) <= $context['now'] ) {
			return $this->error( 'binding_stale', 'The private routing binding expired and must be rebuilt from current revisions.', 409 );
		}
		$operation_keys = array( 'contract_version', 'environment', 'operation_ref', 'order_ref', 'expected_order_version', 'type', 'vertical', 'provider_id', 'target', 'state', 'idempotency_key_digest', 'request_digest', 'scope_digest', 'approval', 'attempt', 'result', 'created_at', 'updated_at', 'dispatched_at', 'completed_at', 'sandbox_truth', 'data_boundary' );
		if ( ! $this->exact_object( $operation, $operation_keys ) || self::CONTRACT_VERSION !== $operation['contract_version'] || 'sandbox' !== $operation['environment'] || 'queued' !== $operation['state'] || null !== $operation['dispatched_at'] || null !== $operation['completed_at'] || ! $this->public_payload_safe( $operation ) ) {
			return $this->error( 'operation_invalid', 'Only an untouched queue-only operation may pass private routing.', 400 );
		}
		if ( ! is_array( $operation['result'] ) || ! isset( $operation['result']['simulated_side_effect_executed'], $operation['result']['real_side_effect_executed'] ) || $operation['result']['simulated_side_effect_executed'] || $operation['result']['real_side_effect_executed'] ) {
			return $this->error( 'operation_side_effect_detected', 'A previously executed operation cannot enter the routing preparation gate.', 409 );
		}
		$target = isset( $operation['target'] ) ? $operation['target'] : null;
		if ( ! $this->exact_object( $target, array( 'kind', 'ref', 'version', 'target_digest' ) ) || 'order_item' !== $target['kind'] ) {
			return $this->error( 'operation_target_invalid', 'The private routing gate accepts order-item operations only.', 400 );
		}
		if ( $operation['order_ref'] !== $record['order_ref'] || $operation['expected_order_version'] !== $record['order_version'] || $operation['provider_id'] !== $record['provider_id'] || $operation['vertical'] !== $record['vertical'] || $target['ref'] !== $record['order_item_ref'] || $target['version'] !== $record['order_version'] || $order['order_ref'] !== $record['order_ref'] || $order['order_digest'] !== $record['order_digest'] || $order['version'] !== $record['order_version'] || $order['owner_scope_digest'] !== $record['owner_scope_digest'] ) {
			return $this->error( 'operation_binding_mismatch', 'The queued operation, order revision, and private route do not share one identity.', 409 );
		}
		$item = $this->order_item( $order, $record['order_item_ref'] );
		if ( is_wp_error( $item ) || ! hash_equals( $target['target_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $item ) ) || $item['offer_digest'] !== $record['offer_digest'] ) {
			return $this->error( 'operation_item_mismatch', 'The queued operation target does not match the routed order-item snapshot.', 409 );
		}
		$capability = $this->operation_capability( $operation['type'] );
		if ( '' === $capability || ! in_array( $capability, $record['capability_binding']['frozen_capabilities'], true ) ) {
			return $this->error( 'operation_capability_not_frozen', 'The selected product did not freeze authority for this operation capability.', 409 );
		}
		return $this->public_projection( $record );
	}

	private function bind_item( $order, $item, $offer, $now ) {
		$offer = $this->offer( $offer, $item, $now );
		if ( is_wp_error( $offer ) ) {
			return $offer;
		}
		$product = $this->catalog->resolve_private_product( $item['provider_id'], $item['provider_reference_digest'], $this->secret );
		if ( is_wp_error( $product ) ) {
			return $product;
		}
		if ( $product['vertical'] !== $item['vertical'] || ! hash_equals( Tra_Vel_Commerce_Policy::canonical_digest( $product['terms'] ), $offer['terms']['terms_digest'] ) || $product['capabilities'] !== $offer['capabilities'] ) {
			return $this->error( 'product_offer_mismatch', 'The private catalog row no longer matches the immutable public offer snapshot.', 409 );
		}
		if ( ! isset( $this->providers[ $item['provider_id'] ], $this->profiles[ $item['provider_id'] ] ) ) {
			return $this->error( 'supplier_missing', 'The selected provider has no unique validated supplier profile.', 409 );
		}
		$provider = $this->providers[ $item['provider_id'] ];
		$profile  = $this->profiles[ $item['provider_id'] ];
		if ( ! in_array( $item['vertical'], $provider['verticals'], true ) || ! in_array( $item['vertical'], $profile['verticals'], true ) || $offer['evidence']['adapter_version'] !== $provider['adapter_version'] . '-sandbox' ) {
			return $this->error( 'supplier_adapter_mismatch', 'The offer, provider network, and supplier profile do not share one adapter route.', 409 );
		}

		$frozen_capabilities = $product['capabilities'];
		sort( $frozen_capabilities, SORT_STRING );
		$required_supplier_capabilities = array();
		foreach ( $frozen_capabilities as $capability ) {
			$required_supplier_capabilities[] = 'affiliate_handoff' === $capability ? 'report_conversion' : $capability;
		}
		$required_supplier_capabilities = array_values( array_unique( $required_supplier_capabilities ) );
		sort( $required_supplier_capabilities, SORT_STRING );
		$profile_capabilities = array();
		foreach ( $profile['capability_claims'] as $claim ) {
			if ( $item['vertical'] === $claim['vertical'] ) {
				$profile_capabilities[] = $claim['capability'];
			}
		}
		$profile_capabilities = array_values( array_unique( $profile_capabilities ) );
		sort( $profile_capabilities, SORT_STRING );
		if ( array_diff( $required_supplier_capabilities, $profile_capabilities ) || array_diff( $required_supplier_capabilities, $provider['capabilities'] ) ) {
			return $this->error( 'product_capability_unavailable', 'The product capability set exceeds the provider or supplier revision.', 409 );
		}

		$credential = $this->credential( $profile, $required_supplier_capabilities, $now );
		if ( is_wp_error( $credential ) ) {
			return $credential;
		}
		$endpoint = $this->endpoint( $profile );
		if ( is_wp_error( $endpoint ) ) {
			return $endpoint;
		}
		$operation_routes = $this->operation_routes( $profile, $frozen_capabilities );
		if ( is_wp_error( $operation_routes ) ) {
			return $operation_routes;
		}

		$service_valid_until = $product['service_window']['available_until'] . 'T23:59:59Z';
		$validity_candidates = array(
			$offer['availability']['fresh_until'],
			$order['expires_at'],
			$profile['source_controls']['terms_valid_until'],
			$credential['expires_at'],
			$service_valid_until,
		);
		foreach ( $profile['capability_claims'] as $claim ) {
			if ( $item['vertical'] === $claim['vertical'] && in_array( $claim['capability'], $required_supplier_capabilities, true ) && null !== $claim['expires_at'] ) {
				$validity_candidates[] = $claim['expires_at'];
			}
		}
		$valid_until_timestamp = null;
		foreach ( $validity_candidates as $candidate ) {
			$timestamp = $this->utc_timestamp( $candidate );
			if ( null === $timestamp ) {
				return $this->error( 'validity_invalid', 'A private routing validity boundary is malformed.', 409 );
			}
			$valid_until_timestamp = null === $valid_until_timestamp ? $timestamp : min( $valid_until_timestamp, $timestamp );
		}
		if ( $valid_until_timestamp <= $now ) {
			return $this->error( 'binding_stale', 'The offer, credential, service, order, or supplier revision is already stale.', 409 );
		}

		$binding_parts = array( $order['owner_scope_digest'], $order['order_ref'], $item['order_item_ref'], $item['provider_id'], $item['provider_reference_digest'], $item['offer_digest'] );
		$record = array(
			'contract_version'          => self::CONTRACT_VERSION,
			'environment'               => 'sandbox',
			'routing_binding_ref'       => $this->private_reference( 'binding', $binding_parts ),
			'routing_binding_digest'    => '',
			'owner_scope_digest'        => $order['owner_scope_digest'],
			'order_ref'                 => $order['order_ref'],
			'order_digest'              => $order['order_digest'],
			'order_version'             => $order['version'],
			'order_item_ref'            => $item['order_item_ref'],
			'component_ref'             => $item['component_ref'],
			'provider_id'               => $item['provider_id'],
			'vertical'                  => $item['vertical'],
			'provider_reference_digest' => $item['provider_reference_digest'],
			'offer_ref'                 => $item['offer_ref'],
			'offer_version'             => $item['offer_version'],
			'offer_digest'              => $item['offer_digest'],
			'catalog_binding'           => array(
				'catalog_digest'        => $this->catalog->catalog_digest(),
				'private_product_ref'   => $product['private_product_ref'],
				'private_product_digest'=> Tra_Vel_Commerce_Policy::canonical_digest( $product ),
				'service_valid_until'   => $service_valid_until,
			),
			'supplier_binding'          => array(
				'network_signature'       => $this->network_signature,
				'profile_revision_id'     => $profile['revision_id'],
				'profile_revision_number' => $profile['revision_number'],
				'profile_content_digest'  => $profile['revision_control']['content_digest'],
				'adapter_version'         => $provider['adapter_version'],
				'source_revisions'        => array(
					'product_revision_digest'      => $profile['source_controls']['product_revision_digest'],
					'rate_revision_digest'         => $profile['source_controls']['rate_revision_digest'],
					'availability_revision_digest' => $profile['source_controls']['availability_revision_digest'],
					'terms_revision_digest'        => $profile['source_controls']['terms_revision_digest'],
				),
			),
			'capability_binding'        => array(
				'frozen_capabilities' => $frozen_capabilities,
				'capability_digest'   => Tra_Vel_Commerce_Policy::canonical_digest( $frozen_capabilities ),
			),
			'private_route'             => array(
				'credential_ref'          => $credential['credential_ref'],
				'endpoint_route_ref'      => $this->private_reference( 'endpoint', array( $profile['revision_id'], $endpoint['host'], $profile['endpoints']['certificate_evidence_digest'] ) ),
				'endpoint_host'           => $endpoint['host'],
				'endpoint_evidence_digest'=> $profile['endpoints']['certificate_evidence_digest'],
				'tls_required'            => true,
				'redirect_policy'         => 'deny',
				'operation_route_refs'    => $operation_routes,
			),
			'validity'                  => array(
				'offer_fresh_until'          => $offer['availability']['fresh_until'],
				'order_expires_at'           => $order['expires_at'],
				'supplier_terms_valid_until' => $profile['source_controls']['terms_valid_until'],
				'credential_expires_at'      => $credential['expires_at'],
				'service_valid_until'         => $service_valid_until,
				'valid_until'                 => gmdate( 'Y-m-d\TH:i:s\Z', $valid_until_timestamp ),
			),
			'created_at'                 => gmdate( 'Y-m-d\TH:i:s\Z', $now ),
			'private_boundary'           => array(
				'server_only'                       => true,
				'public_serialization_allowed'      => false,
				'raw_credentials_stored'            => false,
				'vault_pointers_only'                => true,
				'contains_private_provider_locator' => true,
			),
		);
		$basis = $record;
		unset( $basis['routing_binding_digest'] );
		$record['routing_binding_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $basis );
		return $this->record_valid( $record ) ? $record : $this->error( 'binding_creation_failed', 'The private routing record failed its closed contract.', 500 );
	}

	private function load_profiles( $now ) {
		if ( ! is_string( $this->profiles_path ) || '' === trim( $this->profiles_path ) || ! is_file( $this->profiles_path ) || ! is_readable( $this->profiles_path ) ) {
			return $this->error( 'profiles_unavailable', 'The supplier routing profile fixture is unavailable.', 503 );
		}
		$size = filesize( $this->profiles_path );
		if ( false === $size || $size < 2 || $size > self::MAX_FIXTURE_BYTES ) {
			return $this->error( 'profiles_size_invalid', 'The supplier routing profile fixture has an unsafe size.', 500 );
		}
		$raw = file_get_contents( $this->profiles_path );
		if ( false === $raw || strlen( $raw ) !== $size ) {
			return $this->error( 'profiles_read_failed', 'The supplier routing profile fixture could not be read completely.', 500 );
		}
		$fixture = json_decode( $raw, true );
		$root_keys = array( 'contract_version', 'fixture_id', 'environment', 'network_id', 'network_signature', 'simulated', 'profiles' );
		if ( JSON_ERROR_NONE !== json_last_error() || ! $this->exact_object( $fixture, $root_keys ) || self::CONTRACT_VERSION !== $fixture['contract_version'] || 'supplier_operations_profiles_v1' !== $fixture['fixture_id'] || 'sandbox' !== $fixture['environment'] || Tra_Vel_Commerce_Sandbox_Network::NETWORK_ID !== $fixture['network_id'] || ! hash_equals( $this->network_signature, (string) $fixture['network_signature'] ) || true !== $fixture['simulated'] || ! $this->is_list( $fixture['profiles'] ) || ! $fixture['profiles'] || count( $fixture['profiles'] ) > self::MAX_PROFILES ) {
			return $this->error( 'profiles_fixture_invalid', 'The supplier routing profile fixture is not the closed reconciled sandbox registry.', 500 );
		}

		$profiles = array();
		foreach ( $fixture['profiles'] as $candidate ) {
			$profile = Tra_Vel_Supplier_Operations_Policy::supplier_profile( $candidate, $now );
			if ( is_wp_error( $profile ) ) {
				return $profile;
			}
			$id = $profile['supplier_id'];
			if ( isset( $profiles[ $id ] ) ) {
				return $this->error( 'profile_duplicate', 'A provider has more than one active supplier profile revision.', 409 );
			}
			if ( ! isset( $this->providers[ $id ] ) || ! $this->profile_matches_provider( $profile, $this->providers[ $id ] ) ) {
				return $this->error( 'profile_network_mismatch', 'A supplier profile does not match its provider-network descriptor.', 409 );
			}
			$profiles[ $id ] = $profile;
		}
		if ( count( $profiles ) !== count( $this->providers ) || array_diff( array_keys( $this->providers ), array_keys( $profiles ) ) || array_diff( array_keys( $profiles ), array_keys( $this->providers ) ) ) {
			return $this->error( 'profile_coverage_invalid', 'Supplier profiles and provider descriptors must have one-to-one coverage.', 409 );
		}
		$this->profiles = $profiles;
		return true;
	}

	private function profile_matches_provider( $profile, $provider ) {
		$profile_verticals = $profile['verticals'];
		$provider_verticals = $provider['verticals'];
		sort( $profile_verticals, SORT_STRING );
		sort( $provider_verticals, SORT_STRING );
		$profile_capabilities = array();
		foreach ( $profile['capability_claims'] as $claim ) {
			$profile_capabilities[] = $claim['capability'];
		}
		$profile_capabilities = array_values( array_unique( $profile_capabilities ) );
		$provider_capabilities = $provider['capabilities'];
		sort( $profile_capabilities, SORT_STRING );
		sort( $provider_capabilities, SORT_STRING );
		$commission_matches = in_array( $provider['settlement']['model'], array( 'owned', 'net_rate' ), true )
			? 0 === $provider['settlement']['commission_bps'] && null === $profile['settlement']['commission_bps']
			: $profile['settlement']['commission_bps'] === $provider['settlement']['commission_bps'];
		return $profile['environment'] === $provider['environment']
			&& $profile['relationship']['model'] === $provider['relationship']
			&& $profile_verticals === $provider_verticals
			&& $profile_capabilities === $provider_capabilities
			&& $profile['settlement']['model'] === $provider['settlement']['model']
			&& $profile['settlement']['currency'] === $provider['settlement']['currency']
			&& $commission_matches
			&& $profile['settlement']['payout_lag_days'] === $provider['settlement']['payout_lag_days']
			&& 'ready' === $provider['readiness']['status']
			&& 'sandbox_active' === $profile['lifecycle_status']
			&& 'sandbox_ready' === $profile['readiness']['decision']
			&& 'healthy' === $profile['health']['state']
			&& 'armed' === $profile['kill_switch']['state'];
	}

	private function context( $context ) {
		if ( ! $this->exact_object( $context, array( 'owner_scope_digest', 'now' ) ) || ! $this->digest( $context['owner_scope_digest'] ) || ! is_int( $context['now'] ) || $context['now'] < 1 ) {
			return $this->error( 'context_invalid', 'An exact owner digest and positive integer UTC clock are required.', 400 );
		}
		return $context;
	}

	private function order( $order, $context, $require_fresh ) {
		$keys = array( 'contract_version', 'environment', 'order_ref', 'version', 'owner_scope_digest', 'idempotency_key_digest', 'order_digest', 'selection', 'overall_state', 'checkout', 'payment', 'fulfillment', 'settlement', 'approval', 'pricing', 'last_event_sequence', 'created_at', 'updated_at', 'expires_at', 'sandbox_truth', 'data_boundary' );
		if ( ! $this->exact_object( $order, $keys ) || self::CONTRACT_VERSION !== $order['contract_version'] || 'sandbox' !== $order['environment'] || ! $this->ref( $order['order_ref'], 'order' ) || ! is_int( $order['version'] ) || $order['version'] < 1 || ! $this->digest( $order['owner_scope_digest'] ) || ! hash_equals( $order['owner_scope_digest'], $context['owner_scope_digest'] ) || ! $this->digest( $order['order_digest'] ) || ! $this->public_payload_safe( $order ) ) {
			return $this->error( 'order_invalid', 'The private route requires the exact owner-bound public order contract.', 400 );
		}
		$basis = $order;
		unset( $basis['order_digest'] );
		if ( ! hash_equals( $order['order_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $basis ) ) ) {
			return $this->error( 'order_digest_invalid', 'The public order changed after its immutable digest was issued.', 409 );
		}
		if ( ! isset( $order['checkout']['state'] ) || 'ready' !== $order['checkout']['state'] || ! isset( $order['fulfillment']['items'] ) || ! $this->is_list( $order['fulfillment']['items'] ) || ! $order['fulfillment']['items'] || count( $order['fulfillment']['items'] ) > 32 ) {
			return $this->error( 'order_not_routable', 'The order is not ready or has no bounded fulfillment items.', 409 );
		}
		$expires = $this->utc_timestamp( $order['expires_at'] );
		if ( null === $expires || ( $require_fresh && $expires <= $context['now'] ) ) {
			return $this->error( 'order_stale', 'The order routing window is invalid or expired.', 409 );
		}
		$seen = array();
		foreach ( $order['fulfillment']['items'] as $index => $item ) {
			$item_keys = array( 'order_item_ref', 'component_ref', 'role', 'required', 'sequence', 'vertical', 'provider_id', 'provider_reference_digest', 'offer_ref', 'offer_version', 'offer_digest', 'state', 'latest_operation_ref', 'receipt_digest' );
			if ( ! $this->exact_object( $item, $item_keys ) || ! $this->ref( $item['order_item_ref'], 'order_item' ) || ! $this->ref( $item['component_ref'], 'component' ) || ! $this->ref( $item['offer_ref'], 'offer' ) || ! is_int( $item['offer_version'] ) || $item['offer_version'] < 1 || ! $this->digest( $item['offer_digest'] ) || ! $this->digest( $item['provider_reference_digest'] ) || ! isset( $this->providers[ $item['provider_id'] ] ) || '' === Tra_Vel_Commerce_Taxonomy::vertical( $item['vertical'] ) || $item['sequence'] !== $index + 1 || isset( $seen[ $item['order_item_ref'] ] ) ) {
				return $this->error( 'order_item_invalid', 'An order item is malformed, duplicated, or lacks its offer digest.', 400 );
			}
			$seen[ $item['order_item_ref'] ] = true;
		}
		return $order;
	}

	private function offer_map( $offers ) {
		if ( ! $this->is_list( $offers ) || ! $offers || count( $offers ) > self::MAX_OFFERS ) {
			return $this->error( 'offers_invalid', 'A bounded server-owned offer list is required.', 400 );
		}
		$map = array();
		foreach ( $offers as $offer ) {
			if ( ! is_array( $offer ) || ! isset( $offer['offer_ref'] ) || ! $this->ref( $offer['offer_ref'], 'offer' ) || ! $this->public_payload_safe( $offer ) ) {
				return $this->error( 'offers_invalid', 'The offer list contains a malformed or private projection.', 400 );
			}
			if ( ! isset( $map[ $offer['offer_ref'] ] ) ) {
				$map[ $offer['offer_ref'] ] = array();
			}
			$map[ $offer['offer_ref'] ][] = $offer;
		}
		return $map;
	}

	private function offer( $offer, $item, $now ) {
		$keys = array( 'contract_version', 'environment', 'offer_ref', 'version', 'search_session_ref', 'provider_id', 'provider_reference_digest', 'vertical', 'status', 'product', 'geometry', 'pricing', 'availability', 'terms', 'capabilities', 'ranking', 'evidence', 'sandbox_truth', 'data_boundary' );
		if ( ! $this->exact_object( $offer, $keys ) || self::CONTRACT_VERSION !== $offer['contract_version'] || 'sandbox' !== $offer['environment'] || $offer['offer_ref'] !== $item['offer_ref'] || $offer['version'] !== $item['offer_version'] || $offer['provider_id'] !== $item['provider_id'] || $offer['vertical'] !== $item['vertical'] || ! hash_equals( $offer['provider_reference_digest'], $item['provider_reference_digest'] ) || ! in_array( $offer['status'], array( 'available', 'limited' ), true ) ) {
			return $this->error( 'offer_mismatch', 'The selected offer does not match its order item.', 409 );
		}
		if ( ! hash_equals( $item['offer_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $offer ) ) ) {
			return $this->error( 'offer_digest_invalid', 'The selected offer changed after its digest was frozen.', 409 );
		}
		if ( ! $this->exact_object( $offer['availability'], array( 'state', 'quantity_remaining', 'checked_at', 'fresh_until' ) ) || ! $this->exact_object( $offer['terms'], array( 'terms_digest', 'cancellation', 'changes', 'inclusions', 'requires_revalidation' ) ) || ! $this->exact_object( $offer['evidence'], array( 'adapter_version', 'evidence_digest', 'retrieved_at', 'fresh_until' ) ) || ! $this->digest( $offer['terms']['terms_digest'] ) || ! $this->digest( $offer['evidence']['evidence_digest'] ) || $offer['availability']['fresh_until'] !== $offer['evidence']['fresh_until'] ) {
			return $this->error( 'offer_evidence_invalid', 'The selected offer lacks exact freshness, terms, or adapter evidence.', 409 );
		}
		$fresh_until = $this->utc_timestamp( $offer['availability']['fresh_until'] );
		if ( null === $fresh_until || $fresh_until <= $now ) {
			return $this->error( 'offer_stale', 'The selected offer expired before private routing.', 409 );
		}
		return $offer;
	}

	private function credential( $profile, $required_capabilities, $now ) {
		$matches = array();
		foreach ( $profile['credentials'] as $credential ) {
			$expires = $this->utc_timestamp( $credential['expires_at'] );
			if ( 'configured' === $credential['status'] && 'sandbox' === $credential['environment'] && null !== $expires && $expires > $now && ! array_diff( $required_capabilities, $credential['scopes'] ) ) {
				$matches[] = $credential;
			}
		}
		if ( ! $matches ) {
			return $this->error( 'credential_missing', 'No current vault credential pointer covers the frozen product capabilities.', 409 );
		}
		if ( 1 !== count( $matches ) ) {
			return $this->error( 'credential_ambiguous', 'More than one vault credential route covers the same frozen capability set.', 409 );
		}
		return $matches[0];
	}

	private function endpoint( $profile ) {
		$hosts = $profile['endpoints']['allowed_hosts'];
		if ( ! $this->is_list( $hosts ) || 1 !== count( $hosts ) || ! is_string( $hosts[0] ) || '.invalid' !== substr( $hosts[0], -8 ) || true !== $profile['endpoints']['tls_required'] || 'deny' !== $profile['endpoints']['redirect_policy'] ) {
			return $this->error( 'endpoint_ambiguous', 'Sandbox private routing requires one TLS-only, no-redirect simulator endpoint.', 409 );
		}
		return array( 'host' => $hosts[0] );
	}

	private function operation_routes( $profile, $capabilities ) {
		$routes = array();
		foreach ( $capabilities as $capability ) {
			$lane = $this->capability_lane( $capability );
			if ( '' === $lane || ! isset( $profile['operation_support'][ $lane ] ) || true !== $profile['operation_support'][ $lane ]['supported'] ) {
				return $this->error( 'operation_route_missing', 'A frozen product capability has no supported supplier operation route.', 409 );
			}
			$routes[] = array(
				'capability'             => $capability,
				'primary_route_ref'      => $profile['operation_support'][ $lane ]['contact_route_ref'],
				'after_hours_route_ref'  => $profile['operation_support'][ $lane ]['after_hours_route_ref'],
			);
		}
		usort( $routes, static function ( $left, $right ) { return strcmp( $left['capability'], $right['capability'] ); } );
		return $routes;
	}

	private function capability_lane( $capability ) {
		$map = array(
			'search'               => 'search',
			'revalidate'           => 'search',
			'reserve'              => 'reservation',
			'confirm'              => 'confirmation',
			'fulfill'              => 'fulfillment',
			'change'               => 'change',
			'cancel'               => 'cancel',
			'refund'               => 'refund',
			'payment_authorize'    => 'settlement',
			'payment_capture'      => 'settlement',
			'payment_void'         => 'settlement',
			'payment_refund'       => 'refund',
			'webhook'              => 'webhook',
			'reconcile'            => 'reconciliation',
			'report_conversion'    => 'settlement',
			'settlement_reconcile' => 'settlement',
			'affiliate_handoff'    => 'settlement',
		);
		return isset( $map[ $capability ] ) ? $map[ $capability ] : '';
	}

	private function operation_capability( $type ) {
		$map = array(
			'reserve'                => 'reserve',
			'confirm'                => 'confirm',
			'fulfill'                => 'fulfill',
			'change'                 => 'change',
			'cancel'                 => 'cancel',
			'refund'                 => 'refund',
			'record_affiliate_click' => 'affiliate_handoff',
			'report_conversion'      => 'affiliate_handoff',
			'ingest_webhook'         => 'webhook',
			'reconcile'              => 'reconcile',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : '';
	}

	private function order_item( $order, $order_item_ref ) {
		$matches = array();
		foreach ( $order['fulfillment']['items'] as $item ) {
			if ( $item['order_item_ref'] === $order_item_ref ) {
				$matches[] = $item;
			}
		}
		return 1 === count( $matches ) ? $matches[0] : $this->error( 'order_item_not_unique', 'The routed order item is missing or duplicated.', 409 );
	}

	private function record_valid( $record ) {
		$keys = array( 'contract_version', 'environment', 'routing_binding_ref', 'routing_binding_digest', 'owner_scope_digest', 'order_ref', 'order_digest', 'order_version', 'order_item_ref', 'component_ref', 'provider_id', 'vertical', 'provider_reference_digest', 'offer_ref', 'offer_version', 'offer_digest', 'catalog_binding', 'supplier_binding', 'capability_binding', 'private_route', 'validity', 'created_at', 'private_boundary' );
		if ( ! $this->exact_object( $record, $keys ) || self::CONTRACT_VERSION !== $record['contract_version'] || 'sandbox' !== $record['environment'] || ! $this->digest( $record['routing_binding_digest'] ) || ! $this->private_ref( $record['routing_binding_ref'], 'binding' ) || ! $this->digest( $record['owner_scope_digest'] ) || ! $this->ref( $record['order_ref'], 'order' ) || ! $this->digest( $record['order_digest'] ) || ! is_int( $record['order_version'] ) || $record['order_version'] < 1 || ! $this->ref( $record['order_item_ref'], 'order_item' ) || ! $this->ref( $record['component_ref'], 'component' ) || ! is_string( $record['provider_id'] ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $record['provider_id'] ) || '' === Tra_Vel_Commerce_Taxonomy::vertical( $record['vertical'] ) || ! $this->ref( $record['offer_ref'], 'offer' ) || ! is_int( $record['offer_version'] ) || $record['offer_version'] < 1 || ! $this->digest( $record['provider_reference_digest'] ) || ! $this->digest( $record['offer_digest'] ) ) {
			return false;
		}
		$expected_binding_ref = $this->private_reference(
			'binding',
			array( $record['owner_scope_digest'], $record['order_ref'], $record['order_item_ref'], $record['provider_id'], $record['provider_reference_digest'], $record['offer_digest'] )
		);
		if ( ! hash_equals( $record['routing_binding_ref'], $expected_binding_ref ) ) {
			return false;
		}
		if ( ! $this->exact_object( $record['catalog_binding'], array( 'catalog_digest', 'private_product_ref', 'private_product_digest', 'service_valid_until' ) ) || ! preg_match( '/^px_[a-z0-9_]{8,90}$/', $record['catalog_binding']['private_product_ref'] ) || ! $this->digest( $record['catalog_binding']['catalog_digest'] ) || ! $this->digest( $record['catalog_binding']['private_product_digest'] ) ) {
			return false;
		}
		if ( ! $this->exact_object( $record['supplier_binding'], array( 'network_signature', 'profile_revision_id', 'profile_revision_number', 'profile_content_digest', 'adapter_version', 'source_revisions' ) ) || ! $this->digest( $record['supplier_binding']['network_signature'] ) || ! preg_match( '/^suprev_[a-z0-9]{12,64}$/', $record['supplier_binding']['profile_revision_id'] ) || ! is_int( $record['supplier_binding']['profile_revision_number'] ) || $record['supplier_binding']['profile_revision_number'] < 1 || ! $this->digest( $record['supplier_binding']['profile_content_digest'] ) || ! is_string( $record['supplier_binding']['adapter_version'] ) || 1 !== preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/', $record['supplier_binding']['adapter_version'] ) ) {
			return false;
		}
		$revision_keys = array( 'product_revision_digest', 'rate_revision_digest', 'availability_revision_digest', 'terms_revision_digest' );
		if ( ! $this->exact_object( $record['supplier_binding']['source_revisions'], $revision_keys ) ) {
			return false;
		}
		foreach ( $revision_keys as $key ) {
			if ( ! $this->digest( $record['supplier_binding']['source_revisions'][ $key ] ) ) {
				return false;
			}
		}
		if ( ! $this->exact_object( $record['capability_binding'], array( 'frozen_capabilities', 'capability_digest' ) ) || ! $this->is_list( $record['capability_binding']['frozen_capabilities'] ) || ! $record['capability_binding']['frozen_capabilities'] || count( $record['capability_binding']['frozen_capabilities'] ) !== count( array_unique( $record['capability_binding']['frozen_capabilities'] ) ) || ! $this->digest( $record['capability_binding']['capability_digest'] ) || ! hash_equals( $record['capability_binding']['capability_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $record['capability_binding']['frozen_capabilities'] ) ) ) {
			return false;
		}
		$sorted_capabilities = $record['capability_binding']['frozen_capabilities'];
		sort( $sorted_capabilities, SORT_STRING );
		if ( $sorted_capabilities !== $record['capability_binding']['frozen_capabilities'] ) {
			return false;
		}
		$route_keys = array( 'credential_ref', 'endpoint_route_ref', 'endpoint_host', 'endpoint_evidence_digest', 'tls_required', 'redirect_policy', 'operation_route_refs' );
		if ( ! $this->exact_object( $record['private_route'], $route_keys ) || ! preg_match( '/^credref_[a-z0-9_]{8,120}$/', $record['private_route']['credential_ref'] ) || ! $this->private_ref( $record['private_route']['endpoint_route_ref'], 'endpoint' ) || '.invalid' !== substr( $record['private_route']['endpoint_host'], -8 ) || ! $this->digest( $record['private_route']['endpoint_evidence_digest'] ) || true !== $record['private_route']['tls_required'] || 'deny' !== $record['private_route']['redirect_policy'] || ! $this->is_list( $record['private_route']['operation_route_refs'] ) || ! $record['private_route']['operation_route_refs'] ) {
			return false;
		}
		$route_capabilities = array();
		foreach ( $record['private_route']['operation_route_refs'] as $route ) {
			if ( ! $this->exact_object( $route, array( 'capability', 'primary_route_ref', 'after_hours_route_ref' ) ) || ! is_string( $route['primary_route_ref'] ) || ! is_string( $route['after_hours_route_ref'] ) || 1 !== preg_match( '/^route_[a-z0-9_]{8,160}$/', $route['primary_route_ref'] ) || 1 !== preg_match( '/^route_[a-z0-9_]{8,160}$/', $route['after_hours_route_ref'] ) ) {
				return false;
			}
			$route_capabilities[] = $route['capability'];
		}
		sort( $route_capabilities, SORT_STRING );
		if ( $route_capabilities !== $sorted_capabilities ) {
			return false;
		}
		$validity_keys = array( 'offer_fresh_until', 'order_expires_at', 'supplier_terms_valid_until', 'credential_expires_at', 'service_valid_until', 'valid_until' );
		if ( ! $this->exact_object( $record['validity'], $validity_keys ) ) {
			return false;
		}
		foreach ( $validity_keys as $key ) {
			if ( null === $this->utc_timestamp( $record['validity'][ $key ] ) ) {
				return false;
			}
		}
		$valid_until = $this->utc_timestamp( $record['validity']['valid_until'] );
		foreach ( array( 'offer_fresh_until', 'order_expires_at', 'supplier_terms_valid_until', 'credential_expires_at', 'service_valid_until' ) as $upper_bound ) {
			if ( $valid_until > $this->utc_timestamp( $record['validity'][ $upper_bound ] ) ) {
				return false;
			}
		}
		$boundary = array( 'server_only' => true, 'public_serialization_allowed' => false, 'raw_credentials_stored' => false, 'vault_pointers_only' => true, 'contains_private_provider_locator' => true );
		if ( $record['private_boundary'] !== $boundary ) {
			return false;
		}
		if ( null === $this->utc_timestamp( $record['created_at'] ) ) {
			return false;
		}
		$basis = $record;
		unset( $basis['routing_binding_digest'] );
		return hash_equals( $record['routing_binding_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $basis ) );
	}

	private function record_is_current( $record ) {
		if ( ! isset( $this->profiles[ $record['provider_id'] ], $this->providers[ $record['provider_id'] ] ) ) {
			return false;
		}
		$profile = $this->profiles[ $record['provider_id'] ];
		$provider = $this->providers[ $record['provider_id'] ];
		$source = $record['supplier_binding']['source_revisions'];
		if ( $record['catalog_binding']['catalog_digest'] !== $this->catalog->catalog_digest()
			|| $record['supplier_binding']['network_signature'] !== $this->network_signature
			|| $record['supplier_binding']['profile_revision_id'] !== $profile['revision_id']
			|| $record['supplier_binding']['profile_revision_number'] !== $profile['revision_number']
			|| $record['supplier_binding']['profile_content_digest'] !== $profile['revision_control']['content_digest']
			|| $record['supplier_binding']['adapter_version'] !== $provider['adapter_version']
			|| $source['product_revision_digest'] !== $profile['source_controls']['product_revision_digest']
			|| $source['rate_revision_digest'] !== $profile['source_controls']['rate_revision_digest']
			|| $source['availability_revision_digest'] !== $profile['source_controls']['availability_revision_digest']
			|| $source['terms_revision_digest'] !== $profile['source_controls']['terms_revision_digest'] ) {
			return false;
		}
		$product = $this->catalog->resolve_private_product( $record['provider_id'], $record['provider_reference_digest'], $this->secret );
		return is_array( $product )
			&& $product['private_product_ref'] === $record['catalog_binding']['private_product_ref']
			&& hash_equals( $record['catalog_binding']['private_product_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $product ) );
	}

	private function private_reference( $kind, $parts ) {
		$digest = hash_hmac( 'sha256', $kind . '|' . Tra_Vel_Commerce_Policy::canonical_digest( $parts ), $this->secret . '|tra-vel-commerce-private-routing-v1', true );
		return 'tvr_' . $kind . '_' . rtrim( strtr( base64_encode( $digest ), '+/', '-_' ), '=' );
	}

	private function public_payload_safe( $value, $key = '' ) {
		$forbidden_keys = array( 'private_product_ref', 'provider_locator_ref', 'credential_ref', 'endpoint_route_ref', 'endpoint_host', 'supplier_booking_reference', 'raw_supplier_reference', 'vault_secret_ref' );
		if ( in_array( $key, $forbidden_keys, true ) ) {
			return false;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $child_key => $child ) {
				if ( ! $this->public_payload_safe( $child, (string) $child_key ) ) {
					return false;
				}
			}
			return true;
		}
		return ! is_string( $value ) || ( 0 !== strpos( $value, 'px_' ) && 0 !== strpos( $value, 'credref_' ) && 0 !== strpos( $value, 'tvr_' ) && 0 !== strpos( $value, 'route_' ) && false === strpos( $value, '.invalid' ) );
	}

	private function ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private function private_ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tvr_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private function utc_timestamp( $value ) {
		$normalized = Tra_Vel_Commerce_Policy::utc_datetime( $value );
		return null === $normalized ? null : strtotime( $normalized );
	}

	private function exact_object( $value, $keys ) {
		return is_array( $value ) && ! $this->is_list( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private function is_list( $value ) {
		return is_array( $value ) && ( empty( $value ) || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
	}

	private function error( $suffix, $message, $status ) {
		return new WP_Error( 'tra_vel_commerce_private_routing_' . $suffix, $message, array( 'status' => (int) $status ) );
	}
}
