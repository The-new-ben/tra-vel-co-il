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
  'inc/template-tags.php',
  'inc/guides.php',
  'inc/seo.php',
  'assets/css/app.css',
  'assets/js/app.js',
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

for (const path of allFiles.filter((file) => file.endsWith('.php'))) {
  const source = readFileSync(path, 'utf8');
  if (/\b(?:var_dump|print_r)\s*\(/.test(source)) failures.push(`Debug output found in ${relative(themeRoot, path)}`);
  if (!source.includes('<?php')) failures.push(`PHP opening tag missing in ${relative(themeRoot, path)}`);
}

const appCss = readFileSync(join(themeRoot, 'assets/css/app.css'), 'utf8');
if (!appCss.includes('direction: rtl')) failures.push('RTL direction is missing from the production stylesheet.');
if (!appCss.includes("../images/earth-blue-marble.jpg")) failures.push('The production globe image path is not theme-relative.');

const appJs = readFileSync(join(themeRoot, 'assets/js/app.js'), 'utf8');
if (!appJs.includes('window.traVelV2')) failures.push('The app script is not connected to localized WordPress configuration.');
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
if (!appJs.includes("routePrice = discoveryDataMode === 'live'")) failures.push('Route cards can expose non-live prices as customer inventory.');

const mapPage = readFileSync(join(themeRoot, 'page-map.php'), 'utf8');
for (const marker of ['map-view-layout', 'map-support-section', 'map-destination-panel', 'map-depth-section']) {
  if (!mapPage.includes(marker)) failures.push(`The unobstructed map architecture is missing ${marker}.`);
}
if (mapPage.includes('map-search-floating')) failures.push('The map search must not float over the globe.');
if (mapPage.includes('style="left:') || mapPage.includes('style="right:')) failures.push('Map information must not use inline overlay positioning.');
if (!appCss.includes('.theme-map-shell .route-sheet { position: static')) failures.push('Route comparison must remain below the globe in document flow.');
if (!appCss.includes('.map-mobile-controls { display: none !important; }')) failures.push('The legacy fixed mobile map bar is still allowed to cover the globe.');

const seoSource = readFileSync(join(themeRoot, 'inc/seo.php'), 'utf8');
if (!seoSource.includes('BreadcrumbList')) failures.push('Destination guides are missing breadcrumb structured data.');
if (!seoSource.includes('lastReviewed')) failures.push('Destination guide schema is missing source-review freshness.');
if (!seoSource.includes('CollectionPage') || !seoSource.includes('ItemList')) failures.push('Editorial directories are missing CollectionPage and ItemList schema.');
if (seoSource.includes("'FAQPage'") || seoSource.includes('"FAQPage"')) failures.push('Travel guides must not chase unavailable FAQ rich results.');
if (!seoSource.includes("$robots['noindex']")) failures.push('Faceted and personal routes are missing an explicit noindex policy.');

const destinationPage = readFileSync(join(themeRoot, 'page-destination.php'), 'utf8');
if (!destinationPage.includes('tra_vel_v2_render_guide_evidence')) failures.push('Destination guides do not expose their evidence and freshness status.');
if (/[$₪]\s?\d/.test(destinationPage)) failures.push('Destination templates must not hard-code demo prices that can be mistaken for live inventory.');
if (destinationPage.includes('data-map-result')) failures.push('Destination guide cards must not be overwritten by global demo discovery results.');
if (!destinationPage.includes('data-guide-map-card')) failures.push('Destination guides are missing their isolated map decision card.');

const directoryPage = readFileSync(join(themeRoot, 'page-directory.php'), 'utf8');
for (const marker of ['data-directory-root', 'data-directory-filter', 'data-directory-grid', 'directory-map-pin', 'editorial-directory.json']) {
  if (!directoryPage.includes(marker)) failures.push(`Destination directory is missing ${marker}.`);
}
if (/[$₪]\s?\d/.test(directoryPage)) failures.push('The destination directory must not hard-code commercial prices.');

try {
  const directory = JSON.parse(readFileSync(join(themeRoot, 'assets/data/editorial-directory.json'), 'utf8'));
  if (directory.version !== 1) failures.push('Editorial directory manifest version must be 1.');
  if (!Array.isArray(directory.destinations) || directory.destinations.length < 6) failures.push('Editorial directory requires at least six destination decisions.');
  const ids = directory.destinations.map((destination) => destination.id);
  if (new Set(ids).size !== ids.length) failures.push('Editorial directory destination IDs must be unique.');
  const budapest = directory.destinations.find((destination) => destination.id === 'budapest');
  if (!budapest || budapest.word_count < 5000 || budapest.source_count < 10) failures.push('Budapest directory evidence is not connected to the flagship guide gate.');
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
if (/function\s+get_items\s*\(\s*WP_REST_Request\b/.test(discoveryController)) {
  failures.push('REST controller overrides must keep the untyped WP_REST_Controller method signature for PHP 8 compatibility.');
}
if (!discoveryController.includes("'/' . $this->rest_base . '/cache'")) failures.push('The discovery cache administration route is missing.');
if (!discoveryController.includes("current_user_can( 'manage_options' )")) failures.push('Discovery cache mutation lacks a manage_options capability check.');

const flightController = readFileSync(join(themeRoot, 'inc/flights/class-flight-search-controller.php'), 'utf8');
if (/function\s+get_items\s*\(\s*WP_REST_Request\b/.test(flightController)) {
  failures.push('Flight REST controller overrides must keep the untyped WP_REST_Controller method signature for PHP 8 compatibility.');
}
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

const handoffController = readFileSync(join(themeRoot, 'inc/handoffs/class-supplier-handoff-controller.php'), 'utf8');
if (!handoffController.includes("'https' !== $scheme")) failures.push('Supplier handoffs must enforce HTTPS.');
if (!handoffController.includes("'sponsored noopener noreferrer'")) failures.push('Supplier handoffs must qualify and isolate outbound partner links.');
if (!handoffController.includes("'private, no-store, max-age=0'")) failures.push('Prepared supplier handoffs must not be cached.');
if (!handoffController.includes('allowed_hosts')) failures.push('Supplier handoffs must enforce an explicit host allowlist.');

const experiencePage = readFileSync(join(themeRoot, 'page-experience.php'), 'utf8');
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
if (!experiencePage.includes('data-package-results')) failures.push('The packages page is missing its dynamic comparison region.');

const supplierInterface = readFileSync(join(themeRoot, 'inc/suppliers/interface-supplier-adapter.php'), 'utf8');
for (const method of ['get_id', 'get_verticals', 'is_configured', 'get_mode', 'get_cache_version', 'fetch']) {
  if (!supplierInterface.includes(`function ${method}(`)) failures.push(`Supplier adapter contract is missing ${method}().`);
}

const frontPage = readFileSync(join(themeRoot, 'front-page.php'), 'utf8');
if (/<a\b[^>]*class="ai-input"[^>]*>[\s\S]*?<button\b/.test(frontPage)) {
  failures.push('The AI planner link contains an invalid nested button.');
}

if (failures.length) {
  console.error('Tra-Vel V2 theme validation failed:');
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Tra-Vel V2 theme validation passed (${allFiles.length} files).`);
