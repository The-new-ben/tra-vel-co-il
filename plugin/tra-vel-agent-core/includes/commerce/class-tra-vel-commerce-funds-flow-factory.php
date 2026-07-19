<?php
/**
 * Build the first immutable, server-only funds-flow snapshot for one order item.
 *
 * The factory consumes already selected public commerce data and an exact private
 * routing proof. It reconciles those inputs with one provider descriptor, one
 * supplier-profile revision, and one immutable sandbox commercial configuration.
 * It never contacts a supplier or processor and never grants execution authority.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Funds_Flow_Factory {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_MONEY_MINOR  = 1000000000000;

	/** @var string */
	private $secret = '';

	/** @var WP_Error|null */
	private $error;

	/**
	 * @param string|null $secret Base server secret shared with private routing.
	 */
	public function __construct( $secret = null ) {
		if ( null === $secret ) {
			if ( ! function_exists( 'wp_salt' ) ) {
				$this->error = $this->error( 'secret_unavailable', 'The private funds-flow reference secret is unavailable.', 503 );
				return;
			}
			$secret = (string) wp_salt( 'auth' );
		}
		if ( ! is_string( $secret ) || strlen( $secret ) < 16 ) {
			$this->error = $this->error( 'secret_unavailable', 'The private funds-flow reference secret is unavailable.', 503 );
			return;
		}
		$this->secret = $secret;
	}

	/**
	 * Create version one of the private per-item funds-flow ledger.
	 *
	 * @param array $order_item              Already validated public order item.
	 * @param array $offer                   Exact selected public offer snapshot.
	 * @param array $routing_record          Exact server-only private route record.
	 * @param array $provider_descriptor     Reconciled provider-network descriptor.
	 * @param array $supplier_profile        Reconciled supplier-profile revision.
	 * @param array $commercial_configuration Immutable per-item rate configuration.
	 * @param array $context                 Owner, idempotency digest, and UTC clock.
	 * @return array|WP_Error
	 */
	public function create_initial_snapshot( $order_item, $offer, $routing_record, $provider_descriptor, $supplier_profile, $commercial_configuration, $context ) {
		if ( is_wp_error( $this->error ) ) {
			return $this->error;
		}
		if (
			! class_exists( 'Tra_Vel_Commerce_Policy' ) ||
			! class_exists( 'Tra_Vel_Commerce_Money' ) ||
			! class_exists( 'Tra_Vel_Supplier_Operations_Policy' ) ||
			! class_exists( 'Tra_Vel_Commerce_Funds_Flow_Policy' )
		) {
			return $this->error( 'dependency_unavailable', 'The closed commerce, supplier, money, and funds-flow policies must be loaded first.', 500 );
		}

		$context = $this->context( $context );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$order_item = $this->order_item( $order_item );
		if ( is_wp_error( $order_item ) ) {
			return $order_item;
		}
		$offer = $this->offer( $offer, $order_item, $context['now'] );
		if ( is_wp_error( $offer ) ) {
			return $offer;
		}
		$routing_record = $this->routing_record( $routing_record, $order_item, $offer, $context );
		if ( is_wp_error( $routing_record ) ) {
			return $routing_record;
		}

		$provider = Tra_Vel_Commerce_Policy::provider_descriptor( $provider_descriptor );
		if ( is_wp_error( $provider ) ) {
			return $this->error( 'provider_invalid', 'The provider descriptor failed the closed commerce policy.', 409 );
		}
		if ( $provider !== $provider_descriptor ) {
			return $this->error( 'provider_not_canonical', 'The provider descriptor must already be normalized before financial construction.', 409 );
		}
		$profile = Tra_Vel_Supplier_Operations_Policy::supplier_profile( $supplier_profile, $context['now'] );
		if ( is_wp_error( $profile ) ) {
			return $this->error( 'supplier_profile_invalid', 'The supplier profile failed the closed readiness policy.', 409 );
		}
		if ( $profile !== $supplier_profile ) {
			return $this->error( 'supplier_profile_not_canonical', 'The supplier profile must already be normalized before financial construction.', 409 );
		}
		$reconciled = $this->reconcile_provider_and_profile( $provider, $profile, $routing_record, $order_item, $offer );
		if ( is_wp_error( $reconciled ) ) {
			return $reconciled;
		}

		$configuration = $this->commercial_configuration(
			$commercial_configuration,
			$provider,
			$profile,
			$routing_record,
			$offer,
			$context['now']
		);
		if ( is_wp_error( $configuration ) ) {
			return $configuration;
		}

		$parties = $this->parties( $configuration['commercial_model'], $profile, $configuration );
		if ( is_wp_error( $parties ) ) {
			return $parties;
		}
		$pricing = $this->pricing( $configuration, $offer['pricing'] );
		if ( is_wp_error( $pricing ) ) {
			return $pricing;
		}

		$created_at = gmdate( 'Y-m-d\TH:i:s\Z', $context['now'] );
		$source_revision_digest = Tra_Vel_Commerce_Policy::canonical_digest(
			array(
				'provider_network_signature'    => $configuration['provider_network_signature'],
				'provider_descriptor_digest'    => $configuration['provider_descriptor_digest'],
				'catalog_digest'                => $routing_record['catalog_binding']['catalog_digest'],
				'private_product_digest'        => $routing_record['catalog_binding']['private_product_digest'],
				'product_revision_digest'       => $configuration['product_revision_digest'],
				'rate_revision_digest'          => $configuration['rate_revision_digest'],
				'availability_revision_digest'  => $configuration['availability_revision_digest'],
				'terms_revision_digest'         => $configuration['terms_revision_digest'],
				'offer_digest'                  => $order_item['offer_digest'],
				'offer_evidence_digest'         => $configuration['offer_evidence_digest'],
			)
		);
		$reference_basis = array(
			$context['owner_scope_digest'],
			$routing_record['order_ref'],
			$order_item['order_item_ref'],
			$routing_record['routing_binding_digest'],
			$configuration['configuration_digest'],
			$context['idempotency_key_digest'],
		);
		$collector = $parties['payment_collector'];
		$supplier_payout_ref = 'platform' === $collector && 'affiliate_handoff' !== $configuration['commercial_model']
			? $profile['settlement']['payout_route_ref']
			: null;

		$record = array(
			'contract_version'          => Tra_Vel_Commerce_Funds_Flow_Policy::CONTRACT_VERSION,
			'environment'               => 'sandbox',
			'funds_flow_ref'            => $this->private_reference( 'fflow', $reference_basis ),
			'funds_flow_binding_digest' => '',
			'version'                   => 1,
			'previous_snapshot_digest'  => null,
			'snapshot_digest'           => '',
			'owner_scope_digest'        => $routing_record['owner_scope_digest'],
			'order_ref'                 => $routing_record['order_ref'],
			'order_version'             => $routing_record['order_version'],
			'order_digest'              => $routing_record['order_digest'],
			'order_item_ref'            => $order_item['order_item_ref'],
			'offer_digest'              => $order_item['offer_digest'],
			'routing_binding_digest'    => $routing_record['routing_binding_digest'],
			'provider_id'               => $order_item['provider_id'],
			'vertical'                  => $order_item['vertical'],
			'commercial_model'          => $configuration['commercial_model'],
			'currency'                  => $configuration['currency'],
			'minor_unit_exponent'       => $configuration['minor_unit_exponent'],
			'parties'                   => $parties,
			'commercial_terms'          => array(
				'rate_card_ref'                   => $this->private_reference( 'ratecard', array( $order_item['provider_id'], $order_item['order_item_ref'], $configuration['configuration_digest'] ) ),
				'rate_card_revision_digest'       => $configuration['rate_revision_digest'],
				'source_revision_digest'          => $source_revision_digest,
				'supplier_config_revision_digest' => $configuration['supplier_config_revision_digest'],
				'effective_at'                    => $configuration['effective_at'],
				'valid_until'                     => $configuration['valid_until'],
				'calculation_basis'               => $this->calculation_basis( $configuration['commercial_model'] ),
				'commission_bps'                  => $configuration['commission_bps'],
				'markup_amount_minor'             => $configuration['markup_amount_minor'],
				'tax_treatment'                   => $configuration['tax_treatment'],
				'evidence_digest'                 => $configuration['configuration_digest'],
			),
			'pricing'                   => $pricing,
			'payment'                   => array(
				'state'                     => 'not_started',
				'authority'                 => 'platform' === $collector ? 'platform_processor' : 'supplier_reported',
				'authorized_amount_minor'   => 0,
				'captured_amount_minor'     => 0,
				'refunded_amount_minor'     => 0,
				'disputed_amount_minor'     => 0,
				'charged_back_amount_minor' => 0,
				'processor_payment_ref'     => null,
				'latest_event_digest'       => null,
				'updated_at'                => $created_at,
			),
			'settlement'                => array(
				'state'                         => 'not_started',
				'supplier_due_minor'            => 0,
				'supplier_paid_minor'           => 0,
				'commission_due_minor'          => 0,
				'commission_received_minor'     => 0,
				'chargeback_recovery_due_minor' => 0,
				'chargeback_recovered_minor'    => 0,
				'latest_reconciliation_digest'  => null,
				'due_at'                       => null,
				'updated_at'                   => $created_at,
			),
			'liabilities'               => array(
				'customer_refund_due_minor'               => 0,
				'supplier_payable_outstanding_minor'      => 0,
				'commission_receivable_outstanding_minor' => 0,
				'chargeback_liability_minor'              => 0,
				'chargeback_liability_party'              => $parties['chargeback_liability_party'],
			),
			'private_routes'            => array(
				'private_routing_record_ref' => $routing_record['routing_binding_ref'],
				'payment_route_ref'          => $this->private_reference( 'payroute', array( $collector, $routing_record['routing_binding_digest'], $configuration['configuration_digest'] ) ),
				'settlement_route_ref'       => $this->private_reference( 'setroute', array( $profile['settlement']['payout_route_ref'], $routing_record['routing_binding_digest'], $configuration['configuration_digest'] ) ),
				'supplier_payable_route_ref' => $supplier_payout_ref,
			),
			'created_at'                => $created_at,
			'updated_at'                => $created_at,
			'last_event_sequence'       => 0,
			'sandbox_truth'             => array(
				'simulated'             => true,
				'real_processor_call'   => false,
				'real_customer_charge'  => false,
				'real_supplier_payment' => false,
				'real_settlement'       => false,
			),
			'data_boundary'            => array(
				'server_only'                  => true,
				'public_serialization_allowed' => false,
				'contains_private_locators'    => true,
				'raw_credentials_stored'       => false,
				'raw_payment_data_stored'      => false,
				'personal_data_stored'         => false,
			),
		);

		$record = Tra_Vel_Commerce_Funds_Flow_Policy::seal_snapshot( $record );
		$validated = Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot( $record, $context['now'] );
		return is_wp_error( $validated )
			? $this->error( 'snapshot_creation_failed', 'The constructed initial funds-flow snapshot failed its closed ledger policy: ' . $validated->get_error_code() . '.', 500 )
			: $validated;
	}

	/**
	 * Calculate the self-digest required by an immutable commercial configuration.
	 *
	 * @param array $configuration Commercial configuration including a blank or prior digest.
	 * @return string
	 */
	public static function commercial_configuration_digest( $configuration ) {
		if ( ! is_array( $configuration ) || ! class_exists( 'Tra_Vel_Commerce_Policy' ) ) {
			return '';
		}
		$basis = $configuration;
		unset( $basis['configuration_digest'] );
		return Tra_Vel_Commerce_Policy::canonical_digest( $basis );
	}

	private function context( $context ) {
		$keys = array( 'owner_scope_digest', 'idempotency_key_digest', 'now' );
		if ( ! $this->exact_object( $context, $keys ) || ! $this->digest( $context['owner_scope_digest'] ) || ! $this->digest( $context['idempotency_key_digest'] ) || ! is_int( $context['now'] ) || $context['now'] < 1 ) {
			return $this->error( 'context_invalid', 'An exact owner, idempotency digest, and positive UTC clock are required.', 400 );
		}
		return $context;
	}

	private function order_item( $item ) {
		$keys = array( 'order_item_ref', 'component_ref', 'role', 'required', 'sequence', 'vertical', 'provider_id', 'provider_reference_digest', 'offer_ref', 'offer_version', 'offer_digest', 'state', 'latest_operation_ref', 'receipt_digest' );
		if (
			! $this->exact_object( $item, $keys ) ||
			! $this->public_ref( $item['order_item_ref'], 'order_item' ) ||
			! $this->public_ref( $item['component_ref'], 'component' ) ||
			! $this->public_ref( $item['offer_ref'], 'offer' ) ||
			! is_string( $item['role'] ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{1,47}$/', $item['role'] ) ||
			! is_bool( $item['required'] ) || ! is_int( $item['sequence'] ) || $item['sequence'] < 1 || $item['sequence'] > 32 ||
			! is_string( $item['vertical'] ) || ! in_array( $item['vertical'], Tra_Vel_Commerce_Funds_Flow_Policy::VERTICALS, true ) ||
			! $this->provider_id( $item['provider_id'] ) || ! $this->digest( $item['provider_reference_digest'] ) ||
			! is_int( $item['offer_version'] ) || $item['offer_version'] < 1 || ! $this->digest( $item['offer_digest'] ) ||
			'selected' !== $item['state'] || null !== $item['latest_operation_ref'] || null !== $item['receipt_digest']
		) {
			return $this->error( 'order_item_invalid', 'Only one pristine, selected, digest-bound public order item may initialize funds flow.', 400 );
		}
		return $item;
	}

	private function offer( $offer, $item, $now ) {
		$keys = array( 'contract_version', 'environment', 'offer_ref', 'version', 'search_session_ref', 'provider_id', 'provider_reference_digest', 'vertical', 'status', 'product', 'geometry', 'pricing', 'availability', 'terms', 'capabilities', 'ranking', 'evidence', 'sandbox_truth', 'data_boundary' );
		if ( ! $this->exact_object( $offer, $keys ) || self::CONTRACT_VERSION !== $offer['contract_version'] || 'sandbox' !== $offer['environment'] ) {
			return $this->error( 'offer_invalid', 'The selected offer is not the closed sandbox contract.', 400 );
		}
		if (
			$offer['offer_ref'] !== $item['offer_ref'] || $offer['version'] !== $item['offer_version'] ||
			$offer['provider_id'] !== $item['provider_id'] || $offer['provider_reference_digest'] !== $item['provider_reference_digest'] ||
			$offer['vertical'] !== $item['vertical'] || ! in_array( $offer['status'], array( 'available', 'limited' ), true ) ||
			! hash_equals( $item['offer_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $offer ) )
		) {
			return $this->error( 'offer_binding_mismatch', 'The selected offer does not match the immutable order-item identity and digest.', 409 );
		}
		if ( ! $this->pricing_ledger( $offer['pricing'] ) ) {
			return $this->error( 'offer_pricing_invalid', 'The selected offer money ledger is not an exact balanced integer-minor-unit ledger.', 409 );
		}
		$availability_keys = array( 'state', 'quantity_remaining', 'checked_at', 'fresh_until' );
		$evidence_keys = array( 'adapter_version', 'evidence_digest', 'retrieved_at', 'fresh_until' );
		$terms_keys = array( 'terms_digest', 'cancellation', 'changes', 'inclusions', 'requires_revalidation' );
		if (
			! $this->exact_object( $offer['availability'], $availability_keys ) ||
			! $this->exact_object( $offer['evidence'], $evidence_keys ) ||
			! $this->exact_object( $offer['terms'], $terms_keys ) ||
			! $this->digest( $offer['evidence']['evidence_digest'] ) || ! $this->digest( $offer['terms']['terms_digest'] ) ||
			true !== $offer['terms']['requires_revalidation'] ||
			$this->utc_timestamp( $offer['availability']['fresh_until'] ) <= $now ||
			$this->utc_timestamp( $offer['evidence']['fresh_until'] ) <= $now ||
			$offer['availability']['fresh_until'] !== $offer['evidence']['fresh_until'] ||
			! is_string( $offer['evidence']['adapter_version'] ) || 1 !== preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/', $offer['evidence']['adapter_version'] )
		) {
			return $this->error( 'offer_evidence_invalid', 'The offer requires current, matching availability, terms, adapter, and evidence revisions.', 409 );
		}
		if ( ! $this->is_list( $offer['capabilities'] ) || ! $offer['capabilities'] || count( $offer['capabilities'] ) !== count( array_unique( $offer['capabilities'] ) ) ) {
			return $this->error( 'offer_capabilities_invalid', 'The selected offer capability set must be a non-empty unique list.', 409 );
		}
		$truth = array( 'simulated_inventory' => true, 'real_supplier_request' => false, 'real_inventory_hold' => false, 'real_charge' => false, 'real_booking' => false, 'real_policy_issuance' => false, 'real_settlement' => false );
		$boundary = array( 'raw_supplier_reference_exposed' => false, 'raw_payment_data_exposed' => false, 'medical_data_exposed' => false );
		if ( $offer['sandbox_truth'] !== $truth || $offer['data_boundary'] !== $boundary ) {
			return $this->error( 'offer_truth_invalid', 'The selected offer cannot claim live authority or expose private data.', 409 );
		}
		return $offer;
	}

	private function routing_record( $record, $item, $offer, $context ) {
		$keys = array( 'contract_version', 'environment', 'routing_binding_ref', 'routing_binding_digest', 'owner_scope_digest', 'order_ref', 'order_digest', 'order_version', 'order_item_ref', 'component_ref', 'provider_id', 'vertical', 'provider_reference_digest', 'offer_ref', 'offer_version', 'offer_digest', 'catalog_binding', 'supplier_binding', 'capability_binding', 'private_route', 'validity', 'created_at', 'private_boundary' );
		if ( ! $this->exact_object( $record, $keys ) || self::CONTRACT_VERSION !== $record['contract_version'] || 'sandbox' !== $record['environment'] ) {
			return $this->error( 'routing_record_invalid', 'The server-only routing record is not the closed sandbox contract.', 400 );
		}
		if (
			! $this->routing_ref( $record['routing_binding_ref'], 'binding' ) || ! $this->digest( $record['routing_binding_digest'] ) ||
			! $this->digest( $record['owner_scope_digest'] ) || ! hash_equals( $record['owner_scope_digest'], $context['owner_scope_digest'] ) ||
			! $this->public_ref( $record['order_ref'], 'order' ) || ! $this->digest( $record['order_digest'] ) ||
			! is_int( $record['order_version'] ) || $record['order_version'] < 1 ||
			$record['order_item_ref'] !== $item['order_item_ref'] || $record['component_ref'] !== $item['component_ref'] ||
			$record['provider_id'] !== $item['provider_id'] || $record['vertical'] !== $item['vertical'] ||
			$record['provider_reference_digest'] !== $item['provider_reference_digest'] ||
			$record['offer_ref'] !== $item['offer_ref'] || $record['offer_version'] !== $item['offer_version'] ||
			$record['offer_digest'] !== $item['offer_digest']
		) {
			return $this->error( 'routing_identity_mismatch', 'The private route, owner, order item, provider, and offer do not share one immutable identity.', 409 );
		}
		$expected_ref = $this->private_routing_reference(
			'binding',
			array( $record['owner_scope_digest'], $record['order_ref'], $record['order_item_ref'], $record['provider_id'], $record['provider_reference_digest'], $record['offer_digest'] )
		);
		if ( ! hash_equals( $expected_ref, $record['routing_binding_ref'] ) ) {
			return $this->error( 'routing_reference_invalid', 'The private route reference was not issued for this exact order-item binding.', 409 );
		}
		$basis = $record;
		unset( $basis['routing_binding_digest'] );
		if ( ! hash_equals( $record['routing_binding_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $basis ) ) ) {
			return $this->error( 'routing_digest_invalid', 'The private routing record changed after its integrity digest was issued.', 409 );
		}

		$catalog_keys = array( 'catalog_digest', 'private_product_ref', 'private_product_digest', 'service_valid_until' );
		$supplier_keys = array( 'network_signature', 'profile_revision_id', 'profile_revision_number', 'profile_content_digest', 'adapter_version', 'source_revisions' );
		$revision_keys = array( 'product_revision_digest', 'rate_revision_digest', 'availability_revision_digest', 'terms_revision_digest' );
		if (
			! $this->exact_object( $record['catalog_binding'], $catalog_keys ) ||
			! $this->digest( $record['catalog_binding']['catalog_digest'] ) || ! $this->digest( $record['catalog_binding']['private_product_digest'] ) ||
			! is_string( $record['catalog_binding']['private_product_ref'] ) || 1 !== preg_match( '/^px_[a-z0-9_]{8,90}$/', $record['catalog_binding']['private_product_ref'] ) ||
			! $this->exact_object( $record['supplier_binding'], $supplier_keys ) ||
			! $this->digest( $record['supplier_binding']['network_signature'] ) || ! $this->digest( $record['supplier_binding']['profile_content_digest'] ) ||
			! is_string( $record['supplier_binding']['profile_revision_id'] ) || 1 !== preg_match( '/^suprev_[a-z0-9]{12,64}$/', $record['supplier_binding']['profile_revision_id'] ) ||
			! is_int( $record['supplier_binding']['profile_revision_number'] ) || $record['supplier_binding']['profile_revision_number'] < 1 ||
			! is_string( $record['supplier_binding']['adapter_version'] ) || 1 !== preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/', $record['supplier_binding']['adapter_version'] ) ||
			! $this->exact_object( $record['supplier_binding']['source_revisions'], $revision_keys )
		) {
			return $this->error( 'routing_revision_invalid', 'The private route does not freeze complete catalog, network, profile, and supplier source revisions.', 409 );
		}
		foreach ( $revision_keys as $revision_key ) {
			if ( ! $this->digest( $record['supplier_binding']['source_revisions'][ $revision_key ] ) ) {
				return $this->error( 'routing_revision_invalid', 'Every private supplier source revision must be an immutable digest.', 409 );
			}
		}

		$capability_keys = array( 'frozen_capabilities', 'capability_digest' );
		if ( ! $this->exact_object( $record['capability_binding'], $capability_keys ) || ! $this->is_list( $record['capability_binding']['frozen_capabilities'] ) || ! $record['capability_binding']['frozen_capabilities'] || ! $this->digest( $record['capability_binding']['capability_digest'] ) ) {
			return $this->error( 'routing_capability_invalid', 'The private route must freeze one exact product capability set.', 409 );
		}
		$frozen = $record['capability_binding']['frozen_capabilities'];
		$sorted = $frozen;
		$offer_capabilities = $offer['capabilities'];
		sort( $sorted, SORT_STRING );
		sort( $offer_capabilities, SORT_STRING );
		if ( $sorted !== $frozen || count( $frozen ) !== count( array_unique( $frozen ) ) || $frozen !== $offer_capabilities || ! hash_equals( $record['capability_binding']['capability_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $frozen ) ) ) {
			return $this->error( 'routing_capability_mismatch', 'The route and selected offer do not freeze the same canonical capabilities.', 409 );
		}

		$route_keys = array( 'credential_ref', 'endpoint_route_ref', 'endpoint_host', 'endpoint_evidence_digest', 'tls_required', 'redirect_policy', 'operation_route_refs' );
		if (
			! $this->exact_object( $record['private_route'], $route_keys ) ||
			! is_string( $record['private_route']['credential_ref'] ) || 1 !== preg_match( '/^credref_[a-z0-9_]{8,120}$/', $record['private_route']['credential_ref'] ) ||
			! $this->routing_ref( $record['private_route']['endpoint_route_ref'], 'endpoint' ) ||
			! is_string( $record['private_route']['endpoint_host'] ) || '.invalid' !== substr( $record['private_route']['endpoint_host'], -8 ) ||
			! $this->digest( $record['private_route']['endpoint_evidence_digest'] ) || true !== $record['private_route']['tls_required'] || 'deny' !== $record['private_route']['redirect_policy'] ||
			! $this->is_list( $record['private_route']['operation_route_refs'] ) || ! $record['private_route']['operation_route_refs']
		) {
			return $this->error( 'routing_private_route_invalid', 'Only opaque simulator routes, vault pointers, TLS, and redirect denial may enter funds-flow construction.', 409 );
		}
		$route_capabilities = array();
		foreach ( $record['private_route']['operation_route_refs'] as $operation_route ) {
			if (
				! $this->exact_object( $operation_route, array( 'capability', 'primary_route_ref', 'after_hours_route_ref' ) ) ||
				! is_string( $operation_route['capability'] ) ||
				! is_string( $operation_route['primary_route_ref'] ) || 1 !== preg_match( '/^route_[a-z0-9_]{8,160}$/', $operation_route['primary_route_ref'] ) ||
				! is_string( $operation_route['after_hours_route_ref'] ) || 1 !== preg_match( '/^route_[a-z0-9_]{8,160}$/', $operation_route['after_hours_route_ref'] )
			) {
				return $this->error( 'routing_operation_route_invalid', 'Every frozen capability requires exact opaque primary and after-hours routes.', 409 );
			}
			$route_capabilities[] = $operation_route['capability'];
		}
		sort( $route_capabilities, SORT_STRING );
		if ( $route_capabilities !== $frozen ) {
			return $this->error( 'routing_operation_route_mismatch', 'Private operation routes must cover exactly the selected product capabilities.', 409 );
		}

		$validity_keys = array( 'offer_fresh_until', 'order_expires_at', 'supplier_terms_valid_until', 'credential_expires_at', 'service_valid_until', 'valid_until' );
		$created_at = $this->utc_timestamp( $record['created_at'] );
		if ( ! $this->exact_object( $record['validity'], $validity_keys ) || null === $created_at || $created_at > $context['now'] ) {
			return $this->error( 'routing_validity_invalid', 'The private route chronology is invalid.', 409 );
		}
		$minimum = null;
		foreach ( $validity_keys as $key ) {
			$instant = $this->utc_timestamp( $record['validity'][ $key ] );
			if ( null === $instant ) {
				return $this->error( 'routing_validity_invalid', 'A private route validity boundary is malformed.', 409 );
			}
			if ( 'valid_until' !== $key ) {
				$minimum = null === $minimum ? $instant : min( $minimum, $instant );
			}
		}
		if ( $this->utc_timestamp( $record['validity']['valid_until'] ) !== $minimum || $minimum <= $context['now'] ) {
			return $this->error( 'routing_stale', 'The private route is stale or exceeds one of its frozen validity boundaries.', 409 );
		}
		$boundary = array( 'server_only' => true, 'public_serialization_allowed' => false, 'raw_credentials_stored' => false, 'vault_pointers_only' => true, 'contains_private_provider_locator' => true );
		if ( $record['private_boundary'] !== $boundary ) {
			return $this->error( 'routing_boundary_invalid', 'The private routing boundary cannot be weakened for funds-flow construction.', 409 );
		}
		return $record;
	}

	private function reconcile_provider_and_profile( $provider, $profile, $routing, $item, $offer ) {
		$provider_verticals = $provider['verticals'];
		$profile_verticals  = $profile['verticals'];
		sort( $provider_verticals, SORT_STRING );
		sort( $profile_verticals, SORT_STRING );
		$provider_capabilities = $provider['capabilities'];
		$profile_capabilities = array();
		foreach ( $profile['capability_claims'] as $claim ) {
			$profile_capabilities[] = $claim['capability'];
		}
		$profile_capabilities = array_values( array_unique( $profile_capabilities ) );
		sort( $provider_capabilities, SORT_STRING );
		sort( $profile_capabilities, SORT_STRING );
		$commission_matches = in_array( $provider['settlement']['model'], array( 'owned', 'net_rate' ), true )
			? 0 === $provider['settlement']['commission_bps'] && null === $profile['settlement']['commission_bps']
			: $provider['settlement']['commission_bps'] === $profile['settlement']['commission_bps'];

		if (
			$provider['provider_id'] !== $item['provider_id'] || $profile['supplier_id'] !== $item['provider_id'] ||
			'sandbox' !== $provider['environment'] || 'sandbox' !== $profile['environment'] ||
			$provider['relationship'] !== $profile['relationship']['model'] ||
			$provider_verticals !== $profile_verticals || ! in_array( $item['vertical'], $provider_verticals, true ) ||
			$provider_capabilities !== $profile_capabilities ||
			$provider['settlement']['model'] !== $profile['settlement']['model'] ||
			$provider['settlement']['currency'] !== $profile['settlement']['currency'] || ! $commission_matches ||
			$provider['settlement']['payout_lag_days'] !== $profile['settlement']['payout_lag_days'] ||
			'ready' !== $provider['readiness']['status'] || 'sandbox_active' !== $profile['lifecycle_status'] ||
			'sandbox_ready' !== $profile['readiness']['decision'] || 'healthy' !== $profile['health']['state'] || 'armed' !== $profile['kill_switch']['state'] ||
			true !== $provider['commercial_truth']['simulated'] || $provider['commercial_truth']['real_charge'] || $provider['commercial_truth']['real_booking'] ||
			true !== $profile['commercial_truth']['simulated'] || $profile['commercial_truth']['real_charge'] || $profile['commercial_truth']['real_booking']
		) {
			return $this->error( 'provider_supplier_mismatch', 'Provider and supplier commercial, capability, readiness, currency, and sandbox truth must reconcile exactly.', 409 );
		}
		if (
			$routing['supplier_binding']['profile_revision_id'] !== $profile['revision_id'] ||
			$routing['supplier_binding']['profile_revision_number'] !== $profile['revision_number'] ||
			$routing['supplier_binding']['profile_content_digest'] !== $profile['revision_control']['content_digest'] ||
			$routing['supplier_binding']['adapter_version'] !== $provider['adapter_version'] ||
			$offer['evidence']['adapter_version'] !== $provider['adapter_version'] . '-sandbox'
		) {
			return $this->error( 'routing_supplier_revision_mismatch', 'The private route does not bind this exact provider adapter and supplier-profile revision.', 409 );
		}
		$credential_refs = array();
		foreach ( $profile['credentials'] as $credential ) {
			if ( 'configured' === $credential['status'] && 'sandbox' === $credential['environment'] ) {
				$credential_refs[] = $credential['credential_ref'];
			}
		}
		if ( ! in_array( $routing['private_route']['credential_ref'], $credential_refs, true ) || $routing['private_route']['endpoint_evidence_digest'] !== $profile['endpoints']['certificate_evidence_digest'] || ! in_array( $routing['private_route']['endpoint_host'], $profile['endpoints']['allowed_hosts'], true ) ) {
			return $this->error( 'routing_supplier_route_mismatch', 'The private route does not match the validated supplier credential and endpoint evidence.', 409 );
		}
		$expected_endpoint_ref = $this->private_routing_reference( 'endpoint', array( $profile['revision_id'], $routing['private_route']['endpoint_host'], $profile['endpoints']['certificate_evidence_digest'] ) );
		if ( ! hash_equals( $expected_endpoint_ref, $routing['private_route']['endpoint_route_ref'] ) ) {
			return $this->error( 'routing_endpoint_reference_invalid', 'The private endpoint reference was not issued for this supplier revision.', 409 );
		}
		return true;
	}

	private function commercial_configuration( $configuration, $provider, $profile, $routing, $offer, $now ) {
		$keys = array(
			'contract_version', 'environment', 'authority', 'provider_id', 'vertical',
			'provider_network_signature', 'provider_descriptor_digest', 'supplier_config_revision_digest',
			'product_revision_digest', 'rate_revision_digest', 'availability_revision_digest',
			'terms_revision_digest', 'offer_evidence_digest', 'commercial_model', 'currency',
			'minor_unit_exponent', 'effective_at', 'valid_until', 'commissionable_basis',
			'commission_bps', 'supplier_net_minor', 'markup_amount_minor', 'affiliate_collector',
			'tax_treatment', 'configuration_digest',
		);
		if ( ! $this->exact_object( $configuration, $keys ) || self::CONTRACT_VERSION !== $configuration['contract_version'] || 'sandbox' !== $configuration['environment'] || 'sandbox_reconciled_configuration' !== $configuration['authority'] ) {
			return $this->error( 'commercial_configuration_invalid', 'The per-item commercial configuration is not the closed sandbox reconciliation contract.', 400 );
		}
		foreach ( array( 'provider_network_signature', 'provider_descriptor_digest', 'supplier_config_revision_digest', 'product_revision_digest', 'rate_revision_digest', 'availability_revision_digest', 'terms_revision_digest', 'offer_evidence_digest', 'configuration_digest' ) as $digest_key ) {
			if ( ! $this->digest( $configuration[ $digest_key ] ) ) {
				return $this->error( 'commercial_evidence_invalid', 'Commercial configuration evidence must use immutable SHA-256 digests only.', 409 );
			}
		}
		$expected_configuration_digest = self::commercial_configuration_digest( $configuration );
		if ( ! hash_equals( $configuration['configuration_digest'], $expected_configuration_digest ) ) {
			return $this->error( 'commercial_configuration_digest_invalid', 'The commercial configuration changed after its self-digest was issued.', 409 );
		}
		$source = $routing['supplier_binding']['source_revisions'];
		if (
			$configuration['provider_id'] !== $provider['provider_id'] || $configuration['vertical'] !== $offer['vertical'] ||
			$configuration['provider_network_signature'] !== $routing['supplier_binding']['network_signature'] ||
			$configuration['provider_descriptor_digest'] !== Tra_Vel_Commerce_Policy::canonical_digest( $provider ) ||
			$configuration['supplier_config_revision_digest'] !== $profile['revision_control']['content_digest'] ||
			$configuration['product_revision_digest'] !== $source['product_revision_digest'] ||
			$configuration['rate_revision_digest'] !== $source['rate_revision_digest'] ||
			$configuration['availability_revision_digest'] !== $source['availability_revision_digest'] ||
			$configuration['terms_revision_digest'] !== $source['terms_revision_digest'] ||
			$configuration['offer_evidence_digest'] !== $offer['evidence']['evidence_digest']
		) {
			return $this->error( 'commercial_revision_mismatch', 'Rate, product, availability, terms, offer, network, provider, or supplier configuration evidence does not match the exact private route.', 409 );
		}

		$model = $this->commercial_model( $provider, $profile );
		if ( is_wp_error( $model ) || $configuration['commercial_model'] !== $model ) {
			return is_wp_error( $model ) ? $model : $this->error( 'commercial_model_mismatch', 'The declared commercial model does not match provider and supplier settlement truth.', 409 );
		}
		if (
			$configuration['currency'] !== $offer['pricing']['currency'] ||
			$configuration['currency'] !== $provider['settlement']['currency'] ||
			$configuration['currency'] !== $profile['settlement']['currency'] ||
			$configuration['minor_unit_exponent'] !== $offer['pricing']['minor_unit'] ||
			$configuration['minor_unit_exponent'] !== Tra_Vel_Commerce_Money::exponent( $configuration['currency'] )
		) {
			return $this->error( 'commercial_currency_mismatch', 'Offer, provider, supplier, rate card, and minor-unit exponent must use one exact currency without implicit FX.', 409 );
		}

		$effective = $this->utc_timestamp( $configuration['effective_at'] );
		$valid_until = $this->utc_timestamp( $configuration['valid_until'] );
		$supplier_terms_until = $this->utc_timestamp( $routing['validity']['supplier_terms_valid_until'] );
		$agreement_until = null === $profile['relationship']['expires_at'] ? null : $this->utc_timestamp( $profile['relationship']['expires_at'] );
		if ( null === $effective || null === $valid_until || $effective > $now || $valid_until <= $now || $valid_until > $supplier_terms_until || ( null !== $agreement_until && $valid_until > $agreement_until ) ) {
			return $this->error( 'commercial_terms_stale', 'The exact rate configuration must be effective now and bounded by current supplier and agreement terms.', 409 );
		}
		$expected_tax_treatment = 'included' === $offer['pricing']['tax_state'] ? 'included' : ( 'excluded' === $offer['pricing']['tax_state'] ? 'excluded' : '' );
		if ( '' === $expected_tax_treatment || $configuration['tax_treatment'] !== $expected_tax_treatment ) {
			return $this->error( 'commercial_tax_mismatch', 'Unknown or mismatched tax treatment cannot initialize a financial ledger.', 409 );
		}

		if ( 'affiliate_handoff' === $model || 'direct_commission' === $model ) {
			if (
				! in_array( $configuration['commissionable_basis'], array( 'customer_total', 'subtotal' ), true ) ||
				! is_int( $configuration['commission_bps'] ) || $configuration['commission_bps'] < 1 || $configuration['commission_bps'] > 10000 ||
				$configuration['commission_bps'] !== $profile['settlement']['commission_bps'] ||
				$configuration['commission_bps'] !== $provider['settlement']['commission_bps'] ||
				null !== $configuration['supplier_net_minor'] || 0 !== $configuration['markup_amount_minor']
			) {
				return $this->error( 'commercial_commission_mismatch', 'Commission models require one exact basis-point rate, basis, and no net-rate markup inputs.', 409 );
			}
			if ( 'retail_gross' === $profile['settlement']['gross_basis'] && 'customer_total' !== $configuration['commissionable_basis'] ) {
				return $this->error( 'commercial_commission_basis_mismatch', 'Retail-gross settlement must use the exact customer total as its commission basis.', 409 );
			}
			if ( 'affiliate_handoff' === $model ) {
				if ( ! in_array( $configuration['affiliate_collector'], array( 'supplier', 'affiliate_network' ), true ) ) {
					return $this->error( 'affiliate_configuration_invalid', 'Affiliate handoff requires an explicit external collector.', 409 );
				}
			} elseif ( null !== $configuration['affiliate_collector'] || in_array( 'affiliate_handoff', $offer['capabilities'], true ) ) {
				return $this->error( 'direct_configuration_invalid', 'A direct commission item cannot carry affiliate collector or handoff authority.', 409 );
			}
		} else {
			if (
				'not_applicable' !== $configuration['commissionable_basis'] || null !== $configuration['commission_bps'] ||
				! $this->money( $configuration['supplier_net_minor'] ) || ! $this->money( $configuration['markup_amount_minor'] ) ||
				null !== $configuration['affiliate_collector'] ||
				$configuration['supplier_net_minor'] + $configuration['markup_amount_minor'] !== $offer['pricing']['total_amount_minor'] ||
				'platform' !== $profile['relationship']['merchant_of_record'] || 'platform' !== $profile['settlement']['customer_funds_owner']
			) {
				return $this->error( 'commercial_net_rate_mismatch', 'Net-rate configuration must reconcile exact supplier net plus platform markup to the offer total.', 409 );
			}
		}
		return $configuration;
	}

	private function commercial_model( $provider, $profile ) {
		if ( 'affiliate' === $provider['relationship'] && 'affiliate' === $provider['settlement']['model'] && 'affiliate' === $profile['settlement']['model'] ) {
			return 'affiliate_handoff';
		}
		if ( 'direct' === $provider['relationship'] && 'commission' === $provider['settlement']['model'] && 'commission' === $profile['settlement']['model'] ) {
			return 'direct_commission';
		}
		if ( 'direct' === $provider['relationship'] && 'net_rate' === $provider['settlement']['model'] && 'net_rate' === $profile['settlement']['model'] ) {
			return 'net_rate_markup';
		}
		return $this->error( 'commercial_model_unsupported', 'Only affiliate handoff, direct commission, and net-rate markup can initialize this funds-flow contract.', 409 );
	}

	private function parties( $model, $profile, $configuration ) {
		if ( 'affiliate_handoff' === $model ) {
			$collector = $configuration['affiliate_collector'];
			if ( 'supplier' === $profile['settlement']['customer_funds_owner'] && 'supplier' !== $collector ) {
				return $this->error( 'affiliate_collector_mismatch', 'Supplier-owned customer funds require the supplier to remain merchant and collector.', 409 );
			}
			if ( 'not_applicable' === $profile['settlement']['customer_funds_owner'] && 'affiliate_network' !== $collector ) {
				return $this->error( 'affiliate_collector_mismatch', 'An affiliate-network collector requires an explicit non-supplier customer-funds arrangement.', 409 );
			}
			$supplier_payee = 'affiliate_network' === $collector ? 'affiliate_network' : 'supplier';
		} else {
			$collector = $profile['relationship']['merchant_of_record'];
			$supplier_payee = 'supplier';
			if ( ! in_array( $collector, array( 'platform', 'supplier' ), true ) || $profile['settlement']['customer_funds_owner'] !== $collector ) {
				return $this->error( 'direct_collector_mismatch', 'Direct funds require one explicit platform-or-supplier merchant and collector.', 409 );
			}
			if ( 'net_rate_markup' === $model && 'platform' !== $collector ) {
				return $this->error( 'net_rate_collector_invalid', 'Net-rate markup requires the platform to be merchant and collector.', 409 );
			}
		}
		$chargeback_owner = $profile['settlement']['chargeback_owner'];
		$tax_owner = $profile['settlement']['tax_owner'];
		if ( ! in_array( $chargeback_owner, array( $collector, 'shared' ), true ) || ! in_array( $tax_owner, array( $collector, 'shared', 'not_applicable' ), true ) ) {
			return $this->error( 'liability_party_mismatch', 'Chargeback and tax ownership must reconcile with the explicit merchant and collector.', 409 );
		}
		return array(
			'merchant_of_record'         => $collector,
			'payment_collector'          => $collector,
			'supplier_payee'             => $supplier_payee,
			'commission_payee'           => 'net_rate_markup' === $model ? 'not_applicable' : 'platform',
			'refund_liability_party'     => $collector,
			'chargeback_liability_party' => $chargeback_owner,
			'tax_remitter'               => $tax_owner,
		);
	}

	private function pricing( $configuration, $ledger ) {
		$total = $ledger['total_amount_minor'];
		$pricing = array(
			'customer_total_minor'        => $total,
			'tax_minor'                   => $ledger['tax_amount_minor'],
			'supplier_net_minor'          => 0,
			'commissionable_minor'        => 0,
			'commission_receivable_minor' => 0,
			'platform_markup_minor'       => 0,
			'supplier_payable_minor'      => 0,
			'platform_revenue_minor'      => 0,
		);
		if ( 'affiliate_handoff' === $configuration['commercial_model'] || 'direct_commission' === $configuration['commercial_model'] ) {
			$commissionable = 'subtotal' === $configuration['commissionable_basis'] ? $ledger['subtotal_amount_minor'] : $total;
			if ( $commissionable < 1 || $commissionable > $total ) {
				return $this->error( 'commissionable_amount_invalid', 'The configured commission basis must be positive and bounded by the customer total.', 409 );
			}
			$commission = intdiv( ( $commissionable * $configuration['commission_bps'] ) + 5000, 10000 );
			if ( $commission > $total ) {
				return $this->error( 'commission_amount_invalid', 'The exact commission cannot exceed the customer total.', 409 );
			}
			$pricing['commissionable_minor'] = $commissionable;
			$pricing['commission_receivable_minor'] = $commission;
			$pricing['platform_revenue_minor'] = $commission;
			if ( 'direct_commission' === $configuration['commercial_model'] ) {
				$pricing['supplier_net_minor'] = $total - $commission;
				$pricing['supplier_payable_minor'] = $total - $commission;
			}
		} else {
			$pricing['supplier_net_minor'] = $configuration['supplier_net_minor'];
			$pricing['supplier_payable_minor'] = $configuration['supplier_net_minor'];
			$pricing['platform_markup_minor'] = $configuration['markup_amount_minor'];
			$pricing['platform_revenue_minor'] = $configuration['markup_amount_minor'];
		}
		return $pricing;
	}

	private function pricing_ledger( $ledger ) {
		$keys = array( 'currency', 'minor_unit', 'price_scope', 'line_items', 'subtotal_amount_minor', 'tax_amount_minor', 'fee_amount_minor', 'credit_amount_minor', 'total_amount_minor', 'tax_state', 'fee_state' );
		if ( ! $this->exact_object( $ledger, $keys ) || ! is_string( $ledger['currency'] ) || $ledger['minor_unit'] !== Tra_Vel_Commerce_Money::exponent( $ledger['currency'] ) || ! $this->is_list( $ledger['line_items'] ) || ! $ledger['line_items'] ) {
			return false;
		}
		foreach ( array( 'subtotal_amount_minor', 'tax_amount_minor', 'fee_amount_minor', 'credit_amount_minor', 'total_amount_minor' ) as $field ) {
			if ( ! $this->money( $ledger[ $field ] ) ) {
				return false;
			}
		}
		$debits = $ledger['subtotal_amount_minor'] + $ledger['tax_amount_minor'] + $ledger['fee_amount_minor'];
		return $ledger['credit_amount_minor'] <= $debits && $ledger['total_amount_minor'] > 0 && $ledger['total_amount_minor'] === $debits - $ledger['credit_amount_minor'];
	}

	private function calculation_basis( $model ) {
		$basis = array(
			'affiliate_handoff' => 'affiliate_commission',
			'direct_commission' => 'gross_less_commission',
			'net_rate_markup'    => 'supplier_net_plus_markup',
		);
		return isset( $basis[ $model ] ) ? $basis[ $model ] : '';
	}

	private function private_reference( $prefix, $parts ) {
		$digest = hash_hmac( 'sha256', $prefix . '|' . Tra_Vel_Commerce_Policy::canonical_digest( $parts ), $this->secret . '|tra-vel-commerce-funds-flow-factory-v1', true );
		return $prefix . '_r' . rtrim( strtr( base64_encode( $digest ), '+/', '-_' ), '=' );
	}

	private function private_routing_reference( $kind, $parts ) {
		$digest = hash_hmac( 'sha256', $kind . '|' . Tra_Vel_Commerce_Policy::canonical_digest( $parts ), $this->secret . '|tra-vel-commerce-private-routing-v1', true );
		return 'tvr_' . $kind . '_' . rtrim( strtr( base64_encode( $digest ), '+/', '-_' ), '=' );
	}

	private function public_ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private function routing_ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tvr_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private function provider_id( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $value );
	}

	private function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private function money( $value ) {
		return is_int( $value ) && $value >= 0 && $value <= self::MAX_MONEY_MINOR;
	}

	private function utc_timestamp( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/', $value, $parts ) ) {
			return null;
		}
		if ( ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59 ) {
			return null;
		}
		try {
			return ( new DateTimeImmutable( $value ) )->getTimestamp();
		} catch ( Exception $error ) {
			return null;
		}
	}

	private function exact_object( $value, $keys ) {
		return is_array( $value ) && ! $this->is_list( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private function is_list( $value ) {
		return is_array( $value ) && ( empty( $value ) || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
	}

	private function error( $suffix, $message, $status ) {
		return new WP_Error( 'tra_vel_commerce_funds_flow_factory_' . $suffix, $message, array( 'status' => (int) $status ) );
	}
}
