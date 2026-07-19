import {readFileSync} from 'node:fs';
import {join, resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const vip = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'vip');
const paths = {
  policy: join(vip, 'class-tra-vel-vip-capability-session-policy.php'),
  store: join(vip, 'class-tra-vel-vip-capability-session-store.php'),
  controller: join(vip, 'class-tra-vel-vip-capability-session-controller.php'),
  schema: join(root, 'plugin', 'tra-vel-agent-core', 'schemas', 'vip-capability-session.schema.json'),
  bootstrap: join(vip, 'bootstrap.php'),
  plugin: join(root, 'plugin', 'tra-vel-agent-core', 'tra-vel-agent-core.php'),
  agentController: join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'class-tra-vel-agent-controller.php'),
  uninstall: join(root, 'plugin', 'tra-vel-agent-core', 'uninstall.php'),
  healthVerifier: join(root, 'scripts', 'ci', 'verify_agent_deploy.py'),
  healthValidator: join(root, 'scripts', 'ci', 'validate_agent_health_verification.py'),
  installBootstrap: join(root, 'scripts', 'wp', 'bootstrap-agent-core.ps1'),
  themeWorkflow: join(root, '.github', 'workflows', 'theme-ci.yml'),
  deployWorkflow: join(root, '.github', 'workflows', 'deploy-agent-core.yml'),
};

const failures = [];
const source = {};
let schema = {};
try {
  for (const key of ['policy', 'store', 'controller', 'bootstrap', 'plugin', 'agentController', 'uninstall', 'healthVerifier', 'healthValidator', 'installBootstrap', 'themeWorkflow', 'deployWorkflow']) {
    source[key] = readFileSync(paths[key], 'utf8');
  }
  schema = JSON.parse(readFileSync(paths.schema, 'utf8'));
} catch (error) {
  failures.push(`Capability-session slice is missing or unreadable: ${error.message}`);
}

const requireText = (area, marker, message) => {
  if (!source[area]?.includes(marker)) failures.push(message);
};

if (schema.$schema !== 'http://json-schema.org/draft-07/schema#') failures.push('Capability session must use JSON Schema Draft-07.');
if (schema.$id !== 'https://tra-vel.co.il/schemas/vip-capability-session.schema.json') failures.push('Capability session schema has the wrong canonical ID.');
if (schema.additionalProperties !== false) failures.push('Capability session root must reject unknown fields.');
const expectedSessionKeys = ['contract_version', 'session_ref', 'session_digest', 'capability_ref', 'capability_digest', 'trip_ref', 'case_ref', 'account_ref', 'allowed_scopes', 'disclosure_classes', 'rotation_generation', 'state', 'created_at', 'expires_at', 'revoked_at', 'authorization_effect', 'data_boundary'];
if (JSON.stringify(schema.required) !== JSON.stringify(expectedSessionKeys)) failures.push('Capability session schema does not require the exact private projection.');
const lowRisk = ['trip_view_redacted', 'incident_report', 'ordinary_evidence_add', 'safe_contact_update', 'case_progress_view', 'operator_contact_approve', 'decision_view'];
const highImpact = ['service_reserve', 'service_change', 'service_cancel', 'payment_authorize', 'refund_destination_change', 'identity_change', 'guardian_authority_change', 'sensitive_evidence_disclose', 'recovery_channel_change', 'delegate_manage'];
if (JSON.stringify(schema.properties?.allowed_scopes?.items?.enum) !== JSON.stringify(lowRisk)) failures.push('Session schema is not limited to the existing low-risk capability scopes.');
for (const scope of highImpact) if (schema.properties?.allowed_scopes?.items?.enum?.includes(scope)) failures.push(`Session schema grants high-impact scope ${scope}.`);
if (schema.properties?.authorization_effect?.const !== 'low_risk_capability_only') failures.push('Session schema overstates its authorization effect.');
if (JSON.stringify(schema.properties?.state?.enum) !== JSON.stringify(['active', 'revoked', 'expired'])) failures.push('Session schema has an open or ambiguous lifecycle.');
if (schema.properties?.rotation_generation?.maximum !== 2147483647) failures.push('Session schema does not enforce the bounded maximum rotation generation.');

const visit = (value, pointer = '#') => {
  if (!value || typeof value !== 'object') return;
  if (!Array.isArray(value) && value.type === 'object' && value.additionalProperties !== false) failures.push(`Object ${pointer} accepts unknown fields.`);
  if (Array.isArray(value)) value.forEach((child, index) => visit(child, `${pointer}/${index}`));
  else Object.entries(value).forEach(([key, child]) => visit(child, `${pointer}/${key}`));
};
visit(schema);

for (const marker of [
  'class Tra_Vel_VIP_Capability_Session_Policy',
  'const SESSION_TTL_SECONDS = 1800',
  'const MAX_ROTATION_GENERATION = 2147483647',
  'function issuance_request',
  'Tra_Vel_VIP_Taxonomy::LOW_RISK_CAPABILITY_SCOPES',
  'Tra_Vel_VIP_Taxonomy::HIGH_IMPACT_SCOPES',
  'function session',
  'function scanner_probe',
  "'mutation_performed'        => false",
  "'capability_state_disclosed'=> false",
  "'trip_state_disclosed'      => false",
  "'session_state_disclosed'   => false",
  'function public_session',
  "'supplier_action_started'=> false",
  "'payment_action_started' => false",
  "'denied_high_impact_scopes' => array_values( Tra_Vel_VIP_Taxonomy::HIGH_IMPACT_SCOPES )",
]) requireText('policy', marker, `Capability-session policy is missing ${marker}.`);
const maxGenerationDefinitions = [source.policy, source.store, source.controller].join('\n').match(/const\s+MAX_ROTATION_GENERATION\s*=/g) || [];
if (maxGenerationDefinitions.length !== 1) failures.push('Capability-session PHP must define exactly one maximum rotation-generation constant.');

for (const marker of [
  'class Tra_Vel_VIP_Capability_Session_Store',
  "const GRANT_RETENTION_DAYS  = 30",
  "const SESSION_RETENTION_DAYS = 30",
  "const IDEMPOTENCY_DAYS      = 2",
  "const DB_VERSION            = '1.1.0'",
  "const READINESS_CACHE_OPTION = 'tra_vel_vip_capability_session_readiness_cache'",
  'const READINESS_CACHE_TTL_SECONDS = 300',
  'function invalidate_readiness_cache',
  'function readiness_health',
  'function readiness_cache_valid',
  'function persist_readiness_cache',
  'function issue_server_grant',
  "apply_filters( 'tra_vel_vip_capability_grant_issuable', false",
  "true !== $authorized",
  'function exchange',
  "'START TRANSACTION'",
  "'COMMIT'",
  "'ROLLBACK'",
  'FOR UPDATE',
  'function recover_exact_session',
  'function derive_session_value',
  "wp_salt( 'secure_auth' )",
  'function current_session',
  'function resolve_scoped_session',
  "array( 'trip_ref', 'case_ref', 'account_ref' )",
  'function nullable_binding_equals',
  'function revoke_server_grant',
  'function rotate_server_grant_generation',
  'function mutate_server_grant',
  'mutation_reason_code',
  'mutation_previous_generation',
  'mutation_sessions_revoked',
  'grant_mutation_reason_conflict',
  "apply_filters( 'tra_vel_vip_capability_grant_mutation_authorized', false",
  'function revoke_session',
  'function consume_limit',
  'ON DUPLICATE KEY UPDATE hits = IF(expires_at <= %s,1,hits+1)',
  'function cleanup',
  'function schema_health',
]) requireText('store', marker, `Capability-session store is missing ${marker}.`);

for (const table of ['tra_vel_vip_capability_grants', 'tra_vel_vip_capability_sessions', 'tra_vel_vip_capability_exchanges', 'tra_vel_vip_capability_limits']) {
  requireText('store', table, `Capability-session store does not define ${table}.`);
}
if ((source.store.match(/ENGINE=InnoDB/g) || []).length !== 4) failures.push('All four capability-session tables must be transactional InnoDB tables.');
for (const index of [
  'UNIQUE KEY capability_ref (capability_ref)',
  'UNIQUE KEY capability_digest (capability_digest)',
  'UNIQUE KEY session_ref (session_ref)',
  'UNIQUE KEY session_digest (session_digest)',
  'UNIQUE KEY one_session_per_grant (grant_id)',
  'UNIQUE KEY grant_exchange_key (grant_id,idempotency_key_hash)',
  'PRIMARY KEY  (limit_key)',
]) requireText('store', index, `Capability-session store is missing race-safe index ${index}.`);
for (const field of ['capability_digest', 'session_digest', 'idempotency_key_hash', 'request_digest', 'rotation_generation', 'mutation_operation', 'mutation_reason_code', 'mutation_previous_generation', 'mutation_sessions_revoked', 'mutated_at', 'retention_until']) {
  requireText('store', field, `Capability-session store is missing digest/rotation/retention field ${field}.`);
}
for (const forbiddenColumn of ['bearer_token ', 'raw_token ', 'session_value ', 'exchange_value ', 'owner_digest ', 'provider_payload ', 'identity_number ', 'card_number ']) {
  const createSql = [...source.store.matchAll(/dbDelta\( "CREATE TABLE[\s\S]*?ENGINE=InnoDB \{\$charset\};" \);/g)].map(match => match[0].toLowerCase()).join('\n');
  if (createSql.includes(forbiddenColumn)) failures.push(`Database schema contains forbidden raw column ${forbiddenColumn.trim()}.`);
}
if (!/SELECT \* FROM ['" .]+self::grants_table\(\)[\s\S]{0,180}capability_digest = %s[\s\S]{0,80}FOR UPDATE/.test(source.store)) failures.push('Atomic consume does not lock the digest-selected grant.');
if (!/SELECT \* FROM ['" .]+self::exchanges_table\(\)[\s\S]{0,220}idempotency_key_hash = %s[\s\S]{0,80}FOR UPDATE/.test(source.store)) failures.push('Replay recovery does not lock the exact idempotency record.');
if (!/is_array\( \$prior \)[\s\S]{0,350}request_digest[\s\S]{0,250}idempotency_conflict/.test(source.store)) failures.push('Changed retries do not fail with an idempotency conflict.');
if (!/is_array\( \$prior \)[\s\S]{0,900}recover_exact_session[\s\S]{0,1700}null !== \$grant\['consumed_at'\]/.test(source.store)) failures.push('Exact replay is not recovered before the consumed-grant rejection.');
if (!/grant_rotation_generation[\s\S]{0,500}rotation_generation[\s\S]{0,350}session_missing/.test(source.store)) failures.push('Current session is not invalidated by grant rotation.');
if (!/grant_revoked_at[\s\S]{0,800}session_missing/.test(source.store)) failures.push('Current session is not invalidated by grant revocation.');
if (!/function mutate_server_grant[\s\S]{0,2600}SELECT id,rotation_generation,revoked_at,mutation_operation,mutation_reason_code[\s\S]{0,300}FOR UPDATE[\s\S]{0,3600}mutation_reason_code = %s[\s\S]{0,1500}SET state = 'revoked'[\s\S]{0,1500}mutation_sessions_revoked = %d[\s\S]{0,1000}'COMMIT'/.test(source.store)) failures.push('Server grant revoke/rotation does not persist its original reason, lock/version-check, invalidate sessions, and commit atomically.');
if (!/function consume_limit[\s\S]{0,1800}limit_store_unavailable[\s\S]{0,600}null === \$hits[\s\S]{0,300}limit_store_unavailable/.test(source.store)) failures.push('Limiter database failures do not fail closed as 503 errors.');
if (!/function resolve_scoped_session[\s\S]{0,900}trip_ref[\s\S]{0,600}case_ref[\s\S]{0,600}account_ref[\s\S]{0,600}nullable_binding_equals/.test(source.store)) failures.push('Scoped resolver does not enforce the exact closed trip/case/account binding.');
if (!/function maybe_upgrade[\s\S]{0,300}invalidate_readiness_cache[\s\S]{0,180}install/.test(source.store) || !/function install[\s\S]{0,180}invalidate_readiness_cache/.test(source.store)) failures.push('Readiness cache is not invalidated before install and upgrade inspection.');

for (const marker of [
  'class Tra_Vel_VIP_Capability_Session_Controller extends WP_REST_Controller',
  "$this->rest_base = 'vip/capability-session'",
  "'/probe'",
  "'/exchange'",
  "'/current'",
  "'/logout'",
  "'permission_callback' => array( $this, 'can_probe' )",
  "'permission_callback' => array( $this, 'can_exchange' )",
  "'permission_callback' => array( $this, 'can_read' )",
  "'permission_callback' => array( $this, 'can_logout' )",
  'function same_origin_mutation',
  'function consume_exchange_limit',
  'function consume_read_limit',
  'function consume_logout_limit',
  'const MAX_EXCHANGE_BODY_BYTES = 512',
  'function validated_exchange_body',
  "const SESSION_COOKIE = '__Host-tra_vel_vip_capability_session'",
  'Path=/; Secure; HttpOnly; SameSite=Strict',
  "'Cache-Control', 'private, no-store, max-age=0'",
  "'X-Robots-Tag', 'noindex, nofollow, noarchive'",
  "'Referrer-Policy', 'no-referrer'",
]) requireText('controller', marker, `Capability-session controller is missing ${marker}.`);
if (!/function probe\(\)[\s\S]{0,180}scanner_probe\(\)/.test(source.controller)) failures.push('Scanner probe is not a constant argument-free response.');
const probeStart = source.controller.indexOf('public function probe()');
const probeEnd = source.controller.indexOf('\n\tpublic function ', probeStart + 1);
const probeBody = probeStart >= 0 ? source.controller.slice(probeStart, probeEnd > probeStart ? probeEnd : probeStart + 400) : '';
if (/(store|COOKIE|get_param|get_header)/.test(probeBody)) failures.push('Scanner probe consults mutable or private request state.');
if (/['"]callback['"]\s*=>[\s\S]{0,80}(issue_server_grant|mint|issuance)/.test(source.controller)) failures.push('Server-only grant issuance is exposed as a REST callback.');
if (/['"]callback['"]\s*=>[\s\S]{0,100}(revoke_server_grant|rotate_server_grant_generation|mutate_server_grant)/.test(source.controller)) failures.push('Server-only grant revoke/rotation is exposed as a REST callback.');
if (/capability-session\/\(\?P<[^>]*(?:token|value|secret)/i.test(source.controller)) failures.push('A bearer value is accepted in a capability-session URL.');
if (!/function can_exchange\([\s\S]{0,500}validated_exchange_body[\s\S]{0,500}same_origin_mutation[\s\S]{0,500}consume_exchange_limit[\s\S]{0,500}is_ready/.test(source.controller)) failures.push('Exchange does not enforce body, exact origin, limiter, and cached readiness in safe order.');
if (!/function exchange\([\s\S]{0,2200}\$this->store->exchange/.test(source.controller)) failures.push('Exchange callback does not perform the locked grant lookup after authorization.');
if (!/function authorize_current_session[\s\S]{0,350}session_cookie_value[\s\S]{0,350}consume_read_limit[\s\S]{0,350}is_ready[\s\S]{0,350}\$this->store->current_session/.test(source.controller)) failures.push('Current session does not enforce cookie syntax, limiter, cached readiness, and lookup in safe order.');
if (!/function can_logout[\s\S]{0,350}same_origin_mutation[\s\S]{0,350}session_cookie_value[\s\S]{0,350}consume_logout_limit[\s\S]{0,350}is_ready/.test(source.controller)) failures.push('Logout does not enforce origin, cookie syntax, limiter, and cached readiness in safe order.');
if (!/get_header\( 'Origin' \)[\s\S]{0,900}'https' !== \$origin_scheme[\s\S]{0,400}hash_equals\( \$home_host, \$origin_host \)[\s\S]{0,200}\$origin_port !== \$home_port/.test(source.controller)) failures.push('Mutation origin is not bound to exact HTTPS scheme, host, and port.');
for (const component of ['PHP_URL_USER', 'PHP_URL_PASS', 'PHP_URL_PATH', 'PHP_URL_QUERY', 'PHP_URL_FRAGMENT']) {
  if (!source.controller.includes(`wp_parse_url( $origin, ${component} )`)) failures.push(`Mutation origin does not reject ${component}.`);
}
if (!/get_current_user_id\(\) > 0[\s\S]{0,260}wp_verify_nonce\( \$nonce, 'wp_rest' \)/.test(source.controller)) failures.push('Signed-in mutation does not require a REST nonce.');
if (!/function clear_session_cookie[\s\S]{0,260}Max-Age=0[\s\S]{0,180}Path=\/; Secure; HttpOnly; SameSite=Strict/.test(source.controller)) failures.push('Logout does not clear the exact hardened host cookie.');
if (source.controller.includes('Domain=')) failures.push('Capability cookie must remain __Host-compatible and cannot set Domain.');
for (const rawRead of ['$_POST', '$_GET']) if (source.controller.includes(rawRead)) failures.push(`Controller reads ${rawRead} instead of WP_REST_Request.`);
for (const forbiddenCall of ['wp_remote_get(', 'wp_remote_post(', 'wp_remote_request(', 'curl_exec(', '->reserve(', '->charge(', '->cancel(', '->refund(', '->dispatch(']) {
  if (source.policy.includes(forbiddenCall) || source.store.includes(forbiddenCall) || source.controller.includes(forbiddenCall)) failures.push(`Capability slice contains forbidden network/commercial call ${forbiddenCall}.`);
}

for (const file of ['class-tra-vel-vip-capability-session-policy.php', 'class-tra-vel-vip-capability-session-store.php', 'class-tra-vel-vip-capability-session-controller.php']) {
  requireText('bootstrap', file, `VIP bootstrap does not load ${file}.`);
}
for (const marker of [
  'Tra_Vel_VIP_Capability_Session_Store::install();',
  "array( 'Tra_Vel_VIP_Capability_Session_Store', 'cleanup' )",
  'Tra_Vel_VIP_Capability_Session_Store::maybe_upgrade();',
  '( new Tra_Vel_VIP_Capability_Session_Controller() )->register_routes();',
]) requireText('plugin', marker, `Plugin lifecycle is missing ${marker}.`);
for (const marker of [
  'Tra_Vel_VIP_Capability_Session_Store::schema_health()',
  'Tra_Vel_VIP_Capability_Session_Store::is_ready()',
  "'no_login_scoped_sessions' => $vip_capability_ready",
  "'vip_capability_session_store' => $vip_capability_store_health",
]) requireText('agentController', marker, `Public health is missing truthful capability-session marker ${marker}.`);
for (const marker of ['tra_vel_vip_capability_limits', 'tra_vel_vip_capability_exchanges', 'tra_vel_vip_capability_sessions', 'tra_vel_vip_capability_grants', "delete_option( 'tra_vel_vip_capability_session_db_version' )", "delete_option( 'tra_vel_vip_capability_session_cleanup_status' )", "delete_option( 'tra_vel_vip_capability_session_readiness_cache' )"]) {
  requireText('uninstall', marker, `Opt-in uninstall is missing ${marker}.`);
}
const uninstallOrder = ['tra_vel_vip_capability_limits', 'tra_vel_vip_capability_exchanges', 'tra_vel_vip_capability_sessions', 'tra_vel_vip_capability_grants'].map(name => source.uninstall.indexOf(name));
if (uninstallOrder.some(index => index < 0) || uninstallOrder.some((index, position) => position > 0 && index <= uninstallOrder[position - 1])) failures.push('Opt-in uninstall must remove capability tables child-first.');
for (const workflow of ['themeWorkflow', 'deployWorkflow']) {
  requireText(workflow, 'php scripts/ci/validate-vip-capability-session-runtime.php', `${workflow} does not run the capability runtime gate.`);
  requireText(workflow, 'node scripts/ci/validate-vip-capability-session-contract.mjs', `${workflow} does not run the capability contract gate.`);
}
requireText('deployWorkflow', '"scripts/ci/validate-vip-*.php"', 'Agent deploy trigger does not include VIP PHP gates.');
requireText('deployWorkflow', '"scripts/ci/validate-vip-*.mjs"', 'Agent deploy trigger does not include VIP contract gates.');
for (const marker of ['capabilities.get("no_login_scoped_sessions") is True', 'expected_capability_store', '"expected_tables": 4', '"required_indexes": 7']) {
  requireText('healthVerifier', marker, `Production deploy verifier is missing capability-session requirement ${marker}.`);
}
for (const marker of ['missing_no_login_capability', 'false_no_login_capability', 'missing_capability_store', 'capability_store_negative_values']) {
  requireText('healthValidator', marker, `Health verifier tests are missing independent negative case ${marker}.`);
}
for (const marker of ['$health.capabilities.no_login_scoped_sessions', "$capabilityHealth.schema_version -ne '1.1.0'", '[int]$capabilityHealth.expected_tables -ne 4', '[int]$capabilityHealth.required_indexes -ne 7']) {
  requireText('installBootstrap', marker, `Bootstrap health gate is missing capability-session requirement ${marker}.`);
}

if (failures.length) {
  console.error('VIP capability-session contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('VIP capability-session contract passed (4 private routes; 4 InnoDB tables/7 race indexes; bounded readiness cache; ordered fail-closed gates; closed three-ref binding; replay-safe mutation reason; zero high-impact authority).');
