[CmdletBinding()]
param(
    [string]$WordPressUser = 'travp6jq_admin',
    [string]$CredentialPath = "$env:USERPROFILE\Documents\.codex-secrets\wordpress-app-passwords\tra-vel.co.il.credential.xml"
)

$ErrorActionPreference = 'Stop'

$secretDirectory = Split-Path -Parent $CredentialPath
New-Item -ItemType Directory -Path $secretDirectory -Force | Out-Null

$secureAppPassword = Read-Host 'Paste the Tra-Vel WordPress application password' -AsSecureString
$credential = [System.Management.Automation.PSCredential]::new($WordPressUser, $secureAppPassword)
$credential | Export-Clixml -LiteralPath $CredentialPath

$identity = "$env:USERDOMAIN\$env:USERNAME"
& icacls.exe $CredentialPath /inheritance:r /grant:r "${identity}:(F)" | Out-Null

Remove-Variable secureAppPassword, credential
Write-Host "Encrypted credential saved at: $CredentialPath"
