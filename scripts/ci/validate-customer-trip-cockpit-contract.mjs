import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(import.meta.dirname, '../..');
const schemaPath = path.join(root, 'plugin/tra-vel-agent-core/schemas/private/customer-trip-cockpit-read-model.schema.json');
const policyPath = path.join(root, 'plugin/tra-vel-agent-core/includes/vip/class-tra-vel-customer-trip-cockpit-policy.php');
const factoryPath = path.join(root, 'plugin/tra-vel-agent-core/includes/vip/class-tra-vel-customer-trip-cockpit-factory.php');
const bootstrapPath = path.join(root, 'plugin/tra-vel-agent-core/includes/vip/bootstrap.php');
const runtimePath = path.join(root, 'scripts/ci/validate-customer-trip-cockpit-runtime.php');
const themeWorkflowPath = path.join(root, '.github/workflows/theme-ci.yml');
const agentWorkflowPath = path.join(root, '.github/workflows/deploy-agent-core.yml');

const failures = [];
const fail = message => failures.push(message);
const read = file => fs.readFileSync(file, 'utf8');
const count = (text, needle) => text.split(needle).length - 1;

let schema;
try {
  schema = JSON.parse(read(schemaPath));
} catch (error) {
  fail(`Private cockpit schema does not parse: ${error.message}`);
  schema = {};
}

function sameMembers(actual = [], expected = []) {
  return actual.length === expected.length && [...actual].sort().join('\n') === [...expected].sort().join('\n');
}

function assertClosedObjects(node, pointer = '#') {
  if (!node || typeof node !== 'object') return;
  if (node.type === 'object') {
    if (node.additionalProperties !== false) fail(`${pointer} must set additionalProperties:false.`);
    const properties = Object.keys(node.properties || {});
    if (!sameMembers(node.required || [], properties)) fail(`${pointer} must require every declared property and no undeclared property.`);
  }
  for (const [key, child] of Object.entries(node)) {
    if (child && typeof child === 'object') assertClosedObjects(child, `${pointer}/${key}`);
  }
}

const rootFields = [
  'contract_version', 'environment', 'cockpit_ref', 'trip_ref', 'owner_scope_digest', 'revision',
  'previous_projection_digest', 'projection_digest', 'trip_headline', 'current', 'urgent_next_action',
  'protected', 'changed', 'approvals_required', 'unresolved_questions', 'service_timeline', 'money_status',
  'trip_care_cases', 'trip_care_receipts', 'traveler_readiness', 'loyalty', 'offline_pack', 'last_verified_at', 'authority', 'data_boundary',
];
if (schema.$id !== 'https://tra-vel.co.il/schemas/private/customer-trip-cockpit-read-model.schema.json') fail('Private cockpit schema ID changed.');
if (schema.properties?.environment?.const !== 'sandbox') fail('Cockpit read model must remain sandbox-only.');
if (!sameMembers(schema.required, rootFields)) fail('Cockpit root no longer exposes the exact minimized field set.');
assertClosedObjects(schema);

const currentFields = ['phase', 'health', 'registration_gate', 'registration_readiness', 'affected_service_count', 'unaffected_service_count', 'action_required', 'verified_at'];
if (!sameMembers(schema.definitions?.current?.required, currentFields)) fail('Current status must retain separate phase, health, registration readiness, and affected/unaffected counts.');
if (!sameMembers(Object.keys(schema.definitions?.moneyStatus?.properties || {}), ['funds', 'payments', 'refunds', 'settlements'])) fail('Money status must keep funds, payment, refund, and settlement as four independent axes.');
const moneyEnums = {
  fundsItem: ['not_started', 'authorization_pending', 'partially_collected', 'collected', 'partially_returned', 'returned', 'at_risk', 'failed', 'uncertain'],
  paymentItem: ['not_started', 'pending', 'requires_action', 'authorized', 'captured', 'failed', 'voided', 'partially_refunded', 'refunded', 'uncertain', 'disputed', 'charged_back'],
  refundItem: ['not_requested', 'requested', 'pending', 'partially_refunded', 'refunded', 'failed', 'uncertain', 'disputed'],
  settlementItem: ['not_applicable', 'pending', 'partially_settled', 'settled', 'reversed', 'disputed', 'uncertain'],
};
for (const [definition, states] of Object.entries(moneyEnums)) {
  if (!sameMembers(schema.definitions?.[definition]?.properties?.state?.enum, states)) fail(`${definition} must keep its own closed state vocabulary.`);
}
if (!sameMembers(schema.definitions?.authority?.required, ['authorization_effect', 'supplier_action_started', 'processor_action_started', 'resolution_inferred', 'combined_booking_status_exposed'])) fail('Authority boundary is incomplete.');
for (const field of ['supplier_action_started', 'processor_action_started', 'resolution_inferred', 'combined_booking_status_exposed']) {
  if (schema.definitions?.authority?.properties?.[field]?.const !== false) fail(`Authority.${field} must be structurally false.`);
}
if (schema.definitions?.authority?.properties?.authorization_effect?.const !== 'none') fail('Cockpit projection must grant no authorization effect.');
for (const field of ['public_serialization_allowed', 'raw_identity_data_stored', 'raw_payment_data_stored', 'raw_medical_data_stored', 'raw_provider_payload_stored', 'bearer_secret_stored', 'provider_execution_claimed']) {
  if (schema.definitions?.dataBoundary?.properties?.[field]?.const !== false) fail(`Private boundary ${field} must be structurally false.`);
}
if (schema.definitions?.dataBoundary?.properties?.server_only?.const !== true) fail('Cockpit read model must remain server-only.');

const policy = read(policyPath);
const factory = read(factoryPath);
const bootstrap = read(bootstrapPath);
const runtime = read(runtimePath);

for (const marker of [
  'validate_source', 'validate_projection', 'assert_successor', 'trip_health_partition_invalid',
  'trip_health_action_missing', 'trip_care_receipt_action_missing', 'successor_unaffected_service_changed', 'successor_service_removed', 'projection_digest_invalid',
  'raw_identity_data_stored', 'raw_payment_data_stored', 'raw_medical_data_stored',
]) {
  if (!policy.includes(marker)) fail(`Cockpit policy is missing ${marker}.`);
}
for (const marker of [
  'create_projection', 'urgent_next_action', 'approvals_required', 'unresolved_questions', 'trip_care_cases',
  'service_timeline', 'money_status', "'funds'", "'payments'", "'refunds'", "'settlements'",
  "'resolution_inferred'", "'combined_booking_status_exposed'",
]) {
  if (!factory.includes(marker)) fail(`Cockpit factory is missing ${marker}.`);
}
for (const forbidden of [
  'register_rest_route', '$wpdb', 'wp_remote_', 'curl_', 'mysqli_', 'PDO(', 'update_option(',
  'supplier_action_started\'        => true', 'processor_action_started\'       => true',
]) {
  if (policy.includes(forbidden) || factory.includes(forbidden)) fail(`Cockpit read model crossed its non-network/non-DB/non-REST/non-authority boundary: ${forbidden}`);
}
for (const php8Only of [/\bmatch\s*\(/, /\bstr_(?:contains|starts_with|ends_with)\s*\(/, /\?->/, /\bfn\s*\(/]) {
  if (php8Only.test(policy) || php8Only.test(factory)) fail(`Cockpit classes must remain PHP 7.4-compatible; found ${php8Only}.`);
}
if (!bootstrap.includes("class-tra-vel-customer-trip-cockpit-policy.php") || !bootstrap.includes("class-tra-vel-customer-trip-cockpit-factory.php")) fail('VIP bootstrap does not load both cockpit classes.');
if (bootstrap.indexOf('class-tra-vel-customer-trip-cockpit-policy.php') > bootstrap.indexOf('class-tra-vel-customer-trip-cockpit-factory.php')) fail('Cockpit policy must load before its factory.');

const requiredScenarios = [
  'mid_trip_cascading_change', 'split_party_readiness', 'partial_refund', 'lost_connectivity',
  'accessibility_acknowledgement', 'minor_authority', 'loyalty_stale', 'supplier_uncertainty',
  'vip_immediate_safety', 'approval_deadline', 'deadline_priority_ordering', 'refund_uncertain_payment_captured',
];
for (const scenario of requiredScenarios) {
  if (!runtime.includes(`$scenarios['${scenario}']`)) fail(`Runtime stress suite is missing ${scenario}.`);
}
const scenarioCount = [...runtime.matchAll(/\$scenarios\['[a-z0-9_]+'\]\s*=\s*function/g)].length;
if (scenarioCount < 24) fail(`Cockpit runtime covers only ${scenarioCount} combined scenarios; at least 24 are required.`);
for (const marker of [
  'Incident deadline must outrank', 'Benefit, funds, payment, refund, and settlement truth must remain independently visible',
  'successor_unaffected_service_changed', 'successor_service_removed', 'overall_booking_status',
]) {
  if (!runtime.includes(marker)) fail(`Adversarial runtime is missing boundary assertion: ${marker}.`);
}

const workflowExpectations = [
  [themeWorkflowPath, 2, 1],
  [agentWorkflowPath, 2, 1],
];
for (const [workflowPath, expectedRuntime, expectedContract] of workflowExpectations) {
  const workflow = read(workflowPath);
  const runtimeCount = count(workflow, 'php scripts/ci/validate-customer-trip-cockpit-runtime.php');
  const contractCount = count(workflow, 'node scripts/ci/validate-customer-trip-cockpit-contract.mjs');
  if (runtimeCount !== expectedRuntime) fail(`${path.basename(workflowPath)} must wire the cockpit runtime exactly ${expectedRuntime} times; found ${runtimeCount}.`);
  if (contractCount !== expectedContract) fail(`${path.basename(workflowPath)} must wire the cockpit contract exactly ${expectedContract} time; found ${contractCount}.`);
}

if (failures.length) {
  console.error('Customer Trip Cockpit contract failed:');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log(`Customer Trip Cockpit contract passed (${rootFields.length} root fields; ${scenarioCount} combined stress scenarios; private/no-authority boundary).`);
