<?php
/** @package TraVelAgent */

defined( 'ABSPATH' ) || exit;

interface Tra_Vel_Commerce_Affiliate_Reporter extends Tra_Vel_Commerce_Settlement_Adapter {
	public function report_conversion( $order, $context );
}
