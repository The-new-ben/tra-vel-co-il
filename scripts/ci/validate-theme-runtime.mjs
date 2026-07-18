import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import vm from 'node:vm';

const repoRoot = resolve(import.meta.dirname, '..', '..');
const appPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'js', 'app.js');
const cssPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'css', 'app.css');
const appSource = readFileSync(appPath, 'utf8');
const cssSource = readFileSync(cssPath, 'utf8');

class FakeClassList {
  constructor(...names) {
    this.names = new Set(names);
  }

  add(...names) {
    names.forEach(name => this.names.add(name));
  }

  remove(...names) {
    names.forEach(name => this.names.delete(name));
  }

  contains(name) {
    return this.names.has(name);
  }

  toggle(name, force) {
    const enabled = force === undefined ? !this.names.has(name) : Boolean(force);
    if (enabled) this.names.add(name);
    else this.names.delete(name);
    return enabled;
  }
}

class FakeElement {
  constructor() {
    this.dataset = {};
    this.hidden = false;
    this.classList = new FakeClassList();
    this.attributes = new Map();
    this.queries = new Map();
    this.queryLists = new Map();
    this.children = [];
    this._textContent = '';
    this.textWrites = 0;
  }

  get textContent() {
    return this._textContent;
  }

  set textContent(value) {
    this._textContent = String(value);
    this.textWrites += 1;
  }

  closest() {
    return this;
  }

  querySelector(selector) {
    return this.queries.get(selector) || null;
  }

  querySelectorAll(selector) {
    return this.queryLists.get(selector) || [];
  }

  setAttribute(name, value) {
    this.attributes.set(name, String(value));
  }

  removeAttribute(name) {
    this.attributes.delete(name);
  }

  replaceChildren(...children) {
    this.children = children;
    this._textContent = '';
  }

  append(...children) {
    this.children.push(...children);
  }
}

const mapShell = new FakeElement();
const documentQueries = new Map([['.theme-map-shell', mapShell]]);
const windowListeners = new Map();
const documentStub = {
  readyState: 'loading',
  visibilityState: 'visible',
  documentElement: { dataset: {} },
  addEventListener() {},
  querySelector(selector) { return documentQueries.get(selector) || null; },
  querySelectorAll() { return []; },
  createElement() { return new FakeElement(); }
};
const windowStub = {
  traVelV2: {},
  location: { origin: 'https://tra-vel.co.il', pathname: '/', search: '', hash: '' },
  history: { pushState() {}, replaceState() {} },
  crypto: { randomUUID: () => '11111111-2222-4333-8444-555555555555' },
  matchMedia: () => ({ matches: false }),
  addEventListener(type, callback) {
    const listeners = windowListeners.get(type) || [];
    listeners.push(callback);
    windowListeners.set(type, listeners);
  },
  setTimeout: () => 1,
  clearTimeout() {}
};
const context = vm.createContext({
  AbortController,
  CSS: { escape: value => String(value) },
  URL,
  URLSearchParams,
  console,
  document: documentStub,
  navigator: {},
  window: windowStub,
  setTimeout: windowStub.setTimeout,
  clearTimeout: windowStub.clearTimeout
});
new vm.Script(appSource, { filename: appPath }).runInContext(context);

const mapCheckpoints = Object.fromEntries(['point', 'destination', 'scopes', 'live'].map(name => {
  const checkpoint = new FakeElement();
  const detail = new FakeElement();
  checkpoint.queries.set('[data-map-checkpoint-detail]', detail);
  documentQueries.set(`[data-map-checkpoint="${name}"]`, checkpoint);
  return [name, { checkpoint, detail }];
}));
const mapProgressLive = new FakeElement();
documentQueries.set('[data-map-progress-live]', mapProgressLive);
context.setMapProgressState({ destination: 'waiting', scopes: 'confirmed', live: 'running', destinationDetail: 'Awaiting exact identification', liveDetail: 'Checking available sources' });
assert.equal(mapCheckpoints.point.checkpoint.dataset.state, 'confirmed', 'An Earth click must immediately confirm point receipt.');
assert.equal(mapCheckpoints.destination.checkpoint.dataset.state, 'waiting', 'An unidentified point must not confirm a destination.');
assert.equal(mapCheckpoints.scopes.checkpoint.dataset.state, 'confirmed', 'Opening the twelve planning scopes is a real structural milestone.');
assert.equal(mapCheckpoints.live.checkpoint.dataset.state, 'running', 'Supplier work may be indeterminate without incrementing verified progress.');
assert.equal(mapProgressLive.textWrites, 1, 'A multi-checkpoint journey transition must produce one atomic progress announcement.');
context.setMapProgressCheckpoint('live', 'confirmed', 'Current supplier data received');
assert.equal(mapCheckpoints.live.checkpoint.dataset.state, 'confirmed', 'Live progress advances only after an authoritative confirmed transition.');
assert.equal(mapCheckpoints.live.checkpoint.classList.contains('is-new'), true, 'A newly confirmed checkpoint receives one positive acknowledgement.');
context.setMapProgressCheckpoint('live', 'stale', 'Refresh required');
assert.equal(mapCheckpoints.live.checkpoint.classList.contains('is-new'), false, 'A stale state must stop positive confirmation motion.');
assert.equal(mapCheckpoints.live.detail.textContent, 'Refresh required', 'A stale state must keep a recoverable next-state explanation.');
const announcementsBeforeRepeat = mapProgressLive.textWrites;
context.setMapProgressCheckpoint('live', 'stale', 'Refresh required');
assert.equal(mapProgressLive.textWrites, announcementsBeforeRepeat, 'An unchanged checkpoint must not repeat its polite announcement.');
const weatherOnlyIsCommercialProgress = vm.runInContext(`
  discoveryFreshness = 'current';
  discoveryCacheState = 'fresh';
  discoveryLiveLayers = { deals:false, hotels:false, airports:false, airportDetails:false, weather:true, routePrices:false, routeTotal:false };
  discoveryCommercialDataIsCurrent();
`, context);
assert.equal(weatherOnlyIsCommercialProgress, false, 'Current weather alone must not confirm price and availability progress.');
const pricedRouteIsCommercialProgress = vm.runInContext(`
  discoveryLiveLayers.routePrices = true;
  discoveryCommercialDataIsCurrent();
`, context);
assert.equal(pricedRouteIsCommercialProgress, true, 'Current supplier route pricing may confirm commercial progress.');
vm.runInContext(`discoveryLiveLayers = { deals:false, hotels:false, airports:false, airportDetails:false, weather:false, routePrices:false, routeTotal:false };`, context);
context.setDiscoveryStatus('fallback', 'Previous planning data remains available');
assert.equal(mapCheckpoints.live.checkpoint.dataset.state, 'failed', 'A failed live check must stay failed even when fallback planning data remains visible.');
context.renderDiscoveryEmptyState();
for (const { checkpoint } of Object.values(mapCheckpoints)) {
  assert.equal(checkpoint.dataset.state, 'waiting', 'An empty result must clear every previous progress confirmation.');
}

const root = new FakeElement();
const runStatus = new FakeElement();
root.queries.set('[data-agent-run-state]', runStatus);
context.setAgentWorkbenchStatus(root, 'Searching verified sources', 'searching');
context.setAgentWorkbenchStatus(root, 'Searching verified sources', 'searching');
assert.equal(runStatus.textWrites, 1, 'An unchanged polling status must not rewrite its polite live region.');

const supplierState = new FakeElement();
root.queries.set('[data-agent-supplier-state]', supplierState);
vm.runInContext(`agentRuntime.events = [{event_id:'event-1', sequence:1, phase:'supplier_search', status:'running', message:'One supplier search is active'}]`, context);
context.renderAgentSupplierState(root, { status: 'searching' });
context.renderAgentSupplierState(root, { status: 'searching' });
assert.equal(supplierState.textWrites, 1, 'An unchanged supplier status must not be announced again.');

context.initMap();
assert.equal(windowListeners.get('popstate')?.length, 1, 'The production map must register its Back and Forward handler.');
context.historyObservations = [];
vm.runInContext(`
  syncDiscoveryControls = () => {};
  updatePins = () => {};
  hydrateDiscovery = () => {};
  discoveryRequestParams = () => ({});
  setActiveDestination = destination => {
    historyObservations.push({ destination, handoff: activePlanningSelectionQuery(destination) });
  };
`, context);
const dispatchHistory = (search, state) => {
  windowStub.location.search = search;
  windowListeners.get('popstate').forEach(listener => listener({ state }));
};
dispatchHistory('?destination=bangkok&intent=smart', {
  focus: 'bangkok',
  planningSelection: {
    selection_id: 'destination-bangkok-123',
    kind: 'destination',
    latitude: 13.7563,
    longitude: 100.5018,
    destination: 'bangkok'
  }
});
dispatchHistory('?destination=athens&intent=value', {
  focus: 'athens',
  planningSelection: {
    selection_id: 'destination-athens-4567',
    kind: 'destination',
    latitude: 37.9838,
    longitude: 23.7275,
    destination: 'athens'
  }
});
const historyObservations = JSON.parse(JSON.stringify(context.historyObservations));
assert.deepEqual(historyObservations, [
  {
    destination: 'bangkok',
    handoff: {
      selection_id: 'destination-bangkok-123',
      selection_kind: 'destination',
      latitude: '13.7563',
      longitude: '100.5018',
      destination: 'bangkok'
    }
  },
  {
    destination: 'athens',
    handoff: {
      selection_id: 'destination-athens-4567',
      selection_kind: 'destination',
      latitude: '37.9838',
      longitude: '23.7275',
      destination: 'athens'
    }
  }
], 'The real popstate path must keep the displayed destination and exact AI handoff coherent.');

const transportRoot = new FakeElement();
const journey = new FakeElement();
const nextAction = new FakeElement();
journey.dataset.state = 'searching';
journey.classList.add('is-advancing');
journey.traVelJourneyTimer = 8;
journey.queries.set('[data-agent-journey-next]', nextAction);
journey.queryLists.set('[data-agent-journey-step].is-current', []);
transportRoot.queries.set('[data-agent-journey]', journey);

vm.runInContext(`
  setAgentWorkbenchError = () => {};
  scheduleAgentPoll = () => {};
  agentApiRequest = async () => { throw new Error('offline'); };
  Object.assign(agentRuntime, {
    runId: 'run_runtime_test',
    lastSequence: 0,
    pollFailures: 0,
    pollInFlight: false,
    pollController: null,
    pollGeneration: 0,
    pollRunToken: ''
  });
`, context);
await context.pollAgentRun(transportRoot);
assert.equal(journey.dataset.transport, 'stale', 'A failed poll must freeze the journey at its last confirmed state.');
assert.equal(journey.classList.contains('is-advancing'), false, 'A failed poll must stop positive journey motion.');
assert.equal(journey.traVelJourneyTimer, 0, 'A failed poll must clear the positive-motion timer.');

for (const selector of [
  '.agent-journey[data-transport="stale"] .agent-scope-board li.is-running > i',
  '.agent-journey[data-transport="failed"] .agent-scope-board li.is-running > i',
  '.agent-journey[data-transport="stale"] ~ .agent-event-panel .agent-event.is-running::before',
  '.agent-journey[data-transport="failed"] ~ .agent-event-panel .agent-event.is-running::before'
]) {
  assert(cssSource.includes(selector), `Transport freeze CSS is missing: ${selector}`);
}
assert(cssSource.includes('.map-progress-checkpoints li[data-state="running"] > i'), 'Map progress needs a visible indeterminate state without fake percentage growth.');
assert(cssSource.includes('.theme-map-shell .globe-selection-point::after'), 'The exact selected Earth point needs a persistent marker with one-shot acknowledgement.');
assert(cssSource.includes('.map-progress-checkpoints li,.map-progress-checkpoints li > i,.theme-map-shell .globe-selection-point::after'), 'Map progress must preserve state while removing motion for reduced-motion users.');
assert.match(cssSource, /@media \(max-width: 760px\)[\s\S]*?\.agent-journey-head > div:first-child > span,[^{}]+\{ font-size: 11px; \}[\s\S]*?\.agent-journey-next small \{ font-size: 11px; \}/, 'Narrow-screen journey labels must remain readable.');

console.log('Tra-Vel animated journey runtime checks passed.');
