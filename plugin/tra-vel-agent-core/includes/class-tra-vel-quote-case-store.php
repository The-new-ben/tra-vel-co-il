<?php
/**
 * Durable assisted-quote aggregate with immutable revisions and events.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Quote_Case_Store {
	const DB_VERSION        = '1.0.1';
	const DB_VERSION_OPTION = 'tra_vel_quote_case_db_version';
	const ACTIVE_DAYS       = 30;
	const RETENTION_DAYS    = 90;
	const EVENT_PAGE_SIZE   = 50;
	const EMBEDDED_EVENTS   = 20;
	const HANDOFF_REUSE_SECONDS = 300;
	const HANDOFF_MIN_REMAINING_SECONDS = 30;
	const HANDOFF_WINDOW_SECONDS = 3600;
	const HANDOFF_WINDOW_LIMIT   = 6;
	const CLEANUP_BATCH_SIZE             = 100;
	const CLEANUP_IDEMPOTENCY_BATCH_SIZE = 1000;
	const CLEANUP_MAX_BATCHES            = 10;
	const CLEANUP_MAX_SECONDS            = 20;
	const CLEANUP_STATUS_OPTION          = 'tra_vel_quote_case_cleanup_status';
	const SYNC_RETRY_LIMIT               = 5;

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
		$charset     = $wpdb->get_charset_collate();
		$cases       = self::cases_table();
		$revisions   = self::revisions_table();
		$events      = self::events_table();
		$idempotency = self::idempotency_table();

		dbDelta( "CREATE TABLE {$cases} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			case_uuid char(36) NOT NULL,
			reference_code varchar(16) NOT NULL,
			source_run_id bigint(20) unsigned NOT NULL,
			source_run_uuid char(36) NOT NULL,
			source_request_uuid char(36) NOT NULL,
			source_request_revision int(10) unsigned NOT NULL DEFAULT 1,
			owner_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			owner_token_hash char(64) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL,
			case_version bigint(20) unsigned NOT NULL DEFAULT 1,
			current_revision int(10) unsigned NOT NULL DEFAULT 1,
			latest_request_digest char(64) NOT NULL,
			last_event_sequence bigint(20) unsigned NOT NULL DEFAULT 1,
			service_mode varchar(32) NOT NULL DEFAULT 'assisted_quote',
			assigned_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			consent_version varchar(24) NOT NULL,
			consented_at datetime NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			last_activity_at datetime NOT NULL,
			service_expires_at datetime NOT NULL,
			retention_until datetime NOT NULL,
			closed_at datetime NULL,
			legal_hold tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY case_uuid (case_uuid),
			UNIQUE KEY reference_code (reference_code),
			UNIQUE KEY source_run_uuid (source_run_uuid),
			KEY owner_status (owner_user_id,status),
			KEY guest_owner (owner_token_hash,status),
			KEY queue_status (status,last_activity_at),
			KEY assignee_status (assigned_user_id,status),
			KEY service_expiry (service_expires_at),
			KEY retention (retention_until,legal_hold)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$revisions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			case_id bigint(20) unsigned NOT NULL,
			revision_no int(10) unsigned NOT NULL,
			source_request_uuid char(36) NOT NULL,
			source_request_revision int(10) unsigned NOT NULL,
			request_digest char(64) NOT NULL,
			request_snapshot longtext NOT NULL,
			actor_type varchar(20) NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY case_revision (case_id,revision_no),
			KEY case_digest (case_id,request_digest),
			KEY case_id (case_id)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			case_id bigint(20) unsigned NOT NULL,
			sequence_no bigint(20) unsigned NOT NULL,
			case_version bigint(20) unsigned NOT NULL,
			event_uuid char(36) NOT NULL,
			event_type varchar(64) NOT NULL,
			from_status varchar(32) NULL,
			to_status varchar(32) NOT NULL,
			actor_type varchar(20) NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source varchar(24) NOT NULL,
			visibility varchar(16) NOT NULL DEFAULT 'public',
			message text NOT NULL,
			payload longtext NULL,
			payload_digest char(64) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_uuid (event_uuid),
			UNIQUE KEY case_sequence (case_id,sequence_no),
			KEY case_id (case_id),
			KEY event_type (event_type)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$idempotency} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			operation_scope varchar(40) NOT NULL,
			principal_hash char(64) NOT NULL,
			idempotency_key_hash char(64) NOT NULL,
			request_digest char(64) NOT NULL,
			case_uuid char(36) NOT NULL,
			case_version bigint(20) unsigned NOT NULL,
			response_code smallint(5) unsigned NOT NULL DEFAULT 200,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY operation_key (operation_scope,principal_hash,idempotency_key_hash),
			KEY expires_at (expires_at),
			KEY case_uuid (case_uuid)
		) ENGINE=InnoDB {$charset};" );

		// dbDelta does not reliably change an existing index from UNIQUE to
		// non-unique. An early pre-release schema used a unique case_digest,
		// which prevented a valid A -> B -> A itinerary history. Repair that
		// index explicitly before advertising the migration as complete.
		$digest_index_ready = self::ensure_revision_digest_index();

		self::$ready_cache = null;
		$readiness = self::inspect_schema();
		if ( $digest_index_ready && $readiness['tables_ready'] ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		} else {
			// Never advertise a successful migration when dbDelta was incomplete.
			delete_option( self::DB_VERSION_OPTION );
		}
	}

	/**
	 * Report code and installed database readiness without exposing table names.
	 * The deploy pipeline uses this as its authoritative post-install gate.
	 *
	 * @return array<string,mixed>
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
			'required_supporting_indexes' => (int) $readiness['required_supporting_indexes'],
			'ready_supporting_indexes'    => (int) $readiness['ready_supporting_indexes'],
			'supporting_indexes_ready'    => (bool) $readiness['supporting_indexes_ready'],
			'tables_ready'             => (bool) $readiness['tables_ready'],
			'active_days'              => self::ACTIVE_DAYS,
			'retention_days'           => self::RETENTION_DAYS,
		);
	}

	/**
	 * Fail closed when the installed schema cannot uphold transaction and
	 * idempotency guarantees. Cached only for the current PHP request.
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
	 * Create or idempotently replay one case from a server-owned ready run.
	 *
	 * @param array  $run                Hydrated run.
	 * @param array  $principal          user_id/token_hash/principal_hash.
	 * @param string $consent_version    Accepted policy version.
	 * @param string $idempotency_key    Caller operation key.
	 * @return array|WP_Error {case,replayed,created}.
	 */
	public function create_from_run( $run, $principal, $consent_version, $idempotency_key ) {
		global $wpdb;
		$valid = Tra_Vel_Quote_Case_Policy::validate_ready_run( $run );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$snapshot          = Tra_Vel_Quote_Case_Policy::snapshot( $run['trip_request'] );
		$request_digest    = Tra_Vel_Quote_Case_Policy::digest( $snapshot );
		$operation_digest  = hash( 'sha256', implode( '|', array( $run['run_uuid'], $request_digest, $consent_version ) ) );
		$principal_hash    = (string) $principal['principal_hash'];
		$idempotency_key   = $this->sanitize_idempotency_key( $idempotency_key );
		$replay            = $this->idempotent_result( 'case.create', $principal_hash, $idempotency_key, $operation_digest );
		if ( $replay ) {
			return is_wp_error( $replay ) ? $replay : array( 'case' => $replay, 'replayed' => true, 'created' => false );
		}

		$case_read_error = '';
		$existing = $this->get_case_by_source_run( $run['run_uuid'], $case_read_error );
		if ( '' !== $case_read_error ) {
			return new WP_Error( 'tra_vel_quote_case_read_failed', 'The existing assisted quote could not be checked safely.', array( 'status' => 503 ) );
		}
		if ( $existing ) {
			$synced = $this->sync_from_run( $run );
			if ( is_array( $synced ) ) {
				$existing = $synced;
			}
			if ( ! $this->record_idempotency( 'case.create', $principal_hash, $idempotency_key, $operation_digest, $existing['case_uuid'], $existing['case_version'], 200 ) ) {
				$recorded = $this->idempotent_result( 'case.create', $principal_hash, $idempotency_key, $operation_digest );
				if ( is_wp_error( $recorded ) ) {
					return $recorded;
				}
				if ( ! $recorded ) {
					return new WP_Error( 'tra_vel_quote_case_idempotency_failed', 'The quote request could not be recorded safely.', array( 'status' => 500 ) );
				}
			}
			return array( 'case' => $existing, 'replayed' => true, 'created' => false );
		}

		$now             = current_time( 'mysql', true );
		$case_uuid       = wp_generate_uuid4();
		$reference       = $this->unique_reference();
		$service_expires = gmdate( 'Y-m-d H:i:s', time() + self::ACTIVE_DAYS * DAY_IN_SECONDS );
		$retention       = gmdate( 'Y-m-d H:i:s', time() + self::RETENTION_DAYS * DAY_IN_SECONDS );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'tra_vel_quote_case_transaction_failed', 'The quote request transaction could not be started.', array( 'status' => 500 ) );
		}
		if ( absint( $principal['user_id'] ) > 0 && ! $this->bind_source_run_owner( absint( $run['id'] ), (string) $run['run_uuid'], absint( $principal['user_id'] ) ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_quote_case_source_owner_conflict', 'The private source plan belongs to a different account.', array( 'status' => 409 ) );
		}
		$inserted = $wpdb->insert(
			self::cases_table(),
			array(
				'case_uuid'               => $case_uuid,
				'reference_code'          => $reference,
				'source_run_id'           => absint( $run['id'] ),
				'source_run_uuid'         => (string) $run['run_uuid'],
				'source_request_uuid'     => (string) $snapshot['request_id'],
				'source_request_revision' => absint( $snapshot['revision'] ),
				'owner_user_id'           => absint( $principal['user_id'] ),
				'owner_token_hash'        => absint( $principal['user_id'] ) > 0 ? '' : (string) $principal['token_hash'],
				'status'                  => 'queued',
				'case_version'            => 1,
				'current_revision'        => 1,
				'latest_request_digest'   => $request_digest,
				'last_event_sequence'     => 1,
				'service_mode'            => 'assisted_quote',
				'assigned_user_id'        => 0,
				'consent_version'         => (string) $consent_version,
				'consented_at'            => $now,
				'created_at'              => $now,
				'updated_at'              => $now,
				'last_activity_at'        => $now,
				'service_expires_at'      => $service_expires,
				'retention_until'         => $retention,
				'closed_at'               => null,
				'legal_hold'              => 0,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			$replayed = $this->idempotent_result( 'case.create', $principal_hash, $idempotency_key, $operation_digest );
			if ( $replayed ) {
				return is_wp_error( $replayed ) ? $replayed : array( 'case' => $replayed, 'replayed' => true, 'created' => false );
			}
			$concurrent = $this->get_case_by_source_run( $run['run_uuid'] );
			if ( $concurrent ) {
				if ( ! $this->record_idempotency( 'case.create', $principal_hash, $idempotency_key, $operation_digest, $concurrent['case_uuid'], $concurrent['case_version'], 200 ) ) {
					$recorded = $this->idempotent_result( 'case.create', $principal_hash, $idempotency_key, $operation_digest );
					if ( is_wp_error( $recorded ) ) {
						return $recorded;
					}
					if ( ! $recorded ) {
						return new WP_Error( 'tra_vel_quote_case_idempotency_failed', 'The concurrent quote request could not be recorded safely.', array( 'status' => 500 ) );
					}
				}
				return array( 'case' => $concurrent, 'replayed' => true, 'created' => false );
			}
			return new WP_Error( 'tra_vel_quote_case_store_failed', 'The assisted quote case could not be created.', array( 'status' => 500 ) );
		}
		$case_id = (int) $wpdb->insert_id;
		if ( ! $this->insert_revision( $case_id, 1, $snapshot, $request_digest, 'traveler', absint( $principal['user_id'] ) ) ) {
			$wpdb->query( 'ROLLBACK' );
			$replayed = $this->idempotent_result( 'case.create', $principal_hash, $idempotency_key, $operation_digest );
			if ( $replayed ) {
				return is_wp_error( $replayed ) ? $replayed : array( 'case' => $replayed, 'replayed' => true, 'created' => false );
			}
			return new WP_Error( 'tra_vel_quote_case_revision_failed', 'The quote request snapshot could not be frozen.', array( 'status' => 500 ) );
		}
		$event = $this->insert_event(
			$case_id,
			1,
			1,
			'quote_case.created',
			null,
			'queued',
			'traveler',
			absint( $principal['user_id'] ),
			'web',
			'public',
			'בקשת הסיוע התקבלה ונכנסה לתור בדיקה אנושית.',
			array( 'request_revision' => absint( $snapshot['revision'] ), 'service_mode' => 'assisted_quote' )
		);
		if ( ! $event || ! $this->insert_idempotency( 'case.create', $principal_hash, $idempotency_key, $operation_digest, $case_uuid, 1, 201 ) ) {
			$wpdb->query( 'ROLLBACK' );
			$replayed = $this->idempotent_result( 'case.create', $principal_hash, $idempotency_key, $operation_digest );
			if ( $replayed ) {
				return is_wp_error( $replayed ) ? $replayed : array( 'case' => $replayed, 'replayed' => true, 'created' => false );
			}
			return new WP_Error( 'tra_vel_quote_case_transaction_failed', 'The quote request could not be committed safely.', array( 'status' => 500 ) );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$replayed = $this->idempotent_result( 'case.create', $principal_hash, $idempotency_key, $operation_digest );
			if ( $replayed ) {
				return is_wp_error( $replayed ) ? $replayed : array( 'case' => $replayed, 'replayed' => true, 'created' => false );
			}
			return new WP_Error( 'tra_vel_quote_case_transaction_failed', 'The quote request transaction could not be committed.', array( 'status' => 500 ) );
		}
		return array( 'case' => $this->get_case_by_uuid( $case_uuid ), 'replayed' => false, 'created' => true );
	}

	public function get_case_by_uuid( $case_uuid ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::cases_table() . ' WHERE case_uuid = %s LIMIT 1', (string) $case_uuid ), ARRAY_A );
		return is_array( $row ) ? $this->hydrate_case( $row ) : null;
	}

	public function get_case_by_source_run( $run_uuid, &$read_error = null ) {
		global $wpdb;
		$was_suppressed = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::cases_table() . ' WHERE source_run_uuid = %s LIMIT 1', (string) $run_uuid ), ARRAY_A );
		$read_error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		return is_array( $row ) ? $this->hydrate_case( $row ) : null;
	}

	public function can_access( $case, $user_id, $guest_token_hash ) {
		if ( ! is_array( $case ) ) {
			return false;
		}
		if ( $user_id > 0 && (int) $case['owner_user_id'] === (int) $user_id ) {
			return true;
		}
		return 0 === (int) $case['owner_user_id'] && strlen( (string) $guest_token_hash ) === 64 && strlen( (string) $case['owner_token_hash'] ) === 64 && hash_equals( (string) $case['owner_token_hash'], (string) $guest_token_hash );
	}

	private function owner_guard( $case, $principal ) {
		if ( ! is_array( $case ) || ! is_array( $principal ) ) {
			return null;
		}
		$user_id    = absint( $principal['user_id'] ?? 0 );
		$token_hash = (string) ( $principal['token_hash'] ?? '' );
		if ( $user_id > 0 && (int) $case['owner_user_id'] === $user_id ) {
			return array( 'owner_user_id' => $user_id, 'owner_token_hash' => '' );
		}
		if ( 0 === (int) $case['owner_user_id'] && 64 === strlen( $token_hash ) && 64 === strlen( (string) $case['owner_token_hash'] ) && hash_equals( (string) $case['owner_token_hash'], $token_hash ) ) {
			return array( 'owner_user_id' => 0, 'owner_token_hash' => $token_hash );
		}
		return null;
	}

	private function owner_guard_matches( $case, $owner_guard ) {
		if ( ! is_array( $case ) || ! is_array( $owner_guard ) ) {
			return false;
		}
		$user_id = absint( $owner_guard['owner_user_id'] ?? 0 );
		if ( $user_id > 0 ) {
			return (int) $case['owner_user_id'] === $user_id;
		}
		$token_hash = (string) ( $owner_guard['owner_token_hash'] ?? '' );
		return 0 === (int) $case['owner_user_id'] && 64 === strlen( $token_hash ) && 64 === strlen( (string) $case['owner_token_hash'] ) && hash_equals( (string) $case['owner_token_hash'], $token_hash );
	}

	/**
	 * Bind the private source AgentRun to the quote's account owner and clear
	 * its bearer credential. The caller holds a quote-case transaction and has
	 * already proven ownership of the quote or source run.
	 *
	 * @param int    $run_id   Source run database ID.
	 * @param string $run_uuid Source run UUID.
	 * @param int    $user_id  New account owner.
	 * @return bool
	 */
	private function bind_source_run_owner( $run_id, $run_uuid, $user_id ) {
		global $wpdb;
		if ( $user_id < 1 || ! class_exists( 'Tra_Vel_Agent_Store' ) ) {
			return false;
		}
		$table = Tra_Vel_Agent_Store::runs_table();
		$was_suppressed = $wpdb->suppress_errors();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT owner_user_id FROM ' . $table . ' WHERE id = %d AND run_uuid = %s LIMIT 1 FOR UPDATE',
				absint( $run_id ),
				(string) $run_uuid
			),
			ARRAY_A
		);
		$read_error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( ! is_array( $row ) ) {
			// AgentRuns expire after one day while quote cases remain active for
			// thirty. A definitively absent source has no bearer left to revoke and
			// must not prevent the still-owned quote from being claimed later.
			return '' === $read_error;
		}
		if ( (int) $row['owner_user_id'] > 0 && (int) $row['owner_user_id'] !== (int) $user_id ) {
			return false;
		}
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $table . ' SET owner_user_id = %d, owner_token_hash = %s, updated_at = %s WHERE id = %d AND run_uuid = %s AND owner_user_id IN (0,%d)',
				absint( $user_id ),
				'',
				current_time( 'mysql', true ),
				absint( $run_id ),
				(string) $run_uuid,
				absint( $user_id )
			)
		);
		return false !== $updated;
	}

	public function list_owned( $user_id, $guest_token_hash, $limit = 30 ) {
		global $wpdb;
		$limit = min( 50, max( 1, absint( $limit ) ) );
		$select = 'SELECT c.*,r.request_snapshot AS current_request_snapshot FROM ' . self::cases_table() . ' c LEFT JOIN ' . self::revisions_table() . ' r ON r.case_id = c.id AND r.revision_no = c.current_revision';
		if ( $user_id > 0 && strlen( (string) $guest_token_hash ) === 64 ) {
			$rows = $wpdb->get_results( $wpdb->prepare( $select . ' WHERE (c.owner_user_id = %d OR (c.owner_user_id = 0 AND c.owner_token_hash = %s)) ORDER BY c.last_activity_at DESC LIMIT %d', absint( $user_id ), (string) $guest_token_hash, $limit ), ARRAY_A );
		} elseif ( $user_id > 0 ) {
			$rows = $wpdb->get_results( $wpdb->prepare( $select . ' WHERE c.owner_user_id = %d ORDER BY c.last_activity_at DESC LIMIT %d', absint( $user_id ), $limit ), ARRAY_A );
		} elseif ( strlen( (string) $guest_token_hash ) === 64 ) {
			$rows = $wpdb->get_results( $wpdb->prepare( $select . ' WHERE c.owner_user_id = 0 AND c.owner_token_hash = %s ORDER BY c.last_activity_at DESC LIMIT %d', (string) $guest_token_hash, $limit ), ARRAY_A );
		} else {
			$rows = array();
		}
		$cases = array_map( array( $this, 'hydrate_case' ), is_array( $rows ) ? $rows : array() );
		return $this->attach_creation_events( $cases, false );
	}

	public function list_operator( $status = '', $page = 1, $per_page = 30 ) {
		global $wpdb;
		$page     = max( 1, absint( $page ) );
		$per_page = min( 50, max( 1, absint( $per_page ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$list_where  = '';
		$count_where = '';
		$params   = array();
		if ( in_array( $status, Tra_Vel_Quote_Case_Policy::statuses(), true ) ) {
			$list_where  = ' WHERE c.status = %s';
			$count_where = ' WHERE status = %s';
			$params[] = $status;
		}
		$params[] = $per_page;
		$params[] = $offset;
		$sql      = 'SELECT c.*,r.request_snapshot AS current_request_snapshot FROM ' . self::cases_table() . ' c LEFT JOIN ' . self::revisions_table() . ' r ON r.case_id = c.id AND r.revision_no = c.current_revision' . $list_where . ' ORDER BY c.last_activity_at DESC LIMIT %d OFFSET %d';
		$rows     = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		$count_sql = 'SELECT COUNT(*) FROM ' . self::cases_table() . $count_where;
		$count     = $count_where ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $status ) ) : (int) $wpdb->get_var( $count_sql );
		return array(
			'cases' => $this->attach_creation_events( array_map( array( $this, 'hydrate_case' ), is_array( $rows ) ? $rows : array() ), true ),
			'total' => $count,
		);
	}

	public function get_event_page( $case_id, $after = 0, $include_internal = false, $limit = self::EVENT_PAGE_SIZE ) {
		global $wpdb;
		$limit  = min( self::EVENT_PAGE_SIZE, max( 1, absint( $limit ) ) );
		$sql    = 'SELECT * FROM ' . self::events_table() . ' WHERE case_id = %d AND sequence_no > %d';
		$params = array( absint( $case_id ), absint( $after ) );
		if ( ! $include_internal ) {
			$sql .= ' AND visibility = %s';
			$params[] = 'public';
		}
		$sql     .= ' ORDER BY sequence_no ASC LIMIT %d';
		$params[] = $limit + 1;
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		$rows = is_array( $rows ) ? $rows : array();
		$has_more = count( $rows ) > $limit;
		if ( $has_more ) {
			$rows = array_slice( $rows, 0, $limit );
		}
		$events = array_map( array( $this, 'hydrate_event' ), $rows );
		return array(
			'events'        => $events,
			'last_sequence' => $events ? (int) end( $events )['sequence'] : absint( $after ),
			'has_more'      => $has_more,
		);
	}

	public function get_events( $case_id, $after = 0, $include_internal = false, $limit = self::EVENT_PAGE_SIZE ) {
		$page = $this->get_event_page( $case_id, $after, $include_internal, $limit );
		return $page['events'];
	}

	public function get_recent_events( $case_id, $include_internal = false, $limit = self::EMBEDDED_EVENTS ) {
		global $wpdb;
		$limit  = min( self::EVENT_PAGE_SIZE, max( 1, absint( $limit ) ) );
		$sql    = 'SELECT * FROM ' . self::events_table() . ' WHERE case_id = %d AND sequence_no = 1';
		$params = array( absint( $case_id ) );
		if ( ! $include_internal ) {
			$sql .= ' AND visibility = %s';
			$params[] = 'public';
		}
		$sql     .= ' LIMIT 1';
		$creation = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );
		$rows = is_array( $creation ) ? array( $creation ) : array();
		if ( $limit > 1 ) {
			$tail_sql    = 'SELECT * FROM ' . self::events_table() . ' WHERE case_id = %d AND sequence_no > 1';
			$tail_params = array( absint( $case_id ) );
			if ( ! $include_internal ) {
				$tail_sql .= ' AND visibility = %s';
				$tail_params[] = 'public';
			}
			$tail_sql     .= ' ORDER BY sequence_no DESC LIMIT %d';
			$tail_params[] = $limit - 1;
			$tail = $wpdb->get_results( $wpdb->prepare( $tail_sql, $tail_params ), ARRAY_A );
			if ( is_array( $tail ) ) {
				$rows = array_merge( $rows, array_reverse( $tail ) );
			}
		}
		return array_map( array( $this, 'hydrate_event' ), $rows );
	}

	/**
	 * Optimistic, transactional operator state transition.
	 */
	public function transition( $case_uuid, $to_status, $expected_version, $actor_user_id, $idempotency_key ) {
		$case = $this->get_case_by_uuid( $case_uuid );
		if ( ! $case ) {
			return new WP_Error( 'tra_vel_quote_case_missing', 'Quote case not found.', array( 'status' => 404 ) );
		}
		if ( (int) $case['case_version'] === (int) $expected_version && ! Tra_Vel_Quote_Case_Policy::can_transition( $case['status'], $to_status ) ) {
			return new WP_Error( 'tra_vel_quote_case_transition_invalid', 'That quote-case transition is not allowed.', array( 'status' => 409, 'current_status' => $case['status'] ) );
		}
		return $this->mutate_case(
			$case,
			$to_status,
			$expected_version,
			'quote_case.status_changed',
			'operator',
			$actor_user_id,
			'operator',
			'מצב בקשת הסיוע עודכן: ' . Tra_Vel_Quote_Case_Policy::status_label( $to_status ) . '.',
			array(),
			$idempotency_key,
			'case.transition'
		);
	}

	public function cancel( $case_uuid, $expected_version, $principal, $idempotency_key ) {
		$case = $this->get_case_by_uuid( $case_uuid );
		if ( ! $case ) {
			return new WP_Error( 'tra_vel_quote_case_missing', 'Quote case not found.', array( 'status' => 404 ) );
		}
		$owner_guard = $this->owner_guard( $case, $principal );
		if ( ! $owner_guard ) {
			return new WP_Error( 'tra_vel_quote_case_forbidden', 'This private quote case does not belong to the current traveler.', array( 'status' => 403 ) );
		}
		$user_id = absint( $principal['user_id'] ?? 0 );
		if ( (int) $case['case_version'] === (int) $expected_version && ! Tra_Vel_Quote_Case_Policy::can_cancel( $case['status'] ) ) {
			return new WP_Error( 'tra_vel_quote_case_cancel_invalid', 'This quote case can no longer be cancelled.', array( 'status' => 409 ) );
		}
		return $this->mutate_case( $case, 'cancelled', $expected_version, 'quote_case.cancelled', 'traveler', $user_id, 'web', 'בקשת הסיוע בוטלה על ידי המטייל.', array(), $idempotency_key, 'case.cancel', false, $owner_guard );
	}

	public function record_handoff( $case_uuid, $expected_version, $principal, $channel, $provider, $expires_at, $idempotency_key ) {
		$case = $this->get_case_by_uuid( $case_uuid );
		if ( ! $case ) {
			return new WP_Error( 'tra_vel_quote_case_missing', 'Quote case not found.', array( 'status' => 404 ) );
		}
		$owner_guard = $this->owner_guard( $case, $principal );
		if ( ! $owner_guard ) {
			return new WP_Error( 'tra_vel_quote_case_forbidden', 'This private quote case does not belong to the current traveler.', array( 'status' => 403 ) );
		}
		$user_id = absint( $principal['user_id'] ?? 0 );
		if ( ! Tra_Vel_Quote_Case_Policy::can_cancel( $case['status'] ) ) {
			return new WP_Error( 'tra_vel_quote_case_handoff_inactive', 'This quote case no longer accepts assisted-contact handoffs.', array( 'status' => 409 ) );
		}
		$current_version = (int) $case['case_version'];
		$expected_version = (int) $expected_version;
		$handoff_payload = array( 'channel' => sanitize_key( $channel ), 'provider' => sanitize_key( $provider ), 'expires_at' => (string) $expires_at, 'dispatched' => false );
		$principal_hash = hash( 'sha256', 'traveler:' . $user_id . ':' . $case['case_uuid'] );
		$operation_digest = $this->mutation_operation_digest( $case, $case['status'], $expected_version, 'handoff.prepared', $handoff_payload );
		$idempotent = $this->idempotent_result( 'handoff.prepare', $principal_hash, $idempotency_key, $operation_digest );
		if ( $idempotent ) {
			if ( is_wp_error( $idempotent ) ) {
				return $idempotent;
			}
			if ( ! $this->owner_guard_matches( $idempotent, $owner_guard ) ) {
				return new WP_Error( 'tra_vel_quote_case_forbidden', 'Quote-case ownership changed before this handoff replay.', array( 'status' => 403 ) );
			}
			$event = $this->find_reusable_handoff( $case['id'], $current_version, $channel, $provider, $owner_guard );
			return array( 'case' => $idempotent, 'event' => $event, 'replayed' => true, 'reused' => (bool) $event );
		}
		if ( $expected_version === $current_version || $expected_version + 1 === $current_version ) {
			$reusable = $this->find_reusable_handoff( $case['id'], $current_version, $channel, $provider, $owner_guard );
			if ( $reusable ) {
				return array( 'case' => $case, 'event' => $reusable, 'replayed' => true, 'reused' => true );
			}
		}
		if ( $current_version === $expected_version ) {
			if ( $this->count_recent_handoffs( $case['id'] ) >= self::HANDOFF_WINDOW_LIMIT ) {
				return new WP_Error( 'tra_vel_quote_case_handoff_rate_limited', 'Too many assisted-contact links were prepared for this case. Please wait before trying again.', array( 'status' => 429, 'retry_after' => self::HANDOFF_WINDOW_SECONDS ) );
			}
		}
		return $this->mutate_case(
			$case,
			$case['status'],
			$expected_version,
			'handoff.prepared',
			'traveler',
			$user_id,
			'web',
			'קישור לשיחה עם Tra-Vel הוכן. בשלב הזה לא נשלחה הודעה ולא בוצעה הזמנה.',
			$handoff_payload,
			$idempotency_key,
			'handoff.prepare',
			false,
			$owner_guard
		);
	}

	public function claim( $case_uuid, $expected_version, $user_id, $guest_token_hash, $idempotency_key ) {
		global $wpdb;
		$case = $this->get_case_by_uuid( $case_uuid );
		if ( ! $case || $user_id < 1 ) {
			return new WP_Error( 'tra_vel_quote_case_claim_forbidden', 'This guest quote case cannot be claimed by the current account.', array( 'status' => 403 ) );
		}
		$principal = hash( 'sha256', 'user:' . absint( $user_id ) );
		$digest    = hash( 'sha256', $case_uuid . '|claim|' . $expected_version . '|' . $user_id );
		$replay    = $this->idempotent_result( 'case.claim', $principal, $idempotency_key, $digest );
		if ( $replay ) {
			return is_wp_error( $replay ) ? $replay : array( 'case' => $replay, 'event' => null, 'replayed' => true );
		}
		if ( (int) $case['owner_user_id'] === (int) $user_id ) {
			if ( ! $this->record_idempotency( 'case.claim', $principal, $idempotency_key, $digest, $case_uuid, $case['case_version'], 200 ) ) {
				$recorded = $this->idempotent_result( 'case.claim', $principal, $idempotency_key, $digest );
				if ( is_wp_error( $recorded ) ) {
					return $recorded;
				}
				if ( ! $recorded ) {
					return new WP_Error( 'tra_vel_quote_case_claim_failed', 'The existing account link could not be recorded safely.', array( 'status' => 500 ) );
				}
			}
			return array( 'case' => $case, 'event' => null, 'replayed' => true );
		}
		if ( ! $this->can_access( $case, 0, $guest_token_hash ) ) {
			return new WP_Error( 'tra_vel_quote_case_claim_forbidden', 'This guest quote case cannot be claimed by the current account.', array( 'status' => 403 ) );
		}
		if ( (int) $case['case_version'] !== (int) $expected_version ) {
			return new WP_Error( 'tra_vel_quote_case_version_conflict', 'The quote case changed before it could be claimed.', array( 'status' => 409, 'current_version' => $case['case_version'] ) );
		}
		$now = current_time( 'mysql', true );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'tra_vel_quote_case_claim_failed', 'The account-link transaction could not be started.', array( 'status' => 500 ) );
		}
		if ( ! $this->bind_source_run_owner( $case['source_run_id'], $case['source_run_uuid'], $user_id ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_quote_case_source_owner_conflict', 'The private source plan belongs to a different account.', array( 'status' => 409 ) );
		}
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::cases_table() . ' SET owner_user_id = %d, owner_token_hash = %s, case_version = case_version + 1, last_event_sequence = last_event_sequence + 1, updated_at = %s, last_activity_at = %s WHERE id = %d AND case_version = %d AND owner_user_id = 0',
				absint( $user_id ), '', $now, $now, $case['id'], absint( $expected_version )
			)
		);
		if ( 1 !== $updated ) {
			$wpdb->query( 'ROLLBACK' );
			$replayed = $this->mutation_replay_after_rollback( 'case.claim', $principal, $idempotency_key, $digest );
			if ( $replayed ) {
				return $replayed;
			}
			$current = $this->get_case_by_uuid( $case_uuid );
			return new WP_Error( 'tra_vel_quote_case_version_conflict', 'The quote case changed before it could be claimed.', array( 'status' => 409, 'current_version' => $current ? (int) $current['case_version'] : null ) );
		}
		$new_version = (int) $case['case_version'] + 1;
		$sequence    = (int) $case['last_event_sequence'] + 1;
		$event = $this->insert_event( $case['id'], $sequence, $new_version, 'quote_case.claimed', $case['status'], $case['status'], 'traveler', $user_id, 'account', 'public', 'בקשת האורח קושרה לחשבון המטייל המחובר.', array() );
		if ( ! $event || ! $this->insert_idempotency( 'case.claim', $principal, $idempotency_key, $digest, $case_uuid, $new_version, 200 ) ) {
			$wpdb->query( 'ROLLBACK' );
			$replayed = $this->mutation_replay_after_rollback( 'case.claim', $principal, $idempotency_key, $digest );
			if ( $replayed ) {
				return $replayed;
			}
			return new WP_Error( 'tra_vel_quote_case_claim_failed', 'The quote case could not be linked safely.', array( 'status' => 500 ) );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$replayed = $this->mutation_replay_after_rollback( 'case.claim', $principal, $idempotency_key, $digest );
			if ( $replayed ) {
				return $replayed;
			}
			return new WP_Error( 'tra_vel_quote_case_claim_failed', 'The account-link transaction could not be committed.', array( 'status' => 500 ) );
		}
		return array( 'case' => $this->get_case_by_uuid( $case_uuid ), 'event' => $event, 'replayed' => false );
	}

	/**
	 * Recover a guest case from its still-private source run. The controller may
	 * call this only after AgentRun ownership has been verified. This closes the
	 * response-loss window between committing a case and receiving its cookie,
	 * and lets a signed-in owner carry the same case into an account.
	 *
	 * @param array  $case      Hydrated guest case.
	 * @param string $run_uuid  Verified source AgentRun UUID.
	 * @param array  $principal Current user/token principal.
	 * @return array|WP_Error
	 */
	public function recover_owner_from_run( $case, $run_uuid, $principal ) {
		global $wpdb;
		if ( ! is_array( $case ) || ! hash_equals( (string) $case['source_run_uuid'], (string) $run_uuid ) || (int) $case['owner_user_id'] > 0 ) {
			return new WP_Error( 'tra_vel_quote_case_recovery_forbidden', 'This quote case cannot be recovered from the current private plan.', array( 'status' => 403 ) );
		}
		$user_id    = absint( $principal['user_id'] ?? 0 );
		$token_hash = $user_id > 0 ? '' : (string) ( $principal['token_hash'] ?? '' );
		if ( $user_id < 1 && 64 !== strlen( $token_hash ) ) {
			return new WP_Error( 'tra_vel_quote_case_recovery_forbidden', 'A protected quote owner is required for recovery.', array( 'status' => 403 ) );
		}

		$now         = current_time( 'mysql', true );
		$new_version = (int) $case['case_version'] + 1;
		$sequence    = (int) $case['last_event_sequence'] + 1;
		$source      = $user_id > 0 ? 'account' : 'web';
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'tra_vel_quote_case_recovery_failed', 'The quote-case recovery transaction could not be started.', array( 'status' => 500 ) );
		}
		if ( $user_id > 0 && ! $this->bind_source_run_owner( $case['source_run_id'], $run_uuid, $user_id ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_quote_case_source_owner_conflict', 'The private source plan belongs to a different account.', array( 'status' => 409 ) );
		}
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::cases_table() . ' SET owner_user_id = %d, owner_token_hash = %s, case_version = %d, last_event_sequence = %d, updated_at = %s, last_activity_at = %s WHERE id = %d AND case_version = %d AND owner_user_id = 0 AND source_run_uuid = %s',
				$user_id, $token_hash, $new_version, $sequence, $now, $now, $case['id'], $case['case_version'], (string) $run_uuid
			)
		);
		if ( 1 !== $updated ) {
			$wpdb->query( 'ROLLBACK' );
			$current = $this->get_case_by_uuid( $case['case_uuid'] );
			if ( $current && ( ( $user_id > 0 && (int) $current['owner_user_id'] === $user_id ) || ( $user_id < 1 && $this->can_access( $current, 0, $token_hash ) ) ) ) {
				return array( 'case' => $current, 'event' => null, 'replayed' => true );
			}
			return new WP_Error( 'tra_vel_quote_case_version_conflict', 'The quote case owner changed before recovery completed.', array( 'status' => 409, 'current_version' => $current ? (int) $current['case_version'] : null ) );
		}
		$event = $this->insert_event( $case['id'], $sequence, $new_version, 'quote_case.owner_recovered', $case['status'], $case['status'], 'traveler', $user_id, $source, 'public', 'הגישה הפרטית לבקשה שוחזרה באמצעות הבעלות המאומתת על תוכנית המקור.', array( 'owner_scope' => $user_id > 0 ? 'account' : 'private_browser_owner' ) );
		if ( ! $event ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_quote_case_recovery_failed', 'The quote case owner could not be recovered safely.', array( 'status' => 500 ) );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$current = $this->get_case_by_uuid( $case['case_uuid'] );
			if ( $current && ( ( $user_id > 0 && (int) $current['owner_user_id'] === $user_id ) || ( $user_id < 1 && $this->can_access( $current, 0, $token_hash ) ) ) ) {
				return array( 'case' => $current, 'event' => null, 'replayed' => true );
			}
			return new WP_Error( 'tra_vel_quote_case_recovery_failed', 'The quote-case recovery transaction could not be committed.', array( 'status' => 500 ) );
		}
		return array( 'case' => $this->get_case_by_uuid( $case['case_uuid'] ), 'event' => $event, 'replayed' => false );
	}

	/**
	 * Freeze a later AI-request revision and reset active human work to queue.
	 * Terminal cases are never silently reopened.
	 */
	public function sync_from_run( $run, $attempt = 0, $retry_count = 0 ) {
		global $wpdb;
		if ( ! self::is_ready() || ! Tra_Vel_Agent_Store::is_ready() ) {
			$this->schedule_sync_retry( $run, $retry_count );
			return false;
		}
		$case_read_error = '';
		$case = $this->get_case_by_source_run( $run['run_uuid'] ?? '', $case_read_error );
		if ( '' !== $case_read_error ) {
			$this->schedule_sync_retry( $run, $retry_count );
			return false;
		}
		if ( ! $case || ! Tra_Vel_Quote_Case_Policy::can_cancel( $case['status'] ) ) {
			return false;
		}
		if ( (int) ( $run['owner_user_id'] ?? 0 ) !== (int) $case['owner_user_id'] ) {
			// A run and quote may synchronize only while they have the same durable
			// owner. A guest snapshot can legitimately become stale while an account
			// claim commits between persistence and this listener; retry reloads the
			// authoritative account-owned run instead of trusting the stale object.
			if ( 0 === (int) ( $run['owner_user_id'] ?? 0 ) && (int) $case['owner_user_id'] > 0 ) {
				$this->schedule_sync_retry( $run, $retry_count );
			}
			return false;
		}
		$readiness  = (string) ( $run['trip_request']['readiness']['status'] ?? '' );
		$run_status = (string) ( $run['status'] ?? '' );
		if ( 'request_ready' === $run_status && 'ready_for_search' === $readiness ) {
			$next_status = 'queued';
		} elseif ( 'needs_clarification' === $run_status && 'needs_clarification' === $readiness ) {
			$next_status = 'needs_information';
		} else {
			return false;
		}
		$snapshot = Tra_Vel_Quote_Case_Policy::snapshot( $run['trip_request'] );
		$digest   = Tra_Vel_Quote_Case_Policy::digest( $snapshot );
		if ( ! $this->should_sync_snapshot( $case, $snapshot, $digest ) ) {
			return $case;
		}
		$now          = current_time( 'mysql', true );
		$new_revision = (int) $case['current_revision'] + 1;
		$new_version  = (int) $case['case_version'] + 1;
		$sequence     = (int) $case['last_event_sequence'] + 1;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			$this->schedule_sync_retry( $run, $retry_count );
			return false;
		}
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::cases_table() . ' SET source_request_uuid = %s, source_request_revision = %d, status = %s, case_version = %d, current_revision = %d, latest_request_digest = %s, last_event_sequence = %d, assigned_user_id = 0, updated_at = %s, last_activity_at = %s WHERE id = %d AND case_version = %d AND source_request_revision < %d',
				(string) $snapshot['request_id'], absint( $snapshot['revision'] ), $next_status, $new_version, $new_revision, $digest, $sequence, $now, $now, $case['id'], $case['case_version'], absint( $snapshot['revision'] )
			)
		);
		if ( 1 !== $updated ) {
			$wpdb->query( 'ROLLBACK' );
			if ( 0 === $updated && (int) $attempt < 2 ) {
				// A simultaneous operator update must not permanently strand a
				// newer traveler request behind the previous frozen revision.
				return $this->sync_from_run( $run, (int) $attempt + 1, $retry_count );
			}
			$this->schedule_sync_retry( $run, $retry_count );
			return false;
		}
		if ( ! $this->insert_revision( $case['id'], $new_revision, $snapshot, $digest, 'traveler', $case['owner_user_id'] ) ) {
			$wpdb->query( 'ROLLBACK' );
			$this->schedule_sync_retry( $run, $retry_count );
			return false;
		}
		$message = 'needs_information' === $next_status
			? 'בקשת הנסיעה השתנתה וכעת נדרש מידע נוסף. הטיפול בפרטים הקודמים הושהה.'
			: 'בקשת הנסיעה השתנתה. הבדיקה האנושית חזרה לתור כדי שלא ייעשה שימוש בפרטים ישנים.';
		$event = $this->insert_event( $case['id'], $sequence, $new_version, 'quote_case.request_revised', $case['status'], $next_status, 'traveler', $case['owner_user_id'], 'agent', 'public', $message, array( 'request_revision' => absint( $snapshot['revision'] ), 'previous_status' => $case['status'] ) );
		if ( ! $event ) {
			$wpdb->query( 'ROLLBACK' );
			$this->schedule_sync_retry( $run, $retry_count );
			return false;
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$this->schedule_sync_retry( $run, $retry_count );
			return false;
		}
		return $this->get_case_by_uuid( $case['case_uuid'] );
	}

	/**
	 * Decide whether a source snapshot is strictly newer than the immutable
	 * request already frozen on the case. Workflow status is intentionally not
	 * considered: duplicate delivery must never reset human progress.
	 *
	 * @param array  $case     Current hydrated quote case.
	 * @param array  $snapshot Sanitized incoming TripRequest snapshot.
	 * @param string $digest   Incoming snapshot digest.
	 * @return bool
	 */
	private function should_sync_snapshot( $case, $snapshot, $digest ) {
		if ( ! empty( $case['latest_request_digest'] ) && hash_equals( (string) $case['latest_request_digest'], (string) $digest ) ) {
			return false;
		}
		return absint( $snapshot['revision'] ?? 0 ) > absint( $case['source_request_revision'] ?? 0 );
	}

	private function schedule_sync_retry( $run, $retry_count = 0 ) {
		self::schedule_sync_retry_by_uuid( (string) ( $run['run_uuid'] ?? '' ), absint( $run['trip_request']['revision'] ?? 0 ), $retry_count );
	}

	/**
	 * Requeue a source sync without reading AgentRun storage. This is used when
	 * either schema is temporarily unready, so the durable recovery callback is
	 * not consumed during a migration or transient storage outage.
	 *
	 * @param string $run_uuid        AgentRun UUID.
	 * @param int    $minimum_revision Minimum source revision to reload.
	 * @param int    $retry_count     Current bounded retry attempt.
	 * @return bool Whether a retry already existed or was scheduled.
	 */
	public static function schedule_sync_retry_by_uuid( $run_uuid, $minimum_revision, $retry_count = 0 ) {
		$retry_count = max( 0, absint( $retry_count ) );
		$run_uuid = (string) $run_uuid;
		if ( ! function_exists( 'wp_schedule_single_event' ) || '' === $run_uuid || $retry_count >= self::SYNC_RETRY_LIMIT ) {
			return false;
		}
		$args  = array( $run_uuid, absint( $minimum_revision ), $retry_count + 1 );
		$delay = min( 900, 30 * ( 2 ** $retry_count ) );
		if ( wp_next_scheduled( 'tra_vel_quote_case_sync_retry', $args ) ) {
			return true;
		}
		return (bool) wp_schedule_single_event( time() + $delay, 'tra_vel_quote_case_sync_retry', $args );
	}

	/**
	 * Execute the registered durable sync callback. Readiness is checked before
	 * AgentRun hydration; an unavailable schema consumes no retry opportunity.
	 *
	 * @param string $run_uuid         AgentRun UUID.
	 * @param int    $minimum_revision Minimum source revision to synchronize.
	 * @param int    $retry_count      Current bounded retry attempt.
	 * @return array|false Hydrated case when synchronized, otherwise false.
	 */
	public static function run_scheduled_sync( $run_uuid, $minimum_revision, $retry_count ) {
		$run_uuid        = (string) $run_uuid;
		$minimum_revision = absint( $minimum_revision );
		$retry_count     = absint( $retry_count );
		if ( ! Tra_Vel_Agent_Store::is_ready() || ! self::is_ready() ) {
			self::schedule_sync_retry_by_uuid( $run_uuid, $minimum_revision, $retry_count );
			return false;
		}
		$run_read_error = '';
		$run = ( new Tra_Vel_Agent_Store() )->get_run_by_uuid( $run_uuid, $run_read_error );
		if ( '' !== $run_read_error ) {
			self::schedule_sync_retry_by_uuid( $run_uuid, $minimum_revision, $retry_count );
			return false;
		}
		if ( ! is_array( $run ) || (int) ( $run['trip_request']['revision'] ?? 0 ) < $minimum_revision ) {
			return false;
		}
		return ( new self() )->sync_from_run( $run, 0, $retry_count );
	}

	public static function cleanup() {
		if ( ! self::is_ready() ) {
			$unready = array( 'expired' => 0, 'deleted_cases' => 0, 'deleted_idempotency' => 0, 'deleted_orphan_revisions' => 0, 'deleted_orphan_events' => 0, 'batches' => 0, 'errors' => array( 'schema_not_ready' ) );
			self::record_cleanup_status( $unready );
			return $unready;
		}
		$store             = new self();
		$now               = current_time( 'mysql', true );
		$deadline          = microtime( true ) + self::CLEANUP_MAX_SECONDS;
		$expiry_cursor     = 0;
		$retention_cursor  = 0;
		$idempotency_cursor = 0;
		$orphan_revision_cursor = 0;
		$orphan_event_cursor    = 0;
		$expiry_done       = false;
		$retention_done    = false;
		$idempotency_done  = false;
		$orphan_revision_done = false;
		$orphan_event_done    = false;
		$totals            = array( 'expired' => 0, 'deleted_cases' => 0, 'deleted_idempotency' => 0, 'deleted_orphan_revisions' => 0, 'deleted_orphan_events' => 0, 'batches' => 0, 'errors' => array() );

		for ( $batch = 0; $batch < self::CLEANUP_MAX_BATCHES && microtime( true ) < $deadline; $batch++ ) {
			$totals['batches']++;
			if ( ! $expiry_done ) {
				$result            = $store->expire_service_batch( $now, $expiry_cursor, self::CLEANUP_BATCH_SIZE );
				if ( is_wp_error( $result ) ) {
					$totals['errors'][] = $result->get_error_code();
					$expiry_done = true;
				} else {
					$expiry_cursor     = (int) $result['last_id'];
					$totals['expired'] += (int) $result['expired'];
					$expiry_done       = (int) $result['scanned'] < self::CLEANUP_BATCH_SIZE;
				}
			}

			if ( ! $retention_done && microtime( true ) < $deadline ) {
				$result = $store->delete_retention_batch( $now, $retention_cursor, self::CLEANUP_BATCH_SIZE );
				if ( is_wp_error( $result ) ) {
					$totals['errors'][] = $result->get_error_code();
					$retention_done = true;
				} else {
					$retention_cursor       = (int) $result['last_id'];
					$totals['deleted_cases'] += (int) $result['deleted'];
					$retention_done          = (int) $result['scanned'] < self::CLEANUP_BATCH_SIZE;
				}
			}

			if ( ! $idempotency_done && microtime( true ) < $deadline ) {
				$result = $store->sweep_idempotency_batch( $now, $idempotency_cursor, self::CLEANUP_IDEMPOTENCY_BATCH_SIZE );
				if ( is_wp_error( $result ) ) {
					$totals['errors'][] = $result->get_error_code();
					$idempotency_done = true;
				} else {
					$idempotency_cursor                = (int) $result['last_id'];
					$totals['deleted_idempotency']    += (int) $result['deleted'];
					$idempotency_done                  = (int) $result['scanned'] < self::CLEANUP_IDEMPOTENCY_BATCH_SIZE;
				}
			}

			if ( ! $orphan_revision_done && microtime( true ) < $deadline ) {
				$result = $store->sweep_orphan_case_rows_batch( self::revisions_table(), 'revisions', $orphan_revision_cursor, self::CLEANUP_IDEMPOTENCY_BATCH_SIZE );
				if ( is_wp_error( $result ) ) {
					$totals['errors'][] = $result->get_error_code();
					$orphan_revision_done = true;
				} else {
					$orphan_revision_cursor              = (int) $result['last_id'];
					$totals['deleted_orphan_revisions'] += (int) $result['deleted'];
					$orphan_revision_done                = (int) $result['scanned'] < self::CLEANUP_IDEMPOTENCY_BATCH_SIZE;
				}
			}

			if ( ! $orphan_event_done && microtime( true ) < $deadline ) {
				$result = $store->sweep_orphan_case_rows_batch( self::events_table(), 'events', $orphan_event_cursor, self::CLEANUP_IDEMPOTENCY_BATCH_SIZE );
				if ( is_wp_error( $result ) ) {
					$totals['errors'][] = $result->get_error_code();
					$orphan_event_done = true;
				} else {
					$orphan_event_cursor              = (int) $result['last_id'];
					$totals['deleted_orphan_events'] += (int) $result['deleted'];
					$orphan_event_done                = (int) $result['scanned'] < self::CLEANUP_IDEMPOTENCY_BATCH_SIZE;
				}
			}

			if ( $expiry_done && $retention_done && $idempotency_done && $orphan_revision_done && $orphan_event_done ) {
				break;
			}
		}

		self::record_cleanup_status( $totals );
		return $totals;
	}

	private function expire_service_batch( $now, $after_id, $limit ) {
		global $wpdb;
		$was_suppressed = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id,case_uuid FROM ' . self::cases_table() . " WHERE id > %d AND service_expires_at < %s AND status NOT IN ('closed_no_quote','cancelled','expired') ORDER BY id ASC LIMIT %d",
				absint( $after_id ), (string) $now, absint( $limit )
			),
			ARRAY_A
		);
		$read_error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( '' !== $read_error ) {
			return new WP_Error( 'tra_vel_quote_cleanup_expiry_select_failed', 'Expiring quote cases could not be selected.' );
		}
		$rows    = is_array( $rows ) ? $rows : array();
		$expired = 0;
		$last_id = absint( $after_id );
		foreach ( $rows as $row ) {
			$last_id = max( $last_id, absint( $row['id'] ) );
			$case    = $this->get_case_by_uuid( $row['case_uuid'] );
			$result  = $case ? $this->expire_case( $case ) : null;
			if ( is_array( $result ) ) {
				$expired++;
			}
		}
		return array( 'scanned' => count( $rows ), 'expired' => $expired, 'last_id' => $last_id );
	}

	private function delete_retention_batch( $now, $after_id, $limit ) {
		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'tra_vel_quote_cleanup_transaction_failed', 'Retention cleanup could not start a transaction.' );
		}
		$was_suppressed = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id,case_uuid FROM ' . self::cases_table() . ' WHERE id > %d AND retention_until < %s AND legal_hold = 0 ORDER BY id ASC LIMIT %d FOR UPDATE',
				absint( $after_id ), (string) $now, absint( $limit )
			),
			ARRAY_A
		);
		$read_error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( '' !== $read_error || ! is_array( $rows ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_quote_cleanup_select_failed', 'Retention cleanup could not lock eligible cases.' );
		}
		if ( ! $rows ) {
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'tra_vel_quote_cleanup_commit_failed', 'Retention cleanup could not close its empty transaction.' );
			}
			return array( 'scanned' => 0, 'deleted' => 0, 'last_id' => absint( $after_id ) );
		}

		$case_ids   = array_values( array_map( 'absint', wp_list_pluck( $rows, 'id' ) ) );
		$case_uuids = array_values( array_map( 'strval', wp_list_pluck( $rows, 'case_uuid' ) ) );
		$last_id    = max( $case_ids );
		$uuid_placeholders = implode( ',', array_fill( 0, count( $case_uuids ), '%s' ) );
		$id_placeholders   = implode( ',', array_fill( 0, count( $case_ids ), '%d' ) );

		$deleted_idempotency = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::idempotency_table() . " WHERE case_uuid IN ({$uuid_placeholders})", $case_uuids ) );
		$deleted_events      = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::events_table() . " WHERE case_id IN ({$id_placeholders})", $case_ids ) );
		$deleted_revisions   = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::revisions_table() . " WHERE case_id IN ({$id_placeholders})", $case_ids ) );
		$parent_params       = array_merge( $case_ids, array( (string) $now ) );
		$deleted_cases       = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::cases_table() . " WHERE id IN ({$id_placeholders}) AND retention_until < %s AND legal_hold = 0", $parent_params ) );
		if ( false === $deleted_idempotency || false === $deleted_events || false === $deleted_revisions || count( $case_ids ) !== (int) $deleted_cases ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_quote_cleanup_delete_failed', 'Retention cleanup rolled back an incomplete deletion batch.' );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_quote_cleanup_commit_failed', 'Retention cleanup could not commit a complete deletion batch.' );
		}
		return array( 'scanned' => count( $rows ), 'deleted' => (int) $deleted_cases, 'last_id' => $last_id );
	}

	private function sweep_idempotency_batch( $now, $after_id, $limit ) {
		global $wpdb;
		$was_suppressed = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT i.id FROM ' . self::idempotency_table() . ' i LEFT JOIN ' . self::cases_table() . ' c ON c.case_uuid = i.case_uuid WHERE i.id > %d AND (i.expires_at < %s OR c.id IS NULL) ORDER BY i.id ASC LIMIT %d',
				absint( $after_id ), (string) $now, absint( $limit )
			)
		);
		$read_error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( '' !== $read_error || ! is_array( $rows ) ) {
			return new WP_Error( 'tra_vel_quote_cleanup_idempotency_select_failed', 'Stale idempotency rows could not be selected.' );
		}
		$ids = array_values( array_map( 'absint', $rows ) );
		if ( ! $ids ) {
			return array( 'scanned' => 0, 'deleted' => 0, 'last_id' => absint( $after_id ) );
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$deleted      = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::idempotency_table() . " WHERE id IN ({$placeholders})", $ids ) );
		if ( false === $deleted ) {
			return new WP_Error( 'tra_vel_quote_cleanup_idempotency_delete_failed', 'Stale idempotency rows could not be deleted.' );
		}
		return array( 'scanned' => count( $ids ), 'deleted' => (int) $deleted, 'last_id' => max( $ids ) );
	}

	/**
	 * Heal bounded orphan child rows left by historical non-transactional
	 * retention cleanup. Both reads and deletes fail closed so private snapshots
	 * or events are never silently treated as removed.
	 *
	 * @param string $child_table Trusted WordPress-prefixed child table.
	 * @param string $kind        revisions|events, used only in error codes.
	 * @param int    $after_id    Exclusive child-row cursor.
	 * @param int    $limit       Maximum rows to inspect.
	 * @return array|WP_Error
	 */
	private function sweep_orphan_case_rows_batch( $child_table, $kind, $after_id, $limit ) {
		global $wpdb;
		if ( ! in_array( $kind, array( 'revisions', 'events' ), true ) ) {
			return new WP_Error( 'tra_vel_quote_cleanup_orphan_kind_invalid', 'The quote orphan sweep was not configured safely.' );
		}
		$child_identifier = str_replace( '`', '``', (string) $child_table );
		$cases_identifier = str_replace( '`', '``', self::cases_table() );
		$was_suppressed   = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT c.id FROM `{$child_identifier}` c LEFT JOIN `{$cases_identifier}` p ON p.id = c.case_id WHERE c.id > %d AND p.id IS NULL ORDER BY c.id ASC LIMIT %d",
				absint( $after_id ),
				absint( $limit )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers are internal WordPress-prefixed table names.
		$read_error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( '' !== $read_error || ! is_array( $rows ) ) {
			return new WP_Error( 'tra_vel_quote_cleanup_orphan_' . $kind . '_select_failed', 'Orphan quote child rows could not be selected.' );
		}
		$ids = array_values( array_map( 'absint', $rows ) );
		if ( ! $ids ) {
			return array( 'scanned' => 0, 'deleted' => 0, 'last_id' => absint( $after_id ) );
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$deleted      = $wpdb->query( $wpdb->prepare( "DELETE FROM `{$child_identifier}` WHERE id IN ({$placeholders})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted identifier and prepared IDs.
		if ( false === $deleted || count( $ids ) !== (int) $deleted ) {
			return new WP_Error( 'tra_vel_quote_cleanup_orphan_' . $kind . '_delete_failed', 'Orphan quote child rows could not be deleted completely.' );
		}
		return array( 'scanned' => count( $ids ), 'deleted' => (int) $deleted, 'last_id' => max( $ids ) );
	}

	private static function record_cleanup_status( $totals ) {
		update_option(
			self::CLEANUP_STATUS_OPTION,
			array(
				'last_run_at'   => gmdate( 'c' ),
				'ok'            => empty( $totals['errors'] ),
				'errors'        => array_values( array_unique( (array) ( $totals['errors'] ?? array() ) ) ),
				'deleted_cases' => absint( $totals['deleted_cases'] ?? 0 ),
				'deleted_orphan_revisions' => absint( $totals['deleted_orphan_revisions'] ?? 0 ),
				'deleted_orphan_events'    => absint( $totals['deleted_orphan_events'] ?? 0 ),
			),
			false
		);
	}

	private function expire_case( $case ) {
		return $this->mutate_case( $case, 'expired', $case['case_version'], 'quote_case.expired', 'system', 0, 'cron', 'תוקף בקשת הסיוע הסתיים לאחר תקופת הפעילות שנקבעה.', array(), wp_generate_uuid4(), 'case.expire', true );
	}

	private function mutate_case( $case, $to_status, $expected_version, $event_type, $actor_type, $actor_user_id, $source, $message, $payload, $idempotency_key, $scope, $system_override = false, $owner_guard = null ) {
		global $wpdb;
		$principal_hash   = hash( 'sha256', $actor_type . ':' . absint( $actor_user_id ) . ':' . ( $system_override ? 'system' : $case['case_uuid'] ) );
		$operation_digest = $this->mutation_operation_digest( $case, $to_status, $expected_version, $event_type, $payload );
		$replay           = $this->idempotent_result( $scope, $principal_hash, $idempotency_key, $operation_digest );
		if ( $replay ) {
			if ( is_wp_error( $replay ) ) {
				return $replay;
			}
			if ( $owner_guard && ! $this->owner_guard_matches( $replay, $owner_guard ) ) {
				return new WP_Error( 'tra_vel_quote_case_forbidden', 'Quote-case ownership changed before this replay.', array( 'status' => 403 ) );
			}
			return array( 'case' => $replay, 'event' => null, 'replayed' => true );
		}
		if ( (int) $case['case_version'] !== (int) $expected_version ) {
			return new WP_Error( 'tra_vel_quote_case_version_conflict', 'The quote case changed before this action was saved.', array( 'status' => 409, 'current_version' => $case['case_version'] ) );
		}
		$now         = current_time( 'mysql', true );
		$new_version = (int) $case['case_version'] + 1;
		$sequence    = (int) $case['last_event_sequence'] + 1;
		$is_terminal = in_array( $to_status, array( 'closed_no_quote', 'cancelled', 'expired' ), true );
		$assigned    = 'in_review' === $to_status && $actor_user_id > 0 ? absint( $actor_user_id ) : (int) $case['assigned_user_id'];
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'tra_vel_quote_case_update_failed', 'The quote-case transaction could not be started.', array( 'status' => 500 ) );
		}
		$closed_sql = $is_terminal ? 'closed_at = %s' : 'closed_at = NULL';
		$params     = array( $to_status, $new_version, $sequence, $assigned, $now, $now );
		if ( $is_terminal ) {
			$params[] = $now;
		}
		$params[] = $case['id'];
		$params[] = $case['case_version'];
		$params[] = $case['status'];
		$owner_sql = '';
		if ( is_array( $owner_guard ) && absint( $owner_guard['owner_user_id'] ?? 0 ) > 0 ) {
			$owner_sql = ' AND owner_user_id = %d';
			$params[]  = absint( $owner_guard['owner_user_id'] );
		} elseif ( is_array( $owner_guard ) && 64 === strlen( (string) ( $owner_guard['owner_token_hash'] ?? '' ) ) ) {
			$owner_sql = ' AND owner_user_id = 0 AND owner_token_hash = %s';
			$params[]  = (string) $owner_guard['owner_token_hash'];
		} elseif ( null !== $owner_guard ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_quote_case_forbidden', 'A valid quote-case owner guard is required.', array( 'status' => 403 ) );
		}
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::cases_table() . ' SET status = %s, case_version = %d, last_event_sequence = %d, assigned_user_id = %d, updated_at = %s, last_activity_at = %s, ' . $closed_sql . ' WHERE id = %d AND case_version = %d AND status = %s' . $owner_sql,
				$params
			)
		);
		if ( 1 !== $updated ) {
			$wpdb->query( 'ROLLBACK' );
			$replayed = $this->mutation_replay_after_rollback( $scope, $principal_hash, $idempotency_key, $operation_digest, $owner_guard );
			if ( $replayed ) {
				return $replayed;
			}
			$current = $this->get_case_by_uuid( $case['case_uuid'] );
			return new WP_Error( 'tra_vel_quote_case_version_conflict', 'Another update reached the quote case first.', array( 'status' => 409, 'current_version' => $current ? (int) $current['case_version'] : null ) );
		}
		$event = $this->insert_event( $case['id'], $sequence, $new_version, $event_type, $case['status'], $to_status, $actor_type, $actor_user_id, $source, 'public', $message, $payload );
		if ( ! $event || ! $this->insert_idempotency( $scope, $principal_hash, $idempotency_key, $operation_digest, $case['case_uuid'], $new_version, 200 ) ) {
			$wpdb->query( 'ROLLBACK' );
			$replayed = $this->mutation_replay_after_rollback( $scope, $principal_hash, $idempotency_key, $operation_digest, $owner_guard );
			if ( $replayed ) {
				return $replayed;
			}
			return new WP_Error( 'tra_vel_quote_case_update_failed', 'The quote-case update could not be committed safely.', array( 'status' => 500 ) );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$replayed = $this->mutation_replay_after_rollback( $scope, $principal_hash, $idempotency_key, $operation_digest, $owner_guard );
			if ( $replayed ) {
				return $replayed;
			}
			return new WP_Error( 'tra_vel_quote_case_update_failed', 'The quote-case transaction could not be committed.', array( 'status' => 500 ) );
		}
		return array( 'case' => $this->get_case_by_uuid( $case['case_uuid'] ), 'event' => $event, 'replayed' => false );
	}

	private function mutation_operation_digest( $case, $to_status, $expected_version, $event_type, $payload ) {
		$digest_payload = is_array( $payload ) ? $payload : array();
		if ( 'handoff.prepared' === $event_type ) {
			// The expiry is generated by the server for each attempt and is not
			// part of the caller's logical idempotent request.
			unset( $digest_payload['expires_at'] );
		}
		$payload_digest = hash( 'sha256', (string) wp_json_encode( $digest_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		return hash( 'sha256', implode( '|', array( $case['case_uuid'], $to_status, $expected_version, $event_type, $payload_digest ) ) );
	}

	private function mutation_replay_after_rollback( $scope, $principal_hash, $idempotency_key, $operation_digest, $owner_guard = null ) {
		$replay = $this->idempotent_result( $scope, $principal_hash, $idempotency_key, $operation_digest );
		if ( is_wp_error( $replay ) ) {
			return $replay;
		}
		if ( $replay && $owner_guard && ! $this->owner_guard_matches( $replay, $owner_guard ) ) {
			return new WP_Error( 'tra_vel_quote_case_forbidden', 'Quote-case ownership changed before this replay.', array( 'status' => 403 ) );
		}
		return $replay ? array( 'case' => $replay, 'event' => null, 'replayed' => true ) : null;
	}

	private function insert_revision( $case_id, $revision_no, $snapshot, $digest, $actor_type, $actor_user_id ) {
		global $wpdb;
		return false !== $wpdb->insert(
			self::revisions_table(),
			array(
				'case_id'                 => absint( $case_id ),
				'revision_no'             => absint( $revision_no ),
				'source_request_uuid'     => (string) $snapshot['request_id'],
				'source_request_revision' => absint( $snapshot['revision'] ),
				'request_digest'          => (string) $digest,
				'request_snapshot'        => wp_json_encode( $snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'actor_type'              => sanitize_key( $actor_type ),
				'actor_user_id'           => absint( $actor_user_id ),
				'created_at'              => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	private function find_reusable_handoff( $case_id, $case_version, $channel, $provider, $owner_guard ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::HANDOFF_REUSE_SECONDS );
		$sql    = 'SELECT e.* FROM ' . self::events_table() . ' e INNER JOIN ' . self::cases_table() . ' c ON c.id = e.case_id WHERE e.case_id = %d AND e.case_version = %d AND e.event_type = %s AND e.created_at >= %s';
		$params = array( absint( $case_id ), absint( $case_version ), 'handoff.prepared', $cutoff );
		if ( absint( $owner_guard['owner_user_id'] ?? 0 ) > 0 ) {
			$sql     .= ' AND c.owner_user_id = %d';
			$params[] = absint( $owner_guard['owner_user_id'] );
		} else {
			$sql     .= ' AND c.owner_user_id = 0 AND c.owner_token_hash = %s';
			$params[] = (string) ( $owner_guard['owner_token_hash'] ?? '' );
		}
		$sql .= ' ORDER BY e.sequence_no DESC LIMIT 1';
		$row = $wpdb->get_row(
			$wpdb->prepare( $sql, $params ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		if ( (int) $row['case_version'] !== (int) $case_version ) {
			return null;
		}
		$event = $this->hydrate_event( $row );
		$data  = is_array( $event['data'] ?? null ) ? $event['data'] : array();
		if ( sanitize_key( $channel ) !== ( $data['channel'] ?? '' ) || sanitize_key( $provider ) !== ( $data['provider'] ?? '' ) || strtotime( (string) ( $data['expires_at'] ?? '' ) ) <= time() + self::HANDOFF_MIN_REMAINING_SECONDS ) {
			return null;
		}
		return $event;
	}

	private function count_recent_handoffs( $case_id ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::HANDOFF_WINDOW_SECONDS );
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::events_table() . ' WHERE case_id = %d AND event_type = %s AND created_at >= %s', absint( $case_id ), 'handoff.prepared', $cutoff ) );
	}

	private function insert_event( $case_id, $sequence, $version, $type, $from, $to, $actor_type, $actor_user_id, $source, $visibility, $message, $payload ) {
		global $wpdb;
		$payload        = is_array( $payload ) ? $payload : array();
		$encoded        = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$event_uuid     = wp_generate_uuid4();
		$inserted       = $wpdb->insert(
			self::events_table(),
			array(
				'case_id'          => absint( $case_id ),
				'sequence_no'      => absint( $sequence ),
				'case_version'     => absint( $version ),
				'event_uuid'       => $event_uuid,
				'event_type'       => substr( preg_replace( '/[^a-z0-9._-]/', '', strtolower( (string) $type ) ), 0, 64 ),
				'from_status'      => null === $from ? null : sanitize_key( $from ),
				'to_status'        => sanitize_key( $to ),
				'actor_type'       => sanitize_key( $actor_type ),
				'actor_user_id'    => absint( $actor_user_id ),
				'source'           => sanitize_key( $source ),
				'visibility'       => 'internal' === $visibility ? 'internal' : 'public',
				'message'          => sanitize_text_field( $message ),
				'payload'          => $encoded,
				'payload_digest'   => hash( 'sha256', $encoded ),
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			return false;
		}
		return array(
			'contract_version' => Tra_Vel_Quote_Case_Policy::CONTRACT_VERSION,
			'event_id'        => $event_uuid,
			'sequence'        => absint( $sequence ),
			'type'            => (string) $type,
			'from_status'     => $from,
			'to_status'       => $to,
			'actor_type'      => $actor_type,
			'source'          => $source,
			'visibility'      => $visibility,
			'message'         => sanitize_text_field( $message ),
			'data'            => $payload,
			'occurred_at'     => gmdate( 'c' ),
		);
	}

	private function get_revision( $case_id, $revision_no ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::revisions_table() . ' WHERE case_id = %d AND revision_no = %d LIMIT 1', absint( $case_id ), absint( $revision_no ) ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}
		$snapshot = json_decode( (string) $row['request_snapshot'], true );
		$row['request_snapshot'] = is_array( $snapshot ) ? $snapshot : array();
		return $row;
	}

	private function hydrate_case( $row ) {
		foreach ( array( 'id', 'source_run_id', 'source_request_revision', 'owner_user_id', 'case_version', 'current_revision', 'last_event_sequence', 'assigned_user_id', 'legal_hold' ) as $field ) {
			$row[ $field ] = (int) $row[ $field ];
		}
		$joined_snapshot = array_key_exists( 'current_request_snapshot', $row ) ? json_decode( (string) $row['current_request_snapshot'], true ) : null;
		unset( $row['current_request_snapshot'] );
		if ( is_array( $joined_snapshot ) ) {
			$row['snapshot'] = $joined_snapshot;
		} else {
			$revision        = $this->get_revision( $row['id'], $row['current_revision'] );
			$row['snapshot'] = $revision ? $revision['request_snapshot'] : array();
		}
		return $row;
	}

	private function attach_creation_events( $cases, $include_internal ) {
		global $wpdb;
		if ( ! $cases ) {
			return array();
		}
		$ids          = array_values( array_map( 'absint', wp_list_pluck( $cases, 'id' ) ) );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = 'SELECT * FROM ' . self::events_table() . " WHERE case_id IN ({$placeholders}) AND sequence_no = 1";
		$params       = $ids;
		if ( ! $include_internal ) {
			$sql .= ' AND visibility = %s';
			$params[] = 'public';
		}
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		$by_case = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$by_case[ (int) $row['case_id'] ] = array( $this->hydrate_event( $row ) );
		}
		foreach ( $cases as &$case ) {
			$case['_embedded_events'] = $by_case[ (int) $case['id'] ] ?? array();
		}
		unset( $case );
		return $cases;
	}

	private function hydrate_event( $row ) {
		$payload = json_decode( (string) $row['payload'], true );
		return array(
			'contract_version' => Tra_Vel_Quote_Case_Policy::CONTRACT_VERSION,
			'event_id'        => (string) $row['event_uuid'],
			'sequence'        => (int) $row['sequence_no'],
			'type'            => (string) $row['event_type'],
			'from_status'     => null === $row['from_status'] || '' === $row['from_status'] ? null : (string) $row['from_status'],
			'to_status'       => (string) $row['to_status'],
			'actor_type'      => (string) $row['actor_type'],
			'source'          => (string) $row['source'],
			'visibility'      => (string) $row['visibility'],
			'message'         => (string) $row['message'],
			'data'            => is_array( $payload ) ? $payload : array(),
			'occurred_at'     => gmdate( 'c', strtotime( $row['created_at'] . ' UTC' ) ),
		);
	}

	private function unique_reference() {
		global $wpdb;
		for ( $attempt = 0; $attempt < 8; $attempt++ ) {
			$bytes = function_exists( 'random_bytes' ) ? random_bytes( 5 ) : wp_generate_password( 10, false, false );
			$raw   = is_string( $bytes ) ? substr( preg_replace( '/[^A-Z0-9]/', '', strtoupper( base64_encode( $bytes ) ) ), 0, 8 ) : '';
			$raw   = str_pad( $raw, 8, '7' );
			$code  = 'TV-' . $raw;
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::cases_table() . ' WHERE reference_code = %s LIMIT 1', $code ) ) ) {
				return $code;
			}
		}
		return 'TV-' . strtoupper( substr( hash( 'sha256', wp_generate_uuid4() ), 0, 8 ) );
	}

	private function sanitize_idempotency_key( $key ) {
		return substr( preg_replace( '/[^A-Za-z0-9._:-]/', '', (string) $key ), 0, 100 );
	}

	private function idempotent_result( $scope, $principal_hash, $key, $request_digest ) {
		global $wpdb;
		$key_hash = hash( 'sha256', $this->sanitize_idempotency_key( $key ) );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::idempotency_table() . ' WHERE operation_scope = %s AND principal_hash = %s AND idempotency_key_hash = %s AND expires_at > %s LIMIT 1',
				(string) $scope, (string) $principal_hash, $key_hash, current_time( 'mysql', true )
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		if ( ! hash_equals( (string) $row['request_digest'], (string) $request_digest ) ) {
			return new WP_Error( 'tra_vel_quote_case_idempotency_conflict', 'This idempotency key was already used for different quote-case data.', array( 'status' => 409 ) );
		}
		return $this->get_case_by_uuid( $row['case_uuid'] );
	}

	private function record_idempotency( $scope, $principal_hash, $key, $request_digest, $case_uuid, $version, $response_code ) {
		return $this->insert_idempotency( $scope, $principal_hash, $key, $request_digest, $case_uuid, $version, $response_code );
	}

	private function insert_idempotency( $scope, $principal_hash, $key, $request_digest, $case_uuid, $version, $response_code ) {
		global $wpdb;
		$key = $this->sanitize_idempotency_key( $key );
		if ( strlen( $key ) < 16 ) {
			return false;
		}
		$key_hash = hash( 'sha256', $key );
		$expired_deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::idempotency_table() . ' WHERE operation_scope = %s AND principal_hash = %s AND idempotency_key_hash = %s AND expires_at <= %s',
				sanitize_key( $scope ), (string) $principal_hash, $key_hash, current_time( 'mysql', true )
			)
		);
		if ( false === $expired_deleted ) {
			return false;
		}
		return false !== $wpdb->insert(
			self::idempotency_table(),
			array(
				'operation_scope'      => sanitize_key( $scope ),
				'principal_hash'       => (string) $principal_hash,
				'idempotency_key_hash' => $key_hash,
				'request_digest'       => (string) $request_digest,
				'case_uuid'            => (string) $case_uuid,
				'case_version'         => absint( $version ),
				'response_code'        => absint( $response_code ),
				'created_at'           => current_time( 'mysql', true ),
				'expires_at'           => gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Confirm the columns, transactional engine, and unique indexes required
	 * by the durable aggregate. A version option alone cannot prove that a
	 * dbDelta migration completed safely.
	 *
	 * @return array<string,int|bool>
	 */
	private static function inspect_schema() {
		global $wpdb;
		$requirements = array(
			self::cases_table() => array(
				'columns' => array( 'id', 'case_uuid', 'reference_code', 'source_run_id', 'source_run_uuid', 'source_request_uuid', 'source_request_revision', 'owner_user_id', 'owner_token_hash', 'status', 'case_version', 'current_revision', 'latest_request_digest', 'last_event_sequence', 'service_mode', 'assigned_user_id', 'consent_version', 'consented_at', 'created_at', 'updated_at', 'last_activity_at', 'service_expires_at', 'retention_until', 'closed_at', 'legal_hold' ),
				'unique'  => array( array( 'case_uuid' ), array( 'reference_code' ), array( 'source_run_uuid' ) ),
			),
			self::revisions_table() => array(
				'columns' => array( 'id', 'case_id', 'revision_no', 'source_request_uuid', 'source_request_revision', 'request_digest', 'request_snapshot', 'actor_type', 'actor_user_id', 'created_at' ),
				'unique'  => array( array( 'case_id', 'revision_no' ) ),
				'non_unique' => array( array( 'case_id', 'request_digest' ) ),
			),
			self::events_table() => array(
				'columns' => array( 'id', 'case_id', 'sequence_no', 'case_version', 'event_uuid', 'event_type', 'from_status', 'to_status', 'actor_type', 'actor_user_id', 'source', 'visibility', 'message', 'payload', 'payload_digest', 'created_at' ),
				'unique'  => array( array( 'event_uuid' ), array( 'case_id', 'sequence_no' ) ),
			),
			self::idempotency_table() => array(
				'columns' => array( 'id', 'operation_scope', 'principal_hash', 'idempotency_key_hash', 'request_digest', 'case_uuid', 'case_version', 'response_code', 'created_at', 'expires_at' ),
				'unique'  => array( array( 'operation_scope', 'principal_hash', 'idempotency_key_hash' ) ),
			),
		);
		$ready                = 0;
		$transactional_tables = 0;
		$required_indexes     = 0;
		$ready_indexes        = 0;
		$required_supporting_indexes = 0;
		$ready_supporting_indexes    = 0;
		$was_suppressed = $wpdb->suppress_errors();
		foreach ( $requirements as $table => $requirement ) {
			$identifier = str_replace( '`', '``', (string) $table );
			$columns    = $wpdb->get_col( "SHOW COLUMNS FROM `{$identifier}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted WordPress-prefixed table identifier.
			$status     = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', (string) $table ), ARRAY_A );
			$indexes    = $wpdb->get_results( "SHOW INDEX FROM `{$identifier}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted WordPress-prefixed table identifier.
			$engine_ok  = is_array( $status ) && 'innodb' === strtolower( (string) ( $status['Engine'] ?? '' ) );
			if ( $engine_ok ) {
				$transactional_tables++;
			}

			$unique_indexes = array();
			$non_unique_indexes = array();
			foreach ( is_array( $indexes ) ? $indexes : array() as $index ) {
				$key = (string) ( $index['Key_name'] ?? '' );
				$seq = max( 1, (int) ( $index['Seq_in_index'] ?? 1 ) );
				if ( 0 === (int) ( $index['Non_unique'] ?? 1 ) ) {
					$unique_indexes[ $key ][ $seq ] = (string) ( $index['Column_name'] ?? '' );
				} else {
					$non_unique_indexes[ $key ][ $seq ] = (string) ( $index['Column_name'] ?? '' );
				}
			}
			foreach ( $unique_indexes as &$index_columns ) {
				ksort( $index_columns );
				$index_columns = array_values( $index_columns );
			}
			unset( $index_columns );
			foreach ( $non_unique_indexes as &$index_columns ) {
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
			$table_ready_supporting = 0;
			$required_non_unique = $requirement['non_unique'] ?? array();
			$required_supporting_indexes += count( $required_non_unique );
			foreach ( $required_non_unique as $required_index ) {
				foreach ( $non_unique_indexes as $index_columns ) {
					if ( $required_index === $index_columns ) {
						$table_ready_supporting++;
						$ready_supporting_indexes++;
						break;
					}
				}
			}

			if ( is_array( $columns ) && ! array_diff( $requirement['columns'], $columns ) && $engine_ok && count( $requirement['unique'] ) === $table_ready_indexes && count( $required_non_unique ) === $table_ready_supporting ) {
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
			'required_supporting_indexes' => $required_supporting_indexes,
			'ready_supporting_indexes'    => $ready_supporting_indexes,
			'supporting_indexes_ready'    => $required_supporting_indexes === $ready_supporting_indexes,
			'tables_ready'           => $expected === $ready && $expected === $transactional_tables && $required_indexes === $ready_indexes && $required_supporting_indexes === $ready_supporting_indexes,
		);
	}

	/**
	 * Replace the legacy unique digest index with the required lookup index.
	 *
	 * @return bool
	 */
	private static function ensure_revision_digest_index() {
		global $wpdb;
		$table      = self::revisions_table();
		$identifier = str_replace( '`', '``', (string) $table );
		$rows       = $wpdb->get_results( "SHOW INDEX FROM `{$identifier}` WHERE Key_name = 'case_digest'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted WordPress-prefixed table identifier and fixed key.
		$columns    = array();
		$non_unique = null;
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$columns[ max( 1, (int) ( $row['Seq_in_index'] ?? 1 ) ) ] = (string) ( $row['Column_name'] ?? '' );
			$non_unique = (int) ( $row['Non_unique'] ?? 0 );
		}
		ksort( $columns );
		if ( 1 === $non_unique && array( 'case_id', 'request_digest' ) === array_values( $columns ) ) {
			return true;
		}
		if ( ! empty( $rows ) && false === $wpdb->query( "ALTER TABLE `{$identifier}` DROP INDEX `case_digest`" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted identifier and fixed index.
			return false;
		}
		return false !== $wpdb->query( "ALTER TABLE `{$identifier}` ADD KEY `case_digest` (`case_id`,`request_digest`)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted identifier and fixed index/columns.
	}

	public static function cases_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_quote_cases';
	}

	public static function revisions_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_quote_case_revisions';
	}

	public static function events_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_quote_case_events';
	}

	public static function idempotency_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_quote_case_idempotency';
	}
}
