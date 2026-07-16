<?php
/**
 * Insurance comparison runtime bootstrap.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/interface-insurance-quote-adapter.php';
require_once __DIR__ . '/class-demo-insurance-quote-adapter.php';
require_once __DIR__ . '/class-insurance-quote-registry.php';
require_once __DIR__ . '/class-insurance-quote-repository.php';
require_once __DIR__ . '/class-insurance-quote-controller.php';
