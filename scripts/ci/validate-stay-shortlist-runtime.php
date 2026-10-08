<?php
/** Runtime isolation checks for the Budapest-only local comparison. */
define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );
define( 'TRA_VEL_V2_URI', 'https://example.test/theme/tra-vel-v2' );
$GLOBALS['fixture'] = array( 'page' => true, 'id' => 'budapest-hotels', 'path' => '/hotels/budapest/', 'exposable' => true, 'identity' => true, 'content' => '[tra_vel_stay_shortlist]' );
$GLOBALS['assets'] = array();
function is_singular( $type ) { return 'page' === $type && $GLOBALS['fixture']['page']; }
function is_page( $slug ) { return 'hotels' === $slug && ! empty( $GLOBALS['fixture']['hub'] ); }
function get_permalink( $id ) { return 'https://example.test' . ( $GLOBALS['fixture']['hub_path'] ?? '/hotels/' ); }
function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
function get_queried_object_id() { return 42; }
function tra_vel_v2_get_current_seo_opportunity( $id ) { return array( 'id' => $GLOBALS['fixture']['id'], 'canonicalPath' => $GLOBALS['fixture']['path'] ); }
function tra_vel_v2_is_exposable_seo_opportunity( $entry ) { return $GLOBALS['fixture']['exposable']; }
function tra_vel_v2_seo_opportunity_identity_matches( $id, $entry ) { return $GLOBALS['fixture']['identity']; }
function get_post( $id ) { return (object) array( 'post_content' => $GLOBALS['fixture']['content'] ); }
function has_shortcode( $text, $tag ) { return false !== strpos( $text, '[' . $tag . ']' ); }
function tra_vel_v2_asset_version( $path ) { return 'fixture'; }
function wp_enqueue_style( ...$args ) { $GLOBALS['assets'][] = array( 'style', $args ); }
function wp_enqueue_script( ...$args ) { $GLOBALS['assets'][] = array( 'script', $args ); }
function add_action( ...$args ) {}
function add_shortcode( ...$args ) {}
require TRA_VEL_V2_PATH . '/inc/stay-shortlist.php';
$checks = 0;
function expect_true( $test, $message ) { global $checks; ++$checks; if ( ! $test ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); } }
$original = $GLOBALS['fixture'];
foreach ( array( 'page' => false, 'id' => 'prague-hotels', 'path' => '/hotels/prague/', 'exposable' => false, 'identity' => false ) as $key => $value ) {
	$GLOBALS['fixture'] = $original;
	$GLOBALS['fixture'][ $key ] = $value;
	expect_true( ! tra_vel_v2_stay_shortlist_owner(), 'Reject changed owner condition ' . $key );
	expect_true( '' === tra_vel_v2_stay_shortlist_shortcode(), 'No unrelated or unapproved shortcode output ' . $key );
	$GLOBALS['assets'] = array();
	tra_vel_v2_enqueue_stay_shortlist();
	expect_true( array() === $GLOBALS['assets'], 'No unrelated page asset load ' . $key );
}
$GLOBALS['fixture'] = $original;
$GLOBALS['fixture']['content'] = 'Guide without the component';
tra_vel_v2_enqueue_stay_shortlist();
expect_true( array() === $GLOBALS['assets'], 'No assets without shortcode' );
$GLOBALS['fixture'] = array_merge( $original, array( 'hub' => true, 'id' => 'hotels', 'path' => '/hotels/', 'content' => '' ) );
expect_true( tra_vel_v2_stay_shortlist_owner(), 'Existing exact hotels hub is an independent calculator owner' );
tra_vel_v2_enqueue_stay_shortlist();
expect_true( 3 === count( $GLOBALS['assets'] ), 'Hotels hub loads assets without changing stored content' );
$GLOBALS['assets'] = array();
$GLOBALS['fixture']['hub_path'] = '/unrelated/hotels/';
expect_true( ! tra_vel_v2_stay_shortlist_owner(), 'A same-slug page at another path cannot own the calculator' );
tra_vel_v2_enqueue_stay_shortlist();
expect_true( array() === $GLOBALS['assets'], 'No calculator assets on a misleading same-slug path' );
$GLOBALS['fixture'] = $original;
tra_vel_v2_enqueue_stay_shortlist();
expect_true( 3 === count( $GLOBALS['assets'] ), 'Three scoped assets loaded' );
expect_true( array( 'tra-vel-v2-stay-cost-engine' ) === $GLOBALS['assets'][2][1][2], 'Controller depends on arithmetic engine' );
$html = tra_vel_v2_stay_shortlist_shortcode();
expect_true( false !== strpos( $html, 'data-stay-shortlist' ), 'Real bundled comparison rendered' );
expect_true( false !== strpos( $html, 'id="tr-stay-shortlist-form"' ), 'Prefixed form identity' );
expect_true( false !== strpos( $html, 'id="tr-stay-text-fallback"' ), 'No-script manual fallback retained' );
expect_true( false === strpos( $html, '<main' ) && false === strpos( $html, '<h1' ), 'No nested page main or duplicate first-level heading' );
expect_true( '' === tra_vel_v2_stay_shortlist_shortcode(), 'One instance prevents duplicate interactive IDs' );
echo 'Stay shortlist integration passed (' . $checks . " checks).\n";
