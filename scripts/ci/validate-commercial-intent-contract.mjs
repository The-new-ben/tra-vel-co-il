import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const json = relative => JSON.parse(read(relative));
const failures = [];
const fail = message => failures.push(message);
const requireText = (source, marker, message) => { if (!source.includes(marker)) fail(message); };
const semverAtLeast = (value, minimum) => {
  const parse = candidate => {
    const match = /^(\d+)\.(\d+)\.(\d+)$/.exec(String(candidate || ''));
    return match ? match.slice(1).map(Number) : null;
  };
  const current = parse(value);
  const floor = parse(minimum);
  if (!current || !floor) return false;
  for (let index = 0; index < current.length; index += 1) {
    if (current[index] !== floor[index]) return current[index] > floor[index];
  }
  return true;
};

const main = read('plugin/tra-vel-agent-core/tra-vel-agent-core.php');
const policy = read('plugin/tra-vel-agent-core/includes/class-tra-vel-commercial-intent-policy.php');
const store = read('plugin/tra-vel-agent-core/includes/class-tra-vel-commercial-intent-store.php');
const controller = read('plugin/tra-vel-agent-core/includes/class-tra-vel-commercial-intent-controller.php');
const health = read('plugin/tra-vel-agent-core/includes/class-tra-vel-agent-controller.php');
const uninstall = read('plugin/tra-vel-agent-core/uninstall.php');
const bridge = read('theme/tra-vel-v2/inc/handoffs/class-agent-commercial-intent-handoff-bridge.php');
const bootstrap = read('theme/tra-vel-v2/inc/handoffs/bootstrap.php');
const deployVerifier = read('scripts/ci/verify_agent_deploy.py');
const deployWorkflow = read('.github/workflows/deploy-agent-core.yml');
const schema = json('plugin/tra-vel-agent-core/schemas/commercial-intent.schema.json');
const requirements = json('theme/tra-vel-v2/release-requirements.json');

const pluginHeaderVersion = main.match(/\* Version: (\d+\.\d+\.\d+)/)?.[1];
const pluginRuntimeVersion = main.match(/TRA_VEL_AGENT_VERSION', '(\d+\.\d+\.\d+)'/)?.[1];
if (pluginHeaderVersion !== pluginRuntimeVersion || !semverAtLeast(pluginHeaderVersion, '0.5.0')) fail('Agent Core must remain at or above the durable commercial-intent 0.5.0 release floor with aligned header and runtime versions.');
for (const marker of [
  'class-tra-vel-commercial-intent-policy.php',
  'class-tra-vel-commercial-intent-store.php',
  'class-tra-vel-commercial-intent-controller.php',
  'Tra_Vel_Commercial_Intent_Store::install()',
  'Tra_Vel_Commercial_Intent_Store::maybe_upgrade()',
  "array( 'Tra_Vel_Commercial_Intent_Store', 'cleanup' )",
  'new Tra_Vel_Commercial_Intent_Controller()',
]) requireText(main, marker, `Agent Core bootstrap is missing ${marker}.`);

if (schema.additionalProperties !== false || schema.properties?.scope?.$ref !== '#/$defs/scope') fail('CommercialIntent public schema must be closed and use the closed scope definition.');
for (const key of ['scope', 'trip', 'candidate']) {
  const definition = schema.$defs?.[key];
  if (!definition || definition.additionalProperties !== false) fail(`CommercialIntent ${key} schema must reject undeclared properties.`);
}
const statuses = schema.properties?.status?.enum || [];
if (statuses.join('|') !== 'active|expired') fail('CommercialIntent must expose only active and expired lifecycle truth.');
const schemaText = JSON.stringify(schema).toLowerCase();
for (const forbidden of ['accepted', 'reserved', 'paid', 'booked', 'confirmed', 'issued', 'payment_status', 'booking_id', 'reservation_id', 'ticket_number', 'policy_number']) {
  if (schemaText.includes(forbidden)) fail(`CommercialIntent schema contains forbidden transactional claim ${forbidden}.`);
}

for (const marker of [
  'reject_forbidden_fields',
  'non_binding_planning_intent',
  'final_price_and_availability_require_personal_quote',
  "'resolved_provider' => self::HANDOFF_PROVIDER",
  "'commercial_ref'",
  "'infants'",
  "'rooms'",
  "'return_path'",
]) requireText(policy, marker, `Commercial intent policy is missing ${marker}.`);
for (const forbiddenPersisted of ["'price_amount'", "'total_price'", "'medical_condition'", "'pregnancy'", "'passport_number'", "'payment_status'"]) {
  if (policy.includes(forbiddenPersisted)) fail(`Commercial intent policy persists forbidden or unverified field ${forbiddenPersisted}.`);
}

if ((store.match(/ENGINE=InnoDB/g) || []).length !== 3) fail('Commercial intent store must install exactly three InnoDB tables.');
for (const marker of [
  'tra_vel_commercial_intents',
  'tra_vel_commercial_intent_events',
  'tra_vel_commercial_intent_idempotency',
  'owner_token_hash',
  'principal_hash',
  'idempotency_key_hash',
  'scope_digest',
  'START TRANSACTION',
  'FOR UPDATE',
  'ROLLBACK',
  'commercial_intent.created',
  'handoff.prepared',
	'target_digest',
  "'dispatched'          => false",
  "'side_effect_executed'=> false",
  'inspect_schema',
  'SHOW TABLE STATUS',
  'SHOW INDEX',
]) requireText(store, marker, `Commercial intent store is missing ${marker}.`);
if ((store.match(/UNIQUE KEY/g) || []).length < 5) fail('Commercial intent store lost an ownership, event-order or idempotency uniqueness invariant.');

for (const marker of [
  "'/' . $this->rest_base",
  "/(?P<intent_id>[0-9a-fA-F-]{36})/handoffs",
  'same_site_mutation',
  '__Host-tra_vel_commercial',
  'Secure; HttpOnly; SameSite=Lax',
  "'Cache-Control', 'private, no-store, max-age=0'",
  "'X-Robots-Tag', 'noindex, nofollow, noarchive'",
  'tra_vel_agent_commercial_intent_prepare_handoff',
  "'api.whatsapp.com'",
  "'side_effect_executed'=> false",
  'record_handoff(',
	'$source_port !== $home_port',
]) requireText(controller, marker, `Commercial intent controller is missing ${marker}.`);
requireText(controller, "if ( ! empty( $result['created'] ) )", 'Commercial intent creation hook must not fire again for idempotent replay or active-scope reuse.');
requireText(store, 'CLEANUP_STATUS_OPTION', 'Commercial intent cleanup must expose durable operational status.');
requireText(store, 'retention_select_failed', 'Commercial intent cleanup must distinguish a failed parent read from an empty batch.');
requireText(uninstall, 'tra_vel_commercial_intent_cleanup_status', 'Uninstall does not remove the commercial cleanup status after explicit data-removal opt-in.');
const recordPosition = controller.indexOf('record_handoff(');
const responsePosition = controller.indexOf("'handoff_url'", recordPosition);
if (recordPosition < 0 || responsePosition < recordPosition) fail('Handoff URL must be returned only after the durable handoff event is recorded.');

for (const marker of [
  "'commercial_intents'",
  "'durable_commercial_handoffs'",
  "'commercial_intent_store'",
  "'payment_execution'      => false",
  "'booking_execution'      => false",
  "'reservation_execution'  => false",
  "'ticket_issuance'        => false",
]) requireText(health, marker, `Agent health is missing truthful capability marker ${marker}.`);

for (const table of ['tra_vel_commercial_intent_idempotency', 'tra_vel_commercial_intent_events', 'tra_vel_commercial_intents']) requireText(uninstall, table, `Uninstall retention opt-in does not include ${table}.`);
requireText(uninstall, 'tra_vel_commercial_intent_db_version', 'Uninstall does not remove the commercial schema version option after explicit data-removal opt-in.');
requireText(bridge, 'tra_vel_agent_commercial_intent_prepare_handoff', 'Theme does not bridge Agent Core commercial intents to the owned allowlisted handoff.');
requireText(bridge, "'owned'", 'Commercial-intent bridge does not require the owned relationship.');
requireText(bootstrap, 'class-agent-commercial-intent-handoff-bridge.php', 'Theme handoff bootstrap omits the commercial-intent bridge.');

const dependency = requirements.dependencies?.find(item => item.id === 'tra-vel-agent-core');
if (!dependency || !semverAtLeast(dependency.min_version, '0.5.0')) fail('Theme must require Agent Core 0.5.0 or newer before using commercial intents.');
for (const capability of ['commercial_intents', 'durable_commercial_handoffs']) {
  if (!dependency?.required_capabilities?.includes(capability)) fail(`Theme release preflight does not require ${capability}.`);
}
if (!dependency?.required_stores?.includes('commercial_intent_store')) fail('Theme release preflight does not require the commercial-intent schema.');
for (const marker of ['commercial_intents', 'durable_commercial_handoffs', 'commercial_intent_store', 'expected_commercial_store']) {
  requireText(deployVerifier, marker, `Agent Core post-deploy verification is missing ${marker}.`);
}
for (const marker of ['validate-commercial-intent-contract.mjs', 'validate-commercial-intent-runtime.php']) {
  requireText(deployWorkflow, marker, `Agent Core workflow does not execute ${marker}.`);
}

const supportedCode = `${policy}\n${store}\n${controller}`.toLowerCase();
for (const forbiddenRoute of ['/checkout', '/payments', '/orders', '/reservations', '/tickets', '/policies']) {
  if (supportedCode.includes(forbiddenRoute)) fail(`Commercial-intent supported code exposes forbidden route ${forbiddenRoute}.`);
}

if (failures.length) {
  console.error('Tra-Vel commercial-intent contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Tra-Vel commercial-intent contract validation passed (private ownership, idempotent audit, owned handoff, non-transactional truth).');
