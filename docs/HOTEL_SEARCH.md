# Tra-Vel hotel and neighborhood search

Tra-Vel V2 exposes a normalized hotel decision contract at `GET /wp-json/tra-vel/v2/hotels/search`. It compares room cost, taxes, fees, cancellation, payment timing, breakfast, family suitability, guest score, neighborhood trade-offs and travel time to the selected itinerary. The response keeps nightly price separate from total-stay price and includes canonical Tra-Vel area IDs for map interaction.

The bundled `curated_demo_hotels` adapter is a transparent, non-bookable fallback. A commercial inventory adapter can be registered with `tra_vel_v2_hotel_search_adapters` and must implement `Tra_Vel_V2_Hotel_Search_Adapter`. Supplier credentials stay server-side; live adapters are attempted before the demo fallback.

## Public routes

- `GET /wp-json/tra-vel/v2/hotels/search`
- `GET /wp-json/tra-vel/v2/hotels/health`
- `GET /wp-json/tra-vel/v2/hotels/schema`
- `DELETE /wp-json/tra-vel/v2/hotels/cache` — requires `manage_options`

The runtime provides query-specific fresh caching, a refresh lock, stale-live fallback, adapter health reporting, explicit data-mode headers and deterministic total-cost normalization for date and room changes. No demo property can expose checkout or claim live availability.
