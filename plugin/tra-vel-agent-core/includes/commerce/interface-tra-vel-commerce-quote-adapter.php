<?php
/** @package TraVelAgent */

defined( 'ABSPATH' ) || exit;

interface Tra_Vel_Commerce_Quote_Adapter extends Tra_Vel_Commerce_Provider_Adapter {
	/** Revalidate one server-owned offer snapshot. */
	public function revalidate( $offer, $context );
}
