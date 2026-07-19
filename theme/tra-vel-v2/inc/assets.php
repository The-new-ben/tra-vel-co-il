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

	if ( is_page_template( 'page-saved.php' ) && apply_filters( 'tra_vel_v2_cockpit_feed_available', false ) ) {
		wp_enqueue_script(
			'tra-vel-v2-customer-trip-cockpit',
			TRA_VEL_V2_URI . '/assets/js/customer-trip-cockpit.js',
			array( 'tra-vel-v2-app' ),
			tra_vel_v2_asset_version( '/assets/js/customer-trip-cockpit.js' ),
			true
		);
	}

	wp_add_inline_script( 'tra-vel-v2-app', 'window.dataLayer = window.dataLayer || [];', 'before' );

	$ga4_id = apply_filters( 'tra_vel_v2_ga4_measurement_id', get_option( 'tra_vel_v2_ga4_id', '' ) );
	$ga4_id = is_string( $ga4_id ) ? trim( $ga4_id ) : '';
	if ( preg_match( '/^G-[A-Z0-9]{4,16}$/', $ga4_id ) ) {
		wp_enqueue_script(
			'tra-vel-v2-ga4',
			'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ga4_id ),
			array(),
			null,
			false
		);
		wp_add_inline_script(
			'tra-vel-v2-ga4',
			"window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'granted'});gtag('js',new Date());gtag('config','" . esc_js( $ga4_id ) . "');"
		);
	}
}
add_action( 'wp_enqueue_scripts', 'tra_vel_v2_enqueue_assets' );

function tra_vel_v2_script_attributes( $tag, $handle ) {
	if ( ! in_array( $handle, array( 'tra-vel-v2-app', 'tra-vel-v2-globe-3d', 'tra-vel-v2-customer-trip-cockpit', 'tra-vel-v2-lucide' ), true ) ) {
		return $tag;
	}
	return str_replace( ' src=', ' defer src=', $tag );
}
add_filter( 'script_loader_tag', 'tra_vel_v2_script_attributes', 10, 2 );
