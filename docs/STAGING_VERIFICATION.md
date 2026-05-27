# Tra-Vel staging verification

Date: 2026-05-27

## Scope

- Site: `tra-vel.co.il`
- Staging: `tra-vel-co-il-rev.s998.upress.link`
- Theme folder: `wp-content/themes/travel-revenue`
- Active staging theme: `Tra-Vel Revenue`
- Execution excludes `jus-tice.co.il`; that site remains a reference pattern only.

## Research inputs

- Google SEO starter guide: https://developers.google.com/search/docs/fundamentals/seo-starter-guide
- Google vacation rental structured data reference for future travel listing pages: https://developers.google.com/search/docs/appearance/structured-data/vacation-rental
- Google Hotel Center vacation rental direct links: https://support.google.com/hotelprices/answer/10519514
- uPress sandbox import flow: https://support.upress.co.il/dev/import-to-sandbox/
- uPress Git/file-manager guidance: https://support.upress.io/tag/manage-git/

## Deployment proof

- Created a fresh uPress development environment with suffix `rev`.
- Imported the live `tra-vel.co.il` WordPress site into staging.
- Deployed the code-first theme from this repository into `wp-content/themes/travel-revenue`.
- Activated `Tra-Vel Revenue` on staging.
- Strengthened the anti-spam honeypot clipping rule before deployment.
- Confirmed the front page shows the travel lead funnel and success notice at `/?lead=received`.
- Chrome local-file upload is not enabled in this browser profile, so deployment used a temporary admin-only Code Snippets bridge to write the theme files. The temporary snippet was deactivated and moved to trash after the theme files were written.

## CRM proof

- Lead post type: `travel_lead`
- Admin screen: `/wp-admin/edit.php?post_type=travel_lead`
- Internal test lead: `בדיקת Codex סטייג׳ינג – בודפשט – 2026-05-27`
- Phone: `050-000-0000`
- Destination: `בודפשט`
- Budget: `5,000-10,000 ש״ח לאדם`
- Status column: `New`

## Money lane

- Primary commercial intent: Europe city-break packages for Israelis, starting with Budapest, Prague, Vienna, and a Budapest-Prague-Vienna multi-city route.
- Secondary commercial intent: travel insurance, cheap Europe flights, eSIM, hotels, attractions, and itinerary planning.
- Any future affiliate/deal page must include current supplier data, clear commercial disclosure, cancellation/baggage/visa/insurance notes, and a practical travel value layer so the page is not thin affiliate content.

## Production cautions

- The staging admin shows `All 404 Redirect to Homepage`; review 404 and redirect behavior before production SEO work.
- The live import includes Elementor/Hello, SEO plugins, Code Snippets, and legacy content. Do not remove or disable production plugins without a separate review.
- uPress Git manager accepts HTTPS clone URLs. Because the GitHub repository is private, uPress Git sync still needs an approved credential approach. Do not embed a GitHub token in uPress until the owner approves that exact method.
