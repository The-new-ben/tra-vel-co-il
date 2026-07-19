<?php
/**
 * Durable, owner-bound repository for sealed private Trip Cockpit projections.
 *
 * The browser can never write this store. Trusted server code commits a closed
 * source bundle, and the store derives its keyed owner scope, builds the sealed
 * projection, enforces successor ancestry, and preserves immutable revisions.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Customer_Trip_Cockpit_Store implements Tra_Vel_Customer_Trip_Cockpit_Read_Model_Provider {
	const DB_VERSION          = '1.0.0';
	const DB_VERSION_OPTION   = 'tra_vel_customer_trip_cockpit_db_version';
	const CLEANUP_OPTION      = 'tra_vel_customer_trip_cockpit_cleanup_status';
	const READINESS_CACHE_OPTION = 'tra_vel_customer_trip_cockpit_readiness_cache';
	const READINESS_CACHE_TTL_SECONDS = 300;
	const RETENTION_DAYS      = 400;
	const MAX_PROJECTION_BYTES = 524288;
	const MAX_OWNER_CANDIDATES = 50;

	/** @var array|null */
	private static $ready_cache = null;

	public static function maybe_upgrade() {
		$record = self::readiness_record();
		$version_mismatch = self::DB_VERSION !== (string) $record['installed_schema_version'];
		if ( ( $version_mismatch || empty( $record['health']['tables_ready'] ) ) && empty( $record['repair_attempted'] ) ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		self::invalidate_readiness_cache();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset   = $wpdb->get_charset_collate();
		$current   = self::current_table();
		$revisions = self::revisions_table();
		$limits    = self::limits_table();

		dbDelta( "CREATE TABLE {$current} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_user_id bigint(20) unsigned NOT NULL,
			account_ref varchar(112) NOT NULL,
			cockpit_ref varchar(112) NOT NULL,
			trip_ref varchar(112) NOT NULL,
			owner_scope_digest char(64) NOT NULL,
			revision int(10) unsigned NOT NULL,
			previous_projection_digest char(64) DEFAULT NULL,
			projection_digest char(64) NOT NULL,
			projection_json longtext NOT NULL,
			last_verified_at datetime NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			retention_until datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY trip_ref (trip_ref),
			UNIQUE KEY cockpit_ref (cockpit_ref),
			KEY owner_trip (owner_user_id,trip_ref),
			KEY account_trip (account_ref,trip_ref),
			KEY retention (retention_until,id)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$revisions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			cockpit_id bigint(20) unsigned NOT NULL,
			owner_user_id bigint(20) unsigned NOT NULL,
			account_ref varchar(112) NOT NULL,
			cockpit_ref varchar(112) NOT NULL,
			trip_ref varchar(112) NOT NULL,
			revision int(10) unsigned NOT NULL,
			previous_projection_digest char(64) DEFAULT NULL,
			projection_digest char(64) NOT NULL,
			projection_json longtext NOT NULL,
			last_verified_at datetime NOT NULL,
			stored_at datetime NOT NULL,
			retention_until datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY cockpit_revision (cockpit_ref,revision),
			KEY owner_trip_revision (owner_user_id,trip_ref,revision),
			KEY retention (retention_until,id)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$limits} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			limit_key char(64) NOT NULL,
			hits int(10) unsigned NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY limit_key (limit_key),
			KEY expiry (expires_at,id)
		) ENGINE=InnoDB {$charset};" );

		$health = self::inspect_schema();
		if ( ! empty( $health['tables_ready'] ) ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		} else {
			delete_option( self::DB_VERSION_OPTION );
		}
		self::persist_readiness_cache( $health, true );
	}

	public static function schema_health() {
		$record = self::readiness_record();
		return self::decorate_schema_health( $record['health'], (string) $record['installed_schema_version'] );
	}

	public static function is_ready() {
		$record = self::readiness_record();
		return self::DB_VERSION === (string) $record['installed_schema_version'] && ! empty( $record['health']['tables_ready'] );
	}

	/** Delete request-local and durable readiness state before install or after uncertain reads. */
	public static function invalidate_readiness_cache() {
		self::$ready_cache = null;
		delete_option( self::READINESS_CACHE_OPTION );
	}

	/**
	 * Commit a normalized source from a separately authorized server assembler.
	 * No REST controller exposes this method.
	 *
	 * @return array|WP_Error
	 */
	public function commit_server_source( $owner_user_id, $account_ref, $source, $now = null ) {
		global $wpdb;
		if ( ! self::is_ready() ) {
			return self::error( 'store_unavailable', 'Private Trip Cockpit storage is unavailable.', 503 );
		}
		$owner_user_id = (int) $owner_user_id;
		$timestamp     = self::clock( $now );
		if ( $owner_user_id < 1 || ! is_array( $source ) || ! isset( $source['trip_ref'], $source['owner_scope_digest'] ) || ! Tra_Vel_Traveler_Principal::valid_ref( $account_ref, 'account' ) || ! Tra_Vel_Traveler_Principal::valid_ref( $source['trip_ref'], 'trip' ) ) {
			return self::error( 'source_binding_invalid', 'The private Trip Cockpit owner binding is invalid.', 400 );
		}
		$expected_account = Tra_Vel_Traveler_Principal::account_ref( $owner_user_id );
		$expected_scope   = Tra_Vel_Traveler_Principal::cockpit_owner_scope_digest( $owner_user_id, $account_ref, $source['trip_ref'] );
		if ( '' === $expected_scope || ! hash_equals( $expected_account, $account_ref ) || ! is_string( $source['owner_scope_digest'] ) || ! hash_equals( $expected_scope, $source['owner_scope_digest'] ) ) {
			return self::error( 'source_owner_scope_invalid', 'The private Trip Cockpit source is not bound to the authenticated owner.', 403 );
		}
		$authorized = apply_filters( 'tra_vel_customer_trip_cockpit_source_write_authorized', false, $owner_user_id, $account_ref, $source );
		if ( true !== $authorized ) {
			return self::error( 'source_write_not_authorized', 'A trusted server assembler must authorize the Trip Cockpit source.', 403 );
		}
		$projection = Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $source, $timestamp );
		if ( is_wp_error( $projection ) ) {
			return $projection;
		}
		$encoded = self::encode_projection( $projection );
		if ( is_wp_error( $encoded ) ) {
			return $encoded;
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return self::error( 'transaction_failed', 'The Trip Cockpit transaction could not start.', 503 );
		}
		$was_suppressed   = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::current_table() . ' WHERE trip_ref = %s LIMIT 1 FOR UPDATE', $projection['trip_ref'] ),
			ARRAY_A
		);
		$read_error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( '' !== $read_error ) {
			$wpdb->query( 'ROLLBACK' );
			self::invalidate_readiness_cache();
			return self::error( 'commit_read_failed', 'The Trip Cockpit current revision could not be read safely.', 503 );
		}
		$created = ! is_array( $row );
		if ( $created ) {
			if ( 1 !== (int) $projection['revision'] || null !== $projection['previous_projection_digest'] ) {
				$wpdb->query( 'ROLLBACK' );
				return self::error( 'initial_revision_invalid', 'A new Trip Cockpit must begin with revision one.', 409 );
			}
		} else {
			$previous = $this->hydrate_row( $row, $timestamp );
			if ( is_wp_error( $previous ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $previous;
			}
			if ( (int) $row['owner_user_id'] !== $owner_user_id || ! hash_equals( (string) $row['account_ref'], $account_ref ) || ! hash_equals( (string) $row['cockpit_ref'], $projection['cockpit_ref'] ) ) {
				$wpdb->query( 'ROLLBACK' );
				return self::error( 'trip_owner_conflict', 'The Trip Cockpit is already bound to another owner identity.', 409 );
			}
			if ( (int) $row['revision'] === (int) $projection['revision'] && hash_equals( (string) $row['projection_digest'], $projection['projection_digest'] ) ) {
				$wpdb->query( 'ROLLBACK' );
				return array( 'projection' => $previous, 'created' => false, 'replayed' => true );
			}
			$successor = Tra_Vel_Customer_Trip_Cockpit_Policy::assert_successor( $previous, $projection, $timestamp );
			if ( is_wp_error( $successor ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $successor;
			}
		}

		$now_mysql       = gmdate( 'Y-m-d H:i:s', $timestamp );
		$verified_mysql  = self::mysql_from_iso( $projection['last_verified_at'] );
		$retention_until = gmdate( 'Y-m-d H:i:s', $timestamp + self::RETENTION_DAYS * DAY_IN_SECONDS );
		if ( $created ) {
			$written = $wpdb->insert(
				self::current_table(),
				array(
					'owner_user_id' => $owner_user_id,
					'account_ref' => $account_ref,
					'cockpit_ref' => $projection['cockpit_ref'],
					'trip_ref' => $projection['trip_ref'],
					'owner_scope_digest' => $projection['owner_scope_digest'],
					'revision' => $projection['revision'],
					'previous_projection_digest' => null,
					'projection_digest' => $projection['projection_digest'],
					'projection_json' => $encoded,
					'last_verified_at' => $verified_mysql,
					'created_at' => $now_mysql,
					'updated_at' => $now_mysql,
					'retention_until' => $retention_until,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			$cockpit_id = (int) $wpdb->insert_id;
		} else {
			$cockpit_id = (int) $row['id'];
			$written = $wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . self::current_table() . ' SET revision = %d,previous_projection_digest = %s,projection_digest = %s,projection_json = %s,last_verified_at = %s,updated_at = %s,retention_until = %s WHERE id = %d AND revision = %d AND projection_digest = %s',
					$projection['revision'], $projection['previous_projection_digest'], $projection['projection_digest'], $encoded, $verified_mysql, $now_mysql, $retention_until, $cockpit_id, $row['revision'], $row['projection_digest']
				)
			);
		}
		if ( false === $written || $cockpit_id < 1 || ( ! $created && 1 !== (int) $written ) || ! $this->insert_revision( $cockpit_id, $owner_user_id, $account_ref, $projection, $encoded, $now_mysql, $retention_until ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'commit_failed', 'The Trip Cockpit revision could not be committed safely.', 409 );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'commit_uncertain', 'The Trip Cockpit commit could not be confirmed.', 503 );
		}
		return array( 'projection' => $projection, 'created' => $created, 'replayed' => false );
	}

	/** @return array|WP_Error */
	public function get_owned_current_projection( $owner_user_id, $now = null ) {
		global $wpdb;
		$owner_user_id = (int) $owner_user_id;
		if ( $owner_user_id < 1 ) {
			return self::missing();
		}
		if ( ! self::is_ready() ) {
			return self::error( 'store_unavailable', 'Private Trip Cockpit storage is unavailable.', 503 );
		}
		$timestamp        = self::clock( $now );
		$was_suppressed   = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::current_table() . ' WHERE owner_user_id = %d AND retention_until >= %s ORDER BY last_verified_at DESC,updated_at DESC,id DESC LIMIT %d',
				$owner_user_id,
				gmdate( 'Y-m-d H:i:s', $timestamp ),
				self::MAX_OWNER_CANDIDATES + 1
			),
			ARRAY_A
		);
		$read_error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( '' !== $read_error || ! is_array( $rows ) ) {
			self::invalidate_readiness_cache();
			return self::error( 'read_failed', 'The private Trip Cockpit could not be read safely.', 503 );
		}
		if ( count( $rows ) > self::MAX_OWNER_CANDIDATES ) {
			return self::error( 'selection_unavailable', 'The current Trip Cockpit could not be selected safely.', 503 );
		}
		if ( ! $rows ) {
			return self::missing();
		}

		$post_trip = null;
		foreach ( $rows as $row ) {
			$projection = $this->hydrate_row( $row, $timestamp );
			if ( is_wp_error( $projection ) ) {
				return $projection;
			}
			$record = self::provider_record( $row, $projection );
			if ( 'post_trip' !== (string) $projection['current']['phase'] ) {
				return $record;
			} elseif ( 'post_trip' === (string) $projection['current']['phase'] && null === $post_trip ) {
				$post_trip = $record;
			}
		}
		return null !== $post_trip ? $post_trip : self::missing();
	}

	/** @return array|WP_Error */
	public function get_bound_projection( $trip_ref, $case_ref, $account_ref, $now = null ) {
		global $wpdb;
		if ( null !== $case_ref || ! Tra_Vel_Traveler_Principal::valid_ref( $trip_ref, 'trip' ) || ! Tra_Vel_Traveler_Principal::valid_ref( $account_ref, 'account' ) ) {
			return self::missing();
		}
		if ( ! self::is_ready() ) {
			return self::error( 'store_unavailable', 'Private Trip Cockpit storage is unavailable.', 503 );
		}
		$timestamp        = self::clock( $now );
		$was_suppressed   = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::current_table() . ' WHERE trip_ref = %s AND account_ref = %s AND retention_until >= %s LIMIT 1', $trip_ref, $account_ref, gmdate( 'Y-m-d H:i:s', $timestamp ) ),
			ARRAY_A
		);
		$read_error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( '' !== $read_error ) {
			self::invalidate_readiness_cache();
			return self::error( 'read_failed', 'The private Trip Cockpit could not be read safely.', 503 );
		}
		if ( ! is_array( $row ) ) {
			return self::missing();
		}
		$projection = $this->hydrate_row( $row, $timestamp );
		return is_wp_error( $projection ) ? $projection : self::provider_record( $row, $projection );
	}

	/** Atomic fixed-window limiter containing only an HMAC key. */
	public function consume_limit( $limit_key, $limit, $expires_at ) {
		global $wpdb;
		if ( ! self::digest( $limit_key ) || (int) $limit < 1 || (int) $limit > 500 || (int) $expires_at <= time() ) {
			return self::error( 'limit_unavailable', 'The private Trip Cockpit limiter is unavailable.', 503 );
		}
		$now_mysql     = current_time( 'mysql', true );
		$expires_mysql = gmdate( 'Y-m-d H:i:s', (int) $expires_at );
		$sql = $wpdb->prepare(
			'INSERT INTO ' . self::limits_table() . ' (limit_key,hits,expires_at) VALUES (%s,1,%s) ON DUPLICATE KEY UPDATE hits = IF(expires_at <= %s,1,hits+1), expires_at = IF(expires_at <= %s,VALUES(expires_at),expires_at)',
			$limit_key, $expires_mysql, $now_mysql, $now_mysql
		);
		if ( false === $wpdb->query( $sql ) ) {
			return self::error( 'limit_unavailable', 'The private Trip Cockpit limiter is unavailable.', 503 );
		}
		$hits = $wpdb->get_var( $wpdb->prepare( 'SELECT hits FROM ' . self::limits_table() . ' WHERE limit_key = %s LIMIT 1', $limit_key ) );
		return null === $hits ? self::error( 'limit_unavailable', 'The private Trip Cockpit limiter is unavailable.', 503 ) : (int) $hits <= (int) $limit;
	}

	public static function cleanup() {
		global $wpdb;
		$result = array( 'deleted_current' => 0, 'deleted_revisions' => 0, 'deleted_limits' => 0, 'errors' => array() );
		if ( ! self::is_ready() ) {
			$result['errors'][] = 'schema_not_ready';
			update_option( self::CLEANUP_OPTION, $result, false );
			return $result;
		}
		$now = current_time( 'mysql', true );
		foreach ( array(
			'revisions' => 'DELETE FROM ' . self::revisions_table() . ' WHERE retention_until < %s ORDER BY id ASC LIMIT 500',
			'current'   => 'DELETE FROM ' . self::current_table() . ' WHERE retention_until < %s ORDER BY id ASC LIMIT 500',
			'limits'    => 'DELETE FROM ' . self::limits_table() . ' WHERE expires_at < %s ORDER BY id ASC LIMIT 1000',
		) as $kind => $sql ) {
			$deleted = $wpdb->query( $wpdb->prepare( $sql, $now ) );
			if ( false === $deleted ) {
				$result['errors'][] = $kind . '_cleanup_failed';
			} else {
				$result[ 'deleted_' . $kind ] = (int) $deleted;
			}
		}
		update_option( self::CLEANUP_OPTION, array_merge( $result, array( 'last_run_at' => gmdate( 'c' ), 'ok' => ! $result['errors'] ) ), false );
		return $result;
	}

	private function insert_revision( $cockpit_id, $owner_user_id, $account_ref, $projection, $encoded, $stored_at, $retention_until ) {
		global $wpdb;
		return false !== $wpdb->insert(
			self::revisions_table(),
			array(
				'cockpit_id' => $cockpit_id, 'owner_user_id' => $owner_user_id, 'account_ref' => $account_ref,
				'cockpit_ref' => $projection['cockpit_ref'], 'trip_ref' => $projection['trip_ref'], 'revision' => $projection['revision'],
				'previous_projection_digest' => $projection['previous_projection_digest'], 'projection_digest' => $projection['projection_digest'],
				'projection_json' => $encoded, 'last_verified_at' => self::mysql_from_iso( $projection['last_verified_at'] ),
				'stored_at' => $stored_at, 'retention_until' => $retention_until,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/** Revalidate every duplicated binding and the complete projection seal. */
	private function hydrate_row( $row, $now ) {
		if ( ! is_array( $row ) || ! isset( $row['projection_json'] ) || ! is_string( $row['projection_json'] ) || strlen( $row['projection_json'] ) > self::MAX_PROJECTION_BYTES ) {
			return self::corrupt();
		}
		$projection = json_decode( $row['projection_json'], true );
		if ( ! is_array( $projection ) ) {
			return self::corrupt();
		}
		$validated = Tra_Vel_Customer_Trip_Cockpit_Policy::validate_projection( $projection, $now );
		if ( is_wp_error( $validated ) ) {
			return self::corrupt();
		}
		$expected_scope = Tra_Vel_Traveler_Principal::cockpit_owner_scope_digest( (int) $row['owner_user_id'], (string) $row['account_ref'], (string) $row['trip_ref'] );
		$previous = empty( $row['previous_projection_digest'] ) ? null : (string) $row['previous_projection_digest'];
		if (
			'' === $expected_scope ||
			! hash_equals( $expected_scope, (string) $row['owner_scope_digest'] ) ||
			! hash_equals( (string) $row['owner_scope_digest'], $validated['owner_scope_digest'] ) ||
			! hash_equals( (string) $row['account_ref'], Tra_Vel_Traveler_Principal::account_ref( (int) $row['owner_user_id'] ) ) ||
			! hash_equals( (string) $row['trip_ref'], $validated['trip_ref'] ) ||
			! hash_equals( (string) $row['cockpit_ref'], $validated['cockpit_ref'] ) ||
			! hash_equals( (string) $row['projection_digest'], $validated['projection_digest'] ) ||
			(int) $row['revision'] !== (int) $validated['revision'] ||
			$previous !== $validated['previous_projection_digest'] ||
			self::iso_from_mysql( $row['last_verified_at'] ) !== $validated['last_verified_at']
		) {
			return self::corrupt();
		}
		return $validated;
	}

	private static function provider_record( $row, $projection ) {
		return array(
			'projection' => $projection,
			'owner_user_id' => (int) $row['owner_user_id'],
			'account_ref' => (string) $row['account_ref'],
			'trip_ref' => (string) $row['trip_ref'],
			'case_ref' => null,
			'owner_scope_digest' => (string) $row['owner_scope_digest'],
		);
	}

	private static function encode_projection( $projection ) {
		$encoded = wp_json_encode( $projection, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_PROJECTION_BYTES
			? self::error( 'projection_too_large', 'The private Trip Cockpit projection is too large.', 413 )
			: $encoded;
	}

	/** Read a strictly bounded persistent cache before running expensive SHOW inspections. */
	private static function readiness_record() {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );
		$now       = time();
		if ( self::readiness_cache_valid( self::$ready_cache, $installed, $now ) ) {
			return self::$ready_cache;
		}
		$cached = get_option( self::READINESS_CACHE_OPTION, null );
		if ( self::readiness_cache_valid( $cached, $installed, $now ) ) {
			self::$ready_cache = $cached;
			return $cached;
		}
		self::persist_readiness_cache( self::inspect_schema(), false );
		return self::$ready_cache;
	}

	private static function persist_readiness_cache( $health, $repair_attempted ) {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );
		$now       = time();
		$record    = array(
			'cache_version'            => '1.0.0',
			'schema_version'           => self::DB_VERSION,
			'installed_schema_version' => $installed,
			'checked_at'               => $now,
			'expires_at'               => $now + self::READINESS_CACHE_TTL_SECONDS,
			'repair_attempted'          => (bool) $repair_attempted,
			'health'                    => $health,
		);
		self::$ready_cache = $record;
		update_option( self::READINESS_CACHE_OPTION, $record, false );
	}

	private static function readiness_cache_valid( $record, $installed, $now ) {
		$keys = array( 'cache_version', 'schema_version', 'installed_schema_version', 'checked_at', 'expires_at', 'repair_attempted', 'health' );
		if ( ! is_array( $record ) || array_values( array_keys( $record ) ) !== $keys || '1.0.0' !== (string) $record['cache_version'] || self::DB_VERSION !== (string) $record['schema_version'] || (string) $record['installed_schema_version'] !== (string) $installed || ! is_int( $record['checked_at'] ) || ! is_int( $record['expires_at'] ) || ! is_bool( $record['repair_attempted'] ) || $record['checked_at'] > $now + 5 || $record['expires_at'] <= $now || $record['expires_at'] - $record['checked_at'] > self::READINESS_CACHE_TTL_SECONDS ) {
			return false;
		}
		$health_keys = array( 'expected_tables', 'ready_tables', 'transactional_tables', 'required_indexes', 'ready_indexes', 'required_indexes_ready', 'inspection_errors', 'tables_ready' );
		$health      = $record['health'];
		if ( ! is_array( $health ) || array_values( array_keys( $health ) ) !== $health_keys ) {
			return false;
		}
		foreach ( array( 'expected_tables', 'ready_tables', 'transactional_tables', 'required_indexes', 'ready_indexes' ) as $key ) {
			if ( ! is_int( $health[ $key ] ) || $health[ $key ] < 0 ) {
				return false;
			}
		}
		if ( ! is_bool( $health['required_indexes_ready'] ) || ! is_bool( $health['tables_ready'] ) || ! is_array( $health['inspection_errors'] ) ) {
			return false;
		}
		foreach ( $health['inspection_errors'] as $table ) {
			if ( ! is_string( $table ) || ! in_array( $table, array( self::current_table(), self::revisions_table(), self::limits_table() ), true ) ) {
				return false;
			}
		}
		if ( count( $health['inspection_errors'] ) !== count( array_unique( $health['inspection_errors'] ) ) ) {
			return false;
		}
		$indexes_ready = 13 === $health['ready_indexes'];
		$tables_ready  = 3 === $health['ready_tables'] && 3 === $health['transactional_tables'] && $indexes_ready && ! $health['inspection_errors'];
		return 3 === $health['expected_tables']
			&& $health['ready_tables'] <= 3
			&& $health['transactional_tables'] <= 3
			&& 13 === $health['required_indexes']
			&& $health['ready_indexes'] <= 13
			&& $indexes_ready === $health['required_indexes_ready']
			&& $tables_ready === $health['tables_ready'];
	}

	private static function decorate_schema_health( $health, $installed ) {
		return array_merge(
			array(
				'schema_version'           => self::DB_VERSION,
				'installed_schema_version' => '' === $installed ? null : $installed,
				'retention_days'            => self::RETENTION_DAYS,
				'max_projection_bytes'      => self::MAX_PROJECTION_BYTES,
				'max_owner_candidates'      => self::MAX_OWNER_CANDIDATES,
			),
			$health
		);
	}

	private static function inspect_schema() {
		global $wpdb;
		$requirements = array(
			self::current_table() => array(
				'columns' => array( 'id','owner_user_id','account_ref','cockpit_ref','trip_ref','owner_scope_digest','revision','previous_projection_digest','projection_digest','projection_json','last_verified_at','created_at','updated_at','retention_until' ),
				'indexes' => array( 'PRIMARY' => array( true, array( 'id' ) ), 'trip_ref' => array( true, array( 'trip_ref' ) ), 'cockpit_ref' => array( true, array( 'cockpit_ref' ) ), 'owner_trip' => array( false, array( 'owner_user_id','trip_ref' ) ), 'account_trip' => array( false, array( 'account_ref','trip_ref' ) ), 'retention' => array( false, array( 'retention_until','id' ) ) ),
			),
			self::revisions_table() => array(
				'columns' => array( 'id','cockpit_id','owner_user_id','account_ref','cockpit_ref','trip_ref','revision','previous_projection_digest','projection_digest','projection_json','last_verified_at','stored_at','retention_until' ),
				'indexes' => array( 'PRIMARY' => array( true, array( 'id' ) ), 'cockpit_revision' => array( true, array( 'cockpit_ref','revision' ) ), 'owner_trip_revision' => array( false, array( 'owner_user_id','trip_ref','revision' ) ), 'retention' => array( false, array( 'retention_until','id' ) ) ),
			),
			self::limits_table() => array(
				'columns' => array( 'id','limit_key','hits','expires_at' ),
				'indexes' => array( 'PRIMARY' => array( true, array( 'id' ) ), 'limit_key' => array( true, array( 'limit_key' ) ), 'expiry' => array( false, array( 'expires_at','id' ) ) ),
			),
		);
		$ready = 0; $transactional = 0; $ready_indexes = 0; $required_indexes = 0; $errors = array();
		$was_suppressed = $wpdb->suppress_errors();
		foreach ( $requirements as $table => $requirement ) {
			$identifier = str_replace( '`', '``', (string) $table );
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$identifier}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$indexes = $wpdb->get_results( "SHOW INDEX FROM `{$identifier}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$status  = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
			$engine_ok = is_array( $status ) && 'innodb' === strtolower( (string) ( $status['Engine'] ?? '' ) );
			if ( $engine_ok ) { $transactional++; }
			$columns_ok = is_array( $columns ) && ! array_diff( $requirement['columns'], $columns );
			$present_indexes = array();
			foreach ( is_array( $indexes ) ? $indexes : array() as $index ) {
				$name = (string) ( $index['Key_name'] ?? '' );
				$sequence = (int) ( $index['Seq_in_index'] ?? 0 );
				if ( '' === $name || $sequence < 1 ) { continue; }
				if ( ! isset( $present_indexes[ $name ] ) ) { $present_indexes[ $name ] = array( 'unique' => 0 === (int) ( $index['Non_unique'] ?? 1 ), 'columns' => array() ); }
				$present_indexes[ $name ]['columns'][ $sequence - 1 ] = (string) ( $index['Column_name'] ?? '' );
			}
			foreach ( $present_indexes as &$present_index ) { ksort( $present_index['columns'] ); $present_index['columns'] = array_values( $present_index['columns'] ); }
			unset( $present_index );
			$required_indexes += count( $requirement['indexes'] );
			$table_indexes_ready = true;
			foreach ( $requirement['indexes'] as $name => $expected ) {
				$matches = isset( $present_indexes[ $name ] ) && $present_indexes[ $name ]['unique'] === $expected[0] && $present_indexes[ $name ]['columns'] === $expected[1];
				if ( $matches ) { $ready_indexes++; } else { $table_indexes_ready = false; }
			}
			if ( $columns_ok && $engine_ok && $table_indexes_ready ) { $ready++; } else { $errors[] = $table; }
		}
		$wpdb->suppress_errors( $was_suppressed );
		return array(
			'expected_tables' => count( $requirements ), 'ready_tables' => $ready, 'transactional_tables' => $transactional,
			'required_indexes' => $required_indexes, 'ready_indexes' => $ready_indexes,
			'required_indexes_ready' => $ready_indexes === $required_indexes,
			'inspection_errors' => $errors,
			'tables_ready' => $ready === count( $requirements ) && $transactional === count( $requirements ) && $ready_indexes === $required_indexes,
		);
	}

	private static function current_table() { global $wpdb; return $wpdb->prefix . 'tra_vel_customer_trip_cockpits'; }
	private static function revisions_table() { global $wpdb; return $wpdb->prefix . 'tra_vel_customer_trip_cockpit_revisions'; }
	private static function limits_table() { global $wpdb; return $wpdb->prefix . 'tra_vel_customer_trip_cockpit_limits'; }
	private static function clock( $now ) { $value = null === $now ? time() : (int) $now; return $value > 0 && $value <= time() + 5 ? $value : time(); }
	private static function mysql_from_iso( $value ) { return gmdate( 'Y-m-d H:i:s', strtotime( (string) $value ) ); }
	private static function iso_from_mysql( $value ) { return gmdate( 'Y-m-d\TH:i:s\Z', strtotime( (string) $value . ' UTC' ) ); }
	private static function digest( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value ); }
	private static function missing() { return self::error( 'not_found', 'The requested Trip Cockpit is unavailable.', 404 ); }
	private static function corrupt() { return self::error( 'integrity_unavailable', 'The private Trip Cockpit could not be verified.', 503 ); }
	private static function error( $code, $message, $status ) { return new WP_Error( 'tra_vel_customer_trip_cockpit_' . $code, $message, array( 'status' => (int) $status ) ); }
}
