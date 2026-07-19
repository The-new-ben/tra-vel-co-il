<?php
/** Runtime contract checks for Israel local tourism and Earth-to-local maps. */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	private $data;
	public function __construct( $code, $message = '', $data = null ) {
		$this->code = $code;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }

$base = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/local-tourism/';
require_once $base . 'class-tra-vel-local-tourism-taxonomy.php';
require_once $base . 'class-tra-vel-local-map-state-machine.php';
require_once $base . 'class-tra-vel-local-tourism-policy.php';

$assertions = 0;
function local_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "Local tourism runtime failed: {$message}\n" );
		exit( 1 );
	}
}
function local_clone( $value ) { return unserialize( serialize( $value ) ); }
function local_source( $ref, $authority = 'synthetic_test', $freshness = 'current' ) {
	return array(
		'source_ref'       => $ref,
		'authority'        => $authority,
		'source_kind'      => 'synthetic_test' === $authority ? 'synthetic_contract' : 'api_response',
		'owner_ref'        => 'party_aaaaaaaaaaaa',
		'canonical_uri'    => null,
		'observed_at_utc'  => '2026-07-19T08:00:00Z',
		'retrieved_at_utc' => '2026-07-19T08:01:00Z',
		'effective_from'   => null,
		'effective_to'     => null,
		'expires_at_utc'   => '2026-07-19T10:00:00Z',
		'freshness_state'  => $freshness,
		'content_digest'   => 'sha256:' . str_repeat( 'a', 64 ),
	);
}
function local_unknown_fact() {
	return array(
		'group_state'         => 'unknown',
		'next_action'         => 'verify',
		'last_reviewed_at_utc'=> null,
		'assertions'          => array(),
	);
}
function local_fact_assertion( $code, $state, $source_refs ) {
	return array(
		'code'            => $code,
		'state'           => $state,
		'value_code'      => null,
		'authority_ref'   => null,
		'certificate_ref' => null,
		'valid_from'      => null,
		'valid_to'        => null,
		'limitations'     => array(),
		'source_refs'     => $source_refs,
		'observed_at_utc' => 'unknown' === $state ? null : '2026-07-19T08:00:00Z',
		'expires_at_utc'  => 'unknown' === $state ? null : '2026-07-19T10:00:00Z',
	);
}
function local_base_item() {
	$facts = array();
	foreach ( Tra_Vel_Local_Tourism_Taxonomy::FACT_GROUPS as $group ) {
		$facts[ $group ] = local_unknown_fact();
	}
	return array(
		'contract_version' => '1.0.0',
		'item_ref'         => 'local_item_aaaaaaaaaaaa',
		'operator_ref'     => 'party_aaaaaaaaaaaa',
		'data_mode'        => 'synthetic_demo',
		'inventory_type'   => 'boutique_hotel',
		'variant_code'     => null,
		'title'            => null,
		'classification'   => array(
			'scheme_code'               => 'israel_hotel_ranking',
			'scheme_applicability'      => 'applicable',
			'official_state'           => 'unranked',
			'official_level'           => null,
			'claimed_category_code'    => 'boutique_hotel',
			'source_refs'               => array( 'src_bbbbbbbbbbbb' ),
			'observed_at_utc'           => '2026-07-19T08:00:00Z',
			'valid_to_utc'              => null,
			'quality_inference_allowed' => false,
		),
		'geography'        => array(
			'hierarchy_state'       => 'partial',
			'jurisdiction'          => array( 'country_code' => 'IL', 'district' => null, 'municipality' => null, 'locality' => null, 'neighborhood' => null, 'address' => null ),
			'travel_regions'        => array(),
			'coordinate_state'      => 'verified',
			'primary_point'         => array( 'latitude' => 31.0, 'longitude' => 35.0 ),
			'coordinate_source_refs'=> array( 'src_aaaaaaaaaaaa' ),
			'entrances'             => array(),
			'service_geometry'      => null,
		),
		'provenance' => array(
			local_source( 'src_aaaaaaaaaaaa' ),
			array_merge( local_source( 'src_bbbbbbbbbbbb', 'official_registry' ), array( 'source_kind' => 'registry_record' ) ),
		),
		'fit_facts' => $facts,
		'operations' => array(
			'operating_state'       => 'open_verified',
			'operating_source_refs' => array( 'src_aaaaaaaaaaaa' ),
			'opening_hours'         => array(
				'state'           => 'current_verified',
				'timezone'        => 'Asia/Jerusalem',
				'periods'         => array(),
				'source_refs'     => array( 'src_aaaaaaaaaaaa' ),
				'observed_at_utc' => '2026-07-19T08:00:00Z',
				'expires_at_utc'  => '2026-07-19T10:00:00Z',
			),
		),
		'commercial' => array(
			'availability' => array(
				'state'              => 'unknown',
				'inventory_scope'    => 'property',
				'source_ref'         => null,
				'observed_at_utc'     => null,
				'expires_at_utc'      => null,
				'quantity_remaining' => null,
				'bookable'           => false,
			),
			'pricing' => array(
				'state'           => 'not_requested',
				'currency'        => null,
				'payable_minor'   => null,
				'pay_later_minor' => null,
				'quote_ref'       => null,
				'source_ref'      => null,
				'quoted_at_utc'   => null,
				'expires_at_utc'  => null,
				'bookable'        => false,
			),
		),
	);
}
function local_mobile_layout( $sheet, $revision ) {
	return array(
		'viewport_class'             => 'mobile',
		'reading_direction'          => 'rtl',
		'logical_edge_model'         => 'logical_start_end',
		'map_surface_role'           => 'primary',
		'map_ownership_ratio'        => 0.55,
		'bottom_sheet_state'         => $sheet,
		'side_rail_state'            => 'absent',
		'filter_surface_state'       => 'closed',
		'selected_content_mode'      => 'mobile_bottom_sheet',
		'route_progress_mode'        => 'narrow_top_strip',
		'overlay_stack_depth'        => 1,
		'context_card_count'         => 1,
		'map_controls_zone'          => 'logical_inline_start',
		'map_controls_available'     => true,
		'map_pan_available'          => true,
		'static_poster_under_canvas' => false,
		'requires_second_entry_click'=> false,
		'safe_area_reflow_state'     => 'applied',
		'safe_area_revision'         => $revision,
		'selected_target_visibility' => 'visible',
	);
}

// Canonical taxonomy and exact family boundaries.
local_assert( 14 === count( Tra_Vel_Local_Tourism_Taxonomy::INVENTORY_TYPES ), 'inventory taxonomy must contain all 14 canonical local types' );
foreach ( Tra_Vel_Local_Tourism_Taxonomy::ACCOMMODATION_TYPES as $type ) {
	local_assert( 'accommodation' === Tra_Vel_Local_Tourism_Taxonomy::inventory_family( $type ), "{$type} must remain an accommodation subtype" );
}
local_assert( 'activity' === Tra_Vel_Local_Tourism_Taxonomy::inventory_family( 'tour' ), 'tour must map to activity without losing its type' );
local_assert( 'transfer' === Tra_Vel_Local_Tourism_Taxonomy::inventory_family( 'mobility_transport' ), 'mobility must map to the commerce transfer family' );
local_assert( '' === Tra_Vel_Local_Tourism_Taxonomy::inventory_type( 'resortish' ), 'invented inventory types must fail closed' );
local_assert( 'accessible_entrance' === Tra_Vel_Local_Tourism_Taxonomy::fact_code( 'accessibility', 'accessible_entrance' ), 'fact codes must resolve inside their group' );
local_assert( '' === Tra_Vel_Local_Tourism_Taxonomy::fact_code( 'kosher', 'accessible_entrance' ), 'fact codes cannot leak between groups' );

// Direct Earth-to-local navigation and independent render recovery.
$navigation = array(
	array( 'world_globe', 'select_israel', 'country_focus' ),
	array( 'country_focus', 'camera_descent_complete', 'israel_region_overview' ),
	array( 'israel_region_overview', 'local_tiles_ready', 'local_high_res_map' ),
	array( 'local_high_res_map', 'select_place', 'place_or_route_detail' ),
	array( 'place_or_route_detail', 'add_to_itinerary', 'itinerary_assembly' ),
	array( 'itinerary_assembly', 'revalidation_succeeded', 'revalidated_proposal' ),
);
foreach ( $navigation as $step ) {
	local_assert( $step[2] === Tra_Vel_Local_Map_State_Machine::transition( 'navigation', $step[0], $step[1] ), "{$step[0]} must reach {$step[2]} directly" );
}
local_assert( is_wp_error( Tra_Vel_Local_Map_State_Machine::transition( 'navigation', 'country_focus', 'open_poster' ) ), 'a poster cannot become an intermediate navigation state' );
local_assert( is_wp_error( Tra_Vel_Local_Map_State_Machine::transition( 'navigation', 'world_globe', 'select_place' ) ), 'world view cannot skip the progressive local handoff' );
$reduced = Tra_Vel_Local_Map_State_Machine::transition_plan( 'world_globe', 'select_israel', 'reduced', 'online' );
local_assert( 'instant_with_progress' === $reduced['animation_mode'] && true === $reduced['progress_visible'], 'reduced motion must preserve visible progress without animation' );
local_assert( false === $reduced['requires_second_entry_click'] && false === $reduced['static_poster_required'], 'Israel selection must never require a poster or second click' );
$offline_plan = Tra_Vel_Local_Map_State_Machine::transition_plan( 'world_globe', 'select_israel', 'full', 'offline' );
local_assert( 'cached_read_only' === $offline_plan['data_delivery_mode'] && true === $offline_plan['map_controls_available'], 'offline descent must retain controls in read-only cache mode' );

$render = Tra_Vel_Local_Map_State_Machine::transition( 'render', 'globe_ready', 'start_descent' );
$render = Tra_Vel_Local_Map_State_Machine::transition( 'render', $render, 'begin_local_tiles' );
local_assert( 'degraded_tiles' === Tra_Vel_Local_Map_State_Machine::transition( 'render', $render, 'tile_failure' ), 'tile failure must enter a recoverable degraded state' );
local_assert( 'local_tiles_loading' === Tra_Vel_Local_Map_State_Machine::transition( 'render', 'degraded_tiles', 'retry_tiles' ), 'degraded tiles must support retry' );
local_assert( 'offline_cached' === Tra_Vel_Local_Map_State_Machine::transition( 'render', 'degraded_tiles', 'use_cached_tiles' ), 'degraded tiles must preserve a cached fallback' );
local_assert( 'local_tiles_loading' === Tra_Vel_Local_Map_State_Machine::transition( 'render', 'offline_cached', 'reconnect' ), 'offline cache must reconnect without losing navigation state' );

$context = array(
	'context_ref'        => 'trip_context_aaaaaaaaaaaa',
	'context_digest'     => 'sha256:' . str_repeat( 'b', 64 ),
	'revision'           => 2,
	'dates_state'        => 'set',
	'party_state'        => 'set',
	'budget_state'       => 'set',
	'benefits_state'     => 'connected_current',
	'accessibility_state'=> 'requirements_set',
	'intent_state'       => 'free_language_set',
);
$context_after = $context;
$context_after['revision'] = 3;
local_assert( true === Tra_Vel_Local_Map_State_Machine::assert_context_preserved( $context, $context_after ), 'camera transitions may advance revision while preserving intent' );
$changed = $context_after;
$changed['party_state'] = 'unset';
local_assert( is_wp_error( Tra_Vel_Local_Map_State_Machine::assert_context_preserved( $context, $changed ) ), 'navigation cannot silently drop the party' );
$rollback = $context;
$rollback['revision'] = 1;
local_assert( is_wp_error( Tra_Vel_Local_Map_State_Machine::assert_context_preserved( $context, $rollback ) ), 'context revision cannot move backwards' );

// Mobile RTL and desktop non-stacking layout gates.
$mobile_peek = local_mobile_layout( 'peek', 3 );
local_assert( true === Tra_Vel_Local_Tourism_Policy::validate_layout( $mobile_peek ), 'mobile RTL peek layout must remain pannable' );
$mobile_half = local_mobile_layout( 'half', 4 );
local_assert( true === Tra_Vel_Local_Tourism_Policy::validate_layout( $mobile_half, $mobile_peek ), 'sheet movement must reflow and reveal selected targets' );
$stale_safe_area = local_mobile_layout( 'full', 4 );
local_assert( is_wp_error( Tra_Vel_Local_Tourism_Policy::validate_layout( $stale_safe_area, $mobile_half ) ), 'sheet movement without a new safe area must fail' );
$stacked = local_mobile_layout( 'half', 5 );
$stacked['overlay_stack_depth'] = 2;
local_assert( is_wp_error( Tra_Vel_Local_Tourism_Policy::validate_layout( $stacked ) ), 'stacking a second overlay over the map must fail' );
$poster = local_mobile_layout( 'half', 5 );
$poster['static_poster_under_canvas'] = true;
local_assert( is_wp_error( Tra_Vel_Local_Tourism_Policy::validate_layout( $poster ) ), 'static poster under the Earth must fail' );
$uncontracted_layer = local_mobile_layout( 'half', 5 );
$uncontracted_layer['floating_promo_layer'] = true;
local_assert( 'tra_vel_local_layout_invalid' === Tra_Vel_Local_Tourism_Policy::validate_layout( $uncontracted_layer )->get_error_code(), 'uncontracted floating layers must fail the closed non-stacking layout contract' );
$occluded = local_mobile_layout( 'half', 5 );
$occluded['selected_target_visibility'] = 'occluded';
local_assert( is_wp_error( Tra_Vel_Local_Tourism_Policy::validate_layout( $occluded ) ), 'a sheet cannot hide the selected marker' );
$desktop = local_mobile_layout( 'peek', 1 );
$desktop['viewport_class'] = 'desktop';
$desktop['map_ownership_ratio'] = 0.67;
$desktop['bottom_sheet_state'] = 'absent';
$desktop['side_rail_state'] = 'collapsed';
$desktop['filter_surface_state'] = 'side_rail';
$desktop['selected_content_mode'] = 'collision_aware_anchor';
$desktop['route_progress_mode'] = 'docked_side_rail';
local_assert( true === Tra_Vel_Local_Tourism_Policy::validate_layout( $desktop ), 'desktop must support a two-thirds map with one collision-aware card' );
$desktop['map_ownership_ratio'] = 0.60;
local_assert( is_wp_error( Tra_Vel_Local_Tourism_Policy::validate_layout( $desktop ) ), 'desktop detail cannot squeeze the map below two thirds' );

// Planning data remains useful while prices and availability stay honest.
$item = local_base_item();
$projection = Tra_Vel_Local_Tourism_Policy::evaluate_item( $item, '2026-07-19T09:00:00Z' );
local_assert( ! is_wp_error( $projection ), 'a fully structured planning item must remain usable without a live quote' );
local_assert( 'unranked' === $projection['official_classification_state'] && 'officially_unranked' === $projection['classification_display'], 'an officially unranked property must remain an explicit valid state' );
local_assert( null === $projection['official_classification_level'] && false === $projection['classification_quality_inference_allowed'], 'unranked cannot be converted into a low level or quality inference' );
local_assert( ! in_array( 'find_replacement', $projection['required_actions'], true ), 'voluntary non-participation in ranking cannot exclude an otherwise valid property' );
local_assert( 'exact' === $projection['map_visibility'], 'source-backed coordinates may render an exact point' );
local_assert( 'check_live_availability' === $projection['commercial_display'] && false === $projection['booking_allowed'], 'planning content must request a live check without looking unfinished' );
foreach ( Tra_Vel_Local_Tourism_Taxonomy::FACT_GROUPS as $group ) {
	local_assert( 'unknown' === $projection['fact_states'][ $group ], "{$group} must remain explicitly unknown" );
}

$invented_unranked_level = local_clone( $item );
$invented_unranked_level['classification']['official_level'] = 2;
local_assert( 'tra_vel_local_classification_unranked_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $invented_unranked_level, '2026-07-19T09:00:00Z' )->get_error_code(), 'unranked cannot be converted into an invented star level' );

$unofficial_unranked = local_clone( $item );
$unofficial_unranked['classification']['source_refs'] = array( 'src_aaaaaaaaaaaa' );
local_assert( 'tra_vel_local_classification_source_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $unofficial_unranked, '2026-07-19T09:00:00Z' )->get_error_code(), 'operator or synthetic evidence cannot declare official unranked status' );

$stale_unranked = local_clone( $item );
$stale_unranked['provenance'][1]['freshness_state'] = 'stale';
local_assert( 'tra_vel_local_classification_source_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $stale_unranked, '2026-07-19T09:00:00Z' )->get_error_code(), 'stale official evidence cannot emit a current-looking unranked state' );

$ranked_without_end = local_clone( $item );
$ranked_without_end['classification']['official_state'] = 'ranked';
$ranked_without_end['classification']['official_level'] = 4;
local_assert( 'tra_vel_local_classification_rank_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $ranked_without_end, '2026-07-19T09:00:00Z' )->get_error_code(), 'a current official ranking must carry an active validity end' );

$non_rankable_villa = local_clone( $item );
$non_rankable_villa['inventory_type'] = 'villa';
$non_rankable_villa['classification'] = array(
	'scheme_code'               => 'israel_hotel_ranking',
	'scheme_applicability'      => 'not_applicable',
	'official_state'            => 'not_applicable',
	'official_level'            => null,
	'claimed_category_code'     => 'villa',
	'source_refs'               => array(),
	'observed_at_utc'           => null,
	'valid_to_utc'              => null,
	'quality_inference_allowed' => false,
);
$villa_projection = Tra_Vel_Local_Tourism_Policy::evaluate_item( $non_rankable_villa, '2026-07-19T09:00:00Z' );
local_assert( ! is_wp_error( $villa_projection ) && 'not_applicable' === $villa_projection['official_classification_state'], 'a villa can be explicitly outside the hotel-ranking scheme without becoming unknown or unranked' );

// Runtime validation must be as closed as the JSON schema, not merely require a subset of keys.
$extra_root = local_clone( $item );
$extra_root['uncontracted_hint'] = 'ignore-me';
local_assert( 'tra_vel_local_item_shape_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $extra_root, '2026-07-19T09:00:00Z' )->get_error_code(), 'unknown root fields must fail closed at runtime' );

$bad_version = local_clone( $item );
$bad_version['contract_version'] = '9.9.9';
local_assert( 'tra_vel_local_item_identity_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $bad_version, '2026-07-19T09:00:00Z' )->get_error_code(), 'runtime must reject unsupported contract versions' );

$extra_source = local_clone( $item );
$extra_source['provenance'][0]['raw_supplier_payload'] = array( 'unsafe' => true );
local_assert( 'tra_vel_local_source_identity_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $extra_source, '2026-07-19T09:00:00Z' )->get_error_code(), 'provenance cannot carry an uncontracted raw supplier payload' );

$bad_retrieval = local_clone( $item );
$bad_retrieval['provenance'][0]['retrieved_at_utc'] = 'yesterday';
local_assert( 'tra_vel_local_source_time_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $bad_retrieval, '2026-07-19T09:00:00Z' )->get_error_code(), 'source retrieval time must be validated at runtime' );

$extra_geography = local_clone( $item );
$extra_geography['geography']['poster_url'] = 'https://example.invalid/poster.jpg';
local_assert( 'tra_vel_local_geography_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $extra_geography, '2026-07-19T09:00:00Z' )->get_error_code(), 'geography cannot smuggle a poster field beneath the map contract' );

$extra_point = local_clone( $item );
$extra_point['geography']['primary_point']['marketing_rank'] = 1;
local_assert( 'tra_vel_local_coordinate_evidence_required' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $extra_point, '2026-07-19T09:00:00Z' )->get_error_code(), 'map points must remain closed latitude-longitude objects' );

$extra_fact_group = local_clone( $item );
$extra_fact_group['fit_facts']['kosher']['marketing_badge'] = true;
local_assert( 'tra_vel_local_fact_group_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $extra_fact_group, '2026-07-19T09:00:00Z' )->get_error_code(), 'fit groups cannot accept unverified marketing badges' );

$bad_period = local_clone( $item );
$bad_period['operations']['opening_hours']['periods'][] = array( 'day' => 'someday', 'opens_local' => '25:00', 'closes_local' => '26:00', 'valid_from' => null, 'valid_to' => null );
local_assert( 'tra_vel_local_hours_period_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $bad_period, '2026-07-19T09:00:00Z' )->get_error_code(), 'opening periods must validate canonical days and local clock values' );

$extra_availability = local_clone( $item );
$extra_availability['commercial']['availability']['supplier_note'] = 'trust me';
local_assert( 'tra_vel_local_availability_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $extra_availability, '2026-07-19T09:00:00Z' )->get_error_code(), 'availability cannot accept uncontracted supplier claims' );

$unknown_quantity = local_clone( $item );
$unknown_quantity['commercial']['availability']['quantity_remaining'] = 7;
local_assert( 'tra_vel_local_unverified_quantity_exposed' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $unknown_quantity, '2026-07-19T09:00:00Z' )->get_error_code(), 'unknown inventory cannot expose a persuasive numeric quantity' );

$missing_geo = local_clone( $item );
$missing_geo['geography']['coordinate_state'] = 'unknown';
$missing_geo['geography']['primary_point'] = null;
$missing_geo['geography']['coordinate_source_refs'] = array();
$projection = Tra_Vel_Local_Tourism_Policy::evaluate_item( $missing_geo, '2026-07-19T09:00:00Z' );
local_assert( 'suppressed' === $projection['map_visibility'] && in_array( 'verify_geography', $projection['required_actions'], true ), 'missing geo must fall back to region/list rather than invent a marker' );

$bad_geo = local_clone( $missing_geo );
$bad_geo['geography']['primary_point'] = array( 'latitude' => 31.0, 'longitude' => 35.0 );
local_assert( is_wp_error( Tra_Vel_Local_Tourism_Policy::evaluate_item( $bad_geo, '2026-07-19T09:00:00Z' ) ), 'unknown coordinates cannot expose an authoritative point' );

$customer_geo = local_clone( $item );
$customer_geo['data_mode'] = 'planning_only';
$customer_geo['provenance'][0]['authority'] = 'customer_report';
$customer_geo['provenance'][0]['source_kind'] = 'customer_report';
local_assert( 'tra_vel_local_coordinate_authority_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $customer_geo, '2026-07-19T09:00:00Z' )->get_error_code(), 'customer-reported coordinates cannot be promoted to an exact verified marker' );

$bad_jurisdiction = local_clone( $item );
$bad_jurisdiction['geography']['jurisdiction']['locality'] = array( 'node_ref' => 'geo_locality_test', 'label' => 'Synthetic locality', 'source_refs' => array( 'src_missingmissing' ) );
local_assert( 'tra_vel_local_jurisdiction_source_missing' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $bad_jurisdiction, '2026-07-19T09:00:00Z' )->get_error_code(), 'administrative hierarchy cannot cite a missing source' );

$service_area = local_clone( $item );
$service_area['geography']['service_geometry'] = array(
	'geometry_type' => 'bounds',
	'geometry_ref'  => 'geometry_aaaaaaaa',
	'bounds'        => array( 'north' => 32.1, 'east' => 35.1, 'south' => 31.9, 'west' => 34.9 ),
	'source_refs'   => array( 'src_aaaaaaaaaaaa' ),
);
local_assert( ! is_wp_error( Tra_Vel_Local_Tourism_Policy::evaluate_item( $service_area, '2026-07-19T09:00:00Z' ) ), 'source-backed local service bounds must validate' );
$service_area['geography']['service_geometry']['bounds']['north'] = 31.0;
local_assert( 'tra_vel_local_service_bounds_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $service_area, '2026-07-19T09:00:00Z' )->get_error_code(), 'inverted service bounds must fail closed' );

$numeric_fallback = local_clone( $item );
$numeric_fallback['commercial']['pricing']['state'] = 'checking';
$numeric_fallback['commercial']['pricing']['payable_minor'] = 12345;
local_assert( 'tra_vel_local_non_live_price_exposed' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $numeric_fallback, '2026-07-19T09:00:00Z' )->get_error_code(), 'checking state cannot expose a fictional numeric amount' );

$untrusted_total = local_clone( $item );
$untrusted_total['commercial']['availability'] = array(
	'state' => 'available_verified', 'inventory_scope' => 'unit', 'source_ref' => 'src_aaaaaaaaaaaa',
	'observed_at_utc' => '2026-07-19T08:59:00Z', 'expires_at_utc' => '2026-07-19T09:05:00Z', 'quantity_remaining' => 1, 'bookable' => false,
);
$untrusted_total['commercial']['pricing'] = array(
	'state' => 'verified_quote', 'currency' => 'ILS', 'payable_minor' => 12345, 'pay_later_minor' => 0,
	'quote_ref' => 'quote_aaaaaaaaaaaa', 'source_ref' => 'src_aaaaaaaaaaaa', 'quoted_at_utc' => '2026-07-19T08:59:00Z', 'expires_at_utc' => '2026-07-19T09:05:00Z', 'bookable' => false,
);
local_assert( 'tra_vel_local_availability_not_live' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $untrusted_total, '2026-07-19T09:00:00Z' )->get_error_code(), 'synthetic or planning data cannot label availability or a numeric total as verified' );

$fake_bookable = local_clone( $item );
$fake_bookable['commercial']['availability']['bookable'] = true;
local_assert( 'tra_vel_local_unverified_availability_bookable' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $fake_bookable, '2026-07-19T09:00:00Z' )->get_error_code(), 'unknown availability cannot become checkout inventory' );

// Test-only provider response verifies the positive boundary without seeding a public claim.
$live = local_clone( $item );
$live['data_mode'] = 'live_provider_response';
$live['provenance'][0] = local_source( 'src_aaaaaaaaaaaa', 'provider_authorized_api' );
$live['commercial']['availability'] = array(
	'state' => 'available_verified', 'inventory_scope' => 'unit', 'source_ref' => 'src_aaaaaaaaaaaa',
	'observed_at_utc' => '2026-07-19T08:59:00Z', 'expires_at_utc' => '2026-07-19T09:05:00Z', 'quantity_remaining' => 1, 'bookable' => true,
);
$live['commercial']['pricing'] = array(
	'state' => 'verified_quote', 'currency' => 'ILS', 'payable_minor' => 12345, 'pay_later_minor' => 0,
	'quote_ref' => 'quote_aaaaaaaaaaaa', 'source_ref' => 'src_aaaaaaaaaaaa', 'quoted_at_utc' => '2026-07-19T08:59:00Z', 'expires_at_utc' => '2026-07-19T09:05:00Z', 'bookable' => true,
);
$projection = Tra_Vel_Local_Tourism_Policy::evaluate_item( $live, '2026-07-19T09:00:00Z' );
local_assert( ! is_wp_error( $projection ) && true === $projection['booking_allowed'] && 'verified_total' === $projection['commercial_display'], 'only a current authorized response may expose a checkout total' );

$registry_price = local_clone( $live );
$registry_price['provenance'][0]['authority'] = 'official_registry';
$registry_price['provenance'][0]['source_kind'] = 'registry_record';
local_assert( 'tra_vel_local_availability_not_live' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $registry_price, '2026-07-19T09:00:00Z' )->get_error_code(), 'an official registry cannot stand in for live inventory' );

$stale_hours = local_clone( $live );
$stale_hours['operations']['opening_hours']['state'] = 'stale';
$stale_hours['operations']['opening_hours']['expires_at_utc'] = '2026-07-19T08:30:00Z';
$projection = Tra_Vel_Local_Tourism_Policy::evaluate_item( $stale_hours, '2026-07-19T09:00:00Z' );
local_assert( false === $projection['booking_allowed'] && in_array( 'verify_hours', $projection['required_actions'], true ), 'stale hours must preserve discovery while blocking commercial commitment' );

$unavailable = local_clone( $item );
$unavailable['commercial']['availability'] = array(
	'state' => 'unavailable_verified', 'inventory_scope' => 'property', 'source_ref' => 'src_aaaaaaaaaaaa',
	'observed_at_utc' => '2026-07-19T08:59:00Z', 'expires_at_utc' => '2026-07-19T09:05:00Z', 'quantity_remaining' => 0, 'bookable' => false,
);
$unavailable['commercial']['pricing']['state'] = 'unavailable';
$projection = Tra_Vel_Local_Tourism_Policy::evaluate_item( $unavailable, '2026-07-19T09:00:00Z' );
local_assert( 'unavailable' === $projection['commercial_display'] && in_array( 'find_replacement', $projection['required_actions'], true ), 'unavailable property must trigger replacement rather than checkout' );

// Conflicting high-risk claims stay quarantined and never pass a requirement filter.
$conflict = local_clone( $item );
$conflict['provenance'][] = local_source( 'src_cccccccccccc', 'operator_confirmation' );
$conflict['fit_facts']['accessibility'] = array(
	'group_state' => 'conflict', 'next_action' => 'resolve_conflict', 'last_reviewed_at_utc' => '2026-07-19T08:00:00Z',
	'assertions' => array( local_fact_assertion( 'accessible_entrance', 'conflict', array( 'src_aaaaaaaaaaaa', 'src_cccccccccccc' ) ) ),
);
$projection = Tra_Vel_Local_Tourism_Policy::evaluate_item( $conflict, '2026-07-19T09:00:00Z' );
local_assert( ! is_wp_error( $projection ) && 'conflict' === $projection['fact_states']['accessibility'], 'conflicting accessibility evidence must remain visible as conflict' );
$fit = Tra_Vel_Local_Tourism_Policy::evaluate_fit( $conflict, array( 'accessibility' => array( 'accessible_entrance' => true ) ) );
local_assert( false === $fit['matches'] && 'blocked_conflict' === $fit['decisions']['accessibility']['accessible_entrance'], 'accessibility conflict cannot pass the filter' );
$fit = Tra_Vel_Local_Tourism_Policy::evaluate_fit( $item, array( 'kosher' => array( 'kosher_certificate' => true ) ) );
local_assert( false === $fit['matches'] && 'blocked_unverified' === $fit['decisions']['kosher']['kosher_certificate'], 'unknown kashrut cannot pass as kosher' );
local_assert( is_wp_error( Tra_Vel_Local_Tourism_Policy::evaluate_fit( $item, array( 'accessibility' => true ) ) ), 'broad accessible labels must be rejected in favor of exact dimensions' );

$verified_family = local_clone( $item );
$verified_family['fit_facts']['family'] = array(
	'group_state' => 'evidence_current', 'next_action' => 'none', 'last_reviewed_at_utc' => '2026-07-19T08:00:00Z',
	'assertions' => array( local_fact_assertion( 'children_allowed', 'verified_true', array( 'src_aaaaaaaaaaaa' ) ) ),
);
$projection = Tra_Vel_Local_Tourism_Policy::evaluate_item( $verified_family, '2026-07-19T09:00:00Z' );
local_assert( ! is_wp_error( $projection ), 'evidence-backed family fact should validate' );
$fit = Tra_Vel_Local_Tourism_Policy::evaluate_fit( $verified_family, array( 'family' => array( 'children_allowed' => true ) ) );
local_assert( true === $fit['matches'] && 'matched_verified' === $fit['decisions']['family']['children_allowed'], 'only an exact verified fact may match' );

$bad_fact = local_clone( $verified_family );
$bad_fact['fit_facts']['family']['assertions'][0]['code'] = 'fully_family_friendly';
local_assert( 'tra_vel_local_fact_assertion_invalid' === Tra_Vel_Local_Tourism_Policy::evaluate_item( $bad_fact, '2026-07-19T09:00:00Z' )->get_error_code(), 'marketing-style invented fact codes must fail closed' );

// Every required stress case preserves context and map controls.
foreach ( Tra_Vel_Local_Tourism_Taxonomy::STRESS_TRIGGERS as $trigger ) {
	$response = Tra_Vel_Local_Tourism_Policy::stress_response( $trigger );
	local_assert( ! is_wp_error( $response ), "{$trigger} must have a deterministic recovery" );
	local_assert( true === $response['map_controls_available'] && true === $response['trip_context_preserved'] && false === $response['numeric_price_claim_allowed'], "{$trigger} must preserve controls and trip context without fallback prices" );
}
local_assert( 'suppress_exact_marker' === Tra_Vel_Local_Tourism_Policy::stress_response( 'missing_geo' )['decision'], 'missing geo must suppress the exact marker' );
local_assert( 'block_requirement_match' === Tra_Vel_Local_Tourism_Policy::stress_response( 'conflicting_kashrut' )['commerce_action'], 'kashrut conflict must quarantine the match' );
local_assert( 'replace_item' === Tra_Vel_Local_Tourism_Policy::stress_response( 'unavailable_property' )['commerce_action'], 'unavailable property must search replacements' );
local_assert( 'degraded_tiles' === Tra_Vel_Local_Tourism_Policy::stress_response( 'map_tile_failure' )['map_mode'], 'tile failure must keep a degraded map surface' );
local_assert( 'none' === Tra_Vel_Local_Tourism_Policy::stress_response( 'reduced_motion' )['required_user_action'], 'reduced motion must not burden the traveler' );
local_assert( 'block_checkout' === Tra_Vel_Local_Tourism_Policy::stress_response( 'offline' )['commerce_action'], 'offline mode must prevent a stale commercial commit' );
local_assert( is_wp_error( Tra_Vel_Local_Tourism_Policy::stress_response( 'make_it_up' ) ), 'unknown stress triggers must fail closed' );

echo "Local tourism runtime passed ({$assertions} assertions; 14 inventory types; 8 stress recoveries).\n";
