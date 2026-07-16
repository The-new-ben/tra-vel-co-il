<?php
/**
 * REST controller for restricted Tra-Vel V2 theme deployments.
 *
 * @package TraVelDeploy
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Deploy_Controller extends WP_REST_Controller {
	const THEME_SLUG        = 'tra-vel-v2';
	const OPTION_LAST       = 'tra_vel_deploy_last';
	const LOCK_KEY          = 'tra_vel_deploy_lock';
	const ACTIVATE_PHRASE   = 'ACTIVATE TRA-VEL V2';
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
					'activate'     => array(
						'type'              => 'boolean',
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'confirmation' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				)
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
					'backup' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_file_name',
						'validate_callback' => static function ( $value ) {
							return 'latest' === $value || 1 === preg_match( '/^tra-vel-v2-\d{8}T\d{6}Z-[A-Za-z0-9]+$/', $value );
						},
					),
				)
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
		$theme = wp_get_theme( self::THEME_SLUG );

		return rest_ensure_response(
			array(
				'gateway_version'  => TRA_VEL_DEPLOY_VERSION,
				'theme'            => self::THEME_SLUG,
				'installed'        => $theme->exists(),
				'installed_version'=> $theme->exists() ? $theme->get( 'Version' ) : null,
				'active'           => get_stylesheet() === self::THEME_SLUG,
				'last_deployment'  => get_option( self::OPTION_LAST, null ),
				'backups'          => $this->list_backups(),
			)
		);
	}

	/**
	 * Validate, back up, and install a Tra-Vel V2 ZIP package.
	 */
	public function deploy( WP_REST_Request $request ) {
		$locked = $this->acquire_lock();
		if ( is_wp_error( $locked ) ) {
			return $locked;
		}

		try {
			$activate = rest_sanitize_boolean( $request->get_param( 'activate' ) );
			if ( $activate && self::ACTIVATE_PHRASE !== $request->get_param( 'confirmation' ) ) {
				return new WP_Error( 'tra_vel_activation_confirmation', 'Activation confirmation did not match.', array( 'status' => 400 ) );
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
			if ( ! hash_equals( $expected_hash, $actual_hash ) ) {
				return new WP_Error( 'tra_vel_hash_mismatch', 'Package checksum did not match.', array( 'status' => 400 ) );
			}

			$package_data = $this->inspect_package( $package['tmp_name'] );
			if ( is_wp_error( $package_data ) ) {
				return $package_data;
			}

			$backup = $this->backup_current_theme();
			if ( is_wp_error( $backup ) ) {
				return $backup;
			}

			$result = $this->install_package( $package['tmp_name'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( $activate ) {
				switch_theme( self::THEME_SLUG );
				if ( get_stylesheet() !== self::THEME_SLUG ) {
					return new WP_Error( 'tra_vel_activation_failed', 'The theme installed but activation failed.', array( 'status' => 500 ) );
				}
			}

			$deployment = array(
				'deployed_at' => gmdate( 'c' ),
				'version'     => $package_data['version'],
				'sha256'      => $actual_hash,
				'backup'      => $backup,
				'activated'   => $activate,
				'user_id'     => get_current_user_id(),
			);
			update_option( self::OPTION_LAST, $deployment, false );
			$this->prune_backups();

			return new WP_REST_Response(
				array(
					'ok'         => true,
					'theme'      => self::THEME_SLUG,
					'version'    => $package_data['version'],
					'sha256'     => $actual_hash,
					'backup'     => $backup,
					'active'     => get_stylesheet() === self::THEME_SLUG,
				),
				200
			);
		} finally {
			delete_transient( self::LOCK_KEY );
		}
	}

	/**
	 * Restore a named or latest backup.
	 */
	public function rollback( WP_REST_Request $request ) {
		$locked = $this->acquire_lock();
		if ( is_wp_error( $locked ) ) {
			return $locked;
		}

		try {
			$backup_name = (string) $request->get_param( 'backup' );
			$backups     = $this->list_backups();
			if ( 'latest' === $backup_name ) {
				$backup_name = reset( $backups );
			}
			if ( ! $backup_name || ! in_array( $backup_name, $backups, true ) ) {
				return new WP_Error( 'tra_vel_backup_missing', 'The requested backup was not found.', array( 'status' => 404 ) );
			}

			$result = $this->restore_backup( $backup_name );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$theme = wp_get_theme( self::THEME_SLUG );
			update_option(
				self::OPTION_LAST,
				array(
					'rolled_back_at' => gmdate( 'c' ),
					'backup'         => $backup_name,
					'version'        => $theme->get( 'Version' ),
					'user_id'        => get_current_user_id(),
				),
				false
			);

			return rest_ensure_response(
				array(
					'ok'      => true,
					'restored'=> $backup_name,
					'version' => $theme->get( 'Version' ),
					'active'  => get_stylesheet() === self::THEME_SLUG,
				)
			);
		} finally {
			delete_transient( self::LOCK_KEY );
		}
	}

	/**
	 * Acquire a short deployment lock.
	 */
	private function acquire_lock() {
		if ( get_transient( self::LOCK_KEY ) ) {
			return new WP_Error( 'tra_vel_deploy_locked', 'Another deployment is already running.', array( 'status' => 409 ) );
		}
		set_transient( self::LOCK_KEY, wp_generate_uuid4(), 5 * MINUTE_IN_SECONDS );
		return true;
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
		$this->load_filesystem_api();
		$inspect_dir = trailingslashit( get_temp_dir() ) . 'tra-vel-inspect-' . wp_generate_password( 12, false, false );
		wp_mkdir_p( $inspect_dir );
		$result = unzip_file( $package_path, $inspect_dir );
		if ( is_wp_error( $result ) ) {
			$this->delete_directory( $inspect_dir );
			return new WP_Error( 'tra_vel_invalid_zip', $result->get_error_message(), array( 'status' => 400 ) );
		}

		$entries = array_values( array_diff( scandir( $inspect_dir ), array( '.', '..' ) ) );
		$style   = trailingslashit( $inspect_dir ) . self::THEME_SLUG . '/style.css';
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
		$this->delete_directory( $inspect_dir );

		if ( 'Tra-Vel V2' !== $data['name'] || ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $data['version'] ) ) {
			return new WP_Error( 'tra_vel_theme_headers', 'The package theme headers are invalid.', array( 'status' => 400 ) );
		}

		return $data;
	}

	/**
	 * Install through the WordPress upgrader with overwrite explicitly enabled.
	 */
	private function install_package( $package_path ) {
		$this->load_filesystem_api();
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

		return true;
	}

	/**
	 * Copy the currently installed V2 theme into the release backup directory.
	 */
	private function backup_current_theme() {
		$source = trailingslashit( get_theme_root() ) . self::THEME_SLUG;
		if ( ! is_dir( $source ) ) {
			return null;
		}

		$this->load_filesystem_api();
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

		return $name;
	}

	/**
	 * Restore a validated backup while preserving the current release first.
	 */
	private function restore_backup( $backup_name ) {
		$this->load_filesystem_api();
		global $wp_filesystem;

		$source     = trailingslashit( $this->backup_root() ) . $backup_name;
		$theme_root = trailingslashit( get_theme_root() );
		$target     = $theme_root . self::THEME_SLUG;
		$stage      = $theme_root . self::THEME_SLUG . '-restore-' . wp_generate_password( 8, false, false );
		$quarantine = trailingslashit( $this->backup_root() ) . '.quarantine-' . wp_generate_password( 8, false, false );

		if ( ! is_file( trailingslashit( $source ) . 'style.css' ) ) {
			return new WP_Error( 'tra_vel_backup_invalid', 'The backup is not a valid Tra-Vel V2 theme.', array( 'status' => 400 ) );
		}

		$this->delete_directory( $stage );
		$copied = copy_dir( $source, $stage );
		if ( is_wp_error( $copied ) ) {
			$this->delete_directory( $stage );
			return new WP_Error( 'tra_vel_restore_stage', $copied->get_error_message(), array( 'status' => 500 ) );
		}

		$current_backup = $this->backup_current_theme();
		if ( is_wp_error( $current_backup ) ) {
			$this->delete_directory( $stage );
			return $current_backup;
		}

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

		$this->delete_directory( $quarantine );
		wp_clean_themes_cache( true );
		return true;
	}

	/**
	 * Return backup directory names from newest to oldest.
	 */
	private function list_backups() {
		$root = $this->backup_root();
		if ( ! is_dir( $root ) ) {
			return array();
		}

		$backups = array();
		foreach ( scandir( $root ) as $entry ) {
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
	 * Load WordPress filesystem helpers and initialize direct filesystem access.
	 */
	private function load_filesystem_api() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}

	/**
	 * Delete a directory recursively through the initialized WordPress filesystem.
	 */
	private function delete_directory( $path ) {
		$this->load_filesystem_api();
		global $wp_filesystem;
		if ( $path && is_dir( $path ) ) {
			$wp_filesystem->delete( $path, true );
		}
	}

	/**
	 * Keep backups outside the themes directory.
	 */
	private function backup_root() {
		return WP_CONTENT_DIR . '/tra-vel-v2-releases';
	}
}
