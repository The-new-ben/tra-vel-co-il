<?php
/** Minimal dbDelta bridge for no-WordPress store runtime tests. */

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $sql ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		global $wpdb;
		return $wpdb->db_delta( $sql );
	}
}
