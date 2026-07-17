import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const root = resolve(scriptDir, '..', '..');
const read = (...parts) => readFileSync(join(root, ...parts), 'utf8');

const controller = read('plugin', 'tra-vel-deploy-gateway', 'includes', 'class-tra-vel-deploy-controller.php');
const deployScript = read('scripts', 'deploy', 'deploy-theme.sh');
const rollbackScript = read('scripts', 'deploy', 'rollback-theme.sh');
const powershell = read('scripts', 'wp', 'deploy-theme-rest.ps1');
const deployWorkflow = read('.github', 'workflows', 'deploy-theme.yml');
const rollbackWorkflow = read('.github', 'workflows', 'rollback-theme.yml');
const failures = [];

const requireText = (source, needle, message) => {
  if (!source.includes(needle)) failures.push(message);
};

for (const [needle, message] of [
  ["const THEME_SLUG        = 'tra-vel-v2'", 'Gateway theme slug is not fixed.'],
  ["const DEPLOY_PHRASE     = 'DEPLOY TRA-VEL V2'", 'Gateway deployment phrase is missing.'],
  ["const ACTIVATE_PHRASE   = 'ACTIVATE TRA-VEL V2'", 'Gateway activation phrase is missing.'],
  ["const ROLLBACK_PHRASE   = 'ROLLBACK TRA-VEL V2'", 'Gateway rollback phrase is missing.'],
  ["$request->get_param( 'deployment_confirmation' )", 'Gateway does not read a dedicated deployment confirmation.'],
  ['hash_equals( self::DEPLOY_PHRASE, $confirmation )', 'Gateway does not compare the deployment confirmation exactly.'],
  ['hash_equals( self::ROLLBACK_PHRASE, $confirmation )', 'Gateway does not compare the rollback confirmation exactly.'],
  ["current_user_can( 'install_themes' )", 'Gateway does not require install_themes.'],
  ["current_user_can( 'update_themes' )", 'Gateway does not require update_themes.'],
  ["current_user_can( 'switch_themes' )", 'Gateway does not separately authorize activation.'],
  ["add_option( self::LOCK_KEY", 'Gateway deployment lease is not acquired atomically.'],
  ['expires_at', 'Gateway deployment lease has no expiration.'],
  ['UPDATE {$wpdb->options}', 'Gateway cannot atomically take over an expired lease.'],
  ['AND option_value = %s', 'Gateway lease mutation is not owner-token conditional.'],
  ['release_lock( $lease )', 'Gateway does not release the owner lease.'],
  ['tra_vel_theme_filesystem_unavailable', 'Gateway does not fail closed when WordPress filesystem initialization fails.'],
  ['recover_deployment_error', 'Gateway does not automatically recover failed theme mutations.'],
  ['recover_previous_theme', 'Gateway does not restore the pre-deployment theme state.'],
  ['restore_previous_active_theme', 'Gateway does not preserve the prior active theme.'],
  ['tra_vel_theme_downgrade_blocked', 'Gateway does not reject theme downgrades.'],
  ['tra_vel_theme_same_version_changed', 'Gateway does not reject changed same-version artifacts.'],
  ['fingerprint_directory', 'Gateway does not compare deterministic theme content.'],
  ["'expected_current_fingerprint'", 'Gateway rollback does not require an expected current theme fingerprint.'],
  ["'expected_restored_fingerprint'", 'Gateway rollback does not require an expected backup content fingerprint.'],
  ['tra_vel_theme_rollback_scope_changed', 'Gateway rollback does not fail when current theme content changes.'],
  ['tra_vel_theme_rollback_target_changed', 'Gateway rollback does not fail when selected backup content changes.'],
  ["'installed_fingerprint'", 'Gateway status does not expose the installed theme content fingerprint.'],
  ["'backup_content_sha256'", 'Gateway deployment evidence does not expose the captured backup content fingerprint.'],
  ["'content_sha256' => $restored_fingerprint", 'Gateway rollback does not record the restored content fingerprint.'],
  ["'overwrite_package' => true", 'Gateway does not explicitly overwrite the fixed theme package.'],
]) requireText(controller, needle, message);

if (controller.includes("'latest' === $backup_name")) {
  failures.push('Gateway theme rollback must require an exact named backup, never latest.');
}

const rollbackMethodStart = controller.indexOf('public function rollback( WP_REST_Request $request )');
const rollbackMethodEnd = controller.indexOf('private function acquire_lock()', rollbackMethodStart);
const rollbackMethod = rollbackMethodStart >= 0 && rollbackMethodEnd > rollbackMethodStart
  ? controller.slice(rollbackMethodStart, rollbackMethodEnd)
  : '';
const rollbackLeaseIndex = rollbackMethod.indexOf('$this->acquire_lock()');
const rollbackFingerprintIndex = rollbackMethod.indexOf('$current_fingerprint');
const rollbackBackupFingerprintIndex = rollbackMethod.indexOf('$backup_fingerprint');
const rollbackMutationIndex = rollbackMethod.indexOf('$this->restore_backup( $backup_name )');
if (
  !rollbackMethod
  || rollbackLeaseIndex === -1
  || rollbackFingerprintIndex <= rollbackLeaseIndex
  || rollbackBackupFingerprintIndex <= rollbackFingerprintIndex
  || rollbackMutationIndex <= rollbackBackupFingerprintIndex
) {
  failures.push('Gateway must compare expected live and selected-backup fingerprints under its lease before rollback mutation.');
}

if (/\b(?:set|get|delete)_transient\s*\(\s*self::LOCK_KEY/.test(controller)) {
  failures.push('Gateway deployment lock still relies on a non-atomic transient.');
}

for (const [needle, message] of [
  ['DEPLOY TRA-VEL V2', 'Direct theme deployment lacks the exact deployment phrase.'],
  ['deployment_confirmation=${DEPLOYMENT_CONFIRMATION}', 'Direct theme deployment does not send server-side deployment confirmation.'],
  ['activation_confirmation=${ACTIVATION_CONFIRMATION:-}', 'Direct theme deployment does not send a separate activation confirmation.'],
  ['THEME_DEPLOY_PRESTATE_FILE', 'Direct theme deployment cannot persist pre-deployment identity for recovery.'],
  ['REQUIRE_EXISTING_THEME', 'Direct theme deployment cannot enforce an update-only recovery path.'],
  ['if require_existing and not data["installed"]', 'Direct theme deployment does not fail before a rollback-less first install.'],
  ['installed_fingerprint', 'Direct theme deployment does not persist pre-deployment content identity.'],
  ['data.get("sha256") != expected_sha256', 'Direct theme deployment does not validate the server-confirmed package checksum.'],
  ['data.get("content_sha256"', 'Direct theme deployment does not validate installed content identity.'],
  ['THEME_DEPLOY_RESULT_FILE', 'Direct theme deployment cannot persist its validated gateway receipt.'],
  ['receipt = {', 'Direct theme deployment does not reduce the persisted receipt to an explicit safe field set.'],
  ['"backup_content_sha256": backup_content_sha256', 'Direct theme deployment does not persist captured backup content identity.'],
  ['os.open(result_target, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)', 'Direct theme deployment does not persist its receipt with private permissions.'],
]) requireText(deployScript, needle, message);

for (const [needle, message] of [
  ['ROLLBACK TRA-VEL V2', 'Theme rollback script lacks the exact rollback phrase.'],
  ['confirmation=${ROLLBACK_CONFIRMATION}', 'Theme rollback script does not send server-side rollback confirmation.'],
  ['EXPECTED_CURRENT_FINGERPRINT', 'Theme rollback script does not require the expected current content fingerprint.'],
  ['expected_current_fingerprint=${EXPECTED_CURRENT_FINGERPRINT}', 'Theme rollback script does not send the current content fingerprint for server-side CAS.'],
  ['EXPECTED_RESTORED_FINGERPRINT', 'Theme rollback script does not require the expected restored content fingerprint.'],
  ['expected_restored_fingerprint=${EXPECTED_RESTORED_FINGERPRINT}', 'Theme rollback script does not send the backup content fingerprint for server-side CAS.'],
  ['content_sha256', 'Theme rollback script does not validate the restored content fingerprint.'],
]) requireText(rollbackScript, needle, message);

for (const [needle, message] of [
  ["$DeploymentConfirmation -cne 'DEPLOY TRA-VEL V2'", 'PowerShell deployment helper does not enforce an exact deployment phrase.'],
  ["'deployment_confirmation'", 'PowerShell deployment helper does not send the deployment confirmation.'],
  ["'activation_confirmation'", 'PowerShell deployment helper does not send a separate activation confirmation.'],
]) requireText(powershell, needle, message);

for (const [source, needle, message] of [
  [deployWorkflow, 'DEPLOYMENT_CONFIRMATION:', 'Theme deployment workflow does not pass the deployment phrase to the uploader.'],
  [rollbackWorkflow, 'ROLLBACK_CONFIRMATION:', 'Theme rollback workflow does not pass the rollback phrase to the uploader.'],
  [rollbackWorkflow, 'EXPECTED_CURRENT_FINGERPRINT:', 'Theme rollback workflow does not pass expected current content identity.'],
  [rollbackWorkflow, 'EXPECTED_RESTORED_FINGERPRINT:', 'Theme rollback workflow does not pass expected restored content identity.'],
]) requireText(source, needle, message);

for (const [needle, message] of [
  ['THEME_DEPLOY_RESULT_FILE: theme-deploy-result.json', 'Theme workflow does not persist the deployment receipt between steps.'],
  ['THEME_DEPLOY_PRESTATE_FILE: theme-deploy-prestate.json', 'Theme workflow does not persist pre-deployment release identity.'],
  ['REQUIRE_EXISTING_THEME: true', 'Theme workflow does not require a rollback-capable existing installation.'],
  ['[[ "$ACTIVATE_THEME" == "false" ]]', 'Theme auto-recovery workflow must reject activation because rollback cannot restore a different prior active theme.'],
  ['tra-vel-v2-deploy-receipt-${{ github.run_id }}', 'Theme workflow does not preserve the deployment receipt as a release artifact.'],
  ['theme-deploy-prestate.json\n            theme-deploy-result.json', 'Theme workflow does not publish both prestate and deployment receipt evidence.'],
  ["steps.deploy_theme.outcome != 'skipped'", 'Theme verification does not run after a potentially mutated but unreceipted deployment.'],
  ['!cancelled()', 'Theme recovery does not stop cleanly when the workflow is cancelled.'],
  ['archive = str(data.get("archive", ""))', 'Theme workflow does not select the archive named by the manifest.'],
  ['deployed.get("version") == manifest["version"]', 'Theme workflow does not bind the deployed version to the manifest.'],
  ['deployed.get("sha256") == manifest["sha256"]', 'Theme workflow does not bind the deployed checksum to the manifest.'],
  ['status.get("installed_version") == manifest["version"]', 'Theme workflow does not verify the remotely installed version.'],
  ['last.get("sha256") == manifest["sha256"]', 'Theme workflow does not verify the remote deployment checksum.'],
  ['status.get("installed_fingerprint") == deployed["content_sha256"]', 'Theme workflow does not bind remote installed content to the deployment receipt.'],
  ['backup_content == prestate["installed_fingerprint"]', 'Theme workflow does not bind captured backup content to pre-deployment identity.'],
  ['last.get("backup_content_sha256") == expected_restored_fingerprint', 'Theme workflow does not recheck recorded backup content identity immediately before rollback.'],
  ['EXPECTED_CURRENT_FINGERPRINT="$expected_current_fingerprint"', 'Theme workflow does not bind automatic rollback to current installed content.'],
  ['EXPECTED_RESTORED_FINGERPRINT="$expected_restored_fingerprint"', 'Theme workflow does not bind automatic rollback target content to pre-deployment identity.'],
  ['backup in status.get("backups", [])', 'Theme workflow does not confirm that the captured backup exists remotely.'],
  ['mutation_evidence', 'Theme workflow cannot infer a mutated-but-unreceipted deployment from pre/post identity.'],
  ['theme-rollback-guard.json', 'Theme workflow does not recheck current remote identity immediately before rollback.'],
  ['tra-vel-v2-\\d{8}T\\d{6}Z-[A-Za-z0-9]+', 'Theme workflow does not validate captured rollback identifiers.'],
  ['bash scripts/deploy/rollback-theme.sh "$backup"', 'Theme workflow does not automatically restore the exact captured backup.'],
  ['status.get("installed_version") == prestate["installed_version"]', 'Theme workflow does not verify the restored version against pre-deployment state.'],
  ['status.get("active") is prestate["active"]', 'Theme workflow does not verify restored active state against pre-deployment state.'],
  ['idempotent no-change response', 'Theme workflow does not distinguish a safe no-change verification failure.'],
  ['Captured rollback backup:', 'Theme deployment summary does not report the captured backup identity.'],
]) requireText(deployWorkflow, needle, message);

if (deployWorkflow.includes('bash scripts/deploy/rollback-theme.sh "latest"')) {
  failures.push('Theme workflow must never substitute latest for the captured deployment backup.');
}
if (/default:\s*latest/.test(rollbackWorkflow) || rollbackWorkflow.includes('$BACKUP_NAME" == "latest"')) {
  failures.push('Manual theme rollback must require an exact backup identity, never latest.');
}
if (/^\s*assert\s/m.test(deployWorkflow)) {
  failures.push('Theme workflow identity checks must use explicit failures, not optimizable Python assertions.');
}

const deployJobIndex = deployWorkflow.indexOf('\n  deploy:\n');
const deployStepsIndex = deployWorkflow.indexOf('\n    steps:\n', deployJobIndex);
if (deployJobIndex === -1 || deployStepsIndex === -1) {
  failures.push('Theme deploy job structure could not be inspected for secret scope.');
} else {
  const deployJobEnvironment = deployWorkflow.slice(deployJobIndex, deployStepsIndex);
  if (deployJobEnvironment.includes('secrets.WP_USERNAME') || deployJobEnvironment.includes('secrets.WP_APP_PASSWORD')) {
    failures.push('WordPress deployment credentials must be scoped only to the steps that use them.');
  }
}

const verificationIndex = deployWorkflow.indexOf('- name: Verify deployed identity and website, or restore exact backup');
const receiptArtifactIndex = deployWorkflow.indexOf('- name: Preserve deployment receipt');
if (verificationIndex === -1 || receiptArtifactIndex === -1 || receiptArtifactIndex < verificationIndex) {
  failures.push('Theme receipt evidence must be preserved after live verification/recovery so artifact publication cannot block rollback.');
}

if (failures.length) {
  console.error('Tra-Vel theme deployment contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Tra-Vel theme deployment contract validation passed (receipt persistence, manifest identity, exact-backup recovery, owner lease, confirmations, version guards, and fail-closed filesystem).');
