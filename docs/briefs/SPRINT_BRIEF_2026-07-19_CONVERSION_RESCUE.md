# Sprint brief — Conversion rescue (2026-07-19)

Priority order for the next working session. Scope: make the existing assisted-sales machine actually deliver leads to a human and make every lead measurable. No new product surfaces. Keep all truthful-UX gates intact. Theme work targets 1.21.0; Agent Core work targets 0.8.0.

## P0-1 — Operator + customer notifications (Agent Core)

Today no event in the plugin notifies anyone. Add action hooks and a notifier:

1. Fire `do_action( 'tra_vel_quote_case_created', $case_id, $context )` inside quote-case creation, `do_action( 'tra_vel_assisted_proposal_published', ... )` on proposal publish/revision, and `do_action( 'tra_vel_quote_case_traveler_action', ... )` on every traveler action (`authorize_contact`, `request_changes`, `decline`), after commit, never inside the transaction.
2. New bounded notifier module (own file, loaded from the main bootstrap):
   - Operator channel: `wp_mail` to a filterable operator address list + optional webhook URL (encrypted option, admin-settings route like the existing credential route) for Slack/Make/n8n. Payload: case reference `TV-XXXXXXXX`, status, destination summary, budget band, created time, deep link to the wp-admin queue. No PII beyond what the case snapshot already allows.
   - Customer channel: on proposal publish, `wp_mail` to the verified account email (proposal exists only for logged-in flow) — "הצעה אישית מחכה לכם" + deep link to /saved/. Respect an unsubscribe/notification-preference user meta.
3. Idempotency: notification per (case, event, sequence) recorded in an existing idempotency table pattern so retries never double-send.
4. Health endpoint: add `notifications` capability block reporting configured channels truthfully.

Acceptance: creating a quote case in staging sends the operator email within seconds; publishing a proposal emails the customer; both events visible in case history; zero notifications on replayed idempotent requests.

## P0-2 — Attribution capture (theme + Agent Core)

1. Theme: on first landing, capture `utm_source/medium/campaign/term/content`, `gclid`, `fbclid`, referrer, landing path into a first-party cookie (90 days) + sessionStorage, tiny module in app.js.
2. Pass the attribution snapshot into: commercial-intent creation, quote-case creation (new bounded `acquisition` object in the case context — source/medium/campaign/landing/referrer only, validated + length-capped), and the WhatsApp handoff prepare call.
3. Agent Core: persist `acquisition` on the case; expose it in the operator queue detail so a human sees which page/campaign produced each lead.
4. Do NOT block on consent tooling for first-party attribution, but keep the object free of personal data.

Acceptance: a case created from `/flights/?utm_source=test` shows that source in the operator detail; contract validators updated.

## P0-3 — Trip Cockpit: stop the dead call (theme, quick) + wire the feed (plugin, right after)

1. Quick theme fix: `/saved/` currently calls `GET tra-vel-agent/v1/customer-trip-cockpit/current` and receives 404 for every visitor (route not registered because the authoritative source provider + lifecycle emitter are never loaded). Until the feed is wired, gate the cockpit UI + `customer-trip-cockpit.js` enqueue behind a capability/health probe so the public page stops console-erroring.
2. Plugin fix: require + instantiate `class-tra-vel-customer-trip-cockpit-authoritative-source-provider.php` and `class-tra-vel-customer-trip-cockpit-lifecycle-emitter.php` in `includes/vip/bootstrap.php`, call their `register_hooks()`, and register the snapshot filter with a first real source: project the traveler's own quote cases + published assisted proposals into cockpit items (no fabricated bookings — items carry their true assisted状态 states only).
3. Health: cockpit block must report the provider/emitter wiring truthfully (`authoritative_feed: true/false`).

Acceptance: /saved/ has zero failed requests for anonymous visitors; a logged-in traveler with a published proposal sees it reflected in the cockpit; health reports the feed state.

## P1-4 — Contact capture before WhatsApp handoff (theme)

Before opening `wa.me`, show a two-field inline step (name + phone, Israeli format validated, consent checkbox with a link to privacy policy) and store it on the commercial intent / quote case. Prefill the WhatsApp message unchanged. Skippable ("המשיכו בלי להשאיר פרטים") to avoid killing conversion — but default path captures. Store consent timestamp + source with the contact.

Acceptance: handoff from a flight card stores name+phone+consent on the intent; operator sees contact in the queue; skipping still opens WhatsApp.

## P1-5 — Raise agent availability (Agent Core)

1. Add bounded retry with exponential backoff + jitter (2 retries max, only on transport errors / 429 / 5xx, respecting `Retry-After`) to the OpenAI provider call.
2. Raise the global UTC-day interpretation cap from 20 to 200 (filterable, still conservative) and per-visitor from 5/10min to 8/10min. Keep concurrency lease at 2.
3. When a run is created but interpretation failed (trip_request null), allow one `POST /runs/{id}/messages` retry to re-interpret instead of refusing — same revision rules, no second run.

Acceptance: transient 429 from provider no longer strands runs; existing contract tests updated; health reflects new limits.

## P1-6 — Homepage dead ends (theme)

1. Route-comparison cards on the homepage are inert `aria-pressed` buttons — connect each card to `/travel-map/?destination=…&route=…` so a tap opens the matching comparison, or make the whole card a link.
2. Restore the mobile persistent bottom nav on `/travel-map/` (present on home, absent on map) so the primary surfaces share one navigation contract.

## P2-7 — SEO hygiene batch (theme + WP admin ops)

1. Serve a real robots.txt (physical file via deployment or nginx include) with `Sitemap: https://tra-vel.co.il/sitemap_index.xml`.
2. Yoast: exclude noindexed pages from sitemaps (saved/account/hubs until published); disable author + category + cluster archives or noindex them; unpublish/redirect `hello-world`, `uncategorized`, the off-topic laptop post, the seychelles stub.
3. Remove the duplicate schema emitter plugin (empty `Organization{name:""}`, second BreadcrumbList, duplicate WebSite on every page — it is a live plugin, not in this repo). Remove `debug-log-manager` front-end script from production. Dequeue jQuery + jquery-migrate if nothing in the active theme uses them (verify first).
4. Disable `redirect_guess_404_permalink` so unlaunched registry URLs return clean 404s.

## P2-8 — Legacy/hub reconciliation plan (decision needed before execution)

The four destination hubs are noindexed while six legacy money pages hold the only indexable destination equity, with zero cross-links. Prepare (do not execute yet): publication-gate checklist for athens/budapest/prague/thailand hubs + 301 map from `/budapest-vacation/` → `/destinations/budapest/`, `/prague-vacation/` → `/destinations/prague/`, plus the two old Budapest posts. Ship hubs + redirects in one release.

## Conventions

- Follow existing store patterns: transactions, idempotency keys, bounded payloads, append-only events, fail-closed schema checks.
- Update the contract validators (`scripts/ci/validate-*`) alongside every behavior change; keep public copy inside the truthful-UX gates.
- No secrets in the repo; the repo is public.
- Version bumps: pin new versions in `release-requirements.json` + both release validators, as done for 1.20.x.
