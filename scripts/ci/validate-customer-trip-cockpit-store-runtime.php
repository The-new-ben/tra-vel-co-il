<?php
/**
 * Adversarial no-WordPress runtime for the private Trip Cockpit repository.
 */

define( 'ABSPATH', __DIR__ . '/fixtures/wp-stub/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['ctcs_options'] = array();
$GLOBALS['ctcs_assertions'] = 0;

interface Tra_Vel_Customer_Trip_Cockpit_Read_Model_Provider {
	public static function is_ready();
	public function get_owned_current_projection( $owner_user_id, $now = null );
	public function get_bound_projection( $trip_ref, $case_ref, $account_ref, $now = null );
	public function consume_limit( $limit_key, $limit, $expires_at );
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

class Tra_Vel_Traveler_Principal {
	public static function account_ref( $owner_user_id ) {
		return 'tv_account_' . hash( 'sha256', 'owner|' . (int) $owner_user_id );
	}

	public static function cockpit_owner_scope_digest( $owner_user_id, $account_ref, $trip_ref ) {
		return hash_hmac( 'sha256', (int) $owner_user_id . '|' . $account_ref . '|' . $trip_ref, 'runtime-owner-key' );
	}

	public static function valid_ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[a-z0-9]{12,80}$/', $value );
	}
}

class Tra_Vel_Customer_Trip_Cockpit_Policy {
	public static function validate_projection( $projection, $now ) {
		unset( $now );
		return is_array( $projection ) && empty( $projection['invalid'] )
			? $projection
			: new WP_Error( 'runtime_invalid_projection', 'invalid' );
	}

	public static function assert_successor( $previous, $projection, $now ) {
		unset( $previous, $now );
		return $projection;
	}
}

class Tra_Vel_Customer_Trip_Cockpit_Factory {
	public static function create_projection( $source, $now ) {
		unset( $now );
		return isset( $source['projection'] ) ? $source['projection'] : new WP_Error( 'runtime_projection_missing', 'missing' );
	}
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['ctcs_options'] ) ? $GLOBALS['ctcs_options'][ $name ] : $default; }
function update_option( $name, $value, $autoload = null ) { unset( $autoload ); $GLOBALS['ctcs_options'][ $name ] = $value; return true; }
function delete_option( $name ) { unset( $GLOBALS['ctcs_options'][ $name ] ); return true; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function current_time( $type, $gmt = false ) { unset( $type, $gmt ); return gmdate( 'Y-m-d H:i:s' ); }
function apply_filters( $hook, $default ) { unset( $hook, $default ); return true; }

class CTCS_WPDB {
	public $prefix = 'wp_';
	public $last_error = '';
	public $insert_id = 0;
	public $schema_ready = true;
	public $repair_succeeds = true;
	public $db_delta_calls = 0;
	public $schema_inspections = 0;
	public $select_rows = array();
	public $bound_row = null;
	public $fail_next_select = false;
	public $last_select_sql = '';
	public $queries = array();
	private $errors_suppressed = false;

	public function suppress_errors( $suppress = null ) {
		$previous = $this->errors_suppressed;
		if ( null !== $suppress ) { $this->errors_suppressed = (bool) $suppress; }
		return $previous;
	}

	public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }

	public function prepare( $query ) {
		$args = array_slice( func_get_args(), 1 );
		foreach ( $args as $value ) {
			$query = preg_replace_callback(
				'/%[ds]/',
				static function ( $match ) use ( $value ) {
					return '%d' === $match[0] ? (string) (int) $value : "'" . str_replace( "'", "''", (string) $value ) . "'";
				},
				$query,
				1
			);
		}
		return $query;
	}

	public function get_col( $sql, $column = 0 ) {
		unset( $column );
		$this->schema_inspections++;
		$table = $this->table_from_backticks( $sql );
		$columns = $this->columns_for( $table );
		if ( ! $this->schema_ready && false !== strpos( $table, 'customer_trip_cockpits' ) && false === strpos( $table, 'revisions' ) ) {
			array_pop( $columns );
		}
		return $columns;
	}

	public function get_results( $sql, $output = null ) {
		unset( $output );
		if ( 0 === strpos( $sql, 'SHOW INDEX' ) ) {
			return $this->indexes_for( $this->table_from_backticks( $sql ) );
		}
		if ( 0 === strpos( $sql, 'SELECT * FROM ' ) && false !== strpos( $sql, 'owner_user_id' ) ) {
			$this->last_select_sql = $sql;
			if ( $this->fail_next_select ) {
				$this->fail_next_select = false;
				$this->last_error = 'runtime SELECT failed';
				return null;
			}
			$this->last_error = '';
			return $this->select_rows;
		}
		return array();
	}

	public function get_row( $sql, $output = null ) {
		unset( $output );
		if ( 0 === strpos( $sql, 'SHOW TABLE STATUS' ) ) {
			return array( 'Engine' => 'InnoDB' );
		}
		if ( 0 === strpos( $sql, 'SELECT * FROM ' ) ) {
			$this->last_select_sql = $sql;
			if ( $this->fail_next_select ) {
				$this->fail_next_select = false;
				$this->last_error = 'runtime SELECT failed';
				return null;
			}
			$this->last_error = '';
			return $this->bound_row;
		}
		return null;
	}

	public function query( $sql ) { $this->queries[] = $sql; return true; }
	public function insert( $table, $data, $formats = null ) { unset( $table, $data, $formats ); $this->insert_id = 1; return 1; }
	public function get_var( $sql ) { unset( $sql ); return 1; }

	public function db_delta( $sql ) {
		unset( $sql );
		$this->db_delta_calls++;
		if ( $this->repair_succeeds && $this->db_delta_calls >= 3 ) { $this->schema_ready = true; }
		return array();
	}

	private function table_from_backticks( $sql ) {
		return preg_match( '/`([^`]+)`/', $sql, $matches ) ? $matches[1] : '';
	}

	private function columns_for( $table ) {
		if ( false !== strpos( $table, '_revisions' ) ) {
			return array( 'id','cockpit_id','owner_user_id','account_ref','cockpit_ref','trip_ref','revision','previous_projection_digest','projection_digest','projection_json','last_verified_at','stored_at','retention_until' );
		}
		if ( false !== strpos( $table, '_limits' ) ) {
			return array( 'id','limit_key','hits','expires_at' );
		}
		return array( 'id','owner_user_id','account_ref','cockpit_ref','trip_ref','owner_scope_digest','revision','previous_projection_digest','projection_digest','projection_json','last_verified_at','created_at','updated_at','retention_until' );
	}

	private function indexes_for( $table ) {
		if ( false !== strpos( $table, '_revisions' ) ) {
			$indexes = array( 'PRIMARY' => array( true, array( 'id' ) ), 'cockpit_revision' => array( true, array( 'cockpit_ref','revision' ) ), 'owner_trip_revision' => array( false, array( 'owner_user_id','trip_ref','revision' ) ), 'retention' => array( false, array( 'retention_until','id' ) ) );
		} elseif ( false !== strpos( $table, '_limits' ) ) {
			$indexes = array( 'PRIMARY' => array( true, array( 'id' ) ), 'limit_key' => array( true, array( 'limit_key' ) ), 'expiry' => array( false, array( 'expires_at','id' ) ) );
		} else {
			$indexes = array( 'PRIMARY' => array( true, array( 'id' ) ), 'trip_ref' => array( true, array( 'trip_ref' ) ), 'cockpit_ref' => array( true, array( 'cockpit_ref' ) ), 'owner_trip' => array( false, array( 'owner_user_id','trip_ref' ) ), 'account_trip' => array( false, array( 'account_ref','trip_ref' ) ), 'retention' => array( false, array( 'retention_until','id' ) ) );
		}
		$rows = array();
		foreach ( $indexes as $name => $definition ) {
			foreach ( $definition[1] as $offset => $column ) {
				$rows[] = array( 'Key_name' => $name, 'Seq_in_index' => $offset + 1, 'Column_name' => $column, 'Non_unique' => $definition[0] ? 0 : 1 );
			}
		}
		return $rows;
	}
}

function ctcs_assert( $condition, $message ) {
	$GLOBALS['ctcs_assertions']++;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function ctcs_error( $value, $suffix, $status, $message ) {
	ctcs_assert( is_wp_error( $value ), $message . ' (not WP_Error)' );
	ctcs_assert( 'tra_vel_customer_trip_cockpit_' . $suffix === $value->get_error_code(), $message . ' (wrong code)' );
	ctcs_assert( $status === (int) $value->get_error_data()['status'], $message . ' (wrong status)' );
}

function ctcs_reset_request_cache() {
	$property = new ReflectionProperty( 'Tra_Vel_Customer_Trip_Cockpit_Store', 'ready_cache' );
	$property->setAccessible( true );
	$property->setValue( null, null );
}

function ctcs_ref( $kind, $seed ) { return 'tv_' . $kind . '_' . substr( hash( 'sha256', $seed ), 0, 24 ); }

function ctcs_row( $owner_user_id, $seed, $phase, $verified_at, $id ) {
	$account_ref = Tra_Vel_Traveler_Principal::account_ref( $owner_user_id );
	$trip_ref = ctcs_ref( 'trip', $seed );
	$cockpit_ref = ctcs_ref( 'cockpit', $seed );
	$scope = Tra_Vel_Traveler_Principal::cockpit_owner_scope_digest( $owner_user_id, $account_ref, $trip_ref );
	$digest = hash( 'sha256', 'projection|' . $seed );
	$projection = array(
		'trip_ref' => $trip_ref,
		'cockpit_ref' => $cockpit_ref,
		'owner_scope_digest' => $scope,
		'projection_digest' => $digest,
		'revision' => 1,
		'previous_projection_digest' => null,
		'current' => array( 'phase' => $phase ),
		'last_verified_at' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $verified_at . ' UTC' ) ),
	);
	return array(
		'id' => $id,
		'owner_user_id' => $owner_user_id,
		'account_ref' => $account_ref,
		'cockpit_ref' => $cockpit_ref,
		'trip_ref' => $trip_ref,
		'owner_scope_digest' => $scope,
		'revision' => 1,
		'previous_projection_digest' => null,
		'projection_digest' => $digest,
		'projection_json' => json_encode( $projection ),
		'last_verified_at' => $verified_at,
		'created_at' => $verified_at,
		'updated_at' => $verified_at,
		'retention_until' => '2099-12-31 23:59:59',
	);
}

require_once dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/includes/vip/class-tra-vel-customer-trip-cockpit-store.php';

$wpdb = new CTCS_WPDB();
$GLOBALS['ctcs_options'][ Tra_Vel_Customer_Trip_Cockpit_Store::DB_VERSION_OPTION ] = Tra_Vel_Customer_Trip_Cockpit_Store::DB_VERSION;

ctcs_assert( Tra_Vel_Customer_Trip_Cockpit_Store::is_ready(), 'A complete exact schema must be ready.' );
$inspection_count = $wpdb->schema_inspections;
ctcs_reset_request_cache();
ctcs_assert( Tra_Vel_Customer_Trip_Cockpit_Store::is_ready(), 'A valid durable readiness record must survive a simulated next request.' );
ctcs_assert( $inspection_count === $wpdb->schema_inspections, 'A valid durable readiness record must avoid repeated SHOW inspections.' );
$cache = $GLOBALS['ctcs_options'][ Tra_Vel_Customer_Trip_Cockpit_Store::READINESS_CACHE_OPTION ];
ctcs_assert( 300 === $cache['expires_at'] - $cache['checked_at'], 'The durable readiness record must have an exact bounded lifetime.' );
$GLOBALS['ctcs_options'][ Tra_Vel_Customer_Trip_Cockpit_Store::READINESS_CACHE_OPTION ]['expires_at'] = time() - 1;
ctcs_reset_request_cache();
ctcs_assert( Tra_Vel_Customer_Trip_Cockpit_Store::is_ready(), 'An expired readiness record must be replaced by a fresh inspection.' );
ctcs_assert( $inspection_count + 3 === $wpdb->schema_inspections, 'An expired readiness record must not suppress SHOW inspections.' );

$wpdb->schema_ready = false;
$wpdb->db_delta_calls = 0;
Tra_Vel_Customer_Trip_Cockpit_Store::invalidate_readiness_cache();
Tra_Vel_Customer_Trip_Cockpit_Store::maybe_upgrade();
ctcs_assert( 3 === $wpdb->db_delta_calls, 'Same-version schema drift must trigger one complete dbDelta repair.' );
ctcs_assert( Tra_Vel_Customer_Trip_Cockpit_Store::is_ready(), 'A successful same-version repair must refresh readiness.' );

$wpdb->schema_ready = false;
$wpdb->repair_succeeds = false;
$wpdb->db_delta_calls = 0;
Tra_Vel_Customer_Trip_Cockpit_Store::invalidate_readiness_cache();
Tra_Vel_Customer_Trip_Cockpit_Store::maybe_upgrade();
ctcs_assert( 3 === $wpdb->db_delta_calls, 'A failed repair must still run each table migration once.' );
ctcs_reset_request_cache();
Tra_Vel_Customer_Trip_Cockpit_Store::maybe_upgrade();
ctcs_assert( 3 === $wpdb->db_delta_calls, 'A failed repair must be cooled down by the bounded durable readiness record.' );

$wpdb->schema_ready = true;
$wpdb->repair_succeeds = true;
$GLOBALS['ctcs_options'][ Tra_Vel_Customer_Trip_Cockpit_Store::DB_VERSION_OPTION ] = Tra_Vel_Customer_Trip_Cockpit_Store::DB_VERSION;
Tra_Vel_Customer_Trip_Cockpit_Store::invalidate_readiness_cache();
ctcs_assert( Tra_Vel_Customer_Trip_Cockpit_Store::is_ready(), 'Read tests require a repaired store.' );

$store = new Tra_Vel_Customer_Trip_Cockpit_Store();
$post = ctcs_row( 17, 'post', 'post_trip', '2026-07-19 12:00:00', 30 );
$active_latest = ctcs_row( 17, 'active-latest', 'in_trip', '2026-07-19 11:00:00', 20 );
$active_older = ctcs_row( 17, 'active-older', 'pre_trip', '2026-07-19 10:00:00', 10 );
$wpdb->select_rows = array( $post, $active_latest, $active_older );
$selected = $store->get_owned_current_projection( 17, time() );
ctcs_assert( is_array( $selected ) && $active_latest['trip_ref'] === $selected['trip_ref'], 'The newest deterministic active trip must beat a newer completed post-trip record.' );
ctcs_assert( false !== strpos( $wpdb->last_select_sql, 'ORDER BY last_verified_at DESC,updated_at DESC,id DESC LIMIT 51' ), 'Owner selection must use a deterministic bounded query.' );

$wpdb->select_rows = array( $post );
$selected_post = $store->get_owned_current_projection( 17, time() );
ctcs_assert( is_array( $selected_post ) && $post['trip_ref'] === $selected_post['trip_ref'], 'A post-trip cockpit remains available when no active trip exists.' );

$wpdb->select_rows = array_fill( 0, 51, $post );
ctcs_error( $store->get_owned_current_projection( 17, time() ), 'selection_unavailable', 503, 'An unbounded owner set must not produce a potentially wrong current selection.' );

$wpdb->select_rows = array();
$wpdb->fail_next_select = true;
$read_failed = $store->get_owned_current_projection( 17, time() );
ctcs_error( $read_failed, 'read_failed', 503, 'An owner SELECT failure must not be reported as an empty account.' );
ctcs_assert( ! isset( $GLOBALS['ctcs_options'][ Tra_Vel_Customer_Trip_Cockpit_Store::READINESS_CACHE_OPTION ] ), 'A SELECT failure must invalidate durable readiness.' );

$wpdb->schema_ready = true;
$GLOBALS['ctcs_options'][ Tra_Vel_Customer_Trip_Cockpit_Store::DB_VERSION_OPTION ] = Tra_Vel_Customer_Trip_Cockpit_Store::DB_VERSION;
ctcs_assert( Tra_Vel_Customer_Trip_Cockpit_Store::is_ready(), 'Bound read test must rebuild readiness after invalidation.' );
$wpdb->bound_row = null;
$wpdb->fail_next_select = true;
ctcs_error( $store->get_bound_projection( $active_latest['trip_ref'], null, $active_latest['account_ref'], time() ), 'read_failed', 503, 'A bound SELECT failure must not be reported as a missing scoped cockpit.' );

$wpdb->schema_ready = true;
$GLOBALS['ctcs_options'][ Tra_Vel_Customer_Trip_Cockpit_Store::DB_VERSION_OPTION ] = Tra_Vel_Customer_Trip_Cockpit_Store::DB_VERSION;
ctcs_assert( Tra_Vel_Customer_Trip_Cockpit_Store::is_ready(), 'Commit read test must rebuild readiness after invalidation.' );
$wpdb->fail_next_select = true;
$source = array(
	'trip_ref' => $active_latest['trip_ref'],
	'owner_scope_digest' => $active_latest['owner_scope_digest'],
	'projection' => json_decode( $active_latest['projection_json'], true ),
);
ctcs_error( $store->commit_server_source( 17, $active_latest['account_ref'], $source, time() ), 'commit_read_failed', 503, 'A failed locking read must not be treated as a first cockpit revision.' );
ctcs_assert( in_array( 'ROLLBACK', $wpdb->queries, true ), 'An uncertain locking read must roll back its transaction.' );

fwrite( STDOUT, 'Customer Trip Cockpit store runtime passed (' . $GLOBALS['ctcs_assertions'] . " assertions).\n" );
