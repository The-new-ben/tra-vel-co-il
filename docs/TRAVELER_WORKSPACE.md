# Tra-Vel traveler workspace and supplier handoffs

Tra-Vel V2 1.0 adds a consent-safe personal decision layer. Guests can save normalized flight, hotel, package, route and destination snapshots in browser storage. Signed-in WordPress users can also store those same bounded snapshots in private user meta through the `tra-vel/v2/workspace` REST contract.

The workspace deliberately excludes passport, payment, medical, underwriting and raw AI-conversation data. Server responses use `Cache-Control: private, no-store`, each user is limited to 50 normalized items, saved URLs are constrained to Tra-Vel, and the public schema fixes `sensitive_data_allowed` to `false`.

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

`POST /wp-json/tra-vel/v2/handoffs/prepare` fails closed until another server-side integration registers a live, sponsored provider through `tra_vel_v2_handoff_providers`. A provider must declare supported verticals, disclosure text, an HTTPS host allowlist and a callable URL builder. The controller rejects userinfo, non-HTTPS URLs, unlisted hosts, missing disclosures and unverified providers.

Successful responses always require a final supplier price check, state that booking happens with the partner, use `rel="sponsored noopener noreferrer"`, and are private and non-cacheable. The demo theme registers no handoff provider and therefore cannot create a booking link.
