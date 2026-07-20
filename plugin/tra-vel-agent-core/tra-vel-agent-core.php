<?php
/**
 * Plugin Name: Tra-Vel Agent Core
 * Description: Private AI travel planning plus durable, consented assisted-quote operations for Tra-Vel.
 * Version: 0.9.1
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Tra-Vel
 * Text Domain: tra-vel-agent
 */

defined( 'ABSPATH' ) || exit;

define( 'TRA_VEL_AGENT_VERSION', '0.9.1' );
define( 'TRA_VEL_AGENT_FILE', __FILE__ );
define( 'TRA_VEL_AGENT_PATH', __DIR__ );

require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-credential-vault.php';
require_once TRA_VEL_AGENT_PATH . '/includes/interface-tra-vel-agent-provider.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-openai-provider.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-policy.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-store.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-controller.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-quote-case-policy.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-quote-case-store.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-quote-case-capabilities.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-quote-case-controller.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-quote-case-admin.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-commercial-intent-policy.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-commercial-intent-store.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-commercial-intent-controller.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-assisted-proposal-policy.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-assisted-proposal-composer.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-assisted-proposal-store.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-assisted-proposal-controller.php';
require_once TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-notifier.php';
require_once TRA_VEL_AGENT_PATH . '/includes/commerce/bootstrap.php';
require_once TRA_VEL_AGENT_PATH . '/includes/vip/bootstrap.php';
require_once TRA_VEL_AGENT_PATH . '/includes/local-tourism/bootstrap.php';
require_once TRA_VEL_AGENT_PATH . '/includes/servicing/bootstrap.php';
require_once TRA_VEL_AGENT_PATH . '/includes/operations/bootstrap.php';

register_activation_hook(
	TRA_VEL_AGENT_FILE,
	static function () {
		Tra_Vel_Agent_Store::install();
		Tra_Vel_Quote_Case_Store::install();
		Tra_Vel_Assisted_Proposal_Store::install();
		Tra_Vel_Commercial_Intent_Store::install();
		Tra_Vel_VIP_Intake_Store::install();
		Tra_Vel_VIP_Capability_Session_Store::install();
		Tra_Vel_Traveler_Registration_Store::install();
		Tra_Vel_Customer_Trip_Cockpit_Store::install();
		Tra_Vel_Quote_Case_Capabilities::install();
		if ( ! wp_next_scheduled( 'tra_vel_agent_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'tra_vel_agent_cleanup' );
		}
	}
);

register_deactivation_hook(
	TRA_VEL_AGENT_FILE,
	static function () {
		wp_clear_scheduled_hook( 'tra_vel_agent_cleanup' );
		if ( function_exists( 'wp_unschedule_hook' ) ) {
			wp_unschedule_hook( 'tra_vel_quote_case_sync_retry' );
		}
	}
);

add_action( 'tra_vel_agent_cleanup', array( 'Tra_Vel_Agent_Store', 'cleanup_expired' ) );
add_action( 'tra_vel_agent_cleanup', array( 'Tra_Vel_Assisted_Proposal_Store', 'cleanup' ), 9 );
add_action( 'tra_vel_agent_cleanup', array( 'Tra_Vel_Quote_Case_Store', 'cleanup' ), 10 );
add_action( 'tra_vel_agent_cleanup', array( 'Tra_Vel_Commercial_Intent_Store', 'cleanup' ) );
add_action( 'tra_vel_agent_cleanup', array( 'Tra_Vel_VIP_Intake_Store', 'cleanup' ) );
add_action( 'tra_vel_agent_cleanup', array( 'Tra_Vel_VIP_Capability_Session_Store', 'cleanup' ) );
add_action( 'tra_vel_agent_cleanup', array( 'Tra_Vel_Traveler_Registration_Store', 'cleanup' ) );
add_action( 'tra_vel_agent_cleanup', array( 'Tra_Vel_Customer_Trip_Cockpit_Store', 'cleanup' ) );

add_action(
	'plugins_loaded',
	static function () {
		Tra_Vel_Agent_Store::maybe_upgrade();
		Tra_Vel_Quote_Case_Store::maybe_upgrade();
		Tra_Vel_Assisted_Proposal_Store::maybe_upgrade();
		Tra_Vel_Commercial_Intent_Store::maybe_upgrade();
		Tra_Vel_VIP_Intake_Store::maybe_upgrade();
		Tra_Vel_VIP_Capability_Session_Store::maybe_upgrade();
		Tra_Vel_Traveler_Registration_Store::maybe_upgrade();
		Tra_Vel_Customer_Trip_Cockpit_Store::maybe_upgrade();
		( new Tra_Vel_Customer_Trip_Cockpit_Source_Assembler() )->register_hooks();
		( new Tra_Vel_Customer_Trip_Cockpit_Authoritative_Source_Provider() )->register_hooks();
		( new Tra_Vel_Customer_Trip_Cockpit_Lifecycle_Emitter() )->register_hooks();
		( new Tra_Vel_Customer_Trip_Cockpit_Assisted_Snapshot_Provider() )->register_hooks();
		( new Tra_Vel_Agent_Notifier() )->register_hooks();
		Tra_Vel_Quote_Case_Capabilities::maybe_install();
	}
);

/**
 * Theme 1.21.0 gates its Trip Cockpit UI on this filter. Agent Core 0.8.0
 * ships a truthful assisted-state feed behind the cockpit REST route, so the
 * plugin now declares that feed available.
 */
add_filter( 'tra_vel_v2_cockpit_feed_available', '__return_true' );

add_action(
	'tra_vel_agent_run_revised',
	static function ( $run ) {
		if ( is_array( $run ) ) {
			( new Tra_Vel_Quote_Case_Store() )->sync_from_run( $run );
		}
	},
	10,
	1
);

add_action(
	'tra_vel_quote_case_sync_retry',
	array( 'Tra_Vel_Quote_Case_Store', 'run_scheduled_sync' ),
	10,
	3
);

add_action(
	'rest_api_init',
	static function () {
		$provider = apply_filters( 'tra_vel_agent_request_provider', null );
		$controller = new Tra_Vel_Agent_Controller( null, $provider instanceof Tra_Vel_Agent_Provider ? $provider : null );
		$controller->register_routes();
		( new Tra_Vel_Quote_Case_Controller() )->register_routes();
		( new Tra_Vel_Commercial_Intent_Controller() )->register_routes();
		( new Tra_Vel_Assisted_Proposal_Controller() )->register_routes();
		( new Tra_Vel_Israel_Benefit_Controller() )->register_routes();
		( new Tra_Vel_VIP_Intake_Controller() )->register_routes();
		( new Tra_Vel_VIP_Capability_Session_Controller() )->register_routes();
		( new Tra_Vel_Traveler_Registration_Controller() )->register_routes();
		( new Tra_Vel_Customer_Trip_Cockpit_Controller() )->register_routes();
	}
);

if ( is_admin() ) {
	( new Tra_Vel_Quote_Case_Admin() )->register_hooks();
}
