# Tra-Vel durable commercial intent

Status: implemented locally in Agent Core `0.5.0`; not deployed

Contract: `CommercialIntent 1.0.0`

## Purpose

Result cards must remain useful before live supplier inventory is connected. They may show clearly labelled planning prices, trade-offs, inclusions, and comparisons, with the statement that final price, availability, and terms are confirmed in the personal quote. A commercial click must still become a durable, owned, measurable request before the visitor leaves the site.

The commercial-intent aggregate provides that boundary. It is neither an AI run nor an order. It does not mean that Tra-Vel contacted a supplier, held inventory, sold insurance, charged a card, reserved a room, issued a ticket, or completed a booking.

## Traveler sequence

```text
result card CTA
  -> normalize safe trip and candidate identity
  -> create or resume owned CommercialIntent
  -> receive intent ID, public reference and version
  -> prepare handoff with expected version and a second idempotency key
  -> commit immutable handoff.prepared event
  -> return an allowlisted HTTPS URL
  -> navigate
```

If intent persistence, ownership, URL validation, or event recording fails, navigation does not occur. A timeout retains the same operation key so an uncertain write can be replayed. After a confirmed handoff, a later click receives a fresh key.

## Stored scope

The closed scope contains:

- vertical and result surface;
- planning data mode;
- requested provider and server-resolved provider;
- bounded offer and candidate identity;
- origin, destination and normalized dates;
- adults, children, infants, total travelers and rooms;
- budget ceiling, currency and safe same-site return path;
- an explicit `non_binding_planning_intent` commercial boundary.

The durable record does not contain browser-supplied numeric offer prices. The visible result card can retain a useful planning amount, but the stored intent identifies what must be revalidated rather than certifying that amount.

## Data that is rejected or omitted

- medical and pregnancy answers;
- traveler ages, dates of birth and health data;
- passport or identity data;
- email and phone details;
- card, bank, payment or policy fields;
- raw AI prompts, messages, and notes;
- booking, reservation, ticket, payment, policy, or issuance identifiers;
- a generic metadata object that could bypass the allowlist.

The policy recursively rejects sensitive field names even when they appear inside a nested object. Unknown benign fields are discarded by normalization and never enter the stored scope.

## Ownership and request security

Account-owned intents bind to the exact WordPress user ID. Guest intents use a random bearer in `__Host-tra_vel_commercial`; only its SHA-256 hash is stored. The cookie is `Secure`, `HttpOnly`, `SameSite=Lax`, has no Domain attribute, and cannot be read by the application JavaScript.

Guest mutations require an HTTPS Origin or Referer whose scheme, host, and effective port exactly match the WordPress home origin. Signed-in browser requests also carry the WordPress REST nonce. Create volume is reserved through the existing atomic Agent Core limit table.

Every private response sets:

```text
Cache-Control: private, no-store, max-age=0
X-Robots-Tag: noindex, nofollow, noarchive
Pragma: no-cache
```

## Storage

All three tables must be InnoDB before the routes become available:

- `{prefix}tra_vel_commercial_intents`: owner, safe scope, digest, version, event sequence, expiry and retention;
- `{prefix}tra_vel_commercial_intent_events`: immutable ordered creation and handoff evidence;
- `{prefix}tra_vel_commercial_intent_idempotency`: principal-scoped operation replay and conflict detection.

Creation or resume and handoff event insertion use transactions. Handoff updates compare the exact expected aggregate version and lock the owned record. The operation and event bind a SHA-256 digest of the exact allowlisted target URL without retaining that URL. Reusing a key after the target or any other canonical operation data changes returns `409`.

Active intent service lasts 30 days. The normal retention boundary is 90 days. Daily cleanup deletes expired replay rows and, after retention, removes child events and idempotency rows before the parent in a bounded transaction. Legal hold prevents parent deletion. Failed parent reads, locks, transactions, or deletes fail closed and are recorded in `tra_vel_commercial_intent_cleanup_status` instead of being reported as an empty successful run.

## REST API

Namespace: `/wp-json/tra-vel-agent/v1`

| Method | Route | Meaning |
| --- | --- | --- |
| `POST` | `/commercial-intents` | Create or resume the exact owner's active safe scope |
| `GET` | `/commercial-intents/{intent_id}` | Read the safe exact-owner projection |
| `POST` | `/commercial-intents/{intent_id}/handoffs` | Commit a versioned handoff event and then return its URL |
| `GET` | `/schema/commercial-intent` | Read the closed public JSON Schema |

The create response contains `intent`, `event`, `replayed`, `reused`, and `side_effect_executed: false`. The handoff response adds the provider disclosure, expiring URL, `price_recheck: true`, and `conversion_type: assisted_quote`.

## Current provider boundary

The browser may carry a requested supplier identity for future attribution, but it cannot prove that an offer came from that supplier. Until search results carry a server-issued, expiring, immutable commercial reference, Agent Core resolves the action to `tra-vel-concierge`.

The theme bridge calls the existing handoff controller internally. The plugin then independently requires:

- HTTPS;
- no embedded URL credentials;
- exact host `api.whatsapp.com`;
- exact owned provider `tra-vel-concierge`;
- a bounded expiry.

The filter prepares a URL only. The plugin records `handoff.prepared` with `dispatched: false` before returning it.

## Health and release dependency

Agent health exposes:

```text
commercial_intents: true
durable_commercial_handoffs: true
payment_execution: false
booking_execution: false
reservation_execution: false
ticket_issuance: false
commercial_intent_store.tables_ready: true
```

Theme `1.20.0` declares Agent Core `0.7.0`, the commercial-intent, sourced-proposal, commerce, VIP, and customer Trip Cockpit capabilities, and their durable stores as pre-deploy requirements. A theme upload is blocked before mutation if production still runs an older plugin or a required schema is incomplete.

## Sourced proposal boundary

The sourced personal-proposal layer is attached to a durable quote case, not to the short-lived result-card intent. It uses immutable revisions, source freshness, integer minor-unit ledgers, unresolved fee visibility, and traveler review/change/contact decisions. Its lifecycle remains non-transactional: no accepted, booked, paid, reserved, ticketed, insured, or issued state exists until independently implemented supplier and payment systems can prove those outcomes.
