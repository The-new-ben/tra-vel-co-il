<?php
/**
 * Deterministic controller harness for the first private agent slice.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_AGENT_PATH', dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core' );
define( 'TRA_VEL_AGENT_VERSION', 'test' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['tv_agent_current_user'] = 0;
$GLOBALS['tv_agent_transients']   = array();
$GLOBALS['tv_agent_uuid_counter'] = 0;
$GLOBALS['tv_agent_filters']      = array();

class WP_REST_Controller {
	protected $namespace;
	protected $rest_base;
}

class WP_REST_Server {
	const READABLE  = 'GET';
	const CREATABLE = 'POST';
	const DELETABLE = 'DELETE';
}

class WP_Error {
	private $code;
	private $data;
	public function __construct( $code, $message = '', $data = null ) {
		unset( $message );
		$this->code = $code;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}

class WP_REST_Response {
	public $data;
	public $status;
	public $headers = array();
	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
	public function header( $name, $value ) { $this->headers[ $name ] = $value; }
}

class WP_REST_Request {
	private $params;
	private $headers;
	public function __construct( $params = array(), $headers = array() ) {
		$this->params  = $params;
		$this->headers = array_change_key_case( $headers, CASE_LOWER );
	}
	public function get_param( $key ) { return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null; }
	public function get_header( $key ) {
		$key = strtolower( (string) $key );
		return array_key_exists( $key, $this->headers ) ? $this->headers[ $key ] : '';
	}
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function rest_sanitize_boolean( $value ) { return filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function get_current_user_id() { return (int) $GLOBALS['tv_agent_current_user']; }
function current_user_can( $capability ) {
	if ( 'read' === $capability ) return get_current_user_id() > 0;
	return 'manage_options' === $capability && 1 === get_current_user_id();
}
function wp_generate_uuid4() {
	$GLOBALS['tv_agent_uuid_counter']++;
	return sprintf( '00000000-0000-4000-8000-%012d', $GLOBALS['tv_agent_uuid_counter'] );
}
function wp_list_pluck( $list, $field ) {
	return array_map(
		static function ( $item ) use ( $field ) {
			return is_array( $item ) && array_key_exists( $field, $item ) ? $item[ $field ] : null;
		},
		$list
	);
}
function wp_salt( $scheme = 'auth' ) { return 'test-salt-' . $scheme; }
function apply_filters( $hook, $value ) { return array_key_exists( $hook, $GLOBALS['tv_agent_filters'] ) ? $GLOBALS['tv_agent_filters'][ $hook ] : $value; }
function get_transient( $key ) { return array_key_exists( $key, $GLOBALS['tv_agent_transients'] ) ? $GLOBALS['tv_agent_transients'][ $key ] : false; }
function set_transient( $key, $value, $ttl ) { unset( $ttl ); $GLOBALS['tv_agent_transients'][ $key ] = $value; return true; }
function is_ssl() { return true; }
function wp_get_environment_type() { return 'local'; }
function register_rest_route() {}
function rest_ensure_response( $value ) { return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value ); }

require TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-policy.php';
require TRA_VEL_AGENT_PATH . '/includes/interface-tra-vel-agent-provider.php';
require TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-controller.php';

class Tra_Vel_Test_Agent_Provider implements Tra_Vel_Agent_Provider {
	public $result;
	public $revision_result;
	public $calls = 0;
	public $revision_calls = 0;
	public function __construct( $result, $revision_result = null ) {
		$this->result          = $result;
		$this->revision_result = $revision_result ? $revision_result : $result;
	}
	public function interpret( $prompt, $mode, $locale ) {
		unset( $prompt, $mode, $locale );
		$this->calls++;
		return $this->result;
	}
	public function revise( $previous_request, $message, $mode, $locale ) {
		unset( $previous_request, $message, $mode, $locale );
		$this->calls++;
		$this->revision_calls++;
		return $this->revision_result;
	}
	public function health() { return array( 'configured' => true, 'model' => 'deterministic-fixture', 'endpoint' => 'none', 'live_calls' => false ); }
}

class Tra_Vel_Test_Agent_Store {
	public $runs = array();
	public $events = array();
	public $approvals = array();
	public $tokens = array();
	public $fingerprints = array();
	public $limit_counts = array();
	public $leases = array();
	public $deny_leases = false;
	public $side_effect_count = 0;
	private $next_id = 1;

	public function consume_limit( $key, $limit, $expires_at ) {
		unset( $expires_at );
		$count = isset( $this->limit_counts[ $key ] ) ? $this->limit_counts[ $key ] : 0;
		if ( $count >= (int) $limit ) return false;
		$this->limit_counts[ $key ] = $count + 1;
		return true;
	}

	public function acquire_lease( $namespace, $slots, $ttl ) {
		unset( $ttl );
		if ( $this->deny_leases || count( $this->leases ) >= (int) $slots ) return false;
		$lease = array( 'option' => $namespace . '-' . ( count( $this->leases ) + 1 ), 'value' => wp_generate_uuid4(), 'slot' => count( $this->leases ) + 1 );
		$this->leases[ $lease['value'] ] = $lease;
		return $lease;
	}

	public function release_lease( $lease ) {
		if ( is_array( $lease ) && isset( $lease['value'] ) ) unset( $this->leases[ $lease['value'] ] );
	}

	public function find_recent_fingerprint( $fingerprint ) { return isset( $this->fingerprints[ $fingerprint ] ); }

	public function create_run( $input ) {
		$id       = $this->next_id++;
		$run_uuid = wp_generate_uuid4();
		$token    = str_repeat( chr( 96 + min( 26, $id ) ), 40 );
		$run      = array(
			'id'                  => $id,
			'run_uuid'            => $run_uuid,
			'owner_user_id'       => isset( $input['owner_user_id'] ) ? (int) $input['owner_user_id'] : 0,
			'request_fingerprint' => (string) $input['request_fingerprint'],
			'status'              => 'created',
			'mode'                => (string) $input['mode'],
			'locale'              => (string) $input['locale'],
			'input_kind'          => (string) $input['input_kind'],
			'input_text'          => '',
			'trip_request'        => array(),
			'proposals'           => array(),
			'provider_state'      => array(),
			'created_at'          => '2030-04-01 10:00:00',
			'updated_at'          => '2030-04-01 10:00:00',
			'expires_at'          => '2030-04-02 10:00:00',
		);
		$this->runs[ $id ]                     = $run;
		$this->events[ $id ]                   = array();
		$this->tokens[ $run_uuid ]             = $token;
		$this->fingerprints[ $input['request_fingerprint'] ] = true;
		return array( 'id' => $id, 'run_uuid' => $run_uuid, 'run_token' => $token );
	}

	public function update_run( $run_id, $fields ) {
		if ( ! isset( $this->runs[ $run_id ] ) ) return false;
		$this->runs[ $run_id ] = array_merge( $this->runs[ $run_id ], $fields, array( 'updated_at' => '2030-04-01 10:01:00' ) );
		return true;
	}

	public function append_event( $run_id, $event ) {
		$sequence = count( $this->events[ $run_id ] ) + 1;
		$this->events[ $run_id ][] = array(
			'contract_version' => '1.0.0',
			'event_id'        => wp_generate_uuid4(),
			'sequence'        => $sequence,
			'occurred_at'     => '2030-04-01T10:00:00Z',
			'type'            => $event['type'],
			'phase'           => $event['phase'],
			'status'          => $event['status'],
			'source'          => $event['source'],
			'visible'         => isset( $event['visible'] ) ? (bool) $event['visible'] : true,
			'message'         => $event['message'],
			'data'            => isset( $event['data'] ) ? $event['data'] : array(),
		);
		return $sequence;
	}

	public function get_run_by_uuid( $run_uuid ) {
		foreach ( $this->runs as $run ) if ( $run_uuid === $run['run_uuid'] ) return $run;
		return null;
	}

	public function get_events( $run_id, $after = 0 ) {
		return array_values(
			array_filter(
				isset( $this->events[ $run_id ] ) ? $this->events[ $run_id ] : array(),
				static function ( $event ) use ( $after ) { return $event['sequence'] > (int) $after; }
			)
		);
	}

	public function can_access( $run, $token, $user_id ) {
		if ( ! is_array( $run ) ) return false;
		if ( $user_id > 0 && (int) $run['owner_user_id'] === (int) $user_id ) return true;
		return is_string( $token ) && isset( $this->tokens[ $run['run_uuid'] ] ) && hash_equals( $this->tokens[ $run['run_uuid'] ], $token );
	}

	public function seed_approval( $run_id ) {
		$uuid   = wp_generate_uuid4();
		$digest = str_repeat( 'd', 64 );
		$this->approvals[ $run_id ][ $uuid ] = array(
			'id'               => 1,
			'approval_uuid'    => $uuid,
			'run_id'           => $run_id,
			'approval_version' => 1,
			'action_type'      => 'send_supplier_request',
			'scope_digest'     => $digest,
			'status'           => 'pending',
			'summary'          => array( 'supplier' => 'Tra-Vel concierge', 'amount' => 0, 'currency' => 'ILS' ),
			'action_snapshot'  => array( 'provider_id' => 'tra-vel-concierge', 'data_fields' => array( 'trip_request' ) ),
			'expires_at'       => '2030-04-01 11:00:00',
		);
		return array( 'approval_uuid' => $uuid, 'scope_digest' => $digest );
	}

	public function get_approval( $run_id, $approval_uuid ) {
		return isset( $this->approvals[ $run_id ][ $approval_uuid ] ) ? $this->approvals[ $run_id ][ $approval_uuid ] : null;
	}

	public function decide_approval( $approval, $decision, $decision_key, $user_id ) {
		$approval['status']       = 'approve' === $decision ? 'approved' : 'rejected';
		$approval['decision_key'] = $decision_key;
		$approval['decided_by']   = (int) $user_id;
		$this->approvals[ $approval['run_id'] ][ $approval['approval_uuid'] ] = $approval;
		return $approval;
	}
}

function tv_agent_controller_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Tra-Vel agent controller runtime validation failed: {$message}\n" );
		exit( 1 );
	}
}

function tv_agent_controller_base_request() {
	return array(
		'summary'            => 'Surprise honeymoon for two under 1,000 USD',
		'language'           => 'mixed',
		'origin_text'        => 'Tel Aviv (TLV)',
		'destination_mode'   => 'anywhere',
		'destinations'       => array(),
		'date_text'          => null,
		'date_flexibility'   => 'flexible',
		'travelers'          => array( 'adults' => 2, 'children' => 0, 'child_ages' => array(), 'rooms' => 1 ),
		'budget'             => array( 'amount' => 1000, 'currency' => 'USD', 'flexibility' => 'hard' ),
		'vibes'              => array( 'exotic', 'romantic' ),
		'hard_constraints'   => array( 'whole trip under 1,000 USD' ),
		'preferences'        => array( 'good value' ),
		'search_scope'       => array( 'flights', 'accommodation', 'transfers', 'activities' ),
		'material_questions' => array(),
		'assumptions'        => array( 'Dates are flexible' ),
		'confidence'         => 0.94,
	);
}

function tv_agent_controller_provider_result( $trip_request ) {
	return array(
		'trip_request' => $trip_request,
		'provider'     => array(
			'response_id' => 'resp_deterministic',
			'model'       => 'deterministic-fixture',
			'status'      => 'completed',
			'usage'       => array( 'input_tokens' => 10, 'output_tokens' => 20, 'total_tokens' => 30 ),
		),
	);
}

function tv_agent_controller_request( $overrides = array(), $headers = array() ) {
	$params = array_merge(
		array(
			'prompt'               => 'Surprise honeymoon for two under 1,000 USD from TLV',
			'mode'                 => 'surprise',
			'locale'               => 'mixed',
			'input_kind'           => 'typed',
			'transcript_confirmed' => true,
			'client_request_id'    => 'client-request-ready-0001',
		),
		$overrides
	);
	return new WP_REST_Request( $params, $headers );
}

function tv_agent_controller_reset( $address ) {
	$GLOBALS['tv_agent_current_user'] = 0;
	$GLOBALS['tv_agent_transients']   = array();
	$GLOBALS['tv_agent_filters']      = array();
	$_SERVER['REMOTE_ADDR']           = $address;
	$_COOKIE                          = array();
}

function tv_agent_assert_private_response( $response, $label ) {
	tv_agent_controller_assert( $response instanceof WP_REST_Response, "{$label} did not return a REST response" );
	tv_agent_controller_assert( 'private, no-store, max-age=0' === $response->headers['Cache-Control'], "{$label} is cacheable" );
	tv_agent_controller_assert( 'noindex, nofollow, noarchive' === $response->headers['X-Robots-Tag'], "{$label} is indexable" );
	tv_agent_controller_assert( 'no-cache' === $response->headers['Pragma'], "{$label} lacks legacy no-cache policy" );
}

function tv_agent_assert_event_order( $events, $types, $label ) {
	tv_agent_controller_assert( $types === array_column( $events, 'type' ), "{$label} event order changed" );
	foreach ( $events as $index => $event ) {
		tv_agent_controller_assert( $index + 1 === $event['sequence'], "{$label} event sequence is not contiguous" );
	}
}

function tv_agent_assert_no_transaction_events( $events, $label ) {
	foreach ( $events as $event ) {
		tv_agent_controller_assert( ! preg_match( '/(?:supplier\.search\.started|proposal\.ready|booking|purchase|reservation|order)/', $event['type'] ), "{$label} emitted unsupported transaction event {$event['type']}" );
	}
}

// Ready request: interpretation succeeds, but suppliers and transactions stay off.
tv_agent_controller_reset( '192.0.2.10' );
$ready_store      = new Tra_Vel_Test_Agent_Store();
$ready_provider   = new Tra_Vel_Test_Agent_Provider( tv_agent_controller_provider_result( tv_agent_controller_base_request() ) );
$ready_controller = new Tra_Vel_Agent_Controller( $ready_store, $ready_provider );
$ready_request    = tv_agent_controller_request();
$ready_response   = $ready_controller->create_run( $ready_request );
tv_agent_assert_private_response( $ready_response, 'ready create' );
tv_agent_controller_assert( 201 === $ready_response->status, 'ready create status changed' );
tv_agent_controller_assert( 'request_ready' === $ready_response->data['status'], 'complete request did not reach request_ready' );
tv_agent_controller_assert( 'ready_for_search' === $ready_response->data['trip_request']['readiness']['status'], 'complete request was marked unclear' );
tv_agent_controller_assert( 1 === $ready_provider->calls, 'ready request did not call the interpreter exactly once' );
tv_agent_controller_assert( array() === $ready_store->leases, 'ready request did not release its provider concurrency lease' );
tv_agent_controller_assert( array() === $ready_response->data['proposals'], 'ready interpretation invented proposals' );
tv_agent_controller_assert( array() === $ready_response->data['approvals'], 'ready interpretation invented approvals' );
tv_agent_controller_assert( ! array_key_exists( 'run_token', $ready_response->data ), 'create response exposed the private owner token to JavaScript' );
tv_agent_controller_assert( isset( $ready_response->headers['Set-Cookie'] ) && false !== strpos( $ready_response->headers['Set-Cookie'], '__Host-tra_vel_agent_run=' ) && false !== strpos( $ready_response->headers['Set-Cookie'], 'Secure; HttpOnly; SameSite=Lax' ), 'create response omitted the protected ownership cookie' );
tv_agent_controller_assert( '' === $ready_store->runs[1]['input_text'], 'raw natural-language intake was persisted' );

$ready_events = $ready_response->data['events'];
tv_agent_assert_event_order(
	$ready_events,
	array( 'run.created', 'request.interpretation.started', 'request.interpreted', 'request.ready', 'supplier.search.not_started' ),
	'ready run'
);
tv_agent_assert_no_transaction_events( $ready_events, 'ready run' );
$not_started = end( $ready_events );
tv_agent_controller_assert( false === $not_started['data']['provider_connected'], 'not-started event claims a connected supplier' );
tv_agent_controller_assert( false === $not_started['data']['provider_bookable'], 'not-started event claims bookability' );
tv_agent_controller_assert( 'not_connected' === $not_started['data']['data_mode'], 'not-started event lacks truthful data mode' );
tv_agent_controller_assert( 'waiting' === $not_started['status'] && 'system' === $not_started['source'], 'not-started event claims completed supplier work' );

// Private ownership: a wrong token is denied; the correct token works and is not re-exposed.
$run_id = $ready_response->data['run_id'];
$GLOBALS['tv_agent_current_user'] = 99;
$_COOKIE['__Host-tra_vel_agent_run'] = $run_id . '.wrong-owner-token-that-is-long-enough';
$denied = $ready_controller->can_access_run( new WP_REST_Request( array( 'run_id' => $run_id ) ) );
tv_agent_controller_assert( is_wp_error( $denied ) && 'tra_vel_agent_run_forbidden' === $denied->get_error_code(), 'wrong owner token was accepted' );
$GLOBALS['tv_agent_current_user'] = 0;
$_COOKIE['__Host-tra_vel_agent_run'] = $run_id . '.' . $ready_store->tokens[ $run_id ];
$authorized_request = new WP_REST_Request( array( 'run_id' => $run_id ) );
tv_agent_controller_assert( true === $ready_controller->can_access_run( $authorized_request ), 'correct owner token was rejected' );
$private_get = $ready_controller->get_run( $authorized_request );
tv_agent_assert_private_response( $private_get, 'private get' );
tv_agent_controller_assert( ! array_key_exists( 'run_token', $private_get->data ), 'run token was re-exposed after creation' );

// Duplicate fingerprint: same visitor/client/mode/prompt is rejected before another provider call.
$duplicate = $ready_controller->create_run( tv_agent_controller_request() );
tv_agent_controller_assert( is_wp_error( $duplicate ) && 'tra_vel_agent_duplicate_request' === $duplicate->get_error_code(), 'duplicate request fingerprint was accepted' );
tv_agent_controller_assert( 1 === $ready_provider->calls && 1 === count( $ready_store->runs ), 'duplicate request created work or called the provider' );

// Natural-language revision: the same private run is updated in place, keeps
// its request identity, increments revision and never persists the raw answer.
$initial_request_id = $ready_response->data['trip_request']['request_id'];
$revised_fixture = tv_agent_controller_base_request();
$revised_fixture['destinations']     = array( 'Budapest' );
$revised_fixture['destination_mode'] = 'fixed';
$revised_fixture['budget']['amount'] = 1200;
$revised_fixture['summary']          = 'Budapest for two adults with a 1,200 USD budget';
$ready_provider->revision_result     = tv_agent_controller_provider_result( $revised_fixture );
$revision_request = new WP_REST_Request(
	array(
		'run_id'               => $run_id,
		'message'              => 'Choose Budapest and increase the total budget to 1,200 USD',
		'locale'               => 'en-US',
		'input_kind'           => 'typed',
		'transcript_confirmed' => true,
		'client_request_id'    => 'client-revision-ready-0001',
	)
);
$revision_response = $ready_controller->revise_run( $revision_request );
tv_agent_assert_private_response( $revision_response, 'request revision' );
tv_agent_controller_assert( 200 === $revision_response->status, 'request revision status changed' );
tv_agent_controller_assert( 'request_ready' === $revision_response->data['status'], 'complete revision did not return to request_ready' );
tv_agent_controller_assert( 2 === $revision_response->data['trip_request']['revision'], 'request revision was not incremented' );
tv_agent_controller_assert( $initial_request_id === $revision_response->data['trip_request']['request_id'], 'request revision changed the private request identity' );
tv_agent_controller_assert( array( 'Budapest' ) === $revision_response->data['trip_request']['destinations'], 'revision did not replace the destination' );
tv_agent_controller_assert( 1200 === $revision_response->data['trip_request']['budget']['amount'], 'revision did not update the budget' );
tv_agent_controller_assert( 1 === $ready_provider->revision_calls && 2 === $ready_provider->calls, 'revision did not call the provider exactly once' );
tv_agent_controller_assert( array() === $ready_store->leases, 'revision did not release both concurrency leases' );
tv_agent_controller_assert( false === strpos( wp_json_encode( array( $ready_store->runs, $ready_store->events ) ), 'increase the total budget' ), 'raw clarification text was persisted' );
tv_agent_assert_event_order(
	$revision_response->data['events'],
	array( 'run.created', 'request.interpretation.started', 'request.interpreted', 'request.ready', 'supplier.search.not_started', 'clarification.response.received', 'request.revision.started', 'request.revised', 'request.ready', 'supplier.search.not_started' ),
	'revised ready run'
);
tv_agent_assert_no_transaction_events( $revision_response->data['events'], 'revised ready run' );
$duplicate_revision = $ready_controller->revise_run( $revision_request );
tv_agent_controller_assert( is_wp_error( $duplicate_revision ) && 'tra_vel_agent_duplicate_revision' === $duplicate_revision->get_error_code(), 'duplicate revision idempotency key was accepted' );
tv_agent_controller_assert( 1 === $ready_provider->revision_calls, 'duplicate revision called the provider again' );

// Global balance guard: distributed visitor IDs cannot exceed the configured UTC-day capacity.
tv_agent_controller_reset( '192.0.2.15' );
$GLOBALS['tv_agent_filters']['tra_vel_agent_daily_request_limit'] = 2;
$budget_store      = new Tra_Vel_Test_Agent_Store();
$budget_provider   = new Tra_Vel_Test_Agent_Provider( tv_agent_controller_provider_result( tv_agent_controller_base_request() ) );
$budget_controller = new Tra_Vel_Agent_Controller( $budget_store, $budget_provider );
$budget_first      = $budget_controller->create_run( tv_agent_controller_request( array( 'prompt' => 'First bounded request from TLV for two', 'client_request_id' => 'client-request-budget-00001' ) ) );
$budget_second     = $budget_controller->create_run( tv_agent_controller_request( array( 'prompt' => 'Second bounded request from TLV for two', 'client_request_id' => 'client-request-budget-00002' ) ) );
tv_agent_controller_assert( $budget_first instanceof WP_REST_Response && $budget_second instanceof WP_REST_Response, 'daily guard rejected requests below capacity' );
$budget_blocked = $budget_controller->create_run( tv_agent_controller_request( array( 'prompt' => 'Third bounded request from TLV for two', 'client_request_id' => 'client-request-budget-00003' ) ) );
tv_agent_controller_assert( is_wp_error( $budget_blocked ) && 'tra_vel_agent_daily_capacity' === $budget_blocked->get_error_code(), 'daily global capacity did not stop additional provider spend' );
tv_agent_controller_assert( 2 === $budget_provider->calls && 2 === count( $budget_store->runs ), 'daily capacity consumed provider work after the limit' );
tv_agent_controller_assert( array() === $budget_store->leases, 'daily capacity path leaked a provider concurrency lease' );

// Concurrency guard: a saturated provider pool rejects before storing a run or calling a model.
tv_agent_controller_reset( '192.0.2.16' );
$busy_store = new Tra_Vel_Test_Agent_Store();
$busy_store->deny_leases = true;
$busy_provider = new Tra_Vel_Test_Agent_Provider( tv_agent_controller_provider_result( tv_agent_controller_base_request() ) );
$busy_controller = new Tra_Vel_Agent_Controller( $busy_store, $busy_provider );
$busy_response = $busy_controller->create_run( tv_agent_controller_request( array( 'client_request_id' => 'client-request-busy-000001' ) ) );
tv_agent_controller_assert( is_wp_error( $busy_response ) && 'tra_vel_agent_provider_busy' === $busy_response->get_error_code(), 'saturated provider concurrency did not fail closed' );
tv_agent_controller_assert( 0 === $busy_provider->calls && 0 === count( $busy_store->runs ), 'provider-busy path called the model or persisted a run' );

// Clarification request: voice, adults, child ages, and origin all block supplier search.
tv_agent_controller_reset( '192.0.2.20' );
$clarification_trip = tv_agent_controller_base_request();
$clarification_trip['origin_text'] = null;
$clarification_trip['travelers']   = array( 'adults' => 0, 'children' => 2, 'child_ages' => array( 6 ), 'rooms' => 1 );
$clarification_store      = new Tra_Vel_Test_Agent_Store();
$clarification_provider   = new Tra_Vel_Test_Agent_Provider( tv_agent_controller_provider_result( $clarification_trip ) );
$clarification_controller = new Tra_Vel_Agent_Controller( $clarification_store, $clarification_provider );
$clarification_response   = $clarification_controller->create_run(
	tv_agent_controller_request(
		array(
			'input_kind'           => 'voice',
			'transcript_confirmed' => false,
			'client_request_id'    => 'client-request-clarify-0001',
		)
	)
);
tv_agent_assert_private_response( $clarification_response, 'clarification create' );
tv_agent_controller_assert( 'needs_clarification' === $clarification_response->data['status'], 'unsafe incomplete request reached search-ready state' );
$blockers = $clarification_response->data['trip_request']['readiness']['blockers'];
sort( $blockers );
tv_agent_controller_assert( array( 'child_ages', 'confirm_transcript', 'origin', 'traveler_count' ) === $blockers, 'voice/child/adult/origin blockers are incomplete' );
tv_agent_controller_assert( array() === $clarification_response->data['proposals'] && array() === $clarification_response->data['approvals'], 'clarification path invented commercial output' );
tv_agent_assert_event_order(
	$clarification_response->data['events'],
	array( 'run.created', 'request.interpretation.started', 'request.interpreted', 'clarification.required' ),
	'clarification run'
);
tv_agent_assert_no_transaction_events( $clarification_response->data['events'], 'clarification run' );
tv_agent_controller_assert( ! in_array( 'supplier.search.not_started', array_column( $clarification_response->data['events'], 'type' ), true ), 'clarification path entered supplier phase' );

// Provider quota failure: persisted as a recoverable interpretation failure with no commercial output.
tv_agent_controller_reset( '192.0.2.30' );
$quota_error      = new WP_Error( 'tra_vel_agent_provider_rejected', 'quota', array( 'status' => 502, 'provider_code' => 'insufficient_quota' ) );
$failure_store    = new Tra_Vel_Test_Agent_Store();
$failure_provider = new Tra_Vel_Test_Agent_Provider( $quota_error );
$failure_controller = new Tra_Vel_Agent_Controller( $failure_store, $failure_provider );
$failure_response = $failure_controller->create_run( tv_agent_controller_request( array( 'client_request_id' => 'client-request-quota-00001' ) ) );
tv_agent_assert_private_response( $failure_response, 'provider failure create' );
tv_agent_controller_assert( 201 === $failure_response->status && 'provider_error' === $failure_response->data['status'], 'provider quota failure was not persisted safely' );
tv_agent_controller_assert( 'tra_vel_agent_provider_rejected' === $failure_response->data['provider']['error_code'], 'provider error code was lost' );
tv_agent_controller_assert( array() === $failure_store->leases, 'provider failure leaked its concurrency lease' );
tv_agent_controller_assert( 'insufficient_quota' === $failure_response->data['provider']['provider_code'], 'provider quota provenance was lost' );
tv_agent_controller_assert( array() === $failure_response->data['trip_request'] && array() === $failure_response->data['proposals'] && array() === $failure_response->data['approvals'], 'provider failure invented request or commercial output' );
tv_agent_assert_event_order(
	$failure_response->data['events'],
	array( 'run.created', 'request.interpretation.started', 'request.interpretation.failed' ),
	'provider failure run'
);
tv_agent_assert_no_transaction_events( $failure_response->data['events'], 'provider failure run' );
$failed_event = end( $failure_response->data['events'] );
tv_agent_controller_assert( 'failed' === $failed_event['status'] && 'tool' === $failed_event['source'], 'provider failure event is not truthful' );
tv_agent_controller_assert( true === $failed_event['data']['retryable'] && 'tra_vel_agent_provider_rejected' === $failed_event['data']['error_code'], 'provider failure event lacks normalized recovery data' );

// Approval decision records the human decision but cannot execute an external action.
tv_agent_controller_reset( '192.0.2.40' );
$GLOBALS['tv_agent_current_user'] = 17;
$approval_store = new Tra_Vel_Test_Agent_Store();
$approval_run   = $approval_store->create_run(
	array(
		'owner_user_id'       => 17,
		'request_fingerprint' => str_repeat( 'f', 64 ),
		'mode'                => 'agent',
		'locale'              => 'he-IL',
		'input_kind'          => 'typed',
		'prompt'              => 'Prepare an assisted quote',
	)
);
$approval_store->update_run( $approval_run['id'], array( 'status' => 'approval_required' ) );
$seeded = $approval_store->seed_approval( $approval_run['id'] );
$approval_controller = new Tra_Vel_Agent_Controller( $approval_store, new Tra_Vel_Test_Agent_Provider( tv_agent_controller_provider_result( tv_agent_controller_base_request() ) ) );
$approval_request = new WP_REST_Request(
	array(
		'run_id'          => $approval_run['run_uuid'],
		'approval_id'     => $seeded['approval_uuid'],
		'decision'        => 'approve',
		'expected_version'=> 1,
		'scope_digest'    => $seeded['scope_digest'],
		'idempotency_key' => 'approval-decision-key-0001',
	)
);
$GLOBALS['tv_agent_current_user'] = 18;
$approval_wrong_owner = $approval_controller->can_decide_approval( $approval_request );
tv_agent_controller_assert( is_wp_error( $approval_wrong_owner ) && 'tra_vel_agent_approval_owner_required' === $approval_wrong_owner->get_error_code(), 'a different signed-in account could approve the run action' );
$GLOBALS['tv_agent_current_user'] = 17;
tv_agent_controller_assert( true === $approval_controller->can_decide_approval( $approval_request ), 'run owner could not decide approval' );
$approval_response = $approval_controller->decide_approval( $approval_request );
tv_agent_assert_private_response( $approval_response, 'approval decision' );
tv_agent_controller_assert( 'approved' === $approval_response->data['approval']['status'], 'approval decision was not recorded' );
tv_agent_controller_assert( false === $approval_response->data['side_effect_executed'], 'approval decision falsely claimed an executed action' );
tv_agent_controller_assert( 0 === $approval_store->side_effect_count, 'approval decision executed a supplier side effect' );
$approval_events = $approval_store->get_events( $approval_run['id'] );
tv_agent_assert_event_order( $approval_events, array( 'approval.decided' ), 'approval decision' );
tv_agent_controller_assert( 'human' === $approval_events[0]['source'] && 'completed' === $approval_events[0]['status'], 'approval event lost human-decision provenance' );
tv_agent_controller_assert( ! preg_match( '/execution|booking|purchase/', $approval_events[0]['type'] ), 'approval decision emitted a false execution event' );

echo "Tra-Vel agent controller runtime validation passed (private ownership, deterministic events, clarification and quota failure gates, no supplier or approval side effects).\n";
