# Tra-Vel traveler workspace and supplier handoffs

Tra-Vel V2 1.0 adds a consent-safe personal decision layer. Guests can save normalized flight, hotel, package, route and destination snapshots in browser storage. Signed-in WordPress users can also store those same bounded snapshots in private user meta through the `tra-vel/v2/workspace` REST contract.

The workspace deliberately excludes passport, payment, medical, underwriting and raw AI-conversation data. Server responses use `Cache-Control: private, no-store`, each user is limited to 50 normalized items, saved URLs are constrained to Tra-Vel, and the public schema fixes `sensitive_data_allowed` to `false`.

## Durable assisted requests

Agent Core 0.3.0 adds durable assisted quote cases beside saved comparison items. A traveler can explicitly consent to turn one ready private AI run into an owned request for human assistance. The planner and workspace then show the opaque `TV-XXXXXXXX` reference, persisted status, next action, and traveler-visible event history. This is a separate server-owned aggregate, not another browser-saved card and not a copy of the raw AI conversation.

The truthful states are `queued`, `in_review`, `needs_information`, `ready_for_assistance`, `closed_no_quote`, `cancelled`, and `expired`. Only a server-confirmed current state animates; completed steps settle, loading shimmer represents an HTTP request, and reduced-motion preferences disable nonessential motion. No timer implies that a supplier search, quote, message, reservation, payment, or booking occurred.

Traveler case routes live under `/wp-json/tra-vel-agent/v1/quote-cases`; the operator queue uses capability-protected routes under `/wp-json/tra-vel-agent/v1/operator/quote-cases`. Guest ownership uses a separate HttpOnly quote-owner cookie, signed-in ownership is exact to the WordPress user ID, and active cases have a 30-day service window with a normal 90-day deletion boundary. If the first cookie response is lost, the exact case can be recovered only through its still-owned private source run; a signed-in traveler with the matching guest cookie can claim it into the account. Case details embed at most 20 events, and longer histories load through bounded `after`/`has_more` pages. See [Tra-Vel assisted quote cases](QUOTE_CASE_OPERATIONS.md) for the full state, privacy, idempotency, handoff, and recovery contract.

## Routes

- `GET /wp-json/tra-vel/v2/workspace` — current signed-in user only.
- `POST /wp-json/tra-vel/v2/workspace/items` — create or refresh one normalized item.
- `DELETE /wp-json/tra-vel/v2/workspace/items/{id}` — remove one item.
- `PUT /wp-json/tra-vel/v2/workspace/items/{id}/watch` — store a target price state.
- `PUT /wp-json/tra-vel/v2/workspace/preferences` — store non-sensitive travel defaults.
- `DELETE /wp-json/tra-vel/v2/workspace` — clear the server workspace.
- `GET /wp-json/tra-vel/v2/workspace/schema` — public durable contract.

Price-watch delivery is intentionally disabled. A target can be saved, but `delivery_enabled` stays false and status stays `awaiting_live_supplier` until a reproducible live supplier price, an explicit notification-consent flow, delivery infrastructure and unsubscribe controls exist.

## Supplier handoff boundary

`POST /wp-json/tra-vel/v2/handoffs/prepare` resolves only providers registered through `tra_vel_v2_handoff_providers`. Every provider must declare supported verticals, its owned or affiliate relationship, disclosure text, an HTTPS host allowlist and a callable URL builder. The controller rejects unsupported provider/vertical pairs, userinfo, non-HTTPS URLs, unlisted hosts, missing disclosures and unverified providers.

The theme currently registers `tra-vel-concierge`, an owned assisted-sales provider that opens the public Tra-Vel WhatsApp channel on the allowlisted `api.whatsapp.com` host. It can carry bounded trip-planning context for flights, hotels, packages, insurance, cars, transfers, activities and eSIM, but deliberately excludes sample prices, passport details, payment data and medical answers. Its response is labeled `assisted_quote`, uses `rel="noopener noreferrer"`, sets `booking_on_partner: false`, and still requires a final price and availability check. It is not a supplier booking confirmation.

Any future affiliate provider must additionally be explicitly sponsored; successful affiliate responses use `rel="sponsored noopener noreferrer"` and state that booking happens with the partner. All successful handoff responses are private and non-cacheable, and any unregistered or invalid handoff continues to fail closed.
