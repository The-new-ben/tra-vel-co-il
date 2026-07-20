import { existsSync, readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const registryPath = join(repoRoot, 'content', 'seo', 'content-opportunity-registry.json');
const provisionerPath = join(repoRoot, 'scripts', 'wp', 'provision-seo-registry-pages.ps1');
const helperPath = join(repoRoot, 'theme', 'tra-vel-v2', 'inc', 'seo-opportunities.php');
const templatePath = join(repoRoot, 'theme', 'tra-vel-v2', 'page-seo-opportunity.php');
const destinationTemplatePath = join(repoRoot, 'theme', 'tra-vel-v2', 'page-destination.php');
const seoPath = join(repoRoot, 'theme', 'tra-vel-v2', 'inc', 'seo.php');
const assetsPath = join(repoRoot, 'theme', 'tra-vel-v2', 'inc', 'assets.php');
const cssPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'css', 'app.css');
const buildPath = join(repoRoot, 'scripts', 'ci', 'build_theme.py');
const deployWorkflowPath = join(repoRoot, '.github', 'workflows', 'deploy-theme.yml');
const failures = [];

function fail(message) {
  failures.push(message);
}

function read(path, label) {
  if (!existsSync(path)) {
    fail(`${label} is missing.`);
    return '';
  }
  return readFileSync(path, 'utf8');
}

function expect(source, pattern, message) {
  if (!pattern.test(source)) fail(message);
}

const registrySource = read(registryPath, 'SEO registry');
const provisioner = read(provisionerPath, 'SEO opportunity provisioner');
const helper = read(helperPath, 'SEO opportunity runtime helper');
const template = read(templatePath, 'SEO opportunity template');
const destinationTemplate = read(destinationTemplatePath, 'Destination guide template');
const seo = read(seoPath, 'Global SEO policy');
const assets = read(assetsPath, 'Theme asset loader');
const css = read(cssPath, 'Theme stylesheet');
const build = read(buildPath, 'Theme build script');
const deployWorkflow = read(deployWorkflowPath, 'Theme deployment workflow');

let registry = { entries: [] };
try { registry = JSON.parse(registrySource); } catch (error) { fail(`SEO registry is invalid JSON: ${error.message}`); }
const opportunities = (registry.entries || []).filter(entry => ['decision-guide', 'transactional-cluster'].includes(entry.pageType));
if (!opportunities.length) fail('SEO registry has no decision or transactional opportunities.');

// Child opportunities go public one explicit release at a time. Every exposed
// owner must be named here with its full repo evidence file; anything else in
// live/content-ready status still fails the build.
const approvedExposedOwners = new Map([
  ['budapest-packages', {
    pageType: 'transactional-cluster',
    canonicalPath: '/packages/budapest/',
    mapState: 'budapest',
    evidencePath: join(repoRoot, 'content', 'seo', 'opportunities', 'budapest-packages-2026.meta.json'),
  }],
]);
const exposedRealEntries = opportunities.filter(entry => ['live', 'content-ready'].includes(entry.status));
const unapprovedExposed = exposedRealEntries.filter(entry => !approvedExposedOwners.has(entry.id));
if (unapprovedExposed.length) fail(`Registry exposes child opportunities without a named release: ${unapprovedExposed.map(entry => entry.id).join(', ')}.`);
for (const [ownerId, release] of approvedExposedOwners) {
  const entry = exposedRealEntries.find(item => item.id === ownerId);
  if (!entry) {
    fail(`Approved release owner ${ownerId} is not exposed by the registry.`);
    continue;
  }
  if (entry.pageType !== release.pageType || entry.canonicalPath !== release.canonicalPath || entry.mapState !== release.mapState) {
    fail(`Approved release owner ${ownerId} drifted from its released identity.`);
  }
  if (!existsSync(release.evidencePath)) {
    fail(`Approved release owner ${ownerId} is missing its repo evidence packet.`);
  }
}

for (const id of ['bangkok-hotels', 'larnaca-packages', 'paphos-packages']) {
  const entry = opportunities.find(item => item.id === id);
  if (!entry) {
    fail(`Real registry fixture ${id} is missing.`);
    continue;
  }
  const match = String(entry.canonicalPath).match(/^\/(flights|hotels|packages)\/([a-z0-9-]+)\/$/);
  if (!match || entry.parentPath !== `/${match[1]}/`) fail(`${id} does not satisfy the canonical vertical/parent contract.`);
}

expect(helper, /in_array\( \$entry\['status'\].*array\( 'live', 'content-ready' \)/s, 'Runtime eligibility does not explicitly limit status to live/content-ready.');
expect(helper, /tra_vel_v2_get_guide_publication_contract\( \$post_id \)/, 'Decision readiness does not reuse the shared guide publication contract.');
expect(helper, /'publication_evidence'.*'publish-ready'/s, 'Decision readiness lacks explicit publish-ready evidence.');
expect(helper, /'supporting_content'.*>= 800/s, 'Transactional content threshold is missing.');
expect(helper, /'conversion_ready'.*_tra_vel_seo_conversion_ready/s, 'Transactional explicit conversion gate is missing.');
const editOnlyMetaContexts = helper.match(/'context'\s*=>\s*array\( 'edit' \)/g) || [];
if (editOnlyMetaContexts.length < 2) fail('Internal owner/readiness meta is not restricted to REST edit context.');
if (/show_in_rest'\s*=>\s*true/.test(helper)) fail('Internal owner/readiness meta still uses public show_in_rest=true.');
expect(helper, /'semantic_parent'.*tra_vel_v2_seo_opportunity_semantic_parent_ready/s, 'Semantic parent gate is missing.');
expect(helper, /function tra_vel_v2_get_public_seo_opportunity_links[\s\S]*tra_vel_v2_is_exposable_seo_opportunity[\s\S]*get_page_by_path[\s\S]*tra_vel_v2_seo_opportunity_identity_matches[\s\S]*tra_vel_v2_get_seo_opportunity_publication_contract/, 'Public cluster links do not require exposable status, an exact WordPress owner and the full publication contract.');
expect(helper, /'id'\s*=>[\s\S]*'url'\s*=>[\s\S]*'title'\s*=>[\s\S]*'kind'\s*=>[\s\S]*'cta'\s*=>/, 'Public cluster links do not expose the bounded traveler-facing field set.');
expect(helper, /function tra_vel_v2_get_owned_seo_opportunity[\s\S]*get_permalink[\s\S]*get_seo_opportunity_by_path/, 'Protection owner discovery is not path-based.');
expect(helper, /function tra_vel_v2_get_protected_seo_opportunity[\s\S]*get_owned_seo_opportunity[\s\S]*_tra_vel_seo_opportunity_id[\s\S]*\['by_id'\]/, 'Protection hooks cannot recover a validated owner after canonical path drift.');
expect(helper, /function tra_vel_v2_get_current_seo_opportunity[\s\S]*page-seo-opportunity\.php[\s\S]*get_owned_seo_opportunity/, 'Rendering getter is not separately template-gated.');
expect(helper, /function tra_vel_v2_is_seo_opportunity_page_request[\s\S]*is_singular\( 'page' \)/, 'Current-query SEO hooks are not scoped to singular pages.');
expect(helper, /template_redirect.*protect_seo_opportunity_route_identity|protect_seo_opportunity_route_identity.*template_redirect/s, 'Wrong-template, wrong-owner and backlog paths lack a local 404 guard.');
expect(helper, /wpseo_robots_array.*seo_opportunity_yoast_robots_policy|seo_opportunity_yoast_robots_policy.*wpseo_robots_array/s, 'Yoast lacks an explicit noindex/follow gate.');
expect(helper, /function tra_vel_v2_seo_opportunity_aioseo_robots_policy[\s\S]*\$output\['noindex'\][\s\S]*\$output\['nofollow'\]/s, 'AIOSEO lacks an associative noindex/follow gate.');
expect(helper, /add_filter\( 'aioseo_robots_meta', 'tra_vel_v2_seo_opportunity_aioseo_robots_policy'/, 'AIOSEO robots gate is not registered.');
expect(helper, /array\( 'Product', 'Offer', 'ItemList' \)/, 'Plugin graph does not suppress unvalidated commercial schema.');
expect(helper, /'decision-guide'.*\/destinations\//s, 'Decision canonical/semantic-parent validation is missing.');
expect(helper, /\$canonical_vertical.*'transactional-cluster'/s, 'Transactional CTA is not driven by its canonical vertical.');
expect(helper, /\$primary_product = \$products\[0\]/, 'Decision CTA does not preserve ordered monetization intent.');
if (/'tokyo'\s*=>\s*'HND'/.test(helper)) fail('Tokyo is still biased to a single airport instead of city-wide intent.');

expect(seo, /function tra_vel_v2_should_noindex_public_request[\s\S]*incomplete_guide\s*=\s*is_singular\(\)[\s\S]*has_facets/s, 'Global robots policy lacks a shared, singular-safe noindex predicate.');
expect(seo, /function tra_vel_v2_aioseo_robots_policy[\s\S]*\$output\['noindex'\][\s\S]*\$output\['nofollow'\]/s, 'Global private/facet/guide policy lacks AIOSEO associative robots parity.');
expect(seo, /add_filter\( 'aioseo_robots_meta', 'tra_vel_v2_aioseo_robots_policy'/, 'Global AIOSEO robots policy is not registered.');

expect(template, /class="directory-page"/, 'SEO template does not reuse the readable directory page shell.');
expect(template, /class="article-prose page-width section"/, 'SEO template does not reuse long-form prose typography.');
expect(template, /data-destination-map-state=/, 'SEO template lacks contextual Earth state.');
expect(template, /data-globe-3d/, 'SEO template lacks the compact interactive Earth.');
expect(template, /data-destination=.*aria-label=.*primaryIntent/s, 'Earth marker does not keep full intent in its accessible label.');
if (/class="price-pin[^>]*>\s*<\?php echo esc_html\( \$entry\['primaryIntent'\]/s.test(template)) fail('Earth marker visibly renders the full primary intent and can overflow.');
expect(template, /tra_vel_v2_render_guide_evidence/, 'Decision pages do not render visible editorial evidence.');
expect(template, /\$publication_contract\s*=\s*tra_vel_v2_get_seo_opportunity_publication_contract[\s\S]*! empty\( \$publication_contract\['ready'\] \)[\s\S]*tra_vel_v2_get_public_seo_opportunity_links/, 'SEO opportunity sibling links are not gated by the current page publication contract.');
expect(template, /if \( \$cluster_links \)[\s\S]*class="seo-cluster-link-card"[\s\S]*href="<\?php echo esc_url\( \$cluster_link\['url'\] \)/, 'SEO opportunity sibling links are not conditional escaped server-rendered anchors.');
expect(destinationTemplate, /\$guide_cluster_links\s*=\s*\$guide_is_ready[\s\S]*'publish-ready'[\s\S]*\$guide_registry_matches[\s\S]*tra_vel_v2_get_public_seo_opportunity_links/, 'Destination child links are not gated by guide readiness, publish-ready evidence and exact registry identity.');
expect(destinationTemplate, /if \( \$guide_cluster_links \)[\s\S]*class="seo-cluster-link-card"[\s\S]*href="<\?php echo esc_url\( \$cluster_link\['url'\] \)/, 'Destination child links are not conditional escaped server-rendered anchors.');
if (/article-content/.test(template)) fail('SEO template uses undefined article-content styling.');
expect(assets, /page-seo-opportunity\.php[\s\S]*tra-vel-v2-globe-3d|tra-vel-v2-globe-3d[\s\S]*page-seo-opportunity\.php/s, 'SEO opportunity pages do not enqueue the interactive globe runtime.');
expect(css, /\.destination-globe-toolbar button\s*\{[^}]*min-width:\s*44px[^}]*min-height:\s*44px/s, 'Contextual Earth toolbar controls are smaller than 44px.');
expect(css, /\.compact-map \.globe-webgl \.price-pin\s*\{[^}]*min-width:\s*44px[^}]*min-height:\s*44px/s, 'Contextual Earth pins are smaller than 44px.');
expect(css, /\.seo-cluster-link-grid[\s\S]*\.seo-cluster-link-card[\s\S]*@media \(max-width: 620px\)/, 'Publication-gated cluster cards lack their responsive layout contract.');
expect(helper, /wp_sitemaps_posts_query_args[\s\S]*wpseo_exclude_from_sitemap_by_post_ids[\s\S]*aioseo_sitemap_exclude_posts/s, 'Unready managed opportunity pages are not excluded across core, Yoast and AIOSEO sitemaps.');
expect(helper, /function tra_vel_v2_unready_seo_opportunity_page_ids[\s\S]*get_posts[\s\S]*_tra_vel_seo_opportunity_id[\s\S]*_wp_page_template/s, 'Sitemap exclusion does not union registry paths with owner/template candidates.');

expect(provisioner, /\[ValidateSet\('draft', 'publish'\)\]/, 'Provisioner does not constrain write status.');
expect(provisioner, /Test-RegistryEntryEligible/, 'Provisioner lacks registry eligibility enforcement.');
expect(provisioner, /Assert-RegistryContract -Registry \$registry/, 'Provisioner does not validate the entire registry before selection or writes.');
for (const negativeMarker of ['duplicate unselected canonical', 'duplicate normalized intent', 'missing unselected parent', 'invalid unselected map state', 'unexpected unselected field', 'malformed unselected monetization', 'non-commercial registry parent']) {
  if (!provisioner.includes(negativeMarker)) fail(`Provisioner contract tests lack negative coverage for: ${negativeMarker}.`);
}
expect(provisioner, /status = 'draft'/, 'Provisioner lacks draft-only creation.');
expect(provisioner, /Structural page.*must remain draft and unowned/, 'Provisioner does not protect decision structural ancestors.');
expect(provisioner, /Slug = 'guides'.*DraftOnly = \$false/s, 'Provisioner does not preserve the public guides directory index.');
expect(provisioner, /Slug = \$cluster.*DraftOnly = \$true/s, 'Provisioner does not keep the technical guide cluster draft and unowned.');
expect(provisioner, /Existing authored page preserved without mutation/, 'Draft provisioning does not use create-if-missing preservation for existing authored pages.');
expect(provisioner, /Production host.*requires -Environment production/, 'Production hostname can bypass the production environment binding.');
expect(provisioner, /AllowedStagingHost[\s\S]*\$requiredStagingConfirmation\s*=\s*"USE TRA-VEL SEO STAGING HOST \$siteHost"[\s\S]*\$StagingHostConfirmation -cne \$requiredStagingConfirmation[\s\S]*staging-specific credential file/s, 'Staging confirmation is not exactly hostname-bound or staging credential isolation is incomplete.');
expect(provisioner, /staging accepted the old generic confirmation phrase[\s\S]*staging confirmation was not bound to the selected hostname/s, 'Provisioner contract tests do not reject generic and wrong-host staging confirmations.');
expect(provisioner, /Staging writes require an explicitly supplied -CredentialPath[\s\S]*\$PSBoundParameters\.ContainsKey\('CredentialPath'\)/s, 'Staging can use an implicit credential path.');
expect(provisioner, /TimeoutSec = \$RequestTimeoutSeconds/, 'WordPress REST requests lack a bounded timeout.');
expect(provisioner, /Public opportunity owner requires a supported map state/, 'Public opportunity owners can omit a supported map state.');
expect(provisioner, /Find-PublishedPageByCanonicalPath[\s\S]*ExpectedTemplate[\s\S]*page-destination\.php/s, 'Decision semantic-parent template is not verified.');
expect(provisioner, /Assert-StructuralPageIdentity[\s\S]*exact path re-query after creation/s, 'Structural creation lacks authenticated readback and exact path re-query.');
expect(provisioner, /draft permalink template does not resolve to the exact registry canonical/, 'Draft final identity does not verify its generated canonical.');
expect(provisioner, /postPublishParentId[\s\S]*Assert-DecisionEvidence[\s\S]*Assert-TransactionalEvidence/s, 'Published readback is not revalidated against evidence and operational parent gates.');
expect(provisioner, /PROMOTE TRA-VEL SEO OPPORTUNITY/, 'Provisioner lacks explicit promotion confirmation.');
expect(provisioner, /5000 are required/, 'Provisioner lacks the 5000-word decision threshold.');
expect(provisioner, /800 are required/, 'Provisioner lacks the 800-word transaction threshold.');
expect(provisioner, /foreach \(\$source in \$sources\)[\s\S]*Assert-FreshIsoDate -Value \(\[string\]\$source\.checkedAt\)[\s\S]*\$sourceDate -gt \$checkedDate/s, 'Decision publication does not freshness-check every source or align it with the aggregate review date.');
expect(provisioner, /stale individual source record passed[\s\S]*source checked after aggregate review date passed/s, 'Provisioner contract tests do not reject stale or review-date-misaligned source records.');
expect(provisioner, /MaximumRedirection = 0/, 'Provisioner does not reject redirect-based REST false positives.');
expect(provisioner, /No live or content-ready.*No WordPress request was made/s, 'Provisioner does not short-circuit an empty eligible registry without WordPress contact.');
expect(provisioner, /the first publication phase does not keep both readiness flags exact boolean false[\s\S]*partial readiness was accepted before the final publication phase/s, 'Provisioner contract tests do not enforce exact fail-closed readiness values.');

const preserveReady = provisioner.indexOf('Already-published valid page preserved without mutation');
const unreadyPreconditionBody = provisioner.indexOf('$unreadyPreconditionBody =');
const unreadyPreconditionRequest = provisioner.indexOf('$unreadyPreconditionSaved = Invoke-WpRequest', unreadyPreconditionBody);
const unreadyPreconditionReadback = provisioner.indexOf('$unreadyPreconditionReadback = Invoke-WpRequest', unreadyPreconditionRequest);
const unreadyPreconditionAssert = provisioner.indexOf('Assert-PersistedDisabledReadinessEvidence -Page $unreadyPreconditionReadback', unreadyPreconditionReadback);
const publishUnreadyBody = provisioner.indexOf('$publishUnreadyBody =', unreadyPreconditionAssert);
const publishUnreadyRequest = provisioner.indexOf('$publishedUnready = Invoke-WpRequest', publishUnreadyBody);
const unreadyReadback = provisioner.indexOf('$unreadyReadback = Invoke-WpRequest', publishUnreadyRequest);
const unreadyReadbackAssert = provisioner.indexOf('Assert-PersistedDisabledReadinessEvidence -Page $unreadyReadback', unreadyReadback);
const postPublishParent = provisioner.indexOf('$postPublishParentId = Resolve-StructuralChain', unreadyReadbackAssert);
const postPublishDecisionEvidence = provisioner.indexOf('Assert-DecisionEvidence -Page $unreadyReadback', postPublishParent);
const postPublishTransactionEvidence = provisioner.indexOf('Assert-TransactionalEvidence -Page $unreadyReadback', postPublishParent);
const enableReadinessBody = provisioner.indexOf('$enableReadinessBody =', postPublishParent);
const enableReadinessRequest = provisioner.indexOf('$readinessSaved = Invoke-WpRequest', enableReadinessBody);
const readyReadback = provisioner.indexOf('$readyReadback = Invoke-WpRequest', enableReadinessRequest);
const readyReadbackAssert = provisioner.indexOf('Assert-PersistedReadinessEvidence -Page $readyReadback', readyReadback);
const finalParent = provisioner.indexOf('$finalParentId = Resolve-StructuralChain', readyReadbackAssert);
const finalDecisionEvidence = provisioner.indexOf('Assert-DecisionEvidence -Page $readyReadback', finalParent);
const finalSemanticParent = provisioner.indexOf('Find-PublishedPageByCanonicalPath', finalDecisionEvidence);
const finalTransactionEvidence = provisioner.indexOf('Assert-TransactionalEvidence -Page $readyReadback', finalParent);
const publicationSuccess = provisioner.indexOf('Published after fail-closed two-phase verification', finalTransactionEvidence);
const orderedPublicationMarkers = [
  preserveReady,
  unreadyPreconditionBody,
  unreadyPreconditionRequest,
  unreadyPreconditionReadback,
  unreadyPreconditionAssert,
  publishUnreadyBody,
  publishUnreadyRequest,
  unreadyReadback,
  unreadyReadbackAssert,
  postPublishParent,
  postPublishDecisionEvidence,
  postPublishTransactionEvidence,
  enableReadinessBody,
  enableReadinessRequest,
  readyReadback,
  readyReadbackAssert,
  finalParent,
  finalDecisionEvidence,
  finalSemanticParent,
  finalTransactionEvidence,
  publicationSuccess,
];
if (orderedPublicationMarkers.some(position => position < 0)) {
  fail('Fail-closed two-phase publication markers are incomplete.');
} else if (orderedPublicationMarkers.some((position, index) => index > 0 && position <= orderedPublicationMarkers[index - 1])) {
  fail('Readiness can be enabled before published-unready identity, evidence, semantic and operational verification completes.');
}
expect(provisioner, /\$publishUnreadyBody\s*=\s*@\{[\s\S]*?status = 'publish'[\s\S]*?State 'unready'[\s\S]*?\$publishedUnready = Invoke-WpRequest/s, 'The publish transition does not explicitly keep readiness disabled.');
expect(provisioner, /\$enableReadinessBody\s*=\s*@\{\s*meta = \(New-OpportunityReadinessMeta -Entry \$entry -State 'ready'\)\s*\}/s, 'The final phase is not a readiness-only update.');
const contractReturn = provisioner.indexOf("if ($ContractTest)");
const credentialImport = provisioner.indexOf('Import-Clixml');
const requestCall = provisioner.indexOf('Invoke-WebRequest');
if (contractReturn < 0 || credentialImport < 0 || contractReturn > credentialImport || contractReturn > requestCall) {
  fail('Contract-test mode is not isolated before credentials and network calls.');
}

expect(build, /content-opportunity-registry\.json/, 'Theme build does not bundle the authoritative SEO registry.');
const registryReads = build.match(/SEO_REGISTRY_PATH\.read_bytes\(\)/g) || [];
if (registryReads.length !== 1) fail(`Theme build must read registry bytes once; found ${registryReads.length} reads.`);
expect(build, /seo_registry_sha256 = hashlib\.sha256\(seo_registry_bytes\)/, 'Theme manifest hash is not derived from the packaged registry bytes.');
expect(deployWorkflow, /seo_registry\s*!=\s*"tra-vel-v2\/content\/seo\/content-opportunity-registry\.json"[\s\S]*seo_registry_sha256[\s\S]*actual_seo_registry_sha256/s, 'Deploy workflow does not validate the exact registry path and packaged hash.');

const shellCandidates = process.platform === 'win32' ? ['pwsh.exe', 'powershell.exe', 'pwsh', 'powershell'] : ['pwsh', 'powershell'];
let contractResult = null;
for (const command of shellCandidates) {
  const probe = spawnSync(command, ['-NoProfile', '-Command', '$PSVersionTable.PSVersion.ToString()'], { encoding: 'utf8' });
  if (probe.error || probe.status !== 0) continue;
  contractResult = spawnSync(command, ['-NoProfile', '-File', provisionerPath, '-ContractTest'], { cwd: repoRoot, encoding: 'utf8' });
  break;
}
if (!contractResult) fail('PowerShell is unavailable; provision contract tests did not run.');
else if (contractResult.status !== 0) fail(`Provision contract tests failed: ${(contractResult.stderr || contractResult.stdout).trim()}`);
else if (!/contract tests passed/i.test(contractResult.stdout)) fail('Provision contract test did not emit its success marker.');

if (failures.length) {
  console.error('Tra-Vel SEO opportunity provisioning validation failed:');
  failures.forEach(message => console.error(`- ${message}`));
  process.exit(1);
}

console.log(`Tra-Vel SEO opportunity provisioning validation passed (${opportunities.length - exposedRealEntries.length} backlog owners remain unexposed; released: ${exposedRealEntries.map(entry => entry.id).join(', ') || 'none'}).`);
