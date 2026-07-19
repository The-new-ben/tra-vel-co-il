<?php
/**
 * Focused runtime and 43-scenario stress checks for Israel local operations.
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message, $data = array() ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

$base = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/local-tourism/';
require_once $base . 'class-tra-vel-local-tourism-taxonomy.php';
require_once $base . 'class-tra-vel-local-operations-taxonomy.php';
require_once $base . 'class-tra-vel-local-operations-policy.php';
require_once $base . 'class-tra-vel-local-operations-recovery-planner.php';

$assertions = 0;
function local_ops_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "Local operations runtime failed: {$message}\n" );
		exit( 1 );
	}
}
function local_ops_error( $value, $code, $message ) {
	local_ops_assert( is_wp_error( $value ), $message . ' (expected WP_Error)' );
	local_ops_assert( $code === $value->get_error_code(), $message . ' (unexpected error code)' );
}
function local_ops_digest( $character ) { return str_repeat( $character, 64 ); }
function local_ops_clone( $value ) { return json_decode( json_encode( $value ), true ); }

function local_ops_service( $suffix ) {
	$service = array(
		'contract_version'        => '1.0.0',
		'environment'             => 'sandbox',
		'data_mode'               => 'synthetic_demo',
		'service_revision_ref'    => 'tv_local_service_rev_syntheticrevision' . $suffix,
		'service_ref'             => 'tv_local_service_syntheticservice' . $suffix,
		'revision_number'         => 1,
		'previous_revision_digest'=> null,
		'revision_digest'         => local_ops_digest( '0' ),
		'supplier_ref'            => 'party_synthetic_demo_aaaaaaaaaaa' . $suffix,
		'inventory_type'          => 'boutique_hotel',
		'sellable'                => array(
			'scope'       => 'unit',
			'product_ref' => 'tv_product_syntheticproduct' . $suffix,
			'unit_ref'    => 'tv_unit_syntheticunitreference' . $suffix,
			'session_ref' => null,
			'route_ref'   => null,
		),
		'occupancy'              => array(
			'max_occupancy'     => 5,
			'max_adults'        => 3,
			'max_children'      => 3,
			'child_age_rule_ref'=> 'tv_child_age_rule_syntheticagerule' . $suffix,
			'child_age_bands'   => array(
				array( 'band_code' => 'infant', 'min_age' => 0, 'max_age' => 2, 'counts_as_occupant' => false ),
				array( 'band_code' => 'child', 'min_age' => 3, 'max_age' => 12, 'counts_as_occupant' => true ),
				array( 'band_code' => 'teen', 'min_age' => 13, 'max_age' => 17, 'counts_as_occupant' => true ),
			),
		),
		'arrival'                => array(
			'check_in_local'             => '15:00',
			'check_out_local'            => '11:00',
			'key_handoff_mode'           => 'reception',
			'key_handoff_ref'            => 'tv_handoff_synthetichandoff' . $suffix,
			'late_arrival_notice_minutes'=> 120,
			'arrival_instruction_ref'    => 'tv_arrival_syntheticarrival' . $suffix,
		),
		'terms'                  => array(
			'tax_treatment'             => 'conditional_exemption_review',
			'tax_terms_ref'             => 'tv_terms_synthetictaxterms' . $suffix,
			'deposit_treatment'         => 'preauthorization',
			'deposit_terms_ref'         => 'tv_terms_syntheticdepositterms' . $suffix,
			'cancellation_treatment'    => 'free_until_deadline',
			'cancellation_terms_ref'    => 'tv_terms_syntheticcancelterms' . $suffix,
			'cancellation_deadline_utc' => '2026-07-20T09:00:00Z',
		),
		'after_hours_support'    => array(
			'available'               => true,
			'owner_ref'               => 'party_synthetic_demo_bbbbbbbbbbb' . $suffix,
			'channel_ref'             => 'tv_channel_syntheticchannel' . $suffix,
			'response_target_minutes' => 30,
			'handoff_instruction_ref' => 'tv_handoff_instruction_syntheticinstruction' . $suffix,
		),
		'provenance'             => array(
			'authority'       => 'synthetic_test',
			'source_ref'      => 'tv_source_syntheticsource' . $suffix,
			'evidence_digest' => local_ops_digest( 'a' ),
			'observed_at'     => '2026-07-19T08:00:00Z',
			'reviewed_at'     => '2026-07-19T08:10:00Z',
			'expires_at'      => '2026-08-01T00:00:00Z',
			'review_state'    => 'reviewed',
		),
		'commerce_binding'       => array(
			'commerce_offer_ref' => 'tv_offer_syntheticofferreference' . $suffix,
			'trip_node_ref'      => 'tv_node_synthetictripnoderef' . $suffix,
			'offer_version'      => 1,
			'offer_digest'       => local_ops_digest( 'b' ),
		),
		'created_at'             => '2026-07-19T08:00:00Z',
		'effective_at'           => '2026-07-19T08:30:00Z',
		'boundary'               => array(
			'server_only'                 => true,
			'public_serialization_allowed'=> false,
			'synthetic_demo'              => true,
			'live_availability_claimed'   => false,
			'checkout_created'            => false,
			'supplier_dispatched'         => false,
			'processor_called'            => false,
			'raw_pii_stored'              => false,
			'raw_payment_data_stored'     => false,
		),
	);
	$service['revision_digest'] = Tra_Vel_Local_Operations_Policy::content_digest( $service, 'revision_digest' );
	return $service;
}

function local_ops_context( $corridor = 'jerusalem_corridor' ) {
	$context = array(
		'contract_version'         => '1.0.0',
		'environment'              => 'sandbox',
		'data_mode'                => 'synthetic_demo',
		'context_ref'              => 'tv_local_search_syntheticcontext',
		'context_version'          => 1,
		'previous_context_digest'  => null,
		'context_digest'           => local_ops_digest( '0' ),
		'created_at'               => '2026-07-19T08:00:00Z',
		'dates'                    => array( 'start_date' => '2026-07-20', 'end_date' => '2026-07-22', 'timezone' => 'Asia/Jerusalem' ),
		'party'                    => array( 'adult_count' => 2, 'child_ages' => array( 4, 12 ) ),
		'room_allocations'         => array(
			array( 'allocation_ref' => 'tv_room_allocation_syntheticroomone', 'adult_count' => 2, 'child_age_indexes' => array( 0, 1 ) ),
		),
		'geography'                => array(
			'corridor'                 => $corridor,
			'origin_geo_ref'           => 'tv_geo_syntheticoriginref',
			'destination_geo_refs'     => array( 'tv_geo_syntheticdestinationref' ),
			'drive_time_limit_minutes' => 180,
		),
		'transport_modes'           => array( 'private_car', 'rail' ),
		'requirements'              => array(
			'kosher'        => array( 'kosher_certificate', 'certificate_scope' ),
			'shabbat'       => array( 'manual_key', 'shabbat_check_in' ),
			'accessibility' => array( 'step_free_arrival', 'accessible_unit' ),
			'parking'       => array( 'accessible_bay', 'ev_charging' ),
		),
		'product_intents'           => array(
			'activities' => array( 'licensed_guide', 'weather_fallback' ),
			'dining'     => array( 'reservation', 'certificate_current' ),
			'equipment'  => array( 'child_seat', 'mobility_aid' ),
		),
		'benefit_filters'           => array(
			'filter_mode'             => 'all',
			'airline_inventory_ids'   => array( 'airline_synthetic_inventory' ),
			'program_ids'             => array( 'program_synthetic_loyalty' ),
			'credential_product_ids'  => array( 'credential_synthetic_card' ),
			'campaign_revisions'      => array( array( 'campaign_id' => 'campaign_synthetic_local', 'version' => 1 ) ),
		),
		'boundary'                  => array(
			'server_only'                 => true,
			'public_serialization_allowed'=> false,
			'contains_raw_identity_data'  => false,
			'contains_card_number'        => false,
			'contains_loyalty_credentials'=> false,
			'creates_eligibility'         => false,
			'creates_availability'        => false,
			'creates_price'               => false,
		),
	);
	$context['context_digest'] = Tra_Vel_Local_Operations_Policy::content_digest( $context, 'context_digest' );
	return $context;
}

function local_ops_event( $trigger, $disruption_type, $corridor, $service, $truth_state = 'verified_current' ) {
	$event = array(
		'contract_version'          => '1.0.0',
		'environment'               => 'sandbox',
		'data_mode'                 => 'synthetic_demo',
		'event_ref'                 => 'tv_local_event_syntheticeventref',
		'event_version'             => 1,
		'supersedes_event_digest'   => null,
		'event_digest'              => local_ops_digest( '0' ),
		'trigger'                   => $trigger,
		'disruption_type'           => $disruption_type,
		'severity'                  => in_array( $disruption_type, array( 'security', 'evacuation', 'fire', 'flood' ), true ) ? 'P0' : 'P2',
		'source'                    => array(
			'authority'       => 'synthetic_test',
			'source_ref'      => 'tv_source_syntheticdisruptionsource',
			'evidence_digest' => local_ops_digest( 'c' ),
			'observed_at'     => '2026-07-19T09:00:00Z',
			'fresh_until'     => '2026-07-19T12:00:00Z',
			'truth_state'     => $truth_state,
		),
		'geometry'                  => array(
			'geometry_type'   => 'corridor',
			'geometry_ref'    => 'tv_geometry_syntheticcorridorref',
			'geometry_digest' => local_ops_digest( 'd' ),
			'corridor'        => $corridor,
		),
		'issued_at'                 => '2026-07-19T09:00:00Z',
		'effective_at'              => '2026-07-19T09:15:00Z',
		'expires_at'                => '2026-07-19T12:00:00Z',
		'affected_service_refs'     => array( $service['service_ref'] ),
		'affected_trip_node_refs'   => array( $service['commerce_binding']['trip_node_ref'] ),
		'boundary'                  => array(
			'server_only'                 => true,
			'public_serialization_allowed'=> false,
			'planning_only'               => true,
			'supplier_dispatched'         => false,
			'processor_called'            => false,
			'financial_state_changed'     => false,
			'raw_supplier_payload_stored' => false,
		),
	);
	$event['event_digest'] = Tra_Vel_Local_Operations_Policy::content_digest( $event, 'event_digest' );
	return $event;
}

$now = '2026-07-19T10:00:00Z';
$service_a = local_ops_service( 'a' );
$service_b = local_ops_service( 'b' );
$context = local_ops_context();
local_ops_assert( ! is_wp_error( Tra_Vel_Local_Operations_Policy::service_revision( $service_a, $now ) ), 'a valid immutable local service revision must pass' );
local_ops_assert( ! is_wp_error( Tra_Vel_Local_Operations_Policy::search_context( $context ) ), 'a complete exact local search context must pass' );

$tampered = local_ops_clone( $service_a );
$tampered['arrival']['check_in_local'] = '16:00';
local_ops_error( Tra_Vel_Local_Operations_Policy::service_revision( $tampered, $now ), 'tra_vel_local_operations_service_digest_mismatch', 'post-digest service mutation must fail' );
$ambiguous = local_ops_clone( $service_a );
$ambiguous['sellable']['route_ref'] = 'tv_route_syntheticrouteref';
$ambiguous['revision_digest'] = Tra_Vel_Local_Operations_Policy::content_digest( $ambiguous, 'revision_digest' );
local_ops_error( Tra_Vel_Local_Operations_Policy::service_revision( $ambiguous, $now ), 'tra_vel_local_operations_sellable_scope_ambiguous', 'a unit cannot also claim a route reference' );
$gap = local_ops_clone( $service_a );
$gap['occupancy']['child_age_bands'][1]['min_age'] = 4;
$gap['revision_digest'] = Tra_Vel_Local_Operations_Policy::content_digest( $gap, 'revision_digest' );
local_ops_error( Tra_Vel_Local_Operations_Policy::service_revision( $gap, $now ), 'tra_vel_local_operations_child_age_band_invalid', 'child-age rules cannot contain a gap' );
$expired = local_ops_clone( $service_a );
$expired['provenance']['expires_at'] = '2026-07-19T09:59:59Z';
$expired['revision_digest'] = Tra_Vel_Local_Operations_Policy::content_digest( $expired, 'revision_digest' );
local_ops_error( Tra_Vel_Local_Operations_Policy::service_revision( $expired, $now ), 'tra_vel_local_operations_provenance_invalid', 'expired service provenance must fail closed' );

$duplicate_child = local_ops_clone( $context );
$duplicate_child['room_allocations'][] = array( 'allocation_ref' => 'tv_room_allocation_syntheticroomtwo', 'adult_count' => 1, 'child_age_indexes' => array( 0 ) );
$duplicate_child['context_digest'] = Tra_Vel_Local_Operations_Policy::content_digest( $duplicate_child, 'context_digest' );
local_ops_error( Tra_Vel_Local_Operations_Policy::search_context( $duplicate_child ), 'tra_vel_local_operations_room_child_duplicate', 'a child cannot be allocated twice' );
$broad_access = local_ops_clone( $context );
$broad_access['requirements']['accessibility'] = array( 'accessible' );
$broad_access['context_digest'] = Tra_Vel_Local_Operations_Policy::content_digest( $broad_access, 'context_digest' );
local_ops_assert( is_wp_error( Tra_Vel_Local_Operations_Policy::search_context( $broad_access ) ), 'broad accessibility marketing labels must fail exact filtering' );
$secret_axis = local_ops_clone( $context );
$secret_axis['benefit_filters']['card_number'] = 'not-accepted';
$secret_axis['context_digest'] = Tra_Vel_Local_Operations_Policy::content_digest( $secret_axis, 'context_digest' );
local_ops_error( Tra_Vel_Local_Operations_Policy::search_context( $secret_axis ), 'tra_vel_local_operations_benefit_filters_invalid', 'benefit filters cannot contain a card number or extra axis' );

$matrix_path = __DIR__ . '/../../plugin/tra-vel-agent-core/assets/fixtures/israel-local-operations-stress-matrix.json';
$matrix = json_decode( file_get_contents( $matrix_path ), true );
local_ops_assert( is_array( $matrix ) && JSON_ERROR_NONE === json_last_error(), 'the stress matrix must parse' );
local_ops_assert( count( $matrix['scenarios'] ) >= 36, 'the local operations gate requires at least 36 scenarios' );
$corridors = array();
$triggers = array();
foreach ( $matrix['scenarios'] as $scenario ) {
	$rule = Tra_Vel_Local_Operations_Recovery_Planner::rule( $scenario['trigger'] );
	local_ops_assert( ! is_wp_error( $rule ), $scenario['scenario_id'] . ' needs a deterministic rule' );
	local_ops_assert( $scenario['disruption_type'] === $rule['disruption_type'] && $scenario['expected_strategy'] === $rule['strategy'], $scenario['scenario_id'] . ' matrix expectation must match runtime policy' );
	local_ops_assert( $scenario['expected_supplier_change_approval'] === $rule['supplier_change'] && $scenario['expected_financial_approval'] === $rule['financial_change'] && $scenario['expected_safety_handoff'] === $rule['safety_handoff'] && $scenario['expected_benefit_state'] === $rule['benefit_state'], $scenario['scenario_id'] . ' approval and benefit expectations must match' );
	$scenario_context = local_ops_context( $scenario['corridor'] );
	$scenario_event = local_ops_event( $scenario['trigger'], $scenario['disruption_type'], $scenario['corridor'], $service_a );
	$plan = Tra_Vel_Local_Operations_Recovery_Planner::build( array( $service_a, $service_b ), $scenario_context, $scenario_event, $now );
	local_ops_assert( ! is_wp_error( $plan ), $scenario['scenario_id'] . ' must build a recovery plan' );
	local_ops_assert( $rule['strategy'] === $plan['strategy'] && array( $service_b['service_ref'] ) === $plan['preserved_service_refs'] && array( $service_b['commerce_binding']['trip_node_ref'] ) === $plan['preserved_trip_node_refs'], $scenario['scenario_id'] . ' must preserve every unaffected component' );
	local_ops_assert( false === $plan['dispatch']['supplier_dispatched'] && false === $plan['dispatch']['processor_called'] && 'not_dispatched' === $plan['dispatch']['state'], $scenario['scenario_id'] . ' must perform zero dispatch' );
	foreach ( $plan['actions'] as $action ) {
		local_ops_assert( 'planned' === $action['execution_state'] && false === $action['supplier_dispatched'] && false === $action['processor_called'], $scenario['scenario_id'] . ' actions must stay planned' );
	}
	if ( $rule['supplier_change'] ) {
		local_ops_assert( in_array( 'customer_approval', $plan['required_approvals'], true ) && in_array( 'supplier_change_approval', $plan['required_approvals'], true ), $scenario['scenario_id'] . ' supplier changes require explicit approval' );
	}
	if ( $rule['financial_change'] ) {
		local_ops_assert( in_array( 'customer_approval', $plan['required_approvals'], true ) && in_array( 'financial_approval', $plan['required_approvals'], true ) && true === $plan['financial_separation']['netting_prohibited'] && 'separate_authorization_required' === $plan['financial_separation']['replacement_payment_state'], $scenario['scenario_id'] . ' financial changes require separate approval and no netting' );
	}
	if ( $rule['safety_handoff'] ) {
		local_ops_assert( 'human_safety_review' === $plan['state'], $scenario['scenario_id'] . ' must enter human safety review' );
	}
	local_ops_assert( $rule['benefit_state'] === $plan['benefit_reconciliation']['state'] && false === $plan['benefit_reconciliation']['eligibility_created'] && 'none' === $plan['benefit_reconciliation']['checkout_effect'], $scenario['scenario_id'] . ' benefit state cannot create eligibility or checkout truth' );
	$corridors[ $scenario['corridor'] ] = true;
	$triggers[ $scenario['trigger'] ] = true;
}
local_ops_assert( isset( $corridors['jerusalem_corridor'], $corridors['eilat_corridor'] ), 'both Jerusalem and Eilat corridors must be stressed' );
local_ops_assert( count( $triggers ) === count( Tra_Vel_Local_Operations_Taxonomy::SCENARIO_TRIGGERS ), 'every declared trigger must appear in the stress matrix' );

$stale_event = local_ops_event( 'venue_closed', 'closure', 'jerusalem_corridor', $service_a, 'stale' );
$stale_plan = Tra_Vel_Local_Operations_Recovery_Planner::build( array( $service_a, $service_b ), $context, $stale_event, $now );
local_ops_assert( ! is_wp_error( $stale_plan ) && 'evidence_required' === $stale_plan['state'] && false === $stale_plan['dispatch']['supplier_dispatched'], 'stale disruption evidence must block execution without losing the trip' );

$wrong_type = local_ops_event( 'flash_flood', 'weather', 'jerusalem_corridor', $service_a );
local_ops_error( Tra_Vel_Local_Operations_Recovery_Planner::build( array( $service_a, $service_b ), $context, $wrong_type, $now ), 'tra_vel_local_recovery_event_type_mismatch', 'a trigger cannot be relabeled as another disruption type' );

echo "Israel local operations runtime passed ({$assertions} assertions; " . count( $matrix['scenarios'] ) . " Jerusalem/Eilat scenarios; zero dispatch).\n";
