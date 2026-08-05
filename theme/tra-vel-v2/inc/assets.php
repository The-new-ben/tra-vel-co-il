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
	$has_globe_surface = is_front_page() || is_page_template( 'page-map.php' ) || is_page_template( 'page-destination.php' ) || is_page_template( 'page-seo-opportunity.php' ) || is_page_template( 'page-pillar.php' ) || is_singular( 'destination' );

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

	// The trip proposal panel opens on every surface that renders it: the
	// globe pages carry it inside the arrival card flow, and the comparison
	// pages carry it next to the decision card. Everything the script touches
	// is already server-rendered, so this file adds behaviour, not data.
	$has_proposal_surface = $has_globe_surface || is_page_template( 'page-experience.php' );

	// The next-action guidance library serves the globe selection beacon, the
	// planner chips plus idle completion, and the proposal panel's silent
	// advance cue, so it loads only there.
	if ( $has_proposal_surface || is_page( 'ai-planner' ) ) {
		wp_enqueue_script(
			'tra-vel-v2-next-action',
			TRA_VEL_V2_URI . '/assets/js/next-action.js',
			array(),
			tra_vel_v2_asset_version( '/assets/js/next-action.js' ),
			true
		);
		$app_dependencies[] = 'tra-vel-v2-next-action';
	}

	if ( $has_globe_surface ) {
		wp_enqueue_script(
			'tra-vel-v2-globe-3d',
			TRA_VEL_V2_URI . '/assets/js/globe-3d.js',
			array(),
			tra_vel_v2_asset_version( '/assets/js/globe-3d.js' ),
			true
		);
		$app_dependencies[] = 'tra-vel-v2-globe-3d';

		wp_enqueue_script(
			'tra-vel-v2-voice-dock',
			TRA_VEL_V2_URI . '/assets/js/voice-dock.js',
			array(),
			tra_vel_v2_asset_version( '/assets/js/voice-dock.js' ),
			true
		);
	}

	// The proposal panel is finished before it reaches the browser too. Its
	// script only reveals, multiplies and substitutes what the server already
	// rendered, so it loads exactly where a panel can exist and nowhere else.
	if ( $has_proposal_surface ) {
		wp_enqueue_script(
			'tra-vel-v2-trip-proposal',
			TRA_VEL_V2_URI . '/assets/js/trip-proposal.js',
			array( 'tra-vel-v2-next-action' ),
			tra_vel_v2_asset_version( '/assets/js/trip-proposal.js' ),
			true
		);
	}

	// The decision card is finished before it reaches the browser. This file
	// only adds the opening sequence and the traveler stepper on top of it, so
	// it loads exactly where the card can appear and nowhere else.
	if ( is_page_template( 'page-experience.php' ) ) {
		wp_enqueue_script(
			'tra-vel-v2-decision-card',
			TRA_VEL_V2_URI . '/assets/js/decision-card.js',
			array(),
			tra_vel_v2_asset_version( '/assets/js/decision-card.js' ),
			true
		);
	}

	if ( is_page_template( 'page-pillar.php' ) ) {
		wp_enqueue_script(
			'tra-vel-v2-pillar-earth',
			TRA_VEL_V2_URI . '/assets/js/pillar-earth.js',
			array( 'tra-vel-v2-globe-3d' ),
			tra_vel_v2_asset_version( '/assets/js/pillar-earth.js' ),
			true
		);
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

	$cockpit_cookie_name  = class_exists( 'Tra_Vel_VIP_Capability_Session_Controller' ) ? Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE : '';
	$cockpit_has_audience = is_user_logged_in() || ( '' !== $cockpit_cookie_name && isset( $_COOKIE[ $cockpit_cookie_name ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( is_page_template( 'page-saved.php' ) && $cockpit_has_audience && apply_filters( 'tra_vel_v2_cockpit_feed_available', false ) ) {
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

	$travelpayouts_marker = apply_filters( 'tra_vel_v2_travelpayouts_marker', get_option( 'tra_vel_v2_travelpayouts_marker', '' ) );
	$travelpayouts_marker = is_string( $travelpayouts_marker ) ? trim( $travelpayouts_marker ) : '';
	if ( preg_match( '/^[0-9]{4,10}$/', $travelpayouts_marker ) ) {
		wp_enqueue_script(
			'tra-vel-v2-travelpayouts',
			'https://tpembars.com/' . rawurlencode( base64_encode( $travelpayouts_marker ) ) . '.js?t=' . rawurlencode( $travelpayouts_marker ),
			array(),
			null,
			true
		);
	}

	if ( tra_vel_v2_metasearch_is_enabled() ) {
		wp_enqueue_script(
			'tra-vel-v2-metasearch',
			'https://tpscr.com/wl_web/main.js?wl_id=' . rawurlencode( tra_vel_v2_metasearch_id() ),
			array(),
			null,
			false
		);
	}
}
add_action( 'wp_enqueue_scripts', 'tra_vel_v2_enqueue_assets' );

/**
 * Return the configured Travelpayouts White Label metasearch id.
 *
 * The id is stored as an option so the integration can be rotated or disabled
 * without a release, exactly like the affiliate marker above.
 *
 * @return string Digits only, or an empty string when not configured.
 */
function tra_vel_v2_metasearch_id() {
	$wl_id = apply_filters( 'tra_vel_v2_metasearch_id', get_option( 'tra_vel_v2_travelpayouts_wl_id', '' ) );
	$wl_id = is_string( $wl_id ) || is_int( $wl_id ) ? trim( (string) $wl_id ) : '';
	return preg_match( '/^[0-9]{3,12}$/', $wl_id ) ? $wl_id : '';
}

/**
 * Whether the bookable metasearch belongs on the current request.
 *
 * The widget owns real availability, so it is limited to the commercial
 * experience routes. Every other surface keeps its current behaviour.
 *
 * @return bool
 */
function tra_vel_v2_metasearch_is_enabled() {
	if ( '' === tra_vel_v2_metasearch_id() || ! is_page_template( 'page-experience.php' ) ) {
		return false;
	}
	$slug = get_post_field( 'post_name', get_queried_object_id() );
	return (bool) apply_filters( 'tra_vel_v2_metasearch_enabled', in_array( $slug, array( 'flights', 'hotels', 'packages' ), true ), $slug );
}

function tra_vel_v2_script_attributes( $tag, $handle ) {
	if ( 'tra-vel-v2-metasearch' === $handle ) {
		// The White Label bundle is published as an ES module and refuses to
		// boot when it is loaded as a classic script. It must NOT be async:
		// a module already defers, while async lets it execute before the
		// mount containers exist, and it then silently renders nothing.
		return str_replace( '<script ', '<script type="module" ', str_replace( " type='text/javascript'", '', $tag ) );
	}
	if ( ! in_array( $handle, array( 'tra-vel-v2-app', 'tra-vel-v2-globe-3d', 'tra-vel-v2-voice-dock', 'tra-vel-v2-pillar-earth', 'tra-vel-v2-customer-trip-cockpit', 'tra-vel-v2-next-action', 'tra-vel-v2-decision-card', 'tra-vel-v2-trip-proposal', 'tra-vel-v2-lucide' ), true ) ) {
		return $tag;
	}
	return str_replace( ' src=', ' defer src=', $tag );
}
add_filter( 'script_loader_tag', 'tra_vel_v2_script_attributes', 10, 2 );
