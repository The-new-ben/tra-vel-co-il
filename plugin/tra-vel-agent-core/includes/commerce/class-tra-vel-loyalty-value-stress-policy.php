<?php
/**
 * Closed, server-only policy for adversarial loyalty and stored-value ledgers.
 *
 * These records preserve private simulation truth. They never merge a provider
 * account, credit or debit points, consume a voucher, contact a provider, or
 * authorize a payment, refund, redemption, or supplier action.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Loyalty_Value_Stress_Policy {
	const CONTRACT_VERSION = '1.1.0';
	const MAX_INTEGER_VALUE = 1000000000000;

	const ACCRUAL_STATES = array( 'expected', 'pending', 'disputed', 'credited', 'expired', 'rejected' );
	const CURRENT_ACCRUAL_STATES = array( 'pending', 'disputed', 'credited', 'expired', 'rejected' );
	const COMPONENT_TYPES = array( 'base_fare', 'tax', 'carrier_fee', 'service_fee', 'ancillary' );
	const VOUCHER_STATES = array( 'planned', 'blocked_expired', 'blocked_beneficiary', 'blocked_restriction' );

	/**
	 * Validate a duplicate-member merge proof with exact value conservation.
	 *
	 * @return array|WP_Error
	 */
	public static function validate_member_merge( $record, $now = null ) {
		$keys = array(
			'contract_version', 'environment', 'merge_ref', 'record_digest', 'idempotency_digest',
			'account_scope_digest', 'program_ref', 'unit_code', 'source_member_ref',
			'target_member_ref', 'source_snapshot_digest', 'target_snapshot_digest',
			'pre_merge', 'resolution', 'audit_lineage', 'created_at', 'boundary',
		);
		if ( self::contains_sensitive_material( $record ) ) {
			return self::error( 'sensitive_material_rejected', 'Loyalty records accept opaque references and digests only.' );
		}
		if ( ! self::exact_object( $record, $keys ) || self::CONTRACT_VERSION !== $record['contract_version'] || 'simulation' !== $record['environment'] ) {
			return self::error( 'member_merge_shape_invalid', 'The member merge is not the closed simulation contract.' );
		}
		$clock = self::clock( $now );
		$created = self::utc_timestamp( $record['created_at'] );
		if ( null === $clock || null === $created || $created > $clock ) {
			return self::error( 'member_merge_chronology_invalid', 'The merge requires a valid UTC clock and creation time.' );
		}
		if (
			! self::opaque_ref( $record['merge_ref'], 'loyalty_merge' ) ||
			! self::digest( $record['record_digest'] ) ||
			! self::digest( $record['idempotency_digest'] ) ||
			! self::digest( $record['account_scope_digest'] ) ||
			! self::opaque_ref( $record['program_ref'], 'program' ) ||
			! self::identifier( $record['unit_code'] ) ||
			! self::opaque_ref( $record['source_member_ref'], 'member' ) ||
			! self::opaque_ref( $record['target_member_ref'], 'member' ) ||
			$record['source_member_ref'] === $record['target_member_ref'] ||
			! self::digest( $record['source_snapshot_digest'] ) ||
			! self::digest( $record['target_snapshot_digest'] )
		) {
			return self::error( 'member_merge_identity_invalid', 'The merge must bind two distinct opaque member snapshots.' );
		}

		$pre_keys = array( 'source', 'target' );
		if ( ! self::exact_object( $record['pre_merge'], $pre_keys ) ) {
			return self::error( 'member_merge_pre_shape_invalid', 'Pre-merge ledgers must remain separate.' );
		}
		$source = self::member_ledger( $record['pre_merge']['source'], $record['source_member_ref'] );
		$target = self::member_ledger( $record['pre_merge']['target'], $record['target_member_ref'] );
		if ( is_wp_error( $source ) || is_wp_error( $target ) ) {
			return self::error( 'member_merge_pre_ledger_invalid', 'Both pre-merge member ledgers must be exact and duplicate-free.' );
		}
		if ( array_intersect( $source['lot_refs'], $target['lot_refs'] ) ) {
			return self::error( 'member_merge_lot_collision', 'Source and target ledgers cannot share a value-lot reference.' );
		}

		$resolution_keys = array(
			'survivor_member_ref', 'retired_member_ref', 'transfer_lots', 'preserved_target_lot_refs',
			'post_merge_totals', 'double_credit_integer', 'duplicate_identity_evidence_digest',
		);
		$resolution = $record['resolution'];
		if (
			! self::exact_object( $resolution, $resolution_keys ) ||
			$record['target_member_ref'] !== $resolution['survivor_member_ref'] ||
			$record['source_member_ref'] !== $resolution['retired_member_ref'] ||
			0 !== $resolution['double_credit_integer'] ||
			! self::digest( $resolution['duplicate_identity_evidence_digest'] ) ||
			! self::same_ordered_set( $resolution['preserved_target_lot_refs'], $target['lot_refs'] )
		) {
			return self::error( 'member_merge_resolution_invalid', 'The target must survive, the source must retire, and double credit must stay zero.' );
		}
		$transfer = self::transfer_lots( $resolution['transfer_lots'], $record['source_member_ref'], $source );
		if ( is_wp_error( $transfer ) ) {
			return $transfer;
		}
		$post = self::state_totals( $resolution['post_merge_totals'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$states = array( 'available_integer', 'pending_integer', 'disputed_integer', 'expired_integer' );
		foreach ( $states as $state ) {
			if ( $transfer['totals'][ $state ] !== $source[ $state ] ) {
				return self::error( 'member_merge_transfer_not_conserved', 'Every source lot and state must transfer exactly once.' );
			}
			if ( $post[ $state ] !== $source[ $state ] + $target[ $state ] ) {
				return self::error( 'member_merge_total_not_conserved', 'Post-merge state totals must equal the two pre-merge ledgers without duplication.' );
			}
		}

		$lineage = self::audit_lineage( $record['audit_lineage'], 'loyalty_operation' );
		if ( is_wp_error( $lineage ) ) {
			return $lineage;
		}
		$expected_events = array( $record['source_snapshot_digest'], $record['target_snapshot_digest'] );
		sort( $expected_events, SORT_STRING );
		if (
			! self::same_ordered_set( $lineage['source_event_digests'], $expected_events ) ||
			! hash_equals( self::member_merge_basis_digest( $record ), $lineage['lineage_digest'] )
		) {
			return self::error( 'member_merge_lineage_invalid', 'The immutable lineage must bind both source snapshots and the exact merge basis.' );
		}
		if ( ! self::boundary( $record['boundary'] ) ) {
			return self::error( 'boundary_invalid', 'The private zero-authority boundary is invalid.' );
		}
		if ( ! hash_equals( self::record_digest( $record ), $record['record_digest'] ) ) {
			return self::error( 'record_digest_mismatch', 'The member merge changed after it was sealed.', 409 );
		}
		return $record;
	}

	/**
	 * Validate a posted-bill accrual exception without inferring points credit.
	 *
	 * @return array|WP_Error
	 */
	public static function validate_accrual_case( $record, $now = null ) {
		$keys = array(
			'contract_version', 'environment', 'accrual_case_ref', 'record_digest', 'idempotency_digest',
			'account_scope_digest', 'program_ref', 'member_ref', 'purchase_ref', 'order_item_ref',
			'unit_code', 'bill', 'accrual', 'timeline', 'resolution', 'observed_at', 'boundary',
		);
		if ( self::contains_sensitive_material( $record ) ) {
			return self::error( 'sensitive_material_rejected', 'Accrual cases accept opaque references and digests only.' );
		}
		if ( ! self::exact_object( $record, $keys ) || self::CONTRACT_VERSION !== $record['contract_version'] || 'simulation' !== $record['environment'] ) {
			return self::error( 'accrual_shape_invalid', 'The accrual case is not the closed simulation contract.' );
		}
		$clock = self::clock( $now );
		$observed = self::utc_timestamp( $record['observed_at'] );
		if ( null === $clock || null === $observed || $observed > $clock ) {
			return self::error( 'accrual_chronology_invalid', 'The accrual case requires a valid observation clock.' );
		}
		if (
			! self::opaque_ref( $record['accrual_case_ref'], 'loyalty_accrual' ) ||
			! self::digest( $record['record_digest'] ) ||
			! self::digest( $record['idempotency_digest'] ) ||
			! self::digest( $record['account_scope_digest'] ) ||
			! self::opaque_ref( $record['program_ref'], 'program' ) ||
			! self::opaque_ref( $record['member_ref'], 'member' ) ||
			! self::opaque_ref( $record['purchase_ref'], 'purchase' ) ||
			! self::opaque_ref( $record['order_item_ref'], 'tv_order_item' ) ||
			! self::identifier( $record['unit_code'] )
		) {
			return self::error( 'accrual_identity_invalid', 'The accrual case identity is invalid.' );
		}

		$bill_keys = array( 'state', 'currency', 'amount_minor', 'posted_at', 'evidence_digest' );
		$bill = $record['bill'];
		$posted = is_array( $bill ) && isset( $bill['posted_at'] ) ? self::utc_timestamp( $bill['posted_at'] ) : null;
		if (
			! self::exact_object( $bill, $bill_keys ) ||
			'posted' !== $bill['state'] ||
			! self::currency( $bill['currency'] ) ||
			! self::positive_integer( $bill['amount_minor'] ) ||
			null === $posted || $posted > $observed ||
			! self::digest( $bill['evidence_digest'] )
		) {
			return self::error( 'accrual_bill_invalid', 'A dated posted-bill proof is required and remains independent of points credit.' );
		}

		$accrual_keys = array(
			'state', 'expected_integer', 'credited_integer', 'pending_integer', 'disputed_integer',
			'expired_integer', 'rejected_integer', 'unit_code', 'eligibility_basis_digest', 'expiry_at',
		);
		$accrual = $record['accrual'];
		if (
			! self::exact_object( $accrual, $accrual_keys ) ||
			! in_array( $accrual['state'], self::CURRENT_ACCRUAL_STATES, true ) ||
			$record['unit_code'] !== $accrual['unit_code'] ||
			! self::digest( $accrual['eligibility_basis_digest'] )
		) {
			return self::error( 'accrual_state_invalid', 'The accrual state and evidence are invalid.' );
		}
		foreach ( array( 'expected_integer', 'credited_integer', 'pending_integer', 'disputed_integer', 'expired_integer', 'rejected_integer' ) as $amount_key ) {
			if ( ! self::nonnegative_integer( $accrual[ $amount_key ] ) ) {
				return self::error( 'accrual_amount_invalid', 'Accrual values must be bounded non-negative integers.' );
			}
		}
		if ( 0 === $accrual['expected_integer'] || $accrual['expected_integer'] !== $accrual['credited_integer'] + $accrual['pending_integer'] + $accrual['disputed_integer'] + $accrual['expired_integer'] + $accrual['rejected_integer'] ) {
			return self::error( 'accrual_conservation_invalid', 'Expected value must remain fully partitioned across explicit lifecycle states.' );
		}
		$state_amounts = array(
			'pending' => $accrual['pending_integer'],
			'disputed' => $accrual['disputed_integer'],
			'credited' => $accrual['credited_integer'],
			'expired' => $accrual['expired_integer'],
			'rejected' => $accrual['rejected_integer'],
		);
		foreach ( $state_amounts as $state => $amount ) {
			$expected_amount = $state === $accrual['state'] ? $accrual['expected_integer'] : 0;
			if ( $amount !== $expected_amount ) {
				return self::error( 'accrual_state_amount_mismatch', 'The current state must own the full expected value and every contradictory partition must be zero.' );
			}
		}
		$expiry = null === $accrual['expiry_at'] ? null : self::utc_timestamp( $accrual['expiry_at'] );
		if ( null !== $accrual['expiry_at'] && null === $expiry ) {
			return self::error( 'accrual_expiry_invalid', 'Accrual expiry must be null or strict UTC.' );
		}
		if ( 'expired' === $accrual['state'] && ( null === $expiry || $expiry > $observed ) ) {
			return self::error( 'accrual_not_yet_expired', 'Expired value requires an expiry at or before the observation.' );
		}
		if ( in_array( $accrual['state'], array( 'pending', 'disputed' ), true ) && null !== $expiry && $expiry <= $observed ) {
			return self::error( 'accrual_open_after_expiry', 'Pending or disputed value cannot remain open after its declared expiry.' );
		}

		$timeline = self::accrual_timeline( $record['timeline'], $posted, $observed );
		if ( is_wp_error( $timeline ) || $timeline[ count( $timeline ) - 1 ]['state'] !== $accrual['state'] ) {
			return self::error( 'accrual_timeline_invalid', 'The immutable event timeline must end at the exact declared state.' );
		}
		$resolution_keys = array(
			'case_ref', 'next_action_code', 'deadline_at', 'provider_claim_reference_digest',
			'bill_posted_implies_credit', 'automatic_credit_allowed',
		);
		$resolution = $record['resolution'];
		$deadline = is_array( $resolution ) && array_key_exists( 'deadline_at', $resolution ) && null !== $resolution['deadline_at'] ? self::utc_timestamp( $resolution['deadline_at'] ) : null;
		if (
			! self::exact_object( $resolution, $resolution_keys ) ||
			! self::opaque_ref( $resolution['case_ref'], 'service_case' ) ||
			! self::identifier( $resolution['next_action_code'] ) ||
			( null !== $resolution['deadline_at'] && null === $deadline ) ||
			( null !== $resolution['provider_claim_reference_digest'] && ! self::digest( $resolution['provider_claim_reference_digest'] ) ) ||
			false !== $resolution['bill_posted_implies_credit'] ||
			false !== $resolution['automatic_credit_allowed']
		) {
			return self::error( 'accrual_resolution_invalid', 'Resolution must remain an explicit, non-automatic service plan.' );
		}
		if ( in_array( $accrual['state'], array( 'pending', 'disputed' ), true ) && ( null === $deadline || $deadline <= $observed ) ) {
			return self::error( 'accrual_deadline_required', 'Open accrual exceptions require a future service deadline.' );
		}
		if ( 'disputed' === $accrual['state'] && null === $resolution['provider_claim_reference_digest'] ) {
			return self::error( 'accrual_dispute_claim_required', 'A disputed accrual requires an opaque provider-claim proof.' );
		}
		if ( ! self::boundary( $record['boundary'] ) ) {
			return self::error( 'boundary_invalid', 'The private zero-authority boundary is invalid.' );
		}
		if ( ! hash_equals( self::record_digest( $record ), $record['record_digest'] ) ) {
			return self::error( 'record_digest_mismatch', 'The accrual case changed after it was sealed.', 409 );
		}
		return $record;
	}

	/**
	 * Validate an explicitly componentized cash-plus-points plan.
	 *
	 * @return array|WP_Error
	 */
	public static function validate_cash_points_redemption( $record, $now = null ) {
		$keys = array(
			'contract_version', 'environment', 'redemption_ref', 'record_digest', 'idempotency_digest',
			'account_scope_digest', 'trip_ref', 'program_ref', 'unit_code', 'currency',
			'traveler_refs', 'segment_refs', 'components', 'traveler_totals', 'cancellation_scope',
			'totals', 'created_at', 'boundary',
		);
		if ( self::contains_sensitive_material( $record ) ) {
			return self::error( 'sensitive_material_rejected', 'Redemption plans accept opaque references and digests only.' );
		}
		if ( ! self::exact_object( $record, $keys ) || self::CONTRACT_VERSION !== $record['contract_version'] || 'simulation' !== $record['environment'] ) {
			return self::error( 'redemption_shape_invalid', 'The redemption is not the closed simulation contract.' );
		}
		$clock = self::clock( $now );
		$created = self::utc_timestamp( $record['created_at'] );
		if ( null === $clock || null === $created || $created > $clock ) {
			return self::error( 'redemption_chronology_invalid', 'The redemption requires a valid UTC creation clock.' );
		}
		if (
			! self::opaque_ref( $record['redemption_ref'], 'loyalty_redemption' ) ||
			! self::digest( $record['record_digest'] ) ||
			! self::digest( $record['idempotency_digest'] ) ||
			! self::digest( $record['account_scope_digest'] ) ||
			! self::opaque_ref( $record['trip_ref'], 'trip' ) ||
			! self::opaque_ref( $record['program_ref'], 'program' ) ||
			! self::identifier( $record['unit_code'] ) ||
			! self::currency( $record['currency'] )
		) {
			return self::error( 'redemption_identity_invalid', 'The redemption identity is invalid.' );
		}
		$travelers = self::reference_list( $record['traveler_refs'], 'traveler', 1, 10 );
		$segments = self::reference_list( $record['segment_refs'], 'segment', 1, 30 );
		if ( is_wp_error( $travelers ) || is_wp_error( $segments ) ) {
			return self::error( 'redemption_scope_invalid', 'One to ten unique travelers and explicit segments are required.' );
		}
		$components = self::redemption_components( $record['components'], $travelers, $segments );
		if ( is_wp_error( $components ) ) {
			return $components;
		}
		$types = array();
		$used_segments = array();
		foreach ( $components['items'] as $component ) {
			$types[ $component['component_type'] ] = true;
			$used_segments[ $component['segment_ref'] ] = true;
		}
		$used_segment_refs = array_keys( $used_segments );
		sort( $used_segment_refs, SORT_STRING );
		if ( ! self::same_ordered_set( $used_segment_refs, $segments ) ) {
			return self::error( 'redemption_segment_without_components', 'Every listed segment must own at least one explicit value component.' );
		}
		if ( ! isset( $types['base_fare'], $types['tax'] ) || ! array_intersect( array( 'carrier_fee', 'service_fee', 'ancillary' ), array_keys( $types ) ) ) {
			return self::error( 'redemption_component_breadth_invalid', 'Fare, tax, and fee value must remain separate components.' );
		}
		$traveler_totals = self::traveler_totals( $record['traveler_totals'], $travelers, $components['items'] );
		if ( is_wp_error( $traveler_totals ) ) {
			return $traveler_totals;
		}
		$cancellation = self::cancellation_scope( $record['cancellation_scope'], $travelers, $segments, $components['items'] );
		if ( is_wp_error( $cancellation ) ) {
			return $cancellation;
		}
		$totals_keys = array( 'cash_minor', 'points_integer', 'refund_cash_minor', 'points_reversal_integer' );
		$totals = $record['totals'];
		if ( ! self::exact_object( $totals, $totals_keys ) ) {
			return self::error( 'redemption_totals_shape_invalid', 'Redemption totals must remain a closed component sum.' );
		}
		foreach ( $totals_keys as $total_key ) {
			if ( ! self::nonnegative_integer( $totals[ $total_key ] ) ) {
				return self::error( 'redemption_totals_invalid', 'Cash minor units and points must be bounded integers.' );
			}
		}
		if (
			$totals['cash_minor'] !== $components['cash_minor'] ||
			$totals['points_integer'] !== $components['points_integer'] ||
			$totals['refund_cash_minor'] !== $cancellation['refund_cash_minor'] ||
			$totals['points_reversal_integer'] !== $cancellation['points_reversal_integer']
		) {
			return self::error( 'redemption_totals_mismatch', 'Root totals must equal exact component and cancellation sums.' );
		}
		if ( ! self::boundary( $record['boundary'] ) ) {
			return self::error( 'boundary_invalid', 'The private zero-authority boundary is invalid.' );
		}
		if ( ! hash_equals( self::record_digest( $record ), $record['record_digest'] ) ) {
			return self::error( 'record_digest_mismatch', 'The redemption plan changed after it was sealed.', 409 );
		}
		return $record;
	}

	/**
	 * Validate a voucher ledger with owner, beneficiary, FX, expiry, and value truth.
	 *
	 * @return array|WP_Error
	 */
	public static function validate_voucher_ledger( $record, $now = null ) {
		$keys = array(
			'contract_version', 'environment', 'voucher_ref', 'record_digest', 'idempotency_digest',
			'account_scope_digest', 'program_ref', 'issuer_ref', 'owner_reference_digest',
			'beneficiary_reference_digest', 'presented_beneficiary_reference_digest', 'currency',
			'minor_unit_exponent', 'fx_basis', 'value', 'expiry', 'restrictions', 'consumption',
			'audit_lineage', 'created_at', 'boundary',
		);
		if ( self::contains_sensitive_material( $record ) ) {
			return self::error( 'sensitive_material_rejected', 'Voucher ledgers accept opaque references and digests only.' );
		}
		if ( ! self::exact_object( $record, $keys ) || self::CONTRACT_VERSION !== $record['contract_version'] || 'simulation' !== $record['environment'] ) {
			return self::error( 'voucher_shape_invalid', 'The voucher ledger is not the closed simulation contract.' );
		}
		$clock = self::clock( $now );
		$created = self::utc_timestamp( $record['created_at'] );
		if ( null === $clock || null === $created || $created > $clock ) {
			return self::error( 'voucher_chronology_invalid', 'The voucher ledger requires a valid UTC creation clock.' );
		}
		if (
			! self::opaque_ref( $record['voucher_ref'], 'stored_value_voucher' ) ||
			! self::digest( $record['record_digest'] ) ||
			! self::digest( $record['idempotency_digest'] ) ||
			! self::digest( $record['account_scope_digest'] ) ||
			! self::opaque_ref( $record['program_ref'], 'program' ) ||
			! self::opaque_ref( $record['issuer_ref'], 'issuer' ) ||
			! self::digest( $record['owner_reference_digest'] ) ||
			! self::digest( $record['beneficiary_reference_digest'] ) ||
			! self::digest( $record['presented_beneficiary_reference_digest'] ) ||
			! self::currency( $record['currency'] ) ||
			! is_int( $record['minor_unit_exponent'] ) || $record['minor_unit_exponent'] < 0 || $record['minor_unit_exponent'] > 4
		) {
			return self::error( 'voucher_identity_invalid', 'Voucher identity, parties, or currency are invalid.' );
		}

		$fx = self::fx_basis( $record['fx_basis'], $record['currency'], $record['minor_unit_exponent'] );
		$value = self::voucher_value( $record['value'] );
		$expiry = self::voucher_expiry( $record['expiry'], $created );
		$restrictions = self::voucher_restrictions( $record['restrictions'], $record['owner_reference_digest'], $record['beneficiary_reference_digest'] );
		if ( is_wp_error( $fx ) || is_wp_error( $value ) || is_wp_error( $expiry ) || is_wp_error( $restrictions ) ) {
			return self::error( 'voucher_policy_invalid', 'Voucher FX, value, expiry, or restrictions are invalid.' );
		}
		if ( 'non_transferable' === $restrictions['transferability'] && ! hash_equals( $record['owner_reference_digest'], $record['presented_beneficiary_reference_digest'] ) ) {
			return self::error( 'voucher_nontransferable_presented_party_invalid', 'A non-transferable voucher must always be presented by its owner.' );
		}
		$consumption = self::voucher_consumption(
			$record['consumption'],
			$record['presented_beneficiary_reference_digest'],
			$record['beneficiary_reference_digest'],
			$record['owner_reference_digest'],
			$fx,
			$value,
			$expiry,
			$restrictions,
			$created
		);
		if ( is_wp_error( $consumption ) ) {
			return $consumption;
		}
		$lineage = self::audit_lineage( $record['audit_lineage'], 'stored_value_operation' );
		if ( is_wp_error( $lineage ) || $lineage['operation_ref'] !== $consumption['operation_ref'] || ! hash_equals( self::voucher_lineage_digest( $record ), $lineage['lineage_digest'] ) ) {
			return self::error( 'voucher_lineage_invalid', 'Voucher lineage must bind the exact owner, beneficiary, value, FX, and consumption evidence.' );
		}
		if ( ! self::boundary( $record['boundary'] ) ) {
			return self::error( 'boundary_invalid', 'The private zero-authority boundary is invalid.' );
		}
		if ( ! hash_equals( self::record_digest( $record ), $record['record_digest'] ) ) {
			return self::error( 'record_digest_mismatch', 'The voucher ledger changed after it was sealed.', 409 );
		}
		return $record;
	}

	/** Seal any supported record after its deterministic references are set. */
	public static function seal_record( $record ) {
		$record['record_digest'] = self::record_digest( $record );
		return $record;
	}

	/** Compute the immutable whole-record digest excluding the digest field itself. */
	public static function record_digest( $record ) {
		if ( ! is_array( $record ) ) {
			return '';
		}
		$basis = $record;
		unset( $basis['record_digest'] );
		return self::canonical_digest( $basis );
	}

	/** Compute the member-merge lineage basis. */
	public static function member_merge_basis_digest( $record ) {
		$keys = array(
			'account_scope_digest', 'program_ref', 'unit_code', 'source_member_ref', 'target_member_ref',
			'source_snapshot_digest', 'target_snapshot_digest', 'pre_merge', 'resolution',
		);
		return self::selected_digest( $record, $keys );
	}

	/** Compute the voucher lineage basis. */
	public static function voucher_lineage_digest( $record ) {
		$keys = array(
			'account_scope_digest', 'program_ref', 'issuer_ref', 'owner_reference_digest',
			'beneficiary_reference_digest', 'currency', 'minor_unit_exponent', 'fx_basis',
			'value', 'expiry', 'restrictions', 'consumption',
		);
		return self::selected_digest( $record, $keys );
	}

	private static function member_ledger( $value, $member_ref ) {
		$keys = array( 'member_ref', 'available_integer', 'pending_integer', 'disputed_integer', 'expired_integer', 'lots' );
		if ( ! self::exact_object( $value, $keys ) || $member_ref !== $value['member_ref'] ) {
			return self::error( 'member_ledger_shape_invalid', 'Member ledger shape or binding is invalid.' );
		}
		foreach ( array( 'available_integer', 'pending_integer', 'disputed_integer', 'expired_integer' ) as $key ) {
			if ( ! self::nonnegative_integer( $value[ $key ] ) ) {
				return self::error( 'member_ledger_amount_invalid', 'Member ledger values must be bounded non-negative integers.' );
			}
		}
		if ( ! is_array( $value['lots'] ) || array_values( $value['lots'] ) !== $value['lots'] || ! $value['lots'] || count( $value['lots'] ) > 100 ) {
			return self::error( 'member_ledger_lots_invalid', 'Each member ledger requires exact per-lot value evidence.' );
		}
		$state_keys = array(
			'available' => 'available_integer',
			'pending' => 'pending_integer',
			'disputed' => 'disputed_integer',
			'expired' => 'expired_integer',
		);
		$totals = array( 'available_integer' => 0, 'pending_integer' => 0, 'disputed_integer' => 0, 'expired_integer' => 0 );
		$by_ref = array();
		$order = array();
		foreach ( $value['lots'] as $lot ) {
			$lot_keys = array( 'lot_ref', 'state', 'amount_integer', 'evidence_digest' );
			if (
				! self::exact_object( $lot, $lot_keys ) ||
				! self::opaque_ref( $lot['lot_ref'], 'value_lot' ) ||
				isset( $by_ref[ $lot['lot_ref'] ] ) ||
				! isset( $state_keys[ $lot['state'] ] ) ||
				! self::positive_integer( $lot['amount_integer'] ) ||
				! self::digest( $lot['evidence_digest'] )
			) {
				return self::error( 'member_ledger_lot_invalid', 'Every ledger lot must carry immutable state, amount, and evidence.' );
			}
			$by_ref[ $lot['lot_ref'] ] = $lot;
			$order[] = $lot['lot_ref'];
			$totals[ $state_keys[ $lot['state'] ] ] += $lot['amount_integer'];
		}
		$canonical_order = $order;
		sort( $canonical_order, SORT_STRING );
		if ( $order !== $canonical_order ) {
			return self::error( 'member_ledger_lots_not_canonical', 'Member lots must use canonical lexical order.' );
		}
		foreach ( array_keys( $totals ) as $state_key ) {
			if ( $totals[ $state_key ] !== $value[ $state_key ] ) {
				return self::error( 'member_ledger_lot_totals_mismatch', 'Per-lot values must exactly reconcile to every member state total.' );
			}
		}
		$value['lot_refs'] = $order;
		$value['lots_by_ref'] = $by_ref;
		return $value;
	}

	private static function transfer_lots( $value, $source_member_ref, $source_ledger ) {
		if ( ! is_array( $value ) || array_values( $value ) !== $value || ! $value || count( $value ) > 100 ) {
			return self::error( 'member_merge_transfer_lots_invalid', 'One to one hundred transfer lots are required.' );
		}
		$refs = array();
		$totals = array( 'available_integer' => 0, 'pending_integer' => 0, 'disputed_integer' => 0, 'expired_integer' => 0 );
		$state_keys = array(
			'available' => 'available_integer',
			'pending' => 'pending_integer',
			'disputed' => 'disputed_integer',
			'expired' => 'expired_integer',
		);
		foreach ( $value as $lot ) {
			$keys = array( 'lot_ref', 'original_member_ref', 'state', 'amount_integer', 'evidence_digest' );
			if (
				! self::exact_object( $lot, $keys ) ||
				! self::opaque_ref( $lot['lot_ref'], 'value_lot' ) ||
				$source_member_ref !== $lot['original_member_ref'] ||
				! isset( $state_keys[ $lot['state'] ] ) ||
				! self::positive_integer( $lot['amount_integer'] ) ||
				! self::digest( $lot['evidence_digest'] ) ||
				isset( $refs[ $lot['lot_ref'] ] )
			) {
				return self::error( 'member_merge_transfer_lot_invalid', 'Each source lot must transfer once with its original state and evidence.' );
			}
			$source_lot = isset( $source_ledger['lots_by_ref'][ $lot['lot_ref'] ] ) ? $source_ledger['lots_by_ref'][ $lot['lot_ref'] ] : null;
			if (
				null === $source_lot ||
				$lot['state'] !== $source_lot['state'] ||
				$lot['amount_integer'] !== $source_lot['amount_integer'] ||
				! hash_equals( $source_lot['evidence_digest'], $lot['evidence_digest'] )
			) {
				return self::error( 'member_merge_transfer_lineage_mismatch', 'Transfer lots must preserve the source lot state, amount, and evidence without reassignment.' );
			}
			$refs[ $lot['lot_ref'] ] = true;
			$totals[ $state_keys[ $lot['state'] ] ] += $lot['amount_integer'];
		}
		$actual_refs = array_keys( $refs );
		sort( $actual_refs, SORT_STRING );
		if ( ! self::same_ordered_set( $actual_refs, $source_ledger['lot_refs'] ) ) {
			return self::error( 'member_merge_transfer_lot_scope_invalid', 'The transfer set must cover every source lot exactly once.' );
		}
		return array( 'totals' => $totals, 'lot_refs' => $actual_refs );
	}

	private static function state_totals( $value ) {
		$keys = array( 'available_integer', 'pending_integer', 'disputed_integer', 'expired_integer' );
		if ( ! self::exact_object( $value, $keys ) ) {
			return self::error( 'state_totals_shape_invalid', 'State totals must remain a closed ledger.' );
		}
		foreach ( $keys as $key ) {
			if ( ! self::nonnegative_integer( $value[ $key ] ) ) {
				return self::error( 'state_totals_amount_invalid', 'State totals must be bounded non-negative integers.' );
			}
		}
		return $value;
	}

	private static function audit_lineage( $value, $operation_prefix ) {
		$keys = array( 'operation_ref', 'sequence', 'previous_operation_digest', 'source_event_digests', 'lineage_digest', 'immutable' );
		if (
			! self::exact_object( $value, $keys ) ||
			! self::opaque_ref( $value['operation_ref'], $operation_prefix ) ||
			! self::positive_integer( $value['sequence'] ) ||
			( null !== $value['previous_operation_digest'] && ! self::digest( $value['previous_operation_digest'] ) ) ||
			! self::digest( $value['lineage_digest'] ) ||
			true !== $value['immutable']
		) {
			return self::error( 'audit_lineage_shape_invalid', 'Audit lineage must be immutable, ordered, and digest-bound.' );
		}
		if ( ( 1 === $value['sequence'] && null !== $value['previous_operation_digest'] ) || ( $value['sequence'] > 1 && null === $value['previous_operation_digest'] ) ) {
			return self::error( 'audit_lineage_ancestry_invalid', 'Initial lineage has no predecessor and successors must bind one.' );
		}
		$events = self::digest_list( $value['source_event_digests'], 1, 20 );
		if ( is_wp_error( $events ) ) {
			return $events;
		}
		$value['source_event_digests'] = $events;
		return $value;
	}

	private static function accrual_timeline( $value, $posted, $observed ) {
		if ( ! is_array( $value ) || array_values( $value ) !== $value || ! $value || count( $value ) > 30 ) {
			return self::error( 'accrual_timeline_shape_invalid', 'One to thirty accrual events are required.' );
		}
		$previous_time = null;
		$previous_state = null;
		$legal_transitions = array(
			'expected' => array( 'pending', 'credited', 'rejected' ),
			'pending' => array( 'credited', 'disputed', 'expired', 'rejected' ),
			'disputed' => array( 'credited', 'expired', 'rejected' ),
			'credited' => array(),
			'expired' => array(),
			'rejected' => array(),
		);
		foreach ( $value as $index => $event ) {
			$keys = array( 'sequence', 'state', 'occurred_at', 'evidence_digest' );
			$occurred = is_array( $event ) && isset( $event['occurred_at'] ) ? self::utc_timestamp( $event['occurred_at'] ) : null;
			if (
				! self::exact_object( $event, $keys ) ||
				$index + 1 !== $event['sequence'] ||
				! in_array( $event['state'], self::ACCRUAL_STATES, true ) ||
				null === $occurred || $occurred < $posted || $occurred > $observed ||
				( null !== $previous_time && $occurred < $previous_time ) ||
				! self::digest( $event['evidence_digest'] )
			) {
				return self::error( 'accrual_timeline_event_invalid', 'Accrual events must be exact, chronological, and contiguous.' );
			}
			if ( null !== $previous_state && ! in_array( $event['state'], $legal_transitions[ $previous_state ], true ) ) {
				return self::error( 'accrual_transition_invalid', 'Accrual timelines must follow the closed forward-only lifecycle graph.' );
			}
			$previous_time = $occurred;
			$previous_state = $event['state'];
		}
		if ( 'expected' !== $value[0]['state'] ) {
			return self::error( 'accrual_timeline_origin_invalid', 'The accrual timeline must begin with an expected event.' );
		}
		return $value;
	}

	private static function redemption_components( $value, $travelers, $segments ) {
		if ( ! is_array( $value ) || array_values( $value ) !== $value || ! $value || count( $value ) > 300 ) {
			return self::error( 'redemption_components_invalid', 'One to three hundred value components are required.' );
		}
		$refs = array();
		$cash = 0;
		$points = 0;
		foreach ( $value as $component ) {
			$keys = array( 'component_ref', 'traveler_ref', 'segment_ref', 'component_type', 'cash_minor', 'points_integer', 'evidence_digest' );
			if (
				! self::exact_object( $component, $keys ) ||
				! self::opaque_ref( $component['component_ref'], 'loyalty_component' ) ||
				isset( $refs[ $component['component_ref'] ] ) ||
				! in_array( $component['traveler_ref'], $travelers, true ) ||
				! in_array( $component['segment_ref'], $segments, true ) ||
				! in_array( $component['component_type'], self::COMPONENT_TYPES, true ) ||
				! self::nonnegative_integer( $component['cash_minor'] ) ||
				! self::nonnegative_integer( $component['points_integer'] ) ||
				( 0 === $component['cash_minor'] && 0 === $component['points_integer'] ) ||
				! self::digest( $component['evidence_digest'] )
			) {
				return self::error( 'redemption_component_invalid', 'Each component must bind one traveler, segment, value type, and evidence.' );
			}
			$refs[ $component['component_ref'] ] = $component;
			$cash += $component['cash_minor'];
			$points += $component['points_integer'];
			if ( $cash > self::MAX_INTEGER_VALUE || $points > self::MAX_INTEGER_VALUE ) {
				return self::error( 'redemption_component_sum_overflow', 'Component totals exceed the bounded ledger.' );
			}
		}
		return array( 'items' => $value, 'by_ref' => $refs, 'cash_minor' => $cash, 'points_integer' => $points );
	}

	private static function traveler_totals( $value, $travelers, $components ) {
		if ( ! is_array( $value ) || array_values( $value ) !== $value || count( $value ) !== count( $travelers ) ) {
			return self::error( 'traveler_totals_shape_invalid', 'Every traveler requires exactly one value-total row.' );
		}
		$by_traveler = array();
		foreach ( $components as $component ) {
			if ( ! isset( $by_traveler[ $component['traveler_ref'] ] ) ) {
				$by_traveler[ $component['traveler_ref'] ] = array( 'cash_minor' => 0, 'points_integer' => 0 );
			}
			$by_traveler[ $component['traveler_ref'] ]['cash_minor'] += $component['cash_minor'];
			$by_traveler[ $component['traveler_ref'] ]['points_integer'] += $component['points_integer'];
		}
		$seen = array();
		foreach ( $value as $row ) {
			$keys = array( 'traveler_ref', 'cash_minor', 'points_integer' );
			if (
				! self::exact_object( $row, $keys ) ||
				! in_array( $row['traveler_ref'], $travelers, true ) ||
				isset( $seen[ $row['traveler_ref'] ] ) ||
				! self::nonnegative_integer( $row['cash_minor'] ) ||
				! self::nonnegative_integer( $row['points_integer'] ) ||
				! isset( $by_traveler[ $row['traveler_ref'] ] ) ||
				$row['cash_minor'] !== $by_traveler[ $row['traveler_ref'] ]['cash_minor'] ||
				$row['points_integer'] !== $by_traveler[ $row['traveler_ref'] ]['points_integer']
			) {
				return self::error( 'traveler_totals_invalid', 'Traveler totals must exactly sum only that traveler\'s components.' );
			}
			$seen[ $row['traveler_ref'] ] = true;
		}
		$seen_refs = array_keys( $seen );
		sort( $seen_refs, SORT_STRING );
		if ( ! self::same_ordered_set( $seen_refs, $travelers ) ) {
			return self::error( 'traveler_totals_scope_invalid', 'Traveler totals must be exhaustive.' );
		}
		return $value;
	}

	private static function cancellation_scope( $value, $travelers, $segments, $components ) {
		$keys = array(
			'affected_traveler_refs', 'preserved_traveler_refs', 'affected_component_refs',
			'preserved_component_refs', 'traveler_segment_partitions', 'refund_components',
			'cross_party_reallocation_allowed', 'silent_netting_allowed',
		);
		if ( ! self::exact_object( $value, $keys ) || false !== $value['cross_party_reallocation_allowed'] || false !== $value['silent_netting_allowed'] ) {
			return self::error( 'cancellation_scope_shape_invalid', 'Cancellation must prohibit cross-party reallocation and silent netting.' );
		}
		$affected_travelers = self::reference_list( $value['affected_traveler_refs'], 'traveler', 1, 10 );
		$preserved_travelers = self::reference_list( $value['preserved_traveler_refs'], 'traveler', 0, 10 );
		$component_refs = array();
		$component_by_ref = array();
		foreach ( $components as $component ) {
			$component_refs[] = $component['component_ref'];
			$component_by_ref[ $component['component_ref'] ] = $component;
		}
		sort( $component_refs, SORT_STRING );
		$affected_components = self::reference_list( $value['affected_component_refs'], 'loyalty_component', 1, count( $components ) );
		$preserved_components = self::reference_list( $value['preserved_component_refs'], 'loyalty_component', 0, count( $components ) );
		if (
			is_wp_error( $affected_travelers ) || is_wp_error( $preserved_travelers ) ||
			is_wp_error( $affected_components ) || is_wp_error( $preserved_components ) ||
			! self::disjoint_exhaustive( $affected_travelers, $preserved_travelers, $travelers ) ||
			! self::disjoint_exhaustive( $affected_components, $preserved_components, $component_refs )
		) {
			return self::error( 'cancellation_partition_invalid', 'Affected and preserved travelers and components must be disjoint and exhaustive.' );
		}
		foreach ( $affected_components as $component_ref ) {
			if ( ! isset( $component_by_ref[ $component_ref ] ) || ! in_array( $component_by_ref[ $component_ref ]['traveler_ref'], $affected_travelers, true ) ) {
				return self::error( 'cancellation_cross_party_component', 'An affected component must belong to an explicitly affected traveler.' );
			}
		}
		$affected_component_owners = array();
		foreach ( $affected_components as $component_ref ) {
			$affected_component_owners[ $component_by_ref[ $component_ref ]['traveler_ref'] ] = true;
		}
		$affected_owner_refs = array_keys( $affected_component_owners );
		sort( $affected_owner_refs, SORT_STRING );
		if ( ! self::same_ordered_set( $affected_owner_refs, $affected_travelers ) ) {
			return self::error( 'cancellation_affected_traveler_without_component', 'Every affected traveler must own at least one affected component.' );
		}
		foreach ( $preserved_travelers as $traveler_ref ) {
			foreach ( $affected_components as $component_ref ) {
				if ( $component_by_ref[ $component_ref ]['traveler_ref'] === $traveler_ref ) {
					return self::error( 'cancellation_preserved_party_touched', 'A preserved traveler cannot own an affected component.' );
				}
			}
		}

		$partition_keys = array();
		$expected_pairs = array();
		foreach ( $components as $component ) {
			$expected_pairs[ $component['traveler_ref'] . '|' . $component['segment_ref'] ] = true;
		}
		$partitions = $value['traveler_segment_partitions'];
		if ( ! is_array( $partitions ) || array_values( $partitions ) !== $partitions || count( $partitions ) !== count( $expected_pairs ) ) {
			return self::error( 'cancellation_segment_partitions_invalid', 'Every traveler and segment pair requires one explicit component partition.' );
		}
		foreach ( $partitions as $partition ) {
			$partition_shape = array( 'traveler_ref', 'segment_ref', 'affected_component_refs', 'preserved_component_refs' );
			if ( ! self::exact_object( $partition, $partition_shape ) || ! in_array( $partition['traveler_ref'], $travelers, true ) || ! in_array( $partition['segment_ref'], $segments, true ) ) {
				return self::error( 'cancellation_segment_partition_invalid', 'Traveler-segment partition identity is invalid.' );
			}
			$pair = $partition['traveler_ref'] . '|' . $partition['segment_ref'];
			if ( isset( $partition_keys[ $pair ] ) || ! isset( $expected_pairs[ $pair ] ) ) {
				return self::error( 'cancellation_segment_partition_duplicate', 'Traveler-segment partitions must be unique and scoped.' );
			}
			$partition_keys[ $pair ] = true;
			$pair_refs = array();
			foreach ( $components as $component ) {
				if ( $component['traveler_ref'] === $partition['traveler_ref'] && $component['segment_ref'] === $partition['segment_ref'] ) {
					$pair_refs[] = $component['component_ref'];
				}
			}
			sort( $pair_refs, SORT_STRING );
			$pair_affected = self::reference_list( $partition['affected_component_refs'], 'loyalty_component', 0, count( $pair_refs ) );
			$pair_preserved = self::reference_list( $partition['preserved_component_refs'], 'loyalty_component', 0, count( $pair_refs ) );
			if (
				is_wp_error( $pair_affected ) || is_wp_error( $pair_preserved ) ||
				! self::disjoint_exhaustive( $pair_affected, $pair_preserved, $pair_refs ) ||
				! self::same_ordered_set( $pair_affected, array_values( array_intersect( $pair_refs, $affected_components ) ) )
			) {
				return self::error( 'cancellation_segment_partition_scope_invalid', 'Each traveler-segment row must reproduce the global affected and preserved component partition.' );
			}
		}
		$actual_partition_pairs = array_keys( $partition_keys );
		$expected_partition_pairs = array_keys( $expected_pairs );
		sort( $actual_partition_pairs, SORT_STRING );
		sort( $expected_partition_pairs, SORT_STRING );
		if ( ! self::same_ordered_set( $actual_partition_pairs, $expected_partition_pairs ) ) {
			return self::error( 'cancellation_segment_partitions_not_exhaustive', 'Traveler-segment partitions must exhaust every component-owning pair exactly once.' );
		}

		$refunds = $value['refund_components'];
		if ( ! is_array( $refunds ) || array_values( $refunds ) !== $refunds || count( $refunds ) !== count( $affected_components ) ) {
			return self::error( 'cancellation_refunds_shape_invalid', 'Each affected component requires one separate refund row.' );
		}
		$seen_refunds = array();
		$refund_cash = 0;
		$points_reversal = 0;
		foreach ( $refunds as $refund ) {
			$refund_keys = array( 'component_ref', 'traveler_ref', 'segment_ref', 'cash_refund_minor', 'points_reversal_integer', 'evidence_digest' );
			if ( ! self::exact_object( $refund, $refund_keys ) || ! isset( $component_by_ref[ $refund['component_ref'] ] ) || isset( $seen_refunds[ $refund['component_ref'] ] ) ) {
				return self::error( 'cancellation_refund_invalid', 'Refund rows must be unique and bind an affected component.' );
			}
			$component = $component_by_ref[ $refund['component_ref'] ];
			if (
				! in_array( $refund['component_ref'], $affected_components, true ) ||
				$refund['traveler_ref'] !== $component['traveler_ref'] ||
				$refund['segment_ref'] !== $component['segment_ref'] ||
				! self::nonnegative_integer( $refund['cash_refund_minor'] ) ||
				! self::nonnegative_integer( $refund['points_reversal_integer'] ) ||
				$refund['cash_refund_minor'] > $component['cash_minor'] ||
				$refund['points_reversal_integer'] > $component['points_integer'] ||
				! self::digest( $refund['evidence_digest'] )
			) {
				return self::error( 'cancellation_refund_scope_invalid', 'Refund value cannot cross traveler, segment, component, or original value boundaries.' );
			}
			$seen_refunds[ $refund['component_ref'] ] = true;
			$refund_cash += $refund['cash_refund_minor'];
			$points_reversal += $refund['points_reversal_integer'];
		}
		$refund_refs = array_keys( $seen_refunds );
		sort( $refund_refs, SORT_STRING );
		if ( ! self::same_ordered_set( $refund_refs, $affected_components ) ) {
			return self::error( 'cancellation_refund_coverage_invalid', 'Every affected component must have exactly one refund row.' );
		}
		return array( 'refund_cash_minor' => $refund_cash, 'points_reversal_integer' => $points_reversal );
	}

	private static function fx_basis( $value, $currency, $root_source_exponent ) {
		$keys = array( 'source_currency', 'settlement_currency', 'source_minor_unit_exponent', 'settlement_minor_unit_exponent', 'rate_numerator', 'rate_denominator', 'rounding_mode', 'observed_at', 'valid_until', 'source_digest' );
		if (
			! self::exact_object( $value, $keys ) ||
			$currency !== $value['source_currency'] ||
			! self::currency( $value['settlement_currency'] ) ||
			! is_int( $value['source_minor_unit_exponent'] ) || $value['source_minor_unit_exponent'] < 0 || $value['source_minor_unit_exponent'] > 4 ||
			! is_int( $value['settlement_minor_unit_exponent'] ) || $value['settlement_minor_unit_exponent'] < 0 || $value['settlement_minor_unit_exponent'] > 4 ||
			$root_source_exponent !== $value['source_minor_unit_exponent'] ||
			! self::positive_integer( $value['rate_numerator'] ) ||
			! self::positive_integer( $value['rate_denominator'] ) ||
			'floor_minor_unit' !== $value['rounding_mode'] ||
			! self::digest( $value['source_digest'] )
		) {
			return self::error( 'voucher_fx_shape_invalid', 'FX basis must use exact integer rational terms and evidence.' );
		}
		$observed = self::utc_timestamp( $value['observed_at'] );
		$valid = self::utc_timestamp( $value['valid_until'] );
		if ( null === $observed || null === $valid || $observed > $valid ) {
			return self::error( 'voucher_fx_chronology_invalid', 'FX evidence requires a valid observation window.' );
		}
		if ( $value['source_currency'] === $value['settlement_currency'] && ( 1 !== $value['rate_numerator'] || 1 !== $value['rate_denominator'] || $value['source_minor_unit_exponent'] !== $value['settlement_minor_unit_exponent'] ) ) {
			return self::error( 'voucher_same_currency_fx_invalid', 'Same-currency consumption must use an exact one-to-one basis.' );
		}
		$value['observed_epoch'] = $observed;
		$value['valid_epoch'] = $valid;
		return $value;
	}

	private static function voucher_value( $value ) {
		$keys = array( 'face_value_minor', 'consumed_before_minor', 'available_before_minor', 'requested_consumption_minor', 'applied_consumption_minor', 'remaining_after_minor' );
		if ( ! self::exact_object( $value, $keys ) ) {
			return self::error( 'voucher_value_shape_invalid', 'Voucher value must remain a closed integer ledger.' );
		}
		foreach ( $keys as $key ) {
			if ( ! self::nonnegative_integer( $value[ $key ] ) ) {
				return self::error( 'voucher_value_amount_invalid', 'Voucher values must be bounded non-negative minor units.' );
			}
		}
		if (
			0 === $value['face_value_minor'] ||
			$value['face_value_minor'] !== $value['consumed_before_minor'] + $value['available_before_minor'] ||
			$value['applied_consumption_minor'] > $value['requested_consumption_minor'] ||
			$value['applied_consumption_minor'] > $value['available_before_minor'] ||
			$value['remaining_after_minor'] !== $value['available_before_minor'] - $value['applied_consumption_minor']
		) {
			return self::error( 'voucher_value_conservation_invalid', 'Face, consumed, available, applied, and remaining values must reconcile exactly.' );
		}
		return $value;
	}

	private static function voucher_expiry( $value, $created ) {
		$keys = array( 'issued_at', 'expires_at', 'evaluated_at', 'state' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['state'], array( 'current', 'expired' ), true ) ) {
			return self::error( 'voucher_expiry_shape_invalid', 'Voucher expiry is invalid.' );
		}
		$issued = self::utc_timestamp( $value['issued_at'] );
		$expires = self::utc_timestamp( $value['expires_at'] );
		$evaluated = self::utc_timestamp( $value['evaluated_at'] );
		if ( null === $issued || null === $expires || null === $evaluated || $issued >= $expires || $created < $issued || $evaluated > $created ) {
			return self::error( 'voucher_expiry_chronology_invalid', 'Voucher issue, expiry, evaluation, and record times are inconsistent.' );
		}
		$expected_state = $evaluated >= $expires ? 'expired' : 'current';
		if ( $expected_state !== $value['state'] ) {
			return self::error( 'voucher_expiry_state_invalid', 'Voucher expiry state must match the exact evaluation instant.' );
		}
		$value['issued_epoch'] = $issued;
		$value['expires_epoch'] = $expires;
		$value['evaluated_epoch'] = $evaluated;
		return $value;
	}

	private static function voucher_restrictions( $value, $owner_digest, $beneficiary_digest ) {
		$keys = array( 'transferability', 'beneficiary_match_required', 'partial_consumption_allowed', 'stacking_allowed', 'permitted_verticals', 'permitted_supplier_refs', 'minimum_purchase_minor', 'evidence_digest' );
		if (
			! self::exact_object( $value, $keys ) ||
			! in_array( $value['transferability'], array( 'non_transferable', 'designated_beneficiary', 'reassignable_before_use' ), true ) ||
			! is_bool( $value['beneficiary_match_required'] ) ||
			! is_bool( $value['partial_consumption_allowed'] ) ||
			! is_bool( $value['stacking_allowed'] ) ||
			! self::nonnegative_integer( $value['minimum_purchase_minor'] ) ||
			! self::digest( $value['evidence_digest'] )
		) {
			return self::error( 'voucher_restrictions_shape_invalid', 'Voucher restrictions must be explicit and evidence-bound.' );
		}
		$verticals = self::identifier_list( $value['permitted_verticals'], 1, 20 );
		$suppliers = self::reference_list( $value['permitted_supplier_refs'], 'supplier', 1, 100 );
		if ( is_wp_error( $verticals ) || is_wp_error( $suppliers ) ) {
			return self::error( 'voucher_restrictions_scope_invalid', 'Voucher verticals and suppliers must be explicit unique scopes.' );
		}
		if ( 'non_transferable' === $value['transferability'] && $owner_digest !== $beneficiary_digest ) {
			return self::error( 'voucher_nontransferable_beneficiary_invalid', 'A non-transferable voucher beneficiary must be its owner.' );
		}
		$value['permitted_verticals'] = $verticals;
		$value['permitted_supplier_refs'] = $suppliers;
		return $value;
	}

	private static function voucher_consumption( $value, $presented_digest, $beneficiary_digest, $owner_digest, $fx, $ledger, $expiry, $restrictions, $created ) {
		$keys = array( 'operation_ref', 'purchase_ref', 'vertical', 'supplier_ref', 'settlement_currency', 'purchase_total_minor', 'source_amount_minor', 'settlement_amount_minor', 'rounding_remainder_numerator', 'state', 'blocked_reason', 'consumption_at', 'evidence_digest' );
		$consumption_at = is_array( $value ) && array_key_exists( 'consumption_at', $value ) && null !== $value['consumption_at'] ? self::utc_timestamp( $value['consumption_at'] ) : null;
		if (
			! self::exact_object( $value, $keys ) ||
			! self::opaque_ref( $value['operation_ref'], 'stored_value_operation' ) ||
			! self::opaque_ref( $value['purchase_ref'], 'purchase' ) ||
			! self::identifier( $value['vertical'] ) ||
			! self::opaque_ref( $value['supplier_ref'], 'supplier' ) ||
			$fx['settlement_currency'] !== $value['settlement_currency'] ||
			! self::nonnegative_integer( $value['purchase_total_minor'] ) ||
			! self::nonnegative_integer( $value['source_amount_minor'] ) ||
			! self::nonnegative_integer( $value['settlement_amount_minor'] ) ||
			! self::nonnegative_integer( $value['rounding_remainder_numerator'] ) ||
			! in_array( $value['state'], self::VOUCHER_STATES, true ) ||
			( null !== $value['blocked_reason'] && ! self::identifier( $value['blocked_reason'] ) ) ||
			( null !== $value['consumption_at'] && null === $consumption_at ) ||
			! self::digest( $value['evidence_digest'] )
		) {
			return self::error( 'voucher_consumption_shape_invalid', 'Voucher consumption identity and values are invalid.' );
		}
		$beneficiary_matches = hash_equals( $beneficiary_digest, $presented_digest );
		$owner_matches = hash_equals( $owner_digest, $presented_digest );
		$restriction_matches = in_array( $value['vertical'], $restrictions['permitted_verticals'], true ) && in_array( $value['supplier_ref'], $restrictions['permitted_supplier_refs'], true ) && $value['purchase_total_minor'] >= $restrictions['minimum_purchase_minor'];
		if ( 'planned' === $value['state'] ) {
			if ( 'current' !== $expiry['state'] || ( $restrictions['beneficiary_match_required'] && ! $beneficiary_matches ) || ( 'non_transferable' === $restrictions['transferability'] && ! $owner_matches ) || ! $restriction_matches || null !== $value['blocked_reason'] ) {
				return self::error( 'voucher_planned_eligibility_invalid', 'A planned use requires current expiry, beneficiary match, and restriction match.' );
			}
			if ( null === $consumption_at || $expiry['evaluated_epoch'] !== $created || $consumption_at !== $created || $created >= $expiry['expires_epoch'] || $created < $expiry['issued_epoch'] ) {
				return self::error( 'voucher_planned_clock_invalid', 'Planned evaluation, record creation, and consumption must share one instant before expiry.' );
			}
			if ( $fx['observed_epoch'] < $expiry['issued_epoch'] || $consumption_at < $fx['observed_epoch'] || $consumption_at > $fx['valid_epoch'] ) {
				return self::error( 'voucher_fx_stale', 'Planned conversion requires FX evidence current at evaluation.' );
			}
			if ( 0 === $ledger['requested_consumption_minor'] || $ledger['applied_consumption_minor'] !== $ledger['requested_consumption_minor'] || $value['source_amount_minor'] !== $ledger['applied_consumption_minor'] ) {
				return self::error( 'voucher_planned_value_invalid', 'Planned use must apply the exact requested source value.' );
			}
			if ( ! $restrictions['partial_consumption_allowed'] && $ledger['applied_consumption_minor'] !== $ledger['available_before_minor'] ) {
				return self::error( 'voucher_partial_use_prohibited', 'A non-partial voucher must consume all remaining value.' );
			}
			$source_scale = self::minor_unit_scale( $fx['source_minor_unit_exponent'] );
			$settlement_scale = self::minor_unit_scale( $fx['settlement_minor_unit_exponent'] );
			$scaled = self::safe_multiply( $value['source_amount_minor'], $fx['rate_numerator'] );
			$scaled = null === $scaled ? null : self::safe_multiply( $scaled, $settlement_scale );
			$divisor = self::safe_multiply( $fx['rate_denominator'], $source_scale );
			if ( null === $scaled || null === $divisor || 0 === $divisor ) {
				return self::error( 'voucher_fx_overflow', 'FX conversion exceeds the bounded ledger.' );
			}
			$expected_settlement = intdiv( $scaled, $divisor );
			$expected_remainder = $scaled % $divisor;
			if ( $expected_settlement !== $value['settlement_amount_minor'] || $expected_remainder !== $value['rounding_remainder_numerator'] ) {
				return self::error( 'voucher_fx_reconciliation_invalid', 'Settlement value and rounding remainder must match the exact integer FX basis.' );
			}
			if ( $value['settlement_amount_minor'] > $value['purchase_total_minor'] ) {
				return self::error( 'voucher_consumption_exceeds_purchase', 'Voucher settlement value cannot exceed the purchase total.' );
			}
		} else {
			if ( 0 !== $ledger['applied_consumption_minor'] || 0 !== $value['source_amount_minor'] || 0 !== $value['settlement_amount_minor'] || 0 !== $value['rounding_remainder_numerator'] || null === $value['blocked_reason'] || null !== $value['consumption_at'] ) {
				return self::error( 'voucher_blocked_value_invalid', 'Blocked use cannot consume or convert value.' );
			}
			if ( 'blocked_expired' === $value['state'] && 'expired' !== $expiry['state'] ) {
				return self::error( 'voucher_expiry_block_mismatch', 'Expired block requires expired voucher truth.' );
			}
			if ( 'blocked_beneficiary' === $value['state'] && ( ! $restrictions['beneficiary_match_required'] || $beneficiary_matches ) ) {
				return self::error( 'voucher_beneficiary_block_mismatch', 'Beneficiary block requires an actual required mismatch.' );
			}
			if ( 'blocked_restriction' === $value['state'] && $restriction_matches ) {
				return self::error( 'voucher_restriction_block_mismatch', 'Restriction block requires an actual scope or minimum-spend mismatch.' );
			}
		}
		return $value;
	}

	private static function boundary( $value ) {
		$keys = array(
			'server_only', 'public_serialization_allowed', 'simulation_only', 'execution_authorized',
			'account_merge_authorized', 'accrual_credit_authorized', 'redemption_authorized',
			'voucher_consumption_authorized', 'refund_authorized', 'supplier_dispatched',
			'provider_called', 'processor_called', 'ledger_mutated', 'message_sent',
		);
		if ( ! self::exact_object( $value, $keys ) || true !== $value['server_only'] || true !== $value['simulation_only'] ) {
			return false;
		}
		foreach ( $keys as $key ) {
			if ( in_array( $key, array( 'server_only', 'simulation_only' ), true ) ) {
				continue;
			}
			if ( false !== $value[ $key ] ) {
				return false;
			}
		}
		return true;
	}

	private static function reference_list( $value, $prefix, $minimum, $maximum ) {
		if ( ! is_array( $value ) || array_values( $value ) !== $value || count( $value ) < $minimum || count( $value ) > $maximum ) {
			return self::error( 'reference_list_invalid', 'Reference list cardinality is invalid.' );
		}
		$seen = array();
		foreach ( $value as $ref ) {
			if ( ! self::opaque_ref( $ref, $prefix ) || isset( $seen[ $ref ] ) ) {
				return self::error( 'reference_list_invalid', 'Reference lists must be unique and opaque.' );
			}
			$seen[ $ref ] = true;
		}
		$result = array_keys( $seen );
		sort( $result, SORT_STRING );
		if ( $result !== $value ) {
			return self::error( 'reference_list_not_canonical', 'Reference lists must use canonical lexical order.' );
		}
		return $result;
	}

	private static function identifier_list( $value, $minimum, $maximum ) {
		if ( ! is_array( $value ) || array_values( $value ) !== $value || count( $value ) < $minimum || count( $value ) > $maximum ) {
			return self::error( 'identifier_list_invalid', 'Identifier list cardinality is invalid.' );
		}
		$seen = array();
		foreach ( $value as $identifier ) {
			if ( ! self::identifier( $identifier ) || isset( $seen[ $identifier ] ) ) {
				return self::error( 'identifier_list_invalid', 'Identifier lists must be unique and exact.' );
			}
			$seen[ $identifier ] = true;
		}
		$result = array_keys( $seen );
		sort( $result, SORT_STRING );
		return $result === $value ? $result : self::error( 'identifier_list_not_canonical', 'Identifier lists must use canonical lexical order.' );
	}

	private static function digest_list( $value, $minimum, $maximum ) {
		if ( ! is_array( $value ) || array_values( $value ) !== $value || count( $value ) < $minimum || count( $value ) > $maximum ) {
			return self::error( 'digest_list_invalid', 'Digest list cardinality is invalid.' );
		}
		$seen = array();
		foreach ( $value as $digest ) {
			if ( ! self::digest( $digest ) || isset( $seen[ $digest ] ) ) {
				return self::error( 'digest_list_invalid', 'Digest lists must be unique.' );
			}
			$seen[ $digest ] = true;
		}
		$result = array_keys( $seen );
		sort( $result, SORT_STRING );
		return $result === $value ? $result : self::error( 'digest_list_not_canonical', 'Digest lists must use canonical lexical order.' );
	}

	private static function disjoint_exhaustive( $affected, $preserved, $all ) {
		if ( array_intersect( $affected, $preserved ) ) {
			return false;
		}
		$union = array_merge( $affected, $preserved );
		sort( $union, SORT_STRING );
		$expected = $all;
		sort( $expected, SORT_STRING );
		return $union === $expected;
	}

	private static function same_ordered_set( $left, $right ) {
		return is_array( $left ) && is_array( $right ) && array_values( $left ) === $left && array_values( $right ) === $right && $left === $right;
	}

	private static function selected_digest( $record, $keys ) {
		if ( ! is_array( $record ) || array_diff( $keys, array_keys( $record ) ) ) {
			return '';
		}
		$basis = array();
		foreach ( $keys as $key ) {
			$basis[ $key ] = $record[ $key ];
		}
		return self::canonical_digest( $basis );
	}

	private static function minor_unit_scale( $exponent ) {
		$scales = array( 1, 10, 100, 1000, 10000 );
		return isset( $scales[ $exponent ] ) ? $scales[ $exponent ] : 0;
	}

	private static function safe_multiply( $left, $right ) {
		if ( ! is_int( $left ) || ! is_int( $right ) || $left < 0 || $right < 0 ) {
			return null;
		}
		if ( 0 === $left || 0 === $right ) {
			return 0;
		}
		return $left > intdiv( PHP_INT_MAX, $right ) ? null : $left * $right;
	}

	private static function opaque_ref( $value, $prefix ) {
		return is_string( $value ) && 1 === preg_match( '/^' . preg_quote( $prefix, '/' ) . '_[A-Za-z0-9][A-Za-z0-9_-]{15,95}$/', $value );
	}

	private static function identifier( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9_]{1,63}$/', $value );
	}

	private static function currency( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[A-Z]{3}$/', $value );
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function nonnegative_integer( $value ) {
		return is_int( $value ) && $value >= 0 && $value <= self::MAX_INTEGER_VALUE;
	}

	private static function positive_integer( $value ) {
		return self::nonnegative_integer( $value ) && $value > 0;
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function contains_sensitive_material( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				if ( preg_match( '/(?:^|_)(?:api_?key|secret|password|bearer|access_?token|refresh_?token|private_?key|cvv|cvc|card_?number|pan|iban|passport|medical|email|phone|traveler_?name|full_?name)(?:$|_)/i', (string) $key ) || self::contains_sensitive_material( $child ) ) {
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

	private static function utc_timestamp( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/', $value, $parts ) ) {
			return null;
		}
		if ( ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59 ) {
			return null;
		}
		try {
			return ( new DateTimeImmutable( $value ) )->getTimestamp();
		} catch ( Exception $error ) {
			return null;
		}
	}

	private static function clock( $value ) {
		if ( null === $value ) {
			return time();
		}
		return is_int( $value ) && $value > 0 ? $value : null;
	}

	private static function canonical_digest( $value ) {
		$encoded = wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
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
		return new WP_Error( 'tra_vel_loyalty_value_stress_' . $suffix, $message, array( 'status' => $status ) );
	}
}
