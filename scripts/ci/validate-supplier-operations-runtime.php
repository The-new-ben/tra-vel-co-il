<?php
/**
 * Runtime contract tests for generic supplier onboarding and operational readiness.
 */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code, $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

$commerce = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/';
require_once $commerce . 'class-tra-vel-supplier-operations-taxonomy.php';
require_once $commerce . 'class-tra-vel-supplier-operations-policy.php';
require_once $commerce . 'class-tra-vel-supplier-operations-state-machine.php';

$assertions = 0;

function supplier_operations_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "Supplier operations runtime failed: {$message}\n" );
		exit( 1 );
	}
}

function supplier_operations_error( $value, $code, $message ) {
	supplier_operations_assert( is_wp_error( $value ) && $code === $value->get_error_code(), $message . ( is_wp_error( $value ) ? ' (received ' . $value->get_error_code() . ')' : ' (no error returned)' ) );
}

function supplier_digest( $character ) {
	return str_repeat( $character, 64 );
}

function supplier_operation_lane( $lane, $supported = true ) {
	return array(
		'supported'                      => $supported,
		'contact_route_ref'              => 'route_' . $lane . 'primary',
		'after_hours_route_ref'          => 'route_' . $lane . 'afterhours',
		'acknowledgement_sla_seconds'    => 60,
		'resolution_sla_seconds'         => 3600,
		'reconciliation_sla_seconds'     => 7200,
		'timezone'                       => 'Asia/Jerusalem',
		'holiday_calendar_ref'           => 'calendar_israel2026',
		'evidence_digest'                => supplier_digest( 'a' ),
	);
}

function supplier_claim( $vertical, $capability, $environment ) {
	return array(
		'vertical'             => $vertical,
		'capability'           => $capability,
		'certification_status' => 'live' === $environment ? 'live_certified' : 'sandbox_certified',
		'evidence_digests'     => array( supplier_digest( 'b' ) ),
		'certified_at'         => '2026-07-19T10:00:00Z',
		'expires_at'           => 'live' === $environment ? '2027-07-19T10:00:00Z' : null,
	);
}

function supplier_profile_fixture( $environment = 'sandbox', $relationship = 'direct' ) {
	$verticals = Tra_Vel_Supplier_Operations_Taxonomy::VERTICALS;
	$base_capabilities = array( 'search', 'revalidate', 'reserve', 'confirm', 'fulfill', 'change', 'cancel', 'refund', 'webhook', 'reconcile', 'settlement_reconcile' );
	if ( 'affiliate' === $relationship ) {
		$verticals = array( 'flight' );
		$base_capabilities = array( 'search', 'revalidate', 'webhook', 'reconcile', 'report_conversion', 'settlement_reconcile' );
	}
	$claims = array();
	foreach ( $verticals as $vertical ) {
		$capabilities = $base_capabilities;
		if ( 'package' === $vertical && 'affiliate' !== $relationship ) {
			$capabilities = array_merge( $capabilities, array( 'payment_authorize', 'payment_capture', 'payment_void', 'payment_refund' ) );
		}
		foreach ( $capabilities as $capability ) {
			$claims[] = supplier_claim( $vertical, $capability, $environment );
		}
	}
	$all_capabilities = array_values( array_unique( array_column( $claims, 'capability' ) ) );
	sort( $all_capabilities, SORT_STRING );

	$operation_support = array();
	foreach ( Tra_Vel_Supplier_Operations_Taxonomy::OPERATION_LANES as $lane ) {
		$operation_support[ $lane ] = supplier_operation_lane( $lane );
	}

	$is_live      = 'live' === $environment;
	$is_affiliate = 'affiliate' === $relationship;
	$has_insurance = in_array( 'insurance', $verticals, true );
	$settlement_model = $is_affiliate ? 'affiliate' : 'commission';
	$merchant = $is_affiliate ? 'supplier' : 'platform';
	$profile = array(
		'contract_version'          => '1.0.0',
		'supplier_id'               => $is_affiliate ? 'generic_affiliate_supplier' : 'generic_full_service_supplier',
		'revision_id'               => 'suprev_aaaaaaaaaaaa',
		'revision_number'           => 1,
		'previous_revision_digest'  => null,
		'created_at'                => '2026-07-19T09:00:00Z',
		'effective_at'              => '2026-07-19T09:30:00Z',
		'environment'               => $environment,
		'lifecycle_status'          => $is_live ? 'live_active' : 'sandbox_active',
		'verticals'                 => $verticals,
		'capability_claims'         => $claims,
		'relationship'              => array(
			'model'                    => $relationship,
			'legal_entity_ref'         => 'legal_genericentity',
			'agreement_ref'            => 'agreement_generic2026',
			'agreement_digest'         => supplier_digest( 'c' ),
			'agreement_status'         => 'signed',
			'effective_at'             => '2026-01-01T00:00:00Z',
			'expires_at'               => '2028-01-01T00:00:00Z',
			'governing_jurisdiction'   => 'IL',
			'service_jurisdictions'    => array( 'GLOBAL' ),
			'merchant_of_record'       => $merchant,
		),
		'credentials'               => array(
			array(
				'credential_ref'  => 'credref_genericcredential',
				'environment'     => $environment,
				'status'          => 'configured',
				'scopes'          => $all_capabilities,
				'issued_at'       => '2026-06-01T00:00:00Z',
				'expires_at'      => '2027-06-01T00:00:00Z',
				'last_rotated_at' => '2026-07-01T00:00:00Z',
				'evidence_digest' => supplier_digest( 'd' ),
			),
		),
		'endpoints'                 => array(
			'environment'                 => $environment,
			'allowed_hosts'               => array( $is_live ? 'api.generic-supplier.invalid' : 'sandbox.generic-supplier.invalid' ),
			'tls_required'                => true,
			'redirect_policy'              => 'deny',
			'webhook_source_hosts'         => array( $is_live ? 'events.generic-supplier.invalid' : 'sandbox-events.generic-supplier.invalid' ),
			'certificate_evidence_digest' => supplier_digest( 'e' ),
			'last_verified_at'             => '2026-07-19T11:30:00Z',
		),
		'operation_support'         => $operation_support,
		'escalation'                => array(
			'primary_route_ref'       => 'route_primaryoperations',
			'after_hours_route_ref'   => 'route_afterhoursoperations',
			'duty_manager_route_ref'  => 'route_dutymanageroperations',
			'coverage_model'          => 'business_hours_with_on_call',
			'timezone'                => 'Asia/Jerusalem',
			'holiday_calendar_ref'    => 'calendar_israel2026',
			'steps'                   => array(
				array( 'sequence' => 1, 'delay_seconds' => 0, 'route_ref' => 'route_primaryoperations', 'scope' => 'operational' ),
				array( 'sequence' => 2, 'delay_seconds' => 300, 'route_ref' => 'route_afterhoursoperations', 'scope' => 'operational' ),
				array( 'sequence' => 3, 'delay_seconds' => 900, 'route_ref' => 'route_dutymanageroperations', 'scope' => 'financial' ),
			),
			'last_drill_at'           => '2026-07-18T08:00:00Z',
			'drill_evidence_digest'   => supplier_digest( 'f' ),
		),
		'licensing'                => $has_insurance ? array(
			'status'                   => $is_live ? 'verified' : 'sandbox_only',
			'jurisdictions'            => array( 'IL' ),
			'licence_reference_digest'=> supplier_digest( '1' ),
			'verified_at'              => '2026-06-01T00:00:00Z',
			'expires_at'               => '2027-06-01T00:00:00Z',
			'regulated_contact_ref'    => 'route_regulatedinsurance',
		) : array(
			'status'                    => 'not_required',
			'jurisdictions'             => array(),
			'licence_reference_digest' => null,
			'verified_at'               => null,
			'expires_at'                => null,
			'regulated_contact_ref'     => null,
		),
		'data_governance'           => array(
			'retention_policy_ref'            => 'policy_supplierretention',
			'retention_policy_digest'         => supplier_digest( '2' ),
			'retention_classes'               => array(
				array( 'data_class' => 'operations', 'retention_days' => 730, 'purpose_ref' => 'purpose_serviceoperations' ),
				array( 'data_class' => 'financial', 'retention_days' => 2555, 'purpose_ref' => 'purpose_financialrecords' ),
				array( 'data_class' => 'security_audit', 'retention_days' => 365, 'purpose_ref' => 'purpose_securityaudit' ),
			),
			'minimum_necessary_enforced'      => true,
			'log_redaction_enforced'          => true,
			'data_residency_jurisdictions'    => array( 'IL' ),
			'deletion_sla_days'               => 30,
			'security_review_evidence_digest' => supplier_digest( '3' ),
		),
		'attribution'               => array(
			'mode'                           => $is_affiliate ? 'conversion' : 'none',
			'click_reference_required'       => $is_affiliate,
			'conversion_reference_required'  => $is_affiliate,
			'attribution_window_days'        => $is_affiliate ? 30 : 0,
			'conversion_reporting_sla_hours' => 24,
			'reversal_supported'             => $is_affiliate,
			'evidence_digest'                => supplier_digest( '4' ),
		),
		'settlement'                => array(
			'model'                    => $settlement_model,
			'currency'                 => 'ILS',
			'gross_basis'              => $is_affiliate ? 'commissionable_gross' : 'retail_gross',
			'commission_bps'           => 900,
			'markup_authority'         => 'contract',
			'invoice_party'            => $is_affiliate ? 'affiliate_network' : 'supplier',
			'customer_funds_owner'     => $is_affiliate ? 'supplier' : 'platform',
			'supplier_payable_method'  => $is_affiliate ? 'affiliate_invoice' : 'gross_less_commission',
			'payout_route_ref'         => 'payout_supplierledger',
			'payout_lag_days'          => 30,
			'reconciliation_frequency'=> 'daily',
			'dispute_sla_hours'        => 72,
			'chargeback_owner'         => $is_affiliate ? 'supplier' : 'shared',
			'tax_owner'                => $is_affiliate ? 'supplier' : 'shared',
			'evidence_digest'          => supplier_digest( '5' ),
		),
		'source_controls'           => array(
			'catalog_mode'                 => 'hybrid',
			'product_revision_digest'      => supplier_digest( '6' ),
			'rate_revision_digest'         => supplier_digest( '7' ),
			'availability_revision_digest' => supplier_digest( '8' ),
			'terms_revision_digest'        => supplier_digest( '9' ),
			'blackout_revision_digest'     => supplier_digest( 'a' ),
			'last_verified_at'             => '2026-07-19T11:59:00Z',
			'terms_valid_until'            => '2027-07-19T12:00:00Z',
			'max_cache_age_seconds'        => 3600,
			'revalidation_required'        => true,
			'source_evidence_digest'       => supplier_digest( 'b' ),
		),
		'health'                    => array(
			'state'                     => 'healthy',
			'failure_threshold'         => 5,
			'open_interval_seconds'     => 300,
			'half_open_probe_limit'     => 2,
			'last_probe_at'             => '2026-07-19T11:59:00Z',
			'last_success_at'           => '2026-07-19T11:59:00Z',
			'telemetry_evidence_digest' => supplier_digest( 'c' ),
		),
		'kill_switch'               => array(
			'state'                => 'armed',
			'blocked_capabilities' => array(),
			'reason_code'          => null,
			'activated_at'         => null,
			'activated_by_ref'     => null,
		),
		'readiness'                 => array(
			'requested_mode'   => $environment,
			'decision'         => $is_live ? 'live_ready' : 'sandbox_ready',
			'gates'            => array(
				'commercial'       => 'pass',
				'credentials'      => 'pass',
				'endpoints'        => 'pass',
				'certification'    => 'pass',
				'operations'       => 'pass',
				'licensing'        => $has_insurance ? 'pass' : 'not_applicable',
				'data_governance'  => 'pass',
				'settlement'       => 'pass',
				'source_freshness' => 'pass',
				'resilience'       => 'pass',
			),
			'evidence_digests' => array( supplier_digest( 'd' ) ),
			'decided_at'       => '2026-07-19T11:50:00Z',
		),
		'commercial_truth'          => array( 'simulated' => ! $is_live, 'real_booking' => false, 'real_charge' => false ),
		'revision_control'          => array(
			'immutable'                   => true,
			'content_digest'              => supplier_digest( 'e' ),
			'supersedes_revision_digest' => null,
			'rollback_target_digest'      => null,
			'rollback_reason_code'        => null,
		),
	);
	$profile['revision_control']['content_digest'] = Tra_Vel_Supplier_Operations_Policy::configuration_digest( $profile );
	return $profile;
}

function supplier_rebind_profile_configuration( $profile ) {
	$profile['revision_control']['content_digest'] = Tra_Vel_Supplier_Operations_Policy::configuration_digest( $profile );
	return $profile;
}

function supplier_inventory_fixture() {
	return array(
		'contract_version'         => '1.0.0',
		'supplier_id'              => 'generic_full_service_supplier',
		'vertical'                 => 'flight',
		'revision_id'              => 'invrev_aaaaaaaaaaaa',
		'revision_number'          => 1,
		'previous_revision_digest' => null,
		'state'                    => 'active',
		'environment'              => 'sandbox',
		'created_at'               => '2026-07-19T10:00:00Z',
		'effective_at'             => '2026-07-19T10:05:00Z',
		'valid_until'              => '2027-07-19T12:00:00Z',
		'source'                   => array(
			'source_revision' => 'source_feedrevision01',
			'source_digest'   => supplier_digest( '1' ),
			'captured_at'     => '2026-07-19T09:59:00Z',
			'channel'         => 'feed',
		),
		'artifacts'                => array(
			array(
				'product_ref'          => 'product_flightoffer01',
				'product_revision'     => 'productrev_revision01',
				'product_digest'       => supplier_digest( '2' ),
				'rate_revision'        => 'raterev_revision01',
				'rate_digest'          => supplier_digest( '3' ),
				'availability_revision'=> 'availrev_revision01',
				'availability_digest'  => supplier_digest( '4' ),
				'terms_revision'       => 'termsrev_revision01',
				'terms_digest'         => supplier_digest( '5' ),
				'terms_effective_at'   => '2026-07-19T10:00:00Z',
				'terms_valid_until'    => '2027-07-19T12:00:00Z',
				'blackout_revision'    => 'blackoutrev_revision01',
				'blackout_digest'      => supplier_digest( '6' ),
			),
		),
		'revalidation'            => array(
			'required'              => true,
			'max_cache_age_seconds' => 3600,
			'last_verified_at'      => '2026-07-19T11:59:00Z',
			'next_refresh_at'       => '2026-07-19T12:30:00Z',
			'evidence_digest'       => supplier_digest( '7' ),
		),
		'rollback'                => array( 'allowed' => false, 'target_revision_digest' => null, 'reason_code' => null, 'requested_at' => null ),
		'commercial_truth'        => array( 'simulated' => true, 'real_booking' => false, 'real_charge' => false ),
		'data_boundary'           => array( 'contains_raw_secrets' => false, 'contains_raw_pii' => false, 'restricted_payload_refs_only' => true ),
	);
}

$clock = '2026-07-19T12:00:00Z';
$profile = supplier_profile_fixture();
$validated = Tra_Vel_Supplier_Operations_Policy::supplier_profile( $profile, $clock );
supplier_operations_assert( is_array( $validated ), 'the generic all-vertical sandbox supplier profile must validate' . ( is_wp_error( $validated ) ? ' (received ' . $validated->get_error_code() . ')' : '' ) );
$expected_profile_verticals = Tra_Vel_Supplier_Operations_Taxonomy::VERTICALS;
$actual_profile_verticals   = $validated['verticals'];
sort( $expected_profile_verticals, SORT_STRING );
sort( $actual_profile_verticals, SORT_STRING );
supplier_operations_assert( $expected_profile_verticals === $actual_profile_verticals, 'the profile must preserve all nine canonical verticals' );

$tampered_configuration = $profile;
$tampered_configuration['settlement']['commission_bps']++;
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $tampered_configuration, $clock ), 'tra_vel_supplier_operations_revision_content_digest_mismatch', 'an immutable commercial change with the old configuration digest must fail' );

$future_profile = $profile;
$future_profile['created_at'] = '2026-07-19T12:01:00Z';
$future_profile['effective_at'] = '2026-07-19T12:02:00Z';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $future_profile, $clock ), 'tra_vel_supplier_operations_profile_revision_invalid', 'an active supplier revision cannot be created or become effective in the future' );

$future_certification = $profile;
$future_certification['capability_claims'][0]['certified_at'] = '2026-07-19T12:01:00Z';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $future_certification, $clock ), 'tra_vel_supplier_operations_capability_evidence_missing', 'future-dated capability certification cannot authorize execution' );

$expired_sandbox_certification = $profile;
$expired_sandbox_certification['capability_claims'][0]['expires_at'] = '2026-07-19T11:59:59Z';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $expired_sandbox_certification, $clock ), 'tra_vel_supplier_operations_capability_evidence_missing', 'an expired sandbox certification must fail just like an expired live certification' );

$future_credential = $profile;
$future_credential['credentials'][0]['issued_at'] = '2026-07-19T12:01:00Z';
$future_credential['credentials'][0]['last_rotated_at'] = '2026-07-19T12:02:00Z';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $future_credential, $clock ), 'tra_vel_supplier_operations_credential_evidence_invalid', 'future-issued credentials cannot satisfy supplier execution readiness' );

$future_endpoint = $profile;
$future_endpoint['endpoints']['last_verified_at'] = '2026-07-19T12:01:00Z';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $future_endpoint, $clock ), 'tra_vel_supplier_operations_endpoint_verification_future', 'future endpoint verification evidence must fail closed in sandbox and live modes' );

$future_agreement = $profile;
$future_agreement['relationship']['effective_at'] = '2026-07-19T12:01:00Z';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $future_agreement, $clock ), 'tra_vel_supplier_operations_relationship_not_current', 'a future-effective supplier agreement cannot support an active relationship' );

$profile_successor = $profile;
$profile_successor['revision_id'] = 'suprev_bbbbbbbbbbbb';
$profile_successor['revision_number'] = 2;
$profile_successor['previous_revision_digest'] = $profile['revision_control']['content_digest'];
$profile_successor['created_at'] = '2026-07-19T11:51:00Z';
$profile_successor['effective_at'] = '2026-07-19T11:52:00Z';
$profile_successor['revision_control']['supersedes_revision_digest'] = $profile['revision_control']['content_digest'];
$profile_successor = supplier_rebind_profile_configuration( $profile_successor );
supplier_operations_assert( true === Tra_Vel_Supplier_Operations_State_Machine::assert_profile_successor( $profile, $profile_successor, $clock ), 'a profile successor must increment once and bind the exact prior immutable configuration digest' );
$wrong_profile_successor = $profile_successor;
$wrong_profile_successor['previous_revision_digest'] = supplier_digest( 'f' );
$wrong_profile_successor['revision_control']['supersedes_revision_digest'] = supplier_digest( 'f' );
$wrong_profile_successor = supplier_rebind_profile_configuration( $wrong_profile_successor );
supplier_operations_error( Tra_Vel_Supplier_Operations_State_Machine::assert_profile_successor( $profile, $wrong_profile_successor, $clock ), 'tra_vel_supplier_operations_profile_successor_invalid', 'a profile successor cannot bind a different predecessor configuration' );

$covered_verticals = array();
$covered_capabilities = array();
foreach ( $validated['capability_claims'] as $claim ) {
	$covered_verticals[ $claim['vertical'] ] = true;
	$covered_capabilities[ $claim['capability'] ] = true;
}
foreach ( Tra_Vel_Supplier_Operations_Taxonomy::VERTICALS as $vertical ) {
	supplier_operations_assert( isset( $covered_verticals[ $vertical ] ), "the onboarding contract must cover {$vertical}" );
}
foreach ( array_diff( Tra_Vel_Supplier_Operations_Taxonomy::CAPABILITIES, array( 'report_conversion' ) ) as $capability ) {
	supplier_operations_assert( isset( $covered_capabilities[ $capability ] ), "the direct supplier contract must demonstrate {$capability}" );
}

$affiliate = supplier_profile_fixture( 'sandbox', 'affiliate' );
$validated_affiliate = Tra_Vel_Supplier_Operations_Policy::supplier_profile( $affiliate, $clock );
supplier_operations_assert( is_array( $validated_affiliate ) && isset( array_flip( array_column( $validated_affiliate['capability_claims'], 'capability' ) )['report_conversion'] ), 'the separate affiliate contract must cover conversion reporting without transaction claims' );
$combined_capabilities = array_unique( array_merge( array_column( $validated['capability_claims'], 'capability' ), array_column( $validated_affiliate['capability_claims'], 'capability' ) ) );
sort( $combined_capabilities, SORT_STRING );
$expected_capabilities = Tra_Vel_Supplier_Operations_Taxonomy::CAPABILITIES;
sort( $expected_capabilities, SORT_STRING );
supplier_operations_assert( $expected_capabilities === $combined_capabilities, 'direct and affiliate contracts together must demonstrate all 16 canonical capabilities' );

$synthetic_live = supplier_profile_fixture( 'live' );
$validated_live = Tra_Vel_Supplier_Operations_Policy::supplier_profile( $synthetic_live, $clock );
supplier_operations_assert( is_array( $validated_live ) && false === $validated_live['commercial_truth']['real_booking'] && false === $validated_live['commercial_truth']['real_charge'], 'a generic evidence-complete live-readiness contract may validate without claiming any real booking or charge' );

$net_rate = $profile;
$net_rate['settlement']['model'] = 'net_rate';
$net_rate['settlement']['gross_basis'] = 'supplier_net';
$net_rate['settlement']['commission_bps'] = null;
$net_rate['settlement']['markup_authority'] = 'platform';
$net_rate['settlement']['supplier_payable_method'] = 'net_rate';
$net_rate = supplier_rebind_profile_configuration( $net_rate );
supplier_operations_assert( is_array( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $net_rate, $clock ) ), 'a complete direct net-rate settlement contract must validate' );

$owned = $profile;
$owned['relationship']['model'] = 'owned';
$owned['attribution']['mode'] = 'owned';
$owned['settlement']['model'] = 'owned';
$owned['settlement']['gross_basis'] = 'not_applicable';
$owned['settlement']['commission_bps'] = null;
$owned['settlement']['markup_authority'] = 'not_applicable';
$owned['settlement']['invoice_party'] = 'not_applicable';
$owned['settlement']['supplier_payable_method'] = 'internal';
$owned['settlement']['chargeback_owner'] = 'platform';
$owned['settlement']['tax_owner'] = 'platform';
$owned = supplier_rebind_profile_configuration( $owned );
supplier_operations_assert( is_array( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $owned, $clock ) ), 'a complete owned settlement contract must validate without fictional commission economics' );

$missing_dependency = $profile;
$missing_dependency['capability_claims'] = array_values( array_filter( $missing_dependency['capability_claims'], function ( $claim ) { return ! ( 'flight' === $claim['vertical'] && 'revalidate' === $claim['capability'] ); } ) );
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $missing_dependency, $clock ), 'tra_vel_supplier_operations_capability_dependency_missing', 'reserve must not be claimed without revalidation' );

$impossible_vertical = $profile;
$impossible_vertical['capability_claims'][] = supplier_claim( 'flight', 'payment_authorize', 'sandbox' );
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $impossible_vertical, $clock ), 'tra_vel_supplier_operations_capability_vertical_impossible', 'a flight supplier cannot claim platform payment orchestration' );

$impossible_affiliate = $affiliate;
foreach ( array( 'reserve', 'confirm' ) as $capability ) {
	$impossible_affiliate['capability_claims'][] = supplier_claim( 'flight', $capability, 'sandbox' );
}
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $impossible_affiliate, $clock ), 'tra_vel_supplier_operations_capability_relationship_impossible', 'an affiliate mutation chain must fail before execution' );

$missing_live_credentials = supplier_profile_fixture( 'live' );
$missing_live_credentials['credentials'] = array();
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $missing_live_credentials, $clock ), 'tra_vel_supplier_operations_live_credentials_incomplete', 'live readiness must fail without scoped credential evidence' );

$missing_live_endpoint_evidence = supplier_profile_fixture( 'live' );
$missing_live_endpoint_evidence['endpoints']['certificate_evidence_digest'] = null;
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $missing_live_endpoint_evidence, $clock ), 'tra_vel_supplier_operations_live_endpoint_evidence_missing', 'live readiness must fail without endpoint and certificate evidence' );

$missing_live_certification = supplier_profile_fixture( 'live' );
$missing_live_certification['capability_claims'][0]['certification_status'] = 'sandbox_certified';
$missing_live_certification['capability_claims'][0]['expires_at'] = null;
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $missing_live_certification, $clock ), 'tra_vel_supplier_operations_live_certification_incomplete', 'live readiness must fail when one vertical capability lacks live certification' );

$missing_live_licence = supplier_profile_fixture( 'live' );
$missing_live_licence['licensing'] = array( 'status' => 'sandbox_only', 'jurisdictions' => array( 'IL' ), 'licence_reference_digest' => null, 'verified_at' => null, 'expires_at' => null, 'regulated_contact_ref' => null );
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $missing_live_licence, $clock ), 'tra_vel_supplier_operations_insurance_licence_missing', 'live insurance readiness must fail without current licensing evidence' );

$missing_after_hours = $profile;
$missing_after_hours['operation_support']['confirmation']['after_hours_route_ref'] = null;
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $missing_after_hours, $clock ), 'tra_vel_supplier_operations_operation_support_invalid', 'every confirmation path requires an after-hours escalation route' );

$stale_terms = $profile;
$stale_terms['source_controls']['terms_valid_until'] = '2026-07-19T11:59:59Z';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $stale_terms, $clock ), 'tra_vel_supplier_operations_source_terms_stale', 'stale supplier terms must block readiness' );

$stale_source = $profile;
$stale_source['source_controls']['last_verified_at'] = '2026-07-19T09:00:00Z';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $stale_source, $clock ), 'tra_vel_supplier_operations_source_terms_stale', 'source evidence beyond its cache boundary must fail closed' );

$ambiguous_settlement = $profile;
$ambiguous_settlement['settlement']['commission_bps'] = null;
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $ambiguous_settlement, $clock ), 'tra_vel_supplier_operations_settlement_model_ambiguous', 'a commission relationship must define commission economics' );

$wrong_funds_owner = $profile;
$wrong_funds_owner['settlement']['customer_funds_owner'] = 'supplier';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $wrong_funds_owner, $clock ), 'tra_vel_supplier_operations_settlement_model_ambiguous', 'merchant and customer-funds ownership cannot conflict' );

$raw_secret = $profile;
$raw_secret['credentials'][0]['api_key'] = 'sk-this-must-never-enter-the-contract';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $raw_secret, $clock ), 'tra_vel_supplier_operations_sensitive_material_rejected', 'raw API secrets must be rejected before shape validation' );

$raw_contact = $profile;
$raw_contact['escalation']['after_hours_route_ref'] = 'ops@example.com';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $raw_contact, $clock ), 'tra_vel_supplier_operations_sensitive_material_rejected', 'direct personal contact data must be replaced by an opaque route reference' );

$raw_pii = $profile;
$raw_pii['traveler_name'] = 'Example Traveler';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $raw_pii, $clock ), 'tra_vel_supplier_operations_sensitive_material_rejected', 'traveler PII must never enter a supplier onboarding profile' );

$kill_switch = $profile;
$kill_switch['kill_switch'] = array( 'state' => 'engaged', 'blocked_capabilities' => array( 'confirm' ), 'reason_code' => 'supplier_outage', 'activated_at' => '2026-07-19T11:55:00Z', 'activated_by_ref' => 'actor_dutyoperator01' );
supplier_operations_assert( is_array( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $kill_switch, $clock ) ), 'a runtime kill switch may change without rewriting immutable supplier configuration' );

$gate_failure = $profile;
$gate_failure['readiness']['gates']['operations'] = 'fail';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $gate_failure, $clock ), 'tra_vel_supplier_operations_readiness_gate_failed', 'a failed operations gate must prevent readiness' );

$inventory = supplier_inventory_fixture();
$validated_inventory = Tra_Vel_Supplier_Operations_Policy::inventory_revision( $inventory, $clock );
supplier_operations_assert( is_array( $validated_inventory ), 'the immutable inventory revision must validate' );

$stale_inventory_terms = $inventory;
$stale_inventory_terms['artifacts'][0]['terms_valid_until'] = '2026-07-19T11:00:00Z';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::inventory_revision( $stale_inventory_terms, $clock ), 'tra_vel_supplier_operations_inventory_terms_stale', 'active inventory cannot bind expired terms' );

$stale_inventory_refresh = $inventory;
$stale_inventory_refresh['revalidation']['next_refresh_at'] = '2026-07-19T11:59:59Z';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::inventory_revision( $stale_inventory_refresh, $clock ), 'tra_vel_supplier_operations_inventory_revalidation_invalid', 'an overdue inventory refresh must fail readiness' );

$invalid_rollback = $inventory;
$invalid_rollback['rollback'] = array( 'allowed' => true, 'target_revision_digest' => supplier_digest( '8' ), 'reason_code' => null, 'requested_at' => '2026-07-19T11:00:00Z' );
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::inventory_revision( $invalid_rollback, $clock ), 'tra_vel_supplier_operations_inventory_rollback_invalid', 'rollback requires a target, reason, and request time together' );

$inventory_pii = $inventory;
$inventory_pii['support_email'] = 'support@example.com';
supplier_operations_error( Tra_Vel_Supplier_Operations_Policy::inventory_revision( $inventory_pii, $clock ), 'tra_vel_supplier_operations_inventory_sensitive_material_rejected', 'inventory revisions must reject direct personal data' );

$candidate = $inventory;
$candidate['revision_id'] = 'invrev_bbbbbbbbbbbb';
$candidate['revision_number'] = 2;
$candidate['previous_revision_digest'] = Tra_Vel_Supplier_Operations_Policy::canonical_digest( $inventory );
$candidate['state'] = 'draft';
$candidate['created_at'] = '2026-07-19T12:01:00Z';
$candidate['effective_at'] = '2026-07-19T12:05:00Z';
$candidate['source']['captured_at'] = '2026-07-19T12:00:30Z';
supplier_operations_assert( true === Tra_Vel_Supplier_Operations_State_Machine::assert_revision_successor( $inventory, $candidate, $clock ), 'a successor must bind the exact prior canonical digest and increment once' );
$wrong_successor = $candidate;
$wrong_successor['previous_revision_digest'] = supplier_digest( 'f' );
supplier_operations_error( Tra_Vel_Supplier_Operations_State_Machine::assert_revision_successor( $inventory, $wrong_successor, $clock ), 'tra_vel_supplier_operations_revision_successor_invalid', 'a successor with a different predecessor digest must fail' );

$valid_transitions = array(
	array( 'onboarding', 'draft', 'submit', 'commercial_review' ),
	array( 'onboarding', 'live_active', 'begin_migration', 'migrating' ),
	array( 'onboarding', 'migrating', 'rollback_migration', 'live_active' ),
	array( 'health', 'healthy', 'trip', 'open' ),
	array( 'health', 'open', 'cooldown_elapsed', 'half_open' ),
	array( 'health', 'half_open', 'probe_success', 'healthy' ),
	array( 'revision', 'active', 'supersede', 'superseded' ),
	array( 'revision', 'superseded', 'restore', 'active' ),
	array( 'operation', 'started', 'timeout', 'uncertain' ),
	array( 'operation', 'uncertain', 'reconcile_success', 'reconciled' ),
	array( 'settlement', 'paid', 'dispute', 'disputed' ),
	array( 'settlement', 'disputed', 'resolve_payable', 'payable' ),
);
foreach ( $valid_transitions as $transition ) {
	supplier_operations_assert( $transition[3] === Tra_Vel_Supplier_Operations_State_Machine::transition( $transition[0], $transition[1], $transition[2] ), "{$transition[0]} transition {$transition[1]} -> {$transition[3]} must be deterministic" );
}
supplier_operations_error( Tra_Vel_Supplier_Operations_State_Machine::transition( 'operation', 'uncertain', 'retry' ), 'tra_vel_supplier_operations_transition_invalid', 'uncertain provider mutations must reconcile rather than blindly retry' );
supplier_operations_error( Tra_Vel_Supplier_Operations_State_Machine::transition( 'onboarding', 'draft', 'activate_live' ), 'tra_vel_supplier_operations_transition_invalid', 'onboarding cannot skip commercial, security, certification, and operations review' );

supplier_operations_assert( true === Tra_Vel_Supplier_Operations_State_Machine::can_execute( $validated, 'flight', 'search', $clock ), 'an active sandbox-certified capability may execute' );
$open_profile = $validated;
$open_profile['health']['state'] = 'open';
supplier_operations_error( Tra_Vel_Supplier_Operations_State_Machine::can_execute( $open_profile, 'flight', 'search', $clock ), 'tra_vel_supplier_operations_execution_circuit_open', 'an open circuit must block provider execution' );
$degraded_profile = $validated;
$degraded_profile['health']['state'] = 'degraded';
supplier_operations_assert( true === Tra_Vel_Supplier_Operations_State_Machine::can_execute( $degraded_profile, 'flight', 'search', $clock ), 'degraded health may retain read-only discovery when its evidence is current' );
supplier_operations_error( Tra_Vel_Supplier_Operations_State_Machine::can_execute( $degraded_profile, 'flight', 'confirm', $clock ), 'tra_vel_supplier_operations_execution_degraded_mutation_blocked', 'degraded health must block consequential supplier mutations' );
$killed_profile = $validated;
$killed_profile['kill_switch'] = array( 'state' => 'engaged', 'blocked_capabilities' => array( 'confirm' ), 'reason_code' => 'supplier_outage', 'activated_at' => '2026-07-19T11:55:00Z', 'activated_by_ref' => 'actor_dutyoperator01' );
supplier_operations_error( Tra_Vel_Supplier_Operations_State_Machine::can_execute( $killed_profile, 'flight', 'confirm', $clock ), 'tra_vel_supplier_operations_execution_killed', 'a scoped kill switch must block the selected mutation' );
supplier_operations_error( Tra_Vel_Supplier_Operations_State_Machine::can_execute( $validated, 'flight', 'payment_capture', $clock ), 'tra_vel_supplier_operations_execution_capability_missing', 'execution must fail for a capability not declared on that vertical' );
$execution_tamper = $validated;
$execution_tamper['settlement']['commission_bps']++;
supplier_operations_error( Tra_Vel_Supplier_Operations_State_Machine::can_execute( $execution_tamper, 'flight', 'search', $clock ), 'tra_vel_supplier_operations_execution_profile_invalid', 'execution must revalidate the immutable configuration digest at the action clock' );
$execution_expired_certification = $validated;
$execution_expired_certification['capability_claims'][0]['expires_at'] = '2026-07-19T11:59:59Z';
supplier_operations_error( Tra_Vel_Supplier_Operations_State_Machine::can_execute( $execution_expired_certification, 'flight', 'search', $clock ), 'tra_vel_supplier_operations_execution_profile_invalid', 'execution must revalidate certification expiry at the action clock' );

$stress_path = __DIR__ . '/../../plugin/tra-vel-agent-core/assets/fixtures/commerce-sandbox/supplier-operations-stress-matrix.json';
$stress = json_decode( file_get_contents( $stress_path ), true );
supplier_operations_assert( is_array( $stress ) && JSON_ERROR_NONE === json_last_error(), 'the deterministic supplier stress matrix must be valid JSON' );
supplier_operations_assert( array( 'contract_version', 'environment', 'fixture_id', 'clock_started_at', 'scenarios' ) === array_keys( $stress ), 'the supplier stress fixture envelope must remain closed and ordered' );
supplier_operations_assert( '1.0.0' === $stress['contract_version'] && 'sandbox' === $stress['environment'] && 8 === count( $stress['scenarios'] ), 'the stress matrix must stay sandbox-only and cover exactly the eight required families' );

$expected_injections = array( 'supplier_outage', 'partial_confirmation', 'duplicate_late_webhook', 'credential_rotation', 'terms_revision_mid_checkout', 'refund_mismatch', 'settlement_dispute', 'provider_acquisition_migration' );
$scenario_keys = array( 'scenario_id', 'seed', 'injected_at', 'injection', 'provider_script', 'initial_states', 'expected_states', 'expected_actions', 'invariants', 'customer_projection' );
$state_keys = array( 'onboarding', 'health', 'operation', 'settlement', 'revision' );
$seen_injections = array();
$seen_ids = array();
$seen_seeds = array();
foreach ( $stress['scenarios'] as $scenario ) {
	supplier_operations_assert( $scenario_keys === array_keys( $scenario ), 'every supplier stress scenario must use the exact closed shape' );
	supplier_operations_assert( ! isset( $seen_ids[ $scenario['scenario_id'] ] ) && ! isset( $seen_seeds[ $scenario['seed'] ] ), 'scenario IDs and deterministic seeds must be unique' );
	$seen_ids[ $scenario['scenario_id'] ] = true;
	$seen_seeds[ $scenario['seed'] ] = true;
	$seen_injections[ $scenario['injection'] ] = true;
	supplier_operations_assert( $state_keys === array_keys( $scenario['initial_states'] ) && $state_keys === array_keys( $scenario['expected_states'] ), 'every scenario must preserve all five independent state axes' );
	$last_offset = -1;
	foreach ( $scenario['provider_script'] as $index => $step ) {
		supplier_operations_assert( array( 'sequence', 'at_offset_seconds', 'event', 'outcome' ) === array_keys( $step ) && $step['sequence'] === $index + 1 && $step['at_offset_seconds'] >= $last_offset, 'provider scripts must be ordered against the deterministic fixture clock' );
		$last_offset = $step['at_offset_seconds'];
	}
	supplier_operations_assert( count( $scenario['expected_actions'] ) >= 2 && count( $scenario['invariants'] ) >= 2, 'every stress injection requires explicit actions and invariants' );
}
sort( $expected_injections, SORT_STRING );
$actual_injections = array_keys( $seen_injections );
sort( $actual_injections, SORT_STRING );
supplier_operations_assert( $expected_injections === $actual_injections, 'the stress matrix must cover outage, partial confirmation, webhook, rotation, terms, refund, settlement, and migration' );

$outage = $stress['scenarios'][0];
supplier_operations_assert( in_array( 'open_circuit', $outage['expected_actions'], true ) && in_array( 'route_after_hours', $outage['expected_actions'], true ) && 'uncertain' === $outage['expected_states']['operation'], 'supplier outage must open the circuit, route after hours, and preserve uncertainty' );
$webhook = $stress['scenarios'][2];
supplier_operations_assert( in_array( 'deduplicate_event', $webhook['expected_actions'], true ) && in_array( 'quarantine_late_event', $webhook['expected_actions'], true ), 'duplicate and late webhooks must be handled separately' );
$terms = $stress['scenarios'][4];
supplier_operations_assert( in_array( 'expire_decision', $terms['expected_actions'], true ) && in_array( 'require_reapproval', $terms['expected_actions'], true ) && in_array( 'decision_binds_terms_revision', $terms['invariants'], true ), 'mid-checkout terms changes must expire the old decision and require a newly bound approval' );
$migration = $stress['scenarios'][7];
supplier_operations_assert( in_array( 'dual_read_compare', $migration['expected_actions'], true ) && in_array( 'rollback_revision', $migration['expected_actions'], true ) && in_array( 'migration_keeps_reference_map', $migration['invariants'], true ), 'provider acquisition must preserve reference mapping, dual-read verification, and rollback' );

echo "Supplier operations runtime passed ({$assertions} assertions; 9 verticals; 16 capabilities; 8 deterministic stress families).\n";
