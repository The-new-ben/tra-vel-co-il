# Tra-Vel V2 competitive gap analysis

Research date: 2026-07-18
Scope: global travel leaders, leading Israeli travel brands, product gaps, conversion, monetization, and execution priorities
Status: product and commercial decision document

## Executive decision

Tra-Vel should not try to beat Booking.com, Expedia, Google, or Trip.com by owning more raw inventory on day one. That is not a credible launch strategy. Their advantage comes from global supply, transaction volume, loyalty programs, price data, support operations, and years of supplier integration.

Tra-Vel can build a defensible position by becoming the best decision and orchestration layer for an Israeli traveler:

1. Start with a natural-language request, a budget, or a destination.
2. Reduce hundreds of results to three explainable proposals.
3. Show the whole-party, whole-trip cost from Israel, not an attractive base price.
4. Show route, stay, transport, activities, dining, insurance, connectivity, equipment, and operational risks together.
5. Keep the 3D Earth usable for discovery and move detailed comparison into an adjacent or below-map decision surface.
6. Revalidate the chosen offer, disclose who sells and supports it, and move the traveler to payment with minimal friction.
7. Keep the trip in a persistent cockpit after payment, including alerts, documents, changes, and next actions.

The core product test is therefore not "do we have every feature?" It is:

> Can an Israeli traveler move from "I need a vacation" to a trusted, payable decision faster and with better total-cost understanding than on any competing site?

The current V2 repository has a promising discovery and trust foundation, including a native globe, structured AI intake, a 12-area planning kernel, saved items, price-watch state, and assisted quote cases. It does not yet have the supplier coverage, confirmed live inventory, transactional checkout, post-booking order synchronization, loyalty system, or proven monetization needed to claim an end-to-end travel marketplace.

## Method and scoring caveat

This is an evidence-based capability review, not a traffic, market-share, revenue, or private-systems ranking. Scores reflect only workflows evidenced on official company, help, developer, partner, or public product pages reviewed on the research date. "Benchmark" and "strongest" below therefore mean strongest for the named capability within this sampled set, not largest in Israel or the world.

The competitor matrices use this reproducible decision rule for each capability:

- `0`: no current official evidence was found on the sampled public surface. This does not prove that the capability is absent internally.
- `1`: official evidence exists, but the workflow is announced, beta or account-limited, a redirect, single-step, or materially narrow in product or geography.
- `2`: a traveler can currently complete the capability's core decision or action, but material product, geography, workflow, or servicing limitations remain.
- `3`: official evidence shows a current, mature, end-to-end workflow across the capability's material scope, including transaction or service completion where that is intrinsic to the capability.

An announced or beta-only capability is capped at `1`. If official surfaces conflict, the lower evidenced state controls until the conflict is resolved. A `3` cannot be earned by breadth alone, by an announcement, or by linking to another seller. Every changed cell must preserve an evidence record containing the URL, checked date, observed workflow, live/beta/announced status, applicable geography or account limitation, and the reason for the score. The cited notes under each product are the evidence index for this review.

Google Travel, Flights, and Hotels and Tripadvisor plus Viator are explicitly composite workflow benchmarks. They are useful comparisons, but they are not equivalent to a single product surface. Scores are not averaged into a league table. Product availability can vary by country, app, account, and supplier, so Israel availability is tested separately in the mandatory gate below.

"SEO/content" scores refer to observable crawlable information architecture and content breadth, not a claim about current Google ranking. A valid ranking study needs a fixed Hebrew query set, Israel locale, device, date, Search Console data, and repeatable SERP capture.

### Matrix abbreviations

| Code | Capability |
| --- | --- |
| IN | Inspiration and destination discovery |
| SE | Search, filters, and comparison |
| MP | Map and geospatial planning |
| FL | Flexible dates, budget, or anywhere discovery |
| AI | Conversational or AI-assisted planning |
| TC | Total-cost and value comparison |
| TR | Trust, reviews, policy clarity, and provenance |
| IV | Inventory and product breadth |
| CK | Checkout and payment maturity |
| PC | Post-booking cockpit and trip servicing |
| LO | Loyalty, membership, or wallet |
| SO | Crawlable SEO and editorial content system |
| B2 | Supplier, partner, affiliate, or B2B platform |
| CV | Conversion design and action clarity |
| MO | Monetization breadth and maturity |

### Capability-specific score anchors

The generic decision rule above is applied through these capability anchors. A reviewer chooses the highest level for which all statements in that cell are evidenced; partial evidence falls to the lower level.

| Code | `1` evidence | `2` evidence | `3` evidence |
| --- | --- | --- | --- |
| IN | Static inspiration or destination content with little personalization | Traveler can filter, select, or save useful destination options | Personalized, current, multi-context discovery connects destination fit to actionable trip state |
| SE | Narrow search, a single provider, or redirect-only comparison | Current searchable offers and useful filters, with material supplier or vertical limits | Normalized, current, multi-supplier comparison spans the capability's core verticals and preserves equivalence through action |
| MP | Passive map or basic location pins | Price, inventory, route, or itinerary objects are selectable and synchronized with detail | Semantic world-to-local planning coordinates current objects, route, cost, and itinerary without blocking map use |
| FL | One limited flexible-date, budget, or open-destination tool | Current options respond to multiple flexible constraints | Flexibility spans route, stay, duration, and material ground cost with actionable comparison |
| AI | Announced, beta-limited, ungrounded, or detached assistant | Current conversation edits grounded plan or result state, with meaningful scope limits | Grounded assistant coordinates multiple products and servicing states with approval boundaries and actionable current results |
| TC | Base price or one incomplete total | Mandatory product-level total and scope are comparable | Whole-party, whole-trip ledger exposes equivalent components, missing costs, and risk before commitment |
| TR | Basic rating, seller, or policy disclosure | Source, freshness, scope, policy, seller, and support cues cover the core decision | Provenance, equivalence, payment role, protection, and servicing responsibility remain clear end to end |
| IV | One narrow product or destination set | Multiple useful travel verticals or broad supply in one core vertical | Broad contracted supply spans core trip products and material destinations |
| CK | Deep link or seller redirect without owned payment | Revalidation, payment, confirmation, and failure handling work for a material but limited path | Mature multi-product checkout includes payment, issuance, refund, fraud, and support operations |
| PC | Saved plan, basic itinerary, or static confirmation | Current booking status, documents, alerts, and support work for material products | Cross-supplier itinerary supports synchronized changes, disruption, refund, and next-action servicing |
| LO | Account-only discount or narrow recurring benefit | Funded benefits or wallet value work across meaningful repeat behavior | Mature cross-product loyalty compounds savings, status, and service across trips |
| SO | Crawlable but narrow or repetitive destination pages | Structured, useful, internally linked content covers material intents | Broad, distinct, maintained entity and intent graph supports discovery and commerce; this is not a ranking claim |
| B2 | Affiliate links, manual leads, or basic partner access | API or portal supports a material supply or distribution workflow with scope limits | Mature onboarding, content, availability, reservation, reporting, access, and settlement operations |
| CV | Generic CTA or redirect with little state continuity | State-aware CTA, revalidation, recovery, and intent preservation cover a useful path | Official public workflow evidence shows core journeys reaching payment or named-seller completion, confirmation, and support |
| MO | One narrow public revenue surface | Official public or partner evidence shows multiple booking or attachment revenue surfaces | Official evidence shows diversified core booking, attachment, repeat-value, and B2B revenue surfaces; private profitability is not inferred |

## Global capability matrix

| Product | IN | SE | MP | FL | AI | TC | TR | IV | CK | PC | LO | SO | B2 | CV | MO |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Booking.com | 2 | 3 | 2 | 1 | 2 | 2 | 3 | 3 | 3 | 2 | 3 | 3 | 3 | 3 | 3 |
| Expedia | 2 | 3 | 2 | 2 | 2 | 2 | 3 | 3 | 3 | 3 | 3 | 2 | 3 | 3 | 3 |
| Google Travel, Flights, Hotels (composite) | 3 | 3 | 3 | 3 | 1 | 2 | 3 | 3 | 1 | 1 | 0 | 1 | 3 | 2 | 3 |
| Airbnb | 3 | 3 | 3 | 2 | 0 | 2 | 3 | 2 | 3 | 3 | 0 | 2 | 3 | 3 | 3 |
| Trip.com | 3 | 3 | 2 | 2 | 3 | 2 | 3 | 3 | 3 | 3 | 3 | 3 | 3 | 3 | 3 |
| Skyscanner | 3 | 3 | 2 | 3 | 0 | 2 | 2 | 2 | 1 | 1 | 0 | 3 | 2 | 3 | 2 |
| KAYAK | 3 | 3 | 3 | 3 | 3 | 2 | 2 | 2 | 1 | 2 | 0 | 3 | 2 | 3 | 2 |
| Hopper | 2 | 2 | 1 | 2 | 1 | 2 | 2 | 2 | 3 | 2 | 2 | 1 | 3 | 3 | 3 |
| Tripadvisor plus Viator (composite) | 3 | 3 | 2 | 1 | 3 | 2 | 3 | 3 | 2 | 2 | 0 | 3 | 3 | 3 | 3 |
| GetYourGuide | 3 | 3 | 2 | 1 | 0 | 2 | 3 | 2 | 3 | 2 | 0 | 3 | 3 | 3 | 3 |

### What the global leaders prove

#### Booking.com

Booking.com proves that broad product inventory, policy clarity, account continuity, support, and loyalty can reinforce each other. Its official overview lists stays, flights, rental cars, taxis, and attractions, cloud-synchronized bookings, flexible cancellation options, and multilingual support. Genius progress spans stays, flights, cars, taxis, and attractions. Its AI Trip Planner supports natural-language inspiration and itinerary questions, but the strongest moat remains supply and transaction trust, not the chat surface.

Tra-Vel implication: do not imitate Booking.com's result grid. Match its offer completeness, policy clarity, supplier identity, and support expectations. Win by joining these components into an Israeli total-trip decision.

Sources: [Booking.com fast facts](https://news.booking.com/fast-facts), [Booking.com AI Trip Planner](https://news.booking.com/bookingcom-launches-new-ai-trip-planner-to-enhance-travel-planning-experience/), [Booking.com Genius](https://www.booking.com/genius.html), [Booking.com affiliate program](https://www.booking.com/affiliate-program/v2/index.html), [Booking.com Demand API](https://developers.booking.com/demand/docs/open-api/demand-api).

#### Expedia

Expedia proves the commercial value of packages, cross-product loyalty, price protection, and an itinerary that keeps trip components together. One Key applies across multiple Expedia Group brands and product types. Expedia exposes price tracking and Price Drop Protection, and its 2026 roadmap includes package price insights and natural-language activity planning. Expedia Group's B2B portfolio is broader than Rapid alone: Rapid currently documents lodging shopping, booking, payment, and post-booking servicing; Rapid Car is beta, with a full launch expected in early 2027. A separate 2025 Private Label Solutions announcement described car and activity APIs, insurance testing first on TAAP, and an air API as forthcoming. These live, beta, test, and announced states must not be treated as equally mature.

Tra-Vel implication: Expedia is the best broad benchmark for package economics, post-booking continuity, and B2B infrastructure. Tra-Vel must show a better Israeli cost ledger and clearer route trade-offs.

Sources: [Expedia One Key and AI planning](https://www.expedia.com/newsroom/expedia-group-announces-one-key-a-groundbreaking-new-loyalty-program-that-rewards-every-traveler/), [Expedia Price Drop Protection](https://www.expedia.com/why/price-drop-protection), [Expedia 2026 product announcements](https://www.expedia.com/newsroom/expedia-group-unveils-new-ai-experiences-expands-travel-ecosystem-and-launches-philanthropy-program-at-explore-2026/), [Rapid API and Car beta status](https://partner.expediagroup.com/en-us/solutions/build-your-travel-experience/rapid-api), [Expedia Group 2025 Private Label Solutions announcement](https://www.expedia.com/newsroom/expedia-group-expands-b2b-platform-and-launches-genai-partnerships/), [Rapid Lodging](https://developers.expediagroup.com/rapid/lodging).

#### Google Travel, Flights, and Hotels

Google proves that flexible destination discovery belongs on a map. Google Flights Explore can show prices from an origin across destinations and filter by budget, trip length, and duration. Date grids, price graphs, price tracking, and historical price context shorten the "when should I book?" decision. Google generally sends the traveler to an airline, OTA, or hotel booking site instead of owning checkout. Its partner policies make price accuracy, total taxes and fees, mobile-friendly deep links, and a consistent referral experience explicit quality requirements.

Tra-Vel implication: Google is the map and flexible-search benchmark and also a distribution dependency. Tra-Vel must meet Google's price-accuracy discipline and add the missing complete-trip, Hebrew, and post-decision layer.

Sources: [Google travel money-saving tools](https://blog.google/products-and-platforms/products/search/how-to-save-money-google-travel-2024/), [Google Flights fare ranking](https://support.google.com/travel/answer/7664728?hl=en), [Google Flights booking options](https://support.google.com/travel/answer/11583641?hl=en), [Google Hotel price accuracy](https://support.google.com/hotelprices/answer/6064419?hl=en), [Google hotel free booking links](https://support.google.com/hotelprices/answer/10472393?hl=en-GB).

#### Airbnb

Airbnb proves that a map, a strong visual marketplace, trust protection, and itinerary context can turn a focused vertical into a category. Its 2025 product expansion brought homes, services, and experiences into one app. The Trips experience lets travelers save nearby places to a map and itinerary, while bookings such as car service and grocery delivery can appear in that itinerary. Airbnb also has mature checkout, scheduled payments, and host-side supply tools.

Tra-Vel implication: Airbnb is the strongest benchmark for visual confidence and local experience attachment. Tra-Vel should copy neither its visual identity nor listings, but it should match the clarity of map-to-detail interaction and add flights, route risk, and total trip cost.

Sources: [Airbnb 2025 Summer Release](https://news.airbnb.com/airbnb-2025-summer-release/), [Airbnb trip map and itinerary](https://www.airbnb.com/help/article/4192), [Airbnb map search](https://www.airbnb.com/help/article/252), [AirCover and insurance](https://www.airbnb.com/help/article/3227), [Airbnb scheduled payments](https://www.airbnb.com/help/article/2143), [Airbnb professional hosting tools](https://www.airbnb.com/help/article/2499).

#### Trip.com

Trip.com is the closest global benchmark to the requested product vision. TripGenie uses text and voice, produces personalized itineraries, links into bookable products, and supports post-booking questions. Trip.Planner, announced in 2025, integrates flights, trains, hotels, restaurants, and attractions into an itinerary with real-time availability and over 20 million geotagged points of interest. Trip.com also reports collaborative itinerary editing and real-time flight and hotel alerts. Its loyalty system spans multiple products, and its partner platform includes reservation, availability, pricing, content, and promotion integrations.

Tra-Vel implication: merely adding a chat box is already behind the market. Tra-Vel must connect conversation to live options, editable map state, a cost ledger, and a persistent account.

Sources: [TripGenie product](https://www.trip.com/tripgenie), [TripGenie itinerary and alerts](https://www.trip.com/newsroom/tripgenie-new-features-2/), [Trip.Planner](https://www.trip.com/newsroom/trip-com-launches-trip-planner-smart-itineraries-tailored-to-your-travel-style-with-real-time-recommendations/), [Trip.com Rewards](https://www.trip.com/customer/loyalty?locale=en_us), [Trip.com connectivity](https://connect.trip.com/).

#### Skyscanner

Skyscanner proves that "Everywhere," cheapest month, price alerts, and transparent side-by-side comparison are enduring conversion tools. It is strongest before checkout and normally hands the traveler to a provider.

Tra-Vel implication: the "I do not know where" path must be a primary journey, not a secondary filter. Tra-Vel can win by turning a cheap flight into a realistic whole-trip proposal.

Sources: [Why Skyscanner](https://www.skyscanner.net/uk/en-us/gbp/about-us/why-skyscanner), [Explore Everywhere](https://www.skyscanner.net/flights/advice/skyscanner-everywhere-search-how-to-find-flights-to-anywhere-in-the-world), [Skyscanner flight search](https://www.skyscanner.net/flights).

#### KAYAK

KAYAK combines a strong budget map with real-time conversational search. Ask AI updates flight, hotel, and rental-car results beside the conversation. KAYAK's public help says it uses current provider data, can compare packages, exposes flight fare details, supports shared Trips, and remains primarily a metasearch product whose provider owns booking and payment.

Tra-Vel implication: the AI answer and the commercial results must update together. A detached planner that cannot render current, actionable options is not competitive.

Sources: [KAYAK Ask AI launch](https://www.kayak.com/news/ask-ai/), [KAYAK search and AI help](https://www.kayak.com/c/help/search/), [KAYAK Explore](https://www.kayak.com/news/where-to-fly-on-your-budget-kayak-explore/), [KAYAK product model](https://www.kayak.com/news/what-is-kayak/).

#### Hopper

Hopper's differentiator is travel fintech. Price prediction, price watches, Cancel For Any Reason, disruption assistance, wallet rewards, and B2B white-label platforms monetize uncertainty rather than only inventory. Its HTS business supplies these capabilities to airlines, banks, and travel brands.

Tra-Vel implication: price protection and disruption products can become high-value attachments, but only through properly contracted partners and exact terms. They must not be simulated through UI language.

Sources: [Hopper price predictions](https://help.hopper.com/en_us/about-our-price-predictions-Hy7cLt_Fv), [Hopper Cancel For Any Reason](https://help.hopper.com/en_us/cancel-for-any-reason-flights-ry7OrFuFD), [Hopper Carrot Cash](https://help.hopper.com/en_us/how-can-i-use-carrot-cash-ByevPFqgF), [HTS B2B platform example](https://media.hopper.com/news/lloyds-and-hts-partner-to-launch-travel-booking-portal-designed-for-modern), [Hopper fintech products](https://media.hopper.com/news/hopper-named-a-winner-in-the-2025-fintech-breakthrough-awards).

#### Tripadvisor, Viator, and GetYourGuide

Tripadvisor proves the value of review density, crawlable destination coverage, saved trips, collaboration, and AI itinerary creation. Viator and GetYourGuide prove that destination content can convert into bookable experiences with trust signals, flexible cancellation, support, supplier portals, and APIs. Viator's current official partner program advertises 300,000-plus experiences, an 8 percent commission on completed affiliate bookings, and APIs or links subject to partner qualification and terms.

Tra-Vel implication: activities must be part of every selected destination and itinerary, not a generic footer link. Experiences can also monetize high-intent SEO pages before Tra-Vel owns flight or hotel checkout.

Sources: [Tripadvisor Trips](https://www.tripadvisor.com/Trips), [Viator partner program](https://partnerresources.viator.com/), [Viator API solutions](https://partnerresources.viator.com/travel-commerce/), [GetYourGuide marketplace](https://www.getyourguide.com/), [GetYourGuide connectivity](https://www.getyourguide.supply/connectivity/partners-faqs).

## Israeli capability matrix

| Product | IN | SE | MP | FL | AI | TC | TR | IV | CK | PC | LO | SO | B2 | CV | MO |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| ISSTA | 2 | 2 | 0 | 1 | 1 | 1 | 2 | 3 | 2 | 1 | 1 | 2 | 3 | 3 | 3 |
| Travelist | 1 | 3 | 0 | 1 | 0 | 1 | 1 | 2 | 2 | 0 | 0 | 2 | 2 | 3 | 2 |
| Eshet Tours | 2 | 2 | 0 | 1 | 0 | 1 | 2 | 3 | 2 | 1 | 2 | 3 | 3 | 3 | 3 |
| Gulliver | 2 | 2 | 0 | 1 | 0 | 1 | 2 | 3 | 2 | 1 | 0 | 3 | 2 | 3 | 3 |
| Daka90 | 1 | 2 | 0 | 1 | 0 | 1 | 1 | 3 | 2 | 1 | 0 | 2 | 2 | 3 | 3 |
| Ophir Tours | 2 | 2 | 0 | 1 | 0 | 1 | 2 | 3 | 2 | 1 | 0 | 3 | 3 | 3 | 3 |
| Secret Flights | 3 | 3 | 0 | 3 | 0 | 1 | 2 | 2 | 2 | 2 | 3 | 3 | 2 | 3 | 3 |
| Lametayel | 3 | 2 | 1 | 2 | 0 | 1 | 2 | 2 | 1 | 0 | 2 | 3 | 3 | 2 | 3 |
| Kishrey Teufa | 2 | 2 | 0 | 1 | 0 | 1 | 2 | 3 | 2 | 1 | 1 | 3 | 3 | 3 | 3 |

## Tra-Vel repository readiness, kept separate from live competitors

Repository readiness is deliberately not a competitor score. Its scale is:

- `0`: absent from the audited repository;
- `1`: contract, schema, document, demo, or partial workflow exists, but no complete implemented workflow is evidenced;
- `2`: implemented and repository-tested, but not proven against production suppliers, payment, or reconciled external outcomes;
- `3`: production-verified within an explicitly named product, geography, and commercial scope, with current external evidence and reconciled outcomes.

Audit boundary: repository commit `3898fa7`, theme `1.18.0`, Agent Core `0.4.1`, checked 2026-07-18. This is code and content readiness, not proof of what is deployed on WordPress today.

| Capability | Readiness | Audited evidence | What prevents the next level |
| --- | ---: | --- | --- |
| Inspiration | 2 | Native Earth discovery and destination proposal surfaces | Production route, price, and destination evidence |
| Search and comparison | 1 | Normalized contracts and demo flight, stay, and package adapters | Contracted production adapters and comparable current offers |
| Map and geospatial planning | 2 | Native globe and coordinated 12-area decision kernel | Production objects, semantic city zoom, and mobile usability evidence |
| Flexible and anywhere discovery | 2 | Budget, intent, and open-destination interaction states | Current destination coverage and outcome evidence |
| AI planning | 2 | Structured intake, plan runs, provider boundary, and approval states | Production supplier search and approved consequential actions |
| Total-cost comparison | 1 | Cost-scope contract and suppression of unsupported amounts | Reproducible live whole-party totals |
| Trust and provenance | 2 | Data-mode, freshness, source, and false-claim controls | Seller, support, policy, and legal verification on production offers |
| Inventory and products | 1 | Adapter registry and demo data contracts | Signed, territory-approved, production inventory |
| Checkout and payment | 0 | No completed payable path in the audited repository | Seller handoff with outcome postback or compliant owned checkout |
| Post-booking cockpit | 1 | Saved workspace and assisted quote case state | Confirmed orders, supplier synchronization, and servicing |
| Loyalty and membership | 0 | No implemented cross-trip benefit system | Useful free account value, then proven funded benefits |
| SEO and editorial | 2 | Reviewed content system and four repository packets marked `publish-ready` | Deployed crawlability, broader distinct clusters, and search outcome evidence |
| B2B and supply platform | 1 | Adapter and partner-page foundations | Contract operations, onboarding, feed health, and settlement |
| Conversion design | 2 | State-based CTA, shortlist, save, and assistance surfaces | Paid or seller-confirmed outcome and full-funnel reconciliation |
| Monetization | 0 | Revenue models documented without connected payout evidence | Confirmed revenue, costs, clawbacks, and reconciliation |

## Mandatory Israeli production-readiness gate

This gate is not averaged into any score. A commercial path fails P0 if any applicable row fails, even when the same supplier or feature works elsewhere in the world.

| Dimension | P0 acceptance proof | Hard-fail condition |
| --- | --- | --- |
| Hebrew, RTL, and accessibility | End-to-end mobile and desktop task test in Hebrew and RTL, including keyboard, screen reader, focus, reduced motion, and a non-map alternative | A traveler cannot understand or complete the same purchase path without the globe or with assistive technology |
| Israeli eligibility and supply | Test bookings for Israeli residents or Israel-origin departures in the exact contracted territory and product | Availability is inferred from a global partner page or blocked after handoff |
| Price and currency | ILS whole-party total, source currency, FX method and timestamp, mandatory taxes and fees, baggage or occupancy scope, and revalidation | A base or per-person price can be mistaken for the payable family total |
| Seller, payment, and support | Named seller and merchant of record, supported payment method, Israeli card and 3DS test where applicable, refund owner, Hebrew support route, and service hours | Seller, charge owner, refund owner, or support path is unclear |
| Israeli calendar and departure context | Weekend, school-holiday, airport access, and departure-airport assumptions are explicit and date-aware | Recommendation ignores a material Israel-specific timing or access constraint |
| Route, safety, and entry evidence | Named sources, checked time, freshness policy, and fallback for route disruption, airport, entry, and official travel-warning data | Stale or editorial safety status is presented as current operational fact |
| Kosher and accessibility claims | Claim-level source, interpretation boundary, checked date, and contact confirmation where needed | A sensitive requirement is inferred from neighborhood, star rating, or unsourced copy |
| Privacy, consent, and legal role | Approved per-vertical role, consent record, data-sharing disclosure, retention rule, and escalation owner | Tra-Vel acts beyond its approved affiliate, agent, merchant, or licensed-insurance role |

### What the Israeli competitors prove

#### ISSTA

ISSTA is the broadest Israeli baseline in this review. Its public navigation covers flights, hotels, packages, organized tours, cruises, domestic travel, cars, sports and performances, ski, insurance, eSIM, student products, and multiple travel styles. Its homepage makes price and urgency prominent, and it exposes a WhatsApp entry for an AI agent. It does not evidence a map-led total-trip planner or a unified cost ledger on the reviewed public surface.

Tra-Vel must reach ISSTA's product breadth and commercial confidence without reproducing its layout, brand assets, or copy. The target is ISSTA-level commercial completeness with a materially faster and clearer decision system.

Sources: [ISSTA homepage](https://www.issta.co.il/), [ISSTA company profile](https://www.issta.co.il/support/about-issta.aspx).

#### Travelist

Travelist is the Israeli comparison benchmark. Its official homepage positions the service as a Hebrew comparison engine for flights, hotels, packages, and cars, with a direct flight option and destination landing pages. Its strongest value is side-by-side supplier comparison, not trip orchestration.

Tra-Vel must match comparison integrity, then add map context, complete trip cost, route risk, and an explainable recommendation.

Source: [Travelist homepage](https://www.travelist.co.il/).

#### Eshet Tours

Eshet combines broad inventory, curated house products, organized trips, domestic tourism, cruise, group and institutional travel, and a free loyalty club. Its site exposes deep destination, seasonal, family, kosher, and organized-tour content. It is a strong benchmark for packaged-product merchandising and Hebrew search coverage.

Tra-Vel must match Eshet's curated product confidence and human service while making the underlying trade-offs and complete cost more transparent.

Sources: [Eshet homepage](https://www.eshet.com/), [Eshet company profile](https://www.eshet.com/guide/aboutus/), [Eshet Club](https://www.eshet.com/eshet-club/), [Eshet organized family trips](https://www.eshet.com/organized/family/).

#### Gulliver

Gulliver strongly merchandises current prices, last-minute flights, packages, package combinations, organized tours, domestic stays, and large destination indexes. It demonstrates the Israeli market's expectation that real price cards and familiar product categories appear immediately.

Tra-Vel must keep price visibility but add whole-party totals, source freshness, baggage and policy detail, and a smaller number of decision-ready alternatives.

Source: [Gulliver homepage](https://www.gulliver.co.il/).

#### Daka90

Daka90 exposes a broad set of flight, hotel, package, car, football, and organized-tour destination pages. The sampled official destination pages also show the risk of large-scale generic destination copy: breadth alone does not create decision value or defensible expertise.

Tra-Vel should not mass-publish thin location permutations. Every indexed page must own a useful intent, reviewed evidence, local expertise, a map state, and an appropriate commercial action.

Sources: [Daka90 domestic hotel example](https://www.daka90.co.il/hotels-israel/dead-sea/lot-spa-hotel), [Daka90 flight destination example](https://www.daka90.co.il/flights/columbus).

#### Ophir Tours

Ophir combines flights, packages, domestic hotels, organized tours, cruises, cars, visas, business travel, and group travel. Its current homepage uses immediate-approval and book-now cues on priced offers. It also maintains extensive guide and destination content.

Tra-Vel must match offer confidence and human assistance, then add persistent comparison, map planning, and post-booking state.

Sources: [Ophir Tours homepage](https://www.ophirtours.co.il/), [Ophir company profile](https://www.ophirtours.co.il/about.html), [Ophir travel guides](https://www.ophirtours.co.il/guides.html), [Ophir business travel](https://business.ophirtours.co.il/about/).

#### Secret Flights

Secret Flights is the strongest Israeli benchmark for flexible deal discovery, alerts, membership monetization, and travel-finance attachment. Its homepage supports exact, flexible, and holiday searches. Its Premium and card product combines discounted fares, early WhatsApp alerts, a continuously updated price-drop board, travel-category foreign exchange benefits, and partner benefits. This creates a recurring relationship before and after a flight search.

Tra-Vel must treat price watches and membership as a product, not a notification checkbox. It should differentiate through complete-trip intelligence instead of only flight discounts.

Sources: [Secret Flights homepage](https://secretflights.co.il/), [Secret Flights Premium and card](https://secretflights.co.il/premium), [Secret Flights price-drop board](https://secretflights.co.il/price-drops).

#### Lametayel

Lametayel is the strongest Israeli content-commerce benchmark. Its official surface combines destination information, current travel news, trip inspiration, airport information, community or editorial content, and monetized links to flights, hotels, attractions, insurance, eSIM, equipment, transfers, foreign currency, and cars. It is not an evidenced unified checkout or trip cockpit on the reviewed surface.

Tra-Vel must match its content usefulness and breadth, then connect every useful section to a saved map state, live comparison, and complete-trip proposal.

Source: [Lametayel homepage](https://www.lametayel.co.il/).

#### Kishrey Teufa

Kishrey Teufa exposes very broad destination, package, hotel, organized-tour, event, kosher, domestic, car, cruise, and travel-style navigation. The current homepage heavily emphasizes bookable packages with immediate approval and price per traveler. Its crawlable destination structure is commercially extensive.

Tra-Vel must match the product hierarchy and availability confidence while reducing navigational overload and making whole-party economics clear.

Source: [Kishrey Teufa homepage](https://www.kishrey-teufa.co.il/).

## Category leaders and Tra-Vel's required response

| Capability | Current benchmark | Why it wins | Tra-Vel response |
| --- | --- | --- | --- |
| Inspiration | Trip.com, Airbnb, Tripadvisor, Lametayel | Rich visual and editorial discovery tied to places | A personalized Earth shortlist with evidence, fit, season, and cost |
| Search and comparison | Booking.com, Google, Skyscanner, KAYAK, Travelist | Large supply, fast filters, flexible dates, provider comparison | One query contract across flights, stays, packages, activities, and total cost |
| Geospatial planning | Google, KAYAK, Airbnb | Map is connected to current prices and selectable inventory | 3D world discovery plus regional and city detail, with one coordinated support surface |
| Anywhere and flexible discovery | Google, Skyscanner, KAYAK, Secret Flights | Budget and date flexibility can replace destination input | Make budget-first and vibe-first first-class entry paths |
| AI planning | Trip.com, KAYAK, Expedia, Booking.com, Tripadvisor | Conversation changes live or actionable results | Grounded Hebrew AI that edits visible constraints, map state, and live shortlist |
| Total-trip economics | Expedia and Trip.com are closest | Packages and multiple trip elements are connected | Show full party cost, unpriced items, time cost, risk, and value trade-offs |
| Trust | Booking.com, Airbnb, GetYourGuide, Google | Reviews, policies, support, protection, and price discipline | Source, seller, freshness, terms, price scope, and support owner on every offer |
| Inventory | Booking.com, Expedia, Trip.com; ISSTA and Eshet locally | Mature supplier contracts and broad catalogs | Partner for supply, normalize it, and never present demo inventory as live |
| Checkout | Booking.com, Expedia, Airbnb, Trip.com | Revalidation, payment, confirmation, and service are connected | Start with exact handoff tracking, then add direct checkout only by legal and supplier role |
| Post-booking | Trip.com, Expedia, Airbnb, KAYAK | Itinerary, alerts, and servicing retain the user | One cockpit for orders, alerts, documents, next actions, and approved changes |
| Loyalty | Booking.com, Expedia, Trip.com, Hopper, Secret Flights | Savings and status compound across trips | A Tra-Vel wallet built around watches, completed trips, attachments, and membership value |
| SEO and content | Tripadvisor, Lametayel, Eshet, Gulliver | Strong entity coverage and useful destination entry points | Reviewed intent clusters linked to exact map states and commercial modules |
| B2B supply | Expedia Group B2B, including mature Rapid lodging and separately staged PLS products; Booking.com, Trip.com, Hopper HTS, Viator, GetYourGuide | Supplier tooling creates inventory and distribution scale | Supplier onboarding, contracts, feed health, offer QA, settlement, and reporting |
| Monetization | Expedia, Booking.com, Hopper, Viator, local full-service agencies | Multiple products monetize each trip and repeat use | A disclosed revenue engine across booking, referral, attachment, subscription, and B2B |

## Tra-Vel phase gates

These are acceptance gates, not competitive scores. P0 is intentionally narrow: it proves one truthful, payable, serviceable flagship journey. It does not imply category leadership, broad inventory, or a competitive score of `3`. P1 earns breadth only after the same evidence holds across additional suppliers, destinations, and trip types.

| Capability | Current repository readiness | P0 acceptance evidence | P1 acceptance evidence |
| --- | ---: | --- | --- |
| Inspiration | 2 | Source-backed flagship proposals tied to real route, date, party, and budget state | Personalized discovery across materially broader destinations and intents |
| Search and comparison | 1 | At least one production flight and one stay path return current, equivalent, revalidatable offers for the flagship journey | Multi-supplier flight, stay, package, and attachment comparison with measured coverage |
| Map and geospatial planning | 2 | The flagship route, stay area, and proposal remain selectable on mobile with no obstructive stacking and an equivalent non-map path | World, region, and city semantic zoom with current objects and itinerary coordination |
| Flexible and anywhere discovery | 2 | Budget-first and date-flexible entry resolve to current flagship alternatives, with constraints visible | Date, budget, trip length, climate, and intent filters across broad current destination coverage |
| AI planning | 2 | Natural language edits visible constraints and launches current searches; consequential steps require approval | Conversation safely edits map, itinerary, cost, and multi-supplier state with evaluated grounding |
| Total-cost comparison | 1 | Reproducible whole-party total for the flagship journey, including explicit missing and optional costs | Equivalent full-trip comparison across routes, stays, packages, and attachments |
| Trust and provenance | 2 | Seller, source, timestamp, scope, policies, support owner, role, and disclosure pass on every P0 offer | Automated freshness, policy, provenance, and support controls across expanded supply |
| Inventory and products | 1 | Signed, Israel-eligible flight, stay, and activity paths cover the approved flagship scope | Broader destination and product coverage with supplier health and quality evidence |
| Checkout and payment | 0 | Either a named external-seller handoff reconciles to a supplier-confirmed paid booking, or an authorized owned checkout reconciles payment and issuance | Multiple authorized paths handle payment, failure, refund, and servicing consistently |
| Post-booking cockpit | 1 | The confirmed P0 booking creates normalized trip items, seller references, status, documents, support owner, and next action | Cross-supplier synchronization, alerts, and approved change handling |
| Loyalty and membership | 0 | No paid membership required; saved trips and confirmed bookings provide useful free account continuity | Funded, measurable cross-trip benefits with clear terms and retention evidence |
| SEO and editorial | 2 | A small deployed flagship cluster passes the indexed-page contract and connects to current map and commercial actions | Distinct route, airport, neighborhood, trip-style, and seasonal clusters expand from demand and conversion evidence |
| B2B and supply operations | 1 | Signed terms, manual onboarding, offer QA, health ownership, payout fields, and reconciliation work for P0 partners | Self-service onboarding, feed health, performance, access control, and settlement |
| Conversion design | 2 | One state-true CTA path is reconciled from qualified intent through current offer to confirmed paid outcome | The closed funnel works across major intents, devices, suppliers, and failure states |
| Monetization | 0 | Confirmed revenue and every direct cost reconcile to a P0 trip-level net contribution record | Diversified attachments and partner paths improve contribution without harming trust or completion |

## Feature parity and exceed ledger

| Product area | Parity requirement | Tra-Vel exceed requirement | Phase |
| --- | --- | --- | --- |
| Flight search | Round trip, one way, multi-city, cabin, party, stops, airline, nearby airport, flexible dates | Protected versus separate-ticket risk, bags and seats in total cost, stopover value, Israeli airport context | P0 |
| Flight result | Current fare, duration, stops, carrier, fare family, booking link | Best value, lowest friction, and flexible proposal with a plain-language trade-off | P0 |
| Accommodation search | Dates, rooms, child ages, amenities, rating, price, cancellation, map | Neighborhood fit, door-to-door time, whole-stay taxes, room-composition fit, day-plan impact | P0 |
| Package composition | Flight and stay combined with optional transfer or car | Transparent component ledger, bundle equivalence, unpriced items, party total, route and stay map | P0 |
| Activities | Destination, date, category, rating, price, cancellation, ticket action | Insert into realistic day plan with transit time, opening evidence, dietary or accessibility context | P1 |
| Transfers and cars | Vehicle, pickup, total fees, cancellation, supplier | Bind to flight and hotel, show pickup risk, child seat, luggage, insurance, and local-driving trade-offs | P1 |
| Organized trips | Dates, itinerary, inclusions, guide, price, confirmation status | Compare organized versus independent plan on total time, cost, flexibility, and language support | P1 |
| Cruises, sports, and events | Inventory, dates, package inclusions, room or seat class | Connect arrival, pre-stay, transfers, insurance considerations, and event-day itinerary | P1 |
| Domestic Israel | Hotels, packages, activities, family and group products | Reuse the same full-trip planner with road, rail, accessibility, season, and local activity layers | Later P1 |
| Insurance | Approved quote or referral journey and exact policy documents | Explain trip-specific questions and required extensions without unlicensed suitability claims | P0 |
| Flexible discovery | Cheapest month, flexible days, anywhere, budget, trip length | Include hotel and ground cost, climate, crowding, kosher or accessibility constraint, and total value | P1 |
| Price intelligence | Price history or context, watch, meaningful alert | Alert only when decision value changes, explain why, and preserve the exact comparable trip scope | P1 |
| Map | Search pins, clustering, selected detail, list alternative | 3D route discovery plus 2D city execution, total cost and itinerary coordination, zero obstructive stacking | P1 |
| AI | Text input, itinerary, recommendations, live result links | Hebrew speech, editable understanding, dependency graph, three proposals, approval-gated preparation and action | P1 |
| Saved trip | Save, compare, watch, return across devices | Preserve rejected options, assumptions, cost history, group decisions, and supplier change state | P0 to P1 |
| Checkout | Revalidation, traveler details, payment or exact deep handoff, confirmation | Minimal repeat entry, seller and support clarity, price-change recovery, plan continuity after handoff | P0 |
| Cockpit | Itinerary, confirmations, alerts, changes, support | Cross-supplier next action, impact analysis before a change, human-approved execution, contextual cross-sell | P0 to P1 |
| Trust | Reviews, policies, cancellation, support, secure payment | Data-mode label, provenance, confidence, equivalence, seller role, sponsored separation, update history | P0 |
| Loyalty | Account prices, rewards, wallet, tiers, or membership | Reward useful planning and completed travel without obscuring real price or steering recommendation rank | P1 |
| Content | Destination and service guides, internal links, authorship | Every useful section opens the exact decision or map state and measures commercial assistance | P0 |
| Supplier operations | Inventory, availability, reservations, cancellation, reporting | Feed health, offer quality, content provenance, conversion feedback, reconciliation, and traveler-fit signals | P0 to P1 |
| Accessibility | Keyboard, focus, semantic controls, reduced motion, readable status | Equivalent non-map planning, RTL-first interaction, accessible route and cost explanation | P0 |
| Performance | Fast mobile search, resilient errors, cached results | Partial supplier results, stable map controls, strict third-party budgets, no false completion animation | P0 |

## Critical gap register

| Priority | Gap | Current evidence | Business risk | Required win condition |
| --- | --- | --- | --- | --- |
| P0 | No contracted live flight and hotel supply in the repository | Demo and adapter contracts exist, but current product documents state live supplier integrations are in progress | The map cannot show reproducible bookable prices and the core promise cannot convert | At least one production flight path and one production accommodation path with signed terms, Israeli availability, timestamps, taxes, policies, and revalidation |
| P0 | No completed payment path | Search and assisted quote foundations exist; neither an owned checkout nor a supplier-outcome reconciliation path is implemented | A click, handoff, or quote can look like conversion while producing no confirmed booking or revenue | A named-seller handoff with authoritative paid or issued postback and reconciliation, or a compliant owned checkout with payment, issuance, confirmation, refund, and support ownership |
| P0 | No whole-trip live cost ledger | The 12-area kernel suppresses unsupported amounts correctly | A cheap fare can still produce an expensive or impractical trip | Whole-party total with airfare, bags, seats, lodging, taxes, fees, transfers, insurance allowance, and known required extras; missing costs remain explicit |
| P0 | AI is not yet connected to production supplier search | Structured intake and plan runs exist, but the provider contract explicitly avoids supplier or booking work | AI can feel impressive but cannot shorten the purchase journey | User language changes visible constraints and launches current searches; every result cites supplier data and requires explicit approval for consequential action |
| P0 | Weak conversion closure | Current UI offers discovery, saving, planning, and assisted quote paths, but no paid completion | Traffic and engagement may not produce attributable revenue | One primary CTA per state, full-funnel analytics, assisted fallback, and an authoritative paid or issued outcome reconciled from intent through trip and revenue ledger |
| P0 | No unified order cockpit | Current workspace holds plan and assistance state, not confirmed multi-supplier orders | Retention, service, cross-sell, and trust end at handoff | Normalized TripItem records, confirmations, supplier references, status sync, alerts, cancellation owner, and next action |
| P0 | No production monetization ledger | Revenue paths are documented but no connected payout evidence exists | The team cannot optimize margin, attach rate, or partner value | Every CTA maps to role, partner, payout basis, attribution, disclosure, support cost, refund exposure, and reconciliation status |
| P0 | Reviewed flagship SEO depth exists, but cluster breadth is narrow | At commit `3898fa7`, four guide packets are marked `publish-ready` and their linked articles exceed the 5,000-word repository target; route, airport, neighborhood, seasonal, and service children remain limited or planned | Four deep guides cannot by themselves earn broad non-brand coverage, while undisciplined expansion can create thin or cannibalizing pages | Preserve the flagship quality gate, then publish distinct child intents with source packets, expert review, update windows, internal links, canonicals, exact map state, supply readiness, and a state-true commercial action |
| P0 | Production trust and legal roles are unresolved per vertical | The UI correctly avoids invented claims, but commercial roles are not all contracted | Misleading prices, insurance wording, or unclear seller responsibility can create legal and support risk | A vertical-by-vertical role matrix for publisher, affiliate, agent, merchant, and licensed insurance path, approved before activation |
| P1 | Globe depth stops before a real city planner | Native Earth and decision kernel exist; city-level hotels, activities, transport, and dining are not live | The moat remains visual rather than operational | Semantic zoom from world to region to city, then a high-resolution local map with itinerary and bookable objects |
| P1 | No price prediction, protection, or mature watch product | Saved and watch state exists without supplier history or contracted protection | Secret Flights, Google, Expedia, KAYAK, and Hopper offer stronger reasons to return | Historical price bands, route watches, meaningful alerts, and optional contracted protection with exact terms |
| P1 | No loyalty or membership system | No cross-trip rewards or subscription value is implemented | Repeat users have little compounding benefit | Free account value first, then paid membership only when measurable savings, priority service, or protection benefits exist |
| P1 | No full supplier portal | Adapter and partner-page foundations exist | Supply onboarding and content quality cannot scale operationally | Supplier profile, contract status, product mapping, availability health, content QA, offer performance, and payout reporting |
| P1 | Limited group planning | Account workspace exists without full collaborative permissions | Families and groups still coordinate outside the product | Invite, view, edit, vote, budget share, and approval ownership with privacy boundaries |
| P2 | No native app or full travel-day mode | Mobile web is primary | Push, offline documents, location-aware next actions, and in-trip retention are limited | Progressive web capabilities first, native app only when trip usage and notification value justify it |
| P2 | No immersive hotel or trip preview | 3D Earth exists, not licensed room or destination walkthroughs | The long-term vision is not yet differentiated at detail level | Licensed imagery or 3D content, accessible fallback, source rights, performance budget, and no implied inventory truth |
| P2 | No white-label B2B distribution | Supplier concepts exist, not a distribution platform | A major enterprise revenue path remains closed | Stable APIs, tenant controls, partner branding, settlement, service levels, and compliance |

SEO audit boundary: commit `3898fa7`, checked 2026-07-18. The four reviewed packet files are `content/guides/athens-2026.sources.json`, `content/guides/budapest-2026.sources.json`, `content/guides/prague-2026.sources.json`, and `content/guides/thailand-2026.sources.json`. Their repository status is `publish-ready`; that status does not prove live publication, indexation, rankings, traffic, or conversion.

## Defensible product opportunities

### 1. The Israeli complete-trip graph

No reviewed competitor clearly presents the full cost and operational reality of an outbound Israeli trip in one decision object. Tra-Vel should normalize:

- departure airport and access cost;
- direct, protected-connection, and separate-ticket routes;
- bags, seats, change exposure, and airport changes;
- hotel room composition and full-stay taxes;
- airport and intercity transport;
- activities, dining constraints, and local mobility;
- insurance relevance without unlicensed recommendation;
- eSIM, currency, equipment, and essential add-ons;
- time cost, overnight connections, and recovery risk;
- whole-party total, per-person reference, and unpriced items.

This is more defensible than a lowest-fare badge because it requires canonical places, connected supplier data, Israeli traveler knowledge, cost normalization, and explainable recommendation logic.

### 2. A two-resolution spatial product

The 3D Earth should answer "where could I go and how does the route work?" A regional or city map should answer "where should I stay, what should I do, and how does each day connect?"

The map contract must remain:

- one selected object at a time;
- clustered and prioritized markers;
- no large result cards over the Earth;
- desktop comparison beside or below the map;
- one mobile support sheet with defined snap points;
- a list and keyboard alternative;
- current prices only when source, scope, currency, and retrieval time are reproducible;
- animation only for actual request and state progress, with reduced-motion equivalence.

### 3. Three proposals, not three hundred results

The AI should build three distinct, editable proposals:

1. Best value.
2. Lowest friction.
3. Most flexible or most memorable, based on stated intent.

Each proposal needs:

- why it fits;
- what the traveler gives up;
- complete priced and unpriced ledger;
- route and day plan;
- current source and freshness;
- cancellation or connection exposure;
- one next action.

The user must still be able to open the full comparison. The short list is a decision accelerator, not hidden ranking.

### 4. Hebrew AI with visible control

The AI moat is not fluent text. It is controlled execution:

- accept speech or text;
- show what the system understood;
- ask only questions that change feasibility, price, safety, or a major recommendation;
- convert answers into visible chips and filters;
- keep supplier results separate from editorial facts and AI inference;
- retain an editable plan;
- prepare carts or supplier requests where supported;
- pause before payment, cancellation, data transmission, or insurance action;
- record approval, supplier response, and resulting state.

### 5. Content that opens the exact decision state

Every flagship guide should own a real search intent and open an exact map or comparison state. Examples include:

- Tel Aviv to Bangkok route options;
- direct versus Dubai connection to Thailand;
- Phuket family areas with travel time and room composition;
- kosher travel evidence by destination and interpretation;
- family trip budgets by school-holiday window;
- airport, baggage, connection, and transfer guides;
- insurance consideration pages with approved boundaries;
- seasonal destination alternatives when the obvious destination is poor value.

Google's official guidance prioritizes helpful, original, sourced, people-first content and explicitly says it has no preferred word count. Five thousand words are justified only when the page owns that depth. Automated location permutations without original value are a risk, not a moat. Source: [Google people-first content guidance](https://developers.google.com/search/docs/fundamentals/creating-helpful-content).

## Fast-decision user journey

### The journey contract

| Stage | User question | Required UI | Primary CTA | Conversion event | Failure fallback |
| --- | --- | --- | --- | --- | --- |
| 1. Entry | "Can you help with my kind of trip?" | Destination, budget, or natural-language entry with one dominant action | Plan my trip | `intent_started` | Popular intents and an accessible standard search |
| 2. Understanding | "Did you understand me?" | Editable summary of travelers, dates, budget, constraints, and flexibility | Use these preferences | `intent_confirmed` | Ask one material clarification, not a long form |
| 3. Shortlist | "What should I choose?" | Three proposals tied to the map, total cost, fit, trade-off, and freshness | Compare this plan | `proposal_opened` | Full results list and human-assistance path |
| 4. Decision | "Is this really available at this price?" | Component detail, seller, terms, inclusions, risk, and revalidation state | Check live price | `offer_revalidation_started` | Preserve the plan and show the nearest valid alternative |
| 5A. External-seller commitment | "Where am I paying, and will my plan survive the handoff?" | Revalidated amount, named seller, seller terms, support owner, data-sharing consent, and return path | Continue to [named seller] | `handoff_started` with a unique server-issued handoff ID | Assisted quote or another current seller, with plan state preserved |
| 5B. Tra-Vel-owned commitment | "What will Tra-Vel charge, and under what terms?" | Only for an approved merchant path: final amount, merchant of record, payment method, cancellation, support, and explicit order review | Review and pay [amount] | `payment_intent_started` from the server | Keep the order unpaid, preserve state, and offer safe retry or assistance |
| 6. Authoritative outcome | "Was payment accepted and was the product issued?" | Supplier postback or payment and issuance state from the system that owns the transaction; otherwise an explicit pending or failed state | View my trip | `booking_revenue_confirmed` only after paid or issued outcome and reconciliation | Recovery instructions and human support, never a false success state |
| 7. Cockpit | "What do I need now?" | Orders, documents, next action, alerts, and editable plan | Complete next action | `trip_action_completed` | Supplier contact and synchronized support case |

### Entry-specific CTAs

CTA language must describe the next real action. Avoid vague labels such as "Learn more" and avoid "Book" before a live payable offer exists.

| Intent | Primary CTA concept | Secondary CTA concept |
| --- | --- | --- |
| Known destination | Compare the complete trip | Open destination guide |
| Budget first | Show where my budget works | Adjust flexibility |
| Open to surprise | Build my surprise trip | Set one non-negotiable |
| Flight shopper | Compare real flight costs | Include hotel and transfers |
| Hotel shopper | Compare full-stay prices | See neighborhoods on map |
| SEO guide visitor | Open this route on the map | Save this plan |
| Returning watcher | Review the price change | Keep watching |
| AI plan ready | Compare the three proposals | Change the request |
| Live offer selected | Check current price | Review terms |
| Supplier handoff ready | Continue to the named seller | Get assisted help |
| Assistance eligible | Send this plan for review | Keep editing |

### Conversion rules

1. One dominant CTA per state, with no more than one adjacent secondary action.
2. The primary CTA label changes as truth changes: plan, compare, revalidate, continue, confirm, manage.
3. Preserve traveler intent through every page and supplier handoff.
4. Never ask again for data already confirmed unless the supplier requires a different format.
5. Display whole-party total first for families, then a clearly labeled per-person reference.
6. Show price timestamp, scope, currency, taxes, bags, occupancy, cancellation, and seller before commitment.
7. Show savings only against an equivalent, identified comparator.
8. Use scarcity only from a current supplier field and display its source context.
9. Use positive animation only after a server-confirmed step or state improvement.
10. If live search fails, keep the plan, explain the failure, and offer retry or assisted continuation.

### Checkout ownership and revenue truth

- **External-seller handoff:** the named seller owns checkout, payment, issuance, cancellation, and first-line transaction support. Tra-Vel must preserve the plan, pass only consented data, issue a unique handoff identifier, accept an authoritative supplier postback or report, and reconcile the result. The CTA says "Continue to [seller]", never "Pay Tra-Vel".
- **Tra-Vel-owned checkout:** this path may appear only when Tra-Vel's merchant or agency role, payment and 3DS flow, fraud controls, idempotency, refund process, data handling, support, and supplier issuance are approved for that vertical. The CTA states the amount and payment action.
- **Assisted conversion:** a quote request or case is a lead, not a booking. It becomes revenue-confirmed only when a deposit or order is paid, linked to the case and trip, and reconciled to the operator or supplier record.
- A click, handoff, quote, case creation, payment-intent creation, or client-side success screen never qualifies as a confirmed booking or confirmed revenue by itself.

### Decision-speed service targets

These are internal targets to validate, not public promises:

- cached discovery should show useful destination direction within 3 seconds at the 75th percentile;
- a connected supplier shortlist should resolve within 8 seconds at the 75th percentile, with partial results and honest status when one source is slower;
- a traveler should be able to reach a three-proposal shortlist in under 60 seconds without completing a long form;
- a returning traveler should be able to revalidate a saved offer in two intentional actions;
- mobile controls should meet the 44 by 44 pixel target and keep the map usable;
- no false price, false inventory, false saving, false progress, or false booking state is acceptable.

## Monetization architecture

The product should maximize value per completed trip, not clicks per page. Each revenue surface needs a recorded commercial role and a traveler benefit.

The operating unit is net contribution, not gross booking value or expected commission:

`Net contribution per revenue-confirmed trip = confirmed commission + retained markup + confirmed service or subscription revenue + allocated B2B revenue - paid acquisition cost - payment and FX fees - supplier and fulfillment cost - refunds and partner clawbacks - support and change-handling cost - fraud and chargeback loss - non-recoverable indirect taxes`

Every term is recorded at trip and partner level in a common reporting currency with the FX source and timestamp. Unknown costs remain unknown and block a positive-contribution claim. Expected commission can support forecasting, but only reconciled revenue enters this formula.

| Revenue line | Traveler value | Commercial model | Required control | Best funnel position |
| --- | --- | --- | --- | --- |
| Flights | Current options and route trade-offs | Affiliate, agency commission, or ticketing margin under contract | Fare, baggage, taxes, ticketing responsibility, postback, refund exposure | Core proposal and route comparison |
| Hotels | Neighborhood fit and full-stay comparison | Affiliate commission, agency commission, or net-rate margin | Occupancy, taxes, cancellation, payment timing, seller, revalidation | Proposal, city map, and guide modules |
| Packages | Lower effort and possible combined value | Package commission or contracted markup | Comparable bundle basis, component ownership, protection status, total price | After flight and stay intent are known |
| Activities | Bookable itinerary and reduced planning work | Affiliate or agency commission | Availability, ticket terms, meeting point, cancellation, supplier support | Day plan and destination guide |
| Transfers and cars | Operational certainty from airport to stay | Affiliate or agency commission | Flight linkage, pickup instructions, fuel and insurance terms, total fees | After arrival airport and hotel are selected |
| Travel insurance | Financial protection appropriate to trip context | Licensed referral or sale under an approved Israeli structure | No unlicensed recommendation, approved wording, disclosures, consent, insurer ownership | After dates, travelers, and activities are known |
| eSIM, currency, airport, and equipment | Readiness and convenience | Affiliate commission or retail margin | Compatibility, delivery, refund terms, sponsored disclosure | Pre-departure checklist and cockpit |
| Concierge and assisted planning | Human review for complex trips | Fixed fee, deposit, or qualified lead model | Scope, response time, refund policy, privacy, operator capacity | When automation cannot safely close the trip |
| Membership | Better alerts, support, or contracted benefits | Subscription | Benefits must be real, measurable, and usable before charging | After first saved trip or confirmed value event |
| Sponsored placement | Supplier exposure | CPC, CPA, tenancy, or campaign fee | Explicit label, separate ranking logic, frequency cap, performance reporting | Contextual result or content module, never hidden in organic rank |
| Supplier tools | Distribution, lead quality, analytics, and operations | Subscription, transaction fee, or commission | Contract, access control, content QA, settlement, service level | Supplier portal |
| White-label platform | Travel commerce for another brand | Platform fee plus transaction economics | Tenant separation, API stability, SLA, compliance, reconciliation | P2 enterprise product |

### P0 monetization priority and stop rules

The order below reflects dependency and decision value, not assumed commission rate. Commercial rates, conversion, cancellation, and support cost are unknown until contracts and production data exist.

| Order | Revenue surface | Why now | Required evidence before scaling | Pause or kill criterion |
| ---: | --- | --- | --- | --- |
| 1 | Flight plus stay, or a comparable package | Delivers the core complete-trip promise and creates the denominator for attachments | Israel-eligible terms, reproducible all-in price, named seller, paid outcome postback, refund ownership, and reconciled contribution | No territorial right, no reproducible total, no outcome attribution, or no clear seller and support owner |
| 2 | Activities, transfers or cars, and eSIM | Adds useful itinerary completion after core intent is known | Current eligibility, explicit terms, unique attribution, eligible-trip denominator, paid outcome, refunds, and incremental contribution | Attachment reduces core completion or trust, cannot be reconciled, or support and clawbacks erase contribution over the predeclared review window |
| 3 | Assisted planning for complex trips | Converts cases automation cannot safely close and creates learning data | Defined scope, response SLA, capacity owner, fixed fee or deposit terms, payment record, refund policy, and case-to-trip reconciliation | SLA repeatedly fails, privacy or operator capacity is unresolved, or confirmed revenue does not cover attributable fulfillment and support cost after the predeclared sample minimum |
| 4 | Travel insurance | Can add protection only after trip facts are known | Approved Israeli legal role, licensed partner, exact wording, consent, eligibility, policy documents, and complaint escalation | Hard stop if role, wording, eligibility, or licensed ownership is not approved; never compensate by hiding the disclosure |
| 5 | Membership | Potential repeat value, but only after useful free behavior exists | Measurable funded benefits, clear renewal and cancellation, cohort retention, and contribution after benefit cost and support | Do not charge in P0; stop if benefits are not used or cannot be valued without obscuring price |
| 6 | Sponsored placement, supplier tools, and white label | Later distribution and enterprise revenue | Independent organic ranking, explicit label, contracts, tenancy, reporting, settlement, and service levels | Do not launch in P0 if sponsorship changes recommendation truth or if B2B delivery distracts from the proven traveler funnel |

Before any rate-based target is approved, the owner must record the baseline period, minimum decision sample, contribution threshold, trust guardrails, and stop authority. Without those inputs, this document sets gates and formulas, not fabricated margin or conversion targets.

Viator's official partner terms currently advertise an 8 percent commission on completed experience bookings and a 30-day affiliate cookie, but any Tra-Vel implementation remains subject to qualification, territory, and the signed agreement. Source: [Viator partner commission](https://partnerhelp.viator.com/en/articles/69-how-much-will-i-receive-in-commission-for-bookings-made-from-my-referrals).

### Revenue-truth ledger

No monetized CTA should ship without these fields:

- product and partner;
- Tra-Vel's legal and commercial role;
- payout basis and attribution window;
- gross booking value and currency;
- expected and confirmed revenue;
- paid acquisition cost and source allocation;
- payment, FX, supplier, fulfillment, and non-recoverable tax cost;
- cancellation or clawback state;
- refund, fraud, and chargeback loss;
- support and change-handling owner and attributable cost;
- trip-level net contribution and reconciliation date;
- disclosure shown to the traveler;
- click, handoff, booking, fulfillment, and reconciliation identifiers.

This ledger prevents the product from confusing engagement with revenue.

## Supplier and data requirements

### Minimum viable production supply

P0 is not complete until Tra-Vel has:

1. A flight provider or qualified affiliate path that covers Israeli departures and returns bookable offers with fare family, baggage, taxes, connection structure, and deep handoff.
2. An accommodation provider that returns coordinates, imagery rights, room occupancy, full-stay price, taxes, cancellation, payment timing, and deep handoff or booking.
3. An activities provider with content, price, availability, cancellation, and trackable booking links.
4. A transfer or car provider that can connect arrival details to pickup.
5. An approved Israeli travel-insurance path with exact regulatory role and wording.
6. Map, places, weather, currency, airport, entry-rule, and travel-warning sources with licensing and review rules.

### Offer contract

Every offer needs:

- canonical Tra-Vel place, airport, route, property, and product identifiers;
- supplier and seller identity;
- source retrieval time and expiry;
- base price, taxes, mandatory fees, optional extras, and currency;
- party, occupancy, room, and date scope;
- baggage, meals, transfers, and other inclusions;
- cancellation, change, payment, and refund terms;
- live, cached, stale, historical, editorial, or unknown data mode;
- checkout or handoff URL generated on the server;
- revalidation method and price-change response;
- attribution, booking postback, and reconciliation state.

Google's own hotel policies require accurate, bookable prices, full mandatory taxes and fees, and a consistent landing and booking experience. Tra-Vel should treat that as a minimum internal standard for every commercial vertical, not only Google distribution. Source: [Google Hotel price accuracy policy](https://support.google.com/hotelprices/answer/6064419?hl=en).

## SEO and AEO execution plan

### Content hierarchy

The initial crawlable graph should be:

- continent and country hubs;
- city, island, and region decision pages;
- airport pages;
- origin-to-destination route pages;
- neighborhood and where-to-stay pages;
- trip-style pages for family, kosher, accessible, budget, luxury, solo, and couples;
- season and Israeli school-holiday pages;
- flight, baggage, connection, transfer, insurance, and trip-readiness guides;
- deal pages isolated from evergreen guides;
- comparison pages only when methodology, equivalence, and freshness are visible.

### Minimum indexed-page contract

Every indexed page needs:

- one primary intent and one canonical owner;
- decision-first answer near the top;
- original, reviewed Hebrew content appropriate to the topic depth;
- author, reviewer, methodology, sources, last checked, and next review date;
- exact map state or useful static fallback;
- contextual commercial modules whose data mode is visible;
- internal links to parent, child, route, service, and alternative pages;
- BreadcrumbList and only other structured data supported by visible qualifying content;
- no indexable empty filters, search states, or arbitrary parameter combinations;
- no fabricated prices, availability, ratings, FAQs, or review markup.

### Search opportunity sequence

1. Win long-tail decision queries where Israeli context materially changes the answer.
2. Build route, airport, family, kosher, seasonal, and total-budget authority around flagship destinations.
3. Use real search and conversion data to expand into competitive generic terms.
4. Create category pages only after enough strong child pages and supply exist.
5. Separate expiring deals from evergreen planning so stale prices do not damage trust.

The first keyword program should be driven by Search Console, Google Ads Keyword Planner, Trends, and a repeatable Israel SERP sample. It should map query, intent, current owner, desired page, funnel stage, supply readiness, content evidence, and revenue path. This document does not invent keyword volume.

## Prioritized roadmap

### P0: earn the right to sell

P0 must produce a measurable, truthful path from intent to revenue.

1. **Commercial role and supplier selection**
   - Approve the role for flights, stays, packages, activities, transfers, cars, and insurance.
   - Sign at least flight, hotel, and activity paths.
   - Record territorial availability, caching rights, attribution, payout, refund exposure, and support ownership.

2. **Live offer normalization and revalidation**
   - Implement production adapters behind the existing server-side registry.
   - Add canonical IDs, price components, policies, freshness, health, and stale behavior.
   - Prove that a displayed price can be reproduced for the same dates, party, and inclusions.

3. **Complete-trip cost ledger**
   - Combine flight, bags, hotel, taxes, transfers, and known essentials.
   - Display party total first and keep missing costs explicit.
   - Prevent savings without an equivalent comparator.

4. **Fast shortlist and CTA state machine**
   - Support destination-first, budget-first, and natural-language entry.
   - Produce three explainable proposals.
   - Use one real primary CTA per state.
   - Preserve intent and analytics through search, revalidation, handoff, or assistance.

5. **Conversion closure**
   - Implement a named-seller deep handoff with an authoritative paid or issued booking postback, report, or reconciliation feed; if a supplier cannot return an outcome, it cannot satisfy the P0 revenue exit gate.
   - For an approved Tra-Vel-owned path, reconcile payment, supplier issuance, refund, and order state by stable identifiers.
   - Add price-change, unavailable, payment, pending, confirmation, and recovery states.
   - Keep assisted quote as a structured fallback, not a dead-end contact form.

6. **Account and post-booking foundation**
   - Finish durable multi-device trip state.
   - Normalize confirmed TripItems and supplier references.
   - Show who supports each item and the next real action.

7. **Trust, accessibility, performance, and legal gate**
   - Complete seller, affiliate, sponsor, insurance, privacy, consent, and accessibility review.
   - Validate mobile map usability, keyboard alternatives, reduced motion, and no overlap.
   - Set page and API performance budgets.

8. **SEO commercial nucleus**
   - Publish a small number of flagship route and destination clusters at the full indexed-page standard.
   - Connect each guide to an exact map state, live search, and relevant product action.
   - Measure organic entry to saved plan, live check, handoff, and booking.

P0 exit criteria:

- real current flight and stay offers can be compared for at least one flagship trip;
- full cost scope and missing items are visible;
- at least one end-to-end flagship journey reaches a supplier-confirmed paid or issued booking, or a paid assisted deposit or order, and the outcome reconciles to the trip, partner, revenue, refunds or clawbacks, and attributable costs;
- a click, handoff, quote request, case, or unreconciled expected commission does not satisfy the revenue exit gate;
- the seller and support owner are clear;
- confirmed trips enter the cockpit;
- all material funnel events reconcile by stable identifiers from qualified intent to authoritative booking and revenue outcome;
- no demo state can be mistaken for live commerce.

### P1: become the best Israeli decision product

1. Add the full world, region, city, and street semantic-zoom model.
2. Connect AI conversation to live result refinement and three-proposal generation.
3. Launch the budget and vibe-driven "surprise me" journey.
4. Add route watches, historical price bands, and useful price-change alerts.
5. Add activities, transfers, cars, eSIM, dining evidence, and equipment into the day plan.
6. Add collaborative family and group planning.
7. Launch a supplier portal with offer quality and conversion reporting.
8. Add a free loyalty wallet, then test paid membership only after benefits are proven.
9. Expand the reviewed SEO graph based on demand and conversion evidence.
10. Add proactive cockpit alerts and human-approved change requests.

P1 exit criteria:

- a user can start with no destination and receive current, explainable, editable proposals;
- city planning connects places, time, cost, and bookable activities;
- price watches generate meaningful return visits;
- attached products increase trip value without reducing trust or completion;
- suppliers can see product health and conversion outcomes;
- the account retains users across planning, booking, and travel.

### P2: build the platform moat

1. Add native or installable travel-day experiences with offline documents and push alerts.
2. Add contracted price protection or disruption products.
3. Add agent-assisted change and cancellation with explicit approvals.
4. Add licensed 3D destination, hotel, or room previews where they materially improve decision quality.
5. Add a B2B white-label API and tenant platform.
6. Add advanced loyalty, benefits, and supplier-funded offers.
7. Add direct checkout only for verticals where Tra-Vel's legal, operational, payment, fraud, support, and refund capabilities are mature.

## Measurement framework

### North-star metric

`Trusted revenue-confirmed trips per 100 qualified planning sessions`

Formula: `100 x distinct attributed trusted revenue-confirmed trip_id / distinct eligible qualified planning_session_id`.

This measures the product's stated goal: moving a sufficiently specified human intent to a payable, attributable, trustworthy outcome. It is not satisfied by clicks, handoffs, leads, payment intents, expected commission, or client-side confirmation.

### Operating definitions

- **Planning session:** one server-issued `planning_session_id`, closed after 30 minutes of inactivity. Authenticated and anonymous sessions use the same rule.
- **Qualified planning session:** a human, non-staff, non-test session with one `intent_confirmed` event that contains an Israel origin or approved alternative, party composition, a travel-date window or explicit flexibility, and either a destination or explicit open-destination intent. Repeated confirmations in the same session do not create another denominator.
- **Trusted revenue-confirmed trip:** one stable `trip_id` with at least one core flight, stay, or package paid or issued by the authoritative seller, or a paid assisted order or deposit; a named seller and support owner; a linked partner or payment outcome; confirmed revenue in the ledger; and no unresolved material price, scope, consent, or identity mismatch. Attachments alone do not create a trip.
- **Attribution:** a trip is assigned once to the most recent eligible qualified planning session before its first commercial handoff, within 30 days. A trip cannot be attributed to multiple sessions or channels. Reports remain provisional until the applicable partner refund and clawback window closes, then cohorts are restated.
- **Reporting:** event timestamps are stored in UTC and operating reports use `Asia/Jerusalem`. Product decisions use complete rolling 28-day cohorts; speed is monitored daily. Bots, uptime probes, staff, seeded demos, and QA transactions are excluded by recorded flags, never by ad hoc filtering.

### Exact KPI dictionary

| Metric | Numerator or calculation | Denominator and eligibility | Window | Dedupe | Authoritative source | Guardrail or decision use |
| --- | --- | --- | --- | --- | --- | --- |
| Trusted trip conversion, primary KPI | Count of trusted revenue-confirmed `trip_id` values attributed to eligible sessions | Count of qualified `planning_session_id` values; report per 100 sessions | Rolling 28-day session cohorts with 30-day booking attribution; restate after clawback window | One trip to one session; one qualified denominator per session | Server intent events, handoff registry, supplier or gateway outcome, revenue ledger | Never improve by increasing mismatch, complaints, refunds, or negative contribution |
| Intent confirmation rate | Qualified planning sessions | Human sessions with at least one `intent_started` event | Same 30-minute session; rolling 28 days | One numerator and denominator per session | Server event store | Diagnose entry copy and clarification friction; do not remove material consent or constraints to lift it |
| Primary CTA engagement by state | Sessions with a server-linked click on the rendered state's primary CTA | Sessions with that eligible state and primary CTA successfully rendered | Same session; rolling 28 days | One click per session, state, and CTA version | Client render and click joined to server state ID | A click is diagnostic, never a revenue proxy; false urgency is prohibited |
| Valid shortlist rate | Production searches returning at least one revalidated offer with seller, scope, timestamp, and payable-price components | Qualified sessions that start a production supplier search | Same session; rolling 28 days | One result per `search_request_id`; session counts once | Supplier adapter logs and offer store | Partial and no-result states stay visible; demo or stale offers are excluded |
| Time to first useful result | P50, P75, and P95 seconds from `intent_confirmed_at` to first rendered source-backed proposal or current offer that exposes its data mode | Qualified sessions that request a result; failures reported separately, not dropped | Same session; daily and rolling 28 days | Earliest valid result per session | Server events joined to render acknowledgement | Pair with valid-shortlist and error rates so speed cannot be bought with stale or incomplete results |
| Time to payable decision | P50, P75, and P95 seconds from `intent_confirmed_at` to successful revalidation of the first selected payable offer | Qualified sessions reaching `offer_selected`; report reach rate beside duration | Within session; rolling 28 days | First successful revalidation per session | Server event store and supplier adapter | Do not exclude slow failures; publish the reach rate and no-result rate with the percentile |
| Time to pay | P50, P75, and P95 duration from `intent_confirmed_at` to authoritative paid or issued outcome | Trusted revenue-confirmed trips attributed to qualified sessions | Maximum 30-day attribution; rolling 28-day cohorts | One duration per trip | Intent events, handoff or payment records, supplier outcome | Pair with trusted trip conversion so the metric cannot improve by measuring only fastest buyers |
| External handoff-to-confirmed-booking rate | Unique handoffs with supplier-confirmed paid or issued core booking | Unique named-seller handoffs contractually eligible for an outcome feed | 30 days from handoff; rolling 28-day handoff cohorts | One outcome per `handoff_id`; multiple supplier status messages collapse to final state | Server handoff registry and supplier postback or reconciled report | Unknown outcomes are not bookings and are shown as an explicit completeness gap |
| Owned payment success rate | Unique orders with gateway-paid state and successful supplier issuance | Unique orders for which the traveler submitted payment after final amount review; technical retries on the same payment intent stay one attempt | Same order, with pending outcomes resolved within the processor SLA; rolling 28 days | One final result per `order_id` and idempotent `payment_intent_id` | Payment gateway webhook, order store, supplier issuance | Report payment failure, issuance failure, duplicate prevention, refunds, fraud, and chargebacks separately |
| Assisted paid conversion rate | Assisted cases with a reconciled paid deposit or order linked to a trip | Cases accepted by an operator as eligible and in scope | 30 days from acceptance; rolling 28-day cohorts | One result per `quote_case_id`; later payments update rather than duplicate | Case store, operator order record, payment or bank reconciliation | Case creation, contact, proposal delivery, and verbal acceptance are not conversions |
| Material price or scope mismatch rate | Revalidation or checkout attempts where mandatory payable total, party or room scope, baggage or inclusions, seller, or cancellation terms differ beyond the predeclared product policy | All eligible revalidation and final-price attempts | Rolling 28 days, segmented by supplier and product | One result per offer-scope digest and attempt ID | Offer store, supplier response, checkout review | Any omitted mandatory fee is a mismatch regardless of amount; stop affected offer path when truth is uncertain |
| Attachment rate by vertical | Trusted core trips with at least one paid or issued attachment in the named vertical | Trusted core trips eligible for that vertical by dates, geography, party, and supplier coverage | 30 days from core booking; rolling 28-day cohorts | One attachment flag per trip and vertical | Trip item store and supplier outcomes | Show core conversion, cancellation, support, and contribution beside attach rate |
| Net contribution per trusted trip | Sum of the trip-level net contribution formula in this document | Trusted revenue-confirmed trips in the cohort | Rolling 28-day attributed cohorts, restated after refunds and clawbacks | One ledger roll-up per trip and currency-normalized line item | Revenue-truth ledger, processor, partner statements, acquisition, and support systems | Unknown costs block a positive claim; report distribution and negative trips, not only the mean |
| Support-contact rate | Trusted trips with at least one traveler-initiated support case about price, payment, issuance, cancellation, or fulfillment | Trusted trips whose travel end date plus 14 days has elapsed | Trip cohort through travel end plus 14 days | One flag per trip and issue category; repeated messages in one case do not multiply trips | Support case system joined to trip items | Segment by seller and failure reason; rising trust-related contact blocks scale |
| Organic guide-to-trusted-trip rate | Trusted trips attributed to a qualified session whose first eligible landing page was an indexed guide from organic non-brand search | Qualified organic sessions landing on an indexed guide | 30-day attribution; rolling 28-day landing cohorts | One trip to one session; one landing classification per session | Server analytics, consented attribution, Search Console landing-query data, revenue ledger | Do not interpret as ranking leadership; monitor indexation, content quality, and assisted versus direct outcomes |

No numeric business target is asserted without baseline data. After one complete, validated 28-day instrumentation cohort and a predeclared minimum decision sample, owners set a target, acceptable variance, trust guardrails, and stop authority for each launched path. Supplier latency service targets in this document remain engineering hypotheses until that baseline exists.

### Instrumentation acceptance

- Revenue and booking events are server-owned and idempotent; client analytics can evidence render and click only.
- `planning_session_id`, `trip_id`, `search_request_id`, `offer_id` plus scope digest, `handoff_id`, `order_id`, `payment_intent_id`, `quote_case_id`, partner booking reference, and ledger line IDs must join without many-to-many inflation.
- Dashboard totals must reconcile to partner statements, gateway settlement, refunds, and the trip ledger before commercial decisions are made.
- Outcome-feed completeness and reconciliation lag are reported beside conversion; missing outcomes are not silently excluded.
- Event schema version, consent state, bot or staff flag, experiment assignment, device class, locale, and UTC timestamp are mandatory analysis fields.

### Required experiment discipline

- Test one material hypothesis at a time.
- Keep seller, price scope, policies, and accessibility constant across variants.
- Optimize for confirmed revenue and trip quality, not only CTA clicks.
- Segment known destination, flexible destination, deal seeker, guide visitor, and returning user.
- Review mobile separately from desktop.
- Predeclare primary metric, sample rule, attribution, decision window, guardrails, and stop authority before exposure.
- Stop any experiment that creates a false state or materially increases price misunderstanding, accidental commitment, trust-related support, refund, or complaint risk; when the baseline is too small to establish a rate, any verified severe incident triggers manual review.

## Product governance: claims Tra-Vel must not make yet

Until the relevant P0 evidence exists, Tra-Vel must not claim:

- "best price" without a defined comparison set and timestamp;
- "live" when the state is demo, editorial, historical, cached beyond policy, or stale;
- "saved amount" without an equivalent comparator;
- "booked" when only a plan, quote request, or handoff exists;
- "the AI is booking for you" when it is only interpreting or preparing;
- "all travel in one click" before product, payment, service, and support paths are operational;
- insurance suitability or coverage outside the approved licensed flow;
- review counts, ratings, scarcity, or availability not returned by an authorized source;
- SEO leadership without a dated, repeatable query and analytics study.

## Final strategic position

Tra-Vel should combine seven proven product patterns into one coherent Israeli experience:

- Google's map and flexible discovery;
- KAYAK and Travelist's comparison behavior;
- Trip.com's grounded planning and itinerary continuity;
- Expedia and Booking.com's commercial completeness and trust;
- Lametayel and Eshet's Hebrew content and local travel understanding;
- Hopper and Secret Flights' alerts, fintech, and recurring value;
- Viator and GetYourGuide's destination-level experience commerce.

The product is above competitors only when these capabilities work as one system. A beautiful Earth, a large content library, an AI conversation, or a catalog of affiliate links alone is not the moat. The moat is a fast, truthful, total-trip decision that can be paid for, serviced, and improved over time.
