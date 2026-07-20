# Tra-Vel 3D globe runtime

## Purpose

The production globe is a progressively enhanced travel-discovery surface. It uses a native WebGL sphere when the browser supports WebGL and preserves the existing Earth image only as a failure fallback. The fallback is not painted beneath a working WebGL canvas, so the live Earth never sits on top of a second poster. Destination details, route comparisons, filters, and commercial actions remain outside the globe so map movement is never obstructed.

## Runtime architecture

- `assets/js/globe-3d.js` is loaded for the front page, `page-map.php`, `page-destination.php`, `page-seo-opportunity.php`, and single destination guides (see `inc/assets.php`).
- `earth-blue-marble-2048.jpg` is a 2048 by 1024 power-of-two texture optimized from the existing 5400 by 2700 source.
- The sphere contains 56 latitude segments and 88 longitude segments.
- Device pixel ratio is capped at 1.75 to control mobile GPU memory and fill rate.
- Rendering is event driven. The globe redraws after interaction, resize, selection, or focus animation instead of running a permanent animation loop. Since theme 1.24.0 one bounded exception exists: while the guarded idle spin or auto-fly tour is actually moving the camera, frames self-schedule; the moment a guard fails (globe off-screen, tab hidden, pointer down, reduced motion, or user takeover) the frame chain stops and rendering returns to purely event-driven.
- Intersection and document-visibility observers stop rendering work when the globe is outside the active view. The idle spin and the tour obey the same observers.
- WebGL context loss or initialization failure switches to the static Earth fallback and permanently disables idle motion and the tour. A successful WebGL initialization keeps that image absent rather than hidden under the model.

## Living globe (theme 1.24.0)

- **Guarded idle spin.** The Earth turns slowly (`yaw += 0.00006 * dt`, about 0.0576 degrees per 60fps frame) only while all guards hold: the globe intersects the viewport, `document.visibilityState === 'visible'`, no pointer is down, no camera animation or tour dwell is running, and the visitor does not prefer reduced motion (including Save-Data). Any direct interaction pauses the spin; it resumes about four seconds after the last interaction ends.
- **Auto-fly tour.** Discovery globes (homepage and travel map) arm `startTour()` about three seconds after load. The tour cycles through the destination pins with `focusDestination(id, { rotations: 0, pulse: true, duration: ~1500 })` and dwells about 2600 ms on each stop, so every hop updates the same pin selection state, labels, and route pulse as a manual destination focus. Hops are not announced to the polite live region; that channel remains reserved for traveler-initiated selections, matching the automatic reveal previews. The tour never starts under reduced motion, never hops while the globe is off-screen or the tab is hidden, and is cancelled permanently for the page view by any direct interaction with the globe: pointerdown, double click or double tap, keyboard input, marker activation, zoom controls, or focus entering the globe. Programmatic camera work (reveal previews, hydration focus) only defers the next hop by about four seconds.
- **Double-click and double-tap dive.** A double click, or two touch taps within 300 ms and 24 px, ray-casts the struck screen point through `globePointFromScreen`, re-centers the camera on that coordinate, and steps `state.distance` down by 0.6 (clamped to the existing 2.25 to 4.8 range) over about 700 ms. Since theme 1.25.0 every discovery-globe dive also publishes the struck coordinate through the shared `travelglobe:select` pipeline with `inputType: 'dive'`, and a lone free-point tap waits out the 300 ms double-tap window (`TAP_PREVIEW_DELAY_MS`) before publishing its preview, so a dive is always the gesture's only selection. Marker taps and keyboard selection stay immediate.
- **Scroll law.** The globe never traps page scrolling. No wheel or scroll listener is bound anywhere in the globe runtime; zoom remains buttons, keyboard plus and minus, double click or double tap, and the browser's native pinch path. `touch-action: pan-y` stays intact so vertical swipes keep scrolling the page.
- **Marker budget and level of detail.** Both discovery globes render all reviewed destinations as full price pins plus every exploration hub as a 44 px dot control. Each frame, only the `MARKER_COLLISION_BUDGET` (60) highest-priority front-hemisphere markers enter the O(n^2) collision pass; the rest stay hidden until the camera brings them forward. Far away (`distance > 3.0` since theme 1.25.0) the layout shows destination labels and hub dots only; closer, up to twelve front hubs gain their city label through the same collision pass (`data-globe-lod` and `data-globe-label`). During idle spin, marker declutter is throttled to about 30fps while the sphere renders at full rate. Hub markers carry no price; commercial values remain governed by the supplier data-mode gates in `app.js`.

## Dive store (theme 1.25.0)

Every double-click or double-tap dive reveals the struck location's services and products in a normal-flow panel below the globe (`[data-dive-store]` on the homepage and the travel map). The globe is never covered; it yields height instead.

### Depth model

- **D0 orbit.** The existing behavior: single taps and marker clicks keep their selection previews, the idle spin and tour keep their guards.
- **D1 first dive.** A dive anywhere, or selecting a destination, flies the camera in (the existing dive), updates the destination panel below the globe, and reveals a service chip row for the dived point. The panel smooth-scrolls its top edge into view only as the direct result of the dive gesture, never during free scrolling, and the globe container shrinks through the `data-dive-depth="1"` classes (desktop roughly the 60vh to 40vh step, mobile proportionally).
- **D2 second dive.** A second dive while the same target stays focused reuses the dive (another 0.6 of camera distance, clamped at 2.25) and expands the chips into the full service board: a cards grid, four by two on desktop and one column on mobile, while the globe docks smaller through `data-dive-depth="2"` (roughly 30vh). A dive on a different target at any depth flies there and swaps the panel content back to its D1 state, with no page navigation.
- **Breadcrumb and back.** The line above the panel reads `עולם ‹ {country} ‹ {city}`. Escape or the `חזרה לעולם` button steps back exactly one level, restores the globe height class, and zooms the camera out through the existing zoom path.

### Point types

- **Curated destination** (the eight reviewed destinations): D1 shows a hero strip (name, country, estimated flight time from the planning profile, and weather or season only when the supplier truth gates allow them) plus eight service chips: טיסות, מלונות, העברות, פעילויות, אוכל, ביטוח, eSIM ותקשורת, ציוד. Each chip reuses the exact vertical link patterns of the homepage plan-360 modules. D2 shows the eight service cards (icon, label, one factual line from existing planning data, a `החל מ-` price only where the data owns one, one CTA) plus a pinned ninth `ערכת נסיעה` bundle card (eSIM and insurance together) whose single sample price comes only from the destination's planning-route insurance components.
- **Exploration hub** (the forty-nine dots): D1 shows the city and country plus the four core chips (טיסות, מלונות, eSIM ותקשורת, ביטוח) linking with the hub's `iata_search_code` and city. D2 shows those four as cards, an honest destination-still-being-built banner with a planner CTA, and a `יעדים קרובים` row with the three nearest curated destinations and their great-circle distance chips, computed client-side from latitude and longitude (`nearestCuratedDestinations`).
- **Arbitrary point:** D1 shows a point card (`נקודה על הגלובוס` plus coordinates), the three nearest curated destinations with distance chips, a `חקרו את האזור` action that flies the camera to the region at a medium height through the new `focusPoint` controller method, and the existing planner handoff with the exact coordinates. There is no D2 board for arbitrary points.

### Truth rules

Every price in the dive store comes from existing planning data only and always in the `החל מ-` form with the amount wrapped in `<bdi dir="ltr">`. One footnote per panel, `המחירים להמחשה; המחיר הסופי מאומת לפני התשלום.`, appears only while at least one sample price is visible; cards never carry their own disclaimers, and hub and free-point surfaces never show a price at all. Chips flow from the right edge (RTL) with 44 px touch targets and horizontal scrolling on mobile; the globe is never mirrored.

### First-dive label tuning

Theme 1.25.0 moves `NEAR_LOD_DISTANCE` from 2.8 to 3.0. The per-dive delta is contract-pinned at 0.6, and the first dive from the default camera height lands at 3.15 - 0.6 = 2.55: against the old 2.8 threshold the labeled zone was entered late in the eased flight with almost no margin, so in practice city labels only felt present after a second dive. With the 3.0 threshold the first dive crosses into the near level of detail about a quarter of the way through the animation and settles 0.45 inside it, so hub city labels join the layout during the first dive while the label budget, collision pass, and dive smoothness stay unchanged.

## Interaction contract

- Pointer drag rotates the globe after an eight-pixel intent threshold. Touch starts in a pending state, horizontal-dominant movement rotates, and vertical-dominant movement stays with normal page scrolling.
- A short pointer tap inside the visible sphere is ray-cast back to latitude and longitude. It resolves to a reviewed city only inside a strict 100 km point radius. Every other coordinate remains an explicit free map point and opens an honest agent handoff instead of silently changing the user's place.
- A point outside supported coverage still produces a truthful result: exact coordinates receive a stable selection identity, all twelve decision and cost areas open in a provisional state, and the AI continuation receives the validated point without inventing a city, price, availability, or booking.
- Destination and point handoffs carry an explicit context kind. Browser history stores the bounded selection identity and coordinates beside the visible destination, so Back and Forward cannot pair one destination with another selection's AI link.
- An open-ended homepage request uses `destination_mode=anywhere`. The globe starts with no selected destination and never promotes the first API result into a traveler choice. Destination pins remain available for direct selection, and the AI action can propose a direction using the accepted trip criteria.
- Product, origin, valid dates, bounded party size, and rooms form a separate trip-context contract. The context appears in a normal-flow summary below the globe and is translated into the correct parameter names for flight, hotel, package, insurance, and AI continuations.
- Destination buttons remain real DOM controls above the canvas.
- Selecting or focusing a destination rotates it to the center and updates the information section below the map.
- Plus and minus controls provide a simple-pointer zoom alternative.
- Arrow keys rotate the globe, plus and minus zoom, Home resets the view, and Enter or Space selects the geographic point at the center of the current view.
- Double click, or two touch taps within 300 ms and 24 px, dives toward the struck Earth coordinate; the gesture never creates a duplicate selection.
- Reduced-motion preferences disable animated camera transitions, the idle spin, and the auto-fly tour.
- Mobile non-selected destinations retain 44 by 44 pixel hit targets with smaller visual dots; exploration hubs keep the same 44 px floor at every width.

Tap and drag are separated by an eight-pixel movement threshold and a 700 ms tap window. Pointer capture begins only after horizontal drag intent is established. Vertical browser scrolling remains available through `touch-action: pan-y`; a cancelled or vertical browser gesture cannot rotate the Earth or become a selection.

## Selected Area 360 kernel

Every Earth selection opens a normal-flow decision cockpit below the globe. The discovery response owns a `selected_plan` with twelve areas: route, stay, mobility, activities, dining, weather, entry, connectivity, accessibility, insurance, equipment, and total cost. Each module declares `live`, `editorial`, `needs_details`, `needs_search`, `unknown`, or `unavailable` state plus provenance and a next action. Outside reviewed coverage, all twelve areas remain available for planning but visibly contain zero live-verified results until context resolution and connected searches occur.

Every second-level information marker also participates in that kernel. A traveler click creates a stable `map_point` selection from the typed entity id and coordinates, locks the canonical destination, marks the relevant route, stay, activity, or total-cost module as selected and editable, carries the identity into URL history and downstream product links, and gives the saved workspace item an entity-specific identity. Hotel-area actions additionally retain the selected area; flight, hotel, and package actions use the destination airport code expected by their search contracts. The confirmation says that the point was added to the plan, never that inventory was held or booked. Merely rendering the first marker does not commit a traveler choice.

The coverage meter describes mapped decision areas, not booking completion. The cost ledger always lists the complete in-scope category set and exposes an amount only when destination-scoped, component-level supplier provenance owns that value and currency. Route totals remain hidden unless the supplier explicitly owns the total scope. Savings remain hidden until a server contract proves an equivalent comparison cohort, comparator identity, dates, travelers, inclusions, taxes, currency, and retrieval time. Route selection updates the shared cockpit, save context, AI context, and cost ledger.

Selection acknowledgement, route, module, ledger, and meter transitions animate confirmed interface changes. Pending work uses neutral motion. Positive confirmation runs only for a current response or a real increase in completed Agent stages; stale, fallback, failed, and cancelled states never receive celebratory motion. Reduced-motion users receive the same states with no motion. No new result, action, or progress surface is positioned over the Earth.

Homepage routing follows the same rule. Product and valid criteria receive one positive acknowledgement. The handoff animation receives a painted frame before navigation, but it only says that the destination page is opening. It never represents a supplier search, price, hold, or booking.

Transport state is separate from product state. A failed intake marks the request as unconfirmed. A lost polling connection freezes the last server-confirmed state, stops working motion, and resumes animation only after a current server response arrives. Repeated polls do not re-announce unchanged status text.

Supplier origin and freshness are separate dimensions. A failed or in-progress refresh can preserve the last observed supplier snapshot and its timestamps, but every affected module and amount switches to a stale state, live-price filtering is disabled, the response is not cached, and confirmation motion does not run. Only `fresh` or newly resolved `miss` responses may complete supplier-backed progress stages.

The race-condition acceptance test is Bangkok, Tokyo, then Lisbon in rapid succession. Only Lisbon may finish, announce, animate confirmed stages, or enable its plan state. Aborted and late responses must not mutate the newest selection.

## Geographic projection

Destination latitude and longitude come from the discovery contract. They are converted to sphere coordinates and projected with the same yaw, pitch, distance, and perspective used by the WebGL camera. Points on the rear hemisphere are hidden. Visible labels are ordered by selection and camera depth, then collision checked before display.

The selected route is drawn as a non-interactive SVG curve between Ben Gurion Airport and the selected destination when both endpoints are on the visible hemisphere.

## Data and commercial safety

The globe controls place and selection only. Price, availability, savings, ratings, route duration, and commercial conditions remain governed by the supplier data-mode gates in `app.js`. Demo fixtures cannot be converted into customer offers by the globe.

## Imagery

The texture is derived from NASA Blue Marble: Next Generation imagery. The public map page links to the NASA Visible Earth source. The optimized texture is bundled with the theme, so no third-party script, tile request, token, or browser-exposed credential is required.

## Future map-engine boundary

The native globe is the token-free production foundation. A licensed vector or terrain provider can later replace the sphere renderer without changing the destination, supplier, route, content, or commerce contracts. A provider upgrade must preserve:

- Map interaction without interface obstruction
- Keyboard and simple-pointer alternatives
- Collision and zoom-level label rules
- Supplier attribution and caching restrictions
- No client-side secrets
- No unverified commercial claims
- Mobile performance budgets

