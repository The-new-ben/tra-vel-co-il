<?php
/**
 * Deterministic harness for the supplier handoff boundary.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );

$GLOBALS['tv2_handoff_providers'] = array();

class WP_REST_Controller { protected $namespace; protected $rest_base; }
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; }
class WP_Error {
	private $code; private $data;
	public function __construct( $code, $message = '', $data = null ) { unset( $message ); $this->code = $code; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}
class WP_REST_Response {
	public $data; public $status; public $headers = array();
	public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; }
	public function header( $name, $value ) { $this->headers[ $name ] = $value; }
}
class WP_REST_Request {
	private $params;
	public function __construct( $params = array() ) { $this->params = $params; }
	public function get_param( $key ) { return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null; }
}
function add_action() {}
function add_filter() {}
function register_rest_route() {}
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_list_pluck( $list, $field ) { return array_map( static function ( $item ) use ( $field ) { return isset( $item[ $field ] ) ? $item[ $field ] : null; }, $list ); }
function __( $value ) { return $value; }
function number_format_i18n( $value ) { return number_format( $value ); }
function apply_filters( $hook, $value ) { return 'tra_vel_v2_handoff_providers' === $hook ? $GLOBALS['tv2_handoff_providers'] : $value; }
function esc_url_raw( $value, $protocols = null ) { unset( $protocols ); return filter_var( (string) $value, FILTER_SANITIZE_URL ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function rest_ensure_response( $value ) { return new WP_REST_Response( $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }

require TRA_VEL_V2_PATH . '/inc/handoffs/class-whatsapp-sales-handoff-provider.php';
require TRA_VEL_V2_PATH . '/inc/handoffs/class-supplier-handoff-controller.php';

function tv2_handoff_assert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "Supplier handoff runtime validation failed: {$message}\n" ); exit( 1 ); }
}

$controller = new Tra_Vel_V2_Supplier_Handoff_Controller();
$health = $controller->get_health();
tv2_handoff_assert( false === $health->data['enabled'] && 'disabled' === $health->data['booking_mode'], 'empty configuration pretends booking is enabled' );
$missing = $controller->prepare_handoff( new WP_REST_Request( array( 'provider' => 'none', 'vertical' => 'hotel', 'offer_id' => 'demo' ) ) );
tv2_handoff_assert( is_wp_error( $missing ) && 'tra_vel_handoff_not_configured' === $missing->get_error_code(), 'unconfigured demo handoff did not fail closed' );

$GLOBALS['tv2_handoff_providers'] = array(
	array(
		'id' => 'verified-partner', 'label' => 'Verified Partner', 'live' => true, 'sponsored' => true,
		'verticals' => array( 'hotel', 'unknown' ), 'allowed_hosts' => array( 'booking.partner.example' ),
		'disclosure' => 'Partner link; price and terms are confirmed on the partner site.',
		'build_url' => static function ( $context ) { return 'https://booking.partner.example/search?offer=' . rawurlencode( $context['offer_id'] ); },
	),
);
$valid = $controller->prepare_handoff( new WP_REST_Request( array( 'provider' => 'verified-partner', 'vertical' => 'hotel', 'offer_id' => 'hotel:123', 'destination' => 'Budapest', 'return_path' => '/hotels/' ) ) );
tv2_handoff_assert( ! is_wp_error( $valid ) && 200 === $valid->status, 'verified provider was rejected' );
tv2_handoff_assert( 0 === strpos( $valid->data['handoff_url'], 'https://booking.partner.example/' ), 'verified handoff URL changed host' );
tv2_handoff_assert( 'sponsored noopener noreferrer' === $valid->data['rel'], 'partner rel policy is missing' );
tv2_handoff_assert( true === $valid->data['price_recheck'] && true === $valid->data['booking_on_partner'], 'handoff disclosures are incomplete' );
tv2_handoff_assert( 'affiliate' === $valid->data['provider']['relationship'] && 'partner_booking' === $valid->data['conversion_type'], 'affiliate relationship is not explicit' );
tv2_handoff_assert( 'private, no-store, max-age=0' === $valid->headers['Cache-Control'], 'prepared handoff is cacheable' );

$GLOBALS['tv2_handoff_providers'][0]['build_url'] = static function () { return 'https://evil.example/steal'; };
$rejected = $controller->prepare_handoff( new WP_REST_Request( array( 'provider' => 'verified-partner', 'vertical' => 'hotel', 'offer_id' => 'hotel:123' ) ) );
tv2_handoff_assert( is_wp_error( $rejected ) && 'tra_vel_handoff_url_rejected' === $rejected->get_error_code(), 'non-allowlisted partner URL escaped the boundary' );

$GLOBALS['tv2_handoff_providers'] = tra_vel_v2_register_whatsapp_sales_handoff( array() );
$owned = $controller->prepare_handoff(
	new WP_REST_Request(
		array(
			'provider' => 'tra-vel-concierge', 'vertical' => 'package', 'offer_id' => 'search',
			'origin' => 'TLV', 'destination' => 'Bangkok', 'depart_date' => '2026-11-05',
			'return_date' => '2026-11-17', 'travelers' => 2, 'budget' => 9000, 'currency' => 'ILS',
		)
	)
);
tv2_handoff_assert( ! is_wp_error( $owned ) && false === $owned->data['booking_on_partner'], 'owned assisted-sales handoff was rejected' );
tv2_handoff_assert( 'owned' === $owned->data['provider']['relationship'] && 'assisted_quote' === $owned->data['conversion_type'], 'owned conversion relationship is not explicit' );
tv2_handoff_assert( 'noopener noreferrer' === $owned->data['rel'], 'owned handoff was incorrectly marked sponsored' );
tv2_handoff_assert( 0 === strpos( $owned->data['handoff_url'], 'https://api.whatsapp.com/send?' ), 'owned handoff changed its allowlisted host' );
$owned_query = array();
parse_str( parse_url( $owned->data['handoff_url'], PHP_URL_QUERY ), $owned_query );
tv2_handoff_assert( '972525101555' === $owned_query['phone'], 'owned handoff changed the verified public sales number' );
tv2_handoff_assert( false !== strpos( $owned_query['text'], 'Bangkok' ) && false !== strpos( $owned_query['text'], '9,000 ILS' ), 'owned handoff lost safe trip context' );
$owned_health = $controller->get_health();
tv2_handoff_assert( 'assisted_sales' === $owned_health->data['booking_mode'], 'owned handoff health is not reported as assisted sales' );

echo "Tra-Vel supplier handoff runtime validation passed (fail-closed demo, owned sales, affiliate gate, HTTPS allowlist).\n";
