[CmdletBinding()]
param(
    [string]$SiteUrl = 'https://tra-vel.co.il',
    [string]$CredentialPath = "$env:USERPROFILE\Documents\.codex-secrets\wordpress-app-passwords\tra-vel.co.il.credential.xml"
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $CredentialPath)) {
    throw "Credential file not found: $CredentialPath"
}

$credential = Import-Clixml -LiteralPath $CredentialPath
$password = $credential.GetNetworkCredential().Password -replace '\s', ''
$pair = "$($credential.UserName):$password"
$encodedPair = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($pair))
$headers = @{ Authorization = "Basic $encodedPair" }

try {
    $result = Invoke-RestMethod -Uri "${SiteUrl}/wp-json/wp/v2/users/me?context=edit" -Headers $headers -Method Get
    $result | Select-Object id, name, link
}
finally {
    Remove-Variable password, pair, encodedPair, headers, credential -ErrorAction SilentlyContinue
}
