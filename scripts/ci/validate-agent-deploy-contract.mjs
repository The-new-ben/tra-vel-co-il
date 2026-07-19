import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const root = resolve(scriptDir, '..', '..');
const gateway = readFileSync(join(root, 'plugin', 'tra-vel-deploy-gateway', 'includes', 'class-tra-vel-plugin-deploy-controller.php'), 'utf8');
const builder = readFileSync(join(root, 'scripts', 'ci', 'build_agent_core.py'), 'utf8');
const workflow = readFileSync(join(root, '.github', 'workflows', 'deploy-agent-core.yml'), 'utf8');
const themeCi = readFileSync(join(root, '.github', 'workflows', 'theme-ci.yml'), 'utf8');
const bootstrap = readFileSync(join(root, 'scripts', 'wp', 'bootstrap-agent-core.ps1'), 'utf8');
const configure = readFileSync(join(root, 'scripts', 'wp', 'configure-agent-key.ps1'), 'utf8');
const deployScript = readFileSync(join(root, 'scripts', 'deploy', 'deploy-agent-core.sh'), 'utf8');
const rollbackScript = readFileSync(join(root, 'scripts', 'deploy', 'rollback-agent-core.sh'), 'utf8');
const removeFreshScript = readFileSync(join(root, 'scripts', 'deploy', 'remove-failed-agent-core.sh'), 'utf8');
const healthVerifier = readFileSync(join(root, 'scripts', 'ci', 'verify_agent_deploy.py'), 'utf8');
const healthValidator = readFileSync(join(root, 'scripts', 'ci', 'validate_agent_health_verification.py'), 'utf8');
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
  ["const BACKUP_FINGERPRINT_FILE = '.tra-vel-backup-fingerprint.json'", 'Gateway backup fingerprint marker is missing.'],
  ["directory_fingerprint", 'Gateway does not fingerprint extracted and installed Agent Core content.'],
  ["tra_vel_agent_installed_fingerprint_mismatch", 'Gateway does not fail closed on installed content mismatch.'],
  ["write_backup_fingerprint", 'Gateway does not persist backup content identity.'],
  ["read_backup_fingerprint", 'Gateway does not verify backup content identity before rollback.'],
  ["tra_vel_agent_fresh_recovery_fingerprint_changed", 'Fresh-install recovery is not bound to installed content identity.'],
  ["tra_vel_agent_filesystem_unavailable", 'Gateway does not fail closed when WordPress filesystem initialization fails.'],
  ["tra_vel_agent_installed_identity_mismatch", 'Gateway does not verify the actually installed Agent Core version.'],
  ["tra_vel_agent_live_plugin_missing", 'Rollback does not surface failure to restore the quarantined live plugin.'],
  ["tra_vel_agent_rollback_scope_changed", 'Rollback is not guarded by the expected current content fingerprint.'],
  ["tra_vel_agent_rollback_target_changed", 'Rollback does not verify the expected backup content before live mutation.'],
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
  ["$health.capabilities.no_login_scoped_sessions", 'Bootstrap does not require no-login scoped-session capability readiness.'],
  ["$capabilityHealth.schema_version -ne '1.1.0'", 'Bootstrap does not require the exact capability-session schema version.'],
  ["[int]$capabilityHealth.expected_tables -ne 4", 'Bootstrap does not require exactly four capability-session tables.'],
  ["[int]$capabilityHealth.required_indexes -ne 7", 'Bootstrap does not require exactly seven capability-session race indexes.'],
  ["$health.capabilities.customer_trip_cockpit", 'Bootstrap does not require Customer Trip Cockpit capability readiness.'],
  ["$cockpitHealth.schema_version -ne '1.0.0'", 'Bootstrap does not require the exact Customer Trip Cockpit schema version.'],
  ["[int]$cockpitHealth.expected_tables -ne 3", 'Bootstrap does not require exactly three Customer Trip Cockpit tables.'],
  ["[int]$cockpitHealth.required_indexes -ne 13", 'Bootstrap does not require exactly thirteen Customer Trip Cockpit indexes.'],
  ["@($cockpitHealth.inspection_errors).Count -ne 0", 'Bootstrap does not fail closed on Customer Trip Cockpit schema inspection errors.'],
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
  ['deploy gateway 0.3.0 or newer', 'Direct Agent Core deployment does not require a content-aware gateway.'],
  ['content_sha256', 'Direct Agent Core deployment does not validate installed content identity.'],
]) requireText(deployScript, needle, message);

for (const [needle, message] of [
  ['ROLLBACK TRA-VEL AGENT CORE', 'Agent Core rollback script lacks its exact confirmation phrase.'],
  ['/plugin/agent-core/rollback', 'Agent Core rollback script does not use the restricted rollback route.'],
  ['--data-urlencode "confirmation=', 'Agent Core rollback script does not send server-side confirmation.'],
  ['EXPECTED_CURRENT_FINGERPRINT', 'Agent Core rollback does not require the expected current release fingerprint.'],
  ['expected_current_fingerprint=', 'Agent Core rollback does not bind the mutation to the expected current release.'],
  ['EXPECTED_RESTORED_FINGERPRINT', 'Agent Core rollback does not require the expected restored release fingerprint.'],
  ['expected_restored_fingerprint=', 'Agent Core rollback does not bind the selected backup to the expected restored release.'],
  ['content_sha256', 'Agent Core rollback does not verify restored content identity.'],
]) requireText(rollbackScript, needle, message);

for (const [needle, message] of [
  ['REMOVE FAILED TRA-VEL AGENT CORE', 'Fresh-install cleanup script lacks its exact confirmation phrase.'],
  ['/plugin/agent-core/recovery/fresh', 'Fresh-install cleanup script does not use the restricted recovery route.'],
  ['sha256=', 'Fresh-install cleanup is not bound to the exact failed package checksum.'],
  ['content_sha256', 'Fresh-install cleanup does not verify the removed content identity.'],
]) requireText(removeFreshScript, needle, message);

for (const [source, needle, message] of [
  [builder, 'def content_fingerprint', 'Agent Core packaging does not calculate the extracted content fingerprint.'],
  [builder, '"content_sha256": content_digest', 'Agent Core manifest does not carry the extracted content fingerprint.'],
  [healthVerifier, 'hmac.compare_digest(manifest[key], deployed[key])', 'Production verification does not compare the deployment receipt to the build manifest.'],
]) requireText(source, needle, message);

for (const [needle, message] of [
  ['timeout --signal=TERM --kill-after=5s 330s python3 scripts/ci/verify_agent_deploy.py', 'Agent Core workflow does not enforce a total watchdog around the bounded production verifier.'],
  ['--attempts 6', 'Agent Core workflow does not use a bounded multi-attempt health gate.'],
  ['python3 scripts/ci/validate_agent_health_verification.py', 'Agent Core package job does not exercise the health gate validator.'],
  ['node scripts/ci/validate-assisted-proposal-release-contract.mjs', 'Agent Core package job does not exercise the assisted-proposal release gate.'],
  ['bash scripts/deploy/rollback-agent-core.sh "$backup"', 'Exhausted Agent Core health verification no longer triggers rollback.'],
]) requireText(workflow, needle, message);

requireText(
  themeCi,
  'python3 scripts/ci/validate_agent_health_verification.py',
  'Pull-request CI does not exercise the Agent Core health verifier runtime contract.',
);

for (const [needle, message] of [
  ['class NoRedirectHandler', 'Health verification can follow a missing REST route redirect to the homepage.'],
  ['"Accept": "application/json"', 'Health verification does not request JSON explicitly.'],
  ['"Cache-Control": "no-cache, no-store"', 'Health verification does not bypass stale endpoint caches.'],
  ['MAX_RESPONSE_BYTES', 'Health verification does not bound response-body memory.'],
  ['media_type == "application/json" or media_type.endswith("+json")', 'Health verification can accept a 200 HTML fallback.'],
  ['status={self.status} content_type={self.content_type} bytes={self.byte_count}', 'Health verification does not report only bounded response metadata.'],
  ['secrets.token_hex(8)', 'Health verification does not cache-bust each readiness attempt.'],
  ['basic_authorization(config.username, config.app_password)', 'Operator queue verification is not authenticated in memory.'],
  ['config.base_delay_seconds * (2 ** (attempt - 1))', 'Health verification does not use bounded exponential backoff.'],
  ['health.get("plugin_version") == manifest.get("version")', 'Health verification can accept a stale plugin version.'],
  ['expected_proposal_store', 'Health verification does not enforce the exact assisted-proposal schema contract.'],
  ['capabilities.get("sourced_assisted_proposals") is True', 'Health verification does not require sourced assisted proposals.'],
  ['capabilities.get("audited_proposal_actions") is True', 'Health verification does not require audited proposal actions.'],
  ['capabilities.get("no_login_scoped_sessions") is True', 'Health verification does not require no-login scoped sessions.'],
  ['capabilities.get("customer_trip_cockpit") is True', 'Health verification does not require Customer Trip Cockpit readiness.'],
  ['expected_capability_store', 'Health verification does not enforce the exact capability-session schema health.'],
  ['"expected_tables": 4', 'Health verification does not require exactly four capability-session tables.'],
  ['"required_indexes": 7', 'Health verification does not require exactly seven capability-session indexes.'],
  ['expected_customer_cockpit_store', 'Health verification does not enforce the exact Customer Trip Cockpit schema health.'],
  ['"expected_tables": 3', 'Health verification does not require exactly three Customer Trip Cockpit tables.'],
  ['"required_indexes": 13', 'Health verification does not require exactly thirteen Customer Trip Cockpit indexes.'],
  ['except Exception as error:  # Fail closed without rendering request headers or response bodies.', 'Unexpected verifier failures may expose protected response data.'],
]) requireText(healthVerifier, needle, message);

for (const [needle, message] of [
  ['A 200 HTML fallback was accepted', 'Health validator does not reject a 200 HTML fallback.'],
  ['failed_attempts == [1, 2, 3, 4]', 'Health validator does not prove retry exhaustion is bounded.'],
  ['Exhausted verification did not fail closed for rollback.', 'Health validator does not prove rollback signaling after exhaustion.'],
  ['"not-printed" not in line', 'Health validator does not check secret-safe retry diagnostics.'],
  ['A missing audited-proposal capability passed deployment verification.', 'Health validator does not prove a missing proposal capability fails closed.'],
  ['An incomplete assisted-proposal index set passed deployment verification.', 'Health validator does not prove a proposal schema mismatch fails closed.'],
  ['A missing no-login scoped-session capability passed deployment verification.', 'Health validator does not independently reject a missing no-login capability.'],
  ['A false no-login scoped-session capability passed deployment verification.', 'Health validator does not independently reject a false no-login capability.'],
  ['capability_store_negative_values', 'Health validator does not independently exercise every capability-session health field.'],
  ['A missing Customer Trip Cockpit capability passed deployment verification.', 'Health validator does not independently reject a missing Customer Trip Cockpit capability.'],
  ['A false Customer Trip Cockpit capability passed deployment verification.', 'Health validator does not independently reject a false Customer Trip Cockpit capability.'],
  ['A missing Customer Trip Cockpit store passed deployment verification.', 'Health validator does not independently reject a missing Customer Trip Cockpit store.'],
  ['customer_cockpit_store_negative_values', 'Health validator does not independently exercise every Customer Trip Cockpit schema-health field.'],
]) requireText(healthValidator, needle, message);

if (failures.length) {
  console.error('Tra-Vel Agent Core deployment contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Tra-Vel Agent Core deployment contract validation passed (fixed identity, bounded JSON-only health retries, capability checks, verified backup, rollback, and secret-safe configuration).');
