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
const agentRunSummary = readJson('agent-run-summary.schema.json');
const mainPhp = readFileSync(join(pluginRoot, 'tra-vel-agent-core.php'), 'utf8');
const providerPhp = readPhp('class-tra-vel-agent-openai-provider.php');
const controllerPhp = readPhp('class-tra-vel-agent-controller.php');
const storePhp = readPhp('class-tra-vel-agent-store.php');
const policyPhp = readPhp('class-tra-vel-agent-policy.php');
const providerInterfacePhp = readPhp('interface-tra-vel-agent-provider.php');

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
  ['AgentRunSummary', agentRunSummary],
]) {
  if (schema?.$schema !== 'http://json-schema.org/draft-07/schema#') fail(`${label} must use the repository Draft 7 contract convention.`);
  assertClosedObject(schema, label);
}

assertRequired(tripRequest, [
  'contract_version', 'request_id', 'revision', 'summary', 'language', 'origin_text',
  'destination_mode', 'destinations', 'travelers', 'budget', 'search_scope',
  'material_questions', 'source', 'readiness',
], 'TripRequest');
if (tripRequest.properties?.contract_version?.const !== '1.1.0' || !String(tripRequest.$id || '').endsWith('trip-request-1.1.0.json')) fail('TripRequest planning-context contract must be versioned as 1.1.0.');
if (!(tripRequest.required || []).includes('planning_context')) fail('TripRequest 1.1.0 must require its deterministic planning context.');
if (!(tripRequest.properties?.destination_mode?.enum || []).includes('anywhere')) fail('TripRequest must preserve an explicit anywhere destination mode.');

const travelers = tripRequest.properties?.travelers;
const budget = tripRequest.properties?.budget;
const source = tripRequest.properties?.source;
const planningContext = tripRequest.properties?.planning_context;
const readiness = tripRequest.properties?.readiness;
const question = tripRequest.properties?.material_questions?.items;
assertClosedObject(travelers, 'TripRequest.travelers');
assertClosedObject(budget, 'TripRequest.budget');
assertClosedObject(source, 'TripRequest.source');
assertClosedObject(planningContext, 'TripRequest.planning_context');
assertClosedObject(readiness, 'TripRequest.readiness');
assertClosedObject(question, 'TripRequest.material_questions item');
assertRequired(travelers, ['adults', 'children', 'child_ages', 'rooms'], 'TripRequest.travelers');
assertRequired(source, ['channel', 'input_kind', 'input_sha256', 'transcript_confirmed'], 'TripRequest.source');
assertRequired(planningContext, ['kind', 'selection_id', 'latitude', 'longitude', 'destination', 'intent', 'scope'], 'TripRequest.planning_context');
assertRequired(readiness, ['status', 'blockers'], 'TripRequest.readiness');
assertRequired(question, ['id', 'field', 'question', 'reason', 'blocking', 'status'], 'TripRequest.material_questions item');
if (!sameMembers(source.properties?.input_kind?.enum || [], ['typed', 'voice'])) fail('TripRequest input kind must distinguish typed and voice requests.');
if (source.properties?.input_sha256?.pattern !== '^[a-f0-9]{64}$') fail('TripRequest must carry a non-secret SHA-256 input fingerprint.');
if (!sameMembers(planningContext.properties?.kind?.enum || [], ['free_text', 'destination', 'map_point'])) fail('TripRequest planning context kinds changed.');
if (planningContext.properties?.selection_id?.pattern !== '^[A-Za-z0-9_-]{8,80}$') fail('TripRequest planning context must bind a stable selection identity.');
if (planningContext.properties?.latitude?.minimum !== -90 || planningContext.properties?.latitude?.maximum !== 90 || planningContext.properties?.longitude?.minimum !== -180 || planningContext.properties?.longitude?.maximum !== 180) fail('TripRequest planning coordinates are not range bounded.');
if (!sameMembers(planningContext.properties?.scope?.items?.enum || [], ['flights', 'accommodation', 'transfers', 'activities', 'dining', 'insurance', 'connectivity', 'equipment'])) fail('TripRequest planning scope is not the canonical eight-domain set.');
if (!Array.isArray(planningContext.allOf) || planningContext.allOf.length < 4 || !JSON.stringify(planningContext.allOf).includes('map_point') || !JSON.stringify(planningContext.allOf).includes('destination')) fail('TripRequest planning context does not publish its kind-specific invariants.');
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

const runSummaryFields = [
  'run_id', 'status', 'mode', 'locale', 'summary', 'planning_context', 'readiness',
  'request_revision', 'proposal_count', 'created_at', 'updated_at', 'expires_at',
  'resume_available',
];
assertRequired(agentRunSummary, runSummaryFields, 'AgentRunSummary');
if (!sameMembers(Object.keys(agentRunSummary.properties || {}), runSummaryFields)) fail('AgentRunSummary must expose only the agreed account-history DTO fields.');
assertClosedObject(agentRunSummary.properties?.planning_context, 'AgentRunSummary.planning_context');
assertClosedObject(agentRunSummary.properties?.readiness, 'AgentRunSummary.readiness');
assertRequired(agentRunSummary.properties?.planning_context, ['kind', 'selection_id', 'latitude', 'longitude', 'destination', 'intent', 'scope'], 'AgentRunSummary.planning_context');
assertRequired(agentRunSummary.properties?.readiness, ['status', 'blockers'], 'AgentRunSummary.readiness');
if (agentRunSummary.properties?.proposal_count?.minimum !== 0 || agentRunSummary.properties?.request_revision?.minimum !== 0) fail('AgentRunSummary counts and revisions must remain non-negative.');
if (agentRunSummary.properties?.resume_available?.type !== 'boolean') fail('AgentRunSummary must publish a boolean resume_available truth flag.');
if (agentRunSummary.properties?.planning_context?.additionalProperties !== false || agentRunSummary.properties?.readiness?.additionalProperties !== false) fail('AgentRunSummary nested objects must remain closed.');

// 0.9.2 production response-integrity hardening: outside WP_DEBUG, PHP error
// display must be off and only the deprecation classes may leave the runtime
// error mask, at the earliest load point before any other plugin code loads.
const hardeningIndex = mainPhp.indexOf("ini_set( 'display_errors', '0' )");
const firstRequireIndex = mainPhp.indexOf('require_once');
if (hardeningIndex < 0) fail('Agent Core bootstrap must disable PHP error display outside WP_DEBUG.');
if (hardeningIndex >= 0 && firstRequireIndex >= 0 && hardeningIndex > firstRequireIndex) fail('Error-display hardening must run before any other plugin file is required.');
if (!/if \( ! defined\( 'WP_DEBUG' \) \|\| true !== WP_DEBUG \)/.test(mainPhp)) fail('Error-display hardening must apply exactly when WP_DEBUG is not true.');
if (!/function_exists\( 'ini_set' \)/.test(mainPhp)) fail('Error-display hardening must tolerate a host-disabled ini_set.');
if (!/error_reporting\( error_reporting\(\) & ~E_DEPRECATED & ~E_USER_DEPRECATED \)/.test(mainPhp)) fail('Only the deprecation classes may leave the error mask; fatals and warnings must keep reaching logs.');
if (/['"]display_errors['"],\s*['"]?(?:1|on|true)/i.test(mainPhp)) fail('Agent Core must never force PHP error display on.');

// 0.9.2 cost-control model option: the filter stays the final override, the
// stored option applies only when it matches the strict allowlist, and the
// shipped default model is unchanged in this release.
if (!/const DEFAULT_MODEL = 'gpt-5\.6-terra'/.test(providerPhp)) fail('The interpretation default model must remain gpt-5.6-terra.');
if (!/const MODEL_OPTION = 'tra_vel_agent_model_v1'/.test(providerPhp)) fail('The interpretation model must be stored in the plain tra_vel_agent_model_v1 option.');
if (!/const ALLOWED_MODELS = array\( 'gpt-5\.6-terra', 'gpt-5\.6-mini', 'gpt-5\.6-nano', 'gpt-5-mini' \)/.test(providerPhp)) fail('The interpretation model allowlist is not the agreed strict set.');
if (!/apply_filters\( 'tra_vel_agent_openai_model', \$configured \)/.test(providerPhp)) fail('The tra_vel_agent_openai_model filter must stay the final override above the stored option.');
if (!/'model_source' => \$this->model_source/.test(providerPhp)) fail('Provider health must disclose a truthful model_source.');
for (const source of ["'filter'", "'option'", "'default'"]) {
  if (!providerPhp.includes(`$this->model_source = ${source}`)) fail(`Provider model resolution must be able to report source ${source}.`);
}
const storedModelPhp = providerPhp.slice(providerPhp.indexOf('public static function stored_model'), providerPhp.indexOf('public static function model_status'));
if (!storedModelPhp.includes('in_array( $model, self::ALLOWED_MODELS, true )')) fail('A stored model outside the allowlist must be ignored, never trusted.');
const storeModelPhp = providerPhp.slice(providerPhp.indexOf('public static function store_model'), providerPhp.indexOf('public static function clear_model'));
if (!storeModelPhp.includes('in_array( $model, self::ALLOWED_MODELS, true )') || !storeModelPhp.includes("'tra_vel_agent_model_invalid'")) fail('Model storage must reject identifiers outside the strict allowlist.');
if (!providerPhp.includes('delete_option( self::MODEL_OPTION )')) fail('Clearing the model must restore the default by deleting the option.');
if (!controllerPhp.includes("'/settings/model'")) fail('Admin-only model settings routes are missing.');
if (!controllerPhp.includes("'STORE TRA-VEL AGENT MODEL'") || !controllerPhp.includes("'tra_vel_agent_model_confirmation'")) fail('Model storage must require its exact confirmation phrase.');
const modelRoutePhp = controllerPhp.slice(controllerPhp.indexOf("'/settings/model'"), controllerPhp.indexOf("'/settings/model'") + 1400);
if (!modelRoutePhp.includes('Tra_Vel_Agent_OpenAI_Provider::ALLOWED_MODELS')) fail('The model route must bind its enum to the single provider allowlist constant.');
if ((modelRoutePhp.match(/can_manage_agent/g) || []).length < 2) fail('Both model settings methods must stay admin-only.');
if (!controllerPhp.includes('public function clear_model()') || !controllerPhp.includes('Tra_Vel_Agent_OpenAI_Provider::clear_model()')) fail('The model DELETE route must delete the option to restore the default.');

if (!/'store'\s*=>\s*false/.test(providerPhp)) fail('OpenAI Responses calls must disable provider-side response storage.');
if (!providerPhp.includes('implements Tra_Vel_Agent_Provider')) fail('The OpenAI interpreter is not behind the replaceable provider boundary.');
if (!providerInterfacePhp.includes('public function revise(') || !providerPhp.includes('public function revise(')) fail('The provider boundary must support strict in-place request revision.');
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
for (const marker of ['planning_context_schema', 'validate_planning_context', 'sanitize_planning_context', 'rest_validate_value_from_schema', 'rest_sanitize_value_from_schema', 'tra_vel_agent_coordinate_pair_required', "'selection_id' => $planning_context['selection_id']"]) {
  if (!controllerPhp.includes(marker)) fail(`Structured map handoff is missing ${marker}.`);
}
if (!controllerPhp.includes('/messages') || !controllerPhp.includes('public function revise_run(')) fail('Agent Core must expose a same-run natural-language revision endpoint.');
if (!/request_revision'\s*=>\s*\$this->provider->health\(\)\['configured'\]/.test(controllerPhp)) fail('Agent health must report request revision availability from the configured provider.');
if (!/account_plan_history'\s*=>\s*true/.test(controllerPhp)) fail('Agent health must report account plan-history availability.');
for (const eventType of ['clarification.response.received', 'request.revision.started', 'request.revised', 'request.revision.failed']) {
  if (!controllerPhp.includes(`'${eventType}'`)) fail(`Same-run revision is missing truthful event ${eventType}.`);
}
if (!controllerPhp.includes("'input_sha256' => $message_hash") || controllerPhp.includes("'message' => $message")) fail('Revision input must be fingerprinted without persisting raw clarification text.');
if (!controllerPhp.includes("'tra_vel_agent_max_request_revisions', 8") || !controllerPhp.includes("'tra_vel_agent_duplicate_revision'")) fail('Same-run revision must enforce bounded revisions and idempotency.');
if (!storePhp.includes('update_run_if_owner') || !controllerPhp.includes("'tra_vel_agent_revision_owner_changed'") || !controllerPhp.includes("'owner_token_hash' => (string)")) fail('In-flight revisions must CAS against the owner and run version captured before the provider call.');
if (!/public function revise_run[\s\S]{0,900}can_access\( \$run, \$this->run_cookie_token/.test(controllerPhp)) fail('Revision callbacks must reauthorize the freshly fetched run after REST permission checks.');
if (!policyPhp.includes("$previous['request_id']") || !policyPhp.includes("(int) $previous['revision'] + 1")) fail('Deterministic policy must preserve request identity and increment the revision.');
if (!policyPhp.includes("$previous['planning_context']") || !policyPhp.includes("$request['planning_context']")) fail('Natural-language revisions can lose their exact map selection context.');
if (!/provider_connected'\s*=>\s*false/.test(controllerPhp) || !/provider_bookable'\s*=>\s*false/.test(controllerPhp)) fail('The not-started event must disclose disconnected and non-bookable provider state.');
if (!/data_mode'\s*=>\s*'not_connected'/.test(controllerPhp)) fail('The not-started event must use the not_connected data mode.');
if (!/side_effect_executed'\s*=>\s*false/.test(controllerPhp)) fail('Approval decisions must not claim that a side effect ran in the foundation slice.');
if (!controllerPhp.includes("'tra_vel_agent_approval_owner_required'") || !controllerPhp.includes("(int) $run['owner_user_id'] !== $user_id")) fail('Consequential approvals must require the exact signed-in run owner, not only a bearer token.');
if (!controllerPhp.includes("'tra_vel_agent_daily_request_limit', 200") || !controllerPhp.includes("'tra_vel_agent_daily_capacity'")) fail('Agent intake must enforce the default global daily balance guard.');
if (!controllerPhp.includes("'tra_vel_agent_visitor_request_limit', 8")) fail('Agent intake must enforce the default eight-per-window visitor guard.');
if (!controllerPhp.includes('__Host-tra_vel_agent_run=') || !controllerPhp.includes('Secure; HttpOnly; SameSite=Lax')) fail('Anonymous run ownership must use a Secure HttpOnly SameSite cookie.');
if (controllerPhp.includes("$data['run_token']")) fail('The private run bearer token must not be exposed in a JSON response.');
if (!storePhp.includes("'input_text'           => ''")) fail('Raw natural-language intake must not be persisted in the Agent Core database.');
if (!/const\s+DB_VERSION\s*=\s*'1\.2\.0'/.test(storePhp) || (storePhp.match(/ENGINE=InnoDB/g) || []).length < 4 || !storePhp.includes('ensure_transactional_tables')) fail('AgentRun ownership binding requires all four Agent Core tables to migrate to InnoDB under schema 1.2.0.');
for (const marker of ['inspect_schema', 'SHOW COLUMNS', 'SHOW TABLE STATUS', 'SHOW INDEX', 'required_indexes_ready', 'ready_tables']) {
  if (!storePhp.includes(marker)) fail(`Agent store fail-closed schema inspection is missing ${marker}.`);
}
if ((storePhp.match(/UNIQUE\s+KEY/gi) || []).length < 5 || (storePhp.match(/PRIMARY\s+KEY/gi) || []).length < 4) fail('Agent store DDL lost a required ownership, sequence, approval, or quota uniqueness invariant.');
if (!storePhp.includes("0 === (int) $run['owner_user_id']") || !storePhp.includes("'owner_token_hash'     => absint")) fail('Account-owned AgentRuns must invalidate anonymous bearer ownership.');
if (!controllerPhp.includes("'agent_store'") || !controllerPhp.includes('Tra_Vel_Agent_Store::schema_health()')) fail('Agent health must expose transactional AgentRun store readiness.');
if (!controllerPhp.includes('public function can_use_store') || !controllerPhp.includes('Tra_Vel_Agent_Store::is_ready()') || !controllerPhp.includes("'tra_vel_agent_store_unavailable'")) fail('Agent runtime routes must fail closed when the transactional AgentRun schema is unavailable.');
for (const marker of ['/schema/agent-run-summary', 'public function can_list_runs', 'public function list_runs', 'list_owned_runs( $user_id, $limit, $read_error )', "'tra_vel_agent_runs_read_failed'", "'runs' => array_map"]) {
  if (!controllerPhp.includes(marker)) fail(`Authenticated account-run collection is missing ${marker}.`);
}
const listRunsPhp = controllerPhp.slice(controllerPhp.indexOf('public function can_list_runs'), controllerPhp.indexOf('public function get_run('));
if (!/get_current_user_id\(\)\s*<\s*1/.test(listRunsPhp) || !/current_user_can\(\s*'read'\s*\)/.test(listRunsPhp)) fail('Account-run collection must require both a signed-in user and the read capability.');
if (/get_param\(\s*['"]owner/.test(listRunsPhp) || /owner_user_id\s*=\s*\$request/.test(listRunsPhp)) fail('Account-run collection must never accept an owner identifier from the request.');
if (!/min\(\s*20,\s*max\(\s*1/.test(listRunsPhp)) fail('Account-run collection must cap direct callback limits at 20.');
if (!/['"]limit['"]\s*=>\s*array\([^\n]+['"]default['"]\s*=>\s*12/.test(controllerPhp) || !/get_param\(\s*['"]limit['"]\s*\)[\s\S]{0,40}\?:\s*12/.test(listRunsPhp)) fail('Account-run collection must default to 12 while retaining the 20-item cap.');
if (!/tra_vel_agent_runs_read_failed[\s\S]{0,180}status'\s*=>\s*503/.test(listRunsPhp)) fail('Account-run collection must surface database read uncertainty as 503.');
const summaryPresenterPhp = controllerPhp.slice(controllerPhp.indexOf('public function public_run_summary'), controllerPhp.indexOf('private function attach_run_cookie'));
for (const field of runSummaryFields) {
  if (!new RegExp(`['"]${field}['"]\\s*=>`).test(summaryPresenterPhp)) fail(`Account-run DTO presenter must emit ${field}.`);
}
for (const internalField of ['id', 'owner_user_id', 'owner_token_hash', 'trip_request', 'proposals', 'provider', 'provider_state', 'events', 'approvals', 'input_text']) {
  if (new RegExp(`['"]${internalField}['"]\\s*=>`).test(summaryPresenterPhp)) fail(`Account-run DTO presenter must not expose ${internalField}.`);
}
if (!/proposal_count'\s*=>\s*count\(\s*\$proposals\s*\)/.test(summaryPresenterPhp)) fail('Account-run DTO must count the actual stored proposals array.');
if (!/private_response[\s\S]{0,160}'runs'/.test(listRunsPhp) && !/'runs'[\s\S]{0,160}private_response/.test(listRunsPhp)) fail('Account-run collection must use the private no-store response wrapper.');
if (!storePhp.includes('INSERT IGNORE INTO') || !storePhp.includes('counter_value < %d')) fail('Agent request limits must reserve capacity atomically in the database.');
if (!/function\s+get_run_by_uuid\s*\([^)]*&\$read_error/.test(storePhp) || !/get_run_by_uuid[\s\S]{0,520}last_error/.test(storePhp)) fail('Authoritative AgentRun reads must expose transient SELECT errors separately from true absence.');
const listOwnedRunsPhp = storePhp.slice(storePhp.indexOf('public function list_owned_runs'), storePhp.indexOf('public function get_run_ownership_by_uuids'));
if (!/\$user_id\s*=\s*absint\(\s*\$user_id\s*\)/.test(listOwnedRunsPhp) || !/if \( \$user_id < 1 \)/.test(listOwnedRunsPhp)) fail('AgentRun list storage must reject guest ownership before querying.');
if (!/WHERE owner_user_id = %d AND expires_at >= %s/.test(listOwnedRunsPhp)) fail('AgentRun list SQL must bind the exact account and exclude expired rows.');
if (!/ORDER BY updated_at DESC, id DESC LIMIT %d/.test(listOwnedRunsPhp)) fail('AgentRun list SQL must use stable updated_at DESC, id DESC ordering and a prepared limit.');
if (!/min\(\s*20,\s*max\(\s*1/.test(listOwnedRunsPhp)) fail('AgentRun list storage must cap its limit at 20 independently of REST validation.');
if (!/function\s+list_owned_runs\(\s*\$user_id,\s*\$limit\s*=\s*12/.test(listOwnedRunsPhp)) fail('AgentRun list storage must share the 12-item default.');
if (!/suppress_errors[\s\S]{0,900}last_error/.test(listOwnedRunsPhp) || !/'' !== \$read_error/.test(listOwnedRunsPhp)) fail('AgentRun list storage must distinguish a SELECT failure from an empty account.');
for (const forbiddenColumn of ['owner_token_hash', 'input_text', 'request_fingerprint', 'provider_state']) {
  const selectLine = listOwnedRunsPhp.match(/SELECT [^\n]+ FROM/)?.[0] || '';
  if (selectLine.includes(forbiddenColumn)) fail(`AgentRun account list should not read unnecessary private column ${forbiddenColumn}.`);
}
if (!controllerPhp.includes("'tra_vel_agent_provider_concurrency_limit', 2") || !controllerPhp.includes("'tra_vel_agent_provider_busy'")) fail('Live provider work must enforce the default two-slot concurrency semaphore.');
if (!storePhp.includes('acquire_lease') || !storePhp.includes('release_lease') || !storePhp.includes('AND option_value = %s')) fail('Provider concurrency leases must be atomic and owner-conditionally released.');
if (!storePhp.includes("preg_replace( '/[^a-z0-9._-]/'") || storePhp.includes("str_replace( '_', '.', $row['event_type'] )")) fail('Run events must preserve canonical dot and underscore separators without lossy conversion.');
if (!storePhp.includes("'supplier_search_not_started'    => 'supplier.search.not_started'")) fail('Agent Core must normalize event rows written by version 0.1.0.');
for (const marker of ['delete_expired_run_aggregate', 'sweep_orphan_rows', 'START TRANSACTION', 'FOR UPDATE', 'ROLLBACK', 'CLEANUP_STATUS_OPTION']) {
  if (!storePhp.includes(marker)) fail(`AgentRun privacy cleanup is missing ${marker}.`);
}
const cleanupPhp = storePhp.slice(storePhp.indexOf('public static function cleanup_expired'), storePhp.indexOf('public function consume_limit'));
if (!/if \( ! self::is_ready\(\) \)/.test(cleanupPhp) || !/last_error/.test(cleanupPhp)) fail('AgentRun cleanup must fail closed on schema unreadiness and database read errors.');

if (failures.length) {
  console.error('Tra-Vel agent contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Tra-Vel agent contract validation passed (closed schemas, strict provider output, truthful non-transactional capabilities).');
