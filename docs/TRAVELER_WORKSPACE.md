# Tra-Vel traveler workspace and supplier handoffs

Tra-Vel V2 1.19 adds a consent-safe personal decision layer. Guests can save normalized flight, hotel, package, route and destination snapshots in browser storage. Signed-in WordPress users can also store those same bounded snapshots in private user meta through the `tra-vel/v2/workspace` REST contract.

The workspace deliberately excludes passport, payment, medical, underwriting and raw AI-conversation data. Server responses use `Cache-Control: private, no-store`, each user is limited to 50 normalized items, saved URLs are constrained to Tra-Vel, and the public schema fixes `sensitive_data_allowed` to `false`.

## Durable assisted requests

Agent Core 0.7.0 adds durable assisted quote cases and the private customer Trip Cockpit beside saved comparison items. A traveler can explicitly consent to turn one ready private AI run into an owned request for human assistance. The planner and workspace then show the opaque `TV-XXXXXXXX` reference, persisted status, next action, traveler-visible event history, and an owner-bound trip-service view without copying the raw AI conversation.

The truthful states are `queued`, `in_review`, `needs_information`, `ready_for_assistance`, `closed_no_quote`, `cancelled`, and `expired`. Only a server-confirmed current state animates; completed steps settle, loading shimmer represents an HTTP request, and reduced-motion preferences disable nonessential motion. No timer implies that a supplier search, quote, message, reservation, payment, or booking occurred.

## Current trip cockpit

The Saved Trips page now has a separate in-flow current-trip area above planning history and saved comparisons. It is not sourced from browser workspace data. `GET /wp-json/tra-vel-agent/v1/customer-trip-cockpit/current` resolves either the exact signed-in owner or an already exchanged short-lived capability session, loads the sealed private read model from the Agent Core repository, constructs a five-minute viewing assertion, and passes it through the customer redaction factory. The request cannot choose a trip, case, account, owner digest, viewing mode, scope, source bundle, or projection.

Signed-in views can show redacted money state, traveler readiness, and loyalty readiness. Scoped no-login views structurally withhold those sections. Case progress appears only when its low-risk scope is present, and all payment, refund, supplier, change, cancellation, and booking execution flags remain false. The theme stores none of this response in `localStorage`; a failed refresh retains only the in-memory last verified view until navigation. Loading, current, stale, empty-account, expired-link, rate-limit, reauthentication, and temporary-unavailable states have separate copy and one dedicated live announcer.

Traveler case routes live under `/wp-json/tra-vel-agent/v1/quote-cases`; the operator queue uses capability-protected routes under `/wp-json/tra-vel-agent/v1/operator/quote-cases`. Guest ownership uses a separate HttpOnly quote-owner cookie, signed-in ownership is exact to the WordPress user ID, and active cases have a 30-day service window with a normal 90-day deletion boundary. If the first cookie response is lost, the exact case can be recovered only through its still-owned private source run; a signed-in traveler with the matching guest cookie can claim it into the account. Case details embed at most 20 events, and longer histories load through bounded `after`/`has_more` pages. See [Tra-Vel assisted quote cases](QUOTE_CASE_OPERATIONS.md) for the full state, privacy, idempotency, handoff, and recovery contract.

## Sourced personal proposals

The Saved Trips cockpit loads personal proposals only after the traveler opens one exact quote case. It never performs a proposal request for every case during initial page load. Each returned proposal is revalidated against the closed traveler-safe contract before it is rendered.

The proposal view presents route, itinerary, rationale, tradeoffs, source identity and freshness, exact server ledger, conditions, unresolved work, status history, and eight fixed travel lanes: flights, stays, ground transport, activities, dining, insurance, connectivity, and equipment. Empty lanes remain visibly not included. The client does not create a fallback component, supplier, price, saving, score, schedule, or availability claim.

Only server-returned `next_actions` become buttons. Review, request changes, authorize contact, and decline use the current proposal version plus an idempotency key. A confirmed higher server version may trigger a short positive-state animation; replays, local timers, reduced-motion users, and Save-Data users do not. Historical, expired, withdrawn, superseded, stale-parent, and inaccessible proposals are actionless.

Contact authorization records permission only. It does not book, pay, reserve, issue, submit to a supplier, or open WhatsApp. The existing secure handoff remains a separate explicit action. Every proposal keeps the final-price, availability, and terms disclosure beside the ledger and action controls. See [Tra-Vel sourced assisted proposals](ASSISTED_PROPOSAL_SYSTEM.md) for the immutable revision, source, lifecycle, and audit contract.

## Routes

- `GET /wp-json/tra-vel/v2/workspace` — current signed-in user only.
- `POST /wp-json/tra-vel/v2/workspace/items` — create or refresh one normalized item.
- `PUT /wp-json/tra-vel/v2/workspace/sync` — merge one bounded browser snapshot into the signed-in account.
- `DELETE /wp-json/tra-vel/v2/workspace/items/{id}` — remove one item.
- `PUT /wp-json/tra-vel/v2/workspace/items/{id}/watch` — store a target price state.
- `PUT /wp-json/tra-vel/v2/workspace/preferences` — store non-sensitive travel defaults.
- `DELETE /wp-json/tra-vel/v2/workspace` — clear the server workspace.
- `GET /wp-json/tra-vel/v2/workspace/schema` — public durable contract.

## Bounded account sync

`PUT /wp-json/tra-vel/v2/workspace/sync` is the account-durability boundary for browser-saved comparison items. It requires WordPress cookie authentication with an `X-WP-Nonce` REST nonce, or another WordPress-supported authenticated client such as an Application Password over HTTPS. The route never accepts a user ID: it can read and write only the current WordPress user's private meta.

The JSON body requires `items`, an array of no more than 50 normalized saved-item inputs. `preferences` is optional. `deleted_item_ids` is an optional array of no more than 50 valid item IDs. Undeclared top-level fields, malformed items, duplicate item IDs, duplicate tombstones, and invalid tombstone IDs fail closed.

Tombstones are applied before submitted items are merged, so an item included in both `items` and `deleted_item_ids` remains deleted. Submitted items refresh matching snapshots, but the existing server-owned watch state is preserved. Items that exist only in the account are not removed merely because a browser did not submit them. If the combined unique set would exceed the fixed 50-item ceiling, the request is rejected instead of silently evicting an unrelated server item.

Every workspace mutation uses the exact user-meta value it read as a compare-and-swap condition. A concurrent different write returns `409` and remains intact; an already-applied identical target is success. Creating the first workspace uses a unique meta insert. WordPress cannot safely qualify an update or delete against an existing empty legacy meta value, so that malformed legacy row also fails closed with `409` until it is explicitly repaired. Adding an item through the single-item endpoint returns `409` when the account is already full instead of silently evicting an unrelated saved item; refreshing an existing item remains allowed.

All submitted and legacy stored records pass through the same server sanitization before they are returned. Internal links are constrained to Tra-Vel, malformed legacy records are skipped, malformed preferences fall back to bounded defaults, and watch delivery remains disabled. A browser-provided `data_mode` of `live` is always downgraded to `mixed`: browser JSON is a saved snapshot, not evidence of current supplier provenance. A future trusted server-side offer path must establish live provenance independently.

The browser treats `401` and `403` from any generic workspace mutation or reconciliation as an expired account connection, not as a temporary network failure. The local change remains saved, the workspace enters `reauth_required`, corrective retries stop, and later local changes do not issue more account requests until the page is refreshed or the traveler signs in again. Capacity, timeout, conflict, persistence, and transport errors remain separate states.

The sync response keeps the public workspace v1 shape unchanged: `version`, `items`, `preferences`, and `meta`. Like every private workspace response, it sends `Cache-Control: private, no-store, max-age=0` and an `X-Robots-Tag` noindex policy. A failed user-meta write returns an error and is never reported as a successful account sync.

Price-watch delivery is intentionally disabled. A target can be saved, but `delivery_enabled` stays false and status stays `awaiting_live_supplier` until a reproducible live supplier price, an explicit notification-consent flow, delivery infrastructure and unsubscribe controls exist.

## Supplier handoff boundary

`POST /wp-json/tra-vel/v2/handoffs/prepare` resolves only providers registered through `tra_vel_v2_handoff_providers`. Every provider must declare supported verticals, its owned or affiliate relationship, disclosure text, an HTTPS host allowlist and a callable URL builder. The controller rejects unsupported provider/vertical pairs, userinfo, non-HTTPS URLs, unlisted hosts, missing disclosures and unverified providers.

The theme currently registers `tra-vel-concierge`, an owned assisted-sales provider that opens the public Tra-Vel WhatsApp channel on the allowlisted `api.whatsapp.com` host. It can carry bounded trip-planning context for flights, hotels, packages, insurance, cars, transfers, activities and eSIM, but deliberately excludes sample prices, passport details, payment data and medical answers. Its response is labeled `assisted_quote`, uses `rel="noopener noreferrer"`, sets `booking_on_partner: false`, and still requires a final price and availability check. It is not a supplier booking confirmation.

Any future affiliate provider must additionally be explicitly sponsored; successful affiliate responses use `rel="sponsored noopener noreferrer"` and state that booking happens with the partner. All successful handoff responses are private and non-cacheable, and any unregistered or invalid handoff continues to fail closed.

The public product UI applies a second gate before calling this route. Planning and mixed responses show estimates, a provenance notice, and an assisted price-check action. A direct seller handoff is available only for a live response with a named non-demo provider and an explicit bookable or purchasable flag. Insurance product cards additionally require `regulated_sale_ready: true`; without it, Tra-Vel displays only a neutral checklist and an assisted coverage-check request.
