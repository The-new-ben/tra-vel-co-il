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

	$today = current_time( 'Y-m-d' );
	$campaigns = array_filter(
		$payload['campaigns'],
		static function ( $campaign ) use ( $today ) {
			if ( ! is_array( $campaign ) || empty( $campaign['active_from'] ) || empty( $campaign['active_until'] ) ) {
				return false;
			}

			if ( ! empty( $campaign['price_claim'] ) ) {
				return false;
			}

			return $campaign['active_from'] <= $today && $campaign['active_until'] >= $today;
		}
	);

	usort(
		$campaigns,
		static function ( $left, $right ) {
			return (int) ( $left['priority'] ?? 100 ) <=> (int) ( $right['priority'] ?? 100 );
		}
	);

	return $campaigns[0] ?? array();
}

