import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(import.meta.dirname, '../..');
const vip = path.join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'vip');
const paths = {
  policy: path.join(vip, 'class-tra-vel-vip-intake-fanout-policy.php'),
  planner: path.join(vip, 'class-tra-vel-vip-intake-fanout-planner.php'),
  schema: path.join(root, 'plugin', 'tra-vel-agent-core', 'schemas', 'private', 'vip-intake-fanout.schema.json'),
  runtime: path.join(root, 'scripts', 'ci', 'validate-vip-intake-fanout-runtime.php'),
  bootstrap: path.join(vip, 'bootstrap.php'),
  themeWorkflow: path.join(root, '.github', 'workflows', 'theme-ci.yml'),
  deployWorkflow: path.join(root, '.github', 'workflows', 'deploy-agent-core.yml'),
};

const failures = [];
let assertions = 0;
const check = (condition, message) => {
  assertions += 1;
  if (!condition) failures.push(message);
};
const same = (actual = [], expected = []) => JSON.stringify(actual) === JSON.stringify(expected);
const source = {};
let schema = {};
try {
  for (const [name, file] of Object.entries(paths)) {
    if (name === 'schema') continue;
    source[name] = fs.readFileSync(file, 'utf8');
  }
  schema = JSON.parse(fs.readFileSync(paths.schema, 'utf8'));
} catch (error) {
  failures.push(`VIP intake fan-out slice is missing or unreadable: ${error.message}`);
}

const families = ['lost_card_payment', 'lost_baggage_flight', 'medical_insurance_assistance', 'esim_connectivity', 'accessibility_failure'];
const playbooks = ['secure_payment_instrument_and_reconcile', 'trace_baggage_and_protect_flight_trip', 'medical_safety_and_insurance_assistance', 'restore_esim_and_offline_connectivity', 'restore_accessibility_assistance'];
const evidenceScopes = ['payment_restricted', 'flight_baggage_case', 'medical_restricted', 'connectivity_case', 'accessibility_restricted'];
const authorities = ['payment_instrument_control_verification', 'carrier_claim_or_service_change_step_up', 'medical_assistance_consent_and_policy_verification', 'connectivity_purchase_or_profile_change_step_up', 'accessibility_assistance_consent'];
const afterHoursRoutes = ['payment_security_on_call', 'flight_baggage_on_call', 'medical_assistance_on_call', 'connectivity_support_on_call', 'accessibility_support_on_call'];
const rootFields = ['contract_version', 'environment', 'fanout_ref', 'fanout_digest', 'binding', 'observation_ledger', 'case_seeds', 'summary', 'created_at', 'private_boundary'];

check(schema.$schema === 'http://json-schema.org/draft-07/schema#', 'Fan-out schema must use JSON Schema Draft-07.');
check(schema.$id === 'https://tra-vel.co.il/schemas/private/vip-intake-fanout.schema.json', 'Fan-out schema has the wrong private canonical ID.');
check(schema.additionalProperties === false, 'Fan-out schema root must reject unknown fields.');
check(same(schema.required, rootFields), 'Fan-out schema must require the exact private root field order.');
check(schema.properties?.contract_version?.const === '1.1.0', 'Fan-out schema contract version changed.');
check(schema.properties?.environment?.const === 'sandbox', 'Fan-out plan must remain sandbox-only.');
check(schema.properties?.case_seeds?.minItems === 1 && schema.properties?.case_seeds?.maxItems === 5, 'Fan-out schema must bound the unique family seed set to one through five.');
check(same(schema.definitions?.family?.enum, families), 'Fan-out family vocabulary must contain the five presentation-critical cases exactly.');
check(schema.definitions?.ledgerEntry?.properties?.mapped_case_families?.minItems === 1 && schema.definitions?.ledgerEntry?.properties?.mapped_case_families?.maxItems === 1, 'Each normalized ledger observation must map to exactly one family.');
check(!source.policy?.includes("'cross_trip.multi_issue'"), 'A shared compound observation type must not bypass family-specific risk, service, and dependency scopes.');
check(same(schema.definitions?.caseSeed?.properties?.playbook_code?.enum, playbooks), 'Every case family must have one closed playbook.');
check(same(schema.definitions?.evidencePartition?.properties?.scope?.enum, evidenceScopes), 'Evidence scope vocabulary must preserve five isolated partitions.');
check(same(schema.definitions?.authority?.properties?.requirement_code?.enum, authorities), 'Authority requirements must remain case-specific and closed.');
check(same(schema.definitions?.routing?.properties?.after_hours_route_code?.enum, afterHoursRoutes), 'Every family must retain its explicit after-hours route.');

const visitClosedObjects = (node, pointer = '#') => {
  if (!node || typeof node !== 'object') return;
  if (node.type === 'object') {
    check(node.additionalProperties === false, `${pointer} must reject unknown fields.`);
    const fields = Object.keys(node.properties || {});
    check(same(node.required || [], fields), `${pointer} must require every declared property in exact order.`);
  }
  for (const [key, child] of Object.entries(node)) {
    if (child && typeof child === 'object') visitClosedObjects(child, `${pointer}/${key}`);
  }
};
visitClosedObjects(schema);

for (const field of ['raw_message_present', 'raw_identity_data_present', 'raw_payment_data_present', 'raw_medical_data_present', 'raw_supplier_payload_present', 'bearer_secret_present']) {
  check(schema.definitions?.inputBoundary?.properties?.[field]?.const === false, `Input boundary ${field} must be structurally false.`);
}
for (const field of ['supplier_action_executed', 'payment_action_executed', 'claim_action_executed', 'booking_action_executed']) {
  check(schema.definitions?.execution?.properties?.[field]?.const === false, `Case execution ${field} must be structurally false.`);
}
check(schema.definitions?.execution?.properties?.execution_effect?.const === 'none', 'Case seeds must have no execution effect.');
check(schema.definitions?.authority?.properties?.required?.const === true, 'Each seed must require an authority decision.');
check(schema.definitions?.authority?.properties?.state?.const === 'unverified', 'Seed authority must begin unverified.');
check(schema.definitions?.authority?.properties?.execution_authorized?.const === false, 'Seed authority must not authorize execution.');
check(schema.definitions?.evidencePartition?.properties?.cross_case_disclosure_allowed?.const === false, 'Evidence cannot be disclosed across case partitions.');
check(schema.definitions?.dependencies?.properties?.preserve_unaffected_services?.const === true, 'Every seed must preserve unaffected trip services.');
check(schema.definitions?.routing?.properties?.after_hours_required?.const === true, 'Every seed must retain after-hours routing.');
check(schema.definitions?.routing?.properties?.operator_review_required?.const === true, 'Every seed must require operator review.');
check(schema.definitions?.routing?.properties?.dispatch_state?.const === 'not_dispatched', 'Routing must remain undispatched.');
check(schema.definitions?.summary?.properties?.duplicate_playbook_count?.const === 0, 'Duplicate playbooks must be structurally impossible.');
check(schema.definitions?.summary?.properties?.clarification_required?.const === false, 'Only clarified observations may produce a completed fan-out.');
check(schema.definitions?.summary?.properties?.side_effect_count?.const === 0, 'Fan-out summary must report zero side effects.');
for (const field of ['storage_written', 'rest_route_registered', 'network_called', 'ai_called', 'supplier_dispatched', 'payment_executed', 'claim_submitted', 'booking_executed']) {
  check(schema.definitions?.privateBoundary?.properties?.[field]?.const === false, `Private boundary ${field} must be structurally false.`);
}
check(schema.definitions?.privateBoundary?.properties?.server_only?.const === true, 'Fan-out must remain server-only.');
check(schema.definitions?.privateBoundary?.properties?.public_serialization_allowed?.const === false, 'Fan-out cannot be publicly serialized.');
check(schema.definitions?.privateBoundary?.properties?.planning_only?.const === true, 'Fan-out must remain planning-only.');
check(schema.definitions?.privateBoundary?.properties?.authorization_effect?.const === 'none', 'Fan-out must grant no authority.');

for (const marker of [
  'class Tra_Vel_VIP_Intake_Fanout_Policy',
  "const CONTRACT_VERSION = '1.1.0'",
  'const MAX_OBSERVATIONS = 32',
  'const CASE_FAMILIES = array(',
  'function validated_binding',
  'function normalized_observation',
  'function fanout',
  'function family_config',
  'implicit_multi_mapping_rejected',
  'evidence_coverage_invalid',
  'evidence_cross_partition_invalid',
  'case_seed_order_invalid',
  "'family_ref'",
  'supplier_action_executed',
  'payment_action_executed',
  'claim_action_executed',
  'booking_action_executed',
  'execution_authorized',
  'cross_case_disclosure_allowed',
  'storage_written',
  'rest_route_registered',
  'network_called',
  'ai_called',
]) check(source.policy?.includes(marker), `Fan-out policy is missing ${marker}.`);

for (const value of [...families, ...playbooks, ...evidenceScopes, ...authorities, ...afterHoursRoutes]) {
  check(source.policy?.includes(`'${value}'`), `Fan-out policy is missing closed value ${value}.`);
}

for (const marker of [
  'class Tra_Vel_VIP_Intake_Fanout_Planner',
  'function plan',
  'validated_binding',
  'normalized_observation',
  "'verified' !== $observation['mapping_state']",
  'observation_ref_conflict',
  'observation_idempotency_conflict',
  'evidence_cross_partition_conflict',
  'evidence_digest_conflict',
  'ksort( $accepted, SORT_STRING )',
  'compare_seeds',
  'priority_rank',
  "'duplicate_playbook_count' => 0",
  "'side_effect_count' => 0",
  "'storage_written' => false",
  "'rest_route_registered' => false",
  "'network_called' => false",
  "'ai_called' => false",
  "'supplier_dispatched' => false",
  "'payment_executed' => false",
  "'claim_submitted' => false",
  "'booking_executed' => false",
]) check(source.planner?.includes(marker), `Fan-out planner is missing ${marker}.`);

for (const forbidden of [
  'register_rest_route(', '$wpdb', 'wp_remote_get(', 'wp_remote_post(', 'wp_remote_request(', 'curl_exec(',
  'file_put_contents(', 'update_option(', 'add_action(', 'do_action(', 'apply_filters(', 'random_bytes(',
  'fopen(', 'mysqli_', 'new PDO(', 'openai', '->reserve(', '->charge(', '->refund(', '->dispatch(', '->submit(',
]) {
  check(!source.policy?.toLowerCase().includes(forbidden.toLowerCase()) && !source.planner?.toLowerCase().includes(forbidden.toLowerCase()), `Pure fan-out crossed its no-storage/no-REST/no-network/no-AI/no-commerce boundary: ${forbidden}`);
}
for (const php8Only of [/\bmatch\s*\(/, /\bstr_(?:contains|starts_with|ends_with)\s*\(/, /\?->/, /\bfn\s*\(/]) {
  check(!php8Only.test(source.policy || '') && !php8Only.test(source.planner || ''), `Fan-out must remain PHP 7.4-compatible; found ${php8Only}.`);
}

const policyLoad = "class-tra-vel-vip-intake-fanout-policy.php";
const plannerLoad = "class-tra-vel-vip-intake-fanout-planner.php";
check(source.bootstrap?.includes(policyLoad), 'VIP bootstrap must load the fan-out policy.');
check(source.bootstrap?.includes(plannerLoad), 'VIP bootstrap must load the fan-out planner.');
check((source.bootstrap?.indexOf(policyLoad) ?? -1) >= 0 && source.bootstrap.indexOf(policyLoad) < source.bootstrap.indexOf(plannerLoad), 'VIP bootstrap must load policy before planner.');
for (const workflow of ['themeWorkflow', 'deployWorkflow']) {
  check((source[workflow]?.match(/php scripts\/ci\/validate-vip-intake-fanout-runtime\.php/g) || []).length === 2, `${workflow} must run the fan-out runtime in both PHP/package gates.`);
  check((source[workflow]?.match(/node scripts\/ci\/validate-vip-intake-fanout-contract\.mjs/g) || []).length === 1, `${workflow} must run the fan-out contract gate once.`);
}

const scenarioCount = (source.runtime?.match(/fanout_scenario\(/g) || []).length - 1;
check(scenarioCount >= 20, `Fan-out runtime covers only ${scenarioCount} focused scenarios; at least 20 are required.`);
for (const marker of [
  'one intake creates exactly five isolated case seeds',
  'input order does not change deterministic fan-out',
  'exact observation replay is idempotently deduplicated',
  'two unique observations merge into one family playbook',
  'one intake can produce separately scoped sibling observations',
  'implicit multi-playbook mapping fails closed',
  'ambiguous and conflicted classification require upstream clarification',
  'cross-trip observations are rejected',
  'restricted evidence cannot bleed between medical and payment cases',
  'same evidence reference cannot enter two partitions',
  'impossible calendar timestamps fail closed',
  'P0 must lead the stable family order without suppressing any case',
  'private boundary',
  'zero side effects',
]) check(source.runtime?.includes(marker), `Fan-out runtime is missing required proof: ${marker}.`);

if (failures.length) {
  console.error('VIP intake fan-out contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`VIP intake fan-out contract passed (${assertions} assertions; ${families.length} isolated families; single-family observation scopes; P0-first/non-suppressing; zero execution).`);
