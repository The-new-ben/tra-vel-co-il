=== Tra-Vel Agent Core ===
Contributors: tra-vel
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 0.4.0
License: Proprietary

Private AI travel planning, durable assisted-quote cases, operator progress, and protected approval foundations for Tra-Vel.

== Description ==

This plugin is the server-side control plane for Tra-Vel agent runs. It stores private, expiring run state and append-only events, interprets natural-language travel requests through a strict OpenAI Responses schema, and applies deterministic clarification gates before any supplier work.

Raw natural-language prompts are processed in memory and are not persisted. Anonymous ownership secrets are held in Secure, HttpOnly, SameSite cookies and are not returned to JavaScript. Atomic database counters cap live provider requests per visitor and per UTC day, while owner-token leases limit concurrent provider work to two requests by default.

Version 0.4.0 publishes TripRequest 1.1.0 and binds destination and arbitrary Earth selections to its validated planning context. Exact coordinates, intent, and the eight-domain planning scope survive natural-language revisions, while public events retain only the stable selection identity. The traveler interface derives its seven-stage progress display from real run and event states. It never treats waiting, failure, cancellation, supplier search, price, or booking as completed without corresponding evidence.

== Security ==

Use a hosting environment variable or wp-config.php constant for the OpenAI key when possible. The administrator-only encrypted-option fallback requires sodium. Keys are never returned by REST.

== Changelog ==

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
