# Tra-Vel SEO/AEO destination-guide system

Tra-Vel does not publish destination pages merely because they reach a word count. A flagship guide targets at least 5,000 Hebrew words, but it can become `publish-ready` only after its claims, sources, canonical intent and freshness data pass the repository gate.

## Editorial contract

Every destination owns one primary search intent and one clean canonical path below `/destinations/`. A source packet in `content/guides/*.sources.json` records:

- the canonical topic and route;
- the interactive map state used by the page;
- at least 12 decision-oriented sections;
- at least 10 sources, including six first-party or official sources;
- factual claims mapped to source IDs;
- volatile facts that must be rechecked immediately before publication;
- a minimum word target of 5,000 for flagship guides.
- at least three decision tables before a flagship guide can become `publish-ready`;
- public language checks that reject em dashes, en dashes and internal project terminology.

The theme-level validator applies the same punctuation and prototype-language policy to shared PHP, JavaScript and public JSON data. This prevents a clean guide from inheriting unsuitable copy from the header, footer, homepage or commercial comparison modules.

The first two publish-ready packets are:

- `budapest-2026.sources.json`, covering a focused European city break with official city, transport, airport, entry, currency and Israeli safety sources.
- `thailand-2026.sources.json`, covering a multi-region long-haul decision with official immigration, airport, rail, health, tourism, currency, police and Israeli safety sources.

Both hubs live below `/destinations/`, expose visible source evidence and connect their editorial decisions to the globe and commercial comparison pages.

## Publication states

- `research`: sources and angles are still being collected.
- `source-ready`: the source packet passes, but the article is not yet approved.
- `editorial-review`: the complete article is being checked for accuracy, usefulness and duplication.
- `publish-ready`: the packet must point to the final article file; CI verifies that it meets its 5,000-word minimum.

No automation may move an article to `publish-ready` solely because text was generated. Current fares, entry rules, transport details, flight availability and security guidance always require a publish-time recheck.

## WordPress metadata

The theme registers authenticated REST-editable metadata for pages using `page-destination.php`:

- `_tra_vel_primary_topic`
- `_tra_vel_author`
- `_tra_vel_source_checked`
- `_tra_vel_reviewer`
- `_tra_vel_review_method`
- `_tra_vel_map_state`
- `_tra_vel_sources_json`
- `_tra_vel_flight_time`, `_tra_vel_daily_budget`, `_tra_vel_best_season`, `_tra_vel_best_for`

The public guide shows author, reviewer, check date, source count, methodology and source links. An incomplete guide is visibly marked `Source review pending`; it is never presented as reviewed.

## Search behavior

- Destination guides use a clean singular canonical URL even when opened from a configured map.
- Destination hubs use nested routes such as `/destinations/budapest/`, with a real WordPress parent page and visible breadcrumbs.
- The directory and every published guide use crawlable HTML links. Interactive map state supplements those links instead of replacing them.
- Personal saved-trip pages are `noindex, follow`.
- Flight, hotel, package and map filter parameters are `noindex, follow` to prevent faceted crawl traps.
- The native schema graph contains `WebSite`, `TravelAgency`, `WebPage`, `Article` and `BreadcrumbList` entities. Destination articles can expose `lastReviewed`, `citation` and a `TouristDestination` topic.
- When Yoast is active, the theme enriches Yoast's Article node and suppresses its own duplicate graph.
- The theme does not emit `FAQPage`, demo `Product` or demo `Offer` markup. Travel FAQ rich results are not a viable target, and commercial schema is reserved for verified supplier inventory.

Google's current JavaScript SEO guidance recommends server-rendered content where practical and requires discoverable links to use real `<a href>` elements. Its breadcrumb documentation recommends visible hierarchy plus `BreadcrumbList` markup. The Tra-Vel destination system follows those constraints while retaining the interactive globe as a planning tool.

Run `node scripts/ci/validate-guide-packets.mjs` before publishing or changing a guide packet. The same check runs in pull requests and production packaging.

## Guarded WordPress synchronization

`scripts/wp/sync-guide.ps1` validates every packet in the repository before it contacts WordPress. It refuses to publish unless the selected packet is `publish-ready`, requires an explicit production-write confirmation, stores credentials only through the encrypted local DPAPI file, and verifies that the SHA-256 hash returned by WordPress exactly matches the validated repository article.

Dry run:

```powershell
& "C:\Users\janana\Documents\tra-vel-co-il\scripts\wp\sync-guide.ps1" -GuideId budapest-2026
```

Private draft synchronization:

```powershell
& "C:\Users\janana\Documents\tra-vel-co-il\scripts\wp\sync-guide.ps1" -GuideId budapest-2026 -Status draft -Apply -ProductionConfirmation "SYNC TRA-VEL GUIDE"
```

Replace the guide ID with `thailand-2026` to validate or synchronize the Thailand hub. A publish write additionally uses `-Status publish` and still requires the explicit production confirmation.
