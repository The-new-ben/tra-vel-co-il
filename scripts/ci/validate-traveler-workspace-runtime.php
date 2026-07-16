<?php
/**
 * Deterministic harness for authenticated workspace storage and safety rules.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );

$GLOBALS['tv2_workspace_user_id'] = 17;
$GLOBALS['tv2_workspace_meta']    = array();

class WP_REST_Controller { protected $namespace; protected $rest_base; }
class WP_REST_Server {
	const READABLE = 'GET'; const CREATABLE = 'POST'; const EDITABLE = 'PUT'; const DELETABLE = 'DELETE';
}
class WP_Error {
	private $code; private $data;
	public function __construct( $code, $message = '', $data = null ) { unset( $message ); $this->code = $code; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}
class WP_REST_Response {
	public $data; public $status; public $headers = array(); public $links = array();
	public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; }
	public function header( $name, $value ) { $this->headers[ $name ] = $value; }
	public function add_link( $rel, $href ) { $this->links[ $rel ] = $href; }
}
class WP_REST_Request {
	private $json; private $params;
	public function __construct( $json = array(), $params = array() ) { $this->json = $json; $this->params = $params; }
	public function get_json_params() { return $this->json; }
	public function get_param( $key ) { return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null; }
}
function add_action() {}
function register_rest_route() {}
function get_current_user_id() { return $GLOBALS['tv2_workspace_user_id']; }
function current_user_can( $capability ) { return 'read' === $capability && get_current_user_id() > 0; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function esc_url_raw( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_URL ); }
function home_url( $path = '/' ) { return 'https://tra-vel.co.il' . ( '/' === $path ? '/' : $path ); }
function rest_url( $path = '' ) { return 'https://tra-vel.co.il/wp-json/' . ltrim( $path, '/' ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function current_time( $type ) { return 'c' === $type ? '2030-04-01T10:00:00+00:00' : 0; }
function get_user_meta( $user_id, $key ) { return isset( $GLOBALS['tv2_workspace_meta'][ $user_id ][ $key ] ) ? $GLOBALS['tv2_workspace_meta'][ $user_id ][ $key ] : ''; }
function update_user_meta( $user_id, $key, $value ) { $GLOBALS['tv2_workspace_meta'][ $user_id ][ $key ] = $value; return true; }
function delete_user_meta( $user_id, $key ) { unset( $GLOBALS['tv2_workspace_meta'][ $user_id ][ $key ] ); return true; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function rest_ensure_response( $value ) { return new WP_REST_Response( $value ); }

require TRA_VEL_V2_PATH . '/inc/workspace/class-traveler-workspace-controller.php';

function tv2_workspace_assert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "Traveler workspace runtime validation failed: {$message}\n" ); exit( 1 ); }
}

$controller = new Tra_Vel_V2_Traveler_Workspace_Controller();
tv2_workspace_assert( true === $controller->can_use_workspace(), 'authenticated reader was rejected' );
$GLOBALS['tv2_workspace_user_id'] = 0;
tv2_workspace_assert( false === $controller->can_use_workspace(), 'anonymous visitor can access server workspace' );
$GLOBALS['tv2_workspace_user_id'] = 17;

$initial = $controller->get_workspace();
tv2_workspace_assert( 'private, no-store, max-age=0' === $initial->headers['Cache-Control'], 'personal response is cacheable' );
tv2_workspace_assert( false === $initial->data['meta']['sensitive_data_allowed'], 'workspace allows sensitive data' );

$saved = $controller->save_item( new WP_REST_Request( array(
	'kind' => 'package', 'external_id' => 'budapest-smart-demo', 'title' => 'Smart Budapest', 'subtitle' => '<b>safe</b>',
	'destination' => 'Budapest', 'route' => 'TLV to BUD', 'price_label' => '$1,304', 'price_amount' => 1304,
	'currency' => 'USD', 'data_mode' => 'demo', 'href' => 'https://evil.example/steal',
) ) );
tv2_workspace_assert( 201 === $saved->status, 'valid item was not created' );
tv2_workspace_assert( 'package:budapest-smart-demo' === $saved->data['items'][0]['id'], 'stable saved-item id changed' );
tv2_workspace_assert( 'https://tra-vel.co.il/' === $saved->data['items'][0]['href'], 'external URL was retained' );
tv2_workspace_assert( false === $saved->data['items'][0]['watch']['delivery_enabled'], 'new item enabled watch delivery' );
tv2_workspace_assert( 'safe' === $saved->data['items'][0]['subtitle'], 'saved text was not sanitized' );

$invalid = $controller->save_item( new WP_REST_Request( array( 'kind' => 'passport', 'external_id' => 'secret', 'title' => 'Secret' ) ) );
tv2_workspace_assert( is_wp_error( $invalid ) && 'tra_vel_workspace_invalid_kind' === $invalid->get_error_code(), 'unsupported sensitive item kind was accepted' );

for ( $index = 0; $index < 55; $index++ ) {
	$controller->save_item( new WP_REST_Request( array( 'kind' => 'hotel', 'external_id' => 'hotel-' . $index, 'title' => 'Hotel ' . $index, 'href' => '/hotels/' ) ) );
}
$bounded = $controller->get_workspace();
tv2_workspace_assert( 50 === count( $bounded->data['items'] ), 'server workspace exceeded the 50-item limit' );

$watched_id = $bounded->data['items'][0]['id'];
$watched = $controller->update_watch( new WP_REST_Request( array(), array( 'item_id' => $watched_id, 'enabled' => true, 'target_amount' => 500 ) ) );
tv2_workspace_assert( true === $watched->data['items'][0]['watch']['enabled'], 'price target was not stored' );
tv2_workspace_assert( false === $watched->data['items'][0]['watch']['delivery_enabled'], 'price target falsely enabled delivery' );
tv2_workspace_assert( 'awaiting_live_supplier' === $watched->data['items'][0]['watch']['status'], 'watch readiness is mislabeled' );

$preferences = $controller->update_preferences( new WP_REST_Request( array( 'home_airport' => 'tlv', 'currency' => 'ILS', 'budget' => 9000, 'max_stops' => 1, 'party_style' => 'couple', 'priorities' => array( 'price', 'comfort', 'not_allowed' ) ) ) );
tv2_workspace_assert( 'TLV' === $preferences->data['preferences']['home_airport'], 'airport preference was not normalized' );
tv2_workspace_assert( array( 'price', 'comfort' ) === $preferences->data['preferences']['priorities'], 'unknown priority was retained' );

$cleared = $controller->clear_workspace();
tv2_workspace_assert( 0 === count( $cleared->data['items'] ), 'workspace clear did not remove saved items' );

echo "Tra-Vel traveler workspace runtime validation passed (auth, private cache policy, bounded storage, URL safety, inactive delivery).\n";
