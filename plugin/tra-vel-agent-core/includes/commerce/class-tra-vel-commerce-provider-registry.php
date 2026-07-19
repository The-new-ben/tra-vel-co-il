<?php
/**
 * Deterministic registry for separately capable commerce providers.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Provider_Registry {
	/** @var array<string,Tra_Vel_Commerce_Provider_Adapter> */
	private $adapters = array();

	/** @var array<string,array> */
	private $descriptors = array();

	/**
	 * @param array|null $adapters Explicit adapters for tests/bootstrap; null uses the integration filter.
	 */
	public function __construct( $adapters = null ) {
		if ( null === $adapters ) {
			$adapters = apply_filters( 'tra_vel_commerce_provider_adapters', array() );
		}
		if ( is_array( $adapters ) ) {
			foreach ( $adapters as $adapter ) {
				$this->register( $adapter );
			}
		}
	}

	/**
	 * Register only an adapter whose declared capabilities match interfaces.
	 *
	 * @return true|WP_Error
	 */
	public function register( $adapter ) {
		if ( ! $adapter instanceof Tra_Vel_Commerce_Provider_Adapter ) {
			return new WP_Error( 'tra_vel_commerce_provider_adapter_invalid', 'Commerce providers must implement the base adapter contract.', array( 'status' => 400 ) );
		}
		$descriptor = Tra_Vel_Commerce_Policy::provider_descriptor( $adapter->get_descriptor() );
		if ( is_wp_error( $descriptor ) ) {
			return $descriptor;
		}
		$id = $descriptor['provider_id'];
		if ( isset( $this->adapters[ $id ] ) ) {
			return new WP_Error( 'tra_vel_commerce_provider_duplicate', 'A commerce provider ID was registered more than once.', array( 'status' => 409 ) );
		}
		$capability_contract = array(
			'search'     => 'Tra_Vel_Commerce_Search_Adapter',
			'revalidate' => 'Tra_Vel_Commerce_Quote_Adapter',
			'reserve'    => 'Tra_Vel_Commerce_Fulfillment_Adapter',
			'confirm'    => 'Tra_Vel_Commerce_Fulfillment_Adapter',
			'fulfill'    => 'Tra_Vel_Commerce_Fulfillment_Adapter',
			'change'     => 'Tra_Vel_Commerce_Fulfillment_Adapter',
			'cancel'     => 'Tra_Vel_Commerce_Fulfillment_Adapter',
			'refund'     => 'Tra_Vel_Commerce_Fulfillment_Adapter',
			'payment_authorize'   => 'Tra_Vel_Commerce_Payment_Adapter',
			'payment_capture'     => 'Tra_Vel_Commerce_Payment_Adapter',
			'payment_void'        => 'Tra_Vel_Commerce_Payment_Adapter',
			'payment_refund'      => 'Tra_Vel_Commerce_Payment_Adapter',
			'webhook'             => 'Tra_Vel_Commerce_Webhook_Adapter',
			'reconcile'           => 'Tra_Vel_Commerce_Reconciliation_Adapter',
			'report_conversion'   => 'Tra_Vel_Commerce_Affiliate_Reporter',
			'settlement_reconcile' => 'Tra_Vel_Commerce_Settlement_Adapter',
		);
		foreach ( $descriptor['capabilities'] as $capability ) {
			$interface = $capability_contract[ $capability ];
			if ( ! $adapter instanceof $interface ) {
				return new WP_Error( 'tra_vel_commerce_provider_capability_unimplemented', 'A declared commerce provider capability has no matching adapter interface.', array( 'status' => 400, 'provider_id' => $id, 'capability' => $capability ) );
			}
		}
		if ( 'affiliate' === $descriptor['relationship'] && ( ! in_array( 'report_conversion', $descriptor['capabilities'], true ) || ! in_array( 'settlement_reconcile', $descriptor['capabilities'], true ) || ! $adapter instanceof Tra_Vel_Commerce_Affiliate_Reporter ) ) {
			return new WP_Error( 'tra_vel_commerce_affiliate_reporter_missing', 'An affiliate provider must implement conversion and settlement reporting.', array( 'status' => 400 ) );
		}
		$this->adapters[ $id ]    = $adapter;
		$this->descriptors[ $id ] = $descriptor;
		return true;
	}

	public function get_adapter( $provider_id ) {
		$provider_id = sanitize_key( (string) $provider_id );
		return isset( $this->adapters[ $provider_id ] ) ? $this->adapters[ $provider_id ] : null;
	}

	public function get_descriptor( $provider_id ) {
		$provider_id = sanitize_key( (string) $provider_id );
		return isset( $this->descriptors[ $provider_id ] ) ? $this->descriptors[ $provider_id ] : null;
	}

	/**
	 * Return deterministic descriptors independent of registration order.
	 */
	public function all() {
		$descriptors = array_values( $this->descriptors );
		usort( $descriptors, array( __CLASS__, 'compare_descriptors' ) );
		return $descriptors;
	}

	/**
	 * Resolve providers by canonical vertical, capability, environment and readiness.
	 *
	 * @return array<int,array{descriptor:array,adapter:Tra_Vel_Commerce_Provider_Adapter}>
	 */
	public function eligible( $vertical, $capability, $environment = 'sandbox' ) {
		$vertical    = Tra_Vel_Commerce_Taxonomy::vertical( $vertical );
		$capability  = sanitize_key( (string) $capability );
		$environment = sanitize_key( (string) $environment );
		if ( '' === $vertical || ! in_array( $capability, Tra_Vel_Commerce_Taxonomy::CAPABILITIES, true ) || ! in_array( $environment, array( 'sandbox', 'live' ), true ) ) {
			return array();
		}
		$eligible = array();
		foreach ( $this->all() as $descriptor ) {
			if ( $environment !== $descriptor['environment'] || 'ready' !== $descriptor['readiness']['status'] || ! in_array( $vertical, $descriptor['verticals'], true ) || ! in_array( $capability, $descriptor['capabilities'], true ) ) {
				continue;
			}
			$eligible[] = array( 'descriptor' => $descriptor, 'adapter' => $this->adapters[ $descriptor['provider_id'] ] );
		}
		return $eligible;
	}

	public function signature() {
		return Tra_Vel_Commerce_Policy::canonical_digest( $this->all() );
	}

	private static function compare_descriptors( $left, $right ) {
		$priority = (int) $right['priority'] <=> (int) $left['priority'];
		return 0 !== $priority ? $priority : strcmp( $left['provider_id'], $right['provider_id'] );
	}
}
