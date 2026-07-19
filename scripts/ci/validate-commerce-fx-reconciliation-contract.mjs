import { readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const commerce = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'commerce');
const schemaPath = join(root, 'plugin', 'tra-vel-agent-core', 'schemas', 'private', 'commerce-fx-reconciliation-record.schema.json');
const policyPath = join(commerce, 'class-tra-vel-commerce-fx-reconciliation-policy.php');
const statePath = join(commerce, 'class-tra-vel-commerce-fx-reconciliation-state-machine.php');
const runtimePath = join(root, 'scripts', 'ci', 'validate-commerce-fx-reconciliation-runtime.php');
const catalogPath = join(root, 'plugin', 'tra-vel-agent-core', 'assets', 'fixtures', 'commerce-sandbox', 'product-catalog.json');
const networkPath = join(root, 'plugin', 'tra-vel-agent-core', 'assets', 'fixtures', 'commerce-sandbox', 'provider-network.json');
const bootstrapPath = join(commerce, 'bootstrap.php');
const workflowPaths = [
  join(root, '.github', 'workflows', 'theme-ci.yml'),
  join(root, '.github', 'workflows', 'deploy-agent-core.yml'),
];

const read = (path) => readFileSync(path, 'utf8');
const schema = JSON.parse(read(schemaPath));
const policy = read(policyPath);
const state = read(statePath);
const runtime = read(runtimePath);
const catalog = JSON.parse(read(catalogPath));
const network = JSON.parse(read(networkPath));
const bootstrap = read(bootstrapPath);
const workflows = workflowPaths.map(read);
const failures = [];
const fail = (message) => failures.push(message);

if (schema.$id !== 'https://tra-vel.co.il/schemas/private/commerce-fx-reconciliation-record.schema.json') fail('FX schema must retain its canonical private ID.');
if (schema.additionalProperties !== false || schema.properties?.environment?.const !== 'sandbox') fail('FX schema must be root-closed and sandbox-only.');

const expectedRoot = [
  'contract_version', 'environment', 'reconciliation_ref', 'version',
  'previous_snapshot_digest', 'snapshot_digest', 'owner_scope_digest',
  'order_ref', 'order_item_ref', 'funds_flow_binding_digest',
  'idempotency_key_digest', 'source_currency', 'source_exponent',
  'target_currency', 'target_exponent', 'source_rate', 'locked_quote',
  'ledger', 'servicing', 'liabilities', 'event_history', 'created_at',
  'updated_at', 'last_event_sequence', 'sandbox_truth', 'data_boundary',
];
if (JSON.stringify(schema.required) !== JSON.stringify(expectedRoot) || JSON.stringify(Object.keys(schema.properties)) !== JSON.stringify(expectedRoot)) fail('FX schema root must require one exact complete field set.');

function inspectClosedObjects(node, path = '#') {
  if (!node || typeof node !== 'object') return;
  if (node.type === 'object') {
    if (node.additionalProperties !== false) fail(`${path} must reject unknown object properties.`);
    const properties = Object.keys(node.properties || {});
    const required = node.required || [];
    if (JSON.stringify(properties) !== JSON.stringify(required)) fail(`${path} must require every declared property in stable order.`);
  }
  for (const [key, value] of Object.entries(node)) inspectClosedObjects(value, `${path}/${key}`);
}
inspectClosedObjects(schema);

const sourceRateFields = ['source_rate_ref', 'source_rate_version', 'source_rate_digest', 'fixture_label', 'base_currency', 'quote_currency', 'base_exponent', 'quote_exponent', 'numerator', 'denominator', 'observed_at', 'effective_at', 'valid_until', 'simulated', 'real_provider_response'];
if (JSON.stringify(schema.definitions?.sourceRate?.required) !== JSON.stringify(sourceRateFields)) fail('Source-rate evidence must bind identity, version, digest, direction, integer ratio, clocks, and sandbox truth.');
if (schema.definitions?.sourceRate?.properties?.fixture_label?.pattern !== '^SIMULATED_[A-Z0-9_]{8,80}$') fail('Rate fixtures must carry an unmistakable SIMULATED label.');
if (schema.definitions?.sourceRate?.properties?.real_provider_response?.const !== false) fail('Rate evidence must deny a fabricated live-provider response.');

for (const field of ['spread_bps', 'spread_application', 'fee_minor', 'fee_application', 'rounding_mode', 'residual_policy', 'refund_rate_policy', 'reversal_rate_policy', 'dispute_rate_policy', 'chargeback_rate_policy', 'fee_refund_policy']) {
  if (!schema.definitions?.lockedQuote?.required?.includes(field)) fail(`Locked quote must require ${field}.`);
}
for (const field of ['market_target_minor', 'quoted_target_before_residual_minor', 'residual_adjustment_minor', 'target_amount_minor']) {
  if (!schema.definitions?.ledgerLine?.required?.includes(field)) fail(`Every converted ledger line must preserve ${field}.`);
}
for (const field of ['supplier_payable_outstanding_target_minor', 'customer_refund_due_target_minor', 'dispute_exposure_target_minor', 'chargeback_exposure_target_minor', 'fx_fee_liability_target_minor', 'rounding_exposure_target_minor']) {
  if (!schema.definitions?.liabilities?.required?.includes(field)) fail(`Liability projection must retain ${field}.`);
}

const expectedEvents = ['refund_accrued', 'refund_settled', 'reversal_observed', 'dispute_opened', 'dispute_closed', 'chargeback_observed', 'chargeback_recovered', 'supplier_settlement_observed'];
if (JSON.stringify(schema.definitions?.eventEvidence?.properties?.event_type?.enum) !== JSON.stringify(expectedEvents)) fail('FX servicing event taxonomy must cover return, dispute, chargeback, recovery, and supplier settlement evidence.');

for (const marker of [
  "const MAX_MONEY_MINOR  = 1000000000000",
  "'currency_direction_invalid'",
  "'source_rate_stale'",
  "'largest_absolute_then_code'",
  'mul_div_round',
  'cumulative_rounding_adjustment_minor',
  "'original_locked_rate'",
  'assert_successor',
  'project_event_history',
]) {
  if (!policy.includes(marker)) fail(`FX policy is missing required deterministic boundary: ${marker}.`);
}
for (const marker of ["'idempotency_conflict'", "'event_out_of_order'", 'previous_snapshot_digest', 'assert_successor']) {
  if (!state.includes(marker)) fail(`FX state machine is missing required immutable event behavior: ${marker}.`);
}
for (const marker of [
  'SIMULATED_THAI_SETTLEMENT_RATE_V1',
  "'THB'",
  "'ILS'",
  "'numerator'            => 21",
  "'denominator'          => 200",
  "'rounding_residual_minor'",
  'currency_direction_invalid',
  'source_rate_stale',
  'source_rate_digest_invalid',
  'ledger_conversion_overflow',
  'idempotency_conflict',
  'event_out_of_order',
  "'refund_accrued'",
  "'chargeback_observed'",
  "'supplier_settlement_observed'",
]) {
  if (!runtime.includes(marker)) fail(`FX runtime gate is missing adversarial or servicing coverage: ${marker}.`);
}

const forbiddenSideEffects = ['wp_remote_', 'curl_', 'fsockopen', 'stream_socket_client', 'file_get_contents(', 'file_put_contents', '$wpdb', 'register_rest_route', 'add_action('];
for (const marker of forbiddenSideEffects) {
  if ((policy + state).includes(marker)) fail(`Private FX foundation must not contain a network, persistence, processor, REST, or hook side effect: ${marker}.`);
}
for (const liveClaim of ["'environment' => 'production'", "'real_rate_provider_call'   => true", "'real_processor_call'       => true", "'real_supplier_payment'     => true", "'real_settlement'           => true"]) {
  if ((policy + state + runtime).includes(liveClaim)) fail(`FX foundation must not fabricate live authority: ${liveClaim}.`);
}

const products = Array.isArray(catalog.products) ? catalog.products : [];
const providers = Array.isArray(network.providers) ? network.providers : [];
const thbProductProviders = new Set(products.filter((product) => product?.pricing?.currency === 'THB').map((product) => product.provider_id));
const mismatchedProviders = providers.filter((provider) => thbProductProviders.has(provider.provider_id) && provider?.settlement?.currency === 'ILS');
if (thbProductProviders.size === 0 || mismatchedProviders.length === 0) fail('Focused gate must continue to detect the current THB-offer versus ILS-settlement fixture boundary.');

for (const file of ['class-tra-vel-commerce-fx-reconciliation-policy.php', 'class-tra-vel-commerce-fx-reconciliation-state-machine.php']) {
  if (!bootstrap.includes(file)) fail(`Commerce bootstrap must load ${file}.`);
}
for (const workflow of workflows) {
  if (!workflow.includes('php scripts/ci/validate-commerce-fx-reconciliation-runtime.php')) fail('Both CI workflows must run the FX reconciliation runtime gate.');
  if (!workflow.includes('node scripts/ci/validate-commerce-fx-reconciliation-contract.mjs')) fail('Both CI workflows must run the FX reconciliation contract gate.');
}

if (failures.length) {
  console.error('Tra-Vel commerce FX reconciliation contract failed:');
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Tra-Vel commerce FX reconciliation contract passed (closed private schema, ${mismatchedProviders.length} visible THB/ILS provider gaps, integer servicing and no side effects).`);
