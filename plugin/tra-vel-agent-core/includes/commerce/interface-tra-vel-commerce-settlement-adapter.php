<?php
/** @package TraVelAgent */

defined( 'ABSPATH' ) || exit;

interface Tra_Vel_Commerce_Settlement_Adapter extends Tra_Vel_Commerce_Provider_Adapter {
	public function reconcile_settlement( $period, $context );
}
