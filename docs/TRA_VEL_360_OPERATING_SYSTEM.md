# Tra-Vel 360 Travel Operating System

Status: active product specification  
Owner: Tra-Vel  
Last reviewed: 2026-07-17

## 1. Product promise

Tra-Vel turns an open travel request into a researched, priced, editable, bookable, and monitored trip. The map is the spatial planning surface. The itinerary is the chronological planning surface. The AI agent coordinates both, but suppliers remain the source of truth for availability, price, conditions, and confirmed orders.

The product must answer five questions at every stage:

1. Where can I go?
2. What will the complete trip cost?
3. Why is this option appropriate for me?
4. What still needs a decision or purchase?
5. What changed, and what should I do now?

### Implemented visual-discovery kernel

Theme 1.14.0 implements the first bounded Level 1 kernel for the six supported globe destinations. A tap anywhere inside the rendered Earth returns either a supported destination plan or an explicit unsupported-area continuation. Supported selections organize twelve decision areas and an end-to-end cost scope; unsupported selections retain coordinates without fabricating location, price, availability, or booking state.

Progress motion represents interface state changes only. Editorial coverage has a visually distinct state from live supplier data. Cost amounts remain suppressed without destination-scoped component ownership and an explicit currency. Savings remain suppressed until a server-verified equivalence cohort identifies the comparator, dates, travelers, inclusions, taxes, currency, and retrieval time. This kernel is the contract boundary for future city drill-down and supplier integrations, not a claim that Level 2 or Level 3 execution is complete.

## 2. Product depth model

Each level must work before the next level is presented as operational.

### Level 0: trusted travel knowledge

The system provides crawlable destination, route, neighborhood, airport, insurance, activity, accessibility, kosher, family, and seasonal information. Every volatile fact carries a source and review date. Every commercial statement distinguishes sourced price, indicative example, and unavailable live data.

### Level 1: visual discovery

The Earth supports destination discovery, route comparison, filters, saved places, and total-cost estimates. World view shows a small number of high-value clusters. Regional view reveals cities and airports. City view transitions to a high-resolution map with neighborhoods, hotels, activities, transport, dining, and trip items.

### Level 2: proposal engine

The user describes the trip in natural language. The agent converts it into a structured request, asks only material follow-up questions, searches connected sources, checks constraints, and builds three explainable proposals. Each proposal is editable and has a complete cost ledger.

### Level 3: transactional trip

Connected suppliers return revalidated offers. The user sees price, currency, inclusions, baggage, cancellation terms, supplier, timestamp, and payment responsibility. The agent may prepare a booking but cannot confirm a purchase, cancellation, refund, traveler-data submission, or policy purchase without explicit approval.

### Level 4: live trip cockpit

Confirmed bookings become a chronological and geographic trip. The cockpit stores itinerary items, vouchers, policy documents, payment status, contacts, check-in tasks, transport instructions, alerts, and traveler responsibilities. Flight and supplier webhooks update the trip.

### Level 5: assisted recovery and adaptation

When a disruption occurs, the system identifies affected items, computes alternatives, explains cost and cancellation consequences, and asks for approval. It never silently changes a paid booking.

### Level 6: supplier network

Hotels, agencies, activity operators, transfer providers, insurers, guides, restaurants, and product sellers manage their own inventory, rates, content, fulfillment, commercial terms, and leads in private workspaces.

### Level 7: immersive preview

The trip can generate a visual route story and, when media rights and generation capacity allow, a video preview. Generated scenes must be clearly labeled. A preview is inspiration, not evidence that a room, restaurant, route, or attraction will look exactly the same.

## 3. End-to-end traveler journey

### 3.1 Entry

Entry can begin from Google, a destination guide, a commercial page, the globe, a saved trip, or the agent. The page immediately recognizes the current intent without assuming the traveler is ready to buy.

Possible intents:

- explore without dates
- compare a known destination
- find the cheapest feasible destination
- plan a complex multi-city trip
- continue an existing trip
- respond to a live disruption

### 3.2 One-click surprise journey

The public entry is **תפתיעו אותי**. It is not destination roulette and it is not a long questionnaire. The traveler can type or say one open request such as: "Honeymoon for two, exotic, under $1,000, anywhere. You decide." The system must accept incomplete language, infer low-risk preferences, and ask only questions that materially affect feasibility, safety, eligibility, or price.

The entry supports:

- one click from the homepage, map, destination pages, and future mobile app
- free-language Hebrew, English, or mixed speech
- microphone input with an editable transcript before submission
- budget, vibe, travelers, timing, and hard constraints expressed in any order
- an explicit "I do not care where" state that broadens destination search
- progressive results without forcing the traveler into a form

The agent visibly moves through truthful work states:

1. Understanding the request
2. Checking date and traveler constraints
3. Searching connected flight and ground options
4. Building feasible destination combinations
5. Comparing full-trip cost, travel time, quality, and risk
6. Checking accommodation, transfers, activities, dining, insurance, and required equipment
7. Revalidating the shortlisted supplier offers
8. Sewing the winning components into three editable proposals
9. Waiting for the traveler's approval

Every progress event must come from an actual tool state or deterministic calculation. Decorative animation may visualize the work, but it must never imply that a supplier was searched, a price was found, or a booking was made when that event did not occur.

The proposal is presented as a tailored trip, not a pile of search results. It includes a map route, timeline, complete cost ledger, verified inclusions, unresolved items, supplier timestamps, trade-offs, cancellation exposure, and the reason it represents strong value. "Savings" is shown only when the comparison basis is reproducible.

The agent may prepare carts, holds, reservations, and supplier requests where the integration supports them. It must stop at one consolidated authorization surface before any purchase, cancellation, personal-data submission, or insurance binding. "Everything is ready. Authorize" is a checkout state, not permission to act silently.

Public copy direction:

- Primary CTA: **תפתיעו אותי**
- Supporting line: **ספרו לנו תקציב או אווירה. הסוכן יחפש, ישווה ויתפור לכם חופשה לאישור.**
- Voice prompt example: **"חופשה אקזוטית לזוג עד 1,000 דולר. תחליטו בשבילי."**

This interaction must reuse the same `TripRequest`, proposal, approval, and trip-cockpit contracts on web and in the future application. Immersive hotel and place previews are an optional media layer attached to verified itinerary entities. They never replace real room descriptions, supplier media, accessibility details, or booking terms.

### 3.3 Request understanding

The agent extracts a `TripRequest` with:

- origin airports and acceptable alternatives
- destinations, required stops, and optional stops
- date window and flexibility
- adults, children, ages, rooms, and relationships
- budget and currency
- trip pace and preferred daily load
- baggage, seats, mobility, medical, dietary, kosher, and accessibility needs
- preferred hotel areas, property style, and room constraints
- activity interests and exclusions
- insurance requirements
- transfer, car, rail, ferry, and local transport preferences
- loyalty programs and supplier preferences
- change tolerance and cancellation flexibility
- required products and equipment

The agent displays the interpreted request before searching. The traveler can correct it in plain language or through controls.

### 3.4 Clarification policy

The agent asks a question only when the answer changes feasibility, safety, legal eligibility, price, or a major recommendation. It can defer low-impact preferences.

Examples of blocking questions:

- exact traveler ages for airfare or insurance
- whether separate tickets are acceptable
- whether kosher means certified food, nearby options, or self-catering
- mobility requirements that affect transfer and room selection
- whether the traveler accepts non-refundable inventory

### 3.5 Research and proposal

The proposal engine builds a dependency graph rather than an unordered shopping list.

Example dependency order:

1. international flight window
2. arrival airport and connection risk
3. destination sequence
4. accommodation nights and room occupancy
5. airport and intercity transfers
6. date-sensitive activities
7. restaurants and flexible activities
8. insurance and required extensions
9. connectivity, equipment, and travel products

Each proposal includes:

- route on Earth and local maps
- day-by-day itinerary
- full cost ledger
- assumptions and unpriced items
- total travel time and transfer time
- cancellation and change exposure
- trade-offs against the other proposals
- source freshness and supplier status
- next decisions

### 3.6 Approval and booking

The agent has three tool classes:

1. Read tools: search, retrieve, compare, calculate, and explain.
2. Preparation tools: hold, create a cart, collect traveler details, and prepare checkout when supported.
3. Consequential tools: purchase, cancel, amend, submit personal data, bind insurance, or send a supplier request.

Consequential tools always require a visible approval card showing:

- exact action
- supplier
- traveler or booking affected
- total amount and currency
- cancellation terms
- data being sent
- deadline or price-expiry time

The approval model follows the human-in-the-loop pattern supported by the OpenAI Agents SDK: a run pauses at a protected tool call, preserves state, and resumes only after approval or rejection. See [OpenAI human-in-the-loop guidance](https://openai.github.io/openai-agents-js/guides/human-in-the-loop/).

### 3.7 After booking

Every order creates a normalized `TripItem` and retains the supplier reference. Supplier data remains authoritative. The system records:

- order state
- payment state
- fulfillment state
- cancellation state
- traveler action state
- supplier synchronization time
- source event history

### 3.8 During travel

The cockpit changes from planning mode to execution mode. It shows the next action, not every possible feature. The traveler can ask the agent to change a trip, but the agent first identifies affected orders and terms.

## 4. The Earth and map interaction system

### 4.1 Map principle

The map is a canvas, not a container for cards. Information that requires reading, comparison, editing, checkout, or long scrolling belongs in a coordinated support surface beside or below the map.

### 4.2 Semantic zoom levels

| View | Map objects | Supporting information |
| --- | --- | --- |
| World | country clusters, route arcs, selected origin, a few verified price signals | destination shortlist, date flexibility, budget summary |
| Region | cities, airports, route alternatives, season and risk layers | ranked city cards, route trade-offs, total-cost bands |
| City | neighborhoods, hotels, activities, transport, restaurants | selected-place details, day plan, availability, booking action |
| Street | entrances, walking route, pickup point, accessible path | operational instructions, reservation, voucher, contact |

### 4.3 No-overlap contract

- Only one selected object can open a detail preview on the map.
- World and regional markers use clustering and collision priority.
- A marker label disappears before it obscures another higher-priority label.
- Price markers appear only when the amount is current, scoped, and reproducible.
- Large result cards never float over the globe.
- Search and filters sit outside the globe canvas on desktop.
- Mobile uses one support sheet with defined snap points. It does not use multiple independent floating panels.
- A collapsed mobile sheet leaves enough Earth visible to pan and rotate.
- Map zoom, rotate, reset, and accessibility controls stay reachable and do not move when results change.
- The selected route and selected destination remain visible when the support surface opens.

Mapbox supports clustering that expands as the user zooms. deck.gl provides GPU collision filtering with priority. Cesium supports picking 3D entities. These are the required interaction patterns even if the final renderer changes. Sources: [Mapbox clustering](https://docs.mapbox.com/mapbox-gl-js/example/cluster/), [deck.gl collision filtering](https://deck.gl/docs/api-reference/extensions/collision-filter-extension), [Cesium entity picking](https://cesium.com/learn/cesiumjs-learn/cesiumjs-creating-entities/).

### 4.4 Data layers

The layer system includes:

- airports and direct routes
- live or recently verified flight offers
- route reliability and separate-ticket risk
- hotels and total stay price
- neighborhoods and travel-time isochrones
- activities and time-slot availability
- restaurants, kosher evidence, dietary suitability, and reservation status
- rail, ferry, bus, private transfer, and car options
- weather, season, daylight, and crowd context
- entry requirements and official travel information
- insurance relevance and activity extensions
- traveler-saved places and confirmed trip items
- supplier offers and disclosed sponsored placements

Google Places can provide structured place details, photos, ratings, accessibility, service options, and price level with field-based billing. Photorealistic 3D Tiles can support close destination exploration, subject to terms, attribution, cost, and coverage. Sources: [Google Place Details](https://developers.google.com/maps/documentation/places/web-service/place-details), [Google Map Tiles overview](https://developers.google.com/maps/documentation/tile/overview), [Google Map Tiles policies](https://developers.google.com/maps/documentation/tile/policies).

### 4.5 Selection model

A click performs one action based on zoom:

- cluster click: zoom and expand
- country click: open regional shortlist
- city click: focus city and load decision summary
- place click: select one place and update the support surface
- route click: compare route variants
- booked item click: open operational details in the cockpit

### 4.6 Animation rules

Animation communicates system state:

- route drawing means a route is being constructed
- a moving agent token means supplier searches are in progress
- a completed node means a result was received, not booked
- a locked checkmark means a supplier confirmed an order
- a warning pulse means attention is required

Animation never implies a purchase before confirmation. Reduced-motion preferences disable nonessential movement.

## 5. AI agent architecture

### 5.1 Components

- conversation interface
- request parser
- traveler preference graph
- constraint engine
- itinerary planner
- supplier tool registry
- pricing and currency service
- policy and approval engine
- booking orchestrator
- trip event processor
- notification service
- audit and evaluation service

### 5.2 Core data contracts

`TripRequest` captures traveler intent.  
`TripPlan` contains destinations, days, dependencies, assumptions, and alternatives.  
`Offer` represents supplier inventory with price, terms, freshness, and provenance.  
`Proposal` combines compatible offers and unbooked recommendations.  
`ApprovalRequest` describes one consequential action.  
`Order` records a confirmed supplier action.  
`TripItem` places an order or planned activity in time and space.  
`TripEvent` records supplier, flight, payment, or traveler changes.  
`RecoveryOption` describes a safe response to a disruption.

### 5.3 Agent truth rules

- The language model never invents inventory, price, availability, opening hours, booking reference, insurance coverage, or cancellation rights.
- Every price has supplier, timestamp, currency, scope, and inclusions.
- Every recommendation distinguishes verified constraint from inferred preference.
- Every external side effect has an idempotency key.
- A failed booking never appears as pending success.
- A stale offer is revalidated before payment.
- Separate-ticket risks are explicit.
- Medical, insurance, visa, and safety information links to authoritative sources and carries a review date.

### 5.4 Tool groups

- destination knowledge search
- flight offers and order management
- lodging search, preview, booking, amendment, and cancellation
- activities search and booking
- transfer search and booking
- restaurant search and reservation handoff
- insurance quote, documents, policy purchase, and change
- flight status and alert subscriptions
- weather and disruption intelligence
- currency conversion and fee calculation
- map and route calculation
- trip storage, saved items, and collaboration
- notifications
- supplier messaging

The tool registry must use strict schemas, timeouts, normalized errors, retries only for safe operations, and full tracing. OpenAI's Agents SDK supports typed tools, approvals, sessions, guardrails, and tracing. Sources: [tools](https://openai.github.io/openai-agents-js/guides/tools/), [sessions](https://openai.github.io/openai-agents-js/guides/sessions/), [tracing](https://openai.github.io/openai-agents-js/guides/tracing/).

### 5.5 Evaluation suite

The agent is not production-ready until it passes cases for:

- missing dates
- ambiguous passenger count
- children without ages
- conflicting budget and constraints
- kosher evidence uncertainty
- accessible room and transfer dependencies
- stale price
- supplier timeout
- partial booking failure
- duplicate booking attempt
- separate-ticket connection
- cancellation with penalty
- airline schedule change
- hotel cancellation affecting transfers
- user rejection of a protected action
- unsafe or unsupported request
- Hebrew and English mixed input
- mobile interruption and resume

## 6. Supplier and commerce architecture

### 6.1 Normalized supplier adapter

Every provider maps into the same interface:

1. capabilities
2. search
3. retrieve current offer
4. prepare order
5. create order
6. retrieve order
7. change or cancel when supported
8. webhook verification
9. health and latency
10. commercial attribution

Potential providers must be evaluated by market coverage, contracting, Israel eligibility, agency requirements, merchant-of-record model, support, cancellation liability, cache rules, attribution, commission, and sandbox quality.

### 6.2 Flights

Duffel demonstrates the correct booking sequence: create an offer request, retrieve the selected offer to refresh the total, display conditions, and create an order. Orders expose available change and cancellation actions and synchronize against the airline. Sources: [Duffel flight search](https://duffel.com/docs/api/v2/offer-requests), [order creation](https://duffel.com/docs/api/v2/orders/schema), [offer conditions](https://duffel.com/docs/guides/displaying-offer-and-order-conditions).

Amadeus provides flight, hotel, transfer, experience, market insight, and itinerary APIs, but product and commercial access must be verified for the intended market before selection. Source: [Amadeus API guide](https://developers.amadeus.com/self-service/apis-docs/guides/developer-guides/index.html).

### 6.3 Lodging

Expedia Rapid provides lodging shopping and booking paths. Booking.com Demand and Connectivity products have different roles and partner requirements. A final provider choice requires commercial onboarding.

The selected lodging adapter must support:

- occupancy and child ages
- taxes and fees
- room and bed details
- meal plan
- cancellation terms
- payment timing
- accessibility evidence
- property and room photos with rights
- total stay price
- preview before booking
- reservation retrieval and changes

Sources: [Expedia Rapid lodging](https://developers.expediagroup.com/rapid/lodging), [Booking.com discounts and rates](https://developers.booking.com/demand/docs/accommodations/discounts).

### 6.4 Activities and transfers

Hotelbeds exposes hotel, activities, and transfer API suites. Amadeus also exposes destination experiences and transfer services. Coverage, margins, cancellation paths, vouchers, pickup instructions, and supplier support must be compared before integration. Sources: [Hotelbeds getting started](https://developer.hotelbeds.com/documentation/getting-started/), [Hotelbeds activities booking](https://developer.hotelbeds.com/documentation/activities/booking-api/overview/).

### 6.5 Flight events

Cirium's alert API can push delay, cancellation, departure, arrival, diversion, gate, baggage, and equipment changes to a webhook, subject to a contract plan. This is the pattern required for the trip cockpit. Source: [Cirium Flight Alerts API](https://developer.cirium.com/apis/cirium-sky-api/flight-alerts).

### 6.6 Insurance

Insurance cannot be treated as a generic product upsell. The integration must support eligibility, destination, dates, traveler ages, medical questions, sports and equipment extensions, policy wording, price, purchase, documents, changes, cancellation, claims routing, and licensed distribution responsibilities.

nib documents embedded quote and purchase flows. Sitata documents quoting, policy creation, webhooks, risk alerts, and claims-related capabilities. These are research candidates, not approved Israeli providers. Sources: [nib embedded travel insurance](https://developer.nibtravelinsurance.com/home/getting-started), [nib quote and purchase](https://developer.nibtravelinsurance.com/guides/quote-and-booking-flows/quote-purchase-flows), [Sitata embedded insurance](https://docs.sitata.com/docs/embedded-insurance/introduction/).

## 7. Personal trip cockpit

### 7.1 Main views

- today and next action
- map and route
- itinerary timeline
- bookings and vouchers
- travelers and documents
- costs and payments
- alerts and changes
- conversation with the agent
- shared trip and permissions

### 7.2 Trip health

The cockpit computes:

- missing bookings
- short or risky connections
- schedule conflicts
- transfer gaps
- unconfirmed activities
- document requirements
- insurance gaps
- payment deadlines
- cancellation deadlines
- supplier synchronization age

### 7.3 Change impact

Before a change, the traveler sees all affected items. Moving a flight may affect hotel nights, transfers, activities, insurance dates, and restaurant reservations. The agent produces a change set with new total cost and penalties before asking for approval.

## 8. B2B supplier operating system

### 8.1 Supplier types

- travel agencies and consolidators
- hotels and property managers
- destination management companies
- activity and guide operators
- transfer and transport companies
- restaurants and kosher certifiers
- insurers and licensed agents
- equipment and travel-product sellers
- tourism boards and content partners

### 8.2 Supplier workspace

Each supplier receives:

- organization and user management
- verification and contract status
- products and variants
- inventory calendar
- rates, taxes, fees, restrictions, and promotions
- media and rights metadata
- service areas and map coordinates
- availability and blackout dates
- lead and reservation inbox
- traveler messages
- fulfillment tasks and vouchers
- cancellations, refunds, and disputes
- invoices, commission, and settlement
- performance and conversion analytics
- agent-access permissions
- API keys, webhooks, and integration health

Booking.com Connectivity illustrates the required breadth: rates, availability, reservations, content, photos, messaging, promotions, payments, and performance data. It also expects proactive low-availability and mapping alerts. Sources: [Booking.com Connectivity APIs](https://developers.booking.com/connectivity/docs), [rates and availability](https://developers.booking.com/connectivity/docs/ari), [reservations overview](https://developers.booking.com/connectivity/docs/reservations-api/reservations-overview).

### 8.3 Commercial models

- transaction commission
- disclosed service fee
- supplier subscription
- premium distribution tools
- qualified lead fee
- promoted placement with clear labeling
- affiliate referral
- managed concierge fee
- product margin
- B2B API usage

Revenue cannot determine the default recommendation ranking. Ranking must first satisfy traveler constraints and declared optimization goals. Sponsored results are separate and labeled.

## 9. Price and value system

### 9.1 Price object

Every price stores:

- amount and currency
- per-person, per-night, per-room, or total scope
- traveler and occupancy scope
- taxes and fees
- baggage and extras
- payment timing
- refundable state
- supplier
- retrieved time
- expiry time when known
- source offer ID

### 9.2 Savings rules

Savings display only when a valid comparator exists. The comparator must have equivalent traveler count, dates, room, baggage, cancellation terms, and mandatory fees. If equivalence is incomplete, the UI says `lower headline price` or `lower current total`, not `you saved`.

### 9.3 Deal animation

Urgency animation is allowed only for verifiable events such as a price expiry, limited inventory returned by the supplier, or a newly detected lower equivalent total. Decorative blinking is not allowed.

## 10. SEO and content architecture

### 10.1 Site hierarchy

```text
/
├── destinations/
│   ├── region/
│   ├── country/
│   ├── city/
│   ├── neighborhood/
│   └── island-or-area/
├── flights/
│   ├── route/
│   ├── airline/
│   ├── airport/
│   └── connection-guide/
├── hotels/
│   ├── destination/
│   ├── neighborhood/
│   └── need-state/
├── packages/
├── insurance/
│   ├── destination/
│   ├── traveler-type/
│   └── activity-extension/
├── things-to-do/
├── transport/
├── kosher-travel/
├── family-travel/
├── accessible-travel/
├── travel-products/
└── guides/
```

### 10.2 Destination cluster

Every priority destination can support:

- complete guide
- flights from Israel
- direct versus connecting routes
- airport transfer
- best areas to stay
- hotel intent pages
- itinerary by duration
- month and season pages
- family guide
- kosher guide
- accessibility guide
- budget and total-cost guide
- activities and booking guide
- restaurant guide
- island or multi-city combinations
- insurance guide
- FAQ and problem-solving pages based on real user needs

### 10.3 Indexation rules

- Editorial and stable commercial landing pages may be indexable.
- Personalized results, internal search, saved trips, carts, account, cockpit, and agent sessions are noindex.
- Faceted URLs are noindex or blocked from crawl unless a curated landing page exists.
- Query parameters do not create uncontrolled indexable combinations.
- Canonicals point to the intended stable page.
- XML sitemaps separate destinations, guides, commercial pages, products, and video.
- Breadcrumbs reflect the user hierarchy, not merely folder names.

Google warns that uncontrolled faceted navigation can waste crawl resources and generally does not index URL fragments. Source: [Google faceted navigation guidance](https://developers.google.com/crawling/docs/faceted-navigation).

### 10.4 Structured data

Use only visible, accurate data:

- `Organization`
- `WebSite`
- `BreadcrumbList`
- `Article` for editorial guides
- `Product` and `Offer` only for genuine purchasable products or valid editorial product review cases
- `VacationRental` when the page and inventory meet requirements
- `VideoObject` for a real public video

Do not promise snippets. Google decides whether rich results appear. Sources: [Google supported search features](https://developers.google.com/search/docs/appearance), [breadcrumb guidance](https://developers.google.com/search/docs/appearance/structured-data/breadcrumb), [product structured data](https://developers.google.com/search/docs/appearance/structured-data/product).

### 10.5 Content quality contract

No article is commissioned only to reach a word count. A long article must solve more decisions than a short one. Each priority guide includes author or reviewer identity, source packet, review date, decision tables, original analysis, internal links, and a next action.

Google explicitly recommends useful, reliable, people-first content and says it does not have a preferred word count. Source: [Google people-first content guidance](https://developers.google.com/search/docs/fundamentals/creating-helpful-content).

### 10.6 Keyword research process

The keyword program must use real data from Google Search Console, Google Ads Keyword Planner, Google Trends, site search, support questions, supplier demand, and conversion data. Search result observation identifies intent and competitors but does not provide reliable search volume. Tra-Vel will not invent volume estimates.

Initial intent families for Israel include:

- cheap flights and route combinations
- vacation packages
- destination plus season or month
- destination plus family, kosher, accessibility, nightlife, beach, or honeymoon
- airport transfer and connection questions
- total trip cost
- hotels by area and need
- travel insurance comparison and extensions
- itinerary length
- flight cancellation and disruption recovery

## 11. Mobile product contract

- The first viewport presents one primary task.
- The Earth remains pannable and rotatable with a collapsed support sheet.
- One thumb can reach primary map and agent actions.
- The agent launcher docks to navigation or the support sheet on map pages.
- It does not cover a price marker, checkout button, form control, or bottom navigation.
- Comparison cards scroll in one direction only.
- Tables become labeled cards when horizontal scrolling would hide context.
- Offline access covers confirmed itinerary, vouchers, contacts, and next actions.
- Push notifications deep-link to the affected trip item.
- Authentication interruption returns the traveler to the same trip state.

## 12. Identity, permissions, and privacy

### 12.1 Authentication

Support email link or passkey first, then Google, Apple, Facebook, and LinkedIn only where they reduce friction for the relevant audience. Social identity does not replace traveler identity required by suppliers.

### 12.2 Roles

- traveler
- trip collaborator
- family organizer
- concierge
- agency operator
- supplier administrator
- supplier staff
- finance operator
- content editor
- support operator
- platform administrator

### 12.3 Data controls

- least-privilege tool and user access
- explicit consent for traveler data sent to suppliers
- encrypted personal and booking data
- no payment-card data in model prompts or ordinary logs
- retention rules by data category
- export and deletion workflows
- audit history for consequential actions
- separation between public content, traveler data, and supplier data

## 13. Acceptance test matrix

### Map and layout

- `MAP-001`: At 1440, 1024, 768, 430, 390, and 360 widths, the Earth has no blocking result card over its primary interaction area.
- `MAP-002`: Only one selected map object has an expanded preview.
- `MAP-003`: Dense markers cluster or collision-filter without unreadable overlaps.
- `MAP-004`: Mobile collapsed sheet leaves at least half of the Earth interaction area available.
- `MAP-005`: Opening the agent does not cover map navigation controls.
- `MAP-006`: A destination click updates the support surface and URL state.
- `MAP-007`: Browser back restores the prior selection and zoom state.
- `MAP-008`: Keyboard users can select destinations and operate zoom controls.
- `MAP-009`: Reduced-motion mode removes nonessential route and marker animation.
- `MAP-010`: Every displayed price marker has source and freshness metadata.

### Proposal and agent

- `AGT-001`: Free-language Hebrew input becomes a visible structured request.
- `AGT-002`: Missing child ages trigger a blocking clarification before price search.
- `AGT-003`: Kosher requirements are represented as evidence levels, not a binary unsupported claim.
- `AGT-004`: The agent returns three materially different proposals when inventory permits.
- `AGT-005`: Each proposal explains trade-offs and assumptions.
- `AGT-006`: An unsupported supplier capability is disclosed.
- `AGT-007`: A stale offer is revalidated before checkout.
- `AGT-008`: Purchase and cancellation tools pause for explicit approval.
- `AGT-009`: Rejected approval performs no side effect.
- `AGT-010`: Duplicate submission cannot create a duplicate order.
- `AGT-011`: Agent state resumes after authentication or approval interruption.
- `AGT-012`: Tool timeouts produce a recoverable state and do not invent results.
- `AGT-013`: Every agent recommendation identifies evidence, inference, and live supplier data.
- `AGT-014`: Mixed Hebrew and English destination input is interpreted correctly.
- `AGT-015`: The audit trace connects the conversation, tools, approvals, and resulting orders.
- `AGT-016`: One click opens the surprise journey without a mandatory form.
- `AGT-017`: A voice request produces an editable transcript before it starts a search.
- `AGT-018`: "Anywhere" expands the destination set but retains origin, eligibility, safety, and budget constraints.
- `AGT-019`: Every visible progress state is backed by a tool event or deterministic calculation.
- `AGT-020`: Surprise results include full-trip cost and cannot rank a cheap flight above an infeasible trip.
- `AGT-021`: The agent asks only material blocking questions and defers optional preferences.
- `AGT-022`: The final tailored proposal remains editable before authorization.
- `AGT-023`: A consolidated authorization cannot silently approve multiple undisclosed supplier actions.

### Booking and trip cockpit

- `BKG-001`: Flight checkout shows operating carrier, baggage, total, and conditions.
- `BKG-002`: Hotel checkout shows room, occupancy, taxes, fees, and cancellation terms.
- `BKG-003`: Insurance purchase shows product wording and required disclosures.
- `BKG-004`: Partial booking failure identifies exactly which items succeeded.
- `BKG-005`: A confirmed booking creates one canonical trip item.
- `BKG-006`: Supplier webhook updates are signature-verified and idempotent.
- `BKG-007`: Flight cancellation identifies affected hotel, transfer, and activity items.
- `BKG-008`: A proposed recovery shows total incremental cost before approval.
- `BKG-009`: Offline cockpit exposes vouchers and essential contacts.
- `BKG-010`: Collaborators cannot purchase or cancel without granted permission.

### Supplier workspace

- `SUP-001`: Supplier onboarding records verification and contract state.
- `SUP-002`: Inventory updates cannot cross supplier tenants.
- `SUP-003`: Conflicting restrictions create a visible warning.
- `SUP-004`: Reservation changes update fulfillment tasks.
- `SUP-005`: Media cannot publish without rights metadata.
- `SUP-006`: Sponsored placement is labeled and separated from organic ranking.
- `SUP-007`: Commission and settlement calculations are reproducible.
- `SUP-008`: API failures expose integration health without leaking credentials.
- `SUP-009`: Supplier users have role-specific access.
- `SUP-010`: Every rate and inventory change has an audit record.

### SEO and content

- `SEO-001`: Every indexable page has unique title, H1, canonical, and meaningful body content.
- `SEO-002`: Breadcrumb markup matches visible navigation.
- `SEO-003`: Faceted and personalized URLs do not create an indexable crawl explosion.
- `SEO-004`: Structured data contains only visible and accurate information.
- `SEO-005`: Every priority guide has source, reviewer, and review date.
- `SEO-006`: Long guides pass visible-text word counting, not HTML attribute counting.
- `SEO-007`: Destination clusters link between editorial and commercial intents without cannibalizing one another.
- `SEO-008`: Internal search and trip cockpit are noindex.
- `SEO-009`: Sitemap inclusion matches canonical and indexability state.
- `SEO-010`: Content refresh changes facts and analysis, not only the displayed date.

### Commercial trust

- `REV-001`: Savings appear only against an equivalent comparator.
- `REV-002`: Unverified prices use `check live` language and cannot be booked.
- `REV-003`: Sponsored content is labeled.
- `REV-004`: Recommendation ranking can be explained without commission data.
- `REV-005`: Every transaction exposes supplier and payment responsibility.
- `REV-006`: Refund and cancellation status never imply money returned before confirmation.

## 14. Delivery sequence

### Track A: current WordPress foundation

- complete destination and commercial templates
- repair imagery, overlap, mobile, accessibility, and trust defects
- build crawlable content tree and internal linking
- keep all non-live prices clearly labeled

### Track B: map workspace

- separate Earth canvas from support surface
- implement semantic zoom, clustering, collision priority, selection state, and saved places
- connect verified places, routes, weather, and destination content

### Track C: proposal service

- implement `TripRequest`, `TripPlan`, `Proposal`, and cost ledger
- add agent conversation and approval state
- connect read-only supplier sandboxes
- build eval suite before live tools

### Track D: transactions

- contract one flight and one lodging provider
- complete revalidation, checkout, order, webhook, change, and cancellation paths
- establish payments, support, reconciliation, and legal responsibilities

### Track E: cockpit and events

- normalize confirmed items
- add flight alerts and supplier events
- implement disruption impact and recovery proposals

### Track F: B2B

- launch supplier onboarding and private workspaces
- add direct inventory only after tenant security, audit, settlement, and support are ready

### Track G: Israel domestic tourism

- reuse the same supplier, map, content, itinerary, and cockpit contracts
- add local hotels, attractions, transport, guides, restaurants, and products after the international core is stable

## 15. Definition of real

A feature is real only when its source, behavior, failure state, and commercial responsibility are implemented and tested.

- A real price can be reproduced.
- A real booking returns a supplier order reference.
- A real alert comes from a verified event source.
- A real savings claim has an equivalent baseline.
- A real AI action is traceable and approval-gated.
- A real supplier has a contract and operating workflow.
- A real SEO page helps a traveler complete a decision.

