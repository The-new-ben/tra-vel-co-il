<?php
/**
 * Durable, owner-scoped store for progressive traveler registration.
 *
 * The four tables contain only closed registration projections, immutable
 * revisions/transitions, opaque references, and request/evidence digests.
 * They never contain raw identity, contact, document, payment, medical, or
 * provider payload data, and they cannot execute a commercial side effect.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Traveler_Registration_Store {
	const DB_VERSION            = '1.0.0';
	const DB_VERSION_OPTION     = 'tra_vel_traveler_registration_db_version';
	const CLEANUP_STATUS_OPTION = 'tra_vel_traveler_registration_cleanup_status';
	const RETENTION_DAYS        = 400;
	const IDEMPOTENCY_DAYS      = 7;
	const MAX_AGGREGATE_BYTES   = 65536;

	/** @var bool|null */
	private static $ready_cache = null;

	public static function maybe_upgrade() {
		if ( self::DB_VERSION !== (string) get_option( self::DB_VERSION_OPTION, '' ) ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset     = $wpdb->get_charset_collate();
		$current     = self::registrations_table();
		$revisions   = self::revisions_table();
		$transitions = self::transitions_table();
		$idempotency = self::idempotency_table();

		dbDelta( "CREATE TABLE {$current} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_user_id bigint(20) unsigned NOT NULL,
			registration_ref varchar(128) NOT NULL,
			trip_ref varchar(112) NOT NULL,
			account_ref varchar(112) NOT NULL,
			gate varchar(32) NOT NULL,
			version int(10) unsigned NOT NULL,
			role_manifest_ref varchar(112) NOT NULL,
			aggregate_digest char(64) NOT NULL,
			aggregate longtext NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			retention_until datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY registration_ref (registration_ref),
			UNIQUE KEY owner_trip (owner_user_id,trip_ref),
			KEY retention (retention_until,id)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$revisions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			registration_id bigint(20) unsigned NOT NULL,
			registration_ref varchar(128) NOT NULL,
			version int(10) unsigned NOT NULL,
			aggregate_digest char(64) NOT NULL,
			aggregate longtext NOT NULL,
			created_at datetime NOT NULL,
			retention_until datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY registration_version (registration_id,version),
			KEY registration_ref (registration_ref,version),
			KEY retention (retention_until,id)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$transitions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			registration_id bigint(20) unsigned NOT NULL,
			transition_ref varchar(128) NOT NULL,
			from_version int(10) unsigned NOT NULL,
			to_version int(10) unsigned NOT NULL,
			transition_digest char(64) NOT NULL,
			transition_event longtext NOT NULL,
			occurred_at datetime NOT NULL,
			retention_until datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY transition_ref (transition_ref),
			UNIQUE KEY registration_transition (registration_id,to_version),
			KEY retention (retention_until,id)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$idempotency} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_user_id bigint(20) unsigned NOT NULL,
			operation_scope varchar(16) NOT NULL,
			idempotency_key_hash char(64) NOT NULL,
			request_digest char(64) NOT NULL,
			registration_ref varchar(128) NOT NULL,
			response_version int(10) unsigned NOT NULL,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY owner_operation_key (owner_user_id,operation_scope,idempotency_key_hash),
			KEY registration_response (registration_ref,response_version),
			KEY expiry (expires_at,id)
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
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );
		$health    = self::inspect_schema();
		self::$ready_cache = self::DB_VERSION === $installed && ! empty( $health['tables_ready'] );
		return array_merge(
			array(
				'schema_version'           => self::DB_VERSION,
				'installed_schema_version' => '' === $installed ? null : $installed,
				'retention_days'            => self::RETENTION_DAYS,
				'idempotency_days'          => self::IDEMPOTENCY_DAYS,
				'max_aggregate_bytes'       => self::MAX_AGGREGATE_BYTES,
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
	 * Create the first discover-gate revision for one signed-in owner/trip.
	 *
	 * @return array|WP_Error
	 */
	public function create_registration( $owner_user_id, $input ) {
		global $wpdb;
		if ( ! self::is_ready() ) {
			return self::error( 'schema_unavailable', 'Traveler registration storage is unavailable.', 503 );
		}
		$owner_user_id = (int) $owner_user_id;
		$input         = Tra_Vel_Traveler_Registration_Schema::create_input( $input );
		if ( $owner_user_id < 1 || is_wp_error( $input ) ) {
			return is_wp_error( $input ) ? $input : self::error( 'owner_required', 'A signed-in account owner is required.', 401 );
		}
		$request_digest = Tra_Vel_Traveler_Registration_Schema::canonical_digest( $input );
		$replay = $this->idempotent_result( $owner_user_id, 'create', $input['idempotency_key'], $request_digest );
		if ( null !== $replay ) {
			return $replay;
		}
		$evidence_allowed = $this->verified_evidence_allowed( $owner_user_id, '', $input );
		if ( true !== $evidence_allowed ) {
			return $evidence_allowed;
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return self::error( 'transaction_failed', 'The registration transaction could not be started.', 500 );
		}
		$locked_replay = $this->idempotent_result( $owner_user_id, 'create', $input['idempotency_key'], $request_digest, true );
		if ( null !== $locked_replay ) {
			$wpdb->query( 'ROLLBACK' );
			return $locked_replay;
		}
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT registration_ref FROM ' . self::registrations_table() . ' WHERE owner_user_id = %d AND trip_ref = %s LIMIT 1 FOR UPDATE',
				$owner_user_id,
				$input['trip_ref']
			)
		);
		if ( is_string( $existing ) && '' !== $existing ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'trip_already_registered', 'This account already has a registration for the opaque trip reference.', 409 );
		}

		$now              = self::utc_now();
		$registration_ref = self::random_ref( 'registration' );
		$registration     = array(
			'contract_version' => Tra_Vel_VIP_Taxonomy::CONTRACT_VERSION,
			'registration_ref' => $registration_ref,
			'trip_ref'         => $input['trip_ref'],
			'account_ref'      => self::owner_ref( 'account', $owner_user_id ),
			'gate'             => 'discover',
			'party_flags'      => $input['party_flags'],
			'requirements'     => $input['requirements'],
			'role_manifest_ref'=> $input['role_manifest_ref'],
			'version'          => 1,
			'created_at'       => $now,
			'updated_at'       => $now,
			'data_boundary'    => self::registration_boundary(),
		);
		$aggregate = Tra_Vel_Traveler_Registration_Schema::aggregate( $registration, $input['profile_refs'], $input['vault_item_refs'], $now );
		if ( is_wp_error( $aggregate ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $aggregate;
		}
		$encoded = wp_json_encode( $aggregate, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_AGGREGATE_BYTES ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'aggregate_too_large', 'The privacy-minimized registration aggregate is too large.', 413 );
		}
		$digest          = Tra_Vel_Traveler_Registration_Schema::canonical_digest( $aggregate );
		$now_mysql       = self::mysql_from_iso( $now );
		$retention_until = gmdate( 'Y-m-d H:i:s', strtotime( $now ) + self::RETENTION_DAYS * DAY_IN_SECONDS );
		$inserted = $wpdb->insert(
			self::registrations_table(),
			array(
				'owner_user_id'    => $owner_user_id,
				'registration_ref' => $registration_ref,
				'trip_ref'         => $registration['trip_ref'],
				'account_ref'      => $registration['account_ref'],
				'gate'             => $registration['gate'],
				'version'          => 1,
				'role_manifest_ref'=> $registration['role_manifest_ref'],
				'aggregate_digest' => $digest,
				'aggregate'        => $encoded,
				'created_at'       => $now_mysql,
				'updated_at'       => $now_mysql,
				'retention_until'  => $retention_until,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$registration_id = (int) $wpdb->insert_id;
		if ( false === $inserted || $registration_id < 1 || ! $this->insert_revision( $registration_id, $aggregate, $digest, $retention_until ) || ! $this->insert_idempotency( $owner_user_id, 'create', $input['idempotency_key'], $request_digest, $registration_ref, 1 ) ) {
			$wpdb->query( 'ROLLBACK' );
			$recovered = $this->idempotent_result( $owner_user_id, 'create', $input['idempotency_key'], $request_digest );
			return null !== $recovered ? $recovered : self::error( 'store_failed', 'The registration could not be committed safely.', 409 );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'commit_uncertain', 'The registration commit could not be confirmed.', 500 );
		}
		return array( 'aggregate' => $aggregate, 'transition' => null, 'transition_count' => 0, 'created' => true, 'replayed' => false );
	}

	/**
	 * Append one exact successor and atomically replace the current projection.
	 * Exact idempotency replay resolves before optimistic-version validation.
	 *
	 * @return array|WP_Error
	 */
	public function update_registration( $owner_user_id, $registration_ref, $input ) {
		global $wpdb;
		if ( ! self::is_ready() ) {
			return self::error( 'schema_unavailable', 'Traveler registration storage is unavailable.', 503 );
		}
		$owner_user_id  = (int) $owner_user_id;
		$input          = Tra_Vel_Traveler_Registration_Schema::update_input( $input );
		if ( $owner_user_id < 1 || ! self::ref( $registration_ref, 'registration' ) || is_wp_error( $input ) ) {
			return is_wp_error( $input ) ? $input : self::error( 'request_invalid', 'The owned registration update request is invalid.', 400 );
		}
		$request_digest = Tra_Vel_Traveler_Registration_Schema::canonical_digest( array( 'registration_ref' => $registration_ref, 'input' => $input ) );
		$replay = $this->idempotent_result( $owner_user_id, 'update', $input['idempotency_key'], $request_digest );
		if ( null !== $replay ) {
			return $replay;
		}
		$evidence_allowed = $this->verified_evidence_allowed( $owner_user_id, $registration_ref, $input );
		if ( true !== $evidence_allowed ) {
			return $evidence_allowed;
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return self::error( 'transaction_failed', 'The registration transaction could not be started.', 500 );
		}
		$locked_replay = $this->idempotent_result( $owner_user_id, 'update', $input['idempotency_key'], $request_digest, true );
		if ( null !== $locked_replay ) {
			$wpdb->query( 'ROLLBACK' );
			return $locked_replay;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::registrations_table() . ' WHERE owner_user_id = %d AND registration_ref = %s AND retention_until >= %s LIMIT 1 FOR UPDATE',
				$owner_user_id,
				$registration_ref,
				current_time( 'mysql', true )
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'not_found', 'Traveler registration not found.', 404 );
		}
		$current = $this->hydrate_aggregate( $row );
		if ( is_wp_error( $current ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $current;
		}
		$previous = $current['registration'];
		if ( (int) $input['expected_version'] !== (int) $previous['version'] ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'version_conflict', 'The registration changed before this update. Reload the latest revision.', 409 );
		}
		$now = self::utc_now();
		if ( $now <= $previous['updated_at'] ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'clock_conflict', 'A successor requires a later evidence clock. Retry the request.', 409 );
		}
		$next = $previous;
		$next['gate']              = $input['gate'];
		$next['party_flags']       = $input['party_flags'];
		$next['requirements']      = $input['requirements'];
		$next['role_manifest_ref'] = $input['role_manifest_ref'];
		$next['version']           = $previous['version'] + 1;
		$next['updated_at']        = $now;
		$next_aggregate = Tra_Vel_Traveler_Registration_Schema::aggregate( $next, $input['profile_refs'], $input['vault_item_refs'], $now );
		if ( is_wp_error( $next_aggregate ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $next_aggregate;
		}
		$deltas = self::requirement_deltas( $previous['requirements'], $next['requirements'] );
		if ( ! $deltas['changed'] ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'transition_no_change', 'A successor must change at least one readiness requirement.', 409 );
		}
		$next_digest = Tra_Vel_Traveler_Registration_Schema::canonical_digest( $next_aggregate );
		$transition  = array(
			'contract_version'       => Tra_Vel_VIP_Taxonomy::CONTRACT_VERSION,
			'transition_ref'         => self::random_ref( 'registration_transition' ),
			'registration_ref'       => $registration_ref,
			'trip_ref'               => $previous['trip_ref'],
			'from_version'           => $previous['version'],
			'to_version'             => $next['version'],
			'from_gate'              => $previous['gate'],
			'to_gate'                => $next['gate'],
			'reason'                 => $input['reason'],
			'changed_requirements'   => $deltas['changed'],
			'invalidated_requirements'=> $deltas['invalidated'],
			'actor_ref'              => self::owner_ref( 'principal', $owner_user_id ),
			'authority_digest'        => self::authority_digest( $owner_user_id, $registration_ref, $request_digest, $next['version'] ),
			'evidence_digest'         => Tra_Vel_Traveler_Registration_Schema::canonical_digest( array( 'request_digest' => $request_digest, 'previous_aggregate_digest' => (string) $row['aggregate_digest'], 'next_aggregate_digest' => $next_digest ) ),
			'occurred_at'             => $now,
			'authorization_effect'    => 'registration_only',
			'data_boundary'           => self::registration_boundary(),
		);
		$successor = Tra_Vel_VIP_Policy::registration_successor( $previous, $next, $transition, $now );
		if ( is_wp_error( $successor ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $successor;
		}

		$encoded          = wp_json_encode( $next_aggregate, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$transition_json  = wp_json_encode( $transition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_AGGREGATE_BYTES || ! is_string( $transition_json ) || strlen( $transition_json ) > self::MAX_AGGREGATE_BYTES ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'aggregate_too_large', 'The privacy-minimized registration successor is too large.', 413 );
		}
		$now_mysql       = self::mysql_from_iso( $now );
		$retention_until = gmdate( 'Y-m-d H:i:s', strtotime( $now ) + self::RETENTION_DAYS * DAY_IN_SECONDS );
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::registrations_table() . ' SET gate = %s, version = %d, role_manifest_ref = %s, aggregate_digest = %s, aggregate = %s, updated_at = %s, retention_until = %s WHERE id = %d AND owner_user_id = %d AND version = %d',
				$next['gate'],
				$next['version'],
				$next['role_manifest_ref'],
				$next_digest,
				$encoded,
				$now_mysql,
				$retention_until,
				(int) $row['id'],
				$owner_user_id,
				$previous['version']
			)
		);
		$transition_digest = Tra_Vel_Traveler_Registration_Schema::canonical_digest( $transition );
		$stored_transition = 1 === $updated && false !== $wpdb->insert(
			self::transitions_table(),
			array(
				'registration_id'  => (int) $row['id'],
				'transition_ref'   => $transition['transition_ref'],
				'from_version'     => $transition['from_version'],
				'to_version'       => $transition['to_version'],
				'transition_digest'=> $transition_digest,
				'transition_event' => $transition_json,
				'occurred_at'      => $now_mysql,
				'retention_until'  => $retention_until,
			),
			array( '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		if ( ! $stored_transition || ! $this->insert_revision( (int) $row['id'], $next_aggregate, $next_digest, $retention_until ) || ! $this->insert_idempotency( $owner_user_id, 'update', $input['idempotency_key'], $request_digest, $registration_ref, $next['version'] ) ) {
			$wpdb->query( 'ROLLBACK' );
			$recovered = $this->idempotent_result( $owner_user_id, 'update', $input['idempotency_key'], $request_digest );
			return null !== $recovered ? $recovered : self::error( 'store_failed', 'The registration successor could not be committed safely.', 409 );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'commit_uncertain', 'The registration successor commit could not be confirmed.', 500 );
		}
		$count = $this->transition_count( (int) $row['id'] );
		return array( 'aggregate' => $next_aggregate, 'transition' => $transition, 'transition_count' => $count, 'created' => false, 'replayed' => false );
	}

	/**
	 * Owner-constrained read. The database predicate applies ownership before
	 * any aggregate is returned, preventing cross-user reference probing.
	 *
	 * @return array|WP_Error|null
	 */
	public function get_owned_registration( $owner_user_id, $registration_ref ) {
		global $wpdb;
		if ( ! self::is_ready() ) {
			return self::error( 'schema_unavailable', 'Traveler registration storage is unavailable.', 503 );
		}
		if ( (int) $owner_user_id < 1 || ! self::ref( $registration_ref, 'registration' ) ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::registrations_table() . ' WHERE owner_user_id = %d AND registration_ref = %s AND retention_until >= %s LIMIT 1',
				(int) $owner_user_id,
				$registration_ref,
				current_time( 'mysql', true )
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		$aggregate = $this->hydrate_aggregate( $row );
		if ( is_wp_error( $aggregate ) ) {
			return $aggregate;
		}
		return array( 'aggregate' => $aggregate, 'transition_count' => $this->transition_count( (int) $row['id'] ) );
	}

	public static function cleanup() {
		global $wpdb;
		$result = array( 'deleted_registrations' => 0, 'deleted_revisions' => 0, 'deleted_transitions' => 0, 'deleted_idempotency' => 0, 'errors' => array() );
		if ( ! self::is_ready() ) {
			$result['errors'][] = 'schema_not_ready';
			self::record_cleanup_status( $result );
			return $result;
		}
		$now = current_time( 'mysql', true );
		$deleted_idempotency = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::idempotency_table() . ' WHERE expires_at < %s ORDER BY id ASC LIMIT 1000', $now ) );
		if ( false === $deleted_idempotency ) {
			$result['errors'][] = 'idempotency_cleanup_failed';
		} else {
			$result['deleted_idempotency'] = (int) $deleted_idempotency;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, registration_ref FROM ' . self::registrations_table() . ' WHERE retention_until < %s ORDER BY id ASC LIMIT 100', $now ), ARRAY_A );
		if ( ! $rows ) {
			self::record_cleanup_status( $result );
			return $result;
		}
		$ids  = array_map( 'absint', array_column( $rows, 'id' ) );
		$refs = array_values( array_filter( array_column( $rows, 'registration_ref' ), 'is_string' ) );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			$result['errors'][] = 'transaction_failed';
			self::record_cleanup_status( $result );
			return $result;
		}
		$id_placeholders  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$ref_placeholders = implode( ',', array_fill( 0, count( $refs ), '%s' ) );
		$deleted_transitions = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::transitions_table() . " WHERE registration_id IN ({$id_placeholders})", $ids ) );
		$deleted_revisions   = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::revisions_table() . " WHERE registration_id IN ({$id_placeholders})", $ids ) );
		$deleted_bound_keys  = $refs ? $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::idempotency_table() . " WHERE registration_ref IN ({$ref_placeholders})", $refs ) ) : 0;
		$deleted_registrations = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::registrations_table() . " WHERE id IN ({$id_placeholders}) AND retention_until < %s", array_merge( $ids, array( $now ) ) ) );
		if ( false === $deleted_transitions || false === $deleted_revisions || false === $deleted_bound_keys || false === $deleted_registrations || count( $ids ) !== (int) $deleted_registrations || false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$result['errors'][] = 'aggregate_cleanup_failed';
			self::record_cleanup_status( $result );
			return $result;
		}
		$result['deleted_transitions']  = (int) $deleted_transitions;
		$result['deleted_revisions']    = (int) $deleted_revisions;
		$result['deleted_idempotency'] += (int) $deleted_bound_keys;
		$result['deleted_registrations']= (int) $deleted_registrations;
		self::record_cleanup_status( $result );
		return $result;
	}

	private function idempotent_result( $owner_user_id, $scope, $idempotency_key, $request_digest, $for_update = false ) {
		global $wpdb;
		$key_hash = self::idempotency_hash( $idempotency_key );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::idempotency_table() . ' WHERE owner_user_id = %d AND operation_scope = %s AND idempotency_key_hash = %s AND expires_at >= %s LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ),
				(int) $owner_user_id,
				$scope,
				$key_hash,
				current_time( 'mysql', true )
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		if ( ! hash_equals( (string) $row['request_digest'], $request_digest ) ) {
			return self::error( 'idempotency_conflict', 'This retry key is already bound to a different registration request.', 409 );
		}
		$revision = $this->revision_owned( $owner_user_id, (string) $row['registration_ref'], (int) $row['response_version'], $for_update );
		if ( is_wp_error( $revision ) ) {
			return $revision;
		}
		if ( ! is_array( $revision ) ) {
			return self::error( 'idempotency_replay_missing', 'The immutable response revision for this retry is unavailable.', 500 );
		}
		$transition = (int) $row['response_version'] > 1 ? $this->transition_owned( $owner_user_id, (string) $row['registration_ref'], (int) $row['response_version'], $for_update ) : null;
		if ( (int) $row['response_version'] > 1 && ! is_array( $transition ) ) {
			return self::error( 'idempotency_transition_missing', 'The immutable transition for this retry is unavailable.', 500 );
		}
		return array(
			'aggregate'       => $revision['aggregate'],
			'transition'      => $transition,
			// One immutable transition exists for every successor after revision 1.
			// Using the replayed response revision preserves byte-stable semantics
			// even if the current aggregate has advanced since the original call.
			'transition_count'=> max( 0, (int) $row['response_version'] - 1 ),
			'created'         => false,
			'replayed'        => true,
		);
	}

	private function revision_owned( $owner_user_id, $registration_ref, $version, $for_update ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT r.*, c.owner_user_id FROM ' . self::revisions_table() . ' r INNER JOIN ' . self::registrations_table() . ' c ON c.id = r.registration_id WHERE c.owner_user_id = %d AND c.registration_ref = %s AND r.version = %d LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ),
				(int) $owner_user_id,
				$registration_ref,
				(int) $version
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		$aggregate = $this->hydrate_aggregate( $row );
		return is_wp_error( $aggregate ) ? $aggregate : array( 'aggregate' => $aggregate, 'registration_id' => (int) $row['registration_id'] );
	}

	private function transition_owned( $owner_user_id, $registration_ref, $to_version, $for_update ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT t.* FROM ' . self::transitions_table() . ' t INNER JOIN ' . self::registrations_table() . ' c ON c.id = t.registration_id WHERE c.owner_user_id = %d AND c.registration_ref = %s AND t.to_version = %d LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ),
				(int) $owner_user_id,
				$registration_ref,
				(int) $to_version
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		$transition = json_decode( (string) $row['transition_event'], true );
		$digest     = is_array( $transition ) ? Tra_Vel_Traveler_Registration_Schema::canonical_digest( $transition ) : '';
		return $digest && hash_equals( (string) $row['transition_digest'], $digest ) ? $transition : null;
	}

	private function hydrate_aggregate( $row ) {
		$aggregate = isset( $row['aggregate'] ) ? json_decode( (string) $row['aggregate'], true ) : null;
		$digest    = is_array( $aggregate ) ? Tra_Vel_Traveler_Registration_Schema::canonical_digest( $aggregate ) : '';
		if ( ! $digest || ! isset( $row['aggregate_digest'] ) || ! hash_equals( (string) $row['aggregate_digest'], $digest ) ) {
			return self::error( 'aggregate_integrity_failed', 'The durable registration aggregate failed its integrity check.', 500 );
		}
		$aggregate = Tra_Vel_Traveler_Registration_Schema::validate_aggregate( $aggregate, self::utc_now() );
		if ( is_wp_error( $aggregate ) ) {
			return $aggregate;
		}
		$registration = $aggregate['registration'];
		if ( isset( $row['registration_ref'] ) && $registration['registration_ref'] !== (string) $row['registration_ref'] || isset( $row['trip_ref'] ) && $registration['trip_ref'] !== (string) $row['trip_ref'] || isset( $row['account_ref'] ) && $registration['account_ref'] !== (string) $row['account_ref'] || isset( $row['version'] ) && (int) $registration['version'] !== (int) $row['version'] ) {
			return self::error( 'aggregate_binding_failed', 'The durable registration row no longer matches its immutable projection.', 500 );
		}
		return $aggregate;
	}

	private function insert_revision( $registration_id, $aggregate, $digest, $retention_until ) {
		global $wpdb;
		$encoded      = wp_json_encode( $aggregate, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$registration = $aggregate['registration'];
		return false !== $wpdb->insert(
			self::revisions_table(),
			array(
				'registration_id'  => (int) $registration_id,
				'registration_ref' => $registration['registration_ref'],
				'version'          => $registration['version'],
				'aggregate_digest' => $digest,
				'aggregate'        => $encoded,
				'created_at'       => self::mysql_from_iso( $registration['updated_at'] ),
				'retention_until'  => $retention_until,
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	private function insert_idempotency( $owner_user_id, $scope, $key, $request_digest, $registration_ref, $response_version ) {
		global $wpdb;
		$now = time();
		return false !== $wpdb->insert(
			self::idempotency_table(),
			array(
				'owner_user_id'        => (int) $owner_user_id,
				'operation_scope'      => $scope,
				'idempotency_key_hash' => self::idempotency_hash( $key ),
				'request_digest'       => $request_digest,
				'registration_ref'     => $registration_ref,
				'response_version'     => (int) $response_version,
				'created_at'           => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at'           => gmdate( 'Y-m-d H:i:s', $now + self::IDEMPOTENCY_DAYS * DAY_IN_SECONDS ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	private function transition_count( $registration_id ) {
		global $wpdb;
		return max( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::transitions_table() . ' WHERE registration_id = %d', (int) $registration_id ) ) );
	}

	/**
	 * A digest is not proof by itself. Any requirement presented as verified
	 * must be attested by an upstream server-side profile/vault verifier. The
	 * default is deliberately false; this store never dereferences pointers or
	 * lets an account owner self-promote evidence assurance.
	 *
	 * @return true|WP_Error
	 */
	private function verified_evidence_allowed( $owner_user_id, $registration_ref, $input ) {
		$verified = array();
		foreach ( $input['requirements'] as $requirement ) {
			if ( 'verified' === $requirement['status'] ) {
				$verified[] = array(
					'code'            => $requirement['code'],
					'evidence_digest' => $requirement['evidence_digest'],
					'verified_at'     => $requirement['verified_at'],
				);
			}
		}
		if ( ! $verified ) {
			return true;
		}
		$context = array(
			'owner_user_id'     => (int) $owner_user_id,
			'registration_ref'  => (string) $registration_ref,
			'role_manifest_ref' => $input['role_manifest_ref'],
			'profile_refs'      => $input['profile_refs'],
			'vault_item_refs'   => $input['vault_item_refs'],
			'verified_requirements' => $verified,
			'profile_policy_class'   => 'Tra_Vel_Traveler_Profile_Policy',
			'profile_policy_contract_version' => Tra_Vel_Traveler_Profile_Taxonomy::CONTRACT_VERSION,
			'authorization_effect'  => 'registration_only',
		);
		$allowed = apply_filters( 'tra_vel_traveler_registration_verified_evidence_authorized', false, $context );
		return true === $allowed
			? true
			: self::error( 'verified_evidence_unattested', 'Verified readiness requires an upstream server-side profile/vault attestation.', 409 );
	}

	private static function requirement_deltas( $before, $after ) {
		$before_map = array();
		$after_map  = array();
		foreach ( $before as $item ) {
			$before_map[ $item['code'] ] = $item;
		}
		foreach ( $after as $item ) {
			$after_map[ $item['code'] ] = $item;
		}
		$codes = array_values( array_unique( array_merge( array_keys( $before_map ), array_keys( $after_map ) ) ) );
		$rank  = array( 'not_applicable' => 0, 'self_asserted' => 1, 'verified' => 2 );
		$changed = array();
		$invalidated = array();
		foreach ( $codes as $code ) {
			$prior = isset( $before_map[ $code ] ) ? $before_map[ $code ] : null;
			$next  = isset( $after_map[ $code ] ) ? $after_map[ $code ] : null;
			if ( $prior !== $next ) {
				$changed[] = $code;
			}
			if ( null !== $prior && ( null === $next || $rank[ $next['status'] ] < $rank[ $prior['status'] ] ) ) {
				$invalidated[] = $code;
			}
		}
		sort( $changed, SORT_STRING );
		sort( $invalidated, SORT_STRING );
		return array( 'changed' => $changed, 'invalidated' => $invalidated );
	}

	private static function inspect_schema() {
		global $wpdb;
		$requirements = array(
			self::registrations_table() => array(
				'columns' => array( 'id', 'owner_user_id', 'registration_ref', 'trip_ref', 'account_ref', 'gate', 'version', 'role_manifest_ref', 'aggregate_digest', 'aggregate', 'created_at', 'updated_at', 'retention_until' ),
				'unique'  => array( array( 'registration_ref' ), array( 'owner_user_id', 'trip_ref' ) ),
			),
			self::revisions_table() => array(
				'columns' => array( 'id', 'registration_id', 'registration_ref', 'version', 'aggregate_digest', 'aggregate', 'created_at', 'retention_until' ),
				'unique'  => array( array( 'registration_id', 'version' ) ),
			),
			self::transitions_table() => array(
				'columns' => array( 'id', 'registration_id', 'transition_ref', 'from_version', 'to_version', 'transition_digest', 'transition_event', 'occurred_at', 'retention_until' ),
				'unique'  => array( array( 'transition_ref' ), array( 'registration_id', 'to_version' ) ),
			),
			self::idempotency_table() => array(
				'columns' => array( 'id', 'owner_user_id', 'operation_scope', 'idempotency_key_hash', 'request_digest', 'registration_ref', 'response_version', 'created_at', 'expires_at' ),
				'unique'  => array( array( 'owner_user_id', 'operation_scope', 'idempotency_key_hash' ) ),
			),
		);
		$ready = 0;
		$transactional = 0;
		$required_indexes = 0;
		$ready_indexes = 0;
		$was_suppressed = $wpdb->suppress_errors( true );
		foreach ( $requirements as $table => $requirement ) {
			$identifier = str_replace( '`', '``', (string) $table );
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$identifier}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted internal table.
			$status  = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', (string) $table ), ARRAY_A );
			$indexes = $wpdb->get_results( "SHOW INDEX FROM `{$identifier}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted internal table.
			$engine_ok = is_array( $status ) && 'innodb' === strtolower( (string) ( isset( $status['Engine'] ) ? $status['Engine'] : '' ) );
			if ( $engine_ok ) {
				$transactional++;
			}
			$unique = array();
			foreach ( is_array( $indexes ) ? $indexes : array() as $index ) {
				if ( 0 !== (int) ( isset( $index['Non_unique'] ) ? $index['Non_unique'] : 1 ) ) {
					continue;
				}
				$name = (string) ( isset( $index['Key_name'] ) ? $index['Key_name'] : '' );
				$position = max( 1, (int) ( isset( $index['Seq_in_index'] ) ? $index['Seq_in_index'] : 1 ) );
				$unique[ $name ][ $position ] = (string) ( isset( $index['Column_name'] ) ? $index['Column_name'] : '' );
			}
			foreach ( $unique as &$columns_for_index ) {
				ksort( $columns_for_index );
				$columns_for_index = array_values( $columns_for_index );
			}
			unset( $columns_for_index );
			$table_indexes = 0;
			$required_indexes += count( $requirement['unique'] );
			foreach ( $requirement['unique'] as $expected ) {
				foreach ( $unique as $actual ) {
					if ( $expected === $actual ) {
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
		$expected_tables = count( $requirements );
		return array(
			'expected_tables'        => $expected_tables,
			'ready_tables'           => $ready,
			'transactional_tables'   => $transactional,
			'required_indexes'       => $required_indexes,
			'ready_indexes'          => $ready_indexes,
			'required_indexes_ready' => $required_indexes === $ready_indexes,
			'tables_ready'           => $expected_tables === $ready && $expected_tables === $transactional && $required_indexes === $ready_indexes,
		);
	}

	private static function record_cleanup_status( $result ) {
		update_option(
			self::CLEANUP_STATUS_OPTION,
			array( 'ran_at' => self::utc_now(), 'result' => $result ),
			false
		);
	}

	private static function registration_boundary() {
		return array(
			'raw_identity_data_exposed'   => false,
			'raw_payment_data_exposed'    => false,
			'raw_medical_data_exposed'    => false,
			'raw_provider_payload_exposed'=> false,
			'bearer_secret_exposed'       => false,
		);
	}

	private static function owner_ref( $kind, $owner_user_id ) {
		return Tra_Vel_Traveler_Principal::ref( $kind, $owner_user_id );
	}

	private static function authority_digest( $owner_user_id, $registration_ref, $request_digest, $version ) {
		return hash_hmac( 'sha256', 'registration-only|' . (int) $owner_user_id . '|' . $registration_ref . '|' . $request_digest . '|' . (int) $version, wp_salt( 'secure_auth' ) );
	}

	private static function idempotency_hash( $key ) {
		return hash_hmac( 'sha256', (string) $key, wp_salt( 'nonce' ) );
	}

	private static function random_ref( $kind ) {
		try {
			$random = bin2hex( random_bytes( 24 ) );
		} catch ( Exception $error ) {
			$random = str_replace( '-', '', wp_generate_uuid4() ) . substr( hash( 'sha256', wp_generate_password( 48, false, false ) ), 0, 16 );
		}
		return 'tv_' . $kind . '_' . $random;
	}

	private static function ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private static function utc_now() {
		$clock = (int) apply_filters( 'tra_vel_traveler_registration_clock', time() );
		if ( $clock < 1 || $clock > time() ) {
			$clock = time();
		}
		return gmdate( 'Y-m-d\TH:i:s\Z', $clock );
	}

	private static function mysql_from_iso( $value ) {
		$timestamp = strtotime( (string) $value );
		return gmdate( 'Y-m-d H:i:s', false === $timestamp ? time() : $timestamp );
	}

	private static function error( $suffix, $message, $status ) {
		return new WP_Error( 'tra_vel_traveler_registration_store_' . $suffix, $message, array( 'status' => $status ) );
	}

	public static function registrations_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_traveler_registrations';
	}

	public static function revisions_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_traveler_registration_revisions';
	}

	public static function transitions_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_traveler_registration_transitions';
	}

	public static function idempotency_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_traveler_registration_idempotency';
	}
}
