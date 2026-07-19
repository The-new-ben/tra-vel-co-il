<?php
/**
 * Create closed, idempotent, version-bound sandbox commerce operations.
 *
 * This factory does not dispatch a provider request. It proves that a proposed
 * side effect belongs to the current order revision, targets the correct
 * provider capability, and carries the required approval evidence before an
 * executor is allowed to see it.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Operation_Factory {
	const CONTRACT_VERSION = '1.0.0';

	const TYPE_CAPABILITIES = array(
		'revalidate'             => 'revalidate',
		'prepare_checkout'       => 'revalidate',
		'reserve'                => 'reserve',
		'confirm'                => 'confirm',
		'fulfill'                => 'fulfill',
		'change'                 => 'change',
		'cancel'                 => 'cancel',
		'authorize_payment'      => 'payment_authorize',
		'capture_payment'        => 'payment_capture',
		'void_payment'           => 'payment_void',
		'record_affiliate_click' => 'report_conversion',
		'report_conversion'      => 'report_conversion',
		'ingest_webhook'         => 'webhook',
		'reconcile_settlement'   => 'settlement_reconcile',
		'reconcile'              => 'reconcile',
	);

	const APPROVAL_REQUIRED = array(
		'reserve',
		'confirm',
		'fulfill',
		'change',
		'cancel',
		'authorize_payment',
		'capture_payment',
		'void_payment',
		'refund',
	);

	/** @var string */
	private $secret = '';

	/** @var Tra_Vel_Commerce_Sandbox_Network */
	private $network;

	/** @var WP_Error|null */
	private $error;

	/**
	 * @param string|null                             $secret  Server-only reference secret.
	 * @param Tra_Vel_Commerce_Sandbox_Network|null $network Validated provider network.
	 */
	public function __construct( $secret = null, $network = null ) {
		if ( null === $secret ) {
			if ( ! function_exists( 'wp_salt' ) ) {
				$this->error = $this->error( 'operation_secret_unavailable', 'The operation-reference secret is unavailable.', 503 );
				return;
			}
			$secret = (string) wp_salt( 'auth' );
		}
		if ( ! is_string( $secret ) || strlen( $secret ) < 16 ) {
			$this->error = $this->error( 'operation_secret_unavailable', 'The operation-reference secret is unavailable.', 503 );
			return;
		}
		if ( null !== $network && ! $network instanceof Tra_Vel_Commerce_Sandbox_Network ) {
			$this->error = $this->error( 'operation_network_invalid', 'The operation provider network is invalid.', 500 );
			return;
		}
		$this->secret  = $secret . '|tra-vel-commerce-operation-v1';
		$this->network = null === $network ? new Tra_Vel_Commerce_Sandbox_Network() : $network;
	}

	/**
	 * Create one queued operation. No provider or payment side effect occurs.
	 *
	 * @param array $order   Current authoritative order snapshot.
	 * @param array $command Closed operation command.
	 * @param array $context Closed owner, idempotency, and clock context.
	 * @return array|WP_Error
	 */
	public function create( $order, $command, $context ) {
		if ( is_wp_error( $this->error ) ) {
			return $this->error;
		}
		$context = $this->context( $context );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$requested_type = is_array( $command ) && isset( $command['type'] ) ? sanitize_key( (string) $command['type'] ) : '';
		$order = $this->order( $order, $context, $requested_type );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$command = $this->command( $command, $order, $context );
		if ( is_wp_error( $command ) ) {
			return $command;
		}

		$provider = $this->provider( $command['provider_id'], $command['vertical'], $command['type'], $command['target']['kind'] );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		$operation_ref = $this->reference(
			'operation',
			array(
				$order['order_ref'],
				$order['version'],
				$command['type'],
				$command['target'],
				$context['idempotency_key_digest'],
			)
		);
		$now_iso = gmdate( 'Y-m-d\TH:i:s\Z', $context['now'] );
		$request_basis = array(
			'type'         => $command['type'],
			'vertical'     => $command['vertical'],
			'provider_id'  => $command['provider_id'],
			'target'       => $command['target'],
			'scope_digest' => $command['scope_digest'],
			'input_digest' => $command['input_digest'],
		);
		$operation = array(
			'contract_version'       => self::CONTRACT_VERSION,
			'environment'            => 'sandbox',
			'operation_ref'          => $operation_ref,
			'order_ref'              => $order['order_ref'],
			'expected_order_version' => $order['version'],
			'type'                   => $command['type'],
			'vertical'               => $command['vertical'],
			'provider_id'            => $command['provider_id'],
			'target'                 => $command['target'],
			'state'                  => 'queued',
			'idempotency_key_digest' => $context['idempotency_key_digest'],
			'request_digest'         => Tra_Vel_Commerce_Policy::canonical_digest( $request_basis ),
			'scope_digest'           => $command['scope_digest'],
			'approval'               => $command['approval'],
			'attempt'                => array(
				'number'               => 0,
				'maximum'              => $command['maximum_attempts'],
				'reconciliation_count' => 0,
			),
			'result'                 => array(
				'receipt_digest'               => null,
				'error_code'                    => null,
				'retryable'                     => false,
				'simulated_side_effect_executed'=> false,
				'real_side_effect_executed'     => false,
				'reconciled'                     => false,
			),
			'created_at'             => $now_iso,
			'updated_at'             => $now_iso,
			'dispatched_at'          => null,
			'completed_at'           => null,
			'sandbox_truth'          => $this->sandbox_truth(),
			'data_boundary'          => $this->data_boundary(),
		);
		$encoded = wp_json_encode( $operation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) || false !== strpos( $encoded, 'private_product_ref' ) || false !== strpos( $encoded, '"raw_supplier_reference":' ) || false !== strpos( $encoded, 'payment_token' ) || false !== strpos( $encoded, 'card_number' ) ) {
			return $this->error( 'operation_projection_failed', 'The operation crossed a private commerce boundary.', 500 );
		}
		return $operation;
	}

	private function context( $context ) {
		$keys = array( 'owner_scope_digest', 'idempotency_key_digest', 'now' );
		if ( ! $this->exact_object( $context, $keys ) || ! $this->digest( $context['owner_scope_digest'] ) || ! $this->digest( $context['idempotency_key_digest'] ) || ! is_int( $context['now'] ) || $context['now'] < 1 ) {
			return $this->error( 'operation_context_invalid', 'An exact owner, idempotency, and UTC clock context is required.', 400 );
		}
		return $context;
	}

	private function order( $order, $context, $operation_type ) {
		$keys = array( 'contract_version', 'environment', 'order_ref', 'version', 'owner_scope_digest', 'idempotency_key_digest', 'order_digest', 'selection', 'overall_state', 'checkout', 'payment', 'fulfillment', 'settlement', 'approval', 'pricing', 'last_event_sequence', 'created_at', 'updated_at', 'expires_at', 'sandbox_truth', 'data_boundary' );
		if ( ! $this->exact_object( $order, $keys ) || self::CONTRACT_VERSION !== $order['contract_version'] || 'sandbox' !== $order['environment'] || ! $this->ref( $order['order_ref'], 'order' ) || ! is_int( $order['version'] ) || $order['version'] < 1 || ! $this->digest( $order['owner_scope_digest'] ) || ! hash_equals( $order['owner_scope_digest'], $context['owner_scope_digest'] ) || ! $this->digest( $order['order_digest'] ) || $order['sandbox_truth'] !== $this->sandbox_truth() || $order['data_boundary'] !== $this->data_boundary() ) {
			return $this->error( 'operation_order_invalid', 'The operation requires the current owner-bound sandbox order.', 400 );
		}
		$basis = $order;
		unset( $basis['order_digest'] );
		if ( ! hash_equals( $order['order_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $basis ) ) ) {
			return $this->error( 'operation_order_digest_invalid', 'The order snapshot was modified after its digest was issued.', 409 );
		}
		if ( ! is_array( $order['checkout'] ) || ! isset( $order['checkout']['state'] ) || 'ready' !== $order['checkout']['state'] ) {
			return $this->error( 'operation_checkout_not_ready', 'The order checkout is not ready for an operation.', 409 );
		}
		$expiry = Tra_Vel_Commerce_Policy::utc_datetime( $order['expires_at'] );
		$quote_bound_types = array( 'revalidate', 'prepare_checkout', 'reserve', 'confirm', 'authorize_payment', 'capture_payment' );
		if ( null === $expiry || ( in_array( $operation_type, $quote_bound_types, true ) && strtotime( $expiry ) <= $context['now'] ) ) {
			return $this->error( 'operation_order_expired', 'The order quote expired before the operation was created.', 409 );
		}
		return $order;
	}

	private function command( $command, $order, $context ) {
		$keys = array( 'type', 'vertical', 'provider_id', 'target', 'scope_digest', 'input_digest', 'approval', 'maximum_attempts' );
		if ( ! $this->exact_object( $command, $keys ) ) {
			return $this->error( 'operation_command_invalid', 'The operation command is not a closed supported contract.', 400 );
		}
		$type = sanitize_key( (string) $command['type'] );
		$vertical = Tra_Vel_Commerce_Taxonomy::vertical( $command['vertical'] );
		$provider_id = sanitize_key( (string) $command['provider_id'] );
		if ( ( ! isset( self::TYPE_CAPABILITIES[ $type ] ) && 'refund' !== $type ) || '' === $vertical || ! preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $provider_id ) || ! $this->digest( $command['scope_digest'] ) || ! $this->digest( $command['input_digest'] ) || ! is_int( $command['maximum_attempts'] ) || $command['maximum_attempts'] < 1 || $command['maximum_attempts'] > 20 ) {
			return $this->error( 'operation_command_invalid', 'The operation command contains an unsupported type, identity, scope, or retry bound.', 400 );
		}
		$target = $this->target( $command['target'], $order, $type, $vertical, $provider_id );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		$approval = $this->approval( $command['approval'], in_array( $type, self::APPROVAL_REQUIRED, true ), $command['scope_digest'], $context['now'] );
		if ( is_wp_error( $approval ) ) {
			return $approval;
		}
		return array(
			'type'             => $type,
			'vertical'         => $vertical,
			'provider_id'      => $provider_id,
			'target'           => $target,
			'scope_digest'     => $command['scope_digest'],
			'input_digest'     => $command['input_digest'],
			'approval'         => $approval,
			'maximum_attempts' => $command['maximum_attempts'],
		);
	}

	private function target( $target, $order, $type, $vertical, $provider_id ) {
		$keys = array( 'kind', 'ref', 'version', 'target_digest' );
		if ( ! $this->exact_object( $target, $keys ) || ! is_int( $target['version'] ) || $target['version'] < 1 || ! $this->digest( $target['target_digest'] ) ) {
			return $this->error( 'operation_target_invalid', 'The operation target is invalid.', 400 );
		}
		$kind = sanitize_key( (string) $target['kind'] );
		$allowed_kinds = array(
			'revalidate'             => array( 'package' ),
			'prepare_checkout'       => array( 'order' ),
			'reserve'                => array( 'order_item' ),
			'confirm'                => array( 'order_item' ),
			'fulfill'                => array( 'order_item' ),
			'change'                 => array( 'order_item' ),
			'cancel'                 => array( 'order_item' ),
			'authorize_payment'      => array( 'order' ),
			'capture_payment'        => array( 'payment_intent' ),
			'void_payment'           => array( 'payment_intent' ),
			'refund'                 => array( 'order_item', 'payment_intent' ),
			'record_affiliate_click' => array( 'order_item' ),
			'report_conversion'      => array( 'order_item' ),
			'ingest_webhook'         => array( 'order', 'order_item', 'payment_intent' ),
			'reconcile_settlement'   => array( 'settlement' ),
			'reconcile'              => array( 'order_item', 'payment_intent' ),
		);
		if ( ! isset( $allowed_kinds[ $type ] ) || ! in_array( $kind, $allowed_kinds[ $type ], true ) ) {
			return $this->error( 'operation_target_invalid', 'The operation type cannot act on this target kind.', 400 );
		}

		if ( 'order_item' === $kind ) {
			if ( ! $this->ref( $target['ref'], 'order_item' ) || ! isset( $order['fulfillment']['items'] ) || ! is_array( $order['fulfillment']['items'] ) ) {
				return $this->error( 'operation_target_invalid', 'The order-item target is invalid.', 400 );
			}
			$match = null;
			foreach ( $order['fulfillment']['items'] as $item ) {
				if ( isset( $item['order_item_ref'] ) && hash_equals( (string) $item['order_item_ref'], (string) $target['ref'] ) ) {
					$match = $item;
					break;
				}
			}
			if ( null === $match || ! isset( $match['vertical'], $match['provider_id'] ) || $vertical !== $match['vertical'] || $provider_id !== $match['provider_id'] || $target['version'] !== $order['version'] || ! hash_equals( $target['target_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $match ) ) ) {
				return $this->error( 'operation_target_mismatch', 'The order-item target does not match the current provider-bound item snapshot.', 409 );
			}
		} elseif ( 'order' === $kind ) {
			if ( ! $this->ref( $target['ref'], 'order' ) || $target['ref'] !== $order['order_ref'] || $target['version'] !== $order['version'] || ! hash_equals( $target['target_digest'], $order['order_digest'] ) ) {
				return $this->error( 'operation_target_mismatch', 'The order target does not match the current order revision.', 409 );
			}
		} elseif ( 'payment_intent' === $kind ) {
			$payment_ref = isset( $order['payment']['payment_intent_ref'] ) ? $order['payment']['payment_intent_ref'] : null;
			if ( ! $this->ref( $target['ref'], 'payment' ) || ! is_string( $payment_ref ) || ! hash_equals( $payment_ref, $target['ref'] ) || $target['version'] !== $order['version'] || ! hash_equals( $target['target_digest'], Tra_Vel_Commerce_Policy::canonical_digest( $order['payment'] ) ) ) {
				return $this->error( 'operation_target_mismatch', 'The payment target does not match the current order payment snapshot.', 409 );
			}
		} elseif ( 'package' === $kind ) {
			$selection = isset( $order['selection'] ) && is_array( $order['selection'] ) ? $order['selection'] : array();
			if ( ! isset( $selection['kind'], $selection['package_ref'], $selection['package_version'], $selection['package_digest'] ) || 'package' !== $selection['kind'] || ! $this->ref( $target['ref'], 'package' ) || $target['ref'] !== $selection['package_ref'] || $target['version'] !== $selection['package_version'] || ! hash_equals( $target['target_digest'], $selection['package_digest'] ) ) {
				return $this->error( 'operation_target_mismatch', 'The package target does not match the order selection revision.', 409 );
			}
		} elseif ( 'settlement' === $kind ) {
			$settlement_refs = isset( $order['settlement']['settlement_refs'] ) && is_array( $order['settlement']['settlement_refs'] ) ? $order['settlement']['settlement_refs'] : array();
			if ( ! $this->ref( $target['ref'], 'settlement' ) || ! in_array( $target['ref'], $settlement_refs, true ) ) {
				return $this->error( 'operation_target_mismatch', 'The settlement target is not bound to this order.', 409 );
			}
		} else {
			return $this->error( 'operation_target_invalid', 'The operation target kind or reference is unsupported.', 400 );
		}
		return array( 'kind' => $kind, 'ref' => $target['ref'], 'version' => $target['version'], 'target_digest' => $target['target_digest'] );
	}

	private function approval( $approval, $required, $scope_digest, $now ) {
		$keys = array( 'required', 'approval_ref', 'approval_digest', 'status', 'scope_digest', 'expires_at' );
		if ( ! $this->exact_object( $approval, $keys ) || $approval['required'] !== $required ) {
			return $this->error( 'operation_approval_invalid', 'The operation approval requirement is invalid.', 400 );
		}
		if ( ! $required ) {
			if ( null !== $approval['approval_ref'] || null !== $approval['approval_digest'] || 'not_required' !== $approval['status'] || null !== $approval['scope_digest'] || null !== $approval['expires_at'] ) {
				return $this->error( 'operation_approval_invalid', 'A non-consequential operation cannot carry approval evidence.', 400 );
			}
			return array( 'required' => false, 'approval_ref' => null, 'approval_digest' => null, 'status' => 'not_required' );
		}
		$expiry = Tra_Vel_Commerce_Policy::utc_datetime( $approval['expires_at'] );
		if ( 'approved' !== $approval['status'] || ! $this->ref( $approval['approval_ref'], 'approval' ) || ! $this->digest( $approval['approval_digest'] ) || ! $this->digest( $approval['scope_digest'] ) || ! hash_equals( $scope_digest, $approval['scope_digest'] ) || null === $expiry || strtotime( $expiry ) <= $now ) {
			return $this->error( 'operation_approval_invalid', 'A consequential operation requires current approval bound to its exact scope.', 403 );
		}
		return array( 'required' => true, 'approval_ref' => $approval['approval_ref'], 'approval_digest' => $approval['approval_digest'], 'status' => 'approved' );
	}

	private function provider( $provider_id, $vertical, $type, $target_kind ) {
		$providers = $this->network->all();
		if ( is_wp_error( $providers ) ) {
			return $providers;
		}
		$match = null;
		foreach ( $providers as $provider ) {
			if ( $provider_id === $provider['provider_id'] ) {
				$match = $provider;
				break;
			}
		}
		if ( null === $match || 'ready' !== $match['readiness']['status'] || ! in_array( $vertical, $match['verticals'], true ) ) {
			return $this->error( 'operation_provider_unavailable', 'The operation provider is not ready for this vertical.', 409 );
		}
		$capability = 'refund' === $type && 'payment_intent' === $target_kind ? 'payment_refund' : ( 'refund' === $type ? 'refund' : self::TYPE_CAPABILITIES[ $type ] );
		if ( ! in_array( $capability, $match['capabilities'], true ) ) {
			return $this->error( 'operation_capability_unavailable', 'The provider cannot perform the requested operation.', 409 );
		}
		return $match;
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
