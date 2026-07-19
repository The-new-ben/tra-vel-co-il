# Tra-Vel commerce sandbox contract

## Status and purpose

This document defines a contract-first, deterministic commerce sandbox. It does not implement a supplier connection, inventory hold, charge, insurance issuance, booking, payout, or settlement. The contracts let the product demonstrate the complete journey without presenting simulated outcomes as live commercial facts.

The sandbox is a separate domain from `CommercialIntent`, `QuoteCase`, and `AssistedProposal`. Those aggregates remain non-transactional. A future runtime may project their approved inputs into this system, but it must not add transactional fields or states to their existing contracts.

## Canonical product taxonomy

Every new commerce contract uses these exact vertical identifiers:

- `flight`
- `accommodation`
- `package`
- `transfer`
- `activity`
- `dining`
- `insurance`
- `connectivity`
- `equipment`

Compatibility code may translate legacy `hotel` to `accommodation` and legacy `esim` to `connectivity` at an old endpoint boundary. Stored commerce objects use only the canonical identifiers. A package is a composition of immutable component offers, not an independently invented price.

## Sandbox truth boundary

Every aggregate and receipt requires `environment: sandbox` plus a closed truth object. The truth object states that the inventory is simulated and that no real supplier request, inventory hold, charge, booking, policy issuance, or settlement occurred. A successful sandbox state means only that the deterministic sandbox transition completed.

Every response also carries a closed data boundary. Raw supplier references, raw payment data, and medical data are never exposed. Provider references, offers, orders, payment intents, operations, and settlements use opaque Tra-Vel references. External evidence is represented only by a SHA-256 digest and bounded normalized metadata.

The schemas intentionally contain no card number, CVV, bank account, passport, diagnosis, medical declaration, raw supplier locator, supplier booking reference, or unrestricted metadata object.

## Contract inventory

The Draft-07 schemas are stored under `plugin/tra-vel-agent-core/schemas/`:

| Schema | Responsibility |
| --- | --- |
| `commerce-provider.schema.json` | Safe registry descriptor and declared sandbox capabilities |
| `commerce-search-request.schema.json` | Canonical structured search input after language interpretation |
| `commerce-search-session.schema.json` | Owned, deterministic fan-out and ranking result |
| `commerce-offer.schema.json` | Immutable, expiring normalized offer snapshot |
| `commerce-package.schema.json` | Atomic composition of versioned offer snapshots |
| `commerce-order.schema.json` | Parallel checkout, payment, fulfillment, and settlement projections |
| `commerce-event.schema.json` | Ordered safe event projection |
| `commerce-operation.schema.json` | Idempotent command and reconciliation record |
| `commerce-settlement.schema.json` | Evidence-bound affiliate commission lifecycle |

All nested objects are closed with `additionalProperties: false`. Contract version `1.0.0` is immutable. A breaking field, enum, or semantic change requires a new contract version.

## Deterministic provider execution

A provider descriptor declares capabilities; registration alone never grants them. Search, revalidation, confirmation, fulfillment, supplier refund, payment authorization, capture, void, payment refund, webhook intake, reconciliation, conversion reporting, and settlement reconciliation are separate capability boundaries. Confirmation records the supplier's simulated acceptance decision. Fulfillment represents the later delivery lifecycle. Affiliate conversion reporting records a network report; settlement reconciliation separately determines whether commission is eligible, payable, or paid. The sandbox runtime should reject an operation when the selected provider does not declare the exact vertical and capability.

For one search request, the engine should:

1. Normalize and validate the closed request.
2. Persist its request digest and deterministic selection seed digest.
3. Select eligible providers by vertical and readiness.
4. Execute every eligible provider once for the provider-run reference.
5. Validate each response independently.
6. Normalize offers into immutable server-owned snapshots.
7. Deduplicate by normalized product and itinerary identity.
8. Rank with the declared ranking version and a stable tie-break.
9. Persist the ordered offer references before returning the session.

Provider registration order must not change the final ranking. The stable tie-break is score descending, comparable total in integer minor units ascending, provider priority ascending, then opaque offer reference ascending. Different currencies or price scopes are not comparable unless a separately evidenced conversion contract exists.

The `surprise` profile may animate a roulette-style selection, but the selected result is derived from the persisted seed digest and the ranked set. Animation never changes the chosen offer and never invents progress.

## Money invariants

All amounts are integers in currency minor units. A money ledger declares ISO currency, the currency minor-unit exponent, price scope, explicit line items, subtotal, tax, fees, credits, and total. Runtime validation must use checked integer arithmetic and prove:

```text
total_amount_minor = subtotal_amount_minor
                   + tax_amount_minor
                   + fee_amount_minor
                   - credit_amount_minor
```

Unknown tax or fee status remains explicit. A formatter may render an amount only from the ledger currency and exponent. It must not hard-code a currency symbol.

## Immutable offer and package boundary

The browser receives only an opaque `offer_ref` and version. Revalidation resolves that reference server-side. The browser never chooses a provider by sending a provider ID plus a supplier offer ID.

An offer snapshot binds:

- search session and provider descriptor;
- vertical and normalized product projection;
- complete integer money ledger and price scope;
- availability observation and expiry;
- cancellation, change, and inclusion summaries;
- terms, evidence, and provider-reference digests;
- map places and route segments;
- declared next-step capabilities;
- deterministic rank evidence.

A package binds each component offer reference and version. Package revalidation is atomic from the traveler perspective. If any required component expires or changes, the package becomes `revalidation_required` or `invalid`; the system does not silently substitute another component. Sandbox packages never claim a bundle discount or savings comparator.

## Parallel state model

One broad order status cannot safely describe money and fulfillment. The order stores four parallel projections and derives `overall_state` from them.

### Checkout

```text
draft -> quoted -> awaiting_approval -> ready
  |         |              |             |
  +-------> expired <-------+-------------+
  +-------> abandoned
```

### Payment

```text
not_started -> pending -> requires_action -> authorized -> captured
                  |                              |           |
                  +-> failed                     +-> voided   +-> partially_refunded -> refunded
```

These are simulated sandbox transitions. `real_charge` remains false in every state.

### Fulfillment per order item

```text
selected -> hold_pending -> held -> confirmation_pending -> confirmed
    |             |          |              |                 |
    +-------------+----------+--------------+-> failed         +-> change_pending -> changed
                                                            \-> cancellation_pending -> cancelled
```

Any ambiguous provider result moves the item to `reconciliation_required`. It does not infer success or failure.

### Affiliate settlement

```text
not_applicable
click_recorded -> conversion_reported -> eligible -> payable -> paid
                         |                  |          |         |
                         +-> disputed       +----------+-------> reversed
```

A click is not a conversion, a conversion report is not an eligible commission, and an eligible commission is not a paid settlement.

## Commands, approvals, and uncertain outcomes

Every consequential command carries:

- an opaque operation reference;
- exact target reference;
- expected order version;
- idempotency-key digest;
- request digest;
- scope digest;
- bounded approval reference and digest when required;
- provider capability and attempt count;
- one normalized result and receipt digest.

Operation states are `queued`, `started`, `succeeded`, `failed`, `uncertain`, and `reconciled`. Before a simulated side effect, the system records `side_effect.started`. It then records one evidence-bound terminal result. A timeout after dispatch is `uncertain`. Recovery reuses the same operation reference and idempotency identity, asks the adapter to reconcile, and records the resulting receipt. It must not issue a second command merely because the first response was lost.

The sandbox accepts only opaque fake payment-intent references. It never accepts or stores card fields. A future live payment adapter requires hosted payment fields, signed and replay-safe webhooks, PCI scope review, 3DS/SCA handling where applicable, capture and refund limits, and financial reconciliation before any live capability may be advertised.

Insurance remains a separate regulated boundary. The generic commerce contracts may carry an insurance offer reference, public coverage summary, consent digest, and policy-document digest. They do not carry a medical answer. A future live issuance path requires a licensed provider, current policy documents, explicit consent, private provider handling, and an issuance receipt outside generic search caches.

## Proposed REST projection

The schemas are designed for a future `/wp-json/tra-vel-commerce/v1` namespace:

| Method | Route | Meaning |
| --- | --- | --- |
| `GET` | `/health` | Safe environment, registry, and store readiness only |
| `GET` | `/schemas/{contract}` | One immutable public schema |
| `POST` | `/search-sessions` | Create one owned deterministic sandbox search |
| `GET` | `/search-sessions/{session_ref}` | Read the exact owner's safe search projection |
| `POST` | `/offers/{offer_ref}/revalidations` | Revalidate the server-owned offer snapshot |
| `POST` | `/packages` | Compose versioned component offers |
| `POST` | `/orders` | Create an order from versioned offer or package references |
| `GET` | `/orders/{order_ref}` | Read parallel state projections |
| `GET` | `/orders/{order_ref}/events` | Read ordered safe events after a sequence |
| `POST` | `/orders/{order_ref}/operations` | Submit one closed, versioned, idempotent command |

These routes are a design target only. The presence of these schema files does not mean the routes or capabilities are installed.

## Required validation before runtime work

Contract validation should reject:

- unknown fields at every nesting level;
- a non-canonical vertical;
- floating-point or negative unsigned money values;
- a malformed opaque reference or digest;
- a non-sandbox environment or a real-outcome truth flag;
- an offer whose freshness ends before observation;
- a package with duplicate or package-as-component offers;
- an order with no selected offer or package;
- an operation without exact version, idempotency, request, and scope digests;
- a refund beyond captured value;
- duplicate, out-of-order, or conflicting event sequences;
- a settlement amount that exceeds its evidenced commission;
- any raw supplier, payment, medical, or arbitrary metadata field.

Runtime tests must also prove adapter-order independence, partial-provider failure behavior, deterministic replay, offer expiry, owner isolation, same-key replay, different-payload conflict, uncertain reconciliation, webhook replay resistance, refund ceilings, and no double settlement.
