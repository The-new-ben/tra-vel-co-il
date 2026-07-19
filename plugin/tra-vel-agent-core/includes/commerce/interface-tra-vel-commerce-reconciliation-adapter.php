<?php
/** @package TraVelAgent */

defined( 'ABSPATH' ) || exit;

interface Tra_Vel_Commerce_Reconciliation_Adapter extends Tra_Vel_Commerce_Provider_Adapter {
	/** Retrieve and reconcile the current provider resource after an uncertain result. */
	public function reconcile_resource( $resource_type, $resource_reference, $context );
}
