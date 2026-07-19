#!/usr/bin/env node

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const paths = {
  composer: path.join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'class-tra-vel-assisted-proposal-composer.php'),
  controller: path.join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'class-tra-vel-assisted-proposal-controller.php'),
  store: path.join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'class-tra-vel-assisted-proposal-store.php'),
  admin: path.join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'class-tra-vel-quote-case-admin.php'),
  ui: path.join(root, 'plugin', 'tra-vel-agent-core', 'assets', 'admin', 'assisted-proposal-composer.js'),
  queue: path.join(root, 'plugin', 'tra-vel-agent-core', 'assets', 'admin', 'quote-cases.js'),
  css: path.join(root, 'plugin', 'tra-vel-agent-core', 'assets', 'admin', 'quote-cases.css'),
  bootstrap: path.join(root, 'plugin', 'tra-vel-agent-core', 'tra-vel-agent-core.php'),
  runtime: path.join(root, 'scripts', 'ci', 'validate-assisted-proposal-composer-runtime.php'),
  controllerRuntime: path.join(root, 'scripts', 'ci', 'validate-assisted-proposal-controller-runtime.php'),
  theme: path.join(root, 'theme', 'tra-vel-v2', 'assets', 'js', 'app.js'),
};

const failures = [];
const read = file => {
  if (!fs.existsSync(file)) {
    failures.push(`Missing ${path.relative(root, file)}.`);
    return '';
  }
  return fs.readFileSync(file, 'utf8');
};
const content = Object.fromEntries(Object.entries(paths).map(([key, file]) => [key, read(file)]));
const marker = (source, value, message) => {
  if (!source.includes(value)) failures.push(message);
};

for (const value of [
  'class Tra_Vel_Assisted_Proposal_Composer',
  'public static function compose(',
  'self::require_keys(',
  'Tra_Vel_Assisted_Proposal_Policy::compute_ledger(',
  'Tra_Vel_Assisted_Proposal_Policy::source_set_digest(',
  'Tra_Vel_Assisted_Proposal_Policy::validate_publication(',
  "'availability_revalidation'",
  "'unpriced_component'",
  "'taxes_unknown'",
  "'fees_unknown'",
  "'final_quote_required' => true",
  "'requires_revalidation' => true",
  "'revalidated_now'",
  'const COMMAND_VERSION',
  'wp_generate_uuid4()',
]) marker(content.composer, value, `Server composer is missing ${value}.`);

for (const forbidden of ['$_GET', '$_POST', '$_REQUEST', 'booking_id', 'payment_id', 'reservation_id']) {
  if (content.composer.includes(forbidden)) failures.push(`Server composer contains forbidden caller/transaction marker ${forbidden}.`);
}

for (const value of [
  "$operator_collection . '/compose'",
  "array( 'composition', 'expected_version', 'expected_case_version', 'expected_case_revision', 'expected_request_digest', 'idempotency_key' )",
  'Tra_Vel_Assisted_Proposal_Composer::compose(',
  'publish_composed_revision(',
  'replay_composed_revision(',
  'compose_proposal_revision(',
  'authorize_operator_case(',
  "current_user_can( 'manage_options' )",
  'tra_vel_assisted_proposal_assignment_forbidden',
]) marker(content.controller, value, `Controller is missing composer/assignment marker ${value}.`);

const lockIndex = content.store.indexOf('$locked_case = $this->lock_verified_parent_case( $case )');
const assignmentIndex = content.store.indexOf('$assignment = $this->validate_operator_assignment( $principal, $locked_case )', lockIndex);
if (lockIndex < 0 || assignmentIndex < lockIndex) failures.push('Operator assignment must be checked after the parent case is locked.');
marker(content.store, 'assigned_user_id,retention_until', 'Locked parent SELECT must include assigned_user_id.');
marker(content.store, "'assigned_user_id'      => absint", 'Normalized verified case must retain assigned_user_id for race detection.');

for (const value of [
  "'canPublish'",
  "'canOverrideAssignment'",
  "'currentUserId'",
  "'proposalReady'",
  "'sourceTtlMinutes'",
  'Tra_Vel_Quote_Case_Capabilities::PUBLISH_PROPOSALS',
  "wp_create_nonce( 'wp_rest' )",
  'assisted-proposal-composer.js',
]) marker(content.admin, value, `Admin localization is missing ${value}.`);

for (const value of [
  "'X-WP-Nonce'",
  "credentials: 'same-origin'",
  "'/compose'",
  'proposalExpectedVersion',
  'expected_version: expectedVersion',
	'expected_case_version: casePrecondition.expected_case_version',
	'expected_case_revision: casePrecondition.expected_case_revision',
	'expected_request_digest: casePrecondition.expected_request_digest',
  'idempotency_key:',
  "'/withdraw'",
  'majorToMinor',
  'form.checkValidity()',
  "control.dir = 'auto'",
  "control.dir = options.direction",
  "element('fieldset'",
  "element('legend'",
  'revalidated_now === true',
	'evidence_attestation_token',
	'I rechecked every cited source now',
	'evidenceAttestationCurrent',
  'source_indexes: sourceIndexes',
  'mutationProposal',
	'expected_status',
	'expected_case_revision',
	'expected_request_digest',
	'completeAccessMovedReplay',
	'tra_vel_assisted_proposal_assignment_forbidden',
  'tra_vel_assisted_proposal_response_uncertain',
  'tra_vel_assisted_proposal_network_uncertain',
  'state.uncertain',
  'Retry the exact protected request',
  'retryUncertainMutation',
  'data-tra-vel-proposal-uncertain-retry',
  'restoreState',
  'refreshConflict',
  'route_legs',
	'publicSourceProviders',
	'operator_attested',
	'Select a registered public provider',
	'reconcileSourceProviderPolicy',
  'textContent',
]) marker(content.ui, value, `Composer UI is missing ${value}.`);

for (const forbidden of ['innerHTML', 'outerHTML', 'insertAdjacentHTML', 'localStorage', 'sessionStorage', 'parseFloat(']) {
  if (content.ui.includes(forbidden)) failures.push(`Composer UI must not use unsafe/imprecise client mechanism ${forbidden}.`);
}
if (/evidence_(?:digest|hash)\s*[:=]/i.test(content.ui)) failures.push('Operators must not author evidence digests or hashes in the browser.');

const publishFunctionIndex = content.ui.indexOf('async function publishProposal');
const publishValidationIndex = content.ui.indexOf('var publishedProposal = mutationProposal', publishFunctionIndex);
const publishDraftClearIndex = content.ui.indexOf('state.draft = defaultDraft(state.item)', publishValidationIndex);
if (!(publishFunctionIndex >= 0 && publishValidationIndex > publishFunctionIndex && publishDraftClearIndex > publishValidationIndex)) {
  failures.push('Publish flow must validate the complete mutation response before clearing the operator draft.');
}
const retryFunctionIndex = content.ui.indexOf('async function retryUncertainMutation');
const retryValidationIndex = content.ui.indexOf('var confirmed = mutationProposal', retryFunctionIndex);
const retryDraftClearIndex = content.ui.indexOf('state.draft = defaultDraft(state.item)', retryValidationIndex);
if (!(retryFunctionIndex >= 0 && retryValidationIndex > retryFunctionIndex && retryDraftClearIndex > retryValidationIndex)) {
  failures.push('Uncertain retry must validate the complete mutation response before clearing the operator draft.');
}

for (const value of [
  'TraVelAssistedProposalComposer.mount',
  "heading.scope = 'col'",
  "proposalButton.setAttribute('aria-expanded'",
  "proposalButton.setAttribute('aria-controls'",
  'proposalWorkspaceStates',
  'renderProposalWorkspace(shell)',
]) marker(content.queue, value, `Queue integration is missing ${value}.`);

for (const value of [
  '.tra-vel-proposal-workspace__layout',
  '.tra-vel-proposal-field input:focus',
  '.tra-vel-proposal-review',
  '.tra-vel-proposal-source-checklist',
  '@media (max-width: 600px)',
  '@media (prefers-reduced-motion: reduce)',
  'grid-template-columns: 1fr;',
]) marker(content.css, value, `Composer styles are missing ${value}.`);

const policyInclude = content.bootstrap.indexOf("class-tra-vel-assisted-proposal-policy.php");
const composerInclude = content.bootstrap.indexOf("class-tra-vel-assisted-proposal-composer.php");
const controllerInclude = content.bootstrap.indexOf("class-tra-vel-assisted-proposal-controller.php");
if (!(policyInclude >= 0 && composerInclude > policyInclude && controllerInclude > composerInclude)) {
  failures.push('Plugin bootstrap must load policy, composer, then controller in dependency order.');
}

for (const value of [
  'A minimum real-source composition must produce a proposal.',
  'Unknown or raw evidence fields must fail closed.',
  'Mixed-currency proposal ledgers must fail closed.',
  'Source freshness cannot extend beyond parent retention.',
]) marker(content.runtime, value, `Composer runtime coverage is missing ${value}`);
for (const value of [
  'A publisher who is not assigned to the case must fail closed.',
  'The reduced composer command must produce and publish one server-owned proposal.',
  'An exact reduced-command retry must replay the original server-generated identity without a second write.',
  'The latest exact revision retry may retain its coherent available lifecycle and must not append a third revision.',
  'An old composition receipt must become non-actionable after a traveler action advances the live head.',
  'The original create receipt must remain historical after a newer commercial revision becomes live.',
  'An old composition receipt must remain historical after the live proposal is withdrawn.',
]) marker(content.controllerRuntime, value, `Controller runtime coverage is missing ${value}`);
marker(content.theme, 'calculatedCompletePricing', 'Traveler normalizer must honor taxes/fees when validating complete pricing.');

try {
  const context = {window: {}, document: {documentElement: {lang: 'en'}}, Number, String, Array, Object, RegExp, Date, Intl, JSON, Math, Set, Error};
  vm.createContext(context);
  vm.runInContext(content.ui, context);
  const api = context.window.TraVelAssistedProposalComposer;
  const convert = api.majorToMinor;
  assert.equal(convert('1250'), 125000);
  assert.equal(convert('1250.4'), 125040);
  assert.equal(convert('1250,45'), 125045);
  assert.equal(convert('1.234'), null);
  assert.equal(convert('12x'), null);
  assert.equal(api.expectedVersion(null), 0);
  assert.equal(api.expectedVersion({expected_version: 7}), 7);

  const caseUuid = '11111111-1111-4111-8111-111111111111';
  const proposalUuid = '22222222-2222-4222-8222-222222222222';
  const config = {restUrl: 'https://tra-vel.co.il/wp-json/tra-vel-agent/v1/operator/quote-cases/', nonce: 'nonce-fixture'};
  const item = {case_id: caseUuid, version: 9, case_revision: 3, source: {request_revision: 17, request_digest: 'a'.repeat(64)}};
  assert.equal(api.compositionUrl(config, item, null), `${config.restUrl}${caseUuid}/assisted-proposals/compose`);
  assert.equal(api.compositionUrl(config, item, {proposal_id: proposalUuid}), `${config.restUrl}${caseUuid}/assisted-proposals/${proposalUuid}/compose`);
  assert.equal(api.evidenceAttestationUrl(config, item), `${config.restUrl}${caseUuid}/assisted-proposals/evidence-attestation`);
  assert.equal(api.withdrawalUrl(config, item, proposalUuid), `${config.restUrl}${caseUuid}/assisted-proposals/${proposalUuid}/withdraw`);
  assert.deepEqual({...api.caseAuthoringContext(item)}, {expected_case_version: 9, expected_case_revision: 3, expected_request_digest: 'a'.repeat(64)});

  let observedRequest = null;
  context.window.fetch = async (url, requestOptions) => {
    observedRequest = {url, requestOptions};
    return {ok: true, status: 200, json: async () => ({proposal: {proposal_id: proposalUuid, version: 2}})};
  };
  await api.request(config, '/exact-endpoint', {method: 'POST', body: {expected_version: 1, idempotency_key: 'protected-key-0001'}});
  assert.equal(observedRequest.url, '/exact-endpoint');
  assert.equal(observedRequest.requestOptions.method, 'POST');
  assert.equal(observedRequest.requestOptions.credentials, 'same-origin');
  assert.equal(observedRequest.requestOptions.headers['X-WP-Nonce'], 'nonce-fixture');
  assert.deepEqual(JSON.parse(observedRequest.requestOptions.body), {expected_version: 1, idempotency_key: 'protected-key-0001'});

  context.window.fetch = async () => { throw new Error('response lost'); };
  await assert.rejects(api.request(config, '/mutation', {method: 'POST', body: {}}), error => error.outcomeUncertain === true && error.code === 'tra_vel_assisted_proposal_network_uncertain');
  await assert.rejects(api.request(config, '/read'), error => error.outcomeUncertain === false && error.code === 'tra_vel_assisted_proposal_network_uncertain');
  context.window.fetch = async () => ({ok: false, status: 502, json: async () => ({code: 'bad_gateway', message: 'Gateway lost the response.'})});
  await assert.rejects(api.request(config, '/mutation', {method: 'POST', body: {}}), error => error.outcomeUncertain === true && error.status === 502);

  const draft = {
    position: 'best_value', currency: 'ILS', title: 'Sourced option', summary: 'A complete authored summary.',
    why_it_fits: 'Matches the route.', trade_offs: 'Requires final revalidation.', origin: 'Tel Aviv', destinations: 'Athens',
    route_legs: [{sequence: 1, from: 'Tel Aviv', to: 'Athens', mode: 'flight'}],
    sources: [
      {provider_code: 'source-one', source_type: 'supplier_written_quote', relationship: 'operator_attested', public_label: 'Source one', supplier_name: '', seller_name: 'Tra-Vel', source_reference: 'QUOTE:ONE', source_url: '', freshness_minutes: 60, revalidated_now: true},
      {provider_code: 'source-two', source_type: 'supplier_written_quote', relationship: 'operator_attested', public_label: 'Source two', supplier_name: '', seller_name: 'Tra-Vel', source_reference: 'QUOTE:TWO', source_url: '', freshness_minutes: 60, revalidated_now: true},
    ],
    components: [{component_key: 'flight-option', category: 'flights', title: 'Flight option', description: 'Current sourced option.', priced: false, amount_major: '', basis: 'trip_total', taxes: 'unknown', fees: 'unknown', cancellation: 'Final quote.', changes: 'Final quote.', inclusions: 'Final quote.', source_indexes: [0, 1]}],
    itinerary: [{day: 1, place: 'Athens', title: 'Arrival', component_keys: 'flight-option'}],
    extra_gaps: [
      {code: 'policy_revalidation', label: 'Confirm the fare policy.'},
      {code: 'schedule_revalidation', label: 'Confirm the final schedule.'},
    ],
  };
  const composition = api.buildComposition({draft});
  assert.deepEqual(Array.from(composition.components[0].source_indexes), [0, 1]);
  assert.deepEqual(Array.from(composition.unresolved_items, item => item.code), ['policy_revalidation', 'schedule_revalidation']);
  assert.deepEqual(Object.keys(composition.sources[0]).sort(), ['freshness_minutes', 'provider_code', 'public_label', 'relationship', 'revalidated_now', 'seller_name', 'source_reference', 'source_type', 'source_url', 'supplier_name'].sort());
  const attestationState = {
    draft,
    evidenceAttestation: {
      token: 'token-' + 'x'.repeat(100),
      checked_at: new Date().toISOString(),
      expires_at: new Date(Date.now() + 240000).toISOString(),
      serialized: JSON.stringify(composition),
    },
  };
  assert.equal(api.evidenceAttestationCurrent(attestationState), true);
  assert.equal(api.buildComposition(attestationState, true).evidence_attestation_token, attestationState.evidenceAttestation.token);
  attestationState.draft.title = 'Changed after attestation';
  assert.equal(api.evidenceAttestationCurrent(attestationState), false);
  assert.throws(() => api.buildComposition(attestationState, true), /Record a fresh final evidence check/);
  assert.equal(api.attestationResponse({attestation_token: 'signed-' + 'x'.repeat(100), checked_at: new Date().toISOString(), expires_at: new Date(Date.now() + 240000).toISOString()}).attestation_token.startsWith('signed-'), true);
  assert.throws(() => api.attestationResponse({attestation_token: 'short', checked_at: '', expires_at: ''}), /valid short-lived attestation/);
  const unattested = JSON.parse(JSON.stringify(draft));
  unattested.sources[0].revalidated_now = false;
  assert.throws(() => api.buildComposition({draft: unattested}), /Recheck and attest every exact evidence source/);
  const removable = {draft: {sources: [{}, {}, {}], components: [{source_indexes: [2]}]}, notice: null};
  assert.equal(api.removeSourceAt(removable, 1), true);
  assert.deepEqual(Array.from(removable.draft.components[0].source_indexes), [1]);
  assert.equal(api.removeSourceAt(removable, 1), false);

  const sourceState = {root: null, draft: {sources: [{revalidated_now: true}, {revalidated_now: true}], components: []}};
  api.invalidateComponentSources(sourceState, {source_indexes: [0, 1]});
  assert.equal(sourceState.draft.sources[0].revalidated_now, false);
  assert.equal(sourceState.draft.sources[1].revalidated_now, false);

  const retained = {retainedDrafts: []};
  api.retainDraft(retained, {...draft, title: 'Draft A'}, 'conflict A');
  api.retainDraft(retained, {...draft, title: 'Draft B'}, 'conflict B');
  assert.equal(retained.retainedDrafts.length, 2);
  assert.equal(retained.retainedDrafts[0].draft.title, 'Draft B');
  assert.equal(api.retainDraft(retained, {...draft, title: 'Draft C'}, 'conflict C'), true);
  assert.equal(api.retainDraft(retained, {...draft, title: 'Draft D'}, 'conflict D'), false);
  assert.equal(retained.retainedDrafts.length, 3);
  assert.equal(retained.retentionCapacityReached, true);
  assert.equal(retained.retainedDrafts.some(copy => copy.draft.title === 'Draft A'), true);

  const proposalId = '123e4567-e89b-42d3-a456-426614174001';
  const sourceId = '33333333-3333-4333-8333-333333333333';
  const completeProposal = {
    contract_version: '1.0.0', proposal_id: proposalId, case_id: caseUuid, reference: 'TVP-ABCDEF12',
    status: 'available', version: 2, revision: 2, published_revision: 2, position: 'best_value',
    addresses: {case_revision: 3, request_digest: 'b'.repeat(64)},
    title: 'Verified option', summary: 'Complete canonical operator projection.',
    why_it_fits: ['Matches the request.'], trade_offs: ['Requires final revalidation.'],
    route: {origin: 'Tel Aviv', destinations: ['Athens'], legs: [{sequence: 1, from: 'Tel Aviv', to: 'Athens', mode: 'flight'}]},
    itinerary: [{day: 1, place: 'Athens', title: 'Arrival', component_keys: ['flight-option']}],
    components: [{
      component_key: 'flight-option', category: 'flights', title: 'Flight', description: 'Sourced flight option.',
      price: {priced: false, total_for_party_minor: null, currency: null, basis: 'not_priced', taxes: 'unknown', fees: 'unknown'},
      conditions: {cancellation: 'Final quote.', changes: 'Final quote.', baggage_or_inclusions: 'Final quote.'},
      source_ids: [sourceId], requires_revalidation: true,
    }],
    ledger: {contract_version: '1.0.0', currency: null, priced_total_minor: 0, priced_component_count: 0, unpriced_component_keys: ['flight-option'], complete_pricing: false, calculation_digest: 'c'.repeat(64)},
    sources: [{contract_version: '1.0.0', source_id: sourceId, provider_code: 'fixture-air', source_type: 'supplier_written_quote', relationship: 'operator_attested', public_label: 'Fixture evidence', supplier_name: 'Fixture Air', seller_name: 'Tra-Vel', source_reference: 'QUOTE:FIXTURE', source_url: null, observed_at: '2030-01-01T10:00:00Z', fresh_until: '2030-01-01T11:00:00Z', evidence_digest: 'd'.repeat(64), requires_revalidation: true}],
    source_set_digest: 'e'.repeat(64), freshness: {checked_at: '2030-01-01T10:00:00Z', expires_at: '2030-01-01T11:00:00Z', requires_revalidation: true},
    unresolved_items: [
      {code: 'availability_revalidation', label: 'Recheck availability.'},
      {code: 'unpriced_component', label: 'Confirm the final component price.'},
    ],
    traveler_disposition: 'awaiting_review', next_actions: ['review', 'request_changes', 'authorize_contact', 'decline'],
    disclosure: {commercial_state: 'non_binding_assisted_proposal', final_quote_required: true, message: 'Final price, availability, and terms are provided only after revalidation in a personal quote.'},
    created_at: '2030-01-01T10:00:00Z', published_at: '2030-01-01T10:00:00Z', expires_at: '2030-01-01T11:00:00Z',
  };
  assert.equal(api.sourceShapeValid(completeProposal.sources[0], {}), true, 'Canonical source fixture must pass the nested response validator.');
  assert.equal(api.exactKeys(completeProposal, Array.from(api.proposalKeys)), true, 'Canonical proposal fixture must contain the exact top-level response keys.');
  assert.equal(api.priceShapeValid(completeProposal.components[0].price), true, 'Canonical price fixture must pass the nested response validator.');
  assert.equal(api.ledgerMatchesComponents(completeProposal.ledger, completeProposal.components), true, 'Canonical ledger fixture must match its components.');
  assert.equal(api.nextActionsValid(completeProposal.status, completeProposal.traveler_disposition, completeProposal.next_actions), true, 'Canonical next actions must match lifecycle state.');
  assert.equal(api.isDateTime(completeProposal.created_at), true, 'Canonical created timestamp must be RFC 3339.');
  assert.equal(api.isDateTime(completeProposal.freshness.checked_at), true, 'Canonical freshness timestamp must be RFC 3339.');
  assert.equal(api.uniqueStrings(completeProposal.route.destinations, 1, 8), true, 'Canonical destinations must be unique strings.');
  assert.equal(api.proposalShapeValid(completeProposal, {case_id: caseUuid}), true, 'Canonical proposal fixture must pass the complete response validator.');
  const unsafePortProposal = structuredClone(completeProposal);
  unsafePortProposal.sources[0].source_type = 'public_supplier_page';
  unsafePortProposal.sources[0].relationship = 'public_reference';
  unsafePortProposal.sources[0].source_reference = '';
  unsafePortProposal.sources[0].source_url = 'https://www.booking.com:444/hotel/fixture';
  assert.equal(api.proposalShapeValid(unsafePortProposal, {case_id: caseUuid}), false, 'Canonical response validation must reject public links on non-default HTTPS ports.');
  assert.throws(() => api.proposalList({}), /invalid proposal list/);
  assert.equal(api.proposalList({proposals: [completeProposal], meta: {count: 1}}, {case_id: caseUuid})[0].version, 2);
  assert.equal(api.mutationProposal({proposal: completeProposal, replayed: false}, {case_id: caseUuid, expected_version: 1}).proposal_id, proposalId);
  assert.throws(() => api.mutationProposal({proposal: {proposal_id: proposalId, version: 2}, replayed: false}), /verifiable proposal/);
  assert.throws(() => api.mutationProposal({proposal: completeProposal}), /verifiable proposal/);
  Object.keys(completeProposal).forEach(key => {
    const incomplete = JSON.parse(JSON.stringify(completeProposal));
    delete incomplete[key];
    assert.throws(() => api.mutationProposal({proposal: incomplete, replayed: false}), /verifiable proposal/);
  });
  const brokenSource = JSON.parse(JSON.stringify(completeProposal));
  delete brokenSource.sources[0].evidence_digest;
  assert.throws(() => api.mutationProposal({proposal: brokenSource, replayed: false}), /verifiable proposal/);
  assert.throws(() => api.mutationProposal({proposal: completeProposal, replayed: false}, {case_id: '44444444-4444-4444-8444-444444444444'}), /verifiable proposal/);
  assert.throws(() => api.proposalList({proposals: [completeProposal], meta: {count: 0}}), /invalid proposal list/);
  assert.throws(() => api.proposalList({proposals: [completeProposal, completeProposal], meta: {count: 2}}), /invalid proposal list/);
  assert.equal(api.mergeProposalMonotonic([{proposal_id: proposalId, version: 5}], {proposal_id: proposalId, version: 2})[0].version, 5);

  const malformedMutations = [
    proposal => { proposal.components[0].price.total_for_party_minor = 0; },
    proposal => { proposal.components[0].price.untrusted = true; },
    proposal => { proposal.components[0].source_ids.push(sourceId); },
    proposal => { proposal.ledger.priced_component_count = 1; },
    proposal => { proposal.sources[0].source_url = 'https://private.example/evidence'; },
    proposal => { proposal.sources[0].fresh_until = proposal.sources[0].observed_at; },
    proposal => { proposal.freshness.checked_at = 'not-a-date'; },
    proposal => { proposal.unresolved_items = proposal.unresolved_items.filter(item => item.code !== 'unpriced_component'); },
    proposal => { proposal.next_actions = ['review', 'decline']; },
    proposal => { proposal.disclosure.message = 'Almost final.'; },
    proposal => { proposal.route.legs[0].mode = 'teleport'; },
    proposal => { proposal.itinerary[0].component_keys = ['missing-component']; },
  ];
  const guardedEditor = {draft: {title: 'Unsaved operator draft'}, pending: {key: 'same-key'}};
  malformedMutations.forEach(mutate => {
    const malformed = JSON.parse(JSON.stringify(completeProposal));
    mutate(malformed);
    assert.throws(() => api.mutationProposal({proposal: malformed, replayed: false}, {case_id: caseUuid, expected_version: 1, expected_status: 'available'}), /verifiable proposal/);
    assert.equal(guardedEditor.draft.title, 'Unsaved operator draft');
    assert.equal(guardedEditor.pending.key, 'same-key');
  });
  assert.throws(() => api.mutationProposal({proposal: completeProposal, replayed: false}, {case_id: caseUuid, expected_version: 1, expected_status: 'withdrawn'}), /verifiable proposal/);
  assert.throws(() => api.mutationProposal({proposal: completeProposal, replayed: false}, {case_id: caseUuid, expected_version: 1, expected_status: 'available', expected_case_revision: 4}), /verifiable proposal/);
  assert.throws(() => api.mutationProposal({proposal: completeProposal, replayed: false}, {case_id: caseUuid, expected_version: 1, expected_status: 'available', expected_request_digest: 'f'.repeat(64)}), /verifiable proposal/);
  const supersededReplay = JSON.parse(JSON.stringify(completeProposal));
  supersededReplay.status = 'superseded';
  supersededReplay.traveler_disposition = 'unavailable';
  supersededReplay.next_actions = [];
  assert.equal(api.mutationProposal({proposal: supersededReplay, replayed: true}, {case_id: caseUuid, expected_version: 1, expected_status: 'available'}).status, 'superseded');
  assert.throws(() => api.mutationProposal({proposal: supersededReplay, replayed: false}, {case_id: caseUuid, expected_version: 1, expected_status: 'available'}), /verifiable proposal/);
  ['TVP-ABCDEFGHI', 'TVP-ABCDEFGHIJ', 'TVP-ABCDEFGHIJK'].forEach(reference => {
    const malformedReference = JSON.parse(JSON.stringify(completeProposal));
    malformedReference.reference = reference;
    assert.throws(() => api.mutationProposal({proposal: malformedReference, replayed: false}, {case_id: caseUuid, expected_version: 1}), /verifiable proposal/);
  });
  const credentialedPublicSource = JSON.parse(JSON.stringify(completeProposal.sources[0]));
  credentialedPublicSource.source_type = 'public_supplier_page';
  credentialedPublicSource.relationship = 'public_reference';
  credentialedPublicSource.source_reference = '';
  credentialedPublicSource.source_url = 'https://user:password@booking.com/property';
  assert.equal(api.sourceShapeValid(credentialedPublicSource, {}), false);
  credentialedPublicSource.source_url = 'https://booking.com/property';
  assert.equal(api.sourceShapeValid(credentialedPublicSource, {}), true);
  const invalidListProposal = JSON.parse(JSON.stringify(completeProposal));
  invalidListProposal.ledger.priced_total_minor = 1;
  const existingList = [{proposal_id: 'existing', version: 99}];
  assert.throws(() => api.proposalList({proposals: [invalidListProposal], meta: {count: 1}}, {case_id: caseUuid}), /invalid proposal list/);
  assert.equal(existingList[0].version, 99);

  const registryConfig = {proposal: {publicSourceProviders: {
    booking: {sourceTypes: ['public_supplier_page'], relationships: ['public_reference']},
    'el-al': {sourceTypes: ['public_supplier_page', 'official_information'], relationships: ['public_reference']},
    'israel-government': {sourceTypes: ['official_information'], relationships: ['public_reference']},
  }}};
  assert.deepEqual(Array.from(api.publicSourceProviders(registryConfig, 'official_information')), ['el-al', 'israel-government']);
  assert.deepEqual(Array.from(api.publicSourceRelationships(registryConfig, 'official_information', 'israel-government')), ['public_reference']);
  const publicPolicySource = {source_type: 'official_information', provider_code: 'booking', relationship: 'affiliate', source_reference: 'OLD:PRIVATE', source_url: 'https://booking.com/path'};
  api.reconcileSourceProviderPolicy(publicPolicySource, registryConfig);
  assert.equal(publicPolicySource.provider_code, '');
  assert.equal(publicPolicySource.relationship, '');
  assert.equal(publicPolicySource.source_reference, '');
  publicPolicySource.provider_code = 'israel-government';
  publicPolicySource.relationship = 'public_reference';
  api.reconcileSourceProviderPolicy(publicPolicySource, registryConfig);
  assert.equal(publicPolicySource.provider_code, 'israel-government');
  assert.equal(publicPolicySource.relationship, 'public_reference');
  publicPolicySource.source_type = 'supplier_written_quote';
  publicPolicySource.provider_code = 'opaque-private-feed';
  publicPolicySource.source_url = 'https://must-not-remain.example/path';
  api.reconcileSourceProviderPolicy(publicPolicySource, registryConfig);
  assert.equal(publicPolicySource.provider_code, 'opaque-private-feed');
  assert.equal(publicPolicySource.relationship, 'operator_attested');
  assert.equal(publicPolicySource.source_url, '');

  const replayState = {config, item, proposals: existingList.slice()};
  context.window.fetch = async () => ({ok: false, status: 403, json: async () => ({code: 'tra_vel_assisted_proposal_assignment_forbidden', message: 'Assignment moved.'})});
  const movedReconciliation = await api.reconcileProposalState(replayState, proposalId);
  assert.equal(movedReconciliation.accessMoved, true);
  assert.equal(movedReconciliation.proposal, null);
  assert.equal(replayState.proposals[0].proposal_id, 'existing');
  context.window.fetch = async () => ({ok: false, status: 403, json: async () => ({code: 'rest_forbidden', message: 'Not allowed.'})});
  await assert.rejects(api.reconcileProposalState(replayState, proposalId), error => error.outcomeUncertain === true && error.code === 'tra_vel_assisted_proposal_reconcile_required');
  context.window.fetch = async () => ({ok: true, status: 200, json: async () => ({proposals: [{proposal_id: proposalId}], meta: {count: 1}})});
  await assert.rejects(api.reconcileProposalState(replayState, proposalId), error => error.outcomeUncertain === true && error.code === 'tra_vel_assisted_proposal_reconcile_required');
  assert.equal(replayState.proposals[0].proposal_id, 'existing');
  const accessMovedState = {
    item,
    submitting: true,
    pending: {key: 'protected'},
    uncertain: {kind: 'publish'},
    conflict: true,
    proposals: existingList.slice(),
    editing: {proposal_id: proposalId},
    draft: {title: 'Already committed'},
    authoringContext: {},
    evidenceAttestation: {token: 'old'},
    dirty: true,
    confirmWithdraw: proposalId,
    withdrawPending: {[proposalId]: {key: 'withdraw-key'}},
  };
  api.completeAccessMovedReplay(accessMovedState, 'publish', proposalId);
  assert.equal(accessMovedState.accessMoved, true);
  assert.equal(accessMovedState.pending, null);
  assert.equal(accessMovedState.uncertain, null);
  assert.equal(accessMovedState.editing, null);
  assert.equal(accessMovedState.proposals.length, 0);
  assert.equal(Object.hasOwn(accessMovedState.withdrawPending, proposalId), false);

  const roundTrip = api.draftFromProposal(
    {summary: {}},
    {unresolved_items: [
      {code: 'availability_revalidation', label: 'Automatic.'},
      {code: 'policy_revalidation', label: 'Policy copy.'},
      {code: 'other', label: 'Dietary confirmation.'},
    ]},
    {proposal: {sourceTtlMinutes: {}}}
  );
  assert.deepEqual(Array.from(roundTrip.extra_gaps, gap => gap.code), ['policy_revalidation', 'other']);
} catch (error) {
  failures.push(`Exact client minor-unit conversion failed: ${error.message}`);
}

if (failures.length) {
  console.error('Tra-Vel assisted proposal admin validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Tra-Vel assisted proposal admin passed (server-owned composition, assignment lock, exact money, safe responsive operator UI).');
