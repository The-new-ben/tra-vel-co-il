<?php
/**
 * Fail-closed truth, fit, geography and non-stacking layout policy.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Local_Tourism_Policy {
	const STRESS_RESPONSES = array(
		'missing_geo' => array(
			'decision'             => 'suppress_exact_marker',
			'map_mode'             => 'region_or_list_fallback',
			'commerce_action'      => 'keep_read_only',
			'required_user_action' => 'confirm_location',
			'customer_message_key' => 'local_location_needs_confirmation',
			'audit_event_code'     => 'local_geo_verification_required',
		),
		'stale_hours' => array(
			'decision'             => 'show_stale_and_reverify',
			'map_mode'             => 'preserve_map_context',
			'commerce_action'      => 'revalidate',
			'required_user_action' => 'verify_hours',
			'customer_message_key' => 'local_hours_check_in_progress',
			'audit_event_code'     => 'local_hours_revalidation_required',
		),
		'conflicting_accessibility' => array(
			'decision'             => 'quarantine_accessibility_claim',
			'map_mode'             => 'preserve_map_context',
			'commerce_action'      => 'block_requirement_match',
			'required_user_action' => 'resolve_claim',
			'customer_message_key' => 'local_accessibility_confirmation_needed',
			'audit_event_code'     => 'local_accessibility_conflict',
		),
		'conflicting_kashrut' => array(
			'decision'             => 'quarantine_kashrut_claim',
			'map_mode'             => 'preserve_map_context',
			'commerce_action'      => 'block_requirement_match',
			'required_user_action' => 'resolve_claim',
			'customer_message_key' => 'local_kashrut_confirmation_needed',
			'audit_event_code'     => 'local_kashrut_conflict',
		),
		'unavailable_property' => array(
			'decision'             => 'preserve_trip_and_find_replacement',
			'map_mode'             => 'show_unavailable_with_alternatives',
			'commerce_action'      => 'replace_item',
			'required_user_action' => 'reselect_item',
			'customer_message_key' => 'local_replacement_options_loading',
			'audit_event_code'     => 'local_inventory_unavailable',
		),
		'map_tile_failure' => array(
			'decision'             => 'degrade_map_keep_controls',
			'map_mode'             => 'degraded_tiles',
			'commerce_action'      => 'keep_read_only',
			'required_user_action' => 'retry_tiles',
			'customer_message_key' => 'local_map_details_temporarily_reduced',
			'audit_event_code'     => 'local_map_tiles_degraded',
		),
		'reduced_motion' => array(
			'decision'             => 'remove_motion_keep_progress',
			'map_mode'             => 'instant_transition_with_progress',
			'commerce_action'      => 'none',
			'required_user_action' => 'none',
			'customer_message_key' => 'local_progress_continues_without_motion',
			'audit_event_code'     => 'local_map_reduced_motion_applied',
		),
		'offline' => array(
			'decision'             => 'use_cache_block_commercial_commit',
			'map_mode'             => 'offline_cached',
			'commerce_action'      => 'block_checkout',
			'required_user_action' => 'connect_network',
			'customer_message_key' => 'local_offline_plan_available',
			'audit_event_code'     => 'local_map_offline_read_only',
		),
	);

	/**
	 * Validate and project one local item without presenting unknown facts as true.
	 *
	 * @return array|WP_Error
	 */
	public static function evaluate_item( $item, $now_utc ) {
		if ( ! is_array( $item ) || ! self::is_iso_time( $now_utc ) ) {
			return self::error( 'tra_vel_local_item_invalid', 'A structured local item and a UTC evaluation time are required.' );
		}
		$required = array( 'contract_version', 'item_ref', 'operator_ref', 'data_mode', 'inventory_type', 'variant_code', 'title', 'classification', 'geography', 'provenance', 'fit_facts', 'operations', 'commercial' );
		if ( ! self::has_exact_keys( $item, $required ) ) {
			return self::error( 'tra_vel_local_item_shape_invalid', 'The local item must match the closed item contract.' );
		}
		if ( '1.0.0' !== $item['contract_version'] || ! preg_match( '/^local_item_[a-z0-9]{12,64}$/', (string) $item['item_ref'] ) || ! preg_match( '/^party_[a-z0-9]{12,64}$/', (string) $item['operator_ref'] ) ) {
			return self::error( 'tra_vel_local_item_identity_invalid', 'Local item identities must be opaque contract references.' );
		}
		if ( null !== $item['variant_code'] && ( ! is_string( $item['variant_code'] ) || ! preg_match( '/^[a-z0-9_-]{2,80}$/', $item['variant_code'] ) ) ) {
			return self::error( 'tra_vel_local_item_variant_invalid', 'A local inventory variant must be null or a canonical code.' );
		}
		if ( null !== $item['title'] && ( ! is_string( $item['title'] ) || self::text_length( $item['title'] ) < 2 || self::text_length( $item['title'] ) > 180 ) ) {
			return self::error( 'tra_vel_local_item_title_invalid', 'A local item title must be null or between 2 and 180 characters.' );
		}
		$data_mode      = Tra_Vel_Local_Tourism_Taxonomy::member( $item['data_mode'], Tra_Vel_Local_Tourism_Taxonomy::DATA_MODES );
		$inventory_type = Tra_Vel_Local_Tourism_Taxonomy::inventory_type( $item['inventory_type'] );
		if ( ! $data_mode || ! $inventory_type ) {
			return self::error( 'tra_vel_local_item_taxonomy_invalid', 'The item uses an unsupported data mode or inventory type.' );
		}

		$sources = self::source_map( isset( $item['provenance'] ) ? $item['provenance'] : null, $now_utc );
		if ( is_wp_error( $sources ) ) {
			return $sources;
		}
		$classification = self::evaluate_classification( isset( $item['classification'] ) ? $item['classification'] : null, $sources, $inventory_type, $now_utc );
		if ( is_wp_error( $classification ) ) {
			return $classification;
		}
		$geography = self::evaluate_geography( isset( $item['geography'] ) ? $item['geography'] : null, $sources, $data_mode );
		if ( is_wp_error( $geography ) ) {
			return $geography;
		}
		$facts = self::validate_facts( isset( $item['fit_facts'] ) ? $item['fit_facts'] : null, $sources );
		if ( is_wp_error( $facts ) ) {
			return $facts;
		}
		$operations = self::evaluate_operations( isset( $item['operations'] ) ? $item['operations'] : null, $sources, $now_utc );
		if ( is_wp_error( $operations ) ) {
			return $operations;
		}
		$commercial = self::evaluate_commercial( isset( $item['commercial'] ) ? $item['commercial'] : null, $sources, $data_mode, $now_utc );
		if ( is_wp_error( $commercial ) ) {
			return $commercial;
		}

		$required_actions = array();
		if ( 'suppressed' === $geography['map_visibility'] ) {
			$required_actions[] = 'verify_geography';
		}
		if ( 'revalidate' === $operations['hours_action'] ) {
			$required_actions[] = 'verify_hours';
		}
		if ( 'closed' === $operations['operating_action'] ) {
			$required_actions[] = 'find_replacement';
		} elseif ( 'revalidate' === $operations['operating_action'] ) {
			$required_actions[] = 'verify_operating_state';
		}
		if ( 'unavailable_verified' === $commercial['availability_state'] ) {
			$required_actions[] = 'find_replacement';
		}
		if ( ! $commercial['booking_allowed'] && ! in_array( 'find_replacement', $required_actions, true ) ) {
			$required_actions[] = 'check_live_availability';
		}

		return array(
			'item_ref'           => $item['item_ref'],
			'inventory_type'     => $inventory_type,
			'inventory_family'   => Tra_Vel_Local_Tourism_Taxonomy::inventory_family( $inventory_type ),
			'official_classification_state' => $classification['state'],
			'official_classification_level' => $classification['level'],
			'classification_display' => $classification['display_state'],
			'claimed_category_code' => $classification['claimed_category_code'],
			'classification_quality_inference_allowed' => false,
			'map_visibility'     => $geography['map_visibility'],
			'hours_action'       => $operations['hours_action'],
			'commercial_display' => $commercial['display_state'],
			'booking_allowed'    => $commercial['booking_allowed'] && 'current' === $operations['hours_action'] && 'open' === $operations['operating_action'],
			'required_actions'   => array_values( array_unique( $required_actions ) ),
			'fact_states'        => $facts,
		);
	}

	/**
	 * Keep an official accommodation ranking separate from an operator category
	 * and from every customer-quality signal. Voluntary non-participation is never
	 * converted into a low score or a commercial exclusion.
	 *
	 * @return array|WP_Error
	 */
	private static function evaluate_classification( $classification, $sources, $inventory_type, $now_utc ) {
		$required = array( 'scheme_code', 'scheme_applicability', 'official_state', 'official_level', 'claimed_category_code', 'source_refs', 'observed_at_utc', 'valid_to_utc', 'quality_inference_allowed' );
		if ( ! self::has_exact_keys( $classification, $required ) || 'israel_hotel_ranking' !== $classification['scheme_code'] || ! in_array( $classification['scheme_applicability'], array( 'applicable', 'not_applicable', 'unknown' ), true ) || ! in_array( $classification['official_state'], Tra_Vel_Local_Tourism_Taxonomy::OFFICIAL_CLASSIFICATION_STATES, true ) || ! self::valid_nullable_code( $classification['claimed_category_code'], '/^[a-z0-9_-]{2,80}$/' ) || ! self::is_unique_list( $classification['source_refs'] ) || ! self::valid_nullable_time( $classification['observed_at_utc'] ) || ! self::valid_nullable_time( $classification['valid_to_utc'] ) || false !== $classification['quality_inference_allowed'] ) {
			return self::error( 'tra_vel_local_classification_invalid', 'Official classification must be closed, sourced, and independent from quality.' );
		}
		if ( null !== $classification['official_level'] && ( ! is_int( $classification['official_level'] ) || $classification['official_level'] < 1 || $classification['official_level'] > 5 ) ) {
			return self::error( 'tra_vel_local_classification_level_invalid', 'An official ranking level must be null or an integer from one to five.' );
		}
		foreach ( $classification['source_refs'] as $source_ref ) {
			if ( ! isset( $sources[ $source_ref ] ) || ! in_array( $sources[ $source_ref ]['authority'], array( 'official_registry', 'official_information' ), true ) || true !== $sources[ $source_ref ]['is_current_now'] ) {
				return self::error( 'tra_vel_local_classification_source_invalid', 'Official ranking states may cite only current, effective official registry or information sources.' );
			}
		}

		$state = $classification['official_state'];
		if ( 'not_applicable' === $state ) {
			if ( 'not_applicable' !== $classification['scheme_applicability'] || null !== $classification['official_level'] || $classification['source_refs'] || null !== $classification['observed_at_utc'] || null !== $classification['valid_to_utc'] ) {
				return self::error( 'tra_vel_local_classification_applicability_invalid', 'A non-applicable ranking scheme cannot carry an official level or ranking evidence.' );
			}
		} elseif ( 'unknown' === $state ) {
			if ( 'unknown' !== $classification['scheme_applicability'] ) {
				return self::error( 'tra_vel_local_classification_applicability_invalid', 'Unknown scheme eligibility must remain explicitly unknown.' );
			}
		} elseif ( 'applicable' !== $classification['scheme_applicability'] ) {
			return self::error( 'tra_vel_local_classification_applicability_invalid', 'An official ranked, unranked, expired, or conflicting state requires explicit scheme applicability.' );
		}
		if ( 'ranked' === $state && ( null === $classification['official_level'] || ! $classification['source_refs'] || null === $classification['observed_at_utc'] || null === $classification['valid_to_utc'] || strtotime( $classification['valid_to_utc'] ) <= strtotime( $now_utc ) ) ) {
			return self::error( 'tra_vel_local_classification_rank_invalid', 'A current official ranking needs a level, official evidence, observation time, and a current validity window.' );
		}
		if ( 'unranked' === $state && ( null !== $classification['official_level'] || ! $classification['source_refs'] || null === $classification['observed_at_utc'] ) ) {
			return self::error( 'tra_vel_local_classification_unranked_invalid', 'An officially unranked property needs official evidence but can never carry an invented level.' );
		}
		if ( 'expired' === $state && ( ! $classification['source_refs'] || null === $classification['observed_at_utc'] || null === $classification['valid_to_utc'] || strtotime( $classification['valid_to_utc'] ) > strtotime( $now_utc ) ) ) {
			return self::error( 'tra_vel_local_classification_expiry_invalid', 'An expired ranking needs official evidence and an elapsed validity end.' );
		}
		if ( 'unknown' === $state && ( null !== $classification['official_level'] || $classification['source_refs'] || null !== $classification['observed_at_utc'] || null !== $classification['valid_to_utc'] ) ) {
			return self::error( 'tra_vel_local_classification_unknown_invalid', 'An unknown ranking cannot carry official evidence or a level.' );
		}
		if ( 'conflict' === $state && count( $classification['source_refs'] ) < 2 ) {
			return self::error( 'tra_vel_local_classification_conflict_invalid', 'A classification conflict needs at least two official sources.' );
		}
		$display = array(
			'ranked'         => 'official_ranked',
			'unranked'       => 'officially_unranked',
			'expired'        => 'official_ranking_expired',
			'unknown'        => 'classification_unknown',
			'conflict'       => 'classification_conflict',
			'not_applicable' => 'not_applicable',
		);
		return array(
			'state'                 => $state,
			'level'                 => 'ranked' === $state ? $classification['official_level'] : null,
			'claimed_category_code' => $classification['claimed_category_code'],
			'display_state'         => $display[ $state ],
		);
	}

	/**
	 * Match exact requested fact codes. Broad labels such as "accessible" never pass.
	 *
	 * @return array|WP_Error
	 */
	public static function evaluate_fit( $item, $requirements ) {
		if ( ! is_array( $item ) || ! is_array( $requirements ) || ! isset( $item['fit_facts'] ) ) {
			return self::error( 'tra_vel_local_fit_request_invalid', 'Fit evaluation requires an item and explicit requirement groups.' );
		}
		$decisions = array();
		$matches   = true;
		foreach ( $requirements as $group => $required_facts ) {
			$group = Tra_Vel_Local_Tourism_Taxonomy::member( $group, Tra_Vel_Local_Tourism_Taxonomy::FACT_GROUPS );
			if ( ! $group || ! is_array( $required_facts ) || ! $required_facts ) {
				return self::error( 'tra_vel_local_fit_requirement_invalid', 'Every fit requirement must name exact canonical facts.' );
			}
			$assertion_map = array();
			foreach ( (array) $item['fit_facts'][ $group ]['assertions'] as $assertion ) {
				if ( isset( $assertion['code'], $assertion['state'] ) ) {
					$assertion_map[ $assertion['code'] ] = $assertion['state'];
				}
			}
			$decisions[ $group ] = array();
			foreach ( $required_facts as $code => $required_value ) {
				$code = Tra_Vel_Local_Tourism_Taxonomy::fact_code( $group, $code );
				if ( ! $code || ! is_bool( $required_value ) ) {
					return self::error( 'tra_vel_local_fit_fact_invalid', 'Fit requirements must use a canonical fact code and boolean target.' );
				}
				$state = isset( $assertion_map[ $code ] ) ? $assertion_map[ $code ] : 'unknown';
				if ( 'conflict' === $state ) {
					$decision = 'blocked_conflict';
				} elseif ( ! in_array( $state, array( 'verified_true', 'verified_false' ), true ) ) {
					$decision = 'blocked_unverified';
				} else {
					$actual   = 'verified_true' === $state;
					$decision = $actual === $required_value ? 'matched_verified' : 'not_matched_verified';
				}
				$decisions[ $group ][ $code ] = $decision;
				$matches = $matches && 'matched_verified' === $decision;
			}
		}
		return array( 'matches' => $matches, 'decisions' => $decisions );
	}

	/**
	 * Enforce non-overlapping map surfaces as data, including mobile RTL.
	 *
	 * @return true|WP_Error
	 */
	public static function validate_layout( $layout, $previous = null ) {
		$required = array( 'viewport_class', 'reading_direction', 'logical_edge_model', 'map_surface_role', 'map_ownership_ratio', 'bottom_sheet_state', 'side_rail_state', 'filter_surface_state', 'selected_content_mode', 'route_progress_mode', 'overlay_stack_depth', 'context_card_count', 'map_controls_zone', 'map_controls_available', 'map_pan_available', 'static_poster_under_canvas', 'requires_second_entry_click', 'safe_area_reflow_state', 'safe_area_revision', 'selected_target_visibility' );
		if ( ! self::has_exact_keys( $layout, $required ) ) {
			return self::error( 'tra_vel_local_layout_invalid', 'The complete local-map layout state is required.' );
		}
		$viewport = Tra_Vel_Local_Tourism_Taxonomy::member( $layout['viewport_class'], Tra_Vel_Local_Tourism_Taxonomy::VIEWPORT_CLASSES );
		$direction = Tra_Vel_Local_Tourism_Taxonomy::member( $layout['reading_direction'], Tra_Vel_Local_Tourism_Taxonomy::READING_DIRECTIONS );
		if ( ! $viewport || ! $direction || 'logical_start_end' !== $layout['logical_edge_model'] || 'primary' !== $layout['map_surface_role'] ) {
			return self::error( 'tra_vel_local_layout_taxonomy_invalid', 'Layout must use logical edges and keep the map as the primary surface.' );
		}
		if ( ! is_numeric( $layout['map_ownership_ratio'] ) || $layout['map_ownership_ratio'] < 0 || $layout['map_ownership_ratio'] > 1 || ! is_int( $layout['overlay_stack_depth'] ) || $layout['overlay_stack_depth'] < 0 || $layout['overlay_stack_depth'] > 1 || ! is_int( $layout['context_card_count'] ) || $layout['context_card_count'] < 0 || $layout['context_card_count'] > 1 ) {
			return self::error( 'tra_vel_local_layout_density_invalid', 'The map ratio, overlay depth, or context-card count violates the non-stacking contract.' );
		}
		if ( true !== $layout['map_controls_available'] || true !== $layout['map_pan_available'] || false !== $layout['static_poster_under_canvas'] || false !== $layout['requires_second_entry_click'] || 'occluded' === $layout['selected_target_visibility'] || 'failed' === $layout['safe_area_reflow_state'] ) {
			return self::error( 'tra_vel_local_layout_interaction_blocked', 'Map controls, direct entry, and the selected target must remain usable.' );
		}

		if ( 'desktop' === $viewport ) {
			if ( $layout['map_ownership_ratio'] < ( 2 / 3 ) || 'absent' !== $layout['bottom_sheet_state'] || ! in_array( $layout['side_rail_state'], array( 'collapsed', 'open' ), true ) || 'side_rail' !== $layout['filter_surface_state'] || ! in_array( $layout['selected_content_mode'], array( 'collision_aware_anchor', 'docked_side_rail' ), true ) ) {
				return self::error( 'tra_vel_local_desktop_layout_invalid', 'Desktop must reserve at least two thirds for the map and use a non-overlapping rail or collision-aware card.' );
			}
		} elseif ( 'mobile' === $viewport ) {
			if ( 'absent' === $layout['bottom_sheet_state'] || 'absent' !== $layout['side_rail_state'] || ! in_array( $layout['filter_surface_state'], array( 'closed', 'dedicated_sheet' ), true ) || 'mobile_bottom_sheet' !== $layout['selected_content_mode'] || ! in_array( $layout['route_progress_mode'], array( 'hidden', 'narrow_top_strip' ), true ) ) {
				return self::error( 'tra_vel_local_mobile_layout_invalid', 'Mobile must use one reflow-aware bottom sheet and no side rail.' );
			}
		}

		if ( null !== $previous ) {
			if ( ! is_array( $previous ) || ! isset( $previous['bottom_sheet_state'], $previous['safe_area_revision'] ) || ! is_int( $layout['safe_area_revision'] ) || ! is_int( $previous['safe_area_revision'] ) ) {
				return self::error( 'tra_vel_local_layout_previous_invalid', 'Previous layout state is malformed.' );
			}
			if ( $previous['bottom_sheet_state'] !== $layout['bottom_sheet_state'] && ( $layout['safe_area_revision'] <= $previous['safe_area_revision'] || 'applied' !== $layout['safe_area_reflow_state'] ) ) {
				return self::error( 'tra_vel_local_safe_area_not_reflowed', 'Changing the sheet must advance and apply the map safe area.' );
			}
		}
		return true;
	}

	/**
	 * Deterministic recovery contract for required stress scenarios.
	 *
	 * @return array|WP_Error
	 */
	public static function stress_response( $trigger ) {
		$trigger = Tra_Vel_Local_Tourism_Taxonomy::stress_trigger( $trigger );
		if ( ! $trigger || ! isset( self::STRESS_RESPONSES[ $trigger ] ) ) {
			return self::error( 'tra_vel_local_stress_trigger_invalid', 'The stress trigger is not in the local-tourism contract.' );
		}
		return array_merge( array( 'trigger' => $trigger, 'map_controls_available' => true, 'trip_context_preserved' => true, 'numeric_price_claim_allowed' => false ), self::STRESS_RESPONSES[ $trigger ] );
	}

	/**
	 * Build and validate a source index.
	 *
	 * @return array|WP_Error
	 */
	private static function source_map( $sources, $now_utc ) {
		if ( ! self::is_list( $sources ) || ! $sources ) {
			return self::error( 'tra_vel_local_sources_invalid', 'At least one provenance source is required.' );
		}
		$map = array();
		$required = array( 'source_ref', 'authority', 'source_kind', 'owner_ref', 'canonical_uri', 'observed_at_utc', 'retrieved_at_utc', 'effective_from', 'effective_to', 'expires_at_utc', 'freshness_state', 'content_digest' );
		foreach ( $sources as $source ) {
			if ( ! self::has_exact_keys( $source, $required ) || ! preg_match( '/^src_[a-z0-9]{12,64}$/', (string) $source['source_ref'] ) || ! preg_match( '/^party_[a-z0-9]{12,64}$/', (string) $source['owner_ref'] ) || isset( $map[ $source['source_ref'] ] ) ) {
				return self::error( 'tra_vel_local_source_identity_invalid', 'Provenance sources require unique opaque references.' );
			}
			if ( ! Tra_Vel_Local_Tourism_Taxonomy::member( $source['authority'], Tra_Vel_Local_Tourism_Taxonomy::SOURCE_AUTHORITIES ) || ! Tra_Vel_Local_Tourism_Taxonomy::member( $source['source_kind'], Tra_Vel_Local_Tourism_Taxonomy::SOURCE_KINDS ) || ! Tra_Vel_Local_Tourism_Taxonomy::member( $source['freshness_state'], Tra_Vel_Local_Tourism_Taxonomy::FRESHNESS_STATES ) || ! preg_match( '/^sha256:[a-f0-9]{64}$/', (string) $source['content_digest'] ) ) {
				return self::error( 'tra_vel_local_source_taxonomy_invalid', 'A provenance source has invalid authority, kind, freshness, or digest.' );
			}
			if ( null !== $source['canonical_uri'] && ( ! is_string( $source['canonical_uri'] ) || false === filter_var( $source['canonical_uri'], FILTER_VALIDATE_URL ) ) ) {
				return self::error( 'tra_vel_local_source_uri_invalid', 'Source canonical URI must be null or an absolute URI.' );
			}
			if ( null !== $source['observed_at_utc'] && ! self::is_iso_time( $source['observed_at_utc'] ) ) {
				return self::error( 'tra_vel_local_source_time_invalid', 'Source observation time must be UTC.' );
			}
			if ( ! self::is_iso_time( $source['retrieved_at_utc'] ) || ( null !== $source['effective_from'] && ! self::is_iso_time( $source['effective_from'] ) ) || ( null !== $source['effective_to'] && ! self::is_iso_time( $source['effective_to'] ) ) ) {
				return self::error( 'tra_vel_local_source_time_invalid', 'Source retrieval and effective times must be UTC.' );
			}
			if ( null !== $source['expires_at_utc'] && ! self::is_iso_time( $source['expires_at_utc'] ) ) {
				return self::error( 'tra_vel_local_source_expiry_invalid', 'Source expiry must be UTC.' );
			}
			if ( null !== $source['effective_from'] && null !== $source['effective_to'] && strtotime( $source['effective_to'] ) < strtotime( $source['effective_from'] ) ) {
				return self::error( 'tra_vel_local_source_effective_window_invalid', 'Source effective-to time cannot precede effective-from.' );
			}
			$source['is_current_now'] = 'current' === $source['freshness_state']
				&& ( null === $source['expires_at_utc'] || strtotime( $source['expires_at_utc'] ) > strtotime( $now_utc ) )
				&& ( null === $source['effective_from'] || strtotime( $source['effective_from'] ) <= strtotime( $now_utc ) )
				&& ( null === $source['effective_to'] || strtotime( $source['effective_to'] ) >= strtotime( $now_utc ) );
			$map[ $source['source_ref'] ] = $source;
		}
		return $map;
	}

	/**
	 * Validate jurisdiction, navigation regions and coordinate provenance.
	 *
	 * @return array|WP_Error
	 */
	private static function evaluate_geography( $geography, $sources, $data_mode ) {
		$required = array( 'hierarchy_state', 'jurisdiction', 'travel_regions', 'coordinate_state', 'primary_point', 'coordinate_source_refs', 'entrances', 'service_geometry' );
		if ( ! self::has_exact_keys( $geography, $required ) || ! is_array( $geography['jurisdiction'] ) || 'IL' !== ( $geography['jurisdiction']['country_code'] ?? '' ) ) {
			return self::error( 'tra_vel_local_geography_invalid', 'Local geography must preserve the canonical Israeli jurisdiction.' );
		}
		if ( ! in_array( $geography['hierarchy_state'], array( 'complete', 'partial', 'unknown', 'conflict' ), true ) || ! self::is_list( $geography['travel_regions'] ) || ! self::is_unique_list( $geography['coordinate_source_refs'] ) || ! self::is_list( $geography['entrances'] ) ) {
			return self::error( 'tra_vel_local_geography_shape_invalid', 'Local hierarchy and geometry collections must be canonical lists.' );
		}
		$jurisdiction_required = array( 'country_code', 'district', 'municipality', 'locality', 'neighborhood', 'address' );
		if ( ! self::has_exact_keys( $geography['jurisdiction'], $jurisdiction_required ) ) {
			return self::error( 'tra_vel_local_jurisdiction_incomplete', 'Every jurisdiction level must be present, using null when unknown.' );
		}
		$known_jurisdiction_nodes = 0;
		$seen_geo_nodes           = array();
		foreach ( array( 'district', 'municipality', 'locality', 'neighborhood', 'address' ) as $level ) {
			$node = $geography['jurisdiction'][ $level ];
			if ( null === $node ) {
				continue;
			}
			if ( ! self::has_exact_keys( $node, array( 'node_ref', 'label', 'source_refs' ) ) || ! preg_match( '/^geo_[a-z0-9_-]{4,80}$/', (string) $node['node_ref'] ) || ! is_string( $node['label'] ) || '' === trim( $node['label'] ) || self::text_length( $node['label'] ) > 180 || ! self::is_unique_list( $node['source_refs'] ) || ! $node['source_refs'] || isset( $seen_geo_nodes[ $node['node_ref'] ] ) ) {
				return self::error( 'tra_vel_local_jurisdiction_node_invalid', 'Known jurisdiction nodes require unique identities, labels, and provenance.' );
			}
			foreach ( $node['source_refs'] as $source_ref ) {
				if ( ! isset( $sources[ $source_ref ] ) ) {
					return self::error( 'tra_vel_local_jurisdiction_source_missing', 'Jurisdiction evidence must reference retained provenance.' );
				}
			}
			$seen_geo_nodes[ $node['node_ref'] ] = true;
			$known_jurisdiction_nodes++;
		}
		if ( 'unknown' === $geography['hierarchy_state'] && $known_jurisdiction_nodes ) {
			return self::error( 'tra_vel_local_unknown_hierarchy_claim_invalid', 'Unknown hierarchy cannot expose administrative nodes as verified.' );
		}
		$seen_regions = array();
		foreach ( $geography['travel_regions'] as $region ) {
			if ( ! self::has_exact_keys( $region, array( 'region_code', 'label', 'boundary_kind', 'source_refs' ) ) || ! preg_match( '/^il-travel-[a-z0-9-]{2,80}$/', (string) $region['region_code'] ) || ! is_string( $region['label'] ) || self::text_length( $region['label'] ) < 2 || self::text_length( $region['label'] ) > 180 || 'product_navigation_not_legal_boundary' !== $region['boundary_kind'] || ! self::is_unique_list( $region['source_refs'] ) || ! $region['source_refs'] || isset( $seen_regions[ $region['region_code'] ] ) ) {
				return self::error( 'tra_vel_local_travel_region_invalid', 'Travel regions require unique product-navigation identities and provenance.' );
			}
			foreach ( $region['source_refs'] as $source_ref ) {
				if ( ! isset( $sources[ $source_ref ] ) ) {
					return self::error( 'tra_vel_local_travel_region_source_missing', 'Travel-region evidence must reference retained provenance.' );
				}
			}
			$seen_regions[ $region['region_code'] ] = true;
		}
		$coordinate_state = Tra_Vel_Local_Tourism_Taxonomy::member( $geography['coordinate_state'], Tra_Vel_Local_Tourism_Taxonomy::COORDINATE_STATES );
		if ( ! $coordinate_state ) {
			return self::error( 'tra_vel_local_coordinate_state_invalid', 'Coordinate truth state is unsupported.' );
		}
		foreach ( $geography['coordinate_source_refs'] as $source_ref ) {
			if ( ! isset( $sources[ $source_ref ] ) ) {
				return self::error( 'tra_vel_local_coordinate_source_missing', 'Coordinate evidence must reference retained provenance.' );
			}
		}
		if ( in_array( $coordinate_state, array( 'verified', 'approximate' ), true ) ) {
			if ( ! self::valid_point( $geography['primary_point'] ) || ! $geography['coordinate_source_refs'] ) {
				return self::error( 'tra_vel_local_coordinate_evidence_required', 'Verified or approximate coordinates require a point and evidence.' );
			}
			if ( 'verified' === $coordinate_state ) {
				$authorities = array( 'signed_partner_response', 'provider_authorized_api', 'official_live_feed', 'official_registry', 'official_information', 'operator_confirmation' );
				if ( 'synthetic_demo' === $data_mode ) {
					$authorities[] = 'synthetic_test';
				}
				$trusted = false;
				foreach ( $geography['coordinate_source_refs'] as $source_ref ) {
					$trusted = $trusted || ( in_array( $sources[ $source_ref ]['authority'], $authorities, true ) && 'current' === $sources[ $source_ref ]['freshness_state'] );
				}
				if ( ! $trusted ) {
					return self::error( 'tra_vel_local_coordinate_authority_invalid', 'An exact marker requires current authoritative coordinate evidence.' );
				}
			}
		} elseif ( null !== $geography['primary_point'] ) {
			return self::error( 'tra_vel_local_coordinate_claim_invalid', 'Unknown or conflicting coordinates cannot expose one point as authoritative.' );
		}
		if ( 'unknown' === $coordinate_state && $geography['coordinate_source_refs'] ) {
			return self::error( 'tra_vel_local_unknown_coordinate_sources_invalid', 'Unknown coordinates must not imply supporting evidence.' );
		}
		if ( 'conflict' === $coordinate_state && count( array_unique( $geography['coordinate_source_refs'] ) ) < 2 ) {
			return self::error( 'tra_vel_local_coordinate_conflict_evidence_required', 'A coordinate conflict requires at least two retained sources.' );
		}
		$seen_entrances = array();
		foreach ( $geography['entrances'] as $entrance ) {
			if ( ! self::has_exact_keys( $entrance, array( 'entrance_ref', 'role', 'point', 'accessibility_state', 'source_refs' ) ) || ! preg_match( '/^entrance_[a-z0-9]{8,64}$/', (string) $entrance['entrance_ref'] ) || isset( $seen_entrances[ $entrance['entrance_ref'] ] ) || ! in_array( $entrance['role'], array( 'primary', 'accessible', 'parking', 'transit', 'service', 'meeting_point' ), true ) || ! in_array( $entrance['accessibility_state'], Tra_Vel_Local_Tourism_Taxonomy::FACT_STATES, true ) || ! self::valid_point( $entrance['point'] ) || ! self::is_unique_list( $entrance['source_refs'] ) || ! $entrance['source_refs'] ) {
				return self::error( 'tra_vel_local_entrance_invalid', 'Every entrance requires a precise point and provenance.' );
			}
			foreach ( $entrance['source_refs'] as $source_ref ) {
				if ( ! isset( $sources[ $source_ref ] ) ) {
					return self::error( 'tra_vel_local_entrance_source_missing', 'Entrance evidence must reference retained provenance.' );
				}
			}
			$seen_entrances[ $entrance['entrance_ref'] ] = true;
		}
		if ( null !== $geography['service_geometry'] ) {
			$geometry = $geography['service_geometry'];
			if ( ! self::has_exact_keys( $geometry, array( 'geometry_type', 'geometry_ref', 'bounds', 'source_refs' ) ) || ! in_array( $geometry['geometry_type'], array( 'polygon', 'route', 'bounds' ), true ) || ! preg_match( '/^geometry_[a-z0-9]{8,64}$/', (string) $geometry['geometry_ref'] ) || ! self::has_exact_keys( $geometry['bounds'], array( 'north', 'east', 'south', 'west' ) ) || ! self::is_unique_list( $geometry['source_refs'] ) || ! $geometry['source_refs'] ) {
				return self::error( 'tra_vel_local_service_geometry_invalid', 'Service geometry requires typed bounds and retained provenance.' );
			}
			$bounds = $geometry['bounds'];
			if ( ! is_numeric( $bounds['north'] ) || ! is_numeric( $bounds['east'] ) || ! is_numeric( $bounds['south'] ) || ! is_numeric( $bounds['west'] ) || $bounds['north'] < $bounds['south'] || $bounds['east'] < $bounds['west'] || $bounds['north'] > 90 || $bounds['south'] < -90 || $bounds['east'] > 180 || $bounds['west'] < -180 ) {
				return self::error( 'tra_vel_local_service_bounds_invalid', 'Local service bounds are invalid.' );
			}
			foreach ( $geometry['source_refs'] as $source_ref ) {
				if ( ! isset( $sources[ $source_ref ] ) ) {
					return self::error( 'tra_vel_local_service_geometry_source_missing', 'Service geometry must reference retained provenance.' );
				}
			}
		}

		return array(
			'coordinate_state' => $coordinate_state,
			'map_visibility'   => 'verified' === $coordinate_state ? 'exact' : ( 'approximate' === $coordinate_state ? 'cluster_only' : 'suppressed' ),
		);
	}

	/**
	 * Require every high-risk fit group and explicit unknown/conflict decisions.
	 *
	 * @return array|WP_Error
	 */
	private static function validate_facts( $facts, $sources ) {
		if ( ! self::has_exact_keys( $facts, Tra_Vel_Local_Tourism_Taxonomy::FACT_GROUPS ) ) {
			return self::error( 'tra_vel_local_fact_groups_incomplete', 'Every local item must state all required fit groups, including unknown.' );
		}
		$states = array();
		foreach ( Tra_Vel_Local_Tourism_Taxonomy::FACT_GROUPS as $group ) {
			$fact = $facts[ $group ];
			if ( ! self::has_exact_keys( $fact, array( 'group_state', 'next_action', 'last_reviewed_at_utc', 'assertions' ) ) || ! in_array( $fact['group_state'], Tra_Vel_Local_Tourism_Taxonomy::FACT_GROUP_STATES, true ) || ( null !== $fact['last_reviewed_at_utc'] && ! self::is_iso_time( $fact['last_reviewed_at_utc'] ) ) || ! self::is_list( $fact['assertions'] ) ) {
				return self::error( 'tra_vel_local_fact_group_invalid', 'A fit group is missing its explicit state, next action, or assertions.' );
			}
			$expected_action = array(
				'evidence_current' => 'none',
				'evidence_partial' => 'verify',
				'unknown'          => 'verify',
				'conflict'         => 'resolve_conflict',
				'not_applicable'   => 'not_relevant',
			);
			if ( $expected_action[ $fact['group_state'] ] !== $fact['next_action'] ) {
				return self::error( 'tra_vel_local_fact_action_invalid', 'Fact next action must match its truth state.' );
			}
			$has_conflict    = false;
			$has_unknown     = false;
			$seen_codes      = array();
			$assertion_required = array( 'code', 'state', 'value_code', 'authority_ref', 'certificate_ref', 'valid_from', 'valid_to', 'limitations', 'source_refs', 'observed_at_utc', 'expires_at_utc' );
			foreach ( $fact['assertions'] as $assertion ) {
				if ( ! self::has_exact_keys( $assertion, $assertion_required ) || ! Tra_Vel_Local_Tourism_Taxonomy::fact_code( $group, $assertion['code'] ) || isset( $seen_codes[ $assertion['code'] ] ) || ! in_array( $assertion['state'], Tra_Vel_Local_Tourism_Taxonomy::FACT_STATES, true ) || ! self::is_unique_list( $assertion['source_refs'] ) || ! self::valid_nullable_code( $assertion['value_code'], '/^[a-z0-9_-]{1,100}$/' ) || ! self::valid_nullable_code( $assertion['authority_ref'], '/^party_[a-z0-9]{12,64}$/' ) || ! self::valid_nullable_code( $assertion['certificate_ref'], '/^certificate_[a-z0-9]{8,80}$/' ) || ! self::valid_nullable_time( $assertion['valid_from'] ) || ! self::valid_nullable_time( $assertion['valid_to'] ) || ! self::valid_nullable_time( $assertion['observed_at_utc'] ) || ! self::valid_nullable_time( $assertion['expires_at_utc'] ) || ! self::valid_text_list( $assertion['limitations'], 2, 240 ) ) {
					return self::error( 'tra_vel_local_fact_assertion_invalid', 'Fact assertions require unique group-specific codes and explicit states.' );
				}
				if ( null !== $assertion['valid_from'] && null !== $assertion['valid_to'] && strtotime( $assertion['valid_to'] ) < strtotime( $assertion['valid_from'] ) ) {
					return self::error( 'tra_vel_local_fact_validity_invalid', 'A fact validity window cannot end before it begins.' );
				}
				if ( null !== $assertion['observed_at_utc'] && null !== $assertion['expires_at_utc'] && strtotime( $assertion['expires_at_utc'] ) < strtotime( $assertion['observed_at_utc'] ) ) {
					return self::error( 'tra_vel_local_fact_freshness_window_invalid', 'A fact expiry cannot precede its observation.' );
				}
				$seen_codes[ $assertion['code'] ] = true;
				foreach ( $assertion['source_refs'] as $source_ref ) {
					if ( ! isset( $sources[ $source_ref ] ) ) {
						return self::error( 'tra_vel_local_fact_source_missing', 'Fact evidence must reference retained provenance.' );
					}
				}
				if ( in_array( $assertion['state'], array( 'verified_true', 'verified_false' ), true ) && ( ! $assertion['source_refs'] || ! self::is_iso_time( $assertion['observed_at_utc'] ) ) ) {
					return self::error( 'tra_vel_local_verified_fact_evidence_required', 'A verified fact requires evidence and an observation time.' );
				}
				if ( 'unknown' === $assertion['state'] && ( $assertion['source_refs'] || null !== $assertion['observed_at_utc'] ) ) {
					return self::error( 'tra_vel_local_unknown_fact_claim_invalid', 'An unknown fact cannot carry evidence as if verified.' );
				}
				if ( 'conflict' === $assertion['state'] && count( array_unique( $assertion['source_refs'] ) ) < 2 ) {
					return self::error( 'tra_vel_local_fact_conflict_evidence_required', 'A conflicting fact requires at least two retained sources.' );
				}
				$has_conflict = $has_conflict || 'conflict' === $assertion['state'];
				$has_unknown  = $has_unknown || 'unknown' === $assertion['state'];
			}
			if ( 'evidence_current' === $fact['group_state'] && ( ! $fact['assertions'] || $has_unknown || $has_conflict ) ) {
				return self::error( 'tra_vel_local_fact_group_unsupported', 'Current evidence state requires assertions without unknowns or conflicts.' );
			}
			if ( 'evidence_partial' === $fact['group_state'] && ( ! $fact['assertions'] || ! $has_unknown || $has_conflict ) ) {
				return self::error( 'tra_vel_local_fact_group_partial_invalid', 'Partial evidence requires at least one explicit unknown and no conflict.' );
			}
			if ( 'unknown' === $fact['group_state'] && $fact['assertions'] ) {
				return self::error( 'tra_vel_local_fact_group_unknown_invalid', 'A wholly unknown group must not imply observed dimensions.' );
			}
			if ( 'conflict' === $fact['group_state'] && ! $has_conflict ) {
				return self::error( 'tra_vel_local_fact_group_conflict_invalid', 'A conflicting group must retain a conflicting assertion.' );
			}
			$states[ $group ] = $fact['group_state'];
		}
		return $states;
	}

	/**
	 * Validate opening-state freshness independently from inventory availability.
	 *
	 * @return array|WP_Error
	 */
	private static function evaluate_operations( $operations, $sources, $now_utc ) {
		if ( ! self::has_exact_keys( $operations, array( 'operating_state', 'operating_source_refs', 'opening_hours' ) ) || ! in_array( $operations['operating_state'], Tra_Vel_Local_Tourism_Taxonomy::OPERATING_STATES, true ) || ! self::is_unique_list( $operations['operating_source_refs'] ) || ! is_array( $operations['opening_hours'] ) ) {
			return self::error( 'tra_vel_local_operations_invalid', 'Operational and opening-hours truth states are required.' );
		}
		foreach ( $operations['operating_source_refs'] as $source_ref ) {
			if ( ! isset( $sources[ $source_ref ] ) ) {
				return self::error( 'tra_vel_local_operating_source_missing', 'Operating state must reference retained provenance.' );
			}
		}
		if ( in_array( $operations['operating_state'], array( 'open_verified', 'closed_verified' ), true ) && ! $operations['operating_source_refs'] ) {
			return self::error( 'tra_vel_local_operating_evidence_required', 'Verified operating state requires retained provenance.' );
		}
		if ( 'unknown' === $operations['operating_state'] && $operations['operating_source_refs'] ) {
			return self::error( 'tra_vel_local_unknown_operating_claim_invalid', 'Unknown operating state cannot imply supporting evidence.' );
		}
		if ( 'conflict' === $operations['operating_state'] && count( array_unique( $operations['operating_source_refs'] ) ) < 2 ) {
			return self::error( 'tra_vel_local_operating_conflict_evidence_required', 'Conflicting operating state requires at least two retained sources.' );
		}
		$hours = $operations['opening_hours'];
		if ( ! self::has_exact_keys( $hours, array( 'state', 'timezone', 'periods', 'source_refs', 'observed_at_utc', 'expires_at_utc' ) ) || ! in_array( $hours['state'], Tra_Vel_Local_Tourism_Taxonomy::HOURS_STATES, true ) || 'Asia/Jerusalem' !== $hours['timezone'] || ! self::is_list( $hours['periods'] ) || ! self::is_unique_list( $hours['source_refs'] ) || ! self::valid_nullable_time( $hours['observed_at_utc'] ) || ! self::valid_nullable_time( $hours['expires_at_utc'] ) ) {
			return self::error( 'tra_vel_local_hours_invalid', 'Opening hours require an explicit state and Israel timezone.' );
		}
		foreach ( $hours['periods'] as $period ) {
			if ( ! self::has_exact_keys( $period, array( 'day', 'opens_local', 'closes_local', 'valid_from', 'valid_to' ) ) || ! in_array( $period['day'], array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'holiday' ), true ) || ! preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', (string) $period['opens_local'] ) || ! preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', (string) $period['closes_local'] ) || ! self::valid_nullable_date( $period['valid_from'] ) || ! self::valid_nullable_date( $period['valid_to'] ) ) {
				return self::error( 'tra_vel_local_hours_period_invalid', 'Opening periods require a canonical day, local times, and optional date window.' );
			}
			if ( null !== $period['valid_from'] && null !== $period['valid_to'] && $period['valid_to'] < $period['valid_from'] ) {
				return self::error( 'tra_vel_local_hours_period_window_invalid', 'An opening period cannot end before it begins.' );
			}
		}
		foreach ( $hours['source_refs'] as $source_ref ) {
			if ( ! isset( $sources[ $source_ref ] ) ) {
				return self::error( 'tra_vel_local_hours_source_missing', 'Opening hours must reference retained provenance.' );
			}
		}
		if ( 'current_verified' === $hours['state'] && ( ! $hours['source_refs'] || ! self::is_iso_time( $hours['observed_at_utc'] ) || ! self::is_iso_time( $hours['expires_at_utc'] ) || strtotime( $hours['expires_at_utc'] ) <= strtotime( $now_utc ) ) ) {
			return self::error( 'tra_vel_local_hours_freshness_invalid', 'Current opening hours require unexpired evidence.' );
		}
		if ( 'unknown' === $hours['state'] && ( $hours['periods'] || $hours['source_refs'] ) ) {
			return self::error( 'tra_vel_local_unknown_hours_claim_invalid', 'Unknown hours cannot carry periods as if verified.' );
		}
		if ( 'conflict' === $hours['state'] && count( array_unique( $hours['source_refs'] ) ) < 2 ) {
			return self::error( 'tra_vel_local_hours_conflict_evidence_required', 'Conflicting hours require at least two retained sources.' );
		}
		if ( null !== $hours['observed_at_utc'] && null !== $hours['expires_at_utc'] && strtotime( $hours['expires_at_utc'] ) < strtotime( $hours['observed_at_utc'] ) ) {
			return self::error( 'tra_vel_local_hours_window_invalid', 'Opening-hours expiry cannot precede observation.' );
		}
		$operating_action = 'revalidate';
		if ( 'open_verified' === $operations['operating_state'] ) {
			$operating_action = 'open';
		} elseif ( 'closed_verified' === $operations['operating_state'] ) {
			$operating_action = 'closed';
		}
		return array(
			'hours_action'     => in_array( $hours['state'], array( 'current_verified', 'not_applicable' ), true ) ? 'current' : 'revalidate',
			'operating_action' => $operating_action,
		);
	}

	/**
	 * Enforce that numeric payable values exist only on a current live quote.
	 *
	 * @return array|WP_Error
	 */
	private static function evaluate_commercial( $commercial, $sources, $data_mode, $now_utc ) {
		if ( ! self::has_exact_keys( $commercial, array( 'availability', 'pricing' ) ) || ! is_array( $commercial['availability'] ) || ! is_array( $commercial['pricing'] ) ) {
			return self::error( 'tra_vel_local_commercial_invalid', 'Availability and pricing truth states are required.' );
		}
		$availability = $commercial['availability'];
		$pricing      = $commercial['pricing'];
		$availability_fields = array( 'state', 'inventory_scope', 'source_ref', 'observed_at_utc', 'expires_at_utc', 'quantity_remaining', 'bookable' );
		if ( ! self::has_exact_keys( $availability, $availability_fields ) || ! in_array( $availability['state'], Tra_Vel_Local_Tourism_Taxonomy::AVAILABILITY_STATES, true ) || ! in_array( $availability['inventory_scope'], array( 'property', 'unit', 'session', 'service' ), true ) || ! self::valid_nullable_code( $availability['source_ref'], '/^src_[a-z0-9]{12,64}$/' ) || ! self::valid_nullable_time( $availability['observed_at_utc'] ) || ! self::valid_nullable_time( $availability['expires_at_utc'] ) || ( null !== $availability['quantity_remaining'] && ( ! is_int( $availability['quantity_remaining'] ) || $availability['quantity_remaining'] < 0 ) ) || ! is_bool( $availability['bookable'] ) ) {
			return self::error( 'tra_vel_local_availability_invalid', 'Availability must use the canonical truth contract.' );
		}
		if ( in_array( $availability['state'], array( 'unknown', 'checking' ), true ) && null !== $availability['quantity_remaining'] ) {
			return self::error( 'tra_vel_local_unverified_quantity_exposed', 'Unknown or checking inventory cannot expose a numeric remaining quantity.' );
		}
		$availability_current = false;
		if ( in_array( $availability['state'], array( 'available_verified', 'limited_verified', 'unavailable_verified', 'stale' ), true ) ) {
			if ( ! is_string( $availability['source_ref'] ) || ! isset( $sources[ $availability['source_ref'] ] ) || ! self::is_iso_time( $availability['observed_at_utc'] ) ) {
				return self::error( 'tra_vel_local_availability_evidence_required', 'A supplier-derived availability state requires retained evidence.' );
			}
			$availability_current = self::commercial_source_current( $sources[ $availability['source_ref'] ], $availability['expires_at_utc'], $now_utc );
		} elseif ( true === $availability['bookable'] ) {
			return self::error( 'tra_vel_local_unverified_availability_bookable', 'Unknown or checking availability can never be bookable.' );
		}
		if ( 'unavailable_verified' === $availability['state'] && true === $availability['bookable'] ) {
			return self::error( 'tra_vel_local_unavailable_bookable', 'Verified unavailable inventory can never be bookable.' );
		}
		if ( in_array( $availability['state'], array( 'available_verified', 'limited_verified' ), true ) && ( 'live_provider_response' !== $data_mode || ! $availability_current ) ) {
			return self::error( 'tra_vel_local_availability_not_live', 'Verified available inventory requires a current authorized provider response.' );
		}
		if ( true === $availability['bookable'] && ( ! in_array( $availability['state'], array( 'available_verified', 'limited_verified' ), true ) || 'live_provider_response' !== $data_mode || ! $availability_current ) ) {
			return self::error( 'tra_vel_local_availability_not_live', 'Bookable inventory requires a current authorized provider response.' );
		}

		$pricing_fields = array( 'state', 'currency', 'payable_minor', 'pay_later_minor', 'quote_ref', 'source_ref', 'quoted_at_utc', 'expires_at_utc', 'bookable' );
		if ( ! self::has_exact_keys( $pricing, $pricing_fields ) || ! in_array( $pricing['state'], Tra_Vel_Local_Tourism_Taxonomy::PRICE_STATES, true ) || ! self::valid_nullable_code( $pricing['currency'], '/^[A-Z]{3}$/' ) || ! self::valid_nullable_code( $pricing['quote_ref'], '/^quote_[a-z0-9]{12,64}$/' ) || ! self::valid_nullable_code( $pricing['source_ref'], '/^src_[a-z0-9]{12,64}$/' ) || ! self::valid_nullable_time( $pricing['quoted_at_utc'] ) || ! self::valid_nullable_time( $pricing['expires_at_utc'] ) || ! is_bool( $pricing['bookable'] ) ) {
			return self::error( 'tra_vel_local_pricing_invalid', 'Pricing must use the closed verified-quote contract.' );
		}
		if ( 'verified_quote' === $pricing['state'] ) {
			if ( ! is_string( $pricing['currency'] ) || ! preg_match( '/^[A-Z]{3}$/', $pricing['currency'] ) || ! is_int( $pricing['payable_minor'] ) || $pricing['payable_minor'] < 0 || ! is_int( $pricing['pay_later_minor'] ) || $pricing['pay_later_minor'] < 0 || ! preg_match( '/^quote_[a-z0-9]{12,64}$/', (string) $pricing['quote_ref'] ) || ! is_string( $pricing['source_ref'] ) || ! isset( $sources[ $pricing['source_ref'] ] ) || ! self::is_iso_time( $pricing['quoted_at_utc'] ) || ! self::is_iso_time( $pricing['expires_at_utc'] ) ) {
				return self::error( 'tra_vel_local_verified_quote_incomplete', 'A verified price requires a complete, integer-minor-unit quote.' );
			}
			$price_current = self::commercial_source_current( $sources[ $pricing['source_ref'] ], $pricing['expires_at_utc'], $now_utc );
			if ( 'live_provider_response' !== $data_mode || ! $price_current || ! in_array( $availability['state'], array( 'available_verified', 'limited_verified' ), true ) || ! $availability_current ) {
				return self::error( 'tra_vel_local_verified_quote_not_live', 'A numeric total requires matching current authorized availability and quote responses.' );
			}
			if ( true === $pricing['bookable'] && true !== $availability['bookable'] ) {
				return self::error( 'tra_vel_local_price_not_live', 'A bookable price requires matching live availability and a current authorized quote.' );
			}
		} else {
			if ( null !== $pricing['payable_minor'] || null !== $pricing['pay_later_minor'] || true === $pricing['bookable'] ) {
				return self::error( 'tra_vel_local_non_live_price_exposed', 'Non-live pricing states cannot expose numeric payable amounts or checkout.' );
			}
		}

		$booking_allowed = true === $availability['bookable'] && 'verified_quote' === $pricing['state'] && true === $pricing['bookable'];
		$display_state   = 'check_live_availability';
		if ( 'verified_quote' === $pricing['state'] ) {
			$display_state = 'verified_total';
		} elseif ( 'expired_quote' === $pricing['state'] || 'stale' === $availability['state'] ) {
			$display_state = 'revalidate';
		} elseif ( 'unavailable_verified' === $availability['state'] || 'unavailable' === $pricing['state'] ) {
			$display_state = 'unavailable';
		}

		return array( 'availability_state' => $availability['state'], 'display_state' => $display_state, 'booking_allowed' => $booking_allowed );
	}

	private static function commercial_source_current( $source, $expires_at_utc, $now_utc ) {
		return in_array( $source['authority'], array( 'signed_partner_response', 'provider_authorized_api' ), true ) && true === $source['is_current_now'] && self::is_iso_time( $expires_at_utc ) && strtotime( $expires_at_utc ) > strtotime( $now_utc );
	}

	private static function valid_point( $point ) {
		return self::has_exact_keys( $point, array( 'latitude', 'longitude' ) ) && is_numeric( $point['latitude'] ) && is_numeric( $point['longitude'] ) && $point['latitude'] >= -90 && $point['latitude'] <= 90 && $point['longitude'] >= -180 && $point['longitude'] <= 180;
	}

	private static function has_exact_keys( $value, $required ) {
		return is_array( $value ) && count( $value ) === count( $required ) && ! array_diff( $required, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $required );
	}

	private static function is_list( $value ) {
		return is_array( $value ) && array_values( $value ) === $value;
	}

	private static function is_unique_list( $value ) {
		return self::is_list( $value ) && count( $value ) === count( array_unique( $value, SORT_REGULAR ) );
	}

	private static function valid_nullable_code( $value, $pattern ) {
		return null === $value || ( is_string( $value ) && (bool) preg_match( $pattern, $value ) );
	}

	private static function valid_nullable_time( $value ) {
		return null === $value || self::is_iso_time( $value );
	}

	private static function valid_nullable_date( $value ) {
		if ( null === $value ) {
			return true;
		}
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}
		$parts = array_map( 'intval', explode( '-', $value ) );
		return 3 === count( $parts ) && checkdate( $parts[1], $parts[2], $parts[0] );
	}

	private static function valid_text_list( $value, $minimum, $maximum ) {
		if ( ! self::is_unique_list( $value ) ) {
			return false;
		}
		foreach ( $value as $text ) {
			if ( ! is_string( $text ) || self::text_length( $text ) < $minimum || self::text_length( $text ) > $maximum ) {
				return false;
			}
		}
		return true;
	}

	private static function text_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	private static function is_iso_time( $value ) {
		return is_string( $value ) && (bool) preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value ) && false !== strtotime( $value );
	}

	private static function error( $code, $message ) {
		return new WP_Error( $code, $message, array( 'status' => 400 ) );
	}
}
