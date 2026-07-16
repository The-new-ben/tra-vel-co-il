import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const pluginRoot = join(repoRoot, 'plugin', 'tra-vel-agent-core');
const schemaRoot = join(pluginRoot, 'schemas');
const failures = [];

const fail = message => failures.push(message);
const readJson = filename => JSON.parse(readFileSync(join(schemaRoot, filename), 'utf8'));
const readPhp = filename => readFileSync(join(pluginRoot, 'includes', filename), 'utf8');
const hasEvery = (values, required) => required.every(value => values.includes(value));
const sameMembers = (left, right) => left.length === right.length && hasEvery(left, right);

const tripRequest = readJson('trip-request.schema.json');
const runEvent = readJson('run-event.schema.json');
const approval = readJson('approval.schema.json');
const agentRun = readJson('agent-run.schema.json');
const providerPhp = readPhp('class-tra-vel-agent-openai-provider.php');
const controllerPhp = readPhp('class-tra-vel-agent-controller.php');
const storePhp = readPhp('class-tra-vel-agent-store.php');

const assertClosedObject = (schema, label) => {
  if (schema?.type !== 'object') fail(`${label} must be an object schema.`);
  if (schema?.additionalProperties !== false) fail(`${label} must reject undeclared properties.`);
};

const assertRequired = (schema, keys, label) => {
  const required = schema?.required || [];
  for (const key of keys) {
    if (!required.includes(key)) fail(`${label} must require ${key}.`);
    if (!(key in (schema?.properties || {}))) fail(`${label} must define ${key}.`);
  }
};

for (const [label, schema] of [
  ['TripRequest', tripRequest],
  ['RunEvent', runEvent],
  ['ApprovalRequest', approval],
  ['AgentRun', agentRun],
]) {
  if (schema?.$schema !== 'http://json-schema.org/draft-07/schema#') fail(`${label} must use the repository Draft 7 contract convention.`);
  assertClosedObject(schema, label);
}

assertRequired(tripRequest, [
  'contract_version', 'request_id', 'revision', 'summary', 'language', 'origin_text',
  'destination_mode', 'destinations', 'travelers', 'budget', 'search_scope',
  'material_questions', 'source', 'readiness',
], 'TripRequest');
if (tripRequest.properties?.contract_version?.const !== '1.0.0') fail('TripRequest contract version must remain 1.0.0.');
if (!(tripRequest.properties?.destination_mode?.enum || []).includes('anywhere')) fail('TripRequest must preserve an explicit anywhere destination mode.');

const travelers = tripRequest.properties?.travelers;
const budget = tripRequest.properties?.budget;
const source = tripRequest.properties?.source;
const readiness = tripRequest.properties?.readiness;
const question = tripRequest.properties?.material_questions?.items;
assertClosedObject(travelers, 'TripRequest.travelers');
assertClosedObject(budget, 'TripRequest.budget');
assertClosedObject(source, 'TripRequest.source');
assertClosedObject(readiness, 'TripRequest.readiness');
assertClosedObject(question, 'TripRequest.material_questions item');
assertRequired(travelers, ['adults', 'children', 'child_ages', 'rooms'], 'TripRequest.travelers');
assertRequired(source, ['channel', 'input_kind', 'input_sha256', 'transcript_confirmed'], 'TripRequest.source');
assertRequired(readiness, ['status', 'blockers'], 'TripRequest.readiness');
assertRequired(question, ['id', 'field', 'question', 'reason', 'blocking', 'status'], 'TripRequest.material_questions item');
if (!sameMembers(source.properties?.input_kind?.enum || [], ['typed', 'voice'])) fail('TripRequest input kind must distinguish typed and voice requests.');
if (source.properties?.input_sha256?.pattern !== '^[a-f0-9]{64}$') fail('TripRequest must carry a non-secret SHA-256 input fingerprint.');
if (!sameMembers(readiness.properties?.status?.enum || [], ['needs_clarification', 'ready_for_search', 'unsupported'])) fail('TripRequest readiness states changed.');

assertRequired(runEvent, ['contract_version', 'event_id', 'sequence', 'occurred_at', 'type', 'phase', 'status', 'source', 'visible', 'message', 'data'], 'RunEvent');
if (!(runEvent.properties?.phase?.enum || []).includes('supplier_search')) fail('RunEvent must represent supplier-search state explicitly.');
if (!hasEvery(runEvent.properties?.source?.enum || [], ['system', 'model', 'tool', 'supplier', 'calculation', 'human'])) fail('RunEvent provenance sources are incomplete.');

assertRequired(approval, ['approval_id', 'version', 'status', 'action_type', 'scope_digest', 'summary', 'action', 'expires_at'], 'ApprovalRequest');
if (!hasEvery(approval.properties?.action_type?.enum || [], ['purchase', 'cancel', 'amend', 'submit_personal_data', 'bind_insurance', 'send_supplier_request'])) fail('ApprovalRequest must enumerate every consequential action class.');
if (approval.properties?.scope_digest?.pattern !== '^[a-f0-9]{64}$') fail('ApprovalRequest must bind a decision to an exact SHA-256 action scope.');

assertRequired(agentRun, ['contract_version', 'run_id', 'status', 'mode', 'locale', 'trip_request', 'proposals', 'provider', 'events', 'approvals', 'created_at', 'updated_at', 'expires_at'], 'AgentRun');
if (!sameMembers(agentRun.properties?.mode?.enum || [], ['agent', 'surprise'])) fail('AgentRun must retain both agent and surprise modes.');
if ('run_token' in (agentRun.properties || {})) fail('AgentRun must not expose the private bearer secret in JSON.');
const runStatuses = agentRun.properties?.status?.enum || [];
if (!hasEvery(runStatuses, ['needs_clarification', 'request_ready', 'provider_error'])) fail('AgentRun is missing first-slice terminal/readiness states.');
if (runStatuses.some(status => /booked|purchased|reserved/.test(status))) fail('The interpretation-only AgentRun contract must not claim a supplier transaction state.');

if (!/'store'\s*=>\s*false/.test(providerPhp)) fail('OpenAI Responses calls must disable provider-side response storage.');
if (!providerPhp.includes('implements Tra_Vel_Agent_Provider')) fail('The OpenAI interpreter is not behind the replaceable provider boundary.');
if (!/'strict'\s*=>\s*true/.test(providerPhp)) fail('The OpenAI provider must request strict JSON Schema output.');
if (!/const MAX_OUTPUT_TOKENS\s*=\s*1600/.test(providerPhp)) fail('OpenAI interpretation must keep its explicit 1,600-token output ceiling.');
if (!/'additionalProperties'\s*=>\s*false/.test(providerPhp)) fail('The OpenAI provider schema must reject undeclared fields.');
for (const truthRule of [
  'Never invent dates, traveler ages, budgets',
  'supplier availability, prices, savings, reservations, or bookings',
  'Interpretation is not supplier search',
]) {
  if (!providerPhp.includes(truthRule)) fail(`OpenAI interpretation instructions lost truth rule: ${truthRule}`);
}
if (!/supplier_search'\s*=>\s*false/.test(controllerPhp)) fail('Agent health must report supplier search as unavailable in the first slice.');
if (!/proposal_generation'\s*=>\s*false/.test(controllerPhp)) fail('Agent health must report proposal generation as unavailable in the first slice.');
if (!/booking_execution'\s*=>\s*false/.test(controllerPhp)) fail('Agent health must report booking execution as unavailable in the first slice.');
if (!controllerPhp.includes("'supplier.search.not_started'")) fail('A ready request must emit an explicit supplier-search-not-started event.');
if (!/provider_connected'\s*=>\s*false/.test(controllerPhp) || !/provider_bookable'\s*=>\s*false/.test(controllerPhp)) fail('The not-started event must disclose disconnected and non-bookable provider state.');
if (!/data_mode'\s*=>\s*'not_connected'/.test(controllerPhp)) fail('The not-started event must use the not_connected data mode.');
if (!/side_effect_executed'\s*=>\s*false/.test(controllerPhp)) fail('Approval decisions must not claim that a side effect ran in the foundation slice.');
if (!controllerPhp.includes("'tra_vel_agent_approval_owner_required'") || !controllerPhp.includes("(int) $run['owner_user_id'] !== $user_id")) fail('Consequential approvals must require the exact signed-in run owner, not only a bearer token.');
if (!controllerPhp.includes("'tra_vel_agent_daily_request_limit', 20") || !controllerPhp.includes("'tra_vel_agent_daily_capacity'")) fail('Agent intake must enforce the default global daily balance guard.');
if (!controllerPhp.includes('__Host-tra_vel_agent_run=') || !controllerPhp.includes('Secure; HttpOnly; SameSite=Lax')) fail('Anonymous run ownership must use a Secure HttpOnly SameSite cookie.');
if (controllerPhp.includes("$data['run_token']")) fail('The private run bearer token must not be exposed in a JSON response.');
if (!storePhp.includes("'input_text'           => ''")) fail('Raw natural-language intake must not be persisted in the Agent Core database.');
if (!storePhp.includes('INSERT IGNORE INTO') || !storePhp.includes('counter_value < %d')) fail('Agent request limits must reserve capacity atomically in the database.');
if (!controllerPhp.includes("'tra_vel_agent_provider_concurrency_limit', 2") || !controllerPhp.includes("'tra_vel_agent_provider_busy'")) fail('Live provider work must enforce the default two-slot concurrency semaphore.');
if (!storePhp.includes('acquire_lease') || !storePhp.includes('release_lease') || !storePhp.includes('AND option_value = %s')) fail('Provider concurrency leases must be atomic and owner-conditionally released.');

if (failures.length) {
  console.error('Tra-Vel agent contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Tra-Vel agent contract validation passed (closed schemas, strict provider output, truthful non-transactional capabilities).');
