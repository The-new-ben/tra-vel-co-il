<?php
/**
 * Deterministic materializer for the private customer Trip Cockpit read model.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Customer_Trip_Cockpit_Factory {
	/**
	 * Build a minimized cockpit from already-validated normalized projections.
	 * This method performs no storage, network, supplier, payment, or authority work.
	 *
	 * @return array|WP_Error
	 */
	public static function create_projection( $source, $now ) {
		$source = Tra_Vel_Customer_Trip_Cockpit_Policy::validate_source( $source, $now );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$services = self::sorted_services( $source['services'] );
		$protections = self::sort_by( $source['protections'], 'protection_code' );
		$changes = self::sort_changes( $source['changes'] );
		$approvals = array_values( array_filter( $source['approvals'], function ( $approval ) {
			return in_array( $approval['status'], array( 'pending', 'expired' ), true );
		} ) );
		$approvals = self::sort_deadline_ref( $approvals, 'approval_ref' );
		$questions = array_values( array_filter( $source['questions'], function ( $question ) {
			return 'answered' !== $question['status'];
		} ) );
		$questions = self::sort_deadline_ref( $questions, 'question_ref' );
		$cases = self::sort_deadline_ref( $source['vip_cases'], 'case_ref' );
		$receipts = self::sort_deadline_ref( $source['trip_care_receipts'], 'receipt_ref' );
		$travelers = self::sort_deadline_ref( $source['traveler_readiness'], 'traveler_ref' );
		$money = self::money_status( $source['commerce_orders'] );

		$actions = self::collect_actions( $source, $services, $approvals, $questions, $cases, $receipts, $travelers );
		$urgent = $actions ? $actions[0] : null;

		$record = array(
			'contract_version'           => Tra_Vel_Customer_Trip_Cockpit_Policy::CONTRACT_VERSION,
			'environment'                => 'sandbox',
			'cockpit_ref'                => $source['cockpit_ref'],
			'trip_ref'                   => $source['trip_ref'],
			'owner_scope_digest'         => $source['owner_scope_digest'],
			'revision'                   => $source['revision'],
			'previous_projection_digest' => $source['previous_projection_digest'],
			'projection_digest'          => '',
			'trip_headline'              => $source['headline'],
			'current'                    => array(
				'phase'                    => $source['trip_health']['phase'],
				'health'                   => $source['trip_health']['health'],
				'registration_gate'        => $source['registration']['gate'],
				'registration_readiness'   => $source['registration']['readiness'],
				'affected_service_count'   => count( $source['trip_health']['affected_service_refs'] ),
				'unaffected_service_count' => count( $source['trip_health']['unaffected_service_refs'] ),
				'action_required'          => null !== $urgent,
				'verified_at'              => strcmp( $source['registration']['verified_at'], $source['trip_health']['verified_at'] ) >= 0 ? $source['registration']['verified_at'] : $source['trip_health']['verified_at'],
			),
			'urgent_next_action'         => $urgent,
			'protected'                  => $protections,
			'changed'                    => $changes,
			'approvals_required'         => $approvals,
			'unresolved_questions'       => $questions,
			'service_timeline'           => $services,
			'money_status'               => $money,
			'trip_care_cases'            => $cases,
			'trip_care_receipts'         => $receipts,
			'traveler_readiness'         => $travelers,
			'loyalty'                    => $source['loyalty'],
			'offline_pack'               => $source['offline_pack'],
			'last_verified_at'           => $source['observed_at'],
			'authority'                  => array(
				'authorization_effect'           => 'none',
				'supplier_action_started'        => false,
				'processor_action_started'       => false,
				'resolution_inferred'            => false,
				'combined_booking_status_exposed'=> false,
			),
			'data_boundary'              => array(
				'server_only'                  => true,
				'public_serialization_allowed' => false,
				'raw_identity_data_stored'     => false,
				'raw_payment_data_stored'      => false,
				'raw_medical_data_stored'      => false,
				'raw_provider_payload_stored'  => false,
				'bearer_secret_stored'         => false,
				'provider_execution_claimed'   => false,
			),
		);
		$record = Tra_Vel_Customer_Trip_Cockpit_Policy::seal_projection( $record );
		return Tra_Vel_Customer_Trip_Cockpit_Policy::validate_projection( $record, $now );
	}

	private static function collect_actions( $source, $services, $approvals, $questions, $cases, $receipts, $travelers ) {
		$actions = array();
		self::append_action( $actions, $source['registration']['next_action'], 'registration' );
		self::append_action( $actions, $source['trip_health']['next_action'], 'trip_health' );
		foreach ( $services as $service ) {
			self::append_action( $actions, $service['next_action'], 'service' );
		}
		foreach ( $approvals as $approval ) {
			self::append_action( $actions, array(
				'code'          => 'approval_required',
				'priority'      => $approval['priority'],
				'service_refs'  => $approval['service_refs'],
				'traveler_refs' => $approval['traveler_refs'],
				'deadline'      => $approval['deadline'],
				'truth_state'   => $approval['truth_state'],
			), 'approval' );
		}
		foreach ( $questions as $question ) {
			self::append_action( $actions, array(
				'code'          => $question['question_code'],
				'priority'      => $question['priority'],
				'service_refs'  => $question['service_refs'],
				'traveler_refs' => $question['traveler_refs'],
				'deadline'      => $question['deadline'],
				'truth_state'   => $question['truth_state'],
			), 'question' );
		}
		foreach ( $cases as $case ) {
			self::append_action( $actions, $case['next_action'], 'vip_case' );
		}
		foreach ( $receipts as $receipt ) {
			self::append_action( $actions, $receipt['next_action'], 'trip_care' );
		}
		foreach ( $source['commerce_orders'] as $order ) {
			self::append_action( $actions, $order['next_action'], 'commerce' );
		}
		self::append_action( $actions, $source['loyalty']['next_action'], 'loyalty' );
		foreach ( $travelers as $traveler ) {
			self::append_action( $actions, $traveler['next_action'], 'traveler' );
		}
		self::append_action( $actions, $source['offline_pack']['next_action'], 'offline_pack' );

		usort( $actions, function ( $left, $right ) {
			$priority = array( 'urgent' => 0, 'high' => 1, 'normal' => 2, 'low' => 3 );
			if ( $priority[ $left['priority'] ] !== $priority[ $right['priority'] ] ) {
				return $priority[ $left['priority'] ] < $priority[ $right['priority'] ] ? -1 : 1;
			}
			$left_deadline = null === $left['deadline'] ? '9999-12-31T23:59:59Z' : $left['deadline'];
			$right_deadline = null === $right['deadline'] ? '9999-12-31T23:59:59Z' : $right['deadline'];
			if ( $left_deadline !== $right_deadline ) {
				return strcmp( $left_deadline, $right_deadline );
			}
			$source_compare = strcmp( $left['source'], $right['source'] );
			return 0 !== $source_compare ? $source_compare : strcmp( $left['code'], $right['code'] );
		} );
		return $actions;
	}

	private static function append_action( &$actions, $action, $source ) {
		if ( null === $action ) {
			return;
		}
		$action['source'] = $source;
		$action = array(
			'code'          => $action['code'],
			'source'        => $action['source'],
			'priority'      => $action['priority'],
			'service_refs'  => self::sorted_values( $action['service_refs'] ),
			'traveler_refs' => self::sorted_values( $action['traveler_refs'] ),
			'deadline'      => $action['deadline'],
			'truth_state'   => $action['truth_state'],
		);
		$actions[] = $action;
	}

	private static function money_status( $orders ) {
		$money = array( 'funds' => array(), 'payments' => array(), 'refunds' => array(), 'settlements' => array() );
		foreach ( $orders as $order ) {
			$money['funds'][] = array(
				'order_ref'   => $order['order_ref'],
				'state'       => $order['funds_state'],
				'truth_state' => $order['funds_truth_state'],
				'verified_at' => $order['verified_at'],
			);
			$money['payments'][] = array(
				'order_ref'   => $order['order_ref'],
				'state'       => $order['payment_state'],
				'truth_state' => $order['payment_truth_state'],
				'verified_at' => $order['verified_at'],
			);
			$money['refunds'][] = array(
				'order_ref'   => $order['order_ref'],
				'state'       => $order['refund_state'],
				'truth_state' => $order['refund_truth_state'],
				'verified_at' => $order['verified_at'],
			);
			$money['settlements'][] = array(
				'order_ref'   => $order['order_ref'],
				'state'       => $order['settlement_state'],
				'truth_state' => $order['settlement_truth_state'],
				'verified_at' => $order['verified_at'],
			);
		}
		foreach ( array_keys( $money ) as $axis ) {
			$money[ $axis ] = self::sort_by( $money[ $axis ], 'order_ref' );
		}
		return $money;
	}

	private static function sorted_services( $services ) {
		foreach ( $services as &$service ) {
			$service['protected_codes'] = self::sorted_values( $service['protected_codes'] );
			$service['events'] = self::sort_events( $service['events'] );
			if ( null !== $service['next_action'] ) {
				$service['next_action']['service_refs'] = self::sorted_values( $service['next_action']['service_refs'] );
				$service['next_action']['traveler_refs'] = self::sorted_values( $service['next_action']['traveler_refs'] );
			}
		}
		unset( $service );
		usort( $services, function ( $left, $right ) {
			return $left['sequence'] === $right['sequence'] ? strcmp( $left['service_ref'], $right['service_ref'] ) : ( $left['sequence'] < $right['sequence'] ? -1 : 1 );
		} );
		return $services;
	}

	private static function sort_events( $events ) {
		usort( $events, function ( $left, $right ) {
			return $left['occurred_at'] === $right['occurred_at'] ? strcmp( $left['event_ref'], $right['event_ref'] ) : strcmp( $left['occurred_at'], $right['occurred_at'] );
		} );
		return $events;
	}

	private static function sort_changes( $changes ) {
		usort( $changes, function ( $left, $right ) {
			return $left['observed_at'] === $right['observed_at'] ? strcmp( $left['change_ref'], $right['change_ref'] ) : strcmp( $left['observed_at'], $right['observed_at'] );
		} );
		return $changes;
	}

	private static function sort_deadline_ref( $values, $ref_key ) {
		usort( $values, function ( $left, $right ) use ( $ref_key ) {
			$left_deadline = null === $left['deadline'] ? '9999-12-31T23:59:59Z' : $left['deadline'];
			$right_deadline = null === $right['deadline'] ? '9999-12-31T23:59:59Z' : $right['deadline'];
			return $left_deadline === $right_deadline ? strcmp( $left[ $ref_key ], $right[ $ref_key ] ) : strcmp( $left_deadline, $right_deadline );
		} );
		return $values;
	}

	private static function sort_by( $values, $key ) {
		usort( $values, function ( $left, $right ) use ( $key ) {
			return strcmp( $left[ $key ], $right[ $key ] );
		} );
		return $values;
	}

	private static function sorted_values( $values ) {
		$values = array_values( $values );
		sort( $values, SORT_STRING );
		return $values;
	}
}
