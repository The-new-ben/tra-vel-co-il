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
- a minimum word target of 5,000 for flagship guides;
- at least six unique packet-source URLs cited visibly in the article;
- at least three decision tables before a complete draft can enter `editorial-review`;
- public language checks that reject em dashes, en dashes and internal project terminology.

The theme-level validator applies the same punctuation and prototype-language policy to shared PHP, JavaScript and public JSON data. This prevents a clean guide from inheriting unsuitable copy from the header, footer, homepage or commercial comparison modules.

The first four publish-ready packets are:

- `budapest-2026.sources.json`, covering a focused European city break with official city, transport, airport, entry, currency and Israeli safety sources.
- `thailand-2026.sources.json`, covering a multi-region long-haul decision with official immigration, airport, rail, health, tourism, currency, police and Israeli safety sources.
- `athens-2026.sources.json`, whose 5,398-word Hebrew guide connects the city-break and island-gateway decisions to official transport, airport, cultural-ticket, accessibility, entry and Israeli consular sources.
- `prague-2026.sources.json`, whose 5,155-word Hebrew guide covers neighborhoods, airport access, public transport, Jewish Prague, accessibility, family planning and booking decisions with official city and transport evidence.

All four hubs live below `/destinations/`, expose visible source evidence and connect their editorial decisions to the globe and commercial comparison pages.

Vienna, Dubai, Tokyo, and Lisbon are the next editorial candidates, but none is a fifth publish-ready hub. `vienna-2026.sources.json` and its linked `vienna-2026.he.html` article remain in `editorial-review`. The Vienna draft has 7,623 visible words, and its packet records 16 sources and 20 mapped facts.

`dubai-2026.sources.json` and its linked `dubai-2026.he.html` article also remain in `editorial-review`. The Dubai draft has 6,819 visible words, 33 decision-led H2 sections, 10 decision tables, 10 unique internal targets, and visible citations covering all 31 packet sources. Its packet maps 32 volatile facts.

`tokyo-2026.sources.json` and its linked `tokyo-2026.he.html` article remain in `editorial-review`. The Tokyo draft has 8,811 visible words, 28 decision-led H2 sections, 23 subordinate H3 sections, 14 decision tables, 11 unique internal targets, and visible citations covering all 45 packet sources. Its packet maps 46 volatile facts.

`lisbon-2026.sources.json` and its linked `lisbon-2026.he.html` article remain in `editorial-review`. The Lisbon draft has 9,603 visible words, 40 decision-led H2 sections, 13 decision tables, 16 unique internal targets, and visible citations covering all 37 packet sources. Its packet maps 40 volatile facts. These four top-level drafts remain unavailable as public guide routes until a named editor completes the review, every volatile fact and source link is revalidated at publication time, destination assets are approved, and an explicit promotion decision updates the publication and routing contracts.

`bangkok-2026.sources.json` and its linked `bangkok-2026.he.html` article form the first complete nested city-pillar draft. The Bangkok article remains in `editorial-review` with 8,399 visible words, 24 decision-led H2 sections, 17 subordinate H3 sections, 10 decision tables, 13 traveler FAQs, and visible citations covering all 46 packet sources. Its packet maps 32 volatile facts. The canonical remains `/destinations/thailand/bangkok/`, with Thailand as the real parent and `bangkok` as the existing map state. It is not a ninth Earth destination and has no public route, directory card, or indexable status.

The publication validator checks complete `editorial-review` articles before promotion. Every composed table-of-contents fragment must resolve, including destination-prefixed article IDs, and every internal link must point to a route already declared public by the smoke manifest or a published editorial registry record. A redirect to the homepage is not accepted as a working destination link.

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
- `_tra_vel_publication_status`
- `_tra_vel_flight_time`, `_tra_vel_daily_budget`, `_tra_vel_best_season`, `_tra_vel_best_for`

Publish-ready guides show the available author, named reviewer, check date, source count, methodology and source links. An incomplete guide stays useful and readable, but empty reviewer or date fields are omitted and the visible copy tells the traveler which changing details to recheck before purchase. Internal editorial-state labels are never shown as product copy, and the page is never presented as reviewed.

## Search behavior

- Destination guides use a clean singular canonical URL even when opened from a configured map.
- Destination hubs use routes such as `/destinations/budapest/`, with a real WordPress parent page and visible breadcrumbs. Supporting city guides may use a deeper real hierarchy, such as `/destinations/thailand/bangkok/`, without becoming a ninth Earth destination.
- The directory and every published guide use crawlable HTML links. Interactive map state supplements those links instead of replacing them.
- Personal saved-trip pages are `noindex, follow`.
- Flight, hotel, package and map filter parameters are `noindex, follow` to prevent faceted crawl traps.
- A destination template remains readable if editorial metadata is incomplete, but it is automatically `noindex, follow` and cannot emit an `Article` node until the runtime publication contract confirms at least 5,000 words, ten dated sources, author, reviewer, methodology, topic and map state.
- The native schema graph contains `WebSite`, `TravelAgency`, `WebPage`, `Article` and `BreadcrumbList` entities. Destination articles can expose `lastReviewed`, `citation` and a `TouristDestination` topic.
- When Yoast is active, the theme enriches Yoast's Article node and suppresses its own duplicate graph.
- The theme does not emit `FAQPage`, demo `Product` or demo `Offer` markup. Travel FAQ rich results are not a viable target, and commercial schema is reserved for verified supplier inventory.

## Publication-gated internal links

Destination hubs and registry-owned decision or transactional pages have a server-rendered same-cluster link surface. `tra_vel_v2_get_public_seo_opportunity_links()` preserves registry order, but a registry label is never enough to produce a link. Every target must resolve to the exact published WordPress page, canonical permalink, reusable SEO template and stored owner ID, and its complete publication contract must pass.

The current registry contains 52 decision and transactional child opportunities, all in `backlog`, so the production helper correctly returns no child cards today. It does not render a placeholder or “coming soon” block. When an owner is deliberately promoted to `live` or `content-ready` and its WordPress evidence passes, its destination hub and ready sibling pages gain an ordinary crawlable `<a href>` automatically.

The runtime tests prove that backlog, `editorial-review`, disabled readiness, wrong template, wrong owner, canonical drift, a missing page, an invalid registry and an unknown cluster cannot enter the link graph. Public link records contain only an ID, URL, traveler-facing title, card kind and CTA; publication checks, reviewer state and monetization internals are not exposed.

Google's current JavaScript SEO guidance recommends server-rendered content where practical and requires discoverable links to use real `<a href>` elements. Its breadcrumb documentation recommends visible hierarchy plus `BreadcrumbList` markup. The Tra-Vel destination system follows those constraints while retaining the interactive globe as a planning tool.

Run `node scripts/ci/validate-guide-packets.mjs` before publishing or changing a guide packet. The same check runs in pull requests and production packaging.

## Guarded WordPress synchronization

`scripts/wp/sync-guide.ps1` validates every packet in the repository before it contacts WordPress. It refuses to publish unless the selected packet is `publish-ready`, requires an explicit production-write confirmation, and stores credentials only through the encrypted local DPAPI file. It resolves each WordPress ancestor by exact slug and parent ID, so a nested guide cannot attach to a same-slug page elsewhere. After a write it reads the exact page back in authenticated edit context and verifies page ID, slug, parent, template, WordPress status, repository-content SHA-256, source count, and `_tra_vel_publication_status`. Published pages must return the exact canonical link. Drafts may return WordPress's plain page-ID link, but their official permalink template must independently resolve to the packet canonical. Redirects, ambiguous matches, slug suffix drift, wrong ancestry, missing metadata, and content drift fail closed.

Dry run:

```powershell
& "C:\Users\janana\Documents\tra-vel-co-il\scripts\wp\sync-guide.ps1" -GuideId budapest-2026
```

Network-free hierarchy and identity contract test:

```powershell
& "C:\Users\janana\Documents\tra-vel-co-il\scripts\wp\sync-guide.ps1" -GuideId contract-test -ContractTest
```

Private draft synchronization:

```powershell
& "C:\Users\janana\Documents\tra-vel-co-il\scripts\wp\sync-guide.ps1" -GuideId budapest-2026 -Status draft -Apply -ProductionConfirmation "SYNC TRA-VEL GUIDE"
```

Replace the guide ID with `thailand-2026` to validate or synchronize the Thailand hub. A publish write additionally uses `-Status publish` and still requires the explicit production confirmation.
