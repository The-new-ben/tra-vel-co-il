<?php
/**
 * Flight search runtime bootstrap.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/interface-flight-search-adapter.php';
require_once __DIR__ . '/class-demo-flight-search-adapter.php';
require_once __DIR__ . '/class-flight-search-registry.php';
require_once __DIR__ . '/class-flight-search-repository.php';
require_once __DIR__ . '/class-flight-search-controller.php';
