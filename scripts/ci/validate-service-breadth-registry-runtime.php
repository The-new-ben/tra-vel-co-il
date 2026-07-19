<?php
/**
 * Focused adversarial runtime gate for the private 360 service registry.
 */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

$operations = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/operations/';
require_once $operations . 'class-tra-vel-service-breadth-taxonomy.php';
require_once $operations . 'class-tra-vel-service-breadth-policy.php';

$assertions = 0;
function breadth_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "Service breadth runtime failed: {$message}\n" );
		exit( 1 );
	}
}

function breadth_expect_error( $value, $code, $message ) {
	breadth_assert( is_wp_error( $value ), $message . ' (expected WP_Error)' );
	if ( is_wp_error( $value ) ) {
		breadth_assert( $code === $value->get_error_code(), $message . ' (expected ' . $code . ', got ' . $value->get_error_code() . ')' );
	}
}

function breadth_clone( $value ) {
	return json_decode( json_encode( $value ), true );
}

function breadth_reseal( &$registry ) {
	$registry['fixture_digest'] = Tra_Vel_Service_Breadth_Policy::fixture_digest( $registry );
}

function breadth_same_set( $left, $right ) {
	sort( $left, SORT_STRING );
	sort( $right, SORT_STRING );
	return $left === $right;
}

$fixture_path = __DIR__ . '/../../plugin/tra-vel-agent-core/assets/fixtures/service-breadth-registry.json';
$fixture_source = file_get_contents( $fixture_path );
$fixture = json_decode( $fixture_source, true );

breadth_assert( is_array( $fixture ) && JSON_ERROR_NONE === json_last_error(), 'synthetic fixture must parse' );
breadth_assert( ! is_wp_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $fixture ) ), 'complete registry must validate' );
breadth_assert( '1.1.0' === $fixture['contract_version'], 'hardened registry must expose contract v1.1.0' );
breadth_assert( 1 === preg_match( '/^rg_syn_[a-f0-9]{32}$/', $fixture['registry_ref'] ), 'registry reference must be opaque and non-name-bearing' );
breadth_assert( 17 === $fixture['family_count'] && 17 === count( $fixture['families'] ), 'registry must contain 17 family profiles' );
breadth_assert( 34 === $fixture['scenario_count'] && 34 === count( $fixture['scenarios'] ), 'registry must contain exactly two scenarios per family' );
breadth_assert( hash_equals( $fixture['fixture_digest'], Tra_Vel_Service_Breadth_Policy::fixture_digest( $fixture ) ), 'fixture digest must bind the complete registry' );
breadth_assert( $fixture === Tra_Vel_Service_Breadth_Policy::build_synthetic_fixture(), 'checked-in fixture must replay byte-for-value from the deterministic builder' );

$definitions = Tra_Vel_Service_Breadth_Taxonomy::definitions();
$expected_local_lodging = array( 'city_business_hotel', 'resort_hotel', 'boutique_hotel', 'vacation_apartment_short_term_rental', 'villa', 'hostel', 'rural_bnb_zimmer', 'kibbutz_holiday_village_guest_accommodation', 'campground_glamping' );
$expected_local_experiences = array( 'local_guide_tour', 'attraction', 'museum', 'nature_reserve_park', 'beach', 'spa_wellness', 'event' );
$expected_local_ground = array(
	'taxi_ride' => 'ground_transfer', 'shared_shuttle' => 'ground_transfer', 'private_driver' => 'ground_transfer',
	'public_local_transit' => 'ground_transfer', 'rental_car' => 'car_rental', 'rail' => 'rail', 'coach' => 'coach_bus', 'ferry' => 'ferry',
);
breadth_assert( $expected_local_lodging === $definitions['lodging']['family_subtypes'], 'Israel-local lodging must preserve nine exact accommodation subtypes' );
breadth_assert( $expected_local_experiences === $definitions['experience']['family_subtypes'], 'Israel-local experiences must preserve guide, attraction, museum, nature, beach, wellness, and event subtypes' );
breadth_assert( $expected_local_ground === Tra_Vel_Service_Breadth_Taxonomy::ISRAEL_LOCAL_GROUND_MOBILITY_COVERAGE, 'Israel-local ground mobility must preserve eight explicit modes across their own families' );
breadth_assert( in_array( 'taxi_ride', $definitions['ground_transfer']['family_subtypes'], true ) && in_array( 'public_local_transit', $definitions['ground_transfer']['family_subtypes'], true ), 'ground transfer must keep taxi and public transit subtypes explicit' );
breadth_assert( isset( $definitions['car_rental'], $definitions['rail'], $definitions['coach_bus'], $definitions['ferry'] ), 'rental car, rail, coach, and ferry must remain separate service families' );
foreach ( $expected_local_ground as $subtype => $family ) {
	breadth_assert( in_array( $subtype, $definitions[ $family ]['family_subtypes'], true ), $subtype . ' must remain an exact subtype in ' . $family );
}
$priority_subtypes = Tra_Vel_Service_Breadth_Taxonomy::priority_subtype_operations();
breadth_assert( 9 === count( $priority_subtypes['lodging'] ), 'all nine Israel lodging subtypes need operational proof' );
breadth_assert( 7 === count( $priority_subtypes['experience'] ), 'all seven Israel experience subtypes need operational proof' );
breadth_assert( array( 'taxi_ride', 'private_driver', 'public_local_transit' ) === array_keys( $priority_subtypes['ground_transfer'] ), 'taxi, private driver, and public transit need separate operational proof' );
breadth_assert( 'document_assistance_orchestration_adapter_v1' === $definitions['entry_document_assistance']['crosswalk']['orchestration_adapter'], 'entry-document work must use a dedicated assistance adapter' );
breadth_assert( false === strpos( json_encode( $definitions['entry_document_assistance'] ), 'appointment_orbiometric_deadline_utc' ) && in_array( 'appointment_or_biometric_deadline_utc', $definitions['entry_document_assistance']['critical_deadlines'], true ), 'biometric deadline code must be spelled and routed correctly' );
$car_blueprints = Tra_Vel_Service_Breadth_Taxonomy::scenario_blueprints()['car_rental'];
breadth_assert( 'rental_inventory_and_desk_servicing_handoff' === $car_blueprints[1]['required_handoff_code'], 'pre-pickup vehicle substitution must route to rental inventory and desk servicing' );
$scenario_counts = array_fill_keys( Tra_Vel_Service_Breadth_Taxonomy::SERVICE_FAMILIES, 0 );
$event_coverage = array_fill_keys( Tra_Vel_Service_Breadth_Taxonomy::SERVICE_FAMILIES, array() );
$after_hours_coverage = array_fill_keys( Tra_Vel_Service_Breadth_Taxonomy::SERVICE_FAMILIES, 0 );
$vertical_coverage = array();

foreach ( $fixture['families'] as $profile ) {
	$family = $profile['service_family'];
	$vertical_coverage[ $profile['canonical_vertical'] ] = true;
	breadth_assert( isset( $definitions[ $family ] ), $family . ' must be canonical' );
	breadth_assert( $definitions[ $family ] === $profile, $family . ' must match its complete taxonomy profile' );
	breadth_assert( in_array( $profile['canonical_vertical'], Tra_Vel_Service_Breadth_Taxonomy::CANONICAL_VERTICALS, true ), $family . ' must crosswalk to one existing vertical' );
	breadth_assert( 'orchestration_bucket_only' === $profile['crosswalk']['mapping_kind'] && 'dedicated_service_family_adapter' === $profile['crosswalk']['operation_routing'] && Tra_Vel_Service_Breadth_Taxonomy::orchestration_adapter( $family ) === $profile['crosswalk']['orchestration_adapter'] && false === $profile['crosswalk']['equivalence_claimed'] && true === $profile['crosswalk']['subtype_preserved'], $family . ' must preserve subtype and use its dedicated adapter without claiming equivalence' );
	breadth_assert( Tra_Vel_Service_Breadth_Taxonomy::LIFECYCLE_STAGES === array_keys( $profile['lifecycle'] ), $family . ' must declare all 12 lifecycle axes in order' );
	foreach ( $profile['lifecycle'] as $stage => $applicability ) {
		breadth_assert( in_array( $applicability, Tra_Vel_Service_Breadth_Taxonomy::APPLICABILITY, true ), $family . ':' . $stage . ' applicability must be canonical' );
	}
	breadth_assert( count( $profile['family_subtypes'] ) >= 3, $family . ' must preserve practical service subtypes' );
	breadth_assert( count( $profile['critical_facts'] ) >= 5, $family . ' must declare critical operational facts' );
	breadth_assert( count( $profile['critical_deadlines'] ) >= 3, $family . ' must declare critical deadlines' );
	breadth_assert( count( $profile['required_handoffs'] ) >= 3, $family . ' must declare required human/system handoffs' );
	breadth_assert( true === $profile['israel_local']['applicable'] && count( $profile['israel_local']['required_fact_codes'] ) >= 3, $family . ' must declare Israel-local applicability and facts' );
	breadth_assert( $profile['map']['overview_zoom'] < $profile['map']['decision_zoom'] && $profile['map']['decision_zoom'] < $profile['map']['operational_zoom'], $family . ' must progress from overview to operational map resolution' );
	breadth_assert( $profile['map']['cluster_until_zoom'] >= $profile['map']['overview_zoom'] && $profile['map']['cluster_until_zoom'] < $profile['map']['decision_zoom'], $family . ' must cluster before decision resolution only' );
	breadth_assert( Tra_Vel_Service_Breadth_Taxonomy::MAP_RESOLUTION_PATH === $profile['map']['resolution_path'] && 'attached_non_occluding_context_panel' === $profile['map']['detail_surface'], $family . ' map detail must deepen in an attached non-occluding surface' );
	breadth_assert( true === $profile['map']['selection_to_plan_required'] && true === $profile['map']['viewport_padding_required'] && true === $profile['map']['rtl_mobile_safe_area_required'] && true === $profile['map']['source_freshness_required'] && true === $profile['map']['reduced_motion_alternative_required'], $family . ' map must preserve planning, source, RTL mobile, viewport, and reduced-motion contracts' );
	breadth_assert( count( $profile['map']['operational_anchor_codes'] ) >= 3, $family . ' must declare operational map anchors' );
	if ( isset( $priority_subtypes[ $family ] ) ) {
		foreach ( $priority_subtypes[ $family ] as $subtype => $operation ) {
			breadth_assert( in_array( $subtype, $profile['family_subtypes'], true ), $family . ':' . $subtype . ' must remain explicit' );
			breadth_assert( in_array( $operation['critical_fact_code'], $profile['critical_facts'], true ), $family . ':' . $subtype . ' needs a specific operational fact' );
			breadth_assert( in_array( $operation['deadline_code'], $profile['critical_deadlines'], true ), $family . ':' . $subtype . ' needs a specific deadline' );
			breadth_assert( in_array( $operation['handoff_code'], $profile['required_handoffs'], true ), $family . ':' . $subtype . ' needs a specific handoff' );
			breadth_assert( in_array( $operation['map_anchor_code'], $profile['map']['operational_anchor_codes'], true ), $family . ':' . $subtype . ' needs a specific operational map anchor' );
		}
	}
}
breadth_assert( count( $vertical_coverage ) === count( Tra_Vel_Service_Breadth_Taxonomy::CANONICAL_VERTICALS ), 'all nine canonical verticals must receive at least one explicit family crosswalk' );

$seen_family_slots = array();
$seen_primary_routes = array();
$seen_fallback_routes = array();
$empty_partition_coverage = array( 'affected_party' => false, 'unaffected_party' => false, 'preserved_service' => false );
foreach ( $fixture['scenarios'] as $scenario ) {
	$family = $scenario['service_family'];
	$profile = $definitions[ $family ];
	$family_slot = $family . ':' . $scenario['scenario_slot'];
	$scenario_counts[ $family ]++;
	$event_coverage[ $family ][ $scenario['event_state'] ] = true;
	$after_hours_coverage[ $family ] += $scenario['after_hours']['required'] ? 1 : 0;
	breadth_assert( $profile['canonical_vertical'] === $scenario['canonical_vertical'], $scenario['scenario_ref'] . ' must preserve its exact vertical crosswalk' );
	breadth_assert( in_array( $scenario['family_subtype'], $profile['family_subtypes'], true ), $scenario['scenario_ref'] . ' must preserve a family subtype' );
	breadth_assert( in_array( $scenario['required_deadline_code'], $profile['critical_deadlines'], true ), $scenario['scenario_ref'] . ' must protect a declared deadline' );
	breadth_assert( in_array( $scenario['required_handoff_code'], $profile['required_handoffs'], true ), $scenario['scenario_ref'] . ' must route to a declared handoff' );
	breadth_assert( count( $scenario['expected_actions'] ) >= 3, $scenario['scenario_ref'] . ' must expose a multi-step response' );
	breadth_assert( ! isset( $seen_family_slots[ $family_slot ] ), $family_slot . ' must be unique in the closed matrix' );
	breadth_assert( Tra_Vel_Service_Breadth_Taxonomy::synthetic_ref( 'scenario', $family_slot ) === $scenario['scenario_ref'], $family_slot . ' must use its exact opaque scenario reference' );
	breadth_assert( 1 === preg_match( '/^sc_syn_[a-f0-9]{32}$/', $scenario['scenario_ref'] ), $family_slot . ' scenario ref cannot contain a readable family or person name' );
	$seen_family_slots[ $family_slot ] = true;
	breadth_assert( true === $scenario['preservation']['preserve_unaffected'], $scenario['scenario_ref'] . ' must preserve unaffected trip components' );
	breadth_assert( ! array_intersect( $scenario['preservation']['affected_party_refs'], $scenario['preservation']['unaffected_party_refs'] ), $scenario['scenario_ref'] . ' must partition affected and unaffected travelers' );
	breadth_assert( ! array_intersect( $scenario['preservation']['affected_service_refs'], $scenario['preservation']['preserved_service_refs'] ), $scenario['scenario_ref'] . ' must partition affected and preserved services' );
	breadth_assert( breadth_same_set( $scenario['preservation']['party_scope_refs'], array_merge( $scenario['preservation']['affected_party_refs'], $scenario['preservation']['unaffected_party_refs'] ) ), $scenario['scenario_ref'] . ' party partition must exhaust its bound scope' );
	breadth_assert( breadth_same_set( $scenario['preservation']['service_scope_refs'], array_merge( $scenario['preservation']['affected_service_refs'], $scenario['preservation']['preserved_service_refs'] ) ), $scenario['scenario_ref'] . ' service partition must exhaust its bound scope' );
	breadth_assert( true === $scenario['preservation']['partition_complete'] && 1 === preg_match( '/^sp_syn_[a-f0-9]{32}$/', $scenario['preservation']['partition_scope_ref'] ), $scenario['scenario_ref'] . ' partition must be explicitly complete and scope-bound' );
	$empty_partition_coverage['affected_party'] = $empty_partition_coverage['affected_party'] || ! $scenario['preservation']['affected_party_refs'];
	$empty_partition_coverage['unaffected_party'] = $empty_partition_coverage['unaffected_party'] || ! $scenario['preservation']['unaffected_party_refs'];
	$empty_partition_coverage['preserved_service'] = $empty_partition_coverage['preserved_service'] || ! $scenario['preservation']['preserved_service_refs'];
	breadth_assert( true === $scenario['financial_axes']['netting_prohibited'] && true === $scenario['financial_axes']['independent_ledgers_required'], $scenario['scenario_ref'] . ' must keep payment, refund, and settlement separate' );
	breadth_assert( false === $scenario['after_hours']['supplier_dispatched'], $scenario['scenario_ref'] . ' after-hours routing must stay planned' );
	breadth_assert( ! isset( $seen_primary_routes[ $scenario['after_hours']['route_ref'] ] ) && ! isset( $seen_fallback_routes[ $scenario['after_hours']['fallback_route_ref'] ] ), $scenario['scenario_ref'] . ' must own unique primary and fallback routes' );
	$seen_primary_routes[ $scenario['after_hours']['route_ref'] ] = true;
	$seen_fallback_routes[ $scenario['after_hours']['fallback_route_ref'] ] = true;
	breadth_assert( $scenario['required_deadline_code'] === $scenario['after_hours']['deadline_code'] && $scenario['required_handoff_code'] === $scenario['after_hours']['primary_handoff_code'], $scenario['scenario_ref'] . ' after-hours route must bind the protected deadline and handoff' );
	breadth_assert( $scenario['after_hours']['acknowledgement_sla_seconds'] <= $scenario['after_hours']['fallback_after_seconds'] && $scenario['after_hours']['fallback_after_seconds'] <= $scenario['after_hours']['customer_update_sla_seconds'], $scenario['scenario_ref'] . ' after-hours clocks must progress acknowledgement to fallback to customer update' );
	breadth_assert( 'Asia/Jerusalem' === $scenario['after_hours']['timezone'] && true === $scenario['after_hours']['evidence_required'], $scenario['scenario_ref'] . ' after-hours clock and evidence requirements must be explicit' );
	if ( $scenario['after_hours']['required'] ) {
		breadth_assert( 'outside_declared_window' === $scenario['after_hours']['coverage_state'] && 2 === $scenario['after_hours']['escalation_tier'] && true === $scenario['after_hours']['safety_check_required'] && 'planned' === $scenario['after_hours']['escalation_state'], $scenario['scenario_ref'] . ' after-hours case must activate tier-two safety-aware planning' );
	} else {
		breadth_assert( 'within_declared_window' === $scenario['after_hours']['coverage_state'] && 0 === $scenario['after_hours']['escalation_tier'] && false === $scenario['after_hours']['safety_check_required'] && 'standby' === $scenario['after_hours']['escalation_state'], $scenario['scenario_ref'] . ' in-hours case must keep escalation on standby' );
	}
	foreach ( array( 'supplier_dispatched', 'processor_called', 'order_created', 'payment_created', 'refund_created', 'settlement_created', 'raw_pii_stored', 'raw_secret_stored', 'raw_provider_payload_stored', 'real_commercial_claimed' ) as $false_key ) {
		breadth_assert( false === $scenario['boundary'][ $false_key ], $scenario['scenario_ref'] . ':' . $false_key . ' must remain false' );
	}
}
breadth_assert( ! in_array( false, $empty_partition_coverage, true ), 'fixture must prove legitimate empty affected, unaffected, and preserved partitions' );
breadth_assert( Tra_Vel_Service_Breadth_Taxonomy::SCENARIO_COUNT === count( $seen_family_slots ), 'closed family-slot matrix must contain exactly 34 unique keys' );

foreach ( Tra_Vel_Service_Breadth_Taxonomy::SERVICE_FAMILIES as $family ) {
	breadth_assert( 2 === $scenario_counts[ $family ], $family . ' must have two adversarial scenarios' );
	breadth_assert( isset( $event_coverage[ $family ]['stale'], $event_coverage[ $family ]['missed'] ), $family . ' must cover stale and missed events' );
	breadth_assert( 1 === $after_hours_coverage[ $family ], $family . ' must cover exactly one after-hours escalation' );
}

$mutant = breadth_clone( $fixture );
$mutant['unexpected'] = true;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_registry_shape_invalid', 'extra top-level fields must fail closed' );

$mutant = breadth_clone( $fixture );
$mutant['environment'] = 'production';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_registry_shape_invalid', 'production mode must be rejected' );

$mutant = breadth_clone( $fixture );
$mutant['data_mode'] = 'live';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_registry_shape_invalid', 'live data mode must be rejected' );

$mutant = breadth_clone( $fixture );
$mutant['truth_boundary']['live_availability_claimed'] = true;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_truth_boundary_invalid', 'live availability claims must fail' );

$mutant = breadth_clone( $fixture );
$mutant['family_count'] = 16;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_coverage_invalid', 'declared family undercount must fail' );

$mutant = breadth_clone( $fixture );
array_pop( $mutant['families'] );
$mutant['family_count'] = 16;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_coverage_invalid', 'missing service family must fail' );

$mutant = breadth_clone( $fixture );
$mutant['families'][9]['canonical_vertical'] = 'equipment';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_definition_mismatch', 'car rental cannot be silently remapped' );

$mutant = breadth_clone( $fixture );
$mutant['families'][4]['crosswalk']['equivalence_claimed'] = true;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_crosswalk_invalid', 'cruise crosswalk cannot claim package equivalence' );

$mutant = breadth_clone( $fixture );
$mutant['families'][16]['crosswalk']['orchestration_adapter'] = 'activity_orchestration_adapter_v1';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_crosswalk_invalid', 'entry documents cannot route through the activity adapter' );

$mutant = breadth_clone( $fixture );
$mutant['families'][0]['family_ref'] = 'fm_syn_readable_airline_name';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_profile_invalid', 'readable or name-bearing family references must fail' );

$mutant = breadth_clone( $fixture );
array_pop( $mutant['families'][0]['family_subtypes'] );
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_definition_mismatch', 'subtype loss must fail the canonical profile' );

$mutant = breadth_clone( $fixture );
unset( $mutant['families'][0]['lifecycle']['incident'] );
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_lifecycle_invalid', 'missing incident applicability must fail' );

$mutant = breadth_clone( $fixture );
$mutant['families'][0]['lifecycle']['refund'] = 'assumed';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_lifecycle_invalid', 'invented lifecycle applicability must fail' );

$mutant = breadth_clone( $fixture );
$mutant['families'][0]['critical_deadlines'] = array_slice( $mutant['families'][0]['critical_deadlines'], 0, 2 );
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_operational_depth_invalid', 'families need at least three deadlines' );

$mutant = breadth_clone( $fixture );
$mutant['families'][0]['required_handoffs'] = array_slice( $mutant['families'][0]['required_handoffs'], 0, 2 );
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_operational_depth_invalid', 'families need at least three handoffs' );

$mutant = breadth_clone( $fixture );
$mutant['families'][0]['israel_local']['applicable'] = false;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_israel_local_invalid', 'Israel-local applicability cannot disappear' );

$mutant = breadth_clone( $fixture );
$mutant['families'][0]['map']['decision_zoom'] = $mutant['families'][0]['map']['operational_zoom'];
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_map_invalid', 'map zoom levels must strictly deepen' );

$mutant = breadth_clone( $fixture );
$mutant['families'][0]['map']['detail_surface'] = 'floating_poster_overlay';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_map_invalid', 'map detail cannot regress to an occluding poster overlay' );

$mutant = breadth_clone( $fixture );
$mutant['families'][1]['map']['operational_anchor_codes'] = array_slice( $mutant['families'][1]['map']['operational_anchor_codes'], 0, 3 );
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_subtype_operation_invalid', 'lodging cannot discard subtype-specific operational map anchors' );

$mutant = breadth_clone( $fixture );
array_pop( $mutant['scenarios'] );
$mutant['scenario_count'] = 33;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_coverage_invalid', 'fewer than 34 scenarios must fail' );

$mutant = breadth_clone( $fixture );
$mutant['scenario_count'] = 35;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_coverage_invalid', 'scenario count drift must fail' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][] = $mutant['scenarios'][0];
$mutant['scenarios'][34]['scenario_number'] = 35;
$mutant['scenario_count'] = 35;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_coverage_invalid', 'closed matrix cannot accept an extra third family scenario' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][1]['scenario_ref'] = $mutant['scenarios'][0]['scenario_ref'];
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_identity_invalid', 'duplicate scenario refs must fail' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][1]['scenario_number'] = 1;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_identity_invalid', 'duplicate scenario numbers must fail' );

$mutant = breadth_clone( $fixture );
$first_scenario = $mutant['scenarios'][0];
$mutant['scenarios'][0] = $mutant['scenarios'][2];
$mutant['scenarios'][2] = $first_scenario;
$mutant['scenarios'][0]['scenario_number'] = 1;
$mutant['scenarios'][2]['scenario_number'] = 3;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_identity_invalid', 'closed family-slot matrix order cannot be permuted even when numbers are resequenced' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['scenario_ref'] = 'sc_syn_air_transport_case_one';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_shape_invalid', 'readable scenario references must fail before matrix evaluation' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['canonical_vertical'] = 'activity';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_family_binding_invalid', 'scenario vertical drift must fail' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['family_subtype'] = 'unknown_subtype';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_blueprint_mismatch', 'scenario subtype drift must fail' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['event_state'] = 'current';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_blueprint_mismatch', 'stale coverage cannot be relabeled current' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][1]['after_hours']['required'] = false;
$mutant['scenarios'][1]['after_hours']['escalation_state'] = 'standby';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_after_hours_invalid', 'required after-hours escalation cannot disappear' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][1]['after_hours']['route_ref'] = 'rt_syn_readable_supplier_route';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_after_hours_invalid', 'after-hours route must be opaque and synthetic' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][1]['after_hours']['fallback_route_ref'] = $mutant['scenarios'][1]['after_hours']['route_ref'];
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_after_hours_invalid', 'after-hours fallback route must stay distinct from the primary route' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][1]['after_hours']['deadline_code'] = 'unbound_deadline_utc';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_after_hours_invalid', 'after-hours route must remain bound to the protected deadline' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][1]['after_hours']['acknowledgement_sla_seconds'] = 1200;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_after_hours_invalid', 'after-hours acknowledgement clock cannot drift from the closed playbook' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][1]['after_hours']['supplier_dispatched'] = true;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_after_hours_invalid', 'registry cannot dispatch an after-hours route' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['preservation']['preserve_unaffected'] = false;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_preservation_invalid', 'partial service preservation is mandatory' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['preservation']['unaffected_party_refs'] = $mutant['scenarios'][0]['preservation']['affected_party_refs'];
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_preservation_invalid', 'removing a party from both partitions cannot satisfy the bound scope' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['preservation']['preserved_service_refs'] = $mutant['scenarios'][0]['preservation']['affected_service_refs'];
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_preservation_invalid', 'affected and preserved services cannot overlap' );

$mutant = breadth_clone( $fixture );
array_pop( $mutant['scenarios'][0]['preservation']['service_scope_refs'] );
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_preservation_invalid', 'service partitions must exhaust the declared scope exactly' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['preservation']['partition_scope_ref'] = $mutant['scenarios'][2]['preservation']['partition_scope_ref'];
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_preservation_invalid', 'an opaque partition scope must remain bound to its exact family-slot' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['preservation']['party_scope_refs'] = $mutant['scenarios'][2]['preservation']['party_scope_refs'];
$mutant['scenarios'][0]['preservation']['unaffected_party_refs'] = $mutant['scenarios'][2]['preservation']['party_scope_refs'];
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_preservation_invalid', 'an exhaustive partition from a different family-slot cannot be rebound' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['preservation']['unaffected_party_refs'][0] = 'pt_syn_readable_traveler_name';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_preservation_invalid', 'name-bearing party references must be rejected' );

$legitimate = breadth_clone( $fixture );
$legitimate['scenarios'][1]['preservation']['preserved_service_refs'] = $legitimate['scenarios'][1]['preservation']['service_scope_refs'];
$legitimate['scenarios'][1]['preservation']['affected_service_refs'] = array();
breadth_reseal( $legitimate );
breadth_assert( ! is_wp_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $legitimate ) ), 'a bound empty affected-service partition is legitimate when affected travelers remain and the service scope is exhaustive' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['required_deadline_code'] = 'unknown_deadline_utc';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_blueprint_mismatch', 'undeclared scenario deadline must fail' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['required_handoff_code'] = 'unknown_handoff';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_blueprint_mismatch', 'undeclared scenario handoff must fail' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['financial_axes']['netting_prohibited'] = false;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_financial_separation_invalid', 'financial netting must be rejected' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['financial_axes']['payment'] = 'captured';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_financial_separation_invalid', 'registry cannot assert a new captured payment' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['boundary']['processor_called'] = true;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_scenario_boundary_invalid', 'processor calls must fail' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['email'] = 'traveler@example.test';
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_sensitive_material_rejected', 'raw email must fail before projection' );

$mutant = breadth_clone( $fixture );
$mutant['scenarios'][0]['provider_payload'] = array( 'raw' => true );
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_sensitive_material_rejected', 'raw provider payload must fail before projection' );

$mutant = breadth_clone( $fixture );
$mutant['fixture_digest'] = str_repeat( 'a', 64 );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_fixture_digest_invalid', 'fixture tampering without resealing must fail' );

$mutant = breadth_clone( $fixture );
$first = array_shift( $mutant['families'] );
$mutant['families'][] = $first;
breadth_reseal( $mutant );
breadth_expect_error( Tra_Vel_Service_Breadth_Policy::validate_registry( $mutant ), 'tra_vel_service_breadth_family_order_invalid', 'deterministic family order must not drift' );

echo 'Service breadth registry runtime passed (' . $assertions . " assertions; 17 families; 34 scenarios; zero dispatch).\n";
