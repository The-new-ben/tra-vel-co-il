<?php
/**
 * Trusted server-side source assembler for the customer Trip Cockpit.
 *
 * This class is the only built-in bridge between authoritative lifecycle
 * repositories and the cockpit write store. A lifecycle event contains no
 * projection payload: the assembler derives owner identity and asks an
 * explicitly registered server provider for a closed normalized snapshot.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Customer_Trip_Cockpit_Source_Assembler {
	const LIFECYCLE_ACTION = 'tra_vel_customer_trip_cockpit_authoritative_lifecycle_event';
	const PROVIDER_FILTER  = 'tra_vel_customer_trip_cockpit_source_provider';

	const EVENT_KINDS = array(
		'registration_changed',
		'booking_changed',
		'servicing_changed',
		'supplier_reconciled',
		'payment_changed',
		'refund_changed',
		'settlement_changed',
		'trip_care_changed',
		'traveler_readiness_changed',
		'loyalty_changed',
		'offline_pack_changed',
		'local_service_changed',
	);

	const SNAPSHOT_KEYS = array(
		'headline',
		'registration',
		'trip_health',
		'services',
		'protections',
		'changes',
		'approvals',
		'questions',
		'vip_cases',
		'trip_care_receipts',
		'commerce_orders',
		'loyalty',
		'traveler_readiness',
		'offline_pack',
	);

	/** @var object */
	private $store;

	/** @var array|null Exact, short-lived authorization for one synchronous write. */
	private $write_context = null;

	/** @var array Re-entrancy guard keyed by an owner-bound trip digest. */
	private $refreshing = array();

	/** @param object|null $store Injectable only for deterministic server tests. */
	public function __construct( $store = null ) {
		$this->store = null === $store ? new Tra_Vel_Customer_Trip_Cockpit_Store() : $store;
	}

	/** Register the private lifecycle and narrowly scoped store-authorization hooks. */
	public function register_hooks() {
		add_action( self::LIFECYCLE_ACTION, array( $this, 'handle_lifecycle_event' ), 10, 4 );
		add_filter( 'tra_vel_customer_trip_cockpit_source_write_authorized', array( $this, 'authorize_source_write' ), 10, 4 );
	}

	/**
	 * WordPress action callback. Upstream code fires this only after its own
	 * authoritative transaction has committed.
	 *
	 * @return array|WP_Error
	 */
	public function handle_lifecycle_event( $owner_user_id, $trip_ref, $event_kind, $event_ref ) {
		return $this->refresh( $owner_user_id, $trip_ref, $event_kind, $event_ref );
	}

	/**
	 * Resolve current authoritative components and commit one sealed revision.
	 * No source, scope, owner digest, revision, or ancestry value is accepted
	 * from a browser or from the lifecycle event.
	 *
	 * @return array|WP_Error
	 */
	public function refresh( $owner_user_id, $trip_ref, $event_kind, $event_ref, $now = null ) {
		$owner_user_id = (int) $owner_user_id;
		$trip_ref      = is_string( $trip_ref ) ? $trip_ref : '';
		$event_kind    = is_string( $event_kind ) ? $event_kind : '';
		$event_ref     = is_string( $event_ref ) ? $event_ref : '';
		if (
			$owner_user_id < 1 ||
			! Tra_Vel_Traveler_Principal::valid_ref( $trip_ref, 'trip' ) ||
			! in_array( $event_kind, self::EVENT_KINDS, true ) ||
			! self::valid_event_ref( $event_ref )
		) {
			return self::error( 'event_invalid', 'The authoritative lifecycle event is invalid.', 400 );
		}

		$account_ref = Tra_Vel_Traveler_Principal::account_ref( $owner_user_id );
		if ( ! Tra_Vel_Traveler_Principal::valid_ref( $account_ref, 'account' ) ) {
			return self::error( 'owner_invalid', 'The lifecycle event owner could not be resolved.', 403 );
		}
		$refresh_key = hash_hmac( 'sha256', $owner_user_id . '|' . $account_ref . '|' . $trip_ref, wp_salt( 'secure_auth' ) );
		if ( isset( $this->refreshing[ $refresh_key ] ) ) {
			return self::error( 'refresh_in_progress', 'This trip is already being refreshed.', 409 );
		}

		$context = array(
			'event_ref'    => $event_ref,
			'event_kind'   => $event_kind,
			'owner_user_id'=> $owner_user_id,
			'account_ref'  => $account_ref,
			'trip_ref'     => $trip_ref,
		);
		$timestamp = $this->clock( $now, $context );
		if ( is_wp_error( $timestamp ) ) {
			return $timestamp;
		}

		$this->refreshing[ $refresh_key ] = true;
		try {
			$provider = apply_filters( self::PROVIDER_FILTER, null, $context );
			if ( ! $provider instanceof Tra_Vel_Customer_Trip_Cockpit_Source_Provider ) {
				return self::error( 'provider_unavailable', 'No authoritative Trip Cockpit source provider is available.', 503 );
			}
			$snapshot = $provider->get_authoritative_snapshot( $context );
			if ( is_wp_error( $snapshot ) ) {
				return $snapshot;
			}
			if ( null === $snapshot ) {
				return self::error( 'snapshot_unavailable', 'The authoritative trip snapshot is not available yet.', 404 );
			}
			if ( ! self::exact_object( $snapshot, self::SNAPSHOT_KEYS ) ) {
				return self::error( 'snapshot_shape_invalid', 'The authoritative snapshot must use the closed component contract.', 400 );
			}

			$current = $this->current_record( $owner_user_id, $account_ref, $trip_ref, $timestamp );
			if ( is_wp_error( $current ) ) {
				return $current;
			}
			$source = $this->assemble_source( $owner_user_id, $account_ref, $trip_ref, $snapshot, $current, $timestamp );
			if ( is_wp_error( $source ) ) {
				return $source;
			}
			$candidate = Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $source, $timestamp );
			if ( is_wp_error( $candidate ) ) {
				return $candidate;
			}

			if ( is_array( $current ) && self::same_customer_truth( $current['projection'], $candidate ) ) {
				return array( 'projection' => $current['projection'], 'created' => false, 'replayed' => true );
			}

			$this->write_context = array(
				'owner_user_id' => $owner_user_id,
				'account_ref'   => $account_ref,
				'trip_ref'      => $trip_ref,
				'source_digest' => self::canonical_digest( $source ),
			);
			try {
				return $this->store->commit_server_source( $owner_user_id, $account_ref, $source, $timestamp );
			} finally {
				$this->write_context = null;
			}
		} finally {
			unset( $this->refreshing[ $refresh_key ] );
		}
	}

	/**
	 * Authorize only the exact source synchronously assembled by this instance.
	 * Unrelated writers remain default-deny unless another trusted assembler
	 * independently authorizes its own source.
	 */
	public function authorize_source_write( $allowed, $owner_user_id, $account_ref, $source ) {
		if ( true === $allowed ) {
			return true;
		}
		if ( ! is_array( $this->write_context ) || ! is_array( $source ) || ! isset( $source['trip_ref'] ) ) {
			return false;
		}
		$actual_digest = self::canonical_digest( $source );
		return
			(int) $owner_user_id === $this->write_context['owner_user_id'] &&
			is_string( $account_ref ) && hash_equals( $this->write_context['account_ref'], $account_ref ) &&
			is_string( $source['trip_ref'] ) && hash_equals( $this->write_context['trip_ref'], $source['trip_ref'] ) &&
			'' !== $actual_digest && hash_equals( $this->write_context['source_digest'], $actual_digest );
	}

	/** @return array|WP_Error|null */
	private function current_record( $owner_user_id, $account_ref, $trip_ref, $timestamp ) {
		if ( ! is_object( $this->store ) || ! method_exists( $this->store, 'get_bound_projection' ) || ! method_exists( $this->store, 'commit_server_source' ) ) {
			return self::error( 'store_unavailable', 'The Trip Cockpit write store is unavailable.', 503 );
		}
		$current = $this->store->get_bound_projection( $trip_ref, null, $account_ref, $timestamp );
		if ( is_wp_error( $current ) ) {
			$data   = $current->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
			return 404 === $status ? null : $current;
		}
		$keys = array( 'projection', 'owner_user_id', 'account_ref', 'trip_ref', 'case_ref', 'owner_scope_digest' );
		if ( ! self::exact_object( $current, $keys ) || ! is_array( $current['projection'] ) || null !== $current['case_ref'] ) {
			return self::error( 'current_record_invalid', 'The current Trip Cockpit binding is invalid.', 503 );
		}
		$expected_scope = Tra_Vel_Traveler_Principal::cockpit_owner_scope_digest( $owner_user_id, $account_ref, $trip_ref );
		if (
			(int) $current['owner_user_id'] !== $owner_user_id ||
			! is_string( $current['account_ref'] ) || ! hash_equals( $account_ref, $current['account_ref'] ) ||
			! is_string( $current['trip_ref'] ) || ! hash_equals( $trip_ref, $current['trip_ref'] ) ||
			! is_string( $current['owner_scope_digest'] ) || ! hash_equals( $expected_scope, $current['owner_scope_digest'] )
		) {
			return self::error( 'current_binding_invalid', 'The current Trip Cockpit is not bound to this owner and trip.', 409 );
		}
		$projection = Tra_Vel_Customer_Trip_Cockpit_Policy::validate_projection( $current['projection'], $timestamp );
		if ( is_wp_error( $projection ) || $projection['trip_ref'] !== $trip_ref || $projection['owner_scope_digest'] !== $expected_scope ) {
			return self::error( 'current_projection_invalid', 'The current Trip Cockpit projection failed integrity validation.', 503 );
		}
		$current['projection'] = $projection;
		return $current;
	}

	/** @return array|WP_Error */
	private function assemble_source( $owner_user_id, $account_ref, $trip_ref, $snapshot, $current, $timestamp ) {
		$observed_at = self::latest_observation( $snapshot );
		if ( '' === $observed_at ) {
			return self::error( 'snapshot_clock_invalid', 'The authoritative snapshot has no verifiable observation clock.', 400 );
		}
		if ( is_array( $current ) && strcmp( $observed_at, $current['projection']['last_verified_at'] ) < 0 ) {
			return self::error( 'snapshot_regressed', 'An authoritative refresh cannot move the customer view backwards in time.', 409 );
		}

		$scope = Tra_Vel_Traveler_Principal::cockpit_owner_scope_digest( $owner_user_id, $account_ref, $trip_ref );
		if ( '' === $scope ) {
			return self::error( 'owner_scope_invalid', 'The Trip Cockpit owner scope could not be derived.', 503 );
		}
		if ( is_array( $current ) ) {
			$cockpit_ref = $current['projection']['cockpit_ref'];
			$revision    = (int) $current['projection']['revision'] + 1;
			$previous    = $current['projection']['projection_digest'];
		} else {
			$cockpit_ref = 'tv_cockpit_' . substr( hash_hmac( 'sha256', 'cockpit|' . $account_ref . '|' . $trip_ref, wp_salt( 'secure_auth' ) ), 0, 32 );
			$revision    = 1;
			$previous    = null;
		}

		$source = array_merge(
			array(
				'contract_version'           => Tra_Vel_Customer_Trip_Cockpit_Policy::CONTRACT_VERSION,
				'environment'                => 'sandbox',
				'cockpit_ref'                => $cockpit_ref,
				'trip_ref'                   => $trip_ref,
				'owner_scope_digest'         => $scope,
				'revision'                   => $revision,
				'previous_projection_digest' => $previous,
			),
			$snapshot,
			array(
				'observed_at'  => $observed_at,
				'data_boundary'=> array(
					'server_only'                       => true,
					'already_validated_projections_only'=> true,
					'raw_identity_data_stored'          => false,
					'raw_payment_data_stored'           => false,
					'raw_medical_data_stored'           => false,
					'raw_provider_payload_stored'       => false,
					'bearer_secret_stored'              => false,
				),
			)
		);
		$validated = Tra_Vel_Customer_Trip_Cockpit_Policy::validate_source( $source, $timestamp );
		return $validated;
	}

	/** Derive the source clock only from component observations, never deadlines. */
	private static function latest_observation( $snapshot ) {
		$times = array();
		foreach ( array( 'registration', 'trip_health', 'loyalty', 'offline_pack' ) as $component ) {
			if ( isset( $snapshot[ $component ] ) && is_array( $snapshot[ $component ] ) && isset( $snapshot[ $component ]['verified_at'] ) ) {
				$times[] = $snapshot[ $component ]['verified_at'];
			}
		}
		foreach ( isset( $snapshot['services'] ) && is_array( $snapshot['services'] ) ? $snapshot['services'] : array() as $service ) {
			$times[] = is_array( $service ) && isset( $service['verified_at'] ) ? $service['verified_at'] : '';
			$events = is_array( $service ) && isset( $service['events'] ) && is_array( $service['events'] ) ? $service['events'] : array();
			foreach ( $events as $event ) {
				$times[] = is_array( $event ) && isset( $event['occurred_at'] ) ? $event['occurred_at'] : '';
			}
		}
		foreach ( array( 'protections', 'approvals', 'questions', 'vip_cases', 'trip_care_receipts', 'commerce_orders', 'traveler_readiness' ) as $list ) {
			foreach ( isset( $snapshot[ $list ] ) && is_array( $snapshot[ $list ] ) ? $snapshot[ $list ] : array() as $item ) {
				$times[] = is_array( $item ) && isset( $item['verified_at'] ) ? $item['verified_at'] : '';
			}
		}
		foreach ( isset( $snapshot['changes'] ) && is_array( $snapshot['changes'] ) ? $snapshot['changes'] : array() as $change ) {
			$times[] = is_array( $change ) && isset( $change['observed_at'] ) ? $change['observed_at'] : '';
		}
		$times = array_values( array_filter( $times, function ( $value ) {
			return is_string( $value ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value );
		} ) );
		if ( ! $times ) {
			return '';
		}
		rsort( $times, SORT_STRING );
		return $times[0];
	}

	/** Ignore only revision-chain fields when detecting an exact semantic replay. */
	private static function same_customer_truth( $current, $candidate ) {
		foreach ( array( 'revision', 'previous_projection_digest', 'projection_digest' ) as $key ) {
			unset( $current[ $key ], $candidate[ $key ] );
		}
		$left  = self::canonical_digest( $current );
		$right = self::canonical_digest( $candidate );
		return '' !== $left && hash_equals( $left, $right );
	}

	/** @return int|WP_Error */
	private function clock( $now, $context ) {
		$timestamp = null === $now ? (int) apply_filters( 'tra_vel_customer_trip_cockpit_assembler_clock', time(), $context ) : (int) $now;
		if ( $timestamp < 1 || $timestamp > time() ) {
			return self::error( 'clock_invalid', 'The lifecycle refresh clock is invalid.', 400 );
		}
		return $timestamp;
	}

	private static function valid_event_ref( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_lifecycle_event_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function canonical_digest( $value ) {
		$encoded = wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::canonicalize( $child );
		}
		return $value;
	}

	private static function error( $suffix, $message, $status ) {
		return new WP_Error( 'tra_vel_customer_trip_cockpit_source_assembler_' . $suffix, $message, array( 'status' => (int) $status ) );
	}
}
