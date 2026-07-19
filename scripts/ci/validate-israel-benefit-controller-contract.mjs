import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const read = (path) => readFileSync(path, 'utf8');
const commerce = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'commerce');
const controller = read(join(commerce, 'class-tra-vel-israel-benefit-controller.php'));
const registry = read(join(commerce, 'class-tra-vel-israel-benefit-catalog-registry.php'));
const bootstrap = read(join(commerce, 'bootstrap.php'));
const entrypoint = read(join(root, 'plugin', 'tra-vel-agent-core', 'tra-vel-agent-core.php'));
const runtime = read(join(root, 'scripts', 'ci', 'validate-israel-benefit-controller-runtime.php'));
const workflows = [
  read(join(root, '.github', 'workflows', 'theme-ci.yml')),
  read(join(root, '.github', 'workflows', 'deploy-agent-core.yml')),
];

const failures = [];
const requireText = (haystack, needle, message) => {
  if (!haystack.includes(needle)) failures.push(message);
};

for (const marker of [
  'final class Tra_Vel_Israel_Benefit_Controller extends WP_REST_Controller',
  "WP_REST_Server::READABLE",
  "WP_REST_Server::CREATABLE",
  "'permission_callback' => '__return_true'",
  "'permission_callback' => array( $this, 'can_plan' )",
  "'benefits/israel'",
  "'/' . $this->rest_base . '/options'",
  "'/' . $this->rest_base . '/plan'",
  "'side_effect_executed'] = false",
  "'Cache-Control', 'private, no-store, max-age=0'",
  "'X-Robots-Tag', 'noindex, nofollow, noarchive'",
  "array_diff( array_keys( $body ), $keys )",
  "get_json_params()",
  "get_param( $key )",
]) requireText(controller, marker, `Israeli benefit controller is missing ${marker}.`);

for (const marker of [
  'public function public_options( $evaluated_at_utc )',
  "'airline_inventory' => $airlines",
  "'programs'          => $programs",
  "'payment_networks'   => $networks",
  "'credential_products'=> $credentials",
  "'redemption_portals' => $portals",
  "'campaign_versions'  => $campaigns",
  "'provider_verified_eligibility' => false",
  "'live_balance'                  => false",
  "'live_inventory'                => false",
  "'live_price'                    => false",
  "'live_discount'                 => false",
  "'live_redemption'               => false",
  "'live_checkout'                 => false",
]) requireText(registry, marker, `Israeli benefit public options are missing ${marker}.`);

for (const forbidden of [
  'wp_remote_get(', 'wp_remote_post(', 'wp_remote_request(', 'curl_exec(',
  'update_option(', 'add_option(', 'delete_option(', '$wpdb', 'setcookie(',
  '->charge(', '->capture(', '->redeem(', '->book(', '->reserve(', '->dispatch(',
]) {
  if (controller.includes(forbidden)) failures.push(`Israeli benefit REST planning must not execute live or durable work: ${forbidden}`);
}

requireText(bootstrap, "class-tra-vel-israel-benefit-controller.php", 'Commerce bootstrap does not load the Israeli benefit controller.');
requireText(entrypoint, '( new Tra_Vel_Israel_Benefit_Controller() )->register_routes();', 'Agent Core does not register the Israeli benefit routes.');

for (const proof of [
  'exactly two benefit routes must register',
  'generic Visa identity must never imply eligibility',
  'unknown or secret-bearing request fields must fail closed',
  'stale source review must be explicit and require refresh before planning',
  'stable benefit identities must remain visible when source review expires',
  'signed-in planning must require a REST nonce',
  'planning must execute no supplier or financial side effect',
]) requireText(runtime, proof, `Israeli benefit controller runtime is missing adversarial proof: ${proof}.`);

for (const workflow of workflows) {
  requireText(workflow, 'php scripts/ci/validate-israel-benefit-controller-runtime.php', 'Both CI workflows must run the Israeli benefit controller runtime gate.');
  requireText(workflow, 'node scripts/ci/validate-israel-benefit-controller-contract.mjs', 'Both CI workflows must run the Israeli benefit controller contract gate.');
}

if (failures.length) {
  console.error('Israeli benefit controller contract validation failed:');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('Israeli benefit controller contract passed (two explicit routes; exact axes; no live side effects).');
