<?php
/**
 * Deterministic REST/runtime harness for the private-browser VIP intake slice.
 *
 * No database, network, supplier, payment, reservation, or message delivery is
 * performed. A memory store exercises controller and policy boundaries.
 */

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['tvvi_user_id'] = 0;
$GLOBALS['tvvi_routes']  = array();
$GLOBALS['tvvi_upstream_verified'] = true;

class WP_REST_Controller {
	protected $namespace;
	protected $rest_base;
}

class WP_REST_Server {
	const READABLE  = 'GET';
	const CREATABLE = 'POST';
}

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

class WP_REST_Response {
	public $data;
	public $status;
	public $headers = array();
	public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; }
	public function header( $name, $value ) { $this->headers[ $name ] = $value; }
}

class WP_REST_Request {
	private $method;
	private $params;
	private $headers;
	public function __construct( $method = 'GET', $params = array(), $headers = array() ) {
		$this->method  = $method;
		$this->params  = $params;
		$this->headers = array_change_key_case( $headers, CASE_LOWER );
	}
	public function get_method() { return $this->method; }
	public function get_param( $key ) { return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null; }
	public function get_params() { return $this->params; }
	public function get_json_params() { return $this->params; }
	public function get_header( $name ) { return $this->headers[ strtolower( $name ) ] ?? ''; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/[\x00-\x1F\x7F]+/', ' ', strip_tags( (string) $value ) ) ); }
function rest_validate_request_arg() { return true; }
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['tvvi_routes'][ $namespace . $route ] = $args; }
function get_current_user_id() { return (int) $GLOBALS['tvvi_user_id']; }
function wp_salt( $scheme = '' ) { return 'vip-runtime-salt-' . $scheme; }
function wp_verify_nonce( $nonce, $action ) { return 'vip-runtime-nonce' === $nonce && 'wp_rest' === $action; }
function home_url( $path = '/' ) { return 'https://tra-vel.co.il' . ltrim( (string) $path, '/' ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function wp_generate_password( $length ) { return str_repeat( 'R', (int) $length ); }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function apply_filters( $hook, $value ) {
	if ( 'tra_vel_vip_intake_normalization_attestation_issuable' === $hook ) return (bool) $GLOBALS['tvvi_upstream_verified'];
	return $value;
}
function current_time( $type, $gmt = false ) { unset( $type, $gmt ); return gmdate( 'Y-m-d H:i:s' ); }

$vip_base = dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/includes/vip/';
require_once $vip_base . 'class-tra-vel-vip-intake-taxonomy.php';
require_once $vip_base . 'class-tra-vel-vip-intake-policy.php';
require_once $vip_base . 'class-tra-vel-vip-intake-state-projection.php';
require_once $vip_base . 'class-tra-vel-vip-intake-store.php';

class TVVI_Memory_Store extends Tra_Vel_VIP_Intake_Store {
	public $receipts = array();
	public $indexes = array( 'by_ref' => array(), 'by_channel_event' => array(), 'by_correlation' => array() );
	public $idempotency = array();
	public $normalized_envelopes = array();
	public $limit_calls = array();
	public $allow_limits = true;
	public static $ready = true;

	public static function is_ready() { return self::$ready; }

	public function create_or_replay( $envelope, $principal, $idempotency_key, $request_digest, $normalization ) {
		$scope = $principal['principal_hash'] . '|' . $idempotency_key;
		if ( isset( $this->idempotency[ $scope ] ) ) {
			$prior = $this->idempotency[ $scope ];
			if ( ! hash_equals( $prior['request_digest'], $request_digest ) ) {
				return new WP_Error( 'tra_vel_vip_intake_store_idempotency_conflict', 'conflict', array( 'status' => 409 ) );
			}
			return array( 'receipt' => $this->receipts[ $prior['receipt_ref'] ], 'created' => false, 'replayed' => true, 'deduplicated' => (bool) $this->receipts[ $prior['receipt_ref'] ]['duplicate'] );
		}
		$result = Tra_Vel_VIP_Intake_Policy::intake( $envelope, $this->indexes );
		if ( is_wp_error( $result ) ) return $result;
		$projection = Tra_Vel_VIP_Intake_State_Projection::project( $result );
		if ( is_wp_error( $projection ) ) return $projection;
		$truth = self::truthful_state( $projection, $envelope );
		$now = gmdate( 'Y-m-d H:i:s' );
		$receipt = array(
			'id' => count( $this->receipts ) + 1,
			'canonical_intake_id' => 1,
			'receipt_ref' => $envelope['public_receipt_ref'],
			'attempt_intake_ref' => $envelope['intake_ref'],
			'attempt_fingerprint' => $result['fingerprint'],
			'owner_user_id' => (int) $principal['user_id'],
			'owner_token_hash' => (int) $principal['user_id'] > 0 ? '' : (string) $principal['token_hash'],
			'principal_hash' => (string) $principal['principal_hash'],
			'normalization_attestation_digest' => (string) $normalization['attestation_digest'],
			'classifier_revision' => (string) $normalization['classifier_revision'],
			'public_state' => $truth['state'],
			'message_code' => $truth['message_code'],
			'step_up_required' => $truth['step_up_required'] ? 1 : 0,
			'human_review_required' => $truth['human_review_required'] ? 1 : 0,
			'duplicate' => $result['duplicate'] ? 1 : 0,
			'created_at' => $now,
			'updated_at' => $now,
			'expires_at' => gmdate( 'Y-m-d H:i:s', time() + self::RECEIPT_DAYS * DAY_IN_SECONDS ),
			'retention_until' => gmdate( 'Y-m-d H:i:s', time() + self::RETENTION_DAYS * DAY_IN_SECONDS ),
		);
		$this->receipts[ $receipt['receipt_ref'] ] = $receipt;
		$this->normalized_envelopes[] = $envelope;
		if ( ! $result['duplicate'] ) {
			$this->indexes = Tra_Vel_VIP_Intake_Policy::index_accepted( $this->indexes, $result );
		}
		$this->idempotency[ $scope ] = array( 'request_digest' => $request_digest, 'receipt_ref' => $receipt['receipt_ref'] );
		return array( 'receipt' => $receipt, 'created' => ! $result['duplicate'], 'replayed' => false, 'deduplicated' => (bool) $result['duplicate'] );
	}

	public function get_owned_receipt( $receipt_ref, $user_id, $owner_token_hash ) {
		$receipt = $this->receipts[ $receipt_ref ] ?? null;
		return $receipt && $this->can_access( $receipt, $user_id, $owner_token_hash ) ? $receipt : null;
	}

	public function consume_limit( $key, $limit, $expires_at ) {
		$this->limit_calls[] = array( $key, $limit, $expires_at );
		return $this->allow_limits;
	}
}

require_once $vip_base . 'class-tra-vel-vip-intake-controller.php';

$assertions = 0;
function tvvi_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "VIP intake REST runtime failed: {$message}\n" );
		exit( 1 );
	}
}
function tvvi_error( $value, $code, $message ) {
	tvvi_assert( is_wp_error( $value ) && $code === $value->get_error_code(), $message . ( is_wp_error( $value ) ? ' (got ' . $value->get_error_code() . ')' : '' ) );
}
function tvvi_ref( $kind, $seed ) { return 'tv_' . $kind . '_' . str_pad( preg_replace( '/[^A-Za-z0-9_-]/', '', $seed ), 16, 'x' ); }
function tvvi_digest( $seed ) { return hash( 'sha256', $seed ); }
function tvvi_boundary() {
	return array(
		'raw_message_exposed' => false,
		'raw_contact_data_exposed' => false,
		'raw_attachment_exposed' => false,
		'raw_identity_data_exposed' => false,
		'raw_payment_data_exposed' => false,
		'raw_medical_data_exposed' => false,
		'raw_provider_payload_exposed' => false,
		'bearer_secret_exposed' => false,
	);
}
function tvvi_fixture( $seed, $key ) {
	$reported = gmdate( 'Y-m-d\TH:i:s\Z', time() - 60 );
	return array(
		'contract_version' => '1.0.0',
		'intake_ref' => tvvi_ref( 'intake', $seed ),
		'public_receipt_ref' => 'TVR-' . strtoupper( substr( hash( 'sha256', 'receipt-' . $seed ), 0, 10 ) ),
		'idempotency_digest' => hash( 'sha256', $key ),
		'correlation_digest' => tvvi_digest( 'correlation-' . $seed ),
		'content' => array(
			'message_digest' => tvvi_digest( 'message-' . $seed ),
			'message_vault_ref' => tvvi_ref( 'vault', 'message-' . $seed ),
			'language_tag' => 'he-IL',
			'semantic_summary_codes' => array( 'flight.delay.reported' ),
			'attachments' => array(),
		),
		'source' => array(
			'channel' => 'web',
			'channel_event_digest' => tvvi_digest( 'channel-' . $seed ),
			'sender_assertion_digest' => null,
			'sender_trust' => 'anonymous',
			'transport_integrity' => 'not_available',
			'device_risk' => 'none',
			'scanner_opened' => false,
		),
		'access' => array(
			'mode' => 'public_incident',
			'capability_ref' => null,
			'capability_digest' => null,
			'capability_state' => 'absent',
			'requested_scopes' => array( 'incident_report' ),
			'permitted_intake_scopes' => array( 'incident_report' ),
			'executable_scopes' => array(),
			'authorization_effect' => 'none',
			'session_evidence_digest' => null,
		),
		'trip_match' => array( 'status' => 'no_trip_claimed', 'trip_ref' => null, 'case_ref' => null, 'case_state' => 'none', 'candidate_count' => 0, 'match_evidence_digest' => null ),
		'classification' => array( 'normalized_intent' => 'incident_report', 'incident_family' => 'flight_disruption', 'immediate_danger' => false, 'ambiguity' => 'none', 'priority' => 'P2', 'risk_signals' => array( 'none' ) ),
		'timing' => array( 'reported_at' => $reported, 'received_at' => $reported, 'normalized_at' => $reported, 'delay_class' => 'current', 'sla_started_at' => $reported ),
		'receipt' => array( 'status' => 'queued', 'delivery_attempt_digest' => null, 'next_retry_at' => null, 'calm_receipt' => true, 'login_required' => false ),
		'data_boundary' => tvvi_boundary(),
	);
}
function tvvi_request( $envelope, $key, $headers = null, $attestation = null ) {
	if ( null === $attestation ) {
		$attestation = Tra_Vel_VIP_Intake_Controller::issue_normalization_attestation( $envelope, 'runtime-classifier-1.0.0' );
	}
	return new WP_REST_Request( 'POST', array( 'idempotency_key' => $key, 'envelope' => $envelope, 'normalization_attestation' => $attestation ), null === $headers ? array( 'Origin' => 'https://tra-vel.co.il' ) : $headers );
}

$store      = new TVVI_Memory_Store();
$controller = new Tra_Vel_VIP_Intake_Controller( $store );
$controller->register_routes();
tvvi_assert( isset( $GLOBALS['tvvi_routes']['tra-vel-agent/v1/vip/intakes'] ), 'POST /vip/intakes route was not registered' );
tvvi_assert( isset( $GLOBALS['tvvi_routes']['tra-vel-agent/v1/vip/intakes/(?P<receipt_ref>TVR-[A-Z0-9]{10})'] ), 'owner receipt GET route was not registered' );
tvvi_assert( WP_REST_Server::CREATABLE === $GLOBALS['tvvi_routes']['tra-vel-agent/v1/vip/intakes']['methods'], 'intake route is not POST-only' );
tvvi_assert( is_callable( $GLOBALS['tvvi_routes']['tra-vel-agent/v1/vip/intakes']['permission_callback'] ), 'intake route has no explicit permission callback' );

$key      = 'vip-intake-runtime-key-0001';
$envelope = tvvi_fixture( 'ordinary', $key );
$request  = tvvi_request( $envelope, $key );
tvvi_assert( true === $controller->can_create( $request ), 'same-origin no-login intake was rejected' );
tvvi_error( $controller->can_create( tvvi_request( $envelope, $key, array( 'Origin' => 'https://evil.example' ) ) ), 'tra_vel_vip_intake_origin_rejected', 'cross-origin intake was accepted' );
tvvi_error( $controller->can_create( tvvi_request( $envelope, $key, array() ) ), 'tra_vel_vip_intake_origin_rejected', 'originless public mutation was accepted' );

$GLOBALS['tvvi_user_id'] = 17;
tvvi_error( $controller->can_create( $request ), 'tra_vel_vip_intake_nonce_invalid', 'signed-in cookie mutation without a REST nonce was accepted' );
$signed_headers = array( 'Origin' => 'https://tra-vel.co.il', 'X-WP-Nonce' => 'vip-runtime-nonce' );
tvvi_assert( true === $controller->can_create( tvvi_request( $envelope, $key, $signed_headers ) ), 'valid signed-in same-origin nonce was rejected' );
$GLOBALS['tvvi_user_id'] = 0;

$created = $controller->create_intake( $request );
tvvi_assert( $created instanceof WP_REST_Response && 201 === $created->status, 'new intake did not return 201 after durable storage' );
tvvi_assert( 'received' === $created->data['state'], 'ordinary report did not return the truthful received state' );
tvvi_assert( array( 'received', 'checking', 'need_information', 'human_review' ) === Tra_Vel_VIP_Intake_Store::public_states(), 'public receipt states drifted beyond the four-state contract' );
tvvi_assert( false === $created->data['supplier_action_started'] && false === $created->data['payment_action_started'], 'receipt claimed supplier or payment execution' );
tvvi_assert( array( 'raw_message_received_by_bridge' => false, 'normalized_vault_reference_received' => true, 'classifier_claim_verified' => true ) === $created->data['message_disposition'], 'receipt did not truthfully distinguish attested normalized metadata from raw-message intake' );
tvvi_assert( 'none' === $created->data['verification']['authorization_effect'] && array() === $created->data['verification']['executable_scopes'], 'receipt gained execution authority' );
tvvi_assert( false === $created->data['login_required'] && true === $created->data['resume']['available'], 'private-browser receipt incorrectly requires login or cannot resume' );
tvvi_assert( 'private_browser_owner' === $created->data['ownership'], 'guest receipt did not identify private-browser ownership' );
tvvi_assert( false !== strpos( $created->headers['Set-Cookie'] ?? '', 'Secure; HttpOnly; SameSite=Strict' ), 'owner cookie is not Secure, HttpOnly, and Strict' );
tvvi_assert( 'private, no-store, max-age=0' === ( $created->headers['Cache-Control'] ?? '' ) && 'noindex, nofollow, noarchive' === ( $created->headers['X-Robots-Tag'] ?? '' ), 'private receipt response is cacheable or indexable' );
tvvi_assert( count( $store->limit_calls ) === 2 && 64 === strlen( $store->limit_calls[0][0] ) && false === strpos( $store->limit_calls[0][0], '127.0.0.1' ), 'create rate limit did not use bounded HMAC identities' );
$normalized = $store->normalized_envelopes[0];
tvvi_assert( $normalized['timing']['received_at'] === $normalized['timing']['sla_started_at'] && 'queued' === $normalized['receipt']['status'], 'server did not own the SLA and initial receipt state' );
tvvi_assert( 'verified' === $normalized['source']['transport_integrity'], 'same-site HTTPS transport was not server-normalized' );

preg_match( '/^__Host-tra_vel_vip_intake_owner=([^;]+)/', $created->headers['Set-Cookie'], $cookie_match );
$_COOKIE[ Tra_Vel_VIP_Intake_Controller::OWNER_COOKIE ] = rawurldecode( $cookie_match[1] ?? '' );
$receipt_ref = $created->data['receipt_ref'];
$get_request = new WP_REST_Request( 'GET', array( 'receipt_ref' => $receipt_ref ) );
tvvi_assert( true === $controller->can_read( $get_request ), 'matching browser owner could not access its receipt' );
$read_limit_calls = count( $store->limit_calls );
$read = $controller->get_receipt( $get_request );
tvvi_assert( $read instanceof WP_REST_Response && $read->data === $created->data, 'owner GET did not return the same minimal receipt' );
tvvi_assert( $read_limit_calls === count( $store->limit_calls ), 'receipt callback consumed a second rate-limit unit after its permission check' );

$replayed = $controller->create_intake( $request );
tvvi_assert( $replayed instanceof WP_REST_Response && 200 === $replayed->status && $replayed->data === $created->data, 'exact idempotent retry did not replay the prior receipt' );

unset( $_COOKIE[ Tra_Vel_VIP_Intake_Controller::OWNER_COOKIE ] );
tvvi_error( $controller->can_read( $get_request ), 'tra_vel_vip_intake_receipt_missing', 'receipt was readable without its private owner token' );
$_COOKIE[ Tra_Vel_VIP_Intake_Controller::OWNER_COOKIE ] = str_repeat( 'Z', 43 );
tvvi_error( $controller->can_read( $get_request ), 'tra_vel_vip_intake_receipt_missing', 'receipt was readable with a different browser token' );
$_COOKIE[ Tra_Vel_VIP_Intake_Controller::OWNER_COOKIE ] = rawurldecode( $cookie_match[1] ?? '' );

$high_key = 'vip-intake-runtime-high-0002';
$high = tvvi_fixture( 'high-impact', $high_key );
$high['access']['requested_scopes'] = array( 'incident_report', 'service_cancel', 'payment_authorize', 'refund_destination_change' );
$high['classification']['normalized_intent'] = 'high_impact_request';
$high_result = $controller->create_intake( tvvi_request( $high, $high_key ) );
tvvi_assert( $high_result instanceof WP_REST_Response && 'need_information' === $high_result->data['state'], 'consequential request did not become a need-information receipt' );
tvvi_assert( true === $high_result->data['verification']['step_up_required'] && array() === $high_result->data['verification']['executable_scopes'], 'cancel/payment/refund request did not require zero-authority step-up' );
tvvi_assert( false === $high_result->data['supplier_action_started'] && false === $high_result->data['payment_action_started'], 'high-impact intake pretended to execute an action' );

$danger_key = 'vip-intake-runtime-danger-003';
$danger = tvvi_fixture( 'danger', $danger_key );
$danger['access']['mode'] = 'public_safety';
$danger['classification'] = array( 'normalized_intent' => 'safety_report', 'incident_family' => 'immediate_danger', 'immediate_danger' => true, 'ambiguity' => 'none', 'priority' => 'P0', 'risk_signals' => array( 'safety', 'stranded' ) );
$danger_result = $controller->create_intake( tvvi_request( $danger, $danger_key ) );
tvvi_assert( $danger_result instanceof WP_REST_Response && 'human_review' === $danger_result->data['state'] && true === $danger_result->data['human_review_required'], 'danger report was not surfaced as required specialist review' );
tvvi_assert( 'specialist_review_required' === $danger_result->data['message_code'] && false === $danger_result->data['supplier_action_started'], 'danger receipt made an unverified handoff/execution claim' );

$duplicate_key = 'vip-intake-runtime-duplicate-4';
$duplicate = tvvi_fixture( 'duplicate', $duplicate_key );
$duplicate['correlation_digest'] = $envelope['correlation_digest'];
$duplicate['content']['message_digest'] = $envelope['content']['message_digest'];
$duplicate_result = $controller->create_intake( tvvi_request( $duplicate, $duplicate_key ) );
tvvi_assert( $duplicate_result instanceof WP_REST_Response && 200 === $duplicate_result->status && true === $duplicate_result->data['duplicate'], 'cross-event duplicate created a second canonical case' );
tvvi_assert( 'received' === $duplicate_result->data['state'] && 'request_already_received' === $duplicate_result->data['message_code'], 'duplicate did not get a calm already-received receipt' );

$wrong_digest = tvvi_fixture( 'wrong-digest', 'vip-intake-runtime-wrong-0005' );
$wrong_digest['idempotency_digest'] = tvvi_digest( 'another-key' );
tvvi_error( $controller->create_intake( tvvi_request( $wrong_digest, 'vip-intake-runtime-wrong-0005' ) ), 'tra_vel_vip_intake_idempotency_binding_invalid', 'unbound envelope/idempotency key was accepted' );

$raw = tvvi_fixture( 'raw-field', 'vip-intake-runtime-raw-000006' );
$raw['content']['free_text'] = 'raw customer message';
tvvi_error( $controller->create_intake( tvvi_request( $raw, 'vip-intake-runtime-raw-000006' ) ), 'tra_vel_vip_intake_shape_invalid', 'raw free text crossed the normalized boundary' );

$authority = tvvi_fixture( 'authority-claim', 'vip-intake-runtime-auth-00007' );
$authority['access']['mode'] = 'verified_session';
$authority['access']['session_evidence_digest'] = tvvi_digest( 'fake-session' );
tvvi_error( $controller->create_intake( tvvi_request( $authority, 'vip-intake-runtime-auth-00007' ) ), 'tra_vel_vip_intake_authority_claim_rejected', 'public browser claimed a verified session' );

$trip_claim = tvvi_fixture( 'trip-claim', 'vip-intake-runtime-trip-00008' );
$trip_claim['trip_match'] = array( 'status' => 'unique', 'trip_ref' => tvvi_ref( 'trip', 'claimed' ), 'case_ref' => null, 'case_state' => 'none', 'candidate_count' => 1, 'match_evidence_digest' => tvvi_digest( 'claimed-match' ) );
tvvi_error( $controller->create_intake( tvvi_request( $trip_claim, 'vip-intake-runtime-trip-00008' ) ), 'tra_vel_vip_intake_trip_claim_rejected', 'public browser selected a private trip' );

$missing_attestation_key = 'vip-intake-runtime-attest-0010';
$missing_attestation = tvvi_fixture( 'missing-attestation', $missing_attestation_key );
tvvi_error( $controller->create_intake( tvvi_request( $missing_attestation, $missing_attestation_key, null, array() ) ), 'tra_vel_vip_intake_normalization_attestation_required', 'anonymous browser supplied a normalized envelope without upstream vault/classifier attestation' );

$GLOBALS['tvvi_upstream_verified'] = false;
$unverified_upstream = Tra_Vel_VIP_Intake_Controller::issue_normalization_attestation( $missing_attestation, 'runtime-classifier-1.0.0' );
tvvi_error( $unverified_upstream, 'tra_vel_vip_intake_normalization_upstream_unavailable', 'signer attested a vault reference without an upstream existence/classifier check' );
$GLOBALS['tvvi_upstream_verified'] = true;

$forged_key = 'vip-intake-runtime-forged-0011';
$forged = tvvi_fixture( 'forged-attestation', $forged_key );
$forged_attestation = Tra_Vel_VIP_Intake_Controller::issue_normalization_attestation( $forged, 'runtime-classifier-1.0.0' );
$forged_attestation['signature'] = str_repeat( 'a', 64 );
tvvi_error( $controller->create_intake( tvvi_request( $forged, $forged_key, null, $forged_attestation ) ), 'tra_vel_vip_intake_normalization_attestation_invalid', 'fabricated vault/classifier signature was accepted' );

$classification_key = 'vip-intake-runtime-class-0012';
$classification = tvvi_fixture( 'classification-tamper', $classification_key );
$classification_attestation = Tra_Vel_VIP_Intake_Controller::issue_normalization_attestation( $classification, 'runtime-classifier-1.0.0' );
$classification['classification']['incident_family'] = 'accommodation_problem';
tvvi_error( $controller->create_intake( tvvi_request( $classification, $classification_key, null, $classification_attestation ) ), 'tra_vel_vip_intake_normalization_attestation_mismatch', 'caller-authored classification changed after server attestation' );

$vault_key = 'vip-intake-runtime-vault-0013';
$vault = tvvi_fixture( 'vault-tamper', $vault_key );
$vault_attestation = Tra_Vel_VIP_Intake_Controller::issue_normalization_attestation( $vault, 'runtime-classifier-1.0.0' );
$vault['content']['message_vault_ref'] = tvvi_ref( 'vault', 'fabricated-vault' );
tvvi_error( $controller->create_intake( tvvi_request( $vault, $vault_key, null, $vault_attestation ) ), 'tra_vel_vip_intake_normalization_attestation_mismatch', 'caller substituted a fabricated vault reference after attestation' );

$store->allow_limits = false;
$rate_key = 'vip-intake-runtime-rate-00009';
$rate = $controller->create_intake( tvvi_request( tvvi_fixture( 'rate-limit', $rate_key ), $rate_key ) );
tvvi_error( $rate, 'tra_vel_vip_intake_rate_limited', 'rate limiter failure did not stop intake before storage' );

$expected_public_keys = array( 'contract_version', 'receipt_ref', 'state', 'message_code', 'accepted', 'duplicate', 'login_required', 'ownership', 'received_at', 'updated_at', 'expires_at', 'message_disposition', 'resume', 'verification', 'human_review_required', 'supplier_action_started', 'payment_action_started' );
tvvi_assert( $expected_public_keys === array_keys( $created->data ), 'public receipt shape leaked internal state or omitted a required boundary' );
foreach ( array( 'intake_ref', 'trip_ref', 'case_ref', 'owner_token_hash', 'principal_hash', 'fingerprint', 'safe_actions', 'envelope', 'message_vault_ref' ) as $forbidden ) {
	 tvvi_assert( ! array_key_exists( $forbidden, $created->data ), "public receipt leaked {$forbidden}" );
}

echo "VIP intake REST runtime passed ({$assertions} assertions; owner cookie, same-site mutation, idempotency, dedupe, rate limits, four truthful states, zero execution).\n";
