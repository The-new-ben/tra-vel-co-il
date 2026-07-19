# Israel loyalty, benefits, and local-tourism product blueprint

Research snapshot: 2026-07-19 (Asia/Jerusalem)

Status: architecture and product specification. This document does not make any provider integration, benefit, price, availability, or redemption live.

## Reading contract

- **Verified fact** means a statement supported by a first-party provider, regulator, or government source listed in this document and observed on the research date.
- **Product design** means a proposed Tra-Vel behavior. It must not be shown as a provider fact until implemented and validated.
- **Integration gate** means the feature stays unavailable until a signed commercial relationship, approved API, or other explicit provider-authorized mechanism exists.
- A campaign is never an evergreen fact. Every rate, fee, eligibility rule, inventory promise, and date must be revalidated at use time.

## Product decisions

1. Loyalty and card benefits are modifiers of a travel offer, not travel inventory suppliers. A flight remains a flight offer from its supplier; Fly Card, FlyAll, SKY, Membership Rewards, an issuer coupon, and a Visa network offer are separate possible modifiers.
2. Inventory brand, loyalty program, card product, issuer, payment network, and campaign are separate axes. “Arkia flights”, “use my Matmid points”, “pay with FlyAll value”, and “Visa offer” must never be collapsed into one filter.
3. A user can compare cash, points, cash plus points, card-linked benefit, and earn-for-later scenarios, but only a provider-confirmed redemption quote may change the payable checkout total.
4. A points balance is connected only through a provider-approved authorization flow. Tra-Vel never asks for or stores an issuer or loyalty password, a full card number, a CVV, or an SMS one-time code.
5. Local Israel inventory is a complete commerce graph, not a list of hotels. It includes accommodation, attractions, food, transport, guides, wellness, gear, accessibility, religious requirements, closures, and operational recovery.
6. The 3D Earth is the discovery surface. Selecting Israel transitions into a high-resolution operational map without placing a static poster behind the Earth or requiring an intermediate poster click.

## 1. Current Israeli loyalty and travel-benefit landscape

The following is a verified research baseline, not a set of values to hardcode.

| Product or program | Verified first-party position on 2026-07-19 | Required system interpretation |
| --- | --- | --- |
| EL AL Matmid | EL AL's current club regulations define points, status diamonds, Cash & Points products, and a default points-validity period subject to the current regulations. The rules also distinguish redemption points from status rights. [EL AL Matmid regulations](https://www.elal.com/media/kphn1vnt/hebrew0207.pdf) | Keep `redeemable_points`, `status_currency`, tier, expiry lots, and redemption products separate. Never infer one from another. |
| FLY CARD and FLY CARD PREMIUM | EL AL describes Fly Card as a payment product that accrues Matmid value and supports flight and ancillary redemptions. The 2026 Isracard page publishes multiple distinct card variants, issuer-specific fees, accrual categories, FX terms, campaign windows, and a transition in which Cal-issued Fly Card cards continue to accrue only through a stated transition date. [EL AL Fly Card](https://www.elal.com/join_flycard/) and [Isracard Fly Card 2026](https://www.isracard.co.il/flycard/private) | `FLY_CARD` is not one rule set. Key rules by issuer, exact product variant, card status, effective dates, spend category, customer status, and campaign version. During migration, two issuer versions may legitimately coexist. |
| Cal FlyAll | Cal publishes FlyAll and FlyAll Premium as cash-back style travel programs with different accrual rates and year-based terms. Value is redeemed through the FlyAll travel portal for eligible flights, hotels, and packages. The page also describes migration from Cal-issued Fly Card and states that some advertised benefits require specific card rails, activity, inventory, or redemption channels. [Cal FlyAll](https://www.cal-online.co.il/cards/flyall?ti=1) | Model a currency-like program balance, not Matmid points. Store earn and redeem rules independently. “Available for Arkia” on the FlyAll portal does not create an Arkia loyalty program. |
| MAX SKY | MAX publishes a SKY track whose points are redeemed through MAX Travel. The page identifies a published accrual ratio, different treatment for certain public-sector transactions, first-year card-fee and FX conditions, and a third-party travel-platform operator. [MAX SKY](https://www.max.co.il/benefits/clubbenefits/skymaxexe) | Separate issuer, program, travel merchant, and supplier of record. A points balance and a MAX Travel booking are related but not the same object. |
| MAX Travel | MAX describes a travel site for MAX customers with product-dependent cashback. Current campaign rules distinguish flight, hotel, and package earning, exclude later changes from earning, require authentication and card conditions, and make value available after return. [MAX Travel](https://www.max.co.il/benefits/abroadbenefits/maxtravel) | Cashback may be `pending`, `earned`, `available`, `reversed`, or `expired`. Do not subtract a future, conditional reward from today's payable amount. |
| American Express Membership Rewards | American Express Israel currently supports conversion of Membership Rewards to Matmid, with product-dependent conversion and a dated offer page that requires the customer's own authenticated account. It also states no double promotions. [Amex to EL AL conversion](https://rewards.americanexpress.co.il/benefitsforall/International-Partners/el-al-4136/) | Treat conversion as an irreversible or provider-controlled operation unless the provider explicitly says otherwise. Quote the exact conversion immediately before authorization and retain the source version. |
| Isracard travel benefits | Isracard publishes separately activated travel benefits, including travel insurance and eSIM offers, each with its own product eligibility, activation, end date, inventory, and merchant responsibility. [Isracard travel benefits](https://marketing.isracard.co.il/pages/benefits-lobby/), [travel-insurance benefit](https://marketing.isracard.co.il/pages/insurance-abroad/), and [eSIM example](https://benefits.isracard.co.il/benefitsforall/vacations/esimo_34694/) | Do not present “Isracard customer” as sufficient eligibility. The engine must check exact card, spend threshold, code download, activation, insured party, trip dates, and merchant terms. Insurance remains a licensed insurance flow, not a coupon applied silently. |
| Isracard aviation track | Isracard publishes a separately enrolled annual aviation-points track. Its current page distinguishes eligible card types and conversion ratios, requires registration and an airline member identity before conversion, and identifies a 2026 accrual cycle. [Isracard aviation points](https://benefits.isracard.co.il/dashboard/aviationpagetype/) | Model enrollment cycle, exact eligible card, accrued issuer units, chosen airline program, member-link state, conversion quote, conversion operation, and post-conversion airline points separately. An Isracard card alone does not prove enrollment, balance, conversion value, or bookable EL AL inventory. |
| Visa | Visa Israel publishes travel support and a changing catalogue of merchant offers. Individual offers can depend on issuing country, card tier, registration, inventory, payment credential, and offer dates. [Visa travel support](https://www.visa.co.il/support/consumer/travel-support.html) and [Visa offers example](https://www.visa.co.il/en_il/visa-offers-and-perks/the-bicester-collection/175056) | A Visa logo proves only the payment network. It never proves issuer benefit eligibility. Network offer rules must be evaluated as their own versioned campaign. |
| Israir airline inventory | Israir's official service policy identifies flights operated under code `6H`. Cal's current FlyAll page separately states that the FlyAll travel site can be used for flights including Israir, subject to availability. [Israir seating policy](https://www.israir.co.il/Passengers_Info/Seating_Policy) and [Cal FlyAll](https://www.cal-online.co.il/cards/flyall?ti=1) | Keep `airline=Israir` as an independent inventory identity. The FlyAll relationship is portal inventory scope only: it creates no Israir loyalty program and proves no live availability, price, discount, customer eligibility, or redemption. |
| Israir and Rami Levy club | Israir's official investor site reports signed agreements and 2026 regulatory progress concerning Israir joining the Rami Levy customer-club company. The current Rami Levy club has public rules, but the published material does not yet provide a complete live Israir consumer earn-and-redeem specification. [Israir immediate reports](https://ir.israir.co.il/%D7%93%D7%99%D7%95%D7%95%D7%97%D7%99%D7%9D-%D7%9E%D7%99%D7%99%D7%93%D7%99%D7%99%D7%9D/), [Rami Levy meeting notice](https://www.rami-levy.co.il/he/general-meetings), and [current club rules](https://www.rami-levy.co.il/he/club-rules) | Show no points filter, value, or transfer promise until official consumer rules or a partner contract defines them. Maintain an `announced_not_operational` integration state. |
| Arkia | No current first-party public loyalty-account or co-branded-card rule set was verified in this audit. Arkia has a current first-party booking site, but absence of located terms is not proof that no private or future program exists. [Arkia](https://www.arkia.co.il/) | Keep `airline=Arkia` as an inventory filter. Keep Arkia loyalty connection disabled until first-party program terms and an authorized connection method are verified. FlyAll or another portal being usable for an Arkia ticket is a portal benefit, not Arkia loyalty. |

### The important market lesson

The 2026 Fly Card transition is a concrete reason not to place loyalty logic in page copy or a hardcoded filter. The same brand can have old and new issuer products, overlapping transition periods, different point units, different FX costs, different expiry rules, and campaign-only benefits. The engine must answer “which exact product and which rule version applies to this traveler and this transaction?”

## 2. Canonical benefit-domain model

### 2.1 Core records

| Record | Required fields | Purpose |
| --- | --- | --- |
| `BenefitProgram` | `program_id`, owner, unit name, supported operations, status | Stable identity for Matmid, FlyAll value, SKY points, Membership Rewards, or another program. |
| `CredentialProduct` | issuer, network, product code, tier, residency scope, effective dates | Exact card product. It contains no PAN or CVV. |
| `MemberConnection` | user, program, connection mode, provider subject token, scopes, assurance level, consent, freshness | A revocable link to an account or a clearly labeled customer assertion. |
| `BalanceSnapshot` | program, amount by unit, expiry lots if supplied, observed time, source, assurance | Immutable observation. It is never silently overwritten. |
| `CampaignVersion` | campaign ID and version, provider, source digest, booking and travel windows, inventory limits, status | Immutable campaign rules and evidence. |
| `EligibilityRuleSet` | typed conditions, dependencies, reason codes, effective dates | Machine-evaluable eligibility without hiding the explanation. |
| `BenefitQuote` | offer snapshot, verified inputs, eligible benefits, cash effect, points effect, expiry, provider quote ID | A short-lived comparison attached to one revalidated travel offer. |
| `CombinationRule` | two benefit IDs, allowed state, ordering, cap, explanation | Explicit stacking rules. Missing means `unknown`, never “allowed”. |
| `RedemptionIntent` | selected benefit quote, user authorization, idempotency key, step-up result | Records exactly what the traveler approved. |
| `RedemptionOperation` | provider operation ID, state, points debit, cash effect, reconciliation state | Handles success, failure, timeout, reversal, and uncertain outcomes. |
| `EvidenceSource` | canonical URL/API, owner, locale, fetched time, content digest, effective dates, reviewer | Provenance and freshness. |

### 2.2 Campaign condition vocabulary

The eligibility language needs first-class conditions for:

- issuer, payment network, exact card SKU, bank-issued versus non-bank-issued card, and card tier;
- program membership, loyalty tier, current balance, expiry lots, residency, and account standing;
- new customer, existing customer, previous-card lookback, one benefit per national ID, and household limits;
- enrollment window, booking window, travel window, day of week, origin, destination, fare class, room rate, minimum nights, and excluded dates;
- spend threshold, eligible transaction category, excluded government or wallet transactions, monthly activity, and maximum earn cap;
- required activation, downloaded code, authenticated portal, specific booking channel, and required payment card;
- party composition, named traveler requirement, non-transferability, inventory quota, taxes, airport charges, and pay-at-property exclusions;
- cancellation effects, later itinerary changes, refund clawback, no-show, partial use, and reward reversal;
- combinability, use order, minimum retained cash amount, partial redemption, and whether earn is allowed on the post-redemption cash portion.

Rules must be typed expressions, not prose parsed at checkout. Prose and PDFs are evidence used to create a reviewed rule version.

### 2.3 Decision vocabulary

Every evaluation returns one of four states:

- `eligible_verified`: all decisive facts and the rule version were confirmed by an accepted provider source.
- `likely_customer_asserted`: the traveler supplied one or more decisive facts and no provider verification exists.
- `unknown_requires_action`: a balance, product variant, activation, travel condition, or combinability fact is missing.
- `ineligible_verified`: an explicit condition failed, with a user-readable reason and source.

The UI must not turn `unknown` into either eligible or ineligible. It should offer the smallest next action, such as “Connect Matmid”, “Choose your exact card”, “Activate with the issuer”, or “Check again after selecting dates”.

## 3. Source authority, versioning, and freshness

### 3.1 Authority order

For a specific fact, use the highest available authority:

1. signed partner API response tied to the exact member and transaction;
2. provider-authorized API or authenticated provider redirect;
3. current official rules or tariff document;
4. current official product page;
5. provider support confirmation retained with case metadata;
6. customer-uploaded statement or customer assertion;
7. no data.

An affiliate blog, news story, comparison site, search snippet, or old cached marketing page is discovery material only. It cannot authorize a discount or redemption.

### 3.2 Immutable campaign versions

Each source refresh creates a new version with:

```text
campaign_id
version
provider_id
official_source_url
source_content_digest
observed_at_utc
effective_from / effective_to
enrollment_from / enrollment_to
booking_from / booking_to
travel_from / travel_to
inventory_cap and cap_observed_state
ruleset_digest
review_state
supersedes_version
```

Existing orders retain the exact version used. A later update never rewrites a historical quote or consent.

### 3.3 Freshness policy

Suggested maximum ages are product design defaults and should be tightened by each provider contract:

| Fact | Discovery maximum age | Checkout maximum age | Stale behavior |
| --- | --- | --- | --- |
| campaign existence and public terms | 24 hours | revalidate at checkout | Keep the card visible as “checking current terms”; do not apply value. |
| personal balance | 15 minutes | 2 minutes or provider-required limit | Show last checked time; refresh before redemption. |
| inventory and cash price | supplier contract | supplier contract, usually seconds or minutes | Reprice. Never combine a fresh benefit with a stale base offer. |
| points or cash-plus-points quote | provider quote expiry | immediate pre-authorization quote | Expire atomically and request a new quote. |
| FX fee schedule | 24 hours | current tariff or issuer quote | Show unknown FX cost instead of zero. |
| card fee and enrollment offer | 24 hours | current official rule | Exclude from “savings” until verified. |
| local closure, safety, weather, or transport disruption | source-specific near real time | refresh before route confirmation | Mark affected item unavailable or uncertain and link to the official live source. |

When official sources conflict, the engine does not choose the more attractive value. It quarantines the affected rule, records both sources, narrows by exact issuer/product/effective date, and requests review or a live provider quote.

## 4. Connecting balances and card eligibility safely

### 4.1 Supported connection modes

1. **Provider OAuth or partner authorization**: the preferred mode. Use authorization code with PKCE, exact redirect allowlists, signed state and nonce, minimal scopes, short-lived access tokens, rotated refresh tokens, and provider logout/revocation.
2. **Licensed Open Finance provider**: only when Tra-Vel or its contracted intermediary is legally and technically entitled to use the relevant Israeli Open Finance scope. This can validate card/account facts available under that scope. It must not be presented as access to a loyalty balance unless the API actually provides that balance. Bank of Israel describes customer-consented access by supervised parties and current standards for card and account information. [Bank of Israel open banking](https://www.boi.org.il/roles/supervisionregulation/bank-sup/open-banking) and [current regulation index](https://www.boi.org.il/roles/supervisionregulation/bank-sup/open-banking_regulation/)
3. **Provider deep link**: send the traveler to the provider to authenticate, activate, convert, or redeem. Return only with a signed result or ask the traveler to confirm completion. Do not inspect the provider session.
4. **Manual balance**: let the traveler enter a balance and optional expiry. Label it “entered by you”, record the time, and use it only for planning. A live provider quote remains required for redemption.
5. **Statement evidence**: optional last resort. Accept a redacted statement or screenshot, isolate OCR, extract only required fields, let the customer correct them, and delete the source image under a short retention policy. It never authorizes a transaction.

### 4.2 Prohibited collection

Tra-Vel must never request or retain:

- an EL AL, Cal, Isracard, MAX, American Express, Visa, Israir, Arkia, bank, or email password;
- SMS or authenticator one-time codes intended for the provider;
- full PAN, CVV, magnetic-stripe data, PIN, or security answers;
- browser cookies, copied authenticated pages, or screen-scraped sessions;
- more transaction history than the traveler explicitly approved for a stated purpose.

Payment credentials remain with a compliant payment provider. Loyalty tokens and payment tokens are separated by encryption keys, service permissions, and audit trails.

### 4.3 Consent object

A connection grant includes purpose, provider, scopes, issued time, expiry, data retention, refresh permission, booking permission, redemption permission, and revocation endpoint. Read-balance and redeem are separate scopes. A traveler who permitted balance reading has not permitted points conversion or spending.

High-impact actions require a fresh, plain-language confirmation that states:

- program and account;
- points or program value to debit;
- cash payable now and cash payable later;
- taxes, fees, and likely FX treatment;
- what is being booked and its cancellation terms;
- whether the transfer or redemption can be reversed;
- the quote expiry;
- which entity will charge, ticket, or fulfill.

### 4.4 Connection state machine

```text
not_connected
  -> authorization_started
  -> connected_read_only
  -> refresh_required
  -> connected_current
  -> redemption_step_up_required
  -> redemption_authorized
  -> disconnected

Any provider timeout after a debit request
  -> operation_uncertain
  -> provider_reconciliation_required
  -> succeeded | failed | reversed
```

Retries use the same idempotency key. An uncertain operation is never retried as a new redemption.

## 5. Valuation without false certainty

### 5.1 Values the engine must keep separate

- cash payable now;
- cash payable at property or on arrival;
- taxes, mandatory fees, and optional extras;
- points or program value consumed now;
- points or cashback expected later;
- card fees and activation costs;
- estimated FX cost or range;
- cancellation value and flexibility;
- benefit inventory risk and expiry risk.

### 5.2 Redemption value

For the same revalidated itinerary and commercial conditions:

```text
cash_saved = comparable_cash_total
             - redemption_cash_total
             - incremental_redemption_fees

realized_value_per_point = cash_saved / points_debited
```

The calculation is valid only when baggage, fare family, cancellation, room, meal plan, taxes, party, and fulfillment are comparable. If they are not, show the differences instead of a single value-per-point number.

### 5.3 Effective trip cost

```text
effective_cash_outlay = pay_now
                       + mandatory_pay_later
                       + verified_incremental_card_or_redemption_cost
                       + estimated_fx_cost_range
                       - immediate_verified_credit
```

Future points and cashback appear as “expected after the trip”, not as a deduction from cash outlay. An annual card fee appears in a separate card-cost view unless the traveler explicitly asks the planner to evaluate acquiring the card for this trip. Sunk annual fees are not charged to every itinerary.

### 5.4 Ranking

The default rank is not “highest advertised discount”. It is a weighted, explainable result using:

- lowest verified out-of-pocket total;
- value consumed from points;
- cancellation and change flexibility;
- reward expiry avoided;
- connection and transfer risk;
- itinerary quality, duration, baggage, room, and accessibility fit;
- the traveler's stated preference to save cash, preserve points, maximize comfort, or use expiring value.

The comparison must show at least these scenarios when available:

1. pay cash with no benefit;
2. use points or program balance;
3. cash plus points;
4. pay with an eligible card-linked offer;
5. preserve the balance and earn a future reward.

Each row shows why it is available, what must still be verified, and the official source time.

## 6. Customer-facing filters and one-click flow

### 6.1 Filter families

- **Airline inventory**: EL AL, Arkia, Israir, other carriers, direct only, connection rules.
- **My value**: Matmid, FlyAll, SKY, Membership Rewards, issuer coupon, network offer.
- **Payment preference**: lowest cash today, use expiring points, earn most later, avoid FX, use a specific verified card.
- **Benefit certainty**: verified for me, likely based on my input, activation required, connection required.
- **Total product**: flight, hotel, package, local stay, activity, transfer, insurance, connectivity, equipment.

“Show all Arkia flights” can work without a loyalty connection. “Use my Matmid points” requires a Matmid connection or manual planning balance. “Visa offers” requires exact offer and credential eligibility, not simply a Visa checkbox.

### 6.2 Result card contract

Every benefit-aware result must expose:

- payable total and currency;
- included and excluded products;
- points or value used;
- expected future earn, separately;
- activation or connection step;
- expiry and inventory status;
- combination status;
- cancellation effect on both booking and reward;
- `verified`, `entered by you`, or `needs confirmation` label;
- “checked at” time and link to current official terms.

### 6.3 One-click does not mean hidden consent

The short journey is:

1. traveler states intent in free language or selects dates and party;
2. system returns comparable routes and stays;
3. a single “Use my travel value” action opens the provider authorization or selects already connected balances;
4. the system re-ranks the same offers and explains the winner;
5. the traveler approves one exact final proposal;
6. each supplier and benefit operation executes with visible progress and recoverable states.

No long form is required, but no redemption, conversion, insurance purchase, or charge is hidden.

## 7. Local Israel inventory graph

### 7.1 Geography model

Store several geographies without conflating them:

- national, district, municipality, locality, neighborhood, and street address;
- travel regions such as Galilee, Golan, Haifa and Carmel, the coastal plain, Tel Aviv, Jerusalem and the Judean Hills, Dead Sea and Judean Desert, Negev and Arava, and Eilat;
- precise point, entrance points, service polygon, route geometry, parking point, and accessible entrance;
- supplier-defined destination code and government-source identifiers;
- map cell index for clustering, nearby search, and progressive loading.

Travel regions are product navigation, not legal boundaries. The precise address and official jurisdiction remain authoritative.

### 7.2 Accommodation taxonomy

**Hotel accommodation**

- resort, city, business, boutique, spa or wellness, heritage, airport, apartment hotel, suites, and extended stay;
- kibbutz hotel, holiday village, rural guest house, hostel, youth hostel, field school, and pilgrim or religious guest house;
- vacation apartment, serviced apartment, entire home, villa, private room, short rental, farm stay, and eco-lodge;
- campsite, equipped campsite, glamping tent, caravan or RV pitch, cabin, and Nature and Parks Authority night camp.

The Ministry of Tourism maintains an official national hotel and accommodation-facility database for routine and emergency use. That source can verify registration fields when accessible, but does not replace a live supplier's availability, rate, policy, or quality claim. [Ministry of Tourism accommodation database](https://www.gov.il/he/service/update-hotel-accomodation-database)

The Nature and Parks Authority currently describes 18 equipped camping sites and requires confirmed advance reservation for its night camps. It also documents capacity, booking, cancellation, and site-specific operating information. [Official night-camp booking](https://www.parks.org.il/%D7%94%D7%96%D7%9E%D7%A0%D7%95%D7%AA-%D7%9C%D7%97%D7%A0%D7%99%D7%95%D7%A0%D7%99-%D7%9C%D7%99%D7%9C%D7%94/)

**Sellable accommodation data**

- property, building, unit type, exact unit or allocation, room count, and stop-sell state;
- adult and child occupancy, child-age bands, infant policy, extra bed, crib, and connecting rooms;
- bed configuration, floor, view, balcony, kitchen, work area, pet policy, and smoking policy;
- meal plan, dietary handling, kosher evidence, Shabbat services, and holiday operation;
- accessible-unit details and the exact accessible route from parking or transit;
- check-in and checkout, late arrival, minimum nights, blackout, deposit, damage hold, cleaning, local tax, VAT treatment, and pay-later charges;
- rate plan, allocation, release window, cancellation ladder, no-show, early departure, and modification rules;
- protected-space type, route, approximate distance, capacity claim, source, and last property confirmation;
- utility and operational state: water, electricity, air conditioning, pool, spa, elevator, road access, and emergency closure.

“Boutique”, “accessible”, “kosher”, “shelter available”, and “family friendly” are not unverified marketing booleans. Each needs structured evidence and a date.

### 7.3 Activities and attractions

- national park, nature reserve, trail, beach, pool, water sport, dive, ski or seasonal snow, desert activity, and wildlife experience;
- museum, gallery, archaeology, heritage site, religious site, architecture, market, winery, brewery, farm, and food tour;
- amusement, family attraction, workshop, performance, festival, nightlife, guided city tour, wellness, spa, and medical-wellness activity;
- private guide, group guide, driver-guide, audio guide, photographer, and event service.

Sellable fields include dated session, capacity, age and height constraints, fitness and medical constraints, waiver, language, guide license, equipment included, meeting point, duration, weather rule, minimum group, cancellation, last admission, parking, accessibility, protected-space information, and live closure.

Tour guides operating in Israel are licensed by the Ministry of Tourism, and the ministry provides a current system for licensed guides to maintain their records. [Licensed tour-guide data](https://www.gov.il/he/service/tour-guides-data-update) and [license renewal requirements](https://www.gov.il/he/service/tour_guide_license_renewal)

### 7.4 Dining and food

- restaurant, cafe, bakery, bar, market stall, winery meal, hotel meal, chef experience, catering, delivery, and picnic basket;
- cuisine, meal periods, seating type, reservation, waiting list, party capacity, children, high chairs, allergens, vegan and vegetarian capability;
- kosher status as a structured certificate claim: authority, certificate identifier, level or scope exactly as stated, meat/dairy/pareve, valid dates, photographed evidence, and last verification;
- Shabbat and holiday opening, prepayment, fixed menu, cancellation, accessibility, parking, and protected-space information.

The Chief Rabbinate states that a food business may not be represented as kosher without a certificate from an authorized body. Tra-Vel should therefore show the authority and validity evidence, not infer kosher status from reviews or a supplier checkbox. [Chief Rabbinate kosher enforcement](https://www.gov.il/he/departments/units/unit_honaa) and [report a kosher problem](https://www.gov.il/he/service/report_kashrut_problem)

### 7.5 Ground and domestic transport

- domestic flight, intercity rail, bus, light rail, cable car, shuttle, private transfer, licensed taxi, rental car, car share, bicycle, scooter, walking, and accessible transport;
- station, stop, platform or meeting point, schedule, real-time estimate, fare product, booking requirement, baggage, stroller, bicycle and sports-gear rules;
- step-free boarding, lift status, assistance booking, wheelchair space, service animal, companion rule, and accessible vehicle confirmation;
- Shabbat and holiday service window, late-night return, disruption, road closure, parking, EV charging, and transfer buffer.

The National Public Transport Authority's planner covers bus, rail, and light rail and provides real-time updates, while clearly labeling suggested routes as recommendations. Its official GTFS documentation describes nightly planned-service files. [Official public-transport planner](https://route.bus.gov.il/) and [official GTFS documentation](https://www.gov.il/BlobFolder/generalpage/gtfs_general_transit_feed_specifications/he/gtfs%20_documentation_v3.pdf)

### 7.6 Supporting services

- travel insurance, local cancellation protection, vehicle coverage, activity coverage, and incident assistance;
- eSIM, physical SIM, Wi-Fi device, charger, power bank, and connectivity support;
- luggage storage and transfer, stroller, baby equipment, wheelchair or mobility-equipment rental, outdoor gear, diving gear, bike, and camping gear;
- concierge, childcare where lawfully supplied, pet care, grocery delivery, medical appointment coordination, and pharmacy location;
- event tickets, celebration setup, honeymoon services, flowers, photography, and gifts.

Each remains its own supplier item with price, fulfillment, cancellation, evidence, and support owner. It is not silently bundled into accommodation.

## 8. Accessibility, religious needs, and personal fit

### 8.1 Accessibility is a matrix

Required dimensions include:

- step-free arrival from parking and public transport;
- accessible entrance, internal route, lift, room, bathroom, shower, bed transfer clearance, and balcony;
- wheelchair-accessible pool, beach, attraction, restaurant seating, and transport;
- hearing loop, captions, sign-language option, visual alarm, tactile or Braille information, quiet or sensory-aware service, and cognitive wayfinding;
- service-animal policy, companion policy, medical refrigeration, charging point, and emergency evacuation support;
- evidence owner, verifier, method, date, limitations, and confirmation contact.

Israeli public places and services have accessibility obligations, and the Commission for Equal Rights warns that inaccessible or misleading service information can be actionable. The product implication is to capture exact features and reconfirm them, not to promise “fully accessible” from an unchecked flag. [Commission accessibility service](https://www.gov.il/he/service/complaint_discrimination_inaccessibility_people_with_disabilities)

### 8.2 Religious and cultural fit

Optional traveler preferences can include:

- kosher certificate authority and scope, meat/dairy/pareve, Passover status, and allergen needs;
- Shabbat check-in and checkout, walking-distance needs, manual key, stair access, Shabbat elevator, urn, hotplate, and pre-arranged meals;
- synagogue, mikveh, prayer times, holiday closure, separate-hours request, and modesty requirements;
- halal, church access, prayer space, and other explicitly requested practices.

These are preference filters, not inferred sensitive profiles. Do not infer religion from name, location, previous trip, or payment product.

## 9. Earth-to-local-map experience

### 9.1 Navigation state machine

```text
WORLD_GLOBE
  -> COUNTRY_FOCUS(ISRAEL)
  -> ISRAEL_REGION_OVERVIEW
  -> LOCAL_HIGH_RES_MAP
  -> PLACE_OR_ROUTE_DETAIL
  -> ITINERARY_ASSEMBLY
  -> REVALIDATED_PROPOSAL
```

Dates, party, budget, connected benefits, accessibility requirements, and intent persist through every state. Back navigation restores camera, zoom, filters, and selected items.

### 9.2 Surgical transition behavior

- The Earth renders against one deliberate background. Remove any static Earth poster or destination poster under the interactive canvas.
- Country selection starts a visible camera descent and progressive data load. It does not open a poster that requires a second “enter” click.
- At the handoff threshold, globe labels fade, local vector tiles and terrain load, and the same Israel outline becomes the local map. A brief skeleton indicates data loading without blocking pan or zoom.
- Globe data is deliberately sparse and global. The local map is dense and operational. Do not force thousands of local points onto the world layer.
- A selected marker opens a useful preview immediately: current availability state, verified total or “check live availability”, why it fits, travel time, one primary action, and direct links to rooms, activities, transport, and guide content.

### 9.3 Information density without covering the map

**Desktop**

- map owns at least two thirds of the canvas;
- one collapsible side rail holds filters and itinerary;
- one contextual card is anchored to the selected point but collision-aware;
- price and status labels cluster by map cell and expand as zoom increases;
- controls occupy reserved safe zones and never stack over selected labels.

**Mobile**

- map remains directly pannable above a three-state bottom sheet: peek, half, full;
- peek shows one decision and one action, not a wall of cards;
- dragging the sheet changes the map safe area so markers reflow rather than hide underneath;
- filters open as a dedicated sheet, then close back to the same camera state;
- selected-route progress uses a narrow top strip that does not block map gestures.

### 9.4 Zoom-dependent layers

| Level | Primary information | Interaction |
| --- | --- | --- |
| world | destination clusters, validated lead prices or availability prompts, trip themes | select a country or ask the agent |
| Israel overview | regions, drive-time bands, inventory counts, weather or closure state | select region, compare weekend patterns |
| city or rural area | accommodation clusters, attractions, transport hubs, dining, events | choose an anchor stay or route |
| neighborhood | exact properties, walking and driving times, parking, accessible route, Shabbat needs | add and compare sellable units |
| venue | entrances, meeting points, room or activity choices, indoor media where authorized | confirm a bookable unit or session |

### 9.5 Local trip assembly

The local planner uses an anchor-and-constraints model:

1. derive dates, party, origin, budget, mobility, food, religious, and vibe needs;
2. choose candidate anchor stays or day-trip regions;
3. calculate realistic travel-time zones for each day;
4. load compatible activities, meals, transport, and required services inside those zones;
5. test opening hours, Shabbat or holiday transitions, age and accessibility constraints, weather, and supplier availability;
6. build editable day sequences with buffers and fallback options;
7. revalidate each sellable component and any benefit before presenting one authorization summary.

The user sees a simple story such as “stay here, explore these three places, eat here, and return without a rushed transfer”. The engine retains the dependency graph underneath.

## 10. Local operational safety and recovery

### 10.1 Live context sources

Local recommendations can change because of extreme weather, fire, flood, security instructions, road or rail disruption, attraction closure, and property operations. Safety data must retain source, geographic scope, issued time, expiry, and severity.

- The Nature and Parks Authority says advance registration enables notices about temporary closures, extreme weather, and special activity, and its site publishes current opening changes. [Official site reservations and notices](https://www.parks.org.il/%D7%94%D7%96%D7%9E%D7%A0%D7%95%D7%AA%20%D7%9C%D7%90%D7%AA%D7%A8%D7%99%D7%9D/)
- The Israel Meteorological Service exposes official real-time weather warnings. [Official weather alerts](https://www.gov.il/en/service/weather_alerts)
- Home Front Command's current government guidance identifies its app and National Emergency Portal as official warning channels. Tra-Vel can deep-link and adapt an itinerary from authorized data, but it must never replace the official alert mechanism or rewrite safety instructions. [Current emergency guidance](https://www.gov.il/BlobFolder/guide/emergencyguidelines/he/.14.6.2026.pdf)

### 10.2 Incident categories

- property stop-sell, overbooking, room mismatch, accessibility mismatch, protected-space mismatch, utility failure, or evacuation;
- attraction full, closed, weather-cancelled, age or medical rejection, guide unavailable, or equipment failure;
- public transport cancellation, missed connection, road closure, vehicle breakdown, no accessible vehicle, or Shabbat service gap;
- restaurant closed, reservation lost, kosher certificate expired or mismatched, allergy accommodation unavailable, or holiday menu changed;
- loyalty balance changed, activation failed, card not eligible, points quote expired, redemption timed out, or reward reversed;
- traveler illness, lost item, lost ID, late arrival, party change, pet or childcare problem, or urgent accessibility need.

### 10.3 Recovery policy

Every booked component declares:

- operational owner and after-hours contact;
- replaceability class and acceptable substitutes;
- cancellation and refund path;
- dependent components affected by a change;
- safe time to decide;
- traveler approval threshold;
- payment, points, and settlement consequences.

When one component fails, the system recalculates the dependency graph, not just that line item. A closed northern attraction may change lunch, driving, parking, and the evening check-in. A hotel relocation must recheck room occupancy, accessibility, kosher needs, protected-space information, transport time, cancellation cost, and loyalty treatment before it is proposed.

## 11. Stress-test and acceptance matrix

| Scenario | Required behavior |
| --- | --- |
| User selects “EL AL” without Matmid | Show EL AL inventory. Do not imply points eligibility. Offer optional Matmid connection. |
| User selects “Arkia” and connects FlyAll | Show Arkia inventory and separately evaluate FlyAll portal redemption if a live provider quote supports it. Do not label it Arkia points. |
| User selects “Israir” and connects FlyAll | Keep Israir `6H` inventory and FlyAll program value on separate axes. The public catalogue relationship can support planning, but availability, eligibility, price, and redemption still require current provider responses. Never label it Israir points. |
| Two Fly Card issuer versions overlap | Resolve exact issuer and product. Evaluate only the matching dated rules. Never merge the most favorable fields. |
| Customer manually enters a points balance | Use it for planning with a visible self-reported label. Require live verification before debit. |
| Provider balance refresh fails | Preserve the last timestamped snapshot for planning, mark it stale, and remove redemption from final checkout until refreshed. |
| Benefit quote expires during payment | Do not silently increase the price or retry the debit. Requote, show the delta, and request approval. |
| Redemption request times out | Mark uncertain, lock duplicate redemption, reconcile with provider, and keep cash checkout from creating a second booking. |
| Points are debited but supplier booking fails | Follow the provider compensation path, track reversal separately, and escalate until both fulfillment and points ledgers reconcile. |
| Card offer excludes a wallet transaction | Explain the excluded transaction type before payment and remove expected earn. |
| Future cashback is conditional on completed travel | Show it as pending after-trip value, never as today's discount. Reverse or amend it on cancellation. |
| User changes party after building an Israel package | Reprice occupancy, room count, child ages, activity capacity, transport, dining, cancellation, and all benefit limits. |
| Accessible room becomes unavailable | Do not substitute a standard room. Search exact accessibility requirements, reconfirm evidence, and ask approval. |
| Kosher certificate expires before travel | Mark the dining or meal claim unverified, request updated authority evidence, and propose verified alternatives. |
| Shabbat timing makes planned transport impossible | Rebuild the route and check-in sequence rather than merely warning at the final screen. |
| Nature site closes for heat or flood | Remove the affected session, preserve official notice and time, and propose a safe compatible alternative. |
| Public transport real-time feed conflicts with schedule | Prefer authorized real-time state, show uncertainty, increase buffers, and offer alternate transport. |
| Property reports an emergency closure | Rehouse using the full requirement set, propagate itinerary changes, and keep refund and replacement payments separate. |
| Loyalty source becomes stale while offer remains live | Keep the travel offer, present benefit as “checking”, and prevent stale value from changing the payable total. |
| Mobile bottom sheet covers a selected marker | Recompute the map safe area and reposition camera/labels. Never solve it by stacking another overlay. |
| Israel is selected on the globe | Animate directly to the local high-resolution map with preserved intent. No static poster or second entry click. |
| Two loyalty profiles appear to belong to one member | Require verified identity linkage, conserve every source and target balance exactly, retain immutable snapshot lineage, and prohibit double credit. |
| The card bill is posted but expected points are missing | Keep `bill posted` and `accrual pending`, `disputed`, `expired`, or `rejected` as separate facts. Never infer or execute a credit from the card charge alone. |
| Ten travelers use cash plus points across fares, taxes, fees, and ancillaries | Allocate every amount to an exact traveler, segment, and component. A partial cancellation or refund cannot silently move value across travelers or component types. |
| A voucher is used by someone other than its owner | Preserve owner, permitted beneficiary, presented beneficiary, face value, remaining value, currency, integer-rational FX basis, expiry, restrictions, and partial-consumption lineage before allowing any use. |

## 12. Implementation sequence

### Phase A: trustworthy benefit catalogue

- create programs, credential products, source registry, immutable campaign versions, eligibility expressions, combination matrix, and reviewer workflow;
- seed current public programs only as source-linked, non-transactional facts;
- schedule expiry and conflict detection;
- add user-facing provenance and certainty labels.

### Phase B: planning connections

- add manual balances, exact card-product selection, provider deep links, consent and revocation;
- implement cash versus points comparison and explainable filters;
- keep all redemptions disabled until provider-authorized APIs exist.

### Phase C: authorized live connections

- onboard one program at a time with contract tests, OAuth security review, scopes, balance snapshots, live quotes, idempotent redemption, webhook verification, reconciliation, and support runbooks;
- add step-up approval and a separate points ledger;
- measure connection success, stale-rate frequency, eligibility precision, quote failures, reversals, and customer savings actually realized.

### Phase D: local Israel inventory foundation

- ingest verified accommodation and sellable units;
- add activities, dining, transport, guides, accessibility, religious-service evidence, emergency context, and policies;
- build supplier freshness and closure controls before dense map display;
- prioritize major local regions only when each has end-to-end bookable depth, then expand coverage.

### Phase E: Earth-to-local-map release

- remove the static poster layer;
- implement the globe-to-local state machine and progressive local tile loading;
- add collision-aware clusters, mobile safe areas, bottom-sheet states, direct point previews, trip graph, and route editing;
- pass the stress matrix on mobile RTL, desktop RTL, keyboard, screen reader, touch, poor network, and stale-source conditions.

## 13. Release gates

A benefit filter is release-ready only when:

- the exact program and product are modeled;
- an official source and freshness policy exist;
- eligibility can return a reasoned unknown state;
- fees, FX, taxes, exclusions, expiry, and combination are represented;
- balance provenance is visible;
- checkout reprices and requires explicit authorization;
- uncertain redemption and reversal are recoverable;
- support can identify supplier, issuer, program, operation, and source version.

A local Israel item is release-ready only when:

- geographic identity and entrance are precise;
- a supplier or official source owns the facts;
- sellable unit, availability, price, inclusions, and cancellation are revalidated;
- party, child, accessibility, dietary, religious, and transport constraints are represented where relevant;
- closure and emergency freshness is visible;
- the item can be added, changed, cancelled, refunded, supported, and reconciled without losing its dependencies.

The target experience is easy because the complexity is modeled, not because it is omitted. The traveler can say what matters once, see a populated and usable map, understand the real payable choices, apply only benefits that genuinely fit, and authorize a complete local or international trip without deciphering issuer terms or supplier operations.
