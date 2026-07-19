<?php
/**
 * REST bridge for source-backed Israeli benefit identity planning.
 *
 * This controller never connects an account, reads a balance, searches live
 * inventory, changes a payable total, redeems value, or performs checkout.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Israel_Benefit_Controller extends WP_REST_Controller {
	/** @var Tra_Vel_Israel_Benefit_Catalog_Registry */
	private $registry;

	/** @var callable */
	private $clock;

	public function __construct( $registry = null, $clock = null ) {
		$this->namespace = 'tra-vel-agent/v1';
		$this->rest_base = 'benefits/israel';
		$this->registry  = $registry instanceof Tra_Vel_Israel_Benefit_Catalog_Registry
			? $registry
			: new Tra_Vel_Israel_Benefit_Catalog_Registry();
		$this->clock     = is_callable( $clock ) ? $clock : 'Tra_Vel_Israel_Benefit_Controller::utc_now';
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/options',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_options' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/plan',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_plan' ),
				'permission_callback' => array( $this, 'can_plan' ),
				'args'                => $this->plan_args(),
			)
		);
	}

	/**
	 * Return source-reviewed picker identities without source payloads or member
	 * information.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_options() {
		$options = $this->registry->public_options( $this->now() );
		if ( is_wp_error( $options ) ) {
			return $options;
		}

		$response = rest_ensure_response( $options );
		$response->header( 'Cache-Control', 'public, max-age=300, stale-while-revalidate=300' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		return $response;
	}

	/**
	 * Ensure a planning request originated from the Tra-Vel browser surface.
	 * The action is non-transactional but receives customer-asserted card axes,
	 * so it is deliberately private and same-site.
	 *
	 * @return true|WP_Error
	 */
	public function can_plan( WP_REST_Request $request ) {
		$source = (string) $request->get_header( 'Origin' );
		if ( '' === $source ) {
			$source = (string) $request->get_header( 'Referer' );
		}

		$home          = home_url( '/' );
		$source_host   = strtolower( (string) wp_parse_url( $source, PHP_URL_HOST ) );
		$home_host     = strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) );
		$source_scheme = strtolower( (string) wp_parse_url( $source, PHP_URL_SCHEME ) );
		$home_scheme   = strtolower( (string) wp_parse_url( $home, PHP_URL_SCHEME ) );
		$source_port   = (int) wp_parse_url( $source, PHP_URL_PORT );
		$home_port     = (int) wp_parse_url( $home, PHP_URL_PORT );
		$source_port   = $source_port > 0 ? $source_port : ( 'https' === $source_scheme ? 443 : 80 );
		$home_port     = $home_port > 0 ? $home_port : ( 'https' === $home_scheme ? 443 : 80 );

		if (
			'' === $source_host ||
			'' === $home_host ||
			! hash_equals( $home_host, $source_host ) ||
			'https' !== $source_scheme ||
			'https' !== $home_scheme ||
			$source_port !== $home_port ||
			null !== wp_parse_url( $source, PHP_URL_USER ) ||
			null !== wp_parse_url( $source, PHP_URL_PASS )
		) {
			return new WP_Error(
				'tra_vel_israel_benefit_origin_rejected',
				'Benefit planning requests must come from the Tra-Vel website.',
				array( 'status' => 403 )
			);
		}

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( get_current_user_id() > 0 && ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) ) {
			return new WP_Error(
				'tra_vel_israel_benefit_nonce_invalid',
				'The signed-in session could not be verified.',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Build the smallest-next-action plan. No provider call or durable mutation
	 * occurs here.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_plan( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : array();
		$keys = array_keys( $this->plan_args() );
		if ( array_diff( array_keys( $body ), $keys ) ) {
			return new WP_Error(
				'tra_vel_israel_benefit_plan_unknown_field',
				'The benefit plan contains an unsupported field.',
				array( 'status' => 400 )
			);
		}

		$plan_request = array();
		foreach ( $keys as $key ) {
			$plan_request[ $key ] = $request->get_param( $key );
		}

		$plan = $this->registry->plan( $plan_request, $this->now() );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$plan['side_effect_executed'] = false;
		$response = new WP_REST_Response( $plan, 200 );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		return $response;
	}

	private function plan_args() {
		$nullable_identifier = array(
			'type'              => array( 'string', 'null' ),
			'default'           => null,
			'pattern'           => '^[a-z][a-z0-9_]{2,95}$',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return array(
			'airline_inventory_id' => $nullable_identifier,
			'program_id'           => $nullable_identifier,
			'credential_product_id'=> $nullable_identifier,
			'payment_network_id'   => $nullable_identifier,
			'redemption_portal_id' => $nullable_identifier,
			'campaign_id'          => $nullable_identifier,
			'campaign_version'     => array(
				'type'              => array( 'integer', 'null' ),
				'default'           => null,
				'minimum'           => 1,
				'validate_callback' => 'rest_validate_request_arg',
			),
			'eligibility_claim'    => array(
				'type'              => 'string',
				'default'           => 'none',
				'enum'              => array( 'none', 'generic_visa_eligible', 'generic_fly_card_eligible', 'exact_product_customer_asserted' ),
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}

	private function now() {
		return (string) call_user_func( $this->clock );
	}

	public static function utc_now() {
		return gmdate( 'Y-m-d\TH:i:s\Z' );
	}
}
