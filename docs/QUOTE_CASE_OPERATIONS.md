# Tra-Vel assisted quote cases

## Purpose

Agent Core 0.3.0 separates two different lifecycles:

- `AgentRun` is private AI working state. It interprets and revises a request, expires quickly, and never represents a sale.
- `QuoteCase` is a durable, consented request for human assistance. It is the operator queue and the source of traveler-visible progress.

Creating a case does not search a supplier, produce a live price, send a message, reserve inventory, charge a card, or book travel. The public health contract continues to report supplier search, proposal generation, and booking execution as disabled.

The traveler-facing QuoteCase DTO is version 1.1.0 because it requires `resume_available`; its schema identity is distinct from QuoteCase 1.0.0. QuoteCaseEvent 1.1.0 adds an integrity-only SHA-256 digest that binds each prepared contact event to the exact short-lived URL returned to the traveler without storing that URL in the event. These representation changes do not alter the route namespace, stored aggregate, active-service window, or retention boundary.

## Data model

The module owns four dedicated tables:

1. `tra_vel_quote_cases` stores the aggregate identity, opaque public reference, exact owner, state/version, assignment, consent, activity, expiry, and retention boundary.
2. `tra_vel_quote_case_revisions` stores immutable minimized `TripRequest` snapshots and SHA-256 digests. Repeating the same structured request later is allowed as a new immutable revision; the `(case_id, revision_no)` pair is the revision identity.
3. `tra_vel_quote_case_events` is an append-only, ordered history with actor, source, visibility, before/after state, an allowlisted payload, and a payload digest.
4. `tra_vel_quote_case_idempotency` makes create, transition, cancel, claim, expiry, and handoff preparation replay-safe.

Case creation and every mutation use a database transaction. Mutations update only the expected aggregate version and increment the event sequence atomically. A repeated request with the same idempotency key and operation digest replays the committed winner, including when two identical requests race; a stale version or a reused key with different data returns `409`.

### Minimized revision contract

The durable snapshot is rebuilt server-side from the structured `TripRequest`. It keeps only:

- contract, request, and revision identifiers;
- a derived route/date title, language, bounded origin, up to eight bounded destinations, destination mode, bounded date text, and date flexibility;
- bounded adult, child, child-age, and room counts;
- a bounded amount, supported currency, and budget flexibility;
- the eight allowlisted planning verticals;
- readiness status and blocker count.

It does not copy the model-written summary, raw prompt, conversation, hard constraints, preferences, vibes, clarification questions, assumptions, contact details, medical data, passport data, payment data, or provider/source traces. Adding a future requirement requires a typed allowlist and a contract revision, not another free-form field.

Traveler-safe event data is also closed. It can contain only the small fields used by creation, request revision, ownership recovery, and assisted-contact preparation. At most four allowlisted properties are emitted on one event, and handoff events fix `dispatched` to `false`.

## Ownership and privacy

- Signed-in cases belong to the exact WordPress user ID.
- Guest cases use a separate 256-bit bearer secret in `__Host-tra_vel_quote_owner`, with `Secure`, `HttpOnly`, `SameSite=Lax`, no domain, and root path.
- Browser JavaScript never receives the bearer secret; the browser returns it only as the HttpOnly cookie. The client also never submits a client-authored `TripRequest`.
- A signed-in traveler may claim a guest case only while the matching guest cookie is also present. Claiming rotates the case to account ownership and clears its stored guest hash.
- A signed-in list can include both exact account-owned cases and guest cases still proved by that browser's quote-owner cookie. The interface then uses the protected claim route to move the guest case to the account.
- If case creation committed but its cookie response was lost, retrying creation through the still-owned source `AgentRun` can recover that exact guest case. The controller first verifies private run ownership, then the store atomically rotates a fresh guest owner or moves the case to the signed-in account and records `quote_case.owner_recovered`. A case ID alone can never trigger recovery.
- Creation and lost-response recovery reserve atomic capacity before generating a new owner token or writing idempotency/event rows. One source run receives four accepted attempts per UTC-day bucket by default, and one signed-in user or privacy-hashed visitor address receives twelve attempts per ten minutes. Exhaustion returns `429` without rotating ownership or appending progress.
- Public case responses omit database IDs, owner hashes, user IDs, assignment internals, consent metadata, full snapshots, and legal-hold state.
- The active service window is 30 days. The normal deletion boundary is 90 days unless an explicit legal hold is set.

Do not store passport numbers, payment credentials, medical answers, or unbounded contact notes in case events or revision snapshots.

## Truthful state machine

The initial assisted workflow uses only evidence the system can currently prove:

```text
queued
  -> in_review
  -> needs_information
  -> ready_for_assistance
  -> closed_no_quote

active state -> cancelled (traveler)
active state -> expired (retention job)
```

`needs_information` can return to `queued` or `in_review`. `ready_for_assistance` can return to review when facts change. Terminal states do not reopen silently.

When the traveler revises the AI plan, Agent Core freezes a new immutable case revision. A complete request returns to `queued`; a request with a new blocker moves to `needs_information`. Exact duplicate delivery is a no-op regardless of the current human-work status, and a source revision that is not strictly newer can never overwrite a newer frozen case revision. Transient source/case reads are distinguished from true absence and requeued through bounded exponential retry. This prevents an operator from acting on stale trip details without allowing a delayed callback to erase human progress.

The following state names are intentionally absent until evidence-bearing supplier and proposal records exist: `searching`, `price_found`, `proposal_ready`, `sent`, `reserved`, `paid`, and `booked`.

## State-driven motion contract

The planner, traveler workspace, and operator queue render the same persisted status and events.

- Only the current active step pulses.
- Completed steps settle and remain readable.
- A status-change arrival animation runs only after the server response changes the persisted state.
- Loading shimmer represents an in-flight HTTP request, not business progress.
- Handoff preparation is recorded as `handoff.prepared`; the event explicitly states that no message or booking was sent.
- Errors keep the last confirmed state visible and provide a retry path.
- `prefers-reduced-motion` disables sweep, pulse, spin, and arrival animation.

There are no decorative timers that advance case progress.

## REST surfaces

Traveler routes:

- `POST /tra-vel-agent/v1/runs/{run_id}/quote-cases`
- `GET /tra-vel-agent/v1/quote-cases`
- `GET /tra-vel-agent/v1/quote-cases/{case_id}`
- `GET /tra-vel-agent/v1/quote-cases/{case_id}/events?after={sequence}&limit={1..50}`
- `POST /tra-vel-agent/v1/quote-cases/{case_id}/cancel`
- `POST /tra-vel-agent/v1/quote-cases/{case_id}/claim`
- `POST /tra-vel-agent/v1/quote-cases/{case_id}/handoffs`

Operator routes:

- `GET /tra-vel-agent/v1/operator/quote-cases`
- `GET /tra-vel-agent/v1/operator/quote-cases/{case_id}`
- `GET /tra-vel-agent/v1/operator/quote-cases/{case_id}/events?after={sequence}&limit={1..50}`
- `POST /tra-vel-agent/v1/operator/quote-cases/{case_id}/transitions`
- `GET|POST /tra-vel-agent/v1/operator/quote-cases/{case_id}/assisted-proposals`
- `POST /tra-vel-agent/v1/operator/quote-cases/{case_id}/assisted-proposals/evidence-attestation`
- `POST /tra-vel-agent/v1/operator/quote-cases/{case_id}/assisted-proposals/compose`
- `POST /tra-vel-agent/v1/operator/quote-cases/{case_id}/assisted-proposals/{proposal_id}/compose`
- `POST /tra-vel-agent/v1/operator/quote-cases/{case_id}/assisted-proposals/{proposal_id}/withdraw`

Mutations require an idempotency key. State mutations also require the exact expected case version.

Event pages default to 50 records, fetch one look-ahead row, and return `events`, `last_sequence`, and `has_more`. A client advances `after` to the returned `last_sequence` until `has_more` is false. Traveler pages exclude internal events. A case detail embeds at most 20 traveler-visible events: the creation event plus the latest 19. Collection rows embed only the creation event. This keeps REST responses and the animated timeline bounded without losing the beginning of the case.

## Operator permissions

- `tra_vel_view_quote_cases` reads the queue.
- `tra_vel_manage_quote_cases` records legal state changes.
- `tra_vel_assign_quote_cases` is required to claim a case for review.
- `tra_vel_publish_assisted_proposals` publishes or withdraws a source-bound, non-binding proposal only for the assigned operator. Administrators are the explicit oversight override.
- `tra_vel_ingest_canonical_assisted_proposals` is reserved for administrators or a separately reviewed service identity. It is explicitly excluded from the quote-operator role and is never the human composer capability.
- `tra_vel_dispatch_supplier_requests` is a separate consequential capability and is not granted to the quote-operator role.

Administrators receive all six module capabilities. `tra_vel_quote_operator` receives WordPress read plus case view, manage, assign, and assisted-proposal publication, but never canonical ingestion or supplier dispatch.

## Operator proposal workflow

The queue exposes an explicit **Open proposals** action rather than making the whole row clickable. The in-flow workspace shows the minimized consented request, retained proposal history, and a five-part editor for proposal direction, evidence, trip components, itinerary, and final review.

The operator enters only traveler-facing content and real source references. Agent Core generates identities, binds the exact case version, latest case revision, and request digest, computes evidence and source-set digests, derives freshness, recomputes the one-currency minor-unit ledger, adds mandatory unresolved gaps, and fixes the final-quote disclosure. Public evidence links must also match a server-registered provider, source type, relationship, and hostname. Private connected or written evidence stores only an opaque reference.

The final review step issues a five-minute signed attestation only after the operator confirms every cited source was rechecked. The signature binds the operator and entire composition to the exact case context; any edit requires another check. Publication remains impossible for an unassigned operator, stale case, stale source, missing or invalid attestation, unsourced amount, mixed currency, unknown field, unsafe or unregistered public URL, or terminal case.

Connected, portal, and written source identities are operator-attested in this release, not provider-verified. Their canonical relationship is forced to the neutral `operator_attested` value, and no commercial-relationship field reaches traveler JSON. Default public providers are registered as public references only. Do not claim an affiliate or contracted relationship in traveler copy until an approved production registry and immutable integration evidence establish it.

Composition writes are replay-safe under a principal-bound idempotency key. The exact committed command can be recovered after a later reassignment or case change, but is returned as historical/superseded when it no longer addresses the current request. Reusing a key with changed content fails closed. The aggregate `version` advances for every durable state event, while commercial `revision` advances only for a new immutable proposal revision.

The UI never claims that a source was dispatched, inventory was held, payment was accepted, or travel was booked. Loading and publication motion represent only the corresponding network request and confirmed server result.

Traveler contact authorization is a separate one-click, versioned consent operation available only to a signed-in owner with a valid account email. The notice identifies the Tra-Vel assistance team, email channel, proposal-specific purpose, and exclusions for marketing, supplier sharing, booking, and charging. Agent Core persists only an HMAC-bound email target and rechecks the current target immediately before future dispatch; an account-email change invalidates the old dispatch authority without breaking exact idempotent action replay.

## Assisted WhatsApp boundary

Agent Core asks the active theme to prepare the already allowlisted owned handoff. The bridge reuses `/tra-vel/v2/handoffs/prepare`, accepts only the `tra-vel-concierge` owned provider, and Agent Core additionally restricts the returned URL to HTTPS on `api.whatsapp.com`.

The message carries the opaque `TV-XXXXXXXX` reference and minimized route context. It does not carry the raw AI prompt. The case event is appended only after URL preparation succeeds. Opening WhatsApp is still a traveler action; URL preparation is not recorded as delivery.

A matching preparation may be reused for five minutes only while it still belongs to the current case version and has more than 30 seconds remaining. Reuse does not append another progress event or increment the case version. At most six new preparations are allowed per case in a rolling hour; the seventh returns `429` with a retry interval. Idempotency remains the first replay boundary, while reuse and throttling protect retries that arrive with new keys.

## Retention cleanup

The daily cleanup is deliberately bounded to 100 records per batch, ten batches, and a 20-second wall-clock budget.

- Active cases past `service_expires_at` transition to `expired` through the normal versioned event path.
- Cases past `retention_until` are selected only when `legal_hold = 0` and locked with `FOR UPDATE`.
- One transaction deletes the selected cases' idempotency rows, events, revisions, and parent rows. Any failed child or parent deletion rolls back the batch, so cleanup cannot leave a partial case aggregate.
- Separate bounded, checked sweeps remove expired/orphaned idempotency rows and heal historical orphan revision or event children whose case no longer exists.
- Every cleanup SELECT captures database errors before treating an empty result as complete. Aggregate deletion, orphan reads, and orphan deletes report a stable error code and remain retryable instead of silently claiming success.

Legal hold prevents aggregate deletion; it does not invent a new public state. Cleanup is retryable, and reaching the batch or time budget leaves remaining work for the next cron run.

## Release and recovery

Agent Core and the theme are packaged and deployed independently through their protected workflows.

The Agent Core production gate verifies:

- plugin and schema versions;
- assisted-case and operator-queue capabilities are on;
- all four AgentRun tables and all four quote-case tables have their required columns, InnoDB engines, and concurrency indexes ready;
- supplier search, proposal generation, and booking execution remain off;
- an authenticated administrator can read the operator queue;
- deployed archive checksum matches the deterministic package.

If health or the authenticated queue smoke test fails, the workflow restores the previous plugin backup or removes the failed fresh install.

Before enabling any supplier dispatch, live proposal, payment, or booking state, add an evidence record, adapter-specific idempotency, protected approval, reconciliation process, and a new deployment gate. Do not reinterpret the current quote-case statuses as transactional proof.
