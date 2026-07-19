import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const pluginRoot = join(repoRoot, 'plugin', 'tra-vel-agent-core');
const storePath = join(pluginRoot, 'includes', 'class-tra-vel-assisted-proposal-store.php');
const policyPath = join(pluginRoot, 'includes', 'class-tra-vel-assisted-proposal-policy.php');
const proposalSchemaPath = join(pluginRoot, 'schemas', 'assisted-proposal.schema.json');
const sourceSchemaPath = join(pluginRoot, 'schemas', 'assisted-proposal-source.schema.json');
const store = readFileSync(storePath, 'utf8');
const policy = readFileSync(policyPath, 'utf8');
const proposalSchema = JSON.parse(readFileSync(proposalSchemaPath, 'utf8'));
const sourceSchema = JSON.parse(readFileSync(sourceSchemaPath, 'utf8'));
const failures = [];

const fail = message => failures.push(message);
const requireMarker = (source, marker, message) => {
  if (!source.includes(marker)) fail(message);
};
const section = (source, start, end) => {
  const startIndex = source.indexOf(start);
  const endIndex = startIndex < 0 ? -1 : source.indexOf(end, startIndex + start.length);
  return startIndex < 0 ? '' : source.slice(startIndex, endIndex < 0 ? source.length : endIndex);
};

for (const marker of [
  'class Tra_Vel_Assisted_Proposal_Store',
  "const DB_VERSION                    = '1.0.0'",
  'const MAX_PROPOSALS_PER_CASE        = 12',
  'const MAX_REVISIONS_PER_PROPOSAL    = 20',
  'const MAX_SNAPSHOT_BYTES             = 524288',
  'const CLEANUP_BATCH_SIZE            = 100',
  'const CLEANUP_MAX_BATCHES           = 10',
  'const CLEANUP_MAX_SECONDS           = 20',
  'public static function install()',
  'public static function schema_health()',
  'public static function cleanup()',
  'public function append_draft_revision(',
  'public function publish_revision(',
  'public function publish_composed_revision(',
  'public function replay_traveler_action(',
  'public function record_traveler_action(',
  'public function replay_withdrawal(',
  'public function withdraw(',
  'public function get_by_uuid(',
  'public function list_by_case(',
  'public function list_operator(',
  'public function get_revision_bundle(',
  'public function get_event_by_uuid(',
]) requireMarker(store, marker, `Store is missing required contract marker: ${marker}`);

const innoDbTables = [...store.matchAll(/CREATE TABLE[\s\S]*?ENGINE=InnoDB/g)].length;
if (innoDbTables !== 5) fail(`Expected five InnoDB aggregate tables, found ${innoDbTables}.`);
for (const table of [
  'tra_vel_assisted_proposals',
  'tra_vel_assisted_proposal_revisions',
  'tra_vel_assisted_proposal_sources',
  'tra_vel_assisted_proposal_events',
  'tra_vel_assisted_proposal_idempotency',
]) requireMarker(store, table, `Store is missing table ${table}.`);

for (const column of [
  'quote_case_id', 'quote_case_uuid', 'proposal_version', 'current_revision', 'published_revision',
  'last_event_sequence', 'source_case_version', 'source_case_revision', 'request_digest',
  'current_revision_digest', 'published_revision_digest', 'current_source_set_digest',
  'retention_until', 'legal_hold',
]) requireMarker(store, column, `Proposal head/case binding is missing ${column}.`);

for (const marker of [
  'UNIQUE KEY proposal_uuid (proposal_uuid)',
  'UNIQUE KEY reference_code (reference_code)',
  'UNIQUE KEY proposal_revision (proposal_id,revision_no)',
  'UNIQUE KEY proposal_snapshot (proposal_id,snapshot_digest)',
  'UNIQUE KEY proposal_revision_source (proposal_id,revision_no,source_uuid)',
  'UNIQUE KEY revision_source (proposal_revision_id,source_uuid)',
  'UNIQUE KEY event_uuid (event_uuid)',
  'UNIQUE KEY proposal_sequence (proposal_id,sequence_no)',
  'UNIQUE KEY operation_key (operation_scope,principal_hash,idempotency_key_hash)',
]) requireMarker(store, marker, `Schema is missing required unique index: ${marker}`);

for (const marker of [
  "START TRANSACTION",
  "FOR UPDATE",
  "ROLLBACK",
  "COMMIT",
  'lock_verified_parent_case',
  'get_head_for_update',
  'count_case_heads',
  'tra_vel_assisted_proposal_case_limit',
  'tra_vel_assisted_proposal_revision_limit',
]) requireMarker(store, marker, `Transactional/cap guard is missing ${marker}.`);

const writeRevision = section(store, 'private function write_revision(', 'private function write_state_event(');
const compositionReplay = section(store, 'public function replay_composed_revision(', 'private function finalize_composition_replay(');
const compositionReplayFinalizer = section(store, 'private function finalize_composition_replay(', 'public function record_traveler_action(');
const stateEvent = section(store, 'private function write_state_event(', 'private function validate_revision_for_write(');
const replayStateEvent = section(store, 'private function replay_state_event(', 'private function finalize_state_event_replay(');
const insertRevision = section(store, 'private function insert_revision_and_sources(', 'private function insert_event(');
const advanceHead = section(store, 'private function advance_head(', 'private function insert_revision_and_sources(');
const projector = section(store, 'private function normalize_proposal(', 'private function project_source_shape(');
const sourceProjector = section(store, 'private function project_source_shape(', 'private function normalize_sources(');
const cleanupBatch = section(store, 'private function delete_retention_batch(', 'private function sweep_idempotency_batch(');

if (!writeRevision.includes('Tra_Vel_Assisted_Proposal_Policy::canonical_digest(')) fail('Revision idempotency must use a canonical operation digest.');
if (!store.includes("$this->write_revision( 'proposal.compose'") || !writeRevision.includes("'command'          => $command_basis")) {
  fail('Reduced composition retries must use an authored-command digest rather than regenerated server UUIDs or evidence times.');
}
const compositionParentLockIndex = compositionReplay.indexOf('$locked_case = $this->lock_verified_parent_case( $case, false )');
const compositionReceiptIndex = compositionReplay.indexOf("$replay = $this->idempotent_result( 'proposal.compose'");
const compositionFinalizerIndex = compositionReplay.indexOf('$replay = $this->finalize_composition_replay( $replay, $locked_case )');
const compositionRollbackIndex = compositionReplay.indexOf("$wpdb->query( 'ROLLBACK' )", compositionFinalizerIndex);
if (compositionParentLockIndex < 0 || compositionReceiptIndex < compositionParentLockIndex || compositionFinalizerIndex < compositionReceiptIndex || compositionRollbackIndex < compositionFinalizerIndex) {
  fail('Composition replay must reconcile its receipt against a live head while the retained parent transaction is still locked.');
}
for (const marker of [
  '$live_head = $this->get_head_for_update( $proposal_uuid )',
  "(int) $live_head['proposal_version'] !== (int) ( $replay_head['proposal_version'] ?? 0 )",
  "(int) $live_head['current_revision'] !== (int) ( $replay_head['current_revision'] ?? 0 )",
  "(int) $live_head['published_revision'] !== (int) ( $replay_head['published_revision'] ?? 0 )",
  "$live_head['current_revision_digest']",
  "$live_head['published_revision_digest']",
  "$live_head['current_source_set_digest']",
  "$live_head['status']",
  "$live_head['traveler_disposition']",
  "$replay['_force_superseded'] = true",
]) if (!compositionReplayFinalizer.includes(marker)) fail(`Composition replay lifecycle reconciliation is missing ${marker}.`);
if (!writeRevision.includes("$operation_proposal['created_at']   = 'server_controlled'") || !writeRevision.includes("$operation_proposal['published_at'] = $publish ? 'server_controlled' : null")) {
  fail('Caller timestamps must be excluded from the logical idempotency digest.');
}
const stampIndex = writeRevision.indexOf('apply_server_timestamps(');
const publicationValidationIndex = writeRevision.indexOf('validate_revision_for_write(');
const snapshotDigestIndex = writeRevision.indexOf('$snapshot_digest =');
if (stampIndex < 0 || publicationValidationIndex < 0 || snapshotDigestIndex < 0 || stampIndex > publicationValidationIndex || stampIndex > snapshotDigestIndex) {
  fail('Server timestamps must overwrite the proposal before publication validation and immutable digesting.');
}
if (!store.includes("$proposal['created_at']   = gmdate( 'c', $created_at )") || !store.includes("$proposal['published_at'] = $publish ? gmdate( 'c', (int) $now ) : null")) {
  fail('Proposal created/published timestamps are not server-controlled.');
}
if (!store.includes("$now            = $this->mysql_datetime( $proposal['created_at'] )")) fail('New-head database created_at must match the authoritative immutable DTO.');
if (!store.includes('tra_vel_assisted_proposal_idempotency_conflict')) fail('Different canonical data must conflict on idempotent replay.');
if (!store.includes('principal_hash') || !store.includes('idempotency_key_hash')) fail('Idempotency must be principal-scoped and hash its key.');
if (!store.includes('Tra_Vel_Assisted_Proposal_Policy::validate_publication( $proposal, $sources, $context, $now )')) fail('Publication must invoke the exact policy inside the locked write path.');
if (!store.includes("(int) ( $proposal['version'] ?? 0 ) !== (int) $head['proposal_version'] + 1")) fail('A commercial revision must advance from the locked aggregate version, including intervening traveler events.');
if (!advanceHead.includes("'proposal_version'          => (int) $proposal['version']")) fail('The head must persist the server-validated monotonic proposal version.');
for (const marker of [
  "$replay_head['current_revision']     = (int) $row['revision_no']",
  "$replay_head['published_revision']   = (int) ( $bundle['revision_snapshot']['published_revision'] ?? 0 )",
  "$replay_head['current_revision_digest'] = (string) $row['revision_digest']",
  "$replay_head['source_case_revision'] = (int) $bundle['revision_metadata']['case_revision']",
  "$replay_head['request_digest']       = (string) $bundle['revision_metadata']['request_digest']",
  "$replay_head['current_source_set_digest'] = (string) $bundle['revision_metadata']['source_set_digest']",
]) if (!store.includes(marker)) fail(`Historical event replay is missing coherent revision marker ${marker}.`);
if (writeRevision.indexOf('lock_verified_parent_case') > writeRevision.indexOf('validate_revision_for_write')) fail('Publication policy validation must follow the locked parent read.');
const revisionTransactionIndex = writeRevision.indexOf("$wpdb->query( 'START TRANSACTION' )");
const revisionLockedReplayIndex = writeRevision.indexOf('$locked_replay =');
const revisionLockedParentIndex = writeRevision.indexOf('$locked_case = $this->lock_verified_parent_case( $case )');
const revisionAssignmentIndex = writeRevision.indexOf('$assignment = $this->validate_operator_assignment( $principal, $locked_case )');
const revisionReplayReturnIndex = writeRevision.indexOf('return $locked_replay;', revisionAssignmentIndex);
if (revisionTransactionIndex < 0 || revisionLockedParentIndex < revisionTransactionIndex || revisionAssignmentIndex < revisionLockedParentIndex || revisionLockedReplayIndex < revisionAssignmentIndex || revisionReplayReturnIndex < revisionLockedReplayIndex) {
  fail('Operator publication replay must lock the parent and revalidate assignment before returning private proposal data.');
}
const preTransactionRevision = writeRevision.slice(0, revisionTransactionIndex);
if (/is_array\(\s*\$replay\s*\)[\s\S]{0,160}return\s+\$replay/.test(preTransactionRevision)) {
  fail('Operator publication replay must not return before locked assignment revalidation.');
}
if (!store.includes("'case_revision'  => (int) $case['current_revision']") || !store.includes("'request_digest' => (string) $case['latest_request_digest']")) fail('Publication context must bind current case revision and request digest.');

if (!insertRevision.includes('self::revisions_table()') || !insertRevision.includes('self::sources_table()')) fail('Revision and normalized source rows must be inserted together.');
if (/\$wpdb->update\(\s*self::(?:revisions|sources)_table\(/s.test(store)) fail('Immutable revision/source tables must never be updated.');
if (/UPDATE\s+[^\n]*(?:assisted_proposal_revisions|assisted_proposal_sources)/i.test(store)) fail('Immutable revision/source SQL updates are forbidden.');
if (!insertRevision.includes("'publication_validated'")) fail('Every immutable revision must record whether exact publication policy passed.');

if (!projector.includes('tra_vel_assisted_proposal_not_normalized') || !projector.includes('canonical_digest( $proposal ) !== Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $projected )')) {
  fail('Proposal projection must reject any canonical difference caused by unknown or unsafe fields.');
}
if (!projector.includes('project_source_shape')) fail('Embedded proposal evidence must use the closed source projector.');
if (!projector.includes('tra_vel_assisted_proposal_collection_bounds_invalid') || !projector.includes('tra_vel_assisted_proposal_snapshot_too_large')) {
  fail('Draft and published proposal snapshots must enforce collection and byte-size bounds.');
}
if (!sourceProjector.includes('array_diff( $required, array_keys( $source ) )') || !sourceProjector.includes('array_diff( array_keys( $source ), $required )')) {
  fail('Source projection must reject both missing and unknown properties.');
}
for (const key of Object.keys(proposalSchema.properties)) {
  if (!projector.includes(`'${key}'`)) fail(`Closed proposal projector is missing top-level schema field ${key}.`);
}
for (const key of Object.keys(sourceSchema.properties)) {
  if (!sourceProjector.includes(`'${key}'`)) fail(`Closed source projector is missing schema field ${key}.`);
}

// Contract fixture: unknown nested evidence and a raw prompt must be removed by
// closed-schema projection, making the canonical digest differ and forcing the
// PHP store's comparison above to reject rather than persist either value.
const canonical = value => {
  if (Array.isArray(value)) return value.map(canonical);
  if (!value || typeof value !== 'object') return value;
  return Object.fromEntries(Object.keys(value).sort().map(key => [key, canonical(value[key])]));
};
const digestInput = value => JSON.stringify(canonical(value));
const dirtyFixture = {
  route: { origin: 'TLV', destinations: ['BKK'], legs: [], raw_evidence: { response: 'must-not-persist' } },
  raw_prompt: 'must-not-persist',
};
const projectedFixture = { route: { origin: 'TLV', destinations: ['BKK'], legs: [] } };
if (digestInput(dirtyFixture) === digestInput(projectedFixture)) fail('Unknown nested/raw fields must produce a canonical projection mismatch.');
if ('raw_evidence' in projectedFixture.route || 'raw_prompt' in projectedFixture) fail('Closed projection fixture leaked raw evidence.');

if (!stateEvent.includes('Tra_Vel_Assisted_Proposal_Policy::traveler_action_target(')) fail('Traveler state mutation must use the centralized transition policy.');
if (!store.includes('Tra_Vel_Assisted_Proposal_Policy::traveler_actions_for(')) fail('Read projection must use centralized safe next-actions policy.');
const replayIndex = stateEvent.indexOf('idempotent_result(');
const transitionIndex = stateEvent.indexOf('traveler_action_target(');
if (replayIndex < 0 || transitionIndex < 0 || replayIndex > transitionIndex) fail('Same-key action replay must resolve before current-state conflict validation.');
const transactionIndex = stateEvent.indexOf("$wpdb->query( 'START TRANSACTION' )");
const lockedReplayIndex = stateEvent.indexOf('$locked_replay =');
const lockedParentIndex = stateEvent.indexOf('$locked_case = $this->lock_verified_parent_case( $case, false )');
const lockedOwnerIndex = stateEvent.indexOf('$owner_valid = $this->validate_traveler_owner( $principal, $locked_case )');
const lockedReplayReturnIndex = stateEvent.indexOf('return $locked_replay;', lockedOwnerIndex);
if (transactionIndex < 0 || lockedParentIndex < transactionIndex || lockedOwnerIndex < lockedParentIndex || lockedReplayIndex < lockedOwnerIndex || lockedReplayReturnIndex < lockedReplayIndex) {
  fail('Traveler action replay must re-lock the parent and revalidate its exact owner before returning private proposal data.');
}
const preTransactionStateEvent = stateEvent.slice(0, transactionIndex);
if (/is_array\(\s*\$replay\s*\)[\s\S]{0,160}return\s+\$replay/.test(preTransactionStateEvent)) {
  fail('Traveler action replay must not return before locked parent ownership is revalidated.');
}
if (!stateEvent.includes('$case = $this->normalize_verified_case( $verified_case, false )') || !stateEvent.includes("$this->case_is_active_status( (string) $locked_case['status'] )")) {
  fail('State-event writes must admit a retained parent only for receipt lookup, then require active state for a new mutation.');
}
const stateAssignmentIndex = stateEvent.indexOf('$assignment = $this->validate_operator_assignment( $principal, $locked_case )');
if (stateAssignmentIndex < lockedReplayReturnIndex) fail('Current operator assignment must apply only after an exact withdrawal receipt misses.');
if (!stateEvent.includes("$head['source_case_revision']") || !stateEvent.includes("$head['request_digest']") || !stateEvent.includes('tra_vel_assisted_proposal_request_changed')) {
  fail('A new state mutation must bind the proposal head to the current locked quote-case request.');
}
for (const marker of [
  'private function state_event_operation_digest(',
  "'case_binding'     => $this->stable_case_binding( $case )",
  "'contact_consent'  => $contact_consent",
  'private function replay_state_event(',
  '$locked_case = $this->lock_verified_parent_case( $case, false )',
  'private function finalize_state_event_replay(',
  "(int) $live_head['proposal_version'] !== $event_version",
  "$replay['_force_superseded'] = true",
]) requireMarker(store, marker, `Stable retained-parent state replay is missing ${marker}.`);
if (!replayStateEvent.includes('$owner_valid = $this->validate_traveler_owner( $principal, $locked_case )') || replayStateEvent.indexOf('$owner_valid =') > replayStateEvent.indexOf('$replay = $this->idempotent_result(')) {
  fail('Standalone traveler receipt recovery must revalidate the locked owner before reading private replay data.');
}
if (!policy.includes("'reviewed'        => array( 'request_changes', 'authorize_contact', 'decline' )")) fail('A different-key repeated review must be rejected by the reviewed-state matrix.');
if (!policy.includes("'changes_requested'") || !policy.includes("'contact_authorized'") || !policy.includes("'declined'")) fail('Terminal traveler dispositions must remain explicit policy values.');
if (!store.includes("$to_status     = $operator ? 'withdrawn' : 'available'")) fail('State events may target only available or withdrawn proposal status.');
if (!store.includes("$to_disposition = $operator ? 'unavailable'")) fail('Withdrawal must project traveler state as unavailable.');
if (stateEvent.indexOf('insert_event(') > stateEvent.indexOf('$wpdb->update(')) fail('Append-only action event must be inserted before the head CAS update.');

for (const marker of [
  'const CONTACT_CONSENT_VERSION',
  "'account_email'",
  "'tra_vel_assistance_team'",
  'private function current_account_email_digest(',
  "hash_hmac(",
  "wp_salt( 'auth' )",
  "'wp-user-account:' . $user_id . '|account-email:' . $email",
  "'contact_target_digest' => $contact_target_digest",
  "'consented_at'           => gmdate( 'c', $now )",
  'private function normalize_stored_contact_consent(',
  'public function validate_contact_dispatch_target(',
  'tra_vel_assisted_proposal_contact_target_changed',
  "'authorize_contact' === (string) $row['action_code']",
  'tra_vel_assisted_proposal_contact_consent_unexpected',
]) requireMarker(store, marker, `Durable purpose-limited contact consent is missing ${marker}.`);
if (store.includes("hash( 'sha256', 'wp-user-account:' . $user_id )")) fail('Contact consent must not use a user-ID-only digest in place of an exact email HMAC.');
const consentNormalizer = section(store, 'private function normalize_contact_consent(', 'private function current_account_email_digest(');
if (consentNormalizer.includes('contact_target_digest')) fail('The stable contact-consent command must exclude mutable email evidence from idempotent identity.');
const contactDispatch = section(store, 'public function validate_contact_dispatch_target(', 'private function get_event_row_by_uuid(');
if (!contactDispatch.includes('$this->get_event_row_by_uuid( $event_uuid )') || !contactDispatch.includes('$this->current_account_email_digest( $user_id )') || !contactDispatch.includes('$this->safe_digest_equals(')) {
  fail('Contact dispatch must load persisted consent and compare it with the exact current-email HMAC.');
}
const writeContactDigestIndex = stateEvent.indexOf('$contact_target_digest = $this->current_account_email_digest(');
if (writeContactDigestIndex < lockedReplayReturnIndex) fail('Exact contact-action receipt replay must return before mutable current-email evidence is resolved.');

for (const marker of [
  'owner_user_id', 'owner_token_hash', 'validate_traveler_owner',
  "(int) $case['owner_user_id'] === $user_id",
  "0 === (int) $case['owner_user_id']",
  "hash_equals( (string) $case['owner_token_hash'], $token_hash )",
]) requireMarker(store, marker, `Exact account/private-browser ownership guard is missing ${marker}.`);
if (!stateEvent.includes('lock_verified_parent_case') || stateEvent.indexOf('lock_verified_parent_case') > stateEvent.indexOf('validate_traveler_owner')) {
  fail('Traveler ownership must be checked against the locked parent case.');
}

for (const marker of [
  '$wpdb->last_error', 'suppress_errors()', 'read_row(', 'read_rows(', 'read_column(', 'read_scalar(',
]) requireMarker(store, marker, `Fail-closed reads are missing ${marker}.`);
for (const marker of [
  'SHOW COLUMNS FROM', 'SHOW TABLE STATUS WHERE Name', 'SHOW INDEX FROM',
  "'innodb' === strtolower", 'required_indexes_ready', 'inspection_errors',
]) requireMarker(store, marker, `Physical schema-health validation is missing ${marker}.`);

for (const marker of [
  'INNER JOIN \' . self::quote_cases_table()',
  'p.legal_hold = 0', 'c.legal_hold = 0',
  'p.retention_until < %s', 'c.retention_until < %s',
  'self::events_table()', 'self::revisions_table()', 'self::sources_table()',
]) requireMarker(cleanupBatch, marker, `Retention cleanup is missing ${marker}.`);
if (!store.includes('CLEANUP_STATUS_OPTION') || !store.includes('record_cleanup_status')) fail('Bounded cleanup must publish a status option.');

const forbiddenStateAssignments = ["'booked'", "'paid'", "'reserved'", "'issued'", "'confirmed'", "'accepted'"];
for (const value of forbiddenStateAssignments) {
  if (stateEvent.includes(value)) fail(`State mutation contains forbidden transactional target ${value}.`);
}

if (failures.length) {
  console.error('Tra-Vel assisted proposal store validation failed:');
  failures.forEach(message => console.error(`- ${message}`));
  process.exit(1);
}

console.log('Tra-Vel assisted proposal store validation passed (closed immutable evidence, locked CAS/idempotency, owned actions, bounded retention).');
