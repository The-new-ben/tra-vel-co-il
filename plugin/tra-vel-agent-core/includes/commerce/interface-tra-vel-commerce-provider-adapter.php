<?php
/** @package TraVelAgent */

defined( 'ABSPATH' ) || exit;

interface Tra_Vel_Commerce_Provider_Adapter {
	/** Return the closed, credential-free provider descriptor. */
	public function get_descriptor();
}
