<?php
/**
 * Hotel search runtime bootstrap.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/interface-hotel-search-adapter.php';
require_once __DIR__ . '/class-demo-hotel-search-adapter.php';
require_once __DIR__ . '/class-hotel-search-registry.php';
require_once __DIR__ . '/class-hotel-search-repository.php';
require_once __DIR__ . '/class-hotel-search-controller.php';
