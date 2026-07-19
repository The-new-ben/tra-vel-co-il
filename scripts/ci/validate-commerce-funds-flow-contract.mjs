import {readFileSync} from 'node:fs';
import {join, resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const commerce = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'commerce');
const read = path => readFileSync(path, 'utf8');
const failures = [];
const requireText = (source, needle, message) => {
  if (!source.includes(needle)) failures.push(message);
};
const sameSet = (left, right) => left.length === right.length && [...left].sort().every((value, index) => value === [...right].sort()[index]);

const schemaPath = join(root, 'plugin', 'tra-vel-agent-core', 'schemas', 'private', 'commerce-funds-flow-record.schema.json');
const schema = JSON.parse(read(schemaPath));
const policy = read(join(commerce, 'class-tra-vel-commerce-funds-flow-policy.php'));
const stateMachine = read(join(commerce, 'class-tra-vel-commerce-funds-flow-state-machine.php'));
const bootstrap = read(join(commerce, 'bootstrap.php'));
const runtime = read(join(root, 'scripts', 'ci', 'validate-commerce-funds-flow-runtime.php'));
const workflows = [
  read(join(root, '.github', 'workflows', 'theme-ci.yml')),
  read(join(root, '.github', 'workflows', 'deploy-agent-core.yml')),
];

if (schema.$schema !== 'http://json-schema.org/draft-07/schema#') failures.push('Funds-flow schema must use JSON Schema Draft-07.');
if (schema.$id !== 'https://tra-vel.co.il/schemas/private/commerce-funds-flow-record.schema.json') failures.push('Funds-flow schema must have its canonical private ID.');
if (schema.additionalProperties !== false) failures.push('Funds-flow schema root must be closed.');
if (schema.properties?.contract_version?.const !== '1.0.0' || schema.properties?.environment?.const !== 'sandbox') failures.push('Funds-flow schema must pin contract 1.0.0 to sandbox.');
if (!sameSet(schema.required || [], Object.keys(schema.properties || {}))) failures.push('Funds-flow schema must require every root property exactly.');
for (const field of ['order_ref', 'order_item_ref', 'offer_digest', 'routing_binding_digest', 'commercial_model', 'parties', 'commercial_terms', 'pricing', 'payment', 'settlement', 'liabilities', 'private_routes', 'sandbox_truth', 'data_boundary']) {
  if (!schema.required?.includes(field)) failures.push(`Funds-flow schema is missing ${field}.`);
}

const forbiddenProperties = /^(?:api_?key|secret|password|bearer|access_?token|refresh_?token|private_?key|card_?number|pan|cvv|cvc|iban|passport|medical|email|phone|traveler_?name|full_?name)$/i;
const visit = (value, pointer = '#') => {
  if (!value || typeof value !== 'object') return;
  if (!Array.isArray(value) && value.type === 'object' && value.additionalProperties !== false) failures.push(`Funds-flow object ${pointer} is open to unknown fields.`);
  if (!Array.isArray(value) && value.properties) {
    for (const property of Object.keys(value.properties)) {
      if (forbiddenProperties.test(property)) failures.push(`Funds-flow schema accepts sensitive property ${pointer}/properties/${property}.`);
    }
  }
  if (Array.isArray(value)) value.forEach((item, index) => visit(item, `${pointer}/${index}`));
  else Object.entries(value).forEach(([key, item]) => visit(item, `${pointer}/${key}`));
};
visit(schema);

const projectionStart = policy.indexOf('public static function public_projection');
const projectionEnd = policy.indexOf('public static function assert_successor', projectionStart);
const projectionSource = projectionStart >= 0 && projectionEnd > projectionStart ? policy.slice(projectionStart, projectionEnd) : '';
const projected = [...projectionSource.matchAll(/^\s*'([^']+)'\s*=>/gm)].map(match => match[1]);
const safeProjection = ['contract_version', 'environment', 'order_ref', 'order_version', 'order_item_ref', 'currency', 'funds_flow_binding_digest', 'snapshot_digest', 'rate_card_revision_digest', 'source_revision_digest', 'supplier_config_revision_digest', 'payment_state', 'settlement_state', 'updated_at'];
if (!sameSet(projected, safeProjection)) failures.push('Funds-flow public projection must remain the exact digest-and-state allowlist.');

for (const needle of [
  "const MODELS = array( 'affiliate_handoff', 'direct_commission', 'net_rate_markup' )",
  'funds_flow_binding_digest', 'previous_snapshot_digest', 'rate_card_revision_digest',
  'source_revision_digest', 'supplier_config_revision_digest', 'merchant_of_record',
  'payment_collector', 'refund_liability_party', 'chargeback_liability_party',
  'customer_refund_due_minor', 'supplier_payable_outstanding_minor',
  'commission_receivable_outstanding_minor', 'contains_sensitive_material(',
  'assert_successor(', 'real_processor_call', 'public_serialization_allowed',
]) requireText(policy, needle, `Funds-flow policy is missing ${needle}.`);

for (const needle of [
  'request_authorization', 'authorize', 'capture', 'refund', 'open_dispute',
  'record_chargeback', 'mark_uncertain', 'apply_settlement_event',
  'settlement_overpayment', 'previous_snapshot_digest',
]) requireText(stateMachine, needle, `Funds-flow state machine is missing ${needle}.`);

for (const source of [policy, stateMachine]) {
  for (const call of ['register_rest_route(', 'wp_remote_get(', 'wp_remote_post(', 'wp_remote_request(', 'curl_exec(', '->dispatch(', '->charge(', '->capture(']) {
    if (source.includes(call)) failures.push(`Evidence-only funds flow must not perform external work: ${call}`);
  }
}

for (const file of ['class-tra-vel-commerce-funds-flow-policy.php', 'class-tra-vel-commerce-funds-flow-state-machine.php']) {
  requireText(bootstrap, file, `Commerce bootstrap does not load ${file}.`);
}
for (const proof of ['affiliate_handoff', 'direct_commission', 'net_rate_markup', 'over-capture', 'over-refund', 'chargeback', 'Partial supplier settlement', 'Public projection', 'sensitive_material_rejected']) {
  requireText(runtime.toLowerCase(), proof.toLowerCase(), `Funds-flow runtime is missing adversarial proof ${proof}.`);
}
for (const workflow of workflows) {
  requireText(workflow, 'php scripts/ci/validate-commerce-funds-flow-runtime.php', 'Both CI workflows must run the funds-flow runtime gate.');
  requireText(workflow, 'node scripts/ci/validate-commerce-funds-flow-contract.mjs', 'Both CI workflows must run the funds-flow contract gate.');
}

if (failures.length) {
  console.error('Commerce funds-flow contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Commerce funds-flow contract passed (${safeProjection.length} safe projection fields; closed private ledger; processor boundary scanned).`);
