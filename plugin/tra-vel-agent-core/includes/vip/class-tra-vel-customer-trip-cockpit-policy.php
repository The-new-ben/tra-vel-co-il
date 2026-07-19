<?php
/**
 * Closed, private policy for the deterministic customer Trip Cockpit read model.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Customer_Trip_Cockpit_Policy {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_SERVICES = 64;
	const MAX_TRAVELERS = 32;
	const MAX_EVENTS = 512;

	const PHASES = array( 'planning', 'pre_trip', 'outbound', 'in_trip', 'return_trip', 'post_trip' );
	const HEALTH_STATES = array( 'on_track', 'watching', 'action_required', 'disrupted', 'recovery_in_progress', 'uncertain', 'completed_with_issue' );
	const SERVICE_PHASES = array( 'planned', 'held', 'confirmed', 'travel_ready', 'in_progress', 'completed', 'cancelled', 'recovery' );
	const SERVICE_CHANGE_STATES = array( 'unchanged', 'change_observed', 'change_pending', 'changed', 'cancellation_pending', 'cancelled', 'uncertain' );
	const FULFILLMENT_STATES = array( 'selected', 'hold_pending', 'held', 'confirmation_pending', 'confirmed', 'fulfillment_pending', 'fulfilled', 'change_pending', 'changed', 'cancellation_pending', 'cancelled', 'failed', 'reconciliation_required' );
	const TRUTH_STATES = array( 'verified_current', 'observed_unverified', 'stale', 'uncertain', 'conflict' );
	const PRIORITIES = array( 'urgent', 'high', 'normal', 'low' );
	const REGISTRATION_READINESS = array( 'ready', 'attention_required', 'blocked' );
	const PROTECTION_STATES = array( 'active', 'at_risk', 'unverified', 'expired', 'not_applicable' );
	const APPROVAL_STATES = array( 'pending', 'approved', 'rejected', 'expired' );
	const QUESTION_STATES = array( 'pending', 'answered', 'reopened' );
	const VIP_CASE_STATES = array( 'case_received', 'immediate_safety_help', 'action_required', 'recovery_underway', 'attention_needed', 'recovered', 'resolved_with_loss' );
	const TRIP_CARE_STATES = array( 'received', 'checking', 'need_information', 'human_review' );
	const FUNDS_STATES = array( 'not_started', 'authorization_pending', 'partially_collected', 'collected', 'partially_returned', 'returned', 'at_risk', 'failed', 'uncertain' );
	const PAYMENT_STATES = array( 'not_started', 'pending', 'requires_action', 'authorized', 'captured', 'failed', 'voided', 'partially_refunded', 'refunded', 'uncertain', 'disputed', 'charged_back' );
	const REFUND_STATES = array( 'not_requested', 'requested', 'pending', 'partially_refunded', 'refunded', 'failed', 'uncertain', 'disputed' );
	const SETTLEMENT_STATES = array( 'not_applicable', 'pending', 'partially_settled', 'settled', 'reversed', 'disputed', 'uncertain' );
	const LOYALTY_STATES = array( 'not_requested', 'planning', 'member_connection_needed', 'benefits_checking', 'options_ready', 'stale', 'unavailable' );
	const TRAVELER_READINESS = array( 'ready', 'attention_required', 'blocked', 'unknown' );
	const SUBJECT_KINDS = array( 'adult', 'minor', 'dependent_adult' );
	const OFFLINE_PACK_STATES = array( 'not_requested', 'building', 'ready', 'stale', 'unavailable' );
	const OFFLINE_COMPONENT_STATES = array( 'ready', 'stale', 'missing', 'not_applicable' );
	const VERTICALS = array( 'flight', 'accommodation', 'package', 'transfer', 'activity', 'dining', 'insurance', 'connectivity', 'equipment' );
	const ACTION_SOURCES = array( 'registration', 'trip_health', 'service', 'approval', 'question', 'vip_case', 'trip_care', 'commerce', 'loyalty', 'traveler', 'offline_pack' );

	/**
	 * Validate one already-normalized source bundle. The bundle contains only
	 * opaque references, customer-safe labels, status axes, and timestamps.
	 *
	 * @return array|WP_Error
	 */
	public static function validate_source( $source, $now ) {
		$keys = array(
			'contract_version', 'environment', 'cockpit_ref', 'trip_ref', 'owner_scope_digest',
			'revision', 'previous_projection_digest', 'headline', 'registration', 'trip_health',
			'services', 'protections', 'changes', 'approvals', 'questions', 'vip_cases',
			'trip_care_receipts', 'commerce_orders', 'loyalty', 'traveler_readiness',
			'offline_pack', 'observed_at', 'data_boundary',
		);
		if ( ! self::exact_object( $source, $keys ) || self::contains_sensitive_material( $source ) ) {
			return self::error( 'source_shape_invalid', 'The Trip Cockpit source must be a closed, minimized, privacy-safe projection bundle.' );
		}
		if (
			self::CONTRACT_VERSION !== $source['contract_version'] ||
			'sandbox' !== $source['environment'] ||
			! self::ref( $source['cockpit_ref'], 'cockpit' ) ||
			! self::ref( $source['trip_ref'], 'trip' ) ||
			! self::digest( $source['owner_scope_digest'] ) ||
			! self::positive_int( $source['revision'] ) ||
			! self::nullable_digest( $source['previous_projection_digest'] ) ||
			! self::safe_label( $source['headline'], 160 ) ||
			! self::utc_at_or_before( $source['observed_at'], $now ) ||
			! self::source_boundary( $source['data_boundary'] )
		) {
			return self::error( 'source_identity_invalid', 'The Trip Cockpit source identity, clock, ancestry, headline, or private boundary is invalid.' );
		}
		if ( ( 1 === $source['revision'] && null !== $source['previous_projection_digest'] ) || ( $source['revision'] > 1 && ! self::digest( $source['previous_projection_digest'] ) ) ) {
			return self::error( 'source_ancestry_invalid', 'A cockpit successor must bind the exact previous projection digest.' );
		}

		$times = array();
		$services = self::validate_services( $source['services'], $now, $times );
		if ( is_wp_error( $services ) ) {
			return $services;
		}
		$service_refs = array_keys( $services );

		$travelers = self::validate_travelers( $source['traveler_readiness'], $service_refs, $now, $times );
		if ( is_wp_error( $travelers ) ) {
			return $travelers;
		}
		$traveler_refs = array_keys( $travelers );
		$service_actions = self::validate_service_actions( $source['services'], $service_refs, $traveler_refs, $now );
		if ( is_wp_error( $service_actions ) ) {
			return $service_actions;
		}

		$registration = self::validate_registration( $source['registration'], $service_refs, $traveler_refs, $now, $times );
		if ( is_wp_error( $registration ) ) {
			return $registration;
		}
		$health = self::validate_trip_health( $source['trip_health'], $service_refs, $traveler_refs, $now, $times );
		if ( is_wp_error( $health ) ) {
			return $health;
		}

		$list_checks = array(
			self::validate_protections( $source['protections'], $service_refs, $now, $times ),
			self::validate_changes( $source['changes'], $service_refs, $source['trip_health']['affected_service_refs'], $now, $times ),
			self::validate_approvals( $source['approvals'], $service_refs, $traveler_refs, $now, $times ),
			self::validate_questions( $source['questions'], $service_refs, $traveler_refs, $now, $times ),
			self::validate_vip_cases( $source['vip_cases'], $service_refs, $traveler_refs, $now, $times ),
			self::validate_trip_care_receipts( $source['trip_care_receipts'], $service_refs, $traveler_refs, $now, $times ),
			self::validate_commerce_orders( $source['commerce_orders'], $service_refs, $traveler_refs, $now, $times ),
		);
		foreach ( $list_checks as $check ) {
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}

		$loyalty = self::validate_loyalty( $source['loyalty'], $service_refs, $traveler_refs, $now, $times );
		if ( is_wp_error( $loyalty ) ) {
			return $loyalty;
		}
		$offline = self::validate_offline_pack( $source['offline_pack'], $service_refs, $traveler_refs, $now, $times );
		if ( is_wp_error( $offline ) ) {
			return $offline;
		}

		$latest = self::latest_time( $times );
		if ( $source['observed_at'] !== $latest ) {
			return self::error( 'source_clock_invalid', 'The source observation clock must equal the newest validated component observation, never a fabricated fresher time.' );
		}
		return $source;
	}

	/**
	 * Validate a complete, sealed private cockpit read model.
	 *
	 * @return array|WP_Error
	 */
	public static function validate_projection( $record, $now ) {
		$keys = array(
			'contract_version', 'environment', 'cockpit_ref', 'trip_ref', 'owner_scope_digest',
			'revision', 'previous_projection_digest', 'projection_digest', 'trip_headline',
			'current', 'urgent_next_action', 'protected', 'changed', 'approvals_required',
			'unresolved_questions', 'service_timeline', 'money_status', 'trip_care_cases', 'trip_care_receipts',
			'traveler_readiness', 'loyalty', 'offline_pack', 'last_verified_at', 'authority',
			'data_boundary',
		);
		if ( ! self::exact_object( $record, $keys ) || self::contains_sensitive_material( $record ) ) {
			return self::error( 'projection_shape_invalid', 'The Trip Cockpit projection is not the closed private read-model contract.' );
		}
		if (
			self::CONTRACT_VERSION !== $record['contract_version'] ||
			'sandbox' !== $record['environment'] ||
			! self::ref( $record['cockpit_ref'], 'cockpit' ) ||
			! self::ref( $record['trip_ref'], 'trip' ) ||
			! self::digest( $record['owner_scope_digest'] ) ||
			! self::positive_int( $record['revision'] ) ||
			! self::nullable_digest( $record['previous_projection_digest'] ) ||
			! self::digest( $record['projection_digest'] ) ||
			! self::safe_label( $record['trip_headline'], 160 ) ||
			! self::utc_at_or_before( $record['last_verified_at'], $now ) ||
			! self::projection_boundary( $record['data_boundary'] ) ||
			! self::authority_boundary( $record['authority'] )
		) {
			return self::error( 'projection_identity_invalid', 'The projection identity, clock, ancestry, headline, authority, or private boundary is invalid.' );
		}
		if ( ( 1 === $record['revision'] && null !== $record['previous_projection_digest'] ) || ( $record['revision'] > 1 && ! self::digest( $record['previous_projection_digest'] ) ) ) {
			return self::error( 'projection_ancestry_invalid', 'A cockpit successor must bind its exact predecessor projection digest.' );
		}

		$times = array();
		$services = self::validate_services( $record['service_timeline'], $now, $times );
		if ( is_wp_error( $services ) ) {
			return $services;
		}
		$service_refs = array_keys( $services );
		$travelers = self::validate_travelers( $record['traveler_readiness'], $service_refs, $now, $times );
		if ( is_wp_error( $travelers ) ) {
			return $travelers;
		}
		$traveler_refs = array_keys( $travelers );
		$service_actions = self::validate_service_actions( $record['service_timeline'], $service_refs, $traveler_refs, $now );
		if ( is_wp_error( $service_actions ) ) {
			return $service_actions;
		}

		$current = $record['current'];
		if ( ! self::exact_object( $current, array( 'phase', 'health', 'registration_gate', 'registration_readiness', 'affected_service_count', 'unaffected_service_count', 'action_required', 'verified_at' ) ) || ! in_array( $current['phase'], self::PHASES, true ) || ! in_array( $current['health'], self::HEALTH_STATES, true ) || ! in_array( $current['registration_gate'], Tra_Vel_VIP_Taxonomy::REGISTRATION_GATES, true ) || ! in_array( $current['registration_readiness'], self::REGISTRATION_READINESS, true ) || ! self::bounded_count( $current['affected_service_count'], self::MAX_SERVICES ) || ! self::bounded_count( $current['unaffected_service_count'], self::MAX_SERVICES ) || count( $service_refs ) !== $current['affected_service_count'] + $current['unaffected_service_count'] || ! is_bool( $current['action_required'] ) || ! self::utc_at_or_before( $current['verified_at'], $now ) ) {
			return self::error( 'projection_current_invalid', 'The current cockpit phase and independent health/readiness axes are invalid.' );
		}
		$times[] = $current['verified_at'];
		$urgent = self::validate_projected_action( $record['urgent_next_action'], $service_refs, $traveler_refs, $now );
		if ( is_wp_error( $urgent ) || $current['action_required'] !== ( null !== $record['urgent_next_action'] ) ) {
			return self::error( 'projection_action_invalid', 'The urgent action must be explicit, source-labelled, and consistent with the current action-required flag.' );
		}

		$checks = array(
			self::validate_protections( $record['protected'], $service_refs, $now, $times ),
			self::validate_changes( $record['changed'], $service_refs, null, $now, $times ),
			self::validate_approvals( $record['approvals_required'], $service_refs, $traveler_refs, $now, $times, true ),
			self::validate_questions( $record['unresolved_questions'], $service_refs, $traveler_refs, $now, $times, true ),
			self::validate_vip_cases( $record['trip_care_cases'], $service_refs, $traveler_refs, $now, $times ),
			self::validate_trip_care_receipts( $record['trip_care_receipts'], $service_refs, $traveler_refs, $now, $times ),
			self::validate_money_status( $record['money_status'], $now, $times ),
		);
		foreach ( $checks as $check ) {
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}
		$loyalty = self::validate_loyalty( $record['loyalty'], $service_refs, $traveler_refs, $now, $times );
		$offline = self::validate_offline_pack( $record['offline_pack'], $service_refs, $traveler_refs, $now, $times );
		if ( is_wp_error( $loyalty ) || is_wp_error( $offline ) ) {
			return is_wp_error( $loyalty ) ? $loyalty : $offline;
		}
		if ( $record['last_verified_at'] !== self::latest_time( $times ) ) {
			return self::error( 'projection_clock_invalid', 'The cockpit last-verified time must equal the newest retained source observation.' );
		}
		$expected = self::projection_digest( $record );
		if ( '' === $expected || ! hash_equals( $record['projection_digest'], $expected ) ) {
			return self::error( 'projection_digest_invalid', 'The cockpit projection no longer matches its complete integrity digest.', 409 );
		}
		return $record;
	}

	/** Seal the complete private read model. */
	public static function seal_projection( $record ) {
		$record['projection_digest'] = self::projection_digest( $record );
		return $record;
	}

	/** Return the canonical read-model digest. */
	public static function projection_digest( $record ) {
		if ( ! is_array( $record ) ) {
			return '';
		}
		$copy = $record;
		unset( $copy['projection_digest'] );
		$encoded = wp_json_encode( self::canonicalize( $copy ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
	}

	/**
	 * Enforce monotonic ancestry and prevent unaffected services from changing or
	 * disappearing silently between projections.
	 *
	 * @return true|WP_Error
	 */
	public static function assert_successor( $previous, $next, $now ) {
		$previous = self::validate_projection( $previous, $now );
		$next = self::validate_projection( $next, $now );
		if ( is_wp_error( $previous ) || is_wp_error( $next ) ) {
			return self::error( 'successor_projection_invalid', 'Both cockpit projections must validate at the comparison clock.', 409 );
		}
		if ( $previous['cockpit_ref'] !== $next['cockpit_ref'] || $previous['trip_ref'] !== $next['trip_ref'] || $previous['owner_scope_digest'] !== $next['owner_scope_digest'] || $next['revision'] !== $previous['revision'] + 1 || $next['previous_projection_digest'] !== $previous['projection_digest'] ) {
			return self::error( 'successor_identity_invalid', 'A cockpit successor must preserve owner/trip identity and bind the exact previous revision.', 409 );
		}
		$affected = array();
		foreach ( $next['changed'] as $change ) {
			foreach ( $change['affected_service_refs'] as $service_ref ) {
				$affected[ $service_ref ] = true;
			}
		}
		$next_services = array();
		foreach ( $next['service_timeline'] as $service ) {
			$next_services[ $service['service_ref'] ] = $service;
		}
		foreach ( $previous['service_timeline'] as $service ) {
			$ref = $service['service_ref'];
			if ( ! isset( $next_services[ $ref ] ) ) {
				return self::error( 'successor_service_removed', 'A cockpit successor cannot silently remove a partial-trip service.', 409 );
			}
			if ( ! isset( $affected[ $ref ] ) && self::service_state_basis( $service ) !== self::service_state_basis( $next_services[ $ref ] ) ) {
				return self::error( 'successor_unaffected_service_changed', 'An unaffected service cannot change phase, health, fulfillment, change, protection, or timeline state.', 409 );
			}
		}
		return true;
	}

	private static function validate_registration( $value, $service_refs, $traveler_refs, $now, &$times ) {
		$keys = array( 'gate', 'readiness', 'pending_requirement_codes', 'next_action', 'verified_at' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['gate'], Tra_Vel_VIP_Taxonomy::REGISTRATION_GATES, true ) || ! in_array( $value['readiness'], self::REGISTRATION_READINESS, true ) || ! self::code_list( $value['pending_requirement_codes'], 32 ) || ! self::utc_at_or_before( $value['verified_at'], $now ) ) {
			return self::error( 'registration_invalid', 'Registration gate/readiness projection is invalid.' );
		}
		$action = self::validate_action( $value['next_action'], $service_refs, $traveler_refs, $now );
		if ( is_wp_error( $action ) || ( 'ready' === $value['readiness'] && ( $value['pending_requirement_codes'] || null !== $value['next_action'] ) ) || ( 'ready' !== $value['readiness'] && ( ! $value['pending_requirement_codes'] || null === $value['next_action'] ) ) ) {
			return self::error( 'registration_consistency_invalid', 'Registration readiness cannot hide pending requirements or invent work for a ready gate.' );
		}
		$times[] = $value['verified_at'];
		return true;
	}

	private static function validate_trip_health( $value, $service_refs, $traveler_refs, $now, &$times ) {
		$keys = array( 'phase', 'health', 'dependency_projection_ref', 'recovery_projection_ref', 'affected_service_refs', 'unaffected_service_refs', 'next_action', 'verified_at' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['phase'], self::PHASES, true ) || ! in_array( $value['health'], self::HEALTH_STATES, true ) || ! self::nullable_ref( $value['dependency_projection_ref'], 'graph' ) || ! self::nullable_ref( $value['recovery_projection_ref'], 'recovery' ) || ! self::bound_ref_list( $value['affected_service_refs'], $service_refs ) || ! self::bound_ref_list( $value['unaffected_service_refs'], $service_refs ) || array_intersect( $value['affected_service_refs'], $value['unaffected_service_refs'] ) || ! self::utc_at_or_before( $value['verified_at'], $now ) ) {
			return self::error( 'trip_health_invalid', 'Trip dependency/recovery health is malformed or does not bind known services.' );
		}
		$partition = array_values( array_unique( array_merge( $value['affected_service_refs'], $value['unaffected_service_refs'] ) ) );
		sort( $partition, SORT_STRING );
		$known = $service_refs;
		sort( $known, SORT_STRING );
		if ( $partition !== $known ) {
			return self::error( 'trip_health_partition_invalid', 'Affected and unaffected service references must form an exact partition of the actual partial trip.' );
		}
		$action = self::validate_action( $value['next_action'], $service_refs, $traveler_refs, $now );
		if ( is_wp_error( $action ) ) {
			return $action;
		}
		if ( in_array( $value['health'], array( 'disrupted', 'recovery_in_progress', 'uncertain' ), true ) && ! $value['affected_service_refs'] ) {
			return self::error( 'trip_health_impact_invalid', 'Disruption, recovery, and uncertainty require at least one explicitly affected service.' );
		}
		if ( in_array( $value['health'], array( 'action_required', 'disrupted', 'recovery_in_progress', 'uncertain' ), true ) && null === $value['next_action'] ) {
			return self::error( 'trip_health_action_missing', 'A trip requiring attention must retain an explicit next action rather than only an alarming status.' );
		}
		$times[] = $value['verified_at'];
		return true;
	}

	private static function validate_services( $values, $now, &$times ) {
		if ( ! self::is_list( $values ) || ! $values || count( $values ) > self::MAX_SERVICES ) {
			return self::error( 'services_invalid', 'A cockpit must contain a bounded, non-empty list of actual trip services.' );
		}
		$map = array();
		$sequences = array();
		foreach ( $values as $service ) {
			$keys = array( 'service_ref', 'sequence', 'vertical', 'label', 'phase', 'health', 'fulfillment', 'change_state', 'protected_codes', 'next_action', 'events', 'verified_at' );
			if ( ! self::exact_object( $service, $keys ) || ! self::ref( $service['service_ref'], 'service' ) || isset( $map[ $service['service_ref'] ] ) || ! self::positive_int( $service['sequence'] ) || $service['sequence'] > self::MAX_SERVICES || isset( $sequences[ $service['sequence'] ] ) || ! in_array( $service['vertical'], self::VERTICALS, true ) || ! self::safe_label( $service['label'], 100 ) || ! in_array( $service['phase'], self::SERVICE_PHASES, true ) || ! in_array( $service['health'], self::HEALTH_STATES, true ) || ! in_array( $service['change_state'], self::SERVICE_CHANGE_STATES, true ) || ! self::code_list( $service['protected_codes'], 32 ) || ! self::utc_at_or_before( $service['verified_at'], $now ) ) {
				return self::error( 'service_invalid', 'A service timeline entry is malformed, duplicated, or outside the closed vocabulary.' );
			}
			$fulfillment = $service['fulfillment'];
			if ( ! self::exact_object( $fulfillment, array( 'state', 'truth_state' ) ) || ! in_array( $fulfillment['state'], self::FULFILLMENT_STATES, true ) || ! in_array( $fulfillment['truth_state'], self::TRUTH_STATES, true ) ) {
				return self::error( 'service_fulfillment_invalid', 'Fulfillment state and supplier truth must remain independent closed axes.' );
			}
			$event_result = self::validate_service_events( $service['events'], $service['verified_at'], $now, $times );
			if ( is_wp_error( $event_result ) ) {
				return $event_result;
			}
			$map[ $service['service_ref'] ] = $service;
			$sequences[ $service['sequence'] ] = true;
			$times[] = $service['verified_at'];
		}
		return $map;
	}

	private static function validate_service_actions( $services, $service_refs, $traveler_refs, $now ) {
		foreach ( $services as $service ) {
			$action = self::validate_action( $service['next_action'], $service_refs, $traveler_refs, $now );
			if ( is_wp_error( $action ) ) {
				return $action;
			}
		}
		return true;
	}

	private static function validate_service_events( $values, $service_verified_at, $now, &$times ) {
		if ( ! self::is_list( $values ) || count( $values ) > 24 ) {
			return self::error( 'service_events_invalid', 'Service timeline events must be a bounded list.' );
		}
		$seen = array();
		foreach ( $values as $event ) {
			if ( ! self::exact_object( $event, array( 'event_ref', 'event_code', 'state', 'truth_state', 'occurred_at' ) ) || ! self::ref( $event['event_ref'], 'timeline_event' ) || isset( $seen[ $event['event_ref'] ] ) || ! self::code( $event['event_code'] ) || ! self::code( $event['state'] ) || ! in_array( $event['truth_state'], self::TRUTH_STATES, true ) || ! self::utc_at_or_before( $event['occurred_at'], $now ) || $event['occurred_at'] > $service_verified_at ) {
				return self::error( 'service_event_invalid', 'A service timeline event is malformed, duplicated, future-dated, or newer than its service projection.' );
			}
			$seen[ $event['event_ref'] ] = true;
			$times[] = $event['occurred_at'];
		}
		return true;
	}

	private static function validate_protections( $values, $service_refs, $now, &$times ) {
		if ( ! self::is_list( $values ) || count( $values ) > 128 ) {
			return self::error( 'protections_invalid', 'Protection projections must be a bounded list.' );
		}
		$seen = array();
		foreach ( $values as $item ) {
			if ( ! self::exact_object( $item, array( 'protection_code', 'service_refs', 'state', 'verified_at' ) ) || ! self::code( $item['protection_code'] ) || isset( $seen[ $item['protection_code'] ] ) || ! self::bound_ref_list( $item['service_refs'], $service_refs, true ) || ! in_array( $item['state'], self::PROTECTION_STATES, true ) || ! self::utc_at_or_before( $item['verified_at'], $now ) ) {
				return self::error( 'protection_invalid', 'A protection projection is malformed, duplicated, or not bound to an actual service.' );
			}
			$seen[ $item['protection_code'] ] = true;
			$times[] = $item['verified_at'];
		}
		return true;
	}

	private static function validate_changes( $values, $service_refs, $affected_refs, $now, &$times ) {
		if ( ! self::is_list( $values ) || count( $values ) > self::MAX_EVENTS ) {
			return self::error( 'changes_invalid', 'Change projections must be a bounded list.' );
		}
		$seen = array();
		foreach ( $values as $change ) {
			if ( ! self::exact_object( $change, array( 'change_ref', 'change_code', 'affected_service_refs', 'truth_state', 'observed_at' ) ) || ! self::ref( $change['change_ref'], 'change' ) || isset( $seen[ $change['change_ref'] ] ) || ! self::code( $change['change_code'] ) || ! self::bound_ref_list( $change['affected_service_refs'], $service_refs, true ) || ! in_array( $change['truth_state'], self::TRUTH_STATES, true ) || ! self::utc_at_or_before( $change['observed_at'], $now ) ) {
				return self::error( 'change_invalid', 'A change projection is malformed, duplicated, or not bound to actual services.' );
			}
			if ( is_array( $affected_refs ) && array_diff( $change['affected_service_refs'], $affected_refs ) ) {
				return self::error( 'change_impact_invalid', 'A changed service must also appear in the trip-health affected partition.' );
			}
			$seen[ $change['change_ref'] ] = true;
			$times[] = $change['observed_at'];
		}
		return true;
	}

	private static function validate_approvals( $values, $service_refs, $traveler_refs, $now, &$times, $required_only = false ) {
		if ( ! self::is_list( $values ) || count( $values ) > 128 ) {
			return self::error( 'approvals_invalid', 'Approval projections must be a bounded list.' );
		}
		$seen = array();
		foreach ( $values as $approval ) {
			$keys = array( 'approval_ref', 'scope_code', 'status', 'priority', 'service_refs', 'traveler_refs', 'deadline', 'truth_state', 'verified_at' );
			if ( ! self::exact_object( $approval, $keys ) || ! self::ref( $approval['approval_ref'], 'approval' ) || isset( $seen[ $approval['approval_ref'] ] ) || ! self::code( $approval['scope_code'] ) || ! in_array( $approval['status'], self::APPROVAL_STATES, true ) || ( $required_only && ! in_array( $approval['status'], array( 'pending', 'expired' ), true ) ) || ! in_array( $approval['priority'], self::PRIORITIES, true ) || ! self::bound_ref_list( $approval['service_refs'], $service_refs ) || ! self::bound_ref_list( $approval['traveler_refs'], $traveler_refs ) || ! self::nullable_utc( $approval['deadline'] ) || ! in_array( $approval['truth_state'], self::TRUTH_STATES, true ) || ! self::utc_at_or_before( $approval['verified_at'], $now ) ) {
				return self::error( 'approval_invalid', 'An approval projection is malformed, duplicated, or not bound to the current trip.' );
			}
			$seen[ $approval['approval_ref'] ] = true;
			$times[] = $approval['verified_at'];
		}
		return true;
	}

	private static function validate_questions( $values, $service_refs, $traveler_refs, $now, &$times, $unresolved_only = false ) {
		if ( ! self::is_list( $values ) || count( $values ) > 128 ) {
			return self::error( 'questions_invalid', 'Question projections must be a bounded list.' );
		}
		$seen = array();
		foreach ( $values as $question ) {
			$keys = array( 'question_ref', 'question_code', 'status', 'priority', 'service_refs', 'traveler_refs', 'deadline', 'truth_state', 'verified_at' );
			if ( ! self::exact_object( $question, $keys ) || ! self::ref( $question['question_ref'], 'question' ) || isset( $seen[ $question['question_ref'] ] ) || ! self::code( $question['question_code'] ) || ! in_array( $question['status'], self::QUESTION_STATES, true ) || ( $unresolved_only && 'answered' === $question['status'] ) || ! in_array( $question['priority'], self::PRIORITIES, true ) || ! self::bound_ref_list( $question['service_refs'], $service_refs ) || ! self::bound_ref_list( $question['traveler_refs'], $traveler_refs ) || ! self::nullable_utc( $question['deadline'] ) || ! in_array( $question['truth_state'], self::TRUTH_STATES, true ) || ! self::utc_at_or_before( $question['verified_at'], $now ) ) {
				return self::error( 'question_invalid', 'An unresolved-question projection is malformed, duplicated, or not bound to the current trip.' );
			}
			$seen[ $question['question_ref'] ] = true;
			$times[] = $question['verified_at'];
		}
		return true;
	}

	private static function validate_vip_cases( $values, $service_refs, $traveler_refs, $now, &$times ) {
		if ( ! self::is_list( $values ) || count( $values ) > 64 ) {
			return self::error( 'vip_cases_invalid', 'VIP case projections must be a bounded list.' );
		}
		$seen = array();
		foreach ( $values as $case ) {
			$keys = array( 'case_ref', 'customer_state', 'severity', 'service_refs', 'next_action', 'deadline', 'verified_at' );
			if ( ! self::exact_object( $case, $keys ) || ! self::ref( $case['case_ref'], 'case' ) || isset( $seen[ $case['case_ref'] ] ) || ! in_array( $case['customer_state'], self::VIP_CASE_STATES, true ) || ! in_array( $case['severity'], Tra_Vel_VIP_Taxonomy::SEVERITIES, true ) || ! self::bound_ref_list( $case['service_refs'], $service_refs ) || ! self::nullable_utc( $case['deadline'] ) || ! self::utc_at_or_before( $case['verified_at'], $now ) ) {
				return self::error( 'vip_case_invalid', 'A VIP case customer projection is malformed or not bound to this trip.' );
			}
			$action = self::validate_action( $case['next_action'], $service_refs, $traveler_refs, $now );
			if ( is_wp_error( $action ) ) {
				return $action;
			}
			if ( in_array( $case['customer_state'], array( 'immediate_safety_help', 'action_required', 'recovery_underway', 'attention_needed' ), true ) && null === $case['next_action'] ) {
				return self::error( 'vip_case_action_missing', 'An active VIP case must retain its explicit customer next action.' );
			}
			$seen[ $case['case_ref'] ] = true;
			$times[] = $case['verified_at'];
		}
		return true;
	}

	private static function validate_trip_care_receipts( $values, $service_refs, $traveler_refs, $now, &$times ) {
		if ( ! self::is_list( $values ) || count( $values ) > 128 ) {
			return self::error( 'trip_care_receipts_invalid', 'Trip Care receipt projections must be a bounded list.' );
		}
		$seen = array();
		foreach ( $values as $receipt ) {
			$keys = array( 'receipt_ref', 'customer_state', 'service_refs', 'next_action', 'deadline', 'verified_at' );
			if ( ! self::exact_object( $receipt, $keys ) || ! self::ref( $receipt['receipt_ref'], 'receipt' ) || isset( $seen[ $receipt['receipt_ref'] ] ) || ! in_array( $receipt['customer_state'], self::TRIP_CARE_STATES, true ) || ! self::bound_ref_list( $receipt['service_refs'], $service_refs ) || ! self::nullable_utc( $receipt['deadline'] ) || ! self::utc_at_or_before( $receipt['verified_at'], $now ) ) {
				return self::error( 'trip_care_receipt_invalid', 'A Trip Care receipt is malformed, duplicated, or not bound to this trip.' );
			}
			$action = self::validate_action( $receipt['next_action'], $service_refs, $traveler_refs, $now );
			if ( is_wp_error( $action ) ) {
				return $action;
			}
			if ( 'need_information' === $receipt['customer_state'] && null === $receipt['next_action'] ) {
				return self::error( 'trip_care_receipt_action_missing', 'A Trip Care receipt requesting information must state the next customer action.' );
			}
			$seen[ $receipt['receipt_ref'] ] = true;
			$times[] = $receipt['verified_at'];
		}
		return true;
	}

	private static function validate_commerce_orders( $values, $service_refs, $traveler_refs, $now, &$times ) {
		if ( ! self::is_list( $values ) || count( $values ) > 64 ) {
			return self::error( 'commerce_orders_invalid', 'Commerce status projections must be a bounded list.' );
		}
		$seen = array();
		foreach ( $values as $order ) {
			$keys = array( 'order_ref', 'service_refs', 'funds_state', 'funds_truth_state', 'payment_state', 'payment_truth_state', 'fulfillment_state', 'fulfillment_truth_state', 'refund_state', 'refund_truth_state', 'settlement_state', 'settlement_truth_state', 'next_action', 'verified_at' );
			if ( ! self::exact_object( $order, $keys ) || ! self::ref( $order['order_ref'], 'order' ) || isset( $seen[ $order['order_ref'] ] ) || ! self::bound_ref_list( $order['service_refs'], $service_refs, true ) || ! in_array( $order['funds_state'], self::FUNDS_STATES, true ) || ! in_array( $order['funds_truth_state'], self::TRUTH_STATES, true ) || ! in_array( $order['payment_state'], self::PAYMENT_STATES, true ) || ! in_array( $order['payment_truth_state'], self::TRUTH_STATES, true ) || ! in_array( $order['fulfillment_state'], self::FULFILLMENT_STATES, true ) || ! in_array( $order['fulfillment_truth_state'], self::TRUTH_STATES, true ) || ! in_array( $order['refund_state'], self::REFUND_STATES, true ) || ! in_array( $order['refund_truth_state'], self::TRUTH_STATES, true ) || ! in_array( $order['settlement_state'], self::SETTLEMENT_STATES, true ) || ! in_array( $order['settlement_truth_state'], self::TRUTH_STATES, true ) || ! self::utc_at_or_before( $order['verified_at'], $now ) ) {
				return self::error( 'commerce_order_invalid', 'Payment, fulfillment, refund, and settlement truth must remain separate, closed axes.' );
			}
			$action = self::validate_action( $order['next_action'], $service_refs, $traveler_refs, $now );
			if ( is_wp_error( $action ) ) {
				return $action;
			}
			$seen[ $order['order_ref'] ] = true;
			$times[] = $order['verified_at'];
		}
		return true;
	}

	private static function validate_money_status( $value, $now, &$times ) {
		if ( ! self::exact_object( $value, array( 'funds', 'payments', 'refunds', 'settlements' ) ) ) {
			return self::error( 'money_status_invalid', 'Payment, refund, and settlement projections must remain separate.' );
		}
		$axes = array(
			'funds' => self::FUNDS_STATES,
			'payments' => self::PAYMENT_STATES,
			'refunds' => self::REFUND_STATES,
			'settlements' => self::SETTLEMENT_STATES,
		);
		foreach ( $axes as $axis => $allowed ) {
			$seen = array();
			if ( ! self::is_list( $value[ $axis ] ) || count( $value[ $axis ] ) > 64 ) {
				return self::error( 'money_axis_invalid', 'A money-status axis is not a bounded list.' );
			}
			foreach ( $value[ $axis ] as $item ) {
				if ( ! self::exact_object( $item, array( 'order_ref', 'state', 'truth_state', 'verified_at' ) ) || ! self::ref( $item['order_ref'], 'order' ) || isset( $seen[ $item['order_ref'] ] ) || ! in_array( $item['state'], $allowed, true ) || ! in_array( $item['truth_state'], self::TRUTH_STATES, true ) || ! self::utc_at_or_before( $item['verified_at'], $now ) ) {
					return self::error( 'money_axis_item_invalid', 'A payment, refund, or settlement item is invalid or duplicated.' );
				}
				$seen[ $item['order_ref'] ] = true;
				$times[] = $item['verified_at'];
			}
		}
		return true;
	}

	private static function validate_loyalty( $value, $service_refs, $traveler_refs, $now, &$times ) {
		$keys = array( 'status', 'affected_service_refs', 'next_action', 'verified_at' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['status'], self::LOYALTY_STATES, true ) || ! self::bound_ref_list( $value['affected_service_refs'], $service_refs ) || ! self::utc_at_or_before( $value['verified_at'], $now ) ) {
			return self::error( 'loyalty_invalid', 'Loyalty planning status is malformed or not bound to actual services.' );
		}
		$action = self::validate_action( $value['next_action'], $service_refs, $traveler_refs, $now );
		if ( is_wp_error( $action ) ) {
			return $action;
		}
		$times[] = $value['verified_at'];
		return true;
	}

	private static function validate_travelers( $values, $service_refs, $now, &$times ) {
		if ( ! self::is_list( $values ) || ! $values || count( $values ) > self::MAX_TRAVELERS ) {
			return self::error( 'traveler_readiness_invalid', 'Traveler readiness must contain a bounded, non-empty party projection.' );
		}
		$map = array();
		foreach ( $values as $traveler ) {
			$keys = array( 'traveler_ref', 'subject_kind', 'readiness', 'pending_requirement_codes', 'next_action', 'deadline', 'verified_at' );
			if ( ! self::exact_object( $traveler, $keys ) || ! self::ref( $traveler['traveler_ref'], 'traveler' ) || isset( $map[ $traveler['traveler_ref'] ] ) || ! in_array( $traveler['subject_kind'], self::SUBJECT_KINDS, true ) || ! in_array( $traveler['readiness'], self::TRAVELER_READINESS, true ) || ! self::code_list( $traveler['pending_requirement_codes'], 64 ) || ! self::nullable_utc( $traveler['deadline'] ) || ! self::utc_at_or_before( $traveler['verified_at'], $now ) ) {
				return self::error( 'traveler_readiness_item_invalid', 'A traveler-specific readiness projection is malformed or duplicated.' );
			}
			$map[ $traveler['traveler_ref'] ] = $traveler;
			$times[] = $traveler['verified_at'];
		}
		$traveler_refs = array_keys( $map );
		foreach ( $values as $traveler ) {
			$action = self::validate_action( $traveler['next_action'], $service_refs, $traveler_refs, $now );
			if ( is_wp_error( $action ) ) {
				return $action;
			}
			if ( 'ready' === $traveler['readiness'] && ( $traveler['pending_requirement_codes'] || null !== $traveler['next_action'] ) ) {
				return self::error( 'traveler_readiness_consistency_invalid', 'A ready traveler cannot retain pending requirements or a readiness action.' );
			}
			if ( 'ready' !== $traveler['readiness'] && ( ! $traveler['pending_requirement_codes'] || null === $traveler['next_action'] ) ) {
				return self::error( 'traveler_readiness_action_missing', 'A traveler needing attention must retain explicit missing requirements and a next action.' );
			}
		}
		return $map;
	}

	private static function validate_offline_pack( $value, $service_refs, $traveler_refs, $now, &$times ) {
		$keys = array( 'status', 'itinerary', 'service_contacts', 'emergency_contacts', 'next_action', 'verified_at' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['status'], self::OFFLINE_PACK_STATES, true ) || ! in_array( $value['itinerary'], self::OFFLINE_COMPONENT_STATES, true ) || ! in_array( $value['service_contacts'], self::OFFLINE_COMPONENT_STATES, true ) || ! in_array( $value['emergency_contacts'], self::OFFLINE_COMPONENT_STATES, true ) || ! self::utc_at_or_before( $value['verified_at'], $now ) ) {
			return self::error( 'offline_pack_invalid', 'Offline itinerary/contact-pack status is malformed.' );
		}
		$action = self::validate_action( $value['next_action'], $service_refs, $traveler_refs, $now );
		if ( is_wp_error( $action ) ) {
			return $action;
		}
		if ( 'ready' === $value['status'] && ( 'ready' !== $value['itinerary'] || 'ready' !== $value['service_contacts'] || ! in_array( $value['emergency_contacts'], array( 'ready', 'not_applicable' ), true ) ) ) {
			return self::error( 'offline_pack_consistency_invalid', 'A ready offline pack requires current itinerary and service contacts plus applicable emergency contacts.' );
		}
		if ( in_array( $value['status'], array( 'stale', 'unavailable' ), true ) && null === $value['next_action'] ) {
			return self::error( 'offline_pack_action_missing', 'A stale or unavailable offline pack must retain a clear recovery action.' );
		}
		$times[] = $value['verified_at'];
		return true;
	}

	private static function validate_action( $value, $service_refs, $traveler_refs, $now ) {
		if ( null === $value ) {
			return true;
		}
		$keys = array( 'code', 'priority', 'service_refs', 'traveler_refs', 'deadline', 'truth_state' );
		if ( ! self::exact_object( $value, $keys ) || ! self::code( $value['code'] ) || ! in_array( $value['priority'], self::PRIORITIES, true ) || ! self::bound_ref_list( $value['service_refs'], $service_refs ) || ! self::bound_ref_list( $value['traveler_refs'], $traveler_refs ) || ! self::nullable_utc( $value['deadline'] ) || ! in_array( $value['truth_state'], self::TRUTH_STATES, true ) ) {
			return self::error( 'action_invalid', 'A next action must be explicit, bounded, truth-labelled, and bound to known services/travelers.' );
		}
		return true;
	}

	private static function validate_projected_action( $value, $service_refs, $traveler_refs, $now ) {
		if ( null === $value ) {
			return true;
		}
		if ( ! self::exact_object( $value, array( 'code', 'source', 'priority', 'service_refs', 'traveler_refs', 'deadline', 'truth_state' ) ) || ! in_array( $value['source'], self::ACTION_SOURCES, true ) ) {
			return self::error( 'projected_action_invalid', 'A projected urgent action requires a closed source label.' );
		}
		$copy = $value;
		unset( $copy['source'] );
		return self::validate_action( $copy, $service_refs, $traveler_refs, $now );
	}

	private static function source_boundary( $value ) {
		$keys = array( 'server_only', 'already_validated_projections_only', 'raw_identity_data_stored', 'raw_payment_data_stored', 'raw_medical_data_stored', 'raw_provider_payload_stored', 'bearer_secret_stored' );
		return self::exact_object( $value, $keys ) && true === $value['server_only'] && true === $value['already_validated_projections_only'] && false === $value['raw_identity_data_stored'] && false === $value['raw_payment_data_stored'] && false === $value['raw_medical_data_stored'] && false === $value['raw_provider_payload_stored'] && false === $value['bearer_secret_stored'];
	}

	private static function projection_boundary( $value ) {
		$keys = array( 'server_only', 'public_serialization_allowed', 'raw_identity_data_stored', 'raw_payment_data_stored', 'raw_medical_data_stored', 'raw_provider_payload_stored', 'bearer_secret_stored', 'provider_execution_claimed' );
		return self::exact_object( $value, $keys ) && true === $value['server_only'] && false === $value['public_serialization_allowed'] && false === $value['raw_identity_data_stored'] && false === $value['raw_payment_data_stored'] && false === $value['raw_medical_data_stored'] && false === $value['raw_provider_payload_stored'] && false === $value['bearer_secret_stored'] && false === $value['provider_execution_claimed'];
	}

	private static function authority_boundary( $value ) {
		return self::exact_object( $value, array( 'authorization_effect', 'supplier_action_started', 'processor_action_started', 'resolution_inferred', 'combined_booking_status_exposed' ) ) && 'none' === $value['authorization_effect'] && false === $value['supplier_action_started'] && false === $value['processor_action_started'] && false === $value['resolution_inferred'] && false === $value['combined_booking_status_exposed'];
	}

	private static function service_state_basis( $service ) {
		return self::canonicalize( array(
			'phase' => $service['phase'],
			'health' => $service['health'],
			'fulfillment' => $service['fulfillment'],
			'change_state' => $service['change_state'],
			'protected_codes' => $service['protected_codes'],
			'events' => $service['events'],
		) );
	}

	private static function contains_sensitive_material( $value, $key = '' ) {
		$key = strtolower( (string) $key );
		if ( '' !== $key && false !== $value && null !== $value && '' !== $value && preg_match( '/(?:password|secret|api[_-]?key|access[_-]?token|bearer|card[_-]?(?:number|pan)|cvv|cvc|passport|identity[_-]?number|email|phone|mobile|address|date[_-]?of[_-]?birth|diagnosis|medical[_-]?(?:note|history)|raw[_-]?(?:identity|payment|medical|provider)|provider[_-]?(?:id|name|payload|reference)|supplier[_-]?(?:id|name|payload|reference)|amount[_-]?minor)/', $key ) ) {
			return true;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $child_key => $child ) {
				if ( self::contains_sensitive_material( $child, $child_key ) ) {
					return true;
				}
			}
			return false;
		}
		if ( ! is_string( $value ) ) {
			return false;
		}
		return 1 === preg_match( '/-----BEGIN [A-Z ]*PRIVATE KEY-----|\bBearer\s+[A-Za-z0-9._~+\/=\-]{8,}|\bsk-[A-Za-z0-9_-]{12,}|[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value );
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function is_list( $value ) {
		return is_array( $value ) && array_values( $value ) === $value;
	}

	private static function ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private static function nullable_ref( $value, $kind ) {
		return null === $value || self::ref( $value, $kind );
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function nullable_digest( $value ) {
		return null === $value || self::digest( $value );
	}

	private static function positive_int( $value ) {
		return is_int( $value ) && $value > 0 && $value <= 2147483647;
	}

	private static function bounded_count( $value, $max ) {
		return is_int( $value ) && $value >= 0 && $value <= $max;
	}

	private static function code( $value ) {
		return is_string( $value ) && strlen( $value ) <= 96 && 1 === preg_match( '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $value );
	}

	private static function code_list( $values, $max ) {
		if ( ! self::is_list( $values ) || count( $values ) > $max || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! self::code( $value ) ) {
				return false;
			}
		}
		return true;
	}

	private static function bound_ref_list( $values, $known, $required = false ) {
		if ( ! self::is_list( $values ) || ( $required && ! $values ) || count( $values ) !== count( array_unique( $values ) ) || array_diff( $values, $known ) ) {
			return false;
		}
		return true;
	}

	private static function safe_label( $value, $max ) {
		return is_string( $value ) && '' !== trim( $value ) && self::text_length( $value ) <= $max && 1 !== preg_match( '/[<>\r\n]/', $value );
	}

	private static function text_length( $value ) {
		$count = preg_match_all( '/./us', $value, $matches );
		return false === $count ? strlen( $value ) : $count;
	}

	private static function nullable_utc( $value ) {
		return null === $value || false !== self::utc_timestamp( $value );
	}

	private static function utc_at_or_before( $value, $now ) {
		$time = self::utc_timestamp( $value );
		return false !== $time && is_int( $now ) && $now > 0 && $time <= $now;
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

	private static function latest_time( $times ) {
		$latest = '';
		$latest_epoch = 0;
		foreach ( $times as $value ) {
			$epoch = self::utc_timestamp( $value );
			if ( false !== $epoch && $epoch >= $latest_epoch ) {
				$latest_epoch = $epoch;
				$latest = $value;
			}
		}
		return $latest;
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

	private static function error( $suffix, $message, $status = 400 ) {
		return new WP_Error( 'tra_vel_customer_trip_cockpit_' . $suffix, $message, array( 'status' => $status ) );
	}
}
