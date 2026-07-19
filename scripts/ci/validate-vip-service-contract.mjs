import {readFileSync} from 'node:fs';
import {join, resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const schemaDir = join(root, 'plugin', 'tra-vel-agent-core', 'schemas');
const taxonomyPath = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'vip', 'class-tra-vel-vip-taxonomy.php');
const policyPath = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'vip', 'class-tra-vel-vip-policy.php');
const statePath = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'vip', 'class-tra-vel-vip-state-machine.php');
const names = [
  'traveler-registration.schema.json',
  'traveler-registration-transition.schema.json',
  'traveler-role-manifest.schema.json',
  'vip-service-case.schema.json',
  'vip-service-case-event.schema.json',
  'vip-capability-grant.schema.json',
  'vip-deadline.schema.json',
  'vip-decision.schema.json',
  'vip-disruption-scenario.schema.json',
];
const failures = [];
const schemas = new Map();
const verticals = ['flight', 'accommodation', 'package', 'transfer', 'activity', 'dining', 'insurance', 'connectivity', 'equipment'];
const gates = ['discover', 'personalize', 'ready_to_quote', 'ready_to_reserve', 'ready_to_fulfill', 'ready_to_travel'];
const highImpact = ['service_reserve', 'service_change', 'service_cancel', 'payment_authorize', 'refund_destination_change', 'identity_change', 'guardian_authority_change', 'sensitive_evidence_disclose', 'recovery_channel_change', 'delegate_manage'];
const lowRisk = ['trip_view_redacted', 'incident_report', 'ordinary_evidence_add', 'safe_contact_update', 'case_progress_view', 'operator_contact_approve', 'decision_view'];
const forbiddenProperty = /^(?:bearer_token|token|secret|password|cvv|cvc|card_number|card_pan|pan|passport|passport_number|identity_number|diagnosis|medical_history|medical_narrative|raw_provider_payload|raw_payment_data|payment_token|activation_code|iccid)$/i;
const sameSet = (left, right) => left.length === right.length && [...left].sort().every((value, index) => value === [...right].sort()[index]);

for (const name of names) {
  try {
    schemas.set(name, JSON.parse(readFileSync(join(schemaDir, name), 'utf8')));
  } catch (error) {
    failures.push(`${name} is missing or invalid JSON: ${error.message}`);
  }
}

const visit = (name, rootSchema, value, pointer = '#') => {
  if (!value || typeof value !== 'object') return;
  if (!Array.isArray(value) && value.type === 'object' && value.additionalProperties !== false) {
    failures.push(`${name} leaves object ${pointer} open to unknown fields.`);
  }
  if (!Array.isArray(value) && value.properties) {
    for (const property of Object.keys(value.properties)) {
      if (forbiddenProperty.test(property)) failures.push(`${name} exposes forbidden raw property ${pointer}/properties/${property}.`);
    }
  }
  if (!Array.isArray(value) && typeof value.$ref === 'string') {
    if (!value.$ref.startsWith('#/')) {
      failures.push(`${name} uses non-local reference ${value.$ref} at ${pointer}.`);
    } else {
      const segments = value.$ref.slice(2).split('/').map(part => part.replaceAll('~1', '/').replaceAll('~0', '~'));
      let target = rootSchema;
      for (const segment of segments) target = target && target[segment];
      if (!target) failures.push(`${name} has unresolved reference ${value.$ref}.`);
    }
  }
  if (Array.isArray(value)) value.forEach((child, index) => visit(name, rootSchema, child, `${pointer}/${index}`));
  else Object.entries(value).forEach(([key, child]) => visit(name, rootSchema, child, `${pointer}/${key}`));
};

const ids = new Set();
for (const [name, schema] of schemas) {
  if (schema.$schema !== 'http://json-schema.org/draft-07/schema#') failures.push(`${name} must use JSON Schema Draft-07.`);
  if (typeof schema.$id !== 'string' || !schema.$id.startsWith('https://tra-vel.co.il/schemas/')) failures.push(`${name} has an invalid canonical ID.`);
  else if (ids.has(schema.$id)) failures.push(`${name} duplicates schema ID ${schema.$id}.`);
  else ids.add(schema.$id);
  if (schema.additionalProperties !== false) failures.push(`${name} must be closed at the root.`);
  if (schema.properties?.contract_version?.const !== '1.0.0') failures.push(`${name} must pin contract version 1.0.0.`);
  const boundary = schema.definitions?.dataBoundary;
  const boundaryProperties = boundary?.properties || {};
  if (boundary?.additionalProperties !== false || Object.keys(boundaryProperties).length !== 5 || !Object.values(boundaryProperties).every(value => value.const === false)) {
    failures.push(`${name} must carry the closed five-part no-raw-data boundary.`);
  }
  visit(name, schema, schema);
}

const registration = schemas.get('traveler-registration.schema.json');
if (registration && !sameSet(registration.properties?.gate?.enum || [], gates)) failures.push('Registration gate vocabulary is not exact.');
const registrationRequirements = registration?.properties?.requirements?.items?.properties?.code?.enum || [];
const registrationTransition = schemas.get('traveler-registration-transition.schema.json');
if (registrationTransition && !sameSet(registrationTransition.definitions?.gate?.enum || [], gates)) failures.push('Registration-transition gate vocabulary is not exact.');
if (registrationTransition && !sameSet(registrationTransition.definitions?.requirement?.enum || [], registrationRequirements)) failures.push('Registration and successor-transition requirement vocabularies diverge.');
if (registrationTransition?.properties?.authorization_effect?.const !== 'registration_only') failures.push('A registration transition must explicitly grant no supplier or payment authority.');
const registrationReasons = registrationTransition?.properties?.reason?.enum || [];
if (registrationTransition && (registrationReasons.length !== 10 || new Set(registrationReasons).size !== 10)) failures.push('Registration-transition reasons must contain ten unique real-world change classes.');

const roleManifest = schemas.get('traveler-role-manifest.schema.json');
const roleScopes = roleManifest?.definitions?.authorityScope?.enum || [];
if (roleManifest && !sameSet(roleScopes, [...lowRisk, ...highImpact])) failures.push('Role-manifest authority vocabulary is not exact.');

const capability = schemas.get('vip-capability-grant.schema.json');
const capabilityScopes = capability?.properties?.allowed_scopes?.items?.enum || [];
if (capability && (!sameSet(capabilityScopes, lowRisk) || capabilityScopes.some(scope => highImpact.includes(scope)))) failures.push('Capability link exposes a high-impact scope or omits a low-risk scope.');
if (capability && !/never represented/i.test(capability.description || '')) failures.push('Capability schema must explicitly state that no bearer value is represented.');

const caseSchema = schemas.get('vip-service-case.schema.json');
const caseVerticals = caseSchema?.definitions?.vertical?.enum || [];
const incidents = caseSchema?.definitions?.incidentType?.enum || [];
if (caseSchema && !sameSet(caseVerticals, verticals)) failures.push('Service-case vertical vocabulary is not exact.');
if (caseSchema && (!incidents.length || new Set(incidents).size !== incidents.length)) failures.push('Service-case incident vocabulary is empty or duplicated.');
for (const vertical of verticals) {
  if (!incidents.some(code => code.startsWith(`${vertical}.`))) failures.push(`Incident taxonomy does not cover ${vertical}.`);
}
if (!incidents.some(code => code.startsWith('cross_trip.'))) failures.push('Incident taxonomy lacks cross-trip disruption types.');

const scenarioSchema = schemas.get('vip-disruption-scenario.schema.json');
const scenarioCodes = scenarioSchema?.properties?.scenario_code?.enum || [];
if (scenarioCodes.length !== 50 || new Set(scenarioCodes).size !== 50) failures.push('Disruption scenario vocabulary must contain exactly 50 unique scenarios.');
if (scenarioSchema?.properties?.scenario_number?.minimum !== 1 || scenarioSchema?.properties?.scenario_number?.maximum !== 50) failures.push('Disruption scenario numbers must be bounded from 1 through 50.');
if (scenarioSchema?.properties?.invariant_codes?.minItems !== 10 || scenarioSchema?.properties?.invariant_codes?.maxItems !== 10) failures.push('Every disruption fixture must carry all ten global invariants.');

let taxonomy = '';
let policy = '';
let state = '';
try {
  taxonomy = readFileSync(taxonomyPath, 'utf8');
  policy = readFileSync(policyPath, 'utf8');
  state = readFileSync(statePath, 'utf8');
} catch (error) {
  failures.push(`VIP PHP foundation is missing: ${error.message}`);
}
for (const value of [...verticals, ...gates, ...roleScopes, ...incidents, ...scenarioCodes]) {
  if (!taxonomy.includes(`'${value}'`)) failures.push(`PHP taxonomy is missing schema value ${value}.`);
}
const scenarioMappings = [...taxonomy.matchAll(/^\s*(\d+)\s*=>\s*'([a-z0-9_]+)'/gm)];
if (scenarioMappings.length !== 50 || new Set(scenarioMappings.map(match => match[1])).size !== 50 || new Set(scenarioMappings.map(match => match[2])).size !== 50) failures.push('PHP taxonomy must map every scenario number exactly once.');

for (const marker of ['traveler_registration', 'registration_successor', 'role_manifest', 'authorize_scope', 'capability_grant', 'deadline', 'decision', 'service_case', 'service_case_event', 'disruption_scenario']) {
  if (!policy.includes(`function ${marker}`)) failures.push(`VIP policy is missing ${marker}().`);
}
for (const marker of ['lifecycle_transition', 'severity_change', 'outcome_transition', 'operation_transition', 'customer_projection']) {
  if (!state.includes(`function ${marker}`)) failures.push(`VIP state machine is missing ${marker}().`);
}
if (!policy.includes('service_case_event_replay_conflict')) failures.push('VIP event policy lacks conflicting-replay rejection.');
if (!policy.includes('authorization_step_up_required') || !policy.includes('decision_step_up_required')) failures.push('VIP policy lacks high-impact step-up gates.');
if (!state.includes("'timeout' => 'uncertain'") || !state.includes("'uncertain'  => array( 'reconcile' => 'reconciled' )")) failures.push('VIP operations do not preserve uncertainty until reconciliation.');

if (failures.length) {
  console.error('VIP service contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`VIP service contracts passed (${schemas.size} closed schemas; ${incidents.length} incident types across ${verticals.length} verticals; 50 deterministic scenarios).`);
