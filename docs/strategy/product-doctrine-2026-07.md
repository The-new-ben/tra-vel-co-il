# Tra-Vel product doctrine — adopted 2026-07-21

## Amendment A — the intent machine (owner dictations, 2026-07-22)

The internal codename for this mechanism is "autopilot"; the word never appears in the
interface. It operates across the whole site.

1. Guided next action: after every meaningful choice (a map selection above all), one
   unmissable pulsing cue marks the next step. A first-time visitor sent by a friend must
   be able to reach an offer without help; a real-user test failing this blocks release.
2. Choices are chips, never chat-dumps: refinement questions render as tappable answer
   chips (party, dates, budget). Menus and destination pickers never redirect into the
   chat composer.
3. Silent-user completion: when the traveler stops feeding input, the system visibly and
   animatedly fills the logical details itself, from stored intent memory and honest
   context rules, then keeps going stage by stage all the way to a complete, fully priced
   offer standing one click from closure. A visible timer accompanies each auto-advance
   ("ממשיכים בעוד..."), and any touch pauses it and hands control back.
4. The payment threshold is absolute: autopilot assembles, prices, and proposes; it never
   charges, books, or engages suppliers. At the threshold it stops, presents the complete
   offer with every part editable, and waits for an explicit human yes. Consent and
   truthful-UX contracts outrank autopilot at every stage.
5. Composition tray: the offer carries optional add-ons from our catalog (store products,
   site services, VIP, eSIM, insurance, transfers) and pack tiers (basic / with products /
   premium) selectable like a set-menu order.
6. Price memory: known supplier and agent prices are cached with timestamps and served as
   instant approximations, always labeled with their freshness; AI and live feeds run only
   when data is stale or the traveler advances toward closure. Approximations are never
   presented as bookable prices.
7. Intent memory: every choice a traveler makes becomes a default for their next visit
   (device-local first, account-level later), with a one-tap reset.
8. Zero-click points: club and card points surface with minimal effort (deep link or scan),
   and the Earth then shows what those points buy. Live balance integrations are a
   partnership decision reserved to the owner; public conversion-value content ships first.
9. Vertical pillar Earths: cruises, diving, family tools, and conventions each get a hub
   whose hero is the Earth carrying that vertical's real points, above original pillar
   content that anchors a pillar-and-spoke SEO cluster. Spokes publish through the
   existing fail-closed editorial contracts; hubs never link to pages that do not exist.

Sequencing note: stages of item 3 that require real prices depend on the Release 2
affiliate data feeds; until those land, autopilot completes plans with honest
approximations from item 6 and routes closure through the assisted path.

Shipped v1 (theme 1.32.0): the guided next-action beacon (globe selection card,
home search dock steps, settled planner responses), planner refinement chips for
party, month and budget, device-local intent memory (traVelIntent) with a visible
reset, and the scoped planner silent completion: after six idle seconds an empty
composer fills stage by stage with a visible countdown, any touch stops it for
the page view, and it only marks the existing submit button. It never submits,
never prices, and never contacts a supplier. Items 4, 5, 6, 8 and the priced
end-to-end stage of item 3 remain gated on Release 2 data.

Source: owner strategy document (2026-07-21), adapted against the verified live state
(theme 1.27.1, agent-core 0.9.2, Travelpayouts project 552866 active, 11 indexable hubs,
/packages/budapest/ money page live). This file is the operating plan of record; update it
when a release changes any claim below.

## Thesis

The next proof is commercial behavior, not presentation: click Earth, receive a real
editable 360° trip, understand the full value, choose confidently, and pay or continue
with the responsible provider. Everything below serves that sentence.

## Standing laws adopted verbatim

1. Index only pages with unique customer value, sufficient inventory or stable
   information, original copy, internal links, clear canonical treatment and
   server-rendered content. Filtered combinations without unique value stay non-indexed.
   (Already enforced by the seo-opportunity and guide publication contracts.)
2. Product and Offer schema are reserved for validated commercial inventory, never
   demonstration amounts. (Already enforced: the schema gate strips Product/Offer/ItemList.)
3. Priority destinations are chosen with Search Console, Keyword Planner, commercial
   inventory and conversion data. Competitor presence alone is not search-volume evidence.
   (Matches the registry evidenceBoundary and the rank-first doctrine.)
4. Three commercial roles stay architecturally distinct: affiliate clickout, assisted
   agency quote, direct booking. Direct booking is deferred until the responsibility
   model (complete price, merchant identity, refunds, customer funds) is approved.
5. Insurance recommendations route to licensed providers (affiliate clickout lane only);
   agent copy must never produce personalized insurance advice.
6. The Earth needs a complete keyboard and text alternative, not an accessibility widget.

## Release order (adapted)

### Release 1 — coherence (mostly shipped; close the tail)
Shipped and verified: one-click pickers instead of chat-default (1.26.0), no scroll trap
(validator-enforced), double-click dive with detail tray (dive-store), guarded idle spin,
graceful truthful empty states, zero own-code console errors (lucide icon warnings not
reproducible on 1.27.1).
Remaining tail: saved-trip restoration audit with seeded state (the reported
contradictory empty-state next to a saved card), single-click auto-scroll audit on pin
selection, dead/ambiguous CTA sweep, AI kept inside selected-trip state.

### Release 2 — first real monetization (NEXT; highest priority)
- Connect one validated flight source and one hotel source via the Travelpayouts data
  APIs (Aviasales / Hotellook) using the encrypted-option token route; no secrets in repo.
- Normalize offers into the existing truthful-UX contract: price timestamp, refresh/
  revalidation before continuation, seller identity and checkout destination shown.
- SubID click tracking end to end plus a conversion ledger (click → TP postback → report).
- Demonstration amounts and validated offers must be impossible to confuse internally
  (separate types, separate rendering paths, validator-enforced).
- Assisted WhatsApp path stays as the secondary route.
Definition of proof: one visitor journey from Earth click to a real bookable partner
offer with tracked SubID, on production, with truthful price metadata.

### Release 3 — geolocation and discovery
Once-per-session arrival state, nearby airports, flexible dates, explicit surprise-me,
reduced-motion and keyboard equivalents. Geolocation introduces privacy obligations:
consent copy and a privacy note ship in the same release, and location is never stored
beyond the session without explicit opt-in.

### Release 4 — real agency pilot
The quote-case machine (operator queue, transitions, notifier) is live and E2E-proven;
the pilot wraps it in: one onboarded agency, private workspace, inventory/holds/quote
updates, revalidation and signed offers. Hosted payment only after the commercial
responsibility model is approved (owner decision).

### Release 5 — SEO and domestic scale (content track starts EARLY)
- The commercial local-map layer (weekend availability, driving time, kosher/accessible
  filters, family total price) belongs here, after Release 2 proves the offer engine.
- BUT the Israel content conquest does not wait: Eilat, Dead Sea, Jerusalem, Tel Aviv,
  Galilee, Golan, Negev enter the registry as destination hubs on the existing guide
  pipeline as soon as capacity allows; Israel becomes the highest-resolution part of
  Earth in content first, commerce second.
- Loyalty and cards (Matmid, Fly Card, Isracard, Max, Cal, points plus cash): guide-level
  content is safe early; any live benefit integration is a partnership/compliance
  decision floated to the owner first.

## Account / supplier room / trip cockpit (sequenced after Release 2)

Gap lists adopted as the backlog of record (registration, magic link, social login,
traveler profiles, documents, loyalty balances, bookings, payments, incidents, refunds,
notifications, consent; supplier operating room; one trip object per confirmed journey).
Magic-link access is the one item pulled early: it powers cockpit access and assisted
re-entry without passwords. The rest sequences after monetization proof funds it.

## Definition of done — 20-scenario gate (scoreboard 2026-07-21)

Passing today (verified this weekend): single click never moves the page; double click
zooms without opening details; named marker and empty location share interaction rules;
reduced-motion has no forced spin; searchable pages are indexable without the 3D app;
homepage offer path exists without chat; truthful no-inventory states.
Gated on Release 2: real offers with SubIDs, seller identity, demo/validated separation,
price-expiry revalidation, Athens real results, show-all alternatives list.
Gated on Release 3: geolocation states, full keyboard/screen-reader Earth equivalence.
Gated on Release 4: agency identification, supplier timeout resilience, price-change
explanation, cancellation service case.
Needs audit now: saved-trip exact restoration, mobile bottom sheet vs Earth manipulation.

## Compliance references (owner-supplied)

Israeli consumer guidance (remote sale, complete price), Amendment 13, insurance-agent
licensing, service accessibility regulations — linked in the source document; consulted
before any direct-sale or insurance-adjacent release.
