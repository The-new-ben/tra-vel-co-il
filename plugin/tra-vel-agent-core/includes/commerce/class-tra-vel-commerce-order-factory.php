<?php
/**
 * Create an immutable sandbox order from one freshly revalidated package.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Order_Factory {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_AMOUNT_MINOR = 1000000000000;

	/** @var string */
	private $secret = '';

	/** @var WP_Error|null */
	private $error;

	public function __construct( $secret = null ) {
		if ( null === $secret ) {
			if ( ! function_exists( 'wp_salt' ) ) {
				$this->error = $this->error( 'order_secret_unavailable', 'The order-reference secret is unavailable.', 503 );
				return;
			}
			$secret = (string) wp_salt( 'auth' );
		}
		if ( ! is_string( $secret ) || strlen( $secret ) < 16 ) {
			$this->error = $this->error( 'order_secret_unavailable', 'The order-reference secret is unavailable.', 503 );
			return;
		}
		$this->secret = $secret . '|tra-vel-commerce-order-v1';
	}

	/**
	 * Create one deterministic order without charging or booking anything.
	 *
	 * @param array $package  Server-owned, freshly revalidated package snapshot.
	 * @param array $approval Closed approval snapshot.
	 * @param array $context  Owner, clock, and idempotency digest.
	 * @return array|WP_Error
	 */
	public function create_from_package( $package, $approval, $context ) {
		if ( is_wp_error( $this->error ) ) {
			return $this->error;
		}
		$context = $this->context( $context );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$package = $this->package( $package, $context );
		if ( is_wp_error( $package ) ) {
			return $package;
		}
		$approval = $this->approval( $approval, $context['now'] );
		if ( is_wp_error( $approval ) ) {
			return $approval;
		}

		$order_ref = $this->reference(
			'order',
			array( $context['owner_scope_digest'], $package['package_ref'], $package['package_digest'], $context['idempotency_key_digest'] )
		);
		$items = array();
		foreach ( $package['components'] as $component ) {
			$items[] = array(
				'order_item_ref'     => $this->reference( 'order_item', array( $order_ref, $component['component_ref'], $component['offer_digest'] ) ),
				'component_ref'      => $component['component_ref'],
				'role'               => $component['role'],
				'required'           => $component['required'],
				'sequence'           => $component['sequence'],
				'vertical'           => $component['vertical'],
				'provider_id'        => $component['provider_id'],
				'provider_reference_digest' => $component['provider_reference_digest'],
				'offer_ref'          => $component['offer_ref'],
				'offer_version'      => $component['offer_version'],
				'offer_digest'       => $component['offer_digest'],
				'state'              => 'selected',
				'latest_operation_ref'=> null,
				'receipt_digest'      => null,
			);
		}
		$pricing = $package['pricing'];
		foreach ( $pricing['line_items'] as $index => $line ) {
			$pricing['line_items'][ $index ] = array(
				'code'            => $line['code'],
				'label'           => $line['label'],
				'kind'            => $line['kind'],
				'direction'       => $line['direction'],
				'amount_minor'    => $line['amount_minor'],
				'source_ref'      => $line['component_ref'],
				'evidence_digest' => $line['evidence_digest'],
			);
		}

		$now_iso = gmdate( 'Y-m-d\TH:i:s\Z', $context['now'] );
		$order = array(
			'contract_version'      => self::CONTRACT_VERSION,
			'environment'           => 'sandbox',
			'order_ref'             => $order_ref,
			'version'               => 1,
			'owner_scope_digest'    => $context['owner_scope_digest'],
			'idempotency_key_digest'=> $context['idempotency_key_digest'],
			'order_digest'          => '',
			'selection'             => array(
				'kind'            => 'package',
				'package_ref'     => $package['package_ref'],
				'package_version' => $package['version'],
				'package_digest'  => $package['package_digest'],
			),
			'overall_state'         => 'pending' === $approval['status'] ? 'awaiting_approval' : 'planning',
			'checkout'              => array(
				'state'        => 'pending' === $approval['status'] ? 'awaiting_approval' : 'ready',
				'quote_digest' => $package['package_digest'],
				'quoted_at'    => $now_iso,
				'expires_at'   => $package['expires_at'],
			),
			'payment'               => array(
				'state'                   => 'not_started',
				'payment_intent_ref'      => null,
				'currency'                => $pricing['currency'],
				'authorized_amount_minor' => 0,
				'captured_amount_minor'   => 0,
				'refunded_amount_minor'   => 0,
				'receipt_digest'          => null,
			),
			'fulfillment'           => array( 'summary_state' => 'selected', 'items' => $items ),
			'settlement'            => array( 'state' => 'not_applicable', 'settlement_refs' => array() ),
			'approval'              => $approval,
			'pricing'               => $pricing,
			'last_event_sequence'   => 0,
			'created_at'            => $now_iso,
			'updated_at'            => $now_iso,
			'expires_at'            => $package['expires_at'],
			'sandbox_truth'         => $this->sandbox_truth(),
			'data_boundary'         => $this->data_boundary(),
		);
		$digest_basis = $order;
		unset( $digest_basis['order_digest'] );
		$order['order_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $digest_basis );
		$encoded = wp_json_encode( $order, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) || false !== strpos( $encoded, 'private_product_ref' ) || false !== strpos( $encoded, '"raw_supplier_reference":' ) || false !== strpos( $encoded, 'commission' ) ) {
			return $this->error( 'order_projection_failed', 'The order projection crossed a private commerce boundary.', 500 );
		}
		return $order;
	}

	private function context( $context ) {
		$keys = array( 'owner_scope_digest', 'idempotency_key_digest', 'now' );
		if ( ! $this->exact_object( $context, $keys ) || ! $this->digest( $context['owner_scope_digest'] ) || ! $this->digest( $context['idempotency_key_digest'] ) || ! is_int( $context['now'] ) || $context['now'] < 1 ) {
			return $this->error( 'order_context_invalid', 'An exact owner, idempotency, and UTC clock context is required.', 400 );
		}
		return $context;
	}

	private function package( $package, $context ) {
		$keys = array( 'contract_version', 'environment', 'package_ref', 'version', 'owner_scope_digest', 'search_session_ref', 'status', 'title', 'components', 'pricing', 'comparison', 'revalidation', 'itinerary', 'package_digest', 'created_at', 'updated_at', 'expires_at', 'sandbox_truth', 'data_boundary' );
		if ( ! $this->exact_object( $package, $keys ) || self::CONTRACT_VERSION !== $package['contract_version'] || 'sandbox' !== $package['environment'] || ! $this->ref( $package['package_ref'], 'package' ) || ! is_int( $package['version'] ) || $package['version'] < 1 || ! $this->digest( $package['owner_scope_digest'] ) || $package['owner_scope_digest'] !== $context['owner_scope_digest'] || 'composed' !== $package['status'] || ! $this->ref( $package['search_session_ref'], 'session' ) || ! $this->digest( $package['package_digest'] ) || $package['sandbox_truth'] !== $this->sandbox_truth() || $package['data_boundary'] !== $this->data_boundary() ) {
			return $this->error( 'order_package_invalid', 'The selected package is not an eligible owner-bound sandbox quote.', 400 );
		}
		$basis = $package;
		unset( $basis['package_digest'] );
		if ( ! hash_equals( $package['package_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $basis ) ) ) {
			return $this->error( 'order_package_digest_invalid', 'The selected package digest does not match its immutable snapshot.', 409 );
		}
		if ( ! $this->exact_object( $package['revalidation'], array( 'mode', 'all_components_required', 'state', 'checked_at' ) ) || 'atomic' !== $package['revalidation']['mode'] || true !== $package['revalidation']['all_components_required'] || 'fresh' !== $package['revalidation']['state'] || null === Tra_Vel_Commerce_Policy::utc_datetime( $package['revalidation']['checked_at'] ) ) {
			return $this->error( 'order_package_revalidation_required', 'Every package component must pass atomic revalidation before checkout.', 409 );
		}
		$expiry = Tra_Vel_Commerce_Policy::utc_datetime( $package['expires_at'] );
		if ( null === $expiry || strtotime( $expiry ) <= $context['now'] ) {
			return $this->error( 'order_package_expired', 'The revalidated package expired before checkout.', 409 );
		}
		if ( ! $this->is_list( $package['components'] ) || ! $package['components'] || count( $package['components'] ) > 32 ) {
			return $this->error( 'order_package_components_invalid', 'The package component list is invalid.', 400 );
		}
		$component_refs = array();
		foreach ( $package['components'] as $index => $component ) {
			$component_keys = array( 'component_ref', 'role', 'vertical', 'provider_id', 'provider_reference_digest', 'offer_ref', 'offer_version', 'offer_digest', 'required', 'sequence' );
			if ( ! $this->exact_object( $component, $component_keys ) || ! $this->ref( $component['component_ref'], 'component' ) || isset( $component_refs[ $component['component_ref'] ] ) || ! is_string( $component['role'] ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{1,47}$/', $component['role'] ) || '' === Tra_Vel_Commerce_Taxonomy::vertical( $component['vertical'] ) || 'package' === $component['vertical'] || ! is_string( $component['provider_id'] ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $component['provider_id'] ) || ! $this->digest( $component['provider_reference_digest'] ) || ! $this->ref( $component['offer_ref'], 'offer' ) || ! is_int( $component['offer_version'] ) || $component['offer_version'] < 1 || ! $this->digest( $component['offer_digest'] ) || ! is_bool( $component['required'] ) || $component['sequence'] !== $index + 1 ) {
				return $this->error( 'order_package_components_invalid', 'A package component is invalid, duplicated, or out of sequence.', 400 );
			}
			$component_refs[ $component['component_ref'] ] = true;
		}
		$pricing = $this->pricing( $package['pricing'], $component_refs );
		if ( is_wp_error( $pricing ) ) {
			return $pricing;
		}
		return $package;
	}

	private function approval( $approval, $now ) {
		$keys = array( 'status', 'approval_ref', 'scope_digest', 'decision_digest', 'expires_at' );
		if ( ! $this->exact_object( $approval, $keys ) || ! in_array( $approval['status'], array( 'not_required', 'pending', 'approved' ), true ) ) {
			return $this->error( 'order_approval_invalid', 'The checkout approval snapshot is invalid.', 400 );
		}
		if ( 'not_required' === $approval['status'] ) {
			if ( null !== $approval['approval_ref'] || null !== $approval['scope_digest'] || null !== $approval['decision_digest'] || null !== $approval['expires_at'] ) {
				return $this->error( 'order_approval_invalid', 'A not-required approval cannot carry authorization evidence.', 400 );
			}
			return $approval;
		}
		$expiry = Tra_Vel_Commerce_Policy::utc_datetime( $approval['expires_at'] );
		if ( ! $this->ref( $approval['approval_ref'], 'approval' ) || ! $this->digest( $approval['scope_digest'] ) || ! $this->digest( $approval['decision_digest'] ) || null === $expiry || strtotime( $expiry ) <= $now ) {
			return $this->error( 'order_approval_invalid', 'A pending or approved checkout requires current, scope-bound approval evidence.', 400 );
		}
		return $approval;
	}

	private function pricing( $pricing, $component_refs ) {
		$keys = array( 'currency', 'minor_unit', 'price_scope', 'line_items', 'subtotal_amount_minor', 'tax_amount_minor', 'fee_amount_minor', 'credit_amount_minor', 'total_amount_minor', 'tax_state', 'fee_state' );
		if ( ! $this->exact_object( $pricing, $keys ) || '' === Tra_Vel_Commerce_Money::currency( $pricing['currency'] ) || ! is_int( $pricing['minor_unit'] ) || $pricing['minor_unit'] !== Tra_Vel_Commerce_Money::exponent( $pricing['currency'] ) || 'package_total' !== $pricing['price_scope'] || ! $this->is_list( $pricing['line_items'] ) || ! $pricing['line_items'] ) {
			return $this->error( 'order_package_money_invalid', 'The package money ledger is invalid.', 400 );
		}
		foreach ( array( 'subtotal_amount_minor', 'tax_amount_minor', 'fee_amount_minor', 'credit_amount_minor', 'total_amount_minor' ) as $field ) {
			if ( ! is_int( $pricing[ $field ] ) || $pricing[ $field ] < 0 || $pricing[ $field ] > self::MAX_AMOUNT_MINOR ) {
				return $this->error( 'order_package_money_invalid', 'A package money total is outside the integer boundary.', 400 );
			}
		}
		$debits = Tra_Vel_Commerce_Money::add( $pricing['subtotal_amount_minor'], $pricing['tax_amount_minor'] );
		$debits = is_wp_error( $debits ) ? $debits : Tra_Vel_Commerce_Money::add( $debits, $pricing['fee_amount_minor'] );
		if ( is_wp_error( $debits ) || $pricing['credit_amount_minor'] > $debits || $pricing['total_amount_minor'] !== $debits - $pricing['credit_amount_minor'] ) {
			return $this->error( 'order_package_money_invalid', 'The package money ledger does not balance.', 400 );
		}
		foreach ( $pricing['line_items'] as $line ) {
			$line_keys = array( 'code', 'label', 'kind', 'direction', 'amount_minor', 'component_ref', 'evidence_digest' );
			if ( ! $this->exact_object( $line, $line_keys ) || ! isset( $component_refs[ $line['component_ref'] ] ) || ! is_int( $line['amount_minor'] ) || $line['amount_minor'] < 0 || $line['amount_minor'] > self::MAX_AMOUNT_MINOR || ! $this->digest( $line['evidence_digest'] ) ) {
				return $this->error( 'order_package_money_invalid', 'A package price line is invalid or detached from its component.', 400 );
			}
		}
		return $pricing;
	}

	private function reference( $kind, $parts ) {
		$digest = hash_hmac( 'sha256', $kind . '|' . Tra_Vel_Commerce_Policy::canonical_digest( $parts ), $this->secret, true );
		return 'tv_' . $kind . '_' . rtrim( strtr( base64_encode( $digest ), '+/', '-_' ), '=' );
	}

	private function ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private function exact_object( $value, $keys ) {
		return is_array( $value ) && ! $this->is_list( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private function is_list( $value ) {
		return is_array( $value ) && ( empty( $value ) || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
	}

	private function sandbox_truth() {
		return array( 'simulated_inventory' => true, 'real_supplier_request' => false, 'real_inventory_hold' => false, 'real_charge' => false, 'real_booking' => false, 'real_policy_issuance' => false, 'real_settlement' => false );
	}

	private function data_boundary() {
		return array( 'raw_supplier_reference_exposed' => false, 'raw_payment_data_exposed' => false, 'medical_data_exposed' => false );
	}

	private function error( $suffix, $message, $status ) {
		return new WP_Error( 'tra_vel_commerce_' . $suffix, $message, array( 'status' => (int) $status ) );
	}
}
