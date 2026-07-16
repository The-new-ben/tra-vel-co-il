# Tra-Vel total-trip package composer

## Product decision

The package page is an orchestration product above flights, stays, insurance, transfers, and add-ons. It does not flatten the trip into a promotional price card. Every result exposes the component sum, whole-party total, per-person reference, operational risk, flexibility, location fit, and exactly which requested extras are included.

The first safe fixture is a coherent TLV–Budapest city break. All bundled prices are demo estimates, all checkout URLs are null, and the response explicitly reports `bundle_discount_verified: false`. A future connected adapter may expose a discount only when it receives comparable standalone and bundled prices from an authorized supplier.

## Current competitor evidence

- ISSTA package pages emphasize flight + hotel, sometimes transfers, filters, and price per person: https://www.issta.co.il/packages
- Booking.com markets combined flight/hotel/car packages, all-tax pricing, and single-trip management: https://packages.booking.com/vacationpackages/?locale=en-us
- Expedia exposes selectable components and bundle badges, while noting that package pricing varies: https://www.expedia.com/product/bundle-and-save/

Tra-Vel differentiates through whole-party pricing, a visible component ledger, a route-and-stay map, profile-aware scoring, and explicit non-claims when a savings basis is unavailable.

## REST contract

- `GET /wp-json/tra-vel/v2/packages/search`
- `GET /wp-json/tra-vel/v2/packages/health`
- `GET /wp-json/tra-vel/v2/packages/schema`
- `DELETE /wp-json/tra-vel/v2/packages/cache` for administrators

Connected implementations register through `tra_vel_v2_trip_package_adapters`. Live adapters run before the bundled fallback. Results use fresh and stale transients with adapter provenance, short locks, safe failure behavior, and generation-based cache invalidation.

## Commercial connection checklist

1. Connect authorized flight, accommodation, transfer, and insurance suppliers.
2. Normalize currencies and preserve the source timestamp for every component.
3. Revalidate price, availability, baggage, cancellation, and room occupancy before checkout.
4. Keep insurance declarations and policy acceptance outside cached package searches.
5. Expose a package discount only from a supplier-supported comparable basis.
6. Replace the disabled demo action with a signed server-generated checkout handoff.
