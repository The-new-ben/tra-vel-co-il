import { existsSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const pluginRoot = join(repoRoot, 'plugin', 'tra-vel-agent-core');
const schemaRoot = join(pluginRoot, 'schemas');
const includesRoot = join(pluginRoot, 'includes');
const failures = [];

const statuses = [
  'queued',
  'in_review',
  'needs_information',
  'ready_for_assistance',
  'closed_no_quote',
  'cancelled',
  'expired',
];

const productionFiles = {
  store: 'class-tra-vel-quote-case-store.php',
  policy: 'class-tra-vel-quote-case-policy.php',
  controller: 'class-tra-vel-quote-case-controller.php',
  capabilities: 'class-tra-vel-quote-case-capabilities.php',
  admin: 'class-tra-vel-quote-case-admin.php',
};

const publicCaseFields = [
  'contract_version',
  'case_id',
  'reference',
  'status',
  'status_label',
  'ownership',
  'version',
  'source',
  'summary',
  'next_action',
  'events',
  'created_at',
  'updated_at',
  'retention_until',
];

const publicEventDataFields = [
  'request_revision',
  'service_mode',
  'owner_scope',
  'previous_status',
  'channel',
  'provider',
  'expires_at',
  'dispatched',
];

const fail = message => failures.push(message);
const readJson = filename => {
  try {
    return JSON.parse(readFileSync(join(schemaRoot, filename), 'utf8'));
  } catch (error) {
    fail(`${filename} must contain valid JSON: ${error.message}`);
    return {};
  }
};
const sameMembers = (left, right) => (
  Array.isArray(left)
  && left.length === right.length
  && right.every(value => left.includes(value))
);
const extractBetween = (contents, start, end) => {
  const startIndex = contents.indexOf(start);
  if (startIndex < 0) return '';
  const endIndex = contents.indexOf(end, startIndex + start.length);
  return endIndex < 0 ? contents.slice(startIndex) : contents.slice(startIndex, endIndex);
};
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
const assertNestedObjectSchemasClosed = (schema, label, path = label) => {
  if (!schema || typeof schema !== 'object') return;
  if (schema.type === 'object') assertClosedObject(schema, path);
  for (const [key, child] of Object.entries(schema.properties || {})) {
    assertNestedObjectSchemasClosed(child, label, `${path}.${key}`);
  }
  if (schema.items) assertNestedObjectSchemasClosed(schema.items, label, `${path}[]`);
  for (const keyword of ['allOf', 'anyOf', 'oneOf']) {
    (schema[keyword] || []).forEach((child, index) => (
      assertNestedObjectSchemasClosed(child, label, `${path}.${keyword}[${index}]`)
    ));
  }
};
const assertStatusEnum = (values, label) => {
  if (!sameMembers(values, statuses)) {
    fail(`${label} must use exactly the assisted quote statuses: ${statuses.join(', ')}.`);
  }
};
const requireMarkers = (contents, label, markers) => {
  for (const [marker, message] of markers) {
    if (!contents.includes(marker)) fail(`${label}: ${message}`);
  }
};
const readProductionFile = key => {
  const filename = productionFiles[key];
  const path = join(includesRoot, filename);
  return existsSync(path) ? readFileSync(path, 'utf8') : null;
};

const quoteCase = readJson('quote-case.schema.json');
const quoteEvent = readJson('quote-case-event.schema.json');

for (const [label, schema] of [
  ['QuoteCase', quoteCase],
  ['QuoteCaseEvent', quoteEvent],
]) {
  if (schema?.$schema !== 'http://json-schema.org/draft-07/schema#') {
    fail(`${label} must use the repository Draft 7 contract convention.`);
  }
  assertClosedObject(schema, label);
  assertNestedObjectSchemasClosed(schema, label);
}

assertRequired(quoteCase, [
  'contract_version',
  'case_id',
  'reference',
  'status',
  'status_label',
  'ownership',
  'version',
  'source',
  'summary',
  'next_action',
  'events',
  'created_at',
  'updated_at',
  'retention_until',
], 'QuoteCase');
if (!sameMembers(Object.keys(quoteCase.properties || {}), publicCaseFields)) {
  fail(`QuoteCase properties must match the public wrapper exactly: ${publicCaseFields.join(', ')}.`);
}
if (quoteCase.properties?.contract_version?.const !== '1.0.0') fail('QuoteCase contract version must remain 1.0.0.');
if (quoteCase.properties?.case_id?.format !== 'uuid') fail('QuoteCase.case_id must be a UUID.');
if (quoteCase.properties?.reference?.pattern !== '^TV-[A-Z0-9]{8}$') fail('QuoteCase.reference must be an opaque TV-XXXXXXXX reference.');
if (quoteCase.properties?.version?.minimum !== 1) fail('QuoteCase.version must start at one for optimistic concurrency.');
assertStatusEnum(quoteCase.properties?.status?.enum, 'QuoteCase.status');
if (quoteCase.properties?.status_label?.type !== 'string' || quoteCase.properties?.status_label?.minLength !== 1) fail('QuoteCase.status_label must be a non-empty traveler-facing label.');
if (!sameMembers(quoteCase.properties?.ownership?.enum, ['account', 'private_browser_owner'])) fail('QuoteCase.ownership must distinguish exact account and private-browser ownership.');

const source = quoteCase.properties?.source;
assertClosedObject(source, 'QuoteCase.source');
assertRequired(source, ['run_id', 'request_id', 'request_revision', 'request_digest'], 'QuoteCase.source');
if (source?.properties?.run_id?.format !== 'uuid') fail('QuoteCase.source.run_id must be a UUID.');
if (source?.properties?.request_id?.format !== 'uuid') fail('QuoteCase.source.request_id must be a UUID.');
if (source?.properties?.request_revision?.minimum !== 1) fail('QuoteCase.source.request_revision must be at least one.');
if (source?.properties?.request_digest?.pattern !== '^[a-f0-9]{64}$') fail('QuoteCase.source.request_digest must bind the case to an exact SHA-256 request snapshot.');
const summary = quoteCase.properties?.summary;
assertClosedObject(summary, 'QuoteCase.summary');
assertRequired(summary, ['title', 'language', 'origin', 'destination_mode', 'destinations', 'date_text', 'date_flexibility', 'travelers', 'budget', 'scope'], 'QuoteCase.summary');
if (!sameMembers(summary?.properties?.language?.enum, ['he', 'en', 'mixed'])) fail('QuoteCase.summary.language must preserve a bounded language mode.');
if (!sameMembers(summary?.properties?.destination_mode?.enum, ['fixed', 'anywhere', 'flexible', 'unknown'])) fail('QuoteCase.summary.destination_mode must preserve the structured request modes.');
if (!sameMembers(summary?.properties?.date_flexibility?.enum, ['exact', 'flexible', 'unknown'])) fail('QuoteCase.summary.date_flexibility must preserve a bounded date mode.');
if (summary?.properties?.origin?.maxLength !== 120 || summary?.properties?.date_text?.maxLength !== 120) fail('QuoteCase.summary route and date text must retain their 120-character privacy bounds.');
if (summary?.properties?.destinations?.maxItems !== 8 || summary?.properties?.destinations?.uniqueItems !== true || summary?.properties?.destinations?.items?.maxLength !== 80) {
  fail('QuoteCase.summary.destinations must remain a unique list of at most eight bounded destinations.');
}
const travelers = summary?.properties?.travelers;
assertClosedObject(travelers, 'QuoteCase.summary.travelers');
assertRequired(travelers, ['adults', 'children', 'child_ages', 'rooms'], 'QuoteCase.summary.travelers');
if (travelers?.properties?.child_ages?.maxItems !== 20 || travelers?.properties?.child_ages?.items?.maximum !== 17) fail('QuoteCase.summary.travelers.child_ages must remain bounded.');
const budget = summary?.properties?.budget;
assertClosedObject(budget, 'QuoteCase.summary.budget');
assertRequired(budget, ['amount', 'currency', 'flexibility'], 'QuoteCase.summary.budget');
if (!sameMembers(budget?.properties?.currency?.enum, ['ILS', 'USD', 'EUR', 'UNKNOWN'])) fail('QuoteCase.summary.budget must disclose the supported currency or UNKNOWN.');
if (!sameMembers(budget?.properties?.flexibility?.enum, ['hard', 'soft', 'unknown'])) fail('QuoteCase.summary.budget.flexibility must remain bounded.');
if (budget?.properties?.amount?.minimum !== 0 || budget?.properties?.amount?.maximum !== 1000000000) fail('QuoteCase.summary.budget.amount must retain the server-side numeric bounds.');
if (!sameMembers(summary?.properties?.scope?.items?.enum, ['flights', 'accommodation', 'transfers', 'activities', 'dining', 'insurance', 'connectivity', 'equipment'])) fail('QuoteCase.summary.scope must preserve every supported planning vertical.');
if (summary?.properties?.scope?.maxItems !== 8 || summary?.properties?.scope?.uniqueItems !== true) fail('QuoteCase.summary.scope must remain a unique allowlisted list of at most eight verticals.');
const nextAction = quoteCase.properties?.next_action;
assertClosedObject(nextAction, 'QuoteCase.next_action');
assertRequired(nextAction, ['actor', 'code', 'label'], 'QuoteCase.next_action');
if (!sameMembers(nextAction?.properties?.actor?.enum, ['tra-vel', 'traveler', 'none'])) fail('QuoteCase.next_action.actor must make ownership explicit.');
if (nextAction?.properties?.code?.pattern !== '^[a-z]+(?:_[a-z]+)*$') fail('QuoteCase.next_action.code must be a canonical machine-readable action.');
if (quoteCase.properties?.events?.type !== 'array' || quoteCase.properties?.events?.items?.$ref !== quoteEvent.$id || !String(quoteEvent.$id || '').includes('/wp-json/tra-vel-agent/v1/schema/quote-case-event#1.0.0')) {
  fail('QuoteCase.events must reference the exact resolvable QuoteCaseEvent schema id.');
}
if (quoteCase.properties?.events?.minItems !== 1) fail('QuoteCase.events must include the creation event.');
if (quoteCase.properties?.events?.maxItems !== 20) fail('QuoteCase.events must embed no more than 20 events.');
for (const timestamp of ['created_at', 'updated_at', 'retention_until']) {
  if (quoteCase.properties?.[timestamp]?.format !== 'date-time') fail(`QuoteCase.${timestamp} must use a date-time value.`);
}

assertRequired(quoteEvent, [
  'contract_version',
  'event_id',
  'sequence',
  'type',
  'from_status',
  'to_status',
  'actor_type',
  'source',
  'visibility',
  'message',
  'data',
  'occurred_at',
], 'QuoteCaseEvent');
if (quoteEvent.properties?.contract_version?.const !== '1.0.0') fail('QuoteCaseEvent contract version must remain 1.0.0.');
if (quoteEvent.properties?.event_id?.format !== 'uuid') fail('QuoteCaseEvent.event_id must be a UUID.');
if (quoteEvent.properties?.sequence?.minimum !== 1) fail('QuoteCaseEvent.sequence must be one-based.');
if (quoteEvent.properties?.type?.pattern !== '^[a-z]+(?:[._][a-z]+)+$') fail('QuoteCaseEvent.type must use a canonical namespaced event name.');
const fromStatus = quoteEvent.properties?.from_status?.anyOf || [];
const fromStatusEnum = fromStatus.find(option => Array.isArray(option?.enum))?.enum;
if (!fromStatus.some(option => option?.type === 'null')) fail('QuoteCaseEvent.from_status must allow null for the creation event.');
assertStatusEnum(fromStatusEnum, 'QuoteCaseEvent.from_status');
assertStatusEnum(quoteEvent.properties?.to_status?.enum, 'QuoteCaseEvent.to_status');
if (!sameMembers(quoteEvent.properties?.actor_type?.enum, ['system', 'traveler', 'operator'])) fail('QuoteCaseEvent.actor_type must distinguish system, traveler, and operator actions.');
if (!sameMembers(quoteEvent.properties?.source?.enum, ['web', 'operator', 'account', 'agent', 'cron'])) fail('QuoteCaseEvent.source must preserve web, operator, account, agent, and retention-job provenance.');
if (!sameMembers(quoteEvent.properties?.visibility?.enum, ['public', 'internal'])) fail('QuoteCaseEvent.visibility must make public versus internal disclosure explicit.');
const eventData = quoteEvent.properties?.data;
assertClosedObject(eventData, 'QuoteCaseEvent.data');
if (!sameMembers(Object.keys(eventData?.properties || {}), publicEventDataFields)) {
  fail(`QuoteCaseEvent.data must expose only the bounded event payload allowlist: ${publicEventDataFields.join(', ')}.`);
}
if (eventData?.maxProperties !== 4) fail('QuoteCaseEvent.data must remain bounded to four allowlisted properties.');
if (eventData?.properties?.service_mode?.const !== 'assisted_quote') fail('QuoteCaseEvent.data.service_mode must not imply a bookable service.');
if (eventData?.properties?.channel?.const !== 'whatsapp' || eventData?.properties?.dispatched?.const !== false) fail('QuoteCaseEvent handoff data must identify the verified channel and explicitly deny dispatch.');
if (!sameMembers(eventData?.properties?.owner_scope?.enum, ['account', 'private_browser_owner'])) fail('QuoteCaseEvent.data.owner_scope must use the public ownership modes.');
assertStatusEnum(eventData?.properties?.previous_status?.enum, 'QuoteCaseEvent.data.previous_status');
if (quoteEvent.properties?.occurred_at?.format !== 'date-time') fail('QuoteCaseEvent.occurred_at must use a date-time value.');

const storePhp = readProductionFile('store');
if (storePhp !== null) {
  requireMarkers(storePhp, 'Quote case store', [
    ['quote_cases', 'must name the dedicated persistent quote-case table.'],
    ['request_digest', 'must persist the exact structured request digest.'],
    ['request_revision', 'must persist the structured request revision.'],
    ['retention_until', 'must persist an explicit retention deadline.'],
    ['installed_schema_version', 'must expose the installed database version separately from the code contract.'],
    ['tables_ready', 'must expose an explicit database readiness result.'],
    ['SHOW COLUMNS', 'must inspect the installed table shape before reporting readiness.'],
    ['SHOW TABLE STATUS', 'must verify that durable tables use a transactional engine.'],
    ['SHOW INDEX', 'must verify the unique indexes used for ownership, sequencing, and replay safety.'],
    ['transactional_tables', 'must expose the number of InnoDB-ready tables.'],
    ['required_indexes', 'must expose the required unique-index count.'],
    ['ready_indexes', 'must expose the installed unique-index count.'],
    ['required_indexes_ready', 'must expose unique-index readiness to the deployment gate.'],
    ['required_supporting_indexes', 'must expose required non-unique lookup-index readiness.'],
    ['supporting_indexes_ready', 'must fail health when the revision digest lookup index is missing or still unique.'],
    ['ensure_revision_digest_index', 'must repair the legacy unique revision digest index during migration.'],
    ['$wpdb->prepare', 'must use prepared SQL for variable database queries.'],
    ['get_event_page', 'must expose bounded cursor-style event pages.'],
    ['has_more', 'must tell clients when another event page exists.'],
    ['recover_owner_from_run', 'must close the lost-cookie response window through verified source-run ownership.'],
    ['bind_source_run_owner', 'must bind a claimed quote and its source AgentRun to the same account.'],
    ['tra_vel_quote_case_source_owner_conflict', 'must fail closed when the source plan belongs to another account.'],
    ['find_reusable_handoff', 'must reuse a still-current assisted-contact preparation.'],
    ['count_recent_handoffs', 'must throttle repeated assisted-contact preparation.'],
    ['delete_retention_batch', 'must delete retained quote cases in bounded transactional batches.'],
    ['expire_service_batch', 'must process service expiry in bounded batches.'],
    ['sweep_idempotency_batch', 'must remove expired or orphaned replay rows in bounded batches.'],
    ['sweep_orphan_case_rows_batch', 'must heal historical orphan revision and event rows in bounded batches.'],
    ['mutation_replay_after_rollback', 'must recover a concurrent same-key idempotent winner after rollback.'],
    ['SYNC_RETRY_LIMIT', 'must bound durable retries for committed run revisions.'],
    ['schedule_sync_retry', 'must retry transient synchronization failures and stale guest-to-account hook ordering.'],
    ['schedule_sync_retry_by_uuid', 'must requeue a durable sync before reading an unavailable AgentRun store.'],
    ['run_scheduled_sync', 'must centralize the registered fail-closed retry callback.'],
    ['should_sync_snapshot', 'must reject duplicate and stale AgentRun revisions before changing human workflow state.'],
    ['$case_read_error', 'must distinguish a missing case from a transient authoritative SELECT failure.'],
    ['$run_read_error', 'must distinguish a missing AgentRun from a transient authoritative SELECT failure.'],
  ]);
  if (!/UNIQUE\s+(?:KEY|INDEX)[^(]*\(\s*source_run_uuid\s*\)/i.test(storePhp)) {
    fail('Quote case store must enforce one durable case per agent run with a unique source_run_uuid key.');
  }
  if (!/\(int\) \( \$run\['owner_user_id'\][\s\S]{0,120}!={1,2}[\s\S]{0,120}\(int\) \$case\['owner_user_id'\]/.test(storePhp)) {
    fail('Quote-case revision sync must require exact AgentRun and quote owner agreement.');
  }
  if ((storePhp.match(/ENGINE=InnoDB/g) || []).length < 4) fail('All four quote-case tables must explicitly use InnoDB for transactional guarantees.');
  if ((storePhp.match(/UNIQUE\s+KEY/gi) || []).length !== 7) fail('Quote case DDL must declare exactly the seven required unique indexes verified by schema health.');
  if (!/const\s+DB_VERSION\s*=\s*'1\.0\.1'/.test(storePhp)) fail('Quote case DB version must advance for the revision digest index repair.');
  if (!/KEY\s+case_digest\s*\(case_id,request_digest\)/.test(storePhp) || /UNIQUE\s+KEY\s+case_digest/.test(storePhp)) {
    fail('Revision digest history must use a non-unique lookup index so A to B to A plan changes remain valid.');
  }
  if (!/DROP INDEX `case_digest`[\s\S]{0,240}ADD KEY `case_digest`/.test(storePhp)) fail('Quote case migration must explicitly replace the legacy unique digest index.');
  if (!/closed_at\s*=\s*NULL/.test(storePhp)) {
    fail('Quote case store must clear nullable close timestamps with SQL NULL so strict MySQL modes remain compatible.');
  }
  if (!/const\s+EVENT_PAGE_SIZE\s*=\s*50\s*;/.test(storePhp) || !/const\s+EMBEDDED_EVENTS\s*=\s*20\s*;/.test(storePhp)) {
    fail('Quote case store must keep event pages at 50 by default and embedded histories at 20.');
  }
  if (!/LIMIT\s+%d/.test(storePhp) || !/\$limit\s*\+\s*1/.test(storePhp)) fail('Quote case event pages must fetch one look-ahead row for has_more.');
  if (!/const\s+HANDOFF_REUSE_SECONDS\s*=\s*300\s*;/.test(storePhp) || !/const\s+HANDOFF_MIN_REMAINING_SECONDS\s*=\s*30\s*;/.test(storePhp) || !/const\s+HANDOFF_WINDOW_SECONDS\s*=\s*3600\s*;/.test(storePhp) || !/const\s+HANDOFF_WINDOW_LIMIT\s*=\s*6\s*;/.test(storePhp)) {
    fail('Quote case handoffs must reuse for five minutes with 30 seconds remaining and allow no more than six new preparations per rolling hour.');
  }
  if (!/function\s+find_reusable_handoff\s*\([^)]*\$case_version/.test(storePhp) || !/\$row\[['"]case_version['"]\][\s\S]{0,120}\$case_version/.test(storePhp)) {
    fail('Quote case handoff reuse must be constrained to the current aggregate version.');
  }
  const syncPhp = extractBetween(storePhp, 'public function sync_from_run', 'public static function cleanup');
  if ((syncPhp.match(/schedule_sync_retry/g) || []).length < 6 || !/0 === \(int\) \( \$run\['owner_user_id'\][\s\S]{0,220}schedule_sync_retry/.test(syncPhp)) {
    fail('Every transient quote synchronization failure and stale guest-to-account listener ordering must schedule a bounded authoritative retry.');
  }
  if (!/source_request_revision\s*<\s*%d/.test(syncPhp)) fail('Quote synchronization SQL must reject a stale source revision even when aggregate version CAS still matches.');
  if (!syncPhp.includes("get_case_by_source_run( $run['run_uuid'] ?? '', $case_read_error )") || !/if \( '' !== \$case_read_error \)[\s\S]{0,120}schedule_sync_retry/.test(syncPhp)) fail('A failed authoritative quote-case SELECT must requeue rather than look like true absence.');
  const syncDecisionPhp = extractBetween(storePhp, 'private function should_sync_snapshot', 'private function schedule_sync_retry');
  if (!/hash_equals/.test(syncDecisionPhp) || !/snapshot\[['"]revision['"]\][\s\S]{0,120}>[\s\S]{0,120}case\[['"]source_request_revision['"]\]/.test(syncDecisionPhp)) {
    fail('Quote synchronization must no-op exact duplicate digests and accept only strictly newer source revisions, independent of operator status.');
  }
  const retentionPhp = extractBetween(storePhp, 'public static function cleanup', 'private function mutate_case');
  requireMarkers(retentionPhp, 'Quote case retention cleanup', [
    ['CLEANUP_BATCH_SIZE', 'must enforce a bounded deletion batch.'],
    ['CLEANUP_MAX_BATCHES', 'must cap work per cron invocation.'],
    ['CLEANUP_MAX_SECONDS', 'must enforce a cleanup time budget.'],
    ['FOR UPDATE', 'must lock the selected parent cases before deletion.'],
    ['START TRANSACTION', 'must group each retained-case deletion batch atomically.'],
    ['ROLLBACK', 'must roll back a partial retained-case deletion.'],
    ['COMMIT', 'must commit only a complete retained-case deletion batch.'],
    ['legal_hold', 'must exclude cases under legal hold.'],
    ['case_uuid', 'must remove same-case replay records with the retained aggregate.'],
    ['idempotency_table', 'must clean same-case and stale replay records.'],
    ['deleted_orphan_revisions', 'must report historical orphan request-snapshot cleanup.'],
    ['deleted_orphan_events', 'must report historical orphan event cleanup.'],
  ]);
}

const bootstrapPath = join(pluginRoot, 'tra-vel-agent-core.php');
if (existsSync(bootstrapPath)) {
  const bootstrapPhp = readFileSync(bootstrapPath, 'utf8');
  if (!/tra_vel_quote_case_sync_retry[\s\S]{0,180}Tra_Vel_Quote_Case_Store['"],\s*['"]run_scheduled_sync/.test(bootstrapPhp)) {
    fail('The durable quote retry hook must invoke the readiness-first registered callback instead of hydrating AgentRun storage inline.');
  }
}

const policyPhp = readProductionFile('policy');
if (policyPhp !== null) {
  requireMarkers(policyPhp, 'Quote case policy', statuses.map(status => [
    `'${status}'`,
    `must define the ${status} state.`,
  ]));
  if (!/transition|can_transition/i.test(policyPhp)) fail('Quote case policy must centralize legal status transitions.');
  if (!/ready_for_assistance[\s\S]*(?:closed_no_quote|cancelled)|(?:closed_no_quote|cancelled)[\s\S]*ready_for_assistance/.test(policyPhp)) {
    fail('Quote case policy must explicitly model ready-for-assistance and terminal outcomes.');
  }
  if (!/public_summary/.test(policyPhp) || !/next_action/.test(policyPhp)) fail('Quote case policy must derive the public summary and next action server-side.');
  const snapshotPhp = extractBetween(policyPhp, 'public static function snapshot', 'public static function digest');
  for (const unsafeField of ['hard_constraints', 'preferences', 'vibes', 'material_questions', 'assumptions', 'input_text', 'raw_prompt', 'provider_trace', 'source_trace', 'contact', 'email', 'phone', 'passport', 'payment', 'medical']) {
    if (snapshotPhp.includes(unsafeField)) fail(`Quote case durable snapshot must not retain free-form ${unsafeField}.`);
  }
  if (/\$request\[['"]summary['"]\]/.test(snapshotPhp)) fail('Quote case durable snapshot must derive its title instead of retaining the model-written summary.');
  requireMarkers(snapshotPhp, 'Quote case durable snapshot', [
    ['planning_text_list( $request[\'destinations\'] ?? array(), 8, 80 )', 'must redact and cap destinations to eight 80-character values.'],
    ['planning_text( $request[\'origin_text\'] ?? \'\', 120 )', 'must redact and cap origin text at 120 characters.'],
    ['planning_text( $request[\'date_text\'] ?? \'\', 120 )', 'must redact and cap date text at 120 characters.'],
    ['min( 1000000000', 'must cap the durable budget amount.'],
    ["array( 'flights', 'accommodation', 'transfers', 'activities', 'dining', 'insurance', 'connectivity', 'equipment' )", 'must allowlist retained planning verticals.'],
    ["'blocker_count'", 'must retain only the blocker count, not free-form blocker text.'],
  ]);
  requireMarkers(policyPhp, 'Quote case planning-text privacy filter', [
    ['contains_sensitive_pattern', 'must reject sensitive patterns in every retained model-written planning string.'],
    ['@[A-Z0-9.', 'must detect email addresses.'],
    ['https?:\\/\\/', 'must detect URLs.'],
    ['passport', 'must detect passport-like identifiers.'],
    ['diagnosis', 'must detect medical text.'],
  ]);
}

const controllerPhp = readProductionFile('controller');
if (controllerPhp !== null) {
  requireMarkers(controllerPhp, 'Quote case controller', [
    ['/quote-case', 'must register the traveler run quote-case route.'],
    ['/quote-cases', 'must register the operator quote-case collection route.'],
    ['permission_callback', 'must declare an authorization callback on every REST route.'],
    ['expected_version', 'must require optimistic concurrency for operator mutations.'],
    ['request_digest', 'must bind traveler cases to the structured request snapshot.'],
    ['retention_until', 'must expose the case retention deadline.'],
    ['recover_owner_from_run', 'must recover only through an already authorized source run.'],
    ['get_event_page', 'must use the bounded event-page store method.'],
    ["'has_more'", 'must expose event-page continuation state.'],
    ['consume_create_limit', 'must bound quote creation and lost-cookie ownership recovery before writes.'],
    ['tra_vel_quote_case_create_limit_per_run', 'must retain a small configurable per-source-run retry allowance.'],
    ['tra_vel_quote_case_create_limit_per_visitor', 'must limit fan-out across source runs.'],
  ]);
  if (!/["']limit["']\s*=>\s*array\([\s\S]*?["']maximum["']\s*=>\s*Tra_Vel_Quote_Case_Store::EVENT_PAGE_SIZE/.test(controllerPhp)) fail('Quote case event REST pages must enforce the store page-size maximum.');
  if (!/can_access_run|authorize_run|access_run|agent_store->can_access/i.test(controllerPhp)) fail('Quote case controller must reuse or enforce run-owner access for traveler routes.');
  if (!/Tra_Vel_Quote_Case_Store::is_ready\(\)\s*&&\s*Tra_Vel_Agent_Store::is_ready\(\)/.test(controllerPhp)) fail('Every quote route must fail closed unless both transactional stores are ready.');
  if (!/consume_create_limit\([\s\S]{0,260}principal\( true \)/.test(controllerPhp)) fail('Quote creation must reserve its atomic retry allowance before generating or rotating an owner token.');
  if (!/tra_vel_manage_quote_cases/.test(controllerPhp)) fail('Quote case controller must protect operator routes with the dedicated capability.');
  if (/input_text|raw_prompt|passport|payment_card/i.test(controllerPhp)) fail('Quote case controller must not persist or expose raw prompts, passports, or payment-card data.');

  const publicCasePhp = extractBetween(controllerPhp, 'public function public_case', 'private function operator_case');
  if (!publicCasePhp) {
    fail('Quote case controller must define one authoritative public_case presenter.');
  } else {
    for (const field of publicCaseFields) {
      if (!new RegExp(`['"]${field}['"]\\s*=>`).test(publicCasePhp)) fail(`Quote case public wrapper must emit ${field}.`);
    }
    for (const internalField of ['id', 'owner_user_id', 'owner_token_hash', 'assigned_user_id', 'consent_version', 'consented_at', 'snapshot', 'legal_hold']) {
      if (new RegExp(`['"]${internalField}['"]\\s*=>`).test(publicCasePhp)) fail(`Quote case public wrapper must not expose internal field ${internalField}.`);
    }
  }
}

const capabilitiesPhp = readProductionFile('capabilities');
if (capabilitiesPhp !== null) {
  requireMarkers(capabilitiesPhp, 'Quote case capabilities', [
    ['tra_vel_manage_quote_cases', 'must define the dedicated operator capability.'],
    ['administrator', 'must seed the capability for administrators.'],
  ]);
  if (!/add_cap\s*\(/.test(capabilitiesPhp)) fail('Quote case capabilities must add the operator capability explicitly.');
}

const adminPhp = readProductionFile('admin');
if (adminPhp !== null) {
  requireMarkers(adminPhp, 'Quote case admin', [
    ['Tra_Vel_Quote_Case_Capabilities::VIEW_CASES', 'must protect the operator console with the dedicated view capability.'],
    ["wp_create_nonce( 'wp_rest' )", 'must pass a REST nonce to its authenticated operator client.'],
  ]);
  if (!/add_(?:menu|submenu)_page\s*\(/.test(adminPhp)) fail('Quote case admin must register a discoverable operator queue page.');
  if (!/esc_html|esc_attr|wp_kses/.test(adminPhp)) fail('Quote case admin must escape rendered case data.');
}

if (failures.length) {
  console.error('Tra-Vel quote case contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

const activeProductionChecks = Object.entries(productionFiles)
  .filter(([, filename]) => existsSync(join(includesRoot, filename)))
  .map(([key]) => key);
const productionSuffix = activeProductionChecks.length
  ? ` Production checks: ${activeProductionChecks.join(', ')}.`
  : ' Production classes are not present yet; schema checks are active.';

console.log(`Tra-Vel quote case contract validation passed (closed bounded schemas, minimized snapshots, ownership recovery, paginated events, handoff controls, transactional retention).${productionSuffix}`);
