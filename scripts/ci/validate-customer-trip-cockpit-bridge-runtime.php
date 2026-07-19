<?php
/**
 * Adversarial no-database runtime for the customer Trip Cockpit REST bridge.
 *
 * This gate exercises the real controller, private projection factory, and
 * customer-redaction factory with in-memory provider and capability-session
 * doubles. It intentionally has no WordPress bootstrap or database dependency.
 */

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['ctcb_routes']       = array();
$GLOBALS['ctcb_filters']      = array();
$GLOBALS['ctcb_events']       = array();
$GLOBALS['ctcb_user_id']      = 0;
$GLOBALS['ctcb_can_read']     = true;
$GLOBALS['ctcb_scope_filter'] = null;

class WP_REST_Controller {
	protected $namespace;
	protected $rest_base;
}

class WP_REST_Server {
	const READABLE = 'GET';
}

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code, $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

class WP_REST_Response {
	public $data;
	public $status;
	public $headers = array();

	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	public function header( $name, $value ) {
		$this->headers[ $name ] = $value;
	}
}

class WP_REST_Request {
	private $method;
	private $headers;
	private $query;
	private $body;
	private $route;

	public function __construct( $method = 'GET', $headers = array(), $query = array(), $body = '', $route = '/tra-vel-agent/v1/customer-trip-cockpit/current' ) {
		$this->method  = (string) $method;
		$this->headers = array_change_key_case( $headers, CASE_LOWER );
		$this->query   = $query;
		$this->body    = $body;
		$this->route   = (string) $route;
	}

	public function get_method() {
		return $this->method;
	}

	public function get_header( $name ) {
		$name = strtolower( (string) $name );
		return isset( $this->headers[ $name ] ) ? $this->headers[ $name ] : '';
	}

	public function set_header( $name, $value ) {
		$this->headers[ strtolower( (string) $name ) ] = (string) $value;
	}

	public function get_query_params() {
		return $this->query;
	}

	public function get_body() {
		return $this->body;
	}

	public function set_body( $body ) {
		$this->body = (string) $body;
	}

	public function set_query_params( $query ) {
		$this->query = $query;
	}

	public function get_route() {
		return $this->route;
	}
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, $flags );
}

function register_rest_route( $namespace, $route, $args ) {
	$GLOBALS['ctcb_routes'][ $namespace . $route ] = $args;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['ctcb_filters'][ $hook ][] = array( $callback, $priority, $accepted_args );
}

function rest_convert_error_to_response( $error ) {
	$data   = is_wp_error( $error ) ? $error->get_error_data() : array();
	$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
	return new WP_REST_Response(
		array(
			'code' => is_wp_error( $error ) ? $error->get_error_code() : 'unknown_error',
			'message' => is_wp_error( $error ) ? $error->get_error_message() : 'Unknown error.',
		),
		$status
	);
}

function rest_ensure_response( $response ) {
	return $response instanceof WP_REST_Response ? $response : new WP_REST_Response( $response, 200 );
}

function get_current_user_id() {
	return (int) $GLOBALS['ctcb_user_id'];
}

function current_user_can( $capability ) {
	return 'read' === $capability && true === $GLOBALS['ctcb_can_read'];
}

function wp_verify_nonce( $nonce, $action ) {
	return 'ctcb-valid-rest-nonce' === $nonce && 'wp_rest' === $action;
}

function get_current_blog_id() {
	return 1;
}

function wp_salt( $scheme = '' ) {
	return 'ctcb-runtime-secret-' . (string) $scheme;
}

function home_url( $path = '/' ) {
	unset( $path );
	return 'https://tra-vel.co.il/';
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( (string) $url, $component );
}

function wp_unslash( $value ) {
	return $value;
}

function apply_filters( $hook, $value ) {
	if ( 'tra_vel_customer_trip_cockpit_signed_in_scopes' === $hook && is_array( $GLOBALS['ctcb_scope_filter'] ) ) {
		return $GLOBALS['ctcb_scope_filter'];
	}
	return $value;
}

/** Minimal public surface needed by the bridge's capability cookie binding. */
class Tra_Vel_VIP_Capability_Session_Controller {
	const SESSION_COOKIE = '__Host-tra_vel_vip_capability_session';
}

/** In-memory capability-session authority with a deterministic event trace. */
class Tra_Vel_VIP_Capability_Session_Store {
	public $ready = true;
	public $session_result;
	public $resolved_result;
	public $current_calls = array();
	public $resolve_calls = array();

	public static function session_digest( $session_value ) {
		return hash( 'sha256', 'ctcb-session|' . (string) $session_value );
	}

	public function is_ready() {
		$GLOBALS['ctcb_events'][] = 'capability_ready';
		return true === $this->ready;
	}

	public function current_session( $session_value, $now = null ) {
		$GLOBALS['ctcb_events'][] = 'capability_current';
		$this->current_calls[]     = array( $session_value, $now );
		return $this->session_result;
	}

	public function resolve_scoped_session( $session_value, $binding, $required_scope, $disclosure_class, $now = null ) {
		$GLOBALS['ctcb_events'][] = 'capability_resolve';
		$this->resolve_calls[]     = array( $session_value, $binding, $required_scope, $disclosure_class, $now );
		return $this->resolved_result;
	}
}

$vip = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/vip/';
require_once $vip . 'interface-tra-vel-customer-trip-cockpit-read-model-provider.php';

/** Trusted read-model double; no browser-controlled projection enters it. */
class CTCB_Memory_Provider implements Tra_Vel_Customer_Trip_Cockpit_Read_Model_Provider {
	public static $active;
	public $ready = true;
	public $limit_mode = 'allow';
	public $owned_result;
	public $bound_result;
	public $limit_calls = array();
	public $owned_calls = array();
	public $bound_calls = array();

	public function __construct() {
		self::$active = $this;
	}

	public static function is_ready() {
		$GLOBALS['ctcb_events'][] = 'provider_ready';
		return self::$active instanceof self && true === self::$active->ready;
	}

	public function get_owned_current_projection( $owner_user_id, $now = null ) {
		$GLOBALS['ctcb_events'][] = 'provider_owned';
		$this->owned_calls[]       = array( $owner_user_id, $now );
		return $this->owned_result;
	}

	public function get_bound_projection( $trip_ref, $case_ref, $account_ref, $now = null ) {
		$GLOBALS['ctcb_events'][] = 'provider_bound';
		$this->bound_calls[]       = array( $trip_ref, $case_ref, $account_ref, $now );
		return $this->bound_result;
	}

	public function consume_limit( $limit_key, $limit, $expires_at ) {
		$GLOBALS['ctcb_events'][] = 'limit';
		$this->limit_calls[]       = array( $limit_key, $limit, $expires_at );
		if ( 'error' === $this->limit_mode ) {
			return new WP_Error( 'provider_limit_secret', 'database host leaked', array( 'status' => 503 ) );
		}
		return 'allow' === $this->limit_mode;
	}
}

require_once $vip . 'class-tra-vel-vip-taxonomy.php';
require_once $vip . 'class-tra-vel-traveler-principal.php';
require_once $vip . 'class-tra-vel-customer-trip-cockpit-policy.php';
require_once $vip . 'class-tra-vel-customer-trip-cockpit-factory.php';
require_once $vip . 'class-tra-vel-customer-trip-cockpit-customer-view-policy.php';
require_once $vip . 'class-tra-vel-customer-trip-cockpit-customer-view-factory.php';
require_once $vip . 'class-tra-vel-customer-trip-cockpit-controller.php';

$ctcb_assertions = 0;
$ctcb_scenarios  = 0;

function ctcb_assert( $condition, $message ) {
	global $ctcb_assertions;
	$ctcb_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, 'Customer Trip Cockpit bridge runtime failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function ctcb_error( $value, $suffix, $status, $message ) {
	ctcb_assert( is_wp_error( $value ), $message . ' (no WP_Error returned)' );
	if ( ! is_wp_error( $value ) ) {
		return;
	}
	ctcb_assert( 'tra_vel_customer_trip_cockpit_' . $suffix === $value->get_error_code(), $message . ' (received ' . $value->get_error_code() . ')' );
	$data = $value->get_error_data();
	ctcb_assert( is_array( $data ) && $status === (int) ( isset( $data['status'] ) ? $data['status'] : 0 ), $message . ' (wrong status)' );
}

function ctcb_iso( $timestamp ) {
	return gmdate( 'Y-m-d\TH:i:s\Z', (int) $timestamp );
}

function ctcb_ref( $kind, $seed ) {
	return 'tv_' . $kind . '_' . str_pad( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $seed ), 20, 'x' );
}

function ctcb_source_fixture( $owner_user_id, $now = null ) {
	$now       = null === $now ? time() : (int) $now;
	$clock     = ctcb_iso( $now - 30 );
	$account   = Tra_Vel_Traveler_Principal::account_ref( $owner_user_id );
	$trip      = ctcb_ref( 'trip', 'bridge-runtime-trip' );
	$flight    = ctcb_ref( 'service', 'bridge-flight' );
	$hotel     = ctcb_ref( 'service', 'bridge-hotel' );
	$traveler  = ctcb_ref( 'traveler', 'bridge-adult' );
	$scope     = Tra_Vel_Traveler_Principal::cockpit_owner_scope_digest( $owner_user_id, $account, $trip );

	return array(
		'contract_version' => '1.0.0',
		'environment' => 'sandbox',
		'cockpit_ref' => ctcb_ref( 'cockpit', 'bridge-runtime-cockpit' ),
		'trip_ref' => $trip,
		'owner_scope_digest' => $scope,
		'revision' => 1,
		'previous_projection_digest' => null,
		'headline' => 'Thailand trip with connected services',
		'registration' => array(
			'gate' => 'ready_to_travel', 'readiness' => 'ready', 'pending_requirement_codes' => array(),
			'next_action' => null, 'verified_at' => $clock,
		),
		'trip_health' => array(
			'phase' => 'pre_trip', 'health' => 'on_track', 'dependency_projection_ref' => ctcb_ref( 'graph', 'bridge-graph'),
			'recovery_projection_ref' => null, 'affected_service_refs' => array(), 'unaffected_service_refs' => array( $flight, $hotel ),
			'next_action' => null, 'verified_at' => $clock,
		),
		'services' => array(
			array(
				'service_ref' => $flight, 'sequence' => 1, 'vertical' => 'flight', 'label' => 'Outbound flight',
				'phase' => 'confirmed', 'health' => 'on_track', 'fulfillment' => array( 'state' => 'confirmed', 'truth_state' => 'verified_current' ),
				'change_state' => 'unchanged', 'protected_codes' => array( 'schedule.monitoring', 'trip.insurance' ), 'next_action' => null,
				'events' => array( array( 'event_ref' => ctcb_ref( 'timeline_event', 'bridge-flight-event' ), 'event_code' => 'flight.confirmed', 'state' => 'confirmed', 'truth_state' => 'verified_current', 'occurred_at' => $clock ) ),
				'verified_at' => $clock,
			),
			array(
				'service_ref' => $hotel, 'sequence' => 2, 'vertical' => 'accommodation', 'label' => 'Phuket stay',
				'phase' => 'confirmed', 'health' => 'on_track', 'fulfillment' => array( 'state' => 'confirmed', 'truth_state' => 'verified_current' ),
				'change_state' => 'unchanged', 'protected_codes' => array( 'late.arrival.monitoring' ), 'next_action' => null,
				'events' => array( array( 'event_ref' => ctcb_ref( 'timeline_event', 'bridge-hotel-event' ), 'event_code' => 'stay.confirmed', 'state' => 'confirmed', 'truth_state' => 'verified_current', 'occurred_at' => $clock ) ),
				'verified_at' => $clock,
			),
		),
		'protections' => array(
			array( 'protection_code' => 'trip.insurance', 'service_refs' => array( $flight, $hotel ), 'state' => 'active', 'verified_at' => $clock ),
			array( 'protection_code' => 'schedule.monitoring', 'service_refs' => array( $flight ), 'state' => 'active', 'verified_at' => $clock ),
		),
		'changes' => array(),
		'approvals' => array(),
		'questions' => array(),
		'vip_cases' => array(),
		'trip_care_receipts' => array(),
		'commerce_orders' => array(
			array(
				'order_ref' => ctcb_ref( 'order', 'bridge-order' ), 'service_refs' => array( $flight, $hotel ),
				'funds_state' => 'collected', 'funds_truth_state' => 'verified_current',
				'payment_state' => 'captured', 'payment_truth_state' => 'verified_current',
				'fulfillment_state' => 'confirmed', 'fulfillment_truth_state' => 'verified_current',
				'refund_state' => 'not_requested', 'refund_truth_state' => 'verified_current',
				'settlement_state' => 'pending', 'settlement_truth_state' => 'verified_current',
				'next_action' => null, 'verified_at' => $clock,
			),
		),
		'loyalty' => array( 'status' => 'options_ready', 'affected_service_refs' => array( $flight ), 'next_action' => null, 'verified_at' => $clock ),
		'traveler_readiness' => array(
			array( 'traveler_ref' => $traveler, 'subject_kind' => 'adult', 'readiness' => 'ready', 'pending_requirement_codes' => array(), 'next_action' => null, 'deadline' => null, 'verified_at' => $clock ),
		),
		'offline_pack' => array( 'status' => 'ready', 'itinerary' => 'ready', 'service_contacts' => 'ready', 'emergency_contacts' => 'ready', 'next_action' => null, 'verified_at' => $clock ),
		'observed_at' => $clock,
		'data_boundary' => array(
			'server_only' => true, 'already_validated_projections_only' => true, 'raw_identity_data_stored' => false,
			'raw_payment_data_stored' => false, 'raw_medical_data_stored' => false, 'raw_provider_payload_stored' => false, 'bearer_secret_stored' => false,
		),
	);
}

function ctcb_projection( $owner_user_id = 17 ) {
	$projection = Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( ctcb_source_fixture( $owner_user_id ), time() );
	ctcb_assert( is_array( $projection ), 'The trusted private fixture must validate before bridge tests' . ( is_wp_error( $projection ) ? ' (' . $projection->get_error_code() . ')' : '' ) . '.' );
	return $projection;
}

function ctcb_record( $owner_user_id = 17, $projection = null ) {
	if ( null === $projection ) {
		$projection = ctcb_projection( $owner_user_id );
	}
	$account = Tra_Vel_Traveler_Principal::account_ref( $owner_user_id );
	return array(
		'projection' => $projection,
		'owner_user_id' => (int) $owner_user_id,
		'account_ref' => $account,
		'trip_ref' => $projection['trip_ref'],
		'case_ref' => null,
		'owner_scope_digest' => Tra_Vel_Traveler_Principal::cockpit_owner_scope_digest( $owner_user_id, $account, $projection['trip_ref'] ),
	);
}

function ctcb_session( $record, $scopes = null ) {
	if ( null === $scopes ) {
		$scopes = array( 'trip_view_redacted', 'incident_report', 'case_progress_view' );
	}
	return array(
		'trip_ref' => $record['trip_ref'],
		'case_ref' => null,
		'account_ref' => $record['account_ref'],
		'allowed_scopes' => $scopes,
		'expires_at' => ctcb_iso( time() + 900 ),
	);
}

function ctcb_request( $mode = 'signed-in', $overrides = array() ) {
	$headers = array(
		'X-Tra-Vel-Cockpit-Mode' => $mode,
		'X-Tra-Vel-Cockpit-Read' => '1',
		'Origin' => 'https://tra-vel.co.il',
		'Sec-Fetch-Site' => 'same-origin',
	);
	if ( 'signed-in' === $mode ) {
		$headers['X-WP-Nonce'] = 'ctcb-valid-rest-nonce';
	}
	if ( isset( $overrides['headers'] ) ) {
		foreach ( $overrides['headers'] as $name => $value ) {
			if ( null === $value ) {
				unset( $headers[ $name ] );
			} else {
				$headers[ $name ] = $value;
			}
		}
	}
	return new WP_REST_Request(
		isset( $overrides['method'] ) ? $overrides['method'] : 'GET',
		$headers,
		isset( $overrides['query'] ) ? $overrides['query'] : array(),
		isset( $overrides['body'] ) ? $overrides['body'] : ''
	);
}

function ctcb_harness( $mode = 'signed-in', $scopes = null ) {
	$GLOBALS['ctcb_events']       = array();
	$GLOBALS['ctcb_scope_filter'] = null;
	$GLOBALS['ctcb_can_read']     = true;
	$GLOBALS['ctcb_user_id']      = 'signed-in' === $mode ? 17 : 0;
	$_COOKIE                       = array();

	$provider             = new CTCB_Memory_Provider();
	$capability           = new Tra_Vel_VIP_Capability_Session_Store();
	$record               = ctcb_record( 17 );
	$provider->owned_result = $record;
	$provider->bound_result = $record;
	$session              = ctcb_session( $record, $scopes );
	$capability->session_result  = $session;
	$capability->resolved_result = $session;
	$cookie = 'ctcb_runtime_session_value_1234567890ABCDE';
	if ( 'scoped-session' === $mode ) {
		$_COOKIE[ Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE ] = rawurlencode( $cookie );
	}

	return array(
		'provider' => $provider,
		'capability' => $capability,
		'record' => $record,
		'session' => $session,
		'cookie' => $cookie,
		'controller' => new Tra_Vel_Customer_Trip_Cockpit_Controller( $provider, $capability ),
	);
}

function ctcb_run( $name, $callback ) {
	global $ctcb_scenarios;
	$callback();
	$ctcb_scenarios++;
}

function ctcb_authorize_and_read( $harness, $request ) {
	$allowed = $harness['controller']->can_read( $request );
	ctcb_assert( true === $allowed, 'Permission callback must authorize a valid request.' );
	return $harness['controller']->get_current( $request );
}

ctcb_run( 'route_and_provider_contract', function () {
	$h = ctcb_harness();
	$h['controller']->register_routes();
	$route = 'tra-vel-agent/v1/customer-trip-cockpit/current';
	ctcb_assert( isset( $GLOBALS['ctcb_routes'][ $route ] ), 'The exact customer cockpit route must be registered.' );
	ctcb_assert( WP_REST_Server::READABLE === $GLOBALS['ctcb_routes'][ $route ]['methods'], 'The customer cockpit route must be GET-only.' );
	ctcb_assert( array( $h['controller'], 'can_read' ) === $GLOBALS['ctcb_routes'][ $route ]['permission_callback'], 'The route must use the closed permission callback.' );
	ctcb_assert( array( $h['controller'], 'get_current' ) === $GLOBALS['ctcb_routes'][ $route ]['callback'], 'The route must use the customer-view callback.' );
	ctcb_assert( isset( $GLOBALS['ctcb_filters']['rest_post_dispatch'][0] ) && array( $h['controller'], 'secure_route_response' ) === $GLOBALS['ctcb_filters']['rest_post_dispatch'][0][0] && 10 === $GLOBALS['ctcb_filters']['rest_post_dispatch'][0][1] && 3 === $GLOBALS['ctcb_filters']['rest_post_dispatch'][0][2], 'The route must register its post-dispatch privacy boundary for both success and error responses.' );
	$methods = get_class_methods( 'Tra_Vel_Customer_Trip_Cockpit_Read_Model_Provider' );
	sort( $methods );
	ctcb_assert( array( 'consume_limit', 'get_bound_projection', 'get_owned_current_projection', 'is_ready' ) === $methods, 'The provider abstraction must expose only readiness, exact reads, and rate limiting.' );
} );

ctcb_run( 'signed_in_success_and_order', function () {
	$h = ctcb_harness();
	$response = ctcb_authorize_and_read( $h, ctcb_request() );
	ctcb_assert( $response instanceof WP_REST_Response && 200 === $response->status, 'A signed-in owner must receive the customer response.' );
	ctcb_assert( array( 'limit', 'provider_ready', 'provider_owned' ) === $GLOBALS['ctcb_events'], 'Signed-in processing must rate-limit before readiness and owner lookup.' );
	ctcb_assert( 1 === count( $h['provider']->owned_calls ) && 17 === $h['provider']->owned_calls[0][0], 'Signed-in lookup must use the exact current owner id.' );
	ctcb_assert( 'signed_in' === $response->data['audience']['mode'], 'Signed-in response must carry the explicit signed-in audience.' );
} );

ctcb_run( 'scoped_success_exact_binding_and_scope', function () {
	$h = ctcb_harness( 'scoped-session' );
	$response = ctcb_authorize_and_read( $h, ctcb_request( 'scoped-session' ) );
	ctcb_assert( $response instanceof WP_REST_Response && 200 === $response->status, 'A valid whole-trip session must receive a response.' );
	ctcb_assert( array( 'limit', 'provider_ready', 'capability_ready', 'capability_current', 'provider_bound', 'capability_resolve' ) === $GLOBALS['ctcb_events'], 'Scoped reads must follow the limiter, readiness, session, projection, and resolution order.' );
	ctcb_assert( array( $h['record']['trip_ref'], null, $h['record']['account_ref'] ) === array_slice( $h['provider']->bound_calls[0], 0, 3 ), 'The provider read must carry the exact whole-trip/account binding and a null case.' );
	$resolve = $h['capability']->resolve_calls[0];
	ctcb_assert( $h['cookie'] === $resolve[0] && array( 'trip_ref' => $h['record']['trip_ref'], 'case_ref' => null, 'account_ref' => $h['record']['account_ref'] ) === $resolve[1], 'Capability resolution must use the cookie and exact provider binding.' );
	ctcb_assert( 'trip_view_redacted' === $resolve[2] && 'trip_redacted' === $resolve[3], 'Capability resolution must demand the redacted trip scope and disclosure.' );
	ctcb_assert( 'scoped_session' === $response->data['audience']['mode'], 'Scoped response must identify the scoped audience.' );
} );

foreach ( array(
	'query' => array( 'query' => array( 'trip_ref' => ctcb_ref( 'trip', 'attacker' ) ) ),
	'body' => array( 'body' => '{"trip_ref":"attacker"}' ),
) as $name => $override ) {
	ctcb_run( 'closed_' . $name, function () use ( $name, $override ) {
		$h = ctcb_harness();
		$result = $h['controller']->can_read( ctcb_request( 'signed-in', $override ) );
		ctcb_error( $result, 'request_invalid', 400, 'A non-empty ' . $name . ' must fail the closed read contract.' );
		ctcb_assert( array() === $GLOBALS['ctcb_events'], 'A rejected ' . $name . ' must not reach authentication or storage.' );
	} );
}

foreach ( array(
	'missing_intent' => array( 'X-Tra-Vel-Cockpit-Read' => null ),
	'wrong_intent' => array( 'X-Tra-Vel-Cockpit-Read' => 'true' ),
	'missing_mode' => array( 'X-Tra-Vel-Cockpit-Mode' => null ),
	'unknown_mode' => array( 'X-Tra-Vel-Cockpit-Mode' => 'automatic' ),
) as $name => $headers ) {
	ctcb_run( $name, function () use ( $name, $headers ) {
		$h = ctcb_harness();
		$result = $h['controller']->can_read( ctcb_request( 'signed-in', array( 'headers' => $headers ) ) );
		ctcb_error( $result, 'request_invalid', 400, 'Invalid closed header scenario ' . $name . ' must fail.' );
		ctcb_assert( array() === $GLOBALS['ctcb_events'], 'Invalid closed headers must not consume a rate limit.' );
	} );
}

ctcb_run( 'whitespace_body_is_empty', function () {
	$h = ctcb_harness();
	$response = ctcb_authorize_and_read( $h, ctcb_request( 'signed-in', array( 'body' => " \r\n\t" ) ) );
	ctcb_assert( 200 === $response->status, 'Transport whitespace must not become an attacker-controlled body.' );
} );

foreach ( array(
	'foreign_origin' => array( 'Origin' => 'https://evil.example' ),
	'origin_path' => array( 'Origin' => 'https://tra-vel.co.il/private' ),
	'origin_credentials' => array( 'Origin' => 'https://user:pass@tra-vel.co.il' ),
	'cross_site_fetch' => array( 'Sec-Fetch-Site' => 'cross-site' ),
	'same_site_not_same_origin' => array( 'Sec-Fetch-Site' => 'same-site' ),
) as $name => $headers ) {
	ctcb_run( $name, function () use ( $name, $headers ) {
		$h = ctcb_harness();
		$result = $h['controller']->can_read( ctcb_request( 'signed-in', array( 'headers' => $headers ) ) );
		ctcb_error( $result, 'origin_rejected', 403, 'Origin scenario ' . $name . ' must fail closed.' );
		ctcb_assert( array() === $GLOBALS['ctcb_events'], 'Rejected origin evidence must not touch the limiter or provider.' );
	} );
}

ctcb_run( 'origin_may_be_omitted_on_same_origin_get', function () {
	$h = ctcb_harness();
	$request = ctcb_request( 'signed-in', array( 'headers' => array( 'Origin' => null, 'Sec-Fetch-Site' => null ) ) );
	$response = ctcb_authorize_and_read( $h, $request );
	ctcb_assert( 200 === $response->status, 'A same-origin GET without an Origin header must remain usable.' );
} );

ctcb_run( 'signed_in_requires_user', function () {
	$h = ctcb_harness();
	$GLOBALS['ctcb_user_id'] = 0;
	$result = $h['controller']->can_read( ctcb_request() );
	ctcb_error( $result, 'login_required', 401, 'Signed-in mode must require an authenticated user.' );
	ctcb_assert( array() === $GLOBALS['ctcb_events'], 'Anonymous signed-in attempts must not consume storage work.' );
} );

foreach ( array( 'missing' => null, 'invalid' => 'wrong-nonce' ) as $name => $nonce ) {
	ctcb_run( 'signed_nonce_' . $name, function () use ( $name, $nonce ) {
		$h = ctcb_harness();
		$_COOKIE[ Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE ] = rawurlencode( $h['cookie'] );
		$request = ctcb_request( 'signed-in', array( 'headers' => array( 'X-WP-Nonce' => $nonce ) ) );
		$result = $h['controller']->can_read( $request );
		ctcb_error( $result, 'nonce_invalid', 403, 'Signed-in mode must reject a ' . $name . ' nonce.' );
		ctcb_assert( array() === $GLOBALS['ctcb_events'], 'A cookie must never become a fallback for a failed signed-in nonce.' );
	} );
}

ctcb_run( 'signed_in_capability_required', function () {
	$h = ctcb_harness();
	$GLOBALS['ctcb_can_read'] = false;
	$result = $h['controller']->can_read( ctcb_request() );
	ctcb_error( $result, 'read_denied', 403, 'The signed-in principal must retain the WordPress read capability.' );
	ctcb_assert( array() === $GLOBALS['ctcb_events'], 'Capability denial must happen before rate or provider work.' );
} );

foreach ( array( 'deny' => 'deny', 'error' => 'error' ) as $name => $mode ) {
	ctcb_run( 'signed_limiter_' . $name, function () use ( $name, $mode ) {
		$h = ctcb_harness();
		$h['provider']->limit_mode = $mode;
		$result = $h['controller']->can_read( ctcb_request() );
		ctcb_error( $result, 'deny' === $name ? 'rate_limited' : 'limit_unavailable', 'deny' === $name ? 429 : 503, 'Signed limiter scenario ' . $name . ' must fail safely.' );
		ctcb_assert( array( 'limit' ) === $GLOBALS['ctcb_events'], 'Limiter failure must precede and suppress readiness/provider reads.' );
		$call = $h['provider']->limit_calls[0];
		ctcb_assert( 1 === preg_match( '/^[a-f0-9]{64}$/', $call[0] ) && 60 === $call[1] && $call[2] > time(), 'The limiter must receive only a digest, fixed limit, and future expiry.' );
	} );
}

ctcb_run( 'signed_provider_readiness_before_lookup', function () {
	$h = ctcb_harness();
	$h['provider']->ready = false;
	$result = $h['controller']->can_read( ctcb_request() );
	ctcb_error( $result, 'store_unavailable', 503, 'An unready signed-in provider must return a generic outage.' );
	ctcb_assert( array( 'limit', 'provider_ready' ) === $GLOBALS['ctcb_events'], 'Readiness failure must suppress the owner lookup.' );
} );

foreach ( array( 404 => 'not_found', 503 => 'view_unavailable' ) as $status => $suffix ) {
	ctcb_run( 'signed_provider_' . $status, function () use ( $status, $suffix ) {
		$h = ctcb_harness();
		$h['provider']->owned_result = new WP_Error( 'provider_secret_code', 'Supplier A booking locator SECRET', array( 'status' => $status ) );
		$result = $h['controller']->can_read( ctcb_request() );
		ctcb_error( $result, $suffix, $status, 'Signed provider ' . $status . ' must map to the public error vocabulary.' );
		ctcb_assert( false === strpos( $result->get_error_message(), 'SECRET' ) && false === strpos( $result->get_error_code(), 'provider_secret' ), 'Provider error details must not cross the REST bridge.' );
	} );
}

ctcb_run( 'signed_record_shape_closed', function () {
	$h = ctcb_harness();
	$h['provider']->owned_result['supplier_locator'] = 'SECRET';
	$result = $h['controller']->can_read( ctcb_request() );
	ctcb_error( $result, 'view_unavailable', 503, 'Unknown provider-record fields must fail closed.' );
} );

ctcb_run( 'signed_owner_mismatch', function () {
	$h = ctcb_harness();
	$h['provider']->owned_result['owner_user_id'] = 99;
	$result = $h['controller']->can_read( ctcb_request() );
	ctcb_error( $result, 'view_unavailable', 503, 'A provider record for another owner must not serialize.' );
} );

ctcb_run( 'signed_owner_scope_mismatch', function () {
	$h = ctcb_harness();
	$h['provider']->owned_result['owner_scope_digest'] = hash( 'sha256', 'wrong-owner-scope' );
	$result = $h['controller']->can_read( ctcb_request() );
	ctcb_error( $result, 'view_unavailable', 503, 'The controller must independently verify the keyed owner scope.' );
} );

ctcb_run( 'signed_scopes_are_closed_and_view_is_mandatory', function () {
	$h = ctcb_harness();
	$GLOBALS['ctcb_scope_filter'] = array( 'incident_report', 'payment.authorize', 'incident_report' );
	$response = ctcb_authorize_and_read( $h, ctcb_request() );
	ctcb_assert( true === $response->data['audience']['view_allowed'] && true === $response->data['audience']['report_issue_allowed'] && false === $response->data['audience']['follow_up_allowed'], 'Signed-in capabilities must drop unknown/high-impact/duplicate scopes, restore mandatory viewing, and derive only from the closed low-risk intersection.' );
} );

ctcb_run( 'scoped_missing_cookie_has_no_signed_in_fallback', function () {
	$h = ctcb_harness( 'scoped-session' );
	unset( $_COOKIE[ Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE ] );
	$GLOBALS['ctcb_user_id'] = 17;
	$result = $h['controller']->can_read( ctcb_request( 'scoped-session', array( 'headers' => array( 'X-WP-Nonce' => 'ctcb-valid-rest-nonce' ) ) ) );
	ctcb_error( $result, 'session_missing', 404, 'Scoped mode must not fall back to the signed-in account.' );
	ctcb_assert( array() === $GLOBALS['ctcb_events'], 'A missing scoped cookie must fail before rate/readiness work.' );
} );

ctcb_run( 'scoped_cookie_format_closed', function () {
	$h = ctcb_harness( 'scoped-session' );
	$_COOKIE[ Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE ] = rawurlencode( 'bad cookie/with?punctuation' );
	$result = $h['controller']->can_read( ctcb_request( 'scoped-session' ) );
	ctcb_error( $result, 'session_missing', 404, 'Malformed scoped cookies must receive the generic missing response.' );
	ctcb_assert( array() === $GLOBALS['ctcb_events'], 'Malformed cookies must not touch the limiter or stores.' );
} );

ctcb_run( 'scoped_rate_limit_precedes_readiness', function () {
	$h = ctcb_harness( 'scoped-session' );
	$h['provider']->limit_mode = 'deny';
	$result = $h['controller']->can_read( ctcb_request( 'scoped-session' ) );
	ctcb_error( $result, 'rate_limited', 429, 'Scoped reads must use the same bounded rate gate.' );
	ctcb_assert( array( 'limit' ) === $GLOBALS['ctcb_events'], 'Scoped rate denial must suppress both readiness checks.' );
	ctcb_assert( 1 === preg_match( '/^[a-f0-9]{64}$/', $h['provider']->limit_calls[0][0] ) && false === strpos( $h['provider']->limit_calls[0][0], $h['cookie'] ), 'The scoped limiter key must not contain the bearer cookie.' );
} );

ctcb_run( 'scoped_provider_readiness_short_circuits_capability', function () {
	$h = ctcb_harness( 'scoped-session' );
	$h['provider']->ready = false;
	$result = $h['controller']->can_read( ctcb_request( 'scoped-session' ) );
	ctcb_error( $result, 'store_unavailable', 503, 'An unready projection provider must fail generically.' );
	ctcb_assert( array( 'limit', 'provider_ready' ) === $GLOBALS['ctcb_events'], 'Provider readiness must short-circuit capability and record reads.' );
} );

ctcb_run( 'scoped_capability_readiness_precedes_session', function () {
	$h = ctcb_harness( 'scoped-session' );
	$h['capability']->ready = false;
	$result = $h['controller']->can_read( ctcb_request( 'scoped-session' ) );
	ctcb_error( $result, 'store_unavailable', 503, 'An unready capability store must fail generically.' );
	ctcb_assert( array( 'limit', 'provider_ready', 'capability_ready' ) === $GLOBALS['ctcb_events'], 'Capability readiness must precede session or projection lookup.' );
} );

ctcb_run( 'scoped_session_error_is_generic_404', function () {
	$h = ctcb_harness( 'scoped-session' );
	$h['capability']->session_result = new WP_Error( 'capability_revoked_secret', 'grant generation 7 revoked', array( 'status' => 503 ) );
	$result = $h['controller']->can_read( ctcb_request( 'scoped-session' ) );
	ctcb_error( $result, 'session_missing', 404, 'Revoked, expired, corrupt, and absent sessions must be indistinguishable.' );
	ctcb_assert( false === strpos( $result->get_error_message(), 'generation' ), 'Capability internals must not leak through a missing-link response.' );
} );

ctcb_run( 'scoped_case_bound_session_rejected', function () {
	$h = ctcb_harness( 'scoped-session' );
	$h['capability']->session_result['case_ref'] = ctcb_ref( 'case', 'specific-case' );
	$result = $h['controller']->can_read( ctcb_request( 'scoped-session' ) );
	ctcb_error( $result, 'session_missing', 404, 'A case-bound grant must not expand into the whole-trip endpoint.' );
	ctcb_assert( false === in_array( 'provider_bound', $GLOBALS['ctcb_events'], true ), 'Case rejection must happen before a whole-trip provider lookup.' );
} );

ctcb_run( 'scoped_null_account_rejected', function () {
	$h = ctcb_harness( 'scoped-session' );
	$h['capability']->session_result['account_ref'] = null;
	$result = $h['controller']->can_read( ctcb_request( 'scoped-session' ) );
	ctcb_error( $result, 'session_missing', 404, 'An unbound trip-only session must not open the cockpit.' );
	ctcb_assert( false === in_array( 'provider_bound', $GLOBALS['ctcb_events'], true ), 'Null-account rejection must precede projection lookup.' );
} );

foreach ( array( 404, 401, 403 ) as $status ) {
	ctcb_run( 'scoped_bound_missing_' . $status, function () use ( $status ) {
		$h = ctcb_harness( 'scoped-session' );
		$h['provider']->bound_result = new WP_Error( 'provider_binding_secret', 'trip exists for somebody else', array( 'status' => $status ) );
		$result = $h['controller']->can_read( ctcb_request( 'scoped-session' ) );
		ctcb_error( $result, 'session_missing', 404, 'Non-outage bound lookup failures must be indistinguishable (' . $status . ').' );
		ctcb_assert( false === strpos( $result->get_error_message(), 'somebody else' ), 'Bound-record existence must not leak.' );
	} );
}

ctcb_run( 'scoped_bound_provider_outage_is_503', function () {
	$h = ctcb_harness( 'scoped-session' );
	$h['provider']->bound_result = new WP_Error( 'database_secret', 'mysql node 4', array( 'status' => 503 ) );
	$result = $h['controller']->can_read( ctcb_request( 'scoped-session' ) );
	ctcb_error( $result, 'view_unavailable', 503, 'A genuine bound-provider outage must remain retryable without leaking detail.' );
	ctcb_assert( false === strpos( $result->get_error_message(), 'mysql' ), 'Infrastructure details must not cross the bridge.' );
} );

foreach ( array( 'case', 'trip', 'account' ) as $axis ) {
	ctcb_run( 'scoped_record_' . $axis . '_mismatch', function () use ( $axis ) {
		$h = ctcb_harness( 'scoped-session' );
		if ( 'case' === $axis ) {
			$h['provider']->bound_result['case_ref'] = ctcb_ref( 'case', 'foreign-case' );
		} elseif ( 'trip' === $axis ) {
			$h['provider']->bound_result['trip_ref'] = ctcb_ref( 'trip', 'foreign-trip' );
		} else {
			$h['provider']->bound_result['account_ref'] = Tra_Vel_Traveler_Principal::account_ref( 99 );
		}
		$result = $h['controller']->can_read( ctcb_request( 'scoped-session' ) );
		ctcb_error( $result, 'session_missing', 404, 'Scoped provider ' . $axis . ' mismatch must fail as a missing link.' );
		ctcb_assert( false === in_array( 'capability_resolve', $GLOBALS['ctcb_events'], true ), 'A mismatched provider record must never reach capability resolution.' );
	} );
}

ctcb_run( 'scoped_resolution_failure_is_generic', function () {
	$h = ctcb_harness( 'scoped-session' );
	$h['capability']->resolved_result = new WP_Error( 'scope_secret', 'disclosure class rejected', array( 'status' => 503 ) );
	$result = $h['controller']->can_read( ctcb_request( 'scoped-session' ) );
	ctcb_error( $result, 'session_missing', 404, 'Capability resolution failure must not reveal which binding or scope failed.' );
} );

ctcb_run( 'scoped_scope_intersection', function () {
	$h = ctcb_harness( 'scoped-session', array( 'trip_view_redacted', 'incident_report', 'payment.authorize', 'incident_report' ) );
	$response = ctcb_authorize_and_read( $h, ctcb_request( 'scoped-session' ) );
	ctcb_assert( true === $response->data['audience']['view_allowed'] && true === $response->data['audience']['report_issue_allowed'] && false === $response->data['audience']['follow_up_allowed'], 'Scoped capability booleans must derive from the exact low-risk intersection after duplicate and high-impact values are removed.' );
} );

ctcb_run( 'scoped_trip_view_scope_mandatory', function () {
	$h = ctcb_harness( 'scoped-session', array( 'incident_report', 'case_progress_view' ) );
	$result = $h['controller']->can_read( ctcb_request( 'scoped-session' ) );
	ctcb_error( $result, 'session_missing', 404, 'A resolved session without trip_view_redacted must not receive a view.' );
} );

ctcb_run( 'scoped_expiry_is_bounded_and_live', function () {
	$h = ctcb_harness( 'scoped-session' );
	$h['capability']->resolved_result['expires_at'] = ctcb_iso( time() - 1 );
	$result = $h['controller']->can_read( ctcb_request( 'scoped-session' ) );
	ctcb_error( $result, 'session_missing', 404, 'An already expired resolved session must not authorize a callback.' );
} );

ctcb_run( 'callback_requires_same_request_object', function () {
	$h = ctcb_harness();
	$authorized = ctcb_request();
	ctcb_assert( true === $h['controller']->can_read( $authorized ), 'Permission pass must succeed before callback substitution test.' );
	$result = $h['controller']->get_current( ctcb_request() );
	ctcb_error( $result, 'not_authorized', 403, 'An equivalent but different request object must not consume authorization.' );
	$response = $h['controller']->get_current( $authorized );
	ctcb_assert( $response instanceof WP_REST_Response && 200 === $response->status, 'A rejected foreign callback must not consume the original request authorization.' );
} );

ctcb_run( 'callback_header_fingerprint', function () {
	$h = ctcb_harness();
	$request = ctcb_request();
	ctcb_assert( true === $h['controller']->can_read( $request ), 'Permission pass must succeed before header mutation.' );
	$request->set_header( 'X-Tra-Vel-Cockpit-Read', '0' );
	$result = $h['controller']->get_current( $request );
	ctcb_error( $result, 'not_authorized', 403, 'Changing a fingerprinted header after permission must invalidate the callback.' );
} );

ctcb_run( 'callback_closed_body_fingerprint', function () {
	$h = ctcb_harness();
	$request = ctcb_request();
	ctcb_assert( true === $h['controller']->can_read( $request ), 'Permission pass must succeed before body mutation.' );
	$request->set_body( '{"trip_ref":"injected-after-permission"}' );
	$result = $h['controller']->get_current( $request );
	ctcb_error( $result, 'not_authorized', 403, 'Adding a body after the closed permission check must invalidate the callback.' );
} );

ctcb_run( 'callback_closed_query_fingerprint', function () {
	$h = ctcb_harness();
	$request = ctcb_request();
	ctcb_assert( true === $h['controller']->can_read( $request ), 'Permission pass must succeed before query mutation.' );
	$request->set_query_params( array( 'trip_ref' => ctcb_ref( 'trip', 'injected-after-permission' ) ) );
	$result = $h['controller']->get_current( $request );
	ctcb_error( $result, 'not_authorized', 403, 'Adding a query after the closed permission check must invalidate the callback.' );
} );

ctcb_run( 'callback_origin_fingerprint', function () {
	$h = ctcb_harness();
	$request = ctcb_request();
	ctcb_assert( true === $h['controller']->can_read( $request ), 'Permission pass must succeed before Origin mutation.' );
	$request->set_header( 'Origin', 'https://evil.example' );
	$result = $h['controller']->get_current( $request );
	ctcb_error( $result, 'not_authorized', 403, 'Changing same-origin evidence after permission must invalidate the callback.' );
} );

ctcb_run( 'callback_cookie_fingerprint', function () {
	$h = ctcb_harness( 'scoped-session' );
	$request = ctcb_request( 'scoped-session' );
	ctcb_assert( true === $h['controller']->can_read( $request ), 'Scoped permission pass must succeed before cookie mutation.' );
	$_COOKIE[ Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE ] = rawurlencode( 'different_session_value_1234567890ABCDE' );
	$result = $h['controller']->get_current( $request );
	ctcb_error( $result, 'not_authorized', 403, 'Changing the capability cookie after permission must invalidate the callback.' );
} );

ctcb_run( 'callback_principal_fingerprint', function () {
	$h = ctcb_harness();
	$request = ctcb_request();
	ctcb_assert( true === $h['controller']->can_read( $request ), 'Signed permission pass must succeed before principal mutation.' );
	$GLOBALS['ctcb_user_id'] = 18;
	$result = $h['controller']->get_current( $request );
	ctcb_error( $result, 'not_authorized', 403, 'Changing the principal after permission must invalidate the callback.' );
} );

ctcb_run( 'callback_authorization_is_one_time', function () {
	$h = ctcb_harness();
	$request = ctcb_request();
	$response = ctcb_authorize_and_read( $h, $request );
	ctcb_assert( 200 === $response->status, 'First authorized callback must succeed.' );
	$result = $h['controller']->get_current( $request );
	ctcb_error( $result, 'not_authorized', 403, 'A callback authorization must be consumed exactly once.' );
	ctcb_assert( 1 === count( $h['provider']->owned_calls ), 'The callback must use its cached projection without a second provider read.' );
} );

ctcb_run( 'failed_reauthorization_clears_prior_grant', function () {
	$h = ctcb_harness();
	$request = ctcb_request();
	ctcb_assert( true === $h['controller']->can_read( $request ), 'Initial permission pass must succeed.' );
	$request->set_header( 'X-Tra-Vel-Cockpit-Read', '0' );
	ctcb_error( $h['controller']->can_read( $request ), 'request_invalid', 400, 'A later invalid permission attempt must fail.' );
	$request->set_header( 'X-Tra-Vel-Cockpit-Read', '1' );
	ctcb_error( $h['controller']->get_current( $request ), 'not_authorized', 403, 'A failed reauthorization must erase the old cached grant.' );
} );

ctcb_run( 'signed_redaction_contract', function () {
	$h = ctcb_harness();
	$response = ctcb_authorize_and_read( $h, ctcb_request() );
	$view = $response->data;
	$expected = array(
		'contract_version', 'environment', 'audience', 'trip_headline', 'current', 'next_safe_action', 'protections', 'changes',
		'attention_items', 'service_timeline', 'customer_money', 'case_progress_disclosure', 'trip_care_cases', 'trip_care_receipts',
		'traveler_readiness_disclosure', 'traveler_readiness', 'loyalty', 'offline_pack', 'freshness', 'authority', 'data_boundary',
	);
	ctcb_assert( $expected === array_keys( $view ) && 21 === count( $view ), 'The REST body must be the exact closed 21-field customer-view contract.' );
	$json = wp_json_encode( $view );
	ctcb_assert( false === strpos( $json, $h['record']['trip_ref'] ) && false === strpos( $json, $h['record']['owner_scope_digest'] ) && false === strpos( $json, 'tv_service_' ) && false === strpos( $json, 'tv_order_' ) && false === strpos( $json, 'tv_traveler_' ), 'Private trip, owner, service, order, and traveler references must not serialize.' );
	ctcb_assert( 'signed_in_redacted' === $view['customer_money']['disclosure'] && 1 === count( $view['customer_money']['payments'] ), 'Signed-in customer money must be useful but redacted.' );
	ctcb_assert( 'signed_in_redacted' === $view['traveler_readiness_disclosure'] && 1 === count( $view['traveler_readiness'] ), 'Signed-in traveler readiness must use opaque customer slots.' );
	ctcb_assert( false === $view['authority']['change_started'] && false === $view['authority']['payment_started'] && false === $view['audience']['mutation_authorized'], 'The read bridge must never claim mutation authority or execution.' );
} );

ctcb_run( 'scoped_redaction_contract', function () {
	$h = ctcb_harness( 'scoped-session' );
	$response = ctcb_authorize_and_read( $h, ctcb_request( 'scoped-session' ) );
	$view = $response->data;
	ctcb_assert( 'withheld_scoped_session' === $view['customer_money']['disclosure'] && array() === $view['customer_money']['payments'] && array() === $view['customer_money']['refunds'], 'Forwardable scoped links must withhold purchase existence and state.' );
	ctcb_assert( 'withheld_scoped_session' === $view['traveler_readiness_disclosure'] && array() === $view['traveler_readiness'], 'Forwardable scoped links must withhold traveler facts.' );
	ctcb_assert( 'withheld_scoped_session' === $view['loyalty']['disclosure'] && 'withheld' === $view['loyalty']['status'], 'Forwardable scoped links must withhold loyalty identity and value state.' );
	ctcb_assert( false === strpos( wp_json_encode( $view ), $h['cookie'] ), 'The bearer cookie must never enter the response body.' );
} );

ctcb_run( 'privacy_headers_are_exact', function () {
	$h = ctcb_harness();
	$response = ctcb_authorize_and_read( $h, ctcb_request() );
	$expected = array(
		'Cache-Control' => 'private, no-store, max-age=0',
		'Pragma' => 'no-cache',
		'X-Robots-Tag' => 'noindex, nofollow, noarchive',
		'Referrer-Policy' => 'no-referrer',
		'X-Content-Type-Options' => 'nosniff',
		'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'",
		'Vary' => 'Origin, Cookie, X-WP-Nonce',
	);
	$headers = $response->headers;
	$view_expires_at = isset( $headers['X-Tra-Vel-Cockpit-View-Expires'] ) ? $headers['X-Tra-Vel-Cockpit-View-Expires'] : '';
	unset( $headers['X-Tra-Vel-Cockpit-View-Expires'] );
	ctcb_assert( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $view_expires_at ) && strtotime( $view_expires_at ) > time() && strtotime( $view_expires_at ) <= time() + Tra_Vel_Customer_Trip_Cockpit_Controller::VIEW_CONTEXT_TTL_SECONDS, 'Successful views must expose only a bounded, parseable reauthorization deadline.' );
	ctcb_assert( $expected === $headers, 'Successful customer responses must carry the exact private/no-store/noindex header set.' );
} );

ctcb_run( 'permission_errors_receive_privacy_headers', function () {
	$h = ctcb_harness();
	$h['controller']->register_routes();
	$error = new WP_Error( 'tra_vel_customer_trip_cockpit_session_missing', 'The secure trip link is unavailable.', array( 'status' => 404 ) );
	$response = $h['controller']->secure_route_response( $error, null, ctcb_request() );
	ctcb_assert( $response instanceof WP_REST_Response && 404 === $response->status, 'The post-dispatch boundary must convert route errors without losing status.' );
	ctcb_assert( 'private, no-store, max-age=0' === $response->headers['Cache-Control'] && 'noindex, nofollow, noarchive' === $response->headers['X-Robots-Tag'] && 'Origin, Cookie, X-WP-Nonce' === $response->headers['Vary'], 'Permission errors must receive the same private/no-store/noindex boundary as successes.' );
	$foreign = new WP_REST_Response( array( 'public' => true ), 200 );
	$foreign_request = new WP_REST_Request( 'GET', array(), array(), '', '/wp/v2/posts' );
	ctcb_assert( $foreign === $h['controller']->secure_route_response( $foreign, null, $foreign_request ) && array() === $foreign->headers, 'The privacy filter must not mutate responses for other REST routes.' );
} );

ctcb_assert( $ctcb_scenarios >= 45, 'The bridge runtime must retain at least 45 independent adversarial scenarios.' );

echo 'Customer Trip Cockpit REST bridge runtime passed (' . $ctcb_assertions . ' assertions; ' . $ctcb_scenarios . ' adversarial scenarios).' . PHP_EOL;
