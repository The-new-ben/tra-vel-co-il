# Tra-Vel WordPress Publish Log

Date: 2026-05-27
Owner: Codex acting as operator
Site: `tra-vel.co.il`
Method: WordPress REST API via encrypted local application password helper
Status: six pages published

## 2026-05-27 Public Narrative Cleanup

The first draft batch exposed internal operating language in public copy, including terms around money pages, leads, CRM, UTM, suppliers, and handoff logic. The pages were rewritten as consumer-facing Hebrew travel pages.

Public copy rules applied:

- No visible `CRM`, `UTM`, `lead`, `money page`, `supplier handoff`, `paid lead`, or revenue-language.
- Original Hebrew only. Competitor pages were used for structure and intent analysis, not copied text.
- No invented live prices.
- Consumer-facing CTA language only.
- Each page has one dominant primary keyword to reduce cannibalization.
- Insurance page uses general-information disclaimers and avoids coverage or claim guarantees.
- No em-dash style punctuation.

## Published Pages

| ID | Slug | Status | Live URL | Visible word count after depth pass |
| --- | --- | --- | --- | --- |
| 88 | `budapest-vacation` | publish | https://tra-vel.co.il/budapest-vacation/ | 1427 |
| 89 | `prague-vacation` | publish | https://tra-vel.co.il/prague-vacation/ | 1241 |
| 90 | `vienna-vacation` | publish | https://tra-vel.co.il/vienna-vacation/ | 1103 |
| 91 | `budapest-prague-vienna-trip` | publish | https://tra-vel.co.il/budapest-prague-vienna-trip/ | 1293 |
| 92 | `cheap-flights-europe` | publish | https://tra-vel.co.il/cheap-flights-europe/ | 1249 |
| 93 | `travel-insurance-europe` | publish | https://tra-vel.co.il/travel-insurance-europe/ | 1115 |

## Page-Specific Honesty Statements

### `budapest-vacation`

Public status: published and consumer-facing.

Honesty: the page is now much safer and more useful than the first draft, but it is still below the 1800-2200 word target suggested by Lovable/Semrush. Next improvement should add verified seasonal weather context, real attraction notes, and richer hotel-area examples.

### `prague-vacation`

Public status: published and consumer-facing.

Honesty: the page covers the main city-break intent and avoids internal language. It still needs more competitor-equivalent depth on food, neighborhoods, and attraction planning to reach the 1800-2200 word target.

### `vienna-vacation`

Public status: published and consumer-facing.

Honesty: this query has lower direct competition in Semrush, so the page can publish earlier. It needs more detail on museums, family options, and transport tickets before it becomes a strong flagship page.

### `budapest-prague-vienna-trip`

Public status: published and consumer-facing.

Honesty: this is a pillar page but still below the desired 2200-2600 word target. It should be expanded with verified rail times, route maps, and alternative 7, 9, 10, and 12 day itineraries.

### `cheap-flights-europe`

Public status: published and consumer-facing.

Honesty: this is the hardest travel keyword in the batch. It is published as a useful real-cost guide, but we should not expect it to beat Kayak, Skyscanner, El Al, Issta, or Travelist without supporting articles, tools, and authority links.

### `travel-insurance-europe`

Public status: published with compliance caveats.

Honesty: commercial value is high, but compliance risk is higher. The page is safe as a general checklist. It must not recommend insurers, prices, or coverage tables until partner terms, source dates, and compliant disclosure are approved.

## Verification

WordPress REST API returned `publish` for all six pages after the final depth pass.

Public URL checks returned HTTP 200 for all six URLs.

Visible rendered text scan found no internal operating terms:

- `CRM`
- `UTM`
- `lead`
- `ליד מסחרי`
- `ליד יקר`
- `ספקים`
- `handoff`
- `pipeline`
- `money page`
- `Revenue`
- `טופס צריך`
- `לייצר הכנסה`
- `לייצר כסף`

## Research Anchors Used

- Google helpful content: https://developers.google.com/search/docs/fundamentals/creating-helpful-content
- Google spam policies: https://developers.google.com/search/docs/essentials/spam-policies
- Google outbound link qualification: https://developers.google.com/search/docs/crawling-indexing/qualify-outbound-links
- Budapest official tourist information: https://www.budapestinfo.hu/en/budapest-welcomes-you
- Prague official tourism: https://prague.eu/en/
- Vienna official tourism: https://www.wien.gv.at/freizeit/urlaub-in-wien
- EU air passenger rights: https://europa.eu/youreurope/citizens/travel/passenger-rights/air/index_en.htm
- Israeli National Security Council travel warnings: https://www.gov.il/en/departments/dynamiccollectors/travel-warnings-nsc

## Lovable Usage

Lovable was used for one research prompt in project/session `SEO Navigator`.

- Starting visible credits: 141.5
- After first research prompt: 140.5
- Consumed: 1.0 credit
- Rollover visible after prompt: 27.3 credits expiring in about 16 hours

Lovable/Semrush findings were saved in `docs/CONTENT_DNA_RESEARCH_2026-05-27.md`.

## Next Work

1. Use more Lovable credits for competitor clusters and support-article architecture.
2. Expand these six pages further toward the word-count targets.
3. Generate or source legal-safe hero images and upload them through WordPress media.
4. Upgrade the theme presentation on mobile and improve the page template visual hierarchy.
5. Build supporting articles around the hard money pages instead of avoiding the hard keywords.
6. Add Search Console data once access is available.

## 2026-05-27 Homepage Upgrade Pass

Status: implemented locally in the theme repo. Pending GitHub push and UPress sync/live verification.

What changed:

- Replaced the homepage narrative with public-facing Hebrew travel copy.
- Removed visible internal operating language from the homepage, header and footer.
- Added generated and optimized WebP travel imagery.
- Rebuilt the homepage as a search-style travel inquiry experience inspired by Travelist, Issta, Gulliver, EasyGo, Skyscanner and Holidayfinder patterns.
- Connected homepage sections to the six published pages using direct internal links.
- Added FAQ copy and richer homepage schema.
- Added hero image preload and responsive image sources for better mobile/LCP behavior.

Lovable credit update:

- Lovable project/session used: `Travel Insights Hub`
- Total credits remaining after this block: 139.5
- Rollover expiring: 25.3 credits expire in 15 hours
- Credits consumed in this Tra-Vel research block so far: 2.0

Honesty:

- The homepage theme code is improved, but it is not confirmed live until GitHub/UPress sync completes.
- Local PHP lint could not run because PHP is not installed on this Windows environment.
- No live prices, partner inventory, payment flow or real reviews were added.

Internal research document:

- `docs/HOMEPAGE_DNA_RESEARCH_2026-05-27.md`
