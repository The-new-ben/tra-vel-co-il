<?php
/**
 * Closed policy for the customer-serializable Trip Cockpit view.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Customer_Trip_Cockpit_Customer_View_Policy {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_SERVICES = 64;
	const MAX_TRAVELERS = 32;
	const MAX_VIEWING_CONTEXT_LIFETIME_SECONDS = 1800;

	const ACCESS_MODES = array( 'signed_in', 'scoped_session' );
	const PHASES = array( 'planning', 'pre_trip', 'outbound', 'in_trip', 'return_trip', 'post_trip' );
	const HEALTH_STATES = array( 'on_track', 'watching', 'action_required', 'disrupted', 'recovery_in_progress', 'uncertain', 'completed_with_issue' );
	const REGISTRATION_READINESS = array( 'ready', 'attention_required', 'blocked', 'withheld' );
	const TRUTH_STATES = array( 'verified_current', 'observed_unverified', 'stale', 'uncertain', 'conflict' );
	const PRIORITIES = array( 'urgent', 'high', 'normal', 'low' );
	const INTERACTION_MODES = array( 'view', 'follow_up', 'step_up_required' );
	const REQUESTED_EFFECTS = array( 'review', 'follow_up', 'change', 'cancel', 'pay', 'refund', 'approve', 'account_update', 'commerce_review', 'issue', 'redeem', 'upgrade', 'swap', 'unknown_high_impact' );
	const VERTICALS = array( 'flight', 'accommodation', 'package', 'transfer', 'activity', 'dining', 'insurance', 'connectivity', 'equipment' );
	const SERVICE_PHASES = array( 'planned', 'held', 'confirmed', 'travel_ready', 'in_progress', 'completed', 'cancelled', 'recovery' );
	const SERVICE_CHANGE_STATES = array( 'unchanged', 'change_observed', 'change_pending', 'changed', 'cancellation_pending', 'cancelled', 'uncertain' );
	const FULFILLMENT_STATES = array( 'selected', 'hold_pending', 'held', 'confirmation_pending', 'confirmed', 'fulfillment_pending', 'fulfilled', 'change_pending', 'changed', 'cancellation_pending', 'cancelled', 'failed', 'reconciliation_required' );
	const PROTECTION_STATES = array( 'active', 'at_risk', 'unverified', 'expired', 'not_applicable' );
	const PAYMENT_STATES = array( 'not_started', 'pending', 'requires_action', 'authorized', 'captured', 'failed', 'voided', 'partially_refunded', 'refunded', 'uncertain', 'disputed', 'charged_back' );
	const REFUND_STATES = array( 'not_requested', 'requested', 'pending', 'partially_refunded', 'refunded', 'failed', 'uncertain', 'disputed' );
	const VIP_CASE_STATES = array( 'case_received', 'immediate_safety_help', 'action_required', 'recovery_underway', 'attention_needed', 'recovered', 'resolved_with_loss' );
	const TRIP_CARE_STATES = array( 'received', 'checking', 'need_information', 'human_review' );
	const SUBJECT_KINDS = array( 'adult', 'minor', 'dependent_adult' );
	const TRAVELER_READINESS = array( 'ready', 'attention_required', 'blocked', 'unknown' );
	const LOYALTY_STATES = array( 'not_requested', 'planning', 'member_connection_needed', 'benefits_checking', 'options_ready', 'stale', 'unavailable', 'withheld' );
	const LOYALTY_FILTER_READINESS = array( 'not_requested', 'checking', 'connection_required', 'ready', 'refresh_required', 'unavailable', 'withheld' );
	const OFFLINE_PACK_STATES = array( 'not_requested', 'building', 'ready', 'stale', 'unavailable' );
	const OFFLINE_COMPONENT_STATES = array( 'ready', 'stale', 'missing', 'not_applicable' );
	const VIEWING_SCOPES = array( 'trip_view_redacted', 'incident_report', 'case_progress_view' );
	const CUSTOMER_CODES = array(
		'flight.confirmed', 'stay.confirmed', 'confirmed', 'schedule.change',
		'trip.insurance', 'schedule.monitoring', 'late.arrival.monitoring',
		'approve.flight.change', 'confirm.preference', 'provide.trip.update', 'provide.trip.details',
		'change.flight', 'cancel.flight', 'payment.authorize', 'refund.request',
		'issue.ticket', 'redeem.points', 'upgrade.service', 'swap.service',
		'report.issue', 'case.follow_up', 'question.follow_up', 'view.trip_status',
		'view.offline_itinerary', 'refresh.offline_pack',
		'service.event', 'status.updated', 'protection.status', 'service.change', 'traveler.requirement',
		'registration.review', 'trip.review', 'service.review', 'approval.review', 'question.review', 'payment.review',
		'loyalty.review', 'traveler_readiness.review', 'offline_pack.review', 'trip.status',
		'trip_care.follow_up',
		'registration', 'trip_health', 'service', 'approval', 'question', 'vip_case', 'trip_care',
		'commerce', 'loyalty', 'traveler', 'offline_pack'
	);

	/** Validate one customer-safe view. */
	public static function validate_view( $record, $now ) {
		$root_keys = array(
			'contract_version', 'environment', 'audience', 'trip_headline', 'current',
			'next_safe_action', 'protections', 'changes', 'attention_items', 'service_timeline',
			'customer_money', 'case_progress_disclosure', 'trip_care_cases', 'trip_care_receipts',
			'traveler_readiness_disclosure', 'traveler_readiness',
			'loyalty', 'offline_pack', 'freshness', 'authority', 'data_boundary',
		);
		if ( ! self::exact_object( $record, $root_keys ) || self::contains_sensitive_material( $record ) ) {
			return self::error( 'shape_invalid', 'The customer Trip Cockpit view must be closed, redacted, and free of private material.' );
		}
		if ( self::CONTRACT_VERSION !== $record['contract_version'] || 'sandbox' !== $record['environment'] || ! self::safe_label( $record['trip_headline'], 160 ) ) {
			return self::error( 'identity_invalid', 'The customer Trip Cockpit contract, environment, or headline is invalid.' );
		}

		$audience = self::validate_audience( $record['audience'] );
		if ( is_wp_error( $audience ) ) {
			return $audience;
		}

		$services = self::validate_services( $record['service_timeline'], $now );
		if ( is_wp_error( $services ) ) {
			return $services;
		}
		$service_keys = array_keys( $services );
		$mode = $record['audience']['mode'];
		$travelers = self::validate_travelers( $record['traveler_readiness'], $record['traveler_readiness_disclosure'], $service_keys, $now, $mode );
		if ( is_wp_error( $travelers ) ) {
			return $travelers;
		}
		$traveler_slots = array_keys( $travelers );
		foreach ( $record['service_timeline'] as $service ) {
			$service_action = self::validate_action( $service['next_safe_action'], $service_keys, $traveler_slots );
			if ( is_wp_error( $service_action ) ) {
				return $service_action;
			}
		}

		$current = self::validate_current( $record['current'], $record['next_safe_action'], $services, $now, $mode );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$action = self::validate_action( $record['next_safe_action'], $service_keys, $traveler_slots );
		if ( is_wp_error( $action ) ) {
			return $action;
		}

		$checks = array(
			self::validate_protections( $record['protections'], $service_keys, $now ),
			self::validate_changes( $record['changes'], $record['current']['declared_affected_service_keys'], $service_keys, $now ),
			self::validate_attention( $record['attention_items'], $service_keys, $traveler_slots, $now ),
			self::validate_money( $record['customer_money'], $now, $mode ),
			self::validate_case_progress_disclosure( $record['case_progress_disclosure'], $record['trip_care_cases'], $record['trip_care_receipts'], $record['audience']['follow_up_allowed'] ),
			self::validate_cases( $record['trip_care_cases'], $service_keys, $traveler_slots, $now ),
			self::validate_receipts( $record['trip_care_receipts'], $service_keys, $traveler_slots, $now ),
			self::validate_loyalty( $record['loyalty'], $service_keys, $traveler_slots, $now, $mode ),
			self::validate_offline_pack( $record['offline_pack'], $service_keys, $traveler_slots, $now ),
			self::validate_freshness( $record['freshness'], $now, $mode ),
			self::validate_authority( $record['authority'] ),
			self::validate_boundary( $record['data_boundary'] ),
		);
		foreach ( $checks as $check ) {
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}
		if ( 'scoped_session' === $mode && ! self::scoped_material_is_withheld( $record ) ) {
			return self::error( 'scoped_disclosure_invalid', 'A forwarded scoped view must not expose commerce, loyalty, or traveler-specific material.', 403 );
		}
		return $record;
	}

	/**
	 * Validate an already-established viewing context against the exact private
	 * cockpit identity. This validates a context assertion; it performs no auth.
	 *
	 * @return array|WP_Error
	 */
	public static function validate_viewing_context( $context, $private_projection, $now ) {
		$keys = array( 'mode', 'verified', 'verified_at', 'trip_ref', 'owner_scope_digest', 'expires_at', 'scopes', 'disclosure' );
		if ( ! self::exact_object( $context, $keys ) || ! in_array( $context['mode'], self::ACCESS_MODES, true ) || true !== $context['verified'] || ! self::utc_at_or_before( $context['verified_at'], $now ) || ! self::ref( $context['trip_ref'], 'trip' ) || ! is_array( $private_projection ) || ! isset( $private_projection['trip_ref'], $private_projection['owner_scope_digest'] ) || $context['trip_ref'] !== $private_projection['trip_ref'] || 'trip_redacted' !== $context['disclosure'] || ! self::scope_list( $context['scopes'] ) || ! in_array( 'trip_view_redacted', $context['scopes'], true ) ) {
			return self::error( 'viewing_context_invalid', 'The customer view requires an exact, verified, redacted trip-view context.', 403 );
		}
		$verified_epoch = self::utc_timestamp( $context['verified_at'] );
		$expires_epoch = self::utc_timestamp( $context['expires_at'] );
		if ( false === $expires_epoch || $expires_epoch <= $now || $expires_epoch <= $verified_epoch || $expires_epoch - $verified_epoch > self::MAX_VIEWING_CONTEXT_LIFETIME_SECONDS ) {
			return self::error( 'viewing_context_expiry_invalid', 'A viewing context must carry a short, unexpired per-request assertion window.', 403 );
		}
		if ( 'signed_in' === $context['mode'] ) {
			if ( ! self::digest( $context['owner_scope_digest'] ) || ! hash_equals( $private_projection['owner_scope_digest'], $context['owner_scope_digest'] ) ) {
				return self::error( 'viewing_context_owner_invalid', 'A signed-in viewing context must bind the exact private owner scope.', 403 );
			}
		} elseif ( null !== $context['owner_scope_digest'] ) {
			return self::error( 'viewing_context_session_invalid', 'A scoped viewing context must not disclose an owner digest.', 403 );
		}
		return $context;
	}

	/** Codes exposed to a customer must come from the explicit public vocabulary. */
	public static function is_customer_code( $value ) {
		return is_string( $value ) && in_array( $value, self::CUSTOMER_CODES, true );
	}

	private static function validate_audience( $value ) {
		$keys = array( 'mode', 'view_allowed', 'report_issue_allowed', 'follow_up_allowed', 'high_impact_step_up_required', 'mutation_authorized' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['mode'], self::ACCESS_MODES, true ) || true !== $value['view_allowed'] || ! is_bool( $value['report_issue_allowed'] ) || ! is_bool( $value['follow_up_allowed'] ) || true !== $value['high_impact_step_up_required'] || false !== $value['mutation_authorized'] ) {
			return self::error( 'audience_invalid', 'The audience boundary must allow viewing, reporting, and follow-up while retaining step-up for high-impact actions.' );
		}
		return true;
	}

	private static function validate_current( $value, $next_action, $services, $now, $mode ) {
		$keys = array( 'phase', 'health', 'registration_readiness', 'affected_service_count', 'unaffected_service_count', 'declared_affected_service_keys', 'partition_detail', 'action_required', 'verified_at' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['phase'], self::PHASES, true ) || ! in_array( $value['health'], self::HEALTH_STATES, true ) || ! in_array( $value['registration_readiness'], self::REGISTRATION_READINESS, true ) || ! self::bounded_count( $value['affected_service_count'], self::MAX_SERVICES ) || ! self::bounded_count( $value['unaffected_service_count'], self::MAX_SERVICES ) || ! self::key_list( $value['declared_affected_service_keys'], array_keys( $services ), 'service' ) || ! in_array( $value['partition_detail'], array( 'complete', 'partial', 'aggregate_only' ), true ) || ! is_bool( $value['action_required'] ) || ! self::utc_at_or_before( $value['verified_at'], $now ) ) {
			return self::error( 'current_invalid', 'Current trip phase, health, impact partition, action, or clock is invalid.' );
		}
		if ( count( $services ) !== $value['affected_service_count'] + $value['unaffected_service_count'] || $value['action_required'] !== ( null !== $next_action ) ) {
			return self::error( 'current_consistency_invalid', 'Current counts and action-required truth must match the actual customer view.' );
		}
		if ( ( 'scoped_session' === $mode ) !== ( 'withheld' === $value['registration_readiness'] ) ) {
			return self::error( 'registration_disclosure_invalid', 'Registration readiness must be withheld only from forwarded scoped views.' );
		}
		$declared_count = count( $value['declared_affected_service_keys'] );
		$expected_detail = $declared_count === $value['affected_service_count'] ? 'complete' : ( 0 === $declared_count ? 'aggregate_only' : 'partial' );
		if ( $declared_count > $value['affected_service_count'] || $expected_detail !== $value['partition_detail'] ) {
			return self::error( 'partition_invalid', 'Individually declared affected services may never exceed or misstate the validated aggregate partition.' );
		}
		$impact_counts = array( 'affected' => 0, 'unaffected' => 0, 'not_individually_declared' => 0 );
		foreach ( $services as $service ) {
			$impact_counts[ $service['impact_state'] ]++;
		}
		if ( $impact_counts['affected'] !== $declared_count ) {
			return self::error( 'affected_service_invalid', 'Only individually declared affected services may be labelled affected.' );
		}
		if ( 'complete' === $value['partition_detail'] ) {
			if ( $impact_counts['unaffected'] !== $value['unaffected_service_count'] || 0 !== $impact_counts['not_individually_declared'] ) {
				return self::error( 'complete_partition_invalid', 'A complete partition must exactly label every affected and unaffected service.' );
			}
		} elseif ( 0 !== $impact_counts['unaffected'] || 0 === $impact_counts['not_individually_declared'] ) {
			return self::error( 'incomplete_partition_invalid', 'An incomplete partition must keep every non-declared service explicitly unresolved.' );
		}
		return true;
	}

	private static function validate_services( $values, $now ) {
		if ( ! self::is_list( $values ) || ! $values || count( $values ) > self::MAX_SERVICES ) {
			return self::error( 'services_invalid', 'The customer service timeline must be a bounded, non-empty list.' );
		}
		$map = array();
		$sequences = array();
		foreach ( $values as $service ) {
			$keys = array( 'service_key', 'sequence', 'vertical', 'label', 'phase', 'health', 'fulfillment', 'change_state', 'impact_state', 'protected_codes', 'next_safe_action', 'events', 'verified_at' );
			if ( ! self::exact_object( $service, $keys ) || ! self::key( $service['service_key'], 'service' ) || isset( $map[ $service['service_key'] ] ) || ! is_int( $service['sequence'] ) || $service['sequence'] < 1 || $service['sequence'] > self::MAX_SERVICES || isset( $sequences[ $service['sequence'] ] ) || ! in_array( $service['vertical'], self::VERTICALS, true ) || ! self::safe_label( $service['label'], 100 ) || ! in_array( $service['phase'], self::SERVICE_PHASES, true ) || ! in_array( $service['health'], self::HEALTH_STATES, true ) || ! self::validate_fulfillment( $service['fulfillment'] ) || ! in_array( $service['change_state'], self::SERVICE_CHANGE_STATES, true ) || ! in_array( $service['impact_state'], array( 'affected', 'unaffected', 'not_individually_declared' ), true ) || ! self::code_list( $service['protected_codes'], 64 ) || ! self::utc_at_or_before( $service['verified_at'], $now ) ) {
				return self::error( 'service_invalid', 'A customer service item is malformed, duplicated, unsafe, or stale-dated in the future.' );
			}
			if ( ! self::validate_events( $service['events'], $now ) ) {
				return self::error( 'service_events_invalid', 'Customer service events must be bounded, redacted, truth-labelled timeline facts.' );
			}
			$map[ $service['service_key'] ] = $service;
			$sequences[ $service['sequence'] ] = true;
		}
		return $map;
	}

	private static function validate_fulfillment( $value ) {
		return self::exact_object( $value, array( 'state', 'truth_state' ) ) && in_array( $value['state'], self::FULFILLMENT_STATES, true ) && in_array( $value['truth_state'], self::TRUTH_STATES, true );
	}

	private static function validate_events( $values, $now ) {
		if ( ! self::is_list( $values ) || count( $values ) > 24 ) {
			return false;
		}
		foreach ( $values as $event ) {
			if ( ! self::exact_object( $event, array( 'event_code', 'state', 'truth_state', 'occurred_at' ) ) || ! self::is_customer_code( $event['event_code'] ) || ! self::is_customer_code( $event['state'] ) || ! in_array( $event['truth_state'], self::TRUTH_STATES, true ) || ! self::utc_at_or_before( $event['occurred_at'], $now ) ) {
				return false;
			}
		}
		return true;
	}

	private static function validate_action( $value, $service_keys, $traveler_slots ) {
		if ( null === $value ) {
			return true;
		}
		$keys = array( 'code', 'source', 'priority', 'service_keys', 'traveler_slots', 'deadline', 'truth_state', 'requested_effect', 'interaction_mode', 'execution_effect' );
		if ( ! self::exact_object( $value, $keys ) || ! self::is_customer_code( $value['code'] ) || ! self::is_customer_code( $value['source'] ) || ! in_array( $value['priority'], self::PRIORITIES, true ) || ! self::key_list( $value['service_keys'], $service_keys, 'service' ) || ! self::key_list( $value['traveler_slots'], $traveler_slots, 'traveler' ) || ! self::nullable_utc( $value['deadline'] ) || ! in_array( $value['truth_state'], self::TRUTH_STATES, true ) || ! in_array( $value['requested_effect'], self::REQUESTED_EFFECTS, true ) || ! in_array( $value['interaction_mode'], self::INTERACTION_MODES, true ) || 'none' !== $value['execution_effect'] ) {
			return self::error( 'action_invalid', 'A customer next action must be closed, bound, truth-labelled, and non-executing.' );
		}
		$high_impact = ! in_array( $value['requested_effect'], array( 'review', 'follow_up' ), true );
		if ( ( $high_impact && 'step_up_required' !== $value['interaction_mode'] ) || ( 'follow_up' === $value['requested_effect'] && 'follow_up' !== $value['interaction_mode'] ) || ( 'review' === $value['requested_effect'] && 'view' !== $value['interaction_mode'] ) ) {
			return self::error( 'action_authority_invalid', 'Viewing and follow-up must remain distinct from high-impact actions that require step-up.' );
		}
		return true;
	}

	private static function validate_protections( $values, $service_keys, $now ) {
		if ( ! self::is_list( $values ) || count( $values ) > 128 ) {
			return self::error( 'protections_invalid', 'Customer protection status must be a bounded list.' );
		}
		$seen = array();
		foreach ( $values as $item ) {
			if ( ! self::exact_object( $item, array( 'protection_code', 'service_keys', 'state', 'verified_at' ) ) || ! self::is_customer_code( $item['protection_code'] ) || isset( $seen[ $item['protection_code'] ] ) || ! self::key_list( $item['service_keys'], $service_keys, 'service' ) || ! in_array( $item['state'], self::PROTECTION_STATES, true ) || ! self::utc_at_or_before( $item['verified_at'], $now ) ) {
				return self::error( 'protection_invalid', 'A customer protection status is malformed, duplicated, or not bound to this trip.' );
			}
			$seen[ $item['protection_code'] ] = true;
		}
		return true;
	}

	private static function validate_changes( $values, $declared_affected, $service_keys, $now ) {
		if ( ! self::is_list( $values ) || count( $values ) > 512 ) {
			return self::error( 'changes_invalid', 'Customer change status must be a bounded list.' );
		}
		foreach ( $values as $item ) {
			if ( ! self::exact_object( $item, array( 'change_code', 'affected_service_keys', 'truth_state', 'observed_at' ) ) || ! self::is_customer_code( $item['change_code'] ) || ! self::key_list( $item['affected_service_keys'], $service_keys, 'service', true ) || array_diff( $item['affected_service_keys'], $declared_affected ) || ! in_array( $item['truth_state'], self::TRUTH_STATES, true ) || ! self::utc_at_or_before( $item['observed_at'], $now ) ) {
				return self::error( 'change_invalid', 'A customer change must bind only individually declared affected services and retain source truth.' );
			}
		}
		return true;
	}

	private static function validate_attention( $values, $service_keys, $traveler_slots, $now ) {
		if ( ! self::is_list( $values ) || count( $values ) > 256 ) {
			return self::error( 'attention_invalid', 'Customer attention items must be a bounded list.' );
		}
		$seen = array();
		foreach ( $values as $item ) {
			$keys = array( 'attention_key', 'kind', 'code', 'status', 'priority', 'service_keys', 'traveler_slots', 'deadline', 'truth_state', 'verified_at', 'interaction_mode' );
			if ( ! self::exact_object( $item, $keys ) || ! self::key( $item['attention_key'], 'attention' ) || isset( $seen[ $item['attention_key'] ] ) || ! in_array( $item['kind'], array( 'approval', 'question' ), true ) || ! self::is_customer_code( $item['code'] ) || ! in_array( $item['status'], array( 'pending', 'expired', 'reopened' ), true ) || ! in_array( $item['priority'], self::PRIORITIES, true ) || ! self::key_list( $item['service_keys'], $service_keys, 'service' ) || ! self::key_list( $item['traveler_slots'], $traveler_slots, 'traveler' ) || ! self::nullable_utc( $item['deadline'] ) || ! in_array( $item['truth_state'], self::TRUTH_STATES, true ) || ! self::utc_at_or_before( $item['verified_at'], $now ) || 'step_up_required' !== $item['interaction_mode'] ) {
				return self::error( 'attention_item_invalid', 'Approval and question items must remain customer-safe and step-up protected.' );
			}
			$seen[ $item['attention_key'] ] = true;
		}
		return true;
	}

	private static function validate_money( $value, $now, $mode ) {
		if ( ! self::exact_object( $value, array( 'disclosure', 'payments', 'refunds' ) ) || ! in_array( $value['disclosure'], array( 'signed_in_redacted', 'withheld_scoped_session' ), true ) ) {
			return self::error( 'money_invalid', 'The customer view must keep payment and refund truth as separate axes.' );
		}
		if ( 'scoped_session' === $mode ) {
			if ( 'withheld_scoped_session' !== $value['disclosure'] || array() !== $value['payments'] || array() !== $value['refunds'] ) {
				return self::error( 'money_disclosure_invalid', 'A forwarded scoped view must disclose neither purchase presence nor payment or refund state.' );
			}
			return true;
		}
		if ( 'signed_in_redacted' !== $value['disclosure'] ) {
			return self::error( 'money_disclosure_invalid', 'A signed-in money view must use the redacted disclosure boundary.' );
		}
		$payment_keys = self::validate_money_axis( $value['payments'], self::PAYMENT_STATES, $now );
		$refund_keys = self::validate_money_axis( $value['refunds'], self::REFUND_STATES, $now );
		if ( is_wp_error( $payment_keys ) || is_wp_error( $refund_keys ) ) {
			return is_wp_error( $payment_keys ) ? $payment_keys : $refund_keys;
		}
		sort( $payment_keys, SORT_STRING );
		sort( $refund_keys, SORT_STRING );
		if ( $payment_keys !== $refund_keys ) {
			return self::error( 'money_partition_invalid', 'Each customer payment state must retain its independent refund state.' );
		}
		return true;
	}

	private static function validate_case_progress_disclosure( $disclosure, $cases, $receipts, $allowed ) {
		if ( $allowed ) {
			return 'case_progress_redacted' === $disclosure ? true : self::error( 'case_progress_disclosure_invalid', 'Case progress requires explicit redacted disclosure.' );
		}
		if ( 'withheld_scope_missing' !== $disclosure || array() !== $cases || array() !== $receipts ) {
			return self::error( 'case_progress_scope_invalid', 'Case progress must be withheld unless the verified context has case_progress_view.' );
		}
		return true;
	}

	private static function validate_money_axis( $values, $states, $now ) {
		if ( ! self::is_list( $values ) || count( $values ) > 64 ) {
			return self::error( 'money_axis_invalid', 'A customer money axis must be a bounded list.' );
		}
		$seen = array();
		foreach ( $values as $item ) {
			if ( ! self::exact_object( $item, array( 'purchase_key', 'state', 'truth_state', 'verified_at' ) ) || ! self::key( $item['purchase_key'], 'purchase' ) || isset( $seen[ $item['purchase_key'] ] ) || ! in_array( $item['state'], $states, true ) || ! in_array( $item['truth_state'], self::TRUTH_STATES, true ) || ! self::utc_at_or_before( $item['verified_at'], $now ) ) {
				return self::error( 'money_item_invalid', 'A customer payment or refund item is malformed or duplicated.' );
			}
			$seen[ $item['purchase_key'] ] = true;
		}
		return array_keys( $seen );
	}

	private static function validate_cases( $values, $service_keys, $traveler_slots, $now ) {
		return self::validate_case_like( $values, $service_keys, $traveler_slots, $now, 'case', self::VIP_CASE_STATES, 64 );
	}

	private static function validate_receipts( $values, $service_keys, $traveler_slots, $now ) {
		return self::validate_case_like( $values, $service_keys, $traveler_slots, $now, 'receipt', self::TRIP_CARE_STATES, 128 );
	}

	private static function validate_case_like( $values, $service_keys, $traveler_slots, $now, $kind, $states, $max ) {
		if ( ! self::is_list( $values ) || count( $values ) > $max ) {
			return self::error( $kind . 's_invalid', 'Trip Care status must be a bounded list.' );
		}
		$key_name = $kind . '_key';
		$seen = array();
		foreach ( $values as $item ) {
			$keys = array( $key_name, 'customer_state', 'service_keys', 'next_safe_action', 'deadline', 'verified_at' );
			if ( ! self::exact_object( $item, $keys ) || ! self::key( $item[ $key_name ], $kind ) || isset( $seen[ $item[ $key_name ] ] ) || ! in_array( $item['customer_state'], $states, true ) || ! self::key_list( $item['service_keys'], $service_keys, 'service' ) || ! self::nullable_utc( $item['deadline'] ) || ! self::utc_at_or_before( $item['verified_at'], $now ) ) {
				return self::error( $kind . '_invalid', 'A Trip Care status item is malformed, duplicated, or not bound to this trip.' );
			}
			$action = self::validate_action( $item['next_safe_action'], $service_keys, $traveler_slots );
			if ( is_wp_error( $action ) ) {
				return $action;
			}
			$seen[ $item[ $key_name ] ] = true;
		}
		return true;
	}

	private static function validate_travelers( $values, $disclosure, $service_keys, $now, $mode ) {
		if ( ! in_array( $disclosure, array( 'signed_in_redacted', 'withheld_scoped_session' ), true ) || ! self::is_list( $values ) || count( $values ) > self::MAX_TRAVELERS ) {
			return self::error( 'travelers_invalid', 'Traveler readiness must be a bounded, non-empty identity-free list.' );
		}
		if ( 'scoped_session' === $mode ) {
			return 'withheld_scoped_session' === $disclosure && array() === $values ? array() : self::error( 'traveler_disclosure_invalid', 'A forwarded scoped view must withhold traveler-specific readiness, subject, and requirement facts.' );
		}
		if ( 'signed_in_redacted' !== $disclosure || ! $values ) {
			return self::error( 'traveler_disclosure_invalid', 'Signed-in traveler readiness must use the redacted non-empty boundary.' );
		}
		$map = array();
		foreach ( $values as $item ) {
			$keys = array( 'traveler_slot', 'subject_kind', 'readiness', 'pending_requirement_codes', 'next_safe_action', 'deadline', 'verified_at' );
			if ( ! self::exact_object( $item, $keys ) || ! self::key( $item['traveler_slot'], 'traveler' ) || isset( $map[ $item['traveler_slot'] ] ) || ! in_array( $item['subject_kind'], self::SUBJECT_KINDS, true ) || ! in_array( $item['readiness'], self::TRAVELER_READINESS, true ) || ! self::code_list( $item['pending_requirement_codes'], 64 ) || ! self::nullable_utc( $item['deadline'] ) || ! self::utc_at_or_before( $item['verified_at'], $now ) ) {
				return self::error( 'traveler_invalid', 'Traveler readiness must remain identity-free, bounded, and truthfully timed.' );
			}
			$map[ $item['traveler_slot'] ] = $item;
		}
		$slots = array_keys( $map );
		foreach ( $values as $item ) {
			$action = self::validate_action( $item['next_safe_action'], $service_keys, $slots );
			if ( is_wp_error( $action ) ) {
				return $action;
			}
		}
		return $map;
	}

	private static function validate_loyalty( $value, $service_keys, $traveler_slots, $now, $mode ) {
		$keys = array( 'disclosure', 'status', 'filter_readiness', 'affected_service_keys', 'next_safe_action', 'verified_at' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['disclosure'], array( 'signed_in_redacted', 'withheld_scoped_session' ), true ) || ! in_array( $value['status'], self::LOYALTY_STATES, true ) || ! in_array( $value['filter_readiness'], self::LOYALTY_FILTER_READINESS, true ) || ! self::key_list( $value['affected_service_keys'], $service_keys, 'service' ) ) {
			return self::error( 'loyalty_invalid', 'Customer loyalty-filter readiness is malformed or not bound to this trip.' );
		}
		if ( 'scoped_session' === $mode ) {
			if ( 'withheld_scoped_session' !== $value['disclosure'] || 'withheld' !== $value['status'] || 'withheld' !== $value['filter_readiness'] || array() !== $value['affected_service_keys'] || null !== $value['next_safe_action'] || null !== $value['verified_at'] ) {
				return self::error( 'loyalty_disclosure_invalid', 'A forwarded scoped view must withhold loyalty presence, connection, readiness, and affected-service state.' );
			}
			return true;
		}
		if ( 'signed_in_redacted' !== $value['disclosure'] || ! self::utc_at_or_before( $value['verified_at'], $now ) || 'withheld' === $value['status'] || 'withheld' === $value['filter_readiness'] ) {
			return self::error( 'loyalty_disclosure_invalid', 'Signed-in loyalty status must use the redacted disclosure boundary.' );
		}
		$expected = array(
			'not_requested' => 'not_requested', 'planning' => 'checking', 'member_connection_needed' => 'connection_required',
			'benefits_checking' => 'checking', 'options_ready' => 'ready', 'stale' => 'refresh_required', 'unavailable' => 'unavailable',
		);
		if ( $expected[ $value['status'] ] !== $value['filter_readiness'] ) {
			return self::error( 'loyalty_readiness_invalid', 'Loyalty filter readiness must be derived exactly from the validated loyalty state.' );
		}
		return self::validate_action( $value['next_safe_action'], $service_keys, $traveler_slots );
	}

	private static function validate_offline_pack( $value, $service_keys, $traveler_slots, $now ) {
		$keys = array( 'status', 'itinerary', 'service_contacts', 'emergency_contacts', 'next_safe_action', 'verified_at' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['status'], self::OFFLINE_PACK_STATES, true ) || ! in_array( $value['itinerary'], self::OFFLINE_COMPONENT_STATES, true ) || ! in_array( $value['service_contacts'], self::OFFLINE_COMPONENT_STATES, true ) || ! in_array( $value['emergency_contacts'], self::OFFLINE_COMPONENT_STATES, true ) || ! self::utc_at_or_before( $value['verified_at'], $now ) ) {
			return self::error( 'offline_pack_invalid', 'Customer offline-pack readiness is malformed.' );
		}
		return self::validate_action( $value['next_safe_action'], $service_keys, $traveler_slots );
	}

	private static function validate_freshness( $value, $now, $mode ) {
		$keys = array( 'status', 'source_verified_at', 'projected_at', 'basis', 'resolution_inferred' );
		$expected_basis = 'scoped_session' === $mode ? 'scoped_visible_only' : 'signed_in_redacted';
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['status'], self::TRUTH_STATES, true ) || ! self::utc_at_or_before( $value['source_verified_at'], $now ) || ! self::utc_at_or_before( $value['projected_at'], $now ) || strcmp( $value['projected_at'], $value['source_verified_at'] ) < 0 || $expected_basis !== $value['basis'] || false !== $value['resolution_inferred'] ) {
			return self::error( 'freshness_invalid', 'Freshness must retain the source verification clock and never infer resolution.' );
		}
		return true;
	}

	private static function validate_authority( $value ) {
		$keys = array( 'authorization_effect', 'view_projection_only', 'change_started', 'cancellation_started', 'payment_started', 'refund_started', 'supplier_action_started', 'processor_action_started', 'resolution_inferred' );
		if ( ! self::exact_object( $value, $keys ) || 'none' !== $value['authorization_effect'] || true !== $value['view_projection_only'] || false !== $value['change_started'] || false !== $value['cancellation_started'] || false !== $value['payment_started'] || false !== $value['refund_started'] || false !== $value['supplier_action_started'] || false !== $value['processor_action_started'] || false !== $value['resolution_inferred'] ) {
			return self::error( 'authority_invalid', 'The customer view may report status but may never claim change, cancellation, payment, refund, supplier, or processor execution.' );
		}
		return true;
	}

	private static function validate_boundary( $value ) {
		$keys = array( 'customer_serialization_allowed', 'validated_private_read_model_only', 'owner_scope_exposed', 'internal_refs_exposed', 'raw_identity_data_exposed', 'raw_payment_data_exposed', 'raw_medical_data_exposed', 'raw_provider_data_exposed', 'bearer_secret_exposed', 'internal_operator_routing_exposed', 'settlement_data_exposed', 'commission_data_exposed' );
		if ( ! self::exact_object( $value, $keys ) || true !== $value['customer_serialization_allowed'] || true !== $value['validated_private_read_model_only'] ) {
			return self::error( 'boundary_invalid', 'The customer serialization boundary is invalid.' );
		}
		foreach ( array_slice( $keys, 2 ) as $key ) {
			if ( false !== $value[ $key ] ) {
				return self::error( 'boundary_exposure_invalid', 'Private, raw, operator, settlement, commission, or bearer material cannot be exposed.' );
			}
		}
		return true;
	}

	/** Prevent aliases, codes, and clocks from reintroducing withheld scoped material. */
	private static function scoped_material_is_withheld( $value, $key = '' ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $child_key => $child ) {
				if ( ! self::scoped_material_is_withheld( $child, $child_key ) ) {
					return false;
				}
			}
			return true;
		}
		if ( is_string( $value ) && ( 1 === preg_match( '/^(?:traveler|purchase)-[a-f0-9]{32}$/', $value ) || in_array( $value, array( 'payment.authorize', 'refund.request', 'payment.review', 'commerce', 'redeem.points', 'loyalty.review', 'loyalty', 'traveler.requirement', 'traveler_readiness.review', 'traveler', 'registration.review', 'registration' ), true ) ) ) {
			return false;
		}
		if ( in_array( $key, array( 'purchase_key', 'traveler_slot' ), true ) && null !== $value ) {
			return false;
		}
		return true;
	}

	private static function contains_sensitive_material( $value, $key = '' ) {
		$key = strtolower( (string) $key );
		if ( '' !== $key && false !== $value && null !== $value && '' !== $value && preg_match( '/(?:owner[_-]?scope|cockpit[_-]?ref|trip[_-]?ref|projection[_-]?digest|previous[_-]?projection|internal[_-]?ref|password|secret|api[_-]?key|access[_-]?token|bearer|card[_-]?(?:number|pan)|cvv|cvc|passport[_-]?(?:number|value)|identity[_-]?(?:number|value)|email|phone|mobile|address|date[_-]?of[_-]?birth|diagnosis|medical[_-]?(?:note|history)|raw[_-]?(?:identity|payment|medical|provider)|provider[_-]?(?:id|payload|reference)|supplier[_-]?(?:id|payload|reference)|operator[_-]?(?:route|routing|assignment)|amount[_-]?minor|settlement|commission)/', $key ) ) {
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
		return 1 === preg_match( '/-----BEGIN [A-Z ]*PRIVATE KEY-----|\bBearer\s+[A-Za-z0-9._~+\/=\-]{8,}|\bsk-[A-Za-z0-9_-]{12,}|[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}|\btv_(?:cockpit|trip|service|traveler|order|change|approval|question|case|receipt|timeline_event)_[A-Za-z0-9_-]{16,96}\b|\b[a-f0-9]{64}\b|(?:\+|00)\d[\d .()\-]{7,17}\d|\b0(?:5\d|[23489])[- ]?\d{7}\b|\b(?:\d[ -]*?){13,19}\b/i', $value );
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function is_list( $value ) {
		return is_array( $value ) && array_values( $value ) === $value;
	}

	private static function key( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^' . preg_quote( $kind, '/' ) . '-[a-f0-9]{32}$/', $value );
	}

	private static function key_list( $values, $known, $kind, $required = false ) {
		if ( ! self::is_list( $values ) || ( $required && ! $values ) || count( $values ) !== count( array_unique( $values ) ) || array_diff( $values, $known ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! self::key( $value, $kind ) ) {
				return false;
			}
		}
		return true;
	}

	private static function code( $value ) {
		return is_string( $value ) && strlen( $value ) <= 96 && 1 === preg_match( '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $value );
	}

	private static function scope_list( $values ) {
		return self::is_list( $values ) && ! empty( $values ) && count( $values ) <= count( self::VIEWING_SCOPES ) && count( $values ) === count( array_unique( $values ) ) && ! array_diff( $values, self::VIEWING_SCOPES ) && ! array_diff( $values, Tra_Vel_VIP_Taxonomy::LOW_RISK_CAPABILITY_SCOPES );
	}

	private static function ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function code_list( $values, $max ) {
		if ( ! self::is_list( $values ) || count( $values ) > $max || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! self::is_customer_code( $value ) ) {
				return false;
			}
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

	private static function bounded_count( $value, $max ) {
		return is_int( $value ) && $value >= 0 && $value <= $max;
	}

	private static function nullable_utc( $value ) {
		return null === $value || false !== self::utc_timestamp( $value );
	}

	private static function utc_at_or_before( $value, $now ) {
		$time = self::utc_timestamp( $value );
		return false !== $time && is_int( $now ) && $now > 0 && $time <= $now;
	}

	private static function utc_after( $value, $now ) {
		$time = self::utc_timestamp( $value );
		return false !== $time && is_int( $now ) && $now > 0 && $time > $now;
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

	private static function error( $suffix, $message, $status = 400 ) {
		return new WP_Error( 'tra_vel_customer_trip_cockpit_customer_view_' . $suffix, $message, array( 'status' => $status ) );
	}
}
