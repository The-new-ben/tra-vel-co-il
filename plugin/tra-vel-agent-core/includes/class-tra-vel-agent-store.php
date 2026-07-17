<?php
/**
 * Durable run, event, and approval persistence.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Agent_Store {
	const DB_VERSION         = '1.2.0';
	const DB_VERSION_OPTION  = 'tra_vel_agent_db_version';
	const DEFAULT_EXPIRY_HRS = 24;
	const CLEANUP_STATUS_OPTION = 'tra_vel_agent_cleanup_status';
	const CLEANUP_BATCH_SIZE    = 500;
	const CLEANUP_MAX_SECONDS   = 20;

	/** @var bool|null Request-local readiness cache. */
	private static $ready_cache = null;

	public static function maybe_upgrade() {
		if ( self::DB_VERSION !== get_option( self::DB_VERSION_OPTION ) ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$runs    = self::runs_table();
		$events  = self::events_table();
		$approvals = self::approvals_table();
		$limits    = self::limits_table();

		dbDelta( "CREATE TABLE {$runs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_uuid char(36) NOT NULL,
			owner_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			owner_token_hash char(64) NOT NULL,
			request_fingerprint char(64) NOT NULL,
			status varchar(40) NOT NULL,
			mode varchar(20) NOT NULL,
			locale varchar(12) NOT NULL,
			input_kind varchar(12) NOT NULL DEFAULT 'typed',
			input_text text NOT NULL,
			trip_request longtext NULL,
			proposals longtext NULL,
			provider_state longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY run_uuid (run_uuid),
			KEY owner_user_id (owner_user_id),
			KEY request_fingerprint (request_fingerprint),
			KEY status (status),
			KEY expires_at (expires_at)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_id bigint(20) unsigned NOT NULL,
			sequence_no bigint(20) unsigned NOT NULL,
			event_uuid char(36) NOT NULL,
			event_type varchar(60) NOT NULL,
			phase varchar(40) NOT NULL,
			status varchar(24) NOT NULL,
			source varchar(24) NOT NULL,
			visible tinyint(1) NOT NULL DEFAULT 1,
			message text NOT NULL,
			payload longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_uuid (event_uuid),
			UNIQUE KEY run_sequence (run_id,sequence_no),
			KEY run_id (run_id),
			KEY event_type (event_type)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$approvals} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			approval_uuid char(36) NOT NULL,
			run_id bigint(20) unsigned NOT NULL,
			approval_version int(10) unsigned NOT NULL DEFAULT 1,
			action_type varchar(40) NOT NULL,
			scope_digest char(64) NOT NULL,
			status varchar(32) NOT NULL,
			summary longtext NOT NULL,
			action_snapshot longtext NOT NULL,
			decision_key varchar(100) NULL,
			decided_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			decided_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY approval_uuid (approval_uuid),
			UNIQUE KEY decision_key (decision_key),
			KEY run_id (run_id),
			KEY status (status)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$limits} (
			counter_key varchar(191) NOT NULL,
			counter_value int(10) unsigned NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (counter_key),
			KEY expires_at (expires_at)
		) ENGINE=InnoDB {$charset};" );

		self::$ready_cache = null;
		$transactional = self::ensure_transactional_tables();
		$readiness     = self::inspect_schema();
		if ( $transactional && $readiness['tables_ready'] ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		} else {
			delete_option( self::DB_VERSION_OPTION );
		}
	}

	/**
	 * Expose the database guarantee used by cross-aggregate ownership binding.
	 *
	 * @return array<string,int|string|bool|null>
	 */
	public static function schema_health() {
		$readiness = self::inspect_schema();
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );
		self::$ready_cache = self::DB_VERSION === $installed && ! empty( $readiness['tables_ready'] );
		return array(
			'schema_version'           => self::DB_VERSION,
			'installed_schema_version' => $installed ? $installed : null,
			'expected_tables'          => (int) $readiness['expected_tables'],
			'ready_tables'             => (int) $readiness['ready_tables'],
			'transactional_tables'     => (int) $readiness['transactional_tables'],
			'required_indexes'         => (int) $readiness['required_indexes'],
			'ready_indexes'            => (int) $readiness['ready_indexes'],
			'required_indexes_ready'   => (bool) $readiness['required_indexes_ready'],
			'tables_ready'             => (bool) $readiness['tables_ready'] && self::DB_VERSION === $installed,
		);
	}

	/**
	 * Fail closed if the run/event/approval/quota schema cannot uphold its
	 * ownership, sequencing, idempotency, and atomic-limit guarantees.
	 *
	 * @return bool
	 */
	public static function is_ready() {
		if ( null === self::$ready_cache ) {
			$installed = (string) get_option( self::DB_VERSION_OPTION, '' );
			$readiness = self::inspect_schema();
			self::$ready_cache = self::DB_VERSION === $installed && ! empty( $readiness['tables_ready'] );
		}
		return (bool) self::$ready_cache;
	}

	/**
	 * Delete expired guest/run state in bounded batches.
	 */
	public static function cleanup_expired() {
		global $wpdb;
		if ( ! self::is_ready() ) {
			$result = array( 'deleted_runs' => 0, 'deleted_orphan_events' => 0, 'deleted_orphan_approvals' => 0, 'deleted_limits' => 0, 'errors' => array( 'schema_not_ready' ) );
			update_option(
				self::CLEANUP_STATUS_OPTION,
				array(
					'last_run_at'  => gmdate( 'c' ),
					'ok'           => false,
					'errors'       => array( 'schema_not_ready' ),
					'deleted_runs' => 0,
				),
				false
			);
			return $result;
		}
		$now      = current_time( 'mysql', true );
		$deadline = microtime( true ) + self::CLEANUP_MAX_SECONDS;
		$was_suppressed = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$ids      = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::runs_table() . ' WHERE expires_at < %s ORDER BY id ASC LIMIT %d', $now, self::CLEANUP_BATCH_SIZE ) );
		$read_error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		$result   = array( 'deleted_runs' => 0, 'deleted_orphan_events' => 0, 'deleted_orphan_approvals' => 0, 'deleted_limits' => 0, 'errors' => array() );
		if ( '' !== $read_error || ! is_array( $ids ) ) {
			$result['errors'][] = 'tra_vel_agent_cleanup_select_failed';
		} else {
			foreach ( $ids as $run_id ) {
				if ( microtime( true ) >= $deadline ) {
					break;
				}
				$deleted = self::delete_expired_run_aggregate( absint( $run_id ), $now );
				if ( is_wp_error( $deleted ) ) {
					$result['errors'][] = $deleted->get_error_code();
					break;
				}
				$result['deleted_runs'] += (int) $deleted;
			}
		}

		foreach ( array( 'events' => self::events_table(), 'approvals' => self::approvals_table() ) as $label => $table ) {
			if ( microtime( true ) >= $deadline ) {
				break;
			}
			$swept = self::sweep_orphan_rows( $table, self::runs_table(), 1000 );
			if ( is_wp_error( $swept ) ) {
				$result['errors'][] = $swept->get_error_code();
			} else {
				$result[ 'deleted_orphan_' . $label ] = (int) $swept;
			}
		}
		$limits = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::limits_table() . ' WHERE expires_at < %s LIMIT 1000', $now ) );
		if ( false === $limits ) {
			$result['errors'][] = 'tra_vel_agent_cleanup_limits_failed';
		} else {
			$result['deleted_limits'] = (int) $limits;
		}
		update_option(
			self::CLEANUP_STATUS_OPTION,
			array(
				'last_run_at' => gmdate( 'c' ),
				'ok'          => empty( $result['errors'] ),
				'errors'      => array_values( array_unique( $result['errors'] ) ),
				'deleted_runs'=> (int) $result['deleted_runs'],
			),
			false
		);
		return $result;
	}

	/**
	 * Delete one expired run and all private children atomically.
	 *
	 * @param int    $run_id Run database ID.
	 * @param string $now    UTC MySQL timestamp.
	 * @return int|WP_Error
	 */
	private static function delete_expired_run_aggregate( $run_id, $now ) {
		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'tra_vel_agent_cleanup_transaction_failed', 'AgentRun cleanup could not start a transaction.' );
		}
		$was_suppressed = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::runs_table() . ' WHERE id = %d AND expires_at < %s FOR UPDATE', absint( $run_id ), (string) $now ) );
		$read_error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( '' !== $read_error ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_agent_cleanup_lock_failed', 'AgentRun cleanup could not lock the expired aggregate.' );
		}
		if ( ! $locked ) {
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'tra_vel_agent_cleanup_commit_failed', 'AgentRun cleanup could not close an empty transaction.' );
			}
			return 0;
		}
		$approvals = $wpdb->delete( self::approvals_table(), array( 'run_id' => absint( $run_id ) ), array( '%d' ) );
		$events    = $wpdb->delete( self::events_table(), array( 'run_id' => absint( $run_id ) ), array( '%d' ) );
		$parent    = $wpdb->delete( self::runs_table(), array( 'id' => absint( $run_id ) ), array( '%d' ) );
		if ( false === $approvals || false === $events || 1 !== $parent ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_agent_cleanup_delete_failed', 'AgentRun cleanup rolled back an incomplete aggregate deletion.' );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_agent_cleanup_commit_failed', 'AgentRun cleanup could not commit a complete aggregate deletion.' );
		}
		return 1;
	}

	/**
	 * Remove bounded legacy orphans so a historical partial cleanup can heal.
	 *
	 * @param string $child_table Child table.
	 * @param string $parent_table Parent run table.
	 * @param int    $limit Maximum rows.
	 * @return int|WP_Error
	 */
	private static function sweep_orphan_rows( $child_table, $parent_table, $limit ) {
		global $wpdb;
		$child_identifier  = str_replace( '`', '``', (string) $child_table );
		$parent_identifier = str_replace( '`', '``', (string) $parent_table );
		$was_suppressed = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT c.id FROM `{$child_identifier}` c LEFT JOIN `{$parent_identifier}` p ON p.id = c.run_id WHERE p.id IS NULL ORDER BY c.id ASC LIMIT %d", absint( $limit ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted WordPress-prefixed identifiers.
		$read_error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( '' !== $read_error || ! is_array( $ids ) ) {
			return new WP_Error( 'tra_vel_agent_cleanup_orphan_select_failed', 'Legacy AgentRun orphans could not be selected.' );
		}
		if ( empty( $ids ) ) {
			return 0;
		}
		$ids          = array_values( array_map( 'absint', $ids ) );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$deleted      = $wpdb->query( $wpdb->prepare( "DELETE FROM `{$child_identifier}` WHERE id IN ({$placeholders})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted identifier and prepared IDs.
		return false === $deleted ? new WP_Error( 'tra_vel_agent_cleanup_orphan_delete_failed', 'Legacy AgentRun orphans could not be deleted.' ) : (int) $deleted;
	}

	/**
	 * Atomically reserve one request inside a fixed operational limit.
	 *
	 * INSERT IGNORE wins the first reservation. Conditional UPDATE statements
	 * serialize all subsequent contenders at the database row, preventing a
	 * burst of parallel requests from stepping past the configured ceiling.
	 *
	 * @param string $key        Stable counter/window key.
	 * @param int    $limit      Maximum accepted reservations.
	 * @param int    $expires_at Unix expiry timestamp.
	 * @return bool
	 */
	public function consume_limit( $key, $limit, $expires_at ) {
		global $wpdb;
		$key        = substr( preg_replace( '/[^A-Za-z0-9:_-]/', '', (string) $key ), 0, 191 );
		$limit      = max( 1, absint( $limit ) );
		$expires_at = max( time() + MINUTE_IN_SECONDS, absint( $expires_at ) );
		if ( '' === $key ) {
			return false;
		}
		$table   = self::limits_table();
		$now     = current_time( 'mysql', true );
		$expires = gmdate( 'Y-m-d H:i:s', $expires_at );
		$created = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (counter_key,counter_value,expires_at) VALUES (%s,1,%s)", $key, $expires ) );
		if ( 1 === $created ) {
			return true;
		}

		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$incremented = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET counter_value = counter_value + 1 WHERE counter_key = %s AND expires_at > %s AND counter_value < %d", $key, $now, $limit ) );
			if ( 1 === $incremented ) {
				return true;
			}
			$reset = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET counter_value = 1, expires_at = %s WHERE counter_key = %s AND expires_at <= %s", $expires, $key, $now ) );
			if ( 1 === $reset ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Acquire one of a small fixed set of atomic owner-token leases.
	 *
	 * @param string $namespace Lease namespace.
	 * @param int    $slots     Maximum parallel owners.
	 * @param int    $ttl       Lease lifetime in seconds.
	 * @return array|false
	 */
	public function acquire_lease( $namespace, $slots, $ttl ) {
		global $wpdb;
		$namespace = substr( preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $namespace ) ), 0, 40 );
		$slots     = min( 5, max( 1, absint( $slots ) ) );
		$ttl       = min( 300, max( 60, absint( $ttl ) ) );
		if ( '' === $namespace ) {
			return false;
		}

		for ( $slot = 1; $slot <= $slots; $slot++ ) {
			$option = 'tra_vel_agent_lease_' . $namespace . '_' . $slot;
			$value  = wp_generate_uuid4() . '|' . ( time() + $ttl );
			if ( add_option( $option, $value, '', false ) ) {
				return array( 'option' => $option, 'value' => $value, 'slot' => $slot );
			}
			$current = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option ) );
			$parts   = explode( '|', $current, 2 );
			$expires = isset( $parts[1] ) ? absint( $parts[1] ) : PHP_INT_MAX;
			if ( $current && $expires < time() ) {
				$replaced = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s, autoload = 'no' WHERE option_name = %s AND option_value = %s", $value, $option, $current ) );
				if ( 1 === $replaced ) {
					wp_cache_delete( $option, 'options' );
					return array( 'option' => $option, 'value' => $value, 'slot' => $slot );
				}
			}
		}
		return false;
	}

	/**
	 * Release only the exact lease owned by this request.
	 *
	 * @param array $lease Lease returned by acquire_lease().
	 */
	public function release_lease( $lease ) {
		global $wpdb;
		if ( ! is_array( $lease ) || empty( $lease['option'] ) || empty( $lease['value'] ) ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", (string) $lease['option'], (string) $lease['value'] ) );
		wp_cache_delete( (string) $lease['option'], 'options' );
	}

	/**
	 * Create a private run and return its one-time bearer token.
	 *
	 * @param array $input Normalized create input.
	 * @return array|WP_Error
	 */
	public function create_run( $input ) {
		global $wpdb;
		$run_uuid  = wp_generate_uuid4();
		$token     = $this->random_token();
		$now       = current_time( 'mysql', true );
		$expires   = gmdate( 'Y-m-d H:i:s', time() + self::DEFAULT_EXPIRY_HRS * HOUR_IN_SECONDS );
		$inserted  = $wpdb->insert(
			self::runs_table(),
			array(
				'run_uuid'            => $run_uuid,
				'owner_user_id'       => isset( $input['owner_user_id'] ) ? absint( $input['owner_user_id'] ) : 0,
				'owner_token_hash'     => absint( $input['owner_user_id'] ?? 0 ) > 0 ? '' : hash( 'sha256', $token ),
				'request_fingerprint'  => (string) $input['request_fingerprint'],
				'status'               => 'created',
				'mode'                 => (string) $input['mode'],
				'locale'               => (string) $input['locale'],
				'input_kind'           => (string) $input['input_kind'],
				// Raw natural-language intake is processed in memory and is never
				// persisted. The request fingerprint and policy source hash retain
				// audit continuity without storing free-form personal details.
				'input_text'           => '',
				'trip_request'         => null,
				'proposals'            => wp_json_encode( array() ),
				'provider_state'       => wp_json_encode( array() ),
				'created_at'           => $now,
				'updated_at'           => $now,
				'expires_at'           => $expires,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			return new WP_Error( 'tra_vel_agent_run_store_failed', 'The private agent run could not be created.', array( 'status' => 500 ) );
		}
		return array(
			'id'        => (int) $wpdb->insert_id,
			'run_uuid'  => $run_uuid,
			'run_token' => $token,
		);
	}

	public function update_run( $run_id, $fields ) {
		global $wpdb;
		$allowed = array( 'status', 'trip_request', 'proposals', 'provider_state' );
		$data    = array();
		$formats = array();
		foreach ( $allowed as $field ) {
			if ( ! array_key_exists( $field, $fields ) ) {
				continue;
			}
			$value = in_array( $field, array( 'trip_request', 'proposals', 'provider_state' ), true ) ? wp_json_encode( $fields[ $field ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : (string) $fields[ $field ];
			$data[ $field ] = $value;
			$formats[]      = '%s';
		}
		$data['updated_at'] = current_time( 'mysql', true );
		$formats[]          = '%s';
		return false !== $wpdb->update( self::runs_table(), $data, array( 'id' => absint( $run_id ) ), $formats, array( '%d' ) );
	}

	/**
	 * Persist a model revision only while the run still has the owner and
	 * version timestamp captured before the provider call. Account claiming can
	 * therefore invalidate a stale in-flight guest revision.
	 *
	 * @param int   $run_id         Run database ID.
	 * @param array $fields         Allowed replacement fields.
	 * @param array $owner_guard    owner_user_id/owner_token_hash/updated_at.
	 * @return bool
	 */
	public function update_run_if_owner( $run_id, $fields, $owner_guard ) {
		global $wpdb;
		$allowed = array( 'status', 'trip_request', 'proposals', 'provider_state' );
		$data    = array();
		$formats = array();
		foreach ( $allowed as $field ) {
			if ( ! array_key_exists( $field, $fields ) ) {
				continue;
			}
			$data[ $field ] = in_array( $field, array( 'trip_request', 'proposals', 'provider_state' ), true )
				? wp_json_encode( $fields[ $field ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
				: (string) $fields[ $field ];
			$formats[] = '%s';
		}
		$data['updated_at'] = current_time( 'mysql', true );
		$formats[]          = '%s';
		$where = array(
			'id'            => absint( $run_id ),
			'owner_user_id' => absint( $owner_guard['owner_user_id'] ?? 0 ),
			'updated_at'    => (string) ( $owner_guard['updated_at'] ?? '' ),
		);
		$where_formats = array( '%d', '%d', '%s' );
		if ( 0 === (int) $where['owner_user_id'] ) {
			$token_hash = (string) ( $owner_guard['owner_token_hash'] ?? '' );
			if ( 64 !== strlen( $token_hash ) ) {
				return false;
			}
			$where['owner_token_hash'] = $token_hash;
			$where_formats[]           = '%s';
		}
		return 1 === $wpdb->update( self::runs_table(), $data, $where, $formats, $where_formats );
	}

	public function append_event( $run_id, $event ) {
		global $wpdb;
		$sequence = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(MAX(sequence_no),0) FROM ' . self::events_table() . ' WHERE run_id = %d', absint( $run_id ) ) );
		$event_type = strtolower( (string) $event['type'] );
		$event_type = preg_replace( '/[^a-z0-9._-]/', '', $event_type );
		$inserted = $wpdb->insert(
			self::events_table(),
			array(
				'run_id'      => absint( $run_id ),
				'sequence_no' => $sequence,
				'event_uuid'  => wp_generate_uuid4(),
				'event_type'  => $event_type,
				'phase'       => sanitize_key( (string) $event['phase'] ),
				'status'      => sanitize_key( (string) $event['status'] ),
				'source'      => sanitize_key( (string) $event['source'] ),
				'visible'     => isset( $event['visible'] ) ? (int) (bool) $event['visible'] : 1,
				'message'     => sanitize_text_field( (string) $event['message'] ),
				'payload'     => wp_json_encode( isset( $event['data'] ) ? $event['data'] : array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return false === $inserted ? new WP_Error( 'tra_vel_agent_event_store_failed', 'The run event could not be recorded.', array( 'status' => 500 ) ) : $sequence;
	}

	public function get_run_by_uuid( $run_uuid, &$read_error = null ) {
		global $wpdb;
		$was_suppressed = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::runs_table() . ' WHERE run_uuid = %s LIMIT 1', (string) $run_uuid ), ARRAY_A );
		$read_error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		return is_array( $row ) ? $this->hydrate_run( $row ) : null;
	}

	public function get_events( $run_id, $after = 0 ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::events_table() . ' WHERE run_id = %d AND sequence_no > %d ORDER BY sequence_no ASC', absint( $run_id ), absint( $after ) ), ARRAY_A );
		return array_map( array( $this, 'hydrate_event' ), is_array( $rows ) ? $rows : array() );
	}

	public function find_recent_fingerprint( $fingerprint ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 10 * MINUTE_IN_SECONDS );
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::runs_table() . ' WHERE request_fingerprint = %s AND created_at >= %s', (string) $fingerprint, $cutoff ) ) > 0;
	}

	public function can_access( $run, $token, $user_id ) {
		if ( ! is_array( $run ) || strtotime( $run['expires_at'] . ' UTC' ) < time() ) {
			return false;
		}
		if ( $user_id > 0 && (int) $run['owner_user_id'] === (int) $user_id ) {
			return true;
		}
		return 0 === (int) $run['owner_user_id'] && is_string( $token ) && strlen( $token ) >= 32 && 64 === strlen( (string) $run['owner_token_hash'] ) && hash_equals( (string) $run['owner_token_hash'], hash( 'sha256', $token ) );
	}

	public function get_approval( $run_id, $approval_uuid ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::approvals_table() . ' WHERE run_id = %d AND approval_uuid = %s LIMIT 1', absint( $run_id ), (string) $approval_uuid ), ARRAY_A );
		return is_array( $row ) ? $this->hydrate_approval( $row ) : null;
	}

	public function decide_approval( $approval, $decision, $decision_key, $user_id ) {
		global $wpdb;
		if ( ! is_array( $approval ) || 'pending' !== $approval['status'] ) {
			return new WP_Error( 'tra_vel_agent_approval_not_pending', 'The approval is no longer pending.', array( 'status' => 409 ) );
		}
		if ( strtotime( $approval['expires_at'] . ' UTC' ) <= time() ) {
			$wpdb->update( self::approvals_table(), array( 'status' => 'expired' ), array( 'id' => (int) $approval['id'] ), array( '%s' ), array( '%d' ) );
			return new WP_Error( 'tra_vel_agent_approval_expired', 'The approval has expired.', array( 'status' => 409 ) );
		}
		$updated = $wpdb->update(
			self::approvals_table(),
			array(
				'status'       => 'approve' === $decision ? 'approved' : 'rejected',
				'decision_key' => (string) $decision_key,
				'decided_by'   => absint( $user_id ),
				'decided_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $approval['id'], 'status' => 'pending' ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d', '%s' )
		);
		return 1 === $updated ? $this->get_approval( (int) $approval['run_id'], $approval['approval_uuid'] ) : new WP_Error( 'tra_vel_agent_approval_conflict', 'The approval changed before the decision was saved.', array( 'status' => 409 ) );
	}

	private function hydrate_run( $row ) {
		foreach ( array( 'trip_request', 'proposals', 'provider_state' ) as $field ) {
			$decoded       = json_decode( (string) $row[ $field ], true );
			$row[ $field ] = is_array( $decoded ) ? $decoded : array();
		}
		$row['id']            = (int) $row['id'];
		$row['owner_user_id'] = (int) $row['owner_user_id'];
		return $row;
	}

	private function hydrate_event( $row ) {
		$payload = json_decode( (string) $row['payload'], true );
		$legacy_types = array(
			'run_created'                    => 'run.created',
			'request_interpretation_started' => 'request.interpretation.started',
			'request_interpretation_failed'  => 'request.interpretation.failed',
			'request_interpreted'            => 'request.interpreted',
			'clarification_required'         => 'clarification.required',
			'request_ready'                  => 'request.ready',
			'supplier_search_not_started'    => 'supplier.search.not_started',
			'approval_decided'               => 'approval.decided',
		);
		$stored_type = (string) $row['event_type'];
		return array(
			'contract_version' => '1.0.0',
			'event_id'        => $row['event_uuid'],
			'sequence'        => (int) $row['sequence_no'],
			'occurred_at'     => gmdate( 'c', strtotime( $row['created_at'] . ' UTC' ) ),
			'type'            => isset( $legacy_types[ $stored_type ] ) ? $legacy_types[ $stored_type ] : $stored_type,
			'phase'           => $row['phase'],
			'status'          => $row['status'],
			'source'          => $row['source'],
			'visible'         => (bool) $row['visible'],
			'message'         => $row['message'],
			'data'            => is_array( $payload ) ? $payload : array(),
		);
	}

	private function hydrate_approval( $row ) {
		$row['id']              = (int) $row['id'];
		$row['run_id']          = (int) $row['run_id'];
		$row['approval_version'] = (int) $row['approval_version'];
		$row['summary']         = json_decode( (string) $row['summary'], true );
		$row['action_snapshot'] = json_decode( (string) $row['action_snapshot'], true );
		return $row;
	}

	private function random_token() {
		$bytes = random_bytes( 32 );
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}

	/**
	 * Convert legacy/default engines before enabling ownership transactions.
	 *
	 * @return bool
	 */
	private static function ensure_transactional_tables() {
		global $wpdb;
		$tables = array( self::runs_table(), self::events_table(), self::approvals_table(), self::limits_table() );
		foreach ( $tables as $table ) {
			$identifier = str_replace( '`', '``', (string) $table );
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', (string) $table ), ARRAY_A );
			if ( ! is_array( $status ) ) {
				return false;
			}
			if ( 'innodb' !== strtolower( (string) ( $status['Engine'] ?? '' ) ) && false === $wpdb->query( "ALTER TABLE `{$identifier}` ENGINE=InnoDB" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted WordPress-prefixed identifier.
				return false;
			}
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', (string) $table ), ARRAY_A );
			if ( ! is_array( $status ) || 'innodb' !== strtolower( (string) ( $status['Engine'] ?? '' ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Inspect every runtime column and required unique/primary index.
	 *
	 * @return array<string,int|bool>
	 */
	private static function inspect_schema() {
		global $wpdb;
		$requirements = array(
			self::runs_table() => array(
				'columns' => array( 'id', 'run_uuid', 'owner_user_id', 'owner_token_hash', 'request_fingerprint', 'status', 'mode', 'locale', 'input_kind', 'input_text', 'trip_request', 'proposals', 'provider_state', 'created_at', 'updated_at', 'expires_at' ),
				'unique'  => array( array( 'id' ), array( 'run_uuid' ) ),
			),
			self::events_table() => array(
				'columns' => array( 'id', 'run_id', 'sequence_no', 'event_uuid', 'event_type', 'phase', 'status', 'source', 'visible', 'message', 'payload', 'created_at' ),
				'unique'  => array( array( 'id' ), array( 'event_uuid' ), array( 'run_id', 'sequence_no' ) ),
			),
			self::approvals_table() => array(
				'columns' => array( 'id', 'approval_uuid', 'run_id', 'approval_version', 'action_type', 'scope_digest', 'status', 'summary', 'action_snapshot', 'decision_key', 'decided_by', 'created_at', 'expires_at', 'decided_at' ),
				'unique'  => array( array( 'id' ), array( 'approval_uuid' ), array( 'decision_key' ) ),
			),
			self::limits_table() => array(
				'columns' => array( 'counter_key', 'counter_value', 'expires_at' ),
				'unique'  => array( array( 'counter_key' ) ),
			),
		);
		$ready                = 0;
		$transactional_tables = 0;
		$required_indexes     = 0;
		$ready_indexes        = 0;
		$was_suppressed       = $wpdb->suppress_errors();
		foreach ( $requirements as $table => $requirement ) {
			$identifier = str_replace( '`', '``', (string) $table );
			$columns    = $wpdb->get_col( "SHOW COLUMNS FROM `{$identifier}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted WordPress-prefixed identifier.
			$status     = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', (string) $table ), ARRAY_A );
			$indexes    = $wpdb->get_results( "SHOW INDEX FROM `{$identifier}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted WordPress-prefixed identifier.
			$engine_ok  = is_array( $status ) && 'innodb' === strtolower( (string) ( $status['Engine'] ?? '' ) );
			if ( $engine_ok ) {
				$transactional_tables++;
			}
			$unique_indexes = array();
			foreach ( is_array( $indexes ) ? $indexes : array() as $index ) {
				if ( 0 !== (int) ( $index['Non_unique'] ?? 1 ) ) {
					continue;
				}
				$key = (string) ( $index['Key_name'] ?? '' );
				$seq = max( 1, (int) ( $index['Seq_in_index'] ?? 1 ) );
				$unique_indexes[ $key ][ $seq ] = (string) ( $index['Column_name'] ?? '' );
			}
			foreach ( $unique_indexes as &$index_columns ) {
				ksort( $index_columns );
				$index_columns = array_values( $index_columns );
			}
			unset( $index_columns );
			$table_ready_indexes = 0;
			$required_indexes    += count( $requirement['unique'] );
			foreach ( $requirement['unique'] as $required_index ) {
				foreach ( $unique_indexes as $index_columns ) {
					if ( $required_index === $index_columns ) {
						$table_ready_indexes++;
						$ready_indexes++;
						break;
					}
				}
			}
			if ( is_array( $columns ) && ! array_diff( $requirement['columns'], $columns ) && $engine_ok && count( $requirement['unique'] ) === $table_ready_indexes ) {
				$ready++;
			}
		}
		$wpdb->suppress_errors( $was_suppressed );
		$expected = count( $requirements );
		return array(
			'expected_tables'        => $expected,
			'ready_tables'           => $ready,
			'transactional_tables'   => $transactional_tables,
			'required_indexes'       => $required_indexes,
			'ready_indexes'          => $ready_indexes,
			'required_indexes_ready' => $required_indexes === $ready_indexes,
			'tables_ready'           => $expected === $ready && $expected === $transactional_tables && $required_indexes === $ready_indexes,
		);
	}

	public static function runs_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_agent_runs';
	}

	public static function events_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_agent_events';
	}

	public static function approvals_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_agent_approvals';
	}

	public static function limits_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_agent_limits';
	}
}
