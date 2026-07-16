<?php
/**
 * Contract for hotel inventory suppliers.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

interface Tra_Vel_V2_Hotel_Search_Adapter {
	public function get_id();
	public function is_configured();
	public function get_mode();
	public function get_cache_version();
	public function search( $query );
}
