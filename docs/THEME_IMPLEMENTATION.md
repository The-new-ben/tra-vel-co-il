# Tra-Vel Theme Implementation

Updated: 2026-05-27

## Files

- `style.css` - theme metadata and RTL travel-funnel styling.
- `functions.php` - theme setup, `travel_lead` CRM CPT, lead handler, admin status workflow, commercial disclosure shortcode, schema, and attribution capture.
- `front-page.php` - Hebrew homepage for package leads and travel-service monetization.
- `header.php`, `footer.php`, `index.php` - base WordPress templates.
- `theme.json` - editor palette, layout widths, and Hebrew font stack.

## Commercial Positioning

First revenue cluster:

- `budapest-vacation`
- `prague-vacation`
- `vienna-vacation`
- `budapest-prague-vienna-trip`
- `cheap-flights-europe`
- `travel-insurance-europe`

Secondary add-ons:

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

## CRM Behavior

- Form submission creates a private `travel_lead`.
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

## Remaining Work

- Add partner/affiliate list for flights, hotels, insurance, eSIM, car rental, and attractions.
- Add current price and availability workflow for each deal page.
- Add reviewed copy for each draft destination page.
- Decide if Grow/Green Invoice should sell paid itinerary planning.
- Add Search Console and conversion events after activation.
