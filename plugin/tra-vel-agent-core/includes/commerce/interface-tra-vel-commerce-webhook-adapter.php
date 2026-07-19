<?php
/** @package TraVelAgent */

defined( 'ABSPATH' ) || exit;

interface Tra_Vel_Commerce_Webhook_Adapter extends Tra_Vel_Commerce_Provider_Adapter {
	/** Verify signature and return a closed event envelope without processing it. */
	public function verify_webhook( $headers, $body );

	/** Reconcile one verified, deduplicated event with the current provider resource. */
	public function apply_webhook( $event, $context );
}
