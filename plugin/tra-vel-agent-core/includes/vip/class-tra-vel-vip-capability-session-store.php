<?php
/**
 * Durable digest-only store for one-time VIP capability exchange.
 *
 * The raw grant and session values exist only in server memory and the final
 * Secure HttpOnly cookie. Database rows contain keyed digests, opaque private
 * references, closed low-risk scopes, clocks, and bounded replay metadata.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_VIP_Capability_Session_Store {
	const DB_VERSION            = '1.1.0';
	const DB_VERSION_OPTION     = 'tra_vel_vip_capability_session_db_version';
	const CLEANUP_STATUS_OPTION = 'tra_vel_vip_capability_session_cleanup_status';
	const READINESS_CACHE_OPTION = 'tra_vel_vip_capability_session_readiness_cache';
	const READINESS_CACHE_TTL_SECONDS = 300;
	const GRANT_RETENTION_DAYS  = 30;
	const SESSION_RETENTION_DAYS = 30;
	const IDEMPOTENCY_DAYS      = 2;
	const MAX_IDEMPOTENCY_KEY_BYTES = 100;

	/** @var array|null */
	private static $ready_cache = null;

	public static function maybe_upgrade() {
		if ( self::DB_VERSION !== (string) get_option( self::DB_VERSION_OPTION, '' ) ) {
			self::invalidate_readiness_cache();
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		self::invalidate_readiness_cache();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset  = $wpdb->get_charset_collate();
		$grants   = self::grants_table();
		$sessions = self::sessions_table();
		$exchanges = self::exchanges_table();
		$limits   = self::limits_table();

		dbDelta( "CREATE TABLE {$grants} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			capability_ref varchar(112) NOT NULL,
			capability_digest char(64) NOT NULL,
			trip_ref varchar(112) NOT NULL,
			case_ref varchar(112) NOT NULL DEFAULT '',
			account_ref varchar(112) NOT NULL DEFAULT '',
			issue_reason varchar(32) NOT NULL,
			channel varchar(24) NOT NULL,
			allowed_scopes longtext NOT NULL,
			disclosure_classes longtext NOT NULL,
			rotation_generation int(10) unsigned NOT NULL,
			mutation_operation varchar(16) NOT NULL DEFAULT '',
			mutation_reason_code varchar(96) NOT NULL DEFAULT '',
			mutation_previous_generation int(10) unsigned NULL,
			mutation_sessions_revoked int(10) unsigned NOT NULL DEFAULT 0,
			mutated_at datetime NULL,
			issued_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			consumed_at datetime NULL,
			revoked_at datetime NULL,
			retention_until datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY capability_ref (capability_ref),
			UNIQUE KEY capability_digest (capability_digest),
			KEY expiry (expires_at,revoked_at),
			KEY retention (retention_until,id)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$sessions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_ref varchar(128) NOT NULL,
			session_digest char(64) NOT NULL,
			grant_id bigint(20) unsigned NOT NULL,
			capability_ref varchar(112) NOT NULL,
			capability_digest char(64) NOT NULL,
			trip_ref varchar(112) NOT NULL,
			case_ref varchar(112) NOT NULL DEFAULT '',
			account_ref varchar(112) NOT NULL DEFAULT '',
			allowed_scopes longtext NOT NULL,
			disclosure_classes longtext NOT NULL,
			rotation_generation int(10) unsigned NOT NULL,
			state varchar(16) NOT NULL,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			revoked_at datetime NULL,
			retention_until datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_ref (session_ref),
			UNIQUE KEY session_digest (session_digest),
			UNIQUE KEY one_session_per_grant (grant_id),
			KEY active_expiry (state,expires_at),
			KEY retention (retention_until,id)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$exchanges} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			grant_id bigint(20) unsigned NOT NULL,
			idempotency_key_hash char(64) NOT NULL,
			request_digest char(64) NOT NULL,
			session_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY grant_exchange_key (grant_id,idempotency_key_hash),
			KEY session_replay (session_id),
			KEY expiry (expires_at,id)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$limits} (
			limit_key char(64) NOT NULL,
			hits smallint(5) unsigned NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (limit_key),
			KEY expiry (expires_at)
		) ENGINE=InnoDB {$charset};" );

		$health = self::inspect_schema();
		if ( ! empty( $health['tables_ready'] ) ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		} else {
			delete_option( self::DB_VERSION_OPTION );
		}
		self::persist_readiness_cache( $health );
	}

	public static function schema_health() {
		return self::readiness_health();
	}

	public static function is_ready() {
		$health = self::readiness_health();
		return self::DB_VERSION === (string) $health['installed_schema_version'] && ! empty( $health['tables_ready'] );
	}

	/** Delete request-local and durable readiness state before every install/upgrade. */
	public static function invalidate_readiness_cache() {
		self::$ready_cache = null;
		delete_option( self::READINESS_CACHE_OPTION );
	}

	/**
	 * Issue a one-time grant only after an upstream server integration explicitly
	 * authorizes the exact closed request. Never expose this method as REST.
	 *
	 * @return array|WP_Error Raw exchange_value is returned once to the trusted caller.
	 */
	public function issue_server_grant( $request, $now = null ) {
		$validated = Tra_Vel_VIP_Capability_Session_Policy::issuance_request( $request );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$authorized = apply_filters( 'tra_vel_vip_capability_grant_issuable', false, $validated );
		if ( true !== $authorized ) {
			return self::error( 'issuance_not_authorized', 'A trusted upstream service must authorize this capability grant.', 403 );
		}
		$now_timestamp = null === $now ? time() : (int) $now;
		$exchange_value = self::random_value( 32 );
		$capability_ref = 'tv_capability_' . self::random_value( 18 );
		$grant = array(
			'contract_version' => Tra_Vel_VIP_Taxonomy::CONTRACT_VERSION,
			'capability_ref'   => $capability_ref,
			'capability_digest'=> self::capability_digest( $exchange_value ),
			'trip_ref'         => $validated['trip_ref'],
			'case_ref'         => $validated['case_ref'],
			'account_ref'      => $validated['account_ref'],
			'issue_reason'     => $validated['issue_reason'],
			'channel'          => $validated['channel'],
			'allowed_scopes'   => array_values( $validated['allowed_scopes'] ),
			'disclosure_classes'=> array_values( $validated['disclosure_classes'] ),
			'one_time'         => true,
			'consumed_at'      => null,
			'issued_at'        => gmdate( 'Y-m-d\TH:i:s\Z', $now_timestamp ),
			'expires_at'       => gmdate( 'Y-m-d\TH:i:s\Z', $now_timestamp + (int) $validated['lifetime_seconds'] ),
			'revoked_at'       => null,
			'rotation_generation' => (int) $validated['rotation_generation'],
			'scanner_safe_initial_get' => true,
			'session_exchange_required' => true,
			'security' => array(
				'http_only' => true,
				'secure' => true,
				'same_site' => 'Strict',
				'referrer_policy' => 'no-referrer',
				'third_party_scripts' => false,
				'indexable' => false,
			),
			'data_boundary' => Tra_Vel_VIP_Capability_Session_Policy::safe_data_boundary(),
		);
		$grant = Tra_Vel_VIP_Policy::capability_grant( $grant );
		if ( is_wp_error( $grant ) ) {
			return $grant;
		}
		$stored = $this->persist_grant( $grant );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		return array(
			'grant'          => $grant,
			'exchange_value' => $exchange_value,
		);
	}

	/**
	 * Persist a validated grant. Protected to permit a deterministic memory harness.
	 *
	 * @return true|WP_Error
	 */
	protected function persist_grant( $grant ) {
		global $wpdb;
		$valid = Tra_Vel_VIP_Policy::capability_grant( $grant );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$inserted = $wpdb->insert(
			self::grants_table(),
			array(
				'capability_ref' => $grant['capability_ref'],
				'capability_digest' => $grant['capability_digest'],
				'trip_ref' => $grant['trip_ref'],
				'case_ref' => null === $grant['case_ref'] ? '' : $grant['case_ref'],
				'account_ref' => null === $grant['account_ref'] ? '' : $grant['account_ref'],
				'issue_reason' => $grant['issue_reason'],
				'channel' => $grant['channel'],
				'allowed_scopes' => self::canonical_json( $grant['allowed_scopes'] ),
				'disclosure_classes' => self::canonical_json( $grant['disclosure_classes'] ),
				'rotation_generation' => $grant['rotation_generation'],
				'issued_at' => self::mysql_utc( $grant['issued_at'] ),
				'expires_at' => self::mysql_utc( $grant['expires_at'] ),
				'consumed_at' => null,
				'revoked_at' => null,
				'retention_until' => gmdate( 'Y-m-d H:i:s', strtotime( $grant['expires_at'] ) + self::GRANT_RETENTION_DAYS * DAY_IN_SECONDS ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		return false === $inserted ? self::error( 'grant_store_failed', 'The capability grant could not be stored safely.', 500 ) : true;
	}

	/**
	 * Atomically consume a grant or replay the exact committed response.
	 *
	 * The cookie value is deterministically regenerated from the submitted grant,
	 * idempotency key, request digest, and rotation. It is never stored raw, which
	 * prevents a lost HTTP response from stranding the legitimate browser.
	 *
	 * @return array|WP_Error
	 */
	public function exchange( $exchange_value, $idempotency_key, $request_digest, $now = null ) {
		global $wpdb;
		if ( ! self::valid_secret( $exchange_value ) || ! self::valid_idempotency_key( $idempotency_key ) || ! self::digest_valid( $request_digest ) ) {
			return self::unavailable();
		}
		$now_timestamp = null === $now ? time() : (int) $now;
		$now_mysql = gmdate( 'Y-m-d H:i:s', $now_timestamp );
		$capability_digest = self::capability_digest( $exchange_value );
		$key_hash = self::idempotency_key_hash( $idempotency_key );

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return self::error( 'transaction_failed', 'The capability exchange could not start safely.', 503 );
		}
		$grant_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::grants_table() . ' WHERE capability_digest = %s LIMIT 1 FOR UPDATE', $capability_digest ), ARRAY_A );
		if ( ! is_array( $grant_row ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::unavailable();
		}
		$grant = $this->hydrate_grant( $grant_row );
		$valid_grant = Tra_Vel_VIP_Policy::capability_grant( $grant );
		if ( is_wp_error( $valid_grant ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::unavailable();
		}

		$prior = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::exchanges_table() . ' WHERE grant_id = %d AND idempotency_key_hash = %s LIMIT 1 FOR UPDATE', (int) $grant_row['id'], $key_hash ),
			ARRAY_A
		);
		if ( is_array( $prior ) ) {
			if ( ! hash_equals( (string) $prior['request_digest'], (string) $request_digest ) ) {
				$wpdb->query( 'ROLLBACK' );
				return self::error( 'idempotency_conflict', 'The retry key is already bound to another capability exchange.', 409 );
			}
			$session_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::sessions_table() . ' WHERE id = %d LIMIT 1 FOR UPDATE', (int) $prior['session_id'] ), ARRAY_A );
			$recovered = $this->recover_exact_session( $session_row, $grant, $exchange_value, $idempotency_key, $request_digest, $now_timestamp );
			if ( is_wp_error( $recovered ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $recovered;
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				return self::error( 'transaction_failed', 'The capability replay could not be confirmed safely.', 503 );
			}
			$recovered['replayed'] = true;
			return $recovered;
		}

		if ( null !== $grant['consumed_at'] || null !== $grant['revoked_at'] || strtotime( $grant['expires_at'] ) <= $now_timestamp ) {
			$wpdb->query( 'ROLLBACK' );
			return self::unavailable();
		}

		$session_value = self::derive_session_value( $exchange_value, $idempotency_key, $request_digest, $grant['capability_ref'], $grant['rotation_generation'] );
		$session_digest = self::session_digest( $session_value );
		$session_ref = 'tv_capability_session_' . substr( hash_hmac( 'sha256', 'ref:' . $session_digest, wp_salt( 'nonce' ) ), 0, 32 );
		$session_expires = min( strtotime( $grant['expires_at'] ), $now_timestamp + Tra_Vel_VIP_Capability_Session_Policy::SESSION_TTL_SECONDS );
		if ( $session_expires <= $now_timestamp ) {
			$wpdb->query( 'ROLLBACK' );
			return self::unavailable();
		}
		$session = $this->build_session( $grant, $session_ref, $session_digest, $now_timestamp, $session_expires );
		$session = Tra_Vel_VIP_Capability_Session_Policy::session( $session, gmdate( 'Y-m-d\TH:i:s\Z', $now_timestamp ) );
		if ( is_wp_error( $session ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $session;
		}

		$inserted_session = $wpdb->insert(
			self::sessions_table(),
			array(
				'session_ref' => $session['session_ref'],
				'session_digest' => $session['session_digest'],
				'grant_id' => (int) $grant_row['id'],
				'capability_ref' => $session['capability_ref'],
				'capability_digest' => $session['capability_digest'],
				'trip_ref' => $session['trip_ref'],
				'case_ref' => null === $session['case_ref'] ? '' : $session['case_ref'],
				'account_ref' => null === $session['account_ref'] ? '' : $session['account_ref'],
				'allowed_scopes' => self::canonical_json( $session['allowed_scopes'] ),
				'disclosure_classes' => self::canonical_json( $session['disclosure_classes'] ),
				'rotation_generation' => $session['rotation_generation'],
				'state' => 'active',
				'created_at' => self::mysql_utc( $session['created_at'] ),
				'expires_at' => self::mysql_utc( $session['expires_at'] ),
				'revoked_at' => null,
				'retention_until' => gmdate( 'Y-m-d H:i:s', $session_expires + self::SESSION_RETENTION_DAYS * DAY_IN_SECONDS ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		$session_id = (int) $wpdb->insert_id;
		$grant_updated = $wpdb->query(
			$wpdb->prepare( 'UPDATE ' . self::grants_table() . ' SET consumed_at = %s WHERE id = %d AND consumed_at IS NULL AND revoked_at IS NULL AND rotation_generation = %d', $now_mysql, (int) $grant_row['id'], (int) $grant['rotation_generation'] )
		);
		$exchange_inserted = $wpdb->insert(
			self::exchanges_table(),
			array(
				'grant_id' => (int) $grant_row['id'],
				'idempotency_key_hash' => $key_hash,
				'request_digest' => $request_digest,
				'session_id' => $session_id,
				'created_at' => $now_mysql,
				'expires_at' => gmdate( 'Y-m-d H:i:s', $now_timestamp + self::IDEMPOTENCY_DAYS * DAY_IN_SECONDS ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( false === $inserted_session || 1 !== (int) $grant_updated || false === $exchange_inserted || false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'exchange_conflict', 'The capability exchange could not be reconciled safely.', 409 );
		}
		return array( 'session' => $session, 'session_value' => $session_value, 'created' => true, 'replayed' => false );
	}

	/**
	 * Resolve the current private-browser session without exposing another trip.
	 *
	 * @return array|WP_Error
	 */
	public function current_session( $session_value, $now = null ) {
		if ( ! self::valid_secret( $session_value ) ) {
			return self::session_missing();
		}
		$session = $this->session_by_digest( self::session_digest( $session_value ) );
		return $this->validate_live_session( $session, $now );
	}

	/**
	 * Downstream server helper that proves an exact closed trip/case/account
	 * binding, low-risk scope, and optional disclosure class. It has no
	 * supplier/payment side effect.
	 *
	 * @return array|WP_Error
	 */
	public function resolve_scoped_session( $session_value, $binding, $required_scope, $disclosure_class = null, $now = null ) {
		if ( ! in_array( $required_scope, Tra_Vel_VIP_Taxonomy::LOW_RISK_CAPABILITY_SCOPES, true ) || in_array( $required_scope, Tra_Vel_VIP_Taxonomy::HIGH_IMPACT_SCOPES, true ) ) {
			return self::error( 'scope_denied', 'The capability session cannot authorize this operation.', 403 );
		}
		$binding_keys = array( 'trip_ref', 'case_ref', 'account_ref' );
		$actual_keys  = is_array( $binding ) ? array_keys( $binding ) : array();
		sort( $binding_keys, SORT_STRING );
		sort( $actual_keys, SORT_STRING );
		if ( ! is_array( $binding ) || $actual_keys !== $binding_keys || ! is_string( $binding['trip_ref'] ) || ( null !== $binding['case_ref'] && ! is_string( $binding['case_ref'] ) ) || ( null !== $binding['account_ref'] && ! is_string( $binding['account_ref'] ) ) ) {
			return self::session_missing();
		}
		$session = $this->current_session( $session_value, $now );
		if (
			is_wp_error( $session )
			|| ! hash_equals( (string) $session['trip_ref'], $binding['trip_ref'] )
			|| ! self::nullable_binding_equals( $session['case_ref'], $binding['case_ref'] )
			|| ! self::nullable_binding_equals( $session['account_ref'], $binding['account_ref'] )
			|| ! in_array( $required_scope, $session['allowed_scopes'], true )
			|| ( null !== $disclosure_class && ! in_array( $disclosure_class, $session['disclosure_classes'], true ) )
		) {
			return self::session_missing();
		}
		return $session;
	}

	/**
	 * Server-only, expected-generation revocation. The grant row is locked and all
	 * bound active sessions are revoked in the same transaction. No REST route
	 * exposes this method and its receipt contains no trip, owner, or token data.
	 *
	 * @return array|WP_Error
	 */
	public function revoke_server_grant( $capability_ref, $expected_generation, $reason_code, $now = null ) {
		return $this->mutate_server_grant( $capability_ref, $expected_generation, 'revoke', $reason_code, $now );
	}

	/**
	 * Server-only rotation invalidation. It advances the locked generation and
	 * revokes every old session; a separately authorized issuance may then create
	 * a replacement grant at the returned next_generation.
	 *
	 * @return array|WP_Error
	 */
	public function rotate_server_grant_generation( $capability_ref, $expected_generation, $reason_code, $now = null ) {
		return $this->mutate_server_grant( $capability_ref, $expected_generation, 'rotate', $reason_code, $now );
	}

	/** @return array|WP_Error */
	private function mutate_server_grant( $capability_ref, $expected_generation, $operation, $reason_code, $now ) {
		global $wpdb;
		if ( ! is_string( $capability_ref ) || 1 !== preg_match( '/^tv_capability_[A-Za-z0-9_-]{16,96}$/', $capability_ref ) || ! is_int( $expected_generation ) || $expected_generation < 1 || $expected_generation > Tra_Vel_VIP_Capability_Session_Policy::MAX_ROTATION_GENERATION || ( 'rotate' === $operation && $expected_generation >= Tra_Vel_VIP_Capability_Session_Policy::MAX_ROTATION_GENERATION ) || ! in_array( $operation, array( 'revoke', 'rotate' ), true ) || ! is_string( $reason_code ) || 1 !== preg_match( '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+){0,7}$/', $reason_code ) || strlen( $reason_code ) > 96 ) {
			return self::error( 'grant_mutation_invalid', 'The server-side grant mutation request is invalid.', 400 );
		}
		$authorized = apply_filters( 'tra_vel_vip_capability_grant_mutation_authorized', false, $capability_ref, $expected_generation, $operation, $reason_code );
		if ( true !== $authorized ) {
			return self::error( 'grant_mutation_not_authorized', 'A trusted upstream service must authorize this capability mutation.', 403 );
		}
		$timestamp = null === $now ? time() : (int) $now;
		$now_mysql = gmdate( 'Y-m-d H:i:s', $timestamp );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return self::error( 'transaction_failed', 'The capability mutation could not start safely.', 503 );
		}
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT id,rotation_generation,revoked_at,mutation_operation,mutation_reason_code,mutation_previous_generation,mutation_sessions_revoked,mutated_at FROM ' . self::grants_table() . ' WHERE capability_ref = %s LIMIT 1 FOR UPDATE', $capability_ref ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'grant_generation_conflict', 'The capability grant changed before this server mutation.', 409 );
		}
		$prior_operation  = (string) ( $row['mutation_operation'] ?? '' );
		$prior_reason     = (string) ( $row['mutation_reason_code'] ?? '' );
		$prior_generation = null === ( $row['mutation_previous_generation'] ?? null ) ? null : (int) $row['mutation_previous_generation'];
		if ( '' !== $prior_operation && ( ! in_array( $prior_operation, array( 'revoke', 'rotate' ), true ) || 1 !== preg_match( '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+){0,7}$/', $prior_reason ) || strlen( $prior_reason ) > 96 || ! is_int( $prior_generation ) || $prior_generation < 1 || $prior_generation > Tra_Vel_VIP_Capability_Session_Policy::MAX_ROTATION_GENERATION || empty( $row['mutated_at'] ) ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'grant_mutation_state_invalid', 'The stored capability mutation receipt is unavailable.', 503 );
		}
		if ( '' !== $prior_operation && $prior_generation === $expected_generation && $prior_operation === $operation ) {
			if ( ! hash_equals( $prior_reason, $reason_code ) ) {
				$wpdb->query( 'ROLLBACK' );
				return self::error( 'grant_mutation_reason_conflict', 'The capability mutation retry changed its original reason.', 409 );
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				return self::error( 'transaction_failed', 'The capability mutation replay could not be confirmed.', 503 );
			}
			return array( 'contract_version' => '1.0.0', 'operation' => $prior_operation, 'state' => 'revoked', 'changed' => false, 'previous_generation' => $prior_generation, 'next_generation' => (int) $row['rotation_generation'], 'reason_code' => $prior_reason, 'mutated_at' => self::iso_utc( $row['mutated_at'] ), 'sessions_revoked' => (int) $row['mutation_sessions_revoked'] );
		}
		if ( (int) $row['rotation_generation'] !== $expected_generation || '' !== $prior_operation || ! empty( $row['revoked_at'] ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'grant_generation_conflict', 'The capability grant changed before this server mutation.', 409 );
		}
		$next_generation = 'rotate' === $operation ? $expected_generation + 1 : $expected_generation;
		$updated = $wpdb->query(
			$wpdb->prepare( 'UPDATE ' . self::grants_table() . ' SET revoked_at = %s, rotation_generation = %d, mutation_operation = %s, mutation_reason_code = %s, mutation_previous_generation = %d, mutated_at = %s WHERE id = %d AND rotation_generation = %d AND revoked_at IS NULL AND mutation_operation = %s', $now_mysql, $next_generation, $operation, $reason_code, $expected_generation, $now_mysql, (int) $row['id'], $expected_generation, '' )
		);
		$sessions_revoked = $wpdb->query(
			$wpdb->prepare( "UPDATE " . self::sessions_table() . " SET state = 'revoked', revoked_at = COALESCE(revoked_at,%s) WHERE grant_id = %d AND state = 'active'", $now_mysql, (int) $row['id'] )
		);
		$receipt_saved = false;
		if ( 1 === (int) $updated && false !== $sessions_revoked ) {
			$receipt_saved = 0 === (int) $sessions_revoked
				? 1
				: $wpdb->query(
					$wpdb->prepare( 'UPDATE ' . self::grants_table() . ' SET mutation_sessions_revoked = %d WHERE id = %d AND mutation_operation = %s AND mutation_reason_code = %s AND mutation_previous_generation = %d', (int) $sessions_revoked, (int) $row['id'], $operation, $reason_code, $expected_generation )
				);
		}
		if ( 1 !== (int) $updated || false === $sessions_revoked || 1 !== (int) $receipt_saved || false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'grant_mutation_failed', 'The capability mutation could not be committed safely.', 503 );
		}
		return array(
			'contract_version' => '1.0.0',
			'operation' => $operation,
			'state' => 'revoked',
			'changed' => true,
			'previous_generation' => $expected_generation,
			'next_generation' => $next_generation,
			'reason_code' => $reason_code,
			'mutated_at' => gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ),
			'sessions_revoked' => (int) $sessions_revoked,
		);
	}

	/**
	 * Revoke the current session. A missing token is intentionally indistinguishable.
	 */
	public function revoke_session( $session_value, $now = null ) {
		global $wpdb;
		if ( ! self::valid_secret( $session_value ) ) {
			return true;
		}
		$now_mysql = gmdate( 'Y-m-d H:i:s', null === $now ? time() : (int) $now );
		$result = $wpdb->query(
			$wpdb->prepare( 'UPDATE ' . self::sessions_table() . " SET state = 'revoked', revoked_at = %s WHERE session_digest = %s AND state = 'active'", $now_mysql, self::session_digest( $session_value ) )
		);
		return false === $result ? self::error( 'revoke_failed', 'The private capability session could not be closed safely.', 503 ) : true;
	}

	/**
	 * Atomic fixed-window limiter; only a keyed digest is persisted.
	 *
	 * @return bool|WP_Error True means allowed, false means exhausted, and every
	 *                       storage failure is a fail-closed 503.
	 */
	public function consume_limit( $limit_key, $limit, $expires_at ) {
		global $wpdb;
		if ( ! self::digest_valid( $limit_key ) || (int) $limit < 1 || (int) $limit > 500 || (int) $expires_at <= time() ) {
			return self::error( 'limit_store_unavailable', 'The private capability request limiter is temporarily unavailable.', 503 );
		}
		$now_mysql = current_time( 'mysql', true );
		$expires_mysql = gmdate( 'Y-m-d H:i:s', (int) $expires_at );
		$sql = $wpdb->prepare(
			'INSERT INTO ' . self::limits_table() . ' (limit_key,hits,expires_at) VALUES (%s,1,%s) ON DUPLICATE KEY UPDATE hits = IF(expires_at <= %s,1,hits+1), expires_at = IF(expires_at <= %s,VALUES(expires_at),expires_at)',
			$limit_key,
			$expires_mysql,
			$now_mysql,
			$now_mysql
		);
		if ( false === $wpdb->query( $sql ) ) {
			return self::error( 'limit_store_unavailable', 'The private capability request limiter is temporarily unavailable.', 503 );
		}
		$hits = $wpdb->get_var( $wpdb->prepare( 'SELECT hits FROM ' . self::limits_table() . ' WHERE limit_key = %s LIMIT 1', $limit_key ) );
		if ( null === $hits ) {
			return self::error( 'limit_store_unavailable', 'The private capability request limiter is temporarily unavailable.', 503 );
		}
		return (int) $hits <= (int) $limit;
	}

	public static function cleanup() {
		global $wpdb;
		$result = array( 'deleted_exchanges' => 0, 'deleted_sessions' => 0, 'deleted_grants' => 0, 'deleted_limits' => 0, 'errors' => array() );
		if ( ! self::is_ready() ) {
			$result['errors'][] = 'schema_not_ready';
			self::record_cleanup_status( $result );
			return $result;
		}
		$now = current_time( 'mysql', true );
		$deleted_exchanges = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::exchanges_table() . ' WHERE expires_at < %s ORDER BY id ASC LIMIT 1000', $now ) );
		$deleted_sessions = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::sessions_table() . ' WHERE retention_until < %s ORDER BY id ASC LIMIT 500', $now ) );
		$deleted_grants = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::grants_table() . ' WHERE retention_until < %s AND id NOT IN (SELECT grant_id FROM ' . self::sessions_table() . ') ORDER BY id ASC LIMIT 500', $now ) );
		$deleted_limits = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::limits_table() . ' WHERE expires_at < %s ORDER BY expires_at ASC LIMIT 1000', $now ) );
		foreach ( array( 'exchanges', 'sessions', 'grants', 'limits' ) as $kind ) {
			$value = ${'deleted_' . $kind};
			if ( false === $value ) {
				$result['errors'][] = $kind . '_cleanup_failed';
			} else {
				$result[ 'deleted_' . $kind ] = (int) $value;
			}
		}
		self::record_cleanup_status( $result );
		return $result;
	}

	protected function session_by_digest( $session_digest ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT s.*,g.capability_digest AS grant_capability_digest,g.rotation_generation AS grant_rotation_generation,g.revoked_at AS grant_revoked_at FROM ' . self::sessions_table() . ' s INNER JOIN ' . self::grants_table() . ' g ON g.id = s.grant_id WHERE s.session_digest = %s LIMIT 1',
				$session_digest
			),
			ARRAY_A
		);
		return is_array( $row ) ? $this->hydrate_session( $row ) : null;
	}

	private function recover_exact_session( $row, $grant, $exchange_value, $idempotency_key, $request_digest, $now_timestamp ) {
		if ( ! is_array( $row ) ) {
			return self::unavailable();
		}
		$session = $this->hydrate_session( $row );
		$session_value = self::derive_session_value( $exchange_value, $idempotency_key, $request_digest, $grant['capability_ref'], $grant['rotation_generation'] );
		if ( ! hash_equals( $session['session_digest'], self::session_digest( $session_value ) ) || $session['rotation_generation'] !== $grant['rotation_generation'] || null !== $grant['revoked_at'] || 'active' !== $session['state'] || strtotime( $session['expires_at'] ) <= $now_timestamp || null !== $session['revoked_at'] ) {
			return self::unavailable();
		}
		$valid = Tra_Vel_VIP_Capability_Session_Policy::session( $session, gmdate( 'Y-m-d\TH:i:s\Z', $now_timestamp ) );
		return is_wp_error( $valid ) ? self::unavailable() : array( 'session' => $session, 'session_value' => $session_value, 'created' => false );
	}

	private function validate_live_session( $session, $now ) {
		if ( ! is_array( $session ) ) {
			return self::session_missing();
		}
		$now_iso = gmdate( 'Y-m-d\TH:i:s\Z', null === $now ? time() : (int) $now );
		$grant_capability_digest = $session['grant_capability_digest'] ?? null;
		$grant_rotation_generation = $session['grant_rotation_generation'] ?? null;
		$grant_revoked_at = $session['grant_revoked_at'] ?? null;
		unset( $session['grant_capability_digest'], $session['grant_rotation_generation'], $session['grant_revoked_at'] );
		if ( null !== $grant_capability_digest && ! hash_equals( (string) $session['capability_digest'], (string) $grant_capability_digest ) ) {
			return self::session_missing();
		}
		if ( null !== $grant_rotation_generation && (int) $grant_rotation_generation !== (int) $session['rotation_generation'] ) {
			return self::session_missing();
		}
		if ( null !== $grant_revoked_at || 'active' !== $session['state'] || null !== $session['revoked_at'] || $session['expires_at'] <= $now_iso ) {
			return self::session_missing();
		}
		$valid = Tra_Vel_VIP_Capability_Session_Policy::session( $session, $now_iso );
		return is_wp_error( $valid ) ? self::session_missing() : $valid;
	}

	private function build_session( $grant, $session_ref, $session_digest, $created, $expires ) {
		return array(
			'contract_version' => Tra_Vel_VIP_Capability_Session_Policy::CONTRACT_VERSION,
			'session_ref' => $session_ref,
			'session_digest' => $session_digest,
			'capability_ref' => $grant['capability_ref'],
			'capability_digest' => $grant['capability_digest'],
			'trip_ref' => $grant['trip_ref'],
			'case_ref' => $grant['case_ref'],
			'account_ref' => $grant['account_ref'],
			'allowed_scopes' => array_values( $grant['allowed_scopes'] ),
			'disclosure_classes' => array_values( $grant['disclosure_classes'] ),
			'rotation_generation' => (int) $grant['rotation_generation'],
			'state' => 'active',
			'created_at' => gmdate( 'Y-m-d\TH:i:s\Z', $created ),
			'expires_at' => gmdate( 'Y-m-d\TH:i:s\Z', $expires ),
			'revoked_at' => null,
			'authorization_effect' => 'low_risk_capability_only',
			'data_boundary' => Tra_Vel_VIP_Capability_Session_Policy::safe_data_boundary(),
		);
	}

	private function hydrate_grant( $row ) {
		return array(
			'contract_version' => Tra_Vel_VIP_Taxonomy::CONTRACT_VERSION,
			'capability_ref' => (string) $row['capability_ref'],
			'capability_digest' => (string) $row['capability_digest'],
			'trip_ref' => (string) $row['trip_ref'],
			'case_ref' => '' === (string) $row['case_ref'] ? null : (string) $row['case_ref'],
			'account_ref' => '' === (string) $row['account_ref'] ? null : (string) $row['account_ref'],
			'issue_reason' => (string) $row['issue_reason'],
			'channel' => (string) $row['channel'],
			'allowed_scopes' => self::decode_list( $row['allowed_scopes'] ),
			'disclosure_classes' => self::decode_list( $row['disclosure_classes'] ),
			'one_time' => true,
			'consumed_at' => empty( $row['consumed_at'] ) ? null : self::iso_utc( $row['consumed_at'] ),
			'issued_at' => self::iso_utc( $row['issued_at'] ),
			'expires_at' => self::iso_utc( $row['expires_at'] ),
			'revoked_at' => empty( $row['revoked_at'] ) ? null : self::iso_utc( $row['revoked_at'] ),
			'rotation_generation' => (int) $row['rotation_generation'],
			'scanner_safe_initial_get' => true,
			'session_exchange_required' => true,
			'security' => array( 'http_only' => true, 'secure' => true, 'same_site' => 'Strict', 'referrer_policy' => 'no-referrer', 'third_party_scripts' => false, 'indexable' => false ),
			'data_boundary' => Tra_Vel_VIP_Capability_Session_Policy::safe_data_boundary(),
		);
	}

	private function hydrate_session( $row ) {
		$session = array(
			'contract_version' => Tra_Vel_VIP_Capability_Session_Policy::CONTRACT_VERSION,
			'session_ref' => (string) $row['session_ref'],
			'session_digest' => (string) $row['session_digest'],
			'capability_ref' => (string) $row['capability_ref'],
			'capability_digest' => (string) ( $row['capability_digest'] ?? '' ),
			'trip_ref' => (string) $row['trip_ref'],
			'case_ref' => '' === (string) $row['case_ref'] ? null : (string) $row['case_ref'],
			'account_ref' => '' === (string) $row['account_ref'] ? null : (string) $row['account_ref'],
			'allowed_scopes' => self::decode_list( $row['allowed_scopes'] ),
			'disclosure_classes' => self::decode_list( $row['disclosure_classes'] ),
			'rotation_generation' => (int) $row['rotation_generation'],
			'state' => (string) $row['state'],
			'created_at' => self::iso_utc( $row['created_at'] ),
			'expires_at' => self::iso_utc( $row['expires_at'] ),
			'revoked_at' => empty( $row['revoked_at'] ) ? null : self::iso_utc( $row['revoked_at'] ),
			'authorization_effect' => 'low_risk_capability_only',
			'data_boundary' => Tra_Vel_VIP_Capability_Session_Policy::safe_data_boundary(),
		);
		foreach ( array( 'grant_capability_digest', 'grant_rotation_generation', 'grant_revoked_at' ) as $extra ) {
			if ( array_key_exists( $extra, $row ) ) {
				if ( 'grant_rotation_generation' === $extra ) {
					$session[ $extra ] = (int) $row[ $extra ];
				} elseif ( 'grant_capability_digest' === $extra ) {
					$session[ $extra ] = (string) $row[ $extra ];
				} else {
					$session[ $extra ] = empty( $row[ $extra ] ) ? null : self::iso_utc( $row[ $extra ] );
				}
			}
		}
		return $session;
	}

	private static function derive_session_value( $exchange_value, $idempotency_key, $request_digest, $capability_ref, $generation ) {
		$material = implode( '|', array( 'vip-capability-session-v1', $capability_ref, (string) $generation, $idempotency_key, $request_digest, $exchange_value ) );
		return rtrim( strtr( base64_encode( hash_hmac( 'sha256', $material, wp_salt( 'secure_auth' ), true ) ), '+/', '-_' ), '=' );
	}

	public static function capability_digest( $value ) {
		return hash_hmac( 'sha256', 'vip-capability:' . (string) $value, wp_salt( 'auth' ) );
	}

	public static function session_digest( $value ) {
		return hash_hmac( 'sha256', 'vip-session:' . (string) $value, wp_salt( 'auth' ) );
	}

	public static function idempotency_key_hash( $value ) {
		return hash_hmac( 'sha256', 'vip-exchange-key:' . (string) $value, wp_salt( 'nonce' ) );
	}

	private static function valid_secret( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $value );
	}

	private static function valid_idempotency_key( $value ) {
		return is_string( $value ) && strlen( $value ) >= 16 && strlen( $value ) <= self::MAX_IDEMPOTENCY_KEY_BYTES && 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value );
	}

	private static function digest_valid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function random_value( $bytes ) {
		try {
			return rtrim( strtr( base64_encode( random_bytes( (int) $bytes ) ), '+/', '-_' ), '=' );
		} catch ( Exception $error ) {
			return substr( preg_replace( '/[^A-Za-z0-9_-]/', '', wp_generate_password( (int) $bytes * 2, false, false ) ), 0, (int) $bytes * 2 );
		}
	}

	private static function canonical_json( $value ) {
		$json = wp_json_encode( array_values( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $json ) ? $json : '[]';
	}

	private static function decode_list( $json ) {
		$value = json_decode( (string) $json, true );
		return is_array( $value ) && array_values( $value ) === $value ? array_values( $value ) : array();
	}

	private static function mysql_utc( $value ) {
		return gmdate( 'Y-m-d H:i:s', strtotime( (string) $value ) );
	}

	private static function iso_utc( $value ) {
		return gmdate( 'Y-m-d\TH:i:s\Z', strtotime( (string) $value . ' UTC' ) );
	}

	private static function unavailable() {
		return self::error( 'exchange_unavailable', 'The capability exchange is unavailable.', 403 );
	}

	private static function session_missing() {
		return self::error( 'session_missing', 'The capability session is unavailable.', 404 );
	}

	private static function nullable_binding_equals( $left, $right ) {
		if ( null === $left || null === $right ) {
			return null === $left && null === $right;
		}
		return is_string( $left ) && is_string( $right ) && hash_equals( $left, $right );
	}

	/** Read a bounded persistent cache before running expensive SHOW inspections. */
	private static function readiness_health() {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );
		$now       = time();
		if ( self::readiness_cache_valid( self::$ready_cache, $installed, $now ) ) {
			return self::decorate_schema_health( self::$ready_cache['health'], $installed );
		}
		$cached = get_option( self::READINESS_CACHE_OPTION, null );
		if ( self::readiness_cache_valid( $cached, $installed, $now ) ) {
			self::$ready_cache = $cached;
			return self::decorate_schema_health( $cached['health'], $installed );
		}
		return self::persist_readiness_cache( self::inspect_schema() );
	}

	private static function persist_readiness_cache( $health ) {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );
		$now       = time();
		$record    = array(
			'cache_version'            => '1.0.0',
			'schema_version'           => self::DB_VERSION,
			'installed_schema_version' => $installed,
			'checked_at'               => $now,
			'expires_at'               => $now + self::READINESS_CACHE_TTL_SECONDS,
			'health'                   => $health,
		);
		self::$ready_cache = $record;
		update_option( self::READINESS_CACHE_OPTION, $record, false );
		return self::decorate_schema_health( $health, $installed );
	}

	private static function readiness_cache_valid( $record, $installed, $now ) {
		$keys = array( 'cache_version', 'schema_version', 'installed_schema_version', 'checked_at', 'expires_at', 'health' );
		if ( ! is_array( $record ) || array_values( array_keys( $record ) ) !== $keys || '1.0.0' !== (string) $record['cache_version'] || self::DB_VERSION !== (string) $record['schema_version'] || (string) $record['installed_schema_version'] !== (string) $installed || ! is_int( $record['checked_at'] ) || ! is_int( $record['expires_at'] ) || $record['checked_at'] > $now + 5 || $record['expires_at'] <= $now || $record['expires_at'] - $record['checked_at'] > self::READINESS_CACHE_TTL_SECONDS ) {
			return false;
		}
		$health_keys = array( 'expected_tables', 'ready_tables', 'transactional_tables', 'required_indexes', 'ready_indexes', 'required_indexes_ready', 'tables_ready' );
		$health      = $record['health'];
		if ( ! is_array( $health ) || array_values( array_keys( $health ) ) !== $health_keys ) {
			return false;
		}
		foreach ( array( 'expected_tables', 'ready_tables', 'transactional_tables', 'required_indexes', 'ready_indexes' ) as $key ) {
			if ( ! is_int( $health[ $key ] ) || $health[ $key ] < 0 ) {
				return false;
			}
		}
		$indexes_ready = 7 === $health['ready_indexes'];
		$tables_ready  = 4 === $health['ready_tables'] && 4 === $health['transactional_tables'] && $indexes_ready;
		return 4 === $health['expected_tables']
			&& $health['ready_tables'] <= 4
			&& $health['transactional_tables'] <= 4
			&& 7 === $health['required_indexes']
			&& $health['ready_indexes'] <= 7
			&& is_bool( $health['required_indexes_ready'] )
			&& is_bool( $health['tables_ready'] )
			&& $indexes_ready === $health['required_indexes_ready']
			&& $tables_ready === $health['tables_ready'];
	}

	private static function decorate_schema_health( $health, $installed ) {
		return array_merge(
			array(
				'schema_version'           => self::DB_VERSION,
				'installed_schema_version' => '' === $installed ? null : $installed,
				'grant_retention_days'      => self::GRANT_RETENTION_DAYS,
				'session_retention_days'    => self::SESSION_RETENTION_DAYS,
				'idempotency_days'          => self::IDEMPOTENCY_DAYS,
				'session_ttl_seconds'       => Tra_Vel_VIP_Capability_Session_Policy::SESSION_TTL_SECONDS,
			),
			$health
		);
	}

	private static function record_cleanup_status( $result ) {
		update_option(
			self::CLEANUP_STATUS_OPTION,
			array(
				'last_run_at' => gmdate( 'c' ),
				'ok' => empty( $result['errors'] ),
				'errors' => array_values( array_unique( (array) $result['errors'] ) ),
				'deleted_exchanges' => absint( $result['deleted_exchanges'] ?? 0 ),
				'deleted_sessions' => absint( $result['deleted_sessions'] ?? 0 ),
				'deleted_grants' => absint( $result['deleted_grants'] ?? 0 ),
				'deleted_limits' => absint( $result['deleted_limits'] ?? 0 ),
			),
			false
		);
	}

	private static function inspect_schema() {
		global $wpdb;
		$requirements = array(
			self::grants_table() => array( 'columns' => array( 'id', 'capability_ref', 'capability_digest', 'trip_ref', 'case_ref', 'account_ref', 'issue_reason', 'channel', 'allowed_scopes', 'disclosure_classes', 'rotation_generation', 'mutation_operation', 'mutation_reason_code', 'mutation_previous_generation', 'mutation_sessions_revoked', 'mutated_at', 'issued_at', 'expires_at', 'consumed_at', 'revoked_at', 'retention_until' ), 'unique' => array( array( 'capability_ref' ), array( 'capability_digest' ) ) ),
			self::sessions_table() => array( 'columns' => array( 'id', 'session_ref', 'session_digest', 'grant_id', 'capability_ref', 'capability_digest', 'trip_ref', 'case_ref', 'account_ref', 'allowed_scopes', 'disclosure_classes', 'rotation_generation', 'state', 'created_at', 'expires_at', 'revoked_at', 'retention_until' ), 'unique' => array( array( 'session_ref' ), array( 'session_digest' ), array( 'grant_id' ) ) ),
			self::exchanges_table() => array( 'columns' => array( 'id', 'grant_id', 'idempotency_key_hash', 'request_digest', 'session_id', 'created_at', 'expires_at' ), 'unique' => array( array( 'grant_id', 'idempotency_key_hash' ) ) ),
			self::limits_table() => array( 'columns' => array( 'limit_key', 'hits', 'expires_at' ), 'unique' => array( array( 'limit_key' ) ) ),
		);
		$ready = 0;
		$transactional = 0;
		$required_indexes = 0;
		$ready_indexes = 0;
		$was_suppressed = $wpdb->suppress_errors();
		foreach ( $requirements as $table => $requirement ) {
			$identifier = str_replace( '`', '``', (string) $table );
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$identifier}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted internal table.
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', (string) $table ), ARRAY_A );
			$indexes = $wpdb->get_results( "SHOW INDEX FROM `{$identifier}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted internal table.
			$engine_ok = is_array( $status ) && 'innodb' === strtolower( (string) ( $status['Engine'] ?? '' ) );
			if ( $engine_ok ) {
				$transactional++;
			}
			$unique = array();
			foreach ( is_array( $indexes ) ? $indexes : array() as $index ) {
				if ( 0 === (int) ( $index['Non_unique'] ?? 1 ) ) {
					$name = (string) ( $index['Key_name'] ?? '' );
					$unique[ $name ][ max( 1, (int) ( $index['Seq_in_index'] ?? 1 ) ) ] = (string) ( $index['Column_name'] ?? '' );
				}
			}
			foreach ( $unique as &$actual_columns ) {
				ksort( $actual_columns );
				$actual_columns = array_values( $actual_columns );
			}
			unset( $actual_columns );
			$table_ready_indexes = 0;
			$required_indexes += count( $requirement['unique'] );
			foreach ( $requirement['unique'] as $required ) {
				foreach ( $unique as $actual ) {
					if ( $required === $actual ) {
						$table_ready_indexes++;
						$ready_indexes++;
						break;
					}
				}
			}
			if ( is_array( $columns ) && ! array_diff( $requirement['columns'], $columns ) && $engine_ok && $table_ready_indexes === count( $requirement['unique'] ) ) {
				$ready++;
			}
		}
		$wpdb->suppress_errors( $was_suppressed );
		$expected = count( $requirements );
		return array( 'expected_tables' => $expected, 'ready_tables' => $ready, 'transactional_tables' => $transactional, 'required_indexes' => $required_indexes, 'ready_indexes' => $ready_indexes, 'required_indexes_ready' => $required_indexes === $ready_indexes, 'tables_ready' => $expected === $ready && $expected === $transactional && $required_indexes === $ready_indexes );
	}

	private static function error( $suffix, $message, $status ) {
		return new WP_Error( 'tra_vel_vip_capability_' . $suffix, $message, array( 'status' => (int) $status ) );
	}

	public static function grants_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_vip_capability_grants';
	}

	public static function sessions_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_vip_capability_sessions';
	}

	public static function exchanges_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_vip_capability_exchanges';
	}

	public static function limits_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_vip_capability_limits';
	}
}
