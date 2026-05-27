# Tra-Vel CRM Automation

Updated: 2026-05-27

## CRM Object

- Storage: private WordPress custom post type `travel_lead`.
- Purpose: qualify travel-package, insurance, flight, hotel, eSIM, and itinerary leads.
- Admin menu: Travel Leads.

## Captured Fields

- Contact: name, phone, email.
- Trip intent: destination, trip type, departure month, traveler count.
- Commercial intent: budget range, needed services, timeline.
- Attribution: landing URL, referrer, UTM source, medium, campaign, term, and content.
- Consent: required checkbox before submission.

## Status Workflow

1. `new` - lead received.
2. `qualified` - has usable destination, budget, dates, or service intent.
3. `supplier_research` - supplier or partner offer research required.
4. `offer_needed` - prepare package/flight/insurance/eSIM direction.
5. `partner_sent` - sent to supplier or affiliate partner.
6. `booked` - booking or paid referral happened.
7. `closed_lost` - no fit, no response, or rejected.

## Revenue Paths

- Package/itinerary lead generation.
- Travel insurance affiliate/lead pages.
- Flight, hotel, eSIM, car rental, and attraction affiliate links.
- Paid trip planning or custom itinerary service.
- Supplier referral fees where compliant.

## Operating Rules

- Do not publish outdated prices as current.
- Clearly disclose affiliate or paid supplier relationships near links/offers.
- Every deal page needs update date, supplier, booking terms, baggage rules, cancellation policy, and notes on visas/entry requirements where relevant.
- Do not imply that insurance coverage is guaranteed. Users must review policy wording and exclusions before purchase.

## Deployment Notes

- Sync only into a dedicated empty theme folder in UPress.
- Run PHP lint and staging preview before activation.
- After activation, submit one internal test lead and confirm:
  - Lead appears as private `travel_lead`.
  - Email notification reaches admin.
  - UTM fields persist from URL query parameters.
  - Commercial disclosure appears near offer/affiliate sections.

## 2026-05-27 Initial Status Routing

New leads no longer all start as `new`.

- Multi-city or paid-planning signals start as `supplier_research`.
- Flight, package, or offer requests start as `offer_needed`.
- Insurance, eSIM, hotel, or destination-qualified leads start as `qualified`.
- Everything else remains `new` for manual review.

## 2026-05-27 Conversion Measurement

When the stored-lead redirect reaches `?lead=received`, the theme pushes a privacy-safe `generate_lead` event into `window.dataLayer`.

Payload fields: `event`, `lead_form`, `portfolio_site`, `lead_result`, and `conversion_source`.

No personally identifiable information, destination, travel dates, budget, service need, or message text is sent in this browser event. Configure GTM/GA4 to treat `generate_lead` as the lead key event after production deployment.

## 2026-05-27 Admin Source Columns

The Travel Leads admin list shows `UTM source` and `Landing URL` beside the core triage fields so operators can quickly spot which campaigns and pages are producing leads.

Open the private lead detail screen for the full attribution record, including referrer URL and the complete UTM set.

## 2026-05-27 Revenue Board

The Travel Leads menu includes a `Revenue Board` submenu. It summarizes the latest 50 private leads by status, UTM source, and landing URL, then lists the latest leads with edit links.

Use it for weekly revenue triage: identify which pages and campaigns are producing qualified travel, insurance, flight, hotel, eSIM, or package opportunities before prioritizing new SEO work, supplier outreach, or paid tests.

The `Export board CSV` button downloads only board-level fields: lead ID, date, status, UTM source/medium/campaign, landing URL, and the private admin edit URL. It intentionally excludes name, phone, email, destination, travel dates, budget, service needs, and message text.
