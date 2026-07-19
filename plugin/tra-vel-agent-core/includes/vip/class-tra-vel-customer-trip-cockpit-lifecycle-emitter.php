<?php
/**
 * Relay committed server lifecycle events into the durable cockpit refresh queue.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Customer_Trip_Cockpit_Lifecycle_Emitter {
	public function register_hooks() {
		add_action( 'tra_vel_traveler_registration_committed', array( $this, 'registration_committed' ), 10, 3 );
		add_action( 'tra_vel_commerce_order_committed', array( $this, 'booking_committed' ), 10, 3 );
		add_action( 'tra_vel_postbooking_servicing_committed', array( $this, 'servicing_committed' ), 10, 3 );
		add_action( 'tra_vel_commerce_funds_flow_committed', array( $this, 'funds_flow_committed' ), 10, 4 );
	}

	/** Relay a real registration aggregate only after its durable transaction commits. */
	public function registration_committed( $owner_user_id, $aggregate, $transition = null ) {
		$now = gmdate( 'Y-m-d\TH:i:s\Z' );
		$aggregate = Tra_Vel_Traveler_Registration_Schema::validate_aggregate( $aggregate, $now );
		if ( is_wp_error( $aggregate ) ) {
			return $aggregate;
		}
		$registration = $aggregate['registration'];
		$owner_user_id = (int) $owner_user_id;
		$account_ref = Tra_Vel_Traveler_Principal::account_ref( $owner_user_id );
		if ( $owner_user_id < 1 || $registration['account_ref'] !== $account_ref ) {
			return self::error( 'registration_owner_invalid', 'The committed registration is not bound to its server owner.' );
		}
		$source_ref = is_array( $transition ) && isset( $transition['transition_ref'] ) ? $transition['transition_ref'] : $registration['registration_ref'];
		return $this->relay( $owner_user_id, $registration['trip_ref'], 'registration_changed', $source_ref );
	}

	public function booking_committed( $owner_user_id, $trip_ref, $source_ref ) {
		return $this->relay( $owner_user_id, $trip_ref, 'booking_changed', $source_ref );
	}

	public function servicing_committed( $owner_user_id, $trip_ref, $source_ref ) {
		return $this->relay( $owner_user_id, $trip_ref, 'servicing_changed', $source_ref );
	}

	public function funds_flow_committed( $owner_user_id, $trip_ref, $source_ref, $event_kind = 'payment_changed' ) {
		$allowed = array( 'payment_changed', 'refund_changed', 'settlement_changed' );
		return $this->relay( $owner_user_id, $trip_ref, in_array( $event_kind, $allowed, true ) ? $event_kind : '', $source_ref );
	}

	/** Fire the payload-free event consumed by the durable refresh queue. */
	private function relay( $owner_user_id, $trip_ref, $event_kind, $source_ref ) {
		$owner_user_id = (int) $owner_user_id;
		if (
			$owner_user_id < 1 ||
			! Tra_Vel_Traveler_Principal::valid_ref( $trip_ref, 'trip' ) ||
			! in_array( $event_kind, Tra_Vel_Customer_Trip_Cockpit_Source_Assembler::EVENT_KINDS, true ) ||
			! self::opaque_ref( $source_ref )
		) {
			return self::error( 'event_invalid', 'The committed lifecycle record cannot be relayed.' );
		}
		$event_ref = 'tv_lifecycle_event_' . substr(
			hash_hmac( 'sha256', $event_kind . '|' . $owner_user_id . '|' . $trip_ref . '|' . $source_ref, wp_salt( 'secure_auth' ) ),
			0,
			32
		);
		do_action( Tra_Vel_Customer_Trip_Cockpit_Source_Assembler::LIFECYCLE_ACTION, $owner_user_id, $trip_ref, $event_kind, $event_ref );
		return true;
	}

	private static function opaque_ref( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_[a-z0-9_]+_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_customer_trip_cockpit_lifecycle_' . $suffix, $message, array( 'status' => 400 ) );
	}
}
