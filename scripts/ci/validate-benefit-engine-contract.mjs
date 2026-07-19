import {readFileSync} from 'node:fs';
import {join, resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const schemaDir = join(root, 'plugin', 'tra-vel-agent-core', 'schemas');
const failures = [];
const expectedSchemas = [
  'benefit-program.schema.json',
  'credential-product.schema.json',
  'campaign-version.schema.json',
  'member-connection.schema.json',
  'balance-snapshot.schema.json',
  'benefit-quote.schema.json',
  'redemption-operation.schema.json',
];
const forbiddenProperty = /(?:password|passcode|security_answer|card_number|card_pan|\bpan\b|cvv|cvc|otp|one_time_code|raw_token|access_token|refresh_token|auth_token|session_cookie|secret)/i;
const numericPromise = /(?:rate|ratio|fee|price|accrual|earn_value|redemption_value|amount)$/i;
const decisionStates = ['eligible_verified', 'likely_customer_asserted', 'unknown_requires_action', 'ineligible_verified'];
const consentScopes = ['read_balance', 'refresh_balance', 'redeem', 'disconnect'];
const schemas = new Map();

const sameSet = (left, right) => left.length === right.length && [...left].sort().every((value, index) => value === [...right].sort()[index]);

for (const name of expectedSchemas) {
  try {
    schemas.set(name, JSON.parse(readFileSync(join(schemaDir, name), 'utf8')));
  } catch (error) {
    failures.push(`${name} is missing or invalid JSON: ${error.message}`);
  }
}

const ids = new Set();
const visit = (schemaName, rootSchema, value, pointer = '#') => {
  if (!value || typeof value !== 'object') return;

  if (!Array.isArray(value) && value.type === 'object') {
    if (value.additionalProperties !== false) failures.push(`${schemaName} leaves object ${pointer} open to unknown fields.`);
    const propertyNames = Object.keys(value.properties || {});
    const required = value.required || [];
    if (!sameSet(propertyNames, required)) failures.push(`${schemaName} does not require every property in closed object ${pointer}.`);
  }

  if (!Array.isArray(value) && value.properties) {
    for (const [key, property] of Object.entries(value.properties)) {
      if (forbiddenProperty.test(key)) failures.push(`${schemaName} exposes prohibited credential material at ${pointer}/properties/${key}.`);
      if (property?.type === 'number' || (Array.isArray(property?.type) && property.type.includes('number'))) failures.push(`${schemaName} permits floating-point number ${pointer}/properties/${key}.`);
      if (/(?:_minor|_integer|campaign_version|^version$|supersedes_version)$/.test(key)) {
        const types = Array.isArray(property?.type) ? property.type : [property?.type];
        if (!types.includes('integer')) failures.push(`${schemaName} does not encode ${pointer}/properties/${key} as an integer.`);
      }
    }
  }

  if (!Array.isArray(value) && typeof value.$ref === 'string') {
    if (!value.$ref.startsWith('#/')) {
      failures.push(`${schemaName} uses a non-local reference at ${pointer}.`);
    } else {
      const segments = value.$ref.slice(2).split('/').map(segment => segment.replaceAll('~1', '/').replaceAll('~0', '~'));
      let target = rootSchema;
      for (const segment of segments) target = target && target[segment];
      if (!target) failures.push(`${schemaName} has unresolved reference ${value.$ref}.`);
    }
  }

  if (Array.isArray(value)) value.forEach((item, index) => visit(schemaName, rootSchema, item, `${pointer}/${index}`));
  else Object.entries(value).forEach(([key, item]) => visit(schemaName, rootSchema, item, `${pointer}/${key}`));
};

for (const [name, schema] of schemas) {
  if (schema.$schema !== 'http://json-schema.org/draft-07/schema#') failures.push(`${name} must declare JSON Schema Draft-07.`);
  if (typeof schema.$id !== 'string' || !schema.$id.startsWith('https://tra-vel.co.il/schemas/')) failures.push(`${name} has an invalid canonical schema ID.`);
  else if (ids.has(schema.$id)) failures.push(`${name} duplicates canonical schema ID ${schema.$id}.`);
  else ids.add(schema.$id);
  if (schema.type !== 'object' || schema.additionalProperties !== false) failures.push(`${name} must have a closed object root.`);
  if (schema.properties?.contract_version?.const !== '1.0.0') failures.push(`${name} must pin contract version 1.0.0.`);
  visit(name, schema, schema);
}

for (const name of ['benefit-program.schema.json', 'credential-product.schema.json', 'campaign-version.schema.json']) {
  const schema = schemas.get(name);
  if (!schema) continue;
  for (const property of Object.keys(schema.properties || {})) {
    if (numericPromise.test(property)) failures.push(`${name} hardcodes an unstable numeric commercial promise field ${property}.`);
  }
  if (!schema.properties?.source || !schema.properties?.integration_state) failures.push(`${name} must bind identity to dated evidence and an integration state.`);
}

const member = schemas.get('member-connection.schema.json');
if (member) {
  const scopes = member.properties?.consent?.properties?.scopes?.items?.enum || [];
  if (!sameSet(scopes, consentScopes)) failures.push('Member connection consent scopes do not match the exact least-privilege vocabulary.');
  if (!scopes.includes('read_balance') || !scopes.includes('redeem') || scopes.indexOf('read_balance') === scopes.indexOf('redeem')) failures.push('Read-balance and redeem must remain separate explicit consent scopes.');
  if (member.properties?.commercial_truth?.properties?.provider_connection_live?.const !== false || member.properties?.commercial_truth?.properties?.redemption_enabled?.const !== false) failures.push('Member connection must preserve the non-live foundation boundary.');
}

const campaign = schemas.get('campaign-version.schema.json');
if (campaign) {
  const windows = campaign.properties?.windows?.properties || {};
  if (!sameSet(Object.keys(windows), ['effective', 'enrollment', 'booking', 'travel'])) failures.push('Campaign version must define exact effective, enrollment, booking, and travel windows.');
  if (!campaign.properties?.source?.$ref || !campaign.properties?.ruleset_digest || !campaign.properties?.supersedes_version) failures.push('Campaign version must retain source, ruleset digest, and immutable lineage.');
  if (campaign.properties?.commercial_truth?.properties?.provider_quote_available?.const !== false || campaign.properties?.commercial_truth?.properties?.checkout_application_available?.const !== false) failures.push('Campaign version must not claim provider quoting or checkout application.');
}

const quote = schemas.get('benefit-quote.schema.json');
if (quote) {
  const states = quote.properties?.decision_state?.enum || [];
  if (!sameSet(states, decisionStates)) failures.push('Benefit quote decision vocabulary is incomplete or open.');
  const truth = quote.properties?.commercial_truth?.properties || {};
  if (truth.planning_only?.const !== true || truth.may_change_payable_total?.const !== false || truth.redemption_available?.const !== false) failures.push('Benefit quote must remain planning-only and unable to alter checkout.');
}

const balance = schemas.get('balance-snapshot.schema.json');
if (balance?.properties?.planning_only?.const !== true) failures.push('Balance snapshot must be planning-only.');

const redemption = schemas.get('redemption-operation.schema.json');
if (redemption) {
  const truth = redemption.properties?.commercial_truth?.properties || {};
  if (truth.simulated?.const !== true || truth.provider_submission?.const !== false || truth.real_debit?.const !== false) failures.push('Redemption operation must be simulated, unsubmitted, and unable to debit value.');
}

if (failures.length) {
  console.error('Benefit engine contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Benefit engine contracts passed (${schemas.size} closed Draft-07 schemas; least-privilege consent; integer-only values; non-live truth boundary).`);
