<?php
/**
 * VIP traveler registration, authority, incident, and disruption contracts.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-tra-vel-vip-taxonomy.php';
require_once __DIR__ . '/class-tra-vel-vip-state-machine.php';
require_once __DIR__ . '/class-tra-vel-vip-policy.php';
require_once __DIR__ . '/class-tra-vel-vip-capability-session-policy.php';
require_once __DIR__ . '/class-tra-vel-vip-capability-session-store.php';
require_once __DIR__ . '/class-tra-vel-vip-capability-session-controller.php';
require_once __DIR__ . '/class-tra-vel-vip-intake-taxonomy.php';
require_once __DIR__ . '/class-tra-vel-vip-intake-policy.php';
require_once __DIR__ . '/class-tra-vel-vip-intake-state-projection.php';
require_once __DIR__ . '/class-tra-vel-vip-intake-store.php';
require_once __DIR__ . '/class-tra-vel-vip-intake-controller.php';
require_once __DIR__ . '/class-tra-vel-vip-intake-fanout-policy.php';
require_once __DIR__ . '/class-tra-vel-vip-intake-fanout-planner.php';
require_once __DIR__ . '/class-tra-vel-traveler-principal.php';
require_once __DIR__ . '/class-tra-vel-traveler-profile-taxonomy.php';
require_once __DIR__ . '/class-tra-vel-traveler-profile-policy.php';
require_once __DIR__ . '/class-tra-vel-traveler-registration-schema.php';
require_once __DIR__ . '/class-tra-vel-traveler-registration-store.php';
require_once __DIR__ . '/class-tra-vel-traveler-registration-controller.php';
require_once __DIR__ . '/class-tra-vel-trip-dependency-taxonomy.php';
require_once __DIR__ . '/class-tra-vel-trip-dependency-policy.php';
require_once __DIR__ . '/class-tra-vel-trip-recovery-planner.php';
require_once __DIR__ . '/class-tra-vel-customer-trip-cockpit-policy.php';
require_once __DIR__ . '/class-tra-vel-customer-trip-cockpit-factory.php';
require_once __DIR__ . '/class-tra-vel-customer-trip-cockpit-customer-view-policy.php';
require_once __DIR__ . '/class-tra-vel-customer-trip-cockpit-customer-view-factory.php';
require_once __DIR__ . '/interface-tra-vel-customer-trip-cockpit-read-model-provider.php';
require_once __DIR__ . '/interface-tra-vel-customer-trip-cockpit-source-provider.php';
require_once __DIR__ . '/class-tra-vel-customer-trip-cockpit-store.php';
require_once __DIR__ . '/class-tra-vel-customer-trip-cockpit-source-assembler.php';
require_once __DIR__ . '/class-tra-vel-customer-trip-cockpit-authoritative-source-provider.php';
require_once __DIR__ . '/class-tra-vel-customer-trip-cockpit-lifecycle-emitter.php';
require_once __DIR__ . '/class-tra-vel-customer-trip-cockpit-assisted-snapshot-provider.php';
require_once __DIR__ . '/class-tra-vel-customer-trip-cockpit-controller.php';
