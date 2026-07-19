import { existsSync, readFileSync } from 'node:fs';
import { basename, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const schemaDir = resolve(root, 'plugin/tra-vel-agent-core/schemas');
const fixturePath = resolve(root, 'plugin/tra-vel-agent-core/assets/fixtures/commerce-sandbox/supplier-operations-stress-matrix.json');
const taxonomyPath = resolve(root, 'plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-supplier-operations-taxonomy.php');
const policyPath = resolve(root, 'plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-supplier-operations-policy.php');
const statePath = resolve(root, 'plugin/tra-vel-agent-core/includes/commerce/class-tra-vel-supplier-operations-state-machine.php');
const runtimePath = resolve(root, 'scripts/ci/validate-supplier-operations-runtime.php');

const schemaNames = [
  'supplier-operations-profile.schema.json',
  'supplier-inventory-revision.schema.json',
  'supplier-operations-stress-scenario.schema.json',
];
const verticals = ['flight', 'accommodation', 'package', 'transfer', 'activity', 'dining', 'insurance', 'connectivity', 'equipment'];
const capabilities = ['search', 'revalidate', 'reserve', 'confirm', 'fulfill', 'change', 'cancel', 'refund', 'payment_authorize', 'payment_capture', 'payment_void', 'payment_refund', 'webhook', 'reconcile', 'report_conversion', 'settlement_reconcile'];
const operationLanes = ['search', 'reservation', 'confirmation', 'change', 'cancel', 'refund', 'fulfillment', 'webhook', 'reconciliation', 'settlement'];
const readinessGates = ['commercial', 'credentials', 'endpoints', 'certification', 'operations', 'licensing', 'data_governance', 'settlement', 'source_freshness', 'resilience'];
const expectedInjections = ['supplier_outage', 'partial_confirmation', 'duplicate_late_webhook', 'credential_rotation', 'terms_revision_mid_checkout', 'refund_mismatch', 'settlement_dispute', 'provider_acquisition_migration'];

const failures = [];
const sameSet = (left, right) => left.length === right.length && left.every(value => right.includes(value));
const exactKeys = (value, expected) => value && typeof value === 'object' && !Array.isArray(value) && sameSet(Object.keys(value), expected);

const schemas = new Map();
for (const name of schemaNames) {
  const path = resolve(schemaDir, name);
  if (!existsSync(path)) {
    failures.push(`${name} is missing.`);
    continue;
  }
  try {
    schemas.set(name, JSON.parse(readFileSync(path, 'utf8')));
  } catch (error) {
    failures.push(`${name} is invalid JSON: ${error.message}`);
  }
}

const visitSchema = (name, rootSchema, value, pointer = '#') => {
  if (!value || typeof value !== 'object') return;
  if ((value.type === 'object' || value.properties) && value.additionalProperties !== false) {
    failures.push(`${name} has an open object at ${pointer}.`);
  }
  if (value.properties) {
    const propertyKeys = Object.keys(value.properties);
    if (!sameSet(value.required || [], propertyKeys)) failures.push(`${name} object ${pointer} must require exactly its declared properties.`);
    for (const key of propertyKeys) {
      if (/(^|_)(api_?key|secret|password|bearer|access_?token|refresh_?token|private_?key|cvv|cvc|card_?number|passport|medical|email|phone|traveler_?name|full_?name)($|_)/i.test(key)) {
        failures.push(`${name} exposes forbidden raw data field ${key} at ${pointer}.`);
      }
    }
  }
  if (typeof value.$ref === 'string') {
    if (!value.$ref.startsWith('#/definitions/')) failures.push(`${name} has a non-local reference ${value.$ref}.`);
    else {
      const segments = value.$ref.slice(2).split('/').map(segment => segment.replaceAll('~1', '/').replaceAll('~0', '~'));
      let target = rootSchema;
      for (const segment of segments) target = target && target[segment];
      if (!target) failures.push(`${name} has unresolved reference ${value.$ref}.`);
    }
  }
  if (Array.isArray(value)) value.forEach((item, index) => visitSchema(name, rootSchema, item, `${pointer}/${index}`));
  else Object.entries(value).forEach(([key, item]) => visitSchema(name, rootSchema, item, `${pointer}/${key}`));
};

const ids = new Set();
for (const [name, schema] of schemas) {
  if (schema.$schema !== 'http://json-schema.org/draft-07/schema#') failures.push(`${name} must declare JSON Schema Draft-07.`);
  if (typeof schema.$id !== 'string' || !schema.$id.startsWith('https://tra-vel.co.il/schemas/')) failures.push(`${name} has an invalid canonical schema ID.`);
  else if (ids.has(schema.$id)) failures.push(`${name} duplicates schema ID ${schema.$id}.`);
  else ids.add(schema.$id);
  if (schema.additionalProperties !== false) failures.push(`${name} must be closed at its root.`);
  if (schema.properties?.contract_version?.const !== '1.0.0') failures.push(`${name} must pin contract version 1.0.0.`);
  visitSchema(name, schema, schema);
}

const profile = schemas.get('supplier-operations-profile.schema.json');
if (profile) {
  const profileKeys = ['contract_version', 'supplier_id', 'revision_id', 'revision_number', 'previous_revision_digest', 'created_at', 'effective_at', 'environment', 'lifecycle_status', 'verticals', 'capability_claims', 'relationship', 'credentials', 'endpoints', 'operation_support', 'escalation', 'licensing', 'data_governance', 'attribution', 'settlement', 'source_controls', 'health', 'kill_switch', 'readiness', 'commercial_truth', 'revision_control'];
  if (!sameSet(profile.required || [], profileKeys) || !sameSet(Object.keys(profile.properties || {}), profileKeys)) failures.push('Supplier profile root shape changed without contract review.');
  if (!sameSet(profile.definitions?.vertical?.enum || [], verticals)) failures.push('Supplier profile must cover all nine canonical verticals.');
  if (!sameSet(profile.definitions?.capability?.enum || [], capabilities)) failures.push('Supplier profile capabilities must match the 16 Commerce Core capabilities.');
  const support = profile.definitions?.operationSupport;
  if (!support || !sameSet(support.required || [], operationLanes) || !sameSet(Object.keys(support.properties || {}), operationLanes)) failures.push('Supplier operation support must cover every servicing and escalation lane.');
  const gates = profile.definitions?.readinessGates;
  if (!gates || !sameSet(gates.required || [], readinessGates) || !sameSet(Object.keys(gates.properties || {}), readinessGates)) failures.push('Supplier readiness must preserve all ten independent gates.');
  const settlementFields = ['model', 'currency', 'gross_basis', 'commission_bps', 'markup_authority', 'invoice_party', 'customer_funds_owner', 'supplier_payable_method', 'payout_route_ref', 'payout_lag_days', 'reconciliation_frequency', 'dispute_sla_hours', 'chargeback_owner', 'tax_owner', 'evidence_digest'];
  if (!sameSet(profile.definitions?.settlement?.required || [], settlementFields)) failures.push('Supplier settlement must disambiguate commission, net-rate, affiliate, owned, funds, invoice, tax, and chargeback ownership.');
  const sourceFields = ['catalog_mode', 'product_revision_digest', 'rate_revision_digest', 'availability_revision_digest', 'terms_revision_digest', 'blackout_revision_digest', 'last_verified_at', 'terms_valid_until', 'max_cache_age_seconds', 'revalidation_required', 'source_evidence_digest'];
  if (!sameSet(profile.definitions?.sourceControls?.required || [], sourceFields)) failures.push('Supplier profile must bind product, rate, availability, terms, blackout, freshness, and revalidation evidence.');
  if (profile.definitions?.dataGovernance?.properties?.minimum_necessary_enforced?.const !== true || profile.definitions?.dataGovernance?.properties?.log_redaction_enforced?.const !== true) failures.push('Supplier data governance must enforce least disclosure and log redaction.');
  if (!sameSet(profile.definitions?.commercialTruth?.required || [], ['simulated', 'real_booking', 'real_charge'])) failures.push('Supplier profile must preserve the closed commercial truth boundary.');
}

const inventory = schemas.get('supplier-inventory-revision.schema.json');
if (inventory) {
  if (!sameSet(inventory.definitions?.vertical?.enum || [], verticals)) failures.push('Inventory revision must cover all nine canonical verticals.');
  const artifactFields = ['product_ref', 'product_revision', 'product_digest', 'rate_revision', 'rate_digest', 'availability_revision', 'availability_digest', 'terms_revision', 'terms_digest', 'terms_effective_at', 'terms_valid_until', 'blackout_revision', 'blackout_digest'];
  if (!sameSet(inventory.definitions?.artifact?.required || [], artifactFields)) failures.push('Inventory artifacts must bind product, rate, availability, terms, and blackout revisions and digests.');
  if (inventory.definitions?.revalidation?.properties?.required?.const !== true) failures.push('Inventory revisions must require revalidation.');
  if (inventory.definitions?.dataBoundary?.properties?.contains_raw_secrets?.const !== false || inventory.definitions?.dataBoundary?.properties?.contains_raw_pii?.const !== false || inventory.definitions?.dataBoundary?.properties?.restricted_payload_refs_only?.const !== true) failures.push('Inventory data boundary must exclude raw secrets and PII.');
}

const stressSchema = schemas.get('supplier-operations-stress-scenario.schema.json');
if (stressSchema) {
  if (stressSchema.properties?.environment?.const !== 'sandbox') failures.push('Supplier stress scenarios must remain sandbox-only.');
  if (!sameSet(stressSchema.definitions?.scenario?.properties?.injection?.enum || [], expectedInjections)) failures.push('Supplier stress schema must enumerate all eight required failure families.');
}

let fixture;
try {
  fixture = JSON.parse(readFileSync(fixturePath, 'utf8'));
} catch (error) {
  failures.push(`Supplier stress fixture is invalid JSON: ${error.message}`);
}

if (fixture) {
  const fixtureKeys = ['contract_version', 'environment', 'fixture_id', 'clock_started_at', 'scenarios'];
  const scenarioKeys = ['scenario_id', 'seed', 'injected_at', 'injection', 'provider_script', 'initial_states', 'expected_states', 'expected_actions', 'invariants', 'customer_projection'];
  const stateKeys = ['onboarding', 'health', 'operation', 'settlement', 'revision'];
  const stepKeys = ['sequence', 'at_offset_seconds', 'event', 'outcome'];
  if (!exactKeys(fixture, fixtureKeys) || fixture.contract_version !== '1.0.0' || fixture.environment !== 'sandbox' || !Array.isArray(fixture.scenarios) || fixture.scenarios.length !== 8) failures.push('Supplier stress fixture envelope must be closed, deterministic, sandbox-only, and contain eight scenarios.');
  const seenIds = new Set();
  const seenSeeds = new Set();
  const seenInjections = new Set();
  for (const scenario of fixture.scenarios || []) {
    if (!exactKeys(scenario, scenarioKeys)) failures.push(`Stress scenario ${scenario?.scenario_id || '(unknown)'} has an open or incomplete shape.`);
    if (seenIds.has(scenario.scenario_id)) failures.push(`Stress scenario ID ${scenario.scenario_id} is duplicated.`);
    if (seenSeeds.has(scenario.seed)) failures.push(`Stress seed ${scenario.seed} is duplicated.`);
    seenIds.add(scenario.scenario_id);
    seenSeeds.add(scenario.seed);
    seenInjections.add(scenario.injection);
    if (!exactKeys(scenario.initial_states, stateKeys) || !exactKeys(scenario.expected_states, stateKeys)) failures.push(`${scenario.scenario_id} must preserve all independent state axes.`);
    let lastOffset = -1;
    for (const [index, step] of (scenario.provider_script || []).entries()) {
      if (!exactKeys(step, stepKeys) || step.sequence !== index + 1 || !Number.isInteger(step.at_offset_seconds) || step.at_offset_seconds < lastOffset) failures.push(`${scenario.scenario_id} provider script is not deterministic and ordered.`);
      lastOffset = step.at_offset_seconds;
    }
    if (!Array.isArray(scenario.expected_actions) || scenario.expected_actions.length < 2 || new Set(scenario.expected_actions).size !== scenario.expected_actions.length) failures.push(`${scenario.scenario_id} needs unique recovery actions.`);
    if (!Array.isArray(scenario.invariants) || scenario.invariants.length < 2 || new Set(scenario.invariants).size !== scenario.invariants.length) failures.push(`${scenario.scenario_id} needs unique safety invariants.`);
  }
  if (!sameSet([...seenInjections], expectedInjections)) failures.push('Supplier stress fixture does not cover the exact required failure families.');

  const byInjection = Object.fromEntries((fixture.scenarios || []).map(item => [item.injection, item]));
  const requireValues = (injection, field, values) => {
    const actual = byInjection[injection]?.[field] || [];
    for (const value of values) if (!actual.includes(value)) failures.push(`${injection} must include ${field} value ${value}.`);
  };
  requireValues('supplier_outage', 'expected_actions', ['open_circuit', 'block_mutation', 'reconcile_authoritative_state', 'route_after_hours']);
  requireValues('partial_confirmation', 'invariants', ['partial_success_visible', 'uncertain_not_failed', 'no_duplicate_side_effect']);
  requireValues('duplicate_late_webhook', 'expected_actions', ['deduplicate_event', 'quarantine_late_event', 'reconcile_authoritative_state']);
  requireValues('credential_rotation', 'invariants', ['credential_never_logged', 'old_credential_revoked']);
  requireValues('terms_revision_mid_checkout', 'expected_actions', ['revalidate_terms', 'expire_decision', 'require_reapproval']);
  requireValues('refund_mismatch', 'invariants', ['refund_ledgers_remain_separate', 'settlement_math_balances']);
  requireValues('settlement_dispute', 'expected_actions', ['freeze_settlement', 'open_settlement_dispute', 'preserve_evidence']);
  requireValues('provider_acquisition_migration', 'expected_actions', ['create_migration_revision', 'dual_read_compare', 'rollback_revision']);

  const fixtureText = readFileSync(fixturePath, 'utf8');
  if (/https?:\/\/|@|-----BEGIN|\bBearer\s|\bsk-[A-Za-z0-9_-]{8,}|"real_(?:booking|charge)"\s*:\s*true/i.test(fixtureText)) failures.push('Supplier stress fixture contains a real endpoint/contact/secret or live commercial claim.');
}

const sourceFiles = [taxonomyPath, policyPath, statePath, runtimePath];
for (const path of sourceFiles) if (!existsSync(path)) failures.push(`${basename(path)} is missing.`);
if (sourceFiles.every(existsSync)) {
  const taxonomySource = readFileSync(taxonomyPath, 'utf8');
  const policySource = readFileSync(policyPath, 'utf8');
  const stateSource = readFileSync(statePath, 'utf8');
  const runtimeSource = readFileSync(runtimePath, 'utf8');
  for (const vertical of verticals) if (!taxonomySource.includes(`'${vertical}'`)) failures.push(`Supplier taxonomy is missing vertical ${vertical}.`);
  for (const capability of capabilities) if (!taxonomySource.includes(`'${capability}'`)) failures.push(`Supplier taxonomy is missing capability ${capability}.`);
  for (const method of ['supplier_profile', 'inventory_revision', 'canonical_digest', 'contains_sensitive_material', 'source_controls', 'settlement', 'kill_switch', 'readiness']) if (!policySource.includes(`function ${method}`)) failures.push(`Supplier policy is missing ${method}().`);
  for (const axis of ['onboarding', 'health', 'revision', 'operation', 'settlement']) if (!stateSource.includes(`'${axis}' => array(`)) failures.push(`Supplier state machine is missing ${axis} transitions.`);
  for (const method of ['transition', 'assert_revision_successor', 'can_execute']) if (!stateSource.includes(`function ${method}`)) failures.push(`Supplier state machine is missing ${method}().`);
  for (const injection of expectedInjections) if (!runtimeSource.includes(`'${injection}'`)) failures.push(`Supplier runtime test does not assert ${injection}.`);
  if (!policySource.includes("'insurance' ===") && !policySource.includes("in_array( 'insurance'")) failures.push('Supplier policy must gate live insurance licensing.');
  if (!policySource.includes('after_hours_route_ref')) failures.push('Supplier policy must require after-hours escalation routes.');
  if (!policySource.includes('terms_valid_until')) failures.push('Supplier policy must reject stale terms.');
  if (!policySource.includes('customer_funds_owner')) failures.push('Supplier policy must disambiguate settlement funds ownership.');
}

if (failures.length) {
  console.error('Supplier operations contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Supplier operations contracts passed (${schemas.size} closed Draft-07 schemas; ${verticals.length} verticals; ${capabilities.length} capabilities; ${expectedInjections.length} deterministic stress families).`);
