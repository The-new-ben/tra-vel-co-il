[CmdletBinding()]
param(
    [string]$SiteUrl = 'https://tra-vel.co.il',
    [string]$CredentialPath = 'C:\Users\janana\Documents\.codex-secrets\wordpress-app-passwords\tra-vel.co.il.credential.xml',
    [string]$ArchivePath = '',
    [switch]$Activate,
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
    & python (Join-Path $repoRoot 'scripts\ci\build_theme.py')
    if ($LASTEXITCODE -ne 0) {
        throw 'Theme packaging failed.'
    }
    $ArchivePath = Get-ChildItem -LiteralPath (Join-Path $repoRoot 'dist') -Filter 'tra-vel-v2-*.zip' |
        Sort-Object LastWriteTime -Descending |
        Select-Object -First 1 -ExpandProperty FullName
}

if (-not (Test-Path -LiteralPath $ArchivePath)) {
    throw "Theme archive not found: $ArchivePath"
}
if ($Activate -and $ActivationConfirmation -ne 'ACTIVATE TRA-VEL V2') {
    throw 'Activation requires the exact phrase ACTIVATE TRA-VEL V2.'
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
    $sha256 = (Get-FileHash -LiteralPath $ArchivePath -Algorithm SHA256).Hash.ToLowerInvariant()
    $handler = [Net.Http.HttpClientHandler]::new()
    $client = [Net.Http.HttpClient]::new($handler)
    $client.Timeout = [TimeSpan]::FromMinutes(3)
    $client.DefaultRequestHeaders.Authorization = [Net.Http.Headers.AuthenticationHeaderValue]::new('Basic', $basic)
    $client.DefaultRequestHeaders.Add('X-Tra-Vel-SHA256', $sha256)

    $multipart = [Net.Http.MultipartFormDataContent]::new()
    $fileStream = [IO.File]::OpenRead((Resolve-Path -LiteralPath $ArchivePath).Path)
    $fileContent = [Net.Http.StreamContent]::new($fileStream)
    $fileContent.Headers.ContentType = [Net.Http.Headers.MediaTypeHeaderValue]::new('application/zip')
    $multipart.Add($fileContent, 'package', [IO.Path]::GetFileName($ArchivePath))
    $multipart.Add([Net.Http.StringContent]::new($Activate.IsPresent.ToString().ToLowerInvariant()), 'activate')
    $multipart.Add([Net.Http.StringContent]::new($ActivationConfirmation), 'confirmation')

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
