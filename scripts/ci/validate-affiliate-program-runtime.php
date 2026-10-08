<?php
/**
 * Affiliate links and rendered proposal regressions, without WordPress or network.
 * Run: php scripts/ci/validate-affiliate-program-runtime.php
 */
define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );
$GLOBALS['tv2_affiliate_options'] = array();
$GLOBALS['tv2_affiliate_override'] = null;
$GLOBALS['tv2_affiliate_providers'] = array();
$GLOBALS['tv2_affiliate_checks'] = 0;

set_error_handler( static function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );
function __( $text, $domain = '' ) { return $text; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['tv2_affiliate_options'] ) ? $GLOBALS['tv2_affiliate_options'][ $key ] : $default; }
function apply_filters( $hook, $value, $key = null ) {
	if ( 'tra_vel_v2_handoff_providers' === $hook ) { return $GLOBALS['tv2_affiliate_providers']; }
	if ( 'tra_vel_v2_affiliate_program_link_override' === $hook && null !== $GLOBALS['tv2_affiliate_override'] ) {
		return call_user_func( $GLOBALS['tv2_affiliate_override'], $value, $key );
	}
	return $value;
}
function esc_url_raw( $value, $protocols = array( 'http', 'https' ) ) {
	$value = trim( (string) $value );
	return filter_var( $value, FILTER_VALIDATE_URL ) && in_array( parse_url( $value, PHP_URL_SCHEME ), $protocols, true ) ? $value : '';
}
function esc_url( $value ) { return htmlspecialchars( esc_url_raw( $value ), ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function wp_parse_args( $value, $defaults ) { return array_merge( $defaults, $value ); }
function wp_parse_url( $value, $component ) { return parse_url( $value, $component ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function absint( $value ) { return abs( (int) $value ); }
function number_format_i18n( $value ) { return number_format( $value ); }
function home_url( $path ) { return 'https://travel.example' . $path; }

require TRA_VEL_V2_PATH . '/inc/affiliate-programs.php';
require TRA_VEL_V2_PATH . '/inc/proposal.php';

function tv2_affiliate_assert( $condition, $message ) {
	$GLOBALS['tv2_affiliate_checks']++;
	if ( ! $condition ) { fwrite( STDERR, "Affiliate runtime validation failed: {$message}\n" ); exit( 1 ); }
}
function tv2_affiliate_panel( $addons ) {
	$args = array_fill_keys( array(
		'addon_note', 'addons_heading', 'airline_label', 'book_label', 'check_dates_line', 'check_prices_line',
		'close_label', 'currency_note', 'flight_found_line', 'freshness', 'party_label', 'party_many', 'party_one',
		'scope_note', 'start_label', 'state', 'step_down_label', 'step_up_label', 'symbol', 'tiers_heading', 'title',
		'total_line', 'total_template', 'travelers_label', 'trigger_label', 'verdict_many', 'verdict_one',
		'wa_addons_line', 'wa_addons_template', 'wa_dates_line', 'whatsapp', 'whatsapp_all', 'whatsapp_label',
	), '' );
	$args['instance'] = 1;
	$args['travelers'] = 1;
	$args['min_travelers'] = 1;
	$args['max_travelers'] = 20;
	$args['trigger'] = false;
	$args['fill_lines'] = array();
	$args['wa_travelers_lines'] = array();
	$args['addons'] = $addons;
	$args['tiers'] = array( array_merge(
		array_fill_keys( array( 'airline', 'dates_label', 'label', 'stops_label', 'tier', 'total_label', 'unit_label', 'wa_dates_line' ), '' ),
		array( 'unit' => 100, 'deep_link' => 'https://flight.example/observed' )
	) );
	ob_start();
	include TRA_VEL_V2_PATH . '/template-parts/proposal-panel.php';
	return ob_get_clean();
}

$ekta_url = 'https://ekta.example/affiliate';
foreach ( array( false, true ) as $enabled_option ) {
	foreach ( array( false, true ) as $force_override ) {
		$GLOBALS['tv2_affiliate_options'] = array( 'tra_vel_v2_affiliate_enabled_ekta' => $enabled_option, 'tra_vel_v2_affiliate_url_ekta' => $ekta_url );
		$GLOBALS['tv2_affiliate_override'] = $force_override ? static function ( $link, $key ) use ( $ekta_url ) {
			return 'ekta' === $key ? array( 'enabled' => true, 'url' => $ekta_url, 'label' => 'EKTA' ) : $link;
		} : null;
		foreach ( array( 'ekta', 'EKTA', 'e!kta' ) as $key ) {
			$link = tra_vel_v2_affiliate_program_link( $key );
			tv2_affiliate_assert( false === $link['enabled'] && '' === $link['url'], "EKTA became live for {$key}, option=" . (int) $enabled_option . ', override=' . (int) $force_override );
		}
		$addons = tra_vel_v2_proposal_addons();
		tv2_affiliate_assert( 3 === count( $addons ) && 'insurance' === $addons[0]['key'], 'Insurance content row was removed' );
		tv2_affiliate_assert( 'ביטוח נסיעות' === $addons[0]['label'] && '' === $addons[0]['url'] && '' === $addons[0]['cta_label'] && '' === $addons[0]['verdict_line'], 'Disabled insurance gained a link, CTA or success verdict' );
		$html = tv2_affiliate_panel( $addons );
		tv2_affiliate_assert( false === strpos( $html, $ekta_url ) && false === strpos( $html, 'השגנו לך ביטוח' ), 'Rendered proposal exposes EKTA or insurance success claim' );
		tv2_affiliate_assert( false !== strpos( $html, 'ביטוח נסיעות' ) && false !== strpos( $html, 'https://flight.example/observed' ), 'Existing insurance content or flight exit changed' );
	}
}

$GLOBALS['tv2_affiliate_options'] = array( 'tra_vel_v2_affiliate_enabled_ekta' => true, 'tra_vel_v2_affiliate_url_ekta' => $ekta_url );
$GLOBALS['tv2_affiliate_override'] = static function ( $link ) use ( $ekta_url ) { return array( 'enabled' => true, 'url' => $ekta_url, 'label' => 'EKTA' ); };
$card_args = array( 'affiliate_key' => 'ekta', 'vertical' => 'insurance', 'heading' => 'ביטוח נסיעות', 'enabled_body' => 'השגנו לך ביטוח.', 'fallback_body' => 'בדקו את תנאי הפוליסה לפני רכישה.', 'fallback_label' => 'תכנון הנסיעה' );
ob_start(); tra_vel_v2_render_commerce_next_step( $card_args ); $planner_card = ob_get_clean();
tv2_affiliate_assert( false === strpos( $planner_card, $ekta_url ) && false === strpos( $planner_card, 'השגנו לך ביטוח' ), 'Commerce card exposes disabled affiliate or enabled body' );
tv2_affiliate_assert( false !== strpos( $planner_card, 'https://travel.example/ai-planner/' ) && false !== strpos( $planner_card, 'בדקו את תנאי הפוליסה לפני רכישה.' ), 'Existing planner fallback/content changed' );
$GLOBALS['tv2_affiliate_providers'] = array( array( 'id' => 'tra-vel-concierge', 'live' => true, 'verticals' => array( 'insurance' ), 'allowed_hosts' => array( 'wa.me' ), 'build_url' => static function ( $context ) { return 'https://wa.me/15555550123?text=' . rawurlencode( $context['vertical'] ); } ) );
ob_start(); tra_vel_v2_render_commerce_next_step( $card_args ); $wa_card = ob_get_clean();
tv2_affiliate_assert( false !== strpos( $wa_card, 'https://wa.me/15555550123?text=insurance' ) && false !== strpos( $wa_card, 'data-commerce-next-step-state="assisted"' ), 'Existing WhatsApp fallback no longer renders' );
tv2_affiliate_assert( false === strpos( $wa_card, $ekta_url ) && false === strpos( $wa_card, 'השגנו לך ביטוח' ), 'WhatsApp fallback claims completed insurance' );

$GLOBALS['tv2_affiliate_providers'] = array();
foreach ( array( 'activities_generic', 'transfers_generic', 'esim_generic' ) as $key ) {
	$GLOBALS['tv2_affiliate_override'] = null;
	$GLOBALS['tv2_affiliate_options'] = array();
	tv2_affiliate_assert( false === tra_vel_v2_affiliate_program_link( $key )['enabled'], "{$key} no longer defaults disabled" );
	$GLOBALS['tv2_affiliate_options'][ 'tra_vel_v2_affiliate_enabled_' . $key ] = true;
	$GLOBALS['tv2_affiliate_options'][ 'tra_vel_v2_affiliate_url_' . $key ] = 'https://supplier.example/' . $key;
	$link = tra_vel_v2_affiliate_program_link( $key );
	tv2_affiliate_assert( true === $link['enabled'] && 'https://supplier.example/' . $key === $link['url'], "{$key} valid option path changed" );
	$GLOBALS['tv2_affiliate_options'][ 'tra_vel_v2_affiliate_url_' . $key ] = 'http://supplier.example/insecure';
	tv2_affiliate_assert( false === tra_vel_v2_affiliate_program_link( $key )['enabled'], "{$key} accepted an insecure URL" );
	$GLOBALS['tv2_affiliate_override'] = static function ( $link, $program ) { return array( 'enabled' => true, 'url' => 'https://override.example/' . $program, 'label' => 'Verified supplier' ); };
	$link = tra_vel_v2_affiliate_program_link( $key );
	tv2_affiliate_assert( true === $link['enabled'] && 'https://override.example/' . $key === $link['url'] && 'Verified supplier' === $link['label'], "{$key} valid filter path changed" );
	ob_start(); tra_vel_v2_render_commerce_next_step( array( 'affiliate_key' => $key, 'enabled_body' => 'Existing supplier offer' ) ); $html = ob_get_clean();
	tv2_affiliate_assert( false !== strpos( $html, 'https://override.example/' . $key ) && false !== strpos( $html, 'Existing supplier offer' ), "{$key} live card changed" );
}
$addons = tra_vel_v2_proposal_addons();
tv2_affiliate_assert( '' === $addons[0]['url'] && '' === $addons[0]['verdict_line'], 'Global override re-enabled insurance' );
tv2_affiliate_assert( 'https://override.example/esim_generic' === $addons[1]['url'] && 'https://override.example/transfers_generic' === $addons[2]['url'], 'Other proposal add-on links changed' );
$html = tv2_affiliate_panel( $addons );
tv2_affiliate_assert( false === strpos( $html, 'השגנו לך ביטוח' ) && false === strpos( $html, 'https://override.example/ekta' ), 'Mixed-provider proposal claims EKTA insurance' );
tv2_affiliate_assert( false !== strpos( $html, 'השגנו לך eSIM.' ) && false !== strpos( $html, 'השגנו לך העברה.' ), 'Other live add-on verdicts changed' );
tv2_affiliate_assert( true === tra_vel_v2_affiliate_program_link( 'future_program' )['enabled'], 'Existing unknown-program extension point changed' );
$GLOBALS['tv2_affiliate_override'] = null;
tv2_affiliate_assert( false === tra_vel_v2_affiliate_program_link( 'future_program' )['enabled'], 'Unknown program no longer fails closed by default' );
echo 'Tra-Vel affiliate runtime validation passed (' . $GLOBALS['tv2_affiliate_checks'] . " checks: EKTA options/filter lock, rendered proposal/card, existing providers and fallbacks).\n";
