// Theme 1.25.0 dive-store behavioral harness.
//
// Loads the production client into a stub DOM and drives the dive store end
// to end: D1/D2 depth transitions, point-type routing, nearest-three
// computation, breadcrumb and back behavior, globe height yielding, and the
// single truthful price footnote. Run with: node scripts/ci/validate-dive-store-behavior.mjs
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import vm from 'node:vm';

const repoRoot = resolve(import.meta.dirname, '..', '..');
const appPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'js', 'app.js');
const globePath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'js', 'globe-3d.js');
const appSource = readFileSync(appPath, 'utf8');
const globeSource = readFileSync(globePath, 'utf8');
const discovery = JSON.parse(readFileSync(join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'discovery-demo.json'), 'utf8'));

class FakeClassList {
  constructor() { this.names = new Set(); }
  add(...names) { names.forEach(name => this.names.add(name)); }
  remove(...names) { names.forEach(name => this.names.delete(name)); }
  contains(name) { return this.names.has(name); }
  toggle(name, force) {
    const enabled = force === undefined ? !this.names.has(name) : Boolean(force);
    if (enabled) this.names.add(name); else this.names.delete(name);
    return enabled;
  }
}

class FakeElement {
  constructor(tagName = 'div') {
    this.tagName = String(tagName).toUpperCase();
    this.dataset = {};
    this.hidden = false;
    this.className = '';
    this.type = '';
    this.href = '';
    this.classList = new FakeClassList();
    this.attributes = new Map();
    this.queries = new Map();
    this.children = [];
    this._textContent = '';
  }
  get textContent() { return this._textContent; }
  set textContent(value) { this._textContent = String(value); this.children = []; }
  closest() { return null; }
  querySelector(selector) { return this.queries.get(selector) || null; }
  querySelectorAll() { return []; }
  addEventListener() {}
  setAttribute(name, value) { this.attributes.set(name, String(value)); }
  getAttribute(name) { return this.attributes.get(name) ?? null; }
  removeAttribute(name) { this.attributes.delete(name); }
  replaceChildren(...children) { this.children = children; this._textContent = ''; }
  append(...children) { this.children.push(...children); }
}

function collectText(node) {
  if (node === null || node === undefined) return '';
  if (typeof node === 'string') return node;
  return [node._textContent || '', ...(node.children || []).map(collectText)].join(' ');
}

function collectNodes(node, out = []) {
  if (!node || typeof node === 'string') return out;
  out.push(node);
  (node.children || []).forEach(child => collectNodes(child, out));
  return out;
}

const documentQueries = new Map();
const documentListeners = new Map();
const documentStub = {
  readyState: 'complete',
  visibilityState: 'visible',
  documentElement: { dataset: { traVelV2Ready: 'true' } },
  addEventListener(type, callback) {
    const listeners = documentListeners.get(type) || [];
    listeners.push(callback);
    documentListeners.set(type, listeners);
  },
  dispatchEvent(event) {
    (documentListeners.get(event.type) || []).forEach(callback => callback(event));
    return true;
  },
  querySelector(selector) { return documentQueries.get(selector) || null; },
  querySelectorAll(selector) { return documentQueries.has(selector) ? [documentQueries.get(selector)] : []; },
  createElement(tagName) { return new FakeElement(tagName); },
  createTextNode(value) { return String(value); }
};
const zoomCalls = [];
const focusPointCalls = [];
const windowStub = {
  traVelV2: {},
  traVelGlobe3D: {
    zoom: (direction, options) => { zoomCalls.push({ direction, options }); return true; },
    focusPoint: (latitude, longitude, options) => { focusPointCalls.push({ latitude, longitude, options }); return true; },
    focusDestination() {}, focusHub() {}, setDestinations() {}, setExplorationHubs() {}, clearSelection() {}, pulseRoute() {}
  },
  location: { origin: 'https://tra-vel.co.il', pathname: '/travel-map/', search: '', hash: '', assign() {} },
  history: { pushState() {}, replaceState() {} },
  crypto: { randomUUID: () => '11111111-2222-4333-8444-555555555555' },
  matchMedia: () => ({ matches: false }),
  localStorage: { getItem: () => null, setItem() {}, removeItem() {}, clear() {} },
  addEventListener() {},
  setTimeout: () => 1,
  clearTimeout() {}
};
const context = vm.createContext({
  AbortController,
  CSS: { escape: value => String(value) },
  URL,
  URLSearchParams,
  console,
  CustomEvent: class CustomEvent { constructor(type, options = {}) { this.type = type; this.detail = options.detail; } },
  document: documentStub,
  navigator: {},
  window: windowStub,
  setTimeout: windowStub.setTimeout,
  clearTimeout: windowStub.clearTimeout
});
new vm.Script(appSource, { filename: appPath }).runInContext(context);
const runtime = expression => JSON.parse(vm.runInContext(`JSON.stringify(${expression})`, context));

// --- Fixtures: the real discovery contract drives the geography. -----------
const demoDestinations = Object.fromEntries(discovery.destinations.map(item => [item.id, {
  id: item.id,
  city: item.city,
  country: item.country,
  latitude: item.geo.latitude,
  longitude: item.geo.longitude,
  airportCode: item.airport.code,
  airportDirect: item.airport.direct === true,
  flightDuration: `${Math.floor(item.airport.flight_minutes / 60)}:${String(item.airport.flight_minutes % 60).padStart(2, '0')}`,
  transferMinutes: item.airport.transfer_minutes,
  price: `החל מ-$${item.deal.headline_price}`,
  hotelArea: item.hotel.area,
  hotelPrice: `$${item.hotel.nightly}`,
  currency: item.deal.currency,
  planning: { modules: item.planning.modules },
  liveLayers: { deals: false, hotels: false, airports: false, airportDetails: false, weather: false, routePrices: false, routeTotal: false }
}]));
const demoHubs = Object.fromEntries(discovery.exploration_hubs.map(hub => [hub.id, {
  id: hub.id,
  city: hub.city,
  country: hub.country,
  latitude: hub.geo.latitude,
  longitude: hub.geo.longitude,
  radiusKm: hub.radius_km,
  iataSearchCode: hub.iata_search_code || '',
  liveSearchScopes: hub.live_search_scopes
}]));
context.harnessDestinations = demoDestinations;
context.harnessHubs = demoHubs;
context.harnessRoutes = discovery.route_sets;
vm.runInContext(`
  destinationData = harnessDestinations;
  explorationHubData = harnessHubs;
  homeRouteExamples = harnessRoutes;
`, context);

// --- Depth-model transition matrix ------------------------------------------
const closed = { depth: 0, kind: '', key: '' };
const transitions = [
  [closed, { type: 'dive', kind: 'destination', key: 'bangkok' }, { depth: 1, kind: 'destination', key: 'bangkok' }],
  [{ depth: 1, kind: 'destination', key: 'bangkok' }, { type: 'dive', kind: 'destination', key: 'bangkok' }, { depth: 2, kind: 'destination', key: 'bangkok' }],
  [{ depth: 2, kind: 'destination', key: 'bangkok' }, { type: 'dive', kind: 'destination', key: 'bangkok' }, { depth: 2, kind: 'destination', key: 'bangkok' }],
  [{ depth: 2, kind: 'destination', key: 'bangkok' }, { type: 'dive', kind: 'destination', key: 'lisbon' }, { depth: 1, kind: 'destination', key: 'lisbon' }],
  [{ depth: 2, kind: 'destination', key: 'bangkok' }, { type: 'dive', kind: 'exploration_hub', key: 'larnaca' }, { depth: 1, kind: 'exploration_hub', key: 'larnaca' }],
  [{ depth: 1, kind: 'exploration_hub', key: 'larnaca' }, { type: 'dive', kind: 'exploration_hub', key: 'larnaca' }, { depth: 2, kind: 'exploration_hub', key: 'larnaca' }],
  [{ depth: 1, kind: 'map_point', key: 'point:10.0:10.0' }, { type: 'dive', kind: 'map_point', key: 'point:10.0:10.0' }, { depth: 1, kind: 'map_point', key: 'point:10.0:10.0' }],
  [{ depth: 2, kind: 'destination', key: 'bangkok' }, { type: 'back' }, { depth: 1, kind: 'destination', key: 'bangkok' }],
  [{ depth: 1, kind: 'destination', key: 'bangkok' }, { type: 'back' }, { depth: 0, kind: '', key: '' }],
  [{ depth: 2, kind: 'destination', key: 'bangkok' }, { type: 'select', kind: 'destination', key: 'bangkok' }, { depth: 2, kind: 'destination', key: 'bangkok' }],
  [{ depth: 2, kind: 'destination', key: 'bangkok' }, { type: 'select', kind: 'destination', key: 'athens' }, { depth: 1, kind: 'destination', key: 'athens' }],
  [closed, { type: 'select', kind: 'destination', key: 'athens' }, { depth: 1, kind: 'destination', key: 'athens' }],
  [{ depth: 2, kind: 'destination', key: 'bangkok' }, { type: 'select', kind: 'exploration_hub', key: 'larnaca' }, { depth: 0, kind: '', key: '' }],
  [{ depth: 1, kind: 'destination', key: 'bangkok' }, { type: 'select', kind: 'map_point', key: 'point:1.0:1.0' }, { depth: 0, kind: '', key: '' }],
  [{ depth: 2, kind: 'destination', key: 'bangkok' }, { type: 'reset' }, { depth: 0, kind: '', key: '' }]
];
for (const [current, event, expected] of transitions) {
  context.harnessCurrent = current;
  context.harnessEvent = event;
  const next = runtime('diveStoreNextState(harnessCurrent, harnessEvent)');
  assert.deepEqual({ depth: next.depth, kind: next.kind, key: next.key }, expected,
    `Transition ${JSON.stringify(current)} + ${JSON.stringify(event)} must produce ${JSON.stringify(expected)}.`);
}

// --- Point-type routing ------------------------------------------------------
assert.equal(runtime(`diveStorePointKind({selectionKind:'destination', supported:true, nearestDestination:'bangkok', latitude:13.75, longitude:100.5})`), 'destination');
assert.equal(runtime(`diveStorePointKind({selectionKind:'destination', supported:false, nearestDestination:'bangkok', latitude:3, longitude:100})`), 'map_point',
  'A destination outside its supported radius must stay an honest map point.');
assert.equal(runtime(`diveStorePointKind({selectionKind:'exploration_hub', hubId:'larnaca', latitude:34.9, longitude:33.62})`), 'exploration_hub');
assert.equal(runtime(`diveStorePointKind({selectionKind:'exploration_hub', hubId:'not-a-hub', latitude:34.9, longitude:33.62})`), 'map_point');
assert.equal(runtime(`diveStorePointKind({latitude:-72, longitude:-150})`), 'map_point');
assert.equal(runtime(`diveStorePointKind({latitude:120, longitude:0})`), '', 'Out-of-range coordinates must route nowhere.');
assert.equal(runtime(`diveStoreTargetKey('map_point', {latitude:12.34, longitude:56.78})`), 'point:12.3:56.8');

// --- Nearest-three computation ----------------------------------------------
function haversineKm(a, b) {
  const rad = Math.PI / 180;
  const dLat = (b.latitude - a.latitude) * rad;
  const dLng = (b.longitude - a.longitude) * rad;
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(a.latitude * rad) * Math.cos(b.latitude * rad) * Math.sin(dLng / 2) ** 2;
  return 6371 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(Math.max(0, 1 - h)));
}
const larnacaPoint = { latitude: demoHubs.larnaca.latitude, longitude: demoHubs.larnaca.longitude };
const expectedNearest = Object.values(demoDestinations)
  .map(destination => ({ id: destination.id, distanceKm: Math.round(haversineKm(larnacaPoint, destination)) }))
  .sort((a, b) => a.distanceKm - b.distanceKm || a.id.localeCompare(b.id))
  .slice(0, 3);
context.harnessPoint = larnacaPoint;
const nearest = runtime('nearestCuratedDestinations(harnessPoint, destinationData, 3)');
assert.equal(nearest.length, 3, 'Exactly three nearest curated destinations must be offered.');
assert.deepEqual(nearest.map(entry => ({ id: entry.id, distanceKm: entry.distanceKm })), expectedNearest,
  'Nearest destinations must be ordered by great-circle distance with whole-kilometre chips.');
assert.deepEqual(runtime('nearestCuratedDestinations({latitude:999, longitude:0}, destinationData, 3)'), [],
  'Invalid coordinates must not produce nearest suggestions.');

// --- Rendered surface: chips, board, breadcrumb, footnote singleton ---------
const section = new FakeElement('section');
const parts = {};
for (const name of ['breadcrumb', 'back', 'kicker', 'title', 'meta', 'chips', 'board', 'nearby', 'footnote', 'live']) {
  parts[name] = new FakeElement(name === 'back' ? 'button' : 'div');
  section.queries.set(`[data-dive-${name}]`, parts[name]);
}
let scrollCalls = 0;
section.scrollIntoView = () => { scrollCalls += 1; };
const shell = new FakeElement('main');
shell.queries.set('[data-dive-store]', section);
const worldCanvas = new FakeElement('div');
const globeRoot = new FakeElement('div');
globeRoot.closest = selector => {
  if (selector === '[data-globe-3d][data-discovery-globe]') return globeRoot;
  if (selector === '.theme-map-shell') return shell;
  if (selector === '[data-map-canvas]') return worldCanvas;
  return null;
};
documentQueries.set('[data-dive-store]', section);
context.initGlobeDiveStore();
assert.equal(documentListeners.has('wheel'), false, 'The dive store must not bind wheel listeners.');
assert.equal(documentListeners.has('touchmove'), false, 'The dive store must not bind touchmove listeners.');

const dispatchSelect = (detail, viaDive) => documentStub.dispatchEvent({
  type: 'travelglobe:select',
  target: globeRoot,
  detail: { ...detail, inputType: viaDive ? 'dive' : 'pointer' }
});

// D1 destination dive: chip row, breadcrumb, globe yields height, panel reveals.
dispatchSelect({ selectionKind: 'destination', supported: true, nearestDestination: 'budapest', latitude: 47.4979, longitude: 19.0402 }, true);
assert.equal(section.hidden, false, 'A dive must reveal the dive store.');
assert.equal(section.dataset.diveDepth, '1');
assert.equal(worldCanvas.dataset.diveDepth, '1', 'The globe container must yield height at D1.');
assert.equal(globeRoot.dataset.diveDepth, '1');
assert.equal(scrollCalls, 1, 'The panel must scroll into view exactly once as the direct result of the dive.');
assert.equal(parts.chips.hidden, false);
assert.equal(parts.board.hidden, true, 'The board must stay closed at D1.');
assert.equal(parts.chips.children.length, 8, 'A curated destination must offer eight service chips.');
const chipText = collectText(parts.chips);
for (const label of ['טיסות', 'מלונות', 'העברות', 'פעילויות', 'אוכל', 'ביטוח', 'eSIM ותקשורת', 'ציוד']) {
  assert.ok(chipText.includes(label), `The chip row must include ${label}.`);
}
assert.ok(parts.chips.children[0].href.includes('/flights/') && parts.chips.children[0].href.includes('destination=BUD'),
  'The flights chip must reuse the exact vertical link pattern with the airport code.');
assert.ok(collectText(parts.breadcrumb).includes('עולם') && collectText(parts.breadcrumb).includes('בודפשט') && collectText(parts.breadcrumb).includes('הונגריה'),
  'The breadcrumb must read world, country, city.');
assert.equal(parts.footnote.hidden, true, 'No price is visible at D1, so the footnote must stay hidden.');

// D2 destination dive: full board, bundle card, single footnote.
dispatchSelect({ selectionKind: 'destination', supported: true, nearestDestination: 'budapest', latitude: 47.4979, longitude: 19.0402 }, true);
assert.equal(section.dataset.diveDepth, '2');
assert.equal(worldCanvas.dataset.diveDepth, '2', 'The globe must dock smaller at D2.');
assert.equal(scrollCalls, 2, 'The second dive may reveal the panel again.');
assert.equal(parts.chips.hidden, true, 'Chips expand into the board at D2.');
assert.equal(parts.board.hidden, false);
assert.equal(parts.board.children.length, 9, 'The destination board must hold eight service cards plus the pinned bundle card.');
const bundleCard = parts.board.children[8];
assert.equal(bundleCard.dataset.diveCard, 'travel-kit', 'The ninth card must be the pinned travel-kit bundle.');
const bundleText = collectText(bundleCard);
assert.ok(bundleText.includes('החל מ-'), 'The bundle sample price must use the החל מ- form.');
assert.ok(bundleText.includes('$38'), 'The Budapest bundle price must be the minimum route insurance component from the demo data.');
const boardText = collectText(parts.board);
assert.ok(boardText.includes('District V'), 'The hotels card must use the hotel area name from the discovery data.');
assert.equal(boardText.includes('להמחשה'), false, 'No card may carry its own disclaimer; the panel footnote is the only one.');
assert.equal(parts.footnote.hidden, false, 'Sample prices are visible at destination D2, so the single footnote must show.');
const boardBdis = collectNodes(parts.board).filter(node => node.tagName === 'BDI');
assert.ok(boardBdis.length >= 3 && boardBdis.every(node => node.getAttribute('dir') === 'ltr'),
  'Every board amount must be isolated in an LTR bdi.');

// Back: one level up, globe height restored, camera zoomed out via existing zoom.
documentStub.dispatchEvent({ type: 'keydown', key: 'Escape' });
assert.equal(section.dataset.diveDepth, '1', 'Escape must step back exactly one level.');
assert.equal(worldCanvas.dataset.diveDepth, '1');
assert.equal(zoomCalls.length, 1, 'Backing up must zoom the camera out through the existing zoom path.');
assert.equal(zoomCalls[0].direction, 'out');
const backTarget = new FakeElement('button');
backTarget.closest = selector => (selector === '[data-dive-back]' ? backTarget : null);
documentStub.dispatchEvent({ type: 'click', target: backTarget });
assert.equal(section.hidden, true, 'Backing out of D1 must close the store.');
assert.equal(worldCanvas.dataset.diveDepth, undefined, 'The globe height class must be fully restored at D0.');
assert.equal(zoomCalls.length, 2);

// Hub dive: four chips, then a board without prices plus nearest destinations.
dispatchSelect({ selectionKind: 'exploration_hub', hubId: 'larnaca', latitude: 34.9003, longitude: 33.6232, supported: true }, true);
assert.equal(parts.chips.children.length, 4, 'A hub offers the four core service chips.');
dispatchSelect({ selectionKind: 'exploration_hub', hubId: 'larnaca', latitude: 34.9003, longitude: 33.6232, supported: true }, true);
assert.equal(section.dataset.diveDepth, '2');
assert.equal(parts.board.children.length, 5, 'The hub board must hold the banner plus four cards.');
const hubBoardText = collectText(parts.board);
assert.ok(hubBoardText.includes('דברו עם המתכנן'), 'The hub board must carry the planner banner CTA.');
assert.equal(/[$€£₪]\d/.test(hubBoardText), false, 'The hub board must never show a price before live search.');
assert.equal(parts.footnote.hidden, true, 'Without visible prices the footnote must stay hidden on the hub board.');
assert.equal(parts.nearby.hidden, false, 'The hub board must offer the nearest curated destinations.');
const nearbyButtons = parts.nearby.children.filter(child => typeof child !== 'string' && child.dataset?.diveNearbyDestination);
assert.equal(nearbyButtons.length, 3, 'Exactly three nearest curated destinations must be offered.');
assert.deepEqual(nearbyButtons.map(button => button.dataset.diveNearbyDestination), expectedNearest.map(entry => entry.id),
  'Hub nearest suggestions must follow the great-circle ordering.');
assert.ok(collectText(parts.nearby).includes('ק"מ'), 'Nearest chips must disclose their distance in kilometres.');

// Arbitrary point: point card, explore-the-region flight, no D2 board.
dispatchSelect({ selectionKind: 'map_point', supported: false, latitude: -33.86, longitude: 151.21 }, true);
assert.equal(section.dataset.diveDepth, '1');
assert.ok(collectText(parts.kicker).includes('נקודה על הגלובוס'));
assert.ok(collectText(parts.title).includes('-33.86'), 'The point card must disclose the exact coordinates.');
dispatchSelect({ selectionKind: 'map_point', supported: false, latitude: -33.86, longitude: 151.21 }, true);
assert.equal(section.dataset.diveDepth, '1', 'A repeated dive on an arbitrary point must never open a D2 board.');
assert.equal(parts.board.hidden, true);
const exploreButton = parts.chips.children.find(child => child.dataset?.diveExplore === 'true');
assert.ok(exploreButton, 'The point card must offer the explore-the-region action.');
const exploreTarget = new FakeElement('button');
exploreTarget.dataset = exploreButton.dataset;
exploreTarget.closest = selector => (selector === '[data-dive-explore]' ? exploreTarget : null);
documentStub.dispatchEvent({ type: 'click', target: exploreTarget });
assert.equal(focusPointCalls.length, 1, 'Explore-the-region must fly the camera through the focusPoint controller.');
assert.ok(Math.abs(focusPointCalls[0].latitude - -33.86) < 0.01);
const plannerChip = parts.chips.children.find(child => typeof child !== 'string' && child.href?.includes('/ai-planner/'));
assert.ok(plannerChip && plannerChip.href.includes('scope=') && plannerChip.href.includes('mode=map_point'),
  'The point card must keep the planner handoff with the map-point context.');

// Nearest chip: swapping to a curated destination re-enters D1 for it.
const nearbyTarget = new FakeElement('button');
nearbyTarget.dataset = { diveNearbyDestination: 'athens' };
nearbyTarget.closest = selector => (selector === '[data-dive-nearby-destination]' ? nearbyTarget : null);
documentStub.dispatchEvent({ type: 'click', target: nearbyTarget });
assert.equal(section.dataset.diveDepth, '1');
assert.ok(collectText(parts.title).includes('אתונה'), 'A nearest-destination chip must swap the panel to that destination.');

// Footnote singleton: the disclosure text lives once in the client source and
// never inside a rendered card.
assert.equal(appSource.split('המחירים להמחשה').length - 1, 1, 'The price disclosure must exist exactly once in the client.');
assert.equal(runtime('diveStoreFootnoteText'), 'המחירים להמחשה; המחיר הסופי מאומת לפני התשלום.');

// Globe-side dive contract: the dive publishes one selection and the lone tap
// preview waits out the double-tap window.
assert.match(globeSource, /if \(root\.matches\('\[data-discovery-globe\]'\)\) selectScreenPoint\(clientX, clientY, 'dive'\);/);
assert.match(globeSource, /const TAP_PREVIEW_DELAY_MS = DOUBLE_TAP_WINDOW_MS;/);
assert.match(globeSource, /const NEAR_LOD_DISTANCE = 3\.0;/);
assert.doesNotMatch(globeSource, /addEventListener\(\s*['"](?:wheel|mousewheel|scroll|touchmove)['"]/);

console.log('Tra-Vel dive-store behavioral harness passed (depth model, point routing, nearest-three, breadcrumb/back, footnote singleton).');
