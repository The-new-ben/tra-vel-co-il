<?php
/**
 * Adversarial no-database runtime for VIP capability-session exchange.
 */

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['tvcs_routes'] = array();
$GLOBALS['tvcs_user_id'] = 0;
$GLOBALS['tvcs_upstream'] = false;
$GLOBALS['tvcs_options'] = array();
$_SERVER['REMOTE_ADDR'] = '203.0.113.42';

class WP_REST_Controller { protected $namespace; protected $rest_base; }
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; }
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
	private $params;
	private $headers;
	public function __construct( $params = array(), $headers = array() ) { $this->params = $params; $this->headers = array_change_key_case( $headers, CASE_LOWER ); }
	public function get_param( $key ) { return $this->params[ $key ] ?? null; }
	public function get_params() { return $this->params; }
	public function get_json_params() { return $this->params; }
	public function get_header( $name ) { return $this->headers[ strtolower( $name ) ] ?? ''; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_salt( $scheme = '' ) { return 'vip-capability-runtime-salt-' . $scheme; }
function wp_generate_password( $length ) { return str_repeat( 'G', (int) $length ); }
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['tvcs_routes'][ $namespace . $route ] = $args; }
function get_current_user_id() { return (int) $GLOBALS['tvcs_user_id']; }
function wp_verify_nonce( $nonce, $action ) { return 'vip-capability-runtime-nonce' === $nonce && 'wp_rest' === $action; }
function home_url( $path = '/' ) { unset( $path ); return 'https://tra-vel.co.il/'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function wp_unslash( $value ) { return $value; }
function current_time() { return gmdate( 'Y-m-d H:i:s' ); }
function absint( $value ) { return abs( (int) $value ); }
function rest_validate_request_arg() { return true; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['tvcs_options'] ) ? $GLOBALS['tvcs_options'][ $key ] : $default; }
function update_option( $key, $value ) { $GLOBALS['tvcs_options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['tvcs_options'][ $key ] ); return true; }
function apply_filters( $hook, $value ) {
	if ( in_array( $hook, array( 'tra_vel_vip_capability_grant_issuable', 'tra_vel_vip_capability_grant_mutation_authorized' ), true ) ) {
		return (bool) $GLOBALS['tvcs_upstream'];
	}
	return $value;
}

class TVCS_Health_WPDB {
	public $prefix = 'wp_';
	public $inspection_calls = 0;
	private $suppressed = false;

	public function suppress_errors( $suppress = null ) {
		$prior = $this->suppressed;
		if ( null !== $suppress ) $this->suppressed = (bool) $suppress;
		return $prior;
	}

	public function prepare( $query ) { return (string) $query; }

	public function get_col( $sql ) {
		$this->inspection_calls++;
		$table = $this->table_from_sql( $sql );
		$columns = array(
			'wp_tra_vel_vip_capability_grants' => array( 'id', 'capability_ref', 'capability_digest', 'trip_ref', 'case_ref', 'account_ref', 'issue_reason', 'channel', 'allowed_scopes', 'disclosure_classes', 'rotation_generation', 'mutation_operation', 'mutation_reason_code', 'mutation_previous_generation', 'mutation_sessions_revoked', 'mutated_at', 'issued_at', 'expires_at', 'consumed_at', 'revoked_at', 'retention_until' ),
			'wp_tra_vel_vip_capability_sessions' => array( 'id', 'session_ref', 'session_digest', 'grant_id', 'capability_ref', 'capability_digest', 'trip_ref', 'case_ref', 'account_ref', 'allowed_scopes', 'disclosure_classes', 'rotation_generation', 'state', 'created_at', 'expires_at', 'revoked_at', 'retention_until' ),
			'wp_tra_vel_vip_capability_exchanges' => array( 'id', 'grant_id', 'idempotency_key_hash', 'request_digest', 'session_id', 'created_at', 'expires_at' ),
			'wp_tra_vel_vip_capability_limits' => array( 'limit_key', 'hits', 'expires_at' ),
		);
		return $columns[ $table ] ?? array();
	}

	public function get_row() { $this->inspection_calls++; return array( 'Engine' => 'InnoDB' ); }

	public function get_results( $sql ) {
		$this->inspection_calls++;
		$table = $this->table_from_sql( $sql );
		$indexes = array(
			'wp_tra_vel_vip_capability_grants' => array( array( 'capability_ref' ), array( 'capability_digest' ) ),
			'wp_tra_vel_vip_capability_sessions' => array( array( 'session_ref' ), array( 'session_digest' ), array( 'grant_id' ) ),
			'wp_tra_vel_vip_capability_exchanges' => array( array( 'grant_id', 'idempotency_key_hash' ) ),
			'wp_tra_vel_vip_capability_limits' => array( array( 'limit_key' ) ),
		);
		$rows = array();
		foreach ( $indexes[ $table ] ?? array() as $number => $columns ) {
			foreach ( $columns as $sequence => $column ) {
				$rows[] = array( 'Non_unique' => 0, 'Key_name' => 'unique_' . $number, 'Seq_in_index' => $sequence + 1, 'Column_name' => $column );
			}
		}
		return $rows;
	}

	private function table_from_sql( $sql ) {
		return preg_match( '/`([^`]+)`/', (string) $sql, $match ) ? $match[1] : '';
	}
}

$GLOBALS['wpdb'] = new TVCS_Health_WPDB();

$vip = dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/includes/vip/';
require_once $vip . 'class-tra-vel-vip-taxonomy.php';
require_once $vip . 'class-tra-vel-vip-policy.php';
require_once $vip . 'class-tra-vel-vip-capability-session-policy.php';
require_once $vip . 'class-tra-vel-vip-capability-session-store.php';

class TVCS_Memory_Store extends Tra_Vel_VIP_Capability_Session_Store {
	public static $ready = true;
	public static $last_instance = null;
	public $grants = array();
	public $sessions = array();
	public $idempotency = array();
	public $mutations = array();
	public $events = array();
	public $limit_mode = 'allow';

	public function __construct() { self::$last_instance = $this; }

	public static function is_ready() {
		if ( self::$last_instance instanceof self ) self::$last_instance->events[] = 'readiness';
		return self::$ready;
	}

	protected function persist_grant( $grant ) {
		$this->grants[ $grant['capability_digest'] ] = $grant;
		return true;
	}

	public function exchange( $exchange_value, $idempotency_key, $request_digest, $now = null ) {
		$this->events[] = 'grant_lookup';
		$digest = self::capability_digest( $exchange_value );
		$key = $digest . '|' . self::idempotency_key_hash( $idempotency_key );
		$timestamp = null === $now ? time() : (int) $now;
		if ( isset( $this->idempotency[ $key ] ) ) {
			$prior = $this->idempotency[ $key ];
			if ( ! hash_equals( $prior['request_digest'], $request_digest ) ) {
				return new WP_Error( 'tra_vel_vip_capability_idempotency_conflict', 'conflict', array( 'status' => 409 ) );
			}
			$session = $this->sessions[ $prior['session_digest'] ] ?? null;
			if ( ! is_array( $session ) || 'active' !== $session['state'] || null !== $session['revoked_at'] || strtotime( $session['expires_at'] ) <= $timestamp ) {
				return new WP_Error( 'tra_vel_vip_capability_exchange_unavailable', 'unavailable', array( 'status' => 403 ) );
			}
			return array( 'session' => $session, 'session_value' => $prior['session_value'], 'created' => false, 'replayed' => true );
		}
		$grant = $this->grants[ $digest ] ?? null;
		if ( ! is_array( $grant ) || null !== $grant['consumed_at'] || null !== $grant['revoked_at'] || strtotime( $grant['expires_at'] ) <= $timestamp ) {
			return new WP_Error( 'tra_vel_vip_capability_exchange_unavailable', 'unavailable', array( 'status' => 403 ) );
		}
		$material = implode( '|', array( $exchange_value, $idempotency_key, $request_digest, $grant['rotation_generation'] ) );
		$session_value = rtrim( strtr( base64_encode( hash_hmac( 'sha256', $material, wp_salt( 'secure_auth' ), true ) ), '+/', '-_' ), '=' );
		$session_digest = self::session_digest( $session_value );
		$expires = min( strtotime( $grant['expires_at'] ), $timestamp + Tra_Vel_VIP_Capability_Session_Policy::SESSION_TTL_SECONDS );
		$session = array(
			'contract_version' => '1.0.0',
			'session_ref' => 'tv_capability_session_' . substr( hash( 'sha256', $session_digest ), 0, 24 ),
			'session_digest' => $session_digest,
			'capability_ref' => $grant['capability_ref'],
			'capability_digest' => $grant['capability_digest'],
			'trip_ref' => $grant['trip_ref'],
			'case_ref' => $grant['case_ref'],
			'account_ref' => $grant['account_ref'],
			'allowed_scopes' => $grant['allowed_scopes'],
			'disclosure_classes' => $grant['disclosure_classes'],
			'rotation_generation' => $grant['rotation_generation'],
			'state' => 'active',
			'created_at' => gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ),
			'expires_at' => gmdate( 'Y-m-d\TH:i:s\Z', $expires ),
			'revoked_at' => null,
			'authorization_effect' => 'low_risk_capability_only',
			'data_boundary' => Tra_Vel_VIP_Capability_Session_Policy::safe_data_boundary(),
		);
		$valid = Tra_Vel_VIP_Capability_Session_Policy::session( $session, gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ) );
		if ( is_wp_error( $valid ) ) return $valid;
		$grant['consumed_at'] = gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
		$this->grants[ $digest ] = $grant;
		$this->sessions[ $session_digest ] = $session;
		$this->idempotency[ $key ] = array( 'request_digest' => $request_digest, 'session_digest' => $session_digest, 'session_value' => $session_value );
		return array( 'session' => $session, 'session_value' => $session_value, 'created' => true, 'replayed' => false );
	}

	public function current_session( $session_value, $now = null ) {
		$this->events[] = 'session_lookup';
		$digest = self::session_digest( $session_value );
		$session = $this->sessions[ $digest ] ?? null;
		$timestamp = null === $now ? time() : (int) $now;
		if ( ! is_array( $session ) || 'active' !== $session['state'] || null !== $session['revoked_at'] || strtotime( $session['expires_at'] ) <= $timestamp ) {
			return new WP_Error( 'tra_vel_vip_capability_session_missing', 'missing', array( 'status' => 404 ) );
		}
		$grant = $this->grants[ $session['capability_digest'] ] ?? null;
		if ( ! is_array( $grant ) || null !== $grant['revoked_at'] || (int) $grant['rotation_generation'] !== (int) $session['rotation_generation'] ) {
			return new WP_Error( 'tra_vel_vip_capability_session_missing', 'missing', array( 'status' => 404 ) );
		}
		return $session;
	}

	public function revoke_session( $session_value, $now = null ) {
		$this->events[] = 'session_revoke';
		$digest = self::session_digest( $session_value );
		if ( isset( $this->sessions[ $digest ] ) ) {
			$this->sessions[ $digest ]['state'] = 'revoked';
			$this->sessions[ $digest ]['revoked_at'] = gmdate( 'Y-m-d\TH:i:s\Z', null === $now ? time() : (int) $now );
		}
		return true;
	}

	public function revoke_server_grant( $capability_ref, $expected_generation, $reason_code, $now = null ) {
		return $this->mutate_grant( $capability_ref, $expected_generation, 'revoke', $reason_code, $now );
	}

	public function rotate_server_grant_generation( $capability_ref, $expected_generation, $reason_code, $now = null ) {
		return $this->mutate_grant( $capability_ref, $expected_generation, 'rotate', $reason_code, $now );
	}

	private function mutate_grant( $capability_ref, $expected_generation, $operation, $reason_code, $now ) {
		if ( ! $GLOBALS['tvcs_upstream'] ) {
			return new WP_Error( 'tra_vel_vip_capability_grant_mutation_not_authorized', 'not authorized', array( 'status' => 403 ) );
		}
		if ( ! is_int( $expected_generation ) || $expected_generation < 1 || $expected_generation > Tra_Vel_VIP_Capability_Session_Policy::MAX_ROTATION_GENERATION || ( 'rotate' === $operation && $expected_generation >= Tra_Vel_VIP_Capability_Session_Policy::MAX_ROTATION_GENERATION ) ) {
			return new WP_Error( 'tra_vel_vip_capability_grant_mutation_invalid', 'invalid', array( 'status' => 400 ) );
		}
		$digest = null;
		foreach ( $this->grants as $candidate_digest => $grant ) {
			if ( $grant['capability_ref'] === $capability_ref ) { $digest = $candidate_digest; break; }
		}
		if ( null === $digest ) {
			return new WP_Error( 'tra_vel_vip_capability_grant_generation_conflict', 'conflict', array( 'status' => 409 ) );
		}
		$prior = $this->mutations[ $digest ] ?? null;
		if ( is_array( $prior ) && $prior['previous_generation'] === $expected_generation && $prior['operation'] === $operation ) {
			if ( ! hash_equals( $prior['reason_code'], $reason_code ) ) {
				return new WP_Error( 'tra_vel_vip_capability_grant_mutation_reason_conflict', 'reason conflict', array( 'status' => 409 ) );
			}
			return array_merge( $prior, array( 'changed' => false ) );
		}
		if ( is_array( $prior ) || (int) $this->grants[ $digest ]['rotation_generation'] !== (int) $expected_generation ) {
			return new WP_Error( 'tra_vel_vip_capability_grant_generation_conflict', 'conflict', array( 'status' => 409 ) );
		}
		$timestamp = null === $now ? time() : (int) $now;
		$next = 'rotate' === $operation ? $expected_generation + 1 : $expected_generation;
		$this->grants[ $digest ]['rotation_generation'] = $next;
		$this->grants[ $digest ]['revoked_at'] = gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
		$count = 0;
		foreach ( $this->sessions as &$session ) {
			if ( $session['capability_digest'] === $digest && 'active' === $session['state'] ) {
				$session['state'] = 'revoked';
				$session['revoked_at'] = gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
				$count++;
			}
		}
		unset( $session );
		$receipt = array( 'contract_version' => '1.0.0', 'operation' => $operation, 'state' => 'revoked', 'changed' => true, 'previous_generation' => $expected_generation, 'next_generation' => $next, 'reason_code' => $reason_code, 'mutated_at' => gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ), 'sessions_revoked' => $count );
		$this->mutations[ $digest ] = $receipt;
		return $receipt;
	}

	public function consume_limit( $key, $limit, $expires_at ) {
		$this->events[] = 'limit';
		if ( 'error' === $this->limit_mode ) {
			return new WP_Error( 'tra_vel_vip_capability_limit_store_unavailable', 'limiter unavailable', array( 'status' => 503 ) );
		}
		return 'allow' === $this->limit_mode && 64 === strlen( $key ) && $limit > 0 && $expires_at > time();
	}

	public function revoke_grant( $exchange_value ) {
		$digest = self::capability_digest( $exchange_value );
		$this->grants[ $digest ]['revoked_at'] = gmdate( 'Y-m-d\TH:i:s\Z' );
	}

	public function expire_grant( $exchange_value ) {
		$digest = self::capability_digest( $exchange_value );
		$this->grants[ $digest ]['expires_at'] = gmdate( 'Y-m-d\TH:i:s\Z', time() - 1 );
	}

	public function rotate_grant( $exchange_value ) {
		$digest = self::capability_digest( $exchange_value );
		$this->grants[ $digest ]['rotation_generation']++;
	}
}

require_once $vip . 'class-tra-vel-vip-capability-session-controller.php';

$assertions = 0;
function tvcs_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "VIP capability-session runtime failed: {$message}\n" );
		exit( 1 );
	}
}
function tvcs_error( $value, $code, $message ) {
	tvcs_assert( is_wp_error( $value ) && $code === $value->get_error_code(), $message . ( is_wp_error( $value ) ? ' (got ' . $value->get_error_code() . ')' : '' ) );
}
function tvcs_ref( $kind, $seed ) { return 'tv_' . $kind . '_' . str_pad( preg_replace( '/[^A-Za-z0-9_-]/', '', $seed ), 16, 'x' ); }
function tvcs_issue_request( $scopes = null ) {
	return array(
		'trip_ref' => tvcs_ref( 'trip', 'runtime-trip' ),
		'case_ref' => tvcs_ref( 'case', 'runtime-case' ),
		'account_ref' => tvcs_ref( 'account', 'runtime-account' ),
		'issue_reason' => 'trip_access',
		'channel' => 'email',
		'allowed_scopes' => null === $scopes ? array( 'trip_view_redacted', 'incident_report', 'case_progress_view' ) : $scopes,
		'disclosure_classes' => array( 'trip_redacted', 'case_progress' ),
		'lifetime_seconds' => 3600,
		'rotation_generation' => 1,
	);
}
function tvcs_origin( $origin = 'https://tra-vel.co.il' ) { return array( 'Origin' => $origin ); }
function tvcs_request( $value, $key, $headers = null ) { return new WP_REST_Request( array( 'exchange_value' => $value, 'idempotency_key' => $key ), null === $headers ? tvcs_origin() : $headers ); }
function tvcs_extract_cookie( $header ) {
	preg_match( '/^[^=]+=([^;]+)/', (string) $header, $match );
	return isset( $match[1] ) ? rawurldecode( $match[1] ) : '';
}

update_option( Tra_Vel_VIP_Capability_Session_Store::DB_VERSION_OPTION, Tra_Vel_VIP_Capability_Session_Store::DB_VERSION, false );
Tra_Vel_VIP_Capability_Session_Store::invalidate_readiness_cache();
$health_first = Tra_Vel_VIP_Capability_Session_Store::schema_health();
$first_inspections = $GLOBALS['wpdb']->inspection_calls;
$health_cached = Tra_Vel_VIP_Capability_Session_Store::schema_health();
tvcs_assert( 12 === $first_inspections && 12 === $GLOBALS['wpdb']->inspection_calls, 'persistent readiness cache did not suppress repeated SHOW inspections' );
tvcs_assert( 4 === $health_first['ready_tables'] && 7 === $health_first['ready_indexes'] && true === $health_first['tables_ready'] && $health_first === $health_cached, 'cached readiness health is not the exact four-table/seven-index contract' );
$readiness_record = get_option( Tra_Vel_VIP_Capability_Session_Store::READINESS_CACHE_OPTION, null );
tvcs_assert( is_array( $readiness_record ) && $readiness_record['expires_at'] - $readiness_record['checked_at'] === Tra_Vel_VIP_Capability_Session_Store::READINESS_CACHE_TTL_SECONDS, 'persistent readiness cache is not bounded to its fixed TTL' );
Tra_Vel_VIP_Capability_Session_Store::invalidate_readiness_cache();
tvcs_assert( null === get_option( Tra_Vel_VIP_Capability_Session_Store::READINESS_CACHE_OPTION, null ), 'readiness invalidation left durable state behind' );
$health_rechecked = Tra_Vel_VIP_Capability_Session_Store::schema_health();
tvcs_assert( 24 === $GLOBALS['wpdb']->inspection_calls && true === $health_rechecked['tables_ready'], 'readiness invalidation did not force a fresh schema inspection' );

$store = new TVCS_Memory_Store();
$controller = new Tra_Vel_VIP_Capability_Session_Controller( $store );
$controller->register_routes();
$base = 'tra-vel-agent/v1/vip/capability-session/';
foreach ( array( 'probe', 'exchange', 'current', 'logout' ) as $route ) {
	tvcs_assert( isset( $GLOBALS['tvcs_routes'][ $base . $route ] ), "{$route} route missing" );
}
tvcs_assert( WP_REST_Server::READABLE === $GLOBALS['tvcs_routes'][ $base . 'probe' ]['methods'], 'probe is not GET-only' );
tvcs_assert( WP_REST_Server::CREATABLE === $GLOBALS['tvcs_routes'][ $base . 'exchange' ]['methods'], 'exchange is not POST-only' );
tvcs_assert( ! isset( $GLOBALS['tvcs_routes'][ $base . 'mint' ] ), 'public mint route exists' );

$before_events = $store->events;
$probe_a = $controller->probe();
$_COOKIE[ Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE ] = 'scanner_cookie_value_should_not_be_read_123456';
$probe_b = $controller->probe( new WP_REST_Request( array( 'capability_ref' => tvcs_ref( 'capability', 'fake' ) ) ) );
tvcs_assert( $probe_a instanceof WP_REST_Response && 200 === $probe_a->status, 'scanner probe failed' );
tvcs_assert( wp_json_encode( $probe_a->data ) === wp_json_encode( $probe_b->data ), 'scanner response changes with cookie or fake reference' );
tvcs_assert( $before_events === $store->events, 'scanner probe touched mutable storage' );
tvcs_assert( false === $probe_a->data['capability_state_disclosed'] && false === $probe_a->data['trip_state_disclosed'] && false === $probe_a->data['session_state_disclosed'], 'scanner probe disclosed private state' );
tvcs_assert( 'private, no-store, max-age=0' === $probe_a->headers['Cache-Control'] && 'no-referrer' === $probe_a->headers['Referrer-Policy'], 'scanner response lacks private headers' );
unset( $_COOKIE[ Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE ] );

$blocked_issue = $store->issue_server_grant( tvcs_issue_request() );
tvcs_error( $blocked_issue, 'tra_vel_vip_capability_issuance_not_authorized', 'default-deny upstream hook minted a grant' );
$GLOBALS['tvcs_upstream'] = true;
foreach ( Tra_Vel_VIP_Taxonomy::HIGH_IMPACT_SCOPES as $scope ) {
	tvcs_error( Tra_Vel_VIP_Capability_Session_Policy::issuance_request( tvcs_issue_request( array( $scope ) ) ), 'tra_vel_vip_capability_issuance_scope_invalid', "high-impact scope {$scope} was accepted" );
}
foreach ( Tra_Vel_VIP_Taxonomy::LOW_RISK_CAPABILITY_SCOPES as $scope ) {
	tvcs_assert( ! is_wp_error( Tra_Vel_VIP_Capability_Session_Policy::issuance_request( tvcs_issue_request( array( $scope ) ) ) ), "low-risk scope {$scope} was rejected" );
}
$max_generation = tvcs_issue_request();
$max_generation['rotation_generation'] = Tra_Vel_VIP_Capability_Session_Policy::MAX_ROTATION_GENERATION;
tvcs_assert( ! is_wp_error( Tra_Vel_VIP_Capability_Session_Policy::issuance_request( $max_generation ) ), 'maximum rotation generation was rejected' );
$overflow_generation = $max_generation;
$overflow_generation['rotation_generation']++;
tvcs_error( Tra_Vel_VIP_Capability_Session_Policy::issuance_request( $overflow_generation ), 'tra_vel_vip_capability_issuance_security_invalid', 'rotation generation overflow was accepted' );
$unknown = tvcs_issue_request();
$unknown['supplier_payload'] = array();
tvcs_error( Tra_Vel_VIP_Capability_Session_Policy::issuance_request( $unknown ), 'tra_vel_vip_capability_issuance_shape_invalid', 'open issuance object was accepted' );

$issued = $store->issue_server_grant( tvcs_issue_request() );
tvcs_assert( is_array( $issued ) && ! empty( $issued['exchange_value'] ), 'authorized server issuance failed' );
tvcs_assert( ! is_wp_error( Tra_Vel_VIP_Policy::capability_grant( $issued['grant'] ) ), 'issued grant violates existing grant policy' );
tvcs_assert( false === strpos( wp_json_encode( $issued['grant'] ), $issued['exchange_value'] ), 'raw grant leaked into stored projection' );
tvcs_assert( hash_equals( $issued['grant']['capability_digest'], Tra_Vel_VIP_Capability_Session_Store::capability_digest( $issued['exchange_value'] ) ), 'grant digest is not bound to raw value' );

$request = tvcs_request( $issued['exchange_value'], 'vip-capability-idempotency-0001' );
tvcs_assert( true === $controller->can_exchange( $request ), 'valid exact-origin exchange permission failed' );
$store->events = array();
$oversized_request = tvcs_request( $issued['exchange_value'], 'vip-capability-idempotency-0001', array( 'Origin' => 'https://evil.example', 'Content-Length' => '513' ) );
tvcs_error( $controller->can_exchange( $oversized_request ), 'tra_vel_vip_capability_exchange_too_large', 'oversized body was not rejected before origin processing' );
tvcs_assert( array() === $store->events, 'oversized body touched limiter, readiness, or private state' );
$closed_shape_request = new WP_REST_Request( array( 'exchange_value' => $issued['exchange_value'], 'idempotency_key' => 'vip-capability-idempotency-0001', 'trip_ref' => tvcs_ref( 'trip', 'attacker' ) ), tvcs_origin( 'https://evil.example' ) );
tvcs_error( $controller->can_exchange( $closed_shape_request ), 'tra_vel_vip_capability_exchange_shape_invalid', 'open body was not rejected before origin processing' );
tvcs_assert( array() === $store->events, 'open body touched limiter, readiness, or private state' );
tvcs_error( $controller->can_exchange( tvcs_request( $issued['exchange_value'], 'vip-capability-idempotency-0001', array() ) ), 'tra_vel_vip_capability_origin_rejected', 'originless mutation was accepted' );
$store->events = array();
tvcs_error( $controller->can_exchange( tvcs_request( $issued['exchange_value'], 'vip-capability-idempotency-0001', tvcs_origin( 'https://evil.example' ) ) ), 'tra_vel_vip_capability_origin_rejected', 'cross-origin mutation was accepted' );
tvcs_assert( array() === $store->events, 'invalid exchange origin touched limiter, readiness, or grant state' );
tvcs_error( $controller->can_exchange( tvcs_request( $issued['exchange_value'], 'vip-capability-idempotency-0001', tvcs_origin( 'http://tra-vel.co.il' ) ) ), 'tra_vel_vip_capability_origin_rejected', 'HTTP mutation was accepted' );
tvcs_error( $controller->can_exchange( tvcs_request( $issued['exchange_value'], 'vip-capability-idempotency-0001', tvcs_origin( 'https://tra-vel.co.il:444' ) ) ), 'tra_vel_vip_capability_origin_rejected', 'wrong-port mutation was accepted' );
tvcs_error( $controller->can_exchange( tvcs_request( $issued['exchange_value'], 'vip-capability-idempotency-0001', tvcs_origin( 'https://user@tra-vel.co.il' ) ) ), 'tra_vel_vip_capability_origin_rejected', 'userinfo origin was accepted' );
tvcs_error( $controller->can_exchange( tvcs_request( $issued['exchange_value'], 'vip-capability-idempotency-0001', tvcs_origin( 'https://tra-vel.co.il/path' ) ) ), 'tra_vel_vip_capability_origin_rejected', 'origin with a path was accepted' );
tvcs_error( $controller->can_exchange( tvcs_request( $issued['exchange_value'], 'vip-capability-idempotency-0001', tvcs_origin( 'https://tra-vel.co.il?source=attacker' ) ) ), 'tra_vel_vip_capability_origin_rejected', 'origin with a query was accepted' );
tvcs_assert( array() === $store->events, 'rejected origin variants touched limiter, readiness, or grant state' );
$GLOBALS['tvcs_user_id'] = 71;
tvcs_error( $controller->can_exchange( $request ), 'tra_vel_vip_capability_nonce_invalid', 'signed-in mutation omitted REST nonce' );
tvcs_assert( true === $controller->can_exchange( tvcs_request( $issued['exchange_value'], 'vip-capability-idempotency-0001', array( 'Origin' => 'https://tra-vel.co.il', 'X-WP-Nonce' => 'vip-capability-runtime-nonce' ) ) ), 'signed-in exact-origin nonce failed' );
$GLOBALS['tvcs_user_id'] = 0;

$_COOKIE[ Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE ] = str_repeat( 'F', 43 );
$store->events = array();
$created = $controller->exchange( $request );
tvcs_assert( $created instanceof WP_REST_Response && 201 === $created->status, 'grant did not exchange into a session' );
tvcs_assert( array( 'limit', 'limit', 'readiness', 'grant_lookup' ) === $store->events, 'origin/rate/readiness/grant lookup order changed' );
$set_cookie = $created->headers['Set-Cookie'] ?? '';
$session_value = tvcs_extract_cookie( $set_cookie );
tvcs_assert( $session_value && str_repeat( 'F', 43 ) !== $session_value, 'attacker-fixed cookie was reused' );
foreach ( array( 'Path=/', 'Secure', 'HttpOnly', 'SameSite=Strict' ) as $attribute ) {
	tvcs_assert( false !== strpos( $set_cookie, $attribute ), "session cookie missing {$attribute}" );
}
tvcs_assert( false === strpos( $set_cookie, 'Domain=' ), 'host-only cookie contains Domain' );
foreach ( array( 'trip_ref', 'case_ref', 'account_ref', 'capability_ref', 'capability_digest', 'session_digest', 'exchange_value', 'owner_digest' ) as $forbidden ) {
	tvcs_assert( ! array_key_exists( $forbidden, $created->data ), "session response leaked {$forbidden}" );
}
tvcs_assert( false === $created->data['supplier_action_started'] && false === $created->data['payment_action_started'], 'session claimed a commercial side effect' );
tvcs_assert( Tra_Vel_VIP_Taxonomy::HIGH_IMPACT_SCOPES === $created->data['denied_high_impact_scopes'], 'session does not explicitly deny every high-impact scope' );

$_COOKIE[ Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE ] = rawurlencode( $session_value );
$store->events = array();
$read_controller = new Tra_Vel_VIP_Capability_Session_Controller( $store );
tvcs_assert( true === $read_controller->can_read(), 'owner cookie could not read current session' );
tvcs_assert( array( 'limit', 'readiness', 'session_lookup' ) === $store->events, 'cookie/rate/readiness/session lookup order changed' );
$current = $read_controller->get_current();
tvcs_assert( $current instanceof WP_REST_Response && $created->data === $current->data, 'current session projection changed or leaked binding' );
$binding = array( 'trip_ref' => tvcs_ref( 'trip', 'runtime-trip' ), 'case_ref' => tvcs_ref( 'case', 'runtime-case' ), 'account_ref' => tvcs_ref( 'account', 'runtime-account' ) );
$allowed = $store->resolve_scoped_session( $session_value, $binding, 'incident_report', 'case_progress' );
tvcs_assert( is_array( $allowed ), 'exact trip/low-risk/disclosure resolution failed' );
$wrong_trip = $binding;
$wrong_trip['trip_ref'] = tvcs_ref( 'trip', 'another-trip' );
tvcs_error( $store->resolve_scoped_session( $session_value, $wrong_trip, 'incident_report' ), 'tra_vel_vip_capability_session_missing', 'cross-trip session resolution succeeded' );
$wrong_case = $binding;
$wrong_case['case_ref'] = tvcs_ref( 'case', 'another-case' );
tvcs_error( $store->resolve_scoped_session( $session_value, $wrong_case, 'incident_report' ), 'tra_vel_vip_capability_session_missing', 'cross-case session resolution succeeded' );
$wrong_account = $binding;
$wrong_account['account_ref'] = tvcs_ref( 'account', 'another-account' );
tvcs_error( $store->resolve_scoped_session( $session_value, $wrong_account, 'incident_report' ), 'tra_vel_vip_capability_session_missing', 'cross-account session resolution succeeded' );
$open_binding = $binding;
$open_binding['owner_ref'] = tvcs_ref( 'account', 'attacker' );
tvcs_error( $store->resolve_scoped_session( $session_value, $open_binding, 'incident_report' ), 'tra_vel_vip_capability_session_missing', 'open binding object was accepted' );
$null_case = $binding;
$null_case['case_ref'] = null;
tvcs_error( $store->resolve_scoped_session( $session_value, $null_case, 'incident_report' ), 'tra_vel_vip_capability_session_missing', 'null case did not bind exactly' );
tvcs_error( $store->resolve_scoped_session( $session_value, $binding, 'ordinary_evidence_add' ), 'tra_vel_vip_capability_session_missing', 'scope absent from grant was authorized' );
tvcs_error( $store->resolve_scoped_session( $session_value, $binding, 'incident_report', 'safe_contact_location' ), 'tra_vel_vip_capability_session_missing', 'undisclosed class was exposed' );
foreach ( Tra_Vel_VIP_Taxonomy::HIGH_IMPACT_SCOPES as $scope ) {
	tvcs_error( $store->resolve_scoped_session( $session_value, $binding, $scope ), 'tra_vel_vip_capability_scope_denied', "session authorized high-impact {$scope}" );
}

$replay = $controller->exchange( $request );
tvcs_assert( $replay instanceof WP_REST_Response && 200 === $replay->status, 'exact lost-response replay failed' );
tvcs_assert( $session_value === tvcs_extract_cookie( $replay->headers['Set-Cookie'] ?? '' ), 'lost-response replay did not restore identical cookie' );
tvcs_assert( 1 === count( $store->sessions ), 'replay created a second session' );
$digest = Tra_Vel_VIP_Capability_Session_Policy::canonical_digest( array( 'changed' => true ) );
tvcs_error( $store->exchange( $issued['exchange_value'], 'vip-capability-idempotency-0001', $digest ), 'tra_vel_vip_capability_idempotency_conflict', 'changed replay did not conflict' );
$stolen = $controller->exchange( tvcs_request( $issued['exchange_value'], 'vip-capability-stolen-other-key' ) );
tvcs_error( $stolen, 'tra_vel_vip_capability_exchange_unavailable', 'consumed grant was reused with another key' );

$bad = $controller->exchange( tvcs_request( str_repeat( 'X', 43 ), 'vip-capability-mismatch-key-02' ) );
tvcs_error( $bad, 'tra_vel_vip_capability_exchange_unavailable', 'token mismatch did not fail generically' );
$invalid_shape = new WP_REST_Request( array( 'exchange_value' => str_repeat( 'X', 43 ), 'idempotency_key' => 'vip-capability-shape-key-003', 'trip_ref' => tvcs_ref( 'trip', 'attacker' ) ), tvcs_origin() );
tvcs_error( $controller->exchange( $invalid_shape ), 'tra_vel_vip_capability_exchange_shape_invalid', 'open exchange shape was accepted' );

$expired = $store->issue_server_grant( tvcs_issue_request() );
$store->expire_grant( $expired['exchange_value'] );
tvcs_error( $controller->exchange( tvcs_request( $expired['exchange_value'], 'vip-capability-expired-key-04' ) ), 'tra_vel_vip_capability_exchange_unavailable', 'expired grant exchanged' );
$revoked = $store->issue_server_grant( tvcs_issue_request() );
$store->revoke_grant( $revoked['exchange_value'] );
tvcs_error( $controller->exchange( tvcs_request( $revoked['exchange_value'], 'vip-capability-revoked-key-05' ) ), 'tra_vel_vip_capability_exchange_unavailable', 'revoked grant exchanged' );

$rotated = $store->issue_server_grant( tvcs_issue_request() );
$rotated_response = $controller->exchange( tvcs_request( $rotated['exchange_value'], 'vip-capability-rotation-key-06' ) );
$rotated_session_value = tvcs_extract_cookie( $rotated_response->headers['Set-Cookie'] ?? '' );
$rotation_receipt = $store->rotate_server_grant_generation( $rotated['grant']['capability_ref'], 1, 'operator_requested_rotation' );
tvcs_assert( is_array( $rotation_receipt ) && true === $rotation_receipt['changed'] && 2 === $rotation_receipt['next_generation'] && 1 === $rotation_receipt['sessions_revoked'], 'server rotation did not atomically advance and invalidate' );
tvcs_assert( false === strpos( wp_json_encode( $rotation_receipt ), $rotated['exchange_value'] ), 'rotation receipt leaked raw grant value' );
tvcs_error( $store->current_session( $rotated_session_value ), 'tra_vel_vip_capability_session_missing', 'stale rotation kept a session active' );
$rotation_replay = $store->rotate_server_grant_generation( $rotated['grant']['capability_ref'], 1, 'operator_requested_rotation' );
tvcs_assert( is_array( $rotation_replay ) && false === $rotation_replay['changed'] && 'operator_requested_rotation' === $rotation_replay['reason_code'] && $rotation_receipt['mutated_at'] === $rotation_replay['mutated_at'], 'exact rotation replay did not preserve its original reason and clock' );
tvcs_error( $store->rotate_server_grant_generation( $rotated['grant']['capability_ref'], 1, 'stale_rotation' ), 'tra_vel_vip_capability_grant_mutation_reason_conflict', 'changed rotation replay reason did not conflict' );

$maximum_rotation_request = tvcs_issue_request();
$maximum_rotation_request['rotation_generation'] = Tra_Vel_VIP_Capability_Session_Policy::MAX_ROTATION_GENERATION;
$maximum_rotation_grant = $store->issue_server_grant( $maximum_rotation_request );
tvcs_error( $store->rotate_server_grant_generation( $maximum_rotation_grant['grant']['capability_ref'], Tra_Vel_VIP_Capability_Session_Policy::MAX_ROTATION_GENERATION, 'rotation_limit' ), 'tra_vel_vip_capability_grant_mutation_invalid', 'maximum rotation generation overflowed' );

$unconsumed_revoke_grant = $store->issue_server_grant( tvcs_issue_request() );
$unconsumed_revoke = $store->revoke_server_grant( $unconsumed_revoke_grant['grant']['capability_ref'], 1, 'unused_link_closed' );
tvcs_assert( is_array( $unconsumed_revoke ) && true === $unconsumed_revoke['changed'] && 0 === $unconsumed_revoke['sessions_revoked'], 'unconsumed grant could not be revoked with a zero-session receipt' );
$unconsumed_replay = $store->revoke_server_grant( $unconsumed_revoke_grant['grant']['capability_ref'], 1, 'unused_link_closed' );
tvcs_assert( is_array( $unconsumed_replay ) && false === $unconsumed_replay['changed'] && 0 === $unconsumed_replay['sessions_revoked'], 'zero-session mutation receipt did not replay exactly' );

$server_revoked = $store->issue_server_grant( tvcs_issue_request() );
$server_revoked_response = $controller->exchange( tvcs_request( $server_revoked['exchange_value'], 'vip-capability-server-revoke-08' ) );
$server_revoked_value = tvcs_extract_cookie( $server_revoked_response->headers['Set-Cookie'] ?? '' );
$GLOBALS['tvcs_upstream'] = false;
tvcs_error( $store->revoke_server_grant( $server_revoked['grant']['capability_ref'], 1, 'security_response' ), 'tra_vel_vip_capability_grant_mutation_not_authorized', 'default-deny mutation hook allowed revocation' );
$GLOBALS['tvcs_upstream'] = true;
$revocation_receipt = $store->revoke_server_grant( $server_revoked['grant']['capability_ref'], 1, 'security_response' );
tvcs_assert( is_array( $revocation_receipt ) && true === $revocation_receipt['changed'] && 1 === $revocation_receipt['sessions_revoked'], 'server revocation did not atomically invalidate session' );
tvcs_assert( false === strpos( wp_json_encode( $revocation_receipt ), $server_revoked['exchange_value'] ), 'revocation receipt leaked raw grant value' );
tvcs_error( $store->current_session( $server_revoked_value ), 'tra_vel_vip_capability_session_missing', 'revoked grant left its cookie session active' );
$revoke_replay = $store->revoke_server_grant( $server_revoked['grant']['capability_ref'], 1, 'security_response' );
tvcs_assert( is_array( $revoke_replay ) && false === $revoke_replay['changed'] && 'security_response' === $revoke_replay['reason_code'] && $revocation_receipt['mutated_at'] === $revoke_replay['mutated_at'], 'exact server revocation replay did not preserve its original reason and clock' );
tvcs_error( $store->revoke_server_grant( $server_revoked['grant']['capability_ref'], 1, 'changed_reason' ), 'tra_vel_vip_capability_grant_mutation_reason_conflict', 'changed revocation replay reason did not conflict' );

$session_record = $store->sessions[ Tra_Vel_VIP_Capability_Session_Store::session_digest( $session_value ) ];
$store->sessions[ Tra_Vel_VIP_Capability_Session_Store::session_digest( $session_value ) ]['expires_at'] = gmdate( 'Y-m-d\TH:i:s\Z', time() - 1 );
tvcs_error( $store->current_session( $session_value ), 'tra_vel_vip_capability_session_missing', 'expired session remained usable' );
$store->sessions[ Tra_Vel_VIP_Capability_Session_Store::session_digest( $session_value ) ] = $session_record;

$store->limit_mode = 'deny';
$events_before = count( $store->events );
$rate_issued = $store->issue_server_grant( tvcs_issue_request() );
$rate_result = $controller->exchange( tvcs_request( $rate_issued['exchange_value'], 'vip-capability-rate-key-0007' ) );
tvcs_error( $rate_result, 'tra_vel_vip_capability_rate_limited', 'rate failure did not stop exchange' );
$new_events = array_slice( $store->events, $events_before );
tvcs_assert( array( 'limit' ) === $new_events, 'rate-limited request reached candidate limit, readiness, or grant lookup' );

$store->limit_mode = 'error';
$store->events = array();
$limit_error = $controller->can_exchange( tvcs_request( $rate_issued['exchange_value'], 'vip-capability-limit-db-error' ) );
tvcs_error( $limit_error, 'tra_vel_vip_capability_limit_store_unavailable', 'limiter database failure did not become 503' );
tvcs_assert( 503 === $limit_error->get_error_data()['status'] && array( 'limit' ) === $store->events, 'limiter database failure touched readiness or private lookup' );

$store->limit_mode = 'allow';
TVCS_Memory_Store::$ready = false;
$store->events = array();
$not_ready = $controller->can_exchange( tvcs_request( $rate_issued['exchange_value'], 'vip-capability-not-ready-0008' ) );
tvcs_error( $not_ready, 'tra_vel_vip_capability_store_unavailable', 'unready store did not return 503 after limiting' );
tvcs_assert( array( 'limit', 'limit', 'readiness' ) === $store->events, 'readiness ran before both exchange limiters or lookup ran while unready' );
TVCS_Memory_Store::$ready = true;

$_COOKIE[ Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE ] = 'bad cookie syntax';
$store->events = array();
tvcs_error( ( new Tra_Vel_VIP_Capability_Session_Controller( $store ) )->can_read(), 'tra_vel_vip_capability_session_missing', 'invalid cookie syntax was not rejected generically' );
tvcs_assert( array() === $store->events, 'invalid cookie syntax touched limiter, readiness, or session lookup' );

$_COOKIE[ Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE ] = rawurlencode( $session_value );
$logout_request = new WP_REST_Request( array(), tvcs_origin() );
$store->events = array();
tvcs_error( $controller->can_logout( new WP_REST_Request( array(), tvcs_origin( 'https://evil.example' ) ) ), 'tra_vel_vip_capability_origin_rejected', 'cross-origin logout was accepted' );
tvcs_assert( array() === $store->events, 'invalid logout origin touched limiter, readiness, or session state' );
tvcs_assert( true === $controller->can_logout( $logout_request ), 'same-origin logout permission failed' );
tvcs_assert( array( 'limit', 'readiness' ) === $store->events, 'logout did not enforce rate before readiness and before revoke' );
$logout = $controller->logout( $logout_request );
tvcs_assert( $logout instanceof WP_REST_Response && true === $logout->data['closed'], 'logout did not return a calm closed receipt' );
tvcs_assert( array( 'limit', 'readiness', 'session_revoke' ) === $store->events, 'logout revoke did not follow origin/cookie/rate/readiness gates' );
$clear = $logout->headers['Set-Cookie'] ?? '';
foreach ( array( 'Max-Age=0', 'Path=/', 'Secure', 'HttpOnly', 'SameSite=Strict' ) as $attribute ) {
	tvcs_assert( false !== strpos( $clear, $attribute ), "logout cookie missing {$attribute}" );
}
tvcs_assert( false === strpos( $clear, 'Domain=' ), 'logout changed host-only cookie boundary' );
tvcs_error( $store->current_session( $session_value ), 'tra_vel_vip_capability_session_missing', 'logout left session active' );

echo "VIP capability-session runtime passed ({$assertions} assertions; ordered fail-closed gates, bounded readiness cache, one-time/idempotent exchange, exact three-ref binding, replay-reason and rotation bounds, zero high-impact authority).\n";
