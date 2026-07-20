import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import vm from 'node:vm';

const repoRoot = resolve(import.meta.dirname, '..', '..');
const appPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'js', 'app.js');
const globePath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'js', 'globe-3d.js');
const cssPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'css', 'app.css');
const frontPagePath = join(repoRoot, 'theme', 'tra-vel-v2', 'front-page.php');
const destinationPagePath = join(repoRoot, 'theme', 'tra-vel-v2', 'page-destination.php');
const mapPagePath = join(repoRoot, 'theme', 'tra-vel-v2', 'page-map.php');
const discoveryPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'discovery-demo.json');
const experiencePagePath = join(repoRoot, 'theme', 'tra-vel-v2', 'page-experience.php');
const appSource = readFileSync(appPath, 'utf8');
const globeSource = readFileSync(globePath, 'utf8');
const cssSource = readFileSync(cssPath, 'utf8');
const frontPageSource = readFileSync(frontPagePath, 'utf8');
const destinationPageSource = readFileSync(destinationPagePath, 'utf8');
const experiencePageSource = readFileSync(experiencePagePath, 'utf8');
const discoveryContractFixture = JSON.parse(readFileSync(discoveryPath, 'utf8'));
const internalPageSources = [destinationPageSource, readFileSync(mapPagePath, 'utf8'), experiencePageSource].join('\n');

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
  constructor(tagName = 'div') {
    this.tagName = String(tagName).toUpperCase();
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
    this.style = {};
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
const documentListeners = new Map();
const assignedLocations = [];
const localStorageValues = new Map();
const documentStub = {
  readyState: 'loading',
  visibilityState: 'visible',
  activeElement: null,
  documentElement: { dataset: {} },
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
  querySelectorAll(selector) { return documentQueryLists.get(selector) || []; },
  createElement(tagName) { return new FakeElement(tagName); },
  createTextNode(value) { return String(value); }
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
  localStorage: {
    getItem(key) { return localStorageValues.has(String(key)) ? localStorageValues.get(String(key)) : null; },
    setItem(key, value) { localStorageValues.set(String(key), String(value)); },
    removeItem(key) { localStorageValues.delete(String(key)); },
    clear() { localStorageValues.clear(); }
  },
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
  CustomEvent: class CustomEvent {
    constructor(type, options = {}) {
      this.type = type;
      this.detail = options.detail;
    }
  },
  document: documentStub,
  navigator: {},
  window: windowStub,
  setTimeout: windowStub.setTimeout,
  clearTimeout: windowStub.clearTimeout
});
new vm.Script(appSource, { filename: appPath }).runInContext(context);

const runtimeJson = expression => JSON.parse(vm.runInContext(`JSON.stringify(${expression})`, context));
context.explorationHubContractFixture = discoveryContractFixture.exploration_hubs;
const normalizedExplorationHubIds = runtimeJson('Object.keys(normalizeExplorationHubCollection(explorationHubContractFixture))');
assert.equal(normalizedExplorationHubIds.length, discoveryContractFixture.exploration_hubs.length, 'The complete exploration-hub contract must normalize without losing geographic coverage.');
assert.ok(normalizedExplorationHubIds.length >= 30, 'The runtime must preserve at least thirty globally distributed exploration hubs.');
for (const mutate of [
  hubs => { hubs[1].id = hubs[0].id; },
  hubs => { hubs[1].geo = {...hubs[0].geo}; },
  hubs => { hubs[0].geo.latitude = 91; },
  hubs => { hubs[0].radius_km = 39; },
  hubs => { hubs[0].iata_search_code = 'tlv'; },
  hubs => { hubs[0].price = 499; },
  hubs => { hubs[0].live_search_scopes = ['route', 'stay']; }
]) {
  const fixture = JSON.parse(JSON.stringify(discoveryContractFixture.exploration_hubs));
  mutate(fixture);
  context.invalidExplorationHubFixture = fixture;
  assert.deepEqual(runtimeJson('normalizeExplorationHubCollection(invalidExplorationHubFixture)'), {}, 'Exploration hubs must fail closed on duplicate, out-of-range, commercial, or incomplete input.');
}
delete context.invalidExplorationHubFixture;

const mapEntityDestinations = {
  bangkok: {id:'bangkok',city:'Bangkok',country:'Thailand',airportCode:'BKK',hotelArea:'Siam'},
  dubai: {id:'dubai',city:'Dubai',country:'United Arab Emirates',airportCode:'DXB',hotelArea:'Creek'}
};
const planningDealEntity = {
  id:'deal:bangkok:planning',
  kind:'deal',
  destination_id:'bangkok',
  lat:13.7563,
  lng:100.5018,
  label:'Bangkok full-trip planning option',
  summary:'A comparison-ready planning option that requires a current provider search before purchase.',
  data_mode:'demo',
  truth_state:'planning',
  freshness:'fallback',
  action:{type:'search_packages',label:'Search Bangkok packages',href:'/packages/?destination=bangkok',requires_live_search:true},
  provenance:{source:'Tra-Vel editorial planning model',observed_at:null,retrieved_at:null,reviewed_on:'2026-07-19'},
  price:{amount:950,currency:'USD',formatted:'$950',basis:'per_person_total',state:'planning',bookable:false}
};
const normalizedMapDeals = context.normalizeMapEntityCollection([planningDealEntity], 'deals', mapEntityDestinations);
assert.equal(normalizedMapDeals.length, 1, 'A valid typed deal entity must cross the map client boundary.');
assert.equal(normalizedMapDeals[0].action.href, 'https://tra-vel.co.il/packages/?destination=bangkok', 'A valid relative entity action must normalize to the current site origin.');
assert.equal(normalizedMapDeals[0].price.bookable, false, 'A planning map price must remain explicitly non-bookable.');
const planningEntityContext = context.mapEntityPlanningContext(normalizedMapDeals[0], mapEntityDestinations);
assert.deepEqual(JSON.parse(JSON.stringify(planningEntityContext)), {
  entityId: 'deal:bangkok:planning',
  selectionId: 'entity-deal-bangkok-planning',
  kind: 'deal',
  label: 'Bangkok full-trip planning option',
  destinationId: 'bangkok',
  latitude: 13.7563,
  longitude: 100.5018,
  airportCode: 'BKK',
  hotelArea: 'Siam',
  layer: 'deals',
  module: 'total',
  scope: 'flights,accommodation,transfers,activities,dining,insurance,connectivity,equipment',
  target: 'packages',
  truthState: 'planning',
  freshness: 'fallback',
  dataMode: 'demo',
  source: 'Tra-Vel editorial planning model'
}, 'A typed map entity must resolve into one stable 360-plan selection context.');
context.setMapEntityPlanningSelection(normalizedMapDeals[0], mapEntityDestinations);
const planningEntityAction = new URL(context.mapEntityActionUrl(normalizedMapDeals[0], planningEntityContext));
assert.equal(planningEntityAction.pathname, '/packages/', 'A selected deal marker must continue to package comparison.');
assert.equal(planningEntityAction.searchParams.get('destination'), 'BKK', 'Commercial map handoffs must use the destination airport code expected by product search.');
assert.equal(planningEntityAction.searchParams.get('selection_id'), 'entity-deal-bangkok-planning', 'A marker handoff must retain the stable selected-entity identity.');
assert.equal(planningEntityAction.searchParams.get('selection_kind'), 'map_point', 'A marker handoff must retain its exact geographic context.');
assert.equal(planningEntityAction.searchParams.get('selection_destination'), 'bangkok', 'A marker handoff must retain the canonical destination separately from the product airport code.');
assert.equal(planningEntityAction.searchParams.get('latitude'), '13.7563', 'A marker handoff must retain latitude.');
assert.equal(planningEntityAction.searchParams.get('longitude'), '100.5018', 'A marker handoff must retain longitude.');
vm.runInContext(`
  discoveryMapEntities = [${JSON.stringify(normalizedMapDeals[0])}];
  discoveryRoutes = [];
  discoveryRequestPending = false;
  discoveryFreshness = 'fallback';
  activeDestination = 'bangkok';
`, context);
const mapSelectionWorkspaceItem = context.mapDestinationWorkspaceItem({
  id:'bangkok',city:'Bangkok',country:'Thailand',airportCode:'BKK',hotelArea:'Siam',currency:'USD',
  price:'Planning price',total:'Planning total',totalAmount:950,url:'/destinations/thailand/'
});
assert.equal(mapSelectionWorkspaceItem.external_id, 'entity-deal-bangkok-planning', 'Saving a selected marker must use its entity identity instead of collapsing into the generic destination save.');
assert.equal(mapSelectionWorkspaceItem.price_amount, 0, 'A planning marker must not place its illustrative amount into the saved commercial price field.');
assert.equal(mapSelectionWorkspaceItem.data_mode, 'editorial', 'A planning marker must remain editorial in the saved workspace.');
assert.equal(new URL(mapSelectionWorkspaceItem.href, windowStub.location.origin).searchParams.get('selection_id'), 'entity-deal-bangkok-planning', 'A saved marker must reopen the exact selected planning point.');
vm.runInContext('activePlanningSelection = null; activeMapEntitySelection = null;', context);

const mapEntityRoot = new FakeElement('section');
const mapEntityAction = new FakeElement('a');
const mapEntityDetail = new FakeElement('article');
const mapEntitySelectionStatus = new FakeElement('p');
const mapEntitySelectionStatusText = new FakeElement('span');
mapEntitySelectionStatus.queries.set('span', mapEntitySelectionStatusText);
for (const selector of ['[data-map-entity-kind]','[data-map-entity-title]','[data-map-entity-summary]','[data-map-entity-price]','[data-map-entity-truth]','[data-map-entity-freshness]','[data-map-entity-source]']) {
  mapEntityRoot.queries.set(selector, new FakeElement());
}
mapEntityRoot.queries.set('[data-map-entity-action]', mapEntityAction);
mapEntityRoot.queries.set('[data-map-entity-detail]', mapEntityDetail);
mapEntityRoot.queries.set('[data-map-entity-selection-status]', mapEntitySelectionStatus);
documentQueries.set('[data-map-entity-explorer]', mapEntityRoot);
vm.runInContext(`
  destinationData = {bangkok:{
    id:'bangkok',city:'Bangkok',country:'Thailand',airportCode:'BKK',airport:'BKK',airportDirect:true,
    hotelArea:'Siam',hotel:'Siam',weather:'Seasonal',tags:[],latitude:13.7563,longitude:100.5018,
    liveLayers:{deals:false,hotels:false,airports:false,airportDetails:false,weather:false,routePrices:false,routeTotal:false}
  }};
  discoveryMapEntities = [${JSON.stringify(normalizedMapDeals[0])}];
  discoveryRoutes = [];
  discoverySelectedPlan = null;
  activeDestination = '';
  discoveryFreshness = 'fallback';
  discoveryDataMode = 'demo';
`, context);
assert.equal(context.selectMapEntity('deal:bangkok:planning', {
  focusGlobe:false,commitPlanning:false,syncHistory:false,hydrateDestination:false,announce:false,animatePlan:false
}), true, 'A typed marker may be previewed without committing a traveler choice.');
assert.equal(vm.runInContext('activePlanningSelection', context), null, 'Rendering or previewing the first marker must not silently choose it for the traveler.');
assert.equal(mapEntitySelectionStatus.dataset.state, 'preview', 'A passive marker preview must remain visibly distinct from an added-to-plan choice.');
assert.equal(context.selectMapEntity('deal:bangkok:planning', {
  focusGlobe:false,commitPlanning:true,syncHistory:false,hydrateDestination:false,announce:false,animatePlan:false
}), true, 'A traveler marker click must commit a 360-plan selection.');
assert.deepEqual(runtimeJson('activePlanningSelectionQuery()'), {
  selection_id:'entity-deal-bangkok-planning',selection_kind:'map_point',latitude:'13.7563',longitude:'100.5018',destination:'bangkok'
}, 'A committed marker click must atomically become the active geographic planning selection.');
assert.equal(mapEntitySelectionStatus.dataset.state, 'added', 'A committed marker click must expose an in-flow added-to-plan state.');
assert.equal(mapEntitySelectionStatusText.textContent.includes('נוסף לתוכנית'), true, 'A committed marker click must use traveler-facing added-to-plan copy.');
assert.equal(new URL(mapEntityAction.href).searchParams.get('destination'), 'BKK', 'A committed marker CTA must open the correct product destination contract.');
documentQueries.delete('[data-map-entity-explorer]');
vm.runInContext(`
  activePlanningSelection = null;
  activeMapEntitySelection = null;
  discoveryMapEntities = [];
  destinationData = {...fallbackDestinations};
  activeDestination = 'bangkok';
`, context);

const routeSegmentTruth = {data_mode:'demo',truth_state:'planning',freshness:'fallback',bookable:false};
const routeSegmentProvenance = {source:'Tra-Vel route planning model',observed_at:null,retrieved_at:null,reviewed_on:'2026-07-19'};
const planningRouteSegments = [
  {
    id:'segment:bangkok:via-dxb:2',route_id:'bangkok_via_dxb',destination_id:'bangkok',sequence:2,
    from:{code:'DXB',label:'Dubai International Airport',lat:25.2532,lng:55.3657},
    to:{code:'BKK',label:'Suvarnabhumi Airport',lat:13.69,lng:100.7501},
    truth:routeSegmentTruth,provenance:routeSegmentProvenance
  },
  {
    id:'segment:bangkok:via-dxb:1',route_id:'bangkok_via_dxb',destination_id:'bangkok',sequence:1,
    from:{code:'TLV',label:'Ben Gurion Airport',lat:32.0005,lng:34.8708},
    to:{code:'DXB',label:'Dubai International Airport',lat:25.2532,lng:55.3657},
    truth:routeSegmentTruth,provenance:routeSegmentProvenance
  }
];
const normalizedRouteSegments = context.normalizeMapSegmentCollection(planningRouteSegments, mapEntityDestinations);
assert.equal(normalizedRouteSegments.length, 2, 'A valid two-segment planning route must cross the map client boundary.');
assert.deepEqual(Array.from(normalizedRouteSegments, segment => segment.sequence), [1, 2], 'Typed map route segments must be rendered in travel sequence.');
assert.deepEqual(Array.from(normalizedRouteSegments, segment => segment.from.code), ['TLV', 'DXB'], 'The two-segment fixture must retain its airport chain.');

const cloneMapFixture = value => JSON.parse(JSON.stringify(value));
const crossOriginMapEntity = cloneMapFixture(planningDealEntity);
crossOriginMapEntity.action.href = 'https://malicious.example/checkout';
assert.equal(context.normalizeMapEntityCollection([crossOriginMapEntity], 'deals', mapEntityDestinations).length, 0, 'A cross-origin map action must fail closed.');
const wrongLayerMapEntity = cloneMapFixture(planningDealEntity);
wrongLayerMapEntity.kind = 'hotel_area';
wrongLayerMapEntity.action = {type:'search_hotels',label:'Search hotels',href:'/hotels/?destination=bangkok',requires_live_search:true};
wrongLayerMapEntity.price.basis = 'per_night';
assert.equal(context.normalizeMapEntityCollection([wrongLayerMapEntity], 'deals', mapEntityDestinations).length, 0, 'An entity kind that does not match the requested layer must fail closed.');
const invalidCoordinateMapEntity = cloneMapFixture(planningDealEntity);
invalidCoordinateMapEntity.lat = 91;
assert.equal(context.normalizeMapEntityCollection([invalidCoordinateMapEntity], 'deals', mapEntityDestinations).length, 0, 'An entity outside geographic coordinate bounds must fail closed.');
const bookablePlanningMapEntity = cloneMapFixture(planningDealEntity);
bookablePlanningMapEntity.price.bookable = true;
assert.equal(context.normalizeMapEntityCollection([bookablePlanningMapEntity], 'deals', mapEntityDestinations).length, 0, 'A planning price that claims bookability must fail closed.');
assert.equal(context.normalizeMapEntityCollection([planningDealEntity, cloneMapFixture(planningDealEntity)], 'deals', mapEntityDestinations).length, 1, 'A duplicate map entity id must be rejected instead of creating a second selectable result.');

const malformedMapSegment = cloneMapFixture(planningRouteSegments[0]);
malformedMapSegment.sequence = 0;
assert.equal(context.normalizeMapSegmentCollection([malformedMapSegment], mapEntityDestinations).length, 0, 'A malformed map segment must fail closed.');
const unknownDestinationMapSegment = cloneMapFixture(planningRouteSegments[0]);
unknownDestinationMapSegment.destination_id = 'unknown-destination';
assert.equal(context.normalizeMapSegmentCollection([unknownDestinationMapSegment], mapEntityDestinations).length, 0, 'A segment for an unknown destination must fail closed.');
assert.equal(context.normalizeMapSegmentCollection([planningRouteSegments[0], cloneMapFixture(planningRouteSegments[0])], mapEntityDestinations).length, 1, 'A duplicate map segment id must be rejected instead of drawing the route twice.');

const finalPriceBoundary = 'המחיר, הזמינות והתנאים מאומתים לפני התשלום.';
assert.equal(context.mapEntityTruthCopy({truth_state:'planning'}), `מידע לתכנון ולהשוואה. ${finalPriceBoundary}`, 'Planning map truth copy must retain the exact final-price, availability, and terms boundary.');
assert.equal(context.mapEntityTruthCopy({truth_state:'supplier_snapshot'}), 'נתון ספק מהבדיקה האחרונה. המחיר, הזמינות והתנאים יאומתו שוב לפני אישור.', 'Supplier-snapshot map copy must require revalidation instead of claiming a final price.');
assert.equal(context.mapEntityTruthCopy({truth_state:'last_observed'}), 'זהו הנתון האחרון שנצפה ואינו הצעה נוכחית. נדרשת בדיקה מחדש לפני החלטה.', 'Last-observed map copy must not be mistaken for a current offer.');
assert.equal(context.mapEntityPriceCopy(normalizedMapDeals[0]), '$950 · לאדם, לכל התכנון · מחיר לתכנון', 'A planning amount must remain useful while clearly labeled as planning.');
assert.equal(context.mapEntityPriceCopy({price:{formatted:'$1,205',basis:'per_person_total',state:'supplier_snapshot'}}), '$1,205 · לאדם, לכל התכנון · צילום מחיר ספק', 'A supplier snapshot amount must name its supplier-snapshot state.');
assert.equal(context.mapEntityPriceCopy({price:{formatted:'$1,180',basis:'per_person_total',state:'last_observed'}}), '$1,180 · לאדם, לכל התכנון · מחיר אחרון שנצפה', 'A last-observed amount must name its non-current state.');
assert.equal(context.mapEntityPriceCopy({price:null}), 'מחיר ייבדק לפי התאריכים, הנוסעים והתנאים שבחרתם.', 'An entity without a typed amount must ask for a contextual price check instead of looking unfinished.');

windowStub.traVelV2 = {workspaceUrl: 'https://tra-vel.co.il/wp-json/tra-vel/v1/workspace', isLoggedIn: true, nonce: 'runtime-nonce'};
context.fetch = async () => ({
  ok: false,
  status: 409,
  async json() { return {code: 'tra_vel_workspace_sync_capacity', message: 'Workspace is full.'}; }
});
const capacityError = await context.workspaceRequest('/sync', {method: 'PUT', body: '{}'}).catch(error => error);
assert.equal(capacityError.status, 409, 'Workspace REST failures must preserve the HTTP status.');
assert.equal(capacityError.code, 'tra_vel_workspace_sync_capacity', 'Workspace REST failures must preserve the WordPress error code.');
assert.equal(context.workspaceCapacityError(capacityError), true, 'A full 50-item account must be classified as capacity, not as a transient retry.');
windowStub.traVelV2 = {};
delete context.fetch;
const originalWindowSetTimeout = windowStub.setTimeout;
windowStub.setTimeout = callback => { queueMicrotask(callback); return 77; };
const deadlineError = await context.requestWithDeadline(() => new Promise(() => {}), 'runtime_deadline').catch(error => error);
assert.equal(deadlineError.code, 'runtime_deadline', 'The shared request deadline must reject an unresolved operation with its typed timeout code.');
assert.equal(deadlineError.timedOut, true, 'The shared request deadline must distinguish timeout from an ordinary transport failure.');
const deadlineAgentRequestOriginal = context.agentApiRequest;
context.__deadlineAgentRequest = async () => new Promise(() => {});
vm.runInContext('agentApiRequest = globalThis.__deadlineAgentRequest', context);
const deadlineHandoffButton = new FakeElement('button');
const handoffDeadlineError = await context.requestQuoteCaseHandoff({case_id:'deadline-case',version:2}, deadlineHandoffButton).catch(error => error);
assert.equal(handoffDeadlineError.code, 'quote_case_handoff_timeout', 'QuoteCase handoff must use the shared 15-second deadline.');
assert.equal(Boolean(deadlineHandoffButton.dataset.idempotencyKey), true, 'An ambiguous handoff timeout must retain the original idempotency key.');
context.__deadlineAgentRequest = deadlineAgentRequestOriginal;
vm.runInContext('agentApiRequest = globalThis.__deadlineAgentRequest', context);
delete context.__deadlineAgentRequest;
windowStub.setTimeout = originalWindowSetTimeout;
const demoCommercialPayload = {meta:{data_mode:'demo'}};
const mixedCommercialPayload = {meta:{data_mode:'mixed'}};
const liveCommercialPayload = {meta:{data_mode:'live',cache_state:'fresh'}};
const staleLiveCommercialPayload = {meta:{data_mode:'live',cache_state:'stale_refreshing'}};
assert.equal(context.commercialDataMode({meta:{data_mode:'unexpected'}}), 'demo', 'Unknown commercial provenance must fail closed to planning mode.');
assert.equal(context.commercialSellerReady(demoCommercialPayload, {provider:'connected-provider',bookable:true}), false, 'Planning data must never enable a seller action.');
assert.equal(context.commercialSellerReady(mixedCommercialPayload, {provider:'connected-provider',bookable:true}), false, 'Mixed data must remain an assisted check rather than a seller action.');
assert.equal(context.commercialSellerReady(liveCommercialPayload, {provider:'demo',bookable:true}), false, 'A demo provider must never become bookable even inside a malformed live payload.');
assert.equal(context.commercialSellerReady(liveCommercialPayload, {provider:'connected-provider',bookable:true}), true, 'Only live data with a named connected and bookable provider may enable a seller action.');
assert.equal(context.commercialSellerReady(staleLiveCommercialPayload, {provider:'connected-provider',bookable:true}), false, 'A stale cached live result must return to assisted verification instead of remaining seller-ready.');
assert.equal(context.commercialPriceText(demoCommercialPayload, '$500'), '$500', 'A configured planning amount must remain visible as useful comparison guidance.');
assert.equal(context.commercialPriceText(mixedCommercialPayload, '$500'), '$500', 'A mixed planning amount must remain visible while its notice retains the final-quote boundary.');
assert.equal(context.commercialPriceText(liveCommercialPayload, '$500'), '$500', 'A live supplier amount may retain its supplier-formatted price.');
assert.equal(context.commercialPriceText(demoCommercialPayload, ''), 'בהצעה האישית', 'A missing planning amount must lead to the personal quote without making the card look broken.');
assert.equal(context.commercialDataNotice(demoCommercialPayload).includes('מחיר לתכנון והשוואה'), true, 'Planning prices must be explicitly identified as comparison guidance.');
assert.equal(context.commercialDataNotice(demoCommercialPayload).includes('המחיר, הזמינות והתנאים מאומתים לפני התשלום'), true, 'Planning prices must retain a clear final-price, availability and terms boundary.');
assert.equal(context.insuranceSaleReady({meta:{data_mode:'live',regulated_sale_ready:false}}), false, 'Live travel data alone must not enable a regulated insurance sale.');
assert.equal(context.insuranceSaleReady({meta:{data_mode:'live',regulated_sale_ready:true}}), true, 'Insurance product rendering requires an explicit regulated-sale capability.');
assert.equal(context.insuranceSaleReady({meta:{data_mode:'live',cache_state:'stale_error',regulated_sale_ready:true}}), false, 'Stale regulated results must not expose an insurance sale action.');
const assistedCommercialButton = new FakeElement('button');
context.configureCommercialAction(assistedCommercialButton, 'hotel', {provider:'connected-provider',bookable:true}, demoCommercialPayload);
assert.equal(assistedCommercialButton.classList.contains('is-assisted'), true, 'A non-live result must expose the assisted-check action style.');
assert.equal(assistedCommercialButton.textContent.includes('בקשו בדיקת מלון'), true, 'A non-live result must lead to an assisted price check rather than claim booking or a final quote.');
const sellerCommercialButton = new FakeElement('button');
context.configureCommercialAction(sellerCommercialButton, 'hotel', {provider:'connected-provider',bookable:true}, liveCommercialPayload);
assert.equal(sellerCommercialButton.classList.contains('is-assisted'), false, 'A proven live seller action must not be mislabeled as assisted.');
assert.equal(sellerCommercialButton.textContent.includes('לספק'), true, 'A proven live seller action must name the supplier handoff, not claim an on-site booking.');
const normalizedFlightTrip = JSON.parse(JSON.stringify(context.commercialHandoffContext('flight', {}, {
  query: {
    origin: 'TLV', destination: 'HKT', departure_date: '2026-11-03', return_date: '2026-11-17',
    adults: '2', children: '1', infants: '1', rooms: '2', budget: '5200.55', currency: 'usd',
    oldest_age: '72', ages: [72, 64], medical_condition: true, pregnancy: true
  }
})));
assert.deepEqual(normalizedFlightTrip, {
  origin: 'TLV', destination: 'HKT', depart_date: '2026-11-03', return_date: '2026-11-17',
  adults: 2, children: 1, infants: 1, travelers: 4, rooms: 2, budget: 5200, currency: 'USD', return_path: '/'
}, 'Commercial trip recording must normalize flight dates, party composition, rooms, budget, and currency.');
const normalizedHotelTrip = JSON.parse(JSON.stringify(context.commercialHandoffContext('hotel', {}, {
  query: {destination:'Rome',checkin:'2026-10-08',checkout:'2026-10-12',adults:'2',children:'0',rooms:'1',currency:'EUR'}
})));
assert.equal(normalizedHotelTrip.depart_date, '2026-10-08', 'Hotel check-in must normalize into the shared commercial departure date.');
assert.equal(normalizedHotelTrip.return_date, '2026-10-12', 'Hotel check-out must normalize into the shared commercial return date.');
assert.equal(context.normalizedCommercialDate('not-a-date', '2026-10-08'), '2026-10-08', 'Date normalization must skip an invalid alias and retain the next valid commercial date.');
assert.equal(JSON.stringify(normalizedFlightTrip).includes('medical_condition'), false, 'Commercial trip recording must never serialize a medical-condition flag.');
assert.equal(JSON.stringify(normalizedFlightTrip).includes('pregnancy'), false, 'Commercial trip recording must never serialize a pregnancy flag.');
assert.equal(JSON.stringify(normalizedFlightTrip).includes('oldest_age'), false, 'Commercial trip recording must never serialize traveler ages.');
const normalizedCandidate = JSON.parse(JSON.stringify(context.normalizedCommercialCandidate({
  id:'offer-1',title:'Safe offer',subtitle:'One stop',commercial_ref:'provider-ref',price_scope:'live',medical_condition:true,ages:[71]
}, {}, liveCommercialPayload)));
assert.deepEqual(Object.keys(normalizedCandidate).sort(), ['commercial_ref','id','price_scope','subtitle','title'], 'Only the bounded commercial candidate allowlist may cross the intent boundary.');
assert.equal(context.safeCommercialHandoffUrl('https://partner.example/continue?token=abc'), 'https://partner.example/continue?token=abc', 'A credential-free HTTPS provider URL may be used for handoff.');
assert.equal(context.safeCommercialHandoffUrl('http://partner.example/continue'), '', 'An insecure provider URL must fail closed.');
assert.equal(context.safeCommercialHandoffUrl('javascript:alert(1)'), '', 'A script URL must fail closed.');
assert.equal(context.safeCommercialHandoffUrl('https://user:secret@partner.example/continue'), '', 'A provider URL containing embedded credentials must fail closed.');

const commercialResponse = (body, status = 200) => ({
  ok: status >= 200 && status < 300,
  status,
  async json() { return body; }
});
const commercialIntentId = '123e4567-e89b-42d3-a456-426614174000';
const commercialIntentEndpoint = 'https://tra-vel.co.il/wp-json/tra-vel-agent/v1/commercial-intents';
const commercialPayload = {
  meta:{data_mode:'live',cache_state:'fresh'},
  query:{origin:'TLV',destination:'HKT',departure_date:'2026-11-03',return_date:'2026-11-17',adults:'2',children:'1',infants:'1',rooms:'2',currency:'USD',medical_condition:true,pregnancy:true,oldest_age:'72',ages:[72]},
  destination:{city:'Phuket'}, origin:{code:'TLV'}
};
const commercialCommerce = {id:'flight-offer-1',provider:'connected-provider',bookable:true};
const commercialCandidate = {id:'flight-offer-1',title:'Flight to Phuket',subtitle:'One stop',commercial_ref:'signed-ref',price_scope:'live',medical_condition:true,ages:[72]};
windowStub.traVelV2 = {commercialIntentUrl:commercialIntentEndpoint,nonce:'runtime-nonce'};
vm.runInContext('commercialIntentMutationRegistry.clear()', context);
const successfulCommercialRequests = [];
let releaseCommercialCreate;
context.fetch = async (url, options = {}) => {
  successfulCommercialRequests.push({url:String(url),options,body:JSON.parse(options.body || '{}')});
  if (successfulCommercialRequests.length === 1) {
    return new Promise(resolve => { releaseCommercialCreate = resolve; });
  }
  return commercialResponse({
    intent:{intent_id:commercialIntentId,reference:'TV-COM-1',version:4},
    handoff_url:'https://partner.example/continue?intent=TV-COM-1',
    provider:{label:'Tra-Vel'},
    conversion_type:'assisted_quote'
  });
};
const commercialActionButton = new FakeElement('button');
commercialActionButton.textContent = 'Continue';
const commercialNavigationStart = assignedLocations.length;
const firstCommercialClick = context.startCommercialHandoff(commercialActionButton, 'flight', commercialCommerce, commercialPayload, commercialCandidate);
const duplicateCommercialClick = context.startCommercialHandoff(commercialActionButton, 'flight', commercialCommerce, commercialPayload, commercialCandidate);
await Promise.resolve();
assert.equal(successfulCommercialRequests.length, 1, 'A double click must share one in-flight commercial intent mutation.');
assert.equal(assignedLocations.length, commercialNavigationStart, 'Navigation must not occur before a durable intent is confirmed.');
assert.equal(commercialActionButton.getAttribute('aria-busy'), 'true', 'A pending commercial action must expose its busy state to assistive technology.');
releaseCommercialCreate(commercialResponse({intent:{intent_id:commercialIntentId,reference:'TV-COM-1',version:3}}));
await Promise.all([firstCommercialClick, duplicateCommercialClick]);
assert.equal(successfulCommercialRequests.length, 2, 'A successful commercial action must create the intent and then prepare exactly one handoff.');
assert.equal(successfulCommercialRequests[0].url, commercialIntentEndpoint, 'The first commercial mutation must target the localized durable intent collection.');
assert.equal(successfulCommercialRequests[1].url, `${commercialIntentEndpoint}/${commercialIntentId}/handoffs`, 'The handoff must be prepared beneath the confirmed owned intent.');
const recordedCommercialIntent = successfulCommercialRequests[0].body;
assert.deepEqual(Object.keys(recordedCommercialIntent).sort(), ['candidate','data_mode','idempotency_key','offer_id','requested_provider','surface','trip','vertical'], 'The create request must match the closed commercial-intent contract.');
assert.equal(recordedCommercialIntent.surface, 'flight_results', 'Commercial intent surface must identify the result family.');
assert.equal(recordedCommercialIntent.requested_provider, 'connected-provider', 'Only a current live seller result may request its connected provider.');
assert.equal(recordedCommercialIntent.trip.infants, 1, 'Infants must be included in normalized party composition.');
assert.equal(recordedCommercialIntent.trip.travelers, 4, 'Total travelers must include adults, children, and infants.');
assert.deepEqual(Object.keys(recordedCommercialIntent.candidate).sort(), ['commercial_ref','id','price_scope','subtitle','title'], 'Recorded candidates must remain on the minimal allowlist.');
const recordedCommercialJson = JSON.stringify(recordedCommercialIntent);
for (const forbiddenCommercialField of ['medical_condition','pregnancy','oldest_age','"ages"']) {
  assert.equal(recordedCommercialJson.includes(forbiddenCommercialField), false, `Commercial intent must omit sensitive field ${forbiddenCommercialField}.`);
}
assert.equal(successfulCommercialRequests[0].options.headers['X-WP-Nonce'], 'runtime-nonce', 'Commercial mutations must carry the WordPress REST nonce.');
assert.equal(successfulCommercialRequests[1].body.expected_version, 3, 'Handoff preparation must use the confirmed intent version.');
assert.equal(Boolean(successfulCommercialRequests[1].body.idempotency_key), true, 'Handoff preparation must carry its own idempotency key.');
assert.notEqual(vm.runInContext('Array.from(commercialIntentMutationRegistry.values())[0].handoffKey', context), successfulCommercialRequests[1].body.idempotency_key, 'A confirmed handoff must rotate its operation key while ambiguous retries retain theirs.');
assert.equal(assignedLocations.at(-1), 'https://partner.example/continue?intent=TV-COM-1', 'Navigation may use only the validated HTTPS URL returned by the intent handoff.');
assert.equal(commercialActionButton.textContent, 'פותחים שיחה עם Tra-Vel', 'An owned assisted handoff must not retain a supplier-booking label from the source result card.');
assert.equal(commercialActionButton.getAttribute('aria-busy'), null, 'The commercial busy state must clear after the mutation settles.');
assert.equal(commercialActionButton.dataset.handoffState, 'ready', 'A confirmed handoff must leave the action in its ready state.');

vm.runInContext('commercialIntentMutationRegistry.clear()', context);
let failedCommercialRequestCount = 0;
const failedCommercialNavigationStart = assignedLocations.length;
context.console = {warn() {}};
context.fetch = async () => {
  failedCommercialRequestCount += 1;
  return commercialResponse({code:'provider_unavailable',message:'Provider unavailable.'}, 503);
};
const failedCommercialButton = new FakeElement('button');
failedCommercialButton.textContent = 'Continue';
await context.startCommercialHandoff(failedCommercialButton, 'flight', commercialCommerce, commercialPayload, commercialCandidate);
assert.equal(failedCommercialRequestCount, 1, 'A failed intent write must not fall through to an unrecorded direct or concierge handoff.');
assert.equal(assignedLocations.length, failedCommercialNavigationStart, 'A failed durable intent write must not navigate.');
assert.equal(failedCommercialButton.disabled, false, 'A failed commercial mutation must re-enable its action for retry.');

vm.runInContext('commercialIntentMutationRegistry.clear()', context);
const createTimeoutBodies = [];
const preCreateTimeoutSetTimeout = windowStub.setTimeout;
windowStub.setTimeout = callback => { queueMicrotask(callback); return 91; };
context.fetch = async (url, options = {}) => {
  createTimeoutBodies.push(JSON.parse(options.body || '{}'));
  return new Promise(() => {});
};
const createTimeoutButton = new FakeElement('button');
createTimeoutButton.textContent = 'Continue';
const createTimeoutNavigationStart = assignedLocations.length;
await context.startCommercialHandoff(createTimeoutButton, 'flight', commercialCommerce, commercialPayload, commercialCandidate);
windowStub.setTimeout = preCreateTimeoutSetTimeout;
assert.equal(createTimeoutBodies.length, 1, 'An ambiguous create timeout must perform only its original write attempt.');
assert.equal(assignedLocations.length, createTimeoutNavigationStart, 'An ambiguous create timeout must never trigger navigation.');
const timedOutCreateKey = createTimeoutBodies[0].idempotency_key;
const createRetryBodies = [];
context.fetch = async (url, options = {}) => {
  const body = JSON.parse(options.body || '{}');
  createRetryBodies.push(body);
  if (String(url) === commercialIntentEndpoint) return commercialResponse({intent:{intent_id:commercialIntentId,reference:'TV-COM-RETRY',version:7}});
  return commercialResponse({intent:{intent_id:commercialIntentId,reference:'TV-COM-RETRY',version:8},handoff_url:'https://partner.example/retry-create',provider:'Connected Provider'});
};
await context.startCommercialHandoff(createTimeoutButton, 'flight', commercialCommerce, commercialPayload, commercialCandidate);
assert.equal(createRetryBodies[0].idempotency_key, timedOutCreateKey, 'A retry after an ambiguous create timeout must reuse the original idempotency key.');
assert.equal(createRetryBodies.length, 2, 'A successful create retry must proceed through its recorded handoff exactly once.');

vm.runInContext('commercialIntentMutationRegistry.clear()', context);
const handoffTimeoutBodies = [];
let deadlineSchedule = 0;
windowStub.setTimeout = callback => {
  deadlineSchedule += 1;
  if (deadlineSchedule === 2) queueMicrotask(callback);
  return 100 + deadlineSchedule;
};
context.fetch = async (url, options = {}) => {
  const body = JSON.parse(options.body || '{}');
  handoffTimeoutBodies.push({url:String(url),body});
  if (String(url) === commercialIntentEndpoint) return commercialResponse({intent:{intent_id:commercialIntentId,reference:'TV-COM-HANDOFF',version:11}});
  return new Promise(() => {});
};
const handoffTimeoutButton = new FakeElement('button');
handoffTimeoutButton.textContent = 'Continue';
const handoffTimeoutNavigationStart = assignedLocations.length;
await context.startCommercialHandoff(handoffTimeoutButton, 'flight', commercialCommerce, commercialPayload, commercialCandidate);
windowStub.setTimeout = preCreateTimeoutSetTimeout;
assert.equal(handoffTimeoutBodies.length, 2, 'An ambiguous handoff timeout must retain the already-created intent without another fallback request.');
assert.equal(assignedLocations.length, handoffTimeoutNavigationStart, 'An ambiguous handoff timeout must not navigate.');
const timedOutHandoffKey = handoffTimeoutBodies[1].body.idempotency_key;
const handoffRetryBodies = [];
context.fetch = async (url, options = {}) => {
  const body = JSON.parse(options.body || '{}');
  handoffRetryBodies.push({url:String(url),body});
  return commercialResponse({intent:{intent_id:commercialIntentId,reference:'TV-COM-HANDOFF',version:12},handoff_url:'https://partner.example/retry-handoff',provider:'Connected Provider'});
};
await context.startCommercialHandoff(handoffTimeoutButton, 'flight', commercialCommerce, commercialPayload, commercialCandidate);
assert.equal(handoffRetryBodies.length, 1, 'A retry after an ambiguous handoff must resume the existing intent rather than recreate it.');
assert.equal(handoffRetryBodies[0].body.idempotency_key, timedOutHandoffKey, 'A retry after an ambiguous handoff timeout must reuse the original handoff idempotency key.');
assert.equal(handoffRetryBodies[0].body.expected_version, 11, 'A resumed handoff must retain the last confirmed intent version.');

vm.runInContext('commercialIntentMutationRegistry.clear()', context);
const expiredReplayBodies = [];
context.fetch = async (url, options = {}) => {
  const body = JSON.parse(options.body || '{}');
  expiredReplayBodies.push({url:String(url),body});
  if (String(url) === commercialIntentEndpoint) return commercialResponse({intent:{intent_id:commercialIntentId,reference:'TV-COM-EXPIRED',version:20}});
  if (expiredReplayBodies.length === 2) return commercialResponse({code:'tra_vel_commercial_handoff_replay_expired',message:'Expired.',data:{current_version:21}}, 409);
  return commercialResponse({intent:{intent_id:commercialIntentId,reference:'TV-COM-EXPIRED',version:22},handoff_url:'https://partner.example/fresh-handoff',provider:'Tra-Vel',conversion_type:'assisted_quote'});
};
const expiredReplayButton = new FakeElement('button');
expiredReplayButton.textContent = 'Continue';
await context.startCommercialHandoff(expiredReplayButton, 'flight', commercialCommerce, commercialPayload, commercialCandidate);
const expiredReplayKey = expiredReplayBodies[1].body.idempotency_key;
await context.startCommercialHandoff(expiredReplayButton, 'flight', commercialCommerce, commercialPayload, commercialCandidate);
assert.equal(expiredReplayBodies.length, 3, 'An expired replay must reuse the owned intent and prepare one fresh handoff on the next click.');
assert.equal(expiredReplayBodies[2].body.expected_version, 21, 'An expired replay response must advance the local optimistic version before retry.');
assert.notEqual(expiredReplayBodies[2].body.idempotency_key, expiredReplayKey, 'An expired replay must rotate its handoff operation key.');
context.console = console;
windowStub.traVelV2 = {};
delete context.fetch;
vm.runInContext('commercialIntentMutationRegistry.clear()', context);

// Theme 1.23.0: first-touch acquisition capture and bounded reads.
const acquisitionInitialSearch = windowStub.location.search;
const acquisitionInitialPathname = windowStub.location.pathname;
windowStub.location.hostname = 'tra-vel.co.il';
localStorageValues.delete('traVelAcquisition');
assert.equal(context.readAcquisition(), null, 'Without a stored record readAcquisition must return null.');
windowStub.location.search = `?utm_source=${'g'.repeat(200)}&utm_medium=cpc&utm_campaign=summer-launch`;
windowStub.location.pathname = '/packages/';
documentStub.referrer = 'https://www.google.com/search';
context.captureAcquisition();
const capturedAcquisition = context.readAcquisition();
assert.equal(capturedAcquisition.utm_source, 'g'.repeat(120), 'Acquisition fields must be capped at 120 characters.');
assert.equal(capturedAcquisition.utm_medium, 'cpc', 'Acquisition capture must retain the campaign medium.');
assert.equal(capturedAcquisition.utm_campaign, 'summer-launch', 'Acquisition capture must retain the campaign name.');
assert.equal(capturedAcquisition.landing_path, '/packages/', 'Acquisition capture must record the landing path.');
assert.equal(capturedAcquisition.referrer_host, 'www.google.com', 'Acquisition capture must record the referrer host.');
assert.equal(/^\d{4}-\d{2}-\d{2}T/.test(capturedAcquisition.first_seen_at), true, 'Acquisition capture must record an ISO first-seen instant.');
windowStub.location.search = '?utm_source=later-touch';
windowStub.location.pathname = '/hotels/';
context.captureAcquisition();
assert.equal(context.readAcquisition().utm_source, 'g'.repeat(120), 'First-touch attribution must win over later campaign visits.');
localStorageValues.delete('traVelAcquisition');
windowStub.location.search = '';
windowStub.location.pathname = '/';
documentStub.referrer = 'https://tra-vel.co.il/deals/';
context.captureAcquisition();
assert.equal(context.readAcquisition(), null, 'A same-host referrer without campaign parameters must not create an acquisition record.');
documentStub.referrer = 'https://l.facebook.com/l.php';
context.captureAcquisition();
const referrerOnlyAcquisition = context.readAcquisition();
assert.equal(referrerOnlyAcquisition.referrer_host, 'l.facebook.com', 'An external referrer alone must create a first-touch record.');
assert.equal('utm_source' in referrerOnlyAcquisition, false, 'A referrer-only record must not invent campaign fields.');
localStorageValues.delete('traVelAcquisition');
delete documentStub.referrer;
windowStub.location.search = acquisitionInitialSearch;
windowStub.location.pathname = acquisitionInitialPathname;

assert.equal(context.normalizedIsraeliPhone('050-123-4567'), '+972501234567', 'A local Israeli mobile number must normalize to +972 form.');
assert.equal(context.normalizedIsraeliPhone('+972 50 123 4567'), '+972501234567', 'An international Israeli mobile number must normalize to +972 form.');
assert.equal(context.normalizedIsraeliPhone('03-6317000'), '+97236317000', 'An Israeli landline must normalize to +972 form.');
assert.equal(context.normalizedIsraeliPhone('12345'), '', 'A short number must fail Israeli phone validation.');
assert.equal(context.normalizedIsraeliPhone('+44 20 7946 0000'), '', 'A non-Israeli number must fail Israeli phone validation.');

// Theme 1.23.0: stored acquisition and a consented contact ride the durable
// commercial-intent creation, and consent after creation resumes the scope.
const storedAcquisitionFixture = {
  utm_source: 'google', utm_medium: 'cpc', utm_campaign: 'summer-launch',
  landing_path: '/packages/', referrer_host: 'www.google.com', first_seen_at: '2026-07-19T09:30:00.000Z'
};
localStorageValues.set('traVelAcquisition', JSON.stringify(storedAcquisitionFixture));
windowStub.traVelV2 = {commercialIntentUrl: commercialIntentEndpoint, nonce: 'runtime-nonce'};
vm.runInContext('commercialIntentMutationRegistry.clear()', context);
const leadContactFixture = {name: 'דנה לוי', phone: '+972501234567', consent: true, consent_version: '2026-07-19'};
const contactCreateRequests = [];
context.fetch = async (url, options = {}) => {
  const body = JSON.parse(options.body || '{}');
  contactCreateRequests.push({url: String(url), body});
  if (String(url) === commercialIntentEndpoint) {
    return commercialResponse({intent: {intent_id: commercialIntentId, reference: 'TV-COM-LEAD', version: 2}});
  }
  return commercialResponse({
    intent: {intent_id: commercialIntentId, reference: 'TV-COM-LEAD', version: 3},
    handoff_url: 'https://partner.example/lead-handoff',
    provider: {label: 'Tra-Vel'},
    conversion_type: 'assisted_quote'
  });
};
context.commercialIntentRegistryEntry('flight', commercialCommerce, commercialPayload, commercialCandidate);
context.__leadContactFixture = leadContactFixture;
vm.runInContext(`{
  const leadEntry = Array.from(commercialIntentMutationRegistry.values())[0];
  leadEntry.contact = globalThis.__leadContactFixture;
  leadEntry.contactKey = 'commercial-contact-runtime-0001';
}`, context);
const leadContactButton = new FakeElement('button');
leadContactButton.textContent = 'Continue';
await context.startCommercialHandoff(leadContactButton, 'flight', commercialCommerce, commercialPayload, commercialCandidate);
assert.equal(contactCreateRequests.length, 2, 'A contact-bearing handoff must create the intent once and prepare one handoff.');
assert.equal(contactCreateRequests[0].body.idempotency_key, 'commercial-contact-runtime-0001', 'A contact-bearing create must use its dedicated contact operation key.');
assert.deepEqual(contactCreateRequests[0].body.contact, leadContactFixture, 'The consented contact must ride the commercial-intent creation unchanged.');
assert.deepEqual(contactCreateRequests[0].body.acquisition, storedAcquisitionFixture, 'Stored first-touch acquisition must ride the commercial-intent creation.');
assert.equal(vm.runInContext('Array.from(commercialIntentMutationRegistry.values())[0].contactSaved', context), true, 'A confirmed contact-bearing create must mark the contact as saved.');
assert.equal(contactCreateRequests[1].body.contact, undefined, 'The handoff preparation must never carry the contact.');

vm.runInContext('commercialIntentMutationRegistry.clear()', context);
const attachContactRequests = [];
context.fetch = async (url, options = {}) => {
  const body = JSON.parse(options.body || '{}');
  attachContactRequests.push({url: String(url), body});
  if (String(url) === commercialIntentEndpoint) {
    const createCalls = attachContactRequests.filter(request => request.url === commercialIntentEndpoint).length;
    return commercialResponse({intent: {intent_id: commercialIntentId, reference: 'TV-COM-ATTACH', version: createCalls === 1 ? 5 : 6}});
  }
  return commercialResponse({
    intent: {intent_id: commercialIntentId, reference: 'TV-COM-ATTACH', version: 7},
    handoff_url: 'https://partner.example/attach-handoff',
    provider: {label: 'Tra-Vel'},
    conversion_type: 'assisted_quote'
  });
};
const attachContactButton = new FakeElement('button');
attachContactButton.textContent = 'Continue';
await context.startCommercialHandoff(attachContactButton, 'flight', commercialCommerce, commercialPayload, commercialCandidate);
assert.equal(attachContactRequests.length, 2, 'The contact-free baseline must create and hand off exactly once.');
assert.equal(attachContactRequests[0].body.contact, undefined, 'A skipped contact step must keep the create contact-free.');
context.__attachContactFixture = {phone: '+972521234567', consent: true, consent_version: '2026-07-19'};
vm.runInContext(`{
  const attachEntry = Array.from(commercialIntentMutationRegistry.values())[0];
  attachEntry.contact = globalThis.__attachContactFixture;
  attachEntry.contactKey = 'commercial-contact-runtime-0002';
}`, context);
await context.startCommercialHandoff(attachContactButton, 'flight', commercialCommerce, commercialPayload, commercialCandidate);
assert.equal(attachContactRequests.length, 4, 'Consent after intent creation must resume the scope once and prepare one fresh handoff.');
assert.equal(attachContactRequests[2].url, commercialIntentEndpoint, 'The follow-up contact attachment must target the intent collection.');
assert.equal(attachContactRequests[2].body.idempotency_key, 'commercial-contact-runtime-0002', 'The follow-up contact attachment must use its dedicated operation key.');
assert.deepEqual(attachContactRequests[2].body.contact, {phone: '+972521234567', consent: true, consent_version: '2026-07-19'}, 'The follow-up attachment must carry the exact consented contact.');
assert.equal(attachContactRequests[3].body.expected_version, 6, 'The handoff after attachment must use the resumed intent version.');
delete context.__leadContactFixture;
delete context.__attachContactFixture;

// Theme 1.23.0: stored acquisition rides the quote-case creation payload.
windowStub.traVelV2 = {agentRestUrl: 'https://tra-vel.co.il/wp-json/tra-vel-agent/v1', nonce: 'runtime-nonce'};
const quoteCreateRequests = [];
context.fetch = async (url, options = {}) => {
  quoteCreateRequests.push({url: String(url), body: JSON.parse(options.body || '{}')});
  return commercialResponse({case: {case_id: '99999999-8888-4777-8666-555555555555', reference: 'TV-CASE0001', status: 'queued', version: 1}});
};
vm.runInContext(`
  agentRuntime.status = 'request_ready';
  agentRuntime.runId = 'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff';
  agentRuntime.requestId = 'req_runtime_acquisition_1';
  agentRuntime.requestRevision = 2;
`, context);
const quoteCreateRoot = new FakeElement('section');
const quoteCreateButton = new FakeElement('button');
const quoteCreateConsent = new FakeElement('input');
quoteCreateConsent.checked = true;
const quoteCreateStatus = new FakeElement('p');
quoteCreateRoot.queries.set('[data-quote-case-create-button]', quoteCreateButton);
quoteCreateRoot.queries.set('[data-quote-case-consent]', quoteCreateConsent);
quoteCreateRoot.queries.set('[data-quote-case-create-status]', quoteCreateStatus);
await context.createAgentQuoteCase(quoteCreateRoot);
assert.equal(quoteCreateRequests.length, 1, 'Quote-case creation must send exactly one authoritative write.');
assert.equal(quoteCreateRequests[0].url.endsWith('/runs/bbbbbbbb-cccc-4ddd-8eee-ffffffffffff/quote-cases'), true, 'Quote-case creation must target its run-scoped collection.');
assert.deepEqual(quoteCreateRequests[0].body.acquisition, storedAcquisitionFixture, 'Stored first-touch acquisition must ride the quote-case creation payload.');
assert.equal(quoteCreateRequests[0].body.consent_version, '2026-07-17', 'Quote-case creation must keep its own consent version.');
localStorageValues.delete('traVelAcquisition');
vm.runInContext("agentRuntime.status = ''; agentRuntime.runId = ''; agentRuntime.requestId = ''; agentRuntime.requestRevision = 0; agentRuntime.quoteCase = null;", context);
windowStub.traVelV2 = {};
delete context.fetch;
vm.runInContext('commercialIntentMutationRegistry.clear()', context);

const inlineOfferCard = new FakeElement('article');
const inlineSaveAnchor = {parentElement:null,closest(selector) { return selector.includes('.flight-offer') ? inlineOfferCard : null; }};
context.showWorkspaceToast('Inline save feedback', 'heart', inlineSaveAnchor);
assert.equal(inlineOfferCard.children.some(child => child?.dataset?.workspaceToast === ''), true, 'Save feedback outside map/workspace shells must mount inside the triggering result card.');
const planRunId = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
const makePlanDto = (overrides = {}) => {
  const base = {
    run_id: planRunId,
    status: 'request_ready',
    mode: 'agent',
    locale: 'he-IL',
    summary: '<img src=x onerror=alert(1)> Safe text',
    planning_context: {
      kind: 'map_point',
      selection_id: 'point-123',
      latitude: 32.0853,
      longitude: 34.7818,
      destination: 'tel-aviv',
      intent: 'easy',
      scope: ['flights', 'accommodation']
    },
    readiness: {status: 'ready_for_search', blockers: []},
    request_revision: 3,
    proposal_count: 0,
    created_at: '2026-07-18T09:00:00Z',
    updated_at: '2026-07-18T09:30:00Z',
    expires_at: '2026-07-19T09:00:00Z',
    resume_available: true
  };
  return {
    ...base,
    ...overrides,
    planning_context: {...base.planning_context, ...(overrides.planning_context || {})},
    readiness: {...base.readiness, ...(overrides.readiness || {})}
  };
};
const normalizedPlan = JSON.parse(JSON.stringify(context.normalizeWorkspacePlanRun(makePlanDto())));
assert.deepEqual(normalizedPlan.planning_context, {
  kind: 'map_point',
  selection_id: 'point-123',
  latitude: 32.0853,
  longitude: 34.7818,
  destination: 'tel-aviv',
  intent: 'easy',
  scope: ['flights', 'accommodation']
}, 'Workspace plans must retain the exact closed AgentRun map context.');
assert.equal(context.normalizeWorkspacePlanRun(makePlanDto({planning_context:{scope:['flights', 'unknown']}})), null, 'An unknown scope must fail the closed AgentRun DTO instead of being silently dropped.');
assert.notEqual(context.normalizeWorkspacePlanRun(makePlanDto({summary:'🧭'.repeat(500)})), null, 'AgentRun summary limits must count Unicode code points rather than UTF-16 code units.');
assert.equal(context.normalizeWorkspacePlanRun(makePlanDto({summary:'🧭'.repeat(501)})), null, 'An AgentRun summary over 500 Unicode code points must fail closed.');
assert.notEqual(context.normalizeWorkspacePlanRun(makePlanDto({readiness:{blockers:['🧭'.repeat(200)]}})), null, 'Readiness blocker limits must accept 200 Unicode code points.');
assert.equal(context.normalizeWorkspacePlanRun(makePlanDto({readiness:{blockers:['🧭'.repeat(201)]}})), null, 'A readiness blocker over 200 Unicode code points must fail closed.');
assert.equal(normalizedPlan.locale, 'he-IL', 'Workspace plans must preserve the AgentRun locale contract.');
assert.equal(vm.runInContext(`validWorkspaceAgentRunId('${planRunId}')`, context), true, 'A canonical UUID may identify a resumable plan.');
assert.equal(vm.runInContext("validWorkspaceAgentRunId('not-a-run-id')", context), false, 'An arbitrary run token must never be resumable.');
assert.equal(vm.runInContext(`quoteCaseCanResume({resume_available:true,source:{run_id:'${planRunId}'}})`, context), true, 'A quote case may resume only when the server flag and UUID are both valid.');
assert.equal(vm.runInContext(`quoteCaseCanResume({resume_available:false,source:{run_id:'${planRunId}'}})`, context), false, 'A valid UUID alone must not enable resume.');
assert.equal(vm.runInContext("quoteCaseCanResume({resume_available:true,source:{run_id:'unsafe'}})", context), false, 'A server flag alone must not enable an invalid run id.');
assert.equal(vm.runInContext(`workspacePlanTransition(
  {run_id:'${planRunId}',status:'request_ready',request_revision:3,updated_at:'2026-07-18T09:30:00Z'},
  {run_id:'${planRunId}',status:'proposal_ready',request_revision:3,updated_at:'2026-07-18T09:30:00Z'}
)`, context), 'confirmed', 'An unchanged server revision must not trigger positive plan motion.');
assert.equal(vm.runInContext(`workspacePlanTransition(
  {run_id:'${planRunId}',status:'request_ready',request_revision:3,updated_at:'2026-07-18T09:30:00Z'},
  {run_id:'${planRunId}',status:'proposal_ready',request_revision:3,updated_at:'2026-07-18T09:31:00Z'}
)`, context), 'status_changed', 'A newer forward lifecycle status must be distinguished from a new plan revision.');
assert.equal(vm.runInContext(`workspacePlanTransition(
  {run_id:'${planRunId}',status:'request_ready',request_revision:3,updated_at:'2026-07-18T09:30:00Z'},
  {run_id:'${planRunId}',status:'proposal_ready',request_revision:4,updated_at:'2026-07-18T09:31:00Z'}
)`, context), 'advanced', 'A higher healthy server revision may trigger one positive acknowledgement.');
assert.equal(vm.runInContext(`workspacePlanTransition(
  {run_id:'${planRunId}',status:'needs_clarification',request_revision:3,updated_at:'2026-07-18T09:30:00Z'},
  {run_id:'${planRunId}',status:'request_ready',request_revision:3,updated_at:'2026-07-18T09:31:00Z'}
)`, context), 'recovered', 'A newer server update may acknowledge recovery from attention without inventing progress.');
assert.equal(vm.runInContext(`isConfirmedQuoteCaseRecovery(
  {case_id:'case-1',status:'needs_information',version:2},
  {case_id:'case-1',status:'in_review',version:3}
)`, context), true, 'Quote-case recovery must require a higher authoritative version.');
assert.equal(vm.runInContext(`isConfirmedQuoteCaseRecovery(
  {case_id:'case-1',status:'needs_information',version:3},
  {case_id:'case-1',status:'in_review',version:3}
)`, context), false, 'An unchanged quote-case version must stay visually static.');
for (const terminalStatus of ['failed', 'cancelled']) {
  const terminalWithoutEvidence = context.workspacePlanProgress(context.normalizeWorkspacePlanRun(makePlanDto({
    status: terminalStatus,
    readiness: {status:'unsupported',blockers:[]},
    request_revision: 0,
    resume_available: false
  })));
  assert.equal(terminalWithoutEvidence[1].state, 'pending', `${terminalStatus} with revision zero must not claim a completed structured plan.`);
}
const explicitReadyProgress = context.workspacePlanProgress(context.normalizeWorkspacePlanRun(makePlanDto({
  status: 'completed',
  readiness: {status:'ready_for_search',blockers:[]},
  request_revision: 0,
  resume_available: false
})));
assert.equal(explicitReadyProgress[1].state, 'completed', 'Explicit readiness evidence may confirm a structured plan even before a positive revision.');
for (const malformedPayload of [
  {},
  {runs:{}},
  {runs:[makePlanDto({status:'unknown_status'})]},
  {runs:[makePlanDto({updated_at:'2026-02-31T09:30:00Z'})]},
  {runs:[makePlanDto({resume_available:'true'})]}
]) {
  assert.throws(() => context.normalizeWorkspacePlanPayload(malformedPayload), /AgentRun summary/, 'Malformed /runs payloads must fail their closed contract.');
}
assert.equal(context.safeWorkspaceHref('/saved/'), 'https://tra-vel.co.il/saved/', 'A relative workspace URL on the current origin must remain usable.');
for (const unsafeHref of ['javascript:alert(1)', 'data:text/html,unsafe', 'blob:https://tra-vel.co.il/id', 'https://evil.example/path', 'https://user:pass@tra-vel.co.il/path']) {
  assert.equal(context.safeWorkspaceHref(unsafeHref), 'https://tra-vel.co.il/', `Unsafe workspace URL must fail to the same-origin root: ${unsafeHref}`);
}
localStorageValues.set('traVelV2.workspace.v1', JSON.stringify({
  ...context.defaultLocalWorkspace(),
  items:[{id:'hotel:hostile-live',kind:'hotel',external_id:'hostile-live',title:'Hostile live label',data_mode:'live'}]
}));
vm.runInContext('workspaceDeletionTombstones = new Set()', context);
assert.equal(context.readLocalWorkspace().items[0].data_mode, 'mixed', 'A hostile browser snapshot must never self-assert live supplier provenance.');
assert.equal(context.normalizeWorkspaceItem({kind:'hotel',external_id:'direct-live',title:'Direct live',data_mode:'live'}).data_mode, 'mixed', 'A browser-created save must downgrade an untrusted live label to mixed.');
assert.equal(context.mergeTravelerWorkspaces(
  {...context.defaultLocalWorkspace(),items:[]},
  {...context.defaultLocalWorkspace(),items:[{id:'hotel:server-live',kind:'hotel',external_id:'server-live',title:'Unproven server live',data_mode:'live'}]}
).items[0].data_mode, 'mixed', 'Workspace merge must not introduce a live label without a trusted observation contract.');
localStorageValues.clear();
for (const terminalStatus of ['cancelled', 'expired', 'closed_no_quote']) {
  assert.deepEqual(runtimeJson(`(() => {
    const progress = quoteCaseProgressState('${terminalStatus}');
    return Array.from({length:4}, (_, index) => quoteCaseStepState(progress, index));
  })()`), ['completed', 'terminal', 'pending', 'pending'], `${terminalStatus} without event history must not falsely confirm queue review or assistance.`);
}
assert.deepEqual(runtimeJson(`mergeTravelerWorkspaces(
  {items:[{id:'hotel:kept',kind:'hotel',external_id:'kept',title:'Kept'},{id:'hotel:deleted',kind:'hotel',external_id:'deleted',title:'Deleted'}],preferences:{}},
  {items:[{id:'hotel:deleted',kind:'hotel',external_id:'deleted',title:'Deleted'},{id:'hotel:server',kind:'hotel',external_id:'server',title:'Server'}],preferences:{}},
  new Set(['hotel:deleted'])
).items.map(item => item.id)`), ['hotel:kept', 'hotel:server'], 'A deletion tombstone must prevent stale server data from resurrecting a removed option.');
const workspaceToast = new FakeElement('div');
documentQueries.set('[data-workspace-toast]', workspaceToast);
vm.runInContext(`
  globalThis.__workspaceWriteOriginal = writeLocalWorkspace;
  globalThis.__workspaceForgetOriginal = forgetWorkspaceDeletion;
  globalThis.__workspaceSaveOrder = [];
  writeLocalWorkspace = workspace => {
    globalThis.__workspaceSaveOrder.push('write:' + workspace.items.length);
    travelerWorkspace = workspace;
    return true;
  };
  forgetWorkspaceDeletion = () => {
    globalThis.__workspaceSaveOrder.push('forget:failed');
    return false;
  };
  travelerWorkspace = defaultLocalWorkspace();
  workspaceDeletionTombstones = new Set(['hotel:deleted']);
`, context);
const failedResave = await context.saveWorkspaceItem({kind:'hotel',external_id:'deleted',title:'Deleted hotel'}, null);
assert.equal(failedResave.localSaved, false, 'A re-save must fail closed when its deletion tombstone cannot be cleared.');
assert.deepEqual(runtimeJson('globalThis.__workspaceSaveOrder'), ['write:1', 'forget:failed', 'write:0'], 'Re-save must persist first, attempt tombstone clearing second, and roll the workspace back on failure.');
assert.equal(vm.runInContext('travelerWorkspace.items.length', context), 0, 'A failed tombstone clear must not leave the item resurrected in browser state.');
vm.runInContext(`
  writeLocalWorkspace = globalThis.__workspaceWriteOriginal;
  forgetWorkspaceDeletion = globalThis.__workspaceForgetOriginal;
  delete globalThis.__workspaceWriteOriginal;
  delete globalThis.__workspaceForgetOriginal;
  delete globalThis.__workspaceSaveOrder;
  travelerWorkspace = null;
  workspaceDeletionTombstones = new Set();
`, context);
vm.runInContext(`
  globalThis.__workspaceWriteOriginal = writeLocalWorkspace;
  globalThis.__workspaceCapacityWrites = 0;
  writeLocalWorkspace = workspace => {
    globalThis.__workspaceCapacityWrites += 1;
    travelerWorkspace = workspace;
    return true;
  };
  travelerWorkspace = {
    ...defaultLocalWorkspace(),
    items: Array.from({length:50}, (_, index) => ({id:'hotel:item-' + index,kind:'hotel',external_id:'item-' + index,title:'Hotel ' + index}))
  };
`, context);
const fiftyFirstSave = await context.saveWorkspaceItem({kind:'hotel',external_id:'new-item',title:'New hotel'}, null);
assert.equal(fiftyFirstSave.localSaved, false, 'A brand-new fifty-first local item must fail before mutation.');
assert.equal(fiftyFirstSave.reason, 'local_capacity', 'Local capacity must be distinguishable from a transient save failure.');
assert.equal(vm.runInContext('globalThis.__workspaceCapacityWrites', context), 0, 'The fifty-first item must not write or evict an existing saved option.');
assert.equal(vm.runInContext('travelerWorkspace.items.length', context), 50, 'A rejected fifty-first item must preserve all fifty existing items.');
const refreshedExistingSave = await context.saveWorkspaceItem({kind:'hotel',external_id:'item-0',title:'Updated hotel 0'}, null);
assert.equal(refreshedExistingSave.localSaved, true, 'An existing saved ID may still be refreshed at local capacity.');
assert.equal(vm.runInContext('globalThis.__workspaceCapacityWrites', context), 1, 'Refreshing an existing ID must perform exactly one local write.');
assert.equal(vm.runInContext("travelerWorkspace.items.length === 50 && travelerWorkspace.items[0].title === 'Updated hotel 0' && travelerWorkspace.items.some(item => item.id === 'hotel:item-49')", context), true, 'Refreshing an existing ID must retain all fifty IDs without eviction.');
vm.runInContext(`
  writeLocalWorkspace = globalThis.__workspaceWriteOriginal;
  delete globalThis.__workspaceWriteOriginal;
  delete globalThis.__workspaceCapacityWrites;
  travelerWorkspace = null;
`, context);

windowStub.traVelV2 = {isLoggedIn: true};
vm.runInContext(`
  globalThis.__persistenceWriteOriginal = writeLocalWorkspace;
  globalThis.__persistenceRequestOriginal = workspaceRequest;
  globalThis.__persistenceWriteCount = 0;
  writeLocalWorkspace = workspace => {
    globalThis.__persistenceWriteCount += 1;
    if (globalThis.__persistenceWriteCount === 1) {
      travelerWorkspace = workspace;
      return true;
    }
    return false;
  };
  workspaceRequest = async () => ({
    ...defaultLocalWorkspace(),
    items:[{id:'hotel:server-save',kind:'hotel',external_id:'server-save',title:'Server saved hotel'}],
    meta:{storage:'server-confirmed-item'}
  });
  travelerWorkspace = defaultLocalWorkspace();
`, context);
const accountOnlyItemSave = await context.saveWorkspaceItem({kind:'hotel',external_id:'server-save',title:'Server saved hotel'}, null);
assert.deepEqual(JSON.parse(JSON.stringify(accountOnlyItemSave)), {localSaved:false,accountSynced:true,devicePersisted:false}, 'Item save must distinguish server confirmation from failed device persistence.');
assert.equal(vm.runInContext("travelerWorkspace.meta.storage === 'server-confirmed-item'", context), true, 'A server-confirmed item must remain rendered from in-memory state when persistence fails.');
assert.equal(workspaceToast.children.at(-1).textContent.includes('לא נשמר במכשיר'), true, 'Item save must announce account-only confirmation without claiming device persistence.');

context.FormData = class {
  get(name) { return {home_airport:'TLV',currency:'USD',budget:'1200',max_stops:'1',party_style:'couple'}[name] ?? ''; }
  getAll(name) { return name === 'priorities' ? ['price', 'comfort'] : []; }
};
vm.runInContext(`
  globalThis.__persistenceWriteCount = 0;
  workspaceRequest = async () => ({
    ...defaultLocalWorkspace(),
    preferences:{...defaultLocalWorkspace().preferences,home_airport:'JRS'},
    meta:{storage:'server-confirmed-preferences'}
  });
  travelerWorkspace = defaultLocalWorkspace();
`, context);
const accountOnlyPreferences = await context.saveWorkspacePreferences({});
assert.deepEqual(JSON.parse(JSON.stringify(accountOnlyPreferences)), {localSaved:false,accountSynced:true,devicePersisted:false}, 'Preferences must distinguish server confirmation from failed device persistence.');
assert.equal(vm.runInContext("travelerWorkspace.meta.storage === 'server-confirmed-preferences' && travelerWorkspace.preferences.home_airport === 'JRS'", context), true, 'Server-confirmed preferences must remain available in memory when persistence fails.');
assert.equal(workspaceToast.children.at(-1).textContent.includes('לא נשמר במכשיר'), true, 'Preferences must announce account-only confirmation without claiming device persistence.');

vm.runInContext(`
  globalThis.__persistenceWriteCount = 0;
  travelerWorkspace = {
    ...defaultLocalWorkspace(),
    items:[{id:'hotel:watch-item',kind:'hotel',external_id:'watch-item',title:'Watch hotel',price_amount:100,currency:'USD',watch:{enabled:false,target_amount:0,delivery_enabled:false,status:'off'}}]
  };
  workspaceRequest = async () => ({
    ...defaultLocalWorkspace(),
    items:[{id:'hotel:watch-item',kind:'hotel',external_id:'watch-item',title:'Watch hotel',price_amount:100,currency:'USD',watch:{enabled:true,target_amount:95,delivery_enabled:false,status:'awaiting_live_supplier'}}],
    meta:{storage:'server-confirmed-watch'}
  });
`, context);
const accountOnlyWatch = await context.toggleWorkspaceWatch('hotel:watch-item');
assert.deepEqual(JSON.parse(JSON.stringify(accountOnlyWatch)), {localSaved:false,accountSynced:true,devicePersisted:false}, 'Price watch must distinguish server confirmation from failed device persistence.');
assert.equal(vm.runInContext("travelerWorkspace.meta.storage === 'server-confirmed-watch' && travelerWorkspace.items[0].watch.enabled === true", context), true, 'A server-confirmed watch must remain available in memory when persistence fails.');
assert.equal(workspaceToast.children.at(-1).textContent.includes('לא נשמר במכשיר'), true, 'Price watch must announce account-only confirmation without claiming device persistence.');
vm.runInContext(`
  writeLocalWorkspace = globalThis.__persistenceWriteOriginal;
  workspaceRequest = globalThis.__persistenceRequestOriginal;
  delete globalThis.__persistenceWriteOriginal;
  delete globalThis.__persistenceRequestOriginal;
  delete globalThis.__persistenceWriteCount;
  travelerWorkspace = null;
`, context);
context.__workspaceAuthOriginalRequest = context.workspaceRequest;
context.__workspaceAuthRequestCount = 0;
context.__workspaceAuthRequest = async () => {
  context.__workspaceAuthRequestCount += 1;
  const error = new Error('expired');
  error.status = 401;
  throw error;
};
vm.runInContext(`
  workspaceRequest = globalThis.__workspaceAuthRequest;
  workspaceAccountAuthRequired = false;
  workspaceCorrectiveSyncAttempts = 0;
  workspaceCorrectiveSyncTimer = 0;
  workspaceDeletionTombstones = new Set();
  travelerWorkspace = defaultLocalWorkspace();
`, context);
const authExpiredSave = await context.saveWorkspaceItem({kind:'hotel',external_id:'auth-expired',title:'Locally retained hotel'}, null);
assert.equal(authExpiredSave.reason, 'reauth_required', 'A 401/403 workspace mutation must be classified as reauthentication, not transport failure.');
assert.equal(vm.runInContext('workspaceAccountAuthRequired', context), true, 'An expired workspace session must enter an explicit reauthentication state.');
assert.equal(context.scheduleWorkspaceCorrectiveSync(0, true), false, 'Reauthentication state must stop corrective account retries.');
const authBlockedSave = await context.saveWorkspaceItem({kind:'hotel',external_id:'auth-blocked',title:'Second local hotel'}, null);
assert.equal(authBlockedSave.reason, 'reauth_required', 'Further local saves must remain possible while account writes are paused for reauthentication.');
assert.equal(context.__workspaceAuthRequestCount, 1, 'An expired session must not generate repeated account requests before refresh or sign-in.');
context.__workspaceAuthRequest = context.__workspaceAuthOriginalRequest;
vm.runInContext(`
  workspaceRequest = globalThis.__workspaceAuthRequest;
  workspaceAccountAuthRequired = false;
  travelerWorkspace = null;
`, context);
delete context.__workspaceAuthOriginalRequest;
delete context.__workspaceAuthRequest;
delete context.__workspaceAuthRequestCount;
delete context.FormData;
windowStub.traVelV2 = {};
documentQueries.delete('[data-workspace-toast]');

windowStub.traVelV2 = {isLoggedIn:true};
localStorageValues.clear();
vm.runInContext(`
  workspaceDeletionTombstones = new Set();
  writeLocalWorkspace({...defaultLocalWorkspace(),items:[{id:'hotel:mutation-base',kind:'hotel',external_id:'mutation-base',title:'Mutation base'}]});
  globalThis.__directMutationSnapshot = workspaceLocalSyncSnapshot(travelerWorkspace);
`, context);
context.writeLocalWorkspace({...context.defaultLocalWorkspace(),items:[{id:'hotel:newer-local',kind:'hotel',external_id:'newer-local',title:'Newer local'}]});
const staleDirectApply = context.applyServerConfirmedWorkspace(
  {...context.defaultLocalWorkspace(),items:[{id:'hotel:mutation-base',kind:'hotel',external_id:'mutation-base',title:'Mutation base'}]},
  {...context.defaultLocalWorkspace(),items:[{id:'hotel:stale-server',kind:'hotel',external_id:'stale-server',title:'Stale server'}]},
  '',
  context.__directMutationSnapshot
);
assert.equal(staleDirectApply.localChanged, true, 'A direct mutation response must reject a newer same-tab browser generation.');
assert.equal(staleDirectApply.correctiveScheduled, true, 'A rejected direct mutation response must queue bounded corrective sync.');
assert.equal(vm.runInContext("travelerWorkspace.items[0].id === 'hotel:newer-local'", context), true, 'A stale direct response must preserve the newer same-tab workspace.');

vm.runInContext(`
  workspaceCorrectiveSyncTimer = 0;
  workspaceDeletionTombstones = new Set();
  writeLocalWorkspace({...defaultLocalWorkspace(),items:[{id:'hotel:cross-base',kind:'hotel',external_id:'cross-base',title:'Cross base'}]});
  globalThis.__crossMutationSnapshot = workspaceLocalSyncSnapshot(travelerWorkspace);
`, context);
localStorageValues.set('traVelV2.workspace.v1', JSON.stringify({...context.defaultLocalWorkspace(),items:[{id:'hotel:newer-cross-tab',kind:'hotel',external_id:'newer-cross-tab',title:'Newer cross tab'}]}));
const staleCrossTabApply = context.applyServerConfirmedWorkspace(
  {...context.defaultLocalWorkspace(),items:[{id:'hotel:cross-base',kind:'hotel',external_id:'cross-base',title:'Cross base'}]},
  {...context.defaultLocalWorkspace(),items:[{id:'hotel:stale-cross-server',kind:'hotel',external_id:'stale-cross-server',title:'Stale cross server'}]},
  '',
  context.__crossMutationSnapshot
);
assert.equal(staleCrossTabApply.localChanged, true, 'A direct mutation response must reject a newer cross-tab storage snapshot.');
assert.equal(vm.runInContext("travelerWorkspace.items[0].id === 'hotel:newer-cross-tab'", context), true, 'Cross-tab reconciliation must preserve the newer stored workspace.');
vm.runInContext(`
  workspaceCorrectiveSyncTimer = 0;
  workspaceCorrectiveSyncAttempts = workspaceCorrectiveSyncMaximumAttempts;
`, context);
assert.equal(context.scheduleWorkspaceCorrectiveSync(0), false, 'Corrective workspace synchronization must stop at its bounded attempt budget.');
vm.runInContext(`
  workspaceCorrectiveSyncAttempts = 0;
  delete globalThis.__directMutationSnapshot;
  delete globalThis.__crossMutationSnapshot;
  travelerWorkspace = null;
  workspaceDeletionTombstones = new Set();
`, context);
localStorageValues.clear();
windowStub.traVelV2 = {};

const originalWorkspaceRequest = context.workspaceRequest;
let resolveWorkspaceRace;
context.__workspaceRaceRequest = () => new Promise(resolve => { resolveWorkspaceRace = resolve; });
vm.runInContext('workspaceRequest = globalThis.__workspaceRaceRequest', context);
localStorageValues.clear();
vm.runInContext(`
  travelerWorkspace = defaultLocalWorkspace();
  workspaceDeletionTombstones = new Set(['hotel:deleted-before-sync']);
  writeLocalWorkspace({
    ...defaultLocalWorkspace(),
    items:[{id:'hotel:before-sync',kind:'hotel',external_id:'before-sync',title:'Before sync'}]
  });
  writeWorkspaceDeletionTombstones(workspaceDeletionTombstones);
`, context);
const racingSync = context.synchronizeWorkspaceAccount();
await Promise.resolve();
context.writeLocalWorkspace({
  ...context.defaultLocalWorkspace(),
  items:[
    {id:'hotel:new-during-sync',kind:'hotel',external_id:'new-during-sync',title:'New during sync'},
    {id:'hotel:before-sync',kind:'hotel',external_id:'before-sync',title:'Before sync'}
  ]
});
context.rememberWorkspaceDeletion('hotel:deleted-during-sync');
resolveWorkspaceRace({
  ...context.defaultLocalWorkspace(),
  items:[{id:'hotel:server-old',kind:'hotel',external_id:'server-old',title:'Server old'}]
});
const racingSyncResult = await racingSync;
assert.equal(racingSyncResult.reason, 'local_changed', 'A save or deletion created during /sync must invalidate the submitted snapshot.');
assert.equal(vm.runInContext("travelerWorkspace.items.some(item => item.id === 'hotel:new-during-sync')", context), true, 'A same-tab save during /sync must not be overwritten by the stale response.');
assert.deepEqual(runtimeJson('[...workspaceDeletionTombstones].sort()'), ['hotel:deleted-before-sync','hotel:deleted-during-sync'], 'A stale /sync response must clear none of the old or newly-created tombstones.');

vm.runInContext(`
  workspaceDeletionTombstones = new Set(['hotel:confirmed-delete']);
  writeLocalWorkspace({...defaultLocalWorkspace(),items:[]});
  writeWorkspaceDeletionTombstones(workspaceDeletionTombstones);
  workspaceRequest = async () => ({...defaultLocalWorkspace(),items:[]});
`, context);
const confirmedDeletionSync = await context.synchronizeWorkspaceAccount();
assert.equal(confirmedDeletionSync.confirmed, true, 'An unchanged /sync snapshot may be committed after server confirmation.');
assert.deepEqual(runtimeJson('[...workspaceDeletionTombstones]'), [], 'Only a submitted deletion absent from the confirmed response may have its tombstone cleared.');

context.__workspaceRaceRequest = originalWorkspaceRequest;
vm.runInContext('workspaceRequest = globalThis.__workspaceRaceRequest; travelerWorkspace = null; workspaceDeletionTombstones = new Set()', context);
context.installWorkspaceStorageListener();
localStorageValues.set('traVelV2.workspace.v1', JSON.stringify({
  ...context.defaultLocalWorkspace(),
  items:[{id:'hotel:cross-tab',kind:'hotel',external_id:'cross-tab',title:'Cross tab',data_mode:'live'}]
}));
(windowListeners.get('storage') || []).forEach(listener => listener({key:'traVelV2.workspace.v1'}));
assert.equal(vm.runInContext("travelerWorkspace.items[0].id === 'hotel:cross-tab' && travelerWorkspace.items[0].data_mode === 'mixed'", context), true, 'A storage event must reconcile a cross-tab snapshot conservatively and downgrade untrusted provenance.');
localStorageValues.clear();
vm.runInContext('travelerWorkspace = null; workspaceDeletionTombstones = new Set()', context);

const savedItemsContainer = new FakeElement('div');
const savedItemsEmpty = new FakeElement('div');
const savedItemsEmptyLink = new FakeElement('a');
savedItemsEmpty.append(savedItemsEmptyLink);
documentQueries.set('[data-workspace-items]', savedItemsContainer);
documentQueries.set('[data-workspace-empty]', savedItemsEmpty);
const savedItemOne = {id:'hotel:focus-one',kind:'hotel',external_id:'focus-one',title:'Focus one',data_mode:'mixed',watch:{enabled:false,target_amount:0,delivery_enabled:false,status:'off'}};
const savedItemTwo = {id:'hotel:focus-two',kind:'hotel',external_id:'focus-two',title:'Focus two',data_mode:'mixed',watch:{enabled:false,target_amount:0,delivery_enabled:false,status:'off'}};
context.__savedFocusWorkspace = {...context.defaultLocalWorkspace(),items:[savedItemOne,savedItemTwo]};
vm.runInContext('travelerWorkspace = globalThis.__savedFocusWorkspace', context);
context.renderWorkspaceDashboard();
const savedFocusedCard = savedItemsContainer.children[0];
documentStub.activeElement = {
  dataset:{workspaceItemAction:'watch'},
  closest:() => savedFocusedCard
};
context.__savedFocusWorkspace = {
  ...context.__savedFocusWorkspace,
  items:[{...savedItemOne,watch:{enabled:true,target_amount:100,delivery_enabled:false,status:'awaiting_live_supplier'}},savedItemTwo]
};
vm.runInContext('travelerWorkspace = globalThis.__savedFocusWorkspace', context);
context.renderWorkspaceDashboard('hotel:focus-one');
const savedRestoredWatch = context.findWorkspaceDatasetElement(savedItemsContainer.children[0], 'workspaceItemAction', 'watch');
assert.equal(savedRestoredWatch.focusCalls, 1, 'A saved-card watch refresh must restore focus to the same logical action.');

const savedLastCard = savedItemsContainer.children[0];
documentStub.activeElement = {dataset:{workspaceItemAction:'watch'},closest:() => savedLastCard};
context.__savedFocusWorkspace = {...context.defaultLocalWorkspace(),items:[]};
vm.runInContext('travelerWorkspace = globalThis.__savedFocusWorkspace', context);
context.renderWorkspaceDashboard();
assert.equal(savedItemsEmptyLink.focusCalls, 1, 'Removing the last focused saved card must move focus to its empty-state action.');

context.__savedFocusWorkspace = {...context.defaultLocalWorkspace(),items:[savedItemOne]};
vm.runInContext('travelerWorkspace = globalThis.__savedFocusWorkspace', context);
context.renderWorkspaceDashboard();
const pendingSavedOriginCard = savedItemsContainer.children[0];
documentStub.activeElement = {
  dataset:{workspaceItemAction:'watch'},
  isConnected:true,
  closest:() => pendingSavedOriginCard
};
let resolveSavedMutation;
const pendingSavedMutation = context.runWorkspaceItemMutation('hotel:focus-one', 'watch', () => new Promise(resolve => { resolveSavedMutation = resolve; }));
context.renderWorkspaceDashboard('hotel:focus-one');
const pendingSavedCard = savedItemsContainer.children[0];
const pendingSavedWatch = context.findWorkspaceDatasetElement(pendingSavedCard, 'workspaceItemAction', 'watch');
const pendingSavedRemove = context.findWorkspaceDatasetElement(pendingSavedCard, 'workspaceItemAction', 'remove');
assert.equal(pendingSavedWatch.disabled && pendingSavedWatch.getAttribute('aria-busy') === 'true', true, 'A replacement watch control must remain disabled and busy while its PUT is unresolved.');
assert.equal(pendingSavedRemove.disabled, true, 'Other mutations for the same saved item must be serialized while a watch PUT is unresolved.');
const duplicateSavedMutation = await context.runWorkspaceItemMutation('hotel:focus-one', 'remove', async () => ({accountSynced:true}));
assert.equal(duplicateSavedMutation.reason, 'mutation_in_flight', 'A fast second saved-item action must not race the first mutation.');
const savedElsewhereFocus = new FakeElement('button');
savedElsewhereFocus.isConnected = true;
documentStub.activeElement = savedElsewhereFocus;
resolveSavedMutation({localSaved:true,accountSynced:true});
await pendingSavedMutation;
assert.equal(vm.runInContext('workspaceItemMutationRegistry.size', context), 0, 'The saved-item mutation lock must clear after settlement.');
const settledSavedWatch = context.findWorkspaceDatasetElement(savedItemsContainer.children[0], 'workspaceItemAction', 'watch');
assert.equal(settledSavedWatch.focusCalls, 0, 'A settled saved-item mutation must not steal focus after the traveler moves elsewhere.');

const mapPins = new FakeElement('div');
documentQueries.set('[data-workspace-map-pins]', mapPins);
documentQueryLists.set('[data-workspace-map-pin]', {forEach(callback) { mapPins.children.forEach(callback); }});
const mapFocusItems = [
  {...savedItemOne,title:'Focus one',destination:'Paris',price_label:'$100'},
  {...savedItemTwo,title:'Focus two',destination:'Rome',price_label:'$120'}
];
context.__savedFocusWorkspace = {...context.defaultLocalWorkspace(),items:mapFocusItems};
vm.runInContext('travelerWorkspace = globalThis.__savedFocusWorkspace', context);
context.renderWorkspaceMap(mapFocusItems);
const originallyFocusedPin = mapPins.children[1];
documentStub.activeElement = originallyFocusedPin;
context.renderWorkspaceMap(mapFocusItems);
const restoredMapPin = mapPins.children.find(pin => pin.dataset.workspaceMapPin === savedItemTwo.id);
assert.equal(restoredMapPin.focusCalls, 1, 'A workspace map rerender must restore keyboard focus to the same saved-item pin.');
assert.equal(mapPins.children.find(pin => pin.dataset.workspaceMapPin === savedItemOne.id).getAttribute('aria-pressed'), 'true', 'A map rerender must preserve its selected pin independently from keyboard focus.');
documentQueries.delete('[data-workspace-map-pins]');
documentQueryLists.delete('[data-workspace-map-pin]');

const preferenceForm = new FakeElement('form');
preferenceForm.elements = {
  home_airport:new FakeElement('input'),
  currency:new FakeElement('select'),
  budget:new FakeElement('input'),
  max_stops:new FakeElement('select'),
  party_style:new FakeElement('select')
};
const pricePriority = new FakeElement('input');
pricePriority.value = 'price';
preferenceForm.queryLists.set('[name="priorities"]', [pricePriority]);
documentQueries.set('[data-workspace-preferences]', preferenceForm);
vm.runInContext('workspacePreferencesDirty = false; workspacePreferencesEditGeneration = 0', context);
const baselinePreferences = {home_airport:'TLV',currency:'USD',budget:1000,max_stops:1,party_style:'couple',priorities:['price']};
context.hydrateWorkspacePreferences(baselinePreferences);
preferenceForm.elements.home_airport.value = 'LHR';
const dirtyPreferenceGeneration = context.markWorkspacePreferencesDirty();
context.hydrateWorkspacePreferences({...baselinePreferences,home_airport:'JFK'});
assert.equal(preferenceForm.elements.home_airport.value, 'LHR', 'Cross-tab or corrective hydration must not overwrite an unsubmitted preference edit.');
context.markWorkspacePreferencesDirty();
assert.equal(context.confirmWorkspacePreferencesSubmission(dirtyPreferenceGeneration, {...baselinePreferences,home_airport:'JFK'}), false, 'A stale preference submit generation must not clear a newer edit.');
assert.equal(preferenceForm.elements.home_airport.value, 'LHR', 'A stale submit must leave the newer preference value untouched.');
const currentPreferenceGeneration = vm.runInContext('workspacePreferencesEditGeneration', context);
assert.equal(context.confirmWorkspacePreferencesSubmission(currentPreferenceGeneration, {...baselinePreferences,home_airport:'CDG'}), true, 'The current confirmed preference submission may clear the dirty guard.');
assert.equal(preferenceForm.elements.home_airport.value, 'CDG', 'Current confirmed preferences may hydrate the form after the dirty guard clears.');
documentQueries.delete('[data-workspace-preferences]');
documentStub.activeElement = null;
documentQueries.delete('[data-workspace-items]');
documentQueries.delete('[data-workspace-empty]');
delete context.__savedFocusWorkspace;
vm.runInContext('travelerWorkspace = null', context);

const guestWorkspaceRoot = new FakeElement('main');
const guestCockpit = new FakeElement('section');
const guestCockpitStatus = new FakeElement('p');
const guestCockpitAnnouncer = new FakeElement('p');
const guestCockpitRetry = new FakeElement('button');
guestCockpitRetry.hidden = false;
guestCockpit.queries.set('[data-workspace-cockpit-status]', guestCockpitStatus);
guestCockpit.queries.set('[data-workspace-cockpit-announcer]', guestCockpitAnnouncer);
guestCockpit.queries.set('[data-workspace-cockpit-retry]', guestCockpitRetry);
guestWorkspaceRoot.queries.set('[data-workspace-cockpit-retry]', guestCockpitRetry);
documentQueries.set('[data-traveler-workspace]', guestWorkspaceRoot);
documentQueries.set('[data-workspace-cockpit]', guestCockpit);
const guestDocumentListeners = new Map();
const originalDocumentAddEventListener = documentStub.addEventListener;
documentStub.addEventListener = (type, callback) => {
  const listeners = guestDocumentListeners.get(type) || [];
  listeners.push(callback);
  guestDocumentListeners.set(type, listeners);
};
windowStub.traVelV2 = {isLoggedIn: false};
vm.runInContext(`
  globalThis.__guestReadDeletedOriginal = readWorkspaceDeletionTombstones;
  globalThis.__guestReadWorkspaceOriginal = readLocalWorkspace;
  globalThis.__guestLoadPlansOriginal = loadWorkspacePlans;
  globalThis.__guestLoadCasesOriginal = loadWorkspaceQuoteCases;
  globalThis.__guestSchedulePlansOriginal = scheduleWorkspacePlanPoll;
  globalThis.__guestScheduleCasesOriginal = scheduleWorkspaceQuoteCasePoll;
  globalThis.__guestWorkspaceCalls = {planLoads:0,caseLoads:0,planSchedules:0,caseSchedules:0};
  readWorkspaceDeletionTombstones = () => new Set();
  readLocalWorkspace = () => defaultLocalWorkspace();
  loadWorkspacePlans = () => { globalThis.__guestWorkspaceCalls.planLoads += 1; };
  loadWorkspaceQuoteCases = () => { globalThis.__guestWorkspaceCalls.caseLoads += 1; };
  scheduleWorkspacePlanPoll = () => { globalThis.__guestWorkspaceCalls.planSchedules += 1; };
  scheduleWorkspaceQuoteCasePoll = () => { globalThis.__guestWorkspaceCalls.caseSchedules += 1; };
`, context);
await context.initTravelerWorkspace();
assert.deepEqual(runtimeJson('globalThis.__guestWorkspaceCalls'), {planLoads:0,caseLoads:1,planSchedules:0,caseSchedules:0}, 'Guest initialization must suppress authenticated plans while preserving owner-cookie assistance cases.');
assert.equal(guestCockpit.dataset.state, 'local', 'The logged-out cockpit must expose a calm local-only state.');
assert.equal(guestCockpit.getAttribute('aria-busy'), 'false', 'The logged-out cockpit must not remain busy.');
assert.equal(guestCockpitRetry.hidden, true, 'The logged-out cockpit must keep authenticated retry hidden.');
assert.equal(guestCockpitStatus.textContent.includes('התחברו'), true, 'The logged-out cockpit must explain that account plans require sign-in.');
(guestDocumentListeners.get('visibilitychange') || []).forEach(listener => listener());
assert.deepEqual(runtimeJson('globalThis.__guestWorkspaceCalls'), {planLoads:0,caseLoads:1,planSchedules:0,caseSchedules:1}, 'Guest visibility reconnect may poll owner-cookie cases but must never schedule AgentRun polling.');
vm.runInContext(`
  readWorkspaceDeletionTombstones = globalThis.__guestReadDeletedOriginal;
  readLocalWorkspace = globalThis.__guestReadWorkspaceOriginal;
  loadWorkspacePlans = globalThis.__guestLoadPlansOriginal;
  loadWorkspaceQuoteCases = globalThis.__guestLoadCasesOriginal;
  scheduleWorkspacePlanPoll = globalThis.__guestSchedulePlansOriginal;
  scheduleWorkspaceQuoteCasePoll = globalThis.__guestScheduleCasesOriginal;
  delete globalThis.__guestReadDeletedOriginal;
  delete globalThis.__guestReadWorkspaceOriginal;
  delete globalThis.__guestLoadPlansOriginal;
  delete globalThis.__guestLoadCasesOriginal;
  delete globalThis.__guestSchedulePlansOriginal;
  delete globalThis.__guestScheduleCasesOriginal;
  delete globalThis.__guestWorkspaceCalls;
  travelerWorkspace = null;
`, context);
windowStub.traVelV2 = {};
documentStub.addEventListener = originalDocumentAddEventListener;
documentQueries.delete('[data-traveler-workspace]');
documentQueries.delete('[data-workspace-cockpit]');
const cappedPlanDtos = Array.from({length:14}, (_, index) => makePlanDto({
  run_id: `00000000-0000-4000-8000-${String(index).padStart(12, '0')}`,
  status: 'created',
  summary: `Plan ${index}`,
  planning_context: {
    kind: 'destination',
    selection_id: `destination-${String(index).padStart(2, '0')}`,
    latitude: null,
    longitude: null,
    destination: `destination-${index}`,
    intent: 'smart',
    scope: []
  },
  readiness: {status:'needs_clarification',blockers:[]},
  request_revision: 0,
  updated_at: `2026-07-18T09:${String(index).padStart(2, '0')}:00Z`
}));
assert.equal(context.mergeWorkspacePlanRuns(context.normalizeWorkspacePlanPayload({runs:cappedPlanDtos})).length, 12, 'The private cockpit must cap normalized server runs at twelve cards.');

const cockpit = new FakeElement('section');
const cockpitStatus = new FakeElement('p');
const cockpitAnnouncer = new FakeElement('p');
const cockpitRetry = new FakeElement('button');
const cockpitList = new FakeElement('div');
const cockpitEmpty = new FakeElement('div');
cockpit.queries.set('[data-workspace-cockpit-status]', cockpitStatus);
cockpit.queries.set('[data-workspace-cockpit-announcer]', cockpitAnnouncer);
cockpit.queries.set('[data-workspace-cockpit-retry]', cockpitRetry);
cockpit.queries.set('[data-workspace-plan-list]', cockpitList);
cockpit.queries.set('[data-workspace-plan-empty]', cockpitEmpty);
documentQueries.set('[data-workspace-cockpit]', cockpit);
documentQueries.set('[data-workspace-cockpit-announcer]', cockpitAnnouncer);
const mountedPlan = context.normalizeWorkspacePlanRun(makePlanDto({planning_context:{scope:['flights']}}));
context.renderWorkspacePlans([mountedPlan], new Map([[planRunId, 'advanced']]));
assert.equal(cockpit.dataset.state, 'advanced', 'The mounted cockpit must expose a real server-confirmed advancement state.');
assert.equal(cockpit.getAttribute('aria-busy'), 'false', 'A mounted confirmed plan must clear its request-only busy state.');
assert.equal(cockpitList.children.length, 1, 'The mounted cockpit must render one card for one normalized server run.');
const mountedCard = cockpitList.children[0];
assert.equal(mountedCard.className.includes('is-advancing'), true, 'A mounted card may animate for one confirmed higher revision.');
const mountedHeader = mountedCard.children.find(child => child?.className === 'workspace-plan-card-head');
const mountedTitle = mountedHeader.children[0].children.find(child => child?.tagName === 'H3');
assert.equal(mountedTitle.textContent, '<img src=x onerror=alert(1)> Safe text', 'Untrusted plan copy must mount as text rather than HTML.');
const mountedProgress = mountedCard.children.find(child => child?.className === 'workspace-plan-progress');
assert.equal(mountedProgress.tagName, 'OL', 'Mounted plan progress must be an ordered list.');
assert.equal(mountedProgress.children.some(step => step.getAttribute('aria-current') === 'step'), true, 'Mounted plan progress must expose its current step.');
assert.equal(cockpitAnnouncer.textContent.includes('עודכן') && cockpitAnnouncer.textContent.includes('18.07'), true, 'Plan announcements must identify a real update time in traveler language without exposing an internal revision number.');

const initialAnnouncementWrites = cockpitAnnouncer.textWrites;
context.renderWorkspacePlans([mountedPlan], new Map([[planRunId, 'added']]), {initialLoad:true,previousRuns:new Map()});
assert.equal(cockpitAnnouncer.textWrites, initialAnnouncementWrites + 1, 'The initial confirmed plan load must be announced once.');
assert.equal(cockpitList.children[0].className.includes('is-advancing'), false, 'Initial hydration must not replay poll-time addition motion.');
const failedPlanResumeAction = context.findWorkspaceDatasetElement(cockpitList.children[0], 'workspacePlanAction', 'primary');
const planResumeNavigationCount = assignedLocations.length;
failedPlanResumeAction.listeners.get('click')[0]();
assert.equal(assignedLocations.length, planResumeNavigationCount, 'Saved AgentRun resume must not navigate when session storage is unavailable.');
assert.equal(failedPlanResumeAction.dataset.state, 'error', 'Saved AgentRun resume failure must expose an error state on its action.');
assert.equal(cockpitAnnouncer.textContent.includes('\u05d0\u05d7\u05e1\u05d5\u05df'), true, 'Saved AgentRun resume failure must announce recoverable storage guidance.');
const stableCard = cockpitList.children[0];
const stableFocusedAction = {dataset:{workspacePlanAction:'primary'},closest:() => stableCard};
documentStub.activeElement = stableFocusedAction;
const stableAnnouncementWrites = cockpitAnnouncer.textWrites;
context.renderWorkspacePlans([mountedPlan], new Map([[planRunId, 'confirmed']]), {
  replaceCards:false,
  previousRuns:new Map([[planRunId,mountedPlan]])
});
assert.equal(cockpitList.children[0], stableCard, 'An unchanged plan poll must preserve the mounted card DOM.');
assert.equal(documentStub.activeElement, stableFocusedAction, 'An unchanged plan poll must not drop the focused action.');
assert.equal(cockpitAnnouncer.textWrites, stableAnnouncementWrites, 'An unchanged plan poll must not replay an ARIA announcement.');

const revisedPlan = context.normalizeWorkspacePlanRun(makePlanDto({request_revision:4,updated_at:'2026-07-18T09:31:00Z'}));
context.renderWorkspacePlans([revisedPlan], new Map([[planRunId, 'advanced']]), {
  replaceCards:true,
  previousRuns:new Map([[planRunId,mountedPlan]])
});
const revisedAction = context.findWorkspaceDatasetElement(cockpitList.children[0], 'workspacePlanAction', 'primary');
assert.equal(revisedAction.focusCalls, 1, 'A changed plan render must restore focus to the same logical action.');

const statusChangedPlan = context.normalizeWorkspacePlanRun(makePlanDto({
  status:'searching',
  request_revision:4,
  updated_at:'2026-07-18T09:32:00Z'
}));
const statusTransition = context.workspacePlanTransition(revisedPlan, statusChangedPlan);
assert.equal(statusTransition, 'status_changed', 'A newer lifecycle status at the same plan revision must be recognized without claiming a new revision.');
const statusAnnouncementWrites = cockpitAnnouncer.textWrites;
context.renderWorkspacePlans([statusChangedPlan], new Map([[planRunId,statusTransition]]), {
  previousRuns:new Map([[planRunId,revisedPlan]])
});
assert.equal(cockpitAnnouncer.textWrites, statusAnnouncementWrites + 1, 'A server-confirmed same-revision lifecycle change must receive one ARIA announcement.');
assert.equal(cockpitList.children[0].className.includes('is-advancing'), true, 'A ranked forward lifecycle change may receive one acknowledgement without being called a new revision.');

const secondPlanId = 'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff';
const secondPlan = context.normalizeWorkspacePlanRun(makePlanDto({run_id:secondPlanId,summary:'Second plan'}));
const addedAnnouncementWrites = cockpitAnnouncer.textWrites;
context.renderWorkspacePlans([statusChangedPlan, secondPlan], new Map([[planRunId,'confirmed'],[secondPlanId,'added']]), {
  previousRuns:new Map([[planRunId,statusChangedPlan]])
});
assert.equal(cockpitAnnouncer.textWrites, addedAnnouncementWrites + 1, 'A poll-time server-confirmed plan addition must receive one distinct announcement.');
assert.equal(cockpitList.children[1].className.includes('is-advancing'), true, 'A newly confirmed plan may receive one positive acknowledgement.');

const firstPlanCard = cockpitList.children[0];
documentStub.activeElement = {
  dataset:{workspacePlanAction:'primary'},
  closest:() => firstPlanCard
};
const removalAnnouncementWrites = cockpitAnnouncer.textWrites;
context.renderWorkspacePlans([secondPlan], new Map([[secondPlanId,'confirmed']]), {
  previousRuns:new Map([[planRunId,statusChangedPlan],[secondPlanId,secondPlan]])
});
const removalFallbackAction = context.findWorkspaceDatasetElement(cockpitList.children[0], 'workspacePlanAction', 'primary');
assert.equal(removalFallbackAction.focusCalls, 1, 'A removed or expired focused plan must move focus to the nearest remaining plan action.');
assert.equal(cockpitAnnouncer.textWrites, removalAnnouncementWrites + 1, 'A plan removal or expiry must be announced once.');

const completedPlan = context.normalizeWorkspacePlanRun(makePlanDto({
  status:'completed',
  request_revision:5,
  updated_at:'2026-07-18T09:33:00Z',
  resume_available:false
}));
const completedTransition = context.workspacePlanTransition(statusChangedPlan, completedPlan);
assert.equal(completedTransition, 'completed', 'Successful completion must be distinct from failed or cancelled terminal state.');
context.renderWorkspacePlans([completedPlan], new Map([[planRunId,completedTransition]]), {
  previousRuns:new Map([[planRunId,statusChangedPlan]])
});
assert.equal(cockpitList.children[0].dataset.state, 'completed', 'A completed AgentRun card must have a success state distinct from failed or cancelled terminal state.');
assert.equal(cockpitList.children[0].className.includes('is-advancing'), true, 'A newly server-confirmed successful completion may receive one success acknowledgement.');
assert.equal(cockpitAnnouncer.textContent.includes('אין בכך אישור למחיר'), true, 'Successful completion copy must retain the no-price/payment/booking disclaimer.');

const planEmptyLink = new FakeElement('a');
cockpitEmpty.append(planEmptyLink);
const lastPlanCard = cockpitList.children[0];
documentStub.activeElement = {dataset:{workspacePlanAction:'primary'},closest:() => lastPlanCard};
context.renderWorkspacePlans([], new Map(), {previousRuns:new Map([[planRunId,completedPlan]])});
assert.equal(planEmptyLink.focusCalls, 1, 'Removing the last focused plan must move focus to the visible empty-state action.');

context.renderWorkspacePlans([mountedPlan], new Map([[planRunId,'confirmed']]), {previousRuns:new Map()});
const retainedMalformedCard = cockpitList.children[0];
const malformedMountedPayloads = [
  {},
  {runs:{}},
  {runs:[makePlanDto({status:'unknown_status'})]},
  {runs:[makePlanDto({updated_at:'2026-02-30T09:30:00Z'})]},
  {runs:[makePlanDto({resume_available:1})]}
];
const originalAgentApiRequest = context.agentApiRequest;
for (const malformedPayload of malformedMountedPayloads) {
  context.__malformedPlanRequest = async () => malformedPayload;
  vm.runInContext(`
    agentApiRequest = globalThis.__malformedPlanRequest;
    workspacePlanRuntime.runs = new Map([['${planRunId}', globalThis.__mountedPlanForRuntime]]);
    workspacePlanRuntime.snapshot = workspacePlanSnapshot([globalThis.__mountedPlanForRuntime]);
    workspacePlanRuntime.hasLoaded = true;
    workspacePlanRuntime.inFlight = false;
    workspacePlanRuntime.authRequired = false;
    workspacePlanRuntime.failures = 0;
  `, Object.assign(context, {__mountedPlanForRuntime:mountedPlan}));
  await context.loadWorkspacePlans({polling:true});
  assert.equal(cockpitList.children[0], retainedMalformedCard, 'A malformed /runs DTO must retain the last confirmed mounted card.');
  assert.equal(cockpit.dataset.state, 'stale', 'A malformed /runs DTO must fail closed to stale confirmed state.');
}
context.__malformedPlanRequest = async () => { const error = new Error('expired'); error.status = 401; throw error; };
vm.runInContext(`
  agentApiRequest = globalThis.__malformedPlanRequest;
  workspacePlanRuntime.authRequired = false;
  workspacePlanRuntime.inFlight = false;
  workspacePlanRuntime.timer = 0;
`, context);
await context.loadWorkspacePlans({polling:true});
assert.equal(cockpit.dataset.state, 'reauth_required', 'Plan polling must enter a terminal re-authentication state on 401/403.');
assert.equal(vm.runInContext('workspacePlanRuntime.authRequired', context), true, 'Expired plan authorization must stop automatic retries.');
assert.equal(vm.runInContext('workspacePlanRuntime.timer', context), 0, 'An authorization failure must not schedule another plan poll.');
vm.runInContext('workspacePlanRuntime.authRequired = false; workspacePlanRuntime.timer = 0', context);
documentStub.visibilityState = 'hidden';
context.scheduleWorkspacePlanPoll(10);
assert.equal(vm.runInContext('workspacePlanRuntime.timer', context), 0, 'Plan polling must not schedule work while the document is hidden.');
documentStub.visibilityState = 'visible';
context.__malformedPlanRequest = originalAgentApiRequest;
vm.runInContext('agentApiRequest = globalThis.__malformedPlanRequest', context);
delete context.__mountedPlanForRuntime;
delete context.__malformedPlanRequest;
documentStub.activeElement = null;
documentQueries.delete('[data-workspace-cockpit]');
documentQueries.delete('[data-workspace-cockpit-announcer]');

const quoteRoot = new FakeElement('section');
const quoteGrid = new FakeElement('div');
const quoteEmpty = new FakeElement('div');
const quoteEmptyLink = new FakeElement('a');
const quoteStatus = new FakeElement('p');
quoteEmpty.append(quoteEmptyLink);
quoteRoot.queries.set('[data-workspace-quote-grid]', quoteGrid);
quoteRoot.queries.set('[data-workspace-quote-empty]', quoteEmpty);
quoteRoot.queries.set('[data-workspace-quote-status]', quoteStatus);
documentQueries.set('[data-workspace-quote-cases]', quoteRoot);
documentQueries.set('[data-workspace-quote-grid]', quoteGrid);
const makeQuoteCase = (caseId, overrides = {}) => ({
  case_id: caseId,
  reference: `REF-${caseId}`,
  status: 'queued',
  version: 1,
  updated_at: '2026-07-18T10:00:00Z',
  resume_available: true,
  source: {run_id:planRunId,request_revision:3},
  summary: {title:`Quote ${caseId}`},
  ...overrides
});
const quoteOne = makeQuoteCase('case-one');
const quoteTwo = makeQuoteCase('case-two');
context.renderWorkspaceQuoteCases([quoteOne,quoteTwo]);
const failedQuoteResumeAction = context.findWorkspaceDatasetElement(quoteGrid.children[0], 'workspaceQuoteAction', 'open');
const quoteResumeNavigationCount = assignedLocations.length;
failedQuoteResumeAction.listeners.get('click')[0]();
assert.equal(assignedLocations.length, quoteResumeNavigationCount, 'Saved QuoteCase resume must not navigate when session storage is unavailable.');
assert.equal(context.findWorkspaceClassElement(quoteGrid.children[0], 'workspace-quote-action-status').dataset.state, 'error', 'Saved QuoteCase resume failure must expose an error state.');
assert.equal(context.findWorkspaceClassElement(quoteGrid.children[0], 'workspace-quote-action-status').textContent.includes('\u05d0\u05d7\u05e1\u05d5\u05df'), true, 'Saved QuoteCase resume failure must expose recoverable storage guidance.');
const firstQuoteCard = quoteGrid.children[0];
documentStub.activeElement = {
  dataset:{workspaceQuoteAction:'handoff'},
  closest:() => firstQuoteCard
};
context.renderWorkspaceQuoteCases([quoteOne,quoteTwo]);
const restoredQuoteAction = context.findWorkspaceDatasetElement(quoteGrid.children[0], 'workspaceQuoteAction', 'handoff');
assert.equal(restoredQuoteAction.focusCalls, 1, 'A quote list refresh must restore focus to the same logical case action.');

context.renderWorkspaceQuoteCases([makeQuoteCase('case-positive')], new Set(['case-positive']), {polling:true});
assert.equal(quoteGrid.children[0].className.includes('is-advancing'), true, 'A newly confirmed active assistance case may receive one positive acknowledgement.');
vm.runInContext('workspaceQuoteCaseRuntime.snapshot = new Map()', context);
context.renderWorkspaceQuoteCases([makeQuoteCase('case-needs-info',{status:'needs_information'})], new Set(), {polling:true,attentionTransitionIds:new Set(['case-needs-info'])});
assert.equal(quoteGrid.children[0].className.includes('is-advancing'), false, 'A newly blocked assistance case must not receive positive motion.');
assert.equal(quoteStatus.textContent.includes('מידע נוסף'), true, 'A newly blocked assistance case needs calm attention copy.');
context.renderWorkspaceQuoteCases([makeQuoteCase('case-terminal',{status:'cancelled'})], new Set(), {polling:true,terminalTransitionIds:new Set(['case-terminal'])});
assert.equal(quoteGrid.children[0].className.includes('is-advancing'), false, 'A newly terminal assistance case must not receive positive motion.');

const quoteTransitionAgentRequestOriginal = context.agentApiRequest;
const queuedBeforeAttention = makeQuoteCase('case-transition-attention',{status:'queued',version:1});
const needsInformationAfterPoll = makeQuoteCase('case-transition-attention',{status:'needs_information',version:2,updated_at:'2026-07-18T10:01:00Z'});
context.__quoteTransitionRequest = async () => ({cases:[needsInformationAfterPoll]});
context.__quoteTransitionPrevious = queuedBeforeAttention;
vm.runInContext(`
  agentApiRequest = globalThis.__quoteTransitionRequest;
  workspaceQuoteCaseRuntime.snapshot = workspaceQuoteCaseSnapshot([globalThis.__quoteTransitionPrevious]);
  workspaceQuoteCaseRuntime.inFlight = false;
  workspaceQuoteCaseRuntime.authRequired = false;
  workspaceQuoteCaseRuntime.failures = 0;
`, context);
await context.loadWorkspaceQuoteCases({polling:true});
assert.equal(quoteStatus.textContent.includes('מידע נוסף'), true, 'A real QuoteCase poll must announce a transition into needs-information.');
assert.equal(quoteGrid.children[0].className.includes('is-advancing'), false, 'A real needs-information poll transition must remain free of positive motion.');

const inReviewBeforeTerminal = makeQuoteCase('case-transition-terminal',{status:'in_review',version:2});
const cancelledAfterPoll = makeQuoteCase('case-transition-terminal',{status:'cancelled',version:3,updated_at:'2026-07-18T10:02:00Z'});
context.__quoteTransitionRequest = async () => ({cases:[cancelledAfterPoll]});
context.__quoteTransitionPrevious = inReviewBeforeTerminal;
vm.runInContext(`
  agentApiRequest = globalThis.__quoteTransitionRequest;
  workspaceQuoteCaseRuntime.snapshot = workspaceQuoteCaseSnapshot([globalThis.__quoteTransitionPrevious]);
  workspaceQuoteCaseRuntime.inFlight = false;
`, context);
await context.loadWorkspaceQuoteCases({polling:true});
assert.equal(quoteStatus.textContent.includes('\u05de\u05e6\u05d1 \u05e1\u05d9\u05d5\u05dd'), true, 'A real QuoteCase poll must announce a transition into a terminal status.');
assert.equal(quoteGrid.children[0].className.includes('is-advancing'), false, 'A real terminal poll transition must remain free of positive motion.');
context.__quoteTransitionRequest = quoteTransitionAgentRequestOriginal;
vm.runInContext('agentApiRequest = globalThis.__quoteTransitionRequest', context);
delete context.__quoteTransitionPrevious;

const terminalQuoteCard = quoteGrid.children[0];
documentStub.activeElement = {dataset:{workspaceQuoteAction:'open'},closest:() => terminalQuoteCard};
context.renderWorkspaceQuoteCases([]);
assert.equal(quoteEmptyLink.focusCalls, 1, 'Removing the last focused assistance case must move focus to its empty-state action.');

context.renderWorkspaceQuoteCases([quoteOne]);
const originalQuoteHandoffRequest = context.requestQuoteCaseHandoff;
const originalQuoteReconcile = context.reconcileWorkspaceQuoteCases;
const timeoutCard = quoteGrid.children[0];
const timeoutAction = context.findWorkspaceDatasetElement(timeoutCard, 'workspaceQuoteAction', 'handoff');
timeoutAction.dataset.idempotencyKey = 'ambiguous-timeout-key';
context.__quoteTimeoutPopupCloses = 0;
let rejectQuoteTimeoutRequest;
context.__quoteTimeoutRequest = () => new Promise((resolve, reject) => { rejectQuoteTimeoutRequest = reject; });
context.__quoteTimeoutReconcile = async () => { context.__quoteTimeoutReconciles = (context.__quoteTimeoutReconciles || 0) + 1; return true; };
windowStub.open = () => ({opener:{},location:{replace() {}},close() { context.__quoteTimeoutPopupCloses += 1; }});
vm.runInContext(`
  requestQuoteCaseHandoff = globalThis.__quoteTimeoutRequest;
  reconcileWorkspaceQuoteCases = globalThis.__quoteTimeoutReconcile;
`, context);
documentStub.activeElement = timeoutAction;
const timeoutClick = timeoutAction.listeners.get('click')[0]();
await Promise.resolve();
context.renderWorkspaceQuoteCases([quoteOne]);
const timeoutReplacementAction = context.findWorkspaceDatasetElement(quoteGrid.children[0], 'workspaceQuoteAction', 'handoff');
assert.equal(timeoutReplacementAction.disabled && timeoutReplacementAction.getAttribute('aria-busy') === 'true', true, 'A replacement handoff control must remain disabled while its mutation is unresolved.');
assert.equal(timeoutReplacementAction.dataset.idempotencyKey, 'ambiguous-timeout-key', 'A replacement handoff control must inherit the in-flight idempotency key.');
const postClickFocusTarget = new FakeElement('button');
postClickFocusTarget.isConnected = true;
documentStub.activeElement = postClickFocusTarget;
const quoteTimeoutError = new Error('timeout');
quoteTimeoutError.code = 'quote_case_handoff_timeout';
quoteTimeoutError.timedOut = true;
rejectQuoteTimeoutRequest(quoteTimeoutError);
await timeoutClick;
const timeoutReplacementStatus = context.findWorkspaceClassElement(quoteGrid.children[0], 'workspace-quote-action-status');
assert.equal(context.__quoteTimeoutPopupCloses, 1, 'An ambiguous QuoteCase handoff timeout must close its pre-opened popup.');
assert.equal(context.__quoteTimeoutReconciles, 1, 'An ambiguous QuoteCase handoff timeout must reconcile the case list once.');
assert.equal(timeoutReplacementAction.dataset.idempotencyKey, 'ambiguous-timeout-key', 'An ambiguous QuoteCase handoff timeout must retain its idempotency key across card replacement.');
assert.equal(timeoutReplacementAction.disabled, false, 'The retained-key handoff action must become retryable after timeout cleanup.');
assert.equal(timeoutReplacementAction.focusCalls, 0, 'Handoff cleanup must not steal focus after the traveler moves to another live control.');
assert.equal(timeoutReplacementStatus.textContent.includes('15'), true, 'An ambiguous QuoteCase handoff timeout must explain the shared deadline accurately.');

context.__quoteTimeoutRequest = originalQuoteHandoffRequest;
context.__quoteTimeoutReconcile = originalQuoteReconcile;
vm.runInContext('requestQuoteCaseHandoff = globalThis.__quoteTimeoutRequest; reconcileWorkspaceQuoteCases = globalThis.__quoteTimeoutReconcile', context);
context.renderWorkspaceQuoteCases([quoteOne]);
const conflictCard = quoteGrid.children[0];
const conflictAction = context.findWorkspaceDatasetElement(conflictCard, 'workspaceQuoteAction', 'handoff');
const conflictStatus = context.findWorkspaceClassElement(conflictCard, 'workspace-quote-action-status');
conflictAction.dataset.idempotencyKey = 'stale-key';
windowStub.open = () => ({opener:{},location:{replace() {}},close() {}});
context.__quoteConflictRequest = async () => { const error = new Error('conflict'); error.status = 409; throw error; };
context.__quoteConflictReconcile = async () => { context.__quoteConflictReconciles = (context.__quoteConflictReconciles || 0) + 1; return true; };
vm.runInContext(`
  requestQuoteCaseHandoff = globalThis.__quoteConflictRequest;
  reconcileWorkspaceQuoteCases = globalThis.__quoteConflictReconcile;
`, context);
await conflictAction.listeners.get('click')[0]();
assert.equal(context.__quoteConflictReconciles, 1, 'A QuoteCase handoff 409 must reconcile the list before enabling retry.');
assert.equal(conflictAction.dataset.idempotencyKey, undefined, 'A confirmed version conflict must discard the stale handoff idempotency key.');
assert.equal(vm.runInContext('workspaceQuoteCaseMutationRegistry.size', context), 0, 'The per-case mutation lock must be released after reconciliation.');
assert.equal(conflictStatus.textContent.includes('הרשימה סונכרנה'), true, 'The reconciled 409 copy must describe the action truthfully.');

context.__quoteConflictRequest = originalQuoteHandoffRequest;
context.__quoteConflictReconcile = originalQuoteReconcile;
vm.runInContext('requestQuoteCaseHandoff = globalThis.__quoteConflictRequest; reconcileWorkspaceQuoteCases = globalThis.__quoteConflictReconcile', context);

const overlapOriginalAgentRequest = context.agentApiRequest;
const overlapUpdatedCase = makeQuoteCase('case-overlap',{status:'in_review',version:2,updated_at:'2026-07-18T10:03:00Z'});
const overlapOldCase = makeQuoteCase('case-overlap',{status:'queued',version:1,updated_at:'2026-07-18T10:00:00Z'});
let resolveOverlapOldPoll;
let resolveOverlapHandoff;
context.__quoteOverlapGetCount = 0;
context.__quoteOverlapAgentRequest = async () => {
  context.__quoteOverlapGetCount += 1;
  if (context.__quoteOverlapGetCount === 1) return new Promise(resolve => { resolveOverlapOldPoll = resolve; });
  return {cases:[overlapUpdatedCase]};
};
context.__quoteOverlapHandoff = () => new Promise(resolve => { resolveOverlapHandoff = resolve; });
context.__quoteOverlapOldCase = overlapOldCase;
vm.runInContext(`
  agentApiRequest = globalThis.__quoteOverlapAgentRequest;
  requestQuoteCaseHandoff = globalThis.__quoteOverlapHandoff;
  reconcileWorkspaceQuoteCases = globalThis.__quoteTimeoutReconcile;
  workspaceQuoteCaseRuntime.snapshot = new Map([['case-overlap',{case_id:'case-overlap',status:'queued',version:0,resume_available:true}]]);
  workspaceQuoteCaseRuntime.cases = new Map([['case-overlap',globalThis.__quoteOverlapOldCase]]);
  workspaceQuoteCaseRuntime.reconcileWaiters = [];
  workspaceQuoteCaseRuntime.inFlight = false;
  workspaceQuoteCaseRuntime.authRequired = false;
`, context);
context.__quoteTimeoutReconcile = originalQuoteReconcile;
vm.runInContext('reconcileWorkspaceQuoteCases = globalThis.__quoteTimeoutReconcile', context);
context.renderWorkspaceQuoteCases([overlapOldCase]);
const overlapOldPoll = context.loadWorkspaceQuoteCases({polling:true});
await Promise.resolve();
const overlapOriginalAction = context.findWorkspaceDatasetElement(quoteGrid.children[0], 'workspaceQuoteAction', 'handoff');
const overlapPopup = {opener:{},location:{replaced:'',replace(value) { this.replaced = String(value); }},closeCalls:0,close() { this.closeCalls += 1; }};
windowStub.open = () => overlapPopup;
documentStub.activeElement = overlapOriginalAction;
const overlapHandoffClick = overlapOriginalAction.listeners.get('click')[0]();
await Promise.resolve();
const overlapIdempotencyKey = overlapOriginalAction.dataset.idempotencyKey;
context.renderWorkspaceQuoteCases([overlapOldCase]);
const overlapReplacementWhilePending = context.findWorkspaceDatasetElement(quoteGrid.children[0], 'workspaceQuoteAction', 'handoff');
assert.equal(overlapReplacementWhilePending.disabled, true, 'An overlapping list render must preserve the handoff mutation lock.');
assert.equal(overlapReplacementWhilePending.dataset.idempotencyKey, overlapIdempotencyKey, 'An overlapping list render must preserve the exact handoff idempotency key.');
resolveOverlapHandoff({case:overlapUpdatedCase,handoff_url:'https://api.whatsapp.com/send?phone=972500000000'});
await Promise.resolve();
await Promise.resolve();
assert.equal(vm.runInContext('workspaceQuoteCaseRuntime.reconcileWaiters.length', context), 1, 'A successful handoff must queue a guaranteed fresh list request behind an older in-flight poll.');
resolveOverlapOldPoll({cases:[overlapOldCase]});
await overlapOldPoll;
await overlapHandoffClick;
assert.equal(context.__quoteOverlapGetCount, 2, 'Queued handoff reconciliation must issue one fresh GET after the overlapping GET settles.');
assert.equal(quoteGrid.children[0].dataset.version, '2', 'A stale overlapping GET must not repaint a lower QuoteCase version after handoff success.');
const overlapSettledAction = context.findWorkspaceDatasetElement(quoteGrid.children[0], 'workspaceQuoteAction', 'handoff');
assert.equal(overlapSettledAction.disabled, false, 'The handoff action may re-enable only after guaranteed post-success reconciliation.');
assert.equal(overlapSettledAction.dataset.idempotencyKey, undefined, 'Successful handoff reconciliation must clear the consumed idempotency key from replacement controls.');
assert.equal(overlapPopup.location.replaced.includes('api.whatsapp.com'), true, 'A successful handoff must still navigate its pre-opened popup.');
assert.equal(overlapPopup.closeCalls, 0, 'A successful handoff must not close its navigated popup during reconciliation.');
context.__quoteOverlapAgentRequest = overlapOriginalAgentRequest;
context.__quoteOverlapHandoff = originalQuoteHandoffRequest;
vm.runInContext('agentApiRequest = globalThis.__quoteOverlapAgentRequest; requestQuoteCaseHandoff = globalThis.__quoteOverlapHandoff', context);

const quoteAgentRequestOriginal = context.agentApiRequest;
context.__quoteAuthRequest = async () => { const error = new Error('expired'); error.status = 403; throw error; };
vm.runInContext(`
  agentApiRequest = globalThis.__quoteAuthRequest;
  workspaceQuoteCaseRuntime.authRequired = false;
  workspaceQuoteCaseRuntime.inFlight = false;
  workspaceQuoteCaseRuntime.timer = 0;
`, context);
await context.loadWorkspaceQuoteCases({polling:true});
assert.equal(quoteRoot.dataset.state, 'reauth_required', 'QuoteCase list polling must enter re-authentication state on 401/403.');
assert.equal(vm.runInContext('workspaceQuoteCaseRuntime.authRequired', context), true, 'Expired QuoteCase access must stop its automatic polling lifecycle.');
assert.equal(vm.runInContext('workspaceQuoteCaseRuntime.timer', context), 0, 'QuoteCase authorization failure must not schedule another retry.');
context.__quoteAuthRequest = quoteAgentRequestOriginal;
vm.runInContext('agentApiRequest = globalThis.__quoteAuthRequest; workspaceQuoteCaseRuntime.authRequired = false', context);
delete windowStub.open;
delete context.__quoteConflictRequest;
delete context.__quoteConflictReconcile;
delete context.__quoteConflictReconciles;
delete context.__quoteAuthRequest;
delete context.__quoteTransitionRequest;
delete context.__quoteTimeoutRequest;
delete context.__quoteTimeoutReconcile;
delete context.__quoteTimeoutReconciles;
delete context.__quoteTimeoutPopupCloses;
delete context.__quoteOverlapAgentRequest;
delete context.__quoteOverlapHandoff;
delete context.__quoteOverlapOldCase;
delete context.__quoteOverlapGetCount;
documentStub.activeElement = null;
documentQueries.delete('[data-workspace-quote-cases]');
documentQueries.delete('[data-workspace-quote-grid]');

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
const vmJson = expression => JSON.parse(vm.runInContext(`JSON.stringify(${expression})`, context));

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
assert.deepEqual(vmJson('syncHomeSearchTripContext(document.querySelector("[data-home-search]"))'), {
  product: 'package',
  origin: 'TLV',
  departureDate: '2026-10-01',
  returnDate: '2026-10-08',
  adults: 2,
  children: 1,
  rooms: 1
}, 'The visible homepage controls must become the authoritative sanitized trip context.');
assert.deepEqual(vmJson("validDiscoveryBudgetQuery(new URLSearchParams('?budget=1200'))"), {budget:1200}, 'A valid existing budget query must be preserved for downstream planning links.');
for (const invalidBudget of ['199', '1601', '1200.5', 'free']) {
  assert.deepEqual(vmJson(`validDiscoveryBudgetQuery(new URLSearchParams('?budget=${invalidBudget}'))`), {}, `Invalid budget ${invalidBudget} must not leak into downstream planning links.`);
}

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
home.adults.value = '4';
home.rooms.value = '2';
home.form.dispatch('change');
assert.deepEqual(vmJson('discoveryTripContext'), {
  product: 'package',
  origin: 'TLV',
  departureDate: '2026-10-01',
  returnDate: '2026-10-08',
  adults: 4,
  children: 1,
  rooms: 2
}, 'A live homepage edit must replace restored URL context before the traveler opens a component.');
home.adults.value = '2';
home.rooms.value = '1';
home.form.dispatch('change');

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

const assistedInsuranceForm = {
  dataset: {tripDestination:'dubai'},
  elements: {
    destination: {value:''}, start_date: {value:'2026-11-05'}, end_date: {value:'2026-11-12'},
    adults: {value:'2'}, children: {value:'1'}, trip_type: {value:'family'}, oldest_age: {value:'63'},
    baggage: {value:'true'}, cancellation: {value:'true'}
  }
};
const assistedInsuranceUrl = new URL(context.experiencePersonalCheckUrl(assistedInsuranceForm, 'insurance', '/ai-planner/'));
assert.equal(assistedInsuranceUrl.pathname, '/ai-planner/', 'An unsupported insurance destination must continue to an active planner intake.');
assert.deepEqual(Object.fromEntries(assistedInsuranceUrl.searchParams), {
  product: 'insurance', scope: 'insurance', destination: 'dubai', departure_date: '2026-11-05',
  return_date: '2026-11-12', adults: '2', children: '1', intent: 'family'
}, 'The assisted insurance handoff must preserve only destination, dates, party and non-sensitive planning context.');
assert.equal(context.commercialSearchNeedsPersonalCheck({status:422,code:'unsupported_destination'}), true, 'Unsupported commercial coverage must unlock the personal-check path.');
assert.equal(context.commercialSearchNeedsPersonalCheck({status:503,code:'provider_timeout'}), false, 'A transport or provider failure must not be misrepresented as a no-result personal-check state.');
const personalCheckMarkup = experiencePageSource.split(/\r?\n/u).find(line => line.includes('data-experience-personal-check')) || '';
assert.match(personalCheckMarkup, /\shidden(?:\s|>)/u, 'The actual personal-check CTA must start hidden in server-rendered markup.');
const personalCheckLink = new FakeElement('a');
personalCheckLink.hidden = true;
personalCheckLink.dataset.product = 'flights';
personalCheckLink.dataset.plannerBase = '/ai-planner/';
const personalCheckForm = {
  dataset: {},
  elements: {
    destination: {value:'BUD'}, origin: {value:'TLV'}, departure_date: {value:'2026-11-05'},
    return_date: {value:'2026-11-12'}, adults: {value:'2'}, children: {value:'0'}
  }
};
documentQueries.set('[data-experience-personal-check]', personalCheckLink);
context.setExperiencePersonalCheck(personalCheckForm, context.commercialSearchResultNeedsPersonalCheck({meta:{result_count:3}}), 'flights');
assert.equal(personalCheckLink.hidden, true, 'A successful result set must keep the personal-check CTA hidden.');
context.setExperiencePersonalCheck(personalCheckForm, context.commercialSearchNeedsPersonalCheck({status:503,code:'provider_timeout'}), 'flights');
assert.equal(personalCheckLink.hidden, true, 'A provider failure must keep the personal-check CTA hidden for retry.');
context.setExperiencePersonalCheck(personalCheckForm, context.commercialSearchResultNeedsPersonalCheck({meta:{result_count:0}}), 'flights');
assert.equal(personalCheckLink.hidden, false, 'An executed no-result fixture must reveal the personal-check CTA.');
personalCheckLink.hidden = true;
context.setExperiencePersonalCheck(personalCheckForm, context.commercialSearchNeedsPersonalCheck({status:422,code:'unsupported_destination'}), 'flights');
assert.equal(personalCheckLink.hidden, false, 'An executed unsupported-result fixture must reveal the personal-check CTA.');
documentQueries.delete('[data-experience-personal-check]');
for (const europeanDestination of ['budapest', 'prague', 'vienna', 'athens', 'lisbon']) {
  assert.match(experiencePageSource, new RegExp(`insurance_context_ready[\\s\\S]{0,260}'${europeanDestination}'`), `${europeanDestination} must use the existing Europe insurance context.`);
}
assert.match(experiencePageSource, /data-assisted-url=.*?data-trip-destination=/, 'Non-Europe insurance contexts must expose an actionable planner handoff instead of a disabled dead end.');

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

vm.runInContext('agentRuntime.events = []', context);
const statusOnlyJourney = runtimeJson("computeAgentJourney({run_id:'journey-status-only',status:'completed',trip_request:{revision:2}})");
assert.equal(statusOnlyJourney.readiness, 'complete', 'A structured trip request may confirm readiness.');
for (const protectedStep of ['supplier_search', 'proposal', 'approval', 'execution']) {
  assert.equal(statusOnlyJourney[protectedStep], 'pending', `A broad run status alone must not advance protected ${protectedStep} progress.`);
}
vm.runInContext(`agentRuntime.events = [
  {event_id:'supplier-proof',sequence:1,phase:'supplier_search',status:'completed',visible:true},
  {event_id:'proposal-proof',sequence:2,phase:'proposal',status:'completed',visible:true},
  {event_id:'approval-proof',sequence:3,phase:'approval',status:'completed',visible:true},
  {event_id:'execution-proof',sequence:4,phase:'execution',status:'completed',visible:true}
]`, context);
const evidenceJourney = runtimeJson("computeAgentJourney({run_id:'journey-evidence',status:'completed',trip_request:{revision:2}})");
for (const protectedStep of ['supplier_search', 'proposal', 'approval', 'execution']) {
  assert.equal(evidenceJourney[protectedStep], 'complete', `A matching recorded phase update may advance protected ${protectedStep} progress.`);
}

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
assert(cssSource.includes('.map-progress-checkpoints li,.map-progress-checkpoints li > :is(i,svg),.home-globe-stack .globe-selection-point::after,.theme-map-shell .globe-selection-point::after'), 'Map progress must preserve state while removing motion for reduced-motion users.');
const baseGlobeRuntimeRule = cssSource.match(/(?:^|\n)\.globe\s*\{([^}]*)\}/)?.[1] || '';
assert.doesNotMatch(baseGlobeRuntimeRule, /url\([^)]*earth-blue-marble/i, 'The WebGL loading surface must not paint a second Earth below the canvas.');
assert.match(cssSource, /\.globe-webgl\.globe-3d-unavailable \{ background-image: url\('\.\.\/images\/earth-blue-marble-2048\.jpg'\);/, 'The static Earth must be reserved for an explicit unavailable state.');
assert.match(frontPageSource, /data-home-globe[\s\S]*?<canvas data-globe-canvas[^>]*><\/canvas>\s*<noscript><img class="globe-noscript-image"[\s\S]*?data-globe-selection-point/, 'The homepage Earth needs a no-JavaScript fallback and a dedicated selected-coordinate marker.');
assert.match(cssSource, /@media \(max-width: 760px\)[\s\S]*?\.agent-journey-head > div:first-child > span,[^{}]+\{ font-size: 11px; \}[\s\S]*?\.agent-journey-next small \{ font-size: 11px; \}/, 'Narrow-screen journey labels must remain readable.');

const bangkokDestinationLink = new FakeElement('a');
bangkokDestinationLink.dataset.destination = 'bangkok';
bangkokDestinationLink.classList.add('is-active');
bangkokDestinationLink.setAttribute('aria-current', 'true');
const budapestDestinationLink = new FakeElement('a');
budapestDestinationLink.dataset.destination = 'budapest';
documentQueryLists.set('[data-map-destination-link][data-destination]', [bangkokDestinationLink, budapestDestinationLink]);
context.syncMapDestinationLinks('budapest');
assert.equal(bangkokDestinationLink.classList.contains('is-active'), false, 'The semantic destination index must clear its previous committed destination.');
assert.equal(bangkokDestinationLink.getAttribute('aria-current'), null, 'The semantic destination index must remove stale aria-current state.');
assert.equal(budapestDestinationLink.classList.contains('is-active'), true, 'The semantic destination index must mirror the committed destination without consulting marker visibility.');
assert.equal(budapestDestinationLink.getAttribute('aria-current'), 'true', 'The semantic destination index must expose its committed destination to assistive technology.');
assert.match(appSource, /syncMapDestinationLinks\(key\);/, 'Every destination commit must synchronize the external semantic destination index.');

const dynamicDestinationLink = new FakeElement('a');
dynamicDestinationLink.dataset.destination = 'dynamic-island';
dynamicDestinationLink.href = 'https://tra-vel.co.il/travel-map/?destination=dynamic-island#destination-plan-title';
const dynamicDestinationPin = new FakeElement('button');
dynamicDestinationPin.dataset.destination = 'dynamic-island';
documentQueries.set('[data-discovery-globe] .price-pin[data-destination="dynamic-island"]', dynamicDestinationPin);
context.destinationIndexActivationCalls = [];
vm.runInContext(`
  globalThis.__destinationIndexOriginals = {
    clearActiveMapEntitySelection,
    setActivePlanningSelection,
    setActiveDestination,
    syncDiscoveryUrl,
    discoveryRequestParams,
    hydrateDiscovery
  };
  destinationData['dynamic-island'] = {id:'dynamic-island',city:'Dynamic Island',latitude:17.25,longitude:-61.8};
  clearActiveMapEntitySelection = () => destinationIndexActivationCalls.push({type:'clear'});
  setActivePlanningSelection = selection => destinationIndexActivationCalls.push({type:'planning',selection});
  setActiveDestination = (destination, pin, options) => destinationIndexActivationCalls.push({type:'active',destination,pin:pin?.dataset?.destination || '',options});
  syncDiscoveryUrl = mode => destinationIndexActivationCalls.push({type:'url',mode});
  discoveryRequestParams = params => ({...params,contract:'runtime'});
  hydrateDiscovery = params => destinationIndexActivationCalls.push({type:'hydrate',params});
`, context);
context.bindMapDestinationLink(dynamicDestinationLink);
context.bindMapDestinationLink(dynamicDestinationLink);
const dynamicDestinationClick = dynamicDestinationLink.dispatch('click');
assert.equal(dynamicDestinationClick.defaultPrevented, true, 'A JavaScript-enabled destination-index choice must stay in place instead of reloading the map page.');
assert.equal(dynamicDestinationLink.dataset.selectionBound, 'true', 'A dynamic destination-index choice must bind exactly once.');
assert.deepEqual(JSON.parse(JSON.stringify(context.destinationIndexActivationCalls)), [
  {type:'clear'},
  {type:'planning',selection:{latitude:17.25,longitude:-61.8,destination:'dynamic-island',kind:'destination'}},
  {type:'active',destination:'dynamic-island',pin:'dynamic-island',options:{animate:true,responseConfirmed:false,userSelected:true}},
  {type:'url',mode:'push'},
  {type:'hydrate',params:{destination:'dynamic-island',contract:'runtime'}}
], 'Destination-index activation must commit the same editable destination and hydrate its 360-degree route for any valid collection member.');
vm.runInContext(`
  ({clearActiveMapEntitySelection,setActivePlanningSelection,setActiveDestination,syncDiscoveryUrl,discoveryRequestParams,hydrateDiscovery} = globalThis.__destinationIndexOriginals);
  delete destinationData['dynamic-island'];
  delete globalThis.__destinationIndexOriginals;
`, context);
delete context.destinationIndexActivationCalls;
documentQueries.delete('[data-discovery-globe] .price-pin[data-destination="dynamic-island"]');

assert.match(cssSource, /\.theme-map-shell \.map-destination-index \{ position: static;[^}]*grid-template-columns: auto minmax\(0,1fr\)/, 'The desktop destination index must remain in flow and wrap beside its label.');
assert.match(cssSource, /\.theme-map-shell \.map-destination-index a \{[^}]*min-height: 44px;/, 'Every destination-index option must meet the 44px touch-target floor.');
assert.match(cssSource, /\.theme-map-shell \.map-destination-index a:focus-visible \{[^}]*outline:/, 'Destination-index keyboard focus must remain plainly visible.');
assert.match(cssSource, /@media \(max-width: 760px\)[\s\S]*?\.theme-map-shell \.map-destination-index > div \{[^}]*flex-wrap: nowrap;[^}]*overflow-x: auto;/, 'The mobile destination index must become an in-flow horizontal chooser.');
assert.match(cssSource, /@media \(max-width: 760px\)[\s\S]*?\.theme-map-shell \.map-destination-panel \{[^}]*display: flex;[^}]*flex-direction: column;[^}]*\}[\s\S]*?\.theme-map-shell \.map-destination-copy \{ order: 1; \}[\s\S]*?\.theme-map-shell \.map-destination-panel > img \{[^}]*order: 2;[^}]*position: static;/, 'Mobile selected-plan copy must precede the non-overlay destination image.');

windowStub.location.search = '?product=insurance&origin=JFK&start_date=2026-12-04&end_date=2026-12-10&adults=6&children=0&rooms=3&budget=1200&intent=family';
context.readDiscoveryStateFromUrl();
context.syncHomeSearchTripContext(home.form);
assert.deepEqual(vmJson('discoveryTripContext'), {
  product: 'package',
  origin: 'TLV',
  departureDate: '2026-10-01',
  returnDate: '2026-10-08',
  adults: 2,
  children: 1,
  rooms: 1
}, 'Current visible homepage values must override stale URL trip values while URL intent and budget remain available.');

const fullHomePlan = new FakeElement();
const fullHomeSummary = new FakeElement();
const fullHomeLedgerState = new FakeElement();
const fullHomeCtaLabel = new FakeElement();
const fullHomeLinks = Object.fromEntries([
  'flight',
  'stay',
  'transfer',
  'activity',
  'dining',
  'insurance',
  'connectivity',
  'equipment',
  'extras',
  'ai',
  'full'
].map(name => {
  const link = new FakeElement('a');
  link.setAttribute('aria-disabled', 'true');
  return [name, link];
}));
documentQueries.set('[data-home-plan]', fullHomePlan);
documentQueries.set('[data-home-plan-summary]', fullHomeSummary);
documentQueries.set('[data-home-plan-ledger-state]', fullHomeLedgerState);
documentQueries.set('[data-home-plan-full-label]', fullHomeCtaLabel);
for (const [name, link] of Object.entries(fullHomeLinks)) documentQueries.set(`[data-home-plan-${name}]`, link);
vm.runInContext(`
  destinationData.vienna = {id:'vienna',city:'וינה',airportCode:'VIE',hotelArea:'Innere Stadt',url:'https://tra-vel.co.il/destinations/vienna/'};
  activePlanningSelection = {selection_id:'selection-vienna-runtime',kind:'destination',latitude:48.2082,longitude:16.3738,destination:'vienna'};
  activePlanIntent = 'family';
`, context);
context.updateHomeDestinationPlan({
  id: 'vienna',
  city: 'וינה',
  airportCode: 'VIE',
  hotelArea: 'Innere Stadt',
  url: 'https://tra-vel.co.il/destinations/vienna/'
}, false);

const homeLinkUrl = name => new URL(fullHomeLinks[name].href);
assert.equal(fullHomePlan.dataset.destination, 'vienna', 'A homepage plan update must commit the selected destination identity.');
assert.equal(fullHomePlan.getAttribute('aria-busy'), null, 'A synchronous homepage plan update must not claim network-style busy state.');
assert.match(fullHomeSummary.textContent, /וינה/, 'The editable homepage plan summary must name the selected destination.');
assert.equal(fullHomeLedgerState.textContent, '8 רכיבים בתכנון. המחיר, הזמינות והתנאים ייבדקו לפני רכישה.', 'The eight-component ledger must retain its truthful purchase-time validation boundary after a destination change.');
assert.equal(fullHomeCtaLabel.textContent, 'פתחו את התכנון המלא לוינה', 'The full-plan CTA must update to the selected destination.');
assert.equal(homeLinkUrl('flight').pathname, '/flights/', 'The flight component must open flight comparison.');
assert.equal(homeLinkUrl('flight').searchParams.get('destination'), 'VIE', 'The flight component must use the comparison airport code.');
assert.equal(homeLinkUrl('stay').pathname, '/hotels/', 'The stay component must open hotel comparison.');
assert.equal(homeLinkUrl('stay').searchParams.get('destination'), 'VIE', 'The stay component must use the comparison airport code.');
assert.equal(homeLinkUrl('stay').searchParams.get('area'), 'Innere Stadt', 'The stay component must retain the destination area context.');
assert.equal(homeLinkUrl('activity').pathname, '/destinations/vienna/', 'The activities component must use the reviewed destination URL when one exists.');
assert.equal(homeLinkUrl('ai').pathname, '/ai-planner/', 'The primary planner component must open the AI planner.');
assert.equal(homeLinkUrl('ai').searchParams.get('destination'), 'vienna', 'The primary planner component must preserve the destination slug.');
assert.equal(homeLinkUrl('ai').searchParams.get('scope'), 'flights,accommodation,transfers,activities,dining,insurance,connectivity,equipment', 'The primary planner component must request all eight trip domains.');
assert.equal(homeLinkUrl('transfer').pathname, '/packages/', 'The transfer component must open the complete-trip composer.');
assert.equal(homeLinkUrl('transfer').searchParams.get('destination'), 'VIE', 'The transfer component must preserve the package comparison airport code.');
assert.equal(homeLinkUrl('transfer').searchParams.get('transfers'), '1', 'The transfer component must explicitly request transfers.');
assert.equal(homeLinkUrl('insurance').pathname, '/travel-insurance/', 'The insurance component must open travel-insurance comparison.');
assert.equal(homeLinkUrl('insurance').searchParams.get('trip_destination'), 'vienna', 'The insurance component must use the destination slug expected by its product contract.');
for (const component of ['dining', 'connectivity', 'equipment']) {
  assert.equal(homeLinkUrl(component).pathname, '/ai-planner/', `${component} must open the planner.`);
  assert.equal(homeLinkUrl(component).searchParams.get('destination'), 'vienna', `${component} must preserve the destination slug.`);
  assert.equal(homeLinkUrl(component).searchParams.get('scope'), component, `${component} must retain an independent planning scope.`);
}
assert.equal(homeLinkUrl('extras').pathname, '/ai-planner/', 'The optional supporting-services shortcut must open the planner.');
assert.equal(homeLinkUrl('extras').searchParams.get('scope'), 'dining,connectivity,equipment', 'The supporting-services shortcut may group the same three independently editable domains without replacing them.');
assert.equal(homeLinkUrl('full').pathname, '/travel-map/', 'The full-plan CTA must return to the interactive travel map.');
assert.equal(homeLinkUrl('full').searchParams.get('destination'), 'vienna', 'The full-plan CTA must preserve the selected destination.');
assert.equal(homeLinkUrl('full').searchParams.get('scope'), 'flights,accommodation,transfers,activities,dining,insurance,connectivity,equipment', 'The full-plan CTA must preserve all eight independent planning domains.');
for (const [name, link] of Object.entries(fullHomeLinks)) {
  const url = new URL(link.href);
  assert.equal(url.searchParams.get('budget'), '1200', `${name} must preserve the valid existing budget query.`);
  assert.equal(url.searchParams.get('adults'), '2', `${name} must preserve the current adult count.`);
  assert.equal(url.searchParams.get('children'), '1', `${name} must preserve the current child count.`);
  assert.equal(url.searchParams.get('selection_id'), 'selection-vienna-runtime', `${name} must preserve the selected Earth identity.`);
  assert.equal(url.searchParams.get('selection_kind'), 'destination', `${name} must preserve the selected Earth kind.`);
  assert.equal(url.searchParams.get('latitude'), '48.2082', `${name} must preserve the selected latitude.`);
  assert.equal(url.searchParams.get('longitude'), '16.3738', `${name} must preserve the selected longitude.`);
  assert.equal(url.searchParams.get('intent'), 'family', `${name} must preserve the existing planning intent.`);
}
for (const name of ['flight', 'transfer', 'ai', 'activity', 'dining', 'connectivity', 'equipment', 'extras', 'full']) {
  assert.equal(homeLinkUrl(name).searchParams.get('origin'), 'TLV', `${name} must preserve the applicable current origin.`);
}
for (const name of ['stay', 'insurance']) assert.equal(homeLinkUrl(name).searchParams.has('origin'), false, `${name} must omit the inapplicable flight origin.`);
assert.deepEqual(Object.fromEntries(['flight', 'transfer', 'ai', 'activity', 'dining', 'connectivity', 'equipment', 'extras', 'full'].map(name => [name, [homeLinkUrl(name).searchParams.get('departure_date'), homeLinkUrl(name).searchParams.get('return_date')]])), {
  flight: ['2026-10-01', '2026-10-08'],
  transfer: ['2026-10-01', '2026-10-08'],
  ai: ['2026-10-01', '2026-10-08'],
  activity: ['2026-10-01', '2026-10-08'],
  dining: ['2026-10-01', '2026-10-08'],
  connectivity: ['2026-10-01', '2026-10-08'],
  equipment: ['2026-10-01', '2026-10-08'],
  extras: ['2026-10-01', '2026-10-08'],
  full: ['2026-10-01', '2026-10-08']
}, 'Flight, package, map, and planner links must retain standard travel-date keys.');
assert.deepEqual([homeLinkUrl('stay').searchParams.get('checkin'), homeLinkUrl('stay').searchParams.get('checkout')], ['2026-10-01', '2026-10-08'], 'Hotel links must map the current dates to check-in and checkout.');
assert.deepEqual([homeLinkUrl('insurance').searchParams.get('start_date'), homeLinkUrl('insurance').searchParams.get('end_date')], ['2026-10-01', '2026-10-08'], 'Insurance links must map the current dates to coverage start and end.');
for (const name of ['stay', 'transfer', 'ai', 'activity', 'dining', 'connectivity', 'equipment', 'extras', 'full']) assert.equal(homeLinkUrl(name).searchParams.get('rooms'), '1', `${name} must preserve the applicable room count.`);
for (const name of ['flight', 'insurance']) assert.equal(homeLinkUrl(name).searchParams.has('rooms'), false, `${name} must omit the inapplicable room count.`);

home.adults.value = '5';
home.rooms.value = '2';
home.form.dispatch('change');
assert.equal(homeLinkUrl('flight').searchParams.get('adults'), '5', 'Changing a visible party control must immediately refresh component links.');
assert.equal(homeLinkUrl('stay').searchParams.get('rooms'), '2', 'Changing a visible rooms control must immediately refresh stay links.');
assert.equal(homeLinkUrl('full').searchParams.get('intent'), 'family', 'Refreshing from visible controls must not discard the existing URL intent.');
home.adults.value = '2';
home.rooms.value = '1';
home.form.dispatch('change');
for (const link of Object.values(fullHomeLinks)) assert.equal(link.getAttribute('aria-disabled'), null, 'Every homepage component must become actionable after a destination is selected.');
const homeUpdateFunctionSource = appSource.slice(appSource.indexOf('function updateHomeDestinationPlan'), appSource.indexOf('function initHomeDestinationReveal'));
assert.equal(homeUpdateFunctionSource.includes("setAttribute('aria-busy'"), false, 'Local homepage plan rendering must not toggle aria-busy.');
const destinationPlanFunctionSource = appSource.slice(appSource.indexOf('function updateDestinationPlan'), appSource.indexOf('function updateHomeDestinationPlan'));
assert.doesNotMatch(destinationPlanFunctionSource, /route:\s*activeRouteId/, 'Product and planner URLs must not receive a route parameter that their target pages do not consume.');
assert.match(destinationPlanFunctionSource, /selectedRoute\.label/, 'The selected route must remain visible in the editable plan even though it is not sent as an ignored query parameter.');
const coordinatePointContext = {
  selection_id: 'map-point-action-123', selection_kind: 'map_point', latitude: '11.2233', longitude: '44.5566',
  mode: 'map_point', intent: 'smart', adults: 4, rooms: 2
};
for (const scope of ['flights', 'accommodation', 'activities', 'insurance', 'connectivity', 'equipment', 'flights,accommodation,transfers,activities,dining,insurance,connectivity,equipment']) {
  const pointAction = new FakeElement('a');
  pointAction.setAttribute('aria-disabled', 'true');
  pointAction.setAttribute('tabindex', '-1');
  const actionUrl = new URL(context.setPointPlanningAction(pointAction, coordinatePointContext, scope));
  assert.equal(actionUrl.pathname, '/ai-planner/', `${scope} coordinate action must open the planner.`);
  assert.equal(actionUrl.searchParams.get('selection_id'), 'map-point-action-123', `${scope} coordinate action must preserve the selection id.`);
  assert.equal(actionUrl.searchParams.get('selection_kind'), 'map_point', `${scope} coordinate action must preserve point identity.`);
  assert.equal(actionUrl.searchParams.get('latitude'), '11.2233', `${scope} coordinate action must preserve latitude.`);
  assert.equal(actionUrl.searchParams.get('longitude'), '44.5566', `${scope} coordinate action must preserve longitude.`);
  assert.equal(actionUrl.searchParams.get('scope'), scope, `${scope} coordinate action must preserve its independent planning scope.`);
  assert.equal(pointAction.getAttribute('aria-disabled'), null, `${scope} coordinate action must be enabled.`);
  assert.equal(pointAction.getAttribute('tabindex'), null, `${scope} coordinate action must remain keyboard reachable.`);
}
assert.doesNotMatch(frontPageSource, /data-home-plan[^>]*aria-busy=/, 'The server-rendered homepage plan must begin without aria-busy.');
const homePlanMarkupStart = frontPageSource.indexOf('<div class="home-plan-360"');
const homePlanMarkupEnd = frontPageSource.indexOf('</details>', homePlanMarkupStart);
const homePlanMarkup = frontPageSource.slice(homePlanMarkupStart, homePlanMarkupEnd);
const homeComponentListEnd = homePlanMarkup.indexOf('</ul>');
const homeComponentMarkup = homePlanMarkup.slice(0, homeComponentListEnd);
const renderedHomePlanningDomains = Array.from(homeComponentMarkup.matchAll(/<a data-home-plan-component="([a-z-]+)"[^>]*href=/g), match => match[1]);
assert.deepEqual(renderedHomePlanningDomains, ['flights', 'accommodation', 'transfers', 'activities', 'dining', 'insurance', 'connectivity', 'equipment'], 'The homepage must server-render eight independent, crawlable planning records in the canonical domain order.');
assert.equal((homeComponentMarkup.match(/<small>/g) || []).length, 8, 'Every homepage planning record must expose its own unverified planning state.');
const homeComponentVisibleMarkup = homeComponentMarkup.replace(/<\?php[\s\S]*?\?>/g, '');
assert.doesNotMatch(homeComponentVisibleMarkup, /\$\s*\d|₪\s*\d|data-price|price=/i, 'Independent planning records must not invent per-component prices.');
assert.match(frontPageSource, /אלה אפשרויות לתכנון. המחיר, הזמינות והתנאים מאומתים לפני התשלום./, 'The eight-component plan must retain the pre-purchase revalidation boundary.');
assert.match(cssSource, /\.home-plan-modules a \{[^}]*min-height: 58px;/, 'Every editable planning record must meet the keyboard and touch target floor.');

assert.equal(context.hasKnownTripIntentQuery(new URLSearchParams('?budget=1200')), true, 'A budget query must suppress the automatic seasonal reveal.');
assert.equal(context.hasKnownTripIntentQuery(new URLSearchParams('?departure_date=2026-10-01&adults=2')), true, 'Dates or party criteria must suppress the automatic seasonal reveal.');
assert.equal(context.hasKnownTripIntentQuery(new URLSearchParams('?mode=surprise')), true, 'An explicit journey mode must suppress the automatic seasonal reveal.');
assert.equal(context.hasKnownTripIntentQuery(new URLSearchParams('?utm_source=newsletter')), false, 'Campaign attribution alone must not be misclassified as traveler intent.');

assert.match(appSource, /פותחים רעיון חדש לחופשה שתוכלו לערוך\./u, 'Deterministic Surprise motion must describe an editable idea without making a ranking claim.');
assert.doesNotMatch(appSource, /מחפשים כיוון שנותן יותר לחופשה/u, 'Deterministic Surprise motion cannot claim it found a direction that gives more.');

windowStub.matchMedia = () => ({ matches: false });
context.navigator.connection = { saveData: true };
assert.equal(context.prefersReducedMotion(), true, 'Save-Data must suppress general nonessential plan motion as well as reduced-motion preferences.');
const saveDataPlan = new FakeElement();
context.runConfirmedPlanAnimation(saveDataPlan, '.tail');
assert.equal(saveDataPlan.classList.contains('is-updating'), false, 'Save-Data must prevent confirmed-plan animation classes from starting.');
delete context.navigator.connection;

const revealGlobe = new FakeElement();
revealGlobe.classList.add('is-webgl-ready');
revealGlobe.dataset.defaultDestination = 'bangkok';
revealGlobe.dataset.campaignKind = 'seasonal';
const revealLiveRegion = new FakeElement();
const revealBangkokPin = new FakeElement('button');
const revealBudapestPin = new FakeElement('button');
revealGlobe.queries.set('[data-globe-live]', revealLiveRegion);
revealGlobe.queries.set('.price-pin[data-destination="bangkok"]', revealBangkokPin);
revealGlobe.queries.set('.price-pin[data-destination="budapest"]', revealBudapestPin);
const revealTrigger = new FakeElement('a');
const revealTriggerLabel = new FakeElement();
revealTrigger.queries.set('[data-home-surprise-label]', revealTriggerLabel);
const revealFeedback = new FakeElement();
const revealStatus = new FakeElement();
const revealCancel = new FakeElement('button');
revealFeedback.queries.set('[data-home-reveal-status]', revealStatus);
revealFeedback.queries.set('[data-home-reveal-cancel]', revealCancel);
const revealHomePlan = new FakeElement();
const revealPlanSummary = new FakeElement();
const revealAiLink = new FakeElement('a');
const revealAiLabel = new FakeElement();
revealHomePlan.queries.set('[data-home-plan-summary]', revealPlanSummary);
revealHomePlan.queries.set('[data-home-plan-ai]', revealAiLink);
revealHomePlan.queries.set('[data-home-plan-ai-label]', revealAiLabel);
documentQueries.set('[data-home-globe]', revealGlobe);
documentQueries.set('[data-home-surprise]', revealTrigger);
documentQueries.set('[data-home-reveal]', revealFeedback);
documentQueries.set('[data-home-plan]', revealHomePlan);
documentQueries.set('[data-home-search]', new FakeElement('form'));
context.revealSelections = [];
context.revealGlobeCalls = [];
vm.runInContext(`
  destinationData = {
    bangkok: {id:'bangkok',city:'בנגקוק'},
    budapest: {id:'budapest',city:'בודפשט'}
  };
  activeDestination = 'bangkok';
  setActiveDestination = (destination, pin, options = {}) => {
    revealSelections.push({destination, options});
    activeDestination = destination;
  };
`, context);
windowStub.traVelGlobe3D = {
  focusDestination(destination, options = {}) {
    context.revealGlobeCalls.push({type:'focus',destination,options});
    return true;
  },
  cancelMotion(rootElement) {
    context.revealGlobeCalls.push({type:'cancel',rootElement});
    return true;
  },
  pulseRoute(rootElement) {
    context.revealGlobeCalls.push({type:'pulse',rootElement});
  }
};
const homeFunnelEvents = [];
documentStub.addEventListener('tra-vel:funnel', event => homeFunnelEvents.push(event.detail));
windowStub.dataLayer = [];
vm.runInContext("activePlanningSelection = null; activePlanIntent = 'family';", context);

vm.runInContext(`activeDestination = 'budapest';`, context);
windowStub.matchMedia = () => ({ matches: true });
const explicitIntentReveal = context.initHomeDestinationReveal(Promise.resolve(), {autoEligible:false});
await explicitIntentReveal.state.hydration;
await Promise.resolve();
assert.equal(explicitIntentReveal.state.autoEligible, false, 'An explicit destination or restored traveler intent must suppress the automatic seasonal reveal.');
assert.equal(explicitIntentReveal.state.autoTimer, 0, 'Suppressed automatic reveal intent must not schedule a late roulette timer.');
await explicitIntentReveal.run('seasonal');
assert.equal(context.revealSelections.at(-1).destination, 'bangkok', 'A seasonal reveal must use the server-rendered Earth destination instead of a stale client default.');
assert.equal(await explicitIntentReveal.run('unknown-campaign-kind'), false, 'An unknown campaign kind must fail closed instead of becoming seasonal.');
context.revealSelections.length = 0;
context.revealGlobeCalls.length = 0;
revealLiveRegion._textContent = '';
revealLiveRegion.textWrites = 0;
vm.runInContext(`activeDestination = 'bangkok';`, context);

let settleInitialHydration;
const initialHydrationGate = new Promise(resolve => { settleInitialHydration = resolve; });
windowStub.matchMedia = () => ({ matches: true });
const reducedReveal = context.initHomeDestinationReveal(initialHydrationGate);
const reducedRevealRun = reducedReveal.run('surprise');
await Promise.resolve();
assert.equal(context.revealSelections.length, 0, 'Manual Surprise must wait for initial destination hydration before choosing a candidate.');
assert.equal(revealLiveRegion.textWrites, 0, 'Hydration and visual preparation must not create intermediate live announcements.');
settleInitialHydration();
await reducedRevealRun;
assert.equal(context.revealSelections.at(-1).destination, 'budapest', 'Surprise must choose from the hydrated destination set.');
assert.equal(context.revealSelections.at(-1).options.globeAnnounce, false, 'The reveal commit must leave the single live-region announcement to its state machine.');
assert.equal(revealLiveRegion.textWrites, 1, 'A reduced-motion reveal must make one atomic final announcement.');
assert.equal(reducedReveal.state.running, false, 'Reduced motion must commit immediately without leaving a running reveal.');
assert.equal(revealCancel.hidden, true, 'The Stop control must close as soon as the reduced-motion result commits.');
assert.equal(revealHomePlan.getAttribute('aria-busy'), null, 'Local reveal animation must not claim network-style busy state.');
const surprisePlannerUrl = new URL(revealAiLink.href);
assert.equal(surprisePlannerUrl.searchParams.get('budget'), '1200', 'The completed Surprise planner handoff must preserve the valid existing budget.');
assert.equal(surprisePlannerUrl.searchParams.get('origin'), 'TLV', 'The completed Surprise planner handoff must preserve the current visible origin.');
assert.equal(surprisePlannerUrl.searchParams.get('departure_date'), '2026-10-01', 'The completed Surprise planner handoff must preserve the current visible start date.');
assert.equal(surprisePlannerUrl.searchParams.get('return_date'), '2026-10-08', 'The completed Surprise planner handoff must preserve the current visible end date.');
assert.equal(surprisePlannerUrl.searchParams.get('adults'), '2', 'The completed Surprise planner handoff must preserve the current party.');
assert.equal(surprisePlannerUrl.searchParams.get('rooms'), '1', 'The completed Surprise planner handoff must preserve the current room count.');
assert.equal(surprisePlannerUrl.searchParams.get('intent'), 'family', 'The completed Surprise planner handoff must preserve the existing planning intent.');
assert.equal(surprisePlannerUrl.searchParams.get('mode'), 'surprise', 'The completed Surprise planner handoff must preserve the explicit journey mode.');

context.revealSelections.length = 0;
context.revealGlobeCalls.length = 0;
revealLiveRegion._textContent = '';
revealLiveRegion.textWrites = 0;
const baselinePlanSummary = 'תוכנית בנגקוק המקורית';
const baselineAiHref = 'https://tra-vel.co.il/ai-planner/?destination=bangkok';
const baselineAiLabel = 'סדרו לי חופשה מלאה';
revealPlanSummary.textContent = baselinePlanSummary;
revealAiLink.setAttribute('href', baselineAiHref);
revealAiLabel.textContent = baselineAiLabel;
vm.runInContext(`activeDestination = 'bangkok';`, context);
windowStub.matchMedia = () => ({ matches: false });
const movingReveal = context.initHomeDestinationReveal(Promise.resolve());
await movingReveal.state.hydration;
const movingRevealRun = movingReveal.run('surprise');
const previewCall = context.revealGlobeCalls.find(call => call.type === 'focus');
assert.equal(previewCall?.options?.announce, false, 'Preview spins must remain silent to the atomic live region.');
assert.equal(previewCall?.options?.root, revealGlobe, 'Preview motion must stay scoped to the homepage Earth.');
revealPlanSummary.textContent = 'תוכנית זמנית';
revealAiLink.setAttribute('href', 'https://tra-vel.co.il/ai-planner/?destination=budapest&mode=surprise');
revealAiLabel.textContent = 'הפכו את הרעיון לחופשה מלאה';
movingReveal.cancel();
assert.equal(context.revealGlobeCalls.some(call => call.type === 'cancel' && call.rootElement === revealGlobe), true, 'Stopping a reveal must cancel the homepage globe controller itself.');
assert.equal(context.revealSelections.at(-1).destination, 'bangkok', 'Stopping a reveal must restore the last committed destination and card.');
assert.equal(revealPlanSummary.textContent, baselinePlanSummary, 'Stopping a reveal must restore the last committed plan summary.');
assert.equal(revealAiLink.getAttribute('href'), baselineAiHref, 'Stopping a reveal must restore the last committed planner URL.');
assert.equal(revealAiLabel.textContent, baselineAiLabel, 'Stopping a reveal must restore the last committed planner label.');
assert.equal(movingReveal.state.running, false, 'A cancelled reveal must leave no running state.');
assert.equal(revealLiveRegion.textWrites, 1, 'Cancellation must produce one atomic acknowledgement without preview chatter.');
assert.equal(await movingRevealRun, false, 'A cancelled asynchronous reveal must not commit its late candidate.');

context.revealSelections.length = 0;
vm.runInContext(`activeDestination = 'bangkok';`, context);
revealGlobe.dataset.campaignKind = 'evergreen';
revealLiveRegion._textContent = '';
revealLiveRegion.textWrites = 0;
const scheduledAutoTimers = [];
const preAutoSetTimeout = windowStub.setTimeout;
windowStub.setTimeout = (callback, delay = 0) => {
  const timer = {callback, delay, id: 700 + scheduledAutoTimers.length};
  scheduledAutoTimers.push(timer);
  return timer.id;
};
windowStub.matchMedia = () => ({ matches: false });
context.navigator.connection = { saveData: true };
revealGlobe.classList.remove('is-webgl-ready');
const stalledHydration = new Promise(() => {});
const automaticReveal = context.initHomeDestinationReveal(stalledHydration, {autoEligible:true});
assert.equal(scheduledAutoTimers.length, 1, 'The automatic evergreen reveal must schedule once without waiting for hydration or globe texture readiness.');
assert.equal(scheduledAutoTimers[0].delay, 0, 'Save-Data must remove the automatic reveal animation delay.');
scheduledAutoTimers[0].callback();
scheduledAutoTimers[0].callback();
await Promise.resolve();
await Promise.resolve();
assert.equal(automaticReveal.state.hydrationSettled, false, 'The deterministic auto test must keep network hydration unresolved.');
assert.equal(automaticReveal.state.autoStarted, true, 'The single automatic reveal must record that it started.');
assert.equal(automaticReveal.state.campaignKind, 'evergreen', 'The automatic reveal must retain the server-rendered evergreen campaign kind.');
assert.equal(automaticReveal.state.running, false, 'Save-Data must commit the automatic result without leaving motion running.');
assert.deepEqual(context.revealSelections.map(selection => selection.destination), ['bangkok'], 'A repeated timer callback must still commit exactly one SSR evergreen discovery seed.');
assert.doesNotMatch(revealStatus.textContent, /לעונה/u, 'Evergreen reveal status must not claim seasonal relevance.');
assert.doesNotMatch(revealLiveRegion.textContent, /לעונה/u, 'Evergreen live-region copy must remain neutral.');
windowStub.setTimeout = preAutoSetTimeout;
delete context.navigator.connection;
revealGlobe.classList.add('is-webgl-ready');

windowStub.matchMedia = () => ({ matches: false });
const keyboardInterruptedRun = movingReveal.run('surprise');
documentStub.dispatchEvent({type:'keydown', key:'Tab', target:{closest:() => null}});
assert.equal(movingReveal.state.running, false, 'A global relevant keyboard action must stop a running reveal.');
assert.equal(await keyboardInterruptedRun, false, 'A keyboard-cancelled reveal must not commit a late candidate.');
const stopControlKeyboardRun = movingReveal.run('surprise');
documentStub.dispatchEvent({type:'keydown', key:'Escape', target:{closest:selector => selector === '[data-home-reveal-cancel]' ? revealCancel : null}});
assert.equal(movingReveal.state.running, false, 'Escape on the visible Stop control must stop a running reveal.');
assert.equal(await stopControlKeyboardRun, false, 'Stop-control keyboard cancellation must not commit a late candidate.');
const focusInterruptedRun = movingReveal.run('surprise');
documentStub.dispatchEvent({type:'focusin', target:{closest:() => null}});
assert.equal(movingReveal.state.running, false, 'A global focus change must stop a running reveal.');
assert.equal(await focusInterruptedRun, false, 'A focus-cancelled reveal must not commit a late candidate.');
const visibilityInterruptedRun = movingReveal.run('surprise');
documentStub.visibilityState = 'hidden';
documentStub.dispatchEvent({type:'visibilitychange', target:documentStub});
assert.equal(movingReveal.state.running, false, 'Hiding the document must stop a running reveal.');
assert.equal(await visibilityInterruptedRun, false, 'A visibility-cancelled reveal must not commit a late candidate.');
documentStub.visibilityState = 'visible';

revealHomePlan.dataset.destination = 'bangkok';
const componentOpenTarget = new FakeElement('a');
componentOpenTarget.dataset.homePlanComponent = 'dining';
componentOpenTarget.closest = selector => selector === '[data-home-plan-component]' ? componentOpenTarget : null;
revealHomePlan.dispatch('click', {button:0, target:componentOpenTarget});
const fullPlanOpenTarget = new FakeElement('a');
fullPlanOpenTarget.closest = selector => selector === '[data-home-plan-full]' ? fullPlanOpenTarget : null;
revealHomePlan.dispatch('click', {button:0, target:fullPlanOpenTarget});

assert.equal(homeFunnelEvents.some(event => event.action === 'reveal_start' && event.mode === 'seasonal'), true, 'The funnel DOM event must record automatic or explicit seasonal reveal starts.');
assert.equal(homeFunnelEvents.some(event => event.action === 'reveal_complete' && event.mode === 'seasonal' && event.destination === 'bangkok'), true, 'The funnel DOM event must record the committed seasonal destination.');
assert.equal(homeFunnelEvents.some(event => event.action === 'reveal_start' && event.mode === 'evergreen' && event.destination === 'bangkok'), true, 'The funnel DOM event must identify the evergreen automatic reveal start.');
assert.equal(homeFunnelEvents.some(event => event.action === 'reveal_complete' && event.mode === 'evergreen' && event.destination === 'bangkok'), true, 'The funnel DOM event must identify the evergreen automatic reveal completion.');
assert.equal(homeFunnelEvents.some(event => event.action === 'reveal_start' && event.mode === 'surprise'), true, 'The funnel DOM event must record Surprise Me starts.');
assert.equal(homeFunnelEvents.some(event => event.action === 'reveal_complete' && event.mode === 'surprise'), true, 'The funnel DOM event must record completed Surprise Me reveals.');
assert.equal(homeFunnelEvents.some(event => event.action === 'reveal_cancel' && event.mode === 'surprise'), true, 'The funnel DOM event must record interrupted Surprise Me reveals.');
assert.equal(homeFunnelEvents.some(event => event.action === 'component_open' && event.component === 'dining' && event.destination === 'bangkok'), true, 'Opening an editable component must emit its bounded planning domain.');
assert.equal(homeFunnelEvents.some(event => event.action === 'full_plan_open' && event.destination === 'bangkok'), true, 'Opening the full plan must emit a bounded handoff event.');
for (const event of homeFunnelEvents) {
  assert.deepEqual(Object.keys(event).every(key => ['action', 'surface', 'mode', 'destination', 'component'].includes(key)), true, 'Homepage funnel event details must remain on the non-PII allowlist.');
  assert.equal(Object.values(event).some(value => typeof value === 'number'), false, 'Homepage funnel events must not expose numeric prices or coordinates.');
}
assert.doesNotMatch(JSON.stringify(homeFunnelEvents), /prompt|latitude|longitude|price|url/i, 'Homepage funnel events must omit prompts, coordinates, prices, and URLs.');
assert.equal(windowStub.dataLayer.length, homeFunnelEvents.length, 'An existing dataLayer must mirror each bounded DOM funnel event exactly once.');
const eventCountBeforeNoDataLayer = homeFunnelEvents.length;
delete windowStub.dataLayer;
context.emitHomeFunnelEvent('component_open', {component:'equipment', destination:'bangkok', prompt:'private', latitude:13.7, price:950, url:'https://example.test'});
assert.equal(Object.hasOwn(windowStub, 'dataLayer'), false, 'Instrumentation must never create a dataLayer when the site has not provided one.');
assert.equal(homeFunnelEvents.length, eventCountBeforeNoDataLayer + 1, 'The consent-neutral custom DOM event must remain available without a dataLayer.');
assert.deepEqual(JSON.parse(JSON.stringify(homeFunnelEvents.at(-1))), {action:'component_open', surface:'homepage', destination:'bangkok', component:'equipment'}, 'The funnel allowlist must discard raw prompts, coordinates, numeric prices, and URLs.');
context.emitHomeFunnelEvent('component_open', {component:'flights', destination:'private-traveler-name'});
assert.deepEqual(JSON.parse(JSON.stringify(homeFunnelEvents.at(-1))), {action:'component_open', surface:'homepage', component:'flights'}, 'Unknown free-text destinations must not cross the non-PII funnel boundary.');

const revealFunctionSource = appSource.slice(appSource.indexOf('function initHomeDestinationReveal'), appSource.indexOf('function initDestinationPlan'));
assert.equal(revealFunctionSource.includes("setAttribute('aria-busy'"), false, 'The local Earth reveal state machine must not toggle aria-busy.');
assert.match(revealFunctionSource, /document\.addEventListener\('pointerdown',[\s\S]*?searchForm\?\.addEventListener\('input',[\s\S]*?window\.addEventListener\('scroll'/, 'Automatic reveal must yield to pointer, meaningful form input, and scroll intent.');
assert.match(revealFunctionSource, /document\.addEventListener\('keydown',[\s\S]*?document\.addEventListener\('focusin',[\s\S]*?document\.addEventListener\('visibilitychange'/, 'Running reveals must yield to global keyboard, focus, and document-visibility intent.');
assert.match(revealFunctionSource, /const globe = document\.querySelector\('\[data-home-globe\]'\)/, 'The automatic reveal controller must remain scoped to the homepage Earth.');
assert.doesNotMatch(internalPageSources, /data-home-globe|data-home-reveal/, 'Destination, map, and product pages must never opt into the automatic homepage reveal.');
assert.match(destinationPageSource, /data-destination-map-state="<\?php echo esc_attr\( \$map_state \); \?>"/, 'Destination pages must expose their known contextual Earth state.');
assert.doesNotMatch(revealFunctionSource, /Promise\.all\(\[hydration|globeReady|ssrRevealDeadlineMilliseconds|initializedAt/, 'Automatic seasonal reveal scheduling must not depend on network hydration, texture readiness, or the removed timing race.');
assert.match(revealFunctionSource, /if \(state\.autoEligible && campaignKind && document\.visibilityState !== 'hidden'\)[\s\S]*?state\.autoStarted = true;[\s\S]*?run\(campaignKind\)/, 'The homepage controller must schedule one guarded automatic reveal with its explicit campaign kind.');
assert.match(globeSource, /cancelMotion\(targetRoot = null\)[\s\S]*?controller\.root === targetRoot/, 'Globe cancellation must be public and scoped to the requested Earth instance.');
assert.match(globeSource, /liveRegion && announce/, 'Programmatic previews must be able to suppress globe announcements.');
assert.match(globeSource, /function shouldReduceMotion\(\)[\s\S]*?navigator\.connection\?\.saveData === true/, 'General globe motion must honor Save-Data as well as reduced motion.');
assert.match(globeSource, /const homeGlobe = Boolean\(root\.closest\('\.home-globe-stack'\)\)[\s\S]*?const markerHeight = mobile \? 44 : 34;[\s\S]*?const collisionMarkerHeight = homeGlobe \? 44 : markerHeight;/, 'Homepage marker collision geometry must match the real 44px target height.');
assert.match(globeSource, /addEventListener\('pointerdown'[\s\S]*?startedOnPin: Boolean\(event\.target\.closest\('\.price-pin'\)\)[\s\S]*?distance < 8[\s\S]*?mode = 'drag'/, 'A pin-origin gesture must remain a tap below eight pixels and become a globe drag at the threshold.');
assert.match(globeSource, /if \(pointer\.moved && pointer\.startedOnPin\) state\.suppressPinActivationUntil = performance\.now\(\) \+ 500;[\s\S]*?!pointer\.startedOnPin/, 'A completed pin drag must suppress its synthetic click while a true pin tap remains available to the destination control.');
assert.match(globeSource, /function activateStaticFallback[\s\S]*?classList\.remove\('is-webgl-ready'[\s\S]*?classList\.add\('globe-3d-unavailable'[\s\S]*?WebGL render error[\s\S]*?webglcontextrestored/, 'A post-load WebGL error or restored invalid context must deterministically reveal the static Earth fallback.');
assert.match(globeSource, /candidates\.sort\(\(a, b\) => Number\(b\.focused\) - Number\(a\.focused\) \|\| Number\(b\.active\) - Number\(a\.active\)[\s\S]*?if \(!placement && \(candidate\.active \|\| candidate\.focused\)\)/, 'Focused and active Earth controls must be placed first and must not disappear under collision pressure.');
assert.match(globeSource, /if \(absoluteY >= absoluteX\)[\s\S]*?mode = 'scroll'[\s\S]*?if \(absoluteX < absoluteY \* 1\.25\) return;[\s\S]*?setPointerCapture/, 'Touch drag must require clear horizontal intent before the Earth captures the pointer.');
assert.match(globeSource, /zoom\(direction, options = \{\}\)[\s\S]*?const targetRoot[\s\S]*?controller\.root === targetRoot/, 'Public globe zoom must support one originating Earth instance.');
assert.match(appSource, /traVelGlobe3D\.zoom\(button\.dataset\.mapZoom, \{ root: globe \}\)/, 'Each zoom control must pass its own Earth root to the globe controller.');
const homePointRuntimeSource = appSource.slice(appSource.indexOf('function renderHomePointSelection('), appSource.indexOf('\nfunction initGlobePointSelection('));
for (const selector of ['data-home-plan-flight', 'data-home-plan-stay', 'data-home-plan-transfer', 'data-home-plan-activity', 'data-home-plan-dining', 'data-home-plan-insurance', 'data-home-plan-connectivity', 'data-home-plan-equipment']) {
  assert(homePointRuntimeSource.includes(selector), `Homepage coordinate planning is missing ${selector}.`);
}
assert.match(homePointRuntimeSource, /activePlanningSelectionQuery\(''\)[\s\S]*?destinationPlanUrl\('\/ai-planner\/'[\s\S]*?destinationPlanUrl\('\/travel-map\/'/, 'A homepage coordinate must preserve its identity in both AI and full-map planning links.');
const globeSelectionRuntimeSource = appSource.slice(appSource.indexOf('function initGlobePointSelection('), appSource.indexOf('\nfunction updateDestinationPlanStages('));
assert.match(globeSelectionRuntimeSource, /matches\('\[data-home-globe\]'\)[\s\S]*?renderHomePointSelection\(detail, globeRoot\);[\s\S]*?return;[\s\S]*?closest\('\.theme-map-shell'\)/, 'Homepage coordinate clicks must never enter the full-map renderer.');

class GlobeRuntimeElement {
  constructor({pin = false, hub = false} = {}) {
    this.dataset = {};
    this.classList = new FakeClassList();
    this.hidden = false;
    this.textContent = '';
    this.style = {setProperty() {}};
    this.attributes = new Map();
    this.listeners = new Map();
    this.pin = pin;
    this.hub = hub;
    this.captureCalls = 0;
    this.capturedPointer = null;
  }

  addEventListener(type, callback) {
    const listeners = this.listeners.get(type) || [];
    listeners.push(callback);
    this.listeners.set(type, listeners);
  }

  dispatch(type, input = {}) {
    const event = {
      type,
      defaultPrevented: false,
      immediatePropagationStopped: false,
      preventDefault() { this.defaultPrevented = true; },
      stopImmediatePropagation() { this.immediatePropagationStopped = true; },
      ...input
    };
    (this.listeners.get(type) || []).forEach(callback => callback(event));
    return event;
  }

  dispatchEvent(event) {
    (this.listeners.get(event.type) || []).forEach(callback => callback(event));
    return true;
  }

  closest(selector) {
    if (this.pin && (selector === '.price-pin' || selector === '.price-pin,[data-exploration-hub]')) return this;
    if (this.hub && (selector === '[data-exploration-hub]' || selector === '.price-pin,[data-exploration-hub]')) return this;
    return null;
  }

  matches(selector) {
    return selector === '[data-discovery-globe]';
  }

  setAttribute(name, value) {
    this.attributes.set(name, String(value));
  }

  getAttribute(name) {
    return this.attributes.get(name) ?? null;
  }

  getBoundingClientRect() {
    return {left:0,top:0,width:400,height:400};
  }

  setPointerCapture(pointerId) {
    this.captureCalls += 1;
    this.capturedPointer = pointerId;
  }

  hasPointerCapture(pointerId) {
    return this.capturedPointer === pointerId;
  }

  releasePointerCapture(pointerId) {
    if (this.capturedPointer === pointerId) this.capturedPointer = null;
  }
}

const runtimeGl = {
  VERTEX_SHADER:1, FRAGMENT_SHADER:2, COMPILE_STATUS:3, LINK_STATUS:4,
  ARRAY_BUFFER:5, ELEMENT_ARRAY_BUFFER:6, STATIC_DRAW:7, TEXTURE_2D:8,
  RGBA:9, RGB:10, UNSIGNED_BYTE:11, TEXTURE_MIN_FILTER:12,
  TEXTURE_MAG_FILTER:13, LINEAR:14, TEXTURE_WRAP_S:15, TEXTURE_WRAP_T:16,
  REPEAT:17, CLAMP_TO_EDGE:18, LINEAR_MIPMAP_LINEAR:19, DEPTH_TEST:20,
  CULL_FACE:21, BACK:22, COLOR_BUFFER_BIT:23, DEPTH_BUFFER_BIT:24,
  FLOAT:25, TEXTURE0:26, TRIANGLES:27, UNSIGNED_SHORT:28, NO_ERROR:0,
  errors: [],
  createShader: () => ({}), shaderSource() {}, compileShader() {}, getShaderParameter: () => true,
  getShaderInfoLog: () => '', deleteShader() {}, createProgram: () => ({}), attachShader() {},
  linkProgram() {}, getProgramParameter: () => true, getProgramInfoLog: () => '', deleteProgram() {},
  createBuffer: () => ({}), bindBuffer() {}, bufferData() {}, createTexture: () => ({}), bindTexture() {},
  texImage2D() {}, texParameteri() {}, getAttribLocation: () => 0, getUniformLocation: () => ({}),
  enable() {}, cullFace() {}, clearColor() {}, viewport() {}, clear() {}, useProgram() {},
  enableVertexAttribArray() {}, vertexAttribPointer() {}, uniformMatrix4fv() {}, activeTexture() {},
  uniform1i() {}, drawElements() {}, pixelStorei() {}, generateMipmap() {}, isContextLost: () => false,
  getError() { return this.errors.shift() ?? this.NO_ERROR; }
};
const runtimeCanvas = new GlobeRuntimeElement();
runtimeCanvas.width = 0;
runtimeCanvas.height = 0;
runtimeCanvas.getContext = () => runtimeGl;
const runtimePin = new GlobeRuntimeElement({pin:true});
runtimePin.dataset.destination = 'dynamic-island';
runtimePin.dataset.latitude = '17.25';
runtimePin.dataset.longitude = '-61.8';
runtimePin.textContent = 'Dynamic Island';
runtimePin.classList.add('price-pin', 'is-active');
const runtimeHub = new GlobeRuntimeElement({hub:true});
runtimeHub.dataset.explorationHub = 'runtime-hub';
runtimeHub.dataset.city = 'Runtime City';
runtimeHub.dataset.country = 'Runtime Country';
runtimeHub.dataset.latitude = '8.4';
runtimeHub.dataset.longitude = '11.2';
runtimeHub.dataset.radiusKm = '180';
runtimeHub.dataset.iataSearchCode = 'RTC';
runtimeHub.dataset.liveSearchScopes = 'route,stay,activities,insurance,connectivity,equipment';
runtimeHub.classList.add('exploration-hub');
const runtimeLive = new GlobeRuntimeElement();
const runtimeGlobe = new GlobeRuntimeElement();
runtimeGlobe.dataset.texture = '/earth.jpg';
runtimeGlobe.dataset.originLatitude = '32.0005';
runtimeGlobe.dataset.originLongitude = '34.8708';
runtimeGlobe.querySelector = selector => {
  if (selector === '[data-globe-canvas]') return runtimeCanvas;
  if (selector === '[data-globe-live]') return runtimeLive;
  if (selector === '.price-pin.is-active') return runtimePin;
  if (selector === '.exploration-hub.is-active') return null;
  if (selector.startsWith('.exploration-hub[data-exploration-hub=')) return runtimeHub;
  return null;
};
runtimeGlobe.querySelectorAll = selector => {
  if (selector === '.price-pin[data-destination]') return [runtimePin];
  if (selector === '.exploration-hub[data-exploration-hub]') return [runtimeHub];
  return [];
};
let globeRuntimeClock = 100;
let globeRuntimeFrameId = 0;
const globeRuntimeFrames = new Map();
const globeRuntimeImages = [];
class RuntimeImage {
  constructor() {
    this.listeners = new Map();
    globeRuntimeImages.push(this);
  }
  addEventListener(type, callback) { this.listeners.set(type, callback); }
  set src(value) { this.currentSrc = value; }
  trigger(type) { this.listeners.get(type)?.(); }
}
const globeRuntimeWindow = {
  matchMedia: () => ({matches:false}),
  requestAnimationFrame(callback) {
    globeRuntimeFrameId += 1;
    globeRuntimeFrames.set(globeRuntimeFrameId, callback);
    return globeRuntimeFrameId;
  },
  cancelAnimationFrame(id) { globeRuntimeFrames.delete(id); },
  setTimeout: () => 1,
  clearTimeout() {}
};
const flushGlobeRuntimeFrames = () => {
  const frames = [...globeRuntimeFrames.values()];
  globeRuntimeFrames.clear();
  frames.forEach(callback => callback(globeRuntimeClock));
};
const globeRuntimeContext = vm.createContext({
  CSS: {escape:value => String(value)},
  CustomEvent: class CustomEvent { constructor(type, options = {}) { this.type = type; this.detail = options.detail; } },
  Image: RuntimeImage,
  IntersectionObserver: class IntersectionObserver { observe() {} },
  ResizeObserver: class ResizeObserver { observe() {} },
  console: {warn() {}},
  document: {
    readyState:'complete',
    visibilityState:'visible',
    querySelectorAll: selector => selector === '[data-globe-3d]' ? [runtimeGlobe] : [],
    addEventListener() {}
  },
  navigator: {},
  performance: {now:() => globeRuntimeClock},
  window: globeRuntimeWindow
});
vm.runInContext(globeSource, globeRuntimeContext);
const destinationFirstResolution = globeRuntimeWindow.traVelGlobe3D.resolveSelection(
  {latitude:0, longitude:0},
  [{id:'destination-first', latitude:0, longitude:0.2}],
  [{id:'hub-under-destination', latitude:0, longitude:0, radiusKm:250}],
  100
);
assert.equal(destinationFirstResolution.selectionKind, 'destination', 'A known destination inside its supported radius must always outrank an overlapping exploration hub.');
const hubResolution = globeRuntimeWindow.traVelGlobe3D.resolveSelection(
  {latitude:0, longitude:0},
  [{id:'far-destination', latitude:20, longitude:20}],
  [{id:'near-hub', latitude:0.3, longitude:0.2, radiusKm:120}],
  100
);
assert.equal(hubResolution.selectionKind, 'exploration_hub', 'A point inside an explicit hub radius must resolve to that nearest hub when no destination applies.');
const remoteResolution = globeRuntimeWindow.traVelGlobe3D.resolveSelection(
  {latitude:-72, longitude:-150},
  [{id:'far-destination', latitude:20, longitude:20}],
  [{id:'far-hub', latitude:0.3, longitude:0.2, radiusKm:120}],
  100
);
assert.deepEqual({selectionKind:remoteResolution.selectionKind, supported:remoteResolution.supported, planningAction:remoteResolution.planningAction}, {selectionKind:'map_point', supported:false, planningAction:'identify_coordinate'}, 'A remote point outside every explicit radius must remain a safe generic planning point.');
assert.equal(globeRuntimeImages.length, 1, 'The focused globe runtime must initialize one same-origin Earth texture.');
globeRuntimeImages[0].trigger('load');
flushGlobeRuntimeFrames();
assert.equal(runtimeGlobe.classList.contains('is-webgl-ready'), true, 'A successful texture upload and render must replace the static Earth fallback.');
assert.equal(runtimeGlobe.dataset.globeLod, 'far', 'At the default camera distance the marker pass must publish the far level of detail.');
assert.equal(runtimeHub.dataset.globeLabel, 'dot', 'A non-selected hub at far zoom must stay a dot without a city label.');

runtimeGlobe.dispatch('pointerdown', {isPrimary:true,button:0,pointerId:7,pointerType:'mouse',clientX:100,clientY:100,target:runtimePin});
runtimeGlobe.dispatch('pointermove', {pointerId:7,clientX:108,clientY:100,target:runtimePin});
assert.equal(runtimeGlobe.captureCalls, 1, 'An exact eight-pixel gesture beginning on a pin must commit to globe dragging.');
runtimeGlobe.dispatch('pointerup', {pointerId:7,clientX:108,clientY:100,target:runtimePin});
const draggedPinClick = runtimeGlobe.dispatch('click', {target:runtimePin});
assert.equal(draggedPinClick.defaultPrevented, true, 'The synthetic click following a pin drag must not activate the destination.');
assert.equal(draggedPinClick.immediatePropagationStopped, true, 'The suppressed post-drag click must not reach the destination handler.');

globeRuntimeClock = 700;
runtimeGlobe.dispatch('pointerdown', {isPrimary:true,button:0,pointerId:8,pointerType:'mouse',clientX:120,clientY:120,target:runtimePin});
runtimeGlobe.dispatch('pointerup', {pointerId:8,clientX:120,clientY:120,target:runtimePin});
const tappedPinClick = runtimeGlobe.dispatch('click', {target:runtimePin});
assert.equal(tappedPinClick.defaultPrevented, false, 'A true pin tap must remain available to the accessible destination button.');

const capturesBeforeTouchIntent = runtimeGlobe.captureCalls;
runtimeGlobe.dispatch('pointerdown', {isPrimary:true,button:0,pointerId:9,pointerType:'touch',clientX:100,clientY:100,target:runtimeGlobe});
runtimeGlobe.dispatch('pointermove', {pointerId:9,clientX:103,clientY:114,target:runtimeGlobe});
assert.equal(runtimeGlobe.captureCalls, capturesBeforeTouchIntent, 'A vertical touch gesture must remain available to page scrolling.');
runtimeGlobe.dispatch('pointerup', {pointerId:9,clientX:103,clientY:114,target:runtimeGlobe});
runtimeGlobe.dispatch('pointerdown', {isPrimary:true,button:0,pointerId:10,pointerType:'touch',clientX:100,clientY:100,target:runtimeGlobe});
runtimeGlobe.dispatch('pointermove', {pointerId:10,clientX:114,clientY:102,target:runtimeGlobe});
assert.equal(runtimeGlobe.captureCalls, capturesBeforeTouchIntent + 1, 'A clearly horizontal touch gesture must capture and rotate the Earth.');
runtimeGlobe.dispatch('pointerup', {pointerId:10,clientX:114,clientY:102,target:runtimeGlobe});

let runtimeHubSelection = null;
runtimeGlobe.addEventListener('travelglobe:select', event => { runtimeHubSelection = event.detail; });
globeRuntimeClock = 900;
const tappedHubClick = runtimeGlobe.dispatch('click', {target:runtimeHub, detail:1});
assert.equal(tappedHubClick.defaultPrevented, true, 'A WebGL-owned hub tap must suppress only the duplicate static-fallback handler.');
assert.deepEqual(JSON.parse(JSON.stringify({
  kind: runtimeHubSelection?.selectionKind,
  hubId: runtimeHubSelection?.hubId,
  city: runtimeHubSelection?.hubCity,
  country: runtimeHubSelection?.hubCountry,
  radiusKm: runtimeHubSelection?.supportedRadiusKm,
  scopes: runtimeHubSelection?.hubLiveSearchScopes
})), {
  kind:'exploration_hub', hubId:'runtime-hub', city:'Runtime City', country:'Runtime Country', radiusKm:180,
  scopes:['route','stay','activities','insurance','connectivity','equipment']
}, 'A hub tap must publish the complete geographic planning context into the same Earth-selection event.');

const diveDoubleClick = runtimeGlobe.dispatch('dblclick', {clientX:200, clientY:200, target:runtimeGlobe});
assert.equal(diveDoubleClick.defaultPrevented, true, 'A double click on the visible Earth must dive toward the struck coordinate and consume the event.');
const outsideDoubleClick = runtimeGlobe.dispatch('dblclick', {clientX:2, clientY:2, target:runtimeGlobe});
assert.equal(outsideDoubleClick.defaultPrevented, false, 'A double click outside the sphere must leave the event to the page.');

runtimeGl.errors.push(1282);
globeRuntimeWindow.traVelGlobe3D.requestRender();
flushGlobeRuntimeFrames();
assert.equal(runtimeGlobe.classList.contains('is-webgl-ready'), false, 'A post-load GPU error must immediately clear the WebGL-ready state.');
assert.equal(runtimeGlobe.classList.contains('globe-3d-unavailable'), true, 'A post-load GPU error must deterministically reveal the static Earth.');
runtimeCanvas.dispatch('webglcontextrestored');
assert.equal(runtimeGlobe.classList.contains('globe-3d-unavailable'), true, 'A restored context must not reuse invalid GPU resources after deterministic fallback.');
assert.match(globeSource, /root\.closest\('\[data-destination-map-state\]'\)[\s\S]*?focusDestination\(contextualDestination, \{ animate: false, pulse: false, announce: false \}\)/, 'Destination-page Earth must consume its contextual map state without roulette or announced motion.');
assert.match(frontPageSource, /data-globe-live role="status" aria-live="polite" aria-atomic="true"/, 'The homepage Earth needs one atomic polite result region.');
assert.match(frontPageSource, /data-home-globe data-default-destination="<\?php echo esc_attr\( \$home_default_destination \); \?>"/, 'The homepage Earth must expose its server-rendered destination to client hydration.');
assert.match(appSource, /const initialDiscoveryRequest = homeGlobe && activeDestination && !openEndedDestination[\s\S]*?discoveryRequestParams\(\{ destination: activeDestination \}\)/, 'Homepage hydration must request the exact server-rendered destination rather than a stale global default.');
assert.match(appSource, /hydrateDiscovery\(initialDiscoveryRequest, \{[^}]*allowGlobeFocus: !homeGlobe[^}]*allowConfirmedMotion: !homeGlobe[^}]*\}\)/, 'Initial homepage hydration must not refocus the Earth or celebrate a late hydration response after the reveal controller takes ownership.');
assert.match(appSource, /autoEligible: Boolean\(homeGlobe\) && !hasRequestedDestination && !openEndedDestination && !restoredFreePoint && !hasKnownTripIntent/, 'Any known trip-intent query, open-ended mode, or restored map point must suppress the automatic homepage reveal.');
assert.doesNotMatch(frontPageSource, /data-home-reveal-status[^>]*(?:role="status"|aria-live=)/, 'Visible reveal progress must not be a second live region.');
assert.doesNotMatch(frontPageSource, /data-home-plan-summary[^>]*(?:role="status"|aria-live=)/, 'The editable plan summary must not be a third live region.');
assert.match(cssSource, /\.home-reveal-feedback button \{[^}]*min-height: 44px;/, 'The visible Stop control must meet the 44px touch-target floor.');
assert.match(cssSource, /\.home-globe-stack \.globe-webgl \.price-pin \{[^}]*min-width: 44px;[^}]*min-height: 44px;/, 'Every homepage Earth pin must expose a real 44 by 44 CSS-pixel target.');
assert.match(appSource, /document\.dispatchEvent\(new CustomEvent\('tra-vel:funnel',[\s\S]*?if \(Array\.isArray\(window\.dataLayer\)\)/, 'Homepage funnel measurement must always use a local DOM event and mirror only into an existing dataLayer.');

// Theme 1.24.0 living globe: the idle spin and auto-fly tour stay inside the
// visibility, interaction, and reduced-motion guards, and the globe never
// binds a wheel or scroll listener (the scroll law).
assert.doesNotMatch(globeSource, /addEventListener\(\s*['"](?:wheel|mousewheel|scroll|touchmove)['"]/, 'The globe must never trap page scrolling with wheel, scroll, or touchmove listeners.');
assert.match(globeSource, /function idleSpinEligible\(now\) \{[\s\S]*?!state\.failed[\s\S]*?state\.visible[\s\S]*?document\.visibilityState === 'visible'[\s\S]*?!state\.pointer[\s\S]*?!state\.animation[\s\S]*?!state\.tour\.active[\s\S]*?now >= state\.idleSpin\.resumeAt[\s\S]*?!shouldReduceMotion\(\)/, 'The idle spin must hold every guard: alive, on-screen, visible tab, no pointer, no animation, no tour dwell, past the resume delay, and motion allowed.');
assert.match(globeSource, /if \(state\.animation \|\| idleSpinning\) requestRender\(\);/, 'Frames must self-schedule only while a camera animation or the guarded idle spin is running.');
assert.match(globeSource, /function noteDirectInteraction\(\) \{[\s\S]{0,260}state\.idleSpin\.resumeAt = performance\.now\(\) \+ IDLE_SPIN_RESUME_DELAY_MS;[\s\S]{0,160}stopTour\(true\);/, 'Direct interaction must pause the idle spin for the resume delay and cancel the tour permanently.');
assert.match(globeSource, /function tourHop\(\) \{[\s\S]*?shouldReduceMotion\(\)[\s\S]*?stopTour\(true\);[\s\S]*?document\.visibilityState !== 'visible'[\s\S]*?state\.pointer \|\| state\.animation \|\| now < state\.tour\.suspendedUntil[\s\S]*?focusDestination\(id, \{[\s\S]*?pulse: true,[\s\S]*?announce: false,[\s\S]*?rotations: 0,/, 'Tour hops must stop under reduced motion, wait while hidden or busy, and arrive through the pulsed focusDestination selection pipeline.');
assert.match(globeSource, /root\.matches\('\[data-discovery-globe\]'\)[\s\S]{0,240}startTour\(\);[\s\S]{0,120}TOUR_START_DELAY_MS\)/, 'Only discovery globes may arm the auto-fly tour, and only after the load-idle delay.');
assert.match(globeSource, /function startTour\(ids = null, options = \{\}\) \{\s*if \(state\.tour\.cancelled \|\| state\.failed \|\| root\.classList\.contains\('globe-3d-unavailable'\) \|\| shouldReduceMotion\(\)\) return false;/, 'A cancelled, failed, fallback, or reduced-motion globe must refuse to start a tour.');
assert.match(globeSource, /candidateIndex >= MARKER_COLLISION_BUDGET/, 'The collision pass must stay inside the bounded front-hemisphere marker budget.');
assert.match(globeSource, /timestamp - state\.lastMarkerSyncAt >= IDLE_MARKER_SYNC_INTERVAL_MS/, 'Idle-spin marker declutter must throttle to the bounded interval while the sphere renders at full rate.');

// Theme 1.25.0 dive store: the depth reducer, point-type routing, nearest
// computation, and truthful sample pricing run against the loaded client.
vm.runInContext(`
  destinationData = {
    bangkok: {id:'bangkok', city:'בנגקוק', country:'תאילנד', latitude:13.7563, longitude:100.5018, airportCode:'BKK', price:'החל מ-$950', hotelArea:'Siam', hotelPrice:'$65', currency:'USD'},
    athens: {id:'athens', city:'אתונה', country:'יוון', latitude:37.9838, longitude:23.7275, airportCode:'ATH', price:'החל מ-$189', hotelArea:'Plaka', hotelPrice:'$145', currency:'USD'},
    budapest: {id:'budapest', city:'בודפשט', country:'הונגריה', latitude:47.4979, longitude:19.0402, airportCode:'BUD', price:'החל מ-$229', hotelArea:'District V', hotelPrice:'$130', currency:'USD'},
    dubai: {id:'dubai', city:'דובאי', country:'איחוד האמירויות', latitude:25.2048, longitude:55.2708, airportCode:'DXB', price:'החל מ-$236', hotelArea:'Dubai Creek', hotelPrice:'$170', currency:'USD'}
  };
  explorationHubData = {
    larnaca: {id:'larnaca', city:'לרנקה', country:'קפריסין', latitude:34.9003, longitude:33.6232, radiusKm:90, iataSearchCode:'LCA', liveSearchScopes:['route','stay','activities','insurance','connectivity','equipment']}
  };
`, context);
const diveOne = runtimeJson(`diveStoreNextState({depth:0,kind:'',key:''}, {type:'dive', kind:'destination', key:'bangkok', latitude:13.7563, longitude:100.5018})`);
assert.equal(diveOne.depth, 1, 'A first dive on a destination must open the D1 chip row.');
const diveTwo = runtimeJson(`diveStoreNextState(${JSON.stringify(diveOne)}, {type:'dive', kind:'destination', key:'bangkok'})`);
assert.equal(diveTwo.depth, 2, 'A second dive on the same focused destination must expand to the D2 service board.');
const diveSwap = runtimeJson(`diveStoreNextState(${JSON.stringify(diveTwo)}, {type:'dive', kind:'destination', key:'athens'})`);
assert.deepEqual({depth: diveSwap.depth, key: diveSwap.key}, {depth: 1, key: 'athens'}, 'A dive on a different destination at any depth must swap the panel back to its D1 state.');
assert.equal(runtimeJson(`diveStoreNextState(${JSON.stringify(diveTwo)}, {type:'dive', kind:'destination', key:'bangkok'})`).depth, 2, 'The dive depth must cap at the D2 board.');
const divePointRepeat = runtimeJson(`diveStoreNextState({depth:1,kind:'map_point',key:'point:10.0:10.0'}, {type:'dive', kind:'map_point', key:'point:10.0:10.0'})`);
assert.equal(divePointRepeat.depth, 1, 'An arbitrary point must never open a D2 board, even on repeated dives.');
const diveBack = runtimeJson(`diveStoreNextState(${JSON.stringify(diveTwo)}, {type:'back'})`);
assert.deepEqual({depth: diveBack.depth, key: diveBack.key}, {depth: 1, key: 'bangkok'}, 'Back must step exactly one dive level up.');
assert.equal(runtimeJson(`diveStoreNextState(${JSON.stringify(diveBack)}, {type:'back'})`).depth, 0, 'Backing out of D1 must return the surface to the D0 orbit.');
assert.equal(runtimeJson(`diveStoreNextState(${JSON.stringify(diveTwo)}, {type:'select', kind:'map_point', key:'point:1.0:1.0'})`).depth, 0, 'A plain tap elsewhere must close the dive store back to orbit previews.');
assert.equal(runtimeJson(`diveStoreNextState({depth:0,kind:'',key:''}, {type:'select', kind:'destination', key:'athens'})`).depth, 1, 'Selecting a destination is a D1 entry.');
assert.equal(runtimeJson(`diveStorePointKind({selectionKind:'destination', supported:true, nearestDestination:'bangkok', latitude:13.7, longitude:100.5})`), 'destination', 'A supported destination selection must route to the destination dive surface.');
assert.equal(runtimeJson(`diveStorePointKind({selectionKind:'exploration_hub', hubId:'larnaca', latitude:34.9, longitude:33.6})`), 'exploration_hub', 'A hub selection must route to the hub dive surface.');
assert.equal(runtimeJson(`diveStorePointKind({selectionKind:'exploration_hub', hubId:'unknown-hub', latitude:34.9, longitude:33.6})`), 'map_point', 'An unknown hub identity must fall back to the safe point surface.');
assert.equal(runtimeJson(`diveStorePointKind({latitude:200, longitude:0})`), '', 'Invalid coordinates must not open any dive surface.');
const runtimeNearest = runtimeJson(`nearestCuratedDestinations({latitude:34.9003, longitude:33.6232}, destinationData, 3)`);
assert.deepEqual(runtimeNearest.map(entry => entry.id), ['athens', 'budapest', 'dubai'], 'The nearest-three computation must order curated destinations by great-circle distance.');
assert.ok(runtimeNearest.every(entry => Number.isInteger(entry.distanceKm) && entry.distanceKm > 0), 'Nearest-destination chips must carry whole-kilometre distances.');
assert.deepEqual(runtimeJson(`diveBreadcrumbTrail({depth:1, kind:'destination', key:'bangkok'})`), ['עולם', 'תאילנד', 'בנגקוק'], 'The breadcrumb must read world, country, city for a destination dive.');
assert.deepEqual(runtimeJson(`diveBreadcrumbTrail({depth:1, kind:'map_point', key:'point:1.0:1.0'})`), ['עולם', 'נקודה על הגלובוס'], 'An arbitrary point must stay an honest globe point in the breadcrumb.');
assert.equal(runtimeJson(`diveBundleCard(destinationData.bangkok, []).price`), null, 'Without planning-route insurance data the travel-kit bundle must not show a price.');
assert.equal(runtimeJson(`diveBundleCard(destinationData.bangkok, [{currency:'USD', costs:{insurance:72}}, {currency:'USD', costs:{insurance:85}}]).price.amount`), '$72', 'The bundle sample price must be the minimum existing route insurance component.');
assert.equal(runtimeJson(`divePriceParts('החל מ-$214').amount`), '$214', 'Sample prices must parse only from the החל מ- form.');
assert.equal(runtimeJson(`divePriceParts('$214')`), null, 'A bare amount without the החל מ- form must never render as a dive price.');
assert.equal(runtimeJson(`diveHubCards(explorationHubData.larnaca).every(card => card.price === null)`), true, 'Hub dive cards must never carry a price before live search.');
assert.equal(runtimeJson('diveStoreFootnoteText'), 'המחירים להמחשה; המחיר הסופי מאומת לפני התשלום.', 'The dive store must keep its canonical single price footnote.');
vm.runInContext('destinationData = { ...fallbackDestinations }; explorationHubData = {};', context);

console.log('Tra-Vel animated journey runtime checks passed.');
