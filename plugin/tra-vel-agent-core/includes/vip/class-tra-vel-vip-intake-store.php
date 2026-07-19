<?php
/**
 * Bounded durable store for privacy-minimized, no-login VIP intake.
 *
 * Only envelopes already accepted by Tra_Vel_VIP_Intake_Policy are stored.
 * Raw messages, contact data, attachment bytes, identity/payment/medical facts,
 * bearer values, supplier payloads, and owner tokens never enter these tables.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_VIP_Intake_Store {
	const DB_VERSION             = '1.1.0';
	const DB_VERSION_OPTION      = 'tra_vel_vip_intake_db_version';
	const CLEANUP_STATUS_OPTION  = 'tra_vel_vip_intake_cleanup_status';
	const RECEIPT_DAYS           = 30;
	const RETENTION_DAYS         = 90;
	const IDEMPOTENCY_DAYS       = 7;
	const MAX_ENVELOPE_BYTES     = 65536;
	const PUBLIC_CONTRACT_VERSION = '1.0.0';

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
		$intakes     = self::intakes_table();
		$receipts    = self::receipts_table();
		$idempotency = self::idempotency_table();
		$limits      = self::limits_table();

		dbDelta( "CREATE TABLE {$intakes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			intake_ref varchar(112) NOT NULL,
			fingerprint char(64) NOT NULL,
			channel_event_digest char(64) NOT NULL,
			correlation_digest char(64) NOT NULL,
			content_digest char(64) NOT NULL,
			normalization_attestation_digest char(64) NOT NULL,
			classifier_revision varchar(64) NOT NULL,
			normalization_issued_at datetime NOT NULL,
			envelope longtext NOT NULL,
			envelope_bytes int(10) unsigned NOT NULL,
			created_at datetime NOT NULL,
			retention_until datetime NOT NULL,
			legal_hold tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY intake_ref (intake_ref),
			UNIQUE KEY channel_event_digest (channel_event_digest),
			UNIQUE KEY correlation_digest (correlation_digest),
			KEY retention (retention_until,legal_hold)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$receipts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			receipt_ref char(14) NOT NULL,
			canonical_intake_id bigint(20) unsigned NOT NULL,
			attempt_intake_ref varchar(112) NOT NULL,
			attempt_fingerprint char(64) NOT NULL,
			owner_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			owner_token_hash char(64) NOT NULL DEFAULT '',
			principal_hash char(64) NOT NULL,
			normalization_attestation_digest char(64) NOT NULL,
			classifier_revision varchar(64) NOT NULL,
			public_state varchar(24) NOT NULL,
			message_code varchar(48) NOT NULL,
			step_up_required tinyint(1) NOT NULL DEFAULT 0,
			human_review_required tinyint(1) NOT NULL DEFAULT 0,
			duplicate tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			retention_until datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY receipt_ref (receipt_ref),
			KEY canonical_intake (canonical_intake_id),
			KEY account_owner (owner_user_id,receipt_ref),
			KEY browser_owner (owner_token_hash,receipt_ref),
			KEY retention (retention_until)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$idempotency} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			operation_scope varchar(48) NOT NULL,
			principal_hash char(64) NOT NULL,
			idempotency_key_hash char(64) NOT NULL,
			request_digest char(64) NOT NULL,
			receipt_ref char(14) NOT NULL,
			response_code smallint(5) unsigned NOT NULL DEFAULT 200,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY operation_key (operation_scope,principal_hash,idempotency_key_hash),
			KEY receipt_lookup (receipt_ref),
			KEY expiry (expires_at)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$limits} (
			limit_key char(64) NOT NULL,
			hits smallint(5) unsigned NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (limit_key),
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
				'receipt_days'              => self::RECEIPT_DAYS,
				'retention_days'            => self::RETENTION_DAYS,
				'idempotency_days'          => self::IDEMPOTENCY_DAYS,
				'max_envelope_bytes'        => self::MAX_ENVELOPE_BYTES,
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
	 * Persist a validated report, or replay the exact owner-scoped receipt.
	 *
	 * @param array  $envelope          Server-normalized policy envelope.
	 * @param array  $principal         user_id/token_hash/principal_hash.
	 * @param string $idempotency_key   Client retry key; never stored raw.
	 * @param string $request_digest    Digest of the exact pre-normalization request.
	 * @param array  $normalization     Verified attestation digest/revision/time.
	 * @return array|WP_Error
	 */
	public function create_or_replay( $envelope, $principal, $idempotency_key, $request_digest, $normalization ) {
		global $wpdb;
		$principal = $this->valid_principal( $principal );
		$key       = $this->sanitize_idempotency_key( $idempotency_key );
		if ( is_wp_error( $principal ) ) {
			return $principal;
		}
		if ( strlen( $key ) < 16 || ! self::digest( $request_digest ) ) {
			return self::error( 'request_invalid', 'A bounded idempotency key and request digest are required.', 400 );
		}
		$normalization = $this->valid_normalization_evidence( $normalization );
		if ( is_wp_error( $normalization ) ) {
			return $normalization;
		}
		if ( ! isset( $envelope['idempotency_digest'] ) || ! is_string( $envelope['idempotency_digest'] ) || ! hash_equals( hash( 'sha256', $key ), $envelope['idempotency_digest'] ) ) {
			return self::error( 'idempotency_binding_invalid', 'The intake envelope is not bound to its retry key.', 400 );
		}

		$replay = $this->idempotent_receipt( $principal, $key, $request_digest );
		if ( null !== $replay ) {
			return is_wp_error( $replay ) ? $replay : array( 'receipt' => $replay, 'created' => false, 'replayed' => true, 'deduplicated' => (bool) $replay['duplicate'] );
		}

		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return self::error( 'transaction_failed', 'The private intake transaction could not be started.', 500 );
			}

			$locked_replay = $this->idempotent_receipt( $principal, $key, $request_digest, true );
			if ( null !== $locked_replay ) {
				$wpdb->query( 'ROLLBACK' );
				return is_wp_error( $locked_replay ) ? $locked_replay : array( 'receipt' => $locked_replay, 'created' => false, 'replayed' => true, 'deduplicated' => (bool) $locked_replay['duplicate'] );
			}

			$existing_receipt = $this->get_by_reference( (string) ( $envelope['public_receipt_ref'] ?? '' ), true );
			if ( $existing_receipt ) {
				if ( ! $this->can_access( $existing_receipt, $principal['user_id'], $principal['token_hash'] ) || ! hash_equals( (string) $existing_receipt['attempt_fingerprint'], Tra_Vel_VIP_Intake_Policy::canonical_digest( $envelope ) ) ) {
					$wpdb->query( 'ROLLBACK' );
					return self::error( 'receipt_conflict', 'The receipt reference is already bound to another immutable private intake.', 409 );
				}
				if ( ! $this->insert_idempotency( $principal, $key, $request_digest, $existing_receipt['receipt_ref'], 200 ) || false === $wpdb->query( 'COMMIT' ) ) {
					$wpdb->query( 'ROLLBACK' );
					return self::error( 'idempotency_failed', 'The private receipt replay could not be recorded safely.', 500 );
				}
				return array( 'receipt' => $existing_receipt, 'created' => false, 'replayed' => true, 'deduplicated' => (bool) $existing_receipt['duplicate'] );
			}

			$indexes = $this->matching_indexes( $envelope, true );
			if ( is_wp_error( $indexes ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $indexes;
			}
			$result = Tra_Vel_VIP_Intake_Policy::intake( $envelope, $indexes );
			if ( is_wp_error( $result ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $result;
			}
			$projection = Tra_Vel_VIP_Intake_State_Projection::project( $result );
			if ( is_wp_error( $projection ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $projection;
			}

			if ( $result['duplicate'] ) {
				$canonical = $this->canonical_by_intake_ref( $result['duplicate_of_intake_ref'], true );
				if ( ! $canonical ) {
					$wpdb->query( 'ROLLBACK' );
					return self::error( 'dedupe_target_missing', 'The prior intake could not be reconciled safely.', 409 );
				}
			} else {
				$canonical = $this->insert_canonical( $envelope, $result, $normalization );
				if ( ! $canonical ) {
					$wpdb->query( 'ROLLBACK' );
					if ( 0 === $attempt ) {
						continue;
					}
					return self::error( 'store_failed', 'The private intake could not be saved safely.', 500 );
				}
			}

			$receipt = $this->insert_receipt( $canonical, $envelope, $result, $projection, $principal, $normalization );
			if ( ! $receipt || ! $this->insert_idempotency( $principal, $key, $request_digest, $envelope['public_receipt_ref'], $result['duplicate'] ? 200 : 201 ) ) {
				$wpdb->query( 'ROLLBACK' );
				if ( 0 === $attempt ) {
					continue;
				}
				return self::error( 'store_failed', 'The private intake receipt could not be committed safely.', 500 );
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				$recovered = $this->idempotent_receipt( $principal, $key, $request_digest );
				if ( $recovered && ! is_wp_error( $recovered ) ) {
					return array( 'receipt' => $recovered, 'created' => false, 'replayed' => true, 'deduplicated' => (bool) $recovered['duplicate'] );
				}
				return self::error( 'transaction_failed', 'The private intake receipt commit could not be confirmed.', 500 );
			}

			return array( 'receipt' => $receipt, 'created' => ! $result['duplicate'], 'replayed' => false, 'deduplicated' => (bool) $result['duplicate'] );
		}

		return self::error( 'store_failed', 'The private intake could not be reconciled after a concurrent write.', 409 );
	}

	public function get_by_reference( $receipt_ref, $for_update = false ) {
		global $wpdb;
		if ( ! is_string( $receipt_ref ) || 1 !== preg_match( '/^TVR-[A-Z0-9]{10}$/', $receipt_ref ) ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::receipts_table() . ' WHERE receipt_ref = %s LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ),
				$receipt_ref
			),
			ARRAY_A
		);
		return is_array( $row ) ? $this->hydrate_receipt( $row ) : null;
	}

	public function get_owned_receipt( $receipt_ref, $user_id, $owner_token_hash ) {
		$receipt = $this->get_by_reference( $receipt_ref );
		return $receipt && $this->can_access( $receipt, $user_id, $owner_token_hash ) ? $receipt : null;
	}

	public function can_access( $receipt, $user_id, $owner_token_hash ) {
		if ( ! is_array( $receipt ) ) {
			return false;
		}
		if ( (int) $receipt['owner_user_id'] > 0 ) {
			return (int) $user_id > 0 && (int) $receipt['owner_user_id'] === (int) $user_id;
		}
		return 64 === strlen( (string) $owner_token_hash )
			&& 64 === strlen( (string) $receipt['owner_token_hash'] )
			&& hash_equals( (string) $receipt['owner_token_hash'], (string) $owner_token_hash );
	}

	/**
	 * Project the exact public receipt schema. No internal refs/digests/work leak.
	 */
	public function public_receipt( $receipt, $now = null ) {
		if ( ! is_array( $receipt ) || ! in_array( (string) ( $receipt['public_state'] ?? '' ), self::public_states(), true ) ) {
			return self::error( 'receipt_invalid', 'The private intake receipt is unavailable.', 500 );
		}
		$now_timestamp = null === $now ? time() : (int) $now;
		$expires       = strtotime( (string) $receipt['expires_at'] . ' UTC' );
		$available     = false !== $expires && $expires > $now_timestamp;
		return array(
			'contract_version'       => self::PUBLIC_CONTRACT_VERSION,
			'receipt_ref'            => (string) $receipt['receipt_ref'],
			'state'                  => (string) $receipt['public_state'],
			'message_code'           => (string) $receipt['message_code'],
			'accepted'               => true,
			'duplicate'              => (bool) $receipt['duplicate'],
			'login_required'         => false,
			'ownership'              => (int) $receipt['owner_user_id'] > 0 ? 'account' : 'private_browser_owner',
			'received_at'            => self::iso_utc( $receipt['created_at'] ),
			'updated_at'             => self::iso_utc( $receipt['updated_at'] ),
			'expires_at'             => self::iso_utc( $receipt['expires_at'] ),
			'message_disposition'    => array(
				'raw_message_received_by_bridge'       => false,
				'normalized_vault_reference_received' => true,
				'classifier_claim_verified'            => true,
			),
			'resume'                 => array(
				'available'          => $available,
				'allowed_operations' => $available ? array( 'receipt_view', 'incident_follow_up' ) : array(),
			),
			'verification'           => array(
				'step_up_required'   => (bool) $receipt['step_up_required'],
				'authorization_effect'=> 'none',
				'executable_scopes'  => array(),
			),
			'human_review_required' => (bool) $receipt['human_review_required'],
			'supplier_action_started'=> false,
			'payment_action_started' => false,
		);
	}

	public static function public_states() {
		return array( 'received', 'checking', 'need_information', 'human_review' );
	}

	/**
	 * Map detailed internal triage to the only four truthful public states.
	 */
	public static function truthful_state( $projection, $envelope ) {
		$customer_state = (string) ( $projection['customer_state'] ?? '' );
		$duplicate      = ! empty( $projection['duplicate'] );
		$step_up        = is_array( $envelope ) && isset( $envelope['access']['requested_scopes'] ) && Tra_Vel_VIP_Intake_Taxonomy::has_high_impact_request( $envelope['access']['requested_scopes'] );
		$human_review   = ! empty( $projection['operator_review_required'] );

		if ( $duplicate ) {
			return array( 'state' => 'received', 'message_code' => 'request_already_received', 'step_up_required' => $step_up, 'human_review_required' => $human_review );
		}
		if ( in_array( $customer_state, array( 'clarification_needed', 'step_up_needed' ), true ) ) {
			return array( 'state' => 'need_information', 'message_code' => $step_up ? 'verification_required' : 'more_information_needed', 'step_up_required' => $step_up, 'human_review_required' => $human_review );
		}
		if ( in_array( $customer_state, array( 'security_check_needed', 'attachment_quarantined' ), true ) ) {
			return array( 'state' => 'checking', 'message_code' => 'request_is_being_checked', 'step_up_required' => $step_up, 'human_review_required' => $human_review );
		}
		if ( $human_review || in_array( $customer_state, array( 'human_review_started', 'case_reopen_review', 'immediate_safety_help' ), true ) ) {
			return array( 'state' => 'human_review', 'message_code' => 'specialist_review_required', 'step_up_required' => $step_up, 'human_review_required' => true );
		}
		return array( 'state' => 'received', 'message_code' => 'request_received', 'step_up_required' => $step_up, 'human_review_required' => false );
	}

	/**
	 * Atomic fixed-window limiter. Only an HMAC-derived key is persisted.
	 */
	public function consume_limit( $limit_key, $limit, $expires_at ) {
		global $wpdb;
		if ( ! self::digest( $limit_key ) || (int) $limit < 1 || (int) $limit > 500 || (int) $expires_at <= time() ) {
			return false;
		}
		$now_mysql     = current_time( 'mysql', true );
		$expires_mysql = gmdate( 'Y-m-d H:i:s', (int) $expires_at );
		$sql = $wpdb->prepare(
			'INSERT INTO ' . self::limits_table() . ' (limit_key,hits,expires_at) VALUES (%s,1,%s) ON DUPLICATE KEY UPDATE hits = IF(expires_at <= %s,1,hits+1), expires_at = IF(expires_at <= %s,VALUES(expires_at),expires_at)',
			(string) $limit_key,
			$expires_mysql,
			$now_mysql,
			$now_mysql
		);
		if ( false === $wpdb->query( $sql ) ) {
			return false;
		}
		$hits = $wpdb->get_var( $wpdb->prepare( 'SELECT hits FROM ' . self::limits_table() . ' WHERE limit_key = %s LIMIT 1', (string) $limit_key ) );
		return null !== $hits && (int) $hits <= (int) $limit;
	}

	public static function cleanup() {
		global $wpdb;
		$result = array( 'deleted_intakes' => 0, 'deleted_receipts' => 0, 'deleted_idempotency' => 0, 'deleted_limits' => 0, 'errors' => array() );
		if ( ! self::is_ready() ) {
			$result['errors'][] = 'schema_not_ready';
			self::record_cleanup_status( $result );
			return $result;
		}
		$now = current_time( 'mysql', true );
		$deleted_idempotency = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::idempotency_table() . ' WHERE expires_at < %s ORDER BY id ASC LIMIT 1000', $now ) );
		$deleted_limits      = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::limits_table() . ' WHERE expires_at < %s ORDER BY expires_at ASC LIMIT 1000', $now ) );
		$deleted_receipts    = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::receipts_table() . ' WHERE retention_until < %s ORDER BY id ASC LIMIT 500', $now ) );
		$deleted_intakes     = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::intakes_table() . ' WHERE id IN (SELECT doomed.id FROM (SELECT i.id FROM ' . self::intakes_table() . ' i LEFT JOIN ' . self::receipts_table() . ' r ON r.canonical_intake_id = i.id WHERE i.retention_until < %s AND i.legal_hold = 0 AND r.id IS NULL ORDER BY i.id ASC LIMIT 100) doomed)',
				$now
			)
		);
		foreach ( array( 'deleted_idempotency', 'deleted_limits', 'deleted_receipts', 'deleted_intakes' ) as $key ) {
			$value = ${$key};
			if ( false === $value ) {
				$result['errors'][] = $key . '_failed';
			} else {
				$result[ $key ] = (int) $value;
			}
		}
		self::record_cleanup_status( $result );
		return $result;
	}

	private function matching_indexes( $envelope, $for_update ) {
		$indexes = array( 'by_ref' => array(), 'by_channel_event' => array(), 'by_correlation' => array() );
		$by_ref  = $this->canonical_by_intake_ref( (string) $envelope['intake_ref'], $for_update );
		if ( $by_ref ) {
			$indexes['by_ref'][ $by_ref['intake_ref'] ] = $by_ref['fingerprint'];
		}
		$by_channel = $this->canonical_by_digest( 'channel_event_digest', (string) $envelope['source']['channel_event_digest'], $for_update );
		if ( $by_channel ) {
			$indexes['by_channel_event'][ $by_channel['channel_event_digest'] ] = array( 'intake_ref' => $by_channel['intake_ref'], 'content_digest' => $by_channel['content_digest'] );
		}
		$by_correlation = $this->canonical_by_digest( 'correlation_digest', (string) $envelope['correlation_digest'], $for_update );
		if ( $by_correlation ) {
			$indexes['by_correlation'][ $by_correlation['correlation_digest'] ] = array( 'intake_ref' => $by_correlation['intake_ref'], 'content_digest' => $by_correlation['content_digest'] );
		}
		return $indexes;
	}

	private function canonical_by_intake_ref( $intake_ref, $for_update = false ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::intakes_table() . ' WHERE intake_ref = %s LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ), (string) $intake_ref ),
			ARRAY_A
		);
		return is_array( $row ) ? $this->hydrate_canonical( $row ) : null;
	}

	private function canonical_by_digest( $column, $digest, $for_update = false ) {
		global $wpdb;
		if ( ! in_array( $column, array( 'channel_event_digest', 'correlation_digest' ), true ) || ! self::digest( $digest ) ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::intakes_table() . " WHERE {$column} = %s LIMIT 1" . ( $for_update ? ' FOR UPDATE' : '' ), (string) $digest ),
			ARRAY_A
		);
		return is_array( $row ) ? $this->hydrate_canonical( $row ) : null;
	}

	private function insert_canonical( $envelope, $result, $normalization ) {
		global $wpdb;
		$encoded = self::canonical_json( $envelope );
		$bytes   = strlen( $encoded );
		if ( $bytes < 2 || $bytes > self::MAX_ENVELOPE_BYTES ) {
			return false;
		}
		$now = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			self::intakes_table(),
			array(
				'intake_ref'           => (string) $envelope['intake_ref'],
				'fingerprint'          => (string) $result['fingerprint'],
				'channel_event_digest' => (string) $envelope['source']['channel_event_digest'],
				'correlation_digest'   => (string) $envelope['correlation_digest'],
				'content_digest'       => (string) $envelope['content']['message_digest'],
				'normalization_attestation_digest' => (string) $normalization['attestation_digest'],
				'classifier_revision'  => (string) $normalization['classifier_revision'],
				'normalization_issued_at' => gmdate( 'Y-m-d H:i:s', strtotime( $normalization['issued_at'] ) ),
				'envelope'             => $encoded,
				'envelope_bytes'       => $bytes,
				'created_at'           => $now,
				'retention_until'      => gmdate( 'Y-m-d H:i:s', time() + self::RETENTION_DAYS * DAY_IN_SECONDS ),
				'legal_hold'           => 0,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d' )
		);
		return false === $inserted ? null : $this->canonical_by_intake_ref( $envelope['intake_ref'], true );
	}

	private function insert_receipt( $canonical, $envelope, $result, $projection, $principal, $normalization ) {
		global $wpdb;
		$truth = self::truthful_state( $projection, $envelope );
		if ( ! in_array( $truth['state'], self::public_states(), true ) ) {
			return null;
		}
		$now = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			self::receipts_table(),
			array(
				'receipt_ref'            => (string) $envelope['public_receipt_ref'],
				'canonical_intake_id'     => (int) $canonical['id'],
				'attempt_intake_ref'      => (string) $envelope['intake_ref'],
				'attempt_fingerprint'     => (string) $result['fingerprint'],
				'owner_user_id'           => (int) $principal['user_id'],
				'owner_token_hash'        => (int) $principal['user_id'] > 0 ? '' : (string) $principal['token_hash'],
				'principal_hash'          => (string) $principal['principal_hash'],
				'normalization_attestation_digest' => (string) $normalization['attestation_digest'],
				'classifier_revision'     => (string) $normalization['classifier_revision'],
				'public_state'            => (string) $truth['state'],
				'message_code'            => (string) $truth['message_code'],
				'step_up_required'        => $truth['step_up_required'] ? 1 : 0,
				'human_review_required'   => $truth['human_review_required'] ? 1 : 0,
				'duplicate'               => $result['duplicate'] ? 1 : 0,
				'created_at'              => $now,
				'updated_at'              => $now,
				'expires_at'              => gmdate( 'Y-m-d H:i:s', time() + self::RECEIPT_DAYS * DAY_IN_SECONDS ),
				'retention_until'         => gmdate( 'Y-m-d H:i:s', time() + self::RETENTION_DAYS * DAY_IN_SECONDS ),
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		return false === $inserted ? null : $this->get_by_reference( $envelope['public_receipt_ref'], true );
	}

	private function idempotent_receipt( $principal, $key, $request_digest, $for_update = false ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::idempotency_table() . ' WHERE operation_scope = %s AND principal_hash = %s AND idempotency_key_hash = %s AND expires_at > %s LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ),
				'vip.intake.create',
				(string) $principal['principal_hash'],
				$this->idempotency_key_hash( $key ),
				current_time( 'mysql', true )
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		if ( ! hash_equals( (string) $row['request_digest'], (string) $request_digest ) ) {
			return self::error( 'idempotency_conflict', 'This retry key was already used for different intake data.', 409 );
		}
		$receipt = $this->get_by_reference( (string) $row['receipt_ref'], $for_update );
		if ( ! $receipt || ! $this->can_access( $receipt, $principal['user_id'], $principal['token_hash'] ) ) {
			return self::error( 'replay_unavailable', 'The prior private receipt cannot be replayed safely.', 409 );
		}
		return $receipt;
	}

	private function insert_idempotency( $principal, $key, $request_digest, $receipt_ref, $response_code ) {
		global $wpdb;
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::idempotency_table() . ' WHERE operation_scope = %s AND principal_hash = %s AND idempotency_key_hash = %s AND expires_at <= %s',
				'vip.intake.create',
				(string) $principal['principal_hash'],
				$this->idempotency_key_hash( $key ),
				current_time( 'mysql', true )
			)
		);
		if ( false === $deleted ) {
			return false;
		}
		return false !== $wpdb->insert(
			self::idempotency_table(),
			array(
				'operation_scope'      => 'vip.intake.create',
				'principal_hash'       => (string) $principal['principal_hash'],
				'idempotency_key_hash' => $this->idempotency_key_hash( $key ),
				'request_digest'       => (string) $request_digest,
				'receipt_ref'          => (string) $receipt_ref,
				'response_code'        => (int) $response_code,
				'created_at'           => current_time( 'mysql', true ),
				'expires_at'           => gmdate( 'Y-m-d H:i:s', time() + self::IDEMPOTENCY_DAYS * DAY_IN_SECONDS ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	private function valid_normalization_evidence( $value ) {
		$keys = array( 'attestation_digest', 'classifier_revision', 'issued_at' );
		if ( ! is_array( $value ) || array_diff( $keys, array_keys( $value ) ) || array_diff( array_keys( $value ), $keys ) || ! self::digest( $value['attestation_digest'] ?? null ) || ! is_string( $value['classifier_revision'] ?? null ) || 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/', $value['classifier_revision'] ) || ! self::utc( $value['issued_at'] ?? null ) ) {
			return self::error( 'normalization_evidence_invalid', 'Verified vault and classifier evidence is required before intake storage.', 500 );
		}
		return array(
			'attestation_digest' => (string) $value['attestation_digest'],
			'classifier_revision'=> (string) $value['classifier_revision'],
			'issued_at'          => (string) $value['issued_at'],
		);
	}

	private function valid_principal( $principal ) {
		$user_id        = absint( $principal['user_id'] ?? 0 );
		$token_hash     = (string) ( $principal['token_hash'] ?? '' );
		$principal_hash = (string) ( $principal['principal_hash'] ?? '' );
		if ( ! self::digest( $principal_hash ) || ( 0 === $user_id && ! self::digest( $token_hash ) ) ) {
			return self::error( 'owner_invalid', 'A private browser owner could not be established.', 500 );
		}
		return array( 'user_id' => $user_id, 'token_hash' => 0 === $user_id ? $token_hash : '', 'principal_hash' => $principal_hash );
	}

	private function hydrate_canonical( $row ) {
		$row['id']             = (int) $row['id'];
		$row['envelope_bytes'] = (int) $row['envelope_bytes'];
		$row['legal_hold']     = (int) $row['legal_hold'];
		return $row;
	}

	private function hydrate_receipt( $row ) {
		foreach ( array( 'id', 'canonical_intake_id', 'owner_user_id', 'step_up_required', 'human_review_required', 'duplicate' ) as $field ) {
			$row[ $field ] = (int) $row[ $field ];
		}
		return $row;
	}

	private function sanitize_idempotency_key( $key ) {
		return substr( preg_replace( '/[^A-Za-z0-9._:-]/', '', (string) $key ), 0, 100 );
	}

	private function idempotency_key_hash( $key ) {
		return hash_hmac( 'sha256', (string) $key, wp_salt( 'nonce' ) );
	}

	private static function canonical_json( $value ) {
		$value = self::canonicalize( $value );
		$json  = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $json ) ? $json : '';
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_values( $value ) !== $value ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::canonicalize( $child );
		}
		return $value;
	}

	private static function iso_utc( $mysql_utc ) {
		$timestamp = strtotime( (string) $mysql_utc . ' UTC' );
		return false === $timestamp ? '1970-01-01T00:00:00Z' : gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function utc( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value ) ) {
			return false;
		}
		$timestamp = strtotime( $value );
		return false !== $timestamp && gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ) === $value;
	}

	private static function record_cleanup_status( $result ) {
		update_option(
			self::CLEANUP_STATUS_OPTION,
			array(
				'last_run_at'        => gmdate( 'c' ),
				'ok'                 => empty( $result['errors'] ),
				'errors'             => array_values( array_unique( (array) $result['errors'] ) ),
				'deleted_intakes'    => absint( $result['deleted_intakes'] ?? 0 ),
				'deleted_receipts'   => absint( $result['deleted_receipts'] ?? 0 ),
				'deleted_idempotency'=> absint( $result['deleted_idempotency'] ?? 0 ),
				'deleted_limits'     => absint( $result['deleted_limits'] ?? 0 ),
			),
			false
		);
	}

	private static function inspect_schema() {
		global $wpdb;
		$requirements = array(
			self::intakes_table() => array(
				'columns' => array( 'id', 'intake_ref', 'fingerprint', 'channel_event_digest', 'correlation_digest', 'content_digest', 'normalization_attestation_digest', 'classifier_revision', 'normalization_issued_at', 'envelope', 'envelope_bytes', 'created_at', 'retention_until', 'legal_hold' ),
				'unique'  => array( array( 'intake_ref' ), array( 'channel_event_digest' ), array( 'correlation_digest' ) ),
			),
			self::receipts_table() => array(
				'columns' => array( 'id', 'receipt_ref', 'canonical_intake_id', 'attempt_intake_ref', 'attempt_fingerprint', 'owner_user_id', 'owner_token_hash', 'principal_hash', 'normalization_attestation_digest', 'classifier_revision', 'public_state', 'message_code', 'step_up_required', 'human_review_required', 'duplicate', 'created_at', 'updated_at', 'expires_at', 'retention_until' ),
				'unique'  => array( array( 'receipt_ref' ) ),
			),
			self::idempotency_table() => array(
				'columns' => array( 'id', 'operation_scope', 'principal_hash', 'idempotency_key_hash', 'request_digest', 'receipt_ref', 'response_code', 'created_at', 'expires_at' ),
				'unique'  => array( array( 'operation_scope', 'principal_hash', 'idempotency_key_hash' ) ),
			),
			self::limits_table() => array(
				'columns' => array( 'limit_key', 'hits', 'expires_at' ),
				'unique'  => array( array( 'limit_key' ) ),
			),
		);
		$ready = 0;
		$transactional = 0;
		$required_indexes = 0;
		$ready_indexes = 0;
		$was_suppressed = $wpdb->suppress_errors();
		foreach ( $requirements as $table => $requirement ) {
			$identifier = str_replace( '`', '``', (string) $table );
			$columns    = $wpdb->get_col( "SHOW COLUMNS FROM `{$identifier}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted internal table.
			$status     = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', (string) $table ), ARRAY_A );
			$indexes    = $wpdb->get_results( "SHOW INDEX FROM `{$identifier}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted internal table.
			$engine_ok  = is_array( $status ) && 'innodb' === strtolower( (string) ( $status['Engine'] ?? '' ) );
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
			foreach ( $unique as &$columns_in_index ) {
				ksort( $columns_in_index );
				$columns_in_index = array_values( $columns_in_index );
			}
			unset( $columns_in_index );
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
			if ( is_array( $columns ) && ! array_diff( $requirement['columns'], $columns ) && $engine_ok && count( $requirement['unique'] ) === $table_ready_indexes ) {
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

	private static function error( $suffix, $message, $status ) {
		return new WP_Error( 'tra_vel_vip_intake_store_' . $suffix, $message, array( 'status' => (int) $status ) );
	}

	public static function intakes_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_vip_intakes';
	}

	public static function receipts_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_vip_intake_receipts';
	}

	public static function idempotency_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_vip_intake_idempotency';
	}

	public static function limits_table() {
		global $wpdb;
		return $wpdb->prefix . 'tra_vel_vip_intake_limits';
	}
}
