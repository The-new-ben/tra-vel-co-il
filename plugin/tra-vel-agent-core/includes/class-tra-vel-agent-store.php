<?php
/**
 * Durable run, event, and approval persistence.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Agent_Store {
	const DB_VERSION         = '1.1.0';
	const DB_VERSION_OPTION  = 'tra_vel_agent_db_version';
	const DEFAULT_EXPIRY_HRS = 24;

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
		) {$charset};" );

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
		) {$charset};" );

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
		) {$charset};" );

		dbDelta( "CREATE TABLE {$limits} (
			counter_key varchar(191) NOT NULL,
			counter_value int(10) unsigned NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (counter_key),
			KEY expires_at (expires_at)
		) {$charset};" );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Delete expired guest/run state in bounded batches.
	 */
	public static function cleanup_expired() {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::runs_table() . ' WHERE expires_at < %s ORDER BY id ASC LIMIT 500', current_time( 'mysql', true ) ) );
		foreach ( is_array( $ids ) ? $ids : array() as $run_id ) {
			$wpdb->delete( self::approvals_table(), array( 'run_id' => absint( $run_id ) ), array( '%d' ) );
			$wpdb->delete( self::events_table(), array( 'run_id' => absint( $run_id ) ), array( '%d' ) );
			$wpdb->delete( self::runs_table(), array( 'id' => absint( $run_id ) ), array( '%d' ) );
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::limits_table() . ' WHERE expires_at < %s LIMIT 1000', current_time( 'mysql', true ) ) );
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
				'owner_token_hash'     => hash( 'sha256', $token ),
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

	public function get_run_by_uuid( $run_uuid ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::runs_table() . ' WHERE run_uuid = %s LIMIT 1', (string) $run_uuid ), ARRAY_A );
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
		return is_string( $token ) && strlen( $token ) >= 32 && hash_equals( (string) $run['owner_token_hash'], hash( 'sha256', $token ) );
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
