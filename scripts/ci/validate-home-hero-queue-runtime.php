<?php
/**
 * Runtime validation for the homepage campaign selector.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );

function current_time( $format ) {
	if ( 'Y-m-d' !== $format ) {
		throw new RuntimeException( 'Unexpected current_time format.' );
	}

	return '2026-07-16';
}

require TRA_VEL_V2_PATH . '/inc/hero-queue.php';

$campaign = tra_vel_v2_get_home_hero_campaign();

if ( 'greece-summer-decisions' !== ( $campaign['id'] ?? '' ) ) {
	fwrite( STDERR, "The expected summer campaign was not selected.\n" );
	exit( 1 );
}

if ( false !== ( $campaign['price_claim'] ?? null ) ) {
	fwrite( STDERR, "The selected campaign contains an unverified price claim.\n" );
	exit( 1 );
}

if ( 'athens' !== ( $campaign['map_state'] ?? '' ) ) {
	fwrite( STDERR, "The summer campaign does not focus Athens on the map.\n" );
	exit( 1 );
}

if ( 0 !== strpos( $campaign['primary_url'] ?? '', '/' ) || 0 !== strpos( $campaign['secondary_url'] ?? '', '/' ) ) {
	fwrite( STDERR, "Campaign links must remain internal.\n" );
	exit( 1 );
}

echo "Homepage hero queue runtime validation passed.\n";
