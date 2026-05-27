# Tra-Vel WordPress API Status

Updated: 2026-05-27

## Access

- Site URL: `https://tra-vel.co.il`
- WordPress user: `travp6jq_admin`
- App password name: `Codex API 2026-05-26`
- Secret storage: local Windows DPAPI encrypted file outside the repo:
  `C:\Users\pro\Documents\websites\.codex-secrets\wordpress-app-passwords\tra-vel.co.il.json`

Do not commit app passwords or plaintext credentials.

## REST behavior

Both REST route styles work:

- `https://tra-vel.co.il/wp-json/wp/v2/...`
- `https://tra-vel.co.il/?rest_route=/wp/v2/...`

## Draft money pages created

| ID | Slug | Status | Intent |
| --- | --- | --- | --- |
| 88 | `budapest-vacation` | draft | Budapest flights/hotels/packages |
| 89 | `prague-vacation` | draft | Prague city-break funnel |
| 90 | `vienna-vacation` | draft | Vienna packages and hotels |
| 91 | `budapest-prague-vienna-trip` | draft | Multi-city Central Europe trip leads |
| 92 | `cheap-flights-europe` | draft | Cheap Europe flights pillar |
| 93 | `travel-insurance-europe` | draft | Travel insurance affiliate/lead page |

These are intentionally drafts. Before publishing, add current prices, route data, useful itinerary details, affiliate disclosures, and active partner links/forms.

## Draft content upgraded

Updated: 2026-05-27 05:25 UTC

The six draft Europe travel-commerce pages were upgraded through the WordPress REST API with Hebrew conversion copy, affiliate/partner disclosure gates, official-source boxes, price/availability caveats, CRM-ready CTA routing, and no-guarantee language for insurance, flights, hotels, attractions, routes, refunds, and passenger-rights context. All six remain `draft`.

See `docs/WP_DRAFT_UPDATE_LOG.md` for the full operational log.

## Research anchors

- Google policy requires clear disclosure of sponsorship/affiliate relationships.
- Google spam policies warn against thin affiliate pages. These pages need useful first-hand or practical travel value before going live.
- Hebrew competitors include Travelist, Kayak IL, Lametayel, Kavei, Gulliver, and destination-specific guides.
