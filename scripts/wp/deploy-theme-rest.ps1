[CmdletBinding()]
param(
    [string]$SiteUrl = 'https://tra-vel.co.il',
    [string]$CredentialPath = 'C:\Users\janana\Documents\.codex-secrets\wordpress-app-passwords\tra-vel.co.il.credential.xml',
    [string]$ArchivePath = '',
    [string]$DeploymentConfirmation = '',
    [string]$ActivationConfirmation = ''
)

$ErrorActionPreference = 'Stop'

if (-not $SiteUrl.StartsWith('https://')) {
    throw 'SiteUrl must use HTTPS.'
}
if (-not (Test-Path -LiteralPath $CredentialPath)) {
    throw "Credential file not found: $CredentialPath"
}

if (-not $ArchivePath) {
    $repoRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
    $manifestPath = Join-Path $repoRoot 'dist\manifest.json'
    if (-not (Test-Path -LiteralPath $manifestPath)) {
        throw 'No validated build manifest was found. Build from a clean revision before deployment.'
    }
    $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
    if (-not $manifest.archive -or $manifest.sha256 -notmatch '^[a-f0-9]{64}$') {
        throw 'The deployment manifest is invalid.'
    }
    $ArchivePath = Join-Path $repoRoot (Join-Path 'dist' $manifest.archive)
}

if (-not (Test-Path -LiteralPath $ArchivePath)) {
    throw "Theme archive not found: $ArchivePath"
}
if ($DeploymentConfirmation -cne 'DEPLOY TRA-VEL V2') {
    throw 'Deployment requires the exact phrase DEPLOY TRA-VEL V2.'
}
if ($ActivationConfirmation) {
    throw 'This release path is upload-only and cannot activate the production theme.'
}

$archiveFullPath = (Resolve-Path -LiteralPath $ArchivePath).Path
$manifestPath = Join-Path (Split-Path -Parent $archiveFullPath) 'manifest.json'
if (-not (Test-Path -LiteralPath $manifestPath)) {
    throw 'A manifest.json file must accompany the selected archive.'
}
$manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
if ($manifest.theme -cne 'tra-vel-v2' -or $manifest.archive -cne [IO.Path]::GetFileName($archiveFullPath) -or $manifest.sha256 -notmatch '^[a-f0-9]{64}$') {
    throw 'The selected archive does not match the Tra-Vel V2 manifest identity.'
}
$archiveSha256 = (Get-FileHash -LiteralPath $archiveFullPath -Algorithm SHA256).Hash.ToLowerInvariant()
if ($archiveSha256 -cne $manifest.sha256) {
    throw 'The selected archive checksum does not match manifest.json.'
}

Add-Type -AssemblyName System.Net.Http
$credential = Import-Clixml -LiteralPath $CredentialPath
$passwordPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($credential.Password)
$plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPtr)
$client = $null
$fileStream = $null
$multipart = $null

try {
    $basic = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($credential.UserName + ':' + $plainPassword))
    $sha256 = $archiveSha256
    $handler = [Net.Http.HttpClientHandler]::new()
    $client = [Net.Http.HttpClient]::new($handler)
    $client.Timeout = [TimeSpan]::FromMinutes(3)
    $client.DefaultRequestHeaders.Authorization = [Net.Http.Headers.AuthenticationHeaderValue]::new('Basic', $basic)
    $client.DefaultRequestHeaders.Add('X-Tra-Vel-SHA256', $sha256)

    $multipart = [Net.Http.MultipartFormDataContent]::new()
    $fileStream = [IO.File]::OpenRead($archiveFullPath)
    $fileContent = [Net.Http.StreamContent]::new($fileStream)
    $fileContent.Headers.ContentType = [Net.Http.Headers.MediaTypeHeaderValue]::new('application/zip')
    $multipart.Add($fileContent, 'package', [IO.Path]::GetFileName($ArchivePath))
    $multipart.Add([Net.Http.StringContent]::new('false'), 'activate')
    $multipart.Add([Net.Http.StringContent]::new($DeploymentConfirmation), 'deployment_confirmation')
    $multipart.Add([Net.Http.StringContent]::new($ActivationConfirmation), 'activation_confirmation')

    $endpoint = $SiteUrl.TrimEnd('/') + '/wp-json/tra-vel-deploy/v1/theme'
    $response = $client.PostAsync($endpoint, $multipart).GetAwaiter().GetResult()
    $body = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    if (-not $response.IsSuccessStatusCode) {
        throw "WordPress deployment failed with HTTP $([int]$response.StatusCode): $body"
    }

    $result = $body | ConvertFrom-Json
    if (-not $result.ok) {
        throw 'WordPress did not confirm the deployment.'
    }
    [pscustomobject]@{
        Theme = $result.theme
        Version = $result.version
        Active = $result.active
        Backup = $result.backup
        Sha256 = $result.sha256
    }
}
finally {
    if ($multipart) { $multipart.Dispose() }
    if ($fileStream) { $fileStream.Dispose() }
    if ($client) { $client.Dispose() }
    if ($passwordPtr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPtr) }
    $plainPassword = $null
    $basic = $null
}
