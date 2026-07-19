import {readFileSync} from 'node:fs';
import {join, resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const vipDir = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'vip');
const storePath = join(vipDir, 'class-tra-vel-vip-intake-store.php');
const controllerPath = join(vipDir, 'class-tra-vel-vip-intake-controller.php');
const receiptSchemaPath = join(root, 'plugin', 'tra-vel-agent-core', 'schemas', 'vip-intake-receipt.schema.json');
const vipBootstrapPath = join(vipDir, 'bootstrap.php');
const pluginPath = join(root, 'plugin', 'tra-vel-agent-core', 'tra-vel-agent-core.php');
const agentControllerPath = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'class-tra-vel-agent-controller.php');
const uninstallPath = join(root, 'plugin', 'tra-vel-agent-core', 'uninstall.php');
const workflowPaths = [
  join(root, '.github', 'workflows', 'theme-ci.yml'),
  join(root, '.github', 'workflows', 'deploy-agent-core.yml'),
];
const failures = [];

let store = '';
let controller = '';
let vipBootstrap = '';
let plugin = '';
let agentController = '';
let uninstall = '';
let workflows = [];
let schema = {};
try {
  store = readFileSync(storePath, 'utf8');
  controller = readFileSync(controllerPath, 'utf8');
  vipBootstrap = readFileSync(vipBootstrapPath, 'utf8');
  plugin = readFileSync(pluginPath, 'utf8');
  agentController = readFileSync(agentControllerPath, 'utf8');
  uninstall = readFileSync(uninstallPath, 'utf8');
  workflows = workflowPaths.map(file => readFileSync(file, 'utf8'));
  schema = JSON.parse(readFileSync(receiptSchemaPath, 'utf8'));
} catch (error) {
  failures.push(`VIP intake REST slice is missing or invalid: ${error.message}`);
}

const requireText = (source, marker, message) => {
  if (!source.includes(marker)) failures.push(message);
};

if (schema.$schema !== 'http://json-schema.org/draft-07/schema#') failures.push('Receipt contract must use JSON Schema Draft-07.');
if (schema.$id !== 'https://tra-vel.co.il/schemas/vip-intake-receipt.schema.json') failures.push('Receipt contract has the wrong canonical ID.');
if (schema.additionalProperties !== false) failures.push('Receipt root must reject unknown fields.');
const expectedStates = ['received', 'checking', 'need_information', 'human_review'];
if (JSON.stringify(schema.properties?.state?.enum) !== JSON.stringify(expectedStates)) failures.push('Receipt state must be exactly received/checking/need_information/human_review.');
if (schema.properties?.accepted?.const !== true || schema.properties?.login_required?.const !== false) failures.push('Receipt must truthfully confirm acceptance without a login gate.');
if (schema.properties?.supplier_action_started?.const !== false || schema.properties?.payment_action_started?.const !== false) failures.push('Receipt must structurally deny supplier and payment execution claims.');
const verification = schema.definitions?.verification;
if (verification?.additionalProperties !== false || verification?.properties?.authorization_effect?.const !== 'none' || verification?.properties?.executable_scopes?.maxItems !== 0) {
  failures.push('Receipt verification boundary must have no authorization effect and no executable scope.');
}
const resumeOperations = schema.definitions?.resume?.properties?.allowed_operations?.items?.enum || [];
if (JSON.stringify(resumeOperations) !== JSON.stringify(['receipt_view', 'incident_follow_up'])) failures.push('No-login resume may expose only receipt view and incident follow-up.');
const disposition = schema.definitions?.messageDisposition;
if (disposition?.additionalProperties !== false || disposition?.properties?.raw_message_received_by_bridge?.const !== false || disposition?.properties?.normalized_vault_reference_received?.const !== true || disposition?.properties?.classifier_claim_verified?.const !== true) {
  failures.push('Receipt must truthfully identify an attested normalized vault reference and deny raw-message receipt.');
}

const visit = (value, pointer = '#') => {
  if (!value || typeof value !== 'object') return;
  if (!Array.isArray(value) && value.type === 'object' && value.additionalProperties !== false) failures.push(`Object ${pointer} is open to unknown fields.`);
  if (Array.isArray(value)) value.forEach((child, index) => visit(child, `${pointer}/${index}`));
  else Object.entries(value).forEach(([key, child]) => visit(child, `${pointer}/${key}`));
};
visit(schema);

for (const marker of [
  "class Tra_Vel_VIP_Intake_Controller extends WP_REST_Controller",
  "$this->namespace = 'tra-vel-agent/v1'",
  "$this->rest_base = 'vip/intakes'",
  'WP_REST_Server::CREATABLE',
  'WP_REST_Server::READABLE',
  "'permission_callback' => array( $this, 'can_create' )",
  "'permission_callback' => array( $this, 'can_read' )",
  'function validate_envelope_arg',
  'function validate_normalization_attestation_arg',
  'function issue_normalization_attestation',
  'function verify_normalization_attestation',
  'function public_browser_boundary',
  'function server_normalize_envelope',
  'function same_site_mutation',
  "hash_equals( hash( 'sha256', $key ), $value['idempotency_digest'] )",
  "Tra_Vel_VIP_Intake_Policy::intake( $value )",
  "Tra_Vel_VIP_Intake_Policy::intake( $normalized )",
  "'purpose'            => 'vip_intake_normalized'",
  "'message_vault_ref'  => (string) $envelope['content']['message_vault_ref']",
  "hash_hmac( 'sha256', Tra_Vel_VIP_Intake_Policy::canonical_digest( $unsigned ), wp_salt( 'auth' ) )",
  "apply_filters( 'tra_vel_vip_intake_normalization_attestation_issuable', false",
  'normalization_upstream_unavailable',
  'normalization_attestation_mismatch',
  'normalization_attestation_expired',
  'Secure; HttpOnly; SameSite=Strict',
  "'Cache-Control', 'private, no-store, max-age=0'",
  "'X-Robots-Tag', 'noindex, nofollow, noarchive'",
]) requireText(controller, marker, `VIP intake controller is missing ${marker}.`);

if (/function\s+(?:register_routes|can_create|can_read|create_intake|get_receipt)\s*\([^)]*WP_REST_Request/.test(controller)) {
  failures.push('Controller overrides/callbacks must keep untyped request signatures for PHP/WordPress compatibility.');
}
if (controller.includes("'permission_callback' => '__return_true'")) failures.push('A private intake or receipt route was made publicly readable.');
if (/['"]callback['"]\s*=>\s*array\(\s*\$this,\s*['"]issue_normalization_attestation['"]/.test(controller)) failures.push('The server-side normalization signer was exposed as a REST callback.');
if (!/get_current_user_id\(\) > 0[\s\S]{0,220}! \$nonce \|\| ! wp_verify_nonce\( \$nonce, 'wp_rest' \)/.test(controller)) failures.push('Signed-in same-site mutations do not require a valid REST nonce.');
if (!/hash_equals\( \$home_host, \$source_host \)[\s\S]{0,220}'https' !== \$source_scheme[\s\S]{0,220}\$source_port !== \$home_port/.test(controller)) failures.push('Mutation origin is not bound to exact HTTPS host, scheme, and port.');
if (!/function can_read[\s\S]{0,420}consume_read_limit[\s\S]{0,420}get_owned_receipt/.test(controller)) failures.push('Receipt reads must be rate-limited before the owner-scoped database lookup.');
if (/function get_receipt[\s\S]{0,220}consume_read_limit/.test(controller)) failures.push('Receipt callback must not double-consume the permission-check rate limit.');
for (const code of ['source_claim_rejected', 'authority_claim_rejected', 'trip_claim_rejected']) {
  if (!controller.includes(`tra_vel_vip_intake_${code}`)) failures.push(`Public browser boundary is missing ${code}.`);
}
for (const rawRead of ['$_POST', '$_GET']) {
  if (controller.includes(rawRead)) failures.push(`Controller reads ${rawRead} instead of WP_REST_Request.`);
}

for (const marker of [
  "class Tra_Vel_VIP_Intake_Store",
  "const RECEIPT_DAYS           = 30",
  "const RETENTION_DAYS         = 90",
  "const IDEMPOTENCY_DAYS       = 7",
  "const MAX_ENVELOPE_BYTES     = 65536",
  'function create_or_replay',
  'function get_owned_receipt',
  'function public_receipt',
  'function truthful_state',
  'function consume_limit',
  'function cleanup',
  "array( 'received', 'checking', 'need_information', 'human_review' )",
  "hash_hmac( 'sha256', (string) $key, wp_salt( 'nonce' ) )",
  "Tra_Vel_VIP_Intake_Policy::intake( $envelope, $indexes )",
  'Tra_Vel_VIP_Intake_State_Projection::project( $result )',
  "'authorization_effect'=> 'none'",
  "'executable_scopes'  => array()",
  "'supplier_action_started'=> false",
  "'payment_action_started' => false",
  "'raw_message_received_by_bridge'       => false",
  "'normalized_vault_reference_received' => true",
  "'classifier_claim_verified'            => true",
  "'START TRANSACTION'",
  "'COMMIT'",
  "'ROLLBACK'",
]) requireText(store, marker, `VIP intake store is missing ${marker}.`);

const tableNames = ['tra_vel_vip_intakes', 'tra_vel_vip_intake_receipts', 'tra_vel_vip_intake_idempotency', 'tra_vel_vip_intake_limits'];
for (const table of tableNames) if (!store.includes(table)) failures.push(`Store does not define ${table}.`);
const innodbCount = (store.match(/ENGINE=InnoDB/g) || []).length;
if (innodbCount !== 4) failures.push(`All four VIP intake tables must be transactional InnoDB tables (found ${innodbCount}).`);
for (const uniqueIndex of [
  'UNIQUE KEY intake_ref (intake_ref)',
  'UNIQUE KEY channel_event_digest (channel_event_digest)',
  'UNIQUE KEY correlation_digest (correlation_digest)',
  'UNIQUE KEY receipt_ref (receipt_ref)',
  'UNIQUE KEY operation_key (operation_scope,principal_hash,idempotency_key_hash)',
]) requireText(store, uniqueIndex, `Store is missing race-safe index ${uniqueIndex}.`);
for (const field of ['owner_token_hash', 'principal_hash', 'idempotency_key_hash', 'request_digest', 'normalization_attestation_digest', 'classifier_revision', 'normalization_issued_at', 'retention_until', 'legal_hold']) {
  requireText(store, field, `Store is missing bounded/private field ${field}.`);
}
for (const forbiddenColumn of ['message_body ', 'free_text ', 'phone_number ', 'email_address ', 'passport_number ', 'card_number ', 'bearer_token ', 'raw_provider_payload ']) {
  if (store.toLowerCase().includes(forbiddenColumn)) failures.push(`Store contains forbidden raw column ${forbiddenColumn.trim()}.`);
}
for (const forbiddenCall of ['wp_remote_get(', 'wp_remote_post(', 'wp_remote_request(', 'curl_exec(', '->reserve(', '->charge(', '->cancel(', '->refund(', '->dispatch(']) {
  if (store.includes(forbiddenCall) || controller.includes(forbiddenCall)) failures.push(`No-login receipt slice contains forbidden execution/network call ${forbiddenCall}.`);
}
if (!/owner_user_id[\s\S]{0,300}owner_token_hash/.test(store) || !/hash_equals\( \(string\) \$receipt\['owner_token_hash'\], \(string\) \$owner_token_hash \)/.test(store)) {
  failures.push('Receipt ownership is not bound to an account or constant-time browser token hash.');
}
if (!/ON DUPLICATE KEY UPDATE hits = IF\(expires_at <= %s,1,hits\+1\)/.test(store)) failures.push('Rate limiting is not an atomic bounded counter.');
if (!/DELETE FROM[\s\S]+idempotency_table[\s\S]+expires_at/.test(store) || !/DELETE FROM[\s\S]+receipts_table[\s\S]+retention_until/.test(store)) failures.push('Retention cleanup does not bound idempotency and private receipts.');

for (const requiredFile of ['class-tra-vel-vip-intake-store.php', 'class-tra-vel-vip-intake-controller.php']) {
  requireText(vipBootstrap, requiredFile, `VIP bootstrap does not load ${requiredFile}.`);
}
for (const lifecycleMarker of [
  'Tra_Vel_VIP_Intake_Store::install();',
  "array( 'Tra_Vel_VIP_Intake_Store', 'cleanup' )",
  'Tra_Vel_VIP_Intake_Store::maybe_upgrade();',
  '( new Tra_Vel_VIP_Intake_Controller() )->register_routes();',
]) requireText(plugin, lifecycleMarker, `Plugin lifecycle is missing ${lifecycleMarker}.`);
for (const healthMarker of [
  "'attested_trip_care_receipts' => $vip_intake_ready",
  "'raw_trip_care_intake'     => false",
  "'vip_intake_store' => $vip_intake_store_health",
  'Tra_Vel_VIP_Intake_Store::schema_health()',
]) requireText(agentController, healthMarker, `Agent health is missing truthful Trip Care marker ${healthMarker}.`);
for (const dataMarker of [
  'tra_vel_vip_intake_limits',
  'tra_vel_vip_intake_idempotency',
  'tra_vel_vip_intake_receipts',
  'tra_vel_vip_intakes',
  "delete_option( 'tra_vel_vip_intake_db_version' )",
  "delete_option( 'tra_vel_vip_intake_cleanup_status' )",
]) requireText(uninstall, dataMarker, `Opt-in uninstall is missing ${dataMarker}.`);
const uninstallOrder = [
  'tra_vel_vip_intake_limits',
  'tra_vel_vip_intake_idempotency',
  'tra_vel_vip_intake_receipts',
  'tra_vel_vip_intakes',
].map(table => uninstall.indexOf(table));
if (uninstallOrder.some(index => index < 0) || uninstallOrder.some((index, position) => position > 0 && index <= uninstallOrder[position - 1])) {
  failures.push('Opt-in uninstall must remove VIP intake tables in child-first order.');
}
for (const [index, workflow] of workflows.entries()) {
  requireText(workflow, 'php scripts/ci/validate-vip-intake-rest-runtime.php', `${workflowPaths[index]} does not run the VIP intake REST runtime gate.`);
  requireText(workflow, 'node scripts/ci/validate-vip-intake-rest-contract.mjs', `${workflowPaths[index]} does not run the VIP intake REST contract gate.`);
}

if (failures.length) {
  console.error('VIP intake REST contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('VIP intake REST contract passed (2 owner-scoped routes; 4 InnoDB tables; 4 truthful states; same-site/idempotent/rate-limited; zero execution authority).');
