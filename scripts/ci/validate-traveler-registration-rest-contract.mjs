import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const root = process.cwd();
const vip = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'vip');
const read = (path) => readFileSync(path, 'utf8');
const schemaSource = read(join(vip, 'class-tra-vel-traveler-registration-schema.php'));
const store = read(join(vip, 'class-tra-vel-traveler-registration-store.php'));
const controller = read(join(vip, 'class-tra-vel-traveler-registration-controller.php'));
const bootstrap = read(join(vip, 'bootstrap.php'));
const plugin = read(join(root, 'plugin', 'tra-vel-agent-core', 'tra-vel-agent-core.php'));
const uninstall = read(join(root, 'plugin', 'tra-vel-agent-core', 'uninstall.php'));
const runtime = read(join(root, 'scripts', 'ci', 'validate-traveler-registration-rest-runtime.php'));
const privateSchema = JSON.parse(read(join(root, 'plugin', 'tra-vel-agent-core', 'schemas', 'private', 'traveler-registration-aggregate.schema.json')));
const resourceSchema = JSON.parse(read(join(root, 'plugin', 'tra-vel-agent-core', 'schemas', 'traveler-registration-resource.schema.json')));
const registrationSchema = JSON.parse(read(join(root, 'plugin', 'tra-vel-agent-core', 'schemas', 'traveler-registration.schema.json')));
const workflows = [
  read(join(root, '.github', 'workflows', 'theme-ci.yml')),
  read(join(root, '.github', 'workflows', 'deploy-agent-core.yml')),
];

const failures = [];
const expect = (condition, message) => { if (!condition) failures.push(message); };
const includes = (source, marker, message) => expect(source.includes(marker), message);
const sameSet = (left, right) => left.length === right.length && left.every((item) => right.includes(item));

expect(privateSchema.$id === 'https://tra-vel.co.il/schemas/private/traveler-registration-aggregate-v1.schema.json', 'Private registration aggregate has the wrong canonical schema ID.');
expect(privateSchema.additionalProperties === false, 'Private registration aggregate must reject unknown fields.');
expect(privateSchema.properties?.registration?.$ref === '../traveler-registration.schema.json', 'Private aggregate must reuse the established traveler-registration schema.');
expect(privateSchema.properties?.authorization_effect?.const === 'registration_only', 'Private aggregate must grant registration-only authority.');
expect(privateSchema.properties?.executable_scopes?.maxItems === 0, 'Private aggregate must have no executable scopes.');
for (const flag of ['supplier_action_started', 'payment_action_started', 'booking_action_started', 'cancellation_action_started', 'refund_action_started']) {
  expect(privateSchema.properties?.side_effects?.properties?.[flag]?.const === false, `Private aggregate must pin ${flag} to false.`);
}
for (const flag of ['raw_identity_data_stored', 'raw_contact_data_stored', 'raw_document_data_stored', 'raw_payment_data_stored', 'raw_medical_data_stored', 'raw_provider_payload_stored', 'bearer_secret_stored']) {
  expect(privateSchema.properties?.data_boundary?.properties?.[flag]?.const === false, `Private aggregate must pin ${flag} to false.`);
}
expect(privateSchema.properties?.bindings?.properties?.profile_refs?.items?.pattern === '^tv_profile_[A-Za-z0-9_-]{16,96}$', 'Profile bindings must be opaque profile refs.');
expect(privateSchema.properties?.bindings?.properties?.vault_item_refs?.items?.pattern === '^tv_vault_item_[A-Za-z0-9_-]{16,96}$', 'Vault bindings must be opaque vault-item refs.');

expect(resourceSchema.$id === 'https://tra-vel.co.il/schemas/traveler-registration-resource-v1.schema.json', 'Minimized resource has the wrong canonical schema ID.');
expect(resourceSchema.additionalProperties === false, 'Minimized registration resource must reject unknown response fields.');
expect(resourceSchema.properties?.authorization?.properties?.effect?.const === 'registration_only', 'Public registration resource must state registration-only authority.');
expect(resourceSchema.properties?.authorization?.properties?.account_ownership_grants_trip_role_authority?.const === false, 'Public resource must deny role authority inference from account ownership.');
expect(!('account_ref' in (resourceSchema.properties || {})), 'Minimized resource must not expose account_ref.');
expect(!('profile_refs' in (resourceSchema.properties || {})) && !('vault_item_refs' in (resourceSchema.properties || {})), 'Minimized resource must not expose private profile/vault pointers.');
expect(!('evidence_digest' in (resourceSchema.properties?.requirements?.items?.properties || {})), 'Minimized requirement resources must not expose evidence digests.');
expect(sameSet(resourceSchema.properties?.requirements?.items?.properties?.code?.enum || [], registrationSchema.properties?.requirements?.items?.properties?.code?.enum || []), 'Minimized resource requirement vocabulary drifted from the established registration policy.');

for (const marker of ['class Tra_Vel_Traveler_Registration_Schema', 'create_input', 'update_input', 'Tra_Vel_VIP_Policy::traveler_registration(', 'Tra_Vel_Traveler_Profile_Taxonomy::CONTRACT_VERSION', "class_exists( 'Tra_Vel_Traveler_Profile_Policy' )", 'validate_aggregate', 'public_projection', "'account_ownership_grants_trip_role_authority'=> false", "'registration_only'", "'profile_refs'", "'vault_item_refs'"]) {
  includes(schemaSource, marker, `Registration schema helper is missing ${marker}.`);
}
includes(schemaSource, "array( 'trip_ref', 'role_manifest_ref', 'profile_refs', 'vault_item_refs', 'party_flags', 'requirements', 'idempotency_key' )", 'Create input must use an exact pointer-only allowlist.');
expect(!schemaSource.includes("'account_ref', 'role_manifest_ref', 'profile_refs'"), 'Client create input must not accept account_ref.');

includes(store, 'class Tra_Vel_Traveler_Registration_Store', 'Durable registration store class is missing.');
expect((store.match(/ENGINE=InnoDB/g) || []).length === 4, 'All four registration tables must require InnoDB transactions.');
for (const marker of [
  'tra_vel_traveler_registrations', 'tra_vel_traveler_registration_revisions', 'tra_vel_traveler_registration_transitions', 'tra_vel_traveler_registration_idempotency',
  'UNIQUE KEY owner_trip (owner_user_id,trip_ref)', 'UNIQUE KEY registration_version (registration_id,version)',
  'UNIQUE KEY registration_transition (registration_id,to_version)', 'UNIQUE KEY owner_operation_key (owner_user_id,operation_scope,idempotency_key_hash)',
  "Tra_Vel_VIP_Policy::registration_successor(", "'authorization_effect'    => 'registration_only'",
  "'START TRANSACTION'", "'COMMIT'", "'ROLLBACK'", 'FOR UPDATE', 'schema_health', 'inspect_schema', 'cleanup',
  'RETENTION_DAYS', 'IDEMPOTENCY_DAYS', 'MAX_AGGREGATE_BYTES', 'idempotency_hash', 'authority_digest',
  'tra_vel_traveler_registration_verified_evidence_authorized', "'verified_evidence_unattested'",
]) includes(store, marker, `Durable registration store is missing ${marker}.`);
expect(/WHERE owner_user_id = %d AND registration_ref = %s/.test(store), 'Registration lookup must constrain owner and opaque reference in the database predicate.');
expect(/WHERE c\.owner_user_id = %d AND c\.registration_ref = %s/.test(store), 'Immutable replay lookup must constrain owner before returning a revision.');
const updateStart = store.indexOf('public function update_registration');
const replayIndex = store.indexOf('idempotent_result(', updateStart);
const versionIndex = store.indexOf("$input['expected_version'] !==", updateStart);
expect(updateStart >= 0 && replayIndex > updateStart && versionIndex > replayIndex, 'Exact idempotency replay must resolve before optimistic version validation.');
expect(!/CREATE TABLE[\s\S]*?\b(?:email|phone|passport|card_number|medical_note|provider_payload)\b/i.test((store.match(/dbDelta\([\s\S]*?\);/g) || []).join('\n')), 'Registration tables contain a raw sensitive/provider column.');
expect(store.includes("self::owner_ref( 'account', $owner_user_id )") && store.includes("self::owner_ref( 'principal', $owner_user_id )"), 'Account and actor refs must be server-derived from the authenticated owner.');
expect(!/do_action\s*\(/.test(store) && !/apply_filters\s*\(\s*['\"](?:supplier|payment|booking|cancel|refund)/i.test(store), 'Registration storage must not dispatch a commercial side effect.');

includes(controller, 'extends WP_REST_Controller', 'Registration controller must extend WP_REST_Controller.');
for (const marker of [
  "WP_REST_Server::CREATABLE", "WP_REST_Server::READABLE", "WP_REST_Server::EDITABLE", "'permission_callback'",
  "get_current_user_id() < 1", "current_user_can( 'read' )", "get_header( 'X-WP-Nonce' )", "wp_verify_nonce( $nonce, 'wp_rest' )",
  "get_header( 'Origin' )", "registration_origin_rejected", 'get_owned_registration(', "'private, no-store, max-age=0'", "'noindex, nofollow, noarchive'",
]) includes(controller, marker, `Registration controller is missing ${marker}.`);
expect(!controller.includes("get_header( 'Referer' )"), 'Mutation origin must not silently fall back to Referer.');
expect(!controller.includes('__return_true'), 'Registration routes must not have an open permission callback.');
expect(!/WP_REST_Server::DELETABLE|function\s+(?:delete|cancel|book|pay|refund|reserve)_/i.test(controller), 'Registration controller must expose no delete/commercial execution endpoint.');
for (const forbidden of ["'evidence_digest' =>", "'authority_digest' =>", "'profile_refs' => $event", "'vault_item_refs' => $event"]) {
  expect(!controller.includes(forbidden), `Minimized controller response leaks ${forbidden}.`);
}

for (const file of ['class-tra-vel-traveler-registration-schema.php', 'class-tra-vel-traveler-registration-store.php', 'class-tra-vel-traveler-registration-controller.php']) {
  includes(bootstrap, `require_once __DIR__ . '/${file}';`, `VIP bootstrap does not load ${file}.`);
}
for (const marker of [
  'Tra_Vel_Traveler_Registration_Store::install();', 'Tra_Vel_Traveler_Registration_Store::maybe_upgrade();',
  "array( 'Tra_Vel_Traveler_Registration_Store', 'cleanup' )", '( new Tra_Vel_Traveler_Registration_Controller() )->register_routes();',
]) includes(plugin, marker, `Plugin lifecycle is missing ${marker}.`);
expect(uninstall.includes("defined( 'TRA_VEL_AGENT_REMOVE_DATA' )") && uninstall.includes('true !== TRA_VEL_AGENT_REMOVE_DATA'), 'Registration data removal must remain opt-in.');
for (const suffix of ['tra_vel_traveler_registration_idempotency', 'tra_vel_traveler_registration_transitions', 'tra_vel_traveler_registration_revisions', 'tra_vel_traveler_registrations']) includes(uninstall, suffix, `Opt-in uninstall is missing ${suffix}.`);
for (const option of ['tra_vel_traveler_registration_db_version', 'tra_vel_traveler_registration_cleanup_status']) includes(uninstall, option, `Opt-in uninstall is missing option ${option}.`);

for (const marker of [
  'cross-user registration read', 'cookie mutation without the exact REST nonce', 'same retry key accepted a changed request',
  'fresh key bypassed optimistic version conflict', 'dependent-adult support gate was bypassed', 'minor guardian-authority gate was bypassed',
  'caller-authored digest was treated as verified evidence without an upstream vault/profile attestation',
  'accessibility supplier-acknowledgement gate was bypassed', 'document change did not produce explicit downstream rollback/invalidation',
  'account owner changed role manifest through a progress transition', 'missing schema/tables did not fail closed',
]) includes(runtime, marker, `Adversarial runtime is missing: ${marker}.`);

for (const workflow of workflows) {
  includes(workflow, 'php scripts/ci/validate-traveler-registration-rest-runtime.php', 'Workflow does not run traveler-registration REST runtime gate.');
  includes(workflow, 'node scripts/ci/validate-traveler-registration-rest-contract.mjs', 'Workflow does not run traveler-registration REST contract gate.');
}

if (failures.length) {
  console.error('Traveler registration REST contract failed:');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('Traveler registration REST contract passed: durable owner-only revisions/transitions, exact CSRF and idempotency boundaries, conditional readiness, bounded retention, and zero commercial authority.');
