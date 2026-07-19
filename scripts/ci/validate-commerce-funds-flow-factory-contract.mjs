import {readFileSync} from 'node:fs';
import {join, resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const commerce = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'commerce');
const read = path => readFileSync(path, 'utf8');
const failures = [];
const requireText = (source, needle, message) => {
  if (!source.includes(needle)) failures.push(message);
};

const factory = read(join(commerce, 'class-tra-vel-commerce-funds-flow-factory.php'));
const bootstrap = read(join(commerce, 'bootstrap.php'));
const runtime = read(join(root, 'scripts', 'ci', 'validate-commerce-funds-flow-factory-runtime.php'));
const workflows = [
  read(join(root, '.github', 'workflows', 'theme-ci.yml')),
  read(join(root, '.github', 'workflows', 'deploy-agent-core.yml')),
];

for (const marker of [
  'create_initial_snapshot(',
  'commercial_configuration_digest(',
  'reconcile_provider_and_profile(',
  'provider_network_signature',
  'provider_descriptor_digest',
  'supplier_config_revision_digest',
  'product_revision_digest',
  'rate_revision_digest',
  'availability_revision_digest',
  'terms_revision_digest',
  'offer_evidence_digest',
  'routing_binding_digest',
  'idempotency_key_digest',
  'commercial_currency_mismatch',
  'affiliate_handoff',
  'direct_commission',
  'net_rate_markup',
  'merchant_of_record',
  'payment_collector',
  'chargeback_liability_party',
  'hash_hmac(',
  'seal_snapshot(',
  'validate_snapshot(',
  "'real_processor_call'   => false",
  "'real_customer_charge'  => false",
  "'real_supplier_payment' => false",
]) requireText(factory, marker, `Funds-flow factory is missing ${marker}.`);

for (const call of ['register_rest_route(', 'wp_remote_get(', 'wp_remote_post(', 'wp_remote_request(', 'curl_exec(', 'update_option(', 'add_option(', '->query(', '->dispatch(', '->charge(', '->capture(', '->reserve(']) {
  if (factory.includes(call)) failures.push(`Funds-flow factory must remain deterministic and side-effect free: ${call}`);
}
requireText(bootstrap, 'class-tra-vel-commerce-funds-flow-factory.php', 'Commerce bootstrap does not load the funds-flow factory.');

for (const proof of [
  'THB offer cannot silently use the current ILS settlement configuration',
  'Platform-collected commission',
  'Supplier-collected direct commission',
  'Net rate freezes supplier net and markup separately',
  'Affiliate handoff records commission only',
  'different idempotency digest',
  'wrong-offer-digest',
  'must not invent',
]) requireText(runtime, proof, `Funds-flow factory runtime is missing proof ${proof}.`);

for (const workflow of workflows) {
  requireText(workflow, 'php scripts/ci/validate-commerce-funds-flow-factory-runtime.php', 'Both CI workflows must run the funds-flow factory runtime gate.');
  requireText(workflow, 'node scripts/ci/validate-commerce-funds-flow-factory-contract.mjs', 'Both CI workflows must run the funds-flow factory contract gate.');
}

if (failures.length) {
  console.error('Commerce funds-flow factory contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Commerce funds-flow factory contract passed (exact item/offer/route/revision reconciliation; 3 economic models; no external side effects).');
