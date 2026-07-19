<?php
/**
 * Private REST control plane for durable commercial intent and handoffs.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Commercial_Intent_Controller extends WP_REST_Controller {
	const OWNER_COOKIE = '__Host-tra_vel_commercial';

	/** @var Tra_Vel_Commercial_Intent_Store */
	private $store;

	public function __construct( $store = null ) {
		$this->namespace = 'tra-vel-agent/v1';
		$this->rest_base = 'commercial-intents';
		$this->store     = $store ? $store : new Tra_Vel_Commercial_Intent_Store();
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_intent' ),
				'permission_callback' => array( $this, 'can_create' ),
				'args'                => $this->create_args(),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<intent_id>[0-9a-fA-F-]{36})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_intent' ),
				'permission_callback' => array( $this, 'can_access' ),
				'args'                => array( 'intent_id' => $this->uuid_arg() ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<intent_id>[0-9a-fA-F-]{36})/handoffs',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'prepare_handoff' ),
				'permission_callback' => array( $this, 'can_access' ),
				'args'                => array(
					'intent_id'       => $this->uuid_arg(),
					'expected_version'=> array( 'type' => 'integer', 'required' => true, 'minimum' => 1, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
					'idempotency_key' => $this->idempotency_arg(),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/schema/commercial-intent',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_schema' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function can_create( WP_REST_Request $request ) {
		if ( ! Tra_Vel_Commercial_Intent_Store::is_ready() || ! Tra_Vel_Agent_Store::is_ready() ) {
			return new WP_Error( 'tra_vel_commercial_store_unavailable', 'Commercial request storage is temporarily unavailable.', array( 'status' => 503 ) );
		}
		return $this->same_site_mutation( $request );
	}

	public function can_access( WP_REST_Request $request ) {
		if ( ! Tra_Vel_Commercial_Intent_Store::is_ready() ) {
			return new WP_Error( 'tra_vel_commercial_store_unavailable', 'Commercial request storage is temporarily unavailable.', array( 'status' => 503 ) );
		}
		if ( WP_REST_Server::CREATABLE === $request->get_method() ) {
			$same_site = $this->same_site_mutation( $request );
			if ( true !== $same_site ) {
				return $same_site;
			}
		}
		$intent = $this->store->get_by_uuid( (string) $request->get_param( 'intent_id' ) );
		if ( ! $intent ) {
			return new WP_Error( 'tra_vel_commercial_intent_missing', 'Commercial request not found.', array( 'status' => 404 ) );
		}
		$principal = $this->principal( false );
		return $this->store->can_access( $intent, (int) $principal['user_id'], (string) $principal['token_hash'] )
			? true
			: new WP_Error( 'tra_vel_commercial_forbidden', 'This private commercial request belongs to another traveler.', array( 'status' => 403 ) );
	}

	public function create_intent( WP_REST_Request $request ) {
		$limit = $this->consume_create_limit();
		if ( is_wp_error( $limit ) ) {
			return $limit;
		}
		$raw = $request->get_json_params();
		$raw = is_array( $raw ) ? $raw : $request->get_params();
		$scope = Tra_Vel_Commercial_Intent_Policy::normalize_scope( $raw );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$principal = $this->principal( true );
		if ( 0 === (int) $principal['user_id'] && 64 !== strlen( (string) $principal['token_hash'] ) ) {
			return new WP_Error( 'tra_vel_commercial_owner_unavailable', 'A private browser owner could not be established.', array( 'status' => 500 ) );
		}
		$result = $this->store->create_or_resume( $scope, $principal, (string) $request->get_param( 'idempotency_key' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$payload = array(
			'intent'              => $this->public_intent( $result['intent'] ),
			'event'               => $result['event'],
			'replayed'            => (bool) $result['replayed'],
			'reused'              => (bool) $result['reused'],
			'side_effect_executed'=> false,
		);
		$response = $this->private_response( $payload, ! empty( $result['created'] ) ? 201 : 200 );
		if ( ! empty( $principal['new_token'] ) ) {
			$this->attach_owner_cookie( $response, $principal['token'] );
		}
		if ( ! empty( $result['created'] ) ) {
			do_action( 'tra_vel_commercial_intent_recorded', $payload['intent'] );
		}
		return $response;
	}

	public function get_intent( WP_REST_Request $request ) {
		$intent = $this->store->get_by_uuid( (string) $request->get_param( 'intent_id' ) );
		return $intent
			? $this->private_response( array( 'intent' => $this->public_intent( $intent ), 'side_effect_executed' => false ) )
			: new WP_Error( 'tra_vel_commercial_intent_missing', 'Commercial request not found.', array( 'status' => 404 ) );
	}

	public function prepare_handoff( WP_REST_Request $request ) {
		$intent = $this->store->get_by_uuid( (string) $request->get_param( 'intent_id' ) );
		if ( ! $intent ) {
			return new WP_Error( 'tra_vel_commercial_intent_missing', 'Commercial request not found.', array( 'status' => 404 ) );
		}
		$context  = $this->handoff_context( $intent );
		$prepared = apply_filters( 'tra_vel_agent_commercial_intent_prepare_handoff', null, $context, $this->public_intent( $intent ) );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		if ( ! is_array( $prepared ) || empty( $prepared['handoff_url'] ) ) {
			return new WP_Error( 'tra_vel_commercial_handoff_unavailable', 'No verified owned assisted-contact channel is configured.', array( 'status' => 503 ) );
		}
		$url      = esc_url_raw( (string) $prepared['handoff_url'], array( 'https' ) );
		$scheme   = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$host     = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$user     = wp_parse_url( $url, PHP_URL_USER );
		$password = wp_parse_url( $url, PHP_URL_PASS );
		$provider = sanitize_key( isset( $prepared['provider'] ) ? $prepared['provider'] : '' );
		if ( 'https' !== $scheme || 'api.whatsapp.com' !== $host || $user || $password || Tra_Vel_Commercial_Intent_Policy::HANDOFF_PROVIDER !== $provider ) {
			return new WP_Error( 'tra_vel_commercial_handoff_rejected', 'The assisted-contact channel failed the owned-provider allowlist.', array( 'status' => 502 ) );
		}

		$expires_timestamp = strtotime( isset( $prepared['expires_at'] ) ? (string) $prepared['expires_at'] : '' );
		if ( false === $expires_timestamp || $expires_timestamp < time() + 30 || $expires_timestamp > time() + 600 ) {
			$expires_timestamp = time() + 300;
		}
		$expires_at = gmdate( 'c', $expires_timestamp );
		$principal  = $this->principal( false );
		$target_digest = hash( 'sha256', $url );
		$result     = $this->store->record_handoff(
			$intent['intent_uuid'],
			(int) $request->get_param( 'expected_version' ),
			$principal,
			$provider,
			'whatsapp',
			$target_digest,
			$expires_at,
			(string) $request->get_param( 'idempotency_key' )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! empty( $result['replayed'] ) && ! empty( $result['event']['data']['expires_at'] ) ) {
			$expires_at = (string) $result['event']['data']['expires_at'];
		}
		return $this->private_response(
			array(
				'intent'              => $this->public_intent( $result['intent'] ),
				'event'               => $result['event'],
				'replayed'            => (bool) $result['replayed'],
				'provider'            => array( 'id' => $provider, 'label' => 'Tra-Vel', 'relationship' => 'owned' ),
				'handoff_url'         => $url,
				'expires_at'          => $expires_at,
				'conversion_type'     => 'assisted_quote',
				'price_recheck'       => true,
				'side_effect_executed'=> false,
			)
		);
	}

	public function get_schema() {
		$path = TRA_VEL_AGENT_PATH . '/schemas/commercial-intent.schema.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_commercial_schema_missing', 'Commercial-intent schema is unavailable.', array( 'status' => 503 ) );
		}
		$schema = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $schema ) ? rest_ensure_response( $schema ) : new WP_Error( 'tra_vel_commercial_schema_invalid', 'Commercial-intent schema is invalid.', array( 'status' => 500 ) );
	}

	private function public_intent( $intent ) {
		$expired = strtotime( (string) $intent['expires_at'] . ' UTC' ) <= time();
		return array(
			'contract_version' => Tra_Vel_Commercial_Intent_Policy::CONTRACT_VERSION,
			'intent_id'        => (string) $intent['intent_uuid'],
			'reference'        => (string) $intent['reference_code'],
			'status'           => $expired ? 'expired' : 'active',
			'version'          => (int) $intent['intent_version'],
			'ownership'        => (int) $intent['owner_user_id'] > 0 ? 'account' : 'private_browser_owner',
			'vertical'         => (string) $intent['vertical'],
			'scope'            => (array) $intent['scope'],
			'created_at'       => gmdate( 'c', strtotime( $intent['created_at'] . ' UTC' ) ),
			'updated_at'       => gmdate( 'c', strtotime( $intent['updated_at'] . ' UTC' ) ),
			'expires_at'       => gmdate( 'c', strtotime( $intent['expires_at'] . ' UTC' ) ),
		);
	}

	private function handoff_context( $intent ) {
		$scope = (array) $intent['scope'];
		$trip  = isset( $scope['trip'] ) && is_array( $scope['trip'] ) ? $scope['trip'] : array();
		$labels = array( 'flight' => 'טיסה', 'hotel' => 'מלון', 'package' => 'טיסה ומלון', 'insurance' => 'ביטוח נסיעות', 'car' => 'רכב', 'transfer' => 'העברה', 'activity' => 'פעילות', 'esim' => 'eSIM' );
		$vertical = isset( $scope['vertical'] ) ? (string) $scope['vertical'] : 'package';
		return array(
			'provider'     => Tra_Vel_Commercial_Intent_Policy::HANDOFF_PROVIDER,
			'vertical'     => $vertical,
			'offer_id'     => (string) $intent['reference_code'],
			'intent_id'    => (string) $intent['intent_uuid'],
			'reference'    => (string) $intent['reference_code'],
			'destination'  => (string) ( $trip['destination'] ?? '' ),
			'origin'       => (string) ( $trip['origin'] ?? 'TLV' ),
			'depart_date'  => (string) ( $trip['depart_date'] ?? '' ),
			'return_date'  => (string) ( $trip['return_date'] ?? '' ),
			'travelers'    => max( 1, absint( $trip['travelers'] ?? 1 ) ),
			'budget'       => max( 0, absint( $trip['budget'] ?? 0 ) ),
			'currency'     => in_array( $trip['currency'] ?? '', array( 'ILS', 'USD', 'EUR', 'GBP' ), true ) ? $trip['currency'] : 'ILS',
			'product'      => isset( $labels[ $vertical ] ) ? $labels[ $vertical ] : $vertical,
			'return_path'  => (string) ( $trip['return_path'] ?? '/' ),
		);
	}

	private function principal( $create ) {
		$user_id = get_current_user_id();
		$token   = $this->owner_cookie_token();
		if ( $user_id > 0 ) {
			return array( 'user_id' => $user_id, 'token' => $token, 'token_hash' => $token ? hash( 'sha256', $token ) : '', 'principal_hash' => hash( 'sha256', 'commercial-user:' . $user_id ), 'new_token' => false );
		}
		$new = false;
		if ( ! $token && $create ) {
			try {
				$token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
			} catch ( Exception $error ) {
				$token = wp_generate_password( 48, false, false );
			}
			$new = true;
		}
		$hash = $token ? hash( 'sha256', $token ) : '';
		return array( 'user_id' => 0, 'token' => $token, 'token_hash' => $hash, 'principal_hash' => $hash, 'new_token' => $new );
	}

	private function owner_cookie_token() {
		if ( empty( $_COOKIE[ self::OWNER_COOKIE ] ) ) {
			return '';
		}
		$token = rawurldecode( (string) $_COOKIE[ self::OWNER_COOKIE ] );
		return preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $token ) ? $token : '';
	}

	private function attach_owner_cookie( WP_REST_Response $response, $token ) {
		$response->header( 'Set-Cookie', self::OWNER_COOKIE . '=' . rawurlencode( (string) $token ) . '; Max-Age=' . ( Tra_Vel_Commercial_Intent_Store::ACTIVE_DAYS * DAY_IN_SECONDS ) . '; Path=/; Secure; HttpOnly; SameSite=Lax' );
	}

	private function same_site_mutation( WP_REST_Request $request ) {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( get_current_user_id() > 0 && $nonce && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'tra_vel_commercial_nonce_invalid', 'The signed-in session could not be verified.', array( 'status' => 403 ) );
		}
		$source = (string) $request->get_header( 'Origin' );
		if ( ! $source ) {
			$source = (string) $request->get_header( 'Referer' );
		}
		$home        = home_url( '/' );
		$source_host = strtolower( (string) wp_parse_url( $source, PHP_URL_HOST ) );
		$home_host   = strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) );
		$source_scheme = strtolower( (string) wp_parse_url( $source, PHP_URL_SCHEME ) );
		$home_scheme   = strtolower( (string) wp_parse_url( $home, PHP_URL_SCHEME ) );
		$source_port   = (int) wp_parse_url( $source, PHP_URL_PORT );
		$home_port     = (int) wp_parse_url( $home, PHP_URL_PORT );
		$source_port   = $source_port > 0 ? $source_port : ( 'https' === $source_scheme ? 443 : 80 );
		$home_port     = $home_port > 0 ? $home_port : ( 'https' === $home_scheme ? 443 : 80 );
		$source_user   = wp_parse_url( $source, PHP_URL_USER );
		$source_pass   = wp_parse_url( $source, PHP_URL_PASS );
		if ( ! $source_host || ! $home_host || ! hash_equals( $home_host, $source_host ) || 'https' !== $source_scheme || 'https' !== $home_scheme || $source_port !== $home_port || $source_user || $source_pass ) {
			return new WP_Error( 'tra_vel_commercial_origin_rejected', 'Commercial mutations must come from the Tra-Vel website.', array( 'status' => 403 ) );
		}
		return true;
	}

	private function consume_create_limit() {
		$window = 10 * MINUTE_IN_SECONDS;
		$bucket = (int) floor( time() / $window );
		$limit  = min( 100, max( 4, (int) apply_filters( 'tra_vel_commercial_intent_create_limit', 12 ) ) );
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		$identity = get_current_user_id() > 0 ? 'user:' . get_current_user_id() : 'address:' . $address;
		$key = 'commercial-intent:' . substr( hash_hmac( 'sha256', $identity, wp_salt( 'nonce' ) ), 0, 40 ) . ':' . $bucket;
		if ( ! ( new Tra_Vel_Agent_Store() )->consume_limit( $key, $limit, ( $bucket + 1 ) * $window + MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'tra_vel_commercial_rate_limited', 'Too many commercial requests were started. Please wait before trying again.', array( 'status' => 429, 'retry_after' => max( 60, ( $bucket + 1 ) * $window - time() ) ) );
		}
		return true;
	}

	private function private_response( $data, $status = 200 ) {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Referrer-Policy', 'strict-origin-when-cross-origin' );
		return $response;
	}

	private function create_args() {
		return array(
			'idempotency_key'  => $this->idempotency_arg(),
			'vertical'         => array( 'type' => 'string', 'required' => true, 'enum' => Tra_Vel_Commercial_Intent_Policy::verticals(), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'surface'          => array( 'type' => 'string', 'default' => 'search-results', 'maxLength' => 32, 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'data_mode'        => array( 'type' => 'string', 'default' => 'demo', 'enum' => array( 'demo', 'mixed', 'live' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'requested_provider'=> array( 'type' => 'string', 'default' => 'tra-vel-concierge', 'pattern' => '^[a-z0-9_-]{2,40}$', 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'offer_id'         => array( 'type' => 'string', 'required' => true, 'pattern' => '^[A-Za-z0-9._:-]{1,80}$', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
			'candidate'        => array( 'type' => 'object', 'default' => array(), 'validate_callback' => 'rest_validate_request_arg' ),
			'trip'             => array( 'type' => 'object', 'required' => true, 'validate_callback' => 'rest_validate_request_arg' ),
		);
	}

	private function uuid_arg() {
		return array( 'type' => 'string', 'required' => true, 'format' => 'uuid', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' );
	}

	private function idempotency_arg() {
		return array( 'type' => 'string', 'required' => true, 'minLength' => 16, 'maxLength' => 100, 'pattern' => '^[A-Za-z0-9._:-]+$', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' );
	}
}
