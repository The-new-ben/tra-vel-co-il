[CmdletBinding()]
param(
    [string]$SiteUrl = 'https://tra-vel.co.il',
    [string]$CredentialPath = 'C:\Users\janana\Documents\.codex-secrets\wordpress-app-passwords\tra-vel.co.il.credential.xml',
    [string]$EnvironmentPath = 'C:\Users\janana\Documents\tra-vel-co-il\.env.local'
)

$ErrorActionPreference = 'Stop'

if (-not $SiteUrl.StartsWith('https://')) {
    throw 'SiteUrl must use HTTPS.'
}
if (-not (Test-Path -LiteralPath $CredentialPath)) {
    throw "Credential file not found: $CredentialPath"
}
if (-not (Test-Path -LiteralPath $EnvironmentPath)) {
    throw "Environment file not found: $EnvironmentPath"
}

$keyLine = Get-Content -LiteralPath $EnvironmentPath |
    Where-Object { $_ -match '^\s*OPENAI_API_KEY\s*=' } |
    Select-Object -First 1
if (-not $keyLine) {
    throw 'OPENAI_API_KEY is not present in the confirmed environment file.'
}

$apiKey = $keyLine.Substring($keyLine.IndexOf('=') + 1).Trim().Trim('"')
if ($apiKey.Length -lt 40 -or -not $apiKey.StartsWith('sk-')) {
    throw 'OPENAI_API_KEY does not have the expected project-key format.'
}

$credential = Import-Clixml -LiteralPath $CredentialPath
$passwordPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($credential.Password)
$plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPtr)
$client = $null
$request = $null
$content = $null

try {
    Add-Type -AssemblyName System.Net.Http
    $basic = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($credential.UserName + ':' + $plainPassword))
    $payload = @{
        api_key = $apiKey
        confirmation = 'STORE TRA-VEL OPENAI KEY'
    } | ConvertTo-Json -Compress

    $client = [Net.Http.HttpClient]::new()
    $client.Timeout = [TimeSpan]::FromSeconds(60)
    $client.DefaultRequestHeaders.Authorization = [Net.Http.Headers.AuthenticationHeaderValue]::new('Basic', $basic)
    $content = [Net.Http.StringContent]::new($payload, [Text.Encoding]::UTF8, 'application/json')
    $endpoint = $SiteUrl.TrimEnd('/') + '/wp-json/tra-vel-agent/v1/settings/credential'
    $response = $client.PostAsync($endpoint, $content).GetAwaiter().GetResult()
    $body = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    if (-not $response.IsSuccessStatusCode) {
        throw "Agent credential configuration failed with HTTP $([int]$response.StatusCode): $body"
    }
    $result = $body | ConvertFrom-Json
    if (-not $result.ok -or -not $result.credential.configured) {
        throw 'WordPress did not confirm encrypted Agent Core credential storage.'
    }

    [pscustomobject]@{
        Site = $SiteUrl
        Configured = [bool]$result.credential.configured
        Source = $result.credential.source
        Encryption = $result.credential.encryption
    }
}
finally {
    if ($content) { $content.Dispose() }
    if ($client) { $client.Dispose() }
    if ($passwordPtr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPtr) }
    $apiKey = $null
    $keyLine = $null
    $plainPassword = $null
    $basic = $null
    $payload = $null
}

