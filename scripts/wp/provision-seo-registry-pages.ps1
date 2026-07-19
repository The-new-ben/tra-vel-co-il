[CmdletBinding()]
param(
    [string]$SiteUrl = '',

    [ValidateSet('staging', 'production')]
    [string]$Environment = 'staging',

    [ValidateSet('draft', 'publish')]
    [string]$Status = 'draft',

    [string]$RegistryPath = '',

    [string[]]$OwnerId = @(),

    [string]$CredentialPath = "$env:USERPROFILE\Documents\.codex-secrets\wordpress-app-passwords\tra-vel.co.il.credential.xml",

    [string]$AllowedStagingHost = '',

    [string]$StagingConfirmation = '',

    [ValidateRange(5, 120)]
    [int]$RequestTimeoutSeconds = 30,

    [switch]$Apply,

    [switch]$EditorialReady,

    [switch]$ConversionReady,

    [string]$PromotionConfirmation = '',

    [string]$ProductionConfirmation = '',

    [switch]$ContractTest
)

$ErrorActionPreference = 'Stop'
$RepoRoot = (Resolve-Path (Join-Path (Join-Path $PSScriptRoot '..') '..')).Path
$DefaultProductionCredentialPath = "$env:USERPROFILE\Documents\.codex-secrets\wordpress-app-passwords\tra-vel.co.il.credential.xml"
if (-not $RegistryPath) {
    $RegistryPath = Join-Path (Join-Path (Join-Path $RepoRoot 'content') 'seo') 'content-opportunity-registry.json'
}

function Assert-ContractCondition {
    param([Parameter(Mandatory)][bool]$Condition, [Parameter(Mandatory)][string]$Message)
    if (-not $Condition) {
        throw "SEO opportunity provision contract failed: $Message"
    }
}

function Assert-ContractThrows {
    param([Parameter(Mandatory)][scriptblock]$Action, [Parameter(Mandatory)][string]$Message)
    $threw = $false
    try { & $Action | Out-Null } catch { $threw = $true }
    Assert-ContractCondition -Condition $threw -Message $Message
}

function Test-RegistryEntryEligible {
    param([Parameter(Mandatory)]$Entry)
    return ([string]$Entry.pageType -in @('decision-guide', 'transactional-cluster')) -and
        ([string]$Entry.status -in @('live', 'content-ready'))
}

function Get-CanonicalOpportunityRoute {
    param([Parameter(Mandatory)]$Entry)

    $canonical = [string]$Entry.canonicalPath
    if ([string]$Entry.pageType -ceq 'decision-guide') {
        $match = [regex]::Match($canonical, '\A/guides/(?<cluster>[a-z0-9-]+)/(?<slug>[a-z0-9-]+)/\z')
        if (-not $match.Success) { throw "Decision guide canonicalPath is invalid: $canonical" }
        $cluster = $match.Groups['cluster'].Value
        if ([string]$Entry.cluster -cne $cluster -or [string]$Entry.parentPath -cne "/destinations/$cluster/") {
            throw "Decision guide registry hierarchy is inconsistent for $canonical."
        }
        return [pscustomobject]@{
            CanonicalPath = $canonical
            PageType = 'decision-guide'
            FinalSlug = $match.Groups['slug'].Value
            SemanticParentPath = [string]$Entry.parentPath
            Structural = @(
                [pscustomobject]@{ Slug = 'guides'; Title = 'guides'; Template = 'page-directory.php'; DraftOnly = $false },
                [pscustomobject]@{ Slug = $cluster; Title = $cluster; Template = ''; DraftOnly = $true }
            )
        }
    }

    if ([string]$Entry.pageType -ceq 'transactional-cluster') {
        $match = [regex]::Match($canonical, '\A/(?<vertical>flights|hotels|packages)/(?<slug>[a-z0-9-]+)/\z')
        if (-not $match.Success) { throw "Transactional canonicalPath is invalid: $canonical" }
        $vertical = $match.Groups['vertical'].Value
        if ([string]$Entry.parentPath -cne "/$vertical/") {
            throw "Transactional parentPath must be /$vertical/ for $canonical."
        }
        return [pscustomobject]@{
            CanonicalPath = $canonical
            PageType = 'transactional-cluster'
            FinalSlug = $match.Groups['slug'].Value
            SemanticParentPath = [string]$Entry.parentPath
            Structural = @(
                [pscustomobject]@{ Slug = $vertical; Title = $vertical; Template = 'page-experience.php'; DraftOnly = $false }
            )
        }
    }

    throw "Unsupported registry page type: $([string]$Entry.pageType)"
}

function Assert-RegistryContract {
    param([Parameter(Mandatory)]$Registry)

    $rootKeys = @($Registry.PSObject.Properties.Name)
    $allowedRootKeys = @('schemaVersion', 'updated', 'locale', 'evidenceBoundary', 'entries')
    if (@(Compare-Object -ReferenceObject $allowedRootKeys -DifferenceObject $rootKeys).Count) { throw 'Registry root fields do not match the authoritative schema.' }
    if ([int]$Registry.schemaVersion -ne 1 -or [string]$Registry.locale -cne 'he-IL') { throw 'Registry schemaVersion or locale is invalid.' }
    $updated = [DateTime]::MinValue
    if (-not [DateTime]::TryParseExact([string]$Registry.updated, 'yyyy-MM-dd', [Globalization.CultureInfo]::InvariantCulture, [Globalization.DateTimeStyles]::AssumeUniversal, [ref]$updated) -or $updated.Date -gt [DateTime]::UtcNow.Date.AddDays(1)) {
        throw 'Registry updated must be a non-future ISO date.'
    }
    if ([string]$Registry.evidenceBoundary -notmatch 'not search-volume claims') { throw 'Registry evidenceBoundary is missing its search-volume boundary.' }
    $entries = @($Registry.entries)
    if ($entries.Count -lt 30) { throw 'Registry must contain at least 30 owned intents.' }

    $allowedKeys = @('id', 'canonicalPath', 'pageType', 'primaryIntent', 'cluster', 'parentPath', 'mapState', 'status', 'conversionAction', 'monetization')
    $allowedTypes = @('commercial-hub', 'planning-tool', 'audience-hub', 'destination-hub', 'destination-support', 'transactional-cluster', 'decision-guide')
    $allowedStatuses = @('live', 'content-ready', 'backlog')
    $allowedMapStates = @('budapest', 'prague', 'vienna', 'athens', 'dubai', 'bangkok', 'tokyo', 'lisbon')
    $ids = @{}
    $paths = @{}
    $intents = @{}

    foreach ($entry in $entries) {
        if ($null -eq $entry -or @($entry.PSObject.Properties).Count -eq 0) { throw 'Every registry entry must be an object.' }
        $entryKeys = @($entry.PSObject.Properties.Name)
        if (@(Compare-Object -ReferenceObject $allowedKeys -DifferenceObject $entryKeys).Count) { throw 'Registry entry fields do not match the authoritative schema.' }
        $id = [string]$entry.id
        $canonical = [string]$entry.canonicalPath
        $parent = [string]$entry.parentPath
        $intent = ([string]$entry.primaryIntent).Trim()
        $normalizedIntent = $intent.ToLowerInvariant()
        if ($entry.id -isnot [string] -or $id -cnotmatch '\A[a-z0-9-]+\z' -or $ids.ContainsKey($id)) { throw "Registry owner ID is invalid or duplicated: $id" }
        if ($entry.canonicalPath -isnot [string] -or $canonical -cnotmatch '\A/(?:[a-z0-9-]+/)+\z' -or $paths.ContainsKey($canonical)) { throw "Registry canonical path is invalid or duplicated: $canonical" }
        if ($entry.parentPath -isnot [string] -or ($parent -cne '/' -and $parent -cnotmatch '\A/(?:[a-z0-9-]+/)+\z')) { throw "Registry parent path is invalid for $id." }
        if ($entry.pageType -isnot [string] -or [string]$entry.pageType -notin $allowedTypes) { throw "Registry page type is invalid for $id." }
        if ($entry.status -isnot [string] -or [string]$entry.status -notin $allowedStatuses) { throw "Registry status is invalid for $id." }
        if ($entry.cluster -isnot [string] -or [string]$entry.cluster -cnotmatch '\A[a-z0-9-]+\z') { throw "Registry cluster is invalid for $id." }
        if ($entry.primaryIntent -isnot [string] -or $intent.Length -lt 8 -or $intent -notmatch '[\u0590-\u05ff]' -or $intents.ContainsKey($normalizedIntent)) { throw "Registry primary intent is invalid or duplicated for $id." }
        if ($entry.conversionAction -isnot [string] -or ([string]$entry.conversionAction).Trim().Length -lt 12 -or [string]$entry.conversionAction -notmatch '[\u0590-\u05ff]') { throw "Registry conversion action is invalid for $id." }
        if ($null -ne $entry.mapState -and ($entry.mapState -isnot [string] -or [string]$entry.mapState -notin $allowedMapStates)) { throw "Registry map state is invalid for $id." }
        if ([string]$entry.pageType -in @('decision-guide', 'transactional-cluster') -and [string]$entry.status -in @('live', 'content-ready') -and ($entry.mapState -isnot [string] -or [string]$entry.mapState -notin $allowedMapStates)) {
            throw "Public opportunity owner requires a supported map state: $id."
        }
        if ($entry.monetization -isnot [System.Array] -or @($entry.monetization).Count -lt 1) { throw "Registry monetization must be a non-empty ordered array for $id." }
        $products = @{}
        foreach ($product in @($entry.monetization)) {
            if ($product -isnot [string] -or [string]$product -cnotmatch '\A[a-z0-9-]+\z' -or $products.ContainsKey([string]$product)) { throw "Registry monetization is invalid or duplicated for $id." }
            $products[[string]$product] = $true
        }
        if ([string]$entry.pageType -ceq 'transactional-cluster') {
            $match = [regex]::Match($canonical, '\A/(?<vertical>flights|hotels|packages)/[a-z0-9-]+/\z')
            if (-not $match.Success -or $parent -cne "/$($match.Groups['vertical'].Value)/") { throw "Transactional hierarchy is invalid for $id." }
        }
        if ([string]$entry.pageType -ceq 'decision-guide') {
            $match = [regex]::Match($canonical, '\A/guides/(?<cluster>[a-z0-9-]+)/[a-z0-9-]+/\z')
            if (-not $match.Success -or [string]$entry.cluster -cne $match.Groups['cluster'].Value -or $parent -cne "/destinations/$($match.Groups['cluster'].Value)/") { throw "Decision hierarchy is invalid for $id." }
        }
        $ids[$id] = $entry
        $paths[$canonical] = $entry
        $intents[$normalizedIntent] = $true
    }

    foreach ($entry in $entries) {
        $parentPath = [string]$entry.parentPath
        if ($parentPath -cne '/' -and -not $paths.ContainsKey($parentPath)) { throw "Registry semantic parent is missing for $([string]$entry.id)." }
        if ([string]$entry.pageType -ceq 'decision-guide') {
            $parent = $paths[$parentPath]
            if ([string]$parent.pageType -cne 'destination-hub' -or [string]$parent.cluster -cne [string]$entry.cluster) { throw "Decision semantic parent type or cluster is invalid for $([string]$entry.id)." }
            if ([string]$entry.status -in @('live', 'content-ready') -and [string]$parent.status -notin @('live', 'content-ready')) { throw "Public decision owner has an unready semantic parent: $([string]$entry.id)." }
        }
        if ([string]$entry.pageType -ceq 'transactional-cluster') {
            $parent = $paths[$parentPath]
            if ([string]$parent.pageType -cne 'commercial-hub' -or [string]$parent.canonicalPath -cne $parentPath) { throw "Transactional registry parent is not the exact commercial hub for $([string]$entry.id)." }
            if ([string]$entry.status -in @('live', 'content-ready') -and [string]$parent.status -notin @('live', 'content-ready')) { throw "Public transaction owner has an unready commercial parent: $([string]$entry.id)." }
        }
    }
    return $true
}

function Get-VisibleContentMetrics {
    param([AllowEmptyString()][string]$Content)
    $plain = [regex]::Replace([string]$Content, '<[^>]+>', ' ')
    $plain = [Net.WebUtility]::HtmlDecode($plain)
    $words = [regex]::Matches($plain, "[\p{L}\p{N}][\p{L}\p{N}\u05BE'\u2019]*")
    $hebrewCount = @($words | Where-Object { $_.Value -match '[\u0590-\u05ff]' }).Count
    return [pscustomobject]@{
        WordCount = $words.Count
        HebrewRatio = $hebrewCount / [Math]::Max($words.Count, 1)
        H2Count = [regex]::Matches([string]$Content, '<h2\b', 'IgnoreCase').Count
        TableCount = [regex]::Matches([string]$Content, '<table\b', 'IgnoreCase').Count
    }
}

function Get-PageMetaValue {
    param([Parameter(Mandatory)]$Page, [Parameter(Mandatory)][string]$Key)
    if ($null -eq $Page.meta) { return $null }
    $property = $Page.meta.PSObject.Properties[$Key]
    if ($null -eq $property) { return $null }
    return $property.Value
}

function New-OpportunityReadinessMeta {
    param(
        [Parameter(Mandatory)]$Entry,
        [Parameter(Mandatory)][ValidateSet('unready', 'ready')][string]$State
    )

    $isReady = $State -ceq 'ready'
    return @{
        _tra_vel_seo_opportunity_id = [string]$Entry.id
        _tra_vel_seo_opportunity_ready = $isReady
        _tra_vel_seo_conversion_ready = ($isReady -and [string]$Entry.pageType -ceq 'transactional-cluster')
    }
}

function Assert-PersistedDisabledReadinessEvidence {
    param([Parameter(Mandatory)]$Page, [Parameter(Mandatory)]$Entry)
    if ([string](Get-PageMetaValue -Page $Page -Key '_tra_vel_seo_opportunity_id') -cne [string]$Entry.id) { throw 'Persisted unready owner metadata drifted.' }
    foreach ($key in @('_tra_vel_seo_opportunity_ready', '_tra_vel_seo_conversion_ready')) {
        $value = Get-PageMetaValue -Page $Page -Key $key
        if ($value -isnot [bool] -or $value -ne $false) { throw "Persisted $key must be exact boolean false before readiness can be enabled." }
    }
    return $true
}

function Assert-PersistedReadinessEvidence {
    param([Parameter(Mandatory)]$Page, [Parameter(Mandatory)]$Entry)
    if ([string](Get-PageMetaValue -Page $Page -Key '_tra_vel_seo_opportunity_id') -cne [string]$Entry.id) { throw 'Persisted published owner metadata drifted.' }
    $editorialReadyValue = Get-PageMetaValue -Page $Page -Key '_tra_vel_seo_opportunity_ready'
    if ($editorialReadyValue -isnot [bool] -or $editorialReadyValue -ne $true) { throw 'Persisted editorial readiness evidence is not exact boolean true.' }
    $expectedConversionReady = [string]$Entry.pageType -ceq 'transactional-cluster'
    $conversionReadyValue = Get-PageMetaValue -Page $Page -Key '_tra_vel_seo_conversion_ready'
    if ($conversionReadyValue -isnot [bool] -or $conversionReadyValue -ne $expectedConversionReady) { throw 'Persisted conversion readiness evidence is not the exact expected boolean.' }
    return $true
}

function Assert-FreshIsoDate {
    param([Parameter(Mandatory)][string]$Value, [Parameter(Mandatory)][string]$Label)
    $parsed = [DateTime]::MinValue
    if (-not [DateTime]::TryParseExact($Value, 'yyyy-MM-dd', [Globalization.CultureInfo]::InvariantCulture, [Globalization.DateTimeStyles]::AssumeUniversal, [ref]$parsed)) {
        throw "$Label must be a valid ISO date."
    }
    $today = [DateTime]::UtcNow.Date
    if ($parsed.Date -gt $today.AddDays(1) -or $parsed.Date -lt $today.AddYears(-1)) {
        throw "$Label must be no more than one year old and not in the future."
    }
    return $parsed.Date
}

function Assert-DecisionEvidence {
    param([Parameter(Mandatory)]$Page, [Parameter(Mandatory)][string]$ExpectedMapState)

    $metrics = Get-VisibleContentMetrics -Content ([string]$Page.content.raw)
    if ($metrics.WordCount -lt 5000) { throw "Decision guide has $($metrics.WordCount) visible words; 5000 are required." }
    if ($metrics.HebrewRatio -lt 0.75) { throw 'Decision guide must contain at least 75% Hebrew words.' }
    if ($metrics.H2Count -lt 12) { throw 'Decision guide requires at least 12 H2 sections.' }
    if ($metrics.TableCount -lt 3) { throw 'Decision guide requires at least three decision tables.' }

    foreach ($key in @('_tra_vel_primary_topic', '_tra_vel_author', '_tra_vel_reviewer', '_tra_vel_review_method')) {
        if ([string]::IsNullOrWhiteSpace([string](Get-PageMetaValue -Page $Page -Key $key))) {
            throw "Decision guide is missing $key."
        }
    }
    if ([string](Get-PageMetaValue -Page $Page -Key '_tra_vel_publication_status') -cne 'publish-ready') {
        throw 'Decision guide requires explicit _tra_vel_publication_status=publish-ready.'
    }
    if ([string](Get-PageMetaValue -Page $Page -Key '_tra_vel_map_state') -cne $ExpectedMapState) {
        throw "Decision guide map state does not match '$ExpectedMapState'."
    }
    $checked = [string](Get-PageMetaValue -Page $Page -Key '_tra_vel_source_checked')
    $checkedDate = Assert-FreshIsoDate -Value $checked -Label '_tra_vel_source_checked'

    try { $sources = @((ConvertFrom-Json -InputObject ([string](Get-PageMetaValue -Page $Page -Key '_tra_vel_sources_json')))) }
    catch { throw 'Decision guide source metadata is not valid JSON.' }
    if ($sources.Count -lt 10) { throw 'Decision guide requires at least ten valid source records.' }
    foreach ($source in $sources) {
        if ([string]::IsNullOrWhiteSpace([string]$source.title) -or [string]$source.url -notmatch '\Ahttps://') {
            throw 'Every decision-guide source requires a title and HTTPS URL.'
        }
        $sourceDate = Assert-FreshIsoDate -Value ([string]$source.checkedAt) -Label 'source checkedAt'
        if ($sourceDate -gt $checkedDate) {
            throw 'A source checkedAt date cannot be later than the aggregate review date.'
        }
    }
    return $metrics
}

function Assert-TransactionalEvidence {
    param([Parameter(Mandatory)]$Page, [Parameter(Mandatory)][string]$ExpectedMapState)
    $metrics = Get-VisibleContentMetrics -Content ([string]$Page.content.raw)
    if ($metrics.WordCount -lt 800) { throw "Transactional page has $($metrics.WordCount) visible words; 800 are required." }
    if ($metrics.HebrewRatio -lt 0.70) { throw 'Transactional page must contain at least 70% Hebrew words.' }
    if ($metrics.H2Count -lt 4) { throw 'Transactional page requires at least four H2 sections.' }
    if ($ExpectedMapState -notin @('budapest', 'prague', 'vienna', 'athens', 'dubai', 'bangkok', 'tokyo', 'lisbon')) {
        throw 'Transactional page requires a supported contextual Earth map state.'
    }
    return $metrics
}

function Assert-PromotionAuthorization {
    param([Parameter(Mandatory)]$Entry)
    if ($Status -ne 'publish') { return $true }
    if (-not (Test-RegistryEntryEligible -Entry $Entry)) {
        throw 'Only live or content-ready decision/transaction registry owners can be promoted.'
    }
    if (-not $EditorialReady -or $PromotionConfirmation -cne 'PROMOTE TRA-VEL SEO OPPORTUNITY') {
        throw 'Publishing requires -EditorialReady and -PromotionConfirmation "PROMOTE TRA-VEL SEO OPPORTUNITY".'
    }
    if ([string]$Entry.pageType -ceq 'transactional-cluster' -and -not $ConversionReady) {
        throw 'Publishing a transactional page requires -ConversionReady.'
    }
    return $true
}

function Assert-SiteEnvironmentBinding {
    param(
        [Parameter(Mandatory)][Uri]$SiteOrigin,
        [Parameter(Mandatory)][ValidateSet('staging', 'production')][string]$RequestedEnvironment,
        [AllowEmptyString()][string]$Confirmation = '',
        [AllowEmptyString()][string]$ExpectedStagingHost = '',
        [AllowEmptyString()][string]$StagingHostConfirmation = ''
    )
    $productionHosts = @('tra-vel.co.il', 'www.tra-vel.co.il')
    $siteHost = $SiteOrigin.Host.ToLowerInvariant()
    if ($SiteOrigin.Scheme -cne 'https' -or $SiteOrigin.AbsolutePath -ne '/') { throw 'SiteUrl must be an HTTPS origin without a path.' }
    if ($siteHost -in $productionHosts -and $RequestedEnvironment -cne 'production') {
        throw "Production host $($SiteOrigin.Host) requires -Environment production."
    }
    if ($RequestedEnvironment -ceq 'production' -and $siteHost -notin $productionHosts) {
        throw 'The production environment is restricted to tra-vel.co.il hosts.'
    }
    if ($RequestedEnvironment -ceq 'production' -and $Confirmation -cne 'PUBLISH TRA-VEL SEO OPPORTUNITIES') {
        throw 'Production writes require -ProductionConfirmation "PUBLISH TRA-VEL SEO OPPORTUNITIES".'
    }
    if ($RequestedEnvironment -ceq 'staging') {
        if ([string]::IsNullOrWhiteSpace($ExpectedStagingHost) -or $siteHost -cne $ExpectedStagingHost.Trim().ToLowerInvariant()) {
            throw 'Staging writes require an exact -AllowedStagingHost match.'
        }
        $requiredStagingConfirmation = "USE TRA-VEL SEO STAGING HOST $siteHost"
        if ($StagingHostConfirmation -cne $requiredStagingConfirmation) {
            throw "Staging writes require -StagingConfirmation `"$requiredStagingConfirmation`"."
        }
    }
    return $true
}

function Assert-CredentialEnvironmentBinding {
    param(
        [Parameter(Mandatory)][ValidateSet('staging', 'production')][string]$RequestedEnvironment,
        [Parameter(Mandatory)][string]$CredentialFile,
        [Parameter(Mandatory)][string]$DefaultProductionCredentialFile,
        [bool]$CredentialPathWasExplicit = $false
    )
    if ($RequestedEnvironment -ceq 'staging') {
        if (-not $CredentialPathWasExplicit) {
            throw 'Staging writes require an explicitly supplied -CredentialPath.'
        }
        $selected = [IO.Path]::GetFullPath($CredentialFile)
        $productionDefault = [IO.Path]::GetFullPath($DefaultProductionCredentialFile)
        if ([StringComparer]::OrdinalIgnoreCase.Equals($selected, $productionDefault)) {
            throw 'Staging writes require a non-default, staging-specific credential file.'
        }
    }
    return $true
}

function Invoke-ProvisionContractTests {
    $decision = [pscustomobject]@{ id = 'tokyo-airports'; canonicalPath = '/guides/tokyo/haneda-vs-narita/'; pageType = 'decision-guide'; cluster = 'tokyo'; parentPath = '/destinations/tokyo/'; mapState = 'tokyo'; status = 'content-ready' }
    $transaction = [pscustomobject]@{ id = 'bangkok-hotels'; canonicalPath = '/hotels/bangkok/'; pageType = 'transactional-cluster'; cluster = 'thailand'; parentPath = '/hotels/'; mapState = 'bangkok'; status = 'live' }
    $larnaca = [pscustomobject]@{ id = 'larnaca-packages'; canonicalPath = '/packages/larnaca/'; pageType = 'transactional-cluster'; cluster = 'cyprus'; parentPath = '/packages/'; mapState = $null; status = 'backlog' }
    $backlog = [pscustomobject]@{ id = 'draft-owner'; canonicalPath = '/flights/tokyo/'; pageType = 'transactional-cluster'; cluster = 'tokyo'; parentPath = '/flights/'; mapState = 'tokyo'; status = 'backlog' }

    $hebrewWord = -join ([char]0x05DE, [char]0x05D9, [char]0x05DC, [char]0x05D4)
    $registryEntries = @([pscustomobject]@{
        id = 'flights-hub'; canonicalPath = '/flights/'; pageType = 'commercial-hub'; primaryIntent = "$hebrewWord $hebrewWord 0"
        cluster = 'flights'; parentPath = '/'; mapState = $null; status = 'live'; conversionAction = "$hebrewWord $hebrewWord $hebrewWord 0"; monetization = @('flights')
    })
    foreach ($index in 1..29) {
        $registryEntries += [pscustomobject]@{
            id = "fixture-$index"; canonicalPath = "/flights/fixture-$index/"; pageType = 'transactional-cluster'; primaryIntent = "$hebrewWord $hebrewWord $index"
            cluster = 'fixture'; parentPath = '/flights/'; mapState = 'tokyo'; status = 'backlog'; conversionAction = "$hebrewWord $hebrewWord $hebrewWord $index"; monetization = @('flights')
        }
    }
    $registryFixture = [pscustomobject]@{
        schemaVersion = 1; updated = [DateTime]::UtcNow.ToString('yyyy-MM-dd'); locale = 'he-IL'
        evidenceBoundary = 'Priorities are not search-volume claims.'; entries = $registryEntries
    }
    Assert-RegistryContract -Registry $registryFixture | Out-Null
    $duplicatePathEntry = [pscustomobject]@{
        id = 'fixture-duplicate'; canonicalPath = '/flights/fixture-1/'; pageType = 'transactional-cluster'; primaryIntent = "$hebrewWord $hebrewWord duplicate"
        cluster = 'fixture'; parentPath = '/flights/'; mapState = 'tokyo'; status = 'backlog'; conversionAction = "$hebrewWord $hebrewWord $hebrewWord duplicate"; monetization = @('flights')
    }
    $duplicateRegistry = [pscustomobject]@{ schemaVersion = 1; updated = $registryFixture.updated; locale = 'he-IL'; evidenceBoundary = $registryFixture.evidenceBoundary; entries = @($registryEntries + $duplicatePathEntry) }
    Assert-ContractThrows -Action { Assert-RegistryContract -Registry $duplicateRegistry } -Message 'duplicate unselected canonical did not invalidate the whole registry'
    $invalidMonetization = $registryEntries[29] | Select-Object *
    $invalidMonetization.monetization = 'flights'
    $invalidRegistry = [pscustomobject]@{ schemaVersion = 1; updated = $registryFixture.updated; locale = 'he-IL'; evidenceBoundary = $registryFixture.evidenceBoundary; entries = @($registryEntries[0..28] + $invalidMonetization) }
    Assert-ContractThrows -Action { Assert-RegistryContract -Registry $invalidRegistry } -Message 'malformed unselected monetization did not invalidate the whole registry'
    $duplicateIntent = $registryEntries[29] | Select-Object *
    $duplicateIntent.primaryIntent = $registryEntries[1].primaryIntent
    $duplicateIntentRegistry = [pscustomobject]@{ schemaVersion = 1; updated = $registryFixture.updated; locale = 'he-IL'; evidenceBoundary = $registryFixture.evidenceBoundary; entries = @($registryEntries[0..28] + $duplicateIntent) }
    Assert-ContractThrows -Action { Assert-RegistryContract -Registry $duplicateIntentRegistry } -Message 'duplicate normalized intent did not invalidate the whole registry'
    $invalidParent = $registryEntries[29] | Select-Object *
    $invalidParent.parentPath = '/hotels/'
    $invalidParentRegistry = [pscustomobject]@{ schemaVersion = 1; updated = $registryFixture.updated; locale = 'he-IL'; evidenceBoundary = $registryFixture.evidenceBoundary; entries = @($registryEntries[0..28] + $invalidParent) }
    Assert-ContractThrows -Action { Assert-RegistryContract -Registry $invalidParentRegistry } -Message 'missing unselected parent did not invalidate the whole registry'
    $invalidMap = $registryEntries[29] | Select-Object *
    $invalidMap.mapState = 'unknown-map'
    $invalidMapRegistry = [pscustomobject]@{ schemaVersion = 1; updated = $registryFixture.updated; locale = 'he-IL'; evidenceBoundary = $registryFixture.evidenceBoundary; entries = @($registryEntries[0..28] + $invalidMap) }
    Assert-ContractThrows -Action { Assert-RegistryContract -Registry $invalidMapRegistry } -Message 'invalid unselected map state did not invalidate the whole registry'
    $unexpectedKey = $registryEntries[29] | Select-Object *
    $unexpectedKey | Add-Member -NotePropertyName unexpected -NotePropertyValue 'value'
    $unexpectedKeyRegistry = [pscustomobject]@{ schemaVersion = 1; updated = $registryFixture.updated; locale = 'he-IL'; evidenceBoundary = $registryFixture.evidenceBoundary; entries = @($registryEntries[0..28] + $unexpectedKey) }
    Assert-ContractThrows -Action { Assert-RegistryContract -Registry $unexpectedKeyRegistry } -Message 'unexpected unselected field did not invalidate the whole registry'
    $wrongParentTypeEntries = @($registryEntries | ForEach-Object { $_ | Select-Object * })
    $wrongParentTypeEntries[0].pageType = 'planning-tool'
    $wrongParentTypeRegistry = [pscustomobject]@{ schemaVersion = 1; updated = $registryFixture.updated; locale = 'he-IL'; evidenceBoundary = $registryFixture.evidenceBoundary; entries = $wrongParentTypeEntries }
    Assert-ContractThrows -Action { Assert-RegistryContract -Registry $wrongParentTypeRegistry } -Message 'transactional owner accepted a non-commercial registry parent'
    $missingPublicMapEntries = @($registryEntries | ForEach-Object { $_ | Select-Object * })
    $missingPublicMapEntries[29].status = 'live'
    $missingPublicMapEntries[29].mapState = $null
    $missingPublicMapRegistry = [pscustomobject]@{ schemaVersion = 1; updated = $registryFixture.updated; locale = 'he-IL'; evidenceBoundary = $registryFixture.evidenceBoundary; entries = $missingPublicMapEntries }
    Assert-ContractThrows -Action { Assert-RegistryContract -Registry $missingPublicMapRegistry } -Message 'public owner without map state passed the registry contract'

    $decisionRoute = Get-CanonicalOpportunityRoute -Entry $decision
    Assert-ContractCondition -Condition ($decisionRoute.FinalSlug -ceq 'haneda-vs-narita') -Message 'decision final slug is wrong'
    Assert-ContractCondition -Condition (@($decisionRoute.Structural).Count -eq 2 -and -not $decisionRoute.Structural[0].DraftOnly -and $decisionRoute.Structural[1].DraftOnly) -Message 'public guides index and draft-only cluster contracts are wrong'
    $transactionRoute = Get-CanonicalOpportunityRoute -Entry $transaction
    Assert-ContractCondition -Condition ($transactionRoute.FinalSlug -ceq 'bangkok' -and $transactionRoute.Structural[0].Slug -ceq 'hotels') -Message 'transaction cluster differing from slug was rejected'
    Assert-ContractCondition -Condition ((Get-CanonicalOpportunityRoute -Entry $larnaca).FinalSlug -ceq 'larnaca') -Message 'Cyprus transactional slug was rejected'
    Assert-ContractCondition -Condition (-not (Test-RegistryEntryEligible -Entry $backlog)) -Message 'backlog entry became eligible'

    $originalStatus = $Status
    try {
        $script:Status = 'publish'
        $script:EditorialReady = $false
        $script:PromotionConfirmation = ''
        Assert-ContractThrows -Action { Assert-PromotionAuthorization -Entry $transaction } -Message 'live status bypassed explicit promotion authorization'
    }
    finally {
        $script:Status = $originalStatus
    }

    $today = [DateTime]::UtcNow.ToString('yyyy-MM-dd')
    $sources = 1..10 | ForEach-Object { [pscustomobject]@{ title = "Source $_"; url = "https://example.com/source-$_"; checkedAt = $today } }
    $meta = [pscustomobject]@{
        _tra_vel_primary_topic = 'Tokyo'
        _tra_vel_author = 'Tra-Vel'
        _tra_vel_reviewer = 'Reviewer'
        _tra_vel_review_method = 'Method'
        _tra_vel_publication_status = 'publish-ready'
        _tra_vel_map_state = 'tokyo'
        _tra_vel_source_checked = $today
        _tra_vel_sources_json = ($sources | ConvertTo-Json -Depth 4 -Compress)
    }
    $structure = ((1..12 | ForEach-Object { "<h2>$hebrewWord</h2>" }) -join '') + ((1..3 | ForEach-Object { "<table><tr><td>$hebrewWord</td></tr></table>" }) -join '')
    $validPage = [pscustomobject]@{ content = [pscustomobject]@{ raw = $structure + (($hebrewWord + ' ') * 5000) }; meta = $meta }
    Assert-DecisionEvidence -Page $validPage -ExpectedMapState 'tokyo' | Out-Null
    $staleSources = @((ConvertFrom-Json -InputObject ($sources | ConvertTo-Json -Depth 4 -Compress)))
    $staleSources[0].checkedAt = [DateTime]::UtcNow.AddYears(-2).ToString('yyyy-MM-dd')
    $staleMeta = $meta | Select-Object *
    $staleMeta._tra_vel_sources_json = ($staleSources | ConvertTo-Json -Depth 4 -Compress)
    $staleSourcePage = [pscustomobject]@{ content = $validPage.content; meta = $staleMeta }
    Assert-ContractThrows -Action { Assert-DecisionEvidence -Page $staleSourcePage -ExpectedMapState 'tokyo' } -Message 'stale individual source record passed'
    $misalignedMeta = $meta | Select-Object *
    $misalignedMeta._tra_vel_source_checked = [DateTime]::UtcNow.AddDays(-1).ToString('yyyy-MM-dd')
    $misalignedSourcePage = [pscustomobject]@{ content = $validPage.content; meta = $misalignedMeta }
    Assert-ContractThrows -Action { Assert-DecisionEvidence -Page $misalignedSourcePage -ExpectedMapState 'tokyo' } -Message 'source checked after aggregate review date passed'
    $thinPage = [pscustomobject]@{ content = [pscustomobject]@{ raw = $structure + (($hebrewWord + ' ') * 4900) }; meta = $meta }
    Assert-ContractThrows -Action { Assert-DecisionEvidence -Page $thinPage -ExpectedMapState 'tokyo' } -Message 'sub-5000-word decision guide passed'
    $transactionPage = [pscustomobject]@{ content = [pscustomobject]@{ raw = ("<h2>$hebrewWord</h2>" * 4) + (($hebrewWord + ' ') * 800) } }
    Assert-TransactionalEvidence -Page $transactionPage -ExpectedMapState 'bangkok' | Out-Null
    $unreadyMeta = New-OpportunityReadinessMeta -Entry $transaction -State 'unready'
    Assert-ContractCondition -Condition (
        $unreadyMeta._tra_vel_seo_opportunity_ready -is [bool] -and
        $unreadyMeta._tra_vel_seo_opportunity_ready -eq $false -and
        $unreadyMeta._tra_vel_seo_conversion_ready -is [bool] -and
        $unreadyMeta._tra_vel_seo_conversion_ready -eq $false
    ) -Message 'the first publication phase does not keep both readiness flags exact boolean false'
    $readyMeta = New-OpportunityReadinessMeta -Entry $transaction -State 'ready'
    Assert-ContractCondition -Condition (
        $readyMeta._tra_vel_seo_opportunity_ready -is [bool] -and
        $readyMeta._tra_vel_seo_opportunity_ready -eq $true -and
        $readyMeta._tra_vel_seo_conversion_ready -is [bool] -and
        $readyMeta._tra_vel_seo_conversion_ready -eq $true
    ) -Message 'the final publication phase does not enable exact transactional readiness'
    $persistedUnready = [pscustomobject]@{ meta = [pscustomobject]@{ _tra_vel_seo_opportunity_id = 'bangkok-hotels'; _tra_vel_seo_opportunity_ready = $false; _tra_vel_seo_conversion_ready = $false } }
    Assert-PersistedDisabledReadinessEvidence -Page $persistedUnready -Entry $transaction | Out-Null
    $partialReadiness = [pscustomobject]@{ meta = [pscustomobject]@{ _tra_vel_seo_opportunity_id = 'bangkok-hotels'; _tra_vel_seo_opportunity_ready = $true; _tra_vel_seo_conversion_ready = $false } }
    Assert-ContractThrows -Action { Assert-PersistedDisabledReadinessEvidence -Page $partialReadiness -Entry $transaction } -Message 'partial readiness was accepted before the final publication phase'
    $persistedTransaction = [pscustomobject]@{ meta = [pscustomobject]@{ _tra_vel_seo_opportunity_id = 'bangkok-hotels'; _tra_vel_seo_opportunity_ready = $true; _tra_vel_seo_conversion_ready = $true } }
    Assert-PersistedReadinessEvidence -Page $persistedTransaction -Entry $transaction | Out-Null
    $stringReadiness = [pscustomobject]@{ meta = [pscustomobject]@{ _tra_vel_seo_opportunity_id = 'bangkok-hotels'; _tra_vel_seo_opportunity_ready = 'true'; _tra_vel_seo_conversion_ready = $true } }
    Assert-ContractThrows -Action { Assert-PersistedReadinessEvidence -Page $stringReadiness -Entry $transaction } -Message 'string readiness was accepted as exact boolean evidence'
    Assert-ContractThrows -Action { Assert-SiteEnvironmentBinding -SiteOrigin ([Uri]'https://tra-vel.co.il') -RequestedEnvironment staging } -Message 'production host accepted the staging environment'
    Assert-ContractThrows -Action { Assert-SiteEnvironmentBinding -SiteOrigin ([Uri]'https://tra-vel.co.il') -RequestedEnvironment production -Confirmation '' } -Message 'production host accepted a missing confirmation'
    Assert-ContractThrows -Action { Assert-SiteEnvironmentBinding -SiteOrigin ([Uri]'https://staging.example.test') -RequestedEnvironment staging } -Message 'staging accepted an unbound host and confirmation'
    Assert-ContractThrows -Action { Assert-SiteEnvironmentBinding -SiteOrigin ([Uri]'https://staging.example.test') -RequestedEnvironment staging -ExpectedStagingHost 'typo.example.test' -StagingHostConfirmation 'USE TRA-VEL SEO STAGING HOST staging.example.test' } -Message 'staging accepted a mismatched allowed host'
    Assert-ContractThrows -Action { Assert-SiteEnvironmentBinding -SiteOrigin ([Uri]'https://staging.example.test') -RequestedEnvironment staging -ExpectedStagingHost 'staging.example.test' -StagingHostConfirmation 'USE TRA-VEL STAGING HOST' } -Message 'staging accepted the old generic confirmation phrase'
    Assert-ContractThrows -Action { Assert-SiteEnvironmentBinding -SiteOrigin ([Uri]'https://staging.example.test') -RequestedEnvironment staging -ExpectedStagingHost 'staging.example.test' -StagingHostConfirmation 'USE TRA-VEL SEO STAGING HOST other.example.test' } -Message 'staging confirmation was not bound to the selected hostname'
    Assert-SiteEnvironmentBinding -SiteOrigin ([Uri]'https://staging.example.test') -RequestedEnvironment staging -ExpectedStagingHost 'staging.example.test' -StagingHostConfirmation 'USE TRA-VEL SEO STAGING HOST staging.example.test' | Out-Null
    Assert-ContractThrows -Action { Assert-CredentialEnvironmentBinding -RequestedEnvironment staging -CredentialFile $DefaultProductionCredentialPath -DefaultProductionCredentialFile $DefaultProductionCredentialPath } -Message 'staging accepted the default production credential file'
    $ContractStagingCredentialPath = [IO.Path]::Combine([IO.Path]::GetTempPath(), 'tra-vel-staging.credential.xml')
    Assert-ContractThrows -Action { Assert-CredentialEnvironmentBinding -RequestedEnvironment staging -CredentialFile $ContractStagingCredentialPath -DefaultProductionCredentialFile $DefaultProductionCredentialPath } -Message 'staging accepted an implicit credential path'
    Assert-CredentialEnvironmentBinding -RequestedEnvironment staging -CredentialFile $ContractStagingCredentialPath -DefaultProductionCredentialFile $DefaultProductionCredentialPath -CredentialPathWasExplicit $true | Out-Null
    Write-Host 'SEO opportunity provision contract tests passed.'
}

if ($ContractTest) {
    Invoke-ProvisionContractTests
    return
}

if (-not (Test-Path -LiteralPath $RegistryPath -PathType Leaf)) { throw "Registry not found: $RegistryPath" }
try { $registry = [IO.File]::ReadAllText($RegistryPath, [Text.Encoding]::UTF8) | ConvertFrom-Json }
catch { throw "Registry is invalid JSON: $($_.Exception.Message)" }
Assert-RegistryContract -Registry $registry | Out-Null
$entries = @($registry.entries)
$ids = @{}
foreach ($entry in $entries) {
    if ([string]::IsNullOrWhiteSpace([string]$entry.id) -or $ids.ContainsKey([string]$entry.id)) { throw 'Registry owner IDs must be non-empty and unique.' }
    $ids[[string]$entry.id] = $entry
}

if ($OwnerId.Count) {
    $selected = foreach ($id in $OwnerId) {
        if (-not $ids.ContainsKey($id)) { throw "Unknown registry owner ID: $id" }
        $entry = $ids[$id]
        if (-not (Test-RegistryEntryEligible -Entry $entry)) { throw "Owner '$id' is backlog or has an unsupported page type; it will not be created." }
        $entry
    }
}
else {
    $selected = @($entries | Where-Object { Test-RegistryEntryEligible -Entry $_ })
}

if (-not @($selected).Count) {
    Write-Host 'No live or content-ready decision/transaction registry pages are eligible. No WordPress request was made.'
    return
}

foreach ($entry in @($selected)) {
    $route = Get-CanonicalOpportunityRoute -Entry $entry
    Assert-PromotionAuthorization -Entry $entry | Out-Null
    if (-not $Apply) {
        Write-Host "DRY RUN: $Status $($entry.id) at $($route.CanonicalPath); structural ancestors remain unowned and draft-only where required."
    }
}
if (-not $Apply) { return }

if ([string]::IsNullOrWhiteSpace($SiteUrl)) { throw '-SiteUrl is required with -Apply.' }
$SiteUrl = $SiteUrl.TrimEnd('/')
$siteUri = [Uri]$SiteUrl
Assert-SiteEnvironmentBinding -SiteOrigin $siteUri -RequestedEnvironment $Environment -Confirmation $ProductionConfirmation -ExpectedStagingHost $AllowedStagingHost -StagingHostConfirmation $StagingConfirmation | Out-Null
Assert-CredentialEnvironmentBinding -RequestedEnvironment $Environment -CredentialFile $CredentialPath -DefaultProductionCredentialFile $DefaultProductionCredentialPath -CredentialPathWasExplicit ($PSBoundParameters.ContainsKey('CredentialPath')) | Out-Null
if (-not (Test-Path -LiteralPath $CredentialPath -PathType Leaf)) { throw "Credential file not found: $CredentialPath" }

$credential = Import-Clixml -LiteralPath $CredentialPath
$password = $credential.GetNetworkCredential().Password -replace '\s', ''
$pair = "$($credential.UserName):$password"
$encodedPair = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($pair))
$headers = @{ Authorization = "Basic $encodedPair"; Accept = 'application/json' }

function Invoke-WpRequest {
    param(
        [Parameter(Mandatory)][ValidateSet('Get', 'Post')][string]$Method,
        [Parameter(Mandatory)][string]$Path,
        $Body = $null
    )
    $uri = "$SiteUrl/wp-json/wp/v2/$Path"
    $arguments = @{ Uri = $uri; Headers = $headers; Method = $Method; MaximumRedirection = 0; TimeoutSec = $RequestTimeoutSeconds; UseBasicParsing = $true }
    if ($null -ne $Body) {
        $arguments.ContentType = 'application/json; charset=utf-8'
        $arguments.Body = $Body | ConvertTo-Json -Depth 12 -Compress
    }
    $response = Invoke-WebRequest @arguments
    if ([int]$response.StatusCode -lt 200 -or [int]$response.StatusCode -ge 300) { throw "WordPress returned HTTP $($response.StatusCode) for $Path." }
    if ([string]::IsNullOrWhiteSpace([string]$response.Content)) { return $null }
    return ConvertFrom-Json -InputObject $response.Content
}

function Find-ExactPage {
    param([Parameter(Mandatory)][string]$Slug, [Parameter(Mandatory)][int]$ParentId)
    $encodedSlug = [Uri]::EscapeDataString($Slug)
    $candidates = @(Invoke-WpRequest -Method Get -Path "pages?slug=$encodedSlug&parent=$ParentId&status=any&context=edit&per_page=100&_fields=id,slug,parent,status,template,link,generated_slug,permalink_template,title,excerpt,content,meta")
    $exact = @($candidates | Where-Object { [string]$_.slug -ceq $Slug -and [int]$_.parent -eq $ParentId })
    if ($candidates.Count -ne $exact.Count -or $exact.Count -gt 1) { throw "Ambiguous or conflicting WordPress page for '$Slug' below parent $ParentId." }
    return $(if ($exact.Count) { $exact[0] } else { $null })
}

function Assert-PageIdentity {
    param([Parameter(Mandatory)]$Page, [Parameter(Mandatory)]$Route, [Parameter(Mandatory)][int]$ParentId, [Parameter(Mandatory)][string]$ExpectedStatus)
    if ([string]$Page.slug -cne [string]$Route.FinalSlug -or [int]$Page.parent -ne $ParentId) { throw 'WordPress final page identity drifted.' }
    if ([string]$Page.template -cne 'page-seo-opportunity.php') { throw 'WordPress final page template drifted.' }
    if ([string]$Page.status -cne $ExpectedStatus) { throw "WordPress final page status is '$([string]$Page.status)', expected '$ExpectedStatus'." }
    if ($ExpectedStatus -ceq 'publish' -and [string]$Page.link -cne "$SiteUrl$($Route.CanonicalPath)") { throw 'WordPress published link is not the exact registry canonical.' }
    if ($ExpectedStatus -ceq 'draft') {
        if ([string]$Page.generated_slug -cne [string]$Route.FinalSlug) { throw 'WordPress draft generated_slug drifted.' }
        $permalinkTemplate = [string]$Page.permalink_template
        if (-not $permalinkTemplate.Contains('%pagename%') -or $permalinkTemplate.Replace('%pagename%', [string]$Route.FinalSlug) -cne "$SiteUrl$($Route.CanonicalPath)") {
            throw 'WordPress draft permalink template does not resolve to the exact registry canonical.'
        }
        if ([string]$Page.link -cne "$SiteUrl/?page_id=$([int]$Page.id)") { throw 'WordPress draft link is not the exact plain page-ID permalink.' }
    }
}

function Assert-StructuralPageIdentity {
    param([Parameter(Mandatory)]$Page, [Parameter(Mandatory)]$Node, [Parameter(Mandatory)][int]$ParentId, [Parameter(Mandatory)][int]$ExpectedId)
    if ([int]$Page.id -ne $ExpectedId -or [int]$Page.id -le 0) { throw "Structural page '$($Node.Slug)' returned an invalid or drifting ID." }
    if ([string]$Page.slug -cne [string]$Node.Slug -or [int]$Page.parent -ne $ParentId) { throw "Structural page '$($Node.Slug)' slug or parent drifted." }
    if ([string]$Page.status -cne 'draft' -or [string]$Page.template -cne [string]$Node.Template) { throw "New structural page '$($Node.Slug)' did not persist its draft/template identity." }
}

function Resolve-StructuralChain {
    param([Parameter(Mandatory)]$Route, [Parameter(Mandatory)]$Entry)
    $parentId = 0
    foreach ($node in @($Route.Structural)) {
        $page = Find-ExactPage -Slug $node.Slug -ParentId $parentId
        if ($null -eq $page) {
            if ($Status -eq 'publish') { throw "Publishing requires structural page '$($node.Slug)' to already exist." }
            $created = Invoke-WpRequest -Method Post -Path 'pages' -Body @{
                slug = $node.Slug; parent = $parentId; title = $node.Title; status = 'draft'; template = $node.Template
            }
            $createdId = 0
            if (-not [int]::TryParse([string]$created.id, [ref]$createdId) -or $createdId -le 0) { throw "Structural page '$($node.Slug)' creation returned an invalid ID." }
            $page = Invoke-WpRequest -Method Get -Path "pages/${createdId}?context=edit&_fields=id,slug,parent,status,template,link,generated_slug,permalink_template,meta"
            Assert-StructuralPageIdentity -Page $page -Node $node -ParentId $parentId -ExpectedId $createdId
            $exactReadback = Find-ExactPage -Slug $node.Slug -ParentId $parentId
            if ($null -eq $exactReadback -or [int]$exactReadback.id -ne $createdId) { throw "Structural page '$($node.Slug)' failed exact path re-query after creation." }
        }
        if ($node.DraftOnly -and [string]$page.status -cne 'draft') { throw "Structural page '$($node.Slug)' must remain draft and unowned." }
        if ($node.DraftOnly -and $null -ne $page.meta -and -not [string]::IsNullOrWhiteSpace([string](Get-PageMetaValue -Page $page -Key '_tra_vel_seo_opportunity_id'))) {
            throw "Structural page '$($node.Slug)' must not carry opportunity ownership metadata."
        }
        if (-not $node.DraftOnly -and $Status -eq 'publish') {
            if ([string]$page.status -cne 'publish' -or [string]$page.template -cne [string]$node.Template) { throw "Public index '$($node.Slug)' is not operational with template '$($node.Template)'." }
            if ([string]$page.link -cne "$SiteUrl/$($node.Slug)/") { throw "Public index '$($node.Slug)' canonical link drifted." }
        }
        $parentId = [int]$page.id
    }
    return $parentId
}

function Find-PublishedPageByCanonicalPath {
    param([Parameter(Mandatory)][string]$CanonicalPath, [AllowEmptyString()][string]$ExpectedTemplate = '')
    $parentId = 0
    $page = $null
    foreach ($slug in @($CanonicalPath.Trim('/') -split '/')) {
        $page = Find-ExactPage -Slug $slug -ParentId $parentId
        if ($null -eq $page -or [string]$page.status -cne 'publish') { throw "Semantic parent $CanonicalPath is not fully published." }
        $parentId = [int]$page.id
    }
    if ([string]$page.link -cne "$SiteUrl$CanonicalPath") { throw "Semantic parent $CanonicalPath does not have an exact canonical permalink." }
    if ($ExpectedTemplate -and [string]$page.template -cne $ExpectedTemplate) { throw "Semantic parent $CanonicalPath must use template '$ExpectedTemplate'." }
    return $page
}

try {
    foreach ($entry in @($selected)) {
        $route = Get-CanonicalOpportunityRoute -Entry $entry
        $parentId = Resolve-StructuralChain -Route $route -Entry $entry
        $page = Find-ExactPage -Slug $route.FinalSlug -ParentId $parentId

        if ($Status -eq 'draft') {
            if ($null -ne $page) {
                Assert-PageIdentity -Page $page -Route $route -ParentId $parentId -ExpectedStatus ([string]$page.status)
                if ([string](Get-PageMetaValue -Page $page -Key '_tra_vel_seo_opportunity_id') -cne [string]$entry.id) { throw 'Existing page owner metadata does not match the registry.' }
                Write-Host "Existing authored page preserved without mutation: $($entry.id) (page $([int]$page.id))."
                continue
            }
            $body = @{
                title = [string]$entry.primaryIntent
                slug = $route.FinalSlug
                parent = $parentId
                status = 'draft'
                template = 'page-seo-opportunity.php'
                excerpt = [string]$entry.conversionAction
                meta = @{
                    _tra_vel_seo_opportunity_id = [string]$entry.id
                    _tra_vel_seo_opportunity_ready = $false
                    _tra_vel_seo_conversion_ready = $false
                }
            }
            $saved = Invoke-WpRequest -Method Post -Path 'pages' -Body $body
            $readback = Invoke-WpRequest -Method Get -Path "pages/$([int]$saved.id)?context=edit&_fields=id,slug,parent,status,template,link,generated_slug,permalink_template,title,excerpt,content,meta"
            Assert-PageIdentity -Page $readback -Route $route -ParentId $parentId -ExpectedStatus 'draft'
            if ([string](Get-PageMetaValue -Page $readback -Key '_tra_vel_seo_opportunity_id') -cne [string]$entry.id) { throw 'Persisted owner metadata drifted.' }
            Write-Host "Draft provisioned: $($entry.id) (page $([int]$readback.id))."
            continue
        }

        if ($null -eq $page) { throw "Publishing requires an existing authored draft for $($entry.id)." }
        if ([string]$page.status -notin @('draft', 'publish')) { throw "Publishing requires $($entry.id) to be an existing draft or a previously published exact owner." }
        $originalPageStatus = [string]$page.status
        Assert-PageIdentity -Page $page -Route $route -ParentId $parentId -ExpectedStatus $originalPageStatus
        if ([string](Get-PageMetaValue -Page $page -Key '_tra_vel_seo_opportunity_id') -cne [string]$entry.id) { throw 'Existing draft owner metadata does not match the registry.' }

        $currentEditorialReady = Get-PageMetaValue -Page $page -Key '_tra_vel_seo_opportunity_ready'
        $currentConversionReady = Get-PageMetaValue -Page $page -Key '_tra_vel_seo_conversion_ready'
        $expectedConversionReady = [string]$entry.pageType -ceq 'transactional-cluster'
        $alreadyPublishedAndReady = $originalPageStatus -ceq 'publish' -and
            $currentEditorialReady -is [bool] -and $currentEditorialReady -eq $true -and
            $currentConversionReady -is [bool] -and $currentConversionReady -eq $expectedConversionReady

        if ($alreadyPublishedAndReady) {
            $preservedParentId = Resolve-StructuralChain -Route $route -Entry $entry
            if ($preservedParentId -ne $parentId) { throw 'Structural parent chain drifted while validating an existing published owner.' }
            if ([string]$entry.pageType -ceq 'decision-guide') {
                Assert-DecisionEvidence -Page $page -ExpectedMapState ([string]$entry.mapState) | Out-Null
                $semanticParent = Find-PublishedPageByCanonicalPath -CanonicalPath ([string]$entry.parentPath) -ExpectedTemplate 'page-destination.php'
                Assert-DecisionEvidence -Page $semanticParent -ExpectedMapState ([string]$entry.mapState) | Out-Null
            }
            else {
                Assert-TransactionalEvidence -Page $page -ExpectedMapState ([string]$entry.mapState) | Out-Null
            }
            Assert-PersistedReadinessEvidence -Page $page -Entry $entry | Out-Null
            Write-Host "Already-published valid page preserved without mutation: $($entry.id) at $($page.link)."
            continue
        }

        # Persist and verify the fail-closed precondition before any draft can transition to publish.
        # Existing published pages with partial or malformed readiness are also disabled before revalidation.
        $unreadyPreconditionBody = @{
            meta = (New-OpportunityReadinessMeta -Entry $entry -State 'unready')
        }
        $unreadyPreconditionSaved = Invoke-WpRequest -Method Post -Path "pages/$([int]$page.id)" -Body $unreadyPreconditionBody
        if ([int]$unreadyPreconditionSaved.id -ne [int]$page.id) { throw 'Unready precondition update returned a drifting page ID.' }
        $unreadyPreconditionReadback = Invoke-WpRequest -Method Get -Path "pages/$([int]$page.id)?context=edit&_fields=id,slug,parent,status,template,link,generated_slug,permalink_template,title,excerpt,content,meta"
        Assert-PageIdentity -Page $unreadyPreconditionReadback -Route $route -ParentId $parentId -ExpectedStatus $originalPageStatus
        Assert-PersistedDisabledReadinessEvidence -Page $unreadyPreconditionReadback -Entry $entry | Out-Null

        if ([string]$entry.pageType -ceq 'decision-guide') {
            Assert-DecisionEvidence -Page $unreadyPreconditionReadback -ExpectedMapState ([string]$entry.mapState) | Out-Null
            $semanticParent = Find-PublishedPageByCanonicalPath -CanonicalPath ([string]$entry.parentPath) -ExpectedTemplate 'page-destination.php'
            Assert-DecisionEvidence -Page $semanticParent -ExpectedMapState ([string]$entry.mapState) | Out-Null
        }
        else {
            Assert-TransactionalEvidence -Page $unreadyPreconditionReadback -ExpectedMapState ([string]$entry.mapState) | Out-Null
        }

        # Phase one publishes only the exact owner while both readiness flags remain false.
        $publishUnreadyBody = @{
            status = 'publish'
            meta = (New-OpportunityReadinessMeta -Entry $entry -State 'unready')
        }
        $publishedUnready = Invoke-WpRequest -Method Post -Path "pages/$([int]$page.id)" -Body $publishUnreadyBody
        if ([int]$publishedUnready.id -ne [int]$page.id) { throw 'Published-unready update returned a drifting page ID.' }
        $unreadyReadback = Invoke-WpRequest -Method Get -Path "pages/$([int]$page.id)?context=edit&_fields=id,slug,parent,status,template,link,title,excerpt,content,meta"
        Assert-PageIdentity -Page $unreadyReadback -Route $route -ParentId $parentId -ExpectedStatus 'publish'
        Assert-PersistedDisabledReadinessEvidence -Page $unreadyReadback -Entry $entry | Out-Null
        $postPublishParentId = Resolve-StructuralChain -Route $route -Entry $entry
        if ($postPublishParentId -ne $parentId) { throw 'Structural parent chain drifted during publication.' }
        if ([string]$entry.pageType -ceq 'decision-guide') {
            Assert-DecisionEvidence -Page $unreadyReadback -ExpectedMapState ([string]$entry.mapState) | Out-Null
            $semanticParent = Find-PublishedPageByCanonicalPath -CanonicalPath ([string]$entry.parentPath) -ExpectedTemplate 'page-destination.php'
            Assert-DecisionEvidence -Page $semanticParent -ExpectedMapState ([string]$entry.mapState) | Out-Null
        }
        else {
            Assert-TransactionalEvidence -Page $unreadyReadback -ExpectedMapState ([string]$entry.mapState) | Out-Null
        }

        # Phase two changes readiness only after the published-unready page and its dependencies pass.
        $enableReadinessBody = @{
            meta = (New-OpportunityReadinessMeta -Entry $entry -State 'ready')
        }
        $readinessSaved = Invoke-WpRequest -Method Post -Path "pages/$([int]$page.id)" -Body $enableReadinessBody
        if ([int]$readinessSaved.id -ne [int]$page.id) { throw 'Readiness update returned a drifting page ID.' }
        $readyReadback = Invoke-WpRequest -Method Get -Path "pages/$([int]$page.id)?context=edit&_fields=id,slug,parent,status,template,link,title,excerpt,content,meta"
        Assert-PageIdentity -Page $readyReadback -Route $route -ParentId $parentId -ExpectedStatus 'publish'
        Assert-PersistedReadinessEvidence -Page $readyReadback -Entry $entry | Out-Null
        $finalParentId = Resolve-StructuralChain -Route $route -Entry $entry
        if ($finalParentId -ne $parentId) { throw 'Structural parent chain drifted after readiness was enabled.' }
        if ([string]$entry.pageType -ceq 'decision-guide') {
            Assert-DecisionEvidence -Page $readyReadback -ExpectedMapState ([string]$entry.mapState) | Out-Null
            $semanticParent = Find-PublishedPageByCanonicalPath -CanonicalPath ([string]$entry.parentPath) -ExpectedTemplate 'page-destination.php'
            Assert-DecisionEvidence -Page $semanticParent -ExpectedMapState ([string]$entry.mapState) | Out-Null
        }
        else {
            Assert-TransactionalEvidence -Page $readyReadback -ExpectedMapState ([string]$entry.mapState) | Out-Null
        }
        Write-Host "Published after fail-closed two-phase verification: $($entry.id) at $($readyReadback.link)."
    }
}
finally {
    Remove-Variable password, pair, encodedPair, headers, credential -ErrorAction SilentlyContinue
}
