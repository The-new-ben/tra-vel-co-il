import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {join, resolve} from 'node:path';
import vm from 'node:vm';

const repoRoot = resolve(import.meta.dirname, '..', '..');
const themeRoot = join(repoRoot, 'theme', 'tra-vel-v2');
const app = readFileSync(join(themeRoot, 'assets', 'js', 'app.js'), 'utf8');
const css = readFileSync(join(themeRoot, 'assets', 'css', 'app.css'), 'utf8');
const assets = readFileSync(join(themeRoot, 'inc', 'assets.php'), 'utf8');
const saved = readFileSync(join(themeRoot, 'page-saved.php'), 'utf8');
const schema = JSON.parse(readFileSync(join(repoRoot, 'plugin', 'tra-vel-agent-core', 'schemas', 'assisted-proposal.schema.json'), 'utf8'));
const failures = [];

const requireMarkers = (source, label, markers) => {
  for (const marker of markers) {
    if (!source.includes(marker)) failures.push(`${label} is missing ${marker}.`);
  }
};

requireMarkers(saved, 'Saved Trips proposal introduction', [
  'workspace-proposal-intro',
  'פתחו בקשה כדי לראות את ההצעות האישיות שלה',
  'שמונה חלקי חופשה',
]);

requireMarkers(app, 'Lazy assisted-proposal workspace', [
  'const workspaceAssistedProposalRuntime = new Map()',
  'proposalToggle.dataset.workspaceProposalsToggle',
  "proposalToggle.setAttribute('aria-expanded'",
  "proposalToggle.setAttribute('aria-controls'",
  "proposalPanel.setAttribute('role', 'region')",
  "proposalPanel.setAttribute('aria-labelledby'",
  'if (!proposalState.loaded && !proposalState.loading) loadWorkspaceAssistedProposals(caseData)',
  '/assisted-proposals?per_page=12',
  'normalizeWorkspaceAssistedProposalPayload(payload, caseId)',
]);

const initStart = app.indexOf('async function initTravelerWorkspace()');
const initEnd = app.indexOf('\nfunction ', initStart + 1);
const initBody = initStart >= 0 && initEnd > initStart ? app.slice(initStart, initEnd) : '';
if (!initBody || /loadWorkspaceAssistedProposals\s*\(/.test(initBody)) {
  failures.push('Traveler workspace initialization must not fetch assisted proposals before a quote-case control is opened.');
}
const proposalFetchCalls = [...app.matchAll(/loadWorkspaceAssistedProposals\s*\(/g)].map(match => match.index);
if (proposalFetchCalls.length !== 4) {
  failures.push(`Assisted proposal loading should have one definition plus explicit open, retry, and replay-reconciliation calls; found ${proposalFetchCalls.length}.`);
}

const scopedStart = app.indexOf('function workspaceAssistedProposalState(');
const scopedEnd = app.indexOf('\nfunction renderWorkspaceQuoteCaseCard(', scopedStart);
const scoped = scopedStart >= 0 && scopedEnd > scopedStart ? app.slice(scopedStart, scopedEnd) : '';
if (!scoped) failures.push('Assisted proposal workspace function boundary is missing.');
if (/innerHTML|outerHTML|insertAdjacentHTML|document\.write/.test(scoped)) {
  failures.push('Assisted proposal UI must use safe DOM construction and textContent, never HTML string injection.');
}
requireMarkers(scoped, 'Traveler-safe projection', [
  'public_label: publicLabel',
  'supplier_name: supplierName',
  'seller_name: sellerName',
  'source_url: sourceUrl',
  'observed_at: observedAt',
  'fresh_until: freshUntil',
  "link.rel = 'nofollow noopener noreferrer'",
  "new URL(source.source_url).hostname.replace(/^www\\./, '')",
  "workspaceAssistedProposalSourceTypeLabel(source.source_type)",
  "url.protocol !== 'https:' || url.username || url.password || url.port || url.search || url.hash",
  "/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d{1,6})?(?:Z|[+-]\\d{2}:\\d{2})$/",
]);
if (/appendTextElement\([^\n]*(?:request_digest|evidence_digest|source_reference|provider_code|latitude|longitude|prompt)/.test(scoped)) {
  failures.push('Private digests, source references, coordinates and prompts must never be written into proposal DOM.');
}

const expectedDisclosure = schema.definitions.disclosure.properties.message.const;
assert.equal(expectedDisclosure, 'Final price, availability, and terms are provided only after revalidation in a personal quote.');
if (!app.includes(`const workspaceAssistedProposalDisclosure = '${expectedDisclosure}'`)
  || !scoped.includes("exactDisclosure.lang = 'en'")
  || !scoped.includes("exactDisclosure.dir = 'ltr'")) {
  failures.push('The exact final-quote disclosure must be rendered without paraphrasing and with explicit language direction.');
}
requireMarkers(scoped, 'Hebrew-first commercial disclosure', [
  'המחירים נבדקו במועד המצוין. לפני רכישה נאמת שוב את המחיר, הזמינות והתנאים.',
  "const legalDisclosure = document.createElement('details')",
  "appendTextElement(legalDisclosure, 'summary', 'נוסח מסחרי מלא')",
  "const exactDisclosure = appendTextElement(legalDisclosure, 'p', proposal.disclosure.message)",
]);

const expectedCategories = ['flights', 'accommodation', 'transfers', 'activities', 'dining', 'insurance', 'connectivity', 'equipment'];
let previousCategoryIndex = -1;
for (const category of expectedCategories) {
  const index = app.indexOf(`{key: '${category}'`, previousCategoryIndex + 1);
  if (index < 0 || index <= previousCategoryIndex) failures.push(`The fixed 360 category lane order is missing or out of order at ${category}.`);
  previousCategoryIndex = index;
}
requireMarkers(scoped, 'Eight-lane truth boundary', [
  'workspaceAssistedProposalCategories.forEach(category =>',
  "const components = proposal.components.filter(component => component.category === category.key)",
  "proposal.next_actions.includes('request_changes')",
  'לא נכלל בהצעה הזאת.',
]);

requireMarkers(scoped, 'Ledger and completeness rendering', [
  'formatWorkspaceAssistedProposalMinor(proposal.ledger.priced_total_minor, proposal.ledger.currency)',
  'proposal.ledger.unpriced_component_keys',
  'proposal.unresolved_items',
  'pricedCount !== pricedComponents.length || pricedTotal !== calculatedTotal',
  '(pricedCount === 0 && ledgerCurrency !== null) || (pricedCount > 0 && ledgerCurrency === null)',
]);

requireMarkers(scoped, 'CAS and idempotent traveler actions', [
  "const retryKey = `${mutationKey}:${action}`",
  'workspaceAssistedProposalRetryKeys.get(retryKey) || createWorkspaceAssistedProposalIdempotencyKey()',
  'const requestBody = workspaceAssistedProposalActionRequestBody(action, proposal.version, idempotencyKey)',
  'body: JSON.stringify(requestBody)',
  "'assisted_proposal_action_timeout'",
  'workspaceAssistedProposalRetryKeys.delete(retryKey)',
  'payload?.replayed === true',
  'loadWorkspaceAssistedProposals(caseData, {force: true})',
  'cached && cached.version > confirmed.version',
  'תוצאת הפעולה אינה ודאית ומזהה הניסיון נשמר',
]);
if (!/generated\.length >= 16/.test(scoped)) failures.push('Proposal action idempotency keys must be at least 16 characters.');
if (!/const visibleActions = proposal\.next_actions\.filter\([\s\S]*?visibleActions\.forEach\(action =>[\s\S]*?recordWorkspaceAssistedProposalAction\(caseData, proposal, action\)/.test(scoped)) {
  failures.push('Action buttons must be derived only from the server next_actions array.');
}
if (!/rawProposal\.status === 'available' \? \[\.\.\.new Set\(serverActions\)\] : \[\]/.test(scoped)) {
  failures.push('Expired, withdrawn and superseded history must be readable but actionless.');
}
requireMarkers(scoped, 'Stale proposal action safety', [
  "if (state.stale || (proposal.status === 'available' && Date.parse(proposal.expires_at) <= Date.now()))",
  'state.stale ? {...proposal, next_actions: []} : proposal',
]);
requireMarkers(app, 'Stale parent-case projection', [
  'quoteCaseTerminalStatuses.has(caseData.status)',
  'currentRequestRevision !== proposalState.loadedRequestRevision',
]);
if (!/if \(error\?\.status === 409\) \{[\s\S]*?state\.stale = true;/.test(scoped)
  || !/error\?\.status === 401 \|\| error\?\.status === 403\) \{[\s\S]*?state\.stale = true;/.test(scoped)) {
  failures.push('Conflict and expired-authorization responses must immediately make cached proposals actionless.');
}

requireMarkers(scoped, 'Contact authorization boundary', [
  'לא בוצעה הזמנה או חיוב ולא נשלח מידע לספק',
  "action === 'authorize_contact' && !window.traVelV2?.isLoggedIn",
  "action !== 'authorize_contact' || isLoggedIn",
  "workspaceAssistedProposalContactNotice",
  "button.setAttribute('aria-describedby', consentNoticeId)",
  'התחברו כדי לאשר פנייה במייל',
  'הפנייה תישלח רק לכתובת הדוא״ל המאומתת בחשבון ולא תשותף עם ספקים.',
  "error?.code === 'tra_vel_assisted_proposal_contact_target_unverified'",
  "proposal.traveler_disposition === 'contact_authorized'",
  '!existingHandoff.hidden && !existingHandoff.disabled',
  "handoff.dataset.workspaceProposalHandoff = 'true'",
  "handoff.addEventListener('click', () => existingHandoff.click())",
  'פתיחת השיחה היא פעולה נפרדת. היא אינה הזמנה, תשלום או שליחה לספק.',
]);
requireMarkers(app, 'Closed contact-consent request contract', [
  "contract_version: '1.0.0'",
  "consent_version: '2026-07-19'",
  "affirmed: true",
  "purpose: 'assisted_proposal_follow_up'",
  "channels: Object.freeze(['email'])",
  "controller_scope: 'tra_vel'",
  "recipient_scope: 'tra_vel_assistance_team'",
  "contact_target: 'account_email'",
  "if (action === 'authorize_contact')",
  'body.contact_consent =',
]);
requireMarkers(assets, 'Account-login contact target', [
  "'loginUrl'     => esc_url_raw( wp_login_url( home_url( '/saved/' ) ) )",
]);
if (/authorize_contact[\s\S]{0,500}existingHandoff\.click\(\)/.test(scoped)) {
  failures.push('Authorizing contact must never automatically invoke the separate handoff action.');
}

requireMarkers(scoped, 'Accessible accordion and live status', [
  "toggle.setAttribute('aria-expanded'",
  "toggle.setAttribute('aria-controls'",
  "body.setAttribute('role', 'region')",
  "body.setAttribute('aria-labelledby'",
  "live.setAttribute('role', 'status')",
  "live.setAttribute('aria-live', 'polite')",
  "status.setAttribute('aria-atomic', 'true')",
]);
requireMarkers(css, 'Mobile proposal accordion', [
  '.workspace-quote-card.has-open-proposals { grid-column: 1/-1;',
  '.workspace-proposal-toggle { width: 100%; min-height: 66px;',
  '.workspace-proposal-lanes { display: grid; grid-template-columns: repeat(2,minmax(0,1fr));',
  '.workspace-proposal-rationale,.workspace-proposal-route-legs,.workspace-proposal-itinerary,.workspace-proposal-lanes,.workspace-proposal-sources > ul,.workspace-proposal-actions > div { grid-template-columns: 1fr;',
  '.workspace-proposal-actions .workspace-proposal-contact-notice {',
  '.workspace-proposal-contact-login { display: grid;',
  '.workspace-proposal-contact-login a { min-height: 44px;',
]);

const actionBodyStart = app.indexOf('function workspaceAssistedProposalActionRequestBody(');
const actionBodyEnd = app.indexOf('\nfunction ', actionBodyStart + 1);
const actionBodySource = actionBodyStart >= 0 && actionBodyEnd > actionBodyStart ? app.slice(actionBodyStart, actionBodyEnd) : '';
const contactContext = {Object};
vm.createContext(contactContext);
vm.runInContext(`
const workspaceAssistedProposalContactConsent = Object.freeze({
  contract_version: '1.0.0', consent_version: '2026-07-19', affirmed: true,
  purpose: 'assisted_proposal_follow_up', channels: Object.freeze(['email']),
  controller_scope: 'tra_vel', recipient_scope: 'tra_vel_assistance_team', contact_target: 'account_email'
});
${actionBodySource}
globalThis.buildActionBody = workspaceAssistedProposalActionRequestBody;
`, contactContext);
const contactBody = JSON.parse(JSON.stringify(contactContext.buildActionBody('authorize_contact', 7, 'proposal-action-key-1234')));
assert.deepEqual(contactBody, {
  action: 'authorize_contact', expected_version: 7, idempotency_key: 'proposal-action-key-1234',
  contact_consent: {
    contract_version: '1.0.0', consent_version: '2026-07-19', affirmed: true,
    purpose: 'assisted_proposal_follow_up', channels: ['email'], controller_scope: 'tra_vel',
    recipient_scope: 'tra_vel_assistance_team', contact_target: 'account_email',
  },
});
const reviewBody = JSON.parse(JSON.stringify(contactContext.buildActionBody('review', 7, 'proposal-action-key-1234')));
assert.equal(reviewBody.contact_consent, undefined, 'Contact consent must never accompany a non-contact traveler action.');

if (!/const higherServerVersion = confirmed\.version > proposal\.version;[\s\S]*?state\.confirmedUpdateId = higherServerVersion && !prefersReducedMotion\(\)/.test(scoped)
  || !/proposal\.proposal_id === animateId && !prefersReducedMotion\(\)/.test(scoped)
  || !css.includes('.workspace-proposal.is-confirmed-update { animation: workspace-proposal-confirm')
  || !/@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.workspace-proposal\.is-confirmed-update/.test(css)) {
  failures.push('Positive proposal motion must require a confirmed higher server version and stop for reduced motion or Save-Data.');
}

const normalizerStart = app.indexOf('function workspaceAssistedProposalUuid(');
const normalizerEnd = app.indexOf('\nfunction workspaceAssistedProposalPositionLabel(', normalizerStart);
const normalizerSource = normalizerStart >= 0 && normalizerEnd > normalizerStart ? app.slice(normalizerStart, normalizerEnd) : '';
const context = {URL, Date, Set, Map, Number, JSON, String, Array, Object, RegExp};
vm.createContext(context);
vm.runInContext(`
const workspaceAssistedProposalDisclosure = ${JSON.stringify(expectedDisclosure)};
const workspaceAssistedProposalCategories = ${JSON.stringify(expectedCategories.map(key => ({key})))};
${normalizerSource}
globalThis.normalizeWorkspaceAssistedProposal = normalizeWorkspaceAssistedProposal;
`, context);

const sourceA = '33333333-3333-4333-8333-333333333333';
const sourceB = '44444444-4444-4444-8444-444444444444';
const validFixture = {
  contract_version: '1.0.0',
  proposal_id: '22222222-2222-4222-8222-222222222222',
  case_id: '11111111-1111-4111-8111-111111111111',
  reference: 'TVP-AB12CD34EF56',
  status: 'available', version: 3, revision: 2, published_revision: 2,
  position: 'best_value',
  addresses: {case_revision: 4},
  title: 'Source-backed trip', summary: 'A complete traveler-safe summary.',
  why_it_fits: ['Matches the requested route'], trade_offs: ['One item remains unpriced'],
  route: {
    origin: 'Tel Aviv', destinations: ['Athens', 'Crete'],
    legs: [
      {sequence: 1, from: 'Tel Aviv', to: 'Athens', mode: 'flight'},
      {sequence: 2, from: 'Athens', to: 'Crete', mode: 'ferry'},
    ],
  },
  itinerary: [
    {day: 1, place: 'Athens', title: 'Arrive', component_keys: ['flight-main']},
    {day: 2, place: 'Crete', title: 'Check in', component_keys: ['stay-main']},
  ],
  components: [
    {
      component_key: 'flight-main', category: 'flights', title: 'Flight option', description: 'Returned by a connected source.',
      price: {priced: true, total_for_party_minor: 12345, currency: 'USD', basis: 'ticket_total', taxes: 'included', fees: 'unknown'},
      conditions: {cancellation: 'Supplier policy applies.', changes: 'Changes require revalidation.', baggage_or_inclusions: 'Cabin baggage is listed.'},
      source_ids: [sourceA], requires_revalidation: true,
    },
    {
      component_key: 'stay-main', category: 'accommodation', title: 'Stay option', description: 'Awaiting a final property response.',
      price: {priced: false, total_for_party_minor: null, currency: null, basis: 'not_priced', taxes: 'unknown', fees: 'unknown'},
      conditions: {cancellation: 'To be confirmed.', changes: 'To be confirmed.', baggage_or_inclusions: 'Room basis to be confirmed.'},
      source_ids: [sourceB], requires_revalidation: true,
    },
  ],
  ledger: {
    contract_version: '1.0.0', currency: 'USD', priced_total_minor: 12345, priced_component_count: 1,
    unpriced_component_keys: ['stay-main'], complete_pricing: false,
  },
  sources: [sourceA, sourceB].map((sourceId, index) => ({
    contract_version: '1.0.0', source_id: sourceId,
    source_type: 'connected_api',
    public_label: `Source ${index + 1}`, supplier_name: `Supplier ${index + 1}`, seller_name: `Seller ${index + 1}`,
    source_url: null,
    observed_at: index ? '2026-07-18T08:00:00Z' : '2026-07-18T08:02:00Z',
    fresh_until: index ? '2026-07-18T08:30:00Z' : '2026-07-18T08:45:00Z',
    requires_revalidation: true,
  })),
  freshness: {checked_at: '2026-07-18T08:02:00Z', expires_at: '2026-07-18T08:30:00Z', requires_revalidation: true},
  unresolved_items: [{code: 'unpriced_component', label: 'Stay price is not included yet.'}],
  traveler_disposition: 'awaiting_review', next_actions: ['review', 'request_changes', 'authorize_contact', 'decline'],
  disclosure: {commercial_state: 'non_binding_assisted_proposal', final_quote_required: true, message: expectedDisclosure},
  created_at: '2026-07-18T07:55:00Z', published_at: '2026-07-18T08:05:00Z', expires_at: '2026-07-18T08:30:00Z',
};

const clone = value => structuredClone(value);
const normalize = fixture => context.normalizeWorkspaceAssistedProposal(fixture, validFixture.case_id);
const normalized = normalize(validFixture);
assert.ok(normalized, 'A valid proposal fixture must normalize.');
assert.equal(normalized.addresses.request_digest, undefined, 'Request digests must not survive traveler projection.');
assert.equal(normalized.sources[0].relationship, undefined, 'Unverified commercial relationships must not survive traveler projection.');
assert.equal(normalized.sources[0].evidence_digest, undefined, 'Evidence digests must not survive traveler projection.');
assert.equal(normalized.sources[0].source_reference, undefined, 'Private source references must not survive traveler projection.');
assert.deepEqual([...normalized.next_actions], ['review', 'request_changes', 'authorize_contact', 'decline']);

const legacyReference = clone(validFixture);
legacyReference.reference = 'TVP-AB12CD34';
assert.ok(normalize(legacyReference), 'Legacy eight-character proposal references must remain readable.');

const reducedTravelerFixture = clone(validFixture);
delete reducedTravelerFixture.source_set_digest;
delete reducedTravelerFixture.addresses.request_digest;
delete reducedTravelerFixture.ledger.calculation_digest;
reducedTravelerFixture.sources.forEach(source => {
  delete source.provider_code;
  delete source.relationship;
  delete source.source_reference;
  delete source.evidence_digest;
});
assert.ok(normalize(reducedTravelerFixture), 'The minimized traveler REST projection must normalize end to end.');

const privateUrlLeak = clone(reducedTravelerFixture);
privateUrlLeak.sources[0].source_url = 'https://private-provider.example/evidence';
assert.equal(normalize(privateUrlLeak), null, 'A private evidence type must never expose a supplier lookup URL.');

const digestLeak = clone(reducedTravelerFixture);
digestLeak.source_set_digest = 'c'.repeat(64);
assert.equal(normalize(digestLeak), null, 'Traveler proposal normalization must reject leaked integrity digests.');

const sourceReferenceLeak = clone(reducedTravelerFixture);
sourceReferenceLeak.sources[0].source_reference = 'PRIVATE:REFERENCE';
assert.equal(normalize(sourceReferenceLeak), null, 'Traveler proposal normalization must reject leaked supplier lookup references.');

const relationshipLeak = clone(reducedTravelerFixture);
relationshipLeak.sources[0].relationship = 'contracted';
assert.equal(normalize(relationshipLeak), null, 'Traveler proposal normalization must reject unverified commercial-relationship labels.');

const publicSource = clone(reducedTravelerFixture);
publicSource.sources[0].source_type = 'public_supplier_page';
publicSource.sources[0].source_url = 'https://www.booking.com/hotel/fixture';
assert.ok(normalize(publicSource), 'A credential-free public source path must remain usable.');
const unsafePublicPort = clone(publicSource);
unsafePublicPort.sources[0].source_url = 'https://www.booking.com:444/hotel/fixture';
assert.equal(normalize(unsafePublicPort), null, 'Traveler source links must reject non-default HTTPS service ports.');

const allPricedWithUnknownFees = clone(validFixture);
allPricedWithUnknownFees.components[1].price = {priced: true, total_for_party_minor: 20000, currency: 'USD', basis: 'stay_total', taxes: 'included', fees: 'included'};
allPricedWithUnknownFees.ledger.priced_total_minor = 32345;
allPricedWithUnknownFees.ledger.priced_component_count = 2;
allPricedWithUnknownFees.ledger.unpriced_component_keys = [];
allPricedWithUnknownFees.ledger.complete_pricing = false;
allPricedWithUnknownFees.unresolved_items = [
  {code: 'availability_revalidation', label: 'Availability requires final revalidation.'},
  {code: 'fees_unknown', label: 'Flight fees require confirmation.'},
];
assert.ok(normalize(allPricedWithUnknownFees), 'All-priced proposals with unresolved fees must remain renderable as a truthful partial ledger.');

const expectRejected = (label, mutate) => {
  const fixture = clone(validFixture);
  mutate(fixture);
  assert.equal(normalize(fixture), null, label);
};
expectRejected('Duplicate component keys must be rejected.', fixture => { fixture.components[1].component_key = fixture.components[0].component_key; fixture.itinerary[1].component_keys = [fixture.components[0].component_key]; fixture.ledger.unpriced_component_keys = [fixture.components[0].component_key]; });
expectRejected('Duplicate source ids must be rejected.', fixture => { fixture.sources[1].source_id = fixture.sources[0].source_id; fixture.components[1].source_ids = [fixture.sources[0].source_id]; });
expectRejected('Duplicate itinerary days must be rejected.', fixture => { fixture.itinerary[1].day = fixture.itinerary[0].day; });
expectRejected('Duplicate itinerary component references must be rejected.', fixture => { fixture.itinerary[0].component_keys = ['flight-main', 'flight-main']; });
expectRejected('Duplicate route leg sequences must be rejected.', fixture => { fixture.route.legs[1].sequence = fixture.route.legs[0].sequence; });
expectRejected('Dangling itinerary component keys must be rejected.', fixture => { fixture.itinerary[0].component_keys = ['missing-component']; });
expectRejected('A zero-priced ledger cannot claim a currency.', fixture => {
  fixture.components[0].price = {priced: false, total_for_party_minor: null, currency: null, basis: 'not_priced', taxes: 'unknown', fees: 'unknown'};
  fixture.ledger.priced_total_minor = 0; fixture.ledger.priced_component_count = 0;
  fixture.ledger.unpriced_component_keys = ['flight-main', 'stay-main']; fixture.ledger.currency = 'USD';
});
expectRejected('Source freshness chronology must be strict.', fixture => { fixture.sources[0].fresh_until = fixture.sources[0].observed_at; });
expectRejected('Proposal publication must precede expiry.', fixture => { fixture.expires_at = fixture.published_at; });
expectRejected('Proposal creation cannot follow publication.', fixture => { fixture.created_at = '2026-07-18T10:00:00Z'; });
expectRejected('Freshness checks must precede freshness expiry.', fixture => { fixture.freshness.expires_at = fixture.freshness.checked_at; });
expectRejected('Freshness checked_at must equal the latest source observation.', fixture => { fixture.freshness.checked_at = '2026-07-18T08:01:00Z'; });
expectRejected('Freshness expires_at must equal the top-level proposal expiry.', fixture => { fixture.freshness.expires_at = '2026-07-18T08:25:00Z'; });
expectRejected('Proposal expiry cannot exceed the earliest source freshness boundary.', fixture => {
  fixture.freshness.expires_at = '2026-07-18T08:31:00Z';
  fixture.expires_at = '2026-07-18T08:31:00Z';
});
expectRejected('Proposal publication must not precede the latest evidence check.', fixture => { fixture.published_at = '2026-07-18T08:01:00Z'; });
expectRejected('Numeric version strings must not be coerced.', fixture => { fixture.version = '3'; });
expectRejected('Numeric amount strings must not be coerced.', fixture => { fixture.components[0].price.total_for_party_minor = '12345'; });
expectRejected('Loose human-readable dates must not be coerced.', fixture => { fixture.created_at = 'July 18 2026 08:00 UTC'; });

const historical = clone(validFixture);
historical.status = 'expired';
historical.traveler_disposition = 'unavailable';
historical.next_actions = ['review'];
assert.deepEqual([...normalize(historical).next_actions], [], 'Historical proposals must remain readable and actionless.');
const unknownAction = clone(validFixture);
unknownAction.next_actions = ['review', 'unknown_server_action'];
assert.deepEqual([...normalize(unknownAction).next_actions], ['review'], 'Unknown server actions must be ignored, never rendered.');

if (failures.length) {
  console.error('Assisted proposal theme contract failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Assisted proposal theme contract passed (lazy access, closed evidence, eight lanes, CAS actions, history, accessibility and truthful motion).');
