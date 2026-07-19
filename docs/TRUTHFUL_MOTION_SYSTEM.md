# Tra-Vel truthful motion system

Motion in Tra-Vel communicates verified progress. It must never imply that a search, supplier response, reservation, payment, or booking happened unless the corresponding trusted system state confirms it.

## Product rules

1. Every animation has a state owner: browser request, Tra-Vel server, supplier adapter, payment provider, or user action.
2. Neutral waiting motion is allowed only while a real request is open. It stops on success, failure, timeout, or cancellation.
3. Positive motion is a short, one-shot acknowledgement after a confirmed state transition. It does not loop.
4. Long-lived states are visually calm. Text, icons, timestamps, and progress semantics carry the status.
5. An unavailable integration is shown as unavailable. Demo, editorial, mixed, and live data are visibly distinguished.
6. Motion never blocks Earth controls, obscures prices, captures map gestures, or creates another overlay to repair an existing overlay.
7. `prefers-reduced-motion: reduce` removes nonessential transforms, sweeps, pulses, orbiting, and smooth scrolling while preserving the same content and state changes.

## State-to-motion contract

| State | Trusted trigger | Allowed presentation | Must not say or imply |
| --- | --- | --- | --- |
| Loading | A request was dispatched and has not settled | Neutral spinner, restrained skeleton, or progress sweep | A provider is searching unless a provider request actually started |
| Confirmed | Server returned the saved version or accepted action | One-shot check, brief highlight, route segment draw | Booked, reserved, paid, or price locked |
| Advanced | Server version/status moved forward | One-shot step transition and `aria-current` update | Continuous background work after the response |
| Recovered | A failed request later succeeds | One-shot recovery cue and explicit recovered copy | That earlier work completed during the outage |
| Attention | Validation, clarification, or user approval is required | Static emphasis and focused next action | Failure, rejection, or automatic resolution |
| Terminal | Completed, cancelled, expired, or closed state is confirmed | Static final state; optional one-shot arrival when first received | More work is still running |
| Error | Request failed, timed out, or returned an invalid contract | Static error, retry action, preserved last confirmed state | Silent fallback to success or fabricated results |
| Stale | Last confirmed data is older than its freshness policy | Static timestamp and refresh action | Current price, availability, or operational status |

## Earth and 360-result choreography

An Earth selection opens information beside or below the globe. It does not place a stack of cards over the interactive surface.

1. The selected point receives a brief acknowledgement.
2. Geographic context resolves first: destination, dates, party, and known constraints.
3. Confirmed result groups arrive progressively: routes, stays, local transport, activities, insurance, and useful products.
4. A route segment draws only when that segment exists in the current plan. Editing or removing it updates the path.
5. Price markers settle after their source response is validated. Each marker exposes currency, scope, freshness, and data mode.
6. Savings animate only when a like-for-like baseline is present. Otherwise Tra-Vel displays the price without a savings claim.
7. The AI cockpit may show understanding and request readiness from the validated plan record. Supplier search, proposal, approval, and execution are protected stages: each requires a matching validated phase event. A broad run status alone cannot advance them.
8. Product cards use `data_mode` as a commercial boundary. `demo` and `mixed` results remain planning or assisted-check states. A seller action requires `live` data, a named non-demo provider, and an explicit bookable or purchasable capability.
9. Insurance products remain hidden unless the response is both `live` and explicitly declares `regulated_sale_ready: true`. Otherwise the screen only prepares neutral details for a licensed coverage check.
10. A configured planning amount may count or settle into place after the local comparison calculation completes. It must remain labeled as a planning price, retain the personal-final-quotation notice, and must not animate as supplier confirmation.
11. The complete spinning-Earth reveal is limited to the homepage and explicit surprise mode. Destination and SEO pages focus on their known location; product and saved-trip pages preserve the traveler’s active selection.
12. Homepage automatic motion is typed as `seasonal` or `evergreen`; an explicit traveler-triggered rerun is typed as `surprise`. Unknown or malformed campaign types fail closed, and analytics retain the same exact type from start through completion or cancellation.
13. Seasonal motion may land on the campaign destination. Evergreen motion uses neutral discovery copy and a stable daily rotation instead of a permanently pinned destination. Neither may call the result the traveler’s best deal before preferences and validated availability support that conclusion.
14. Reduced-motion and Save-Data modes commit the same truthful selection without preview spins, pulses, staged counters, or simulated background work.

### Typed detail-plane contract

The Earth remains the overview and gesture surface. A separate in-flow detail plane below it renders the active layer from `map_entities` and the selected route from `map_segments`.

- `deals`, `hotels`, `airports`, and `weather` resolve only to their matching entity kinds. A kind from another layer is discarded client-side.
- Every entity requires a known destination, legal WGS84 coordinates, bounded display copy, a same-origin action, source provenance, freshness, data mode, and truth state.
- Planning, current supplier snapshot, and last-observed values remain different states. All map prices are non-bookable context; the linked product search performs the fresh commercial check.
- Airport and weather entities do not invent prices. Deal and hotel prices include currency, scope, truth state, and the final-price notice.
- Route lines use only canonical airport coordinates. An unknown airport, malformed connection, or impossible coordinate fails the server contract instead of drawing approximate geometry.
- Layer refreshes remove the previous layer before showing a neutral busy state. A one-shot reveal runs only after a valid response is rendered.
- An open-ended request may show destinations to inspect, but it does not draw a recommended route as though the traveler selected it.
- Clicking a detail-plane entity may focus the matching destination on Earth. It does not silently change the trip, lock a price, or claim a booking.
- The detail plane is a responsive document-flow section. Only its markers and route SVG may use absolute positioning inside that contained plane; no supporting panel may cover the globe.

## Mobile behavior

- Earth remains the primary interactive region and retains pan, zoom, rotate, and selection gestures.
- Supporting information becomes a vertical journey or a single bounded sheet, not simultaneous floating panels.
- One active decision is emphasized at a time; completed context remains available without covering the map.
- Touch targets are at least 44 by 44 CSS pixels.
- Layout must work at 320 CSS pixels without horizontal page scrolling.
- Motion durations are shorter and large parallax/orbit effects are omitted.

## Accessibility contract

- Set `aria-busy="true"` only during an active request and return it to `false` when the request settles.
- Announce meaningful changes through one atomic polite live region; do not announce decorative frames.
- Use semantic ordered lists for progress and `aria-current="step"` for the current confirmed stage.
- Keep focus visible and stable. Do not move focus merely because an animation completed.
- Never encode progress, freshness, or success by color or movement alone.
- Reduced-motion mode must be functionally equivalent and must not delay information behind animation timers.

## Implementation guardrails

- Bind one-shot animation classes to a newly observed server version, status, event sequence, or successful persistence response.
- Persist the last observed version locally only to prevent replaying a success animation. Local state is not proof of server success.
- Treat a run status as lifecycle context, not evidence that protected commercial work occurred. Only the matching supplier-search, proposal, approval, or execution event may advance that step.
- Clear request timers and busy classes in a `finally` path.
- Treat malformed responses, ownership uncertainty, and missing provenance as errors or unavailable states.
- Classify account `401` and `403` responses as reauthentication requirements. Preserve local saves, stop corrective retries, and ask the traveler to sign in again or refresh.
- Avoid infinite animation on saved plans, quote cases, watch states, or itinerary stages.
- Do not start supplier, payment, or booking language from elapsed time alone.

## Release checks

- Exercise success, unchanged success, validation error, authorization error, server error, timeout, retry, stale data, terminal state, and recovery. Verify that a `401` or `403` stops account retries while local saving still works.
- Exercise `demo`, `mixed`, and `live` product responses. Verify that only a valid live provider can open a seller handoff and only an explicit regulated capability can expose insurance products.
- Verify the globe remains operable with mouse, keyboard, touch, and screen magnification.
- Verify no panel overlaps another panel or essential map control at desktop, tablet, and 320px mobile widths.
- Verify motion stops when requests settle and does not replay after refresh without a newer confirmed state.
- Verify reduced-motion mode contains no looping progress or route animation.
- Verify all price and savings motion retains source, freshness, currency, and scope labels.
