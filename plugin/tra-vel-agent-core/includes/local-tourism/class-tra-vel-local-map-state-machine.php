<?php
/**
 * Earth-to-Israel navigation and resilient map-rendering state machines.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Local_Map_State_Machine {
	const TRANSITIONS = array(
		'navigation' => array(
			'world_globe'           => array( 'select_israel' => 'country_focus' ),
			'country_focus'         => array( 'camera_descent_complete' => 'israel_region_overview', 'back_to_world' => 'world_globe' ),
			'israel_region_overview'=> array( 'local_tiles_ready' => 'local_high_res_map', 'back_to_country' => 'country_focus' ),
			'local_high_res_map'     => array( 'select_place' => 'place_or_route_detail', 'select_route' => 'place_or_route_detail', 'back_to_region' => 'israel_region_overview' ),
			'place_or_route_detail'  => array( 'add_to_itinerary' => 'itinerary_assembly', 'back_to_map' => 'local_high_res_map' ),
			'itinerary_assembly'     => array( 'revalidation_succeeded' => 'revalidated_proposal', 'back_to_detail' => 'place_or_route_detail' ),
			'revalidated_proposal'   => array( 'edit_itinerary' => 'itinerary_assembly', 'quote_expired' => 'itinerary_assembly' ),
		),
		'render' => array(
			'globe_ready'       => array( 'start_descent' => 'descending', 'go_offline' => 'offline_cached' ),
			'descending'        => array( 'begin_local_tiles' => 'local_tiles_loading', 'go_offline' => 'offline_cached' ),
			'local_tiles_loading'=> array( 'tiles_ready' => 'local_ready', 'tile_failure' => 'degraded_tiles', 'go_offline' => 'offline_cached' ),
			'local_ready'       => array( 'refresh_tiles' => 'local_tiles_loading', 'tile_failure' => 'degraded_tiles', 'go_offline' => 'offline_cached' ),
			'degraded_tiles'    => array( 'retry_tiles' => 'local_tiles_loading', 'use_cached_tiles' => 'offline_cached' ),
			'offline_cached'    => array( 'reconnect' => 'local_tiles_loading' ),
		),
	);

	/**
	 * Return the next navigation/render state or fail closed.
	 *
	 * @return string|WP_Error
	 */
	public static function transition( $axis, $from, $command ) {
		$axis    = sanitize_key( (string) $axis );
		$from    = sanitize_key( (string) $from );
		$command = sanitize_key( (string) $command );
		if ( ! isset( self::TRANSITIONS[ $axis ][ $from ][ $command ] ) ) {
			return new WP_Error(
				'tra_vel_local_map_transition_invalid',
				'This local-map transition is not valid from the current state.',
				array( 'status' => 409, 'axis' => $axis, 'from' => $from, 'command' => $command )
			);
		}
		return self::TRANSITIONS[ $axis ][ $from ][ $command ];
	}

	/**
	 * Describe transition motion without ever requiring a poster or second click.
	 *
	 * @return array|WP_Error
	 */
	public static function transition_plan( $from, $command, $motion_preference, $connectivity ) {
		$to = self::transition( 'navigation', $from, $command );
		if ( is_wp_error( $to ) ) {
			return $to;
		}
		$motion_preference = Tra_Vel_Local_Tourism_Taxonomy::member( $motion_preference, Tra_Vel_Local_Tourism_Taxonomy::MOTION_PREFERENCES );
		$connectivity      = Tra_Vel_Local_Tourism_Taxonomy::member( $connectivity, Tra_Vel_Local_Tourism_Taxonomy::CONNECTIVITY_STATES );
		if ( ! $motion_preference || ! $connectivity ) {
			return new WP_Error( 'tra_vel_local_map_transition_context_invalid', 'Motion and connectivity states must use the local-map contract.', array( 'status' => 400 ) );
		}

		return array(
			'from'                        => sanitize_key( (string) $from ),
			'to'                          => $to,
			'animation_mode'              => 'reduced' === $motion_preference ? 'instant_with_progress' : 'camera_descent',
			'progress_visible'            => true,
			'map_controls_available'      => true,
			'map_interaction_locked'      => false,
			'requires_second_entry_click' => false,
			'static_poster_required'      => false,
			'data_delivery_mode'          => 'offline' === $connectivity ? 'cached_read_only' : 'progressive_live',
		);
	}

	/**
	 * Navigation may change camera state, never the travel-intent identity.
	 *
	 * @return true|WP_Error
	 */
	public static function assert_context_preserved( $before, $after ) {
		$keys = array( 'context_ref', 'context_digest', 'dates_state', 'party_state', 'budget_state', 'benefits_state', 'accessibility_state', 'intent_state' );
		if ( ! is_array( $before ) || ! is_array( $after ) ) {
			return new WP_Error( 'tra_vel_local_map_context_invalid', 'Map context must be a structured object.', array( 'status' => 400 ) );
		}
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $before ) || ! array_key_exists( $key, $after ) || $before[ $key ] !== $after[ $key ] ) {
				return new WP_Error( 'tra_vel_local_map_context_changed', 'A navigation transition cannot silently change preserved trip intent.', array( 'status' => 409, 'field' => $key ) );
			}
		}
		if ( ! isset( $before['revision'], $after['revision'] ) || ! is_int( $before['revision'] ) || ! is_int( $after['revision'] ) || $after['revision'] < $before['revision'] ) {
			return new WP_Error( 'tra_vel_local_map_context_revision_invalid', 'Preserved context revision cannot move backwards.', array( 'status' => 409 ) );
		}
		return true;
	}
}
