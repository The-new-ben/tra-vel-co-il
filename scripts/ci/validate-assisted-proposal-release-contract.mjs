#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const json = relative => JSON.parse(read(relative));
const failures = [];
const requireText = (source, marker, message) => {
  if (!source.includes(marker)) failures.push(message);
};

const main = read('plugin/tra-vel-agent-core/tra-vel-agent-core.php');
const health = read('plugin/tra-vel-agent-core/includes/class-tra-vel-agent-controller.php');
const uninstall = read('plugin/tra-vel-agent-core/uninstall.php');
const readme = read('plugin/tra-vel-agent-core/readme.txt');
const capabilities = read('plugin/tra-vel-agent-core/includes/class-tra-vel-quote-case-capabilities.php');
const themeStyle = read('theme/tra-vel-v2/style.css');
const themeFunctions = read('theme/tra-vel-v2/functions.php');
const requirements = json('theme/tra-vel-v2/release-requirements.json');
const agentVerifier = read('scripts/ci/verify_agent_deploy.py');
const healthValidator = read('scripts/ci/validate_agent_health_verification.py');
const themePreflight = read('scripts/ci/verify_theme_preflight.py');
const agentWorkflow = read('.github/workflows/deploy-agent-core.yml');
const themeWorkflow = read('.github/workflows/deploy-theme.yml');
const themeCi = read('.github/workflows/theme-ci.yml');

if (!/\* Version: 0\.9\.1/.test(main) || !/TRA_VEL_AGENT_VERSION', '0\.9\.1'/.test(main)) {
  failures.push('Agent Core header and runtime version must both identify release 0.9.1.');
}

for (const marker of [
  'class-tra-vel-assisted-proposal-policy.php',
	'class-tra-vel-assisted-proposal-composer.php',
  'class-tra-vel-assisted-proposal-store.php',
  'class-tra-vel-assisted-proposal-controller.php',
  'Tra_Vel_Assisted_Proposal_Store::install()',
  'Tra_Vel_Assisted_Proposal_Store::maybe_upgrade()',
  "array( 'Tra_Vel_Assisted_Proposal_Store', 'cleanup' ), 9",
  "array( 'Tra_Vel_Quote_Case_Store', 'cleanup' ), 10",
  'new Tra_Vel_Assisted_Proposal_Controller()',
]) requireText(main, marker, `Agent Core bootstrap is missing ${marker}.`);

const policyRequire = main.indexOf('class-tra-vel-assisted-proposal-policy.php');
const composerRequire = main.indexOf('class-tra-vel-assisted-proposal-composer.php');
const storeRequire = main.indexOf('class-tra-vel-assisted-proposal-store.php');
const controllerRequire = main.indexOf('class-tra-vel-assisted-proposal-controller.php');
if (!(policyRequire >= 0 && policyRequire < composerRequire && composerRequire < storeRequire && storeRequire < controllerRequire)) {
	failures.push('Assisted proposal policy, composer, store, and controller must load in dependency order.');
}
const proposalCleanup = main.indexOf("array( 'Tra_Vel_Assisted_Proposal_Store', 'cleanup' ), 9");
const quoteCleanup = main.indexOf("array( 'Tra_Vel_Quote_Case_Store', 'cleanup' ), 10");
if (!(proposalCleanup >= 0 && proposalCleanup < quoteCleanup)) {
  failures.push('Proposal children must be scheduled for cleanup before quote-case parents.');
}

for (const marker of [
  "const PUBLISH_PROPOSALS = 'tra_vel_publish_assisted_proposals'",
  "const INGEST_PROPOSALS = 'tra_vel_ingest_canonical_assisted_proposals'",
  'self::PUBLISH_PROPOSALS',
  'self::INGEST_PROPOSALS',
  '$operator->remove_cap( self::INGEST_PROPOSALS )',
]) requireText(capabilities, marker, `Proposal publication capability wiring is missing ${marker}.`);

for (const marker of [
  "'sourced_assisted_proposals'",
  "'audited_proposal_actions'",
  "'assisted_proposal_store'",
  'Tra_Vel_Assisted_Proposal_Store::schema_health()',
  'Tra_Vel_Assisted_Proposal_Store::is_ready()',
  "'expected_tables'            => 5",
  "'required_indexes'           => 9",
  "'supplier_search'        => false",
  "'supplier_dispatch'      => false",
  "'proposal_generation'    => false",
  "'payment_execution'      => false",
  "'booking_execution'      => false",
]) requireText(health, marker, `Public health is missing truthful proposal marker ${marker}.`);

const uninstallOrder = [
  'tra_vel_assisted_proposal_idempotency',
  'tra_vel_assisted_proposal_events',
  'tra_vel_assisted_proposal_sources',
  'tra_vel_assisted_proposal_revisions',
  'tra_vel_assisted_proposals',
  'tra_vel_quote_cases',
].map(marker => uninstall.indexOf(marker));
if (uninstallOrder.some(index => index < 0) || uninstallOrder.some((index, offset) => offset > 0 && index <= uninstallOrder[offset - 1])) {
  failures.push('Opt-in uninstall must remove proposal children before the quote-case parent.');
}
for (const marker of ['tra_vel_assisted_proposal_db_version', 'tra_vel_assisted_proposal_cleanup_status']) {
  requireText(uninstall, marker, `Opt-in uninstall is missing ${marker}.`);
}

if (!readme.includes('Stable tag: 0.9.1') || !readme.includes('= 0.9.1 =')) {
  failures.push('Agent Core readme must identify release 0.9.1.');
}
if (!themeStyle.includes('Version: 1.22.1') || !themeFunctions.includes("define( 'TRA_VEL_V2_VERSION', '1.22.1' )")) {
  failures.push('Theme header and runtime version must both identify release 1.22.1.');
}

const dependency = requirements.dependencies?.find(item => item.id === 'tra-vel-agent-core');
if (requirements.theme?.version !== '1.22.1' || dependency?.min_version !== '0.7.0') {
  failures.push('Theme release requirements must bind theme 1.22.1 to Agent Core 0.7.0 or newer.');
}
for (const capability of ['audited_proposal_actions', 'sourced_assisted_proposals']) {
  if (!dependency?.required_capabilities?.includes(capability)) failures.push(`Theme preflight does not require ${capability}.`);
}
if (!dependency?.required_stores?.includes('assisted_proposal_store')) {
  failures.push('Theme preflight does not require the assisted-proposal store.');
}

for (const marker of ['sourced_assisted_proposals', 'audited_proposal_actions', 'supplier_dispatch', 'assisted_proposal_store']) {
  requireText(agentVerifier, marker, `Agent deployment verification is missing ${marker}.`);
  requireText(healthValidator, marker, `Agent health runtime validation is missing ${marker}.`);
}
requireText(agentVerifier, 'expected_proposal_store', 'Agent deployment verification is missing the exact assisted-proposal store contract.');
requireText(healthValidator, 'An incomplete assisted-proposal index set passed deployment verification.', 'Agent health runtime validation does not exercise an assisted-proposal schema mismatch.');
for (const marker of ['ASSISTED_PROPOSAL_STORE_HEALTH', 'assisted_proposal_store']) {
  requireText(themePreflight, marker, `Theme live preflight is missing exact assisted-proposal check ${marker}.`);
}

for (const marker of [
  'validate-assisted-proposal-contract.mjs',
  'validate-assisted-proposal-store-contract.mjs',
  'validate-assisted-proposal-controller-contract.mjs',
	'validate-assisted-proposal-admin.mjs',
  'validate-assisted-proposal-release-contract.mjs',
  'validate-assisted-proposal-runtime.php',
  'validate-assisted-proposal-controller-runtime.php',
	'validate-assisted-proposal-composer-runtime.php',
	'validate-quote-case-capabilities-runtime.php',
]) {
  requireText(agentWorkflow, marker, `Agent Core workflow does not run ${marker}.`);
  requireText(themeCi, marker, `Theme CI does not run ${marker}.`);
}
requireText(themeWorkflow, 'validate-assisted-proposal-release-contract.mjs', 'Theme deploy validation does not run the assisted-proposal release contract.');
requireText(themeCi, 'validate-assisted-proposal-theme.mjs', 'Theme CI does not run the traveler-facing assisted-proposal contract.');
requireText(themeWorkflow, 'validate-assisted-proposal-theme.mjs', 'Theme deploy validation does not run the traveler-facing assisted-proposal contract.');
for (const [workflow, label] of [[agentWorkflow, 'Agent Core workflow'], [themeCi, 'Theme CI']]) {
  requireText(workflow, 'shivammathur/setup-php@v2', `${label} does not install explicit PHP runtimes.`);
  requireText(workflow, 'php-version: ["7.4", "8.3"]', `${label} does not test the declared PHP 7.4 boundary and current production runtime.`);
}

if (failures.length) {
  console.error('Tra-Vel assisted proposal release contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Tra-Vel assisted proposal release contract passed (lifecycle, truthful health, dependency preflight, and opt-in removal).');
