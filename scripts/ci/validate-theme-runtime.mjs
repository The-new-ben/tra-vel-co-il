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
    this.disabled = false;
    this.required = false;
    this.value = '';
    this.name = '';
    this.min = '';
    this.action = '';
    this.id = '';
    this.tabIndex = 0;
    this.options = [];
    this.selectedOptions = [];
    this.validationMessage = '';
    this.valid = true;
    this.reportValidityCalls = 0;
    this.focusCalls = 0;
    this.classList = new FakeClassList();
    this.attributes = new Map();
    this.queries = new Map();
    this.queryLists = new Map();
    this.listeners = new Map();
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

  addEventListener(type, callback) {
    const listeners = this.listeners.get(type) || [];
    listeners.push(callback);
    this.listeners.set(type, listeners);
  }

  dispatch(type, event = {}) {
    const value = {
      type,
      defaultPrevented: false,
      preventDefault() { this.defaultPrevented = true; },
      ...event
    };
    (this.listeners.get(type) || []).forEach(callback => callback(value));
    return value;
  }

  setAttribute(name, value) {
    this.attributes.set(name, String(value));
  }

  getAttribute(name) {
    return this.attributes.get(name) ?? null;
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

  setCustomValidity(message) {
    this.validationMessage = String(message);
  }

  checkValidity() {
    return this.valid && !this.validationMessage;
  }

  reportValidity() {
    this.reportValidityCalls += 1;
    return this.checkValidity();
  }

  focus() {
    this.focusCalls += 1;
  }
}

const mapShell = new FakeElement();
const documentQueries = new Map([['.theme-map-shell', mapShell]]);
const documentQueryLists = new Map();
const windowListeners = new Map();
const assignedLocations = [];
const documentStub = {
  readyState: 'loading',
  visibilityState: 'visible',
  documentElement: { dataset: {} },
  addEventListener() {},
  querySelector(selector) { return documentQueries.get(selector) || null; },
  querySelectorAll(selector) { return documentQueryLists.get(selector) || []; },
  createElement() { return new FakeElement(); }
};
const windowStub = {
  traVelV2: {},
  location: {
    origin: 'https://tra-vel.co.il',
    pathname: '/',
    search: '',
    hash: '',
    assign(value) { assignedLocations.push(String(value)); }
  },
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

const homeSearchContract = kind => JSON.parse(JSON.stringify(vm.runInContext(`homeSearchProductContract(${JSON.stringify(kind)})`, context)));
assert.deepEqual(homeSearchContract('flights'), {
  destination: 'destination',
  departure: 'departure_date',
  return: 'return_date',
  usesOrigin: true,
  usesRooms: false,
  destinationMode: 'code'
}, 'Flights must receive airport codes, flight dates, and origin without an irrelevant rooms parameter.');
assert.deepEqual(homeSearchContract('hotels'), {
  destination: 'destination',
  departure: 'checkin',
  return: 'checkout',
  usesOrigin: false,
  usesRooms: true,
  destinationMode: 'code'
}, 'Hotels must receive stay dates and rooms without an irrelevant flight origin.');
assert.deepEqual(homeSearchContract('insurance'), {
  destination: 'trip_destination',
  departure: 'start_date',
  return: 'end_date',
  usesOrigin: false,
  usesRooms: false,
  destinationMode: 'slug'
}, 'Insurance must receive a destination slug and coverage dates without flight-only controls.');
for (const kind of ['package', 'packages']) {
  assert.deepEqual(homeSearchContract(kind), {
    destination: 'destination',
    departure: 'departure_date',
    return: 'return_date',
    usesOrigin: true,
    usesRooms: true,
    destinationMode: 'code'
  }, `${kind} must preserve the complete origin, destination, dates, and rooms query contract.`);
}
assert.deepEqual(homeSearchContract('unsupported-product'), homeSearchContract('package'), 'Unknown homepage product state must fail safely to the complete package contract.');
assert.equal(vm.runInContext('Object.isFrozen(homeSearchProductContracts)', context), true, 'Homepage product-query contracts must remain immutable at runtime.');

function createHomeDiscoveryFixture() {
  const form = new FakeElement();
  form.dataset.productKind = 'package';
  form.dataset.mapAction = '/travel-map/';
  form.dataset.state = 'ready';
  form.action = '/packages/';
  form.setAttribute('aria-busy', 'false');

  const originWrap = new FakeElement();
  const origin = new FakeElement();
  origin.name = 'origin';
  origin.value = 'TLV';
  originWrap.queries.set('input', origin);

  const destination = new FakeElement();
  destination.name = 'destination';
  const options = [
    ['anywhere', 'anywhere'],
    ['BUD', 'budapest'],
    ['ATH', 'athens']
  ].map(([code, slug]) => {
    const option = new FakeElement();
    option.dataset.code = code;
    option.dataset.slug = slug;
    option.value = code;
    return option;
  });
  destination.options = options;
  destination.selectedOptions = [options[1]];
  destination.value = options[1].value;

  const departure = new FakeElement();
  departure.name = 'departure_date';
  departure.value = '2026-10-01';
  const returning = new FakeElement();
  returning.name = 'return_date';
  returning.value = '2026-10-08';
  const adults = new FakeElement();
  adults.name = 'adults';
  adults.value = '2';
  const children = new FakeElement();
  children.name = 'children';
  children.value = '1';
  const rooms = new FakeElement();
  rooms.name = 'rooms';
  rooms.value = '1';
  const roomsWrap = new FakeElement();
  const submit = new FakeElement();
  const submitLabel = new FakeElement();
  submit.queries.set('span', submitLabel);

  form.queries.set('[data-home-origin-wrap]', originWrap);
  form.queries.set('[data-home-origin-wrap] input', origin);
  form.queries.set('[data-home-destination]', destination);
  form.queries.set('[data-home-departure]', departure);
  form.queries.set('[data-home-return]', returning);
  form.queries.set('[data-home-adults]', adults);
  form.queries.set('[data-home-children]', children);
  form.queries.set('[data-home-rooms-wrap]', roomsWrap);
  form.queries.set('[data-home-rooms]', rooms);
  form.queries.set('[data-home-search-submit]', submit);
  form.queries.set('[data-home-search-submit] span', submitLabel);
  form.queries.set('[data-home-departure-label]', new FakeElement());
  form.queries.set('[data-home-return-label]', new FakeElement());

  const progress = new FakeElement();
  const status = new FakeElement();
  progress.queries.set('[data-home-search-status]', status);
  const steps = Object.fromEntries(['product', 'criteria', 'handoff'].map(name => {
    const step = new FakeElement();
    step.dataset.state = name === 'handoff' ? 'waiting' : 'confirmed';
    step.queries.set('small', new FakeElement());
    progress.queries.set(`[data-home-search-step="${name}"]`, step);
    return [name, step];
  }));

  const tabDefinitions = {
    package: ['/packages/', 'Package'],
    flights: ['/flights/', 'Flights'],
    hotels: ['/hotels/', 'Hotels'],
    packages: ['/packages/', 'Packages'],
    insurance: ['/travel-insurance/', 'Insurance']
  };
  const tabs = Object.fromEntries(Object.entries(tabDefinitions).map(([kind, [action, label]], index) => {
    const tab = new FakeElement();
    tab.id = `home-tab-${kind}`;
    tab.dataset.productKind = kind;
    tab.dataset.productAction = action;
    tab.dataset.departureLabel = `${label} start`;
    tab.dataset.returnLabel = `${label} end`;
    tab.dataset.submitLabel = `Compare ${label}`;
    tab.textContent = label;
    tab.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
    return [kind, tab];
  }));

  return {
    form,
    originWrap,
    origin,
    destination,
    options,
    departure,
    returning,
    adults,
    children,
    roomsWrap,
    rooms,
    submit,
    progress,
    status,
    steps,
    tabs,
    tabList: Object.values(tabs)
  };
}

const home = createHomeDiscoveryFixture();
documentQueries.set('[data-home-search]', home.form);
documentQueries.set('[data-home-search-progress]', home.progress);
documentQueryLists.set('.product-tabs [role="tab"][data-product-kind]', home.tabList);

home.returning.value = home.departure.value;
for (const kind of ['package', 'packages', 'flights', 'hotels']) {
  home.form.dataset.productKind = kind;
  assert.equal(context.homeSearchDatesAreValid(home.form), false, `${kind} must reject a same-day end date.`);
  assert.equal(home.returning.min, '2026-10-02', `${kind} must advance the minimum end date by one day.`);
}
home.form.dataset.productKind = 'insurance';
assert.equal(context.homeSearchDatesAreValid(home.form), true, 'Travel insurance may cover a valid same-day trip.');
assert.equal(home.returning.min, home.departure.value, 'Insurance must allow its end date to equal the coverage start date.');
home.returning.value = '2026-10-08';

const internalStartDate = new FakeElement();
const internalEndDate = new FakeElement();
internalStartDate.value = '2026-10-01';
internalEndDate.value = '2026-10-01';
context.syncStrictTravelEndDate(internalStartDate, internalEndDate, 7);
assert.equal(internalEndDate.min, '2026-10-02', 'Internal comparison forms must advance their end-date minimum to the next calendar day after a start-date edit.');
assert.equal(internalEndDate.value, '2026-10-08', 'An invalid internal flight end date must move to its product fallback duration.');
assert.equal(vm.runInContext("travelDateAfter('2028-02-29', 1)", context), '2028-03-01', 'Strict date edits must handle leap-day boundaries in UTC.');
assert.equal(vm.runInContext("travelDateAfter('2026-02-31', 1)", context), '', 'Strict date edits must reject impossible calendar dates.');

context.syncHomeSearchProduct(home.form, home.tabs.hotels, { announce: false, animate: false });
assert.equal(home.origin.disabled, true, 'Hotel discovery must disable the irrelevant origin control.');
assert.equal(home.form.dataset.usesOrigin, 'false', 'Hotel discovery must disclose that it does not use an origin.');
assert.equal(home.rooms.disabled, false, 'Hotel discovery must preserve the rooms control.');
assert.equal(home.roomsWrap.hidden, false, 'Hotel discovery must visibly disclose its price-affecting room count.');
assert.equal(home.form.dataset.usesRooms, 'true', 'Hotel discovery must expose that rooms are part of its query contract.');
context.syncHomeSearchProduct(home.form, home.tabs.insurance, { announce: false, animate: false });
assert.equal(home.origin.disabled, true, 'Insurance discovery must disable the irrelevant origin control.');
assert.equal(home.form.dataset.usesOrigin, 'false', 'Insurance discovery must disclose that it does not use an origin.');
assert.equal(home.rooms.disabled, true, 'Insurance discovery must disable the irrelevant rooms control.');
assert.equal(home.roomsWrap.hidden, true, 'Insurance discovery must hide the irrelevant room count.');
assert.equal(home.form.dataset.usesRooms, 'false', 'Insurance discovery must disclose that rooms are outside its query contract.');
assert.equal(home.destination.name, 'trip_destination', 'Insurance must submit its destination through the insurance context key.');
assert.equal(home.destination.options[1].value, 'budapest', 'Insurance must submit destination slugs rather than airport codes.');
context.syncHomeSearchProduct(home.form, home.tabs.package, { announce: false, animate: false });
home.rooms.value = '3';
context.updateHomeSearchCriteriaState(home.form, { announce: false, animate: false });
assert.equal(home.steps.criteria.querySelector('small').textContent.includes('3'), true, 'Homepage progress must disclose a restored room count before handoff.');
home.rooms.value = '1';

function homeNavigationQuery(kind) {
  context.syncHomeSearchProduct(home.form, home.tabs[kind], { announce: false, animate: false });
  home.destination.selectedOptions = [home.options[1]];
  home.destination.value = home.options[1].value;
  const url = context.homeSearchNavigationUrl(home.form, false);
  return { path: url.pathname, query: Object.fromEntries(url.searchParams) };
}

assert.deepEqual(homeNavigationQuery('flights'), {
  path: '/flights/',
  query: {
    origin: 'TLV',
    destination: 'BUD',
    departure_date: '2026-10-01',
    return_date: '2026-10-08',
    adults: '2',
    children: '1'
  }
}, 'Flight navigation must carry only the flight comparison contract.');
assert.deepEqual(homeNavigationQuery('hotels'), {
  path: '/hotels/',
  query: {
    destination: 'BUD',
    checkin: '2026-10-01',
    checkout: '2026-10-08',
    adults: '2',
    children: '1',
    rooms: '1'
  }
}, 'Hotel navigation must map dates to check-in and checkout without leaking an origin.');
assert.deepEqual(homeNavigationQuery('insurance'), {
  path: '/travel-insurance/',
  query: {
    trip_destination: 'budapest',
    start_date: '2026-10-01',
    end_date: '2026-10-08',
    adults: '2',
    children: '1'
  }
}, 'Insurance navigation must map destination and dates to its own context names.');
assert.deepEqual(homeNavigationQuery('package'), {
  path: '/packages/',
  query: {
    origin: 'TLV',
    destination: 'BUD',
    departure_date: '2026-10-01',
    return_date: '2026-10-08',
    adults: '2',
    children: '1',
    rooms: '1'
  }
}, 'Package navigation must preserve the complete trip-composer contract.');
assert.deepEqual(homeNavigationQuery('packages'), {
  path: '/packages/',
  query: {
    origin: 'TLV',
    destination: 'BUD',
    departure_date: '2026-10-01',
    return_date: '2026-10-08',
    adults: '2',
    children: '1',
    rooms: '1'
  }
}, 'The packages tab must preserve the same complete trip-composer contract.');

context.syncHomeSearchProduct(home.form, home.tabs.package, { announce: false, animate: false });
home.destination.selectedOptions = [home.options[0]];
home.destination.value = home.options[0].value;
const anywhereUrl = context.homeSearchNavigationUrl(home.form, true);
assert.deepEqual(Object.fromEntries(anywhereUrl.searchParams), {
  destination_mode: 'anywhere',
  product: 'package',
  origin: 'TLV',
  departure_date: '2026-10-01',
  return_date: '2026-10-08',
  adults: '2',
  children: '1',
  rooms: '1'
}, 'Open-ended discovery must preserve the usable trip context without inventing a destination.');

home.destination.selectedOptions = [home.options[1]];
home.destination.value = home.options[1].value;
context.initHomeDiscoverySearch();
home.returning.value = home.departure.value;
const invalidSubmit = home.form.dispatch('submit');
assert.equal(invalidSubmit.defaultPrevented, true, 'An invalid homepage search must not navigate.');
assert.equal(home.steps.handoff.dataset.state, 'failed', 'An invalid submit must show a failed handoff checkpoint.');
assert.equal(home.form.reportValidityCalls, 1, 'An invalid submit must expose native field validity once.');
home.returning.value = '2026-10-08';
home.form.dispatch('input');
assert.equal(home.steps.criteria.dataset.state, 'confirmed', 'Corrected trip criteria must return to a confirmed state.');
assert.equal(home.steps.handoff.dataset.state, 'waiting', 'Correcting invalid criteria must reset the failed handoff instead of leaving stale failure state.');

const validSubmit = home.form.dispatch('submit');
assert.equal(validSubmit.defaultPrevented, true, 'A valid homepage search must use the controlled progress handoff.');
assert.equal(home.form.getAttribute('aria-busy'), 'true', 'A valid submit must expose its brief navigation state.');
assert.equal(home.submit.disabled, true, 'A valid submit must prevent duplicate navigation while the handoff is running.');
assert.equal(home.steps.handoff.dataset.state, 'running', 'The handoff may animate only as page navigation, not as a completed supplier search.');
const routingStatus = home.status.textContent;
(windowListeners.get('pageshow') || []).forEach(listener => listener({ persisted: true }));
assert.equal(home.form.getAttribute('aria-busy'), 'false', 'Back and Forward restoration must clear the busy state.');
assert.equal(home.submit.disabled, false, 'Back and Forward restoration must re-enable the submit button.');
assert.equal(home.steps.handoff.dataset.state, 'waiting', 'Back and Forward restoration must return the handoff to waiting.');
assert.notEqual(home.status.textContent, routingStatus, 'Back and Forward restoration must replace the stale routing announcement.');

const frameQueue = [];
const navigationTimers = [];
const originalWindowTimeout = windowStub.setTimeout;
const originalWindowClearTimeout = windowStub.clearTimeout;
windowStub.requestAnimationFrame = callback => {
  frameQueue.push(callback);
  return frameQueue.length;
};
windowStub.setTimeout = (callback, delay = 0) => {
  const timer = { id: navigationTimers.length + 1, callback, delay, cancelled: false };
  navigationTimers.push(timer);
  return timer.id;
};
windowStub.clearTimeout = id => {
  const timer = navigationTimers.find(item => item.id === id);
  if (timer) timer.cancelled = true;
};
assignedLocations.length = 0;
context.scheduleHomeSearchNavigation(new URL('https://tra-vel.co.il/hotels/?destination=BUD'));
assert.equal(frameQueue.length, 1, 'Navigation must first yield one paint frame for the progress acknowledgement.');
assert.equal(navigationTimers.some(timer => timer.delay === 700), true, 'Navigation must retain a bounded fallback when animation frames are throttled.');
assert.equal(assignedLocations.length, 0, 'Navigation cannot run before the first paint frame.');
frameQueue.shift()();
assert.equal(frameQueue.length, 1, 'Navigation must yield a second paint frame before leaving the page.');
assert.equal(assignedLocations.length, 0, 'Navigation cannot run before the second paint frame.');
frameQueue.shift()();
assert.equal(navigationTimers.some(timer => timer.delay === 120), true, 'The double-frame acknowledgement must schedule one brief, bounded handoff delay.');
navigationTimers.find(timer => timer.delay === 120).callback();
assert.deepEqual(assignedLocations, ['https://tra-vel.co.il/hotels/?destination=BUD'], 'Navigation must run exactly once after the double requestAnimationFrame handoff.');
const fallbackNavigationTimer = navigationTimers.find(timer => timer.delay === 700);
assert.equal(fallbackNavigationTimer.cancelled, true, 'Successful double-frame navigation must cancel its fallback timer.');
fallbackNavigationTimer.callback();
assert.equal(assignedLocations.length, 1, 'The fallback timer must not cause duplicate navigation after the handoff completes.');
delete windowStub.requestAnimationFrame;
windowStub.setTimeout = originalWindowTimeout;
windowStub.clearTimeout = originalWindowClearTimeout;

const vmJson = expression => JSON.parse(vm.runInContext(`JSON.stringify(${expression})`, context));
windowStub.location.search = '?destination_mode=anywhere&product=packages123&origin=tlv&departure_date=2026-11-05&return_date=2026-11-12&adults=99&children=-2&rooms=9';
context.readDiscoveryStateFromUrl();
assert.deepEqual(vmJson('({ mode: discoveryDestinationMode, locked: discoveryDestinationLocked, active: activeDestination, trip: discoveryTripContext })'), {
  mode: 'anywhere',
  locked: false,
  active: '',
  trip: {
    product: 'packages',
    origin: 'TLV',
    departureDate: '2026-11-05',
    returnDate: '2026-11-12',
    adults: 6,
    children: 0,
    rooms: 3
  }
}, 'An open-ended map request must remain unselected while sanitizing and preserving its product, route, dates, party, and rooms context.');
assert.deepEqual(vmJson("discoveryTripContextQuery('flights')"), {
  origin: 'TLV', departure_date: '2026-11-05', return_date: '2026-11-12', adults: 6, children: 0
}, 'Map-to-flight context must use flight date names and omit rooms.');
assert.deepEqual(vmJson("discoveryTripContextQuery('hotels')"), {
  checkin: '2026-11-05', checkout: '2026-11-12', adults: 6, children: 0, rooms: 3
}, 'Map-to-hotel context must use stay date names and omit origin.');
assert.deepEqual(vmJson("discoveryTripContextQuery('insurance')"), {
  start_date: '2026-11-05', end_date: '2026-11-12', adults: 6, children: 0
}, 'Map-to-insurance context must use coverage date names and omit flight and room controls.');
assert.deepEqual(vmJson("discoveryTripContextQuery('ai')"), {
  origin: 'TLV', departure_date: '2026-11-05', return_date: '2026-11-12', adults: 6, children: 0, rooms: 3, product: 'packages'
}, 'Map-to-agent context must preserve the complete sanitized request.');

windowStub.location.search = '?destination=BUD';
context.readDiscoveryStateFromUrl();
assert.deepEqual(vmJson('({ destination: activeDestination, locked: discoveryDestinationLocked, mode: discoveryDestinationMode })'), {
  destination: 'budapest', locked: true, mode: 'recommended'
}, 'Airport-code aliases must resolve to a deterministic map destination.');
windowStub.location.search = '?selection_id=map-point-runtime-123&selection_kind=map_point&latitude=11.2233&longitude=44.5566&product=hotels&adults=4&rooms=2';
context.readDiscoveryStateFromUrl();
assert.deepEqual(vmJson('({ freePoint: activeFreePlanningPoint(), active: activeDestination, selection: activePlanningSelectionQuery(), trip: discoveryTripContext })'), {
  freePoint: true,
  active: '',
  selection: {
    selection_id: 'map-point-runtime-123',
    selection_kind: 'map_point',
    latitude: '11.2233',
    longitude: '44.5566',
    destination: ''
  },
  trip: {
    product: 'hotels',
    origin: '',
    departureDate: '',
    returnDate: '',
    adults: 4,
    children: null,
    rooms: 2
  }
}, 'A shared exact Earth point must restore its identity, coordinates, and sanitized trip context without inventing a destination.');
windowStub.location.search = '?destination_mode=anywhere&product=insurance&start_date=2026-12-04&end_date=2026-12-04';
context.readDiscoveryStateFromUrl();
assert.equal(vm.runInContext('discoveryTripContext.returnDate', context), '2026-12-04', 'Map context parsing must allow a same-day insurance policy.');
assert.deepEqual(vmJson("discoveryTripContextQuery('ai')"), {
  departure_date: '2026-12-04', return_date: '2026-12-04', product: 'insurance'
}, 'Map-to-agent handoff must preserve the same-day insurance dates exactly.');
windowStub.location.search = '?destination_mode=anywhere&product=package&departure_date=2026-12-04&return_date=2026-12-04';
context.readDiscoveryStateFromUrl();
assert.equal(vm.runInContext('discoveryTripContext.returnDate', context), '', 'Map context parsing must reject a same-day package return.');
windowStub.location.search = '';

const mapCheckpoints = Object.fromEntries(['point', 'destination', 'scopes', 'live'].map(name => {
  const checkpoint = new FakeElement();
  const detail = new FakeElement();
  checkpoint.queries.set('[data-map-checkpoint-detail]', detail);
  documentQueries.set(`[data-map-checkpoint="${name}"]`, checkpoint);
  return [name, { checkpoint, detail }];
}));
const mapProgressLive = new FakeElement();
documentQueries.set('[data-map-progress-live]', mapProgressLive);
const destinationPlan = new FakeElement();
const destinationPlanState = new FakeElement();
destinationPlan.queries.set('[data-plan-state]', destinationPlanState);
documentQueries.set('[data-destination-plan]', destinationPlan);
const homePlan = new FakeElement();
homePlan.queries.set('[data-home-plan-summary]', new FakeElement());
documentQueries.set('[data-home-plan]', homePlan);
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
context.setDiscoveryStatus('loading', 'Refreshing the available destination catalog', { planWork: false });
assert.equal(mapCheckpoints.live.checkpoint.dataset.state, 'waiting', 'Open-ended destination loading must not animate supplier or selected-plan work.');
assert.equal(destinationPlan.getAttribute('aria-busy'), 'false', 'The 360 plan must stay idle until an open-ended traveler chooses a destination.');
assert.equal(destinationPlan.dataset.requestState, 'waiting-for-destination', 'The plan must expose its truthful destination gate while the catalog refreshes.');
assert.equal(homePlan.getAttribute('aria-busy'), 'false', 'Homepage plan motion must not claim destination work for an open-ended request.');
context.setDiscoveryStatus('fallback', 'Destination catalog fallback remains available', { planWork: false });
assert.equal(mapCheckpoints.live.checkpoint.dataset.state, 'waiting', 'A catalog fallback cannot be presented as a failed supplier check when no destination was selected.');
context.setDiscoveryStatus('loading', 'Checking a selected trip');
assert.equal(mapCheckpoints.live.checkpoint.dataset.state, 'running', 'A real selected-destination refresh may use neutral working motion.');
assert.equal(destinationPlan.getAttribute('aria-busy'), 'true', 'The selected 360 plan must expose its real busy state.');
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
context.pointHistoryObservations = [];
vm.runInContext(`
  syncDiscoveryControls = () => {};
  updatePins = () => {};
  hydrateDiscovery = () => {};
  discoveryRequestParams = () => ({});
  setActiveDestination = destination => {
    historyObservations.push({ destination, handoff: activePlanningSelectionQuery(destination) });
  };
  renderUnsupportedGlobeSelection = detail => {
    pointHistoryObservations.push({ detail, handoff: activePlanningSelectionQuery() });
  };
`, context);
const dispatchHistory = (search, state) => {
  windowStub.location.search = search;
  windowListeners.get('popstate').forEach(listener => listener({ state }));
};
dispatchHistory('?intent=smart', {
  focus: '',
  planningSelection: {
    selection_id: 'map-point-history-987',
    kind: 'map_point',
    latitude: -12.3456,
    longitude: 98.7654,
    destination: ''
  }
});
assert.deepEqual(JSON.parse(JSON.stringify(context.pointHistoryObservations)), [{
  detail: {
    selectionId: 'map-point-history-987',
    latitude: -12.3456,
    longitude: 98.7654,
    inputType: 'history'
  },
  handoff: {
    selection_id: 'map-point-history-987',
    selection_kind: 'map_point',
    latitude: '-12.3456',
    longitude: '98.7654',
    destination: ''
  }
}], 'Back and Forward must restore an exact free-Earth point instead of falling through to a stale destination.');
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
  '.agent-journey[data-transport="stale"] .agent-scope-board li.is-running > :is(i,svg)',
  '.agent-journey[data-transport="failed"] .agent-scope-board li.is-running > :is(i,svg)',
  '.agent-journey[data-transport="stale"] ~ .agent-event-panel .agent-event.is-running::before',
  '.agent-journey[data-transport="failed"] ~ .agent-event-panel .agent-event.is-running::before'
]) {
  assert(cssSource.includes(selector), `Transport freeze CSS is missing: ${selector}`);
}
assert(cssSource.includes('.map-progress-checkpoints li[data-state="running"] > :is(i,svg)'), 'Map progress needs a Lucide-compatible indeterminate state without fake percentage growth.');
assert(cssSource.includes('.theme-map-shell .globe-selection-point::after'), 'The exact selected Earth point needs a persistent marker with one-shot acknowledgement.');
assert(cssSource.includes('.map-progress-checkpoints li,.map-progress-checkpoints li > :is(i,svg),.theme-map-shell .globe-selection-point::after'), 'Map progress must preserve state while removing motion for reduced-motion users.');
assert.match(cssSource, /@media \(max-width: 760px\)[\s\S]*?\.agent-journey-head > div:first-child > span,[^{}]+\{ font-size: 11px; \}[\s\S]*?\.agent-journey-next small \{ font-size: 11px; \}/, 'Narrow-screen journey labels must remain readable.');

console.log('Tra-Vel animated journey runtime checks passed.');
