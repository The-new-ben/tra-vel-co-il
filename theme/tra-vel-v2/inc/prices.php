<?php
/**
 * Found flight prices for the Earth (theme 1.35.0).
 *
 * Travelpayouts publishes fares that travelers found in recent searches. They
 * are not our inventory, we never sell them, and they can move at any moment.
 * Every value that leaves this file therefore carries the moment it was
 * retrieved, is labelled as a found price in the templates, and links out to
 * the supplier that actually owns the fare.
 *
 * Money is never converted here. The upstream endpoint honours a currency
 * parameter natively, so a shekel price and a dollar price are two separate
 * upstream observations with two separate caches. There is no exchange rate
 * table, no multiplication and no client side conversion anywhere in this
 * theme: a number we display is a number a supplier published in that exact
 * currency. Because the response record itself carries no currency field, the
 * currency is proven from the record's own supplier link before the number is
 * allowed onto a page.
 *
 * Nothing here may block or break a page render. The visitor is always served
 * from cache, a twice daily cron job keeps that cache warm, and every failure
 * mode degrades to no price at all instead of to a price we cannot stand
 * behind. We never emit Offer, Product or AggregateOffer schema for these
 * numbers: they are third party observations, not our commercial offer.
 *
 * Release 1.35.0 adds the option set. One upstream response already contains
 * several genuinely different fares, so the same single call that produces the
 * headline price now also produces up to three real choices: the cheapest, the
 * cheapest non stop and the shortest. A tier exists only when a real record
 * stands behind it, and two records that would read identically to a traveler
 * collapse into one. There is no fourth call, no synthetic option and no
 * placeholder tier.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

define( 'TRA_VEL_V2_PRICE_ENDPOINT', 'https://api.travelpayouts.com/aviasales/v3/prices_for_dates' );
define( 'TRA_VEL_V2_PRICE_BOOKING_ORIGIN', 'https://www.aviasales.com' );
define( 'TRA_VEL_V2_PRICE_MARKER', '552866.tra-vel' );
define( 'TRA_VEL_V2_PRICE_ORIGIN_AIRPORT', 'TLV' );
define( 'TRA_VEL_V2_PRICE_CURRENCY', 'ILS' );
define( 'TRA_VEL_V2_PRICE_DEFAULT_CURRENCY', 'ils' );
define( 'TRA_VEL_V2_CURRENCY_COOKIE', 'tra_vel_currency' );
define( 'TRA_VEL_V2_CURRENCY_ACTION', 'tra_vel_currency' );
define( 'TRA_VEL_V2_CURRENCY_COOKIE_TTL', YEAR_IN_SECONDS );
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
define( 'TRA_VEL_V2_PRICE_OPTIONS_TRANSIENT_PREFIX', 'tra_vel_v2_price_opts_' );
define( 'TRA_VEL_V2_PRICE_OPTIONS_STALE_TRANSIENT_PREFIX', 'tra_vel_v2_price_opts_stale_' );
define( 'TRA_VEL_V2_PRICE_OPTIONS_LIMIT', 10 );
define( 'TRA_VEL_V2_PRICE_OPTIONS_MAX', 3 );
define( 'TRA_VEL_V2_PRICE_MAX_DURATION', 6000 );
define( 'TRA_VEL_V2_DECISION_CARD_MIN_TRAVELERS', 1 );
define( 'TRA_VEL_V2_DECISION_CARD_MAX_TRAVELERS', 6 );
define( 'TRA_VEL_V2_DECISION_CARD_DEFAULT_TRAVELERS', 2 );

/**
 * The currencies this site is allowed to display, mapped to their symbol.
 *
 * The default currency can never be filtered away: every unknown, unsupported
 * or hostile value resolves to it, so it has to exist.
 *
 * @return array<string,string> Lower case ISO code to display symbol.
 */
function tra_vel_v2_supported_currencies() {
	$base = array(
		'ils' => '₪',
		'usd' => '$',
		'eur' => '€',
	);

	/**
	 * Filter the currencies the site may display.
	 *
	 * @param array<string,string> $base Lower case ISO code to display symbol.
	 */
	$filtered = apply_filters( 'tra_vel_v2_supported_currencies', $base );
	if ( ! is_array( $filtered ) || ! $filtered ) {
		return $base;
	}

	$supported = array();
	foreach ( $filtered as $code => $symbol ) {
		$code   = is_string( $code ) ? strtolower( trim( $code ) ) : '';
		$symbol = is_string( $symbol ) ? trim( $symbol ) : '';
		if ( ! preg_match( '/^[a-z]{3}$/', $code ) || '' === $symbol ) {
			continue;
		}
		$supported[ $code ] = function_exists( 'mb_substr' ) ? mb_substr( $symbol, 0, 4 ) : substr( $symbol, 0, 4 );
	}

	if ( ! isset( $supported[ TRA_VEL_V2_PRICE_DEFAULT_CURRENCY ] ) ) {
		$supported = array_merge(
			array( TRA_VEL_V2_PRICE_DEFAULT_CURRENCY => $base[ TRA_VEL_V2_PRICE_DEFAULT_CURRENCY ] ),
			$supported
		);
	}

	return $supported;
}

/**
 * Resolve any candidate into a currency we are allowed to display.
 *
 * @param mixed $currency Candidate currency code.
 * @return string Lower case supported ISO code.
 */
function tra_vel_v2_normalize_currency( $currency ) {
	$currency = is_string( $currency ) ? strtolower( trim( $currency ) ) : '';
	if ( ! preg_match( '/^[a-z]{3}$/', $currency ) ) {
		return TRA_VEL_V2_PRICE_DEFAULT_CURRENCY;
	}

	return isset( tra_vel_v2_supported_currencies()[ $currency ] ) ? $currency : TRA_VEL_V2_PRICE_DEFAULT_CURRENCY;
}

/**
 * The display symbol for one currency.
 *
 * @param string $currency Currency code.
 * @return string Display symbol.
 */
function tra_vel_v2_currency_symbol( $currency ) {
	$currencies = tra_vel_v2_supported_currencies();
	$currency   = tra_vel_v2_normalize_currency( $currency );

	return isset( $currencies[ $currency ] ) ? $currencies[ $currency ] : $currencies[ TRA_VEL_V2_PRICE_DEFAULT_CURRENCY ];
}

/**
 * The currency this request displays.
 *
 * The preference lives only in a cookie, never in a URL: a currency query
 * argument would create a second crawlable copy of every indexed page. We also
 * never guess from IP or Accept-Language. A Hebrew page is a shekel page until
 * the visitor says otherwise.
 *
 * @return string Lower case supported ISO code.
 */
function tra_vel_v2_current_currency() {
	// A display preference, not an authenticated action: reading it is safe
	// without a nonce, and the value is re-derived against the allowlist.
	$cookie = isset( $_COOKIE[ TRA_VEL_V2_CURRENCY_COOKIE ] ) ? $_COOKIE[ TRA_VEL_V2_CURRENCY_COOKIE ] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$cookie = is_string( $cookie ) ? wp_unslash( $cookie ) : '';

	return tra_vel_v2_normalize_currency( $cookie );
}

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
 * Resolve one IATA code back to its destination map state.
 *
 * The same single source of truth, read the other way round, so a page that
 * only knows an airport code can still find the destination we hold prices
 * for. A code that is not in that table simply has no map state here.
 *
 * @param string $airport Three letter IATA code.
 * @return string Destination map state, or an empty string.
 */
function tra_vel_v2_price_state_for_airport( $airport ) {
	$airport = strtoupper( trim( (string) $airport ) );
	if ( ! preg_match( '/^[A-Z]{3}$/', $airport ) || ! function_exists( 'tra_vel_v2_seo_opportunity_destinations' ) ) {
		return '';
	}

	foreach ( tra_vel_v2_seo_opportunity_destinations() as $map_state => $destination ) {
		if ( isset( $destination['airport'] ) && $airport === strtoupper( (string) $destination['airport'] ) ) {
			return sanitize_key( (string) $map_state );
		}
	}

	return '';
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
 * Prove that one record really is denominated in the currency we asked for.
 *
 * This is the reason release 1.34.0 exists. The endpoint honours the currency
 * parameter, but the response record carries no currency field at all, so a
 * record on its own can never tell us what its number means. The supplier link
 * inside the same record does: it carries expected_price_currency and
 * expected_price, which is the amount the traveler will see on the supplier
 * side. A record is trustworthy only when that link agrees with both the
 * currency we requested and the amount the record published. Anything else is
 * a number whose currency we cannot prove, and we would rather show nothing.
 *
 * @param mixed  $link     Raw link value from the API record.
 * @param string $currency Lower case currency that was requested.
 * @param int    $amount   Amount published by the same record.
 * @return bool Whether the record may be displayed in that currency.
 */
function tra_vel_v2_price_link_confirms_currency( $link, $currency, $amount ) {
	if ( ! is_string( $link ) || '' === $link || ! preg_match( '/^[a-z]{3}$/', (string) $currency ) ) {
		return false;
	}

	$query = wp_parse_url( $link, PHP_URL_QUERY );
	if ( ! is_string( $query ) || '' === $query ) {
		return false;
	}

	$args = array();
	wp_parse_str( $query, $args );

	$link_currency = isset( $args['expected_price_currency'] ) && is_scalar( $args['expected_price_currency'] )
		? strtolower( trim( (string) $args['expected_price_currency'] ) )
		: '';
	if ( ! preg_match( '/^[a-z]{3}$/', $link_currency ) || $link_currency !== $currency ) {
		return false;
	}

	$link_amount = isset( $args['expected_price'] ) && is_scalar( $args['expected_price'] )
		? trim( (string) $args['expected_price'] )
		: '';
	if ( ! preg_match( '/^\d+$/', $link_amount ) ) {
		return false;
	}

	return (int) $link_amount === (int) $amount;
}

/**
 * Normalize one raw API record into our published price shape.
 *
 * @param mixed  $record    Raw API record.
 * @param string $airport   Expected destination IATA code.
 * @param string $fetched_at RFC3339 retrieval instant.
 * @param string $currency   Lower case currency that was requested.
 * @return array<string,mixed>|null Normalized price, or null when untrustworthy.
 */
function tra_vel_v2_price_normalize_record( $record, $airport, $fetched_at, $currency = TRA_VEL_V2_PRICE_DEFAULT_CURRENCY ) {
	if ( ! is_array( $record ) ) {
		return null;
	}

	$currency = tra_vel_v2_normalize_currency( $currency );

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

	$raw_link = isset( $record['link'] ) ? $record['link'] : '';
	if ( ! tra_vel_v2_price_link_confirms_currency( $raw_link, $currency, $amount ) ) {
		return null;
	}

	$deep_link = tra_vel_v2_price_deep_link( $raw_link );
	if ( '' === $deep_link ) {
		return null;
	}

	$transfers = isset( $record['transfers'] ) && is_numeric( $record['transfers'] ) ? (int) $record['transfers'] : 0;
	if ( $transfers < 0 || $transfers > 8 ) {
		return null;
	}

	$gate = isset( $record['gate'] ) ? sanitize_text_field( (string) $record['gate'] ) : '';
	$gate = function_exists( 'mb_substr' ) ? mb_substr( $gate, 0, 60 ) : substr( $gate, 0, 60 );

	// Total round trip minutes. An absent or impossible value is recorded as
	// zero, which means unknown: such a record can still be the cheapest or the
	// non stop option, but it can never be published as the shortest one.
	$duration = isset( $record['duration'] ) && is_numeric( $record['duration'] ) ? (int) $record['duration'] : 0;
	if ( $duration < 0 || $duration > TRA_VEL_V2_PRICE_MAX_DURATION ) {
		$duration = 0;
	}

	return array(
		'price'           => $amount,
		'currency'        => strtoupper( $currency ),
		'currency_symbol' => tra_vel_v2_currency_symbol( $currency ),
		'departure_date'  => $departure_date,
		'return_date'     => tra_vel_v2_price_sanitize_date( isset( $record['return_at'] ) ? $record['return_at'] : '' ),
		'airline'         => $airline,
		'transfers'       => $transfers,
		'duration'        => $duration,
		'gate'            => $gate,
		'deep_link'       => $deep_link,
		'fetched_at'      => $fetched_at,
		'is_stale'        => false,
	);
}

/**
 * Ask Travelpayouts for every round trip we can currently stand behind.
 *
 * One call, one response, every trustworthy record inside it. The currency is
 * requested upstream, never derived locally: a dollar price is a separate call,
 * not a multiplication of the shekel price.
 *
 * Any failure returns null. The token never reaches a log, an error message or
 * a stored value.
 *
 * @param string $airport  Destination IATA code.
 * @param string $currency Lower case currency to request.
 * @return array<int,array<string,mixed>>|null Records ordered cheapest first, or null.
 */
function tra_vel_v2_price_fetch_records( $airport, $currency = TRA_VEL_V2_PRICE_DEFAULT_CURRENCY ) {
	$token    = tra_vel_v2_travelpayouts_api_token();
	$currency = tra_vel_v2_normalize_currency( $currency );
	if ( '' === $token || ! preg_match( '/^[A-Z]{3}$/', (string) $airport ) ) {
		return null;
	}

	$url = add_query_arg(
		array(
			'origin'      => TRA_VEL_V2_PRICE_ORIGIN_AIRPORT,
			'destination' => $airport,
			'currency'    => $currency,
			'sorting'     => 'price',
			'limit'       => TRA_VEL_V2_PRICE_OPTIONS_LIMIT,
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
	$records    = array();
	// Every returned record is judged on its own. One record whose link proves
	// a different currency, or an amount that disagrees with its own link, is
	// dropped and the next candidate is examined. If none survive we publish
	// nothing at all for this destination in this currency.
	foreach ( $body['data'] as $record ) {
		$candidate = tra_vel_v2_price_normalize_record( $record, $airport, $fetched_at, $currency );
		if ( null === $candidate ) {
			continue;
		}
		$records[] = $candidate;
	}

	if ( ! $records ) {
		return null;
	}

	return tra_vel_v2_price_sort_records( $records );
}

/**
 * Put a record set in a deterministic cheapest first order.
 *
 * Upstream already sorts by price, but selection must never depend on that
 * promise, and PHP 7.4 does not guarantee a stable sort. The original position
 * is therefore the final tiebreak, so the same response always produces the
 * same tiers.
 *
 * @param array<int,array<string,mixed>> $records Normalized records.
 * @return array<int,array<string,mixed>> Ordered records.
 */
function tra_vel_v2_price_sort_records( $records ) {
	$indexed = array();
	foreach ( array_values( $records ) as $position => $record ) {
		$indexed[] = array( 'position' => $position, 'record' => $record );
	}

	usort(
		$indexed,
		static function ( $left, $right ) {
			$by_price = (int) $left['record']['price'] <=> (int) $right['record']['price'];
			if ( 0 !== $by_price ) {
				return $by_price;
			}
			$by_transfers = (int) $left['record']['transfers'] <=> (int) $right['record']['transfers'];
			if ( 0 !== $by_transfers ) {
				return $by_transfers;
			}

			return $left['position'] <=> $right['position'];
		}
	);

	return array_map(
		static function ( $entry ) {
			return $entry['record'];
		},
		$indexed
	);
}

/**
 * Ask Travelpayouts for the cheapest round trip we can currently observe.
 *
 * @param string $airport  Destination IATA code.
 * @param string $currency Lower case currency to request.
 * @return array<string,mixed>|null Normalized price, or null.
 */
function tra_vel_v2_price_fetch( $airport, $currency = TRA_VEL_V2_PRICE_DEFAULT_CURRENCY ) {
	$records = tra_vel_v2_price_fetch_records( $airport, $currency );

	return is_array( $records ) && $records ? $records[0] : null;
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
 * The cache key for one destination in one currency.
 *
 * Currency is part of every key. Two currencies are two independent upstream
 * observations, and a shekel entry must never be able to answer a dollar
 * request. The negative marker is written to the same key, so an unavailable
 * dollar price never suppresses an available shekel price.
 *
 * @param string $prefix    Transient prefix.
 * @param string $map_state Destination map state.
 * @param string $currency  Lower case currency.
 * @return string Transient key.
 */
function tra_vel_v2_price_cache_key( $prefix, $map_state, $currency ) {
	return $prefix . sanitize_key( (string) $map_state ) . '_' . tra_vel_v2_normalize_currency( $currency );
}

/**
 * Whether a cached payload can still prove the currency it is being read for.
 *
 * @param mixed  $price    Cached payload.
 * @param string $currency Lower case currency the caller asked for.
 * @return bool
 */
function tra_vel_v2_price_currency_matches( $price, $currency ) {
	return is_array( $price )
		&& isset( $price['price'], $price['currency'] )
		&& strtolower( (string) $price['currency'] ) === tra_vel_v2_normalize_currency( $currency );
}

/**
 * Whether a cached record set can still prove the currency it is read for.
 *
 * The guard runs per record, not per payload. A single record that cannot
 * prove the requested currency invalidates the whole cached set rather than
 * being quietly skipped, because a set we cannot fully vouch for is a set we
 * would rather refetch.
 *
 * @param mixed  $records  Cached payload.
 * @param string $currency Lower case currency the caller asked for.
 * @return bool
 */
function tra_vel_v2_price_records_currency_matches( $records, $currency ) {
	if ( ! is_array( $records ) || ! $records || array_keys( $records ) !== range( 0, count( $records ) - 1 ) ) {
		return false;
	}

	foreach ( $records as $record ) {
		if ( ! tra_vel_v2_price_currency_matches( $record, $currency ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Flag freshness across a whole record set.
 *
 * @param array<int,array<string,mixed>> $records Cached records.
 * @return array<int,array<string,mixed>> Records with truthful staleness flags.
 */
function tra_vel_v2_price_apply_freshness_to_records( $records ) {
	return array_map( 'tra_vel_v2_price_apply_freshness', array_values( (array) $records ) );
}

/**
 * What one record looks like to a traveler.
 *
 * Two records with the same price, carrier, route shape, length and dates are
 * the same choice as far as anybody reading the page is concerned, even when
 * the supplier returned them as separate rows. The signature is what stops a
 * second tier from being a relabelled copy of the first one.
 *
 * @param array<string,mixed> $record Normalized record.
 * @return string Comparable signature.
 */
function tra_vel_v2_price_record_signature( $record ) {
	return implode(
		'|',
		array(
			(string) ( isset( $record['price'] ) ? (int) $record['price'] : 0 ),
			(string) ( isset( $record['currency'] ) ? $record['currency'] : '' ),
			(string) ( isset( $record['airline'] ) ? $record['airline'] : '' ),
			(string) ( isset( $record['transfers'] ) ? (int) $record['transfers'] : 0 ),
			(string) ( isset( $record['duration'] ) ? (int) $record['duration'] : 0 ),
			(string) ( isset( $record['departure_date'] ) ? $record['departure_date'] : '' ),
			(string) ( isset( $record['return_date'] ) ? $record['return_date'] : '' ),
		)
	);
}

/**
 * Choose the real options a traveler can actually tell apart.
 *
 * Three rules and nothing else: the cheapest record is the value option, the
 * cheapest record with no stop is the direct option, and the shortest record is
 * the fast option. A slot with no record behind it is simply not returned, and
 * a slot whose record would read exactly like one already chosen is dropped
 * rather than duplicated. A record whose length upstream did not publish can
 * never win the fast slot, because we would be ranking a number we do not have.
 *
 * @param array<int,array<string,mixed>> $records Normalized records, cheapest first.
 * @return array<int,array<string,mixed>> One to three records, each carrying a tier key.
 */
function tra_vel_v2_price_select_options( $records ) {
	if ( ! is_array( $records ) || ! $records ) {
		return array();
	}

	$records = tra_vel_v2_price_sort_records( array_values( $records ) );

	$direct_index = null;
	$fast_index   = null;
	foreach ( $records as $index => $record ) {
		if ( 0 === (int) $record['transfers'] && ( null === $direct_index || (int) $record['price'] < (int) $records[ $direct_index ]['price'] ) ) {
			$direct_index = $index;
		}

		$duration = isset( $record['duration'] ) ? (int) $record['duration'] : 0;
		if ( $duration <= 0 ) {
			continue;
		}
		if ( null === $fast_index ) {
			$fast_index = $index;
			continue;
		}
		$shortest = (int) $records[ $fast_index ]['duration'];
		if ( $duration < $shortest || ( $duration === $shortest && (int) $record['price'] < (int) $records[ $fast_index ]['price'] ) ) {
			$fast_index = $index;
		}
	}

	$options    = array();
	$signatures = array();
	foreach ( array( 'value' => 0, 'direct' => $direct_index, 'fast' => $fast_index ) as $tier => $index ) {
		if ( null === $index || ! isset( $records[ $index ] ) ) {
			continue;
		}
		$signature = tra_vel_v2_price_record_signature( $records[ $index ] );
		if ( in_array( $signature, $signatures, true ) ) {
			continue;
		}
		$signatures[]      = $signature;
		$option            = $records[ $index ];
		$option['tier']    = $tier;
		$options[]         = $option;
	}

	return array_slice( $options, 0, TRA_VEL_V2_PRICE_OPTIONS_MAX );
}

/**
 * Write one upstream observation into every cache that depends on it.
 *
 * The headline price and the option set are two readings of the same response,
 * so one call fills both. That is the whole reason the option set costs nothing
 * extra: whichever surface asks first pays, and every other surface is served
 * from the cache it just warmed.
 *
 * @param string                              $map_state Destination map state.
 * @param string                              $currency  Lower case currency.
 * @param array<int,array<string,mixed>>|null $records   Fetched records, or null on failure.
 * @return array<int,array<string,mixed>>|null Stored records, or null.
 */
function tra_vel_v2_price_store_records( $map_state, $currency, $records ) {
	$fresh_price   = tra_vel_v2_price_cache_key( TRA_VEL_V2_PRICE_TRANSIENT_PREFIX, $map_state, $currency );
	$stale_price   = tra_vel_v2_price_cache_key( TRA_VEL_V2_PRICE_STALE_TRANSIENT_PREFIX, $map_state, $currency );
	$fresh_options = tra_vel_v2_price_cache_key( TRA_VEL_V2_PRICE_OPTIONS_TRANSIENT_PREFIX, $map_state, $currency );
	$stale_options = tra_vel_v2_price_cache_key( TRA_VEL_V2_PRICE_OPTIONS_STALE_TRANSIENT_PREFIX, $map_state, $currency );

	if ( ! is_array( $records ) || ! $records ) {
		set_transient( $fresh_price, TRA_VEL_V2_PRICE_UNAVAILABLE, TRA_VEL_V2_PRICE_NEGATIVE_TTL );
		set_transient( $fresh_options, TRA_VEL_V2_PRICE_UNAVAILABLE, TRA_VEL_V2_PRICE_NEGATIVE_TTL );
		return null;
	}

	$records = array_values( $records );
	set_transient( $fresh_options, $records, TRA_VEL_V2_PRICE_FRESH_TTL );
	set_transient( $stale_options, $records, TRA_VEL_V2_PRICE_STALE_TTL );
	set_transient( $fresh_price, $records[0], TRA_VEL_V2_PRICE_FRESH_TTL );
	set_transient( $stale_price, $records[0], TRA_VEL_V2_PRICE_STALE_TTL );

	return $records;
}

/**
 * Spend one bounded upstream call and refresh every cache it feeds.
 *
 * @param string $map_state Destination map state.
 * @param string $airport   Destination IATA code.
 * @param string $currency  Lower case currency.
 * @return array<int,array<string,mixed>>|null Fresh records, or null.
 */
function tra_vel_v2_price_refresh_destination( $map_state, $airport, $currency ) {
	return tra_vel_v2_price_store_records( $map_state, $currency, tra_vel_v2_price_fetch_records( $airport, $currency ) );
}

/**
 * The cached record set for one destination, or null.
 *
 * @param string $map_state Destination map state.
 * @param string $currency  Lower case currency.
 * @param bool   $stale     Whether to read the seven day copy.
 * @return array<int,array<string,mixed>>|null Records, or null.
 */
function tra_vel_v2_price_cached_records( $map_state, $currency, $stale = false ) {
	$prefix  = $stale ? TRA_VEL_V2_PRICE_OPTIONS_STALE_TRANSIENT_PREFIX : TRA_VEL_V2_PRICE_OPTIONS_TRANSIENT_PREFIX;
	$records = get_transient( tra_vel_v2_price_cache_key( $prefix, $map_state, $currency ) );

	return tra_vel_v2_price_records_currency_matches( $records, $currency ) ? tra_vel_v2_price_apply_freshness_to_records( $records ) : null;
}

/**
 * The found price for one destination, or null.
 *
 * @param string      $map_state Destination map state.
 * @param string|null $currency  Currency to display, or null for this request's currency.
 * @return array<string,mixed>|null Normalized price payload, or null.
 */
function tra_vel_v2_get_destination_price( $map_state, $currency = null ) {
	$map_state = sanitize_key( (string) $map_state );
	$currency  = null === $currency ? tra_vel_v2_current_currency() : tra_vel_v2_normalize_currency( $currency );
	$airport   = tra_vel_v2_price_destination_airport( $map_state );
	if ( '' === $airport ) {
		return null;
	}

	$fresh_key = tra_vel_v2_price_cache_key( TRA_VEL_V2_PRICE_TRANSIENT_PREFIX, $map_state, $currency );
	$stale_key = tra_vel_v2_price_cache_key( TRA_VEL_V2_PRICE_STALE_TRANSIENT_PREFIX, $map_state, $currency );
	$cached    = get_transient( $fresh_key );
	if ( tra_vel_v2_price_currency_matches( $cached, $currency ) ) {
		return tra_vel_v2_price_apply_freshness( $cached );
	}

	// A warm option set already contains this destination's cheapest record, so
	// a decision card that fetched first pays for the headline price too.
	$warm = tra_vel_v2_price_cached_records( $map_state, $currency );
	if ( null !== $warm ) {
		return $warm[0];
	}

	$stale = get_transient( $stale_key );
	$stale = tra_vel_v2_price_currency_matches( $stale, $currency ) ? tra_vel_v2_price_apply_freshness( $stale ) : null;

	if ( TRA_VEL_V2_PRICE_UNAVAILABLE === $cached || ! tra_vel_v2_price_may_fetch() ) {
		return $stale;
	}

	$records = tra_vel_v2_price_refresh_destination( $map_state, $airport, $currency );

	return is_array( $records ) && $records ? $records[0] : $stale;
}

/**
 * The real option set for one destination: one, two or three genuine records.
 *
 * Selected from the same cached upstream response the headline price uses, so
 * this adds no API call of its own. Returns an empty array whenever we have
 * nothing we can prove, which is the only honest alternative to inventing a
 * choice.
 *
 * @param string      $map_state Destination map state.
 * @param string|null $currency  Currency to display, or null for this request's currency.
 * @return array<int,array<string,mixed>> Ordered options, each carrying a tier key.
 */
function tra_vel_v2_get_destination_options( $map_state, $currency = null ) {
	$map_state = sanitize_key( (string) $map_state );
	$currency  = null === $currency ? tra_vel_v2_current_currency() : tra_vel_v2_normalize_currency( $currency );
	$airport   = tra_vel_v2_price_destination_airport( $map_state );
	if ( '' === $airport ) {
		return array();
	}

	$fresh = tra_vel_v2_price_cached_records( $map_state, $currency );
	if ( null !== $fresh ) {
		return tra_vel_v2_price_select_options( $fresh );
	}

	$stale = tra_vel_v2_price_cached_records( $map_state, $currency, true );
	$marker = get_transient( tra_vel_v2_price_cache_key( TRA_VEL_V2_PRICE_OPTIONS_TRANSIENT_PREFIX, $map_state, $currency ) );
	if ( TRA_VEL_V2_PRICE_UNAVAILABLE === $marker || ! tra_vel_v2_price_may_fetch() ) {
		return null === $stale ? array() : tra_vel_v2_price_select_options( $stale );
	}

	$records = tra_vel_v2_price_refresh_destination( $map_state, $airport, $currency );
	if ( ! is_array( $records ) || ! $records ) {
		return null === $stale ? array() : tra_vel_v2_price_select_options( $stale );
	}

	return tra_vel_v2_price_select_options( $records );
}

/**
 * Every destination price we can currently stand behind.
 *
 * Safe to call on any page: destinations without an airport are skipped, and
 * the per request refresh budget bounds a cold cache. A visitor who selected a
 * currency the cron does not warm therefore sees prices appear gradually
 * instead of waiting on a chain of upstream calls.
 *
 * @param string|null $currency Currency to display, or null for this request's currency.
 * @return array<string,array<string,mixed>> Map state to price payload.
 */
function tra_vel_v2_get_all_destination_prices( $currency = null ) {
	if ( ! function_exists( 'tra_vel_v2_seo_opportunity_destinations' ) ) {
		return array();
	}

	$currency = null === $currency ? tra_vel_v2_current_currency() : tra_vel_v2_normalize_currency( $currency );
	$prices   = array();
	foreach ( array_keys( tra_vel_v2_seo_opportunity_destinations() ) as $map_state ) {
		$price = tra_vel_v2_get_destination_price( $map_state, $currency );
		if ( is_array( $price ) ) {
			$prices[ $map_state ] = $price;
		}
	}

	return $prices;
}

/**
 * Warm every destination price so visitors virtually always hit the cache.
 *
 * Only the default currency is warmed, and deliberately so. Warming every
 * supported currency would multiply the upstream call volume by the size of
 * the allowlist for a preference most visitors never change. Shekel visitors
 * stay instant, and a dollar or euro visitor pays for one bounded call on the
 * first view of a destination and hits the cache from then on.
 *
 * @return void
 */
function tra_vel_v2_refresh_all_destination_prices() {
	if ( '' === tra_vel_v2_travelpayouts_api_token() || ! function_exists( 'tra_vel_v2_seo_opportunity_destinations' ) ) {
		return;
	}

	$currency = TRA_VEL_V2_PRICE_DEFAULT_CURRENCY;
	foreach ( array_keys( tra_vel_v2_seo_opportunity_destinations() ) as $map_state ) {
		$airport = tra_vel_v2_price_destination_airport( $map_state );
		if ( '' === $airport ) {
			continue;
		}
		// One call per destination warms the headline price and the option set
		// together, exactly as a visitor request would.
		tra_vel_v2_price_refresh_destination( $map_state, $airport, $currency );
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
 * Whether the current request can render a found price.
 *
 * @return bool
 */
function tra_vel_v2_price_surface() {
	$surface = is_front_page()
		|| is_page_template( 'page-map.php' )
		|| is_page_template( 'page-destination.php' )
		|| is_page_template( 'page-seo-opportunity.php' )
		|| is_page_template( 'page-pillar.php' )
		|| is_page_template( 'page-experience.php' )
		|| is_singular( 'destination' );

	/**
	 * Filter whether this request may render prices and the currency switcher.
	 *
	 * @param bool $surface Whether this request renders prices.
	 */
	return (bool) apply_filters( 'tra_vel_v2_price_surface', $surface );
}

/**
 * Reduce any candidate to a same site path we are willing to return to.
 *
 * Absolute URLs, protocol relative URLs, schemes, hosts, traversal and header
 * injection are all rejected outright, and any currency argument that somehow
 * reached the URL is stripped. What survives is a leading slash path, which
 * wp_safe_redirect resolves against this host and no other.
 *
 * @param mixed $candidate Candidate path or URL.
 * @return string Same site path, or an empty string.
 */
function tra_vel_v2_currency_sanitize_return_path( $candidate ) {
	$candidate = is_string( $candidate ) ? trim( wp_unslash( $candidate ) ) : '';
	if ( '' === $candidate || strlen( $candidate ) > 600 ) {
		return '';
	}
	if ( 0 !== strpos( $candidate, '/' ) || 0 === strpos( $candidate, '//' ) || preg_match( '/[\x00-\x1f\x7f]/', $candidate ) ) {
		return '';
	}

	$parts = wp_parse_url( $candidate );
	if ( ! is_array( $parts ) || isset( $parts['scheme'] ) || isset( $parts['host'] ) ) {
		return '';
	}

	$path = isset( $parts['path'] ) ? $parts['path'] : '/';
	if ( '' === $path || 0 !== strpos( $path, '/' ) || false !== strpos( $path, '..' ) ) {
		return '';
	}

	$args = array();
	if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
		wp_parse_str( $parts['query'], $args );
	}
	// The hard SEO law of this release: a currency never survives into a URL.
	foreach ( array( 'currency', 'cur', TRA_VEL_V2_CURRENCY_COOKIE ) as $forbidden ) {
		unset( $args[ $forbidden ] );
	}

	return $args ? $path . '?' . http_build_query( $args ) : $path;
}

/**
 * The path this request should return to after a currency switch.
 *
 * @return string Same site path.
 */
function tra_vel_v2_currency_return_path() {
	$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	$path    = tra_vel_v2_currency_sanitize_return_path( $request );

	return '' === $path ? '/' : $path;
}

/**
 * The traveler facing name of one currency.
 *
 * @param string $currency Currency code.
 * @return string
 */
function tra_vel_v2_currency_name( $currency ) {
	$currency = tra_vel_v2_normalize_currency( $currency );
	$names    = array(
		'ils' => __( 'שקל', 'tra-vel-v2' ),
		'usd' => __( 'דולר', 'tra-vel-v2' ),
		'eur' => __( 'אירו', 'tra-vel-v2' ),
	);

	return isset( $names[ $currency ] ) ? $names[ $currency ] : strtoupper( $currency );
}

/**
 * The currency switcher for the site header.
 *
 * A real form that posts to admin-post.php and redirects back to the same
 * path. It works with JavaScript disabled, it is reachable by keyboard, and it
 * never puts the choice in a URL.
 *
 * @return void
 */
function tra_vel_v2_render_currency_switcher() {
	if ( ! tra_vel_v2_price_surface() ) {
		return;
	}

	$currencies = tra_vel_v2_supported_currencies();
	if ( count( $currencies ) < 2 ) {
		return;
	}

	$current = tra_vel_v2_current_currency();
	?>
	<form class="currency-switcher" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( TRA_VEL_V2_CURRENCY_ACTION ); ?>">
		<input type="hidden" name="tra_vel_return" value="<?php echo esc_attr( tra_vel_v2_currency_return_path() ); ?>">
		<?php wp_nonce_field( TRA_VEL_V2_CURRENCY_ACTION, 'tra_vel_currency_nonce', false ); ?>
		<div class="currency-switcher-pills" role="group" aria-label="<?php esc_attr_e( 'מטבע התצוגה', 'tra-vel-v2' ); ?>">
			<?php foreach ( $currencies as $code => $symbol ) : ?>
				<?php $is_current = $code === $current; ?>
				<button
					class="currency-pill<?php echo $is_current ? ' is-current' : ''; ?>"
					type="submit"
					name="currency"
					value="<?php echo esc_attr( $code ); ?>"
					aria-pressed="<?php echo $is_current ? 'true' : 'false'; ?>"
					aria-label="<?php echo esc_attr( sprintf( /* translators: %s: currency name. */ __( 'הצגת מחירים ב%s', 'tra-vel-v2' ), tra_vel_v2_currency_name( $code ) ) ); ?>"
				><span aria-hidden="true"><?php echo esc_html( $symbol ); ?></span></button>
			<?php endforeach; ?>
		</div>
	</form>
	<?php
}

/**
 * Whether a currency switch request really came from this site.
 *
 * The nonce is the primary check. A nonce is also a timestamped value, and
 * this form is rendered inside pages that a page cache may hold for longer
 * than a nonce lives, so an expired nonce must not turn the control into a
 * button that silently does nothing. When the nonce has aged out we fall back
 * to a strict same origin check on the request itself, which is the same
 * property the nonce was there to prove. The action writes one three letter
 * display preference and nothing else.
 *
 * @return bool
 */
function tra_vel_v2_currency_request_is_trusted() {
	$nonce = isset( $_POST['tra_vel_currency_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['tra_vel_currency_nonce'] ) ) : '';
	if ( '' !== $nonce && wp_verify_nonce( $nonce, TRA_VEL_V2_CURRENCY_ACTION ) ) {
		return true;
	}

	$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
	if ( '' === $origin ) {
		$referer = wp_get_raw_referer();
		$origin  = is_string( $referer ) ? $referer : '';
	}
	if ( '' === $origin ) {
		return false;
	}

	$origin_host = wp_parse_url( $origin, PHP_URL_HOST );
	$site_host   = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

	return is_string( $origin_host ) && is_string( $site_host ) && strtolower( $origin_host ) === strtolower( $site_host );
}

/**
 * Persist the chosen currency and return the visitor to the same page.
 *
 * @return void
 */
function tra_vel_v2_handle_currency_switch() {
	$return = isset( $_POST['tra_vel_return'] ) ? tra_vel_v2_currency_sanitize_return_path( $_POST['tra_vel_return'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( '' === $return ) {
		$referer = wp_get_referer();
		$referer = is_string( $referer ) ? (string) wp_parse_url( $referer, PHP_URL_PATH ) : '';
		$return  = tra_vel_v2_currency_sanitize_return_path( $referer );
	}
	if ( '' === $return ) {
		$return = '/';
	}

	if ( tra_vel_v2_currency_request_is_trusted() ) {
		$requested = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$currency  = tra_vel_v2_normalize_currency( $requested );
		setcookie(
			TRA_VEL_V2_CURRENCY_COOKIE,
			$currency,
			array(
				'expires'  => time() + TRA_VEL_V2_CURRENCY_COOKIE_TTL,
				'path'     => '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ TRA_VEL_V2_CURRENCY_COOKIE ] = $currency;
	}

	// A relative path can only resolve against this host, and wp_safe_redirect
	// rejects anything that is not local. 303 turns the POST into a clean GET
	// of the same page, with no currency argument anywhere in the URL.
	wp_safe_redirect( $return, 303 );
	exit;
}
add_action( 'admin_post_' . TRA_VEL_V2_CURRENCY_ACTION, 'tra_vel_v2_handle_currency_switch' );
add_action( 'admin_post_nopriv_' . TRA_VEL_V2_CURRENCY_ACTION, 'tra_vel_v2_handle_currency_switch' );

/**
 * Keep price pages cacheable for the default currency and private otherwise.
 *
 * A page that renders prices now differs by one cookie, so it announces Vary:
 * Cookie. That alone is enough for a well behaved shared cache, but a
 * WordPress page cache plugin usually ignores Vary and serves the first
 * rendered copy to everyone. Rather than fight that, we keep the default
 * shekel render fully cacheable, which is what almost every visitor sees, and
 * mark only the non default currency requests as private and unstorable. A
 * dollar visitor costs one uncached render; a shekel visitor costs nothing.
 *
 * @return void
 */
function tra_vel_v2_price_send_cache_headers() {
	if ( is_admin() || is_feed() || is_robots() || headers_sent() || ! tra_vel_v2_price_surface() ) {
		return;
	}

	$vary = array( 'Cookie' );
	foreach ( headers_list() as $sent ) {
		if ( 0 !== stripos( $sent, 'vary:' ) ) {
			continue;
		}
		foreach ( explode( ',', substr( $sent, 5 ) ) as $token ) {
			$token = trim( $token );
			if ( '' !== $token && ! in_array( strtolower( $token ), array_map( 'strtolower', $vary ), true ) ) {
				$vary[] = $token;
			}
		}
	}
	header( 'Vary: ' . implode( ', ', $vary ) );

	if ( TRA_VEL_V2_PRICE_DEFAULT_CURRENCY === tra_vel_v2_current_currency() ) {
		return;
	}

	header( 'Cache-Control: private, no-store' );
	if ( defined( 'LSCWP_V' ) ) {
		// Cooperate with LiteSpeed through its own documented control instead
		// of relying on a header it does not read.
		do_action( 'litespeed_control_set_nocache', 'tra-vel non default display currency' );
	}
}
add_action( 'template_redirect', 'tra_vel_v2_price_send_cache_headers', 20 );

/**
 * Format one found amount for public display.
 *
 * The symbol comes from the payload itself, which carries the currency the
 * supplier link proved. A bare amount without a payload can only be the
 * default currency, because that is the only currency we could have observed
 * without knowing which one was requested.
 *
 * @param array<string,mixed>|int $price Price payload or raw amount.
 * @return string Formatted amount such as the shekel sign followed by 463.
 */
function tra_vel_v2_format_found_price( $price ) {
	$amount = is_array( $price ) ? (int) ( isset( $price['price'] ) ? $price['price'] : 0 ) : (int) $price;
	if ( $amount <= 0 ) {
		return '';
	}

	$symbol = is_array( $price ) && isset( $price['currency_symbol'] ) && is_string( $price['currency_symbol'] )
		? trim( $price['currency_symbol'] )
		: '';
	if ( '' === $symbol ) {
		$symbol = tra_vel_v2_currency_symbol( TRA_VEL_V2_PRICE_DEFAULT_CURRENCY );
	}

	return $symbol . number_format_i18n( $amount, 0 );
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
 * What the chosen display currency actually means at the payment moment.
 *
 * We show a number a supplier published in that currency, but we are not the
 * merchant and we do not know what the supplier will charge or what a card
 * issuer will do with it. Saying that plainly is the entire point of this
 * release. No fee percentage is invented here, because we do not know one.
 *
 * @param string|null $currency Currency being displayed, or null for this request's.
 * @return string
 */
function tra_vel_v2_found_price_currency_note( $currency = null ) {
	$currency = null === $currency ? tra_vel_v2_current_currency() : tra_vel_v2_normalize_currency( $currency );

	if ( TRA_VEL_V2_PRICE_DEFAULT_CURRENCY === $currency ) {
		return __( 'המחיר מוצג בשקלים. אם ספק ההזמנה גובה במטבע אחר, ייתכן הפרש המרה בכרטיס.', 'tra-vel-v2' );
	}

	return __( 'התשלום בפועל מתבצע אצל ספק ההזמנה, לעיתים במטבע אחר. חברת האשראי עשויה לגבות עמלת המרה.', 'tra-vel-v2' );
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
		<p class="found-price-scope found-price-currency-note"><?php echo esc_html( tra_vel_v2_found_price_currency_note( isset( $price['currency'] ) ? $price['currency'] : null ) ); ?></p>
	</div>
	<?php
}

/**
 * The traveler counts the trip cost block offers.
 *
 * @return array<int,int>
 */
function tra_vel_v2_trip_cost_traveler_counts() {
	return array( 1, 2, 3, 4 );
}

/**
 * The default traveler count the server renders.
 *
 * @return int
 */
function tra_vel_v2_trip_cost_default_travelers() {
	return 2;
}

/**
 * What the trip actually costs, using only what we actually know.
 *
 * One found fare multiplied by one traveler count. That is the whole
 * calculation, and the block says so: no accommodation number, no total, no
 * estimate for anything we have not observed. An invented hotel line would be
 * the easiest number on this page to produce and the only dishonest one.
 *
 * @param string $map_state Destination map state.
 * @return void
 */
function tra_vel_v2_render_trip_cost( $map_state ) {
	$price = tra_vel_v2_get_destination_price( $map_state );
	if ( ! is_array( $price ) || empty( $price['price'] ) ) {
		return;
	}

	$unit      = (int) $price['price'];
	$symbol    = isset( $price['currency_symbol'] ) && is_string( $price['currency_symbol'] ) && '' !== trim( $price['currency_symbol'] )
		? trim( $price['currency_symbol'] )
		: tra_vel_v2_currency_symbol( TRA_VEL_V2_PRICE_DEFAULT_CURRENCY );
	$travelers = tra_vel_v2_trip_cost_default_travelers();
	$default   = tra_vel_v2_format_found_price(
		array(
			'price'           => $unit * $travelers,
			'currency_symbol' => $symbol,
		)
	);
	if ( '' === $default ) {
		return;
	}
	?>
	<section class="trip-cost page-width" id="trip-cost" aria-labelledby="trip-cost-title" data-trip-cost data-trip-cost-unit="<?php echo esc_attr( (string) $unit ); ?>" data-trip-cost-symbol="<?php echo esc_attr( $symbol ); ?>">
		<div class="trip-cost-head">
			<span class="eyebrow"><?php esc_html_e( 'טיסות בלבד', 'tra-vel-v2' ); ?></span>
			<h2 id="trip-cost-title"><?php esc_html_e( 'כמה זה עולה לכם', 'tra-vel-v2' ); ?></h2>
		</div>
		<div class="trip-cost-controls" role="group" aria-label="<?php esc_attr_e( 'מספר הנוסעים', 'tra-vel-v2' ); ?>">
			<span class="trip-cost-controls-label"><?php esc_html_e( 'נוסעים', 'tra-vel-v2' ); ?></span>
			<?php foreach ( tra_vel_v2_trip_cost_traveler_counts() as $count ) : ?>
				<?php $is_current = $count === $travelers; ?>
				<button class="trip-cost-traveler<?php echo $is_current ? ' is-current' : ''; ?>" type="button" data-trip-cost-travelers="<?php echo esc_attr( (string) $count ); ?>" aria-pressed="<?php echo $is_current ? 'true' : 'false'; ?>"><?php echo esc_html( number_format_i18n( $count ) ); ?></button>
			<?php endforeach; ?>
		</div>
		<p class="trip-cost-output" aria-live="polite"><span><?php esc_html_e( 'טיסות לכל הנוסעים:', 'tra-vel-v2' ); ?></span> <strong><bdi dir="ltr" data-trip-cost-total><?php echo esc_html( $default ); ?></bdi></strong></p>
		<p class="trip-cost-missing"><?php esc_html_e( 'עוד לא כלול: לינה, העברות, ביטוח וכבודה. נוסיף אותם כשהמחירים יהיו זמינים.', 'tra-vel-v2' ); ?></p>
		<p class="trip-cost-note"><?php echo esc_html( tra_vel_v2_found_price_currency_note( isset( $price['currency'] ) ? $price['currency'] : null ) ); ?></p>
	</section>
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

/**
 * The traveler facing name of each real option slot.
 *
 * @return array<string,string> Tier key to Hebrew label.
 */
function tra_vel_v2_decision_card_tier_labels() {
	return array(
		'value'  => __( 'הכי משתלם', 'tra-vel-v2' ),
		'direct' => __( 'ישיר', 'tra-vel-v2' ),
		'fast'   => __( 'הכי מהיר', 'tra-vel-v2' ),
	);
}

/**
 * The route shape of one option, in words a traveler uses.
 *
 * @param int $transfers Number of stops.
 * @return string
 */
function tra_vel_v2_decision_card_stops_label( $transfers ) {
	$transfers = max( 0, (int) $transfers );
	if ( 0 === $transfers ) {
		return __( 'ישיר', 'tra-vel-v2' );
	}
	if ( 1 === $transfers ) {
		return __( 'עצירה אחת', 'tra-vel-v2' );
	}

	/* translators: %s: number of stops. */
	return sprintf( __( '%s עצירות', 'tra-vel-v2' ), number_format_i18n( $transfers ) );
}

/**
 * One calendar date as a compact day and month.
 *
 * Formatted straight from the stored calendar string. Converting it through a
 * timestamp would let a site timezone move the day the supplier published.
 *
 * @param string $date YYYY-MM-DD.
 * @return string Day dot month, or an empty string.
 */
function tra_vel_v2_decision_card_date_label( $date ) {
	$date = tra_vel_v2_price_sanitize_date( $date );
	if ( '' === $date ) {
		return '';
	}
	$parts = explode( '-', $date );

	return number_format_i18n( (int) $parts[2] ) . '.' . number_format_i18n( (int) $parts[1] );
}

/**
 * The travel window of one option.
 *
 * @param array<string,mixed> $option Normalized option.
 * @return string
 */
function tra_vel_v2_decision_card_dates_label( $option ) {
	$departure = tra_vel_v2_decision_card_date_label( isset( $option['departure_date'] ) ? $option['departure_date'] : '' );
	$return    = tra_vel_v2_decision_card_date_label( isset( $option['return_date'] ) ? $option['return_date'] : '' );
	if ( '' === $departure ) {
		return '';
	}
	if ( '' === $return ) {
		return $departure;
	}

	/* translators: 1: outbound date, 2: return date. */
	return sprintf( __( '%1$s עד %2$s', 'tra-vel-v2' ), $departure, $return );
}

/**
 * One city name spelled the way it reads after a single letter prefix.
 *
 * Hebrew doubles an opening vav once a prefix is attached, so Warsaw is
 * lamed plus vav vav resh, not lamed plus vav resh, which a reader would sound
 * out as a different word entirely.
 *
 * @param string $city Destination name.
 * @return string Name ready to follow a one letter prefix.
 */
function tra_vel_v2_decision_card_prefixed_city( $city ) {
	$city = (string) $city;

	return 0 === strpos( $city, 'ו' ) ? 'ו' . $city : $city;
}

/**
 * Bound any traveler count to what this card is allowed to price.
 *
 * @param mixed $travelers Candidate count.
 * @return int
 */
function tra_vel_v2_decision_card_travelers( $travelers = null ) {
	$travelers = is_numeric( $travelers ) ? (int) $travelers : TRA_VEL_V2_DECISION_CARD_DEFAULT_TRAVELERS;

	return max( TRA_VEL_V2_DECISION_CARD_MIN_TRAVELERS, min( TRA_VEL_V2_DECISION_CARD_MAX_TRAVELERS, $travelers ) );
}

/**
 * How a total is described for one party size.
 *
 * @param int $travelers Traveler count.
 * @return string
 */
function tra_vel_v2_decision_card_party_label( $travelers ) {
	$travelers = tra_vel_v2_decision_card_travelers( $travelers );
	if ( 1 === $travelers ) {
		return __( 'לנוסע אחד', 'tra-vel-v2' );
	}

	/* translators: %s: number of travelers. */
	return sprintf( __( 'לכל %s הנוסעים', 'tra-vel-v2' ), number_format_i18n( $travelers ) );
}

/**
 * Everything the decision card needs, or null when we have nothing real.
 *
 * The whole view is computed on the server, including every total, so the card
 * a visitor without JavaScript receives is the finished card and not a shell
 * waiting to be filled.
 *
 * @param string               $map_state Destination map state.
 * @param array<string,mixed>  $args      Optional overrides.
 * @return array<string,mixed>|null View model, or null when no real option exists.
 */
function tra_vel_v2_decision_card_view( $map_state, $args = array() ) {
	$map_state = sanitize_key( (string) $map_state );
	if ( '' === $map_state ) {
		return null;
	}

	$args = wp_parse_args(
		$args,
		array(
			'travelers' => TRA_VEL_V2_DECISION_CARD_DEFAULT_TRAVELERS,
			'currency'  => null,
		)
	);

	$options = tra_vel_v2_get_destination_options( $map_state, $args['currency'] );
	if ( ! $options ) {
		return null;
	}

	$destinations = function_exists( 'tra_vel_v2_seo_opportunity_destinations' ) ? tra_vel_v2_seo_opportunity_destinations() : array();
	$city         = isset( $destinations[ $map_state ]['name'] ) ? (string) $destinations[ $map_state ]['name'] : '';
	if ( '' === $city ) {
		return null;
	}

	$travelers = tra_vel_v2_decision_card_travelers( $args['travelers'] );
	$labels    = tra_vel_v2_decision_card_tier_labels();
	$symbol    = isset( $options[0]['currency_symbol'] ) && is_string( $options[0]['currency_symbol'] ) && '' !== trim( $options[0]['currency_symbol'] )
		? trim( $options[0]['currency_symbol'] )
		: tra_vel_v2_currency_symbol( TRA_VEL_V2_PRICE_DEFAULT_CURRENCY );

	$tiers = array();
	foreach ( $options as $option ) {
		$unit = isset( $option['price'] ) ? (int) $option['price'] : 0;
		$tier = isset( $option['tier'] ) ? (string) $option['tier'] : '';
		if ( $unit <= 0 || ! isset( $labels[ $tier ] ) || empty( $option['deep_link'] ) ) {
			continue;
		}
		$tiers[] = array(
			'tier'        => $tier,
			'label'       => $labels[ $tier ],
			'unit'        => $unit,
			'unit_label'  => tra_vel_v2_format_found_price( array( 'price' => $unit, 'currency_symbol' => $symbol ) ),
			'total'       => $unit * $travelers,
			'total_label' => tra_vel_v2_format_found_price( array( 'price' => $unit * $travelers, 'currency_symbol' => $symbol ) ),
			'airline'     => isset( $option['airline'] ) ? (string) $option['airline'] : '',
			'stops_label' => tra_vel_v2_decision_card_stops_label( isset( $option['transfers'] ) ? $option['transfers'] : 0 ),
			'dates_label' => tra_vel_v2_decision_card_dates_label( $option ),
			'cta'         => (string) $option['deep_link'],
			'is_stale'    => ! empty( $option['is_stale'] ),
		);
	}

	if ( ! $tiers ) {
		return null;
	}

	return array(
		'state'         => $map_state,
		'city'          => $city,
		'prefixed_city' => tra_vel_v2_decision_card_prefixed_city( $city ),
		'origin'        => __( 'תל אביב', 'tra-vel-v2' ),
		'symbol'        => $symbol,
		'currency'      => isset( $options[0]['currency'] ) ? (string) $options[0]['currency'] : '',
		'travelers'     => $travelers,
		'min_travelers' => TRA_VEL_V2_DECISION_CARD_MIN_TRAVELERS,
		'max_travelers' => TRA_VEL_V2_DECISION_CARD_MAX_TRAVELERS,
		'party_label'   => tra_vel_v2_decision_card_party_label( $travelers ),
		'party_one'     => tra_vel_v2_decision_card_party_label( 1 ),
		/* translators: %s: number of travelers. */
		'party_many'    => __( 'לכל %s הנוסעים', 'tra-vel-v2' ),
		'tiers'         => $tiers,
		'fill_lines'    => array(
			/* translators: %s: number of travelers. */
			sprintf( __( 'מתאימים לכם: %s נוסעים', 'tra-vel-v2' ), number_format_i18n( $travelers ) ),
			__( 'תאריכים גמישים', 'tra-vel-v2' ),
			/* translators: %s: destination city. */
			sprintf( __( 'יעד: %s', 'tra-vel-v2' ), $city ),
		),
		'fill_count'    => $travelers,
		'scope_note'    => tra_vel_v2_found_price_scope_note(),
		'currency_note' => tra_vel_v2_found_price_currency_note( isset( $options[0]['currency'] ) ? $options[0]['currency'] : null ),
		'freshness'     => tra_vel_v2_found_price_freshness( $options[0] ),
		'cta_label'     => __( 'בחרו והמשיכו להזמנה', 'tra-vel-v2' ),
	);
}

/**
 * The two real places a traveler can go when we have no price to show.
 *
 * @param string $map_state Destination map state.
 * @return array<string,string> Absolute same site URLs.
 */
function tra_vel_v2_decision_card_next_steps( $map_state ) {
	$map_state = sanitize_key( (string) $map_state );

	return array(
		'map'     => $map_state ? add_query_arg( 'destination', $map_state, home_url( '/travel-map/' ) ) : home_url( '/travel-map/' ),
		'planner' => $map_state ? add_query_arg( 'destination', $map_state, home_url( '/ai-planner/' ) ) : home_url( '/ai-planner/' ),
	);
}

/**
 * The one screen decision card, or an honest next step when we have no price.
 *
 * Never renders a supplier failure and never renders an empty shell. Either a
 * visitor sees real, priced, bookable choices, or a visitor sees two working
 * links and a plain sentence explaining why the price is missing.
 *
 * Deliberately schema free, exactly like every other found price surface.
 *
 * @param string              $map_state Destination map state.
 * @param array<string,mixed> $args      Optional overrides.
 * @return void
 */
function tra_vel_v2_render_decision_card( $map_state, $args = array() ) {
	$view = tra_vel_v2_decision_card_view( $map_state, $args );
	if ( null === $view ) {
		tra_vel_v2_render_decision_card_next_step( $map_state );
		return;
	}

	get_template_part( 'template-parts/decision-card', null, $view );
}

/**
 * The honest state: no price yet, and two real ways forward.
 *
 * @param string $map_state Destination map state.
 * @return void
 */
function tra_vel_v2_render_decision_card_next_step( $map_state ) {
	$links        = tra_vel_v2_decision_card_next_steps( $map_state );
	$destinations = function_exists( 'tra_vel_v2_seo_opportunity_destinations' ) ? tra_vel_v2_seo_opportunity_destinations() : array();
	$state        = sanitize_key( (string) $map_state );
	$city         = isset( $destinations[ $state ]['name'] ) ? (string) $destinations[ $state ]['name'] : '';
	?>
	<section class="decision-card decision-card-pending page-width" data-decision-card-pending aria-labelledby="decision-card-pending-title">
		<div class="decision-card-head">
			<span class="eyebrow"><?php esc_html_e( 'טיסות בלבד', 'tra-vel-v2' ); ?></span>
			<h2 id="decision-card-pending-title">
				<?php
				echo esc_html(
					$city
						/* translators: %s: destination city. */
						? sprintf( __( 'מתל אביב ל%s, הלוך ושוב', 'tra-vel-v2' ), tra_vel_v2_decision_card_prefixed_city( $city ) )
						: __( 'טיסות הלוך ושוב מתל אביב', 'tra-vel-v2' )
				);
				?>
			</h2>
		</div>
		<p class="decision-card-pending-copy"><?php esc_html_e( 'עדיין אין לנו מחיר עדכני ליעד הזה. אפשר לבדוק ישירות אצל שותף ההשוואה או לתאר לנו את החופשה ונחזור עם הצעה.', 'tra-vel-v2' ); ?></p>
		<div class="decision-card-pending-actions">
			<a class="decision-card-pending-primary" href="<?php echo esc_url( $links['map'] ); ?>"><?php esc_html_e( 'בדקו את היעד במפת החופשות', 'tra-vel-v2' ); ?><i data-lucide="earth" aria-hidden="true"></i></a>
			<a class="decision-card-pending-secondary" href="<?php echo esc_url( $links['planner'] ); ?>"><?php esc_html_e( 'תארו לנו את החופשה', 'tra-vel-v2' ); ?><i data-lucide="sparkles" aria-hidden="true"></i></a>
		</div>
	</section>
	<?php
}
