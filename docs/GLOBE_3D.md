# Tra-Vel 3D globe runtime

## Purpose

The production globe is a progressively enhanced travel-discovery surface. It uses a native WebGL sphere when the browser supports WebGL and preserves the existing Earth image as a fallback. Destination details, route comparisons, filters, and commercial actions remain outside the globe so map movement is never obstructed.

## Runtime architecture

- `assets/js/globe-3d.js` is loaded only for `page-map.php`.
- `earth-blue-marble-2048.jpg` is a 2048 by 1024 power-of-two texture optimized from the existing 5400 by 2700 source.
- The sphere contains 56 latitude segments and 88 longitude segments.
- Device pixel ratio is capped at 1.75 to control mobile GPU memory and fill rate.
- Rendering is event driven. The globe redraws after interaction, resize, selection, or focus animation instead of running a permanent animation loop.
- Intersection and document-visibility observers stop rendering work when the globe is outside the active view.
- WebGL context loss or initialization failure leaves the static Earth fallback visible.

## Interaction contract

- Pointer drag rotates the globe after an eight-pixel intent threshold. Touch starts in a pending state, horizontal-dominant movement rotates, and vertical-dominant movement stays with normal page scrolling.
- A short pointer tap inside the visible sphere is ray-cast back to latitude and longitude. It resolves to a reviewed city only inside a strict 100 km point radius. Every other coordinate remains an explicit free map point and opens an honest agent handoff instead of silently changing the user's place.
- A point outside supported coverage still produces a truthful result: exact coordinates receive a stable selection identity, all twelve decision and cost areas open in a provisional state, and the AI continuation receives the validated point without inventing a city, price, availability, or booking.
- Destination and point handoffs carry an explicit context kind. Browser history stores the bounded selection identity and coordinates beside the visible destination, so Back and Forward cannot pair one destination with another selection's AI link.
- Destination buttons remain real DOM controls above the canvas.
- Selecting or focusing a destination rotates it to the center and updates the information section below the map.
- Plus and minus controls provide a simple-pointer zoom alternative.
- Arrow keys rotate the globe, plus and minus zoom, Home resets the view, and Enter or Space selects the geographic point at the center of the current view.
- Reduced-motion preferences disable animated camera transitions.
- Mobile non-selected destinations retain 44 by 44 pixel hit targets with smaller visual dots.

Tap and drag are separated by an eight-pixel movement threshold and a 700 ms tap window. Pointer capture begins only after horizontal drag intent is established. Vertical browser scrolling remains available through `touch-action: pan-y`; a cancelled or vertical browser gesture cannot rotate the Earth or become a selection.

## Selected Area 360 kernel

Every Earth selection opens a normal-flow decision cockpit below the globe. The discovery response owns a `selected_plan` with twelve areas: route, stay, mobility, activities, dining, weather, entry, connectivity, accessibility, insurance, equipment, and total cost. Each module declares `live`, `editorial`, `needs_details`, `needs_search`, `unknown`, or `unavailable` state plus provenance and a next action. Outside reviewed coverage, all twelve areas remain available for planning but visibly contain zero live-verified results until context resolution and connected searches occur.

The coverage meter describes mapped decision areas, not booking completion. The cost ledger always lists the complete in-scope category set and exposes an amount only when destination-scoped, component-level supplier provenance owns that value and currency. Route totals remain hidden unless the supplier explicitly owns the total scope. Savings remain hidden until a server contract proves an equivalent comparison cohort, comparator identity, dates, travelers, inclusions, taxes, currency, and retrieval time. Route selection updates the shared cockpit, save context, AI context, and cost ledger.

Selection acknowledgement, route, module, ledger, and meter transitions animate confirmed interface changes. Pending work uses neutral motion. Positive confirmation runs only for a current response or a real increase in completed Agent stages; stale, fallback, failed, and cancelled states never receive celebratory motion. Reduced-motion users receive the same states with no motion. No new result, action, or progress surface is positioned over the Earth.

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

