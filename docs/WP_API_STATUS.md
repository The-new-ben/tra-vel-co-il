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

## Published travel-commerce pages

| ID | Slug | Status | Intent |
| --- | --- | --- | --- |
| 88 | `budapest-vacation` | publish | Budapest city-break planning and package inquiry |
| 89 | `prague-vacation` | publish | Prague city-break planning and package inquiry |
| 90 | `vienna-vacation` | publish | Vienna city-break planning and package inquiry |
| 91 | `budapest-prague-vienna-trip` | publish | Multi-city Central Europe route pillar |
| 92 | `cheap-flights-europe` | publish | Cheap Europe flights and real-cost decision guide |
| 93 | `travel-insurance-europe` | publish | Europe travel-insurance checklist and comparison guide |

Live URLs:

- https://tra-vel.co.il/budapest-vacation/
- https://tra-vel.co.il/prague-vacation/
- https://tra-vel.co.il/vienna-vacation/
- https://tra-vel.co.il/budapest-prague-vienna-trip/
- https://tra-vel.co.il/cheap-flights-europe/
- https://tra-vel.co.il/travel-insurance-europe/

## Published content upgraded

Updated: 2026-05-27 07:17 UTC

The six Europe travel-commerce pages were rewritten and published through the WordPress REST API with public-facing Hebrew copy. The rewrite removed internal operating language such as CRM, UTM, supplier handoff, paid leads, money page, and revenue language from visible content.

Verification after publish:

- All six URLs returned HTTP 200.
- REST status returned `publish` for all six pages.
- Visible rendered text scan found no internal operating terms.
- Pages include source/caveat sections and avoid invented live prices.
- Travel-insurance copy is general information only and avoids coverage, claim, medical, or legal guarantees.

See `docs/WP_DRAFT_UPDATE_LOG.md` for the full operational log.

## Research anchors

- Google policy requires clear disclosure of sponsorship/affiliate relationships.
- Google spam policies warn against thin affiliate pages. These pages need useful first-hand or practical travel value before going live.
- Hebrew competitors include Travelist, Kayak IL, Lametayel, Kavei, Gulliver, and destination-specific guides.
