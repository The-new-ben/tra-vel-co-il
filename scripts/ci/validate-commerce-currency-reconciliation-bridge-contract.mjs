import { readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const commerce = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'commerce');
const read = (path) => readFileSync(path, 'utf8');
const schema = JSON.parse(read(join(root, 'plugin', 'tra-vel-agent-core', 'schemas', 'private', 'commerce-currency-reconciliation-bridge.schema.json')));
const policy = read(join(commerce, 'class-tra-vel-commerce-currency-reconciliation-bridge-policy.php'));
const factory = read(join(commerce, 'class-tra-vel-commerce-currency-reconciliation-bridge-factory.php'));
const runtime = read(join(root, 'scripts', 'ci', 'validate-commerce-currency-reconciliation-bridge-runtime.php'));
const bootstrap = read(join(commerce, 'bootstrap.php'));
const workflows = [
  read(join(root, '.github', 'workflows', 'theme-ci.yml')),
  read(join(root, '.github', 'workflows', 'deploy-agent-core.yml')),
];
const failures = [];
const fail = (message) => failures.push(message);
const same = (left, right) => JSON.stringify(left) === JSON.stringify(right);

if (schema.$id !== 'https://tra-vel.co.il/schemas/private/commerce-currency-reconciliation-bridge.schema.json') fail('Currency bridge schema must retain its canonical private ID.');
if (schema.additionalProperties !== false || schema.properties?.environment?.const !== 'sandbox') fail('Currency bridge schema must be root-closed and sandbox-only.');
const rootFields = [
  'contract_version', 'environment', 'bridge_ref', 'bridge_binding_digest',
  'snapshot_digest', 'binding', 'currency_bridge', 'source_customer_funds',
  'source_supplier_accrual', 'target_supplier_settlement', 'overall_status',
  'evaluated_at', 'sandbox_truth', 'data_boundary',
];
if (!same(schema.required, rootFields) || !same(Object.keys(schema.properties || {}), rootFields)) fail('Currency bridge root must require one exact stable field set.');

function inspectClosedObjects(node, pointer = '#') {
  if (!node || typeof node !== 'object') return;
  if (node.type === 'object') {
    if (node.additionalProperties !== false) fail(`${pointer} must reject unknown fields.`);
    if (!same(node.required || [], Object.keys(node.properties || {}))) fail(`${pointer} must require every declared field in stable order.`);
  }
  for (const [key, value] of Object.entries(node)) inspectClosedObjects(value, `${pointer}/${key}`);
}
inspectClosedObjects(schema);

const bindingFields = [
  'owner_scope_digest', 'order_ref', 'order_version', 'order_digest',
  'order_item_ref', 'offer_digest', 'routing_binding_digest',
  'funds_flow_ref', 'funds_flow_binding_digest', 'funds_flow_snapshot_digest',
  'fx_reconciliation_ref', 'fx_snapshot_digest',
];
if (!same(schema.definitions?.binding?.required, bindingFields)) fail('Bridge identity must bind exact owner, order, item, routing, funds-flow and current FX digests.');
if (schema.definitions?.currencyBridge?.properties?.bridge_scope?.const !== 'platform_collected_supplier_payable') fail('Version one must pin platform-collected supplier-payable scope.');
if (schema.definitions?.currencyBridge?.properties?.fx_ledger_code?.const !== 'settlement_obligation') fail('Bridge FX role must be an explicit settlement obligation.');
if (schema.definitions?.sourceSupplierAccrual?.properties?.supplier_paid_source_minor?.const !== 0) fail('Schema must make source-currency supplier-paid amount an invariant zero.');
for (const field of ['supplier_payable_target_minor', 'supplier_settled_target_minor', 'supplier_outstanding_target_minor']) {
  if (!schema.definitions?.targetSupplierSettlement?.required?.includes(field)) fail(`Target settlement must retain ${field}.`);
}
for (const field of ['real_rate_provider_call', 'real_processor_call', 'real_customer_charge', 'real_supplier_payment', 'real_settlement', 'external_authority']) {
  if (schema.definitions?.sandboxTruth?.properties?.[field]?.const !== false) fail(`Private bridge must deny ${field}.`);
}

for (const marker of [
  'Tra_Vel_Commerce_Funds_Flow_Policy::validate_snapshot',
  'Tra_Vel_Commerce_Fx_Reconciliation_Policy::validate_snapshot',
  "'cross_ledger_identity_mismatch'",
  "'currency_direction_mismatch'",
  "'platform_collected_supplier_payable'",
  "'supplier_payable_component_mismatch'",
  "0 !== $settlement['supplier_paid_minor']",
  "'supplier_paid_source_minor'        => 0",
  "'ready_for_target_settlement'",
  "'partially_target_settled'",
  "'partially_returned'",
  "'target_settled'",
  'funds_flow_snapshot_digest',
  'fx_snapshot_digest',
  'routing_binding_digest',
  'snapshot_digest(',
]) {
  if (!policy.includes(marker)) fail(`Currency bridge policy is missing invariant: ${marker}.`);
}
for (const marker of [
  'expected_binding', 'current_binding_mismatch', 'funds_flow_snapshot_digest',
  'fx_snapshot_digest', 'Tra_Vel_Commerce_Currency_Reconciliation_Bridge_Policy::seal_snapshot',
]) {
  if (!factory.includes(marker)) fail(`Currency bridge factory is missing current-binding behavior: ${marker}.`);
}
for (const marker of [
  'currency_bridge_assertions', 'waiting_customer_funds', 'waiting_source_accrual',
  'ready_for_target_settlement', 'partially_target_settled', 'target_settled',
  'stale current funds-flow digest', 'stale current FX digest', 'another owner',
  'another order', 'another item', 'different immutable funds flow',
  'cannot differ by one minor unit', 'cannot mix another converted component',
  'target payment must never populate a source paid amount',
  'Target settlement must never be copied into source-currency paid fields',
  'supplier-collected and affiliate scope', 'Credential-shaped material',
]) {
  if (!runtime.includes(marker)) fail(`Currency bridge runtime is missing focused proof: ${marker}.`);
}

for (const marker of ['wp_remote_', 'curl_', 'fsockopen', 'stream_socket_client', 'file_get_contents(', 'file_put_contents', '$wpdb', 'register_rest_route', 'add_action(', 'update_option(', 'wp_insert_post(']) {
  if ((policy + factory).includes(marker)) fail(`Currency bridge cannot contain network, persistence, REST, hook, or external-work side effects: ${marker}.`);
}
for (const marker of ["'real_rate_provider_call'  => true", "'real_processor_call'      => true", "'real_customer_charge'     => true", "'real_supplier_payment'    => true", "'real_settlement'          => true", "'external_authority'       => true"]) {
  if ((policy + factory + runtime).includes(marker)) fail(`Currency bridge cannot fabricate authority: ${marker}.`);
}
for (const file of ['class-tra-vel-commerce-currency-reconciliation-bridge-policy.php', 'class-tra-vel-commerce-currency-reconciliation-bridge-factory.php']) {
  if (!bootstrap.includes(file)) fail(`Commerce bootstrap must load ${file}.`);
}
for (const workflow of workflows) {
  if (!workflow.includes('php scripts/ci/validate-commerce-currency-reconciliation-bridge-runtime.php')) fail('Both required workflows must run the currency bridge runtime gate.');
  if (!workflow.includes('node scripts/ci/validate-commerce-currency-reconciliation-bridge-contract.mjs')) fail('Both required workflows must run the currency bridge contract gate.');
}

if (failures.length) {
  console.error('Tra-Vel currency reconciliation bridge contract failed:');
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Tra-Vel currency reconciliation bridge contract passed (${bindingFields.length} exact binding fields; closed private projection; no side effects).`);
