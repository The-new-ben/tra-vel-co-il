import {readFileSync} from 'node:fs';
import {join, resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const commerce = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'commerce');
const read = path => readFileSync(path, 'utf8');
const taxonomy = read(join(commerce, 'class-tra-vel-commerce-taxonomy.php'));
const money = read(join(commerce, 'class-tra-vel-commerce-money.php'));
const policy = read(join(commerce, 'class-tra-vel-commerce-policy.php'));
const stateMachine = read(join(commerce, 'class-tra-vel-commerce-state-machine.php'));
const registry = read(join(commerce, 'class-tra-vel-commerce-provider-registry.php'));
const bootstrap = read(join(commerce, 'bootstrap.php'));
const vipBootstrap = read(join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'vip', 'bootstrap.php'));
const localTourismBootstrap = read(join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'local-tourism', 'bootstrap.php'));
const plugin = read(join(root, 'plugin', 'tra-vel-agent-core', 'tra-vel-agent-core.php'));
const themeCi = read(join(root, '.github', 'workflows', 'theme-ci.yml'));
const agentDeploy = read(join(root, '.github', 'workflows', 'deploy-agent-core.yml'));
const failures = [];

const requireText = (source, needle, message) => {
  if (!source.includes(needle)) failures.push(message);
};

for (const vertical of ['flight', 'accommodation', 'package', 'transfer', 'activity', 'dining', 'insurance', 'connectivity', 'equipment']) {
  requireText(taxonomy, `'${vertical}'`, `Canonical commerce vertical is missing: ${vertical}.`);
}
for (const capability of ['search', 'revalidate', 'reserve', 'confirm', 'fulfill', 'change', 'cancel', 'refund', 'payment_authorize', 'payment_capture', 'payment_void', 'payment_refund', 'webhook', 'reconcile', 'report_conversion', 'settlement_reconcile']) {
  requireText(taxonomy, `'${capability}'`, `Canonical provider capability is missing: ${capability}.`);
}
for (const state of ['awaiting_approval', 'requires_action', 'reconciliation_required', 'conversion_reported', 'uncertain', 'reconciled']) {
  requireText(taxonomy, `'${state}'`, `Parallel commerce state vocabulary is missing: ${state}.`);
}
requireText(taxonomy, "'hotel'      => 'accommodation'", 'Legacy hotel alias is not explicit.');
requireText(taxonomy, "'esim'       => 'connectivity'", 'Legacy eSIM alias is not explicit.');
if (/['"]car['"]\s*=>/.test(taxonomy)) failures.push('Car must not be silently mapped into an unrelated canonical vertical.');

for (const needle of [
  'is_int( $value )',
  '$right > PHP_INT_MAX - $left',
  "array( 'code', 'amount_minor', 'currency' ) !== array_keys( $line )",
  "'total_minor' => $total",
]) requireText(money, needle, `Integer money boundary is missing ${needle}.`);
if (/is_numeric|floatval|\(float\)/.test(money)) failures.push('Commerce money primitives must not coerce floating-point values.');

for (const needle of [
  "const CONTRACT_VERSION = '1.0.0'",
  "array( 'sandbox', 'live' )",
  "array( 'owned', 'direct', 'affiliate' )",
  "true !== $truth['simulated'] || $truth['real_charge'] || $truth['real_booking']",
  "'payment_capture' => array( 'payment_authorize' )",
  "'payment_refund'  => array( 'payment_capture' )",
  "in_array( 'insurance', $verticals, true )",
  "DateTimeImmutable( $value )",
  "hash( 'sha256', wp_json_encode",
]) requireText(policy, needle, `Closed provider policy is missing ${needle}.`);

for (const needle of [
  "'payment' => array(",
  "'fulfillment' => array(",
  "'settlement' => array(",
  "'operation' => array(",
  "'timeout' => 'uncertain'",
  "'reconciliation_required'",
  "return 'trip_confirmed'",
]) requireText(stateMachine, needle, `Independent commerce state machine is missing ${needle}.`);

for (const [file, iface] of [
  ['interface-tra-vel-commerce-search-adapter.php', 'Tra_Vel_Commerce_Search_Adapter'],
  ['interface-tra-vel-commerce-quote-adapter.php', 'Tra_Vel_Commerce_Quote_Adapter'],
  ['interface-tra-vel-commerce-fulfillment-adapter.php', 'Tra_Vel_Commerce_Fulfillment_Adapter'],
  ['interface-tra-vel-commerce-webhook-adapter.php', 'Tra_Vel_Commerce_Webhook_Adapter'],
  ['interface-tra-vel-commerce-reconciliation-adapter.php', 'Tra_Vel_Commerce_Reconciliation_Adapter'],
  ['interface-tra-vel-commerce-payment-adapter.php', 'Tra_Vel_Commerce_Payment_Adapter'],
  ['interface-tra-vel-commerce-settlement-adapter.php', 'Tra_Vel_Commerce_Settlement_Adapter'],
  ['interface-tra-vel-commerce-affiliate-reporter.php', 'Tra_Vel_Commerce_Affiliate_Reporter'],
]) requireText(read(join(commerce, file)), `interface ${iface}`, `Capability-specific adapter interface is missing: ${iface}.`);

for (const needle of [
  "'revalidate' => 'Tra_Vel_Commerce_Quote_Adapter'",
  "'reserve'    => 'Tra_Vel_Commerce_Fulfillment_Adapter'",
  "'fulfill'    => 'Tra_Vel_Commerce_Fulfillment_Adapter'",
  "'webhook'             => 'Tra_Vel_Commerce_Webhook_Adapter'",
  "'reconcile'           => 'Tra_Vel_Commerce_Reconciliation_Adapter'",
  "'payment_authorize'   => 'Tra_Vel_Commerce_Payment_Adapter'",
  "'payment_refund'      => 'Tra_Vel_Commerce_Payment_Adapter'",
  "'settlement_reconcile' => 'Tra_Vel_Commerce_Settlement_Adapter'",
  'Tra_Vel_Commerce_Affiliate_Reporter',
  "usort( $descriptors, array( __CLASS__, 'compare_descriptors' ) )",
  "strcmp( $left['provider_id'], $right['provider_id'] )",
]) requireText(registry, needle, `Deterministic provider registry is missing ${needle}.`);

requireText(plugin, "require_once TRA_VEL_AGENT_PATH . '/includes/commerce/bootstrap.php'", 'Agent Core does not load Commerce Core.');
requireText(plugin, "require_once TRA_VEL_AGENT_PATH . '/includes/vip/bootstrap.php'", 'Agent Core does not load the VIP service contracts.');
requireText(plugin, "require_once TRA_VEL_AGENT_PATH . '/includes/local-tourism/bootstrap.php'", 'Agent Core does not load the local-tourism and map contracts.');
for (const file of [
  'class-tra-vel-commerce-taxonomy.php', 'class-tra-vel-commerce-money.php', 'class-tra-vel-commerce-policy.php',
  'class-tra-vel-commerce-state-machine.php',
  'interface-tra-vel-commerce-search-adapter.php', 'interface-tra-vel-commerce-quote-adapter.php',
  'interface-tra-vel-commerce-fulfillment-adapter.php', 'interface-tra-vel-commerce-payment-adapter.php',
  'interface-tra-vel-commerce-webhook-adapter.php', 'interface-tra-vel-commerce-reconciliation-adapter.php',
  'interface-tra-vel-commerce-settlement-adapter.php', 'interface-tra-vel-commerce-affiliate-reporter.php',
  'class-tra-vel-commerce-provider-registry.php', 'class-tra-vel-commerce-sandbox-network.php',
  'class-tra-vel-commerce-sandbox-catalog.php', 'class-tra-vel-commerce-search-engine.php',
  'class-tra-vel-commerce-package-composer.php',
	'class-tra-vel-commerce-atomic-revalidator.php',
	'class-tra-vel-commerce-order-factory.php',
	'class-tra-vel-commerce-operation-factory.php',
	'class-tra-vel-supplier-operations-taxonomy.php', 'class-tra-vel-supplier-operations-policy.php',
	'class-tra-vel-supplier-operations-state-machine.php',
	'class-tra-vel-commerce-private-routing-registry.php',
	'class-tra-vel-commerce-funds-flow-policy.php',
	'class-tra-vel-commerce-funds-flow-state-machine.php',
	'class-tra-vel-benefit-taxonomy.php', 'class-tra-vel-benefit-policy.php',
]) requireText(bootstrap, file, `Commerce bootstrap does not load ${file}.`);
for (const file of ['class-tra-vel-vip-taxonomy.php', 'class-tra-vel-vip-state-machine.php', 'class-tra-vel-vip-policy.php']) {
  requireText(vipBootstrap, file, `VIP bootstrap does not load ${file}.`);
}
for (const file of ['class-tra-vel-local-tourism-taxonomy.php', 'class-tra-vel-local-tourism-policy.php', 'class-tra-vel-local-map-state-machine.php']) {
  requireText(localTourismBootstrap, file, `Local-tourism bootstrap does not load ${file}.`);
}

for (const workflow of [themeCi, agentDeploy]) {
  requireText(workflow, 'php scripts/ci/validate-commerce-core-runtime.php', 'A required pipeline does not run Commerce Core runtime validation.');
  requireText(workflow, 'php scripts/ci/validate-commerce-state-machine-runtime.php', 'A required pipeline does not run Commerce state-machine runtime validation.');
  requireText(workflow, 'php scripts/ci/validate-commerce-sandbox-network-runtime.php', 'A required pipeline does not run the seeded provider-network validation.');
  requireText(workflow, 'php scripts/ci/validate-commerce-search-runtime.php', 'A required pipeline does not run deterministic commerce search validation.');
  requireText(workflow, 'php scripts/ci/validate-commerce-package-runtime.php', 'A required pipeline does not run atomic package-composition validation.');
  requireText(workflow, 'php scripts/ci/validate-commerce-revalidation-runtime.php', 'A required pipeline does not run atomic package-revalidation validation.');
  requireText(workflow, 'php scripts/ci/validate-commerce-order-runtime.php', 'A required pipeline does not run checkout-boundary order validation.');
  requireText(workflow, 'php scripts/ci/validate-commerce-operation-runtime.php', 'A required pipeline does not run version-bound operation validation.');
  requireText(workflow, 'php scripts/ci/validate-commerce-private-routing-runtime.php', 'A required pipeline does not run server-only private routing validation.');
  requireText(workflow, 'php scripts/ci/validate-commerce-funds-flow-runtime.php', 'A required pipeline does not run private per-item funds-flow validation.');
  requireText(workflow, 'php scripts/ci/validate-commerce-search-schema-conformance.php', 'A required pipeline does not run Commerce search/schema conformance validation.');
  requireText(workflow, 'php scripts/ci/validate-benefit-engine-runtime.php', 'A required pipeline does not run the benefit-engine runtime validation.');
  requireText(workflow, 'php scripts/ci/validate-vip-service-runtime.php', 'A required pipeline does not run the VIP-service runtime validation.');
  requireText(workflow, 'php scripts/ci/validate-vip-intake-runtime.php', 'A required pipeline does not run privacy-minimized no-login VIP intake validation.');
  requireText(workflow, 'php scripts/ci/validate-local-tourism-runtime.php', 'A required pipeline does not run the local-tourism runtime validation.');
  requireText(workflow, 'php scripts/ci/validate-supplier-operations-runtime.php', 'A required pipeline does not run supplier-operations runtime validation.');
  requireText(workflow, 'php scripts/ci/validate-supplier-profile-seeds-runtime.php', 'A required pipeline does not run the exact provider-to-supplier profile conformance gate.');
  requireText(workflow, 'node scripts/ci/validate-commerce-core-contract.mjs', 'A required pipeline does not run Commerce Core contract validation.');
  requireText(workflow, 'node scripts/ci/validate-commerce-schema-contract.mjs', 'A required pipeline does not run closed commerce schema validation.');
  requireText(workflow, 'node scripts/ci/validate-commerce-private-routing-contract.mjs', 'A required pipeline does not run the private routing contract and public-boundary scan.');
  requireText(workflow, 'node scripts/ci/validate-commerce-funds-flow-contract.mjs', 'A required pipeline does not run the private funds-flow contract gate.');
  requireText(workflow, 'node scripts/ci/validate-benefit-engine-contract.mjs', 'A required pipeline does not run closed benefit-engine schema validation.');
  requireText(workflow, 'node scripts/ci/validate-vip-service-contract.mjs', 'A required pipeline does not run closed VIP-service schema validation.');
  requireText(workflow, 'node scripts/ci/validate-vip-intake-contract.mjs', 'A required pipeline does not run the closed no-login VIP intake schema validation.');
  requireText(workflow, 'node scripts/ci/validate-local-tourism-contract.mjs', 'A required pipeline does not run closed local-tourism schema validation.');
  requireText(workflow, 'node scripts/ci/validate-supplier-operations-contract.mjs', 'A required pipeline does not run closed supplier-operations schema validation.');
}

if (failures.length) {
  console.error('Commerce Core contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Commerce Core contract passed (taxonomy, integer money, provider truth, capability interfaces, deterministic registry, and CI gates).');
