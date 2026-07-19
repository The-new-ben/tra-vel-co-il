import { readdirSync, readFileSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const themeRoot = join(repoRoot, 'theme', 'tra-vel-v2');
const guideRoot = join(repoRoot, 'content', 'guides');
const opportunityRegistryPath = join(repoRoot, 'content', 'seo', 'content-opportunity-registry.json');
const failures = [];

const themeFiles = [
  'header.php',
  'footer.php',
  'front-page.php',
  'index.php',
  'single-destination.php',
  'page-account.php',
  'page-destination.php',
  'page-directory.php',
  'page-experience.php',
  'page-map.php',
  'page-partners.php',
  'page-saved.php',
  'page-seo-opportunity.php',
  'assets/js/app.js',
  'assets/js/globe-3d.js',
  'assets/data/discovery-demo.json',
  'assets/data/flight-search-demo.json',
];

const guideFiles = readdirSync(guideRoot)
  .filter(name => name.endsWith('.he.html'))
  .map(name => join(guideRoot, name));

const publicFiles = [
  ...themeFiles.map(name => join(themeRoot, name)),
  ...guideFiles,
];

const rejectedCopy = [
  ['—', 'em dash'],
  ['–', 'en dash'],
  ['5,000+ מילים', 'word-count production metric'],
  ['5K+', 'abbreviated word-count production metric'],
  ['לפני פרסום', 'editorial publication instruction'],
  ['בקרת פרסום', 'editorial workflow heading'],
  ['תכנון אישי עם AI', 'internal AI presentation label'],
  ['תוכניות AI פרטיות', 'internal AI workspace label'],
  ['עדכון תכנון', 'internal revision label'],
  ['אין מידע מובנה לנקודה', 'dead-end map wording'],
  ['אין עדיין מידע ליעד הזה', 'dead-end coordinate announcement'],
  ['מוצג יעד לדוגמה', 'fallback implementation wording'],
  ['המידע עדיין בבדיקה', 'editorial workflow wording'],
  ['המקורות עדיין בבדיקה', 'editorial workflow wording'],
  ['טרם תועד', 'empty editorial evidence placeholder'],
  ['0 נבדקו', 'empty verification counter'],
  ['ערוץ ספק מאומת', 'internal supplier-channel wording'],
  ['נשמרת כטיוטה', 'internal draft-state wording'],
  ['נשמר כטיוטה', 'internal draft-state wording'],
  ['0/12', 'unfinished numeric progress counter'],
];

for (const file of publicFiles) {
  const source = readFileSync(file, 'utf8');
  for (const [phrase, reason] of rejectedCopy) {
    const index = source.indexOf(phrase);
    if (index === -1) continue;
    const line = source.slice(0, index).split(/\r?\n/u).length;
    failures.push(`${relative(repoRoot, file)}:${line} exposes ${reason}: ${phrase}`);
  }
}

const opportunityRegistry = JSON.parse(readFileSync(opportunityRegistryPath, 'utf8'));
const registryCopyFields = ['primaryIntent', 'conversionAction'];
let registryCopyValueCount = 0;
for (const entry of opportunityRegistry.entries || []) {
  for (const field of registryCopyFields) {
    const value = entry?.[field];
    if (typeof value !== 'string') {
      failures.push(`content/seo/content-opportunity-registry.json#${String(entry?.id || 'unknown')}.${field} must be public copy text.`);
      continue;
    }
    registryCopyValueCount += 1;
    for (const [phrase, reason] of rejectedCopy) {
      if (!value.includes(phrase)) continue;
      failures.push(`content/seo/content-opportunity-registry.json#${String(entry?.id || 'unknown')}.${field} exposes ${reason}: ${phrase}`);
    }
  }
}

const experienceSource = readFileSync(join(themeRoot, 'page-experience.php'), 'utf8');
const globeSource = readFileSync(join(themeRoot, 'assets/js/globe-3d.js'), 'utf8');
const appSource = readFileSync(join(themeRoot, 'assets/js/app.js'), 'utf8');
if (!experienceSource.includes('data-insurance-planning-boundary')) {
  failures.push('The insurance planning boundary needs a stable semantic marker.');
}
const globeSelectionContracts = [
  [/selectionKind:\s*['"]map_point['"]/u, 'identify arbitrary coordinates as map points'],
  [/planningAction:\s*['"]identify_coordinate['"]/u, 'make arbitrary coordinates actionable'],
  [/selectionKind:\s*['"]destination['"]/u, 'identify supported destinations'],
  [/planningAction:\s*['"]open_destination['"]/u, 'open supported destinations'],
  [/selectionKind:\s*['"]exploration_hub['"]/u, 'identify supported exploration hubs'],
  [/planningAction:\s*['"]open_hub['"]/u, 'open supported exploration hubs'],
  [/selectionKind:\s*resolution\.selectionKind/u, 'publish the resolved selection kind'],
  [/planningAction:\s*resolution\.planningAction/u, 'publish the resolved planning action'],
];
for (const [contractPattern, description] of globeSelectionContracts) {
  if (!contractPattern.test(globeSource)) {
    failures.push(`The globe selection flow must ${description}.`);
  }
}

const homePointCopyStart = appSource.indexOf('function renderHomePointSelection(');
const homePointCopyEnd = appSource.indexOf('\nfunction initGlobePointSelection(', homePointCopyStart);
const homePointCopySource = homePointCopyStart >= 0 && homePointCopyEnd > homePointCopyStart ? appSource.slice(homePointCopyStart, homePointCopyEnd) : '';
for (const phrase of [
  'בחרתם אזור על המפה',
  'כל שמונת חלקי החופשה מוכנים לזיהוי, התאמה ועריכה',
  'מחיר סופי לאחר בדיקה',
  'האזור נבחר. כל חלקי החופשה מוכנים לעריכה.',
]) {
  if (!homePointCopySource.includes(phrase)) failures.push(`Homepage coordinate planning is missing traveler-facing copy: ${phrase}`);
}
for (const phrase of ['אין מידע מובנה לנקודה', 'נקודה לא נתמכת', 'מצב map_point', 'מזהה בחירה']) {
  if (homePointCopySource.includes(phrase)) failures.push(`Homepage coordinate planning exposes internal or dead-end wording: ${phrase}`);
}

const forbiddenDemoIdentity = /\b(?:EL\s*AL|Wizz\s*Air|Arkia|Emirates|Amadeus|Duffel|Booking\.com|Expedia)\b/iu;

const discoveryDemo = JSON.parse(readFileSync(join(themeRoot, 'assets/data/discovery-demo.json'), 'utf8'));
if (discoveryDemo.data_mode !== 'demo') failures.push('The bundled discovery fixture must remain explicitly demo data.');
for (const [vertical, provider] of Object.entries(discoveryDemo.provider_status || {})) {
  if (provider?.connected !== false || !String(provider?.adapter || '').includes('demo_')) {
    failures.push(`${vertical} discovery provider must remain disconnected and unmistakably illustrative.`);
  }
}
for (const destination of discoveryDemo.destinations || []) {
  if (!String(destination.hotel?.name || '').includes('דוגמת')) {
    failures.push(`${destination.id || 'discovery destination'} needs an unmistakably illustrative hotel identity.`);
  }
}
for (const [destinationId, routes] of Object.entries(discoveryDemo.route_sets || {})) {
  for (const route of routes || []) {
    if (!String(route.label || '').includes('תרחיש')) {
      failures.push(`${destinationId}/${route.id || 'route'} needs an unmistakably illustrative route label.`);
    }
    if (forbiddenDemoIdentity.test(String(route.label || ''))) {
      failures.push(`${destinationId}/${route.id || 'route'} exposes a real carrier identity in demo data.`);
    }
  }
}

const flightDemo = JSON.parse(readFileSync(join(themeRoot, 'assets/data/flight-search-demo.json'), 'utf8'));
if (flightDemo.data_mode !== 'demo' || flightDemo.provider_status?.connected !== false || flightDemo.provider_status?.bookable !== false) {
  failures.push('The bundled flight fixture must remain disconnected, non-bookable demo data.');
}
for (const offer of flightDemo.offers || []) {
  const airlineName = String(offer.airline?.name || '');
  const airlineCode = String(offer.airline?.code || '');
  if (!String(offer.label || '').includes('תרחיש') || !airlineName.includes('לדוגמה') || !/^(?:D\d+|MIX)$/u.test(airlineCode)) {
    failures.push(`${offer.id || 'flight fixture'} needs illustrative route, airline name and carrier code labels.`);
  }
  if (forbiddenDemoIdentity.test(`${offer.label || ''} ${airlineName}`)) {
    failures.push(`${offer.id || 'flight fixture'} exposes a real carrier identity in demo data.`);
  }
  if (offer.booking?.bookable !== false || offer.booking?.provider !== 'demo' || offer.booking?.checkout_url !== null) {
    failures.push(`${offer.id || 'flight fixture'} must not expose checkout or a real supplier.`);
  }
}

const hotelDemo = JSON.parse(readFileSync(join(themeRoot, 'assets/data/hotel-search-demo.json'), 'utf8'));
for (const property of hotelDemo.properties || []) {
  if (!String(property.name || '').includes('דוגמת')) {
    failures.push(`${property.id || 'hotel fixture'} needs an unmistakably illustrative property name.`);
  }
}

const packageDemo = JSON.parse(readFileSync(join(themeRoot, 'assets/data/trip-package-demo.json'), 'utf8'));
for (const tripPackage of packageDemo.packages || []) {
  if (!String(tripPackage.flight?.airline || '').includes('לדוגמה')) {
    failures.push(`${tripPackage.id || 'package fixture'} needs an unmistakably illustrative airline identity.`);
  }
  if (!String(tripPackage.stay?.name || '').includes('דוגמת')) {
    failures.push(`${tripPackage.id || 'package fixture'} needs an unmistakably illustrative property identity.`);
  }
}

if (failures.length) {
  console.error('Tra-Vel public copy validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Tra-Vel public copy validation passed (${publicFiles.length} public files + ${registryCopyValueCount} registry copy values).`);
