import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');
const privateSchemas = path.join(root, 'plugin/tra-vel-agent-core/schemas/private');
const localIncludes = path.join(root, 'plugin/tra-vel-agent-core/includes/local-tourism');
const fixturePath = path.join(root, 'plugin/tra-vel-agent-core/assets/fixtures/israel-local-operations-stress-matrix.json');
const schemaNames = [
  'israel-local-service-revision.schema.json',
  'israel-local-search-context.schema.json',
  'israel-local-disruption-event.schema.json',
  'israel-local-recovery-plan.schema.json',
  'israel-local-operations-stress-matrix.schema.json',
];

let assertions = 0;
function assert(condition, message) {
  assertions += 1;
  if (!condition) throw new Error(`Israel local operations contract failed: ${message}`);
}
function sorted(values) { return [...values].sort(); }
function exact(actual, expected, message) {
  assert(JSON.stringify(sorted(actual)) === JSON.stringify(sorted(expected)), message);
}
function resolvePointer(schema, ref) {
  assert(ref.startsWith('#/'), `only local schema refs are permitted (${ref})`);
  return ref.slice(2).split('/').reduce((value, token) => value?.[token.replace(/~1/g, '/').replace(/~0/g, '~')], schema);
}
function inspectSchema(schema, value, pointer = '#') {
  if (!value || typeof value !== 'object') return;
  if (typeof value.$ref === 'string') assert(Boolean(resolvePointer(schema, value.$ref)), `${pointer} has unresolved ref ${value.$ref}`);
  if (value.type === 'object') {
    assert(value.additionalProperties === false, `${pointer} must be a closed object`);
    assert(value.properties && typeof value.properties === 'object', `${pointer} must declare properties`);
  }
  if (Array.isArray(value.enum)) assert(new Set(value.enum.map(v => JSON.stringify(v))).size === value.enum.length, `${pointer} enum contains duplicates`);
  for (const [key, child] of Object.entries(value)) if (child && typeof child === 'object') inspectSchema(schema, child, `${pointer}/${key}`);
}

const schemas = new Map(schemaNames.map(name => {
  const source = fs.readFileSync(path.join(privateSchemas, name), 'utf8');
  const schema = JSON.parse(source);
  assert(schema.$schema === 'http://json-schema.org/draft-07/schema#', `${name} must use Draft-07`);
  assert(/^https:\/\/tra-vel\.co\.il\/schemas\/private\/.+-v1\.0\.0\.json$/.test(schema.$id), `${name} needs a stable private v1 identifier`);
  assert(!/"(?:supplier_name|provider_name|property_name|price|amount_minor|estimated_amount|live_availability)"\s*:/.test(source), `${name} cannot seed real identity, numeric price, or availability claims`);
  inspectSchema(schema, schema);
  return [name, schema];
}));

const service = schemas.get('israel-local-service-revision.schema.json');
for (const field of ['sellable', 'occupancy', 'arrival', 'terms', 'after_hours_support', 'provenance', 'commerce_binding']) {
  assert(service.required.includes(field), `service revision must require ${field}`);
}
exact(service.definitions.sellable.required, ['scope', 'product_ref', 'unit_ref', 'session_ref', 'route_ref'], 'sellable must distinguish an exact unit, session, or route');
exact(service.definitions.terms.required, ['tax_treatment', 'tax_terms_ref', 'deposit_treatment', 'deposit_terms_ref', 'cancellation_treatment', 'cancellation_terms_ref', 'cancellation_deadline_utc'], 'tax, deposit, and cancellation terms must be explicit');
assert(service.definitions.boundary.properties.public_serialization_allowed.const === false, 'service revisions must remain private');
assert(service.definitions.boundary.properties.live_availability_claimed.const === false, 'service revisions cannot claim live inventory');
assert(service.definitions.boundary.properties.supplier_dispatched.const === false && service.definitions.boundary.properties.processor_called.const === false, 'service revisions cannot dispatch');

const search = schemas.get('israel-local-search-context.schema.json');
exact(search.definitions.benefitFilters.required, ['filter_mode', 'airline_inventory_ids', 'program_ids', 'credential_product_ids', 'campaign_revisions'], 'local benefit filters must expose every exact identity axis');
exact(search.definitions.requirements.required, ['kosher', 'shabbat', 'accessibility', 'parking'], 'local fit filtering must use exact operational groups');
exact(search.definitions.productIntents.required, ['activities', 'dining', 'equipment'], 'activities, dining, and equipment must be first-class intent');
assert(search.definitions.party.properties.child_ages.items.maximum === 17, 'child ages must be exact and bounded');
assert(search.definitions.boundary.properties.contains_card_number.const === false && search.definitions.boundary.properties.contains_loyalty_credentials.const === false, 'search filters cannot hold payment or loyalty secrets');
assert(search.definitions.boundary.properties.creates_eligibility.const === false && search.definitions.boundary.properties.creates_price.const === false, 'search context cannot create commercial truth');

const event = schemas.get('israel-local-disruption-event.schema.json');
for (const type of ['weather', 'fire', 'flood', 'security', 'evacuation', 'road', 'rail', 'utility', 'closure', 'certificate_expiry']) {
  assert(event.properties.disruption_type.enum.includes(type), `disruption taxonomy is missing ${type}`);
}
exact(event.definitions.source.required, ['authority', 'source_ref', 'evidence_digest', 'observed_at', 'fresh_until', 'truth_state'], 'event source authority and freshness must be explicit');
exact(event.definitions.geometry.required, ['geometry_type', 'geometry_ref', 'geometry_digest', 'corridor'], 'event geometry must be source-bound and corridor-aware');
assert(event.definitions.boundary.properties.financial_state_changed.const === false, 'events cannot mutate financial state');

const recovery = schemas.get('israel-local-recovery-plan.schema.json');
assert(recovery.definitions.financialSeparation.properties.netting_prohibited.const === true, 'refund and replacement payment netting must be prohibited');
assert(recovery.definitions.financialSeparation.properties.existing_commerce_engine_authoritative.const === true, 'the existing commerce engine must stay authoritative');
assert(recovery.definitions.dispatch.properties.state.const === 'not_dispatched', 'recovery plans must be zero-dispatch');
assert(recovery.definitions.action.properties.execution_state.const === 'planned', 'recovery actions must remain planned');
assert(recovery.definitions.boundary.properties.creates_order.const === false && recovery.definitions.boundary.properties.creates_payment.const === false && recovery.definitions.boundary.properties.creates_refund.const === false, 'recovery cannot create a second commerce engine');

const stressSchema = schemas.get('israel-local-operations-stress-matrix.schema.json');
assert(stressSchema.properties.scenarios.minItems >= 36, 'the stress matrix gate must require at least 36 scenarios');
assert(stressSchema.definitions.scenario.properties.preserve_unaffected_components.const === true, 'every scenario must preserve unaffected components');
assert(stressSchema.definitions.scenario.properties.supplier_dispatched.const === false && stressSchema.definitions.scenario.properties.processor_called.const === false, 'every scenario must be zero-dispatch');

const fixtureSource = fs.readFileSync(fixturePath, 'utf8');
const fixture = JSON.parse(fixtureSource);
assert(fixture.contract_version === '1.0.0' && fixture.environment === 'sandbox' && fixture.data_mode === 'synthetic_demo', 'stress fixture must be a sandbox synthetic demo');
assert(fixture.scenarios.length >= 36, 'stress fixture needs at least 36 scenarios');
assert(!/(?:El Al|Arkia|Isracard|Visa|Fly Card|FlyAll|₪|\$[0-9])/i.test(fixtureSource), 'stress fixture cannot contain real supplier names or numeric public prices');
const expectedTriggers = stressSchema.definitions.trigger.enum;
exact(fixture.scenarios.map(row => row.trigger), expectedTriggers, 'stress fixture must cover every declared operational trigger exactly once');
const ids = new Set();
const corridors = new Map([['jerusalem_corridor', 0], ['eilat_corridor', 0]]);
for (const row of fixture.scenarios) {
  assert(!ids.has(row.scenario_id), `duplicate scenario ID ${row.scenario_id}`);
  ids.add(row.scenario_id);
  assert(corridors.has(row.corridor), `${row.scenario_id} uses an unsupported corridor`);
  corridors.set(row.corridor, corridors.get(row.corridor) + 1);
  assert(row.preserve_unaffected_components === true && row.supplier_dispatched === false && row.processor_called === false, `${row.scenario_id} violates the recovery boundary`);
}
assert(corridors.get('jerusalem_corridor') >= 18 && corridors.get('eilat_corridor') >= 18, 'both Jerusalem and Eilat need at least 18 stress cases');

const bootstrap = fs.readFileSync(path.join(localIncludes, 'bootstrap.php'), 'utf8');
const phpFiles = [
  'class-tra-vel-local-operations-taxonomy.php',
  'class-tra-vel-local-operations-policy.php',
  'class-tra-vel-local-operations-recovery-planner.php',
];
for (const file of phpFiles) assert(bootstrap.includes(`require_once __DIR__ . '/${file}'`), `local bootstrap must load ${file}`);
const phpSource = phpFiles.map(file => fs.readFileSync(path.join(localIncludes, file), 'utf8')).join('\n');
for (const trigger of expectedTriggers) assert(phpSource.includes(`'${trigger}'`), `PHP runtime is missing ${trigger}`);
assert(!/register_rest_route|wp_remote_(?:get|post|request)|payment_capture|new\s+Tra_Vel_Commerce_Order/i.test(phpSource), 'local operations runtime cannot expose a route, call a supplier, or create another commerce engine');
for (const token of ["'supplier_dispatched'               => false", "'processor_called'                  => false", "'netting_prohibited'", "'existing_commerce_engine_authoritative'"]) {
  assert(phpSource.includes(token), `recovery planner is missing zero-side-effect control ${token}`);
}

const runtime = fs.readFileSync(path.join(root, 'scripts/ci/validate-local-operations-runtime.php'), 'utf8');
assert(runtime.includes("count( $matrix['scenarios'] ) >= 36"), 'runtime gate must enforce at least 36 scenarios');
assert(runtime.includes("array( $service_b['service_ref'] ) === $plan['preserved_service_refs']"), 'runtime must prove unaffected-service preservation');
assert(runtime.includes("'separate_authorization_required' === $plan['financial_separation']['replacement_payment_state']"), 'runtime must prove refund/payment separation');

for (const workflowName of ['.github/workflows/theme-ci.yml', '.github/workflows/deploy-agent-core.yml']) {
  const workflow = fs.readFileSync(path.join(root, workflowName), 'utf8');
  assert(workflow.includes('php scripts/ci/validate-local-operations-runtime.php'), `${workflowName} must run the local-operations runtime gate`);
  assert(workflow.includes('node scripts/ci/validate-local-operations-contract.mjs'), `${workflowName} must run the local-operations contract gate`);
}

console.log(`Israel local operations contracts passed (${schemaNames.length} private closed schemas; ${fixture.scenarios.length} scenarios; ${assertions} assertions).`);
