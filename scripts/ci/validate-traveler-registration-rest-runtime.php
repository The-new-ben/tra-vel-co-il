<?php
/**
 * Adversarial runtime harness for durable progressive traveler registration.
 *
 * It uses a memory persistence double but the production schema/policy and REST
 * controller. No network, vault, supplier, booking, payment, cancellation, or
 * refund operation is available to this harness.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_AGENT_PATH', dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['tvtr_user_id'] = 0;
$GLOBALS['tvtr_routes']  = array();
$GLOBALS['tvtr_can_read']= true;
$GLOBALS['tvtr_evidence_authorized'] = true;

class WP_REST_Controller {
	protected $namespace;
	protected $rest_base;
	protected $schema = null;
}

class WP_REST_Server {
	const READABLE  = 'GET';
	const CREATABLE = 'POST';
	const EDITABLE  = 'POST, PUT, PATCH';
}

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

class WP_REST_Response {
	public $data;
	public $status;
	public $headers = array();
	public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; }
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
	public function get_param( $key ) { return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : ( is_array( $this->json ) && array_key_exists( $key, $this->json ) ? $this->json[ $key ] : null ); }
	public function get_header( $key ) { return isset( $this->headers[ strtolower( $key ) ] ) ? $this->headers[ strtolower( $key ) ] : ''; }
	public function get_json_params() { return $this->json; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['tvtr_routes'][ $namespace . $route ] = $args; }
function get_current_user_id() { return (int) $GLOBALS['tvtr_user_id']; }
function current_user_can( $capability ) { return 'read' === $capability && (bool) $GLOBALS['tvtr_can_read']; }
function wp_verify_nonce( $nonce, $action ) { return 'registration-runtime-nonce' === $nonce && 'wp_rest' === $action; }
function home_url( $path = '/' ) { unset( $path ); return 'https://tra-vel.co.il/'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function rest_validate_request_arg() { return true; }
function wp_salt( $scheme = '' ) { return 'registration-runtime-salt-' . $scheme; }
function get_current_blog_id() { return 1; }
function apply_filters( $hook, $value ) {
	if ( 'tra_vel_traveler_registration_verified_evidence_authorized' === $hook ) return (bool) $GLOBALS['tvtr_evidence_authorized'];
	return $value;
}

$vip = TRA_VEL_AGENT_PATH . '/includes/vip/';
require_once $vip . 'class-tra-vel-vip-taxonomy.php';
require_once $vip . 'class-tra-vel-vip-state-machine.php';
require_once $vip . 'class-tra-vel-vip-policy.php';
require_once $vip . 'class-tra-vel-traveler-profile-taxonomy.php';
require_once $vip . 'class-tra-vel-traveler-profile-policy.php';
require_once $vip . 'class-tra-vel-traveler-registration-schema.php';
require_once $vip . 'class-tra-vel-traveler-registration-store.php';

class TVTR_Memory_Store extends Tra_Vel_Traveler_Registration_Store {
	public static $ready = true;
	public $current = array();
	public $revisions = array();
	public $transitions = array();
	public $idempotency = array();
	public $clock;

	public function __construct() {
		$this->clock = time() - 600;
	}

	public static function is_ready() { return self::$ready; }

	public function next_iso() { return gmdate( 'Y-m-d\TH:i:s\Z', $this->clock + 2 ); }

	public function create_registration( $owner_user_id, $input ) {
		$input = Tra_Vel_Traveler_Registration_Schema::create_input( $input );
		if ( is_wp_error( $input ) ) return $input;
		$digest = Tra_Vel_Traveler_Registration_Schema::canonical_digest( $input );
		$replay = $this->replay( $owner_user_id, 'create', $input['idempotency_key'], $digest );
		if ( null !== $replay ) return $replay;
		$evidence = $this->evidence_allowed( $owner_user_id, '', $input );
		if ( true !== $evidence ) return $evidence;
		foreach ( $this->current as $record ) {
			if ( $record['owner'] === (int) $owner_user_id && $record['aggregate']['registration']['trip_ref'] === $input['trip_ref'] ) {
				return new WP_Error( 'tra_vel_traveler_registration_store_trip_already_registered', 'conflict', array( 'status' => 409 ) );
			}
		}
		$this->clock += 2;
		$now = gmdate( 'Y-m-d\TH:i:s\Z', $this->clock );
		$seed = str_pad( (string) ( count( $this->current ) + 1 ), 24, 'x' );
		$ref  = 'tv_registration_' . $seed;
		$registration = array(
			'contract_version' => '1.0.0',
			'registration_ref' => $ref,
			'trip_ref' => $input['trip_ref'],
			'account_ref' => tvtr_ref( 'account', 'owner-' . $owner_user_id ),
			'gate' => 'discover',
			'party_flags' => $input['party_flags'],
			'requirements' => $input['requirements'],
			'role_manifest_ref' => $input['role_manifest_ref'],
			'version' => 1,
			'created_at' => $now,
			'updated_at' => $now,
			'data_boundary' => tvtr_registration_boundary(),
		);
		$aggregate = Tra_Vel_Traveler_Registration_Schema::aggregate( $registration, $input['profile_refs'], $input['vault_item_refs'], $now );
		if ( is_wp_error( $aggregate ) ) return $aggregate;
		$this->current[ $ref ] = array( 'owner' => (int) $owner_user_id, 'aggregate' => $aggregate );
		$this->revisions[ $ref ][1] = $aggregate;
		$scope = $this->scope( $owner_user_id, 'create', $input['idempotency_key'] );
		$this->idempotency[ $scope ] = array( 'digest' => $digest, 'ref' => $ref, 'version' => 1 );
		return array( 'aggregate' => $aggregate, 'transition' => null, 'transition_count' => 0, 'created' => true, 'replayed' => false );
	}

	public function update_registration( $owner_user_id, $registration_ref, $input ) {
		$input = Tra_Vel_Traveler_Registration_Schema::update_input( $input );
		if ( is_wp_error( $input ) ) return $input;
		$digest = Tra_Vel_Traveler_Registration_Schema::canonical_digest( array( 'registration_ref' => $registration_ref, 'input' => $input ) );
		$replay = $this->replay( $owner_user_id, 'update', $input['idempotency_key'], $digest );
		if ( null !== $replay ) return $replay;
		$evidence = $this->evidence_allowed( $owner_user_id, $registration_ref, $input );
		if ( true !== $evidence ) return $evidence;
		$owned = $this->get_owned_registration( $owner_user_id, $registration_ref );
		if ( ! is_array( $owned ) ) return new WP_Error( 'tra_vel_traveler_registration_store_not_found', 'missing', array( 'status' => 404 ) );
		$previous = $owned['aggregate']['registration'];
		if ( $input['expected_version'] !== $previous['version'] ) return new WP_Error( 'tra_vel_traveler_registration_store_version_conflict', 'stale', array( 'status' => 409 ) );
		$this->clock += 2;
		$now = gmdate( 'Y-m-d\TH:i:s\Z', $this->clock );
		$next = $previous;
		$next['gate'] = $input['gate'];
		$next['party_flags'] = $input['party_flags'];
		$next['requirements'] = $input['requirements'];
		$next['role_manifest_ref'] = $input['role_manifest_ref'];
		$next['version']++;
		$next['updated_at'] = $now;
		$aggregate = Tra_Vel_Traveler_Registration_Schema::aggregate( $next, $input['profile_refs'], $input['vault_item_refs'], $now );
		if ( is_wp_error( $aggregate ) ) return $aggregate;
		$deltas = tvtr_deltas( $previous['requirements'], $next['requirements'] );
		if ( ! $deltas['changed'] ) return new WP_Error( 'tra_vel_traveler_registration_store_transition_no_change', 'no change', array( 'status' => 409 ) );
		$transition = array(
			'contract_version' => '1.0.0',
			'transition_ref' => tvtr_ref( 'registration_transition', $registration_ref . '-' . $next['version'] ),
			'registration_ref' => $registration_ref,
			'trip_ref' => $previous['trip_ref'],
			'from_version' => $previous['version'],
			'to_version' => $next['version'],
			'from_gate' => $previous['gate'],
			'to_gate' => $next['gate'],
			'reason' => $input['reason'],
			'changed_requirements' => $deltas['changed'],
			'invalidated_requirements' => $deltas['invalidated'],
			'actor_ref' => tvtr_ref( 'principal', 'owner-' . $owner_user_id ),
			'authority_digest' => tvtr_digest( 'registration-only|' . $owner_user_id . '|' . $digest ),
			'evidence_digest' => tvtr_digest( 'transition-evidence|' . $digest ),
			'occurred_at' => $now,
			'authorization_effect' => 'registration_only',
			'data_boundary' => tvtr_registration_boundary(),
		);
		$valid = Tra_Vel_VIP_Policy::registration_successor( $previous, $next, $transition, $now );
		if ( is_wp_error( $valid ) ) return $valid;
		$this->current[ $registration_ref ]['aggregate'] = $aggregate;
		$this->revisions[ $registration_ref ][ $next['version'] ] = $aggregate;
		$this->transitions[ $registration_ref ][ $next['version'] ] = $transition;
		$scope = $this->scope( $owner_user_id, 'update', $input['idempotency_key'] );
		$this->idempotency[ $scope ] = array( 'digest' => $digest, 'ref' => $registration_ref, 'version' => $next['version'] );
		return array( 'aggregate' => $aggregate, 'transition' => $transition, 'transition_count' => $next['version'] - 1, 'created' => false, 'replayed' => false );
	}

	public function get_owned_registration( $owner_user_id, $registration_ref ) {
		if ( ! isset( $this->current[ $registration_ref ] ) || $this->current[ $registration_ref ]['owner'] !== (int) $owner_user_id ) return null;
		$aggregate = $this->current[ $registration_ref ]['aggregate'];
		return array( 'aggregate' => $aggregate, 'transition_count' => $aggregate['registration']['version'] - 1 );
	}

	private function replay( $owner, $operation, $key, $digest ) {
		$scope = $this->scope( $owner, $operation, $key );
		if ( ! isset( $this->idempotency[ $scope ] ) ) return null;
		$prior = $this->idempotency[ $scope ];
		if ( ! hash_equals( $prior['digest'], $digest ) ) return new WP_Error( 'tra_vel_traveler_registration_store_idempotency_conflict', 'conflict', array( 'status' => 409 ) );
		$aggregate = $this->revisions[ $prior['ref'] ][ $prior['version'] ];
		$transition = $prior['version'] > 1 ? $this->transitions[ $prior['ref'] ][ $prior['version'] ] : null;
		return array( 'aggregate' => $aggregate, 'transition' => $transition, 'transition_count' => $prior['version'] - 1, 'created' => false, 'replayed' => true );
	}

	private function evidence_allowed( $owner, $registration_ref, $input ) {
		$verified = array_values( array_filter( $input['requirements'], static function ( $item ) { return 'verified' === $item['status']; } ) );
		if ( ! $verified ) return true;
		$allowed = apply_filters( 'tra_vel_traveler_registration_verified_evidence_authorized', false, array( 'owner_user_id' => (int) $owner, 'registration_ref' => $registration_ref, 'verified_requirements' => $verified ) );
		return true === $allowed ? true : new WP_Error( 'tra_vel_traveler_registration_store_verified_evidence_unattested', 'unattested', array( 'status' => 409 ) );
	}

	private function scope( $owner, $operation, $key ) { return (int) $owner . '|' . $operation . '|' . $key; }
}

require_once $vip . 'class-tra-vel-traveler-registration-controller.php';

$assertions = 0;
function tvtr_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "Traveler registration REST runtime failed: {$message}\n" );
		exit( 1 );
	}
}
function tvtr_error( $value, $code, $message ) {
	tvtr_assert( is_wp_error( $value ) && $code === $value->get_error_code(), $message . ( is_wp_error( $value ) ? ' (got ' . $value->get_error_code() . ')' : '' ) );
}
function tvtr_ref( $kind, $seed ) { return 'tv_' . $kind . '_' . substr( hash( 'sha256', $seed ), 0, 32 ); }
function tvtr_digest( $seed ) { return hash( 'sha256', $seed ); }
function tvtr_registration_boundary() {
	return array( 'raw_identity_data_exposed' => false, 'raw_payment_data_exposed' => false, 'raw_medical_data_exposed' => false, 'raw_provider_payload_exposed' => false, 'bearer_secret_exposed' => false );
}
function tvtr_flags() { return array( 'minor_present' => true, 'dependent_adult_present' => true, 'accessibility_required' => true ); }
function tvtr_requirement( $code, $status = 'self_asserted', $verified_at = null ) {
	return array( 'code' => $code, 'status' => $status, 'evidence_digest' => 'verified' === $status ? tvtr_digest( 'evidence-' . $code . '-' . $verified_at ) : null, 'verified_at' => 'verified' === $status ? $verified_at : null );
}
function tvtr_map( $items ) {
	$map = array();
	foreach ( $items as $item ) $map[ $item['code'] ] = $item;
	return $map;
}
function tvtr_for_gate( $current, $gate, $flags, $verified_at, $omit = array() ) {
	$map = tvtr_map( $current );
	$verified = array( 'contact_verified', 'reachable_contact_verified', 'travel_document_snapshot_verified', 'guardian_authority_verified', 'dependent_authority_verified', 'payment_session_owner_verified', 'payment_state_sufficient', 'supplier_confirmation_verified', 'mandatory_traveler_data_accepted', 'traveler_manifest_snapshot_verified', 'document_admissibility_current', 'accessibility_supplier_acknowledged', 'service_contact_pack_offline', 'dependency_health_checked' );
	foreach ( Tra_Vel_VIP_Policy::requirements_for_gate( $gate, $flags ) as $code ) {
		if ( in_array( $code, $omit, true ) ) continue;
		if ( ! isset( $map[ $code ] ) ) $map[ $code ] = tvtr_requirement( $code, in_array( $code, $verified, true ) ? 'verified' : 'self_asserted', $verified_at );
	}
	$ordered = array();
	foreach ( Tra_Vel_VIP_Taxonomy::REGISTRATION_REQUIREMENTS as $code ) if ( isset( $map[ $code ] ) ) $ordered[] = $map[ $code ];
	return $ordered;
}
function tvtr_deltas( $before, $after ) {
	$a = tvtr_map( $before ); $b = tvtr_map( $after );
	$codes = array_values( array_unique( array_merge( array_keys( $a ), array_keys( $b ) ) ) );
	$rank = array( 'not_applicable' => 0, 'self_asserted' => 1, 'verified' => 2 );
	$changed = array(); $invalidated = array();
	foreach ( $codes as $code ) {
		$prior = isset( $a[ $code ] ) ? $a[ $code ] : null; $next = isset( $b[ $code ] ) ? $b[ $code ] : null;
		if ( $prior !== $next ) $changed[] = $code;
		if ( null !== $prior && ( null === $next || $rank[ $next['status'] ] < $rank[ $prior['status'] ] ) ) $invalidated[] = $code;
	}
	sort( $changed ); sort( $invalidated );
	return array( 'changed' => $changed, 'invalidated' => $invalidated );
}
function tvtr_headers( $origin = 'https://tra-vel.co.il', $nonce = 'registration-runtime-nonce' ) { return array( 'Origin' => $origin, 'X-WP-Nonce' => $nonce ); }
function tvtr_create_body( $key ) {
	return array(
		'trip_ref' => tvtr_ref( 'trip', 'runtime-trip' ),
		'role_manifest_ref' => tvtr_ref( 'manifest', 'runtime-manifest-1' ),
		'profile_refs' => array( tvtr_ref( 'profile', 'adult-profile' ) ),
		'vault_item_refs' => array( tvtr_ref( 'vault_item', 'intent-vault' ) ),
		'party_flags' => tvtr_flags(),
		'requirements' => array( tvtr_requirement( 'intent_recorded' ), tvtr_requirement( 'travel_window_recorded' ), tvtr_requirement( 'party_size_recorded' ) ),
		'idempotency_key' => $key,
	);
}
function tvtr_update_body( $current, $gate, $reason, $requirements, $key, $manifest = null ) {
	return array(
		'expected_version' => $current['registration']['version'],
		'gate' => $gate,
		'reason' => $reason,
		'role_manifest_ref' => null === $manifest ? $current['registration']['role_manifest_ref'] : $manifest,
		'profile_refs' => $current['bindings']['profile_refs'],
		'vault_item_refs' => $current['bindings']['vault_item_refs'],
		'party_flags' => $current['registration']['party_flags'],
		'requirements' => $requirements,
		'idempotency_key' => $key,
	);
}

$store = new TVTR_Memory_Store();
$controller = new Tra_Vel_Traveler_Registration_Controller( $store );
$controller->register_routes();
$collection_route = 'tra-vel-agent/v1/traveler-registrations';
$item_route = 'tra-vel-agent/v1/traveler-registrations/(?P<registration_ref>tv_registration_[A-Za-z0-9_-]{16,96})';
tvtr_assert( isset( $GLOBALS['tvtr_routes'][ $collection_route ], $GLOBALS['tvtr_routes'][ $item_route ], $GLOBALS['tvtr_routes']['tra-vel-agent/v1/schema/traveler-registration-resource'] ), 'required create/item/schema routes were not registered' );
tvtr_assert( WP_REST_Server::CREATABLE === $GLOBALS['tvtr_routes'][ $collection_route ]['methods'], 'collection route is not create-only' );
tvtr_assert( 2 === count( $GLOBALS['tvtr_routes'][ $item_route ] ) && WP_REST_Server::READABLE === $GLOBALS['tvtr_routes'][ $item_route ][0]['methods'] && WP_REST_Server::EDITABLE === $GLOBALS['tvtr_routes'][ $item_route ][1]['methods'], 'item route does not separate owner read and edit endpoints' );

$key = 'registration-create-runtime-0001';
$body = tvtr_create_body( $key );
$create_request = new WP_REST_Request( 'POST', array(), tvtr_headers(), $body );
tvtr_error( $controller->can_create( $create_request ), 'tra_vel_traveler_registration_authentication_required', 'logged-out creation was accepted' );
$GLOBALS['tvtr_user_id'] = 17;
tvtr_error( $controller->can_create( new WP_REST_Request( 'POST', array(), array( 'Origin' => 'https://tra-vel.co.il' ), $body ) ), 'tra_vel_traveler_registration_nonce_invalid', 'cookie mutation without the exact REST nonce was accepted' );
tvtr_error( $controller->can_create( new WP_REST_Request( 'POST', array(), tvtr_headers( 'https://evil.example' ), $body ) ), 'tra_vel_traveler_registration_origin_rejected', 'cross-origin registration mutation was accepted' );
tvtr_error( $controller->can_create( new WP_REST_Request( 'POST', array(), tvtr_headers( 'https://tra-vel.co.il:444' ), $body ) ), 'tra_vel_traveler_registration_origin_rejected', 'same-host wrong-port mutation was accepted' );
tvtr_error( $controller->can_create( new WP_REST_Request( 'POST', array(), tvtr_headers( 'https://user:pass@tra-vel.co.il' ), $body ) ), 'tra_vel_traveler_registration_origin_rejected', 'origin with user info was accepted' );
$GLOBALS['tvtr_can_read'] = false;
tvtr_error( $controller->can_create( $create_request ), 'tra_vel_traveler_registration_account_forbidden', 'account without baseline read capability was accepted' );
$GLOBALS['tvtr_can_read'] = true;
tvtr_assert( true === $controller->can_create( $create_request ), 'valid signed-in same-origin nonce was rejected' );

$raw = $body;
$raw['email'] = 'traveler@example.com';
tvtr_error( $controller->create_registration( new WP_REST_Request( 'POST', array(), tvtr_headers(), $raw ) ), 'tra_vel_traveler_registration_create_shape_invalid', 'raw contact field crossed the exact registration boundary' );
$created = $controller->create_registration( $create_request );
tvtr_assert( $created instanceof WP_REST_Response && 201 === $created->status, 'valid discover registration did not return 201' );
$public = $created->data['registration'];
tvtr_assert( 'discover' === $public['gate'] && 1 === $public['version'] && 'account' === $public['ownership'], 'created registration projection is incorrect' );
tvtr_assert( 'registration_only' === $created->data['authorization_effect'] && array() === $created->data['executable_scopes'], 'registration response gained executable authority' );
tvtr_assert( array_values( array_unique( $created->data['side_effects'] ) ) === array( false ), 'registration response claimed a commercial side effect' );
tvtr_assert( false === $public['authorization']['account_ownership_grants_trip_role_authority'], 'account ownership was promoted to guardian/booker/payer authority' );
tvtr_assert( 'private, no-store, max-age=0' === $created->headers['Cache-Control'] && 'noindex, nofollow, noarchive' === $created->headers['X-Robots-Tag'], 'private registration response is cacheable or indexable' );
$serialized = wp_json_encode( $created->data );
foreach ( array( 'evidence_digest', 'profile_refs', 'vault_item_refs', 'account_ref', 'authority_digest', 'payment_authorize', 'service_cancel' ) as $forbidden ) tvtr_assert( false === strpos( $serialized, $forbidden ), "minimized response leaked {$forbidden}" );

$registration_ref = $public['registration_ref'];
$get_request = new WP_REST_Request( 'GET', array( 'registration_ref' => $registration_ref ) );
tvtr_assert( true === $controller->can_read( $get_request ), 'owner could not read registration' );
$read = $controller->get_registration( $get_request );
tvtr_assert( $read instanceof WP_REST_Response && $read->data['registration'] === $public, 'owner read did not return the durable projection' );
$GLOBALS['tvtr_user_id'] = 18;
tvtr_error( $controller->can_read( $get_request ), 'tra_vel_traveler_registration_not_found', 'cross-user registration read was not denied with a non-enumerating response' );
$cross_update = new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), array() );
tvtr_error( $controller->can_update( $cross_update ), 'tra_vel_traveler_registration_not_found', 'cross-user registration update was accepted' );
$GLOBALS['tvtr_user_id'] = 17;

$current = $store->current[ $registration_ref ]['aggregate'];
$bad_personal = tvtr_for_gate( $current['registration']['requirements'], 'personalize', tvtr_flags(), $store->next_iso() );
foreach ( $bad_personal as &$requirement ) if ( 'contact_verified' === $requirement['code'] ) $requirement = tvtr_requirement( 'contact_verified' );
unset( $requirement );
$bad_body = tvtr_update_body( $current, 'personalize', 'progress', $bad_personal, 'registration-bad-readiness-02' );
$bad_request = new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), $bad_body );
tvtr_assert( true === $controller->can_update( $bad_request ), 'owned mutation permission unexpectedly failed' );
tvtr_error( $controller->update_registration( $bad_request ), 'tra_vel_vip_registration_gate_blocked', 'registration advanced past policy readiness' );

$current = $store->current[ $registration_ref ]['aggregate'];
$personal_requirements = tvtr_for_gate( $current['registration']['requirements'], 'personalize', tvtr_flags(), $store->next_iso() );
$GLOBALS['tvtr_evidence_authorized'] = false;
$unattested_body = tvtr_update_body( $current, 'personalize', 'progress', $personal_requirements, 'registration-unattested-0003' );
tvtr_error( $controller->update_registration( new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), $unattested_body ) ), 'tra_vel_traveler_registration_store_verified_evidence_unattested', 'caller-authored digest was treated as verified evidence without an upstream vault/profile attestation' );
$GLOBALS['tvtr_evidence_authorized'] = true;
$personal_body = tvtr_update_body( $current, 'personalize', 'progress', $personal_requirements, 'registration-personalize-0003' );
$personal_request = new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), $personal_body );
tvtr_assert( true === $controller->can_update( $personal_request ), 'valid personalize mutation permission failed' );
$personal = $controller->update_registration( $personal_request );
tvtr_assert( $personal instanceof WP_REST_Response && 2 === $personal->data['registration']['version'] && 'personalize' === $personal->data['registration']['gate'], 'valid personalize successor failed' );
tvtr_assert( 'registration_only' === $personal->data['transition']['authorization_effect'], 'immutable transition gained non-registration authority' );
$replayed = $controller->update_registration( $personal_request );
tvtr_assert( $replayed instanceof WP_REST_Response && true === $replayed->data['replayed'] && 2 === $replayed->data['registration']['version'], 'exact retry did not replay before stale-version validation' );
$changed_replay = $personal_body;
$changed_replay['gate'] = 'ready_to_quote';
tvtr_error( $controller->update_registration( new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), $changed_replay ) ), 'tra_vel_traveler_registration_store_idempotency_conflict', 'same retry key accepted a changed request' );
$stale = $personal_body;
$stale['idempotency_key'] = 'registration-stale-version-004';
tvtr_error( $controller->update_registration( new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), $stale ) ), 'tra_vel_traveler_registration_store_version_conflict', 'fresh key bypassed optimistic version conflict' );

$current = $store->current[ $registration_ref ]['aggregate'];
$quote_missing_dependent = tvtr_for_gate( $current['registration']['requirements'], 'ready_to_quote', tvtr_flags(), $store->next_iso(), array( 'dependent_support_plan_recorded' ) );
$quote_missing_body = tvtr_update_body( $current, 'ready_to_quote', 'progress', $quote_missing_dependent, 'registration-dependent-missing-05' );
tvtr_error( $controller->update_registration( new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), $quote_missing_body ) ), 'tra_vel_vip_registration_gate_blocked', 'dependent-adult support gate was bypassed' );
$quote_requirements = tvtr_for_gate( $current['registration']['requirements'], 'ready_to_quote', tvtr_flags(), $store->next_iso() );
$quote_body = tvtr_update_body( $current, 'ready_to_quote', 'progress', $quote_requirements, 'registration-ready-quote-0006' );
$quote = $controller->update_registration( new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), $quote_body ) );
tvtr_assert( $quote instanceof WP_REST_Response && 'ready_to_quote' === $quote->data['registration']['gate'], 'conditional quote readiness failed' );

$current = $store->current[ $registration_ref ]['aggregate'];
$reserve_missing_guardian = tvtr_for_gate( $current['registration']['requirements'], 'ready_to_reserve', tvtr_flags(), $store->next_iso(), array( 'guardian_authority_verified' ) );
$reserve_missing_body = tvtr_update_body( $current, 'ready_to_reserve', 'progress', $reserve_missing_guardian, 'registration-guardian-missing-07' );
tvtr_error( $controller->update_registration( new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), $reserve_missing_body ) ), 'tra_vel_vip_registration_gate_blocked', 'minor guardian-authority gate was bypassed' );
$reserve_requirements = tvtr_for_gate( $current['registration']['requirements'], 'ready_to_reserve', tvtr_flags(), $store->next_iso() );
$reserve_body = tvtr_update_body( $current, 'ready_to_reserve', 'progress', $reserve_requirements, 'registration-ready-reserve-08' );
$reserve = $controller->update_registration( new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), $reserve_body ) );
tvtr_assert( $reserve instanceof WP_REST_Response && 'ready_to_reserve' === $reserve->data['registration']['gate'], 'minor/dependent reserve readiness failed with evidence' );

$current = $store->current[ $registration_ref ]['aggregate'];
$fulfill_requirements = tvtr_for_gate( $current['registration']['requirements'], 'ready_to_fulfill', tvtr_flags(), $store->next_iso() );
$fulfill_body = tvtr_update_body( $current, 'ready_to_fulfill', 'progress', $fulfill_requirements, 'registration-ready-fulfill-09' );
$fulfill = $controller->update_registration( new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), $fulfill_body ) );
tvtr_assert( $fulfill instanceof WP_REST_Response && 'ready_to_fulfill' === $fulfill->data['registration']['gate'], 'fulfill readiness failed' );

$current = $store->current[ $registration_ref ]['aggregate'];
$travel_missing_access = tvtr_for_gate( $current['registration']['requirements'], 'ready_to_travel', tvtr_flags(), $store->next_iso(), array( 'accessibility_supplier_acknowledged' ) );
$travel_missing_body = tvtr_update_body( $current, 'ready_to_travel', 'progress', $travel_missing_access, 'registration-access-missing-010' );
tvtr_error( $controller->update_registration( new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), $travel_missing_body ) ), 'tra_vel_vip_registration_gate_blocked', 'accessibility supplier-acknowledgement gate was bypassed' );
$travel_requirements = tvtr_for_gate( $current['registration']['requirements'], 'ready_to_travel', tvtr_flags(), $store->next_iso() );
$travel_body = tvtr_update_body( $current, 'ready_to_travel', 'progress', $travel_requirements, 'registration-ready-travel-0011' );
$travel = $controller->update_registration( new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), $travel_body ) );
tvtr_assert( $travel instanceof WP_REST_Response && 'ready_to_travel' === $travel->data['registration']['gate'], 'ready-to-travel progression failed with all conditional evidence' );

$current = $store->current[ $registration_ref ]['aggregate'];
$rollback_requirements = tvtr_for_gate( array(), 'ready_to_quote', tvtr_flags(), $store->next_iso() );
$rollback_body = tvtr_update_body( $current, 'ready_to_quote', 'document_change', $rollback_requirements, 'registration-document-rollback-12' );
$rollback = $controller->update_registration( new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), $rollback_body ) );
tvtr_assert( $rollback instanceof WP_REST_Response && 'ready_to_quote' === $rollback->data['registration']['gate'] && in_array( 'travel_document_snapshot_verified', $rollback->data['transition']['invalidated_requirements'], true ), 'document change did not produce explicit downstream rollback/invalidation' );

$current = $store->current[ $registration_ref ]['aggregate'];
$role_change_requirements = $current['registration']['requirements'];
foreach ( $role_change_requirements as &$item ) {
	if ( 'traveler_preferences_recorded' === $item['code'] ) $item = tvtr_requirement( 'traveler_preferences_recorded', 'verified', $store->next_iso() );
}
unset( $item );
$role_change = tvtr_update_body( $current, 'ready_to_quote', 'progress', $role_change_requirements, 'registration-role-promotion-013', tvtr_ref( 'manifest', 'another-manifest' ) );
tvtr_error( $controller->update_registration( new WP_REST_Request( 'PATCH', array( 'registration_ref' => $registration_ref ), tvtr_headers(), $role_change ) ), 'tra_vel_vip_registration_transition_progress_invalid', 'account owner changed role manifest through a progress transition' );

TVTR_Memory_Store::$ready = false;
tvtr_error( $controller->can_create( $create_request ), 'tra_vel_traveler_registration_store_unavailable', 'missing schema/tables did not fail closed' );
TVTR_Memory_Store::$ready = true;

$schema = $controller->get_schema();
tvtr_assert( $schema instanceof WP_REST_Response && 'https://tra-vel.co.il/schemas/traveler-registration-resource-v1.schema.json' === $schema->data['$id'], 'resource schema endpoint is unavailable or drifted' );

echo "Traveler registration REST runtime passed ({$assertions} assertions; owner isolation, exact CSRF boundary, idempotency/versioning, conditional readiness, rollback, zero commercial authority).\n";
