[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [string]$SiteUrl,

    [ValidateSet('staging', 'production')]
    [string]$Environment = 'staging',

    [ValidateSet('draft', 'publish')]
    [string]$Status = 'draft',

    [string]$CredentialPath = "$env:USERPROFILE\Documents\.codex-secrets\wordpress-app-passwords\tra-vel.co.il.credential.xml",

    [switch]$Apply,

    [string]$ProductionConfirmation = ''
)

$ErrorActionPreference = 'Stop'
$SiteUrl = $SiteUrl.TrimEnd('/')

if ($Environment -eq 'production' -and $Apply -and $ProductionConfirmation -ne 'PUBLISH TRA-VEL V2') {
    throw 'A production write requires -ProductionConfirmation "PUBLISH TRA-VEL V2".'
}

if (-not (Test-Path -LiteralPath $CredentialPath)) {
    throw "Credential file not found: $CredentialPath"
}

$credential = Import-Clixml -LiteralPath $CredentialPath
$password = $credential.GetNetworkCredential().Password -replace '\s', ''
$pair = "$($credential.UserName):$password"
$encodedPair = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($pair))
$headers = @{ Authorization = "Basic $encodedPair" }

$pages = @(
    [pscustomobject]@{
        Slug = 'travel-map'
        Title = 'מפת המסע החכמה'
        Template = 'page-map.php'
        Excerpt = 'גלובוס אינטראקטיבי להשוואת מחירים, מסלולים, מלונות ועלות כוללת.'
    },
    [pscustomobject]@{
        Slug = 'thailand'
        Title = 'תאילנד'
        Template = 'page-destination.php'
        Excerpt = 'מדריך תאילנד מחובר למפת מסלול, עונות, טיסות, מלונות ועלויות.'
    },
    [pscustomobject]@{
        Slug = 'flights'
        Title = 'טיסות חכמות'
        Template = 'page-experience.php'
        Excerpt = 'השוואת טיסות לפי מחיר כולל, מסלול, זמן, כבודה וגמישות.'
    },
    [pscustomobject]@{
        Slug = 'hotels'
        Title = 'מלונות וחבילות'
        Template = 'page-experience.php'
        Excerpt = 'בחירת מלון לפי אזור, מסלול, תנאים ועלות כוללת.'
    },
    [pscustomobject]@{
        Slug = 'travel-insurance'
        Title = 'ביטוח נסיעות'
        Template = 'page-experience.php'
        Excerpt = 'השוואת כיסויים לפי היעד, המסלול, הנוסעים והפעילויות.'
    },
    [pscustomobject]@{
        Slug = 'ai-planner'
        Title = 'מתכנן הנסיעות החכם'
        Template = 'page-experience.php'
        Excerpt = 'מתכנן AI שהופך בקשה בשפה טבעית למסלול חזותי וחלופות ברורות.'
    },
    [pscustomobject]@{
        Slug = 'destinations'
        Title = 'יעדים'
        Template = 'page-experience.php'
        Excerpt = 'גילוי יעדים לפי תקציב, זמן, עונה וסגנון נסיעה.'
    },
    [pscustomobject]@{
        Slug = 'guides'
        Title = 'מדריכי נסיעות'
        Template = 'page-experience.php'
        Excerpt = 'מדריכי עומק מחוברים למפה, לנתונים ולשלב הבא בתכנון.'
    }
)

try {
    foreach ($page in $pages) {
        $lookupUri = "${SiteUrl}/wp-json/wp/v2/pages?slug=$($page.Slug)&context=edit&_fields=id,slug,status,template,title"
        $existing = @(Invoke-RestMethod -Uri $lookupUri -Headers $headers -Method Get)
        $body = @{
            title = $page.Title
            slug = $page.Slug
            status = $Status
            template = $page.Template
            excerpt = $page.Excerpt
        }

        if (-not $Apply) {
            $action = if ($existing.Count) { 'update' } else { 'create' }
            Write-Host "DRY RUN: $action /$($page.Slug)/ with template $($page.Template) and status $Status"
            continue
        }

        if ($existing.Count) {
            $uri = "${SiteUrl}/wp-json/wp/v2/pages/$($existing[0].id)"
            $result = Invoke-RestMethod -Uri $uri -Headers $headers -Method Post -ContentType 'application/json; charset=utf-8' -Body ($body | ConvertTo-Json -Depth 5)
            Write-Host "Updated /$($result.slug)/ (ID $($result.id), $($result.status))."
        }
        else {
            $uri = "${SiteUrl}/wp-json/wp/v2/pages"
            $result = Invoke-RestMethod -Uri $uri -Headers $headers -Method Post -ContentType 'application/json; charset=utf-8' -Body ($body | ConvertTo-Json -Depth 5)
            Write-Host "Created /$($result.slug)/ (ID $($result.id), $($result.status))."
        }
    }
}
finally {
    Remove-Variable password, pair, encodedPair, headers, credential -ErrorAction SilentlyContinue
}
