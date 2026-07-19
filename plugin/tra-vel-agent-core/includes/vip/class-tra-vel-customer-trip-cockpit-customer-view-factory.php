<?php
/**
 * Deterministic redactor for the customer-facing Trip Cockpit view.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Customer_Trip_Cockpit_Customer_View_Factory {
	/**
	 * Project an already-validated private cockpit into a customer-safe view.
	 *
	 * The viewing-context object is a pre-verified assertion supplied by an auth
	 * wrapper. This pure factory only validates its exact binding and expiry; it
	 * does not authenticate, mint a token, grant a capability, or authorize work.
	 *
	 * @return array|WP_Error
	 */
	public static function create_view( $private_projection, $viewing_context, $now ) {
		$private_projection = Tra_Vel_Customer_Trip_Cockpit_Policy::validate_projection( $private_projection, $now );
		if ( is_wp_error( $private_projection ) ) {
			return new WP_Error(
				'tra_vel_customer_trip_cockpit_customer_view_private_source_invalid',
				'The customer view requires a complete, validated private Trip Cockpit read model.',
				array( 'status' => 409 )
			);
		}
		$viewing_context = Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_viewing_context( $viewing_context, $private_projection, $now );
		if ( is_wp_error( $viewing_context ) ) {
			return $viewing_context;
		}

		$is_scoped = 'scoped_session' === $viewing_context['mode'];
		$case_progress_allowed = in_array( 'case_progress_view', $viewing_context['scopes'], true );
		$alias_scope = array( $private_projection['trip_ref'], $private_projection['owner_scope_digest'] );
		$service_map = self::alias_map( $private_projection['service_timeline'], 'service_ref', 'service', $alias_scope );
		$traveler_map = $is_scoped ? array() : self::alias_map( $private_projection['traveler_readiness'], 'traveler_ref', 'traveler', $alias_scope );
		$purchase_map = $is_scoped ? array() : self::purchase_alias_map( $private_projection['money_status'], $alias_scope );
		$declared_refs = self::declared_affected_refs( $private_projection['changed'] );
		$declared_keys = self::map_refs( $declared_refs, $service_map );
		$affected_count = $private_projection['current']['affected_service_count'];
		$partition_detail = count( $declared_keys ) === $affected_count ? 'complete' : ( ! $declared_keys ? 'aggregate_only' : 'partial' );

		$services = array();
		foreach ( $private_projection['service_timeline'] as $service ) {
			$service_key = $service_map[ $service['service_ref'] ];
			$is_declared_affected = in_array( $service['service_ref'], $declared_refs, true );
			$impact_state = $is_declared_affected ? 'affected' : ( 'complete' === $partition_detail ? 'unaffected' : 'not_individually_declared' );
			$events = array();
			foreach ( $service['events'] as $event ) {
				if ( $is_scoped && ( self::scoped_code_is_withheld( $event['event_code'] ) || self::scoped_code_is_withheld( $event['state'] ) ) ) {
					continue;
				}
				$events[] = array(
					'event_code' => self::customer_code( $event['event_code'], 'service.event' ),
					'state'      => self::customer_code( $event['state'], 'status.updated' ),
					'truth_state'=> $event['truth_state'],
					'occurred_at'=> $event['occurred_at'],
				);
			}
			$services[] = array(
				'service_key'     => $service_key,
				'sequence'        => $service['sequence'],
				'vertical'        => $service['vertical'],
				'label'           => self::service_template( $service['vertical'], $service['phase'] ),
				'phase'           => $service['phase'],
				'health'          => $service['health'],
				'fulfillment'     => $service['fulfillment'],
				'change_state'    => $service['change_state'],
				'impact_state'    => $impact_state,
				'protected_codes' => self::customer_codes( $service['protected_codes'], 'protection.status', $is_scoped ),
				'next_safe_action'=> self::map_action( $service['next_action'], 'service', $service_map, $traveler_map, $viewing_context ),
				'events'          => $events,
				'verified_at'     => $service['verified_at'],
			);
		}

		$protections = array();
		foreach ( $private_projection['protected'] as $item ) {
			if ( $is_scoped && self::scoped_code_is_withheld( $item['protection_code'] ) ) {
				continue;
			}
			$protections[] = array(
				'protection_code' => self::customer_code( $item['protection_code'], 'protection.status' ),
				'service_keys'    => self::map_refs( $item['service_refs'], $service_map ),
				'state'           => $item['state'],
				'verified_at'     => $item['verified_at'],
			);
		}

		$changes = array();
		foreach ( $private_projection['changed'] as $item ) {
			if ( $is_scoped && self::scoped_code_is_withheld( $item['change_code'] ) ) {
				continue;
			}
			$changes[] = array(
				'change_code'          => self::customer_code( $item['change_code'], 'service.change' ),
				'affected_service_keys'=> self::map_refs( $item['affected_service_refs'], $service_map ),
				'truth_state'          => $item['truth_state'],
				'observed_at'          => $item['observed_at'],
			);
		}

		$attention = array();
		if ( ! $is_scoped ) {
			foreach ( $private_projection['approvals_required'] as $item ) {
				$attention[] = self::attention_item( $item, 'approval', 'approval_ref', 'scope_code', $service_map, $traveler_map, $alias_scope );
			}
			foreach ( $private_projection['unresolved_questions'] as $item ) {
				$attention[] = self::attention_item( $item, 'question', 'question_ref', 'question_code', $service_map, $traveler_map, $alias_scope );
			}
		}
		usort( $attention, function ( $left, $right ) {
			$left_deadline = null === $left['deadline'] ? '9999-12-31T23:59:59Z' : $left['deadline'];
			$right_deadline = null === $right['deadline'] ? '9999-12-31T23:59:59Z' : $right['deadline'];
			return $left_deadline === $right_deadline ? strcmp( $left['attention_key'], $right['attention_key'] ) : strcmp( $left_deadline, $right_deadline );
		} );

		$cases = array();
		if ( $case_progress_allowed ) {
			foreach ( $private_projection['trip_care_cases'] as $item ) {
				$cases[] = array(
					'case_key'        => self::public_alias( 'case', $item['case_ref'], $alias_scope ),
					'customer_state'  => $item['customer_state'],
					'service_keys'    => self::map_refs( $item['service_refs'], $service_map ),
					'next_safe_action'=> self::map_action( $item['next_action'], 'vip_case', $service_map, $traveler_map, $viewing_context ),
					'deadline'        => $item['deadline'],
					'verified_at'     => $item['verified_at'],
				);
			}
		}

		$receipts = array();
		if ( $case_progress_allowed ) {
			foreach ( $private_projection['trip_care_receipts'] as $item ) {
				$receipts[] = array(
					'receipt_key'     => self::public_alias( 'receipt', $item['receipt_ref'], $alias_scope ),
					'customer_state'  => $item['customer_state'],
					'service_keys'    => self::map_refs( $item['service_refs'], $service_map ),
					'next_safe_action'=> self::map_action( $item['next_action'], 'trip_care', $service_map, $traveler_map, $viewing_context ),
					'deadline'        => $item['deadline'],
					'verified_at'     => $item['verified_at'],
				);
			}
		}

		$travelers = array();
		if ( ! $is_scoped ) {
			foreach ( $private_projection['traveler_readiness'] as $item ) {
				$travelers[] = array(
					'traveler_slot'            => $traveler_map[ $item['traveler_ref'] ],
					'subject_kind'             => $item['subject_kind'],
					'readiness'                => $item['readiness'],
					'pending_requirement_codes'=> self::customer_codes( $item['pending_requirement_codes'], 'traveler.requirement' ),
					'next_safe_action'         => self::map_action( $item['next_action'], 'traveler', $service_map, $traveler_map, $viewing_context ),
					'deadline'                 => $item['deadline'],
					'verified_at'              => $item['verified_at'],
				);
			}
		}
		$next_safe_action = self::map_action( $private_projection['urgent_next_action'], null, $service_map, $traveler_map, $viewing_context );
		$customer_money = $is_scoped ? array(
			'disclosure' => 'withheld_scoped_session',
			'payments'   => array(),
			'refunds'    => array(),
		) : array(
			'disclosure' => 'signed_in_redacted',
			'payments'   => self::money_axis( $private_projection['money_status']['payments'], $purchase_map ),
			'refunds'    => self::money_axis( $private_projection['money_status']['refunds'], $purchase_map ),
		);
		$loyalty = $is_scoped ? array(
			'disclosure'           => 'withheld_scoped_session',
			'status'               => 'withheld',
			'filter_readiness'     => 'withheld',
			'affected_service_keys'=> array(),
			'next_safe_action'     => null,
			'verified_at'          => null,
		) : array(
			'disclosure'           => 'signed_in_redacted',
			'status'               => $private_projection['loyalty']['status'],
			'filter_readiness'     => self::loyalty_filter_readiness( $private_projection['loyalty']['status'] ),
			'affected_service_keys'=> self::map_refs( $private_projection['loyalty']['affected_service_refs'], $service_map ),
			'next_safe_action'     => self::map_action( $private_projection['loyalty']['next_action'], 'loyalty', $service_map, $traveler_map, $viewing_context ),
			'verified_at'          => $private_projection['loyalty']['verified_at'],
		);
		$offline_pack = array(
			'status'             => $private_projection['offline_pack']['status'],
			'itinerary'          => $private_projection['offline_pack']['itinerary'],
			'service_contacts'    => $private_projection['offline_pack']['service_contacts'],
			'emergency_contacts'  => $private_projection['offline_pack']['emergency_contacts'],
			'next_safe_action'    => self::map_action( $private_projection['offline_pack']['next_action'], 'offline_pack', $service_map, $traveler_map, $viewing_context ),
			'verified_at'         => $private_projection['offline_pack']['verified_at'],
		);
		$visible_freshness_basis = array( $next_safe_action, $protections, $services, $changes, $attention, $cases, $receipts, $offline_pack );
		if ( ! $is_scoped ) {
			$visible_freshness_basis[] = array( 'verified_at' => $private_projection['current']['verified_at'] );
			$visible_freshness_basis[] = $customer_money;
			$visible_freshness_basis[] = $travelers;
			$visible_freshness_basis[] = $loyalty;
		}
		$visible_source_verified_at = self::freshness_source_verified_at( $visible_freshness_basis );

		$record = array(
			'contract_version' => Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::CONTRACT_VERSION,
			'environment'      => 'sandbox',
			'audience'         => array(
				'mode'                         => $viewing_context['mode'],
				'view_allowed'                 => true,
				'report_issue_allowed'         => in_array( 'incident_report', $viewing_context['scopes'], true ),
				'follow_up_allowed'            => in_array( 'case_progress_view', $viewing_context['scopes'], true ),
				'high_impact_step_up_required' => true,
				'mutation_authorized'           => false,
			),
			'trip_headline'    => self::trip_template( $private_projection['current']['phase'] ),
			'current'          => array(
				'phase'                         => $private_projection['current']['phase'],
				'health'                        => $private_projection['current']['health'],
				'registration_readiness'        => $is_scoped ? 'withheld' : $private_projection['current']['registration_readiness'],
				'affected_service_count'        => $affected_count,
				'unaffected_service_count'      => $private_projection['current']['unaffected_service_count'],
				'declared_affected_service_keys'=> $declared_keys,
				'partition_detail'              => $partition_detail,
				'action_required'               => null !== $next_safe_action,
				'verified_at'                   => $is_scoped ? $visible_source_verified_at : $private_projection['current']['verified_at'],
			),
			'next_safe_action' => $next_safe_action,
			'protections'      => $protections,
			'changes'          => $changes,
			'attention_items'  => $attention,
			'service_timeline' => $services,
			'customer_money'   => $customer_money,
			'case_progress_disclosure' => $case_progress_allowed ? 'case_progress_redacted' : 'withheld_scope_missing',
			'trip_care_cases'    => $cases,
			'trip_care_receipts' => $receipts,
			'traveler_readiness_disclosure' => $is_scoped ? 'withheld_scoped_session' : 'signed_in_redacted',
			'traveler_readiness' => $travelers,
			'loyalty'             => $loyalty,
			'offline_pack'          => $offline_pack,
			'freshness'            => array(
				'status'              => self::freshness_status( $visible_freshness_basis ),
				'source_verified_at'  => $visible_source_verified_at,
				'projected_at'        => gmdate( 'Y-m-d\TH:i:s\Z', $now ),
				'basis'               => $is_scoped ? 'scoped_visible_only' : 'signed_in_redacted',
				'resolution_inferred' => false,
			),
			'authority'            => array(
				'authorization_effect'     => 'none',
				'view_projection_only'     => true,
				'change_started'            => false,
				'cancellation_started'      => false,
				'payment_started'           => false,
				'refund_started'            => false,
				'supplier_action_started'   => false,
				'processor_action_started'  => false,
				'resolution_inferred'       => false,
			),
			'data_boundary'        => array(
				'customer_serialization_allowed'   => true,
				'validated_private_read_model_only' => true,
				'owner_scope_exposed'               => false,
				'internal_refs_exposed'             => false,
				'raw_identity_data_exposed'         => false,
				'raw_payment_data_exposed'          => false,
				'raw_medical_data_exposed'          => false,
				'raw_provider_data_exposed'         => false,
				'bearer_secret_exposed'             => false,
				'internal_operator_routing_exposed' => false,
				'settlement_data_exposed'           => false,
				'commission_data_exposed'           => false,
			),
		);
		return Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::validate_view( $record, $now );
	}

	private static function trip_template( $phase ) {
		$templates = array(
			'planning' => 'Trip plan',
			'pre_trip' => 'Upcoming trip',
			'outbound' => 'Outbound journey',
			'in_trip' => 'Trip in progress',
			'return_trip' => 'Return journey',
			'post_trip' => 'Completed trip',
		);
		return $templates[ $phase ];
	}

	private static function service_template( $vertical, $phase ) {
		$verticals = array(
			'flight' => 'Flight', 'accommodation' => 'Stay', 'package' => 'Package', 'transfer' => 'Transfer',
			'activity' => 'Activity', 'dining' => 'Dining', 'insurance' => 'Insurance',
			'connectivity' => 'Connectivity', 'equipment' => 'Equipment',
		);
		$phases = array(
			'planned' => 'planned', 'held' => 'on hold', 'confirmed' => 'confirmed', 'travel_ready' => 'ready for travel',
			'in_progress' => 'in progress', 'completed' => 'completed', 'cancelled' => 'cancelled', 'recovery' => 'support in progress',
		);
		return $verticals[ $vertical ] . ' - ' . $phases[ $phase ];
	}

	private static function alias_map( $items, $ref_key, $kind, $alias_scope ) {
		$map = array();
		foreach ( $items as $item ) {
			$map[ $item[ $ref_key ] ] = self::public_alias( $kind, $item[ $ref_key ], $alias_scope );
		}
		return $map;
	}

	private static function purchase_alias_map( $money_status, $alias_scope ) {
		$refs = array();
		foreach ( array( 'payments', 'refunds' ) as $axis ) {
			foreach ( $money_status[ $axis ] as $item ) {
				$refs[ $item['order_ref'] ] = true;
			}
		}
		$map = array();
		foreach ( array_keys( $refs ) as $ref ) {
			$map[ $ref ] = self::public_alias( 'purchase', $ref, $alias_scope );
		}
		return $map;
	}

	private static function public_alias( $kind, $private_ref, $alias_scope ) {
		$message = 'tra-vel-customer-view|' . $kind . '|' . $alias_scope[0] . '|' . $private_ref;
		return $kind . '-' . substr( hash_hmac( 'sha256', $message, $alias_scope[1] ), 0, 32 );
	}

	private static function map_refs( $refs, $map ) {
		$values = array();
		foreach ( $refs as $ref ) {
			if ( isset( $map[ $ref ] ) ) {
				$values[] = $map[ $ref ];
			}
		}
		sort( $values, SORT_STRING );
		return array_values( array_unique( $values ) );
	}

	private static function declared_affected_refs( $changes ) {
		$refs = array();
		foreach ( $changes as $change ) {
			foreach ( $change['affected_service_refs'] as $ref ) {
				$refs[ $ref ] = true;
			}
		}
		$refs = array_keys( $refs );
		sort( $refs, SORT_STRING );
		return $refs;
	}

	private static function customer_code( $code, $fallback ) {
		return Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::is_customer_code( $code ) ? $code : $fallback;
	}

	private static function customer_codes( $codes, $fallback, $scoped = false ) {
		$mapped = array();
		foreach ( $codes as $code ) {
			if ( $scoped && self::scoped_code_is_withheld( $code ) ) {
				continue;
			}
			$mapped[] = self::customer_code( $code, $fallback );
		}
		$mapped = array_values( array_unique( $mapped ) );
		sort( $mapped, SORT_STRING );
		return $mapped;
	}

	private static function map_action( $action, $source_override, $service_map, $traveler_map, $viewing_context ) {
		if ( null === $action ) {
			return null;
		}
		$source = null === $source_override ? $action['source'] : $source_override;
		$effect = self::requested_effect( $action['code'], $source );
		$scopes = $viewing_context['scopes'];
		if ( 'report.issue' === $action['code'] && ! in_array( 'incident_report', $scopes, true ) ) {
			return null;
		}
		if ( 'report.issue' !== $action['code'] && ( in_array( $source, array( 'vip_case', 'trip_care' ), true ) || in_array( $action['code'], array( 'case.follow_up', 'provide.trip.update', 'provide.trip.details' ), true ) ) && ! in_array( 'case_progress_view', $scopes, true ) ) {
			return null;
		}
		if ( 'scoped_session' === $viewing_context['mode'] && ( self::scoped_code_is_withheld( $action['code'] ) || in_array( $source, array( 'commerce', 'loyalty', 'traveler', 'registration' ), true ) || in_array( $effect, array( 'pay', 'refund', 'account_update', 'commerce_review', 'redeem' ), true ) ) ) {
			return null;
		}
		$mode = 'review' === $effect ? 'view' : ( 'follow_up' === $effect ? 'follow_up' : 'step_up_required' );
		return array(
			'code'             => self::customer_code( $action['code'], self::fallback_action_code( $source ) ),
			'source'           => self::customer_code( $source, 'trip.status' ),
			'priority'         => $action['priority'],
			'service_keys'     => self::map_refs( $action['service_refs'], $service_map ),
			'traveler_slots'   => self::map_refs( $action['traveler_refs'], $traveler_map ),
			'deadline'         => $action['deadline'],
			'truth_state'      => $action['truth_state'],
			'requested_effect' => $effect,
			'interaction_mode' => $mode,
			'execution_effect' => 'none',
		);
	}

	/** Withhold codes that reveal commerce, loyalty, or traveler-readiness facts in forwarded views. */
	private static function scoped_code_is_withheld( $code ) {
		if ( ! is_string( $code ) ) {
			return true;
		}
		if ( in_array(
			$code,
			array(
				'payment.authorize', 'refund.request', 'payment.review', 'commerce',
				'redeem.points', 'loyalty.review', 'loyalty',
				'traveler.requirement', 'traveler_readiness.review', 'traveler', 'registration.review', 'registration',
			),
			true
		) ) {
			return true;
		}
		return 1 === preg_match( '/(?:^|[._-])(?:pay|payment|purchase|billing|card|refund|charge|commerce|settlement|commission|loyalty|points?|redeem|flycard|traveler|minor|dependent|guardian|accessibility|medical|passport|document|registration)(?:$|[._-])/', $code );
	}

	private static function requested_effect( $code, $source ) {
		if ( ! Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy::is_customer_code( $code ) ) {
			return 'unknown_high_impact';
		}
		if ( in_array( $code, array( 'report.issue', 'case.follow_up', 'question.follow_up', 'provide.trip.update', 'provide.trip.details' ), true ) ) {
			return 'follow_up';
		}
		if ( in_array( $code, array( 'view.trip_status', 'view.offline_itinerary', 'refresh.offline_pack' ), true ) ) {
			return 'review';
		}
		if ( preg_match( '/(?:^|[._-])cancel(?:$|[._-])/', $code ) ) {
			return 'cancel';
		}
		if ( preg_match( '/(?:^|[._-])refund(?:$|[._-])/', $code ) ) {
			return 'refund';
		}
		if ( preg_match( '/(?:^|[._-])(?:pay|payment|purchase|capture|authorize|collect)(?:$|[._-])/', $code ) ) {
			return 'pay';
		}
		if ( preg_match( '/(?:^|[._-])(?:change|modify|amend|rebook|replace|reschedule)(?:$|[._-])/', $code ) ) {
			return 'change';
		}
		if ( preg_match( '/(?:^|[._-])issue(?:$|[._-])/', $code ) ) {
			return 'issue';
		}
		if ( preg_match( '/(?:^|[._-])redeem(?:$|[._-])/', $code ) ) {
			return 'redeem';
		}
		if ( preg_match( '/(?:^|[._-])upgrade(?:$|[._-])/', $code ) ) {
			return 'upgrade';
		}
		if ( preg_match( '/(?:^|[._-])swap(?:$|[._-])/', $code ) ) {
			return 'swap';
		}
		if ( 'approval' === $source || preg_match( '/(?:^|[._-])(?:approve|accept|reserve|book|submit)(?:$|[._-])/', $code ) ) {
			return 'approve';
		}
		if ( in_array( $source, array( 'registration', 'traveler', 'loyalty' ), true ) ) {
			return 'account_update';
		}
		if ( 'commerce' === $source ) {
			return 'commerce_review';
		}
		return 'unknown_high_impact';
	}

	private static function fallback_action_code( $source ) {
		$codes = array(
			'registration' => 'registration.review', 'trip_health' => 'trip.review', 'service' => 'service.review',
			'approval' => 'approval.review', 'question' => 'question.follow_up', 'vip_case' => 'trip_care.follow_up',
			'trip_care' => 'trip_care.follow_up', 'commerce' => 'payment.review', 'loyalty' => 'loyalty.review',
			'traveler' => 'traveler_readiness.review', 'offline_pack' => 'offline_pack.review',
		);
		return isset( $codes[ $source ] ) ? $codes[ $source ] : 'trip.review';
	}

	private static function attention_item( $item, $kind, $ref_key, $code_key, $service_map, $traveler_map, $alias_scope ) {
		return array(
			'attention_key'   => self::public_alias( 'attention', $item[ $ref_key ], $alias_scope ),
			'kind'            => $kind,
			'code'            => self::customer_code( $item[ $code_key ], $kind . '.review' ),
			'status'          => $item['status'],
			'priority'        => $item['priority'],
			'service_keys'    => self::map_refs( $item['service_refs'], $service_map ),
			'traveler_slots'  => self::map_refs( $item['traveler_refs'], $traveler_map ),
			'deadline'        => $item['deadline'],
			'truth_state'     => $item['truth_state'],
			'verified_at'     => $item['verified_at'],
			'interaction_mode'=> 'step_up_required',
		);
	}

	private static function money_axis( $items, $purchase_map ) {
		$values = array();
		foreach ( $items as $item ) {
			$values[] = array(
				'purchase_key' => $purchase_map[ $item['order_ref'] ],
				'state'        => $item['state'],
				'truth_state'  => $item['truth_state'],
				'verified_at'  => $item['verified_at'],
			);
		}
		return $values;
	}

	private static function loyalty_filter_readiness( $status ) {
		$map = array(
			'not_requested' => 'not_requested', 'planning' => 'checking', 'member_connection_needed' => 'connection_required',
			'benefits_checking' => 'checking', 'options_ready' => 'ready', 'stale' => 'refresh_required', 'unavailable' => 'unavailable',
		);
		return $map[ $status ];
	}

	private static function freshness_status( $customer_visible_axes ) {
		$weights = array( 'verified_current' => 0, 'observed_unverified' => 1, 'stale' => 2, 'uncertain' => 3, 'conflict' => 4 );
		$worst = 'verified_current';
		self::walk_truth_states( $customer_visible_axes, $weights, $worst );
		return $worst;
	}

	private static function freshness_source_verified_at( $customer_visible_axes ) {
		$latest = '';
		$latest_epoch = 0;
		self::walk_visible_observation_clocks( $customer_visible_axes, $latest, $latest_epoch );
		return $latest;
	}

	private static function walk_visible_observation_clocks( $value, &$latest, &$latest_epoch, $key = '' ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $child_key => $child ) {
				self::walk_visible_observation_clocks( $child, $latest, $latest_epoch, $child_key );
			}
			return;
		}
		if ( ! in_array( $key, array( 'verified_at', 'observed_at', 'occurred_at' ), true ) || ! is_string( $value ) ) {
			return;
		}
		$epoch = strtotime( $value );
		if ( false !== $epoch && $epoch >= $latest_epoch ) {
			$latest = $value;
			$latest_epoch = $epoch;
		}
	}

	private static function walk_truth_states( $value, $weights, &$worst, $key = '' ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $child_key => $child ) {
				self::walk_truth_states( $child, $weights, $worst, $child_key );
			}
			return;
		}
		if ( 'truth_state' === $key && is_string( $value ) && isset( $weights[ $value ] ) && $weights[ $value ] > $weights[ $worst ] ) {
			$worst = $value;
		}
	}
}
