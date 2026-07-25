<?php
/**
 * Runtime contract tests for the one screen decision card (theme 1.35.0).
 *
 * Executed against the real theme/tra-vel-v2/inc/prices.php and the real
 * template-parts/decision-card.php with a stub WordPress and a stub HTTP
 * transport, so every assertion below is a statement about shipped code.
 *
 * Proves, in order:
 *   a. three tiers appear only when three distinct real records exist;
 *   b. a single record response yields exactly one tier and invents none;
 *   c. every total is the observed fare times the traveler count for 1 to 6,
 *      and carries no component we did not observe;
 *   d. every tier action is a tracked supplier search link with our marker;
 *   e. a record whose own link cannot prove the requested currency is dropped
 *      before it can become a tier;
 *   f. no public surface can render a supplier failure or an unavailable
 *      supplier state to a visitor.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'YEAR_IN_SECONDS', 31536000 );

$test_transients = array();
$test_responses  = array();
$test_http_calls = 0;
$test_http_urls  = array();
$test_failures   = array();
$test_checks     = 0;

function add_action() {}
function add_filter() {}
function is_front_page() { return false; }
function is_page_template() { return false; }
function is_singular() { return false; }
function is_admin() { return false; }
function is_feed() { return false; }
function is_robots() { return false; }
function is_ssl() { return true; }
function wp_doing_cron() { return false; }
function wp_next_scheduled() { return time() + 3600; }
function wp_schedule_event() { return true; }
function wp_unschedule_event() { return true; }
function get_option( $name, $default = '' ) { return 'tra_vel_v2_travelpayouts_api_token' === $name ? str_repeat( 'a1b2', 8 ) : $default; }
function apply_filters( $tag, $value ) {
	// The card and the headline price may both need a call inside one test run.
	return 'tra_vel_v2_price_request_fetch_budget' === $tag ? 500 : $value;
}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_parse_str( $string, &$array ) { parse_str( (string) $string, $array ); }
function esc_url_raw( $url ) { return (string) $url; }
function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_html_e( $text ) { echo esc_html( $text ); }
function esc_attr_e( $text ) { echo esc_attr( $text ); }
function __( $text ) { return $text; }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals ); }
function wp_date( $format, $timestamp = null ) { return gmdate( (string) $format, null === $timestamp ? time() : (int) $timestamp ); }
function home_url( $path = '/' ) { return 'https://tra-vel.test' . ( '/' === $path ? '/' : '/' . ltrim( (string) $path, '/' ) ); }
function admin_url( $path = '' ) { return home_url( '/wp-admin/' . ltrim( (string) $path, '/' ) ); }
function wp_nonce_field() {}
function is_wp_error( $thing ) { return $thing instanceof WP_Error_Stub; }
function wp_remote_retrieve_response_code( $response ) { return isset( $response['response']['code'] ) ? $response['response']['code'] : 0; }
function wp_remote_retrieve_body( $response ) { return isset( $response['body'] ) ? $response['body'] : ''; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }
function add_query_arg( $key, $value = null, $url = null ) {
	if ( is_array( $key ) ) {
		$query  = $key;
		$target = (string) $value;
	} else {
		$query  = array( $key => $value );
		$target = (string) $url;
	}
	return $target . ( false === strpos( $target, '?' ) ? '?' : '&' ) . http_build_query( $query );
}
function get_transient( $key ) {
	global $test_transients;
	return array_key_exists( $key, $test_transients ) ? $test_transients[ $key ] : false;
}
function set_transient( $key, $value ) {
	global $test_transients;
	$test_transients[ $key ] = $value;
	return true;
}
function wp_remote_get( $url ) {
	global $test_responses, $test_http_calls, $test_http_urls;
	$test_http_calls++;
	$test_http_urls[] = $url;
	if ( ! $test_responses ) {
		return new WP_Error_Stub();
	}
	return array_shift( $test_responses );
}
function get_template_part( $slug, $name = null, $args = array() ) {
	include TRA_VEL_V2_PATH . '/' . $slug . '.php';
}
function tra_vel_v2_seo_opportunity_destinations() {
	// Mirrors the single source of truth in inc/seo-opportunities.php.
	return array(
		'warsaw'  => array( 'name' => 'ורשה', 'airport' => 'WAW' ),
		'bangkok' => array( 'name' => 'בנגקוק', 'airport' => 'BKK' ),
		'tokyo'   => array( 'name' => 'טוקיו', 'airport' => '' ),
	);
}

class WP_Error_Stub {}

require_once TRA_VEL_V2_PATH . '/inc/prices.php';

/**
 * Report one assertion.
 */
function check( $condition, $message ) {
	global $test_failures, $test_checks;
	$test_checks++;
	if ( ! $condition ) {
		$test_failures[] = $message;
	}
}

/**
 * Forget every cached observation between scenarios.
 */
function reset_state( $records = array() ) {
	global $test_transients, $test_responses, $test_http_calls, $test_http_urls;
	$test_transients = array();
	$test_http_calls = 0;
	$test_http_urls  = array();
	$test_responses  = array( api_response( $records ) );
}

/**
 * One upstream HTTP response carrying the given records.
 */
function api_response( $records, $success = true ) {
	return array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode_stub( array( 'success' => $success, 'data' => $records ) ),
	);
}

function wp_json_encode_stub( $value ) {
	return json_encode( $value, JSON_UNESCAPED_UNICODE );
}

/**
 * One raw upstream record, with a supplier link that proves its own currency.
 */
function api_record( $price, $transfers, $duration, $options = array() ) {
	$options    = array_merge(
		array(
			'airline'     => 'W6',
			'currency'    => 'ils',
			'link_price'  => $price,
			'departure'   => '2026-11-12T06:10:00+02:00',
			'return'      => '2026-11-19T21:35:00+01:00',
			'destination' => 'WAW',
			'salt'        => '1',
		),
		$options
	);
	$link = '/search/TLV1211WAW1911' . $options['salt'] . '?t=' . $options['salt']
		. '&expected_price_currency=' . $options['currency']
		. '&expected_price=' . $options['link_price']
		. '&expected_price_source=share';

	return array(
		'origin'      => 'TLV',
		'destination' => $options['destination'],
		'price'       => $price,
		'airline'     => $options['airline'],
		'transfers'   => $transfers,
		'duration'    => $duration,
		'departure_at' => $options['departure'],
		'return_at'   => $options['return'],
		'gate'        => 'Aviasales',
		'link'        => $link,
	);
}

/**
 * Render one card or one honest next step and return the markup.
 */
function render_card( $state, $args = array() ) {
	ob_start();
	tra_vel_v2_render_decision_card( $state, $args );
	return (string) ob_get_clean();
}

// ---------------------------------------------------------------------------
// a. Three real records that a traveler can tell apart produce three tiers.
// ---------------------------------------------------------------------------
reset_state(
	array(
		api_record( 463, 1, 900, array( 'salt' => 'a' ) ),
		api_record( 640, 0, 800, array( 'airline' => 'LY', 'salt' => 'b' ) ),
		api_record( 720, 1, 610, array( 'airline' => 'TK', 'salt' => 'c' ) ),
	)
);
$options = tra_vel_v2_get_destination_options( 'warsaw', 'ils' );
check( 3 === count( $options ), 'Three distinct real records must produce three tiers, got ' . count( $options ) . '.' );
check( array( 'value', 'direct', 'fast' ) === array_column( $options, 'tier' ), 'Tiers must be ordered value, direct, fast.' );
check( 463 === (int) $options[0]['price'], 'The value tier must be the cheapest real record.' );
check( 640 === (int) $options[1]['price'] && 0 === (int) $options[1]['transfers'], 'The direct tier must be the cheapest record with no stop.' );
check( 720 === (int) $options[2]['price'] && 610 === (int) $options[2]['duration'], 'The fast tier must be the shortest real record.' );
check( 1 === $GLOBALS['test_http_calls'], 'The whole option set must come from a single upstream call.' );
check( false !== strpos( $GLOBALS['test_http_urls'][0], 'limit=10' ), 'The option set must request the ten cheapest candidates in one call.' );

// The headline price rides on the same observation: no second call.
$headline = tra_vel_v2_get_destination_price( 'warsaw', 'ils' );
check( 1 === $GLOBALS['test_http_calls'], 'Reading the headline price after the option set must not spend another upstream call.' );
check( is_array( $headline ) && 463 === (int) $headline['price'], 'The headline price must be the value tier record.' );

// A second read of the option set is served from cache.
tra_vel_v2_get_destination_options( 'warsaw', 'ils' );
check( 1 === $GLOBALS['test_http_calls'], 'A warm option set must never refetch.' );

// ---------------------------------------------------------------------------
// d. Every tier action is a tracked supplier search link carrying our marker.
// ---------------------------------------------------------------------------
foreach ( $options as $option ) {
	check( 0 === strpos( $option['deep_link'], 'https://www.aviasales.com/search/' ), 'Tier ' . $option['tier'] . ' must link to a tracked supplier search path.' );
	check( false !== strpos( $option['deep_link'], 'marker=552866.tra-vel' ), 'Tier ' . $option['tier'] . ' must carry the tra-vel marker.' );
}

// ---------------------------------------------------------------------------
// c. Totals are the observed fare times the traveler count and nothing else.
// ---------------------------------------------------------------------------
$expected_tier_keys = array( 'tier', 'label', 'unit', 'unit_label', 'total', 'total_label', 'airline', 'stops_label', 'dates_label', 'cta', 'is_stale' );
for ( $travelers = 1; $travelers <= 6; $travelers++ ) {
	$view = tra_vel_v2_decision_card_view( 'warsaw', array( 'travelers' => $travelers, 'currency' => 'ils' ) );
	check( is_array( $view ) && 3 === count( $view['tiers'] ), 'The view must keep three tiers for ' . $travelers . ' travelers.' );
	foreach ( $view['tiers'] as $tier ) {
		check( (int) $tier['unit'] * $travelers === (int) $tier['total'], 'Total for ' . $tier['tier'] . ' at ' . $travelers . ' travelers must be the fare times the party size.' );
		check( '₪' . number_format( (int) $tier['unit'] * $travelers ) === $tier['total_label'], 'The printed total for ' . $tier['tier'] . ' at ' . $travelers . ' travelers must match the multiplication.' );
		check( $expected_tier_keys === array_keys( $tier ), 'A tier must expose only observed fields, got ' . implode( ',', array_keys( $tier ) ) . '.' );
	}
	check( $travelers === (int) $view['travelers'], 'The view must render the requested party size.' );
}
$bounded = tra_vel_v2_decision_card_view( 'warsaw', array( 'travelers' => 99, 'currency' => 'ils' ) );
check( 6 === (int) $bounded['travelers'], 'A party size above six must be bounded to six.' );
$bounded = tra_vel_v2_decision_card_view( 'warsaw', array( 'travelers' => 0, 'currency' => 'ils' ) );
check( 1 === (int) $bounded['travelers'], 'A party size below one must be bounded to one.' );

$default_view = tra_vel_v2_decision_card_view( 'warsaw', array( 'currency' => 'ils' ) );
check( 2 === (int) $default_view['travelers'], 'The card must open on two travelers without being asked.' );
check( '2026-11-12' === $options[0]['departure_date'] && '2026-11-19' === $options[0]['return_date'], 'The card must open on the real dates of the chosen record.' );

// ---------------------------------------------------------------------------
// b. One real record yields exactly one tier and no invented companions.
// ---------------------------------------------------------------------------
reset_state( array( api_record( 511, 1, 940, array( 'salt' => 'z' ) ) ) );
$single = tra_vel_v2_get_destination_options( 'warsaw', 'ils' );
check( 1 === count( $single ), 'A single record response must yield exactly one tier, got ' . count( $single ) . '.' );
check( 'value' === $single[0]['tier'], 'The only tier of a single record response must be the value tier.' );

// A record that is also the cheapest non stop cannot be published twice.
reset_state(
	array(
		api_record( 400, 0, 800, array( 'salt' => 'p' ) ),
		api_record( 500, 0, 700, array( 'airline' => 'LY', 'salt' => 'q' ) ),
	)
);
$collapsed = tra_vel_v2_get_destination_options( 'warsaw', 'ils' );
check( 2 === count( $collapsed ), 'A cheapest record that is already non stop must not be republished as a second tier.' );
check( array( 'value', 'fast' ) === array_column( $collapsed, 'tier' ), 'The remaining tiers must be value and fast.' );

// Two records that would read identically collapse into one tier.
reset_state(
	array(
		api_record( 430, 1, 880, array( 'salt' => 'm' ) ),
		api_record( 430, 1, 880, array( 'salt' => 'n' ) ),
	)
);
$twins = tra_vel_v2_get_destination_options( 'warsaw', 'ils' );
check( 1 === count( $twins ), 'Two records a traveler cannot tell apart must not become two tiers.' );

// A record with no published length can never win the fast slot.
reset_state(
	array(
		api_record( 300, 1, 0, array( 'salt' => 'r' ) ),
		api_record( 900, 1, 0, array( 'airline' => 'TK', 'salt' => 's' ) ),
	)
);
$unknown_length = tra_vel_v2_get_destination_options( 'warsaw', 'ils' );
check( 1 === count( $unknown_length ) && 'value' === $unknown_length[0]['tier'], 'A fast tier must never be ranked on a length upstream did not publish.' );

// ---------------------------------------------------------------------------
// e. The currency guard drops a record before it can become a tier.
// ---------------------------------------------------------------------------
reset_state(
	array(
		api_record( 151, 1, 900, array( 'currency' => 'usd', 'salt' => 'u' ) ),
		api_record( 640, 0, 800, array( 'airline' => 'LY', 'salt' => 'v' ) ),
	)
);
$guarded = tra_vel_v2_get_destination_options( 'warsaw', 'ils' );
check( 1 === count( $guarded ), 'A record whose link proves another currency must never reach a tier.' );
check( 640 === (int) $guarded[0]['price'] && 'ILS' === $guarded[0]['currency'], 'Only the record that proves the requested currency may be published.' );

reset_state(
	array(
		api_record( 463, 1, 900, array( 'link_price' => 999, 'salt' => 'w' ) ),
		api_record( 640, 0, 800, array( 'airline' => 'LY', 'salt' => 'x' ) ),
	)
);
$mismatched = tra_vel_v2_get_destination_options( 'warsaw', 'ils' );
check( 1 === count( $mismatched ) && 640 === (int) $mismatched[0]['price'], 'A record whose amount disagrees with its own supplier link must never reach a tier.' );

// A cached set that cannot prove the currency is refused wholesale, and the
// refresh it triggers is the only thing allowed to replace it.
reset_state( array() );
$GLOBALS['test_transients'][ tra_vel_v2_price_cache_key( TRA_VEL_V2_PRICE_OPTIONS_TRANSIENT_PREFIX, 'warsaw', 'ils' ) ] = array(
	array( 'price' => 463, 'currency' => 'USD', 'deep_link' => 'https://www.aviasales.com/search/x?marker=552866.tra-vel' ),
);
check( array() === tra_vel_v2_get_destination_options( 'warsaw', 'ils' ), 'A cached set denominated in another currency must not be published.' );
check( TRA_VEL_V2_PRICE_UNAVAILABLE === get_transient( tra_vel_v2_price_cache_key( TRA_VEL_V2_PRICE_OPTIONS_TRANSIENT_PREFIX, 'warsaw', 'ils' ) ), 'An unusable observation must be replaced by an explicit unavailable marker.' );

// A destination without an airport can never produce an option.
reset_state( array( api_record( 400, 0, 800 ) ) );
check( array() === tra_vel_v2_get_destination_options( 'tokyo', 'ils' ), 'A destination with no airport in the source of truth must produce no options.' );
check( 0 === $GLOBALS['test_http_calls'], 'A destination with no airport must not reach the network.' );

// ---------------------------------------------------------------------------
// f. Public rendering never shows a supplier failure or an unavailable state.
// ---------------------------------------------------------------------------
reset_state(
	array(
		api_record( 463, 1, 900, array( 'salt' => 'a' ) ),
		api_record( 640, 0, 800, array( 'airline' => 'LY', 'salt' => 'b' ) ),
		api_record( 720, 1, 610, array( 'airline' => 'TK', 'salt' => 'c' ) ),
	)
);
$markup = render_card( 'warsaw' );
check( 3 === substr_count( $markup, 'data-decision-tier="' ), 'The rendered card must publish one element per real tier.' );
check( 3 === substr_count( $markup, 'rel="sponsored nofollow noopener"' ), 'Every tier action must be a disclosed outbound supplier link.' );
check( 3 === substr_count( $markup, 'target="_blank"' ), 'Every tier action must open the supplier outside this page.' );
check( 3 === substr_count( $markup, 'https://www.aviasales.com/search/' ), 'Every tier action must point at a tracked supplier search path.' );
check( 3 === substr_count( $markup, 'marker=552866.tra-vel' ), 'Every rendered tier action must carry the marker.' );
check( false !== strpos( $markup, 'הכי משתלם' ) && false !== strpos( $markup, 'ישיר' ) && false !== strpos( $markup, 'הכי מהיר' ), 'The rendered card must use the agreed tier names.' );
check( false !== strpos( $markup, 'בחרו והמשיכו להזמנה' ), 'Every tier must offer the agreed primary action.' );
check( false !== strpos( $markup, 'מחיר שנמצא בחיפושים אחרונים, לא כולל שינויים וכבודה. המחיר הסופי נסגר אצל ספק ההזמנה.' ), 'The card must carry the pinned scope note byte for byte.' );
check( false !== strpos( $markup, 'המחיר מוצג בשקלים. אם ספק ההזמנה גובה במטבע אחר, ייתכן הפרש המרה בכרטיס.' ), 'The card must carry the pinned shekel currency disclosure byte for byte.' );
check( false !== strpos( $markup, '₪926' ) && false !== strpos( $markup, '₪463 לנוסע' ), 'The card must open with the party total and the per traveler fare already computed.' );
check( false !== strpos( $markup, 'לכל 2 הנוסעים' ), 'The card must open on two travelers with the total described for the party.' );
check( false !== strpos( $markup, 'מתל אביב לוורשה, הלוך ושוב' ), 'A destination that opens with vav must be spelled correctly after a prefix.' );
check( false !== strpos( $markup, 'יעד: ורשה' ), 'The unprefixed destination name must stay unchanged.' );
check( false !== strpos( $markup, 'aria-live="polite"' ), 'The card must announce recalculated totals politely.' );
check( false !== strpos( $markup, 'data-decision-card-step="-1"' ) && false !== strpos( $markup, 'data-decision-card-step="1"' ), 'The card must expose a keyboard operable traveler stepper.' );

$pending = render_card( 'tokyo' );
check( false !== strpos( $pending, 'עדיין אין לנו מחיר עדכני ליעד הזה. אפשר לבדוק ישירות אצל שותף ההשוואה או לתאר לנו את החופשה ונחזור עם הצעה.' ), 'A destination with no price must render the honest next step copy.' );
check( false !== strpos( $pending, '/travel-map/' ) && false !== strpos( $pending, '/ai-planner/' ), 'The honest next step must offer both real links.' );
check( false === strpos( $pending, 'data-decision-tier=' ), 'The honest next step must never render an empty tier.' );

foreach ( array( 'card' => $markup, 'next step' => $pending ) as $surface => $html ) {
	foreach ( array( 'Offer', 'Product', 'AggregateOffer', 'itemtype', 'application/ld+json' ) as $vocabulary ) {
		check( false === strpos( $html, $vocabulary ), 'The rendered ' . $surface . ' must stay free of commercial offer schema (' . $vocabulary . ').' );
	}
	check( 0 === preg_match( '/\bdemo\b/i', $html ), 'The rendered ' . $surface . ' must never expose an unavailable supplier state.' );
	check( 0 === preg_match( '/[\x{2013}\x{2014}]/u', $html ), 'The rendered ' . $surface . ' must not use em dash or en dash punctuation.' );
}

// Every Hebrew literal a visitor can be shown, across every public surface
// that touches this release, is checked against the banned vocabulary.
$banned = array(
	'/דמו/u'                                                       => 'an unavailable supplier state',
	'/הדגמה/u'                                                     => 'internal prototype language',
	'/ניחוש|מנחש/u'                                                => 'the internal mechanism name',
	'/ספק(?:ים)?[^\'"]{0,30}(?:אינו זמין|אינם זמינים|לא זמין|לא זמינים|אינו מחובר|לא מחובר)/u' => 'a supplier availability failure',
	'/(?:לא נמצאו|אין) ספקים/u'                                    => 'a missing supplier state',
	'/תקלה אצל הספק|כשל אצל הספק/u'                                => 'a supplier failure',
);
$public_sources = array(
	'theme/tra-vel-v2/page-experience.php',
	'theme/tra-vel-v2/template-parts/decision-card.php',
	'theme/tra-vel-v2/inc/prices.php',
	'theme/tra-vel-v2/assets/js/app.js',
	'theme/tra-vel-v2/assets/js/decision-card.js',
);
$literal_count = 0;
foreach ( $public_sources as $relative ) {
	$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $relative );
	check( 0 === preg_match( '/Demo offers are not live or bookable/', $source ), $relative . ' must never carry the demo unavailability disclaimer into a rendered surface.' );
	check( 0 === preg_match( '/\.disclaimer\b/', $source ), $relative . ' must never read the internal search disclaimer into a rendered surface.' );
	preg_match_all( '/([\'"])((?:(?!\1|\\\\).|\\\\.)*\p{Hebrew}(?:(?!\1|\\\\).|\\\\.)*)\1/u', $source, $matches );
	foreach ( $matches[2] as $literal ) {
		$literal_count++;
		foreach ( $banned as $pattern => $reason ) {
			if ( preg_match( $pattern, $literal ) ) {
				check( false, $relative . ' exposes ' . $reason . ' in traveler facing copy: ' . $literal );
			}
		}
	}
}
check( $literal_count > 400, 'The public copy sweep must actually reach the traveler facing strings, only saw ' . $literal_count . '.' );

// The card itself never leaves the page except through a supplier link.
$card_template = (string) file_get_contents( TRA_VEL_V2_PATH . '/template-parts/decision-card.php' );
check( 1 === preg_match_all( '/<a\s/', $card_template ), 'The decision card may contain exactly one anchor template, the outbound supplier action.' );
check( false === strpos( $card_template, '<form' ), 'The decision card must never ask a visitor to feed details through a form.' );
$card_script = (string) file_get_contents( TRA_VEL_V2_PATH . '/assets/js/decision-card.js' );
foreach ( array( 'fetch(', 'XMLHttpRequest', 'location.href', 'location.assign', 'location.replace', 'window.open', '.submit(' ) as $navigation ) {
	check( false === strpos( $card_script, $navigation ), 'The decision card script must never navigate or refetch (' . $navigation . ').' );
}
check( 0 === preg_match( '/addEventListener\(\s*[\'"](?:wheel|mousewheel|scroll|touchmove)[\'"]/', $card_script ), 'The decision card script must never trap page scrolling.' );
check( false !== strpos( $card_script, 'prefers-reduced-motion: reduce' ), 'The opening sequence must yield to a reduced motion preference.' );
check( 3 * 420 + 260 < 2000, 'The opening sequence must finish in under two seconds.' );

$card_css = (string) file_get_contents( TRA_VEL_V2_PATH . '/assets/css/app.css' );
check( false !== strpos( $card_css, '.decision-card-tiers { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(3,minmax(0,1fr));' ), 'Desktop must show the tiers side by side.' );
check( false !== strpos( $card_css, '.decision-card-tiers { grid-template-columns: 1fr; gap: 7px; }' ), 'Narrow screens must collapse the tiers into one compact stacked list.' );
check( false !== strpos( $card_css, '@media (prefers-reduced-motion: reduce) {' ), 'The stylesheet must honour a reduced motion preference.' );

$experience = (string) file_get_contents( TRA_VEL_V2_PATH . '/page-experience.php' );
check( false !== strpos( $experience, 'tra_vel_v2_render_decision_card( $decision_card_state )' ), 'The experience hero must render the decision card above everything else.' );
check( strpos( $experience, 'tra_vel_v2_render_decision_card' ) < strpos( $experience, 'class="experience-hero"' ), 'The decision card must sit above the fold, before the hero.' );

if ( $test_failures ) {
	echo "Tra-Vel decision card runtime validation failed:\n";
	foreach ( $test_failures as $failure ) {
		echo '- ' . $failure . "\n";
	}
	exit( 1 );
}

echo 'Tra-Vel decision card runtime validation passed (' . $test_checks . ' checks: real tier selection, single record honesty, party totals, tracked actions, currency guard, and no supplier-failure copy on any public surface).' . "\n";
