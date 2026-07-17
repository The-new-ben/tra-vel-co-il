<?php
/**
 * Plugin Name: Tra-Vel Agent Core
 * Description: Private AI travel-request, run-event, proposal, and approval foundation for Tra-Vel.
 * Version: 0.2.1
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Tra-Vel
 * Text Domain: tra-vel-agent
 */

defined( 'ABSPATH' ) || exit;

define( 'TRA_VEL_AGENT_VERSION', '0.2.1' );
define( 'TRA_VEL_AGENT_FILE', __FILE__ );
define( 'TRA_VEL_AGENT_PATH', __DIR__ );

require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-credential-vault.php';
require_once TRA_VEL_AGENT_PATH . '/includes/interface-tra-vel-agent-provider.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-openai-provider.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-policy.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-store.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-controller.php';

register_activation_hook(
	TRA_VEL_AGENT_FILE,
	static function () {
		Tra_Vel_Agent_Store::install();
		if ( ! wp_next_scheduled( 'tra_vel_agent_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'tra_vel_agent_cleanup' );
		}
	}
);

register_deactivation_hook(
	TRA_VEL_AGENT_FILE,
	static function () {
		wp_clear_scheduled_hook( 'tra_vel_agent_cleanup' );
	}
);

add_action( 'tra_vel_agent_cleanup', array( 'Tra_Vel_Agent_Store', 'cleanup_expired' ) );

add_action(
	'plugins_loaded',
	static function () {
		Tra_Vel_Agent_Store::maybe_upgrade();
	}
);

add_action(
	'rest_api_init',
	static function () {
		$provider = apply_filters( 'tra_vel_agent_request_provider', null );
		$controller = new Tra_Vel_Agent_Controller( null, $provider instanceof Tra_Vel_Agent_Provider ? $provider : null );
		$controller->register_routes();
	}
);
