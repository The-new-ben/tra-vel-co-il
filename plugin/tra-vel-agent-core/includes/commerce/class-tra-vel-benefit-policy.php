<?php
/**
 * Closed validation policy for the non-transactional benefits foundation.
 *
 * This contract deliberately validates only digests and product identities. It
 * cannot accept authentication material, payment credentials, or a live debit.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Benefit_Policy {
	/**
	 * Validate one source-backed program identity without campaign promises.
	 *
	 * @return array|WP_Error
	 */
	public static function benefit_program( $record ) {
		$keys = array( 'contract_version', 'program_id', 'owner_id', 'display_name', 'unit_code', 'unit_type', 'supported_operations', 'status', 'integration_state', 'source', 'commercial_truth' );
		if ( ! self::exact_object( $record, $keys ) || Tra_Vel_Benefit_Taxonomy::CONTRACT_VERSION !== $record['contract_version'] ) {
			return self::error( 'program_shape_invalid', 'The benefit program is not a closed supported contract.' );
		}

		$program_id = Tra_Vel_Benefit_Taxonomy::identifier( $record['program_id'], 'program' );
		$owner_id   = Tra_Vel_Benefit_Taxonomy::identifier( $record['owner_id'] );
		$unit_code  = Tra_Vel_Benefit_Taxonomy::identifier( $record['unit_code'], 'unit' );
		if ( '' === $program_id || '' === $owner_id || '' === $unit_code || ! self::plain_text( $record['display_name'], 2, 100 ) || '' === Tra_Vel_Benefit_Taxonomy::enum_value( $record['unit_type'], Tra_Vel_Benefit_Taxonomy::UNIT_TYPES ) ) {
			return self::error( 'program_identity_invalid', 'The benefit program identity or unit is invalid.' );
		}

		$operations = self::enum_list( $record['supported_operations'], array( 'read_balance', 'quote', 'redeem', 'convert', 'earn', 'read_status' ), 1, 6 );
		$status     = Tra_Vel_Benefit_Taxonomy::enum_value( $record['status'], array( 'catalogued', 'active', 'suspended', 'retired', 'announced_not_operational', 'unverified' ) );
		$integration = Tra_Vel_Benefit_Taxonomy::integration_state( $record['integration_state'] );
		$source      = self::catalog_source( $record['source'] );
		if ( is_wp_error( $operations ) || '' === $status || '' === $integration || is_wp_error( $source ) || ! self::exact_false_truth( $record['commercial_truth'], array( 'live_connection', 'live_redemption' ) ) ) {
			return self::error( 'program_policy_invalid', 'The benefit program status, source, or commercial boundary is invalid.' );
		}
		if ( ( 'announced_not_operational' === $status ) !== ( 'announced_not_operational' === $integration ) ) {
			return self::error( 'program_integration_mismatch', 'An announced but non-operational program must retain the matching integration state.' );
		}

		$record['supported_operations'] = $operations;
		$record['source']               = $source;
		return $record;
	}

	/**
	 * Validate an exact issuer/network product identity without cardholder data.
	 *
	 * @return array|WP_Error
	 */
	public static function credential_product( $record ) {
		$keys = array( 'contract_version', 'credential_product_id', 'issuer_id', 'network_id', 'product_code', 'display_name', 'tier', 'residency_scopes', 'effective_window', 'status', 'integration_state', 'source', 'commercial_truth' );
		if ( ! self::exact_object( $record, $keys ) || Tra_Vel_Benefit_Taxonomy::CONTRACT_VERSION !== $record['contract_version'] ) {
			return self::error( 'credential_product_shape_invalid', 'The credential product is not a closed supported contract.' );
		}

		if ( '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['credential_product_id'], 'credential_product' ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['issuer_id'] ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['network_id'] ) || ! self::exact_slug( $record['product_code'], 2, 64, true ) || ! self::exact_slug( $record['tier'], 2, 32, true ) || ! self::plain_text( $record['display_name'], 2, 100 ) ) {
			return self::error( 'credential_product_identity_invalid', 'The credential product identity is invalid.' );
		}

		$residencies = self::country_scope_list( $record['residency_scopes'] );
		$window      = self::window( $record['effective_window'] );
		$status      = Tra_Vel_Benefit_Taxonomy::enum_value( $record['status'], array( 'catalogued', 'active', 'transition', 'suspended', 'retired', 'unverified' ) );
		$integration = Tra_Vel_Benefit_Taxonomy::integration_state( $record['integration_state'] );
		$source      = self::catalog_source( $record['source'] );
		if ( is_wp_error( $residencies ) || is_wp_error( $window ) || '' === $status || '' === $integration || is_wp_error( $source ) || ! self::exact_false_truth( $record['commercial_truth'], array( 'live_eligibility_verification' ) ) ) {
			return self::error( 'credential_product_policy_invalid', 'The credential product window, status, source, or commercial boundary is invalid.' );
		}

		$record['residency_scopes'] = $residencies;
		$record['effective_window'] = $window;
		$record['source']           = $source;
		return $record;
	}

	/**
	 * Validate an immutable campaign evidence version and all dated windows.
	 *
	 * @return array|WP_Error
	 */
	public static function campaign_version( $record ) {
		$keys = array( 'contract_version', 'campaign_id', 'version', 'provider_id', 'program_ids', 'credential_product_ids', 'benefit_types', 'windows', 'inventory_cap_state', 'ruleset_digest', 'review_state', 'status', 'supersedes_version', 'integration_state', 'source', 'commercial_truth' );
		if ( ! self::exact_object( $record, $keys ) || Tra_Vel_Benefit_Taxonomy::CONTRACT_VERSION !== $record['contract_version'] ) {
			return self::error( 'campaign_shape_invalid', 'The campaign version is not a closed supported contract.' );
		}
		if ( '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['campaign_id'], 'campaign' ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['provider_id'] ) || ! is_int( $record['version'] ) || $record['version'] < 1 || $record['version'] > 2147483647 || '' === Tra_Vel_Benefit_Taxonomy::digest( $record['ruleset_digest'] ) ) {
			return self::error( 'campaign_identity_invalid', 'The campaign identity, version, provider, or ruleset digest is invalid.' );
		}

		$program_ids = self::identifier_list( $record['program_ids'], 'program', 0, 20 );
		$product_ids = self::identifier_list( $record['credential_product_ids'], 'credential_product', 0, 50 );
		$types       = self::enum_list( $record['benefit_types'], Tra_Vel_Benefit_Taxonomy::BENEFIT_TYPES, 1, 7 );
		$windows     = self::campaign_windows( $record['windows'] );
		$source      = self::catalog_source( $record['source'] );
		$review      = Tra_Vel_Benefit_Taxonomy::enum_value( $record['review_state'], array( 'draft', 'reviewed', 'quarantined', 'superseded' ) );
		$status      = Tra_Vel_Benefit_Taxonomy::enum_value( $record['status'], array( 'draft', 'scheduled', 'active', 'expired', 'suspended', 'announced_not_operational' ) );
		$integration = Tra_Vel_Benefit_Taxonomy::integration_state( $record['integration_state'] );
		$cap_state   = Tra_Vel_Benefit_Taxonomy::enum_value( $record['inventory_cap_state'], array( 'not_stated', 'unknown', 'available', 'exhausted' ) );
		if ( is_wp_error( $program_ids ) || is_wp_error( $product_ids ) || is_wp_error( $types ) || is_wp_error( $windows ) || is_wp_error( $source ) || '' === $review || '' === $status || '' === $integration || '' === $cap_state || ! self::exact_false_truth( $record['commercial_truth'], array( 'provider_quote_available', 'checkout_application_available' ) ) ) {
			return self::error( 'campaign_policy_invalid', 'The campaign rules, windows, source, status, or commercial boundary is invalid.' );
		}

		$supersedes = $record['supersedes_version'];
		if ( null !== $supersedes && ( ! is_int( $supersedes ) || $supersedes < 1 || $supersedes >= $record['version'] ) ) {
			return self::error( 'campaign_version_lineage_invalid', 'A campaign may supersede only an earlier positive version.' );
		}
		if ( 'active' === $status && ( 'reviewed' !== $review || 'reviewed' !== $source['review_state'] ) ) {
			return self::error( 'campaign_review_required', 'An active campaign requires reviewed rules and reviewed source evidence.' );
		}
		if ( ( 'announced_not_operational' === $status ) !== ( 'announced_not_operational' === $integration ) ) {
			return self::error( 'campaign_integration_mismatch', 'An announced campaign must remain explicitly non-operational.' );
		}

		$record['program_ids']            = $program_ids;
		$record['credential_product_ids'] = $product_ids;
		$record['benefit_types']          = $types;
		$record['windows']                = $windows;
		$record['source']                 = $source;
		return $record;
	}

	/**
	 * Return current, stale, or not_yet_observed for a source at an exact time.
	 *
	 * @return string|WP_Error
	 */
	public static function source_freshness_state( $source, $evaluated_at_utc ) {
		if ( ! is_array( $source ) || ! isset( $source['observed_at_utc'], $source['fresh_until_utc'] ) ) {
			return self::error( 'source_freshness_shape_invalid', 'Source freshness requires observed and expiry instants.' );
		}
		$observed = Tra_Vel_Benefit_Taxonomy::utc_datetime( $source['observed_at_utc'] );
		$fresh    = Tra_Vel_Benefit_Taxonomy::utc_datetime( $source['fresh_until_utc'] );
		$at       = Tra_Vel_Benefit_Taxonomy::utc_datetime( $evaluated_at_utc );
		if ( null === $observed || null === $fresh || null === $at || strcmp( $observed, $fresh ) > 0 ) {
			return self::error( 'source_freshness_invalid', 'Source freshness instants are invalid.' );
		}
		if ( strcmp( $at, $observed ) < 0 ) {
			return 'not_yet_observed';
		}
		return strcmp( $at, $fresh ) <= 0 ? 'current' : 'stale';
	}

	/**
	 * Return before, open, or after for one validated campaign window.
	 *
	 * @return string|WP_Error
	 */
	public static function campaign_window_state( $record, $window_name, $evaluated_at_utc ) {
		$campaign = self::campaign_version( $record );
		if ( is_wp_error( $campaign ) || ! in_array( $window_name, array( 'effective', 'enrollment', 'booking', 'travel' ), true ) ) {
			return self::error( 'campaign_window_request_invalid', 'A valid campaign and named window are required.' );
		}
		$at = Tra_Vel_Benefit_Taxonomy::utc_datetime( $evaluated_at_utc );
		if ( null === $at ) {
			return self::error( 'campaign_window_time_invalid', 'The campaign evaluation time is invalid.' );
		}
		$window = $campaign['windows'][ $window_name ];
		if ( null !== $window['from_utc'] && strcmp( $at, $window['from_utc'] ) < 0 ) {
			return 'before';
		}
		if ( null !== $window['to_utc'] && strcmp( $at, $window['to_utc'] ) > 0 ) {
			return 'after';
		}
		return 'open';
	}

	/**
	 * Validate least-privilege consent and connection state.
	 *
	 * @return array|WP_Error
	 */
	public static function member_connection( $record ) {
		$keys = array( 'contract_version', 'connection_id', 'user_reference_digest', 'program_id', 'mode', 'state', 'subject_reference_digest', 'assurance', 'consent', 'freshness', 'commercial_truth' );
		if ( ! self::exact_object( $record, $keys ) || Tra_Vel_Benefit_Taxonomy::CONTRACT_VERSION !== $record['contract_version'] ) {
			return self::error( 'connection_shape_invalid', 'The member connection is not a closed supported contract.' );
		}
		if ( '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['connection_id'], 'connection' ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['program_id'], 'program' ) || '' === Tra_Vel_Benefit_Taxonomy::digest( $record['user_reference_digest'] ) || ( null !== $record['subject_reference_digest'] && '' === Tra_Vel_Benefit_Taxonomy::digest( $record['subject_reference_digest'] ) ) ) {
			return self::error( 'connection_identity_invalid', 'The member connection identifiers are invalid.' );
		}

		$mode      = Tra_Vel_Benefit_Taxonomy::enum_value( $record['mode'], Tra_Vel_Benefit_Taxonomy::CONNECTION_MODES );
		$state     = Tra_Vel_Benefit_Taxonomy::enum_value( $record['state'], Tra_Vel_Benefit_Taxonomy::CONNECTION_STATES );
		$assurance = Tra_Vel_Benefit_Taxonomy::enum_value( $record['assurance'], Tra_Vel_Benefit_Taxonomy::ASSURANCE_LEVELS );
		$consent   = self::connection_consent( $record['consent'] );
		$freshness = self::nullable_freshness( $record['freshness'] );
		if ( '' === $mode || '' === $state || '' === $assurance || is_wp_error( $consent ) || is_wp_error( $freshness ) || ! self::exact_false_truth( $record['commercial_truth'], array( 'provider_connection_live', 'redemption_enabled' ) ) ) {
			return self::error( 'connection_policy_invalid', 'The connection mode, state, assurance, consent, freshness, or commercial boundary is invalid.' );
		}

		$scopes         = $consent['scopes'];
		$can_read       = in_array( 'read_balance', $scopes, true );
		$can_refresh    = in_array( 'refresh_balance', $scopes, true );
		$can_redeem     = in_array( 'redeem', $scopes, true );
		$connected      = in_array( $state, array( 'connected_read_only', 'refresh_required', 'connected_current', 'redemption_step_up_required', 'redemption_authorized' ), true );
		$customer_mode  = in_array( $mode, array( 'manual_balance', 'statement_evidence' ), true );
		if ( ( $can_refresh && ! $can_read ) || ( $can_redeem && ! $can_read ) || $consent['refresh_permission'] !== $can_refresh || $consent['redemption_permission'] !== $can_redeem ) {
			return self::error( 'connection_scope_escalation', 'Refresh and redemption permissions must be explicit and may not be inferred from balance reading.' );
		}
		if ( $can_redeem && ( 'benefit_redemption' !== $consent['purpose'] || 'redemption_authorized' !== $state ) ) {
			return self::error( 'connection_redemption_consent_invalid', 'Redemption requires its own purpose, scope, and authorized state.' );
		}
		if ( 'redemption_authorized' === $state && ! $can_redeem ) {
			return self::error( 'connection_redemption_consent_invalid', 'An authorized redemption state requires explicit redemption consent.' );
		}
		if ( $connected && ( ! $can_read || null === $freshness['observed_at_utc'] ) ) {
			return self::error( 'connection_freshness_required', 'A connected account requires balance-read scope and a timestamped freshness record.' );
		}
		if ( $customer_mode && ( $can_redeem || in_array( $assurance, array( 'provider_verified', 'authorized_partner_verified' ), true ) ) ) {
			return self::error( 'connection_customer_evidence_boundary', 'Manual or statement evidence is planning-only and cannot authorize redemption or claim provider verification.' );
		}
		if ( ! $customer_mode && $connected && null === $record['subject_reference_digest'] ) {
			return self::error( 'connection_subject_reference_required', 'An authorized provider connection requires a pseudonymous subject reference digest.' );
		}

		$record['consent']   = $consent;
		$record['freshness'] = $freshness;
		return $record;
	}

	/**
	 * Validate an immutable integer-only balance observation.
	 *
	 * @return array|WP_Error
	 */
	public static function balance_snapshot( $record, $evaluated_at_utc = null ) {
		$keys = array( 'contract_version', 'snapshot_id', 'connection_id', 'program_id', 'amounts', 'expiry_lots', 'assurance', 'declared_freshness', 'source', 'immutable_digest', 'planning_only' );
		if ( ! self::exact_object( $record, $keys ) || Tra_Vel_Benefit_Taxonomy::CONTRACT_VERSION !== $record['contract_version'] || true !== $record['planning_only'] ) {
			return self::error( 'balance_shape_invalid', 'The balance snapshot is not a closed planning-only contract.' );
		}
		if ( '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['snapshot_id'], 'balance_snapshot' ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['connection_id'], 'connection' ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['program_id'], 'program' ) || '' === Tra_Vel_Benefit_Taxonomy::digest( $record['immutable_digest'] ) ) {
			return self::error( 'balance_identity_invalid', 'The balance snapshot identifiers are invalid.' );
		}

		$amounts   = self::balance_amounts( $record['amounts'] );
		$source    = self::balance_source( $record['source'] );
		$assurance = Tra_Vel_Benefit_Taxonomy::enum_value( $record['assurance'], Tra_Vel_Benefit_Taxonomy::ASSURANCE_LEVELS );
		$declared  = Tra_Vel_Benefit_Taxonomy::enum_value( $record['declared_freshness'], array( 'current', 'stale', 'expired', 'self_reported' ) );
		if ( is_wp_error( $amounts ) || is_wp_error( $source ) || '' === $assurance || '' === $declared ) {
			return self::error( 'balance_policy_invalid', 'The balance amounts, source, assurance, or freshness declaration is invalid.' );
		}
		$lots = self::expiry_lots( $record['expiry_lots'], $amounts );
		if ( is_wp_error( $lots ) ) {
			return $lots;
		}

		$customer_source = in_array( $source['authority'], array( 'customer_evidence', 'customer_assertion' ), true );
		if ( $customer_source !== ( 'self_reported' === $declared ) ) {
			return self::error( 'balance_provenance_mismatch', 'Customer-provided balances must remain self-reported and provider observations must not be labeled self-reported.' );
		}
		if ( null !== $evaluated_at_utc ) {
			$actual = self::source_freshness_state( $source, $evaluated_at_utc );
			if ( is_wp_error( $actual ) || 'not_yet_observed' === $actual || ( 'current' === $actual && 'current' !== $declared && ! $customer_source ) || ( 'stale' === $actual && 'current' === $declared ) ) {
				return self::error( 'balance_freshness_mismatch', 'The declared balance freshness does not match its timestamped source.' );
			}
		}

		$record['amounts']     = array_values( $amounts );
		$record['expiry_lots'] = $lots;
		$record['source']      = $source;
		return $record;
	}

	/**
	 * Validate a planning-only benefit comparison.
	 *
	 * @return array|WP_Error
	 */
	public static function benefit_quote( $record ) {
		$keys = array( 'contract_version', 'benefit_quote_id', 'base_offer_snapshot_id', 'base_offer_digest', 'program_id', 'campaign_id', 'campaign_version', 'connection_id', 'decision_state', 'reason_codes', 'next_action_code', 'verified_input_digest', 'cash_effect', 'points_effects', 'quoted_at_utc', 'expires_at_utc', 'provider_quote_reference_digest', 'source', 'commercial_truth' );
		if ( ! self::exact_object( $record, $keys ) || Tra_Vel_Benefit_Taxonomy::CONTRACT_VERSION !== $record['contract_version'] ) {
			return self::error( 'quote_shape_invalid', 'The benefit quote is not a closed supported contract.' );
		}
		if ( '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['benefit_quote_id'], 'benefit_quote' ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['base_offer_snapshot_id'] ) || '' === Tra_Vel_Benefit_Taxonomy::digest( $record['base_offer_digest'] ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['program_id'], 'program' ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['campaign_id'], 'campaign' ) || ! is_int( $record['campaign_version'] ) || $record['campaign_version'] < 1 || $record['campaign_version'] > 2147483647 || ( null !== $record['connection_id'] && '' === Tra_Vel_Benefit_Taxonomy::identifier( $record['connection_id'], 'connection' ) ) || '' === Tra_Vel_Benefit_Taxonomy::digest( $record['verified_input_digest'] ) || ( null !== $record['provider_quote_reference_digest'] && '' === Tra_Vel_Benefit_Taxonomy::digest( $record['provider_quote_reference_digest'] ) ) ) {
			return self::error( 'quote_identity_invalid', 'The benefit quote identifiers are invalid.' );
		}

		$decision = Tra_Vel_Benefit_Taxonomy::decision_state( $record['decision_state'] );
		$reasons  = self::identifier_list( $record['reason_codes'], 'generic', 1, 20 );
		$cash     = self::quote_cash_effect( $record['cash_effect'] );
		$points   = self::quote_points_effects( $record['points_effects'] );
		$source   = self::quote_source( $record['source'] );
		$quoted   = Tra_Vel_Benefit_Taxonomy::utc_datetime( $record['quoted_at_utc'] );
		$expires  = Tra_Vel_Benefit_Taxonomy::utc_datetime( $record['expires_at_utc'] );
		$next     = null === $record['next_action_code'] ? null : Tra_Vel_Benefit_Taxonomy::identifier( $record['next_action_code'] );
		$truth    = $record['commercial_truth'];
		if ( '' === $decision || is_wp_error( $reasons ) || is_wp_error( $cash ) || is_wp_error( $points ) || is_wp_error( $source ) || null === $quoted || null === $expires || strcmp( $quoted, $expires ) >= 0 || ( null !== $record['next_action_code'] && '' === $next ) || ! self::exact_object( $truth, array( 'planning_only', 'may_change_payable_total', 'redemption_available' ) ) || true !== $truth['planning_only'] || false !== $truth['may_change_payable_total'] || false !== $truth['redemption_available'] ) {
			return self::error( 'quote_policy_invalid', 'The benefit quote decision, values, times, source, or commercial boundary is invalid.' );
		}
		if ( 'unknown_requires_action' === $decision && null === $next ) {
			return self::error( 'quote_next_action_required', 'An unknown benefit decision requires the smallest next action.' );
		}
		if ( strcmp( $source['base_offer_revalidated_at_utc'], $quoted ) > 0 ) {
			return self::error( 'quote_offer_time_invalid', 'The base offer cannot be revalidated after the benefit quote was created.' );
		}
		if ( 'eligible_verified' === $decision ) {
			$freshness = self::source_freshness_state( $source, $quoted );
			if ( 'current' !== $freshness || ! in_array( $source['assurance'], array( 'provider_verified', 'authorized_partner_verified', 'official_terms_verified' ), true ) ) {
				return self::error( 'quote_verification_invalid', 'A verified eligibility decision requires current accepted evidence.' );
			}
		}

		$record['reason_codes'] = $reasons;
		$record['cash_effect']  = $cash;
		$record['points_effects'] = $points;
		$record['quoted_at_utc'] = $quoted;
		$record['expires_at_utc'] = $expires;
		$record['source'] = $source;
		return $record;
	}

	/**
	 * Validate a simulated redemption state envelope. V1 rejects provider
	 * submission references and real debits by construction.
	 *
	 * @return array|WP_Error
	 */
	public static function redemption_operation( $record ) {
		$keys = array( 'contract_version', 'redemption_operation_id', 'idempotency_reference_digest', 'benefit_quote_id', 'connection_id', 'program_id', 'campaign_id', 'campaign_version', 'state', 'reconciliation_state', 'authorization_reference_digest', 'provider_operation_reference_digest', 'points_debits', 'cash_effect', 'requested_at_utc', 'updated_at_utc', 'commercial_truth' );
		if ( ! self::exact_object( $record, $keys ) || Tra_Vel_Benefit_Taxonomy::CONTRACT_VERSION !== $record['contract_version'] ) {
			return self::error( 'redemption_shape_invalid', 'The redemption operation is not a closed supported contract.' );
		}
		$id_checks = array(
			Tra_Vel_Benefit_Taxonomy::identifier( $record['redemption_operation_id'], 'redemption_operation' ),
			Tra_Vel_Benefit_Taxonomy::identifier( $record['benefit_quote_id'], 'benefit_quote' ),
			Tra_Vel_Benefit_Taxonomy::identifier( $record['connection_id'], 'connection' ),
			Tra_Vel_Benefit_Taxonomy::identifier( $record['program_id'], 'program' ),
			Tra_Vel_Benefit_Taxonomy::identifier( $record['campaign_id'], 'campaign' ),
			Tra_Vel_Benefit_Taxonomy::digest( $record['idempotency_reference_digest'] ),
			Tra_Vel_Benefit_Taxonomy::digest( $record['authorization_reference_digest'] ),
		);
		if ( in_array( '', $id_checks, true ) || ! is_int( $record['campaign_version'] ) || $record['campaign_version'] < 1 || $record['campaign_version'] > 2147483647 || null !== $record['provider_operation_reference_digest'] ) {
			return self::error( 'redemption_identity_invalid', 'The simulated redemption identifiers or provider-submission boundary are invalid.' );
		}

		$state = Tra_Vel_Benefit_Taxonomy::enum_value( $record['state'], Tra_Vel_Benefit_Taxonomy::REDEMPTION_STATES );
		$reconciliation = Tra_Vel_Benefit_Taxonomy::enum_value( $record['reconciliation_state'], Tra_Vel_Benefit_Taxonomy::RECONCILIATION_STATES );
		$points = self::redemption_points( $record['points_debits'] );
		$cash   = self::redemption_cash_effect( $record['cash_effect'] );
		$requested = Tra_Vel_Benefit_Taxonomy::utc_datetime( $record['requested_at_utc'] );
		$updated   = Tra_Vel_Benefit_Taxonomy::utc_datetime( $record['updated_at_utc'] );
		$truth     = $record['commercial_truth'];
		if ( '' === $state || '' === $reconciliation || is_wp_error( $points ) || is_wp_error( $cash ) || null === $requested || null === $updated || strcmp( $requested, $updated ) > 0 || ! self::exact_object( $truth, array( 'simulated', 'provider_submission', 'real_debit' ) ) || true !== $truth['simulated'] || false !== $truth['provider_submission'] || false !== $truth['real_debit'] ) {
			return self::error( 'redemption_policy_invalid', 'The simulated redemption values, states, times, or commercial truth are invalid.' );
		}
		if ( in_array( $state, array( 'operation_uncertain', 'provider_reconciliation_required' ), true ) && ! in_array( $reconciliation, array( 'pending', 'required' ), true ) ) {
			return self::error( 'redemption_reconciliation_required', 'An uncertain simulated redemption must remain locked for reconciliation.' );
		}
		if ( 'succeeded' === $state && 'matched' !== $reconciliation ) {
			return self::error( 'redemption_reconciliation_mismatch', 'A simulated success requires a matched reconciliation state.' );
		}
		if ( 'reversed' === $state && 'reversed' !== $reconciliation ) {
			return self::error( 'redemption_reversal_mismatch', 'A simulated reversal requires a reversed reconciliation state.' );
		}

		$record['points_debits']   = $points;
		$record['cash_effect']     = $cash;
		$record['requested_at_utc'] = $requested;
		$record['updated_at_utc']   = $updated;
		return $record;
	}

	private static function connection_consent( $consent ) {
		$keys = array( 'consent_version', 'consent_reference_digest', 'purpose', 'scopes', 'issued_at_utc', 'expires_at_utc', 'retention_until_utc', 'refresh_permission', 'redemption_permission', 'revocation_route_id' );
		if ( ! self::exact_object( $consent, $keys ) || ! is_string( $consent['consent_version'] ) || 1 !== preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+$/', $consent['consent_version'] ) || '' === Tra_Vel_Benefit_Taxonomy::digest( $consent['consent_reference_digest'] ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $consent['revocation_route_id'], 'revocation_route' ) || ! is_bool( $consent['refresh_permission'] ) || ! is_bool( $consent['redemption_permission'] ) ) {
			return self::error( 'consent_shape_invalid', 'The benefit consent object is invalid.' );
		}
		$purpose = Tra_Vel_Benefit_Taxonomy::enum_value( $consent['purpose'], array( 'planning', 'balance_comparison', 'benefit_redemption' ) );
		$scopes  = Tra_Vel_Benefit_Taxonomy::consent_scopes( $consent['scopes'] );
		$issued  = Tra_Vel_Benefit_Taxonomy::utc_datetime( $consent['issued_at_utc'] );
		$expires = Tra_Vel_Benefit_Taxonomy::utc_datetime( $consent['expires_at_utc'] );
		$retain  = Tra_Vel_Benefit_Taxonomy::utc_datetime( $consent['retention_until_utc'] );
		if ( '' === $purpose || is_wp_error( $scopes ) || null === $issued || null === $expires || null === $retain || strcmp( $issued, $expires ) >= 0 || strcmp( $issued, $retain ) > 0 ) {
			return self::error( 'consent_policy_invalid', 'The benefit consent purpose, scopes, or validity period is invalid.' );
		}
		$consent['scopes']              = $scopes;
		$consent['issued_at_utc']       = $issued;
		$consent['expires_at_utc']      = $expires;
		$consent['retention_until_utc'] = $retain;
		return $consent;
	}

	private static function nullable_freshness( $freshness ) {
		if ( ! self::exact_object( $freshness, array( 'observed_at_utc', 'fresh_until_utc' ) ) ) {
			return self::error( 'freshness_shape_invalid', 'The freshness object is invalid.' );
		}
		if ( null === $freshness['observed_at_utc'] && null === $freshness['fresh_until_utc'] ) {
			return $freshness;
		}
		if ( null === $freshness['observed_at_utc'] || null === $freshness['fresh_until_utc'] ) {
			return self::error( 'freshness_pair_invalid', 'Freshness timestamps must be present or absent together.' );
		}
		$observed = Tra_Vel_Benefit_Taxonomy::utc_datetime( $freshness['observed_at_utc'] );
		$fresh    = Tra_Vel_Benefit_Taxonomy::utc_datetime( $freshness['fresh_until_utc'] );
		if ( null === $observed || null === $fresh || strcmp( $observed, $fresh ) > 0 ) {
			return self::error( 'freshness_time_invalid', 'Freshness timestamps are invalid.' );
		}
		return array( 'observed_at_utc' => $observed, 'fresh_until_utc' => $fresh );
	}

	private static function catalog_source( $source ) {
		$keys = array( 'authority', 'official_source_url', 'source_content_digest', 'observed_at_utc', 'fresh_until_utc', 'locale', 'review_state' );
		if ( ! self::exact_object( $source, $keys ) || '' === Tra_Vel_Benefit_Taxonomy::enum_value( $source['authority'], Tra_Vel_Benefit_Taxonomy::SOURCE_AUTHORITIES ) || '' === Tra_Vel_Benefit_Taxonomy::digest( $source['source_content_digest'] ) || ! is_string( $source['locale'] ) || 1 !== preg_match( '/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $source['locale'] ) || '' === Tra_Vel_Benefit_Taxonomy::enum_value( $source['review_state'], array( 'unreviewed', 'reviewed', 'quarantined', 'superseded' ) ) ) {
			return self::error( 'catalog_source_shape_invalid', 'The catalogue source evidence is invalid.' );
		}
		$official = in_array( $source['authority'], array( 'official_rules', 'official_product_page' ), true );
		if ( ( null !== $source['official_source_url'] && ! self::https_url( $source['official_source_url'] ) ) || ( $official && ! self::https_url( $source['official_source_url'] ) ) ) {
			return self::error( 'catalog_source_url_invalid', 'Official rules and product pages require a canonical HTTPS source.' );
		}
		$observed = Tra_Vel_Benefit_Taxonomy::utc_datetime( $source['observed_at_utc'] );
		$fresh    = Tra_Vel_Benefit_Taxonomy::utc_datetime( $source['fresh_until_utc'] );
		if ( null === $observed || null === $fresh || strcmp( $observed, $fresh ) > 0 ) {
			return self::error( 'catalog_source_time_invalid', 'The catalogue source freshness interval is invalid.' );
		}
		$source['observed_at_utc'] = $observed;
		$source['fresh_until_utc'] = $fresh;
		return $source;
	}

	private static function balance_source( $source ) {
		$keys = array( 'authority', 'source_reference_digest', 'observed_at_utc', 'fresh_until_utc' );
		$allowed = array( 'signed_partner_api', 'provider_authorized_api', 'provider_support_confirmation', 'customer_evidence', 'customer_assertion' );
		if ( ! self::exact_object( $source, $keys ) || '' === Tra_Vel_Benefit_Taxonomy::enum_value( $source['authority'], $allowed ) || '' === Tra_Vel_Benefit_Taxonomy::digest( $source['source_reference_digest'] ) ) {
			return self::error( 'balance_source_shape_invalid', 'The balance source evidence is invalid.' );
		}
		$observed = Tra_Vel_Benefit_Taxonomy::utc_datetime( $source['observed_at_utc'] );
		$fresh    = Tra_Vel_Benefit_Taxonomy::utc_datetime( $source['fresh_until_utc'] );
		if ( null === $observed || null === $fresh || strcmp( $observed, $fresh ) > 0 ) {
			return self::error( 'balance_source_time_invalid', 'The balance source freshness interval is invalid.' );
		}
		$source['observed_at_utc'] = $observed;
		$source['fresh_until_utc'] = $fresh;
		return $source;
	}

	private static function quote_source( $source ) {
		$keys = array( 'campaign_source_digest', 'base_offer_revalidated_at_utc', 'observed_at_utc', 'fresh_until_utc', 'assurance' );
		if ( ! self::exact_object( $source, $keys ) || '' === Tra_Vel_Benefit_Taxonomy::digest( $source['campaign_source_digest'] ) || '' === Tra_Vel_Benefit_Taxonomy::enum_value( $source['assurance'], Tra_Vel_Benefit_Taxonomy::ASSURANCE_LEVELS ) ) {
			return self::error( 'quote_source_shape_invalid', 'The benefit quote source evidence is invalid.' );
		}
		$base     = Tra_Vel_Benefit_Taxonomy::utc_datetime( $source['base_offer_revalidated_at_utc'] );
		$observed = Tra_Vel_Benefit_Taxonomy::utc_datetime( $source['observed_at_utc'] );
		$fresh    = Tra_Vel_Benefit_Taxonomy::utc_datetime( $source['fresh_until_utc'] );
		if ( null === $base || null === $observed || null === $fresh || strcmp( $observed, $fresh ) > 0 ) {
			return self::error( 'quote_source_time_invalid', 'The benefit quote source timestamps are invalid.' );
		}
		$source['base_offer_revalidated_at_utc'] = $base;
		$source['observed_at_utc'] = $observed;
		$source['fresh_until_utc'] = $fresh;
		return $source;
	}

	private static function campaign_windows( $windows ) {
		$names = array( 'effective', 'enrollment', 'booking', 'travel' );
		if ( ! self::exact_object( $windows, $names ) ) {
			return self::error( 'campaign_windows_shape_invalid', 'Campaign windows must be explicit and closed.' );
		}
		$normalized = array();
		foreach ( $names as $name ) {
			$window = self::window( $windows[ $name ] );
			if ( is_wp_error( $window ) ) {
				return $window;
			}
			$normalized[ $name ] = $window;
		}
		return $normalized;
	}

	private static function window( $window ) {
		if ( ! self::exact_object( $window, array( 'from_utc', 'to_utc' ) ) ) {
			return self::error( 'window_shape_invalid', 'A dated window requires exact from and to fields.' );
		}
		$from = null === $window['from_utc'] ? null : Tra_Vel_Benefit_Taxonomy::utc_datetime( $window['from_utc'] );
		$to   = null === $window['to_utc'] ? null : Tra_Vel_Benefit_Taxonomy::utc_datetime( $window['to_utc'] );
		if ( ( null !== $window['from_utc'] && null === $from ) || ( null !== $window['to_utc'] && null === $to ) || ( null !== $from && null !== $to && strcmp( $from, $to ) > 0 ) ) {
			return self::error( 'window_time_invalid', 'A dated window is invalid or reversed.' );
		}
		return array( 'from_utc' => $from, 'to_utc' => $to );
	}

	private static function balance_amounts( $rows ) {
		if ( ! self::sequential_list( $rows, 1, 20 ) ) {
			return self::error( 'balance_amounts_invalid', 'A balance requires one to twenty integer amount rows.' );
		}
		$normalized = array();
		foreach ( $rows as $row ) {
			if ( ! self::exact_object( $row, array( 'unit_code', 'unit_type', 'amount_integer' ) ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $row['unit_code'], 'unit' ) || '' === Tra_Vel_Benefit_Taxonomy::enum_value( $row['unit_type'], Tra_Vel_Benefit_Taxonomy::UNIT_TYPES ) || ! Tra_Vel_Benefit_Taxonomy::nonnegative_integer( $row['amount_integer'] ) || isset( $normalized[ $row['unit_code'] ] ) ) {
				return self::error( 'balance_amount_invalid', 'Balance amounts require unique units and nonnegative integers.' );
			}
			$normalized[ $row['unit_code'] ] = $row;
		}
		ksort( $normalized, SORT_STRING );
		return $normalized;
	}

	private static function expiry_lots( $rows, $amounts ) {
		if ( ! self::sequential_list( $rows, 0, 100 ) ) {
			return self::error( 'expiry_lots_invalid', 'Expiry lots must be a bounded list.' );
		}
		$totals = array();
		$normalized = array();
		foreach ( $rows as $row ) {
			if ( ! self::exact_object( $row, array( 'unit_code', 'amount_integer', 'expires_at_utc' ) ) || ! isset( $amounts[ $row['unit_code'] ] ) || ! Tra_Vel_Benefit_Taxonomy::nonnegative_integer( $row['amount_integer'] ) ) {
				return self::error( 'expiry_lot_invalid', 'An expiry lot must reference a known balance unit and integer amount.' );
			}
			$expires = Tra_Vel_Benefit_Taxonomy::utc_datetime( $row['expires_at_utc'] );
			$current = isset( $totals[ $row['unit_code'] ] ) ? $totals[ $row['unit_code'] ] : 0;
			if ( null === $expires || $row['amount_integer'] > PHP_INT_MAX - $current ) {
				return self::error( 'expiry_lot_invalid', 'An expiry lot time or total is invalid.' );
			}
			$totals[ $row['unit_code'] ] = $current + $row['amount_integer'];
			if ( $totals[ $row['unit_code'] ] > $amounts[ $row['unit_code'] ]['amount_integer'] ) {
				return self::error( 'expiry_lot_exceeds_balance', 'Expiry lots cannot exceed the observed unit balance.' );
			}
			$row['expires_at_utc'] = $expires;
			$normalized[] = $row;
		}
		return $normalized;
	}

	private static function quote_cash_effect( $cash ) {
		$keys = array( 'currency', 'immediate_discount_minor', 'future_reward_minor', 'fees_minor', 'payable_now_minor', 'payable_later_minor' );
		if ( ! self::exact_object( $cash, $keys ) || '' === Tra_Vel_Benefit_Taxonomy::currency( $cash['currency'] ) ) {
			return self::error( 'quote_cash_invalid', 'The benefit quote cash effect is invalid.' );
		}
		foreach ( array_diff( $keys, array( 'currency' ) ) as $key ) {
			if ( ! Tra_Vel_Benefit_Taxonomy::nonnegative_integer( $cash[ $key ] ) ) {
				return self::error( 'quote_cash_invalid', 'Benefit cash values must use nonnegative integer minor units.' );
			}
		}
		return $cash;
	}

	private static function quote_points_effects( $rows ) {
		if ( ! self::sequential_list( $rows, 0, 20 ) ) {
			return self::error( 'quote_points_invalid', 'Benefit point effects must be a bounded list.' );
		}
		$units = array();
		foreach ( $rows as $row ) {
			if ( ! self::exact_object( $row, array( 'unit_code', 'debit_integer', 'earn_later_integer' ) ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $row['unit_code'], 'unit' ) || ! Tra_Vel_Benefit_Taxonomy::nonnegative_integer( $row['debit_integer'] ) || ! Tra_Vel_Benefit_Taxonomy::nonnegative_integer( $row['earn_later_integer'] ) || isset( $units[ $row['unit_code'] ] ) ) {
				return self::error( 'quote_points_invalid', 'Benefit point effects require unique units and nonnegative integers.' );
			}
			$units[ $row['unit_code'] ] = true;
		}
		return $rows;
	}

	private static function redemption_points( $rows ) {
		if ( ! self::sequential_list( $rows, 0, 20 ) ) {
			return self::error( 'redemption_points_invalid', 'Redemption points must be a bounded list.' );
		}
		$units = array();
		foreach ( $rows as $row ) {
			if ( ! self::exact_object( $row, array( 'unit_code', 'amount_integer' ) ) || '' === Tra_Vel_Benefit_Taxonomy::identifier( $row['unit_code'], 'unit' ) || ! Tra_Vel_Benefit_Taxonomy::nonnegative_integer( $row['amount_integer'] ) || isset( $units[ $row['unit_code'] ] ) ) {
				return self::error( 'redemption_points_invalid', 'Redemption points require unique units and nonnegative integers.' );
			}
			$units[ $row['unit_code'] ] = true;
		}
		return $rows;
	}

	private static function redemption_cash_effect( $cash ) {
		$keys = array( 'currency', 'discount_minor', 'fees_minor' );
		if ( ! self::exact_object( $cash, $keys ) || '' === Tra_Vel_Benefit_Taxonomy::currency( $cash['currency'] ) || ! Tra_Vel_Benefit_Taxonomy::nonnegative_integer( $cash['discount_minor'] ) || ! Tra_Vel_Benefit_Taxonomy::nonnegative_integer( $cash['fees_minor'] ) ) {
			return self::error( 'redemption_cash_invalid', 'Redemption cash effects require integer minor units.' );
		}
		return $cash;
	}

	private static function identifier_list( $values, $kind, $min, $max ) {
		if ( ! self::sequential_list( $values, $min, $max ) ) {
			return self::error( 'identifier_list_invalid', 'An identifier list is invalid.' );
		}
		$unique = array();
		foreach ( $values as $value ) {
			$value = Tra_Vel_Benefit_Taxonomy::identifier( $value, $kind );
			if ( '' === $value || isset( $unique[ $value ] ) ) {
				return self::error( 'identifier_list_invalid', 'Identifiers must be exact and unique.' );
			}
			$unique[ $value ] = true;
		}
		$values = array_keys( $unique );
		sort( $values, SORT_STRING );
		return $values;
	}

	private static function enum_list( $values, $allowed, $min, $max ) {
		if ( ! self::sequential_list( $values, $min, $max ) ) {
			return self::error( 'enum_list_invalid', 'A closed vocabulary list is invalid.' );
		}
		$unique = array();
		foreach ( $values as $value ) {
			if ( '' === Tra_Vel_Benefit_Taxonomy::enum_value( $value, $allowed ) || isset( $unique[ $value ] ) ) {
				return self::error( 'enum_list_invalid', 'Closed vocabulary values must be exact and unique.' );
			}
			$unique[ $value ] = true;
		}
		$values = array_keys( $unique );
		sort( $values, SORT_STRING );
		return $values;
	}

	private static function country_scope_list( $values ) {
		if ( ! self::sequential_list( $values, 1, 50 ) ) {
			return self::error( 'country_scope_list_invalid', 'At least one exact country scope is required.' );
		}
		$unique = array();
		foreach ( $values as $value ) {
			$value = Tra_Vel_Benefit_Taxonomy::country_scope( $value );
			if ( '' === $value || isset( $unique[ $value ] ) ) {
				return self::error( 'country_scope_list_invalid', 'Country scopes must be exact and unique.' );
			}
			$unique[ $value ] = true;
		}
		$values = array_keys( $unique );
		sort( $values, SORT_STRING );
		return $values;
	}

	private static function exact_false_truth( $truth, $keys ) {
		if ( ! self::exact_object( $truth, $keys ) ) {
			return false;
		}
		foreach ( $keys as $key ) {
			if ( false !== $truth[ $key ] ) {
				return false;
			}
		}
		return true;
	}

	private static function https_url( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > 2048 || false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$parts = parse_url( $value );
		return is_array( $parts ) && isset( $parts['scheme'], $parts['host'] ) && 'https' === strtolower( $parts['scheme'] ) && '' !== $parts['host'] && ! isset( $parts['user'] ) && ! isset( $parts['pass'] );
	}

	private static function exact_slug( $value, $min, $max, $allow_first_digit = false ) {
		if ( ! is_string( $value ) || strlen( $value ) < $min || strlen( $value ) > $max ) {
			return false;
		}
		$pattern = $allow_first_digit ? '/^[a-z0-9][a-z0-9_]+$/' : '/^[a-z][a-z0-9_]+$/';
		return 1 === preg_match( $pattern, $value );
	}

	private static function plain_text( $value, $min, $max ) {
		return is_string( $value ) && $value === trim( $value ) && strlen( $value ) >= $min && strlen( $value ) <= $max && 0 === preg_match( '/[\x00-\x1F\x7F]/u', $value );
	}

	private static function sequential_list( $value, $min, $max ) {
		return is_array( $value ) && array_values( $value ) === $value && count( $value ) >= $min && count( $value ) <= $max;
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_benefit_' . $suffix, $message, array( 'status' => 400 ) );
	}
}
