<?php
/**
 * Deterministic server-only integration gate for the Trip Cockpit source bridge.
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['ctsa_actions']  = array();
$GLOBALS['ctsa_filters']  = array();
$GLOBALS['ctsa_provider'] = null;
$GLOBALS['ctsa_now']      = time();

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_salt( $scheme = '' ) { return 'ctsa-server-secret-' . $scheme; }
function get_current_blog_id() { return 1; }

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['ctsa_actions'][ $hook ][] = array( 'callback' => $callback, 'priority' => $priority, 'accepted_args' => $accepted_args );
}

function do_action( $hook ) {
	$args = array_slice( func_get_args(), 1 );
	$callbacks = isset( $GLOBALS['ctsa_actions'][ $hook ] ) ? $GLOBALS['ctsa_actions'][ $hook ] : array();
	usort( $callbacks, function ( $left, $right ) { return $left['priority'] - $right['priority']; } );
	foreach ( $callbacks as $entry ) {
		call_user_func_array( $entry['callback'], array_slice( $args, 0, $entry['accepted_args'] ) );
	}
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['ctsa_filters'][ $hook ][] = array( 'callback' => $callback, 'priority' => $priority, 'accepted_args' => $accepted_args );
}

function apply_filters( $hook, $value ) {
	$args = array_slice( func_get_args(), 2 );
	$callbacks = isset( $GLOBALS['ctsa_filters'][ $hook ] ) ? $GLOBALS['ctsa_filters'][ $hook ] : array();
	usort( $callbacks, function ( $left, $right ) { return $left['priority'] - $right['priority']; } );
	foreach ( $callbacks as $entry ) {
		$call_args = array_merge( array( $value ), array_slice( $args, 0, max( 0, $entry['accepted_args'] - 1 ) ) );
		$value = call_user_func_array( $entry['callback'], $call_args );
	}
	return $value;
}

$vip = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/vip/';
require_once $vip . 'class-tra-vel-vip-taxonomy.php';
require_once $vip . 'class-tra-vel-traveler-principal.php';
require_once $vip . 'class-tra-vel-customer-trip-cockpit-policy.php';
require_once $vip . 'class-tra-vel-customer-trip-cockpit-factory.php';
require_once $vip . 'interface-tra-vel-customer-trip-cockpit-source-provider.php';
require_once $vip . 'class-tra-vel-customer-trip-cockpit-source-assembler.php';

$ctsa_assertions = 0;

function ctsa_assert( $condition, $message ) {
	global $ctsa_assertions;
	$ctsa_assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, 'Customer Trip Cockpit source assembler runtime failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function ctsa_error( $value, $suffix, $message ) {
	ctsa_assert( is_wp_error( $value ), $message . ' (no WP_Error returned)' );
	if ( is_wp_error( $value ) ) {
		ctsa_assert( 'tra_vel_customer_trip_cockpit_source_assembler_' . $suffix === $value->get_error_code(), $message . ' (received ' . $value->get_error_code() . ')' );
	}
}

function ctsa_ref( $kind, $seed ) {
	return 'tv_' . $kind . '_' . substr( hash( 'sha256', $seed ), 0, 24 );
}

function ctsa_action( $code, $services = array(), $travelers = array() ) {
	return array(
		'code' => $code,
		'priority' => 'normal',
		'service_refs' => $services,
		'traveler_refs' => $travelers,
		'deadline' => null,
		'truth_state' => 'verified_current',
	);
}

function ctsa_snapshot( $clock ) {
	$service  = ctsa_ref( 'service', 'source-assembler-flight' );
	$traveler = ctsa_ref( 'traveler', 'source-assembler-adult' );
	return array(
		'headline' => 'A connected trip ready for service',
		'registration' => array(
			'gate' => 'ready_to_travel', 'readiness' => 'ready', 'pending_requirement_codes' => array(),
			'next_action' => null, 'verified_at' => $clock,
		),
		'trip_health' => array(
			'phase' => 'pre_trip', 'health' => 'on_track', 'dependency_projection_ref' => ctsa_ref( 'graph', 'source-assembler-graph' ),
			'recovery_projection_ref' => null, 'affected_service_refs' => array(), 'unaffected_service_refs' => array( $service ),
			'next_action' => null, 'verified_at' => $clock,
		),
		'services' => array(
			array(
				'service_ref' => $service, 'sequence' => 1, 'vertical' => 'flight', 'label' => 'Outbound flight',
				'phase' => 'confirmed', 'health' => 'on_track', 'fulfillment' => array( 'state' => 'confirmed', 'truth_state' => 'verified_current' ),
				'change_state' => 'unchanged', 'protected_codes' => array( 'schedule.monitoring' ), 'next_action' => null,
				'events' => array(
					array( 'event_ref' => ctsa_ref( 'timeline_event', 'source-assembler-confirmed' ), 'event_code' => 'flight.confirmed', 'state' => 'confirmed', 'truth_state' => 'verified_current', 'occurred_at' => $clock ),
				),
				'verified_at' => $clock,
			),
		),
		'protections' => array(
			array( 'protection_code' => 'schedule.monitoring', 'service_refs' => array( $service ), 'state' => 'active', 'verified_at' => $clock ),
		),
		'changes' => array(),
		'approvals' => array(),
		'questions' => array(),
		'vip_cases' => array(),
		'trip_care_receipts' => array(),
		'commerce_orders' => array(
			array(
				'order_ref' => ctsa_ref( 'order', 'source-assembler-order' ), 'service_refs' => array( $service ),
				'funds_state' => 'collected', 'funds_truth_state' => 'verified_current',
				'payment_state' => 'captured', 'payment_truth_state' => 'verified_current',
				'fulfillment_state' => 'confirmed', 'fulfillment_truth_state' => 'verified_current',
				'refund_state' => 'not_requested', 'refund_truth_state' => 'verified_current',
				'settlement_state' => 'pending', 'settlement_truth_state' => 'verified_current',
				'next_action' => null, 'verified_at' => $clock,
			),
		),
		'loyalty' => array( 'status' => 'not_requested', 'affected_service_refs' => array(), 'next_action' => null, 'verified_at' => $clock ),
		'traveler_readiness' => array(
			array( 'traveler_ref' => $traveler, 'subject_kind' => 'adult', 'readiness' => 'ready', 'pending_requirement_codes' => array(), 'next_action' => null, 'deadline' => null, 'verified_at' => $clock ),
		),
		'offline_pack' => array( 'status' => 'ready', 'itinerary' => 'ready', 'service_contacts' => 'ready', 'emergency_contacts' => 'ready', 'next_action' => null, 'verified_at' => $clock ),
	);
}

final class CTSA_Authoritative_Provider implements Tra_Vel_Customer_Trip_Cockpit_Source_Provider {
	public $snapshot;
	public $contexts = array();
	public function __construct( $snapshot ) { $this->snapshot = $snapshot; }
	public function get_authoritative_snapshot( $context ) {
		$this->contexts[] = $context;
		return $this->snapshot;
	}
}

final class CTSA_Memory_Store {
	public $record = null;
	public $commit_count = 0;
	public $authorization_results = array();
	public $sources = array();

	public function get_bound_projection( $trip_ref, $case_ref, $account_ref, $now = null ) {
		unset( $now );
		if ( null !== $case_ref || ! is_array( $this->record ) || $trip_ref !== $this->record['trip_ref'] || $account_ref !== $this->record['account_ref'] ) {
			return new WP_Error( 'tra_vel_customer_trip_cockpit_not_found', 'not found', array( 'status' => 404 ) );
		}
		return $this->record;
	}

	public function commit_server_source( $owner_user_id, $account_ref, $source, $now = null ) {
		$authorized = apply_filters( 'tra_vel_customer_trip_cockpit_source_write_authorized', false, $owner_user_id, $account_ref, $source );
		$this->authorization_results[] = $authorized;
		if ( true !== $authorized ) {
			return new WP_Error( 'tra_vel_customer_trip_cockpit_source_write_not_authorized', 'not authorized', array( 'status' => 403 ) );
		}
		$projection = Tra_Vel_Customer_Trip_Cockpit_Factory::create_projection( $source, $now );
		if ( is_wp_error( $projection ) ) {
			return $projection;
		}
		if ( is_array( $this->record ) ) {
			$successor = Tra_Vel_Customer_Trip_Cockpit_Policy::assert_successor( $this->record['projection'], $projection, $now );
			if ( is_wp_error( $successor ) ) {
				return $successor;
			}
		}
		$this->record = array(
			'projection' => $projection,
			'owner_user_id' => (int) $owner_user_id,
			'account_ref' => $account_ref,
			'trip_ref' => $projection['trip_ref'],
			'case_ref' => null,
			'owner_scope_digest' => $projection['owner_scope_digest'],
		);
		$this->sources[] = $source;
		$this->commit_count++;
		return array( 'projection' => $projection, 'created' => 1 === $this->commit_count, 'replayed' => false );
	}
}

add_filter( Tra_Vel_Customer_Trip_Cockpit_Source_Assembler::PROVIDER_FILTER, function ( $provider, $context ) {
	unset( $provider, $context );
	return $GLOBALS['ctsa_provider'];
}, 10, 2 );
add_filter( 'tra_vel_customer_trip_cockpit_assembler_clock', function ( $now, $context ) {
	unset( $now, $context );
	return $GLOBALS['ctsa_now'];
}, 10, 2 );

$owner     = 37;
$trip      = ctsa_ref( 'trip', 'source-assembler-trip' );
$event     = ctsa_ref( 'lifecycle_event', 'registration-created' );
$clock     = gmdate( 'Y-m-d\TH:i:s\Z', $GLOBALS['ctsa_now'] - 60 );
$provider  = new CTSA_Authoritative_Provider( ctsa_snapshot( $clock ) );
$store     = new CTSA_Memory_Store();
$assembler = new Tra_Vel_Customer_Trip_Cockpit_Source_Assembler( $store );
$GLOBALS['ctsa_provider'] = $provider;
$assembler->register_hooks();

/* One payload-free authoritative lifecycle event must populate the store. */
do_action( Tra_Vel_Customer_Trip_Cockpit_Source_Assembler::LIFECYCLE_ACTION, $owner, $trip, 'registration_changed', $event );
ctsa_assert( 1 === $store->commit_count && is_array( $store->record ), 'The authoritative lifecycle event did not produce a stored projection.' );
ctsa_assert( 1 === count( $provider->contexts ) && array( 'event_ref', 'event_kind', 'owner_user_id', 'account_ref', 'trip_ref' ) === array_keys( $provider->contexts[0] ), 'The source provider did not receive the exact closed server context.' );
ctsa_assert( $owner === $provider->contexts[0]['owner_user_id'] && $trip === $provider->contexts[0]['trip_ref'], 'The provider context lost the exact owner or trip binding.' );
ctsa_assert( Tra_Vel_Traveler_Principal::account_ref( $owner ) === $provider->contexts[0]['account_ref'], 'The assembler did not derive the account identity server-side.' );
ctsa_assert( true === $store->authorization_results[0], 'The assembler did not authorize its own exact synchronous write.' );
ctsa_assert( 1 === $store->record['projection']['revision'] && null === $store->record['projection']['previous_projection_digest'], 'The first source did not create exact initial ancestry.' );
ctsa_assert( $trip === $store->record['projection']['trip_ref'], 'The stored projection is not bound to the lifecycle trip.' );
ctsa_assert( 1 === preg_match( '/^tv_cockpit_[A-Za-z0-9_-]{16,96}$/', $store->record['projection']['cockpit_ref'] ), 'The assembler did not derive a valid cockpit identity.' );
ctsa_assert( false === strpos( wp_json_encode( $store->sources[0] ), $event ), 'Lifecycle transport metadata leaked into the durable source.' );

/* An exact event replay is a semantic no-op and must not create a revision. */
do_action( Tra_Vel_Customer_Trip_Cockpit_Source_Assembler::LIFECYCLE_ACTION, $owner, $trip, 'registration_changed', $event );
ctsa_assert( 1 === $store->commit_count && 1 === $store->record['projection']['revision'], 'An exact authoritative replay created a duplicate revision.' );

/* A newer registration fact must create an exact successor. */
$previous_digest = $store->record['projection']['projection_digest'];
$next_clock = gmdate( 'Y-m-d\TH:i:s\Z', $GLOBALS['ctsa_now'] - 30 );
$provider->snapshot['registration'] = array(
	'gate' => 'ready_to_reserve',
	'readiness' => 'attention_required',
	'pending_requirement_codes' => array( 'terms_accepted' ),
	'next_action' => ctsa_action( 'review_terms' ),
	'verified_at' => $next_clock,
);
do_action( Tra_Vel_Customer_Trip_Cockpit_Source_Assembler::LIFECYCLE_ACTION, $owner, $trip, 'registration_changed', ctsa_ref( 'lifecycle_event', 'registration-updated' ) );
ctsa_assert( 2 === $store->commit_count && 2 === $store->record['projection']['revision'], 'A newer registration lifecycle fact did not create revision two.' );
ctsa_assert( $previous_digest === $store->record['projection']['previous_projection_digest'], 'The successor did not bind the exact previous projection digest.' );
ctsa_assert( 'attention_required' === $store->record['projection']['current']['registration_readiness'], 'The stored customer projection did not carry the authoritative registration change.' );

/* The write-authorizer must be closed outside the assembler call stack. */
$outside = apply_filters( 'tra_vel_customer_trip_cockpit_source_write_authorized', false, $owner, Tra_Vel_Traveler_Principal::account_ref( $owner ), $store->sources[1] );
ctsa_assert( false === $outside, 'The source-write filter remained open after the synchronous commit.' );
$mutated = $store->sources[1];
$mutated['headline'] = 'Mutated outside the trusted assembler';
$outside_mutation = apply_filters( 'tra_vel_customer_trip_cockpit_source_write_authorized', false, $owner, Tra_Vel_Traveler_Principal::account_ref( $owner ), $mutated );
ctsa_assert( false === $outside_mutation, 'A mutated source was authorized outside the exact in-flight digest.' );

/* No provider means no fallback source and no write. */
$GLOBALS['ctsa_provider'] = null;
ctsa_error( $assembler->refresh( $owner, $trip, 'servicing_changed', ctsa_ref( 'lifecycle_event', 'provider-missing' ), $GLOBALS['ctsa_now'] ), 'provider_unavailable', 'A missing authoritative provider did not fail closed.' );
ctsa_assert( 2 === $store->commit_count, 'A missing provider produced a fallback write.' );

/* Providers cannot inject identity, ancestry, scope, or arbitrary payload fields. */
$GLOBALS['ctsa_provider'] = $provider;
$provider->snapshot['owner_scope_digest'] = hash( 'sha256', 'browser-controlled-scope' );
ctsa_error( $assembler->refresh( $owner, $trip, 'servicing_changed', ctsa_ref( 'lifecycle_event', 'identity-injection' ), $GLOBALS['ctsa_now'] ), 'snapshot_shape_invalid', 'A provider-controlled owner scope did not fail the closed snapshot contract.' );
unset( $provider->snapshot['owner_scope_digest'] );
ctsa_assert( 2 === $store->commit_count, 'An identity-injected snapshot reached storage.' );

/* Event kind and reference are closed before any provider read. */
$provider_reads = count( $provider->contexts );
ctsa_error( $assembler->refresh( $owner, $trip, 'browser_payload', ctsa_ref( 'lifecycle_event', 'bad-event-kind' ), $GLOBALS['ctsa_now'] ), 'event_invalid', 'An unknown lifecycle kind did not fail closed.' );
ctsa_error( $assembler->refresh( $owner, $trip, 'servicing_changed', 'trip-from-browser', $GLOBALS['ctsa_now'] ), 'event_invalid', 'A malformed lifecycle reference did not fail closed.' );
ctsa_assert( $provider_reads === count( $provider->contexts ), 'Invalid lifecycle input reached the authoritative provider.' );

echo 'Customer Trip Cockpit source assembler runtime passed (' . $ctsa_assertions . ' assertions; event-to-store lifecycle, replay, successor, and fail-closed boundaries).' . PHP_EOL;
