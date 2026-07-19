<?php
/**
 * Strict deterministic loader for the bundled generic commerce sandbox network.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Sandbox_Network {
	const NETWORK_ID    = 'tra_vel_commerce_sandbox';
	const MAX_PROVIDERS = 100;

	/** @var string */
	private $fixture_path;

	/** @var array<int,array> */
	private $descriptors = array();

	/** @var string */
	private $signature = '';

	/** @var true|WP_Error|null */
	private $load_result = null;

	/**
	 * @param string|null $fixture_path Explicit local fixture path for tests; null uses the bundled fixture.
	 */
	public function __construct( $fixture_path = null ) {
		$this->fixture_path = null === $fixture_path
			? dirname( dirname( __DIR__ ) ) . '/assets/fixtures/commerce-sandbox/provider-network.json'
			: $fixture_path;
	}

	/**
	 * Load and validate the closed network fixture once.
	 *
	 * @return true|WP_Error
	 */
	public function load() {
		if ( true === $this->load_result || is_wp_error( $this->load_result ) ) {
			return $this->load_result;
		}
		if ( ! is_string( $this->fixture_path ) || '' === trim( $this->fixture_path ) || ! is_file( $this->fixture_path ) || ! is_readable( $this->fixture_path ) ) {
			return $this->fail( 'fixture_unreadable', 'The commerce sandbox provider fixture is not a readable local file.' );
		}

		$contents = file_get_contents( $this->fixture_path );
		if ( false === $contents ) {
			return $this->fail( 'fixture_unreadable', 'The commerce sandbox provider fixture could not be read.' );
		}
		$fixture = json_decode( $contents, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $fixture ) ) {
			return $this->fail( 'fixture_json_invalid', 'The commerce sandbox provider fixture is not valid JSON.' );
		}
		if ( ! self::exact_object( $fixture, array( 'contract_version', 'network_id', 'environment', 'providers' ) ) ) {
			return $this->fail( 'fixture_shape_invalid', 'The commerce sandbox provider fixture contains missing or unknown top-level fields.' );
		}
		if ( Tra_Vel_Commerce_Policy::CONTRACT_VERSION !== $fixture['contract_version'] || self::NETWORK_ID !== $fixture['network_id'] || 'sandbox' !== $fixture['environment'] ) {
			return $this->fail( 'fixture_identity_invalid', 'The commerce sandbox provider fixture identity is not supported.' );
		}
		if ( ! is_array( $fixture['providers'] ) || array_values( $fixture['providers'] ) !== $fixture['providers'] || ! $fixture['providers'] || count( $fixture['providers'] ) > self::MAX_PROVIDERS ) {
			return $this->fail( 'fixture_providers_invalid', 'The commerce sandbox provider fixture requires a bounded provider list.' );
		}

		$descriptors = array();
		$provider_ids = array();
		$vertical_coverage = array();
		foreach ( $fixture['providers'] as $index => $candidate ) {
			$descriptor = Tra_Vel_Commerce_Policy::provider_descriptor( $candidate );
			if ( is_wp_error( $descriptor ) ) {
				$this->load_result = $descriptor;
				return $descriptor;
			}
			if ( 'sandbox' !== $descriptor['environment'] ) {
				return $this->fail(
					'provider_environment_invalid',
					'The bundled commerce network accepts sandbox provider descriptors only.',
					array( 'provider_index' => $index )
				);
			}
			$provider_id = $descriptor['provider_id'];
			if ( isset( $provider_ids[ $provider_id ] ) ) {
				return $this->fail(
					'provider_duplicate',
					'A commerce sandbox provider ID appears more than once.',
					array( 'provider_id' => $provider_id )
				);
			}
			$provider_ids[ $provider_id ] = true;
			foreach ( $descriptor['verticals'] as $vertical ) {
				$vertical_coverage[ $vertical ] = true;
			}
			$descriptors[] = $descriptor;
		}

		foreach ( Tra_Vel_Commerce_Taxonomy::VERTICALS as $vertical ) {
			if ( ! isset( $vertical_coverage[ $vertical ] ) ) {
				return $this->fail(
					'vertical_coverage_incomplete',
					'The commerce sandbox provider network does not cover every canonical vertical.',
					array( 'missing_vertical' => $vertical )
				);
			}
		}

		usort( $descriptors, array( __CLASS__, 'compare_descriptors' ) );
		$this->descriptors = $descriptors;
		$this->signature   = Tra_Vel_Commerce_Policy::canonical_digest( $descriptors );
		$this->load_result = true;
		return true;
	}

	/**
	 * Return normalized descriptors in priority-descending, provider-ID order.
	 *
	 * @return array|WP_Error
	 */
	public function all() {
		$result = $this->load();
		return is_wp_error( $result ) ? $result : $this->descriptors;
	}

	/**
	 * Return the SHA-256 digest of the deterministic normalized descriptor list.
	 *
	 * @return string|WP_Error
	 */
	public function signature() {
		$result = $this->load();
		return is_wp_error( $result ) ? $result : $this->signature;
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function compare_descriptors( $left, $right ) {
		$priority = (int) $right['priority'] <=> (int) $left['priority'];
		return 0 !== $priority ? $priority : strcmp( $left['provider_id'], $right['provider_id'] );
	}

	private function fail( $suffix, $message, $data = array() ) {
		$this->load_result = new WP_Error(
			'tra_vel_commerce_sandbox_network_' . $suffix,
			$message,
			array_merge( array( 'status' => 400 ), $data )
		);
		return $this->load_result;
	}
}
