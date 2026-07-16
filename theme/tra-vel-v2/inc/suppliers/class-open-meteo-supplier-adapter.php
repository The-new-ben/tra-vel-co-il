<?php
/**
 * Open-Meteo commercial weather adapter.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Open_Meteo_Supplier_Adapter implements Tra_Vel_V2_Supplier_Adapter {
	const DEFAULT_ENDPOINT = 'https://customer-api.open-meteo.com/v1/forecast';

	public function get_id() {
		return 'open_meteo_commercial';
	}

	public function get_verticals() {
		return array( 'weather' );
	}

	public function is_configured() {
		$key      = $this->get_api_key();
		$endpoint = $this->get_endpoint();
		$parts    = wp_parse_url( $endpoint );
		return '' !== $key
			&& is_array( $parts )
			&& 'https' === ( isset( $parts['scheme'] ) ? $parts['scheme'] : '' )
			&& 'customer-api.open-meteo.com' === ( isset( $parts['host'] ) ? $parts['host'] : '' );
	}

	public function get_mode() {
		return 'live';
	}

	public function get_cache_version() {
		return 'open-meteo-current-v1';
	}

	public function fetch( $context ) {
		unset( $context );
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'open_meteo_not_configured', 'Open-Meteo commercial access is not configured.' );
		}

		$destinations = $this->load_destinations();
		if ( is_wp_error( $destinations ) ) {
			return $destinations;
		}

		$latitudes  = array();
		$longitudes = array();
		foreach ( $destinations as $destination ) {
			$latitudes[]  = $destination['geo']['latitude'];
			$longitudes[] = $destination['geo']['longitude'];
		}

		$url = add_query_arg(
			array(
				'latitude'  => implode( ',', $latitudes ),
				'longitude' => implode( ',', $longitudes ),
				'current'   => 'temperature_2m,apparent_temperature,weather_code,is_day',
				'timezone'  => 'auto',
				'apikey'    => $this->get_api_key(),
			),
			$this->get_endpoint()
		);

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 1,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'open_meteo_request_failed', 'Open-Meteo request failed.' );
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'open_meteo_bad_status', 'Open-Meteo returned an unexpected status.' );
		}

		$weather = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $weather['current'] ) ) {
			$weather = array( $weather );
		}
		if ( ! is_array( $weather ) || count( $weather ) !== count( $destinations ) ) {
			return new WP_Error( 'open_meteo_invalid_response', 'Open-Meteo returned an invalid location set.' );
		}

		$updates     = array();
		$observed_at = null;
		foreach ( $destinations as $index => $destination ) {
			$current = isset( $weather[ $index ]['current'] ) ? $weather[ $index ]['current'] : array();
			if ( ! isset( $current['temperature_2m'], $current['weather_code'] ) || ! is_numeric( $current['temperature_2m'] ) ) {
				return new WP_Error( 'open_meteo_invalid_current', 'Open-Meteo current conditions are incomplete.' );
			}
			$observed_at = isset( $current['time'] ) ? sanitize_text_field( $current['time'] ) : gmdate( 'c' );
			$updates[]   = array(
				'id'      => $destination['id'],
				'weather' => array(
					'temperature_c'        => (int) round( (float) $current['temperature_2m'] ),
					'apparent_temperature_c' => isset( $current['apparent_temperature'] ) ? (int) round( (float) $current['apparent_temperature'] ) : null,
					'condition'            => $this->condition_label( (int) $current['weather_code'] ),
					'weather_code'         => (int) $current['weather_code'],
					'is_day'               => ! empty( $current['is_day'] ),
					'observed_at'          => $observed_at,
					'live'                 => true,
					'source'               => 'Open-Meteo',
				),
			);
		}

		return array(
			'provider_status' => array(
				'weather' => array(
					'connected'       => true,
					'adapter'         => $this->get_id(),
					'readiness'       => 'live',
					'observed_at'     => $observed_at,
					'attribution'     => 'Weather data by Open-Meteo (CC BY 4.0)',
					'attribution_url' => 'https://open-meteo.com/',
					'license_url'     => 'https://creativecommons.org/licenses/by/4.0/',
				),
			),
			'destinations' => $updates,
		);
	}

	private function load_destinations() {
		$path = TRA_VEL_V2_PATH . '/assets/data/discovery-demo.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'open_meteo_locations_missing', 'Weather locations are unavailable.' );
		}
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) || empty( $data['destinations'] ) ) {
			return new WP_Error( 'open_meteo_locations_invalid', 'Weather locations are invalid.' );
		}
		foreach ( $data['destinations'] as $destination ) {
			if ( empty( $destination['id'] ) || ! isset( $destination['geo']['latitude'], $destination['geo']['longitude'] ) ) {
				return new WP_Error( 'open_meteo_coordinates_missing', 'A weather location is missing coordinates.' );
			}
		}
		return $data['destinations'];
	}

	private function get_api_key() {
		return defined( 'TRA_VEL_OPEN_METEO_API_KEY' ) ? trim( (string) TRA_VEL_OPEN_METEO_API_KEY ) : '';
	}

	private function get_endpoint() {
		return defined( 'TRA_VEL_OPEN_METEO_ENDPOINT' ) ? esc_url_raw( TRA_VEL_OPEN_METEO_ENDPOINT ) : self::DEFAULT_ENDPOINT;
	}

	private function condition_label( $code ) {
		if ( 0 === $code ) {
			return 'בהיר';
		}
		if ( in_array( $code, array( 1, 2, 3 ), true ) ) {
			return 'מעונן חלקית';
		}
		if ( in_array( $code, array( 45, 48 ), true ) ) {
			return 'ערפל';
		}
		if ( $code >= 51 && $code <= 57 ) {
			return 'טפטוף';
		}
		if ( ( $code >= 61 && $code <= 67 ) || ( $code >= 80 && $code <= 82 ) ) {
			return 'גשם';
		}
		if ( ( $code >= 71 && $code <= 77 ) || in_array( $code, array( 85, 86 ), true ) ) {
			return 'שלג';
		}
		if ( $code >= 95 ) {
			return 'סופת רעמים';
		}
		return 'משתנה';
	}
}
