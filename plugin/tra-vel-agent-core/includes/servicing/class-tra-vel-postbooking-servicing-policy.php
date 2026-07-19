<?php
/**
 * Fail-closed private semantics for post-booking flight and lodging service.
 *
 * The policy deliberately separates supplier reservation, fulfillment,
 * payment, refund and settlement truth. It produces review-only projections;
 * it cannot dispatch, persist, charge, refund, reissue or alter inventory.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Postbooking_Servicing_Policy {
	const MAX_MONEY_MINOR = 1000000000000;

	/**
	 * Validate one complete immutable servicing observation.
	 *
	 * @param array $snapshot Complete closed snapshot.
	 * @param int   $now      Positive injected UTC epoch.
	 * @return array|WP_Error
	 */
	public static function validate_snapshot( $snapshot, $now ) {
		$keys = array(
			'contract_version', 'environment', 'data_mode', 'servicing_case_ref',
			'snapshot_version', 'previous_snapshot_digest', 'snapshot_digest',
			'owner_scope_digest', 'bindings', 'change_class', 'affected_scope',
			'flight_state', 'lodging_state', 'financial_differentials',
			'independent_states', 'message_delivery', 'observed_at', 'boundary',
		);
		if ( self::contains_sensitive_material( $snapshot ) ) {
			return self::error( 'sensitive_material_rejected', 'Servicing snapshots accept opaque references and evidence digests, never raw identity, credentials, payment-card data or message bodies.' );
		}
		if (
			! self::exact_object( $snapshot, $keys ) ||
			Tra_Vel_Postbooking_Servicing_Taxonomy::CONTRACT_VERSION !== $snapshot['contract_version'] ||
			'sandbox' !== $snapshot['environment'] ||
			'synthetic_demo' !== $snapshot['data_mode'] ||
			! is_int( $now ) || $now < 1
		) {
			return self::error( 'snapshot_shape_invalid', 'The servicing observation must match the closed private sandbox contract.' );
		}
		if (
			! self::ref( $snapshot['servicing_case_ref'], 'servicing_case' ) ||
			! is_int( $snapshot['snapshot_version'] ) || $snapshot['snapshot_version'] < 1 ||
			! self::nullable_digest( $snapshot['previous_snapshot_digest'] ) ||
			! self::digest( $snapshot['snapshot_digest'] ) ||
			! self::digest( $snapshot['owner_scope_digest'] )
		) {
			return self::error( 'snapshot_identity_invalid', 'The servicing case version, owner scope and immutable lineage must be exact.' );
		}
		if (
			( 1 === $snapshot['snapshot_version'] && null !== $snapshot['previous_snapshot_digest'] ) ||
			( $snapshot['snapshot_version'] > 1 && null === $snapshot['previous_snapshot_digest'] )
		) {
			return self::error( 'snapshot_lineage_invalid', 'First observations cannot have a predecessor and later observations must bind one predecessor digest.' );
		}

		$bindings = self::bindings( $snapshot['bindings'] );
		if ( is_wp_error( $bindings ) ) {
			return $bindings;
		}
		$change_class = Tra_Vel_Postbooking_Servicing_Taxonomy::member( $snapshot['change_class'], Tra_Vel_Postbooking_Servicing_Taxonomy::CHANGE_CLASSES );
		if ( '' === $change_class ) {
			return self::error( 'change_class_invalid', 'Voluntary, planned, day-of-travel and lodging changes must remain distinct.' );
		}
		$scope = self::scope( $snapshot['affected_scope'] );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$flight = self::flight_state( $snapshot['flight_state'], $scope );
		if ( is_wp_error( $flight ) ) {
			return $flight;
		}
		$lodging = self::lodging_state( $snapshot['lodging_state'], $scope );
		if ( is_wp_error( $lodging ) ) {
			return $lodging;
		}
		if ( ! $flight['applicable'] && ! $lodging['applicable'] ) {
			return self::error( 'vertical_scope_empty', 'A servicing observation must cover a flight, lodging reservation, or both.' );
		}
		if ( $flight['applicable'] && ( ! $scope['all_traveler_refs'] || ! $scope['all_segment_refs'] || ! $scope['all_order_item_refs'] || ! $scope['affected_traveler_refs'] || ! $scope['affected_segment_refs'] || ! $scope['affected_order_item_refs'] ) ) {
			return self::error( 'flight_scope_incomplete', 'Flight servicing requires exact total and affected traveler, segment and order-item scope.' );
		}
		if ( $lodging['applicable'] && ( ! $scope['all_room_refs'] || ! $scope['all_guest_refs'] || ! $scope['affected_room_refs'] || ! $scope['affected_guest_refs'] || null === $scope['date_scope']['check_in_date'] || null === $scope['date_scope']['check_out_date'] ) ) {
			return self::error( 'lodging_scope_incomplete', 'Lodging servicing requires exact total and affected room, guest and date scope.' );
		}

		$differentials = self::financial_differentials( $snapshot['financial_differentials'], $scope );
		if ( is_wp_error( $differentials ) ) {
			return $differentials;
		}
		$states = self::independent_states( $snapshot['independent_states'] );
		if ( is_wp_error( $states ) ) {
			return $states;
		}
		$messages = self::message_delivery( $snapshot['message_delivery'], $now );
		if ( is_wp_error( $messages ) ) {
			return $messages;
		}
		$observed = self::utc_timestamp( $snapshot['observed_at'] );
		if ( false === $observed || $observed > $now ) {
			return self::error( 'observation_clock_invalid', 'The source observation must be a valid UTC instant no later than the injected clock.' );
		}
		$boundary = array(
			'server_only'                  => true,
			'public_serialization_allowed' => false,
			'planning_only'                => true,
			'synthetic_demo'               => true,
			'creates_reservation'          => false,
			'issues_ticket_or_emd'          => false,
			'changes_coupon'               => false,
			'changes_lodging_inventory'    => false,
			'creates_payment'              => false,
			'creates_refund'               => false,
			'creates_settlement'           => false,
			'supplier_dispatched'          => false,
			'processor_called'             => false,
		);
		if ( self::canonical_digest( $snapshot['boundary'] ) !== self::canonical_digest( $boundary ) ) {
			return self::error( 'snapshot_boundary_invalid', 'Servicing truth must remain private, synthetic, review-only and zero-dispatch.' );
		}
		if ( ! hash_equals( $snapshot['snapshot_digest'], self::snapshot_digest( $snapshot ) ) ) {
			return self::error( 'snapshot_digest_mismatch', 'The servicing observation changed after its immutable digest was sealed.', 409 );
		}

		$snapshot['bindings']                = $bindings;
		$snapshot['change_class']            = $change_class;
		$snapshot['affected_scope']          = $scope;
		$snapshot['flight_state']            = $flight;
		$snapshot['lodging_state']           = $lodging;
		$snapshot['financial_differentials'] = $differentials;
		$snapshot['independent_states']      = $states;
		$snapshot['message_delivery']        = $messages;
		return $snapshot;
	}

	/**
	 * Materialize all deterministic plan fields except identity and timestamps.
	 *
	 * @param array $snapshot Valid complete snapshot.
	 * @param int   $now      Positive injected UTC epoch.
	 * @return array|WP_Error
	 */
	public static function project_snapshot( $snapshot, $now ) {
		$snapshot = self::validate_snapshot( $snapshot, $now );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		$scope = $snapshot['affected_scope'];
		$scope_resolution = array(
			'affected_traveler_refs' => $scope['affected_traveler_refs'],
			'preserved_traveler_refs'=> self::difference( $scope['all_traveler_refs'], $scope['affected_traveler_refs'] ),
			'affected_segment_refs'  => $scope['affected_segment_refs'],
			'preserved_segment_refs' => self::difference( $scope['all_segment_refs'], $scope['affected_segment_refs'] ),
			'affected_order_item_refs'=> $scope['affected_order_item_refs'],
			'preserved_order_item_refs'=> self::difference( $scope['all_order_item_refs'], $scope['affected_order_item_refs'] ),
			'affected_room_refs'     => $scope['affected_room_refs'],
			'preserved_room_refs'    => self::difference( $scope['all_room_refs'], $scope['affected_room_refs'] ),
			'affected_guest_refs'    => $scope['affected_guest_refs'],
			'preserved_guest_refs'   => self::difference( $scope['all_guest_refs'], $scope['affected_guest_refs'] ),
			'date_scope'             => $scope['date_scope'],
		);

		$truth_codes = array( 'supplier_order' );
		$actions = array( self::action( 'retrieve_current_supplier_order', $scope_resolution, array( 'human_operator_review' ) ) );
		if ( $snapshot['flight_state']['applicable'] ) {
			$truth_codes = array_merge( $truth_codes, array( 'flight_reservation', 'ticket_issuance', 'ticket_coupons', 'emd_fulfillment', 'servicing_ownership' ) );
			$actions[] = self::action( 'verify_reservation_separately', $scope_resolution, array( 'human_operator_review' ) );
			$actions[] = self::action( 'verify_ticket_issuance', $scope_resolution, array( 'human_operator_review' ) );
			$actions[] = self::action( 'verify_coupon_statuses', $scope_resolution, array( 'human_operator_review' ) );
			$actions[] = self::action( 'verify_emd_fulfillment', $scope_resolution, array( 'human_operator_review' ) );
			$actions[] = self::action( 'verify_servicing_owner', $scope_resolution, array( 'human_operator_review' ) );
			if ( 'voluntary_change' === $snapshot['change_class'] ) {
				$truth_codes[] = 'voluntary_change_quote';
				$actions[] = self::action( 'quote_voluntary_change_rules', $scope_resolution, array( 'customer_approval', 'human_operator_review' ) );
			} elseif ( in_array( $snapshot['change_class'], array( 'planned_schedule_change', 'day_of_travel_disruption' ), true ) ) {
				$truth_codes[] = 'involuntary_entitlement';
				$actions[] = self::action( 'verify_involuntary_entitlements', $scope_resolution, array( 'human_operator_review', 'supplier_change_approval' ) );
			}
			$actions[] = self::action( 'preserve_unaffected_flight_scope', $scope_resolution, array( 'human_operator_review' ) );
		}
		if ( $snapshot['lodging_state']['applicable'] ) {
			$truth_codes = array_merge( $truth_codes, array( 'lodging_reservation', 'room_guest_date_occupancy', 'no_show', 'inventory_restoration', 'message_delivery', 'authoritative_message_retrieval' ) );
			$actions[] = self::action( 'retrieve_current_lodging_reservation', $scope_resolution, array( 'human_operator_review' ) );
			$actions[] = self::action( 'reconcile_room_guest_date_occupancy', $scope_resolution, array( 'customer_approval', 'human_operator_review' ) );
			$actions[] = self::action( 'reconcile_no_show_separately', $scope_resolution, array( 'human_operator_review' ) );
			$actions[] = self::action( 'verify_inventory_restoration', $scope_resolution, array( 'human_operator_review' ) );
			$actions[] = self::action( 'verify_message_delivery', $scope_resolution, array( 'human_operator_review' ) );
			$actions[] = self::action( 'perform_authoritative_message_retrieval', $scope_resolution, array( 'human_operator_review' ) );
			$actions[] = self::action( 'preserve_unaffected_lodging_scope', $scope_resolution, array( 'human_operator_review' ) );
		}

		$outcomes = array();
		$differential_refs = array();
		$component_states = array();
		$has_add_collect = false;
		$has_traveler_repayment = false;
		foreach ( $snapshot['financial_differentials'] as $differential ) {
			$differential_refs[] = $differential['differential_ref'];
			$outcomes = array_merge( $outcomes, $differential['outcome_types'] );
			$component_states[] = array(
				'differential_ref'         => $differential['differential_ref'],
				'add_collect_state'        => $differential['states']['add_collect_state'],
				'supplier_refund_state'    => $differential['states']['supplier_refund_state'],
				'residual_value_state'     => $differential['states']['residual_value_state'],
				'reusable_value_state'     => $differential['states']['reusable_value_state'],
				'traveler_repayment_state' => $differential['states']['traveler_repayment_state'],
				'settlement_state'         => $differential['states']['settlement_state'],
			);
			if ( in_array( 'add_collect', $differential['outcome_types'], true ) ) {
				$has_add_collect = true;
			}
			if ( $differential['traveler_repayment_minor'] > 0 ) {
				$has_traveler_repayment = true;
			}
		}
		$differential_refs = self::sorted_unique( $differential_refs );
		$outcomes = self::canonical_outcomes( $outcomes );
		foreach ( $outcomes as $outcome ) {
			$action_map = array(
				'add_collect'    => 'reconcile_add_collect_separately',
				'refund'         => 'reconcile_supplier_refund_separately',
				'residual_value' => 'reconcile_residual_value_separately',
				'reusable_value' => 'reconcile_reusable_value_separately',
				'even_exchange'  => 'record_even_exchange_without_netting',
			);
			$truth_codes[] = 'financial_' . $outcome;
			$actions[] = self::action( $action_map[ $outcome ], $scope_resolution, array( 'financial_approval', 'human_operator_review' ) );
		}
		if ( $has_traveler_repayment ) {
			$truth_codes[] = 'traveler_repayment';
			$actions[] = self::action( 'reconcile_traveler_repayment_separately', $scope_resolution, array( 'financial_approval', 'human_operator_review' ) );
		}
		if ( $snapshot['financial_differentials'] ) {
			$truth_codes[] = 'supplier_settlement';
			$actions[] = self::action( 'reconcile_supplier_settlement_separately', $scope_resolution, array( 'financial_approval', 'human_operator_review' ) );
		}
		$truth_codes = self::sorted_unique( $truth_codes );
		$truth_checks = array();
		foreach ( $truth_codes as $code ) {
			$truth_checks[] = array( 'truth_code' => $code, 'state' => 'required', 'supplier_dispatched' => false );
		}

		$approvals = array( 'human_operator_review' );
		if ( in_array( $snapshot['change_class'], array( 'voluntary_change', 'traveler_lodging_change' ), true ) ) {
			$approvals[] = 'customer_approval';
		}
		if ( in_array( $snapshot['change_class'], array( 'planned_schedule_change', 'day_of_travel_disruption', 'supplier_initiated_lodging_change' ), true ) ) {
			$approvals[] = 'supplier_change_approval';
		}
		if ( $snapshot['financial_differentials'] ) {
			$approvals[] = 'financial_approval';
		}
		if ( $has_add_collect ) {
			$approvals[] = 'customer_approval';
		}
		$approvals = self::approval_order( $approvals );

		$plan_state = self::needs_evidence( $snapshot ) ? 'evidence_required' : 'ready_for_human_review';
		return array(
			'change_class'      => $snapshot['change_class'],
			'plan_state'        => $plan_state,
			'scope_resolution'  => $scope_resolution,
			'truth_checks'      => $truth_checks,
			'action_queue'      => $actions,
			'financial_handling'=> array(
				'differential_refs'                    => $differential_refs,
				'outcomes_present'                     => $outcomes,
				'component_states'                     => $component_states,
				'netting_prohibited'                   => true,
				'no_derived_net_amount'                 => true,
				'existing_commerce_ledgers_authoritative'=> true,
				'new_payment_state'                    => $has_add_collect ? 'separate_authorization_required' : 'not_required',
				'traveler_repayment_state'             => $has_traveler_repayment ? 'separate_reconciliation_required' : 'not_required',
				'customer_refund_state'                => $snapshot['independent_states']['customer_refund_state'],
				'supplier_settlement_state'            => $snapshot['independent_states']['supplier_settlement_state'],
			),
			'state_axes'         => $snapshot['independent_states'],
			'lodging_reconciliation' => array(
				'reservation_state'            => $snapshot['lodging_state']['applicable'] ? 'independent_review_required' : 'not_applicable',
				'room_state'                   => $snapshot['lodging_state']['applicable'] ? 'independent_review_required' : 'not_applicable',
				'guest_state'                  => $snapshot['lodging_state']['applicable'] ? 'independent_review_required' : 'not_applicable',
				'date_state'                   => $snapshot['lodging_state']['applicable'] ? 'independent_review_required' : 'not_applicable',
				'occupancy_state'              => $snapshot['lodging_state']['applicable'] ? 'independent_review_required' : 'not_applicable',
				'no_show_state'                => $snapshot['lodging_state']['applicable'] ? 'independent_review_required' : 'not_applicable',
				'inventory_restoration_state'  => $snapshot['lodging_state']['applicable'] ? 'independent_review_required' : 'not_applicable',
			),
			'communication_reconciliation' => array(
				'delivery_evidence_only'          => true,
				'authoritative_retrieval_state'   => $snapshot['message_delivery']['authoritative_retrieval_state'],
				'booking_state_inference_allowed' => false,
				'retry_state'                     => in_array( $snapshot['message_delivery']['webhook_health'], array( 'failed', 'degraded', 'unknown' ), true ) ? 'review_required' : 'not_required',
			),
			'required_approvals' => $approvals,
		);
	}

	/**
	 * Validate a materialized plan against its exact current source snapshot.
	 *
	 * @return array|WP_Error
	 */
	public static function validate_plan( $plan, $snapshot, $expected_binding, $now ) {
		$keys = array(
			'contract_version', 'environment', 'data_mode', 'plan_ref', 'plan_digest',
			'input_binding', 'change_class', 'plan_state', 'scope_resolution',
			'truth_checks', 'action_queue', 'financial_handling', 'state_axes',
			'lodging_reconciliation', 'communication_reconciliation',
			'required_approvals', 'evaluated_at', 'boundary',
		);
		if ( self::contains_sensitive_material( $plan ) || ! self::exact_object( $plan, $keys ) || ! is_int( $now ) || $now < 1 ) {
			return self::error( 'plan_shape_invalid', 'The servicing plan must be the exact private side-effect-free contract.' );
		}
		$snapshot = self::validate_snapshot( $snapshot, $now );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		$binding = self::expected_binding( $snapshot );
		if ( ! self::exact_object( $expected_binding, array_keys( $binding ) ) || self::canonical_digest( $expected_binding ) !== self::canonical_digest( $binding ) ) {
			return self::error( 'current_binding_mismatch', 'The caller must bind the exact current owner, trip, supplier order, commerce order and servicing snapshot digests.', 409 );
		}
		if (
			Tra_Vel_Postbooking_Servicing_Taxonomy::CONTRACT_VERSION !== $plan['contract_version'] ||
			'sandbox' !== $plan['environment'] || 'synthetic_demo' !== $plan['data_mode'] ||
			self::canonical_digest( $plan['input_binding'] ) !== self::canonical_digest( $binding )
		) {
			return self::error( 'plan_binding_invalid', 'The plan must bind the exact current immutable source identities and digests.', 409 );
		}
		$projection = self::project_snapshot( $snapshot, $now );
		if ( is_wp_error( $projection ) ) {
			return $projection;
		}
		foreach ( $projection as $key => $value ) {
			if ( self::canonical_digest( $plan[ $key ] ) !== self::canonical_digest( $value ) ) {
				return self::error( 'plan_projection_mismatch', 'The servicing plan no longer replays exactly from the bound snapshot.', 409 );
			}
		}
		$evaluated = self::utc_timestamp( $plan['evaluated_at'] );
		$observed = self::utc_timestamp( $snapshot['observed_at'] );
		if ( false === $evaluated || $evaluated > $now || $evaluated < $observed ) {
			return self::error( 'plan_clock_invalid', 'Plan evaluation must follow the source observation and not exceed the injected clock.' );
		}
		$boundary = array(
			'server_only'                  => true,
			'public_serialization_allowed' => false,
			'planning_only'                => true,
			'creates_order'                => false,
			'issues_ticket_or_emd'          => false,
			'changes_supplier_booking'      => false,
			'changes_inventory'             => false,
			'creates_payment'               => false,
			'creates_refund'                => false,
			'creates_settlement'            => false,
			'supplier_dispatched'           => false,
			'processor_called'              => false,
			'message_sent'                  => false,
		);
		if ( self::canonical_digest( $plan['boundary'] ) !== self::canonical_digest( $boundary ) ) {
			return self::error( 'plan_boundary_invalid', 'A review plan cannot execute supplier, inventory, ticket, message or financial work.' );
		}
		$expected_ref = 'tv_servicing_plan_' . substr( self::canonical_digest( $binding ), 0, 24 );
		if ( ! self::ref( $plan['plan_ref'], 'servicing_plan' ) || ! hash_equals( $expected_ref, $plan['plan_ref'] ) ) {
			return self::error( 'plan_ref_invalid', 'The plan reference must derive from the exact immutable input binding.', 409 );
		}
		if ( ! self::digest( $plan['plan_digest'] ) || ! hash_equals( $plan['plan_digest'], self::plan_digest( $plan ) ) ) {
			return self::error( 'plan_digest_mismatch', 'The servicing plan changed after its immutable digest was sealed.', 409 );
		}
		return $plan;
	}

	/** Seal a snapshot digest after all fields are populated. */
	public static function seal_snapshot( $snapshot ) {
		$snapshot['snapshot_digest'] = self::snapshot_digest( $snapshot );
		return $snapshot;
	}

	/** Seal a materialized plan digest after all fields are populated. */
	public static function seal_plan( $plan ) {
		$plan['plan_digest'] = self::plan_digest( $plan );
		return $plan;
	}

	/** Exact caller-observed identity and lineage required by the factory. */
	public static function expected_binding( $snapshot ) {
		return array(
			'owner_scope_digest'     => $snapshot['owner_scope_digest'],
			'servicing_case_ref'     => $snapshot['servicing_case_ref'],
			'snapshot_version'       => $snapshot['snapshot_version'],
			'snapshot_digest'        => $snapshot['snapshot_digest'],
			'trip_ref'               => $snapshot['bindings']['trip_ref'],
			'trip_digest'            => $snapshot['bindings']['trip_digest'],
			'supplier_order_ref'     => $snapshot['bindings']['supplier_order_ref'],
			'supplier_order_digest'  => $snapshot['bindings']['supplier_order_digest'],
			'commerce_order_ref'     => $snapshot['bindings']['commerce_order_ref'],
			'commerce_order_digest'  => $snapshot['bindings']['commerce_order_digest'],
		);
	}

	/** Digest all snapshot content except the digest field itself. */
	public static function snapshot_digest( $snapshot ) {
		$copy = $snapshot;
		unset( $copy['snapshot_digest'] );
		return self::canonical_digest( $copy );
	}

	/** Digest all plan content except the digest field itself. */
	public static function plan_digest( $plan ) {
		$copy = $plan;
		unset( $copy['plan_digest'] );
		return self::canonical_digest( $copy );
	}

	private static function bindings( $value ) {
		$keys = array( 'trip_ref', 'trip_digest', 'supplier_order_ref', 'supplier_order_digest', 'commerce_order_ref', 'commerce_order_digest' );
		if (
			! self::exact_object( $value, $keys ) || ! self::ref( $value['trip_ref'], 'trip' ) ||
			! self::digest( $value['trip_digest'] ) || ! self::ref( $value['supplier_order_ref'], 'supplier_order' ) ||
			! self::digest( $value['supplier_order_digest'] ) || ! self::ref( $value['commerce_order_ref'], 'order' ) ||
			! self::digest( $value['commerce_order_digest'] )
		) {
			return self::error( 'bindings_invalid', 'The snapshot must bind exact trip, supplier-order and commerce-order identities and digests.' );
		}
		return $value;
	}

	private static function scope( $value ) {
		$keys = array(
			'all_traveler_refs', 'affected_traveler_refs', 'all_segment_refs',
			'affected_segment_refs', 'all_order_item_refs', 'affected_order_item_refs',
			'all_room_refs', 'affected_room_refs', 'all_guest_refs',
			'affected_guest_refs', 'date_scope',
		);
		if ( ! self::exact_object( $value, $keys ) ) {
			return self::error( 'scope_shape_invalid', 'Affected scope must name complete and affected sets for every servicing dimension.' );
		}
		$specs = array(
			'all_traveler_refs'       => 'traveler',
			'affected_traveler_refs'  => 'traveler',
			'all_segment_refs'        => 'segment',
			'affected_segment_refs'   => 'segment',
			'all_order_item_refs'     => 'order_item',
			'affected_order_item_refs'=> 'order_item',
			'all_room_refs'           => 'room',
			'affected_room_refs'      => 'room',
			'all_guest_refs'          => 'guest',
			'affected_guest_refs'     => 'guest',
		);
		foreach ( $specs as $key => $prefix ) {
			if ( ! self::ref_list( $value[ $key ], $prefix, true ) ) {
				return self::error( 'scope_refs_invalid', 'Scope references must be unique, sorted and opaque.' );
			}
		}
		foreach ( array( 'traveler', 'segment', 'order_item', 'room', 'guest' ) as $dimension ) {
			if ( ! self::subset( $value[ 'affected_' . $dimension . '_refs' ], $value[ 'all_' . $dimension . '_refs' ] ) ) {
				return self::error( 'affected_scope_not_subset', 'Every affected reference must belong to the complete order scope.' );
			}
		}
		$date_scope = $value['date_scope'];
		if ( ! self::exact_object( $date_scope, array( 'check_in_date', 'check_out_date' ) ) || ! self::nullable_date( $date_scope['check_in_date'] ) || ! self::nullable_date( $date_scope['check_out_date'] ) || ( ( null === $date_scope['check_in_date'] ) !== ( null === $date_scope['check_out_date'] ) ) || ( null !== $date_scope['check_in_date'] && $date_scope['check_out_date'] <= $date_scope['check_in_date'] ) ) {
			return self::error( 'date_scope_invalid', 'Lodging dates must be both absent or one chronological half-open stay range.' );
		}
		return $value;
	}

	private static function flight_state( $value, $scope ) {
		$keys = array( 'applicable', 'reservation', 'ticketing', 'tickets', 'emds' );
		if ( ! self::exact_object( $value, $keys ) || ! is_bool( $value['applicable'] ) ) {
			return self::error( 'flight_shape_invalid', 'Flight state must match the closed reservation, ticket, coupon and EMD contract.' );
		}
		$reservation = $value['reservation'];
		if ( ! self::exact_object( $reservation, array( 'state', 'pnr_ref', 'airline_order_ref', 'ticketing_deadline_utc' ) ) || ! in_array( $reservation['state'], array( 'not_applicable', 'held', 'confirmed', 'cancelled', 'unknown' ), true ) || ! self::nullable_ref( $reservation['pnr_ref'], 'pnr' ) || ! self::nullable_ref( $reservation['airline_order_ref'], 'airline_order' ) || ! self::nullable_utc( $reservation['ticketing_deadline_utc'] ) ) {
			return self::error( 'flight_reservation_invalid', 'Flight reservation and ticketing deadline truth must be explicit.' );
		}
		$ticketing = $value['ticketing'];
		if ( ! self::exact_object( $ticketing, array( 'issuance_state', 'validating_carrier_ref', 'ticket_stock_ref', 'consolidator_owner_ref', 'servicing_owner_ref', 'servicing_channel' ) ) || ! in_array( $ticketing['issuance_state'], array( 'not_applicable', 'not_issued', 'queued', 'partially_issued', 'issued', 'voided', 'unknown' ), true ) || ! self::nullable_party_ref( $ticketing['validating_carrier_ref'] ) || ! self::nullable_ref( $ticketing['ticket_stock_ref'], 'ticket_stock' ) || ! self::nullable_party_ref( $ticketing['consolidator_owner_ref'] ) || ! self::nullable_party_ref( $ticketing['servicing_owner_ref'] ) || ! in_array( $ticketing['servicing_channel'], array( 'none', 'airline', 'consolidator', 'agency', 'api' ), true ) ) {
			return self::error( 'flight_ticketing_invalid', 'Ticket issuance, stock and servicing ownership must be explicit and separate from reservation truth.' );
		}
		if ( ! is_array( $value['tickets'] ) || array_values( $value['tickets'] ) !== $value['tickets'] || ! is_array( $value['emds'] ) || array_values( $value['emds'] ) !== $value['emds'] ) {
			return self::error( 'flight_documents_invalid', 'Tickets and EMDs must be explicit document lists.' );
		}
		if ( ! $value['applicable'] ) {
			if ( 'not_applicable' !== $reservation['state'] || null !== $reservation['pnr_ref'] || null !== $reservation['airline_order_ref'] || null !== $reservation['ticketing_deadline_utc'] || 'not_applicable' !== $ticketing['issuance_state'] || null !== $ticketing['validating_carrier_ref'] || null !== $ticketing['ticket_stock_ref'] || null !== $ticketing['consolidator_owner_ref'] || null !== $ticketing['servicing_owner_ref'] || 'none' !== $ticketing['servicing_channel'] || $value['tickets'] || $value['emds'] ) {
				return self::error( 'flight_not_applicable_contaminated', 'A non-flight case cannot carry flight reservation, issuance, coupon, EMD or owner claims.' );
			}
			return $value;
		}
		if ( 'not_applicable' === $reservation['state'] || ( null === $reservation['pnr_ref'] && null === $reservation['airline_order_ref'] ) || 'not_applicable' === $ticketing['issuance_state'] || null === $ticketing['servicing_owner_ref'] || 'none' === $ticketing['servicing_channel'] || ( 'consolidator' === $ticketing['servicing_channel'] && null === $ticketing['consolidator_owner_ref'] ) ) {
			return self::error( 'flight_ownership_incomplete', 'Applicable flight service must identify reservation truth and the party and channel that own servicing.' );
		}
		$tickets = array();
		$ticket_refs = array();
		$coupon_refs = array();
		foreach ( $value['tickets'] as $ticket ) {
			if ( ! self::exact_object( $ticket, array( 'ticket_ref', 'traveler_ref', 'document_state', 'coupons' ) ) || ! self::ref( $ticket['ticket_ref'], 'ticket' ) || isset( $ticket_refs[ $ticket['ticket_ref'] ] ) || ! in_array( $ticket['traveler_ref'], $scope['all_traveler_refs'], true ) || '' === Tra_Vel_Postbooking_Servicing_Taxonomy::member( $ticket['document_state'], Tra_Vel_Postbooking_Servicing_Taxonomy::TICKET_DOCUMENT_STATES ) || ! is_array( $ticket['coupons'] ) || array_values( $ticket['coupons'] ) !== $ticket['coupons'] || ! $ticket['coupons'] ) {
				return self::error( 'ticket_document_invalid', 'Every ticket document must bind one traveler and at least one coupon.' );
			}
			$ticket_refs[ $ticket['ticket_ref'] ] = true;
			$coupons = array();
			foreach ( $ticket['coupons'] as $coupon ) {
				if ( ! self::exact_object( $coupon, array( 'coupon_ref', 'segment_ref', 'state' ) ) || ! self::ref( $coupon['coupon_ref'], 'coupon' ) || isset( $coupon_refs[ $coupon['coupon_ref'] ] ) || ! in_array( $coupon['segment_ref'], $scope['all_segment_refs'], true ) || '' === Tra_Vel_Postbooking_Servicing_Taxonomy::member( $coupon['state'], Tra_Vel_Postbooking_Servicing_Taxonomy::COUPON_STATES ) ) {
					return self::error( 'ticket_coupon_invalid', 'Coupon state must be unique and bound to one exact segment.' );
				}
				$coupon_refs[ $coupon['coupon_ref'] ] = true;
				$coupons[] = $coupon;
			}
			$ticket['coupons'] = $coupons;
			$tickets[] = $ticket;
		}
		if ( in_array( $ticketing['issuance_state'], array( 'not_issued', 'queued' ), true ) && $tickets ) {
			return self::error( 'unissued_ticket_documents_present', 'A queued or unissued reservation cannot claim ticket documents.' );
		}
		if ( in_array( $ticketing['issuance_state'], array( 'partially_issued', 'issued', 'voided' ), true ) && ! $tickets ) {
			return self::error( 'issued_ticket_documents_missing', 'Issued or voided ticketing truth requires opaque ticket and coupon evidence.' );
		}
		if ( $tickets && ( null === $ticketing['validating_carrier_ref'] || null === $ticketing['ticket_stock_ref'] ) ) {
			return self::error( 'ticket_stock_owner_missing', 'Ticket documents require validating-carrier and ticket-stock ownership metadata.' );
		}
		$emds = array();
		$emd_refs = array();
		foreach ( $value['emds'] as $emd ) {
			if ( ! self::exact_object( $emd, array( 'emd_ref', 'traveler_ref', 'order_item_ref', 'service_ref', 'document_state' ) ) || ! self::ref( $emd['emd_ref'], 'emd' ) || isset( $emd_refs[ $emd['emd_ref'] ] ) || ! in_array( $emd['traveler_ref'], $scope['all_traveler_refs'], true ) || ! in_array( $emd['order_item_ref'], $scope['all_order_item_refs'], true ) || ! self::ref( $emd['service_ref'], 'service' ) || '' === Tra_Vel_Postbooking_Servicing_Taxonomy::member( $emd['document_state'], Tra_Vel_Postbooking_Servicing_Taxonomy::EMD_STATES ) ) {
				return self::error( 'emd_document_invalid', 'Every ancillary document must bind one traveler, item, service and independent EMD state.' );
			}
			$emd_refs[ $emd['emd_ref'] ] = true;
			$emds[] = $emd;
		}
		$value['tickets'] = $tickets;
		$value['emds'] = $emds;
		return $value;
	}

	private static function lodging_state( $value, $scope ) {
		if ( ! self::exact_object( $value, array( 'applicable', 'reservation_ref', 'reservation_state', 'rooms' ) ) || ! is_bool( $value['applicable'] ) || ! self::nullable_ref( $value['reservation_ref'], 'lodging_reservation' ) || ! in_array( $value['reservation_state'], array( 'not_applicable', 'pending', 'confirmed', 'cancelled', 'stayed', 'no_show', 'partial_no_show', 'unknown' ), true ) || ! is_array( $value['rooms'] ) || array_values( $value['rooms'] ) !== $value['rooms'] ) {
			return self::error( 'lodging_shape_invalid', 'Lodging state must preserve reservation and room facts independently.' );
		}
		if ( ! $value['applicable'] ) {
			if ( null !== $value['reservation_ref'] || 'not_applicable' !== $value['reservation_state'] || $value['rooms'] ) {
				return self::error( 'lodging_not_applicable_contaminated', 'A non-lodging case cannot carry lodging reservation or room truth.' );
			}
			return $value;
		}
		if ( null === $value['reservation_ref'] || 'not_applicable' === $value['reservation_state'] || ! $value['rooms'] ) {
			return self::error( 'lodging_reservation_incomplete', 'Applicable lodging service requires a reservation reference, state and rooms.' );
		}
		$room_refs = array();
		$guest_refs = array();
		$rooms = array();
		foreach ( $value['rooms'] as $room ) {
			$keys = array( 'room_ref', 'guest_refs', 'check_in_date', 'check_out_date', 'adult_count', 'child_count', 'room_state', 'no_show_state', 'inventory_restoration_state' );
			if ( ! self::exact_object( $room, $keys ) || ! in_array( $room['room_ref'], $scope['all_room_refs'], true ) || isset( $room_refs[ $room['room_ref'] ] ) || ! self::ref_list( $room['guest_refs'], 'guest', false ) || ! self::date_value( $room['check_in_date'] ) || ! self::date_value( $room['check_out_date'] ) || $room['check_out_date'] <= $room['check_in_date'] || ! is_int( $room['adult_count'] ) || ! is_int( $room['child_count'] ) || $room['adult_count'] < 0 || $room['child_count'] < 0 || $room['adult_count'] + $room['child_count'] !== count( $room['guest_refs'] ) || $room['adult_count'] < 1 || ! in_array( $room['room_state'], array( 'pending', 'confirmed', 'changed', 'cancelled', 'stayed', 'no_show', 'unknown' ), true ) || ! in_array( $room['no_show_state'], array( 'none', 'partial', 'complete', 'disputed', 'unknown' ), true ) || ! in_array( $room['inventory_restoration_state'], array( 'not_applicable', 'not_requested', 'pending', 'restored', 'not_restored', 'unknown' ), true ) ) {
				return self::error( 'lodging_room_invalid', 'Each room needs exact guest, date, occupancy, no-show and restoration states.' );
			}
			$room_refs[ $room['room_ref'] ] = true;
			foreach ( $room['guest_refs'] as $guest_ref ) {
				if ( ! in_array( $guest_ref, $scope['all_guest_refs'], true ) || isset( $guest_refs[ $guest_ref ] ) ) {
					return self::error( 'lodging_guest_allocation_invalid', 'Each scoped guest must belong to exactly one room allocation.' );
				}
				$guest_refs[ $guest_ref ] = true;
			}
			if ( $room['check_in_date'] < $scope['date_scope']['check_in_date'] || $room['check_out_date'] > $scope['date_scope']['check_out_date'] ) {
				return self::error( 'lodging_room_date_outside_scope', 'Room dates must remain within the exact affected stay window.' );
			}
			$rooms[] = $room;
		}
		if ( self::sorted_unique( array_keys( $room_refs ) ) !== $scope['all_room_refs'] || self::sorted_unique( array_keys( $guest_refs ) ) !== $scope['all_guest_refs'] ) {
			return self::error( 'lodging_scope_mismatch', 'The lodging room and guest records must cover the complete scoped reservation exactly once.' );
		}
		$value['rooms'] = $rooms;
		return $value;
	}

	private static function financial_differentials( $values, $scope ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values ) {
			return self::error( 'financial_differentials_invalid', 'Financial differentials must be a list of independent per-item outcomes.' );
		}
		$seen_refs = array();
		$seen_items = array();
		$output = array();
		foreach ( $values as $value ) {
			$keys = array(
				'differential_ref', 'order_item_ref', 'currency', 'minor_unit_exponent',
				'outcome_types', 'old_item_amount_minor', 'replacement_item_amount_minor',
				'penalty_minor', 'tax_difference_minor', 'add_collect_minor',
				'supplier_refund_minor', 'residual_value_minor', 'reusable_value_minor',
				'traveler_repayment_minor', 'settlement_adjustment_minor', 'states',
			);
			if ( ! self::exact_object( $value, $keys ) || ! self::ref( $value['differential_ref'], 'servicing_diff' ) || isset( $seen_refs[ $value['differential_ref'] ] ) || ! in_array( $value['order_item_ref'], $scope['affected_order_item_refs'], true ) || isset( $seen_items[ $value['order_item_ref'] ] ) || ! is_string( $value['currency'] ) || 1 !== preg_match( '/^[A-Z]{3}$/', $value['currency'] ) || ! is_int( $value['minor_unit_exponent'] ) || $value['minor_unit_exponent'] < 0 || $value['minor_unit_exponent'] > 4 ) {
				return self::error( 'financial_identity_invalid', 'Each differential must bind one unique affected item and exact currency exponent.' );
			}
			$outcomes = Tra_Vel_Postbooking_Servicing_Taxonomy::ordered_subset( $value['outcome_types'], Tra_Vel_Postbooking_Servicing_Taxonomy::FINANCIAL_OUTCOMES );
			if ( is_wp_error( $outcomes ) ) {
				return self::error( 'financial_outcomes_invalid', 'Financial outcomes must use the canonical non-netted vocabulary.' );
			}
			foreach ( array( 'old_item_amount_minor', 'replacement_item_amount_minor', 'penalty_minor', 'add_collect_minor', 'supplier_refund_minor', 'residual_value_minor', 'reusable_value_minor', 'traveler_repayment_minor' ) as $money_key ) {
				if ( ! self::money( $value[ $money_key ], false ) ) {
					return self::error( 'financial_amount_invalid', 'Servicing financial components must use bounded integer minor units.' );
				}
			}
			foreach ( array( 'tax_difference_minor', 'settlement_adjustment_minor' ) as $signed_key ) {
				if ( ! self::money( $value[ $signed_key ], true ) ) {
					return self::error( 'financial_signed_amount_invalid', 'Tax and settlement adjustments must use bounded signed integer minor units.' );
				}
			}
			$states = $value['states'];
			if ( ! self::exact_object( $states, array( 'add_collect_state', 'supplier_refund_state', 'residual_value_state', 'reusable_value_state', 'traveler_repayment_state', 'settlement_state' ) ) || ! in_array( $states['add_collect_state'], array( 'not_applicable', 'proposed', 'authorized', 'collected', 'failed' ), true ) || ! in_array( $states['supplier_refund_state'], array( 'not_applicable', 'requested', 'pending', 'received', 'failed' ), true ) || ! in_array( $states['residual_value_state'], array( 'not_applicable', 'proposed', 'issued', 'used', 'expired' ), true ) || ! in_array( $states['reusable_value_state'], array( 'not_applicable', 'proposed', 'issued', 'used', 'expired' ), true ) || ! in_array( $states['traveler_repayment_state'], array( 'not_applicable', 'not_started', 'pending', 'paid', 'failed' ), true ) || ! in_array( $states['settlement_state'], array( 'not_started', 'accrued', 'partially_settled', 'settled', 'disputed' ), true ) ) {
				return self::error( 'financial_states_invalid', 'Every financial component needs an independent lifecycle state.' );
			}
			$mapping = array(
				'add_collect'    => array( 'add_collect_minor', 'add_collect_state' ),
				'refund'         => array( 'supplier_refund_minor', 'supplier_refund_state' ),
				'residual_value' => array( 'residual_value_minor', 'residual_value_state' ),
				'reusable_value' => array( 'reusable_value_minor', 'reusable_value_state' ),
			);
			foreach ( $mapping as $outcome => $fields ) {
				$present = in_array( $outcome, $outcomes, true );
				if ( $present !== ( $value[ $fields[0] ] > 0 ) || $present === ( 'not_applicable' === $states[ $fields[1] ] ) ) {
					return self::error( 'financial_component_mismatch', 'Each non-netted outcome amount and lifecycle state must agree without implying another component.' );
				}
			}
			$repayment_present = $value['traveler_repayment_minor'] > 0;
			if ( $repayment_present === ( 'not_applicable' === $states['traveler_repayment_state'] ) ) {
				return self::error( 'traveler_repayment_state_mismatch', 'Traveler repayment must remain separate from supplier refund and have its own state.' );
			}
			if ( in_array( 'even_exchange', $outcomes, true ) ) {
				if ( array( 'even_exchange' ) !== $outcomes || $value['old_item_amount_minor'] !== $value['replacement_item_amount_minor'] || 0 !== $value['penalty_minor'] || 0 !== $value['tax_difference_minor'] || 0 !== $value['add_collect_minor'] || 0 !== $value['supplier_refund_minor'] || 0 !== $value['residual_value_minor'] || 0 !== $value['reusable_value_minor'] || 0 !== $value['traveler_repayment_minor'] || 0 !== $value['settlement_adjustment_minor'] ) {
					return self::error( 'even_exchange_invalid', 'Even exchange is its own zero-differential outcome and cannot hide another component.' );
				}
			}
			$seen_refs[ $value['differential_ref'] ] = true;
			$seen_items[ $value['order_item_ref'] ] = true;
			$value['outcome_types'] = $outcomes;
			$output[] = $value;
		}
		return $output;
	}

	private static function independent_states( $value ) {
		$allowed = array(
			'supplier_reservation_state' => array( 'pending', 'held', 'confirmed', 'changed', 'cancelled', 'unknown' ),
			'supplier_fulfillment_state' => array( 'not_started', 'queued', 'partial', 'fulfilled', 'failed', 'unknown' ),
			'customer_payment_state'      => array( 'not_started', 'authorized', 'partially_captured', 'captured', 'failed', 'disputed', 'charged_back', 'unknown' ),
			'supplier_refund_state'       => array( 'not_applicable', 'requested', 'pending', 'received', 'failed', 'unknown' ),
			'customer_refund_state'       => array( 'not_applicable', 'not_started', 'pending', 'partially_paid', 'paid', 'failed', 'unknown' ),
			'supplier_settlement_state'   => array( 'not_started', 'accrued', 'partially_settled', 'settled', 'disputed', 'unknown' ),
			'reconciliation_state'        => array( 'not_started', 'pending', 'matched', 'exception', 'unknown' ),
		);
		if ( ! self::exact_object( $value, array_keys( $allowed ) ) ) {
			return self::error( 'independent_states_shape_invalid', 'Supplier, fulfillment, payment, refund, settlement and reconciliation axes must all be present.' );
		}
		foreach ( $allowed as $key => $states ) {
			if ( ! in_array( $value[ $key ], $states, true ) ) {
				return self::error( 'independent_state_invalid', 'A servicing lifecycle axis uses an unsupported state.' );
			}
		}
		return $value;
	}

	private static function message_delivery( $value, $now ) {
		$keys = array( 'webhook_health', 'poll_cursor_ref', 'authoritative_retrieval_state', 'messages' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['webhook_health'], array( 'healthy', 'degraded', 'failed', 'not_configured', 'unknown' ), true ) || ! self::nullable_ref( $value['poll_cursor_ref'], 'message_cursor' ) || ! in_array( $value['authoritative_retrieval_state'], array( 'not_requested', 'current', 'stale', 'conflict', 'failed' ), true ) || ! is_array( $value['messages'] ) || array_values( $value['messages'] ) !== $value['messages'] ) {
			return self::error( 'message_delivery_shape_invalid', 'Message transport and authoritative retrieval must remain distinct.' );
		}
		$seen = array();
		foreach ( $value['messages'] as $message ) {
			$keys = array( 'message_ref', 'message_type', 'channel', 'delivery_state', 'acknowledged_at', 'authoritative_retrieval_state', 'source_digest', 'observed_at' );
			if ( ! self::exact_object( $message, $keys ) || ! self::ref( $message['message_ref'], 'supplier_message' ) || isset( $seen[ $message['message_ref'] ] ) || ! in_array( $message['message_type'], array( 'reservation', 'modification', 'cancellation', 'late_arrival', 'no_show', 'inventory_update' ), true ) || ! in_array( $message['channel'], array( 'webhook', 'poll', 'fallback_notice', 'manual_evidence' ), true ) || ! in_array( $message['delivery_state'], array( 'queued', 'sent', 'delivered', 'acknowledged', 'failed', 'unknown' ), true ) || ! self::nullable_utc( $message['acknowledged_at'] ) || ! in_array( $message['authoritative_retrieval_state'], array( 'not_requested', 'current', 'stale', 'conflict', 'failed' ), true ) || ! self::digest( $message['source_digest'] ) || false === self::utc_timestamp( $message['observed_at'] ) || self::utc_timestamp( $message['observed_at'] ) > $now ) {
				return self::error( 'supplier_message_invalid', 'Supplier messages need unique evidence, transport state and independent authoritative-retrieval state.' );
			}
			if ( ( 'acknowledged' === $message['delivery_state'] ) !== ( null !== $message['acknowledged_at'] ) ) {
				return self::error( 'message_acknowledgement_invalid', 'Only an acknowledged message may carry an acknowledgement time, and every acknowledged message must carry one.' );
			}
			$seen[ $message['message_ref'] ] = true;
		}
		return $value;
	}

	private static function action( $type, $scope, $approvals ) {
		$targets = array(
			'traveler_refs'  => $scope['affected_traveler_refs'],
			'segment_refs'   => $scope['affected_segment_refs'],
			'order_item_refs'=> $scope['affected_order_item_refs'],
			'room_refs'      => $scope['affected_room_refs'],
			'guest_refs'     => $scope['affected_guest_refs'],
		);
		return array(
			'action_type'         => $type,
			'targets'             => $targets,
			'required_approvals'  => self::approval_order( $approvals ),
			'execution_state'     => 'planned',
			'supplier_dispatched' => false,
			'processor_called'    => false,
			'ledger_mutated'      => false,
			'message_sent'        => false,
		);
	}

	private static function needs_evidence( $snapshot ) {
		foreach ( $snapshot['independent_states'] as $state ) {
			if ( 'unknown' === $state ) {
				return true;
			}
		}
		if ( $snapshot['flight_state']['applicable'] ) {
			if ( 'unknown' === $snapshot['flight_state']['reservation']['state'] || 'unknown' === $snapshot['flight_state']['ticketing']['issuance_state'] ) {
				return true;
			}
			foreach ( $snapshot['flight_state']['tickets'] as $ticket ) {
				if ( 'unknown' === $ticket['document_state'] ) {
					return true;
				}
				foreach ( $ticket['coupons'] as $coupon ) {
					if ( 'unknown' === $coupon['state'] ) {
						return true;
					}
				}
			}
			foreach ( $snapshot['flight_state']['emds'] as $emd ) {
				if ( 'unknown' === $emd['document_state'] ) {
					return true;
				}
			}
		}
		if ( $snapshot['lodging_state']['applicable'] ) {
			if ( 'unknown' === $snapshot['lodging_state']['reservation_state'] ) {
				return true;
			}
			foreach ( $snapshot['lodging_state']['rooms'] as $room ) {
				if ( in_array( 'unknown', array( $room['room_state'], $room['no_show_state'], $room['inventory_restoration_state'] ), true ) ) {
					return true;
				}
			}
		}
		return 'current' !== $snapshot['message_delivery']['authoritative_retrieval_state'];
	}

	private static function canonical_outcomes( $values ) {
		$result = array();
		foreach ( Tra_Vel_Postbooking_Servicing_Taxonomy::FINANCIAL_OUTCOMES as $outcome ) {
			if ( in_array( $outcome, $values, true ) ) {
				$result[] = $outcome;
			}
		}
		return $result;
	}

	private static function approval_order( $values ) {
		$order = array( 'customer_approval', 'financial_approval', 'human_operator_review', 'supplier_change_approval' );
		$result = array();
		foreach ( $order as $approval ) {
			if ( in_array( $approval, $values, true ) ) {
				$result[] = $approval;
			}
		}
		return $result;
	}

	private static function difference( $all, $affected ) {
		return self::sorted_unique( array_values( array_diff( $all, $affected ) ) );
	}

	private static function sorted_unique( $values ) {
		$values = array_values( array_unique( $values ) );
		sort( $values, SORT_STRING );
		return $values;
	}

	private static function subset( $subset, $all ) {
		return ! array_diff( $subset, $all );
	}

	private static function ref_list( $values, $prefix, $allow_empty ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( ! $allow_empty && ! $values ) || $values !== self::sorted_unique( $values ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! self::ref( $value, $prefix ) ) {
				return false;
			}
		}
		return true;
	}

	private static function money( $value, $signed ) {
		return is_int( $value ) && $value <= self::MAX_MONEY_MINOR && $value >= ( $signed ? -self::MAX_MONEY_MINOR : 0 );
	}

	private static function ref( $value, $prefix ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $prefix, '/' ) . '_[A-Za-z0-9_-]{8,96}$/', $value );
	}

	private static function nullable_ref( $value, $prefix ) {
		return null === $value || self::ref( $value, $prefix );
	}

	private static function party_ref( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^party_[A-Za-z0-9_-]{8,96}$/', $value );
	}

	private static function nullable_party_ref( $value ) {
		return null === $value || self::party_ref( $value );
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function nullable_digest( $value ) {
		return null === $value || self::digest( $value );
	}

	private static function date_value( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
			return false;
		}
		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
	}

	private static function nullable_date( $value ) {
		return null === $value || self::date_value( $value );
	}

	private static function nullable_utc( $value ) {
		return null === $value || false !== self::utc_timestamp( $value );
	}

	private static function utc_timestamp( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/', $value, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59 ) {
			return false;
		}
		try {
			return ( new DateTimeImmutable( $value ) )->getTimestamp();
		} catch ( Exception $error ) {
			return false;
		}
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function contains_sensitive_material( $value, $key = '' ) {
		$key = strtolower( (string) $key );
		$sensitive = 1 === preg_match( '/(?:password|secret|api[_-]?key|access[_-]?token|card[_-]?(?:number|pan)|cvv|cvc|passport(?:_number)?|email(?:_address)?|phone(?:_number)?|traveler_name|guest_name|message_body|raw_payload)/', $key );
		if ( $sensitive && null !== $value && false !== $value && '' !== $value ) {
			return true;
		}
		if ( ! is_array( $value ) ) {
			return false;
		}
		foreach ( $value as $child_key => $child ) {
			if ( self::contains_sensitive_material( $child, $child_key ) ) {
				return true;
			}
		}
		return false;
	}

	private static function canonical_digest( $value ) {
		$encoded = wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
		if ( ! $is_list ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::canonicalize( $child );
		}
		return $value;
	}

	private static function error( $suffix, $message, $status = 400 ) {
		return new WP_Error( 'tra_vel_postbooking_servicing_' . $suffix, $message, array( 'status' => $status ) );
	}
}
