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
  ["'overwrite_package' => true", 'Gateway does not explicitly overwrite the fixed theme package.'],
]) requireText(controller, needle, message);

if (/\b(?:set|get|delete)_transient\s*\(\s*self::LOCK_KEY/.test(controller)) {
  failures.push('Gateway deployment lock still relies on a non-atomic transient.');
}

for (const [needle, message] of [
  ['DEPLOY TRA-VEL V2', 'Direct theme deployment lacks the exact deployment phrase.'],
  ['deployment_confirmation=${DEPLOYMENT_CONFIRMATION}', 'Direct theme deployment does not send server-side deployment confirmation.'],
  ['activation_confirmation=${ACTIVATION_CONFIRMATION:-}', 'Direct theme deployment does not send a separate activation confirmation.'],
]) requireText(deployScript, needle, message);

for (const [needle, message] of [
  ['ROLLBACK TRA-VEL V2', 'Theme rollback script lacks the exact rollback phrase.'],
  ['confirmation=${ROLLBACK_CONFIRMATION}', 'Theme rollback script does not send server-side rollback confirmation.'],
]) requireText(rollbackScript, needle, message);

for (const [needle, message] of [
  ["$DeploymentConfirmation -cne 'DEPLOY TRA-VEL V2'", 'PowerShell deployment helper does not enforce an exact deployment phrase.'],
  ["'deployment_confirmation'", 'PowerShell deployment helper does not send the deployment confirmation.'],
  ["'activation_confirmation'", 'PowerShell deployment helper does not send a separate activation confirmation.'],
]) requireText(powershell, needle, message);

for (const [source, needle, message] of [
  [deployWorkflow, 'DEPLOYMENT_CONFIRMATION:', 'Theme deployment workflow does not pass the deployment phrase to the uploader.'],
  [rollbackWorkflow, 'ROLLBACK_CONFIRMATION:', 'Theme rollback workflow does not pass the rollback phrase to the uploader.'],
]) requireText(source, needle, message);

if (failures.length) {
  console.error('Tra-Vel theme deployment contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Tra-Vel theme deployment contract validation passed (owner lease, confirmations, recovery, version guards, and fail-closed filesystem).');
