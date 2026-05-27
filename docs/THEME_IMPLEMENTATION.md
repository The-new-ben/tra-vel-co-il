# Tra-Vel Theme Implementation

Updated: 2026-05-27

## Files

- `style.css` - theme metadata and RTL consumer travel styling.
- `functions.php` - theme setup, private inquiry CPT, form handler, admin status workflow, public disclosure shortcode, schema, and attribution capture.
- `front-page.php` - Hebrew consumer homepage with hero image, search-style inquiry panel, destination cards, service links, FAQ and final CTA.
- `header.php`, `footer.php`, `index.php` - base WordPress templates.
- `theme.json` - editor palette, layout widths, and Hebrew font stack.

## Public Positioning

First homepage link cluster:

- `budapest-vacation`
- `prague-vacation`
- `vienna-vacation`
- `budapest-prague-vienna-trip`
- `cheap-flights-europe`
- `travel-insurance-europe`

Secondary services to add later:

- eSIM/SIM
- hotels
- car rental
- attractions
- custom itinerary planning

## SEO And Disclosure

- Front-page schema uses `TravelAgency`.
- Vacation rental structured data should not be added unless the site has real listing data and meets Google eligibility requirements.
- Affiliate/commercial disclosure shortcode: `[travel_commercial_disclosure]`.
- Deal pages must show update date and warn that price/availability changes.

## Inquiry Behavior

- Form submission creates a private `travel_lead` post in WordPress admin.
- Required fields: name, phone, destination, consent.
- Anti-spam: hidden honeypot field.
- Stored attribution: landing page, referrer, and UTM fields.
- Admin status can be updated from the lead edit screen.

## UPress Sync Checklist

1. Create a dedicated empty theme folder in UPress file manager.
2. Connect this GitHub branch to that folder only.
3. Run PHP lint in staging or via UPress tooling.
4. Preview the theme without activating on production.
5. Submit a test lead and verify CRM/email/UTM capture.
6. Only then activate.

## 2026-05-27 Homepage Upgrade

- Replaced public homepage copy that exposed internal operating language.
- Added original WebP image assets under `assets/img/`.
- Added responsive hero preload for LCP.
- Added richer homepage schema with TravelAgency, WebSite and FAQPage.
- Added hard internal links from the homepage and footer to all six published pages.
- Kept the existing form handler so the CMS flow still works after the redesign.

Detailed research and honesty notes: `docs/HOMEPAGE_DNA_RESEARCH_2026-05-27.md`.

## Remaining Work

- Add partner/affiliate list for flights, hotels, insurance, eSIM, car rental, and attractions.
- Add current price and availability workflow for each deal page.
- Add reviewed copy for each draft destination page.
- Decide if Grow/Green Invoice should sell paid itinerary planning.
- Add Search Console and conversion events after activation.
