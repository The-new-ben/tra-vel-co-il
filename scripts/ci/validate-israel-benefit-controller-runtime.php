<?php
/**
 * Focused runtime checks for the Israeli benefit REST bridge.
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

class WP_REST_Controller {
	public $namespace;
	public $rest_base;
}
class WP_REST_Server {
	const READABLE  = 'GET';
	const CREATABLE = 'POST';
}
class WP_REST_Response {
	public $data;
	public $status;
	public $headers = array();
	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
	public function header( $name, $value ) { $this->headers[ $name ] = $value; }
}
class WP_REST_Request {
	private $method;
	private $params;
	private $headers;
	private $json;
	public function __construct( $method, $params = array(), $headers = array(), $json = null ) {
		$this->method  = $method;
		$this->params  = $params;
		$this->headers = array_change_key_case( $headers, CASE_LOWER );
		$this->json    = null === $json ? $params : $json;
	}
	public function get_method() { return $this->method; }
	public function get_param( $key ) { return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null; }
	public function get_header( $key ) { $key = strtolower( $key ); return isset( $this->headers[ $key ] ) ? $this->headers[ $key ] : ''; }
	public function get_json_params() { return $this->json; }
}

$GLOBALS['israel_benefit_routes'] = array();
$GLOBALS['israel_benefit_user_id'] = 0;
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['israel_benefit_routes'][] = array( $namespace, $route, $args ); }
function rest_ensure_response( $value ) { return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value, 200 ); }
function home_url( $path = '/' ) { return 'https://tra-vel.co.il' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function get_current_user_id() { return (int) $GLOBALS['israel_benefit_user_id']; }
function wp_verify_nonce( $nonce, $action ) { return 'valid-rest-nonce' === $nonce && 'wp_rest' === $action; }
function rest_validate_request_arg() { return true; }
function __return_true() { return true; }

$commerce = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/';
require_once $commerce . 'class-tra-vel-benefit-taxonomy.php';
require_once $commerce . 'class-tra-vel-benefit-policy.php';
require_once $commerce . 'class-tra-vel-israel-benefit-catalog-registry.php';
require_once $commerce . 'class-tra-vel-israel-benefit-controller.php';

$assertions = 0;
function israel_benefit_controller_assert( $condition, $message ) {
	global $assertions;
	++$assertions;
	if ( ! $condition ) {
		fwrite( STDERR, "Israel benefit controller runtime failed: {$message}\n" );
		exit( 1 );
	}
}

function israel_benefit_controller_has_key( $value, $needle ) {
	if ( ! is_array( $value ) ) {
		return false;
	}
	foreach ( $value as $key => $child ) {
		if ( $needle === $key || israel_benefit_controller_has_key( $child, $needle ) ) {
			return true;
		}
	}
	return false;
}

$fixture  = __DIR__ . '/../../plugin/tra-vel-agent-core/assets/fixtures/israel-benefit-catalog.json';
$registry = new Tra_Vel_Israel_Benefit_Catalog_Registry( $fixture );
$clock    = static function () { return '2026-07-19T12:00:00Z'; };
$controller = new Tra_Vel_Israel_Benefit_Controller( $registry, $clock );
$controller->register_routes();

israel_benefit_controller_assert( 2 === count( $GLOBALS['israel_benefit_routes'] ), 'exactly two benefit routes must register' );
$options_route = $GLOBALS['israel_benefit_routes'][0];
$plan_route    = $GLOBALS['israel_benefit_routes'][1];
israel_benefit_controller_assert( 'tra-vel-agent/v1' === $options_route[0] && '/benefits/israel/options' === $options_route[1], 'options route identity must be exact' );
israel_benefit_controller_assert( WP_REST_Server::READABLE === $options_route[2]['methods'], 'options route must be read-only' );
israel_benefit_controller_assert( '__return_true' === $options_route[2]['permission_callback'], 'public options must still declare an explicit permission callback' );
israel_benefit_controller_assert( '/benefits/israel/plan' === $plan_route[1] && WP_REST_Server::CREATABLE === $plan_route[2]['methods'], 'plan route must be an explicit POST' );
israel_benefit_controller_assert( array( $controller, 'can_plan' ) === $plan_route[2]['permission_callback'], 'plan route must use the same-site permission gate' );
israel_benefit_controller_assert( 8 === count( $plan_route[2]['args'] ), 'plan route must expose the exact eight-axis request contract' );

$options = $controller->get_options();
israel_benefit_controller_assert( $options instanceof WP_REST_Response && 200 === $options->status, 'reviewed public options must return a REST response' );
israel_benefit_controller_assert( 'public, max-age=300, stale-while-revalidate=300' === $options->headers['Cache-Control'], 'identity catalogue must use bounded public caching' );
israel_benefit_controller_assert( 3 === count( $options->data['options']['airline_inventory'] ), 'options must expose three independent airline inventory identities' );
israel_benefit_controller_assert( in_array( 'airline_israir', array_column( $options->data['options']['airline_inventory'], 'airline_inventory_id' ), true ), 'public options must expose the stable Israir identity without creating a loyalty program' );
israel_benefit_controller_assert( 3 === count( $options->data['options']['programs'] ), 'options must expose three independent loyalty programs' );
israel_benefit_controller_assert( 8 === count( $options->data['options']['credential_products'] ), 'options must expose exact card products, not a generic FLY CARD family' );
israel_benefit_controller_assert( 4 === count( $options->data['options']['payment_networks'] ), 'options must keep payment rails independent' );
israel_benefit_controller_assert( ! israel_benefit_controller_has_key( $options->data, 'source' ), 'public options must not leak source evidence payloads' );
israel_benefit_controller_assert( ! israel_benefit_controller_has_key( $options->data, 'official_source_url' ), 'public options must not leak source URLs through nested relationships' );
foreach ( $options->data['commercial_truth'] as $value ) {
	israel_benefit_controller_assert( false === $value, 'every public commercial-truth flag must remain false' );
}

$same_site = new WP_REST_Request( 'POST', array(), array( 'Origin' => 'https://tra-vel.co.il' ) );
israel_benefit_controller_assert( true === $controller->can_plan( $same_site ), 'anonymous same-site planner must be allowed without registration' );
$cross_site = new WP_REST_Request( 'POST', array(), array( 'Origin' => 'https://attacker.example' ) );
$cross_result = $controller->can_plan( $cross_site );
israel_benefit_controller_assert( is_wp_error( $cross_result ) && 'tra_vel_israel_benefit_origin_rejected' === $cross_result->get_error_code(), 'cross-site planning must be rejected' );
$userinfo = new WP_REST_Request( 'POST', array(), array( 'Origin' => 'https://user:pass@tra-vel.co.il' ) );
israel_benefit_controller_assert( is_wp_error( $controller->can_plan( $userinfo ) ), 'origins containing user information must be rejected' );

$GLOBALS['israel_benefit_user_id'] = 7;
israel_benefit_controller_assert( is_wp_error( $controller->can_plan( $same_site ) ), 'signed-in planning must require a REST nonce' );
$signed = new WP_REST_Request( 'POST', array(), array( 'Origin' => 'https://tra-vel.co.il', 'X-WP-Nonce' => 'valid-rest-nonce' ) );
israel_benefit_controller_assert( true === $controller->can_plan( $signed ), 'a valid signed-in same-site request must pass' );
$GLOBALS['israel_benefit_user_id'] = 0;

$plan_payload = array(
	'airline_inventory_id' => null,
	'program_id' => 'program_elal_matmid',
	'credential_product_id' => 'credential_isracard_fly_card_mastercard',
	'payment_network_id' => 'network_mastercard',
	'redemption_portal_id' => 'portal_elal_matmid',
	'campaign_id' => 'campaign_isracard_flycard_join_2026',
	'campaign_version' => 1,
	'eligibility_claim' => 'exact_product_customer_asserted',
);
$plan_request = new WP_REST_Request( 'POST', $plan_payload, array( 'Origin' => 'https://tra-vel.co.il' ), $plan_payload );
$plan = $controller->create_plan( $plan_request );
israel_benefit_controller_assert( $plan instanceof WP_REST_Response && 200 === $plan->status, 'exact linked axes must produce a planning response' );
israel_benefit_controller_assert( 'likely_customer_asserted' === $plan->data['decision_state'], 'customer assertion must remain an unverified planning state' );
israel_benefit_controller_assert( 'connect_matmid_or_enter_planning_balance' === $plan->data['next_action']['code'], 'planner must return the smallest concrete next action' );
israel_benefit_controller_assert( false === $plan->data['side_effect_executed'], 'planning must execute no supplier or financial side effect' );
israel_benefit_controller_assert( 'private, no-store, max-age=0' === $plan->headers['Cache-Control'], 'customer-asserted plan must not be cached' );
foreach ( $plan->data['commercial_truth'] as $value ) {
	israel_benefit_controller_assert( false === $value, 'planning must not infer commercial truth' );
}

$visa_payload = array_fill_keys( array_keys( $plan_payload ), null );
$visa_payload['payment_network_id'] = 'network_visa';
$visa_payload['eligibility_claim']  = 'generic_visa_eligible';
$visa = $controller->create_plan( new WP_REST_Request( 'POST', $visa_payload, array( 'Origin' => 'https://tra-vel.co.il' ), $visa_payload ) );
israel_benefit_controller_assert( is_wp_error( $visa ) && 'tra_vel_israel_benefit_generic_visa_eligibility_forbidden' === $visa->get_error_code(), 'generic Visa identity must never imply eligibility' );

$unknown_payload = $plan_payload;
$unknown_payload['raw_card_number'] = '4111111111111111';
$unknown = $controller->create_plan( new WP_REST_Request( 'POST', $unknown_payload, array( 'Origin' => 'https://tra-vel.co.il' ), $unknown_payload ) );
israel_benefit_controller_assert( is_wp_error( $unknown ) && 'tra_vel_israel_benefit_plan_unknown_field' === $unknown->get_error_code(), 'unknown or secret-bearing request fields must fail closed' );

$stale_controller = new Tra_Vel_Israel_Benefit_Controller(
	new Tra_Vel_Israel_Benefit_Catalog_Registry( $fixture ),
	static function () { return '2026-07-21T12:00:00Z'; }
);
$stale = $stale_controller->get_options();
israel_benefit_controller_assert( $stale instanceof WP_REST_Response, 'stable benefit identities must remain visible when source review expires' );
israel_benefit_controller_assert( 'stale' === $stale->data['review']['state'] && 'source_refresh_required' === $stale->data['review']['planning_state'], 'stale source review must be explicit and require refresh before planning' );
foreach ( $stale->data['commercial_truth'] as $value ) {
	israel_benefit_controller_assert( false === $value, 'stale identity display must not gain any commercial truth' );
}

echo "Israeli benefit controller runtime passed ({$assertions} assertions; exact axes; no live commercial claim).\n";
