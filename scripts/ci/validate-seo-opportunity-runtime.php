<?php
/** Runtime contract tests for registry-owned SEO opportunity pages. */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );
define( 'OBJECT', 'OBJECT' );

class WP_Post {
	public $ID;
	public function __construct( $id ) {
		$this->ID = (int) $id;
	}
}
class Test_WP_Query {
	public $is_404 = false;
	public function set_404() { $this->is_404 = true; }
}

$test_registry_path = '';
$test_current_id = 101;
$test_is_page_request = true;
$test_posts = array();
$test_pages_by_path = array();
$test_profiles = array();
$test_guide_contracts = array();
$test_registered_meta = array();
$test_status_header = 200;
$wp_query = new Test_WP_Query();

function add_action() {}
function add_filter() {}
function add_post_type_support() {}
function register_post_meta( $post_type, $meta_key, $args ) { global $test_registered_meta; $test_registered_meta[ $meta_key ] = $args; return true; }
function current_user_can() { return true; }
function is_admin() { return false; }
function status_header( $status ) { global $test_status_header; $test_status_header = (int) $status; }
function nocache_headers() {}
function apply_filters( $tag, $value ) {
	global $test_registry_path;
	return 'tra_vel_v2_seo_opportunity_registry_path' === $tag ? $test_registry_path : $value;
}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function rest_sanitize_boolean( $value ) { return filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $path = '/' ) { return 'https://example.test' . ( '/' === $path ? '/' : '/' . ltrim( $path, '/' ) ); }
function get_queried_object_id() { global $test_current_id; return $test_current_id; }
function is_singular( $post_type = '' ) { global $test_is_page_request; return $test_is_page_request && ( '' === $post_type || 'page' === $post_type ); }
function get_page_template_slug( $post_id ) { global $test_posts; $id = $post_id instanceof WP_Post ? $post_id->ID : (int) $post_id; return $test_posts[ $id ]['template'] ?? ''; }
function get_permalink( $post_id ) { global $test_posts; $id = $post_id instanceof WP_Post ? $post_id->ID : (int) $post_id; return $test_posts[ $id ]['permalink'] ?? ''; }
function get_post_status( $post_id ) { global $test_posts; $id = $post_id instanceof WP_Post ? $post_id->ID : (int) $post_id; return $test_posts[ $id ]['status'] ?? ''; }
function get_post_field( $field, $post_id ) { global $test_posts; $id = $post_id instanceof WP_Post ? $post_id->ID : (int) $post_id; return $test_posts[ $id ][ $field ] ?? ''; }
function get_post_meta( $post_id, $key, $single = true ) { global $test_posts; return $test_posts[ (int) $post_id ]['meta'][ $key ] ?? ''; }
function get_page_by_path( $path, $output = OBJECT, $post_type = 'page' ) { global $test_pages_by_path; return isset( $test_pages_by_path[ trim( $path, '/' ) ] ) ? new WP_Post( $test_pages_by_path[ trim( $path, '/' ) ] ) : null; }
function get_posts( $args = array() ) {
	global $test_posts;
	$ids = array();
	foreach ( $test_posts as $id => $post ) {
		if ( 'publish' !== ( $post['status'] ?? '' ) ) continue;
		if ( ! empty( $post['meta']['_tra_vel_seo_opportunity_id'] ) || 'page-seo-opportunity.php' === ( $post['template'] ?? '' ) ) $ids[] = (int) $id;
	}
	return $ids;
}
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_trim_words( $value, $limit ) { return implode( ' ', array_slice( preg_split( '/\s+/', trim( (string) $value ) ), 0, $limit ) ); }
function get_the_excerpt( $post_id ) { global $test_posts; return $test_posts[ (int) $post_id ]['excerpt'] ?? ''; }
function get_the_author_meta() { return 'Tra-Vel'; }
function get_the_date() { return '2026-07-01T00:00:00+00:00'; }
function get_the_modified_date() { return '2026-07-18T00:00:00+00:00'; }
function esc_url_raw( $url ) { return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : ''; }
function __( $text ) { return $text; }
function is_page_template( $template ) { return $template === get_page_template_slug( get_queried_object_id() ); }
function add_query_arg( $key, $value = null, $url = null ) {
	if ( is_array( $key ) ) { $query = $key; $target = (string) $value; }
	else { $query = array( $key => $value ); $target = (string) $url; }
	$query = array_filter( $query, static function ( $item ) { return '' !== $item && null !== $item; } );
	return $target . ( false === strpos( $target, '?' ) ? '?' : '&' ) . http_build_query( $query );
}
function tra_vel_v2_get_guide_profile( $post_id = 0 ) { global $test_profiles; return $test_profiles[ (int) $post_id ] ?? array(); }
function tra_vel_v2_get_guide_publication_contract( $post_id = 0 ) { global $test_guide_contracts; return $test_guide_contracts[ (int) $post_id ] ?? array( 'ready' => false, 'checks' => array() ); }

require dirname( __DIR__, 2 ) . '/theme/tra-vel-v2/inc/seo-opportunities.php';
tra_vel_v2_register_seo_opportunity_meta();

function assert_true( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "SEO opportunity runtime validation failed: {$message}\n" );
		exit( 1 );
	}
}

function fixture_entry( $id, $path, $type, $intent, $cluster, $parent, $map, $status, $action, $products ) {
	return array(
		'id' => $id, 'canonicalPath' => $path, 'pageType' => $type, 'primaryIntent' => $intent,
		'cluster' => $cluster, 'parentPath' => $parent, 'mapState' => $map, 'status' => $status,
		'conversionAction' => $action, 'monetization' => $products,
	);
}

function registered_meta_for_context( $context ) {
	global $test_registered_meta;
	return array_filter(
		$test_registered_meta,
		static function ( $args ) use ( $context ) {
			return in_array( $context, $args['show_in_rest']['schema']['context'] ?? array( 'view', 'edit' ), true );
		}
	);
}

$fixture = array(
	'schemaVersion' => 1,
	'locale' => 'he-IL',
	'entries' => array(
		fixture_entry( 'destinations-hub', '/destinations/', 'audience-hub', 'יעדים ומדריכי נסיעות', 'destinations', '/', null, 'live', 'בחרו יעד והתחילו לתכנן את החופשה', array( 'packages' ) ),
		fixture_entry( 'tokyo-guide', '/destinations/tokyo/', 'destination-hub', 'מדריך טוקיו לישראלים', 'tokyo', '/destinations/', 'tokyo', 'content-ready', 'תכננו חופשה מלאה בטוקיו על המפה', array( 'flights', 'hotels' ) ),
		fixture_entry( 'flights-hub', '/flights/', 'commercial-hub', 'טיסות לחוץ לארץ', 'flights', '/', null, 'live', 'השוו טיסות לפי המחיר הכולל והתנאים', array( 'flights' ) ),
		fixture_entry( 'packages-hub', '/packages/', 'commercial-hub', 'חבילות נופש לחוץ לארץ', 'packages', '/', null, 'live', 'השוו חבילות לפי כל מרכיבי החופשה', array( 'packages' ) ),
		fixture_entry( 'tokyo-airports', '/guides/tokyo/haneda-vs-narita/', 'decision-guide', 'האנדה או נריטה איזה שדה מתאים', 'tokyo', '/destinations/tokyo/', 'tokyo', 'content-ready', 'בחרו שדה תעופה והמשיכו לתכנון', array( 'transfers', 'rail', 'hotels' ) ),
		fixture_entry( 'tokyo-flights', '/flights/tokyo/', 'transactional-cluster', 'טיסות לטוקיו והשוואת מסלולים', 'tokyo', '/flights/', 'tokyo', 'live', 'השוו טיסות לטוקיו לפי כל התנאים', array( 'flights', 'baggage' ) ),
		fixture_entry( 'tokyo-packages', '/packages/tokyo/', 'transactional-cluster', 'חבילות נופש לטוקיו בהתאמה אישית', 'tokyo', '/packages/', 'tokyo', 'backlog', 'בנו חבילה לטוקיו לפי ההרכב שלכם', array( 'packages', 'flights' ) ),
	),
);
$test_registry_path = tempnam( sys_get_temp_dir(), 'travel-seo-registry-' );
file_put_contents( $test_registry_path, json_encode( $fixture, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

$hebrew_word = 'מילה ';
$decision_content = str_repeat( '<h2>כותרת</h2>', 12 ) . str_repeat( '<table><tr><td>מידע</td></tr></table>', 3 ) . str_repeat( $hebrew_word, 5000 );
$transaction_content = str_repeat( '<h2>כותרת</h2>', 4 ) . str_repeat( $hebrew_word, 800 );
$sources = array();
for ( $index = 1; $index <= 10; $index++ ) {
	$sources[] = array( 'title' => "Source {$index}", 'url' => "https://example.com/source-{$index}", 'checkedAt' => '2026-07-01' );
}
$ready_profile = array(
	'primary_topic' => 'טוקיו', 'author' => 'Tra-Vel', 'reviewer' => 'Reviewer', 'method' => 'Method',
	'checked' => '2026-07-01', 'publication_status' => 'publish-ready', 'map_state' => 'tokyo',
	'sources' => $sources, 'is_reviewed' => true,
);
$ready_guide_contract = array(
	'ready' => true,
	'checks' => array( 'long_form_content' => true, 'hebrew_language' => true, 'section_depth' => true, 'decision_tables' => true, 'primary_topic' => true, 'source_freshness' => true ),
);

$test_posts = array(
	101 => array( 'template' => 'page-seo-opportunity.php', 'permalink' => 'https://example.test/guides/tokyo/haneda-vs-narita/', 'status' => 'publish', 'post_content' => $decision_content, 'post_author' => 1, 'excerpt' => 'מדריך החלטה', 'meta' => array( '_tra_vel_seo_opportunity_id' => 'tokyo-airports', '_tra_vel_seo_opportunity_ready' => true, '_tra_vel_seo_conversion_ready' => false ) ),
	102 => array( 'template' => 'page-seo-opportunity.php', 'permalink' => 'https://example.test/flights/tokyo/', 'status' => 'publish', 'post_content' => $transaction_content, 'post_author' => 1, 'excerpt' => 'השוואת טיסות', 'meta' => array( '_tra_vel_seo_opportunity_id' => 'tokyo-flights', '_tra_vel_seo_opportunity_ready' => true, '_tra_vel_seo_conversion_ready' => true ) ),
	103 => array( 'template' => 'page-seo-opportunity.php', 'permalink' => 'https://example.test/packages/tokyo/', 'status' => 'publish', 'post_content' => $transaction_content, 'post_author' => 1, 'excerpt' => 'חבילות טוקיו', 'meta' => array( '_tra_vel_seo_opportunity_id' => 'tokyo-packages', '_tra_vel_seo_opportunity_ready' => true, '_tra_vel_seo_conversion_ready' => true ) ),
	201 => array( 'template' => 'page-destination.php', 'permalink' => 'https://example.test/destinations/tokyo/', 'status' => 'publish', 'post_content' => $decision_content, 'post_author' => 1, 'excerpt' => 'טוקיו', 'meta' => array() ),
	301 => array( 'template' => 'page-experience.php', 'permalink' => 'https://example.test/flights/', 'status' => 'publish', 'post_content' => '', 'post_author' => 1, 'excerpt' => '', 'meta' => array() ),
);
$test_pages_by_path = array(
	'destinations/tokyo' => 201,
	'flights' => 301,
	'guides/tokyo/haneda-vs-narita' => 101,
	'flights/tokyo' => 102,
	'packages/tokyo' => 103,
);
$test_profiles = array( 101 => $ready_profile, 201 => $ready_profile );
$test_guide_contracts = array( 101 => $ready_guide_contract, 201 => $ready_guide_contract );

$registry = tra_vel_v2_load_seo_opportunity_registry( $test_registry_path );
assert_true( $registry['valid'], 'synthetic registry was rejected: ' . $registry['error'] );
$invalid_parent_fixture = $fixture;
$invalid_parent_fixture['entries'][2]['pageType'] = 'planning-tool';
$invalid_parent_path = tempnam( sys_get_temp_dir(), 'travel-seo-invalid-parent-' );
file_put_contents( $invalid_parent_path, json_encode( $invalid_parent_fixture, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
$invalid_parent_registry = tra_vel_v2_load_seo_opportunity_registry( $invalid_parent_path );
assert_true( ! $invalid_parent_registry['valid'] && 'registry_transaction_parent_invalid' === $invalid_parent_registry['error'], 'transaction owner accepted a non-commercial registry parent' );
$invalid_public_map_fixture = $fixture;
$invalid_public_map_fixture['entries'][5]['mapState'] = null;
$invalid_public_map_path = tempnam( sys_get_temp_dir(), 'travel-seo-invalid-map-' );
file_put_contents( $invalid_public_map_path, json_encode( $invalid_public_map_fixture, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
$invalid_public_map_registry = tra_vel_v2_load_seo_opportunity_registry( $invalid_public_map_path );
assert_true( ! $invalid_public_map_registry['valid'] && 'registry_entry_invalid' === $invalid_public_map_registry['error'], 'public opportunity owner without a map state passed' );
assert_true( 0 === count( registered_meta_for_context( 'view' ) ), 'internal readiness meta leaked into public REST view context' );
assert_true( 3 === count( registered_meta_for_context( 'edit' ) ), 'authenticated edit context cannot read/write all readiness meta' );
assert_true( 'string' === $test_registered_meta['_tra_vel_seo_opportunity_id']['show_in_rest']['schema']['type'], 'owner REST meta has the wrong type' );
assert_true( 'boolean' === $test_registered_meta['_tra_vel_seo_opportunity_ready']['show_in_rest']['schema']['type'], 'readiness REST meta has the wrong type' );
assert_true( true === $test_registered_meta['_tra_vel_seo_opportunity_ready']['auth_callback']( false, '_tra_vel_seo_opportunity_ready', 101 ), 'authenticated edit authorization failed' );
assert_true( null === tra_vel_v2_get_seo_opportunity_by_path( '/guides/tokyo//haneda-vs-narita/', $test_registry_path ), 'non-exact path matched an owner' );
$backlog = tra_vel_v2_get_seo_opportunity_by_path( '/packages/tokyo/', $test_registry_path );
assert_true( ! tra_vel_v2_is_exposable_seo_opportunity( $backlog ), 'backlog owner became exposable' );

$public_cluster_links = tra_vel_v2_get_public_seo_opportunity_links( 'tokyo' );
assert_true( array( 'tokyo-airports', 'tokyo-flights' ) === array_column( $public_cluster_links, 'id' ), 'public cluster links do not preserve ready registry order' );
assert_true( array( 'tokyo-airports' ) === array_column( tra_vel_v2_get_public_seo_opportunity_links( 'tokyo', '', 1 ), 'id' ), 'public cluster link limit is not deterministic' );
assert_true( array( 'tokyo-flights' ) === array_column( tra_vel_v2_get_public_seo_opportunity_links( 'tokyo', 'tokyo-airports' ), 'id' ), 'current owner exclusion did not remove the decision page' );
assert_true( ! in_array( 'tokyo-packages', array_column( $public_cluster_links, 'id' ), true ), 'a fully page-shaped backlog owner entered the public internal-link graph' );
foreach ( $public_cluster_links as $public_cluster_link ) {
	assert_true( array() === array_diff( array_keys( $public_cluster_link ), array( 'id', 'url', 'title', 'kind', 'cta' ) ), 'public cluster link leaked internal publication or monetization fields' );
}

$test_profiles[101]['publication_status'] = 'editorial-review';
assert_true( array( 'tokyo-flights' ) === array_column( tra_vel_v2_get_public_seo_opportunity_links( 'tokyo' ), 'id' ), 'editorial-review decision page remained publicly linked' );
$test_profiles[101] = $ready_profile;
$test_posts[102]['meta']['_tra_vel_seo_opportunity_ready'] = false;
assert_true( array( 'tokyo-airports' ) === array_column( tra_vel_v2_get_public_seo_opportunity_links( 'tokyo' ), 'id' ), 'transaction without explicit readiness remained publicly linked' );
$test_posts[102]['meta']['_tra_vel_seo_opportunity_ready'] = true;
$test_posts[101]['template'] = 'default';
assert_true( array( 'tokyo-flights' ) === array_column( tra_vel_v2_get_public_seo_opportunity_links( 'tokyo' ), 'id' ), 'wrong-template decision page remained publicly linked' );
$test_posts[101]['template'] = 'page-seo-opportunity.php';
$test_posts[101]['meta']['_tra_vel_seo_opportunity_id'] = 'wrong-owner';
assert_true( array( 'tokyo-flights' ) === array_column( tra_vel_v2_get_public_seo_opportunity_links( 'tokyo' ), 'id' ), 'wrong-owner decision page remained publicly linked' );
$test_posts[101]['meta']['_tra_vel_seo_opportunity_id'] = 'tokyo-airports';
$test_posts[101]['permalink'] = 'https://example.test/guides/tokyo/haneda-vs-narita-drifted/';
assert_true( array( 'tokyo-flights' ) === array_column( tra_vel_v2_get_public_seo_opportunity_links( 'tokyo' ), 'id' ), 'canonical-drifted decision page remained publicly linked' );
$test_posts[101]['permalink'] = 'https://example.test/guides/tokyo/haneda-vs-narita/';
unset( $test_pages_by_path['guides/tokyo/haneda-vs-narita'] );
assert_true( array( 'tokyo-flights' ) === array_column( tra_vel_v2_get_public_seo_opportunity_links( 'tokyo' ), 'id' ), 'missing decision page remained publicly linked' );
$test_pages_by_path['guides/tokyo/haneda-vs-narita'] = 101;
assert_true( array() === tra_vel_v2_get_public_seo_opportunity_links( 'unknown-cluster' ), 'unknown cluster returned public links' );
$valid_registry_path = $test_registry_path;
$test_registry_path = $invalid_parent_path;
assert_true( array() === tra_vel_v2_get_public_seo_opportunity_links( 'tokyo' ), 'invalid registry returned public links' );
$test_registry_path = $valid_registry_path;

$decision = tra_vel_v2_get_current_seo_opportunity( 101 );
$decision_contract = tra_vel_v2_get_seo_opportunity_publication_contract( 101, $decision );
assert_true( $decision_contract['ready'], 'fully evidenced decision guide did not pass' );
assert_true( isset( $decision_contract['checks']['guide_source_freshness'] ), 'shared guide freshness contract was not reused' );
$test_posts[201]['template'] = 'default';
assert_true( ! tra_vel_v2_get_seo_opportunity_publication_contract( 101, $decision )['ready'], 'decision guide accepted a semantic destination parent with the wrong template' );
$test_posts[201]['template'] = 'page-destination.php';
$test_guide_contracts[101]['ready'] = false;
assert_true( ! tra_vel_v2_get_seo_opportunity_publication_contract( 101, $decision )['ready'], 'failed guide contract did not close the decision gate' );
$test_guide_contracts[101] = $ready_guide_contract;
$test_profiles[101]['publication_status'] = 'editorial-review';
assert_true( ! tra_vel_v2_get_seo_opportunity_publication_contract( 101, $decision )['ready'], 'editorial-review was accepted as publish-ready' );
$test_profiles[101] = $ready_profile;

$decision_metrics = tra_vel_v2_seo_opportunity_content_metrics( $decision_content );
assert_true( $decision_metrics['word_count'] >= 5000 && $decision_metrics['hebrew_ratio'] >= 0.75 && 12 === $decision_metrics['h2_count'] && 3 === $decision_metrics['table_count'], 'decision metrics drifted' );
$breadcrumbs = tra_vel_v2_seo_opportunity_breadcrumb_items( $decision );
assert_true( 4 === count( $breadcrumbs ) && '/destinations/' === wp_parse_url( $breadcrumbs[1]['url'], PHP_URL_PATH ) && '/destinations/tokyo/' === wp_parse_url( $breadcrumbs[2]['url'], PHP_URL_PATH ), 'decision semantic breadcrumbs are not Home > Destinations > destination > page' );
assert_true( false !== strpos( tra_vel_v2_seo_opportunity_action_url( $decision ), '/ai-planner/' ), 'decision primary transfers intent did not preserve ordered planner scope' );

$decision_nodes = tra_vel_v2_seo_opportunity_schema_nodes( 101, $decision );
$decision_types = array_column( $decision_nodes, '@type' );
assert_true( in_array( 'WebPage', $decision_types, true ) && in_array( 'Article', $decision_types, true ) && in_array( 'BreadcrumbList', $decision_types, true ), 'ready decision schema is incomplete' );

$test_current_id = 102;
$transaction = tra_vel_v2_get_current_seo_opportunity( 102 );
assert_true( tra_vel_v2_get_seo_opportunity_publication_contract( 102, $transaction )['ready'], 'fully evidenced transaction did not pass' );
$transaction_nodes = tra_vel_v2_seo_opportunity_schema_nodes( 102, $transaction );
$transaction_types = array_column( $transaction_nodes, '@type' );
assert_true( in_array( 'WebPage', $transaction_types, true ) && in_array( 'BreadcrumbList', $transaction_types, true ) && ! in_array( 'Article', $transaction_types, true ), 'transaction schema type is wrong' );
assert_true( '' === tra_vel_v2_seo_opportunity_airport_code( 'tokyo' ), 'Tokyo retained a single-airport HND bias' );
$tokyo_flight_action = tra_vel_v2_seo_opportunity_action_url( $transaction );
$tokyo_flight_query = array();
parse_str( (string) wp_parse_url( $tokyo_flight_action, PHP_URL_QUERY ), $tokyo_flight_query );
assert_true( false !== strpos( $tokyo_flight_action, '/ai-planner/' ) && 'tokyo' === ( $tokyo_flight_query['destination'] ?? '' ) && 0 === strpos( (string) ( $tokyo_flight_query['scope'] ?? '' ), 'flights' ) && false === strpos( $tokyo_flight_action, 'HND' ), 'Tokyo flight CTA is not city-wide map/planner intent' );

$package_entry = $fixture['entries'][6];
$package_entry['status'] = 'live';
$tokyo_package_action = tra_vel_v2_seo_opportunity_action_url( $package_entry );
$tokyo_package_query = array();
parse_str( (string) wp_parse_url( $tokyo_package_action, PHP_URL_QUERY ), $tokyo_package_query );
assert_true( false !== strpos( $tokyo_package_action, '/ai-planner/' ) && 0 === strpos( (string) ( $tokyo_package_query['scope'] ?? '' ), 'packages' ), 'canonical package vertical did not control the city-wide planner scope' );
$plugin_graph = array(
	array( '@type' => 'WebSite' ), array( '@type' => 'Product' ), array( '@type' => 'Offer' ), array( '@type' => 'ItemList' ), array( '@type' => 'Article' ), array( '@type' => 'WebPage', 'offers' => array() ),
);
$merged = tra_vel_v2_merge_seo_opportunity_schema_graph( $plugin_graph );
$merged_types = array_column( $merged, '@type' );
assert_true( ! array_intersect( array( 'Product', 'Offer', 'ItemList', 'Article' ), $merged_types ), 'transaction plugin graph retained unvalidated commercial or Article nodes' );

$test_posts[102]['meta']['_tra_vel_seo_opportunity_ready'] = false;
$robots = tra_vel_v2_seo_opportunity_robots_policy( array( 'index' => true ) );
assert_true( ! empty( $robots['noindex'] ) && ! empty( $robots['follow'] ) && ! isset( $robots['index'] ), 'unready transaction was not noindexed' );
$aioseo_robots = tra_vel_v2_seo_opportunity_aioseo_robots_policy( array( 'noindex' => '', 'nofollow' => 'nofollow', 'max-image-preview' => 'large' ) );
assert_true( 'noindex' === $aioseo_robots['noindex'] && '' === $aioseo_robots['nofollow'] && 'large' === $aioseo_robots['max-image-preview'], 'AIOSEO associative robots attributes bypassed the fail-closed gate' );
$unready_graph = tra_vel_v2_merge_seo_opportunity_schema_graph( $plugin_graph );
$unready_types = array_column( $unready_graph, '@type' );
assert_true( ! array_intersect( array( 'Product', 'Offer', 'ItemList', 'Article', 'BreadcrumbList' ), $unready_types ), 'unready graph retained rich nodes' );

$test_posts[102]['meta']['_tra_vel_seo_opportunity_ready'] = true;
$test_posts[102]['permalink'] = 'https://example.test/flights/tokyo-wrong/';
assert_true( ! tra_vel_v2_get_seo_opportunity_publication_contract( 102, $transaction )['ready'], 'wrong canonical permalink passed' );

$test_posts[102]['permalink'] = 'https://example.test/flights/tokyo/';
$test_posts[102]['template'] = 'default';
assert_true( 'tokyo-flights' === tra_vel_v2_get_owned_seo_opportunity( 102 )['id'] && null === tra_vel_v2_get_current_seo_opportunity( 102 ), 'path-owner discovery still depends on the page template' );
$wrong_template_robots = tra_vel_v2_seo_opportunity_robots_policy( array( 'index' => true ) );
$wrong_template_yoast = tra_vel_v2_seo_opportunity_yoast_robots_policy( array( 'index' => 'index', 'follow' => 'nofollow' ) );
assert_true( ! empty( $wrong_template_robots['noindex'] ) && 'noindex' === $wrong_template_yoast['index'] && 'follow' === $wrong_template_yoast['follow'], 'wrong-template owner escaped core or Yoast robots protection' );
assert_true( '' === tra_vel_v2_seo_opportunity_canonical_url( 'https://example.test/leak/', new WP_Post( 102 ) ), 'wrong-template owner retained a canonical signal' );
$wrong_template_graph = tra_vel_v2_merge_seo_opportunity_schema_graph( $plugin_graph );
assert_true( ! array_intersect( array( 'Product', 'Offer', 'ItemList', 'Article', 'BreadcrumbList' ), array_column( $wrong_template_graph, '@type' ) ), 'wrong-template owner retained rich schema' );
$wp_query->is_404 = false;
$test_status_header = 200;
tra_vel_v2_protect_seo_opportunity_route_identity();
assert_true( $wp_query->is_404 && 404 === $test_status_header, 'wrong-template owner did not become a local 404' );

$test_posts[102]['permalink'] = 'https://example.test/flights/tokyo-drifted/';
assert_true( 'tokyo-flights' === ( tra_vel_v2_get_protected_seo_opportunity( 102 )['id'] ?? '' ), 'stored registry owner did not protect a path-drifted page' );
assert_true( ! empty( tra_vel_v2_seo_opportunity_robots_policy( array() )['noindex'] ), 'combined path and template drift escaped robots protection' );
assert_true( '' === tra_vel_v2_seo_opportunity_canonical_url( 'https://example.test/leak/', new WP_Post( 102 ) ), 'combined path and template drift retained a canonical signal' );
$combined_drift_graph = tra_vel_v2_merge_seo_opportunity_schema_graph( $plugin_graph );
assert_true( ! array_intersect( array( 'Product', 'Offer', 'ItemList', 'Article', 'BreadcrumbList' ), array_column( $combined_drift_graph, '@type' ) ), 'combined path and template drift retained rich schema' );
$wp_query->is_404 = false;
tra_vel_v2_protect_seo_opportunity_route_identity();
assert_true( $wp_query->is_404, 'combined path and template drift did not become a local 404' );

$test_posts[102]['meta']['_tra_vel_seo_opportunity_id'] = 'unknown-owner';
assert_true( null === tra_vel_v2_get_protected_seo_opportunity( 102 ) && tra_vel_v2_is_seo_opportunity_protection_candidate( 102 ), 'unresolved stored ownership did not remain a fail-closed protection candidate' );
assert_true( ! empty( tra_vel_v2_seo_opportunity_robots_policy( array() )['noindex'] ), 'unresolved stored ownership escaped robots protection' );
assert_true( '' === tra_vel_v2_seo_opportunity_canonical_url( 'https://example.test/leak/', new WP_Post( 102 ) ), 'unresolved stored ownership retained a canonical signal' );
$unresolved_owner_graph = tra_vel_v2_merge_seo_opportunity_schema_graph( $plugin_graph );
assert_true( ! array_intersect( array( 'Product', 'Offer', 'ItemList', 'Article', 'BreadcrumbList' ), array_column( $unresolved_owner_graph, '@type' ) ), 'unresolved stored ownership retained rich schema' );
$wp_query->is_404 = false;
tra_vel_v2_protect_seo_opportunity_route_identity();
assert_true( $wp_query->is_404, 'unresolved stored ownership did not become a local 404' );

$test_posts[102]['permalink'] = 'https://example.test/flights/tokyo/';
$test_posts[102]['template'] = 'page-seo-opportunity.php';
$test_posts[102]['meta']['_tra_vel_seo_opportunity_id'] = 'wrong-owner';
assert_true( ! tra_vel_v2_get_seo_opportunity_publication_contract( 102, $transaction )['ready'], 'wrong owner meta passed the publication contract' );
assert_true( ! empty( tra_vel_v2_seo_opportunity_robots_policy( array() )['noindex'] ), 'wrong owner meta escaped robots protection' );
$wp_query->is_404 = false;
tra_vel_v2_protect_seo_opportunity_route_identity();
assert_true( $wp_query->is_404, 'wrong owner meta did not become a local 404' );

$test_posts[103] = array( 'template' => 'default', 'permalink' => 'https://example.test/packages/tokyo/', 'status' => 'publish', 'post_content' => $transaction_content, 'post_author' => 1, 'excerpt' => '', 'meta' => array() );
$test_current_id = 103;
assert_true( 'tokyo-packages' === tra_vel_v2_get_owned_seo_opportunity( 103 )['id'], 'backlog path owner was not discovered' );
assert_true( ! empty( tra_vel_v2_seo_opportunity_robots_policy( array() )['noindex'] ), 'occupied backlog path escaped noindex' );
$wp_query->is_404 = false;
tra_vel_v2_protect_seo_opportunity_route_identity();
assert_true( $wp_query->is_404, 'occupied backlog path did not become a local 404' );

$test_is_page_request = false;
$non_page_robots = tra_vel_v2_seo_opportunity_robots_policy( array( 'index' => true ) );
$non_page_graph = tra_vel_v2_merge_seo_opportunity_schema_graph( $plugin_graph );
$non_page_canonical = tra_vel_v2_seo_opportunity_canonical_url( 'https://example.test/archive-canonical/', new WP_Post( 103 ) );
$wp_query->is_404 = false;
$test_status_header = 200;
tra_vel_v2_protect_seo_opportunity_route_identity();
assert_true( array( 'index' => true ) === $non_page_robots, 'an archive/term query ID collision changed robots' );
assert_true( $plugin_graph === $non_page_graph, 'an archive/term query ID collision changed schema' );
assert_true( 'https://example.test/archive-canonical/' === $non_page_canonical, 'an archive/term query ID collision changed canonical output' );
assert_true( ! $wp_query->is_404 && 200 === $test_status_header, 'an archive/term query ID collision became a local 404' );

$sitemap_ids = tra_vel_v2_unready_seo_opportunity_page_ids();
$core_sitemap_args = tra_vel_v2_exclude_unready_seo_opportunities_from_core_sitemap( array( 'post__not_in' => array( 999 ) ), 'page' );
$post_sitemap_args = tra_vel_v2_exclude_unready_seo_opportunities_from_core_sitemap( array(), 'post' );
$plugin_sitemap_ids = tra_vel_v2_exclude_unready_seo_opportunities_from_plugin_sitemaps( array( 998 ) );
assert_true( in_array( 102, $sitemap_ids, true ) && in_array( 103, $sitemap_ids, true ) && ! in_array( 101, $sitemap_ids, true ), 'sitemap exclusion IDs do not follow the publication contract' );
assert_true( in_array( 999, $core_sitemap_args['post__not_in'], true ) && in_array( 102, $core_sitemap_args['post__not_in'], true ) && in_array( 103, $core_sitemap_args['post__not_in'], true ), 'core page sitemap did not merge managed exclusions' );
assert_true( array() === $post_sitemap_args, 'core non-page sitemap was modified' );
assert_true( in_array( 998, $plugin_sitemap_ids, true ) && in_array( 102, $plugin_sitemap_ids, true ) && in_array( 103, $plugin_sitemap_ids, true ), 'Yoast/AIOSEO sitemap exclusions did not merge' );

@unlink( $test_registry_path );
@unlink( $invalid_parent_path );
@unlink( $invalid_public_map_path );
echo "Tra-Vel SEO opportunity runtime validation passed.\n";
