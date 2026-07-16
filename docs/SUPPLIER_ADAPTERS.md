# Tra-Vel supplier adapters

Tra-Vel V2 reads discovery data through a supplier registry. The bundled `curated_demo` adapter is always registered as a non-bookable fallback. Live adapters can enrich or replace normalized destination, route, hotel, insurance, and weather fields without changing the public REST or browser contract.

## Adapter contract

A live integration implements `Tra_Vel_V2_Supplier_Adapter` and returns a full or partial discovery contract from `fetch( $context )`. Every adapter declares:

- a stable public ID that contains no credential material;
- the supported verticals (`flights`, `hotels`, `insurance`, `weather`);
- whether required server-side configuration exists;
- `live` or `demo` mode;
- a cache version that changes when normalization logic changes.

Register it from a small integration plugin—not from a page template:

```php
add_filter(
	'tra_vel_v2_supplier_adapters',
	static function ( $adapters ) {
		$adapters[] = new Company_Tra_Vel_Flight_Adapter();
		return $adapters;
	}
);
```

Keep API keys in hosting environment variables or `wp-config.php` constants. Never return keys, tokens, raw supplier payloads, or request authorization headers from `fetch()` or health methods.

## Normalization rules

Live fragments merge by destination `id`. A supplied route set replaces the fallback route set for that destination. Provider status must set `connected: true` only after a successful normalized response. The runtime derives `demo`, `mixed`, or `live` mode from successful adapters and connected provider statuses.

## Resilience

- Fresh normalized results: 5 minutes.
- Stale fallback results: 24 hours.
- Refresh lock: 30 seconds to limit supplier stampedes.
- If every supplier refresh fails, the last valid stale response is returned with `cache_state: stale_error`.
- Cache invalidation uses a generation number, avoiding database-wide transient scans.

`GET /wp-json/tra-vel/v2/discovery/health` exposes safe adapter and cache state. Administrators can invalidate all discovery variants with authenticated `DELETE /wp-json/tra-vel/v2/discovery/cache`.
