# Tra-Vel flight search runtime

Tra-Vel V2 exposes a normalized search contract at `GET /wp-json/tra-vel/v2/flights/search`. The browser sends airports, dates, party, cabin, stop preference, sort and result limit. The response separates the ticket fare from the estimated full-trip cost so users can compare baggage, seats, hotel, transfers, insurance and any connection-night cost before choosing.

The bundled `curated_demo_flights` adapter is a transparent, non-bookable fallback. It must never claim live availability or expose a checkout URL. A commercial adapter can be registered with the `tra_vel_v2_flight_search_adapters` filter and must implement `Tra_Vel_V2_Flight_Search_Adapter`. Configured live adapters are tried before the demo adapter.

Runtime protections include query-specific caching, a short refresh lock, stale-live fallback when a supplier fails, adapter health reporting, explicit data-mode headers and an administrator-only cache purge route. Contract and runtime tests run in both pull-request CI and the protected production deployment workflow.

## Public routes

- `GET /wp-json/tra-vel/v2/flights/search`
- `GET /wp-json/tra-vel/v2/flights/health`
- `GET /wp-json/tra-vel/v2/flights/schema`
- `DELETE /wp-json/tra-vel/v2/flights/cache` — requires `manage_options`

No flight offer is bookable until a licensed supplier agreement, credentials, attribution rules, price-refresh policy, redirect/checkout terms and production monitoring are in place.
