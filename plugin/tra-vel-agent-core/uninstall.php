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
foreach ( array( 'tra_vel_customer_trip_cockpit_limits', 'tra_vel_customer_trip_cockpit_revisions', 'tra_vel_customer_trip_cockpits', 'tra_vel_vip_capability_limits', 'tra_vel_vip_capability_exchanges', 'tra_vel_vip_capability_sessions', 'tra_vel_vip_capability_grants', 'tra_vel_traveler_registration_idempotency', 'tra_vel_traveler_registration_transitions', 'tra_vel_traveler_registration_revisions', 'tra_vel_traveler_registrations', 'tra_vel_vip_intake_limits', 'tra_vel_vip_intake_idempotency', 'tra_vel_vip_intake_receipts', 'tra_vel_vip_intakes', 'tra_vel_assisted_proposal_idempotency', 'tra_vel_assisted_proposal_events', 'tra_vel_assisted_proposal_sources', 'tra_vel_assisted_proposal_revisions', 'tra_vel_assisted_proposals', 'tra_vel_commercial_intent_idempotency', 'tra_vel_commercial_intent_events', 'tra_vel_commercial_intents', 'tra_vel_quote_case_idempotency', 'tra_vel_quote_case_events', 'tra_vel_quote_case_revisions', 'tra_vel_quote_cases', 'tra_vel_agent_approvals', 'tra_vel_agent_events', 'tra_vel_agent_runs', 'tra_vel_agent_limits' ) as $suffix ) {
	$table = $wpdb->prefix . $suffix;
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internally constructed table name.
}
delete_option( 'tra_vel_agent_db_version' );
delete_option( 'tra_vel_agent_openai_credential_v1' );
delete_option( 'tra_vel_agent_notification_webhook_v1' );
delete_option( 'tra_vel_quote_case_db_version' );
delete_option( 'tra_vel_commercial_intent_db_version' );
delete_option( 'tra_vel_commercial_intent_cleanup_status' );
delete_option( 'tra_vel_assisted_proposal_db_version' );
delete_option( 'tra_vel_assisted_proposal_cleanup_status' );
delete_option( 'tra_vel_vip_intake_db_version' );
delete_option( 'tra_vel_vip_intake_cleanup_status' );
delete_option( 'tra_vel_vip_capability_session_db_version' );
delete_option( 'tra_vel_vip_capability_session_cleanup_status' );
delete_option( 'tra_vel_vip_capability_session_readiness_cache' );
delete_option( 'tra_vel_traveler_registration_db_version' );
delete_option( 'tra_vel_traveler_registration_cleanup_status' );
delete_option( 'tra_vel_customer_trip_cockpit_db_version' );
delete_option( 'tra_vel_customer_trip_cockpit_cleanup_status' );
delete_option( 'tra_vel_customer_trip_cockpit_readiness_cache' );

require_once __DIR__ . '/includes/class-tra-vel-quote-case-capabilities.php';
Tra_Vel_Quote_Case_Capabilities::uninstall();
