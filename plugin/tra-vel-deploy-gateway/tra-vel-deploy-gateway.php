<?php
/**
 * Plugin Name: Tra-Vel Deploy Gateway
 * Description: Restricted REST deployment gateway for the Tra-Vel V2 theme.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Tra-Vel
 * Text Domain: tra-vel-deploy
 */

defined( 'ABSPATH' ) || exit;

define( 'TRA_VEL_DEPLOY_VERSION', '0.1.0' );
define( 'TRA_VEL_DEPLOY_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-tra-vel-deploy-controller.php';

add_action(
	'rest_api_init',
	static function () {
		$controller = new Tra_Vel_Deploy_Controller();
		$controller->register_routes();
	}
);
