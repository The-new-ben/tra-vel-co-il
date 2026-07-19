# Tra-Vel sourced assisted proposals

Status: implemented locally for the next Agent Core release; not deployed

## Product boundary

Tra-Vel separates four states that must never be presented as the same thing:

1. A homepage or map interaction produces an editable trip direction. It can use planning examples, but it is not a supplier search or a personal quote.
2. A private AgentRun turns the traveler's language into a structured request. It still does not contain a supplier result.
3. A sourced assisted proposal is published only after an authorized operator supplies fresh evidence for every displayed component. It is non-binding and always requires final revalidation.
4. A final quote, reservation, payment, ticket, insurance policy, or booking confirmation requires a separately connected transactional system. Those capabilities remain disabled in Agent Core.

This boundary keeps the interface useful and visually rich without presenting invented inventory as purchasable travel.

## Aggregate and immutable revisions

An assisted proposal belongs to one exact `QuoteCase`. The store locks both the numeric and UUID parent identity, verifies the current case revision and request digest, and rejects publication when the case has changed or is no longer active.

The aggregate has two counters:

- `version` changes for every durable proposal or traveler-state mutation.
- `revision` changes only when an immutable commercial proposal revision is appended.

Published content and its normalized evidence rows are immutable. Traveler review, change requests, contact authorization, decline, operator withdrawal, and lazy expiry update the head and append an audit event; they do not rewrite the sourced revision.

## Evidence and freshness

Every component points to one or more normalized evidence sources. Supported evidence types are connected API responses, supplier portals, written supplier quotes, public supplier pages, and official information. General official information can support factual content, but it cannot be the only evidence for a numeric commercial amount.

Each source records its provider, relationship, traveler-safe label, seller/supplier identity where applicable, observation time, revalidation deadline, and SHA-256 evidence digest. Public URLs must be credential-free HTTPS paths without query or fragment data. A server-owned provider registry binds each public provider code to its permitted source types, relationships, and hostnames; an operator cannot make an arbitrary link trusted by typing a provider name. Connected API, portal, and written evidence uses only a bounded opaque reference. Its private URL is never stored in the proposal contract.

Immediately before publication, the operator must confirm that every cited source was rechecked. Agent Core returns a five-minute signed evidence attestation bound to the operator, exact case version and revision, request digest, and the complete canonical composition. Any edit invalidates the browser attestation. The composer verifies the signature again and derives evidence observation times from that signed check, not from the later submit time. This proves an operator attestation; it does not prove supplier inventory, reserve a price, or replace final revalidation.

Private provider codes remain operator-attested labels in this release. Every connected, portal, or written source is forced to the neutral `operator_attested` relationship, and that relationship field is removed from traveler JSON. It is not a provider-verified integration fact. Provider-verified wording requires a future server-owned adapter/contract registry and an immutable provider response or offer artifact. Default public registry entries are references only; affiliate status must not be enabled until an approved production agreement registry supplies an active, reviewable relationship.

Freshness is bounded by source class. Proposal expiry cannot outlive its earliest source deadline, and a proposal cannot be published before its latest evidence observation. An elapsed proposal is projected as expired even before cleanup persists the lifecycle change.

## Cost ledger and gaps

Amounts use integer minor units and one currency per proposal. The server recomputes the ledger from the exact component set; a caller cannot submit a trusted total independently. Each component declares whether it is priced, its party basis, whether taxes and fees are included, and the source IDs supporting it.

An unpriced component carries no numeric amount or currency. Unknown taxes, unknown fees, unpriced components, and availability revalidation must appear as explicit unresolved items. Savings, comparator prices, discounts, booking outcomes, payment outcomes, policy numbers, tickets, reservations, and confirmation claims are outside the contract.

Every traveler-visible proposal carries this exact commercial boundary:

> Final price, availability, and terms are provided only after revalidation in a personal quote.

## Traveler actions

Initial publication starts in `awaiting_review` and exposes exactly four actions:

- review;
- request changes;
- authorize contact;
- decline.

After review, only request changes, authorize contact, and decline remain. Change requested, contact authorized, and declined are terminal traveler dispositions for version 1. A new sourced revision can return the proposal to review. Authorization means that the Tra-Vel assistance team may contact the verified account email only about that proposal. It does not authorize marketing, supplier sharing, message delivery, booking, or charging.

The browser sends the exact versioned consent purpose and channel only for `authorize_contact`; it never sends an email address or target digest. Guests see an account-login route instead of a contact-authorization button. The server derives a salted HMAC of the normalized current account email and stores no raw contact value. The idempotent command remains bound to the stable `account_email` target, so an exact retry remains recoverable, while dispatch must revalidate the immutable event against the current account-email HMAC. Changing the email therefore requires fresh consent before any contact can be dispatched.

Every mutation requires the current aggregate version and an idempotency key. Guest actions require the exact private-browser owner token; account actions require the exact account owner. The store rechecks ownership while the parent case is locked. Operator publication and withdrawal require the dedicated `tra_vel_publish_assisted_proposals` capability and never inherit traveler ownership.

New publication and withdrawal commands also require the case to be assigned to the current operator. A WordPress administrator is the explicit recovery and oversight exception. The controller checks assignment before mutation, and the store repeats the check after locking the parent case, so reassignment cannot race publication. Operator proposal reads are also assignment-scoped unless the user is an administrator.

Exact committed composition retries are receipt-first. A retry with the same principal, idempotency key, expected state, and complete command can recover the historical result even if the parent case was subsequently reassigned, revised, or closed. The response is marked replayed and projected as superseded when it no longer represents the current case. A changed command under the same key fails with a conflict, and every new write still passes current assignment and case-state checks.

## Operator composer

The **Tra-Vel Quotes** admin screen contains an in-flow, responsive proposal workspace. It keeps the case context beside the form on larger screens and returns to a single sequential column on mobile. The operator can review proposal history, enter a route and itinerary, add sources and 360-degree trip components, mark each component priced or unpriced, publish, and withdraw an available option.

The browser submits only bounded authored content and real source references. It never authors proposal or source UUIDs, public references, evidence hashes, source-set digests, ledger totals, case binding, lifecycle state, traveler actions, disclosure text, timestamps, or expiry. The server-owned composer derives all of those fields, adds mandatory availability and pricing gaps, and then passes the exact result through the existing publication policy and immutable store. Every compose request also carries the exact QuoteCase version, case revision, and request digest that the editor displayed; a stale tab cannot publish against a newer traveler request.

Human operators use `tra_vel_publish_assisted_proposals` and the reduced composer command. Direct ingestion of an already canonical proposal is a separate service/administrator boundary, `tra_vel_ingest_canonical_assisted_proposals`, and is explicitly removed from the quote-operator role. This prevents the raw canonical endpoint from bypassing the human composer policy.

Numeric amounts are optional. The default component is unpriced and contains no numeric placeholder. When an operator has current commercial evidence, the interface accepts a full-party amount in major units and converts it exactly to integer minor units without floating-point parsing. One currency applies to the complete proposal.

Unfinished form state remains in memory only. It is not placed in browser storage and is not sent to the traveler. A future durable draft workflow must use the existing immutable draft store through a separately authorized REST route; autosave must never call publication.

## REST surface

All private responses use `Cache-Control: private, no-store`, `X-Robots-Tag: noindex, nofollow, noarchive`, and `Pragma: no-cache`.

Traveler routes:

- `GET /wp-json/tra-vel-agent/v1/quote-cases/{case_id}/assisted-proposals`
- `GET /wp-json/tra-vel-agent/v1/quote-cases/{case_id}/assisted-proposals/{proposal_id}`
- `POST /wp-json/tra-vel-agent/v1/quote-cases/{case_id}/assisted-proposals/{proposal_id}/actions`

Operator routes:

- `GET|POST /wp-json/tra-vel-agent/v1/operator/quote-cases/{case_id}/assisted-proposals`
- `POST /wp-json/tra-vel-agent/v1/operator/quote-cases/{case_id}/assisted-proposals/evidence-attestation`
- `POST /wp-json/tra-vel-agent/v1/operator/quote-cases/{case_id}/assisted-proposals/compose`
- `GET /wp-json/tra-vel-agent/v1/operator/quote-cases/{case_id}/assisted-proposals/{proposal_id}`
- `POST /wp-json/tra-vel-agent/v1/operator/quote-cases/{case_id}/assisted-proposals/{proposal_id}/compose`
- `POST /wp-json/tra-vel-agent/v1/operator/quote-cases/{case_id}/assisted-proposals/{proposal_id}/withdraw`

Schema routes:

- `GET /wp-json/tra-vel-agent/v1/schema/assisted-proposal`
- `GET /wp-json/tra-vel-agent/v1/schema/assisted-proposal-source`
- `GET /wp-json/tra-vel-agent/v1/schema/assisted-proposal-traveler-source`

The schema endpoints are public documentation. Proposal collections and records are exact-owner or capability-protected.

## Storage, retention, and recovery

The module owns five InnoDB tables for proposal heads, append-only events, immutable revisions, normalized sources, and principal-bound idempotency. New heads and revisions are bounded so a faulty privileged client cannot grow a case indefinitely.

Proposal retention cannot exceed the parent QuoteCase retention boundary. Legal hold is copied and rechecked against the still-present parent before deletion. Cleanup is bounded by rows and wall-clock time, records durable operational status, and fails closed on read, lock, delete, or commit uncertainty. Agent Core data remains retained on uninstall unless the existing explicit data-removal constant is enabled after retention review.

## Release requirements

The feature is not production-ready merely because the PHP classes exist. The Agent Core release must also include:

- store installation and upgrade hooks;
- proposal cleanup before parent QuoteCase cleanup;
- health capabilities and exact store-schema health;
- schema, policy, store, controller, ownership, idempotency, retention, and runtime tests in CI;
- composer command, assignment-lock, exact-money, safe-DOM, responsive admin, and traveler partial-ledger tests in CI;
- signed evidence-attestation, public-provider binding, exact case-precondition, malformed-response, and receipt-replay tests in CI;
- a post-deploy health check that rejects stale code or partial tables;
- theme preflight requirements that block upload when the installed Agent Core version or proposal store is incomplete.

No production upload or activation is authorized by this document.
