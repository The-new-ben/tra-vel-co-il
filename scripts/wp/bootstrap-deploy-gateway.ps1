[CmdletBinding()]
param(
    [string]$SiteUrl = 'https://tra-vel.co.il',
    [string]$CredentialPath = 'C:\Users\janana\Documents\.codex-secrets\wordpress-app-passwords\tra-vel.co.il.credential.xml',
    [string]$GatewayArchive = ''
)

$ErrorActionPreference = 'Stop'

if (-not $SiteUrl.StartsWith('https://')) {
    throw 'SiteUrl must use HTTPS.'
}
if (-not (Test-Path -LiteralPath $CredentialPath)) {
    throw "Credential file not found: $CredentialPath"
}

$repoRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
if (-not $GatewayArchive) {
    & python (Join-Path $repoRoot 'scripts\ci\build_deploy_gateway.py')
    if ($LASTEXITCODE -ne 0) {
        throw 'Deploy gateway packaging failed.'
    }
    $GatewayArchive = Get-ChildItem -LiteralPath (Join-Path $repoRoot 'dist') -Filter 'tra-vel-deploy-gateway-*.zip' |
        Sort-Object LastWriteTime -Descending |
        Select-Object -First 1 -ExpandProperty FullName
}
if (-not (Test-Path -LiteralPath $GatewayArchive)) {
    throw "Gateway archive not found: $GatewayArchive"
}

$bootstrapCode = @'
add_action( 'rest_api_init', static function () {
	register_rest_route( 'tra-vel-bootstrap/v1', '/plugin', array(
		'methods' => WP_REST_Server::CREATABLE,
		'permission_callback' => static function () {
			return current_user_can( 'install_plugins' ) && current_user_can( 'activate_plugins' );
		},
		'callback' => static function ( WP_REST_Request $request ) {
			$files = $request->get_file_params();
			if ( empty( $files['package'] ) || ! is_array( $files['package'] ) ) {
				return new WP_Error( 'tra_vel_bootstrap_missing', 'A plugin package is required.', array( 'status' => 400 ) );
			}
			$file = $files['package'];
			if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
				return new WP_Error( 'tra_vel_bootstrap_upload', 'The plugin upload failed.', array( 'status' => 400 ) );
			}
			if ( (int) $file['size'] < 1 || (int) $file['size'] > 1048576 || 'zip' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) ) {
				return new WP_Error( 'tra_vel_bootstrap_package', 'The plugin package is invalid.', array( 'status' => 400 ) );
			}
			$expected = strtolower( (string) $request->get_header( 'x-tra-vel-sha256' ) );
			$actual = hash_file( 'sha256', $file['tmp_name'] );
			if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $expected ) || ! hash_equals( $expected, $actual ) ) {
				return new WP_Error( 'tra_vel_bootstrap_hash', 'The plugin checksum did not match.', array( 'status' => 400 ) );
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$skin = new Automatic_Upgrader_Skin();
			$upgrader = new Plugin_Upgrader( $skin );
			$installed = $upgrader->install( $file['tmp_name'], array( 'overwrite_package' => true ) );
			if ( is_wp_error( $installed ) ) {
				return new WP_Error( 'tra_vel_bootstrap_install', $installed->get_error_message(), array( 'status' => 500 ) );
			}
			if ( ! $installed ) {
				$message = $skin->get_errors()->has_errors() ? $skin->get_errors()->get_error_message() : 'WordPress could not install the gateway plugin.';
				return new WP_Error( 'tra_vel_bootstrap_install', $message, array( 'status' => 500 ) );
			}
			$plugin = 'tra-vel-deploy-gateway/tra-vel-deploy-gateway.php';
			if ( ! is_file( WP_PLUGIN_DIR . '/' . $plugin ) ) {
				return new WP_Error( 'tra_vel_bootstrap_layout', 'The installed gateway path is invalid.', array( 'status' => 500 ) );
			}
			$activated = activate_plugin( $plugin );
			if ( is_wp_error( $activated ) ) {
				return new WP_Error( 'tra_vel_bootstrap_activate', $activated->get_error_message(), array( 'status' => 500 ) );
			}
			return rest_ensure_response( array( 'ok' => true, 'plugin' => $plugin, 'sha256' => $actual ) );
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

try {
    $basic = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($credential.UserName + ':' + $plainPassword))
    $headers = @{ Authorization = 'Basic ' + $basic }
    $snippetsUrl = $SiteUrl.TrimEnd('/') + '/wp-json/code-snippets/v1/snippets'
    $payload = @{
        name = 'Tra-Vel deploy gateway bootstrap'
        desc = 'Temporary authenticated installer; deleted automatically after use.'
        code = $bootstrapCode
        tags = @('tra-vel', 'temporary-bootstrap')
        scope = 'global'
        active = $false
        priority = 10
    } | ConvertTo-Json -Depth 6

    $snippet = Invoke-RestMethod -Uri $snippetsUrl -Method Post -Headers $headers -ContentType 'application/json' -Body $payload -TimeoutSec 30
    $snippetId = [int]$snippet.id
    if ($snippetId -lt 1 -or $snippet.code_error) {
        throw "The temporary bootstrap snippet could not be created: $($snippet.code_error)"
    }

    Invoke-RestMethod -Uri "$snippetsUrl/$snippetId/activate" -Method Post -Headers $headers -ContentType 'application/json' -Body '{}' -TimeoutSec 30 | Out-Null

    $handler = [Net.Http.HttpClientHandler]::new()
    $client = [Net.Http.HttpClient]::new($handler)
    $client.Timeout = [TimeSpan]::FromMinutes(3)
    $client.DefaultRequestHeaders.Authorization = [Net.Http.Headers.AuthenticationHeaderValue]::new('Basic', $basic)
    $sha256 = (Get-FileHash -LiteralPath $GatewayArchive -Algorithm SHA256).Hash.ToLowerInvariant()
    $client.DefaultRequestHeaders.Add('X-Tra-Vel-SHA256', $sha256)
    $multipart = [Net.Http.MultipartFormDataContent]::new()
    $fileStream = [IO.File]::OpenRead((Resolve-Path -LiteralPath $GatewayArchive).Path)
    $fileContent = [Net.Http.StreamContent]::new($fileStream)
    $fileContent.Headers.ContentType = [Net.Http.Headers.MediaTypeHeaderValue]::new('application/zip')
    $multipart.Add($fileContent, 'package', [IO.Path]::GetFileName($GatewayArchive))

    $bootstrapUrl = $SiteUrl.TrimEnd('/') + '/wp-json/tra-vel-bootstrap/v1/plugin'
    $response = $client.PostAsync($bootstrapUrl, $multipart).GetAwaiter().GetResult()
    $body = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    if (-not $response.IsSuccessStatusCode) {
        throw "Gateway installation failed with HTTP $([int]$response.StatusCode): $body"
    }
    $installResult = $body | ConvertFrom-Json
    if (-not $installResult.ok) {
        throw 'WordPress did not confirm the gateway installation.'
    }

    $statusUrl = $SiteUrl.TrimEnd('/') + '/wp-json/tra-vel-deploy/v1/theme/status'
    $status = Invoke-RestMethod -Uri $statusUrl -Headers $headers -TimeoutSec 30
    if (-not $status.gateway_version) {
        throw 'The installed gateway status endpoint is unavailable.'
    }

    [pscustomobject]@{
        GatewayVersion = $status.gateway_version
        InstalledTheme = $status.installed
        ActiveTheme = $status.active
        BootstrapSnippetId = $snippetId
    }
}
finally {
    if ($multipart) { $multipart.Dispose() }
    if ($fileStream) { $fileStream.Dispose() }
    if ($client) { $client.Dispose() }
    if ($snippetId) {
        try { Invoke-RestMethod -Uri "$($SiteUrl.TrimEnd('/'))/wp-json/code-snippets/v1/snippets/$snippetId/deactivate" -Method Post -Headers $headers -ContentType 'application/json' -Body '{}' -TimeoutSec 30 | Out-Null } catch {}
        try {
            $neutralPayload = @{
                name = 'Tra-Vel bootstrap removed'
                desc = 'Temporary bootstrap completed and code removed.'
                code = '// Temporary bootstrap removed.'
                tags = @('tra-vel', 'removed')
                scope = 'global'
                active = $false
                priority = 10
            } | ConvertTo-Json -Depth 5
            Invoke-RestMethod -Uri "$($SiteUrl.TrimEnd('/'))/wp-json/code-snippets/v1/snippets/$snippetId" -Method Put -Headers $headers -ContentType 'application/json' -Body $neutralPayload -TimeoutSec 30 | Out-Null
        } catch {}
        # Code Snippets moves an item to trash on the first DELETE and permanently removes it on the second.
        # Some versions return an error on permanent deletion, so the record is neutralized before both attempts.
        try { Invoke-RestMethod -Uri "$($SiteUrl.TrimEnd('/'))/wp-json/code-snippets/v1/snippets/$snippetId" -Method Delete -Headers $headers -TimeoutSec 30 | Out-Null } catch {}
        try { Invoke-RestMethod -Uri "$($SiteUrl.TrimEnd('/'))/wp-json/code-snippets/v1/snippets/$snippetId" -Method Delete -Headers $headers -TimeoutSec 30 | Out-Null } catch {}
    }
    if ($passwordPtr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPtr) }
    $plainPassword = $null
    $basic = $null
}
