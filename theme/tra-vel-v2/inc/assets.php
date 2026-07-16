<?php
/**
 * Front-end assets.
 *
 * @package TraVelV2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tra_vel_v2_asset_version( $relative_path ) {
	$file = TRA_VEL_V2_PATH . $relative_path;
	return file_exists( $file ) ? (string) filemtime( $file ) : TRA_VEL_V2_VERSION;
}

function tra_vel_v2_enqueue_assets() {
	$app_dependencies = array( 'tra-vel-v2-lucide' );

	wp_enqueue_style(
		'tra-vel-v2-app',
		TRA_VEL_V2_URI . '/assets/css/app.css',
		array(),
		tra_vel_v2_asset_version( '/assets/css/app.css' )
	);

	wp_enqueue_script(
		'tra-vel-v2-lucide',
		TRA_VEL_V2_URI . '/assets/vendor/lucide.min.js',
		array(),
		'0.468.0',
		true
	);

	if ( is_page_template( 'page-map.php' ) ) {
		wp_enqueue_script(
			'tra-vel-v2-globe-3d',
			TRA_VEL_V2_URI . '/assets/js/globe-3d.js',
			array(),
			tra_vel_v2_asset_version( '/assets/js/globe-3d.js' ),
			true
		);
		$app_dependencies[] = 'tra-vel-v2-globe-3d';
	}

	wp_enqueue_script(
		'tra-vel-v2-app',
		TRA_VEL_V2_URI . '/assets/js/app.js',
		$app_dependencies,
		tra_vel_v2_asset_version( '/assets/js/app.js' ),
		true
	);

	wp_localize_script(
		'tra-vel-v2-app',
		'traVelV2',
		array(
			'homeUrl'      => home_url( '/' ),
			'restUrl'      => esc_url_raw( rest_url( 'tra-vel/v2' ) ),
			'discoveryUrl' => esc_url_raw( rest_url( 'tra-vel/v2/discovery' ) ),
			'flightSearchUrl' => esc_url_raw( rest_url( 'tra-vel/v2/flights/search' ) ),
			'hotelSearchUrl'  => esc_url_raw( rest_url( 'tra-vel/v2/hotels/search' ) ),
			'insuranceQuoteUrl' => esc_url_raw( rest_url( 'tra-vel/v2/insurance/quote' ) ),
			'packageSearchUrl' => esc_url_raw( rest_url( 'tra-vel/v2/packages/search' ) ),
			'workspaceUrl' => esc_url_raw( rest_url( 'tra-vel/v2/workspace' ) ),
			'isLoggedIn'  => is_user_logged_in(),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'demoMode'     => (bool) apply_filters( 'tra_vel_v2_demo_mode', true ),
			'assetUrl'     => TRA_VEL_V2_URI . '/assets/images/',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'tra_vel_v2_enqueue_assets' );

function tra_vel_v2_script_attributes( $tag, $handle ) {
	if ( ! in_array( $handle, array( 'tra-vel-v2-app', 'tra-vel-v2-globe-3d' ), true ) ) {
		return $tag;
	}
	return str_replace( ' src=', ' defer src=', $tag );
}
add_filter( 'script_loader_tag', 'tra_vel_v2_script_attributes', 10, 2 );
