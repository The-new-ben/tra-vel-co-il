# Tra-Vel Homepage DNA Research And Implementation

Date: 2026-05-27
Scope: homepage and theme upgrade for `tra-vel.co.il`
Status: implemented locally in the theme repo, pending GitHub/UPress sync verification

## Public Rule

The homepage must speak only to the traveler. It must not expose internal operating language such as CRM, UTM, paid leads, supplier routing, money pages, revenue targets, or handoff logic.

## Web Research Used

Primary SEO and performance sources:

- Google Search Central, helpful content: https://developers.google.com/search/docs/fundamentals/creating-helpful-content
- Google Search Central, SEO starter guide and site structure: https://developers.google.com/search/docs/fundamentals/seo-starter-guide
- Google Search Central, spam policies: https://developers.google.com/search/docs/essentials/spam-policies
- Google Search Central, outbound link qualification: https://developers.google.com/search/docs/crawling-indexing/qualify-outbound-links
- web.dev Core Web Vitals and LCP: https://web.dev/articles/lcp
- web.dev image learning path: https://web.dev/learn/images

Competitor and SERP inspiration reviewed through Lovable plus manual checks:

- Travelist: https://www.travelist.co.il/
- Issta: https://www.issta.co.il/
- Gulliver: https://www.gulliver.co.il/
- Israir: https://www.israir.co.il/
- Skyscanner Israel: https://www.skyscanner.co.il/
- Kayak Hebrew Israel: https://www.he.kayak.com/
- Lametayel: https://www.lametayel.co.il/
- EasyGo: https://www.easygo.co.il/
- Holidayfinder: https://www.holidayfinder.co.il/

## Lovable Usage

Lovable project/session: `Travel Insights Hub`

Prompt purpose:

- Research only.
- Analyze dominant Hebrew travel homepage competitors.
- Extract design DNA, section order, SEO homepage architecture, microcopy, image prompts, implementation guidance, and honesty statements.

Useful Lovable findings:

- Israeli travel homepages usually lead with a search-style widget, not a long article.
- Since Tra-Vel does not yet have live inventory, the honest version is an intake/search widget that asks for destination, dates, travelers, budget and needs.
- Do not show fake `from` prices.
- Use homepage as a hub, not as a destination page competing with the city pages.
- Exact internal links should point to:
  - `/budapest-vacation/`
  - `/prague-vacation/`
  - `/vienna-vacation/`
  - `/budapest-prague-vienna-trip/`
  - `/cheap-flights-europe/`
  - `/travel-insurance-europe/`
- Homepage H1 should stay broad and brand/category based.
- Destination keywords belong in H2s, card headings, internal link anchors, and support copy.

Credit status checked in Lovable billing on 2026-05-27:

- Total credits remaining: 139.5
- Daily credits: 5, reset in 15 hours
- Monthly credits: 100, reset in 1 day
- Extra credits: 34.5
- Rollover expiring soon: 25.3 credits expire in 15 hours
- Credits used in this Tra-Vel research block: 2.0

Honesty: there are still 25.3 rollover credits that should be used quickly for the next blocks: page expansion briefs, support article clusters, competitor backlink/content gaps, and homepage variants for the other portfolio sites.

## Competitor DNA Summary

What the strongest travel competitors do:

- Search box or booking widget above the fold.
- Clear tabs or service choices: flights, packages, hotels, multi-city, insurance or extras.
- Large visual travel photography with strong landmark recognition.
- Phone or direct contact CTA in the header.
- Trust microcopy near the form.
- Destination cards below the search area.
- No long explanation before the user can act.
- Freshness cues such as promotions, update date, or changing deal rails.
- Mobile layout puts action first and collapses navigation horizontally.

What Tra-Vel should copy as DNA, not text:

- Search-shaped experience.
- Fast visual answer to "where can I go?"
- Strong internal links to hard commercial pages.
- Short useful explanations that help users choose.
- No fake live prices until inventory or partner data exists.

## Implemented Homepage Architecture

Files changed:

- `front-page.php`
- `header.php`
- `footer.php`
- `style.css`
- `functions.php`
- `assets/img/hero-budapest-1600.webp`
- `assets/img/hero-budapest-900.webp`
- `assets/img/city-budapest.webp`
- `assets/img/city-prague.webp`
- `assets/img/city-vienna.webp`

Sections implemented:

1. Full-bleed visual hero with H1: `חופשות באירופה שמתאימות בדיוק לכם`
2. Search-style intake panel connected to the existing WordPress submission handler.
3. Three destination cards: Budapest, Prague, Vienna.
4. Central Europe route band linking to the multi-city pillar.
5. Services block linking to flights and insurance pages.
6. Trust strip with public-facing reasons.
7. FAQ section with schema.
8. Final CTA with update date.
9. Footer with all six important internal links.

## Generated Images

Generated with the built-in image tool, then converted to WebP with FFmpeg.

Original generated PNGs remain under:

`C:\Users\pro\.codex\generated_images\019e65ee-1e14-7370-bb95-a46954dcdff8`

Workspace assets:

- `assets/img/hero-budapest-1600.webp`
- `assets/img/hero-budapest-900.webp`
- `assets/img/city-budapest.webp`
- `assets/img/city-prague.webp`
- `assets/img/city-vienna.webp`

Validation notes:

- No text in image.
- No logos.
- No watermarks.
- No recognizable faces.
- WebP sizes are lightweight: about 37 KB to 132 KB per asset.

## SEO Implementation Notes

- Homepage now links to all six published pages with clear Hebrew anchors.
- The homepage does not try to rank as `חופשה בבודפשט` directly. That phrase stays primarily for the Budapest page.
- FAQ schema was added through `travel_revenue_front_page_schema`.
- TravelAgency and WebSite schema are included.
- The old simple schema hook is removed and replaced with the richer graph.
- Hero image preload added for LCP.
- Hero image uses responsive source for mobile and desktop.
- Visible copy avoids the internal vocabulary flagged by the user.

## Design Notes

Design direction:

- Israeli travel competitor feel, but lighter and faster.
- Blue brand base, amber primary CTA, green secondary trust color, off-white service sections.
- Full-bleed hero image, not a gradient-only hero.
- Search panel overlaps the hero like competitor booking widgets.
- Destination cards are image-led, one card per destination on mobile.
- RTL throughout.
- No invented prices.
- No testimonials were added because real reviews were not available.

## Honesty Statement

What is done:

- The public homepage narrative was rewritten for travelers.
- Internal language was removed from visible homepage, header and footer copy.
- Original travel images were generated and optimized.
- The homepage now acts as a hub for the six published pages.
- Form plumbing remains connected to the existing WordPress private submission flow.

What is not done yet:

- The theme changes are local until GitHub/UPress sync publishes them.
- PHP lint could not run locally because PHP is not installed on this Windows machine.
- Live inventory, real prices, payment flow and partner feeds are not connected.
- Reviews were not added because we should not invent customer reviews.
- Search Console data has not been connected yet.

Next priority:

1. Commit and push theme changes.
2. Confirm UPress sync and live homepage rendering.
3. Use more Lovable rollover credits on page expansion and support article clusters.
4. Run a live mobile/page-speed check after deployment.
5. Expand the six published pages to the Lovable target word counts.
