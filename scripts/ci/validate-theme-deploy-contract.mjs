import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const root = resolve(scriptDir, '..', '..');
const read = (...parts) => readFileSync(join(root, ...parts), 'utf8');

const controller = read('plugin', 'tra-vel-deploy-gateway', 'includes', 'class-tra-vel-deploy-controller.php');
const deployScript = read('scripts', 'deploy', 'deploy-theme.sh');
const statusScript = read('scripts', 'deploy', 'get-theme-status.sh');
const rollbackScript = read('scripts', 'deploy', 'rollback-theme.sh');
const smokeScript = read('scripts', 'deploy', 'smoke-test.sh');
const smokeManifest = read('scripts', 'deploy', 'smoke-routes.tsv');
const preflightScript = read('scripts', 'ci', 'verify_theme_preflight.py');
const buildThemeScript = read('scripts', 'ci', 'build_theme.py');
const releaseRequirementsSource = read('theme', 'tra-vel-v2', 'release-requirements.json');
const powershell = read('scripts', 'wp', 'deploy-theme-rest.ps1');
const deployWorkflow = read('.github', 'workflows', 'deploy-theme.yml');
const themeCiWorkflow = read('.github', 'workflows', 'theme-ci.yml');
const rollbackWorkflow = read('.github', 'workflows', 'rollback-theme.yml');
const headerTemplate = read('theme', 'tra-vel-v2', 'header.php');
const frontTemplate = read('theme', 'tra-vel-v2', 'front-page.php');
const mapTemplate = read('theme', 'tra-vel-v2', 'page-map.php');
const experienceTemplate = read('theme', 'tra-vel-v2', 'page-experience.php');
const directoryTemplate = read('theme', 'tra-vel-v2', 'page-directory.php');
const destinationTemplate = read('theme', 'tra-vel-v2', 'page-destination.php');
const savedTemplate = read('theme', 'tra-vel-v2', 'page-saved.php');
const accountTemplate = read('theme', 'tra-vel-v2', 'page-account.php');
const partnersTemplate = read('theme', 'tra-vel-v2', 'page-partners.php');
const themeStyle = read('theme', 'tra-vel-v2', 'style.css');
const themeFunctions = read('theme', 'tra-vel-v2', 'functions.php');
const failures = [];

const requireText = (source, needle, message) => {
  if (!source.includes(needle)) failures.push(message);
};

let releaseRequirements = {};
try {
  releaseRequirements = JSON.parse(releaseRequirementsSource);
} catch (error) {
  failures.push(`Theme release requirements are not valid JSON: ${error.message}`);
}
const releaseDependency = Array.isArray(releaseRequirements.dependencies) ? releaseRequirements.dependencies[0] : null;
if (
  releaseRequirements.contract_version !== '1.0.0'
  || releaseRequirements.theme?.slug !== 'tra-vel-v2'
  || releaseRequirements.theme?.version !== '1.29.2'
  || releaseRequirements.theme?.requires_wordpress !== '6.6'
  || releaseRequirements.deploy_gateway?.min_version !== '0.3.0'
  || releaseRequirements.public_http?.unknown_status !== 404
  || releaseRequirements.public_http?.redirects_allowed !== false
  || releaseDependency?.id !== 'tra-vel-agent-core'
  || releaseDependency?.min_version !== '0.7.0'
	|| !releaseDependency?.required_capabilities?.includes('account_plan_history')
	|| !releaseDependency?.required_capabilities?.includes('audited_proposal_actions')
	|| !releaseDependency?.required_capabilities?.includes('commercial_intents')
	|| !releaseDependency?.required_capabilities?.includes('durable_commercial_handoffs')
	|| !releaseDependency?.required_capabilities?.includes('sourced_assisted_proposals')
	|| !releaseDependency?.required_stores?.includes('assisted_proposal_store')
	|| !releaseDependency?.required_stores?.includes('commercial_intent_store')
) {
  failures.push('Theme release requirements no longer express the WordPress, gateway, Agent Core, capability, and real-404 launch floor.');
}
if (!themeStyle.includes('Version: 1.29.2') || !themeStyle.includes('Requires at least: 6.6') || !themeFunctions.includes("define( 'TRA_VEL_V2_VERSION', '1.29.2' )")) {
  failures.push('Theme headers, runtime version, and release requirements are not aligned.');
}

const expectedSmokeEntries = [
  ['/', 'data-tra-vel-page="home"'],
  ['/travel-map/', 'data-tra-vel-page="travel-map"'],
  ['/destinations/thailand/', 'data-guide-slug="thailand"'],
  ['/flights/', 'data-experience-kind="flights"'],
  ['/hotels/', 'data-experience-kind="hotels"'],
  ['/packages/', 'data-experience-kind="packages"'],
  ['/packages/budapest/', 'data-tra-vel-page="seo-opportunity"'],
  ['/destinations/larnaca/', 'data-destination-map-state="larnaca"'],
  ['/destinations/crete/', 'data-destination-map-state="crete"'],
  ['/travel-insurance/', 'data-experience-kind="travel-insurance"'],
  ['/ai-planner/', 'data-experience-kind="ai-planner"'],
  ['/destinations/', 'data-directory-kind="destinations"'],
  ['/guides/', 'data-directory-kind="guides"'],
  ['/saved/', 'data-tra-vel-page="saved"'],
  ['/account/', 'data-tra-vel-page="account"'],
  ['/partners/', 'data-tra-vel-page="partners"'],
];
const smokeEntries = smokeManifest.trim().split(/\r?\n/).map(line => line.split('\t'));
if (JSON.stringify(smokeEntries) !== JSON.stringify(expectedSmokeEntries)) {
  failures.push('The authoritative smoke route manifest no longer matches the production route contract.');
}
if (new Set(smokeEntries.map(entry => entry[0])).size !== smokeEntries.length) {
  failures.push('The smoke route manifest contains duplicate paths.');
}

for (const [source, needle, message] of [
  [frontTemplate, 'data-tra-vel-page="home"', 'Homepage route identity marker is missing.'],
  [mapTemplate, 'data-tra-vel-page="travel-map"', 'Map route identity marker is missing.'],
  [experienceTemplate, 'data-experience-kind="<?php echo esc_attr( $experience_kind ); ?>"', 'Experience routes lack a server-rendered kind marker.'],
  [directoryTemplate, 'data-directory-kind="<?php echo esc_attr( $page_slug ); ?>"', 'Directory routes lack a server-rendered kind marker.'],
  [destinationTemplate, 'data-guide-slug="<?php echo esc_attr( $guide_slug ); ?>"', 'Destination guides lack a server-rendered slug marker.'],
  [savedTemplate, 'data-tra-vel-page="saved"', 'Saved Trips route identity marker is missing.'],
  [accountTemplate, 'data-tra-vel-page="account"', 'Account route identity marker is missing.'],
  [partnersTemplate, 'data-tra-vel-page="partners"', 'Partner route identity marker is missing.'],
  [headerTemplate, '<meta name="tra-vel-release" content="<?php echo esc_attr( TRA_VEL_V2_VERSION ); ?>">', 'Theme pages lack an explicit release identity marker.'],
  [smokeScript, 'smoke-routes.tsv', 'Smoke testing does not load the authoritative route manifest.'],
  [smokeScript, '--validate-manifest', 'Smoke route configuration has no network-free validation mode.'],
  [smokeScript, 'No authoritative marker is registered for smoke path', 'Unmapped smoke routes do not fail closed.'],
  [smokeScript, String.raw`name=['\"]tra-vel-release['\"]`, 'Smoke testing does not verify the explicit release marker.'],
  [smokeScript, 'release_version != expected_version', 'Smoke testing does not bind the rendered release to the deployment manifest.'],
  [smokeScript, 'allow_legacy_release = sys.argv[4] == "true"', 'Smoke testing lacks a narrowly scoped legacy-release recovery path.'],
  [deployWorkflow, 'bash scripts/deploy/smoke-test.sh --validate-manifest', 'Theme CI does not validate the versioned production smoke route set.'],
  [deployWorkflow, '"content/seo/**"', 'Registry-only changes do not trigger the theme deployment pipeline.'],
  [deployWorkflow, 'node scripts/ci/validate-public-copy.mjs', 'Theme deployment can bypass public-copy validation.'],
  [deployWorkflow, 'node scripts/ci/validate-seo-opportunity-provisioning.mjs', 'Theme deployment can bypass SEO provisioning validation.'],
  [deployWorkflow, 'php scripts/ci/validate-seo-opportunity-runtime.php', 'Theme deployment can bypass the SEO opportunity runtime contract.'],
  [deployWorkflow, '& scripts/wp/provision-seo-registry-pages.ps1 -ContractTest', 'Theme deployment does not run the offline provisioning contract.'],
  [themeCiWorkflow, 'node scripts/ci/validate-commercial-intent-contract.mjs', 'Theme CI omits the commercial-intent contract.'],
  [themeCiWorkflow, 'php scripts/ci/validate-commercial-intent-runtime.php', 'Theme CI omits the commercial-intent runtime contract.'],
  [themeCiWorkflow, 'Get-ChildItem scripts/wp -File -Filter *.ps1', 'Theme CI does not syntax-parse every WordPress PowerShell script.'],
  [themeCiWorkflow, 'bash scripts/deploy/smoke-test.sh --validate-manifest', 'Theme CI does not validate the offline smoke manifest.'],
  [deployWorkflow, 'ALLOW_LEGACY_RELEASE_MARKER=true EXPECTED_THEME_VERSION=', 'Automatic recovery does not scope legacy release-marker compatibility to post-rollback smoke.'],
  [rollbackWorkflow, 'ALLOW_LEGACY_RELEASE_MARKER=true bash scripts/deploy/smoke-test.sh', 'Manual rollback cannot smoke-test an exact legacy backup that predates release markers.'],
  [preflightScript, 'class NoRedirectHandler', 'Theme preflight may follow redirects.'],
  [preflightScript, 'missing_status == http["unknown_status"]', 'Theme preflight does not require exact missing-route status.'],
	[preflightScript, 'capabilities.get(capability) is True', 'Theme preflight does not enforce required Agent Core capabilities.'],
	[preflightScript, 'store.get("tables_ready") is True', 'Theme preflight does not enforce dependency store readiness.'],
	[preflightScript, 'ASSISTED_PROPOSAL_STORE_HEALTH', 'Theme preflight does not enforce the exact assisted-proposal schema contract.'],
  [buildThemeScript, 'release_requirements_sha256', 'Theme build manifest does not bind release requirements.'],
  [buildThemeScript, 'seo_registry_sha256', 'Theme build manifest does not bind the authoritative SEO registry.'],
  [deployScript, 'REQUIRED_GATEWAY_VERSION', 'Theme uploader does not enforce the required deployment gateway version.'],
  [deployScript, 'version_at_least(data["gateway_version"], required_gateway_version)', 'Theme uploader does not compare the live gateway against the release floor.'],
  [deployWorkflow, 'python3 scripts/ci/verify_theme_preflight.py --requirements "$THEME_RELEASE_REQUIREMENTS_FILE" --site-url "$WP_SITE_URL"', 'Theme workflow does not run the live dependency and HTTP preflight.'],
  [deployWorkflow, 'release_requirements_sha256', 'Theme workflow does not verify packaged release requirements.'],
  [deployWorkflow, 'source_requirements_sha256', 'Theme workflow does not compare packaged release requirements with checkout source.'],
  [deployWorkflow, 'actual_seo_registry_sha256', 'Theme workflow does not verify the packaged SEO registry.'],
  [deployWorkflow, 'source_seo_registry_sha256', 'Theme workflow does not compare the packaged SEO registry with checkout source.'],
]) requireText(source, needle, message);

if ((deployWorkflow.match(/ALLOW_LEGACY_RELEASE_MARKER=true/g) || []).length !== 1) {
  failures.push('Automatic deployment may allow a missing release marker only once, after exact-backup recovery.');
}

if (deployWorkflow.includes('SMOKE_PATHS: ${{ vars.SMOKE_PATHS }}') || deployWorkflow.includes('SMOKE_MARKERS: ${{ vars.SMOKE_MARKERS }}') || rollbackWorkflow.includes('SMOKE_PATHS: ${{ vars.SMOKE_PATHS }}') || rollbackWorkflow.includes('SMOKE_MARKERS: ${{ vars.SMOKE_MARKERS }}')) {
  failures.push('Production and rollback smoke coverage must come from the versioned manifest, not mutable GitHub variables.');
}

if (smokeScript.includes('parse_qs(asset.query)') || smokeScript.includes('stylesheet version does not match the expected release')) {
  failures.push('Release identity must not be inferred from the filemtime-based stylesheet cache key.');
}

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
  ['get-theme-status.sh" "$RESPONSE_FILE"', 'Direct theme deployment bypasses the bounded authenticated status helper.'],
]) requireText(deployScript, needle, message);

for (const [needle, message] of [
  ['THEME_STATUS_ATTEMPTS:-6', 'Theme status checks do not have a bounded attempt count.'],
  ['ATTEMPTS > 12', 'Theme status attempt configuration is not capped.'],
  ['--connect-timeout 8 --max-time 15', 'Each theme status attempt is not time bounded.'],
  ['sleep 2', 'Theme status retries do not use a short fixed delay.'],
  ['data.get("theme") != "tra-vel-v2"', 'Theme status retries accept an unexpected theme identity.'],
  ['data.get("gateway_version", "")', 'Theme status retries do not require gateway identity.'],
  ['chmod 600 "$OUTPUT_FILE"', 'Theme status evidence is not persisted with private permissions.'],
]) requireText(statusScript, needle, message);
if (statusScript.includes('--retry ') || statusScript.includes('Retry-After')) {
  failures.push('Theme status polling must use its bounded loop instead of server-directed curl retry delays.');
}

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

if ((deployWorkflow.match(/bash scripts\/deploy\/get-theme-status\.sh/g) || []).length < 4) {
  failures.push('Theme verification and rollback guards do not consistently use bounded JSON status polling.');
}

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
  if (!deployJobEnvironment.includes('timeout-minutes: 25')) {
    failures.push('The theme deploy job must reserve a 25-minute window for bounded verification and exact-backup recovery.');
  }
  if (deployJobEnvironment.includes('secrets.WP_USERNAME') || deployJobEnvironment.includes('secrets.WP_APP_PASSWORD')) {
    failures.push('WordPress deployment credentials must be scoped only to the steps that use them.');
  }
}

const verificationIndex = deployWorkflow.indexOf('- name: Verify deployed identity and website, or restore exact backup');
const receiptArtifactIndex = deployWorkflow.indexOf('- name: Preserve deployment receipt');
if (verificationIndex === -1 || receiptArtifactIndex === -1 || receiptArtifactIndex < verificationIndex) {
  failures.push('Theme receipt evidence must be preserved after live verification/recovery so artifact publication cannot block rollback.');
} else {
  const verificationSection = deployWorkflow.slice(verificationIndex, receiptArtifactIndex);
  const recoveryBudgetRequirements = [
    ['verification_budget_seconds=360', 'Initial post-deploy verification must have a six-minute total budget.'],
    ['verification_started=$SECONDS', 'Initial post-deploy verification does not start a shared deadline before status polling.'],
    ['verification_remaining=$((verification_budget_seconds - verification_elapsed))', 'Route smoke does not consume only the time remaining after identity verification.'],
    ['timeout --signal=TERM --kill-after=5s "${verification_remaining}s"', 'Initial route smoke is not constrained by the remaining shared verification budget.'],
    ['env EXPECTED_THEME_VERSION="$expected_theme_version"', 'The bounded initial route smoke is not bound to the deployment manifest version.'],
    ['timeout --signal=TERM --kill-after=5s 360s', 'Post-rollback route smoke is not bounded to six minutes.'],
    ['env ALLOW_LEGACY_RELEASE_MARKER=true EXPECTED_THEME_VERSION="$restored_theme_version"', 'The bounded rollback smoke does not preserve exact-version legacy-marker recovery semantics.'],
    ['starting recovery checks', 'Verification deadline failures do not visibly enter the existing recovery path.'],
  ];
  for (const [needle, message] of recoveryBudgetRequirements) requireText(verificationSection, needle, message);

  if ((verificationSection.match(/timeout --signal=TERM --kill-after=5s/g) || []).length !== 2) {
    failures.push('The verification/recovery step must contain exactly two workflow-level smoke deadlines: initial verification and post-rollback verification.');
  }

  const budgetStartIndex = verificationSection.indexOf('verification_started=$SECONDS');
  const initialStatusIndex = verificationSection.indexOf('if bash scripts/deploy/get-theme-status.sh theme-status.json');
  const remainingBudgetIndex = verificationSection.indexOf('verification_remaining=$((verification_budget_seconds - verification_elapsed))');
  const initialSmokeDeadlineIndex = verificationSection.indexOf('timeout --signal=TERM --kill-after=5s "${verification_remaining}s"');
  const recoveryPathIndex = verificationSection.indexOf('if [[ "$verified" != "true" ]]');
  const rollbackMutationIndex = verificationSection.indexOf('bash scripts/deploy/rollback-theme.sh "$backup"');
  const rollbackSmokeDeadlineIndex = verificationSection.indexOf('timeout --signal=TERM --kill-after=5s 360s');
  if (
    budgetStartIndex === -1
    || initialStatusIndex === -1
    || remainingBudgetIndex === -1
    || initialSmokeDeadlineIndex === -1
    || recoveryPathIndex === -1
    || rollbackMutationIndex === -1
    || rollbackSmokeDeadlineIndex === -1
    || !(budgetStartIndex < initialStatusIndex
      && initialStatusIndex < remainingBudgetIndex
      && remainingBudgetIndex < initialSmokeDeadlineIndex
      && initialSmokeDeadlineIndex < recoveryPathIndex
      && recoveryPathIndex < rollbackMutationIndex
      && rollbackMutationIndex < rollbackSmokeDeadlineIndex)
  ) {
    failures.push('Verification deadlines must start before status polling, constrain only the remaining route-smoke time, and leave bounded post-rollback verification inside the recovery path.');
  }
}

const livePreflightIndex = deployWorkflow.indexOf('- name: Verify live dependencies and missing-route semantics');
const uploadIndex = deployWorkflow.indexOf('- name: Upload isolated theme through WordPress REST');
if (livePreflightIndex === -1 || uploadIndex === -1 || livePreflightIndex > uploadIndex) {
  failures.push('Live dependency and HTTP preflight must finish before any theme mutation.');
}

if (failures.length) {
  console.error('Tra-Vel theme deployment contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Tra-Vel theme deployment contract validation passed (release and route identity, bounded verification and recovery, receipt persistence, exact-backup recovery, owner lease, confirmations, version guards, and fail-closed filesystem).');
