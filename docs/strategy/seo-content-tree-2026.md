# Tra-Vel search, content, and revenue architecture

Updated: 2026-07-16

## Product promise

Tra-Vel helps an Israeli traveler discover a destination, understand the complete trip, compare the real tradeoffs, and book the required travel products in one journey. The public site must speak as an established travel service. It must not expose roadmap labels, demo language, internal review states, invented prices, or unsupported savings claims.

## Search architecture

### Competitive evidence refreshed 2026-07-16

- ISSTA's public product range covers flights, hotels, packages, organized trips, cruises, car rental and supporting travel services. Tra-Vel must match that commercial breadth while making tradeoffs easier to understand.
- Gulliver's public homepage combines live-looking deal cards with high-intent landing links for flights, packages, hotels, last minute, charter routes and destination clusters. Tra-Vel should preserve the intent coverage but avoid unsupported prices or generic destination copy.
- Google Search Central states that crawlable discovery depends on real HTML links with `href`, and recommends server-rendered or pre-rendered content because not every bot executes JavaScript. The globe therefore links into permanent destination hubs instead of acting as the only source of destination information.
- Google supports breadcrumb markup for visible hierarchy. Destination hubs use `/destinations/{destination}/` and connect upward to the destination directory, sideways to related decisions and downward to live commercial searches.

### Level 1: commercial hubs

- `/flights/`: cheap flights, direct flights, baggage, stopovers, flexible dates, airport guides
- `/hotels/`: hotels abroad, areas to stay, family hotels, all-inclusive hotels, cancellation terms
- `/packages/`: flight and hotel packages, last-minute holidays, family holidays, holiday-period packages
- `/travel-insurance/`: destination-aware insurance, medical cover, cancellation, sports, baggage, comparisons
- `/travel-map/`: visual destination discovery, route comparison, airports, weather, lodging areas
- `/destinations/`: destination directory by region, season, traveler type, duration, and budget
- `/guides/`: decision guides that support discovery and purchase

### Level 2: destination hubs

Every priority destination receives one canonical hub. The hub connects the map, guide, commercial inventory, and supporting articles.

Example: `/destinations/thailand/`

- Overview and who the destination suits
- Live flight and package search
- Best time to visit and weather by month
- Airports and routes from Israel
- Areas, islands, and cities
- Where to stay
- Trip budgets by traveler type
- Seven, ten, fourteen, and twenty-one day itineraries
- Family, couple, solo, kosher, accessible, and luxury variants
- Attractions and bookable experiences
- Local transport, transfers, rental cars, and ferries
- Travel insurance, eSIM, health, entry, and safety requirements
- Frequently asked questions based on real search demand
- Price alerts and saved-trip actions

Published flagship hubs as of 2026-07-16:

- `/destinations/budapest/`: 5,000+ visible Hebrew words, 16 reviewed sources and three decision tables.
- `/destinations/thailand/`: 5,000+ visible Hebrew words, 18 reviewed sources and three decision tables covering the regional, seasonal and airport-chain decisions required for a long-haul trip.

Published Athens hub:

- `/destinations/athens/`: 5,398 visible Hebrew words, 17 official or first-party sources, six decision tables, ten practical FAQs, and mapped decisions covering airport transport, public transport, neighborhoods, culture, accessibility, entry and Israeli consular support.

The Athens cluster owns the city-break and island-gateway intent. Its first supporting pages are: flights from Tel Aviv to Athens, airport to city or Piraeus, where to stay in Athens, Athens with children, and Athens plus a Greek island. A future Greece family-island comparison owns the separate “which island” intent so it does not compete with the Athens hub.

### Level 3: high-intent landing pages

Each destination hub can support focused pages when the intent is distinct and the page has unique value.

- Flights from Tel Aviv to the destination
- Direct flights versus stopover routes
- Holiday packages to the destination
- Last-minute packages to the destination
- Hotels in each meaningful neighborhood or resort area
- Family hotels, all-inclusive hotels, romantic hotels, and hotels near an attraction
- Month-specific planning pages
- Holiday-period pages for Rosh Hashanah, Sukkot, Hanukkah, Passover, and summer
- Airport transfer and route pages
- Insurance and activity pages relevant to the destination

### Level 4: decision articles

Decision articles answer a narrow question and send the traveler to a destination or commercial page.

- Which area is best for a first visit?
- How many days are needed?
- What is included in the flight fare?
- Is a stopover worth the saving?
- What does a realistic trip budget include?
- Which airport is most convenient?
- What should families book in advance?
- What changes between two nearby destinations?

## Initial keyword and destination queue

This is a prioritization hypothesis, not a volume claim. It must be validated every month with Google Search Console, Google Trends Israel, paid-search query reports, and supplier conversion data.

### Priority 0: revenue foundations

1. דילים לחו״ל
2. חבילות נופש
3. טיסות זולות
4. מלונות בחו״ל
5. ביטוח נסיעות לחו״ל
6. דילים ברגע האחרון
7. חבילות נופש למשפחות
8. טיסה ומלון

### Priority 1: July to October 2026 demand capture

1. Cyprus: Larnaca, Paphos, Limassol
2. Greece: Athens, Crete, Rhodes, Thessaloniki, Corfu
3. City breaks: Budapest, Prague, Vienna, Rome, Barcelona
4. Short-haul value: Tbilisi, Batumi, Bucharest, Sofia
5. Holiday-period packages: Rosh Hashanah and Sukkot
6. Last-minute and flexible-date pages for every connected supplier feed

### Priority 2: winter and long-haul depth

1. Thailand: Bangkok, Phuket, Koh Samui, Krabi, Chiang Mai
2. Dubai and Abu Dhabi
3. Japan: Tokyo, Kyoto, Osaka
4. Ski: Bulgaria, Austria, Italy, France
5. Winter sun and Christmas market clusters

### Priority 3: defensible long-tail library

- Month-by-month destination pages
- Airport and connection guides
- Neighborhood and resort-area comparisons
- Family age-specific guides
- Kosher travel and holiday-period guides
- Accessible travel guides
- eSIM, transfers, lounges, rental cars, and attractions
- Route risk, separate-ticket, baggage, cancellation, and insurance explainers

## Homepage hero queue

The homepage hero should rotate by business priority, not randomly. One primary commercial intent is shown at a time.

1. Current seasonal package campaign with verified live inventory
2. Last-minute departures from Israel
3. Family summer holidays
4. Rosh Hashanah and Sukkot packages
5. Autumn city breaks
6. Thailand winter planning
7. Ski holidays
8. Evergreen map-led discovery when no campaign has adequate inventory

Each hero requires a verified feed, a clear travel period, transparent party composition, a destination landing page, and an alternative discovery action. If live inventory is unavailable, the hero promotes destination discovery rather than a price.

The production queue is stored in `theme/tra-vel-v2/assets/data/home-hero-queue.json`. The homepage selects the highest-priority active campaign on the server, exposes crawlable links, and focuses the matching map destination in the browser. Campaigns with a price claim are rejected until verified supplier inventory is connected.

## Page standard for search and answer engines

Every indexable destination or guide page should contain:

- One clear primary intent and canonical URL
- A concise answer block near the top
- Original decision support, not rewritten supplier copy
- Updated date, named editorial author, named reviewer, and methodology link
- Primary or authoritative sources for factual claims
- Tables for seasons, airports, areas, durations, and tradeoffs
- Map links that open the correct destination and layer
- Internal links up to the hub, sideways to related decisions, and down to commercial actions
- Breadcrumbs and visible hierarchy
- `Article`, `BreadcrumbList`, `Place` or `TouristDestination`, and relevant `ItemList` structured data
- `Product` and `Offer` markup only for live, bookable, time-stamped inventory
- No fabricated ratings, reviews, prices, availability, or savings

Word count follows the decision depth. A flagship guide may exceed 5,000 words, but length is not the objective. Completeness, original evidence, usable structure, and commercial relevance are the objective.

## Monetization system

### Direct transaction revenue

- Flights
- Hotels and vacation rentals
- Flight and hotel packages
- Travel insurance
- Transfers and rental cars
- Activities and attraction tickets

### Affiliate and cross-sell revenue

- Accommodation and car inventory through approved demand partners
- Activities through approved experience partners
- eSIM, airport lounge, transport, and travel-product partners
- Post-booking cross-sell tied to the actual itinerary

### Service revenue

- Human travel-planning assistance
- Complex itinerary and group requests
- Premium support and change handling
- Corporate and community travel

### B2B revenue

- Supplier portal and inventory onboarding
- Commission-based marketplace distribution
- Sponsored destination or property placement with visible disclosure
- Agent workspace, lead routing, and reporting
- White-label map and itinerary tools for travel businesses

## Account and supplier foundation

Traveler accounts should support saved destinations, saved searches, price alerts, itinerary storage, bookings, documents, and preferences. Start with email magic link plus Google and Apple. Add Facebook only if customer demand justifies the operational cost. LinkedIn belongs in supplier or corporate access, not the main traveler login.

Supplier accounts require separate roles, approval, inventory ownership, offer validity, commercial terms, lead or booking reporting, content moderation, and an audit trail. Customer and supplier permissions must never share a generic WordPress role.

## Measurement

- Search impressions and clicks by hub, destination, and intent
- Non-brand versus brand traffic
- Map interaction to destination-detail rate
- Destination detail to live-search rate
- Search to supplier handoff rate
- Revenue and margin per session, destination, and supplier
- Price-alert signup and return rate
- Guide-assisted conversion
- Structured-data validity and indexed-page coverage
- Core Web Vitals and mobile task completion

