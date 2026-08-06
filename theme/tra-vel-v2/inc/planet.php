<?php
/**
 * Premium planet: the server side of the Cesium photorealistic upgrade.
 *
 * Theme 1.43.0. The legacy WebGL globe stays the instant first paint and the
 * permanent fallback; this module only decides whether the one-tap upgrade to
 * the streamed photorealistic planet may be offered, and hands a granted
 * session its Google Maps Platform key.
 *
 * The key lives in one place: the tra_vel_v2_gmp_key option, set on the live
 * site by an operator and never committed to this public repository. It is
 * never printed into page HTML, never localized into script data, and never
 * logged. The only road to it is a POST to the grant endpoint below, which
 * counts against a daily session cap and fails closed in every other case.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

/**
 * The validated Google Maps Platform key, or an empty string.
 *
 * Only a value matching Google's browser key shape is ever accepted, so a
 * mistyped or placeholder option behaves exactly like no key at all.
 *
 * @return string
 */
function tra_vel_v2_planet_gmp_key() {
	$key = get_option( 'tra_vel_v2_gmp_key', '' );
	$key = is_string( $key ) ? trim( $key ) : '';
	return preg_match( '/^AIza[0-9A-Za-z_-]{35}$/', $key ) ? $key : '';
}

/**
 * Daily photorealistic session budget.
 *
 * Defaults to 25 grants per day, roughly 750 per month, which stays inside
 * the free tile-session tier with margin. Zero or a negative value disables
 * granting entirely while leaving the option in place.
 *
 * @return int
 */
function tra_vel_v2_planet_daily_cap() {
	$cap = get_option( 'tra_vel_v2_gmp_daily_cap', 25 );
	return is_numeric( $cap ) ? max( 0, (int) $cap ) : 25;
}

/**
 * Whether the server can possibly grant a premium planet session.
 *
 * No valid key means no upgrade affordance is rendered at all: the visitor
 * never sees a control the server could not honor.
 *
 * @return bool
 */
function tra_vel_v2_planet_upgrade_available() {
	return '' !== tra_vel_v2_planet_gmp_key();
}

/**
 * Register the grant endpoint.
 */
function tra_vel_v2_planet_register_routes() {
	register_rest_route(
		'tra-vel/v2',
		'/planet/upgrade-grant',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'tra_vel_v2_planet_grant',
			'permission_callback' => 'tra_vel_v2_planet_grant_permitted',
		)
	);
}
add_action( 'rest_api_init', 'tra_vel_v2_planet_register_routes' );

/**
 * The grant requires the same REST nonce the theme already localizes.
 *
 * Logged-out visitors carry a valid wp_rest nonce too, so the planet stays
 * public while casual cross-site POSTs and blind crawlers stay out.
 *
 * @param WP_REST_Request $request Incoming request.
 * @return bool
 */
function tra_vel_v2_planet_grant_permitted( $request ) {
	$nonce = (string) $request->get_header( 'X-WP-Nonce' );
	return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
}

/**
 * Grant one photorealistic session, or refuse with 403.
 *
 * Fail-closed order: no valid key, exhausted or disabled daily budget. Under
 * the cap the counter increments first, then the key is returned once, with
 * caching disabled so no shared layer can replay it.
 *
 * @param WP_REST_Request $request Incoming request.
 * @return WP_REST_Response|WP_Error
 */
function tra_vel_v2_planet_grant( $request ) {
	$key = tra_vel_v2_planet_gmp_key();
	if ( '' === $key ) {
		return new WP_Error( 'tra_vel_planet_unavailable', 'The premium planet is not configured.', array( 'status' => 403 ) );
	}

	$cap     = tra_vel_v2_planet_daily_cap();
	$today   = current_time( 'Y-m-d' );
	$counter = get_option( 'tra_vel_v2_gmp_daily_counter', array() );
	$count   = is_array( $counter ) && isset( $counter['date'], $counter['count'] ) && $today === $counter['date']
		? max( 0, (int) $counter['count'] )
		: 0;

	if ( $cap <= 0 || $count >= $cap ) {
		return new WP_Error( 'tra_vel_planet_cap_reached', 'The daily premium planet budget is spent.', array( 'status' => 403 ) );
	}

	$count++;
	update_option( 'tra_vel_v2_gmp_daily_counter', array( 'date' => $today, 'count' => $count ), false );

	$response = rest_ensure_response(
		array(
			'granted' => true,
			'key'     => $key,
		)
	);
	$response->header( 'Cache-Control', 'no-store' );
	return $response;
}

/**
 * The one-tap upgrade control, rendered only when a grant is possible.
 *
 * The button ships hidden; planet-premium.js reveals it after the client
 * guards pass (WebGL2, no Save-Data, healthy legacy globe). Without the key
 * option this function renders nothing, so the page carries no trace of the
 * premium path at all.
 */
function tra_vel_v2_premium_planet_control() {
	if ( ! tra_vel_v2_planet_upgrade_available() ) {
		return;
	}
	?>
	<button class="globe-premium-upgrade" data-planet-upgrade type="button" hidden aria-pressed="false">
		<i data-lucide="earth" aria-hidden="true"></i>
		<span><?php esc_html_e( 'כדור הארץ האמיתי', 'tra-vel-v2' ); ?></span>
	</button>
	<?php
}
