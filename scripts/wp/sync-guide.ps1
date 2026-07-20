[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [string]$GuideId,

    [string]$SiteUrl = 'https://tra-vel.co.il',

    [ValidateSet('draft', 'publish')]
    [string]$Status = 'draft',

    [string]$CredentialPath = "$env:USERPROFILE\Documents\.codex-secrets\wordpress-app-passwords\tra-vel.co.il.credential.xml",

    [switch]$Apply,

    [string]$ProductionConfirmation = '',

    [switch]$ContractTest
)

$ErrorActionPreference = 'Stop'
$SiteUrl = $SiteUrl.TrimEnd('/')
$RepoRoot = (Resolve-Path (Join-Path (Join-Path $PSScriptRoot '..') '..')).Path
$GuideDirectory = Join-Path (Join-Path $RepoRoot 'content') 'guides'
$PacketPath = Join-Path $GuideDirectory "$GuideId.sources.json"
$ValidatorPath = Join-Path (Join-Path (Join-Path $RepoRoot 'scripts') 'ci') 'validate-guide-packets.mjs'

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

function Get-CanonicalGuideRoute {
    param([Parameter(Mandatory)][string]$CanonicalPath)

    $match = [regex]::Match(
        $CanonicalPath,
        '\A/destinations/(?<hub>[a-z0-9-]+)(?:/(?<child>[a-z0-9-]+))?/\z',
        [Text.RegularExpressions.RegexOptions]::CultureInvariant
    )
    if (-not $match.Success) {
        throw 'canonicalPath must be /destinations/{hub}/ or /destinations/{hub}/{child}/ with a trailing slash.'
    }

    $hub = $match.Groups['hub'].Value
    $child = $match.Groups['child'].Value
    $ancestorSlugs = @('destinations')
    $finalSlug = $hub
    if ($child) {
        $ancestorSlugs += $hub
        $finalSlug = $child
    }

    return [pscustomobject]@{
        CanonicalPath = $CanonicalPath
        HubSlug = $hub
        ChildSlug = $child
        FinalSlug = $finalSlug
        AncestorSlugs = $ancestorSlugs
    }
}

function Assert-GuidePacketStatus {
    param(
        [Parameter(Mandatory)][string]$PacketStatus,
        [Parameter(Mandatory)][ValidateSet('draft', 'publish')][string]$RequestedStatus,
        [Parameter(Mandatory)][bool]$ContentExists
    )

    if (-not $ContentExists) {
        throw 'The guide packet must reference existing content before any draft or publish sync.'
    }
    if ($RequestedStatus -eq 'publish' -and $PacketStatus -ne 'publish-ready') {
        throw "Publishing requires packet status publish-ready; current status is $PacketStatus."
    }
    if ($RequestedStatus -eq 'draft' -and $PacketStatus -notin @('source-ready', 'editorial-review', 'publish-ready')) {
        throw "Draft synchronization requires source-ready, editorial-review, or publish-ready packet status; current status is $PacketStatus."
    }

    return $true
}

function Resolve-GuideAncestorChain {
    param(
        [Parameter(Mandatory)]$Route,
        [Parameter(Mandatory)][scriptblock]$Lookup,
        [switch]$RequirePublished
    )

    $resolved = @()
    $expectedParentId = 0
    foreach ($ancestorSlug in @($Route.AncestorSlugs)) {
        $matches = @(& $Lookup $ancestorSlug $expectedParentId)
        $exactMatches = @($matches | Where-Object {
            ([string]$_.slug -ceq $ancestorSlug) -and ([int]$_.parent -eq $expectedParentId)
        })
        if ($matches.Count -ne $exactMatches.Count) {
            throw "WordPress returned a conflicting ancestor match for slug '$ancestorSlug' below parent $expectedParentId."
        }
        if ($exactMatches.Count -ne 1) {
            throw "Expected one WordPress ancestor for slug '$ancestorSlug' below parent $expectedParentId; found $($exactMatches.Count)."
        }

        $pageId = 0
        if (-not [int]::TryParse([string]$exactMatches[0].id, [ref]$pageId) -or $pageId -le 0) {
            throw "WordPress ancestor '$ancestorSlug' returned an invalid page ID."
        }
        if ($RequirePublished -and [string]$exactMatches[0].status -cne 'publish') {
            throw "Publishing requires ancestor '$ancestorSlug' to already be published."
        }

        $resolved += [pscustomobject]@{
            id = $pageId
            slug = [string]$exactMatches[0].slug
            parent = [int]$exactMatches[0].parent
            status = [string]$exactMatches[0].status
        }
        $expectedParentId = $pageId
    }

    return [pscustomobject]@{
        Pages = $resolved
        FinalParentId = $expectedParentId
    }
}

function Find-ExactGuideTarget {
    param(
        [Parameter(Mandatory)][string]$Slug,
        [Parameter(Mandatory)][int]$ParentId,
        [Parameter(Mandatory)][AllowEmptyCollection()][object[]]$Candidates
    )

    $exactMatches = @($Candidates | Where-Object {
        ([string]$_.slug -ceq $Slug) -and ([int]$_.parent -eq $ParentId)
    })
    if ($Candidates.Count -ne $exactMatches.Count) {
        throw "WordPress returned a conflicting guide match for slug '$Slug' below parent $ParentId."
    }
    if ($exactMatches.Count -gt 1) {
        throw "Expected at most one WordPress guide for slug '$Slug' below parent $ParentId; found $($exactMatches.Count)."
    }
    if (-not $exactMatches.Count) {
        return $null
    }

    return $exactMatches[0]
}

function Assert-GuideResponseIdentity {
    param(
        [Parameter(Mandatory)]$Result,
        [Parameter(Mandatory)][string]$ExpectedSlug,
        [Parameter(Mandatory)][int]$ExpectedParentId,
        [Parameter(Mandatory)][string]$ExpectedStatus,
        [Parameter(Mandatory)][string]$ExpectedSiteUrl,
        [Parameter(Mandatory)][string]$ExpectedCanonicalLink,
        [int]$ExpectedId = 0
    )

    $resultId = 0
    if (-not [int]::TryParse([string]$Result.id, [ref]$resultId) -or $resultId -le 0) {
        throw 'WordPress returned an invalid page ID.'
    }
    if ($ExpectedId -gt 0 -and $resultId -ne $ExpectedId) {
        throw "WordPress page ID drift detected. Expected $ExpectedId; received $resultId."
    }
    if ([string]$Result.slug -cne $ExpectedSlug) {
        throw "WordPress slug drift detected. Expected '$ExpectedSlug'; received '$([string]$Result.slug)'."
    }
    if ([int]$Result.parent -ne $ExpectedParentId) {
        throw "WordPress parent drift detected. Expected $ExpectedParentId; received $([int]$Result.parent)."
    }
    if ([string]$Result.template -cne 'page-destination.php') {
        throw "WordPress template mismatch. Expected page-destination.php; received '$([string]$Result.template)'."
    }
    if ([string]$Result.status -cne $ExpectedStatus) {
        throw "WordPress status mismatch. Expected '$ExpectedStatus'; received '$([string]$Result.status)'."
    }
    if ($ExpectedStatus -eq 'publish') {
        if ([string]$Result.link -cne $ExpectedCanonicalLink) {
            throw "WordPress permalink mismatch or redirect. Expected '$ExpectedCanonicalLink'; received '$([string]$Result.link)'."
        }
    }
    else {
        if ($null -eq $Result.PSObject.Properties['generated_slug']) {
            throw 'WordPress draft response is missing the edit-context generated_slug field.'
        }
        $permalinkTemplate = [string]$Result.permalink_template
        if (-not $permalinkTemplate.Contains('%pagename%')) {
            throw 'WordPress draft response is missing the official %pagename% permalink template.'
        }
        $sampleCanonicalLink = $permalinkTemplate.Replace('%pagename%', $ExpectedSlug)
        if ($sampleCanonicalLink -cne $ExpectedCanonicalLink) {
            throw "WordPress draft permalink template resolves to the wrong route. Expected '$ExpectedCanonicalLink'; received '$sampleCanonicalLink'."
        }
        $expectedPlainLink = "$ExpectedSiteUrl/?page_id=$resultId"
        if ([string]$Result.link -cne $expectedPlainLink) {
            throw "WordPress draft link must be the plain permalink for exact page ID $resultId; received '$([string]$Result.link)'."
        }
    }

    return $true
}

function Assert-PersistedGuide {
    param(
        [Parameter(Mandatory)]$Result,
        [Parameter(Mandatory)][string]$ExpectedSlug,
        [Parameter(Mandatory)][int]$ExpectedParentId,
        [Parameter(Mandatory)][string]$ExpectedStatus,
        [Parameter(Mandatory)][string]$ExpectedSiteUrl,
        [Parameter(Mandatory)][string]$ExpectedCanonicalLink,
        [Parameter(Mandatory)][int]$ExpectedId,
        [Parameter(Mandatory)][string]$ExpectedArticleHash,
        [Parameter(Mandatory)][int]$ExpectedSourceCount,
        [Parameter(Mandatory)][string]$ExpectedPublicationStatus
    )

    Assert-GuideResponseIdentity -Result $Result -ExpectedSlug $ExpectedSlug -ExpectedParentId $ExpectedParentId -ExpectedStatus $ExpectedStatus -ExpectedSiteUrl $ExpectedSiteUrl -ExpectedCanonicalLink $ExpectedCanonicalLink -ExpectedId $ExpectedId | Out-Null

    $remoteArticle = [string]$Result.content.raw
    $remoteHash = Get-TextSha256 -Value $remoteArticle
    if ($remoteHash -cne $ExpectedArticleHash) {
        throw 'Persisted WordPress content does not match the validated repository article SHA-256.'
    }

    $storedSourceJson = [string]$Result.meta._tra_vel_sources_json
    try {
        $storedSourceValue = ConvertFrom-Json -InputObject $storedSourceJson
        $storedSources = @($storedSourceValue | ForEach-Object { $_ })
    }
    catch {
        throw 'Persisted WordPress source metadata is not valid JSON.'
    }
    if ($storedSources.Count -ne $ExpectedSourceCount) {
        throw "Persisted WordPress source count mismatch. Expected $ExpectedSourceCount; received $($storedSources.Count)."
    }
    if ([string]$Result.meta._tra_vel_publication_status -cne $ExpectedPublicationStatus) {
        throw "Persisted WordPress publication-status metadata mismatch. Expected '$ExpectedPublicationStatus'; received '$([string]$Result.meta._tra_vel_publication_status)'."
    }

    return [pscustomobject]@{
        ArticleHash = $remoteHash
        SourceCount = $storedSources.Count
    }
}

function Assert-ContractCondition {
    param([Parameter(Mandatory)][bool]$Condition, [Parameter(Mandatory)][string]$Message)
    if (-not $Condition) {
        throw "Guide sync contract test failed: $Message"
    }
}

function Assert-ContractThrows {
    param([Parameter(Mandatory)][scriptblock]$Action, [Parameter(Mandatory)][string]$Message)

    $threw = $false
    try {
        & $Action | Out-Null
    }
    catch {
        $threw = $true
    }
    if (-not $threw) {
        throw "Guide sync contract test failed: $Message"
    }
}

function Invoke-GuideSyncContractTests {
    $topLevel = Get-CanonicalGuideRoute -CanonicalPath '/destinations/athens/'
    Assert-ContractCondition -Condition ($topLevel.FinalSlug -ceq 'athens' -and @($topLevel.AncestorSlugs).Count -eq 1) -Message 'top-level canonical chain is incorrect'
    $topLookup = {
        param($slug, $parentId)
        if ($slug -ceq 'destinations' -and $parentId -eq 0) {
            return [pscustomobject]@{ id = 10; slug = 'destinations'; parent = 0; status = 'publish' }
        }
        return @()
    }
    $topChain = Resolve-GuideAncestorChain -Route $topLevel -Lookup $topLookup -RequirePublished
    Assert-ContractCondition -Condition ($topChain.FinalParentId -eq 10) -Message 'top-level destination parent was not resolved exactly'

    $nested = Get-CanonicalGuideRoute -CanonicalPath '/destinations/thailand/bangkok/'
    Assert-ContractCondition -Condition ($nested.FinalSlug -ceq 'bangkok' -and (@($nested.AncestorSlugs) -join '/') -ceq 'destinations/thailand') -Message 'nested canonical chain is incorrect'
    $nestedLookup = {
        param($slug, $parentId)
        if ($slug -ceq 'destinations' -and $parentId -eq 0) {
            return [pscustomobject]@{ id = 10; slug = 'destinations'; parent = 0; status = 'publish' }
        }
        if ($slug -ceq 'thailand' -and $parentId -eq 10) {
            return [pscustomobject]@{ id = 20; slug = 'thailand'; parent = 10; status = 'publish' }
        }
        return @()
    }
    $nestedChain = Resolve-GuideAncestorChain -Route $nested -Lookup $nestedLookup -RequirePublished
    Assert-ContractCondition -Condition ($nestedChain.FinalParentId -eq 20) -Message 'nested destination parent was not resolved sequentially'
    Assert-ContractThrows -Action { Get-CanonicalGuideRoute -CanonicalPath '/destinations/thailand/bangkok/food/' } -Message 'an over-deep canonical guide path was accepted'

    $newTarget = Find-ExactGuideTarget -Slug 'bangkok' -ParentId 20 -Candidates @()
    Assert-ContractCondition -Condition ($null -eq $newTarget) -Message 'an empty exact target lookup did not preserve the create path'
    $ambiguousTargets = @(
        [pscustomobject]@{ id = 30; slug = 'bangkok'; parent = 20; status = 'draft' },
        [pscustomobject]@{ id = 31; slug = 'bangkok'; parent = 20; status = 'draft' }
    )
    Assert-ContractThrows -Action { Find-ExactGuideTarget -Slug 'bangkok' -ParentId 20 -Candidates $ambiguousTargets } -Message 'ambiguous final guide lookup was accepted'
    $conflictingTarget = @([pscustomobject]@{ id = 30; slug = 'bangkok'; parent = 99; status = 'draft' })
    Assert-ContractThrows -Action { Find-ExactGuideTarget -Slug 'bangkok' -ParentId 20 -Candidates $conflictingTarget } -Message 'final guide lookup with the wrong parent was accepted'

    $ambiguousLookup = {
        param($slug, $parentId)
        if ($slug -ceq 'destinations') {
            return [pscustomobject]@{ id = 10; slug = 'destinations'; parent = 0; status = 'publish' }
        }
        return @(
            [pscustomobject]@{ id = 20; slug = 'thailand'; parent = 10; status = 'publish' },
            [pscustomobject]@{ id = 21; slug = 'thailand'; parent = 10; status = 'publish' }
        )
    }
    Assert-ContractThrows -Action { Resolve-GuideAncestorChain -Route $nested -Lookup $ambiguousLookup } -Message 'ambiguous parent lookup was accepted'

    $missingAncestorLookup = {
        param($slug, $parentId)
        if ($slug -ceq 'destinations') {
            return [pscustomobject]@{ id = 10; slug = 'destinations'; parent = 0; status = 'publish' }
        }
        return @()
    }
    Assert-ContractThrows -Action { Resolve-GuideAncestorChain -Route $nested -Lookup $missingAncestorLookup } -Message 'missing ancestor lookup was accepted'

    $wrongParentLookup = {
        param($slug, $parentId)
        if ($slug -ceq 'destinations') {
            return [pscustomobject]@{ id = 10; slug = 'destinations'; parent = 0; status = 'publish' }
        }
        return [pscustomobject]@{ id = 20; slug = 'thailand'; parent = 99; status = 'publish' }
    }
    Assert-ContractThrows -Action { Resolve-GuideAncestorChain -Route $nested -Lookup $wrongParentLookup } -Message 'wrong parent ID was accepted'

    $draftAncestorLookup = {
        param($slug, $parentId)
        if ($slug -ceq 'destinations') {
            return [pscustomobject]@{ id = 10; slug = 'destinations'; parent = 0; status = 'publish' }
        }
        return [pscustomobject]@{ id = 20; slug = 'thailand'; parent = 10; status = 'draft' }
    }
    Assert-ContractThrows -Action { Resolve-GuideAncestorChain -Route $nested -Lookup $draftAncestorLookup -RequirePublished } -Message 'publish accepted an unpublished ancestor'

    Assert-GuidePacketStatus -PacketStatus 'source-ready' -RequestedStatus 'draft' -ContentExists $true | Out-Null
    Assert-GuidePacketStatus -PacketStatus 'editorial-review' -RequestedStatus 'draft' -ContentExists $true | Out-Null
    Assert-ContractThrows -Action { Assert-GuidePacketStatus -PacketStatus 'source-ready' -RequestedStatus 'draft' -ContentExists $false } -Message 'source-ready draft synchronization without content was accepted'
    Assert-ContractThrows -Action { Assert-GuidePacketStatus -PacketStatus 'research' -RequestedStatus 'draft' -ContentExists $true } -Message 'wrong draft packet status was accepted'
    Assert-ContractThrows -Action { Assert-GuidePacketStatus -PacketStatus 'editorial-review' -RequestedStatus 'publish' -ContentExists $true } -Message 'publish-ready enforcement was bypassed'
    Assert-GuidePacketStatus -PacketStatus 'publish-ready' -RequestedStatus 'publish' -ContentExists $true | Out-Null

    $article = '<p>Validated guide content</p>'
    $articleHash = Get-TextSha256 -Value $article
    $sourceJson = ConvertTo-Json -InputObject @(
        [ordered]@{ id = 'source-1'; title = 'Source 1'; url = 'https://example.org/1'; checkedAt = '2026-07-18' },
        [ordered]@{ id = 'source-2'; title = 'Source 2'; url = 'https://example.org/2'; checkedAt = '2026-07-18' }
    ) -Depth 4 -Compress
    $persisted = [pscustomobject]@{
        id = 30
        slug = 'bangkok'
        parent = 20
        status = 'publish'
        template = 'page-destination.php'
        link = 'https://tra-vel.co.il/destinations/thailand/bangkok/'
        content = [pscustomobject]@{ raw = $article }
        meta = [pscustomobject]@{
            _tra_vel_sources_json = $sourceJson
            _tra_vel_publication_status = 'publish-ready'
        }
    }
    $publishExpectation = @{
        ExpectedSlug = 'bangkok'
        ExpectedParentId = 20
        ExpectedStatus = 'publish'
        ExpectedSiteUrl = 'https://tra-vel.co.il'
        ExpectedCanonicalLink = 'https://tra-vel.co.il/destinations/thailand/bangkok/'
        ExpectedId = 30
        ExpectedArticleHash = $articleHash
        ExpectedSourceCount = 2
        ExpectedPublicationStatus = 'publish-ready'
    }
    Assert-PersistedGuide -Result $persisted @publishExpectation | Out-Null

    $slugDrift = $persisted | ConvertTo-Json -Depth 10 | ConvertFrom-Json
    $slugDrift.slug = 'bangkok-2'
    Assert-ContractThrows -Action { Assert-PersistedGuide -Result $slugDrift @publishExpectation } -Message 'WordPress -2 slug drift was accepted'

    $wrongLink = $persisted | ConvertTo-Json -Depth 10 | ConvertFrom-Json
    $wrongLink.link = 'https://tra-vel.co.il/destinations/bangkok/'
    Assert-ContractThrows -Action { Assert-PersistedGuide -Result $wrongLink @publishExpectation } -Message 'wrong or redirected permalink was accepted'

    $wrongPublicationStatus = $persisted | ConvertTo-Json -Depth 10 | ConvertFrom-Json
    $wrongPublicationStatus.meta._tra_vel_publication_status = 'editorial-review'
    Assert-ContractThrows -Action { Assert-PersistedGuide -Result $wrongPublicationStatus @publishExpectation } -Message 'wrong persisted packet status was accepted'

    $wrongTemplate = $persisted | ConvertTo-Json -Depth 10 | ConvertFrom-Json
    $wrongTemplate.template = 'default'
    Assert-ContractThrows -Action { Assert-PersistedGuide -Result $wrongTemplate @publishExpectation } -Message 'wrong persisted page template was accepted'

    $wrongWordPressStatus = $persisted | ConvertTo-Json -Depth 10 | ConvertFrom-Json
    $wrongWordPressStatus.status = 'draft'
    Assert-ContractThrows -Action { Assert-PersistedGuide -Result $wrongWordPressStatus @publishExpectation } -Message 'wrong persisted WordPress status was accepted'

    $wrongHashExpectation = $publishExpectation.Clone()
    $wrongHashExpectation.ExpectedArticleHash = ('0' * 64)
    Assert-ContractThrows -Action { Assert-PersistedGuide -Result $persisted @wrongHashExpectation } -Message 'wrong persisted article SHA-256 was accepted'

    $wrongSourceExpectation = $publishExpectation.Clone()
    $wrongSourceExpectation.ExpectedSourceCount = 3
    Assert-ContractThrows -Action { Assert-PersistedGuide -Result $persisted @wrongSourceExpectation } -Message 'wrong persisted source count was accepted'

    $draftPersisted = [pscustomobject]@{
        id = 31
        slug = 'bangkok'
        generated_slug = 'title-derived-bangkok-guide'
        parent = 20
        status = 'draft'
        template = 'page-destination.php'
        link = 'https://tra-vel.co.il/?page_id=31'
        permalink_template = 'https://tra-vel.co.il/destinations/thailand/%pagename%/'
        content = [pscustomobject]@{ raw = $article }
        meta = [pscustomobject]@{
            _tra_vel_sources_json = $sourceJson
            _tra_vel_publication_status = 'editorial-review'
        }
    }
    $draftExpectation = @{
        ExpectedSlug = 'bangkok'
        ExpectedParentId = 20
        ExpectedStatus = 'draft'
        ExpectedSiteUrl = 'https://tra-vel.co.il'
        ExpectedCanonicalLink = 'https://tra-vel.co.il/destinations/thailand/bangkok/'
        ExpectedId = 31
        ExpectedArticleHash = $articleHash
        ExpectedSourceCount = 2
        ExpectedPublicationStatus = 'editorial-review'
    }
    Assert-PersistedGuide -Result $draftPersisted @draftExpectation | Out-Null

    $draftWrongId = $draftPersisted | ConvertTo-Json -Depth 10 | ConvertFrom-Json
    $draftWrongId.link = 'https://tra-vel.co.il/?page_id=32'
    Assert-ContractThrows -Action { Assert-PersistedGuide -Result $draftWrongId @draftExpectation } -Message 'draft plain permalink with the wrong page ID was accepted'

    $draftWrongResponseId = $draftPersisted | ConvertTo-Json -Depth 10 | ConvertFrom-Json
    $draftWrongResponseId.id = 32
    $draftWrongResponseId.link = 'https://tra-vel.co.il/?page_id=32'
    Assert-ContractThrows -Action { Assert-PersistedGuide -Result $draftWrongResponseId @draftExpectation } -Message 'draft response with the wrong page ID was accepted'

    $draftWrongParent = $draftPersisted | ConvertTo-Json -Depth 10 | ConvertFrom-Json
    $draftWrongParent.parent = 99
    Assert-ContractThrows -Action { Assert-PersistedGuide -Result $draftWrongParent @draftExpectation } -Message 'draft response with the wrong parent was accepted'

    $draftWrongSlug = $draftPersisted | ConvertTo-Json -Depth 10 | ConvertFrom-Json
    $draftWrongSlug.slug = 'bangkok-2'
    Assert-ContractThrows -Action { Assert-PersistedGuide -Result $draftWrongSlug @draftExpectation } -Message 'draft response with slug drift was accepted'

    $draftWrongPrettyLink = $draftPersisted | ConvertTo-Json -Depth 10 | ConvertFrom-Json
    $draftWrongPrettyLink.link = 'https://tra-vel.co.il/destinations/bangkok/'
    Assert-ContractThrows -Action { Assert-PersistedGuide -Result $draftWrongPrettyLink @draftExpectation } -Message 'draft response with a wrong pretty path was accepted'

    $draftWrongTemplate = $draftPersisted | ConvertTo-Json -Depth 10 | ConvertFrom-Json
    $draftWrongTemplate.permalink_template = 'https://tra-vel.co.il/destinations/%pagename%/'
    Assert-ContractThrows -Action { Assert-PersistedGuide -Result $draftWrongTemplate @draftExpectation } -Message 'draft permalink template resolving to the wrong canonical route was accepted'

    Write-Output 'Tra-Vel guide sync contract validation passed (top-level, nested, ambiguity, parent, status, slug and permalink boundaries).'
}

if ($ContractTest) {
    Invoke-GuideSyncContractTests
    return
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

$ContentPath = Join-Path $RepoRoot ([string]$packet.contentPath)
$contentExists = Test-Path -LiteralPath $ContentPath
Assert-GuidePacketStatus -PacketStatus ([string]$packet.status) -RequestedStatus $Status -ContentExists $contentExists | Out-Null
if (-not $contentExists) {
    throw "Guide content not found: $ContentPath"
}

$route = Get-CanonicalGuideRoute -CanonicalPath ([string]$packet.canonicalPath)

if ($Apply -and $ProductionConfirmation -ne 'SYNC TRA-VEL GUIDE') {
    throw 'A production WordPress write requires -ProductionConfirmation "SYNC TRA-VEL GUIDE".'
}

$article = Get-Content -Raw -Encoding UTF8 -LiteralPath $ContentPath
$articleHash = Get-TextSha256 -Value $article
$sourceRecords = @($packet.sources | ForEach-Object {
    [ordered]@{
        id = $_.id
        title = $_.title
        url = $_.url
        publisher = $_.publisher
        checkedAt = $_.checkedAt
    }
})
if ($sourceRecords.Count -gt 80) {
    throw "Guide packet contains $($sourceRecords.Count) sources, above the registered WordPress source-meta capacity of 80."
}
$sourceMeta = ConvertTo-Json -InputObject $sourceRecords -Depth 6 -Compress

if (-not $Apply) {
    [pscustomobject]@{
        Action = 'dry-run'
        Guide = $GuideId
        Slug = $route.FinalSlug
        ParentSlug = @($route.AncestorSlugs)[-1]
        AncestorSlugs = @($route.AncestorSlugs) -join '/'
        CanonicalPath = $route.CanonicalPath
        Status = $Status
        WordPressStatus = $Status
        PacketStatus = [string]$packet.status
        Words = ([regex]::Matches(($article -replace '<[^>]+>', ' '), "[\p{L}\p{N}][\p{L}\p{N}\u05be'’-]*")).Count
        Sources = $sourceRecords.Count
        Sha256 = $articleHash
    }
    return
}

if (-not (Test-Path -LiteralPath $CredentialPath)) {
    throw "Credential file not found: $CredentialPath"
}

$credential = $null
$password = $null
$pair = $null
$encodedPair = $null
$headers = $null
try {
    $credential = Import-Clixml -LiteralPath $CredentialPath
    $password = $credential.GetNetworkCredential().Password -replace '\s', ''
    $pair = "$($credential.UserName):$password"
    $encodedPair = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($pair))
    $headers = @{ Authorization = "Basic $encodedPair" }

    # Windows PowerShell 5.1 Invoke-RestMethod can hand back JSON array responses
    # nested one level deep (a single-element or empty inner array), which breaks
    # strict count-based conflict guards. Flatten to genuine page records only.
    function ConvertTo-PageRecordArray {
        param($Response)
        $records = @()
        foreach ($item in @($Response)) {
            if ($null -eq $item) { continue }
            if ($item -is [System.Collections.IEnumerable] -and $item -isnot [string] -and $null -eq $item.PSObject.Properties['id']) {
                foreach ($nested in $item) {
                    if ($null -ne $nested -and $null -ne $nested.PSObject.Properties['id']) { $records += $nested }
                }
                continue
            }
            if ($null -ne $item.PSObject.Properties['id']) { $records += $item }
        }
        return ,$records
    }

    $lookupPage = {
        param($lookupSlug, $lookupParentId)
        $encodedSlug = [Uri]::EscapeDataString([string]$lookupSlug)
        $lookupUri = "$SiteUrl/wp-json/wp/v2/pages?slug=$encodedSlug&parent=$lookupParentId&status=any&context=edit&_fields=id,slug,status,parent"
        return ConvertTo-PageRecordArray (Invoke-RestMethod -Uri $lookupUri -Headers $headers -Method Get -MaximumRedirection 0 -TimeoutSec 30)
    }

    $ancestorChain = Resolve-GuideAncestorChain -Route $route -Lookup $lookupPage -RequirePublished:($Status -eq 'publish')
    $parentId = [int]$ancestorChain.FinalParentId
    $encodedFinalSlug = [Uri]::EscapeDataString([string]$route.FinalSlug)
    $targetLookupUri = "$SiteUrl/wp-json/wp/v2/pages?slug=$encodedFinalSlug&parent=$parentId&status=any&context=edit&_fields=id,slug,status,parent"
    $targetMatches = ConvertTo-PageRecordArray (Invoke-RestMethod -Uri $targetLookupUri -Headers $headers -Method Get -MaximumRedirection 0 -TimeoutSec 30)
    $existing = Find-ExactGuideTarget -Slug $route.FinalSlug -ParentId $parentId -Candidates $targetMatches

    $body = [ordered]@{
        title = $packet.title
        slug = $route.FinalSlug
        parent = $parentId
        status = $Status
        template = 'page-destination.php'
        excerpt = $packet.excerpt
        content = [string]$article
        meta = [ordered]@{
            _tra_vel_primary_topic = $packet.primaryTopic
            _tra_vel_author = $packet.author
            _tra_vel_source_checked = $packet.checkedAt
            _tra_vel_reviewer = $packet.reviewer
            _tra_vel_review_method = $packet.reviewMethod
            _tra_vel_publication_status = [string]$packet.status
            _tra_vel_map_state = $packet.mapState
            _tra_vel_sources_json = $sourceMeta
            _tra_vel_flight_time = [string]$packet.flightTime
            _tra_vel_daily_budget = [string]$packet.dailyBudget
            _tra_vel_best_season = [string]$packet.bestSeason
            _tra_vel_best_for = [string]$packet.bestFor
        }
    }
    $json = ConvertTo-Json -InputObject $body -Depth 10 -Compress
    $writeUri = if ($null -ne $existing) { "$SiteUrl/wp-json/wp/v2/pages/$($existing.id)" } else { "$SiteUrl/wp-json/wp/v2/pages" }
    $result = Invoke-RestMethod -Uri $writeUri -Headers $headers -Method Post -ContentType 'application/json; charset=utf-8' -Body $json -MaximumRedirection 0 -TimeoutSec 120
    $expectedCanonicalLink = "$SiteUrl$($route.CanonicalPath)"
    $resultId = 0
    if (-not [int]::TryParse([string]$result.id, [ref]$resultId) -or $resultId -le 0) {
        throw 'WordPress write response returned an invalid page ID.'
    }
    if ($null -ne $existing -and $resultId -ne [int]$existing.id) {
        throw "WordPress update response changed page identity. Expected $([int]$existing.id); received $resultId."
    }

    $persistedUri = "$SiteUrl/wp-json/wp/v2/pages/$($resultId)?context=edit&_fields=id,slug,parent,status,template,link,permalink_template,generated_slug,content,meta"
    $persisted = Invoke-RestMethod -Uri $persistedUri -Headers $headers -Method Get -MaximumRedirection 0 -TimeoutSec 30
    $verification = Assert-PersistedGuide -Result $persisted -ExpectedSlug $route.FinalSlug -ExpectedParentId $parentId -ExpectedStatus $Status -ExpectedSiteUrl $SiteUrl -ExpectedCanonicalLink $expectedCanonicalLink -ExpectedId $resultId -ExpectedArticleHash $articleHash -ExpectedSourceCount $sourceRecords.Count -ExpectedPublicationStatus ([string]$packet.status)

    [pscustomobject]@{
        Action = if ($null -ne $existing) { 'updated' } else { 'created' }
        Id = $persisted.id
        Slug = $persisted.slug
        Parent = $persisted.parent
        CanonicalPath = $route.CanonicalPath
        Status = $persisted.status
        PacketStatus = [string]$persisted.meta._tra_vel_publication_status
        Words = ([regex]::Matches(([string]$persisted.content.raw -replace '<[^>]+>', ' '), "[\p{L}\p{N}][\p{L}\p{N}\u05be'’-]*")).Count
        Sources = $verification.SourceCount
        Sha256 = $verification.ArticleHash
    }
}
finally {
    Remove-Variable password, pair, encodedPair, headers, credential -ErrorAction SilentlyContinue
}
