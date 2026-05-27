# Tra-Vel WordPress Draft Update Log

Date: 2026-05-27
Owner: Codex acting as operator
Site: `tra-vel.co.il`
Method: WordPress REST API via encrypted local application password helper
Status: all pages remain `draft`

## 2026-05-27 Europe Travel-Commerce Page Upgrade

Updated the six draft money pages from `docs/MONEY_PAGE_BRIEFS.md`.

| ID | Slug | Status | Updated title |
| --- | --- | --- | --- |
| 88 | `budapest-vacation` | draft | `חופשה בבודפשט - טיסות, מלונות ומסלול קצר לפי תקציב` |
| 89 | `prague-vacation` | draft | `חופשה בפראג - חבילות, מלונות ומסלול קצר למטיילים מישראל` |
| 90 | `vienna-vacation` | draft | `חופשה בוינה - מלונות, טיסות ומסלול עירוני לפי סגנון` |
| 91 | `budapest-prague-vienna-trip` | draft | `בודפשט פראג ווינה - מסלול מרכז אירופה לפי ימים ותקציב` |
| 92 | `cheap-flights-europe` | draft | `טיסות זולות לאירופה - איך לבדוק מחיר אמיתי לפני הזמנה` |
| 93 | `travel-insurance-europe` | draft | `ביטוח נסיעות לאירופה - מה לבדוק לפני רכישה` |

## What Changed

- Rebuilt the six pages as Hebrew travel-commerce decision pages, not generic destination inspiration.
- Added commercial disclosure sections before future affiliate/partner CTAs.
- Added `rel="sponsored"` reminders for paid, affiliate, or sponsored links.
- Added price/availability caveats: final price, availability, baggage, cancellation, hotel terms, attraction terms, and supplier conditions are controlled by the supplier.
- Added official-source boxes for city tourism pages, Israeli travel warnings, EU passenger-rights context, Google helpful-content guidance, and Google sponsored-link guidance.
- Added CRM-ready CTA routing to `/#lead`.
- Added lead-field tables for source page, UTM, destination, dates, traveler count, budget, service need, consent, and partner/supplier handoff status.
- Added no-guarantee language around cheapest flights, insurance coverage, claim approval, refunds, attraction availability, route suitability, hotel availability, and legal/passenger-rights outcomes.

## Page-Specific Notes

### `travel-insurance-europe`

Commercial role: insurance affiliate/partner lead and later paid planning bundle.

Added:

- Checklist for destination, dates, traveler ages, baggage, cancellation, medical needs, activities, and multi-country travel.
- Traveler-type comparison for city break, family, winter, and multi-country travel.
- Clear warning that coverage, claim approval, compensation, and policy suitability are controlled by policy wording and supplier terms.

### `budapest-vacation`

Commercial role: Budapest city-break package, hotel/flight/eSIM/attraction modules, and itinerary leads.

Added:

- Fit notes for couples, budget travelers, families, and winter markets.
- Area chooser: city center, Jewish Quarter, Danube/Parliament side, and quieter family areas.
- Three-day route outline and package-vs-DIY decision table.
- Official-source requirement before hotel, attraction, or package claims.

### `prague-vacation`

Commercial role: Prague city-break package and hotel/attraction funnel.

Added:

- Fit notes for couples, first-time travelers, families, culture/old-town/Jewish heritage.
- Area chooser: Old Town, New Town, Lesser Town, and metro/tram-based options.
- Three-day route outline and offer components.

### `vienna-vacation`

Commercial role: Vienna culture/shopping/family city-break package and premium travel lead.

Added:

- Fit notes for culture, families, Christmas markets, shopping, first Europe trips, and premium city breaks.
- Area chooser: Innere Stadt, Ring, Mariahilf, and Prater/family angle.
- Vienna as standalone vs Vienna with Budapest/Prague route table.

### `budapest-prague-vienna-trip`

Commercial role: higher-value multi-city planning lead.

Added:

- Route duration table for 6, 7, 9, and 10+ days.
- City-order tradeoffs and transport decision table for train, flight, and private transfer.
- Paid planning gate: no charging before Grow/Green Invoice terms, cancellation policy, and service scope are approved.

### `cheap-flights-europe`

Commercial role: flight affiliate clicks, package upgrade leads, insurance/eSIM add-ons.

Added:

- Real-cost checklist: baggage, seat, airport, transfer, night arrival, cancellation/change, and connection risk.
- Package-vs-flight-only comparison.
- Destination modules linking to Budapest, Prague, Vienna, and multi-city pages.
- EU passenger-rights source context without legal-advice language.

## Research Anchors Used

- Google helpful content / people-first content: https://developers.google.com/search/docs/fundamentals/creating-helpful-content
- Google spam policies / thin affiliate warning: https://developers.google.com/search/docs/essentials/spam-policies
- Google outbound link qualification: https://developers.google.com/search/docs/crawling-indexing/qualify-outbound-links
- Budapest official tourist information: https://www.budapestinfo.hu/en/budapest-welcomes-you
- Prague official tourism: https://prague.eu/en/
- Vienna official tourism: https://www.wien.gv.at/tourismus/
- EU air passenger rights: https://europa.eu/youreurope/citizens/travel/passenger-rights/air/index_en.htm
- Israeli National Security Council travel warnings: https://www.gov.il/en/departments/dynamiccollectors/travel-warnings-nsc
- Israeli Ministry of Tourism complaint context: https://www.gov.il/he/service/review_and_complaints

## Verification

REST verification returned these pages as `draft` after update:

- `budapest-vacation`, modified `2026-05-27T05:25:27`
- `prague-vacation`, modified `2026-05-27T05:25:27`
- `vienna-vacation`, modified `2026-05-27T05:25:28`
- `budapest-prague-vienna-trip`, modified `2026-05-27T05:25:27`
- `cheap-flights-europe`, modified `2026-05-27T05:25:28`
- `travel-insurance-europe`, modified `2026-05-27T05:25:27`

Final REST output returned proper Hebrew titles from WordPress.

## Next Steps

1. Verify the lead form captures source page, destination, dates, traveler count, trip style, budget, service need, UTM fields, consent, and partner disclosure acknowledgement.
2. Add actual affiliate/partner links only after supplier terms and disclosure text are approved.
3. Ensure all paid/affiliate links use `rel="sponsored"`.
4. Recheck city tourism sources, Israeli travel warnings, EU passenger-rights context, prices, baggage, cancellation, and supplier terms immediately before publication.
5. Prepare Grow/Green Invoice paid itinerary products only after service scope, cancellation policy, and payment flow are approved.
6. Keep pages as draft until source dates, supplier terms, and commercial disclosures are complete.
