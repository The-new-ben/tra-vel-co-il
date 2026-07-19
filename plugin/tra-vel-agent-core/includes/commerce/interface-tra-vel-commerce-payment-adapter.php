<?php
/** @package TraVelAgent */

defined( 'ABSPATH' ) || exit;

interface Tra_Vel_Commerce_Payment_Adapter extends Tra_Vel_Commerce_Provider_Adapter {
	public function create_intent( $order, $command );
	public function confirm_intent( $intent, $command );
	public function capture( $intent, $command );
	public function void( $intent, $command );
	public function refund_payment( $intent, $command );
	public function verify_webhook( $headers, $body );
}
