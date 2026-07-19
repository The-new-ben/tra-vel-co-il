# Tra-Vel destination publication backlog

Updated: 2026-07-18

Status: planning contract only. This file does not authorize publication, create public routes, or claim search volume.

## Scope and evidence boundary

This backlog covers the eight destination states currently supported by the Earth and discovery contract: Budapest, Prague, Vienna, Athens, Dubai, Bangkok, Tokyo, and Lisbon. It is based on repository evidence plus the explicitly dated competitor and official-source snapshots recorded below. Repository evidence includes:

- `theme/tra-vel-v2/assets/data/discovery-demo.json`
- `theme/tra-vel-v2/assets/data/editorial-directory.json`
- `content/seo/content-opportunity-registry.json`
- `content/guides/*.sources.json` and their linked Hebrew articles
- `theme/tra-vel-v2/inc/guides.php`, `inc/seo.php`, and `inc/discovery.php`
- the destination, directory, map, homepage, and product templates
- `docs/SEO_AEO_GUIDE_SYSTEM.md` and `docs/strategy/seo-content-tree-2026.md`

No current keyword volume, ranking, price, schedule, availability, or supplier claim is inferred here. Competitor pages are used only to identify traveler tasks and commercial page shapes. Official sources establish the evidence categories that a future packet must recheck. The order is a local product and content hypothesis. Re-score it with Search Console, current Israeli search-demand evidence, paid-query evidence, and supplier conversion data before commissioning each wave.

## Decision summary

The Earth has eight usable destination states and nineteen route-planning records. Only four states currently own a crawlable, publish-ready flagship guide. The other four top-level candidates now have complete evidence-bound drafts in `editorial-review`: Vienna has 7,623 visible words, 16 sources, and 20 mapped facts; Dubai has 6,819 visible words, 31 sources, and 32 mapped facts; Tokyo has 8,811 visible words, 45 sources, and 46 mapped facts; Lisbon has 9,603 visible words, 37 sources, and 40 mapped facts. Their child intents remain uniquely reserved as backlog, including mapless Japan, Portugal, and Porto owners that cannot replace a city Earth state. None has a public guide route or publication approval, so all four correctly fall back to `/destinations/` instead of exposing a thin or unreviewed URL.

Bangkok is a deliberate exception. The `bangkok` Earth state resolves to the published country pillar at `/destinations/thailand/`. The distinct city intent is reserved at `/destinations/thailand/bangkok/` and now has an 8,399-word, 46-source, 32-fact draft in `editorial-review`, but it remains backlog and unpublished. Do not create a competing `/destinations/bangkok/` page.

Publication order starts with Vienna because it already has a directory record, Earth state, two route-planning records, template coordinate support, a destination-specific image, an `editorial-review` packet and article, and exact backlog owners for the pillar and seven child decisions. Its remaining work is named-editor review, publish-time volatile fact and link revalidation, explicit promotion and routing approval, and publication integration, not writing the flagship draft or building product discovery infrastructure.

## Current Earth to search inventory

| Earth state | Airport context | Directory record | Current canonical guide | Packet and article | Registry state | Current safe behavior |
| --- | --- | --- | --- | --- | --- | --- |
| `budapest` | `BUD` | Budapest, published | `/destinations/budapest/` | `budapest-2026.sources.json` and `budapest-2026.he.html` | `content-ready` | Direct guide link |
| `prague` | `PRG` | Prague, published | `/destinations/prague/` | `prague-2026.sources.json` and `prague-2026.he.html` | `content-ready` | Direct guide link |
| `vienna` | `VIE` | Vienna, research | Missing: `/destinations/vienna/` | `vienna-2026.sources.json` and `vienna-2026.he.html`, both `editorial-review` | Pillar plus seven child intents are backlog | Directory anchor or map, then `/destinations/` fallback |
| `athens` | `ATH` | Athens, published | `/destinations/athens/` | `athens-2026.sources.json` and `athens-2026.he.html` | `content-ready` | Direct guide link |
| `dubai` | `DXB` | Dubai, research | Missing: `/destinations/dubai/` | `dubai-2026.sources.json` and `dubai-2026.he.html`, both `editorial-review` | Pillar plus eight child intents are backlog | Directory anchor or map, then `/destinations/` fallback |
| `bangkok` | `BKK` | Thailand, published; Bangkok child, editorial review | `/destinations/thailand/` | Published `thailand-2026` pillar plus unpublished `bangkok-2026` nested draft | Country pillar `content-ready`; city owner backlog | Direct country-pillar link until explicit Bangkok publication |
| `tokyo` | `HND` | Tokyo, research | Missing: `/destinations/tokyo/` | `tokyo-2026.sources.json` and `tokyo-2026.he.html`, both `editorial-review` | Pillar plus nine child intents are backlog; adjacent Japan multi-city owner has no map state | Directory anchor or map, then `/destinations/` fallback |
| `lisbon` | `LIS` | Lisbon, research | Missing: `/destinations/lisbon/` | `lisbon-2026.sources.json` and `lisbon-2026.he.html`, both `editorial-review` | Pillar plus ten child intents are backlog; adjacent Portugal and Porto owners have no map state | Directory anchor or map, then `/destinations/` fallback |

The discovery resolver reads published guide paths from the editorial directory. An unpublished state returns `/destinations/`. The client also has a four-route published allowlist in `assets/js/app.js`. Both controls must change only after the matching guide passes the publication contract.

## Published flagship asset inventory

The following counts are the registered editorial-directory counts and source-packet counts. All four packets pass the local guide validator.

| Pillar | Registered words | Sources | Planned sections | Source-mapped facts | Map state | Existing revenue children |
| --- | ---: | ---: | ---: | ---: | --- | --- |
| Budapest | 5,256 | 16 | 15 | 12 | `budapest` | Flights, packages, and hotels are registered as backlog |
| Prague | 5,155 | 14 | 17 | 14 | `prague` | Flights, packages, and hotels are registered as backlog |
| Athens | 5,398 | 17 | 15 | 12 | `athens` | Flights and packages are registered as backlog; hotels are not yet owned |
| Thailand | 5,333 | 18 | 18 | 16 | `bangkok` | Flights, packages, Bangkok, Phuket, family budget, and kosher travel are registered as backlog |

These pillars are not complete clusters. They have editorial depth, but most transactional and decision children are still backlog. The missing Earth hubs should not erase that second gap.

## Exact missing canonical guide pages

| Priority | Proposed packet ID | Canonical owner | Dominant primary intent | Relationship to the Earth |
| ---: | --- | --- | --- | --- |
| 0 | `vienna-2026` | `/destinations/vienna/` | `מדריך וינה לישראלים` | Canonical hub for `vienna` |
| 1 | `dubai-2026` | `/destinations/dubai/` | `מדריך דובאי לישראלים` | Canonical hub for `dubai` |
| 2 | `tokyo-2026` | `/destinations/tokyo/` | `מדריך טוקיו לישראלים` | Canonical hub for `tokyo` |
| 3 | `lisbon-2026` | `/destinations/lisbon/` | `מדריך ליסבון לישראלים` | Canonical hub for `lisbon` |
| 4 | `bangkok-2026` | `/destinations/thailand/bangkok/` | `מדריך בנגקוק לישראלים` | City child of Thailand for `bangkok`, not a second Earth pillar |

Tokyo remains a one-level destination hub under the current directory and schema contract. `/destinations/japan/` is now reserved as a separate backlog owner for country-wide and multi-city planning, with `mapState: null`. It must earn real country-level depth before publication and must not replace, inherit, or duplicate the `tokyo` Earth state or the Tokyo city intent.

### Canonical and registry boundary

Vienna, Dubai, Tokyo, and Lisbon each have backlog ownership plus an `editorial-review` packet and article. They can use the one-level destination contract only after named-editor approval, publish-time revalidation, destination-asset approval, promotion to `publish-ready`, matching directory evidence, and an explicit routing decision. The repository also has a complete editorial draft using the separate nested-guide contract for Bangkok:

- the editorial directory still contains exactly eight unique Earth records and now carries a separate `supporting_guides` collection, which is intentionally empty until a city guide passes the full gate;
- validators can distinguish a top-level Earth pillar from a nested supporting guide without creating a ninth map state or changing the Thailand canonical;
- visible and structured breadcrumbs derive the real WordPress ancestor chain, so a future Bangkok page can preserve Home, Destinations, Thailand, and Bangkok;
- guide metadata records `_tra_vel_publication_status`, while strict runtime enforcement remains deferred until existing public guide metadata is safely backfilled;
- the Earth and discovery resolver still send the `bangkok` state to `/destinations/thailand/`. No Bangkok page, route, directory card, or public status has been created.

Bangkok therefore no longer needs a hierarchy redesign, evidence packet, or flagship article. Its 8,399-word article and 46-source packet remain in `editorial-review`. It still needs a named editor, licensed destination asset, publish-time revalidation, exact WordPress parent, supporting-guide registry approval, and explicit promotion. Do not solve those remaining requirements by adding Bangkok as a ninth top-level Earth record or by replacing Thailand's canonical. City links should appear only in explicit Bangkok contexts after publication.

## Missing intent clusters and page ownership

The Hebrew phrases below are intent labels, not volume claims. Each URL owns one decision. Synonyms and close variants belong inside the owner page unless evidence shows a materially different task.

### Vienna cluster

| Page owner | Canonical path | Intent boundary | Primary conversion |
| --- | --- | --- | --- |
| Vienna pillar | `/destinations/vienna/` | Complete first-trip and repeat-trip planning for Vienna | Open Vienna in the map and check dates |
| Flights | `/flights/vienna/` | `טיסות לוינה`, route, baggage, direct versus connection | Run a dated flight comparison |
| Packages | `/packages/vienna/` | `חבילות נופש לוינה`, flight plus stay tradeoffs | Compare a complete verified package |
| Hotels | `/hotels/vienna/` | `מלונות בוינה` and area-led hotel selection | Compare rooms by area and terms |
| Areas | `/guides/vienna/where-to-stay/` | Innere Stadt versus transit-ring neighborhoods | Select an area, then open hotels |
| Airport transfer | `/guides/vienna/airport-to-city/` | Airport to accommodation by arrival time and party needs | Compare transfer options |
| Family | `/guides/vienna/vienna-with-children/` | Age-aware pace, rooms, transport, and activities | Build a family trip scope |
| Seasonal decision | `/guides/vienna/christmas-markets/` | Christmas-market trip planning only | Check dates, stay area, and activities |

The pillar owns broad Vienna planning. The seasonal page owns market-specific dates, locations, opening evidence, and route decisions. It must not repeat a general Vienna guide with a winter title.

### Dubai cluster

| Page owner | Canonical path | Intent boundary | Primary conversion |
| --- | --- | --- | --- |
| Dubai pillar | `/destinations/dubai/` | Complete Dubai holiday planning for Israeli travelers | Open Dubai in the map and check dates |
| Flights | `/flights/dubai/` | `טיסות לדובאי`, airport, baggage, and connection decisions | Run a dated flight comparison |
| Packages | `/packages/dubai/` | `חבילות לדובאי`, full-stay package composition | Compare a verified package |
| Hotels | `/hotels/dubai/` | `מלונות בדובאי` after the traveler chooses the district | Compare rooms, board, cancellation, and full cost |
| Areas | `/guides/dubai/where-to-stay/` | District, beach access, transport, and trip-pace tradeoffs without duplicating hotel inventory | Choose a district, then open `/hotels/dubai/` |
| DXB transfer | `/guides/dubai/dxb-airport-transfer/` | DXB terminal to the selected accommodation by arrival time, luggage, and party needs | Compare transfer options or request assistance |
| Family | `/guides/dubai/dubai-with-children/` | Heat-aware schedule, room setup, transfers, and activities | Build a family trip scope |
| Stopover | `/guides/dubai/stopover/` | Dubai as a protected connection window on a journey elsewhere, not a full holiday | Combine flights, stopover stay, and transfer |
| First trip and local laws | `/guides/dubai/first-trip-local-laws/` | Official entry, conduct, local-law, and travel-advisory checks for a first trip | Complete official checks, then return to the trip builder |

The pillar owns complete Dubai holiday planning. `/flights/dubai/` owns the Israel-to-Dubai destination journey, while the stopover page owns a connection window on a route to another destination. The hotel page owns transactional room comparison after district choice; the area guide owns the earlier location decision. The first-trip page must recheck current official entry guidance, local rules, and Israeli travel advice before publication and must never freeze a warning level or imply frictionless entry. It routes the traveler back to the editable trip after the checks are complete.

A Dubai and Abu Dhabi comparison is intentionally not registered in this wave. It needs distinct regional evidence and a proven one-base versus two-base conversion task before receiving a canonical owner.

#### Dated Dubai competitor and intent evidence

This snapshot was checked on 2026-07-18. It is evidence for page ownership and product design, not a claim about ranking position or search volume.

- [ISSTA's Dubai package page](https://www.issta.co.il/packages/dubai) puts flight-plus-hotel products and per-person pricing first. That supports a dedicated transactional package owner, but Tra-Vel must show the full party cost, inclusions, source time, and revalidation boundary instead of treating a merchandising card as a complete trip decision.
- [ISSTA's Dubai family article](https://blog.issta.co.il/dubai-with-kids/) confirms durable family-planning intent, while its older publication footprint reinforces the need for separate source dates, heat-aware scheduling, room composition, transport, and attraction rechecks.
- A recent specialist [where-to-stay guide](https://www.wheretostay.co.il/dubai/) organizes the decision around districts and proximity. Tra-Vel therefore keeps the editorial area choice separate from the later room-inventory comparison.
- A current specialist [Dubai connection guide](https://blog.solotravel.co.il/connectionindubai/) confirms stopover intent among Israelis. Connection safety, entry, and flight-operability statements are too volatile to inherit from a blog. They must be rechecked against the [live Israeli National Security Council travel-warning collector](https://www.gov.il/he/Departments/DynamicCollectors/travel-warnings-nsc), current airline and airport responses, and the [official UAE entry authority](https://u.ae/en/information-and-services/visa-and-emirates-id/Visa-information/do-you-need-an-entry-permit-or-a-visa-to-enter-the-uae).

The competitive response is not a copied landing page. Each intent has one crawlable owner, all nine owners share the `dubai` Earth state, and every page returns the traveler to the same editable trip, saved decisions, verified-source boundary, and next commercial action.

### Tokyo cluster

| Page owner | Canonical path | Intent boundary | Primary conversion |
| --- | --- | --- | --- |
| Tokyo city pillar | `/destinations/tokyo/` | Complete Tokyo city planning, duration, core areas, city experiences, and the next booking decision | Open Tokyo in the map and check dates |
| Flights | `/flights/tokyo/` | `טיסות לטוקיו`, arrival airport, connection, baggage, total journey, and change terms | Run a dated flight comparison |
| Packages | `/packages/tokyo/` | Tokyo flight plus stay, area, airport transfer, baggage, and change terms | Compare a verified package or request assistance |
| Hotels | `/hotels/tokyo/` | Live room comparison after the traveler chooses an area and station context | Compare rooms, configuration, cancellation, and full cost |
| Areas | `/guides/tokyo/where-to-stay/` | Neighborhood, station access, pace, and night-noise tradeoffs without duplicating hotel inventory | Choose an area, then open `/hotels/tokyo/` |
| Haneda or Narita | `/guides/tokyo/haneda-vs-narita/` | Airport choice and the complete airport-to-accommodation chain for the actual landing time, luggage, party, and accessibility needs | Compare both door-to-door arrival chains |
| City transit | `/guides/tokyo/getting-around/` | Travel inside Tokyo after arrival, including operators, station changes, walking, luggage, and step-free needs | Build day routes around real stations and transfers |
| Family | `/guides/tokyo/tokyo-with-children/` | Child ages, room setup, walking, stroller, station complexity, rest, and activities | Build a family trip scope |
| Accessible travel | `/guides/tokyo/accessible-travel/` | Step-free airport, station, hotel, room, and attraction planning for a stated access need | Confirm an accessible chain before booking |
| First trip | `/guides/tokyo/first-trip/` | Ordered pre-departure and first-arrival checklist, official entry links, safety preparation, and the first days in Tokyo | Complete official checks, then build the opening days |
| Japan multi-city pillar | `/destinations/japan/` | Country-wide routing between Tokyo and other Japanese bases, with rail and domestic-flight decisions; it does not own Tokyo city planning | Build a multi-city route without changing the Tokyo Earth owner |

All eleven canonical paths above remain ownership reservations with `status: backlog`. The Tokyo pillar now has an evidence packet and complete article in `editorial-review`, but this does not create a WordPress route, public page, indexable surface, or child content. The ten Tokyo owners share `mapState: tokyo`; the Japan-wide owner uses `mapState: null` so it cannot silently replace the city pin.

The Tokyo pillar owns the city decision. `/destinations/japan/` owns multi-city sequencing and country-wide routing, including Tokyo with Kyoto, Osaka, or another base. The airport page owns Haneda-versus-Narita and airport-to-hotel chains; the city-transit page starts after arrival and does not absorb airport access or Japan-wide rail-pass decisions. The area guide ends when an area is chosen; the hotel page begins when real room inventory is compared. Family and accessible-travel owners may link to each other, but family owns child-specific pace and room constraints while accessibility owns a declared mobility, sensory, vision, hearing, or assistance need. The first-trip owner is a short ordered decision page, not a second Tokyo pillar or a Japan entry encyclopedia.

Day-trip intent is intentionally not registered in this wave. It should receive a canonical owner only after current Israeli demand evidence and a distinct transport or activity conversion task show that it is more useful than a section in the Tokyo pillar.

#### Dated Tokyo competitor and intent evidence

This snapshot was checked on 2026-07-18. It supports page ownership and product architecture only. It does not establish ranking position, search volume, current price, flight operation, hotel availability, or the truth of a competitor's travel claims.

- [ISSTA's Japan package page](https://www.issta.co.il/packages/in/japan) combines Tokyo flight and hotel products and sometimes refers to transfers. This supports separate package and airport-chain decisions. Tra-Vel should compare the complete party cost and inclusions only from a current provider response.
- [ISSTA's Tokyo hotel page](https://www.issta.co.il/hotels/in/japan/tokyo) owns the room-shopping task but gives limited decision support for station access, room configuration, and neighborhood fit. That supports keeping the area owner separate from the transactional hotel owner.
- [Travelist's Tokyo airline page](https://www.travelist.co.il/airlines/elal/Tokyo/) targets the airline-plus-Tokyo flight task. Its schedules, direct-flight language, cabin inclusions, and duration are volatile and must not be inherited. The flight owner should compare current validated responses by airport, connection, baggage, total journey, and change terms.
- The Israeli specialist [Japan Tours Tokyo hotel page](https://japan-tours.co.il/p-hotels/tokyo/) organizes choices around areas, station proximity, room type, and family needs. This supports distinct area, hotel, and family decisions, but its commercial and descriptive claims are not source evidence for Tra-Vel.
- A recent Hebrew [Tokyo first-timer article on Lametayel](https://www.lametayel.co.il/posts/8vmk10) indicates a recognizable first-trip and where-to-stay task. It is community content, so it is evidence of the question only, not authority for entry, safety, transit, or accessibility facts.

#### Dated Tokyo official evidence architecture

The official pages below were checked on 2026-07-18 as the research architecture for the current packet. The packet is now the authoritative evidence record. Every volatile statement and link still requires a fresh publication-time check.

| Decision | Current primary sources | Ownership and recheck rule |
| --- | --- | --- |
| Entry preparation | [Japan Ministry of Foreign Affairs visa-exemption list](https://www.mofa.go.jp/j_info/visit/visa/short/novisa.html) and the Digital Agency's [Visit Japan Web guide](https://services.digital.go.jp/en/visit-japan-web/guide/) | The first-trip page may link to the current official procedure and explain what to verify. It must not freeze eligibility, stay length, document requirements, or a guarantee of entry. |
| Haneda arrival | [Haneda Airport access](https://tokyo-haneda.com/en/access/index.html) and [special-assistance information](https://tokyo-haneda.com/en/faq/assistance.html) | The airport owner compares a complete route to the selected accommodation. Times, fares, terminal operation, and assistance arrangements require a fresh check. |
| Narita arrival | [Narita Airport access](https://www.narita-airport.jp/en/access/) and [universal-design services](https://www.narita-airport.jp/en/service/ud/) | Use the same door-to-door comparison fields as Haneda. Do not reduce the choice to a universal closest, fastest, or cheapest label. |
| Transit inside Tokyo | [Tokyo Metro visitor guidance](https://www.tokyometro.jp/en/tips/index.html) and [JR East Suica guidance](https://www.jreast.co.jp/en/multi/pass/suica.html) | The city-transit owner explains operator, station, change, exit, walking, luggage, and accessibility decisions. Ticket rules, availability, coverage, and fares are volatile. |
| Safety and disruption | [JNTO emergency guidance](https://www.japan.travel/en/plan/emergencies/), the [Japan Meteorological Agency risk map](https://www.jma.go.jp/bosai/en_risk/m_index.html), and Israel's [National Security Council travel-warning collector](https://www.gov.il/he/Departments/DynamicCollectors/travel-warnings-nsc) | The first-trip page links to live authorities and helps the traveler prepare. Never copy a warning level, live alert, emergency number, or insurance conclusion into undated evergreen copy. |
| Accessibility | GO TOKYO's [accessibility guide](https://www.gotokyo.org/en/plan/accessibility/index.html), [Haneda universal facilities](https://tokyo-haneda.com/en/service/facilities/universal.html), and [Narita transport accessibility](https://www.narita-airport.jp/en/service/ud/bus-train/) | The accessible-travel owner assembles an end-to-end chain for the traveler's stated need. Verify each station, assistance request, room, and attraction rather than promising city-wide accessibility. |
| Areas | GO TOKYO's official [Tokyo Area Guide](https://www.gotokyo.org/en/destinations/index.html) | The area owner compares neighborhood and station fit. It must not list unsourced hotel inventory or create one thin page per neighborhood. |
| Family | GO TOKYO's official [Tokyo with kids itinerary](https://www.gotokyo.org/en/story/walks-and-tours/enjoy-tokyo-with-your-kids/index.html) and its [children and accessibility planning guidance](https://www.gotokyo.org/en/plan/accessibility/index.html) | The family owner maps ages, walking, stroller, rest, room, and station needs to the trip. Attraction access and reservations require current operator evidence. |
| First trip | GO TOKYO's official [first-time visitor route](https://www.gotokyo.org/en/story/walks-and-tours/for-the-first-timer/index.html) and [getting-around hub](https://www.gotokyo.org/en/plan/index.html) | The first-trip owner sequences decisions and links to the specialist owners. It must not duplicate the city pillar, area guide, transit guide, or Japan multi-city route. |

The competitive response is a connected decision system, not a copied destination article. The Earth opens the Tokyo city owner; each child resolves one high-friction decision and returns the result to the same editable trip. The Japan-wide owner remains adjacent and mapless until it earns separate country-level evidence and publication approval.

### Lisbon cluster

| Page owner | Canonical path | Intent boundary | Primary conversion |
| --- | --- | --- | --- |
| Lisbon city pillar | `/destinations/lisbon/` | Complete Lisbon city planning, duration, core areas, city experiences, and the next booking decision | Open Lisbon in the map and check dates |
| Flights | `/flights/lisbon/` | `טיסות לליסבון`, connection, baggage, total journey, and change terms | Run a dated flight comparison |
| Packages | `/packages/lisbon/` | Lisbon flight plus stay, area, airport transfer, baggage, and change terms | Compare a verified package or request assistance |
| Hotels | `/hotels/lisbon/` | Live room comparison after the traveler chooses an area and access context | Compare rooms, accessibility, cancellation, and full cost |
| Areas | `/guides/lisbon/where-to-stay/` | Area, hills, transit, walking, pace, and night-noise tradeoffs without duplicating hotel inventory | Choose an area, then open `/hotels/lisbon/` |
| LIS airport to city | `/guides/lisbon/airport-to-city/` | The complete LIS-to-accommodation chain for the actual landing time, luggage, party, and accessibility needs | Compare door-to-door arrival chains |
| City transit | `/guides/lisbon/getting-around/` | Travel inside Lisbon after arrival, including metro, bus, tram, walking, slopes, luggage, and step-free needs | Build day routes around real stops and access constraints |
| Sintra and coast | `/guides/lisbon/sintra-and-coast/` | A day trip versus a split stay in Sintra or the Lisbon coast, without absorbing all Portugal routing | Add current transport, nights, and activities to the trip |
| Family | `/guides/lisbon/lisbon-with-children/` | Child ages, room setup, stroller, hills, rest, transport, and activities | Build a family trip scope |
| Accessible travel and hills | `/guides/lisbon/accessible-travel/` | End-to-end airport, station, street, hotel, room, and attraction planning for a stated access need | Confirm an accessible chain before booking |
| First trip | `/guides/lisbon/first-trip/` | Ordered pre-departure and first-arrival checklist, official entry and safety links, and the first days in Lisbon | Complete official checks, then build the opening days |
| Portugal multi-city pillar | `/destinations/portugal/` | Country-wide routing between Lisbon, Porto, the coast, and other bases; it does not own Lisbon city planning | Build a multi-city route without changing the Lisbon Earth owner |
| Porto city pillar | `/destinations/porto/` | Porto city planning only, separate from Lisbon and Portugal-wide routing | Plan Porto without rewriting the Lisbon guide |

All thirteen canonical paths above remain ownership reservations with `status: backlog`. The Lisbon pillar now has an evidence packet and complete article in `editorial-review`, but this does not create a WordPress route, public page, indexable surface, or child content. The eleven Lisbon owners share `mapState: lisbon`; the Portugal-wide and Porto owners use `mapState: null` so neither can silently replace the Lisbon pin.

The Lisbon pillar owns the city decision. `/destinations/portugal/` owns country and multi-city sequencing, while `/destinations/porto/` owns the Porto city task. The airport page begins at the LIS arrival chain and ends at the selected accommodation. The city-transit page starts after arrival and must not duplicate that chain. The area guide ends when an area is chosen; the hotel page begins when current room inventory is compared. The Sintra and coast owner resolves a Lisbon-based day trip versus a separate stay, not a complete Portugal itinerary. Family and accessible-travel owners may link to each other, but family owns child-specific pace, stroller, room, and rest needs while accessibility owns a declared mobility, sensory, vision, hearing, or assistance need. The first-trip owner is an ordered checklist, not a second Lisbon pillar.

A Lisbon-versus-Porto comparison is intentionally not registered in this wave. It should receive a canonical owner only after current Israeli demand evidence and a distinct one-base, two-base, or city-choice conversion task prove that it is more useful than linked comparison sections in the three owners above.

#### Dated Lisbon competitor and intent evidence

This snapshot was checked on 2026-07-18. It supports page ownership and product architecture only. It does not establish ranking position, search volume, current price, flight operation, hotel availability, or the truth of a competitor's travel claims.

- [ISSTA's Lisbon package page](https://www.issta.co.il/packages/in/portugal/lisbon) puts flight-plus-stay products first. This supports a dedicated package owner, but dates, amounts, airlines, hotels, inclusions, and availability must come only from a current validated provider response.
- [ISSTA's Lisbon hotel page](https://www.issta.co.il/hotels/in/portugal/lisbon) owns the room-shopping task and mixes inventory with location guidance. This supports keeping area choice separate from transactional hotel comparison. Property identities, room facts, ratings, and availability are not inherited.
- [Travelist's Lisbon flight page](https://www.travelist.co.il/budget/Flights_to_Lisbon/) targets flight comparison, but its direct-flight language conflicts with newer pages on the same site. This proves that route and schedule statements are volatile. Tra-Vel should compare validated responses by connection, baggage, total journey, and change terms without copying either claim.
- [Ophir Tours' Lisbon package page](https://www.ophirtours.co.il/hotelpakage/lisbon.html) combines the Lisbon holiday task with references to Sintra, Porto, and other extensions. That supports separate Lisbon city, Sintra and coast, Porto, and Portugal-wide owners rather than one page that tries to rank for every geography.
- [Lametayel's Lisbon destination page](https://www.lametayel.co.il/destinations/lisbon-657) combines broad planning, where-to-stay, and family questions. It is evidence that the traveler asks those questions, not authority for entry, transport, safety, accessibility, flight, hotel, or attraction facts.

#### Dated Lisbon official evidence architecture

The official pages below were checked on 2026-07-18 as the research architecture for the current packet. The packet is now the authoritative evidence record. Every volatile statement and link still requires a fresh publication-time check.

| Decision | Current primary sources | Ownership and recheck rule |
| --- | --- | --- |
| Entry preparation | Portugal's official [travel-to-Portugal guide](https://www.gov.pt/guias/viajar-para-portugal) and the European Union's [travel-document guidance for non-EU nationals](https://europa.eu/youreurope/citizens/travel/entry-exit/non-eu-nationals/index_en.htm) | The first-trip owner may point travelers to current official checks. It must not freeze visa eligibility, passport validity, stay limits, border-system operation, supporting documents, or a guarantee of entry. |
| LIS arrival chain | Lisbon Airport's official [public-transport page](https://www.lisbonairport.pt/en/lis/access-parking/getting-to-and-from-the-airport/public-transportation) and [reduced-mobility assistance page](https://www.lisbonairport.pt/en/lis/services-shopping/essential-services/reduced-mobility) | The airport owner compares a complete route to the selected accommodation. Times, fares, terminals, luggage rules, pickup points, and assistance arrangements require a fresh check. |
| Transit inside Lisbon | [Metropolitano de Lisboa travel guidance](https://www.metrolisboa.pt/en/travel/using-the-metro/), [CARRIS route information](https://www.carris.pt/en/travel/carreiras/), and [CARRIS reduced-mobility guidance](https://www.carris.pt/en/travel/reduced-mobility/) | The city-transit owner explains operator, stop, transfer, slope, paving, luggage, and access decisions. Service, ticket rules, vehicle access, fares, and disruption notices are volatile. |
| Safety and disruption | Israel's [National Security Council travel-warning collector](https://www.gov.il/en/departments/dynamiccollectors/travel-warnings-nsc) and Portugal's [civil-protection warning feed](https://prociv.gov.pt/en/warnings-to-the-population/) | The first-trip owner links to live authorities and helps the traveler prepare. Never copy a warning level, active incident, weather alert, emergency procedure, or insurance conclusion into undated evergreen copy. |
| Areas and first orientation | Visit Lisboa's official [Stay and Plan hub](https://www.visitlisboa.com/en/p/stay-plan), [Lisbon region page](https://www.visitlisboa.com/en/regions/lisbon), and [traveler information](https://www.visitlisboa.com/en/traveller-information) | The area owner compares practical fit without listing unsourced hotel inventory. The first-trip owner sequences decisions and links to specialists without duplicating the pillar. Operator hours, passes, and events need current checks. |
| Family | Visit Lisboa's official [Family and Kids filter](https://www.visitlisboa.com/en/places?q%5Bcategories_id_in%5D%5B%5D=76) | The family owner maps age, stroller, rest, room, transit, and weather needs to a route. Each attraction's suitability, access, schedule, reservation rule, and ticket must be verified with its current operator. |
| Accessibility and hills | Turismo de Portugal's official [Lisbon accessible itinerary](https://www.visitportugal.com/en/destinos/lisboa-regi%C3%A3o/315757), Lisbon Airport assistance, Metro guidance, and CARRIS reduced-mobility guidance | The accessibility owner assembles one end-to-end chain for the traveler's stated need. It must verify every station, vehicle, slope, paving segment, room, assistance request, and attraction rather than promising city-wide access. |
| Sintra and coast | [CP's official Sintra rail-planning page](https://www.cp.pt/info/en/w/discover-sintra), Parques de Sintra's [visit-planning hub](https://www.parquesdesintra.pt/en/plan-your-visit/), and Visit Lisboa's official [Sintra](https://www.visitlisboa.com/en/regions/sintra) and [Cascais](https://www.visitlisboa.com/en/regions/cascais) area pages | The Sintra and coast owner compares a day trip with a separate stay. Rail service, local circulation, timed entry, monument access, weather exposure, beach conditions, and tickets require operator checks before a recommendation or sale. |

The competitive response is a connected decision system, not a copied Lisbon article. The Earth opens the Lisbon city owner; each child resolves one high-friction decision and returns the result to the same editable trip. Portugal and Porto remain adjacent and mapless until each earns separate evidence and publication approval.

### Bangkok city cluster under Thailand

| Page owner | Canonical path | Intent boundary | Primary conversion |
| --- | --- | --- | --- |
| Thailand country pillar | `/destinations/thailand/` | Multi-region Thailand planning | Build the country route |
| Bangkok city guide | `/destinations/thailand/bangkok/` | Bangkok neighborhoods, days, transport, food, and onward route | Focus Bangkok in the country plan |
| Bangkok hotels | `/hotels/bangkok/` | Hotel and neighborhood selection inside Bangkok | Compare rooms by area |
| Airports | `/guides/thailand/bangkok-airports/` | BKK versus DMK and onward connections | Compare airport chains |
| Airport transfer | `/guides/thailand/bangkok-airport-to-city/` | Airport to selected Bangkok area | Compare transfer options |
| Short stop | `/guides/thailand/bangkok-24-48-72-hours/` | A timed Bangkok stop inside a larger Thailand trip | Add nights and activities to the route |

`/flights/thailand/` continues to own Israel-to-Thailand flight intent. Do not create a competing `/flights/bangkok/` page unless demand and supplier evidence prove that the tasks are distinct.

The Bangkok pillar keeps enough airport, transfer, area, and first-visit depth to complete the city decision while its children are unpublished. When a child launches, the ownership handoff is explicit: Thailand retains national arrival and multi-region routing; Bangkok retains city fit and a short decision summary; airport pages own operational BKK/DMK and address-specific transfer detail; the short-stop page owns timed 24/48/72-hour sequencing; the hotel page owns live property and room inventory. The parent section must then contract to a compact comparison and contextual handoff instead of competing with the child.

The content-opportunity registry now reserves all five Bangkok-specific owners above with unique Hebrew intents and `status: backlog`. The hotel owner remains under `/hotels/`; the three decision guides use the Thailand topic parent; every Bangkok record keeps `mapState: bangkok`. This ownership reservation creates no page, route, indexable URL, inventory claim, or publication approval.

## Evidence packet required before a 5,000+ word article

Each missing flagship starts as `research`. The packet can advance only through `source-ready`, `editorial-review`, and `publish-ready`. A generated article is never sufficient by itself.

### Repository gate

Every flagship packet and article must provide:

- one unique `primaryTopic`, one clean trailing-slash `canonicalPath`, and one supported `mapState`;
- at least 5,000 visible words, with at least 75 percent Hebrew words;
- at least 12 decision-oriented sections and 12 visible H2 sections;
- at least three real decision tables;
- at least ten unique dated HTTPS sources, including at least six official or first-party sources;
- at least ten factual claims mapped to source IDs;
- `recheckBeforePublish: true` for every volatile fact;
- a named author, named reviewer, review method, and source-check date;
- at least four crawlable internal decision links and six visible cited source links;
- unique HTML IDs that do not collide with the destination shell;
- no scripts, iframes, unsupported prices, unsupported schedules, internal project language, or em dash and en dash punctuation;
- a discovery map state with at least two route-planning records;
- a matching approved publication-registry record and content-opportunity owner before `content-ready` or `published` is declared. Top-level Earth hubs use the eight-record destination directory. A nested Bangkok guide must use the separate `supporting_guides` collection and preserve Thailand as its real parent.

### Destination evidence matrix

The source categories below are research requirements, not pre-approved publishers. The editor must identify and record the current official source for each category.

| Destination | Minimum official evidence groups | High-volatility checks before publication |
| --- | --- | --- |
| Vienna | Austrian entry authority, Vienna airport, city transit, official city tourism, attraction or cultural operators, accessibility authority, Israeli travel advice | Entry rules, route availability, airport and transit service, attraction hours and booking rules, seasonal-event dates, safety advice |
| Dubai | UAE entry authority, Dubai airports, public transport authority, official tourism authority, local-law guidance, accessibility information, Israeli travel advice | Entry rules, flight and terminal operation, transport fares and hours, attraction access, event dates, local restrictions, safety advice |
| Tokyo | Japanese entry authority, Haneda and Narita airport operators, official rail and metro operators, national and city tourism authorities, disaster guidance, accessibility information, Israeli travel advice | Entry rules, airport choice and transfers, rail service and ticket rules, attraction reservations, weather and disruption guidance, safety advice |
| Lisbon | Portuguese entry authority, Lisbon airport, metro, bus, tram and rail operators, official city and Sintra tourism authorities, accessibility information, Israeli travel advice | Entry rules, connections, transport service and ticket rules, attraction reservations, seasonal conditions, safety advice |
| Bangkok | Thai entry authority, BKK and DMK airport operators, official urban and airport rail operators, official tourism authority, health guidance, accessibility information, Israeli travel advice | Entry rules, airport and onward-route operation, transit service, attraction rules, weather disruption, health and safety advice |

Prices, exchange rates, hotel identities, airline schedules, direct-flight status, availability, ratings, savings, and insurance terms require a current provider response or an authoritative dated source. Planning copy may explain what to compare. It may not present an unsourced commercial fact as a live offer. Final price, availability, and terms are provided only after revalidation in a personal quote.

## Answer-engine and structured-data requirements

Each pillar needs a concise server-rendered answer block near the top covering who the destination suits, how to choose duration, route, area, season, and the next action. It should answer the decision without pretending there is one universal duration, budget, or best month.

The current schema boundary remains correct:

- emit `WebPage`, `Article`, `BreadcrumbList`, and `TouristDestination` only as allowed by the publication gate;
- keep directory pages as `CollectionPage` plus a non-commercial `ItemList`;
- include `lastReviewed` and source `citation` only from reviewed metadata;
- do not emit `Article` for an incomplete guide;
- do not emit `Product` or `Offer` for editorial or non-live planning data;
- do not emit `FAQPage` markup. Useful questions remain visible HTML sections or disclosure panels;
- allow an active SEO plugin to own the graph while the theme enriches and gates its nodes.

FAQ sections should answer real traveler decisions such as route choice, airport choice, area selection, trip length, family constraints, accessibility, entry checks, booking sequence, and change risk. Each answer must be sourced where factual, date-stamped where volatile, and linked to the next useful action. FAQ copy must not be created merely to repeat keyword variants.

## Internal-link entry points

No guide is published until every important journey has a crawlable HTML route independent of JavaScript and the 3D Earth.

1. **Destination directory:** change `guide_status`, `guide_path`, word count, and source count only when the packet is publish-ready. Research cards continue to link to the map and directory, not a thin page.
2. **Earth and full map:** the discovery API should resolve the new `guide_path` from the editorial directory. The map result guide CTA must land on the canonical guide after publication and on `/destinations/` before publication.
3. **Homepage reveal:** extend the client guide-path allowlist and server default resolution only after the canonical guide exists. Until then, the editable trip can link to the destination directory card.
4. **Product hubs:** flight, hotel, package, and insurance surfaces may preserve destination context in noindex query URLs. Each new transactional cluster must also have a stable canonical page and a server-rendered link back to the destination pillar.
5. **Destination pillar:** link up to `/destinations/`, sideways to genuinely related destination decisions, down to stable flight, hotel, package, insurance, transfer, activity, and planning pages, and into the exact Earth state.
6. **Supporting guides:** link to one parent pillar, one next commercial action, and only the related siblings needed to complete the decision. Do not create circular blocks of generic links.
7. **Mega menu and footer:** expose a destination only after its canonical page is indexable and useful. Menu prominence follows current demand and conversion evidence, not the existence of a map pin.
8. **Sitemap:** the repository has no custom destination sitemap manifest. WordPress or the active SEO plugin owns XML sitemap output. Verify that a newly published canonical appears exactly once and that drafts, incomplete guides, saved trips, account pages, and faceted URLs do not enter an indexable sitemap.

The map query, such as `/travel-map/?destination=vienna`, remains `noindex, follow`. It is a useful planning entry point but never substitutes for the stable canonical guide.

## Keyword cannibalization guards

- Register one exact canonical owner and one dominant Hebrew intent before drafting.
- Keep broad destination planning on the pillar. Put live flight, hotel, and package intent on the matching commercial route.
- Keep route, area, airport, audience, seasonal, and comparison pages narrow enough to solve a different decision.
- Keep Thailand as the country and multi-region owner. Bangkok is its city child. Never publish a second top-level Bangkok guide.
- Keep Tokyo as the city owner. The backlog `/destinations/japan/` owner holds country and multi-city intent with no Earth map state; it is not a rewritten Tokyo guide.
- Keep Dubai holiday intent separate from Dubai stopover intent and from a Dubai-versus-Abu-Dhabi comparison.
- Keep Vienna general planning separate from Christmas-market dates and from Vienna-versus-Budapest-versus-Prague comparison intent.
- Keep Lisbon as the city owner. The mapless `/destinations/portugal/` owner holds country and multi-city intent, while the mapless `/destinations/porto/` owner holds Porto city intent. Keep Sintra and coast as a bounded Lisbon trip decision, and defer Lisbon-versus-Porto until it proves a distinct comparison task.
- Treat spelling variants, transliterations, singular and plural phrases, and year modifiers as one owner unless the traveler task changes.
- Do not create month, neighborhood, hotel-type, or audience pages without unique evidence, useful inventory, and a distinct conversion path.
- Use a self-referencing canonical on the stable page. Faceted and personalized variants remain noindex and point users back to the owner page.
- If an older page already owns the same task, consolidate, redirect, and update internal links instead of publishing another page.

## Conversion and monetization modules

Every flagship should help the traveler act without turning editorial copy into an unsupported offer.

| Module | Traveler decision | Revenue path | Truth boundary |
| --- | --- | --- | --- |
| Route comparator | Direct, connection, stopover, airport, baggage, and total journey | Flight referral, supplier handoff, assisted quote | Show commercial amounts only from a current validated response |
| Stay-area selector | Area, room setup, access, cancellation, and total stay | Hotel referral, package, assisted quote | Separate editorial area guidance from live room inventory |
| Complete trip composer | Flight, stay, transfers, activities, dining, insurance, connectivity, and equipment | Package margin, planning service, cross-sell | Mark unresolved components and require revalidation |
| Insurance checklist | Party, destination, duration, activities, and coverage questions | Licensed insurance handoff | Do not recommend or price a policy without an authorized provider response |
| Transfers and mobility | Airport, accommodation, local transport, accessibility, and onward route | Transfer, rail, car, or mobility partner | State operator, timing, and fare only from current evidence |
| Activities and tickets | What fits the route, age, pace, and booking window | Activity affiliate or supplier | Never claim availability, rating, or discount without live evidence |
| Connectivity and equipment | eSIM, roaming, luggage, rental, and activity gear | Approved affiliate or product partner | Explain criteria before presenting a product |
| Save and alert | Save destination, dated search, and unresolved tasks | Return visit, price alert, assisted service | Do not describe a saved plan as booked or held |
| Personal proposal | Assemble a sourced, editable complete trip | Human service fee, commission, qualified lead | Final price, availability, and terms only after revalidation and traveler approval |

The pillar should have one primary action near the answer block, usually check dates or build the trip, and contextual actions after each major decision. Avoid generic internal language such as start a flow, launch a module, or review the prototype.

## Priority order and delivery waves

### Wave 0: Vienna

The source packet and linked 7,623-visible-word article are now in `editorial-review`, with 16 sources and 20 mapped facts. A named editor must complete the review, every volatile fact and source link must be revalidated at publication time, and an explicit promotion and routing decision must be recorded before `/destinations/vienna/` is connected. The pillar, flight, package, hotel, area, airport-transfer, family, and Christmas-market owners remain backlog until each has unique value and an operational conversion route. Vienna is the lowest integration-risk missing hub because its Earth, directory, image, coordinate support, evidence packet, article draft, and canonical ownership now exist.

### Wave 1: Dubai

Nine unique owners are registered as backlog: the full-holiday pillar, flights, packages, hotels, area choice, DXB transfer, family, stopover, and first-trip local-law decisions. The pillar packet and linked 6,819-visible-word article are now in `editorial-review`, with 31 official or primary sources and 32 mapped facts. A named editor must complete the review, every volatile claim and source link must be revalidated at publication time, and destination-specific visual licensing plus an explicit promotion and routing decision must be recorded before `/destinations/dubai/` is connected. Keep stopover and the other child owners separate; commission them only when each supplies unique evidence and its stated conversion route works. No Dubai route or public-page status was created by the editorial draft.

### Wave 2: Tokyo

Ten Tokyo owners and one adjacent Japan-wide owner are registered as backlog. The Tokyo pillar packet and linked 8,811-visible-word article are now in `editorial-review`, with 45 official or primary sources and 46 mapped facts. A named editor must complete the review, every volatile claim and link must be revalidated, and destination-specific visual licensing plus an explicit promotion and routing decision must be recorded before `/destinations/tokyo/` is connected. Keep Haneda-versus-Narita separate from city transit, area choice separate from hotel inventory, family separate from declared accessibility needs, and the first-trip checklist separate from the broad city pillar. The Japan-wide owner has no map state and cannot replace the Tokyo Earth destination. While the child pages remain unpublished, the pillar keeps enough airport, transit, and area depth to answer the full planning task. When a child page publishes, reduce the corresponding pillar section to a decision summary, compact comparison, and contextual handoff instead of allowing two pages to own the same intent. No Tokyo or Japan public route, public-page status, or publication approval was created by the editorial draft.

### Wave 3: Lisbon

Eleven Lisbon owners plus separate mapless Portugal-wide and Porto owners are registered as backlog. The Lisbon pillar packet and linked 9,603-visible-word article are now in `editorial-review`, with 37 official or primary sources and 40 mapped facts. A named editor must complete the review, every volatile claim and link must be revalidated, and destination-specific visual licensing plus an explicit promotion and routing decision must be recorded before `/destinations/lisbon/` is connected. Keep the LIS arrival chain separate from city transit, area choice separate from hotel inventory, Sintra and the coast separate from Portugal-wide routing, family separate from declared accessibility needs, and the first-trip checklist separate from the broad city pillar. The pillar now keeps Sintra and the coast to a bounded include, day-trip, or split-stay decision so the reserved child can later own detailed routes and operations. No Lisbon, Portugal, or Porto public route, public-page status, or publication approval was created by the editorial draft.

### Wave 4: Bangkok city

The nested Bangkok packet and linked 8,399-word article are now in `editorial-review`, with 46 official or primary sources, 32 source-mapped volatile facts, 24 H2 sections, 10 decision tables, and 13 traveler FAQs. Keep the Earth linked to the published Thailand pillar until a named editor reviews the draft, every volatile claim and source is revalidated, destination imagery is licensed, the real Thailand parent is provisioned, the supporting-guide registry is approved, and the packet is explicitly promoted. After publication, use the city page only inside Bangkok-specific directory and planning contexts while country-level routes continue to use `/destinations/thailand/`.

### Wave 5: finish the four published clusters

Build the registered revenue children for Budapest, Prague, Athens, and Thailand. Add the missing Athens hotel owner and validate whether each proposed child has distinct search intent, source depth, and a real next action before creating it.

## Publication acceptance checklist

### Ownership and research

- [ ] The primary intent and canonical path are unique in the content-opportunity registry.
- [ ] The entry starts as backlog, never as `content-ready`.
- [ ] The destination map state exists and has at least two discovery route records.
- [ ] The source packet starts as `research` and names the intended content path.
- [ ] No current demand, price, schedule, or supplier claim is included without evidence.
- [ ] A licensed destination-specific hero image, alt text, credit, and responsive asset plan exist. Vienna has a local image; Dubai, Tokyo, and Lisbon currently use generic fallback imagery and need real assets.

### Editorial evidence

- [ ] At least ten sources exist, with at least six official or first-party sources.
- [ ] Every source has a unique ID, unique HTTPS URL, publisher, type, check date, and supported decisions.
- [ ] At least ten claims map to valid source IDs.
- [ ] Every volatile fact is marked for a publish-time recheck and has been rechecked.
- [ ] A named author and reviewer have completed the documented review method.
- [ ] The visible article has at least 5,000 words, 75 percent Hebrew words, 12 H2 sections, three decision tables, four internal links, and six visible source links.
- [ ] The article contains no script, iframe, unsupported commercial facts, internal project language, em dash, en dash, duplicate IDs, or broken anchors.

### WordPress and search integration

- [ ] The WordPress page is a child of the correct real parent and uses `page-destination.php`.
- [ ] Primary topic, author, reviewer, review method, checked date, map state, sources, and decision metadata are stored.
- [ ] The runtime publication contract returns ready before the page becomes public and indexable.
- [ ] For a top-level Earth hub, the editorial directory changes to `published` with the exact canonical path, word count, and source count only after the packet is `publish-ready`. A nested Bangkok guide uses the separate supporting-guide registry and does not become a ninth Earth record.
- [ ] The content-opportunity owner changes to `content-ready` only after the publish-ready packet exists at the same canonical and map state.
- [ ] For a top-level hub, the discovery resolver, map CTA, homepage guide-path allowlist, directory card, and applicable mega-menu link resolve the canonical page. Bangkok remains mapped to Thailand at Earth level and receives city links only in explicit Bangkok contexts.
- [ ] The canonical is self-referencing and independent of map or product query parameters.
- [ ] Every internal article link resolves to a route declared public by the smoke manifest or a published editorial record. Homepage redirects and unpublished child placeholders fail the publication gate.
- [ ] Every generated table-of-contents fragment resolves against the final article, including destination-prefixed anchor IDs.
- [ ] Visible breadcrumbs and `BreadcrumbList` show Home, Destinations, and the destination. A nested Bangkok page additionally preserves the real Thailand parent in both hierarchies.
- [ ] `Article`, `TouristDestination`, citations, and `lastReviewed` appear only when the gate passes. No editorial `Product`, `Offer`, or `FAQPage` markup appears.
- [ ] The active XML sitemap includes the canonical once, with no faceted or incomplete duplicate.

### Conversion and experience

- [ ] The page has one clear primary action and contextual actions tied to traveler decisions.
- [ ] Flight, hotel, package, insurance, transfer, activity, and planning links preserve destination context.
- [ ] Every commercial amount and availability state comes from a current validated provider response.
- [ ] Non-live modules remain useful with criteria, tradeoffs, and a check-dates or personal-quote action.
- [ ] The exact final-price, availability, and terms boundary is visible before assisted handoff.
- [ ] The destination is usable without JavaScript, 3D rendering, animation, or pointer input.
- [ ] Mobile layout, keyboard behavior, focus, reduced motion, Save-Data, and 44 by 44 pixel targets are checked in a later authorized browser session.

### Required local checks

```powershell
node scripts/ci/validate-guide-packets.mjs
node scripts/ci/validate-content-opportunity-registry.mjs
node scripts/ci/validate-discovery-contract.mjs
php scripts/ci/validate-guide-publication-runtime.php
node scripts/ci/validate-theme.mjs
```

Run `scripts/wp/sync-guide.ps1` without `-Apply` first. Publication, WordPress writes, browser screenshots, deployment, and sitemap submission remain separate explicitly authorized operations.

## Validator decision

The repository validators now fail on duplicate intent or canonical ownership, future-dated evidence, invalid map states, `content-ready` entries without matching publish-ready packets, published directory entries without packets, top-level or nested publication-registry drift, packet and directory map-state drift, thin flagship content, broken anchors, unsafe markup, and invalid public copy. Runtime fixtures also exercise top-level and nested breadcrumbs, nested public paths, and publication-status metadata. The uncovered destinations remain a prioritized backlog, not a repository-integrity failure. CI deliberately does not require a public guide for every Earth pin because that would reward thin publication instead of evidence-backed completeness.
