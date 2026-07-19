<?php
/**
 * Private-browser REST bridge for attested VIP trip-care reports.
 *
 * This is not a raw free-language endpoint. An upstream server-side vault and
 * classifier must issue a short-lived HMAC attestation binding the exact closed
 * envelope, vault reference, and classifier revision. This controller can then
 * acknowledge and route the report, but it cannot reserve, change, cancel, pay,
 * refund, disclose sensitive evidence, or claim any supplier execution.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_VIP_Intake_Controller extends WP_REST_Controller {
	const OWNER_COOKIE = '__Host-tra_vel_vip_intake_owner';

	/** @var Tra_Vel_VIP_Intake_Store */
	private $store;

	public function __construct( $store = null ) {
		$this->namespace = 'tra-vel-agent/v1';
		$this->rest_base = 'vip/intakes';
		$this->store     = $store ? $store : new Tra_Vel_VIP_Intake_Store();
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_intake' ),
				'permission_callback' => array( $this, 'can_create' ),
				'args'                => $this->create_args(),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<receipt_ref>TVR-[A-Z0-9]{10})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_receipt' ),
				'permission_callback' => array( $this, 'can_read' ),
				'args'                => array(
					'receipt_ref' => array(
						'type'              => 'string',
						'required'          => true,
						'pattern'           => '^TVR-[A-Z0-9]{10}$',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
	}

	public function can_create( $request ) {
		if ( ! $this->store->is_ready() ) {
			return new WP_Error( 'tra_vel_vip_intake_store_unavailable', 'Private trip-care intake is temporarily unavailable.', array( 'status' => 503 ) );
		}
		return $this->same_site_mutation( $request );
	}

	public function can_read( $request ) {
		if ( ! $this->store->is_ready() ) {
			return new WP_Error( 'tra_vel_vip_intake_store_unavailable', 'Private trip-care intake is temporarily unavailable.', array( 'status' => 503 ) );
		}
		$principal = $this->principal( false );
		$rate      = $this->consume_read_limit( $principal );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}
		$receipt   = $this->store->get_owned_receipt( (string) $request->get_param( 'receipt_ref' ), $principal['user_id'], $principal['token_hash'] );
		return $receipt
			? true
			: new WP_Error( 'tra_vel_vip_intake_receipt_missing', 'Private trip-care receipt not found.', array( 'status' => 404 ) );
	}

	public function create_intake( $request ) {
		$raw      = $request->get_json_params();
		$raw      = is_array( $raw ) ? $raw : $request->get_params();
		$envelope = isset( $raw['envelope'] ) && is_array( $raw['envelope'] ) ? $raw['envelope'] : null;
		$valid    = $this->validate_envelope_arg( $envelope, $request, 'envelope' );
		if ( true !== $valid ) {
			return $valid;
		}
		$attestation = $this->verify_normalization_attestation( $request->get_param( 'normalization_attestation' ), $envelope );
		if ( is_wp_error( $attestation ) ) {
			return $attestation;
		}

		$principal = $this->principal( true );
		if ( 0 === (int) $principal['user_id'] && 64 !== strlen( (string) $principal['token_hash'] ) ) {
			return new WP_Error( 'tra_vel_vip_intake_owner_unavailable', 'A private browser owner could not be established.', array( 'status' => 500 ) );
		}
		$rate = $this->consume_create_limit( $principal );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$request_digest = Tra_Vel_VIP_Intake_Policy::canonical_digest(
			array(
				'operation' => 'vip.intake.create',
				'envelope'  => $envelope,
				'normalization_attestation' => $request->get_param( 'normalization_attestation' ),
			)
		);
		$normalized = $this->server_normalize_envelope( $envelope );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}
		$result = $this->store->create_or_replay( $normalized, $principal, (string) $request->get_param( 'idempotency_key' ), $request_digest, $attestation );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$public = $this->store->public_receipt( $result['receipt'] );
		if ( is_wp_error( $public ) ) {
			return $public;
		}
		$response = $this->private_response( $public, ! empty( $result['created'] ) ? 201 : 200 );
		if ( ! empty( $principal['new_token'] ) ) {
			$this->attach_owner_cookie( $response, $principal['token'] );
		}
		return $response;
	}

	public function get_receipt( $request ) {
		$principal = $this->principal( false );
		$receipt = $this->store->get_owned_receipt( (string) $request->get_param( 'receipt_ref' ), $principal['user_id'], $principal['token_hash'] );
		if ( ! $receipt ) {
			return new WP_Error( 'tra_vel_vip_intake_receipt_missing', 'Private trip-care receipt not found.', array( 'status' => 404 ) );
		}
		$public = $this->store->public_receipt( $receipt );
		return is_wp_error( $public ) ? $public : $this->private_response( $public );
	}

	/**
	 * REST argument validator. Nested fields remain unsanitized-by-rewrite so the
	 * closed policy can reject unknown/raw fields rather than silently alter them.
	 */
	public function validate_envelope_arg( $value, $request, $param ) {
		unset( $param );
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'tra_vel_vip_intake_envelope_required', 'A normalized trip-care intake envelope is required.', array( 'status' => 400 ) );
		}
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > Tra_Vel_VIP_Intake_Store::MAX_ENVELOPE_BYTES ) {
			return new WP_Error( 'tra_vel_vip_intake_envelope_too_large', 'The normalized intake envelope is too large.', array( 'status' => 413 ) );
		}
		$boundary = $this->public_browser_boundary( $value );
		if ( true !== $boundary ) {
			return $boundary;
		}
		$key = $this->sanitize_idempotency_key( (string) $request->get_param( 'idempotency_key' ) );
		if ( strlen( $key ) < 16 || empty( $value['idempotency_digest'] ) || ! is_string( $value['idempotency_digest'] ) || ! hash_equals( hash( 'sha256', $key ), $value['idempotency_digest'] ) ) {
			return new WP_Error( 'tra_vel_vip_intake_idempotency_binding_invalid', 'The normalized intake is not bound to its retry key.', array( 'status' => 400 ) );
		}
		$submitted = Tra_Vel_VIP_Intake_Policy::intake( $value );
		if ( is_wp_error( $submitted ) ) {
			return $submitted;
		}
		$attestation = $this->verify_normalization_attestation( $request->get_param( 'normalization_attestation' ), $value );
		if ( is_wp_error( $attestation ) ) {
			return $attestation;
		}
		$normalized = $this->server_normalize_envelope( $value );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}
		$validated = Tra_Vel_VIP_Intake_Policy::intake( $normalized );
		return is_wp_error( $validated ) ? $validated : true;
	}

	public function validate_normalization_attestation_arg( $value, $request, $param ) {
		unset( $param );
		$envelope = $request->get_param( 'envelope' );
		if ( ! is_array( $envelope ) ) {
			return new WP_Error( 'tra_vel_vip_intake_envelope_required', 'A normalized trip-care intake envelope is required.', array( 'status' => 400 ) );
		}
		$verified = $this->verify_normalization_attestation( $value, $envelope );
		return is_wp_error( $verified ) ? $verified : true;
	}

	/**
	 * Server-side upstream helper. Never expose this signer through a public REST
	 * route; call it only after the message is durably vaulted and classified.
	 *
	 * @return array|WP_Error
	 */
	public static function issue_normalization_attestation( $envelope, $classifier_revision, $now = null ) {
		$validated = Tra_Vel_VIP_Intake_Policy::intake( $envelope );
		if ( is_wp_error( $validated ) || ! is_string( $classifier_revision ) || 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/', $classifier_revision ) ) {
			return new WP_Error( 'tra_vel_vip_intake_normalization_not_signable', 'Only a valid vaulted and classified envelope can be attested.', array( 'status' => 500 ) );
		}
		$upstream_verified = apply_filters( 'tra_vel_vip_intake_normalization_attestation_issuable', false, $envelope['content']['message_vault_ref'], $classifier_revision, $envelope );
		if ( true !== $upstream_verified ) {
			return new WP_Error( 'tra_vel_vip_intake_normalization_upstream_unavailable', 'A configured server-side vault and classifier must verify this envelope before attestation.', array( 'status' => 503 ) );
		}
		$issued_timestamp = null === $now ? time() : (int) $now;
		try {
			$nonce = rtrim( strtr( base64_encode( random_bytes( 24 ) ), '+/', '-_' ), '=' );
		} catch ( Exception $error ) {
			$nonce = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', wp_generate_password( 48, false, false ) ), 0, 48 );
		}
		$unsigned = array(
			'contract_version'   => '1.0.0',
			'purpose'            => 'vip_intake_normalized',
			'envelope_digest'    => Tra_Vel_VIP_Intake_Policy::canonical_digest( $envelope ),
			'message_vault_ref'  => (string) $envelope['content']['message_vault_ref'],
			'classifier_revision'=> $classifier_revision,
			'issued_at'          => gmdate( 'Y-m-d\TH:i:s\Z', $issued_timestamp ),
			'expires_at'         => gmdate( 'Y-m-d\TH:i:s\Z', $issued_timestamp + 300 ),
			'nonce'              => $nonce,
		);
		$unsigned['signature'] = hash_hmac( 'sha256', Tra_Vel_VIP_Intake_Policy::canonical_digest( $unsigned ), wp_salt( 'auth' ) );
		return $unsigned;
	}

	private function verify_normalization_attestation( $attestation, $envelope ) {
		$keys = array( 'contract_version', 'purpose', 'envelope_digest', 'message_vault_ref', 'classifier_revision', 'issued_at', 'expires_at', 'nonce', 'signature' );
		if ( ! is_array( $attestation ) || array_diff( $keys, array_keys( $attestation ) ) || array_diff( array_keys( $attestation ), $keys ) || '1.0.0' !== (string) ( $attestation['contract_version'] ?? '' ) || 'vip_intake_normalized' !== (string) ( $attestation['purpose'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_vip_intake_normalization_attestation_required', 'A verified vault and classifier attestation is required.', array( 'status' => 403 ) );
		}
		foreach ( array( 'envelope_digest', 'signature' ) as $digest_field ) {
			if ( ! is_string( $attestation[ $digest_field ] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $attestation[ $digest_field ] ) ) {
				return new WP_Error( 'tra_vel_vip_intake_normalization_attestation_invalid', 'The vault and classifier attestation is invalid.', array( 'status' => 403 ) );
			}
		}
		if ( ! is_string( $attestation['message_vault_ref'] ) || 1 !== preg_match( '/^tv_vault_[A-Za-z0-9_-]{16,96}$/', $attestation['message_vault_ref'] ) || ! is_string( $attestation['classifier_revision'] ) || 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/', $attestation['classifier_revision'] ) || ! is_string( $attestation['nonce'] ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{32,96}$/', $attestation['nonce'] ) ) {
			return new WP_Error( 'tra_vel_vip_intake_normalization_attestation_invalid', 'The vault and classifier attestation is invalid.', array( 'status' => 403 ) );
		}
		$issued  = self::utc_timestamp( $attestation['issued_at'] );
		$expires = self::utc_timestamp( $attestation['expires_at'] );
		$now     = time();
		if ( false === $issued || false === $expires || $issued > $now + 30 || $issued < $now - 300 || $expires <= $now || $expires > $issued + 300 ) {
			return new WP_Error( 'tra_vel_vip_intake_normalization_attestation_expired', 'The vault and classifier attestation has expired.', array( 'status' => 403 ) );
		}
		$expected_envelope = Tra_Vel_VIP_Intake_Policy::canonical_digest( $envelope );
		$vault_ref         = is_array( $envelope ) && isset( $envelope['content']['message_vault_ref'] ) ? (string) $envelope['content']['message_vault_ref'] : '';
		if ( ! hash_equals( $expected_envelope, (string) $attestation['envelope_digest'] ) || ! hash_equals( $vault_ref, (string) $attestation['message_vault_ref'] ) ) {
			return new WP_Error( 'tra_vel_vip_intake_normalization_attestation_mismatch', 'The normalized envelope changed after vaulting or classification.', array( 'status' => 403 ) );
		}
		$unsigned = $attestation;
		unset( $unsigned['signature'] );
		$expected_signature = hash_hmac( 'sha256', Tra_Vel_VIP_Intake_Policy::canonical_digest( $unsigned ), wp_salt( 'auth' ) );
		if ( ! hash_equals( $expected_signature, (string) $attestation['signature'] ) ) {
			return new WP_Error( 'tra_vel_vip_intake_normalization_attestation_invalid', 'The vault and classifier attestation is invalid.', array( 'status' => 403 ) );
		}
		return array(
			'attestation_digest' => Tra_Vel_VIP_Intake_Policy::canonical_digest( $attestation ),
			'classifier_revision'=> (string) $attestation['classifier_revision'],
			'issued_at'          => (string) $attestation['issued_at'],
		);
	}

	private static function utc_timestamp( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value ) ) {
			return false;
		}
		$timestamp = strtotime( $value );
		return false !== $timestamp && gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ) === $value ? $timestamp : false;
	}

	private function public_browser_boundary( $envelope ) {
		$source = isset( $envelope['source'] ) && is_array( $envelope['source'] ) ? $envelope['source'] : array();
		$access = isset( $envelope['access'] ) && is_array( $envelope['access'] ) ? $envelope['access'] : array();
		$match  = isset( $envelope['trip_match'] ) && is_array( $envelope['trip_match'] ) ? $envelope['trip_match'] : array();
		if ( 'web' !== (string) ( $source['channel'] ?? '' ) || ! in_array( (string) ( $source['sender_trust'] ?? '' ), array( 'anonymous', 'unverified' ), true ) || ! empty( $source['sender_assertion_digest'] ) || ! empty( $source['scanner_opened'] ) ) {
			return new WP_Error( 'tra_vel_vip_intake_source_claim_rejected', 'This route accepts only an unverified public web report.', array( 'status' => 403 ) );
		}
		if ( ! in_array( (string) ( $access['mode'] ?? '' ), array( 'public_safety', 'public_incident' ), true ) || null !== ( $access['capability_ref'] ?? null ) || null !== ( $access['capability_digest'] ?? null ) || 'absent' !== (string) ( $access['capability_state'] ?? '' ) || null !== ( $access['session_evidence_digest'] ?? null ) || ! empty( $access['executable_scopes'] ) || 'none' !== (string) ( $access['authorization_effect'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_vip_intake_authority_claim_rejected', 'Public intake cannot claim a session, capability, or action authority.', array( 'status' => 403 ) );
		}
		if ( ! in_array( (string) ( $match['status'] ?? '' ), array( 'not_attempted', 'no_trip_claimed' ), true ) || null !== ( $match['trip_ref'] ?? null ) || null !== ( $match['case_ref'] ?? null ) || null !== ( $match['match_evidence_digest'] ?? null ) || 0 !== (int) ( $match['candidate_count'] ?? -1 ) || 'none' !== (string) ( $match['case_state'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_vip_intake_trip_claim_rejected', 'A public browser report cannot select or prove a private trip or case.', array( 'status' => 403 ) );
		}
		return true;
	}

	private function server_normalize_envelope( $envelope ) {
		if ( ! is_array( $envelope ) || ! isset( $envelope['timing'], $envelope['receipt'], $envelope['classification'], $envelope['source'] ) || ! is_array( $envelope['timing'] ) || ! is_array( $envelope['classification'] ) || ! is_array( $envelope['source'] ) ) {
			return new WP_Error( 'tra_vel_vip_intake_shape_invalid', 'The normalized intake envelope is incomplete.', array( 'status' => 400 ) );
		}
		$now_timestamp = time();
		$now           = gmdate( 'Y-m-d\TH:i:s\Z', $now_timestamp );
		$reported      = $envelope['timing']['reported_at'] ?? null;
		if ( null === $reported ) {
			$delay_class = 'unknown';
		} else {
			$reported_timestamp = strtotime( (string) $reported );
			if ( false === $reported_timestamp || $reported_timestamp > $now_timestamp + 300 ) {
				return new WP_Error( 'tra_vel_vip_intake_report_time_invalid', 'The reported incident time could not be reconciled with the server clock.', array( 'status' => 400 ) );
			}
			$delay       = max( 0, $now_timestamp - $reported_timestamp );
			$delay_class = $delay <= 1800 ? 'current' : ( $delay <= DAY_IN_SECONDS ? 'delayed' : 'offline_replay' );
			if ( 'offline_replay' === $delay_class ) {
				$signals = isset( $envelope['classification']['risk_signals'] ) && is_array( $envelope['classification']['risk_signals'] ) ? $envelope['classification']['risk_signals'] : array();
				$signals = array_values( array_diff( $signals, array( 'none' ) ) );
				$signals[] = 'offline';
				$envelope['classification']['risk_signals'] = array_values( array_unique( $signals ) );
			}
		}
		$envelope['timing']['received_at']    = $now;
		$envelope['timing']['normalized_at']  = $now;
		$envelope['timing']['delay_class']    = $delay_class;
		$envelope['timing']['sla_started_at'] = $now;
		$envelope['receipt'] = array(
			'status'                  => 'queued',
			'delivery_attempt_digest' => null,
			'next_retry_at'           => null,
			'calm_receipt'            => true,
			'login_required'          => false,
		);
		$envelope['source']['transport_integrity'] = 'verified';
		return $envelope;
	}

	private function principal( $create ) {
		$user_id = get_current_user_id();
		$token   = $this->owner_cookie_token();
		$new     = false;
		if ( ! $token && $create && 0 === $user_id ) {
			try {
				$token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
			} catch ( Exception $error ) {
				$token = wp_generate_password( 48, false, false );
			}
			$new = true;
		}
		$token_hash = $token ? hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ) : '';
		$principal_hash = $user_id > 0
			? hash_hmac( 'sha256', 'vip-user:' . $user_id, wp_salt( 'auth' ) )
			: $token_hash;
		return array( 'user_id' => (int) $user_id, 'token' => $token, 'token_hash' => $token_hash, 'principal_hash' => $principal_hash, 'new_token' => $new );
	}

	private function owner_cookie_token() {
		if ( empty( $_COOKIE[ self::OWNER_COOKIE ] ) ) {
			return '';
		}
		$raw   = wp_unslash( (string) $_COOKIE[ self::OWNER_COOKIE ] );
		$token = rawurldecode( $raw );
		return 1 === preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $token ) ? $token : '';
	}

	private function attach_owner_cookie( $response, $token ) {
		$response->header( 'Set-Cookie', self::OWNER_COOKIE . '=' . rawurlencode( (string) $token ) . '; Max-Age=' . ( Tra_Vel_VIP_Intake_Store::RECEIPT_DAYS * DAY_IN_SECONDS ) . '; Path=/; Secure; HttpOnly; SameSite=Strict' );
	}

	private function same_site_mutation( $request ) {
		if ( get_current_user_id() > 0 ) {
			$nonce = (string) $request->get_header( 'X-WP-Nonce' );
			if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error( 'tra_vel_vip_intake_nonce_invalid', 'The signed-in session could not be verified.', array( 'status' => 403 ) );
			}
		}
		$source = (string) $request->get_header( 'Origin' );
		if ( ! $source ) {
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
		$source_user   = wp_parse_url( $source, PHP_URL_USER );
		$source_pass   = wp_parse_url( $source, PHP_URL_PASS );
		if ( ! $source_host || ! $home_host || ! hash_equals( $home_host, $source_host ) || 'https' !== $source_scheme || 'https' !== $home_scheme || $source_port !== $home_port || $source_user || $source_pass ) {
			return new WP_Error( 'tra_vel_vip_intake_origin_rejected', 'Trip-care intake must come from the Tra-Vel website.', array( 'status' => 403 ) );
		}
		return true;
	}

	private function consume_create_limit( $principal ) {
		$window  = 10 * MINUTE_IN_SECONDS;
		$expires = ( (int) floor( time() / $window ) + 1 ) * $window + MINUTE_IN_SECONDS;
		$limit   = min( 100, max( 4, (int) apply_filters( 'tra_vel_vip_intake_create_limit', 12 ) ) );
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$address = filter_var( $address, FILTER_VALIDATE_IP ) ? $address : 'unknown';
		$address_key   = hash_hmac( 'sha256', 'vip-intake:create:address:' . $address, wp_salt( 'nonce' ) );
		$principal_key = hash_hmac( 'sha256', 'vip-intake:create:principal:' . (string) $principal['principal_hash'], wp_salt( 'nonce' ) );
		if ( ! $this->store->consume_limit( $address_key, $limit * 2, $expires ) || ! $this->store->consume_limit( $principal_key, $limit, $expires ) ) {
			return new WP_Error( 'tra_vel_vip_intake_rate_limited', 'Too many trip-care reports were submitted. Please wait before trying again.', array( 'status' => 429, 'retry_after' => max( 60, $expires - time() ) ) );
		}
		return true;
	}

	private function consume_read_limit( $principal ) {
		if ( 64 !== strlen( (string) $principal['principal_hash'] ) ) {
			return new WP_Error( 'tra_vel_vip_intake_receipt_missing', 'Private trip-care receipt not found.', array( 'status' => 404 ) );
		}
		$window  = 10 * MINUTE_IN_SECONDS;
		$expires = ( (int) floor( time() / $window ) + 1 ) * $window + MINUTE_IN_SECONDS;
		$key     = hash_hmac( 'sha256', 'vip-intake:read:' . (string) $principal['principal_hash'], wp_salt( 'nonce' ) );
		if ( ! $this->store->consume_limit( $key, 120, $expires ) ) {
			return new WP_Error( 'tra_vel_vip_intake_rate_limited', 'Too many receipt checks were made. Please wait before trying again.', array( 'status' => 429, 'retry_after' => max( 60, $expires - time() ) ) );
		}
		return true;
	}

	private function private_response( $data, $status = 200 ) {
		$response = new WP_REST_Response( $data, (int) $status );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Referrer-Policy', 'no-referrer' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		return $response;
	}

	private function create_args() {
		return array(
			'idempotency_key' => array(
				'type'              => 'string',
				'required'          => true,
				'minLength'         => 16,
				'maxLength'         => 100,
				'pattern'           => '^[A-Za-z0-9._:-]+$',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'envelope' => array(
				'type'              => 'object',
				'required'          => true,
				'validate_callback' => array( $this, 'validate_envelope_arg' ),
			),
			'normalization_attestation' => array(
				'type'              => 'object',
				'required'          => true,
				'validate_callback' => array( $this, 'validate_normalization_attestation_arg' ),
			),
		);
	}

	private function sanitize_idempotency_key( $key ) {
		return substr( preg_replace( '/[^A-Za-z0-9._:-]/', '', (string) $key ), 0, 100 );
	}
}
