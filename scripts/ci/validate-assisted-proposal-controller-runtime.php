<?php
/**
 * Deterministic REST fixtures for the assisted-proposal controller boundary.
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) );
define( 'TRA_VEL_AGENT_PATH', dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core' );
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message = '', $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function esc_url_raw( $value, $protocols = null ) { return 0 === strpos( (string) $value, 'https://' ) ? (string) $value : ''; }
function wp_generate_uuid4() { static $counter = 0; $counter++; return $counter % 2 ? 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' : 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'; }
function wp_salt( $scheme = 'auth' ) { return 'assisted-proposal-controller-runtime-salt-' . $scheme; }
function rest_sanitize_boolean( $value ) { return filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }
function rest_validate_request_arg() { return true; }
function __return_true() { return true; }

class WP_REST_Controller { public $namespace; public $rest_base; }
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; }
class WP_REST_Request {
	private $method;
	private $params;
	private $json;
	private $headers;
	private $route;
	public function __construct( $method = 'GET', $params = array(), $json = null, $headers = array(), $route = '' ) {
		$this->method = $method; $this->params = $params; $this->json = $json; $this->route = $route;
		$this->headers = array();
		foreach ( $headers as $key => $value ) { $this->headers[ strtolower( $key ) ] = $value; }
	}
	public function get_method() { return $this->method; }
	public function get_param( $key ) { return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : ( is_array( $this->json ) && array_key_exists( $key, $this->json ) ? $this->json[ $key ] : null ); }
	public function get_json_params() { return $this->json; }
	public function get_header( $key ) { return $this->headers[ strtolower( $key ) ] ?? ''; }
	public function get_route() { return $this->route; }
}
class WP_REST_Response {
	private $data;
	private $status;
	private $headers = array();
	public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; }
	public function header( $key, $value ) { $this->headers[ $key ] = $value; }
	public function get_headers() { return $this->headers; }
	public function get_data() { return $this->data; }
	public function get_status() { return $this->status; }
}

$tra_vel_registered_routes = array();
$tra_vel_registered_filters = array();
function register_rest_route( $namespace, $route, $args ) { global $tra_vel_registered_routes; $tra_vel_registered_routes[ '/' . $namespace . $route ] = $args; return true; }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { global $tra_vel_registered_filters; $tra_vel_registered_filters[ $hook ][] = array( $callback, $priority, $accepted_args ); return true; }
function rest_ensure_response( $value ) { return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value ); }
function rest_convert_error_to_response( $error ) { $data = $error->get_error_data(); return new WP_REST_Response( array( 'code' => $error->get_error_code() ), is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500 ); }

$tra_vel_current_user_id = 0;
$tra_vel_capabilities = array();
$tra_vel_user_email_available = true;
function get_current_user_id() { global $tra_vel_current_user_id; return $tra_vel_current_user_id; }
function current_user_can( $capability ) { global $tra_vel_capabilities; return ! empty( $tra_vel_capabilities[ $capability ] ); }
function get_userdata( $user_id ) { global $tra_vel_user_email_available; return $user_id > 0 && $tra_vel_user_email_available ? (object) array( 'ID' => $user_id, 'user_email' => 'traveler@example.com' ) : false; }
function is_email( $email ) { return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function home_url() { return 'https://tra-vel.co.il/'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_verify_nonce( $nonce, $action ) { return 'wp_rest' === $action && 'valid-nonce' === $nonce; }

function tra_vel_schema_is_list( $value ) { return is_array( $value ) && ( empty( $value ) || array_keys( $value ) === range( 0, count( $value ) - 1 ) ); }
function rest_validate_value_from_schema( $value, $schema, $param = '' ) {
	if ( ! is_array( $schema ) ) { return true; }
	$type = $schema['type'] ?? null;
	$types = is_array( $type ) ? $type : ( null === $type ? array() : array( $type ) );
	if ( $types ) {
		$matches = false;
		foreach ( $types as $candidate ) {
			$matches = $matches || ( 'object' === $candidate && is_array( $value ) && ! tra_vel_schema_is_list( $value ) )
				|| ( 'array' === $candidate && tra_vel_schema_is_list( $value ) )
				|| ( 'string' === $candidate && is_string( $value ) )
				|| ( 'integer' === $candidate && is_int( $value ) )
				|| ( 'boolean' === $candidate && is_bool( $value ) )
				|| ( 'null' === $candidate && null === $value );
		}
		if ( ! $matches ) { return new WP_Error( 'rest_invalid_type', 'Invalid type.' ); }
	}
	if ( array_key_exists( 'const', $schema ) && $value !== $schema['const'] ) { return new WP_Error( 'rest_invalid_const', 'Invalid constant.' ); }
	if ( isset( $schema['enum'] ) && ! in_array( $value, $schema['enum'], true ) ) { return new WP_Error( 'rest_invalid_enum', 'Invalid enum.' ); }
	if ( is_string( $value ) ) {
		if ( isset( $schema['minLength'] ) && strlen( $value ) < $schema['minLength'] ) { return new WP_Error( 'rest_too_short', 'String too short.' ); }
		if ( isset( $schema['maxLength'] ) && strlen( $value ) > $schema['maxLength'] ) { return new WP_Error( 'rest_too_long', 'String too long.' ); }
		if ( isset( $schema['pattern'] ) && 1 !== preg_match( '~' . str_replace( '~', '\\~', $schema['pattern'] ) . '~', $value ) ) { return new WP_Error( 'rest_invalid_pattern', 'Invalid pattern.' ); }
	}
	if ( is_int( $value ) ) {
		if ( isset( $schema['minimum'] ) && $value < $schema['minimum'] ) { return new WP_Error( 'rest_too_small', 'Integer too small.' ); }
		if ( isset( $schema['maximum'] ) && $value > $schema['maximum'] ) { return new WP_Error( 'rest_too_large', 'Integer too large.' ); }
	}
	if ( is_array( $value ) && ! tra_vel_schema_is_list( $value ) ) {
		foreach ( (array) ( $schema['required'] ?? array() ) as $required ) { if ( ! array_key_exists( $required, $value ) ) { return new WP_Error( 'rest_missing_required', 'Missing required value.' ); } }
		foreach ( $value as $key => $child ) {
			if ( isset( $schema['properties'][ $key ] ) ) { $valid = rest_validate_value_from_schema( $child, $schema['properties'][ $key ], $param . '.' . $key ); if ( is_wp_error( $valid ) ) { return $valid; } }
		}
	}
	if ( tra_vel_schema_is_list( $value ) ) {
		if ( isset( $schema['minItems'] ) && count( $value ) < $schema['minItems'] ) { return new WP_Error( 'rest_too_few_items', 'Too few items.' ); }
		if ( isset( $schema['maxItems'] ) && count( $value ) > $schema['maxItems'] ) { return new WP_Error( 'rest_too_many_items', 'Too many items.' ); }
		if ( ! empty( $schema['uniqueItems'] ) && count( array_unique( array_map( 'serialize', $value ) ) ) !== count( $value ) ) { return new WP_Error( 'rest_duplicate_items', 'Duplicate items.' ); }
		foreach ( $value as $index => $child ) { if ( isset( $schema['items'] ) ) { $valid = rest_validate_value_from_schema( $child, $schema['items'], $param . '[' . $index . ']' ); if ( is_wp_error( $valid ) ) { return $valid; } } }
	}
	return true;
}

class Tra_Vel_Quote_Case_Capabilities {
	const VIEW_CASES = 'tra_vel_view_quote_cases';
	const PUBLISH_PROPOSALS = 'tra_vel_publish_assisted_proposals';
	const INGEST_PROPOSALS = 'tra_vel_ingest_canonical_assisted_proposals';
}

require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-assisted-proposal-policy.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-assisted-proposal-composer.php';

function tra_vel_controller_source( $now ) {
	return array(
		'contract_version' => '1.0.0', 'source_id' => '123e4567-e89b-42d3-a456-426614174000',
		'provider_code' => 'fixture-provider', 'source_type' => 'connected_api', 'relationship' => 'operator_attested',
		'public_label' => 'Current contracted-provider response', 'supplier_name' => 'Fixture Air', 'seller_name' => 'Fixture Seller',
		'source_reference' => 'SRC:FIXTURE-1', 'source_url' => null, 'observed_at' => gmdate( 'c', $now ),
		'fresh_until' => gmdate( 'c', $now + 3600 ), 'evidence_digest' => str_repeat( 'a', 64 ), 'requires_revalidation' => true,
	);
}
function tra_vel_controller_proposal( $case_uuid, $source, $now ) {
	$component = array(
		'component_key' => 'outbound-flight', 'category' => 'flights', 'title' => 'Sourced flight option',
		'description' => 'A validated option for review before a final personal quote.',
		'price' => array( 'priced' => true, 'total_for_party_minor' => 123400, 'currency' => 'ILS', 'basis' => 'trip_total', 'taxes' => 'included', 'fees' => 'included' ),
		'conditions' => array( 'cancellation' => 'Revalidate before authorization.', 'changes' => 'Changes require a new quote.', 'baggage_or_inclusions' => 'The cited response controls inclusions.' ),
		'source_ids' => array( $source['source_id'] ), 'requires_revalidation' => true,
	);
	$ledger = Tra_Vel_Assisted_Proposal_Policy::compute_ledger( array( $component ) );
	return array(
		'contract_version' => '1.0.0', 'proposal_id' => '123e4567-e89b-42d3-a456-426614174001', 'case_id' => $case_uuid,
		'reference' => 'TVP-ABCDEFGH', 'status' => 'available', 'version' => 1, 'revision' => 1, 'published_revision' => 1,
		'position' => 'best_value', 'addresses' => array( 'case_revision' => 2, 'request_digest' => str_repeat( 'b', 64 ) ),
		'title' => 'Personal sourced proposal', 'summary' => 'A non-binding proposal for traveler review.',
		'why_it_fits' => array( 'It addresses the current request.' ), 'trade_offs' => array( 'Commercial facts require final revalidation.' ),
		'route' => array( 'origin' => 'Tel Aviv', 'destinations' => array( 'Athens' ), 'legs' => array() ),
		'itinerary' => array( array( 'day' => 1, 'place' => 'Athens', 'title' => 'Arrival', 'component_keys' => array( 'outbound-flight' ) ) ),
		'components' => array( $component ), 'ledger' => $ledger, 'sources' => array( $source ),
		'source_set_digest' => Tra_Vel_Assisted_Proposal_Policy::source_set_digest( array( $source ) ),
		'freshness' => array( 'checked_at' => gmdate( 'c', $now ), 'expires_at' => gmdate( 'c', $now + 3600 ), 'requires_revalidation' => true ),
		'unresolved_items' => array( array( 'code' => 'availability_revalidation', 'label' => 'Availability requires a new source check.' ) ),
		'traveler_disposition' => 'awaiting_review', 'next_actions' => Tra_Vel_Assisted_Proposal_Policy::traveler_actions_for( 'available', 'awaiting_review' ),
		'disclosure' => array( 'commercial_state' => 'non_binding_assisted_proposal', 'final_quote_required' => true, 'message' => Tra_Vel_Assisted_Proposal_Policy::FINAL_QUOTE_DISCLOSURE ),
		'created_at' => gmdate( 'c', $now - 60 ), 'published_at' => gmdate( 'c', $now ), 'expires_at' => gmdate( 'c', $now + 3600 ),
	);
}

function tra_vel_controller_composition() {
	return array(
		'position' => 'best_value', 'title' => 'Server-composed option', 'summary' => 'A sourced non-binding option for review.',
		'why_it_fits' => array( 'It matches the current route.' ), 'trade_offs' => array( 'Final revalidation is required.' ),
		'route' => array( 'origin' => 'Tel Aviv', 'destinations' => array( 'Athens' ), 'legs' => array() ),
		'itinerary' => array( array( 'day' => 1, 'place' => 'Athens', 'title' => 'Arrival', 'component_keys' => array( 'flight-option' ) ) ),
		'components' => array(
			array(
				'component_key' => 'flight-option', 'category' => 'flights', 'title' => 'Flight option',
				'description' => 'A provider-backed option requiring final revalidation.',
				'price' => array( 'priced' => false, 'total_for_party_minor' => null, 'currency' => null, 'basis' => 'not_priced', 'taxes' => 'unknown', 'fees' => 'unknown' ),
				'conditions' => array( 'cancellation' => 'Confirm in final quote.', 'changes' => 'Confirm in final quote.', 'baggage_or_inclusions' => 'Confirm in final quote.' ),
				'source_indexes' => array( 0 ),
			),
		),
		'sources' => array(
			array(
				'provider_code' => 'fixture-provider', 'source_type' => 'supplier_written_quote', 'relationship' => 'operator_attested',
				'public_label' => 'Current supplier quote', 'supplier_name' => 'Fixture supplier', 'seller_name' => 'Fixture seller',
				'source_reference' => 'QUOTE:FIXTURE-2', 'source_url' => '', 'freshness_minutes' => 60, 'revalidated_now' => true,
			),
		),
		'unresolved_items' => array(),
	);
}
function tra_vel_controller_attest_composition( $composition, $case, $operator_user_id, $now ) {
	$attestation = Tra_Vel_Assisted_Proposal_Composer::issue_evidence_attestation( $composition, $case, $operator_user_id, $now );
	if ( is_wp_error( $attestation ) ) { return $attestation; }
	$composition['evidence_attestation_token'] = $attestation['attestation_token'];
	return $composition;
}

class Tra_Vel_Assisted_Proposal_Store {
	public static $ready = true;
	public $head;
	public $proposal;
	public $sources;
	public $read_error = false;
	public $publish_calls = 0;
	public $compose_receipts = array();
	public $state_receipts = array();
	public $state_event_calls = 0;
	public function __construct( $head = null, $proposal = null, $sources = null ) { if ( $head ) { $this->head = $head; $this->proposal = $proposal; $this->sources = $sources; } }
	public static function is_ready() { return self::$ready; }
	public function list_by_case() { return $this->read_error ? new WP_Error( 'fixture_read_failed', 'Read failed.', array( 'status' => 500 ) ) : array( $this->head ); }
	public function get_by_uuid( $uuid ) { return $this->read_error ? new WP_Error( 'fixture_read_failed', 'Read failed.', array( 'status' => 500 ) ) : ( $uuid === $this->head['proposal_uuid'] ? $this->head : null ); }
	public function get_revision_bundle( $uuid, $revision = 0 ) { return $this->read_error ? new WP_Error( 'fixture_bundle_failed', 'Bundle failed.', array( 'status' => 500 ) ) : $this->bundle(); }
	public function publish_revision( $case, $proposal, $sources, $expected_version, $principal, $key ) {
		$current_version = (int) ( $this->head['proposal_version'] ?? 0 );
		$is_new = empty( $this->head ) || (string) ( $this->head['proposal_uuid'] ?? '' ) !== (string) $proposal['proposal_id'];
		if ( $is_new && ( 0 !== (int) $expected_version || 1 !== (int) $proposal['version'] || 1 !== (int) $proposal['revision'] ) ) { return new WP_Error( 'tra_vel_assisted_proposal_version_conflict', 'Invalid initial version.', array( 'status' => 409, 'current_version' => $current_version ) ); }
		if ( ! $is_new && ( (int) $expected_version !== $current_version || (int) $proposal['version'] !== $current_version + 1 || (int) $proposal['revision'] !== (int) $this->head['current_revision'] + 1 ) ) { return new WP_Error( 'tra_vel_assisted_proposal_version_conflict', 'Stale version.', array( 'status' => 409, 'current_version' => $current_version ) ); }
		$this->publish_calls++; $this->proposal = $proposal; $this->sources = $sources;
		$this->head['proposal_uuid'] = $proposal['proposal_id']; $this->head['reference_code'] = $proposal['reference'];
		$this->head['quote_case_id'] = $case['id']; $this->head['quote_case_uuid'] = $case['case_uuid'];
		$this->head['position'] = $proposal['position']; $this->head['status'] = $proposal['status'];
		$this->head['traveler_disposition'] = $proposal['traveler_disposition']; $this->head['proposal_version'] = (int) $proposal['version'];
		$this->head['current_revision'] = $proposal['revision']; $this->head['published_revision'] = $proposal['published_revision'];
		$this->head['source_case_version'] = $case['case_version']; $this->head['source_case_revision'] = $case['current_revision'];
		$this->head['request_digest'] = $case['latest_request_digest']; $this->head['expires_at'] = $proposal['expires_at'];
		return $this->bundle();
	}
	public function publish_composed_revision( $case, $proposal, $sources, $expected_version, $principal, $key, $command_basis ) {
		$receipt_key = (string) $principal['principal_hash'] . '|' . (string) $key;
		$digest = $this->composition_digest( $case, $expected_version, $command_basis );
		if ( isset( $this->compose_receipts[ $receipt_key ] ) ) {
			if ( ! hash_equals( $this->compose_receipts[ $receipt_key ]['digest'], $digest ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_idempotency_conflict', 'Different command.', array( 'status' => 409 ) );
			}
			$result = $this->compose_receipts[ $receipt_key ]['result'];
			$result['replayed'] = true;
			return $result;
		}
		$result = $this->publish_revision( $case, $proposal, $sources, $expected_version, $principal, $key );
		if ( ! is_wp_error( $result ) ) {
			$this->compose_receipts[ $receipt_key ] = array( 'digest' => $digest, 'result' => $result );
		}
		return $result;
	}
	public function replay_composed_revision( $case, $expected_version, $principal, $key, $command_basis ) {
		$receipt_key = (string) $principal['principal_hash'] . '|' . (string) $key;
		if ( ! isset( $this->compose_receipts[ $receipt_key ] ) ) { return null; }
		$digest = $this->composition_digest( $case, $expected_version, $command_basis );
		if ( ! hash_equals( $this->compose_receipts[ $receipt_key ]['digest'], $digest ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_idempotency_conflict', 'Different command.', array( 'status' => 409 ) );
		}
		$result = $this->compose_receipts[ $receipt_key ]['result'];
		$live_status = (string) ( $this->head['status'] ?? '' );
		$receipt_status = (string) ( $result['head']['status'] ?? '' );
		if ( 'available' === $live_status && strtotime( (string) ( $this->head['expires_at'] ?? '' ) ) <= time() ) { $live_status = 'expired'; }
		if ( 'available' === $receipt_status && strtotime( (string) ( $result['head']['expires_at'] ?? '' ) ) <= time() ) {
			$receipt_status = 'expired'; $result['head']['status'] = 'expired'; $result['head']['traveler_disposition'] = 'unavailable';
		}
		if ( ! in_array( (string) ( $case['status'] ?? '' ), array( 'queued', 'in_review', 'needs_information', 'ready_for_assistance' ), true )
			|| (int) ( $result['head']['source_case_revision'] ?? 0 ) !== (int) $case['current_revision']
			|| ! hash_equals( (string) ( $result['head']['request_digest'] ?? '' ), (string) $case['latest_request_digest'] )
			|| (int) ( $this->head['proposal_version'] ?? 0 ) !== (int) ( $result['head']['proposal_version'] ?? 0 )
			|| (int) ( $this->head['current_revision'] ?? 0 ) !== (int) ( $result['head']['current_revision'] ?? 0 )
			|| $live_status !== $receipt_status
			|| ( 'expired' !== $live_status && (string) ( $this->head['traveler_disposition'] ?? '' ) !== (string) ( $result['head']['traveler_disposition'] ?? '' ) ) ) { $result['_force_superseded'] = true; }
		$result['replayed'] = true;
		return $result;
	}
	public function replay_traveler_action( $case, $uuid, $expected_version, $principal, $action, $contact_consent, $key ) {
		return $this->replay_state( 'proposal.traveler_action', $case, $uuid, $expected_version, $principal, $action, $contact_consent, $key );
	}
	public function record_traveler_action( $case, $uuid, $expected_version, $principal, $action, $contact_consent, $key, $now = null ) {
		$replay = $this->replay_state( 'proposal.traveler_action', $case, $uuid, $expected_version, $principal, $action, $contact_consent, $key );
		if ( is_wp_error( $replay ) || is_array( $replay ) ) { return $replay; }
		if ( $expected_version !== $this->head['proposal_version'] ) { return new WP_Error( 'tra_vel_assisted_proposal_version_conflict', 'Stale version.', array( 'status' => 409 ) ); }
		$target = Tra_Vel_Assisted_Proposal_Policy::traveler_action_target( 'available', $this->head['traveler_disposition'], $action );
		if ( is_wp_error( $target ) ) { return $target; }
		$this->head['proposal_version']++; $this->head['traveler_disposition'] = $target;
		$this->state_event_calls++;
		$result = $this->bundle();
		$result['event'] = array( 'proposal_version' => $this->head['proposal_version'], 'action' => $action, 'source_snapshot' => 'must-not-leak', 'owner_token_hash' => str_repeat( 'e', 64 ) );
		if ( is_array( $contact_consent ) ) { $result['event']['contact_consent'] = $contact_consent + array( 'contact_target_digest' => hash( 'sha256', 'wp-user-account:' . (int) $principal['user_id'] ), 'consented_at' => gmdate( 'c' ) ); }
		$this->save_state_receipt( 'proposal.traveler_action', $case, $uuid, $expected_version, $principal, $action, $contact_consent, $key, $result );
		return $result;
	}
	public function replay_withdrawal( $case, $uuid, $expected_version, $principal, $key ) {
		return $this->replay_state( 'proposal.withdraw', $case, $uuid, $expected_version, $principal, 'withdraw', null, $key );
	}
	public function withdraw( $case, $uuid, $expected_version, $principal, $key, $now = null ) {
		$replay = $this->replay_state( 'proposal.withdraw', $case, $uuid, $expected_version, $principal, 'withdraw', null, $key );
		if ( is_wp_error( $replay ) || is_array( $replay ) ) { return $replay; }
		if ( $expected_version !== $this->head['proposal_version'] ) { return new WP_Error( 'tra_vel_assisted_proposal_version_conflict', 'Stale version.', array( 'status' => 409 ) ); }
		$this->head['proposal_version']++; $this->head['status'] = 'withdrawn'; $this->head['traveler_disposition'] = 'unavailable'; $this->state_event_calls++;
		$result = $this->bundle(); $result['event'] = array( 'proposal_version' => $this->head['proposal_version'], 'action' => 'withdraw' );
		$this->save_state_receipt( 'proposal.withdraw', $case, $uuid, $expected_version, $principal, 'withdraw', null, $key, $result );
		return $result;
	}
	private function state_digest( $scope, $case, $uuid, $expected_version, $action, $contact_consent ) {
		return Tra_Vel_Assisted_Proposal_Policy::canonical_digest( array( 'operation' => $scope, 'case_binding' => array( 'case_id' => $case['case_uuid'] ), 'proposal_id' => $uuid, 'expected_version' => (int) $expected_version, 'action' => $action, 'contact_consent' => $contact_consent ) );
	}
	private function state_receipt_key( $scope, $principal, $key ) { return $scope . '|' . (string) $principal['principal_hash'] . '|' . (string) $key; }
	private function save_state_receipt( $scope, $case, $uuid, $expected_version, $principal, $action, $contact_consent, $key, $result ) {
		$this->state_receipts[ $this->state_receipt_key( $scope, $principal, $key ) ] = array( 'digest' => $this->state_digest( $scope, $case, $uuid, $expected_version, $action, $contact_consent ), 'result' => $result );
	}
	private function replay_state( $scope, $case, $uuid, $expected_version, $principal, $action, $contact_consent, $key ) {
		$receipt_key = $this->state_receipt_key( $scope, $principal, $key );
		if ( ! isset( $this->state_receipts[ $receipt_key ] ) ) { return null; }
		$digest = $this->state_digest( $scope, $case, $uuid, $expected_version, $action, $contact_consent );
		if ( ! hash_equals( $this->state_receipts[ $receipt_key ]['digest'], $digest ) ) { return new WP_Error( 'tra_vel_assisted_proposal_idempotency_conflict', 'Different state command.', array( 'status' => 409 ) ); }
		$result = $this->state_receipts[ $receipt_key ]['result'];
		if ( ! in_array( (string) ( $case['status'] ?? '' ), array( 'queued', 'in_review', 'needs_information', 'ready_for_assistance' ), true ) || (int) $this->head['proposal_version'] !== (int) $result['head']['proposal_version'] || (int) $this->head['source_case_revision'] !== (int) $case['current_revision'] || ! hash_equals( (string) $this->head['request_digest'], (string) $case['latest_request_digest'] ) ) { $result['_force_superseded'] = true; }
		$result['replayed'] = true;
		return $result;
	}
	private function bundle() {
		$proposal = $this->proposal; $proposal['internal_note'] = 'must-not-leak';
		$sources = $this->sources; $sources[0]['source_snapshot'] = 'raw-evidence-must-not-leak';
		return array( 'head' => $this->head + array( 'owner_user_id' => 99, 'owner_token_hash' => str_repeat( 'f', 64 ) ), 'proposal' => $proposal, 'revision_snapshot' => array( 'raw' => 'must-not-leak' ), 'sources' => $sources, 'revision_metadata' => array( 'internal_id' => 77 ), 'replayed' => false );
	}
	private function composition_digest( $case, $expected_version, $command_basis ) {
		return Tra_Vel_Assisted_Proposal_Policy::canonical_digest(
			array(
				'expected_version' => (int) $expected_version,
				'case_id'          => (string) $case['case_uuid'],
				'command'          => $command_basis,
			)
		);
	}
}

class Tra_Vel_Quote_Case_Store {
	public static $ready = true;
	public $case;
	public function __construct( $case = null ) { if ( $case ) { $this->case = $case; } }
	public static function is_ready() { return self::$ready; }
	public function get_case_by_uuid( $uuid ) { return $uuid === $this->case['case_uuid'] ? $this->case : null; }
	public function can_access( $case, $user_id, $token_hash ) { return ( $user_id > 0 && $user_id === $case['owner_user_id'] ) || ( 0 === $user_id && $token_hash && hash_equals( $case['owner_token_hash'], $token_hash ) ); }
}

require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-assisted-proposal-controller.php';

$tra_vel_controller_assertions = 0;
function tra_vel_controller_expect( $condition, $message ) { global $tra_vel_controller_assertions; $tra_vel_controller_assertions++; if ( ! $condition ) { fwrite( STDERR, 'Tra-Vel assisted proposal controller runtime failed: ' . $message . PHP_EOL ); exit( 1 ); } }
function tra_vel_controller_expect_error( $value, $code, $message, $status = null ) { tra_vel_controller_expect( is_wp_error( $value ), $message . ' Expected WP_Error.' ); if ( is_wp_error( $value ) ) { tra_vel_controller_expect( $code === $value->get_error_code(), $message . ' Unexpected ' . $value->get_error_code() . '.' ); if ( null !== $status ) { $data = $value->get_error_data(); tra_vel_controller_expect( is_array( $data ) && $status === (int) ( $data['status'] ?? 0 ), $message . ' Unexpected REST status.' ); } } }
function tra_vel_controller_contains_key( $value, $forbidden ) { if ( ! is_array( $value ) ) { return false; } foreach ( $value as $key => $child ) { if ( in_array( (string) $key, $forbidden, true ) || tra_vel_controller_contains_key( $child, $forbidden ) ) { return true; } } return false; }

$now = time();
$case_uuid = '123e4567-e89b-42d3-a456-426614174002';
$owner_token = str_repeat( 'A', 40 );
$_COOKIE[ Tra_Vel_Assisted_Proposal_Controller::OWNER_COOKIE ] = $owner_token;
$source = tra_vel_controller_source( $now );
$proposal = tra_vel_controller_proposal( $case_uuid, $source, $now );
$head = array(
	'id' => 41, 'proposal_uuid' => $proposal['proposal_id'], 'reference_code' => $proposal['reference'], 'quote_case_id' => 17,
	'quote_case_uuid' => $case_uuid, 'position' => 'best_value', 'status' => 'available', 'traveler_disposition' => 'awaiting_review',
	'proposal_version' => 1, 'current_revision' => 1, 'published_revision' => 1, 'source_case_version' => 4,
	'source_case_revision' => 2, 'request_digest' => str_repeat( 'b', 64 ), 'expires_at' => gmdate( 'c', $now + 3600 ),
);
$case = array(
	'id' => 17, 'case_uuid' => $case_uuid, 'status' => 'ready_for_assistance', 'case_version' => 4, 'current_revision' => 2,
	'latest_request_digest' => str_repeat( 'b', 64 ), 'owner_user_id' => 0, 'owner_token_hash' => hash( 'sha256', $owner_token ),
	'assigned_user_id' => 7, 'retention_until' => gmdate( 'Y-m-d H:i:s', $now + DAY_IN_SECONDS ), 'legal_hold' => 0,
);
$composition_precondition = array(
	'expected_case_version'   => 4,
	'expected_case_revision'  => 2,
	'expected_request_digest' => str_repeat( 'b', 64 ),
);
$store = new Tra_Vel_Assisted_Proposal_Store( $head, $proposal, array( $source ) );
$case_store = new Tra_Vel_Quote_Case_Store( $case );
$controller = new Tra_Vel_Assisted_Proposal_Controller( $store, $case_store );
$controller->register_routes();

$expected_routes = array(
	'/tra-vel-agent/v1/schema/assisted-proposal',
	'/tra-vel-agent/v1/schema/assisted-proposal-source',
	'/tra-vel-agent/v1/schema/assisted-proposal-traveler',
	'/tra-vel-agent/v1/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/assisted-proposals',
	'/tra-vel-agent/v1/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/assisted-proposals/(?P<proposal_id>[0-9a-fA-F-]{36})',
	'/tra-vel-agent/v1/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/assisted-proposals/(?P<proposal_id>[0-9a-fA-F-]{36})/actions',
	'/tra-vel-agent/v1/operator/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/assisted-proposals',
	'/tra-vel-agent/v1/operator/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/assisted-proposals/evidence-attestation',
	'/tra-vel-agent/v1/operator/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/assisted-proposals/compose',
	'/tra-vel-agent/v1/operator/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/assisted-proposals/(?P<proposal_id>[0-9a-fA-F-]{36})',
	'/tra-vel-agent/v1/operator/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/assisted-proposals/(?P<proposal_id>[0-9a-fA-F-]{36})/compose',
	'/tra-vel-agent/v1/operator/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/assisted-proposals/(?P<proposal_id>[0-9a-fA-F-]{36})/withdraw',
);
foreach ( $expected_routes as $route ) { tra_vel_controller_expect( isset( $tra_vel_registered_routes[ $route ] ), 'Missing route ' . $route . '.' ); }
tra_vel_controller_expect( 12 === count( $tra_vel_registered_routes ) && 13 === array_sum( array_map( static function ( $args ) { return isset( $args[0] ) ? count( $args ) : 1; }, $tra_vel_registered_routes ) ), 'Route registration must expose exactly twelve routes and thirteen method endpoints.' );
tra_vel_controller_expect( isset( $tra_vel_registered_filters['rest_post_dispatch'] ), 'Private error responses require a scoped post-dispatch guard.' );
$operator_collection_route = '/tra-vel-agent/v1/operator/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/assisted-proposals';
tra_vel_controller_expect( 2 === count( $tra_vel_registered_routes[ $operator_collection_route ] ) && 'GET' === $tra_vel_registered_routes[ $operator_collection_route ][0]['methods'] && 'POST' === $tra_vel_registered_routes[ $operator_collection_route ][1]['methods'], 'Operator collection must expose capability-separated GET list and POST publish endpoints.' );
tra_vel_controller_expect( 'can_ingest_proposals' === $tra_vel_registered_routes[ $operator_collection_route ][1]['permission_callback'][1], 'Raw canonical publication must use the trusted-ingestion permission callback.' );
$compose_create_route = $operator_collection_route . '/compose';
$attestation_route = $operator_collection_route . '/evidence-attestation';
$attestation_args = $tra_vel_registered_routes[ $attestation_route ];
tra_vel_controller_expect( 'POST' === $attestation_args['methods'] && 'attest_composition_evidence' === $attestation_args['callback'][1] && 'can_publish_proposals' === $attestation_args['permission_callback'][1] && true === $attestation_args['args']['expected_case_version']['required'], 'Evidence attestation must be a capability-gated POST bound to the exact quote-case context.' );
$compose_create_args = $tra_vel_registered_routes[ $compose_create_route ];
tra_vel_controller_expect( 'POST' === $compose_create_args['methods'] && 'compose_proposal' === $compose_create_args['callback'][1] && 'can_publish_proposals' === $compose_create_args['permission_callback'][1], 'Create composition route must use the exact POST callback and human publication capability.' );
tra_vel_controller_expect( true === $compose_create_args['args']['composition']['required'] && 0 === $compose_create_args['args']['expected_version']['minimum'] && true === $compose_create_args['args']['idempotency_key']['required'] && true === $compose_create_args['args']['expected_case_version']['required'] && true === $compose_create_args['args']['expected_case_revision']['required'] && true === $compose_create_args['args']['expected_request_digest']['required'], 'Create composition route must require a reduced command, exact quote-case/request precondition, zero-capable version, and idempotency key.' );
$compose_revision_route = $operator_collection_route . '/(?P<proposal_id>[0-9a-fA-F-]{36})/compose';
$compose_revision_args = $tra_vel_registered_routes[ $compose_revision_route ];
tra_vel_controller_expect( 'POST' === $compose_revision_args['methods'] && 'compose_proposal_revision' === $compose_revision_args['callback'][1] && 'can_publish_proposals' === $compose_revision_args['permission_callback'][1], 'Revision composition route must use the exact POST revision callback and human publication capability.' );
tra_vel_controller_expect( true === $compose_revision_args['args']['composition']['required'] && 1 === $compose_revision_args['args']['expected_version']['minimum'] && true === $compose_revision_args['args']['proposal_id']['required'] && true === $compose_revision_args['args']['idempotency_key']['required'] && true === $compose_revision_args['args']['expected_case_version']['required'] && true === $compose_revision_args['args']['expected_case_revision']['required'] && true === $compose_revision_args['args']['expected_request_digest']['required'], 'Revision composition route must require proposal identity, a positive version, exact quote-case/request precondition, reduced command, and idempotency key.' );
$traveler_action_route = '/tra-vel-agent/v1/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/assisted-proposals/(?P<proposal_id>[0-9a-fA-F-]{36})/actions';
tra_vel_controller_expect( array( 'review', 'request_changes', 'authorize_contact', 'decline' ) === $tra_vel_registered_routes[ $traveler_action_route ]['args']['action']['enum'], 'Traveler action route must publish only the exact four safe actions.' );

$base_params = array( 'case_id' => $case_uuid, 'proposal_id' => $proposal['proposal_id'], 'per_page' => 20 );
$get_request = new WP_REST_Request( 'GET', $base_params, null, array(), '/tra-vel-agent/v1/quote-cases/' . $case_uuid . '/assisted-proposals/' . $proposal['proposal_id'] );
tra_vel_controller_expect( true === $controller->can_access_traveler_case( $get_request ), 'Exact guest owner must pass.' );
$traveler_response = $controller->get_traveler_proposal( $get_request );
tra_vel_controller_expect( $traveler_response instanceof WP_REST_Response && 200 === $traveler_response->get_status(), 'Traveler GET must return a private response.' );
$traveler_data = $traveler_response->get_data();
tra_vel_controller_expect( true === $controller->validate_traveler_proposal( $traveler_data['proposal'] ), 'Traveler proposal must validate against the exact reduced response schema.' );
tra_vel_controller_expect( is_wp_error( $controller->validate_proposal_arg( $traveler_data['proposal'] ) ), 'A reduced traveler response must not be accepted as a canonical publication command.' );
tra_vel_controller_expect( 'Fixture Air' === $traveler_data['proposal']['sources'][0]['supplier_name'] && null === $traveler_data['proposal']['sources'][0]['source_url'], 'Traveler-safe provenance must preserve its public supplier label while suppressing private evidence URLs.' );
tra_vel_controller_expect( ! tra_vel_controller_contains_key( $traveler_data['proposal'], array( 'provider_code', 'relationship', 'source_reference', 'evidence_digest', 'source_set_digest', 'request_digest', 'calculation_digest' ) ), 'Traveler proposal JSON must omit internal provider relationships, supplier lookup handles, and proposal-level integrity digests.' );
tra_vel_controller_expect( ! tra_vel_controller_contains_key( $traveler_data, array( 'internal_note', 'source_snapshot', 'revision_snapshot', 'owner_user_id', 'owner_token_hash', 'internal_id', 'event', 'idempotency_key' ) ), 'Raw evidence, owner, event, and internal persistence fields must never be emitted.' );
$traveler_headers = $traveler_response->get_headers();
tra_vel_controller_expect( 'private, no-store, max-age=0' === ( $traveler_headers['Cache-Control'] ?? '' ) && 'noindex, nofollow, noarchive' === ( $traveler_headers['X-Robots-Tag'] ?? '' ), 'Traveler response must be private, no-store, and noindex.' );

$schema_response = $controller->get_proposal_schema();
tra_vel_controller_expect( $schema_response instanceof WP_REST_Response && ! isset( $schema_response->get_headers()['X-Robots-Tag'] ), 'Schema GET is the only unguarded response class.' );
$traveler_schema_response = $controller->get_traveler_proposal_schema();
$traveler_schema_data = $traveler_schema_response->get_data();
tra_vel_controller_expect( ! isset( $traveler_schema_data['properties']['source_set_digest'] ) && ! isset( $traveler_schema_data['definitions']['addresses']['properties']['request_digest'] ) && ! isset( $traveler_schema_data['definitions']['ledger']['properties']['calculation_digest'] ), 'Public traveler schema must describe the minimized hash-free proposal projection.' );
$guarded_error = $controller->protect_private_response( new WP_REST_Response( array( 'code' => 'error' ), 403 ), null, $get_request );
tra_vel_controller_expect( 'private, no-store, max-age=0' === ( $guarded_error->get_headers()['Cache-Control'] ?? '' ), 'Post-dispatch guard must protect error responses too.' );
$guarded_wp_error = $controller->protect_private_response( new WP_Error( 'fixture_error', 'Private error.', array( 'status' => 409 ) ), null, $get_request );
tra_vel_controller_expect( $guarded_wp_error instanceof WP_REST_Response && 409 === $guarded_wp_error->get_status() && 'noindex, nofollow, noarchive' === ( $guarded_wp_error->get_headers()['X-Robots-Tag'] ?? '' ), 'Scoped post-dispatch guard must convert and protect a raw WP_Error fail-closed response.' );

$unknown_nested = $proposal;
$unknown_nested['components'][0]['rogue_field'] = 'reject-me';
$tra_vel_capabilities[ Tra_Vel_Quote_Case_Capabilities::INGEST_PROPOSALS ] = true;
$publish_json = array( 'proposal' => $unknown_nested, 'expected_version' => 0, 'idempotency_key' => 'publish-fixture-0001' );
$publish_request = new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $publish_json, $publish_json );
tra_vel_controller_expect_error( $controller->publish_proposal( $publish_request ), 'tra_vel_assisted_proposal_shape_invalid', 'Unknown nested proposal fields must fail closed.' );
tra_vel_controller_expect( 0 === $store->publish_calls, 'Rejected unknown fields must never reach persistence.' );
$unknown_source = $proposal;
$unknown_source['sources'][0]['raw_response'] = array( 'secret' => true );
tra_vel_controller_expect_error( $controller->validate_proposal_arg( $unknown_source ), 'tra_vel_assisted_proposal_shape_invalid', 'Unknown nested source evidence must fail closed.' );
$unknown_envelope = array( 'proposal' => $proposal, 'expected_version' => 0, 'idempotency_key' => 'publish-fixture-0002', 'booking_id' => 'forbidden' );
tra_vel_controller_expect_error( $controller->publish_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $unknown_envelope, $unknown_envelope ) ), 'tra_vel_assisted_proposal_envelope_unknown', 'Unknown publication envelope fields must fail closed.' );
unset( $tra_vel_capabilities[ Tra_Vel_Quote_Case_Capabilities::INGEST_PROPOSALS ] );

$same_site_mutation = new ReflectionMethod( 'Tra_Vel_Assisted_Proposal_Controller', 'same_site_mutation' );
$same_site_mutation->setAccessible( true );
$tra_vel_current_user_id = 77;
$signed_in_without_nonce = new WP_REST_Request( 'POST', array(), array(), array( 'Origin' => 'https://tra-vel.co.il' ) );
tra_vel_controller_expect_error( $same_site_mutation->invoke( $controller, $signed_in_without_nonce ), 'tra_vel_assisted_proposal_nonce_invalid', 'A signed-in traveler mutation without a REST nonce must fail closed.' );
$signed_in_invalid_nonce = new WP_REST_Request( 'POST', array(), array(), array( 'Origin' => 'https://tra-vel.co.il', 'X-WP-Nonce' => 'invalid-nonce' ) );
tra_vel_controller_expect_error( $same_site_mutation->invoke( $controller, $signed_in_invalid_nonce ), 'tra_vel_assisted_proposal_nonce_invalid', 'A signed-in traveler mutation with an invalid REST nonce must fail closed.' );
$signed_in_valid_nonce = new WP_REST_Request( 'POST', array(), array(), array( 'Origin' => 'https://tra-vel.co.il', 'X-WP-Nonce' => 'valid-nonce' ) );
tra_vel_controller_expect( true === $same_site_mutation->invoke( $controller, $signed_in_valid_nonce ), 'A signed-in same-origin traveler mutation with a valid REST nonce must pass the CSRF boundary.' );
$tra_vel_current_user_id = 0;

$bad_origin = new WP_REST_Request( 'POST', $base_params + array( 'action' => 'review' ), array( 'action' => 'review', 'expected_version' => 1, 'idempotency_key' => 'action-fixture-0001' ), array( 'Origin' => 'https://evil.example' ) );
tra_vel_controller_expect_error( $controller->can_access_traveler_case( $bad_origin ), 'tra_vel_assisted_proposal_origin_rejected', 'Cross-origin traveler action must be rejected.' );
$bad_referer = new WP_REST_Request( 'POST', $base_params + array( 'action' => 'review' ), array( 'action' => 'review', 'expected_version' => 1, 'idempotency_key' => 'action-fixture-0002' ), array( 'Referer' => 'https://tra-vel.co.il.evil.example/path' ) );
tra_vel_controller_expect_error( $controller->can_access_traveler_case( $bad_referer ), 'tra_vel_assisted_proposal_origin_rejected', 'Lookalike Referer host must be rejected.' );
$bad_port = new WP_REST_Request( 'POST', $base_params + array( 'action' => 'review' ), array( 'action' => 'review', 'expected_version' => 1, 'idempotency_key' => 'action-fixture-0003' ), array( 'Origin' => 'https://tra-vel.co.il:444' ) );
tra_vel_controller_expect_error( $controller->can_access_traveler_case( $bad_port ), 'tra_vel_assisted_proposal_origin_rejected', 'Mismatched effective port must be rejected.' );
$credential_origin = new WP_REST_Request( 'POST', $base_params + array( 'action' => 'review' ), array( 'action' => 'review', 'expected_version' => 1, 'idempotency_key' => 'action-fixture-0004' ), array( 'Origin' => 'https://user:pass@tra-vel.co.il' ) );
tra_vel_controller_expect_error( $controller->can_access_traveler_case( $credential_origin ), 'tra_vel_assisted_proposal_origin_rejected', 'Credential-bearing Origin must be rejected.' );
$good_action_json = array( 'action' => 'review', 'expected_version' => 1, 'idempotency_key' => 'action-fixture-0005' );
$good_action = new WP_REST_Request( 'POST', $base_params + $good_action_json, $good_action_json, array( 'Referer' => 'https://tra-vel.co.il/trips/fixture' ) );
tra_vel_controller_expect( true === $controller->can_access_traveler_case( $good_action ), 'Same-origin Referer and exact owner must pass.' );
$action_response = $controller->record_traveler_action( $good_action );
tra_vel_controller_expect( $action_response instanceof WP_REST_Response && 'reviewed' === $action_response->get_data()['proposal']['traveler_disposition'], 'Safe traveler action must project its new disposition.' );
tra_vel_controller_expect( ! tra_vel_controller_contains_key( $action_response->get_data(), array( 'event', 'source_snapshot', 'owner_token_hash' ) ), 'Traveler action response must not leak its raw event.' );
$decline_json = array( 'action' => 'decline', 'expected_version' => 2, 'idempotency_key' => 'action-fixture-0008' );
$decline_response = $controller->record_traveler_action( new WP_REST_Request( 'POST', $base_params + $decline_json, $decline_json, array( 'Origin' => 'https://tra-vel.co.il' ) ) );
tra_vel_controller_expect( $decline_response instanceof WP_REST_Response && 'available' === $decline_response->get_data()['proposal']['status'] && 'declined' === $decline_response->get_data()['proposal']['traveler_disposition'] && array() === $decline_response->get_data()['proposal']['next_actions'], 'Decline must remain a traveler disposition, not masquerade as operator withdrawal.' );
tra_vel_controller_expect( true === $controller->validate_traveler_proposal( $decline_response->get_data()['proposal'] ), 'Terminal traveler disposition must still match the traveler response schema.' );
$evolved_action_replay = $controller->record_traveler_action( $good_action );
tra_vel_controller_expect( $evolved_action_replay instanceof WP_REST_Response && true === $evolved_action_replay->get_data()['replayed'] && 'superseded' === $evolved_action_replay->get_data()['proposal']['status'] && 2 === $store->state_event_calls, 'An exact traveler-action retry must recover its committed historical result after the proposal evolves without appending another event.' );
$changed_action_body = array( 'action' => 'review', 'expected_version' => 2, 'idempotency_key' => 'action-fixture-0005' );
tra_vel_controller_expect_error( $controller->record_traveler_action( new WP_REST_Request( 'POST', $base_params + $changed_action_body, $changed_action_body, array( 'Origin' => 'https://tra-vel.co.il' ) ) ), 'tra_vel_assisted_proposal_idempotency_conflict', 'The same traveler-action key with a changed canonical body must conflict even after the proposal evolves.', 409 );

$stale_json = array( 'action' => 'request_changes', 'expected_version' => 1, 'idempotency_key' => 'action-fixture-0006' );
$stale_request = new WP_REST_Request( 'POST', $base_params + $stale_json, $stale_json, array( 'Origin' => 'https://tra-vel.co.il' ) );
tra_vel_controller_expect_error( $controller->record_traveler_action( $stale_request ), 'tra_vel_assisted_proposal_version_conflict', 'Stale traveler state version must fail closed.' );
$unsupported_json = array( 'action' => 'pay', 'expected_version' => 2, 'idempotency_key' => 'action-fixture-0007' );
tra_vel_controller_expect_error( $controller->record_traveler_action( new WP_REST_Request( 'POST', $base_params + $unsupported_json, $unsupported_json, array( 'Origin' => 'https://tra-vel.co.il' ) ) ), 'tra_vel_assisted_proposal_action_unsupported', 'Unsupported consequential action must fail closed.' );

$saved_cookie = $_COOKIE[ Tra_Vel_Assisted_Proposal_Controller::OWNER_COOKIE ];
$_COOKIE[ Tra_Vel_Assisted_Proposal_Controller::OWNER_COOKIE ] = str_repeat( 'Z', 40 );
tra_vel_controller_expect_error( $controller->can_access_traveler_case( $get_request ), 'tra_vel_assisted_proposal_forbidden', 'A different guest owner must not read the proposal.' );
$_COOKIE[ Tra_Vel_Assisted_Proposal_Controller::OWNER_COOKIE ] = $saved_cookie;

$mismatch_store = new Tra_Vel_Assisted_Proposal_Store( array_merge( $head, array( 'source_case_revision' => 3 ) ), $proposal, array( $source ) );
$mismatch_controller = new Tra_Vel_Assisted_Proposal_Controller( $mismatch_store, $case_store );
$mismatch_response = $mismatch_controller->get_traveler_proposal( $get_request );
tra_vel_controller_expect( $mismatch_response instanceof WP_REST_Response && 'superseded' === $mismatch_response->get_data()['proposal']['status'] && array() === $mismatch_response->get_data()['proposal']['next_actions'], 'Historical request mismatch must remain readable as superseded with no actions.' );
$mismatch_action_json = array( 'action' => 'review', 'expected_version' => 1, 'idempotency_key' => 'action-mismatch-0001' );
tra_vel_controller_expect_error( $mismatch_controller->record_traveler_action( new WP_REST_Request( 'POST', $base_params + $mismatch_action_json, $mismatch_action_json, array( 'Origin' => 'https://tra-vel.co.il' ) ) ), 'tra_vel_assisted_proposal_request_changed', 'Mismatched parent request revision must still block mutations.' );
$inactive_case_store = new Tra_Vel_Quote_Case_Store( array_merge( $case, array( 'status' => 'cancelled' ) ) );
$inactive_controller = new Tra_Vel_Assisted_Proposal_Controller( $store, $inactive_case_store );
tra_vel_controller_expect( true === $inactive_controller->can_access_traveler_case( $get_request ), 'A retained closed QuoteCase must remain readable by its exact owner.' );
$inactive_read = $inactive_controller->get_traveler_proposal( $get_request );
tra_vel_controller_expect( $inactive_read instanceof WP_REST_Response && 'superseded' === $inactive_read->get_data()['proposal']['status'] && array() === $inactive_read->get_data()['proposal']['next_actions'], 'A proposal under a retained inactive QuoteCase must be historical and non-actionable.' );
$inactive_action_json = array( 'action' => 'review', 'expected_version' => 1, 'idempotency_key' => 'action-inactive-0001' );
$inactive_action_request = new WP_REST_Request( 'POST', $base_params + $inactive_action_json, $inactive_action_json, array( 'Origin' => 'https://tra-vel.co.il' ) );
tra_vel_controller_expect( true === $inactive_controller->can_access_traveler_case( $inactive_action_request ), 'A retained exact owner must reach receipt reconciliation after the parent closes.' );
$closed_action_replay = $inactive_controller->record_traveler_action( $good_action );
tra_vel_controller_expect( $closed_action_replay instanceof WP_REST_Response && true === $closed_action_replay->get_data()['replayed'] && 'superseded' === $closed_action_replay->get_data()['proposal']['status'] && 2 === $store->state_event_calls, 'An exact committed traveler action must remain replayable as non-actionable history after the retained parent closes.' );
tra_vel_controller_expect_error( $inactive_controller->record_traveler_action( $inactive_action_request ), 'tra_vel_assisted_proposal_parent_inactive', 'A new traveler mutation under a retained closed QuoteCase must still fail closed.', 409 );
$expired_store = new Tra_Vel_Assisted_Proposal_Store( array_merge( $head, array( 'status' => 'expired' ) ), $proposal, array( $source ) );
$expired_controller = new Tra_Vel_Assisted_Proposal_Controller( $expired_store, $inactive_case_store );
$expired_list = $expired_controller->list_traveler_proposals( new WP_REST_Request( 'GET', array( 'case_id' => $case_uuid, 'per_page' => 20 ) ) );
tra_vel_controller_expect( $expired_list instanceof WP_REST_Response && 1 === count( $expired_list->get_data()['proposals'] ) && 'expired' === $expired_list->get_data()['proposals'][0]['status'], 'Traveler history must include retained expired, withdrawn, or superseded published heads.' );
$read_store = new Tra_Vel_Assisted_Proposal_Store( $head, $proposal, array( $source ) );
$read_store->read_error = true;
$read_controller = new Tra_Vel_Assisted_Proposal_Controller( $read_store, $case_store );
tra_vel_controller_expect_error( $read_controller->get_traveler_proposal( $get_request ), 'fixture_read_failed', 'Store read uncertainty must fail closed.' );

// Contact authorization is a separate explicit consent event. The release
// boundary supports only an authenticated account email and the Tra-Vel team;
// no raw contact target or supplier-sharing permission enters the command.
$tra_vel_current_user_id = 77;
$account_case = array_merge( $case, array( 'owner_user_id' => 77, 'owner_token_hash' => '' ) );
$account_case_store = new Tra_Vel_Quote_Case_Store( $account_case );
$account_store = new Tra_Vel_Assisted_Proposal_Store( $head, $proposal, array( $source ) );
$account_controller = new Tra_Vel_Assisted_Proposal_Controller( $account_store, $account_case_store );
$account_headers = array( 'Origin' => 'https://tra-vel.co.il', 'X-WP-Nonce' => 'valid-nonce' );
$account_review_json = array( 'action' => 'review', 'expected_version' => 1, 'idempotency_key' => 'account-review-0001' );
$account_review = $account_controller->record_traveler_action( new WP_REST_Request( 'POST', $base_params + $account_review_json, $account_review_json, $account_headers ) );
tra_vel_controller_expect( $account_review instanceof WP_REST_Response && 'reviewed' === $account_review->get_data()['proposal']['traveler_disposition'], 'An authenticated owner must review before authorizing contact.' );
$missing_consent_json = array( 'action' => 'authorize_contact', 'expected_version' => 2, 'idempotency_key' => 'account-contact-missing-0001' );
tra_vel_controller_expect_error( $account_controller->record_traveler_action( new WP_REST_Request( 'POST', $base_params + $missing_consent_json, $missing_consent_json, $account_headers ) ), 'tra_vel_assisted_proposal_contact_consent_required', 'Authorize contact must reject an absent consent object.', 400 );
$contact_consent = array(
	'contract_version' => Tra_Vel_Assisted_Proposal_Controller::CONTACT_CONSENT_CONTRACT_VERSION,
	'consent_version'  => Tra_Vel_Assisted_Proposal_Controller::CONTACT_CONSENT_VERSION,
	'affirmed'         => true,
	'purpose'          => Tra_Vel_Assisted_Proposal_Controller::CONTACT_CONSENT_PURPOSE,
	'channels'         => array( 'email' ),
	'controller_scope' => Tra_Vel_Assisted_Proposal_Controller::CONTACT_CONSENT_CONTROLLER_SCOPE,
	'recipient_scope'  => 'tra_vel_assistance_team',
	'contact_target'   => 'account_email',
);
$unexpected_consent_json = array( 'action' => 'review', 'contact_consent' => $contact_consent, 'expected_version' => 2, 'idempotency_key' => 'account-contact-unexpected-0001' );
tra_vel_controller_expect_error( $account_controller->record_traveler_action( new WP_REST_Request( 'POST', $base_params + $unexpected_consent_json, $unexpected_consent_json, $account_headers ) ), 'tra_vel_assisted_proposal_contact_consent_unexpected', 'Non-contact actions must reject a contact-consent payload.', 400 );
$broad_consent = $contact_consent;
$broad_consent['recipient_scope'] = 'tra_vel_assistance_team_and_selected_suppliers';
$broad_consent_json = array( 'action' => 'authorize_contact', 'contact_consent' => $broad_consent, 'expected_version' => 2, 'idempotency_key' => 'account-contact-broad-0001' );
tra_vel_controller_expect_error( $account_controller->record_traveler_action( new WP_REST_Request( 'POST', $base_params + $broad_consent_json, $broad_consent_json, $account_headers ) ), 'tra_vel_assisted_proposal_contact_consent_invalid', 'Contact consent must not silently authorize supplier sharing.', 400 );
$contact_json = array( 'action' => 'authorize_contact', 'contact_consent' => $contact_consent, 'expected_version' => 2, 'idempotency_key' => 'account-contact-0001' );
$contact_request = new WP_REST_Request( 'POST', $base_params + $contact_json, $contact_json, $account_headers );
$contact_response = $account_controller->record_traveler_action( $contact_request );
tra_vel_controller_expect( $contact_response instanceof WP_REST_Response && 'contact_authorized' === $contact_response->get_data()['proposal']['traveler_disposition'] && false === $contact_response->get_data()['replayed'], 'Exact signed-in consent must authorize the bounded contact transition once.' );
$account_receipts = $account_store->state_receipts;
$contact_receipt = end( $account_receipts );
$stored_consent = $contact_receipt['result']['event']['contact_consent'] ?? array();
tra_vel_controller_expect( hash( 'sha256', 'wp-user-account:77' ) === ( $stored_consent['contact_target_digest'] ?? '' ) && ! empty( $stored_consent['consented_at'] ) && 'account_email' === ( $stored_consent['contact_target'] ?? '' ) && false === strpos( wp_json_encode( $stored_consent ), 'traveler@example.com' ), 'The immutable contact event must add server time and a server-derived account digest without persisting raw contact data.' );
$account_store->head['proposal_version']++;
$account_case_store->case = array_merge( $account_case, array( 'status' => 'cancelled' ) );
$tra_vel_user_email_available = false;
$contact_replay = $account_controller->record_traveler_action( $contact_request );
tra_vel_controller_expect( $contact_replay instanceof WP_REST_Response && true === $contact_replay->get_data()['replayed'] && 'superseded' === $contact_replay->get_data()['proposal']['status'] && 2 === $account_store->state_event_calls, 'Exact consent-bound contact authorization must replay as historical after the target disappears, proposal evolves, and parent closes without another event.' );
$changed_contact_body = $contact_json;
$changed_contact_body['expected_version'] = 3;
tra_vel_controller_expect_error( $account_controller->record_traveler_action( new WP_REST_Request( 'POST', $base_params + $changed_contact_body, $changed_contact_body, $account_headers ) ), 'tra_vel_assisted_proposal_idempotency_conflict', 'The same contact-action key with a changed canonical body must conflict.', 409 );
$new_contact_key = $contact_json;
$new_contact_key['idempotency_key'] = 'account-contact-0002';
tra_vel_controller_expect_error( $account_controller->record_traveler_action( new WP_REST_Request( 'POST', $base_params + $new_contact_key, $new_contact_key, $account_headers ) ), 'tra_vel_assisted_proposal_parent_inactive', 'A different contact-action key remains a new mutation and must obey the closed parent state.', 409 );
$tra_vel_user_email_available = true;
$tra_vel_current_user_id = 0;

$tra_vel_capabilities = array( 'tra_vel_manage_quote_cases' => true );
tra_vel_controller_expect_error( $controller->can_publish_proposals(), 'tra_vel_assisted_proposal_publish_forbidden', 'Generic queue management must not grant proposal publication.' );
$tra_vel_capabilities[ Tra_Vel_Quote_Case_Capabilities::VIEW_CASES ] = true;
tra_vel_controller_expect( true === $controller->can_view_proposals(), 'VIEW_CASES must gate operator list and get.' );
$tra_vel_capabilities[ Tra_Vel_Quote_Case_Capabilities::PUBLISH_PROPOSALS ] = true;
tra_vel_controller_expect( true === $controller->can_publish_proposals(), 'Dedicated PUBLISH_PROPOSALS must gate publish and withdraw.' );
tra_vel_controller_expect_error( $controller->can_ingest_proposals(), 'tra_vel_assisted_proposal_ingest_forbidden', 'A human proposal publisher must not gain canonical ingestion.' );
$raw_permission_store = new Tra_Vel_Assisted_Proposal_Store( $head, $proposal, array( $source ) );
$raw_permission_controller = new Tra_Vel_Assisted_Proposal_Controller( $raw_permission_store, $case_store );
$raw_permission_json = array( 'proposal' => $proposal, 'expected_version' => 0, 'idempotency_key' => 'publish-permission-0001' );
tra_vel_controller_expect_error( $raw_permission_controller->publish_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $raw_permission_json, $raw_permission_json ) ), 'tra_vel_assisted_proposal_ingest_forbidden', 'The raw canonical endpoint must reject the normal human publisher capability.' );
tra_vel_controller_expect( 0 === $raw_permission_store->publish_calls, 'Raw capability rejection must happen before persistence.' );
$tra_vel_capabilities[ Tra_Vel_Quote_Case_Capabilities::INGEST_PROPOSALS ] = true;
tra_vel_controller_expect( true === $controller->can_ingest_proposals(), 'Only the separate trusted-ingestion capability may enter canonical proposals.' );
$tra_vel_current_user_id = 8;
$assignment_store = new Tra_Vel_Assisted_Proposal_Store( $head, $proposal, array( $source ) );
$assignment_controller = new Tra_Vel_Assisted_Proposal_Controller( $assignment_store, $case_store );
$assignment_json = array( 'proposal' => $proposal, 'expected_version' => 0, 'idempotency_key' => 'publish-assignment-0001' );
tra_vel_controller_expect_error( $assignment_controller->publish_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $assignment_json, $assignment_json ) ), 'tra_vel_assisted_proposal_assignment_forbidden', 'A publisher who is not assigned to the case must fail closed.' );
tra_vel_controller_expect( 0 === $assignment_store->publish_calls, 'Assignment rejection must happen before proposal persistence.' );
unset( $tra_vel_capabilities[ Tra_Vel_Quote_Case_Capabilities::INGEST_PROPOSALS ] );
$unassigned_list = $assignment_controller->list_operator_proposals( new WP_REST_Request( 'GET', array( 'case_id' => $case_uuid, 'per_page' => 20 ) ) );
tra_vel_controller_expect_error( $unassigned_list, 'tra_vel_assisted_proposal_assignment_forbidden', 'An unassigned queue viewer must not read full supplier evidence.', 403 );
$unassigned_get = $assignment_controller->get_operator_proposal( new WP_REST_Request( 'GET', array( 'case_id' => $case_uuid, 'proposal_id' => $proposal['proposal_id'] ) ) );
tra_vel_controller_expect_error( $unassigned_get, 'tra_vel_assisted_proposal_assignment_forbidden', 'An unassigned queue viewer must not read one full supplier evidence bundle.', 403 );
$unassigned_composition_json = array_merge( array( 'composition' => tra_vel_controller_composition(), 'expected_version' => 0, 'idempotency_key' => 'compose-assignment-0001' ), $composition_precondition );
tra_vel_controller_expect_error( $assignment_controller->compose_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $unassigned_composition_json, $unassigned_composition_json ) ), 'tra_vel_assisted_proposal_assignment_forbidden', 'An unassigned human publisher must not create a reduced proposal.', 403 );
$unassigned_revision_json = array_merge( array( 'composition' => tra_vel_controller_composition(), 'expected_version' => 1, 'idempotency_key' => 'revision-assignment-0001' ), $composition_precondition );
tra_vel_controller_expect_error( $assignment_controller->compose_proposal_revision( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid, 'proposal_id' => $proposal['proposal_id'] ) + $unassigned_revision_json, $unassigned_revision_json ) ), 'tra_vel_assisted_proposal_assignment_forbidden', 'An unassigned human publisher must not revise a proposal.', 403 );
$unassigned_withdraw_json = array( 'expected_version' => 1, 'idempotency_key' => 'withdraw-assignment-0001' );
tra_vel_controller_expect_error( $assignment_controller->withdraw_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid, 'proposal_id' => $proposal['proposal_id'] ) + $unassigned_withdraw_json, $unassigned_withdraw_json ) ), 'tra_vel_assisted_proposal_assignment_forbidden', 'An unassigned human publisher must not withdraw a proposal.', 403 );
$tra_vel_capabilities['manage_options'] = true;
$override_store = new Tra_Vel_Assisted_Proposal_Store( $head, $proposal, array( $source ) );
$override_controller = new Tra_Vel_Assisted_Proposal_Controller( $override_store, $case_store );
$override_composition = tra_vel_controller_attest_composition( tra_vel_controller_composition(), $case, 8, $now );
tra_vel_controller_expect( is_array( $override_composition ), 'Administrator composition fixture must receive an operator-bound attestation.' );
$override_json = array_merge( array( 'composition' => $override_composition, 'expected_version' => 0, 'idempotency_key' => 'compose-admin-override-0001' ), $composition_precondition );
$override_response = $override_controller->compose_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $override_json, $override_json ) );
tra_vel_controller_expect( $override_response instanceof WP_REST_Response && 201 === $override_response->get_status() && 1 === $override_store->publish_calls, 'Administrator oversight must be the explicit assignment override.' );
unset( $tra_vel_capabilities['manage_options'] );
$tra_vel_current_user_id = 7;
$compose_store = new Tra_Vel_Assisted_Proposal_Store( $head, $proposal, array( $source ) );
$compose_controller = new Tra_Vel_Assisted_Proposal_Controller( $compose_store, $case_store );
$unsigned_composition = tra_vel_controller_composition();
$attestation_json = array_merge( array( 'composition' => $unsigned_composition ), $composition_precondition );
$attestation_response = $compose_controller->attest_composition_evidence( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $attestation_json, $attestation_json ) );
tra_vel_controller_expect( $attestation_response instanceof WP_REST_Response && ! empty( $attestation_response->get_data()['attestation_token'] ), 'The assigned operator must receive a short-lived attestation for the exact final evidence command.' );
$unsigned_composition['evidence_attestation_token'] = $attestation_response->get_data()['attestation_token'];
$composition_json = array_merge( array( 'composition' => $unsigned_composition, 'expected_version' => 0, 'idempotency_key' => 'compose-fixture-0001' ), $composition_precondition );
$composition_response = $compose_controller->compose_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $composition_json, $composition_json ) );
tra_vel_controller_expect( $composition_response instanceof WP_REST_Response && 201 === $composition_response->get_status() && 1 === $compose_store->publish_calls, 'The reduced composer command must produce and publish one server-owned proposal.' );
$composed = $composition_response->get_data()['proposal'];
tra_vel_controller_expect( $case_uuid === $composed['case_id'] && 2 === $composed['addresses']['case_revision'] && 'available' === $composed['status'], 'Composition must derive current case binding and lifecycle state.' );
tra_vel_controller_expect( 'non_binding_assisted_proposal' === $composed['disclosure']['commercial_state'] && array( 'review', 'request_changes', 'authorize_contact', 'decline' ) === $composed['next_actions'], 'Composition must derive the exact non-binding traveler action boundary.' );
$composition_replay = $compose_controller->compose_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $composition_json, $composition_json ) );
tra_vel_controller_expect( $composition_replay instanceof WP_REST_Response && 200 === $composition_replay->get_status() && true === $composition_replay->get_data()['replayed'] && $composed['proposal_id'] === $composition_replay->get_data()['proposal']['proposal_id'] && 1 === $compose_store->publish_calls, 'An exact reduced-command retry must replay the original server-generated identity without a second write.' );
$compose_receipt_key = hash( 'sha256', 'operator:7' ) . '|compose-fixture-0001';
$original_live_expiry = $compose_store->head['expires_at'];
$original_receipt_expiry = $compose_store->compose_receipts[ $compose_receipt_key ]['result']['head']['expires_at'];
$compose_store->head['expires_at'] = gmdate( 'c', $now - 1 );
$compose_store->compose_receipts[ $compose_receipt_key ]['result']['head']['expires_at'] = gmdate( 'c', $now - 1 );
$expired_composition_replay = $compose_controller->compose_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $composition_json, $composition_json ) );
tra_vel_controller_expect( $expired_composition_replay instanceof WP_REST_Response && true === $expired_composition_replay->get_data()['replayed'] && 'expired' === $expired_composition_replay->get_data()['proposal']['status'] && array() === $expired_composition_replay->get_data()['proposal']['next_actions'] && 1 === $compose_store->publish_calls, 'A composition receipt that expires before retry must project the coherent expired lifecycle and never restore actions.' );
$compose_store->head['expires_at'] = $original_live_expiry;
$compose_store->compose_receipts[ $compose_receipt_key ]['result']['head']['expires_at'] = $original_receipt_expiry;
$case_store->case = array_merge( $case, array( 'case_version' => 5, 'current_revision' => 3, 'latest_request_digest' => str_repeat( 'c', 64 ), 'assigned_user_id' => 0 ) );
$parent_advanced_replay = $compose_controller->compose_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $composition_json, $composition_json ) );
tra_vel_controller_expect( $parent_advanced_replay instanceof WP_REST_Response && true === $parent_advanced_replay->get_data()['replayed'] && 'superseded' === $parent_advanced_replay->get_data()['proposal']['status'] && 1 === $compose_store->publish_calls, 'An exact create retry after parent-request evolution and assignment reset must resolve the original principal-scoped receipt as historical without creating another proposal.' );
$case_store->case = array_merge( $case, array( 'case_version' => 5, 'current_revision' => 3, 'latest_request_digest' => str_repeat( 'c', 64 ) ) );
$stale_uncommitted = $composition_json;
$stale_uncommitted['idempotency_key'] = 'compose-stale-uncommitted-0001';
tra_vel_controller_expect_error( $compose_controller->compose_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $stale_uncommitted, $stale_uncommitted ) ), 'tra_vel_assisted_proposal_case_precondition_failed', 'An uncommitted draft authored against an older traveler request must not publish against the evolved request.', 409 );
tra_vel_controller_expect( 1 === $compose_store->publish_calls, 'A stale uncommitted draft must not append a proposal.' );
$case_store->case = array_merge( $case, array( 'status' => 'cancelled' ) );
$closed_parent_replay = $compose_controller->compose_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $composition_json, $composition_json ) );
tra_vel_controller_expect( $closed_parent_replay instanceof WP_REST_Response && true === $closed_parent_replay->get_data()['replayed'] && 'superseded' === $closed_parent_replay->get_data()['proposal']['status'] && 1 === $compose_store->publish_calls, 'A committed composition must remain exactly replayable as historical after its retained parent closes.' );
$case_store->case = $case;
$different_retry = $composition_json;
$different_retry['composition']['title'] = 'Different composition under the same key';
tra_vel_controller_expect_error( $compose_controller->compose_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $different_retry, $different_retry ) ), 'tra_vel_assisted_proposal_idempotency_conflict', 'Reusing a compose key for different authored data must fail closed.' );
$compose_store->record_traveler_action( $case, $composed['proposal_id'], 1, array( 'principal_hash' => str_repeat( 'd', 64 ) ), 'review', null, 'compose-action-review-0001' );
$compose_store->record_traveler_action( $case, $composed['proposal_id'], 2, array( 'principal_hash' => str_repeat( 'd', 64 ) ), 'request_changes', null, 'compose-action-changes-0001' );
tra_vel_controller_expect( 3 === $compose_store->head['proposal_version'] && 1 === $compose_store->head['current_revision'], 'Traveler actions must advance aggregate version without inventing commercial revisions.' );
$post_action_compose_replay = $compose_controller->compose_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $composition_json, $composition_json ) );
tra_vel_controller_expect( $post_action_compose_replay instanceof WP_REST_Response && true === $post_action_compose_replay->get_data()['replayed'] && 'superseded' === $post_action_compose_replay->get_data()['proposal']['status'] && array() === $post_action_compose_replay->get_data()['proposal']['next_actions'] && 1 === $compose_store->publish_calls, 'An old composition receipt must become non-actionable after a traveler action advances the live head.' );
$revision_composition = tra_vel_controller_composition();
$revision_composition['title'] = 'Server-composed revised option';
$revision_composition = tra_vel_controller_attest_composition( $revision_composition, $case, 7, $now );
tra_vel_controller_expect( is_array( $revision_composition ), 'A revised commercial command must receive its own fresh attestation.' );
$revision_json = array_merge( array( 'composition' => $revision_composition, 'expected_version' => 3, 'idempotency_key' => 'compose-revision-0001' ), $composition_precondition );
$revision_params = array( 'case_id' => $case_uuid, 'proposal_id' => $composed['proposal_id'] ) + $revision_json;
$revision_response = $compose_controller->compose_proposal_revision( new WP_REST_Request( 'POST', $revision_params, $revision_json ) );
tra_vel_controller_expect( $revision_response instanceof WP_REST_Response && 4 === $revision_response->get_data()['proposal']['version'] && 2 === $revision_response->get_data()['proposal']['revision'] && $composed['proposal_id'] === $revision_response->get_data()['proposal']['proposal_id'], 'A revision command must preserve identity and advance aggregate version after intervening traveler actions.' );
$post_revision_create_replay = $compose_controller->compose_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $composition_json, $composition_json ) );
tra_vel_controller_expect( $post_revision_create_replay instanceof WP_REST_Response && true === $post_revision_create_replay->get_data()['replayed'] && 'superseded' === $post_revision_create_replay->get_data()['proposal']['status'] && 2 === $compose_store->publish_calls, 'The original create receipt must remain historical after a newer commercial revision becomes live.' );
$revision_replay = $compose_controller->compose_proposal_revision( new WP_REST_Request( 'POST', $revision_params, $revision_json ) );
tra_vel_controller_expect( $revision_replay instanceof WP_REST_Response && 200 === $revision_replay->get_status() && true === $revision_replay->get_data()['replayed'] && 'available' === $revision_replay->get_data()['proposal']['status'] && 4 === $revision_replay->get_data()['proposal']['version'] && 2 === $revision_replay->get_data()['proposal']['revision'] && 2 === $compose_store->publish_calls, 'The latest exact revision retry may retain its coherent available lifecycle and must not append a third revision.' );
$changed_revision_retry = $revision_json;
$changed_revision_retry['composition']['summary'] = 'Different revision command under the same key.';
tra_vel_controller_expect_error( $compose_controller->compose_proposal_revision( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid, 'proposal_id' => $composed['proposal_id'] ) + $changed_revision_retry, $changed_revision_retry ) ), 'tra_vel_assisted_proposal_idempotency_conflict', 'Reusing a revision key for changed authored data must fail without another append.' );
$changed_position = $revision_composition;
$changed_position['position'] = 'most_flexible';
$changed_position = tra_vel_controller_attest_composition( $changed_position, $case, 7, $now );
$changed_position_json = array_merge( array( 'composition' => $changed_position, 'expected_version' => 4, 'idempotency_key' => 'compose-revision-0002' ), $composition_precondition );
tra_vel_controller_expect_error( $compose_controller->compose_proposal_revision( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid, 'proposal_id' => $composed['proposal_id'] ) + $changed_position_json, $changed_position_json ) ), 'tra_vel_assisted_composition_identity_changed', 'A composed revision must not change immutable proposal position.' );
$compose_store->head['current_revision'] = 20;
$compose_store->head['proposal_version'] = 25;
$delayed_revision_replay = $compose_controller->compose_proposal_revision( new WP_REST_Request( 'POST', $revision_params, $revision_json ) );
tra_vel_controller_expect( $delayed_revision_replay instanceof WP_REST_Response && true === $delayed_revision_replay->get_data()['replayed'] && 'superseded' === $delayed_revision_replay->get_data()['proposal']['status'] && 2 === $delayed_revision_replay->get_data()['proposal']['revision'] && 2 === $compose_store->publish_calls, 'A delayed exact retry must recover revision two as non-actionable history after a later live revision reaches the limit.' );
$compose_store->withdraw( $case, $composed['proposal_id'], 25, array( 'principal_hash' => hash( 'sha256', 'operator:7' ) ), 'compose-withdraw-0001' );
$withdrawn_composition_replay = $compose_controller->compose_proposal_revision( new WP_REST_Request( 'POST', $revision_params, $revision_json ) );
tra_vel_controller_expect( $withdrawn_composition_replay instanceof WP_REST_Response && true === $withdrawn_composition_replay->get_data()['replayed'] && 'superseded' === $withdrawn_composition_replay->get_data()['proposal']['status'] && array() === $withdrawn_composition_replay->get_data()['proposal']['next_actions'] && 2 === $compose_store->publish_calls, 'An old composition receipt must remain historical after the live proposal is withdrawn.' );
$unknown_composition = tra_vel_controller_composition();
$unknown_composition = tra_vel_controller_attest_composition( $unknown_composition, $case, 7, $now );
$unknown_composition['sources'][0]['raw_prompt'] = 'must-not-pass';
$unknown_composition_json = array_merge( array( 'composition' => $unknown_composition, 'expected_version' => 0, 'idempotency_key' => 'compose-fixture-0002' ), $composition_precondition );
tra_vel_controller_expect_error( $compose_controller->compose_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $unknown_composition_json, $unknown_composition_json ) ), 'tra_vel_assisted_composition_shape_invalid', 'The reduced composer command must reject unknown nested fields.' );
$operator_store = new Tra_Vel_Assisted_Proposal_Store();
$operator_controller = new Tra_Vel_Assisted_Proposal_Controller( $operator_store, $case_store );
$valid_publish_json = array( 'proposal' => $proposal, 'expected_version' => 0, 'idempotency_key' => 'publish-fixture-0003' );
$tra_vel_capabilities[ Tra_Vel_Quote_Case_Capabilities::INGEST_PROPOSALS ] = true;
$valid_publish = $operator_controller->publish_proposal( new WP_REST_Request( 'POST', array( 'case_id' => $case_uuid ) + $valid_publish_json, $valid_publish_json ) );
tra_vel_controller_expect( $valid_publish instanceof WP_REST_Response && 201 === $valid_publish->get_status() && 1 === $operator_store->publish_calls, 'Dedicated operator publication endpoint must call the exact store mutation once.' );
tra_vel_controller_expect( ! tra_vel_controller_contains_key( $valid_publish->get_data(), array( 'revision_snapshot', 'source_snapshot', 'owner_user_id', 'owner_token_hash', 'internal_id', 'event' ) ), 'Operator publication response must also stay inside the public schema projection.' );
unset( $tra_vel_capabilities[ Tra_Vel_Quote_Case_Capabilities::INGEST_PROPOSALS ] );
$withdraw_json = array( 'expected_version' => 1, 'idempotency_key' => 'withdraw-fixture-0001' );
$withdraw_response = $operator_controller->withdraw_proposal( new WP_REST_Request( 'POST', $base_params + $withdraw_json, $withdraw_json ) );
tra_vel_controller_expect( $withdraw_response instanceof WP_REST_Response && 'withdrawn' === $withdraw_response->get_data()['proposal']['status'] && 'unavailable' === $withdraw_response->get_data()['proposal']['traveler_disposition'] && array() === $withdraw_response->get_data()['proposal']['next_actions'], 'Operator withdrawal must project the exact terminal non-transactional state.' );
tra_vel_controller_expect( true === $operator_controller->validate_proposal_arg( $withdraw_response->get_data()['proposal'] ), 'Operator withdrawal projection must still match the public schema.' );
$withdraw_replay = $operator_controller->withdraw_proposal( new WP_REST_Request( 'POST', $base_params + $withdraw_json, $withdraw_json ) );
tra_vel_controller_expect( $withdraw_replay instanceof WP_REST_Response && true === $withdraw_replay->get_data()['replayed'] && 'withdrawn' === $withdraw_replay->get_data()['proposal']['status'] && 1 === $operator_store->state_event_calls, 'An exact withdrawal retry must recover the committed terminal response without appending another event.' );
$operator_store->head['proposal_version']++;
$case_store->case = array_merge( $case, array( 'status' => 'cancelled', 'assigned_user_id' => 0 ) );
$closed_withdraw_replay = $operator_controller->withdraw_proposal( new WP_REST_Request( 'POST', $base_params + $withdraw_json, $withdraw_json ) );
tra_vel_controller_expect( $closed_withdraw_replay instanceof WP_REST_Response && true === $closed_withdraw_replay->get_data()['replayed'] && 'withdrawn' === $closed_withdraw_replay->get_data()['proposal']['status'] && 1 === $operator_store->state_event_calls, 'The original operator must recover an exact committed withdrawal after head evolution, assignment reset, and retained parent closure.' );
$changed_withdraw_body = array( 'expected_version' => 2, 'idempotency_key' => 'withdraw-fixture-0001' );
tra_vel_controller_expect_error( $operator_controller->withdraw_proposal( new WP_REST_Request( 'POST', $base_params + $changed_withdraw_body, $changed_withdraw_body ) ), 'tra_vel_assisted_proposal_idempotency_conflict', 'The same withdrawal key with a changed canonical body must conflict before mutable-state checks.', 409 );
$new_closed_withdraw = array( 'expected_version' => 3, 'idempotency_key' => 'withdraw-fixture-0002' );
tra_vel_controller_expect_error( $operator_controller->withdraw_proposal( new WP_REST_Request( 'POST', $base_params + $new_closed_withdraw, $new_closed_withdraw ) ), 'tra_vel_assisted_proposal_parent_inactive', 'A different withdrawal key remains a new mutation and must obey the closed parent state.', 409 );
$case_store->case = array_merge( $case, array( 'assigned_user_id' => 8 ) );
tra_vel_controller_expect_error( $operator_controller->withdraw_proposal( new WP_REST_Request( 'POST', $base_params + $new_closed_withdraw, $new_closed_withdraw ) ), 'tra_vel_assisted_proposal_assignment_forbidden', 'A different withdrawal key must still obey current operator assignment.', 403 );
$case_store->case = $case;

echo 'Tra-Vel assisted proposal controller runtime passed (' . $tra_vel_controller_assertions . ' deterministic assertions).' . PHP_EOL;
