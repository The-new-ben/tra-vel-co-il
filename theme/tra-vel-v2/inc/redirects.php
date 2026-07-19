<?php
/**
 * Intentional permanent redirects that consolidate legacy destination URLs
 * onto their canonical destination hubs.
 *
 * Every entry here is a deliberate editorial decision recorded in
 * docs/briefs/. Automatic guess-redirects stay disabled in inc/seo.php, so
 * this map is the only redirect authority the theme owns.
 *
 * @package TraVelV2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Legacy page slugs whose search intent is now owned by a destination hub.
 *
 * @return array<string, string> Legacy slug => absolute hub path.
 */
function tra_vel_v2_legacy_hub_redirects() {
	return array(
		'budapest-vacation' => '/destinations/budapest/',
		'prague-vacation'   => '/destinations/prague/',
	);
}

/**
 * Legacy post IDs whose guide intent is now owned by a destination hub.
 *
 * @return array<int, string> Post ID => absolute hub path.
 */
function tra_vel_v2_legacy_post_redirects() {
	return array(
		45 => '/destinations/budapest/',
	);
}

/** Issue one permanent redirect when a legacy URL is requested. */
function tra_vel_v2_apply_legacy_redirects() {
	if ( is_admin() || wp_doing_ajax() ) {
		return;
	}

	$target = '';
	foreach ( tra_vel_v2_legacy_hub_redirects() as $legacy_slug => $hub_path ) {
		if ( is_page( $legacy_slug ) ) {
			$target = $hub_path;
			break;
		}
	}
	if ( '' === $target ) {
		foreach ( tra_vel_v2_legacy_post_redirects() as $legacy_post_id => $hub_path ) {
			if ( is_single( $legacy_post_id ) ) {
				$target = $hub_path;
				break;
			}
		}
	}
	if ( '' === $target ) {
		return;
	}

	wp_safe_redirect( home_url( $target ), 301 );
	exit;
}
add_action( 'template_redirect', 'tra_vel_v2_apply_legacy_redirects', 1 );
