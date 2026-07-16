<?php
/**
 * Tra-Vel V2 bootstrap.
 *
 * @package TraVelV2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TRA_VEL_V2_VERSION', '1.4.1' );
define( 'TRA_VEL_V2_PATH', get_template_directory() );
define( 'TRA_VEL_V2_URI', get_template_directory_uri() );

require_once TRA_VEL_V2_PATH . '/inc/setup.php';
require_once TRA_VEL_V2_PATH . '/inc/assets.php';
require_once TRA_VEL_V2_PATH . '/inc/template-tags.php';
require_once TRA_VEL_V2_PATH . '/inc/guides.php';
require_once TRA_VEL_V2_PATH . '/inc/seo.php';
require_once TRA_VEL_V2_PATH . '/inc/suppliers/bootstrap.php';
require_once TRA_VEL_V2_PATH . '/inc/discovery.php';
require_once TRA_VEL_V2_PATH . '/inc/flights/bootstrap.php';
require_once TRA_VEL_V2_PATH . '/inc/hotels/bootstrap.php';
require_once TRA_VEL_V2_PATH . '/inc/insurance/bootstrap.php';
require_once TRA_VEL_V2_PATH . '/inc/packages/bootstrap.php';
require_once TRA_VEL_V2_PATH . '/inc/workspace/bootstrap.php';
require_once TRA_VEL_V2_PATH . '/inc/handoffs/bootstrap.php';
