<?php
/**
 * Optional Tra-Vel Agent Core data removal.
 *
 * Agent audit data is retained by default. Define TRA_VEL_AGENT_REMOVE_DATA as
 * true before uninstalling only after the required retention/export review.
 *
 * @package TraVelAgent
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'TRA_VEL_AGENT_REMOVE_DATA' ) || true !== TRA_VEL_AGENT_REMOVE_DATA ) {
	return;
}

global $wpdb;
foreach ( array( 'tra_vel_agent_approvals', 'tra_vel_agent_events', 'tra_vel_agent_runs', 'tra_vel_agent_limits' ) as $suffix ) {
	$table = $wpdb->prefix . $suffix;
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internally constructed table name.
}
delete_option( 'tra_vel_agent_db_version' );
delete_option( 'tra_vel_agent_openai_credential_v1' );
