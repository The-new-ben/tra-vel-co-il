<?php
/** @package TraVelAgent */

defined( 'ABSPATH' ) || exit;

interface Tra_Vel_Commerce_Search_Adapter extends Tra_Vel_Commerce_Provider_Adapter {
	/** Search one canonical request and return supplier candidates or WP_Error. */
	public function search( $request, $context );
}
