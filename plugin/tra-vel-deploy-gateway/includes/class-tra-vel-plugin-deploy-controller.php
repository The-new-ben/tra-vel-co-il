<?php
/**
 * Fixed-scope Agent Core plugin deployment controller.
 *
 * @package TraVelDeploy
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Plugin_Deploy_Controller extends WP_REST_Controller {
	const PLUGIN_SLUG       = 'tra-vel-agent-core';
	const PLUGIN_FILE       = 'tra-vel-agent-core/tra-vel-agent-core.php';
	const PLUGIN_NAME       = 'Tra-Vel Agent Core';
	const OPTION_LAST       = 'tra_vel_agent_deploy_last';
	const LOCK_KEY          = 'tra_vel_agent_deploy_lock';
	const DEPLOY_PHRASE     = 'DEPLOY TRA-VEL AGENT CORE';
	const ACTIVATE_PHRASE   = 'ACTIVATE TRA-VEL AGENT CORE';
	const ROLLBACK_PHRASE   = 'ROLLBACK TRA-VEL AGENT CORE';
	const REMOVE_FRESH_PHRASE = 'REMOVE FAILED TRA-VEL AGENT CORE';
	const MAX_PACKAGE_BYTES = 5242880;
	const BACKUP_LIMIT      = 10;

	public function __construct() {
		$this->namespace = 'tra-vel-deploy/v1';
		$this->rest_base = 'plugin/agent-core';
	}

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
					'activate'                => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
					'deployment_confirmation' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'activation_confirmation' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
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
					'backup' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_file_name',
						'validate_callback' => static function ( $value ) {
							return 'latest' === $value || 1 === preg_match( '/^tra-vel-agent-core-\d{8}T\d{6}Z-[A-Za-z0-9]+$/', $value );
						},
					),
					'confirmation' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/recovery/fresh',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'remove_failed_fresh_install' ),
				'permission_callback' => array( $this, 'can_remove_failed_fresh_install' ),
				'args'                => array(
					'version'      => array( 'type' => 'string', 'required' => true, 'pattern' => '^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
					'sha256'       => array( 'type' => 'string', 'required' => true, 'pattern' => '^[a-f0-9]{64}$', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
					'confirmation' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	public function can_deploy() {
		return current_user_can( 'install_plugins' ) && current_user_can( 'update_plugins' );
	}

	public function can_remove_failed_fresh_install() {
		return $this->can_deploy() && current_user_can( 'activate_plugins' ) && current_user_can( 'delete_plugins' );
	}

	public function get_status() {
		$this->load_plugin_api();
		$path      = trailingslashit( WP_PLUGIN_DIR ) . self::PLUGIN_FILE;
		$installed = is_file( $path );
		$data      = $installed ? get_plugin_data( $path, false, false ) : array();
		return rest_ensure_response(
			array(
				'gateway_version'  => TRA_VEL_DEPLOY_VERSION,
				'plugin'           => self::PLUGIN_SLUG,
				'installed'        => $installed,
				'installed_version'=> $installed && isset( $data['Version'] ) ? $data['Version'] : null,
				'active'           => $installed && is_plugin_active( self::PLUGIN_FILE ),
				'last_deployment'  => get_option( self::OPTION_LAST, null ),
				'backups'          => $this->list_backups(),
			)
		);
	}

	public function deploy( WP_REST_Request $request ) {
		$lease = $this->acquire_lock();
		if ( is_wp_error( $lease ) ) {
			return $lease;
		}
		try {
			$activate = rest_sanitize_boolean( $request->get_param( 'activate' ) );
			if ( self::DEPLOY_PHRASE !== $request->get_param( 'deployment_confirmation' ) ) {
				return new WP_Error( 'tra_vel_agent_deployment_confirmation', 'Agent Core deployment confirmation did not match.', array( 'status' => 400 ) );
			}
			if ( $activate && self::ACTIVATE_PHRASE !== $request->get_param( 'activation_confirmation' ) ) {
				return new WP_Error( 'tra_vel_agent_activation_confirmation', 'Agent Core activation confirmation did not match.', array( 'status' => 400 ) );
			}
			if ( $activate && ! current_user_can( 'activate_plugins' ) ) {
				return new WP_Error( 'tra_vel_agent_activation_forbidden', 'The current user cannot activate plugins.', array( 'status' => 403 ) );
			}

			$package = $this->get_uploaded_package( $request );
			if ( is_wp_error( $package ) ) {
				return $package;
			}
			$expected_hash = strtolower( (string) $request->get_header( 'x-tra-vel-sha256' ) );
			$actual_hash   = hash_file( 'sha256', $package['tmp_name'] );
			if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $expected_hash ) || ! hash_equals( $expected_hash, $actual_hash ) ) {
				return new WP_Error( 'tra_vel_agent_hash_mismatch', 'Agent Core package checksum did not match.', array( 'status' => 400 ) );
			}

			$package_data = $this->inspect_package( $package['tmp_name'] );
			if ( is_wp_error( $package_data ) ) {
				return $package_data;
			}
			$this->load_plugin_api();
			$current_path = trailingslashit( WP_PLUGIN_DIR ) . self::PLUGIN_FILE;
			if ( is_file( $current_path ) ) {
				$current_data    = get_plugin_data( $current_path, false, false );
				$current_version = isset( $current_data['Version'] ) ? (string) $current_data['Version'] : '';
				if ( $current_version && version_compare( $package_data['version'], $current_version, '<' ) ) {
					return new WP_Error( 'tra_vel_agent_downgrade_blocked', 'Agent Core downgrades require the protected rollback route.', array( 'status' => 409, 'installed_version' => $current_version, 'package_version' => $package_data['version'] ) );
				}
				if ( $current_version && 0 === version_compare( $package_data['version'], $current_version ) ) {
					$last = get_option( self::OPTION_LAST, array() );
					if ( ! is_array( $last ) || empty( $last['sha256'] ) || ! hash_equals( (string) $last['sha256'], $actual_hash ) ) {
						return new WP_Error( 'tra_vel_agent_same_version_changed', 'A different Agent Core artifact cannot overwrite the same installed version. Increase the plugin version or use rollback.', array( 'status' => 409, 'installed_version' => $current_version ) );
					}
					if ( $activate && ! is_plugin_active( self::PLUGIN_FILE ) ) {
						$activated = activate_plugin( self::PLUGIN_FILE );
						if ( is_wp_error( $activated ) || ! is_plugin_active( self::PLUGIN_FILE ) ) {
							return new WP_Error( 'tra_vel_agent_activation_failed', is_wp_error( $activated ) ? $activated->get_error_message() : 'Agent Core activation failed.', array( 'status' => 500 ) );
						}
					}
					return rest_ensure_response( array( 'ok' => true, 'plugin' => self::PLUGIN_SLUG, 'version' => $current_version, 'sha256' => $actual_hash, 'backup' => null, 'active' => is_plugin_active( self::PLUGIN_FILE ), 'unchanged' => true ) );
				}
			}
			$was_active = is_plugin_active( self::PLUGIN_FILE );
			$backup = $this->backup_current_plugin();
			if ( is_wp_error( $backup ) ) {
				return $backup;
			}
			$installed = $this->install_package( $package['tmp_name'] );
			if ( is_wp_error( $installed ) ) {
				return $this->recover_deployment_error( 'tra_vel_agent_install_failed', $installed->get_error_message(), $backup, $was_active );
			}
			$this->load_plugin_api();
			$installed_path = trailingslashit( WP_PLUGIN_DIR ) . self::PLUGIN_FILE;
			$installed_data = is_file( $installed_path ) ? get_plugin_data( $installed_path, false, false ) : array();
			if ( self::PLUGIN_NAME !== ( isset( $installed_data['Name'] ) ? $installed_data['Name'] : '' ) || $package_data['version'] !== ( isset( $installed_data['Version'] ) ? $installed_data['Version'] : '' ) ) {
				return $this->recover_deployment_error( 'tra_vel_agent_installed_identity_mismatch', 'The installed Agent Core identity or version did not match the inspected package.', $backup, $was_active );
			}
			if ( $activate ) {
				$result = activate_plugin( self::PLUGIN_FILE );
				if ( is_wp_error( $result ) || ! is_plugin_active( self::PLUGIN_FILE ) ) {
					$message = is_wp_error( $result ) ? $result->get_error_message() : 'Agent Core activation failed.';
					return $this->recover_deployment_error( 'tra_vel_agent_activation_failed', $message, $backup, $was_active );
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
					'ok'      => true,
					'plugin'  => self::PLUGIN_SLUG,
					'version' => $package_data['version'],
					'sha256'  => $actual_hash,
					'backup'  => $backup,
					'active'  => is_plugin_active( self::PLUGIN_FILE ),
				),
				200
			);
		} finally {
			$this->release_lock( $lease );
		}
	}

	public function rollback( WP_REST_Request $request ) {
		if ( self::ROLLBACK_PHRASE !== $request->get_param( 'confirmation' ) ) {
			return new WP_Error( 'tra_vel_agent_rollback_confirmation', 'Agent Core rollback confirmation did not match.', array( 'status' => 400 ) );
		}
		$lease = $this->acquire_lock();
		if ( is_wp_error( $lease ) ) {
			return $lease;
		}
		try {
			$backup_name = (string) $request->get_param( 'backup' );
			$backups     = $this->list_backups();
			if ( 'latest' === $backup_name ) {
				$backup_name = reset( $backups );
			}
			if ( ! $backup_name || ! in_array( $backup_name, $backups, true ) ) {
				return new WP_Error( 'tra_vel_agent_backup_missing', 'The requested Agent Core backup was not found.', array( 'status' => 404 ) );
			}
			$restored = $this->restore_backup( $backup_name );
			if ( is_wp_error( $restored ) ) {
				return $restored;
			}
			$status = $this->get_status()->get_data();
			update_option( self::OPTION_LAST, array( 'rolled_back_at' => gmdate( 'c' ), 'backup' => $backup_name, 'version' => $status['installed_version'], 'user_id' => get_current_user_id() ), false );
			return rest_ensure_response( array( 'ok' => true, 'restored' => $backup_name, 'version' => $status['installed_version'], 'active' => $status['active'] ) );
		} finally {
			$this->release_lock( $lease );
		}
	}

	public function remove_failed_fresh_install( WP_REST_Request $request ) {
		if ( self::REMOVE_FRESH_PHRASE !== $request->get_param( 'confirmation' ) ) {
			return new WP_Error( 'tra_vel_agent_fresh_recovery_confirmation', 'Fresh-install recovery confirmation did not match.', array( 'status' => 400 ) );
		}
		$lease = $this->acquire_lock();
		if ( is_wp_error( $lease ) ) {
			return $lease;
		}
		try {
			$version = (string) $request->get_param( 'version' );
			$sha256  = (string) $request->get_param( 'sha256' );
			$last    = get_option( self::OPTION_LAST, null );
			$recent  = is_array( $last ) && ! empty( $last['deployed_at'] ) && strtotime( (string) $last['deployed_at'] ) >= time() - 30 * MINUTE_IN_SECONDS;
			$fresh   = is_array( $last ) && array_key_exists( 'backup', $last ) && null === $last['backup'];
			$matches = is_array( $last ) && isset( $last['version'], $last['sha256'] ) && hash_equals( (string) $last['version'], $version ) && hash_equals( (string) $last['sha256'], $sha256 );
			if ( ! $recent || ! $fresh || ! $matches ) {
				return new WP_Error( 'tra_vel_agent_fresh_recovery_scope_changed', 'The failed fresh-install recovery scope no longer matches the latest deployment.', array( 'status' => 409 ) );
			}

			$this->load_plugin_api();
			$main = trailingslashit( WP_PLUGIN_DIR ) . self::PLUGIN_FILE;
			if ( is_file( $main ) ) {
				$data = get_plugin_data( $main, false, false );
				if ( self::PLUGIN_NAME !== ( isset( $data['Name'] ) ? $data['Name'] : '' ) || $version !== ( isset( $data['Version'] ) ? $data['Version'] : '' ) ) {
					return new WP_Error( 'tra_vel_agent_fresh_recovery_identity_changed', 'The installed plugin no longer matches the failed fresh deployment.', array( 'status' => 409 ) );
				}
			}
			if ( is_plugin_active( self::PLUGIN_FILE ) ) {
				deactivate_plugins( self::PLUGIN_FILE, true );
			}
			$deleted = $this->delete_directory( trailingslashit( WP_PLUGIN_DIR ) . self::PLUGIN_SLUG );
			if ( is_wp_error( $deleted ) || is_dir( trailingslashit( WP_PLUGIN_DIR ) . self::PLUGIN_SLUG ) || is_file( $main ) ) {
				return is_wp_error( $deleted ) ? $deleted : new WP_Error( 'tra_vel_agent_fresh_recovery_delete_failed', 'The failed fresh Agent Core installation could not be removed.', array( 'status' => 500 ) );
			}
			update_option( self::OPTION_LAST, array( 'recovered_at' => gmdate( 'c' ), 'recovery' => 'removed_failed_fresh_install', 'failed_version' => $version, 'failed_sha256' => $sha256, 'user_id' => get_current_user_id() ), false );
			return rest_ensure_response( array( 'ok' => true, 'removed' => true, 'version' => $version, 'sha256' => $sha256 ) );
		} finally {
			$this->release_lock( $lease );
		}
	}

	private function acquire_lock() {
		global $wpdb;
		$lease = wp_generate_uuid4() . '|' . ( time() + 15 * MINUTE_IN_SECONDS );
		if ( add_option( self::LOCK_KEY, $lease, '', false ) ) {
			return $lease;
		}
		$current = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::LOCK_KEY ) );
		$parts   = explode( '|', $current, 2 );
		$expires = isset( $parts[1] ) ? absint( $parts[1] ) : PHP_INT_MAX;
		if ( $current && $expires < time() ) {
			$replaced = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $lease, self::LOCK_KEY, $current ) );
			if ( 1 === $replaced ) {
				wp_cache_delete( self::LOCK_KEY, 'options' );
				return $lease;
			}
		}
		return new WP_Error( 'tra_vel_agent_deploy_locked', 'Another Agent Core deployment is running.', array( 'status' => 409 ) );
	}

	private function release_lock( $lease ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK_KEY, (string) $lease ) );
		wp_cache_delete( self::LOCK_KEY, 'options' );
	}

	private function get_uploaded_package( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['package'] ) || ! is_array( $files['package'] ) ) {
			return new WP_Error( 'tra_vel_agent_package_missing', 'An Agent Core package is required.', array( 'status' => 400 ) );
		}
		$file = $files['package'];
		if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'tra_vel_agent_upload_failed', 'The Agent Core upload failed.', array( 'status' => 400 ) );
		}
		if ( (int) $file['size'] < 1 || (int) $file['size'] > self::MAX_PACKAGE_BYTES || 'zip' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'tra_vel_agent_package_invalid', 'The Agent Core package is outside the allowed size or type.', array( 'status' => 400 ) );
		}
		return $file;
	}

	private function inspect_package( $package_path ) {
		$filesystem = $this->load_filesystem_api();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}
		$inspect_dir = trailingslashit( get_temp_dir() ) . 'tra-vel-agent-inspect-' . wp_generate_password( 12, false, false );
		wp_mkdir_p( $inspect_dir );
		$result = unzip_file( $package_path, $inspect_dir );
		if ( is_wp_error( $result ) ) {
			$this->delete_directory( $inspect_dir );
			return new WP_Error( 'tra_vel_agent_invalid_zip', $result->get_error_message(), array( 'status' => 400 ) );
		}
		$entries = array_values( array_diff( scandir( $inspect_dir ), array( '.', '..' ) ) );
		$main    = trailingslashit( $inspect_dir ) . self::PLUGIN_FILE;
		if ( array( self::PLUGIN_SLUG ) !== $entries || ! is_file( $main ) ) {
			$this->delete_directory( $inspect_dir );
			return new WP_Error( 'tra_vel_agent_package_layout', 'The ZIP must contain only tra-vel-agent-core at its root.', array( 'status' => 400 ) );
		}
		$data = get_file_data( $main, array( 'name' => 'Plugin Name', 'version' => 'Version' ) );
		$this->delete_directory( $inspect_dir );
		if ( self::PLUGIN_NAME !== $data['name'] || ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $data['version'] ) ) {
			return new WP_Error( 'tra_vel_agent_plugin_headers', 'Agent Core plugin headers are invalid.', array( 'status' => 400 ) );
		}
		return $data;
	}

	private function install_package( $package_path ) {
		$filesystem = $this->load_filesystem_api();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $package_path, array( 'overwrite_package' => true ) );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'tra_vel_agent_install_failed', $result->get_error_message(), array( 'status' => 500 ) );
		}
		if ( ! $result ) {
			$message = $skin->get_errors()->has_errors() ? $skin->get_errors()->get_error_message() : 'WordPress could not install Agent Core.';
			return new WP_Error( 'tra_vel_agent_install_failed', $message, array( 'status' => 500 ) );
		}
		$this->load_plugin_api();
		return is_file( trailingslashit( WP_PLUGIN_DIR ) . self::PLUGIN_FILE ) ? true : new WP_Error( 'tra_vel_agent_plugin_invalid', 'The installed Agent Core path is invalid.', array( 'status' => 500 ) );
	}

	private function recover_deployment_error( $code, $message, $backup, $was_active ) {
		$recovered = $this->recover_previous_plugin( $backup, $was_active );
		if ( is_wp_error( $recovered ) ) {
			return new WP_Error(
				$code . '_recovery_failed',
				$message . ' Automatic recovery also failed: ' . $recovered->get_error_message(),
				array( 'status' => 500, 'recovered' => false, 'backup' => $backup )
			);
		}
		return new WP_Error( $code, $message, array( 'status' => 500, 'recovered' => true, 'backup' => $backup ) );
	}

	private function recover_previous_plugin( $backup, $was_active ) {
		if ( $backup ) {
			$restored = $this->restore_backup( $backup );
			if ( is_wp_error( $restored ) ) {
				return $restored;
			}
		} else {
			$deleted = $this->delete_directory( trailingslashit( WP_PLUGIN_DIR ) . self::PLUGIN_SLUG );
			if ( is_wp_error( $deleted ) || is_dir( trailingslashit( WP_PLUGIN_DIR ) . self::PLUGIN_SLUG ) ) {
				return is_wp_error( $deleted ) ? $deleted : new WP_Error( 'tra_vel_agent_recovery_delete_failed', 'The failed fresh Agent Core install could not be removed.' );
			}
		}
		if ( $was_active ) {
			$this->load_plugin_api();
			$activated = activate_plugin( self::PLUGIN_FILE );
			if ( is_wp_error( $activated ) || ! is_plugin_active( self::PLUGIN_FILE ) ) {
				return new WP_Error( 'tra_vel_agent_recovery_activation_failed', is_wp_error( $activated ) ? $activated->get_error_message() : 'The restored Agent Core plugin is not active.' );
			}
		}
		return true;
	}

	private function backup_current_plugin() {
		$source = trailingslashit( WP_PLUGIN_DIR ) . self::PLUGIN_SLUG;
		if ( ! is_dir( $source ) ) {
			return null;
		}
		$filesystem = $this->load_filesystem_api();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}
		$root = $this->backup_root();
		if ( ! wp_mkdir_p( $root ) ) {
			return new WP_Error( 'tra_vel_agent_backup_root', 'Could not create the Agent Core backup directory.', array( 'status' => 500 ) );
		}
		$name = self::PLUGIN_SLUG . '-' . gmdate( 'Ymd\THis\Z' ) . '-' . wp_generate_password( 8, false, false );
		$result = copy_dir( $source, trailingslashit( $root ) . $name );
		return is_wp_error( $result ) ? new WP_Error( 'tra_vel_agent_backup_failed', $result->get_error_message(), array( 'status' => 500 ) ) : $name;
	}

	private function restore_backup( $backup_name ) {
		$filesystem = $this->load_filesystem_api();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}
		global $wp_filesystem;
		$source     = trailingslashit( $this->backup_root() ) . $backup_name;
		$target     = trailingslashit( WP_PLUGIN_DIR ) . self::PLUGIN_SLUG;
		$stage      = trailingslashit( WP_PLUGIN_DIR ) . self::PLUGIN_SLUG . '-restore-' . wp_generate_password( 8, false, false );
		$quarantine = trailingslashit( $this->backup_root() ) . '.quarantine-' . wp_generate_password( 8, false, false );
		if ( ! is_file( trailingslashit( $source ) . 'tra-vel-agent-core.php' ) ) {
			return new WP_Error( 'tra_vel_agent_backup_invalid', 'The backup is not a valid Agent Core plugin.', array( 'status' => 400 ) );
		}
		$this->delete_directory( $stage );
		$copied = copy_dir( $source, $stage );
		if ( is_wp_error( $copied ) ) {
			return new WP_Error( 'tra_vel_agent_restore_stage', $copied->get_error_message(), array( 'status' => 500 ) );
		}
		$current_backup = $this->backup_current_plugin();
		if ( is_wp_error( $current_backup ) ) {
			$this->delete_directory( $stage );
			return $current_backup;
		}
		if ( is_dir( $target ) && ! $wp_filesystem->move( $target, $quarantine, true ) ) {
			$this->delete_directory( $stage );
			return new WP_Error( 'tra_vel_agent_restore_quarantine', 'Could not move the current Agent Core before rollback.', array( 'status' => 500 ) );
		}
		if ( ! $wp_filesystem->move( $stage, $target, true ) ) {
			$fallback_restored = ! is_dir( $quarantine ) || $wp_filesystem->move( $quarantine, $target, true );
			$this->load_plugin_api();
			$live_main = trailingslashit( $target ) . 'tra-vel-agent-core.php';
			$live_data = $fallback_restored && is_file( $live_main ) ? get_plugin_data( $live_main, false, false ) : array();
			if ( ! $fallback_restored || self::PLUGIN_NAME !== ( isset( $live_data['Name'] ) ? $live_data['Name'] : '' ) ) {
				return new WP_Error(
					'tra_vel_agent_live_plugin_missing',
					'Rollback failed and the prior live Agent Core plugin could not be restored. Recovery directories were preserved for an administrator.',
					array( 'status' => 500, 'recovery_state' => 'live_plugin_missing', 'stage' => basename( $stage ), 'quarantine' => basename( $quarantine ) )
				);
			}
			$this->delete_directory( $stage );
			return new WP_Error( 'tra_vel_agent_restore_failed', 'Could not restore the Agent Core backup.', array( 'status' => 500 ) );
		}
		$this->delete_directory( $quarantine );
		return true;
	}

	private function list_backups() {
		$root = $this->backup_root();
		if ( ! is_dir( $root ) ) {
			return array();
		}
		$backups = array();
		foreach ( scandir( $root ) as $entry ) {
			if ( 1 === preg_match( '/^tra-vel-agent-core-\d{8}T\d{6}Z-[A-Za-z0-9]+$/', $entry ) && is_file( trailingslashit( $root ) . $entry . '/tra-vel-agent-core.php' ) ) {
				$backups[ $entry ] = filemtime( trailingslashit( $root ) . $entry );
			}
		}
		arsort( $backups, SORT_NUMERIC );
		return array_keys( $backups );
	}

	private function prune_backups() {
		foreach ( array_slice( $this->list_backups(), self::BACKUP_LIMIT ) as $backup ) {
			$this->delete_directory( trailingslashit( $this->backup_root() ) . $backup );
		}
	}

	private function load_plugin_api() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	private function load_filesystem_api() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;
		$initialized = WP_Filesystem();
		return $initialized && is_object( $wp_filesystem ) ? true : new WP_Error( 'tra_vel_agent_filesystem_unavailable', 'WordPress filesystem access is unavailable for Agent Core deployment.', array( 'status' => 503 ) );
	}

	private function delete_directory( $path ) {
		$filesystem = $this->load_filesystem_api();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}
		global $wp_filesystem;
		if ( $path && is_dir( $path ) ) {
			return $wp_filesystem->delete( $path, true );
		}
		return true;
	}

	private function backup_root() {
		return WP_CONTENT_DIR . '/tra-vel-agent-core-releases';
	}
}
