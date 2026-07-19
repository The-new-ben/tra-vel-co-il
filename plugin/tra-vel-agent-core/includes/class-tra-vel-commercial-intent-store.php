<?php
/**
 * Durable ownership, idempotency and audit ledger for commercial intent.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Commercial_Intent_Store {
	const DB_VERSION        = '1.0.0';
	const DB_VERSION_OPTION = 'tra_vel_commercial_intent_db_version';
	const CLEANUP_STATUS_OPTION = 'tra_vel_commercial_intent_cleanup_status';
	const ACTIVE_DAYS       = 30;
	const RETENTION_DAYS    = 90;
	const IDEMPOTENCY_DAYS  = 7;
	const HANDOFF_WINDOW_SECONDS = 3600;
	const HANDOFF_WINDOW_LIMIT   = 6;

	/** @var bool|null */
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
		$intents     = self::intents_table();
		$events      = self::events_table();
		$idempotency = self::idempotency_table();

		dbDelta( "CREATE TABLE {$intents} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			intent_uuid char(36) NOT NULL,
			reference_code varchar(16) NOT NULL,
			owner_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			owner_token_hash char(64) NOT NULL DEFAULT '',
			vertical varchar(24) NOT NULL,
			intent_version bigint(20) unsigned NOT NULL DEFAULT 1,
			last_event_sequence bigint(20) unsigned NOT NULL DEFAULT 1,
			scope longtext NOT NULL,
			scope_digest char(64) NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			retention_until datetime NOT NULL,
			legal_hold tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY intent_uuid (intent_uuid),
			UNIQUE KEY reference_code (reference_code),
			KEY account_scope (owner_user_id,scope_digest,expires_at),
			KEY guest_scope (owner_token_hash,scope_digest,expires_at),
			KEY expiry (expires_at),
			KEY retention (retention_until,legal_hold)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			intent_id bigint(20) unsigned NOT NULL,
			sequence_no bigint(20) unsigned NOT NULL,
			intent_version bigint(20) unsigned NOT NULL,
			event_uuid char(36) NOT NULL,
			event_type varchar(64) NOT NULL,
			actor_type varchar(20) NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source varchar(24) NOT NULL,
			message text NOT NULL,
			payload longtext NOT NULL,
			payload_digest char(64) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_uuid (event_uuid),
			UNIQUE KEY intent_sequence (intent_id,sequence_no),
			KEY intent_type (intent_id,event_type),
			KEY event_created (event_type,created_at)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$idempotency} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			operation_scope varchar(48) NOT NULL,
			principal_hash char(64) NOT NULL,
			idempotency_key_hash char(64) NOT NULL,
			request_digest char(64) NOT NULL,
			intent_uuid char(36) NOT NULL,
			intent_version bigint(20) unsigned NOT NULL,
			event_uuid char(36) NOT NULL DEFAULT '',
			response_code smallint(5) unsigned NOT NULL DEFAULT 200,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY operation_key (operation_scope,principal_hash,idempotency_key_hash),
			KEY intent_lookup (intent_uuid,intent_version),
			KEY expiry (expires_at)
		) ENGINE=InnoDB {$charset};" );

		self::$ready_cache = null;
		$health = self::inspect_schema();
		if ( ! empty( $health['tables_ready'] ) ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		} else {
			delete_option( self::DB_VERSION_OPTION );
		}
	}

	public static function schema_health() {
		$health    = self::inspect_schema();
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );
		self::$ready_cache = self::DB_VERSION === $installed && ! empty( $health['tables_ready'] );
		return array_merge(
			array(
				'schema_version'           => self::DB_VERSION,
				'installed_schema_version' => $installed ? $installed : null,
				'active_days'              => self::ACTIVE_DAYS,
				'retention_days'           => self::RETENTION_DAYS,
			),
			$health
		);
	}

	public static function is_ready() {
		if ( null === self::$ready_cache ) {
			$installed = (string) get_option( self::DB_VERSION_OPTION, '' );
			$health    = self::inspect_schema();
			self::$ready_cache = self::DB_VERSION === $installed && ! empty( $health['tables_ready'] );
		}
		return (bool) self::$ready_cache;
	}

	/**
	 * Create or resume an active intent for the same owner and safe scope.
	 *
	 * @param array  $scope Normalized policy scope.
	 * @param array  $principal Owner data.
	 * @param string $idempotency_key Client operation key.
	 * @return array|WP_Error
	 */
	public function create_or_resume( $scope, $principal, $idempotency_key ) {
		global $wpdb;
		$scope_digest     = Tra_Vel_Commercial_Intent_Policy::digest( $scope );
		$principal_hash   = (string) ( $principal['principal_hash'] ?? '' );
		$operation_digest = Tra_Vel_Commercial_Intent_Policy::digest( array( 'scope' => $scope, 'operation' => 'intent.create' ) );
		$replay           = $this->idempotent_result( 'intent.create', $principal_hash, $idempotency_key, $operation_digest );
		if ( $replay ) {
			return is_wp_error( $replay ) ? $replay : array( 'intent' => $replay['intent'], 'event' => $replay['event'], 'replayed' => true, 'reused' => false, 'created' => false );
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'tra_vel_commercial_transaction_failed', 'The commercial request transaction could not be started.', array( 'status' => 500 ) );
		}

		$locked_replay = $this->idempotent_result( 'intent.create', $principal_hash, $idempotency_key, $operation_digest, true );
		if ( $locked_replay ) {
			$wpdb->query( 'ROLLBACK' );
			return is_wp_error( $locked_replay ) ? $locked_replay : array( 'intent' => $locked_replay['intent'], 'event' => $locked_replay['event'], 'replayed' => true, 'reused' => false, 'created' => false );
		}

		$existing = $this->find_active_by_scope( $principal, $scope_digest, true );
		if ( $existing ) {
			if ( ! $this->insert_idempotency( 'intent.create', $principal_hash, $idempotency_key, $operation_digest, $existing['intent_uuid'], $existing['intent_version'], '', 200 ) ) {
				$wpdb->query( 'ROLLBACK' );
				$recovered = $this->idempotent_result( 'intent.create', $principal_hash, $idempotency_key, $operation_digest );
				if ( $recovered && ! is_wp_error( $recovered ) ) {
					return array( 'intent' => $recovered['intent'], 'event' => $recovered['event'], 'replayed' => true, 'reused' => false, 'created' => false );
				}
				if ( is_wp_error( $recovered ) ) {
					return $recovered;
				}
				return new WP_Error( 'tra_vel_commercial_idempotency_failed', 'The resumed commercial request could not be recorded safely.', array( 'status' => 500 ) );
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				$recovered = $this->idempotent_result( 'intent.create', $principal_hash, $idempotency_key, $operation_digest );
				if ( $recovered && ! is_wp_error( $recovered ) ) {
					return array( 'intent' => $recovered['intent'], 'event' => $recovered['event'], 'replayed' => true, 'reused' => false, 'created' => false );
				}
				return new WP_Error( 'tra_vel_commercial_transaction_failed', 'The resumed commercial request could not be committed.', array( 'status' => 500 ) );
			}
			return array( 'intent' => $existing, 'event' => null, 'replayed' => false, 'reused' => true, 'created' => false );
		}

		$now       = current_time( 'mysql', true );
		$uuid      = wp_generate_uuid4();
		$reference = $this->unique_reference();
		$encoded   = Tra_Vel_Commercial_Intent_Policy::canonical_json( $scope );
		$inserted  = $wpdb->insert(
			self::intents_table(),
			array(
				'intent_uuid'        => $uuid,
				'reference_code'     => $reference,
				'owner_user_id'      => absint( $principal['user_id'] ?? 0 ),
				'owner_token_hash'   => absint( $principal['user_id'] ?? 0 ) > 0 ? '' : (string) ( $principal['token_hash'] ?? '' ),
				'vertical'           => (string) $scope['vertical'],
				'intent_version'     => 1,
				'last_event_sequence'=> 1,
				'scope'              => $encoded,
				'scope_digest'       => $scope_digest,
				'created_at'         => $now,
				'updated_at'         => $now,
				'expires_at'         => gmdate( 'Y-m-d H:i:s', time() + self::ACTIVE_DAYS * DAY_IN_SECONDS ),
				'retention_until'    => gmdate( 'Y-m-d H:i:s', time() + self::RETENTION_DAYS * DAY_IN_SECONDS ),
				'legal_hold'         => 0,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_commercial_store_failed', 'The commercial request could not be saved.', array( 'status' => 500 ) );
		}
		$intent_id = (int) $wpdb->insert_id;
		$event     = $this->insert_event(
			$intent_id,
			1,
			1,
			'commercial_intent.created',
			'traveler',
			absint( $principal['user_id'] ?? 0 ),
			'web',
			'בקשת הבדיקה נשמרה. המחיר, הזמינות והתנאים הסופיים יאושרו בהצעה האישית.',
			array( 'vertical' => (string) $scope['vertical'], 'data_mode' => (string) $scope['data_mode'], 'side_effect_executed' => false )
		);
		if ( ! $event || ! $this->insert_idempotency( 'intent.create', $principal_hash, $idempotency_key, $operation_digest, $uuid, 1, $event['event_id'], 201 ) ) {
			$wpdb->query( 'ROLLBACK' );
			$recovered = $this->idempotent_result( 'intent.create', $principal_hash, $idempotency_key, $operation_digest );
			if ( $recovered && ! is_wp_error( $recovered ) ) {
				return array( 'intent' => $recovered['intent'], 'event' => $recovered['event'], 'replayed' => true, 'reused' => false, 'created' => false );
			}
			if ( is_wp_error( $recovered ) ) {
				return $recovered;
			}
			return new WP_Error( 'tra_vel_commercial_transaction_failed', 'The commercial request audit record could not be saved.', array( 'status' => 500 ) );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$recovered = $this->idempotent_result( 'intent.create', $principal_hash, $idempotency_key, $operation_digest );
			if ( $recovered && ! is_wp_error( $recovered ) ) {
				return array( 'intent' => $recovered['intent'], 'event' => $recovered['event'], 'replayed' => true, 'reused' => false, 'created' => false );
			}
			return new WP_Error( 'tra_vel_commercial_transaction_failed', 'The commercial request could not be committed.', array( 'status' => 500 ) );
		}
		return array( 'intent' => $this->get_by_uuid( $uuid ), 'event' => $event, 'replayed' => false, 'reused' => false, 'created' => true );
	}

	/**
	 * Record a prepared handoff before its URL is returned to the browser.
	 *
	 * @param string $intent_uuid Intent UUID.
	 * @param int    $expected_version Optimistic version.
	 * @param array  $principal Owner data.
	 * @param string $provider Resolved provider.
	 * @param string $channel Handoff channel.
	 * @param string $target_digest SHA-256 digest of the exact allowlisted handoff URL.
	 * @param string $expires_at Prepared URL expiry.
	 * @param string $idempotency_key Client operation key.
	 * @return array|WP_Error
	 */
	public function record_handoff( $intent_uuid, $expected_version, $principal, $provider, $channel, $target_digest, $expires_at, $idempotency_key ) {
		global $wpdb;
		$principal_hash   = (string) ( $principal['principal_hash'] ?? '' );
		$target_digest    = strtolower( (string) $target_digest );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $target_digest ) ) {
			return new WP_Error( 'tra_vel_commercial_handoff_target_invalid', 'The assisted-contact target could not be bound to its audit record.', array( 'status' => 500 ) );
		}
		$operation_digest = Tra_Vel_Commercial_Intent_Policy::digest(
			array(
				'intent_id'       => (string) $intent_uuid,
				'expected_version'=> absint( $expected_version ),
				'provider'        => sanitize_key( $provider ),
				'channel'         => sanitize_key( $channel ),
				'target_digest'   => $target_digest,
				'operation'       => 'handoff.prepare',
			)
		);
		$replay = $this->idempotent_result( 'handoff.prepare', $principal_hash, $idempotency_key, $operation_digest );
		if ( $replay ) {
			if ( is_wp_error( $replay ) ) {
				return $replay;
			}
			if ( ! $this->can_access( $replay['intent'], absint( $principal['user_id'] ?? 0 ), (string) ( $principal['token_hash'] ?? '' ) ) ) {
				return new WP_Error( 'tra_vel_commercial_forbidden', 'This private commercial request belongs to another traveler.', array( 'status' => 403 ) );
			}
			$replay_expiry = is_array( $replay['event'] ?? null ) ? strtotime( (string) ( $replay['event']['data']['expires_at'] ?? '' ) ) : false;
			if ( false === $replay_expiry || $replay_expiry <= time() + 30 ) {
				return new WP_Error( 'tra_vel_commercial_handoff_replay_expired', 'The previous contact link has expired. Prepare a fresh handoff.', array( 'status' => 409, 'current_version' => (int) $replay['intent']['intent_version'] ) );
			}
			return array( 'intent' => $replay['intent'], 'event' => $replay['event'], 'replayed' => true );
		}

		$intent = $this->get_by_uuid( $intent_uuid );
		if ( ! $intent ) {
			return new WP_Error( 'tra_vel_commercial_intent_missing', 'Commercial request not found.', array( 'status' => 404 ) );
		}
		if ( ! $this->can_access( $intent, absint( $principal['user_id'] ?? 0 ), (string) ( $principal['token_hash'] ?? '' ) ) ) {
			return new WP_Error( 'tra_vel_commercial_forbidden', 'This private commercial request belongs to another traveler.', array( 'status' => 403 ) );
		}
		if ( strtotime( (string) $intent['expires_at'] . ' UTC' ) <= time() ) {
			return new WP_Error( 'tra_vel_commercial_intent_expired', 'This commercial request has expired. Start a fresh price check.', array( 'status' => 409 ) );
		}
		if ( (int) $intent['intent_version'] !== (int) $expected_version ) {
			return new WP_Error( 'tra_vel_commercial_version_conflict', 'The commercial request changed before the handoff was prepared.', array( 'status' => 409, 'current_version' => (int) $intent['intent_version'] ) );
		}
		if ( $this->count_recent_handoffs( $intent['id'] ) >= self::HANDOFF_WINDOW_LIMIT ) {
			return new WP_Error( 'tra_vel_commercial_rate_limited', 'Too many contact links were prepared for this request. Please wait before trying again.', array( 'status' => 429, 'retry_after' => 300 ) );
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'tra_vel_commercial_transaction_failed', 'The handoff transaction could not be started.', array( 'status' => 500 ) );
		}
		$locked = $this->get_owned_for_update( $intent_uuid, $principal );
		if ( ! $locked ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_commercial_version_conflict', 'The commercial request changed before the handoff was saved.', array( 'status' => 409 ) );
		}
		$locked_replay = $this->idempotent_result( 'handoff.prepare', $principal_hash, $idempotency_key, $operation_digest, true );
		if ( $locked_replay ) {
			$wpdb->query( 'ROLLBACK' );
			return is_wp_error( $locked_replay ) ? $locked_replay : array( 'intent' => $locked_replay['intent'], 'event' => $locked_replay['event'], 'replayed' => true );
		}
		if ( (int) $locked['intent_version'] !== (int) $expected_version ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_commercial_version_conflict', 'Another action reached the commercial request first.', array( 'status' => 409, 'current_version' => (int) $locked['intent_version'] ) );
		}
		if ( strtotime( (string) $locked['expires_at'] . ' UTC' ) <= time() ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_commercial_intent_expired', 'This commercial request has expired. Start a fresh price check.', array( 'status' => 409 ) );
		}
		if ( $this->count_recent_handoffs( $locked['id'] ) >= self::HANDOFF_WINDOW_LIMIT ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_commercial_rate_limited', 'Too many contact links were prepared for this request. Please wait before trying again.', array( 'status' => 429, 'retry_after' => 300 ) );
		}

		$new_version = (int) $locked['intent_version'] + 1;
		$sequence    = (int) $locked['last_event_sequence'] + 1;
		$now         = current_time( 'mysql', true );
		$updated     = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::intents_table() . ' SET intent_version = %d, last_event_sequence = %d, updated_at = %s WHERE id = %d AND intent_version = %d',
				$new_version,
				$sequence,
				$now,
				(int) $locked['id'],
				(int) $expected_version
			)
		);
		if ( 1 !== $updated ) {
			$wpdb->query( 'ROLLBACK' );
			$recovered = $this->idempotent_result( 'handoff.prepare', $principal_hash, $idempotency_key, $operation_digest );
			if ( $recovered && ! is_wp_error( $recovered ) && $this->can_access( $recovered['intent'], absint( $principal['user_id'] ?? 0 ), (string) ( $principal['token_hash'] ?? '' ) ) ) {
				return array( 'intent' => $recovered['intent'], 'event' => $recovered['event'], 'replayed' => true );
			}
			return new WP_Error( 'tra_vel_commercial_version_conflict', 'Another action reached the commercial request first.', array( 'status' => 409 ) );
		}
		$event = $this->insert_event(
			$locked['id'],
			$sequence,
			$new_version,
			'handoff.prepared',
			'traveler',
			absint( $principal['user_id'] ?? 0 ),
			'web',
			'ערוץ הקשר הוכן. לא בוצעו הזמנה, שמירת מקום או חיוב.',
			array(
				'provider'            => sanitize_key( $provider ),
				'channel'             => sanitize_key( $channel ),
				'target_digest'       => $target_digest,
				'expires_at'          => (string) $expires_at,
				'dispatched'          => false,
				'side_effect_executed'=> false,
			)
		);
		if ( ! $event || ! $this->insert_idempotency( 'handoff.prepare', $principal_hash, $idempotency_key, $operation_digest, $intent_uuid, $new_version, $event['event_id'], 200 ) ) {
			$wpdb->query( 'ROLLBACK' );
			$recovered = $this->idempotent_result( 'handoff.prepare', $principal_hash, $idempotency_key, $operation_digest );
			if ( $recovered && ! is_wp_error( $recovered ) && $this->can_access( $recovered['intent'], absint( $principal['user_id'] ?? 0 ), (string) ( $principal['token_hash'] ?? '' ) ) ) {
				return array( 'intent' => $recovered['intent'], 'event' => $recovered['event'], 'replayed' => true );
			}
			if ( is_wp_error( $recovered ) ) {
				return $recovered;
			}
			return new WP_Error( 'tra_vel_commercial_transaction_failed', 'The handoff audit event could not be recorded.', array( 'status' => 500 ) );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$recovered = $this->idempotent_result( 'handoff.prepare', $principal_hash, $idempotency_key, $operation_digest );
			if ( $recovered && ! is_wp_error( $recovered ) && $this->can_access( $recovered['intent'], absint( $principal['user_id'] ?? 0 ), (string) ( $principal['token_hash'] ?? '' ) ) ) {
				return array( 'intent' => $recovered['intent'], 'event' => $recovered['event'], 'replayed' => true );
			}
			return new WP_Error( 'tra_vel_commercial_transaction_failed', 'The prepared handoff could not be committed.', array( 'status' => 500 ) );
		}
		return array( 'intent' => $this->get_by_uuid( $intent_uuid ), 'event' => $event, 'replayed' => false );
	}

	public function get_by_uuid( $intent_uuid ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::intents_table() . ' WHERE intent_uuid = %s LIMIT 1', (string) $intent_uuid ), ARRAY_A );
		return is_array( $row ) ? $this->hydrate_intent( $row ) : null;
	}

	public function can_access( $intent, $user_id, $guest_token_hash ) {
		if ( ! is_array( $intent ) ) {
			return false;
		}
		if ( $user_id > 0 && (int) $intent['owner_user_id'] === (int) $user_id ) {
			return true;
		}
		return 0 === (int) $intent['owner_user_id']
			&& 64 === strlen( (string) $guest_token_hash )
			&& 64 === strlen( (string) $intent['owner_token_hash'] )
			&& hash_equals( (string) $intent['owner_token_hash'], (string) $guest_token_hash );
	}

	public function get_event_by_uuid( $event_uuid ) {
		global $wpdb;
		if ( ! $event_uuid ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::events_table() . ' WHERE event_uuid = %s LIMIT 1', (string) $event_uuid ), ARRAY_A );
		return is_array( $row ) ? $this->hydrate_event( $row ) : null;
	}

	public static function cleanup() {
		global $wpdb;
		$result = array( 'deleted_intents' => 0, 'deleted_idempotency' => 0, 'schema_ready' => true, 'errors' => array() );
		if ( ! self::is_ready() ) {
			$result['schema_ready'] = false;
			$result['errors'][]     = 'schema_not_ready';
			self::record_cleanup_status( $result );
			return $result;
		}
		$now = current_time( 'mysql', true );
		$was_suppressed = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::intents_table() . ' WHERE retention_until < %s AND legal_hold = 0 ORDER BY id ASC LIMIT 100', $now ) );
		$select_error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( '' !== $select_error ) {
			$result['errors'][] = 'retention_select_failed';
			self::record_cleanup_status( $result );
			return $result;
		}
		$ids = is_array( $ids ) ? array_values( array_map( 'absint', $ids ) ) : array();
		if ( $ids ) {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				$result['errors'][] = 'retention_transaction_start_failed';
				self::record_cleanup_status( $result );
				return $result;
			}
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$was_suppressed = $wpdb->suppress_errors();
			$wpdb->last_error = '';
			$uuids = $wpdb->get_col( $wpdb->prepare( 'SELECT intent_uuid FROM ' . self::intents_table() . " WHERE id IN ({$placeholders}) FOR UPDATE", $ids ) );
			$lock_error = (string) $wpdb->last_error;
			$wpdb->suppress_errors( $was_suppressed );
			$uuids = is_array( $uuids ) ? array_values( array_map( 'strval', $uuids ) ) : array();
			if ( '' !== $lock_error || count( $ids ) !== count( $uuids ) ) {
				$wpdb->query( 'ROLLBACK' );
				$result['errors'][] = '' !== $lock_error ? 'retention_lock_select_failed' : 'retention_lock_incomplete';
				self::record_cleanup_status( $result );
				return $result;
			}
			$uuid_placeholders = implode( ',', array_fill( 0, count( $uuids ), '%s' ) );
			$children = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::events_table() . " WHERE intent_id IN ({$placeholders})", $ids ) );
			$idem = $uuids ? $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::idempotency_table() . " WHERE intent_uuid IN ({$uuid_placeholders})", $uuids ) ) : 0;
			$parents = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::intents_table() . " WHERE id IN ({$placeholders}) AND retention_until < %s AND legal_hold = 0", array_merge( $ids, array( $now ) ) ) );
			if ( false !== $children && false !== $idem && count( $ids ) === (int) $parents && false !== $wpdb->query( 'COMMIT' ) ) {
				$result['deleted_intents'] = (int) $parents;
			} else {
				$wpdb->query( 'ROLLBACK' );
				$result['errors'][] = 'retention_delete_failed';
				self::record_cleanup_status( $result );
				return $result;
			}
		}
		$deleted_idempotency = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::idempotency_table() . ' WHERE expires_at < %s ORDER BY id ASC LIMIT 1000', $now ) );
		if ( false === $deleted_idempotency ) {
			$result['errors'][] = 'idempotency_delete_failed';
		} else {
			$result['deleted_idempotency'] = (int) $deleted_idempotency;
		}
		self::record_cleanup_status( $result );
		return $result;
	}

	private static function record_cleanup_status( $result ) {
		update_option(
			self::CLEANUP_STATUS_OPTION,
			array(
				'last_run_at'         => gmdate( 'c' ),
				'ok'                  => empty( $result['errors'] ),
				'errors'              => array_values( array_unique( (array) ( $result['errors'] ?? array() ) ) ),
				'deleted_intents'     => absint( $result['deleted_intents'] ?? 0 ),
				'deleted_idempotency' => absint( $result['deleted_idempotency'] ?? 0 ),
			),
			false
		);
	}

	private function find_active_by_scope( $principal, $scope_digest, $for_update = false ) {
		global $wpdb;
		$user_id = absint( $principal['user_id'] ?? 0 );
		$now     = current_time( 'mysql', true );
		if ( $user_id > 0 ) {
			$sql = $wpdb->prepare( 'SELECT * FROM ' . self::intents_table() . ' WHERE owner_user_id = %d AND scope_digest = %s AND expires_at > %s ORDER BY id DESC LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ), $user_id, (string) $scope_digest, $now );
		} else {
			$token_hash = (string) ( $principal['token_hash'] ?? '' );
			if ( 64 !== strlen( $token_hash ) ) {
				return null;
			}
			$sql = $wpdb->prepare( 'SELECT * FROM ' . self::intents_table() . ' WHERE owner_user_id = 0 AND owner_token_hash = %s AND scope_digest = %s AND expires_at > %s ORDER BY id DESC LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ), $token_hash, (string) $scope_digest, $now );
		}
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fully prepared above.
		return is_array( $row ) ? $this->hydrate_intent( $row ) : null;
	}

	private function get_owned_for_update( $intent_uuid, $principal ) {
		global $wpdb;
		$user_id = absint( $principal['user_id'] ?? 0 );
		if ( $user_id > 0 ) {
			$sql = $wpdb->prepare( 'SELECT * FROM ' . self::intents_table() . ' WHERE intent_uuid = %s AND owner_user_id = %d LIMIT 1 FOR UPDATE', (string) $intent_uuid, $user_id );
		} else {
			$token_hash = (string) ( $principal['token_hash'] ?? '' );
			if ( 64 !== strlen( $token_hash ) ) {
				return null;
			}
			$sql = $wpdb->prepare( 'SELECT * FROM ' . self::intents_table() . ' WHERE intent_uuid = %s AND owner_user_id = 0 AND owner_token_hash = %s LIMIT 1 FOR UPDATE', (string) $intent_uuid, $token_hash );
		}
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fully prepared above.
		return is_array( $row ) ? $this->hydrate_intent( $row ) : null;
	}

	private function insert_event( $intent_id, $sequence, $version, $type, $actor_type, $actor_user_id, $source, $message, $payload ) {
		global $wpdb;
		$payload    = is_array( $payload ) ? $payload : array();
		$encoded    = Tra_Vel_Commercial_Intent_Policy::canonical_json( $payload );
		$event_uuid = wp_generate_uuid4();
		$inserted   = $wpdb->insert(
			self::events_table(),
			array(
				'intent_id'       => absint( $intent_id ),
				'sequence_no'     => absint( $sequence ),
				'intent_version'  => absint( $version ),
				'event_uuid'      => $event_uuid,
				'event_type'      => substr( preg_replace( '/[^a-z0-9._-]/', '', strtolower( (string) $type ) ), 0, 64 ),
				'actor_type'      => sanitize_key( $actor_type ),
				'actor_user_id'   => absint( $actor_user_id ),
				'source'          => sanitize_key( $source ),
				'message'         => sanitize_text_field( $message ),
				'payload'         => $encoded,
				'payload_digest'  => hash( 'sha256', $encoded ),
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			return false;
		}
		return array(
			'contract_version' => Tra_Vel_Commercial_Intent_Policy::EVENT_CONTRACT_VERSION,
			'event_id'         => $event_uuid,
			'sequence'         => absint( $sequence ),
			'intent_version'   => absint( $version ),
			'type'             => (string) $type,
			'actor_type'       => sanitize_key( $actor_type ),
			'source'           => sanitize_key( $source ),
			'message'          => sanitize_text_field( $message ),
			'data'             => $payload,
			'occurred_at'      => gmdate( 'c' ),
		);
	}

	private function hydrate_intent( $row ) {
		foreach ( array( 'id', 'owner_user_id', 'intent_version', 'last_event_sequence', 'legal_hold' ) as $field ) {
			$row[ $field ] = (int) $row[ $field ];
		}
		$scope       = json_decode( (string) $row['scope'], true );
		$row['scope'] = is_array( $scope ) ? $scope : array();
		return $row;
	}

	private function hydrate_event( $row ) {
		$payload = json_decode( (string) $row['payload'], true );
		return array(
			'contract_version' => Tra_Vel_Commercial_Intent_Policy::EVENT_CONTRACT_VERSION,
			'event_id'         => (string) $row['event_uuid'],
			'sequence'         => (int) $row['sequence_no'],
			'intent_version'   => (int) $row['intent_version'],
			'type'             => (string) $row['event_type'],
			'actor_type'       => (string) $row['actor_type'],
			'source'           => (string) $row['source'],
			'message'          => (string) $row['message'],
			'data'             => is_array( $payload ) ? $payload : array(),
			'occurred_at'      => gmdate( 'c', strtotime( $row['created_at'] . ' UTC' ) ),
		);
	}

	private function idempotent_result( $scope, $principal_hash, $key, $request_digest, $for_update = false ) {
		global $wpdb;
		$key_hash = hash( 'sha256', $this->sanitize_idempotency_key( $key ) );
		$row      = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::idempotency_table() . ' WHERE operation_scope = %s AND principal_hash = %s AND idempotency_key_hash = %s AND expires_at > %s LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ),
				$this->sanitize_operation_scope( $scope ),
				(string) $principal_hash,
				$key_hash,
				current_time( 'mysql', true )
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		if ( ! hash_equals( (string) $row['request_digest'], (string) $request_digest ) ) {
			return new WP_Error( 'tra_vel_commercial_idempotency_conflict', 'This idempotency key was already used for different commercial data.', array( 'status' => 409 ) );
		}
		$intent = $this->get_by_uuid( $row['intent_uuid'] );
		if ( ! $intent ) {
			return new WP_Error( 'tra_vel_commercial_replay_unavailable', 'The previous commercial request cannot be replayed safely.', array( 'status' => 409 ) );
		}
		$event = $this->get_event_by_uuid( $row['event_uuid'] );
		if ( '' !== (string) $row['event_uuid'] && ! $event ) {
			return new WP_Error( 'tra_vel_commercial_replay_unavailable', 'The previous commercial audit event cannot be replayed safely.', array( 'status' => 409 ) );
		}
		return array( 'intent' => $intent, 'event' => $event );
	}

	private function insert_idempotency( $scope, $principal_hash, $key, $request_digest, $intent_uuid, $version, $event_uuid, $response_code ) {
		global $wpdb;
		$key = $this->sanitize_idempotency_key( $key );
		if ( strlen( $key ) < 16 || 64 !== strlen( (string) $principal_hash ) ) {
			return false;
		}
		$key_hash = hash( 'sha256', $key );
		$deleted  = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::idempotency_table() . ' WHERE operation_scope = %s AND principal_hash = %s AND idempotency_key_hash = %s AND expires_at <= %s',
				$this->sanitize_operation_scope( $scope ),
				(string) $principal_hash,
				$key_hash,
				current_time( 'mysql', true )
			)
		);
		if ( false === $deleted ) {
			return false;
		}
		return false !== $wpdb->insert(
			self::idempotency_table(),
			array(
				'operation_scope'      => $this->sanitize_operation_scope( $scope ),
				'principal_hash'       => (string) $principal_hash,
				'idempotency_key_hash' => $key_hash,
				'request_digest'       => (string) $request_digest,
				'intent_uuid'          => (string) $intent_uuid,
				'intent_version'       => absint( $version ),
				'event_uuid'           => (string) $event_uuid,
				'response_code'        => absint( $response_code ),
				'created_at'           => current_time( 'mysql', true ),
				'expires_at'           => gmdate( 'Y-m-d H:i:s', time() + self::IDEMPOTENCY_DAYS * DAY_IN_SECONDS ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);
	}

	private function count_recent_handoffs( $intent_id ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::HANDOFF_WINDOW_SECONDS );
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::events_table() . ' WHERE intent_id = %d AND event_type = %s AND created_at >= %s', absint( $intent_id ), 'handoff.prepared', $cutoff ) );
	}

	private function unique_reference() {
		global $wpdb;
		for ( $attempt = 0; $attempt < 8; $attempt++ ) {
			try {
				$bytes = random_bytes( 6 );
			} catch ( Exception $error ) {
				$bytes = wp_generate_password( 12, false, false );
			}
			$raw  = substr( preg_replace( '/[^A-Z0-9]/', '', strtoupper( base64_encode( (string) $bytes ) ) ), 0, 8 );
			$code = 'TVI-' . str_pad( $raw, 8, '7' );
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::intents_table() . ' WHERE reference_code = %s LIMIT 1', $code ) ) ) {
				return $code;
			}
		}
		return 'TVI-' . strtoupper( substr( hash( 'sha256', wp_generate_uuid4() ), 0, 8 ) );
	}

	private function sanitize_idempotency_key( $key ) {
		return substr( preg_replace( '/[^A-Za-z0-9._:-]/', '', (string) $key ), 0, 100 );
	}

	private function sanitize_operation_scope( $scope ) {
		return substr( preg_replace( '/[^a-z0-9._-]/', '', strtolower( (string) $scope ) ), 0, 48 );
	}

	private static function inspect_schema() {
		global $wpdb;
		$requirements = array(
			self::intents_table() => array(
				'columns' => array( 'id', 'intent_uuid', 'reference_code', 'owner_user_id', 'owner_token_hash', 'vertical', 'intent_version', 'last_event_sequence', 'scope', 'scope_digest', 'created_at', 'updated_at', 'expires_at', 'retention_until', 'legal_hold' ),
				'unique'  => array( array( 'intent_uuid' ), array( 'reference_code' ) ),
			),
			self::events_table() => array(
				'columns' => array( 'id', 'intent_id', 'sequence_no', 'intent_version', 'event_uuid', 'event_type', 'actor_type', 'actor_user_id', 'source', 'message', 'payload', 'payload_digest', 'created_at' ),
				'unique'  => array( array( 'event_uuid' ), array( 'intent_id', 'sequence_no' ) ),
			),
			self::idempotency_table() => array(
				'columns' => array( 'id', 'operation_scope', 'principal_hash', 'idempotency_key_hash', 'request_digest', 'intent_uuid', 'intent_version', 'event_uuid', 'response_code', 'created_at', 'expires_at' ),
				'unique'  => array( array( 'operation_scope', 'principal_hash', 'idempotency_key_hash' ) ),
			),
		);
		$ready = 0;
		$transactional = 0;
		$required_indexes = 0;
		$ready_indexes = 0;
		$was_suppressed = $wpdb->suppress_errors();
		foreach ( $requirements as $table => $requirement ) {
			$identifier = str_replace( '`', '``', (string) $table );
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$identifier}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted internal table.
			$status  = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', (string) $table ), ARRAY_A );
			$indexes = $wpdb->get_results( "SHOW INDEX FROM `{$identifier}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted internal table.
			$engine_ok = is_array( $status ) && 'innodb' === strtolower( (string) ( $status['Engine'] ?? '' ) );
			if ( $engine_ok ) {
				$transactional++;
			}
			$unique = array();
			foreach ( is_array( $indexes ) ? $indexes : array() as $index ) {
				if ( 0 !== (int) ( $index['Non_unique'] ?? 1 ) ) {
					continue;
				}
				$name = (string) ( $index['Key_name'] ?? '' );
				$unique[ $name ][ max( 1, (int) ( $index['Seq_in_index'] ?? 1 ) ) ] = (string) ( $index['Column_name'] ?? '' );
			}
			foreach ( $unique as &$index_columns ) {
				ksort( $index_columns );
				$index_columns = array_values( $index_columns );
			}
			unset( $index_columns );
			$table_indexes = 0;
			$required_indexes += count( $requirement['unique'] );
			foreach ( $requirement['unique'] as $required ) {
				foreach ( $unique as $actual ) {
					if ( $required === $actual ) {
						$table_indexes++;
						$ready_indexes++;
						break;
					}
				}
			}
			if ( is_array( $columns ) && ! array_diff( $requirement['columns'], $columns ) && $engine_ok && count( $requirement['unique'] ) === $table_indexes ) {
				$ready++;
			}
		}
		$wpdb->suppress_errors( $was_suppressed );
		$expected = count( $requirements );
		return array(
			'expected_tables'        => $expected,
			'ready_tables'           => $ready,
			'transactional_tables'   => $transactional,
			'required_indexes'       => $required_indexes,
			'ready_indexes'          => $ready_indexes,
			'required_indexes_ready' => $required_indexes === $ready_indexes,
			'tables_ready'           => $expected === $ready && $expected === $transactional && $required_indexes === $ready_indexes,
		);
	}

	public static function intents_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_commercial_intents';
	}

	public static function events_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_commercial_intent_events';
	}

	public static function idempotency_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_commercial_intent_idempotency';
	}
}
