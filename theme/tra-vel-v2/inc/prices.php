<?php
/**
 * Found flight prices for the Earth (theme 1.33.0).
 *
 * Travelpayouts publishes fares that travelers found in recent searches. They
 * are not our inventory, we never sell them, and they can move at any moment.
 * Every value that leaves this file therefore carries the moment it was
 * retrieved, is labelled as a found price in the templates, and links out to
 * the supplier that actually owns the fare.
 *
 * Nothing here may block or break a page render. The visitor is always served
 * from cache, a twice daily cron job keeps that cache warm, and every failure
 * mode degrades to no price at all instead of to a price we cannot stand
 * behind. We never emit Offer, Product or AggregateOffer schema for these
 * numbers: they are third party observations, not our commercial offer.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

define( 'TRA_VEL_V2_PRICE_ENDPOINT', 'https://api.travelpayouts.com/aviasales/v3/prices_for_dates' );
define( 'TRA_VEL_V2_PRICE_BOOKING_ORIGIN', 'https://www.aviasales.com' );
define( 'TRA_VEL_V2_PRICE_MARKER', '552866.tra-vel' );
define( 'TRA_VEL_V2_PRICE_ORIGIN_AIRPORT', 'TLV' );
define( 'TRA_VEL_V2_PRICE_CURRENCY', 'ILS' );
define( 'TRA_VEL_V2_PRICE_TRANSIENT_PREFIX', 'tra_vel_v2_price_' );
define( 'TRA_VEL_V2_PRICE_STALE_TRANSIENT_PREFIX', 'tra_vel_v2_price_stale_' );
define( 'TRA_VEL_V2_PRICE_UNAVAILABLE', 'unavailable' );
define( 'TRA_VEL_V2_PRICE_CRON_HOOK', 'tra_vel_v2_refresh_prices' );
define( 'TRA_VEL_V2_PRICE_FRESH_TTL', 6 * HOUR_IN_SECONDS );
define( 'TRA_VEL_V2_PRICE_NEGATIVE_TTL', 15 * MINUTE_IN_SECONDS );
define( 'TRA_VEL_V2_PRICE_STALE_TTL', 7 * DAY_IN_SECONDS );
define( 'TRA_VEL_V2_PRICE_STALE_AFTER', DAY_IN_SECONDS );
define( 'TRA_VEL_V2_PRICE_HTTP_TIMEOUT', 6 );
define( 'TRA_VEL_V2_PRICE_MAX_AMOUNT', 100000 );

/**
 * The Travelpayouts API token.
 *
 * The token lives only in the database, never in this repository. An invalid
 * or absent token is not an error state: it simply means the Earth shows no
 * prices, which is the honest thing to show when we cannot verify one.
 *
 * @return string Validated token, or an empty string.
 */
function tra_vel_v2_travelpayouts_api_token() {
	$token = get_option( 'tra_vel_v2_travelpayouts_api_token', '' );

	/**
	 * Filter the Travelpayouts token so contract tests and controlled hosting
	 * layouts can inject one without touching the option store.
	 *
	 * @param string $token Stored token.
	 */
	$token = apply_filters( 'tra_vel_v2_travelpayouts_api_token', is_string( $token ) ? trim( $token ) : '' );
	$token = is_string( $token ) ? trim( $token ) : '';

	return preg_match( '/^[a-f0-9]{16,64}$/', $token ) ? $token : '';
}

/**
 * Resolve one destination IATA code from the single source of truth.
 *
 * inc/seo-opportunities.php owns the destination table. There is no second
 * airport map anywhere in the theme, so a destination without an airport there
 * simply has no price here.
 *
 * @param string $map_state Destination map state.
 * @return string Three letter IATA code, or an empty string.
 */
function tra_vel_v2_price_destination_airport( $map_state ) {
	$map_state = sanitize_key( (string) $map_state );
	if ( '' === $map_state || ! function_exists( 'tra_vel_v2_seo_opportunity_destinations' ) ) {
		return '';
	}

	$destinations = tra_vel_v2_seo_opportunity_destinations();
	$airport      = isset( $destinations[ $map_state ]['airport'] ) ? (string) $destinations[ $map_state ]['airport'] : '';

	return preg_match( '/^[A-Z]{3}$/', $airport ) ? $airport : '';
}

/**
 * Strict calendar date normalization for anything an upstream API sends.
 *
 * @param mixed $value Candidate date or date-time string.
 * @return string YYYY-MM-DD, or an empty string.
 */
function tra_vel_v2_price_sanitize_date( $value ) {
	if ( ! is_string( $value ) || '' === $value ) {
		return '';
	}

	$date = substr( $value, 0, 10 );
	if ( function_exists( 'tra_vel_v2_sanitize_iso_date' ) ) {
		return (string) tra_vel_v2_sanitize_iso_date( $date );
	}
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts ) ) {
		return '';
	}

	return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ? $date : '';
}

/**
 * Build the tracked supplier deep link from a site relative Aviasales link.
 *
 * The API returns a path such as /search/TLV1110WAW15101?t=...  Anything that
 * is not exactly that shape is rejected outright: an absolute URL from an
 * upstream response must never become an outbound link on our pages.
 *
 * @param mixed $link Raw link value from the API.
 * @return string Absolute tracked URL, or an empty string.
 */
function tra_vel_v2_price_deep_link( $link ) {
	if ( ! is_string( $link ) || 0 !== strpos( $link, '/search/' ) || strlen( $link ) > 600 ) {
		return '';
	}
	// No scheme, no protocol relative host, no traversal out of /search/, no
	// fragment that would swallow the marker, and no quote or angle bracket
	// that could escape an attribute.
	if ( preg_match( '~[:#<>"\'\\\\]|//|\.\.~', $link ) || ! preg_match( '~^/search/[\x21-\x7e]+$~', $link ) ) {
		return '';
	}

	$separator = false === strpos( $link, '?' ) ? '?' : '&';
	$url       = TRA_VEL_V2_PRICE_BOOKING_ORIGIN . $link . $separator . 'marker=' . rawurlencode( TRA_VEL_V2_PRICE_MARKER );
	$url       = esc_url_raw( $url );

	return 0 === strpos( (string) $url, TRA_VEL_V2_PRICE_BOOKING_ORIGIN . '/search/' ) ? (string) $url : '';
}

/**
 * Normalize one raw API record into our published price shape.
 *
 * @param mixed  $record    Raw API record.
 * @param string $airport   Expected destination IATA code.
 * @param string $fetched_at RFC3339 retrieval instant.
 * @return array<string,mixed>|null Normalized price, or null when untrustworthy.
 */
function tra_vel_v2_price_normalize_record( $record, $airport, $fetched_at ) {
	if ( ! is_array( $record ) ) {
		return null;
	}

	$amount = isset( $record['price'] ) && is_numeric( $record['price'] ) ? (int) $record['price'] : 0;
	if ( $amount <= 0 || $amount >= TRA_VEL_V2_PRICE_MAX_AMOUNT || (float) $amount !== (float) $record['price'] ) {
		return null;
	}

	$destination = isset( $record['destination'] ) ? (string) $record['destination'] : '';
	if ( $destination && $destination !== $airport ) {
		return null;
	}

	$airline = isset( $record['airline'] ) ? strtoupper( (string) $record['airline'] ) : '';
	if ( ! preg_match( '/^[A-Z0-9]{2}$/', $airline ) ) {
		return null;
	}

	$departure_date = tra_vel_v2_price_sanitize_date( isset( $record['departure_at'] ) ? $record['departure_at'] : '' );
	if ( '' === $departure_date ) {
		return null;
	}

	$deep_link = tra_vel_v2_price_deep_link( isset( $record['link'] ) ? $record['link'] : '' );
	if ( '' === $deep_link ) {
		return null;
	}

	$transfers = isset( $record['transfers'] ) && is_numeric( $record['transfers'] ) ? (int) $record['transfers'] : 0;
	if ( $transfers < 0 || $transfers > 8 ) {
		return null;
	}

	$gate = isset( $record['gate'] ) ? sanitize_text_field( (string) $record['gate'] ) : '';
	$gate = function_exists( 'mb_substr' ) ? mb_substr( $gate, 0, 60 ) : substr( $gate, 0, 60 );

	return array(
		'price'          => $amount,
		'currency'       => TRA_VEL_V2_PRICE_CURRENCY,
		'departure_date' => $departure_date,
		'return_date'    => tra_vel_v2_price_sanitize_date( isset( $record['return_at'] ) ? $record['return_at'] : '' ),
		'airline'        => $airline,
		'transfers'      => $transfers,
		'gate'           => $gate,
		'deep_link'      => $deep_link,
		'fetched_at'     => $fetched_at,
		'is_stale'       => false,
	);
}

/**
 * Ask Travelpayouts for the cheapest round trip we can currently observe.
 *
 * Any failure returns null. The token never reaches a log, an error message or
 * a stored value.
 *
 * @param string $airport Destination IATA code.
 * @return array<string,mixed>|null Normalized price, or null.
 */
function tra_vel_v2_price_fetch( $airport ) {
	$token = tra_vel_v2_travelpayouts_api_token();
	if ( '' === $token || ! preg_match( '/^[A-Z]{3}$/', (string) $airport ) ) {
		return null;
	}

	$url = add_query_arg(
		array(
			'origin'      => TRA_VEL_V2_PRICE_ORIGIN_AIRPORT,
			'destination' => $airport,
			'currency'    => strtolower( TRA_VEL_V2_PRICE_CURRENCY ),
			'sorting'     => 'price',
			'limit'       => 3,
			'one_way'     => 'false',
			'token'       => $token,
		),
		TRA_VEL_V2_PRICE_ENDPOINT
	);

	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => TRA_VEL_V2_PRICE_HTTP_TIMEOUT,
			'redirection' => 1,
			'headers'     => array( 'Accept' => 'application/json' ),
		)
	);
	unset( $url );

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $body ) || true !== ( isset( $body['success'] ) ? $body['success'] : false ) ) {
		return null;
	}
	if ( ! isset( $body['data'] ) || ! is_array( $body['data'] ) || ! $body['data'] ) {
		return null;
	}

	$fetched_at = gmdate( 'c' );
	$best       = null;
	foreach ( $body['data'] as $record ) {
		$candidate = tra_vel_v2_price_normalize_record( $record, $airport, $fetched_at );
		if ( null === $candidate ) {
			continue;
		}
		if ( null === $best || $candidate['price'] < $best['price'] ) {
			$best = $candidate;
		}
	}

	return $best;
}

/**
 * Flag a cached price that is older than a day.
 *
 * A price we found yesterday is still useful context, but it must never be
 * presented as if we had just seen it.
 *
 * @param array<string,mixed> $price Cached price.
 * @return array<string,mixed> Price with a truthful staleness flag.
 */
function tra_vel_v2_price_apply_freshness( $price ) {
	if ( ! is_array( $price ) || ! isset( $price['fetched_at'] ) ) {
		return $price;
	}

	$fetched = strtotime( (string) $price['fetched_at'] );
	$price['is_stale'] = ! $fetched || ( time() - $fetched ) > TRA_VEL_V2_PRICE_STALE_AFTER;

	return $price;
}

/**
 * Whether this request may still spend a bounded network call on a cache miss.
 *
 * Page renders get at most one refresh attempt so a cold cache can never turn
 * a homepage into a chain of upstream timeouts. Cron and WP-CLI refresh the
 * whole set.
 *
 * @return bool
 */
function tra_vel_v2_price_may_fetch() {
	static $spent = 0;

	if ( ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return true;
	}

	/**
	 * Filter how many upstream refresh attempts one visitor request may make.
	 *
	 * @param int $budget Number of allowed refresh attempts.
	 */
	$budget = (int) apply_filters( 'tra_vel_v2_price_request_fetch_budget', 1 );
	if ( $spent >= max( 0, $budget ) ) {
		return false;
	}

	$spent++;

	return true;
}

/**
 * The found price for one destination, or null.
 *
 * @param string $map_state Destination map state.
 * @return array<string,mixed>|null Normalized price payload, or null.
 */
function tra_vel_v2_get_destination_price( $map_state ) {
	$map_state = sanitize_key( (string) $map_state );
	$airport   = tra_vel_v2_price_destination_airport( $map_state );
	if ( '' === $airport ) {
		return null;
	}

	$fresh_key = TRA_VEL_V2_PRICE_TRANSIENT_PREFIX . $map_state;
	$stale_key = TRA_VEL_V2_PRICE_STALE_TRANSIENT_PREFIX . $map_state;
	$cached    = get_transient( $fresh_key );
	if ( is_array( $cached ) && isset( $cached['price'] ) ) {
		return tra_vel_v2_price_apply_freshness( $cached );
	}

	$stale = get_transient( $stale_key );
	$stale = is_array( $stale ) && isset( $stale['price'] ) ? tra_vel_v2_price_apply_freshness( $stale ) : null;

	if ( TRA_VEL_V2_PRICE_UNAVAILABLE === $cached || ! tra_vel_v2_price_may_fetch() ) {
		return $stale;
	}

	$price = tra_vel_v2_price_fetch( $airport );
	if ( null === $price ) {
		set_transient( $fresh_key, TRA_VEL_V2_PRICE_UNAVAILABLE, TRA_VEL_V2_PRICE_NEGATIVE_TTL );
		return $stale;
	}

	set_transient( $fresh_key, $price, TRA_VEL_V2_PRICE_FRESH_TTL );
	set_transient( $stale_key, $price, TRA_VEL_V2_PRICE_STALE_TTL );

	return $price;
}

/**
 * Every destination price we can currently stand behind.
 *
 * Safe to call on any page: destinations without an airport are skipped, and
 * the per request refresh budget bounds a cold cache.
 *
 * @return array<string,array<string,mixed>> Map state to price payload.
 */
function tra_vel_v2_get_all_destination_prices() {
	if ( ! function_exists( 'tra_vel_v2_seo_opportunity_destinations' ) ) {
		return array();
	}

	$prices = array();
	foreach ( array_keys( tra_vel_v2_seo_opportunity_destinations() ) as $map_state ) {
		$price = tra_vel_v2_get_destination_price( $map_state );
		if ( is_array( $price ) ) {
			$prices[ $map_state ] = $price;
		}
	}

	return $prices;
}

/**
 * Warm every destination price so visitors virtually always hit the cache.
 *
 * @return void
 */
function tra_vel_v2_refresh_all_destination_prices() {
	if ( '' === tra_vel_v2_travelpayouts_api_token() || ! function_exists( 'tra_vel_v2_seo_opportunity_destinations' ) ) {
		return;
	}

	foreach ( array_keys( tra_vel_v2_seo_opportunity_destinations() ) as $map_state ) {
		$airport = tra_vel_v2_price_destination_airport( $map_state );
		if ( '' === $airport ) {
			continue;
		}
		$price = tra_vel_v2_price_fetch( $airport );
		if ( null === $price ) {
			set_transient( TRA_VEL_V2_PRICE_TRANSIENT_PREFIX . $map_state, TRA_VEL_V2_PRICE_UNAVAILABLE, TRA_VEL_V2_PRICE_NEGATIVE_TTL );
			continue;
		}
		set_transient( TRA_VEL_V2_PRICE_TRANSIENT_PREFIX . $map_state, $price, TRA_VEL_V2_PRICE_FRESH_TTL );
		set_transient( TRA_VEL_V2_PRICE_STALE_TRANSIENT_PREFIX . $map_state, $price, TRA_VEL_V2_PRICE_STALE_TTL );
	}
}
add_action( TRA_VEL_V2_PRICE_CRON_HOOK, 'tra_vel_v2_refresh_all_destination_prices' );

/**
 * Keep the twice daily warm-up scheduled.
 *
 * @return void
 */
function tra_vel_v2_price_schedule_refresh() {
	if ( ! wp_next_scheduled( TRA_VEL_V2_PRICE_CRON_HOOK ) ) {
		wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'twicedaily', TRA_VEL_V2_PRICE_CRON_HOOK );
	}
}
add_action( 'after_switch_theme', 'tra_vel_v2_price_schedule_refresh' );
add_action( 'init', 'tra_vel_v2_price_schedule_refresh' );

/**
 * Stop warming prices for a theme that is no longer serving the site.
 *
 * @return void
 */
function tra_vel_v2_price_unschedule_refresh() {
	$timestamp = wp_next_scheduled( TRA_VEL_V2_PRICE_CRON_HOOK );
	while ( $timestamp ) {
		wp_unschedule_event( $timestamp, TRA_VEL_V2_PRICE_CRON_HOOK );
		$timestamp = wp_next_scheduled( TRA_VEL_V2_PRICE_CRON_HOOK );
	}
}
add_action( 'switch_theme', 'tra_vel_v2_price_unschedule_refresh' );

/**
 * Format one found amount for public display.
 *
 * @param array<string,mixed>|int $price Price payload or raw amount.
 * @return string Formatted amount such as the shekel sign followed by 463.
 */
function tra_vel_v2_format_found_price( $price ) {
	$amount = is_array( $price ) ? (int) ( isset( $price['price'] ) ? $price['price'] : 0 ) : (int) $price;
	if ( $amount <= 0 ) {
		return '';
	}

	return '₪' . number_format_i18n( $amount, 0 );
}

/**
 * The traveler facing scope of every found price on the site.
 *
 * @return string
 */
function tra_vel_v2_found_price_scope_note() {
	return __( 'מחיר שנמצא בחיפושים אחרונים, לא כולל שינויים וכבודה. המחיר הסופי נסגר אצל ספק ההזמנה.', 'tra-vel-v2' );
}

/**
 * The headline line for a found price, including its honest route shape.
 *
 * @param array<string,mixed> $price Price payload.
 * @return string
 */
function tra_vel_v2_found_price_headline( $price ) {
	$amount = tra_vel_v2_format_found_price( $price );
	if ( '' === $amount ) {
		return '';
	}

	$transfers = isset( $price['transfers'] ) ? (int) $price['transfers'] : 0;
	if ( $transfers < 1 ) {
		$shape = __( 'טיסה ישירה', 'tra-vel-v2' );
	} elseif ( 1 === $transfers ) {
		$shape = __( 'עם עצירה אחת', 'tra-vel-v2' );
	} else {
		$shape = __( 'עם עצירות', 'tra-vel-v2' );
	}

	return sprintf(
		/* translators: 1: formatted found price, 2: route shape. */
		__( 'מ-%1$s הלוך ושוב, %2$s', 'tra-vel-v2' ),
		$amount,
		$shape
	);
}

/**
 * The freshness sub line for a found price.
 *
 * @param array<string,mixed> $price Price payload.
 * @return string
 */
function tra_vel_v2_found_price_freshness( $price ) {
	if ( ! is_array( $price ) ) {
		return '';
	}
	if ( ! empty( $price['is_stale'] ) ) {
		return __( 'המחיר עשוי להשתנות, בדקו עכשיו', 'tra-vel-v2' );
	}

	$fetched = isset( $price['fetched_at'] ) ? strtotime( (string) $price['fetched_at'] ) : 0;
	if ( ! $fetched ) {
		return __( 'המחיר עשוי להשתנות, בדקו עכשיו', 'tra-vel-v2' );
	}

	return sprintf(
		/* translators: %s: date the price was last found. */
		__( 'נמצא לאחרונה ב-%s', 'tra-vel-v2' ),
		wp_date( 'j.n.Y', $fetched )
	);
}

/**
 * Render the found price block next to a destination call to action.
 *
 * Deliberately schema free: these prices are third party observations, so the
 * markup carries no Offer, Product or AggregateOffer vocabulary.
 *
 * @param string               $map_state Destination map state.
 * @param array<string,string> $args      Optional label overrides.
 * @return void
 */
function tra_vel_v2_render_found_price( $map_state, $args = array() ) {
	$price = tra_vel_v2_get_destination_price( $map_state );
	if ( ! is_array( $price ) ) {
		return;
	}

	$headline = tra_vel_v2_found_price_headline( $price );
	if ( '' === $headline ) {
		return;
	}

	$args = wp_parse_args(
		$args,
		array(
			'cta_label' => __( 'בדקו את המחיר אצל ספק ההזמנה', 'tra-vel-v2' ),
		)
	);
	$stale = ! empty( $price['is_stale'] );
	?>
	<div class="found-price<?php echo $stale ? ' is-stale' : ''; ?>" data-found-price-block>
		<p class="found-price-headline"><i data-lucide="plane" aria-hidden="true"></i><strong><?php echo esc_html( $headline ); ?></strong></p>
		<p class="found-price-freshness"><?php echo esc_html( tra_vel_v2_found_price_freshness( $price ) ); ?></p>
		<a class="found-price-cta" href="<?php echo esc_url( $price['deep_link'] ); ?>" target="_blank" rel="sponsored nofollow noopener"><?php echo esc_html( $args['cta_label'] ); ?><i data-lucide="external-link" aria-hidden="true"></i></a>
		<p class="found-price-scope"><?php echo esc_html( tra_vel_v2_found_price_scope_note() ); ?></p>
	</div>
	<?php
}

/**
 * The pin attribute payload for one destination, ready for the Earth.
 *
 * @param array<string,mixed>|null $price Price payload.
 * @return string Formatted amount for the pin, or an empty string.
 */
function tra_vel_v2_found_price_pin_label( $price ) {
	return is_array( $price ) ? tra_vel_v2_format_found_price( $price ) : '';
}
