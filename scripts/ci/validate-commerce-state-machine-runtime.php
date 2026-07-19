<?php
/** Runtime state-machine checks for Commerce Core. */

define( 'ABSPATH', __DIR__ );
class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }

$base = __DIR__ . '/../../plugin/tra-vel-agent-core/includes/commerce/';
require_once $base . 'class-tra-vel-commerce-taxonomy.php';
require_once $base . 'class-tra-vel-commerce-state-machine.php';

$assertions = 0;
function state_assert( $condition, $message ) {
	global $assertions;
	$assertions++;
	if ( ! $condition ) {
		fwrite( STDERR, "Commerce state machine failed: {$message}\n" );
		exit( 1 );
	}
}

$chains = array(
	'checkout' => array( 'draft', array( 'quote', 'quoted' ), array( 'request_approval', 'awaiting_approval' ), array( 'approve', 'ready' ) ),
	'payment' => array( 'not_started', array( 'start', 'pending' ), array( 'require_action', 'requires_action' ), array( 'authorize', 'authorized' ), array( 'capture', 'captured' ), array( 'partial_refund', 'partially_refunded' ), array( 'refund', 'refunded' ) ),
	'fulfillment' => array( 'selected', array( 'start_hold', 'hold_pending' ), array( 'hold', 'held' ), array( 'start_confirmation', 'confirmation_pending' ), array( 'confirm', 'confirmed' ), array( 'start_fulfillment', 'fulfillment_pending' ), array( 'fulfill', 'fulfilled' ), array( 'start_change', 'change_pending' ), array( 'change', 'changed' ), array( 'start_fulfillment', 'fulfillment_pending' ), array( 'fulfill', 'fulfilled' ), array( 'start_cancellation', 'cancellation_pending' ), array( 'cancel', 'cancelled' ) ),
	'settlement' => array( 'not_applicable', array( 'record_click', 'click_recorded' ), array( 'record_conversion', 'conversion_reported' ), array( 'mark_eligible', 'eligible' ), array( 'mark_payable', 'payable' ), array( 'mark_paid', 'paid' ) ),
	'operation' => array( 'queued', array( 'start', 'started' ), array( 'timeout', 'uncertain' ), array( 'reconcile', 'reconciled' ) ),
);

foreach ( $chains as $axis => $steps ) {
	$current = array_shift( $steps );
	foreach ( $steps as $step ) {
		$current = Tra_Vel_Commerce_State_Machine::transition( $axis, $current, $step[0] );
		state_assert( $step[1] === $current, "{$axis} {$step[0]} must reach {$step[1]}" );
	}
}

state_assert( is_wp_error( Tra_Vel_Commerce_State_Machine::transition( 'payment', 'not_started', 'capture' ) ), 'payment cannot capture before authorization' );
state_assert( is_wp_error( Tra_Vel_Commerce_State_Machine::transition( 'fulfillment', 'selected', 'confirm' ) ), 'fulfillment cannot confirm before hold' );
state_assert( 'uncertain' === Tra_Vel_Commerce_State_Machine::transition( 'operation', 'started', 'timeout' ), 'operation timeout must remain uncertain' );
state_assert( is_wp_error( Tra_Vel_Commerce_State_Machine::transition( 'operation', 'uncertain', 'succeed' ) ), 'uncertain operation must reconcile instead of claiming success' );
state_assert( 'processing' === Tra_Vel_Commerce_State_Machine::project( 'ready', 'captured', array( 'confirmed', 'confirmed' ), array( 'payable' ) ), 'supplier confirmation without issued entitlements must remain processing' );
state_assert( 'trip_confirmed' === Tra_Vel_Commerce_State_Machine::project( 'ready', 'captured', array( 'fulfilled', 'fulfilled' ), array( 'payable' ) ), 'issued fulfillment plus captured payment must project a confirmed trip' );
state_assert( 'attention_required' === Tra_Vel_Commerce_State_Machine::project( 'ready', 'authorized', array( 'reconciliation_required' ), array( 'not_applicable' ) ), 'uncertain fulfillment must project attention required' );
state_assert( 'cancelled_and_resolved' === Tra_Vel_Commerce_State_Machine::project( 'ready', 'refunded', array( 'cancelled', 'cancelled' ), array( 'reversed' ) ), 'cancelled items plus refunded payment must project resolved cancellation' );
state_assert( is_wp_error( Tra_Vel_Commerce_State_Machine::project( 'ready', 'captured', array( 'booked' ), array() ) ), 'invented fulfillment state must fail closed' );

echo "Commerce state machine passed ({$assertions} assertions).\n";
