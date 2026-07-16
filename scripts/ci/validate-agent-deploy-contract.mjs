import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const root = resolve(scriptDir, '..', '..');
const gateway = readFileSync(join(root, 'plugin', 'tra-vel-deploy-gateway', 'includes', 'class-tra-vel-plugin-deploy-controller.php'), 'utf8');
const bootstrap = readFileSync(join(root, 'scripts', 'wp', 'bootstrap-agent-core.ps1'), 'utf8');
const configure = readFileSync(join(root, 'scripts', 'wp', 'configure-agent-key.ps1'), 'utf8');
const deployScript = readFileSync(join(root, 'scripts', 'deploy', 'deploy-agent-core.sh'), 'utf8');
const rollbackScript = readFileSync(join(root, 'scripts', 'deploy', 'rollback-agent-core.sh'), 'utf8');
const removeFreshScript = readFileSync(join(root, 'scripts', 'deploy', 'remove-failed-agent-core.sh'), 'utf8');
const failures = [];

const requireText = (source, needle, message) => {
  if (!source.includes(needle)) failures.push(message);
};

for (const [needle, message] of [
  ["const PLUGIN_SLUG       = 'tra-vel-agent-core'", 'Gateway plugin slug is not fixed.'],
  ["const PLUGIN_FILE       = 'tra-vel-agent-core/tra-vel-agent-core.php'", 'Gateway plugin file is not fixed.'],
  ["const PLUGIN_NAME       = 'Tra-Vel Agent Core'", 'Gateway plugin header identity is not fixed.'],
  ["const DEPLOY_PHRASE     = 'DEPLOY TRA-VEL AGENT CORE'", 'Gateway deployment phrase is missing.'],
  ["const ACTIVATE_PHRASE   = 'ACTIVATE TRA-VEL AGENT CORE'", 'Gateway activation phrase is missing.'],
  ["const ROLLBACK_PHRASE   = 'ROLLBACK TRA-VEL AGENT CORE'", 'Gateway rollback phrase is missing.'],
  ["const REMOVE_FRESH_PHRASE = 'REMOVE FAILED TRA-VEL AGENT CORE'", 'Gateway fresh-install recovery phrase is missing.'],
  ["current_user_can( 'install_plugins' )", 'Gateway does not require install_plugins.'],
  ["current_user_can( 'update_plugins' )", 'Gateway does not require update_plugins.'],
  ["current_user_can( 'activate_plugins' )", 'Gateway does not separately authorize activation.'],
  ["hash_equals( $expected_hash, $actual_hash )", 'Gateway does not compare the package checksum safely.'],
  ["array( self::PLUGIN_SLUG ) !== $entries", 'Gateway does not enforce a single fixed ZIP root.'],
  ["backup_current_plugin", 'Gateway does not back up Agent Core before overwrite.'],
  ["restore_backup", 'Gateway does not provide Agent Core rollback.'],
  ["recover_deployment_error", 'Gateway does not automatically recover failed installation or activation.'],
  ["add_option( self::LOCK_KEY", 'Gateway deployment lock is not acquired atomically.'],
  ["AND option_value = %s", 'Gateway lock release is not owner-token conditional.'],
  ["tra_vel_agent_downgrade_blocked", 'Gateway does not reject version downgrades.'],
  ["tra_vel_agent_same_version_changed", 'Gateway does not reject changed same-version artifacts.'],
  ["tra_vel_agent_filesystem_unavailable", 'Gateway does not fail closed when WordPress filesystem initialization fails.'],
  ["tra_vel_agent_installed_identity_mismatch", 'Gateway does not verify the actually installed Agent Core version.'],
  ["tra_vel_agent_live_plugin_missing", 'Rollback does not surface failure to restore the quarantined live plugin.'],
  ["remove_failed_fresh_install", 'Gateway cannot remove a failed fresh Agent Core release after health verification.'],
  ["overwrite_package' => true", 'Gateway does not explicitly overwrite the fixed plugin package.'],
]) requireText(gateway, needle, message);

for (const [needle, message] of [
  ["$requiredConfirmation = 'INSTALL TRA-VEL AGENT CORE'", 'Bootstrap installation confirmation is missing.'],
  ["$pluginFile = 'tra-vel-agent-core/tra-vel-agent-core.php'", 'Bootstrap path is not fixed.'],
  ["X-Tra-Vel-SHA256", 'Bootstrap checksum header is missing.'],
  ["temporary Agent Core bootstrap", 'Bootstrap does not neutralize its temporary installer.'],
  ["/wp-json/tra-vel-agent/v1/health", 'Bootstrap does not verify Agent Core health.'],
  ["add_option( $lock_key", 'Bootstrap lock is not acquired atomically.'],
  ["AND option_value = %s", 'Bootstrap lock release is not owner-token conditional.'],
  ["tra_vel_agent_bootstrap_filesystem", 'Bootstrap does not fail closed when WordPress filesystem initialization fails.'],
  ["tra_vel_agent_bootstrap_existing", 'Bootstrap can overwrite an existing Agent Core installation.'],
  ["cleanup_failed_install", 'Bootstrap cannot remove a fresh install after health failure.'],
  ["fresh_install_removed", 'Bootstrap install failures do not report verified fresh-install cleanup.'],
]) requireText(bootstrap, needle, message);

for (const [needle, message] of [
  ["'STORE TRA-VEL OPENAI KEY'", 'Credential configuration confirmation is missing.'],
  ["/wp-json/tra-vel-agent/v1/settings/credential", 'Credential helper does not use the protected Agent Core route.'],
  ["$apiKey = $null", 'Credential helper does not clear its key variable.'],
  ["ZeroFreeBSTR", 'Credential helper does not clear the WordPress application password.'],
]) requireText(configure, needle, message);

if (/Write-(Host|Output).*apiKey|echo.*OPENAI_API_KEY/i.test(configure)) {
  failures.push('Credential helper may print secret material.');
}

for (const [needle, message] of [
  ['DEPLOY TRA-VEL AGENT CORE', 'Direct Agent Core deployment lacks the exact server deployment phrase.'],
  ['deployment_confirmation=', 'Direct Agent Core deployment does not send the deployment confirmation.'],
  ['AGENT_DEPLOY_RESULT_FILE', 'Direct Agent Core deployment does not preserve the rollback identifier for health recovery.'],
]) requireText(deployScript, needle, message);

for (const [needle, message] of [
  ['ROLLBACK TRA-VEL AGENT CORE', 'Agent Core rollback script lacks its exact confirmation phrase.'],
  ['/plugin/agent-core/rollback', 'Agent Core rollback script does not use the restricted rollback route.'],
  ['--data-urlencode "confirmation=', 'Agent Core rollback script does not send server-side confirmation.'],
]) requireText(rollbackScript, needle, message);

for (const [needle, message] of [
  ['REMOVE FAILED TRA-VEL AGENT CORE', 'Fresh-install cleanup script lacks its exact confirmation phrase.'],
  ['/plugin/agent-core/recovery/fresh', 'Fresh-install cleanup script does not use the restricted recovery route.'],
  ['sha256=', 'Fresh-install cleanup is not bound to the exact failed package checksum.'],
]) requireText(removeFreshScript, needle, message);

if (failures.length) {
  console.error('Tra-Vel Agent Core deployment contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Tra-Vel Agent Core deployment contract validation passed (fixed slug, checksum, capability, backup, rollback, and secret-safe configuration).');
