<?php
/** @package TraVelAgent */

defined( 'ABSPATH' ) || exit;

interface Tra_Vel_Commerce_Fulfillment_Adapter extends Tra_Vel_Commerce_Provider_Adapter {
	public function reserve( $offer, $command );
	public function confirm( $hold, $command );
	public function fulfill( $confirmation, $command );
	public function quote_change( $fulfillment, $command );
	public function change( $change_quote, $command );
	public function quote_cancellation( $fulfillment, $command );
	public function cancel( $cancellation_quote, $command );
	public function refund( $fulfillment, $command );
}
