# Commerce provider lifecycle research

This document translates current official supplier, payment, and industry guidance into the Tra-Vel Commerce Core contract. It is an architecture reference, not evidence that a production supplier is connected.

## Canonical lifecycle

| Stage | Commerce meaning | Required durable evidence |
| --- | --- | --- |
| Search | Discover candidates for one closed request | Provider and environment, search reference, criteria digest, party, currency, locale, result count, timeout report |
| Revalidate | Refresh inventory, price, fees, policies, and payment rules | Immutable offer version, source digest, integer ledger, policy digests, availability time, expiry, price delta |
| Hold | Temporarily reserve eligible inventory | Hold reference digest, status, expiry, payment deadline, price guarantee boundary |
| Confirm | Ask a supplier to create a reservation | Logical operation ID, provider idempotency translation, request digest, pending, confirmed, failed, or uncertain result |
| Fulfill | Issue the travel entitlement | Ticket, voucher, reservation, or policy evidence digest, issued time, fulfillment state |
| Modify | Quote a change, then explicitly apply it | Expiring change quote, add and remove set, fee, price delta, refund destination, result receipt |
| Cancel | Quote cancellation economics, then commit | Expiring cancellation quote, refundable amount, penalty, cutoff, reason, result receipt |
| Refund | Return supplier and customer funds | Supplier refund reference, payment refund reference, amount, destination, pending or terminal status |
| Settle | Reconcile the platform and supplier economics | Period, gross, supplier payable, commission, fees, tax, refunds, chargebacks, net, status |

Search results are not booking instructions. Revalidation does not create a reservation. A supplier order does not prove fulfillment. Supplier refunds do not prove that customer funds were returned. A reported affiliate conversion does not prove paid commission.

## Flights

The flight path is `search -> revalidate -> optional hold -> order -> ticket fulfillment`. Offers expire and must be retrieved again before booking. A hold can reserve space while its price guarantee follows a separate deadline. Fulfillment requires issued ticket evidence, not only an airline order identifier.

An order creation result can be indeterminate. A supplier may accept a request for asynchronous completion. That state must be stored as `uncertain`, not inferred as failure, and must not be blindly retried. The operation is reconciled using the supplier event or a fresh resource retrieval.

Changes and cancellations are quote-then-confirm processes. Their quotes contain price deltas, airline penalties, refund destination, and expiry. Supplier credit to an agency balance and repayment to the traveler remain separate records.

Primary references:

- [Duffel offers](https://duffel.com/docs/api/offers/get-offers)
- [Duffel holds and pay later](https://duffel.com/docs/guides/holding-orders-and-paying-later)
- [Duffel order creation response handling](https://duffel.com/docs/api/overview/response-handling/order-and-booking-creation)
- [Duffel order changes](https://duffel.com/docs/api/order-changes)
- [Duffel order cancellations](https://duffel.com/docs/api/order-cancellations)
- [Amadeus flight APIs](https://developers.amadeus.com/self-service/apis-docs/guides/developer-guides/resources/flights/)
- [IATA ONE Order](https://www.iata.org/en/programs/airline-distribution/retailing/one-order/)
- [IATA Settlement with Orders](https://www.iata.org/en/programs/airline-distribution/retailing/settlement-orders-swo/)

## Accommodation

The accommodation path is `search and availability -> order preview -> create -> retrieve -> modify or cancel -> reconcile`. Preview is the revalidation boundary. It verifies the final price, guest allocation, policies, payment schedule, and an expiring supplier order token. Modifications may require another preview because the economics can change.

Primary references:

- [Booking.com accommodation search](https://developers.booking.com/demand/docs/accommodations/search-for-available-properties)
- [Booking.com order preview and create](https://developers.booking.com/demand/docs/orders-api/order-preview-create)
- [Booking.com orders lifecycle](https://developers.booking.com/demand/docs/orders-api/overview)
- [Booking.com order modification](https://developers.booking.com/demand/docs/orders-api/order-modify)
- [Booking.com cancellation](https://developers.booking.com/demand/docs/orders-api/cancel-order)
- [Booking.com order reporting](https://developers.booking.com/demand/docs/orders-api/order-details)

## Activities and ground transport

Activities require a real-time availability check before hold and booking. Confirmation models can be instant, manual, or a combination. Amendment and cancellation are quote-then-confirm actions. Some providers expose delta feeds that must be polled and acknowledged, so Commerce Core requires both webhook ingestion and scheduled reconciliation capabilities.

Ground transport capability is provider-specific. Search, preview, booking, management, and cancellation may not all be commercially enabled even when search access exists. Capability declarations must therefore be configuration evidence, not assumptions based on registration.

Primary references:

- [Viator Partner API technical documentation](https://docs.viator.com/partner-api/technical/)
- [Viator partner models](https://docs.viator.com/partner-api/)
- [Amadeus cars and transfers](https://developers.amadeus.com/self-service/apis-docs/guides/developer-guides/resources/cars-transfers/)
- [Booking.com cars overview](https://developers.booking.com/demand/docs/cars/overview)
- [Booking.com car search tokens](https://developers.booking.com/demand/docs/cars/search-for-cars)

## Insurance

Insurance remains isolated from general travel caches and logs. The path is `quote -> booking -> optional payment confirmation -> policy fulfillment`. Modification and cancellation require their own current quote and explicit confirmation. Policy cancellation, customer refund, and any future claims process are separate lifecycles. Medical answers never enter generic commerce events.

Primary references:

- [XCover API](https://xcover-api-docs.covergenius.com/gl_insurance.html)
- [XCover webhooks](https://xcover-api-docs.covergenius.com/)
- [XCover idempotency](https://docs.covergenius.com/xcover/idempotency-keys)

## Payment and settlement

Payment state is independent from checkout and supplier fulfillment. The canonical payment path includes `not_started`, `pending`, `requires_action`, `authorized`, `captured`, `failed`, `voided`, `partially_refunded`, and `refunded`. Asynchronous outcomes are consumed server-side from signed, replay-safe events.

Refunds can remain pending or fail. Platform, supplier, and customer liability depends on the merchant model and charge type. Commerce Core therefore does not select a production Connect model in code. It stores customer payment, supplier payable, commission, processor fees, refunds, reversals, disputes, and net settlement separately.

Primary references:

- [Stripe PaymentIntent lifecycle](https://docs.stripe.com/payments/paymentintents/lifecycle)
- [Stripe fulfillment and status webhooks](https://docs.stripe.com/payments/payment-intents/verifying-status)
- [Stripe webhook security and delivery](https://docs.stripe.com/webhooks)
- [Stripe refunds](https://docs.stripe.com/refunds)
- [Stripe Connect charge models](https://docs.stripe.com/connect/charges)
- [Stripe separate charges and transfers](https://docs.stripe.com/connect/separate-charges-and-transfers)
- [Stripe idempotency](https://docs.stripe.com/api/idempotent_requests)

## Tra-Vel invariants derived from the research

1. Every adapter declares granular capabilities. Registration alone grants none.
2. Every quote, policy, change, and cancellation result is immutable and expiring.
3. Every consequential command binds owner, aggregate version, exact scope digest, and idempotency key.
4. A timeout becomes `uncertain`. It is reconciled, never retried as though nothing happened.
5. Every side effect has one durable operation ledger with `queued`, `started`, `succeeded`, `failed`, `uncertain`, or `reconciled` state.
6. Traveler confirmation copy appears only after provider fulfillment evidence exists.
7. Webhook inboxes deduplicate provider event IDs, verify signatures, store only raw-body digests, tolerate reordered delivery, and retrieve current provider resources.
8. Search, supplier sandbox, and payment sandbox are tested separately.
9. Package offers bind exact component offer references and versions. One stale component invalidates the atomic package revalidation.
10. Affiliate click, conversion, eligibility, payable commission, paid commission, reversal, and dispute remain separate settlement states.
