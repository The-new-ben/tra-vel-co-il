<?php
/**
 * Encrypted-at-rest fallback for the OpenAI credential.
 *
 * Hosting constants or environment variables take precedence. The encrypted
 * option exists for hosts where environment configuration is unavailable.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Agent_Credential_Vault {
	const OPTION_KEY = 'tra_vel_agent_openai_credential_v1';

	/**
	 * Return the configured key without exposing its source to REST responses.
	 *
	 * @return string
	 */
	public static function get_api_key() {
		if ( defined( 'TRA_VEL_OPENAI_API_KEY' ) && is_string( TRA_VEL_OPENAI_API_KEY ) ) {
			return trim( TRA_VEL_OPENAI_API_KEY );
		}

		$environment = getenv( 'OPENAI_API_KEY' );
		if ( is_string( $environment ) && '' !== trim( $environment ) ) {
			return trim( $environment );
		}

		$record = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $record ) || 1 !== (int) ( isset( $record['version'] ) ? $record['version'] : 0 ) ) {
			return '';
		}
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) || empty( $record['nonce'] ) || empty( $record['ciphertext'] ) ) {
			return '';
		}

		$nonce      = base64_decode( (string) $record['nonce'], true );
		$ciphertext = base64_decode( (string) $record['ciphertext'], true );
		if ( false === $nonce || false === $ciphertext || SODIUM_CRYPTO_SECRETBOX_NONCEBYTES !== strlen( $nonce ) ) {
			return '';
		}

		$plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, self::encryption_key() );
		return false === $plaintext ? '' : trim( $plaintext );
	}

	/**
	 * Store a project key encrypted with WordPress installation salts.
	 *
	 * @param string $api_key OpenAI project API key.
	 * @return true|WP_Error
	 */
	public static function store_api_key( $api_key ) {
		$api_key = trim( (string) $api_key );
		if ( strlen( $api_key ) < 40 || 0 !== strpos( $api_key, 'sk-' ) ) {
			return new WP_Error( 'tra_vel_agent_invalid_key', 'The OpenAI project key format is invalid.', array( 'status' => 400 ) );
		}
		if ( ! function_exists( 'sodium_crypto_secretbox' ) || ! function_exists( 'random_bytes' ) ) {
			return new WP_Error( 'tra_vel_agent_encryption_unavailable', 'Secure credential storage is unavailable on this host.', array( 'status' => 503 ) );
		}

		$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = sodium_crypto_secretbox( $api_key, $nonce, self::encryption_key() );
		$stored     = update_option(
			self::OPTION_KEY,
			array(
				'version'    => 1,
				'nonce'      => base64_encode( $nonce ),
				'ciphertext' => base64_encode( $ciphertext ),
				'updated_at' => gmdate( 'c' ),
			),
			false
		);
		$exists = is_array( get_option( self::OPTION_KEY, null ) );
		sodium_memzero( $api_key );
		return ( $stored || $exists ) ? true : new WP_Error( 'tra_vel_agent_key_store_failed', 'The encrypted credential could not be saved.', array( 'status' => 500 ) );
	}

	/**
	 * Remove only the encrypted WordPress fallback.
	 *
	 * @return bool
	 */
	public static function clear_stored_key() {
		return delete_option( self::OPTION_KEY );
	}

	/**
	 * Safe configuration state for health checks.
	 *
	 * @return array
	 */
	public static function status() {
		$source = 'none';
		if ( defined( 'TRA_VEL_OPENAI_API_KEY' ) && is_string( TRA_VEL_OPENAI_API_KEY ) && '' !== trim( TRA_VEL_OPENAI_API_KEY ) ) {
			$source = 'constant';
		} elseif ( is_string( getenv( 'OPENAI_API_KEY' ) ) && '' !== trim( (string) getenv( 'OPENAI_API_KEY' ) ) ) {
			$source = 'environment';
		} elseif ( '' !== self::get_api_key() ) {
			$source = 'encrypted_option';
		}
		return array(
			'configured' => 'none' !== $source,
			'source'     => $source,
			'encryption' => function_exists( 'sodium_crypto_secretbox' ) ? 'sodium_secretbox' : 'unavailable',
		);
	}

	/**
	 * Derive a fixed-size encryption key from per-installation WordPress salts.
	 *
	 * @return string Binary key.
	 */
	private static function encryption_key() {
		$material = wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ) . '|tra-vel-agent-v1';
		return hash( 'sha256', $material, true );
	}
}
