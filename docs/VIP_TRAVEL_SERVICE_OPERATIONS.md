# VIP travel service operations

This document defines the operational architecture required for Tra-Vel to behave like a capable human VIP travel desk across pre-trip, in-trip, and post-trip service. It is a design contract, not evidence that a production supplier, insurer, payment processor, emergency service, or government authority is connected.

The intended customer experience is deliberately simple: report what happened once, see that Tra-Vel owns the next step, approve consequential choices, and receive verified progress. The operating system behind that experience is deliberately deep: it preserves every deadline, dependency, authorization, supplier response, payment movement, entitlement, piece of evidence, and unresolved ambiguity until the case is truly settled.

Research was reviewed on 2026-07-19. Supplier capabilities, consumer rules, emergency contacts, coverage, and deadlines are versioned facts and must be checked again before production use.

## 1. Operating thesis

Travel service is not one reservation. It is a dependency graph whose nodes can fail independently:

`traveler -> documents -> flight -> baggage -> transfer -> accommodation -> activity -> dining -> connectivity -> equipment -> insurance -> payment -> refund/settlement`

A flight change can invalidate a transfer, late check-in, activity, dining reservation, eSIM activation plan, and insurance notification deadline. A human agent's real work is therefore not merely responding to a message. It is:

1. identifying the traveler and exact affected services;
2. determining what is known, alleged, expired, pending, or uncertain;
3. protecting the earliest irreversible deadline;
4. finding viable alternatives across every dependent service;
5. explaining cost, rights, risk, and trade-offs without overpromising;
6. obtaining the right person's authorization for consequential actions;
7. executing each supplier action exactly once;
8. reconciling supplier, payment, fulfillment, and settlement truth;
9. monitoring until the traveler can actually continue the trip; and
10. preserving an evidence trail for refunds, insurance, disputes, and complaints.

The system must never collapse those responsibilities into a single `booked`, `failed`, or `resolved` flag.

## 2. Evidence baseline: facts that shape the design

This section records external facts from primary sources. The architecture that follows is Tra-Vel's inference from those facts. It is not a legal opinion, and the applicability of any consumer rule must be determined from the current itinerary, place of sale, traveler, carrier, supplier contract, and governing law.

| Domain | Current external fact | Operational consequence |
| --- | --- | --- |
| Traveler identity | ICAO distinguishes Advance Passenger Information, containing identity and travel-document data, from PNR data collected for airline business purposes; ICAO notes that PNR accuracy is not guaranteed and can contain sensitive data. [ICAO API and PNR standards](https://www.icao.int/facilitation-programmes/api-guidelines-and-pnr-reporting-standards) | Do not treat a booking profile as verified identity. Store source and verification level per field and create a trip-specific traveler snapshot before fulfillment. |
| Document readiness | IATA describes document admissibility as a pre-departure process and promotes selective disclosure and traveler control in One ID. Timatic is an actively maintained source for passport, visa, and health requirements. [IATA One ID](https://www.iata.org/en/programs/passenger/one-id/), [IATA Travel Centre](https://www.iata.org/en/services/compliance/timatic/travel-documentation/) | Rules must be fetched from an authorized current source and evaluated for the exact passport, residency, transit, destination, and itinerary. A generic destination article is not clearance to travel. |
| Booking ambiguity | Expedia warns that a lost connection or 5xx response after a booking request does not mean failure; the reservation may already be charged and confirmed. It instructs partners to retrieve using the original affiliate reference instead of blindly creating again. [Expedia handling booking requests](https://developers.expediagroup.com/rapid/lodging/reference/handle-booking-reqs) | Any indeterminate mutation becomes `uncertain`; block a duplicate and reconcile by provider reference before retrying. |
| Accommodation servicing | Booking.com's order model includes booked, traveler-cancelled, accommodation-cancelled, system-cancelled, stayed, and no-show outcomes. Cancellation is policy- and time-dependent and should be verified again from order details. [Booking.com order FAQ](https://developers.booking.com/demand/docs/orders-api/orders-faqs), [Booking.com cancellation handling](https://developers.booking.com/demand/docs/orders-api/cancel-order) | Property cancellation, fraud cancellation, invalid-card cancellation, traveler cancellation, and no-show are different incidents with different next actions and liability. |
| Accommodation room-level changes | Booking.com's connectivity reporting treats a partial no-show differently from a complete no-show and warns that a changed stay does not necessarily restore availability automatically. Expedia's Manage Booking API separately exposes retrieve, room-detail change, hard change, cancellation, hold, and resume operations, and returns totals in the billable currency. [Booking.com reservation-change reporting](https://developers.booking.com/connectivity/docs/reporting-api/b_xml-reporting), [Expedia Manage Booking](https://developers.expediagroup.com/rapid/lodging/manage-booking/about-mg-booking-api) | Preserve reservation, room, guest, date, occupancy, price, payment, no-show, and inventory-restoration states independently. A room change is not complete until the new room is confirmed, the old room state is reconciled, the property calendar effect is known, and any currency or refund difference is resolved. |
| Accommodation message delivery | Booking.com documents fallback handling when a connectivity system does not retrieve a new reservation, modification, or cancellation message, and recommends frequent polling to reduce missed messages, overbooking, and price mismatches. [Booking.com missing reservation messages](https://developers.booking.com/connectivity/docs/con-faq-reservations-missing-res-messages) | Webhooks, polling cursors, fallback notices, acknowledgements, and authoritative retrieval need independent health and replay controls. A missed event cannot be converted directly into a supplier failure or a duplicate rebooking. |
| Flight servicing | A Duffel order-change offer has an expiry, a penalty, a new total, and either an extra charge or a refund destination. [Duffel order changes](https://duffel.com/docs/api/order-changes) | A flight change must be quoted, explained, authorized, and reconfirmed before expiry. The supplier refund and repayment to the traveler remain separate operations. |
| Flight reservation versus ticketing | Amadeus distinguishes offer search, final pricing, order creation, ticket issuance through a consolidator, retrieval, and post-ticketing servicing. Its Self-Service documentation says cancellation through the API is generally limited to the pre-ticketing window and that issued-ticket changes or refunds may require the consolidator. [Amadeus flight API tutorial](https://developers.amadeus.com/self-service/apis-docs/guides/developer-guides/resources/flights/), [Amadeus Self-Service FAQ](https://developers.amadeus.com/self-service/apis-docs/guides/developer-guides/faq/) | A PNR or order reference is not proof that a ticket or ancillary document was issued. Model reservation, ticketing queue, ticket number, coupon status, EMD or ancillary fulfillment, consolidator ownership, ticketing deadline, and servicing channel separately. |
| Flight servicing financial differential | IATA's offer-and-order implementation guidance identifies multiple per-item servicing outcomes, including additional collection, refund, residual value, reusable value, even exchange, and combinations of additional collection with refund or residual value. [IATA implementation guide](https://guides.developer.iata.org/docs/20-2_ImplementationGuide.pdf) | Never reduce a changed order to one signed net number. Preserve the old item, replacement item, penalty, tax difference, amount newly collected, supplier refund, residual credit, reusable value, traveler repayment, and settlement result as separate ledgers with their own evidence and status. |
| Airline offer and order servicing | IATA defines NDC as an offer-and-order data-exchange standard available to airlines, agents, intermediaries, and technology providers. IATA's servicing material explicitly treats schedule changes and unavailable services as events that can alter a customer order. [IATA NDC](https://www.iata.org/en/programs/airline-distribution/retailing/ndc), [IATA NDC servicing](https://www.iata.org/contentassets/6de4dce5f38b45ce82b0db42acd23d1c/ndc-infocus-servicing.pdf) | Preserve the supplier's order model and servicing permissions. Search, ticket issuance, ancillary documents, schedule-change acceptance, exchange, reissue, refund, and traveler notification are separate operations; rebuilding a local itinerary does not service the airline order. |
| Planned change versus IROPs | IATA's interline guidance distinguishes a planned schedule change from an irregular operation and requires partners to agree who owns traveler contact, re-accommodation, itinerary changes, ticket reissue, baggage, expenses, and downstream carrier communication. [IATA interline IROPs guidance](https://www.iata.org/contentassets/e7a533819be440edbb1e49da96e0f2a8/guidance-document-interline-irops_25june2020.pdf), [IATA interline baseline checklist](https://www.iata.org/contentassets/e7a533819be440edbb1e49da96e0f2a8/baseline-checklist-guidance-doc-airlines.pdf) | Normalize `planned_schedule_change` and `day_of_travel_disruption` separately. Bind validating, marketing, operating, ticketing, and disrupting-carrier roles to each affected segment, then verify re-accommodation, reissue, ancillary transfer, baggage, expense, and traveler-contact outcomes independently. |
| Operational contact | IATA Resolution 830d materials say agents should provide a passenger mobile number and/or email for operational notifications; IATA also says these details are for journey updates such as delay, cancellation, rebooking, gate, seat, baggage, and check-in notices rather than marketing. [IATA passenger-contact requirement](https://portal.iata.org/faq/articles/en_US/FAQ/Is-it-mandatory-for-travel-agents-to-provide-the-passenger-s-mobile-phone-and-email-address-1448977338174), [IATA operational-contact purpose](https://portal.iata.org/faq/articles/en_US/FAQ/For-what-purposes-will-the-airline-use-the-passenger-s-contact-1448977338184) | Keep operational-notification routing, reachable-contact verification, and marketing consent as different records. A traveler can decline marketing without losing disruption notices, and a contact change must be propagated only to the bookings and suppliers for which it is authorized. |
| Interline ownership | IATA describes interline standards as covering schedules, reservations, ticketing, pricing, baggage, departure control, irregular operations, billing, and settlement across more than one carrier. [IATA multilateral interline framework](https://www.iata.org/en/programs/airline-distribution/retailing/future-of-interline/multilateral-interline-framework/) | A replacement seat is not the complete recovery. The case stays open until every affected ticket coupon/order item, baggage handoff, paid ancillary, SSR, partner notification, billing responsibility, and onward connection has been reconciled. |
| Agency settlement and debit exposure | IATA's BSP agent material covers refunds, billing reports, credit and debit memos, and cases where an airline has not enabled direct refund functionality. [IATA BSP Manual for Agents](https://www.iata.org/en/fmc-documents/a4938a2d-e11c-44f5-b88f-dd1e548829ef/) | A traveler refund, airline refund authority, BSP posting, commission reversal, agency debit memo, and cash movement are independent records. The financial tail can remain open after the traveler-facing trip is repaired and needs its own deadline, evidence, owner, and reconciliation. |
| Activities | Viator says schedule data can become stale, recommends real-time availability checks, supports availability or price holds, and supports instant, manual, and mixed confirmation models. It also exposes a cancellation quote before cancellation. [Viator Partner API](https://docs.viator.com/partner-api/technical/) | An activity card is not proof of current capacity. Manual confirmation, supplier cancellation, booking cutoffs, refund quotes, and modification feeds require timers and reconciliation. |
| Activity amendments and supplier events | Viator's current partner API exposes amendment checking, amendment quoting, amendment execution, supplier-originated modification feeds, cancellation quotes, and cancellation operations. It also distinguishes pending and confirmed booking behavior. [Viator Partner API 2.0](https://docs.viator.com/partner-api/technical/), [Viator supplier cancellation and amendment automation](https://partnerresources.viator.com/travel-commerce/automating-supplier-cancellations/) | An activity modification needs an immutable before-and-after comparison, participant and pickup revalidation, expiry and refund checks, traveler approval when material, event acknowledgement, and final voucher replacement. Supplier cancellation and traveler cancellation cannot share one reason or liability state. |
| Ground-transfer fulfillment | Amadeus separates transfer search, booking, and management. Its search supports materially different products such as private transfers, hourly service, taxi, shared transfer, airport express rail or bus, private jet, and helicopter, while management currently uses the booking reference for cancellation. [Amadeus Cars and Transfers](https://developers.amadeus.com/self-service/apis-docs/guides/developer-guides/resources/cars-transfers/) | “Transfer booked” is not a sufficient operational state. Preserve mode, exact pickup and drop-off, flight linkage, meet-and-greet instructions, passenger and baggage capacity, child seat, accessibility, waiting allowance, operating supplier, driver assignment, contact handoff, cancellation capability, and pickup completion independently. A delayed flight must recalculate the pickup clock before deciding that the driver is a no-show. |
| Car-rental post-booking | Booking.com's current Demand API exposes car search, depot and supplier detail plus order preview, creation, detail, modification and cancellation. Its 2026 change log adds a modification preview, car cancellation, insurance data, payment timing, and booker-country context for post-booking accuracy. [Booking.com car-rental API](https://developers.booking.com/demand/docs/open-api/demand-api/cars), [Booking.com Demand API change log](https://developers.booking.com/demand/docs/whats-new/archive) | Model rental, depot, named driver, age eligibility, licence evidence, country context, vehicle class, included mileage, fuel and charging terms, deposit or preauthorization, insurance layers, excess, equipment, border permission, opening hours, flight delay handling, pickup payment, inspection evidence, damage claim, modification quote, cancellation and final release as separate facts. Never present a car-order reference as proof that a vehicle, child seat, insurance, or deposit release is fulfilled. |
| Baggage | IATA Resolution 753 requires tracking at passenger handover, aircraft loading, transfer delivery, and return to the passenger, including sharing across relevant interline partners. [IATA baggage tracking](https://www.iata.org/en/programs/ops-infra/baggage/baggage-tracking) | Store bag-tag and tracing references separately from the passenger itinerary and monitor handoffs, especially at connections. A baggage report is an operational artifact, not free text alone. |
| Insurance and assistance | XCover distinguishes emergency assistance from ordinary claims and says the correct emergency number depends on the purchased wording. Its claim guidance lists booking, cancellation, medical, police, carrier, expense, and other evidence that may be required. [XCover emergency assistance](https://www.xcover.com/en-us/help/xcover-assist), [XCover travel claim documents](https://www.xcover.com/en/help/travel-claim-documents) | Route imminent danger to local emergency services and the policy-specific assistance service. Preserve evidence, but do not let Tra-Vel's general support workflow adjudicate coverage or provide medical advice. |
| Connectivity | Airalo documents common failures including locked devices, missing internet during installation, wrong APN, roaming or network selection, coverage, and eSIM delivery delay. It also provides offline setup instructions only after purchase and installation. [Airalo activation troubleshooting](https://www.airalo.com/help/troubleshooting/0UEL63PDK5IJ/why-am-i-seeing-the-unable-to-activate-esim-error-on-ios/YHSBQOQME6W8), [Airalo offline mode](https://www.airalo.com/help/using-managing-esims/ZSEEHBT5HW6F/what-is-offline-mode-and-when-should-i-use-it/IOTPG8TCPBO5) | Device compatibility and unlock status should be checked before sale. Instructions and emergency contacts needed on arrival must be available offline before the traveler loses connectivity. |
| Payment | Stripe models payment as a lifecycle that can require customer action or remain processing. It recommends idempotency keys for safe retries; refunds can remain pending or fail; disputes have their own evidence and deadline lifecycle. [Stripe PaymentIntent lifecycle](https://docs.stripe.com/payments/paymentintents/lifecycle), [Stripe idempotency](https://docs.stripe.com/api/idempotent_requests), [Stripe refunds](https://docs.stripe.com/refunds), [Stripe disputes](https://docs.stripe.com/disputes/how-disputes-work) | Payment, supplier reservation, fulfillment, refund, and settlement must be independent state axes. A charge is not a ticket, a supplier refund is not customer receipt, and a chargeback is not a cancellation request. |
| Accessibility | IATA publishes standard accessibility SSR guidance for agents. EU guidance says assistance needs should normally be communicated at least 48 hours ahead, while reasonable efforts still apply when not pre-notified; U.S. DOT separately regulates safe wheelchair handling. [IATA SSR guidance](https://www.iata.org/contentassets/7b3762815ac44a10b83ccf5560c1b308/best-practices-on-the-application-of-ssr-codes-and-assistance-service.pdf), [EU reduced-mobility rights](https://europa.eu/youreurope/citizens/travel/transport-disability/reduced-mobility/index_en.htm), [U.S. DOT wheelchair rule](https://www.transportation.gov/regulations/federal-register-documents/2024-29731) | Accessibility needs are fulfillment-critical requirements. They must survive every rebook, be re-acknowledged by each supplier, and be monitored through handoffs. |
| Accessibility precision | IATA's SSR guidance separates the traveler's actual assistance need, the standardized code, and the service-provider acknowledgment; it prompts agents to ask what help is needed during the journey rather than infer it from a diagnosis. [IATA SSR and assistance-service practices](https://www.iata.org/contentassets/7b3762815ac44a10b83ccf5560c1b308/best-practices-on-the-application-of-ssr-codes-and-assistance-service.pdf) | Store functional assistance requirements, equipment measurements/battery facts, requested SSR, supplier acknowledgment, airport handoff, and completion evidence separately. Never infer a code from a disability label, and never mark a rebook ready while the replacement SSR remains unacknowledged. |
| Minors | EU guidance warns that minors may need additional authorization and that transit countries may also request it. Israel's Population and Immigration Authority has distinct parental-consent requirements for child travel documents. [EU documents for minors](https://europa.eu/youreurope/citizens/travel/entry-exit/travel-documents-minors/index_en.htm), [Israel travel documents](https://www.gov.il/en/service/request_for_israeli_biometric_travel_document) | Model guardianship and authorization evidence per traveler and itinerary. Do not infer authority from who paid, who created the account, or a shared family surname. |
| Israeli passenger and consumer context | Israel's Aviation Services Law provides a statutory framework for assistance and compensation for covered disruptions. Israel's Consumer Protection Authority also publishes remote-sale cancellation guidance for tourism services. [Knesset Aviation Services Law text](https://fs.knesset.gov.il/18/law/18_lsr_301013.pdf), [Consumer Protection Authority tourism cancellation guidance](https://www.gov.il/ar/pages/qaflight23) | Run a versioned jurisdiction and entitlement evaluation. Do not hard-code compensation copy or assume that the same rule applies to every segment or package. |
| Privacy | Israel's Privacy Protection Authority identifies heightened obligations for databases containing personal information of special sensitivity and publishes access-log and security guidance. [Sensitive-database notification](https://www.gov.il/he/service/notice-obligation), [access logging guidance](https://www.gov.il/BlobFolder/reports/takana10d/he/Takna10_Tikon13.pdf) | Health, disability, identity, and incident evidence require purpose limitation, strict role access, audit, retention controls, and legal review before production. |
| Emergency routing | Israel's National Security Council publishes destination-specific warning levels. The Ministry of Foreign Affairs requires personal attendance at a mission when an Israeli travel document is lost, stolen, or damaged abroad. [NSC travel warnings](https://www.gov.il/en/departments/dynamiccollectors/travel-warnings-nsc), [MFA travel-document replacement](https://www.gov.il/en/service/issuing_and_extending_travel_documents) | Emergency content and consular routing must come from current official sources. Tra-Vel can coordinate, preserve documents, and guide next steps, but cannot impersonate an emergency service or consular authority. |
| Israeli airport operations | The Israel Airports Authority publishes live terminal and operating notices; for example, its current notices identify terminal-specific domestic and international processing and advise travelers to verify the departure terminal against the current schedule. [Israel Airports Authority notifications](https://www.iaa.gov.il/en/airports/ben-gurion/notifications-and-updates/) | Terminal is a versioned trip fact, not decorative text. A terminal change must recalculate arrival time, security/check-in instructions, parking, rail/bus/transfer meeting point, accessibility handoff, lounge eligibility, and connection buffers, then notify every affected traveler and ground supplier. |
| Local route computation | Google Maps Routes documents that transit routing has different constraints from driving, does not accept intermediate waypoints, may omit fare unless it can calculate the complete route fare, and warns that transit schedules can change. Driving routes support stop and pass-through waypoints with different semantics. [Google transit routes](https://developers.google.com/maps/documentation/routes/transit-route), [Google waypoint types](https://developers.google.com/maps/documentation/routes/waypoint-types) | The Earth can introduce and compare a trip, but the local map must descend into mode-specific route calculations. Every stop, pass-through point, pickup, route leg, fare, schedule timestamp, and freshness state must remain explicit; a polyline alone is not an executable local itinerary. |
| Local transit live state | GTFS Realtime separates trip updates, vehicle positions, and service alerts. Its best practices recommend frequent refresh, bounded data age, stable identifiers, and specific cancellation and detour signaling instead of treating every disruption as one generic status. [GTFS Realtime best practices](https://gtfs.org/documentation/realtime/realtime-best-practices/), [GTFS service alerts](https://gtfs.org/documentation/realtime/feed-entities/service-alerts/) | Keep the published schedule, predicted trip, actual vehicle, service alert, affected stop/route, freshness clock and fallback source as independent axes. A stale vehicle dot must never look like a current vehicle; a route-wide closure, cancelled trip, delayed trip, skipped stop and detour require different downstream recovery and customer messages. |
| Israeli accommodation and attractions | Israel's Ministry of Tourism distinguishes hotel-scale accommodation from smaller guest-room facilities in its operating material, inspects hotels, tourism sites, attractions, guides, beaches, and related services, and publishes physical accommodation standards. Israeli accessibility guidance also treats beaches, parks, viewpoints, attractions, event gardens, and other open public places as distinct service environments. [Ministry accommodation assessment](https://www.gov.il/he/service/request-for-architectural-assessment), [Tourism Experience Administration](https://www.gov.il/en/departments/units/operational_standards_and_quality_of_service), [open-place accessibility guidance](https://www.gov.il/he/pages/non_building_place_accessibility_guide) | Local tourism inventory must preserve facility type, official/claimed classification, operating status, accessibility evidence, room/unit facts, parking and arrival instructions, and last verification separately. A generic “accessible” or “hotel” badge is insufficient for matching a real family, mobility device, Shabbat arrival, or late check-in. |

| Israeli tourism complaints and service evidence | Israel's Ministry of Tourism complaint service asks for a complete event description with place and time, details of the tourism business or professional, supporting receipts or images, and permission to forward the complaint. It distinguishes receipt, referral for response, interim acknowledgment, delay notice, and final answer. [Ministry of Tourism complaint service](https://www.gov.il/en/service/review_and_complaints) | Recovery and complaint handling are separate but linked workflows. Preserve the original service problem, immediate remedy, evidence manifest, customer forwarding consent, exact recipient, delivery proof, requested response, interim status, delay reason, final decision, and any refund or claim as separate records. The customer should report once, while the system assembles the authority-ready evidence packet and tracks every handoff. |
| Israeli accessibility service recovery | Israel's Equal Rights Commission states that a public service provider must provide access and service without disability discrimination and at the same service level, and it accepts supporting correspondence, documents, and images for complaints. [Equal Rights Commission accessibility complaint service](https://www.gov.il/he/service/complaint_discrimination_inaccessibility_people_with_disabilities) | A claimed accessibility feature is not fulfillment evidence. Record the functional requirement, promised adjustment, on-site result, safe replacement, correspondence, images and consent separately. Keep immediate traveler safety and continuity ahead of the later supplier, regulator, refund, insurance, or legal path. |

## 3. Complete traveler registration, not a generic account

Registration is a progressive, trip-aware process. The customer should not face one enormous form, but the system must still reach booking-grade completeness before it asks a supplier to fulfill anything.

### 3.1 Identity and relationship graph

Use separate records for:

- `account`: login identities, communication channels, language, timezone, notification permissions, and recovery methods;
- `party`: a household, couple, group, company, or other purchasing group;
- `traveler`: the human being who will consume a service;
- `booker`: the person authorized to submit a reservation;
- `payer`: the person or entity authorizing payment;
- `guardian`: the person with documented authority for a minor or dependent adult;
- `beneficiary`: the person covered by insurance or another entitlement;
- `emergency_contact`: a contact who is not automatically authorized to view or change the trip;
- `operator_delegate`: a time-limited person whom the traveler explicitly allows Tra-Vel to contact or involve; and
- `supplier_passenger`: the supplier-specific representation created from a verified trip snapshot.

These roles may belong to different people. Authorization must never be inferred from account ownership or payment alone.

### 3.2 Field-level provenance and verification

Every critical field carries:

- normalized value or a vault reference;
- source: traveler, document scan, operator, supplier, government/authorized rules provider, or imported booking;
- `unverified`, `self_asserted`, `document_matched`, `supplier_accepted`, or `authority_verified` status;
- who verified it and when;
- applicable trip and supplier scope;
- expiry and refresh deadline;
- consent/purpose reference; and
- correction history without overwriting past fulfillment evidence.

Examples:

- A preferred first name is safe for customer copy, but the ticketing name comes from the travel document snapshot.
- A frequent-flyer number can be self-asserted until the airline accepts it.
- “Wheelchair needed” is a request; the supplier acknowledgment and exact SSR are separate evidence.
- “Kosher meals preferred” is not the same as a confirmed special meal, a restaurant's certification, or an allergy instruction.

### 3.3 Progressive registration gates

| Gate | Minimum data | Result |
| --- | --- | --- |
| Discover | destination intent, dates or flexibility, party size, budget/vibe, coarse accessibility needs | Search and planning only; no booking-grade identity required. |
| Personalize | account/contact verification, traveler roles, ages/age bands, preferences, loyalty programs, broad constraints | Saved trip and tailored options; still no supplier commitment. |
| Ready to quote | exact occupancy and passenger categories, residency/nationality facts required for rules, accessibility and equipment requirements, contactability | Provider-specific availability and policy checks. |
| Ready to reserve | exact document name and required API fields, guardian authority where applicable, accepted terms, supplier-required booking questions, verified payment-session ownership | Hold or reservation request may be attempted. |
| Ready to fulfill | payment outcome sufficient for the merchant model, supplier confirmation, required documents accepted, all mandatory traveler data acknowledged | Ticket, voucher, reservation, policy, activation code, or other entitlement can be issued. |
| Ready to travel | current document/admissibility check, check-in and pickup instructions, SSR confirmation, insurance/emergency contacts, offline itinerary, dependency health | Traveler receives a departure-ready status with exceptions made explicit. |

Registration readiness is versioned evidence, not a permanent badge. Each successor revision records its actor, authority evidence, exact changed requirements, exact invalidated requirements, reason, and UTC clock. Ordinary progress may advance only one gate. A document, party, guardian, accessibility, supplier, itinerary, or policy change can move readiness backward and must name the downstream checks it invalidated. The transition itself has `authorization_effect=registration_only`; it cannot reserve, change, cancel, charge, or refund anything.

A traveler who does not use a loyalty program can record that choice without being blocked. A dependent adult requires both a support plan and verified authority before reservation. Before fulfillment, the system requires an immutable traveler-manifest snapshot, and before travel it requires an offline service-contact pack in addition to the itinerary.

### 3.4 Registration details by service

- **Flights:** exact document name, birth date and other carrier-required passenger data, nationality/document fields where required, passenger type, loyalty number, contact, SSRs, meal request, seat/baggage selection, ticketing deadline, through-ticket versus self-transfer, and contact disclosure to the carrier.
- **Accommodation:** legal guest name, lead guest, age/child allocation as defined by that property, arrival time, late-arrival requirement, room and bed allocation, accessibility, deposit/payment model, local-tax responsibility, key collection, and special requests as unconfirmed until acknowledged.
- **Transfer/car:** passenger count, luggage and oversize items, pickup/drop-off coordinates and terminals, flight number, buffer rule, child-seat requirements, mobility equipment dimensions, driver age/license requirements where applicable, reachable local number, and contingency meeting point.
- **Activities:** exact participant mix, age/height/weight or health restrictions only when legitimately required, language, pickup, waiver/consent requirement, equipment sizing, weather policy, booking questions, and confirmation model.
- **Dining:** party composition, seating/accessibility, dietary preference, allergy information only with explicit purpose and careful human confirmation, kosher/certification requirement, deposit/card guarantee, cancellation/no-show deadline, and late-arrival contact method.
- **Insurance:** insured persons, residency/eligibility facts, trip cost and dates, policy wording/version, beneficiary details where required, explicit regulated disclosures/consents, assistance number, and claim boundary. Medical history never enters a generic trip note.
- **Connectivity:** device model, eSIM compatibility and lock status, destination coverage, activation trigger, voice/SMS need, data allowance, installation status, ICCID/activation reference in a restricted vault, and offline instructions.
- **Equipment:** user measurements and fit, skill/qualification where required, safety requirements, delivery/collection point, deposit, serial/item assignment, condition photos, return deadline, and damage/loss process.

### 3.5 Immutable trip snapshot

At each consequential supplier action, create an immutable `traveler_manifest_version`. It contains only fields needed for that supplier and action, references the authorization used, and records the rules/source versions that were checked. Later profile edits do not silently alter an existing reservation. A change to a fulfilled name, date of birth, accessibility request, or guardian arrangement opens a servicing case and follows the supplier's change process.

## 4. The operational data model

### 4.1 Core aggregates

1. **TripGraph**: travelers, services, legs, local times, locations, dependencies, buffers, rights jurisdictions, and critical deadlines.
2. **CommerceOrder**: exact provider, offer/version, policy snapshot, provider reference, and the independent checkout, payment, fulfillment, refund, and settlement states.
3. **Entitlement**: ticket, voucher, room reservation, insurance policy, transfer confirmation, dining reservation, eSIM, or equipment handover evidence.
4. **ServiceCase**: one operational problem, its affected graph, severity, owner, authorizations, plan, actions, timers, evidence, communications, and outcome.
5. **Operation**: one consequential attempt against one provider, identified independently of HTTP retries.
6. **EvidenceItem**: immutable metadata and digest for a document, message, receipt, photo, report, recording, or supplier response; the sensitive payload lives in the appropriate protected store.
7. **CommunicationThread**: channel, participants, consent/purpose, delivery receipts, inbound/outbound messages, translations, and commitments extracted for review.
8. **Deadline**: cancellation cutoff, ticketing limit, check-in, boarding, connection, pickup, claim notice, dispute response, refund follow-up, or internal SLA timer.
9. **Decision**: ranked alternatives, costs, trade-offs, policy facts, recommendation, required approver, and expiry.

### 4.2 Independent state axes

Never use a single order status. Preserve at least:

- checkout: `draft`, `reviewed`, `authorized`, `submitted`, `completed`, `abandoned`;
- payment: `not_started`, `requires_method`, `requires_action`, `processing`, `authorized`, `captured`, `failed`, `voided`, `partially_refunded`, `refunded`, `disputed`;
- supplier reservation: `not_started`, `held`, `pending`, `confirmed`, `rejected`, `cancelled`, `uncertain`;
- fulfillment: `not_started`, `pending`, `issued`, `partially_issued`, `failed`, `voided`, `uncertain`;
- customer refund: `not_started`, `pending`, `partially_paid`, `paid`, `failed`, `disputed`;
- supplier/affiliate settlement: `unreported`, `reported`, `eligible`, `payable`, `paid`, `reversed`, `disputed`;
- operational health: `healthy`, `at_risk`, `disrupted`, `stranded`, `recovery_in_progress`, `recovered`, `closed_with_loss`.

One example of why this matters: `payment=captured`, `supplier reservation=uncertain`, and `fulfillment=not_started` is a high-priority reconciliation case, not “booked” and not “failed.”

### 4.3 Service-case lifecycle

`received -> verified -> triaged -> plan_building -> approval_needed -> executing -> supplier_pending -> uncertain -> monitoring -> resolved -> closed`

Side paths are `evidence_needed`, `traveler_pending`, `escalated`, `safety_handoff`, `reopened`, and `closed_with_loss`. Severity is independent from lifecycle. A case can be `supplier_pending` and still be P0.

Resolution requires a reason code and evidence. “Message sent,” “supplier contacted,” “refund requested,” and “traveler informed” are progress events, not resolution.

## 5. One-tap, no-password incident intake

The goal is zero-friction reporting, not zero security.

### 5.1 Three access levels

| Access | Customer can do | Customer cannot do |
| --- | --- | --- |
| Public emergency intake | Report immediate danger or a new problem, select coarse location, request a callback, receive official emergency guidance without exposing itinerary data | View a booking, name a traveler from search results, change/cancel, upload high-sensitivity documents, authorize payment |
| Scoped trip link | View a redacted trip card, report an incident, add ordinary evidence, update safe contact/location data, read case progress, approve contact by a named operator | Reveal full passport/payment/medical data, change identity, spend money, cancel a service, redirect a refund, add a new delegate |
| Step-up verified session | Approve an expiring change/cancellation quote, authorize a payment, disclose sensitive evidence to a named recipient, change recovery channels or traveler authority | Exceed the explicit action scope or remain trusted indefinitely |

### 5.2 Capability-link contract

A scoped link is an opaque capability, not a normal account session:

- generate at least 128 bits of cryptographically secure randomness;
- store only a keyed digest, never the bearer token;
- bind it to one account/trip/case, allowed actions, disclosure scope, channel, issue reason, and hard expiry;
- exchange it once for a short-lived, `HttpOnly`, `Secure`, `SameSite` scoped session; rotate the token after exchange;
- revoke on use when the action is one-time, on contact-channel change, on suspected compromise, on case closure, or from any signed-in device;
- rate-limit issuance, redemption, evidence upload, and recovery; return non-enumerating errors;
- prevent referrer leakage, third-party scripts, analytics parameters, and indexing on redemption pages;
- show the exact trip/action before acceptance and notify an existing trusted channel after sensitive access;

The first customer cockpit bridge deliberately has no request-level trip selector. Signed-in access resolves the newest read model owned by the authenticated account. Scoped access resolves trip and account only from the live `HttpOnly` capability session, then repeats the exact binding check against the authoritative repository before redaction. Whole-trip views reject non-null case bindings until a separately tested case-filtered projection exists. The browser never supplies the private projection, owner digest, disclosure class, or scope list, and the bridge exposes no write method.
- record device/risk signals as security evidence, not as sole identity proof; and
- require step-up for financial, identity, cancellation, refund-destination, guardian, or medical disclosure actions.

NIST's current guidance requires one-time acceptance and replay resistance for authentication secrets and says email is not an acceptable out-of-band authenticator for higher-assurance authentication. [NIST SP 800-63B](https://pages.nist.gov/800-63-4/sp800-63b.html) Tra-Vel therefore uses an email link as a bounded low-risk recovery/intake capability, not as blanket proof that its holder may control a trip.

### 5.3 Lost phone, no connectivity, and compromised link

- A public intake can open a case without displaying private trip facts.
- A traveler can quote a non-secret public case reference to an operator; the operator performs separate verification.
- Offline travel cards contain supplier and official emergency contacts, policy number/reference where appropriate, meeting points, and minimum recovery instructions, but not full payment or unnecessary identity data.
- A “this was not me” action revokes all scoped links for the case and moves sensitive actions to manual verification.
- Recovery channel replacement requires stronger proof than the lost channel itself.
- The system never asks a traveler to send card security codes, passwords, or complete medical history through chat. PCI SSC specifically prohibits retaining card verification values after authorization, including in audio recordings. [PCI SSC FAQ](https://www.pcisecuritystandards.org/faqs/1210/)

## 6. Incident taxonomy and required playbooks

Every intake is normalized into `incident_type`, `affected_services`, `people_at_risk`, `current_location`, `next_critical_event`, `time_to_event`, `evidence_status`, `authorization_status`, `supplier_contactability`, and `financial_exposure`.

One traveler message may describe several problems, but it is never treated as one shared operational bucket. The classifier must emit one verified, trip-bound observation per case family. Each observation owns its own risks, affected service references, dependency references, evidence disclosure scope, authority requirement, after-hours route, and idempotency identity. The planner may then open several sibling playbooks from the same intake, order a P0 safety playbook first, and still preserve every lower-priority case. This prevents a lost-card fraud signal from changing baggage priority, prevents medical evidence from entering a payment case, and prevents one urgent case from suppressing the rest of the traveler's request.

### 6.1 Cross-trip incidents

- traveler cannot be reached or is using a new number;
- passport/visa/admissibility problem;
- lost/stolen document or wallet;
- minor/guardian authority dispute;
- illness, injury, death, hospitalization, or family emergency;
- accessibility assistance missing or mobility equipment damaged;
- destination safety warning, border closure, strike, weather, security event, or mass cancellation;
- payment uncertain, duplicate, declined, reversed, or disputed;
- customer requests a change that cascades across several independently booked services;
- supplier or platform nonresponse/outage;
- fraud suspicion, account takeover, or conflicting instructions from different party members.

### 6.2 Flights and baggage

Playbooks must distinguish schedule change, cancellation, delay, denied boarding, missed connection on one ticket, self-transfer failure, ticket not issued, duplicate booking, name/document mismatch, seat/SSR loss after rebook, baggage delayed/lost/damaged, and airline insolvency/service cessation.

For any schedule event:

1. retrieve current airline/order/ticket truth;
2. identify operating and marketing carriers, ticket stock, PNR/order, and through-ticket boundary;
3. compute connection and ground-service impact from actual local times and terminals;
4. evaluate current rights separately from fare rules and voluntary-change rules;
5. preserve the existing booking while alternatives are only being quoted;
6. carry accessibility, minor, meal, seat, baggage, and traveler-contact requirements into each replacement option;
7. obtain approval if the choice changes cost, destination/airport, dates, cabin, refund form, or material conditions;
8. confirm ticket issuance, not only the reservation; and
9. reschedule or cancel dependent services and record their separate economics.

For baggage, capture bag-tag numbers, Property Irregularity Report/tracing reference, last confirmed handoff, delivery address that can change with the trip, essential-purchase receipts, photographs, carrier communications, and insurance notice. Do not promise compensation until the applicable carrier, convention, law, and policy have been evaluated.

### 6.3 Accommodation

Playbooks cover property cannot find booking, property cancellation, overbooking/walk, room unavailable, invalid payment card, surprise deposit/tax, wrong occupancy, bed mismatch, inaccessible room, cleanliness/safety issue, late arrival, early departure, no-show dispute, key collection failure, rental host unreachable, and refund disagreement.

The operator view must expose merchant model, who collects payment, cancellation schedule in property local time, check-in deadline, property and supplier references, guest allocation, confirmation communications, and replacement-accommodation radius/requirements. Near check-in, the safety plan is first to secure a viable room, while preserving evidence and avoiding duplicate nonrefundable bookings.

### 6.4 Transfers and local mobility

Playbooks cover driver no-show, traveler no-show, late inbound flight, wrong terminal, bad meeting point, unreachable driver/traveler, vehicle too small, missing child seat, wheelchair or equipment incompatibility, unsafe vehicle/driver, border crossing, address error, and cancellation cutoff.

Flight tracking is a signal, not authorization to change a transfer. The system must know whether the provider monitors the flight, how long waiting time lasts, and whether a new pickup was confirmed. The contingency plan presents verified public transport/taxi or replacement-supplier choices without claiming the original transfer is cancelled until that cancellation is confirmed.

### 6.5 Activities

Playbooks cover stale availability, manual confirmation overdue, supplier rejection/cancellation, weather cancellation, minimum-participant failure, pickup failure, ticket/voucher not accepted, language mismatch, accessibility failure, age/height/weight/qualification issue, customer illness, partial-party cancellation, and post-activity complaint.

Always retrieve current booking status and cancellation quote. Supplier time, local start time, booking cutoff, and customer notification deadline are different clocks. If the supplier cancels, dependent transport and dining are re-evaluated automatically.

### 6.6 Dining

Playbooks cover restaurant closure, reservation missing, late arrival, deposit/no-show charge, party-size change, dietary/certification mismatch, allergy concern, accessibility, special occasion request, and replacement near the actual itinerary location.

OpenTable's public terms illustrate that restaurant-specific cancellation policies and card guarantees may apply, and that a reported no-show can be disputed. [OpenTable terms](https://www.opentable.com/c/legal/terms-and-conditions/) Tra-Vel should therefore store the displayed policy and confirmation evidence, never promise a preference, and treat allergy/medical content as restricted data requiring human confirmation with the venue.

### 6.7 Insurance and medical incidents

The first branch is always `immediate danger?` If yes, show the destination's official local emergency instruction and the purchased policy's assistance contact, then open a P0 coordination case. Tra-Vel does not diagnose, direct treatment, guarantee coverage, or delay local emergency help while collecting forms.

Separate states are required for:

- assistance contact attempted/connected;
- insurer preauthorization requested/granted/declined;
- provider guarantee of payment requested/received;
- claim draft/submitted/evidence-needed/assessing/approved/declined/appealed/paid;
- supplier refunds or vouchers that reduce the claimed loss; and
- customer reimbursement received.

Evidence requests are generated from policy/version and incident type. Medical evidence is disclosed only to the minimum authorized recipient; generic operators see a task such as “insurer requires one medical certificate,” not the diagnosis unless their role genuinely requires it.

### 6.8 eSIM and connectivity

Playbooks cover incompatible or locked device, QR/activation code already consumed, delivery delayed, activation started too early, eSIM installed but inactive, no coverage, wrong APN, roaming off, wrong network, primary-SIM roaming risk, data exhausted, device lost, and traveler offline.

Pre-departure readiness includes device check, installation timing, saved offline instructions, and a fallback contact path. Support records ICCID/provider references in a restricted store and avoids exposing reusable activation credentials in normal event logs.

### 6.9 Equipment and products

Playbooks cover unavailable size/model, substitution consent, delivery missed, pickup location closed, unsafe/damaged item, certification mismatch, deposit, lost/stolen item, return delay, and damage claim. Equipment needed for accessibility or a booked activity is a dependency, not an optional retail line. A replacement must preserve safety and fit constraints, not only price.

### 6.10 Payment, refunds, and chargebacks

Playbooks distinguish authorization, capture, asynchronous processing, supplier collection, property collection, partial capture, duplicate appearance, FX difference, refund requested, supplier refund approved, processor refund pending/failed, funds received, chargeback inquiry, formal dispute, and settlement reversal.

The customer sees one monetary timeline with plain labels. Operators retain separate ledgers and evidence. A concurrent refund and chargeback is frozen for specialist review to prevent double reimbursement or contradictory evidence.

## 7. The orchestration loop

Every case runs the same auditable loop:

1. **Receive:** accept free language, voice transcript, structured alert, provider event, operator report, or monitoring signal.
2. **Safety gate:** detect life safety, abuse/trafficking concern, vulnerable traveler, lost minor, medical emergency, or destination warning; route to qualified human and official emergency channel.
3. **Resolve identity and scope:** identify who is reporting, their authority, affected travelers/services, current location, and next irreversible deadline.
4. **Retrieve truth:** obtain current provider resources, payment state, entitlements, policies, communications, and prior operations. Never act on a stale card alone.
5. **Build impact graph:** mark direct failure, dependent services, buffers, accessibility/minor requirements, cancellation windows, and financial exposure.
6. **Protect:** prevent duplicate execution, preserve a hold or claim deadline when authorized, alert a property of late arrival, or make another reversible protective action defined by policy.
7. **Generate options:** provide at least the viable “continue,” “replace,” “cancel/refund,” and “human exception request” branches when they exist, with exact expiries and unresolved facts.
8. **Authorize:** identify the legal/contractual approver for every consequential action. Group changes can require per-traveler or guardian scope.
9. **Execute:** run a saga of explicit operations. Each mutation has one logical ID and provider idempotency mapping.
10. **Reconcile:** retrieve authoritative states after ambiguous responses, process events, and compare provider/payment/fulfillment ledgers.
11. **Monitor:** continue until the traveler has the usable entitlement or funds, not merely until the API accepted the request.
12. **Resolve and learn:** record outcome, loss, saved value, unmet obligation, evidence, root cause, supplier performance, and prevention task.

### 7.1 Cross-supplier saga, not a global transaction

Travel suppliers do not share one atomic commit. A Thailand recovery might involve a new flight, airport transfer, first-night hotel change, activity cancellation, eSIM extension, and insurance notice. The orchestrator must:

- rank actions by deadline and reversibility;
- use holds before irreversible purchases where supported;
- present a single customer authorization packet with per-component effects;
- execute each component independently;
- stop when a prerequisite becomes uncertain;
- invoke explicit compensation actions only where policy and authorization allow; and
- keep partial success visible and recoverable.

“Rollback” is never assumed. Cancelling a newly issued flight may carry a penalty even if the hotel replacement failed.

## 8. Customer-simple versus operator-deep

| Customer sees | Operator/system must know |
| --- | --- |
| “We found your affected flight and are checking the connection.” | Exact tickets/orders, schedule-event source, segment status, minimum connection assumptions, baggage/interline boundary, SSRs, dependent services, stale fields, and jurisdiction candidates. |
| “Two safe alternatives are ready until 18:20.” | Revalidated offer versions, expiry, seats, fare rules, refund/penalty, payment delta, airport/terminal changes, accessibility, traveler authorization scope, and supplier capability. |
| “Approve the recommended option.” | Authenticated approver, immutable decision digest, price/policy delta, consent text/version, idempotency key, case version, and operation plan. |
| “The airline is processing the change.” | Provider operation is pending or uncertain; payment may be separate; duplicate action is blocked; reconciliation timer and escalation owner are active. |
| “Your new ticket is ready. We also moved the transfer and notified the hotel.” | Ticket/document evidence exists, old coupons are accounted for, transfer and hotel have separate confirmed states, deadlines were recomputed, and notification delivery was recorded. |
| “Your refund is on its way.” | Supplier credit, processor refund, destination, amount/currency, pending/failed state, expected follow-up, settlement impact, and chargeback collision guard. |

The UI uses calm progress language only for verified states. Animation can show real event movement, but never simulates a supplier response or a completed task.

## 9. SLA and escalation model

The following are proposed internal service objectives, not statutory promises. Production values require staffing, partner contracts, region coverage, and legal approval.

| Tier | Example | Automated acknowledgment | Human ownership objective | First safe plan objective | Monitoring cadence |
| --- | --- | ---: | ---: | ---: | ---: |
| P0 safety | imminent danger, lost minor, acute medical event, stranded vulnerable traveler, suspected exploitation | immediate | 2 minutes, 24/7 qualified queue | 5 minutes, including official emergency handoff | continuous/5 minutes until handed off and stable |
| P1 trip-critical | departure/arrival at risk within 6 hours, denied room at night, missed connection, accessibility failure, supplier cancellation near service | 30 seconds | 5 minutes | 15 minutes | 5-15 minutes |
| P2 urgent | service within 48 hours, ticket not issued, invalid card threatens booking, activity/transfer failure with time to recover | 1 minute | 15 minutes | 60 minutes | 30-60 minutes |
| P3 servicing | voluntary change, cancellation/refund, evidence collection, non-immediate complaint | 1 minute | 4 business hours | same business day | deadline-based |
| P4 follow-up | settlement, commission, closed-trip quality issue, non-urgent document correction | immediate | 1 business day | 2 business days | daily/weekly |

### 9.1 Deadline-derived priority

Static severity is insufficient. The effective action deadline is the earliest of:

`internal SLA`, `supplier cutoff - safety margin`, `trip event - intervention time`, `legal/policy notice deadline - evidence margin`, and `vulnerability escalation threshold`.

Priority increases automatically when:

- time to departure/check-in/pickup shrinks;
- the traveler is a minor, alone, medically vulnerable, mobility-dependent, or without connectivity;
- local time is night and safe accommodation/transport is missing;
- several travelers or services are affected;
- a nonrefundable deadline is approaching;
- the supplier is unresponsive or a provider operation is uncertain; or
- a mass disruption reduces inventory.

### 9.2 Ownership and escalation

- One named case owner remains accountable even when specialist tasks are delegated.
- Every timer has an owner, due time, escalation target, and deterministic missed-timer event.
- Supplier nonresponse escalates by channel: API retrieval/event -> documented partner support -> duty manager/account channel -> authorized replacement/exception path.
- P0 and sensitive medical/minor cases never wait in an ordinary sales queue.
- A handoff requires positive acceptance; changing an assignee does not prove ownership.
- Operator shift changes include a structured summary, open risks, next deadline, last verified supplier states, customer expectation, and prohibited duplicate actions.

## 10. Idempotency, event, and reconciliation rules

### 10.1 Command contract

Every consequential command includes:

- `operation_id` generated once for the business action;
- aggregate and expected version;
- actor, authority, and authorization evidence;
- exact normalized request digest;
- provider/environment/capability;
- provider idempotency or correlation reference;
- case and trip correlation IDs;
- money ledger and currency in integer minor units;
- offer/policy/decision versions and expiries;
- created, started, timeout, and next-reconcile times; and
- result: `queued`, `started`, `succeeded`, `failed`, `uncertain`, or `reconciled`.

Reusing an idempotency key with a different digest is a conflict. A timeout after dispatch becomes `uncertain`; it never becomes safe to retry merely because the customer refreshed.

### 10.2 Event envelope

Every event contains event ID, aggregate/version, type/schema version, occurred and received times, source, actor, correlation/causation IDs, operation ID, prior and new state, provider reference, evidence digests, and redaction classification. Sensitive payloads are referenced, not copied into a general event stream.

Events are append-only. Corrections are new events. Derived projections are rebuildable and versioned.

### 10.3 Webhook inbox and outbox

- Verify signature against the raw body and provider-specific replay window.
- Deduplicate by provider event ID plus account/environment.
- Persist receipt before processing; acknowledge according to provider rules.
- Tolerate duplicate and out-of-order events.
- Retrieve the current provider object before taking a consequential follow-up action when the event is only a signal.
- Use a durable outbox for customer notifications and provider commands.
- Record delivery and provider request IDs without logging secrets or full sensitive payloads.

### 10.4 Reconciliation schedules

Reconcile immediately after an ambiguous mutation, then with bounded backoff. Separately run:

- near-term order reconciliation for pending/uncertain bookings;
- departure readiness checks for unfulfilled entitlements;
- disruption polling where the provider lacks reliable events;
- daily order/payment/refund mismatch checks;
- supplier cancellation and modification feed ingestion;
- refund aging and failed-refund follow-up;
- payment dispute/deadline checks;
- supplier/affiliate settlement reconciliation; and
- orphan detection for payment without order, order without fulfillment, fulfillment without traveler delivery, and cancellation without refund accounting.

Reconciliation closes uncertainty only with authoritative evidence or an explicit manual determination. Silence is not success.

## 11. Stress-test scenario matrix

Each scenario is a deterministic fixture with a clock, provider scripts, expected events, customer projection, operator tasks, and ledger assertions. No fixture may rely on a real charge or real booking.

| # | Injected scenario | Expected behavior and invariant |
| ---: | --- | --- |
| 1 | Booking POST times out after provider confirmation | Mark uncertain, block duplicate, retrieve with original correlation ID, then project confirmed exactly once. |
| 2 | Booking returns malformed HTML/502 | Same as timeout; transport failure cannot decide business outcome. |
| 3 | Customer double-clicks approve on two devices | One logical operation; same digest replays, different digest conflicts. |
| 4 | Payment captured, supplier rejects | Open P1 financial recovery; no fulfillment; void/refund according to processor state and reconcile receipt. |
| 5 | Supplier confirms, payment remains `requires_action` | Do not claim completion; preserve/monitor supplier deadline and request step-up action. |
| 6 | Hold expires during 3DS | Stop confirmation, revalidate price/inventory, require new approval for any material delta. |
| 7 | Webhook is duplicated 20 times | One inbox event and one state transition; delivery remains idempotent. |
| 8 | “Cancelled” webhook arrives before “confirmed” | Rebuild by provider sequence/current retrieval; never regress to confirmed. |
| 9 | Flight schedule change breaks a through connection | Detect graph impact, preserve SSRs, generate reroute/refund branches, reissue before marking recovered. |
| 10 | Self-transfer inbound delay | Explicitly show independent-ticket boundary; evaluate new flight, hotel, transfer, and insurance paths separately. |
| 11 | Airline cancels first leg but return remains active | Retrieve entire order/ticket, avoid accidental return cancellation, present legally/policy-valid choices. |
| 12 | Replacement flight uses another airport | Recompute transfer, visa/transit, baggage, accessibility, hotel, and timing consequences before approval. |
| 13 | Name typo discovered after ticketing | Create servicing case; do not silently edit profile or supplier passenger; quote permitted correction/change. |
| 14 | Passport expires before destination requirement | Block ready-to-travel status using current authorized rules; route to document-resolution options. |
| 15 | Transit rule changes after booking | Alert affected travelers, re-evaluate admissibility and alternate route, record rule-source version. |
| 16 | Minor travels with one parent and authority evidence is missing | Ask only for itinerary-relevant proof; do not infer consent; escalate before cutoff. |
| 17 | Rebook loses wheelchair SSR | Keep case open and trip at risk until the new carrier/airport acknowledges required assistance. |
| 18 | Wheelchair arrives damaged | P1 accessibility case, carrier report/evidence, safe loaner/replacement coordination, insurance route if applicable. |
| 19 | Bag misses an interline transfer | Track tag and handoff evidence, update delivery address as itinerary changes, preserve expense/claim evidence. |
| 20 | Acute medical event | Immediate official emergency and policy-assistance routing; minimum data; no diagnosis or coverage promise. |
| 21 | Hotel cancels two hours before midnight check-in | Secure accessible/safe replacement first, preserve original cancellation evidence, keep financial cases separate. |
| 22 | Property cannot find a valid confirmation | Verify supplier itinerary and property reference, contact both channels, avoid duplicate nonrefundable room unless authorized. |
| 23 | Property-collect card becomes invalid | Notify traveler securely, use supplier recapture flow if available, monitor cancellation deadline/status. |
| 24 | Late flight causes hotel no-show | Notify property with delivery evidence, request late-arrival acknowledgment, monitor booking status. |
| 25 | Room is not accessible as confirmed | P1 recovery preserving accessibility needs; capture evidence; replacement and complaint/claim are separate tasks. |
| 26 | Transfer driver no-show and supplier phone is unanswered | Verify meeting point, start bounded supplier timer, present authorized safe fallback, preserve original claim. |
| 27 | Flight lands at wrong terminal for booked transfer | Recompute meeting point and wait entitlement; do not claim driver update until acknowledged. |
| 28 | Required child seat is missing | Treat as safety-critical; do not tell traveler to accept unsafe transport; source a compliant alternative. |
| 29 | Activity supplier cancels for weather | Retrieve cancellation/refund state, cancel dependent transfer/dining if authorized, offer alternatives only after live check. |
| 30 | Manual-confirm activity remains pending past internal deadline | Escalate and offer reversible alternative; prevent simultaneous confirmations from causing duplicates. |
| 31 | Partial group cancels an activity | Quote per-participant economics, preserve remaining travelers, reconcile partial refund exactly. |
| 32 | Restaurant reports no-show but traveler attended | Preserve confirmation/payment/location/communication evidence and open a charge dispute path without rewriting attendance as fact. |
| 33 | Severe allergy disclosed in free text | Restrict data, obtain explicit confirmation and venue acknowledgment; do not translate it into a safety guarantee. |
| 34 | Insurance claimant lacks cancellation invoice | Generate a precise evidence task, preserve provider correspondence, request alternative acceptable evidence from insurer workflow. |
| 35 | Traveler asks Tra-Vel whether treatment is covered before emergency care | Route emergency first and insurer assistance; no medical or coverage adjudication by generic agent/AI. |
| 36 | eSIM purchase completes but delivery is delayed | Show pending delivery truth, monitor provider, preserve offline fallback, refund only after provider/processor evidence. |
| 37 | eSIM is installed but device is carrier-locked | Run device-specific checklist, avoid exposing activation credentials, source fallback connectivity. |
| 38 | Equipment delivery fails before a booked dive | Protect activity deadline, source correct-fit/safe replacement, keep equipment and activity cancellation economics separate. |
| 39 | Supplier approves refund but processor refund fails | Customer view remains “refund needs attention”; retry/alternate payout only through authorized finance workflow. |
| 40 | Chargeback opens while refund is pending | Freeze duplicate reimbursement, preserve chronological evidence, specialist review with both deadlines. |
| 41 | Customer changes refund destination in chat | Reject as insufficient authorization; require step-up and processor-supported destination rules. |
| 42 | Supplier API and support are both unavailable | Circuit breaker, no blind retry, alternative-provider plan, management escalation, mass-incident grouping. |
| 43 | 10,000 travelers affected by a regional closure | Shared incident intelligence plus individual case isolation; prioritize by safety/time/vulnerability; no PII in broadcast updates. |
| 44 | Destination warning rises after travelers depart | Current official alert, geofenced affected-trip query, safe check-in, itinerary options, and consular/insurer routing. |
| 45 | Magic link is opened by a scanner before the traveler | Token exchange design tolerates prefetch/scanner or requires explicit confirmation; no sensitive disclosure on initial GET. |
| 46 | Traveler reports a stolen phone | Revoke scoped sessions, use independent recovery, keep public case intake available without leaking itinerary. |
| 47 | Daylight-saving change shifts a cancellation cutoff | Store supplier timestamp/offset and UTC instant; test both clocks; never recompute from display text alone. |
| 48 | JPY/ILS/USD partial refund produces rounding differences | Integer minor-unit ledgers, explicit FX source/time, no cross-currency arithmetic without a booked conversion entry. |
| 49 | Two family members issue conflicting change instructions | Pause, resolve authority and per-traveler scope, preserve existing booking and both communications. |
| 50 | Sensitive medical/passport content appears in a provider error | Quarantine/redact payload, security event, no general logs/analytics, role-limited evidence review. |
| 51 | Three connection disruptions overlap | Topologically order the broken dependencies, select one recovery for review, and preserve every still-viable component. |
| 52 | One flight changes while lodging, insurance, and ground services remain valid | Put only the flight and its proven dependents in the affected partition; preserve every unrelated vertical. |
| 53 | A five-person package contains pending consent, guardian, eligibility, or accessibility constraints | Block only the people and components that require the unresolved fact; keep the three traveler partitions and component partitions exhaustive and disjoint. |
| 54 | Aircraft or terminal changes after seats and assistance were confirmed | Recheck seat assignment, SSR, wheelchair handoff, baggage allowance, and minimum connection time before any recovery can be authorized. |
| 55 | Israel transit data is stale or unavailable | Prohibit a current-route claim, preserve the stale-source evidence, and expose the official route planner, official operator channel, and human travel desk as explicit fallbacks. |

### 11.1 Required assertions for every test

- No duplicate booking, charge, cancellation, refund, claim, or customer message.
- No success projection without authoritative evidence.
- No irreversible action without current authority and a non-expired decision.
- No loss of SSR, minor, accessibility, or safety constraints during substitution.
- No hidden partial failure across package components.
- No raw secrets, full payment data, unnecessary passport data, or medical narrative in general logs/events.
- Every open risk has an owner and timer.
- Every monetary movement balances by currency.
- Every customer-visible timestamp includes the relevant local timezone.
- Replaying all accepted events produces the same current projection.

## 12. Operator cockpit requirements

The operator workspace is not a CRM note screen. It needs:

- live trip timeline with local/UTC clocks and next deadline;
- dependency graph highlighting broken and threatened nodes;
- traveler roster with roles, authority, vulnerability/accessibility badges, and restricted-data boundaries;
- independent supplier, payment, fulfillment, refund, and settlement states;
- provider references and last verified timestamps;
- decision workspace comparing viable options and downstream effects;
- exact action preview, required approver, expiry, and idempotency state;
- evidence checklist generated by playbook/policy;
- communications timeline with delivery status and commitments;
- supplier contact ladder and nonresponse timer;
- shift handoff summary and accountable owner;
- mass-incident parent linking without merging private case records;
- “do not retry” and “uncertain” controls that cannot be dismissed casually;
- redaction-aware access with reason-for-access audit; and
- resolution checklist proving traveler continuity, financial follow-up, and evidence preservation.

AI may summarize, translate, extract deadlines, rank options, and draft messages, but it cannot silently mutate orders or invent supplier facts. Every proposed tool action is schema-validated, policy-gated, displayed to the operator or authorized traveler when required, executed with a bounded credential, and reconciled afterward.

## 13. Customer cockpit requirements

The traveler sees one coherent trip rather than supplier silos:

- **Now:** current location/time, next event, how to get there, required document/voucher, and offline contact;
- **Trip health:** healthy, attention needed, action required, recovery underway, or recovered, based only on real states;
- **One-tap help:** “My flight changed,” “My bag is missing,” “I cannot check in,” “My driver is not here,” “I am sick/injured,” “My eSIM does not work,” and free-language/voice intake;
- **Agent progress:** observed event, checks running, alternatives ready, approval needed, supplier processing, entitlement issued, dependent services updated;
- **Decision card:** recommendation, alternatives, price delta, refund/penalty, critical trade-offs, deadline, and exactly what approval will do;
- **Documents:** least-sensitive offline-ready itinerary, vouchers, policy contacts, receipts, and evidence tasks;
- **Money:** authorized, paid, supplier-confirmed, refundable, refund requested, refund processing, received, or disputed, never one vague total status;
- **Control:** revoke links, change communication preference, delegate carefully, download case history, or escalate to a human.

The customer reports the symptom once. The system asks only the next smallest necessary question, reuses already verified trip facts, and fans work out to every affected supplier.

## 14. Privacy, medical, safety, and legal boundaries

These boundaries make VIP service dependable rather than conservative:

1. **Safety first:** emergency routing is never blocked by account recovery, payment, or document upload.
2. **Minimum necessary disclosure:** each adapter receives only the fields required for its current action.
3. **Medical separation:** health/insurance evidence is encrypted and role-segregated; generic search, analytics, marketing, and AI transcripts do not receive it.
4. **No medical practice or coverage adjudication:** the system can route, collect, organize, and communicate; qualified providers/insurers decide treatment and coverage.
5. **Children and dependents:** verified authority and least-privilege access are explicit; family account membership is not consent.
6. **Payment isolation:** use a compliant hosted/tokenized processor model; do not store card verification values or let agents request them in recorded channels.
7. **Purpose-bound communication:** service alerts, claim coordination, and marketing are separate permissions.
8. **Retention classes:** active travel operations, financial/accounting evidence, dispute/claim evidence, identity documents, medical data, recordings, and ordinary support messages have separate lawful retention and deletion rules.
9. **Jurisdiction engine:** rights and disclosures are evaluated from current source packets; a qualified reviewer approves production policy logic and customer agreements.
10. **Emergency authority:** Tra-Vel identifies itself accurately and never presents itself as an airline, insurer, government, police, medical provider, or emergency dispatcher.

## 15. Readiness and quality gates

### 15.1 Supplier/vertical launch gate

No vertical becomes production-transactable until it has:

- a verified commercial relationship and granular capability declaration;
- sandbox and contract fixtures for every mutation and error family;
- current policy/price/availability revalidation;
- provider-specific idempotency and uncertain-outcome recovery;
- fulfillment evidence definition;
- change/cancel/refund servicing paths;
- events or a tested polling/reconciliation plan;
- escalation contacts and support-hour model;
- settlement and dispute ownership;
- privacy/security review and credential rotation; and
- customer copy that accurately reflects merchant, supplier, and responsibility boundaries.

### 15.2 Service metrics

Measure outcomes, not ticket closure volume:

- time to safety handoff, ownership, first viable plan, approval, execution, and verified recovery;
- percentage of cases identified before the traveler reports them;
- percentage of disrupted dependencies found automatically;
- duplicate-side-effect rate, uncertain-operation age, and reconciliation lag;
- traveler re-explanation rate and number of repeated evidence requests;
- entitlement issuance failure and unfulfilled-paid-order rate;
- SSR/accessibility preservation rate after changes;
- supplier response/exception/false-confirmation rate;
- refund time from supplier approval to customer receipt;
- reopened case, unresolved financial tail, chargeback, and complaint rates;
- PII leakage/redaction failures and unauthorized-access attempts; and
- customer confidence after disruption, not only after an uncomplicated sale.

### 15.3 Definition of “VIP resolved”

A case is resolved only when:

- immediate safety is stable or transferred to the proper authority;
- the traveler knows the next action and can access it offline if needed;
- each affected service is confirmed, replaced, cancelled, or explicitly accepted as lost;
- required entitlements are usable and delivered;
- dependent services have been rechecked;
- all authorized money actions are accounted for, with pending financial tails still monitored separately;
- evidence and customer communications are preserved;
- no timer or uncertain operation is orphaned; and
- the traveler receives a concise outcome plus any remaining monitored item.

That is the operational promise behind the simple customer message: “Tell us once. We will coordinate the trip around you.”

## 16. Primary-source reference index

### Identity, documents, and privacy

- [ICAO API Guidelines and PNR Reporting Standards](https://www.icao.int/facilitation-programmes/api-guidelines-and-pnr-reporting-standards)
- [IATA One ID](https://www.iata.org/en/programs/passenger/one-id/)
- [IATA Travel Centre / Timatic](https://www.iata.org/en/services/compliance/timatic/travel-documentation/)
- [NIST SP 800-63B](https://pages.nist.gov/800-63-4/sp800-63b.html)
- [Israel Privacy Protection Authority: sensitive-database notice](https://www.gov.il/he/service/notice-obligation)
- [Israel Privacy Protection Authority: access-log guidance](https://www.gov.il/BlobFolder/reports/takana10d/he/Takna10_Tikon13.pdf)

### Air, baggage, accessibility, and passenger rules

- [IATA baggage tracking / Resolution 753](https://www.iata.org/en/programs/ops-infra/baggage/baggage-tracking)
- [IATA accessibility](https://www.iata.org/en/programs/passenger/accessibility/)
- [IATA NDC offer and order distribution](https://www.iata.org/en/programs/airline-distribution/retailing/ndc)
- [IATA NDC servicing](https://www.iata.org/contentassets/6de4dce5f38b45ce82b0db42acd23d1c/ndc-infocus-servicing.pdf)
- [IATA BSP Manual for Agents](https://www.iata.org/en/fmc-documents/a4938a2d-e11c-44f5-b88f-dd1e548829ef/)
- [EU air passenger rights](https://europa.eu/youreurope/citizens/travel/passenger-rights/air/index_en.htm)
- [EU reduced-mobility rights](https://europa.eu/youreurope/citizens/travel/transport-disability/reduced-mobility/index_en.htm)
- [U.S. DOT refunds](https://www.transportation.gov/individuals/aviation-consumer-protection/refunds)
- [U.S. DOT wheelchair final rule](https://www.transportation.gov/regulations/federal-register-documents/2024-29731)
- [Israel Aviation Services Law text](https://fs.knesset.gov.il/18/law/18_lsr_301013.pdf)
- [Duffel order changes](https://duffel.com/docs/api/order-changes)

### Lodging, activities, transfers, and dining

- [Booking.com Demand order lifecycle](https://developers.booking.com/demand/docs/orders-api/overview)
- [Booking.com cancellation policies](https://developers.booking.com/demand/docs/orders-api/cancellation-policies)
- [Booking.com cancellation handling](https://developers.booking.com/demand/docs/orders-api/cancel-order)
- [Expedia handling booking requests](https://developers.expediagroup.com/rapid/lodging/reference/handle-booking-reqs)
- [Expedia Manage Booking API](https://developers.expediagroup.com/rapid/lodging/manage-booking/about-mg-booking-api)
- [Viator Partner API technical documentation](https://docs.viator.com/partner-api/technical/)
- [Amadeus cars and transfers](https://developers.amadeus.com/self-service/apis-docs/guides/developer-guides/resources/cars-transfers/)
- [Booking.com car-rental Demand API](https://developers.booking.com/demand/docs/open-api/demand-api/cars)
- [Booking.com Demand API change log](https://developers.booking.com/demand/docs/whats-new/archive)
- [Google transit route semantics](https://developers.google.com/maps/documentation/routes/transit-route)
- [GTFS Realtime best practices](https://gtfs.org/documentation/realtime/realtime-best-practices/)
- [GTFS Realtime service alerts](https://gtfs.org/documentation/realtime/feed-entities/service-alerts/)
- [OpenTable terms and no-show policy](https://www.opentable.com/c/legal/terms-and-conditions/)
- [Israel Ministry of Tourism complaint service](https://www.gov.il/en/service/review_and_complaints)
- [Israel Equal Rights Commission accessibility complaint service](https://www.gov.il/he/service/complaint_discrimination_inaccessibility_people_with_disabilities)

### Insurance, connectivity, payments, and emergencies

- [XCover emergency assistance](https://www.xcover.com/en-us/help/xcover-assist)
- [XCover travel claim evidence](https://www.xcover.com/en/help/travel-claim-documents)
- [Airalo eSIM troubleshooting](https://www.airalo.com/help/troubleshooting/0UEL63PDK5IJ/why-is-my-esim-not-working/X7ZYBK1S7GA8/)
- [Airalo offline mode](https://www.airalo.com/help/using-managing-esims/ZSEEHBT5HW6F/what-is-offline-mode-and-when-should-i-use-it/IOTPG8TCPBO5)
- [Stripe PaymentIntent lifecycle](https://docs.stripe.com/payments/paymentintents/lifecycle)
- [Stripe idempotency](https://docs.stripe.com/api/idempotent_requests)
- [Stripe webhooks](https://docs.stripe.com/webhooks)
- [Stripe refunds](https://docs.stripe.com/refunds)
- [Stripe disputes](https://docs.stripe.com/disputes/how-disputes-work)
- [PCI SSC outsourced-payment responsibility](https://www.pcisecuritystandards.org/faqs/does-pci-dss-apply-to-merchants-who-outsource-all-payment-processing-operations-and-never-store-process-or-transmit-cardholder-data/)
- [Israel NSC travel warnings](https://www.gov.il/en/departments/dynamiccollectors/travel-warnings-nsc)
- [Israel MFA replacement of travel documents abroad](https://www.gov.il/en/service/issuing_and_extending_travel_documents)
