<?php
/**
 * Runtime validation for the destination-guide indexing and schema boundary.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_PATH', __DIR__ . '/../../theme/tra-vel-v2' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'YEAR_IN_SECONDS', 31536000 );

class WP_Post {}
class Test_Yoast_Article_Piece {}
class Test_Yoast_Website_Piece {}
class_alias( 'Test_Yoast_Article_Piece', 'Yoast\\WP\\SEO\\Generators\\Schema\\Article' );

$GLOBALS['guide_test_post_id'] = 73;
$GLOBALS['guide_test_is_singular'] = true;
$GLOBALS['guide_test_content'] = '';
$GLOBALS['guide_test_meta']    = array();
$GLOBALS['guide_test_template'] = 'page-destination.php';
$GLOBALS['guide_test_post_name'] = 'athens';
$GLOBALS['guide_test_ancestors'] = array( 71 );
$GLOBALS['guide_test_titles'] = array(
	71 => 'Destinations',
	72 => 'Thailand',
	73 => 'Athens',
	74 => 'Bangkok',
);
$GLOBALS['guide_test_permalinks'] = array(
	71 => 'https://example.test/destinations/',
	72 => 'https://example.test/destinations/thailand/',
	73 => 'https://example.test/destinations/athens/',
	74 => 'https://example.test/destinations/thailand/bangkok/',
);

function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return sanitize_text_field( $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function esc_url_raw( $value, $protocols = null ) { return 0 === strpos( (string) $value, 'https://' ) ? (string) $value : ''; }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function __( $value ) { return $value; }
function add_filter() {}
function add_action() {}
function get_queried_object_id() { return $GLOBALS['guide_test_post_id']; }
function get_post_meta( $post_id, $key, $single = false ) { return $GLOBALS['guide_test_meta'][ $key ] ?? ''; }
function get_post_field( $field, $post_id = null ) {
	if ( 'post_content' === $field ) return $GLOBALS['guide_test_content'];
	if ( 'post_author' === $field ) return 1;
	if ( 'post_name' === $field ) return $GLOBALS['guide_test_post_name'];
	return '';
}
function get_post_type() { return 'page'; }
function get_page_template_slug() { return $GLOBALS['guide_test_template']; }
function is_page_template( $template ) { return $GLOBALS['guide_test_template'] === $template; }
function is_page() { return false; }
function is_singular() { return $GLOBALS['guide_test_is_singular']; }
function is_admin() { return false; }
function is_feed() { return false; }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function get_post_ancestors( $post_id ) { return $GLOBALS['guide_test_ancestors']; }
function get_permalink( $post_id = null ) {
	$post_id = $post_id ?: $GLOBALS['guide_test_post_id'];
	return $GLOBALS['guide_test_permalinks'][ $post_id ] ?? '';
}
function get_bloginfo() { return 'Tra-Vel'; }
function get_the_title( $post_id = null ) {
	$post_id = $post_id ?: $GLOBALS['guide_test_post_id'];
	return $GLOBALS['guide_test_titles'][ $post_id ] ?? '';
}
function get_the_author_meta() { return 'Tra-Vel'; }
function get_the_post_thumbnail_url() { return ''; }
function get_the_excerpt() { return 'מדריך מלא לתכנון החופשה באתונה.'; }
function wp_trim_words( $value ) { return $value; }
function get_the_date() { return '2026-07-01T08:00:00+00:00'; }
function get_the_modified_date() { return '2026-07-18T08:00:00+00:00'; }

require_once __DIR__ . '/../../theme/tra-vel-v2/inc/guides.php';
require_once __DIR__ . '/../../theme/tra-vel-v2/inc/guide-html.php';
require_once __DIR__ . '/../../theme/tra-vel-v2/inc/seo.php';

function guide_publication_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Guide publication runtime validation failed: {$message}\n" );
		exit( 1 );
	}
}

function guide_publication_types( $graph ) {
	$types = array();
	foreach ( $graph['@graph'] as $node ) {
		foreach ( (array) ( $node['@type'] ?? array() ) as $type ) $types[] = $type;
	}
	return $types;
}

function guide_publication_node( $graph, $type ) {
	foreach ( $graph['@graph'] as $node ) {
		if ( in_array( $type, (array) ( $node['@type'] ?? array() ), true ) ) return $node;
	}
	return array();
}

$GLOBALS['guide_test_is_singular'] = false;
guide_publication_assert( false === tra_vel_v2_is_destination_guide(), 'an archive queried-object ID collision must not be treated as a guide' );
guide_publication_assert( true === tra_vel_v2_is_destination_guide( 73 ), 'explicit guide IDs must remain reusable outside singular requests' );
guide_publication_assert( array( 'index' => true ) === tra_vel_v2_robots_policy( array( 'index' => true ) ), 'an archive queried-object ID collision changed robots' );
$GLOBALS['guide_test_is_singular'] = true;

$html_parser_fixture = '<!-- <h2 id="comment-id"></h2> --><h2 title=\'1 > 0\' ID=\'bangkok-fit\'>Fit</h2><a title="1 > 0" href="/not-public/"></a>';
$html_parser_tags    = tra_vel_v2_tokenize_guide_html_tags( $html_parser_fixture );
$html_parser_ids     = tra_vel_v2_extract_guide_content_ids( $html_parser_fixture );
guide_publication_assert( 2 === count( $html_parser_tags ) && false !== strpos( $html_parser_tags[1], 'href="/not-public/"' ), 'guide HTML tokenizer must not end a tag at a greater-than sign inside a quoted title attribute' );
guide_publication_assert( array( 'bangkok-fit' ) === array_keys( $html_parser_ids ), 'guide runtime anchors must use the same comment, case, and quoted-greater-than semantics as CI' );

function set_complete_guide_fixture() {
	$today = gmdate( 'Y-m-d' );
	$sources = array();
	for ( $index = 1; $index <= 10; $index++ ) {
		$sources[] = array(
			'id'        => 'source-' . $index,
			'title'     => 'Official source ' . $index,
			'url'       => 'https://example.org/source-' . $index,
			'checkedAt' => $today,
		);
	}
	$structure = str_repeat( '<h2>כותרת</h2>', 12 ) . str_repeat( '<table><tr><td>החלטה</td></tr></table>', 3 );
	$GLOBALS['guide_test_content'] = $structure . str_repeat( 'מילה ', 5000 );
	$GLOBALS['guide_test_meta'] = array(
		'_tra_vel_primary_topic'  => 'Athens travel guide for Israelis',
		'_tra_vel_source_checked' => $today,
		'_tra_vel_author'         => 'מערכת Tra-Vel',
		'_tra_vel_reviewer'       => 'עורך נסיעות',
		'_tra_vel_review_method'  => 'בדיקת מקורות רשמיים והשוואה לפני פרסום.',
		'_tra_vel_map_state'      => 'athens',
		'_tra_vel_sources_json'   => json_encode( $sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		'_tra_vel_publication_status' => 'publish-ready',
	);
}

$source_capacity_fixture = array();
for ( $index = 1; $index <= 81; $index++ ) {
	$source_capacity_fixture[] = array(
		'id'        => 'capacity-source-' . $index,
		'title'     => 'Capacity source ' . $index,
		'url'       => 'https://example.org/capacity-source-' . $index,
		'checkedAt' => '2026-07-18',
	);
}
$sanitized_capacity_fixture = json_decode( tra_vel_v2_sanitize_sources_json( wp_json_encode( $source_capacity_fixture ) ), true );
guide_publication_assert( 80 === count( $sanitized_capacity_fixture ), 'guide source storage must preserve up to 80 validated sources without silently truncating source-rich packets' );

$meta_fields = tra_vel_v2_guide_meta_fields();
guide_publication_assert( isset( $meta_fields['_tra_vel_publication_status'] ), 'publication status must be registered as guide metadata' );
guide_publication_assert( 'editorial-review' === tra_vel_v2_sanitize_publication_status( 'editorial-review' ) && '' === tra_vel_v2_sanitize_publication_status( 'unknown' ), 'publication status sanitizer must accept only supported workflow states' );

set_complete_guide_fixture();
$contract = tra_vel_v2_get_guide_publication_contract();
guide_publication_assert( true === $contract['ready'], 'a 5,000-word guide with the full evidence contract must pass' );
guide_publication_assert( true === ( $contract['checks']['source_record_freshness'] ?? false ) && true === ( $contract['checks']['source_date_alignment'] ?? false ), 'fresh source records must pass freshness and aggregate-review alignment' );
$stale_sources = json_decode( $GLOBALS['guide_test_meta']['_tra_vel_sources_json'], true );
$stale_sources[0]['checkedAt'] = gmdate( 'Y-m-d', time() - ( 2 * YEAR_IN_SECONDS ) );
$GLOBALS['guide_test_meta']['_tra_vel_sources_json'] = json_encode( $stale_sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
$stale_source_contract = tra_vel_v2_get_guide_publication_contract();
guide_publication_assert( false === $stale_source_contract['ready'] && false === ( $stale_source_contract['checks']['source_record_freshness'] ?? true ), 'a stale individual source record must close the guide publication gate' );
set_complete_guide_fixture();
$GLOBALS['guide_test_meta']['_tra_vel_source_checked'] = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
$misaligned_source_contract = tra_vel_v2_get_guide_publication_contract();
guide_publication_assert( false === $misaligned_source_contract['ready'] && false === ( $misaligned_source_contract['checks']['source_date_alignment'] ?? true ), 'a source checked after the aggregate review date must close the guide publication gate' );
set_complete_guide_fixture();
$contract = tra_vel_v2_get_guide_publication_contract();
guide_publication_assert( 'publish-ready' === tra_vel_v2_get_guide_profile()['publication_status'], 'complete guide fixture must carry explicit publish-ready evidence' );
unset( $GLOBALS['guide_test_meta']['_tra_vel_publication_status'] );
guide_publication_assert( '' === tra_vel_v2_get_guide_profile()['publication_status'], 'legacy guides without publication status must retain a readable profile' );
guide_publication_assert( false === tra_vel_v2_is_guide_publication_ready(), 'legacy guide without explicit publish-ready evidence became indexable' );
$legacy_robots = tra_vel_v2_robots_policy( array( 'index' => true, 'follow' => true ) );
guide_publication_assert( true === ( $legacy_robots['noindex'] ?? false ) && true === ( $legacy_robots['follow'] ?? false ), 'legacy guide without publication status did not become noindex, follow' );
guide_publication_assert( ! in_array( 'Article', guide_publication_types( tra_vel_v2_schema_data() ), true ), 'legacy guide without publication status emitted Article schema' );
$GLOBALS['guide_test_meta']['_tra_vel_publication_status'] = 'editorial-review';
guide_publication_assert( 'editorial-review' === tra_vel_v2_get_guide_profile()['publication_status'], 'publication status must be exposed in the guide profile' );
guide_publication_assert( false === tra_vel_v2_is_guide_publication_ready(), 'editorial-review guide became indexable' );
$editorial_review_robots = tra_vel_v2_robots_policy( array( 'index' => true, 'follow' => true ) );
guide_publication_assert( true === ( $editorial_review_robots['noindex'] ?? false ) && true === ( $editorial_review_robots['follow'] ?? false ), 'editorial-review guide did not become noindex, follow' );
guide_publication_assert( ! in_array( 'Article', guide_publication_types( tra_vel_v2_schema_data() ), true ), 'editorial-review guide emitted Article schema' );
$GLOBALS['guide_test_meta']['_tra_vel_publication_status'] = 'publish-ready';
guide_publication_assert( true === tra_vel_v2_is_guide_publication_ready(), 'explicit publish-ready guide failed the complete runtime contract' );
guide_publication_assert( $contract['word_count'] >= 5000, 'the long-form word gate must count visible words deterministically' );
$robots = tra_vel_v2_robots_policy( array( 'index' => true, 'follow' => true ) );
guide_publication_assert( empty( $robots['noindex'] ), 'a complete guide must remain indexable' );
$_GET   = array( 'route' => 'TLV-ATH', 'dates' => 'flexible' );
$robots = tra_vel_v2_robots_policy( array( 'index' => true, 'follow' => true ) );
guide_publication_assert( true === ( $robots['noindex'] ?? false ) && true === ( $robots['follow'] ?? false ) && ! isset( $robots['index'] ), 'legacy route and date query variants must be noindex, follow' );
foreach ( array( 'departure', 'check_in', 'check_out', 'date', 'travelers', 'party', 'flexible', 'flexibility', 'hotel_area', 'transfers', 'kosher', 'accessibility', 'vibe' ) as $intent_key ) {
	$_GET   = array( $intent_key => 'fixture' );
	$robots = tra_vel_v2_robots_policy( array( 'index' => true, 'follow' => true ) );
	guide_publication_assert( true === ( $robots['noindex'] ?? false ) && true === ( $robots['follow'] ?? false ) && ! isset( $robots['index'] ), "known traveler intent query {$intent_key} must be noindex, follow" );
}
$_GET   = array();
$types = guide_publication_types( tra_vel_v2_schema_data() );
guide_publication_assert( in_array( 'Article', $types, true ), 'a complete guide must emit Article schema when the theme owns the graph' );

$top_level_breadcrumbs = tra_vel_v2_guide_breadcrumb_items();
guide_publication_assert( 3 === count( $top_level_breadcrumbs ), 'a top-level destination guide breadcrumb must remain Home, Destinations, guide' );
guide_publication_assert( 'https://example.test/destinations/' === $top_level_breadcrumbs[1]['url'] && 'Athens' === $top_level_breadcrumbs[2]['name'], 'top-level guide breadcrumbs must use the real destination ancestor and current page' );
guide_publication_assert( tra_vel_v2_is_public_guide_path( '/destinations/athens/' ), 'top-level destination paths must remain public guide paths' );
guide_publication_assert( tra_vel_v2_is_public_guide_path( '/destinations/thailand/bangkok/' ), 'one nested supporting-guide segment must be public' );
guide_publication_assert( ! tra_vel_v2_is_public_guide_path( '/destinations/thailand/bangkok/food/' ), 'guide paths deeper than one supporting segment must fail closed' );

$GLOBALS['guide_test_post_id']   = 74;
$GLOBALS['guide_test_post_name'] = 'bangkok';
$GLOBALS['guide_test_ancestors'] = array( 72, 71 );
$nested_breadcrumbs = tra_vel_v2_guide_breadcrumb_items();
guide_publication_assert( 4 === count( $nested_breadcrumbs ), 'a supporting guide breadcrumb must expose the destination hub ancestor' );
guide_publication_assert( 'Destinations' === $nested_breadcrumbs[1]['name'] && 'Thailand' === $nested_breadcrumbs[2]['name'] && 'Bangkok' === $nested_breadcrumbs[3]['name'], 'nested visible breadcrumbs must preserve the real hierarchy' );
$nested_schema = tra_vel_v2_schema_data();
$nested_schema_breadcrumb = guide_publication_node( $nested_schema, 'BreadcrumbList' );
$nested_schema_items = $nested_schema_breadcrumb['itemListElement'] ?? array();
guide_publication_assert( 4 === count( $nested_schema_items ), 'nested breadcrumb JSON-LD must contain the same four hierarchy levels' );
guide_publication_assert( array( 1, 2, 3, 4 ) === array_column( $nested_schema_items, 'position' ), 'nested breadcrumb JSON-LD positions must be deterministic' );
guide_publication_assert( 'https://example.test/destinations/thailand/' === ( $nested_schema_items[2]['item'] ?? '' ) && 'https://example.test/destinations/thailand/bangkok/' === ( $nested_schema_items[3]['item'] ?? '' ), 'nested breadcrumb JSON-LD must preserve canonical ancestor and child URLs' );
$GLOBALS['guide_test_post_id']   = 73;
$GLOBALS['guide_test_post_name'] = 'athens';
$GLOBALS['guide_test_ancestors'] = array( 71 );

$directory = tra_vel_v2_directory_item_list();
$published = $directory['itemListElement'][0]['item'] ?? array();
$research  = $directory['itemListElement'][2]['item'] ?? array();
guide_publication_assert( ! empty( $published['url'] ), 'a published directory item must expose its canonical guide URL' );
guide_publication_assert( empty( $research['url'] ), 'a research-only directory item must not expose a guide URL' );
$GLOBALS['guide_test_template']  = 'page-directory.php';
$GLOBALS['guide_test_post_name'] = 'guides';
$guide_directory = tra_vel_v2_directory_item_list();
guide_publication_assert( 4 === $guide_directory['numberOfItems'], 'the guide index must contain published guides only' );
foreach ( $guide_directory['itemListElement'] as $entry ) {
	guide_publication_assert( ! empty( $entry['item']['url'] ), 'every item in the guide index schema must have a canonical guide URL' );
}
$GLOBALS['guide_test_template']  = 'page-destination.php';
$GLOBALS['guide_test_post_name'] = 'athens';

$GLOBALS['guide_test_content'] = str_repeat( '<h2>כותרת</h2>', 12 ) . str_repeat( '<table><tr><td>החלטה</td></tr></table>', 3 ) . str_repeat( 'מילה ', 250 );
$contract = tra_vel_v2_get_guide_publication_contract();
guide_publication_assert( false === $contract['ready'], 'a thin destination template must fail closed' );
$robots = tra_vel_v2_robots_policy( array( 'index' => true ) );
guide_publication_assert( true === ( $robots['noindex'] ?? false ) && true === ( $robots['follow'] ?? false ) && ! isset( $robots['index'] ), 'an incomplete guide must be noindex, follow' );
$aioseo_robots = tra_vel_v2_aioseo_robots_policy( array( 'index' => 'index', 'nofollow' => 'nofollow', 'max-image-preview' => 'large' ) );
guide_publication_assert( 'noindex' === ( $aioseo_robots['noindex'] ?? '' ) && '' === ( $aioseo_robots['nofollow'] ?? null ) && 'large' === ( $aioseo_robots['max-image-preview'] ?? '' ), 'AIOSEO must preserve the global incomplete-guide noindex/follow policy' );
$yoast_robots = tra_vel_v2_yoast_robots_policy( array( 'index' => 'index', 'follow' => 'nofollow' ) );
guide_publication_assert( 'noindex' === ( $yoast_robots['index'] ?? '' ) && 'follow' === ( $yoast_robots['follow'] ?? '' ), 'Yoast must preserve the global incomplete-guide noindex/follow policy' );
$types = guide_publication_types( tra_vel_v2_schema_data() );
guide_publication_assert( ! in_array( 'Article', $types, true ) && in_array( 'WebPage', $types, true ), 'an incomplete guide must keep WebPage schema without an Article claim' );

$pieces = tra_vel_v2_gate_yoast_article_piece( array( new Test_Yoast_Website_Piece(), new Test_Yoast_Article_Piece() ) );
guide_publication_assert( 1 === count( $pieces ) && $pieces[0] instanceof Test_Yoast_Website_Piece, 'Yoast Article pieces must be removed from incomplete guides' );
$aioseo = tra_vel_v2_gate_aioseo_guide_schema(
	array(
		array( '@type' => 'WebPage', 'about' => array( '@id' => '#article' ), 'mainEntity' => array( '@id' => '#article' ) ),
		array( '@type' => 'Article', 'headline' => 'Thin page' ),
	)
);
guide_publication_assert( 1 === count( $aioseo ) && ! isset( $aioseo[0]['about'], $aioseo[0]['mainEntity'] ), 'AIOSEO must remove incomplete Article nodes and dangling WebPage references' );

set_complete_guide_fixture();
$aioseo = tra_vel_v2_gate_aioseo_guide_schema( array( array( '@type' => 'Article', 'headline' => 'Complete guide' ) ) );
guide_publication_assert( 'Athens' === ( $aioseo[0]['about']['name'] ?? '' ) && 10 === count( $aioseo[0]['citation'] ?? array() ), 'a complete AIOSEO Article must use the destination entity and evidence citations' );

$GLOBALS['guide_test_meta']['_tra_vel_review_method'] = '';
guide_publication_assert( false === tra_vel_v2_is_guide_publication_ready(), 'missing review methodology must close the gate even when the article is long' );

echo "Tra-Vel guide publication runtime validation passed.\n";
