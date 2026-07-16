[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [string]$GuideId,

    [string]$SiteUrl = 'https://tra-vel.co.il',

    [ValidateSet('draft', 'publish')]
    [string]$Status = 'draft',

    [string]$CredentialPath = "$env:USERPROFILE\Documents\.codex-secrets\wordpress-app-passwords\tra-vel.co.il.credential.xml",

    [switch]$Apply,

    [string]$ProductionConfirmation = ''
)

$ErrorActionPreference = 'Stop'
$SiteUrl = $SiteUrl.TrimEnd('/')
$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$PacketPath = Join-Path $RepoRoot "content\guides\$GuideId.sources.json"
$ValidatorPath = Join-Path $RepoRoot 'scripts\ci\validate-guide-packets.mjs'

function Get-TextSha256 {
    param([Parameter(Mandatory)][string]$Value)
    $sha = [Security.Cryptography.SHA256]::Create()
    try {
        $bytes = [Text.Encoding]::UTF8.GetBytes($Value)
        return ([BitConverter]::ToString($sha.ComputeHash($bytes))).Replace('-', '').ToLowerInvariant()
    }
    finally {
        $sha.Dispose()
    }
}

if (-not (Test-Path -LiteralPath $PacketPath)) {
    throw "Guide packet not found: $PacketPath"
}

& node $ValidatorPath
if ($LASTEXITCODE -ne 0) {
    throw 'Guide validation failed. WordPress was not changed.'
}

$packet = Get-Content -Raw -Encoding UTF8 -LiteralPath $PacketPath | ConvertFrom-Json
if (-not $packet.contentPath) {
    throw 'The guide packet has no validated contentPath.'
}

$ContentPath = Join-Path $RepoRoot ($packet.contentPath -replace '/', '\')
if (-not (Test-Path -LiteralPath $ContentPath)) {
    throw "Guide content not found: $ContentPath"
}

if ($Status -eq 'publish' -and $packet.status -ne 'publish-ready') {
    throw "Publishing requires packet status publish-ready; current status is $($packet.status)."
}

if ($Apply -and $ProductionConfirmation -ne 'SYNC TRA-VEL GUIDE') {
    throw 'A production WordPress write requires -ProductionConfirmation "SYNC TRA-VEL GUIDE".'
}

if (-not (Test-Path -LiteralPath $CredentialPath)) {
    throw "Credential file not found: $CredentialPath"
}

$slug = ([string]$packet.canonicalPath).Trim('/')
$article = Get-Content -Raw -Encoding UTF8 -LiteralPath $ContentPath
$articleHash = Get-TextSha256 -Value $article
$sourceMeta = @($packet.sources | ForEach-Object {
    [ordered]@{
        id = $_.id
        title = $_.title
        url = $_.url
        publisher = $_.publisher
        checkedAt = $_.checkedAt
    }
}) | ConvertTo-Json -Depth 6 -Compress

if (-not $Apply) {
    [pscustomobject]@{
        Action = 'dry-run'
        Guide = $GuideId
        Slug = $slug
        Status = $Status
        Words = ([regex]::Matches(($article -replace '<[^>]+>', ' '), "[\p{L}\p{N}][\p{L}\p{N}\u05be'’-]*")).Count
        Sources = @($packet.sources).Count
        Sha256 = $articleHash
    }
    return
}

$credential = Import-Clixml -LiteralPath $CredentialPath
$password = $credential.GetNetworkCredential().Password -replace '\s', ''
$pair = "$($credential.UserName):$password"
$encodedPair = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($pair))
$headers = @{ Authorization = "Basic $encodedPair" }

try {
    $lookupUri = "$SiteUrl/wp-json/wp/v2/pages?slug=$slug&status=any&context=edit&_fields=id,slug,status"
    $existing = @(Invoke-RestMethod -Uri $lookupUri -Headers $headers -Method Get)
    $body = [ordered]@{
        title = $packet.title
        slug = $slug
        status = $Status
        template = 'page-destination.php'
        excerpt = $packet.excerpt
        content = [string]$article
        meta = [ordered]@{
            _tra_vel_primary_topic = $packet.primaryTopic
            _tra_vel_source_checked = $packet.checkedAt
            _tra_vel_reviewer = $packet.reviewer
            _tra_vel_review_method = $packet.reviewMethod
            _tra_vel_map_state = $packet.mapState
            _tra_vel_sources_json = $sourceMeta
        }
    }
    $json = ConvertTo-Json -InputObject $body -Depth 10 -Compress
    $uri = if ($existing.Count) { "$SiteUrl/wp-json/wp/v2/pages/$($existing[0].id)" } else { "$SiteUrl/wp-json/wp/v2/pages" }
    $result = Invoke-RestMethod -Uri $uri -Headers $headers -Method Post -ContentType 'application/json; charset=utf-8' -Body $json -TimeoutSec 120
    $remoteArticle = [string]$result.content.raw
    $remoteHash = Get-TextSha256 -Value $remoteArticle
    if ($remoteHash -ne $articleHash) {
        throw 'WordPress returned content that does not match the validated repository article.'
    }
    [pscustomobject]@{
        Action = if ($existing.Count) { 'updated' } else { 'created' }
        Id = $result.id
        Slug = $result.slug
        Status = $result.status
        Words = ([regex]::Matches(($remoteArticle -replace '<[^>]+>', ' '), "[\p{L}\p{N}][\p{L}\p{N}\u05be'’-]*")).Count
        Sources = @($packet.sources).Count
        Sha256 = $remoteHash
    }
}
finally {
    Remove-Variable password, pair, encodedPair, headers, credential -ErrorAction SilentlyContinue
}
