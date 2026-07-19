import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const pluginRoot = join(repoRoot, 'plugin', 'tra-vel-agent-core');
const schemaRoot = join(pluginRoot, 'schemas');
const failures = [];

const fail = message => failures.push(message);
const read = path => readFileSync(path, 'utf8');
const readJson = filename => JSON.parse(read(join(schemaRoot, filename)));
const sameMembers = (actual, expected) => actual.length === expected.length && expected.every(value => actual.includes(value));
const requireMarker = (source, marker, message) => { if (!source.includes(marker)) fail(message); };

const proposal = readJson('assisted-proposal.schema.json');
const source = readJson('assisted-proposal-source.schema.json');
const travelerSource = readJson('assisted-proposal-traveler-source.schema.json');
const policy = read(join(pluginRoot, 'includes', 'class-tra-vel-assisted-proposal-policy.php'));
const runtime = read(join(repoRoot, 'scripts', 'ci', 'validate-assisted-proposal-runtime.php'));

const assertClosedObjects = (node, path = '$', seen = new Set()) => {
  if (!node || typeof node !== 'object' || seen.has(node)) return;
  seen.add(node);
  if (node.type === 'object' && node.additionalProperties !== false) {
    fail(`${path} must reject undeclared properties.`);
  }
  if (Array.isArray(node)) {
    node.forEach((child, index) => assertClosedObjects(child, `${path}[${index}]`, seen));
    return;
  }
  for (const [key, child] of Object.entries(node)) assertClosedObjects(child, `${path}.${key}`, seen);
};

const assertRequired = (schema, keys, label) => {
  const required = schema.required || [];
  for (const key of keys) {
    if (!required.includes(key)) fail(`${label} must require ${key}.`);
    if (!(key in (schema.properties || {}))) fail(`${label} must define ${key}.`);
  }
};

for (const [label, schema] of [['AssistedProposal', proposal], ['AssistedProposalSource', source], ['TravelerAssistedProposalSource', travelerSource]]) {
  if (schema.$schema !== 'http://json-schema.org/draft-07/schema#') fail(`${label} must use the repository Draft 7 convention.`);
  if (schema.type !== 'object' || schema.additionalProperties !== false) fail(`${label} must be a closed object schema.`);
  assertClosedObjects(schema, label);
}

assertRequired(proposal, [
  'contract_version', 'proposal_id', 'case_id', 'reference', 'status', 'version', 'revision',
  'published_revision', 'position', 'addresses', 'title', 'summary', 'why_it_fits', 'trade_offs',
  'route', 'itinerary', 'components', 'ledger', 'sources', 'source_set_digest', 'freshness',
  'unresolved_items', 'traveler_disposition', 'next_actions', 'disclosure', 'created_at',
  'published_at', 'expires_at',
], 'AssistedProposal');

assertRequired(source, [
  'contract_version', 'source_id', 'provider_code', 'source_type', 'relationship', 'public_label',
  'supplier_name', 'seller_name', 'source_reference', 'source_url', 'observed_at', 'fresh_until',
  'evidence_digest', 'requires_revalidation',
], 'AssistedProposalSource');

const travelerSourceKeys = [
  'contract_version', 'source_id', 'source_type', 'public_label', 'supplier_name',
  'seller_name', 'source_url', 'observed_at', 'fresh_until', 'requires_revalidation',
];
assertRequired(travelerSource, travelerSourceKeys, 'TravelerAssistedProposalSource');
if (!sameMembers(travelerSource.required || [], travelerSourceKeys)
  || !sameMembers(Object.keys(travelerSource.properties || {}), travelerSourceKeys)) {
  fail('TravelerAssistedProposalSource must expose exactly its minimized disclosure fields.');
}
for (const privateField of ['provider_code', 'relationship', 'source_reference', 'evidence_digest']) {
  if (privateField in (travelerSource.properties || {})) fail(`Traveler source must omit private field ${privateField}.`);
}

if (proposal.properties?.contract_version?.const !== '1.0.0' || source.properties?.contract_version?.const !== '1.0.0' || travelerSource.properties?.contract_version?.const !== '1.0.0') {
  fail('Proposal, canonical source, and traveler source contracts must identify version 1.0.0.');
}
if (!String(proposal.$id || '').endsWith('assisted-proposal-1.0.0.json')) fail('AssistedProposal schema identity must be versioned.');
if (!String(source.$id || '').endsWith('assisted-proposal-source-1.0.0.json')) fail('AssistedProposalSource schema identity must be versioned.');
if (!String(travelerSource.$id || '').endsWith('assisted-proposal-traveler-source-1.0.0.json')) fail('TravelerAssistedProposalSource schema identity must be versioned.');

const statuses = ['draft', 'available', 'withdrawn', 'expired', 'superseded'];
const positions = ['best_value', 'lowest_friction', 'most_flexible', 'most_memorable', 'custom'];
const dispositions = ['unavailable', 'awaiting_review', 'reviewed', 'changes_requested', 'contact_authorized', 'declined'];
const sourceTypes = ['connected_api', 'supplier_portal', 'supplier_written_quote', 'public_supplier_page', 'official_information'];
const relationships = ['operator_attested', 'public_reference'];

if (!sameMembers(proposal.properties?.status?.enum || [], statuses)) fail('AssistedProposal lifecycle states changed.');
if (!sameMembers(proposal.properties?.position?.enum || [], positions)) fail('AssistedProposal shortlist positions changed.');
if (!sameMembers(proposal.properties?.traveler_disposition?.enum || [], dispositions)) fail('AssistedProposal traveler dispositions changed.');
if (!sameMembers(source.properties?.source_type?.enum || [], sourceTypes)) fail('AssistedProposalSource evidence types changed.');
if (!sameMembers(source.properties?.relationship?.enum || [], relationships)) fail('AssistedProposalSource relationships changed.');
if (!sameMembers(travelerSource.properties?.source_type?.enum || [], sourceTypes)) fail('Traveler source evidence types changed.');
if (source.allOf?.[0]?.then?.properties?.relationship?.const !== 'public_reference') fail('Public evidence must use the neutral registered public-reference relationship.');
if (source.allOf?.[0]?.else?.properties?.relationship?.const !== 'operator_attested') fail('Private evidence must use the neutral operator-attested relationship.');
if (source.allOf?.[0]?.else?.properties?.source_url?.type !== 'null') fail('Private canonical evidence must store no provider URL.');
if (travelerSource.allOf?.[0]?.else?.properties?.source_url?.type !== 'null') fail('Private evidence types must expose a null URL in the traveler contract.');

const conditionals = Array.isArray(proposal.allOf) ? proposal.allOf : [];
const awaitingActions = conditionals.find(rule => rule?.if?.properties?.status?.const === 'available' && rule?.if?.properties?.traveler_disposition?.const === 'awaiting_review')?.then?.properties?.next_actions;
const reviewedActions = conditionals.find(rule => rule?.if?.properties?.status?.const === 'available' && rule?.if?.properties?.traveler_disposition?.const === 'reviewed')?.then?.properties?.next_actions;
const terminalActions = conditionals.find(rule => rule?.if?.properties?.status?.const === 'available' && Array.isArray(rule?.if?.properties?.traveler_disposition?.enum))?.then?.properties?.next_actions;
if (awaitingActions?.minItems !== 4 || awaitingActions?.maxItems !== 4) fail('Awaiting-review proposals must expose exactly four safe traveler actions.');
if (reviewedActions?.minItems !== 3 || reviewedActions?.maxItems !== 3 || !sameMembers(reviewedActions?.items?.enum || [], ['request_changes', 'authorize_contact', 'decline'])) fail('Reviewed proposals must expose only the three remaining safe actions.');
if (terminalActions?.maxItems !== 0) fail('Terminal traveler dispositions must expose no misleading repeat action.');

if (proposal.properties?.sources?.items?.$ref !== 'assisted-proposal-source.schema.json') fail('Proposal source rows must use the dedicated closed source contract.');
if (proposal.properties?.components?.minItems !== 1 || proposal.properties?.components?.maxItems !== 16) fail('Proposal components must remain bounded to 1..16.');
if (proposal.properties?.sources?.minItems !== 1 || proposal.properties?.sources?.maxItems !== 32) fail('Proposal evidence must remain bounded to 1..32 sources.');
if (source.properties?.requires_revalidation?.const !== true || proposal.definitions?.freshness?.properties?.requires_revalidation?.const !== true) {
  fail('Source and proposal freshness must require revalidation.');
}

const price = proposal.definitions?.price;
const ledger = proposal.definitions?.ledger;
if (!price || price.additionalProperties !== false || !ledger || ledger.additionalProperties !== false) fail('Price and ledger contracts must be closed.');
if (price.properties?.total_for_party_minor?.type?.[0] !== 'integer' || price.properties?.total_for_party_minor?.maximum !== 1000000000000) {
  fail('Component totals must be bounded integer minor units.');
}
assertRequired(ledger || {}, [
  'contract_version', 'currency', 'priced_total_minor', 'priced_component_count',
  'unpriced_component_keys', 'complete_pricing', 'calculation_digest',
], 'AssistedProposal.ledger');
if (ledger?.properties?.calculation_digest?.pattern !== '^[a-f0-9]{64}$') fail('Server ledger requires a SHA-256 calculation digest.');
if (!sameMembers(ledger?.properties?.currency?.enum || [], ['ILS', 'USD', 'EUR', null])) fail('Proposal ledger must allow one supported currency or no priced currency.');
for (const field of ['cancellation', 'changes', 'baggage_or_inclusions']) {
  if (proposal.definitions?.conditions?.properties?.[field]?.minLength !== 1) fail(`Component condition ${field} must be non-empty.`);
}

const disclosure = proposal.definitions?.disclosure;
const expectedDisclosure = 'Final price, availability, and terms are provided only after revalidation in a personal quote.';
if (disclosure?.properties?.commercial_state?.const !== 'non_binding_assisted_proposal'
  || disclosure?.properties?.final_quote_required?.const !== true
  || disclosure?.properties?.message?.const !== expectedDisclosure) {
  fail('AssistedProposal must carry the exact non-binding final-quote disclosure.');
}

const forbiddenKeys = new Set([
  'accepted', 'acceptance_status', 'reservation', 'reservation_id', 'reserved', 'payment',
  'payment_status', 'paid', 'checkout', 'order', 'order_id', 'booking', 'booking_id', 'booked',
  'confirmation', 'confirmation_number', 'ticket', 'ticket_number', 'issued', 'policy_number',
  'purchase', 'purchased', 'savings', 'saving_amount', 'discount_amount', 'comparator_price',
]);
const inspectSchemaKeys = (node, path = '$') => {
  if (!node || typeof node !== 'object') return;
  if (node.properties && typeof node.properties === 'object') {
    for (const key of Object.keys(node.properties)) {
      if (forbiddenKeys.has(key.toLowerCase())) fail(`${path} exposes forbidden transactional or comparative field ${key}.`);
    }
  }
  if (Array.isArray(node)) node.forEach((child, index) => inspectSchemaKeys(child, `${path}[${index}]`));
  else for (const [key, child] of Object.entries(node)) inspectSchemaKeys(child, `${path}.${key}`);
};
inspectSchemaKeys(proposal, 'AssistedProposal');
inspectSchemaKeys(source, 'AssistedProposalSource');
inspectSchemaKeys(travelerSource, 'TravelerAssistedProposalSource');

const forbiddenOutcomes = new Set(['accepted', 'reserved', 'paid', 'booked', 'confirmed', 'issued', 'purchased']);
const inspectEnums = (node, path = '$') => {
  if (!node || typeof node !== 'object') return;
  if (Array.isArray(node.enum)) {
    for (const value of node.enum) {
      if (typeof value === 'string' && forbiddenOutcomes.has(value.toLowerCase())) fail(`${path} exposes forbidden outcome value ${value}.`);
    }
  }
  if (Array.isArray(node)) node.forEach((child, index) => inspectEnums(child, `${path}[${index}]`));
  else for (const [key, child] of Object.entries(node)) inspectEnums(child, `${path}.${key}`);
};
inspectEnums(proposal, 'AssistedProposal');
inspectEnums(source, 'AssistedProposalSource');
inspectEnums(travelerSource, 'TravelerAssistedProposalSource');

for (const marker of [
  'class Tra_Vel_Assisted_Proposal_Policy',
  "const CONTRACT_VERSION        = '1.0.0'",
  "const SOURCE_CONTRACT_VERSION = '1.0.0'",
  'traveler_actions_for',
  'traveler_action_target',
  'const MIN_PUBLICATION_TTL',
  'const MAX_PUBLICATION_TTL',
  'const MAX_AMOUNT_MINOR',
  'const FINAL_QUOTE_DISCLOSURE',
  'public static function validate_publication',
  'public static function validate_source',
  'public static function compute_ledger',
  'public static function source_set_digest',
  'public static function effective_status',
  'public static function can_append_revision',
  'public static function reject_forbidden_fields',
  "'available' !== ( $proposal['status']",
  "'case_active'",
  "'case_revision'",
  "'request_digest'",
  "'source_set_digest'",
  "'total_for_party_minor'",
  "'calculation_digest'",
  "'non_binding_assisted_proposal'",
  "'final_quote_required'",
  "'awaiting_review' !== ( $proposal['traveler_disposition']",
  'expected_next_actions',
  "'review', 'request_changes', 'authorize_contact', 'decline'",
  '$proposal_version < 1',
  '$proposal_version < $revision',
  'self::is_uuid( $proposal[\'proposal_id\']',
  'self::is_uuid( $proposal[\'case_id\']',
  '$published_at < $checked_at',
  '$published_at > $expires_at',
  'tra_vel_assisted_proposal_itinerary_component_missing',
  'required_unresolved',
  "array( 'availability_revalidation' )",
  'tra_vel_assisted_proposal_gap_undisclosed',
]) requireMarker(policy, marker, `Assisted proposal policy is missing ${marker}.`);

for (const value of [...statuses, ...positions, ...dispositions, ...sourceTypes, ...relationships]) {
  requireMarker(policy, `'${value}'`, `PHP publication policy must own allowlisted value ${value}.`);
}
for (const field of forbiddenKeys) {
  requireMarker(policy, `'${field}'`, `PHP publication policy must reject forbidden field ${field}.`);
}

const publicMethods = [...policy.matchAll(/public static function\s+([a-zA-Z0-9_]+)/g)].map(match => match[1]);
for (const method of publicMethods) {
  if (/pay|book|reserve|issue|checkout|purchase/i.test(method)) fail(`Policy exposes forbidden consequential method ${method}.`);
}
if (!/return in_array\( \$status, array\( 'draft', 'available' \), true \);/.test(policy)) {
  fail('Only draft or available proposal heads may append immutable revisions.');
}
if (!policy.includes("'official_information' !== $source_map[ $source_id ]['source_type']")) {
  fail('Priced components must require evidence beyond general information.');
}
if (!policy.includes("$expires_at > $earliest_expiry")) fail('Proposal expiry must never exceed its earliest evidence deadline.');
if (!policy.includes('self::canonical_digest( $computed_ledger )')) fail('Publication must compare the submitted ledger with the server-computed ledger.');

for (const marker of [
  'validate_publication',
  'tra_vel_assisted_proposal_publication_order_invalid',
  'Future-dated evidence must fail closed.',
  'Stale evidence must fail closed.',
  'tra_vel_assisted_proposal_currency_mixed',
  'An explicitly unpriced sourced component must remain representable.',
  'tra_vel_assisted_proposal_price_unsourced',
  "$forbidden['booking_id'] = 'forbidden';",
  'tra_vel_assisted_proposal_source_set_changed',
  'tra_vel_assisted_proposal_case_revision_changed',
  'tra_vel_assisted_proposal_request_changed',
  'tra_vel_assisted_proposal_disposition_invalid',
  'tra_vel_assisted_proposal_next_actions_invalid',
  'tra_vel_assisted_proposal_itinerary_component_missing',
  'tra_vel_assisted_proposal_gap_undisclosed',
  'effective_status',
  'can_append_revision',
]) requireMarker(runtime, marker, `Assisted proposal runtime fixtures are missing ${marker}.`);

if (failures.length) {
  console.error('Tra-Vel assisted proposal contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Tra-Vel assisted proposal contract validation passed (closed immutable revisions, sourced freshness, server ledger, non-transactional truth).');
