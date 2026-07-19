import {readFileSync} from 'node:fs';
import {join, resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const commerce = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'commerce');
const read = path => readFileSync(path, 'utf8');
const failures = [];
const requireText = (source, needle, message) => {
  if (!source.includes(needle)) failures.push(message);
};

const fixturePath = join(root, 'plugin', 'tra-vel-agent-core', 'assets', 'fixtures', 'israel-benefit-catalog.json');
const fixture = JSON.parse(read(fixturePath));
const registry = read(join(commerce, 'class-tra-vel-israel-benefit-catalog-registry.php'));
const bootstrap = read(join(commerce, 'bootstrap.php'));
const runtime = read(join(root, 'scripts', 'ci', 'validate-israel-benefit-catalog-runtime.php'));
const workflows = [
  read(join(root, '.github', 'workflows', 'theme-ci.yml')),
  read(join(root, '.github', 'workflows', 'deploy-agent-core.yml')),
];
const sameSet = (left, right) => left.length === right.length && [...left].sort().every((value, index) => value === [...right].sort()[index]);

const exactRoot = ['contract_version', 'catalog_id', 'observed_at_utc', 'fresh_until_utc', 'airline_inventory', 'programs', 'payment_networks', 'credential_products', 'redemption_portals', 'campaign_versions', 'program_portal_links', 'credential_program_links', 'portal_inventory_links', 'migrations', 'commercial_truth'];
if (!sameSet(Object.keys(fixture), exactRoot)) failures.push('Israeli benefit fixture root is not the exact closed catalogue shape.');
if (fixture.contract_version !== '1.0.0' || fixture.catalog_id !== 'israel_benefits_2026_07_19') failures.push('Israeli benefit catalogue identity is not pinned.');
const expectedCounts = {airline_inventory: 3, programs: 3, payment_networks: 4, credential_products: 8, redemption_portals: 3, campaign_versions: 4, migrations: 1};
for (const [collection, count] of Object.entries(expectedCounts)) {
  if (!Array.isArray(fixture[collection]) || fixture[collection].length !== count) failures.push(`Israeli benefit ${collection} must contain exactly ${count} source-catalogued records.`);
}

const ids = value => new Set((value || []).map(item => item.airline_inventory_id || item.program_id || item.payment_network_id || item.credential_product_id || item.redemption_portal_id || item.campaign_id || item.migration_id));
const airlineIds = ids(fixture.airline_inventory);
const programIds = ids(fixture.programs);
const networkIds = ids(fixture.payment_networks);
for (const id of ['airline_el_al', 'airline_arkia', 'airline_israir']) if (!airlineIds.has(id)) failures.push(`Israeli benefit catalogue is missing ${id}.`);
for (const id of ['program_elal_matmid', 'program_cal_flyall', 'program_max_skymax']) if (!programIds.has(id)) failures.push(`Israeli benefit catalogue is missing independent program ${id}.`);
for (const id of ['network_mastercard', 'network_american_express', 'network_diners_mastercard_combined', 'network_visa']) if (!networkIds.has(id)) failures.push(`Israeli benefit catalogue is missing payment-rail identity ${id}.`);
const visa = fixture.payment_networks.find(item => item.payment_network_id === 'network_visa');
if (visa?.scope !== 'network_identity_only') failures.push('Visa must remain a network identity and never prove issuer/card/campaign eligibility.');
if (fixture.credential_products.some(item => item.network_id === 'network_visa')) failures.push('A generic Visa network identity must not masquerade as an exact credential product.');
if (fixture.programs.some(item => /arkia/i.test(item.program_id))) failures.push('Arkia inventory must not create a fabricated Arkia loyalty program.');
if (fixture.programs.some(item => /israir/i.test(item.program_id))) failures.push('Israir inventory must not create a fabricated Israir loyalty program.');
if (!fixture.portal_inventory_links.some(link => link.redemption_portal_id === 'portal_flyall' && link.airline_inventory_id === 'airline_israir' && link.relationship === 'source_catalogued_inventory_scope' && link.commercial_truth?.live_availability === false && link.commercial_truth?.airline_loyalty_program_implied === false)) failures.push('FlyAll must catalogue Israir only as non-live portal inventory scope.');
if (fixture.migrations[0]?.transition_window?.to_utc !== '2026-12-31T23:59:59Z' || fixture.migrations[0]?.accrual_cutover_at_utc !== '2027-01-01T00:00:00Z') failures.push('The Cal FLY CARD/FlyAll transition and accrual cutover must remain explicit.');

const forbiddenKeys = /^(?:password|passcode|pan|card_number|cvv|cvc|otp|access_token|refresh_token|member_number|customer_id|price_minor|discount_minor|rate_bps|balance_amount)$/i;
const officialHosts = new Set(['www.elal.com', 'www.arkia.co.il', 'www.israir.co.il', 'www.cal-online.co.il', 'www.max.co.il', 'www.isracard.co.il', 'digital.isracard.co.il', 'www.visa.co.il']);
const seenUrls = new Set();
const visit = (value, pointer = '#') => {
  if (Array.isArray(value)) return value.forEach((item, index) => visit(item, `${pointer}/${index}`));
  if (!value || typeof value !== 'object') return;
  for (const [key, child] of Object.entries(value)) {
    if (forbiddenKeys.test(key)) failures.push(`Israeli benefit catalogue accepts prohibited field ${pointer}/${key}.`);
    if (key === 'official_source_url') {
      try {
        const url = new URL(child);
        if (url.protocol !== 'https:' || !officialHosts.has(url.hostname)) failures.push(`Israeli benefit source is not on the official host allowlist: ${child}`);
        seenUrls.add(child);
      } catch {
        failures.push(`Israeli benefit source URL is invalid at ${pointer}/${key}.`);
      }
    }
    visit(child, `${pointer}/${key}`);
  }
};
visit(fixture);
if (seenUrls.size < 7) failures.push('Israeli benefit catalogue lacks diverse first-party source evidence.');
for (const [key, value] of Object.entries(fixture.commercial_truth || {})) if (value !== false) failures.push(`Israeli benefit catalogue falsely asserts live commercial truth ${key}.`);

for (const marker of [
  'function plan(',
  'generic_visa_eligibility_forbidden',
  'generic_fly_card_eligibility_forbidden',
  'credential_program_conflict',
  'program_portal_conflict',
  'portal_inventory_scope_unknown',
  'campaign_revision_not_current',
  'campaign_revision_conflict',
  'choose_exact_issuer_card_campaign',
  'verify_eligibility_with_provider',
  'live_eligibility_not_verified',
  'source_current(',
  'catalog_digest(',
]) requireText(registry, marker, `Israeli benefit registry is missing ${marker}.`);
for (const call of ['register_rest_route(', 'wp_remote_get(', 'wp_remote_post(', 'wp_remote_request(', 'curl_exec(', '->redeem(', '->charge(', '->book(']) {
  if (registry.includes(call)) failures.push(`Israeli benefit catalogue must not claim or execute live work: ${call}`);
}
requireText(bootstrap, 'class-tra-vel-israel-benefit-catalog-registry.php', 'Commerce bootstrap does not load the Israeli benefit catalogue registry.');
for (const proof of ['generic Visa logo', 'generic FLY CARD family', 'FlyAll and Matmid', 'Arkia inventory', 'Israir inventory', 'Israir airline selection', 'automatic customer migration', 'catalog_stale', 'conflicting-revision']) {
  requireText(runtime, proof, `Israeli benefit runtime is missing adversarial proof ${proof}.`);
}
for (const workflow of workflows) {
  requireText(workflow, 'php scripts/ci/validate-israel-benefit-catalog-runtime.php', 'Both CI workflows must run the Israeli benefit catalogue runtime gate.');
  requireText(workflow, 'node scripts/ci/validate-israel-benefit-catalog-contract.mjs', 'Both CI workflows must run the Israeli benefit catalogue contract gate.');
}

if (failures.length) {
  console.error('Israeli benefit catalogue contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Israeli benefit catalogue contract passed (${fixture.programs.length} programs; ${fixture.credential_products.length} exact card products; ${fixture.campaign_versions.length} campaign revisions; ${seenUrls.size} official sources).`);
