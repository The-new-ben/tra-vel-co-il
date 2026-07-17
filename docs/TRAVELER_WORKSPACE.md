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

`POST /wp-json/tra-vel/v2/handoffs/prepare` resolves only providers registered through `tra_vel_v2_handoff_providers`. Every provider must declare supported verticals, its owned or affiliate relationship, disclosure text, an HTTPS host allowlist and a callable URL builder. The controller rejects unsupported provider/vertical pairs, userinfo, non-HTTPS URLs, unlisted hosts, missing disclosures and unverified providers.

The theme currently registers `tra-vel-concierge`, an owned assisted-sales provider that opens the public Tra-Vel WhatsApp channel on the allowlisted `api.whatsapp.com` host. It can carry bounded trip-planning context for flights, hotels, packages, insurance, cars, transfers, activities and eSIM, but deliberately excludes sample prices, passport details, payment data and medical answers. Its response is labeled `assisted_quote`, uses `rel="noopener noreferrer"`, sets `booking_on_partner: false`, and still requires a final price and availability check. It is not a supplier booking confirmation.

Any future affiliate provider must additionally be explicitly sponsored; successful affiliate responses use `rel="sponsored noopener noreferrer"` and state that booking happens with the partner. All successful handoff responses are private and non-cacheable, and any unregistered or invalid handoff continues to fail closed.
