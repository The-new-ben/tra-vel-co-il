=== Tra-Vel Agent Core ===
Contributors: tra-vel
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 0.8.0
License: Proprietary

Private AI travel planning, durable assisted-quote cases, operator progress, and protected approval foundations for Tra-Vel.

== Description ==

This plugin is the server-side control plane for Tra-Vel agent runs. It stores private, expiring run state and append-only events, interprets natural-language travel requests through a strict OpenAI Responses schema, and applies deterministic clarification gates before any supplier work.

Raw natural-language prompts are processed in memory and are not persisted. Anonymous ownership secrets are held in Secure, HttpOnly, SameSite cookies and are not returned to JavaScript. Atomic database counters cap live provider requests per visitor and per UTC day, while owner-token leases limit concurrent provider work to two requests by default.

Version 0.7.0 adds the end-to-end sandbox commerce, supplier operations, VIP servicing, traveler registration, local tourism, loyalty value, and private Trip Cockpit foundations. Customer-safe views are owner-bound, capability-scoped, no-store, and revisioned; simulated commercial facts remain explicitly sandboxed until a validated provider response supplies live availability, price, and terms.

Version 0.8.0 adds the post-commit notification spine, bounded provider retries with raised interpretation capacity, and the truthful assisted-state Trip Cockpit feed. Notifications carry only the opaque TV reference; the optional operator webhook endpoint is stored encrypted and is never echoed by REST.

== Security ==

Use a hosting environment variable or wp-config.php constant for the OpenAI key when possible. The administrator-only encrypted-option fallback requires sodium. Keys are never returned by REST.

== Changelog ==

= 0.8.0 =

* Notification spine: post-commit `tra_vel_quote_case_created`, `tra_vel_assisted_proposal_published`, and `tra_vel_quote_case_traveler_action` actions with idempotent send-once markers, filterable operator email, an optional encrypted webhook channel behind admin-only REST settings, and a privacy-minimal Hebrew customer email on proposal publication.
* Provider resilience and capacity: bounded interpreter retries (two at most, transport/429/5xx only, jittered exponential backoff, Retry-After honored up to eight seconds) plus a 200-per-day global and 8-per-10-minutes visitor interpretation default.
* Trip Cockpit feed: wired the authoritative source provider and lifecycle emitter, added a truthful assisted-state snapshot projector from durable quote cases and published proposals only, and re-enabled the theme cockpit UI via `tra_vel_v2_cockpit_feed_available`.
* Release mechanics: version, readme, health fixtures, and validator pins moved to 0.8.0 while theme compatibility stays at Agent Core 0.7.0 or newer.

= 0.7.0 =

* Added seeded generic supplier profiles, deterministic sandbox search, offer, package, order, funds-flow, settlement, servicing, local-tourism, and loyalty contracts.
* Added secure traveler registration, no-login capability sessions, VIP intake routing, disruption recovery, and a private customer Trip Cockpit with immutable server projections.
* Added release health, schema, PHP 7.4/8.3, mobile RTL, privacy, and adversarial deployment gates for the new system.

= 0.6.0 =
* Added the closed AssistedProposal and AssistedProposalSource 1.0.0 contracts with immutable revisions, normalized evidence, append-only actions, and principal-scoped idempotency.
* Added a five-table InnoDB store bound to exact QuoteCase ownership, revision, request digest, retention, and legal-hold state.
* Added dedicated publication authorization plus traveler review, change-request, contact-authorization, and decline transitions without transactional claims.
* Added a mobile-responsive, safe-DOM operator composer whose reduced command is converted into a complete canonical proposal by the server.
* Added assignment checks before mutation and again under the locked parent case, exact major-to-minor money conversion, and mandatory source/gap derivation.
* Added exact case-version/request preconditions, signed five-minute evidence recheck attestations, registered public-provider host binding, and receipt-first composition replay.
* Separated human publication from canonical service ingestion and scoped operator proposal reads to current assignment.
* Added truthful capability and schema health, exact deployment gates, bounded child-first cleanup, and opt-in uninstall ordering.

= 0.5.0 =
* Added the closed CommercialIntent 1.0.0 contract and three-table InnoDB aggregate.
* Added exact account or private-browser ownership, same-origin guest mutations, rate limits, optimistic versions, and idempotent create/resume behavior.
* Added durable-before-navigation handoff events and a strict owned WhatsApp host/provider boundary.
* Added recursive sensitive-field rejection and explicit non-binding final-quote language without removing useful planning cards.
* Added schema health, bounded retention cleanup, deployment gates, and adversarial PHP and JavaScript validation.

= 0.4.1 =
* Added an authenticated, exact-owner GET /runs account history with a 12-plan default and a 20-plan cap.
* Added a closed AgentRun account-summary schema and private no-store collection responses.
* Added deterministic updated-time and primary-key ordering plus explicit 503 read-failure handling.
* Versioned the closed traveler QuoteCase DTO as 1.1.0 and added batched, fail-false source-run resume availability; QuoteCaseEvent 1.1.0 binds prepared contact events to the exact returned URL digest without retaining that URL.
* Added adversarial runtime and contract coverage for ownership isolation, expiry, ordering, DTO closure, cache policy, and source-run uncertainty.

= 0.4.0 =
* Added a closed, range-bounded planning context for destination and arbitrary Earth selections.
* Versioned the expanded public request schema as TripRequest 1.1.0 with kind-specific invariants.
* Preserved stable selection identity, coordinates, intent, and eight-domain scope across request revisions.
* Limited the append-only creation event to safe selection identity instead of storing exact coordinates.
* Added runtime and contract checks for map-context continuity and truthful progress semantics.

= 0.3.0 =
* Added durable assisted-quote cases with separate revisions, events, idempotency, retention, and guest ownership.
* Added exact traveler read/cancel/claim/handoff boundaries and an optimistic operator state machine.
* Added a least-privilege quote operator role and event-driven admin queue.
* Added fail-closed transactional schema gates, bounded recovery retries, and atomic quote-create/recovery limits.
* Made source synchronization monotonic and healed historical orphan retention rows with checked cleanup status.
* Kept supplier search, proposal generation, dispatch, and booking execution truthfully disabled.

= 0.2.1 =
* Deduplicated model and deterministic clarification prompts by material field.
* Required provider questions to name a supported canonical TripRequest field.
* Kept the deterministic policy wording and blocker when both layers identify the same missing decision.

= 0.2.0 =
* Added strict in-place TripRequest revisions from natural-language clarification.
* Preserved request identity while incrementing revisions and re-running deterministic readiness policy.
* Kept clarification text out of persistent storage and added idempotency, per-run locking, and truthful revision events.

= 0.1.1 =
* Preserved canonical event type separators and normalized legacy 0.1.0 event rows.

= 0.1.0 =
* Added strict TripRequest interpretation.
* Added private run ownership tokens and expiry cleanup.
* Added append-only event and protected approval storage.
* Added fail-closed capability and provider health reporting.
