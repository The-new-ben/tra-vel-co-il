<?php
/**
 * Bridge durable commercial intents to the theme-owned allowlisted handoff.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prepare an owned contact URL. Agent Core records the immutable handoff event
 * before the URL is returned to the browser.
 *
 * @param mixed $prepared Existing provider result.
 * @param array $context  Minimized commercial-intent context.
 * @param array $intent   Public intent contract (unused by the bridge).
 * @return array|WP_Error|mixed
 */
function tra_vel_v2_prepare_agent_commercial_intent_handoff( $prepared, $context, $intent ) {
	unset( $intent );
	if ( null !== $prepared ) {
		return $prepared;
	}
	if ( ! is_array( $context ) || ! function_exists( 'rest_do_request' ) ) {
		return new WP_Error( 'tra_vel_commercial_handoff_bridge_unavailable', 'The owned assisted-contact bridge is unavailable.' );
	}

	$request = new WP_REST_Request( 'POST', '/tra-vel/v2/handoffs/prepare' );
	foreach ( array( 'provider', 'vertical', 'offer_id', 'destination', 'origin', 'depart_date', 'return_date', 'travelers', 'budget', 'currency', 'product', 'return_path' ) as $field ) {
		if ( array_key_exists( $field, $context ) ) {
			$request->set_param( $field, $context[ $field ] );
		}
	}
	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$data = $response->get_data();
	if ( $response->get_status() >= 400 || ! is_array( $data ) || empty( $data['handoff_url'] ) ) {
		return new WP_Error( 'tra_vel_commercial_handoff_prepare_failed', 'The owned assisted-contact channel could not be prepared.', array( 'status' => max( 409, (int) $response->get_status() ) ) );
	}
	if ( 'owned' !== ( $data['provider']['relationship'] ?? '' ) || 'tra-vel-concierge' !== ( $data['provider']['id'] ?? '' ) ) {
		return new WP_Error( 'tra_vel_commercial_handoff_not_owned', 'Commercial intents can use only the owned Tra-Vel contact channel.', array( 'status' => 409 ) );
	}
	return array(
		'provider'    => 'tra-vel-concierge',
		'handoff_url' => (string) $data['handoff_url'],
		'expires_at'  => (string) $data['expires_at'],
	);
}
add_filter( 'tra_vel_agent_commercial_intent_prepare_handoff', 'tra_vel_v2_prepare_agent_commercial_intent_handoff', 10, 3 );
