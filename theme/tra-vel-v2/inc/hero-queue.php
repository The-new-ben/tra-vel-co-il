<?php
/**
 * Homepage campaign queue.
 *
 * Campaigns remain useful without supplier inventory. Price-led campaigns are
 * rejected until a live offer source is connected and verified.
 *
 * @package TraVelV2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Select an active seasonal campaign or the evergreen fallback.
 *
 * @param array<int, mixed> $campaigns Campaign candidates.
 * @param string            $today     Current date in Y-m-d form.
 * @return array<string, mixed>
 */
function tra_vel_v2_select_home_hero_campaign( $campaigns, $today ) {
	$seasonal = array();
	$evergreen = array();
	$required_strings = array( 'id', 'eyebrow', 'title', 'copy', 'primary_label', 'secondary_label', 'primary_url', 'secondary_url' );
	$valid_today = 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $today );
	foreach ( (array) $campaigns as $campaign ) {
		if ( ! is_array( $campaign ) || false !== ( $campaign['price_claim'] ?? null ) || ! isset( $campaign['priority'] ) || ! is_int( $campaign['priority'] ) ) {
			continue;
		}
		foreach ( $required_strings as $required_string ) {
			if ( ! isset( $campaign[ $required_string ] ) || ! is_string( $campaign[ $required_string ] ) || '' === trim( $campaign[ $required_string ] ) ) {
				continue 2;
			}
		}
		if ( 1 !== preg_match( '/^[a-z0-9-]+$/', $campaign['id'] ) || 1 !== preg_match( '#^/[a-z0-9/?=&._-]*$#i', $campaign['primary_url'] ) || 1 !== preg_match( '#^/[a-z0-9/?=&._-]*$#i', $campaign['secondary_url'] ) ) {
			continue;
		}
		$kind = (string) ( $campaign['kind'] ?? '' );
		if ( 'evergreen' === $kind ) {
			if ( array_key_exists( 'active_from', $campaign ) || array_key_exists( 'active_until', $campaign ) || array_key_exists( 'map_state', $campaign ) ) {
				continue;
			}
			$evergreen[] = $campaign;
			continue;
		}
		if ( 'seasonal' !== $kind || ! $valid_today || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $campaign['active_from'] ?? '' ) ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $campaign['active_until'] ?? '' ) ) || empty( $campaign['map_state'] ) || ! is_string( $campaign['map_state'] ) ) {
			continue;
		}
		if ( $campaign['active_from'] <= $today && $campaign['active_until'] >= $today && $campaign['active_from'] <= $campaign['active_until'] ) {
			$seasonal[] = $campaign;
		}
	}

	$order = static function ( $left, $right ) {
		$priority = (int) ( $left['priority'] ?? 100 ) <=> (int) ( $right['priority'] ?? 100 );
		return 0 !== $priority ? $priority : strcmp( (string) ( $left['id'] ?? '' ), (string) ( $right['id'] ?? '' ) );
	};
	usort( $seasonal, $order );
	usort( $evergreen, $order );

	return $seasonal[0] ?? $evergreen[0] ?? array();
}

/**
 * Pick a stable daily discovery seed without permanently favoring one destination.
 *
 * This is a neutral starting direction, not a personalized recommendation. Seasonal
 * campaigns may still provide their reviewed map state through the campaign contract.
 *
 * @param array<int, mixed> $destination_ids Supported destination identifiers.
 * @param string            $today           Current date in Y-m-d form.
 * @return string
 */
function tra_vel_v2_select_home_discovery_destination( $destination_ids, $today ) {
	if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $today ) ) {
		return '';
	}

	$valid_ids = array();
	foreach ( (array) $destination_ids as $destination_id ) {
		$destination_id = is_string( $destination_id ) ? $destination_id : '';
		if ( '' !== $destination_id && 1 === preg_match( '/^[a-z0-9-]+$/', $destination_id ) && ! in_array( $destination_id, $valid_ids, true ) ) {
			$valid_ids[] = $destination_id;
		}
	}

	if ( array() === $valid_ids ) {
		return '';
	}

	$unsigned_hash = (int) sprintf( '%u', crc32( $today . '|' . implode( '|', $valid_ids ) ) );
	return $valid_ids[ $unsigned_hash % count( $valid_ids ) ];
}

/**
 * Return the first active, safe homepage campaign.
 *
 * @return array<string, mixed>
 */
function tra_vel_v2_get_home_hero_campaign() {
	$path = TRA_VEL_V2_PATH . '/assets/data/home-hero-queue.json';
	if ( ! file_exists( $path ) ) {
		return array();
	}

	$payload = json_decode( file_get_contents( $path ), true );
	if ( ! is_array( $payload ) || empty( $payload['campaigns'] ) || ! is_array( $payload['campaigns'] ) ) {
		return array();
	}

	return tra_vel_v2_select_home_hero_campaign( $payload['campaigns'], current_time( 'Y-m-d' ) );
}
