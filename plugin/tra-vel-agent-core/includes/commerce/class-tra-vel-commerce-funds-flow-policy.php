<?php
/**
 * Closed, server-only policy for per-order-item commercial funds flow.
 *
 * This policy records financial truth and validates evidence. It never calls a
 * processor, dispatches to a supplier, stores a credential, or authorizes an
 * operation.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Funds_Flow_Policy {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_MONEY_MINOR  = 1000000000000;

	const MODELS = array( 'affiliate_handoff', 'direct_commission', 'net_rate_markup' );
	const VERTICALS = array( 'flight', 'accommodation', 'package', 'transfer', 'activity', 'dining', 'insurance', 'connectivity', 'equipment' );
	const PAYMENT_STATES = array( 'not_started', 'authorization_pending', 'authorized', 'capture_pending', 'partially_captured', 'captured', 'partially_refunded', 'refunded', 'disputed', 'charged_back', 'payment_failed', 'uncertain' );
	const SETTLEMENT_STATES = array( 'not_started', 'accrued', 'partially_settled', 'settled', 'disputed' );

	/**
	 * Validate a complete immutable financial snapshot.
	 *
	 * @param array    $record Server-only funds-flow snapshot.
	 * @param int|null $now    Positive UTC epoch used for freshness checks.
	 * @return array|WP_Error
	 */
	public static function validate_snapshot( $record, $now = null ) {
		$keys = array(
			'contract_version', 'environment', 'funds_flow_ref', 'funds_flow_binding_digest',
			'version', 'previous_snapshot_digest', 'snapshot_digest', 'owner_scope_digest',
			'order_ref', 'order_version', 'order_digest', 'order_item_ref', 'offer_digest',
			'routing_binding_digest', 'provider_id', 'vertical', 'commercial_model', 'currency',
			'minor_unit_exponent', 'parties', 'commercial_terms', 'pricing', 'payment',
			'settlement', 'liabilities', 'private_routes', 'created_at', 'updated_at',
			'last_event_sequence', 'sandbox_truth', 'data_boundary',
		);
		if ( self::contains_sensitive_material( $record ) ) {
			return self::error( 'sensitive_material_rejected', 'Funds-flow records accept opaque private locators and digests only, never credentials, payment-card data, or personal data.' );
		}
		if ( ! self::exact_object( $record, $keys ) || self::CONTRACT_VERSION !== $record['contract_version'] || 'sandbox' !== $record['environment'] ) {
			return self::error( 'record_shape_invalid', 'The funds-flow snapshot is not the closed sandbox contract.' );
		}
		$now = self::clock( $now );
		if ( null === $now ) {
			return self::error( 'clock_invalid', 'A positive integer UTC validation clock is required.' );
		}

		if (
			! self::opaque_ref( $record['funds_flow_ref'], 'fflow' ) ||
			! self::digest( $record['funds_flow_binding_digest'] ) ||
			! self::digest( $record['snapshot_digest'] ) ||
			! self::nullable_digest( $record['previous_snapshot_digest'] ) ||
			! self::digest( $record['owner_scope_digest'] ) ||
			! self::opaque_ref( $record['order_ref'], 'tv_order' ) ||
			! self::digest( $record['order_digest'] ) ||
			! self::opaque_ref( $record['order_item_ref'], 'tv_order_item' ) ||
			! self::digest( $record['offer_digest'] ) ||
			! self::digest( $record['routing_binding_digest'] ) ||
			! self::provider_id( $record['provider_id'] ) ||
			! in_array( $record['vertical'], self::VERTICALS, true ) ||
			! in_array( $record['commercial_model'], self::MODELS, true ) ||
			! self::positive_int( $record['version'], 2147483647 ) ||
			! self::positive_int( $record['order_version'], 2147483647 ) ||
			! is_int( $record['last_event_sequence'] ) ||
			$record['last_event_sequence'] < 0 ||
			$record['last_event_sequence'] !== $record['version'] - 1
		) {
			return self::error( 'identity_invalid', 'Funds flow must bind one typed order item, owner, offer, route and monotonic snapshot revision.' );
		}
		if ( ( 1 === $record['version'] && null !== $record['previous_snapshot_digest'] ) || ( $record['version'] > 1 && ! self::digest( $record['previous_snapshot_digest'] ) ) ) {
			return self::error( 'snapshot_ancestry_invalid', 'Every successor snapshot must bind its exact predecessor digest.' );
		}

		$created = self::utc_timestamp( $record['created_at'] );
		$updated = self::utc_timestamp( $record['updated_at'] );
		if ( null === $created || null === $updated || $created > $updated || $updated > $now ) {
			return self::error( 'chronology_invalid', 'Funds-flow timestamps must be valid UTC observations at or before the validation clock.' );
		}
		if ( ! self::currency_exponent( $record['currency'], $record['minor_unit_exponent'] ) ) {
			return self::error( 'currency_minor_unit_invalid', 'The currency and minor-unit exponent are not a supported exact pair.' );
		}

		$parties = self::parties( $record['parties'], $record['commercial_model'] );
		if ( is_wp_error( $parties ) ) {
			return $parties;
		}
		$terms = self::commercial_terms( $record['commercial_terms'], $record['commercial_model'], $created );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		$pricing = self::pricing( $record['pricing'], $record['commercial_model'], $terms );
		if ( is_wp_error( $pricing ) ) {
			return $pricing;
		}
		$routes = self::private_routes( $record['private_routes'], $record['commercial_model'], $parties['payment_collector'] );
		if ( is_wp_error( $routes ) ) {
			return $routes;
		}
		$payment = self::payment( $record['payment'], $pricing, $parties, $created, $updated );
		if ( is_wp_error( $payment ) ) {
			return $payment;
		}
		$settlement = self::settlement( $record['settlement'], $pricing, $payment, $parties, $created, $updated );
		if ( is_wp_error( $settlement ) ) {
			return $settlement;
		}
		$liabilities = self::liabilities( $record['liabilities'], $payment, $settlement, $parties );
		if ( is_wp_error( $liabilities ) ) {
			return $liabilities;
		}
		if ( ! self::sandbox_truth( $record['sandbox_truth'] ) || ! self::data_boundary( $record['data_boundary'] ) ) {
			return self::error( 'boundary_invalid', 'The private sandbox and data-handling boundaries must remain explicit and fail closed.' );
		}

		$binding_digest = self::binding_digest( $record );
		if ( '' === $binding_digest || ! hash_equals( $record['funds_flow_binding_digest'], $binding_digest ) ) {
			return self::error( 'binding_digest_mismatch', 'The immutable commercial, supplier, rate-card, order-item or private-route binding changed in place.', 409 );
		}
		$snapshot_digest = self::snapshot_digest( $record );
		if ( '' === $snapshot_digest || ! hash_equals( $record['snapshot_digest'], $snapshot_digest ) ) {
			return self::error( 'snapshot_digest_mismatch', 'The versioned financial snapshot no longer matches its integrity digest.', 409 );
		}
		return $record;
	}

	/**
	 * Seal a constructed snapshot with deterministic binding and snapshot digests.
	 * Semantic validation is still mandatory before the record is trusted.
	 *
	 * @param array $record Unsealed record.
	 * @return array
	 */
	public static function seal_snapshot( $record ) {
		$record['funds_flow_binding_digest'] = self::binding_digest( $record );
		$record['snapshot_digest']           = self::snapshot_digest( $record );
		return $record;
	}

	/**
	 * Digest the immutable commercial binding, including private locators.
	 *
	 * @param array $record Funds-flow record.
	 * @return string Empty when required binding inputs are unavailable.
	 */
	public static function binding_digest( $record ) {
		$keys = array(
			'contract_version', 'environment', 'funds_flow_ref', 'owner_scope_digest', 'order_ref',
			'order_version', 'order_digest', 'order_item_ref', 'offer_digest', 'routing_binding_digest',
			'provider_id', 'vertical', 'commercial_model', 'currency', 'minor_unit_exponent', 'parties',
			'commercial_terms', 'pricing', 'private_routes', 'sandbox_truth', 'data_boundary',
		);
		if ( ! is_array( $record ) || array_diff( $keys, array_keys( $record ) ) ) {
			return '';
		}
		$basis = array();
		foreach ( $keys as $key ) {
			$basis[ $key ] = $record[ $key ];
		}
		return self::canonical_digest( $basis );
	}

	/**
	 * Digest the complete versioned snapshot except its own digest field.
	 *
	 * @param array $record Funds-flow record.
	 * @return string
	 */
	public static function snapshot_digest( $record ) {
		if ( ! is_array( $record ) ) {
			return '';
		}
		$basis = $record;
		unset( $basis['snapshot_digest'] );
		return self::canonical_digest( $basis );
	}

	/**
	 * Return the only projection permitted outside the private financial boundary.
	 * It intentionally excludes parties, economics, supplier identity and locators.
	 *
	 * @param array    $record Valid server-only snapshot.
	 * @param int|null $now Validation clock.
	 * @return array|WP_Error
	 */
	public static function public_projection( $record, $now = null ) {
		$validated = self::validate_snapshot( $record, $now );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		return array(
			'contract_version'                => $validated['contract_version'],
			'environment'                     => $validated['environment'],
			'order_ref'                       => $validated['order_ref'],
			'order_version'                   => $validated['order_version'],
			'order_item_ref'                  => $validated['order_item_ref'],
			'currency'                        => $validated['currency'],
			'funds_flow_binding_digest'       => $validated['funds_flow_binding_digest'],
			'snapshot_digest'                 => $validated['snapshot_digest'],
			'rate_card_revision_digest'       => $validated['commercial_terms']['rate_card_revision_digest'],
			'source_revision_digest'          => $validated['commercial_terms']['source_revision_digest'],
			'supplier_config_revision_digest' => $validated['commercial_terms']['supplier_config_revision_digest'],
			'payment_state'                   => $validated['payment']['state'],
			'settlement_state'                => $validated['settlement']['state'],
			'updated_at'                      => $validated['updated_at'],
		);
	}

	/**
	 * Prove that candidate is a one-step state successor of the same immutable binding.
	 *
	 * @return true|WP_Error
	 */
	public static function assert_successor( $current, $candidate, $now = null ) {
		$current_valid   = self::validate_snapshot( $current, $now );
		$candidate_valid = self::validate_snapshot( $candidate, $now );
		if ( is_wp_error( $current_valid ) || is_wp_error( $candidate_valid ) ) {
			return self::error( 'successor_record_invalid', 'Both funds-flow snapshots must validate at the comparison clock.', 409 );
		}
		if (
			$candidate_valid['funds_flow_binding_digest'] !== $current_valid['funds_flow_binding_digest'] ||
			$candidate_valid['funds_flow_ref'] !== $current_valid['funds_flow_ref'] ||
			$candidate_valid['version'] !== $current_valid['version'] + 1 ||
			$candidate_valid['last_event_sequence'] !== $current_valid['last_event_sequence'] + 1 ||
			$candidate_valid['previous_snapshot_digest'] !== $current_valid['snapshot_digest'] ||
			$candidate_valid['created_at'] !== $current_valid['created_at'] ||
			self::utc_timestamp( $candidate_valid['updated_at'] ) <= self::utc_timestamp( $current_valid['updated_at'] )
		) {
			return self::error( 'successor_invalid', 'A successor must advance exactly once while preserving and binding the immutable commercial identity.', 409 );
		}
		return true;
	}

	private static function parties( $value, $model ) {
		$keys = array( 'merchant_of_record', 'payment_collector', 'supplier_payee', 'commission_payee', 'refund_liability_party', 'chargeback_liability_party', 'tax_remitter' );
		$funds_parties = array( 'platform', 'supplier', 'affiliate_network' );
		$liability_parties = array( 'platform', 'supplier', 'affiliate_network', 'shared', 'not_applicable' );
		if (
			! self::exact_object( $value, $keys ) ||
			! in_array( $value['merchant_of_record'], $funds_parties, true ) ||
			! in_array( $value['payment_collector'], $funds_parties, true ) ||
			! in_array( $value['supplier_payee'], array( 'supplier', 'affiliate_network', 'internal' ), true ) ||
			! in_array( $value['commission_payee'], array( 'platform', 'affiliate_network', 'not_applicable' ), true ) ||
			! in_array( $value['refund_liability_party'], $liability_parties, true ) ||
			! in_array( $value['chargeback_liability_party'], $liability_parties, true ) ||
			! in_array( $value['tax_remitter'], $liability_parties, true )
		) {
			return self::error( 'parties_invalid', 'Merchant, collector, payee and liability roles must be explicit supported parties.' );
		}
		if ( $value['payment_collector'] !== $value['merchant_of_record'] ) {
			return self::error( 'collector_mor_mismatch', 'This sandbox contract requires one explicit merchant of record to collect the customer payment.' );
		}
		if ( ! in_array( $value['tax_remitter'], array( $value['merchant_of_record'], 'shared', 'not_applicable' ), true ) ) {
			return self::error( 'tax_party_invalid', 'Tax remittance must remain with the merchant of record, a shared arrangement, or a documented non-applicable scope.' );
		}
		if ( 'affiliate_handoff' === $model ) {
			if ( 'platform' === $value['merchant_of_record'] || ! in_array( $value['supplier_payee'], array( 'supplier', 'affiliate_network' ), true ) || 'platform' !== $value['commission_payee'] || ! in_array( $value['refund_liability_party'], array( $value['merchant_of_record'], 'shared' ), true ) || ! in_array( $value['chargeback_liability_party'], array( $value['merchant_of_record'], 'shared' ), true ) ) {
				return self::error( 'affiliate_parties_invalid', 'Affiliate handoff cannot make the platform merchant, collector, supplier payee, or sole customer-funds liability owner.' );
			}
		} elseif ( 'direct_commission' === $model ) {
			if ( ! in_array( $value['merchant_of_record'], array( 'platform', 'supplier' ), true ) || 'supplier' !== $value['supplier_payee'] || 'platform' !== $value['commission_payee'] || ! in_array( $value['refund_liability_party'], array( $value['merchant_of_record'], 'shared' ), true ) || ! in_array( $value['chargeback_liability_party'], array( $value['merchant_of_record'], 'shared' ), true ) ) {
				return self::error( 'commission_parties_invalid', 'Direct commission requires an explicit platform-or-supplier merchant, supplier payee and platform commission payee.' );
			}
		} elseif ( 'net_rate_markup' === $model ) {
			if ( 'platform' !== $value['merchant_of_record'] || 'platform' !== $value['payment_collector'] || 'supplier' !== $value['supplier_payee'] || 'not_applicable' !== $value['commission_payee'] || ! in_array( $value['refund_liability_party'], array( 'platform', 'shared' ), true ) || ! in_array( $value['chargeback_liability_party'], array( 'platform', 'shared' ), true ) ) {
				return self::error( 'net_rate_parties_invalid', 'Net-rate markup requires the platform merchant and collector, a supplier payee, and no commission payee.' );
			}
		}
		return $value;
	}

	private static function commercial_terms( $value, $model, $created ) {
		$keys = array( 'rate_card_ref', 'rate_card_revision_digest', 'source_revision_digest', 'supplier_config_revision_digest', 'effective_at', 'valid_until', 'calculation_basis', 'commission_bps', 'markup_amount_minor', 'tax_treatment', 'evidence_digest' );
		if (
			! self::exact_object( $value, $keys ) ||
			! self::opaque_ref( $value['rate_card_ref'], 'ratecard' ) ||
			! self::digest( $value['rate_card_revision_digest'] ) ||
			! self::digest( $value['source_revision_digest'] ) ||
			! self::digest( $value['supplier_config_revision_digest'] ) ||
			! self::digest( $value['evidence_digest'] ) ||
			! self::money( $value['markup_amount_minor'] ) ||
			! in_array( $value['tax_treatment'], array( 'included', 'excluded', 'not_applicable' ), true )
		) {
			return self::error( 'commercial_terms_invalid', 'Rate-card, source, supplier-configuration and calculation evidence must be complete and immutable.' );
		}
		$effective = self::utc_timestamp( $value['effective_at'] );
		$valid_until = self::utc_timestamp( $value['valid_until'] );
		if ( null === $effective || null === $valid_until || $effective > $created || $valid_until <= $created ) {
			return self::error( 'commercial_terms_chronology_invalid', 'The exact rate-card revision must have been effective for this item when the immutable funds-flow binding was created.' );
		}
		$expected_basis = array(
			'affiliate_handoff' => 'affiliate_commission',
			'direct_commission' => 'gross_less_commission',
			'net_rate_markup'    => 'supplier_net_plus_markup',
		);
		if ( $expected_basis[ $model ] !== $value['calculation_basis'] ) {
			return self::error( 'calculation_basis_mismatch', 'The rate-card calculation basis does not match the commercial model.' );
		}
		if ( 'net_rate_markup' === $model ) {
			if ( null !== $value['commission_bps'] ) {
				return self::error( 'net_rate_commission_invalid', 'Net-rate markup cannot also declare a commission rate.' );
			}
		} elseif ( ! is_int( $value['commission_bps'] ) || $value['commission_bps'] < 1 || $value['commission_bps'] > 10000 || 0 !== $value['markup_amount_minor'] ) {
			return self::error( 'commission_rate_invalid', 'Commission models require a positive basis-point rate and cannot also add markup.' );
		}
		return $value;
	}

	private static function pricing( $value, $model, $terms ) {
		$keys = array( 'customer_total_minor', 'tax_minor', 'supplier_net_minor', 'commissionable_minor', 'commission_receivable_minor', 'platform_markup_minor', 'supplier_payable_minor', 'platform_revenue_minor' );
		if ( ! self::exact_object( $value, $keys ) ) {
			return self::error( 'pricing_shape_invalid', 'Pricing must be a closed integer-minor-unit ledger.' );
		}
		foreach ( $keys as $key ) {
			if ( ! self::money( $value[ $key ] ) ) {
				return self::error( 'pricing_amount_invalid', 'Every price component must be a bounded non-negative integer in currency minor units.' );
			}
		}
		if ( 0 === $value['customer_total_minor'] || $value['tax_minor'] > $value['customer_total_minor'] ) {
			return self::error( 'pricing_total_invalid', 'Customer total must be positive and cannot contain more tax than the total.' );
		}
		if ( 'affiliate_handoff' === $model ) {
			$commission = self::basis_points_amount( $value['commissionable_minor'], $terms['commission_bps'] );
			if ( 0 === $value['commissionable_minor'] || $value['commissionable_minor'] > $value['customer_total_minor'] || $value['commission_receivable_minor'] !== $commission || 0 !== $value['supplier_net_minor'] || 0 !== $value['supplier_payable_minor'] || 0 !== $value['platform_markup_minor'] || $value['platform_revenue_minor'] !== $commission ) {
				return self::error( 'affiliate_pricing_invalid', 'Affiliate pricing records commission only; it cannot invent platform-collected supplier funds, net rate, or markup.' );
			}
		} elseif ( 'direct_commission' === $model ) {
			$commission = self::basis_points_amount( $value['commissionable_minor'], $terms['commission_bps'] );
			if ( 0 === $value['commissionable_minor'] || $value['commissionable_minor'] > $value['customer_total_minor'] || $value['commission_receivable_minor'] !== $commission || 0 !== $value['platform_markup_minor'] || $value['platform_revenue_minor'] !== $commission || $value['supplier_net_minor'] !== $value['supplier_payable_minor'] || $value['customer_total_minor'] !== $value['supplier_payable_minor'] + $commission ) {
				return self::error( 'commission_pricing_invalid', 'Direct commission must reconcile gross customer value into supplier payable plus the exact commission.' );
			}
		} else {
			if ( 0 !== $value['commissionable_minor'] || 0 !== $value['commission_receivable_minor'] || $value['platform_markup_minor'] !== $terms['markup_amount_minor'] || $value['platform_revenue_minor'] !== $value['platform_markup_minor'] || $value['supplier_payable_minor'] !== $value['supplier_net_minor'] || $value['customer_total_minor'] !== $value['supplier_net_minor'] + $value['platform_markup_minor'] ) {
				return self::error( 'net_rate_pricing_invalid', 'Net-rate pricing must reconcile supplier net plus the immutable platform markup, without commission.' );
			}
		}
		return $value;
	}

	private static function payment( $value, $pricing, $parties, $created, $updated ) {
		$keys = array( 'state', 'authority', 'authorized_amount_minor', 'captured_amount_minor', 'refunded_amount_minor', 'disputed_amount_minor', 'charged_back_amount_minor', 'processor_payment_ref', 'latest_event_digest', 'updated_at' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['state'], self::PAYMENT_STATES, true ) ) {
			return self::error( 'payment_shape_invalid', 'Payment state is outside the closed funds-flow contract.' );
		}
		$expected_authority = 'platform' === $parties['payment_collector'] ? 'platform_processor' : 'supplier_reported';
		if ( $expected_authority !== $value['authority'] ) {
			return self::error( 'payment_authority_invalid', 'Payment evidence authority must match the declared collector and merchant of record.' );
		}
		$amount_keys = array( 'authorized_amount_minor', 'captured_amount_minor', 'refunded_amount_minor', 'disputed_amount_minor', 'charged_back_amount_minor' );
		foreach ( $amount_keys as $key ) {
			if ( ! self::money( $value[ $key ] ) ) {
				return self::error( 'payment_amount_invalid', 'Payment amounts must be bounded non-negative integer minor units.' );
			}
		}
		$authorized = $value['authorized_amount_minor'];
		$captured   = $value['captured_amount_minor'];
		$refunded   = $value['refunded_amount_minor'];
		$disputed   = $value['disputed_amount_minor'];
		$charged    = $value['charged_back_amount_minor'];
		if ( $authorized > $pricing['customer_total_minor'] || $captured > $authorized || $refunded > $captured || $disputed > $captured - $refunded || $charged > $disputed || $charged > $captured - $refunded ) {
			return self::error( 'payment_amount_order_invalid', 'Authorization, capture, refund, dispute and chargeback amounts must remain ordered and bounded by the item total.' );
		}
		$payment_updated = self::utc_timestamp( $value['updated_at'] );
		if ( null === $payment_updated || $payment_updated < $created || $payment_updated > $updated || ! self::nullable_opaque_ref( $value['processor_payment_ref'], 'paytxn' ) || ! self::nullable_digest( $value['latest_event_digest'] ) ) {
			return self::error( 'payment_evidence_invalid', 'Payment evidence requires a private transaction locator, digest and valid chronology.' );
		}
		$all_zero = 0 === $authorized && 0 === $captured && 0 === $refunded && 0 === $disputed && 0 === $charged;
		$has_ref  = null !== $value['processor_payment_ref'];
		$has_event = null !== $value['latest_event_digest'];
		$valid_state = false;
		switch ( $value['state'] ) {
			case 'not_started':
				$valid_state = $all_zero && ! $has_ref && ! $has_event;
				break;
			case 'authorization_pending':
				$valid_state = $all_zero && $has_ref && $has_event;
				break;
			case 'authorized':
				$valid_state = $authorized > 0 && 0 === $captured && 0 === $refunded && 0 === $disputed && 0 === $charged && $has_ref && $has_event;
				break;
			case 'capture_pending':
				$valid_state = $authorized > 0 && $captured < $authorized && 0 === $refunded && 0 === $disputed && 0 === $charged && $has_ref && $has_event;
				break;
			case 'partially_captured':
				$valid_state = $captured > 0 && $captured < $pricing['customer_total_minor'] && 0 === $refunded && 0 === $disputed && 0 === $charged && $has_ref && $has_event;
				break;
			case 'captured':
				$valid_state = $captured === $pricing['customer_total_minor'] && 0 === $refunded && 0 === $disputed && 0 === $charged && $has_ref && $has_event;
				break;
			case 'partially_refunded':
				$valid_state = $refunded > 0 && $refunded < $captured && 0 === $disputed && 0 === $charged && $has_ref && $has_event;
				break;
			case 'refunded':
				$valid_state = $captured > 0 && $refunded === $captured && 0 === $disputed && 0 === $charged && $has_ref && $has_event;
				break;
			case 'disputed':
				$valid_state = $disputed > 0 && 0 === $charged && $has_ref && $has_event;
				break;
			case 'charged_back':
				$valid_state = $charged > 0 && $has_ref && $has_event;
				break;
			case 'payment_failed':
				$valid_state = $all_zero && $has_event;
				break;
			case 'uncertain':
				$valid_state = $has_ref && $has_event;
				break;
		}
		if ( ! $valid_state ) {
			return self::error( 'payment_state_amount_mismatch', 'The payment state is inconsistent with its authorization, capture, refund, dispute, chargeback or evidence amounts.' );
		}
		return $value;
	}

	private static function settlement( $value, $pricing, $payment, $parties, $created, $updated ) {
		$keys = array( 'state', 'supplier_due_minor', 'supplier_paid_minor', 'commission_due_minor', 'commission_received_minor', 'chargeback_recovery_due_minor', 'chargeback_recovered_minor', 'latest_reconciliation_digest', 'due_at', 'updated_at' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['state'], self::SETTLEMENT_STATES, true ) ) {
			return self::error( 'settlement_shape_invalid', 'Settlement state is outside the closed funds-flow contract.' );
		}
		$amount_keys = array( 'supplier_due_minor', 'supplier_paid_minor', 'commission_due_minor', 'commission_received_minor', 'chargeback_recovery_due_minor', 'chargeback_recovered_minor' );
		foreach ( $amount_keys as $key ) {
			if ( ! self::money( $value[ $key ] ) ) {
				return self::error( 'settlement_amount_invalid', 'Settlement amounts must be bounded non-negative integer minor units.' );
			}
		}
		if ( $value['supplier_paid_minor'] > $value['supplier_due_minor'] || $value['commission_received_minor'] > $value['commission_due_minor'] || $value['chargeback_recovered_minor'] > $value['chargeback_recovery_due_minor'] || $value['chargeback_recovery_due_minor'] > $payment['charged_back_amount_minor'] ) {
			return self::error( 'settlement_amount_order_invalid', 'Paid, received and recovered amounts cannot exceed their recognized obligations.' );
		}
		if ( 'platform' === $parties['payment_collector'] ) {
			if ( 0 !== $value['commission_due_minor'] || 0 !== $value['commission_received_minor'] || $value['supplier_due_minor'] > $pricing['supplier_payable_minor'] ) {
				return self::error( 'platform_collection_settlement_invalid', 'When the platform collects, settlement may accrue a bounded supplier payable but not an external commission receivable.' );
			}
		} elseif ( 0 !== $value['supplier_due_minor'] || 0 !== $value['supplier_paid_minor'] || $value['commission_due_minor'] > $pricing['commission_receivable_minor'] ) {
			return self::error( 'external_collection_settlement_invalid', 'When the supplier or affiliate collects, settlement may accrue commission receivable but not a platform supplier payable.' );
		}
		$settlement_updated = self::utc_timestamp( $value['updated_at'] );
		$due_at = self::nullable_utc_timestamp( $value['due_at'] );
		if ( null === $settlement_updated || $settlement_updated < $created || $settlement_updated > $updated || false === $due_at || ! self::nullable_digest( $value['latest_reconciliation_digest'] ) ) {
			return self::error( 'settlement_evidence_invalid', 'Settlement evidence and due date must have valid UTC chronology.' );
		}
		$total_due = $value['supplier_due_minor'] + $value['commission_due_minor'] + $value['chargeback_recovery_due_minor'];
		$total_settled = $value['supplier_paid_minor'] + $value['commission_received_minor'] + $value['chargeback_recovered_minor'];
		$fully_settled = $value['supplier_paid_minor'] === $value['supplier_due_minor'] && $value['commission_received_minor'] === $value['commission_due_minor'] && $value['chargeback_recovered_minor'] === $value['chargeback_recovery_due_minor'];
		$has_evidence = null !== $value['latest_reconciliation_digest'];
		$valid_state = false;
		if ( 'not_started' === $value['state'] ) {
			$valid_state = 0 === $total_due && 0 === $total_settled && ! $has_evidence && null === $value['due_at'];
		} elseif ( 'accrued' === $value['state'] ) {
			$valid_state = $total_due > 0 && 0 === $total_settled && $has_evidence && null !== $value['due_at'];
		} elseif ( 'partially_settled' === $value['state'] ) {
			$valid_state = $total_due > 0 && $total_settled > 0 && ! $fully_settled && $has_evidence && null !== $value['due_at'];
		} elseif ( 'settled' === $value['state'] ) {
			$valid_state = $total_due > 0 && $fully_settled && $has_evidence && null !== $value['due_at'];
		} else {
			$valid_state = $has_evidence && in_array( $payment['state'], array( 'disputed', 'charged_back' ), true );
		}
		if ( ! $valid_state ) {
			return self::error( 'settlement_state_amount_mismatch', 'Settlement state must agree with due, paid, commission, recovery, dispute and reconciliation evidence.' );
		}
		return $value;
	}

	private static function liabilities( $value, $payment, $settlement, $parties ) {
		$keys = array( 'customer_refund_due_minor', 'supplier_payable_outstanding_minor', 'commission_receivable_outstanding_minor', 'chargeback_liability_minor', 'chargeback_liability_party' );
		if ( ! self::exact_object( $value, $keys ) ) {
			return self::error( 'liabilities_shape_invalid', 'Liabilities must be a closed per-item ledger.' );
		}
		foreach ( array( 'customer_refund_due_minor', 'supplier_payable_outstanding_minor', 'commission_receivable_outstanding_minor', 'chargeback_liability_minor' ) as $key ) {
			if ( ! self::money( $value[ $key ] ) ) {
				return self::error( 'liability_amount_invalid', 'Liabilities must use bounded non-negative integer minor units.' );
			}
		}
		$available_customer_funds = $payment['captured_amount_minor'] - $payment['refunded_amount_minor'] - $payment['charged_back_amount_minor'];
		if (
			$value['customer_refund_due_minor'] > $available_customer_funds ||
			$value['supplier_payable_outstanding_minor'] !== $settlement['supplier_due_minor'] - $settlement['supplier_paid_minor'] ||
			$value['commission_receivable_outstanding_minor'] !== $settlement['commission_due_minor'] - $settlement['commission_received_minor'] ||
			$value['chargeback_liability_minor'] !== $payment['charged_back_amount_minor'] ||
			$value['chargeback_liability_party'] !== $parties['chargeback_liability_party']
		) {
			return self::error( 'liability_reconciliation_invalid', 'Customer refunds, supplier payables, commission receivables and chargebacks must reconcile to payment and settlement ledgers.' );
		}
		return $value;
	}

	private static function private_routes( $value, $model, $collector ) {
		$keys = array( 'private_routing_record_ref', 'payment_route_ref', 'settlement_route_ref', 'supplier_payable_route_ref' );
		if (
			! self::exact_object( $value, $keys ) ||
			! self::opaque_ref( $value['private_routing_record_ref'], 'tvr_binding' ) ||
			! self::opaque_ref( $value['payment_route_ref'], 'payroute' ) ||
			! self::opaque_ref( $value['settlement_route_ref'], 'setroute' ) ||
			! self::nullable_opaque_ref( $value['supplier_payable_route_ref'], 'payout' )
		) {
			return self::error( 'private_routes_invalid', 'Funds routes must be opaque server-only locators with no endpoint or credential material.' );
		}
		$requires_supplier_payout = 'platform' === $collector && 'affiliate_handoff' !== $model;
		if ( $requires_supplier_payout !== ( null !== $value['supplier_payable_route_ref'] ) ) {
			return self::error( 'supplier_payable_route_invalid', 'A supplier payout route exists only when the platform collects direct or net-rate customer funds.' );
		}
		return $value;
	}

	private static function sandbox_truth( $value ) {
		return self::exact_object( $value, array( 'simulated', 'real_processor_call', 'real_customer_charge', 'real_supplier_payment', 'real_settlement' ) ) && true === $value['simulated'] && false === $value['real_processor_call'] && false === $value['real_customer_charge'] && false === $value['real_supplier_payment'] && false === $value['real_settlement'];
	}

	private static function data_boundary( $value ) {
		return self::exact_object( $value, array( 'server_only', 'public_serialization_allowed', 'contains_private_locators', 'raw_credentials_stored', 'raw_payment_data_stored', 'personal_data_stored' ) ) && true === $value['server_only'] && false === $value['public_serialization_allowed'] && true === $value['contains_private_locators'] && false === $value['raw_credentials_stored'] && false === $value['raw_payment_data_stored'] && false === $value['personal_data_stored'];
	}

	private static function basis_points_amount( $amount, $basis_points ) {
		return intdiv( ( $amount * $basis_points ) + 5000, 10000 );
	}

	private static function currency_exponent( $currency, $exponent ) {
		if ( ! is_string( $currency ) || ! is_int( $exponent ) ) {
			return false;
		}
		$two_decimal = array(
			'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN',
			'BAM', 'BBD', 'BDT', 'BGN', 'BMD', 'BND', 'BOB', 'BOV', 'BRL', 'BSD', 'BTN', 'BWP', 'BYN', 'BZD',
			'CAD', 'CDF', 'CHE', 'CHF', 'CHW', 'CNY', 'COP', 'COU', 'CRC', 'CUP', 'CVE', 'CZK',
			'DKK', 'DOP', 'DZD', 'EGP', 'ERN', 'ETB', 'EUR', 'FJD', 'FKP',
			'GBP', 'GEL', 'GHS', 'GIP', 'GMD', 'GTQ', 'GYD', 'HKD', 'HNL', 'HTG', 'HUF',
			'IDR', 'ILS', 'INR', 'IRR', 'JMD', 'KES', 'KGS', 'KHR', 'KPW', 'KYD', 'KZT',
			'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRU', 'MUR', 'MVR', 'MWK', 'MXN', 'MXV', 'MYR', 'MZN',
			'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN',
			'QAR', 'RON', 'RSD', 'RUB', 'SAR', 'SBD', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP', 'SLE', 'SOS', 'SRD', 'SSP', 'STN', 'SVC', 'SYP', 'SZL',
			'THB', 'TJS', 'TMT', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'USD', 'USN', 'UYU', 'UZS',
			'VED', 'VES', 'WST', 'XCD', 'YER', 'ZAR', 'ZMW', 'ZWG',
		);
		$map = array_fill_keys( $two_decimal, 2 );
		foreach ( array( 'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'UYI', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' ) as $code ) {
			$map[ $code ] = 0;
		}
		foreach ( array( 'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND' ) as $code ) {
			$map[ $code ] = 3;
		}
		$map['CLF'] = 4;
		$map['UYW'] = 4;
		return isset( $map[ $currency ] ) && $map[ $currency ] === $exponent;
	}

	private static function money( $value ) {
		return is_int( $value ) && $value >= 0 && $value <= self::MAX_MONEY_MINOR;
	}

	private static function positive_int( $value, $max ) {
		return is_int( $value ) && $value > 0 && $value <= $max;
	}

	private static function provider_id( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $value );
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function nullable_digest( $value ) {
		return null === $value || self::digest( $value );
	}

	private static function opaque_ref( $value, $prefix ) {
		return is_string( $value ) && 1 === preg_match( '/^' . preg_quote( $prefix, '/' ) . '_[A-Za-z0-9][A-Za-z0-9_-]{15,95}$/', $value );
	}

	private static function nullable_opaque_ref( $value, $prefix ) {
		return null === $value || self::opaque_ref( $value, $prefix );
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function contains_sensitive_material( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				if ( preg_match( '/(?:^|_)(?:api_?key|secret|password|bearer|access_?token|refresh_?token|private_?key|cvv|cvc|card_?number|pan|iban|passport|medical|email|phone|traveler_?name|full_?name)(?:$|_)/i', (string) $key ) || self::contains_sensitive_material( $child ) ) {
					return true;
				}
			}
			return false;
		}
		if ( ! is_string( $value ) ) {
			return false;
		}
		if ( preg_match( '/-----BEGIN [A-Z ]*PRIVATE KEY-----|\bBearer\s+[A-Za-z0-9._~+\/=\-]{8,}|\bsk-[A-Za-z0-9_-]{12,}/i', $value ) || preg_match( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value ) ) {
			return true;
		}
		$digits = preg_replace( '/\D+/', '', $value );
		return is_string( $digits ) && strlen( $digits ) >= 12 && strlen( $digits ) <= 19 && 1 === preg_match( '/^[0-9 ()+\-]+$/', $value );
	}

	private static function utc_timestamp( $value ) {
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

	private static function nullable_utc_timestamp( $value ) {
		return null === $value ? null : ( null === self::utc_timestamp( $value ) ? false : self::utc_timestamp( $value ) );
	}

	private static function clock( $value ) {
		if ( null === $value ) {
			return time();
		}
		return is_int( $value ) && $value > 0 ? $value : null;
	}

	private static function canonical_digest( $value ) {
		$encoded = wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_values( $value ) !== $value ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}
		return $value;
	}

	private static function error( $suffix, $message, $status = 400 ) {
		return new WP_Error( 'tra_vel_commerce_funds_flow_' . $suffix, $message, array( 'status' => $status ) );
	}
}
