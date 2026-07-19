<?php
/**
 * Deterministic harness for authenticated workspace storage and safety rules.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );

$GLOBALS['tv2_workspace_user_id'] = 17;
$GLOBALS['tv2_workspace_meta']    = array();
$GLOBALS['tv2_workspace_routes']  = array();
$GLOBALS['tv2_workspace_write_failure'] = false;
$GLOBALS['tv2_workspace_delete_failure'] = false;
$GLOBALS['tv2_workspace_race_mode'] = '';
$GLOBALS['tv2_workspace_race_value'] = null;

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
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['tv2_workspace_routes'][ $namespace . $route ] = $args; }
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
function metadata_exists( $meta_type, $user_id, $key ) {
	return 'user' === $meta_type && isset( $GLOBALS['tv2_workspace_meta'][ $user_id ] ) && array_key_exists( $key, $GLOBALS['tv2_workspace_meta'][ $user_id ] );
}
function get_user_meta( $user_id, $key, $single = false ) {
	unset( $single );
	return metadata_exists( 'user', $user_id, $key ) ? $GLOBALS['tv2_workspace_meta'][ $user_id ][ $key ] : '';
}
function tv2_workspace_apply_race( $mode, $user_id, $key ) {
	if ( $mode !== $GLOBALS['tv2_workspace_race_mode'] ) return;
	$GLOBALS['tv2_workspace_meta'][ $user_id ][ $key ] = $GLOBALS['tv2_workspace_race_value'];
	$GLOBALS['tv2_workspace_race_mode'] = '';
}
function add_user_meta( $user_id, $key, $value, $unique = false ) {
	tv2_workspace_apply_race( 'add', $user_id, $key );
	if ( $GLOBALS['tv2_workspace_write_failure'] ) return false;
	if ( $unique && metadata_exists( 'user', $user_id, $key ) ) return false;
	$GLOBALS['tv2_workspace_meta'][ $user_id ][ $key ] = $value;
	return 101;
}
function update_user_meta( $user_id, $key, $value, $prev_value = null ) {
	tv2_workspace_apply_race( 'update', $user_id, $key );
	if ( $GLOBALS['tv2_workspace_write_failure'] ) return false;
	if ( ! metadata_exists( 'user', $user_id, $key ) ) return false;
	if ( func_num_args() >= 4 && $GLOBALS['tv2_workspace_meta'][ $user_id ][ $key ] !== $prev_value ) return false;
	if ( $GLOBALS['tv2_workspace_meta'][ $user_id ][ $key ] === $value ) return false;
	$GLOBALS['tv2_workspace_meta'][ $user_id ][ $key ] = $value;
	return true;
}
function delete_user_meta( $user_id, $key, $value = null ) {
	tv2_workspace_apply_race( 'delete', $user_id, $key );
	if ( $GLOBALS['tv2_workspace_delete_failure'] ) return false;
	if ( ! metadata_exists( 'user', $user_id, $key ) ) return false;
	if ( func_num_args() >= 3 && $GLOBALS['tv2_workspace_meta'][ $user_id ][ $key ] !== $value ) return false;
	unset( $GLOBALS['tv2_workspace_meta'][ $user_id ][ $key ] );
	return true;
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function rest_ensure_response( $value ) { return new WP_REST_Response( $value ); }

require TRA_VEL_V2_PATH . '/inc/workspace/class-traveler-workspace-controller.php';

function tv2_workspace_assert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "Traveler workspace runtime validation failed: {$message}\n" ); exit( 1 ); }
}

function tv2_workspace_item_by_id( $workspace, $item_id ) {
	foreach ( $workspace['items'] as $item ) {
		if ( $item_id === $item['id'] ) return $item;
	}
	return null;
}

$controller = new Tra_Vel_V2_Traveler_Workspace_Controller();
$controller->register_routes();
$sync_route = $GLOBALS['tv2_workspace_routes']['tra-vel/v2/workspace/sync'];
tv2_workspace_assert( 'PUT' === $sync_route['methods'], 'workspace sync accepts a method other than PUT' );
tv2_workspace_assert( array( $controller, 'can_use_workspace' ) === $sync_route['permission_callback'], 'workspace sync is missing account authorization' );
tv2_workspace_assert( true === $sync_route['args']['items']['required'] && 50 === $sync_route['args']['items']['maxItems'], 'workspace sync item route bounds changed' );
tv2_workspace_assert( 50 === $sync_route['args']['deleted_item_ids']['maxItems'], 'workspace sync tombstone route bounds changed' );
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

for ( $index = 0; $index < 49; $index++ ) {
	$controller->save_item( new WP_REST_Request( array( 'kind' => 'hotel', 'external_id' => 'hotel-' . $index, 'title' => 'Hotel ' . $index, 'href' => '/hotels/' ) ) );
}
$bounded = $controller->get_workspace();
tv2_workspace_assert( 50 === count( $bounded->data['items'] ), 'server workspace exceeded the 50-item limit' );
$before_single_capacity = $bounded->data;
$single_capacity = $controller->save_item( new WP_REST_Request( array( 'kind' => 'hotel', 'external_id' => 'hotel-overflow', 'title' => 'Must not evict', 'href' => '/hotels/' ) ) );
tv2_workspace_assert( is_wp_error( $single_capacity ) && 'tra_vel_workspace_capacity' === $single_capacity->get_error_code() && 409 === $single_capacity->get_error_data()['status'], 'single-item save did not conflict at capacity' );
tv2_workspace_assert( $before_single_capacity === $controller->get_workspace()->data, 'single-item capacity conflict evicted an unrelated item' );
$single_refresh = $controller->save_item( new WP_REST_Request( array( 'kind' => 'hotel', 'external_id' => 'hotel-48', 'title' => 'Hotel 48 refreshed', 'href' => '/hotels/' ) ) );
tv2_workspace_assert( $single_refresh instanceof WP_REST_Response && 50 === count( $single_refresh->data['items'] ) && 'Hotel 48 refreshed' === $single_refresh->data['items'][0]['title'], 'refreshing an existing item was blocked at capacity' );

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

// User-meta writes are compare-and-swap mutations. A competing first insert,
// non-empty update, or clear wins intact and the stale request receives 409.
unset( $GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ] );
$concurrent_first = $controller->get_workspace()->data;
$concurrent_first['preferences']['currency'] = 'EUR';
$GLOBALS['tv2_workspace_race_mode'] = 'add';
$GLOBALS['tv2_workspace_race_value'] = $concurrent_first;
$add_race = $controller->save_item( new WP_REST_Request( array( 'kind' => 'hotel', 'external_id' => 'stale-first', 'title' => 'Stale first' ) ) );
tv2_workspace_assert( is_wp_error( $add_race ) && 'tra_vel_workspace_conflict' === $add_race->get_error_code() && 409 === $add_race->get_error_data()['status'], 'competing first workspace insert was overwritten' );
tv2_workspace_assert( $concurrent_first === $GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ], 'add race did not preserve the winning workspace' );

$base_workspace = $concurrent_first;
$concurrent_update = $base_workspace;
$concurrent_update['preferences']['budget'] = 7777;
$GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ] = $base_workspace;
$GLOBALS['tv2_workspace_race_mode'] = 'update';
$GLOBALS['tv2_workspace_race_value'] = $concurrent_update;
$update_race = $controller->update_preferences( new WP_REST_Request( array( 'home_airport' => 'TLV', 'currency' => 'ILS', 'budget' => 50, 'max_stops' => 1, 'party_style' => 'couple', 'priorities' => array( 'price' ) ) ) );
tv2_workspace_assert( is_wp_error( $update_race ) && 'tra_vel_workspace_conflict' === $update_race->get_error_code(), 'non-empty user-meta CAS did not detect a concurrent update' );
tv2_workspace_assert( $concurrent_update === $GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ], 'update race did not preserve the winning workspace' );

$GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ] = $base_workspace;
$GLOBALS['tv2_workspace_race_mode'] = 'delete';
$GLOBALS['tv2_workspace_race_value'] = $concurrent_update;
$clear_race = $controller->clear_workspace();
tv2_workspace_assert( is_wp_error( $clear_race ) && 'tra_vel_workspace_conflict' === $clear_race->get_error_code(), 'workspace clear deleted a concurrent update' );
tv2_workspace_assert( $concurrent_update === $GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ], 'clear race did not preserve the winning workspace' );

// An unchanged target succeeds without a write, even when storage writes are
// unavailable; an existing empty legacy value cannot be safely qualified.
$GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ] = $base_workspace;
$GLOBALS['tv2_workspace_write_failure'] = true;
$unchanged = $controller->update_preferences( new WP_REST_Request( $base_workspace['preferences'] ) );
$GLOBALS['tv2_workspace_write_failure'] = false;
tv2_workspace_assert( $unchanged instanceof WP_REST_Response, 'an unchanged target was reported as a failed write' );
$GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ] = '';
$empty_legacy_save = $controller->save_item( new WP_REST_Request( array( 'kind' => 'hotel', 'external_id' => 'empty-legacy', 'title' => 'Empty legacy' ) ) );
$empty_legacy_clear = $controller->clear_workspace();
tv2_workspace_assert( is_wp_error( $empty_legacy_save ) && 'tra_vel_workspace_conflict' === $empty_legacy_save->get_error_code(), 'existing empty legacy meta received an unqualified update' );
tv2_workspace_assert( is_wp_error( $empty_legacy_clear ) && 'tra_vel_workspace_conflict' === $empty_legacy_clear->get_error_code(), 'existing empty legacy meta received an unqualified delete' );
tv2_workspace_assert( '' === $GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ], 'empty legacy meta was mutated without an exact CAS' );
unset( $GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ] );

// Account sync: tombstones win, omitted server records survive, matching
// server watches remain authoritative, and client provenance is downgraded.
$controller->save_item( new WP_REST_Request( array( 'kind' => 'flight', 'external_id' => 'watched-flight', 'title' => 'Original flight', 'href' => '/flights/' ) ) );
$controller->save_item( new WP_REST_Request( array( 'kind' => 'destination', 'external_id' => 'server-only', 'title' => 'Server only', 'href' => '/destinations/' ) ) );
$controller->save_item( new WP_REST_Request( array( 'kind' => 'route', 'external_id' => 'deleted-route', 'title' => 'Deleted route', 'href' => '/travel-map/' ) ) );
$controller->update_watch( new WP_REST_Request( array(), array( 'item_id' => 'flight:watched-flight', 'enabled' => true, 'target_amount' => 700 ) ) );
$synced = $controller->sync_workspace( new WP_REST_Request( array(
	'items' => array(
		array( 'kind' => 'flight', 'external_id' => 'watched-flight', 'title' => 'Updated flight', 'data_mode' => 'live', 'href' => 'https://evil.example/account' ),
		array( 'kind' => 'hotel', 'external_id' => 'local-only', 'title' => 'Local hotel', 'href' => '/hotels/' ),
		array( 'kind' => 'route', 'external_id' => 'deleted-route', 'title' => 'Must stay deleted', 'href' => '/travel-map/' ),
	),
	'deleted_item_ids' => array( 'route:deleted-route' ),
) ) );
tv2_workspace_assert( $synced instanceof WP_REST_Response && 200 === $synced->status, 'valid account sync was rejected' );
tv2_workspace_assert( 'private, no-store, max-age=0' === $synced->headers['Cache-Control'], 'account sync response is cacheable' );
tv2_workspace_assert( 'noindex, nofollow' === $synced->headers['X-Robots-Tag'], 'account sync response is indexable' );
tv2_workspace_assert( null === tv2_workspace_item_by_id( $synced->data, 'route:deleted-route' ), 'tombstoned item was resurrected from the submitted list' );
tv2_workspace_assert( null !== tv2_workspace_item_by_id( $synced->data, 'destination:server-only' ), 'unrelated server item was deleted by sync' );
$synced_watch = tv2_workspace_item_by_id( $synced->data, 'flight:watched-flight' );
tv2_workspace_assert( true === $synced_watch['watch']['enabled'] && 700.0 === (float) $synced_watch['watch']['target_amount'], 'server watch was overwritten by browser sync' );
tv2_workspace_assert( 'mixed' === $synced_watch['data_mode'], 'browser asserted live provenance survived sync' );
tv2_workspace_assert( 'https://tra-vel.co.il/' === $synced_watch['href'], 'external sync URL survived account sanitization' );

$live_save = $controller->save_item( new WP_REST_Request( array( 'kind' => 'hotel', 'external_id' => 'claimed-live', 'title' => 'Claimed live', 'data_mode' => 'live', 'href' => '/hotels/' ) ) );
tv2_workspace_assert( 'mixed' === $live_save->data['items'][0]['data_mode'], 'single-item endpoint retained browser asserted live provenance' );

$too_many_items = array();
for ( $index = 0; $index < 51; $index++ ) {
	$too_many_items[] = array( 'kind' => 'hotel', 'external_id' => 'sync-' . $index, 'title' => 'Sync ' . $index );
}
$item_limit = $controller->sync_workspace( new WP_REST_Request( array( 'items' => $too_many_items ) ) );
tv2_workspace_assert( is_wp_error( $item_limit ) && 'tra_vel_workspace_invalid_sync_items' === $item_limit->get_error_code(), 'sync accepted more than 50 submitted items' );

$too_many_tombstones = array();
for ( $index = 0; $index < 51; $index++ ) $too_many_tombstones[] = 'hotel:deleted-' . $index;
$tombstone_limit = $controller->sync_workspace( new WP_REST_Request( array( 'items' => array(), 'deleted_item_ids' => $too_many_tombstones ) ) );
tv2_workspace_assert( is_wp_error( $tombstone_limit ) && 'tra_vel_workspace_invalid_tombstones' === $tombstone_limit->get_error_code(), 'sync accepted more than 50 tombstones' );

$malformed_item = $controller->sync_workspace( new WP_REST_Request( array( 'items' => array( 'not-an-object' ) ) ) );
tv2_workspace_assert( is_wp_error( $malformed_item ) && 'tra_vel_workspace_invalid_item' === $malformed_item->get_error_code(), 'sync accepted a malformed item' );
$unknown_field = $controller->sync_workspace( new WP_REST_Request( array( 'items' => array(), 'passport' => 'secret' ) ) );
tv2_workspace_assert( is_wp_error( $unknown_field ) && 'tra_vel_workspace_unknown_sync_field' === $unknown_field->get_error_code(), 'sync accepted an undeclared field' );
$bad_tombstone = $controller->sync_workspace( new WP_REST_Request( array( 'items' => array(), 'deleted_item_ids' => array( '../other-user' ) ) ) );
tv2_workspace_assert( is_wp_error( $bad_tombstone ) && 'tra_vel_workspace_invalid_tombstone' === $bad_tombstone->get_error_code(), 'sync accepted an invalid tombstone id' );
$bad_preferences = $controller->sync_workspace( new WP_REST_Request( array( 'items' => array(), 'preferences' => array( 'home_airport' => array( 'TLV' ) ) ) ) );
tv2_workspace_assert( is_wp_error( $bad_preferences ) && 'tra_vel_workspace_invalid_preferences' === $bad_preferences->get_error_code(), 'sync accepted malformed preferences' );

// A full account plus one local-only item must conflict rather than evicting
// an unrelated server record to make room.
$full_items = array();
for ( $index = 0; $index < 50; $index++ ) {
	$full_items[] = array(
		'id' => 'hotel:server-cap-' . $index, 'kind' => 'hotel', 'external_id' => 'server-cap-' . $index, 'title' => 'Server cap ' . $index,
		'subtitle' => '', 'destination' => '', 'route' => '', 'price_label' => '', 'price_amount' => 0, 'currency' => 'USD', 'data_mode' => 'demo',
		'href' => '/hotels/', 'saved_at' => '2030-04-01T10:00:00+00:00', 'watch' => array( 'enabled' => false, 'target_amount' => 0, 'delivery_enabled' => false, 'status' => 'off' ),
	);
}
$GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ] = array(
	'version' => 1, 'items' => $full_items,
	'preferences' => array( 'home_airport' => 'TLV', 'currency' => 'USD', 'budget' => 0, 'max_stops' => 1, 'party_style' => 'couple', 'priorities' => array( 'price' ) ),
	'meta' => array(),
);
$capacity = $controller->sync_workspace( new WP_REST_Request( array( 'items' => array( array( 'kind' => 'hotel', 'external_id' => 'local-overflow', 'title' => 'Local overflow' ) ) ) ) );
tv2_workspace_assert( is_wp_error( $capacity ) && 'tra_vel_workspace_sync_capacity' === $capacity->get_error_code(), 'sync evicted a server-only item when the merged workspace exceeded 50' );
tv2_workspace_assert( 50 === count( $GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ]['items'] ), 'capacity conflict mutated the full server workspace' );

// Legacy user meta is treated as hostile input on every read. Invalid records
// disappear, valid records are normalized, and delivery can never be enabled.
$GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ] = array(
	'version' => 999,
	'items' => array(
		array(
			'kind' => 'hotel', 'external_id' => 'legacy-hotel', 'title' => '<b>Legacy hotel</b>', 'href' => 'https://evil.example/legacy',
			'data_mode' => 'live', 'saved_at' => 'not-a-date',
			'watch' => array( 'enabled' => true, 'target_amount' => 350, 'delivery_enabled' => true, 'status' => 'sent' ),
		),
		array(
			'kind' => 'hotel', 'external_id' => 'legacy-relative-date', 'title' => 'Relative date hotel', 'href' => '/hotels/',
			'data_mode' => 'editorial', 'saved_at' => 'tomorrow',
			'watch' => array( 'enabled' => false, 'target_amount' => 0, 'delivery_enabled' => false, 'status' => 'off' ),
		),
		array( 'kind' => 'passport', 'external_id' => 'secret', 'title' => 'Secret' ),
		'not-an-item',
	),
	'preferences' => array( 'home_airport' => '123', 'currency' => 'BTC', 'budget' => -25, 'max_stops' => 99, 'party_style' => 'unknown', 'priorities' => array( 'price', 'malicious' ) ),
	'meta' => array( 'sensitive_data_allowed' => true ),
);
$legacy = $controller->get_workspace();
tv2_workspace_assert( array( 'version', 'items', 'preferences', 'meta' ) === array_keys( $legacy->data ), 'legacy read changed the public v1 response shape' );
tv2_workspace_assert( 1 === $legacy->data['version'] && 2 === count( $legacy->data['items'] ), 'legacy read trusted an invalid version or item' );
$legacy_item = $legacy->data['items'][0];
tv2_workspace_assert( 'Legacy hotel' === $legacy_item['title'], 'legacy title was not sanitized' );
tv2_workspace_assert( 'https://tra-vel.co.il/' === $legacy_item['href'], 'legacy external URL survived revalidation' );
tv2_workspace_assert( 'mixed' === $legacy_item['data_mode'], 'legacy browser live claim survived revalidation' );
tv2_workspace_assert( '2030-04-01T10:00:00+00:00' === $legacy_item['saved_at'], 'invalid legacy timestamp survived revalidation' );
tv2_workspace_assert( true === $legacy_item['watch']['enabled'] && false === $legacy_item['watch']['delivery_enabled'] && 'awaiting_live_supplier' === $legacy_item['watch']['status'], 'legacy watch truth state was trusted' );
$relative_date_item = $legacy->data['items'][1];
tv2_workspace_assert( 'tomorrow' !== $relative_date_item['saved_at'] && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $relative_date_item['saved_at'] ), 'parseable legacy timestamp was not normalized to RFC 3339' );
tv2_workspace_assert( 'TLV' === $legacy->data['preferences']['home_airport'] && 'USD' === $legacy->data['preferences']['currency'], 'malformed legacy preferences survived revalidation' );
tv2_workspace_assert( 0 === $legacy->data['preferences']['budget'] && 3 === $legacy->data['preferences']['max_stops'], 'legacy numeric preferences escaped bounds' );
tv2_workspace_assert( false === $legacy->data['meta']['sensitive_data_allowed'], 'legacy meta overrode the fixed privacy contract' );

// Storage failure must fail closed rather than returning a successful private
// workspace that was never persisted.
$before_failed_write = $GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ];
$GLOBALS['tv2_workspace_write_failure'] = true;
$failed_write = $controller->sync_workspace( new WP_REST_Request( array(
	'items' => array( array( 'kind' => 'destination', 'external_id' => 'write-must-fail', 'title' => 'Write must fail' ) ),
	'deleted_item_ids' => array( 'hotel:legacy-hotel' ),
) ) );
$GLOBALS['tv2_workspace_write_failure'] = false;
tv2_workspace_assert( is_wp_error( $failed_write ) && 'tra_vel_workspace_write_failed' === $failed_write->get_error_code(), 'sync reported success after user-meta write failure' );
tv2_workspace_assert( $before_failed_write === $GLOBALS['tv2_workspace_meta'][17][ Tra_Vel_V2_Traveler_Workspace_Controller::META_KEY ], 'failed sync mutated stored user meta' );

echo "Tra-Vel traveler workspace runtime validation passed (auth, bounded sync, no-eviction capacity, tombstones, exact user-meta CAS, legacy hardening, provenance, write failure, private cache policy).\n";
