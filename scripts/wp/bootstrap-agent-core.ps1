[CmdletBinding()]
param(
    [string]$SiteUrl = 'https://tra-vel.co.il',
    [string]$CredentialPath = 'C:\Users\janana\Documents\.codex-secrets\wordpress-app-passwords\tra-vel.co.il.credential.xml',
    [string]$AgentArchive = '',
    [string]$DeploymentConfirmation = ''
)

$ErrorActionPreference = 'Stop'
$requiredConfirmation = 'INSTALL TRA-VEL AGENT CORE'
$pluginSlug = 'tra-vel-agent-core'
$pluginFile = 'tra-vel-agent-core/tra-vel-agent-core.php'
$manifest = $null

$siteUri = $null
if (-not [Uri]::TryCreate($SiteUrl, [UriKind]::Absolute, [ref]$siteUri) -or $siteUri.Scheme -ne 'https' -or -not $siteUri.Host) {
    throw 'SiteUrl must be an absolute HTTPS URL.'
}
if ($DeploymentConfirmation -ne $requiredConfirmation) {
    throw "Deployment requires the exact phrase $requiredConfirmation."
}
if (-not (Test-Path -LiteralPath $CredentialPath -PathType Leaf)) {
    throw "Credential file not found: $CredentialPath"
}

$repoRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
if (-not $AgentArchive) {
    & python (Join-Path $repoRoot 'scripts\ci\build_agent_core.py')
    if ($LASTEXITCODE -ne 0) {
        throw 'Agent Core packaging failed.'
    }
    $manifestPath = Join-Path $repoRoot 'dist\agent-core-manifest.json'
    if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) {
        throw "Agent Core manifest not found: $manifestPath"
    }
    $manifest = Get-Content -Raw -LiteralPath $manifestPath | ConvertFrom-Json
    if ($manifest.plugin -ne $pluginSlug -or -not $manifest.archive -or $manifest.sha256 -notmatch '^[a-f0-9]{64}$') {
        throw 'Agent Core manifest is invalid.'
    }
    $AgentArchive = Join-Path $repoRoot ('dist\' + [string]$manifest.archive)
}
if (-not (Test-Path -LiteralPath $AgentArchive -PathType Leaf)) {
    throw "Agent Core archive not found: $AgentArchive"
}
if ([IO.Path]::GetExtension($AgentArchive).ToLowerInvariant() -ne '.zip') {
    throw 'Agent Core archive must be a ZIP file.'
}

$sha256 = (Get-FileHash -LiteralPath $AgentArchive -Algorithm SHA256).Hash.ToLowerInvariant()
if ($manifest -and $manifest.sha256 -ne $sha256) {
    throw 'Agent Core archive checksum does not match its manifest.'
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$archiveInspection = [IO.Compression.ZipFile]::OpenRead((Resolve-Path -LiteralPath $AgentArchive).Path)
try {
    $entryNames = @($archiveInspection.Entries | ForEach-Object { $_.FullName.Replace('\', '/') })
    $expectedMain = $pluginFile
    if ($expectedMain -notin $entryNames) {
        throw "Agent Core archive is missing $expectedMain."
    }
    foreach ($entryName in $entryNames) {
        $segments = @($entryName.Split('/', [StringSplitOptions]::RemoveEmptyEntries))
        if (-not $entryName.StartsWith($pluginSlug + '/') -or $entryName.StartsWith('/') -or '..' -in $segments) {
            throw "Agent Core archive contains an invalid path: $entryName"
        }
    }
}
finally {
    $archiveInspection.Dispose()
}

$bootstrapCode = @'
add_action( 'rest_api_init', static function () {
	register_rest_route( 'tra-vel-agent-bootstrap/v1', '/plugin', array(
		'methods' => WP_REST_Server::CREATABLE,
		'permission_callback' => static function () {
			return current_user_can( 'install_plugins' ) && current_user_can( 'update_plugins' ) && current_user_can( 'activate_plugins' ) && current_user_can( 'delete_plugins' );
		},
		'callback' => static function ( WP_REST_Request $request ) {
			$lock_key = 'tra_vel_agent_bootstrap_lock';
			$lock_value = wp_generate_uuid4() . '|' . ( time() + 15 * MINUTE_IN_SECONDS );
			$lock_acquired = add_option( $lock_key, $lock_value, '', false );
			global $wpdb;
			if ( ! $lock_acquired ) {
				$current_lock = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $lock_key ) );
				$lock_parts   = explode( '|', $current_lock, 2 );
				$lock_expires = isset( $lock_parts[1] ) ? absint( $lock_parts[1] ) : PHP_INT_MAX;
				if ( $current_lock && $lock_expires < time() ) {
					$lock_acquired = 1 === $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $lock_value, $lock_key, $current_lock ) );
					if ( $lock_acquired ) {
						wp_cache_delete( $lock_key, 'options' );
					}
				}
			}
			if ( ! $lock_acquired ) {
				return new WP_Error( 'tra_vel_agent_bootstrap_locked', 'Another Agent Core installation is running.', array( 'status' => 409 ) );
			}
			$inspect_dir = '';
			try {
				if ( 'cleanup_failed_install' === sanitize_key( (string) $request->get_param( 'operation' ) ) ) {
					if ( 'REMOVE FAILED TRA-VEL AGENT CORE' !== $request->get_param( 'confirmation' ) ) {
						return new WP_Error( 'tra_vel_agent_bootstrap_cleanup_confirmation', 'Failed-install cleanup confirmation did not match.', array( 'status' => 400 ) );
					}
					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
					global $wp_filesystem;
					if ( ! WP_Filesystem() || ! is_object( $wp_filesystem ) ) {
						return new WP_Error( 'tra_vel_agent_bootstrap_cleanup_filesystem', 'WordPress filesystem access is unavailable for failed-install cleanup.', array( 'status' => 503 ) );
					}
					$plugin = 'tra-vel-agent-core/tra-vel-agent-core.php';
					if ( is_plugin_active( $plugin ) ) {
						deactivate_plugins( $plugin, true );
					}
					$failed_root = trailingslashit( WP_PLUGIN_DIR ) . 'tra-vel-agent-core';
					$wp_filesystem->delete( $failed_root, true );
					wp_clean_plugins_cache( true );
					return is_dir( $failed_root ) || is_file( trailingslashit( $failed_root ) . 'tra-vel-agent-core.php' )
						? new WP_Error( 'tra_vel_agent_bootstrap_cleanup_failed', 'The failed Agent Core installation could not be removed.', array( 'status' => 500 ) )
						: rest_ensure_response( array( 'ok' => true, 'removed' => true ) );
				}
				$files = $request->get_file_params();
				if ( empty( $files['package'] ) || ! is_array( $files['package'] ) ) {
					return new WP_Error( 'tra_vel_agent_bootstrap_missing', 'An Agent Core plugin package is required.', array( 'status' => 400 ) );
				}
				$file = $files['package'];
				if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
					return new WP_Error( 'tra_vel_agent_bootstrap_upload', 'The Agent Core plugin upload failed.', array( 'status' => 400 ) );
				}
				if ( (int) $file['size'] < 1 || (int) $file['size'] > 5242880 || 'zip' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) ) {
					return new WP_Error( 'tra_vel_agent_bootstrap_package', 'The Agent Core package is invalid.', array( 'status' => 400 ) );
				}

				$expected = strtolower( (string) $request->get_header( 'x-tra-vel-sha256' ) );
				$actual   = hash_file( 'sha256', $file['tmp_name'] );
				if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $expected ) || ! hash_equals( $expected, $actual ) ) {
					return new WP_Error( 'tra_vel_agent_bootstrap_hash', 'The Agent Core checksum did not match.', array( 'status' => 400 ) );
				}

				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				global $wp_filesystem;
				if ( ! WP_Filesystem() || ! is_object( $wp_filesystem ) ) {
					return new WP_Error( 'tra_vel_agent_bootstrap_filesystem', 'WordPress filesystem access is unavailable for Agent Core installation.', array( 'status' => 503 ) );
				}
				$failed_root = trailingslashit( WP_PLUGIN_DIR ) . 'tra-vel-agent-core';
				$cleanup_fresh = static function () use ( $wp_filesystem, $failed_root ) {
					$plugin = 'tra-vel-agent-core/tra-vel-agent-core.php';
					if ( is_plugin_active( $plugin ) ) {
						deactivate_plugins( $plugin, true );
					}
					$wp_filesystem->delete( $failed_root, true );
					wp_clean_plugins_cache( true );
					return ! is_dir( $failed_root ) && ! is_file( trailingslashit( $failed_root ) . 'tra-vel-agent-core.php' );
				};
				$inspect_dir = trailingslashit( get_temp_dir() ) . 'tra-vel-agent-inspect-' . wp_generate_password( 12, false, false );
				if ( ! wp_mkdir_p( $inspect_dir ) ) {
					return new WP_Error( 'tra_vel_agent_bootstrap_inspect', 'Could not create an inspection directory.', array( 'status' => 500 ) );
				}
				$unzipped = unzip_file( $file['tmp_name'], $inspect_dir );
				if ( is_wp_error( $unzipped ) ) {
					return new WP_Error( 'tra_vel_agent_bootstrap_zip', $unzipped->get_error_message(), array( 'status' => 400 ) );
				}

				$entries = array_values( array_diff( scandir( $inspect_dir ), array( '.', '..' ) ) );
				$plugin_root = trailingslashit( $inspect_dir ) . 'tra-vel-agent-core';
				$main_file   = trailingslashit( $plugin_root ) . 'tra-vel-agent-core.php';
				if ( array( 'tra-vel-agent-core' ) !== $entries || ! is_dir( $plugin_root ) || ! is_file( $main_file ) ) {
					return new WP_Error( 'tra_vel_agent_bootstrap_layout', 'The ZIP must contain only tra-vel-agent-core at its root.', array( 'status' => 400 ) );
				}
				$headers = get_file_data( $main_file, array( 'name' => 'Plugin Name', 'version' => 'Version' ) );
				if ( 'Tra-Vel Agent Core' !== $headers['name'] || 1 !== preg_match( '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $headers['version'] ) ) {
					return new WP_Error( 'tra_vel_agent_bootstrap_headers', 'The Agent Core plugin headers are invalid.', array( 'status' => 400 ) );
				}

				if ( $wp_filesystem ) {
					$wp_filesystem->delete( $inspect_dir, true );
					$inspect_dir = '';
				}

				$plugin = 'tra-vel-agent-core/tra-vel-agent-core.php';
				$installed_main = trailingslashit( WP_PLUGIN_DIR ) . $plugin;
				if ( is_file( $installed_main ) ) {
					return new WP_Error( 'tra_vel_agent_bootstrap_existing', 'Agent Core is already installed. Use the protected deployment gateway for updates.', array( 'status' => 409 ) );
				}
				$skin      = new Automatic_Upgrader_Skin();
				$upgrader  = new Plugin_Upgrader( $skin );
				$installed = $upgrader->install( $file['tmp_name'], array( 'overwrite_package' => true ) );
				if ( is_wp_error( $installed ) ) {
					$cleaned = $cleanup_fresh();
					return new WP_Error( $cleaned ? 'tra_vel_agent_bootstrap_install' : 'tra_vel_agent_bootstrap_install_cleanup_failed', $installed->get_error_message(), array( 'status' => 500, 'fresh_install_removed' => $cleaned ) );
				}
				if ( ! $installed ) {
					$message = $skin->get_errors()->has_errors() ? $skin->get_errors()->get_error_message() : 'WordPress could not install Agent Core.';
					$cleaned = $cleanup_fresh();
					return new WP_Error( $cleaned ? 'tra_vel_agent_bootstrap_install' : 'tra_vel_agent_bootstrap_install_cleanup_failed', $message, array( 'status' => 500, 'fresh_install_removed' => $cleaned ) );
				}

				if ( ! is_file( $installed_main ) ) {
					$cleaned = $cleanup_fresh();
					return new WP_Error( $cleaned ? 'tra_vel_agent_bootstrap_installed_layout' : 'tra_vel_agent_bootstrap_layout_cleanup_failed', 'The installed Agent Core path is invalid.', array( 'status' => 500, 'fresh_install_removed' => $cleaned ) );
				}
				$installed_headers = get_file_data( $installed_main, array( 'name' => 'Plugin Name', 'version' => 'Version' ) );
				if ( 'Tra-Vel Agent Core' !== $installed_headers['name'] || $headers['version'] !== $installed_headers['version'] ) {
					$cleaned = $cleanup_fresh();
					return new WP_Error( $cleaned ? 'tra_vel_agent_bootstrap_installed_headers' : 'tra_vel_agent_bootstrap_headers_cleanup_failed', 'The installed Agent Core identity did not match the inspected package.', array( 'status' => 500, 'fresh_install_removed' => $cleaned ) );
				}

				$activated = activate_plugin( $plugin );
				if ( is_wp_error( $activated ) ) {
					$cleaned = $cleanup_fresh();
					return new WP_Error( $cleaned ? 'tra_vel_agent_bootstrap_activate' : 'tra_vel_agent_bootstrap_activate_cleanup_failed', $activated->get_error_message(), array( 'status' => 500, 'fresh_install_removed' => $cleaned ) );
				}
				if ( ! is_plugin_active( $plugin ) ) {
					$cleaned = $cleanup_fresh();
					return new WP_Error( $cleaned ? 'tra_vel_agent_bootstrap_inactive' : 'tra_vel_agent_bootstrap_inactive_cleanup_failed', 'Agent Core was installed but is not active.', array( 'status' => 500, 'fresh_install_removed' => $cleaned ) );
				}

				wp_clean_plugins_cache( true );
				return rest_ensure_response( array(
					'ok'      => true,
					'plugin'  => $plugin,
					'version' => $installed_headers['version'],
					'active'  => true,
					'sha256'  => $actual,
				) );
			} finally {
				if ( $inspect_dir && is_dir( $inspect_dir ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					WP_Filesystem();
					global $wp_filesystem;
					if ( $wp_filesystem ) {
						$wp_filesystem->delete( $inspect_dir, true );
					}
				}
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $lock_key, $lock_value ) );
				wp_cache_delete( $lock_key, 'options' );
			}
		},
	) );
} );
'@

Add-Type -AssemblyName System.Net.Http
$credential = Import-Clixml -LiteralPath $CredentialPath
$passwordPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($credential.Password)
$plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPtr)
$snippetId = $null
$client = $null
$fileStream = $null
$multipart = $null
$headers = $null

try {
    $basic = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($credential.UserName + ':' + $plainPassword))
    $headers = @{ Authorization = 'Basic ' + $basic }
    $baseUrl = $SiteUrl.TrimEnd('/')
    $snippetsUrl = $baseUrl + '/wp-json/code-snippets/v1/snippets'
    $payload = @{
        name = 'Tra-Vel Agent Core bootstrap'
        desc = 'Temporary authenticated Agent Core installer; neutralized and deleted automatically.'
        code = $bootstrapCode
        tags = @('tra-vel', 'agent-core', 'temporary-bootstrap')
        scope = 'global'
        active = $false
        priority = 10
    } | ConvertTo-Json -Depth 6

    $snippet = Invoke-RestMethod -Uri $snippetsUrl -Method Post -Headers $headers -ContentType 'application/json' -Body $payload -TimeoutSec 30
    $snippetId = [int]$snippet.id
    if ($snippetId -lt 1 -or $snippet.code_error) {
        throw "The temporary Agent Core bootstrap snippet could not be created: $($snippet.code_error)"
    }
    Invoke-RestMethod -Uri "$snippetsUrl/$snippetId/activate" -Method Post -Headers $headers -ContentType 'application/json' -Body '{}' -TimeoutSec 30 | Out-Null

    $handler = [Net.Http.HttpClientHandler]::new()
    $client = [Net.Http.HttpClient]::new($handler)
    $client.Timeout = [TimeSpan]::FromMinutes(3)
    $client.DefaultRequestHeaders.Authorization = [Net.Http.Headers.AuthenticationHeaderValue]::new('Basic', $basic)
    $client.DefaultRequestHeaders.Add('X-Tra-Vel-SHA256', $sha256)
    $multipart = [Net.Http.MultipartFormDataContent]::new()
    $fileStream = [IO.File]::OpenRead((Resolve-Path -LiteralPath $AgentArchive).Path)
    $fileContent = [Net.Http.StreamContent]::new($fileStream)
    $fileContent.Headers.ContentType = [Net.Http.Headers.MediaTypeHeaderValue]::new('application/zip')
    $multipart.Add($fileContent, 'package', [IO.Path]::GetFileName($AgentArchive))

    $bootstrapUrl = $baseUrl + '/wp-json/tra-vel-agent-bootstrap/v1/plugin'
    $response = $client.PostAsync($bootstrapUrl, $multipart).GetAwaiter().GetResult()
    $body = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    if (-not $response.IsSuccessStatusCode) {
        throw "Agent Core installation failed with HTTP $([int]$response.StatusCode): $body"
    }
    $installResult = $body | ConvertFrom-Json
    if (-not $installResult.ok -or $installResult.plugin -ne $pluginFile -or -not $installResult.active -or $installResult.sha256 -ne $sha256) {
        throw 'WordPress did not confirm the exact Agent Core installation.'
    }

    try {
        $health = Invoke-RestMethod -Uri ($baseUrl + '/wp-json/tra-vel-agent/v1/health') -Method Get -TimeoutSec 30
        if (-not $health.ok -or $health.plugin_version -ne $installResult.version) {
            throw 'The activated Agent Core health contract is unavailable or has the wrong version.'
        }
        $capabilityHealth = $health.vip_capability_session_store
        if (
            -not $health.capabilities.no_login_scoped_sessions -or
            -not $capabilityHealth -or
            $capabilityHealth.schema_version -ne '1.1.0' -or
            $capabilityHealth.installed_schema_version -ne '1.1.0' -or
            [int]$capabilityHealth.expected_tables -ne 4 -or
            [int]$capabilityHealth.ready_tables -ne 4 -or
            [int]$capabilityHealth.transactional_tables -ne 4 -or
            [int]$capabilityHealth.required_indexes -ne 7 -or
            [int]$capabilityHealth.ready_indexes -ne 7 -or
            -not $capabilityHealth.required_indexes_ready -or
            -not $capabilityHealth.tables_ready
        ) {
            throw 'The activated Agent Core capability-session schema is not exactly ready.'
        }
        $cockpitHealth = $health.customer_trip_cockpit_store
        if (
            -not $health.capabilities.customer_trip_cockpit -or
            -not $cockpitHealth -or
            $cockpitHealth.schema_version -ne '1.0.0' -or
            $cockpitHealth.installed_schema_version -ne '1.0.0' -or
            [int]$cockpitHealth.retention_days -ne 400 -or
            [int]$cockpitHealth.max_projection_bytes -ne 524288 -or
            [int]$cockpitHealth.expected_tables -ne 3 -or
            [int]$cockpitHealth.ready_tables -ne 3 -or
            [int]$cockpitHealth.transactional_tables -ne 3 -or
            [int]$cockpitHealth.required_indexes -ne 13 -or
            [int]$cockpitHealth.ready_indexes -ne 13 -or
            -not $cockpitHealth.required_indexes_ready -or
            @($cockpitHealth.inspection_errors).Count -ne 0 -or
            -not $cockpitHealth.tables_ready
        ) {
            throw 'The activated Agent Core Customer Trip Cockpit schema is not exactly ready.'
        }
    }
    catch {
        $healthError = $_.Exception.Message
        $cleanupPayload = @{ operation = 'cleanup_failed_install'; confirmation = 'REMOVE FAILED TRA-VEL AGENT CORE' } | ConvertTo-Json -Compress
        try {
            $cleanupResult = Invoke-RestMethod -Uri $bootstrapUrl -Method Post -Headers $headers -ContentType 'application/json' -Body $cleanupPayload -TimeoutSec 60
            if (-not $cleanupResult.ok -or -not $cleanupResult.removed) {
                throw 'WordPress did not confirm failed Agent Core cleanup.'
            }
        }
        catch {
            throw "Agent Core health verification failed and cleanup also failed: $healthError | $($_.Exception.Message)"
        }
        throw "Agent Core health verification failed; the fresh installation was removed: $healthError"
    }

    [pscustomobject]@{
        Plugin = $installResult.plugin
        Version = $installResult.version
        Active = $installResult.active
        Sha256 = $installResult.sha256
        ContractVersion = $health.contract_version
        BootstrapSnippetId = $snippetId
    }
}
finally {
    if ($multipart) { $multipart.Dispose() }
    if ($fileStream) { $fileStream.Dispose() }
    if ($client) { $client.Dispose() }
    if ($snippetId -and $headers) {
        $snippetUrl = "$($SiteUrl.TrimEnd('/'))/wp-json/code-snippets/v1/snippets/$snippetId"
        try { Invoke-RestMethod -Uri "$snippetUrl/deactivate" -Method Post -Headers $headers -ContentType 'application/json' -Body '{}' -TimeoutSec 30 | Out-Null } catch {}
        try {
            $neutralPayload = @{
                name = 'Tra-Vel Agent Core bootstrap removed'
                desc = 'Temporary Agent Core bootstrap completed and code removed.'
                code = '// Temporary Agent Core bootstrap removed.'
                tags = @('tra-vel', 'agent-core', 'removed')
                scope = 'global'
                active = $false
                priority = 10
            } | ConvertTo-Json -Depth 5
            Invoke-RestMethod -Uri $snippetUrl -Method Put -Headers $headers -ContentType 'application/json' -Body $neutralPayload -TimeoutSec 30 | Out-Null
        } catch {}
        # Code Snippets moves a record to trash on the first DELETE and permanently removes it on the second.
        try { Invoke-RestMethod -Uri $snippetUrl -Method Delete -Headers $headers -TimeoutSec 30 | Out-Null } catch {}
        try { Invoke-RestMethod -Uri $snippetUrl -Method Delete -Headers $headers -TimeoutSec 30 | Out-Null } catch {}
    }
    if ($passwordPtr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPtr) }
    $plainPassword = $null
    $basic = $null
}
