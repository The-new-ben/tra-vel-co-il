import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const themeRoot = join(repoRoot, 'theme', 'tra-vel-v2');
const failures = [];

const requiredFiles = [
  'style.css',
  'theme.json',
  'release-requirements.json',
  'functions.php',
  'header.php',
  'footer.php',
  'inc/class-commercial-provenance.php',
  'index.php',
  'front-page.php',
  'page-map.php',
  'page-destination.php',
  'page-experience.php',
  'page-directory.php',
  'page-saved.php',
  'page-account.php',
  'page-partners.php',
  'inc/auth.php',
  'inc/setup.php',
  'inc/assets.php',
  'inc/discovery.php',
  'inc/suppliers/bootstrap.php',
  'inc/suppliers/interface-supplier-adapter.php',
  'inc/suppliers/class-demo-supplier-adapter.php',
  'inc/suppliers/class-open-meteo-supplier-adapter.php',
  'inc/suppliers/class-supplier-registry.php',
  'inc/suppliers/class-discovery-repository.php',
  'inc/flights/bootstrap.php',
  'inc/flights/interface-flight-search-adapter.php',
  'inc/flights/class-demo-flight-search-adapter.php',
  'inc/flights/class-flight-search-registry.php',
  'inc/flights/class-flight-search-repository.php',
  'inc/flights/class-flight-search-controller.php',
  'inc/hotels/bootstrap.php',
  'inc/hotels/interface-hotel-search-adapter.php',
  'inc/hotels/class-demo-hotel-search-adapter.php',
  'inc/hotels/class-hotel-search-registry.php',
  'inc/hotels/class-hotel-search-repository.php',
  'inc/hotels/class-hotel-search-controller.php',
  'inc/insurance/bootstrap.php',
  'inc/insurance/interface-insurance-quote-adapter.php',
  'inc/insurance/class-demo-insurance-quote-adapter.php',
  'inc/insurance/class-insurance-quote-registry.php',
  'inc/insurance/class-insurance-quote-repository.php',
  'inc/insurance/class-insurance-quote-controller.php',
  'inc/packages/bootstrap.php',
  'inc/packages/interface-trip-package-adapter.php',
  'inc/packages/class-demo-trip-package-adapter.php',
  'inc/packages/class-trip-package-registry.php',
  'inc/packages/class-trip-package-repository.php',
  'inc/packages/class-trip-package-controller.php',
  'inc/workspace/bootstrap.php',
  'inc/workspace/class-traveler-workspace-controller.php',
  'inc/handoffs/bootstrap.php',
  'inc/handoffs/class-supplier-handoff-controller.php',
  'inc/handoffs/class-whatsapp-sales-handoff-provider.php',
  'inc/template-tags.php',
  'inc/guides.php',
  'inc/hero-queue.php',
  'inc/seo.php',
  'assets/css/app.css',
  'assets/js/app.js',
  'assets/js/globe-3d.js',
  'assets/data/discovery-demo.json',
  'assets/data/discovery.schema.json',
  'assets/data/flight-search-demo.json',
  'assets/data/flight-search.schema.json',
  'assets/data/hotel-search-demo.json',
  'assets/data/hotel-search.schema.json',
  'assets/data/insurance-quote-demo.json',
  'assets/data/insurance-quote.schema.json',
  'assets/data/trip-package-demo.json',
  'assets/data/trip-package.schema.json',
  'assets/data/traveler-workspace.schema.json',
  'assets/data/supplier-handoff.schema.json',
  'assets/data/guide-source-packet.schema.json',
  'assets/data/home-hero-queue.json',
  'assets/data/editorial-directory.json',
  'assets/vendor/lucide.min.js',
  'assets/images/earth-blue-marble.jpg',
  'assets/images/earth-blue-marble-2048.jpg',
  'assets/images/thailand.jpg'
];

for (const file of requiredFiles) {
  if (!existsSync(join(themeRoot, file))) failures.push(`Missing required theme file: ${file}`);
}

const requiredRepositoryContracts = [
  'content/seo/content-opportunity-registry.json',
  'scripts/ci/validate-content-opportunity-registry.mjs',
  'scripts/ci/validate-public-copy.mjs',
  'scripts/ci/validate-guide-publication-runtime.php',
  '.github/workflows/theme-ci.yml',
  '.github/workflows/deploy-theme.yml'
];

for (const file of requiredRepositoryContracts) {
  if (!existsSync(join(repoRoot, file))) failures.push(`Missing required repository contract: ${file}`);
}

const themeCiWorkflowPath = join(repoRoot, '.github', 'workflows', 'theme-ci.yml');
const deployWorkflowPath = join(repoRoot, '.github', 'workflows', 'deploy-theme.yml');
const themeCiWorkflow = existsSync(themeCiWorkflowPath) ? readFileSync(themeCiWorkflowPath, 'utf8') : '';
const deployWorkflow = existsSync(deployWorkflowPath) ? readFileSync(deployWorkflowPath, 'utf8') : '';
for (const [name, source] of [['theme CI', themeCiWorkflow], ['theme deploy', deployWorkflow]]) {
  if (!source.includes('node scripts/ci/validate-content-opportunity-registry.mjs')) failures.push(`The ${name} workflow does not validate the SEO content opportunity registry.`);
  if (!source.includes('php scripts/ci/validate-guide-publication-runtime.php')) failures.push(`The ${name} workflow does not enforce the guide publication runtime contract.`);
  if (!source.includes('python3 scripts/ci/verify_theme_preflight.py --requirements theme/tra-vel-v2/release-requirements.json --validate-only')) failures.push(`The ${name} workflow does not validate theme release requirements without network access.`);
}
if ((themeCiWorkflow.match(/- "content\/seo\/\*\*"/g) || []).length < 2) failures.push('Theme CI must run for both pull-request and push changes to the SEO content registry.');
if (!themeCiWorkflow.includes('& scripts/wp/sync-guide.ps1 -GuideId contract-test -ContractTest')) failures.push('Theme CI does not execute the offline nested-guide sync contract tests.');
if (!themeCiWorkflow.includes('node scripts/ci/validate-public-copy.mjs')) failures.push('Theme CI does not enforce the public traveler-copy contract.');

const style = readFileSync(join(themeRoot, 'style.css'), 'utf8');
for (const header of ['Theme Name: Tra-Vel V2', 'Version:', 'Requires at least:', 'Requires PHP:', 'Text Domain: tra-vel-v2']) {
  if (!style.includes(header)) failures.push(`style.css is missing header: ${header}`);
}
if (!style.includes('Requires at least: 6.6')) failures.push('theme.json version 3 requires a WordPress 6.6 minimum.');

try {
  const themeJson = JSON.parse(readFileSync(join(themeRoot, 'theme.json'), 'utf8'));
  if (themeJson.version !== 3) failures.push('theme.json must use schema version 3.');
  const palette = themeJson?.settings?.color?.palette || [];
  const slugs = palette.map((item) => item.slug);
  if (new Set(slugs).size !== slugs.length) failures.push('theme.json contains duplicate color slugs.');
} catch (error) {
  failures.push(`theme.json is invalid JSON: ${error.message}`);
}

function walk(directory) {
  return readdirSync(directory).flatMap((name) => {
    const path = join(directory, name);
    return statSync(path).isDirectory() ? walk(path) : [path];
  });
}

const allFiles = walk(themeRoot);
const forbidden = allFiles.filter((path) => /(?:^|[\\/])(?:\.git|node_modules|dist|output)(?:[\\/]|$)/.test(path));
for (const path of forbidden) failures.push(`Forbidden packaged path: ${relative(themeRoot, path)}`);

const publicCopyFiles = allFiles.filter((path) =>
  (path.endsWith('.php') || path.endsWith('.js')) &&
  !path.includes(`${join('assets', 'vendor')}${String.fromCharCode(92)}`) &&
  !path.includes(`${join('assets', 'vendor')}/`)
);
for (const path of publicCopyFiles) {
  if (/[—–]/u.test(readFileSync(path, 'utf8'))) {
    failures.push(`Public theme copy uses em dash or en dash punctuation: ${relative(themeRoot, path)}`);
  }
}

for (const path of allFiles.filter((file) => /\.(?:php|js|json)$/.test(file) && !file.includes(join('assets', 'vendor')))) {
  if (/הדגמה|מדריך הדגל|נתוני הדגמה/u.test(readFileSync(path, 'utf8'))) {
    failures.push(`Public theme data exposes internal prototype language: ${relative(themeRoot, path)}`);
  }
}

for (const path of allFiles.filter((file) => file.endsWith('.php'))) {
  const source = readFileSync(path, 'utf8');
  if (/\b(?:var_dump|print_r)\s*\(/.test(source)) failures.push(`Debug output found in ${relative(themeRoot, path)}`);
  if (!source.includes('<?php')) failures.push(`PHP opening tag missing in ${relative(themeRoot, path)}`);
}

const appCss = readFileSync(join(themeRoot, 'assets/css/app.css'), 'utf8');
if (!appCss.includes('direction: rtl')) failures.push('RTL direction is missing from the production stylesheet.');
const baseGlobeRule = appCss.match(/(?:^|\n)\.globe\s*\{([^}]*)\}/)?.[1] || '';
if (!baseGlobeRule || /url\([^)]*earth-blue-marble/i.test(baseGlobeRule)) failures.push('The initial globe paint must remain a neutral loading surface without a stacked Earth image.');
if (!appCss.includes(".globe-webgl.globe-3d-unavailable { background-image: url('../images/earth-blue-marble-2048.jpg');")) failures.push('The same-origin Earth image must appear only through the explicit WebGL-unavailable fallback.');

const appJs = readFileSync(join(themeRoot, 'assets/js/app.js'), 'utf8');
const globeJs = readFileSync(join(themeRoot, 'assets/js/globe-3d.js'), 'utf8');
const frontPage = readFileSync(join(themeRoot, 'front-page.php'), 'utf8');
const homeSearchMarkerIndex = frontPage.indexOf('data-home-search data-product-kind');
const homeSearchStartIndex = homeSearchMarkerIndex >= 0 ? frontPage.lastIndexOf('<form', homeSearchMarkerIndex) : -1;
const homeSearchEndIndex = homeSearchMarkerIndex >= 0 ? frontPage.indexOf('</form>', homeSearchMarkerIndex) : -1;
const homeSearchMarkup = homeSearchStartIndex >= 0 && homeSearchEndIndex > homeSearchStartIndex
  ? frontPage.slice(homeSearchStartIndex, homeSearchEndIndex + '</form>'.length)
  : '';
const homeSearchTabs = [...frontPage.matchAll(/<button\b[^>]*role="tab"[^>]*>/g)].map(match => match[0]);
const homeDiscoverySource = appJs.match(/const homeSearchProductContracts[\s\S]*?\nfunction initControls\(\)/)?.[0] || '';
if (!homeSearchMarkup) failures.push('The homepage is missing its canonical discovery form.');
const noScriptDiscovery = [...frontPage.matchAll(/<noscript>[\s\S]*?<\/noscript>/g)].map(match => match[0]).find(source => source.includes('product-links-noscript')) || '';
if (!noScriptDiscovery.includes('product-links-noscript')) failures.push('The homepage needs an explicit no-JavaScript discovery fallback.');
for (const route of ['/packages/', '/flights/', '/hotels/', '/travel-insurance/']) {
  if (!noScriptDiscovery.includes(route)) failures.push(`The no-JavaScript discovery fallback is missing ${route}.`);
}
if (!noScriptDiscovery.includes('$map_url')) failures.push('The no-JavaScript discovery fallback must preserve access to the destination map.');
if (!frontPage.includes('class="product-tabs" role="tablist"') || homeSearchTabs.length !== 5) failures.push('Homepage products must remain one five-option accessible tablist.');
if (homeSearchTabs.filter(tab => tab.includes('aria-selected="true"') && tab.includes('tabindex="0"')).length !== 1) failures.push('Exactly one homepage product tab must be selected and keyboard reachable on first render.');
if (homeSearchTabs.some(tab => !tab.includes('aria-controls="home-search-panel"'))) failures.push('Every homepage product tab must control the shared search panel.');
for (const kind of ['package', 'flights', 'hotels', 'packages', 'insurance']) {
  if (!homeSearchTabs.some(tab => tab.includes(`data-product-kind="${kind}"`))) failures.push(`The homepage tablist is missing the ${kind} product contract.`);
}
for (const attribute of ['id="home-search-panel"', 'role="tabpanel"', 'aria-labelledby="home-search-tab-package"', 'aria-describedby="home-search-status"', 'aria-busy="false"', 'data-map-action']) {
  if (!homeSearchMarkup.includes(attribute)) failures.push(`The homepage discovery panel is missing ${attribute}.`);
}
for (const name of ['origin', 'destination', 'departure_date', 'return_date', 'adults', 'children', 'rooms']) {
  if (!homeSearchMarkup.includes(`name="${name}"`)) failures.push(`The homepage discovery form is missing the canonical ${name} control.`);
}
for (const marker of ['data-home-origin-wrap', 'data-home-destination', 'data-home-departure', 'data-home-return', 'data-home-adults', 'data-home-children', 'data-home-rooms-wrap', 'data-home-rooms', 'data-home-search-submit']) {
  if (!homeSearchMarkup.includes(marker)) failures.push(`The homepage discovery form is missing ${marker}.`);
}
if (homeSearchMarkup.includes('class="sr-only"') || /name="(?:route|dates|travelers)"/.test(homeSearchMarkup)) failures.push('The homepage discovery form cannot regress to invisible fixed-value route, date, or traveler inputs.');
if (/type="hidden"[^>]*name="rooms"/.test(homeSearchMarkup) || !homeSearchMarkup.includes('data-uses-rooms="true"') || !homeDiscoverySource.includes('form.dataset.usesRooms = String(contract.usesRooms)') || !appCss.includes('.search-dock[data-uses-rooms="false"] [data-home-rooms-wrap] { display: none; }')) failures.push('Rooms must remain a visible, product-aware, price-affecting homepage criterion instead of a hidden carried value.');
const homeDestinationLoopCount = (frontPage.match(/foreach \( \$home_destinations as \$destination \)/g) || []).length;
for (const marker of [
  "'/assets/data/discovery-demo.json'",
  '$home_destinations',
  '$home_destination_ids',
  "$hero_campaign['map_state']",
  '$home_default_destination === $destination_id',
  'data-code="<?php echo esc_attr( $airport_code ); ?>"',
  'data-slug="<?php echo esc_attr( $destination_id ); ?>"'
]) {
  if (!frontPage.includes(marker)) failures.push(`The data-driven homepage destination contract is missing ${marker}.`);
}
if (homeDestinationLoopCount < 3) failures.push('Homepage default resolution, Earth pins, and product destinations must all derive from the discovery destination collection.');
if (!homeSearchMarkup.includes('data-code="anywhere" data-slug="anywhere"')) failures.push('Homepage discovery is missing its explicit open-ended destination choice.');
if (/class="price-pin pin-(?:bangkok|athens|budapest|prague|vienna|dubai|tokyo|lisbon)\b/.test(frontPage)) failures.push('The homepage Earth still contains a hard-coded destination pin instead of the discovery loop.');
try {
  const homeDiscovery = JSON.parse(readFileSync(join(themeRoot, 'assets/data/discovery-demo.json'), 'utf8'));
  const destinations = Array.isArray(homeDiscovery.destinations) ? homeDiscovery.destinations : [];
  const baselineDestinationCodes = new Map([
    ['budapest', 'BUD'], ['prague', 'PRG'], ['vienna', 'VIE'], ['athens', 'ATH'],
    ['dubai', 'DXB'], ['bangkok', 'BKK'], ['tokyo', 'HND'], ['lisbon', 'LIS']
  ]);
  const destinationIds = destinations.map(destination => destination?.id);
  if (destinations.length < baselineDestinationCodes.size) failures.push('Homepage discovery cannot drop a baseline supported destination when the collection expands.');
  if (new Set(destinationIds).size !== destinationIds.length) failures.push('Homepage discovery destination identities must remain unique.');
  for (const destination of destinations) {
    if (!/^[a-z0-9-]+$/.test(destination?.id || '') || !/^[A-Z]{3}$/.test(destination?.airport?.code || '')) failures.push('Every discovery destination needs a stable slug and IATA-style airport code.');
    if (!Array.isArray(homeDiscovery?.route_sets?.[destination?.id]) || homeDiscovery.route_sets[destination.id].length < 2) failures.push(`Homepage discovery destination ${destination?.id || '(missing id)'} needs at least two planning routes.`);
  }
  for (const [id, code] of baselineDestinationCodes) {
    const destination = destinations.find(candidate => candidate?.id === id);
    if (!destination || destination?.airport?.code !== code) failures.push(`Homepage discovery is missing the ${id}/${code} identity pair.`);
  }
} catch (error) {
  failures.push(`Homepage discovery data is invalid JSON: ${error.message}`);
}
for (const marker of ['$home_route_sets', '$home_default_routes', 'data-home-route-data', 'data-home-route-cards', 'data-home-route-airport', 'data-home-route-link']) {
  if (!frontPage.includes(marker)) failures.push(`The homepage route comparison is missing its discovery-driven contract marker ${marker}.`);
}
for (const marker of ['function initHomeRouteExamples', 'function renderHomeRouteComparison', 'homeRouteExamples[data.id]', 'renderHomeRouteComparison(data);']) {
  if (!appJs.includes(marker)) failures.push(`Earth selections cannot rebuild the homepage route comparison: ${marker}.`);
}
if (/שלוש דרכים להגיע לבנגקוק|destination=BKK|ישיר עם EL AL/u.test(frontPage)) failures.push('The server-rendered homepage route section is still hard-coded to Bangkok instead of the selected Earth destination.');
for (const marker of ['tra_vel_v2_select_home_discovery_destination', '$home_default_context', 'data-result-context']) {
  if (!frontPage.includes(marker)) failures.push(`The evergreen homepage needs its neutral daily discovery contract marker ${marker}.`);
}
if (/\?\s*\$hero_campaign\['map_state'\]\s*:\s*['"]bangkok['"]/u.test(frontPage)) failures.push('The evergreen homepage still hard-codes Bangkok as its discovery result.');
if (!appJs.includes('motion.userSelected === true') || !appJs.includes("resultContext.textContent = 'היעד שבחרתם'")) failures.push('The homepage result must claim traveler choice only after a direct destination selection.');
for (const step of ['product', 'criteria', 'handoff']) {
  if (!frontPage.includes(`data-home-search-step="${step}"`)) failures.push(`Homepage routing progress is missing the ${step} checkpoint.`);
}
if (!frontPage.includes('id="home-search-status" data-home-search-status role="status" aria-live="polite" aria-atomic="true"')) failures.push('Homepage discovery progress must use one atomic polite status region described by the form.');
if (!homeDiscoverySource || !homeDiscoverySource.includes('function homeSearchProductContract(kind)')) failures.push('The homepage does not expose a deterministic product-query contract.');
if (!appJs.includes('initHomeDiscoverySearch();')) failures.push('The production app does not initialize the homepage discovery search.');
for (const marker of ['function syncHomeSearchTripContext(form)', 'syncHomeSearchTripContext(form);', 'refreshHomeDestinationPlanLinks();', "form.addEventListener('input', syncCurrentSearch)", "form.addEventListener('change', syncCurrentSearch)"]) {
  if (!homeDiscoverySource.includes(marker) && !appJs.includes(marker)) failures.push(`Homepage planning links do not follow current visible search values: ${marker}.`);
}
for (const marker of ['function validDiscoveryBudgetQuery(', "homePlanningLinkContext('flights')", "homePlanningLinkContext('hotels')", "homePlanningLinkContext('insurance')", "homePlanningLinkContext('ai')", "homePlanningLinkContext('map')"]) {
  if (!appJs.includes(marker)) failures.push(`Homepage planning continuity is missing ${marker}.`);
}
if (!homeDiscoverySource.includes("selectedOption.dataset.code === 'anywhere'") || !homeDiscoverySource.includes("form.dataset.mapAction || '/travel-map/'") || !homeDiscoverySource.includes("url.searchParams.set('destination_mode', 'anywhere')") || !homeDiscoverySource.includes("url.searchParams.set('product', form.dataset.productKind || 'package')") || !homeDiscoverySource.includes('scheduleHomeSearchNavigation(homeSearchNavigationUrl(form, anywhere))')) failures.push('The open-ended homepage choice must hand off its trip context to an unselected travel map without inventing a destination.');
if (!homeDiscoverySource.includes("window.addEventListener('pageshow'") || !homeDiscoverySource.includes("form.setAttribute('aria-busy', 'false')") || !homeDiscoverySource.includes('submit.disabled = false') || !homeDiscoverySource.includes("setHomeSearchStep(progress, 'handoff', 'waiting'")) failures.push('Back/Forward restoration must clear homepage busy, disabled-submit, and handoff progress state.');
if (!homeDiscoverySource.includes('function setHomeSearchRoutingState(form, message)') || !homeDiscoverySource.includes("form.dataset.state = 'navigating'") || !homeDiscoverySource.includes("setHomeSearchStep(progress, 'handoff', 'running'") || !homeDiscoverySource.includes('function scheduleHomeSearchNavigation(url)') || !/window\.requestAnimationFrame\(\(\) => window\.requestAnimationFrame\(\(\) => window\.setTimeout\(navigate,\s*\d+\)\)\)/.test(homeDiscoverySource)) failures.push('Homepage progress must represent a double-paint navigation handoff, not an invented supplier or booking milestone.');
if (/הספק (?:אישר|אישרה)|ההזמנה הושלמה|המחיר (?:אושר|ננעל)|נשמר אצל הספק/u.test(`${homeSearchMarkup}\n${homeDiscoverySource}`)) failures.push('Homepage routing progress cannot claim supplier confirmation, completed booking, or a locked price.');
if (!/\.product-tabs button\s*\{[^}]*min-height:\s*44px;/.test(appCss) || !/\.search-field input,\.search-field select\s*\{[^}]*min-height:\s*44px;/.test(appCss)) failures.push('Homepage tabs and visible discovery controls must retain 44px touch targets.');
if (!appCss.includes('.product-tabs button:focus-visible') || !appCss.includes('.search-field:focus-within') || !appCss.includes('.search-field input:focus-visible,.search-field select:focus-visible')) failures.push('Homepage product tabs and each discovery control need visible keyboard focus treatment.');
if (!appCss.includes('.search-dock[data-uses-origin="false"] .search-route-field .search-field-controls { grid-template-columns: minmax(0,1fr);') || !appCss.includes('.search-dock[data-uses-origin="false"] .search-route-field .search-field-controls > :is(i,svg) { display: none;')) failures.push('Hotel and insurance discovery must collapse the disabled origin and route arrow without leaving an empty column.');
if (!/@media \(max-width: 1000px\)[\s\S]*?\.search-dock \{ grid-template-columns: repeat\(2,minmax\(0,1fr\)\);/.test(appCss)) failures.push('Homepage discovery is missing its two-column tablet layout.');
if (!/@media \(max-width: 760px\)[\s\S]*?\.search-dock \{ grid-template-columns: 1fr;/.test(appCss)) failures.push('Homepage discovery is missing its single-column mobile layout.');
if (!/@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.search-dock\[data-state="navigating"\] \.search-submit,\.home-search-progress li,\.home-search-progress li > :is\(i,svg\) \{ animation: none !important; \}/.test(appCss)) failures.push('Homepage routing progress is missing its SVG-compatible reduced-motion path.');
const homeSearchProgressRule = appCss.match(/\.home-search-progress\s*\{([^}]*)\}/)?.[1] || '';
if (!homeSearchProgressRule || /position:\s*(?:absolute|fixed|sticky)/.test(homeSearchProgressRule)) failures.push('Homepage progress must remain in document flow instead of covering the search or globe.');
if (!appCss.includes('.home-search-progress li[data-state="running"] > :is(i,svg)') || !appCss.includes('.map-progress-checkpoints li[data-state="running"] > :is(i,svg)')) failures.push('Lucide progress animation must continue after icons are replaced with inline SVG elements.');
if (!appJs.includes('window.traVelV2')) failures.push('The app script is not connected to localized WordPress configuration.');
if (!appJs.includes('agentRestUrl') || !appJs.includes("agentApiRequest('/runs'")) failures.push('The AI planner is not connected to the private Agent Core POST contract.');
if (!appJs.includes('window.sessionStorage') || !appJs.includes("credentials: 'same-origin'")) failures.push('The private agent run ID is not retained per tab or requests do not include the protected same-origin cookie.');
if (appJs.includes('run_token') || appJs.includes('X-Tra-Vel-Run-Token') || appJs.includes('agentTokenStorageKey')) failures.push('The private agent bearer secret is exposed to JavaScript instead of the HttpOnly ownership cookie.');
if (!appJs.includes('mergeAndRenderAgentEvents') || !appJs.includes("event?.visible === false")) failures.push('The AI planner does not render the actual visible server event log.');
if (!appJs.includes('discoveryUrl')) failures.push('The app script is not connected to the discovery REST contract.');
if (!appJs.includes('hydrateDiscovery')) failures.push('The app script does not hydrate the map from discovery data.');
if (!appJs.includes('flightSearchUrl')) failures.push('The app script is not connected to the flight search REST contract.');
if (!appJs.includes('initFlightSearch')) failures.push('The app script does not initialize the flight comparison product.');
if (!appJs.includes('hotelSearchUrl')) failures.push('The app script is not connected to the hotel search REST contract.');
if (!appJs.includes('initHotelSearch')) failures.push('The app script does not initialize the hotel comparison product.');
if (!appJs.includes('insuranceQuoteUrl')) failures.push('The app script is not connected to the insurance quote REST contract.');
if (!appJs.includes('initInsuranceQuote')) failures.push('The app script does not initialize the insurance comparison product.');
if (!appJs.includes('packageSearchUrl')) failures.push('The app script is not connected to the trip package REST contract.');
if (!appJs.includes('initTripPackageSearch')) failures.push('The app script does not initialize the total-trip package product.');
if (!appJs.includes('workspaceUrl')) failures.push('The app script is not connected to the private traveler workspace contract.');
if (!appJs.includes('initTravelerWorkspace')) failures.push('The app script does not initialize the saved-trip workspace.');
if (!appJs.includes('createSaveOfferButton')) failures.push('Comparison cards cannot save decisions into the traveler workspace.');
if (!appJs.includes('initDirectory')) failures.push('The destination directory does not initialize its search and filters.');
if (!appJs.includes('discoveryDataMode')) failures.push('Map prices are not gated by the live supplier data mode.');
if (!appJs.includes('hasLiveRouteData = discoverySnapshotIsCurrent() && hasRouteSnapshot') || !appJs.includes('המחיר האחרון שנבדק · בדקו שוב')) failures.push('Route cards can expose non-current prices without clear traveler-facing recheck guidance.');
if (!appJs.includes('discoveryCacheFreshness') || !appJs.includes('discoverySourceFreshness') || !appJs.includes('discoveryFreshnessLabel()')) failures.push('The client collapses cache refresh failures and stale supplier observations into one misleading freshness state.');
if (!appJs.includes('function settledDiscoveryResponseState()') || !appJs.includes('function discoveryFreshnessLabel()') || !appJs.includes('const verificationLabels =') || !appJs.includes('discoveryLiveLayers[activeLayer]') || !appJs.includes('setDiscoveryStatus(confirmedState')) failures.push('Fallback, stale, and unverified map results must resolve through explicit truth-state and layer-verification contracts.');
if (!appJs.includes("showRouteSnapshot ? `△ ${route.cons[0]}` : '△ מחיר ותנאים דורשים בדיקה לפי תאריכים'")) failures.push('Non-live route tradeoffs can expose unsupported savings or conditions.');
if (!appJs.includes('resolveDiscoveryLiveLayers') || !appJs.includes('discoveryLiveLayers.deals') || !appJs.includes('discoveryLiveLayers.weather')) failures.push('Mixed supplier mode is not separated into truthful layer-level provenance.');
if (!appJs.includes('payload.field_provenance') || !appJs.includes('weather_season') || /function resolveDiscoveryLiveLayers\(providerStatus/.test(appJs)) failures.push('The map can still infer live fields from provider connection instead of server-owned field provenance.');
if (!appJs.includes('התנאים הנוכחיים עודכנו. התאמת העונה תיבדק לפי תאריך הנסיעה')) failures.push('Live current weather can still certify an editorial season recommendation.');
if (!appJs.includes("trip_destination: data.id")) failures.push('Map insurance actions do not preserve the selected destination context.');
if (!appJs.includes("activeLayer = layer && discoveryLayers.has(layer) ? layer : 'deals'") || !appJs.includes("activePlanIntent = intent && destinationPlanIntents[intent] ? intent : 'smart'") || !appJs.includes('intentConstraints')) failures.push('Map history and intent-only deep links cannot restore deterministic discovery defaults.');
if (!appJs.includes('function destinationDirectoryUrl(destinationId, params = {})') || !appJs.includes('destinationGuidePaths[destinationId]') || !appJs.includes('`/destinations/#destination-${directoryId}`') || !appJs.includes("destinationPlanUrl('/destinations/', params)")) failures.push('Destination activity links can still point to an unprovisioned guide route or an empty filtered guide directory.');
for (const directoryId of ['budapest', 'prague', 'vienna', 'athens', 'dubai', 'thailand', 'tokyo', 'lisbon']) {
  if (!appJs.includes(`'${directoryId}'`)) failures.push(`Client destination-directory routing is missing ${directoryId}.`);
}
for (const [destinationId, guidePath] of Object.entries({ budapest: '/destinations/budapest/', prague: '/destinations/prague/', athens: '/destinations/athens/', bangkok: '/destinations/thailand/' })) {
  if (!appJs.includes(`${destinationId}: '${guidePath}'`)) failures.push(`Published destination ${destinationId} is missing its real guide route.`);
}
for (const target of ['flights', 'hotels', 'insurance', 'packages', 'ai']) {
  if (!appJs.includes(`discoveryTripContextQuery('${target}')`)) failures.push(`Map decisions do not preserve the sanitized trip context for ${target}.`);
}
if (!appJs.includes('agentPlanningContextFromLocation') || !appJs.includes('planning_context: agentPlanningContextFromLocation()')) failures.push('Earth selections are not carried into Agent Core as structured planning context.');
if (!appJs.includes("const inferredKind = destination ? 'destination' : (hasCoordinates ? 'map_point' : 'free_text')") || !appJs.includes("params.get('selection_kind')")) failures.push('Reviewed destinations are collapsed into arbitrary map-point context when coordinates are present.');
if (!appJs.includes('planningSelectionHistoryState') || !appJs.includes('restorePlanningSelectionFromHistory') || !appJs.includes('planningSelection: planningSelectionHistoryState()')) failures.push('Map Back and Forward history can retain the previous selection identity or coordinates.');
if (!appJs.includes('restorePlanningSelectionFromUrl') || !appJs.includes("'selection_destination'") || !appJs.includes('const initialHydration = !restoredFreePoint') || !appJs.includes(': Promise.resolve();')) failures.push('An exact free-Earth point must survive reload/share and must not be replaced by initial destination hydration.');
for (const marker of [
  'const serverHomeDestination = String(homeGlobe?.dataset.defaultDestination',
  'const initialDiscoveryRequest = homeGlobe && activeDestination && !openEndedDestination',
  'discoveryRequestParams({ destination: activeDestination })',
  'hydrateDiscovery(initialDiscoveryRequest, { allowGlobeFocus: !homeGlobe',
  'allowConfirmedMotion: !homeGlobe',
  'autoEligible: Boolean(homeGlobe) && !hasRequestedDestination && !openEndedDestination && !restoredFreePoint'
]) {
  if (!appJs.includes(marker)) failures.push(`Homepage hydration is not anchored to explicit SSR and traveler intent: ${marker}.`);
}
if (!frontPage.includes('data-home-globe data-default-destination="<?php echo esc_attr( $home_default_destination ); ?>"')) failures.push('The homepage Earth does not expose its server-rendered default destination to hydration.');
if (!appJs.includes('async function hydrateDiscovery(params = {}, { allowGlobeFocus = true') || !appJs.includes('globeFocus: allowGlobeFocus')) failures.push('Homepage hydration cannot suppress a late response from refocusing the Earth after the reveal controller takes ownership.');
if (!appJs.includes('activePlanningSelection.destination !== key') || !appJs.includes("kind: 'destination'")) failures.push('A restored destination does not rebuild mismatched planning-selection context.');
if (!appJs.includes('renderAgentJourney') || !appJs.includes('completed > previousCompleted')) failures.push('Agent progress motion is not derived from actual journey advancement.');
if (!appJs.includes("!['provider_error', 'failed', 'cancelled'].includes(status)")) failures.push('Agent failure or cancellation can still trigger positive progress motion.');
if (!appJs.includes('failAgentJourneyConnection(root)') || !appJs.includes('pauseAgentJourneyTransport(root') || !appCss.includes('.agent-journey[data-transport="stale"] .agent-scope-board li.is-running > :is(i,svg)') || !appCss.includes('.agent-journey[data-transport="stale"] ~ .agent-event-panel .agent-event.is-running::before')) failures.push('Agent transport failures can leave a false or Lucide-incompatible working animation active.');
if (!appJs.includes('request?.planning_context?.scope') || !appJs.includes('latestAgentScopeEvent')) failures.push('The Agent scope board can diverge from exact requested scope or claim domain progress without events.');
if (!appJs.includes('setTextContentIfChanged(next, agentJourneyNextAction(run))') || !appJs.includes('setTextContentIfChanged(status, message)') || !appJs.includes('setTextContentIfChanged(supplier, latest.message)')) failures.push('Unchanged Agent polling can repeatedly announce live-region content.');

const mapPage = readFileSync(join(themeRoot, 'page-map.php'), 'utf8');
const destinationGlobePage = readFileSync(join(themeRoot, 'page-destination.php'), 'utf8');
const seoOpportunityPage = readFileSync(join(themeRoot, 'page-seo-opportunity.php'), 'utf8');
for (const [template, source] of [['front-page.php', frontPage], ['page-map.php', mapPage], ['page-destination.php', destinationGlobePage], ['page-seo-opportunity.php', seoOpportunityPage]]) {
  if (!source.includes('<noscript><img class="globe-noscript-image"') || !source.includes('earth-blue-marble-2048.jpg')) failures.push(`${template} is missing its explicit no-JavaScript Earth fallback.`);
}
if (!frontPage.includes('data-globe-selection-point')) failures.push('The homepage Earth is missing its exact selected-coordinate marker.');
for (const marker of ['map-view-layout', 'map-support-section', 'map-destination-panel', 'map-depth-section', 'destination-plan-360', 'data-destination-plan', 'data-plan-intent', 'data-plan-flight', 'data-plan-stay', 'data-plan-cover', 'data-plan-total', 'data-mobile-filter-host', 'data-budget-coverage', 'data-globe-3d', 'data-globe-canvas', 'data-globe-route', 'data-globe-selection-point', 'data-supported-radius-km', 'data-globe-selection', 'data-map-trip-context', 'data-map-trip-context-summary', 'data-map-progress-checkpoints', 'data-map-progress-live', 'destination-decision-cockpit', 'data-plan-meter', 'data-plan-modules', 'data-plan-ledger', 'data-plan-save']) {
  if (!mapPage.includes(marker)) failures.push(`The unobstructed map architecture is missing ${marker}.`);
}
for (const marker of ['data-map-entity-explorer', 'data-map-entity-plane', 'data-map-entity-segments', 'data-map-entity-markers', 'data-map-entity-empty', 'data-map-entity-detail', 'data-map-entity-kind', 'data-map-entity-title', 'data-map-entity-summary', 'data-map-entity-price', 'data-map-entity-truth', 'data-map-entity-freshness', 'data-map-entity-source', 'data-map-entity-action', 'data-map-entity-list', 'map-entity-boundary']) {
  if (!mapPage.includes(marker)) failures.push(`The in-flow map entity explorer is missing ${marker}.`);
}
const mapWorkspaceIndex = mapPage.indexOf('class="map-workspace page-width"');
const mapEntityExplorerIndex = mapPage.indexOf('data-map-entity-explorer');
const mapSupportIndex = mapPage.indexOf('class="map-support-section"');
if (mapWorkspaceIndex < 0 || mapEntityExplorerIndex <= mapWorkspaceIndex || mapSupportIndex <= mapEntityExplorerIndex
  || !/<\/section>\s*<\/div>\s*<section class="map-entity-section page-width"[^>]*data-map-entity-explorer/.test(mapPage)) failures.push('The entity detail plane must remain after the globe workspace and before the supporting comparison section, never inside the Earth paint surface.');
const mapEntityFlowRules = new Map([
  ['.map-entity-section', appCss.match(/\.map-entity-section\s*\{([^}]*)\}/)?.[1] || ''],
  ['.map-entity-layout', appCss.match(/\.map-entity-layout\s*\{([^}]*)\}/)?.[1] || ''],
  ['.map-entity-detail', appCss.match(/\.map-entity-detail\s*\{([^}]*)\}/)?.[1] || ''],
  ['.map-entity-list', appCss.match(/\.map-entity-list\s*\{([^}]*)\}/)?.[1] || '']
]);
for (const [selector, rule] of mapEntityFlowRules) {
  if (!rule || /position:\s*(?:absolute|fixed|sticky)/.test(rule)) failures.push(`${selector} must remain in document flow and cannot cover the globe.`);
}
const mapEntityPlaneRule = appCss.match(/\.map-entity-plane\s*\{([^}]*)\}/)?.[1] || '';
if (!/position:\s*relative/.test(mapEntityPlaneRule) || !/isolation:\s*isolate/.test(mapEntityPlaneRule) || !/overflow:\s*hidden/.test(mapEntityPlaneRule)) failures.push('The second-level map plane must contain its markers and route segments inside an isolated, clipped surface.');
if (!/\.map-entity-markers \[data-map-entity-marker\],\s*\.map-entity-marker\s*\{[^}]*min-width:\s*44px;[^}]*min-height:\s*44px;/.test(appCss)) failures.push('Every contained map entity marker must retain a 44 by 44 CSS-pixel target.');
if (!/\.map-entity-action\s*\{[^}]*min-height:\s*(?:4[4-9]|[5-9][0-9]|[1-9][0-9]{2,})px;/.test(appCss)) failures.push('The selected map entity action must retain at least a 44px touch target.');
const mapEntityCssStart = appCss.indexOf('.map-entity-section');
const mapEntityMobileStart = appCss.indexOf('@media (max-width: 760px)', mapEntityCssStart);
const mapEntityMobileEnd = appCss.indexOf('@media (max-width: 420px)', mapEntityMobileStart);
const mapEntityMobileSource = mapEntityMobileStart >= 0 && mapEntityMobileEnd > mapEntityMobileStart ? appCss.slice(mapEntityMobileStart, mapEntityMobileEnd) : '';
if (!mapEntityMobileSource.includes('.map-entity-layout { grid-template-columns: 1fr; }') || !mapEntityMobileSource.includes('.map-entity-list { grid-template-columns: 1fr; }') || !mapEntityMobileSource.includes('.map-entity-plane,.map-entity-empty { min-height: 320px; }')) failures.push('The map entity explorer must become a stable single-column surface on mobile.');
const mapEntityReducedMotionStart = appCss.indexOf('@media (prefers-reduced-motion: reduce)', mapEntityMobileEnd);
const mapEntityReducedMotionEnd = appCss.indexOf('/* Account and partner entry points */', mapEntityReducedMotionStart);
const mapEntityReducedMotionSource = mapEntityReducedMotionStart >= 0 && mapEntityReducedMotionEnd > mapEntityReducedMotionStart ? appCss.slice(mapEntityReducedMotionStart, mapEntityReducedMotionEnd) : '';
if (!mapEntityReducedMotionSource.includes('.map-entity-section.is-updating .map-entity-detail') || !mapEntityReducedMotionSource.includes('animation: none !important;') || !mapEntityReducedMotionSource.includes('.map-entity-markers [data-map-entity-marker]') || !mapEntityReducedMotionSource.includes('transition: none !important;')) failures.push('The entity detail plane is missing its reduced-motion path.');
for (const marker of [
  'normalizeMapEntityCollection(payload.map_entities, activeLayer, destinationData)',
  'normalizeMapSegmentCollection(payload.map_segments, destinationData)',
  'renderMapEntityExplorer(nextMapEntities, remainOpen ? [] : nextMapSegments',
  'const expectedKind = mapEntityKindByLayer[layer]',
  'kind !== expectedKind',
  'href.origin !== window.location.origin',
  'raw.bookable === false',
  "dataMode === 'demo' && truthState === 'planning'",
  'Object.prototype.hasOwnProperty.call(destinations, destinationId)',
  'seen.has(entity.id)',
  'seen.has(id)'
]) {
  if (!appJs.includes(marker)) failures.push(`The map entity payload is missing its fail-closed client contract: ${marker}.`);
}
const mapEntitySelectionStart = appJs.indexOf('function selectMapEntity(');
const mapEntitySelectionEnd = appJs.indexOf('\nfunction pinLabel(', mapEntitySelectionStart);
const mapEntitySelectionSource = mapEntitySelectionStart >= 0 && mapEntitySelectionEnd > mapEntitySelectionStart ? appJs.slice(mapEntitySelectionStart, mapEntitySelectionEnd) : '';
for (const marker of ['[data-map-entity-id]', "setAttribute('aria-pressed'", '[data-map-entity-title]', '[data-map-entity-summary]', '[data-map-entity-price]', '[data-map-entity-truth]', '[data-map-entity-freshness]', '[data-map-entity-source]', '[data-map-entity-action]', 'action.href = entity.action.href', 'action.dataset.requiresLiveSearch', '[data-map-entity-markers]', '[data-map-entity-list]', "addEventListener('click', () => selectMapEntity(entity.id, { commitPlanning: true }))", 'renderMapEntitySegments', 'focusGlobe: false']) {
  if (!mapEntitySelectionSource.includes(marker)) failures.push(`Map entity selection, detail, or action wiring is missing ${marker}.`);
}
for (const marker of ['mapEntityPlanningContext', 'setMapEntityPlanningSelection', 'activePlanningSelectionHandoffQuery', 'applyMapEntityPlanningContext', 'mapEntityActionUrl', 'activeMapEntitySelection', 'committedEntity', 'selection_destination']) {
  if (!appJs.includes(marker)) failures.push(`Selected map entities are not connected end to end to the editable trip: ${marker}.`);
}
if (!mapPage.includes('data-map-entity-selection-status')) failures.push('The in-flow map detail card does not visibly confirm when a point joins the trip plan.');
if (!appCss.includes('.destination-plan-360.has-map-selection-change [data-plan-module][data-map-selection="true"]')) failures.push('A real map-to-plan selection change has no bounded plan-module acknowledgement.');
for (const marker of ["'/assets/data/discovery-demo.json'", '$map_destinations', "foreach ( $map_destinations as $destination )", 'data-map-destination-link']) {
  if (!mapPage.includes(marker)) failures.push(`The server-rendered map is missing discovery-driven marker ${marker}.`);
}
for (const marker of ['$map_exploration_hubs', 'foreach ( $map_exploration_hubs as $exploration_hub )', 'data-exploration-hub=', 'data-radius-km=', 'data-live-search-scopes=', '--hub-static-x:', '--hub-static-y:']) {
  if (!mapPage.includes(marker)) failures.push(`The server-rendered exploration-hub layer is missing ${marker}.`);
}
for (const marker of ['$home_exploration_hubs', 'foreach ( $home_exploration_hubs as $exploration_hub )', 'data-exploration-hub=', 'data-radius-km=', 'data-live-search-scopes=', '--hub-static-x:', '--hub-static-y:']) {
  if (!frontPage.includes(marker)) failures.push(`The homepage Earth is missing its server-rendered exploration-hub layer marker ${marker}.`);
}
const homeDiscoveryGlobeIndex = frontPage.indexOf('data-home-globe');
const homeExplorationHubLoopIndex = frontPage.indexOf('foreach ( $home_exploration_hubs as $exploration_hub )');
const homeDiscoveryGlobeLiveIndex = frontPage.indexOf('data-globe-live', homeDiscoveryGlobeIndex);
if (homeDiscoveryGlobeIndex < 0 || homeExplorationHubLoopIndex <= homeDiscoveryGlobeIndex || homeDiscoveryGlobeLiveIndex <= homeExplorationHubLoopIndex) failures.push('Homepage exploration hubs must remain direct controls inside its Earth surface, before the live region and without an intervening poster.');
const homeExplorationHubTemplate = homeExplorationHubLoopIndex >= 0 && homeDiscoveryGlobeLiveIndex > homeExplorationHubLoopIndex ? frontPage.slice(homeExplorationHubLoopIndex, homeDiscoveryGlobeLiveIndex) : '';
if (homeExplorationHubTemplate.includes('<img') || !homeExplorationHubTemplate.includes('type="button"')) failures.push('Homepage exploration hubs must be direct buttons and cannot be represented by a poster image.');
const discoveryGlobeIndex = mapPage.indexOf('data-globe-3d data-discovery-globe');
const explorationHubLoopIndex = mapPage.indexOf('foreach ( $map_exploration_hubs as $exploration_hub )');
const discoveryGlobeLiveIndex = mapPage.indexOf('data-globe-live', discoveryGlobeIndex);
if (discoveryGlobeIndex < 0 || explorationHubLoopIndex <= discoveryGlobeIndex || discoveryGlobeLiveIndex <= explorationHubLoopIndex) failures.push('Exploration hubs must remain direct controls inside the Earth surface, without an intervening poster or second-click overlay.');
const explorationHubTemplate = explorationHubLoopIndex >= 0 && discoveryGlobeLiveIndex > explorationHubLoopIndex ? mapPage.slice(explorationHubLoopIndex, discoveryGlobeLiveIndex) : '';
if (explorationHubTemplate.includes('<img') || !explorationHubTemplate.includes('type="button"')) failures.push('Exploration hubs must be direct buttons and must not open through a poster image.');
if ((mapPage.match(/foreach \( \$map_destinations as \$destination \)/g) || []).length < 2) failures.push('Map pins and the keyboard-accessible destination index must both derive from the discovery contract.');
if ((mapPage.match(/class="price-pin pin-/g) || []).length !== 1 || /pin-(?:bangkok|athens|budapest|prague|vienna|dubai|tokyo|lisbon)\b/.test(mapPage)) failures.push('The map template still depends on a hard-coded destination pin set instead of the discovery loop.');
const mapTripContextRule = appCss.match(/\.map-trip-context\s*\{([^}]*)\}/)?.[1] || '';
if (!mapTripContextRule || /position:\s*(?:absolute|fixed|sticky)/.test(mapTripContextRule)) failures.push('Carried trip context must remain in document flow instead of covering the Earth.');
const mapTripContextIndex = mapPage.indexOf('data-map-trip-context');
if (mapTripContextIndex < mapPage.indexOf('class="map-status-row"') || mapTripContextIndex > mapPage.indexOf('data-globe-selection data-state')) failures.push('Carried trip context must render beneath the globe status and before the decision rail.');
if (mapPage.includes('map-search-floating')) failures.push('The map search must not float over the globe.');
if (mapPage.includes('style="left:') || mapPage.includes('style="right:')) failures.push('Map information must not use inline overlay positioning.');
if (!(mapPage.indexOf('class="map-layer-buttons"') < mapPage.indexOf('class="world-canvas"'))) failures.push('Mobile map control DOM order does not match the controls-first visual order.');
if (!appCss.includes('.theme-map-shell .route-sheet { position: static')) failures.push('Route comparison must remain below the globe in document flow.');
if (!appCss.includes('.map-mobile-controls { display: none !important; }')) failures.push('The legacy fixed mobile map bar is still allowed to cover the globe.');
if (!appCss.includes('.whatsapp-button { right: 20px !important; bottom: 20px !important; width: 58px !important;')) failures.push('The legacy WhatsApp action can still cover primary desktop content.');
if (!appCss.includes('.whatsapp-button { display: none !important; }')) failures.push('The legacy WhatsApp action can still cover mobile content and inline actions.');
if (!appCss.includes('.theme-map-shell .globe-webgl .price-pin:not(.is-active) { width: 44px; height: 44px; min-width: 44px;')) failures.push('Mobile globe destination targets must retain a 44px hit area.');
if (!/\.theme-map-shell \.globe-webgl \.exploration-hub \{[^}]*z-index:\s*6;[^}]*width:\s*44px;[^}]*height:\s*44px;[^}]*min-width:\s*44px;[^}]*min-height:\s*44px;/.test(appCss)) failures.push('Every exploration hub must remain a subtle 44 by 44 CSS-pixel control below destination-marker priority.');
if (!/\.home-globe-stack \.globe-webgl \.exploration-hub \{[^}]*z-index:\s*6;[^}]*width:\s*44px;[^}]*height:\s*44px;[^}]*min-width:\s*44px;[^}]*min-height:\s*44px;/.test(appCss)) failures.push('Every homepage exploration hub must retain a subtle 44 by 44 CSS-pixel control below destination-marker priority.');
if (!appCss.includes('.theme-map-shell .globe-webgl .exploration-hub:is(:focus-visible,.is-active) .exploration-hub-label { opacity: 1; visibility: visible;') || appCss.includes('.exploration-hub:hover .exploration-hub-label')) failures.push('Exploration-hub labels must appear only for keyboard focus, committed selection, or the bounded near-zoom label budget - never hover.');
if (!appCss.includes('.home-globe-stack .globe-webgl .exploration-hub:is(:focus-visible,.is-active) .exploration-hub-label { opacity: 1; visibility: visible;')) failures.push('Homepage exploration-hub labels must appear only after keyboard focus, committed selection, or the bounded near-zoom label budget.');
if (!appCss.includes('.theme-map-shell .globe-webgl[data-globe-lod="near"] .exploration-hub[data-globe-label="visible"] .exploration-hub-label { opacity: 1; visibility: visible;') || !appCss.includes('.home-globe-stack .globe-webgl[data-globe-lod="near"] .exploration-hub[data-globe-label="visible"] .exploration-hub-label { opacity: 1; visibility: visible;')) failures.push('The near-zoom hub label level of detail is missing its bounded CSS path on one of the discovery globes.');
if (!appCss.includes('.theme-map-shell .world-canvas { direction: rtl; min-width: 0; min-height: 650px; position: relative; overflow: hidden;') || !/\.exploration-hub-label \{[^}]*pointer-events:\s*none;/.test(appCss)) failures.push('Exploration-hub labels must remain clipped to the Earth surface and cannot intercept mobile controls.');
if (!appCss.includes('.theme-map-shell .globe-webgl.globe-3d-unavailable .exploration-hub { left: var(--hub-static-x) !important; top: var(--hub-static-y) !important;')) failures.push('The static Earth fallback must retain deterministic server-rendered exploration-hub coordinates.');
if (!appCss.includes('.home-globe-stack .globe-webgl.is-webgl-ready,\n.compact-map .globe-webgl.is-webgl-ready,\n.theme-map-shell .globe-webgl.is-webgl-ready { background-image: none; }')) failures.push('Every loaded 3D Earth must keep the static fallback out of the paint stack.');
if (!appCss.includes('transform: scale(var(--globe-depth,1));')) failures.push('Globe depth must scale the visual marker without shrinking its mobile hit area.');
if (!/function bindDestinationPin[\s\S]{0,520}addEventListener\('click'[\s\S]{0,520}setActiveDestination\([\s\S]{0,320}hydrateDiscovery\(/.test(appJs)) failures.push('Globe marker clicks must synchronize the destination support panel before refreshing discovery data.');
const destinationIndexMarkup = mapPage.match(/<nav class="map-destination-index"[\s\S]*?<\/nav>/)?.[0] || '';
if (!destinationIndexMarkup.includes('#destination-plan-title') || destinationIndexMarkup.includes('#map-support')) failures.push('The no-JavaScript destination index must land on the selected 360-degree plan.');
if (!/function bindMapDestinationLink[\s\S]*?event\.preventDefault\(\)[\s\S]*?setActiveDestination\([\s\S]*?hydrateDiscovery\(discoveryRequestParams\(\{ destination \}\)\)/.test(appJs)) failures.push('Destination-index links must update and hydrate the same in-place 360-degree route as globe pins.');
if (!/@media \(max-width: 760px\)[\s\S]*?\.theme-map-shell \.map-destination-copy \{ order: 1; \}[\s\S]*?\.theme-map-shell \.map-destination-panel > img \{ order: 2; position: static;/.test(appCss)) failures.push('Mobile selected-plan content must precede its in-flow destination image.');
for (const marker of ['reconcileDestinationPins', 'data-discovery-globe', 'discoveryRequestGeneration', 'AbortController', 'setRouteListBusy(true)', 'renderDiscoveryEmptyState', 'initDestinationPlan', 'updateDestinationPlan', 'mapDestinationWorkspaceItem', 'max_stops', 'max_duration', 'allow_overnight']) {
  if (!appJs.includes(marker)) failures.push(`Map discovery state is missing ${marker}.`);
}
for (const marker of ['discoverySelectedPlan', 'initGlobePointSelection', 'renderUnsupportedGlobeSelection', 'renderDestinationDecisionCockpit', 'activePlanCostLedger', 'selectedPlanForDestination', 'pointPlanScopes']) {
  if (!appJs.includes(marker)) failures.push(`The every-click 360-degree planning kernel is missing ${marker}.`);
}
for (const marker of ['normalizeExplorationHubCollection', 'explorationHubForPoint', 'renderExplorationHubSelection', 'bindExplorationHubMarker', "selectionKind === 'exploration_hub'", "link.dataset.requiresLiveSearch = 'true'", "'connectivity'", "'equipment'"]) {
  if (!appJs.includes(marker)) failures.push(`Exploration hubs are not wired through the shared in-flow 360-degree planning shell: ${marker}.`);
}
const hubFallbackBindingStart = appJs.indexOf('function bindExplorationHubMarker(');
const hubFallbackBindingEnd = appJs.indexOf('\nfunction bindMapDestinationLink(', hubFallbackBindingStart);
const hubFallbackBindingSource = hubFallbackBindingStart >= 0 && hubFallbackBindingEnd > hubFallbackBindingStart ? appJs.slice(hubFallbackBindingStart, hubFallbackBindingEnd) : '';
if (!hubFallbackBindingSource.includes('if (event.defaultPrevented) return;') || !hubFallbackBindingSource.includes('renderExplorationHubSelection({')) failures.push('The no-WebGL hub button must open the same plan while yielding to an active WebGL controller.');
const hubRenderStart = appJs.indexOf('function renderExplorationHubSelection(');
const hubRenderEnd = appJs.indexOf('\nfunction renderUnsupportedGlobeSelection(', hubRenderStart);
const hubRenderSource = hubRenderStart >= 0 && hubRenderEnd > hubRenderStart ? appJs.slice(hubRenderStart, hubRenderEnd) : '';
for (const productPath of ["'/flights/'", "'/hotels/'", "'/travel-insurance/'", "'/packages/'", "'/ai-planner/'"]) {
  if (!hubRenderSource.includes(productPath)) failures.push(`The exploration-hub 360 plan is missing its contextual live-search handoff ${productPath}.`);
}
if (/\$\s*\d|₪\s*\d|€\s*\d|£\s*\d/.test(hubRenderSource) || /(?:supplier|hotel|property)[-_ ](?:a|b|c)\b/i.test(hubRenderSource)) failures.push('Exploration-hub planning must not invent prices, named inventory, or supplier identities before live search.');
const geographicResolverStart = globeJs.indexOf('function resolveGeographicSelection(');
const geographicResolverEnd = globeJs.indexOf('\n  function createController(', geographicResolverStart);
const geographicResolverSource = geographicResolverStart >= 0 && geographicResolverEnd > geographicResolverStart ? globeJs.slice(geographicResolverStart, geographicResolverEnd) : '';
if (!geographicResolverSource.includes("selectionKind: 'destination'") || !geographicResolverSource.includes("selectionKind: 'exploration_hub'") || !geographicResolverSource.includes("selectionKind: 'map_point'") || geographicResolverSource.indexOf("selectionKind: 'destination'") > geographicResolverSource.indexOf("selectionKind: 'exploration_hub'")) failures.push('Geographic selection must deterministically resolve destination first, then an in-radius hub, then a safe generic point.');
if (!/candidates\.sort\([\s\S]*?priority:\s*3[\s\S]*?priority:\s*1|priority:\s*3[\s\S]*?priority:\s*1[\s\S]*?candidates\.sort/.test(globeJs) || !globeJs.includes('collisionFreeMarkerPlacement(candidate, placed, width, height)') || !globeJs.includes('placed.some(existing => boxesOverlap(box, existing))') || !globeJs.includes('const markerHeight = active || focused ? 88 : 44;') || globeJs.includes('!candidate.active && placed.some(existing => boxesOverlap(box, existing))')) failures.push('Exploration hubs must use collision-free bounded placement, including active labels, while destination markers retain priority.');
if (!globeJs.includes('Number(b.focused) - Number(a.focused)') || !globeJs.includes('Number(b.active) - Number(a.active)') || !globeJs.includes('if (!placement && (candidate.active || candidate.focused))') || !globeJs.includes('document.activeElement === marker')) failures.push('Collision management must prioritize and preserve the currently focused or committed Earth control.');
if (!appJs.includes('createPlanningSelectionId') || !appJs.includes('setActivePlanningSelection') || !appJs.includes('activePlanningSelectionQuery')) failures.push('Earth clicks do not retain a stable planning-selection identity.');
if (!/const pointContext = \{[\s\S]{0,360}selection_id:\s*selectionId[\s\S]{0,240}latitude:\s*latitude\.toFixed\(4\)[\s\S]{0,120}longitude:\s*longitude\.toFixed\(4\)/.test(appJs)) failures.push('Arbitrary Earth points can lose their exact coordinates during the AI handoff.');
if (!appJs.includes("selection_kind: 'map_point'")) failures.push('Arbitrary Earth handoffs do not preserve an explicit planning-context kind.');
if (!appJs.includes("meter.setAttribute('aria-valuenow', '1')") || !appJs.includes("label.textContent = 'בחירות מוכנות'") || !appJs.includes('fullTripCostScope.map') || !appJs.includes('עיר, מחיר, זמינות או אפשרות רכישה יוצגו רק אחרי זיהוי ובדיקה מתאימה')) failures.push('Unsupported Earth points must preserve the selected point, open all twelve planning scopes, and keep commercial facts behind identification and validation.');
if (!appJs.includes('const verifiedCount =') || !appJs.includes("module.state === 'live'") || !appJs.includes('setMapProgressCheckpoint') || !appJs.includes('discoveryCommercialDataIsCurrent') || !appJs.includes('announceMapProgress')) failures.push('Map progress is not separated into mapped scopes and authoritative verified progress.');
if (!appJs.includes("['fallback', 'error'].includes(mode) ? 'failed'") || !appJs.includes("point: 'waiting'") || !appJs.includes("modules: selectedPlan.modules.map(module => module.state === 'live' ? { ...module, state: 'stale' } : module)")) failures.push('Map progress can retain or celebrate stale, empty, or failed supplier state.');
if (!appJs.includes("if (animate && responseState === 'current')")) failures.push('Destination progress can celebrate an unverified or stale response.');
const fallbackDestinationSource = appJs.match(/const fallbackDestinations = \{([\s\S]*?)\n\};/)?.[1] || '';
const fallbackDestinationCount = (fallbackDestinationSource.match(/\bid:\s*'/g) || []).length;
if (!fallbackDestinationCount || (fallbackDestinationSource.match(/\blatitude:/g) || []).length < fallbackDestinationCount || (fallbackDestinationSource.match(/\blongitude:/g) || []).length < fallbackDestinationCount) failures.push('Fallback destinations can lose their Earth coordinates after an empty or filtered response.');
const pragueFallbackSource = fallbackDestinationSource.match(/^\s*prague:\s*\{(.+)\},?$/m)?.[1] || '';
if (!pragueFallbackSource.includes("id: 'prague'") || !pragueFallbackSource.includes('city-prague.webp') || !pragueFallbackSource.includes('latitude: 50.0755') || !pragueFallbackSource.includes('longitude: 14.4378')) failures.push('Prague is missing from the client fallback map with its canonical image and coordinates.');
const viennaFallbackSource = fallbackDestinationSource.match(/^\s*vienna:\s*\{(.+)\},?$/m)?.[1] || '';
if (!viennaFallbackSource.includes("id: 'vienna'") || !viennaFallbackSource.includes('city-vienna.webp') || !viennaFallbackSource.includes('latitude: 48.2082') || !viennaFallbackSource.includes('longitude: 16.3738')) failures.push('Vienna is missing from the client fallback map with its canonical image and coordinates.');
if (!appJs.includes("event.target.closest('[data-globe-3d][data-discovery-globe]')")) failures.push('Free-point Earth selection can still alter compact guide globes that have no 360-degree result surface.');
const homePointRenderStart = appJs.indexOf('function renderHomePointSelection(');
const homePointRenderEnd = appJs.indexOf('\nfunction initGlobePointSelection(', homePointRenderStart);
const homePointRenderSource = homePointRenderStart >= 0 && homePointRenderEnd > homePointRenderStart ? appJs.slice(homePointRenderStart, homePointRenderEnd) : '';
for (const selector of ['data-home-plan-flight', 'data-home-plan-stay', 'data-home-plan-transfer', 'data-home-plan-activity', 'data-home-plan-dining', 'data-home-plan-insurance', 'data-home-plan-connectivity', 'data-home-plan-equipment']) {
  if (!homePointRenderSource.includes(selector)) failures.push(`A homepage coordinate selection is missing its editable ${selector} handoff.`);
}
for (const marker of ['candidateHub', "selectionKind === 'exploration_hub'", 'explorationHubDistanceKm', 'planningDestination', 'placeLabel', "homePlan.dataset.selectionKind = hub ? 'exploration_hub' : 'map_point'", "q: placeLabel, destination: planningDestination"]) {
  if (!homePointRenderSource.includes(marker)) failures.push(`A named homepage exploration hub cannot preserve its direct 360-degree plan context: ${marker}.`);
}
if (!homePointRenderSource.includes("destinationPlanUrl('/ai-planner/'") || !homePointRenderSource.includes("destinationPlanUrl('/travel-map/'") || !homePointRenderSource.includes("kind: 'map_point'") || !homePointRenderSource.includes("activePlanningSelectionQuery('')") || !appJs.includes('activePlanningSelection.latitude.toFixed(4)') || !appJs.includes('activePlanningSelection.longitude.toFixed(4)')) failures.push('Homepage Earth coordinates must reach the AI and full-map handoffs with stable coordinate context.');
const globeSelectionHandlerSource = appJs.slice(appJs.indexOf('function initGlobePointSelection('), appJs.indexOf('\nfunction updateDestinationPlanStages('));
if (!/matches\('\[data-home-globe\]'\)[\s\S]*?renderHomePointSelection\(detail, globeRoot\);[\s\S]*?return;[\s\S]*?closest\('\.theme-map-shell'\)/.test(globeSelectionHandlerSource)) failures.push('Homepage Earth clicks must resolve in the home plan before the full-map renderer can run.');
if (!/function renderUnsupportedGlobeSelection\(detail = \{\}, globeRoot = null\)[\s\S]{0,180}closest\?\.\('\.theme-map-shell'\)[\s\S]{0,80}if \(!mapShell\) return false;/.test(appJs)) failures.push('The generic full-map point renderer must reject Earths outside the map workspace.');
if (!appJs.includes('window.traVelGlobe3D.zoom(button.dataset.mapZoom, { root: globe })') || !appJs.includes('setActiveDestination(destinationId, null, { animate: true, responseConfirmed: false, userSelected: true, globeRoot })')) failures.push('Zoom and destination focus must stay scoped to the Earth instance that originated the action.');
if (!appJs.includes('function resetDestinationPlanTransientState') || (appJs.match(/resetDestinationPlanTransientState\(plan/g) || []).length < 2 || !appJs.includes("plan.removeAttribute('data-destination')")) failures.push('Empty or unsupported Earth selections can retain a previous destination plan or confirmed motion state.');
const fallbackAssignmentIndex = appJs.indexOf('destinationData = { ...fallbackDestinations };');
const fallbackRouteResetIndex = appJs.indexOf("activeRouteId = '';", fallbackAssignmentIndex);
const fallbackSelectionIndex = appJs.indexOf('setActiveDestination(fallbackDestination', fallbackAssignmentIndex);
if (fallbackAssignmentIndex < 0 || fallbackRouteResetIndex < fallbackAssignmentIndex || fallbackSelectionIndex < fallbackRouteResetIndex) failures.push('A request fallback can rebuild plan links before clearing the previously selected route.');
if (!appCss.includes('.map-selection-rail { position: relative') || appCss.includes('.map-selection-rail { position: fixed') || appCss.includes('.map-selection-rail { position: absolute')) failures.push('The globe selection handoff must remain in document flow and outside the Earth paint surface.');
if (!appCss.includes('.map-search-bar:focus-within') || !appCss.includes('.map-status-row { flex-wrap: wrap;')) failures.push('The map search focus state or tablet-safe status wrapping is missing.');
for (const marker of ['.destination-decision-cockpit', '.destination-decision-grid', '.destination-cost-ledger', '.map-progress-checkpoints', '.globe-selection-point', '@keyframes mapSelectionSignal', '@keyframes globePointConfirm', '@keyframes globeRouteConfirm']) {
  if (!appCss.includes(marker)) failures.push(`The 360-degree decision cockpit styling is missing ${marker}.`);
}
if (!appCss.includes('@keyframes destinationPlanReveal') || !appCss.includes('@media (prefers-reduced-motion: reduce)')) failures.push('The 360-degree destination plan needs truthful progressive motion and a reduced-motion path.');
if (!appJs.includes('updateDestinationPlanStages') || !appCss.includes('@keyframes destinationStageConfirm')) failures.push('The 360-degree progress display is not connected to real layer and response state.');
const homePlanOpeningTag = frontPage.match(/<div class="home-plan-360"[^>]*data-home-plan[^>]*>/)?.[0] || '';
const homePlanUpdateSource = appJs.match(/function updateHomeDestinationPlan\(data, animate = true\) \{[\s\S]*?\n\}/)?.[0] || '';
const homeRevealStart = appJs.indexOf('function initHomeDestinationReveal(');
const homeRevealEnd = appJs.indexOf('function initDestinationPlan', homeRevealStart);
const homeRevealSource = homeRevealStart >= 0 && homeRevealEnd > homeRevealStart ? appJs.slice(homeRevealStart, homeRevealEnd) : '';
if (!homePlanOpeningTag) failures.push('The homepage is missing its editable eight-component plan surface.');
if (/aria-busy=/.test(homePlanOpeningTag)) failures.push('The server-rendered homepage plan cannot claim network work before any request begins.');
for (const marker of [
  'data-home-plan-flight',
  'data-home-plan-stay',
  'data-home-plan-guide',
  'data-home-plan-ai',
  'data-home-plan-transfer',
  'data-home-plan-activity',
  'data-home-plan-insurance',
  'data-home-plan-extras',
  'data-home-plan-ledger',
  'data-home-plan-ledger-state',
  'data-home-plan-full-label'
]) {
  if (!frontPage.includes(marker)) failures.push(`The homepage full-trip plan is missing ${marker}.`);
}
if (!frontPage.includes('<ul class="home-plan-modules"') || !frontPage.includes('המחיר, הזמינות והתנאים מאומתים לפני התשלום.')) failures.push('The homepage plan must expose semantic supporting components and a pre-purchase revalidation boundary.');
if (!frontPage.includes('data-lucide="compass"') || frontPage.includes('data-lucide="dices"')) failures.push('The one-click Surprise journey must use travel discovery imagery without dice or casino cues.');
if (frontPage.includes('בחירה עונתית') || frontPage.includes('מצב שמונת רכיבי התכנון')) failures.push('The homepage must use traveler-facing benefit copy rather than internal merchandising or component-state language.');
for (const phrase of ['מקור מחובר', 'ספק מחובר', 'בעלות ספק', 'בקשת סיוע', 'בקשת הסיוע', 'זהות מסחרית', 'מצב רכיבים', 'הצעה סופית', 'אישור בהצעה האישית', 'המחיר הסופי יינתן לאחר בדיקה אישית', 'מחיר סופי לאחר בדיקה מחדש', 'המחיר הסופי טרם אושר']) {
  if (frontPage.includes(phrase) || appJs.includes(phrase)) failures.push(`Traveler-facing copy exposes internal workflow terminology: ${phrase}.`);
}
for (const marker of [
  "const ledgerState = document.querySelector('[data-home-plan-ledger-state]')",
  "const fullLabel = document.querySelector('[data-home-plan-full-label]')",
  "'[data-home-plan-transfer]': destinationPlanUrl('/packages/'",
  "'[data-home-plan-activity]': destinationPlanUrl(data.url || '/destinations/'",
  "'[data-home-plan-insurance]': destinationPlanUrl('/travel-insurance/'",
  "'[data-home-plan-extras]': destinationPlanUrl('/ai-planner/'",
  "scope: 'dining,connectivity,equipment'"
]) {
  if (!homePlanUpdateSource.includes(marker)) failures.push(`Homepage destination changes do not update the full-trip contract: ${marker}.`);
}
if (!homePlanUpdateSource || homePlanUpdateSource.includes("setAttribute('aria-busy'")) failures.push('A synchronous homepage plan update must not expose network-style aria-busy state.');
if (!homeRevealSource || homeRevealSource.includes("setAttribute('aria-busy'")) failures.push('The local Earth reveal must not expose network-style aria-busy state.');
for (const marker of ['{ autoEligible = true } = {}', 'autoEligible: autoEligible === true', 'baselinePlanState', 'captureBaselinePlanState', 'if (baselinePlanState)', 'aiLink.setAttribute(\'href\', baselinePlanState.aiHref)']) {
  if (!homeRevealSource.includes(marker)) failures.push(`The homepage reveal intent or cancellation contract is missing ${marker}.`);
}
if (!homeRevealSource.includes('פותחים רעיון חדש לחופשה שתוכלו לערוך.') || homeRevealSource.includes('מחפשים כיוון שנותן יותר לחופשה.')) failures.push('Deterministic Surprise motion must offer a neutral editable idea without claiming superior value.');
if (!homeRevealSource.includes("...homePlanningLinkContext('ai')") || !homeRevealSource.includes('intent: activePlanIntent')) failures.push('The completed Surprise handoff must retain current trip context and planning intent.');
if (!appJs.includes("homePlan.setAttribute('aria-busy', String(planWork && mode === 'loading'))")) failures.push('A real selected-destination hydration must retain its truthful busy state.');
for (const marker of [
  '.home-plan-360.is-updating .home-plan-modules a',
  '.home-plan-360.is-updating .home-plan-ledger',
  '@keyframes homePlanConfirm'
]) {
  if (!appCss.includes(marker)) failures.push(`The homepage eight-component plan styling is missing ${marker}.`);
}
const homePlanModulesRule = appCss.match(/\.home-plan-modules\s*\{([^}]*)\}/)?.[1] || '';
const homePlanLedgerRule = appCss.match(/\.home-plan-ledger\s*\{([^}]*)\}/)?.[1] || '';
if (!/display:\s*grid;/.test(homePlanModulesRule)) failures.push('The homepage supporting components must use a stable grid layout.');
if (!/\.home-plan-modules a\s*\{[^}]*min-height:\s*(?:4[4-9]|[5-9][0-9]|[1-9][0-9]{2,})px;/.test(appCss)) failures.push('Every homepage supporting component must retain at least a 44px touch target.');
if (!/\.home-plan-ledger summary\s*\{[^}]*min-height:\s*(?:4[4-9]|[5-9][0-9]|[1-9][0-9]{2,})px;/.test(appCss)) failures.push('The homepage ledger disclosure must retain at least a 44px touch target.');
for (const [selector, rule] of [['.home-plan-modules', homePlanModulesRule], ['.home-plan-ledger', homePlanLedgerRule]]) {
  if (!rule || /position:\s*(?:absolute|fixed|sticky)/.test(rule)) failures.push(`${selector} must remain in document flow and cannot cover the Earth or result card.`);
}
if (!/@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.home-plan-360\.is-updating \.home-plan-modules a[\s\S]*?\.home-plan-360\.is-updating \.home-plan-ledger/.test(appCss)) failures.push('Homepage component and ledger confirmation motion is missing its reduced-motion path.');
if (!appJs.includes('runConfirmedPlanAnimation') || !appJs.includes("plan.classList.remove('is-updating')")) failures.push('Confirmed progress animation can leak into a later loading request.');
if (!appJs.includes('traVelMotionGeneration') || !appJs.includes('responseConfirmed: false')) failures.push('Progress motion is not generation-safe or still treats pre-request selection as a confirmed response.');
if (!appJs.includes("settledDiscoveryResponseState") || !appJs.includes("responseState: 'fallback'") || !appJs.includes('אפשרויות מהבדיקה האחרונה') || !appJs.includes('מוכן לחיפוש מחדש')) failures.push('Settled stale and fallback discovery responses can remain presented as actively updating.');
if (!appJs.includes('מעדכנים את השוואת המסלולים ליעד ולבחירות החדשות') || !/function setRouteListBusy[\s\S]{0,360}list\.replaceChildren\(\)/.test(appJs)) failures.push('A destination refresh can leave the previous destination route cards visible under the new title.');
if (!appJs.includes('discoveryRequestPending') || !appJs.includes('!discoveryRequestPending && discoverySnapshotIsCurrent()') || !/mode === 'loading'[\s\S]{0,500}save\.disabled = true/.test(appJs) || !/data-map-result[\s\S]{0,140}button\.disabled = true/.test(appJs)) failures.push('Map and plan saves can retain or persist a previous live snapshot while discovery is loading.');
if (!appJs.includes('const current = !discoveryRequestPending && discoverySnapshotIsCurrent()') || !/updatePins\(\)[\s\S]{0,220}mode === 'loading' && planWork[\s\S]{0,520}responseState: 'pending'/.test(appJs) || !appJs.includes('selectedPlanForResponse')) failures.push('A selected-destination request can leave previous-result prices or supplier modules visible as the incoming result.');
if (!appJs.includes('function setDiscoveryStatus(mode, message, { planWork = true } = {})') || !appJs.includes("setDiscoveryStatus('loading', 'מעדכנים את היעדים הזמינים על המפה. בדיקת מחיר ותוכנית תתחיל רק לאחר בחירה.', { planWork: false })") || !appJs.includes("plan.dataset.requestState = planWork ? mode : 'waiting-for-destination'")) failures.push('Open-ended map loading must refresh only the destination catalog without claiming selected-plan or supplier progress.');
if (!appJs.includes("['complete_live', 'partial_live', 'stale_complete', 'stale_partial']")) failures.push('The client can discard truthful complete or stale-complete cost-ledger states.');
if ((appJs.match(/\['stale_complete', 'stale_partial'\]\.includes\(ledger\.state\)/g) || []).length < 2) failures.push('A stale complete cost ledger can lose its previous-snapshot warning.');
if (!/data-plan-ledger-state[\s\S]{0,120}'12 /.test(mapPage)) failures.push('The initial decision cockpit does not match the canonical twelve-row cost ledger.');
if (!appJs.includes('discoveryBudgetCoverage') || !appJs.includes('budgetCoverageLabel()') || !appJs.includes('budget_filter_active') || !appCss.includes('.budget-coverage-note')) failures.push('Partial or absent live-price budget coverage is not disclosed beside the budget control and result status.');
if (!appJs.includes('activeRouteSelectionLocked') || !appJs.includes("button.setAttribute('aria-pressed'") || !appJs.includes('payload.recommended?.id')) failures.push('Route recommendations and explicit user route choices are not statefully distinguished.');
if (appJs.includes('Math.max(...discoveryRoutes') || appJs.includes('savings: saved > 0')) failures.push('Client code can still invent verified savings from non-comparable route totals.');
if (!appJs.includes("{ traVelMap: true, focus: activeDestination || ''") || !appJs.includes('event.state?.focus') || !appJs.includes('{ focus: historyFocus }')) failures.push('Map Back and Forward history cannot restore an unlocked focused destination.');
if (!appJs.includes('}, 12000)') || !appJs.includes('timedOut')) failures.push('A stalled discovery request can leave animated progress and controls busy indefinitely.');
if (!appCss.includes('.home-globe-stack .globe { position: relative; inset: auto;') || !appCss.includes('touch-action: pan-y pinch-zoom; cursor: default;') || !appCss.includes('.home-globe-stack .globe-tools { position: static;')) failures.push('The homepage globe can trap mobile scrolling or place controls over the Earth.');
if (!appCss.includes('.home-globe-stack .map-result[data-selection-kind="map_point"] { grid-template-columns: minmax(0,1fr); }')) failures.push('A coordinate-only homepage result must close the hidden-image grid column instead of leaving an empty panel.');
for (const marker of ['data-home-globe', 'data-home-surprise', 'data-home-reveal data-state="ready"', 'data-home-reveal-cancel', 'data-home-plan-ai-label', 'data-campaign-kind', 'data-home-reveal-context']) {
  if (!frontPage.includes(marker)) failures.push(`The homepage destination reveal is missing ${marker}.`);
}
for (const marker of ['function initHomeDestinationReveal', 'const campaignKind', 'run(campaignKind)', "run('surprise')", "event.key === 'Escape'", "mode: 'surprise'", 'globeRotations:', 'navigator.connection?.saveData']) {
  if (!appJs.includes(marker)) failures.push(`The homepage destination reveal controller is missing ${marker}.`);
}
if (!appCss.includes('.home-reveal-feedback { width: min(550px,92%);') || !appCss.includes('.home-reveal-feedback[data-state="spinning"]') || !appCss.includes('.home-reveal-feedback[data-state] > span > i')) failures.push('The in-flow homepage reveal feedback or its reduced-motion path is missing.');

for (const marker of ['getContext(\'webgl\'', 'pointerdown', 'IntersectionObserver', 'ResizeObserver', 'prefers-reduced-motion', 'focusDestination', 'boxesOverlap', 'greatCircleDistanceKm', 'globePointFromScreen', 'travelglobe:select', 'supportedRadiusKm', 'selectedPoint', 'preservePoint', 'data-globe-selection-point', 'pointer.moved', "event.key === 'Enter'", 'pulseRoute', 'options.rotations', 'options.duration']) {
  if (!globeJs.includes(marker)) failures.push(`The production 3D globe is missing ${marker}.`);
}
if ((globeJs.match(/state\.visible = document\.visibilityState !== 'hidden'/g) || []).length < 3) failures.push('Globe drag, keyboard and zoom input must wake event-driven rendering.');
if (!globeJs.includes('mobile && !active ? 44') || !globeJs.includes('const markerHeight = mobile ? 44 : 34')) failures.push('Globe collision geometry does not match the 44px mobile marker targets.');
if (/addEventListener\(\s*['"]focus['"][\s\S]{0,160}focusDestination/.test(globeJs)) failures.push('Globe markers must not move on focus before their pointer click can synchronize the supporting destination and route panels.');
if (/https?:\/\//.test(globeJs)) failures.push('The 3D globe must not load unapproved third-party runtime code or textures.');
if (!/pointerup[\s\S]{0,260}!pointer\.moved[\s\S]{0,220}selectScreenPoint/.test(globeJs)) failures.push('Globe taps are not separated from drags before selecting an Earth point.');
if (!globeJs.includes('zPitch > 1 / state.distance') || !globeJs.includes("addEventListener('lostpointercapture'")) failures.push('Globe visibility culling or pointer cleanup is not hardened for the perspective camera.');
if (!globeJs.includes("options.pulse === true") || !appJs.includes('pulseRoute: responseSupportsConfirmedMotion') || !appJs.includes('globeAnimate: false')) failures.push('Stale or fallback discovery outcomes can still receive confirmed route motion.');
if (!globeJs.includes("root.classList.contains('globe-3d-unavailable')") || !globeJs.includes('return handled;') || !appJs.includes('const handled = window.traVelGlobe3D.zoom')) failures.push('The static Earth zoom fallback is unreachable when WebGL is unavailable.');
const pointerDownSource = globeJs.match(/addEventListener\('pointerdown'[\s\S]*?addEventListener\('pointermove'/)?.[0] || '';
if (!globeJs.includes("mode: 'pending'") || !globeJs.includes('if (absoluteY >= absoluteX)') || !globeJs.includes("state.pointer.mode = 'scroll'") || !globeJs.includes('if (absoluteX < absoluteY * 1.25) return;') || !globeJs.includes("state.pointer.mode !== 'drag'")) failures.push('The mobile globe is missing clear horizontal-intent locking and vertical-scroll escape.');
if (pointerDownSource.includes('setPointerCapture')) failures.push('The globe captures touch before the user has committed to a horizontal drag.');
if (pointerDownSource.includes("event.button !== 0 || event.target.closest('.price-pin')")) failures.push('A drag that starts on a destination pin is still blocked.');
if (!globeJs.includes("startedOnPin: Boolean(event.target.closest('.price-pin'))") || !globeJs.includes("distance < 8") || !globeJs.includes('pointer.moved && pointer.startedOnPin') || !globeJs.includes('state.suppressPinActivationUntil = performance.now() + 500') || !globeJs.includes('event.stopImmediatePropagation()')) failures.push('Pin gestures must preserve taps, begin dragging at eight pixels, and suppress the synthetic activation after a pin-origin drag.');
if (!/function activateStaticFallback[\s\S]*?classList\.remove\('is-webgl-ready'[\s\S]*?classList\.add\('globe-3d-unavailable'/.test(globeJs) || !globeJs.includes('WebGL render error') || !globeJs.includes("addEventListener('webglcontextrestored'")) failures.push('Post-load WebGL failures must clear the ready state and remain on the deterministic static Earth fallback after context restoration.');
if (!appCss.includes('.theme-map-shell .world-canvas .globe { width: min(640px,100%);') || !appCss.includes('@media (max-width: 1000px)') || !appCss.includes('.theme-map-shell .map-view-layout { grid-template-columns: 1fr;') || !appCss.includes('.theme-map-shell .map-layer-buttons { order: 1;') || !appCss.includes('.theme-map-shell .world-canvas { order: 2;')) failures.push('Tablet map sizing or visual order can clip the Earth or diverge from DOM focus order.');
if (!appCss.includes('.map-selection-rail.is-updating .map-selection-signal,.map-selection-rail.is-updating .map-selection-copy') || !appCss.includes('.home-globe-stack .globe-selection-point::after,.theme-map-shell .globe-selection-point::after') || !appCss.includes('.theme-map-shell .globe-webgl.is-routing .globe-route-layer path { animation: none !important; }')) failures.push('New globe progress motion is missing its reduced-motion path.');
const globeTextureSize = statSync(join(themeRoot, 'assets/images/earth-blue-marble-2048.jpg')).size;
if (globeTextureSize > 500000) failures.push(`The mobile globe texture is too large (${globeTextureSize} bytes).`);

// Theme 1.24.0 living globe: guarded idle spin, auto-fly tour, double-click
// dive, the scroll law, and the marker performance budget.
if (/addEventListener\(\s*['"](?:wheel|mousewheel|scroll|touchmove)['"]/.test(globeJs)) failures.push('The globe must never bind wheel, scroll, or touchmove listeners; page scrolling stays with the browser.');
if (!appCss.includes('.theme-map-shell .globe-webgl { isolation: isolate; touch-action: pan-y pinch-zoom;')) failures.push('The map globe must keep touch-action: pan-y so vertical swipes scroll the page.');
for (const marker of [
  'const IDLE_SPIN_YAW_PER_MS = 0.00006;',
  'const IDLE_SPIN_RESUME_DELAY_MS = 4000;',
  'const IDLE_MARKER_SYNC_INTERVAL_MS = 33;',
  'const TOUR_START_DELAY_MS = 3000;',
  'const TOUR_DEFAULT_DWELL_MS = 2600;',
  'const TOUR_DEFAULT_HOP_DURATION_MS = 1500;',
  'const DOUBLE_TAP_WINDOW_MS = 300;',
  'const DOUBLE_TAP_RADIUS_PX = 24;',
  'const DOUBLE_CLICK_DIVE_DISTANCE = 0.6;',
  'const DOUBLE_CLICK_DIVE_DURATION_MS = 700;',
  'const MARKER_COLLISION_BUDGET = 60;',
  'const NEAR_LOD_HUB_LABEL_BUDGET = 12;'
]) {
  if (!globeJs.includes(marker)) failures.push(`The living-globe motion contract is missing ${marker}`);
}
const idleSpinEligibleSource = globeJs.match(/function idleSpinEligible\(now\) \{[\s\S]*?\n    \}/)?.[0] || '';
for (const guard of ["document.visibilityState === 'visible'", '!state.pointer', '!state.animation', '!state.tour.active', 'state.visible', 'now >= state.idleSpin.resumeAt', '!shouldReduceMotion()']) {
  if (!idleSpinEligibleSource.includes(guard)) failures.push(`The idle spin is missing its ${guard} guard.`);
}
if (!globeJs.includes('if (state.animation || idleSpinning) requestRender();')) failures.push('The render loop must stay conditional: frames self-schedule only while animating or idle-spinning.');
if (!globeJs.includes('state.yaw = normalizeAngle(state.yaw + IDLE_SPIN_YAW_PER_MS * step);')) failures.push('The idle spin must advance yaw through the frame-time-scaled constant.');
if (!/root\.addEventListener\('pointerdown', event => \{\s*noteDirectInteraction\(\);/.test(globeJs)) failures.push('A pointer touching the globe must pause idle motion and cancel the tour permanently.');
if (!/function noteDirectInteraction\(\) \{[\s\S]{0,240}stopTour\(true\);/.test(globeJs)) failures.push('Direct interaction must cancel the auto-fly tour for the rest of the session.');
const tourHopSource = globeJs.match(/function tourHop\(\) \{[\s\S]*?\n    \}/)?.[0] || '';
if (!tourHopSource.includes('shouldReduceMotion()') || !tourHopSource.includes("document.visibilityState !== 'visible'") || !tourHopSource.includes('state.pointer || state.animation')) failures.push('Tour hops must respect reduced motion, visibility, and active interaction before moving the camera.');
if (!tourHopSource.includes('pulse: true') || !tourHopSource.includes('rotations: 0') || !tourHopSource.includes('announce: false') || !tourHopSource.includes('focusDestination(id, {')) failures.push('Tour hops must reuse the focusDestination selection pipeline with a pulsed, non-announced, rotation-free arrival.');
if (!/root\.matches\('\[data-discovery-globe\]'\)[\s\S]{0,220}startTour\(\);[\s\S]{0,80}TOUR_START_DELAY_MS\)/.test(globeJs)) failures.push('The auto-fly tour must arm only on discovery globes after the load-idle delay.');
if (!/root\.addEventListener\('dblclick'[\s\S]{0,700}diveToScreenPoint\(event\.clientX, event\.clientY\)\) event\.preventDefault\(\);/.test(globeJs)) failures.push('Double-click must dive through the shared ray-cast helper and consume the event.');
if (!/function diveToScreenPoint\([\s\S]{0,700}globePointFromScreen\([\s\S]{0,700}DOUBLE_CLICK_DIVE_DURATION_MS/.test(globeJs)) failures.push('The dive must ray-cast the struck coordinate and animate with the bounded dive duration.');
if (!globeJs.includes('tap.at - previousTap.at <= DOUBLE_TAP_WINDOW_MS') || !globeJs.includes('<= DOUBLE_TAP_RADIUS_PX')) failures.push('Touch double-tap detection must use the bounded 300ms/24px window.');
if (!globeJs.includes('candidateIndex >= MARKER_COLLISION_BUDGET') || !globeJs.includes('timestamp - state.lastMarkerSyncAt >= IDLE_MARKER_SYNC_INTERVAL_MS')) failures.push('The marker pass is missing its collision budget or the ~30fps idle declutter throttle.');
if (!globeJs.includes("root.dataset.globeLod = lodLevel;") || !globeJs.includes("candidate.marker.dataset.globeLabel = labelState;")) failures.push('The marker level of detail must publish its state for the bounded CSS label path.');

const assetSource = readFileSync(join(themeRoot, 'inc/assets.php'), 'utf8');
if (!assetSource.includes('is_front_page()') || !assetSource.includes("is_page_template( 'page-map.php' )") || !assetSource.includes("is_page_template( 'page-destination.php' )") || !assetSource.includes('tra-vel-v2-globe-3d')) failures.push('The WebGL globe must load on the homepage, map, and destination templates.');

const seoSource = readFileSync(join(themeRoot, 'inc/seo.php'), 'utf8');
if (!seoSource.includes('BreadcrumbList')) failures.push('Destination guides are missing breadcrumb structured data.');
if (!seoSource.includes('tra_vel_v2_guide_breadcrumb_items') || !seoSource.includes('tra_vel_v2_is_public_guide_path') || !seoSource.includes('supporting_guides')) failures.push('Nested guide breadcrumbs or directory schema support is missing.');
if (!seoSource.includes('lastReviewed')) failures.push('Destination guide schema is missing source-review freshness.');
if (!seoSource.includes('CollectionPage') || !seoSource.includes('ItemList')) failures.push('Editorial directories are missing CollectionPage and ItemList schema.');
if (seoSource.includes("'FAQPage'") || seoSource.includes('"FAQPage"')) failures.push('Travel guides must not chase unavailable FAQ rich results.');
if (!seoSource.includes("$robots['noindex']")) failures.push('Faceted and personal routes are missing an explicit noindex policy.');
for (const facet of ['focus', 'layer', 'intent', 'trip', 'max_stops', 'max_duration', 'allow_overnight', 'trip_destination', 'destination_mode', 'selection_destination', 'selection_id', 'selection_kind', 'scope', 'latitude', 'longitude', 'product', 'route', 'dates', 'departure', 'check_in', 'check_out', 'date', 'travelers', 'party', 'flexible', 'flexibility', 'hotel_area', 'transfers', 'kosher', 'accessibility', 'vibe']) {
  if (!seoSource.includes(`'${facet}'`)) failures.push(`The SEO noindex policy is missing ${facet}.`);
}
if (!seoSource.includes("add_filter( 'document_title_parts', 'tra_vel_v2_document_title_parts' )")) failures.push('Public document titles are not protected from the legacy Europe-only site name.');
if (!seoSource.includes("add_filter( 'wpseo_title', 'tra_vel_v2_public_seo_title' )") || !seoSource.includes("add_filter( 'wpseo_schema_website', 'tra_vel_v2_enrich_yoast_website_schema' )")) failures.push('Yoast can still expose the legacy Europe-only site name.');

const destinationPage = readFileSync(join(themeRoot, 'page-destination.php'), 'utf8');
if (!destinationPage.includes('tra_vel_v2_render_guide_evidence')) failures.push('Destination guides do not expose their evidence and freshness status.');
if (!destinationPage.includes('tra_vel_v2_guide_breadcrumb_items')) failures.push('Destination guides do not render the shared ancestor breadcrumb trail.');
if (/[$₪]\s?\d/.test(destinationPage)) failures.push('Destination templates must not hard-code demo prices that can be mistaken for live inventory.');
if (destinationPage.includes('data-map-result')) failures.push('Destination guide cards must not be overwritten by global demo discovery results.');
if (!destinationPage.includes('data-guide-map-card')) failures.push('Destination guides are missing their isolated map decision card.');
for (const shellId of ['main-content', 'map', 'decision-when', 'decision-areas', 'guide']) {
  if (!destinationPage.includes(`id="${shellId}"`)) failures.push(`Destination guide shell is missing unique anchor #${shellId}.`);
}
for (const anchorBinding of ['$guide_intro_anchor', '$guide_flights_anchor', '$guide_costs_anchor', '$guide_insurance_anchor', '$guide_faq_anchor']) {
  if (!destinationPage.includes(anchorBinding)) failures.push(`Destination guide navigation is missing composed-content binding ${anchorBinding}.`);
}
if (!destinationPage.includes("'prague'   => 'images/city-prague.webp'") || !destinationPage.includes("'prague'   => array( 'latitude' => '50.0755', 'longitude' => '14.4378' )")) failures.push('Prague destination guides are missing the canonical fallback image or Earth coordinates.');
if (!destinationPage.includes("'vienna'   => 'images/city-vienna.webp'") || !destinationPage.includes("'vienna'   => array( 'latitude' => '48.2082', 'longitude' => '16.3738' )")) failures.push('Vienna destination guides are missing the canonical fallback image or Earth coordinates.');
if (!destinationPage.includes('tra_vel_v2_get_guide_publication_contract') || !destinationPage.includes('$guide_is_ready') || !destinationPage.includes("$guide_profile['checked']") || destinationPage.includes("get_the_modified_date( 'F Y' )")) failures.push('The destination hero badge is not gated by publication readiness and the source review date.');
if (!/<\?php if \( \$guide_is_ready \) : \?>[\s\S]{0,240}data-lucide="badge-check"[\s\S]{0,420}<\?php else : \?>[\s\S]{0,240}data-lucide="map-pinned"/.test(destinationPage)) failures.push('Incomplete destination guides can still display the publish-ready verification badge.');
for (const marker of ['$guide_registry_matches', "$guide_profile['publication_status']", 'tra_vel_v2_get_public_seo_opportunity_links', 'if ( $guide_cluster_links )', 'class="seo-cluster-link-card"']) {
  if (!destinationPage.includes(marker)) failures.push(`Destination guides are missing publication-gated cluster navigation marker ${marker}.`);
}
if (!destinationPage.includes("$globe_points[ $map_state ] ?? null") || !destinationPage.includes('<?php if ( $globe_point ) : ?>') || destinationPage.includes("?? $globe_points['bangkok']")) failures.push('Unknown destination guides can still inherit Bangkok coordinates instead of a neutral globe.');
for (const marker of ['data-globe-3d', 'data-globe-canvas', 'data-globe-route', 'destination-globe-toolbar']) {
  if (!destinationPage.includes(marker)) failures.push(`Destination guides are missing interactive globe marker ${marker}.`);
}
if (!appCss.includes('.compact-map .map-result { position: relative;')) failures.push('Destination guide information must remain below the globe instead of covering it.');
if (/מפת העריכה|במחקר|בדיקת מערכת|מדריך דגל/u.test(destinationPage)) failures.push('Destination templates expose internal project language.');

const publicDirectoryPage = readFileSync(join(themeRoot, 'page-directory.php'), 'utf8');
if (/מפת העריכה|במחקר|בדיקת מערכת|מדריך דגל|מחירים מומצאים/u.test(publicDirectoryPage)) failures.push('Destination directories expose internal project language.');
if (/[—–]/u.test(destinationPage) || /[—–]/u.test(publicDirectoryPage)) failures.push('Public destination templates must not use em dash or en dash punctuation.');
const commercialExperiencePage = readFileSync(join(themeRoot, 'page-experience.php'), 'utf8');
for (const phrase of ['מקור מחובר', 'ספק מחובר', 'בעלות ספק', 'בקשת סיוע', 'בקשת הסיוע', 'זהות מסחרית', 'מצב רכיבים', 'הצעה סופית']) {
  if (commercialExperiencePage.includes(phrase) || publicDirectoryPage.includes(phrase)) failures.push(`Commercial page copy exposes internal or contradictory terminology: ${phrase}.`);
}
const hotelContextFlagIndex = commercialExperiencePage.indexOf("$hotel_initial_search     = 'BUD' === $stay_destination_code;");
const hotelContextIfIndex = commercialExperiencePage.indexOf('<?php if ( $hotel_initial_search ) : ?>', hotelContextFlagIndex);
const hotelAreaMapIndex = commercialExperiencePage.indexOf('data-hotel-area-map', hotelContextIfIndex);
const hotelContextElseIndex = commercialExperiencePage.indexOf('<?php else : ?>', hotelAreaMapIndex);
const hotelContextPendingIndex = commercialExperiencePage.indexOf('data-hotel-area-context-pending', hotelContextElseIndex);
const hotelContextEndIndex = commercialExperiencePage.indexOf('<?php endif; ?>', hotelContextPendingIndex);
const supportedHotelMapSource = hotelContextIfIndex >= 0 && hotelContextElseIndex > hotelContextIfIndex ? commercialExperiencePage.slice(hotelContextIfIndex, hotelContextElseIndex) : '';
if (hotelContextFlagIndex < 0 || hotelContextIfIndex < hotelContextFlagIndex || hotelAreaMapIndex < hotelContextIfIndex || hotelContextElseIndex < hotelAreaMapIndex || hotelContextPendingIndex < hotelContextElseIndex || hotelContextEndIndex < hotelContextPendingIndex || !supportedHotelMapSource.includes('BUDA') || !supportedHotelMapSource.includes('PEST')) failures.push('The Budapest hotel-area map is not fail-closed behind a matching hotel context with an explicit unsupported-destination state.');
if (!commercialExperiencePage.includes('נקודת המפה שנבחרה')) failures.push('Supported destination handoffs drop their exact Earth point from the model prompt.');
if (/הגרסה הבאה|המבנה והמסע מוכנים|יחוברו ספקים/u.test(commercialExperiencePage)) failures.push('Commercial experience pages expose internal roadmap language.');
if (/החיבור בבנייה/u.test(commercialExperiencePage) || /ספק חלקי/u.test(appJs)) failures.push('Consumer comparison status exposes internal build or supplier language.');
if (!commercialExperiencePage.includes('commercial-assurance')) failures.push('Commercial experience pages are missing the assisted-sales trust boundary.');
if (!commercialExperiencePage.includes("'easy'      => 'comfort'") || !commercialExperiencePage.includes("'adventure' => 'adventure'") || !commercialExperiencePage.includes('checked( $flight_direct )') || !commercialExperiencePage.includes('$package_budget_total')) failures.push('Package planning does not preserve map intent, directness, and budget context.');
if (!commercialExperiencePage.includes('$allow_overnight') || !appJs.includes('allow_overnight: discoveryQuery.allow_overnight ? 1')) failures.push('The 360-degree AI and package handoffs drop the overnight preference.');
for (const marker of ['data-agent-journey', 'data-agent-journey-meter', 'data-agent-journey-next', 'data-agent-journey-scopes']) {
  if (!commercialExperiencePage.includes(marker)) failures.push(`The Agent workbench is missing semantic journey marker ${marker}.`);
}
if ((commercialExperiencePage.match(/data-agent-journey-step=/g) || []).length !== 7) failures.push('The Agent journey must expose exactly seven truthful orchestration stages.');
for (const step of ['supplier_search', 'proposal', 'approval', 'execution']) {
  if (!new RegExp(`data-agent-journey-step="${step}" hidden`).test(commercialExperiencePage)) failures.push(`Protected Agent stage ${step} must be absent from the initial public journey.`);
}
if (!appJs.includes('agentJourneyStepIsVisible') || !appJs.includes('provider_connected === true')) failures.push('Protected Agent stages are not revealed only from matching capability or phase evidence.');
if ((commercialExperiencePage.match(/data-agent-scope=/g) || []).length !== 8) failures.push('The Agent journey must expose all eight 360-degree planning domains.');
for (const marker of ['.agent-journey', '.agent-journey-steps .is-waiting', '.agent-journey-steps .is-failed', '@keyframes agent-journey-confirm']) {
  if (!appCss.includes(marker)) failures.push(`The Agent journey motion system is missing ${marker}.`);
}
if (!appCss.includes('.agent-journey.is-advancing .agent-journey-steps .is-complete') || !appCss.includes('.agent-journey[data-state="connecting"] .agent-journey-steps .is-current')) failures.push('Agent journey motion does not distinguish real advancement from current work.');
if (!appCss.includes('.agent-scope-board li small { color: inherit; font-size: 10.5px') || !appCss.includes('@media (max-width: 420px)') || !appCss.includes('.agent-journey-steps,.agent-scope-board ul { grid-template-columns: 1fr; }')) failures.push('The new Agent progress interface is not readable on narrow mobile screens.');
for (const marker of ['data-agent-revision-composer', 'data-agent-revision-form', 'data-agent-revision-message', 'data-agent-revision-status']) {
  if (!commercialExperiencePage.includes(marker)) failures.push(`The AI planner same-run clarification UI is missing ${marker}.`);
}
if (!appJs.includes('async function reviseAgentRun(') || !appJs.includes('/messages`, {') || !appJs.includes("form.setAttribute('aria-busy', 'true')")) failures.push('The AI planner does not submit same-run revisions with an accessible real activity state.');
if (!appJs.includes('התוכנית הקודמת נשארה ללא שינוי') || !appJs.includes('renderAgentRun(root, currentRun)')) failures.push('A failed revision must retain and refresh the last confirmed plan.');
for (const marker of ['pollController: null', 'pollGeneration: 0', "pollRunToken: ''", 'quoteCasePollController: null', 'quoteCasePollGeneration: 0', "quoteCasePollCaseToken: ''", 'invalidateAgentPoll()', 'invalidateQuoteCasePoll()', 'isCurrentAgentPoll(runId, generation, controller)', 'isCurrentQuoteCasePoll(caseId, generation, controller)']) {
  if (!appJs.includes(marker)) failures.push(`Agent polling lifecycle is missing ${marker}.`);
}
if (!/currentCase\?\.case_id === caseData\.case_id[\s\S]{0,220}incomingVersion < currentVersion\) return false;/.test(appJs)) failures.push('A stale lower quote-case version can regress visible status or actions.');
if (!/const reconnectRun = Boolean[\s\S]{0,420}invalidateAgentPoll\(\);[\s\S]{0,120}invalidateQuoteCasePoll\(\);[\s\S]{0,120}visibilityState !== 'visible'/.test(appJs)) failures.push('Visibility reconnect does not invalidate old Agent and quote-case requests before rescheduling.');
if (!appCss.includes('@keyframes agent-progress-sweep') || !appCss.includes('@keyframes agent-positive-arrival') || !appCss.includes('.agent-revision-form[data-state="loading"]')) failures.push('Same-run revision progress and confirmed success lack truthful visual motion.');
if (!appJs.includes('latestSequenceByPhase') || !appCss.includes('.agent-event.is-running.is-resolved::before')) failures.push('Completed agent phases can continue showing a false running animation.');
const agentJourneyComputation = appJs.match(/function computeAgentJourney\(run\) \{[\s\S]*?\n\}/)?.[0] || '';
if (!agentJourneyComputation.includes('latestAgentEventForPhase(phase)')
  || /status[^\n;]*(?:searching|proposal_ready|approval_required|completed)[\s\S]{0,120}steps\.(?:supplier_search|proposal|approval|execution)\s*=/.test(agentJourneyComputation)) {
  failures.push('Protected supplier search, proposal, approval, or execution progress can advance from a broad run status without matching phase evidence.');
}
for (const phrase of ['השרת ', 'הריצה ', 'אירועי הריצה', 'גרסת תוכנית', 'חיפוש חי', 'בדיקה חיה']) {
  if (appJs.includes(phrase)) failures.push(`Traveler-facing runtime copy exposes internal or unsupported language: ${phrase.trim()}.`);
}

const directoryPage = readFileSync(join(themeRoot, 'page-directory.php'), 'utf8');
for (const marker of ['data-directory-root', 'data-directory-filter', 'data-directory-grid', 'directory-map-pin', 'editorial-directory.json']) {
  if (!directoryPage.includes(marker)) failures.push(`Destination directory is missing ${marker}.`);
}
for (const marker of ['supporting_guides', 'array_merge( $earth_destinations, $supporting_guides )', '$orbit_destinations = $earth_destinations;', 'foreach ( $orbit_destinations as $destination )']) {
  if (!directoryPage.includes(marker)) failures.push(`Nested guide directory foundation is missing ${marker}.`);
}

const guideRuntimeSource = readFileSync(join(themeRoot, 'inc/guides.php'), 'utf8');
if (!guideRuntimeSource.includes("'_tra_vel_publication_status'") || !guideRuntimeSource.includes('tra_vel_v2_sanitize_publication_status')) failures.push('Guide publication status is not registered and exposed to the runtime contract.');
if (!/'publication_status'\s*=>\s*'publish-ready'\s*===\s*\( \$profile\['publication_status'\]/.test(guideRuntimeSource)) failures.push('Guide indexability and Article schema are not fail-closed behind explicit publish-ready status.');

function supportingGuideContractErrors(guide, earthByPath, discoveryIds) {
  const errors = [];
  const requiredStrings = ['id', 'city', 'country', 'region', 'region_label', 'experience', 'experience_label', 'duration', 'best_for', 'decision', 'map_state', 'image', 'guide_status', 'guide_path', 'parent_path'];
  for (const key of requiredStrings) {
    if (typeof guide?.[key] !== 'string' || !guide[key].trim()) errors.push(`${key} is required`);
  }
  if (!/^[a-z0-9-]+$/.test(guide?.id || '')) errors.push('id must be a lowercase slug');
  if (guide?.guide_status !== 'published') errors.push('guide_status must be published');
  if (!/^\/destinations\/[a-z0-9-]+\/[a-z0-9-]+\/$/.test(guide?.guide_path || '')) errors.push('guide_path must be exactly one segment below a destination hub');
  if (!/^\/destinations\/[a-z0-9-]+\/$/.test(guide?.parent_path || '')) errors.push('parent_path must be a destination-hub path');
  const immediateParentPath = String(guide?.guide_path || '').replace(/[^/]+\/$/, '');
  if (guide?.parent_path !== immediateParentPath) errors.push('parent_path must be the immediate guide ancestor');
  const parent = earthByPath.get(guide?.parent_path);
  if (!parent || parent.guide_status !== 'published') errors.push('parent_path must identify a published Earth destination hub');
  if (!discoveryIds.has(guide?.map_state)) errors.push('map_state must reuse a supported discovery state');
  if (!Number.isInteger(guide?.word_count) || guide.word_count < 5000) errors.push('word_count must be at least 5000');
  if (!Number.isInteger(guide?.source_count) || guide.source_count < 10) errors.push('source_count must be at least 10');
  if (typeof guide?.image === 'string' && guide.image && !existsSync(join(themeRoot, 'assets', 'images', guide.image))) errors.push('image must exist in the theme image directory');
  return errors;
}
if (/[$₪]\s?\d/.test(directoryPage)) failures.push('The destination directory must not hard-code commercial prices.');

try {
  const directory = JSON.parse(readFileSync(join(themeRoot, 'assets/data/editorial-directory.json'), 'utf8'));
  const discovery = JSON.parse(readFileSync(join(themeRoot, 'assets/data/discovery-demo.json'), 'utf8'));
  if (/[—–]/u.test(JSON.stringify(directory))) failures.push('Public destination directory data must not use em dash or en dash punctuation.');
  if (directory.version !== 1) failures.push('Editorial directory manifest version must be 1.');
  if (!Array.isArray(directory.destinations) || directory.destinations.length < 1) failures.push('Editorial directory must represent the discovery destination collection.');
  if (!Array.isArray(directory.supporting_guides)) failures.push('Editorial directory supporting_guides must be an array.');
  const directoryDestinations = Array.isArray(directory.destinations) ? directory.destinations : [];
  const supportingGuides = Array.isArray(directory.supporting_guides) ? directory.supporting_guides : [];
  const ids = directoryDestinations.map((destination) => destination.id);
  if (new Set(ids).size !== ids.length) failures.push('Editorial directory destination IDs must be unique.');
  for (const id of ids) {
    if (!directoryPage.includes(`'${id}' => array(`)) failures.push(`Destination directory orbit is missing an explicit non-overlapping position for ${id}.`);
  }
  if (!directoryPage.includes('id="destination-<?php echo esc_attr( $destination[\'id\'] ); ?>"') || !directoryPage.includes('data-directory-map-state=')) failures.push('Destination directory cards need stable crawl targets and their canonical map-state identity.');
  const discoveryIds = new Set((discovery.destinations || []).map((destination) => destination.id));
  const directoryMapStates = directoryDestinations.map((destination) => destination.map_state);
  if (new Set(directoryMapStates).size !== directoryMapStates.length) failures.push('Editorial directory map states must be unique.');
  if (JSON.stringify([...directoryMapStates].sort()) !== JSON.stringify([...discoveryIds].sort())) failures.push('Editorial directory map states must cover every discovery destination exactly once.');
  for (const destination of directoryDestinations) {
    if (destination.map_state && !discoveryIds.has(destination.map_state)) failures.push(`Directory destination ${destination.id || '(missing id)'} links to unsupported map state ${destination.map_state}.`);
    if (destination.guide_status === 'published' && !/^\/destinations\/[a-z0-9-]+\/$/.test(destination.guide_path || '')) failures.push(`Published directory destination ${destination.id || '(missing id)'} needs a provisionable destination guide path.`);
  }
  const budapest = directoryDestinations.find((destination) => destination.id === 'budapest');
  if (!budapest || budapest.word_count < 5000 || budapest.source_count < 10) failures.push('Budapest directory evidence is not connected to the flagship guide gate.');
  const thailand = directoryDestinations.find((destination) => destination.id === 'thailand');
  if (!thailand || thailand.guide_path !== '/destinations/thailand/' || thailand.word_count < 5000 || thailand.source_count < 10) failures.push('Thailand directory evidence is not connected to the flagship guide gate.');
  if (directoryDestinations.some((destination) => destination.guide_status !== 'published' && destination.guide_path)) failures.push('Unreviewed directory guides must not expose a public guide path.');

  const earthByPath = new Map(directoryDestinations.filter(destination => destination.guide_path).map(destination => [destination.guide_path, destination]));
  const supportingIds = supportingGuides.map(guide => guide?.id);
  const supportingPaths = supportingGuides.map(guide => guide?.guide_path);
  if (new Set(supportingIds).size !== supportingIds.length) failures.push('Supporting guide IDs must be unique.');
  if (supportingIds.some(id => ids.includes(id))) failures.push('Supporting guide IDs must not duplicate Earth destination IDs.');
  if (new Set(supportingPaths).size !== supportingPaths.length) failures.push('Supporting guide paths must be unique.');
  const publicEarthPaths = directoryDestinations.map(destination => destination.guide_path).filter(Boolean);
  if (supportingPaths.some(path => publicEarthPaths.includes(path))) failures.push('Supporting guide paths must not duplicate Earth destination paths.');
  for (const guide of supportingGuides) {
    for (const message of supportingGuideContractErrors(guide, earthByPath, discoveryIds)) {
      failures.push(`Supporting guide ${guide?.id || '(missing id)'}: ${message}.`);
    }
  }

  const nestedGuideFixture = {
    id: 'bangkok-guide', city: 'Bangkok', country: 'Thailand', region: 'asia', region_label: 'Asia',
    experience: 'city', experience_label: 'City', duration: '4 days', best_for: 'Travelers', decision: 'Choose an area',
    map_state: 'bangkok', image: 'thailand.jpg', guide_status: 'published', guide_path: '/destinations/thailand/bangkok/',
    parent_path: '/destinations/thailand/', word_count: 5000, source_count: 10
  };
  if (supportingGuideContractErrors(nestedGuideFixture, earthByPath, discoveryIds).length) failures.push('Supporting guide validator rejects a valid nested guide that reuses an Earth map state.');
  if (!supportingGuideContractErrors({ ...nestedGuideFixture, guide_path: '/destinations/bangkok/' }, earthByPath, discoveryIds).length) failures.push('Supporting guide validator accepts a top-level path.');
  if (!supportingGuideContractErrors({ ...nestedGuideFixture, parent_path: '/destinations/athens/' }, earthByPath, discoveryIds).length) failures.push('Supporting guide validator accepts a non-immediate parent path.');
} catch (error) {
  failures.push(`Editorial directory manifest is invalid JSON: ${error.message}`);
}

const guideSyncPath = join(repoRoot, 'scripts', 'wp', 'sync-guide.ps1');
if (!existsSync(guideSyncPath)) failures.push('The guarded WordPress guide synchronization pipeline is missing.');
else {
  const guideSync = readFileSync(guideSyncPath, 'utf8');
  if (!guideSync.includes('validate-guide-packets.mjs')) failures.push('Guide synchronization does not run the content quality gate first.');
  if (!guideSync.includes('Assert-GuidePacketStatus') || !guideSync.includes("$RequestedStatus -eq 'publish' -and $PacketStatus -ne 'publish-ready'")) failures.push('Guide synchronization can publish content that is not publish-ready.');
  if (!guideSync.includes('SYNC TRA-VEL GUIDE')) failures.push('Guide synchronization lacks an explicit production-write confirmation.');
  if (!guideSync.includes('Import-Clixml')) failures.push('Guide synchronization is not using the encrypted credential file.');
  if (!guideSync.includes('_tra_vel_publication_status = [string]$packet.status')) failures.push('Guide synchronization does not persist the packet publication status.');
  if (!guideSync.includes('Get-CanonicalGuideRoute') || !guideSync.includes("\\A/destinations/(?<hub>[a-z0-9-]+)(?:/(?<child>[a-z0-9-]+))?/\\z")) failures.push('Guide synchronization does not restrict canonical paths to top-level or one nested supporting-guide segment.');
  if (!guideSync.includes('Resolve-GuideAncestorChain') || !guideSync.includes("([string]$_.slug -ceq $ancestorSlug) -and ([int]$_.parent -eq $expectedParentId)")) failures.push('Guide synchronization does not resolve every WordPress ancestor by exact slug and parent ID.');
  if (!guideSync.includes('slug=$encodedFinalSlug&parent=$parentId') || /pages\?slug=\$encodedFinalSlug&status/.test(guideSync)) failures.push('Guide synchronization can still select the final page through a global slug lookup.');
  if ((guideSync.match(/-MaximumRedirection 0/g) || []).length < 4) failures.push('Guide synchronization can follow redirects during lookup, write, or persistence verification.');
  for (const marker of ['Assert-PersistedGuide', 'ExpectedArticleHash', 'ExpectedSourceCount', 'ExpectedPublicationStatus', 'ExpectedCanonicalLink', 'ExpectedId', 'page-destination.php', 'permalink_template', 'generated_slug', '%pagename%', 'page_id=$resultId']) {
    if (!guideSync.includes(marker)) failures.push(`Guide synchronization persistence verification is missing ${marker}.`);
  }
  if (!guideSync.includes('?context=edit&_fields=id,slug,parent,status,template,link,permalink_template,generated_slug,content,meta')) failures.push('Guide synchronization does not perform an exact edit-context post-write readback with all verification fields.');
  const dryRunBoundary = guideSync.indexOf("if (-not $Apply) {");
  const credentialBoundary = guideSync.indexOf('if (-not (Test-Path -LiteralPath $CredentialPath))');
  if (dryRunBoundary < 0 || credentialBoundary < 0 || dryRunBoundary > credentialBoundary) failures.push('Guide synchronization dry runs still require production credentials.');
  for (const fixture of ['top-level canonical chain', 'nested canonical chain', 'ambiguous parent lookup', 'wrong parent ID', 'publish-ready enforcement', 'WordPress -2 slug drift', 'wrong or redirected permalink']) {
    if (!guideSync.includes(fixture)) failures.push(`Guide synchronization contract tests are missing ${fixture}.`);
  }
  for (const draftFixture of ['draft plain permalink with the wrong page ID', 'draft response with the wrong page ID', 'draft response with the wrong parent', 'draft response with slug drift', 'draft response with a wrong pretty path', 'draft permalink template resolving to the wrong canonical route']) {
    if (!guideSync.includes(draftFixture)) failures.push(`Guide synchronization draft contract tests are missing ${draftFixture}.`);
  }
  for (const persistenceFixture of ['wrong persisted page template', 'wrong persisted WordPress status', 'wrong persisted article SHA-256', 'wrong persisted source count', 'wrong persisted packet status']) {
    if (!guideSync.includes(persistenceFixture)) failures.push(`Guide synchronization persistence contract tests are missing ${persistenceFixture}.`);
  }
}

const discoveryController = readFileSync(join(themeRoot, 'inc/discovery.php'), 'utf8');
const publicTemplateTags = readFileSync(join(themeRoot, 'inc/template-tags.php'), 'utf8');
const supplierRegistry = readFileSync(join(themeRoot, 'inc/suppliers/class-supplier-registry.php'), 'utf8');
for (const phrase of ['מקור מחובר', 'ספק מחובר', 'בעלות ספק', 'בקשת סיוע', 'בקשת הסיוע', 'זהות מסחרית', 'מצב רכיבים', 'הצעה סופית']) {
  if (discoveryController.includes(phrase) || publicTemplateTags.includes(phrase)) failures.push(`Public server-rendered copy exposes internal or contradictory terminology: ${phrase}.`);
}
if (!discoveryController.includes("'selected_destination' => $selected_id ? $selected_id : null") || !discoveryController.includes("in_array( $selection_target, $destination_ids, true )") || !discoveryController.includes("$route['destination_id'] = $selected_id")) failures.push('Discovery routes are not bound to the resolved visible destination.');
if (!discoveryController.includes("$selection_target = $destination ? $destination : $focus") || !discoveryController.includes("'focus'") || !appJs.includes("focus: focusedDestination")) failures.push('Layer changes cannot preserve transient globe focus without hard-filtering the destination set.');
if (!/['"]focus['"][\s\S]{0,220}['"]validate_callback['"]\s*=>\s*['"]rest_validate_request_arg['"]/.test(discoveryController) || !/['"]destination['"][\s\S]{0,220}['"]validate_callback['"]\s*=>\s*['"]rest_validate_request_arg['"]/.test(discoveryController)) failures.push('Destination and transient focus REST parameters lack strict type validation.');
if (!discoveryController.includes("field_live_destination_ids( $field_provenance, 'deals' )") || !discoveryController.includes('in_array( $item[\'id\'], $live_deal_destination_ids, true )') || /\$package_prices_live|\$component_prices_live/.test(discoveryController)) failures.push('Discovery budget filtering is not gated by exact destination deal-price provenance.');
for (const marker of ['prepare_selected_plan', 'planning_profile_module', 'prepare_selected_plan_cost_ledger', 'selected_plan_schema', "'selected_plan'", "'booking_confirmed'", "'comparable_verified'"]) {
  if (!discoveryController.includes(marker)) failures.push(`The discovery REST response is missing truthful selected-plan behavior: ${marker}.`);
}
if (!discoveryController.includes('$usable_live_route') || !discoveryController.includes("'savings'             => null") || !discoveryController.includes("'state'       => $is_live ? ( $supplier_current ? 'live' : 'stale' ) : 'needs_search'")) failures.push('The selected-plan ledger can expose demo, stale, incomplete, or non-comparable prices without truthful state gating.');
if (!discoveryController.includes("'retrieved_at'") || !discoveryController.includes("'cost_components'") || !discoveryController.includes("'total_live'")) failures.push('Selected-plan monetary provenance is missing retrieval, component ownership, or total-scope metadata.');
if (!supplierRegistry.includes('detect_field_provenance') || !supplierRegistry.includes("array( 'deals', 'packages' )") || !supplierRegistry.includes("'weather_season'") || !supplierRegistry.includes('route_cost_metadata') || !supplierRegistry.includes("'retrieved_at'")) failures.push('Supplier merging does not fail closed at destination and component provenance boundaries.');
for (const marker of ["'trip'", "'max_stops'", "'max_duration'", "'allow_overnight'", "'comfort' === $sort"]) {
  if (!discoveryController.includes(marker)) failures.push(`Discovery intent filtering is missing ${marker}.`);
}
if (/function\s+get_items\s*\(\s*WP_REST_Request\b/.test(discoveryController)) {
  failures.push('REST controller overrides must keep the untyped WP_REST_Controller method signature for PHP 8 compatibility.');
}
if (!discoveryController.includes("'/' . $this->rest_base . '/cache'")) failures.push('The discovery cache administration route is missing.');
if (!discoveryController.includes("current_user_can( 'manage_options' )")) failures.push('Discovery cache mutation lacks a manage_options capability check.');

const flightController = readFileSync(join(themeRoot, 'inc/flights/class-flight-search-controller.php'), 'utf8');
if (/function\s+get_items\s*\(\s*WP_REST_Request\b/.test(flightController)) {
  failures.push('Flight REST controller overrides must keep the untyped WP_REST_Controller method signature for PHP 8 compatibility.');
}
if (!flightController.includes("'max_duration'") || !flightController.includes("'maximum' => 3000") || !commercialExperiencePage.includes('name="max_duration"')) failures.push('Map route-duration intent is not enforced by the flight search contract.');
if (!flightController.includes("'max_stops'      => array( 'type' => 'integer', 'default' => 1, 'minimum' => 0, 'maximum' => 3")) failures.push('Map and flight search disagree on the supported stop range.');
if (!flightController.includes("'/flights/cache'")) failures.push('The flight cache administration route is missing.');
if (!flightController.includes("current_user_can( 'manage_options' )")) failures.push('Flight cache mutation lacks a manage_options capability check.');

const flightInterface = readFileSync(join(themeRoot, 'inc/flights/interface-flight-search-adapter.php'), 'utf8');
for (const method of ['get_id', 'is_configured', 'get_mode', 'get_cache_version', 'search']) {
  if (!flightInterface.includes(`function ${method}(`)) failures.push(`Flight adapter contract is missing ${method}().`);
}

const hotelController = readFileSync(join(themeRoot, 'inc/hotels/class-hotel-search-controller.php'), 'utf8');
if (/function\s+get_items\s*\(\s*WP_REST_Request\b/.test(hotelController)) {
  failures.push('Hotel REST controller overrides must keep the untyped WP_REST_Controller method signature for PHP 8 compatibility.');
}
if (!hotelController.includes("'/hotels/cache'")) failures.push('The hotel cache administration route is missing.');
if (!hotelController.includes("current_user_can( 'manage_options' )")) failures.push('Hotel cache mutation lacks a manage_options capability check.');

const hotelInterface = readFileSync(join(themeRoot, 'inc/hotels/interface-hotel-search-adapter.php'), 'utf8');
for (const method of ['get_id', 'is_configured', 'get_mode', 'get_cache_version', 'search']) {
  if (!hotelInterface.includes(`function ${method}(`)) failures.push(`Hotel adapter contract is missing ${method}().`);
}

const insuranceController = readFileSync(join(themeRoot, 'inc/insurance/class-insurance-quote-controller.php'), 'utf8');
if (/function\s+get_items\s*\(\s*WP_REST_Request\b/.test(insuranceController)) {
  failures.push('Insurance REST controller overrides must keep the untyped WP_REST_Controller method signature for PHP 8 compatibility.');
}
if (!insuranceController.includes("'/insurance/cache'")) failures.push('The insurance cache administration route is missing.');
if (!insuranceController.includes("current_user_can( 'manage_options' )")) failures.push('Insurance cache mutation lacks a manage_options capability check.');
if (!insuranceController.includes("'policy_wording_controls' => true")) failures.push('Insurance responses must state that policy wording controls.');
if (!insuranceController.includes('tra_vel_insurance_sensitive_requires_post')) failures.push('Insurance assessment flags must be rejected over query-string GET.');
if (!insuranceController.includes("'private, no-store'")) failures.push('Sensitive insurance responses must disable storage.');
if (!appJs.includes("method: 'POST'")) failures.push('The browser must submit insurance quote inputs in a request body.');

const insuranceInterface = readFileSync(join(themeRoot, 'inc/insurance/interface-insurance-quote-adapter.php'), 'utf8');
for (const method of ['get_id', 'is_configured', 'get_mode', 'get_cache_version', 'quote']) {
  if (!insuranceInterface.includes(`function ${method}(`)) failures.push(`Insurance adapter contract is missing ${method}().`);
}

const packageController = readFileSync(join(themeRoot, 'inc/packages/class-trip-package-controller.php'), 'utf8');
if (/function\s+get_items\s*\(\s*WP_REST_Request\b/.test(packageController)) {
  failures.push('Package REST controller overrides must keep the untyped WP_REST_Controller method signature for PHP 8 compatibility.');
}
if (!packageController.includes("'/packages/cache'")) failures.push('The package cache administration route is missing.');
if (!packageController.includes("current_user_can( 'manage_options' )")) failures.push('Package cache mutation lacks a manage_options capability check.');
if (!packageController.includes("'bundle_discount_verified' => false")) failures.push('Package responses must reject unverified savings claims.');
if (!packageController.includes("'booking_enabled' => false")) failures.push('Demo package responses must keep booking disabled.');

const packageInterface = readFileSync(join(themeRoot, 'inc/packages/interface-trip-package-adapter.php'), 'utf8');
for (const method of ['get_id', 'is_configured', 'get_mode', 'get_cache_version', 'search']) {
  if (!packageInterface.includes(`function ${method}(`)) failures.push(`Package adapter contract is missing ${method}().`);
}

const workspaceController = readFileSync(join(themeRoot, 'inc/workspace/class-traveler-workspace-controller.php'), 'utf8');
if (!workspaceController.includes("current_user_can( 'read' )")) failures.push('Traveler workspace routes are not restricted to an authenticated user capability.');
if (!workspaceController.includes("'private, no-store, max-age=0'")) failures.push('Personal workspace responses must be private and non-cacheable.');
if (!workspaceController.includes("'delivery_enabled' => false")) failures.push('Price-watch delivery must remain disabled until a live supplier and consent flow exist.');
if (!workspaceController.includes('sanitize_internal_url')) failures.push('Saved item URLs must be constrained to internal Tra-Vel destinations.');

const savedPage = readFileSync(join(themeRoot, 'page-saved.php'), 'utf8');
for (const phrase of ['מקור מחובר', 'בקשת סיוע', 'זהות מסחרית', 'מצב רכיבים']) {
  if (savedPage.includes(phrase)) failures.push(`Saved Trips copy exposes internal workflow terminology: ${phrase}.`);
}
if (!savedPage.includes('data-traveler-workspace')) failures.push('The saved-trip page is missing its functional workspace root.');
if (!savedPage.includes('data-workspace-map')) failures.push('The saved-trip page is missing its interactive decision map.');
if (!savedPage.includes('data-workspace-preferences')) failures.push('The saved-trip page is missing traveler preference controls.');
for (const marker of [
  'data-workspace-cockpit',
  'data-workspace-plan-list',
  'data-workspace-cockpit-retry',
  'data-workspace-cockpit-announcer role="status" aria-live="polite" aria-atomic="true"',
  'data-workspace-map data-coordinate-mode="option-orbit" role="region"',
  'data-workspace-map-detail role="status" aria-live="polite" aria-atomic="true"',
  'data-workspace-filter="all" aria-pressed="true"',
  'data-workspace-preferences-status role="status" aria-live="polite" aria-atomic="true"'
]) {
  if (!savedPage.includes(marker)) failures.push(`The saved-trip mounted UI is missing ${marker}.`);
}
if (savedPage.indexOf('data-workspace-cockpit') > savedPage.indexOf('data-workspace-quote-cases')) failures.push('Private AI plans must appear in flow before assistance cases.');
for (const marker of [
  "const workspaceDeletedLocalKey = 'traVelV2.workspace.deleted.v1'",
  "workspaceMutationRequest('/sync', {method: 'PUT'",
  'error.status = response.status',
  "error.code = typeof payload.code === 'string' ? payload.code : ''",
  "['tra_vel_workspace_capacity', 'tra_vel_workspace_sync_capacity'].includes(error?.code)",
  'אפשר לשמור עד 50 אפשרויות',
  'אפשר לסנכרן עד 50 אפשרויות',
  'deleted_item_ids: deletedItemIds',
  'workspaceDeletionTombstones.size >= 50',
  "agentApiRequest('/runs?limit=12'",
  'return normalizedRuns.slice(0, 12)',
  "return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i",
  'run.resume_available === true && validWorkspaceAgentRunId(run.run_id)',
  "!['he-IL', 'en-US', 'mixed'].includes(rawRun.locale)",
  "typeof planningContext.destination === 'string' && /^[a-z0-9-]{1,60}$/.test(planningContext.destination)",
  'planningContext.latitude >= -90 && planningContext.latitude <= 90',
  'workspacePlanRunContractValid',
  'normalizeWorkspacePlanPayload',
  "setWorkspaceCockpitState('loading'",
  "hasConfirmedPlans ? 'stale' : 'error'",
  "state = 'recovered'",
  "state = 'advanced'",
  "state = 'attention'",
  "state = 'terminal'"
]) {
  if (!appJs.includes(marker)) failures.push(`The traveler workspace browser contract is missing ${marker}.`);
}
const planRenderStart = appJs.indexOf('function renderWorkspacePlanCard(');
const planRenderEnd = appJs.indexOf('\nfunction setWorkspaceCockpitState(', planRenderStart);
const planRenderBody = planRenderStart >= 0 && planRenderEnd > planRenderStart ? appJs.slice(planRenderStart, planRenderEnd) : '';
if (!planRenderBody || planRenderBody.includes('innerHTML')) failures.push('AI plan cards must be mounted with textContent-only DOM construction.');
if (!/createElement\('ol'\)[\s\S]*?setAttribute\('aria-current', 'step'\)/.test(planRenderBody)) failures.push('AI plan cards need a semantic ordered progress list with aria-current.');
const saveWorkspaceStart = appJs.indexOf('async function saveWorkspaceItem(');
const saveWorkspaceEnd = appJs.indexOf('\nfunction createSaveOfferButton(', saveWorkspaceStart);
const saveWorkspaceBody = saveWorkspaceStart >= 0 && saveWorkspaceEnd > saveWorkspaceStart ? appJs.slice(saveWorkspaceStart, saveWorkspaceEnd) : '';
const toastStart = appJs.indexOf('function showWorkspaceToast(');
const toastEnd = appJs.indexOf('\nfunction normalizeWorkspaceItem(', toastStart);
const toastBody = toastStart >= 0 && toastEnd > toastStart ? appJs.slice(toastStart, toastEnd) : '';
if (!/function showWorkspaceToast\(message, icon = 'heart', anchor = null\)/.test(toastBody)
  || !/anchor\?\.closest\?\.\('\.flight-offer, \.hotel-offer, \.trip-package-card'\)/.test(toastBody)
  || !/if \(toast\.parentElement !== host\) host\.append\(toast\)/.test(toastBody)
  || !saveWorkspaceBody.includes("showWorkspaceToast(message, icon, button)")) {
  failures.push('Save feedback outside the map/workspace must mount inside its triggering result card instead of below the footer.');
}
if (!/writeLocalWorkspace\(nextWorkspace\)[\s\S]*?forgetWorkspaceDeletion\(item\.id\)/.test(saveWorkspaceBody)
  || !/if \(!forgetWorkspaceDeletion\(item\.id\)\) \{[\s\S]*?writeLocalWorkspace\(workspace\)/.test(saveWorkspaceBody)) {
  failures.push('Re-saving a deleted item must persist locally before clearing its tombstone and roll back if tombstone clearing fails.');
}
if (!/if \(!existing && workspace\.items\.length >= 50\) \{[\s\S]*?reason: 'local_capacity'/.test(saveWorkspaceBody)
  || !saveWorkspaceBody.includes('הסירו אפשרות אחת ואז שמרו את האפשרות החדשה')) {
  failures.push('A new fifty-first local item must fail before mutation with explicit remove-one guidance while existing IDs remain refreshable.');
}
const preferencesStart = appJs.indexOf('async function saveWorkspacePreferences(');
const preferencesEnd = appJs.indexOf('\nfunction setWorkspaceAccountSyncState(', preferencesStart);
const preferencesBody = preferencesStart >= 0 && preferencesEnd > preferencesStart ? appJs.slice(preferencesStart, preferencesEnd) : '';
const watchStart = appJs.indexOf('async function toggleWorkspaceWatch(');
const watchEnd = appJs.indexOf('\nfunction renderWorkspaceCard(', watchStart);
const watchBody = watchStart >= 0 && watchEnd > watchStart ? appJs.slice(watchStart, watchEnd) : '';
const removeStart = appJs.indexOf('async function removeWorkspaceItem(');
const removeEnd = appJs.indexOf('\nasync function toggleWorkspaceWatch(', removeStart);
const removeBody = removeStart >= 0 && removeEnd > removeStart ? appJs.slice(removeStart, removeEnd) : '';
if (!/function applyServerConfirmedWorkspace\([\s\S]*?const devicePersisted = writeLocalWorkspace\(mergedWorkspace\);[\s\S]*?if \(!devicePersisted\) travelerWorkspace = mergedWorkspace/.test(appJs)) {
  failures.push('Server-confirmed workspace state must stay available in memory when device persistence fails.');
}
for (const [label, body] of [['item save', saveWorkspaceBody], ['preferences', preferencesBody], ['price watch', watchBody]]) {
  if (!/applyServerConfirmedWorkspace\([\s\S]*?if \(confirmation\.devicePersisted\)[\s\S]*?devicePersisted: false/.test(body)) {
    failures.push(`${label} must distinguish account confirmation from failed device persistence.`);
  }
}
for (const [label, body] of [['item save', saveWorkspaceBody], ['preferences', preferencesBody], ['price watch', watchBody], ['item removal', removeBody]]) {
  if (!body.includes('workspaceLocalSyncSnapshot(')
    || !body.includes('workspaceMutationRequest(')
    || !/applyServerConfirmedWorkspace\([^;]+mutationSnapshot\)/.test(body)) {
    failures.push(`${label} must capture post-write browser state and reject a stale async server response.`);
  }
}
if (!appJs.includes('sharedRequestDeadlineMilliseconds = 15000')
  || !/function requestWithDeadline\([\s\S]*?Promise\.race/.test(appJs)
  || !/function workspaceMutationRequest\([\s\S]*?'workspace_request_timeout'/.test(appJs)) failures.push('Workspace writes must share a real 15-second request deadline.');
if (!/workspaceCorrectiveSyncMaximumAttempts = 3/.test(appJs)
  || !/workspaceCorrectiveSyncAttempts >= workspaceCorrectiveSyncMaximumAttempts/.test(appJs)) failures.push('Corrective workspace sync must have a bounded retry budget.');
if (saveWorkspaceBody.includes('הסנכרון לחשבון ינסה שוב בהמשך')
  || !/correctiveScheduled[\s\S]*?ניסיון סנכרון מוגבל/.test(saveWorkspaceBody)) failures.push('Item-save copy may promise a retry only when bounded corrective sync was actually scheduled.');
if (!/if \(canResume && !storeAgentRunSession\(run\.run_id\)\)[\s\S]*?return;[\s\S]*?window\.location\.assign/.test(planRenderBody)) failures.push('Saved AgentRun resume must not navigate when private session storage fails.');
const initWorkspaceStart = appJs.indexOf('async function initTravelerWorkspace(');
const initWorkspaceEnd = appJs.indexOf('\nfunction initNavigation(', initWorkspaceStart);
const initWorkspaceBody = initWorkspaceStart >= 0 && initWorkspaceEnd > initWorkspaceStart ? appJs.slice(initWorkspaceStart, initWorkspaceEnd) : '';
if (!/const authenticatedWorkspace = Boolean\(window\.traVelV2\?\.isLoggedIn\)/.test(initWorkspaceBody)
  || !/if \(authenticatedWorkspace\) \{[\s\S]*?loadWorkspacePlans\(\)[\s\S]*?\} else \{[\s\S]*?setWorkspaceCockpitState\('local'/.test(initWorkspaceBody)
  || !/scheduleWorkspaceQuoteCasePoll\(250\);[\s\S]*?if \(authenticatedWorkspace\) scheduleWorkspacePlanPoll\(250\)/.test(initWorkspaceBody)) {
  failures.push('Guest workspaces must keep owner-cookie QuoteCases active while suppressing authenticated AgentRun loading and polling.');
}
if (/setInterval\s*\([^)]*(?:workspacePlan|cockpit)/i.test(appJs)) failures.push('Cockpit progress must never advance on a decorative interval.');
if (!/nextRevision > previousRevision[\s\S]*?return 'advanced'/.test(appJs) || !/return 'recovered'/.test(appJs)) failures.push('Positive cockpit motion must require a newer server revision or a confirmed recovery.');
if (!/data-workspace-cockpit data-state="idle"[^>]+aria-busy="false"/.test(savedPage)
  || !/data-workspace-quote-cases data-state="idle"[^>]+aria-busy="false"/.test(savedPage)
  || savedPage.includes('טוען את סביבת העבודה האישית')) failures.push('Saved-page markup must default to truthful idle states when JavaScript is unavailable.');
if (!/requestedDataMode === 'live' \? 'mixed'/.test(appJs)
  || !/parsed\.items[\s\S]*?\.map\(normalizeBrowserWorkspaceItem\)/.test(appJs)) failures.push('Browser workspace snapshots must downgrade untrusted live provenance to mixed.');
if (!/allowedProtocol[\s\S]*?sameOrigin[\s\S]*?!url\.username && !url\.password/.test(appJs)) failures.push('Workspace links must require HTTP(S), same origin, and no embedded credentials.');
const applyWorkspaceStart = appJs.indexOf('function applyServerConfirmedWorkspace(');
const applyWorkspaceEnd = appJs.indexOf('\nfunction workspaceCapacityError(', applyWorkspaceStart);
const applyWorkspaceBody = applyWorkspaceStart >= 0 && applyWorkspaceEnd > applyWorkspaceStart ? appJs.slice(applyWorkspaceStart, applyWorkspaceEnd) : '';
const synchronizeWorkspaceStart = appJs.indexOf('async function synchronizeWorkspaceAccount(');
const synchronizeWorkspaceEnd = appJs.indexOf('\nfunction scheduleWorkspaceCorrectiveSync(', synchronizeWorkspaceStart);
const synchronizeWorkspaceBody = synchronizeWorkspaceStart >= 0 && synchronizeWorkspaceEnd > synchronizeWorkspaceStart
  ? appJs.slice(synchronizeWorkspaceStart, synchronizeWorkspaceEnd)
  : '';
if (!/workspaceLocalSyncSnapshotMatches\(mutationSnapshot, currentSnapshot\)/.test(applyWorkspaceBody)
  || !/scheduleWorkspaceCorrectiveSync\(250, true\)/.test(applyWorkspaceBody)
  || !/workspaceLocalSyncSnapshotMatches[\s\S]*?reason: 'local_changed'/.test(synchronizeWorkspaceBody)
  || !/window\.addEventListener\('storage'[\s\S]*?scheduleWorkspaceCorrectiveSync\(0, true\)/.test(appJs)) failures.push('Workspace sync must detect in-flight/local cross-tab changes and schedule conservative reconciliation.');
if (!/document\.visibilityState === 'hidden'[\s\S]*?workspacePlanRuntime\.authRequired/.test(appJs)
  || !/error\?\.status === 401 \|\| error\?\.status === 403[\s\S]*?reauth_required/.test(appJs)) failures.push('Private plan polling must stop while hidden and after authorization expiry.');
if (!/captureWorkspaceListFocus[\s\S]*?restoreWorkspaceListFocus/.test(appJs)
  || !/workspaceItemMutationRegistry/.test(appJs)) failures.push('Saved cards and async item mutations must preserve logical focus and serialize per item.');
if (!/function renderWorkspaceMap\(items, preferredItemId = ''\)[\s\S]*?focusedPinId[\s\S]*?focusTarget\?\.focus/.test(appJs)) failures.push('Workspace map rerenders must preserve keyboard focus by saved-item pin id.');
if (!/workspacePreferencesDirty && !force/.test(appJs)
  || !/form\?\.addEventListener\('input', markWorkspacePreferencesDirty\)/.test(appJs)
  || !/confirmWorkspacePreferencesSubmission\(submittedEditGeneration/.test(appJs)) failures.push('Workspace synchronization must not overwrite dirty, unsubmitted preference controls.');
if (!/const shouldRestoreFocus = Boolean\(capturedFocusSnapshot\)[\s\S]*?if \(shouldRestoreFocus\) restoreWorkspaceListFocus/.test(appJs)) failures.push('Async saved-item mutations must not steal focus after the traveler moves elsewhere.');
for (const marker of [
  '.text-link { min-height: 44px',
  '.workspace-quote-empty a { min-height: 44px',
  '.workspace-empty a { min-height: 44px',
  '.workspace-auth-card > a { min-height: 44px',
  '.save-offer-button { min-height: 44px',
  '.workspace-map-detail h2 { min-width: 0;',
  '.workspace-card-head h3 { min-width: 0;',
  '.workspace-quote-card h3 { min-width: 0;'
]) {
  if (!appCss.includes(marker)) failures.push(`Workspace mobile/accessibility CSS is missing ${marker}.`);
}
for (const marker of [
  '.workspace-plan-card[data-state="completed"]',
  '.workspace-plan-status[data-state="completed"]',
  '.workspace-card-context span { min-width: 0;',
  '.workspace-quote-card-next p { min-width: 0;',
  '.workspace-map-pin strong { max-width: 120px;',
  '.workspace-orbit [data-workspace-map-pins] { position: absolute;',
  '.workspace-map-pin:nth-child(n+5) { display: none; }'
]) {
  if (!appCss.includes(marker)) failures.push(`Workspace completion/mobile CSS is missing ${marker}.`);
}
if (!/\.workspace-cockpit \{ position: static;/.test(appCss)) failures.push('The AI cockpit must remain in document flow.');
if (!/\.workspace-toast \{ position: static;/.test(appCss) || /\.workspace-toast \{ position: fixed;/.test(appCss)) failures.push('Workspace feedback must not overlay the Earth or its controls.');
if (!/@media \(max-width: 680px\)[\s\S]*?\.workspace-plan-list \{ grid-template-columns: 1fr; \}[\s\S]*?\.workspace-item-grid \{ display: grid; grid-template-columns: 1fr;/.test(appCss)) failures.push('AI plans and saved options must become vertical mobile cards.');
for (const marker of ['min-height: 44px', ':focus-visible', '.workspace-plan-progress li[data-state="current"]', '.workspace-map-pin,.workspace-toast,.workspace-cockpit[aria-busy="true"]']) {
  if (!appCss.includes(marker)) failures.push(`Workspace accessibility CSS is missing ${marker}.`);
}

const headerPage = readFileSync(join(themeRoot, 'header.php'), 'utf8');
for (const marker of ['mobile-primary-navigation', 'mobile-nav-disclosure', '/account/', '/partners/']) {
  if (!headerPage.includes(marker)) failures.push(`The navigation is missing ${marker}.`);
}
for (const marker of ['role="dialog"', 'aria-modal="true"', 'aria-labelledby="mobile-navigation-title"', 'class="mobile-drawer-navigation"', 'class="sr-only skip-link"']) {
  if (!headerPage.includes(marker)) failures.push(`The mobile navigation accessibility contract is missing ${marker}.`);
}
if (/add_query_arg\(\s*'service'\s*,\s*'(?:transfers|activities)'/.test(headerPage)) failures.push('Navigation still sends service intent to destination filters that do not consume it.');
if (headerPage.includes('רכב והעברות')) failures.push('Navigation must not advertise car rental through a transfers-only planner scope.');
for (const scope of ['transfers', 'activities']) {
  const scopedPlannerLinks = headerPage.match(new RegExp(`add_query_arg\\( 'scope', '${scope}', home_url\\( '/ai-planner/' \\) \\)`, 'g')) || [];
  if (scopedPlannerLinks.length < 2) failures.push(`${scope} intent must reach the AI planner from both desktop and mobile navigation.`);
}
for (const marker of ['drawerInertSnapshot', 'drawerBackgroundTargets', 'setDrawerBackgroundInert', 'drawerFocusableControls', 'containDrawerFocus', "event.key !== 'Tab'", "event.shiftKey", "element.setAttribute('inert', '')", "element.removeAttribute('inert')"]) {
  if (!appJs.includes(marker)) failures.push(`The mobile drawer focus/inert lifecycle is missing ${marker}.`);
}
if (!/if \(!wasOpen\) \{[\s\S]{0,180}setDrawerBackgroundInert\(true\)/.test(appJs) || !/else \{[\s\S]{0,100}setDrawerBackgroundInert\(false\)[\s\S]{0,180}drawerWasOpenedBy\.focus\(\)/.test(appJs)) failures.push('The mobile drawer must apply inert once on open, restore it before returning focus, and preserve the existing opener lifecycle.');
if (!/\.skip-link:is\(:focus,:focus-visible\)\s*\{[^}]*position:\s*fixed;[^}]*z-index:\s*300;[^}]*clip:\s*auto;[^}]*background:\s*var\(--lime\);/.test(appCss)) failures.push('The skip link must become a high-contrast visible control when focused.');
const accountPage = readFileSync(join(themeRoot, 'page-account.php'), 'utf8');
if (!accountPage.includes('wp_login_form')) failures.push('The account page is missing the native secure WordPress login form.');
const authSource = readFileSync(join(themeRoot, 'inc/auth.php'), 'utf8');
if (!authSource.includes("shortcode_exists( 'nextend_social_login' )")) failures.push('Social login must stay hidden until a real provider plugin is configured.');
const partnerPage = readFileSync(join(themeRoot, 'page-partners.php'), 'utf8');
if (!partnerPage.includes('tra_vel_v2_user_can_access_supplier_portal')) failures.push('The partner center is missing its capability gate.');

const handoffController = readFileSync(join(themeRoot, 'inc/handoffs/class-supplier-handoff-controller.php'), 'utf8');
if (!handoffController.includes("'https' !== $scheme")) failures.push('Supplier handoffs must enforce HTTPS.');
if (!handoffController.includes("'sponsored noopener noreferrer'")) failures.push('Supplier handoffs must qualify and isolate outbound partner links.');
if (!handoffController.includes("'private, no-store, max-age=0'")) failures.push('Prepared supplier handoffs must not be cached.');
if (!handoffController.includes("'assisted_quote'") || !handoffController.includes("'partner_booking'")) failures.push('Commercial handoffs do not distinguish owned sales from affiliate bookings.');
const ownedHandoff = readFileSync(join(themeRoot, 'inc/handoffs/class-whatsapp-sales-handoff-provider.php'), 'utf8');
if (!ownedHandoff.includes("'relationship'  => 'owned'")) failures.push('The owned Tra-Vel assisted-sales provider is missing.');
if (!ownedHandoff.includes('api.whatsapp.com')) failures.push('The owned sales provider is missing its allowlisted WhatsApp destination.');
if (/\$context\['(?:price|pricing|medical_condition|pregnancy)'\]/.test(ownedHandoff)) failures.push('The assisted-sales provider must not transmit sample prices or medical answers.');
if (!appJs.includes('startCommercialHandoff') || !appJs.includes('tra-vel-concierge')) failures.push('Commercial result cards are not connected to the verified handoff boundary.');
for (const marker of ['commercialDataMode', 'commercialSellerReady', 'commercialPriceText', 'commercialDataNotice', 'configureCommercialAction', 'is-planning-only', 'insuranceSaleReady', 'regulated_sale_ready']) {
  if (!appJs.includes(marker)) failures.push(`Commercial result truth gating is missing ${marker}.`);
}
if (!/function commercialPriceText\(payload, formatted\)[\s\S]{0,180}if \(!value\) return 'בהצעה האישית';[\s\S]{0,80}return value;/.test(appJs)) failures.push('Configured planning amounts must remain visible while missing amounts lead to the personal quote.');
for (const marker of [
  'price_amount: liveOffer ? offer.trip_total.total : null',
  'price_amount: liveProperty ? property.pricing.total_stay : null',
  'price_amount: livePackage ? tripPackage.pricing.total_party : null',
  "times.textContent = `${journey.departure_time} → ${journey.arrival_time}`",
  "appendTextElement(identity, 'h3', offer.label)",
  "appendTextElement(body, 'h3', property.name)",
  "appendTextElement(identity, 'h3', tripPackage.name)",
  'המחיר, הזמינות והתנאים מאומתים לפני התשלום',
  'בקשו בדיקת מחיר לטיסה',
  'בקשו בדיקת מלון',
  'בקשו בדיקת חופשה'
]) {
  if (!appJs.includes(marker)) failures.push(`Rich planning-price presentation is missing: ${marker}`);
}
for (const forbidden of [
  "demo: 'תרחישי תכנון, לא הצעת מחיר'",
  "commercialDataMode(payload) === 'live' ? value : 'מחיר טרם נבדק'",
  "title: liveProperty ? property.name : planningTitle",
  "title: liveOffer ? offer.label : planningTitle",
  "title: livePackage ? tripPackage.name : planningTitle"
]) {
  if (appJs.includes(forbidden)) failures.push(`An empty or generic planning presentation remains in public commercial copy: ${forbidden}`);
}
for (const marker of ['.commercial-data-notice', '.is-planning-only', '.insurance-planning-boundary']) {
  if (!appCss.includes(marker)) failures.push(`Commercial result truth styling is missing ${marker}.`);
}
if (!/commercialDataMode\(payload\) === 'live'[\s\S]{0,180}provider !== 'demo'[\s\S]{0,180}(?:bookable|purchasable)/.test(appJs)) failures.push('Seller actions are not closed behind live provenance, a non-demo provider, and an explicit commerce capability.');
if (!/commercialDataMode\(payload\) === 'live' && payload\?\.meta\?\.regulated_sale_ready === true/.test(appJs)) failures.push('Insurance products are not closed behind the explicit regulated-sale capability.');
for (const marker of [
  "const acquisitionStorageKey = 'traVelAcquisition'",
  'function captureAcquisition()',
  'function readAcquisition()',
  'captureAcquisition();',
  "const leadContactConsentVersion = '2026-07-19'",
  'function normalizedIsraeliPhone(raw)',
  'function buildLeadContactStep({onSave, onSkip})',
  'function openCommercialContactStep(button, vertical, commerce, payload, candidate = {})',
  'רוצים שנחזור אליכם גם אם השיחה מתנתקת?',
  'אני מאשר/ת ל-Tra-Vel לשמור את הפרטים וליצור קשר לגבי הבקשה הזו',
  'שמרו והמשיכו בוואטסאפ',
  'המשיכו בלי להשאיר פרטים',
  "privacyLink.href = '/privacy-policy/'",
  'consent_version: leadContactConsentVersion',
  '...(acquisition ? {acquisition} : {})',
  '...(contact ? {contact} : {})'
]) {
  if (!appJs.includes(marker)) failures.push(`The 1.23.0 acquisition capture or pre-WhatsApp lead-contact step is missing ${marker}.`);
}
if (!/first-touch|First-touch/.test(appJs) || !appJs.includes('.trim().slice(0, 120)')) failures.push('Acquisition capture must stay first-touch and cap every stored field at 120 characters.');
if (!/function openCommercialContactStep\([\s\S]{0,400}if \(commercialSellerReady\(payload, commerce\)\) \{\s*return startCommercialHandoff\(button, vertical, commerce, payload, candidate\);\s*\}/.test(appJs)) failures.push('The WhatsApp-labeled contact step must gate only the assisted-sales path and leave proven live seller handoffs unchanged.');
if (!appCss.includes('.lead-contact-step') || !appCss.includes('.lead-contact-save:focus-visible') || !appCss.includes('.lead-contact-field input:focus-visible')) failures.push('The lead-contact step is missing its styling or visible keyboard focus treatment.');
if (!/@media \(prefers-reduced-motion: reduce\)[\s\S]*\.lead-contact-step,\.lead-contact-step \* \{ animation: none !important; transition: none !important; \}/.test(appCss)) failures.push('The lead-contact step is missing its reduced-motion path.');
const provenanceSource = readFileSync(join(themeRoot, 'inc/class-commercial-provenance.php'), 'utf8');
for (const marker of ['retrieved_at', 'fresh_until', 'availability_checked_at', 'price_scope', 'licensed_provider', 'license_reference', 'regulated_sale_ready']) {
  if (!provenanceSource.includes(marker)) failures.push(`The live commercial provenance boundary is missing ${marker}.`);
}
for (const [registry, markers] of Object.entries({
  'inc/flights/class-flight-search-registry.php': ['Tra_Vel_V2_Commercial_Provenance::validate', 'whole_party_round_trip'],
  'inc/hotels/class-hotel-search-registry.php': ['Tra_Vel_V2_Commercial_Provenance::validate', 'whole_stay'],
  'inc/packages/class-trip-package-registry.php': ['Tra_Vel_V2_Commercial_Provenance::validate', 'whole_party_trip'],
  'inc/insurance/class-insurance-quote-registry.php': ['Tra_Vel_V2_Commercial_Provenance::validate', 'whole_policy_period', "array( 'regulated' => true )"]
})) {
  const source = readFileSync(join(themeRoot, registry), 'utf8');
  for (const marker of markers) if (!source.includes(marker)) failures.push(`${registry} is missing live-provenance marker ${marker}.`);
}
const insuranceQuoteController = readFileSync(join(themeRoot, 'inc/insurance/class-insurance-quote-controller.php'), 'utf8');
if (!insuranceQuoteController.includes("'regulated_sale_ready' => $regulated_sale_ready")) failures.push('Insurance REST responses do not expose the validated regulated-sale capability.');
if (!handoffController.includes('allowed_hosts')) failures.push('Supplier handoffs must enforce an explicit host allowlist.');

const experiencePage = readFileSync(join(themeRoot, 'page-experience.php'), 'utf8');
if (!experiencePage.includes('$agent_destination') || !experiencePage.includes('$agent_intents') || !experiencePage.includes('esc_textarea( $agent_prompt )')) failures.push('Destination-to-agent handoff cannot safely prefill the private AI request.');
for (const marker of ['$experience_destination_catalog', '$experience_selected_destination', '$experience_destination_options', 'data-context-supported="<?php echo $experience_context_supported', 'data-selected-destination="<?php echo $experience_context_supported', "$destination_code_slugs[ $requested_destination_code ]", '$stay_destination_code', 'היעד שהוזן אינו מוחלף']) {
  if (!experiencePage.includes(marker)) failures.push(`Internal product maps can contradict the active destination without ${marker}.`);
}
const expectedExperienceDestinations = new Map([
  ['BUD', 'budapest'], ['PRG', 'prague'], ['VIE', 'vienna'], ['ATH', 'athens'],
  ['DXB', 'dubai'], ['BKK', 'bangkok'], ['HND', 'tokyo'], ['LIS', 'lisbon']
]);
const experienceCatalogSource = experiencePage.match(/\$experience_destination_catalog\s*=\s*array\(([\s\S]*?)\n\);/)?.[1] || '';
for (const [code, destination] of expectedExperienceDestinations) {
  if (!experiencePage.includes(`'${code}' => '${destination}'`)) failures.push(`Internal experience pages are missing the ${code}/${destination} destination identity.`);
  if (!(new RegExp(`['"]${destination}['"]\\s*=>\\s*array\\(`)).test(experienceCatalogSource)) failures.push(`Internal experience maps are missing destination content for ${destination}.`);
  if (!appJs.includes(`${code.toLowerCase()}: '${destination}'`)) failures.push(`Client URL normalization is missing the ${code}/${destination} destination identity.`);
}
for (const marker of ['$experience_destination_links', 'data-experience-destination-index', 'data-experience-destination-link', "home_url( '/' . $experience_kind . '/' )", "home_url( '/travel-insurance/' )", "home_url( '/ai-planner/' )"]) {
  if (!experiencePage.includes(marker)) failures.push(`Internal experience pages are missing crawlable destination routing marker ${marker}.`);
}
if (/home_url\(\s*['"]\/(?:flights|hotels|packages)\/[a-z0-9-]+\//.test(experiencePage)) failures.push('Internal experience pages must not invent unprovisioned nested product slugs.');
const experienceDestinationIndexRule = appCss.match(/\.experience-destination-index\s*\{([^}]*)\}/)?.[1] || '';
if (!experienceDestinationIndexRule || /position:\s*(?:absolute|fixed|sticky)/.test(experienceDestinationIndexRule)) failures.push('The eight-destination experience index must remain in document flow.');
if (!/\.experience-destination-index a\s*\{[^}]*min-height:\s*(?:4[4-9]|[5-9][0-9]|[1-9][0-9]{2,})px;/.test(appCss)) failures.push('Every experience destination link must retain at least a 44px touch target.');
if (!appJs.includes("if (map.dataset.contextSupported === 'false') return;")) failures.push('An unsupported product-page destination can still be replaced by the first decorative map option.');
for (const marker of ['$requested_origin_code', '$requested_adults', '$requested_children', '$requested_rooms', '$requested_date']) {
  if (!experiencePage.includes(marker)) failures.push(`Homepage discovery choices cannot prefill comparison pages without ${marker}.`);
}
for (const key of ['departure_date', 'return_date', 'checkin', 'checkout', 'start_date', 'end_date']) {
  if (!experiencePage.includes(`$requested_date( '${key}'`)) failures.push(`Comparison pages do not preserve and validate the homepage ${key} query value.`);
}
if (!experiencePage.includes("preg_match( '/^[A-Z]{3}$/'") || !experiencePage.includes("min( 6, max( 1, absint( wp_unslash( $_GET['adults'] ) ) ) )") || !experiencePage.includes("min( 4, max( 0, absint( wp_unslash( $_GET['children'] ) ) ) )") || !experiencePage.includes("min( 3, max( 1, absint( wp_unslash( $_GET['rooms'] ) ) ) )")) failures.push('Homepage comparison prefills must validate airport codes and bound party sizes.');
if (!experiencePage.includes('selected( $requested_adults') || !experiencePage.includes('selected( $requested_children') || !experiencePage.includes('selected( $requested_rooms')) failures.push('Validated homepage party choices are not rendered back into comparison controls.');
if (!experiencePage.includes('checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] )') || !experiencePage.includes('$value < $minimum')) failures.push('Comparison date prefills must reject impossible and past dates server-side.');
for (const minimumContract of [
  "$return_default    = $requested_date( 'return_date', $date_after( $departure_default, 14 ), $return_minimum );",
  "$checkout_default  = $requested_date( 'checkout', $date_after( $checkin_default, 4 ), $date_after( $checkin_default, 1 ) );",
  "$package_return    = $requested_date( 'return_date', $date_after( $package_departure, 4 ), $date_after( $package_departure, 1 ) );",
  "$insurance_end     = $requested_date( 'end_date', $date_after( $insurance_start, 6 ), $insurance_start );"
]) {
  if (!experiencePage.includes(minimumContract)) failures.push(`Comparison date ordering contract is missing: ${minimumContract.split('=')[0].trim()}.`);
}
if (!experiencePage.includes("$return_minimum    = $is_ai_planner && 'insurance' === $requested_product ? $departure_default : $date_after( $departure_default, 1 );")) failures.push('Map-to-agent insurance context must preserve a valid same-day coverage range instead of replacing it with a fourteen-day fallback.');
for (const inputMinimum of [
  'min="<?php echo esc_attr( $date_after( $departure_default, 1 ) ); ?>"',
  'min="<?php echo esc_attr( $date_after( $checkin_default, 1 ) ); ?>"',
  'min="<?php echo esc_attr( $date_after( $package_departure, 1 ) ); ?>"',
  'min="<?php echo esc_attr( $insurance_start ); ?>"'
]) {
  if (!experiencePage.includes(inputMinimum)) failures.push(`Rendered comparison dates are missing their strict minimum: ${inputMinimum}.`);
}
for (const state of ['$flight_initial_search', '$hotel_initial_search', '$package_initial_search', '$insurance_context_ready']) {
  if (!experiencePage.includes(`${state} `)) failures.push(`Comparison pages must keep a separate truthful auto-search state for ${state}.`);
}
for (const marker of [
  "data-auto-search=\"<?php echo esc_attr( $flight_initial_search ? 'true' : 'false' ); ?>\"",
  "data-auto-search=\"<?php echo esc_attr( $hotel_initial_search ? 'true' : 'false' ); ?>\"",
  "data-auto-search=\"<?php echo esc_attr( $package_initial_search ? 'true' : 'false' ); ?>\"",
  "data-context-supported=\"<?php echo esc_attr( $insurance_context_ready ? 'true' : 'false' ); ?>\""
]) {
  if (!experiencePage.includes(marker)) failures.push(`Comparison auto-search truth is not rendered through ${marker}.`);
}
if ((appJs.match(/form\.dataset\.autoSearch === 'false'/g) || []).length < 3 || !appJs.includes("form.dataset.contextSupported === 'false'")) failures.push('Flights, hotels, packages, and insurance must fail closed through separate client auto-search gates.');
for (const strictDateSync of [
  'syncStrictTravelEndDate(departure, returning, 7);',
  'syncStrictTravelEndDate(checkin, checkout, 4);',
  'syncStrictTravelEndDate(departure, returnDate, 4);'
]) {
  if (!appJs.includes(strictDateSync)) failures.push(`Internal comparison date edits must enforce a next-day end-date minimum through ${strictDateSync}`);
}
if (!experiencePage.includes('data-flight-search')) failures.push('The flights page is missing its functional search form.');
if (!experiencePage.includes('data-flight-results')) failures.push('The flights page is missing its dynamic results region.');
if (!experiencePage.includes('data-hotel-search')) failures.push('The hotels page is missing its functional search form.');
if (!experiencePage.includes('data-hotel-area-map')) failures.push('The hotels page is missing its neighborhood map.');
if (!experiencePage.includes('data-hotel-results')) failures.push('The hotels page is missing its dynamic results region.');
if (!experiencePage.includes('data-insurance-quote')) failures.push('The insurance page is missing its functional comparison form.');
if (!experiencePage.includes('data-insurance-risk-map')) failures.push('The insurance page is missing its destination/activity coverage map.');
if (!experiencePage.includes('data-insurance-results')) failures.push('The insurance page is missing its dynamic plan results region.');
if (!experiencePage.includes('data-package-search')) failures.push('The packages page is missing its functional composer form.');
if (!experiencePage.includes('data-package-map')) failures.push('The packages page is missing its total-trip map.');
for (const marker of ['hotel-map-column', 'package-map-column', 'insurance-map-column', 'experience-globe-column', 'data-experience-decision-map', 'data-experience-destination', 'data-experience-decision-detail']) {
  if (!experiencePage.includes(marker)) failures.push(`Map decision details or interactive experience pins are missing ${marker}.`);
}
for (const marker of ['.hotel-area-detail { position: static', '.package-map-detail { position: static', '.insurance-risk-detail { position: static', '.experience-globe-detail { position: static', '.workspace-map-detail { position: static']) {
  if (!appCss.includes(marker)) failures.push(`Decision detail cards must remain outside map paint surfaces: ${marker}.`);
}
if (!appJs.includes('initExperienceDecisionMap') || !appJs.includes('map.dataset.selectedDestination = destination')) failures.push('Experience-map buttons do not update a real selected-destination state.');
if (!savedPage.includes('data-coordinate-mode="option-orbit"') || !savedPage.includes('data-workspace-orbit-label')) failures.push('Saved options without coordinates must be disclosed as an option orbit, not a geographic map.');
if (!appCss.includes('.package-search-form,.package-route-row') || !appCss.includes('contain: inline-size paint') || !appCss.includes('direction: ltr') || !appCss.includes('direction: rtl')) failures.push('The mobile package composer is missing its RTL-safe inline overflow containment.');
if (!appCss.includes('.experience-card-grid { display: flex; direction: ltr;') || !appCss.includes('.package-journey-map { position: relative; top: auto; min-height: 0; overflow: hidden; padding: 335px 10px 10px; contain: paint;')) failures.push('The supporting content and journey map are missing mobile paint containment.');
for (const marker of [
  '[data-hotel-map-pins] { position: relative; z-index: 6; display: grid;',
  '.hotel-area-pin { position: static; width: 100%;',
  '[data-package-map-pins] { position: relative; z-index: 6; display: grid;',
  '.package-map-pin { position: static; width: 100%;',
  '.directory-map-pin { position: static; width: 100%;'
]) {
  if (!appCss.includes(marker)) failures.push(`Mobile map choices are still allowed to collide over the map: ${marker}`);
}
if (!appCss.includes('.flight-results-grid { display: flex; direction: ltr;') || !appCss.includes('.hotel-results-grid { display: flex; direction: ltr;') || !appCss.includes('.insurance-plan-grid { display: flex; direction: ltr;')) failures.push('The mobile commerce result rails are missing RTL-safe paint containment.');
if (!experiencePage.includes('data-package-results')) failures.push('The packages page is missing its dynamic comparison region.');
for (const marker of ['data-agent-entry-form', 'data-agent-workbench', 'data-agent-trip-request', 'data-agent-clarifications', 'data-agent-event-log', 'data-agent-supplier-state']) {
  if (!experiencePage.includes(marker)) failures.push(`The private AI planner is missing ${marker}.`);
}
if (!experiencePage.includes('method="post"') || !experiencePage.includes('data-agent-submit disabled')) failures.push('The agent prompt must fail closed when JavaScript is unavailable.');
if (experiencePage.includes('name="q"') && /data-agent-entry-form[\s\S]{0,1000}name="q"/.test(experiencePage)) failures.push('The agent prompt can still leak into a query-string parameter.');

const supplierInterface = readFileSync(join(themeRoot, 'inc/suppliers/interface-supplier-adapter.php'), 'utf8');
for (const method of ['get_id', 'get_verticals', 'is_configured', 'get_mode', 'get_cache_version', 'fetch']) {
  if (!supplierInterface.includes(`function ${method}(`)) failures.push(`Supplier adapter contract is missing ${method}().`);
}
const demoSupplierAdapter = readFileSync(join(themeRoot, 'inc/suppliers/class-demo-supplier-adapter.php'), 'utf8');
if (!demoSupplierAdapter.includes("'-discovery-contract-3'")) failures.push('The discovery adapter cache signature must change with the typed 1.3 exploration-hub payload.');

if (/<a\b[^>]*class="ai-input"[^>]*>[\s\S]*?<button\b/.test(frontPage)) {
  failures.push('The AI planner link contains an invalid nested button.');
}
if (!frontPage.includes('tra_vel_v2_get_home_hero_campaign') || !frontPage.includes('data-hero-campaign')) {
  failures.push('The homepage is not connected to the server-rendered campaign queue.');
}
if (!appJs.includes("document.querySelector('[data-hero-campaign]')")) {
  failures.push('The homepage campaign cannot focus its matching map destination.');
}

const heroQueuePath = join(themeRoot, 'assets/data/home-hero-queue.json');
if (!existsSync(heroQueuePath)) {
  failures.push('The homepage campaign queue is missing.');
} else {
  try {
    const heroQueue = JSON.parse(readFileSync(heroQueuePath, 'utf8'));
    const campaigns = Array.isArray(heroQueue.campaigns) ? heroQueue.campaigns : [];
    const discovery = JSON.parse(readFileSync(join(themeRoot, 'assets/data/discovery-demo.json'), 'utf8'));
    const discoveryIds = new Set((discovery.destinations || []).map(destination => destination?.id).filter(Boolean));
    const ids = new Set();
    const evergreenCampaigns = [];
    if (heroQueue.version !== 2) failures.push('The homepage campaign queue must use the typed version 2 contract.');
    if (!campaigns.length) failures.push('The homepage campaign queue is empty.');
    for (const campaign of campaigns) {
      if (!campaign || typeof campaign !== 'object') { failures.push('Every homepage campaign must be an object.'); continue; }
      if (!/^[a-z0-9-]+$/.test(campaign.id || '')) failures.push('Every homepage campaign needs a lowercase slug id.');
      if (ids.has(campaign.id)) failures.push(`Duplicate homepage campaign id ${campaign.id}.`);
      ids.add(campaign.id);
      if (!['seasonal', 'evergreen'].includes(campaign.kind)) failures.push(`${campaign.id || 'campaign'} has an unsupported campaign kind.`);
      if (campaign.kind === 'seasonal') {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(campaign.active_from || '') || !/^\d{4}-\d{2}-\d{2}$/.test(campaign.active_until || '')) failures.push(`${campaign.id || 'campaign'} has invalid seasonal dates.`);
        if ((campaign.active_from || '') > (campaign.active_until || '')) failures.push(`${campaign.id || 'campaign'} ends before it starts.`);
        if (typeof campaign.map_state !== 'string' || !discoveryIds.has(campaign.map_state)) failures.push(`${campaign.id || 'campaign'} needs a supported seasonal map_state.`);
      }
      if (campaign.kind === 'evergreen') {
        evergreenCampaigns.push(campaign);
        if ('active_from' in campaign || 'active_until' in campaign || 'map_state' in campaign) failures.push(`${campaign.id || 'campaign'} must remain date-independent and destination-neutral.`);
      }
      if (!Number.isInteger(campaign.priority)) failures.push(`${campaign.id || 'campaign'} needs an integer priority.`);
      for (const key of ['eyebrow', 'title', 'copy', 'primary_label', 'secondary_label']) {
        if (typeof campaign[key] !== 'string' || !campaign[key].trim()) failures.push(`${campaign.id || 'campaign'} is missing ${key}.`);
      }
      for (const key of ['primary_url', 'secondary_url']) {
        if (!/^\/[a-z0-9/?=&._-]*$/i.test(campaign[key] || '')) failures.push(`${campaign.id || 'campaign'} has an unsafe ${key}.`);
      }
      const publicText = [campaign.eyebrow, campaign.title, campaign.copy, campaign.primary_label, campaign.secondary_label].join(' ');
      if (/[—–]/u.test(publicText)) failures.push(`${campaign.id || 'campaign'} uses prohibited dash punctuation.`);
      if (/מפת העריכה|במחקר|בדיקת מערכת|מדריך דגל/u.test(publicText)) failures.push(`${campaign.id || 'campaign'} exposes internal project language.`);
      if (campaign.price_claim !== false) failures.push(`${campaign.id || 'campaign'} cannot make a price claim without verified inventory.`);
    }
    if (evergreenCampaigns.length !== 1 || evergreenCampaigns[0]?.id !== 'evergreen-map-discovery') failures.push('The homepage campaign queue needs exactly one canonical evergreen discovery fallback.');
  } catch (error) {
    failures.push(`The homepage campaign queue is invalid JSON: ${error.message}`);
  }
}

// Theme 1.25.0 dive store: every dive reveals the location's services below
// the globe with truthful sample pricing, a single footnote per panel, and no
// new scroll traps. These checks extend the 1.24.x contract; none replace it.
for (const marker of [
  'const NEAR_LOD_DISTANCE = 3.0;',
  'const TAP_PREVIEW_DELAY_MS = DOUBLE_TAP_WINDOW_MS;',
  'const DIVE_REGION_DISTANCE = 2.9;'
]) {
  if (!globeJs.includes(marker)) failures.push(`The dive-store globe tuning contract is missing ${marker}`);
}
if (!/function diveToScreenPoint\([\s\S]{0,700}selectScreenPoint\(clientX, clientY, 'dive'\)[\s\S]{0,400}animateTo\(/.test(globeJs)) failures.push('A discovery-globe dive must publish its selection through the shared pipeline before requesting the dive camera flight.');
if (!globeJs.includes("if (root.matches('[data-discovery-globe]')) selectScreenPoint(clientX, clientY, 'dive');")) failures.push('Dive selections must stay scoped to discovery globes; guide globes dive without publishing.');
if (!/state\.previewTimer = window\.setTimeout\(\(\) => \{ selectScreenPoint\(clientX, clientY, 'pointer'\); state\.previewTimer = 0; \}, TAP_PREVIEW_DELAY_MS\);/.test(globeJs)) failures.push('A lone free-point tap must debounce its preview through the double-tap window so a dive never fires a preview first.');
if (!/root\.addEventListener\('pointerdown', event => \{\s*noteDirectInteraction\(\);\s*cancelPendingPreview\(\);/.test(globeJs)) failures.push('A new pointer gesture must cancel the pending free-point preview before it can fire with stale coordinates.');
if (!/function focusPoint\(latitude, longitude, options = \{\}\) \{[\s\S]{0,900}clamp\(Number\(options\.distance\) \|\| DIVE_REGION_DISTANCE, 2\.25, 4\.8\)/.test(globeJs)) failures.push('The explore-the-region flight must clamp to the shared camera distance range.');
if (!globeJs.includes('Math.min(state.animation ? state.animation.toDistance : state.distance, 3.05)')) failures.push('A selection-driven focus flight must not zoom back out past an in-flight dive.');
if (/addEventListener\(\s*['"](?:wheel|mousewheel)['"]/.test(appJs)) failures.push('The dive store must never bind wheel listeners; page scrolling stays with the browser.');
for (const [template, source] of [['front-page.php', frontPage], ['page-map.php', mapPage]]) {
  for (const marker of ['data-dive-store', 'data-dive-breadcrumb', 'data-dive-back', 'data-dive-kicker', 'data-dive-title', 'data-dive-meta', 'data-dive-chips', 'data-dive-board', 'data-dive-nearby', 'data-dive-footnote', 'data-dive-live role="status" aria-live="polite" aria-atomic="true"']) {
    if (!source.includes(marker)) failures.push(`${template} is missing the dive-store surface marker ${marker}.`);
  }
  const footnoteCount = source.split('המחירים להמחשה; המחיר הסופי מאומת לפני התשלום.').length - 1;
  if (footnoteCount !== 1) failures.push(`${template} must render exactly one dive-store price footnote (found ${footnoteCount}).`);
  if (!source.includes('חזרה לעולם')) failures.push(`${template} is missing the dive-store back-to-world control.`);
}
if (mapPage.indexOf('data-dive-store') < mapPage.indexOf('class="map-status-row"') || mapPage.indexOf('data-dive-store') > mapPage.indexOf('data-map-destination-index')) failures.push('The map dive store must sit directly below the globe status row, before the destination index, never over the Earth.');
if (frontPage.indexOf('data-dive-store') < frontPage.indexOf('data-home-globe') || frontPage.indexOf('data-dive-store') > frontPage.indexOf('data-map-result')) failures.push('The homepage dive store must sit between the globe panel and the result card, never over the Earth.');
if (!appJs.includes("const diveStoreFootnoteText = 'המחירים להמחשה; המחיר הסופי מאומת לפני התשלום.';")) failures.push('The dive store is missing its canonical single price footnote text.');
if ((appJs.split('המחירים להמחשה').length - 1) !== 1) failures.push('The dive-store price disclosure must exist exactly once in the client, never per card.');
if (!appJs.includes('footnote.hidden = pricedLines === 0;')) failures.push('The dive-store footnote must appear only while at least one sample price is visible.');
for (const marker of ['function diveStoreNextState', 'function diveStorePointKind', 'function diveStoreTargetKey', 'function nearestCuratedDestinations', 'function diveBreadcrumbTrail', 'function diveBundleCard', 'function diveHubCards', 'function renderGlobeDiveStore', 'function initGlobeDiveStore', 'function diveStoreStepBack', 'function setGlobeDiveDepthAttributes', 'function syncGlobeDiveStoreRoutes']) {
  if (!appJs.includes(marker)) failures.push(`The dive-store client kernel is missing ${marker}.`);
}
if (!appJs.includes('initGlobeDiveStore();')) failures.push('The production app does not initialize the globe dive store.');
const diveDestinationLinksSource = appJs.match(/function diveDestinationServiceLinks\(data\) \{[\s\S]*?\n\}/)?.[0] || '';
for (const marker of [
  "flights: destinationPlanUrl('/flights/', { destination: airport })",
  "accommodation: destinationPlanUrl('/hotels/', { destination: airport })",
  "transfers: destinationPlanUrl('/packages/', { destination: airport, transfers: 'true' })",
  "insurance: destinationPlanUrl('/travel-insurance/', { trip_destination: data.id })",
  "scope: 'dining'",
  "scope: 'connectivity'",
  "scope: 'equipment'",
  "scope: 'activities'"
]) {
  if (!diveDestinationLinksSource.includes(marker)) failures.push(`Dive-store destination chips must reuse the exact plan-360 vertical link patterns: ${marker}.`);
}
const diveHubCardsSource = appJs.match(/function diveHubCards\(hub\) \{[\s\S]*?\n\}/)?.[0] || '';
const diveHubLinksSource = appJs.match(/function diveHubServiceLinks\(hub\) \{[\s\S]*?\n\}/)?.[0] || '';
if (/[$₪€£]\s?\d|החל מ-/.test(diveHubCardsSource)) failures.push('Hub dive cards must never invent a price before live search.');
if (!diveHubLinksSource.includes('const destination = hub.iataSearchCode || hub.id;')) failures.push('Hub dive chips must search through the hub IATA code or its stable identity.');
const diveBundleSource = appJs.match(/function diveBundleCard\(data, routes = \[\]\) \{[\s\S]*?\n\}/)?.[0] || '';
if (!diveBundleSource.includes('Number(route?.costs?.insurance)') || !diveBundleSource.includes('Math.min(...insuranceCosts)') || !diveBundleSource.includes('? {') || !diveBundleSource.includes(': null')) failures.push('The travel-kit bundle sample price must derive only from existing planning-route insurance components and fail closed without them.');
if (!appJs.includes("wrap.append(document.createTextNode('החל מ-'));") || !appJs.includes("const amount = document.createElement('bdi');") || !appJs.includes("amount.setAttribute('dir', 'ltr');")) failures.push('Every dive-store sample price must render in the החל מ- form with an LTR-isolated amount.');
if (!/const sameFocusedTarget = state\.depth >= 1 && state\.kind === kind && state\.key === key;/.test(appJs) || !appJs.includes("const depth = kind === 'map_point' ? 1 : (sameFocusedTarget ? 2 : 1);")) failures.push('The dive depth model must deepen only on a repeated same-target dive and must never open a board for an arbitrary point.');
if (!appJs.includes("window.traVelGlobe3D?.zoom?.('out', { root: globeDiveRoot });")) failures.push('Stepping back up a dive level must zoom the camera out through the existing zoom path.');
if (!/if \(inputType === 'dive'\) return;/.test(appJs)) failures.push('A dive reveal must not double-scroll through the selection rail; the dive panel owns its own reveal.');
if (!appJs.includes("section.scrollIntoView({ behavior: preferredScrollBehavior(), block: 'nearest' });")) failures.push('The dive panel must reveal its top edge with the motion-preference-aware scroll behavior.');
for (const marker of [
  '.dive-chip { flex: 0 0 auto; min-height: 44px;',
  '.dive-back { min-height: 44px;',
  '.dive-card-action { min-height: 44px;',
  '.dive-nearby-chip { min-height: 44px;',
  '.dive-chip-row { display: flex; gap: 8px; overflow-x: auto;',
  '.dive-board { display: grid; grid-template-columns: repeat(4,minmax(0,1fr));',
  '.theme-map-shell .world-canvas[data-dive-depth="1"] { min-height: 440px; }',
  '.theme-map-shell .world-canvas[data-dive-depth="2"] { min-height: 330px; }',
  '.home-globe-stack .globe-panel[data-dive-depth="1"] .globe',
  '.home-globe-stack .globe-panel[data-dive-depth="2"] .globe',
  '.globe-dive-store,.globe-dive-store * { animation: none !important; transition: none !important; }'
]) {
  if (!appCss.includes(marker)) failures.push(`The dive-store layout contract is missing ${marker}`);
}
if (!/@media \(max-width: 760px\)[\s\S]*?\.dive-board \{ grid-template-columns: 1fr; \}/.test(appCss)) failures.push('The dive-store board must become a single column on mobile.');
const diveStoreBaseRule = appCss.match(/\n\.globe-dive-store \{([^}]*)\}/)?.[1] || '';
if (!diveStoreBaseRule || /position:\s*(?:absolute|fixed|sticky)/.test(diveStoreBaseRule)) failures.push('The dive store must remain in document flow and can never cover the globe.');

// Theme 1.26.0 one-click-first: taps resolve through pickers (search dock,
// tap calendar, party stepper, destination chips, and the destination map
// plan) instead of the planner conversation. The planner stays a fully
// functional, honestly labeled secondary mode. These checks extend the
// 1.25.x contract; none replace it.
const heroActionsMarkup = frontPage.match(/<div class="hero-agent-actions">[\s\S]*?<\/div>/)?.[0] || '';
if (!heroActionsMarkup.includes('data-hero-compare') || !heroActionsMarkup.includes('href="#search"') || !heroActionsMarkup.includes('השוו טיסה ומלון')) failures.push('The hero primary CTA must open the search dock with the honest comparison label.');
if (heroActionsMarkup.indexOf('data-hero-compare') < 0 || heroActionsMarkup.indexOf('data-home-surprise') < 0 || heroActionsMarkup.indexOf('data-hero-compare') > heroActionsMarkup.indexOf('data-home-surprise')) failures.push('The hero comparison CTA must render before the surprise trigger.');
const heroCompareAnchor = heroActionsMarkup.match(/<a class="hero-compare-cta"[^>]*>/)?.[0] || '';
if (!heroCompareAnchor || heroCompareAnchor.includes('ai-planner')) failures.push('The hero primary CTA must never route into the planner conversation.');
const heroSurpriseAnchor = frontPage.match(/<a class="surprise-cta"[^>]*>/)?.[0] || '';
if (!heroSurpriseAnchor.includes('data-home-surprise') || heroSurpriseAnchor.includes('ai-planner')) failures.push('The surprise trigger must land on the dock or map, never in the planner conversation.');
if (!frontPage.includes("add_query_arg( 'destination', 'anywhere', home_url( '/' ) ) . '#search'")) failures.push('The surprise fallback must preselect the open-ended destination on the dock.');
if (!frontPage.includes('data-hero-planner-quiet') || !frontPage.includes('או פשוט תארו לנו במילים')) failures.push('The demoted planner entry must remain an honest quiet secondary link.');
const homeChipsMarkup = frontPage.match(/<div class="home-destination-chips"[\s\S]*?<\/div>/)?.[0] || '';
if (!homeChipsMarkup.includes('data-home-destination-chips') || !homeChipsMarkup.includes('data-destination-chip')) failures.push('The homepage is missing its tap-first destination chip row.');
if (!homeChipsMarkup.includes('data-chip-slug="anywhere"') || !homeChipsMarkup.includes('לא משנה לאן')) failures.push('The destination chip row is missing its honest open-ended choice.');
if (/<button(?![^>]*type="button")[^>]*data-destination-chip/.test(homeChipsMarkup)) failures.push('Destination chips must be non-submitting buttons.');
if (!frontPage.includes("array( 'budapest', 'athens', 'dubai', 'bangkok', 'tokyo', 'lisbon' )")) failures.push('The chip row must derive from the six popular discovery destinations.');
if (frontPage.indexOf('data-home-destination-chips') < 0 || frontPage.indexOf('data-home-destination-chips') > frontPage.indexOf('data-home-search data-product-kind')) failures.push('The destination chip row must sit above the search dock destination select.');
if (!frontPage.includes('.search-zone .home-destination-chips{display:none!important}')) failures.push('The JavaScript-only chip row must stay hidden for no-JavaScript travelers.');
for (const marker of ['function initHomeDestinationChips', 'function applyHomeDestinationChip', 'function syncHomeDestinationChipStates', 'initHomeDestinationChips();']) {
  if (!appJs.includes(marker)) failures.push(`Destination chips are not wired to the canonical destination select: ${marker}.`);
}
if (!appCss.includes('.home-destination-chips button { flex: 0 0 auto; min-height: 44px;') || !appCss.includes('.home-destination-chips button:focus-visible')) failures.push('Destination chips must keep 44px targets and visible keyboard focus.');
const oneClickModuleStart = appJs.indexOf('// --- One-click planning surfaces (theme 1.26.0)');
const oneClickModuleEnd = appJs.indexOf('\nfunction initTraVelV2(', oneClickModuleStart);
const oneClickModuleSource = oneClickModuleStart >= 0 && oneClickModuleEnd > oneClickModuleStart ? appJs.slice(oneClickModuleStart, oneClickModuleEnd) : '';
if (!oneClickModuleSource) failures.push('The one-click planning module is missing from the client.');
for (const marker of [
  'function tripCalendarNextRange',
  'function tripCalendarPresetRange',
  'function commitTripCalendarRange',
  'function ensureTripFlexibilityField',
  'function setTripFlexibility',
  'function setupTripDateRangePicker',
  'function initTripDateRangePickers',
  "host.setAttribute('role', 'dialog')",
  "host.setAttribute('aria-label', 'בחירת טווח תאריכים')",
  'control.readOnly = true',
  "control.setAttribute('aria-haspopup', 'dialog')",
  'new Intl.DateTimeFormat(tripCalendarLocale',
  'ArrowLeft: 1, ArrowRight: -1, ArrowUp: -7, ArrowDown: 7',
  "event.key === 'Escape'",
  'preferredScrollBehavior()',
  "const declared = String(start.getAttribute('min') || '')",
  'function tripCalendarTodayIso'
]) {
  if (!oneClickModuleSource.includes(marker)) failures.push(`The tap calendar contract is missing ${marker}`);
}
for (const preset of ['סופ״ש הקרוב', 'שבוע', 'שבועיים', 'גמיש ±3 ימים']) {
  if (!oneClickModuleSource.includes(preset)) failures.push(`The tap calendar preset ${preset} is missing.`);
}
if (/[$₪€£]\s?\d|החל מ-/.test(oneClickModuleSource)) failures.push('The one-click surfaces must never invent a price.');
for (const marker of ["field.name = 'flexibility'", "field.setAttribute('data-trip-flexibility', 'true')", 'field.disabled = !field.value']) {
  if (!oneClickModuleSource.includes(marker)) failures.push(`The flexible-dates field contract is missing ${marker}`);
}
if (!homeDiscoverySource.includes("form.querySelector('[data-trip-flexibility]')") || (homeDiscoverySource.split("url.searchParams.set('flexibility', flexibility.value)").length - 1) < 2) failures.push('Flexible dates must travel with both the comparison and open-ended handoffs, only when actually chosen.');
for (const marker of [
  'function partyStepperClamp',
  'function partyStepperSummary',
  'function renderPartyChildAges',
  'function syncPartyChildAgesField',
  'function setupPartyStepper',
  'function initPartySteppers',
  "pill.setAttribute('aria-expanded', 'false')",
  "pill.setAttribute('aria-controls', panelId)",
  "minus.setAttribute('aria-label', partyStepperStepLabels[kind].remove)",
  "plus.setAttribute('aria-label', partyStepperStepLabels[kind].add)",
  'host.hidden = total === 0;',
  'initPartySteppers();'
]) {
  if (!oneClickModuleSource.includes(marker) && !appJs.includes(marker)) failures.push(`The party stepper contract is missing ${marker}`);
}
if (!oneClickModuleSource.includes("children === 0 ? 'ללא ילדים'")) failures.push('The party pill must describe a childless party honestly.');
if (/type="hidden"[^>]*name="(?:adults|children|rooms)"/.test(frontPage) || /type="hidden"[^>]*name="(?:adults|children|rooms)"/.test(commercialExperiencePage)) failures.push('Party counts must stay on the existing visible select contract, never hidden inputs.');
for (const marker of [
  '.trip-calendar-day { min-height: 44px;',
  '.trip-calendar-presets button { min-height: 44px;',
  '.party-row-controls button { min-height: 44px; min-width: 44px;',
  '.party-pill { display: flex;',
  'label[data-party-hidden="true"] { display: none !important; }',
  '.search-dock .trip-calendar,.search-dock .party-panel { grid-column: 1 / -1; }'
]) {
  if (!appCss.includes(marker)) failures.push(`The one-click layout contract is missing ${marker}`);
}
const tripCalendarBaseRule = appCss.match(/\n\.trip-calendar \{([^}]*)\}/)?.[1] || '';
const partyPanelBaseRule = appCss.match(/\n\.party-panel \{([^}]*)\}/)?.[1] || '';
for (const [selector, rule] of [['.trip-calendar', tripCalendarBaseRule], ['.party-panel', partyPanelBaseRule]]) {
  if (!rule || /position:\s*(?:absolute|fixed|sticky)/.test(rule)) failures.push(`${selector} must remain an in-flow surface and can never overlay the dock or the Earth.`);
}
if (!/@media \(max-width: 760px\)[\s\S]*?\.trip-calendar-months \{ grid-template-columns: 1fr; \}/.test(appCss)) failures.push('The tap calendar must become a full-width single-column sheet on mobile.');
if (!/@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.trip-calendar,\.trip-calendar \*,\.party-panel,\.party-panel \*/.test(appCss)) failures.push('The tap calendar and party stepper are missing their reduced-motion path.');
for (const scope of ['activities', 'dining', 'connectivity', 'equipment']) {
  if (!diveDestinationLinksSource.includes(`destinationPlanUrl('/travel-map/', { destination: data.id, scope: '${scope}' })`)) failures.push(`The ${scope} dive chip must open the destination map plan, not the planner conversation.`);
}
if (diveDestinationLinksSource.includes("'/ai-planner/'")) failures.push('Destination dive chips must not route any vertical into the planner conversation.');
if (!appJs.includes("connectivity: 'תכננו עם המומחה'")) failures.push('The hub connectivity handoff must disclose that it opens the expert planner.');
for (const marker of ['function initMapPlanScopeFocus', 'module.open = moduleKeys.includes(module.dataset.planModule)', 'initMapPlanScopeFocus();']) {
  if (!appJs.includes(marker)) failures.push(`The map scope focus contract is missing ${marker}.`);
}
const holidayStart = frontPage.indexOf('id="holiday-planning"');
const holidayEnd = frontPage.indexOf('</section>', holidayStart);
const holidayMarkup = holidayStart >= 0 && holidayEnd > holidayStart ? frontPage.slice(holidayStart, holidayEnd) : '';
if (!holidayMarkup) failures.push('The homepage is missing the holiday planning module.');
if (holidayStart < frontPage.indexOf('id="deals"') || holidayStart > frontPage.indexOf('id="ai"')) failures.push('The holiday module must sit between the deals grid and the planner section.');
for (const holiday of ['ראש השנה', 'סוכות', 'חנוכה', 'פסח']) {
  if (!holidayMarkup.includes(`'${holiday}'`)) failures.push(`The holiday module is missing ${holiday}.`);
}
if (!holidayMarkup.includes('חופשות לפי החגים') || !holidayMarkup.includes('בדקו יעדים וזמינות לתקופת החג')) failures.push('The holiday module must keep its honest availability-check copy.');
if (!holidayMarkup.includes("add_query_arg( 'intent', $holiday_card['intent'], $map_url )")) failures.push('Holiday cards must open the travel map with an existing planning intent.');
if (/[$₪€£]\s?\d|החל מ-|\d{4}-\d{2}-\d{2}/.test(holidayMarkup)) failures.push('Holiday cards must not fabricate prices or dates.');
if (holidayMarkup.includes('ai-planner')) failures.push('Holiday cards must never route into the planner conversation.');
if (!holidayMarkup.includes('class="deal-card holiday-card"') || (holidayMarkup.match(/'intent' => '(?:smart|value|easy|romantic|family|adventure)'/g) || []).length !== 4) failures.push('The holiday module must render exactly four static holiday cards with existing planning intents.');

if (failures.length) {
  console.error('Tra-Vel V2 theme validation failed:');
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Tra-Vel V2 theme validation passed (${allFiles.length} files).`);
