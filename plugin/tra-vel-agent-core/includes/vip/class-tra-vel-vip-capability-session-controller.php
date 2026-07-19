<?php
/**
 * Scanner-safe, no-login REST exchange for low-risk VIP capability sessions.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_VIP_Capability_Session_Controller extends WP_REST_Controller {
	const SESSION_COOKIE = '__Host-tra_vel_vip_capability_session';
	const MAX_EXCHANGE_BODY_BYTES = 512;

	/** @var Tra_Vel_VIP_Capability_Session_Store */
	private $store;

	/** @var array|null */
	private $authorized_session = null;

	/** @var string|null */
	private $authorized_exchange_request = null;

	/** @var string|null */
	private $authorized_logout_request = null;

	/** @var string|null */
	private $authorized_logout_session_value = null;

	public function __construct( $store = null ) {
		$this->namespace = 'tra-vel-agent/v1';
		$this->rest_base = 'vip/capability-session';
		$this->store     = $store ? $store : new Tra_Vel_VIP_Capability_Session_Store();
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/probe',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'probe' ),
				'permission_callback' => array( $this, 'can_probe' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/exchange',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'exchange' ),
				'permission_callback' => array( $this, 'can_exchange' ),
				'args'                => $this->exchange_args(),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/current',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_current' ),
				'permission_callback' => array( $this, 'can_read' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/logout',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'logout' ),
				'permission_callback' => array( $this, 'can_logout' ),
			)
		);
	}

	/** Scanner callbacks never inspect storage, cookies, refs, query values, or state. */
	public function can_probe() {
		return true;
	}

	public function probe() {
		return $this->private_response( Tra_Vel_VIP_Capability_Session_Policy::scanner_probe(), 200 );
	}

	public function can_exchange( $request ) {
		$this->authorized_exchange_request = null;
		$body = $this->validated_exchange_body( $request );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$origin = $this->same_origin_mutation( $request );
		if ( is_wp_error( $origin ) ) {
			return $origin;
		}
		$rate = $this->consume_exchange_limit( $body['exchange_value'] );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}
		if ( ! $this->store->is_ready() ) {
			return $this->store_unavailable( 'exchange' );
		}
		$this->authorized_exchange_request = $this->exchange_request_fingerprint( $request, $body );
		return true;
	}

	public function can_read() {
		$this->authorized_session = null;
		return $this->authorize_current_session();
	}

	public function can_logout( $request ) {
		$this->authorized_logout_request       = null;
		$this->authorized_logout_session_value = null;
		$origin = $this->same_origin_mutation( $request );
		if ( is_wp_error( $origin ) ) {
			return $origin;
		}
		$value = $this->session_cookie_value();
		if ( is_wp_error( $value ) ) {
			return $value;
		}
		$rate = $this->consume_logout_limit( $value );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}
		if ( ! $this->store->is_ready() ) {
			return $this->store_unavailable( 'session' );
		}
		$this->authorized_logout_session_value = $value;
		$this->authorized_logout_request       = $this->request_fingerprint( $request, 'logout', self::session_principal( $value ) );
		return true;
	}

	public function exchange( $request ) {
		$body = $this->validated_exchange_body( $request );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$fingerprint = $this->exchange_request_fingerprint( $request, $body );
		if ( null === $this->authorized_exchange_request || ! hash_equals( $this->authorized_exchange_request, $fingerprint ) ) {
			$authorized = $this->can_exchange( $request );
			if ( true !== $authorized ) {
				return $authorized;
			}
		}
		$this->authorized_exchange_request = null;
		$exchange_value = $body['exchange_value'];
		$idempotency_key = $body['idempotency_key'];
		$request_digest = Tra_Vel_VIP_Capability_Session_Policy::canonical_digest(
			array(
				'operation'            => 'vip.capability.exchange',
				'capability_digest'    => Tra_Vel_VIP_Capability_Session_Store::capability_digest( $exchange_value ),
				'idempotency_key_hash' => Tra_Vel_VIP_Capability_Session_Store::idempotency_key_hash( $idempotency_key ),
			)
		);
		$result = $this->store->exchange( $exchange_value, $idempotency_key, $request_digest );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$public = Tra_Vel_VIP_Capability_Session_Policy::public_session( $result['session'] );
		if ( is_wp_error( $public ) ) {
			return $public;
		}
		$response = $this->private_response( $public, ! empty( $result['created'] ) ? 201 : 200 );
		$this->attach_session_cookie( $response, $result['session_value'], $result['session']['expires_at'] );
		return $response;
	}

	public function get_current() {
		if ( null === $this->authorized_session ) {
			$authorized = $this->authorize_current_session();
			if ( true !== $authorized ) {
				return $authorized;
			}
		}
		$public = Tra_Vel_VIP_Capability_Session_Policy::public_session( $this->authorized_session );
		$this->authorized_session = null;
		return is_wp_error( $public ) ? $public : $this->private_response( $public, 200 );
	}

	public function logout( $request = null ) {
		if ( $request instanceof WP_REST_Request && null !== $this->authorized_logout_request && null !== $this->authorized_logout_session_value ) {
			$fingerprint = $this->request_fingerprint( $request, 'logout', self::session_principal( $this->authorized_logout_session_value ) );
			if ( ! hash_equals( $this->authorized_logout_request, $fingerprint ) ) {
				$this->authorized_logout_request       = null;
				$this->authorized_logout_session_value = null;
			}
		}
		if ( null === $this->authorized_logout_request || null === $this->authorized_logout_session_value ) {
			if ( ! $request instanceof WP_REST_Request ) {
				return new WP_Error( 'tra_vel_vip_capability_logout_not_authorized', 'The capability session could not be closed safely.', array( 'status' => 403 ) );
			}
			$authorized = $this->can_logout( $request );
			if ( true !== $authorized ) {
				return $authorized;
			}
		}
		$value = $this->authorized_logout_session_value;
		$this->authorized_logout_request       = null;
		$this->authorized_logout_session_value = null;
		$revoked = $this->store->revoke_session( $value );
		if ( is_wp_error( $revoked ) ) {
			return $revoked;
		}
		$this->authorized_session = null;
		$response = $this->private_response( array( 'contract_version' => Tra_Vel_VIP_Capability_Session_Policy::CONTRACT_VERSION, 'closed' => true ), 200 );
		$this->clear_session_cookie( $response );
		return $response;
	}

	private function authorize_current_session() {
		$value = $this->session_cookie_value();
		if ( is_wp_error( $value ) ) {
			return $value;
		}
		$rate  = $this->consume_read_limit( $value );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}
		if ( ! $this->store->is_ready() ) {
			return $this->store_unavailable( 'session' );
		}
		$session = $this->store->current_session( $value );
		if ( is_wp_error( $session ) ) {
			return new WP_Error( 'tra_vel_vip_capability_session_missing', 'The capability session is unavailable.', array( 'status' => 404 ) );
		}
		$this->authorized_session = $session;
		return true;
	}

	private function same_origin_mutation( $request ) {
		$origin = (string) $request->get_header( 'Origin' );
		$home   = home_url( '/' );
		$origin_host = strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) );
		$home_host   = strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) );
		$origin_scheme = strtolower( (string) wp_parse_url( $origin, PHP_URL_SCHEME ) );
		$home_scheme   = strtolower( (string) wp_parse_url( $home, PHP_URL_SCHEME ) );
		$origin_port = (int) wp_parse_url( $origin, PHP_URL_PORT );
		$home_port   = (int) wp_parse_url( $home, PHP_URL_PORT );
		$origin_port = $origin_port > 0 ? $origin_port : ( 'https' === $origin_scheme ? 443 : 80 );
		$home_port   = $home_port > 0 ? $home_port : ( 'https' === $home_scheme ? 443 : 80 );
		if ( ! $origin_host || ! $home_host || 'https' !== $origin_scheme || 'https' !== $home_scheme || ! hash_equals( $home_host, $origin_host ) || $origin_port !== $home_port || wp_parse_url( $origin, PHP_URL_USER ) || wp_parse_url( $origin, PHP_URL_PASS ) || wp_parse_url( $origin, PHP_URL_PATH ) || wp_parse_url( $origin, PHP_URL_QUERY ) || wp_parse_url( $origin, PHP_URL_FRAGMENT ) ) {
			return new WP_Error( 'tra_vel_vip_capability_origin_rejected', 'Capability-session changes must come from the Tra-Vel website.', array( 'status' => 403 ) );
		}
		if ( get_current_user_id() > 0 ) {
			$nonce = (string) $request->get_header( 'X-WP-Nonce' );
			if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error( 'tra_vel_vip_capability_nonce_invalid', 'The signed-in session could not be verified.', array( 'status' => 403 ) );
			}
		}
		return true;
	}

	private function consume_exchange_limit( $candidate ) {
		$window = 10 * MINUTE_IN_SECONDS;
		$expires = ( (int) floor( time() / $window ) + 1 ) * $window + MINUTE_IN_SECONDS;
		$address_key = $this->limit_key( 'exchange-address', $this->remote_address() );
		$candidate_key = $this->limit_key( 'exchange-candidate', Tra_Vel_VIP_Capability_Session_Store::capability_digest( $candidate ) );
		$address_result = $this->store->consume_limit( $address_key, 12, $expires );
		if ( is_wp_error( $address_result ) ) {
			return $address_result;
		}
		if ( true !== $address_result ) {
			return $this->rate_error( $expires );
		}
		$candidate_result = $this->store->consume_limit( $candidate_key, 6, $expires );
		if ( is_wp_error( $candidate_result ) ) {
			return $candidate_result;
		}
		if ( true !== $candidate_result ) {
			return $this->rate_error( $expires );
		}
		return true;
	}

	private function consume_read_limit( $candidate ) {
		$window = 10 * MINUTE_IN_SECONDS;
		$expires = ( (int) floor( time() / $window ) + 1 ) * $window + MINUTE_IN_SECONDS;
		$principal = $candidate ? Tra_Vel_VIP_Capability_Session_Store::session_digest( $candidate ) : $this->remote_address();
		$result = $this->store->consume_limit( $this->limit_key( 'read', $principal ), 60, $expires );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( true !== $result ) {
			return $this->rate_error( $expires );
		}
		return true;
	}

	private function consume_logout_limit( $candidate ) {
		$window = 10 * MINUTE_IN_SECONDS;
		$expires = ( (int) floor( time() / $window ) + 1 ) * $window + MINUTE_IN_SECONDS;
		$principal = $candidate ? Tra_Vel_VIP_Capability_Session_Store::session_digest( $candidate ) : $this->remote_address();
		$result = $this->store->consume_limit( $this->limit_key( 'logout', $principal ), 20, $expires );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( true !== $result ) {
			return $this->rate_error( $expires );
		}
		return true;
	}

	private function limit_key( $operation, $principal ) {
		return hash_hmac( 'sha256', 'vip-capability:' . $operation . ':' . (string) $principal, wp_salt( 'nonce' ) );
	}

	private function remote_address() {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return filter_var( $address, FILTER_VALIDATE_IP ) ? $address : 'unknown';
	}

	private function rate_error( $expires ) {
		return new WP_Error( 'tra_vel_vip_capability_rate_limited', 'Too many private capability requests were made. Please wait before trying again.', array( 'status' => 429, 'retry_after' => max( 60, (int) $expires - time() ) ) );
	}

	/** @return array|WP_Error */
	private function validated_exchange_body( $request ) {
		$content_length = trim( (string) $request->get_header( 'Content-Length' ) );
		if ( '' !== $content_length && ( 1 !== preg_match( '/^\d{1,10}$/', $content_length ) || (int) $content_length > self::MAX_EXCHANGE_BODY_BYTES ) ) {
			return new WP_Error( 'tra_vel_vip_capability_exchange_too_large', 'The capability exchange request is too large.', array( 'status' => 413 ) );
		}
		$body     = $request->get_json_params();
		$required = array( 'exchange_value', 'idempotency_key' );
		if ( ! is_array( $body ) || array_diff( $required, array_keys( $body ) ) || array_diff( array_keys( $body ), $required ) ) {
			return new WP_Error( 'tra_vel_vip_capability_exchange_shape_invalid', 'A closed capability exchange request is required.', array( 'status' => 400 ) );
		}
		$encoded = wp_json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_EXCHANGE_BODY_BYTES ) {
			return new WP_Error( 'tra_vel_vip_capability_exchange_too_large', 'The capability exchange request is too large.', array( 'status' => 413 ) );
		}
		$exchange_value = $body['exchange_value'];
		$idempotency_key = $body['idempotency_key'];
		if ( ! is_string( $exchange_value ) || ! is_string( $idempotency_key ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $exchange_value ) || strlen( $idempotency_key ) < 16 || strlen( $idempotency_key ) > Tra_Vel_VIP_Capability_Session_Store::MAX_IDEMPOTENCY_KEY_BYTES || 1 !== preg_match( '/^[A-Za-z0-9._:-]+$/', $idempotency_key ) ) {
			return new WP_Error( 'tra_vel_vip_capability_exchange_invalid', 'The capability exchange request is invalid.', array( 'status' => 400 ) );
		}
		return array( 'exchange_value' => $exchange_value, 'idempotency_key' => $idempotency_key );
	}

	private function exchange_request_fingerprint( $request, $body ) {
		$principal = Tra_Vel_VIP_Capability_Session_Store::capability_digest( $body['exchange_value'] ) . ':' . Tra_Vel_VIP_Capability_Session_Store::idempotency_key_hash( $body['idempotency_key'] );
		return $this->request_fingerprint( $request, 'exchange', $principal );
	}

	private function request_fingerprint( $request, $operation, $principal ) {
		$material = implode(
			'|',
			array(
				'vip-capability-controller-v1',
				(string) $operation,
				spl_object_hash( $request ),
				(string) $request->get_header( 'Origin' ),
				(string) $request->get_header( 'X-WP-Nonce' ),
				(string) $principal,
			)
		);
		return hash_hmac( 'sha256', $material, wp_salt( 'nonce' ) );
	}

	private static function session_principal( $value ) {
		return '' === $value ? 'absent-cookie' : Tra_Vel_VIP_Capability_Session_Store::session_digest( $value );
	}

	private function store_unavailable( $kind ) {
		$message = 'exchange' === $kind ? 'Private capability exchange is temporarily unavailable.' : 'Private capability session is temporarily unavailable.';
		return new WP_Error( 'tra_vel_vip_capability_store_unavailable', $message, array( 'status' => 503 ) );
	}

	private function session_cookie_value() {
		if ( empty( $_COOKIE[ self::SESSION_COOKIE ] ) ) {
			return '';
		}
		$value = rawurldecode( wp_unslash( (string) $_COOKIE[ self::SESSION_COOKIE ] ) );
		return 1 === preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $value )
			? $value
			: new WP_Error( 'tra_vel_vip_capability_session_missing', 'The capability session is unavailable.', array( 'status' => 404 ) );
	}

	private function attach_session_cookie( $response, $value, $expires_at ) {
		$max_age = max( 1, min( Tra_Vel_VIP_Capability_Session_Policy::SESSION_TTL_SECONDS, strtotime( (string) $expires_at ) - time() ) );
		$response->header( 'Set-Cookie', self::SESSION_COOKIE . '=' . rawurlencode( (string) $value ) . '; Max-Age=' . $max_age . '; Path=/; Secure; HttpOnly; SameSite=Strict' );
	}

	private function clear_session_cookie( $response ) {
		$response->header( 'Set-Cookie', self::SESSION_COOKIE . '=; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; Secure; HttpOnly; SameSite=Strict' );
	}

	private function private_response( $data, $status ) {
		$response = new WP_REST_Response( $data, (int) $status );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		$response->header( 'Referrer-Policy', 'no-referrer' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'" );
		return $response;
	}

	private function exchange_args() {
		return array(
			'exchange_value' => array(
				'type' => 'string',
				'required' => true,
				'minLength' => 32,
				'maxLength' => 128,
				'pattern' => '^[A-Za-z0-9_-]+$',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'idempotency_key' => array(
				'type' => 'string',
				'required' => true,
				'minLength' => 16,
				'maxLength' => Tra_Vel_VIP_Capability_Session_Store::MAX_IDEMPOTENCY_KEY_BYTES,
				'pattern' => '^[A-Za-z0-9._:-]+$',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
