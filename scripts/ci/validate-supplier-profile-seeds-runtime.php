<?php
/**
 * Runtime validation for the one-to-one sandbox supplier operations profile seeds.
 */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message = '', $data = null ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/[\x00-\x1F\x7F]/u', '', (string) $value ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

$commerce = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/';
require_once $commerce . 'class-tra-vel-commerce-taxonomy.php';
require_once $commerce . 'class-tra-vel-commerce-money.php';
require_once $commerce . 'class-tra-vel-commerce-policy.php';
require_once $commerce . 'class-tra-vel-commerce-sandbox-network.php';
require_once $commerce . 'class-tra-vel-supplier-operations-taxonomy.php';
require_once $commerce . 'class-tra-vel-supplier-operations-policy.php';
require_once $commerce . 'class-tra-vel-supplier-operations-state-machine.php';

$assertions = 0;

function supplier_seed_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "Supplier profile seed runtime failed: {$message}\n" );
		exit( 1 );
	}
}

function supplier_seed_error( $value, $code, $message ) {
	supplier_seed_assert(
		is_wp_error( $value ) && $code === $value->get_error_code(),
		$message . ( is_wp_error( $value ) ? ' (received ' . $value->get_error_code() . ')' : ' (no error returned)' )
	);
}

function supplier_seed_sorted( $values ) {
	$values = array_values( array_unique( $values ) );
	sort( $values, SORT_STRING );
	return $values;
}

function supplier_seed_exact_object( $value, $keys ) {
	return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
}

function supplier_seed_contains_sensitive( $value, $key = '' ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $child_key => $child ) {
			$child_key = (string) $child_key;
			if ( preg_match( '/(?:^|_)(?:api_?key|secret|password|bearer|access_?token|refresh_?token|private_?key|cvv|cvc|card_?number|passport|medical|email|phone|traveler_?name|full_?name)(?:$|_)/i', $child_key ) || supplier_seed_contains_sensitive( $child, $child_key ) ) {
				return true;
			}
		}
		return false;
	}
	if ( ! is_string( $value ) ) {
		return false;
	}
	if ( preg_match( '/-----BEGIN [A-Z ]*PRIVATE KEY-----|\bBearer\s+[A-Za-z0-9._~+\/=\-]{8,}|\bsk-[A-Za-z0-9_-]{12,}/i', $value ) || preg_match( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value ) ) {
		return true;
	}
	$digits = preg_replace( '/\D+/', '', $value );
	return is_string( $digits ) && preg_match( '/^\+?[0-9 ()\-]{8,20}$/', $value ) && strlen( $digits ) >= 8;
}

function supplier_seed_is_invalid_host( $host ) {
	return is_string( $host ) && strlen( $host ) > 8 && '.invalid' === substr( $host, -8 );
}

function supplier_seed_profile_capabilities( $profile ) {
	$capabilities = array();
	foreach ( $profile['capability_claims'] as $claim ) {
		$capabilities[] = $claim['capability'];
	}
	return supplier_seed_sorted( $capabilities );
}

function supplier_seed_profile_vertical_capabilities( $profile, $vertical ) {
	$capabilities = array();
	foreach ( $profile['capability_claims'] as $claim ) {
		if ( $vertical === $claim['vertical'] ) {
			$capabilities[] = $claim['capability'];
		}
	}
	return supplier_seed_sorted( $capabilities );
}

function supplier_seed_commission_aligned( $profile, $descriptor ) {
	// The provider network uses integer zero as its compact no-commission
	// sentinel. The richer operations contract requires null for owned/net-rate
	// models so zero cannot be misread as a negotiated commission percentage.
	if ( in_array( $descriptor['settlement']['model'], array( 'owned', 'net_rate' ), true ) ) {
		return 0 === $descriptor['settlement']['commission_bps'] && null === $profile['settlement']['commission_bps'];
	}
	return $profile['settlement']['commission_bps'] === $descriptor['settlement']['commission_bps'];
}

function supplier_seed_alignment_errors( $profile, $descriptor ) {
	$errors = array();
	$checks = array(
		'environment'           => $profile['environment'] === $descriptor['environment'],
		'relationship'          => $profile['relationship']['model'] === $descriptor['relationship'],
		'verticals'             => supplier_seed_sorted( $profile['verticals'] ) === supplier_seed_sorted( $descriptor['verticals'] ),
		'capabilities'          => supplier_seed_profile_capabilities( $profile ) === supplier_seed_sorted( $descriptor['capabilities'] ),
		'settlement.model'      => $profile['settlement']['model'] === $descriptor['settlement']['model'],
		'settlement.currency'   => $profile['settlement']['currency'] === $descriptor['settlement']['currency'],
		'settlement.commission' => supplier_seed_commission_aligned( $profile, $descriptor ),
		'settlement.payout_lag' => $profile['settlement']['payout_lag_days'] === $descriptor['settlement']['payout_lag_days'],
	);
	foreach ( $checks as $field => $matches ) {
		if ( ! $matches ) {
			$errors[] = $field;
		}
	}
	return $errors;
}

function supplier_seed_coverage_errors( $profiles, $descriptors ) {
	$errors = array();
	$profile_ids = array();
	$provider_ids = array();
	foreach ( $profiles as $profile ) {
		$id = isset( $profile['supplier_id'] ) ? (string) $profile['supplier_id'] : '';
		if ( isset( $profile_ids[ $id ] ) ) {
			$errors[] = 'duplicate profile ' . $id;
		}
		$profile_ids[ $id ] = true;
	}
	foreach ( $descriptors as $descriptor ) {
		$id = $descriptor['provider_id'];
		if ( isset( $provider_ids[ $id ] ) ) {
			$errors[] = 'duplicate provider ' . $id;
		}
		$provider_ids[ $id ] = true;
	}
	foreach ( array_diff( array_keys( $provider_ids ), array_keys( $profile_ids ) ) as $id ) {
		$errors[] = 'missing profile ' . $id;
	}
	foreach ( array_diff( array_keys( $profile_ids ), array_keys( $provider_ids ) ) as $id ) {
		$errors[] = 'orphan profile ' . $id;
	}
	return $errors;
}

$fixture_path = __DIR__ . '/../../plugin/tra-vel-agent-core/assets/fixtures/commerce-sandbox/supplier-operations-profiles.json';
$raw = file_get_contents( $fixture_path );
supplier_seed_assert( is_string( $raw ) && '' !== trim( $raw ), 'the bundled supplier profile fixture must be readable' );
$fixture = json_decode( $raw, true );
supplier_seed_assert( is_array( $fixture ) && JSON_ERROR_NONE === json_last_error(), 'the supplier profile fixture must be valid JSON' );
supplier_seed_assert(
	supplier_seed_exact_object( $fixture, array( 'contract_version', 'fixture_id', 'environment', 'network_id', 'network_signature', 'simulated', 'profiles' ) ),
	'the supplier profile fixture envelope must remain closed'
);
supplier_seed_assert( '1.0.0' === $fixture['contract_version'], 'the supplier profile fixture contract version must remain supported' );
supplier_seed_assert( 'supplier_operations_profiles_v1' === $fixture['fixture_id'], 'the fixture identity must remain immutable' );
supplier_seed_assert( 'sandbox' === $fixture['environment'] && true === $fixture['simulated'], 'the fixture must remain explicitly sandbox-only and simulated' );
supplier_seed_assert( ! supplier_seed_contains_sensitive( $fixture ), 'the fixture must contain no raw secrets, PII, payment data, or direct contact data' );

$network = new Tra_Vel_Commerce_Sandbox_Network();
supplier_seed_assert( true === $network->load(), 'the canonical provider network must validate before profile alignment' );
$descriptors = $network->all();
$signature = $network->signature();
supplier_seed_assert( is_array( $descriptors ) && 15 === count( $descriptors ), 'the canonical network must expose exactly 15 providers' );
supplier_seed_assert( 'tra_vel_commerce_sandbox' === $fixture['network_id'], 'the profile fixture must bind the canonical network ID' );
supplier_seed_assert( $signature === $fixture['network_signature'], 'the profile fixture must bind the exact normalized provider-network signature' );
supplier_seed_assert( is_array( $fixture['profiles'] ) && array_values( $fixture['profiles'] ) === $fixture['profiles'], 'supplier profiles must be a closed list' );
supplier_seed_assert( 15 === count( $fixture['profiles'] ), 'the fixture must contain one profile for each of the 15 providers' );

$coverage_errors = supplier_seed_coverage_errors( $fixture['profiles'], $descriptors );
supplier_seed_assert( ! $coverage_errors, 'provider/profile coverage must be exactly one-to-one: ' . implode( ', ', $coverage_errors ) );

$descriptor_by_id = array();
foreach ( $descriptors as $descriptor ) {
	$descriptor_by_id[ $descriptor['provider_id'] ] = $descriptor;
}

$clock = '2026-07-19T12:00:00Z';
$validated_profiles = array();
$alignment_errors = array();
$execution_checks = 0;
foreach ( $fixture['profiles'] as $index => $profile ) {
	$id = $profile['supplier_id'];
	supplier_seed_assert( preg_match( '/^(?:flight|accommodation|package|transfer|activity|dining|insurance|connectivity|equipment)_supplier_[a-z]$/', $id ), "profile {$index} must use only a generic supplier ID" );
	supplier_seed_assert( isset( $descriptor_by_id[ $id ] ), "{$id} must map to one canonical provider" );
	$validated = Tra_Vel_Supplier_Operations_Policy::supplier_profile( $profile, $clock );
	supplier_seed_assert( is_array( $validated ), "{$id} must validate through the fail-closed supplier operations policy" );
	supplier_seed_assert( true === $validated['commercial_truth']['simulated'] && false === $validated['commercial_truth']['real_booking'] && false === $validated['commercial_truth']['real_charge'], "{$id} must never claim a live booking or charge" );
	supplier_seed_assert( 'sandbox_active' === $validated['lifecycle_status'] && 'sandbox_ready' === $validated['readiness']['decision'], "{$id} must be executable only in the active sandbox lifecycle" );
	supplier_seed_assert( true === $validated['revision_control']['immutable'] && 1 === $validated['revision_number'] && null === $validated['previous_revision_digest'], "{$id} must be an immutable first profile revision" );
	supplier_seed_assert( 'armed' === $validated['kill_switch']['state'] && array() === $validated['kill_switch']['blocked_capabilities'], "{$id} must expose a clear, inactive kill switch" );
	supplier_seed_assert( 'healthy' === $validated['health']['state'], "{$id} must start with evidence-backed healthy simulator telemetry" );
	supplier_seed_assert( $validated['relationship']['merchant_of_record'] === $validated['settlement']['customer_funds_owner'], "{$id} merchant-of-record and customer-funds ownership must agree" );

	foreach ( array_merge( $validated['endpoints']['allowed_hosts'], $validated['endpoints']['webhook_source_hosts'] ) as $host ) {
		supplier_seed_assert( supplier_seed_is_invalid_host( $host ), "{$id} may reference only reserved .invalid simulator hosts" );
	}
	foreach ( Tra_Vel_Supplier_Operations_Taxonomy::OPERATION_LANES as $lane ) {
		$lane_contract = $validated['operation_support'][ $lane ];
		supplier_seed_assert( 'Asia/Jerusalem' === $lane_contract['timezone'], "{$id} {$lane} support must have an explicit timezone" );
		supplier_seed_assert( 0 === strpos( $lane_contract['contact_route_ref'], 'route_' ) && 0 === strpos( $lane_contract['after_hours_route_ref'], 'route_' ), "{$id} {$lane} support must use opaque primary and after-hours routes" );
		supplier_seed_assert( $lane_contract['acknowledgement_sla_seconds'] <= $lane_contract['resolution_sla_seconds'] && $lane_contract['resolution_sla_seconds'] <= $lane_contract['reconciliation_sla_seconds'], "{$id} {$lane} SLAs must increase from acknowledgement through reconciliation" );
	}
	foreach ( $validated['verticals'] as $vertical ) {
		foreach ( supplier_seed_profile_vertical_capabilities( $validated, $vertical ) as $capability ) {
			supplier_seed_assert( true === Tra_Vel_Supplier_Operations_State_Machine::can_execute( $validated, $vertical, $capability, $clock ), "{$id} must execute certified {$vertical}:{$capability} in sandbox" );
			$execution_checks++;
		}
	}

	$errors = supplier_seed_alignment_errors( $validated, $descriptor_by_id[ $id ] );
	if ( $errors ) {
		$declared = supplier_seed_profile_capabilities( $validated );
		$network_caps = supplier_seed_sorted( $descriptor_by_id[ $id ]['capabilities'] );
		$alignment_errors[] = $id . ' [' . implode( ', ', $errors ) . '] profile={' . implode( ',', $declared ) . '} network={' . implode( ',', $network_caps ) . '}';
	}
	$validated_profiles[ $id ] = $validated;
}

$direct_id = 'flight_supplier_a';
$commercial_tamper = $validated_profiles[ $direct_id ];
$commercial_tamper['settlement']['commission_bps']++;
supplier_seed_assert(
	in_array( 'settlement.commission', supplier_seed_alignment_errors( $commercial_tamper, $descriptor_by_id[ $direct_id ] ), true ),
	'canonical alignment must reject a structurally valid commission change'
);

$raw_secret = $fixture['profiles'][0];
$raw_secret['credentials'][0]['api_key'] = 'sk-synthetic-value-must-still-be-rejected';
supplier_seed_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $raw_secret, $clock ), 'tra_vel_supplier_operations_sensitive_material_rejected', 'raw credential material must fail before profile execution' );

$raw_contact = $fixture['profiles'][0];
$raw_contact['escalation']['after_hours_route_ref'] = 'operator@example.invalid';
supplier_seed_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $raw_contact, $clock ), 'tra_vel_supplier_operations_sensitive_material_rejected', 'direct contact data must be replaced with an opaque route reference' );

$stale_source = $fixture['profiles'][0];
$stale_source['source_controls']['last_verified_at'] = '2026-07-19T10:00:00Z';
supplier_seed_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $stale_source, $clock ), 'tra_vel_supplier_operations_source_terms_stale', 'stale operational source evidence must fail closed' );

$live_claim = $fixture['profiles'][0];
$live_claim['commercial_truth']['real_charge'] = true;
supplier_seed_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $live_claim, $clock ), 'tra_vel_supplier_operations_sandbox_truth_invalid', 'a sandbox profile must never claim a real charge' );

$mutable_revision = $fixture['profiles'][0];
$mutable_revision['revision_control']['immutable'] = false;
supplier_seed_error( Tra_Vel_Supplier_Operations_Policy::supplier_profile( $mutable_revision, $clock ), 'tra_vel_supplier_operations_revision_control_invalid', 'a mutable supplier profile revision must be rejected' );

$open_profile = $validated_profiles['flight_supplier_a'];
$open_profile['health']['state'] = 'open';
supplier_seed_error( Tra_Vel_Supplier_Operations_State_Machine::can_execute( $open_profile, 'flight', 'search', $clock ), 'tra_vel_supplier_operations_execution_circuit_open', 'an open supplier circuit must block a declared capability' );

$killed_profile = $validated_profiles['flight_supplier_a'];
$killed_profile['kill_switch'] = array(
	'state' => 'engaged',
	'blocked_capabilities' => array( 'confirm' ),
	'reason_code' => 'sandbox_incident',
	'activated_at' => '2026-07-19T11:55:00Z',
	'activated_by_ref' => 'actor_sandboxoperator',
);
supplier_seed_error( Tra_Vel_Supplier_Operations_State_Machine::can_execute( $killed_profile, 'flight', 'confirm', $clock ), 'tra_vel_supplier_operations_execution_killed', 'a scoped kill switch must block the selected capability' );

$duplicate_profiles = $fixture['profiles'];
$duplicate_profiles[] = $fixture['profiles'][0];
$duplicate_errors = supplier_seed_coverage_errors( $duplicate_profiles, $descriptors );
supplier_seed_assert( (bool) preg_grep( '/^duplicate profile /', $duplicate_errors ), 'one-to-one coverage must detect a duplicate supplier profile' );

// Hard release gate: policy-safe profiles never invent transactional authority.
// Canonical affiliate/dependency mismatches must be reconciled in the network,
// then the bound signature updated. Never weaken this assertion.
supplier_seed_assert(
	! $alignment_errors,
	"canonical provider/profile alignment is unresolved:\n - " . implode( "\n - ", $alignment_errors )
);

echo "Supplier profile seeds passed ({$assertions} assertions; 15 profiles; {$execution_checks} certified executions; network {$signature}).\n";
