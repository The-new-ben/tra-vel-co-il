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
  'functions.php',
  'header.php',
  'footer.php',
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
  'assets/data/editorial-directory.json',
  'assets/vendor/lucide.min.js',
  'assets/images/earth-blue-marble.jpg',
  'assets/images/earth-blue-marble-2048.jpg',
  'assets/images/thailand.jpg'
];

for (const file of requiredFiles) {
  if (!existsSync(join(themeRoot, file))) failures.push(`Missing required theme file: ${file}`);
}

const style = readFileSync(join(themeRoot, 'style.css'), 'utf8');
for (const header of ['Theme Name: Tra-Vel V2', 'Version:', 'Requires at least:', 'Requires PHP:', 'Text Domain: tra-vel-v2']) {
  if (!style.includes(header)) failures.push(`style.css is missing header: ${header}`);
}

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
if (!appCss.includes("../images/earth-blue-marble.jpg")) failures.push('The production globe image path is not theme-relative.');

const appJs = readFileSync(join(themeRoot, 'assets/js/app.js'), 'utf8');
const frontPage = readFileSync(join(themeRoot, 'front-page.php'), 'utf8');
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
if (!appJs.includes('hasLiveRouteData = discoveryLiveLayers.airports')) failures.push('Route cards can expose non-live prices as customer inventory.');
if (!appJs.includes('מחיר ותנאים יאומתו בחיפוש חי')) failures.push('Non-live route tradeoffs can expose unsupported savings or conditions.');
if (!appJs.includes('resolveDiscoveryLiveLayers') || !appJs.includes('discoveryLiveLayers.deals') || !appJs.includes('discoveryLiveLayers.weather')) failures.push('Mixed supplier mode is not separated into truthful layer-level provenance.');
if (!appJs.includes('payload.field_provenance') || !appJs.includes('weather_season') || /function resolveDiscoveryLiveLayers\(providerStatus/.test(appJs)) failures.push('The map can still infer live fields from provider connection instead of server-owned field provenance.');
if (!appJs.includes('התנאים הנוכחיים עודכנו. התאמת העונה תיבדק לפי תאריך הנסיעה')) failures.push('Live current weather can still certify an editorial season recommendation.');
if (!appJs.includes("trip_destination: data.id")) failures.push('Map insurance actions do not preserve the selected destination context.');
if (!appJs.includes("activeLayer = layer && discoveryLayers.has(layer) ? layer : 'deals'") || !appJs.includes("activePlanIntent = intent && destinationPlanIntents[intent] ? intent : 'smart'") || !appJs.includes('intentConstraints')) failures.push('Map history and intent-only deep links cannot restore deterministic discovery defaults.');
if (!appJs.includes('destinationDirectoryUrl(destinationId, planningContext)') || !appJs.includes("destinationPlanUrl('/guides/', params)")) failures.push('Globe destinations without directory coverage can still open an empty filtered guide directory.');

const mapPage = readFileSync(join(themeRoot, 'page-map.php'), 'utf8');
for (const marker of ['map-view-layout', 'map-support-section', 'map-destination-panel', 'map-depth-section', 'destination-plan-360', 'data-destination-plan', 'data-plan-intent', 'data-plan-flight', 'data-plan-stay', 'data-plan-cover', 'data-plan-total', 'data-mobile-filter-host', 'data-globe-3d', 'data-globe-canvas', 'data-globe-route']) {
  if (!mapPage.includes(marker)) failures.push(`The unobstructed map architecture is missing ${marker}.`);
}
if (mapPage.includes('map-search-floating')) failures.push('The map search must not float over the globe.');
if (mapPage.includes('style="left:') || mapPage.includes('style="right:')) failures.push('Map information must not use inline overlay positioning.');
if (!appCss.includes('.theme-map-shell .route-sheet { position: static')) failures.push('Route comparison must remain below the globe in document flow.');
if (!appCss.includes('.map-mobile-controls { display: none !important; }')) failures.push('The legacy fixed mobile map bar is still allowed to cover the globe.');
if (!appCss.includes('.whatsapp-button { right: 20px !important; bottom: 20px !important; width: 58px !important;')) failures.push('The legacy WhatsApp action can still cover primary desktop content.');
if (!appCss.includes('.whatsapp-button { display: none !important; }')) failures.push('The legacy WhatsApp action can still cover mobile content and inline actions.');
if (!appCss.includes('.theme-map-shell .globe-webgl .price-pin:not(.is-active) { width: 44px; height: 44px; min-width: 44px;')) failures.push('Mobile globe destination targets must retain a 44px hit area.');
if (!appCss.includes('transform: scale(var(--globe-depth,1));')) failures.push('Globe depth must scale the visual marker without shrinking its mobile hit area.');
if (!/function bindDestinationPin[\s\S]{0,420}addEventListener\('click'[\s\S]{0,220}setActiveDestination\([\s\S]{0,220}hydrateDiscovery\(/.test(appJs)) failures.push('Globe marker clicks must synchronize the destination support panel before refreshing discovery data.');
for (const marker of ['reconcileDestinationPins', 'data-discovery-globe', 'discoveryRequestGeneration', 'AbortController', 'setRouteListBusy(true)', 'renderDiscoveryEmptyState', 'initDestinationPlan', 'updateDestinationPlan', 'mapDestinationWorkspaceItem', 'max_stops', 'max_duration', 'allow_overnight']) {
  if (!appJs.includes(marker)) failures.push(`Map discovery state is missing ${marker}.`);
}
if (!appCss.includes('@keyframes destinationPlanReveal') || !appCss.includes('@media (prefers-reduced-motion: reduce)')) failures.push('The 360-degree destination plan needs truthful progressive motion and a reduced-motion path.');
if (!appJs.includes('updateDestinationPlanStages') || !appCss.includes('@keyframes destinationStageConfirm')) failures.push('The 360-degree progress display is not connected to real layer and response state.');
if (!frontPage.includes('data-home-plan aria-busy="false"') || !appJs.includes("homePlan.setAttribute('aria-busy', String(mode === 'loading'))") || !appCss.includes('@keyframes homePlanConfirm')) failures.push('The homepage 360-degree plan must expose and animate confirmed request progress.');
if (!appCss.includes('.home-plan-360[aria-busy="true"] [data-home-plan-summary],.home-plan-360.is-updating [data-home-plan-summary]')) failures.push('The homepage progress motion does not provide a reduced-motion path.');
if (!appJs.includes('runConfirmedPlanAnimation') || !appCss.includes('.is-updating:not([aria-busy="true"])') || !appJs.includes("plan.classList.remove('is-updating')")) failures.push('Confirmed progress animation can leak into a later loading request.');
if (!appJs.includes("{ traVelMap: true, focus: activeDestination || '' }") || !appJs.includes('event.state?.focus') || !appJs.includes('{ focus: historyFocus }')) failures.push('Map Back and Forward history cannot restore an unlocked focused destination.');
if (!appJs.includes('}, 12000)') || !appJs.includes('timedOut')) failures.push('A stalled discovery request can leave animated progress and controls busy indefinitely.');
if (!appCss.includes('.home-globe-stack .globe { position: relative; inset: auto;') || !appCss.includes('touch-action: pan-y; cursor: default;') || !appCss.includes('.home-globe-stack .globe-tools { position: static;')) failures.push('The homepage globe can trap mobile scrolling or place controls over the Earth.');

const globeJs = readFileSync(join(themeRoot, 'assets/js/globe-3d.js'), 'utf8');
for (const marker of ['getContext(\'webgl\'', 'pointerdown', 'IntersectionObserver', 'ResizeObserver', 'prefers-reduced-motion', 'focusDestination', 'boxesOverlap']) {
  if (!globeJs.includes(marker)) failures.push(`The production 3D globe is missing ${marker}.`);
}
if ((globeJs.match(/state\.visible = document\.visibilityState !== 'hidden'/g) || []).length < 3) failures.push('Globe drag, keyboard and zoom input must wake event-driven rendering.');
if (!globeJs.includes('mobile && !active ? 44') || !globeJs.includes('const markerHeight = mobile ? 44 : 34')) failures.push('Globe collision geometry does not match the 44px mobile marker targets.');
if (/addEventListener\(\s*['"]focus['"][\s\S]{0,160}focusDestination/.test(globeJs)) failures.push('Globe markers must not move on focus before their pointer click can synchronize the supporting destination and route panels.');
if (/https?:\/\//.test(globeJs)) failures.push('The 3D globe must not load unapproved third-party runtime code or textures.');
const globeTextureSize = statSync(join(themeRoot, 'assets/images/earth-blue-marble-2048.jpg')).size;
if (globeTextureSize > 500000) failures.push(`The mobile globe texture is too large (${globeTextureSize} bytes).`);

const assetSource = readFileSync(join(themeRoot, 'inc/assets.php'), 'utf8');
if (!assetSource.includes("is_page_template( 'page-map.php' )") || !assetSource.includes("is_page_template( 'page-destination.php' )") || !assetSource.includes('tra-vel-v2-globe-3d')) failures.push('The WebGL globe must load on map and destination templates.');

const seoSource = readFileSync(join(themeRoot, 'inc/seo.php'), 'utf8');
if (!seoSource.includes('BreadcrumbList')) failures.push('Destination guides are missing breadcrumb structured data.');
if (!seoSource.includes('lastReviewed')) failures.push('Destination guide schema is missing source-review freshness.');
if (!seoSource.includes('CollectionPage') || !seoSource.includes('ItemList')) failures.push('Editorial directories are missing CollectionPage and ItemList schema.');
if (seoSource.includes("'FAQPage'") || seoSource.includes('"FAQPage"')) failures.push('Travel guides must not chase unavailable FAQ rich results.');
if (!seoSource.includes("$robots['noindex']")) failures.push('Faceted and personal routes are missing an explicit noindex policy.');
for (const facet of ['focus', 'layer', 'intent', 'trip', 'max_stops', 'max_duration', 'allow_overnight', 'trip_destination']) {
  if (!seoSource.includes(`'${facet}'`)) failures.push(`The SEO noindex policy is missing ${facet}.`);
}
if (!seoSource.includes("add_filter( 'document_title_parts', 'tra_vel_v2_document_title_parts' )")) failures.push('Public document titles are not protected from the legacy Europe-only site name.');
if (!seoSource.includes("add_filter( 'wpseo_title', 'tra_vel_v2_public_seo_title' )") || !seoSource.includes("add_filter( 'wpseo_schema_website', 'tra_vel_v2_enrich_yoast_website_schema' )")) failures.push('Yoast can still expose the legacy Europe-only site name.');

const destinationPage = readFileSync(join(themeRoot, 'page-destination.php'), 'utf8');
if (!destinationPage.includes('tra_vel_v2_render_guide_evidence')) failures.push('Destination guides do not expose their evidence and freshness status.');
if (/[$₪]\s?\d/.test(destinationPage)) failures.push('Destination templates must not hard-code demo prices that can be mistaken for live inventory.');
if (destinationPage.includes('data-map-result')) failures.push('Destination guide cards must not be overwritten by global demo discovery results.');
if (!destinationPage.includes('data-guide-map-card')) failures.push('Destination guides are missing their isolated map decision card.');
for (const marker of ['data-globe-3d', 'data-globe-canvas', 'data-globe-route', 'destination-globe-toolbar']) {
  if (!destinationPage.includes(marker)) failures.push(`Destination guides are missing interactive globe marker ${marker}.`);
}
if (!appCss.includes('.compact-map .map-result { position: relative;')) failures.push('Destination guide information must remain below the globe instead of covering it.');
if (/מפת העריכה|במחקר|בדיקת מערכת|מדריך דגל/u.test(destinationPage)) failures.push('Destination templates expose internal project language.');

const publicDirectoryPage = readFileSync(join(themeRoot, 'page-directory.php'), 'utf8');
if (/מפת העריכה|במחקר|בדיקת מערכת|מדריך דגל|מחירים מומצאים/u.test(publicDirectoryPage)) failures.push('Destination directories expose internal project language.');
if (/[—–]/u.test(destinationPage) || /[—–]/u.test(publicDirectoryPage)) failures.push('Public destination templates must not use em dash or en dash punctuation.');
const commercialExperiencePage = readFileSync(join(themeRoot, 'page-experience.php'), 'utf8');
if (/הגרסה הבאה|המבנה והמסע מוכנים|יחוברו ספקים/u.test(commercialExperiencePage)) failures.push('Commercial experience pages expose internal roadmap language.');
if (/החיבור בבנייה/u.test(commercialExperiencePage) || /ספק חלקי/u.test(appJs)) failures.push('Consumer comparison status exposes internal build or supplier language.');
if (!commercialExperiencePage.includes('commercial-assurance')) failures.push('Commercial experience pages are missing the assisted-sales trust boundary.');
if (!commercialExperiencePage.includes("'easy'      => 'comfort'") || !commercialExperiencePage.includes("'adventure' => 'adventure'") || !commercialExperiencePage.includes('checked( $flight_direct )') || !commercialExperiencePage.includes('$package_budget_total')) failures.push('Package planning does not preserve map intent, directness, and budget context.');
if (!commercialExperiencePage.includes('$allow_overnight') || !appJs.includes('allow_overnight: discoveryQuery.allow_overnight ? 1')) failures.push('The 360-degree AI and package handoffs drop the overnight preference.');
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

const directoryPage = readFileSync(join(themeRoot, 'page-directory.php'), 'utf8');
for (const marker of ['data-directory-root', 'data-directory-filter', 'data-directory-grid', 'directory-map-pin', 'editorial-directory.json']) {
  if (!directoryPage.includes(marker)) failures.push(`Destination directory is missing ${marker}.`);
}
if (/[$₪]\s?\d/.test(directoryPage)) failures.push('The destination directory must not hard-code commercial prices.');

try {
  const directory = JSON.parse(readFileSync(join(themeRoot, 'assets/data/editorial-directory.json'), 'utf8'));
  if (/[—–]/u.test(JSON.stringify(directory))) failures.push('Public destination directory data must not use em dash or en dash punctuation.');
  if (directory.version !== 1) failures.push('Editorial directory manifest version must be 1.');
  if (!Array.isArray(directory.destinations) || directory.destinations.length < 6) failures.push('Editorial directory requires at least six destination decisions.');
  const ids = directory.destinations.map((destination) => destination.id);
  if (new Set(ids).size !== ids.length) failures.push('Editorial directory destination IDs must be unique.');
  const budapest = directory.destinations.find((destination) => destination.id === 'budapest');
  if (!budapest || budapest.word_count < 5000 || budapest.source_count < 10) failures.push('Budapest directory evidence is not connected to the flagship guide gate.');
  const thailand = directory.destinations.find((destination) => destination.id === 'thailand');
  if (!thailand || thailand.guide_path !== '/destinations/thailand/' || thailand.word_count < 5000 || thailand.source_count < 10) failures.push('Thailand directory evidence is not connected to the flagship guide gate.');
  if (directory.destinations.some((destination) => destination.guide_status !== 'published' && destination.guide_path)) failures.push('Unreviewed directory guides must not expose a public guide path.');
} catch (error) {
  failures.push(`Editorial directory manifest is invalid JSON: ${error.message}`);
}

const guideSyncPath = join(repoRoot, 'scripts', 'wp', 'sync-guide.ps1');
if (!existsSync(guideSyncPath)) failures.push('The guarded WordPress guide synchronization pipeline is missing.');
else {
  const guideSync = readFileSync(guideSyncPath, 'utf8');
  if (!guideSync.includes('validate-guide-packets.mjs')) failures.push('Guide synchronization does not run the content quality gate first.');
  if (!guideSync.includes("packet.status -ne 'publish-ready'")) failures.push('Guide synchronization can publish content that is not publish-ready.');
  if (!guideSync.includes('SYNC TRA-VEL GUIDE')) failures.push('Guide synchronization lacks an explicit production-write confirmation.');
  if (!guideSync.includes('Import-Clixml')) failures.push('Guide synchronization is not using the encrypted credential file.');
  if (!guideSync.includes('remoteHash -ne $articleHash')) failures.push('Guide synchronization does not verify the persisted article hash.');
}

const discoveryController = readFileSync(join(themeRoot, 'inc/discovery.php'), 'utf8');
const supplierRegistry = readFileSync(join(themeRoot, 'inc/suppliers/class-supplier-registry.php'), 'utf8');
if (!discoveryController.includes("'selected_destination' => $selected_id ? $selected_id : null") || !discoveryController.includes("in_array( $selection_target, $destination_ids, true )") || !discoveryController.includes("$route['destination_id'] = $selected_id")) failures.push('Discovery routes are not bound to the resolved visible destination.');
if (!discoveryController.includes("$selection_target = $destination ? $destination : $focus") || !discoveryController.includes("'focus'") || !appJs.includes("focus: focusedDestination")) failures.push('Layer changes cannot preserve transient globe focus without hard-filtering the destination set.');
if (!/['"]focus['"][\s\S]{0,220}['"]validate_callback['"]\s*=>\s*['"]rest_validate_request_arg['"]/.test(discoveryController) || !/['"]destination['"][\s\S]{0,220}['"]validate_callback['"]\s*=>\s*['"]rest_validate_request_arg['"]/.test(discoveryController)) failures.push('Destination and transient focus REST parameters lack strict type validation.');
if (!discoveryController.includes("$field_provenance['deals']['live']") || /\$package_prices_live|\$component_prices_live/.test(discoveryController)) failures.push('Discovery budget filtering is not gated by live deal-field provenance.');
if (!supplierRegistry.includes('detect_field_provenance') || !supplierRegistry.includes("array( 'deals', 'packages' )") || !supplierRegistry.includes("'weather_season'")) failures.push('Supplier merging does not fail closed at field-level provenance boundaries.');
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
if (!savedPage.includes('data-traveler-workspace')) failures.push('The saved-trip page is missing its functional workspace root.');
if (!savedPage.includes('data-workspace-map')) failures.push('The saved-trip page is missing its interactive decision map.');
if (!savedPage.includes('data-workspace-preferences')) failures.push('The saved-trip page is missing traveler preference controls.');

const headerPage = readFileSync(join(themeRoot, 'header.php'), 'utf8');
for (const marker of ['mobile-primary-navigation', 'mobile-nav-disclosure', '/account/', '/partners/']) {
  if (!headerPage.includes(marker)) failures.push(`The navigation is missing ${marker}.`);
}
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
if (!handoffController.includes('allowed_hosts')) failures.push('Supplier handoffs must enforce an explicit host allowlist.');

const experiencePage = readFileSync(join(themeRoot, 'page-experience.php'), 'utf8');
if (!experiencePage.includes('$agent_destination') || !experiencePage.includes('$agent_intents') || !experiencePage.includes('esc_textarea( $agent_prompt )')) failures.push('Destination-to-agent handoff cannot safely prefill the private AI request.');
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
if (!appCss.includes('.experience-card-grid { display: flex; direction: ltr;') || !appCss.includes('.package-journey-map { position: relative; top: auto; min-height: 570px; contain: paint;')) failures.push('The supporting content and journey map are missing mobile paint containment.');
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
    const ids = new Set();
    if (!campaigns.length) failures.push('The homepage campaign queue is empty.');
    for (const campaign of campaigns) {
      if (!campaign || typeof campaign !== 'object') { failures.push('Every homepage campaign must be an object.'); continue; }
      if (!/^[a-z0-9-]+$/.test(campaign.id || '')) failures.push('Every homepage campaign needs a lowercase slug id.');
      if (ids.has(campaign.id)) failures.push(`Duplicate homepage campaign id ${campaign.id}.`);
      ids.add(campaign.id);
      if (!/^\d{4}-\d{2}-\d{2}$/.test(campaign.active_from || '') || !/^\d{4}-\d{2}-\d{2}$/.test(campaign.active_until || '')) failures.push(`${campaign.id || 'campaign'} has invalid active dates.`);
      if ((campaign.active_from || '') > (campaign.active_until || '')) failures.push(`${campaign.id || 'campaign'} ends before it starts.`);
      if (!Number.isInteger(campaign.priority)) failures.push(`${campaign.id || 'campaign'} needs an integer priority.`);
      for (const key of ['eyebrow', 'title', 'copy', 'primary_label', 'secondary_label', 'map_state']) {
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
    if (!ids.has('evergreen-map-discovery')) failures.push('The homepage campaign queue needs an evergreen discovery fallback.');
  } catch (error) {
    failures.push(`The homepage campaign queue is invalid JSON: ${error.message}`);
  }
}

if (failures.length) {
  console.error('Tra-Vel V2 theme validation failed:');
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Tra-Vel V2 theme validation passed (${allFiles.length} files).`);
