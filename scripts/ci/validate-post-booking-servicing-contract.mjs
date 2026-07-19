import { readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const read = (...parts) => readFileSync(join(root, ...parts), 'utf8');
const parse = (...parts) => JSON.parse(read(...parts));
const schemaRoot = ['plugin', 'tra-vel-agent-core', 'schemas', 'private'];
const schemas = {
  snapshot: parse(...schemaRoot, 'post-booking-servicing-snapshot.schema.json'),
  plan: parse(...schemaRoot, 'post-booking-servicing-plan.schema.json'),
  matrix: parse(...schemaRoot, 'post-booking-servicing-stress-matrix.schema.json'),
};
const matrix = parse('plugin', 'tra-vel-agent-core', 'assets', 'fixtures', 'post-booking-servicing-stress-matrix.json');
const servicingRoot = ['plugin', 'tra-vel-agent-core', 'includes', 'servicing'];
const taxonomy = read(...servicingRoot, 'class-tra-vel-postbooking-servicing-taxonomy.php');
const policy = read(...servicingRoot, 'class-tra-vel-postbooking-servicing-policy.php');
const factory = read(...servicingRoot, 'class-tra-vel-postbooking-servicing-factory.php');
const bootstrap = read(...servicingRoot, 'bootstrap.php');
const pluginMain = read('plugin', 'tra-vel-agent-core', 'tra-vel-agent-core.php');
const runtime = read('scripts', 'ci', 'validate-post-booking-servicing-runtime.php');
const operationsDoc = read('docs', 'VIP_TRAVEL_SERVICE_OPERATIONS.md');
const workflows = {
  theme: read('.github', 'workflows', 'theme-ci.yml'),
  deploy: read('.github', 'workflows', 'deploy-agent-core.yml'),
};

const failures = [];
const fail = (message) => failures.push(message);
const same = (left, right) => JSON.stringify(left) === JSON.stringify(right);
const count = (text, marker) => text.split(marker).length - 1;

const expectedIds = {
  snapshot: 'https://tra-vel.co.il/schemas/private/post-booking-servicing-snapshot.schema.json',
  plan: 'https://tra-vel.co.il/schemas/private/post-booking-servicing-plan.schema.json',
  matrix: 'https://tra-vel.co.il/schemas/private/post-booking-servicing-stress-matrix.schema.json',
};
for (const [name, schema] of Object.entries(schemas)) {
  if (schema.$id !== expectedIds[name]) fail(`${name} schema must retain its canonical private ID.`);
  if (schema.additionalProperties !== false) fail(`${name} schema root must reject unknown fields.`);
}

function inspectClosedObjects(node, pointer = '#') {
  if (!node || typeof node !== 'object') return;
  if (node.type === 'object') {
    if (node.additionalProperties !== false) fail(`${pointer} must reject unknown fields.`);
    if (!same(node.required || [], Object.keys(node.properties || {}))) fail(`${pointer} must require every declared field in stable order.`);
  }
  for (const [key, value] of Object.entries(node)) inspectClosedObjects(value, `${pointer}/${key}`);
}
for (const [name, schema] of Object.entries(schemas)) inspectClosedObjects(schema, `#/${name}`);

const snapshotFields = [
  'contract_version', 'environment', 'data_mode', 'servicing_case_ref',
  'snapshot_version', 'previous_snapshot_digest', 'snapshot_digest',
  'owner_scope_digest', 'bindings', 'change_class', 'affected_scope',
  'flight_state', 'lodging_state', 'financial_differentials',
  'independent_states', 'message_delivery', 'observed_at', 'boundary',
];
const planFields = [
  'contract_version', 'environment', 'data_mode', 'plan_ref', 'plan_digest',
  'input_binding', 'change_class', 'plan_state', 'scope_resolution',
  'truth_checks', 'action_queue', 'financial_handling', 'state_axes',
  'lodging_reconciliation', 'communication_reconciliation',
  'required_approvals', 'evaluated_at', 'boundary',
];
if (!same(schemas.snapshot.required, snapshotFields) || !same(Object.keys(schemas.snapshot.properties), snapshotFields)) fail('Snapshot root must retain one exact immutable field set.');
if (!same(schemas.plan.required, planFields) || !same(Object.keys(schemas.plan.properties), planFields)) fail('Plan root must retain one exact deterministic field set.');

const stateFields = [
  'supplier_reservation_state', 'supplier_fulfillment_state', 'customer_payment_state',
  'supplier_refund_state', 'customer_refund_state', 'supplier_settlement_state',
  'reconciliation_state',
];
if (!same(schemas.snapshot.definitions?.independentStates?.required, stateFields)) fail('Snapshot must preserve all seven independent supplier/financial axes.');
if (!same(schemas.plan.definitions?.independentStates?.required, stateFields)) fail('Plan must project all seven axes without inference.');
const financialOutcomes = ['add_collect', 'refund', 'residual_value', 'reusable_value', 'even_exchange'];
if (!same(schemas.snapshot.definitions?.financialDifferential?.properties?.outcome_types?.items?.enum, financialOutcomes)) fail('Snapshot must preserve the exact IATA-style financial outcome vocabulary.');
for (const invariant of ['netting_prohibited', 'no_derived_net_amount', 'existing_commerce_ledgers_authoritative']) {
  if (schemas.plan.definitions?.financialHandling?.properties?.[invariant]?.const !== true) fail(`Plan must pin ${invariant} to true.`);
}
for (const field of ['reservation_state', 'room_state', 'guest_state', 'date_state', 'occupancy_state', 'no_show_state', 'inventory_restoration_state']) {
  if (!schemas.plan.definitions?.lodgingReconciliation?.required?.includes(field)) fail(`Lodging plan must preserve independent ${field}.`);
}
if (schemas.plan.definitions?.communicationReconciliation?.properties?.booking_state_inference_allowed?.const !== false) fail('Message delivery cannot be converted into booking truth.');

if (matrix.scenario_count !== 36 || matrix.scenarios.length !== 36) fail('Stress fixture must contain exactly 36 declared scenarios.');
const scenarioIds = new Set(matrix.scenarios.map((scenario) => scenario.scenario_id));
const families = new Set(matrix.scenarios.map((scenario) => scenario.family));
if (scenarioIds.size !== 36) fail('Stress scenario IDs must be unique.');
if (families.size !== 7) fail('Stress matrix must cover all seven flight, lodging and cross-axis families.');
const observedOutcomes = new Set(matrix.scenarios.flatMap((scenario) => scenario.financial_outcomes));
for (const outcome of financialOutcomes) if (!observedOutcomes.has(outcome)) fail(`Stress matrix must exercise ${outcome}.`);
if (!matrix.scenarios.some((scenario) => scenario.financial_outcomes.length > 1)) fail('Stress matrix must exercise combined financial outcomes without netting.');
if (!matrix.scenarios.some((scenario) => scenario.flight_applicable && scenario.lodging_applicable)) fail('Stress matrix must exercise one cross-vertical servicing case.');
if (!matrix.scenarios.some((scenario) => scenario.expected_plan_state === 'evidence_required')) fail('Stress matrix must fail closed when current evidence is unavailable.');

const evidenceMarkers = [
  'https://developers.booking.com/demand/docs/orders-api/orders-faqs',
  'https://developers.booking.com/connectivity/docs/reporting-api/b_xml-reporting',
  'https://developers.booking.com/connectivity/docs/con-faq-reservations-missing-res-messages',
  'https://developers.amadeus.com/self-service/apis-docs/guides/developer-guides/resources/flights/',
  'https://developers.amadeus.com/self-service/apis-docs/guides/developer-guides/faq/',
  'https://guides.developer.iata.org/docs/20-2_ImplementationGuide.pdf',
  'https://www.iata.org/contentassets/e7a533819be440edbb1e49da96e0f2a8/guidance-document-interline-irops_25june2020.pdf',
  'A PNR or order reference is not proof that a ticket or ancillary document was issued.',
  'Never reduce a changed order to one signed net number.',
  'Preserve reservation, room, guest, date, occupancy, price, payment, no-show, and inventory-restoration states independently.',
  'Webhooks, polling cursors, fallback notices, acknowledgements, and authoritative retrieval need independent health and replay controls.',
];
for (const marker of evidenceMarkers) if (!operationsDoc.includes(marker)) fail(`Operations source gate is missing: ${marker}`);

for (const marker of [
  'voluntary_change', 'planned_schedule_change', 'day_of_travel_disruption',
  'add_collect', 'refund', 'residual_value', 'reusable_value', 'even_exchange',
  'verify_ticket_issuance', 'verify_coupon_statuses', 'verify_emd_fulfillment',
  'reconcile_no_show_separately', 'verify_inventory_restoration',
  'perform_authoritative_message_retrieval',
]) if (!taxonomy.includes(marker)) fail(`Taxonomy is missing servicing marker: ${marker}.`);

for (const marker of [
  'snapshot_lineage_invalid', 'flight_ownership_incomplete',
  'unissued_ticket_documents_present', 'issued_ticket_documents_missing',
  'ticket_stock_owner_missing', 'lodging_guest_allocation_invalid',
  'lodging_room_date_outside_scope', 'financial_component_mismatch',
  'even_exchange_invalid', 'traveler_repayment_state_mismatch',
  'message_acknowledgement_invalid', 'booking_state_inference_allowed',
  'netting_prohibited', 'no_derived_net_amount', 'existing_commerce_ledgers_authoritative',
  'supplier_reservation_state', 'supplier_fulfillment_state',
  'customer_payment_state', 'supplier_refund_state', 'customer_refund_state',
  'supplier_settlement_state', 'reconciliation_state',
]) if (!policy.includes(marker)) fail(`Policy is missing invariant: ${marker}.`);

for (const marker of [
  'expected_binding', 'current_binding_mismatch',
  'Tra_Vel_Postbooking_Servicing_Policy::seal_plan',
  'Tra_Vel_Postbooking_Servicing_Policy::validate_plan',
]) if (!factory.includes(marker)) fail(`Factory is missing current-binding behavior: ${marker}.`);
for (const marker of ['owner_scope_digest', 'supplier_order_digest', 'commerce_order_digest', 'snapshot_digest']) {
  if (!policy.includes(marker)) fail(`Policy exact binding is missing: ${marker}.`);
}

for (const marker of [
  '36 adversarial scenarios', 'confirmed reservation with no issued ticket',
  'unissued state cannot retain ticket documents', 'EMD must bind a scoped order item',
  'no-show cannot imply restored inventory', 'delivery cannot become booking truth',
  'supplier refund cannot imply customer repayment', 'must never collapse service into one signed net',
  'another owner scope', 'stale trip digest', 'stale supplier-order digest',
  'stale commerce-order digest', 'stale servicing snapshot digest',
]) if (!runtime.includes(marker)) fail(`Runtime gate is missing adversarial proof: ${marker}.`);

const forbiddenSideEffects = [
  'wp_remote_', 'curl_', 'fsockopen', 'stream_socket_client', 'file_get_contents(',
  'file_put_contents', '$wpdb', 'register_rest_route', 'add_action(', 'update_option(',
  'wp_insert_post(', 'wp_mail(',
];
for (const marker of forbiddenSideEffects) if ((policy + factory).includes(marker)) fail(`Servicing policy/factory cannot perform side effects: ${marker}.`);
for (const forbidden of ['net_amount_minor', 'net_difference_minor', 'net_refund_minor']) {
  if ((policy + factory).includes(forbidden)) fail(`Servicing policy/factory cannot model a collapsed signed net field: ${forbidden}.`);
}

for (const file of [
  'class-tra-vel-postbooking-servicing-taxonomy.php',
  'class-tra-vel-postbooking-servicing-policy.php',
  'class-tra-vel-postbooking-servicing-factory.php',
]) if (count(bootstrap, file) !== 1) fail(`Servicing bootstrap must load ${file} exactly once.`);
if (count(pluginMain, "'/includes/servicing/bootstrap.php'") !== 1) fail('Plugin main file must load the servicing bootstrap exactly once.');

const runtimeCommand = 'php scripts/ci/validate-post-booking-servicing-runtime.php';
const contractCommand = 'node scripts/ci/validate-post-booking-servicing-contract.mjs';
for (const [name, workflow] of Object.entries(workflows)) {
  if (count(workflow, runtimeCommand) !== 2) fail(`${name} workflow must run the servicing runtime gate exactly once in each PHP/package validation lane.`);
  if (count(workflow, contractCommand) !== 1) fail(`${name} workflow must run the servicing contract/source gate exactly once.`);
}
if (count(workflows.deploy, 'scripts/ci/validate-post-booking-servicing-*.mjs') !== 1 || count(workflows.deploy, 'scripts/ci/validate-post-booking-servicing-*.php') !== 1) fail('Deploy path filters must include the servicing gates exactly once.');

if (failures.length) {
  console.error('Tra-Vel post-booking servicing contract failed:');
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Tra-Vel post-booking servicing contract passed (3 closed private schemas; ${matrix.scenarios.length} scenarios; evidence/source and zero-side-effect gates).`);
