<?php
/**
 * Runtime contract tests for the cost answer page (theme 1.38.0).
 *
 * Executed against the real theme/tra-vel-v2/inc/prices.php, the real
 * theme/tra-vel-v2/inc/seo.php, the real theme/tra-vel-v2/inc/cost-answers.php
 * and the real theme/tra-vel-v2/page-cost-answer.php, with a stub WordPress
 * and a stub HTTP transport, so every assertion below is a statement about
 * shipped code and about markup a visitor would actually receive.
 *
 * Proves, in order:
 *   a. a destination with no price is noindexed and emits no schema at all;
 *   b. a visible FAQ with fewer than five pairs is noindexed and emits no
 *      FAQPage;
 *   c. FAQPage is emitted only when it is word-for-word the visible FAQ, and
 *      is parsed back out of the markup the page renders;
 *   d. no Offer, Product or AggregateOffer vocabulary appears in the schema
 *      graph, in the plugin graph filter output, or in the rendered page;
 *   e. the when-to-fly section shows observed history only when
 *      tra_vel_v2_price_history_is_meaningful() is true;
 *   f. the cost breakdown prints exactly one number, and it is the observed
 *      fare: every component we do not measure is words only, with no digits;
 *   g. the answer sentence carries the live price and is exactly the meta
 *      description, through the shipped meta description filter chain.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );
define( 'OBJECT', 'OBJECT' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'YEAR_IN_SECONDS', 31536000 );

$test_transients      = array();
$test_responses       = array();
$test_failures        = array();
$test_checks          = 0;
$test_template        = 'page-cost-answer.php';
$test_is_singular     = true;
$test_post_id         = 501;
$test_slug            = 'flight-cost-warsaw';
$test_permalink       = 'https://tra-vel.test/flights/warsaw-cost/';
$test_excerpt         = '';
$test_history_ready   = false;
$test_history_stats   = null;
$test_published_paths = array( 'destinations' => 701, 'flights' => 702, 'destinations/warsaw' => 0 );

class WP_Post {
	public $ID;
	public function __construct( $id ) {
		$this->ID = (int) $id;
	}
}
class WP_Error_Stub {}

function add_action() {}
function add_filter() {}
function is_front_page() { return false; }
function is_admin() { return false; }
function is_feed() { return false; }
function is_robots() { return false; }
function is_ssl() { return true; }
function is_author() { return false; }
function is_category() { return false; }
function is_tag() { return false; }
function is_date() { return false; }
function is_search() { return false; }
function is_page() { return false; }
function wp_doing_cron() { return false; }
function wp_next_scheduled() { return time() + 3600; }
function wp_schedule_event() { return true; }
function wp_unschedule_event() { return true; }
function is_singular( $post_type = '' ) { global $test_is_singular; return $test_is_singular && ( '' === $post_type || 'page' === $post_type ); }
function is_page_template( $template = '' ) { global $test_template; return $template === $test_template; }
function get_queried_object_id() { global $test_post_id; return (int) $test_post_id; }
function get_permalink( $post_id = 0 ) { global $test_permalink; return $test_permalink; }
function get_post_field( $field, $post_id = 0 ) { global $test_slug; return 'post_name' === $field ? $test_slug : ''; }
function get_the_excerpt( $post_id = 0 ) { global $test_excerpt; return $test_excerpt; }
function get_the_title( $post_id = 0 ) { return 'Fixture'; }
function get_bloginfo() { return 'Tra-Vel'; }
function get_option( $name, $default = '' ) { return 'tra_vel_v2_travelpayouts_api_token' === $name ? str_repeat( 'a1b2', 8 ) : $default; }
function get_page_by_path( $path, $output = OBJECT, $post_type = 'page' ) {
	global $test_published_paths;
	$key = trim( (string) $path, '/' );
	return ! empty( $test_published_paths[ $key ] ) ? new WP_Post( $test_published_paths[ $key ] ) : null;
}
function get_post_status( $post ) { return 'publish'; }
function apply_filters( $tag, $value ) {
	return 'tra_vel_v2_price_request_fetch_budget' === $tag ? 500 : $value;
}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
function sanitize_title( $value ) { return strtolower( preg_replace( '/[^a-z0-9-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_trim_words( $value, $limit ) { return implode( ' ', array_slice( preg_split( '/\s+/', trim( (string) $value ) ), 0, $limit ) ); }
function wp_unslash( $value ) { return $value; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_parse_str( $string, &$array ) { parse_str( (string) $string, $array ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }
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
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, (int) $flags ); }
function get_header() {}
function get_footer() {}
function get_template_part( $slug, $name = null, $args = array() ) {}
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
function get_transient( $key ) { global $test_transients; return array_key_exists( $key, $test_transients ) ? $test_transients[ $key ] : false; }
function set_transient( $key, $value ) { global $test_transients; $test_transients[ $key ] = $value; return true; }
function wp_remote_get( $url ) {
	global $test_responses;
	if ( ! $test_responses ) {
		return new WP_Error_Stub();
	}
	return array_shift( $test_responses );
}
function tra_vel_v2_is_destination_guide( $post_id = 0 ) { return false; }
function tra_vel_v2_is_guide_publication_ready( $post_id = 0 ) { return false; }
function tra_vel_v2_price_history_record( $map_state, $currency, $record ) { return false; }
function tra_vel_v2_price_history_is_meaningful( $map_state ) { global $test_history_ready; return (bool) $test_history_ready; }
function tra_vel_v2_price_history_stats( $map_state, $days = 90 ) { global $test_history_stats; return $test_history_stats; }
function tra_vel_v2_seo_opportunity_destinations() {
	// Mirrors the single source of truth in inc/seo-opportunities.php. The
	// mirror is proven against that file below rather than trusted.
	return array(
		'warsaw' => array( 'name' => 'ורשה', 'airport' => 'WAW', 'latitude' => '52.2297', 'longitude' => '21.0122' ),
		'prague' => array( 'name' => 'פראג', 'airport' => 'PRG', 'latitude' => '50.0755', 'longitude' => '14.4378' ),
		'tokyo'  => array( 'name' => 'טוקיו', 'airport' => '', 'latitude' => '35.6762', 'longitude' => '139.6503' ),
	);
}
function tra_vel_v2_load_seo_opportunity_registry( $path = '' ) {
	$entries = array(
		array( 'id' => 'destinations-directory', 'canonicalPath' => '/destinations/', 'pageType' => 'commercial-hub', 'primaryIntent' => 'יעדים לחופשה ולאן לטוס', 'cluster' => 'discovery', 'parentPath' => '/', 'mapState' => null, 'status' => 'live' ),
		array( 'id' => 'flights-hub', 'canonicalPath' => '/flights/', 'pageType' => 'commercial-hub', 'primaryIntent' => 'טיסות זולות לחוץ לארץ', 'cluster' => 'flights', 'parentPath' => '/', 'mapState' => null, 'status' => 'live' ),
		array( 'id' => 'warsaw-guide', 'canonicalPath' => '/destinations/warsaw/', 'pageType' => 'destination-hub', 'primaryIntent' => 'מדריך ורשה לישראלים', 'cluster' => 'warsaw', 'parentPath' => '/destinations/', 'mapState' => 'warsaw', 'status' => 'backlog' ),
	);
	$by_path = array();
	foreach ( $entries as $entry ) {
		$by_path[ $entry['canonicalPath'] ] = $entry;
	}
	return array( 'valid' => true, 'error' => '', 'entries' => $entries, 'by_path' => $by_path, 'by_id' => array() );
}

require_once TRA_VEL_V2_PATH . '/inc/prices.php';
require_once TRA_VEL_V2_PATH . '/inc/seo.php';
require_once TRA_VEL_V2_PATH . '/inc/cost-answers.php';

/** Report one assertion. */
function check( $condition, $message ) {
	global $test_failures, $test_checks;
	$test_checks++;
	if ( ! $condition ) {
		$test_failures[] = $message;
	}
}

/** Forget every cached observation between scenarios. */
function reset_state( $records = array() ) {
	global $test_transients, $test_responses;
	$test_transients = array();
	$test_responses  = $records ? array( api_response( $records ) ) : array( api_response( array(), false ) );
}

/** One upstream HTTP response carrying the given records. */
function api_response( $records, $success = true ) {
	return array(
		'response' => array( 'code' => 200 ),
		'body'     => json_encode( array( 'success' => $success, 'data' => $records ), JSON_UNESCAPED_UNICODE ),
	);
}

/** One raw upstream record, with a supplier link that proves its own currency. */
function api_record( $price, $transfers, $duration, $options = array() ) {
	$options = array_merge(
		array(
			'airline'    => 'W6',
			'currency'   => 'ils',
			'departure'  => '2026-10-11T06:10:00+03:00',
			'return'     => '2026-10-18T21:35:00+02:00',
			'salt'       => '1',
		),
		$options
	);
	$link = '/search/TLV1110WAW1810' . $options['salt'] . '?t=' . $options['salt']
		. '&expected_price_currency=' . $options['currency']
		. '&expected_price=' . $price
		. '&expected_price_source=share';

	return array(
		'origin'       => 'TLV',
		'destination'  => 'WAW',
		'price'        => $price,
		'airline'      => $options['airline'],
		'transfers'    => $transfers,
		'duration'     => $duration,
		'departure_at' => $options['departure'],
		'return_at'    => $options['return'],
		'gate'         => 'Aviasales',
		'link'         => $link,
	);
}

/** Render the shipped template and return its markup. */
function render_cost_answer() {
	ob_start();
	include TRA_VEL_V2_PATH . '/page-cost-answer.php';
	return (string) ob_get_clean();
}

/** The inner HTML of one identified page section. */
function section_html( $html, $section_id ) {
	$start = strpos( $html, 'id="' . $section_id . '"' );
	if ( false === $start ) {
		return '';
	}
	$end = strpos( $html, '</section>', $start );
	return false === $end ? '' : substr( $html, $start, $end - $start );
}

$warsaw_records = array(
	api_record( 463, 1, 900, array( 'salt' => 'a' ) ),
	api_record( 640, 0, 800, array( 'airline' => 'LY', 'salt' => 'b' ) ),
	api_record( 720, 1, 610, array( 'airline' => 'TK', 'salt' => 'c' ) ),
);

// The destination mirror above must never drift from the single source of truth.
$destinations_source = (string) file_get_contents( TRA_VEL_V2_PATH . '/inc/seo-opportunities.php' );
check(
	false !== strpos( $destinations_source, "'warsaw'   => array( 'name' => 'ורשה', 'airport' => 'WAW'" ),
	'The Warsaw fixture no longer mirrors tra_vel_v2_seo_opportunity_destinations().'
);

// ---------------------------------------------------------------------------
// The resolver: a slug names exactly one destination or it names none.
// ---------------------------------------------------------------------------
foreach ( array( 'flight-cost-warsaw' => 'warsaw', 'warsaw-cost' => 'warsaw', 'how-much-warsaw-flight' => 'warsaw' ) as $slug => $expected ) {
	$GLOBALS['test_slug'] = $slug;
	check( $expected === tra_vel_v2_cost_answer_map_state( 501 ), "Slug {$slug} must resolve to {$expected}." );
}
foreach ( array( 'flight-cost', 'flight-cost-warsaw-prague', 'flight-cost-berlin' ) as $slug ) {
	$GLOBALS['test_slug'] = $slug;
	check( '' === tra_vel_v2_cost_answer_map_state( 501 ), "Slug {$slug} must fail closed to no destination." );
}
$GLOBALS['test_slug'] = 'flight-cost-warsaw';

// ---------------------------------------------------------------------------
// g. The answer sentence carries the live price and is the meta description.
// ---------------------------------------------------------------------------
reset_state( $warsaw_records );
$contract = tra_vel_v2_cost_answer_contract( 501 );
$view     = $contract['view'];
check( ! empty( $contract['ready'] ), 'A resolved destination with a real price, three options and five FAQ pairs must be indexable.' );
check( 'warsaw' === $view['map_state'] && 'ורשה' === $view['city'] && 'וורשה' === $view['prefixed_city'], 'The Warsaw view must carry the prefixed Hebrew city name.' );
$answer = (string) $view['answer'];
check( false !== strpos( $answer, '₪463' ), 'The answer sentence must contain the live found price, got: ' . $answer );
check( false !== strpos( $answer, 'טיסה הלוך ושוב מתל אביב לוורשה נמצאה לאחרונה במחיר ' ), 'The answer sentence must open with the pinned extractable clause, got: ' . $answer );
check( false !== strpos( $answer, 'ליציאה ב-11 באוקטובר 2026' ), 'The answer sentence must name the real departure date of the found fare, got: ' . $answer );
check( false !== strpos( $answer, 'עם עצירה אחת.' ), 'The answer sentence must state the real route shape of the found fare, got: ' . $answer );
check( $answer === tra_vel_v2_cost_answer_meta_description( 501 ), 'The meta description must be exactly the answer sentence.' );
check( $answer === tra_vel_v2_singular_meta_description_fallback( 501 ), 'The shipped meta description chain must serve the answer sentence.' );
$GLOBALS['test_excerpt'] = 'תקציר שנכתב בעבר ואינו מכיר את המחיר החי.';
check( $answer === tra_vel_v2_singular_meta_description_fallback( 501 ), 'A stale authored excerpt must not replace the live answer sentence.' );
$GLOBALS['test_excerpt'] = '';
check( $answer === tra_vel_v2_public_meta_description( '' ), 'An empty plugin description must be filled with the live answer sentence.' );
check( 'תיאור שנכתב ביד' === tra_vel_v2_public_meta_description( 'תיאור שנכתב ביד' ), 'A hand written plugin description must still win.' );

// ---------------------------------------------------------------------------
// c. FAQPage mirrors the visible FAQ word for word, parsed from its markup.
// ---------------------------------------------------------------------------
check( 5 === count( $view['faq_items'] ), 'The cost answer FAQ must carry exactly five pairs, got ' . count( $view['faq_items'] ) . '.' );
$parsed = tra_vel_v2_visible_faq_items( 0, (string) $view['faq_markup'] );
check( 5 === count( $parsed ), 'The rendered FAQ markup must parse back to five visible pairs, got ' . count( $parsed ) . '.' );
foreach ( $view['faq_items'] as $index => $item ) {
	check( $parsed[ $index ]['question'] === $item['question'], 'Visible FAQ question ' . ( $index + 1 ) . ' drifted from the rendered markup.' );
	check( $parsed[ $index ]['answer'] === $item['answer'], 'Visible FAQ answer ' . ( $index + 1 ) . ' drifted from the rendered markup.' );
}
$nodes = tra_vel_v2_cost_answer_schema_nodes( 501, $view );
$types = array_column( $nodes, '@type' );
check( in_array( 'WebPage', $types, true ) && in_array( 'BreadcrumbList', $types, true ) && in_array( 'FAQPage', $types, true ), 'A ready cost answer must emit WebPage, BreadcrumbList and FAQPage.' );
$faq_node = null;
foreach ( $nodes as $node ) {
	if ( in_array( 'FAQPage', (array) $node['@type'], true ) ) {
		$faq_node = $node;
	}
}
check( is_array( $faq_node ) && 5 === count( $faq_node['mainEntity'] ), 'The FAQPage node must mirror exactly the five visible pairs.' );
check( is_array( $faq_node ) && $view['faq_items'][0]['question'] === $faq_node['mainEntity'][0]['name'], 'The FAQPage question is not word-identical to the visible question.' );
check( is_array( $faq_node ) && $view['faq_items'][0]['answer'] === $faq_node['mainEntity'][0]['acceptedAnswer']['text'], 'The FAQPage answer is not word-identical to the visible answer.' );
check( is_array( $faq_node ) && false !== strpos( (string) $faq_node['mainEntity'][0]['acceptedAnswer']['text'], '₪463' ), 'The first FAQ answer must repeat the live found price.' );

// A view whose rendered FAQ says something else can only ever produce that
// something else: the schema is parsed from the markup, never from intent.
$mismatched                = $view;
$mismatched['faq_markup']  = str_replace( $view['faq_items'][0]['question'], 'שאלה אחרת לגמרי?', (string) $view['faq_markup'] );
$mismatched_nodes          = tra_vel_v2_cost_answer_schema_nodes( 501, $mismatched );
$mismatched_faq            = null;
foreach ( $mismatched_nodes as $node ) {
	if ( in_array( 'FAQPage', (array) $node['@type'], true ) ) {
		$mismatched_faq = $node;
	}
}
check( is_array( $mismatched_faq ) && 'שאלה אחרת לגמרי?' === $mismatched_faq['mainEntity'][0]['name'], 'FAQ schema must be parsed from the rendered markup, not from the intended items.' );

// ---------------------------------------------------------------------------
// b. Fewer than five visible pairs: noindex, and no FAQPage at all.
// ---------------------------------------------------------------------------
$four_pairs               = $view;
$four_pairs['faq_markup'] = tra_vel_v2_cost_answer_faq_markup( array_slice( $view['faq_items'], 0, 4 ), $view['faq_title'] );
$four_contract            = tra_vel_v2_cost_answer_contract( 501, $four_pairs );
check( 4 === count( tra_vel_v2_visible_faq_items( 0, (string) $four_pairs['faq_markup'] ) ), 'The four pair fixture must really render four pairs.' );
check( empty( $four_contract['ready'] ) && false === $four_contract['checks']['visible_faq'], 'A cost answer with four visible FAQ pairs must fail its indexability contract.' );
check( array() === tra_vel_v2_cost_answer_schema_nodes( 501, $four_pairs ), 'A cost answer with four visible FAQ pairs must emit no schema at all.' );

$no_faq               = $view;
$no_faq['faq_markup'] = '<p>' . esc_html( $view['faq_items'][0]['question'] ) . '</p>';
$no_faq_contract      = tra_vel_v2_cost_answer_contract( 501, $no_faq );
check( empty( $no_faq_contract['ready'] ), 'A cost answer whose FAQ section does not render must fail its indexability contract.' );
check( array() === tra_vel_v2_cost_answer_schema_nodes( 501, $no_faq ), 'A cost answer with no visible FAQ must emit no schema.' );

// ---------------------------------------------------------------------------
// d. No Offer, Product or AggregateOffer anywhere.
// ---------------------------------------------------------------------------
$graph_json = (string) json_encode( tra_vel_v2_schema_data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
foreach ( array( 'Offer', 'Product', 'AggregateOffer', 'ItemList', 'priceCurrency' ) as $forbidden ) {
	check( false === strpos( $graph_json, $forbidden ), "The cost answer schema graph must never carry {$forbidden}." );
}
check( false !== strpos( $graph_json, 'FAQPage' ) && false !== strpos( $graph_json, 'BreadcrumbList' ), 'The cost answer schema graph must carry FAQPage and BreadcrumbList.' );

$plugin_graph = array(
	array( '@type' => 'WebSite' ),
	array( '@type' => 'Product' ),
	array( '@type' => 'Offer' ),
	array( '@type' => 'AggregateOffer' ),
	array( '@type' => 'ItemList' ),
	array( '@type' => 'Article' ),
	array( '@type' => 'FAQPage', 'mainEntity' => array( array( 'name' => 'שאלה שהומצאה' ) ) ),
	array( '@type' => 'WebPage', 'offers' => array() ),
);
$merged       = tra_vel_v2_cost_answer_schema_graph( $plugin_graph );
$merged_types = array_column( $merged, '@type' );
check( ! array_intersect( array( 'Product', 'Offer', 'AggregateOffer', 'ItemList', 'Article' ), $merged_types ), 'The plugin graph filter must drop unvalidated commercial and Article nodes.' );
$merged_faq = array();
foreach ( $merged as $node ) {
	if ( in_array( 'FAQPage', (array) ( $node['@type'] ?? array() ), true ) ) {
		$merged_faq[] = $node;
	}
}
check( 1 === count( $merged_faq ) && $view['faq_items'][0]['question'] === $merged_faq[0]['mainEntity'][0]['name'], 'A fabricated plugin FAQPage must be replaced by the visible-content node.' );

// ---------------------------------------------------------------------------
// e and f: the rendered page itself.
// ---------------------------------------------------------------------------
// Observations already exist for this destination; only the meaningfulness gate
// is still closed. That is the real accumulating state, and it is the state in
// which a fabricated season would be easiest to publish by accident.
$GLOBALS['test_history_ready'] = false;
$GLOBALS['test_history_stats'] = array(
	'observations'      => 6,
	'min'               => 463,
	'max'               => 980,
	'avg'               => 700,
	'first_observed_at' => '2026-07-20 06:00:00',
	'last_observed_at'  => '2026-07-24 06:00:00',
	'by_month'          => array( array( 'month' => '2026-10', 'min' => 463, 'observations' => 6 ) ),
);
check( null === tra_vel_v2_cost_answer_history( 'warsaw', 'ils' ), 'Recorded observations must stay invisible until the meaningfulness gate opens.' );
reset_state( $warsaw_records );
$view     = tra_vel_v2_cost_answer_view( 501 );
$contract = tra_vel_v2_cost_answer_contract( 501, $view );
check( ! empty( $contract['ready'] ), 'The page must stay indexable while its history gate is still closed.' );
$html = render_cost_answer();
check( false !== strpos( $html, 'data-tra-vel-page="cost-answer"' ), 'The cost answer template must identify itself for QA.' );
check( false !== strpos( $html, esc_html( $answer ) ), 'The rendered page must open with the same answer sentence the description carries.' );
check( false !== strpos( $html, esc_html( tra_vel_v2_found_price_scope_note() ) ), 'The rendered page must carry the pinned found-price scope note.' );
$options_section = section_html( $html, 'cost-answer-options' );
check( 3 === substr_count( $options_section, '<th scope="row">' ), 'The comparison table must render one row per real option.' );
check( 3 === substr_count( $options_section, 'rel="sponsored nofollow noopener"' ), 'Every comparison row must link out as a sponsored, nofollow, noopener supplier link.' );
foreach ( array( 'Offer', 'Product', 'AggregateOffer', 'itemprop', 'schema.org' ) as $forbidden ) {
	check( false === strpos( $html, $forbidden ), "The rendered cost answer page must never carry {$forbidden}." );
}
foreach ( array( 'addEventListener', 'onclick=', 'wheel', 'touchmove' ) as $forbidden ) {
	check( false === strpos( $html, $forbidden ), "The rendered cost answer page must bind no {$forbidden} behaviour." );
}
check( false === strpos( $html, '—' ) && false === strpos( $html, '–' ), 'The rendered cost answer page must not use em dash or en dash punctuation.' );

// f. Exactly one number in the breakdown, and it is the observed fare.
$breakdown = section_html( $html, 'cost-answer-breakdown' );
check( '' !== $breakdown, 'The cost breakdown section must render.' );
preg_match_all( '#<tr[^>]*>.*?</tr>#s', $breakdown, $breakdown_rows );
$breakdown_rows = isset( $breakdown_rows[0] ) ? $breakdown_rows[0] : array();
check( 6 === count( $breakdown_rows ), 'The cost breakdown must render one header row and five component rows, got ' . count( $breakdown_rows ) . '.' );
$numeric_rows = 0;
foreach ( $breakdown_rows as $row ) {
	preg_match_all( '#<td[^>]*>(.*?)</td>#s', $row, $cells );
	if ( empty( $cells[1] ) ) {
		continue;
	}
	$amount = strip_tags( $cells[1][0] );
	$note   = isset( $cells[1][1] ) ? strip_tags( $cells[1][1] ) : '';
	check( ! preg_match( '/\d/', $note ), 'A cost breakdown explanation must never smuggle a number: ' . $note );
	if ( preg_match( '/\d/', $amount ) ) {
		$numeric_rows++;
		check( '₪463' === trim( $amount ), 'The only number in the cost breakdown must be the observed fare, got: ' . $amount );
	} else {
		check( 'תלוי בבחירה שלכם' === trim( $amount ), 'An unmeasured cost component must read as depending on the traveler choice, got: ' . $amount );
	}
}
check( 1 === $numeric_rows, 'The cost breakdown must print exactly one number, got ' . $numeric_rows . '.' );

// e. No observed history and therefore no table, only honest guidance.
check( false === strpos( $html, 'המחיר הנמוך ביותר שנצפה' ), 'An unproven history must never render an observed minimum table.' );
check( false !== strpos( $html, 'עדיין אין מספיק תצפיות' ), 'An unproven history must say so in words.' );
check( false !== strpos( $html, 'cost-answer-guidance' ), 'An unproven history must still leave honest timing guidance.' );
check( null === $view['history'], 'The view must hold no history while the gate is closed.' );

// e. The gate opens, and only then does the observed table appear.
$GLOBALS['test_history_ready'] = true;
$GLOBALS['test_history_stats'] = array(
	'observations'      => 41,
	'min'               => 412,
	'max'               => 980,
	'avg'               => 610,
	'first_observed_at' => '2026-05-01 06:00:00',
	'last_observed_at'  => '2026-07-24 06:00:00',
	'by_month'          => array(
		array( 'month' => '2026-10', 'min' => 463, 'observations' => 18 ),
		array( 'month' => '2026-11', 'min' => 412, 'observations' => 23 ),
	),
);
reset_state( $warsaw_records );
$history_view = tra_vel_v2_cost_answer_view( 501 );
check( is_array( $history_view['history'] ) && 41 === (int) $history_view['history']['observations'], 'A meaningful history must reach the view with its real observation count.' );
check( 'נובמבר 2026' === $history_view['history']['cheapest']['label'], 'The cheapest observed month must be the real minimum bucket.' );
$history_html = render_cost_answer();
check( false !== strpos( $history_html, 'המחיר הנמוך ביותר שנצפה' ), 'A meaningful history must render the observed minimum table.' );
check( false !== strpos( $history_html, esc_html( 'נובמבר 2026' ) ) && false !== strpos( $history_html, '₪412' ), 'The observed table must print the real monthly minimum.' );
check( false === strpos( $history_html, 'cost-answer-guidance' ), 'The observed table replaces the no-data guidance instead of stacking on it.' );
check( false !== strpos( $history_html, 'אלה תצפיות ולא תחזיות' ), 'The observed table must say it is an observation and not a forecast.' );
$history_faq = tra_vel_v2_cost_answer_faq_items( $history_view );
check( false !== strpos( $history_faq[1]['answer'], 'נובמבר 2026' ) && false !== strpos( $history_faq[1]['answer'], '41' ), 'The timing FAQ must quote the observed month and observation count once the gate opens.' );
$GLOBALS['test_history_ready'] = false;
$GLOBALS['test_history_stats'] = null;

// A non default display currency must never mix a shekel history into the page.
reset_state( $warsaw_records );
$GLOBALS['test_history_ready'] = true;
$GLOBALS['test_history_stats'] = array( 'observations' => 41, 'by_month' => array( array( 'month' => '2026-11', 'min' => 412, 'observations' => 23 ) ) );
check( null === tra_vel_v2_cost_answer_history( 'warsaw', 'usd' ), 'Shekel observations must never be shown under a non default display currency.' );
$GLOBALS['test_history_ready'] = false;
$GLOBALS['test_history_stats'] = null;

// ---------------------------------------------------------------------------
// a. No price: noindex, no schema, and still an honest page.
// ---------------------------------------------------------------------------
reset_state( array() );
$empty_contract = tra_vel_v2_cost_answer_contract( 501 );
check( empty( $empty_contract['ready'] ), 'A destination with no found price must never be indexable.' );
check( false === $empty_contract['checks']['real_price'] && false === $empty_contract['checks']['comparison_rows'], 'The failing checks must name the missing price and the empty comparison table.' );
check( '' === tra_vel_v2_cost_answer_meta_description( 501 ), 'A page with no price must publish no meta description claim.' );
check( '' === tra_vel_v2_cost_answer_public_title( 501 ), 'A page with no price must publish no head-term title claim.' );
check( array() === tra_vel_v2_cost_answer_schema_nodes( 501 ), 'A page with no price must emit no schema.' );
$empty_robots = tra_vel_v2_cost_answer_robots_policy( array( 'index' => true ) );
check( ! empty( $empty_robots['noindex'] ) && ! empty( $empty_robots['follow'] ) && ! isset( $empty_robots['index'] ), 'A page with no price must be noindex, follow under core robots.' );
$empty_yoast = tra_vel_v2_cost_answer_yoast_robots_policy( array( 'index' => 'index', 'follow' => 'nofollow' ) );
check( 'noindex' === $empty_yoast['index'] && 'follow' === $empty_yoast['follow'], 'A page with no price must be noindex, follow under Yoast.' );
$empty_aioseo = tra_vel_v2_cost_answer_aioseo_robots_policy( array( 'index' => 'index', 'nofollow' => 'nofollow', 'max-image-preview' => 'large' ) );
check( 'noindex' === $empty_aioseo['noindex'] && '' === $empty_aioseo['nofollow'] && 'large' === $empty_aioseo['max-image-preview'], 'A page with no price must be noindex under AIOSEO without losing unrelated directives.' );
$empty_html = render_cost_answer();
check( false !== strpos( $empty_html, 'ולא נמציא אחד' ), 'A page with no price must say plainly that it will not invent one.' );
check( false === strpos( $empty_html, '₪' ), 'A page with no price must print no currency amount at all.' );
check( false !== strpos( $empty_html, '/travel-map/' ) && false !== strpos( $empty_html, '/ai-planner/' ), 'A page with no price must still offer two working ways forward.' );
check( false === strpos( $empty_html, 'cost-answer-breakdown' ), 'A page with no price must not render a cost breakdown with no measured component.' );

// An unresolvable slug is the same fail closed state.
$GLOBALS['test_slug'] = 'flight-cost-nowhere';
reset_state( $warsaw_records );
$unresolved = tra_vel_v2_cost_answer_contract( 501 );
check( empty( $unresolved['ready'] ) && false === $unresolved['checks']['destination_resolved'], 'An unresolvable slug must fail closed on the destination check.' );
check( ! empty( tra_vel_v2_cost_answer_robots_policy( array() )['noindex'] ), 'An unresolvable slug must be noindexed.' );
$GLOBALS['test_slug'] = 'flight-cost-warsaw';

// A request that is not this template must never be touched by these hooks.
$GLOBALS['test_template'] = 'page-destination.php';
reset_state( array() );
check( array( 'index' => true ) === tra_vel_v2_cost_answer_robots_policy( array( 'index' => true ) ), 'Cost answer robots must not touch another template.' );
check( $plugin_graph === tra_vel_v2_cost_answer_schema_graph( $plugin_graph ), 'Cost answer schema gating must not touch another template.' );
check( '' === tra_vel_v2_cost_answer_public_title(), 'Cost answer titles must not leak onto another template.' );
$GLOBALS['test_template'] = 'page-cost-answer.php';

if ( $test_failures ) {
	fwrite( STDERR, "Tra-Vel cost answer runtime validation failed:\n" );
	foreach ( $test_failures as $failure ) {
		fwrite( STDERR, "- {$failure}\n" );
	}
	exit( 1 );
}

echo "Tra-Vel cost answer runtime validation passed ({$test_checks} checks).\n";
