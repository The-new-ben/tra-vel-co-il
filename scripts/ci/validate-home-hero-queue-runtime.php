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

if ( 'seasonal' !== ( $campaign['kind'] ?? '' ) ) {
	fwrite( STDERR, "The summer campaign is not explicitly seasonal.\n" );
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

$payload = json_decode( file_get_contents( TRA_VEL_V2_PATH . '/assets/data/home-hero-queue.json' ), true );
$campaigns = is_array( $payload['campaigns'] ?? null ) ? $payload['campaigns'] : array();
$evergreen_gap = tra_vel_v2_select_home_hero_campaign( $campaigns, '2027-04-15' );
$evergreen_future = tra_vel_v2_select_home_hero_campaign( $campaigns, '2035-01-01' );

if ( 'evergreen-map-discovery' !== ( $evergreen_gap['id'] ?? '' ) || 'evergreen' !== ( $evergreen_gap['kind'] ?? '' ) ) {
	fwrite( STDERR, "The date-gap fixture did not select the explicit evergreen fallback.\n" );
	exit( 1 );
}

if ( 'evergreen-map-discovery' !== ( $evergreen_future['id'] ?? '' ) || 'evergreen' !== ( $evergreen_future['kind'] ?? '' ) ) {
	fwrite( STDERR, "The evergreen fallback expired in the future fixture.\n" );
	exit( 1 );
}

$unsafe_seasonal = $campaigns[0];
$unsafe_seasonal['price_claim'] = true;
$fallback_only = tra_vel_v2_select_home_hero_campaign( array( $unsafe_seasonal, $evergreen_future ), '2026-07-16' );
if ( 'evergreen-map-discovery' !== ( $fallback_only['id'] ?? '' ) ) {
	fwrite( STDERR, "A price-led seasonal campaign did not fail closed to evergreen.\n" );
	exit( 1 );
}

$malformed_seasonal = $campaigns[0];
unset( $malformed_seasonal['map_state'] );
$unknown_kind = $campaigns[0];
$unknown_kind['kind'] = 'rolling';
$invalid_fixtures = tra_vel_v2_select_home_hero_campaign( array( $malformed_seasonal, $unknown_kind, $evergreen_future ), '2026-07-16' );
if ( 'evergreen-map-discovery' !== ( $invalid_fixtures['id'] ?? '' ) ) {
	fwrite( STDERR, "Malformed or unknown campaign kinds did not fail closed to evergreen.\n" );
	exit( 1 );
}

$destination_biased_evergreen = $evergreen_future;
$destination_biased_evergreen['map_state'] = 'bangkok';
if ( array() !== tra_vel_v2_select_home_hero_campaign( array( $destination_biased_evergreen ), '2035-01-01' ) ) {
	fwrite( STDERR, "A destination-biased evergreen campaign was accepted.\n" );
	exit( 1 );
}

$destination_ids = array( 'budapest', 'prague', 'vienna', 'athens', 'dubai', 'bangkok', 'tokyo', 'lisbon' );
$daily_seed = tra_vel_v2_select_home_discovery_destination( $destination_ids, '2027-04-15' );
if ( ! in_array( $daily_seed, $destination_ids, true ) || $daily_seed !== tra_vel_v2_select_home_discovery_destination( $destination_ids, '2027-04-15' ) ) {
	fwrite( STDERR, "The evergreen discovery seed is not stable and supported for a given date.\n" );
	exit( 1 );
}

$daily_destinations = array();
$cursor = new DateTimeImmutable( '2027-04-15' );
for ( $offset = 0; $offset < 16; $offset++ ) {
	$daily_destinations[] = tra_vel_v2_select_home_discovery_destination( $destination_ids, $cursor->modify( "+{$offset} days" )->format( 'Y-m-d' ) );
}
if ( count( array_unique( $daily_destinations ) ) < 2 ) {
	fwrite( STDERR, "The evergreen discovery seed permanently favors one destination.\n" );
	exit( 1 );
}

if ( '' !== tra_vel_v2_select_home_discovery_destination( $destination_ids, 'not-a-date' ) || '' !== tra_vel_v2_select_home_discovery_destination( array(), '2027-04-15' ) ) {
	fwrite( STDERR, "The evergreen discovery seed did not fail closed for invalid input.\n" );
	exit( 1 );
}

echo "Homepage hero queue runtime validation passed.\n";
