<?php
/**
 * Closed vocabulary for supplier onboarding and operational readiness.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Supplier_Operations_Taxonomy {
	const VERTICALS = array(
		'flight',
		'accommodation',
		'package',
		'transfer',
		'activity',
		'dining',
		'insurance',
		'connectivity',
		'equipment',
	);

	const CAPABILITIES = array(
		'search',
		'revalidate',
		'reserve',
		'confirm',
		'fulfill',
		'change',
		'cancel',
		'refund',
		'payment_authorize',
		'payment_capture',
		'payment_void',
		'payment_refund',
		'webhook',
		'reconcile',
		'report_conversion',
		'settlement_reconcile',
	);

	const OPERATION_LANES = array(
		'search',
		'reservation',
		'confirmation',
		'change',
		'cancel',
		'refund',
		'fulfillment',
		'webhook',
		'reconciliation',
		'settlement',
	);

	const RELATIONSHIP_MODELS = array( 'owned', 'direct', 'affiliate' );
	const SETTLEMENT_MODELS = array( 'owned', 'commission', 'net_rate', 'affiliate' );
	const CERTIFICATION_STATES = array( 'not_started', 'fixture_passed', 'sandbox_certified', 'live_certified', 'suspended' );
	const ONBOARDING_STATES = array( 'draft', 'commercial_review', 'security_review', 'technical_certification', 'operations_review', 'sandbox_ready', 'sandbox_active', 'live_review', 'live_ready', 'live_active', 'suspended', 'migrating', 'retired', 'disabled' );
	const HEALTH_STATES = array( 'healthy', 'degraded', 'open', 'half_open', 'offline', 'disabled' );
	const REVISION_STATES = array( 'draft', 'certified', 'active', 'superseded', 'rolled_back', 'retired' );
	const OPERATION_STATES = array( 'queued', 'started', 'succeeded', 'failed', 'uncertain', 'reconciled' );
	const SETTLEMENT_STATES = array( 'unreported', 'reported', 'eligible', 'payable', 'paid', 'reversed', 'disputed' );
	const READINESS_GATES = array( 'commercial', 'credentials', 'endpoints', 'certification', 'operations', 'licensing', 'data_governance', 'settlement', 'source_freshness', 'resilience' );

	const CAPABILITY_DEPENDENCIES = array(
		'revalidate'            => array( 'search' ),
		'reserve'               => array( 'revalidate' ),
		'confirm'               => array( 'reserve' ),
		'fulfill'               => array( 'confirm' ),
		'change'                => array( 'confirm' ),
		'cancel'                => array( 'confirm' ),
		'refund'                => array( 'cancel' ),
		'payment_capture'       => array( 'payment_authorize' ),
		'payment_void'          => array( 'payment_authorize' ),
		'payment_refund'        => array( 'payment_capture' ),
		'report_conversion'     => array( 'search' ),
	);

	const LANE_CAPABILITIES = array(
		'search'         => array( 'search', 'revalidate' ),
		'reservation'    => array( 'reserve' ),
		'confirmation'   => array( 'confirm' ),
		'change'         => array( 'change' ),
		'cancel'         => array( 'cancel' ),
		'refund'         => array( 'refund', 'payment_refund' ),
		'fulfillment'    => array( 'fulfill' ),
		'webhook'        => array( 'webhook' ),
		'reconciliation' => array( 'reconcile' ),
		'settlement'     => array( 'report_conversion', 'settlement_reconcile' ),
	);

	/**
	 * Return one canonical token from an allowed list.
	 */
	public static function token( $value, $allowed ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Normalize a unique, sorted list and reject unknown or empty values.
	 *
	 * @return array|WP_Error
	 */
	public static function list_of( $values, $allowed, $label ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ! $values ) {
			return self::error( 'list_invalid', 'At least one canonical ' . $label . ' is required.' );
		}

		$normalized = array();
		foreach ( $values as $value ) {
			$value = self::token( $value, $allowed );
			if ( '' === $value ) {
				return self::error( 'token_invalid', 'An unsupported ' . $label . ' was provided.' );
			}
			$normalized[ $value ] = true;
		}

		$normalized = array_keys( $normalized );
		sort( $normalized, SORT_STRING );
		return $normalized;
	}

	/**
	 * Return the declared capability set for each vertical.
	 *
	 * @return array|WP_Error
	 */
	public static function capability_map( $claims, $declared_verticals ) {
		if ( ! is_array( $claims ) || array_values( $claims ) !== $claims || ! $claims ) {
			return self::error( 'capability_claims_invalid', 'At least one supplier capability claim is required.' );
		}

		$map  = array();
		$seen = array();
		foreach ( $declared_verticals as $vertical ) {
			$map[ $vertical ] = array();
		}

		foreach ( $claims as $claim ) {
			if ( ! is_array( $claim ) || ! isset( $claim['vertical'], $claim['capability'] ) ) {
				return self::error( 'capability_claim_invalid', 'A supplier capability claim is malformed.' );
			}
			$vertical   = self::token( $claim['vertical'], self::VERTICALS );
			$capability = self::token( $claim['capability'], self::CAPABILITIES );
			$key        = $vertical . ':' . $capability;
			if ( '' === $vertical || '' === $capability || ! in_array( $vertical, $declared_verticals, true ) || isset( $seen[ $key ] ) ) {
				return self::error( 'capability_claim_invalid', 'A supplier capability claim is unsupported, undeclared, or duplicated.' );
			}
			$seen[ $key ]        = true;
			$map[ $vertical ][] = $capability;
		}

		foreach ( $map as $vertical => $capabilities ) {
			if ( ! $capabilities ) {
				return self::error( 'vertical_capabilities_missing', 'Every declared supplier vertical requires at least one capability.' );
			}
			$capabilities = array_values( array_unique( $capabilities ) );
			sort( $capabilities, SORT_STRING );
			$map[ $vertical ] = $capabilities;
		}

		ksort( $map, SORT_STRING );
		return $map;
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_supplier_operations_' . $suffix, $message, array( 'status' => 400 ) );
	}
}
