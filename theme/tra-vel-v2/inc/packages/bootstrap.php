<?php
/**
 * Total-trip package runtime bootstrap.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/interface-trip-package-adapter.php';
require_once __DIR__ . '/class-demo-trip-package-adapter.php';
require_once __DIR__ . '/class-trip-package-registry.php';
require_once __DIR__ . '/class-trip-package-repository.php';
require_once __DIR__ . '/class-trip-package-controller.php';
