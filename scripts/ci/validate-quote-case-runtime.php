<?php
/**
 * Deterministic runtime harness for the assisted quote-case control plane.
 *
 * This executes the production policy and REST controller against in-memory
 * stores. It intentionally performs no database, network, browser, supplier,
 * email, or messaging work.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_AGENT_PATH', dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['tvq_current_user']      = 0;
$GLOBALS['tvq_capabilities']      = array();
$GLOBALS['tvq_handoff_filter']    = null;
$GLOBALS['tvq_handoff_context']   = null;
$GLOBALS['tvq_registered_routes'] = array();
$GLOBALS['tvq_uuid_counter']      = 100;
$GLOBALS['tvq_scheduled_events']  = array();
$GLOBALS['tvq_options']           = array( 'tra_vel_quote_case_db_version' => '1.1.0', 'tra_vel_agent_db_version' => '1.2.0' );
$GLOBALS['tvq_assisted_proposal_ready'] = true;

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
	private $params;

	public function __construct( $params = array() ) {
		$this->params = $params;
	}

	public function get_param( $key ) {
		return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null;
	}
}

class Tra_Vel_Test_Quote_Schema_Wpdb {
	public $prefix = 'wp_';
	public $last_error = '';
	public $agent_run_owner = null;
	public $agent_run_read_error = '';
	public $read_fail_pattern = '';
	public $query_fail_pattern = '';
	public $orphan_ids = array();
	public $transaction_log = array();
	public $delete_fail_table = '';
	public $schemas = array(
		'wp_tra_vel_agent_runs' => array( 'id', 'run_uuid', 'owner_user_id', 'owner_token_hash', 'request_fingerprint', 'status', 'mode', 'locale', 'input_kind', 'input_text', 'trip_request', 'proposals', 'provider_state', 'created_at', 'updated_at', 'expires_at' ),
		'wp_tra_vel_agent_events' => array( 'id', 'run_id', 'sequence_no', 'event_uuid', 'event_type', 'phase', 'status', 'source', 'visible', 'message', 'payload', 'created_at' ),
		'wp_tra_vel_agent_approvals' => array( 'id', 'approval_uuid', 'run_id', 'approval_version', 'action_type', 'scope_digest', 'status', 'summary', 'action_snapshot', 'decision_key', 'decided_by', 'created_at', 'expires_at', 'decided_at' ),
		'wp_tra_vel_agent_limits' => array( 'counter_key', 'counter_value', 'expires_at' ),
		'wp_tra_vel_quote_cases' => array( 'id', 'case_uuid', 'reference_code', 'source_run_id', 'source_run_uuid', 'source_request_uuid', 'source_request_revision', 'owner_user_id', 'owner_token_hash', 'status', 'case_version', 'current_revision', 'latest_request_digest', 'last_event_sequence', 'service_mode', 'assigned_user_id', 'consent_version', 'consented_at', 'acquisition', 'contact', 'created_at', 'updated_at', 'last_activity_at', 'service_expires_at', 'retention_until', 'closed_at', 'legal_hold' ),
		'wp_tra_vel_quote_case_revisions' => array( 'id', 'case_id', 'revision_no', 'source_request_uuid', 'source_request_revision', 'request_digest', 'request_snapshot', 'actor_type', 'actor_user_id', 'created_at' ),
		'wp_tra_vel_quote_case_events' => array( 'id', 'case_id', 'sequence_no', 'case_version', 'event_uuid', 'event_type', 'from_status', 'to_status', 'actor_type', 'actor_user_id', 'source', 'visibility', 'message', 'payload', 'payload_digest', 'created_at' ),
		'wp_tra_vel_quote_case_idempotency' => array( 'id', 'operation_scope', 'principal_hash', 'idempotency_key_hash', 'request_digest', 'case_uuid', 'case_version', 'response_code', 'created_at', 'expires_at' ),
	);
	public $unique_indexes = array(
		'wp_tra_vel_agent_runs' => array( 'PRIMARY' => array( 'id' ), 'run_uuid' => array( 'run_uuid' ) ),
		'wp_tra_vel_agent_events' => array( 'PRIMARY' => array( 'id' ), 'event_uuid' => array( 'event_uuid' ), 'run_sequence' => array( 'run_id', 'sequence_no' ) ),
		'wp_tra_vel_agent_approvals' => array( 'PRIMARY' => array( 'id' ), 'approval_uuid' => array( 'approval_uuid' ), 'decision_key' => array( 'decision_key' ) ),
		'wp_tra_vel_agent_limits' => array( 'PRIMARY' => array( 'counter_key' ) ),
		'wp_tra_vel_quote_cases' => array(
			'case_uuid' => array( 'case_uuid' ),
			'reference_code' => array( 'reference_code' ),
			'source_run_uuid' => array( 'source_run_uuid' ),
		),
		'wp_tra_vel_quote_case_revisions' => array( 'case_revision' => array( 'case_id', 'revision_no' ) ),
		'wp_tra_vel_quote_case_events' => array(
			'event_uuid' => array( 'event_uuid' ),
			'case_sequence' => array( 'case_id', 'sequence_no' ),
		),
		'wp_tra_vel_quote_case_idempotency' => array( 'operation_key' => array( 'operation_scope', 'principal_hash', 'idempotency_key_hash' ) ),
	);
	public $non_unique_indexes = array(
		'wp_tra_vel_quote_case_revisions' => array( 'case_digest' => array( 'case_id', 'request_digest' ) ),
	);
	private $errors_suppressed = false;

	public function suppress_errors( $suppress = true ) {
		$previous = $this->errors_suppressed;
		$this->errors_suppressed = (bool) $suppress;
		return $previous;
	}

	public function get_col( $query, $column = 0 ) {
		unset( $column );
		if ( $this->fail_read( $query ) ) {
			return array();
		}
		if ( false !== strpos( (string) $query, 'LEFT JOIN `wp_tra_vel_quote_cases`' ) ) {
			return array_values( $this->orphan_ids );
		}
		if ( ! preg_match( '/SHOW COLUMNS FROM `([^`]+)`/', (string) $query, $match ) ) {
			return array();
		}
		return $this->schemas[ $match[1] ] ?? array();
	}

	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		foreach ( $args as $arg ) {
			$replacement = is_int( $arg ) ? (string) $arg : "'" . str_replace( "'", "''", (string) $arg ) . "'";
			$query = preg_replace( '/%[sd]/', $replacement, (string) $query, 1 );
		}
		return $query;
	}

	public function get_row( $query, $output = null ) {
		unset( $output );
		if ( $this->fail_read( $query ) ) {
			return null;
		}
		if ( false !== strpos( (string) $query, 'SELECT owner_user_id FROM wp_tra_vel_agent_runs' ) ) {
			$this->last_error = (string) $this->agent_run_read_error;
			return null === $this->agent_run_owner ? null : array( 'owner_user_id' => (int) $this->agent_run_owner );
		}
		if ( ! preg_match( "/SHOW TABLE STATUS WHERE Name = '([^']+)'/", (string) $query, $match ) || ! isset( $this->schemas[ $match[1] ] ) ) {
			return null;
		}
		return array( 'Name' => $match[1], 'Engine' => 'InnoDB' );
	}

	public function get_results( $query, $output = null ) {
		unset( $output );
		if ( $this->fail_read( $query ) ) {
			return array();
		}
		if ( ! preg_match( '/SHOW INDEX FROM `([^`]+)`/', (string) $query, $match ) ) {
			return array();
		}
		$rows = array();
		foreach ( $this->unique_indexes[ $match[1] ] ?? array() as $key => $columns ) {
			foreach ( array_values( $columns ) as $offset => $column ) {
				$rows[] = array( 'Key_name' => $key, 'Non_unique' => 0, 'Seq_in_index' => $offset + 1, 'Column_name' => $column );
			}
		}
		foreach ( $this->non_unique_indexes[ $match[1] ] ?? array() as $key => $columns ) {
			foreach ( array_values( $columns ) as $offset => $column ) {
				$rows[] = array( 'Key_name' => $key, 'Non_unique' => 1, 'Seq_in_index' => $offset + 1, 'Column_name' => $column );
			}
		}
		return $rows;
	}

	public function get_var( $query ) {
		if ( $this->fail_read( $query ) ) {
			return null;
		}
		if ( false !== strpos( (string) $query, 'SELECT id FROM wp_tra_vel_agent_runs' ) ) {
			return 991;
		}
		return null;
	}

	public function query( $query ) {
		$this->transaction_log[] = (string) $query;
		if ( '' !== (string) $this->query_fail_pattern && false !== strpos( (string) $query, (string) $this->query_fail_pattern ) ) {
			return false;
		}
		if ( false !== strpos( (string) $query, 'DELETE FROM `wp_tra_vel_quote_case_' ) && ! empty( $this->orphan_ids ) ) {
			return count( $this->orphan_ids );
		}
		return 1;
	}

	public function delete( $table, $where, $formats = null ) {
		unset( $where, $formats );
		if ( (string) $table === (string) $this->delete_fail_table ) {
			return false;
		}
		return 'wp_tra_vel_agent_runs' === $table ? 1 : 0;
	}

	private function fail_read( $query ) {
		$this->last_error = '';
		if ( '' !== (string) $this->read_fail_pattern && false !== strpos( (string) $query, (string) $this->read_fail_pattern ) ) {
			$this->last_error = 'simulated read failure';
			return true;
		}
		return false;
	}
}
$GLOBALS['wpdb'] = new Tra_Vel_Test_Quote_Schema_Wpdb();

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function wp_list_pluck( $list, $field ) {
	return array_map(
		static function ( $item ) use ( $field ) {
			return is_array( $item ) && array_key_exists( $field, $item ) ? $item[ $field ] : null;
		},
		(array) $list
	);
}

function absint( $value ) {
	return abs( (int) $value );
}

function rest_sanitize_boolean( $value ) {
	return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
}

function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, $flags );
}

function get_current_user_id() {
	return (int) $GLOBALS['tvq_current_user'];
}

function current_user_can( $capability ) {
	$user_id = get_current_user_id();
	if ( 'read' === $capability ) {
		return $user_id > 0;
	}
	return ! empty( $GLOBALS['tvq_capabilities'][ $user_id ][ $capability ] );
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['tvq_options'] ) ? $GLOBALS['tvq_options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	unset( $autoload );
	$GLOBALS['tvq_options'][ $key ] = $value;
	return true;
}

function wp_generate_uuid4() {
	$GLOBALS['tvq_uuid_counter']++;
	return sprintf( '00000000-0000-4000-8000-%012d', $GLOBALS['tvq_uuid_counter'] );
}

function wp_generate_password( $length = 12 ) {
	return substr( str_repeat( 'deterministic-password', 8 ), 0, $length );
}

function wp_salt( $scheme = 'auth' ) {
	return 'deterministic-quote-salt-' . $scheme;
}

function is_ssl() {
	return true;
}

function wp_get_environment_type() {
	return 'production';
}

function register_rest_route( $namespace, $route, $args ) {
	$GLOBALS['tvq_registered_routes'][] = compact( 'namespace', 'route', 'args' );
}

function rest_ensure_response( $value ) {
	return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value );
}

function wp_next_scheduled( $hook, $args = array() ) {
	foreach ( $GLOBALS['tvq_scheduled_events'] as $event ) {
		if ( $event['hook'] === $hook && $event['args'] === $args ) {
			return $event['timestamp'];
		}
	}
	return false;
}

function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
	$GLOBALS['tvq_scheduled_events'][] = compact( 'timestamp', 'hook', 'args' );
	return true;
}

function esc_url_raw( $value, $protocols = null ) {
	unset( $protocols );
	return filter_var( (string) $value, FILTER_SANITIZE_URL );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function apply_filters( $hook, $value, ...$args ) {
	if ( 'tra_vel_agent_quote_case_prepare_handoff' !== $hook || ! is_callable( $GLOBALS['tvq_handoff_filter'] ) ) {
		return $value;
	}
	return call_user_func_array( $GLOBALS['tvq_handoff_filter'], array_merge( array( $value ), $args ) );
}

$GLOBALS['tvq_fired_actions'] = array();
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		$GLOBALS['tvq_fired_actions'][] = array( 'hook' => (string) $hook, 'args' => $args );
	}
}

function tvq_fired( $hook ) {
	$matches = array();
	foreach ( $GLOBALS['tvq_fired_actions'] as $fired ) {
		if ( $fired['hook'] === $hook ) {
			$matches[] = $fired;
		}
	}
	return $matches;
}

function __return_true() {
	return true;
}

class Tra_Vel_Assisted_Proposal_Store {
	public static function is_ready() {
		return ! empty( $GLOBALS['tvq_assisted_proposal_ready'] );
	}

	public static function proposals_table() {
		return $GLOBALS['wpdb']->prefix . 'tra_vel_assisted_proposals';
	}
}

require TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-store.php';
require TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-quote-case-policy.php';
require TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-quote-case-store.php';
require TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-quote-case-controller.php';

class Tra_Vel_Test_Quote_Agent_Store {
	public $runs = array();
	public $tokens = array();
	public $limit_counts = array();
	public $resume_read_error = '';
	public $resume_read_calls = 0;

	public function seed_ready_run( $suffix ) {
		$run_uuid     = sprintf( '10000000-0000-4000-8000-%012d', (int) $suffix );
		$request_uuid = sprintf( '20000000-0000-4000-8000-%012d', (int) $suffix );
		$token        = 'run-owner-token-' . str_pad( (string) $suffix, 32, '0', STR_PAD_LEFT );
		$run          = array(
			'id'               => (int) $suffix,
			'run_uuid'         => $run_uuid,
			'owner_user_id'    => 0,
			'owner_token_hash' => hash( 'sha256', $token ),
			'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			'status'          => 'request_ready',
			'trip_request'     => array(
				'contract_version' => '1.0.0',
				'request_id'      => $request_uuid,
				'revision'        => 1,
				'summary'         => 'Bangkok and Phuket for two adults',
				'language'        => 'en',
				'origin_text'     => 'Tel Aviv (TLV)',
				'destination_mode'=> 'fixed',
				'destinations'    => array( 'Bangkok', 'Phuket' ),
				'date_text'       => 'November 2026, flexible by three days',
				'date_flexibility'=> 'flexible',
				'travelers'       => array( 'adults' => 2, 'children' => 0, 'child_ages' => array(), 'rooms' => 1 ),
				'budget'          => array( 'amount' => 9000, 'currency' => 'ILS', 'flexibility' => 'soft' ),
				'vibes'           => array( 'beach', 'food' ),
				'hard_constraints'=> array(),
				'preferences'     => array( 'good value' ),
				'search_scope'    => array( 'flights', 'accommodation', 'transfers', 'activities', 'insurance' ),
				'material_questions' => array(),
				'assumptions'     => array(),
				'confidence'      => 0.95,
				'source'          => array( 'channel' => 'web', 'input_kind' => 'typed', 'input_sha256' => str_repeat( 'a', 64 ), 'transcript_confirmed' => true ),
				'readiness'       => array( 'status' => 'ready_for_search', 'blockers' => array() ),
			),
		);
		$this->runs[ $run_uuid ]   = $run;
		$this->tokens[ $run_uuid ] = $token;
		return array( 'run' => $run, 'token' => $token );
	}

	public function get_run_by_uuid( $run_uuid ) {
		return $this->runs[ $run_uuid ] ?? null;
	}

	public function get_run_ownership_by_uuids( $run_uuids, &$read_error = null ) {
		$this->resume_read_calls++;
		$read_error = (string) $this->resume_read_error;
		if ( '' !== $read_error ) {
			return array();
		}
		$rows = array();
		foreach ( array_unique( (array) $run_uuids ) as $run_uuid ) {
			if ( ! isset( $this->runs[ $run_uuid ] ) ) {
				continue;
			}
			$run = $this->runs[ $run_uuid ];
			$rows[ $run_uuid ] = array(
				'run_uuid'        => (string) $run_uuid,
				'owner_user_id'   => (int) $run['owner_user_id'],
				'owner_token_hash'=> (string) ( $run['owner_token_hash'] ?? '' ),
				'expires_at'      => (string) ( $run['expires_at'] ?? '' ),
			);
		}
		return $rows;
	}

	public function can_access( $run, $token, $user_id ) {
		if ( ! is_array( $run ) || empty( $run['expires_at'] ) || strtotime( $run['expires_at'] . ' UTC' ) < time() ) {
			return false;
		}
		if ( $user_id > 0 && (int) $run['owner_user_id'] === (int) $user_id ) {
			return true;
		}
		return 0 === (int) $run['owner_user_id'] && isset( $this->tokens[ $run['run_uuid'] ] ) && hash_equals( $this->tokens[ $run['run_uuid'] ], (string) $token );
	}

	public function consume_limit( $key, $limit, $expires_at ) {
		unset( $expires_at );
		$count = isset( $this->limit_counts[ $key ] ) ? (int) $this->limit_counts[ $key ] : 0;
		if ( $count >= (int) $limit ) {
			return false;
		}
		$this->limit_counts[ $key ] = $count + 1;
		return true;
	}
}

class Tra_Vel_Test_Quote_Case_Store {
	const ACTIVE_DAYS = 30;

	public $cases = array();
	public $events = array();
	public $create_calls = 0;
	public $handoff_calls = 0;
	public $list_read_error = '';

	private $next_id = 1;

	public function create_from_run( $run, $principal, $consent_version, $idempotency_key, $acquisition = array(), $contact = array() ) {
		unset( $idempotency_key );
		$this->create_calls++;
		foreach ( $this->cases as $existing ) {
			if ( $run['run_uuid'] === $existing['source_run_uuid'] ) {
				return array( 'case' => $existing, 'replayed' => true, 'created' => false );
			}
		}

		$snapshot = Tra_Vel_Quote_Case_Policy::snapshot( $run['trip_request'] );
		$digest   = Tra_Vel_Quote_Case_Policy::digest( $snapshot );
		$id       = $this->next_id++;
		$uuid     = sprintf( '30000000-0000-4000-8000-%012d', $id );
		$case     = array(
			'id'                      => $id,
			'case_uuid'               => $uuid,
			'reference_code'          => 'TV-RT' . str_pad( (string) $id, 6, '0', STR_PAD_LEFT ),
			'source_run_uuid'         => (string) $run['run_uuid'],
			'source_request_uuid'     => (string) $snapshot['request_id'],
			'source_request_revision' => (int) $snapshot['revision'],
			'owner_user_id'           => (int) $principal['user_id'],
			'owner_token_hash'        => (int) $principal['user_id'] > 0 ? '' : (string) $principal['token_hash'],
			'status'                  => 'queued',
			'case_version'            => 1,
			'current_revision'        => 1,
			'latest_request_digest'   => $digest,
			'last_event_sequence'     => 1,
			'assigned_user_id'        => 0,
			'consent_version'         => (string) $consent_version,
			'consented_at'            => '2030-04-01 10:00:00',
			'acquisition'             => is_array( $acquisition ) ? $acquisition : array(),
			'contact'                 => is_array( $contact ) ? $contact : array(),
			'created_at'              => '2030-04-01 10:00:00',
			'updated_at'              => '2030-04-01 10:00:00',
			'retention_until'         => '2030-06-30 10:00:00',
			'legal_hold'              => 0,
			'snapshot'                => $snapshot,
		);
		$this->cases[ $uuid ]  = $case;
		$this->events[ $uuid ] = array();
		$this->append_event( $case, 'quote_case.created', null, 'queued', 'traveler', 'web', 'public', 'Your assisted quote request was received.', array( 'request_revision' => 1 ) );
		return array( 'case' => $case, 'replayed' => false, 'created' => true );
	}

	public function get_case_by_uuid( $case_uuid ) {
		return $this->cases[ $case_uuid ] ?? null;
	}

	public function can_access( $case, $user_id, $guest_token_hash ) {
		if ( ! is_array( $case ) ) {
			return false;
		}
		if ( $user_id > 0 && (int) $case['owner_user_id'] === (int) $user_id ) {
			return true;
		}
		return 0 === (int) $case['owner_user_id']
			&& 64 === strlen( (string) $guest_token_hash )
			&& 64 === strlen( (string) $case['owner_token_hash'] )
			&& hash_equals( (string) $case['owner_token_hash'], (string) $guest_token_hash );
	}

	public function list_owned( $user_id, $guest_token_hash, $limit = 30, &$read_error = null ) {
		$read_error = (string) $this->list_read_error;
		if ( '' !== $read_error ) {
			return array();
		}
		$owned = array_filter(
			$this->cases,
			function ( $case ) use ( $user_id, $guest_token_hash ) {
				return $this->can_access( $case, $user_id, $guest_token_hash );
			}
		);
		return array_slice( array_values( $owned ), 0, (int) $limit );
	}

	public function get_events( $case_id, $after = 0, $include_internal = false ) {
		$uuid = $this->uuid_for_id( $case_id );
		if ( ! $uuid ) {
			return array();
		}
		return array_values(
			array_filter(
				$this->events[ $uuid ],
				static function ( $event ) use ( $after, $include_internal ) {
					return $event['sequence'] > (int) $after && ( $include_internal || 'public' === $event['visibility'] );
				}
			)
		);
	}

	public function get_event_page( $case_id, $after = 0, $include_internal = false, $limit = 50 ) {
		$events   = $this->get_events( $case_id, $after, $include_internal );
		$has_more = count( $events ) > (int) $limit;
		$events   = array_slice( $events, 0, (int) $limit );
		return array(
			'events'        => $events,
			'last_sequence' => $events ? (int) end( $events )['sequence'] : (int) $after,
			'has_more'      => $has_more,
		);
	}

	public function get_recent_events( $case_id, $include_internal = false, $limit = 20 ) {
		$events = $this->get_events( $case_id, 0, $include_internal );
		if ( (int) $limit <= 1 ) {
			return array_slice( $events, 0, 1 );
		}
		if ( count( $events ) <= (int) $limit ) {
			return $events;
		}
		$creation = array_slice( $events, 0, 1 );
		$tail     = array_slice( $events, -( (int) $limit - 1 ) );
		return array_merge( $creation, $tail );
	}

	public function record_handoff( $case_uuid, $expected_version, $principal, $channel, $provider, $target_digest, $expires_at, $idempotency_key ) {
		unset( $idempotency_key );
		$case = $this->get_case_by_uuid( $case_uuid );
		if ( ! $this->can_access( $case, (int) ( $principal['user_id'] ?? 0 ), (string) ( $principal['token_hash'] ?? '' ) ) ) {
			return new WP_Error( 'tra_vel_quote_case_forbidden', 'Forbidden.', array( 'status' => 403 ) );
		}
		$this->handoff_calls++;
		return $this->mutate(
			$case_uuid,
			$expected_version,
			null,
			'handoff.prepared',
			'traveler',
			'web',
			(int) ( $principal['user_id'] ?? 0 ),
			array( 'channel' => $channel, 'provider' => $provider, 'target_digest' => $target_digest, 'expires_at' => $expires_at, 'dispatched' => false )
		);
	}

	public function cancel( $case_uuid, $expected_version, $principal, $idempotency_key ) {
		unset( $idempotency_key );
		$case = $this->get_case_by_uuid( $case_uuid );
		if ( ! $this->can_access( $case, (int) ( $principal['user_id'] ?? 0 ), (string) ( $principal['token_hash'] ?? '' ) ) ) {
			return new WP_Error( 'tra_vel_quote_case_forbidden', 'Forbidden.', array( 'status' => 403 ) );
		}
		return $this->mutate( $case_uuid, $expected_version, 'cancelled', 'quote_case.cancelled', 'traveler', 'web', (int) ( $principal['user_id'] ?? 0 ), array() );
	}

	public function claim( $case_uuid, $expected_version, $user_id, $guest_token_hash, $idempotency_key ) {
		unset( $idempotency_key );
		$case = $this->get_case_by_uuid( $case_uuid );
		if ( ! $case || $user_id < 1 || ! $this->can_access( $case, 0, $guest_token_hash ) ) {
			return new WP_Error( 'tra_vel_quote_case_claim_forbidden', 'Claim forbidden.', array( 'status' => 403 ) );
		}
		if ( (int) $case['case_version'] !== (int) $expected_version ) {
			return $this->version_error( $case );
		}
		$case['owner_user_id']    = (int) $user_id;
		$case['owner_token_hash'] = '';
		$this->cases[ $case_uuid ] = $case;
		return $this->mutate( $case_uuid, $expected_version, null, 'quote_case.claimed', 'traveler', 'account', $user_id, array() );
	}

	public function recover_owner_from_run( $case, $run_uuid, $principal ) {
		if ( ! is_array( $case ) || $case['source_run_uuid'] !== $run_uuid || (int) $case['owner_user_id'] > 0 ) {
			return new WP_Error( 'tra_vel_quote_case_recovery_forbidden', 'Recovery forbidden.', array( 'status' => 403 ) );
		}
		$case_uuid = $case['case_uuid'];
		$case['owner_user_id']    = (int) $principal['user_id'];
		$case['owner_token_hash'] = $case['owner_user_id'] > 0 ? '' : (string) $principal['token_hash'];
		$this->cases[ $case_uuid ] = $case;
		$result = $this->mutate( $case_uuid, $case['case_version'], null, 'quote_case.owner_recovered', 'traveler', $case['owner_user_id'] > 0 ? 'account' : 'web', $case['owner_user_id'], array( 'owner_scope' => $case['owner_user_id'] > 0 ? 'account' : 'private_browser_owner' ) );
		return array( 'case' => $result['case'], 'event' => $result['event'] );
	}

	public function list_operator( $status = '', $page = 1, $per_page = 30 ) {
		$cases = array_values(
			array_filter(
				$this->cases,
				static function ( $case ) use ( $status ) {
					return '' === $status || $status === $case['status'];
				}
			)
		);
		$total  = count( $cases );
		$offset = ( max( 1, (int) $page ) - 1 ) * (int) $per_page;
		return array( 'cases' => array_slice( $cases, $offset, (int) $per_page ), 'total' => $total );
	}

	public function transition( $case_uuid, $to_status, $expected_version, $actor_user_id, $idempotency_key ) {
		unset( $idempotency_key );
		$case = $this->get_case_by_uuid( $case_uuid );
		if ( ! $case || ! Tra_Vel_Quote_Case_Policy::can_transition( $case['status'], $to_status ) ) {
			return new WP_Error( 'tra_vel_quote_case_transition_invalid', 'Invalid transition.', array( 'status' => 409 ) );
		}
		return $this->mutate( $case_uuid, $expected_version, $to_status, 'quote_case.status_changed', 'operator', 'operator', $actor_user_id, array() );
	}

	private function mutate( $case_uuid, $expected_version, $to_status, $event_type, $actor_type, $source, $actor_user_id, $data ) {
		$case = $this->get_case_by_uuid( $case_uuid );
		if ( ! $case ) {
			return new WP_Error( 'tra_vel_quote_case_missing', 'Case missing.', array( 'status' => 404 ) );
		}
		if ( (int) $case['case_version'] !== (int) $expected_version ) {
			return $this->version_error( $case );
		}
		$from_status = $case['status'];
		$to_status   = null === $to_status ? $from_status : $to_status;
		$case['status']              = $to_status;
		$case['case_version']       += 1;
		$case['last_event_sequence'] = count( $this->events[ $case_uuid ] ) + 1;
		$case['updated_at']          = '2030-04-01 10:05:00';
		if ( 'in_review' === $to_status && $actor_user_id > 0 ) {
			$case['assigned_user_id'] = (int) $actor_user_id;
		}
		$this->cases[ $case_uuid ] = $case;
		$event = $this->append_event( $case, $event_type, $from_status, $to_status, $actor_type, $source, 'public', 'Deterministic quote-case event.', $data );
		return array( 'case' => $case, 'event' => $event, 'replayed' => false );
	}

	private function append_event( $case, $type, $from_status, $to_status, $actor_type, $source, $visibility, $message, $data ) {
		$sequence = count( $this->events[ $case['case_uuid'] ] ) + 1;
		$event    = array(
			'contract_version' => Tra_Vel_Quote_Case_Policy::EVENT_CONTRACT_VERSION,
			'event_id'        => wp_generate_uuid4(),
			'sequence'        => $sequence,
			'type'            => $type,
			'from_status'     => $from_status,
			'to_status'       => $to_status,
			'actor_type'      => $actor_type,
			'source'          => $source,
			'visibility'      => $visibility,
			'message'         => $message,
			'data'            => $data,
			'occurred_at'     => '2030-04-01T10:05:00Z',
		);
		$this->events[ $case['case_uuid'] ][] = $event;
		return $event;
	}

	private function uuid_for_id( $case_id ) {
		foreach ( $this->cases as $uuid => $case ) {
			if ( (int) $case['id'] === (int) $case_id ) {
				return $uuid;
			}
		}
		return '';
	}

	private function version_error( $case ) {
		return new WP_Error( 'tra_vel_quote_case_version_conflict', 'Version conflict.', array( 'status' => 409, 'current_version' => (int) $case['case_version'] ) );
	}
}

function tvq_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Tra-Vel quote case runtime validation failed: {$message}\n" );
		exit( 1 );
	}
}

function tvq_assert_error( $value, $code, $label ) {
	tvq_assert( is_wp_error( $value ), "{$label} did not fail closed" );
	tvq_assert( $code === $value->get_error_code(), "{$label} returned {$value->get_error_code()} instead of {$code}" );
}

function tvq_assert_private( $response, $label ) {
	tvq_assert( $response instanceof WP_REST_Response, "{$label} did not return a REST response" );
	tvq_assert( 'private, no-store, max-age=0' === ( $response->headers['Cache-Control'] ?? '' ), "{$label} is cacheable" );
	tvq_assert( 'noindex, nofollow, noarchive' === ( $response->headers['X-Robots-Tag'] ?? '' ), "{$label} is indexable" );
	tvq_assert( 'no-cache' === ( $response->headers['Pragma'] ?? '' ), "{$label} lacks no-cache policy" );
}

function tvq_assert_public_contract( $case, $label ) {
	$case_schema  = json_decode( (string) file_get_contents( TRA_VEL_AGENT_PATH . '/schemas/quote-case.schema.json' ), true );
	$event_schema = json_decode( (string) file_get_contents( TRA_VEL_AGENT_PATH . '/schemas/quote-case-event.schema.json' ), true );
	$actual       = array_keys( $case );
	$expected     = array_keys( $case_schema['properties'] );
	sort( $actual );
	sort( $expected );
	tvq_assert( $expected === $actual, "{$label} fields diverge from quote-case.schema.json" );
	tvq_assert( '1.1.0' === $case['contract_version'] && $case_schema['properties']['contract_version']['const'] === $case['contract_version'] && Tra_Vel_Quote_Case_Policy::CONTRACT_VERSION === $case['contract_version'], "{$label} did not emit the versioned QuoteCase 1.1.0 contract" );
	tvq_assert( preg_match( '/^TV-[A-Z0-9]{8}$/', $case['reference'] ), "{$label} reference is not opaque" );
	tvq_assert( $case['version'] >= 1 && 64 === strlen( $case['source']['request_digest'] ), "{$label} lost version or request digest" );
	tvq_assert( is_bool( $case['resume_available'] ), "{$label} resume_available is not boolean" );
	tvq_assert( ! isset( $case['owner_token_hash'], $case['owner_user_id'], $case['snapshot'], $case['consent_version'] ), "{$label} leaked internal ownership or consent data" );
	tvq_assert( ! empty( $case['events'] ), "{$label} omitted its creation event" );
	foreach ( $case['events'] as $event ) {
		$actual_event   = array_keys( $event );
		$expected_event = array_keys( $event_schema['properties'] );
		sort( $actual_event );
		sort( $expected_event );
		tvq_assert( $expected_event === $actual_event, "{$label} event fields diverge from quote-case-event.schema.json" );
		tvq_assert( '1.1.0' === $event['contract_version'] && $event_schema['properties']['contract_version']['const'] === $event['contract_version'] && Tra_Vel_Quote_Case_Policy::EVENT_CONTRACT_VERSION === $event['contract_version'], "{$label} did not emit the versioned QuoteCaseEvent 1.1.0 contract" );
		tvq_assert( in_array( $event['actor_type'], $event_schema['properties']['actor_type']['enum'], true ), "{$label} event actor provenance is invalid" );
		tvq_assert( in_array( $event['source'], $event_schema['properties']['source']['enum'], true ), "{$label} event source provenance is invalid" );
		tvq_assert( in_array( $event['visibility'], $event_schema['properties']['visibility']['enum'], true ), "{$label} event visibility is invalid" );
	}
}

function tvq_request( $run, $overrides = array() ) {
	return new WP_REST_Request(
		array_merge(
			array(
				'run_id'              => $run['run_uuid'],
				'expected_request_id' => $run['trip_request']['request_id'],
				'expected_revision'   => $run['trip_request']['revision'],
				'consent'             => true,
				'consent_version'     => Tra_Vel_Quote_Case_Policy::CONSENT_VERSION,
				'idempotency_key'     => 'quote-case-create-00000001',
			),
			$overrides
		)
	);
}

function tvq_extract_cookie_token( $header, $name ) {
	$matched = preg_match( '/' . preg_quote( $name, '/' ) . '=([^;]+)/', (string) $header, $matches );
	return $matched ? rawurldecode( $matches[1] ) : '';
}

function tvq_reset_runtime() {
	$GLOBALS['tvq_current_user']    = 0;
	$GLOBALS['tvq_capabilities']    = array();
	$GLOBALS['tvq_handoff_filter']  = null;
	$GLOBALS['tvq_handoff_context'] = null;
	$_COOKIE                        = array();
}

function tvq_fixture( $suffix ) {
	$agent_store = new Tra_Vel_Test_Quote_Agent_Store();
	$seed        = $agent_store->seed_ready_run( $suffix );
	$case_store  = new Tra_Vel_Test_Quote_Case_Store();
	$controller  = new Tra_Vel_Quote_Case_Controller( $case_store, $agent_store );
	return array( $controller, $case_store, $seed['run'], $seed['token'], $agent_store );
}

function tvq_create_guest_case( $controller, $run, $run_token ) {
	$_COOKIE['__Host-tra_vel_agent_run'] = $run['run_uuid'] . '.' . $run_token;
	$response = $controller->create_case( tvq_request( $run ) );
	tvq_assert_private( $response, 'guest quote create' );
	tvq_assert( 201 === $response->status, 'guest quote create did not return 201' );
	$cookie = $response->headers['Set-Cookie'] ?? '';
	tvq_assert( false !== strpos( $cookie, '__Host-tra_vel_quote_owner=' ), 'guest quote create omitted owner cookie' );
	tvq_assert( false !== strpos( $cookie, 'Path=/; Secure; HttpOnly; SameSite=Lax' ), 'owner cookie lacks Secure, HttpOnly, or SameSite policy' );
	$token = tvq_extract_cookie_token( $cookie, '__Host-tra_vel_quote_owner' );
	tvq_assert( strlen( $token ) >= 32, 'owner cookie token is too short' );
	$_COOKIE['__Host-tra_vel_quote_owner'] = $token;
	return array( $response, $token );
}

// Deployment health distinguishes source-code version from an actually
// installed, readable table shape.
$agent_schema_health = Tra_Vel_Agent_Store::schema_health();
tvq_assert( '1.2.0' === $agent_schema_health['installed_schema_version'], 'Agent store health lost the installed database version' );
tvq_assert( true === $agent_schema_health['tables_ready'] && 4 === $agent_schema_health['ready_tables'] && 4 === $agent_schema_health['transactional_tables'], 'Agent store health rejected a complete transactional schema' );
tvq_assert( true === $agent_schema_health['required_indexes_ready'] && 9 === $agent_schema_health['ready_indexes'], 'Agent store health did not verify its nine primary and unique concurrency indexes' );
$complete_agent_schemas = array_intersect_key( $GLOBALS['wpdb']->schemas, array_flip( array( 'wp_tra_vel_agent_runs', 'wp_tra_vel_agent_events', 'wp_tra_vel_agent_approvals', 'wp_tra_vel_agent_limits' ) ) );
foreach ( $complete_agent_schemas as $table => $columns ) {
	foreach ( array_values( $columns ) as $column ) {
		$GLOBALS['wpdb']->schemas[ $table ] = array_values( array_diff( $columns, array( $column ) ) );
		$incomplete_agent_health = Tra_Vel_Agent_Store::schema_health();
		tvq_assert( false === $incomplete_agent_health['tables_ready'], 'Agent store health masked missing runtime column ' . $table . '.' . $column );
	}
	$GLOBALS['wpdb']->schemas[ $table ] = $columns;
}
$saved_agent_event_indexes = $GLOBALS['wpdb']->unique_indexes['wp_tra_vel_agent_events'];
$GLOBALS['wpdb']->unique_indexes['wp_tra_vel_agent_events'] = array( 'PRIMARY' => array( 'id' ), 'event_uuid' => array( 'event_uuid' ) );
$incomplete_agent_index_health = Tra_Vel_Agent_Store::schema_health();
tvq_assert( false === $incomplete_agent_index_health['tables_ready'] && false === $incomplete_agent_index_health['required_indexes_ready'], 'Agent store health masked a missing event sequence index' );
$GLOBALS['wpdb']->transaction_log = array();
$unready_agent_cleanup = Tra_Vel_Agent_Store::cleanup_expired();
tvq_assert( in_array( 'schema_not_ready', $unready_agent_cleanup['errors'], true ) && empty( $GLOBALS['wpdb']->transaction_log ), 'Agent cleanup mutated storage while its transactional schema was unready' );
$GLOBALS['wpdb']->unique_indexes['wp_tra_vel_agent_events'] = $saved_agent_event_indexes;
tvq_assert( true === Tra_Vel_Agent_Store::schema_health()['tables_ready'] && Tra_Vel_Agent_Store::is_ready(), 'Agent store readiness did not recover after its complete schema was restored' );

$agent_cleanup = new ReflectionMethod( 'Tra_Vel_Agent_Store', 'delete_expired_run_aggregate' );
$agent_cleanup->setAccessible( true );
$GLOBALS['wpdb']->transaction_log = array();
$GLOBALS['wpdb']->read_fail_pattern = 'SELECT id FROM wp_tra_vel_agent_runs';
$failed_agent_cleanup_read = $agent_cleanup->invoke( null, 991, '2030-04-03 00:00:00' );
tvq_assert( is_wp_error( $failed_agent_cleanup_read ) && 'tra_vel_agent_cleanup_lock_failed' === $failed_agent_cleanup_read->get_error_code(), 'AgentRun cleanup mistook a failed lock read for a missing run' );
tvq_assert( in_array( 'ROLLBACK', $GLOBALS['wpdb']->transaction_log, true ), 'AgentRun cleanup did not roll back after a failed lock read' );
$GLOBALS['wpdb']->read_fail_pattern = '';
$GLOBALS['wpdb']->transaction_log = array();
$GLOBALS['wpdb']->delete_fail_table = 'wp_tra_vel_agent_events';
$failed_agent_cleanup = $agent_cleanup->invoke( null, 991, '2030-04-03 00:00:00' );
tvq_assert( is_wp_error( $failed_agent_cleanup ) && 'tra_vel_agent_cleanup_delete_failed' === $failed_agent_cleanup->get_error_code(), 'AgentRun cleanup did not fail closed on a child deletion error' );
tvq_assert( in_array( 'ROLLBACK', $GLOBALS['wpdb']->transaction_log, true ), 'AgentRun cleanup did not roll back a partial aggregate deletion' );
$GLOBALS['wpdb']->transaction_log = array();
$GLOBALS['wpdb']->delete_fail_table = '';
tvq_assert( 1 === $agent_cleanup->invoke( null, 991, '2030-04-03 00:00:00' ), 'AgentRun cleanup could not commit a complete aggregate deletion' );
tvq_assert( in_array( 'COMMIT', $GLOBALS['wpdb']->transaction_log, true ), 'AgentRun cleanup did not commit its complete aggregate deletion' );

$schema_health = Tra_Vel_Quote_Case_Store::schema_health();
tvq_assert( '1.1.0' === $schema_health['installed_schema_version'], 'schema health lost the installed database version' );
tvq_assert( true === $schema_health['tables_ready'] && 4 === $schema_health['ready_tables'], 'schema health rejected a complete quote-case database shape' );
tvq_assert( 4 === $schema_health['transactional_tables'], 'schema health did not require every quote-case table to use InnoDB' );
tvq_assert( true === $schema_health['required_indexes_ready'] && 7 === $schema_health['ready_indexes'], 'schema health did not verify the unique concurrency indexes' );
tvq_assert( true === $schema_health['supporting_indexes_ready'] && 1 === $schema_health['ready_supporting_indexes'], 'schema health did not require the non-unique revision digest lookup index' );
$complete_schemas = array_intersect_key( $GLOBALS['wpdb']->schemas, array_flip( array( 'wp_tra_vel_quote_cases', 'wp_tra_vel_quote_case_revisions', 'wp_tra_vel_quote_case_events', 'wp_tra_vel_quote_case_idempotency' ) ) );
foreach ( $complete_schemas as $table => $columns ) {
	foreach ( array_values( $columns ) as $column ) {
		$GLOBALS['wpdb']->schemas[ $table ] = array_values( array_diff( $columns, array( $column ) ) );
		$incomplete_health = Tra_Vel_Quote_Case_Store::schema_health();
		tvq_assert( false === $incomplete_health['tables_ready'], 'schema health masked missing runtime column ' . $table . '.' . $column );
	}
	$GLOBALS['wpdb']->schemas[ $table ] = $columns;
}
$saved_event_indexes = $GLOBALS['wpdb']->unique_indexes['wp_tra_vel_quote_case_events'];
$GLOBALS['wpdb']->unique_indexes['wp_tra_vel_quote_case_events'] = array( 'event_uuid' => array( 'event_uuid' ) );
$incomplete_index_health = Tra_Vel_Quote_Case_Store::schema_health();
tvq_assert( false === $incomplete_index_health['tables_ready'] && false === $incomplete_index_health['required_indexes_ready'], 'schema health masked a missing unique sequence index' );
$GLOBALS['wpdb']->unique_indexes['wp_tra_vel_quote_case_events'] = $saved_event_indexes;
$saved_revision_digest_indexes = $GLOBALS['wpdb']->non_unique_indexes['wp_tra_vel_quote_case_revisions'];
$GLOBALS['wpdb']->non_unique_indexes['wp_tra_vel_quote_case_revisions'] = array();
$missing_digest_health = Tra_Vel_Quote_Case_Store::schema_health();
tvq_assert( false === $missing_digest_health['tables_ready'] && false === $missing_digest_health['supporting_indexes_ready'], 'schema health masked a missing or legacy-unique revision digest index' );
$GLOBALS['wpdb']->non_unique_indexes['wp_tra_vel_quote_case_revisions'] = $saved_revision_digest_indexes;
$restored_health = Tra_Vel_Quote_Case_Store::schema_health();
tvq_assert( true === $restored_health['tables_ready'] && Tra_Vel_Quote_Case_Store::is_ready(), 'schema readiness did not recover after a complete shape was restored' );

$quote_cleanup_store = new Tra_Vel_Quote_Case_Store();
$attach_creation_events = new ReflectionMethod( 'Tra_Vel_Quote_Case_Store', 'attach_creation_events' );
$attach_creation_events->setAccessible( true );
$event_read_error = '';
$GLOBALS['wpdb']->read_fail_pattern = 'FROM wp_tra_vel_quote_case_events';
$event_args = array( array( array( 'id' => 71 ) ), false, &$event_read_error );
$failed_event_attach = $attach_creation_events->invokeArgs( $quote_cleanup_store, $event_args );
tvq_assert( array() === $failed_event_attach && 'simulated read failure' === $event_read_error, 'quote list mistook a failed creation-event read for an event-free case' );
$GLOBALS['wpdb']->read_fail_pattern = '';
$event_read_error = '';
$missing_event_args = array( array( array( 'id' => 71 ) ), false, &$event_read_error );
$missing_event_attach = $attach_creation_events->invokeArgs( $quote_cleanup_store, $missing_event_args );
tvq_assert( array() === $missing_event_attach && 'missing quote creation event' === $event_read_error, 'quote list exposed an aggregate without its schema-required creation event' );

$quote_expiry_cleanup = new ReflectionMethod( 'Tra_Vel_Quote_Case_Store', 'expire_service_batch' );
$quote_expiry_cleanup->setAccessible( true );
$GLOBALS['wpdb']->read_fail_pattern = 'service_expires_at <';
$failed_quote_expiry_read = $quote_expiry_cleanup->invoke( $quote_cleanup_store, '2030-04-03 00:00:00', 0, 100 );
tvq_assert( is_wp_error( $failed_quote_expiry_read ) && 'tra_vel_quote_cleanup_expiry_select_failed' === $failed_quote_expiry_read->get_error_code(), 'quote expiry cleanup mistook a failed read for an empty batch' );

$quote_retention_cleanup = new ReflectionMethod( 'Tra_Vel_Quote_Case_Store', 'delete_retention_batch' );
$quote_retention_cleanup->setAccessible( true );
$GLOBALS['tvq_assisted_proposal_ready'] = false;
$GLOBALS['wpdb']->transaction_log = array();
$guarded_quote_retention = $quote_retention_cleanup->invoke( $quote_cleanup_store, '2030-04-03 00:00:00', 0, 100 );
tvq_assert( is_wp_error( $guarded_quote_retention ) && 'tra_vel_quote_cleanup_proposal_store_unavailable' === $guarded_quote_retention->get_error_code(), 'quote retention cleanup did not fail closed while assisted-proposal child storage was uncertain' );
tvq_assert( empty( $GLOBALS['wpdb']->transaction_log ), 'quote retention cleanup opened a transaction before verifying assisted-proposal child storage' );
$GLOBALS['tvq_assisted_proposal_ready'] = true;
$GLOBALS['wpdb']->transaction_log = array();
$GLOBALS['wpdb']->read_fail_pattern = 'retention_until <';
$failed_quote_retention_read = $quote_retention_cleanup->invoke( $quote_cleanup_store, '2030-04-03 00:00:00', 0, 100 );
tvq_assert( is_wp_error( $failed_quote_retention_read ) && 'tra_vel_quote_cleanup_select_failed' === $failed_quote_retention_read->get_error_code(), 'quote retention cleanup mistook a failed lock read for an empty batch' );
tvq_assert( in_array( 'ROLLBACK', $GLOBALS['wpdb']->transaction_log, true ), 'quote retention cleanup did not roll back after a failed lock read' );

$quote_idempotency_cleanup = new ReflectionMethod( 'Tra_Vel_Quote_Case_Store', 'sweep_idempotency_batch' );
$quote_idempotency_cleanup->setAccessible( true );
$GLOBALS['wpdb']->read_fail_pattern = 'SELECT i.id';
$failed_quote_idempotency_read = $quote_idempotency_cleanup->invoke( $quote_cleanup_store, '2030-04-03 00:00:00', 0, 1000 );
tvq_assert( is_wp_error( $failed_quote_idempotency_read ) && 'tra_vel_quote_cleanup_idempotency_select_failed' === $failed_quote_idempotency_read->get_error_code(), 'quote idempotency cleanup mistook a failed read for an empty sweep' );
$GLOBALS['wpdb']->read_fail_pattern = '';

$quote_orphan_cleanup = new ReflectionMethod( 'Tra_Vel_Quote_Case_Store', 'sweep_orphan_case_rows_batch' );
$quote_orphan_cleanup->setAccessible( true );
$GLOBALS['wpdb']->orphan_ids = array( 71, 72 );
$GLOBALS['wpdb']->read_fail_pattern = 'FROM `wp_tra_vel_quote_case_revisions`';
$failed_orphan_read = $quote_orphan_cleanup->invoke( $quote_cleanup_store, 'wp_tra_vel_quote_case_revisions', 'revisions', 0, 1000 );
tvq_assert( is_wp_error( $failed_orphan_read ) && 'tra_vel_quote_cleanup_orphan_revisions_select_failed' === $failed_orphan_read->get_error_code(), 'quote revision orphan sweep mistook a failed read for an empty batch' );
$GLOBALS['wpdb']->read_fail_pattern = '';
$GLOBALS['wpdb']->query_fail_pattern = 'DELETE FROM `wp_tra_vel_quote_case_events`';
$failed_orphan_delete = $quote_orphan_cleanup->invoke( $quote_cleanup_store, 'wp_tra_vel_quote_case_events', 'events', 0, 1000 );
tvq_assert( is_wp_error( $failed_orphan_delete ) && 'tra_vel_quote_cleanup_orphan_events_delete_failed' === $failed_orphan_delete->get_error_code(), 'quote event orphan sweep accepted an incomplete delete' );
$GLOBALS['wpdb']->query_fail_pattern = '';
$successful_orphan_cleanup = $quote_orphan_cleanup->invoke( $quote_cleanup_store, 'wp_tra_vel_quote_case_revisions', 'revisions', 0, 1000 );
tvq_assert( 2 === $successful_orphan_cleanup['deleted'] && 72 === $successful_orphan_cleanup['last_id'], 'quote revision orphan sweep lost its bounded deletion cursor' );
$GLOBALS['wpdb']->orphan_ids = array();

$binder = new ReflectionMethod( 'Tra_Vel_Quote_Case_Store', 'bind_source_run_owner' );
$binder->setAccessible( true );
$GLOBALS['wpdb']->agent_run_owner = null;
$GLOBALS['wpdb']->agent_run_read_error = '';

$sync_decision = new ReflectionMethod( 'Tra_Vel_Quote_Case_Store', 'should_sync_snapshot' );
$sync_decision->setAccessible( true );
$operator_case_state = array( 'latest_request_digest' => str_repeat( 'd', 64 ), 'source_request_revision' => 3, 'status' => 'in_review', 'assigned_user_id' => 900, 'case_version' => 8, 'current_revision' => 3 );
tvq_assert( false === $sync_decision->invoke( new Tra_Vel_Quote_Case_Store(), $operator_case_state, array( 'revision' => 3 ), str_repeat( 'd', 64 ) ), 'duplicate source delivery would reset an in-review operator case' );
$operator_case_state['status'] = 'ready_for_assistance';
tvq_assert( false === $sync_decision->invoke( new Tra_Vel_Quote_Case_Store(), $operator_case_state, array( 'revision' => 3 ), str_repeat( 'd', 64 ) ), 'duplicate source delivery would reset a ready operator case' );
tvq_assert( false === $sync_decision->invoke( new Tra_Vel_Quote_Case_Store(), $operator_case_state, array( 'revision' => 2 ), str_repeat( 'e', 64 ) ), 'stale source revision could overwrite a newer frozen quote snapshot' );
tvq_assert( true === $sync_decision->invoke( new Tra_Vel_Quote_Case_Store(), $operator_case_state, array( 'revision' => 4 ), str_repeat( 'f', 64 ) ), 'strictly newer source revision was not eligible for synchronization' );

$sync_scheduler = new ReflectionMethod( 'Tra_Vel_Quote_Case_Store', 'schedule_sync_retry' );
$sync_scheduler->setAccessible( true );
$GLOBALS['tvq_scheduled_events'] = array();
$retry_run = array( 'run_uuid' => '00000000-0000-4000-8000-000000000992', 'trip_request' => array( 'revision' => 2 ) );
$sync_scheduler->invoke( new Tra_Vel_Quote_Case_Store(), $retry_run, 0 );
tvq_assert( 1 === count( $GLOBALS['tvq_scheduled_events'] ) && 3 === count( $GLOBALS['tvq_scheduled_events'][0]['args'] ) && 1 === $GLOBALS['tvq_scheduled_events'][0]['args'][2], 'quote sync retry did not persist its bounded retry attempt' );
$saved_retry_agent_schema = $GLOBALS['wpdb']->schemas['wp_tra_vel_agent_limits'];
$GLOBALS['wpdb']->schemas['wp_tra_vel_agent_limits'] = array_values( array_diff( $saved_retry_agent_schema, array( 'expires_at' ) ) );
Tra_Vel_Agent_Store::schema_health();
Tra_Vel_Quote_Case_Store::run_scheduled_sync( $retry_run['run_uuid'], 2, 1 );
tvq_assert( 2 === count( $GLOBALS['tvq_scheduled_events'] ) && 2 === $GLOBALS['tvq_scheduled_events'][1]['args'][2], 'schema-unready sync callback could not requeue its next bounded attempt without reading AgentRun storage' );
$GLOBALS['wpdb']->schemas['wp_tra_vel_agent_limits'] = $saved_retry_agent_schema;
Tra_Vel_Agent_Store::schema_health();
$GLOBALS['tvq_scheduled_events'] = array();
$GLOBALS['wpdb']->read_fail_pattern = 'SELECT * FROM wp_tra_vel_quote_cases WHERE source_run_uuid';
$failed_case_read_sync = ( new Tra_Vel_Quote_Case_Store() )->sync_from_run( array_merge( $retry_run, array( 'owner_user_id' => 0, 'status' => 'request_ready' ) ), 0, 1 );
tvq_assert( false === $failed_case_read_sync && 1 === count( $GLOBALS['tvq_scheduled_events'] ) && 2 === $GLOBALS['tvq_scheduled_events'][0]['args'][2], 'failed authoritative quote-case read consumed the committed revision sync' );
$GLOBALS['wpdb']->read_fail_pattern = '';
$GLOBALS['tvq_scheduled_events'] = array();
( new Tra_Vel_Quote_Case_Store() )->sync_from_run( array_merge( $retry_run, array( 'owner_user_id' => 0, 'status' => 'request_ready' ) ), 0, 1 );
tvq_assert( 0 === count( $GLOBALS['tvq_scheduled_events'] ), 'truly absent quote case was mistaken for a transient read error' );

$GLOBALS['wpdb']->read_fail_pattern = 'SELECT * FROM wp_tra_vel_agent_runs';
Tra_Vel_Quote_Case_Store::run_scheduled_sync( $retry_run['run_uuid'], 2, 1 );
tvq_assert( 1 === count( $GLOBALS['tvq_scheduled_events'] ) && 2 === $GLOBALS['tvq_scheduled_events'][0]['args'][2], 'failed authoritative AgentRun read consumed the durable sync retry' );
$GLOBALS['wpdb']->read_fail_pattern = '';
$GLOBALS['tvq_scheduled_events'] = array();
Tra_Vel_Quote_Case_Store::run_scheduled_sync( $retry_run['run_uuid'], 2, 1 );
tvq_assert( 0 === count( $GLOBALS['tvq_scheduled_events'] ), 'truly absent AgentRun was mistaken for a transient read error' );
$sync_scheduler->invoke( new Tra_Vel_Quote_Case_Store(), $retry_run, Tra_Vel_Quote_Case_Store::SYNC_RETRY_LIMIT );
tvq_assert( 0 === count( $GLOBALS['tvq_scheduled_events'] ), 'quote sync retry exceeded its bounded retry limit' );
tvq_assert( true === $binder->invoke( new Tra_Vel_Quote_Case_Store(), 991, '00000000-0000-4000-8000-000000000991', 77 ), 'an expired source AgentRun prevented its still-active quote from being claimed' );
$GLOBALS['wpdb']->agent_run_read_error = 'simulated read failure';
tvq_assert( false === $binder->invoke( new Tra_Vel_Quote_Case_Store(), 991, '00000000-0000-4000-8000-000000000991', 77 ), 'source AgentRun read failure was mistaken for a safely expired run' );
$GLOBALS['wpdb']->agent_run_read_error = '';

$gate_controller = new Tra_Vel_Quote_Case_Controller( new Tra_Vel_Test_Quote_Case_Store(), new Tra_Vel_Test_Quote_Agent_Store() );
$saved_idempotency_schema = $GLOBALS['wpdb']->schemas['wp_tra_vel_quote_case_idempotency'];
$GLOBALS['wpdb']->schemas['wp_tra_vel_quote_case_idempotency'] = array_values( array_diff( $saved_idempotency_schema, array( 'expires_at' ) ) );
Tra_Vel_Quote_Case_Store::schema_health();
$unready_gate = $gate_controller->can_use_store();
tvq_assert_error( $unready_gate, 'tra_vel_quote_case_store_unavailable', 'incomplete quote store runtime gate' );
$GLOBALS['wpdb']->schemas['wp_tra_vel_quote_case_idempotency'] = $saved_idempotency_schema;
Tra_Vel_Quote_Case_Store::schema_health();
$saved_agent_limit_schema = $GLOBALS['wpdb']->schemas['wp_tra_vel_agent_limits'];
$GLOBALS['wpdb']->schemas['wp_tra_vel_agent_limits'] = array_values( array_diff( $saved_agent_limit_schema, array( 'expires_at' ) ) );
Tra_Vel_Agent_Store::schema_health();
$unready_agent_gate = $gate_controller->can_use_store();
tvq_assert_error( $unready_agent_gate, 'tra_vel_quote_case_store_unavailable', 'incomplete Agent store cross-table runtime gate' );
$GLOBALS['wpdb']->schemas['wp_tra_vel_agent_limits'] = $saved_agent_limit_schema;
Tra_Vel_Agent_Store::schema_health();

$privacy_agent   = new Tra_Vel_Test_Quote_Agent_Store();
$privacy_request = $privacy_agent->seed_ready_run( 99 )['run']['trip_request'];
$privacy_request['summary']          = 'Traveler phone 0500000000 and a private medical diagnosis';
$privacy_request['hard_constraints'] = array( 'private medical diagnosis' );
$privacy_request['preferences']      = array( 'call 0500000000' );
$privacy_request['vibes']            = array( 'repeat the private diagnosis' );
$privacy_request['origin_text']      = 'Tel Aviv, call 050-000-0000';
$privacy_request['destinations']     = array( 'Bangkok', 'passport A12345678', 'private@example.com' );
$privacy_request['date_text']        = 'November 2026, medical diagnosis attached';
$privacy_snapshot = Tra_Vel_Quote_Case_Policy::snapshot( $privacy_request );
$privacy_json     = wp_json_encode( $privacy_snapshot );
tvq_assert( false === strpos( $privacy_json, 'diagnosis' ) && false === strpos( $privacy_json, '050' ) && false === strpos( $privacy_json, 'A12345678' ) && false === strpos( $privacy_json, 'private@example.com' ), 'durable snapshot retained sensitive model-written planning text' );
tvq_assert( '' === $privacy_snapshot['origin_text'] && '' === $privacy_snapshot['date_text'] && array( 'Bangkok' ) === $privacy_snapshot['destinations'], 'planning-text redaction removed safe locations or retained sensitive content' );
tvq_assert( array( 'adults', 'children', 'child_ages', 'rooms' ) === array_keys( $privacy_snapshot['travelers'] ), 'durable snapshot lost its bounded traveler taxonomy' );

// Policy state machine remains deliberately assisted-only.
tvq_assert( Tra_Vel_Quote_Case_Policy::can_transition( 'queued', 'in_review' ), 'legal queued-to-review transition disappeared' );
tvq_assert( ! Tra_Vel_Quote_Case_Policy::can_transition( 'queued', 'ready_for_assistance' ), 'policy allows the operator to skip review' );
tvq_assert( ! in_array( 'booked', Tra_Vel_Quote_Case_Policy::statuses(), true ), 'quote policy claims booking execution' );

// A lost first response must not orphan the committed case. The same private
// run rotates ownership to a replacement HttpOnly cookie without duplicating
// the aggregate.
tvq_reset_runtime();
list( $recovery_controller, $recovery_store, $recovery_run, $recovery_run_token ) = tvq_fixture( 10 );
list( $first_recovery_response, $lost_owner_token ) = tvq_create_guest_case( $recovery_controller, $recovery_run, $recovery_run_token );
$recovery_case_id = $first_recovery_response->data['case']['case_id'];
unset( $_COOKIE['__Host-tra_vel_quote_owner'] );
$recovered = $recovery_controller->create_case( tvq_request( $recovery_run, array( 'idempotency_key' => 'quote-create-recovery-0001' ) ) );
tvq_assert_private( $recovered, 'lost-cookie quote recovery' );
$replacement_token = tvq_extract_cookie_token( $recovered->headers['Set-Cookie'] ?? '', '__Host-tra_vel_quote_owner' );
tvq_assert( $recovery_case_id === $recovered->data['case']['case_id'] && 2 === $recovered->data['case']['version'], 'lost-cookie recovery duplicated the case or skipped its audit version' );
tvq_assert( strlen( $replacement_token ) >= 32 && ! hash_equals( $lost_owner_token, $replacement_token ), 'lost-cookie recovery did not rotate to a replacement protected owner' );
tvq_assert( 1 === count( $recovery_store->cases ) && 2 === count( $recovery_store->events[ $recovery_case_id ] ), 'lost-cookie recovery duplicated storage or omitted its event' );

// A still-valid AgentRun cookie gets a small lost-response allowance, but it
// cannot rotate quote ownership or grow recovery/idempotency events forever.
tvq_reset_runtime();
list( $bounded_controller, $bounded_store, $bounded_run, $bounded_run_token ) = tvq_fixture( 12 );
$_COOKIE['__Host-tra_vel_agent_run'] = $bounded_run['run_uuid'] . '.' . $bounded_run_token;
$bounded_case_id = '';
for ( $attempt = 1; $attempt <= 4; $attempt++ ) {
	unset( $_COOKIE['__Host-tra_vel_quote_owner'] );
	$bounded_response = $bounded_controller->create_case( tvq_request( $bounded_run, array( 'idempotency_key' => 'quote-create-bounded-000' . $attempt ) ) );
	tvq_assert_private( $bounded_response, 'bounded quote recovery attempt ' . $attempt );
	$bounded_case_id = (string) $bounded_response->data['case']['case_id'];
}
$bounded_before = $bounded_store->get_case_by_uuid( $bounded_case_id );
$bounded_event_count = count( $bounded_store->events[ $bounded_case_id ] );
unset( $_COOKIE['__Host-tra_vel_quote_owner'] );
$bounded_rejected = $bounded_controller->create_case( tvq_request( $bounded_run, array( 'idempotency_key' => 'quote-create-bounded-0005' ) ) );
tvq_assert_error( $bounded_rejected, 'tra_vel_quote_case_rate_limited', 'fifth source-run quote recovery attempt' );
$bounded_after = $bounded_store->get_case_by_uuid( $bounded_case_id );
tvq_assert( 4 === $bounded_store->create_calls, 'exhausted quote recovery reached durable create/idempotency storage' );
tvq_assert( $bounded_before['case_version'] === $bounded_after['case_version'] && $bounded_before['owner_token_hash'] === $bounded_after['owner_token_hash'], 'exhausted quote recovery rotated the protected owner' );
tvq_assert( $bounded_event_count === count( $bounded_store->events[ $bounded_case_id ] ), 'exhausted quote recovery appended an event' );

// An account-owned case must never remain bearer-accessible merely because
// the same browser still has an older guest owner cookie.
tvq_reset_runtime();
$account_agent = new Tra_Vel_Test_Quote_Agent_Store();
$account_seed  = $account_agent->seed_ready_run( 11 );
$account_run   = $account_seed['run'];
$account_run['owner_user_id'] = 77;
$account_agent->runs[ $account_run['run_uuid'] ] = $account_run;
$account_store      = new Tra_Vel_Test_Quote_Case_Store();
$account_controller = new Tra_Vel_Quote_Case_Controller( $account_store, $account_agent );
$GLOBALS['tvq_current_user'] = 77;
$_COOKIE['__Host-tra_vel_quote_owner'] = 'existing-guest-owner-token-abcdefghijklmnopqrstuvwxyz0123456789';
$account_created = $account_controller->create_case( tvq_request( $account_run, array( 'idempotency_key' => 'quote-create-account-0001' ) ) );
tvq_assert_private( $account_created, 'account quote create' );
tvq_assert( true === $account_created->data['case']['resume_available'], 'live exact-owner account source was not resumable' );
$account_case_id = $account_created->data['case']['case_id'];
$account_raw_case = $account_store->get_case_by_uuid( $account_case_id );
tvq_assert( 77 === $account_raw_case['owner_user_id'] && '' === $account_raw_case['owner_token_hash'], 'account quote creation retained bearer ownership' );
$GLOBALS['tvq_current_user'] = 0;
$logged_out_access = $account_controller->can_access_case( new WP_REST_Request( array( 'case_id' => $account_case_id ) ) );
tvq_assert_error( $logged_out_access, 'tra_vel_quote_case_forbidden', 'logged-out bearer access to an account quote' );

// Guest creation: run ownership, fresh request binding, explicit consent,
// protected quote ownership, idempotent replay, list/read, and denial paths.
tvq_reset_runtime();
list( $controller, $case_store, $run, $run_token, $source_agent_store ) = tvq_fixture( 1 );
$controller->register_routes();
tvq_assert( 13 === count( $GLOBALS['tvq_registered_routes'] ), 'quote controller route count changed unexpectedly' );

$_COOKIE['__Host-tra_vel_agent_run'] = $run['run_uuid'] . '.wrong-run-token';
$wrong_run = $controller->can_create_case( tvq_request( $run ) );
tvq_assert_error( $wrong_run, 'tra_vel_agent_run_forbidden', 'wrong private run owner' );
$_COOKIE['__Host-tra_vel_agent_run'] = $run['run_uuid'] . '.' . $run_token;
tvq_assert( true === $controller->can_create_case( tvq_request( $run ) ), 'correct private run owner was rejected' );

$stale = $controller->create_case( tvq_request( $run, array( 'expected_revision' => 2 ) ) );
tvq_assert_error( $stale, 'tra_vel_quote_case_request_changed', 'stale expected request' );
tvq_assert( 0 === $case_store->create_calls, 'stale request reached durable case creation' );

$no_consent = $controller->create_case( tvq_request( $run, array( 'consent' => false ) ) );
tvq_assert_error( $no_consent, 'tra_vel_quote_case_consent_required', 'missing explicit consent' );
tvq_assert( 0 === $case_store->create_calls, 'missing consent reached durable case creation' );

$GLOBALS['tvq_fired_actions'] = array();
list( $created, $owner_token ) = tvq_create_guest_case( $controller, $run, $run_token );
$case_id = $created->data['case']['case_id'];
tvq_assert_public_contract( $created->data['case'], 'created quote case' );
tvq_assert( true === $created->data['case']['resume_available'], 'live exact-owner guest source was not resumable' );
tvq_assert( false === $created->data['replayed'], 'first quote create was marked as replay' );
$tvq_created_announcements = tvq_fired( 'tra_vel_quote_case_created' );
tvq_assert(
	1 === count( $tvq_created_announcements )
		&& $case_id === ( $tvq_created_announcements[0]['args'][0] ?? '' )
		&& $created->data['case']['reference'] === ( $tvq_created_announcements[0]['args'][1] ?? '' )
		&& is_array( $tvq_created_announcements[0]['args'][2] ?? null )
		&& 'queued' === ( $tvq_created_announcements[0]['args'][2]['status'] ?? '' ),
	'a committed quote case did not announce exactly one post-commit creation event with opaque identifiers'
);

$replay = $controller->create_case( tvq_request( $run ) );
tvq_assert( 1 === count( tvq_fired( 'tra_vel_quote_case_created' ) ), 'an idempotent quote-create replay announced a duplicate creation event' );
tvq_assert_private( $replay, 'idempotent quote replay' );
tvq_assert( 200 === $replay->status && true === $replay->data['replayed'], 'idempotent quote replay changed state' );
tvq_assert( 1 === count( $case_store->cases ) && 1 === count( $created->data['case']['events'] ), 'idempotent replay duplicated a case or creation event' );

$source_agent_store->resume_read_calls = 0;
$owned = $controller->list_owned_cases( new WP_REST_Request( array( 'per_page' => 30 ) ) );
tvq_assert_private( $owned, 'owned quote list' );
tvq_assert( 1 === count( $owned->data['cases'] ) && 'private_browser_owner' === $owned->data['meta']['storage'], 'guest-owned list lost its private case' );
tvq_assert( 1 === $source_agent_store->resume_read_calls && true === $owned->data['cases'][0]['resume_available'], 'quote list used N+1 source reads or lost live resume truth' );
$case_store->list_read_error = 'simulated quote list failure';
$failed_owned_list = $controller->list_owned_cases( new WP_REST_Request( array( 'per_page' => 30 ) ) );
tvq_assert_error( $failed_owned_list, 'tra_vel_quote_case_list_read_failed', 'quote list database uncertainty' );
tvq_assert( 503 === $failed_owned_list->get_error_data()['status'], 'quote list database uncertainty did not return 503' );
$case_store->list_read_error = '';
$read_request = new WP_REST_Request( array( 'case_id' => $case_id ) );
tvq_assert( true === $controller->can_access_case( $read_request ), 'correct guest quote owner was rejected' );
$read = $controller->get_case( $read_request );
tvq_assert_private( $read, 'owned quote read' );
tvq_assert_public_contract( $read->data['case'], 'owned quote read' );

// Resume truth is derived from the current source AgentRun on every traveler
// projection and fails false for expiry, absence, owner mismatch, or read error.
$source_run_snapshot = $source_agent_store->runs[ $run['run_uuid'] ];
$source_agent_store->runs[ $run['run_uuid'] ]['expires_at'] = gmdate( 'Y-m-d H:i:s', time() - 60 );
$expired_source = $controller->get_case( $read_request );
tvq_assert( false === $expired_source->data['case']['resume_available'], 'expired source AgentRun remained resumable' );
unset( $source_agent_store->runs[ $run['run_uuid'] ] );
$missing_source = $controller->get_case( $read_request );
tvq_assert( false === $missing_source->data['case']['resume_available'], 'missing source AgentRun remained resumable' );
$source_agent_store->runs[ $run['run_uuid'] ] = $source_run_snapshot;
$source_agent_store->runs[ $run['run_uuid'] ]['owner_user_id'] = 77;
$source_agent_store->runs[ $run['run_uuid'] ]['owner_token_hash'] = '';
$wrong_source_owner = $controller->get_case( $read_request );
tvq_assert( false === $wrong_source_owner->data['case']['resume_available'], 'wrong-owner source AgentRun remained resumable' );
$source_agent_store->runs[ $run['run_uuid'] ] = $source_run_snapshot;
$source_agent_store->resume_read_error = 'simulated source read failure';
$uncertain_source = $controller->get_case( $read_request );
tvq_assert( false === $uncertain_source->data['case']['resume_available'], 'source read uncertainty did not fail resume false' );
$source_agent_store->resume_read_error = '';

$_COOKIE['__Host-tra_vel_quote_owner'] = 'wrong-owner-token-abcdefghijklmnopqrstuvwxyz0123456789';
$wrong_guest = $controller->can_access_case( $read_request );
tvq_assert_error( $wrong_guest, 'tra_vel_quote_case_forbidden', 'wrong guest quote owner' );
$wrong_list = $controller->list_owned_cases( new WP_REST_Request( array( 'per_page' => 30 ) ) );
tvq_assert( array() === $wrong_list->data['cases'], 'wrong guest token listed another traveler quote case' );
$_COOKIE['__Host-tra_vel_quote_owner'] = $owner_token;

// Handoff is fail-closed until a verified owned provider returns an allowlisted
// HTTPS URL. Rejected preparations must not create audit events.
$handoff_request = new WP_REST_Request(
	array(
		'case_id'          => $case_id,
		'expected_version' => 1,
		'idempotency_key'  => 'handoff-prepare-00000001',
		'channel'          => 'whatsapp',
	)
);
$unavailable = $controller->prepare_handoff( $handoff_request );
tvq_assert_error( $unavailable, 'tra_vel_quote_case_handoff_unavailable', 'unconfigured assisted handoff' );
tvq_assert( 0 === $case_store->handoff_calls && 1 === count( $case_store->events[ $case_id ] ), 'unconfigured handoff recorded a false event' );

$GLOBALS['tvq_handoff_filter'] = static function () {
	return array( 'handoff_url' => 'https://evil.example/send', 'provider' => 'tra-vel-concierge', 'expires_at' => '2030-04-01T10:10:00Z' );
};
$rejected_handoff = $controller->prepare_handoff( $handoff_request );
tvq_assert_error( $rejected_handoff, 'tra_vel_quote_case_handoff_rejected', 'non-allowlisted assisted handoff' );
tvq_assert( 0 === $case_store->handoff_calls && 1 === count( $case_store->events[ $case_id ] ), 'rejected handoff recorded a false event' );

$GLOBALS['tvq_handoff_filter'] = static function ( $value, $context, $public_case ) {
	unset( $value );
	$GLOBALS['tvq_handoff_context'] = $context;
	tvq_assert( $public_case['reference'] === $context['reference'], 'handoff filter received mismatched case identity' );
	return array(
		'handoff_url' => 'https://api.whatsapp.com/send?phone=972525101555&text=' . rawurlencode( $context['reference'] ),
		'provider'    => 'tra-vel-concierge',
		'expires_at'  => '2030-04-01T10:10:00Z',
	);
};
$prepared = $controller->prepare_handoff( $handoff_request );
tvq_assert_private( $prepared, 'owned assisted handoff' );
tvq_assert( 0 === strpos( $prepared->data['handoff_url'], 'https://api.whatsapp.com/send?' ), 'owned handoff changed its allowlisted host' );
tvq_assert( 1 === $case_store->handoff_calls && 2 === $prepared->data['case']['version'], 'owned handoff was not recorded exactly once' );
tvq_assert( 'handoff.prepared' === $prepared->data['event']['type'] && false === $prepared->data['event']['data']['dispatched'], 'handoff event falsely claims a sent message' );
tvq_assert( hash( 'sha256', $prepared->data['handoff_url'] ) === $prepared->data['event']['data']['target_digest'], 'handoff event is not bound to the exact returned URL' );
tvq_assert( 'TV-RT000001' === $GLOBALS['tvq_handoff_context']['reference'], 'handoff lost the opaque case reference' );

// Every mutation is optimistic: stale versions fail without another event.
$stale_cancel = $controller->cancel_case( new WP_REST_Request( array( 'case_id' => $case_id, 'expected_version' => 1, 'idempotency_key' => 'quote-cancel-000000001' ) ) );
tvq_assert_error( $stale_cancel, 'tra_vel_quote_case_version_conflict', 'stale quote cancellation' );
tvq_assert( 2 === count( $case_store->events[ $case_id ] ), 'stale cancellation appended an event' );
$cancelled = $controller->cancel_case( new WP_REST_Request( array( 'case_id' => $case_id, 'expected_version' => 2, 'idempotency_key' => 'quote-cancel-000000002' ) ) );
tvq_assert_private( $cancelled, 'quote cancellation' );
tvq_assert( 'cancelled' === $cancelled->data['case']['status'] && 3 === $cancelled->data['case']['version'], 'valid cancellation did not settle the case' );
tvq_assert_public_contract( $cancelled->data['case'], 'cancelled quote case' );

// Claiming a guest case requires both a signed-in reader and the matching guest
// owner token. The case then follows the account without exposing either token.
tvq_reset_runtime();
list( $claim_controller, $claim_store, $claim_run, $claim_run_token ) = tvq_fixture( 2 );
list( $claim_created, $claim_owner_token ) = tvq_create_guest_case( $claim_controller, $claim_run, $claim_run_token );
$claim_case_id = $claim_created->data['case']['case_id'];
$claim_request = new WP_REST_Request( array( 'case_id' => $claim_case_id, 'expected_version' => 1, 'idempotency_key' => 'quote-claim-0000000001' ) );
$anonymous_claim = $claim_controller->can_claim_case( $claim_request );
tvq_assert_error( $anonymous_claim, 'tra_vel_quote_case_login_required', 'anonymous quote claim' );

$GLOBALS['tvq_current_user'] = 44;
$_COOKIE['__Host-tra_vel_quote_owner'] = 'wrong-owner-token-abcdefghijklmnopqrstuvwxyz0123456789';
$wrong_claim = $claim_controller->can_claim_case( $claim_request );
tvq_assert_error( $wrong_claim, 'tra_vel_quote_case_claim_forbidden', 'wrong-token account claim' );
$_COOKIE['__Host-tra_vel_quote_owner'] = $claim_owner_token;
$claim_raw_case = $claim_store->get_case_by_uuid( $claim_case_id );
tvq_assert( hash_equals( $claim_raw_case['owner_token_hash'], hash( 'sha256', $claim_owner_token ) ), 'claim owner cookie no longer matches its durable hash' );
tvq_assert( 1 === preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $claim_owner_token ), 'claim owner cookie is rejected by the controller token grammar' );
$claim_permission = $claim_controller->can_claim_case( $claim_request );
tvq_assert( true === $claim_permission, 'signed-in guest owner could not claim the case' . ( is_wp_error( $claim_permission ) ? ': ' . $claim_permission->get_error_code() : '' ) );
$claimed = $claim_controller->claim_case( $claim_request );
tvq_assert_private( $claimed, 'signed-in quote claim' );
tvq_assert( 2 === $claimed->data['case']['version'] && 'quote_case.claimed' === $claimed->data['event']['type'], 'quote claim did not produce one versioned event' );
unset( $_COOKIE['__Host-tra_vel_quote_owner'] );
tvq_assert( true === $claim_controller->can_access_case( new WP_REST_Request( array( 'case_id' => $claim_case_id ) ) ), 'claimed case did not follow its account owner' );
$GLOBALS['tvq_current_user'] = 45;
$other_account = $claim_controller->can_access_case( new WP_REST_Request( array( 'case_id' => $claim_case_id ) ) );
tvq_assert_error( $other_account, 'tra_vel_quote_case_forbidden', 'different account quote read' );

// Operator queue permissions are distinct: viewers can inspect, managers can
// apply only legal, version-bound transitions.
$GLOBALS['tvq_current_user'] = 51;
tvq_assert( false === $claim_controller->can_view_queue() && false === $claim_controller->can_manage_queue(), 'ordinary account gained operator capabilities' );
$GLOBALS['tvq_capabilities'][901] = array( 'tra_vel_view_quote_cases' => true );
$GLOBALS['tvq_current_user'] = 901;
tvq_assert( true === $claim_controller->can_view_queue() && false === $claim_controller->can_manage_queue(), 'view-only operator gained mutation authority' );
$operator_list = $claim_controller->list_operator_cases( new WP_REST_Request( array( 'status' => '', 'page' => 1, 'per_page' => 30 ) ) );
tvq_assert_private( $operator_list, 'operator quote list' );
tvq_assert( 1 === $operator_list->data['meta']['total'], 'operator queue omitted the persistent case' );
$operator_case = $operator_list->data['cases'][0];
tvq_assert( isset( $operator_case['case_revision'], $operator_case['assigned_user_id'], $operator_case['consent_version'], $operator_case['consented_at'], $operator_case['allowed_transitions'] ) && (int) $claim_store->cases[ $claim_case_id ]['current_revision'] === (int) $operator_case['case_revision'], 'operator projection lacks the exact quote-case revision or operational metadata' );
tvq_assert( ! isset( $operator_case['owner_token_hash'], $operator_case['snapshot'] ), 'operator projection leaked owner token or raw snapshot' );
$operator_events = $claim_controller->get_operator_case_events( new WP_REST_Request( array( 'case_id' => $claim_case_id, 'after' => 0, 'limit' => 50 ) ) );
tvq_assert_private( $operator_events, 'operator quote event page' );
tvq_assert( is_array( $operator_events->data['events'] ) && false === $operator_events->data['has_more'], 'operator event route lost bounded cursor metadata' );

$GLOBALS['tvq_capabilities'][900] = array( 'tra_vel_view_quote_cases' => true, 'tra_vel_manage_quote_cases' => true );
$GLOBALS['tvq_current_user'] = 900;
tvq_assert( true === $claim_controller->can_manage_queue(), 'authorized operator cannot manage quote cases' );
$transition = $claim_controller->transition_case(
	new WP_REST_Request(
		array(
			'case_id'          => $claim_case_id,
			'status'           => 'in_review',
			'expected_version' => 2,
			'idempotency_key'  => 'quote-transition-000001',
		)
	)
);
tvq_assert_private( $transition, 'operator quote transition' );
tvq_assert( 'in_review' === $transition->data['case']['status'] && 3 === $transition->data['case']['version'], 'authorized operator transition did not persist' );
tvq_assert( 'operator' === $transition->data['event']['actor_type'] && 'operator' === $transition->data['event']['source'], 'operator transition lost human provenance' );
tvq_assert( 900 === $claim_store->cases[ $claim_case_id ]['assigned_user_id'], 'in-review case was not assigned to the acting operator' );
tvq_assert( ! isset( $transition->data['booking'], $transition->data['reservation'], $transition->data['price'] ), 'assisted transition invented a transaction' );

// Lead capture: acquisition attribution and the explicitly consented contact
// are policy-bounded, stored with the case, surfaced to operators only, and
// never echoed into traveler payloads. Contact without provable consent fails
// closed before any durable write.
$rejected_contact_policy = Tra_Vel_Quote_Case_Policy::sanitize_contact( array( 'name' => 'דנה', 'phone' => '052-510-1555' ) );
tvq_assert_error( $rejected_contact_policy, 'tra_vel_quote_case_contact_consent_required', 'contact without explicit consent' );
$stale_contact_policy = Tra_Vel_Quote_Case_Policy::sanitize_contact( array( 'phone' => '0525101555', 'consent' => true, 'consent_version' => '2026-01-01' ) );
tvq_assert_error( $stale_contact_policy, 'tra_vel_quote_case_contact_consent_required', 'contact with a stale consent version' );
$bad_phone_policy = Tra_Vel_Quote_Case_Policy::sanitize_contact( array( 'phone' => '12ab34', 'consent' => true, 'consent_version' => Tra_Vel_Quote_Case_Policy::CONTACT_CONSENT_VERSION ) );
tvq_assert_error( $bad_phone_policy, 'tra_vel_quote_case_contact_phone_invalid', 'invalid callback phone' );
tvq_assert( '+972525101555' === Tra_Vel_Quote_Case_Policy::normalize_phone( '+972 52-510-1555' ), 'phone normalization lost its international format' );
tvq_assert( '' === Tra_Vel_Quote_Case_Policy::normalize_phone( '123456' ), 'a six-digit fragment passed phone normalization' );
$lead_acquisition = Tra_Vel_Quote_Case_Policy::sanitize_acquisition(
	array(
		'utm_source'    => 'google',
		'utm_medium'    => 'cpc',
		'utm_campaign'  => 'thailand-winter',
		'utm_term'      => str_repeat( 'k', 200 ),
		'utm_content'   => 'https://evil.example/track',
		'landing_path'  => '/deals/thailand/?utm_source=google',
		'referrer_host' => 'https://www.google.com/search?q=x',
		'first_seen_at' => '2026-07-19T08:30:00Z',
		'session_id'    => 'must-be-stripped',
		'client_ip'     => '10.0.0.1',
	)
);
tvq_assert( 'google' === ( $lead_acquisition['utm_source'] ?? '' ) && 'thailand-winter' === ( $lead_acquisition['utm_campaign'] ?? '' ), 'bounded acquisition lost safe campaign fields' );
tvq_assert( isset( $lead_acquisition['utm_term'] ) && strlen( $lead_acquisition['utm_term'] ) <= 120, 'acquisition utm_term escaped its 120-character bound' );
tvq_assert( ! isset( $lead_acquisition['utm_content'] ), 'a URL-bearing utm_content escaped sensitive-pattern redaction' );
tvq_assert( ! isset( $lead_acquisition['session_id'] ) && ! isset( $lead_acquisition['client_ip'] ), 'unknown acquisition keys were not stripped' );
tvq_assert( 'www.google.com' === ( $lead_acquisition['referrer_host'] ?? '' ), 'referrer URL was not reduced to its bare host' );
tvq_assert( '/deals/thailand/?utm_source=google' === ( $lead_acquisition['landing_path'] ?? '' ), 'a safe same-site landing path was not preserved' );
tvq_assert( '2026-07-19T08:30:00+00:00' === ( $lead_acquisition['first_seen_at'] ?? '' ), 'first_seen_at was not normalized to UTC date-time' );
tvq_assert( ! isset( Tra_Vel_Quote_Case_Policy::sanitize_acquisition( array( 'landing_path' => 'https://evil.example/x' ) )['landing_path'] ), 'an absolute landing URL escaped the same-site path bound' );
tvq_assert( array() === Tra_Vel_Quote_Case_Policy::sanitize_acquisition( array( 'utm_source' => '', 'unknown' => 'x' ) ), 'an empty acquisition object did not collapse to nothing stored' );

tvq_reset_runtime();
list( $lead_controller, $lead_store, $lead_run, $lead_run_token ) = tvq_fixture( 3 );
$_COOKIE['__Host-tra_vel_agent_run'] = $lead_run['run_uuid'] . '.' . $lead_run_token;
$unconsented_contact = $lead_controller->create_case( tvq_request( $lead_run, array( 'idempotency_key' => 'quote-create-lead-000001', 'contact' => array( 'name' => 'דנה לוי', 'phone' => '0525101555' ) ) ) );
tvq_assert_error( $unconsented_contact, 'tra_vel_quote_case_contact_consent_required', 'quote contact without consent' );
tvq_assert( 0 === $lead_store->create_calls, 'an unconsented contact reached durable case creation' );
$lead_response = $lead_controller->create_case(
	tvq_request(
		$lead_run,
		array(
			'idempotency_key' => 'quote-create-lead-000002',
			'acquisition'     => array( 'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'thailand-winter', 'landing_path' => '/deals/thailand/', 'referrer_host' => 'www.google.com', 'first_seen_at' => '2026-07-19T08:30:00Z', 'client_ip' => '10.0.0.1' ),
			'contact'         => array( 'name' => 'דנה לוי', 'phone' => '+972 52-510-1555', 'consent' => true, 'consent_version' => Tra_Vel_Quote_Case_Policy::CONTACT_CONSENT_VERSION ),
		)
	)
);
tvq_assert_private( $lead_response, 'lead-capture quote create' );
tvq_assert_public_contract( $lead_response->data['case'], 'lead-capture quote case' );
$lead_case_id     = $lead_response->data['case']['case_id'];
$lead_public_json = (string) wp_json_encode( $lead_response->data, JSON_UNESCAPED_UNICODE );
tvq_assert( false === strpos( $lead_public_json, '525101555' ) && false === strpos( $lead_public_json, 'דנה' ) && false === strpos( $lead_public_json, 'acquisition' ), 'traveler payload leaked lead contact or acquisition data' );
$stored_lead_case = $lead_store->get_case_by_uuid( $lead_case_id );
tvq_assert( 'google' === ( $stored_lead_case['acquisition']['utm_source'] ?? '' ) && '+972525101555' === ( $stored_lead_case['contact']['phone'] ?? '' ), 'the durable case did not retain bounded lead capture' );
tvq_assert( Tra_Vel_Quote_Case_Policy::CONTACT_CONSENT_VERSION === ( $stored_lead_case['contact']['consent_version'] ?? '' ), 'the stored contact lost its consent version evidence' );
tvq_assert( ! isset( $stored_lead_case['acquisition']['client_ip'] ), 'an unknown acquisition key reached durable storage' );

$GLOBALS['tvq_capabilities'][902] = array( 'tra_vel_view_quote_cases' => true );
$GLOBALS['tvq_current_user']      = 902;
$lead_operator_list = $lead_controller->list_operator_cases( new WP_REST_Request( array( 'status' => '', 'page' => 1, 'per_page' => 30 ) ) );
tvq_assert_private( $lead_operator_list, 'lead-capture operator list' );
$lead_operator_case = $lead_operator_list->data['cases'][0];
tvq_assert( 'google' === ( $lead_operator_case['acquisition']['utm_source'] ?? '' ) && 'thailand-winter' === ( $lead_operator_case['acquisition']['utm_campaign'] ?? '' ), 'the operator queue lost acquisition attribution' );
tvq_assert( '+972525101555' === ( $lead_operator_case['contact']['phone'] ?? '' ) && 'דנה לוי' === ( $lead_operator_case['contact']['name'] ?? '' ), 'the operator queue lost the consented lead contact' );
$lead_operator_single = $lead_controller->get_operator_case( new WP_REST_Request( array( 'case_id' => $lead_case_id ) ) );
tvq_assert_private( $lead_operator_single, 'lead-capture operator detail' );
tvq_assert( '+972525101555' === ( $lead_operator_single->data['case']['contact']['phone'] ?? '' ) && 'google' === ( $lead_operator_single->data['case']['acquisition']['utm_source'] ?? '' ), 'the operator case detail lost lead capture' );
$lead_plain_case = $lead_store->get_case_by_uuid( $lead_case_id );
$lead_plain_case['acquisition'] = array();
$lead_plain_case['contact']     = array();
$lead_store->cases[ $lead_case_id ] = $lead_plain_case;
$lead_absent_single = $lead_controller->get_operator_case( new WP_REST_Request( array( 'case_id' => $lead_case_id ) ) );
tvq_assert( null === $lead_absent_single->data['case']['acquisition'] && null === $lead_absent_single->data['case']['contact'], 'an absent lead capture was not presented as an explicit null' );
$GLOBALS['tvq_current_user'] = 0;

echo "Tra-Vel quote case runtime validation passed (cross-store readiness, bounded recovery, monotonic sync, read-error retry, transactional retention, private ownership, handoff, account claim, operator gates, and consent-gated lead capture).\n";
