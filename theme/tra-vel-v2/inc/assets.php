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

	if ( is_front_page() || is_page_template( 'page-map.php' ) || is_page_template( 'page-destination.php' ) || is_page_template( 'page-seo-opportunity.php' ) || is_singular( 'destination' ) ) {
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
			'agentRestUrl' => esc_url_raw( rest_url( 'tra-vel-agent/v1' ) ),
			'discoveryUrl' => esc_url_raw( rest_url( 'tra-vel/v2/discovery' ) ),
			'flightSearchUrl' => esc_url_raw( rest_url( 'tra-vel/v2/flights/search' ) ),
			'hotelSearchUrl'  => esc_url_raw( rest_url( 'tra-vel/v2/hotels/search' ) ),
			'insuranceQuoteUrl' => esc_url_raw( rest_url( 'tra-vel/v2/insurance/quote' ) ),
			'packageSearchUrl' => esc_url_raw( rest_url( 'tra-vel/v2/packages/search' ) ),
			'workspaceUrl' => esc_url_raw( rest_url( 'tra-vel/v2/workspace' ) ),
			'customerTripCockpitUrl' => esc_url_raw( rest_url( 'tra-vel-agent/v1/customer-trip-cockpit/current' ) ),
			'capabilitySessionLogoutUrl' => esc_url_raw( rest_url( 'tra-vel-agent/v1/vip/capability-session/logout' ) ),
			'tripCareUrl' => esc_url_raw( home_url( '/ai-planner/' ) ),
			'commercialIntentUrl' => esc_url_raw( rest_url( 'tra-vel-agent/v1/commercial-intents' ) ),
			'handoffUrl'   => esc_url_raw( rest_url( 'tra-vel/v2/handoffs/prepare' ) ),
			'isLoggedIn'  => is_user_logged_in(),
			'loginUrl'     => esc_url_raw( wp_login_url( home_url( '/saved/' ) ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'demoMode'     => (bool) apply_filters( 'tra_vel_v2_demo_mode', true ),
			'assetUrl'     => TRA_VEL_V2_URI . '/assets/images/',
		)
	);

	if ( is_page_template( 'page-saved.php' ) ) {
		wp_enqueue_script(
			'tra-vel-v2-customer-trip-cockpit',
			TRA_VEL_V2_URI . '/assets/js/customer-trip-cockpit.js',
			array( 'tra-vel-v2-app' ),
			tra_vel_v2_asset_version( '/assets/js/customer-trip-cockpit.js' ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'tra_vel_v2_enqueue_assets' );

function tra_vel_v2_script_attributes( $tag, $handle ) {
	if ( ! in_array( $handle, array( 'tra-vel-v2-app', 'tra-vel-v2-globe-3d', 'tra-vel-v2-customer-trip-cockpit' ), true ) ) {
		return $tag;
	}
	return str_replace( ' src=', ' defer src=', $tag );
}
add_filter( 'script_loader_tag', 'tra_vel_v2_script_attributes', 10, 2 );
