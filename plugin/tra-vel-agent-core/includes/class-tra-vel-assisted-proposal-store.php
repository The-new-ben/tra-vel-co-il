<?php
/**
 * Durable, append-only persistence for sourced assisted proposals.
 *
 * Authorization deliberately remains a controller responsibility. Mutating
 * methods require an already verified operator principal and quote-case row,
 * then re-lock that exact parent row to close the verification/use race.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Assisted_Proposal_Store {
	const DB_VERSION                    = '1.0.0';
	const DB_VERSION_OPTION             = 'tra_vel_assisted_proposal_db_version';
	const CLEANUP_STATUS_OPTION         = 'tra_vel_assisted_proposal_cleanup_status';
	const IDEMPOTENCY_DAYS              = 7;
	const MAX_PROPOSALS_PER_CASE        = 12;
	const MAX_REVISIONS_PER_PROPOSAL    = 20;
	const MAX_SNAPSHOT_BYTES             = 524288;
	const CLEANUP_BATCH_SIZE            = 100;
	const CLEANUP_IDEMPOTENCY_BATCH_SIZE = 1000;
	const CLEANUP_MAX_BATCHES           = 10;
	const CLEANUP_MAX_SECONDS           = 20;
	const CONTACT_CONSENT_VERSION        = '2026-07-19';
	const CONTACT_CONSENT_CONTRACT_VERSION = '1.0.0';
	const CONTACT_CONSENT_PURPOSE        = 'assisted_proposal_follow_up';
	const CONTACT_CONSENT_CONTROLLER_SCOPE = 'tra_vel';

	/** @var bool|null */
	private static $ready_cache = null;

	/**
	 * Install a pending schema revision when required.
	 */
	public static function maybe_upgrade() {
		if ( self::DB_VERSION !== get_option( self::DB_VERSION_OPTION ) ) {
			self::install();
		}
	}

	/**
	 * Create the proposal aggregate tables. Every table must remain InnoDB so
	 * a head, revision, source set, and idempotency receipt commit together.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset     = $wpdb->get_charset_collate();
		$proposals   = self::proposals_table();
		$revisions   = self::revisions_table();
		$sources     = self::sources_table();
		$events      = self::events_table();
		$idempotency = self::idempotency_table();

		dbDelta( "CREATE TABLE {$proposals} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			proposal_uuid char(36) NOT NULL,
			reference_code varchar(16) NOT NULL,
			quote_case_id bigint(20) unsigned NOT NULL,
			quote_case_uuid char(36) NOT NULL,
			position varchar(32) NOT NULL,
			status varchar(24) NOT NULL,
			traveler_disposition varchar(32) NOT NULL,
			proposal_version bigint(20) unsigned NOT NULL DEFAULT 1,
			current_revision bigint(20) unsigned NOT NULL DEFAULT 1,
			published_revision bigint(20) unsigned NOT NULL DEFAULT 0,
			last_event_sequence bigint(20) unsigned NOT NULL DEFAULT 0,
			source_case_version bigint(20) unsigned NOT NULL,
			source_case_revision bigint(20) unsigned NOT NULL,
			request_digest char(64) NOT NULL,
			current_revision_digest char(64) NOT NULL,
			published_revision_digest char(64) NOT NULL DEFAULT '',
			current_source_set_digest char(64) NOT NULL,
			created_by_user_id bigint(20) unsigned NOT NULL,
			last_actor_user_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			published_at datetime NULL,
			expires_at datetime NULL,
			retention_until datetime NOT NULL,
			legal_hold tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY proposal_uuid (proposal_uuid),
			UNIQUE KEY reference_code (reference_code),
			KEY quote_case (quote_case_id,quote_case_uuid,status),
			KEY quote_case_position (quote_case_id,position,updated_at),
			KEY expiry (status,expires_at),
			KEY retention (retention_until,legal_hold)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			proposal_id bigint(20) unsigned NOT NULL,
			sequence_no bigint(20) unsigned NOT NULL,
			proposal_version bigint(20) unsigned NOT NULL,
			event_uuid char(36) NOT NULL,
			event_type varchar(64) NOT NULL,
			action_code varchar(32) NOT NULL,
			from_status varchar(24) NOT NULL,
			to_status varchar(24) NOT NULL,
			from_disposition varchar(32) NOT NULL,
			to_disposition varchar(32) NOT NULL,
			actor_type varchar(20) NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			principal_hash char(64) NOT NULL,
			payload longtext NOT NULL,
			payload_digest char(64) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_uuid (event_uuid),
			UNIQUE KEY proposal_sequence (proposal_id,sequence_no),
			KEY proposal_event_type (proposal_id,event_type),
			KEY event_created (event_type,created_at)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$revisions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			proposal_id bigint(20) unsigned NOT NULL,
			revision_no bigint(20) unsigned NOT NULL,
			proposal_version bigint(20) unsigned NOT NULL,
			quote_case_id bigint(20) unsigned NOT NULL,
			quote_case_uuid char(36) NOT NULL,
			source_case_version bigint(20) unsigned NOT NULL,
			source_case_revision bigint(20) unsigned NOT NULL,
			request_digest char(64) NOT NULL,
			snapshot_digest char(64) NOT NULL,
			source_set_digest char(64) NOT NULL,
			source_count smallint(5) unsigned NOT NULL,
			proposal_snapshot longtext NOT NULL,
			publication_validated tinyint(1) NOT NULL DEFAULT 0,
			actor_user_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY proposal_revision (proposal_id,revision_no),
			UNIQUE KEY proposal_snapshot (proposal_id,snapshot_digest),
			KEY case_binding (quote_case_id,quote_case_uuid,source_case_revision,request_digest)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$sources} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			proposal_id bigint(20) unsigned NOT NULL,
			proposal_revision_id bigint(20) unsigned NOT NULL,
			revision_no bigint(20) unsigned NOT NULL,
			source_uuid char(36) NOT NULL,
			provider_code varchar(64) NOT NULL,
			source_type varchar(32) NOT NULL,
			relationship varchar(24) NOT NULL,
			public_label varchar(190) NOT NULL,
			supplier_name varchar(190) NOT NULL DEFAULT '',
			seller_name varchar(190) NOT NULL DEFAULT '',
			source_reference varchar(190) NOT NULL DEFAULT '',
			source_url varchar(500) NOT NULL DEFAULT '',
			observed_at datetime NOT NULL,
			fresh_until datetime NOT NULL,
			evidence_digest char(64) NOT NULL,
			source_digest char(64) NOT NULL,
			source_snapshot longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY proposal_revision_source (proposal_id,revision_no,source_uuid),
			UNIQUE KEY revision_source (proposal_revision_id,source_uuid),
			KEY source_freshness (source_type,fresh_until),
			KEY evidence_lookup (evidence_digest)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$idempotency} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			operation_scope varchar(64) NOT NULL,
			principal_hash char(64) NOT NULL,
			idempotency_key_hash char(64) NOT NULL,
			request_digest char(64) NOT NULL,
			proposal_uuid char(36) NOT NULL,
			proposal_version bigint(20) unsigned NOT NULL,
			revision_no bigint(20) unsigned NOT NULL,
			revision_digest char(64) NOT NULL,
			event_uuid char(36) NOT NULL DEFAULT '',
			response_code smallint(5) unsigned NOT NULL DEFAULT 200,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY operation_key (operation_scope,principal_hash,idempotency_key_hash),
			KEY proposal_revision (proposal_uuid,revision_no),
			KEY event_lookup (event_uuid),
			KEY expiry (expires_at)
		) ENGINE=InnoDB {$charset};" );

		self::$ready_cache = null;
		$health            = self::inspect_schema();
		if ( ! empty( $health['tables_ready'] ) ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		} else {
			delete_option( self::DB_VERSION_OPTION );
		}
	}

	/**
	 * Report physical schema truth, not only the stored migration option.
	 *
	 * @return array
	 */
	public static function schema_health() {
		$health    = self::inspect_schema();
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );
		self::$ready_cache = self::DB_VERSION === $installed && ! empty( $health['tables_ready'] );
		return array_merge(
			array(
				'schema_version'           => self::DB_VERSION,
				'installed_schema_version' => $installed ? $installed : null,
				'idempotency_days'          => self::IDEMPOTENCY_DAYS,
				'max_proposals_per_case'    => self::MAX_PROPOSALS_PER_CASE,
				'max_revisions_per_proposal'=> self::MAX_REVISIONS_PER_PROPOSAL,
				'max_snapshot_bytes'         => self::MAX_SNAPSHOT_BYTES,
			),
			$health
		);
	}

	/**
	 * @return bool
	 */
	public static function is_ready() {
		if ( null === self::$ready_cache ) {
			$health = self::inspect_schema();
			self::$ready_cache = self::DB_VERSION === (string) get_option( self::DB_VERSION_OPTION, '' ) && ! empty( $health['tables_ready'] );
		}
		return (bool) self::$ready_cache;
	}

	/**
	 * Append a private draft revision. Drafts are immutable but are never
	 * traveler-visible and cannot carry a transactional outcome.
	 *
	 * @param array    $verified_case   Controller-verified quote case.
	 * @param array    $proposal        Exact draft snapshot.
	 * @param array    $sources         Exact immutable source records.
	 * @param int      $expected_version Optimistic head version; zero creates.
	 * @param array    $principal       Verified operator principal.
	 * @param string   $idempotency_key Idempotency key.
	 * @param int|null $now             Optional UTC epoch for deterministic tests.
	 * @return array|WP_Error
	 */
	public function append_draft_revision( $verified_case, $proposal, $sources, $expected_version, $principal, $idempotency_key, $now = null ) {
		return $this->write_revision( 'proposal.draft.append', false, $verified_case, $proposal, $sources, $expected_version, $principal, $idempotency_key, $now );
	}

	/**
	 * Publish one exact sourced revision. The exact policy is evaluated after
	 * the verified parent case and proposal head are locked.
	 *
	 * The controller performs the first capability and assignment check. This
	 * store revalidates the authenticated principal and repeats assignment after
	 * locking the parent case so reassignment cannot race publication or replay.
	 *
	 * @param array    $verified_case   Controller-verified quote case.
	 * @param array    $proposal        Exact available proposal snapshot.
	 * @param array    $sources         Exact immutable source records.
	 * @param int      $expected_version Optimistic head version; zero creates.
	 * @param array    $principal       Verified operator principal.
	 * @param string   $idempotency_key Idempotency key.
	 * @param int|null $now             Optional UTC epoch for deterministic tests.
	 * @return array|WP_Error
	 */
	public function publish_revision( $verified_case, $proposal, $sources, $expected_version, $principal, $idempotency_key, $now = null ) {
		return $this->write_revision( 'proposal.publish', true, $verified_case, $proposal, $sources, $expected_version, $principal, $idempotency_key, $now );
	}

	/**
	 * Publish a server-composed revision with command-level idempotency.
	 *
	 * A reduced composition is expanded with fresh server UUIDs, evidence times,
	 * digests, and lifecycle fields on every HTTP attempt. The authored command
	 * basis therefore owns the idempotency digest; otherwise an exact network
	 * retry would be misclassified as different canonical proposal data.
	 *
	 * @param array    $verified_case    Controller-verified quote case.
	 * @param array    $proposal         Server-composed proposal snapshot.
	 * @param array    $sources          Server-composed immutable sources.
	 * @param int      $expected_version Optimistic head version; zero creates.
	 * @param array    $principal        Verified operator principal.
	 * @param array|null $contact_consent Explicit contact consent for authorize_contact only.
	 * @param string   $idempotency_key  Idempotency key.
	 * @param array    $command_basis    Closed, server-built command basis.
	 * @param int|null $now              Optional UTC epoch for deterministic tests.
	 * @return array|WP_Error
	 */
	public function publish_composed_revision( $verified_case, $proposal, $sources, $expected_version, $principal, $idempotency_key, $command_basis, $now = null ) {
		if ( ! is_array( $command_basis ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_idempotency_invalid', 'The proposal composition retry boundary is invalid.', array( 'status' => 500 ) );
		}
		return $this->write_revision( 'proposal.compose', true, $verified_case, $proposal, $sources, $expected_version, $principal, $idempotency_key, $now, $command_basis );
	}

	/**
	 * Resolve an exact reduced-command retry before regenerating dynamic fields.
	 *
	 * This is required when a delayed retry would otherwise derive an expired
	 * freshness window or revision 21 before the existing receipt can be read.
	 * The private result is returned only after locking the retained parent and
	 * resolving a principal-scoped receipt. Assignment is required for a new
	 * write, but an original author must be able to recover a committed response
	 * after a request sync resets assignment.
	 *
	 * @param array  $verified_case    Controller-verified quote case.
	 * @param int    $expected_version Original optimistic head version.
	 * @param array  $principal        Verified operator principal.
	 * @param string $idempotency_key  Idempotency key.
	 * @param array  $command_basis    Closed, server-built command basis.
	 * @return array|null|WP_Error
	 */
	public function replay_composed_revision( $verified_case, $expected_version, $principal, $idempotency_key, $command_basis ) {
		global $wpdb;
		if ( ! self::is_ready() ) {
			return new WP_Error( 'tra_vel_assisted_proposal_store_unavailable', 'Assisted proposal storage is not ready.', array( 'status' => 503 ) );
		}
		$principal_result = $this->validate_principal( $principal );
		if ( is_wp_error( $principal_result ) ) {
			return $principal_result;
		}
		$key = $this->sanitize_idempotency_key( $idempotency_key );
		if ( strlen( $key ) < 16 || ! is_array( $command_basis ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_idempotency_required', 'A bounded composition retry key and command are required.', array( 'status' => 400 ) );
		}
		$case = $this->normalize_verified_case( $verified_case, false );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		$operation_digest = $this->composition_operation_digest( $case, $expected_version, $command_basis );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_transaction_failed', 'The proposal replay transaction could not be started.', array( 'status' => 500 ) );
		}
		$locked_case = $this->lock_verified_parent_case( $case, false );
		if ( is_wp_error( $locked_case ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $locked_case;
		}
		$replay = $this->idempotent_result( 'proposal.compose', (string) $principal['principal_hash'], $key, $operation_digest, true );
		if ( is_wp_error( $replay ) || null === $replay ) {
			$wpdb->query( 'ROLLBACK' );
			return $replay;
		}
		$replay = $this->finalize_composition_replay( $replay, $locked_case );
		$wpdb->query( 'ROLLBACK' );
		if ( is_wp_error( $replay ) ) {
			return $replay;
		}
		$replay['replayed'] = true;
		return $replay;
	}

	/**
	 * Reconcile a historical composition receipt with the locked live aggregate.
	 *
	 * The immutable receipt still proves what committed, but it must not restore
	 * an actionable lifecycle after a traveler event, newer commercial revision,
	 * withdrawal, expiry, or quote-request change. A coherent latest receipt may
	 * retain its effective lifecycle; every stale receipt is projected as
	 * superseded by the controller.
	 *
	 * @param array $replay      Historical receipt bundle.
	 * @param array $locked_case Locked retained quote case.
	 * @return array|WP_Error
	 */
	private function finalize_composition_replay( $replay, $locked_case ) {
		if ( ! is_array( $replay ) || ! is_array( $replay['head'] ?? null ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_replay_unavailable', 'The prior proposal composition cannot be replayed safely.', array( 'status' => 409 ) );
		}
		$replay_head   = $replay['head'];
		$proposal_uuid = (string) ( $replay_head['proposal_uuid'] ?? '' );
		if ( ! $this->is_uuid( $proposal_uuid ) || (int) ( $replay_head['quote_case_id'] ?? 0 ) !== (int) $locked_case['id'] || ! hash_equals( strtolower( (string) ( $replay_head['quote_case_uuid'] ?? '' ) ), strtolower( (string) $locked_case['case_uuid'] ) ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_replay_unavailable', 'The prior composition is not bound to this quote case.', array( 'status' => 409 ) );
		}
		$live_head = $this->get_head_for_update( $proposal_uuid );
		if ( is_wp_error( $live_head ) ) {
			return $live_head;
		}
		if ( ! is_array( $live_head ) || (int) $live_head['quote_case_id'] !== (int) $locked_case['id'] || ! hash_equals( strtolower( (string) $live_head['quote_case_uuid'] ), strtolower( (string) $locked_case['case_uuid'] ) ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_replay_unavailable', 'The live proposal aggregate cannot be verified for replay.', array( 'status' => 409 ) );
		}
		$stale = ! $this->case_is_active_status( (string) $locked_case['status'] )
			|| (int) ( $replay_head['source_case_revision'] ?? 0 ) !== (int) $locked_case['current_revision']
			|| ! $this->safe_digest_equals( (string) ( $replay_head['request_digest'] ?? '' ), (string) $locked_case['latest_request_digest'] )
			|| (int) $live_head['source_case_revision'] !== (int) $locked_case['current_revision']
			|| ! $this->safe_digest_equals( (string) $live_head['request_digest'], (string) $locked_case['latest_request_digest'] )
			|| (int) $live_head['proposal_version'] !== (int) ( $replay_head['proposal_version'] ?? 0 )
			|| (int) $live_head['current_revision'] !== (int) ( $replay_head['current_revision'] ?? 0 )
			|| (int) $live_head['published_revision'] !== (int) ( $replay_head['published_revision'] ?? 0 )
			|| ! $this->safe_digest_equals( (string) $live_head['current_revision_digest'], (string) ( $replay_head['current_revision_digest'] ?? '' ) )
			|| ! $this->safe_digest_equals( (string) $live_head['published_revision_digest'], (string) ( $replay_head['published_revision_digest'] ?? '' ) )
			|| ! $this->safe_digest_equals( (string) $live_head['current_source_set_digest'], (string) ( $replay_head['current_source_set_digest'] ?? '' ) )
			|| ! hash_equals( (string) $live_head['status'], (string) ( $replay_head['status'] ?? '' ) )
			|| ! hash_equals( (string) $live_head['traveler_disposition'], (string) ( $replay_head['traveler_disposition'] ?? '' ) );
		if ( $stale ) {
			$replay['_force_superseded'] = true;
		}
		return $replay;
	}

	/**
	 * Append an owned traveler action without mutating commercial evidence.
	 *
	 * The controller must first authorize the exact QuoteCase. This method then
	 * re-locks that parent and verifies its database owner against the server-side
	 * principal. Caller-supplied browser owner IDs are never accepted.
	 *
	 * @param array    $verified_case    Controller-verified quote case.
	 * @param string   $proposal_uuid    Proposal UUID.
	 * @param int      $expected_version Optimistic head/state version.
	 * @param array    $principal        Server-resolved traveler principal.
	 * @param string   $action           Safe traveler action.
	 * @param string   $idempotency_key  Idempotency key.
	 * @param int|null $now              Optional UTC epoch.
	 * @return array|WP_Error
	 */
	public function record_traveler_action( $verified_case, $proposal_uuid, $expected_version, $principal, $action, $contact_consent, $idempotency_key, $now = null ) {
		$action = sanitize_key( $action );
		if ( ! in_array( $action, array( 'review', 'request_changes', 'authorize_contact', 'decline' ), true ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_action_invalid', 'The traveler action is not allowlisted.', array( 'status' => 400 ) );
		}
		$contact_consent = $this->normalize_contact_consent( $action, $contact_consent, $principal );
		if ( is_wp_error( $contact_consent ) ) {
			return $contact_consent;
		}
		return $this->write_state_event( 'proposal.traveler_action', $verified_case, $proposal_uuid, $expected_version, $principal, $action, '', $contact_consent, $idempotency_key, $now, false );
	}

	/**
	 * Recover an exact committed traveler action before checking mutable state.
	 *
	 * Ownership is revalidated against the retained parent while it is locked.
	 * A missing receipt returns null so the controller can continue through the
	 * normal active/current mutation path.
	 *
	 * @param array  $verified_case    Controller-verified retained quote case.
	 * @param string $proposal_uuid    Proposal UUID.
	 * @param int    $expected_version Original optimistic version.
	 * @param array  $principal        Server-resolved traveler principal.
	 * @param string $action           Safe traveler action.
	 * @param array|null $contact_consent Explicit contact consent for authorize_contact only.
	 * @param string $idempotency_key  Original idempotency key.
	 * @return array|null|WP_Error
	 */
	public function replay_traveler_action( $verified_case, $proposal_uuid, $expected_version, $principal, $action, $contact_consent, $idempotency_key ) {
		$action = sanitize_key( $action );
		if ( ! in_array( $action, array( 'review', 'request_changes', 'authorize_contact', 'decline' ), true ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_action_invalid', 'The traveler action is not allowlisted.', array( 'status' => 400 ) );
		}
		$contact_consent = $this->normalize_contact_consent( $action, $contact_consent, $principal );
		if ( is_wp_error( $contact_consent ) ) {
			return $contact_consent;
		}
		return $this->replay_state_event( 'proposal.traveler_action', $verified_case, $proposal_uuid, $expected_version, $principal, $action, $contact_consent, $idempotency_key, false );
	}

	/**
	 * Withdraw an available proposal through an auditable operator event.
	 *
	 * @param array    $verified_case    Controller-verified quote case.
	 * @param string   $proposal_uuid    Proposal UUID.
	 * @param int      $expected_version Optimistic head/state version.
	 * @param array    $principal        Verified operator principal.
	 * @param string   $idempotency_key  Idempotency key.
	 * @param int|null $now              Optional UTC epoch.
	 * @return array|WP_Error
	 */
	public function withdraw( $verified_case, $proposal_uuid, $expected_version, $principal, $idempotency_key, $now = null ) {
		return $this->write_state_event( 'proposal.withdraw', $verified_case, $proposal_uuid, $expected_version, $principal, 'withdraw', '', null, $idempotency_key, $now, true );
	}

	/**
	 * Recover an exact committed operator withdrawal without requiring the case
	 * to remain active or assigned to its original operator. The receipt remains
	 * principal-scoped and the retained parent is locked before private data is
	 * returned.
	 *
	 * @param array  $verified_case    Controller-verified retained quote case.
	 * @param string $proposal_uuid    Proposal UUID.
	 * @param int    $expected_version Original optimistic version.
	 * @param array  $principal        Verified operator principal.
	 * @param string $idempotency_key  Original idempotency key.
	 * @return array|null|WP_Error
	 */
	public function replay_withdrawal( $verified_case, $proposal_uuid, $expected_version, $principal, $idempotency_key ) {
		return $this->replay_state_event( 'proposal.withdraw', $verified_case, $proposal_uuid, $expected_version, $principal, 'withdraw', null, $idempotency_key, true );
	}

	/**
	 * Read one proposal head. SQL errors return WP_Error; absence returns null.
	 *
	 * @param string $proposal_uuid Proposal UUID.
	 * @return array|null|WP_Error
	 */
	public function get_by_uuid( $proposal_uuid ) {
		global $wpdb;
		if ( ! $this->is_uuid( $proposal_uuid ) ) {
			return null;
		}
		$row = $this->read_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::proposals_table() . ' WHERE proposal_uuid = %s LIMIT 1', (string) $proposal_uuid ),
			'tra_vel_assisted_proposal_read_failed',
			'Proposal storage could not be read safely.'
		);
		return is_array( $row ) ? $this->hydrate_head( $row ) : $row;
	}

	/**
	 * List heads attached to both the numeric and UUID quote-case identity.
	 *
	 * @param int    $quote_case_id   Numeric quote-case ID.
	 * @param string $quote_case_uuid Quote-case UUID.
	 * @param int    $limit           Maximum records.
	 * @return array|WP_Error
	 */
	public function list_by_case( $quote_case_id, $quote_case_uuid, $limit = 20 ) {
		global $wpdb;
		if ( absint( $quote_case_id ) < 1 || ! $this->is_uuid( $quote_case_uuid ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_case_identity_invalid', 'Both quote-case identities are required.', array( 'status' => 400 ) );
		}
		$limit = min( 50, max( 1, absint( $limit ) ) );
		$rows  = $this->read_rows(
			$wpdb->prepare(
				'SELECT * FROM ' . self::proposals_table() . ' WHERE quote_case_id = %d AND quote_case_uuid = %s ORDER BY updated_at DESC,id DESC LIMIT %d',
				absint( $quote_case_id ),
				(string) $quote_case_uuid,
				$limit
			),
			'tra_vel_assisted_proposal_list_failed',
			'Proposal storage could not be listed safely.'
		);
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		return array_map( array( $this, 'hydrate_head' ), $rows );
	}

	/**
	 * Capability-neutral operator listing. The controller must gate this method.
	 *
	 * @param string $status   Optional stored status.
	 * @param int    $page     One-based page.
	 * @param int    $per_page Bounded page size.
	 * @return array|WP_Error
	 */
	public function list_operator( $status = '', $page = 1, $per_page = 30 ) {
		global $wpdb;
		$status   = sanitize_key( $status );
		$page     = max( 1, absint( $page ) );
		$per_page = min( 50, max( 1, absint( $per_page ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		if ( '' !== $status && ! in_array( $status, Tra_Vel_Assisted_Proposal_Policy::statuses(), true ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_status_invalid', 'The proposal status filter is invalid.', array( 'status' => 400 ) );
		}
		if ( '' === $status ) {
			$sql = $wpdb->prepare( 'SELECT * FROM ' . self::proposals_table() . ' ORDER BY updated_at DESC,id DESC LIMIT %d OFFSET %d', $per_page, $offset );
		} else {
			$sql = $wpdb->prepare( 'SELECT * FROM ' . self::proposals_table() . ' WHERE status = %s ORDER BY updated_at DESC,id DESC LIMIT %d OFFSET %d', $status, $per_page, $offset );
		}
		$rows = $this->read_rows( $sql, 'tra_vel_assisted_proposal_operator_list_failed', 'Proposal storage could not be listed safely.' );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		return array_map( array( $this, 'hydrate_head' ), $rows );
	}

	/**
	 * Read and integrity-check an immutable revision plus its exact source set.
	 *
	 * @param string $proposal_uuid Proposal UUID.
	 * @param int    $revision_no   Revision number; zero selects current.
	 * @return array|null|WP_Error
	 */
	public function get_revision_bundle( $proposal_uuid, $revision_no = 0 ) {
		$head = $this->get_by_uuid( $proposal_uuid );
		if ( is_wp_error( $head ) || null === $head ) {
			return $head;
		}
		$revision_no = absint( $revision_no );
		if ( $revision_no < 1 ) {
			$revision_no = (int) $head['current_revision'];
		}
		return $this->load_revision_bundle( $head, $revision_no );
	}

	/**
	 * Bounded retention cleanup. A proposal is deletable only while both its
	 * copied legal-hold flag and the still-present parent case prove no hold.
	 * Missing parents are retained for explicit reconciliation, never guessed.
	 *
	 * @return array
	 */
	public static function cleanup() {
		if ( ! self::is_ready() ) {
			$result = array(
				'deleted_proposals'   => 0,
				'deleted_idempotency' => 0,
				'batches'             => 0,
				'errors'              => array( 'schema_not_ready' ),
			);
			self::record_cleanup_status( $result );
			return $result;
		}

		$store              = new self();
		$now                = current_time( 'mysql', true );
		$deadline           = microtime( true ) + self::CLEANUP_MAX_SECONDS;
		$retention_cursor   = 0;
		$idempotency_cursor = 0;
		$retention_done     = false;
		$idempotency_done   = false;
		$result             = array(
			'deleted_proposals'   => 0,
			'deleted_idempotency' => 0,
			'batches'             => 0,
			'errors'              => array(),
		);

		for ( $batch = 0; $batch < self::CLEANUP_MAX_BATCHES && microtime( true ) < $deadline; $batch++ ) {
			$result['batches']++;
			if ( ! $retention_done ) {
				$deleted = $store->delete_retention_batch( $now, $retention_cursor, self::CLEANUP_BATCH_SIZE );
				if ( is_wp_error( $deleted ) ) {
					$result['errors'][] = $deleted->get_error_code();
					$retention_done     = true;
				} else {
					$retention_cursor             = (int) $deleted['last_id'];
					$result['deleted_proposals'] += (int) $deleted['deleted'];
					$retention_done               = (int) $deleted['scanned'] < self::CLEANUP_BATCH_SIZE;
				}
			}

			if ( ! $idempotency_done && microtime( true ) < $deadline ) {
				$deleted = $store->sweep_idempotency_batch( $now, $idempotency_cursor, self::CLEANUP_IDEMPOTENCY_BATCH_SIZE );
				if ( is_wp_error( $deleted ) ) {
					$result['errors'][] = $deleted->get_error_code();
					$idempotency_done   = true;
				} else {
					$idempotency_cursor             = (int) $deleted['last_id'];
					$result['deleted_idempotency'] += (int) $deleted['deleted'];
					$idempotency_done               = (int) $deleted['scanned'] < self::CLEANUP_IDEMPOTENCY_BATCH_SIZE;
				}
			}

			if ( $retention_done && $idempotency_done ) {
				break;
			}
		}

		self::record_cleanup_status( $result );
		return $result;
	}

	/**
	 * Execute a draft append or exact publication mutation.
	 */
	private function write_revision( $scope, $publish, $verified_case, $proposal, $sources, $expected_version, $principal, $idempotency_key, $now, $command_basis = null ) {
		global $wpdb;
		if ( ! self::is_ready() ) {
			return new WP_Error( 'tra_vel_assisted_proposal_store_unavailable', 'Assisted proposal storage is not ready.', array( 'status' => 503 ) );
		}
		$now = null === $now ? time() : (int) $now;
		$principal_result = $this->validate_principal( $principal );
		if ( is_wp_error( $principal_result ) ) {
			return $principal_result;
		}
		$key = $this->sanitize_idempotency_key( $idempotency_key );
		if ( strlen( $key ) < 16 ) {
			return new WP_Error( 'tra_vel_assisted_proposal_idempotency_required', 'A bounded idempotency key is required.', array( 'status' => 400 ) );
		}
		$case = $this->normalize_verified_case( $verified_case );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		$normalized_sources = $this->normalize_sources( $sources, $now );
		if ( is_wp_error( $normalized_sources ) ) {
			return $normalized_sources;
		}
		$normalized_proposal = $this->normalize_proposal( $proposal );
		if ( is_wp_error( $normalized_proposal ) ) {
			return $normalized_proposal;
		}
		$proposal = $normalized_proposal;
		if ( null === $command_basis ) {
			$operation_proposal                 = $proposal;
			$operation_proposal['created_at']   = 'server_controlled';
			$operation_proposal['published_at'] = $publish ? 'server_controlled' : null;
			$operation_input = array(
				'operation'        => (string) $scope,
				'expected_version' => absint( $expected_version ),
				'case_binding'     => $this->stable_case_binding( $case ),
				'proposal'         => $operation_proposal,
				'sources'          => $normalized_sources,
			);
		} else {
			$operation_input = null;
		}
		$operation_digest = null === $operation_input
			? $this->composition_operation_digest( $case, $expected_version, $command_basis )
			: Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $operation_input );
		$principal_hash = (string) $principal['principal_hash'];

		$replay = $this->idempotent_result( $scope, $principal_hash, $key, $operation_digest, false );
		if ( is_wp_error( $replay ) ) {
			return $replay;
		}
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_transaction_failed', 'The proposal transaction could not be started.', array( 'status' => 500 ) );
		}

		$locked_case = $this->lock_verified_parent_case( $case );
		if ( is_wp_error( $locked_case ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $locked_case;
		}
		$assignment = $this->validate_operator_assignment( $principal, $locked_case );
		if ( is_wp_error( $assignment ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $assignment;
		}
		$locked_replay = $this->idempotent_result( $scope, $principal_hash, $key, $operation_digest, true );
		if ( is_wp_error( $locked_replay ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $locked_replay;
		}
		if ( is_array( $locked_replay ) ) {
			$wpdb->query( 'ROLLBACK' );
			$locked_replay['replayed'] = true;
			return $locked_replay;
		}

		$head = $this->get_head_for_update( (string) ( $proposal['proposal_id'] ?? '' ) );
		if ( is_wp_error( $head ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $head;
		}
		if ( null === $head ) {
			$head_count = $this->count_case_heads( $locked_case );
			if ( is_wp_error( $head_count ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $head_count;
			}
			if ( $head_count >= self::MAX_PROPOSALS_PER_CASE ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'tra_vel_assisted_proposal_case_limit', 'This quote case already has the maximum retained proposal options.', array( 'status' => 409 ) );
			}
		}
		$proposal = $this->apply_server_timestamps( $proposal, $head, $publish, $now );

		$valid = $this->validate_revision_for_write( $proposal, $normalized_sources, $locked_case, $head, absint( $expected_version ), $publish, $now );
		if ( is_wp_error( $valid ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $valid;
		}

		$snapshot_digest = Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $proposal );
		$source_digest   = Tra_Vel_Assisted_Proposal_Policy::source_set_digest( $normalized_sources );
		$revision        = (int) $proposal['revision'];
		$actor_user_id   = absint( $principal['user_id'] );

		if ( null === $head ) {
			$head = $this->insert_head( $locked_case, $proposal, $snapshot_digest, $source_digest, $actor_user_id, $publish );
			if ( is_wp_error( $head ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $head;
			}
		}

		$revision_row = $this->insert_revision_and_sources( $head, $locked_case, $proposal, $normalized_sources, $snapshot_digest, $source_digest, $actor_user_id, $publish );
		if ( is_wp_error( $revision_row ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $revision_row;
		}

		if ( (int) $head['current_revision'] !== $revision ) {
			$updated = $this->advance_head( $head, $locked_case, $proposal, $snapshot_digest, $source_digest, $actor_user_id, $publish );
			if ( is_wp_error( $updated ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $updated;
			}
			$head = $updated;
		}
		$version = (int) $head['proposal_version'];

		if ( ! $this->insert_idempotency( $scope, $principal_hash, $key, $operation_digest, (string) $proposal['proposal_id'], $version, $revision, $snapshot_digest, '', $publish ? 201 : 200 ) ) {
			$wpdb->query( 'ROLLBACK' );
			$recovered = $this->idempotent_result( $scope, $principal_hash, $key, $operation_digest, false );
			if ( is_array( $recovered ) ) {
				$recovered['replayed'] = true;
				return $recovered;
			}
			return is_wp_error( $recovered ) ? $recovered : new WP_Error( 'tra_vel_assisted_proposal_idempotency_failed', 'The proposal mutation could not be recorded safely.', array( 'status' => 500 ) );
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$recovered = $this->idempotent_result( $scope, $principal_hash, $key, $operation_digest, false );
			if ( is_array( $recovered ) ) {
				$recovered['replayed'] = true;
				return $recovered;
			}
			return is_wp_error( $recovered ) ? $recovered : new WP_Error( 'tra_vel_assisted_proposal_transaction_failed', 'The proposal transaction could not be committed.', array( 'status' => 500 ) );
		}

		return array(
			'head'              => $this->hydrate_head( $head ),
			'proposal'          => $proposal,
			'revision_snapshot' => $proposal,
			'sources'           => $normalized_sources,
			'revision_metadata' => $revision_row,
			'event'             => null,
			'replayed'          => false,
		);
	}

	/**
	 * Hash the stable reduced command, not regenerated proposal identities or
	 * mutable parent-request state. First execution separately validates the
	 * locked current request; an exact retry must still find its prior receipt
	 * after the parent request evolves.
	 *
	 * @param array $case             Normalized verified case.
	 * @param int   $expected_version Original optimistic version.
	 * @param array $command_basis    Closed authored command basis.
	 * @return string
	 */
	private function composition_operation_digest( $case, $expected_version, $command_basis ) {
		return Tra_Vel_Assisted_Proposal_Policy::canonical_digest(
			array(
				'operation'        => 'proposal.compose',
				'expected_version' => absint( $expected_version ),
				'case_binding'     => $this->stable_case_binding( $case ),
				'command'          => $command_basis,
			)
		);
	}

	/** @return array */
	private function stable_case_binding( $case ) {
		return array(
			'case_id' => (string) $case['case_uuid'],
		);
	}

	/**
	 * Build the immutable command digest for a proposal state transition.
	 *
	 * The live quote-case snapshot is deliberately excluded. It may advance or
	 * close after a successful commit, but that must not make an exact network
	 * retry look like a different command.
	 */
	private function state_event_operation_digest( $scope, $case, $proposal_uuid, $expected_version, $action, $contact_consent ) {
		return Tra_Vel_Assisted_Proposal_Policy::canonical_digest(
			array(
				'operation'        => (string) $scope,
				'case_binding'     => $this->stable_case_binding( $case ),
				'proposal_id'      => strtolower( (string) $proposal_uuid ),
				'expected_version' => absint( $expected_version ),
				'action'           => (string) $action,
				'contact_consent'  => $contact_consent,
			)
		);
	}

	/**
	 * Enforce the same closed, privacy-safe contact-consent contract at the
	 * persistence boundary. Raw contact details are never accepted here.
	 */
	private function normalize_contact_consent( $action, $contact_consent, $principal ) {
		if ( 'authorize_contact' !== (string) $action ) {
			return null === $contact_consent
				? null
				: new WP_Error( 'tra_vel_assisted_proposal_contact_consent_unexpected', 'Contact consent is accepted only with authorize_contact.', array( 'status' => 400 ) );
		}
		if ( ! is_array( $contact_consent ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_consent_required', 'Explicit contact consent is required before contact can be authorized.', array( 'status' => 400 ) );
		}
		$required = array( 'contract_version', 'consent_version', 'affirmed', 'purpose', 'channels', 'controller_scope', 'recipient_scope', 'contact_target' );
		if ( array_diff( $required, array_keys( $contact_consent ) ) || array_diff( array_keys( $contact_consent ), $required ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_consent_shape_invalid', 'Contact consent must use the exact supported fields.', array( 'status' => 400 ) );
		}
		$channels = $contact_consent['channels'];
		if ( ! is_array( $channels ) || array( 'email' ) !== array_values( $channels ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_consent_invalid', 'Contact consent channels are invalid.', array( 'status' => 400 ) );
		}
		if ( self::CONTACT_CONSENT_CONTRACT_VERSION !== ( $contact_consent['contract_version'] ?? null ) || self::CONTACT_CONSENT_VERSION !== ( $contact_consent['consent_version'] ?? null ) || true !== ( $contact_consent['affirmed'] ?? null ) || self::CONTACT_CONSENT_PURPOSE !== ( $contact_consent['purpose'] ?? null ) || self::CONTACT_CONSENT_CONTROLLER_SCOPE !== ( $contact_consent['controller_scope'] ?? null ) || 'tra_vel_assistance_team' !== ( $contact_consent['recipient_scope'] ?? null ) || 'account_email' !== ( $contact_consent['contact_target'] ?? null ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_consent_invalid', 'Contact consent does not match the supported privacy notice.', array( 'status' => 400 ) );
		}
		$user_id = absint( $principal['user_id'] ?? 0 );
		if ( $user_id < 1 ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_target_unverified', 'Contact authorization requires an authenticated account target.', array( 'status' => 409 ) );
		}
		return array(
			'contract_version' => self::CONTACT_CONSENT_CONTRACT_VERSION,
			'consent_version'  => self::CONTACT_CONSENT_VERSION,
			'affirmed'         => true,
			'purpose'          => self::CONTACT_CONSENT_PURPOSE,
			'channels'         => array( 'email' ),
			'controller_scope' => self::CONTACT_CONSENT_CONTROLLER_SCOPE,
			'recipient_scope'  => 'tra_vel_assistance_team',
			'contact_target'   => 'account_email',
		);
	}

	/**
	 * Derive a non-reversible binding to the account email that exists now.
	 *
	 * The stable command contract deliberately identifies only account_email;
	 * this HMAC is persisted as event evidence and never participates in the
	 * idempotency digest. Exact receipt replay therefore survives a later account
	 * edit, while a new dispatch must still match the precise consented address.
	 */
	private function current_account_email_digest( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 || ! function_exists( 'get_userdata' ) || ! function_exists( 'is_email' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_target_unverified', 'The current account email could not be verified.', array( 'status' => 409 ) );
		}
		$user = get_userdata( $user_id );
		if ( ! is_object( $user ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_target_unverified', 'The current account email could not be verified.', array( 'status' => 409 ) );
		}
		$email     = strtolower( trim( (string) ( $user->user_email ?? '' ) ) );
		$validated = is_email( $email );
		if ( false === $validated || '' === $email ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_target_unverified', 'The current account email could not be verified.', array( 'status' => 409 ) );
		}
		if ( is_string( $validated ) ) {
			$email = strtolower( trim( $validated ) );
		}
		if ( ! function_exists( 'wp_salt' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_target_secret_unavailable', 'The contact authorization secret is unavailable.', array( 'status' => 503 ) );
		}
		$secret = (string) wp_salt( 'auth' );
		if ( '' === $secret ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_target_secret_unavailable', 'The contact authorization secret is unavailable.', array( 'status' => 503 ) );
		}
		return hash_hmac(
			'sha256',
			'wp-user-account:' . $user_id . '|account-email:' . $email,
			$secret . '|tra-vel-assisted-proposal-contact-target-v1'
		);
	}

	/**
	 * Validate persisted consent evidence without consulting mutable account data.
	 */
	private function normalize_stored_contact_consent( $stored_consent, $actor_user_id ) {
		if ( ! is_array( $stored_consent ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_event_integrity_failed', 'The contact authorization event lacks valid consent evidence.', array( 'status' => 500 ) );
		}
		$stable_fields = array( 'contract_version', 'consent_version', 'affirmed', 'purpose', 'channels', 'controller_scope', 'recipient_scope', 'contact_target' );
		$required      = array_merge( $stable_fields, array( 'contact_target_digest', 'consented_at' ) );
		if ( array_diff( $required, array_keys( $stored_consent ) ) || array_diff( array_keys( $stored_consent ), $required ) || ! $this->is_sha256( $stored_consent['contact_target_digest'] ?? null ) || $this->parse_datetime( $stored_consent['consented_at'] ?? '' ) < 1 ) {
			return new WP_Error( 'tra_vel_assisted_proposal_event_integrity_failed', 'The contact authorization event lacks valid consent evidence.', array( 'status' => 500 ) );
		}
		$stable_consent = array();
		foreach ( $stable_fields as $field ) {
			$stable_consent[ $field ] = $stored_consent[ $field ];
		}
		$normalized = $this->normalize_contact_consent( 'authorize_contact', $stable_consent, array( 'user_id' => absint( $actor_user_id ) ) );
		if ( is_wp_error( $normalized ) || ! $this->safe_digest_equals( Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $stable_consent ), Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $normalized ) ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_event_integrity_failed', 'The contact authorization consent evidence is not canonical.', array( 'status' => 500 ) );
		}
		return array_merge(
			$normalized,
			array(
				'contact_target_digest' => (string) $stored_consent['contact_target_digest'],
				'consented_at'           => (string) $stored_consent['consented_at'],
			)
		);
	}

	/**
	 * Look up an exact state-event receipt while holding its retained parent.
	 *
	 * The original traveler must still own the case. Operator receipts are
	 * recoverable only by the same authenticated principal; current assignment
	 * is intentionally a new-write condition rather than a replay condition.
	 */
	private function replay_state_event( $scope, $verified_case, $proposal_uuid, $expected_version, $principal, $action, $contact_consent, $idempotency_key, $operator ) {
		global $wpdb;
		if ( ! self::is_ready() ) {
			return new WP_Error( 'tra_vel_assisted_proposal_store_unavailable', 'Assisted proposal storage is not ready.', array( 'status' => 503 ) );
		}
		if ( ! $this->is_uuid( $proposal_uuid ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_identity_invalid', 'A valid proposal identity is required.', array( 'status' => 400 ) );
		}
		$principal_valid = $operator ? $this->validate_principal( $principal ) : $this->validate_traveler_principal_shape( $principal );
		if ( is_wp_error( $principal_valid ) ) {
			return $principal_valid;
		}
		$key = $this->sanitize_idempotency_key( $idempotency_key );
		if ( strlen( $key ) < 16 ) {
			return new WP_Error( 'tra_vel_assisted_proposal_idempotency_required', 'A bounded idempotency key is required.', array( 'status' => 400 ) );
		}
		$case = $this->normalize_verified_case( $verified_case, false );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		$operation_digest = $this->state_event_operation_digest( $scope, $case, $proposal_uuid, $expected_version, $action, $contact_consent );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_transaction_failed', 'The proposal replay transaction could not be started.', array( 'status' => 500 ) );
		}
		$locked_case = $this->lock_verified_parent_case( $case, false );
		if ( is_wp_error( $locked_case ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $locked_case;
		}
		if ( ! $operator ) {
			$owner_valid = $this->validate_traveler_owner( $principal, $locked_case );
			if ( is_wp_error( $owner_valid ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $owner_valid;
			}
		}
		$replay = $this->idempotent_result( $scope, (string) $principal['principal_hash'], $key, $operation_digest, true );
		if ( is_wp_error( $replay ) || null === $replay ) {
			$wpdb->query( 'ROLLBACK' );
			return $replay;
		}
		$replay = $this->finalize_state_event_replay( $replay, $locked_case, $proposal_uuid );
		$wpdb->query( 'ROLLBACK' );
		if ( is_wp_error( $replay ) ) {
			return $replay;
		}
		$replay['replayed'] = true;
		return $replay;
	}

	/**
	 * Validate a historical receipt against the requested aggregate and mark it
	 * non-actionable when the live parent or head has advanced since the event.
	 */
	private function finalize_state_event_replay( $replay, $locked_case, $proposal_uuid ) {
		if ( ! is_array( $replay ) || ! is_array( $replay['head'] ?? null ) || ! is_array( $replay['event'] ?? null ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_replay_unavailable', 'The prior proposal action cannot be replayed safely.', array( 'status' => 409 ) );
		}
		$replay_head = $replay['head'];
		if ( ! hash_equals( strtolower( (string) $proposal_uuid ), strtolower( (string) ( $replay_head['proposal_uuid'] ?? '' ) ) ) || (int) ( $replay_head['quote_case_id'] ?? 0 ) !== (int) $locked_case['id'] || ! hash_equals( strtolower( (string) ( $replay_head['quote_case_uuid'] ?? '' ) ), strtolower( (string) $locked_case['case_uuid'] ) ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_replay_unavailable', 'The prior proposal action is not bound to this quote case.', array( 'status' => 409 ) );
		}
		$live_head = $this->get_head_for_update( $proposal_uuid );
		if ( is_wp_error( $live_head ) ) {
			return $live_head;
		}
		if ( ! is_array( $live_head ) || (int) $live_head['quote_case_id'] !== (int) $locked_case['id'] || ! hash_equals( strtolower( (string) $live_head['quote_case_uuid'] ), strtolower( (string) $locked_case['case_uuid'] ) ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_replay_unavailable', 'The prior proposal action aggregate cannot be verified.', array( 'status' => 409 ) );
		}
		$event_version = (int) ( $replay['event']['proposal_version'] ?? 0 );
		if ( ! $this->case_is_active_status( (string) $locked_case['status'] ) || (int) $live_head['proposal_version'] !== $event_version || (int) $live_head['source_case_revision'] !== (int) $locked_case['current_revision'] || ! $this->safe_digest_equals( (string) $live_head['request_digest'], (string) $locked_case['latest_request_digest'] ) ) {
			$replay['_force_superseded'] = true;
		}
		return $replay;
	}

	/**
	 * Append a traveler-action or operator-withdrawal event and advance only the
	 * mutable head. Immutable commercial revisions and evidence remain untouched.
	 */
	private function write_state_event( $scope, $verified_case, $proposal_uuid, $expected_version, $principal, $action, $target_disposition, $contact_consent, $idempotency_key, $now, $operator ) {
		global $wpdb;
		if ( ! self::is_ready() ) {
			return new WP_Error( 'tra_vel_assisted_proposal_store_unavailable', 'Assisted proposal storage is not ready.', array( 'status' => 503 ) );
		}
		$now = null === $now ? time() : (int) $now;
		if ( ! $this->is_uuid( $proposal_uuid ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_identity_invalid', 'A valid proposal identity is required.', array( 'status' => 400 ) );
		}
		$principal_valid = $operator ? $this->validate_principal( $principal ) : $this->validate_traveler_principal_shape( $principal );
		if ( is_wp_error( $principal_valid ) ) {
			return $principal_valid;
		}
		$key = $this->sanitize_idempotency_key( $idempotency_key );
		if ( strlen( $key ) < 16 ) {
			return new WP_Error( 'tra_vel_assisted_proposal_idempotency_required', 'A bounded idempotency key is required.', array( 'status' => 400 ) );
		}
		$case = $this->normalize_verified_case( $verified_case, false );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		$operation_digest = $this->state_event_operation_digest( $scope, $case, $proposal_uuid, $expected_version, $action, $contact_consent );
		$principal_hash = (string) $principal['principal_hash'];

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_transaction_failed', 'The proposal state transaction could not be started.', array( 'status' => 500 ) );
		}
		$locked_case = $this->lock_verified_parent_case( $case, false );
		if ( is_wp_error( $locked_case ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $locked_case;
		}
		if ( ! $operator ) {
			$owner_valid = $this->validate_traveler_owner( $principal, $locked_case );
			if ( is_wp_error( $owner_valid ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $owner_valid;
			}
		}
		$locked_replay = $this->idempotent_result( $scope, $principal_hash, $key, $operation_digest, true );
		if ( is_wp_error( $locked_replay ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $locked_replay;
		}
		if ( is_array( $locked_replay ) ) {
			$locked_replay = $this->finalize_state_event_replay( $locked_replay, $locked_case, $proposal_uuid );
			$wpdb->query( 'ROLLBACK' );
			if ( is_wp_error( $locked_replay ) ) {
				return $locked_replay;
			}
			$locked_replay['replayed'] = true;
			return $locked_replay;
		}
		if ( ! $this->case_is_active_status( (string) $locked_case['status'] ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_assisted_proposal_parent_inactive', 'The parent quote case is not active for proposal mutation.', array( 'status' => 409 ) );
		}
		if ( $operator ) {
			$assignment = $this->validate_operator_assignment( $principal, $locked_case );
			if ( is_wp_error( $assignment ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $assignment;
			}
		}
		$head = $this->get_head_for_update( $proposal_uuid );
		if ( is_wp_error( $head ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $head;
		}
		if ( ! is_array( $head ) || (int) $head['quote_case_id'] !== (int) $locked_case['id'] || ! hash_equals( strtolower( (string) $head['quote_case_uuid'] ), strtolower( (string) $locked_case['case_uuid'] ) ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_assisted_proposal_parent_mismatch', 'The proposal does not belong to the verified quote case.', array( 'status' => 409 ) );
		}
		if ( (int) $head['source_case_revision'] !== (int) $locked_case['current_revision'] || ! $this->safe_digest_equals( (string) $head['request_digest'], (string) $locked_case['latest_request_digest'] ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_assisted_proposal_request_changed', 'The quote-case request changed after this proposal was prepared.', array( 'status' => 409 ) );
		}
		if ( (int) $head['proposal_version'] !== absint( $expected_version ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_assisted_proposal_version_conflict', 'The proposal state changed before this action.', array( 'status' => 409, 'current_version' => (int) $head['proposal_version'] ) );
		}
		$effective_status = Tra_Vel_Assisted_Proposal_Policy::effective_status( (string) $head['stored_status'], (string) $head['expires_at'], $now );
		if ( 'available' !== $effective_status || (int) $head['published_revision'] < 1 ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_assisted_proposal_not_available', 'Only a current available proposal can accept this action.', array( 'status' => 409 ) );
		}

		$from_disposition = (string) $head['traveler_disposition'];
		if ( ! $operator ) {
			$target_disposition = Tra_Vel_Assisted_Proposal_Policy::traveler_action_target( 'available', $from_disposition, $action );
			if ( is_wp_error( $target_disposition ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $target_disposition;
			}
		}
		$to_status     = $operator ? 'withdrawn' : 'available';
		$to_disposition = $operator ? 'unavailable' : (string) $target_disposition;
		$new_version   = (int) $head['proposal_version'] + 1;
		$sequence      = (int) $head['last_event_sequence'] + 1;
		$actor_type    = $operator ? 'operator' : ( absint( $principal['user_id'] ?? 0 ) > 0 ? 'traveler' : 'guest' );
		$payload       = array(
			'contract_version' => '1.0.0',
			'action'           => (string) $action,
			'from_status'      => (string) $head['stored_status'],
			'to_status'        => $to_status,
			'from_disposition' => $from_disposition,
			'to_disposition'   => $to_disposition,
			'proposal_version' => $new_version,
			'case_revision'    => (int) $locked_case['current_revision'],
			'request_digest'   => (string) $locked_case['latest_request_digest'],
			'proposal_revision'=> (int) $head['published_revision'],
		);
		if ( is_array( $contact_consent ) ) {
			$contact_target_digest = $this->current_account_email_digest( $principal['user_id'] ?? 0 );
			if ( is_wp_error( $contact_target_digest ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $contact_target_digest;
			}
			$payload['contact_consent'] = array_merge(
				$contact_consent,
				array(
					'contact_target_digest' => $contact_target_digest,
					'consented_at'           => gmdate( 'c', $now ),
				)
			);
		}
		$event = $this->insert_event(
			(int) $head['id'],
			$sequence,
			$new_version,
			$operator ? 'proposal.withdrawn' : 'traveler.' . $to_disposition,
			$action,
			(string) $head['stored_status'],
			$to_status,
			$from_disposition,
			$to_disposition,
			$actor_type,
			absint( $principal['user_id'] ?? 0 ),
			$principal_hash,
			$payload
		);
		if ( is_wp_error( $event ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $event;
		}

		$updated = $wpdb->update(
			self::proposals_table(),
			array(
				'status'               => $to_status,
				'traveler_disposition' => $to_disposition,
				'proposal_version'     => $new_version,
				'last_event_sequence'  => $sequence,
				'last_actor_user_id'   => absint( $principal['user_id'] ?? 0 ),
				'updated_at'           => current_time( 'mysql', true ),
				'legal_hold'           => ! empty( $head['legal_hold'] ) || ! empty( $locked_case['legal_hold'] ) ? 1 : 0,
			),
			array(
				'id'                  => (int) $head['id'],
				'proposal_version'    => (int) $head['proposal_version'],
				'last_event_sequence' => (int) $head['last_event_sequence'],
			),
			array( '%s', '%s', '%d', '%d', '%d', '%s', '%d' ),
			array( '%d', '%d', '%d' )
		);
		if ( 1 !== (int) $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_assisted_proposal_version_conflict', 'The proposal state changed before its event could commit.', array( 'status' => 409 ) );
		}
		$head = array_merge(
			$head,
			array(
				'status'               => $to_status,
				'stored_status'        => $to_status,
				'traveler_disposition' => $to_disposition,
				'proposal_version'     => $new_version,
				'last_event_sequence'  => $sequence,
				'last_actor_user_id'   => absint( $principal['user_id'] ?? 0 ),
				'updated_at'           => current_time( 'mysql', true ),
			)
		);
		$revision_digest = (string) $head['published_revision_digest'];
		if ( ! $this->insert_idempotency( $scope, $principal_hash, $key, $operation_digest, (string) $proposal_uuid, $new_version, (int) $head['published_revision'], $revision_digest, (string) $event['event_id'], 200 ) ) {
			$wpdb->query( 'ROLLBACK' );
			$recovered = $this->idempotent_result( $scope, $principal_hash, $key, $operation_digest, false );
			if ( is_array( $recovered ) ) {
				$recovered['replayed'] = true;
				return $recovered;
			}
			return is_wp_error( $recovered ) ? $recovered : new WP_Error( 'tra_vel_assisted_proposal_idempotency_failed', 'The proposal action could not be recorded safely.', array( 'status' => 500 ) );
		}
		$bundle = $this->load_revision_bundle( $this->hydrate_head( $head ), (int) $head['published_revision'] );
		if ( is_wp_error( $bundle ) || ! is_array( $bundle ) ) {
			$wpdb->query( 'ROLLBACK' );
			return is_wp_error( $bundle ) ? $bundle : new WP_Error( 'tra_vel_assisted_proposal_action_read_failed', 'The proposal action result could not be assembled safely.', array( 'status' => 500 ) );
		}
		$bundle['event']    = $event;
		$bundle['replayed'] = false;
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$recovered = $this->idempotent_result( $scope, $principal_hash, $key, $operation_digest, false );
			if ( is_array( $recovered ) ) {
				$recovered['replayed'] = true;
				return $recovered;
			}
			return is_wp_error( $recovered ) ? $recovered : new WP_Error( 'tra_vel_assisted_proposal_transaction_failed', 'The proposal action could not be committed.', array( 'status' => 500 ) );
		}
		return $bundle;
	}

	/**
	 * Replace caller timestamps with authoritative server time before policy
	 * validation and immutable snapshot digesting. Existing heads preserve their
	 * original server-created timestamp; every publication receives a fresh
	 * server publication timestamp. Expiry remains source-derived and bounded.
	 */
	private function apply_server_timestamps( $proposal, $head, $publish, $now ) {
		$created_at = is_array( $head )
			? strtotime( (string) $head['created_at'] . ' UTC' )
			: (int) $now;
		if ( false === $created_at || $created_at < 1 ) {
			$created_at = (int) $now;
		}
		$proposal['created_at']   = gmdate( 'c', $created_at );
		$proposal['published_at'] = $publish ? gmdate( 'c', (int) $now ) : null;
		return $proposal;
	}

	/**
	 * Validate exact identities, optimistic sequence, source binding, and the
	 * operation-specific lifecycle before immutable insertion.
	 */
	private function validate_revision_for_write( $proposal, $sources, $case, $head, $expected_version, $publish, $now ) {
		$forbidden = Tra_Vel_Assisted_Proposal_Policy::reject_forbidden_fields( $proposal );
		if ( is_wp_error( $forbidden ) ) {
			return $forbidden;
		}
		if ( Tra_Vel_Assisted_Proposal_Policy::CONTRACT_VERSION !== ( $proposal['contract_version'] ?? '' ) || ! $this->is_uuid( $proposal['proposal_id'] ?? '' ) || ! $this->is_uuid( $proposal['case_id'] ?? '' ) || ! preg_match( '/^TVP-[A-Z0-9]{8}(?:[A-Z0-9]{4})?$/', (string) ( $proposal['reference'] ?? '' ) ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_identity_invalid', 'The proposal identity contract is invalid.', array( 'status' => 400 ) );
		}
		if ( ! hash_equals( strtolower( (string) $case['case_uuid'] ), strtolower( (string) $proposal['case_id'] ) ) || ! in_array( $proposal['position'] ?? '', Tra_Vel_Assisted_Proposal_Policy::positions(), true ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_case_binding_invalid', 'The proposal is not bound to the verified quote case and position.', array( 'status' => 409 ) );
		}
		$addresses = is_array( $proposal['addresses'] ?? null ) ? $proposal['addresses'] : array();
		if ( (int) ( $addresses['case_revision'] ?? 0 ) !== (int) $case['current_revision'] || ! $this->safe_digest_equals( (string) ( $addresses['request_digest'] ?? '' ), (string) $case['latest_request_digest'] ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_request_changed', 'The proposal request binding is stale.', array( 'status' => 409 ) );
		}

		$source_set_digest = Tra_Vel_Assisted_Proposal_Policy::source_set_digest( $sources );
		$embedded_sources  = is_array( $proposal['sources'] ?? null ) ? $proposal['sources'] : array();
		if ( ! $this->safe_digest_equals( $source_set_digest, (string) ( $proposal['source_set_digest'] ?? '' ) ) || ! $this->safe_digest_equals( $source_set_digest, Tra_Vel_Assisted_Proposal_Policy::source_set_digest( $embedded_sources ) ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_source_set_changed', 'The exact normalized source set is not bound to this revision.', array( 'status' => 409 ) );
		}

		if ( null === $head ) {
			if ( 0 !== $expected_version || 1 !== (int) ( $proposal['version'] ?? 0 ) || 1 !== (int) ( $proposal['revision'] ?? 0 ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_version_conflict', 'A new proposal must begin at version and revision one.', array( 'status' => 409 ) );
			}
		} else {
			if ( (int) $head['proposal_version'] !== $expected_version || (int) ( $proposal['revision'] ?? 0 ) !== (int) $head['current_revision'] + 1 || (int) ( $proposal['version'] ?? 0 ) !== (int) $head['proposal_version'] + 1 ) {
				return new WP_Error( 'tra_vel_assisted_proposal_version_conflict', 'The proposal changed before this revision was appended.', array( 'status' => 409, 'current_version' => (int) $head['proposal_version'] ) );
			}
			$effective_status = Tra_Vel_Assisted_Proposal_Policy::effective_status( (string) $head['status'], (string) $head['expires_at'], $now );
			if ( ! Tra_Vel_Assisted_Proposal_Policy::can_append_revision( $effective_status ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_terminal', 'A terminal or expired proposal requires a new proposal identity.', array( 'status' => 409 ) );
			}
			if ( (int) $head['quote_case_id'] !== (int) $case['id'] || ! hash_equals( strtolower( (string) $head['quote_case_uuid'] ), strtolower( (string) $case['case_uuid'] ) ) || ! hash_equals( (string) $head['reference_code'], (string) $proposal['reference'] ) || ! hash_equals( (string) $head['position'], (string) $proposal['position'] ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_identity_changed', 'Immutable proposal identity fields cannot change across revisions.', array( 'status' => 409 ) );
			}
		}
		if ( (int) ( $proposal['revision'] ?? 0 ) > self::MAX_REVISIONS_PER_PROPOSAL ) {
			return new WP_Error( 'tra_vel_assisted_proposal_revision_limit', 'This proposal has reached its immutable revision limit; create a new proposal option.', array( 'status' => 409 ) );
		}

		if ( $publish ) {
			if ( $this->parse_datetime( $proposal['expires_at'] ?? '' ) >= $this->parse_datetime( $case['retention_until'] ?? '' ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_retention_conflict', 'The proposal freshness window must end before its parent retention window.', array( 'status' => 409 ) );
			}
			$context = array(
				'case_active'    => true,
				'case_revision'  => (int) $case['current_revision'],
				'request_digest' => (string) $case['latest_request_digest'],
			);
			return Tra_Vel_Assisted_Proposal_Policy::validate_publication( $proposal, $sources, $context, $now );
		}

		if ( 'draft' !== ( $proposal['status'] ?? '' ) || 0 !== (int) ( $proposal['published_revision'] ?? -1 ) || null !== ( $proposal['published_at'] ?? null ) || null !== ( $proposal['expires_at'] ?? null ) || 'unavailable' !== ( $proposal['traveler_disposition'] ?? '' ) || ! empty( $proposal['next_actions'] ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_draft_state_invalid', 'A private draft cannot claim publication or traveler action state.', array( 'status' => 400 ) );
		}
		if ( is_array( $head ) && 'draft' !== (string) $head['status'] ) {
			return new WP_Error( 'tra_vel_assisted_proposal_draft_after_publication', 'A published proposal can only advance through another validated publication.', array( 'status' => 409 ) );
		}
		return true;
	}

	/**
	 * Insert a new head. Its first revision is inserted in the same transaction.
	 */
	private function insert_head( $case, $proposal, $snapshot_digest, $source_digest, $actor_user_id, $publish ) {
		global $wpdb;
		$now            = $this->mysql_datetime( $proposal['created_at'] );
		$updated_at     = $publish ? $this->mysql_datetime( $proposal['published_at'] ) : $now;
		$retention      = (string) $case['retention_until'];
		$published_at   = $publish ? $this->mysql_datetime( $proposal['published_at'] ) : null;
		$expires_at     = $publish ? $this->mysql_datetime( $proposal['expires_at'] ) : null;
		$published_no   = $publish ? (int) $proposal['revision'] : 0;
		$published_hash = $publish ? (string) $snapshot_digest : '';
		$inserted       = $wpdb->insert(
			self::proposals_table(),
			array(
				'proposal_uuid'             => (string) $proposal['proposal_id'],
				'reference_code'            => (string) $proposal['reference'],
				'quote_case_id'             => (int) $case['id'],
				'quote_case_uuid'           => (string) $case['case_uuid'],
				'position'                  => (string) $proposal['position'],
				'status'                    => (string) $proposal['status'],
				'traveler_disposition'      => (string) $proposal['traveler_disposition'],
				'proposal_version'          => (int) $proposal['version'],
				'current_revision'          => (int) $proposal['revision'],
				'published_revision'        => $published_no,
				'last_event_sequence'       => 0,
				'source_case_version'       => (int) $case['case_version'],
				'source_case_revision'      => (int) $case['current_revision'],
				'request_digest'            => (string) $case['latest_request_digest'],
				'current_revision_digest'   => (string) $snapshot_digest,
				'published_revision_digest' => $published_hash,
				'current_source_set_digest' => (string) $source_digest,
				'created_by_user_id'        => (int) $actor_user_id,
				'last_actor_user_id'        => (int) $actor_user_id,
				'created_at'                => $now,
				'updated_at'                => $updated_at,
				'published_at'              => $published_at,
				'expires_at'                => $expires_at,
				'retention_until'           => $retention,
				'legal_hold'                => empty( $case['legal_hold'] ) ? 0 : 1,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		if ( false === $inserted ) {
			return new WP_Error( 'tra_vel_assisted_proposal_head_insert_failed', 'The proposal head could not be created atomically.', array( 'status' => 500 ) );
		}
		return array(
			'id'                        => (int) $wpdb->insert_id,
			'proposal_uuid'             => (string) $proposal['proposal_id'],
			'reference_code'            => (string) $proposal['reference'],
			'quote_case_id'             => (int) $case['id'],
			'quote_case_uuid'           => (string) $case['case_uuid'],
			'position'                  => (string) $proposal['position'],
			'status'                    => (string) $proposal['status'],
			'traveler_disposition'      => (string) $proposal['traveler_disposition'],
			'proposal_version'          => (int) $proposal['version'],
			'current_revision'          => (int) $proposal['revision'],
			'published_revision'        => $published_no,
			'last_event_sequence'       => 0,
			'source_case_version'       => (int) $case['case_version'],
			'source_case_revision'      => (int) $case['current_revision'],
			'request_digest'            => (string) $case['latest_request_digest'],
			'current_revision_digest'   => (string) $snapshot_digest,
			'published_revision_digest' => $published_hash,
			'current_source_set_digest' => (string) $source_digest,
			'created_by_user_id'        => (int) $actor_user_id,
			'last_actor_user_id'        => (int) $actor_user_id,
			'created_at'                => $now,
			'updated_at'                => $updated_at,
			'published_at'              => $published_at,
			'expires_at'                => $expires_at,
			'retention_until'           => $retention,
			'legal_hold'                => empty( $case['legal_hold'] ) ? 0 : 1,
		);
	}

	/**
	 * Advance only the mutable head pointer; revision/source rows never update.
	 */
	private function advance_head( $head, $case, $proposal, $snapshot_digest, $source_digest, $actor_user_id, $publish ) {
		global $wpdb;
		$published_no   = $publish ? (int) $proposal['revision'] : 0;
		$published_hash = $publish ? (string) $snapshot_digest : '';
		$retention      = max( strtotime( (string) $head['retention_until'] . ' UTC' ), strtotime( (string) $case['retention_until'] . ' UTC' ) );
		$data           = array(
			'status'                    => (string) $proposal['status'],
			'traveler_disposition'      => (string) $proposal['traveler_disposition'],
			'proposal_version'          => (int) $proposal['version'],
			'current_revision'          => (int) $proposal['revision'],
			'published_revision'        => $published_no,
			'source_case_version'       => (int) $case['case_version'],
			'source_case_revision'      => (int) $case['current_revision'],
			'request_digest'            => (string) $case['latest_request_digest'],
			'current_revision_digest'   => (string) $snapshot_digest,
			'published_revision_digest' => $published_hash,
			'current_source_set_digest' => (string) $source_digest,
			'last_actor_user_id'        => (int) $actor_user_id,
			'updated_at'                => current_time( 'mysql', true ),
			'published_at'              => $publish ? $this->mysql_datetime( $proposal['published_at'] ) : null,
			'expires_at'                => $publish ? $this->mysql_datetime( $proposal['expires_at'] ) : null,
			'retention_until'           => gmdate( 'Y-m-d H:i:s', $retention ),
			'legal_hold'                => ! empty( $head['legal_hold'] ) || ! empty( $case['legal_hold'] ) ? 1 : 0,
		);
		$updated = $wpdb->update(
			self::proposals_table(),
			$data,
			array(
				'id'               => (int) $head['id'],
				'proposal_version' => (int) $head['proposal_version'],
				'current_revision' => (int) $head['current_revision'],
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d' ),
			array( '%d', '%d', '%d' )
		);
		if ( 1 !== (int) $updated ) {
			return new WP_Error( 'tra_vel_assisted_proposal_version_conflict', 'The proposal head changed before it could advance.', array( 'status' => 409 ) );
		}
		return array_merge( $head, $data );
	}

	/**
	 * Insert one immutable revision and its immutable normalized source rows.
	 */
	private function insert_revision_and_sources( $head, $case, $proposal, $sources, $snapshot_digest, $source_digest, $actor_user_id, $publish ) {
		global $wpdb;
		$encoded = $this->canonical_json( $proposal );
		if ( is_wp_error( $encoded ) ) {
			return $encoded;
		}
		$created_at = current_time( 'mysql', true );
		$inserted   = $wpdb->insert(
			self::revisions_table(),
			array(
				'proposal_id'              => (int) $head['id'],
				'revision_no'             => (int) $proposal['revision'],
				'proposal_version'        => (int) $proposal['version'],
				'quote_case_id'           => (int) $case['id'],
				'quote_case_uuid'         => (string) $case['case_uuid'],
				'source_case_version'     => (int) $case['case_version'],
				'source_case_revision'    => (int) $case['current_revision'],
				'request_digest'          => (string) $case['latest_request_digest'],
				'snapshot_digest'         => (string) $snapshot_digest,
				'source_set_digest'       => (string) $source_digest,
				'source_count'            => count( $sources ),
				'proposal_snapshot'       => $encoded,
				'publication_validated'   => $publish ? 1 : 0,
				'actor_user_id'           => (int) $actor_user_id,
				'created_at'              => $created_at,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s' )
		);
		if ( false === $inserted ) {
			return new WP_Error( 'tra_vel_assisted_proposal_revision_insert_failed', 'The immutable proposal revision could not be stored.', array( 'status' => 500 ) );
		}
		$revision_id = (int) $wpdb->insert_id;

		foreach ( $sources as $source ) {
			$source_json = $this->canonical_json( $source );
			if ( is_wp_error( $source_json ) ) {
				return $source_json;
			}
			$source_row_digest = Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $source );
			$source_inserted   = $wpdb->insert(
				self::sources_table(),
				array(
					'proposal_id'          => (int) $head['id'],
					'proposal_revision_id' => $revision_id,
					'revision_no'          => (int) $proposal['revision'],
					'source_uuid'          => (string) $source['source_id'],
					'provider_code'        => (string) $source['provider_code'],
					'source_type'          => (string) $source['source_type'],
					'relationship'         => (string) $source['relationship'],
					'public_label'         => (string) $source['public_label'],
					'supplier_name'        => (string) $source['supplier_name'],
					'seller_name'          => (string) $source['seller_name'],
					'source_reference'     => (string) $source['source_reference'],
					'source_url'           => null === $source['source_url'] ? '' : (string) $source['source_url'],
					'observed_at'          => $this->mysql_datetime( $source['observed_at'] ),
					'fresh_until'          => $this->mysql_datetime( $source['fresh_until'] ),
					'evidence_digest'      => (string) $source['evidence_digest'],
					'source_digest'        => $source_row_digest,
					'source_snapshot'      => $source_json,
					'created_at'           => $created_at,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( false === $source_inserted ) {
				return new WP_Error( 'tra_vel_assisted_proposal_source_insert_failed', 'The immutable proposal evidence set could not be stored.', array( 'status' => 500 ) );
			}
		}

		return array(
			'id'                    => $revision_id,
			'revision'              => (int) $proposal['revision'],
			'proposal_version'      => (int) $proposal['version'],
			'snapshot_digest'       => (string) $snapshot_digest,
			'source_set_digest'     => (string) $source_digest,
			'source_count'          => count( $sources ),
			'publication_validated' => (bool) $publish,
			'created_at'            => gmdate( 'c', strtotime( $created_at . ' UTC' ) ),
		);
	}

	/**
	 * Append one immutable action event. Event rows are never updated.
	 */
	private function insert_event( $proposal_id, $sequence, $version, $event_type, $action, $from_status, $to_status, $from_disposition, $to_disposition, $actor_type, $actor_user_id, $principal_hash, $payload ) {
		global $wpdb;
		$encoded = $this->canonical_json( $payload );
		if ( is_wp_error( $encoded ) ) {
			return $encoded;
		}
		$event_uuid = wp_generate_uuid4();
		$inserted   = $wpdb->insert(
			self::events_table(),
			array(
				'proposal_id'       => absint( $proposal_id ),
				'sequence_no'       => absint( $sequence ),
				'proposal_version'  => absint( $version ),
				'event_uuid'        => $event_uuid,
				'event_type'        => substr( preg_replace( '/[^a-z0-9._-]/', '', strtolower( (string) $event_type ) ), 0, 64 ),
				'action_code'       => sanitize_key( $action ),
				'from_status'       => sanitize_key( $from_status ),
				'to_status'         => sanitize_key( $to_status ),
				'from_disposition'  => sanitize_key( $from_disposition ),
				'to_disposition'    => sanitize_key( $to_disposition ),
				'actor_type'        => sanitize_key( $actor_type ),
				'actor_user_id'     => absint( $actor_user_id ),
				'principal_hash'    => (string) $principal_hash,
				'payload'           => $encoded,
				'payload_digest'    => hash( 'sha256', $encoded ),
				'created_at'        => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			return new WP_Error( 'tra_vel_assisted_proposal_event_insert_failed', 'The proposal action event could not be appended.', array( 'status' => 500 ) );
		}
		return array(
			'contract_version' => '1.0.0',
			'event_id'         => $event_uuid,
			'proposal_id'      => absint( $proposal_id ),
			'sequence'         => absint( $sequence ),
			'proposal_version' => absint( $version ),
			'type'             => (string) $event_type,
			'action'           => (string) $action,
			'from_status'      => (string) $from_status,
			'to_status'        => (string) $to_status,
			'from_disposition' => (string) $from_disposition,
			'to_disposition'   => (string) $to_disposition,
			'actor_type'       => (string) $actor_type,
			'data'             => $payload,
			'occurred_at'      => gmdate( 'c' ),
		);
	}

	/**
	 * Read one immutable action event by UUID.
	 */
	public function get_event_by_uuid( $event_uuid ) {
		$row = $this->get_event_row_by_uuid( $event_uuid );
		return is_array( $row ) ? $this->hydrate_event( $row ) : $row;
	}

	/**
	 * Authorize a follow-up dispatch only while the event-bound account address
	 * is still the exact current address for the same traveler account.
	 */
	public function validate_contact_dispatch_target( $event_uuid, $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_target_unverified', 'Contact dispatch requires an authenticated account target.', array( 'status' => 409 ) );
		}
		$row = $this->get_event_row_by_uuid( $event_uuid );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_event_missing', 'The contact authorization event was not found.', array( 'status' => 404 ) );
		}
		$event = $this->hydrate_event( $row );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		if ( 'authorize_contact' !== (string) $row['action_code'] || 'traveler' !== (string) $row['actor_type'] || $user_id !== absint( $row['actor_user_id'] ?? 0 ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_event_forbidden', 'The contact authorization event does not belong to this account.', array( 'status' => 403 ) );
		}
		$stored_consent = $this->normalize_stored_contact_consent( $event['data']['contact_consent'] ?? null, $user_id );
		if ( is_wp_error( $stored_consent ) ) {
			return $stored_consent;
		}
		$current_digest = $this->current_account_email_digest( $user_id );
		if ( is_wp_error( $current_digest ) ) {
			return $current_digest;
		}
		if ( ! $this->safe_digest_equals( (string) $stored_consent['contact_target_digest'], $current_digest ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_target_changed', 'The account email changed after consent. New contact authorization is required.', array( 'status' => 409 ) );
		}
		return true;
	}

	/**
	 * Read the raw immutable event row for integrity-sensitive internal checks.
	 */
	private function get_event_row_by_uuid( $event_uuid ) {
		global $wpdb;
		if ( ! $this->is_uuid( $event_uuid ) ) {
			return null;
		}
		$row = $this->read_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::events_table() . ' WHERE event_uuid = %s LIMIT 1', (string) $event_uuid ),
			'tra_vel_assisted_proposal_event_read_failed',
			'The proposal action event could not be read safely.'
		);
		return $row;
	}

	/**
	 * Lock and compare the exact parent row previously authorized by a controller.
	 */
	private function lock_verified_parent_case( $verified_case, $require_active = true ) {
		global $wpdb;
		$row = $this->read_row(
			$wpdb->prepare(
				'SELECT id,case_uuid,status,case_version,current_revision,latest_request_digest,owner_user_id,owner_token_hash,assigned_user_id,retention_until,legal_hold FROM ' . self::quote_cases_table() . ' WHERE id = %d AND case_uuid = %s LIMIT 1 FOR UPDATE',
				(int) $verified_case['id'],
				(string) $verified_case['case_uuid']
			),
			'tra_vel_assisted_proposal_parent_read_failed',
			'The verified quote case could not be locked safely.'
		);
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_missing', 'The verified quote case no longer exists.', array( 'status' => 409 ) );
		}
		$actual = $this->normalize_verified_case( $row, $require_active );
		if ( is_wp_error( $actual ) ) {
			return $actual;
		}
		if ( Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $actual ) !== Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $verified_case ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_changed', 'The quote case changed after operator verification.', array( 'status' => 409, 'current_case_version' => (int) $actual['case_version'] ) );
		}
		return $actual;
	}

	/**
	 * Normalize the closed verified-case subset used by this aggregate.
	 */
	private function normalize_verified_case( $case, $require_active = true ) {
		if ( ! is_array( $case ) || absint( $case['id'] ?? 0 ) < 1 || ! $this->is_uuid( $case['case_uuid'] ?? '' ) || absint( $case['case_version'] ?? 0 ) < 1 || absint( $case['current_revision'] ?? 0 ) < 1 || ! $this->is_sha256( $case['latest_request_digest'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_invalid', 'A complete verified quote-case identity and request binding are required.', array( 'status' => 409 ) );
		}
		$owner_user_id    = absint( $case['owner_user_id'] ?? 0 );
		$owner_token_hash = (string) ( $case['owner_token_hash'] ?? '' );
		if ( $owner_user_id < 1 && ! $this->is_sha256( $owner_token_hash ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_owner_invalid', 'The verified quote case has no durable owner binding.', array( 'status' => 409 ) );
		}
		$status = (string) ( $case['status'] ?? '' );
		$retained_statuses = array( 'queued', 'in_review', 'needs_information', 'ready_for_assistance', 'closed_no_quote', 'cancelled', 'expired' );
		if ( ! in_array( $status, $retained_statuses, true ) || ( $require_active && ! $this->case_is_active_status( $status ) ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_inactive', 'The parent quote case is not active.', array( 'status' => 409 ) );
		}
		$retention_timestamp = $this->parse_datetime( $case['retention_until'] ?? '' );
		if ( $retention_timestamp <= time() ) {
			return new WP_Error( 'tra_vel_assisted_proposal_retention_invalid', 'The parent quote case has no active retention window.', array( 'status' => 409 ) );
		}
		return array(
			'id'                    => absint( $case['id'] ),
			'case_uuid'             => strtolower( (string) $case['case_uuid'] ),
			'status'                => $status,
			'case_version'          => absint( $case['case_version'] ),
			'current_revision'      => absint( $case['current_revision'] ),
			'latest_request_digest' => (string) $case['latest_request_digest'],
			'owner_user_id'         => $owner_user_id,
			'owner_token_hash'      => $owner_user_id > 0 ? '' : $owner_token_hash,
			'assigned_user_id'      => absint( $case['assigned_user_id'] ?? 0 ),
			'retention_until'       => gmdate( 'Y-m-d H:i:s', $retention_timestamp ),
			'legal_hold'            => empty( $case['legal_hold'] ) ? 0 : 1,
		);
	}

	private function case_is_active_status( $status ) {
		return in_array( (string) $status, array( 'queued', 'in_review', 'needs_information', 'ready_for_assistance' ), true );
	}

	/**
	 * Project the full proposal into the closed public schema before digesting or
	 * writing it. A canonical comparison rejects every unknown nested field,
	 * including raw supplier evidence, prompts, traces, or arbitrary metadata.
	 */
	private function normalize_proposal( $proposal ) {
		if ( ! is_array( $proposal ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_snapshot_invalid', 'A typed proposal snapshot is required.', array( 'status' => 400 ) );
		}
		$text = function ( $value ) {
			return is_string( $value ) ? sanitize_text_field( $value ) : '';
		};
		$text_list = function ( $values ) use ( $text ) {
			if ( ! is_array( $values ) ) {
				return array();
			}
			return array_values( array_map( $text, $values ) );
		};
		$routes = array();
		foreach ( is_array( $proposal['route']['legs'] ?? null ) ? $proposal['route']['legs'] : array() as $leg ) {
			$leg = is_array( $leg ) ? $leg : array();
			$routes[] = array(
				'sequence' => is_int( $leg['sequence'] ?? null ) ? $leg['sequence'] : 0,
				'from'     => $text( $leg['from'] ?? null ),
				'to'       => $text( $leg['to'] ?? null ),
				'mode'     => $text( $leg['mode'] ?? null ),
			);
		}
		$itinerary = array();
		foreach ( is_array( $proposal['itinerary'] ?? null ) ? $proposal['itinerary'] : array() as $day ) {
			$day = is_array( $day ) ? $day : array();
			$itinerary[] = array(
				'day'            => is_int( $day['day'] ?? null ) ? $day['day'] : 0,
				'place'          => $text( $day['place'] ?? null ),
				'title'          => $text( $day['title'] ?? null ),
				'component_keys' => $text_list( $day['component_keys'] ?? null ),
			);
		}
		$components = array();
		foreach ( is_array( $proposal['components'] ?? null ) ? $proposal['components'] : array() as $component ) {
			$component  = is_array( $component ) ? $component : array();
			$price      = is_array( $component['price'] ?? null ) ? $component['price'] : array();
			$conditions = is_array( $component['conditions'] ?? null ) ? $component['conditions'] : array();
			$components[] = array(
				'component_key'        => $text( $component['component_key'] ?? null ),
				'category'             => $text( $component['category'] ?? null ),
				'title'                => $text( $component['title'] ?? null ),
				'description'          => $text( $component['description'] ?? null ),
				'price'                => array(
					'priced'                => true === ( $price['priced'] ?? null ),
					'total_for_party_minor' => is_int( $price['total_for_party_minor'] ?? null ) ? $price['total_for_party_minor'] : null,
					'currency'              => null === ( $price['currency'] ?? null ) ? null : $text( $price['currency'] ),
					'basis'                 => $text( $price['basis'] ?? null ),
					'taxes'                 => $text( $price['taxes'] ?? null ),
					'fees'                  => $text( $price['fees'] ?? null ),
				),
				'conditions'           => array(
					'cancellation'         => $text( $conditions['cancellation'] ?? null ),
					'changes'              => $text( $conditions['changes'] ?? null ),
					'baggage_or_inclusions'=> $text( $conditions['baggage_or_inclusions'] ?? null ),
				),
				'source_ids'           => $text_list( $component['source_ids'] ?? null ),
				'requires_revalidation'=> true === ( $component['requires_revalidation'] ?? null ),
			);
		}
		$embedded_sources = array();
		foreach ( is_array( $proposal['sources'] ?? null ) ? $proposal['sources'] : array() as $source ) {
			$projected_source = $this->project_source_shape( $source );
			if ( is_wp_error( $projected_source ) ) {
				return $projected_source;
			}
			$embedded_sources[] = $projected_source;
		}
		$unresolved = array();
		foreach ( is_array( $proposal['unresolved_items'] ?? null ) ? $proposal['unresolved_items'] : array() as $item ) {
			$item = is_array( $item ) ? $item : array();
			$unresolved[] = array(
				'code'  => $text( $item['code'] ?? null ),
				'label' => $text( $item['label'] ?? null ),
			);
		}
		$ledger     = is_array( $proposal['ledger'] ?? null ) ? $proposal['ledger'] : array();
		$freshness  = is_array( $proposal['freshness'] ?? null ) ? $proposal['freshness'] : array();
		$disclosure = is_array( $proposal['disclosure'] ?? null ) ? $proposal['disclosure'] : array();
		$addresses  = is_array( $proposal['addresses'] ?? null ) ? $proposal['addresses'] : array();
		$route      = is_array( $proposal['route'] ?? null ) ? $proposal['route'] : array();
		$projected  = array(
			'contract_version'      => $text( $proposal['contract_version'] ?? null ),
			'proposal_id'          => $text( $proposal['proposal_id'] ?? null ),
			'case_id'              => $text( $proposal['case_id'] ?? null ),
			'reference'            => $text( $proposal['reference'] ?? null ),
			'status'               => $text( $proposal['status'] ?? null ),
			'version'              => is_int( $proposal['version'] ?? null ) ? $proposal['version'] : 0,
			'revision'             => is_int( $proposal['revision'] ?? null ) ? $proposal['revision'] : 0,
			'published_revision'   => is_int( $proposal['published_revision'] ?? null ) ? $proposal['published_revision'] : -1,
			'position'             => $text( $proposal['position'] ?? null ),
			'addresses'            => array(
				'case_revision' => is_int( $addresses['case_revision'] ?? null ) ? $addresses['case_revision'] : 0,
				'request_digest'=> $text( $addresses['request_digest'] ?? null ),
			),
			'title'                 => $text( $proposal['title'] ?? null ),
			'summary'               => $text( $proposal['summary'] ?? null ),
			'why_it_fits'           => $text_list( $proposal['why_it_fits'] ?? null ),
			'trade_offs'            => $text_list( $proposal['trade_offs'] ?? null ),
			'route'                 => array(
				'origin'       => $text( $route['origin'] ?? null ),
				'destinations' => $text_list( $route['destinations'] ?? null ),
				'legs'         => $routes,
			),
			'itinerary'             => $itinerary,
			'components'            => $components,
			'ledger'                => array(
				'contract_version'        => $text( $ledger['contract_version'] ?? null ),
				'currency'                => null === ( $ledger['currency'] ?? null ) ? null : $text( $ledger['currency'] ),
				'priced_total_minor'      => is_int( $ledger['priced_total_minor'] ?? null ) ? $ledger['priced_total_minor'] : -1,
				'priced_component_count'  => is_int( $ledger['priced_component_count'] ?? null ) ? $ledger['priced_component_count'] : -1,
				'unpriced_component_keys' => $text_list( $ledger['unpriced_component_keys'] ?? null ),
				'complete_pricing'        => true === ( $ledger['complete_pricing'] ?? null ),
				'calculation_digest'      => $text( $ledger['calculation_digest'] ?? null ),
			),
			'sources'               => $embedded_sources,
			'source_set_digest'     => $text( $proposal['source_set_digest'] ?? null ),
			'freshness'             => array(
				'checked_at'            => $text( $freshness['checked_at'] ?? null ),
				'expires_at'            => $text( $freshness['expires_at'] ?? null ),
				'requires_revalidation' => true === ( $freshness['requires_revalidation'] ?? null ),
			),
			'unresolved_items'      => $unresolved,
			'traveler_disposition' => $text( $proposal['traveler_disposition'] ?? null ),
			'next_actions'         => $text_list( $proposal['next_actions'] ?? null ),
			'disclosure'           => array(
				'commercial_state'   => $text( $disclosure['commercial_state'] ?? null ),
				'final_quote_required'=> true === ( $disclosure['final_quote_required'] ?? null ),
				'message'            => $text( $disclosure['message'] ?? null ),
			),
			'created_at'            => $text( $proposal['created_at'] ?? null ),
			'published_at'          => null === ( $proposal['published_at'] ?? null ) ? null : $text( $proposal['published_at'] ),
			'expires_at'            => null === ( $proposal['expires_at'] ?? null ) ? null : $text( $proposal['expires_at'] ),
		);
		if ( Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $proposal ) !== Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $projected ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_not_normalized', 'The proposal contains unknown, unsafe, or non-canonical fields.', array( 'status' => 400 ) );
		}
		$bounds_valid = count( $projected['why_it_fits'] ) >= 1
			&& count( $projected['why_it_fits'] ) <= 6
			&& count( $projected['trade_offs'] ) >= 1
			&& count( $projected['trade_offs'] ) <= 6
			&& count( $projected['route']['destinations'] ) >= 1
			&& count( $projected['route']['destinations'] ) <= 8
			&& count( $projected['route']['legs'] ) <= 12
			&& count( $projected['itinerary'] ) >= 1
			&& count( $projected['itinerary'] ) <= 31
			&& count( $projected['components'] ) >= 1
			&& count( $projected['components'] ) <= 16
			&& count( $projected['sources'] ) >= 1
			&& count( $projected['sources'] ) <= 32
			&& count( $projected['unresolved_items'] ) <= 16
			&& count( $projected['next_actions'] ) <= 4;
		foreach ( $projected['components'] as $component ) {
			$bounds_valid = $bounds_valid && count( $component['source_ids'] ) >= 1 && count( $component['source_ids'] ) <= 8;
		}
		if ( ! $bounds_valid ) {
			return new WP_Error( 'tra_vel_assisted_proposal_collection_bounds_invalid', 'Proposal collections exceed the closed bounded contract.', array( 'status' => 400 ) );
		}
		$encoded = $this->canonical_json( $projected );
		if ( is_wp_error( $encoded ) ) {
			return $encoded;
		}
		if ( strlen( $encoded ) > self::MAX_SNAPSHOT_BYTES ) {
			return new WP_Error( 'tra_vel_assisted_proposal_snapshot_too_large', 'The proposal snapshot exceeds its durable storage boundary.', array( 'status' => 413 ) );
		}
		return $projected;
	}

	/**
	 * Project one source into the exact source schema shape.
	 */
	private function project_source_shape( $source ) {
		if ( ! is_array( $source ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_source_shape_invalid', 'Evidence must match the closed source contract exactly.', array( 'status' => 400 ) );
		}
		$required = array( 'contract_version', 'source_id', 'provider_code', 'source_type', 'relationship', 'public_label', 'supplier_name', 'seller_name', 'source_reference', 'source_url', 'observed_at', 'fresh_until', 'evidence_digest', 'requires_revalidation' );
		if ( array_diff( $required, array_keys( $source ) ) || array_diff( array_keys( $source ), $required ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_source_shape_invalid', 'Evidence must match the closed source contract exactly.', array( 'status' => 400 ) );
		}
		return array(
			'contract_version'      => is_string( $source['contract_version'] ) ? $source['contract_version'] : '',
			'source_id'             => is_string( $source['source_id'] ) ? strtolower( $source['source_id'] ) : '',
			'provider_code'         => is_string( $source['provider_code'] ) ? $source['provider_code'] : '',
			'source_type'           => is_string( $source['source_type'] ) ? $source['source_type'] : '',
			'relationship'          => is_string( $source['relationship'] ) ? $source['relationship'] : '',
			'public_label'          => is_string( $source['public_label'] ) ? sanitize_text_field( $source['public_label'] ) : '',
			'supplier_name'         => is_string( $source['supplier_name'] ) ? sanitize_text_field( $source['supplier_name'] ) : '',
			'seller_name'           => is_string( $source['seller_name'] ) ? sanitize_text_field( $source['seller_name'] ) : '',
			'source_reference'      => is_string( $source['source_reference'] ) ? $source['source_reference'] : '',
			'source_url'            => null === $source['source_url'] ? null : ( is_string( $source['source_url'] ) ? $source['source_url'] : '' ),
			'observed_at'           => is_string( $source['observed_at'] ) ? $source['observed_at'] : '',
			'fresh_until'           => is_string( $source['fresh_until'] ) ? $source['fresh_until'] : '',
			'evidence_digest'       => is_string( $source['evidence_digest'] ) ? $source['evidence_digest'] : '',
			'requires_revalidation' => true === $source['requires_revalidation'],
		);
	}

	/**
	 * Require the exact closed source object and normalize it before storage.
	 */
	private function normalize_sources( $sources, $now ) {
		if ( ! is_array( $sources ) || count( $sources ) < 1 || count( $sources ) > 32 ) {
			return new WP_Error( 'tra_vel_assisted_proposal_sources_invalid', 'Between one and 32 exact evidence sources are required.', array( 'status' => 400 ) );
		}
		$normalized = array();
		$seen       = array();
		foreach ( array_values( $sources ) as $source ) {
			$item = $this->project_source_shape( $source );
			if ( is_wp_error( $item ) ) {
				return $item;
			}
			if ( Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $source ) !== Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $item ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_source_not_normalized', 'Evidence contains non-canonical or unsafe values.', array( 'status' => 400 ) );
			}
			if ( '' === $item['public_label'] || strlen( $item['public_label'] ) > 190 || strlen( $item['supplier_name'] ) > 190 || strlen( $item['seller_name'] ) > 190 || strlen( $item['source_reference'] ) > 190 || ( is_string( $item['source_url'] ) && strlen( $item['source_url'] ) > 500 ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_source_bounds_invalid', 'Evidence labels and references exceed their storage boundary.', array( 'status' => 400 ) );
			}
			$valid = Tra_Vel_Assisted_Proposal_Policy::validate_source( $item, $now );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
			if ( isset( $seen[ $item['source_id'] ] ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_source_duplicate', 'Evidence source identifiers must be unique.', array( 'status' => 409 ) );
			}
			$seen[ $item['source_id'] ] = true;
			$normalized[]               = $item;
		}
		usort(
			$normalized,
			static function ( $left, $right ) {
				return strcmp( (string) $left['source_id'], (string) $right['source_id'] );
			}
		);
		return $normalized;
	}

	/**
	 * Lock an existing proposal head for optimistic mutation.
	 */
	private function get_head_for_update( $proposal_uuid ) {
		global $wpdb;
		if ( ! $this->is_uuid( $proposal_uuid ) ) {
			return null;
		}
		$row = $this->read_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::proposals_table() . ' WHERE proposal_uuid = %s LIMIT 1 FOR UPDATE', (string) $proposal_uuid ),
			'tra_vel_assisted_proposal_lock_failed',
			'The proposal head could not be locked safely.'
		);
		return is_array( $row ) ? $this->hydrate_head( $row ) : $row;
	}

	/**
	 * Count retained heads while the parent case row is already locked. The
	 * parent lock serializes all creates for one case, making the cap deterministic.
	 */
	private function count_case_heads( $case ) {
		global $wpdb;
		$count = $this->read_scalar(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::proposals_table() . ' WHERE quote_case_id = %d AND quote_case_uuid = %s',
				(int) $case['id'],
				(string) $case['case_uuid']
			),
			'tra_vel_assisted_proposal_case_count_failed',
			'Proposal option limits could not be checked safely.'
		);
		return is_wp_error( $count ) ? $count : (int) $count;
	}

	/**
	 * Load one immutable revision and verify every stored digest and binding.
	 */
	private function load_revision_bundle( $head, $revision_no ) {
		global $wpdb;
		$row = $this->read_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::revisions_table() . ' WHERE proposal_id = %d AND revision_no = %d LIMIT 1', (int) $head['id'], absint( $revision_no ) ),
			'tra_vel_assisted_proposal_revision_read_failed',
			'The immutable proposal revision could not be read safely.'
		);
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( ! is_array( $row ) ) {
			return null;
		}
		$proposal = json_decode( (string) $row['proposal_snapshot'], true );
		$addresses = is_array( $proposal['addresses'] ?? null ) ? $proposal['addresses'] : array();
		if ( ! is_array( $proposal ) || ! $this->safe_digest_equals( (string) $row['snapshot_digest'], Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $proposal ) ) || (int) ( $proposal['revision'] ?? 0 ) !== (int) $row['revision_no'] || (int) ( $proposal['version'] ?? 0 ) !== (int) $row['proposal_version'] || ! hash_equals( strtolower( (string) $head['proposal_uuid'] ), strtolower( (string) ( $proposal['proposal_id'] ?? '' ) ) ) || (int) $row['quote_case_id'] !== (int) $head['quote_case_id'] || ! hash_equals( strtolower( (string) $row['quote_case_uuid'] ), strtolower( (string) $head['quote_case_uuid'] ) ) || ! hash_equals( strtolower( (string) ( $proposal['case_id'] ?? '' ) ), strtolower( (string) $row['quote_case_uuid'] ) ) || (int) ( $addresses['case_revision'] ?? 0 ) !== (int) $row['source_case_revision'] || ! $this->safe_digest_equals( (string) ( $addresses['request_digest'] ?? '' ), (string) $row['request_digest'] ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_revision_integrity_failed', 'The immutable proposal revision failed its integrity check.', array( 'status' => 500 ) );
		}

		$source_rows = $this->read_rows(
			$wpdb->prepare( 'SELECT * FROM ' . self::sources_table() . ' WHERE proposal_id = %d AND revision_no = %d ORDER BY source_uuid ASC', (int) $head['id'], absint( $revision_no ) ),
			'tra_vel_assisted_proposal_source_read_failed',
			'The immutable proposal evidence could not be read safely.'
		);
		if ( is_wp_error( $source_rows ) ) {
			return $source_rows;
		}
		if ( count( $source_rows ) !== (int) $row['source_count'] ) {
			return new WP_Error( 'tra_vel_assisted_proposal_source_integrity_failed', 'The immutable proposal evidence set is incomplete.', array( 'status' => 500 ) );
		}
		$sources = array();
		foreach ( $source_rows as $source_row ) {
			$source = json_decode( (string) $source_row['source_snapshot'], true );
			$stored_source_url = (string) $source_row['source_url'];
			$source_url        = null === ( $source['source_url'] ?? null ) ? '' : (string) $source['source_url'];
			if ( ! is_array( $source ) || ! $this->safe_digest_equals( (string) $source_row['source_digest'], Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $source ) ) || ! hash_equals( strtolower( (string) $source_row['source_uuid'] ), strtolower( (string) ( $source['source_id'] ?? '' ) ) ) || ! $this->safe_digest_equals( (string) $source_row['evidence_digest'], (string) ( $source['evidence_digest'] ?? '' ) ) || ! hash_equals( (string) $source_row['provider_code'], (string) ( $source['provider_code'] ?? '' ) ) || ! hash_equals( (string) $source_row['source_type'], (string) ( $source['source_type'] ?? '' ) ) || ! hash_equals( (string) $source_row['relationship'], (string) ( $source['relationship'] ?? '' ) ) || ! hash_equals( (string) $source_row['public_label'], (string) ( $source['public_label'] ?? '' ) ) || ! hash_equals( (string) $source_row['supplier_name'], (string) ( $source['supplier_name'] ?? '' ) ) || ! hash_equals( (string) $source_row['seller_name'], (string) ( $source['seller_name'] ?? '' ) ) || ! hash_equals( (string) $source_row['source_reference'], (string) ( $source['source_reference'] ?? '' ) ) || ! hash_equals( $stored_source_url, $source_url ) || ! hash_equals( (string) $source_row['observed_at'], $this->mysql_datetime( $source['observed_at'] ?? '' ) ) || ! hash_equals( (string) $source_row['fresh_until'], $this->mysql_datetime( $source['fresh_until'] ?? '' ) ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_source_integrity_failed', 'An immutable proposal evidence row failed its integrity check.', array( 'status' => 500 ) );
			}
			$sources[] = $source;
		}
		$source_set_digest = Tra_Vel_Assisted_Proposal_Policy::source_set_digest( $sources );
		if ( ! $this->safe_digest_equals( (string) $row['source_set_digest'], $source_set_digest ) || ! $this->safe_digest_equals( (string) ( $proposal['source_set_digest'] ?? '' ), $source_set_digest ) || ! $this->safe_digest_equals( Tra_Vel_Assisted_Proposal_Policy::source_set_digest( $proposal['sources'] ?? array() ), $source_set_digest ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_source_set_integrity_failed', 'The proposal and evidence-set digests no longer agree.', array( 'status' => 500 ) );
		}

		$revision_snapshot = $proposal;
		$proposal          = $this->project_effective_proposal( $revision_snapshot, $head );
		return array(
			'head'              => $head,
			'proposal'          => $proposal,
			'revision_snapshot' => $revision_snapshot,
			'sources'           => $sources,
			'revision_metadata' => array(
				'id'                    => (int) $row['id'],
				'revision'              => (int) $row['revision_no'],
				'proposal_version'      => (int) $row['proposal_version'],
				'snapshot_digest'       => (string) $row['snapshot_digest'],
				'source_set_digest'     => (string) $row['source_set_digest'],
				'source_count'          => (int) $row['source_count'],
				'publication_validated' => 1 === (int) $row['publication_validated'],
				'case_version'          => (int) $row['source_case_version'],
				'case_revision'         => (int) $row['source_case_revision'],
				'request_digest'        => (string) $row['request_digest'],
				'created_at'            => gmdate( 'c', strtotime( (string) $row['created_at'] . ' UTC' ) ),
			),
			'event'             => null,
			'replayed'          => false,
		);
	}

	/**
	 * Resolve an exact principal-scoped mutation receipt.
	 */
	private function idempotent_result( $scope, $principal_hash, $key, $request_digest, $for_update ) {
		global $wpdb;
		$key_hash = hash( 'sha256', $this->sanitize_idempotency_key( $key ) );
		$row      = $this->read_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::idempotency_table() . ' WHERE operation_scope = %s AND principal_hash = %s AND idempotency_key_hash = %s AND expires_at > %s LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ),
				$this->sanitize_operation_scope( $scope ),
				(string) $principal_hash,
				$key_hash,
				current_time( 'mysql', true )
			),
			'tra_vel_assisted_proposal_idempotency_read_failed',
			'Proposal idempotency storage could not be read safely.'
		);
		if ( is_wp_error( $row ) || null === $row ) {
			return $row;
		}
		if ( ! $this->safe_digest_equals( (string) $row['request_digest'], (string) $request_digest ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_idempotency_conflict', 'This idempotency key was already used for different canonical proposal data.', array( 'status' => 409 ) );
		}
		$bundle = $this->get_revision_bundle( (string) $row['proposal_uuid'], (int) $row['revision_no'] );
		if ( is_wp_error( $bundle ) ) {
			return $bundle;
		}
		if ( ! is_array( $bundle ) || ! $this->safe_digest_equals( (string) $row['revision_digest'], (string) $bundle['revision_metadata']['snapshot_digest'] ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_replay_unavailable', 'The prior proposal result cannot be replayed safely.', array( 'status' => 409 ) );
		}
		$event_uuid = (string) ( $row['event_uuid'] ?? '' );
		if ( '' !== $event_uuid ) {
			$event = $this->get_event_by_uuid( $event_uuid );
			if ( is_wp_error( $event ) ) {
				return $event;
			}
			if ( ! is_array( $event ) || (int) $event['proposal_version'] !== (int) $row['proposal_version'] || (int) $event['proposal_id'] !== (int) $bundle['head']['id'] ) {
				return new WP_Error( 'tra_vel_assisted_proposal_replay_unavailable', 'The prior proposal action event cannot be replayed safely.', array( 'status' => 409 ) );
			}
			$replay_head                         = $bundle['head'];
			$replay_head['stored_status']        = (string) $event['to_status'];
			$replay_head['status']               = Tra_Vel_Assisted_Proposal_Policy::effective_status( (string) $event['to_status'], (string) ( $bundle['revision_snapshot']['expires_at'] ?? '' ) );
			$replay_head['stored_traveler_disposition'] = (string) $event['to_disposition'];
			$replay_head['traveler_disposition'] = 'available' === $replay_head['status'] ? (string) $event['to_disposition'] : 'unavailable';
			$replay_head['proposal_version']     = (int) $event['proposal_version'];
			$replay_head['current_revision']     = (int) $row['revision_no'];
			$replay_head['published_revision']   = (int) ( $bundle['revision_snapshot']['published_revision'] ?? 0 );
			$replay_head['current_revision_digest'] = (string) $row['revision_digest'];
			$replay_head['published_revision_digest'] = (int) $replay_head['published_revision'] > 0 ? (string) $row['revision_digest'] : '';
			$replay_head['current_source_set_digest'] = (string) $bundle['revision_metadata']['source_set_digest'];
			$replay_head['source_case_version']  = (int) $bundle['revision_metadata']['case_version'];
			$replay_head['source_case_revision'] = (int) $bundle['revision_metadata']['case_revision'];
			$replay_head['request_digest']       = (string) $bundle['revision_metadata']['request_digest'];
			$replay_head['last_event_sequence']  = (int) $event['sequence'];
			$replay_head['next_actions']         = $this->next_actions_for( (string) $replay_head['status'], (string) $replay_head['traveler_disposition'] );
			$bundle['head']                      = $replay_head;
			$bundle['proposal']                  = $this->project_effective_proposal( $bundle['revision_snapshot'], $replay_head );
			$bundle['event'] = $event;
		} else {
			$snapshot                              = $bundle['revision_snapshot'];
			$replay_head                           = $bundle['head'];
			$replay_head['stored_status']          = (string) ( $snapshot['status'] ?? 'draft' );
			$replay_head['status']                 = Tra_Vel_Assisted_Proposal_Policy::effective_status( (string) $replay_head['stored_status'], (string) ( $snapshot['expires_at'] ?? '' ) );
			$replay_head['stored_traveler_disposition'] = (string) ( $snapshot['traveler_disposition'] ?? 'unavailable' );
			$replay_head['traveler_disposition']   = 'available' === $replay_head['status'] ? $replay_head['stored_traveler_disposition'] : 'unavailable';
			$replay_head['proposal_version']       = (int) $row['proposal_version'];
			$replay_head['current_revision']       = (int) $row['revision_no'];
			$replay_head['published_revision']     = (int) ( $snapshot['published_revision'] ?? 0 );
			$replay_head['current_revision_digest']= (string) $row['revision_digest'];
			$replay_head['published_revision_digest'] = (int) $replay_head['published_revision'] > 0 ? (string) $row['revision_digest'] : '';
			$replay_head['current_source_set_digest'] = (string) $bundle['revision_metadata']['source_set_digest'];
			$replay_head['source_case_version']       = (int) $bundle['revision_metadata']['case_version'];
			$replay_head['source_case_revision']      = (int) $bundle['revision_metadata']['case_revision'];
			$replay_head['request_digest']            = (string) $bundle['revision_metadata']['request_digest'];
			$replay_head['last_event_sequence']    = 0;
			$replay_head['next_actions']           = $this->next_actions_for( (string) $replay_head['status'], (string) $replay_head['traveler_disposition'] );
			$bundle['head']                        = $replay_head;
			$bundle['proposal']                    = $this->project_effective_proposal( $snapshot, $replay_head );
		}
		return $bundle;
	}

	/**
	 * Insert one principal-scoped idempotency receipt.
	 */
	private function insert_idempotency( $scope, $principal_hash, $key, $request_digest, $proposal_uuid, $version, $revision, $revision_digest, $event_uuid, $response_code ) {
		global $wpdb;
		$key = $this->sanitize_idempotency_key( $key );
		if ( strlen( $key ) < 16 || ! $this->is_sha256( $principal_hash ) || ! $this->is_sha256( $request_digest ) || ! $this->is_sha256( $revision_digest ) ) {
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
				'proposal_uuid'        => (string) $proposal_uuid,
				'proposal_version'     => absint( $version ),
				'revision_no'          => absint( $revision ),
				'revision_digest'      => (string) $revision_digest,
				'event_uuid'           => (string) $event_uuid,
				'response_code'        => absint( $response_code ),
				'created_at'           => current_time( 'mysql', true ),
				'expires_at'           => gmdate( 'Y-m-d H:i:s', time() + self::IDEMPOTENCY_DAYS * DAY_IN_SECONDS ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Delete one bounded batch only when parent and child retention/hold agree.
	 */
	private function delete_retention_batch( $now, $after_id, $limit ) {
		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_cleanup_transaction_failed', 'Proposal retention cleanup could not start a transaction.' );
		}
		$rows = $this->read_rows(
			$wpdb->prepare(
				'SELECT p.id,p.proposal_uuid FROM ' . self::proposals_table() . ' p INNER JOIN ' . self::quote_cases_table() . ' c ON c.id = p.quote_case_id AND c.case_uuid = p.quote_case_uuid WHERE p.id > %d AND p.retention_until < %s AND p.legal_hold = 0 AND c.retention_until < %s AND c.legal_hold = 0 ORDER BY p.id ASC LIMIT %d FOR UPDATE',
				absint( $after_id ),
				(string) $now,
				(string) $now,
				absint( $limit )
			),
			'tra_vel_assisted_proposal_cleanup_select_failed',
			'Proposal retention cleanup could not lock eligible records.'
		);
		if ( is_wp_error( $rows ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $rows;
		}
		if ( ! $rows ) {
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'tra_vel_assisted_proposal_cleanup_commit_failed', 'Proposal retention cleanup could not close its empty transaction.' );
			}
			return array( 'scanned' => 0, 'deleted' => 0, 'last_id' => absint( $after_id ) );
		}

		$ids   = array_values( array_map( 'absint', wp_list_pluck( $rows, 'id' ) ) );
		$uuids = array_values( array_map( 'strval', wp_list_pluck( $rows, 'proposal_uuid' ) ) );
		$id_placeholders   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$uuid_placeholders = implode( ',', array_fill( 0, count( $uuids ), '%s' ) );
		$deleted_sources   = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::sources_table() . " WHERE proposal_id IN ({$id_placeholders})", $ids ) );
		$deleted_revisions = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::revisions_table() . " WHERE proposal_id IN ({$id_placeholders})", $ids ) );
		$deleted_events    = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::events_table() . " WHERE proposal_id IN ({$id_placeholders})", $ids ) );
		$deleted_receipts  = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::idempotency_table() . " WHERE proposal_uuid IN ({$uuid_placeholders})", $uuids ) );
		$parent_params     = array_merge( $ids, array( (string) $now, (string) $now ) );
		$deleted_heads     = $wpdb->query(
			$wpdb->prepare(
				'DELETE p FROM ' . self::proposals_table() . ' p INNER JOIN ' . self::quote_cases_table() . " c ON c.id = p.quote_case_id AND c.case_uuid = p.quote_case_uuid WHERE p.id IN ({$id_placeholders}) AND p.retention_until < %s AND p.legal_hold = 0 AND c.retention_until < %s AND c.legal_hold = 0",
				$parent_params
			)
		);
		if ( false === $deleted_sources || false === $deleted_revisions || false === $deleted_events || false === $deleted_receipts || count( $ids ) !== (int) $deleted_heads ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_assisted_proposal_cleanup_delete_failed', 'Proposal retention cleanup rolled back an incomplete deletion batch.' );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'tra_vel_assisted_proposal_cleanup_commit_failed', 'Proposal retention cleanup could not commit a complete deletion batch.' );
		}
		return array( 'scanned' => count( $ids ), 'deleted' => (int) $deleted_heads, 'last_id' => max( $ids ) );
	}

	/**
	 * Sweep a bounded exact ID set of expired or orphaned idempotency receipts.
	 */
	private function sweep_idempotency_batch( $now, $after_id, $limit ) {
		global $wpdb;
		$rows = $this->read_column(
			$wpdb->prepare(
				'SELECT i.id FROM ' . self::idempotency_table() . ' i LEFT JOIN ' . self::proposals_table() . ' p ON p.proposal_uuid = i.proposal_uuid WHERE i.id > %d AND (i.expires_at < %s OR p.id IS NULL) ORDER BY i.id ASC LIMIT %d',
				absint( $after_id ),
				(string) $now,
				absint( $limit )
			),
			'tra_vel_assisted_proposal_cleanup_idempotency_select_failed',
			'Expired proposal idempotency rows could not be selected safely.'
		);
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$ids = array_values( array_map( 'absint', $rows ) );
		if ( ! $ids ) {
			return array( 'scanned' => 0, 'deleted' => 0, 'last_id' => absint( $after_id ) );
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$deleted      = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::idempotency_table() . " WHERE id IN ({$placeholders})", $ids ) );
		if ( false === $deleted || count( $ids ) !== (int) $deleted ) {
			return new WP_Error( 'tra_vel_assisted_proposal_cleanup_idempotency_delete_failed', 'Expired proposal idempotency rows could not be deleted completely.' );
		}
		return array( 'scanned' => count( $ids ), 'deleted' => (int) $deleted, 'last_id' => max( $ids ) );
	}

	/**
	 * Record bounded cleanup observability without storing proposal content.
	 */
	private static function record_cleanup_status( $result ) {
		update_option(
			self::CLEANUP_STATUS_OPTION,
			array(
				'last_run_at'         => gmdate( 'c' ),
				'ok'                  => empty( $result['errors'] ),
				'errors'              => array_values( array_unique( (array) ( $result['errors'] ?? array() ) ) ),
				'deleted_proposals'   => absint( $result['deleted_proposals'] ?? 0 ),
				'deleted_idempotency' => absint( $result['deleted_idempotency'] ?? 0 ),
				'batches'             => absint( $result['batches'] ?? 0 ),
			),
			false
		);
	}

	/**
	 * Validate the future controller's non-anonymous operator principal.
	 */
	private function validate_principal( $principal ) {
		if ( ! is_array( $principal ) || absint( $principal['user_id'] ?? 0 ) < 1 || ! $this->is_sha256( $principal['principal_hash'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_operator_required', 'A verified operator principal is required.', array( 'status' => 403 ) );
		}
		$user_id = absint( $principal['user_id'] );
		if ( ! function_exists( 'get_current_user_id' ) || $user_id !== absint( get_current_user_id() ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_operator_required', 'The operator principal does not match the authenticated WordPress user.', array( 'status' => 403 ) );
		}
		if ( ! empty( $principal['assignment_override'] ) && ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_assignment_forbidden', 'The assignment override is not authorized for this operator.', array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Recheck operator assignment against the locked parent row. Administrators
	 * may carry a server-resolved override for recovery and oversight only.
	 *
	 * @param array $principal Verified server-side operator principal.
	 * @param array $case      Locked normalized quote case.
	 * @return true|WP_Error
	 */
	private function validate_operator_assignment( $principal, $case ) {
		if ( ! empty( $principal['assignment_override'] ) ) {
			return true;
		}
		$user_id = absint( $principal['user_id'] ?? 0 );
		if ( $user_id > 0 && $user_id === absint( $case['assigned_user_id'] ?? 0 ) ) {
			return true;
		}
		return new WP_Error( 'tra_vel_assisted_proposal_assignment_forbidden', 'The locked quote case is not assigned to this operator.', array( 'status' => 403 ) );
	}

	/**
	 * Validate the shape of a server-resolved traveler principal.
	 */
	private function validate_traveler_principal_shape( $principal ) {
		if ( ! is_array( $principal ) || ! $this->is_sha256( $principal['principal_hash'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_traveler_required', 'A verified traveler principal is required.', array( 'status' => 403 ) );
		}
		$user_id    = absint( $principal['user_id'] ?? 0 );
		$token_hash = (string) ( $principal['token_hash'] ?? '' );
		if ( $user_id < 1 && ! $this->is_sha256( $token_hash ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_traveler_required', 'A verified traveler account or private-browser principal is required.', array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Compare only server-resolved principal data with the locked parent owner.
	 */
	private function validate_traveler_owner( $principal, $case ) {
		$user_id = absint( $principal['user_id'] ?? 0 );
		if ( $user_id > 0 && (int) $case['owner_user_id'] === $user_id ) {
			return true;
		}
		$token_hash = (string) ( $principal['token_hash'] ?? '' );
		if ( 0 === (int) $case['owner_user_id'] && $this->is_sha256( $token_hash ) && $this->is_sha256( $case['owner_token_hash'] ?? '' ) && hash_equals( (string) $case['owner_token_hash'], $token_hash ) ) {
			return true;
		}
		return new WP_Error( 'tra_vel_assisted_proposal_forbidden', 'This proposal does not belong to the current traveler.', array( 'status' => 403 ) );
	}

	/**
	 * Hydrate numeric values while preserving stored/effective lifecycle truth.
	 */
	private function hydrate_head( $row ) {
		foreach ( array( 'id', 'quote_case_id', 'proposal_version', 'current_revision', 'published_revision', 'last_event_sequence', 'source_case_version', 'source_case_revision', 'created_by_user_id', 'last_actor_user_id', 'legal_hold' ) as $field ) {
			$row[ $field ] = (int) $row[ $field ];
		}
		$row['stored_status']               = (string) $row['status'];
		$row['stored_traveler_disposition'] = (string) $row['traveler_disposition'];
		$row['status']                      = Tra_Vel_Assisted_Proposal_Policy::effective_status( (string) $row['status'], (string) $row['expires_at'] );
		if ( 'available' !== $row['status'] ) {
			$row['traveler_disposition'] = 'unavailable';
		}
		$row['next_actions']  = $this->next_actions_for( (string) $row['status'], (string) $row['traveler_disposition'] );
		return $row;
	}

	/**
	 * V1 traveler transition matrix. Terminal dispositions intentionally expose
	 * no further action until an operator publishes a new exact revision.
	 */
	private function next_actions_for( $status, $disposition ) {
		if ( 'available' !== $status ) {
			return array();
		}
		return Tra_Vel_Assisted_Proposal_Policy::traveler_actions_for( $status, $disposition );
	}

	/**
	 * Project mutable lifecycle truth for reads without changing the immutable
	 * commercial revision JSON or its digest.
	 */
	private function project_effective_proposal( $snapshot, $head ) {
		$projected                         = $snapshot;
		$projected['status']               = (string) $head['status'];
		$projected['version']              = (int) $head['proposal_version'];
		$projected['published_revision']   = (int) $head['published_revision'];
		$projected['traveler_disposition'] = (string) $head['traveler_disposition'];
		$projected['next_actions']         = $this->next_actions_for( (string) $head['status'], (string) $head['traveler_disposition'] );
		return $projected;
	}

	private function hydrate_event( $row ) {
		$payload = json_decode( (string) $row['payload'], true );
		if ( ! is_array( $payload ) || ! $this->safe_digest_equals( (string) $row['payload_digest'], hash( 'sha256', (string) $row['payload'] ) ) || ! hash_equals( (string) $row['action_code'], (string) ( $payload['action'] ?? '' ) ) || ! hash_equals( (string) $row['from_status'], (string) ( $payload['from_status'] ?? '' ) ) || ! hash_equals( (string) $row['to_status'], (string) ( $payload['to_status'] ?? '' ) ) || ! hash_equals( (string) $row['from_disposition'], (string) ( $payload['from_disposition'] ?? '' ) ) || ! hash_equals( (string) $row['to_disposition'], (string) ( $payload['to_disposition'] ?? '' ) ) || (int) $row['proposal_version'] !== (int) ( $payload['proposal_version'] ?? 0 ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_event_integrity_failed', 'The proposal action event failed its integrity check.', array( 'status' => 500 ) );
		}
		$has_contact_consent = array_key_exists( 'contact_consent', $payload );
		if ( 'authorize_contact' === (string) $row['action_code'] ) {
			$stored_consent = $has_contact_consent && is_array( $payload['contact_consent'] ) ? $payload['contact_consent'] : null;
			$normalized_consent = $this->normalize_stored_contact_consent( $stored_consent, (int) ( $row['actor_user_id'] ?? 0 ) );
			if ( is_wp_error( $normalized_consent ) ) {
				return $normalized_consent;
			}
			$payload['contact_consent'] = $normalized_consent;
		} elseif ( $has_contact_consent ) {
			return new WP_Error( 'tra_vel_assisted_proposal_event_integrity_failed', 'A non-contact proposal event contains unexpected consent evidence.', array( 'status' => 500 ) );
		}
		return array(
			'contract_version' => '1.0.0',
			'event_id'         => (string) $row['event_uuid'],
			'proposal_id'      => (int) $row['proposal_id'],
			'sequence'         => (int) $row['sequence_no'],
			'proposal_version' => (int) $row['proposal_version'],
			'type'             => (string) $row['event_type'],
			'action'           => (string) $row['action_code'],
			'from_status'      => (string) $row['from_status'],
			'to_status'        => (string) $row['to_status'],
			'from_disposition' => (string) $row['from_disposition'],
			'to_disposition'   => (string) $row['to_disposition'],
			'actor_type'       => (string) $row['actor_type'],
			'data'             => $payload,
			'occurred_at'      => gmdate( 'c', strtotime( (string) $row['created_at'] . ' UTC' ) ),
		);
	}

	/**
	 * Read helpers always inspect last_error so an empty result never masks a
	 * database failure.
	 */
	private function read_row( $sql, $error_code, $message ) {
		global $wpdb;
		$was_suppressed   = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$row               = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- caller passes a fully prepared internal query.
		$read_error        = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( '' !== $read_error ) {
			return new WP_Error( $error_code, $message, array( 'status' => 500 ) );
		}
		return is_array( $row ) ? $row : null;
	}

	private function read_rows( $sql, $error_code, $message ) {
		global $wpdb;
		$was_suppressed   = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$rows              = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- caller passes a fully prepared internal query.
		$read_error        = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( '' !== $read_error || ! is_array( $rows ) ) {
			return new WP_Error( $error_code, $message, array( 'status' => 500 ) );
		}
		return $rows;
	}

	private function read_column( $sql, $error_code, $message ) {
		global $wpdb;
		$was_suppressed   = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$rows              = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- caller passes a fully prepared internal query.
		$read_error        = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( '' !== $read_error || ! is_array( $rows ) ) {
			return new WP_Error( $error_code, $message, array( 'status' => 500 ) );
		}
		return $rows;
	}

	private function read_scalar( $sql, $error_code, $message ) {
		global $wpdb;
		$was_suppressed   = $wpdb->suppress_errors();
		$wpdb->last_error = '';
		$value             = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- caller passes a fully prepared internal query.
		$read_error        = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $was_suppressed );
		if ( '' !== $read_error || null === $value ) {
			return new WP_Error( $error_code, $message, array( 'status' => 500 ) );
		}
		return $value;
	}

	/**
	 * Canonical JSON used for immutable snapshots.
	 */
	private function canonical_json( $value ) {
		$encoded = wp_json_encode( $this->canonicalize( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $encoded ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_encoding_failed', 'The immutable proposal record could not be encoded.', array( 'status' => 400 ) );
		}
		return $encoded;
	}

	private function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$is_list = empty( $value ) || array_keys( $value ) === range( 0, count( $value ) - 1 );
		if ( ! $is_list ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $child ) {
			$value[ $key ] = $this->canonicalize( $child );
		}
		return $value;
	}

	private function sanitize_idempotency_key( $key ) {
		return substr( preg_replace( '/[^A-Za-z0-9._:-]/', '', (string) $key ), 0, 100 );
	}

	private function sanitize_operation_scope( $scope ) {
		return substr( preg_replace( '/[^a-z0-9._-]/', '', strtolower( (string) $scope ) ), 0, 64 );
	}

	private function safe_digest_equals( $known, $candidate ) {
		return $this->is_sha256( $known ) && $this->is_sha256( $candidate ) && hash_equals( (string) $known, (string) $candidate );
	}

	private function is_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}

	private function is_sha256( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private function parse_datetime( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return 0;
		}
		$timestamp = strtotime( $value );
		return false === $timestamp ? 0 : (int) $timestamp;
	}

	private function mysql_datetime( $value ) {
		return gmdate( 'Y-m-d H:i:s', $this->parse_datetime( $value ) );
	}

	/**
	 * Inspect required columns, engines, and exact unique index shapes.
	 */
	private static function inspect_schema() {
		global $wpdb;
		$requirements = array(
			self::proposals_table() => array(
				'columns' => array( 'id', 'proposal_uuid', 'reference_code', 'quote_case_id', 'quote_case_uuid', 'position', 'status', 'traveler_disposition', 'proposal_version', 'current_revision', 'published_revision', 'last_event_sequence', 'source_case_version', 'source_case_revision', 'request_digest', 'current_revision_digest', 'published_revision_digest', 'current_source_set_digest', 'created_by_user_id', 'last_actor_user_id', 'created_at', 'updated_at', 'published_at', 'expires_at', 'retention_until', 'legal_hold' ),
				'unique'  => array( array( 'proposal_uuid' ), array( 'reference_code' ) ),
			),
			self::revisions_table() => array(
				'columns' => array( 'id', 'proposal_id', 'revision_no', 'proposal_version', 'quote_case_id', 'quote_case_uuid', 'source_case_version', 'source_case_revision', 'request_digest', 'snapshot_digest', 'source_set_digest', 'source_count', 'proposal_snapshot', 'publication_validated', 'actor_user_id', 'created_at' ),
				'unique'  => array( array( 'proposal_id', 'revision_no' ), array( 'proposal_id', 'snapshot_digest' ) ),
			),
			self::sources_table() => array(
				'columns' => array( 'id', 'proposal_id', 'proposal_revision_id', 'revision_no', 'source_uuid', 'provider_code', 'source_type', 'relationship', 'public_label', 'supplier_name', 'seller_name', 'source_reference', 'source_url', 'observed_at', 'fresh_until', 'evidence_digest', 'source_digest', 'source_snapshot', 'created_at' ),
				'unique'  => array( array( 'proposal_id', 'revision_no', 'source_uuid' ), array( 'proposal_revision_id', 'source_uuid' ) ),
			),
			self::events_table() => array(
				'columns' => array( 'id', 'proposal_id', 'sequence_no', 'proposal_version', 'event_uuid', 'event_type', 'action_code', 'from_status', 'to_status', 'from_disposition', 'to_disposition', 'actor_type', 'actor_user_id', 'principal_hash', 'payload', 'payload_digest', 'created_at' ),
				'unique'  => array( array( 'event_uuid' ), array( 'proposal_id', 'sequence_no' ) ),
			),
			self::idempotency_table() => array(
				'columns' => array( 'id', 'operation_scope', 'principal_hash', 'idempotency_key_hash', 'request_digest', 'proposal_uuid', 'proposal_version', 'revision_no', 'revision_digest', 'event_uuid', 'response_code', 'created_at', 'expires_at' ),
				'unique'  => array( array( 'operation_scope', 'principal_hash', 'idempotency_key_hash' ) ),
			),
		);

		$ready             = 0;
		$transactional     = 0;
		$required_indexes  = 0;
		$ready_indexes     = 0;
		$inspection_errors = array();
		$was_suppressed    = $wpdb->suppress_errors();

		foreach ( $requirements as $table => $requirement ) {
			$identifier       = str_replace( '`', '``', (string) $table );
			$wpdb->last_error = '';
			$columns           = $wpdb->get_col( "SHOW COLUMNS FROM `{$identifier}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted WordPress-prefixed table.
			$column_error      = (string) $wpdb->last_error;
			$wpdb->last_error = '';
			$status            = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', (string) $table ), ARRAY_A );
			$status_error      = (string) $wpdb->last_error;
			$wpdb->last_error = '';
			$indexes           = $wpdb->get_results( "SHOW INDEX FROM `{$identifier}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted WordPress-prefixed table.
			$index_error       = (string) $wpdb->last_error;
			if ( '' !== $column_error || '' !== $status_error || '' !== $index_error ) {
				$inspection_errors[] = (string) $table;
			}

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

			$table_indexes     = 0;
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
			if ( '' === $column_error && '' === $status_error && '' === $index_error && is_array( $columns ) && ! array_diff( $requirement['columns'], $columns ) && $engine_ok && count( $requirement['unique'] ) === $table_indexes ) {
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
			'inspection_errors'      => array_values( array_unique( $inspection_errors ) ),
			'tables_ready'           => $expected === $ready && $expected === $transactional && $required_indexes === $ready_indexes && empty( $inspection_errors ),
		);
	}

	public static function proposals_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_assisted_proposals';
	}

	public static function revisions_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_assisted_proposal_revisions';
	}

	public static function sources_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_assisted_proposal_sources';
	}

	public static function events_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_assisted_proposal_events';
	}

	public static function idempotency_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_assisted_proposal_idempotency';
	}

	private static function quote_cases_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_quote_cases';
	}
}
