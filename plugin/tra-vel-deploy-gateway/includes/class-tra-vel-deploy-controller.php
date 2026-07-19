<?php
/**
 * REST controller for restricted Tra-Vel V2 theme deployments.
 *
 * @package TraVelDeploy
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Deploy_Controller extends WP_REST_Controller {
	const THEME_SLUG        = 'tra-vel-v2';
	const THEME_NAME        = 'Tra-Vel V2';
	const OPTION_LAST       = 'tra_vel_deploy_last';
	const LOCK_KEY          = 'tra_vel_deploy_lock';
	const DEPLOY_PHRASE     = 'DEPLOY TRA-VEL V2';
	const ACTIVATE_PHRASE   = 'ACTIVATE TRA-VEL V2';
	const ROLLBACK_PHRASE   = 'ROLLBACK TRA-VEL V2';
	const LOCK_TTL          = 900;
	const MAX_PACKAGE_BYTES = 26214400;
	const BACKUP_LIMIT      = 10;

	/**
	 * Configure the controller namespace and resource base.
	 */
	public function __construct() {
		$this->namespace = 'tra-vel-deploy/v1';
		$this->rest_base = 'theme';
	}

	/**
	 * Register authenticated deployment endpoints.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'can_deploy' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'deploy' ),
				'permission_callback' => array( $this, 'can_deploy' ),
				'args'                => array(
					'activate'                => array(
						'type'              => 'boolean',
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'deployment_confirmation' => array(
						'type'     => 'string',
						'required' => true,
					),
					'activation_confirmation' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/rollback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rollback' ),
				'permission_callback' => array( $this, 'can_deploy' ),
				'args'                => array(
					'backup'       => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_file_name',
						'validate_callback' => static function ( $value ) {
							return 1 === preg_match( '/^tra-vel-v2-\d{8}T\d{6}Z-[A-Za-z0-9]+$/', $value );
						},
					),
					'expected_current_fingerprint' => array(
						'type'              => 'string',
						'required'          => true,
						'pattern'           => '^[a-f0-9]{64}$',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'expected_restored_fingerprint' => array(
						'type'              => 'string',
						'required'          => true,
						'pattern'           => '^[a-f0-9]{64}$',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'confirmation' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Require an authenticated administrator with theme install/update rights.
	 */
	public function can_deploy() {
		return current_user_can( 'install_themes' ) && current_user_can( 'update_themes' );
	}

	/**
	 * Return deployment state without exposing credentials or filesystem paths.
	 */
	public function get_status() {
		$theme       = wp_get_theme( self::THEME_SLUG );
		$fingerprint = $theme->exists() ? $this->fingerprint_directory( $this->theme_path() ) : null;
		if ( is_wp_error( $fingerprint ) ) {
			return $fingerprint;
		}

		return rest_ensure_response(
			array(
				'gateway_version'   => TRA_VEL_DEPLOY_VERSION,
				'theme'             => self::THEME_SLUG,
				'installed'         => $theme->exists(),
				'installed_version' => $theme->exists() ? $theme->get( 'Version' ) : null,
				'installed_fingerprint' => $fingerprint,
				'active'            => get_stylesheet() === self::THEME_SLUG,
				'last_deployment'   => get_option( self::OPTION_LAST, null ),
				'backups'           => $this->list_backups(),
			)
		);
	}

	/**
	 * Validate, back up, and install a Tra-Vel V2 ZIP package.
	 */
	public function deploy( WP_REST_Request $request ) {
		$confirmation = $request->get_param( 'deployment_confirmation' );
		if ( ! is_string( $confirmation ) || ! hash_equals( self::DEPLOY_PHRASE, $confirmation ) ) {
			return new WP_Error( 'tra_vel_deployment_confirmation', 'Theme deployment confirmation did not match.', array( 'status' => 400 ) );
		}

		$activate = rest_sanitize_boolean( $request->get_param( 'activate' ) );
		if ( $activate ) {
			$activation_confirmation = $request->get_param( 'activation_confirmation' );
			if ( ! is_string( $activation_confirmation ) || ! hash_equals( self::ACTIVATE_PHRASE, $activation_confirmation ) ) {
				return new WP_Error( 'tra_vel_activation_confirmation', 'Theme activation confirmation did not match.', array( 'status' => 400 ) );
			}
			if ( ! current_user_can( 'switch_themes' ) ) {
				return new WP_Error( 'tra_vel_activation_forbidden', 'The current user cannot activate themes.', array( 'status' => 403 ) );
			}
		}

		$lease = $this->acquire_lock();
		if ( is_wp_error( $lease ) ) {
			return $lease;
		}

		$backup                  = null;
		$had_existing            = false;
		$mutation_started        = false;
		$activation_tried        = false;
		$previous_active         = (string) get_stylesheet();
		$backup_content_sha256   = null;

		try {
			$filesystem = $this->load_filesystem_api();
			if ( is_wp_error( $filesystem ) ) {
				return $filesystem;
			}

			$package = $this->get_uploaded_package( $request );
			if ( is_wp_error( $package ) ) {
				return $package;
			}

			$expected_hash = strtolower( (string) $request->get_header( 'x-tra-vel-sha256' ) );
			if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $expected_hash ) ) {
				return new WP_Error( 'tra_vel_hash_missing', 'A valid X-Tra-Vel-SHA256 header is required.', array( 'status' => 400 ) );
			}

			$actual_hash = hash_file( 'sha256', $package['tmp_name'] );
			if ( ! is_string( $actual_hash ) || ! hash_equals( $expected_hash, $actual_hash ) ) {
				return new WP_Error( 'tra_vel_hash_mismatch', 'Package checksum did not match.', array( 'status' => 400 ) );
			}

			$package_data = $this->inspect_package( $package['tmp_name'] );
			if ( is_wp_error( $package_data ) ) {
				return $package_data;
			}

			$transition = $this->validate_release_transition( $package_data );
			if ( is_wp_error( $transition ) ) {
				return $transition;
			}
			$had_existing = $transition['installed'];

			if ( $transition['unchanged'] ) {
				if ( $activate && get_stylesheet() !== self::THEME_SLUG ) {
					$activation_tried = true;
					switch_theme( self::THEME_SLUG );
					if ( get_stylesheet() !== self::THEME_SLUG ) {
						return $this->recover_activation_error(
							new WP_Error( 'tra_vel_activation_failed', 'The unchanged theme could not be activated.', array( 'status' => 500 ) ),
							$previous_active
						);
					}
				}

				return rest_ensure_response(
					array(
						'ok'             => true,
						'theme'          => self::THEME_SLUG,
						'version'        => $package_data['version'],
						'sha256'         => $actual_hash,
						'content_sha256' => $package_data['content_sha256'],
						'backup'         => null,
						'active'         => get_stylesheet() === self::THEME_SLUG,
						'unchanged'      => true,
					)
				);
			}

			$backup = $this->backup_current_theme();
			if ( is_wp_error( $backup ) ) {
				return $backup;
			}
			if ( $had_existing && ! $backup ) {
				return new WP_Error( 'tra_vel_backup_missing', 'The installed theme could not be captured before deployment.', array( 'status' => 500 ) );
			}
			if ( $backup ) {
				$backup_content_sha256 = $this->fingerprint_directory( trailingslashit( $this->backup_root() ) . $backup );
				if ( is_wp_error( $backup_content_sha256 ) ) {
					return $backup_content_sha256;
				}
			}

			$mutation_started = true;
			$result           = $this->install_package( $package['tmp_name'], $package_data );
			if ( is_wp_error( $result ) ) {
				return $this->recover_deployment_error( $result, $backup, $had_existing, $previous_active );
			}

			if ( $activate ) {
				$activation_tried = true;
				switch_theme( self::THEME_SLUG );
				if ( get_stylesheet() !== self::THEME_SLUG ) {
					return $this->recover_deployment_error(
						new WP_Error( 'tra_vel_activation_failed', 'The theme installed but activation failed.', array( 'status' => 500 ) ),
						$backup,
						$had_existing,
						$previous_active
					);
				}
			}

			$deployment = array(
				'deployed_at'          => gmdate( 'c' ),
				'version'              => $package_data['version'],
				'sha256'               => $actual_hash,
				'content_sha256'       => $package_data['content_sha256'],
				'backup'               => $backup,
				'backup_content_sha256' => $backup_content_sha256,
				'activated'            => $activate,
				'previous_theme'       => $previous_active,
				'user_id'              => get_current_user_id(),
			);
			update_option( self::OPTION_LAST, $deployment, false );
			$this->prune_backups();
			$this->purge_site_caches();

			return new WP_REST_Response(
				array(
					'ok'                    => true,
					'theme'                 => self::THEME_SLUG,
					'version'               => $package_data['version'],
					'sha256'                => $actual_hash,
					'content_sha256'        => $package_data['content_sha256'],
					'backup'                => $backup,
					'backup_content_sha256' => $backup_content_sha256,
					'active'                => get_stylesheet() === self::THEME_SLUG,
				),
				200
			);
		} catch ( Throwable $throwable ) {
			$error = new WP_Error( 'tra_vel_deploy_exception', 'The theme deployment stopped unexpectedly.', array( 'status' => 500 ) );
			if ( $mutation_started ) {
				return $this->recover_deployment_error( $error, $backup, $had_existing, $previous_active );
			}
			if ( $activation_tried ) {
				return $this->recover_activation_error( $error, $previous_active );
			}
			return $error;
		} finally {
			$this->release_lock( $lease );
		}
	}

	/**
	 * Restore an exact named backup only when current content still matches.
	 */
	public function rollback( WP_REST_Request $request ) {
		$confirmation = $request->get_param( 'confirmation' );
		if ( ! is_string( $confirmation ) || ! hash_equals( self::ROLLBACK_PHRASE, $confirmation ) ) {
			return new WP_Error( 'tra_vel_rollback_confirmation', 'Theme rollback confirmation did not match.', array( 'status' => 400 ) );
		}

		$lease = $this->acquire_lock();
		if ( is_wp_error( $lease ) ) {
			return $lease;
		}

		try {
			$filesystem = $this->load_filesystem_api();
			if ( is_wp_error( $filesystem ) ) {
				return $filesystem;
			}

			$expected_current_fingerprint = strtolower( (string) $request->get_param( 'expected_current_fingerprint' ) );
			$current_fingerprint          = $this->fingerprint_directory( $this->theme_path() );
			if ( is_wp_error( $current_fingerprint ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $expected_current_fingerprint ) || ! hash_equals( $expected_current_fingerprint, (string) $current_fingerprint ) ) {
				return new WP_Error( 'tra_vel_theme_rollback_scope_changed', 'The installed theme content changed before rollback; refusing to overwrite a later release.', array( 'status' => 409 ) );
			}

			$backup_name = (string) $request->get_param( 'backup' );
			$backups     = $this->list_backups();
			if ( ! $backup_name || ! in_array( $backup_name, $backups, true ) ) {
				return new WP_Error( 'tra_vel_backup_missing', 'The requested backup was not found.', array( 'status' => 404 ) );
			}
			$expected_restored_fingerprint = strtolower( (string) $request->get_param( 'expected_restored_fingerprint' ) );
			$backup_fingerprint            = $this->fingerprint_directory( trailingslashit( $this->backup_root() ) . $backup_name );
			if ( is_wp_error( $backup_fingerprint ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $expected_restored_fingerprint ) || ! hash_equals( $expected_restored_fingerprint, (string) $backup_fingerprint ) ) {
				return new WP_Error( 'tra_vel_theme_rollback_target_changed', 'The selected theme backup no longer matches the expected restored content; refusing to mutate production.', array( 'status' => 409 ) );
			}

			$result = $this->restore_backup( $backup_name );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			wp_clean_themes_cache( true );
			$theme                = wp_get_theme( self::THEME_SLUG );
			$restored_fingerprint = $this->fingerprint_directory( $this->theme_path() );
			if ( ! $theme->exists() || is_wp_error( $restored_fingerprint ) || ! hash_equals( $expected_restored_fingerprint, (string) $restored_fingerprint ) ) {
				return new WP_Error( 'tra_vel_theme_rollback_identity_missing', 'The restored theme identity could not be verified.', array( 'status' => 500 ) );
			}
			update_option(
				self::OPTION_LAST,
				array(
					'rolled_back_at' => gmdate( 'c' ),
					'backup'         => $backup_name,
					'version'        => $theme->get( 'Version' ),
					'content_sha256' => $restored_fingerprint,
					'user_id'        => get_current_user_id(),
				),
				false
			);

			$this->purge_site_caches();

			return rest_ensure_response(
				array(
					'ok'             => true,
					'restored'       => $backup_name,
					'version'        => $theme->get( 'Version' ),
					'content_sha256' => $restored_fingerprint,
					'active'          => get_stylesheet() === self::THEME_SLUG,
				)
			);
		} finally {
			$this->release_lock( $lease );
		}
	}

	/**
	 * Purge page and object caches after a file mutation so visitors receive
	 * the deployed release immediately. LiteSpeed purge is a no-op when the
	 * host does not run it.
	 */
	private function purge_site_caches() {
		do_action( 'litespeed_purge_all' );
		wp_cache_flush();
	}

	/**
	 * Acquire an owner-token database lease atomically.
	 */
	private function acquire_lock() {
		global $wpdb;

		$lease = array(
			'owner'      => wp_generate_uuid4() . '-' . wp_generate_password( 24, false, false ),
			'expires_at' => time() + self::LOCK_TTL,
		);

		if ( add_option( self::LOCK_KEY, $lease, '', false ) ) {
			return $lease;
		}

		$current_raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				self::LOCK_KEY
			)
		);

		if ( null === $current_raw && add_option( self::LOCK_KEY, $lease, '', false ) ) {
			return $lease;
		}

		$current = maybe_unserialize( $current_raw );
		$expired = is_array( $current ) && isset( $current['expires_at'] ) && absint( $current['expires_at'] ) < time();
		if ( $expired ) {
			$replaced = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = %s, autoload = 'no' WHERE option_name = %s AND option_value = %s",
					maybe_serialize( $lease ),
					self::LOCK_KEY,
					$current_raw
				)
			);
			if ( 1 === $replaced ) {
				wp_cache_delete( self::LOCK_KEY, 'options' );
				return $lease;
			}
		}

		return new WP_Error( 'tra_vel_deploy_locked', 'Another theme deployment is already running.', array( 'status' => 409 ) );
	}

	/**
	 * Release only the database lease owned by this request.
	 */
	private function release_lock( $lease ) {
		if ( ! is_array( $lease ) || empty( $lease['owner'] ) || empty( $lease['expires_at'] ) ) {
			return;
		}

		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				self::LOCK_KEY,
				maybe_serialize( $lease )
			)
		);
		wp_cache_delete( self::LOCK_KEY, 'options' );
	}

	/**
	 * Return and validate the uploaded ZIP file record.
	 */
	private function get_uploaded_package( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['package'] ) || ! is_array( $files['package'] ) ) {
			return new WP_Error( 'tra_vel_package_missing', 'A package file is required.', array( 'status' => 400 ) );
		}

		$file = $files['package'];
		if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'tra_vel_upload_failed', 'The package upload failed.', array( 'status' => 400 ) );
		}
		if ( (int) $file['size'] < 1 || (int) $file['size'] > self::MAX_PACKAGE_BYTES ) {
			return new WP_Error( 'tra_vel_package_size', 'The package size is outside the allowed range.', array( 'status' => 413 ) );
		}
		if ( 'zip' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'tra_vel_package_type', 'Only ZIP packages are accepted.', array( 'status' => 415 ) );
		}

		return $file;
	}

	/**
	 * Inspect the package in a temporary directory before WordPress installs it.
	 */
	private function inspect_package( $package_path ) {
		$filesystem = $this->load_filesystem_api();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}

		$inspect_dir = trailingslashit( get_temp_dir() ) . 'tra-vel-inspect-' . wp_generate_password( 12, false, false );
		if ( ! wp_mkdir_p( $inspect_dir ) ) {
			return new WP_Error( 'tra_vel_inspect_directory', 'Could not create a package inspection directory.', array( 'status' => 500 ) );
		}

		$result = unzip_file( $package_path, $inspect_dir );
		if ( is_wp_error( $result ) ) {
			$this->delete_directory( $inspect_dir );
			return new WP_Error( 'tra_vel_invalid_zip', $result->get_error_message(), array( 'status' => 400 ) );
		}

		$scanned = scandir( $inspect_dir );
		$entries = false === $scanned ? array() : array_values( array_diff( $scanned, array( '.', '..' ) ) );
		$root    = trailingslashit( $inspect_dir ) . self::THEME_SLUG;
		$style   = trailingslashit( $root ) . 'style.css';
		if ( array( self::THEME_SLUG ) !== $entries || ! is_file( $style ) ) {
			$this->delete_directory( $inspect_dir );
			return new WP_Error( 'tra_vel_package_layout', 'The ZIP must contain only tra-vel-v2 at its root.', array( 'status' => 400 ) );
		}

		$data = get_file_data(
			$style,
			array(
				'name'    => 'Theme Name',
				'version' => 'Version',
			)
		);
		if ( self::THEME_NAME !== $data['name'] || ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $data['version'] ) ) {
			$this->delete_directory( $inspect_dir );
			return new WP_Error( 'tra_vel_theme_headers', 'The package theme headers are invalid.', array( 'status' => 400 ) );
		}

		$fingerprint = $this->fingerprint_directory( $root );
		if ( is_wp_error( $fingerprint ) ) {
			$this->delete_directory( $inspect_dir );
			return $fingerprint;
		}
		$data['content_sha256'] = $fingerprint;

		$deleted = $this->delete_directory( $inspect_dir );
		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		return $data;
	}

	/**
	 * Reject downgrades and changed artifacts that reuse an installed version.
	 */
	private function validate_release_transition( $package_data ) {
		wp_clean_themes_cache( true );
		$theme = wp_get_theme( self::THEME_SLUG );
		if ( ! $theme->exists() ) {
			return array(
				'installed' => false,
				'unchanged' => false,
			);
		}
		if ( $theme->errors() ) {
			return new WP_Error( 'tra_vel_installed_theme_invalid', 'The installed Tra-Vel V2 theme is invalid and cannot be overwritten automatically.', array( 'status' => 409 ) );
		}

		$current_version = trim( (string) $theme->get( 'Version' ) );
		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $current_version ) ) {
			return new WP_Error( 'tra_vel_installed_version_invalid', 'The installed theme version is invalid.', array( 'status' => 409 ) );
		}

		if ( version_compare( $package_data['version'], $current_version, '<' ) ) {
			return new WP_Error(
				'tra_vel_theme_downgrade_blocked',
				'Theme downgrades require the protected rollback route.',
				array(
					'status'            => 409,
					'installed_version' => $current_version,
					'package_version'   => $package_data['version'],
				)
			);
		}

		if ( 0 === version_compare( $package_data['version'], $current_version ) ) {
			$current_fingerprint = $this->fingerprint_directory( $this->theme_path() );
			if ( is_wp_error( $current_fingerprint ) ) {
				return $current_fingerprint;
			}
			if ( ! hash_equals( $current_fingerprint, $package_data['content_sha256'] ) ) {
				return new WP_Error(
					'tra_vel_theme_same_version_changed',
					'A different theme artifact cannot overwrite the same installed version. Increase the theme version or use rollback.',
					array(
						'status'            => 409,
						'installed_version' => $current_version,
					)
				);
			}
			return array(
				'installed' => true,
				'unchanged' => true,
			);
		}

		return array(
			'installed' => true,
			'unchanged' => false,
		);
	}

	/**
	 * Install through the WordPress upgrader with overwrite explicitly enabled.
	 */
	private function install_package( $package_path, $package_data ) {
		$filesystem = $this->load_filesystem_api();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$result   = $upgrader->install( $package_path, array( 'overwrite_package' => true ) );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'tra_vel_install_failed', $result->get_error_message(), array( 'status' => 500 ) );
		}
		if ( ! $result ) {
			$message = $skin->get_errors()->has_errors() ? $skin->get_errors()->get_error_message() : 'WordPress could not install the theme.';
			return new WP_Error( 'tra_vel_install_failed', $message, array( 'status' => 500 ) );
		}

		wp_clean_themes_cache( true );
		$theme = wp_get_theme( self::THEME_SLUG );
		if ( ! $theme->exists() || $theme->errors() ) {
			return new WP_Error( 'tra_vel_theme_invalid', 'The installed theme did not pass WordPress validation.', array( 'status' => 500 ) );
		}
		if ( 0 !== version_compare( (string) $theme->get( 'Version' ), $package_data['version'] ) ) {
			return new WP_Error( 'tra_vel_installed_version_mismatch', 'The installed theme version did not match the inspected package.', array( 'status' => 500 ) );
		}

		$fingerprint = $this->fingerprint_directory( $this->theme_path() );
		if ( is_wp_error( $fingerprint ) ) {
			return $fingerprint;
		}
		if ( ! hash_equals( $package_data['content_sha256'], $fingerprint ) ) {
			return new WP_Error( 'tra_vel_installed_content_mismatch', 'The installed theme content did not match the inspected package.', array( 'status' => 500 ) );
		}

		return true;
	}

	/**
	 * Restore the previous files and active theme after a failed mutation.
	 */
	private function recover_deployment_error( WP_Error $error, $backup, $had_existing, $previous_active ) {
		$recovered = $this->recover_previous_theme( $backup, $had_existing, $previous_active );
		if ( is_wp_error( $recovered ) ) {
			return new WP_Error(
				$error->get_error_code() . '_recovery_failed',
				$error->get_error_message() . ' Automatic recovery also failed: ' . $recovered->get_error_message(),
				array(
					'status'    => 500,
					'recovered' => false,
					'backup'    => $backup,
				)
			);
		}

		return new WP_Error(
			$error->get_error_code(),
			$error->get_error_message(),
			array(
				'status'         => 500,
				'recovered'      => true,
				'backup'         => $backup,
				'previous_theme' => $previous_active,
			)
		);
	}

	/**
	 * Restore only the prior active theme when an idempotent activation fails.
	 */
	private function recover_activation_error( WP_Error $error, $previous_active ) {
		$recovered = $this->restore_previous_active_theme( $previous_active );
		if ( is_wp_error( $recovered ) ) {
			return new WP_Error(
				'tra_vel_activation_recovery_failed',
				$error->get_error_message() . ' The prior active theme could not be restored.',
				array( 'status' => 500, 'recovered' => false )
			);
		}
		return new WP_Error( $error->get_error_code(), $error->get_error_message(), array( 'status' => 500, 'recovered' => true ) );
	}

	/**
	 * Recover the exact pre-deployment theme state.
	 */
	private function recover_previous_theme( $backup, $had_existing, $previous_active ) {
		if ( $backup ) {
			$restored = $this->replace_theme_from_backup( $backup );
			if ( is_wp_error( $restored ) ) {
				return $restored;
			}
		} elseif ( $had_existing ) {
			return new WP_Error( 'tra_vel_recovery_backup_missing', 'The prior theme backup is missing.' );
		} else {
			$deleted = $this->delete_directory( $this->theme_path() );
			if ( is_wp_error( $deleted ) ) {
				return $deleted;
			}
			if ( is_dir( $this->theme_path() ) ) {
				return new WP_Error( 'tra_vel_recovery_delete_failed', 'The failed fresh theme install could not be removed.' );
			}
		}

		return $this->restore_previous_active_theme( $previous_active );
	}

	/**
	 * Preserve the active stylesheet that existed before deployment.
	 */
	private function restore_previous_active_theme( $previous_active ) {
		if ( get_stylesheet() === $previous_active ) {
			return true;
		}
		if ( ! is_string( $previous_active ) || '' === $previous_active ) {
			return new WP_Error( 'tra_vel_previous_theme_missing', 'The prior active theme identity is unavailable.' );
		}

		wp_clean_themes_cache( true );
		$theme = wp_get_theme( $previous_active );
		if ( ! $theme->exists() || $theme->errors() ) {
			return new WP_Error( 'tra_vel_previous_theme_invalid', 'The prior active theme is unavailable.' );
		}
		switch_theme( $previous_active );
		return get_stylesheet() === $previous_active ? true : new WP_Error( 'tra_vel_previous_theme_activation_failed', 'The prior active theme could not be reactivated.' );
	}

	/**
	 * Copy the currently installed V2 theme into the release backup directory.
	 */
	private function backup_current_theme() {
		$source = $this->theme_path();
		if ( ! is_dir( $source ) ) {
			return null;
		}

		$filesystem = $this->load_filesystem_api();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}
		$root = $this->backup_root();
		if ( ! wp_mkdir_p( $root ) ) {
			return new WP_Error( 'tra_vel_backup_root', 'Could not create the backup directory.', array( 'status' => 500 ) );
		}

		$name        = self::THEME_SLUG . '-' . gmdate( 'Ymd\THis\Z' ) . '-' . wp_generate_password( 8, false, false );
		$destination = trailingslashit( $root ) . $name;
		$result      = copy_dir( $source, $destination );
		if ( is_wp_error( $result ) ) {
			$this->delete_directory( $destination );
			return new WP_Error( 'tra_vel_backup_failed', $result->get_error_message(), array( 'status' => 500 ) );
		}

		$source_hash      = $this->fingerprint_directory( $source );
		$destination_hash = $this->fingerprint_directory( $destination );
		if ( is_wp_error( $source_hash ) || is_wp_error( $destination_hash ) || ! hash_equals( $source_hash, $destination_hash ) ) {
			$this->delete_directory( $destination );
			return new WP_Error( 'tra_vel_backup_verification_failed', 'The theme backup could not be verified.', array( 'status' => 500 ) );
		}

		return $name;
	}

	/**
	 * Restore a validated backup while preserving the current release first.
	 */
	private function restore_backup( $backup_name ) {
		$previous_active = (string) get_stylesheet();
		$current_backup  = $this->backup_current_theme();
		if ( is_wp_error( $current_backup ) ) {
			return $current_backup;
		}

		$restored = $this->replace_theme_from_backup( $backup_name );
		if ( is_wp_error( $restored ) ) {
			return $restored;
		}

		$active = $this->restore_previous_active_theme( $previous_active );
		if ( is_wp_error( $active ) ) {
			if ( $current_backup ) {
				$this->replace_theme_from_backup( $current_backup );
			}
			return $active;
		}

		return true;
	}

	/**
	 * Replace the theme directory with a staged, verified backup snapshot.
	 */
	private function replace_theme_from_backup( $backup_name ) {
		$filesystem = $this->load_filesystem_api();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}
		global $wp_filesystem;

		$source       = trailingslashit( $this->backup_root() ) . $backup_name;
		$theme_root   = trailingslashit( get_theme_root() );
		$target       = $this->theme_path();
		$stage        = $theme_root . self::THEME_SLUG . '-restore-' . wp_generate_password( 8, false, false );
		$quarantine   = trailingslashit( $this->backup_root() ) . '.quarantine-' . wp_generate_password( 8, false, false );
		$source_style = trailingslashit( $source ) . 'style.css';

		if ( ! is_file( $source_style ) ) {
			return new WP_Error( 'tra_vel_backup_invalid', 'The backup is not a valid Tra-Vel V2 theme.', array( 'status' => 400 ) );
		}
		$headers = get_file_data( $source_style, array( 'name' => 'Theme Name', 'version' => 'Version' ) );
		if ( self::THEME_NAME !== $headers['name'] || ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $headers['version'] ) ) {
			return new WP_Error( 'tra_vel_backup_invalid', 'The backup theme headers are invalid.', array( 'status' => 400 ) );
		}

		$source_hash = $this->fingerprint_directory( $source );
		if ( is_wp_error( $source_hash ) ) {
			return $source_hash;
		}

		$clean_stage = $this->delete_directory( $stage );
		if ( is_wp_error( $clean_stage ) ) {
			return $clean_stage;
		}
		$copied = copy_dir( $source, $stage );
		if ( is_wp_error( $copied ) ) {
			$this->delete_directory( $stage );
			return new WP_Error( 'tra_vel_restore_stage', $copied->get_error_message(), array( 'status' => 500 ) );
		}
		$stage_hash = $this->fingerprint_directory( $stage );
		if ( is_wp_error( $stage_hash ) || ! hash_equals( $source_hash, $stage_hash ) ) {
			$this->delete_directory( $stage );
			return new WP_Error( 'tra_vel_restore_stage_verification', 'The staged rollback copy could not be verified.', array( 'status' => 500 ) );
		}

		$this->delete_directory( $quarantine );
		if ( is_dir( $target ) && ! $wp_filesystem->move( $target, $quarantine, true ) ) {
			$this->delete_directory( $stage );
			return new WP_Error( 'tra_vel_restore_quarantine', 'Could not move the current theme before rollback.', array( 'status' => 500 ) );
		}
		if ( ! $wp_filesystem->move( $stage, $target, true ) ) {
			if ( is_dir( $quarantine ) ) {
				$wp_filesystem->move( $quarantine, $target, true );
			}
			$this->delete_directory( $stage );
			return new WP_Error( 'tra_vel_restore_failed', 'Could not move the restored theme into place.', array( 'status' => 500 ) );
		}

		$target_hash = $this->fingerprint_directory( $target );
		if ( is_wp_error( $target_hash ) || ! hash_equals( $source_hash, $target_hash ) ) {
			$this->delete_directory( $target );
			if ( is_dir( $quarantine ) ) {
				$wp_filesystem->move( $quarantine, $target, true );
			}
			return new WP_Error( 'tra_vel_restore_verification_failed', 'The restored theme could not be verified.', array( 'status' => 500 ) );
		}

		$this->delete_directory( $quarantine );
		wp_clean_themes_cache( true );
		return true;
	}

	/**
	 * Produce a deterministic content hash for a theme directory.
	 */
	private function fingerprint_directory( $root ) {
		$real_root = realpath( $root );
		if ( false === $real_root || ! is_dir( $real_root ) ) {
			return new WP_Error( 'tra_vel_fingerprint_root', 'The theme directory could not be inspected.', array( 'status' => 500 ) );
		}

		$files = array();
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $real_root, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);
			foreach ( $iterator as $file ) {
				if ( $file->isLink() ) {
					return new WP_Error( 'tra_vel_fingerprint_symlink', 'Theme packages may not contain symbolic links.', array( 'status' => 400 ) );
				}
				if ( ! $file->isFile() || ! $file->isReadable() ) {
					return new WP_Error( 'tra_vel_fingerprint_file', 'A theme file could not be inspected.', array( 'status' => 500 ) );
				}
				$path     = $file->getPathname();
				$relative = ltrim( str_replace( '\\', '/', substr( $path, strlen( $real_root ) ) ), '/' );
				$digest   = hash_file( 'sha256', $path );
				if ( ! is_string( $digest ) ) {
					return new WP_Error( 'tra_vel_fingerprint_hash', 'A theme file could not be hashed.', array( 'status' => 500 ) );
				}
				$files[ $relative ] = $digest;
			}
		} catch ( Throwable $throwable ) {
			return new WP_Error( 'tra_vel_fingerprint_failed', 'The theme directory could not be fingerprinted.', array( 'status' => 500 ) );
		}

		ksort( $files, SORT_STRING );
		$context = hash_init( 'sha256' );
		foreach ( $files as $relative => $digest ) {
			hash_update( $context, $relative . "\0" . $digest . "\0" );
		}
		return hash_final( $context );
	}

	/**
	 * Return backup directory names from newest to oldest.
	 */
	private function list_backups() {
		$root = $this->backup_root();
		if ( ! is_dir( $root ) ) {
			return array();
		}

		$entries = scandir( $root );
		if ( false === $entries ) {
			return array();
		}
		$backups = array();
		foreach ( $entries as $entry ) {
			if ( 1 === preg_match( '/^tra-vel-v2-\d{8}T\d{6}Z-[A-Za-z0-9]+$/', $entry ) && is_file( trailingslashit( $root ) . $entry . '/style.css' ) ) {
				$backups[ $entry ] = filemtime( trailingslashit( $root ) . $entry );
			}
		}
		arsort( $backups, SORT_NUMERIC );
		return array_keys( $backups );
	}

	/**
	 * Keep a bounded backup history.
	 */
	private function prune_backups() {
		$backups = $this->list_backups();
		foreach ( array_slice( $backups, self::BACKUP_LIMIT ) as $backup ) {
			$this->delete_directory( trailingslashit( $this->backup_root() ) . $backup );
		}
	}

	/**
	 * Load WordPress filesystem helpers and fail closed if access is unavailable.
	 */
	private function load_filesystem_api() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;
		$initialized = WP_Filesystem();
		if ( ! $initialized || ! is_object( $wp_filesystem ) || ! is_callable( array( $wp_filesystem, 'move' ) ) || ! is_callable( array( $wp_filesystem, 'delete' ) ) ) {
			return new WP_Error( 'tra_vel_theme_filesystem_unavailable', 'WordPress filesystem access is unavailable for theme deployment.', array( 'status' => 503 ) );
		}
		return true;
	}

	/**
	 * Delete a directory recursively through the initialized filesystem.
	 */
	private function delete_directory( $path ) {
		$filesystem = $this->load_filesystem_api();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}
		global $wp_filesystem;
		if ( ! $path || ! is_dir( $path ) ) {
			return true;
		}
		return $wp_filesystem->delete( $path, true ) ? true : new WP_Error( 'tra_vel_directory_delete_failed', 'A deployment working directory could not be removed.', array( 'status' => 500 ) );
	}

	/**
	 * Fixed installed theme directory.
	 */
	private function theme_path() {
		return trailingslashit( get_theme_root() ) . self::THEME_SLUG;
	}

	/**
	 * Keep backups outside the themes directory.
	 */
	private function backup_root() {
		return WP_CONTENT_DIR . '/tra-vel-v2-releases';
	}
}
