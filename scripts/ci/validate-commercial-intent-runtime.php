<?php
/**
 * Deterministic runtime harness for commercial-intent policy and REST flow.
 *
 * No database, browser, network, supplier, message or payment work occurs.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_AGENT_PATH', dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['tvc_user_id'] = 0;
$GLOBALS['tvc_filter']  = null;
$GLOBALS['tvc_actions'] = array();
$GLOBALS['tvc_routes']  = array();

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
	public function __construct( $code, $message = '', $data = null ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}
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
		$this->method = $method;
		$this->params = $params;
		$this->headers = array_change_key_case( $headers, CASE_LOWER );
	}
	public function get_method() { return $this->method; }
	public function get_param( $key ) { return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null; }
	public function get_params() { return $this->params; }
	public function get_json_params() { return $this->params; }
	public function get_header( $name ) { return $this->headers[ strtolower( $name ) ] ?? ''; }
}

class Tra_Vel_Agent_Store {
	public static function is_ready() { return true; }
	public function consume_limit( $key, $limit, $expires_at ) { return $key && $limit > 0 && $expires_at > time(); }
}

class Tra_Vel_Commercial_Intent_Store {
	const ACTIVE_DAYS = 30;
	public static $ready = true;
	public $intent = null;
	public $record_calls = 0;
	public $create_calls = 0;
	public static function is_ready() { return self::$ready; }
	public function create_or_resume( $scope, $principal, $key ) {
		$this->create_calls++;
		$this->intent = array(
			'id' => 7,
			'intent_uuid' => '123e4567-e89b-42d3-a456-426614174000',
			'reference_code' => 'TVI-ABCDEFGH',
			'owner_user_id' => (int) $principal['user_id'],
			'owner_token_hash' => (string) $principal['token_hash'],
			'vertical' => (string) $scope['vertical'],
			'intent_version' => 1,
			'last_event_sequence' => 1,
			'scope' => $scope,
			'scope_digest' => Tra_Vel_Commercial_Intent_Policy::digest( $scope ),
			'created_at' => '2026-07-18 09:00:00',
			'updated_at' => '2026-07-18 09:00:00',
			'expires_at' => '2026-08-17 09:00:00',
			'retention_until' => '2026-10-16 09:00:00',
			'legal_hold' => 0,
		);
		return array(
			'intent' => $this->intent,
			'event' => array( 'event_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'type' => 'commercial_intent.created' ),
			'replayed' => false,
			'reused' => false,
			'created' => true,
		);
	}
	public function get_by_uuid( $uuid ) { return $this->intent && $uuid === $this->intent['intent_uuid'] ? $this->intent : null; }
	public function can_access( $intent, $user_id, $token_hash ) {
		if ( $user_id > 0 ) return (int) $intent['owner_user_id'] === $user_id;
		return 0 === (int) $intent['owner_user_id'] && hash_equals( (string) $intent['owner_token_hash'], (string) $token_hash );
	}
	public function record_handoff( $uuid, $version, $principal, $provider, $channel, $target_digest, $expires_at, $key ) {
		unset( $principal, $key );
		$this->record_calls++;
		if ( ! $this->intent || $uuid !== $this->intent['intent_uuid'] || $version !== $this->intent['intent_version'] ) {
			return new WP_Error( 'version_conflict', 'Version conflict.', array( 'status' => 409 ) );
		}
		$this->intent['intent_version']++;
		$this->intent['updated_at'] = '2026-07-18 09:01:00';
		return array(
			'intent' => $this->intent,
			'event' => array(
				'event_id' => 'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff',
				'type' => 'handoff.prepared',
				'data' => array( 'provider' => $provider, 'channel' => $channel, 'target_digest' => $target_digest, 'expires_at' => $expires_at, 'dispatched' => false, 'side_effect_executed' => false ),
			),
			'replayed' => false,
		);
	}
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_key( $value ) { return substr( preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ), 0, 200 ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/[\x00-\x1F\x7F]+/', ' ', strip_tags( (string) $value ) ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_generate_password( $length ) { return str_repeat( 'A', (int) $length ); }
function get_current_user_id() { return (int) $GLOBALS['tvc_user_id']; }
function wp_salt( $scheme = '' ) { return 'runtime-salt-' . $scheme; }
function wp_verify_nonce( $nonce, $action ) { return 'runtime-nonce' === $nonce && 'wp_rest' === $action; }
function home_url( $path = '/' ) { return 'https://tra-vel.co.il' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function esc_url_raw( $url, $protocols = null ) {
	$scheme = parse_url( (string) $url, PHP_URL_SCHEME );
	return $protocols && ! in_array( $scheme, $protocols, true ) ? '' : (string) $url;
}
function current_time( $type, $gmt = false ) { unset( $type, $gmt ); return '2026-07-18 09:00:00'; }
function apply_filters( $hook, $value, ...$args ) {
	if ( 'tra_vel_agent_commercial_intent_prepare_handoff' === $hook && is_callable( $GLOBALS['tvc_filter'] ) ) {
		return call_user_func( $GLOBALS['tvc_filter'], $value, ...$args );
	}
	if ( 'tra_vel_commercial_intent_create_limit' === $hook ) return $value;
	return $value;
}
function do_action( $hook, ...$args ) { $GLOBALS['tvc_actions'][] = array( $hook, $args ); }
function rest_validate_request_arg() { return true; }
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['tvc_routes'][] = array( $namespace, $route, $args ); }
function rest_ensure_response( $data ) { return new WP_REST_Response( $data ); }

require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-commercial-intent-policy.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-commercial-intent-controller.php';

function tvc_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Commercial intent runtime validation failed: {$message}\n" );
		exit( 1 );
	}
}
function tvc_error( $value, $code, $message ) {
	tvc_assert( is_wp_error( $value ) && $code === $value->get_error_code(), $message );
}

$raw = array(
	'idempotency_key' => 'commercial-create-1234567890',
	'vertical' => 'flight',
	'surface' => 'flight_results',
	'data_mode' => 'live',
	'requested_provider' => 'supplier-browser-claim',
	'offer_id' => 'flight-1',
	'candidate' => array( 'id' => 'flight-1', 'title' => 'Flight option', 'subtitle' => 'One stop', 'commercial_ref' => 'browser-ref', 'price_scope' => 'live' ),
	'trip' => array( 'origin' => 'TLV', 'destination' => 'HKT', 'depart_date' => '2026-11-03', 'return_date' => '2026-11-17', 'adults' => 2, 'children' => 1, 'infants' => 1, 'travelers' => 3, 'rooms' => 2, 'budget' => 5200, 'currency' => 'USD', 'return_path' => '/flights/?destination=HKT' ),
);
$scope = Tra_Vel_Commercial_Intent_Policy::normalize_scope( $raw );
tvc_assert( is_array( $scope ), 'valid commercial scope was rejected' );
tvc_assert( 4 === $scope['trip']['travelers'], 'infants were not included in the authoritative traveler count' );
tvc_assert( 'tra-vel-concierge' === $scope['resolved_provider'], 'a browser supplier claim escaped the owned-channel boundary' );
tvc_assert( 'non_binding_planning_intent' === $scope['commercial_boundary']['state'], 'non-binding planning truth is missing' );
tvc_assert( false === strpos( wp_json_encode( $scope ), '5200.55' ), 'an unbounded browser price entered the scope' );

$sensitive = $raw;
$sensitive['trip']['medical_condition'] = true;
tvc_error( Tra_Vel_Commercial_Intent_Policy::normalize_scope( $sensitive ), 'tra_vel_commercial_sensitive_field', 'medical data was accepted' );
$sensitive = $raw;
$sensitive['candidate']['oldest_age'] = 72;
tvc_error( Tra_Vel_Commercial_Intent_Policy::normalize_scope( $sensitive ), 'tra_vel_commercial_sensitive_field', 'traveler age was accepted' );
$transactional = $raw;
$transactional['candidate']['booking_status'] = 'confirmed';
tvc_error( Tra_Vel_Commercial_Intent_Policy::normalize_scope( $transactional ), 'tra_vel_commercial_sensitive_field', 'browser booking outcome was accepted' );
$bad_dates = $raw;
$bad_dates['trip']['depart_date'] = '2026-11-20';
$bad_dates['trip']['return_date'] = '2026-11-17';
tvc_error( Tra_Vel_Commercial_Intent_Policy::normalize_scope( $bad_dates ), 'tra_vel_commercial_dates_invalid', 'backward dates were accepted' );

$store = new Tra_Vel_Commercial_Intent_Store();
$controller = new Tra_Vel_Commercial_Intent_Controller( $store );
$headers = array( 'Origin' => 'https://tra-vel.co.il', 'X-WP-Nonce' => 'runtime-nonce' );
$request = new WP_REST_Request( 'POST', $raw, $headers );
tvc_assert( true === $controller->can_create( $request ), 'same-origin create was rejected' );
$cross_origin = new WP_REST_Request( 'POST', $raw, array( 'Origin' => 'https://evil.example' ) );
tvc_error( $controller->can_create( $cross_origin ), 'tra_vel_commercial_origin_rejected', 'cross-origin create was accepted' );
$wrong_port = new WP_REST_Request( 'POST', $raw, array( 'Origin' => 'https://tra-vel.co.il:8443' ) );
tvc_error( $controller->can_create( $wrong_port ), 'tra_vel_commercial_origin_rejected', 'same-host mutation on a different origin port was accepted' );

$create = $controller->create_intent( $request );
tvc_assert( $create instanceof WP_REST_Response && 201 === $create->status, 'create did not return a durable 201 response' );
tvc_assert( false === $create->data['side_effect_executed'], 'create claimed a side effect' );
tvc_assert( 'private_browser_owner' === $create->data['intent']['ownership'], 'guest ownership was not represented privately' );
tvc_assert( false !== strpos( $create->headers['Set-Cookie'] ?? '', 'Secure; HttpOnly; SameSite=Lax' ), 'guest owner cookie is not hardened' );
tvc_assert( 'private, no-store, max-age=0' === ( $create->headers['Cache-Control'] ?? '' ), 'private create response is cacheable' );
tvc_assert( 'noindex, nofollow, noarchive' === ( $create->headers['X-Robots-Tag'] ?? '' ), 'private create response is indexable' );

preg_match( '/^__Host-tra_vel_commercial=([^;]+)/', $create->headers['Set-Cookie'], $cookie_match );
$_COOKIE['__Host-tra_vel_commercial'] = rawurldecode( $cookie_match[1] ?? '' );
$intent_id = $create->data['intent']['intent_id'];
$access = new WP_REST_Request( 'POST', array( 'intent_id' => $intent_id, 'expected_version' => 1, 'idempotency_key' => 'commercial-handoff-123456' ), $headers );
tvc_assert( true === $controller->can_access( $access ), 'matching browser owner could not access its intent' );

$GLOBALS['tvc_filter'] = static function ( $prepared, $context ) {
	unset( $prepared );
	tvc_assert( 'TVI-ABCDEFGH' === $context['offer_id'], 'handoff did not use the server reference' );
	return array( 'provider' => 'tra-vel-concierge', 'handoff_url' => 'https://api.whatsapp.com/send?phone=972525101555', 'expires_at' => gmdate( 'c', time() + 300 ) );
};
$handoff = $controller->prepare_handoff( $access );
tvc_assert( $handoff instanceof WP_REST_Response && 200 === $handoff->status, 'owned handoff failed' );
tvc_assert( 1 === $store->record_calls, 'handoff event was not recorded exactly once' );
tvc_assert( 'handoff.prepared' === $handoff->data['event']['type'], 'handoff audit event is missing' );
tvc_assert( hash( 'sha256', $handoff->data['handoff_url'] ) === $handoff->data['event']['data']['target_digest'], 'handoff audit event is not bound to the returned target' );
tvc_assert( false === $handoff->data['side_effect_executed'], 'handoff preparation claimed that contact or booking occurred' );
tvc_assert( 2 === $handoff->data['intent']['version'], 'handoff did not advance the optimistic intent version' );
tvc_assert( 0 === strpos( $handoff->data['handoff_url'], 'https://api.whatsapp.com/' ), 'handoff returned an unowned host' );

$store->intent['intent_version'] = 2;
$malicious = new WP_REST_Request( 'POST', array( 'intent_id' => $intent_id, 'expected_version' => 2, 'idempotency_key' => 'commercial-handoff-evil1234' ), $headers );
$GLOBALS['tvc_filter'] = static function () { return array( 'provider' => 'tra-vel-concierge', 'handoff_url' => 'https://evil.example/collect', 'expires_at' => gmdate( 'c', time() + 300 ) ); };
tvc_error( $controller->prepare_handoff( $malicious ), 'tra_vel_commercial_handoff_rejected', 'an unowned handoff host was accepted' );
tvc_assert( 1 === $store->record_calls, 'a rejected URL was recorded as a prepared handoff' );

unset( $_COOKIE['__Host-tra_vel_commercial'] );
tvc_error( $controller->can_access( $access ), 'tra_vel_commercial_forbidden', 'guest intent was readable without its HttpOnly owner token' );

echo "Tra-Vel commercial-intent runtime validation passed (sensitive-field rejection, guest ownership, same-origin mutation, durable-before-navigation handoff, owned URL allowlist).\n";
