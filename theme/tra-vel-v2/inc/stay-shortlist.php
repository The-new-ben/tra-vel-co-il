<?php
/**
 * User-entered hotel comparison, scoped to the exact Budapest registry owner.
 * No supplier data, remote calls, storage, measurement or price conversion.
 *
 * @package TraVelV2
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tra_vel_v2_stay_shortlist_is_hotels_hub() {
	return is_singular( 'page' )
		&& is_page( 'hotels' )
		&& '/hotels/' === wp_parse_url( get_permalink( get_queried_object_id() ), PHP_URL_PATH );
}

function tra_vel_v2_stay_shortlist_owner() {
	if ( ! is_singular( 'page' ) ) {
		return false;
	}
	if ( tra_vel_v2_stay_shortlist_is_hotels_hub() ) {
		return true;
	}
	$post_id = (int) get_queried_object_id();
	$entry = tra_vel_v2_get_current_seo_opportunity( $post_id );
	return is_array( $entry )
		&& 'budapest-hotels' === ( $entry['id'] ?? '' )
		&& '/hotels/budapest/' === ( $entry['canonicalPath'] ?? '' )
		&& tra_vel_v2_is_exposable_seo_opportunity( $entry )
		&& tra_vel_v2_seo_opportunity_identity_matches( $post_id, $entry );
}

function tra_vel_v2_enqueue_stay_shortlist() {
	$post = get_post( get_queried_object_id() );
	if ( ! tra_vel_v2_stay_shortlist_owner() || ( ! tra_vel_v2_stay_shortlist_is_hotels_hub() && ( ! $post || ! has_shortcode( (string) $post->post_content, 'tra_vel_stay_shortlist' ) ) ) ) {
		return;
	}
	wp_enqueue_style( 'tra-vel-v2-stay-shortlist', TRA_VEL_V2_URI . '/assets/css/stay-shortlist.css', array( 'tra-vel-v2-app' ), tra_vel_v2_asset_version( '/assets/css/stay-shortlist.css' ) );
	wp_enqueue_script( 'tra-vel-v2-stay-cost-engine', TRA_VEL_V2_URI . '/assets/js/stay-cost-engine.js', array(), tra_vel_v2_asset_version( '/assets/js/stay-cost-engine.js' ), true );
	wp_enqueue_script( 'tra-vel-v2-stay-shortlist', TRA_VEL_V2_URI . '/assets/js/stay-shortlist.js', array( 'tra-vel-v2-stay-cost-engine' ), tra_vel_v2_asset_version( '/assets/js/stay-shortlist.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'tra_vel_v2_enqueue_stay_shortlist', 20 );

function tra_vel_v2_stay_shortlist_shortcode() {
	static $rendered = false;
	if ( $rendered || ! tra_vel_v2_stay_shortlist_owner() ) {
		return '';
	}
	$file = TRA_VEL_V2_PATH . '/inc/partials/stay-shortlist.html';
	if ( ! is_readable( $file ) ) {
		return '';
	}
	$html = file_get_contents( $file ); // Bundled trusted markup; no external source.
	if ( false === $html ) {
		return '';
	}
	$rendered = true;
	return $html;
}
add_shortcode( 'tra_vel_stay_shortlist', 'tra_vel_v2_stay_shortlist_shortcode' );
