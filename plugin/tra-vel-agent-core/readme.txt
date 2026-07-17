=== Tra-Vel Agent Core ===
Contributors: tra-vel
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 0.2.0
License: Proprietary

Private AI travel-request interpretation, persistent run events, and protected approval foundations for Tra-Vel.

== Description ==

This plugin is the server-side control plane for Tra-Vel agent runs. It stores private, expiring run state and append-only events, interprets natural-language travel requests through a strict OpenAI Responses schema, and applies deterministic clarification gates before any supplier work.

Raw natural-language prompts are processed in memory and are not persisted. Anonymous ownership secrets are held in Secure, HttpOnly, SameSite cookies and are not returned to JavaScript. Atomic database counters cap live provider requests per visitor and per UTC day, while owner-token leases limit concurrent provider work to two requests by default.

Version 0.2.0 accepts natural-language clarification and preference changes inside the same private run. It does not search suppliers, quote live prices, or execute bookings. Its health response reports those capabilities as unavailable and its event log explicitly records that supplier search has not started.

== Security ==

Use a hosting environment variable or wp-config.php constant for the OpenAI key when possible. The administrator-only encrypted-option fallback requires sodium. Keys are never returned by REST.

== Changelog ==

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
