<?php
/**
 * Deterministic Open-Meteo commercial adapter normalization test.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_V2_VERSION', 'test' );
define( 'TRA_VEL_V2_PATH', dirname( __DIR__, 2 ) . '/theme/tra-vel-v2' );
define( 'TRA_VEL_OPEN_METEO_API_KEY', 'test-commercial-key-never-ship' );

$GLOBALS['tv2_weather_request_url'] = '';

class WP_Error {
	private $code;
	public function __construct( $code ) {
		$this->code = $code;
	}
	public function get_error_code() {
		return $this->code;
	}
}
function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}
function wp_parse_url( $url ) {
	return parse_url( $url );
}
function esc_url_raw( $url ) {
	return filter_var( $url, FILTER_SANITIZE_URL );
}
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function add_query_arg( $args, $url ) {
	return $url . '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
}
function wp_safe_remote_get( $url, $args ) {
	unset( $args );
	$GLOBALS['tv2_weather_request_url'] = $url;
	$items = array();
	for ( $index = 0; $index < 6; ++$index ) {
		$items[] = array(
			'current' => array(
				'time'                 => '2026-07-16T15:00',
				'temperature_2m'       => 20.4 + $index,
				'apparent_temperature' => 19.6 + $index,
				'weather_code'         => 61,
				'is_day'               => 1,
			),
		);
	}
	return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $items ) );
}
function wp_remote_retrieve_response_code( $response ) {
	return $response['response']['code'];
}
function wp_remote_retrieve_body( $response ) {
	return $response['body'];
}

require TRA_VEL_V2_PATH . '/inc/suppliers/interface-supplier-adapter.php';
require TRA_VEL_V2_PATH . '/inc/suppliers/class-open-meteo-supplier-adapter.php';

function tv2_weather_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Open-Meteo adapter validation failed: {$message}\n" );
		exit( 1 );
	}
}

$adapter = new Tra_Vel_V2_Open_Meteo_Supplier_Adapter();
$payload = $adapter->fetch( array() );
$parts   = parse_url( $GLOBALS['tv2_weather_request_url'] );
parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $query );

tv2_weather_assert( $adapter->is_configured(), 'commercial adapter did not detect its key' );
tv2_weather_assert( ! is_wp_error( $payload ), 'adapter returned an error' );
tv2_weather_assert( 'customer-api.open-meteo.com' === $parts['host'], 'request did not use the commercial endpoint' );
tv2_weather_assert( TRA_VEL_OPEN_METEO_API_KEY === $query['apikey'], 'commercial key was not sent to the supplier' );
tv2_weather_assert( 6 === count( explode( ',', $query['latitude'] ) ), 'multi-location request does not contain six coordinates' );
tv2_weather_assert( 6 === count( $payload['destinations'] ), 'normalized response does not contain six destinations' );
tv2_weather_assert( true === $payload['provider_status']['weather']['connected'], 'weather provider was not marked connected' );
tv2_weather_assert( 'live' === $payload['provider_status']['weather']['readiness'], 'weather provider was not marked live' );
tv2_weather_assert( 'גשם' === $payload['destinations'][0]['weather']['condition'], 'WMO weather code was not normalized' );
tv2_weather_assert( 20 === $payload['destinations'][0]['weather']['temperature_c'], 'temperature was not normalized' );
tv2_weather_assert( true === $payload['destinations'][0]['weather']['live'], 'current conditions lack a field-level live marker' );
tv2_weather_assert( 'Open-Meteo' === $payload['destinations'][0]['weather']['source'], 'current conditions lost their source' );
tv2_weather_assert( ! array_key_exists( 'season_fit', $payload['destinations'][0]['weather'] ), 'current conditions falsely supplied an editorial season fit' );
tv2_weather_assert( false === strpos( json_encode( $payload ), TRA_VEL_OPEN_METEO_API_KEY ), 'API key leaked into normalized output' );

echo "Tra-Vel Open-Meteo adapter validation passed (commercial endpoint, field provenance, multi-location normalization, no key leakage).\n";
