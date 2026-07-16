<?php
/**
 * Strict outbound supplier handoff boundary.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Supplier_Handoff_Controller extends WP_REST_Controller {
	/** @var array|null */
	protected $schema;

	public function __construct() {
		$this->namespace = 'tra-vel/v2';
		$this->rest_base = 'handoffs';
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/prepare',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'prepare_handoff' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'provider' => array( 'type' => 'string', 'required' => true, 'pattern' => '^[a-z0-9_-]{2,40}$', 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
					'vertical' => array( 'type' => 'string', 'required' => true, 'enum' => $this->supported_verticals(), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
					'offer_id' => array( 'type' => 'string', 'required' => true, 'pattern' => '^[A-Za-z0-9._:-]{1,80}$', 'sanitize_callback' => array( $this, 'sanitize_offer_id' ), 'validate_callback' => 'rest_validate_request_arg' ),
					'destination' => array( 'type' => 'string', 'default' => '', 'maxLength' => 80, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
					'origin' => array( 'type' => 'string', 'default' => '', 'maxLength' => 80, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
					'depart_date' => array( 'type' => 'string', 'default' => '', 'pattern' => '^(?:\\d{4}-\\d{2}-\\d{2})?$', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
					'return_date' => array( 'type' => 'string', 'default' => '', 'pattern' => '^(?:\\d{4}-\\d{2}-\\d{2})?$', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
					'travelers' => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 20, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
					'budget' => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 1000000, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
					'currency' => array( 'type' => 'string', 'default' => 'ILS', 'enum' => array( 'ILS', 'USD', 'EUR', 'GBP' ), 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
					'product' => array( 'type' => 'string', 'default' => '', 'maxLength' => 100, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
					'return_path' => array( 'type' => 'string', 'default' => '/', 'maxLength' => 200, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/schema',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_contract_schema' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function prepare_handoff( $request ) {
		$provider_id = (string) $request->get_param( 'provider' );
		$vertical    = (string) $request->get_param( 'vertical' );
		$providers   = $this->configured_providers();
		if ( ! isset( $providers[ $provider_id ] ) || ! in_array( $vertical, $providers[ $provider_id ]['verticals'], true ) ) {
			return new WP_Error( 'tra_vel_handoff_not_configured', 'A verified live supplier handoff is not configured for this offer.', array( 'status' => 409 ) );
		}
		$provider = $providers[ $provider_id ];
		$context  = array(
			'vertical'     => $vertical,
			'offer_id'     => $this->sanitize_offer_id( $request->get_param( 'offer_id' ) ),
			'destination'  => sanitize_text_field( (string) $request->get_param( 'destination' ) ),
			'return_path'  => $this->sanitize_return_path( $request->get_param( 'return_path' ) ),
			'origin'       => sanitize_text_field( (string) $request->get_param( 'origin' ) ),
			'depart_date'  => $this->sanitize_date( $request->get_param( 'depart_date' ) ),
			'return_date'  => $this->sanitize_date( $request->get_param( 'return_date' ) ),
			'travelers'    => max( 1, min( 20, absint( $request->get_param( 'travelers' ) ) ) ),
			'budget'       => min( 1000000, absint( $request->get_param( 'budget' ) ) ),
			'currency'     => in_array( $request->get_param( 'currency' ), array( 'ILS', 'USD', 'EUR', 'GBP' ), true ) ? $request->get_param( 'currency' ) : 'ILS',
			'product'      => substr( sanitize_text_field( (string) $request->get_param( 'product' ) ), 0, 100 ),
			'source'       => 'tra-vel-v2',
			'campaign'     => 'owned' === $provider['relationship'] ? 'assisted_quote' : 'verified_handoff',
		);
		$url = call_user_func( $provider['build_url'], $context );
		$url = $this->validate_provider_url( $url, $provider['allowed_hosts'] );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		$response = new WP_REST_Response(
			array(
				'provider'       => array( 'id' => $provider_id, 'label' => $provider['label'], 'relationship' => $provider['relationship'] ),
				'vertical'       => $vertical,
				'offer_id'       => $context['offer_id'],
				'handoff_url'    => $url,
				'rel'            => 'affiliate' === $provider['relationship'] ? 'sponsored noopener noreferrer' : 'noopener noreferrer',
				'disclosure'     => $provider['disclosure'],
				'price_recheck'   => true,
				'booking_on_partner' => 'affiliate' === $provider['relationship'],
				'conversion_type' => 'affiliate' === $provider['relationship'] ? 'partner_booking' : 'assisted_quote',
				'expires_at'      => gmdate( 'c', time() + 300 ),
			),
			200
		);
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'Referrer-Policy', 'strict-origin-when-cross-origin' );
		return $response;
	}

	public function get_health() {
		$providers = $this->configured_providers();
		$relationships = array_unique( wp_list_pluck( $providers, 'relationship' ) );
		$booking_mode  = 'disabled';
		if ( in_array( 'affiliate', $relationships, true ) && in_array( 'owned', $relationships, true ) ) {
			$booking_mode = 'hybrid';
		} elseif ( in_array( 'affiliate', $relationships, true ) ) {
			$booking_mode = 'partner_handoff';
		} elseif ( in_array( 'owned', $relationships, true ) ) {
			$booking_mode = 'assisted_sales';
		}

		return rest_ensure_response(
			array(
				'ok'                  => true,
				'enabled'             => count( $providers ) > 0,
				'configured_count'    => count( $providers ),
				'supported_verticals' => $this->supported_verticals(),
				'booking_mode'        => $booking_mode,
				'relationships'       => array_values( $relationships ),
			)
		);
	}

	public function get_contract_schema() {
		$path = TRA_VEL_V2_PATH . '/assets/data/supplier-handoff.schema.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_handoff_schema_missing', 'Supplier handoff schema is unavailable.', array( 'status' => 503 ) );
		}
		$schema = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $schema ) ? rest_ensure_response( $schema ) : new WP_Error( 'tra_vel_handoff_schema_invalid', 'Supplier handoff schema is invalid.', array( 'status' => 500 ) );
	}

	public function sanitize_offer_id( $value ) {
		return substr( preg_replace( '/[^A-Za-z0-9._:-]/', '', (string) $value ), 0, 80 );
	}

	private function configured_providers() {
		$raw      = apply_filters( 'tra_vel_v2_handoff_providers', array() );
		$resolved = array();
		foreach ( is_array( $raw ) ? $raw : array() as $provider ) {
			$id            = sanitize_key( isset( $provider['id'] ) ? $provider['id'] : '' );
			$verticals     = array_values( array_intersect( $this->supported_verticals(), array_map( 'sanitize_key', isset( $provider['verticals'] ) ? (array) $provider['verticals'] : array() ) ) );
			$allowed_hosts = array_values( array_filter( array_map( array( $this, 'normalize_host' ), isset( $provider['allowed_hosts'] ) ? (array) $provider['allowed_hosts'] : array() ) ) );
			$disclosure    = sanitize_text_field( isset( $provider['disclosure'] ) ? $provider['disclosure'] : '' );
			$relationship  = sanitize_key( isset( $provider['relationship'] ) ? $provider['relationship'] : ( ! empty( $provider['sponsored'] ) ? 'affiliate' : '' ) );
			if ( ! in_array( $relationship, array( 'owned', 'affiliate' ), true ) ) {
				continue;
			}
			if ( 'affiliate' === $relationship && empty( $provider['sponsored'] ) ) {
				continue;
			}
			if ( ! $id || empty( $provider['live'] ) || ! $verticals || ! $allowed_hosts || ! $disclosure || empty( $provider['build_url'] ) || ! is_callable( $provider['build_url'] ) ) {
				continue;
			}
			$resolved[ $id ] = array(
				'id'            => $id,
				'label'         => sanitize_text_field( isset( $provider['label'] ) ? $provider['label'] : $id ),
				'verticals'     => $verticals,
				'allowed_hosts' => $allowed_hosts,
				'disclosure'    => $disclosure,
				'relationship'  => $relationship,
				'build_url'     => $provider['build_url'],
			);
		}
		return $resolved;
	}

	private function validate_provider_url( $url, $allowed_hosts ) {
		$url    = esc_url_raw( (string) $url, array( 'https' ) );
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$host   = $this->normalize_host( wp_parse_url( $url, PHP_URL_HOST ) );
		$user   = wp_parse_url( $url, PHP_URL_USER );
		if ( 'https' !== $scheme || ! $host || $user || ! in_array( $host, $allowed_hosts, true ) ) {
			return new WP_Error( 'tra_vel_handoff_url_rejected', 'Supplier handoff URL failed the allowlist policy.', array( 'status' => 502 ) );
		}
		return $url;
	}

	private function normalize_host( $host ) {
		return strtolower( preg_replace( '/[^a-z0-9.-]/', '', (string) $host ) );
	}

	private function sanitize_return_path( $path ) {
		$path = '/' . ltrim( sanitize_text_field( (string) $path ), '/' );
		return substr( preg_replace( '/[^A-Za-z0-9_\-\/.?=&]/', '', $path ), 0, 200 );
	}

	private function sanitize_date( $value ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	private function supported_verticals() {
		return array( 'flight', 'hotel', 'package', 'insurance', 'car', 'transfer', 'activity', 'esim' );
	}
}

add_action(
	'rest_api_init',
	static function () {
		$controller = new Tra_Vel_V2_Supplier_Handoff_Controller();
		$controller->register_routes();
	}
);
