const destinationAssetBase = window.traVelV2?.assetUrl || './assets/';
const fallbackDestinations = {
  bangkok: { id: 'bangkok', city: 'בנגקוק', country: 'תאילנד', price: 'החל מ-$950', total: 'מחיר לתכנון לאדם', note: 'המחיר, הזמינות והתנאים מאומתים לפני התשלום', image: `${destinationAssetBase}thailand.jpg`, tags: ['12 לילות', 'עד עצירה אחת', 'כבודה בתכנון'], airport: 'BKK · בנגקוק', hotel: 'Siam · מרכז ותחבורה', weather: 'עונה חמה ולחה', latitude: 13.7563, longitude: 100.5018, x: 72, y: 61 },
  athens: { id: 'athens', city: 'אתונה', country: 'יוון', price: 'החל מ-$189', total: 'מחיר לתכנון לאדם', note: 'המחיר, הזמינות והתנאים מאומתים לפני התשלום', image: `${destinationAssetBase}athens-acropolis.jpg`, tags: ['טיסה ישירה', '3 לילות', 'מרכז העיר'], airport: 'ATH · אתונה', hotel: 'Plaka · היסטוריה ואוכל', weather: 'מתאים לחופשה קצרה', latitude: 37.9838, longitude: 23.7275, x: 48, y: 43 },
  budapest: { id: 'budapest', city: 'בודפשט', country: 'הונגריה', price: 'החל מ-$229', total: 'מחיר לתכנון לאדם', note: 'המחיר, הזמינות והתנאים מאומתים לפני התשלום', image: `${destinationAssetBase}city-budapest.webp`, tags: ['טיסה ישירה', '4 לילות', 'מרכז פשט'], airport: 'BUD · בודפשט', hotel: 'District V · מרכז ונוף', weather: 'עיר, ספא וקולינריה', latitude: 47.4979, longitude: 19.0402, x: 43, y: 32 },
  prague: { id: 'prague', city: 'פראג', country: 'צ׳כיה', price: 'החל מ-$229', total: 'מחיר לתכנון לאדם', note: 'המחיר, הזמינות והתנאים מאומתים לפני התשלום', image: `${destinationAssetBase}city-prague.webp`, tags: ['טיסה ישירה', '4 לילות', 'מרכז היסטורי'], airport: 'PRG · פראג', hotel: 'Prague 1 · מרכז ותחבורה', weather: 'עיר, היסטוריה ואוכל', latitude: 50.0755, longitude: 14.4378, x: 39, y: 29 },
  vienna: { id: 'vienna', city: 'וינה', country: 'אוסטריה', price: 'החל מ-$239', total: 'מחיר לתכנון לאדם', note: 'המחיר, הזמינות והתנאים מאומתים לפני התשלום', image: `${destinationAssetBase}city-vienna.webp`, tags: ['טיסה ישירה', '4 לילות', 'תרבות ומשפחות'], airport: 'VIE · וינה', hotel: 'Innere Stadt · מרכז ותחבורה', weather: 'עיר, תרבות וקצב רגוע', latitude: 48.2082, longitude: 16.3738, x: 41, y: 30 },
  dubai: { id: 'dubai', city: 'דובאי', country: 'איחוד האמירויות', price: 'החל מ-$310', total: 'מחיר לתכנון לאדם', note: 'המחיר, הזמינות והתנאים מאומתים לפני התשלום', image: `${destinationAssetBase}city-dubai.webp`, tags: ['טיסה ישירה', 'סוף שבוע', 'עיר וחוף'], airport: 'DXB · דובאי', hotel: 'Creek · שוק ועיר עתיקה', weather: 'שמש ומלונות נופש', latitude: 25.2048, longitude: 55.2708, x: 59, y: 53 },
  tokyo: { id: 'tokyo', city: 'טוקיו', country: 'יפן', price: 'החל מ-$875', total: 'מחיר לתכנון לאדם', note: 'המחיר, הזמינות והתנאים מאומתים לפני התשלום', image: `${destinationAssetBase}city-tokyo.webp`, tags: ['עד עצירה אחת', '10 לילות', 'מסלול עירוני'], airport: 'HND · טוקיו', hotel: 'Shinjuku · רכבות וחיי לילה', weather: 'עונות מובחנות', latitude: 35.6762, longitude: 139.6503, x: 84, y: 39 },
  lisbon: { id: 'lisbon', city: 'ליסבון', country: 'פורטוגל', price: 'החל מ-$399', total: 'מחיר לתכנון לאדם', note: 'המחיר, הזמינות והתנאים מאומתים לפני התשלום', image: `${destinationAssetBase}city-lisbon.webp`, tags: ['7 לילות', 'עד עצירה אחת', 'עיר וחופים'], airport: 'LIS · ליסבון', hotel: 'Baixa · מרכז והליכה', weather: 'אקלים אטלנטי נוח', latitude: 38.7223, longitude: -9.1393, x: 29, y: 43 }
};

let destinationData = { ...fallbackDestinations };
let explorationHubData = {};
let discoveryRoutes = [];
let discoveryMapEntities = [];
let discoveryMapSegments = [];
let activeMapEntityId = '';
let activeMapEntitySelection = null;
let discoverySelectedPlan = null;
let activeRouteId = '';
let activeRouteSelectionLocked = false;
let activeLayer = 'deals';
let activeDestination = 'bangkok';
let homeRouteExamples = {};
let discoveryDataMode = 'demo';
let discoveryCacheState = 'degraded_fallback';
let discoveryFreshness = 'fallback';
let discoveryCacheFreshness = 'fallback';
let discoverySourceFreshness = 'not_applicable';
let discoveryBudgetCoverage = 'none';
let discoveryBudgetApplied = false;
let discoveryBudgetFilterActive = false;
let discoveryFieldProvenance = {};
let discoveryLiveLayers = { deals: false, hotels: false, airports: false, airportDetails: false, weather: false, routePrices: false, routeTotal: false };
let discoveryRequestController = null;
let discoveryRequestGeneration = 0;
let discoveryRequestPending = false;
let hotelSearchController = null;
let hotelSearchGeneration = 0;
let insuranceSearchController = null;
let insuranceSearchGeneration = 0;
let activePlanIntent = 'smart';
let discoveryDestinationLocked = false;
let discoveryDestinationMode = 'recommended';
let activePlanningSelection = null;
const discoveryLayers = new Set(['deals', 'hotels', 'airports', 'weather']);
const mapEntityKindByLayer = Object.freeze({ deals: 'deal', hotels: 'hotel_area', airports: 'airport', weather: 'weather' });
const mapEntityActionByKind = Object.freeze({ deal: 'search_packages', hotel_area: 'search_hotels', airport: 'compare_routes', weather: 'plan_for_weather' });
const mapEntityLayerLabels = Object.freeze({ deals: 'חופשות ועלות מלאה', hotels: 'אזורי לינה', airports: 'שדות ודרכי הגעה', weather: 'מזג אוויר ועונה' });
const mapEntityKindLabels = Object.freeze({ deal: 'אפשרות חופשה', hotel_area: 'אזור לינה', airport: 'שדה תעופה', weather: 'תנאי מזג אוויר' });
const mapEntityKindIcons = Object.freeze({ deal: 'badge-dollar-sign', hotel_area: 'bed-double', airport: 'plane', weather: 'cloud-sun' });
const mapEntityTruthStates = new Set(['planning', 'supplier_snapshot', 'last_observed']);
const mapEntityFreshnessStates = new Set(['current', 'refreshing', 'stale', 'fallback']);
const mapEntityDataModes = new Set(['demo', 'live']);
const mapEntityPriceBases = new Set(['per_person_total', 'per_night']);
const discoverySorts = new Set(['smart', 'price', 'time', 'comfort']);
const discoveryTrips = new Set(['all', 'short', 'long']);
const discoveryDefaults = {
  q: '',
  budget: 950,
  direct: false,
  sort: 'smart',
  trip: 'all',
  max_stops: 1,
  max_duration: 960,
  allow_overnight: false
};
let discoveryQuery = { ...discoveryDefaults };
const discoveryTripProductLabels = {
  package: 'טיסה ומלון',
  packages: 'חבילה',
  flights: 'טיסות',
  hotels: 'מלונות',
  insurance: 'ביטוח נסיעות'
};
let discoveryTripContext = { product: '', origin: '', departureDate: '', returnDate: '', adults: null, children: null, rooms: null };
const destinationPlanIntents = {
  smart: { label: 'החכמה', summary: 'מאזנים זמן, נוחות, גמישות ועלות מלאה לפני שבוחרים.' },
  value: { label: 'המשתלמת', summary: 'בודקים מה מקבלים בכל שקל ולא מסתפקים במחיר הכותרת.' },
  easy: { label: 'הקלה', summary: 'מצמצמים החלפות, נסיעות וסיכון כדי לפשט את כל הדרך.' },
  romantic: { label: 'הזוגית', summary: 'בונים קצב רגוע, אזור לינה נכון וחוויות שמתאימות לשניים.' },
  family: { label: 'המשפחתית', summary: 'מתעדפים חדר נכון, מרחקים קצרים, גמישות ופרטים לבדיקת ביטוח.' },
  adventure: { label: 'ההרפתקנית', summary: 'משלבים טבע, פעילות וציוד בלי לוותר על בטיחות ולוגיסטיקה.' },
  surprise: { label: 'המפתיעה', summary: 'מבקשים ממתכנן החופשה לארגן כיוון יצירתי לפי ההעדפות והתקציב.' }
};
const fullTripPlanningScope = 'flights,accommodation,transfers,activities,dining,insurance,connectivity,equipment';
const explorationHubLiveSearchScopes = Object.freeze(['route', 'stay', 'activities', 'insurance', 'connectivity', 'equipment']);
const mapEntityPlanningProfiles = Object.freeze({
  deal: { layer: 'deals', module: 'total', scope: fullTripPlanningScope, target: 'packages' },
  hotel_area: { layer: 'hotels', module: 'stay', scope: 'accommodation', target: 'hotels' },
  airport: { layer: 'airports', module: 'route', scope: 'flights', target: 'flights' },
  weather: { layer: 'weather', module: 'activities', scope: 'activities,equipment', target: 'ai' }
});
const homePlanningDomains = new Set(fullTripPlanningScope.split(','));
const homeFunnelActions = new Set(['reveal_start', 'reveal_complete', 'reveal_cancel', 'component_open', 'full_plan_open']);
const homeFunnelModes = new Set(['seasonal', 'evergreen', 'surprise']);
const knownTripIntentQueryKeys = new Set([
  'q', 'destination', 'origin', 'departure', 'departure_date', 'return', 'return_date', 'check_in', 'check_out',
  'date', 'dates', 'budget', 'currency', 'adults', 'children', 'infants', 'rooms', 'travelers', 'party', 'intent',
  'trip', 'mode', 'scope', 'direct', 'max_stops', 'max_duration', 'allow_overnight', 'flexible', 'flexibility',
  'area', 'hotel_area', 'transfers', 'kosher', 'accessibility', 'vibe', 'latitude', 'longitude',
  'selection_id', 'selection_kind', 'selection_destination'
]);
const fullTripCostScope = ['flight', 'baggage', 'stay', 'taxes', 'transfers', 'local_transport', 'activities', 'dining', 'insurance', 'connectivity', 'equipment', 'entry'];
const destinationPlanIntentConstraints = {
  smart: { sort: 'smart', trip: 'all', max_stops: 1, max_duration: 960, allow_overnight: false },
  value: { sort: 'price', trip: 'all', max_stops: 1, max_duration: 960, allow_overnight: false },
  easy: { sort: 'comfort', trip: 'all', max_stops: 1, max_duration: 960, allow_overnight: false },
  romantic: { sort: 'comfort', trip: 'short', max_stops: 1, max_duration: 960, allow_overnight: false },
  family: { sort: 'comfort', trip: 'all', max_stops: 1, max_duration: 960, allow_overnight: false },
  adventure: { sort: 'smart', trip: 'long', max_stops: 3, max_duration: 3000, allow_overnight: true },
  surprise: { sort: 'smart', trip: 'all', max_stops: 3, max_duration: 3000, allow_overnight: true }
};
const directoryDestinationAliases = { bangkok: 'thailand' };
const directoryDestinationIds = new Set(['budapest', 'prague', 'vienna', 'athens', 'dubai', 'thailand', 'tokyo', 'lisbon']);
const destinationGuidePaths = {
  budapest: '/destinations/budapest/',
  prague: '/destinations/prague/',
  athens: '/destinations/athens/',
  bangkok: '/destinations/thailand/'
};
const destinationCodeAliases = { bud: 'budapest', prg: 'prague', vie: 'vienna', ath: 'athens', dxb: 'dubai', bkk: 'bangkok', hnd: 'tokyo', lis: 'lisbon' };

function renderIcons() {
  if (window.lucide) window.lucide.createIcons({ attrs: { 'stroke-width': 1.8 } });
}

function prefersReducedMotion() {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true || navigator.connection?.saveData === true;
}

function emitHomeFunnelEvent(action, values = {}) {
  if (!homeFunnelActions.has(action)) return false;
  const detail = { action, surface: 'homepage' };
  const mode = homeFunnelModes.has(values.mode) ? values.mode : '';
  const requestedDestination = String(values.destination || '').toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 60);
  const destination = requestedDestination && Object.prototype.hasOwnProperty.call(destinationData, requestedDestination) ? requestedDestination : '';
  const component = homePlanningDomains.has(values.component) ? values.component : '';
  if (mode) detail.mode = mode;
  if (destination) detail.destination = destination;
  if (component) detail.component = component;
  try {
    document.dispatchEvent(new CustomEvent('tra-vel:funnel', { detail }));
  } catch {
    console.warn('Tra-Vel funnel event could not be dispatched.');
  }
  if (Array.isArray(window.dataLayer)) {
    try {
      window.dataLayer.push({ event: 'tra_vel_funnel', ...detail });
    } catch {
      console.warn('Tra-Vel funnel event could not be mirrored.');
    }
  }
  return true;
}

function hasKnownTripIntentQuery(params = new URLSearchParams(window.location.search)) {
  return Array.from(knownTripIntentQueryKeys).some(key => params.has(key));
}

function preferredScrollBehavior() {
	return prefersReducedMotion() ? 'auto' : 'smooth';
}

function setTextContentIfChanged(element, value) {
  if (element && element.textContent !== value) element.textContent = value;
}

function travelDateAfter(value, days = 1) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return '';
  const date = new Date(`${value}T00:00:00Z`);
  if (Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== value) return '';
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}

function syncStrictTravelEndDate(start, end, fallbackDays) {
  if (!start || !end) return;
  const minimum = travelDateAfter(start.value, 1);
  if (!minimum) return;
  end.min = minimum;
  if (end.value && end.value >= minimum) return;
  end.value = travelDateAfter(start.value, fallbackDays) || minimum;
}

function createPlanningSelectionId(prefix = 'map') {
  if (window.crypto?.randomUUID) return window.crypto.randomUUID();
  const random = Math.random().toString(36).slice(2, 14);
  return `${prefix}-${Date.now().toString(36)}-${random}`.slice(0, 80);
}

function setActivePlanningSelection({selectionId = '', latitude = null, longitude = null, destination = '', kind = 'map_point'} = {}) {
  const numericLatitude = Number(latitude);
  const numericLongitude = Number(longitude);
  const hasCoordinateValues = latitude !== null && latitude !== '' && longitude !== null && longitude !== '';
  const hasCoordinates = hasCoordinateValues && Number.isFinite(numericLatitude) && numericLatitude >= -90 && numericLatitude <= 90
    && Number.isFinite(numericLongitude) && numericLongitude >= -180 && numericLongitude <= 180;
  const normalizedDestination = String(destination || '').replace(/[^a-z0-9-]/g, '').slice(0, 60);
  const resolvedKind = kind === 'destination' && normalizedDestination
    ? 'destination'
    : (hasCoordinates ? 'map_point' : (normalizedDestination ? 'destination' : 'free_text'));
  activePlanningSelection = {
    selection_id: resolvedKind === 'free_text'
      ? null
      : (/^[A-Za-z0-9_-]{8,80}$/.test(selectionId) ? selectionId : createPlanningSelectionId(resolvedKind === 'destination' ? 'destination' : 'map')),
    kind: resolvedKind,
    latitude: hasCoordinates ? numericLatitude : null,
    longitude: hasCoordinates ? numericLongitude : null,
    destination: resolvedKind === 'free_text' ? '' : normalizedDestination
  };
  return activePlanningSelection;
}

function activePlanningSelectionQuery(destination = '') {
  if (!activePlanningSelection) return destination ? {destination} : {};
  return {
    selection_id: activePlanningSelection.selection_id,
    selection_kind: activePlanningSelection.kind,
    latitude: Number.isFinite(activePlanningSelection.latitude) ? activePlanningSelection.latitude.toFixed(4) : '',
    longitude: Number.isFinite(activePlanningSelection.longitude) ? activePlanningSelection.longitude.toFixed(4) : '',
    destination: activePlanningSelection.destination || destination
  };
}

function activePlanningSelectionHandoffQuery(destination = '') {
  const query = activePlanningSelectionQuery(destination);
  const selectionDestination = query.destination || '';
  delete query.destination;
  if (selectionDestination) query.selection_destination = selectionDestination;
  return query;
}

function planningSelectionHistoryState() {
  if (!activePlanningSelection) return null;
  return {
    selection_id: activePlanningSelection.selection_id,
    kind: activePlanningSelection.kind,
    latitude: activePlanningSelection.latitude,
    longitude: activePlanningSelection.longitude,
    destination: activePlanningSelection.destination
  };
}

function restorePlanningSelectionFromHistory(value) {
  if (!value || typeof value !== 'object') {
    activePlanningSelection = null;
    return false;
  }
  const kind = ['destination', 'map_point'].includes(value.kind) ? value.kind : 'free_text';
  const destination = String(value.destination || '').replace(/[^a-z0-9-]/g, '').slice(0, 60);
  const selectionId = String(value.selection_id || '');
  const latitude = value.latitude === null ? null : Number(value.latitude);
  const longitude = value.longitude === null ? null : Number(value.longitude);
  const hasCoordinates = Number.isFinite(latitude) && latitude >= -90 && latitude <= 90
    && Number.isFinite(longitude) && longitude >= -180 && longitude <= 180;
  const valid = /^[A-Za-z0-9_-]{8,80}$/.test(selectionId)
    && ((kind === 'map_point' && hasCoordinates) || (kind === 'destination' && Boolean(destination)));
  if (!valid) {
    activePlanningSelection = null;
    return false;
  }
  setActivePlanningSelection({selectionId, latitude: hasCoordinates ? latitude : null, longitude: hasCoordinates ? longitude : null, destination, kind});
  return true;
}

function restorePlanningSelectionFromUrl(params, { preserveMissing = false } = {}) {
  const selectionKeys = ['selection_id', 'selection_kind', 'selection_destination', 'latitude', 'longitude'];
  const hasSelectionContext = selectionKeys.some(key => params.has(key));
  if (!hasSelectionContext) {
    if (!preserveMissing) activePlanningSelection = null;
    return false;
  }

  const kind = String(params.get('selection_kind') || '');
  const selectionId = String(params.get('selection_id') || '');
  const requestedDestination = String(params.get('selection_destination') || params.get('destination') || '').toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 60);
  const destination = requestedDestination === 'anywhere' ? '' : (destinationCodeAliases[requestedDestination] || requestedDestination);
  const latitude = Number(params.get('latitude'));
  const longitude = Number(params.get('longitude'));
  const hasCoordinates = params.has('latitude') && params.has('longitude')
    && Number.isFinite(latitude) && latitude >= -90 && latitude <= 90
    && Number.isFinite(longitude) && longitude >= -180 && longitude <= 180;
  const selectionIdIsValid = !selectionId || /^[A-Za-z0-9_-]{8,80}$/.test(selectionId);
  const valid = selectionIdIsValid
    && ((kind === 'map_point' && hasCoordinates) || (kind === 'destination' && Boolean(destination)));

  if (!valid) {
    activePlanningSelection = null;
    return false;
  }

  setActivePlanningSelection({
    selectionId,
    latitude: hasCoordinates ? latitude : null,
    longitude: hasCoordinates ? longitude : null,
    destination,
    kind
  });
  return true;
}

function activeFreePlanningPoint() {
  return Boolean(
    activePlanningSelection?.kind === 'map_point'
    && Number.isFinite(activePlanningSelection.latitude)
    && Number.isFinite(activePlanningSelection.longitude)
    && !activePlanningSelection.destination
  );
}

function discoverySnapshotIsCurrent() {
  return discoveryFreshness === 'current' && ['fresh', 'miss'].includes(discoveryCacheState);
}

function discoveryCommercialDataIsCurrent() {
  return discoverySnapshotIsCurrent() && Boolean(
    discoveryLiveLayers.deals
    || discoveryLiveLayers.hotels
    || discoveryLiveLayers.routePrices
    || discoveryLiveLayers.routeTotal
  );
}

function discoverySnapshotIsStale() {
  return ['refreshing', 'stale'].includes(discoveryFreshness);
}

function settledDiscoveryResponseState() {
  if (discoverySnapshotIsStale()) return 'stale';
  if (discoverySnapshotIsCurrent() && !['fallback', 'error'].includes(discoveryDataMode)) return 'current';
  return 'fallback';
}

function discoveryFreshnessLabel() {
  if (discoveryCacheFreshness === 'refreshing') return 'מוצג המחיר האחרון שנבדק בזמן שמחפשים עדכון';
  if (discoveryCacheFreshness === 'stale') return 'המחיר האחרון שנבדק מוצג, אך ייתכן שהשתנה';
  if (discoveryCacheFreshness === 'fallback') return 'מחיר עדכני אינו זמין כרגע';
  const sourceLabels = {
    stale: 'המידע האחרון שנבדק עשוי להשתנות; בדקו שוב לפני החלטה',
    future: 'מועד הבדיקה אינו ברור; בדקו שוב לפני החלטה',
    unknown: 'לא נמצא מועד בדיקה אמין; בדקו שוב לפני החלטה'
  };
  return sourceLabels[discoverySourceFreshness] || 'בדקו שוב לפני החלטה';
}

function budgetCoverageLabel() {
  if (!(Number(discoveryQuery.budget) > 0)) return '';
  if (discoveryBudgetCoverage === 'full' && discoveryBudgetApplied) return 'התקציב הוחל על כל היעדים עם מחיר עדכני';
  if (discoveryBudgetCoverage === 'partial' && discoveryBudgetFilterActive) return 'התקציב סינן רק יעדים שיש להם מחיר עדכני';
  return 'התקציב עדיין לא סינן יעדים כי אין מספיק מחירים עדכניים';
}

function updateBudgetCoverageStatus(mode = 'settled') {
  const status = document.querySelector('[data-budget-coverage]');
  if (!status) return;
  status.dataset.coverage = mode === 'loading' ? 'loading' : discoveryBudgetCoverage;
  status.textContent = mode === 'loading' ? 'בודקים אילו יעדים מתאימים לתקציב ומכינים אפשרויות להשוואה.' : budgetCoverageLabel();
}

function clampDiscoveryNumber(value, minimum, maximum, fallback) {
  const number = Number(value);
  return Number.isFinite(number) ? Math.min(maximum, Math.max(minimum, Math.round(number))) : fallback;
}

function normalizeDiscoveryTripDate(value) {
  const normalized = String(value || '');
  const match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!match) return '';
  const timestamp = Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
  const date = new Date(timestamp);
  const valid = date.getUTCFullYear() === Number(match[1])
    && date.getUTCMonth() === Number(match[2]) - 1
    && date.getUTCDate() === Number(match[3]);
  if (!valid) return '';
  const now = new Date();
  const localToday = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  return normalized >= localToday ? normalized : '';
}

function readDiscoveryTripContext(params) {
  const requestedProduct = String(params.get('product') || '').replace(/[^a-z]/g, '');
  const product = Object.prototype.hasOwnProperty.call(discoveryTripProductLabels, requestedProduct) ? requestedProduct : '';
  const originValue = String(params.get('origin') || '').toUpperCase();
  const origin = /^[A-Z]{3}$/.test(originValue) ? originValue : '';
  const departureDate = normalizeDiscoveryTripDate(params.get('departure_date') || params.get('checkin') || params.get('start_date'));
  let returnDate = normalizeDiscoveryTripDate(params.get('return_date') || params.get('checkout') || params.get('end_date'));
  const sameDayAllowed = product === 'insurance';
  if (departureDate && returnDate && (returnDate < departureDate || (!sameDayAllowed && returnDate === departureDate))) returnDate = '';
  const adults = params.has('adults') ? clampDiscoveryNumber(params.get('adults'), 1, 6, 2) : null;
  const children = params.has('children') ? clampDiscoveryNumber(params.get('children'), 0, 4, 0) : null;
  const rooms = params.has('rooms') ? clampDiscoveryNumber(params.get('rooms'), 1, 3, 1) : null;
  discoveryTripContext = { product, origin, departureDate, returnDate, adults, children, rooms };
  return discoveryTripContext;
}

function discoveryTripContextQuery(target = 'map') {
  const context = discoveryTripContext;
  const query = {};
  if (target === 'map' && context.product) query.product = context.product;
  if (context.origin && ['map', 'flights', 'package', 'packages', 'ai'].includes(target)) query.origin = context.origin;
  if (context.departureDate) {
    const startKey = target === 'hotels' ? 'checkin' : (target === 'insurance' ? 'start_date' : 'departure_date');
    query[startKey] = context.departureDate;
  }
  if (context.returnDate) {
    const endKey = target === 'hotels' ? 'checkout' : (target === 'insurance' ? 'end_date' : 'return_date');
    query[endKey] = context.returnDate;
  }
  if (context.adults !== null) query.adults = context.adults;
  if (context.children !== null) query.children = context.children;
  if (context.rooms !== null && ['map', 'hotels', 'package', 'packages', 'ai'].includes(target)) query.rooms = context.rooms;
  if (target === 'ai' && context.product) query.product = context.product;
  return query;
}

function validDiscoveryBudgetQuery(params = new URLSearchParams(window.location.search)) {
  if (!params.has('budget')) return {};
  const rawBudget = String(params.get('budget') || '').trim();
  if (!/^\d+$/.test(rawBudget)) return {};
  const budget = Number(rawBudget);
  return Number.isInteger(budget) && budget >= 200 && budget <= 1600 ? { budget } : {};
}

function homePlanningLinkContext(target = 'map') {
  return { ...discoveryTripContextQuery(target), ...validDiscoveryBudgetQuery() };
}

function syncDiscoveryTripContext() {
  const container = document.querySelector('[data-map-trip-context]');
  if (!container) return;
  const context = discoveryTripContext;
  const hasContext = Boolean(context.product || context.origin || context.departureDate || context.returnDate || context.adults !== null || context.children !== null || context.rooms !== null);
  container.hidden = !hasContext;
  if (!hasContext) return;
  const parts = [];
  if (context.product) parts.push(discoveryTripProductLabels[context.product]);
  if (context.departureDate && context.returnDate) parts.push(`${context.departureDate} עד ${context.returnDate}`);
  if (context.adults !== null) parts.push(`${context.adults + (context.children || 0)} נוסעים`);
  if (context.rooms !== null && ['package', 'packages', 'hotels'].includes(context.product)) parts.push(`${context.rooms} חדרים`);
  if (context.origin) parts.push(`יציאה מ-${context.origin}`);
  const summary = container.querySelector('[data-map-trip-context-summary]');
  if (summary) summary.textContent = parts.join(' · ');
  const edit = container.querySelector('[data-map-trip-context-edit]');
  if (edit) {
    const editUrl = new URL('/', window.location.origin);
    Object.entries(discoveryTripContextQuery('map')).forEach(([key, value]) => editUrl.searchParams.set(key, String(value)));
    if (activeDestination) editUrl.searchParams.set('destination', activeDestination);
    else editUrl.searchParams.set('destination_mode', 'anywhere');
    editUrl.hash = 'search';
    edit.href = editUrl.toString();
  }
  const signature = JSON.stringify(context);
  if (container.dataset.signature !== signature) {
    container.dataset.signature = signature;
    container.classList.remove('is-new');
    if (!prefersReducedMotion()) {
      void container.offsetWidth;
      container.classList.add('is-new');
      window.clearTimeout(container.traVelMotionTimer);
      container.traVelMotionTimer = window.setTimeout(() => container.classList.remove('is-new'), 760);
    }
  }
}

function isMapWorkspacePage() {
  return Boolean(document.querySelector('.theme-map-shell'));
}

function readDiscoveryStateFromUrl({ preservePlanningSelection = false } = {}) {
  const params = new URLSearchParams(window.location.search);
  const layer = params.get('layer');
  const intent = params.get('intent');
  const sort = params.get('sort');
  const trip = params.get('trip');
  restorePlanningSelectionFromUrl(params, { preserveMissing: preservePlanningSelection });
  readDiscoveryTripContext(params);
  activeLayer = layer && discoveryLayers.has(layer) ? layer : 'deals';
  activePlanIntent = intent && destinationPlanIntents[intent] ? intent : 'smart';
  const intentConstraints = destinationPlanIntentConstraints[activePlanIntent] || destinationPlanIntentConstraints.smart;
  discoveryQuery = {
    q: String(params.get('q') || '').slice(0, 160),
    budget: params.has('budget') ? clampDiscoveryNumber(params.get('budget'), 200, 1600, discoveryDefaults.budget) : discoveryDefaults.budget,
    direct: ['1', 'true'].includes(params.get('direct')),
    sort: sort && discoverySorts.has(sort) ? sort : intentConstraints.sort,
    trip: trip && discoveryTrips.has(trip) ? trip : intentConstraints.trip,
    max_stops: params.has('max_stops') ? clampDiscoveryNumber(params.get('max_stops'), 0, 3, intentConstraints.max_stops) : intentConstraints.max_stops,
    max_duration: params.has('max_duration') ? clampDiscoveryNumber(params.get('max_duration'), 60, 3000, intentConstraints.max_duration) : intentConstraints.max_duration,
    allow_overnight: params.has('allow_overnight') ? ['1', 'true'].includes(params.get('allow_overnight')) : intentConstraints.allow_overnight
  };
  const requestedDestination = String(params.get('destination') || '').toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 60);
  const destination = requestedDestination === 'anywhere' ? '' : (destinationCodeAliases[requestedDestination] || requestedDestination);
  const openEndedRequested = params.get('destination_mode') === 'anywhere' || requestedDestination === 'anywhere';
  discoveryDestinationMode = openEndedRequested && !destination ? 'anywhere' : 'recommended';
  discoveryDestinationLocked = Boolean(destination);
  if (destination) activeDestination = destination;
  else if (discoveryDestinationMode === 'anywhere') activeDestination = '';
  if (activeFreePlanningPoint()) {
    discoveryDestinationMode = 'recommended';
    discoveryDestinationLocked = false;
    activeDestination = '';
  }
}

function normalizeFieldProvenance(provenance = {}) {
  const fields = ['deals', 'hotels', 'airports', 'routes', 'weather_current', 'weather_season'];
  return Object.fromEntries(fields.map(field => {
    const entry = provenance?.[field] || {};
    const destinationIds = Array.isArray(entry.destination_ids)
      ? entry.destination_ids.filter(id => typeof id === 'string' && /^[a-z0-9-]{1,60}$/.test(id))
      : [];
    const byDestination = Object.fromEntries(destinationIds.map(destinationId => {
      const destination = entry?.by_destination?.[destinationId] || {};
      return [destinationId, {
        source: typeof destination.source === 'string' ? destination.source.slice(0, 80) : '',
        observed_at: typeof destination.observed_at === 'string' ? destination.observed_at.slice(0, 40) : '',
        retrieved_at: typeof destination.retrieved_at === 'string' ? destination.retrieved_at.slice(0, 40) : '',
        currency: typeof destination.currency === 'string' && /^[A-Z]{3}$/.test(destination.currency) ? destination.currency : '',
        cost_components: Array.isArray(destination.cost_components)
          ? destination.cost_components.filter(value => typeof value === 'string' && /^[a-z0-9_-]{1,40}$/.test(value)).slice(0, 24)
          : [],
        total_live: destination.total_live === true,
        price_scope: typeof destination.price_scope === 'string' ? destination.price_scope.replace(/[^a-z0-9_-]/g, '').slice(0, 60) : ''
      }];
    }));
    return [field, {
      live: entry.live === true,
      source: typeof entry.source === 'string' ? entry.source : '',
      observed_at: typeof entry.observed_at === 'string' ? entry.observed_at : '',
      retrieved_at: typeof entry.retrieved_at === 'string' ? entry.retrieved_at : '',
      destination_ids: destinationIds,
      by_destination: byDestination
    }];
  }));
}

function normalizePlanningProfile(profile = {}) {
  const moduleIds = ['mobility', 'dining', 'entry', 'connectivity', 'accessibility', 'equipment'];
  const states = new Set(['editorial', 'needs_details', 'unknown']);
  const modules = Object.fromEntries(moduleIds.map(id => {
    const module = profile?.modules?.[id] || {};
    return [id, {
      state: states.has(module.state) ? module.state : 'unknown',
      headline: typeof module.headline === 'string' ? module.headline.slice(0, 180) : '',
      detail: typeof module.detail === 'string' ? module.detail.slice(0, 420) : '',
      nextAction: typeof module.next_action === 'string' ? module.next_action.slice(0, 100) : ''
    }];
  }));
  return {
    reviewedOn: typeof profile.reviewed_on === 'string' ? profile.reviewed_on : '',
    sourceLabel: typeof profile.source_label === 'string' ? profile.source_label.slice(0, 160) : '',
    modules,
    costCategories: Array.isArray(profile?.cost_scope?.categories)
      ? profile.cost_scope.categories.filter(value => typeof value === 'string').slice(0, 20)
      : []
  };
}

function fieldProvenanceLive(provenance, field, destinationId = '') {
  const entry = provenance?.[field];
  if (entry?.live !== true) return false;
  return destinationId ? entry.destination_ids.includes(destinationId) : entry.destination_ids.length > 0;
}

function destinationFieldProvenance(provenance, field, destinationId) {
  if (!fieldProvenanceLive(provenance, field, destinationId)) return {};
  return provenance?.[field]?.by_destination?.[destinationId] || {};
}

function resolveDiscoveryLiveLayers(provenance = {}, destinationId = '') {
  const fields = normalizeFieldProvenance(provenance);
  const routeProvenance = destinationId ? destinationFieldProvenance(fields, 'routes', destinationId) : {};
  return {
    deals: fieldProvenanceLive(fields, 'deals', destinationId),
    hotels: fieldProvenanceLive(fields, 'hotels', destinationId),
    airports: fieldProvenanceLive(fields, 'routes', destinationId),
    airportDetails: fieldProvenanceLive(fields, 'airports', destinationId),
    weather: fieldProvenanceLive(fields, 'weather_current', destinationId),
    routePrices: Array.isArray(routeProvenance.cost_components) && routeProvenance.cost_components.length > 0,
    routeTotal: routeProvenance.total_live === true
  };
}

function destinationDirectoryUrl(destinationId, params = {}) {
  const directoryId = directoryDestinationAliases[destinationId] || destinationId;
  if (destinationGuidePaths[destinationId]) return destinationPlanUrl(destinationGuidePaths[destinationId], params);
  return directoryDestinationIds.has(directoryId)
    ? destinationPlanUrl(`/destinations/#destination-${directoryId}`, params)
    : destinationPlanUrl('/destinations/', params);
}

function discoveryRequestParams(overrides = {}) {
  const request = {
    ...discoveryQuery,
    layer: activeLayer,
    ...overrides
  };
  if (!Object.prototype.hasOwnProperty.call(overrides, 'destination') && discoveryDestinationLocked && activeDestination) {
    request.destination = activeDestination;
  }
  return request;
}

function syncDiscoveryControls() {
  const budget = document.querySelector('[data-budget]');
  if (budget) {
    budget.value = String(Math.min(Number(budget.max || 5000), Math.max(Number(budget.min || 0), discoveryQuery.budget)));
    const value = document.querySelector('[data-budget-value]');
    if (value) value.textContent = `$${Number(discoveryQuery.budget).toLocaleString('en-US')}`;
  }
  document.querySelectorAll('[data-filter-kind="sort"] [data-filter-value]').forEach(button => {
    const selected = button.dataset.filterValue === discoveryQuery.sort;
    button.classList.toggle('is-active', selected);
    button.setAttribute('aria-pressed', String(selected));
  });
  document.querySelectorAll('[data-filter-kind="trip"] [data-filter-value]').forEach(button => {
    const selected = button.dataset.filterValue === discoveryQuery.trip;
    button.classList.toggle('is-active', selected);
    button.setAttribute('aria-pressed', String(selected));
  });
  const direct = document.querySelector('[data-direct-filter]');
  direct?.classList.toggle('is-active', discoveryQuery.direct);
  direct?.setAttribute('aria-pressed', String(discoveryQuery.direct));
  const maxStops = document.querySelector('[data-max-stops]');
  const maxDuration = document.querySelector('[data-max-duration]');
  const overnight = document.querySelector('[data-allow-overnight]');
  if (maxStops) maxStops.checked = discoveryQuery.max_stops <= 1;
  if (maxDuration) maxDuration.checked = discoveryQuery.max_duration <= 960;
  if (overnight) overnight.checked = discoveryQuery.allow_overnight;
  document.querySelectorAll('[data-map-layer]').forEach(button => {
    const selected = button.dataset.mapLayer === activeLayer;
    button.classList.toggle('is-active', selected);
    button.setAttribute('aria-pressed', String(selected));
  });
  document.querySelectorAll('[data-plan-intent]').forEach(button => {
    const selected = button.dataset.planIntent === activePlanIntent;
    button.classList.toggle('is-active', selected);
    button.setAttribute('aria-pressed', String(selected));
  });
  const mapQuery = document.querySelector('.map-search-bar [name="q"]');
  if (mapQuery) mapQuery.value = discoveryQuery.q;
  syncDiscoveryTripContext();
}

function syncDiscoveryUrl(mode = 'push') {
  if (!isMapWorkspacePage()) return;
  const url = new URL(window.location.href);
  const keys = ['destination', 'destination_mode', 'selection_id', 'selection_kind', 'selection_destination', 'latitude', 'longitude', 'layer', 'intent', 'q', 'budget', 'direct', 'sort', 'trip', 'max_stops', 'max_duration', 'allow_overnight', 'product', 'origin', 'departure_date', 'return_date', 'checkin', 'checkout', 'start_date', 'end_date', 'adults', 'children', 'rooms'];
  keys.forEach(key => url.searchParams.delete(key));
  if (discoveryDestinationLocked && activeDestination) url.searchParams.set('destination', activeDestination);
  else if (discoveryDestinationMode === 'anywhere') url.searchParams.set('destination_mode', 'anywhere');
  if (activeLayer !== 'deals') url.searchParams.set('layer', activeLayer);
  if (activePlanIntent !== 'smart') url.searchParams.set('intent', activePlanIntent);
  if (discoveryQuery.q) url.searchParams.set('q', discoveryQuery.q);
  if (discoveryQuery.budget !== discoveryDefaults.budget) url.searchParams.set('budget', String(discoveryQuery.budget));
  if (discoveryQuery.direct) url.searchParams.set('direct', '1');
  if (discoveryQuery.sort !== discoveryDefaults.sort) url.searchParams.set('sort', discoveryQuery.sort);
  if (discoveryQuery.trip !== discoveryDefaults.trip) url.searchParams.set('trip', discoveryQuery.trip);
  if (discoveryQuery.max_stops !== discoveryDefaults.max_stops) url.searchParams.set('max_stops', String(discoveryQuery.max_stops));
  if (discoveryQuery.max_duration !== discoveryDefaults.max_duration) url.searchParams.set('max_duration', String(discoveryQuery.max_duration));
  if (discoveryQuery.allow_overnight) url.searchParams.set('allow_overnight', '1');
  Object.entries(discoveryTripContextQuery('map')).forEach(([key, value]) => url.searchParams.set(key, String(value)));
  const selectionMatchesLockedDestination = discoveryDestinationLocked
    && Boolean(activeDestination)
    && activePlanningSelection?.destination === activeDestination;
  if (activeFreePlanningPoint() || (activePlanningSelection && ['destination', 'map_point'].includes(activePlanningSelection.kind) && selectionMatchesLockedDestination)) {
    const selectionQuery = activePlanningSelectionQuery('');
    const selectionDestination = selectionQuery.destination;
    delete selectionQuery.destination;
    Object.entries(selectionQuery).forEach(([key, value]) => {
      if (value !== '' && value !== undefined && value !== null) url.searchParams.set(key, String(value));
    });
    if (selectionDestination) url.searchParams.set('selection_destination', selectionDestination);
  }
  const method = mode === 'replace' ? 'replaceState' : 'pushState';
  window.history[method]({ traVelMap: true, focus: activeDestination || '', planningSelection: planningSelectionHistoryState() }, '', `${url.pathname}${url.search}${url.hash}`);
}

function replaceChildrenWithSpans(element, values) {
  if (!element) return;
  element.replaceChildren(...values.map(value => {
    const span = document.createElement('span');
    span.textContent = value;
    return span;
  }));
}

function normalizeDestination(item) {
  return {
    id: item.id,
    city: item.city,
    country: item.country,
    url: item.url,
    price: item.deal.headline_formatted,
    total: item.deal.total_formatted,
    totalAmount: Number(item.deal.total_per_person) || 0,
    currency: item.deal.currency || 'USD',
    note: item.deal.insight,
    nights: item.deal.nights,
    image: item.image,
    tags: item.tags,
    airport: `${item.airport.code} · ${item.airport.flight_duration_label}`,
    airportCode: item.airport.code,
    airportDirect: item.airport.direct === true,
    flightDuration: item.airport.flight_duration_label,
    transferMinutes: item.airport.transfer_minutes,
    hotel: `${item.hotel.area} · ${item.hotel.rating}★`,
    hotelArea: item.hotel.area,
    hotelName: item.hotel.name,
    hotelPrice: item.hotel.nightly_formatted,
    weather: `${item.weather.temperature_c}°C`,
    weatherCondition: item.weather.condition,
    seasonFit: item.weather.season_fit,
    planning: normalizePlanningProfile(item.planning),
    liveLayers: resolveDiscoveryLiveLayers(discoveryFieldProvenance, item.id),
    latitude: item.geo.latitude,
    longitude: item.geo.longitude,
    x: item.position.x,
    y: item.position.y
  };
}

function boundedMapEntityString(value, maximum = 240) {
  if (typeof value !== 'string') return '';
  return value.replace(/[\u0000-\u001f\u007f]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, maximum);
}

function normalizeExplorationHub(raw = {}) {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const allowedKeys = new Set(['id', 'city', 'country', 'geo', 'radius_km', 'iata_search_code', 'live_search_scopes']);
  if (Object.keys(raw).some(key => !allowedKeys.has(key))) return null;
  if (!raw.geo || typeof raw.geo !== 'object' || Array.isArray(raw.geo)
    || Object.keys(raw.geo).some(key => !['latitude', 'longitude'].includes(key))) return null;
  const id = boundedMapEntityString(raw.id, 60);
  const city = boundedMapEntityString(raw.city, 100);
  const country = boundedMapEntityString(raw.country, 100);
  const latitude = Number(raw.geo.latitude);
  const longitude = Number(raw.geo.longitude);
  const radiusKm = Number(raw.radius_km);
  const iataSearchCode = boundedMapEntityString(raw.iata_search_code || '', 3);
  const liveSearchScopes = Array.isArray(raw.live_search_scopes) ? raw.live_search_scopes.map(value => boundedMapEntityString(value, 24)) : [];
  const scopesAreComplete = liveSearchScopes.length === explorationHubLiveSearchScopes.length
    && new Set(liveSearchScopes).size === explorationHubLiveSearchScopes.length
    && explorationHubLiveSearchScopes.every(scope => liveSearchScopes.includes(scope));
  if (!/^[a-z0-9-]{2,60}$/.test(id) || !city || !country || Object.prototype.hasOwnProperty.call(destinationData, id)
    || !Number.isFinite(latitude) || latitude < -90 || latitude > 90
    || !Number.isFinite(longitude) || longitude < -180 || longitude > 180
    || !Number.isInteger(radiusKm) || radiusKm < 40 || radiusKm > 750
    || (iataSearchCode && !/^[A-Z]{3}$/.test(iataSearchCode)) || !scopesAreComplete) return null;
  return { id, city, country, latitude, longitude, radiusKm, iataSearchCode, liveSearchScopes };
}

function normalizeExplorationHubCollection(rawHubs) {
  if (!Array.isArray(rawHubs) || rawHubs.length < 30 || rawHubs.length > 80) return {};
  const seenIds = new Set();
  const seenPlaces = new Set();
  const seenCoordinates = new Set();
  const seenCodes = new Set();
  const normalized = {};
  for (const raw of rawHubs) {
    const hub = normalizeExplorationHub(raw);
    if (!hub) return {};
    const placeKey = `${hub.city.toLocaleLowerCase()}|${hub.country.toLocaleLowerCase()}`;
    const coordinateKey = `${hub.latitude}|${hub.longitude}`;
    if (seenIds.has(hub.id) || seenPlaces.has(placeKey) || seenCoordinates.has(coordinateKey)
      || (hub.iataSearchCode && seenCodes.has(hub.iataSearchCode))) return {};
    seenIds.add(hub.id);
    seenPlaces.add(placeKey);
    seenCoordinates.add(coordinateKey);
    if (hub.iataSearchCode) seenCodes.add(hub.iataSearchCode);
    normalized[hub.id] = hub;
  }
  return normalized;
}

function explorationHubsFromDom() {
  const rawHubs = Array.from(document.querySelectorAll('[data-discovery-globe] [data-exploration-hub]')).map(marker => ({
    id: marker.dataset.explorationHub,
    city: marker.dataset.city,
    country: marker.dataset.country,
    geo: { latitude: Number(marker.dataset.latitude), longitude: Number(marker.dataset.longitude) },
    radius_km: Number(marker.dataset.radiusKm),
    ...(marker.dataset.iataSearchCode ? { iata_search_code: marker.dataset.iataSearchCode } : {}),
    live_search_scopes: String(marker.dataset.liveSearchScopes || '').split(',').filter(Boolean)
  }));
  return normalizeExplorationHubCollection(rawHubs);
}

function setExplorationHubData(rawHubs) {
  const normalized = normalizeExplorationHubCollection(rawHubs);
  if (Object.keys(normalized).length < 30) return false;
  explorationHubData = normalized;
  window.traVelGlobe3D?.setExplorationHubs(explorationHubData);
  return true;
}

function explorationHubDistanceKm(first, second) {
  const degrees = Math.PI / 180;
  const latitudeDelta = (Number(second.latitude) - Number(first.latitude)) * degrees;
  const longitudeDelta = (Number(second.longitude) - Number(first.longitude)) * degrees;
  const firstLatitude = Number(first.latitude) * degrees;
  const secondLatitude = Number(second.latitude) * degrees;
  const haversine = Math.sin(latitudeDelta / 2) ** 2
    + Math.cos(firstLatitude) * Math.cos(secondLatitude) * Math.sin(longitudeDelta / 2) ** 2;
  return 6371 * 2 * Math.atan2(Math.sqrt(haversine), Math.sqrt(Math.max(0, 1 - haversine)));
}

function explorationHubForPoint(latitude, longitude) {
  const point = { latitude: Number(latitude), longitude: Number(longitude) };
  if (!Number.isFinite(point.latitude) || point.latitude < -90 || point.latitude > 90
    || !Number.isFinite(point.longitude) || point.longitude < -180 || point.longitude > 180) return null;
  return Object.values(explorationHubData)
    .map(hub => ({ ...hub, distanceKm: explorationHubDistanceKm(point, hub) }))
    .filter(hub => hub.distanceKm <= hub.radiusKm)
    .sort((first, second) => first.distanceKm - second.distanceKm || first.radiusKm - second.radiusKm)[0] || null;
}

function normalizeMapEntityProvenance(raw = {}) {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const source = boundedMapEntityString(raw.source, 160);
  if (!source) return null;
  const normalizeDate = (value, maximum) => value === null || value === undefined || value === ''
    ? null
    : boundedMapEntityString(value, maximum);
  return {
    source,
    observed_at: normalizeDate(raw.observed_at, 40),
    retrieved_at: normalizeDate(raw.retrieved_at, 40),
    reviewed_on: normalizeDate(raw.reviewed_on, 20)
  };
}

function safeMapEntityAction(raw = {}, kind = '') {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const type = boundedMapEntityString(raw.type, 40);
  const label = boundedMapEntityString(raw.label, 100);
  if (!type || type !== mapEntityActionByKind[kind] || !label || typeof raw.requires_live_search !== 'boolean') return null;
  try {
    const href = new URL(boundedMapEntityString(raw.href, 2000), window.location.origin);
    if (!['https:', 'http:'].includes(href.protocol) || href.origin !== window.location.origin || href.username || href.password) return null;
    return { type, label, href: href.href, requires_live_search: raw.requires_live_search };
  } catch (error) {
    return null;
  }
}

function normalizeMapEntityPrice(raw, kind, truthState, dataMode) {
  if (raw === null || raw === undefined) return { valid: true, value: null };
  if (['airport', 'weather'].includes(kind) || typeof raw !== 'object' || Array.isArray(raw)) return { valid: false, value: null };
  const amount = Number(raw.amount);
  const currency = boundedMapEntityString(raw.currency, 3).toUpperCase();
  const formatted = boundedMapEntityString(raw.formatted, 40);
  const basis = boundedMapEntityString(raw.basis, 40);
  const state = boundedMapEntityString(raw.state, 30);
  const expectedBasis = kind === 'deal' ? 'per_person_total' : 'per_night';
  const valid = Number.isFinite(amount) && amount >= 0 && amount <= 1000000000
    && /^[A-Z]{3}$/.test(currency) && formatted
    && mapEntityPriceBases.has(basis) && basis === expectedBasis
    && mapEntityTruthStates.has(state) && state === truthState
    && typeof raw.bookable === 'boolean' && raw.bookable === false
    && (dataMode !== 'demo' || state === 'planning');
  return valid
    ? { valid: true, value: { amount, currency, formatted, basis, state, bookable: false } }
    : { valid: false, value: null };
}

function normalizeMapEntity(raw, layer = activeLayer, destinations = destinationData) {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const kind = boundedMapEntityString(raw.kind, 30);
  const destinationId = boundedMapEntityString(raw.destination_id, 60).toLowerCase();
  const id = boundedMapEntityString(raw.id, 120);
  const latitude = Number(raw.lat);
  const longitude = Number(raw.lng);
  const dataMode = boundedMapEntityString(raw.data_mode, 20);
  const truthState = boundedMapEntityString(raw.truth_state, 30);
  const freshness = boundedMapEntityString(raw.freshness, 30);
  const label = boundedMapEntityString(raw.label, 140);
  const summary = boundedMapEntityString(raw.summary, 360);
  const expectedKind = mapEntityKindByLayer[layer];
  const identityIsSafe = /^[a-z0-9][a-z0-9:_-]{1,119}$/.test(id)
    && /^[a-z0-9][a-z0-9-]{1,59}$/.test(destinationId)
    && Object.prototype.hasOwnProperty.call(destinations, destinationId);
  const truthIsSafe = mapEntityDataModes.has(dataMode) && mapEntityTruthStates.has(truthState)
    && mapEntityFreshnessStates.has(freshness)
    && ((dataMode === 'demo' && truthState === 'planning') || (dataMode === 'live' && truthState !== 'planning'));
  if (!expectedKind || kind !== expectedKind || !identityIsSafe || !label || !summary || !truthIsSafe
    || !Number.isFinite(latitude) || latitude < -90 || latitude > 90
    || !Number.isFinite(longitude) || longitude < -180 || longitude > 180) return null;
  const action = safeMapEntityAction(raw.action, kind);
  const provenance = normalizeMapEntityProvenance(raw.provenance);
  const price = normalizeMapEntityPrice(raw.price, kind, truthState, dataMode);
  if (!action || !provenance || !price.valid) return null;
  return {
    id,
    kind,
    destination_id: destinationId,
    lat: latitude,
    lng: longitude,
    label,
    summary,
    data_mode: dataMode,
    truth_state: truthState,
    freshness,
    action,
    provenance,
    price: price.value
  };
}

function normalizeMapEntityCollection(rawEntities, layer = activeLayer, destinations = destinationData) {
  if (!Array.isArray(rawEntities)) return [];
  const seen = new Set();
  return rawEntities.slice(0, 80).reduce((entities, raw) => {
    const entity = normalizeMapEntity(raw, layer, destinations);
    if (!entity || seen.has(entity.id)) return entities;
    seen.add(entity.id);
    entities.push(entity);
    return entities;
  }, []);
}

function mapEntitySelectionId(entity) {
  const stablePart = String(entity?.id || '').replace(/[^A-Za-z0-9_-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
  return stablePart ? `entity-${stablePart}`.slice(0, 80) : '';
}

function mapEntityPlanningContext(entity, destinations = destinationData) {
  const destination = destinations?.[entity?.destination_id];
  const profile = mapEntityPlanningProfiles[entity?.kind];
  const selectionId = mapEntitySelectionId(entity);
  if (!destination || !profile || !selectionId) return null;
  const airportCode = boundedMapEntityString(destination.airportCode || destination.airport?.code || '', 3).toUpperCase();
  const hotelArea = boundedMapEntityString(entity.kind === 'hotel_area' ? entity.label : (destination.hotelArea || ''), 100);
  return {
    entityId: entity.id,
    selectionId,
    kind: entity.kind,
    label: entity.label,
    destinationId: entity.destination_id,
    latitude: entity.lat,
    longitude: entity.lng,
    airportCode: /^[A-Z]{3}$/.test(airportCode) ? airportCode : '',
    hotelArea,
    layer: profile.layer,
    module: profile.module,
    scope: profile.scope,
    target: profile.target,
    truthState: entity.truth_state,
    freshness: entity.freshness,
    dataMode: entity.data_mode,
    source: entity.provenance.source
  };
}

function setMapEntityPlanningSelection(entity, destinations = destinationData) {
  const context = mapEntityPlanningContext(entity, destinations);
  if (!context) return null;
  activeMapEntitySelection = context;
  setActivePlanningSelection({
    selectionId: context.selectionId,
    latitude: context.latitude,
    longitude: context.longitude,
    destination: context.destinationId,
    kind: 'map_point'
  });
  return context;
}

function clearActiveMapEntitySelection() {
  activeMapEntitySelection = null;
  const root = document.querySelector('[data-map-entity-explorer]');
  if (root) {
    delete root.dataset.committedEntity;
    root.classList.remove('has-plan-selection');
    const status = root.querySelector('[data-map-entity-selection-status]');
    if (status) {
      status.dataset.state = 'preview';
      const statusText = status.querySelector('span');
      if (statusText) statusText.textContent = 'בחרו נקודה כדי לחבר אותה לתוכנית החופשה המלאה.';
    }
  }
  const plan = document.querySelector('[data-destination-plan]');
  if (plan) {
    delete plan.dataset.mapEntityId;
    delete plan.dataset.mapEntityKind;
    plan.classList.remove('has-map-selection-change');
    plan.querySelectorAll('[data-plan-module][data-map-selection]').forEach(module => delete module.dataset.mapSelection);
  }
}

function mapEntityActionUrl(entity, context = mapEntityPlanningContext(entity)) {
  if (!context) return '';
  try {
    const url = new URL(entity.action.href, window.location.origin);
    if (url.origin !== window.location.origin || !['http:', 'https:'].includes(url.protocol)) return '';
    const targetContext = context.target === 'packages' ? 'packages' : context.target;
    const selection = activePlanningSelectionHandoffQuery(context.destinationId);
    Object.entries({...selection, ...discoveryTripContextQuery(targetContext)}).forEach(([key, value]) => {
      if (value !== '' && value !== undefined && value !== null) url.searchParams.set(key, String(value));
    });
    url.searchParams.set('destination', context.target === 'ai' ? context.destinationId : (context.airportCode || context.destinationId));
    url.searchParams.set('intent', activePlanIntent);
    if (context.target === 'hotels' && context.hotelArea) url.searchParams.set('area', context.hotelArea);
    if (context.target === 'ai') {
      url.searchParams.set('mode', 'destination');
      url.searchParams.set('scope', context.scope);
    }
    return url.toString();
  } catch (error) {
    return '';
  }
}

function normalizeMapSegmentPoint(raw = {}) {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const code = boundedMapEntityString(raw.code, 3).toUpperCase();
  const label = boundedMapEntityString(raw.label, 140);
  const latitude = Number(raw.lat);
  const longitude = Number(raw.lng);
  if (!/^[A-Z0-9]{3}$/.test(code) || !label || !Number.isFinite(latitude) || latitude < -90 || latitude > 90
    || !Number.isFinite(longitude) || longitude < -180 || longitude > 180) return null;
  return { code, label, lat: latitude, lng: longitude };
}

function normalizeMapSegmentTruth(raw = {}) {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const dataMode = boundedMapEntityString(raw.data_mode, 20);
  const truthState = boundedMapEntityString(raw.truth_state, 30);
  const freshness = boundedMapEntityString(raw.freshness, 30);
  const valid = mapEntityDataModes.has(dataMode) && mapEntityTruthStates.has(truthState)
    && mapEntityFreshnessStates.has(freshness) && raw.bookable === false
    && ((dataMode === 'demo' && truthState === 'planning') || (dataMode === 'live' && truthState !== 'planning'));
  return valid ? { data_mode: dataMode, truth_state: truthState, freshness, bookable: false } : null;
}

function normalizeMapSegmentCollection(rawSegments, destinations = destinationData) {
  if (!Array.isArray(rawSegments)) return [];
  const seen = new Set();
  return rawSegments.slice(0, 16).reduce((segments, raw) => {
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return segments;
    const id = boundedMapEntityString(raw.id, 120);
    const routeId = boundedMapEntityString(raw.route_id, 100);
    const destinationId = boundedMapEntityString(raw.destination_id, 60).toLowerCase();
    const sequence = Number(raw.sequence);
    const from = normalizeMapSegmentPoint(raw.from);
    const to = normalizeMapSegmentPoint(raw.to);
    const truth = normalizeMapSegmentTruth(raw.truth);
    const provenance = normalizeMapEntityProvenance(raw.provenance);
    const valid = /^[a-z0-9][a-z0-9:_-]{1,119}$/.test(id) && /^[a-z0-9][a-z0-9_-]{1,99}$/.test(routeId)
      && /^[a-z0-9][a-z0-9-]{1,59}$/.test(destinationId) && Object.prototype.hasOwnProperty.call(destinations, destinationId)
      && Number.isSafeInteger(sequence) && sequence >= 1 && sequence <= 8 && from && to && truth && provenance;
    if (!valid || seen.has(id)) return segments;
    seen.add(id);
    segments.push({ id, route_id: routeId, destination_id: destinationId, sequence, from, to, truth, provenance });
    return segments;
  }, []).sort((first, second) => first.sequence - second.sequence);
}

function mapEntityPlanePoint(latitude, longitude) {
  return {
    x: Math.min(100, Math.max(0, ((Number(longitude) + 180) / 360) * 100)),
    y: Math.min(100, Math.max(0, ((90 - Number(latitude)) / 180) * 100))
  };
}

function mapEntityTruthCopy(entity) {
  if (entity.truth_state === 'supplier_snapshot') return 'נתון ספק מהבדיקה האחרונה. המחיר, הזמינות והתנאים יאומתו שוב לפני אישור.';
  if (entity.truth_state === 'last_observed') return 'זהו הנתון האחרון שנצפה ואינו הצעה נוכחית. נדרשת בדיקה מחדש לפני החלטה.';
  return 'מידע לתכנון ולהשוואה. המחיר, הזמינות והתנאים מאומתים לפני התשלום.';
}

function mapEntityFreshnessCopy(freshness) {
  return ({ current: 'מעודכן למועד הבדיקה', refreshing: 'מתעדכן כעת', stale: 'נדרש רענון', fallback: 'מידע תכנוני זמין' })[freshness] || 'נדרשת בדיקה';
}

function mapEntityPriceCopy(entity) {
  if (!entity.price) return 'מחיר ייבדק לפי התאריכים, הנוסעים והתנאים שבחרתם.';
  const basis = entity.price.basis === 'per_night' ? 'ללילה' : 'לאדם, לכל התכנון';
  const state = entity.price.state === 'supplier_snapshot'
    ? 'צילום מחיר ספק'
    : (entity.price.state === 'last_observed' ? 'מחיר אחרון שנצפה' : 'מחיר לתכנון');
  return `${entity.price.formatted} · ${basis} · ${state}`;
}

function mapEntityMarkerLabel(entity) {
  if (!entity.price) return entity.label;
  const state = entity.price.state === 'planning'
    ? 'לתכנון'
    : (entity.price.state === 'last_observed' ? 'אחרון שנצפה' : 'נבדק');
  return `${entity.price.formatted} · ${state}`;
}

function mapEntityFallbackAction(reason = 'empty') {
  const pointContext = activeFreePlanningPoint() ? activePlanningSelectionQuery() : {};
  if (reason === 'point') return { label: 'זהו את האזור עם מתכנן החופשה', href: destinationPlanUrl('/ai-planner/', { ...pointContext, scope: fullTripPlanningScope }) };
  const layerPaths = { deals: '/packages/', hotels: '/hotels/', airports: '/flights/', weather: '/ai-planner/' };
  const layerLabels = { deals: 'פתחו חיפוש חופשה', hotels: 'פתחו חיפוש לינה', airports: 'השוו דרכי הגעה', weather: 'תכננו לפי העונה' };
  return { label: layerLabels[activeLayer] || 'פתחו תכנון חופשה', href: destinationPlanUrl(layerPaths[activeLayer] || '/ai-planner/', discoveryTripContextQuery(activeLayer === 'airports' ? 'flights' : (activeLayer === 'hotels' ? 'hotels' : 'map'))) };
}

function setMapEntityActionContent(action, label) {
  if (!action) return;
  action.replaceChildren();
  appendTextElement(action, 'span', label);
  const icon = document.createElement('i');
  icon.setAttribute('data-lucide', 'arrow-left');
  icon.setAttribute('aria-hidden', 'true');
  action.append(icon);
}

function resetMapEntityExplorer(reason = 'empty') {
  discoveryMapEntities = [];
  discoveryMapSegments = [];
  activeMapEntityId = '';
  const root = document.querySelector('[data-map-entity-explorer]');
  if (!root) return;
  const messages = {
    loading: ['פותחים את שכבת המידע', 'הנקודות הקודמות הוסרו. מידע חדש יופיע כאן לאחר שהבדיקה תסתיים.'],
    open: ['בחרו יעד או נקודה על העולם', 'אפשר להתחיל מהגלובוס או לתת למתכנן החופשה להציע כיוון לפי התקציב והסגנון.'],
    point: ['הנקודה נשמרה', 'זהו את האזור כדי לפתוח שדות תעופה, לינה, מזג אוויר ואפשרויות חופשה מתאימות.'],
    error: ['המידע המפורט לא התעדכן', 'הגלובוס והכלים האחרים עדיין זמינים. אפשר לנסות שוב או להמשיך לחיפוש המוצר המתאים.'],
    empty: ['אין נקודות מתאימות לבחירה הזאת', 'שנו יעד, תקציב או שכבה כדי לפתוח אפשרויות נוספות.']
  };
  const [titleText, summaryText] = messages[reason] || messages.empty;
  root.dataset.state = reason;
  root.classList.remove('is-updating');
  root.setAttribute('aria-busy', String(reason === 'loading'));
  const layer = root.querySelector('[data-map-entity-layer-label]');
  const count = root.querySelector('[data-map-entity-count]');
  const kind = root.querySelector('[data-map-entity-kind]');
  const title = root.querySelector('[data-map-entity-title]');
  const summary = root.querySelector('[data-map-entity-summary]');
  const price = root.querySelector('[data-map-entity-price]');
  const truth = root.querySelector('[data-map-entity-truth]');
  const freshness = root.querySelector('[data-map-entity-freshness]');
  const source = root.querySelector('[data-map-entity-source]');
  const action = root.querySelector('[data-map-entity-action]');
  const selectionStatus = root.querySelector('[data-map-entity-selection-status]');
  const empty = root.querySelector('[data-map-entity-empty]');
  if (layer) layer.textContent = mapEntityLayerLabels[activeLayer] || 'מידע על המפה';
  if (count) count.textContent = reason === 'loading' ? 'מעדכנים' : '0 נקודות';
  if (kind) kind.textContent = reason === 'point' ? 'נקודה חופשית' : 'שכבת מידע';
  if (title) title.textContent = titleText;
  if (summary) summary.textContent = summaryText;
  if (price) price.textContent = 'מחיר יוצג רק עם הקשר ברור ומועד בדיקה.';
  if (truth) truth.textContent = 'המחיר, הזמינות והתנאים מאומתים לפני התשלום.';
  if (freshness) freshness.textContent = reason === 'loading' ? 'בדיקה פעילה' : 'ממתין לבחירה';
  if (source) source.textContent = 'מקור יוצג לצד כל נתון.';
  if (selectionStatus) {
    selectionStatus.dataset.state = 'preview';
    const selectionStatusText = selectionStatus.querySelector('span');
    if (selectionStatusText) selectionStatusText.textContent = 'בחרו נקודה כדי לחבר אותה לתוכנית החופשה המלאה.';
  }
  if (empty) {
    empty.hidden = false;
    empty.textContent = summaryText;
  }
  root.querySelector('[data-map-entity-markers]')?.replaceChildren();
  root.querySelector('[data-map-entity-segments]')?.replaceChildren();
  root.querySelector('[data-map-entity-list]')?.replaceChildren();
  if (action) {
    const fallback = mapEntityFallbackAction(reason);
    action.href = fallback.href;
    setMapEntityActionContent(action, fallback.label);
  }
  renderIcons();
}

function setMapEntityExplorerLoading() {
  resetMapEntityExplorer('loading');
}

function renderMapEntitySegments(container, segments) {
  if (!container) return;
  container.replaceChildren();
  segments.forEach(segment => {
    const start = mapEntityPlanePoint(segment.from.lat, segment.from.lng);
    const end = mapEntityPlanePoint(segment.to.lat, segment.to.lng);
    const startX = start.x * 10;
    const startY = start.y * 5.2;
    const endX = end.x * 10;
    const endY = end.y * 5.2;
    const bend = Math.min(90, Math.max(24, Math.abs(endX - startX) * .08));
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', `M ${startX.toFixed(1)} ${startY.toFixed(1)} Q ${((startX + endX) / 2).toFixed(1)} ${(Math.min(startY, endY) - bend).toFixed(1)} ${endX.toFixed(1)} ${endY.toFixed(1)}`);
    path.setAttribute('data-map-segment-id', segment.id);
    path.setAttribute('data-truth-state', segment.truth.truth_state);
    container.append(path);
  });
}

function mapEntitySelectionCopy(entity, context, destination) {
  const city = destination?.city || context.destinationId;
  const copy = {
    deal: {
      result: 'אפשרות החופשה שבחרתם',
      planState: `${entity.label} נוספה להשוואה של ${city}`,
      planSummary: 'האפשרות מחוברת עכשיו לטיסה, ללינה, להעברות, לפעילויות, לביטוח ולעלות המלאה. אפשר לערוך כל חלק לפני בקשת הצעה.',
      moduleTitle: `${entity.label} נוספה להשוואת העלות המלאה`,
      moduleDetail: 'הרכב החבילה והמחיר הסופי ייבדקו לפי תאריכים, נוסעים, מלאי ותנאים.'
    },
    hotel_area: {
      result: 'אזור הלינה שבחרתם',
      planState: `${entity.label} נוסף כבסיס לחיפוש הלינה ב${city}`,
      planSummary: 'האזור מחובר עכשיו למסלול, לזמני המעבר, למקומות שמעניינים אתכם ולתקציב. אפשר להחליף אזור או לפתוח בדיקת מלונות.',
      moduleTitle: `מתחילים את בדיקת הלינה ב${context.hotelArea || entity.label}`,
      moduleDetail: 'מלון, חדר, מלאי, מסים, ביטול ומחיר סופי ייבדקו לפי ההרכב והתאריכים.'
    },
    airport: {
      result: 'שדה התעופה שבחרתם',
      planState: `${entity.label} נוסף כנקודת ההגעה ל${city}`,
      planSummary: 'השדה מחובר עכשיו להשוואת המסלולים, לזמן הדרך, לכבודה, להעברה למקום הלינה ולעלות המלאה.',
      moduleTitle: `משווים דרכי הגעה דרך ${context.airportCode || entity.label}`,
      moduleDetail: 'שעות, עצירות, כבודה, סוג הכרטיס, סיכון בקונקשן ומחיר ייבדקו יחד.'
    },
    weather: {
      result: 'תנאי העונה שבחרתם',
      planState: `תנאי העונה ב${city} נוספו לתכנון`,
      planSummary: 'העונה מחוברת עכשיו למסלול היומי, לפעילויות, לציוד, לביטוח ולחלופות לימים שבהם התנאים משתנים.',
      moduleTitle: `מתאימים את הפעילויות והציוד לעונה ב${city}`,
      moduleDetail: 'תחזית מדויקת תיבדק לפי תאריך. בינתיים אפשר לבנות חלופות ודרישות ציוד.'
    }
  }[entity.kind];
  return copy || {
    result: 'הנקודה שבחרתם',
    planState: `${entity.label} נוסף לתוכנית`,
    planSummary: 'הנקודה מחוברת עכשיו לכל חלקי החופשה ואפשר לערוך את הבחירה.',
    moduleTitle: entity.label,
    moduleDetail: entity.summary
  };
}

function applyMapEntityPlanningContext(entity, context, { announce = true, animate = true } = {}) {
  const destination = destinationData[context.destinationId];
  if (!destination) return false;
  const copy = mapEntitySelectionCopy(entity, context, destination);
  const actionUrl = mapEntityActionUrl(entity, context) || entity.action.href;
  const root = document.querySelector('[data-map-entity-explorer]');
  if (root) {
    root.dataset.committedEntity = entity.id;
    root.classList.add('has-plan-selection');
    const status = root.querySelector('[data-map-entity-selection-status]');
    if (status) {
      status.dataset.state = 'added';
      const statusText = status.querySelector('span');
      if (statusText) statusText.textContent = `${entity.label} נוסף לתוכנית. אפשר לערוך כל חלק לפני בדיקת המחיר והזמינות.`;
    }
  }

  document.querySelectorAll('[data-map-result]').forEach(card => {
    const resultContext = card.querySelector('[data-result-context]');
    if (resultContext) resultContext.textContent = copy.result;
    if (entity.kind === 'hotel_area') {
      const hotel = card.querySelector('[data-result-hotel]');
      if (hotel) hotel.textContent = context.hotelArea || entity.label;
      const hotels = card.querySelector('[data-result-hotels]');
      if (hotels) hotels.href = actionUrl;
    }
    if (entity.kind === 'airport') {
      const airport = card.querySelector('[data-result-airport]');
      if (airport) airport.textContent = entity.label;
    }
  });

  const plan = document.querySelector('[data-destination-plan]');
  if (plan) {
    plan.querySelectorAll('[data-plan-module][data-map-selection]').forEach(module => delete module.dataset.mapSelection);
    plan.dataset.mapEntityId = entity.id;
    plan.dataset.mapEntityKind = entity.kind;
    const planFields = {
      '[data-plan-state]': copy.planState,
      '[data-plan-summary]': copy.planSummary,
      '[data-plan-truth]': `הבחירה נוספה לתכנון, לא להזמנה. ${mapEntityTruthCopy(entity)}`
    };
    Object.entries(planFields).forEach(([selector, value]) => {
      const field = plan.querySelector(selector);
      if (field) field.textContent = value;
    });
    const primaryLinkByKind = {
      deal: '[data-plan-total]',
      hotel_area: '[data-plan-stay]',
      airport: '[data-plan-flight]',
      weather: '[data-plan-weather]'
    };
    const primaryLink = plan.querySelector(primaryLinkByKind[entity.kind] || '');
    if (primaryLink) primaryLink.href = actionUrl;
    const module = plan.querySelector(`[data-plan-module="${context.module}"]`);
    if (module) {
      module.dataset.mapSelection = 'true';
      const title = module.querySelector('[data-plan-module-title]');
      const state = module.querySelector('[data-plan-module-state]');
      const detail = module.querySelector('[data-plan-module-detail]');
      const action = module.querySelector('[data-plan-module-action]');
      if (title) title.textContent = copy.moduleTitle;
      if (state) state.textContent = 'נוסף לתוכנית · פתוח לעריכה';
      if (detail) detail.textContent = copy.moduleDetail;
      if (action) action.href = actionUrl;
    }
    plan.classList.remove('has-map-selection-change');
    if (animate && !prefersReducedMotion()) {
      void plan.offsetWidth;
      plan.classList.add('has-map-selection-change');
      window.clearTimeout(plan.traVelMapSelectionTimer);
      plan.traVelMapSelectionTimer = window.setTimeout(() => plan.classList.remove('has-map-selection-change'), 760);
    }
  }

  const currentSupplierSelection = context.dataMode === 'live' && context.truthState === 'supplier_snapshot'
    && context.freshness === 'current' && discoverySnapshotIsCurrent();
  const staleSelection = context.truthState === 'last_observed' || context.freshness === 'stale' || discoverySnapshotIsStale();
  setMapProgressState({
    point: 'confirmed',
    destination: 'confirmed',
    scopes: 'confirmed',
    live: currentSupplierSelection ? 'confirmed' : (staleSelection ? 'stale' : 'waiting'),
    destinationDetail: `${entity.label} נוסף לתוכנית`,
    liveDetail: currentSupplierSelection ? 'נתון ספק עדכני התקבל; ייבדק שוב לפני אישור' : (staleSelection ? 'המידע האחרון דורש רענון' : 'מחיר וזמינות ייבדקו לפי הפרטים שלכם'),
    announce
  });
  refreshMapSaveControls();
  return true;
}

function selectMapEntity(entityId, { focusGlobe = true, commitPlanning = false, syncHistory = true, hydrateDestination = true, announce = true, animatePlan = true } = {}) {
  const entity = discoveryMapEntities.find(item => item.id === entityId);
  const root = document.querySelector('[data-map-entity-explorer]');
  if (!entity || !root) return false;
  const previousDestination = activeDestination;
  activeMapEntityId = entity.id;
  root.dataset.selectedEntity = entity.id;
  root.querySelectorAll('[data-map-entity-id]').forEach(control => {
    const selected = control.dataset.mapEntityId === entity.id;
    control.classList.toggle('is-active', selected);
    if (control.matches('button')) control.setAttribute('aria-pressed', String(selected));
  });
  const fields = {
    '[data-map-entity-kind]': mapEntityKindLabels[entity.kind] || 'מידע על המפה',
    '[data-map-entity-title]': entity.label,
    '[data-map-entity-summary]': entity.summary,
    '[data-map-entity-price]': mapEntityPriceCopy(entity),
    '[data-map-entity-truth]': mapEntityTruthCopy(entity),
    '[data-map-entity-freshness]': mapEntityFreshnessCopy(entity.freshness),
    '[data-map-entity-source]': `מקור: ${entity.provenance.source}${entity.provenance.reviewed_on ? ` · נבדק ${entity.provenance.reviewed_on}` : ''}`
  };
  Object.entries(fields).forEach(([selector, value]) => {
    const field = root.querySelector(selector);
    if (field) field.textContent = value;
  });
  const detail = root.querySelector('[data-map-entity-detail]');
  if (detail) detail.dataset.truthState = entity.truth_state;
  const action = root.querySelector('[data-map-entity-action]');
  if (action) {
    action.href = entity.action.href;
    setMapEntityActionContent(action, entity.action.label);
    action.dataset.requiresLiveSearch = String(entity.action.requires_live_search);
  }
  const selectionStatus = root.querySelector('[data-map-entity-selection-status]');
  if (selectionStatus && (!commitPlanning || !destinationData[entity.destination_id])) {
    selectionStatus.dataset.state = 'preview';
    const selectionStatusText = selectionStatus.querySelector('span');
    if (selectionStatusText) selectionStatusText.textContent = `בחרו את ${entity.label} כדי לחבר אותה לתוכנית החופשה המלאה.`;
  }
  if (commitPlanning && destinationData[entity.destination_id]) {
    const context = setMapEntityPlanningSelection(entity);
    if (!context) return false;
    discoveryDestinationMode = 'recommended';
    discoveryDestinationLocked = true;
    discoverySelectedPlan = previousDestination === entity.destination_id ? discoverySelectedPlan : null;
    if (previousDestination !== entity.destination_id) {
      activeRouteId = '';
      activeRouteSelectionLocked = false;
    }
    setActiveDestination(entity.destination_id, null, {
      animate: animatePlan,
      responseConfirmed: false,
      responseState: settledDiscoveryResponseState(),
      userSelected: true,
      globeAnimate: focusGlobe,
      globeFocus: focusGlobe,
      globeAnnounce: false,
      pulseRoute: false
    });
    if (action) action.href = mapEntityActionUrl(entity, context) || entity.action.href;
    applyMapEntityPlanningContext(entity, context, { announce, animate: animatePlan });
    if (syncHistory) syncDiscoveryUrl('push');
    if (hydrateDestination && previousDestination !== entity.destination_id) {
      hydrateDiscovery(discoveryRequestParams({ destination: entity.destination_id }));
    }
  } else if (focusGlobe && destinationData[entity.destination_id]) {
    const globeRoot = document.querySelector('.theme-map-shell [data-globe-3d]');
    window.traVelGlobe3D?.focusDestination(entity.destination_id, { root: globeRoot, animate: !prefersReducedMotion(), pulse: false, announce: false });
  }
  return true;
}

function renderMapEntityExplorer(entities, segments, { preferredDestination = '', reason = 'empty' } = {}) {
  const root = document.querySelector('[data-map-entity-explorer]');
  discoveryMapEntities = Array.isArray(entities) ? entities : [];
  discoveryMapSegments = Array.isArray(segments) ? segments : [];
  if (!root) return;
  if (!discoveryMapEntities.length) {
    clearActiveMapEntitySelection();
    resetMapEntityExplorer(reason);
    return;
  }
  root.dataset.state = 'ready';
  root.setAttribute('aria-busy', 'false');
  root.classList.remove('is-updating');
  if (!prefersReducedMotion()) {
    void root.offsetWidth;
    root.classList.add('is-updating');
    window.clearTimeout(root.traVelEntityMotionTimer);
    root.traVelEntityMotionTimer = window.setTimeout(() => root.classList.remove('is-updating'), 520);
  }
  const layer = root.querySelector('[data-map-entity-layer-label]');
  const count = root.querySelector('[data-map-entity-count]');
  const empty = root.querySelector('[data-map-entity-empty]');
  if (layer) layer.textContent = mapEntityLayerLabels[activeLayer] || 'מידע על המפה';
  if (count) count.textContent = `${discoveryMapEntities.length} נקודות לבחירה`;
  if (empty) empty.hidden = true;
  renderMapEntitySegments(root.querySelector('[data-map-entity-segments]'), discoveryMapSegments);
  const markers = root.querySelector('[data-map-entity-markers]');
  const list = root.querySelector('[data-map-entity-list]');
  markers?.replaceChildren();
  list?.replaceChildren();
  discoveryMapEntities.forEach(entity => {
    const destination = destinationData[entity.destination_id];
    const point = mapEntityPlanePoint(entity.lat, entity.lng);
    const markerItem = document.createElement('span');
    markerItem.className = 'map-entity-marker-item';
    markerItem.setAttribute('role', 'listitem');
    markerItem.style.setProperty('--entity-x', `${point.x.toFixed(2)}%`);
    markerItem.style.setProperty('--entity-y', `${point.y.toFixed(2)}%`);
    const marker = document.createElement('button');
    marker.type = 'button';
    marker.className = `map-entity-marker map-entity-marker--${entity.kind}`;
    marker.dataset.mapEntityId = entity.id;
    marker.setAttribute('aria-pressed', 'false');
    marker.setAttribute('aria-label', `${entity.label}, ${destination?.city || ''}, ${mapEntityMarkerLabel(entity)}`.replace(/,\s*$/, ''));
    const markerIcon = document.createElement('i');
    markerIcon.setAttribute('data-lucide', mapEntityKindIcons[entity.kind] || 'map-pin');
    appendTextElement(marker, 'span', mapEntityMarkerLabel(entity));
    marker.prepend(markerIcon);
    marker.addEventListener('click', () => selectMapEntity(entity.id, { commitPlanning: true }));
    markerItem.append(marker);
    markers?.append(markerItem);

    const listItem = document.createElement('article');
    listItem.className = 'map-entity-list-item';
    listItem.setAttribute('role', 'listitem');
    const listButton = document.createElement('button');
    listButton.type = 'button';
    listButton.dataset.mapEntityId = entity.id;
    listButton.setAttribute('aria-pressed', 'false');
    appendTextElement(listButton, 'small', `${mapEntityKindLabels[entity.kind]} · ${destination?.city || destination?.country || ''}`.replace(/ ·\s*$/, ''));
    appendTextElement(listButton, 'strong', entity.label);
    appendTextElement(listButton, 'span', entity.price ? mapEntityPriceCopy(entity) : mapEntityFreshnessCopy(entity.freshness));
    listButton.addEventListener('click', () => selectMapEntity(entity.id, { commitPlanning: true }));
    listItem.append(listButton);
    list?.append(listItem);
  });
  const restoredSelection = discoveryMapEntities.find(entity => mapEntitySelectionId(entity) === activePlanningSelection?.selection_id
    && entity.destination_id === activePlanningSelection?.destination);
  if (!restoredSelection && activeMapEntitySelection && !discoveryMapEntities.some(entity => entity.id === activeMapEntitySelection.entityId)) {
    clearActiveMapEntitySelection();
  }
  const preferred = restoredSelection
    || discoveryMapEntities.find(entity => entity.id === activeMapEntityId)
    || discoveryMapEntities.find(entity => entity.destination_id === preferredDestination)
    || discoveryMapEntities[0];
  selectMapEntity(preferred.id, {
    focusGlobe: false,
    commitPlanning: Boolean(restoredSelection),
    syncHistory: false,
    hydrateDestination: false,
    announce: false,
    animatePlan: false
  });
  renderIcons();
}

function pinLabel(data) {
  const liveLayers = data.liveLayers || resolveDiscoveryLiveLayers(discoveryFieldProvenance, data.id);
  const current = !discoveryRequestPending && discoverySnapshotIsCurrent();
  if (activeLayer === 'hotels') return current && liveLayers.hotels ? (data.hotelPrice || data.total) : data.city;
  if (activeLayer === 'airports') return data.airportCode || data.airport;
  if (activeLayer === 'weather') return current && liveLayers.weather ? data.weather : data.city;
  return current && liveLayers.deals ? data.price : data.city;
}

function bindDestinationPin(pin) {
  if (!pin || pin.dataset.selectionBound === 'true') return;
  pin.dataset.selectionBound = 'true';
  pin.addEventListener('click', event => {
    event.stopPropagation();
    clearActiveMapEntitySelection();
    discoveryDestinationMode = 'recommended';
    setActivePlanningSelection({ latitude: pin.dataset.latitude, longitude: pin.dataset.longitude, destination: pin.dataset.destination, kind: 'destination' });
    discoveryDestinationLocked = true;
    discoverySelectedPlan = null;
    activeRouteId = '';
    activeRouteSelectionLocked = false;
    setActiveDestination(pin.dataset.destination, pin, { animate: true, responseConfirmed: false, userSelected: true });
    syncDiscoveryUrl('push');
    hydrateDiscovery(discoveryRequestParams({ destination: pin.dataset.destination }));
  });
}

function bindExplorationHubMarker(marker) {
  if (!marker || marker.dataset.explorationSelectionBound === 'true') return;
  marker.dataset.explorationSelectionBound = 'true';
  marker.addEventListener('click', event => {
    // The WebGL controller owns normal clicks. This path keeps the same in-flow
    // planning result usable when WebGL is unavailable before initialization.
    if (event.defaultPrevented) return;
    const hub = explorationHubData[String(marker.dataset.explorationHub || '')];
    if (!hub) return;
    event.preventDefault();
    event.stopPropagation();
    clearActiveMapEntitySelection();
    const selection = setActivePlanningSelection({
      latitude: hub.latitude,
      longitude: hub.longitude,
      destination: '',
      kind: 'map_point'
    });
    renderExplorationHubSelection({
      selectionId: selection.selection_id,
      latitude: hub.latitude,
      longitude: hub.longitude,
      inputType: event.detail === 0 ? 'keyboard' : 'pointer',
      supported: true,
      supportedRadiusKm: hub.radiusKm,
      selectionKind: 'exploration_hub',
      planningAction: 'open_hub',
      hubId: hub.id,
      hubCity: hub.city,
      hubCountry: hub.country,
      hubIataSearchCode: hub.iataSearchCode,
      hubLiveSearchScopes: hub.liveSearchScopes,
      hubDistanceKm: 0
    }, marker.closest('[data-globe-3d]'));
  });
}

function bindMapDestinationLink(link) {
  if (!link || link.dataset.selectionBound === 'true') return;
  link.dataset.selectionBound = 'true';
  link.addEventListener('click', event => {
    const destination = String(link.dataset.destination || '');
    const data = destinationData[destination];
    if (!data) return;
    event.preventDefault();
    clearActiveMapEntitySelection();
    discoveryDestinationMode = 'recommended';
    setActivePlanningSelection({ latitude: data.latitude, longitude: data.longitude, destination, kind: 'destination' });
    discoveryDestinationLocked = true;
    discoverySelectedPlan = null;
    activeRouteId = '';
    activeRouteSelectionLocked = false;
    const pin = document.querySelector(`[data-discovery-globe] .price-pin[data-destination="${CSS.escape(destination)}"]`);
    setActiveDestination(destination, pin, { animate: true, responseConfirmed: false, userSelected: true });
    syncDiscoveryUrl('push');
    hydrateDiscovery(discoveryRequestParams({ destination }));
  });
}

function reconcileDestinationPins() {
  document.querySelectorAll('[data-discovery-globe]').forEach(globe => {
    Object.values(destinationData).forEach(data => {
      let pin = Array.from(globe.querySelectorAll('.price-pin[data-destination]')).find(item => item.dataset.destination === data.id);
      if (!pin) {
        pin = document.createElement('button');
        pin.type = 'button';
        pin.className = 'price-pin';
        pin.dataset.destination = data.id;
        pin.setAttribute('aria-pressed', 'false');
        globe.append(pin);
      }
      bindDestinationPin(pin);
    });
  });
}

function updatePins() {
  reconcileDestinationPins();
  document.querySelectorAll('[data-discovery-globe] .price-pin[data-destination]').forEach(pin => {
    const data = destinationData[pin.dataset.destination];
    pin.hidden = !data;
    if (!data) return;
    const label = pinLabel(data);
    pin.textContent = label;
    if (Number.isFinite(Number(data.latitude))) pin.dataset.latitude = String(data.latitude);
    if (Number.isFinite(Number(data.longitude))) pin.dataset.longitude = String(data.longitude);
    if (!pin.closest('[data-globe-3d]')) {
      pin.style.left = `${data.x}%`;
      pin.style.top = `${data.y}%`;
    }
    pin.setAttribute('aria-label', label === data.city ? data.city : `${data.city}, ${label}`);
  });
  window.traVelGlobe3D?.setDestinations(destinationData);
}

function syncMapDestinationLinks(destination) {
  document.querySelectorAll('[data-map-destination-link][data-destination]').forEach(link => {
    const active = link.dataset.destination === destination;
    link.classList.toggle('is-active', active);
    if (active) link.setAttribute('aria-current', 'true');
    else link.removeAttribute('aria-current');
  });
}

function setActiveDestination(key, pin, motion = true) {
  const data = destinationData[key];
  if (!data) return;
  const destinationSelectionMissing = !activePlanningSelection || activePlanningSelection.destination !== key;
  const destinationCoordinatesMissing = activePlanningSelection?.kind === 'destination'
    && activePlanningSelection.destination === key
    && (!Number.isFinite(activePlanningSelection.latitude) || !Number.isFinite(activePlanningSelection.longitude));
  if (destinationSelectionMissing || destinationCoordinatesMissing) {
    setActivePlanningSelection({ selectionId: destinationCoordinatesMissing ? activePlanningSelection.selection_id : '', latitude: data.latitude, longitude: data.longitude, destination: key, kind: 'destination' });
  }
  const animatePlan = typeof motion === 'object' ? motion.animate !== false : Boolean(motion);
  const userSelected = typeof motion === 'object' && motion.userSelected === true;
  const responseConfirmed = typeof motion === 'object' ? motion.responseConfirmed === true : Boolean(motion);
  const responseState = typeof motion === 'object' && ['pending', 'current', 'stale', 'fallback'].includes(motion.responseState)
    ? motion.responseState
    : (responseConfirmed ? 'current' : 'pending');
  const globeAnimate = typeof motion === 'object' && Object.prototype.hasOwnProperty.call(motion, 'globeAnimate')
    ? motion.globeAnimate === true
    : animatePlan && Boolean(pin);
  const globePulse = typeof motion === 'object' && Object.prototype.hasOwnProperty.call(motion, 'pulseRoute')
    ? motion.pulseRoute === true
    : responseConfirmed;
  const globeAnnounce = typeof motion === 'object' && Object.prototype.hasOwnProperty.call(motion, 'globeAnnounce')
    ? motion.globeAnnounce !== false
    : true;
  const globeRotations = typeof motion === 'object' ? Number(motion.globeRotations) || 0 : 0;
  const globeDuration = typeof motion === 'object' ? Number(motion.globeDuration) || 680 : 680;
  const globeFocus = typeof motion === 'object' && Object.prototype.hasOwnProperty.call(motion, 'globeFocus')
    ? motion.globeFocus !== false
    : true;
  const requestedGlobeRoot = typeof motion === 'object' && motion.globeRoot?.matches?.('[data-globe-3d]')
    ? motion.globeRoot
    : null;
  const globeRoot = requestedGlobeRoot
    || pin?.closest?.('[data-globe-3d]')
    || (isMapWorkspacePage()
      ? document.querySelector('.theme-map-shell [data-globe-3d][data-discovery-globe]')
      : document.querySelector('[data-home-globe]'));
  discoveryLiveLayers = data.liveLayers || resolveDiscoveryLiveLayers(discoveryFieldProvenance, key);
  const snapshotCurrent = responseState === 'current' && discoverySnapshotIsCurrent();
  const snapshotStale = responseState === 'stale' && discoverySnapshotIsStale();
  const hasDealSnapshot = discoveryLiveLayers.deals;
  const hasHotelSnapshot = discoveryLiveLayers.hotels;
  const hasRouteSnapshot = discoveryLiveLayers.airports;
  const hasAirportSnapshot = discoveryLiveLayers.airportDetails;
  const hasWeatherSnapshot = discoveryLiveLayers.weather;
  const hasSeasonSnapshot = fieldProvenanceLive(discoveryFieldProvenance, 'weather_season', key);
  const hasLiveDealPrices = snapshotCurrent && hasDealSnapshot;
  const hasLiveHotelPrices = snapshotCurrent && hasHotelSnapshot;
  const hasLiveRouteData = snapshotCurrent && hasRouteSnapshot;
  const hasLiveAirportDetails = snapshotCurrent && hasAirportSnapshot;
  const hasLiveWeather = snapshotCurrent && hasWeatherSnapshot;
  const hasLiveSeason = snapshotCurrent && hasSeasonSnapshot;
  const displayTags = hasLiveDealPrices
    ? [`${data.nights} לילות`, 'מחיר ספק התקבל', 'תנאים לפני אישור']
    : (snapshotStale && hasDealSnapshot
      ? [`${data.nights} לילות`, 'מחיר אחרון שנבדק', 'בדקו שוב לפני החלטה']
      : (data.tags?.length ? data.tags : ['מדריך ליעד', 'אזורי לינה', 'מסלולים אפשריים']));
  const layerStates = {
    deals: {
      label: 'מחיר וזמינות',
      total: hasLiveDealPrices || (snapshotStale && hasDealSnapshot) ? data.total : (data.total || 'מחיר לתכנון'),
      price: data.price || 'בהצעה האישית',
      note: hasLiveDealPrices
        ? 'מחיר ההצעה התקבל מהספק במועד המצוין. זמינות, הרכב ותנאים יאומתו לפני אישור.'
        : (snapshotStale && hasDealSnapshot ? 'זהו צילום הספק האחרון, לא הצעה נוכחית. נדרש אימות מחיר וזמינות מחדש.' : (data.note || 'המחיר, הזמינות והתנאים מאומתים לפני התשלום.'))
    },
    hotels: {
      label: 'לינה באזור הנכון',
      total: hasLiveHotelPrices || (snapshotStale && hasHotelSnapshot) ? (data.hotelPrice || data.total) : (data.hotelPrice || 'תקציב לינה לתכנון'),
      price: data.hotelArea || 'אזור לינה',
      note: hasLiveHotelPrices
        ? 'מחיר החדר התקבל מהספק במועד המצוין. מסים, מלאי, ביטול ותנאים יאומתו בהצעה מתוארכת.'
        : (snapshotStale && hasHotelSnapshot ? 'זהו מחיר החדר האחרון שנבדק. מלאי, מסים וביטול עשויים להשתנות ויש לבדוק שוב.' : 'אזורי הלינה והתקציב עוזרים לבחור. המחיר, הזמינות והתנאים מאומתים לפני התשלום.')
    },
    airports: {
      label: 'שדה ודרך',
      total: data.airportCode || 'בדיקת שדה',
      price: data.airportDirect ? 'טיסה ישירה לבדיקה' : 'בדקו אפשרויות עם עצירה',
      note: 'זמן, מחיר, כבודה ותנאי כרטיס ייבדקו לפי תאריכים.'
    },
    weather: {
      label: 'מזג אוויר ועונה',
      total: hasLiveWeather || (snapshotStale && hasWeatherSnapshot) ? data.weather : 'לפי תאריך',
      price: hasLiveWeather || (snapshotStale && hasWeatherSnapshot) ? (data.weatherCondition || 'המידע האחרון שנבדק') : 'בחרו מועד',
      note: hasLiveWeather
        ? (hasLiveSeason ? `התאמת עונה מעודכנת: ${data.seasonFit || 'לפי מסלול'}.` : 'התנאים הנוכחיים עודכנו. התאמת העונה תיבדק לפי תאריך הנסיעה.')
        : (snapshotStale && hasWeatherSnapshot ? 'מוצג המידע האחרון שנבדק. זו אינה תחזית למועד הנסיעה ויש לבדוק שוב.' : 'תחזית תוצג רק למועד נסיעה מוגדר.')
    }
  };
  const layerState = layerStates[activeLayer] || layerStates.deals;
  activeDestination = key;
  document.querySelectorAll('.price-pin[data-destination]').forEach(item => {
    const active = item.dataset.destination === key;
    item.classList.toggle('is-active', active);
    if (item.matches('button')) item.setAttribute('aria-pressed', String(active));
    else if (active) item.setAttribute('aria-current', 'location');
    else item.removeAttribute('aria-current');
  });
  syncMapDestinationLinks(key);
  pin?.classList.add('is-active');
  if (globeFocus && globeRoot) {
    window.traVelGlobe3D?.focusDestination(key, { animate: globeAnimate, pulse: globePulse, announce: globeAnnounce, rotations: globeRotations, duration: globeDuration, root: globeRoot });
  }

  const selectionHandoff = activePlanningSelectionHandoffQuery(key);
  document.querySelectorAll('[data-map-result]').forEach(card => {
    card.dataset.destination = key;
    card.removeAttribute('data-selection-kind');
    card.removeAttribute('data-empty');
    const resultContext = card.querySelector('[data-result-context]');
    if (resultContext && userSelected) resultContext.textContent = 'היעד שבחרתם';
    const image = card.querySelector('[data-result-image]');
    if (image) {
      image.hidden = false;
      image.src = data.image;
      image.alt = `${data.city}, ${data.country}`;
    }
    const fields = {
      '[data-result-city]': `${data.city}, ${data.country}`,
      '[data-result-state-label]': layerState.label,
      '[data-result-price]': layerState.price,
      '[data-result-total]': layerState.total,
      '[data-result-note]': layerState.note,
      '[data-result-airport]': hasLiveAirportDetails || (snapshotStale && hasAirportSnapshot) ? data.airport : (data.airportCode || 'שדות תעופה'),
      '[data-result-hotel]': hasLiveHotelPrices || (snapshotStale && hasHotelSnapshot) ? data.hotel : (data.hotelArea || 'אזורי לינה'),
      '[data-result-weather]': hasLiveWeather || (snapshotStale && hasWeatherSnapshot) ? `${data.weather} · ${data.weatherCondition || ''}` : 'מזג אוויר לפי תאריך'
    };
    Object.entries(fields).forEach(([selector, value]) => {
      const field = card.querySelector(selector);
      if (field) field.textContent = value;
    });
    replaceChildrenWithSpans(card.querySelector('[data-result-tags]'), displayTags);
    const cardLinks = {
      '[data-result-guide]': destinationPlanUrl(data.url || '/destinations/', { ...selectionHandoff, destination: data.id }),
      '[data-result-hotels]': destinationPlanUrl('/hotels/', { ...selectionHandoff, destination: data.airportCode || '', area: data.hotelArea || '', ...discoveryTripContextQuery('hotels') }),
      '[data-result-insurance]': destinationPlanUrl('/travel-insurance/', { ...selectionHandoff, trip_destination: data.id, ...discoveryTripContextQuery('insurance') })
    };
    Object.entries(cardLinks).forEach(([selector, href]) => {
      const link = card.querySelector(selector);
      if (link) {
        link.href = href;
        link.removeAttribute('aria-disabled');
        link.removeAttribute('tabindex');
      }
    });
    const saveButton = card.querySelector('.save-button');
    if (saveButton) {
      const saved = isWorkspaceItemSaved(normalizeWorkspaceItem(mapDestinationWorkspaceItem(data)).id);
      saveButton.disabled = responseState === 'pending';
      saveButton.classList.toggle('is-saved', saved);
      saveButton.setAttribute('aria-label', saved ? 'נשמר לנסיעה' : `שמירת ${data.city} לנסיעה`);
    }
  });

  const routeTitle = document.querySelector('[data-route-title]');
  if (routeTitle) routeTitle.textContent = `מתל אביב ל${data.city}: השוואת מסלולים`;
  renderHomeRouteComparison(data);
  updateHomeDestinationPlan(data, animatePlan && responseConfirmed);
  updateDestinationPlan(data, animatePlan, responseState);
  updateGlobeSelectionRail(data, { animate: animatePlan });
  syncDiscoveryTripContext();
}

function resetDestinationPlanTransientState(plan, { pointReceived = false } = {}) {
  if (!plan) return;
  plan.traVelMotionGeneration = (Number(plan.traVelMotionGeneration) || 0) + 1;
  if (plan.traVelMotionTimer) window.clearTimeout(plan.traVelMotionTimer);
  plan.traVelMotionTimer = 0;
  plan.classList.remove('is-updating');
  plan.removeAttribute('data-destination');
  plan.dataset.motionState = 'editorial';

  const stageLabels = {
    destination: pointReceived ? 'נקודה התקבלה' : 'ממתין ליעד',
    route: 'ממתין ליעד',
    stay: 'ממתין ליעד',
    experience: 'ממתין ליעד',
    cover: 'ממתין לפרטים',
    total: 'טרם חושב'
  };
  plan.querySelectorAll('[data-plan-stage]').forEach(stage => {
    const stageName = stage.dataset.planStage || '';
    stage.classList.remove('is-complete', 'is-ready', 'is-current', 'is-informed');
    stage.classList.toggle('is-pending', !(pointReceived && stageName === 'destination'));
    if (pointReceived && stageName === 'destination') {
      stage.classList.add('is-current', 'is-informed');
      stage.setAttribute('aria-current', 'step');
    } else stage.removeAttribute('aria-current');
    const detail = stage.querySelector('small');
    if (detail) detail.textContent = stageLabels[stageName] || 'ממתין';
  });

  const optionCopy = {
    '[data-plan-flight-title]': 'הדרך תיבנה לאחר זיהוי היעד',
    '[data-plan-flight-detail]': 'טיסה, עצירות, כבודה ותנאים',
    '[data-plan-stay-title]': 'אזור הלינה ייבחר לאחר זיהוי',
    '[data-plan-stay-detail]': 'אזור, מלאי, ביטול ועלות',
    '[data-plan-experience-title]': 'המסלול היומי ממתין ליעד',
    '[data-plan-experience] em': 'אוכל, תרבות, טבע וזמן חופשי',
    '[data-plan-weather-title]': 'מזג האוויר ייבדק לפי יעד ותאריך',
    '[data-plan-weather-detail]': 'לא מניחים תנאים בלי מקום ומועד',
    '[data-plan-cover-title]': 'פרטי הביטוח ייבדקו לאחר זיהוי המסלול',
    '[data-plan-cover] em': 'גילים, יעד, פעילות וכבודה',
    '[data-plan-total-title]': 'עדיין אין עלות מלאה',
    '[data-plan-total] em': 'כל רכיב יופיע רק אחרי חיפוש מתאים'
  };
  Object.entries(optionCopy).forEach(([selector, value]) => {
    const element = plan.querySelector(selector);
    if (element) element.textContent = value;
  });

  const moduleNames = {
    mobility: 'הגעה ותחבורה מקומית',
    dining: 'אוכל, כשרות והעדפות',
    entry: 'כניסה, דרכון ואשרות',
    connectivity: 'תקשורת ו-eSIM',
    accessibility: 'משפחה ונגישות',
    equipment: 'ציוד, כבודה והשכרה'
  };
  plan.querySelectorAll('[data-plan-module]').forEach(module => {
    const title = module.querySelector('[data-plan-module-title]');
    if (title) title.textContent = moduleNames[module.dataset.planModule] || 'פרט בחופשה';
  });
  const save = plan.querySelector('[data-plan-save]');
  if (save) {
    save.disabled = true;
    save.classList.remove('is-saved');
  }
}

function renderDiscoveryEmptyState({ reason = 'filters' } = {}) {
  const openEnded = reason === 'open';
  resetMapEntityExplorer(openEnded ? 'open' : 'empty');
  activePlanningSelection = null;
  activeDestination = '';
  activeRouteId = '';
  activeRouteSelectionLocked = false;
  discoverySelectedPlan = null;
  const discoveryGlobe = isMapWorkspacePage()
    ? document.querySelector('.theme-map-shell [data-globe-3d][data-discovery-globe]')
    : document.querySelector('[data-home-globe]');
  window.traVelGlobe3D?.clearSelection({ root: discoveryGlobe });
  document.querySelectorAll('.price-pin[data-destination]').forEach(item => {
    item.classList.remove('is-active');
    if (item.matches('button')) item.setAttribute('aria-pressed', 'false');
    else item.removeAttribute('aria-current');
  });
  document.querySelectorAll('[data-map-result]').forEach(card => {
    card.dataset.empty = 'true';
    card.removeAttribute('data-destination');
    const image = card.querySelector('[data-result-image]');
    if (image) image.hidden = true;
    const fields = {
      '[data-result-city]': openEnded ? 'היעד נשאר פתוח לבחירה' : 'לא נמצא יעד שתואם לבחירות',
      '[data-result-state-label]': openEnded ? 'חיפוש בלי יעד קבוע' : 'תוצאת הסינון',
      '[data-result-price]': openEnded ? 'בחרו נקודה או יעד' : 'נדרש שינוי סינון',
      '[data-result-total]': openEnded ? 'לא בחרנו במקומכם' : 'אין תוצאה',
      '[data-result-note]': openEnded ? 'היעדים והמפה מוכנים. פרטי החופשה ייפתחו אחרי הבחירה הראשונה.' : 'נסו להרחיב תקציב, גמישות או יעדים.',
      '[data-result-airport]': 'שדות תעופה',
      '[data-result-hotel]': 'אזורי לינה',
      '[data-result-weather]': 'מזג אוויר'
    };
    Object.entries(fields).forEach(([selector, value]) => {
      const field = card.querySelector(selector);
      if (field) field.textContent = value;
    });
    replaceChildrenWithSpans(card.querySelector('[data-result-tags]'), openEnded ? ['הבקשה התקבלה', 'היעד פתוח', 'אתם בשליטה'] : ['הרחיבו תקציב', 'שנו תאריכים', 'פתחו יעדים']);
    const saveButton = card.querySelector('.save-button');
    if (saveButton) {
      saveButton.disabled = true;
      saveButton.classList.remove('is-saved');
      saveButton.setAttribute('aria-label', openEnded ? 'בחרו יעד לפני שמירה' : 'אין יעד זמין לשמירה');
    }
    const fallbackLinks = {
      '[data-result-guide]': destinationPlanUrl('/destinations/'),
      '[data-result-hotels]': destinationPlanUrl('/hotels/'),
      '[data-result-insurance]': destinationPlanUrl('/travel-insurance/')
    };
    Object.entries(fallbackLinks).forEach(([selector, href]) => {
      const link = card.querySelector(selector);
      if (!link) return;
      if (openEnded) {
        link.removeAttribute('href');
        link.setAttribute('aria-disabled', 'true');
        link.tabIndex = -1;
        return;
      }
      link.href = href;
      link.removeAttribute('aria-disabled');
      link.removeAttribute('tabindex');
    });
  });
  const routeTitle = document.querySelector('[data-route-title]');
  if (routeTitle) routeTitle.textContent = openEnded ? 'המסלול ייבנה אחרי בחירת יעד' : 'אין מסלול עד שבוחרים יעד תואם';
  const homeRouteBoard = document.querySelector('[data-home-route-board]');
  const homeRouteEmpty = document.querySelector('[data-home-route-empty]');
  if (homeRouteBoard && homeRouteEmpty) {
    homeRouteBoard.hidden = true;
    homeRouteEmpty.hidden = false;
  }
  const homePlanSummary = document.querySelector('[data-home-plan-summary]');
  if (homePlanSummary) homePlanSummary.textContent = openEnded ? 'בחרו יעד כדי לפתוח את פרטי החופשה בלי להתחיל מחדש.' : 'שנו את הבחירות כדי לפתוח תוכנית חופשה ליעד מתאים.';
  document.querySelectorAll('[data-home-plan-component],[data-home-plan-ai],[data-home-plan-extras],[data-home-plan-full]').forEach(link => {
    link.setAttribute('aria-disabled', 'true');
    link.removeAttribute('href');
  });
  const homeLedgerState = document.querySelector('[data-home-plan-ledger-state]');
  if (homeLedgerState) homeLedgerState.textContent = 'בחרו יעד כדי לפתוח את כל רכיבי העלות.';
  discoveryRoutes = [];
  renderRoutes(discoveryRoutes);
  const plan = document.querySelector('[data-destination-plan]');
  if (plan) {
    resetDestinationPlanTransientState(plan);
    plan.dataset.state = 'empty';
    plan.dataset.coverageState = 'unknown';
    plan.setAttribute('aria-busy', 'false');
    plan.classList.remove('is-updating');
    const title = plan.querySelector('[data-plan-title]');
    const state = plan.querySelector('[data-plan-state]');
    const summary = plan.querySelector('[data-plan-summary]');
    if (title) title.textContent = openEnded ? 'התוכנית מחכה לבחירה הראשונה שלכם' : 'התוכנית מחכה ליעד מתאים';
    if (state) state.textContent = openEnded ? 'פרטי הנסיעה נשמרו · היעד פתוח' : 'שנו את הבחירות כדי להמשיך';
    if (summary) summary.textContent = openEnded ? 'סובבו את העולם, בחרו יעד או בקשו ממתכנן החופשה לארגן כיוון לפי הפרטים שכבר נבחרו.' : 'לא נשאיר מידע ישן מתחת למפה כאשר אין תוצאה תואמת.';
    plan.querySelectorAll('[data-plan-flight],[data-plan-stay],[data-plan-experience],[data-plan-weather],[data-plan-cover],[data-plan-total],[data-plan-guide]').forEach(link => {
      link.setAttribute('aria-disabled', 'true');
      link.tabIndex = -1;
      link.removeAttribute('href');
    });
    const ai = plan.querySelector('[data-plan-ai]');
    if (ai) ai.href = destinationPlanUrl('/ai-planner/', { mode: 'surprise', ...discoveryTripContextQuery('ai') });
    const save = plan.querySelector('[data-plan-save]');
    if (save) save.disabled = true;
    const meter = plan.querySelector('[data-plan-meter]');
    if (meter) {
      meter.setAttribute('aria-valuenow', '0');
      meter.setAttribute('aria-valuetext', openEnded ? 'פרטי הנסיעה התקבלו; אין יעד; אין הזמנה מאושרת' : '0 פרטים מוכנים; אין יעד; אין הזמנה מאושרת');
      const count = meter.querySelector('[data-plan-meter-count]');
      const fill = meter.querySelector('[data-plan-meter-fill]');
      if (count) count.textContent = 'בחרו יעד';
      if (fill) fill.style.setProperty('--plan-coverage', '0%');
    }
    const coverageCopy = plan.querySelector('[data-plan-coverage-copy]');
    if (coverageCopy) coverageCopy.textContent = openEnded ? 'פרטי הנסיעה נשמרו. 12 חלקי החופשה ייפתחו לאחר בחירת יעד.' : 'אין יעד פעיל. פרטי החופשה ייפתחו מחדש לאחר בחירה.';
    plan.querySelectorAll('[data-plan-module]').forEach(module => {
      module.dataset.state = 'unknown';
      const moduleState = module.querySelector('[data-plan-module-state]');
      const moduleDetail = module.querySelector('[data-plan-module-detail]');
      const moduleAction = module.querySelector('[data-plan-module-action]');
      if (moduleState) moduleState.textContent = 'ממתין ליעד';
      if (moduleDetail) moduleDetail.textContent = 'לא נשאיר כאן פרטים מהיעד הקודם. בחרו יעד או פתחו את מתכנן החופשה.';
      if (moduleAction) {
        moduleAction.href = destinationPlanUrl('/ai-planner/', { mode: 'surprise', scope: module.dataset.planModule || '', ...discoveryTripContextQuery('ai') });
        moduleAction.removeAttribute('aria-disabled');
      }
    });
    const ledgerList = plan.querySelector('[data-plan-ledger-list]');
    const ledgerTotal = plan.querySelector('[data-plan-ledger-total]');
    const ledgerState = plan.querySelector('[data-plan-ledger-state]');
    const ledgerTruth = plan.querySelector('[data-plan-ledger-truth]');
    const costScope = ['flight', 'baggage', 'stay', 'taxes', 'transfers', 'local_transport', 'activities', 'dining', 'insurance', 'connectivity', 'equipment', 'entry'];
    if (ledgerList) ledgerList.replaceChildren(...costScope.map(id => {
      const row = document.createElement('span');
      row.dataset.state = 'needs_search';
      appendTextElement(row, 'b', selectedPlanCostLabels[id]);
      appendTextElement(row, 'em', 'ממתין ליעד ולחיפוש');
      return row;
    }));
    if (ledgerTotal) ledgerTotal.textContent = 'עדיין אין יעד';
    if (ledgerState) ledgerState.textContent = '12 רכיבי עלות ממתינים';
    if (ledgerTruth) ledgerTruth.textContent = 'אין מחיר, חיסכון או הזמנה עד לבחירת יעד ובדיקה מול מקור מתאים.';
    const planTruth = plan.querySelector('[data-plan-truth]');
    if (planTruth) planTruth.textContent = openEnded ? 'פרטי הנסיעה התקבלו. עדיין אין יעד, מחיר, זמינות או הזמנה.' : 'אין מידע ישן, מחיר או הזמנה פעילה. בחרו יעד כדי להתחיל מחדש.';
  }
  const rail = document.querySelector('[data-globe-selection]');
  if (rail) {
    rail.dataset.state = openEnded ? 'open' : 'empty';
    const kicker = rail.querySelector('[data-globe-selection-kicker]');
    const title = rail.querySelector('[data-globe-selection-title]');
    const detail = rail.querySelector('[data-globe-selection-detail]');
    const action = rail.querySelector('[data-globe-selection-action]');
    if (kicker) kicker.textContent = openEnded ? 'הבקשה התקבלה' : 'אין תוצאה פעילה';
    if (title) title.textContent = openEnded ? 'לא בחרנו יעד במקומכם' : 'אין כרגע יעד שתואם לבחירות';
    if (detail) detail.textContent = openEnded ? 'בחרו נקודה או פתחו את מתכנן החופשה.' : 'שנו תקציב, גמישות או יעד כדי להמשיך.';
    if (action) {
      action.href = destinationPlanUrl('/ai-planner/', { mode: 'surprise', ...discoveryTripContextQuery('ai') });
      action.firstChild.textContent = 'תפתיעו אותי';
    }
  }
  setMapProgressState({
    point: 'waiting',
    destination: 'waiting',
    scopes: 'waiting',
    live: 'waiting',
    destinationDetail: openEnded ? 'ממתינים לבחירה שלכם' : 'אין יעד שתואם לבחירות',
    liveDetail: openEnded ? 'מחיר וזמינות ייבדקו לאחר בחירת יעד' : 'שנו את הסינון כדי להתחיל מחדש'
  });
}

function destinationPlanUrl(path, params = {}) {
  const url = new URL(path, window.location.origin);
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== undefined && value !== null) url.searchParams.set(key, String(value));
  });
  return url.toString();
}

const selectedPlanModuleStates = new Set(['live', 'stale', 'editorial', 'needs_details', 'needs_search', 'unknown', 'unavailable']);
const selectedPlanModuleLabels = {
  live: 'מידע עדכני התקבל',
  stale: 'המידע האחרון · בדקו שוב',
  editorial: 'בסיס תכנוני מוכן',
  needs_details: 'ממתין להעדפות',
  needs_search: 'נדרשת בדיקה מול ספק',
  unknown: 'נדרש מידע נוסף',
  unavailable: 'אין עדיין כיסוי מובנה'
};
const selectedPlanCostLabels = {
  flight: 'טיסה',
  baggage: 'כבודה',
  hotel: 'לינה',
  stay: 'לינה',
  taxes: 'מסים ועמלות',
  transfers: 'העברות',
  local_transport: 'תחבורה מקומית',
  activities: 'פעילויות וכרטיסים',
  dining: 'אוכל',
  insurance: 'ביטוח',
  connectivity: 'תקשורת ו-eSIM',
  equipment: 'ציוד והשכרה',
  entry: 'כניסה ואשרות',
  overnight: 'לילה בדרך'
};

function safeSelectedPlanModule(module = {}) {
  return {
    id: typeof module.id === 'string' ? module.id.replace(/[^a-z0-9_-]/g, '').slice(0, 40) : '',
    state: selectedPlanModuleStates.has(module.state) ? module.state : 'unknown',
    headline: typeof module.headline === 'string' ? module.headline.slice(0, 220) : '',
    detail: typeof module.detail === 'string' ? module.detail.slice(0, 520) : '',
    next_action: typeof module.next_action === 'string' ? module.next_action.slice(0, 120) : '',
    provenance: {
      source: typeof module?.provenance?.source === 'string' ? module.provenance.source.slice(0, 160) : '',
      observed_at: typeof module?.provenance?.observed_at === 'string' ? module.provenance.observed_at.slice(0, 40) : '',
      retrieved_at: typeof module?.provenance?.retrieved_at === 'string' ? module.provenance.retrieved_at.slice(0, 40) : ''
    }
  };
}

function normalizeSelectedPlan(plan, data) {
  if (!plan || plan.destination_id !== data.id || !Array.isArray(plan.modules)) return null;
  const modules = plan.modules.map(safeSelectedPlanModule).filter(module => module.id);
  const lineItems = Array.isArray(plan?.cost_ledger?.line_items)
    ? plan.cost_ledger.line_items.slice(0, 24).map(item => ({
      id: typeof item.id === 'string' ? item.id.replace(/[^a-z0-9_-]/g, '').slice(0, 40) : '',
      state: ['live', 'stale'].includes(item.state) ? item.state : 'needs_search',
      amount: Number.isFinite(Number(item.amount)) ? Number(item.amount) : null,
      formatted: typeof item.formatted === 'string' ? item.formatted.slice(0, 60) : '',
      source: typeof item.source === 'string' ? item.source.slice(0, 80) : '',
      observed_at: typeof item.observed_at === 'string' ? item.observed_at.slice(0, 40) : '',
      retrieved_at: typeof item.retrieved_at === 'string' ? item.retrieved_at.slice(0, 40) : ''
    })).filter(item => item.id)
    : [];
  const moduleCount = Math.max(modules.length, Number(plan?.coverage?.module_count) || 0);
  const mappedCount = Math.min(moduleCount, Math.max(0, Number(plan?.coverage?.mapped_count) || 0));
  return {
    destination_id: data.id,
    state: ['mixed', 'stale'].includes(plan.state) ? plan.state : 'editorial',
    freshness: ['current', 'refreshing', 'stale', 'fallback'].includes(plan.freshness) ? plan.freshness : 'fallback',
    selection: {
      latitude: Number(plan?.selection?.latitude),
      longitude: Number(plan?.selection?.longitude),
      granularity: plan?.selection?.granularity === 'city' ? 'city' : 'area'
    },
    coverage: { mapped_count: mappedCount, module_count: moduleCount },
    modules,
    cost_ledger: {
      state: ['complete_live', 'partial_live', 'stale_complete', 'stale_partial'].includes(plan?.cost_ledger?.state) ? plan.cost_ledger.state : 'needs_search',
      freshness: ['current', 'refreshing', 'stale', 'fallback'].includes(plan?.cost_ledger?.freshness) ? plan.cost_ledger.freshness : 'fallback',
      currency: typeof plan?.cost_ledger?.currency === 'string' ? plan.cost_ledger.currency.toUpperCase().slice(0, 3) : 'USD',
      route_id: typeof plan?.cost_ledger?.route_id === 'string' ? plan.cost_ledger.route_id.replace(/[^a-z0-9_-]/g, '').slice(0, 80) : '',
      scope: typeof plan?.cost_ledger?.scope === 'string' ? plan.cost_ledger.scope.replace(/[^a-z0-9_-]/g, '').slice(0, 80) : '',
      source: typeof plan?.cost_ledger?.source === 'string' ? plan.cost_ledger.source.slice(0, 80) : '',
      observed_at: typeof plan?.cost_ledger?.observed_at === 'string' ? plan.cost_ledger.observed_at.slice(0, 40) : '',
      retrieved_at: typeof plan?.cost_ledger?.retrieved_at === 'string' ? plan.cost_ledger.retrieved_at.slice(0, 40) : '',
      booking_confirmed: plan?.cost_ledger?.booking_confirmed === true,
      line_items: lineItems,
      total: Number.isFinite(Number(plan?.cost_ledger?.total?.amount)) ? {
        amount: Number(plan.cost_ledger.total.amount),
        formatted: typeof plan.cost_ledger.total.formatted === 'string' ? plan.cost_ledger.total.formatted.slice(0, 60) : ''
      } : null,
      savings: null
    }
  };
}

function localPlanningModule(data, id, fallback) {
  const profile = data?.planning?.modules?.[id];
  return safeSelectedPlanModule({
    id,
    state: profile?.state || fallback.state,
    headline: profile?.headline || fallback.headline,
    detail: profile?.detail || fallback.detail,
    next_action: profile?.nextAction || fallback.next_action,
    provenance: {
      source: data?.planning?.sourceLabel || 'פרופיל התכנון של Tra-Vel',
      observed_at: data?.planning?.reviewedOn || ''
    }
  });
}

function buildLocalSelectedPlan(data) {
  const destinationRoutes = discoveryRoutes.filter(route => !route.destination_id || route.destination_id === data.id);
  const snapshotCurrent = discoverySnapshotIsCurrent();
  const snapshotStale = discoverySnapshotIsStale();
  const currentRouteData = snapshotCurrent && discoveryLiveLayers.airports;
  const currentHotelData = snapshotCurrent && discoveryLiveLayers.hotels;
  const currentWeatherData = snapshotCurrent && discoveryLiveLayers.weather;
  const routeState = discoveryLiveLayers.airports
    ? (snapshotCurrent ? 'live' : 'stale')
    : (destinationRoutes.length ? 'editorial' : 'unavailable');
  const editorialSource = data?.planning?.sourceLabel || 'פרופיל התכנון של Tra-Vel';
  const reviewedOn = data?.planning?.reviewedOn || '';
  const module = (id, state, headline, detail, nextAction) => safeSelectedPlanModule({
    id, state, headline, detail, next_action: nextAction,
    provenance: { source: editorialSource, observed_at: reviewedOn }
  });
  const modules = [
    module('route', routeState, destinationRoutes.length ? `${destinationRoutes.length} דרכים ל${data.city} מוכנות להשוואה` : `נדרשת בדיקת דרך ל${data.city}`, currentRouteData ? 'מבנה הדרך והזמנים התקבלו מהספק. המחיר מוצג רק לפריטים שהספק תמחר.' : 'השוו מסלולים, זמני דרך ותקציבי תכנון. המחיר, הזמינות והתנאים מאומתים לפני התשלום.', 'בחרו דרך מועדפת'),
    module('stay', discoveryLiveLayers.hotels ? (snapshotCurrent ? 'live' : 'stale') : 'editorial', data.hotelArea ? `מתחילים באזור ${data.hotelArea}` : 'בוחרים אזור לפני מלון', currentHotelData ? 'מחיר החדר וזהות המלון התקבלו. מסים, מלאי וביטול עדיין דורשים הצעה מתוארכת.' : 'השוו אזורים, סגנונות לינה ותקציבי תכנון. המחיר, הזמינות והתנאים מאומתים לפני התשלום.', 'השוו אזורים ומלונות'),
    localPlanningModule(data, 'mobility', { state: 'editorial', headline: 'מחברים שדה, מלון ותחבורה מקומית', detail: 'סוג ההעברה והמחיר ייבדקו לפי שעת הנחיתה, כתובת המלון והרכב הנוסעים.', next_action: 'הוסיפו פרטי העברה' }),
    module('activities', 'editorial', `בונים קצב ופעילויות ל${data.city}`, 'מחברים עוגנים, זמן חופשי, מרחקים וכרטיסים. זמינות ומחיר דורשים תאריך.', 'הוסיפו העדפות לפעילויות'),
    localPlanningModule(data, 'dining', { state: 'needs_details', headline: 'אוכל וכשרות לפי ההעדפות שלכם', detail: 'מוסיפים כשרות, אלרגיות, ילדים ותקציב לפני בניית מסלול אוכל.', next_action: 'הוסיפו העדפות אוכל' }),
    module('weather', discoveryLiveLayers.weather ? (snapshotCurrent ? 'live' : 'stale') : 'editorial', currentWeatherData ? `${data.weather} עכשיו; התחזית תותאם לתאריך` : 'מזג האוויר ייבדק לפי מועד הנסיעה', currentWeatherData ? 'התנאים הנוכחיים התקבלו. עונה וציוד עדיין יותאמו לתאריכים.' : 'פרופיל עונתי עוזר לתכנון, אך תחזית אינה מוצגת בלי מועד.', 'הוסיפו תאריך לבדיקה'),
    localPlanningModule(data, 'entry', { state: 'needs_details', headline: 'כניסה, דרכון ואשרות', detail: 'אזרחות, תוקף דרכון, מסלול ותאריך נבדקים מול מקור רשמי לפני רכישה.', next_action: 'הוסיפו אזרחות ותאריך' }),
    localPlanningModule(data, 'connectivity', { state: 'editorial', headline: 'eSIM, נדידה ו-SIM מקומי', detail: 'משווים חיבור לפי ימים, נפח, כיסוי ושיתוף אינטרנט.', next_action: 'הוסיפו צורכי תקשורת' }),
    localPlanningModule(data, 'accessibility', { state: 'needs_details', headline: 'משפחה ונגישות', detail: 'גילים, עגלה, הליכה, מעלית וסיוע משנים את המלון ואת קצב היום.', next_action: 'הוסיפו צורכי נגישות' }),
    module('insurance', 'needs_details', 'פרטים לבדיקת ביטוח', 'גילים, יעד, פעילויות, כבודה וביטול הם מידע שכדאי למסור לגורם מורשה. אין כאן המלצה או פוליסה.', 'הכינו פרטים לבדיקת כיסוי'),
    localPlanningModule(data, 'equipment', { state: 'needs_details', headline: 'ציוד, כבודה והשכרה', detail: 'מחברים פעילויות, עונה ותנאי כבודה לפני שמחליטים מה לארוז ומה לשכור.', next_action: 'הוסיפו צורכי ציוד' }),
    module('total', 'needs_search', 'פירוט עלויות לכל החופשה', 'תקציב התכנון מחבר טיסה, לינה, העברות ותוספות. המחיר, הזמינות והתנאים מאומתים לפני התשלום.', 'בקשו בדיקה אישית')
  ];
  const costCategories = data?.planning?.costCategories?.length
    ? data.planning.costCategories
    : ['flight', 'baggage', 'stay', 'taxes', 'transfers', 'local_transport', 'activities', 'dining', 'insurance', 'connectivity', 'equipment', 'entry'];
  return {
    destination_id: data.id,
    state: snapshotStale && Object.values(discoveryLiveLayers).some(Boolean) ? 'stale' : (Object.values(discoveryLiveLayers).some(Boolean) ? 'mixed' : 'editorial'),
    freshness: discoveryFreshness,
    selection: { latitude: data.latitude, longitude: data.longitude, granularity: 'city' },
    coverage: { mapped_count: modules.filter(item => !['unknown', 'unavailable'].includes(item.state)).length, module_count: modules.length },
    modules,
    cost_ledger: {
      state: 'needs_search',
      freshness: discoveryFreshness,
      currency: data.currency || 'USD',
      line_items: costCategories.map(id => ({ id, state: 'needs_search', amount: null, formatted: '' })),
      total: null,
      savings: null
    }
  };
}

function selectedPlanForDestination(data) {
  return normalizeSelectedPlan(discoverySelectedPlan, data) || buildLocalSelectedPlan(data);
}

function selectedPlanForResponse(data, responseState) {
  const selectedPlan = selectedPlanForDestination(data);
  if (responseState === 'current') return selectedPlan;
  if (responseState === 'stale') {
    return {
      ...selectedPlan,
      state: 'stale',
      freshness: 'stale',
      modules: selectedPlan.modules.map(module => module.state === 'live' ? { ...module, state: 'stale' } : module)
    };
  }
  return {
    ...selectedPlan,
    state: 'editorial',
    freshness: responseState === 'fallback' ? 'fallback' : 'refreshing',
    modules: selectedPlan.modules.map(module => ['live', 'stale'].includes(module.state) ? {
      ...module,
      state: 'needs_search',
      detail: 'מעדכנים את נתוני הספק לבחירה החדשה. הפרטים הקודמים אינם מוצגים כתוצאה נוכחית.',
      provenance: {}
    } : module),
    cost_ledger: {
      ...selectedPlan.cost_ledger,
      state: 'needs_search',
      freshness: responseState === 'fallback' ? 'fallback' : 'refreshing',
      booking_confirmed: false,
      line_items: selectedPlan.cost_ledger.line_items.map(item => ({ ...item, state: 'needs_search', amount: null, formatted: '', source: '', observed_at: '', retrieved_at: '' })),
      total: null,
      savings: null
    }
  };
}

function selectedPlanActionUrl(moduleId, data) {
  const scopeByModule = {
    mobility: 'transfers',
    dining: 'dining',
    entry: 'entry',
    connectivity: 'connectivity',
    accessibility: 'accessibility',
    equipment: 'equipment'
  };
  return destinationPlanUrl('/ai-planner/', {
    destination: data.id,
    intent: activePlanIntent,
    scope: scopeByModule[moduleId] || moduleId,
    route: activeRouteId
  });
}

function activePlanCostLedger(selectedPlan) {
  const ledger = selectedPlan.cost_ledger || { state: 'needs_search', currency: 'USD', line_items: [], total: null, savings: null };
  if (!activeRouteId || !ledger.route_id || activeRouteId === ledger.route_id) return ledger;
  return {
    ...ledger,
    state: 'needs_search',
    route_id: activeRouteId,
    line_items: ledger.line_items.map(item => ({ ...item, state: 'needs_search', amount: null, formatted: '' })),
    total: null,
    savings: null,
    booking_confirmed: false
  };
}

function renderDestinationDecisionCockpit(plan, data, responseState = 'pending') {
  const selectedPlan = selectedPlanForResponse(data, responseState);
  const moduleMap = new Map(selectedPlan.modules.map(module => [module.id, module]));
  const meter = plan.querySelector('[data-plan-meter]');
  const moduleCount = Math.max(1, selectedPlan.coverage.module_count || selectedPlan.modules.length || 12);
  const mappedCount = Math.min(moduleCount, Math.max(0, selectedPlan.coverage.mapped_count || 0));
  const verifiedCount = Math.min(moduleCount, selectedPlan.modules.filter(module => module.state === 'live').length);
  const needsInputCount = selectedPlan.modules.filter(module => ['stale', 'needs_details', 'needs_search', 'unknown', 'unavailable'].includes(module.state)).length;
  const coverage = Math.round((verifiedCount / moduleCount) * 100);
  if (meter) {
    meter.setAttribute('aria-valuemax', String(moduleCount));
    meter.setAttribute('aria-valuenow', String(verifiedCount));
    meter.setAttribute('aria-valuetext', `${mappedCount} פרטים מוכנים; ${verifiedCount} נבדקו במידע עדכני; ${needsInputCount} דורשים פרטים או בדיקה; אין הזמנה מאושרת`);
    const count = meter.querySelector('[data-plan-meter-count]');
    const fill = meter.querySelector('[data-plan-meter-fill]');
    const label = meter.querySelector('[data-plan-meter-label]');
    if (count) count.textContent = `${verifiedCount}/${moduleCount}`;
    if (fill) fill.style.setProperty('--plan-coverage', `${coverage}%`);
    if (label) label.textContent = 'פרטים נבדקו';
  }
  const coverageCopy = plan.querySelector('[data-plan-coverage-copy]');
  if (coverageCopy) coverageCopy.textContent = selectedPlan.state === 'stale'
    ? `${mappedCount} מתוך ${moduleCount} פרטי חופשה מוכנים ל${data.city}. מידע קודם מסומן ודורש רענון.`
    : `${mappedCount} מתוך ${moduleCount} פרטי חופשה מוכנים ל${data.city}. זו תוכנית, לא אישור הזמנה.`;

  plan.querySelectorAll('[data-plan-module]').forEach(details => {
    const module = moduleMap.get(details.dataset.planModule) || safeSelectedPlanModule({ id: details.dataset.planModule });
    details.dataset.state = module.state;
    const title = details.querySelector('[data-plan-module-title]');
    const state = details.querySelector('[data-plan-module-state]');
    const detail = details.querySelector('[data-plan-module-detail]');
    const action = details.querySelector('[data-plan-module-action]');
    if (title && module.headline) title.textContent = module.headline;
    if (state) state.textContent = selectedPlanModuleLabels[module.state] || selectedPlanModuleLabels.unknown;
    if (detail && module.detail) detail.textContent = module.detail;
    if (action) {
      action.href = selectedPlanActionUrl(module.id, data);
      action.firstChild.textContent = module.next_action || 'הוסיפו פרטים';
    }
  });

  const ledger = activePlanCostLedger(selectedPlan);
  const ledgerList = plan.querySelector('[data-plan-ledger-list]');
  const ledgerTotal = plan.querySelector('[data-plan-ledger-total]');
  const ledgerState = plan.querySelector('[data-plan-ledger-state]');
  const ledgerTruth = plan.querySelector('[data-plan-ledger-truth]');
  if (ledgerList) {
    ledgerList.replaceChildren(...ledger.line_items.map(item => {
      const row = document.createElement('span');
      appendTextElement(row, 'b', selectedPlanCostLabels[item.id] || item.id.replaceAll('_', ' '));
      const amountLabel = item.state === 'live' && item.formatted
        ? item.formatted
        : (item.state === 'stale' && item.formatted ? `${item.formatted} · המחיר האחרון שנבדק` : 'ייבדק לפי הפרטים');
      appendTextElement(row, 'em', amountLabel);
      row.dataset.state = item.state;
      return row;
    }));
  }
  if (ledgerTotal) ledgerTotal.textContent = ledger.total?.formatted
    ? (['stale_complete', 'stale_partial'].includes(ledger.state) ? `${ledger.total.formatted} · הסכום האחרון שנבדק` : ledger.total.formatted)
    : 'נדרשת בדיקת מחיר';
  if (ledgerState) {
    const liveCosts = ledger.line_items.filter(item => item.state === 'live').length;
    const staleCosts = ledger.line_items.filter(item => item.state === 'stale').length;
    ledgerState.textContent = staleCosts
      ? `${ledger.line_items.length} רכיבי עלות · ${staleCosts} מחירים קודמים שצריך לבדוק שוב`
      : `${ledger.line_items.length} רכיבי עלות במעקב · ${liveCosts} התקבלו מספק`;
  }
  if (ledgerTruth) {
    ledgerTruth.textContent = ['stale_complete', 'stale_partial'].includes(ledger.state)
      ? `הסכומים הם האחרונים שנבדקו: ${discoveryFreshnessLabel()}. הם אינם אישור מחיר, חיסכון או זמינות.`
      : (ledger.savings?.comparable_verified
      ? `הפער המאומת מול חלופה ברת-השוואה הוא ${ledger.savings.formatted}. המחיר עדיין כפוף לזמינות עד אישור.`
      : 'לא מוצג חיסכון עד שיש מחיר בסיס בר-השוואה, זמינות ומועד בדיקה.');
  }
  plan.dataset.coverageState = selectedPlan.state;
  return selectedPlan;
}

const mapProgressStates = new Set(['confirmed', 'running', 'waiting', 'stale', 'failed']);
const mapProgressLabels = { point: 'נקודה', destination: 'יעד', scopes: 'מוצרי החופשה', live: 'מחיר וזמינות' };

function announceMapProgress(message) {
  const liveRegion = document.querySelector('[data-map-progress-live]');
  if (!liveRegion || !message || liveRegion.textContent === message) return;
  liveRegion.textContent = message;
}

function setMapProgressCheckpoint(name, state, detail = '', { announce = true } = {}) {
  const checkpoint = document.querySelector(`[data-map-checkpoint="${name}"]`);
  if (!checkpoint) return;
  const normalizedState = mapProgressStates.has(state) ? state : 'waiting';
  const previousState = checkpoint.dataset.state || '';
  const detailElement = checkpoint.querySelector('[data-map-checkpoint-detail]');
  const previousDetail = detailElement?.textContent || '';
  const changed = previousState !== normalizedState || (detail && detail !== previousDetail);
  checkpoint.dataset.state = normalizedState;
  if (detail && detailElement) detailElement.textContent = detail;
  checkpoint.classList.remove('is-new');
  if (normalizedState === 'confirmed' && previousState !== 'confirmed' && !prefersReducedMotion()) {
    void checkpoint.offsetWidth;
    checkpoint.classList.add('is-new');
    window.clearTimeout(checkpoint.traVelMotionTimer);
    checkpoint.traVelMotionTimer = window.setTimeout(() => checkpoint.classList.remove('is-new'), 760);
  }
  if (announce && changed) announceMapProgress(`${mapProgressLabels[name] || name}: ${detail || previousDetail}`);
}

function setMapProgressState({ point = 'confirmed', destination = 'confirmed', scopes = 'confirmed', live = 'waiting', destinationDetail = '', liveDetail = '', announce = true } = {}) {
  const states = { point, destination, scopes, live };
  setMapProgressCheckpoint('point', point, point === 'confirmed' ? 'נבחרה על הגלובוס' : 'בחרו נקודת התחלה', { announce: false });
  setMapProgressCheckpoint('destination', destination, destinationDetail || (destination === 'confirmed' ? 'היעד זוהה' : 'אפשר לזהות את האזור'), { announce: false });
  setMapProgressCheckpoint('scopes', scopes, scopes === 'confirmed' ? 'מוכנים להתאמה' : 'ייפתחו אחרי בחירה', { announce: false });
  setMapProgressCheckpoint('live', live, liveDetail || (live === 'confirmed' ? 'מידע עדכני התקבל' : 'ייבדקו לפי תאריכים ונוסעים'), { announce: false });
  const confirmedCount = Object.values(states).filter(state => state === 'confirmed').length;
  const currentDetail = liveDetail || destinationDetail || (confirmedCount ? 'הבחירה נשמרה. אפשר לערוך כל חלק; מחיר וזמינות ייבדקו לפני רכישה.' : 'בחרו נקודת התחלה');
  if (announce) announceMapProgress(`התקדמות: ${confirmedCount}/4. ${currentDetail}`);
}

function updateGlobeSelectionRail(data, options = {}) {
  const rail = document.querySelector('[data-globe-selection]');
  if (!rail) return;
  rail.classList.remove('is-updating');
  if (rail.traVelMotionTimer) window.clearTimeout(rail.traVelMotionTimer);
  if (options.animate !== false && !prefersReducedMotion()) {
    void rail.offsetWidth;
    rail.classList.add('is-updating');
    rail.traVelMotionTimer = window.setTimeout(() => rail.classList.remove('is-updating'), 900);
  }
  const mode = options.mode === 'unsupported' ? 'unsupported' : 'supported';
  rail.dataset.state = mode;
  const kicker = rail.querySelector('[data-globe-selection-kicker]');
  const title = rail.querySelector('[data-globe-selection-title]');
  const detail = rail.querySelector('[data-globe-selection-detail]');
  const action = rail.querySelector('[data-globe-selection-action]');
  if (mode === 'unsupported') {
    const latitudeValue = Number(options.latitude);
    const longitudeValue = Number(options.longitude);
    const latitude = latitudeValue.toFixed(2);
    const longitude = longitudeValue.toFixed(2);
    if (kicker) kicker.textContent = 'האזור שבחרתם';
    if (title) title.textContent = 'בואו נהפוך את הנקודה לחופשה';
    if (detail) detail.textContent = `הנקודה נשמרה (${latitude}°, ${longitude}°). מתכנן החופשה יכול לזהות את האזור ולפתוח טיסות, לינה וכל שאר החלקים.`;
    if (action) {
      action.href = destinationPlanUrl('/ai-planner/', {
        selection_id: options.selectionId || '',
        selection_kind: 'map_point',
        latitude: latitudeValue.toFixed(4),
        longitude: longitudeValue.toFixed(4),
        mode: 'map_point',
        intent: activePlanIntent,
        ...discoveryTripContextQuery('ai'),
        scope: fullTripPlanningScope
      });
      action.firstChild.textContent = 'זהו את האזור ובנו חופשה';
    }
    setMapProgressState({ destination: 'waiting', scopes: 'confirmed', live: 'waiting', destinationDetail: 'מוכן לזיהוי מדויק', liveDetail: 'מחיר וזמינות ייבדקו אחרי בחירת היעד והפרטים' });
    return;
  }
  const selectedPlan = selectedPlanForDestination(data);
  const distanceKm = Number(options.distanceKm);
  const isNearbyPoint = Number.isFinite(distanceKm) && distanceKm > 20;
  if (kicker) kicker.textContent = 'הנקודה התקבלה';
  if (title) title.textContent = isNearbyPoint ? `${data.city} היא היעד הקרוב` : `${data.city} נבחרה`;
  if (detail) detail.textContent = options.awaiting
    ? 'הבחירה נשמרה. ממתינים לעדכון.'
    : (isNearbyPoint
      ? `הנקודה במרחק כ-${Math.round(distanceKm)} ק״מ. בדקו התאמה לפני המשך.`
      : `${selectedPlan.coverage.mapped_count} פרטים מוכנים. מחיר וזמינות טרם נבדקו.`);
  if (action) {
    action.href = '#destination-plan-title';
    action.firstChild.textContent = 'צפו בתוכנית';
  }
  const anyCurrentLiveData = discoveryCommercialDataIsCurrent();
  const liveState = options.awaiting
    ? 'running'
    : (anyCurrentLiveData ? 'confirmed' : (discoverySnapshotIsStale() ? 'stale' : 'waiting'));
  setMapProgressState({
    destinationDetail: data.city,
    live: liveState,
    liveDetail: options.awaiting ? 'ממתינים לעדכון' : (anyCurrentLiveData ? 'מידע עדכני התקבל' : (discoverySnapshotIsStale() ? 'נדרש רענון' : 'נדרשת בדיקת מחיר'))
  });
}

function revealGlobeSelection(inputType = 'pointer') {
  // A dive reveal is owned by the dive store panel, which scrolls itself into
  // view as the direct result of the gesture; the rail must not compete.
  if (inputType === 'dive') return;
  const rail = document.querySelector('[data-globe-selection]');
  if (!rail) return;
  if (inputType === 'keyboard') rail.focus({ preventScroll: true });
  if (inputType === 'keyboard' || window.matchMedia('(max-width: 760px)').matches) {
    rail.scrollIntoView({ behavior: preferredScrollBehavior(), block: 'nearest' });
  }
}

function setPointPlanningAction(link, pointContext, scope) {
  if (!link) return '';
  link.href = destinationPlanUrl('/ai-planner/', { ...pointContext, scope });
  link.removeAttribute('aria-disabled');
  link.removeAttribute('tabindex');
  return link.href;
}

function setExplorationHubAction(link, path, hub, pointContext, scope, extra = {}) {
  if (!link) return '';
  const destination = hub.iataSearchCode || hub.id;
  link.href = destinationPlanUrl(path, {
    ...pointContext,
    q: `${hub.city}, ${hub.country}`,
    scope,
    destination,
    ...extra
  });
  link.dataset.requiresLiveSearch = 'true';
  link.removeAttribute('aria-disabled');
  link.removeAttribute('tabindex');
  return link.href;
}

function renderExplorationHubSelection(detail = {}, globeRoot = null) {
  const mapShell = globeRoot?.closest?.('.theme-map-shell');
  if (!mapShell) return false;
  const hubId = String(detail.hubId || '').replace(/[^a-z0-9-]/g, '').slice(0, 60);
  const hub = explorationHubData[hubId];
  const latitude = Number(detail.latitude);
  const longitude = Number(detail.longitude);
  if (!hub || !Number.isFinite(latitude) || latitude < -90 || latitude > 90
    || !Number.isFinite(longitude) || longitude < -180 || longitude > 180
    || explorationHubDistanceKm({ latitude, longitude }, hub) > hub.radiusKm) {
    renderUnsupportedGlobeSelection(detail, globeRoot);
    return false;
  }

  renderUnsupportedGlobeSelection({ ...detail, supported: false }, globeRoot);
  window.traVelGlobe3D?.focusHub(hub.id, {
    root: globeRoot,
    animate: false,
    announce: false
  });
  globeRoot.querySelectorAll('[data-exploration-hub]').forEach(marker => {
    const active = marker.dataset.explorationHub === hub.id;
    marker.classList.toggle('is-active', active);
    marker.setAttribute('aria-pressed', String(active));
  });
  const selectionId = activePlanningSelection?.selection_id || createPlanningSelectionId('hub');
  const pointContext = {
    selection_id: selectionId,
    selection_kind: 'map_point',
    latitude: latitude.toFixed(4),
    longitude: longitude.toFixed(4),
    mode: 'map_point',
    intent: activePlanIntent,
    ...discoveryTripContextQuery('ai')
  };
  const placeLabel = `${hub.city}, ${hub.country}`;
  const searchTarget = hub.iataSearchCode ? `${hub.iataSearchCode} · ${hub.city}` : hub.city;

  syncMapDestinationLinks('');
  mapShell.querySelectorAll('[data-map-result]').forEach(card => {
    card.dataset.empty = 'false';
    card.dataset.explorationHub = hub.id;
    const fields = {
      '[data-result-city]': hub.city,
      '[data-result-state-label]': hub.country,
      '[data-result-price]': 'פותחים חיפוש לפי הנוסעים והתאריכים',
      '[data-result-total]': 'מחיר וזמינות יתקבלו לאחר בדיקה',
      '[data-result-note]': 'בחרתם מקום מוכר על המפה. עכשיו אפשר לבדוק כל חלק בחופשה בלי להציג מחיר, ספק או זמינות שלא אומתו.',
      '[data-result-airport]': `דרכי הגעה אל ${searchTarget}`,
      '[data-result-hotel]': `לינה ב${hub.city} לפי אזור והרכב`,
      '[data-result-weather]': 'עונה ומזג אוויר ייבדקו לפי מועד הנסיעה'
    };
    Object.entries(fields).forEach(([selector, value]) => {
      const element = card.querySelector(selector);
      if (element) element.textContent = value;
    });
    replaceChildrenWithSpans(card.querySelector('[data-result-tags]'), ['טיסות לפי תאריכים', 'לינה לפי הרכב', 'כל החופשה ניתנת לעריכה']);
    const guide = card.querySelector('[data-result-guide]');
    const hotels = card.querySelector('[data-result-hotels]');
    const insurance = card.querySelector('[data-result-insurance]');
    setExplorationHubAction(guide, '/ai-planner/', hub, pointContext, fullTripPlanningScope, { mode: 'destination' });
    setExplorationHubAction(hotels, '/hotels/', hub, pointContext, 'accommodation');
    setExplorationHubAction(insurance, '/travel-insurance/', hub, pointContext, 'insurance', { trip_destination: hub.id });
    if (guide) guide.lastChild.textContent = `בנו חופשה ל${hub.city}`;
    if (hotels) hotels.lastChild.textContent = `חפשו לינה ב${hub.city}`;
    if (insurance) insurance.lastChild.textContent = 'בדקו ביטוח לפי המסלול';
  });

  const plan = mapShell.querySelector('[data-destination-plan]');
  if (plan) {
    plan.dataset.state = 'exploration-hub';
    plan.dataset.explorationHub = hub.id;
    plan.dataset.coverageState = 'needs_search';
    const fields = {
      '[data-plan-title]': `החופשה שלכם ל${hub.city}, במקום אחד`,
      '[data-plan-state]': 'המקום זוהה · כל האפשרויות מוכנות לחיפוש לפי הפרטים שלכם',
      '[data-plan-summary]': `פתחו טיסות, לינה, פעילויות, ביטוח, תקשורת וציוד ל${placeLabel}. התוצאות יוצגו רק לאחר חיפוש לפי תאריכים ונוסעים.`,
      '[data-plan-flight-title]': `חפשו דרכי הגעה אל ${searchTarget}`,
      '[data-plan-flight-detail]': 'זמן, עצירות, כבודה ותנאים לפי תאריך והרכב',
      '[data-plan-stay-title]': `חפשו אזור ולינה ב${hub.city}`,
      '[data-plan-stay-detail]': 'מלון, דירה או אירוח לפי הרכב, מיקום ותנאים',
      '[data-plan-experience-title]': `בנו ימים ופעילויות ב${hub.city}`,
      '[data-plan-weather-title]': 'בדקו עונה, מזג אוויר וציוד לפי המועד',
      '[data-plan-cover-title]': 'התאימו ביטוח לנוסעים ולפעילויות',
      '[data-plan-total-title]': 'חברו את כל מרכיבי החופשה לאחר בדיקת האפשרויות',
      '[data-plan-truth]': 'המקום זוהה גאוגרפית. מחיר, זמינות, לוחות זמנים, ספקים ותנאים יוצגו רק מתוצאות עדכניות התואמות לתאריכים ולנוסעים.'
    };
    Object.entries(fields).forEach(([selector, value]) => {
      const element = plan.querySelector(selector);
      if (element) element.textContent = value;
    });
    const actions = [
      ['[data-plan-flight]', '/flights/', 'flights', {}],
      ['[data-plan-stay]', '/hotels/', 'accommodation', {}],
      ['[data-plan-experience]', '/ai-planner/', 'activities', { mode: 'destination' }],
      ['[data-plan-weather]', '/ai-planner/', 'activities,equipment', { mode: 'destination' }],
      ['[data-plan-cover]', '/travel-insurance/', 'insurance', { trip_destination: hub.id }],
      ['[data-plan-total]', '/packages/', fullTripPlanningScope, {}],
      ['[data-plan-guide]', '/ai-planner/', fullTripPlanningScope, { mode: 'destination' }],
      ['[data-plan-ai]', '/ai-planner/', fullTripPlanningScope, { mode: 'destination' }]
    ];
    actions.forEach(([selector, path, scope, extra]) => setExplorationHubAction(plan.querySelector(selector), path, hub, pointContext, scope, extra));
    const moduleActions = {
      mobility: ['/ai-planner/', 'transfers'],
      dining: ['/ai-planner/', 'activities'],
      entry: ['/ai-planner/', 'activities'],
      connectivity: ['/ai-planner/', 'connectivity'],
      accessibility: ['/ai-planner/', 'accommodation,transfers'],
      equipment: ['/ai-planner/', 'equipment']
    };
    plan.querySelectorAll('[data-plan-module]').forEach(module => {
      module.dataset.state = 'needs_search';
      const state = module.querySelector('[data-plan-module-state]');
      const moduleDetail = module.querySelector('[data-plan-module-detail]');
      const action = module.querySelector('[data-plan-module-action]');
      if (state) state.textContent = 'ייפתח לפי תאריכים, נוסעים והעדפות';
      if (moduleDetail) moduleDetail.textContent = `החלק הזה יתוכנן ל${placeLabel} לפי ההרכב, המועד והבחירות שלכם. לא מוצגת כאן זמינות שלא נבדקה.`;
      const [path, scope] = moduleActions[module.dataset.planModule] || ['/ai-planner/', fullTripPlanningScope];
      setExplorationHubAction(action, path, hub, pointContext, scope, { mode: 'destination' });
    });
    const coverage = plan.querySelector('[data-plan-coverage-copy]');
    if (coverage) coverage.textContent = `${placeLabel} נבחרה. כל שנים עשר חלקי העלות פתוחים לעריכה ולחיפוש לפי הפרטים שלכם.`;
    const ledgerTotal = plan.querySelector('[data-plan-ledger-total]');
    const ledgerState = plan.querySelector('[data-plan-ledger-state]');
    const ledgerList = plan.querySelector('[data-plan-ledger-list]');
    const ledgerTruth = plan.querySelector('[data-plan-ledger-truth]');
    if (ledgerTotal) ledgerTotal.textContent = 'תחושב לאחר בחירת תאריכים ונוסעים';
    if (ledgerState) ledgerState.textContent = '12 מרכיבי עלות ממתינים לחיפוש תואם';
    if (ledgerList) ledgerList.replaceChildren(...fullTripCostScope.map(itemId => {
      const row = document.createElement('span');
      row.dataset.state = 'needs_search';
      appendTextElement(row, 'b', selectedPlanCostLabels[itemId] || itemId.replaceAll('_', ' '));
      appendTextElement(row, 'em', `בדיקת אפשרויות ל${hub.city}`);
      return row;
    }));
    if (ledgerTruth) ledgerTruth.textContent = 'העלות תחובר רק מתוצאות שתואמות לאותם תאריכים, נוסעים ותנאים. לא מוצג חיסכון ללא שתי חלופות ברות השוואה.';
    const save = plan.querySelector('[data-plan-save]');
    if (save) {
      save.disabled = true;
      save.setAttribute('aria-label', 'אפשר לשמור אחרי קבלת אפשרויות חיפוש');
    }
    if (!prefersReducedMotion()) {
      plan.classList.remove('is-updating');
      void plan.offsetWidth;
      plan.classList.add('is-updating');
    }
  }

  const rail = mapShell.querySelector('[data-globe-selection]');
  if (rail) {
    rail.dataset.state = 'supported';
    const kicker = rail.querySelector('[data-globe-selection-kicker]');
    const title = rail.querySelector('[data-globe-selection-title]');
    const copy = rail.querySelector('[data-globe-selection-detail]');
    const action = rail.querySelector('[data-globe-selection-action]');
    if (kicker) kicker.textContent = 'המקום זוהה';
    if (title) title.textContent = `${placeLabel} מוכנה לתכנון`;
    if (copy) copy.textContent = 'כל חלקי החופשה פתוחים עכשיו. מחירים וזמינות יתקבלו רק בחיפוש לפי התאריכים והנוסעים שלכם.';
    if (action) {
      action.href = '#destination-plan-title';
      action.firstChild.textContent = 'פתחו את תוכנית החופשה';
    }
  }
  setMapProgressState({ point: 'confirmed', destination: 'confirmed', scopes: 'confirmed', live: 'waiting', destinationDetail: placeLabel, liveDetail: 'ממתין לתאריכים ולנוסעים לפני בדיקת האפשרויות' });
  const routeTitle = mapShell.querySelector('[data-route-title]');
  if (routeTitle) routeTitle.textContent = `תל אביב אל ${hub.city}: השוו דרכים לאחר בחירת תאריכים ונוסעים`;
  setDiscoveryStatus('demo', `${placeLabel} נבחרה · מחירים וזמינות ייבדקו לפי פרטי הנסיעה`);
  renderIcons();
  syncDiscoveryUrl('replace');
  revealGlobeSelection(detail.inputType || 'pointer');
  return true;
}

function renderUnsupportedGlobeSelection(detail = {}, globeRoot = null) {
  const mapShell = globeRoot?.closest?.('.theme-map-shell');
  if (!mapShell) return false;
  const latitude = Number(detail.latitude);
  const longitude = Number(detail.longitude);
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return false;
  clearActiveMapEntitySelection();
  const requestedSelectionId = /^[A-Za-z0-9_-]{8,80}$/.test(detail.selectionId || '') ? detail.selectionId : '';
  const selectionId = setActivePlanningSelection({
    selectionId: requestedSelectionId,
    latitude,
    longitude,
    destination: '',
    kind: 'map_point'
  }).selection_id;
  discoveryRequestController?.abort();
  discoveryRequestController = null;
  discoveryRequestGeneration += 1;
  setRouteListBusy(false);
  activeDestination = '';
  activeRouteId = '';
  discoverySelectedPlan = null;
  discoveryRoutes = [];
  discoveryDestinationLocked = false;
  discoveryDestinationMode = 'recommended';
  discoveryBudgetCoverage = 'none';
  discoveryBudgetApplied = false;
  discoveryBudgetFilterActive = false;
  resetMapEntityExplorer('point');
  updateBudgetCoverageStatus();
  window.traVelGlobe3D?.clearSelection({ preservePoint: true, root: globeRoot });
  mapShell.querySelectorAll('.price-pin[data-destination]').forEach(item => {
    item.classList.remove('is-active');
    if (item.matches('button')) item.setAttribute('aria-pressed', 'false');
    else item.removeAttribute('aria-current');
  });
  renderRoutes([]);
  const routeTitle = mapShell.querySelector('[data-route-title]');
  if (routeTitle) routeTitle.textContent = 'המסלול ייבנה לאחר זיהוי היעד והנוסעים';
  const pointContext = {
    selection_id: selectionId,
    selection_kind: 'map_point',
    latitude: latitude.toFixed(4),
    longitude: longitude.toFixed(4),
    mode: 'map_point',
    intent: activePlanIntent,
    ...discoveryTripContextQuery('ai')
  };

  mapShell.querySelectorAll('[data-map-result]').forEach(card => {
    card.dataset.empty = 'true';
    card.removeAttribute('data-destination');
    const image = card.querySelector('[data-result-image]');
    if (image) image.hidden = true;
    const fields = {
      '[data-result-city]': 'האזור שבחרתם',
      '[data-result-state-label]': `${latitude.toFixed(2)}°, ${longitude.toFixed(2)}°`,
      '[data-result-price]': 'זהו את המקום והוסיפו העדפות',
      '[data-result-total]': 'הבחירה נשמרה',
      '[data-result-note]': 'מתכנן החופשה יזהה את האזור לפני שיוצגו עיר, מוצרים או מחירים.',
      '[data-result-airport]': 'דרך הגעה לפי היעד',
      '[data-result-hotel]': 'לינה לפי האזור',
      '[data-result-weather]': 'מזג אוויר לפי המועד'
    };
    Object.entries(fields).forEach(([selector, value]) => {
      const element = card.querySelector(selector);
      if (element) element.textContent = value;
    });
    replaceChildrenWithSpans(card.querySelector('[data-result-tags]'), ['אזור נבחר', 'זיהוי לפני הצעה', 'כל החופשה בלחיצה']);
    const resultActions = {
      '[data-result-guide]': { scope: fullTripPlanningScope, label: 'זהו את האזור', icon: 'locate-fixed' },
      '[data-result-hotels]': { scope: 'accommodation', label: 'תכננו לינה באזור', icon: 'bed-double' },
      '[data-result-insurance]': { scope: 'insurance', label: 'סדרו נושאים לבירור', icon: 'shield-check' }
    };
    Object.entries(resultActions).forEach(([selector, config]) => {
      const link = card.querySelector(selector);
      if (!link) return;
      setPointPlanningAction(link, pointContext, config.scope);
      link.replaceChildren();
      const icon = document.createElement('i');
      icon.setAttribute('data-lucide', config.icon);
      link.append(icon, document.createTextNode(config.label));
    });
    const saveButton = card.querySelector('.save-button');
    if (saveButton) {
      saveButton.disabled = true;
      saveButton.classList.remove('is-saved');
      saveButton.setAttribute('aria-label', 'זהו את האזור לפני שמירה');
    }
  });

  const plan = mapShell.querySelector('[data-destination-plan]');
  if (plan) {
    resetDestinationPlanTransientState(plan, { pointReceived: true });
    plan.dataset.state = 'unsupported';
    plan.dataset.coverageState = 'unknown';
    plan.setAttribute('aria-busy', 'false');
    const fields = {
      '[data-plan-title]': 'בואו נהפוך את הנקודה לחופשה',
      '[data-plan-state]': 'נקודת ההתחלה נשמרה · כל חלק פתוח להתאמה',
      '[data-plan-summary]': 'כתבו מה נמצא כאן או תנו למתכנן לזהות את האזור. אחר כך תוכלו לערוך טיסות, לינה, תחבורה, פעילויות וכל שאר החופשה.',
      '[data-plan-truth]': 'האזור עדיין לא זוהה. עיר, מחיר, זמינות או אפשרות רכישה יוצגו רק אחרי זיהוי ובדיקה מתאימה.'
    };
    Object.entries(fields).forEach(([selector, value]) => {
      const element = plan.querySelector(selector);
      if (element) element.textContent = value;
    });
    const pointPlanScopes = {
      '[data-plan-flight]': 'flights',
      '[data-plan-stay]': 'accommodation',
      '[data-plan-experience]': 'activities',
      '[data-plan-weather]': fullTripPlanningScope,
      '[data-plan-cover]': 'insurance',
      '[data-plan-total]': fullTripPlanningScope,
      '[data-plan-guide]': fullTripPlanningScope
    };
    Object.entries(pointPlanScopes).forEach(([selector, scope]) => {
      const link = plan.querySelector(selector);
      if (!link) return;
      setPointPlanningAction(link, pointContext, scope);
    });
    const ai = plan.querySelector('[data-plan-ai]');
    setPointPlanningAction(ai, pointContext, fullTripPlanningScope);
    const meter = plan.querySelector('[data-plan-meter]');
    if (meter) {
      meter.setAttribute('aria-valuemax', '12');
      meter.setAttribute('aria-valuenow', '1');
      meter.setAttribute('aria-valuetext', 'נקודת ההתחלה נבחרה; עוד 11 חלקי חופשה פתוחים להתאמה');
      const count = meter.querySelector('[data-plan-meter-count]');
      const fill = meter.querySelector('[data-plan-meter-fill]');
      const label = meter.querySelector('[data-plan-meter-label]');
      if (count) count.textContent = '1/12';
      if (fill) fill.style.setProperty('--plan-coverage', '8.33%');
      if (label) label.textContent = 'בחירות מוכנות';
    }
    const coverage = plan.querySelector('[data-plan-coverage-copy]');
    if (coverage) coverage.textContent = 'נקודת ההתחלה נבחרה. זהו את האזור ואז פתחו כל חלק בחופשה לפי הסדר שמתאים לכם.';
    const pointScopeByModule = {
      route: 'flights', stay: 'accommodation', mobility: 'transfers', activities: 'activities', dining: 'dining',
      connectivity: 'connectivity', insurance: 'insurance', equipment: 'equipment', accessibility: 'accommodation,transfers'
    };
    plan.querySelectorAll('[data-plan-module]').forEach(module => {
      module.dataset.state = 'needs_details';
      const state = module.querySelector('[data-plan-module-state]');
      const moduleDetail = module.querySelector('[data-plan-module-detail]');
      const action = module.querySelector('[data-plan-module-action]');
      if (state) state.textContent = 'מוכן להתאמה';
      if (moduleDetail) moduleDetail.textContent = 'זהו את האזור והוסיפו רק את הפרטים שחשובים לכם בחלק הזה של החופשה.';
      setPointPlanningAction(action, pointContext, pointScopeByModule[module.dataset.planModule] || fullTripPlanningScope);
    });
    const ledgerTotal = plan.querySelector('[data-plan-ledger-total]');
    const ledgerState = plan.querySelector('[data-plan-ledger-state]');
    const ledgerList = plan.querySelector('[data-plan-ledger-list]');
    const ledgerTruth = plan.querySelector('[data-plan-ledger-truth]');
    if (ledgerTotal) ledgerTotal.textContent = 'נבנה אחרי זיהוי האזור';
    if (ledgerState) ledgerState.textContent = '12 רכיבי עלות שאפשר להתאים';
    if (ledgerList) ledgerList.replaceChildren(...fullTripCostScope.map(itemId => {
      const row = document.createElement('span');
      row.dataset.state = 'needs_search';
      appendTextElement(row, 'b', selectedPlanCostLabels[itemId] || itemId.replaceAll('_', ' '));
      appendTextElement(row, 'em', 'זהו את האזור ובחרו פרטים');
      return row;
    }));
    if (ledgerTruth) ledgerTruth.textContent = 'לא מוצגים מחיר או חיסכון לפני זיהוי יעד ובדיקה מול מקור מתאים.';
  }
  updateGlobeSelectionRail(null, { mode: 'unsupported', latitude, longitude, selectionId });
  setDiscoveryStatus('demo', 'האזור נבחר · אפשר לזהות אותו ולבנות תוכנית מלאה');
  renderIcons();
  syncDiscoveryUrl('replace');
  revealGlobeSelection(detail.inputType || 'pointer');
  return true;
}

function renderHomePointSelection(detail = {}, globeRoot = null) {
  const homeGlobe = globeRoot?.matches?.('[data-home-globe]') ? globeRoot : null;
  const homeStack = homeGlobe?.closest('.home-globe-stack');
  const homePlan = homeStack?.querySelector('[data-home-plan]');
  const latitude = Number(detail.latitude);
  const longitude = Number(detail.longitude);
  if (!homeGlobe || !homeStack || !homePlan
    || !Number.isFinite(latitude) || latitude < -90 || latitude > 90
    || !Number.isFinite(longitude) || longitude < -180 || longitude > 180) return false;

  const hubId = String(detail.hubId || '').replace(/[^a-z0-9-]/g, '').slice(0, 60);
  const candidateHub = detail.selectionKind === 'exploration_hub' ? explorationHubData[hubId] : null;
  const hub = candidateHub && explorationHubDistanceKm({ latitude, longitude }, candidateHub) <= candidateHub.radiusKm
    ? candidateHub
    : null;
  const placeLabel = hub ? `${hub.city}, ${hub.country}` : '';
  const planningDestination = hub ? (hub.iataSearchCode || hub.id) : '';

  const requestedSelectionId = /^[A-Za-z0-9_-]{8,80}$/.test(detail.selectionId || '') ? detail.selectionId : '';
  const selection = setActivePlanningSelection({
    selectionId: requestedSelectionId,
    latitude,
    longitude,
    destination: '',
    kind: 'map_point'
  });
  activeDestination = '';
  activeRouteId = '';
  activeRouteSelectionLocked = false;
  discoverySelectedPlan = null;
  discoveryDestinationLocked = false;
  discoveryDestinationMode = 'recommended';
  homeGlobe.querySelectorAll('.price-pin[data-destination]').forEach(pin => {
    pin.classList.remove('is-active');
    if (pin.matches('button')) pin.setAttribute('aria-pressed', 'false');
    else pin.removeAttribute('aria-current');
  });

  homePlan.removeAttribute('data-destination');
  homePlan.dataset.selectionKind = hub ? 'exploration_hub' : 'map_point';
  homePlan.dataset.selectionId = selection.selection_id;
  if (hub) homePlan.dataset.explorationHub = hub.id;
  else homePlan.removeAttribute('data-exploration-hub');
  const selectionQuery = activePlanningSelectionQuery('');
  const aiContext = {
    ...selectionQuery,
    ...homePlanningLinkContext('ai'),
    mode: 'map_point',
    intent: activePlanIntent,
    ...(hub ? { q: placeLabel, destination: planningDestination } : {})
  };
  const componentScopes = {
    '[data-home-plan-flight]': 'flights',
    '[data-home-plan-stay]': 'accommodation',
    '[data-home-plan-transfer]': 'transfers',
    '[data-home-plan-activity]': 'activities',
    '[data-home-plan-dining]': 'dining',
    '[data-home-plan-insurance]': 'insurance',
    '[data-home-plan-connectivity]': 'connectivity',
    '[data-home-plan-equipment]': 'equipment',
    '[data-home-plan-extras]': 'dining,connectivity,equipment',
    '[data-home-plan-ai]': fullTripPlanningScope
  };
  Object.entries(componentScopes).forEach(([selector, scope]) => {
    const link = homePlan.querySelector(selector);
    if (!link) return;
    link.href = destinationPlanUrl('/ai-planner/', { ...aiContext, scope });
    link.removeAttribute('aria-disabled');
    link.removeAttribute('tabindex');
  });
  const fullPlan = homePlan.querySelector('[data-home-plan-full]');
  if (fullPlan) {
    fullPlan.href = destinationPlanUrl('/travel-map/', {
      ...selectionQuery,
      ...homePlanningLinkContext('map'),
      mode: 'map_point',
      intent: activePlanIntent,
      scope: fullTripPlanningScope,
      ...(hub ? { q: placeLabel, destination: planningDestination } : {})
    });
    fullPlan.removeAttribute('aria-disabled');
    fullPlan.removeAttribute('tabindex');
  }

  const planSummary = homePlan.querySelector('[data-home-plan-summary]');
  const ledgerState = homePlan.querySelector('[data-home-plan-ledger-state]');
  const aiLabel = homePlan.querySelector('[data-home-plan-ai-label]');
  const fullLabel = homePlan.querySelector('[data-home-plan-full-label]');
  if (planSummary) planSummary.textContent = hub
    ? `בחרתם את ${placeLabel}. כל שמונת חלקי החופשה פתוחים עכשיו לחיפוש, התאמה ועריכה לפי הפרטים שלכם.`
    : 'בחרתם אזור על המפה. כל שמונת חלקי החופשה מוכנים לזיהוי, התאמה ועריכה לפי הפרטים שלכם.';
  if (ledgerState) ledgerState.textContent = '8 חלקים בתכנון. נזהה את האזור ונבדוק מחיר, זמינות ותנאים לפני הרכישה.';
  if (aiLabel) aiLabel.textContent = hub ? `סדרו לי חופשה מלאה ל${hub.city}` : 'זהו את האזור וסדרו לי חופשה מלאה';
  if (fullLabel) fullLabel.textContent = hub ? `פתחו תכנון מלא ל${hub.city}` : 'פתחו את האזור בתכנון המלא';

  const resultCard = homeStack.querySelector('[data-map-result]');
  if (resultCard) {
    resultCard.dataset.selectionKind = hub ? 'exploration_hub' : 'map_point';
    resultCard.removeAttribute('data-destination');
    if (hub) resultCard.dataset.explorationHub = hub.id;
    else resultCard.removeAttribute('data-exploration-hub');
    const image = resultCard.querySelector('[data-result-image]');
    if (image) image.hidden = true;
    const resultContext = resultCard.querySelector('[data-result-context]');
    const resultCity = resultCard.querySelector('[data-result-city]');
    const resultPrice = resultCard.querySelector('[data-result-price]');
    const resultNote = resultCard.querySelector('[data-result-note]');
    if (resultContext) resultContext.textContent = hub ? 'המקום שבחרתם על המפה' : 'הבחירה שלכם על המפה';
    if (resultCity) resultCity.textContent = hub ? placeLabel : 'האזור שבחרתם';
    if (resultPrice) resultPrice.textContent = hub ? 'מוכנים לחיפוש לפי תאריכים ונוסעים' : 'נזהה ונבדוק לפי הפרטים שלכם';
    if (resultNote) resultNote.textContent = hub
      ? `הבחירה ב${placeLabel} כבר פתחה טיסות, לינה, העברות, פעילויות, אוכל, ביטוח, תקשורת וציוד לעריכה. מחיר וזמינות יתקבלו בחיפוש המתאים לפרטים שלכם.`
      : 'הנקודה נשמרה בתכנון. עברו למפה המלאה או תנו למתכנן לזהות את המקום ולחבר את כל חלקי החופשה.';
    replaceChildrenWithSpans(resultCard.querySelector('[data-result-tags]'), hub
      ? [hub.city, '8 חלקים לעריכה', 'מחיר סופי לאחר בדיקה']
      : ['אזור נבחר', '8 חלקים לעריכה', 'מחיר סופי לאחר בדיקה']);
    const saveButton = resultCard.querySelector('.save-button');
    if (saveButton) {
      saveButton.disabled = true;
      saveButton.classList.remove('is-saved');
      saveButton.setAttribute('aria-label', 'אפשר לשמור לאחר זיהוי האזור');
    }
  }

  const routeBoard = document.querySelector('[data-home-route-board]');
  const routeEmpty = document.querySelector('[data-home-route-empty]');
  if (routeBoard && routeEmpty) {
    routeBoard.hidden = true;
    routeEmpty.hidden = false;
    routeEmpty.textContent = hub
      ? `דרכי ההגעה ל${hub.city} ייבדקו לפי התאריכים, הנוסעים והכבודה שלכם.`
      : 'נזהה את האזור לפני שנציג דרכי הגעה. כל פרטי הנקודה כבר עברו למפה ולמתכנן.';
  }
  const feedback = homeStack.querySelector('[data-home-reveal]');
  const feedbackStatus = feedback?.querySelector('[data-home-reveal-status]');
  if (feedback) feedback.dataset.state = 'ready';
  if (feedbackStatus) feedbackStatus.textContent = hub ? `${placeLabel}: היעד נבחר וכל חלקי החופשה מוכנים לעריכה.` : 'האזור נבחר. כל חלקי החופשה מוכנים לעריכה.';
  const liveRegion = homeGlobe.querySelector('[data-globe-live]');
  if (liveRegion) liveRegion.textContent = hub ? `${placeLabel}: היעד נוסף לתכנון ושמונת חלקי החופשה מוכנים לעריכה.` : 'האזור שבחרתם נוסף לתכנון. שמונת חלקי החופשה מוכנים לעריכה.';
  runConfirmedPlanAnimation(homePlan, '.home-plan-full');
  renderIcons();
  return true;
}

function initGlobePointSelection() {
  document.addEventListener('travelglobe:select', event => {
    const globeRoot = event.target.closest('[data-globe-3d][data-discovery-globe]');
    if (!globeRoot) return;
    const detail = event.detail || {};
    const destinationId = typeof detail.nearestDestination === 'string' ? detail.nearestDestination : '';
    const hubId = typeof detail.hubId === 'string' ? detail.hubId : '';
    const resolvedHub = detail.selectionKind === 'exploration_hub' && Boolean(explorationHubData[hubId]);
    if (globeRoot.matches('[data-home-globe]')) {
      if (detail.supported && !resolvedHub && destinationData[destinationId]) {
        const selection = setActivePlanningSelection({
          latitude: detail.latitude,
          longitude: detail.longitude,
          destination: destinationId,
          kind: 'destination'
        });
        detail.selectionId = selection.selection_id;
        discoveryDestinationMode = 'recommended';
        discoveryDestinationLocked = true;
        discoverySelectedPlan = null;
        activeRouteId = '';
        activeRouteSelectionLocked = false;
        const pin = globeRoot.querySelector(`.price-pin[data-destination="${CSS.escape(destinationId)}"]`);
        setActiveDestination(destinationId, pin, { animate: true, responseConfirmed: false, userSelected: true, globeRoot });
        hydrateDiscovery(discoveryRequestParams({ destination: destinationId }));
        return;
      }
      renderHomePointSelection(detail, globeRoot);
      return;
    }
    if (!globeRoot.closest('.theme-map-shell')) return;
    clearActiveMapEntitySelection();
    const selection = setActivePlanningSelection({
      latitude: detail.latitude,
      longitude: detail.longitude,
      destination: detail.supported && !resolvedHub ? destinationId : '',
      kind: 'map_point'
    });
    detail.selectionId = selection.selection_id;
    if (resolvedHub) {
      renderExplorationHubSelection(detail, globeRoot);
      return;
    }
    if (!detail.supported || !destinationData[destinationId]) {
      renderUnsupportedGlobeSelection(detail, globeRoot);
      return;
    }
    discoveryDestinationMode = 'recommended';
    discoveryDestinationLocked = true;
    discoverySelectedPlan = null;
    activeRouteId = '';
    activeRouteSelectionLocked = false;
    setActiveDestination(destinationId, null, { animate: true, responseConfirmed: false, userSelected: true, globeRoot });
    updateGlobeSelectionRail(destinationData[destinationId], { distanceKm: Number(detail.distanceKm), awaiting: true });
    syncDiscoveryUrl('push');
    revealGlobeSelection(detail.inputType || 'pointer');
    hydrateDiscovery(discoveryRequestParams({ destination: destinationId }));
  });
}

function updateDestinationPlanStages(plan, responseState = 'pending') {
  const responseSettled = responseState !== 'pending';
  const responseConfirmed = responseState === 'current';
  const staleSnapshot = responseState === 'stale';
  const fallbackSnapshot = responseState === 'fallback';
  const currentStage = { deals: 'total', hotels: 'stay', airports: 'route', weather: 'experience' }[activeLayer] || 'route';
  const routeCount = responseConfirmed && discoveryLiveLayers.airports ? discoveryRoutes.length : 0;
  const labels = !responseSettled ? {
    destination: 'נבחר', route: 'מעדכנים', stay: 'מעדכנים', experience: 'מעדכנים', cover: 'מעדכנים', total: 'מעדכנים'
  } : (staleSnapshot ? {
    destination: 'נבחר',
    route: discoveryLiveLayers.airports ? `${discoveryRoutes.length} אפשרויות מהבדיקה האחרונה` : 'מוכן לחיפוש מחדש',
    stay: discoveryLiveLayers.hotels ? 'מידע קודם · נדרש רענון' : 'מוכן לבדיקה',
    experience: discoveryLiveLayers.weather ? 'המידע האחרון · בדקו שוב' : 'מוכן לתכנון',
    cover: 'ממתין לפרטים',
    total: discoveryLiveLayers.deals ? 'מחיר קודם · נדרש רענון' : 'ממתין לבדיקת מחיר'
  } : (fallbackSnapshot ? {
    destination: 'נבחר', route: 'מוכן לבדיקה', stay: 'מוכן לבדיקה', experience: 'מוכן לתכנון', cover: 'ממתין לפרטים', total: 'ממתין לבדיקת מחיר'
  } : {
    destination: 'נבחר',
    route: routeCount ? `${routeCount} אפשרויות התקבלו` : 'מוכן להשוואה',
    stay: discoveryLiveLayers.hotels ? 'מידע עדכני התקבל' : 'מוכן לבדיקה',
    experience: discoveryLiveLayers.weather ? 'תנאי מזג אוויר התקבלו' : 'מוכן לתכנון',
    cover: 'ממתין לפרטים',
    total: discoveryLiveLayers.deals ? 'נתון מחיר התקבל' : 'ממתין לבדיקת מחיר'
  }));
  plan.querySelectorAll('[data-plan-stage]').forEach(stage => {
    const stageName = stage.dataset.planStage || '';
    const isCurrent = stageName === currentStage;
    const isInformed = stageName === 'destination' || (responseConfirmed && (
      (stageName === 'route' && discoveryLiveLayers.airports) ||
      (stageName === 'stay' && discoveryLiveLayers.hotels) ||
      (stageName === 'total' && discoveryLiveLayers.deals)
    ));
    stage.classList.toggle('is-current', isCurrent);
    stage.classList.toggle('is-informed', isInformed);
    stage.classList.toggle('is-complete', stageName === 'destination');
    stage.classList.toggle('is-ready', responseSettled && stageName !== 'destination' && !isInformed);
    stage.classList.toggle('is-pending', !responseSettled && stageName !== 'destination');
    if (isCurrent) stage.setAttribute('aria-current', 'step');
    else stage.removeAttribute('aria-current');
    const detail = stage.querySelector('small');
    if (detail && labels[stageName]) detail.textContent = labels[stageName];
  });
}

function runConfirmedPlanAnimation(container, tailSelector) {
  if (!container) return;
  const generation = (Number(container.traVelMotionGeneration) || 0) + 1;
  container.traVelMotionGeneration = generation;
  if (container.traVelMotionTimer) window.clearTimeout(container.traVelMotionTimer);
  container.classList.remove('is-updating');
  if (prefersReducedMotion()) return;
  void container.offsetWidth;
  container.classList.add('is-updating');
  const tail = container.querySelector(tailSelector);
  const finish = () => {
    if (container.traVelMotionGeneration !== generation) return;
    container.classList.remove('is-updating');
    if (container.traVelMotionTimer) window.clearTimeout(container.traVelMotionTimer);
    container.traVelMotionTimer = 0;
  };
  tail?.addEventListener('animationend', finish, { once: true });
  container.traVelMotionTimer = window.setTimeout(finish, 1400);
}

function updateDestinationPlan(data, animate = true, responseState = animate ? 'current' : 'pending') {
  const plan = document.querySelector('[data-destination-plan]');
  if (!plan || !data) return;
  const intent = destinationPlanIntents[activePlanIntent] || destinationPlanIntents.smart;
  const snapshotCurrent = responseState === 'current' && discoverySnapshotIsCurrent();
  const snapshotStale = responseState === 'stale' && discoverySnapshotIsStale();
  const anySupplierSnapshot = Object.values(discoveryLiveLayers).some(Boolean);
  const currentLayerHasLiveData = snapshotCurrent && discoveryLiveLayers[activeLayer] === true;
  const anyLiveData = snapshotCurrent && anySupplierSnapshot;
  const hasLiveSeason = snapshotCurrent && fieldProvenanceLive(discoveryFieldProvenance, 'weather_season', data.id);
  const airportCode = data.airportCode || '';
  const destinationId = data.id || '';
  const selectedRoute = discoveryRoutes.find(route => route.id === activeRouteId && (!route.destination_id || route.destination_id === destinationId));
  const layerLabel = { deals: 'עלות מלאה', hotels: 'לינה', airports: 'טיסה ודרך', weather: 'מזג אוויר' }[activeLayer] || 'תכנון';
  plan.dataset.destination = destinationId;
  plan.dataset.intent = activePlanIntent;
  plan.dataset.state = snapshotStale && anySupplierSnapshot ? 'stale' : (currentLayerHasLiveData ? 'live' : 'planning');
  plan.dataset.motionState = snapshotStale && anySupplierSnapshot ? 'stale' : (anyLiveData ? 'live' : 'editorial');

  const fields = {
    '[data-plan-title]': `התוכנית ${intent.label} ל${data.city}`,
    '[data-plan-state]': snapshotStale && anySupplierSnapshot ? `${layerLabel} במוקד · מוצג המידע האחרון ויש לבדוק שוב` : `${layerLabel} במוקד · כל פרטי החופשה נשארים מחוברים`,
    '[data-plan-summary]': `${intent.summary} כל פרט נשאר מחובר ל${data.city} ולבחירות שלכם.`,
    '[data-plan-flight-title]': selectedRoute
      ? `${selectedRoute.label} נבחר כמועמד`
      : (activePlanIntent === 'easy'
      ? (data.airportDirect ? 'מתחילים במסלול הישיר' : 'מחפשים קונקשן מוגן ופשוט')
      : (activePlanIntent === 'value' ? 'משווים עלות מלאה בין דרכים' : 'ישיר מול קונקשן חכם')),
    '[data-plan-flight-detail]': [airportCode, data.flightDuration, 'כבודה ותנאי כרטיס'].filter(Boolean).join(' · '),
    '[data-plan-stay-title]': activePlanIntent === 'family'
      ? 'אזור נוח למשפחה ולמעברים קצרים'
      : (activePlanIntent === 'romantic' ? 'אזור שקט ונעים לשניים' : (data.hotelArea ? `מתחילים באזור ${data.hotelArea}` : 'בוחרים אזור לפני מלון')),
    '[data-plan-stay-detail]': [data.nights ? `${data.nights} לילות` : '', 'תחבורה', 'ביטול ועלות מלאה'].filter(Boolean).join(' · '),
    '[data-plan-experience-title]': activePlanIntent === 'adventure'
      ? 'טבע, פעילות, ציוד וימי התאוששות'
      : (activePlanIntent === 'romantic' ? 'אוכל, שקיעה וזמן חופשי' : (activePlanIntent === 'family' ? 'פעילויות לפי גיל וקצב' : 'מסלול לפי הכוונה שלכם')),
    '[data-plan-weather-title]': snapshotCurrent && discoveryLiveLayers.weather && data.weather
      ? `${data.weather} · ${data.weatherCondition || ''}`
      : (snapshotStale && discoveryLiveLayers.weather && data.weather ? `${data.weather} · המידע האחרון שנבדק` : 'בדיקה לפי תאריך'),
    '[data-plan-weather-detail]': snapshotCurrent && discoveryLiveLayers.weather
      ? (hasLiveSeason ? `התאמת עונה מעודכנת: ${data.seasonFit || 'לפי מועד'}` : 'התנאים הנוכחיים עודכנו. התאמת העונה תיבדק לפי תאריך הנסיעה')
      : (snapshotStale && discoveryLiveLayers.weather
        ? 'מוצגת תצפית מזג אוויר קודמת בלבד. נדרש רענון ותאריך נסיעה לפני החלטה.'
        : 'מזג אוויר יאומת לפי תאריך הנסיעה'),
    '[data-plan-cover-title]': activePlanIntent === 'adventure' ? 'פרטי פעילות וציוד לבדיקת ביטוח' : 'פרטים לבדיקת ביטוח נסיעות',
    '[data-plan-total-title]': 'עדיין לא חושבה בחבילה מלאה',
    '[data-plan-truth]': snapshotStale && anySupplierSnapshot
      ? `המידע שמוצג הוא האחרון שנבדק: ${discoveryFreshnessLabel()}. אין בכך אישור מחיר, זמינות או הזמנה.`
      : (anyLiveData
      ? 'יש מידע עדכני לחלק מהחופשה. העלות הכוללת והזמינות ייבדקו לפני כל אישור.'
      : 'היעד נבחר. מחירים, זמינות והזמנה עדיין לא נבדקו.')
  };
  Object.entries(fields).forEach(([selector, value]) => {
    const element = plan.querySelector(selector);
    if (element) element.textContent = value;
  });

  const planningContext = {
    intent: activePlanIntent,
    budget: discoveryQuery.budget,
    trip: discoveryQuery.trip,
    max_stops: discoveryQuery.max_stops,
    max_duration: discoveryQuery.max_duration,
    allow_overnight: discoveryQuery.allow_overnight ? 1 : '',
    direct: discoveryQuery.direct ? 1 : ''
  };
  const selectionHandoff = activePlanningSelectionHandoffQuery(destinationId);
  const links = {
    '[data-plan-flight]': destinationPlanUrl('/flights/', { ...selectionHandoff, destination: airportCode, ...discoveryTripContextQuery('flights'), ...planningContext }),
    '[data-plan-stay]': destinationPlanUrl('/hotels/', { ...selectionHandoff, destination: airportCode, area: data.hotelArea || '', ...discoveryTripContextQuery('hotels'), ...planningContext }),
    '[data-plan-experience]': destinationDirectoryUrl(destinationId, { ...selectionHandoff, ...discoveryTripContextQuery('map'), ...planningContext }),
    '[data-plan-weather]': destinationPlanUrl('/travel-map/', { ...selectionHandoff, destination: destinationId, layer: 'weather', intent: activePlanIntent, ...discoveryQuery, ...discoveryTripContextQuery('map') }),
    '[data-plan-cover]': destinationPlanUrl('/travel-insurance/', { ...selectionHandoff, trip_destination: destinationId, intent: activePlanIntent, ...discoveryTripContextQuery('insurance') }),
    '[data-plan-total]': destinationPlanUrl('/packages/', { ...selectionHandoff, destination: airportCode, ...discoveryTripContextQuery('packages'), ...planningContext }),
    '[data-plan-guide]': destinationPlanUrl(data.url || '/destinations/', { ...selectionHandoff, destination: destinationId }),
    '[data-plan-ai]': destinationPlanUrl('/ai-planner/', {
      ...activePlanningSelectionQuery(destinationId),
      scope: fullTripPlanningScope,
      ...discoveryTripContextQuery('ai'),
      ...planningContext
    })
  };
  Object.entries(links).forEach(([selector, href]) => {
    const link = plan.querySelector(selector);
    if (link) {
      link.href = href;
      link.removeAttribute('aria-disabled');
      link.removeAttribute('tabindex');
    }
  });
  const saveButton = plan.querySelector('[data-plan-save]');
  if (saveButton) {
    saveButton.disabled = responseState === 'pending';
    const saved = isWorkspaceItemSaved(normalizeWorkspaceItem(mapDestinationWorkspaceItem(data)).id);
    saveButton.classList.toggle('is-saved', saved);
    const label = saveButton.querySelector('span');
    if (label) label.textContent = saved ? 'נשמר לנסיעה' : 'שמרו את התוכנית';
  }
  plan.querySelectorAll('[data-plan-layer]').forEach(option => option.classList.toggle('is-layer-active', option.dataset.planLayer === activeLayer));
  updateDestinationPlanStages(plan, responseState);
  renderDestinationDecisionCockpit(plan, data, responseState);

  if (animate && responseState === 'current') {
    runConfirmedPlanAnimation(plan, '.destination-cost-ledger');
  } else plan.classList.remove('is-updating');
}

function updateHomeDestinationPlan(data, animate = true) {
  if (!data) return;
  const airportCode = data.airportCode || '';
  const destinationId = data.id || '';
  const homePlan = document.querySelector('[data-home-plan]');
  if (homePlan) {
    homePlan.dataset.destination = destinationId;
    homePlan.removeAttribute('data-selection-kind');
    homePlan.removeAttribute('data-selection-id');
  }
  const summary = document.querySelector('[data-home-plan-summary]');
  if (summary) summary.textContent = `${data.city} נבחרה. עכשיו מחברים דרך, לינה, חוויות והגנה לתוכנית אחת.`;
  const ledgerState = document.querySelector('[data-home-plan-ledger-state]');
  if (ledgerState) ledgerState.textContent = '8 רכיבים בתכנון. המחיר, הזמינות והתנאים ייבדקו לפני רכישה.';
  const fullLabel = document.querySelector('[data-home-plan-full-label]');
  if (fullLabel) fullLabel.textContent = `פתחו את התכנון המלא ל${data.city}`;
  const selection = activePlanningSelectionQuery(destinationId);
  const context = { ...selection, intent: activePlanIntent };
  const links = {
    '[data-home-plan-flight]': destinationPlanUrl('/flights/', { ...selection, ...homePlanningLinkContext('flights'), destination: airportCode, intent: activePlanIntent }),
    '[data-home-plan-stay]': destinationPlanUrl('/hotels/', { ...selection, ...homePlanningLinkContext('hotels'), destination: airportCode, area: data.hotelArea || '', intent: activePlanIntent }),
    '[data-home-plan-ai]': destinationPlanUrl('/ai-planner/', { ...context, ...homePlanningLinkContext('ai'), scope: fullTripPlanningScope }),
    '[data-home-plan-transfer]': destinationPlanUrl('/packages/', { ...context, ...homePlanningLinkContext('packages'), destination: airportCode, transfers: 1 }),
    '[data-home-plan-activity]': destinationPlanUrl(data.url || '/destinations/', { ...context, ...homePlanningLinkContext('map'), destination: destinationId }),
    '[data-home-plan-dining]': destinationPlanUrl('/ai-planner/', { ...context, ...homePlanningLinkContext('ai'), scope: 'dining' }),
    '[data-home-plan-insurance]': destinationPlanUrl('/travel-insurance/', { ...context, ...homePlanningLinkContext('insurance'), trip_destination: destinationId }),
    '[data-home-plan-connectivity]': destinationPlanUrl('/ai-planner/', { ...context, ...homePlanningLinkContext('ai'), scope: 'connectivity' }),
    '[data-home-plan-equipment]': destinationPlanUrl('/ai-planner/', { ...context, ...homePlanningLinkContext('ai'), scope: 'equipment' }),
    '[data-home-plan-extras]': destinationPlanUrl('/ai-planner/', { ...context, ...homePlanningLinkContext('ai'), scope: 'dining,connectivity,equipment' }),
    '[data-home-plan-full]': destinationPlanUrl('/travel-map/', { ...context, ...homePlanningLinkContext('map'), scope: fullTripPlanningScope })
  };
  Object.entries(links).forEach(([selector, href]) => {
    const link = document.querySelector(selector);
    if (link) {
      link.href = href;
      link.removeAttribute('aria-disabled');
    }
  });
  if (homePlan && animate) {
    runConfirmedPlanAnimation(homePlan, '.home-plan-full');
  } else homePlan?.classList.remove('is-updating');
}

function refreshHomeDestinationPlanLinks() {
  const homePlan = document.querySelector('[data-home-plan]');
  const destinationId = String(homePlan?.dataset.destination || activeDestination || '');
  if (destinationId && destinationData[destinationId]) updateHomeDestinationPlan(destinationData[destinationId], false);
}

function initHomeRouteExamples() {
  const source = document.querySelector('[data-home-route-data]');
  if (!source) return;
  try {
    const parsed = JSON.parse(source.textContent || '{}');
    homeRouteExamples = parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
  } catch {
    homeRouteExamples = {};
  }
}

function homeRoutePriceLabel(route) {
  if (typeof route?.costs?.total_formatted === 'string' && route.costs.total_formatted.trim()) {
    return route.costs.total_formatted.trim();
  }
  const amount = Number(route?.costs?.total || 0);
  return amount > 0 ? `$${amount.toLocaleString('en-US', { maximumFractionDigits: 0 })}` : 'בהצעה האישית';
}

function homeRouteDurationLabel(route) {
  if (typeof route?.duration_label === 'string' && route.duration_label.trim()) return route.duration_label.trim();
  const minutes = Math.max(0, Number(route?.duration_minutes || 0));
  if (!minutes) return 'זמן הדרך ייבדק לפי התאריך';
  const hours = Math.floor(minutes / 60);
  return `${hours}:${String(Math.round(minutes % 60)).padStart(2, '0')} שעות`;
}

function renderHomeRouteComparison(data) {
  const board = document.querySelector('[data-home-route-board]');
  const empty = document.querySelector('[data-home-route-empty]');
  const cards = board?.querySelector('[data-home-route-cards]');
  if (!board || !empty || !cards || !data) return;
  const hydratedRoutes = discoveryRoutes.filter(route => route.destination_id === data.id);
  const planningRoutes = Array.isArray(homeRouteExamples[data.id]) ? homeRouteExamples[data.id] : [];
  const routes = (hydratedRoutes.length ? hydratedRoutes : planningRoutes).slice(0, 3);
  const eyebrow = document.querySelector('[data-home-route-eyebrow]');
  const airport = board.querySelector('[data-home-route-airport]');
  const link = document.querySelector('[data-home-route-link]');
  const linkLabel = link?.querySelector('[data-home-route-link-label]');
  if (eyebrow) eyebrow.textContent = `דרכים להגיע ל${data.city}`;
  if (airport) airport.textContent = data.airportCode || data.city;
  if (link) link.href = destinationPlanUrl('/flights/', { destination: data.airportCode || data.id });
  if (linkLabel) linkLabel.textContent = `השוו טיסות ל${data.city}`;
  empty.textContent = `אין עדיין השוואת מסלולים ל${data.city}. פתחו את חיפוש הטיסות כדי לבדוק תאריכים ונוסעים.`;
  board.hidden = routes.length === 0;
  empty.hidden = routes.length !== 0;
  cards.replaceChildren();
  if (!routes.length) return;
  const bestScore = Math.max(...routes.map(route => Number(route.score || 0)));
  routes.forEach(route => {
    const price = homeRoutePriceLabel(route);
    const stops = Number(route.stops || 0);
    const stopsLabel = stops === 0 ? 'ישיר' : (stops === 1 ? 'עצירה אחת' : `${stops} עצירות`);
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `route-card${Number(route.score || 0) === bestScore ? ' recommended' : ''}${route.id === activeRouteId ? ' is-selected' : ''}`;
    button.dataset.route = String(route.id || '');
    button.dataset.routeSummary = `${route.label || 'מסלול לתכנון'} · ${price} · מחיר לתכנון`;
    button.setAttribute('aria-pressed', String(route.id === activeRouteId));
    const top = document.createElement('div');
    top.className = 'route-card-top';
    appendTextElement(top, 'span', `${route.badge || 'אפשרות'} · לתכנון`, 'route-badge');
    appendTextElement(top, 'strong', price, 'price');
    button.append(top);
    appendTextElement(button, 'h3', route.label || 'מסלול לתכנון');
    appendTextElement(button, 'p', `${homeRouteDurationLabel(route)} · ${stopsLabel}`);
    const facts = document.createElement('div');
    facts.className = 'route-data';
    const pros = Array.isArray(route.pros) && route.pros.length
      ? route.pros.slice(0, 3)
      : ['משווים זמן ונוחות', 'בודקים כבודה', 'מאמתים תנאי מעבר'];
    pros.forEach(pro => {
      const fact = document.createElement('span');
      const icon = document.createElement('i');
      icon.dataset.lucide = 'circle-check-big';
      fact.append(icon, document.createTextNode(String(pro)));
      facts.append(fact);
    });
    button.append(facts);
    button.addEventListener('click', () => selectRoute(button));
    cards.append(button);
  });
  const summary = board.querySelector('[data-route-summary]');
  if (summary) summary.textContent = 'בחרו מסלול כדי לשמור העדפה. המחיר, הזמינות והתנאים מאומתים לפני התשלום.';
  renderIcons();
}

function initHomeDestinationReveal(initialHydration = Promise.resolve(), { autoEligible = true } = {}) {
  const globe = document.querySelector('[data-home-globe]');
  const trigger = document.querySelector('[data-home-surprise]');
  const feedback = document.querySelector('[data-home-reveal]');
  const status = feedback?.querySelector('[data-home-reveal-status]');
  const cancelButton = feedback?.querySelector('[data-home-reveal-cancel]');
  const homePlan = document.querySelector('[data-home-plan]');
  const planSummary = homePlan?.querySelector('[data-home-plan-summary]');
  const liveRegion = globe?.querySelector('[data-globe-live]');
  if (!globe || !trigger || !feedback || !status || !cancelButton || !homePlan || !planSummary || !liveRegion) return null;

  const ssrDestination = String(globe.dataset.defaultDestination || activeDestination || '').toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 60);
  const campaignKind = ['seasonal', 'evergreen'].includes(globe.dataset.campaignKind) ? globe.dataset.campaignKind : '';
  const state = {
    generation: 0,
    phase: 'ready',
    mode: '',
    running: false,
    interacted: false,
    autoEligible: autoEligible === true,
    campaignKind,
    autoStarted: false,
    autoTimer: 0,
    completionTimer: 0,
    surpriseCursor: 0,
    baselineDestination: '',
    candidateDestination: '',
    hydrationSettled: false,
    pendingWaits: new Map(),
    baselinePlanState: null
  };
  const reducedMotion = () => prefersReducedMotion();
  const hydration = Promise.resolve(initialHydration)
    .catch(() => undefined)
    .finally(() => { state.hydrationSettled = true; });
  state.hydration = hydration;

  const wait = (milliseconds, token) => new Promise(resolve => {
    if (token !== state.generation) {
      resolve(false);
      return;
    }
    if (reducedMotion()) {
      resolve(true);
      return;
    }
    const timeout = window.setTimeout(() => {
      state.pendingWaits.delete(timeout);
      resolve(token === state.generation);
    }, milliseconds);
    state.pendingWaits.set(timeout, resolve);
  });
  const clearWaits = () => {
    state.pendingWaits.forEach((resolve, timeout) => {
      window.clearTimeout(timeout);
      resolve(false);
    });
    state.pendingWaits.clear();
  };
  const clearCompletionTimer = () => {
    if (state.completionTimer) window.clearTimeout(state.completionTimer);
    state.completionTimer = 0;
  };
  const announce = message => setTextContentIfChanged(liveRegion, message);
  const setRevealState = (phase, message, { announceMessage = false } = {}) => {
    state.phase = phase;
    feedback.dataset.state = phase;
    status.textContent = message;
    cancelButton.hidden = !state.running || !['preparing', 'spinning', 'building'].includes(phase);
    if (announceMessage) announce(message);
  };
  const captureBaselinePlanState = () => {
    const aiLink = homePlan.querySelector('[data-home-plan-ai]');
    const aiLabel = homePlan.querySelector('[data-home-plan-ai-label]');
    return {
      summary: planSummary.textContent,
      aiHref: aiLink?.getAttribute('href') ?? null,
      aiDisabled: aiLink?.getAttribute('aria-disabled') ?? null,
      aiLabel: aiLabel?.textContent || ''
    };
  };
  const restoreBaseline = () => {
    const destination = state.baselineDestination;
    if (destination && destinationData[destination]) {
      setActiveDestination(destination, globe.querySelector(`.price-pin[data-destination="${CSS.escape(destination)}"]`), {
        animate: false,
        responseConfirmed: false,
        responseState: settledDiscoveryResponseState(),
        globeAnimate: false,
        globeAnnounce: false,
        pulseRoute: false
      });
    } else if (discoveryDestinationMode === 'anywhere') {
      renderDiscoveryEmptyState({ reason: 'open' });
    }
    const baselinePlanState = state.baselinePlanState;
    const aiLink = homePlan.querySelector('[data-home-plan-ai]');
    const aiLabel = homePlan.querySelector('[data-home-plan-ai-label]');
    if (baselinePlanState) {
      planSummary.textContent = baselinePlanState.summary;
      if (aiLink) {
        if (baselinePlanState.aiHref === null) aiLink.removeAttribute('href');
        else aiLink.setAttribute('href', baselinePlanState.aiHref);
        if (baselinePlanState.aiDisabled === null) aiLink.removeAttribute('aria-disabled');
        else aiLink.setAttribute('aria-disabled', baselinePlanState.aiDisabled);
      }
      if (aiLabel && baselinePlanState.aiLabel) aiLabel.textContent = baselinePlanState.aiLabel;
    }
  };
  const cancel = ({
    restore = true,
    message = 'השליטה אצלכם. בחרו יעד או הפעילו שוב את ההפתעה.',
    announceMessage = true
  } = {}) => {
    if (!state.running) return false;
    const cancelledMode = state.mode;
    const cancelledDestination = state.candidateDestination || state.baselineDestination;
    state.generation += 1;
    clearWaits();
    clearCompletionTimer();
    window.traVelGlobe3D?.cancelMotion?.(globe);
    if (restore) restoreBaseline();
    state.running = false;
    state.mode = '';
    state.candidateDestination = '';
    homePlan.classList.remove('is-updating');
    setRevealState('ready', message, { announceMessage });
    emitHomeFunnelEvent('reveal_cancel', { mode: cancelledMode, destination: cancelledDestination });
    return true;
  };
  const finish = (token, finalData, completedMode, animate) => {
    if (token !== state.generation) return false;
    const isSurprise = completedMode === 'surprise';
    const aiLink = homePlan.querySelector('[data-home-plan-ai]');
    const aiLabel = homePlan.querySelector('[data-home-plan-ai-label]');
    if (isSurprise && aiLink) {
      aiLink.href = destinationPlanUrl('/ai-planner/', {
        ...activePlanningSelectionQuery(finalData.id),
        ...homePlanningLinkContext('ai'),
        destination: finalData.id,
        intent: activePlanIntent,
        mode: 'surprise',
        scope: fullTripPlanningScope
      });
      if (aiLabel) aiLabel.textContent = 'הפכו את הרעיון לחופשה מלאה';
    } else if (aiLabel) aiLabel.textContent = 'סדרו לי חופשה מלאה';

    homePlan.classList.remove('is-updating');
    if (animate) {
      void homePlan.offsetWidth;
      homePlan.classList.add('is-updating');
      state.completionTimer = window.setTimeout(() => {
        homePlan.classList.remove('is-updating');
        state.completionTimer = 0;
      }, 900);
    }
    planSummary.textContent = `כיוון לחופשה ב${finalData.city} מוכן לעריכה. פתחו כל חלק והתאימו אותו לכם. המחיר, הזמינות והתנאים מאומתים לפני התשלום.`;
    if (animate) window.traVelGlobe3D?.pulseRoute?.(globe);
    const triggerLabel = trigger.querySelector('[data-home-surprise-label]');
    if (triggerLabel && isSurprise) triggerLabel.textContent = 'תפתיעו אותי שוב';
    state.running = false;
    state.mode = '';
    state.baselineDestination = finalData.id;
    state.candidateDestination = '';
    emitHomeFunnelEvent('reveal_complete', { mode: completedMode, destination: finalData.id });
    const completionMessage = isSurprise
      ? `מצאנו רעיון: ${finalData.city}. אפשר לפתוח כל רכיב ולשנות אותו.`
      : (completedMode === 'seasonal'
        ? `בחרנו כיוון שמתאים לעונה: ${finalData.city}. אפשר לבחור יעד אחר בכל רגע.`
        : `פתחנו כיוון להתחלת החיפוש: ${finalData.city}. אפשר לבחור יעד אחר בכל רגע.`);
    setRevealState('ready', completionMessage, { announceMessage: true });
    return true;
  };

  const run = async (mode, { preferredDestination = '' } = {}) => {
    if (!homeFunnelModes.has(mode)) return false;
    if (state.running) cancel({ message: 'מתחילים רעיון חדש.', announceMessage: false });
    const token = ++state.generation;
    state.running = true;
    state.mode = mode;
    if (state.mode === 'surprise') {
      state.autoEligible = false;
      if (state.autoTimer) window.clearTimeout(state.autoTimer);
      state.autoTimer = 0;
    }
    state.baselineDestination = activeDestination;
    state.baselinePlanState = captureBaselinePlanState();
    state.candidateDestination = '';
    clearWaits();
    clearCompletionTimer();
    emitHomeFunnelEvent('reveal_start', { mode: state.mode, destination: state.mode === 'surprise' ? '' : (preferredDestination || ssrDestination) });

    if (state.mode === 'surprise' && !state.hydrationSettled) {
      setRevealState('preparing', 'מעדכנים את רעיונות התכנון לפני ההפתעה.');
      await hydration;
      if (token !== state.generation) return false;
      state.baselineDestination = activeDestination;
      state.baselinePlanState = captureBaselinePlanState();
    }

    const destinationIds = Object.keys(destinationData).filter(id => globe.querySelector(`.price-pin[data-destination="${CSS.escape(id)}"]`));
    if (!destinationIds.length) {
      cancel({ restore: false, message: 'היעדים עדיין נטענים. נסו שוב בעוד רגע.' });
      return false;
    }

    const isSurprise = state.mode === 'surprise';
    const currentIndex = Math.max(0, destinationIds.indexOf(activeDestination));
    state.surpriseCursor = isSurprise ? (Math.max(state.surpriseCursor, currentIndex) + 1) % destinationIds.length : currentIndex;
    const campaignPreference = preferredDestination || (isSurprise ? '' : ssrDestination);
    if (!isSurprise && (!campaignPreference || !destinationData[campaignPreference] || !destinationIds.includes(campaignPreference))) {
      cancel({ restore: false, message: 'הכיוון הזה אינו זמין כרגע. בחרו יעד אחר על הגלובוס.' });
      return false;
    }
    const campaignDestination = destinationData[campaignPreference] ? campaignPreference : activeDestination;
    const finalDestination = isSurprise
      ? destinationIds[state.surpriseCursor]
      : (destinationData[campaignDestination] ? campaignDestination : destinationIds[0]);
    const previewIds = isSurprise && !reducedMotion()
      ? Array.from({ length: Math.min(4, destinationIds.length) }, (_, index) => destinationIds[(state.surpriseCursor + index + 1) % destinationIds.length])
      : [];
    state.candidateDestination = finalDestination;

    setRevealState('spinning', isSurprise
      ? 'פותחים רעיון חדש לחופשה שתוכלו לערוך.'
      : (state.mode === 'seasonal' ? 'פותחים כיוון שמתאים לעונה.' : 'פותחים כיוון להתחלת החיפוש.'));
    for (const previewId of previewIds) {
      if (token !== state.generation) return false;
      const preview = destinationData[previewId];
      if (preview) status.textContent = `עוברים על כיוון אפשרי: ${preview.city}.`;
      window.traVelGlobe3D?.focusDestination(previewId, { animate: true, pulse: false, announce: false, duration: 300, root: globe });
      if (!(await wait(315, token))) return false;
    }

    const finalData = destinationData[finalDestination];
    if (token !== state.generation) return false;
    if (!finalData) {
      cancel({ restore: false, message: 'הרעיון שבחרנו אינו זמין כרגע. נסו יעד אחר.' });
      return false;
    }
    const responseState = settledDiscoveryResponseState();
    setActiveDestination(finalDestination, globe.querySelector(`.price-pin[data-destination="${CSS.escape(finalDestination)}"]`), {
      animate: !reducedMotion(),
      responseConfirmed: false,
      responseState,
      globeAnimate: !reducedMotion(),
      globeAnnounce: false,
      globeRotations: reducedMotion() ? 0 : 1,
      globeDuration: reducedMotion() ? 180 : (isSurprise ? 1180 : 940),
      pulseRoute: false
    });
    if (reducedMotion()) return finish(token, finalData, state.mode, false);
    status.textContent = `נבחרה ${finalData.city}. כל חלקי החופשה פתוחים עכשיו לעריכה.`;
    if (!(await wait(isSurprise ? 1200 : 960, token))) return false;

    setRevealState('building', `הכיוון ל${finalData.city} מוכן. עוברים על האפשרויות שאפשר לערוך.`);
    planSummary.textContent = `${finalData.city} נבחרה. אפשר להשוות דרכי הגעה, כבודה ותנאי כרטיס.`;
    if (!(await wait(360, token))) return false;
    planSummary.textContent = 'אפשר לבחור אזור לינה לפי הקצב, התחבורה ומה שחשוב לכם.';
    if (!(await wait(360, token))) return false;
    planSummary.textContent = 'אפשר להוסיף פעילויות, תחבורה ונושאים לבירור בביטוח.';
    if (!(await wait(360, token))) return false;
    return finish(token, finalData, state.mode, true);
  };

  trigger.addEventListener('click', event => {
    if (event.defaultPrevented || (typeof event.button === 'number' && event.button !== 0) || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    state.interacted = true;
    state.autoEligible = false;
    run('surprise');
  });
  if (homePlan.dataset.funnelBound !== 'true') {
    homePlan.dataset.funnelBound = 'true';
    homePlan.addEventListener('click', event => {
      if (event.defaultPrevented || (typeof event.button === 'number' && event.button !== 0)) return;
      const componentLink = event.target?.closest?.('[data-home-plan-component]');
      if (componentLink && componentLink.getAttribute('aria-disabled') !== 'true') {
        emitHomeFunnelEvent('component_open', {
          component: componentLink.dataset.homePlanComponent,
          destination: homePlan.dataset.destination
        });
        return;
      }
      const fullPlanLink = event.target?.closest?.('[data-home-plan-full]');
      if (fullPlanLink && fullPlanLink.getAttribute('aria-disabled') !== 'true') {
        emitHomeFunnelEvent('full_plan_open', { destination: homePlan.dataset.destination });
      }
    });
  }
  cancelButton.addEventListener('click', () => {
    state.interacted = true;
    state.autoEligible = false;
    cancel();
    globe.focus();
  });
  globe.addEventListener('pointerdown', () => {
    state.interacted = true;
    state.autoEligible = false;
    if (state.running) cancel({ message: 'הסיבוב נעצר. בחרו יעד ישירות על הגלובוס.' });
  });
  globe.addEventListener('keydown', event => {
    state.interacted = true;
    state.autoEligible = false;
    const globeControlKeys = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'Enter', ' ', '+', '-', '=', '_'];
    if (!state.running || (event.key !== 'Escape' && !globeControlKeys.includes(event.key))) return;
    if (event.key === 'Escape') event.preventDefault();
    cancel({ message: 'הסיבוב נעצר. המשיכו לשלוט בגלובוס מהמקלדת.' });
  }, true);

  const cancelAutomaticReveal = event => {
    if (event?.type !== 'keydown' && event?.target?.closest?.('[data-home-reveal-cancel]')) return;
    state.interacted = true;
    state.autoEligible = false;
    if (state.autoTimer) window.clearTimeout(state.autoTimer);
    state.autoTimer = 0;
    if (state.running) cancel({ message: 'הפעולה נעצרה. המשיכו מהבחירה שלכם.' });
  };
  document.addEventListener('pointerdown', cancelAutomaticReveal, { capture: true, passive: true });
  const ignoredIntentKeys = new Set(['Alt', 'AltGraph', 'CapsLock', 'Control', 'Meta', 'NumLock', 'ScrollLock', 'Shift']);
  document.addEventListener('keydown', event => {
    if (!ignoredIntentKeys.has(event.key)) cancelAutomaticReveal(event);
  }, true);
  document.addEventListener('focusin', cancelAutomaticReveal, true);
  document.addEventListener('visibilitychange', event => {
    if (document.visibilityState === 'hidden') cancelAutomaticReveal(event);
  });
  const searchForm = document.querySelector('[data-home-search]');
  searchForm?.addEventListener('input', cancelAutomaticReveal);
  searchForm?.addEventListener('change', cancelAutomaticReveal);
  window.addEventListener('scroll', event => {
    if (Math.abs(window.scrollY || 0) > 8) cancelAutomaticReveal(event);
  }, { passive: true });

  if (state.autoEligible && campaignKind && document.visibilityState !== 'hidden') {
    state.autoTimer = window.setTimeout(() => {
      state.autoTimer = 0;
      if (state.autoStarted || state.interacted || !state.autoEligible || document.visibilityState === 'hidden') return;
      state.autoStarted = true;
      run(campaignKind);
    }, reducedMotion() ? 0 : 280);
  }

  return {
    run,
    cancel: options => cancel(options),
    state
  };
}

function initDestinationPlan() {
  const plan = document.querySelector('[data-destination-plan]');
  if (!plan) return;
  plan.addEventListener('click', event => {
    const disabledLink = event.target.closest('a[aria-disabled="true"]');
    if (disabledLink) event.preventDefault();
  });
  plan.querySelectorAll('[data-plan-intent]').forEach(button => button.addEventListener('click', () => {
    discoveryDestinationLocked = true;
    activePlanIntent = destinationPlanIntents[button.dataset.planIntent] ? button.dataset.planIntent : 'smart';
    activeRouteSelectionLocked = false;
    discoveryQuery = { ...discoveryQuery, ...(destinationPlanIntentConstraints[activePlanIntent] || {}) };
    syncDiscoveryControls();
    updateDestinationPlan(destinationData[activeDestination], false);
    syncDiscoveryUrl('push');
    hydrateDiscovery(discoveryRequestParams());
  }));
  const saveButton = plan.querySelector('[data-plan-save]');
  saveButton?.addEventListener('click', () => {
    if (saveButton.disabled || discoveryRequestPending) return;
    const data = destinationData[activeDestination];
    if (!data) {
      showWorkspaceToast('בחרו יעד מזוהה לפני שמירת התוכנית', 'map-pin');
      return;
    }
    saveWorkspaceItem(mapDestinationWorkspaceItem(data), saveButton);
  });
}

function appendTextElement(parent, tag, text, className = '') {
  const element = document.createElement(tag);
  if (className) element.className = className;
  element.textContent = text;
  parent.append(element);
  return element;
}

const workspaceLocalKey = 'traVelV2.workspace.v1';
const workspaceDeletedLocalKey = 'traVelV2.workspace.deleted.v1';
let travelerWorkspace = null;
let activeWorkspaceFilter = 'all';
let workspaceLocalStorageAvailable = true;
let workspaceDeletionTombstones = new Set();
let workspaceLocalMutationGeneration = 0;
let workspaceAccountSyncInFlight = false;
let workspaceAccountAuthRequired = false;
let workspaceCorrectiveSyncTimer = 0;
let workspaceCorrectiveSyncAttempts = 0;
let workspaceStorageListenerInstalled = false;
let workspacePreferencesDirty = false;
let workspacePreferencesEditGeneration = 0;
const workspaceItemMutationRegistry = new Map();
const workspaceQuoteCaseMutationRegistry = new Map();
const workspaceQuoteCaseRetryKeys = new Map();
const workspaceAssistedProposalRuntime = new Map();
const workspaceAssistedProposalMutations = new Map();
const workspaceAssistedProposalRetryKeys = new Map();
const workspaceCorrectiveSyncMaximumAttempts = 3;
const sharedRequestDeadlineMilliseconds = 15000;
const workspaceAssistedProposalDisclosure = 'Final price, availability, and terms are provided only after revalidation in a personal quote.';
const workspaceAssistedProposalContactConsent = Object.freeze({
  contract_version: '1.0.0',
  consent_version: '2026-07-19',
  affirmed: true,
  purpose: 'assisted_proposal_follow_up',
  channels: Object.freeze(['email']),
  controller_scope: 'tra_vel',
  recipient_scope: 'tra_vel_assistance_team',
  contact_target: 'account_email'
});
const workspaceAssistedProposalContactNotice = 'בלחיצה על „אשרו פנייה במייל”, אתם מאשרים לצוות הסיוע של Tra‑Vel לפנות לכתובת הדוא״ל המאומתת בחשבון, רק לצורך טיפול בהצעה זו. האישור אינו מאפשר שיווק או שיתוף עם ספקים, ואינו מבצע הזמנה או חיוב.';
const workspaceAssistedProposalCategories = [
  {key: 'flights', label: 'טיסות', icon: 'plane'},
  {key: 'accommodation', label: 'לינה', icon: 'bed-double'},
  {key: 'transfers', label: 'העברות', icon: 'car-front'},
  {key: 'activities', label: 'פעילויות', icon: 'ticket'},
  {key: 'dining', label: 'אוכל', icon: 'utensils'},
  {key: 'insurance', label: 'ביטוח', icon: 'shield-check'},
  {key: 'connectivity', label: 'תקשורת', icon: 'wifi'},
  {key: 'equipment', label: 'ציוד', icon: 'luggage'}
];
const workspaceQuoteCaseRuntime = {
  timer: 0,
  failures: 0,
  inFlight: false,
  controller: null,
  snapshot: new Map(),
  cases: new Map(),
  reconcileWaiters: [],
  authRequired: false
};
const workspacePlanRuntime = {
  timer: 0,
  failures: 0,
  inFlight: false,
  controller: null,
  snapshot: new Map(),
  runs: new Map(),
  hasLoaded: false,
  authRequired: false
};
const workspacePlanStatuses = new Set(['created', 'provider_error', 'needs_clarification', 'request_ready', 'searching', 'proposal_ready', 'approval_required', 'completed', 'failed', 'cancelled']);
const workspacePlanAttentionStatuses = new Set(['provider_error', 'needs_clarification']);
const workspacePlanTerminalStatuses = new Set(['completed', 'failed', 'cancelled']);

function defaultLocalWorkspace() {
  return {
    version: 1,
    items: [],
    preferences: {home_airport: 'TLV', currency: 'USD', budget: 0, max_stops: 1, party_style: 'couple', priorities: ['price', 'comfort']},
    meta: {storage: 'browser_local', max_items: 50, price_watch_delivery_enabled: false, sensitive_data_allowed: false}
  };
}

function normalizeWorkspaceItemId(value) {
  const itemId = String(value || '');
  return /^(destination|route|flight|hotel|package):[A-Za-z0-9._:-]{1,60}$/.test(itemId) ? itemId : '';
}

function readWorkspaceDeletionTombstones() {
  try {
    const parsed = JSON.parse(window.localStorage.getItem(workspaceDeletedLocalKey) || '[]');
    workspaceLocalStorageAvailable = true;
    return new Set((Array.isArray(parsed) ? parsed : []).map(normalizeWorkspaceItemId).filter(Boolean).slice(0, 50));
  } catch (error) {
    workspaceLocalStorageAvailable = false;
    console.warn(error);
    return new Set();
  }
}

function writeWorkspaceDeletionTombstones(tombstones = workspaceDeletionTombstones) {
  const normalized = [...tombstones].map(normalizeWorkspaceItemId).filter(Boolean).slice(0, 50);
  const previousTombstones = new Set(workspaceDeletionTombstones);
  try {
    window.localStorage.setItem(workspaceDeletedLocalKey, JSON.stringify(normalized));
    workspaceDeletionTombstones = new Set(normalized);
    workspaceLocalMutationGeneration += 1;
    workspaceLocalStorageAvailable = true;
    return true;
  } catch (error) {
    workspaceDeletionTombstones = previousTombstones;
    workspaceLocalStorageAvailable = false;
    console.warn(error);
    return false;
  }
}

function rememberWorkspaceDeletion(itemId) {
  const normalized = normalizeWorkspaceItemId(itemId);
  if (!normalized) return false;
  if (!workspaceDeletionTombstones.has(normalized) && workspaceDeletionTombstones.size >= 50) return false;
  const next = new Set(workspaceDeletionTombstones);
  next.add(normalized);
  return writeWorkspaceDeletionTombstones(next);
}

function forgetWorkspaceDeletion(itemId) {
  const normalized = normalizeWorkspaceItemId(itemId);
  if (!normalized || !workspaceDeletionTombstones.has(normalized)) return true;
  const next = new Set(workspaceDeletionTombstones);
  next.delete(normalized);
  return writeWorkspaceDeletionTombstones(next);
}

function readLocalWorkspace() {
  try {
    const parsed = JSON.parse(window.localStorage.getItem(workspaceLocalKey) || 'null');
    workspaceLocalStorageAvailable = true;
    if (!parsed || parsed.version !== 1 || !Array.isArray(parsed.items)) return defaultLocalWorkspace();
    return {
      ...defaultLocalWorkspace(),
      ...parsed,
      items: parsed.items
        .map(normalizeBrowserWorkspaceItem)
        .filter(item => item.external_id && item.title && !workspaceDeletionTombstones.has(item.id))
        .slice(0, 50),
      preferences: {...defaultLocalWorkspace().preferences, ...(parsed.preferences || {})}
    };
  } catch (error) {
    workspaceLocalStorageAvailable = false;
    console.warn(error);
    return defaultLocalWorkspace();
  }
}

function writeLocalWorkspace(workspace) {
  try {
    window.localStorage.setItem(workspaceLocalKey, JSON.stringify(workspace));
    travelerWorkspace = workspace;
    workspaceLocalMutationGeneration += 1;
    workspaceLocalStorageAvailable = true;
    return true;
  } catch (error) {
    workspaceLocalStorageAvailable = false;
    console.warn(error);
    return false;
  }
}

function workspaceLocalSyncSnapshot(workspace = travelerWorkspace) {
  const snapshot = {
    generation: workspaceLocalMutationGeneration,
    memory_workspace: JSON.stringify(workspace || null),
    memory_tombstones: JSON.stringify([...workspaceDeletionTombstones].sort()),
    stored_workspace: '',
    stored_tombstones: '',
    storage_readable: true
  };
  try {
    snapshot.stored_workspace = window.localStorage.getItem(workspaceLocalKey) || '';
    snapshot.stored_tombstones = window.localStorage.getItem(workspaceDeletedLocalKey) || '';
  } catch (error) {
    snapshot.storage_readable = false;
  }
  return snapshot;
}

function workspaceLocalSyncSnapshotMatches(left, right) {
  return left.generation === right.generation
    && left.memory_workspace === right.memory_workspace
    && left.memory_tombstones === right.memory_tombstones
    && left.storage_readable === right.storage_readable
    && left.stored_workspace === right.stored_workspace
    && left.stored_tombstones === right.stored_tombstones;
}

function mergeTravelerWorkspaces(local, server, deletedIds = workspaceDeletionTombstones) {
  if (!server) return local;
  const tombstones = deletedIds instanceof Set ? deletedIds : new Set((deletedIds || []).map(normalizeWorkspaceItemId).filter(Boolean));
  const byId = new Map([...(local.items || []), ...(server.items || [])]
    .map(normalizeBrowserWorkspaceItem)
    .filter(item => item.external_id && item.title && !tombstones.has(item.id))
    .map(item => [item.id, item]));
  return {...local, ...server, items: Array.from(byId.values()).slice(0, 50)};
}

function applyServerConfirmedWorkspace(localWorkspace, serverWorkspace, preferredItemId = '', mutationSnapshot = null) {
  const currentWorkspace = travelerWorkspace || localWorkspace || readLocalWorkspace();
  const currentSnapshot = workspaceLocalSyncSnapshot(currentWorkspace);
  if (!mutationSnapshot || !workspaceLocalSyncSnapshotMatches(mutationSnapshot, currentSnapshot)) {
    const sameTabChanged = Boolean(mutationSnapshot)
      && (mutationSnapshot.generation !== currentSnapshot.generation
        || mutationSnapshot.memory_workspace !== currentSnapshot.memory_workspace
        || mutationSnapshot.memory_tombstones !== currentSnapshot.memory_tombstones);
    if (mutationSnapshot && !sameTabChanged) {
      workspaceDeletionTombstones = readWorkspaceDeletionTombstones();
      travelerWorkspace = readLocalWorkspace();
    }
    const correctiveScheduled = scheduleWorkspaceCorrectiveSync(250, true);
    if (document.querySelector('[data-traveler-workspace]')) renderWorkspaceDashboard(preferredItemId);
    refreshMapSaveControls();
    return {
      workspace: travelerWorkspace || currentWorkspace,
      devicePersisted: true,
      localChanged: true,
      correctiveScheduled
    };
  }
  const mergedWorkspace = mergeTravelerWorkspaces(localWorkspace, serverWorkspace);
  const devicePersisted = writeLocalWorkspace(mergedWorkspace);
  if (!devicePersisted) travelerWorkspace = mergedWorkspace;
  if (document.querySelector('[data-traveler-workspace]')) renderWorkspaceDashboard(preferredItemId);
  refreshMapSaveControls();
  return {workspace: mergedWorkspace, devicePersisted, localChanged: false, correctiveScheduled: false};
}

async function workspaceRequest(path = '', options = {}) {
  const endpoint = window.traVelV2?.workspaceUrl;
  if (!endpoint || !window.traVelV2?.isLoggedIn) return null;
  const response = await fetch(`${endpoint}${path}`, {
    ...options,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-WP-Nonce': window.traVelV2?.nonce || '',
      ...(options.headers || {})
    }
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(payload.message || `Workspace request failed: ${response.status}`);
    error.status = response.status;
    error.code = typeof payload.code === 'string' ? payload.code : '';
    throw error;
  }
  return payload;
}

async function requestWithDeadline(executor, timeoutCode = 'request_timeout') {
  const controller = typeof AbortController === 'function' ? new AbortController() : null;
  let timeoutId = 0;
  let timedOut = false;
  const timeoutPromise = new Promise((resolve, reject) => {
    timeoutId = window.setTimeout(() => {
      timedOut = true;
      controller?.abort();
      const error = new Error('Request deadline exceeded.');
      error.name = 'TimeoutError';
      error.code = timeoutCode;
      error.status = 0;
      error.timedOut = true;
      reject(error);
    }, sharedRequestDeadlineMilliseconds);
  });
  try {
    return await Promise.race([
      Promise.resolve().then(() => executor(controller?.signal)),
      timeoutPromise
    ]);
  } catch (error) {
    if (timedOut && error?.code !== timeoutCode) {
      const timeoutError = new Error('Request deadline exceeded.');
      timeoutError.name = 'TimeoutError';
      timeoutError.code = timeoutCode;
      timeoutError.status = 0;
      timeoutError.timedOut = true;
      throw timeoutError;
    }
    throw error;
  } finally {
    if (timeoutId) window.clearTimeout(timeoutId);
  }
}

function workspaceMutationRequest(path = '', options = {}) {
  return requestWithDeadline(
    signal => workspaceRequest(path, {...options, ...(signal ? {signal} : {})}),
    'workspace_request_timeout'
  );
}

function workspaceRequestTimedOut(error) {
  return error?.code === 'workspace_request_timeout' || error?.timedOut === true;
}

function workspaceCapacityError(error) {
  return ['tra_vel_workspace_capacity', 'tra_vel_workspace_sync_capacity'].includes(error?.code);
}

function workspaceAuthenticationRequired(error) {
  return [401, 403].includes(Number(error?.status));
}

function requireWorkspaceReauthentication(message = 'החיבור לחשבון פג. השמירות נשארו במכשיר. התחברו מחדש או רעננו לפני ניסיון סנכרון נוסף.') {
  workspaceAccountAuthRequired = true;
  workspaceCorrectiveSyncAttempts = 0;
  if (workspaceCorrectiveSyncTimer) {
    window.clearTimeout(workspaceCorrectiveSyncTimer);
    workspaceCorrectiveSyncTimer = 0;
  }
  setWorkspaceAccountSyncState('reauth_required', message);
}

function commercialDataMode(payload = {}) {
  const mode = payload?.meta?.data_mode;
  return ['live', 'mixed', 'demo'].includes(mode) ? mode : 'demo';
}

function commercialCacheStateCurrent(payload = {}) {
  const cacheState = String(payload?.meta?.cache_state || '').trim().toLowerCase();
  return !['stale_refreshing', 'stale_error', 'degraded_fallback'].includes(cacheState);
}

function commercialSellerReady(payload, commerce = {}) {
  const provider = String(commerce?.provider || '').trim().toLowerCase();
  return commercialDataMode(payload) === 'live'
    && commercialCacheStateCurrent(payload)
    && provider !== ''
    && provider !== 'demo'
    && (commerce?.bookable === true || commerce?.purchasable === true);
}

function commercialPriceText(payload, formatted) {
  const value = String(formatted || '').trim();
  if (!value) return 'בהצעה האישית';
  return value;
}

function commercialDataNotice(payload = {}) {
  const mode = commercialDataMode(payload);
  if (mode === 'live') return 'הנתונים התקבלו מהספק. המחיר והזמינות ייבדקו שוב לפני מעבר לספק.';
  if (mode === 'mixed') return 'מחיר לתכנון המבוסס בחלקו על נתוני ספק. המחיר, הזמינות והתנאים מאומתים לפני התשלום.';
  return 'מחיר לתכנון והשוואה. המחיר, הזמינות והתנאים מאומתים לפני התשלום.';
}

const productPlannerDestinationAliases = {
  BUD: 'budapest', PRG: 'prague', VIE: 'vienna', ATH: 'athens',
  DXB: 'dubai', BKK: 'bangkok', HND: 'tokyo', NRT: 'tokyo', LIS: 'lisbon'
};

function commercialResponseError(response, payload = {}, fallback = 'Commercial search failed.') {
  const error = new Error(payload?.message || fallback);
  error.status = Number(response?.status) || 0;
  error.code = String(payload?.code || 'commercial_search_failed');
  return error;
}

function commercialSearchNeedsPersonalCheck(error) {
  const code = String(error?.code || '').toLowerCase();
  return Number(error?.status) === 422 || /unsupported|no[_-]?results?|not[_-]?covered/.test(code);
}

function commercialSearchResultNeedsPersonalCheck(payload = {}) {
  return Number(payload?.meta?.result_count) === 0;
}

function experiencePlannerField(form, name) {
  return String(form?.elements?.[name]?.value || '').trim();
}

function experiencePersonalCheckUrl(form, product, base = '') {
  const normalizedProduct = product === 'travel-insurance' ? 'insurance' : product;
  const rawDestination = experiencePlannerField(form, 'destination').toUpperCase();
  const destination = productPlannerDestinationAliases[rawDestination]
    || String(form?.dataset?.tripDestination || rawDestination).trim().toLowerCase();
  const params = {
    product: normalizedProduct,
    scope: normalizedProduct === 'flights' ? 'flights'
      : (normalizedProduct === 'hotels' ? 'accommodation'
        : (normalizedProduct === 'insurance' ? 'insurance' : fullTripPlanningScope)),
    destination,
    origin: ['flights', 'packages'].includes(normalizedProduct) ? experiencePlannerField(form, 'origin').toUpperCase() : '',
    departure_date: experiencePlannerField(form, normalizedProduct === 'hotels' ? 'checkin' : (normalizedProduct === 'insurance' ? 'start_date' : 'departure_date')),
    return_date: experiencePlannerField(form, normalizedProduct === 'hotels' ? 'checkout' : (normalizedProduct === 'insurance' ? 'end_date' : 'return_date')),
    adults: experiencePlannerField(form, 'adults'),
    children: experiencePlannerField(form, 'children'),
    rooms: ['hotels', 'packages'].includes(normalizedProduct) ? experiencePlannerField(form, 'rooms') : '',
    intent: normalizedProduct === 'insurance'
      ? ({family: 'family', adventure: 'adventure', winter: 'adventure'}[experiencePlannerField(form, 'trip_type')] || 'smart')
      : ''
  };
  return destinationPlanUrl(base || '/ai-planner/', params);
}

function setExperiencePersonalCheck(form, visible, product) {
  const link = document.querySelector('[data-experience-personal-check]');
  if (!link) return;
  const productKind = product || link.dataset.product || '';
  link.href = experiencePersonalCheckUrl(form, productKind, link.dataset.plannerBase || '/ai-planner/');
  link.hidden = !visible;
}

function appendCommercialDataNotice(parent, payload) {
  const notice = appendTextElement(parent, 'p', commercialDataNotice(payload), 'commercial-data-notice');
  notice.dataset.dataMode = commercialDataMode(payload);
  return notice;
}

const commercialIntentMutationRegistry = new Map();

function boundedCommercialString(value, maximumLength = 160) {
  return String(value ?? '')
    .replace(/[\u0000-\u001f\u007f]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, maximumLength);
}

function boundedCommercialInteger(value, fallback, minimum, maximum) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) return fallback;
  return Math.max(minimum, Math.min(maximum, Math.floor(numeric)));
}

function normalizedCommercialDate(...values) {
  for (const item of values) {
    const value = boundedCommercialString(item, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) continue;
    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    if (date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day) return value;
  }
  return '';
}

function normalizedCommercialOfferId(value) {
  return boundedCommercialString(value || 'search', 80).replace(/[^A-Za-z0-9._:-]/g, '') || 'search';
}

function normalizedCommercialCandidate(candidate = {}, commerce = {}, payload = {}) {
  const priceScope = boundedCommercialString(candidate.price_scope || commercialDataMode(payload), 40)
    .toLowerCase()
    .replace(/[^a-z0-9_-]/g, '') || commercialDataMode(payload);
  return {
    id: normalizedCommercialOfferId(candidate.id || commerce.id),
    title: boundedCommercialString(candidate.title || candidate.label || candidate.name || '', 120),
    subtitle: boundedCommercialString(candidate.subtitle || '', 180),
    commercial_ref: boundedCommercialString(candidate.commercial_ref || commerce.commercial_ref || commerce.reference || '', 100).replace(/[^A-Za-z0-9._:-]/g, ''),
    price_scope: priceScope
  };
}

const acquisitionStorageKey = 'traVelAcquisition';
const acquisitionUtmFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
const leadContactConsentVersion = '2026-07-19';

function boundedAcquisitionField(value) {
  return String(value == null ? '' : value).trim().slice(0, 120);
}

/**
 * Record first-touch acquisition once per browser. The record is written only
 * when the landing URL carries campaign parameters or the visit arrived from
 * another host, and an existing record always wins so later visits cannot
 * rewrite the original attribution.
 */
function captureAcquisition() {
  try {
    if (window.localStorage.getItem(acquisitionStorageKey)) return;
    const params = new URLSearchParams(window.location.search);
    const hasCampaignParams = acquisitionUtmFields.some(field => boundedAcquisitionField(params.get(field)) !== '')
      || boundedAcquisitionField(params.get('gclid')) !== ''
      || boundedAcquisitionField(params.get('fbclid')) !== '';
    let referrerHost = '';
    try {
      const referrer = String(document.referrer || '');
      if (referrer) {
        const host = new URL(referrer).hostname.toLowerCase();
        if (host && host !== String(window.location.hostname || '').toLowerCase()) referrerHost = boundedAcquisitionField(host);
      }
    } catch (error) {
      referrerHost = '';
    }
    if (!hasCampaignParams && !referrerHost) return;
    const record = {};
    for (const field of acquisitionUtmFields) {
      const value = boundedAcquisitionField(params.get(field));
      if (value) record[field] = value;
    }
    const landingPath = boundedAcquisitionField(window.location.pathname);
    if (landingPath.startsWith('/')) record.landing_path = landingPath;
    if (referrerHost) record.referrer_host = referrerHost;
    record.first_seen_at = boundedAcquisitionField(new Date().toISOString());
    window.localStorage.setItem(acquisitionStorageKey, JSON.stringify(record));
  } catch (error) {
    // First-touch attribution is optional and must never block the page.
  }
}

/** Read the bounded first-touch acquisition record, or null when absent. */
function readAcquisition() {
  try {
    const parsed = JSON.parse(window.localStorage.getItem(acquisitionStorageKey) || 'null');
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return null;
    const record = {};
    for (const field of [...acquisitionUtmFields, 'landing_path', 'referrer_host', 'first_seen_at']) {
      const value = boundedAcquisitionField(parsed[field]);
      if (value) record[field] = value;
    }
    return Object.keys(record).length ? record : null;
  } catch (error) {
    return null;
  }
}

/**
 * Normalize an Israeli phone number to +972 form. Accepts mobile and landline
 * numbers with optional +972/972 prefixes, spaces, and dashes; anything else
 * returns an empty string so an invalid number is never stored.
 */
function normalizedIsraeliPhone(raw) {
  const value = String(raw || '').trim();
  if (!/^[+0-9][0-9 -]{6,20}$/.test(value)) return '';
  const compact = value.replace(/[ -]/g, '');
  let local = compact;
  if (local.startsWith('+972')) local = `0${local.slice(4)}`;
  else if (local.startsWith('972')) local = `0${local.slice(3)}`;
  if (!/^0(?:[23489][0-9]{7}|[57][0-9]{8})$/.test(local)) return '';
  return `+972${local.slice(1)}`;
}

/**
 * Build the inline contact step shown before an assisted WhatsApp handoff.
 * The step renders inside the existing card or panel, never as a browser
 * modal, and resolves through exactly one of its save or skip actions.
 */
function buildLeadContactStep({onSave, onSkip}) {
  const stepId = `lead-contact-${createAgentClientRequestId().replace(/[^A-Za-z0-9-]/g, '').slice(0, 24)}`;
  const step = document.createElement('section');
  step.className = 'lead-contact-step';
  step.dataset.leadContactStep = '';
  step.setAttribute('aria-labelledby', `${stepId}-title`);

  const heading = document.createElement('h4');
  heading.id = `${stepId}-title`;
  heading.tabIndex = -1;
  heading.textContent = 'רוצים שנחזור אליכם גם אם השיחה מתנתקת?';
  step.append(heading);
  appendTextElement(step, 'p', 'הפרטים נשמרים אצל Tra-Vel בלבד ומשמשים רק לחזרה אליכם על הבקשה הזו.', 'lead-contact-intro');

  const fields = document.createElement('div');
  fields.className = 'lead-contact-fields';
  const buildField = (key, labelText, type, autocomplete) => {
    const field = document.createElement('label');
    field.className = 'lead-contact-field';
    field.htmlFor = `${stepId}-${key}`;
    appendTextElement(field, 'span', labelText);
    const input = document.createElement('input');
    input.type = type;
    input.id = `${stepId}-${key}`;
    input.name = `lead_contact_${key}`;
    input.autocomplete = autocomplete;
    input.maxLength = 80;
    if (type === 'tel') {
      input.inputMode = 'tel';
      input.dir = 'ltr';
    }
    field.append(input);
    fields.append(field);
    return input;
  };
  const nameInput = buildField('name', 'שם', 'text', 'name');
  const phoneInput = buildField('phone', 'טלפון', 'tel', 'tel');
  step.append(fields);

  const consent = document.createElement('label');
  consent.className = 'lead-contact-consent';
  consent.htmlFor = `${stepId}-consent`;
  const consentInput = document.createElement('input');
  consentInput.type = 'checkbox';
  consentInput.id = `${stepId}-consent`;
  consent.append(consentInput);
  const consentCopy = document.createElement('span');
  consentCopy.append(document.createTextNode('אני מאשר/ת ל-Tra-Vel לשמור את הפרטים וליצור קשר לגבי הבקשה הזו. '));
  const privacyLink = document.createElement('a');
  privacyLink.href = '/privacy-policy/';
  privacyLink.target = '_blank';
  privacyLink.rel = 'noopener';
  privacyLink.textContent = 'מדיניות הפרטיות';
  consentCopy.append(privacyLink);
  consent.append(consentCopy);
  step.append(consent);

  const error = document.createElement('p');
  error.className = 'lead-contact-error';
  error.setAttribute('role', 'alert');
  error.hidden = true;
  step.append(error);

  const showLeadContactError = (message, input) => {
    error.textContent = message;
    error.hidden = false;
    if (input) {
      input.setAttribute('aria-invalid', 'true');
      input.focus?.();
    }
  };
  const clearLeadContactError = () => {
    error.textContent = '';
    error.hidden = true;
    nameInput.removeAttribute('aria-invalid');
    phoneInput.removeAttribute('aria-invalid');
  };

  const actions = document.createElement('div');
  actions.className = 'lead-contact-actions';
  const save = document.createElement('button');
  save.type = 'button';
  save.className = 'lead-contact-save';
  save.dataset.leadContactSave = '';
  save.textContent = 'שמרו והמשיכו בוואטסאפ';
  const skip = document.createElement('button');
  skip.type = 'button';
  skip.className = 'lead-contact-skip';
  skip.dataset.leadContactSkip = '';
  skip.textContent = 'המשיכו בלי להשאיר פרטים';
  actions.append(save, skip);
  step.append(actions);

  const setBusy = busy => {
    save.disabled = busy;
    skip.disabled = busy;
    if (busy) step.setAttribute('aria-busy', 'true');
    else step.removeAttribute('aria-busy');
  };

  save.addEventListener('click', () => {
    clearLeadContactError();
    const phone = normalizedIsraeliPhone(phoneInput.value);
    if (!phone) {
      showLeadContactError('בדקו את מספר הטלפון. אפשר להזין מספר ישראלי נייד או קווי.', phoneInput);
      return;
    }
    if (!consentInput.checked) {
      showLeadContactError('כדי שנשמור את הפרטים ונחזור אליכם, סמנו את אישור שמירת הפרטים.', consentInput);
      return;
    }
    const name = String(nameInput.value || '').trim().slice(0, 80);
    const contact = {
      ...(name ? {name} : {}),
      phone,
      consent: true,
      consent_version: leadContactConsentVersion
    };
    onSave(contact, {setBusy, showError: message => { setBusy(false); showLeadContactError(message); }, step});
  });
  skip.addEventListener('click', () => onSkip({setBusy, step}));

  step.focusHeading = () => heading.focus?.();
  return step;
}

function commercialIntentBaseUrl() {
  const configured = boundedCommercialString(window.traVelV2?.commercialIntentUrl || '', 500);
  if (!configured) return '';
  try {
    const endpoint = new URL(configured, window.location.origin);
    if (endpoint.protocol !== 'https:' || endpoint.origin !== window.location.origin) return '';
    endpoint.hash = '';
    endpoint.search = '';
    return endpoint.href.replace(/\/+$/, '');
  } catch (error) {
    return '';
  }
}

function safeCommercialHandoffUrl(value) {
  try {
    const handoffUrl = new URL(boundedCommercialString(value, 2000));
    if (handoffUrl.protocol !== 'https:' || handoffUrl.username || handoffUrl.password) return '';
    return handoffUrl.href;
  } catch (error) {
    return '';
  }
}

async function commercialIntentMutation(endpoint, body, timeoutCode) {
  return requestWithDeadline(async signal => {
    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'Cache-Control': 'no-store',
        'X-WP-Nonce': window.traVelV2?.nonce || ''
      },
      body: JSON.stringify(body),
      ...(signal ? {signal} : {})
    });
    const responseBody = await response.json().catch(() => ({}));
    if (!response.ok) {
      const requestError = new Error(responseBody.message || `Commercial intent request failed: ${response.status}`);
      requestError.status = response.status;
      requestError.code = typeof responseBody.code === 'string' ? responseBody.code : 'commercial_intent_request_failed';
      requestError.currentVersion = boundedCommercialInteger(responseBody?.data?.current_version ?? responseBody?.current_version, 0, 0, 1000000000);
      throw requestError;
    }
    return responseBody;
  }, timeoutCode);
}

function normalizedCommercialIntent(response = {}) {
  const source = response?.intent || response;
  const intentId = boundedCommercialString(source?.intent_id || '', 80);
  const version = boundedCommercialInteger(source?.version, 0, 0, 1000000000);
  if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(intentId) || version < 1) return null;
  return {
    intent_id: intentId,
    version,
    reference: boundedCommercialString(source?.reference || '', 120)
  };
}

function commercialIntentRegistryEntry(vertical, commerce, payload, candidate) {
  const trip = commercialHandoffContext(vertical, commerce, payload);
  const normalizedCandidate = normalizedCommercialCandidate(candidate, commerce, payload);
  const offerId = normalizedCommercialOfferId(commerce?.id || normalizedCandidate.id);
  const registryKey = JSON.stringify([vertical, offerId, normalizedCandidate.commercial_ref, trip]);
  let entry = commercialIntentMutationRegistry.get(registryKey);
  if (!entry) {
    const mutationId = createAgentClientRequestId();
    entry = {
      createKey: `commercial-create-${mutationId}`,
      handoffKey: `commercial-handoff-${mutationId}`,
      intent: null,
      inFlight: null,
      contact: null,
      contactKey: '',
      contactSaved: false,
      contactDeclined: false
    };
    commercialIntentMutationRegistry.set(registryKey, entry);
  }
  return {entry, trip, candidate: normalizedCandidate, offerId};
}

function rotateCommercialHandoffKey(entry) {
  if (!entry) return '';
  const previous = String(entry.handoffKey || '');
  let next = `commercial-handoff-${createAgentClientRequestId()}`;
  if (next === previous) next = `${next}-${Date.now().toString(36)}`;
  entry.handoffKey = next.slice(0, 100);
  return entry.handoffKey;
}

function commercialIntentCreateBody(vertical, commerce, payload, state, contact = null) {
  const sellerReady = commercialSellerReady(payload, commerce);
  const requestedProvider = sellerReady
    ? boundedCommercialString(commerce?.provider || '', 40).toLowerCase().replace(/[^a-z0-9_-]/g, '')
    : 'tra-vel-concierge';
  const acquisition = readAcquisition();
  // A consented contact travels under its own operation key: the contact
  // changes the server-side idempotency digest, so reusing the plain create
  // key would conflict with an earlier contact-free write of the same scope.
  return {
    idempotency_key: contact ? state.entry.contactKey : state.entry.createKey,
    vertical: boundedCommercialString(vertical, 30).toLowerCase().replace(/[^a-z0-9_-]/g, ''),
    surface: `${boundedCommercialString(vertical, 30).toLowerCase().replace(/[^a-z0-9_-]/g, '')}_results`,
    data_mode: commercialDataMode(payload),
    requested_provider: requestedProvider || 'tra-vel-concierge',
    offer_id: state.offerId,
    candidate: state.candidate,
    trip: state.trip,
    ...(acquisition ? {acquisition} : {}),
    ...(contact ? {contact} : {})
  };
}

function configureCommercialAction(button, vertical, commerce, payload, candidate = {}) {
  if (!button) return;
  const sellerReady = commercialSellerReady(payload, commerce);
  const currentLabels = {
    flight: 'בדקו מחיר והמשיכו לספק',
    hotel: 'בדקו זמינות והמשיכו לספק',
    package: 'בדקו מחיר והמשיכו לספק',
    insurance: 'בדקו את ההצעה אצל המבטח'
  };
  const assistedLabels = {
    flight: 'בקשו בדיקת מחיר לטיסה',
    hotel: 'בקשו בדיקת מלון',
    package: 'בקשו בדיקת חופשה',
    insurance: 'בקשו בדיקת ביטוח'
  };
  button.classList.toggle('is-assisted', !sellerReady);
  button.textContent = sellerReady
    ? (currentLabels[vertical] || 'בדקו מחיר והמשיכו לספק')
    : (assistedLabels[vertical] || 'שלחו את האפשרות לבדיקה');
  button.addEventListener('click', () => openCommercialContactStep(button, vertical, {...commerce, bookable: sellerReady, purchasable: sellerReady}, payload, candidate));
}

/**
 * Show the inline callback-contact step before the assisted WhatsApp handoff.
 * Saving attaches the consented contact to the durable commercial intent;
 * skipping continues exactly like the pre-1.23.0 flow. Either choice is
 * remembered per result card so a retry never asks twice.
 */
function openCommercialContactStep(button, vertical, commerce, payload, candidate = {}) {
  if (!button) return false;
  // The step copy promises a WhatsApp continuation, so it appears only on the
  // assisted-sales path. A proven live seller handoff continues unchanged.
  if (commercialSellerReady(payload, commerce)) {
    return startCommercialHandoff(button, vertical, commerce, payload, candidate);
  }
  const state = commercialIntentRegistryEntry(vertical, commerce, payload, candidate);
  if (state.entry.inFlight) return state.entry.inFlight;
  if (state.entry.contact || state.entry.contactSaved || state.entry.contactDeclined) {
    return startCommercialHandoff(button, vertical, commerce, payload, candidate);
  }
  const host = button.parentElement || button;
  const existing = host.querySelector?.('[data-lead-contact-step]');
  if (existing) {
    existing.focusHeading?.();
    return false;
  }
  const step = buildLeadContactStep({
    onSave: contact => {
      state.entry.contact = contact;
      if (!state.entry.contactKey) state.entry.contactKey = `commercial-contact-${createAgentClientRequestId()}`.slice(0, 100);
      step.remove?.();
      button.focus?.();
      startCommercialHandoff(button, vertical, commerce, payload, candidate);
    },
    onSkip: () => {
      state.entry.contactDeclined = true;
      step.remove?.();
      button.focus?.();
      startCommercialHandoff(button, vertical, commerce, payload, candidate);
    }
  });
  if (button.insertAdjacentElement) button.insertAdjacentElement('afterend', step);
  else host.append?.(step);
  step.focusHeading?.();
  return false;
}

function commercialHandoffContext(vertical, commerce, payload = {}) {
  const query = payload.query || payload.search || {};
  const calculation = payload.calculation || {};
  const adults = boundedCommercialInteger(query.adults ?? payload.trip?.adults, 1, 1, 20);
  const children = boundedCommercialInteger(query.children ?? payload.trip?.children, 0, 0, 20);
  const infants = boundedCommercialInteger(query.infants ?? payload.trip?.infants, 0, 0, 10);
  const rooms = boundedCommercialInteger(query.rooms ?? payload.trip?.rooms, 1, 1, 10);
  const verticalLabels = {
    flight: 'טיסה', hotel: 'מלון', package: 'טיסה ומלון', insurance: 'ביטוח נסיעות'
  };
  const suppliedTravelers = boundedCommercialInteger(payload.trip?.travelers ?? calculation.travelers, 0, 0, 20);
  const travelers = Math.max(1, Math.min(20, Math.max(adults + children + infants, suppliedTravelers)));
  const budgetValue = Number(query.budget ?? query.max_total ?? payload.trip?.budget ?? 0);
  const budget = Number.isFinite(budgetValue) ? Math.max(0, Math.min(1000000, Math.floor(budgetValue))) : 0;
  const currencyValue = boundedCommercialString(query.currency || payload.trip?.currency || '', 3).toUpperCase();
  return {
    origin: boundedCommercialString(payload.origin?.code || query.origin || payload.trip?.origin || 'TLV', 80),
    destination: boundedCommercialString(payload.destination?.city || payload.destination?.name || payload.destination?.code || query.destination || payload.trip?.destination || '', 80),
    depart_date: normalizedCommercialDate(query.departure_date, query.depart_date, query.checkin, query.check_in, query.start_date, payload.trip?.depart_date),
    return_date: normalizedCommercialDate(query.return_date, query.checkout, query.check_out, query.end_date, payload.trip?.return_date),
    adults,
    children,
    infants,
    travelers,
    rooms,
    budget,
    currency: ['ILS', 'USD', 'EUR', 'GBP'].includes(currencyValue) ? currencyValue : 'ILS',
    return_path: boundedCommercialString(`${window.location.pathname}${window.location.search}`, 200)
  };
}

async function startCommercialHandoff(button, vertical, commerce, payload, candidate = {}) {
  const endpoint = commercialIntentBaseUrl();
  if (!endpoint || !button) return false;
  const state = commercialIntentRegistryEntry(vertical, commerce, payload, candidate);
  if (state.entry.inFlight) return state.entry.inFlight;
  let resolveInFlight;
  state.entry.inFlight = new Promise(resolve => { resolveInFlight = resolve; });
  const originalText = button.textContent;
  button.disabled = true;
  button.dataset.handoffState = 'loading';
  button.setAttribute('aria-busy', 'true');
  button.textContent = 'פותחים את בקשת הבדיקה...';
  try {
    const contact = state.entry.contact && !state.entry.contactSaved ? state.entry.contact : null;
    if (!state.entry.intent) {
      const createResponse = await commercialIntentMutation(
        endpoint,
        commercialIntentCreateBody(vertical, commerce, payload, state, contact),
        'commercial_intent_create_timeout'
      );
      state.entry.intent = normalizedCommercialIntent(createResponse);
      if (!state.entry.intent) throw new Error('Commercial intent response is invalid.');
      if (contact) state.entry.contactSaved = true;
    } else if (contact) {
      // The 0.9.0 API has no intent-update route. Re-posting the same safe
      // scope with the consented contact resumes the existing durable intent
      // and the server explicitly attaches the contact to it, so consent
      // given after the intent was first created is never silently dropped.
      const attachResponse = await commercialIntentMutation(
        endpoint,
        commercialIntentCreateBody(vertical, commerce, payload, state, contact),
        'commercial_intent_create_timeout'
      );
      const attachedIntent = normalizedCommercialIntent(attachResponse);
      if (attachedIntent) state.entry.intent = attachedIntent;
      state.entry.contactSaved = true;
    }
    const handoff = await commercialIntentMutation(
      `${endpoint}/${encodeURIComponent(state.entry.intent.intent_id)}/handoffs`,
      {
        expected_version: state.entry.intent.version,
        idempotency_key: state.entry.handoffKey
      },
      'commercial_intent_handoff_timeout'
    );
    const updatedIntent = normalizedCommercialIntent(handoff);
    const safeHandoffUrl = safeCommercialHandoffUrl(handoff.handoff_url);
    if (!safeHandoffUrl) throw new Error('Commercial handoff URL is invalid.');
    if (updatedIntent) state.entry.intent = updatedIntent;
    button.dataset.handoffState = 'ready';
    const providerLabel = boundedCommercialString(typeof handoff.provider === 'string' ? handoff.provider : handoff.provider?.label, 80);
    const assistedHandoff = boundedCommercialString(handoff.conversion_type, 40) === 'assisted_quote';
    button.textContent = assistedHandoff || !commercialSellerReady(payload, commerce)
      ? 'פותחים שיחה עם Tra-Vel'
      : (providerLabel ? `ממשיכים אל ${providerLabel}` : 'ממשיכים לספק');
    // A confirmed response ends the ambiguity window. A later user action may
    // create a new handoff event, so it must receive a fresh operation key.
    rotateCommercialHandoffKey(state.entry);
    window.location.assign(safeHandoffUrl);
  } catch (error) {
    if (error?.code === 'tra_vel_commercial_handoff_replay_expired' && error.currentVersion > 0 && state.entry.intent) {
      state.entry.intent = {...state.entry.intent, version: error.currentVersion};
      rotateCommercialHandoffKey(state.entry);
    }
    button.disabled = false;
    button.dataset.handoffState = 'error';
    button.textContent = 'לא הצלחנו לפתוח את הבקשה. נסו שוב';
    window.setTimeout(() => {
      if (button.dataset.handoffState === 'error') button.textContent = originalText;
    }, 4500);
    console.warn(error);
  } finally {
    button.removeAttribute('aria-busy');
    resolveInFlight?.();
    state.entry.inFlight = null;
  }
}

function showWorkspaceToast(message, icon = 'heart', anchor = null) {
  let toast = document.querySelector('[data-workspace-toast]');
  if (!toast) {
    toast = document.createElement('div');
    toast.className = 'workspace-toast';
    toast.dataset.workspaceToast = '';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.setAttribute('aria-atomic', 'true');
  }
  const anchoredCard = anchor?.closest?.('.flight-offer, .hotel-offer, .trip-package-card');
  const host = anchoredCard
    || document.querySelector('[data-traveler-workspace] .workspace-command-main')
    || document.querySelector('.theme-map-shell .map-main-column')
    || anchor?.parentElement
    || document.body;
  if (toast.parentElement !== host) host.append(toast);
  toast.replaceChildren();
  const iconElement = document.createElement('i');
  iconElement.dataset.lucide = icon;
  toast.append(iconElement);
  appendTextElement(toast, 'span', message);
  toast.classList.add('is-visible');
  renderIcons();
  window.clearTimeout(showWorkspaceToast.timeout);
  showWorkspaceToast.timeout = window.setTimeout(() => toast.classList.remove('is-visible'), 3200);
}

function normalizeWorkspaceItem(item) {
  const externalId = String(item.external_id || '').replace(/[^A-Za-z0-9._:-]/g, '').slice(0, 60);
  const kind = ['destination', 'route', 'flight', 'hotel', 'package'].includes(item.kind) ? item.kind : 'destination';
  const requestedDataMode = ['demo', 'mixed', 'live', 'editorial'].includes(item.data_mode) ? item.data_mode : 'demo';
  return {
    id: `${kind}:${externalId}`,
    kind,
    external_id: externalId,
    title: String(item.title || '').slice(0, 160),
    subtitle: String(item.subtitle || '').slice(0, 240),
    destination: String(item.destination || '').slice(0, 80),
    route: String(item.route || '').slice(0, 160),
    price_label: String(item.price_label || '').slice(0, 40),
    price_amount: Math.max(0, Math.min(1000000, Number(item.price_amount) || 0)),
    currency: ['USD', 'EUR', 'ILS'].includes(item.currency) ? item.currency : 'USD',
    data_mode: requestedDataMode === 'live' ? 'mixed' : requestedDataMode,
    href: String(item.href || '/'),
    saved_at: new Date().toISOString(),
    watch: {enabled: false, target_amount: 0, delivery_enabled: false, status: 'off'}
  };
}

function normalizeBrowserWorkspaceItem(rawItem) {
  const source = rawItem && typeof rawItem === 'object' && !Array.isArray(rawItem) ? rawItem : {};
  const normalized = normalizeWorkspaceItem(source);
  const watch = source.watch && typeof source.watch === 'object' && !Array.isArray(source.watch) ? source.watch : {};
  const savedAt = typeof source.saved_at === 'string' && !Number.isNaN(new Date(source.saved_at).getTime())
    ? source.saved_at
    : normalized.saved_at;
  return {
    ...normalized,
    saved_at: savedAt,
    watch: {
      enabled: watch.enabled === true,
      target_amount: Math.max(0, Math.min(1000000, Number(watch.target_amount) || 0)),
      delivery_enabled: watch.delivery_enabled === true,
      status: ['off', 'awaiting_live_supplier', 'active', 'paused'].includes(watch.status) ? watch.status : 'off'
    }
  };
}

function isWorkspaceItemSaved(itemId) {
  return (travelerWorkspace || readLocalWorkspace()).items.some(item => item.id === itemId);
}

function refreshMapSaveControls() {
  const data = destinationData[activeDestination];
  if (!data) return;
  const saved = isWorkspaceItemSaved(normalizeWorkspaceItem(mapDestinationWorkspaceItem(data)).id);
  document.querySelectorAll('[data-map-result] .save-button').forEach(button => {
    button.classList.toggle('is-saved', saved);
    button.setAttribute('aria-label', saved ? 'נשמר לנסיעה' : `שמירת ${data.city} לנסיעה`);
  });
}

async function saveWorkspaceItem(rawItem, button) {
  const item = normalizeWorkspaceItem(rawItem);
  const notify = (message, icon = 'heart') => showWorkspaceToast(message, icon, button);
  if (!item.external_id || !item.title) return {localSaved: false, accountSynced: false};
  const workspace = travelerWorkspace || readLocalWorkspace();
  const existing = workspace.items.find(saved => saved.id === item.id);
  if (!existing && workspace.items.length >= 50) {
    notify('אפשר לשמור עד 50 אפשרויות במכשיר. הסירו אפשרות אחת ואז שמרו את האפשרות החדשה.', 'list-x');
    return {localSaved: false, accountSynced: false, reason: 'local_capacity'};
  }
  if (existing?.watch) item.watch = existing.watch;
  const nextWorkspace = {
    ...workspace,
    items: [item, ...workspace.items.filter(saved => saved.id !== item.id)].slice(0, 50)
  };
  if (!writeLocalWorkspace(nextWorkspace)) {
    notify('לא הצלחנו לשמור בדפדפן. בדקו שהאחסון המקומי זמין ונסו שוב.', 'triangle-alert');
    return {localSaved: false, accountSynced: false};
  }
  if (!forgetWorkspaceDeletion(item.id)) {
    if (!writeLocalWorkspace(workspace)) travelerWorkspace = workspace;
    notify('לא הצלחנו לבטל את סימון המחיקה המקומי. השמירה בוטלה ולא נשלחה כדי למנוע שחזור מצב שגוי.', 'triangle-alert');
    return {localSaved: false, accountSynced: false};
  }
  button?.classList.add('is-saved');
  if (button) {
    const label = button.querySelector('span');
    if (label) label.textContent = 'נשמר לנסיעה';
    button.setAttribute('aria-label', 'נשמר לנסיעה');
  }
  if (!window.traVelV2?.isLoggedIn) {
    notify('נשמר באופן פרטי במכשיר הזה', 'heart');
    return {localSaved: true, accountSynced: false};
  }
  if (workspaceAccountAuthRequired) {
    notify('נשמר במכשיר. התחברו מחדש או רעננו כדי לסנכרן לחשבון.', 'log-in');
    return {localSaved:true,accountSynced:false,reason:'reauth_required',correctiveScheduled:false};
  }
  const mutationSnapshot = workspaceLocalSyncSnapshot(travelerWorkspace || nextWorkspace);
  notify('נשמר במכשיר. בודקים סנכרון לחשבון...', 'cloud');
  try {
    const serverWorkspace = await workspaceMutationRequest('/items', {method: 'POST', body: JSON.stringify(item)});
    if (serverWorkspace) {
      const confirmation = applyServerConfirmedWorkspace(nextWorkspace, serverWorkspace, item.id, mutationSnapshot);
      if (confirmation.localChanged) {
        notify(
          confirmation.correctiveScheduled
            ? 'השמירה בחשבון התקבלה, אך המכשיר השתנה בינתיים. השינוי החדש נשמר ומתבצע סנכרון תיקון מוגבל.'
            : 'השמירה בחשבון התקבלה, אך המכשיר השתנה בינתיים. השינוי החדש נשמר; רעננו כדי לסנכרן.',
          'refresh-cw'
        );
        return {localSaved:true,accountSynced:false,reason:'local_changed',correctiveScheduled:confirmation.correctiveScheduled};
      }
      if (confirmation.devicePersisted) {
        notify('השמירה אושרה בחשבון ובמכשיר', 'cloud-check');
        return {localSaved: true, accountSynced: true, devicePersisted: true};
      }
      notify('השמירה אושרה בחשבון ומוצגת בלשונית הזאת, אך העדכון לא נשמר במכשיר.', 'cloud-off');
      return {localSaved: false, accountSynced: true, devicePersisted: false};
    }
    const correctiveScheduled = scheduleWorkspaceCorrectiveSync(500, true);
    notify(
      correctiveScheduled
        ? 'נשמר במכשיר; מתבצע ניסיון סנכרון מוגבל לחשבון.'
        : 'נשמר במכשיר; סנכרון החשבון אינו זמין. רעננו כדי לנסות שוב.',
      'cloud-off'
    );
  } catch (error) {
    if (workspaceAuthenticationRequired(error)) {
      requireWorkspaceReauthentication();
      notify('נשמר במכשיר. החיבור לחשבון פג, לכן לא יתבצעו ניסיונות נוספים עד שתתחברו מחדש או תרעננו.', 'log-in');
      return {localSaved:true,accountSynced:false,reason:'reauth_required',correctiveScheduled:false};
    }
    const correctiveScheduled = !workspaceCapacityError(error) && scheduleWorkspaceCorrectiveSync(500, true);
    notify(
      workspaceCapacityError(error)
        ? 'החשבון מלא: אפשר לשמור עד 50 אפשרויות. הסירו אפשרות אחת מהחשבון ואז שמרו שוב.'
        : workspaceRequestTimedOut(error)
          ? correctiveScheduled
            ? 'הבדיקה מול החשבון נמשכה יותר מ־15 שניות. השמירה במכשיר נשמרה ומתבצע ניסיון תיקון מוגבל.'
            : 'הבדיקה מול החשבון נמשכה יותר מ־15 שניות. השמירה במכשיר נשמרה; רעננו כדי לבדוק את מצב החשבון.'
          : correctiveScheduled
            ? 'נשמר במכשיר; מתבצע ניסיון סנכרון מוגבל לחשבון.'
            : 'נשמר במכשיר; השינוי בחשבון לא אושר. רעננו כדי לנסות שוב.',
      workspaceCapacityError(error) ? 'list-x' : 'cloud-off'
    );
    console.warn(error);
    return {localSaved:true,accountSynced:false,reason:workspaceRequestTimedOut(error) ? 'timeout' : 'account_unconfirmed',correctiveScheduled};
  }
  return {localSaved: true, accountSynced: false};
}

function createSaveOfferButton(item) {
  const normalized = normalizeWorkspaceItem(item);
  const button = document.createElement('button');
  button.type = 'button';
  button.className = `save-offer-button${isWorkspaceItemSaved(normalized.id) ? ' is-saved' : ''}`;
  const icon = document.createElement('i');
  icon.dataset.lucide = 'heart';
  button.append(icon);
  appendTextElement(button, 'span', isWorkspaceItemSaved(normalized.id) ? 'נשמר לנסיעה' : 'שמרו לנסיעה');
  button.addEventListener('click', () => saveWorkspaceItem(item, button));
  return button;
}

function mapDestinationWorkspaceItem(data) {
  const selectedRoute = discoveryRoutes.find(route => route.id === activeRouteId && (!route.destination_id || route.destination_id === data.id));
  const currentDeal = !discoveryRequestPending && discoverySnapshotIsCurrent() && discoveryLiveLayers.deals;
  const entityContext = activeMapEntitySelection?.destinationId === data.id
    && activePlanningSelection?.selection_id === activeMapEntitySelection.selectionId
    ? activeMapEntitySelection
    : null;
  const selectedEntity = entityContext ? discoveryMapEntities.find(entity => entity.id === entityContext.entityId) : null;
  const currentEntityPrice = Boolean(selectedEntity?.price && entityContext?.dataMode === 'live'
    && entityContext?.truthState === 'supplier_snapshot' && entityContext?.freshness === 'current'
    && !discoveryRequestPending && discoverySnapshotIsCurrent());
  const externalId = entityContext ? entityContext.selectionId.slice(0, 60) : data.id;
  const entityKind = entityContext ? (mapEntityKindLabels[entityContext.kind] || 'נקודה במפה') : '';
  return {
    kind: 'destination',
    external_id: externalId,
    title: entityContext ? `${data.city}: ${entityContext.label}` : `${data.city}, ${data.country}`,
    subtitle: entityContext
      ? `${entityKind} · נוסף לתוכנית ופתוח לעריכה · תוכנית ${destinationPlanIntents[activePlanIntent]?.label || 'חכמה'}`
      : `${selectedRoute?.label || data.airportCode || 'יעד'} · ${data.hotelArea || 'אזור לינה לבחירה'} · תוכנית ${destinationPlanIntents[activePlanIntent]?.label || 'חכמה'}`,
    destination: data.city,
    route: selectedRoute ? `TLV → ${selectedRoute.label}` : `TLV → ${data.airportCode || data.city}`,
    price_label: currentEntityPrice ? selectedEntity.price.formatted : (entityContext ? 'מחיר בהצעה האישית' : (currentDeal ? data.total : (data.price || 'בהצעה האישית'))),
    price_amount: currentEntityPrice ? selectedEntity.price.amount : (entityContext ? 0 : (currentDeal ? data.totalAmount : 0)),
    currency: data.currency || 'USD',
    data_mode: currentEntityPrice ? 'live' : (entityContext ? 'editorial' : (currentDeal ? 'live' : 'editorial')),
    href: entityContext
      ? destinationPlanUrl('/travel-map/', { ...activePlanningSelectionHandoffQuery(data.id), destination: data.id, layer: entityContext.layer, intent: activePlanIntent })
      : (data.url || destinationPlanUrl('/destinations/', { destination: data.id }))
  };
}

function renderRoutes(routes, recommendedId = '') {
  syncGlobeDiveStoreRoutes();
  const list = document.querySelector('[data-route-list]');
  if (!list) return;
  list.replaceChildren();
  if (!routes.length) {
    activeRouteId = '';
    activeRouteSelectionLocked = false;
    appendTextElement(list, 'p', 'עדיין אין השוואת טיסות מלאה ליעד הזה. המשיכו לחיפוש לפי תאריכים ונוסעים.', 'route-empty');
    return;
  }
  const recommendedRouteId = routes.some(route => route.id === recommendedId) ? recommendedId : '';
  if (!activeRouteSelectionLocked && recommendedRouteId) activeRouteId = recommendedRouteId;
  if (!routes.some(route => route.id === activeRouteId)) activeRouteId = recommendedRouteId || routes[0].id;
  routes.forEach((route, index) => {
    const hasRouteSnapshot = discoveryLiveLayers.airports;
    const hasRouteTotalSnapshot = discoveryLiveLayers.routeTotal;
    const hasLiveRouteData = discoverySnapshotIsCurrent() && hasRouteSnapshot;
    const hasLiveRouteTotal = discoverySnapshotIsCurrent() && hasRouteTotalSnapshot;
    const hasStaleRouteData = discoverySnapshotIsStale() && hasRouteSnapshot;
    const hasStaleRouteTotal = discoverySnapshotIsStale() && hasRouteTotalSnapshot;
    const planningRoutePrice = route.costs?.total_formatted || 'בהצעה האישית';
    const routePrice = hasLiveRouteTotal
      ? route.costs.total_formatted
      : (hasStaleRouteTotal ? `${route.costs.total_formatted} · המחיר האחרון שנבדק` : planningRoutePrice);
    const showRouteSnapshot = hasLiveRouteData || hasStaleRouteData;
    const button = document.createElement('button');
    button.className = `mini-route${route.id === activeRouteId ? ' is-selected' : ''}`;
    button.type = 'button';
    button.dataset.route = route.id;
    button.dataset.freshness = hasStaleRouteData ? 'stale' : (hasLiveRouteData ? 'current' : 'editorial');
    button.dataset.routeSummary = `${route.label} · ${routePrice}`;
    button.setAttribute('aria-pressed', String(route.id === activeRouteId));
    appendTextElement(button, 'small', showRouteSnapshot ? route.badge : 'מסלול לתכנון');
    appendTextElement(button, 'strong', `${route.label} · ${route.duration_label}`);
    const stopsLabel = route.stops === 0 ? 'ישיר' : (route.stops === 1 ? 'עצירה אחת' : `${route.stops} עצירות`);
    appendTextElement(button, 'span', `${stopsLabel} · ${route.ticket_mode === 'single' ? 'כרטיס אחד' : 'כרטיסים נפרדים'}${showRouteSnapshot ? '' : ' · לתכנון'}`);
    const tradeoffs = document.createElement('div');
    tradeoffs.className = 'route-tradeoffs';
    appendTextElement(tradeoffs, 'span', showRouteSnapshot ? `✓ ${route.pros[0]}` : '✓ משווים זמן, נוחות וגמישות', 'route-pro');
    appendTextElement(tradeoffs, 'span', showRouteSnapshot ? `△ ${route.cons[0]}` : '△ מחיר ותנאים דורשים בדיקה לפי תאריכים', 'route-con');
    button.append(tradeoffs);
    appendTextElement(button, 'b', routePrice);
    appendTextElement(button, 'em', hasLiveRouteTotal ? 'עלות המסלול לפי הפרטים שנבדקו' : (hasStaleRouteTotal ? 'המחיר האחרון שנבדק · בדקו שוב' : 'מחיר לתכנון · בדיקה לפני רכישה'));
    button.addEventListener('click', () => selectRoute(button));
    list.append(button);
  });
}

function setRouteListBusy(busy) {
  const list = document.querySelector('[data-route-list]');
  if (!list) return;
  list.setAttribute('aria-busy', String(busy));
  if (busy) {
    list.replaceChildren();
    appendTextElement(list, 'p', 'מעדכנים את השוואת המסלולים ליעד ולבחירות החדשות...', 'route-empty');
    const routeStatus = document.querySelector('[data-route-status]');
    if (routeStatus) routeStatus.textContent = '';
  }
  list.querySelectorAll('button').forEach(button => { button.disabled = busy; });
}

function selectRoute(card) {
  document.querySelectorAll('[data-route]').forEach(item => {
    item.classList.remove('is-selected');
    item.setAttribute('aria-pressed', 'false');
  });
  card.classList.add('is-selected');
  card.setAttribute('aria-pressed', 'true');
  activeRouteId = card.dataset.route || '';
  activeRouteSelectionLocked = true;
  const summary = document.querySelector('[data-route-summary]');
  if (summary) summary.textContent = card.dataset.routeSummary || 'המסלול נבחר';
  const routeStatus = document.querySelector('[data-route-status]');
  if (routeStatus) routeStatus.textContent = `המסלול נבחר: ${card.dataset.routeSummary || 'אפשרות מסלול'}`;
  const data = destinationData[activeDestination];
  if (data) {
    updateDestinationPlan(data, true, settledDiscoveryResponseState());
  }
  window.traVelGlobe3D?.pulseRoute?.();
}

function setDiscoveryStatus(mode, message, { planWork = true } = {}) {
  discoveryRequestPending = mode === 'loading';
  if (mode === 'loading') updateBudgetCoverageStatus('loading');
  const canvas = document.querySelector('[data-map-canvas]');
  if (canvas) canvas.dataset.dataMode = mode;
  const status = document.querySelector('[data-layer-status]');
  if (status) {
    const allowedStates = new Set(['loading', 'live', 'demo', 'refreshing', 'stale', 'fallback', 'error']);
    const state = allowedStates.has(mode)
      ? mode
      : (mode === 'mixed' && discoveryLiveLayers[activeLayer] ? 'live' : 'demo');
    status.dataset.state = state;
    status.textContent = message;
  }
  const anyCurrentLiveData = discoveryCommercialDataIsCurrent();
  const liveProgressState = !planWork
    ? 'waiting'
    : (mode === 'loading'
    ? 'running'
    : (anyCurrentLiveData && ['live', 'mixed'].includes(mode)
      ? 'confirmed'
      : (['refreshing', 'stale'].includes(mode) ? 'stale' : (['fallback', 'error'].includes(mode) ? 'failed' : 'waiting'))));
  const liveProgressDetail = !planWork
    ? 'בחרו יעד כדי להתחיל בדיקת מחיר וזמינות'
    : (liveProgressState === 'running'
    ? 'מעדכנים אפשרויות לפי הבחירה'
    : (liveProgressState === 'confirmed'
      ? 'מידע עדכני התקבל'
      : (liveProgressState === 'stale' ? 'מוצג מידע קודם ונדרש רענון' : (liveProgressState === 'failed' ? 'הבדיקה נעצרה, אפשר לנסות שוב' : 'נדרשת בדיקת מחיר'))));
  setMapProgressCheckpoint('live', liveProgressState, liveProgressDetail);
  updatePins();
  if (mode === 'loading' && planWork) {
    const pendingData = destinationData[activeDestination];
    if (pendingData) {
      setActiveDestination(activeDestination, null, {
        animate: false,
        responseConfirmed: false,
        responseState: 'pending',
        globeAnimate: false,
        globeAnnounce: false,
        pulseRoute: false
      });
    }
  }
  const plan = document.querySelector('[data-destination-plan]');
  if (plan) {
    plan.dataset.requestState = planWork ? mode : 'waiting-for-destination';
    plan.setAttribute('aria-busy', String(planWork && mode === 'loading'));
    const planState = plan.querySelector('[data-plan-state]');
    if (mode === 'loading' && planWork) {
      plan.classList.remove('is-updating');
      updateDestinationPlanStages(plan, 'pending');
      if (planState) planState.textContent = 'מעדכנים את התוכנית לפי הבחירה שלכם';
      const save = plan.querySelector('[data-plan-save]');
      if (save) save.disabled = true;
    }
  }
  if (mode === 'loading' && planWork) {
    document.querySelectorAll('[data-map-result] .save-button').forEach(button => { button.disabled = true; });
  }
  const homePlan = document.querySelector('[data-home-plan]');
  if (homePlan) {
    homePlan.dataset.requestState = planWork ? mode : 'waiting-for-destination';
    homePlan.setAttribute('aria-busy', String(planWork && mode === 'loading'));
    const homeSummary = homePlan.querySelector('[data-home-plan-summary]');
    if (mode === 'loading' && planWork) {
      homePlan.classList.remove('is-updating');
      if (homeSummary) homeSummary.textContent = 'היעד נבחר. בודקים דרך, לינה, חוויות ותנאים...';
    }
  }
}

function updateWeatherAttribution(providerStatus) {
  const attribution = document.querySelector('[data-weather-attribution]');
  if (!attribution) return;
  const weather = providerStatus?.weather;
  const visible = weather?.connected === true && typeof weather.attribution_url === 'string';
  attribution.hidden = !visible;
  if (visible) {
    attribution.href = weather.attribution_url;
    attribution.textContent = weather.attribution || 'Weather data by Open-Meteo · CC BY 4.0';
  }
}

async function hydrateDiscovery(params = {}, { allowGlobeFocus = true, allowConfirmedMotion = true } = {}) {
  const endpoint = window.traVelV2?.discoveryUrl;
  if (!endpoint || !document.querySelector('[data-discovery-globe] .price-pin[data-destination]')) return;
  const requestParams = discoveryRequestParams(params);
  const openEndedRequest = discoveryDestinationMode === 'anywhere' && !discoveryDestinationLocked && !requestParams.destination;
  const url = new URL(endpoint, window.location.origin);
  Object.entries(requestParams).forEach(([key, value]) => {
    if (value !== '' && value !== undefined && value !== false) url.searchParams.set(key, String(value));
  });
  discoveryRequestController?.abort();
  const controller = typeof AbortController === 'function' ? new AbortController() : null;
  discoveryRequestController = controller;
  const generation = ++discoveryRequestGeneration;
  let timedOut = false;
  const timeoutId = controller ? window.setTimeout(() => {
    timedOut = true;
    controller.abort();
  }, 12000) : 0;
  if (openEndedRequest) {
    renderDiscoveryEmptyState({ reason: 'open' });
    setMapEntityExplorerLoading();
    setDiscoveryStatus('loading', 'מעדכנים את היעדים הזמינים על המפה. בדיקת מחיר ותוכנית תתחיל רק לאחר בחירה.', { planWork: false });
  } else {
    setMapEntityExplorerLoading();
    setDiscoveryStatus('loading', 'מעדכן יעדים ומסלולים...');
    setRouteListBusy(true);
  }
  try {
    const response = await fetch(url, { headers: { Accept: 'application/json' }, ...(controller ? { signal: controller.signal } : {}) });
    if (!response.ok) throw new Error(`Discovery request failed: ${response.status}`);
    const payload = await response.json();
    if (generation !== discoveryRequestGeneration) return;
    discoveryDataMode = payload.meta?.data_mode || 'demo';
    discoveryCacheState = ['fresh', 'miss', 'stale_refreshing', 'stale_error', 'degraded_fallback'].includes(payload.meta?.cache_state)
      ? payload.meta.cache_state
      : 'degraded_fallback';
    discoveryFreshness = ['current', 'refreshing', 'stale', 'fallback'].includes(payload.meta?.freshness)
      ? payload.meta.freshness
      : 'fallback';
    discoveryCacheFreshness = ['current', 'refreshing', 'stale', 'fallback'].includes(payload.meta?.cache_freshness)
      ? payload.meta.cache_freshness
      : 'fallback';
    discoverySourceFreshness = ['not_applicable', 'current', 'future', 'stale', 'unknown'].includes(payload.meta?.source_freshness)
      ? payload.meta.source_freshness
      : 'unknown';
    discoveryBudgetCoverage = ['none', 'partial', 'full'].includes(payload.meta?.filters?.budget_coverage)
      ? payload.meta.filters.budget_coverage
      : 'none';
    discoveryBudgetApplied = payload.meta?.filters?.budget_applied === true;
    discoveryBudgetFilterActive = payload.meta?.filters?.budget_filter_active === true;
    updateBudgetCoverageStatus();
    discoveryFieldProvenance = normalizeFieldProvenance(payload.field_provenance);
    discoveryLiveLayers = resolveDiscoveryLiveLayers(discoveryFieldProvenance);
    discoverySelectedPlan = payload.selected_plan || null;
    destinationData = Object.fromEntries(payload.destinations.map(item => [item.id, normalizeDestination(item)]));
    if (Array.isArray(payload.exploration_hubs)) setExplorationHubData(payload.exploration_hubs);
    const nextMapEntities = normalizeMapEntityCollection(payload.map_entities, activeLayer, destinationData);
    const nextMapSegments = normalizeMapSegmentCollection(payload.map_segments, destinationData);
    updateWeatherAttribution(payload.provider_status);
    updatePins();
    const resolvedDestination = payload.meta?.selected_destination || '';
    const selected = destinationData[resolvedDestination]
      ? resolvedDestination
      : (destinationData[requestParams.destination] ? requestParams.destination : Object.keys(destinationData)[0]);
    const remainOpen = openEndedRequest;
    if (remainOpen) {
      discoveryLiveLayers = { deals: false, hotels: false, airports: false, airportDetails: false, weather: false, routePrices: false, routeTotal: false };
      renderDiscoveryEmptyState({ reason: 'open' });
      syncDiscoveryUrl('replace');
    } else if (selected) {
      discoveryLiveLayers = destinationData[selected]?.liveLayers || resolveDiscoveryLiveLayers(discoveryFieldProvenance, selected);
      discoveryRoutes = resolvedDestination === selected && Array.isArray(payload.routes)
        ? payload.routes.filter(route => route.destination_id === selected)
        : [];
      const responseState = settledDiscoveryResponseState();
      const responseSupportsConfirmedMotion = responseState === 'current' && allowConfirmedMotion;
      const recommendedRouteId = typeof payload.recommended?.id === 'string' ? payload.recommended.id : '';
      renderRoutes(discoveryRoutes, recommendedRouteId);
      setActiveDestination(selected, document.querySelector(`[data-destination="${selected}"]`), {
        animate: responseSupportsConfirmedMotion,
        responseConfirmed: responseSupportsConfirmedMotion,
        responseState,
        globeAnimate: responseSupportsConfirmedMotion,
        globeFocus: allowGlobeFocus,
        globeAnnounce: false,
        pulseRoute: responseSupportsConfirmedMotion
      });
      syncDiscoveryUrl('replace');
    } else {
      renderDiscoveryEmptyState();
      syncDiscoveryUrl('replace');
    }
    renderMapEntityExplorer(nextMapEntities, remainOpen ? [] : nextMapSegments, {
      preferredDestination: selected || '',
      reason: remainOpen ? 'open' : 'empty'
    });
    const layerName = activeLayer === 'deals' && !discoveryLiveLayers.deals
      ? 'יעדים'
      : (payload.layers?.find(layer => layer.id === activeLayer)?.label || 'יעדים');
    const liveModeLabels = { deals: 'מחירים שהתקבלו מהספק', hotels: 'מחירי לינה שהתקבלו מהספק', airports: 'נתוני דרך מעודכנים', weather: 'תנאי מזג אוויר נוכחיים' };
    const verificationLabels = { deals: 'מחירי תכנון מוצגים כעת. מחיר, זמינות ותנאים סופיים יינתנו לאחר בדיקה מחדש, לפני הרכישה', hotels: 'מחירי תכנון עוזרים לבחור אזור וחדר. המחיר, הזמינות והתנאים מאומתים לפני התשלום', airports: 'השוו זמן, עצירות ותנאים. פרטי הטיסה הסופיים יינתנו לאחר בדיקה מחדש, לפני הרכישה', weather: 'התאימו את החופשה לעונה. תחזית מדויקת תוצג סמוך לנסיעה' };
    const modeLabel = remainOpen
      ? 'בחרו יעד כדי לפתוח מחיר, זמינות ותוכנית מלאה'
      : (discoveryFreshness !== 'current'
      ? discoveryFreshnessLabel()
      : (discoveryLiveLayers[activeLayer]
      ? liveModeLabels[activeLayer]
      : verificationLabels[activeLayer]));
    const confirmedState = discoverySnapshotIsStale()
      ? discoveryFreshness
      : (['fallback', 'error'].includes(discoveryDataMode)
      ? discoveryDataMode
      : (discoveryLiveLayers[activeLayer] && discoverySnapshotIsCurrent() ? 'live' : 'demo'));
    const budgetLabel = budgetCoverageLabel();
    setDiscoveryStatus(confirmedState, `${layerName} · ${payload.meta.result_count} יעדים · ${modeLabel}${budgetLabel ? ` · ${budgetLabel}` : ''}`, { planWork: !remainOpen });
  } catch (error) {
    if ((error?.name === 'AbortError' && !timedOut) || generation !== discoveryRequestGeneration) return;
    discoveryDataMode = 'demo';
    discoveryCacheState = 'degraded_fallback';
    discoveryFreshness = 'fallback';
    discoveryCacheFreshness = 'fallback';
    discoverySourceFreshness = 'not_applicable';
    discoveryBudgetCoverage = 'none';
    discoveryBudgetApplied = false;
    discoveryBudgetFilterActive = false;
    discoveryFieldProvenance = normalizeFieldProvenance();
    discoveryLiveLayers = { deals: false, hotels: false, airports: false, airportDetails: false, weather: false, routePrices: false, routeTotal: false };
    discoverySelectedPlan = null;
    destinationData = { ...fallbackDestinations };
    discoveryRoutes = [];
    activeRouteId = '';
    activeRouteSelectionLocked = false;
    updateWeatherAttribution(null);
    updateBudgetCoverageStatus();
    updatePins();
    renderRoutes([]);
    const remainOpen = openEndedRequest;
    const fallbackDestination = remainOpen ? '' : (destinationData[requestParams.destination] ? requestParams.destination : Object.keys(destinationData)[0]);
    if (remainOpen) {
      renderDiscoveryEmptyState({ reason: 'open' });
      syncDiscoveryUrl('replace');
    } else if (fallbackDestination) {
      setActiveDestination(fallbackDestination, document.querySelector(`[data-destination="${fallbackDestination}"]`), {
        animate: false,
        responseConfirmed: false,
        responseState: 'fallback',
        globeAnimate: false,
        globeFocus: allowGlobeFocus,
        globeAnnounce: false,
        pulseRoute: false
      });
      syncDiscoveryUrl('replace');
    }
    resetMapEntityExplorer('error');
    const fallbackDestinationCount = Object.keys(destinationData).length;
    setDiscoveryStatus(remainOpen || fallbackDestination ? 'fallback' : 'error', timedOut ? `העדכון נעצר לפני שהושלם · ${fallbackDestinationCount} יעדים נשארו זמינים לתכנון` : `${fallbackDestinationCount} יעדים זמינים · מחירי תכנון והצעה אישית`, { planWork: !remainOpen });
    console.warn(error);
  } finally {
    if (timeoutId) window.clearTimeout(timeoutId);
    if (generation === discoveryRequestGeneration) {
      setRouteListBusy(false);
      if (discoveryRequestController === controller) discoveryRequestController = null;
    }
  }
}

function flightJourneyRow(label, journey, liveJourney = false) {
  const row = document.createElement('div');
  row.className = 'flight-journey-row';
  appendTextElement(row, 'span', label, 'flight-journey-direction');
  const times = document.createElement('strong');
  times.dir = 'ltr';
  times.textContent = `${journey.departure_time} → ${journey.arrival_time}`;
  row.append(times);
  appendTextElement(row, 'span', `${journey.duration_label} · ${journey.stops_label}${liveJourney ? '' : ' · מסלול לתכנון'}`, 'flight-journey-meta');
  if (journey.via?.length) appendTextElement(row, 'small', `דרך ${journey.via.join(', ')}`);
  return row;
}

function renderFlightOffers(payload) {
  const container = document.querySelector('[data-flight-results]');
  if (!container) return;
  container.replaceChildren();
  if (!payload.offers?.length) {
    appendTextElement(container, 'p', 'לא נמצאו אפשרויות שתואמות למסננים. נסו לאפשר עצירה אחת.', 'flight-empty');
    return;
  }

  payload.offers.forEach((offer, index) => {
    const liveOffer = commercialDataMode(payload) === 'live';
    const card = document.createElement('article');
    card.className = `flight-offer${offer.id === payload.recommended ? ' is-recommended' : ''}${liveOffer ? '' : ' is-planning-only'}`;
    card.dataset.dataMode = commercialDataMode(payload);

    const head = document.createElement('div');
    head.className = 'flight-offer-head';
    const identity = document.createElement('div');
    appendTextElement(identity, 'small', liveOffer ? offer.badge : `${offer.badge} · לתכנון`, 'flight-offer-badge');
    appendTextElement(identity, 'h3', offer.label);
    appendTextElement(identity, 'span', `${offer.airline.name} · ${offer.ticket_mode === 'single' ? 'כרטיס אחד' : 'כרטיסים נפרדים'}${liveOffer ? '' : ' · דוגמת מסלול'}`);
    head.append(identity);
    const score = appendTextElement(head, 'strong', `${offer.score}`, 'flight-score');
    score.setAttribute('aria-label', `${liveOffer ? 'ציון התאמה' : 'ציון התאמה לתכנון'} ${offer.score} מתוך 100`);
    card.append(head);

    const journeys = document.createElement('div');
    journeys.className = 'flight-journeys';
    journeys.append(flightJourneyRow('הלוך', offer.outbound, liveOffer), flightJourneyRow('חזור', offer.inbound, liveOffer));
    card.append(journeys);

    const tradeoffs = document.createElement('div');
    tradeoffs.className = 'flight-tradeoffs';
    appendTextElement(tradeoffs, 'span', `✓ ${offer.pros[0]}`, 'flight-pro');
    appendTextElement(tradeoffs, 'span', `△ ${offer.cons[0]}`, 'flight-con');
    card.append(tradeoffs);

    const totals = document.createElement('div');
    totals.className = 'flight-total-grid';
    const fare = document.createElement('div');
    appendTextElement(fare, 'small', liveOffer ? 'טיסה עם תוספות' : 'מחיר לתכנון, טיסה ותוספות');
    appendTextElement(fare, 'strong', commercialPriceText(payload, offer.fare.total_formatted));
    const trip = document.createElement('div');
    appendTextElement(trip, 'small', liveOffer ? 'עלות החופשה' : 'תקציב חופשה לתכנון');
    appendTextElement(trip, 'strong', commercialPriceText(payload, offer.trip_total.total_formatted));
    totals.append(fare, trip);
    card.append(totals);
    appendCommercialDataNotice(card, payload);

    const details = document.createElement('details');
    appendTextElement(details, 'summary', 'פירוט עלות ותנאים');
    const breakdown = document.createElement('div');
    breakdown.className = 'flight-breakdown';
    [['מחיר בסיס', offer.fare.base_formatted], ['מסים', offer.fare.taxes_formatted], ['כבודה', offer.fare.baggage_formatted], ['מושבים', offer.fare.seats_formatted], ['מלון משוער', offer.trip_total.hotel_formatted], ['ביטוח משוער', offer.trip_total.insurance_formatted]].forEach(([label, value]) => {
      const line = document.createElement('span');
      appendTextElement(line, 'i', label);
      appendTextElement(line, 'b', commercialPriceText(payload, value));
      breakdown.append(line);
    });
    appendTextElement(breakdown, 'p', `${liveOffer ? '' : 'תנאים לתכנון: '}${offer.policies.baggage} · ${offer.policies.changes}`);
    details.append(breakdown);
    card.append(details);

    const action = document.createElement('button');
    action.className = 'flight-offer-action';
    action.type = 'button';
    configureCommercialAction(action, 'flight', {...offer.booking, id: offer.id}, payload, {
      id: offer.id,
      title: offer.label,
      subtitle: offer.airline?.name,
      commercial_ref: offer.commercial_ref || offer.booking?.commercial_ref,
      price_scope: offer.price_scope
    });
    card.append(action, createSaveOfferButton({
      kind: 'flight', external_id: offer.id, title: offer.label,
      subtitle: `${offer.airline.name} · ${offer.outbound.duration_label} · ${offer.outbound.stops_label}${liveOffer ? '' : ' · לתכנון'}`,
      destination: payload.destination?.city || payload.destination?.code || '',
      route: `${payload.origin?.code || 'TLV'} → ${payload.destination?.code || ''}`,
      price_label: commercialPriceText(payload, offer.trip_total.total_formatted), price_amount: liveOffer ? offer.trip_total.total : null,
      currency: payload.query?.currency || 'USD', data_mode: payload.meta?.data_mode || 'demo', href: `${window.traVelV2?.homeUrl || '/'}flights/`
    }));
    container.append(card);
  });
}

async function searchFlights(form) {
  const endpoint = window.traVelV2?.flightSearchUrl;
  const status = document.querySelector('[data-flight-status]');
  const submit = form.querySelector('[type="submit"]');
  if (!endpoint) return;
  const params = new URLSearchParams(new FormData(form));
  if (!params.has('direct')) params.set('direct', 'false');
  const url = new URL(endpoint, window.location.origin);
  params.forEach((value, key) => url.searchParams.set(key, value));
  setExperiencePersonalCheck(form, false, 'flights');
  submit.disabled = true;
  form.dataset.state = 'loading';
  if (status) status.textContent = 'מכין השוואה לפי התאריכים, הנוסעים וההעדפות שבחרתם...';
  try {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    const payload = await response.json();
    if (!response.ok) throw commercialResponseError(response, payload, `Flight search failed: ${response.status}`);
    renderFlightOffers(payload);
    const modeLabels = { live: 'מחירים שנבדקו כעת', mixed: 'מחירי תכנון עם חלק שנבדק', demo: 'מחירי תכנון והשוואה' };
    const cacheLabels = { miss: 'עודכן עכשיו', fresh: 'תוצאה שמורה ועדכנית', stale_refreshing: 'מעדכן ברקע', stale_error: 'תוצאה אחרונה תקינה', degraded_fallback: 'חלק מהתוצאות אינן זמינות כרגע' };
    const freshness = payload.meta.data_mode === 'live' ? ` · ${cacheLabels[payload.meta.cache_state] || 'עודכן'}` : '';
    const noExactResult = commercialSearchResultNeedsPersonalCheck(payload);
    setExperiencePersonalCheck(form, noExactResult, 'flights');
    if (status) status.textContent = noExactResult
      ? 'לא נמצאה כרגע התאמה מדויקת. אותם פרטים מוכנים לבדיקה אישית.'
      : `${payload.meta.result_count} אפשרויות · ${modeLabels[payload.meta.data_mode] || modeLabels.demo}${freshness}`;
    form.dataset.state = payload.meta.data_mode;
  } catch (error) {
    document.querySelector('[data-flight-results]')?.replaceChildren();
    const personalCheckAvailable = commercialSearchNeedsPersonalCheck(error);
    setExperiencePersonalCheck(form, personalCheckAvailable, 'flights');
    if (status) status.textContent = personalCheckAvailable
      ? 'לא נמצאה כרגע התאמה מדויקת. אותם פרטים מוכנים לבדיקה אישית.'
      : 'לא הצלחנו להשלים את ההשוואה. בדקו את התאריכים ונסו שוב.';
    form.dataset.state = 'error';
    console.warn(error);
  } finally {
    submit.disabled = false;
  }
}

function initFlightSearch() {
  const form = document.querySelector('[data-flight-search]');
  if (!form) return;
  const departure = form.querySelector('[name="departure_date"]');
  const returning = form.querySelector('[name="return_date"]');
  departure?.addEventListener('change', () => {
    syncStrictTravelEndDate(departure, returning, 7);
  });
  form.querySelectorAll('[name="origin"], [name="destination"]').forEach(input => input.addEventListener('input', () => {
    input.value = input.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3);
  }));
  form.addEventListener('submit', event => {
    event.preventDefault();
    searchFlights(form);
  });
  if (form.dataset.autoSearch === 'false') {
    const status = document.querySelector('[data-flight-status]');
    if (status) status.textContent = form.dataset.initialStatus || 'היעד נשמר בטופס. התחילו חיפוש כשתרצו לבדוק זמינות.';
  } else {
    searchFlights(form);
  }
}

function setHotelAreaDetail(area, liveArea = false) {
  if (!area) return;
  const name = document.querySelector('[data-hotel-area-name]');
  const profile = document.querySelector('[data-hotel-area-profile]');
  const tradeoff = document.querySelector('[data-hotel-area-tradeoff]');
  if (name) name.textContent = area.name;
  if (profile) profile.textContent = `${area.profile} · ${area.transport}${liveArea ? '' : ' · לתכנון'}`;
  if (tradeoff) tradeoff.textContent = `△ ${area.tradeoff}`;
  replaceChildrenWithSpans(
    document.querySelector('[data-hotel-area-tags]'),
    area.best_for || []
  );
}

function renderHotelAreaMap(payload, form) {
  const pins = document.querySelector('[data-hotel-map-pins]');
  if (!pins) return;
  pins.replaceChildren();
  const selectedArea = form.elements.area.value;
  const recommended = payload.properties?.find(property => property.id === payload.recommended);
  const detailArea = payload.areas.find(area => area.id === selectedArea) || payload.areas.find(area => area.id === recommended?.area_id) || payload.areas[0];
  const liveArea = commercialDataMode(payload) === 'live';
  setHotelAreaDetail(detailArea, liveArea);
  payload.areas.forEach(area => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `hotel-area-pin${area.id === detailArea?.id ? ' is-active' : ''}${area.visible_properties === 0 ? ' is-empty' : ''}`;
    button.dataset.hotelArea = area.id;
    button.style.left = `${area.position.x}%`;
    button.style.top = `${area.position.y}%`;
    button.setAttribute('aria-label', `${area.name}, ${liveArea ? 'מחיר ממוצע' : 'מחיר לתכנון'} €${area.average_nightly} ללילה`);
    appendTextElement(button, 'strong', `${liveArea ? '' : 'מ־'}€${area.average_nightly}`);
    appendTextElement(button, 'span', area.short_name);
    button.addEventListener('click', () => {
      form.elements.area.value = area.id;
      searchHotels(form);
    });
    pins.append(button);
  });
}

function renderHotelProperties(payload) {
  const container = document.querySelector('[data-hotel-results]');
  if (!container) return;
  container.replaceChildren();
  if (!payload.properties?.length) {
    appendTextElement(container, 'p', 'לא נמצאו מלונות שמתאימים לכל המסננים. נסו להסיר מסנן או להציג את כל האזורים.', 'hotel-empty');
    return;
  }
  payload.properties.forEach((property, index) => {
    const liveProperty = commercialDataMode(payload) === 'live';
    const card = document.createElement('article');
    card.className = `hotel-offer${property.id === payload.recommended ? ' is-recommended' : ''}${liveProperty ? '' : ' is-planning-only'}`;
    card.dataset.dataMode = commercialDataMode(payload);
    const media = document.createElement('div');
    media.className = 'hotel-offer-media';
    const image = document.createElement('img');
    image.loading = 'lazy';
    image.alt = `${property.name}, ${property.area?.name || payload.destination.city}`;
    const safeLocalImage = /^[a-z0-9._-]+$/i.test(property.image || '') ? property.image : 'city-budapest.webp';
    image.src = `${window.traVelV2?.assetUrl || ''}${safeLocalImage}`;
    media.append(image);
    appendTextElement(media, 'span', liveProperty ? property.badge : `${property.badge} · לתכנון`, 'hotel-offer-badge');
    const score = appendTextElement(media, 'strong', `${property.score}`, 'hotel-match-score');
    score.setAttribute('aria-label', `${liveProperty ? 'ציון התאמה' : 'ציון התאמה לתכנון'} ${property.score} מתוך 100`);
    card.append(media);

    const body = document.createElement('div');
    body.className = 'hotel-offer-body';
    appendTextElement(body, 'small', `${property.area?.name || ''} · ${'★'.repeat(property.stars)}${liveProperty ? '' : ' · דוגמת לינה'}`, 'hotel-area-line');
    appendTextElement(body, 'h3', property.name);
    appendTextElement(body, 'span', `${property.guest_score}/10 · ${property.review_count.toLocaleString('he-IL')} חוות דעת${liveProperty ? '' : ' בדוגמת התכנון'}`, 'hotel-guest-score');
    const route = document.createElement('div');
    route.className = 'hotel-route-fit';
    appendTextElement(route, 'strong', `${property.location.route_minutes} דק׳ למסלול${liveProperty ? '' : ' בתכנון'}`);
    appendTextElement(route, 'span', property.location.transit);
    body.append(route);
    appendTextElement(body, 'p', `${property.room.name} · ${property.room.size_sqm} מ״ר · עד ${property.room.sleeps} אורחים${liveProperty ? '' : ' · דוגמת חדר'}`, 'hotel-room');

    const tradeoffs = document.createElement('div');
    tradeoffs.className = 'hotel-tradeoffs';
    appendTextElement(tradeoffs, 'span', `✓ ${property.pros[0]}`, 'hotel-pro');
    appendTextElement(tradeoffs, 'span', `△ ${property.cons[0]}`, 'hotel-con');
    body.append(tradeoffs);

    const totals = document.createElement('div');
    totals.className = 'hotel-total-grid';
    const nightly = document.createElement('div');
    appendTextElement(nightly, 'small', liveProperty ? 'ללילה, לחדר' : 'מחיר לתכנון ללילה, לחדר');
    appendTextElement(nightly, 'strong', commercialPriceText(payload, property.pricing.nightly_formatted));
    const stay = document.createElement('div');
    appendTextElement(stay, 'small', `${property.stay_nights} לילות · ${liveProperty ? 'מחיר שהייה' : 'תקציב שהייה לתכנון'}`);
    appendTextElement(stay, 'strong', commercialPriceText(payload, property.pricing.total_stay_formatted));
    totals.append(nightly, stay);
    body.append(totals);
    appendCommercialDataNotice(body, payload);

    const policyChips = document.createElement('div');
    policyChips.className = 'hotel-policy-chips';
    if (property.policies.free_cancellation) appendTextElement(policyChips, 'span', liveProperty ? 'ביטול חינם' : 'ביטול גמיש בתכנון');
    if (property.policies.pay_at_property) appendTextElement(policyChips, 'span', liveProperty ? 'תשלום במקום' : 'תשלום במקום בתכנון');
    if (property.amenities.breakfast) appendTextElement(policyChips, 'span', 'ארוחת בוקר');
    if (property.amenities.family) appendTextElement(policyChips, 'span', 'מתאים למשפחות');
    body.append(policyChips);

    const details = document.createElement('details');
    appendTextElement(details, 'summary', 'מחיר מלא ותנאי החדר');
    const breakdown = document.createElement('div');
    breakdown.className = 'hotel-breakdown';
    [['חדרים ולילות', property.pricing.base_formatted], ['מסים', property.pricing.taxes_formatted], ['עמלות', property.pricing.fees_formatted], ['לאדם בשהייה', property.pricing.per_person_formatted]].forEach(([label, value]) => {
      const line = document.createElement('span');
      appendTextElement(line, 'i', label);
      appendTextElement(line, 'b', commercialPriceText(payload, value));
      breakdown.append(line);
    });
    appendTextElement(breakdown, 'p', `${liveProperty ? '' : 'תנאים לתכנון: '}${property.policies.cancellation_deadline} · כניסה ${property.policies.check_in} · יציאה ${property.policies.check_out}`);
    details.append(breakdown);
    body.append(details);

    const action = document.createElement('button');
    action.className = 'hotel-offer-action';
    action.type = 'button';
    configureCommercialAction(action, 'hotel', {...property.booking, id: property.id}, payload, {
      id: property.id,
      title: property.name,
      subtitle: property.area?.name,
      commercial_ref: property.commercial_ref || property.booking?.commercial_ref,
      price_scope: property.price_scope
    });
    body.append(action, createSaveOfferButton({
      kind: 'hotel', external_id: property.id, title: property.name,
      subtitle: `${property.area?.name || ''} · ${property.stars}★ · ${property.guest_score}/10${liveProperty ? '' : ' · לתכנון'}`,
      destination: payload.destination?.city || '', route: `${property.location.route_minutes} דקות למסלול${liveProperty ? '' : ' בתכנון'}`,
      price_label: commercialPriceText(payload, property.pricing.total_stay_formatted), price_amount: liveProperty ? property.pricing.total_stay : null,
      currency: payload.query?.currency || 'EUR', data_mode: payload.meta?.data_mode || 'demo', href: `${window.traVelV2?.homeUrl || '/'}hotels/`
    }));
    card.append(body);
    container.append(card);
  });
}

async function searchHotels(form) {
  const endpoint = window.traVelV2?.hotelSearchUrl;
  const status = document.querySelector('[data-hotel-status]');
  const submit = form.querySelector('[type="submit"]');
  if (!endpoint) return;
  const params = new URLSearchParams(new FormData(form));
  ['free_cancellation', 'breakfast', 'family'].forEach(name => {
    if (!params.has(name)) params.set(name, 'false');
  });
  const url = new URL(endpoint, window.location.origin);
  params.forEach((value, key) => url.searchParams.set(key, value));
  hotelSearchController?.abort();
  const controller = typeof AbortController === 'function' ? new AbortController() : null;
  hotelSearchController = controller;
  const generation = ++hotelSearchGeneration;
  setExperiencePersonalCheck(form, false, 'hotels');
  submit.disabled = true;
  form.dataset.state = 'loading';
  if (status) status.textContent = 'מכין השוואת אזורים, עלויות ותנאים לפי הפרטים שבחרתם...';
  try {
    const response = await fetch(url, {
      headers: {Accept: 'application/json'},
      ...(controller ? {signal: controller.signal} : {})
    });
    const payload = await response.json();
    if (generation !== hotelSearchGeneration) return;
    if (!response.ok) throw commercialResponseError(response, payload, `Hotel search failed: ${response.status}`);
    renderHotelAreaMap(payload, form);
    renderHotelProperties(payload);
    const modeLabels = {live: 'מחירים שנבדקו כעת', mixed: 'מחירי תכנון עם חלק שנבדק', demo: 'מחירי תכנון והשוואה'};
    const cacheLabels = {miss: 'עודכן עכשיו', fresh: 'תוצאה שמורה ועדכנית', stale_refreshing: 'מעדכן ברקע', stale_error: 'תוצאה אחרונה תקינה', degraded_fallback: 'חלק מהתוצאות אינן זמינות כרגע'};
    const freshness = payload.meta.data_mode === 'live' ? ` · ${cacheLabels[payload.meta.cache_state] || 'עודכן'}` : '';
    const noExactResult = commercialSearchResultNeedsPersonalCheck(payload);
    setExperiencePersonalCheck(form, noExactResult, 'hotels');
    if (status) status.textContent = noExactResult
      ? 'לא נמצאה כרגע התאמה מדויקת. אותם פרטים מוכנים לבדיקה אישית.'
      : `${payload.meta.result_count} מקומות · ${payload.search.nights} לילות · ${modeLabels[payload.meta.data_mode] || modeLabels.demo}${freshness}`;
    form.dataset.state = payload.meta.data_mode;
  } catch (error) {
    if (error?.name === 'AbortError' || generation !== hotelSearchGeneration) return;
    document.querySelector('[data-hotel-results]')?.replaceChildren();
    const personalCheckAvailable = commercialSearchNeedsPersonalCheck(error);
    setExperiencePersonalCheck(form, personalCheckAvailable, 'hotels');
    if (status) status.textContent = personalCheckAvailable
      ? 'לא נמצאה כרגע התאמה מדויקת. אותם פרטים מוכנים לבדיקה אישית.'
      : 'לא הצלחנו להשלים את השוואת המלונות. בדקו את התאריכים ונסו שוב.';
    form.dataset.state = 'error';
    console.warn(error);
  } finally {
    if (generation === hotelSearchGeneration) {
      submit.disabled = false;
      if (hotelSearchController === controller) hotelSearchController = null;
    }
  }
}

function initHotelSearch() {
  const form = document.querySelector('[data-hotel-search]');
  if (!form) return;
  const checkin = form.querySelector('[name="checkin"]');
  const checkout = form.querySelector('[name="checkout"]');
  checkin?.addEventListener('change', () => {
    syncStrictTravelEndDate(checkin, checkout, 4);
  });
  form.querySelector('[name="destination"]')?.addEventListener('input', event => {
    event.target.value = event.target.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3);
  });
  form.addEventListener('submit', event => {
    event.preventDefault();
    form.elements.area.value = '';
    searchHotels(form);
  });
  document.querySelector('[data-hotel-area-reset]')?.addEventListener('click', () => {
    form.elements.area.value = '';
    searchHotels(form);
  });
  if (form.dataset.autoSearch === 'false') {
    const status = document.querySelector('[data-hotel-status]');
    if (status) status.textContent = form.dataset.initialStatus || 'היעד נשמר בטופס. התחילו חיפוש כשתרצו לבדוק זמינות.';
  } else {
    searchHotels(form);
  }
}

const insuranceAddonLabels = {
  baggage: 'כבודה',
  cancellation: 'ביטול וקיצור',
  adventure_sports: 'ספורט אתגרי',
  winter_sports: 'ספורט חורף',
  electronics: 'אלקטרוניקה'
};

function setInsuranceRiskDetail(context) {
  if (!context) return;
  const title = document.querySelector('[data-insurance-risk-title]');
  const note = document.querySelector('[data-insurance-risk-note]');
  if (title) title.textContent = context.title;
  if (note) note.textContent = context.note;
  replaceChildrenWithSpans(
    document.querySelector('[data-insurance-risk-addons]'),
    (context.recommended_addons || []).map(addon => `לבדיקה: ${insuranceAddonLabels[addon] || addon}`)
  );
}

function renderInsuranceRiskMap(payload, form) {
  const pins = document.querySelector('[data-insurance-risk-pins]');
  if (!pins) return;
  pins.replaceChildren();
  const tripType = form.elements.trip_type.value;
  const active = payload.risk_contexts.find(context => context.trip_type === tripType) || payload.risk_contexts[0];
  setInsuranceRiskDetail(active);
  payload.risk_contexts.forEach(context => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `insurance-risk-pin${context.id === active?.id ? ' is-active' : ''}`;
    button.dataset.insuranceRisk = context.id;
    button.style.left = `${context.position.x}%`;
    button.style.top = `${context.position.y}%`;
    button.setAttribute('aria-label', `${context.title}: ${context.note}`);
    appendTextElement(button, 'strong', context.title);
    appendTextElement(button, 'span', (context.recommended_addons || []).map(addon => `לבדיקה: ${insuranceAddonLabels[addon] || addon}`).join(' · '));
    button.addEventListener('click', () => {
      form.elements.trip_type.value = context.trip_type;
      ['adventure_sports', 'winter_sports'].forEach(name => { form.elements[name].checked = false; });
      searchInsuranceQuotes(form);
    });
    pins.append(button);
  });
}

function appendInsuranceCoverageRow(parent, label, value, emphasis = false) {
  const row = document.createElement('div');
  if (emphasis) row.className = 'is-emphasis';
  appendTextElement(row, 'span', label);
  appendTextElement(row, 'strong', value);
  parent.append(row);
}

function insuranceSaleReady(payload = {}) {
  return commercialDataMode(payload) === 'live' && payload?.meta?.regulated_sale_ready === true
    && commercialCacheStateCurrent(payload);
}

function renderInsurancePlanningBoundary(container, payload) {
  const card = document.createElement('article');
  card.className = 'insurance-planning-boundary';
  card.dataset.dataMode = commercialDataMode(payload);
  appendTextElement(card, 'small', 'פרטים לבדיקת כיסוי', 'insurance-plan-badge');
  appendTextElement(card, 'h3', 'הכינו את המידע שהמבטח יצטרך');
  appendTextElement(card, 'p', 'במסך הזה אין המלצה אישית, פוליסה, פרמיה תקפה או אפשרות רכישה. אפשר לארגן את פרטי הנסיעה ולשלוח אותם לבדיקה אצל גורם מורשה.');
  const list = document.createElement('ul');
  [
    `${payload.query?.trip_days || 'מספר'} ימי נסיעה ו${payload.calculation?.travelers || 'מספר'} נוסעים`,
    'גילים, יעד, תאריכים ומצב רפואי למסירה ישירה לגורם המורשה',
    'פעילויות, ספורט, כבודה וציוד שדורשים בירור',
    'ביטול, קיצור נסיעה, חריגים והשתתפות עצמית',
    'נוסח הפוליסה, החיתום והמחיר שקובע המבטח'
  ].forEach(item => appendTextElement(list, 'li', item));
  card.append(list);
  appendCommercialDataNotice(card, payload);
  const action = document.createElement('button');
  action.type = 'button';
  action.className = 'insurance-plan-action is-assisted';
  configureCommercialAction(action, 'insurance', {id: 'insurance-cover-check', purchasable: false}, payload, {
    id: 'insurance-cover-check',
    title: 'Insurance coverage check',
    subtitle: 'Licensed provider review',
    price_scope: 'assisted_quote'
  });
  card.append(action);
  container.append(card);
}

function renderInsurancePlans(payload) {
  const container = document.querySelector('[data-insurance-results]');
  if (!container) return;
  container.replaceChildren();
  if (!payload.plans?.length) {
    appendTextElement(container, 'p', 'לא התקבל מידע שאפשר להשתמש בו לבדיקת כיסוי. בדקו את התאריכים והפרטים.', 'insurance-empty');
    return;
  }
  if (!insuranceSaleReady(payload)) {
    renderInsurancePlanningBoundary(container, payload);
    return;
  }
  payload.plans.forEach(plan => {
    const card = document.createElement('article');
    card.className = `insurance-plan${plan.id === payload.recommended ? ' is-recommended' : ''}`;
    const head = document.createElement('div');
    head.className = 'insurance-plan-head';
    const identity = document.createElement('div');
    appendTextElement(identity, 'small', plan.badge, 'insurance-plan-badge');
    appendTextElement(identity, 'h3', plan.name);
    appendTextElement(identity, 'span', plan.service.availability);
    head.append(identity);
    const score = appendTextElement(head, 'strong', `${plan.score}`, 'insurance-match-score');
    score.setAttribute('aria-label', `ציון התאמה ${plan.score} מתוך 100`);
    card.append(head);

    if (plan.eligibility.status === 'medical_assessment_required') {
      appendTextElement(card, 'p', `! ${plan.eligibility.message}`, 'insurance-underwriting-alert');
    }
    const coverage = document.createElement('div');
    coverage.className = 'insurance-coverage-grid';
    appendInsuranceCoverageRow(coverage, 'הוצאות רפואיות', plan.coverage.medical_limit_formatted, true);
    appendInsuranceCoverageRow(coverage, 'השתתפות עצמית', plan.coverage.medical_deductible_formatted);
    appendInsuranceCoverageRow(coverage, 'איתור וחילוץ', plan.coverage.search_rescue_limit_formatted);
    appendInsuranceCoverageRow(coverage, 'כבודה בבסיס', plan.coverage.baggage_limit ? plan.coverage.baggage_limit_formatted : 'לא כלול');
    appendInsuranceCoverageRow(coverage, 'ביטול בבסיס', plan.coverage.cancellation_limit ? plan.coverage.cancellation_limit_formatted : 'לא כלול');
    card.append(coverage);

    const service = document.createElement('div');
    service.className = 'insurance-service-box';
    appendTextElement(service, 'strong', plan.service.model);
    appendTextElement(service, 'span', `${plan.service.digital_doctor ? 'רופא דיגיטלי' : 'ללא רופא דיגיטלי'} · ${plan.service.payment_coordination ? 'תיאום תשלום' : 'החזר בכפוף למסמכים'}`);
    card.append(service);

    if (plan.requested_addons.length) {
      const addons = document.createElement('div');
      addons.className = 'insurance-selected-addons';
      plan.requested_addons.forEach(addon => {
        appendTextElement(addons, 'span', `${insuranceAddonLabels[addon.id] || addon.id} · ${addon.included ? 'כלול' : `+${addon.estimated_cost_formatted}`}`);
      });
      card.append(addons);
    }

    const tradeoffs = document.createElement('div');
    tradeoffs.className = 'insurance-tradeoffs';
    appendTextElement(tradeoffs, 'span', `✓ ${plan.pros[0]}`, 'insurance-pro');
    appendTextElement(tradeoffs, 'span', `△ ${plan.cons[0]}`, 'insurance-con');
    card.append(tradeoffs);

    const total = document.createElement('div');
    total.className = 'insurance-total';
    const price = document.createElement('div');
    appendTextElement(price, 'small', `${payload.query.trip_days} ימים · ${payload.calculation.travelers} נוסעים`);
    appendTextElement(price, 'strong', plan.pricing.total_trip_formatted);
    appendTextElement(price, 'span', `${plan.pricing.daily_party_formatted} ליום לכל הקבוצה`);
    total.append(price);
    appendTextElement(total, 'em', 'אומדן בלבד, מחיר ותנאים סופיים רק לאחר חיתום המבטח');
    card.append(total);

    const details = document.createElement('details');
    appendTextElement(details, 'summary', 'חריגים, הרחבות ותנאים לבדיקה');
    const list = document.createElement('ul');
    plan.exclusions.forEach(exclusion => appendTextElement(list, 'li', exclusion));
    details.append(list);
    appendTextElement(details, 'p', plan.eligibility.message);
    card.append(details);

    const action = document.createElement('button');
    action.type = 'button';
    action.className = 'insurance-plan-action';
    configureCommercialAction(action, 'insurance', {...plan.purchase, id: plan.id}, payload, {
      id: plan.id,
      title: plan.name,
      subtitle: plan.badge,
      commercial_ref: plan.commercial_ref || plan.purchase?.commercial_ref,
      price_scope: plan.price_scope
    });
    card.append(action);
    container.append(card);
  });
}

async function searchInsuranceQuotes(form) {
  const endpoint = window.traVelV2?.insuranceQuoteUrl;
  const status = document.querySelector('[data-insurance-status]');
  const submit = form.querySelector('[type="submit"]');
  if (!endpoint) return;
  const params = new URLSearchParams(new FormData(form));
  ['baggage', 'cancellation', 'adventure_sports', 'winter_sports', 'electronics', 'medical_condition', 'pregnancy'].forEach(name => {
    if (!params.has(name)) params.set(name, 'false');
  });
  const requestBody = Object.fromEntries(params.entries());
  insuranceSearchController?.abort();
  const controller = typeof AbortController === 'function' ? new AbortController() : null;
  insuranceSearchController = controller;
  const generation = ++insuranceSearchGeneration;
  setExperiencePersonalCheck(form, false, 'insurance');
  submit.disabled = true;
  form.dataset.state = 'loading';
  if (status) status.textContent = 'מארגן את פרטי הנסיעה ואת הנושאים שכדאי לבדוק מול מבטח...';
  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {Accept: 'application/json', 'Content-Type': 'application/json'},
      body: JSON.stringify(requestBody),
      ...(controller ? {signal: controller.signal} : {})
    });
    const payload = await response.json();
    if (generation !== insuranceSearchGeneration) return;
    if (!response.ok) throw commercialResponseError(response, payload, `Insurance comparison failed: ${response.status}`);
    renderInsuranceRiskMap(payload, form);
    renderInsurancePlans(payload);
    const policyNote = document.querySelector('[data-insurance-policy-note]');
    if (policyNote) policyNote.textContent = insuranceSaleReady(payload)
      ? `${payload.destination.medical_cost_context} ${payload.destination.policy_note}`
      : 'אין כאן פוליסה או המלצה אישית. נוסח הפוליסה, החיתום, המחיר והכיסוי שקובע המבטח הם המחייבים.';
    const modeLabels = {live: 'מידע ממבטח מחובר', mixed: 'מידע חלקי לבירור', demo: 'פרטים לתכנון בדיקת כיסוי'};
    const cacheLabels = {miss: 'עודכן עכשיו', fresh: 'תוצאה שמורה ועדכנית', stale_refreshing: 'מעדכן ברקע', stale_error: 'תוצאה אחרונה תקינה', degraded_fallback: 'חלק מהתוצאות אינן זמינות כרגע', bypass_sensitive: 'לא נשמר מטעמי פרטיות'};
    const assessment = payload.meta.medical_assessment_required ? ' · נדרש בירור רפואי' : '';
    const resultLabel = insuranceSaleReady(payload) ? `${payload.meta.result_count} אפשרויות` : 'הפרטים לבדיקת כיסוי מוכנים';
    const freshness = insuranceSaleReady(payload) ? ` · ${cacheLabels[payload.meta.cache_state] || 'עודכן'}` : '';
    const noExactResult = commercialSearchResultNeedsPersonalCheck(payload);
    setExperiencePersonalCheck(form, noExactResult, 'insurance');
    if (status) status.textContent = noExactResult
      ? 'לא נמצאה כרגע התאמה מדויקת. אותם פרטים מוכנים לבדיקה אישית.'
      : `${resultLabel} · ${payload.query.trip_days} ימים · ${modeLabels[payload.meta.data_mode] || modeLabels.demo}${assessment}${freshness}`;
    form.dataset.state = payload.meta.data_mode;
  } catch (error) {
    if (error?.name === 'AbortError' || generation !== insuranceSearchGeneration) return;
    document.querySelector('[data-insurance-results]')?.replaceChildren();
    const personalCheckAvailable = commercialSearchNeedsPersonalCheck(error);
    setExperiencePersonalCheck(form, personalCheckAvailable, 'insurance');
    if (status) status.textContent = personalCheckAvailable
      ? 'לא נמצאה כרגע התאמה מדויקת. אותם פרטים מוכנים לבדיקה אישית.'
      : 'לא הצלחנו להשלים את ההשוואה. בדקו תאריכים ונסו שוב.';
    form.dataset.state = 'error';
    console.warn(error);
  } finally {
    if (generation === insuranceSearchGeneration) {
      submit.disabled = false;
      if (insuranceSearchController === controller) insuranceSearchController = null;
    }
  }
}

function initInsuranceQuote() {
  const form = document.querySelector('[data-insurance-quote]');
  if (!form) return;
  const start = form.querySelector('[name="start_date"]');
  const end = form.querySelector('[name="end_date"]');
  start?.addEventListener('change', () => {
    if (!end) return;
    end.min = start.value;
    if (end.value < start.value) {
      const next = new Date(`${start.value}T12:00:00`);
      next.setDate(next.getDate() + 6);
      end.value = next.toISOString().slice(0, 10);
    }
  });
  form.addEventListener('submit', event => {
    event.preventDefault();
    if (form.dataset.contextSupported === 'false') {
      const status = document.querySelector('[data-insurance-status]');
      const url = experiencePersonalCheckUrl(form, 'insurance', form.dataset.assistedUrl || '/ai-planner/');
      if (status) status.textContent = 'הפרטים נשמרו. עוברים למתכנן החופשה כדי לסדר את הנושאים לבדיקה אישית, בלי פוליסה או הצעת מחיר.';
      window.location.assign(url);
      return;
    }
    searchInsuranceQuotes(form);
  });
  document.querySelector('[data-insurance-risk-reset]')?.addEventListener('click', () => {
    form.elements.trip_type.value = 'city_break';
    form.elements.adventure_sports.checked = false;
    form.elements.winter_sports.checked = false;
    if (form.dataset.contextSupported !== 'false') searchInsuranceQuotes(form);
  });
  if (form.dataset.contextSupported === 'false') {
    const status = document.querySelector('[data-insurance-status]');
    if (status) status.textContent = form.dataset.initialStatus || 'השלימו את פרטי הנסיעה כדי לסדר את הנושאים לבדיקה אישית.';
  } else {
    searchInsuranceQuotes(form);
  }
}

let packageComparisonPayload = null;

function packageRiskLabel(risk) {
  return {low: 'מעט נקודות שדורשות תשומת לב', medium: 'כמה פרטים דורשים בדיקה', high: 'פרטים מהותיים דורשים בדיקה'}[risk] || 'נדרשת בדיקה נוספת';
}

function selectTripPackage(packageId, shouldFocus = false) {
  if (!packageComparisonPayload) return;
  const tripPackage = packageComparisonPayload.packages.find(item => item.id === packageId);
  if (!tripPackage) return;
  const livePackage = commercialDataMode(packageComparisonPayload) === 'live';
  document.querySelectorAll('[data-package-card]').forEach(card => card.classList.toggle('is-selected', card.dataset.packageCard === packageId));
  document.querySelectorAll('[data-package-pin]').forEach(pin => {
    const selected = pin.dataset.packagePin === packageId;
    pin.classList.toggle('is-active', selected);
    pin.setAttribute('aria-pressed', String(selected));
  });
  const fields = {
    '[data-package-map-title]': tripPackage.name,
    '[data-package-map-route]': `${tripPackage.flight.airline} · ${tripPackage.flight.stops_label} · ${tripPackage.stay.name} · ${tripPackage.stay.area_name}${livePackage ? '' : ' · לתכנון'}`,
    '[data-package-map-total]': commercialPriceText(packageComparisonPayload, tripPackage.pricing.total_party_formatted),
    '[data-package-map-nights]': String(packageComparisonPayload.trip.nights),
    '[data-package-map-score]': `${tripPackage.score}/100${livePackage ? '' : ' לתכנון'}`
  };
  Object.entries(fields).forEach(([selector, value]) => {
    const element = document.querySelector(selector);
    if (element) element.textContent = value;
  });
  if (shouldFocus) document.querySelector('[data-package-map-detail]')?.scrollIntoView({behavior: preferredScrollBehavior(), block: 'center'});
}

function renderPackageMap(payload) {
  const pins = document.querySelector('[data-package-map-pins]');
  if (!pins) return;
  pins.replaceChildren();
  payload.packages.forEach((tripPackage, index) => {
    const livePackage = commercialDataMode(payload) === 'live';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'package-map-pin';
    button.dataset.packagePin = tripPackage.id;
    button.style.left = `${tripPackage.stay.position.x}%`;
    button.style.top = `${tripPackage.stay.position.y}%`;
    button.setAttribute('aria-label', `${tripPackage.name}: ${livePackage ? 'מחיר' : 'מחיר לתכנון'} ${tripPackage.pricing.total_party_formatted} לכל ההרכב, ${tripPackage.stay.area_name}`);
    button.setAttribute('aria-pressed', 'false');
    appendTextElement(button, 'strong', commercialPriceText(payload, tripPackage.pricing.total_party_formatted));
    appendTextElement(button, 'span', `${tripPackage.stay.area_name}${livePackage ? '' : ' · לתכנון'}`);
    button.addEventListener('click', () => selectTripPackage(tripPackage.id));
    pins.append(button);
  });
}

function appendPackageBreakdownRow(parent, label, value, icon) {
  const row = document.createElement('div');
  if (icon) {
    const iconElement = document.createElement('i');
    iconElement.dataset.lucide = icon;
    row.append(iconElement);
  }
  appendTextElement(row, 'span', label);
  appendTextElement(row, 'strong', value);
  parent.append(row);
}

function renderTripPackages(payload) {
  packageComparisonPayload = payload;
  const container = document.querySelector('[data-package-results]');
  if (!container) return;
  container.replaceChildren();
  if (!payload.packages.length) {
    const empty = document.createElement('div');
    empty.className = 'package-empty';
    appendTextElement(empty, 'strong', 'לא נמצאה חבילה שעומדת בכל התנאים.');
    appendTextElement(empty, 'span', 'הסירו מגבלת תקציב, הגדילו מספר חדרים או בטלו מסנן אחד ונסו שוב.');
    container.append(empty);
    return;
  }
  payload.packages.forEach((tripPackage, index) => {
    const livePackage = commercialDataMode(payload) === 'live';
    const card = document.createElement('article');
    card.className = `trip-package-card${tripPackage.id === payload.recommended ? ' is-recommended' : ''}${livePackage ? '' : ' is-planning-only'}`;
    card.dataset.packageCard = tripPackage.id;
    card.dataset.dataMode = commercialDataMode(payload);

    const head = document.createElement('div');
    head.className = 'package-card-head';
    const identity = document.createElement('div');
    appendTextElement(identity, 'small', livePackage ? tripPackage.badge : `${tripPackage.badge} · לתכנון`, 'package-card-badge');
    appendTextElement(identity, 'h3', tripPackage.name);
    appendTextElement(identity, 'span', `${tripPackage.stay.area_name} · ${tripPackage.stay.route_minutes} דקות למסלול${livePackage ? '' : ' בתכנון'}`);
    head.append(identity);
    const score = appendTextElement(head, 'strong', `${tripPackage.score}`, 'package-match-score');
    score.setAttribute('aria-label', `${livePackage ? 'ציון התאמה' : 'ציון התאמה לתכנון'} ${tripPackage.score} מתוך 100`);
    card.append(head);

    const journey = document.createElement('div');
    journey.className = 'package-journey-summary';
    appendPackageBreakdownRow(journey, `${tripPackage.flight.airline} · ${tripPackage.flight.departure_time} עד ${tripPackage.flight.return_time}`, `${tripPackage.flight.stops_label} · ${tripPackage.flight.duration_label}${livePackage ? '' : ' · לתכנון'}`, 'plane-takeoff');
    appendPackageBreakdownRow(journey, `${tripPackage.stay.name} · ${tripPackage.stay.stars}★`, `${payload.trip.nights} לילות · ${tripPackage.stay.guest_score}/10${livePackage ? '' : ' · דוגמת לינה'}`, 'hotel');
    appendPackageBreakdownRow(journey, 'מידע לבדיקת כיסוי', livePackage && tripPackage.insurance.tier === 'none' ? 'לא נכלל בחישוב' : 'אין כאן פוליסה או המלצה אישית. הפרטים ייבדקו בנפרד', 'shield-check');
    card.append(journey);

    const chips = document.createElement('div');
    chips.className = 'package-inclusion-chips';
    const inclusionLabels = [
      [tripPackage.inclusions.baggage, 'כבודה'],
      [tripPackage.inclusions.breakfast, 'ארוחת בוקר'],
      [tripPackage.inclusions.free_cancellation, 'ביטול גמיש'],
      [tripPackage.inclusions.transfers_requested, 'העברה'],
      [tripPackage.inclusions.insurance_in_calculation, 'אומדן ביטוח בחישוב']
    ];
    inclusionLabels.forEach(([included, label]) => appendTextElement(
      chips,
      'span',
      `${included ? '✓' : '+'} ${label}${livePackage ? '' : ' בתכנון'}`,
      included ? 'is-included' : 'is-extra'
    ));
    card.append(chips);

    const traits = document.createElement('div');
    traits.className = 'package-trait-grid';
    [['נוחות', tripPackage.traits.comfort], ['גמישות', tripPackage.traits.flexibility], ['מיקום', tripPackage.traits.location]].forEach(([label, value]) => {
      const trait = document.createElement('span');
      appendTextElement(trait, 'small', `${label}${livePackage ? '' : ' לתכנון'}`);
      const meter = document.createElement('i');
      meter.style.setProperty('--package-score', `${value}%`);
      trait.append(meter);
      appendTextElement(trait, 'b', `${value}`);
      traits.append(trait);
    });
    card.append(traits);

    const pricing = document.createElement('div');
    pricing.className = 'package-price-panel';
    const breakdown = document.createElement('div');
    breakdown.className = 'package-price-breakdown';
    appendPackageBreakdownRow(breakdown, 'טיסות', commercialPriceText(payload, tripPackage.pricing.flight_formatted));
    appendPackageBreakdownRow(breakdown, 'לינה', commercialPriceText(payload, tripPackage.pricing.stay_formatted));
    appendPackageBreakdownRow(breakdown, 'אומדן לביטוח, לא פוליסה', commercialPriceText({meta:{data_mode:'demo'}}, tripPackage.pricing.insurance_formatted));
    appendPackageBreakdownRow(breakdown, 'העברות', commercialPriceText(payload, tripPackage.pricing.transfers_formatted));
    appendPackageBreakdownRow(breakdown, 'תוספות', commercialPriceText(payload, tripPackage.pricing.addons_formatted));
    pricing.append(breakdown);
    const total = document.createElement('div');
    total.className = 'package-price-total';
    appendTextElement(total, 'small', `${livePackage ? 'סך הכול' : 'תקציב לתכנון'} ל-${payload.trip.travelers} נוסעים`);
    appendTextElement(total, 'strong', commercialPriceText(payload, tripPackage.pricing.total_party_formatted));
    appendTextElement(total, 'span', `${commercialPriceText(payload, tripPackage.pricing.per_person_formatted)} לאדם${livePackage ? '' : ' בתכנון'}`);
    pricing.append(total);
    card.append(pricing);
    appendCommercialDataNotice(card, payload);

    const tradeoffs = document.createElement('div');
    tradeoffs.className = 'package-tradeoffs';
    appendTextElement(tradeoffs, 'span', `✓ ${tripPackage.pros[0]}`, 'package-pro');
    appendTextElement(tradeoffs, 'span', `△ ${tripPackage.cons[0]}`, 'package-con');
    appendTextElement(tradeoffs, 'small', `${packageRiskLabel(tripPackage.traits.risk)}${livePackage ? '' : ' בהצעה הסופית'}`);
    card.append(tradeoffs);

    const details = document.createElement('details');
    appendTextElement(details, 'summary', 'כל הרכיבים ותנאי התכנון');
    const list = document.createElement('ul');
    appendTextElement(list, 'li', `${livePackage ? '' : 'תכנון טיסה: '}כרטיס ${tripPackage.flight.ticket_mode} · כבודה ${tripPackage.flight.baggage_included ? 'כלולה' : 'מחושבת כתוספת'}`);
    appendTextElement(list, 'li', `${livePackage ? '' : 'תכנון לינה: '}${tripPackage.stay.name} · ${tripPackage.stay.room} · ${tripPackage.stay.free_cancellation ? 'ביטול גמיש בדוגמה' : 'ללא ביטול גמיש בדוגמה'}`);
    appendTextElement(list, 'li', 'ביטוח: פרטי הכיסוי דורשים בדיקה אצל גורם מורשה; אין כאן פוליסה או המלצה אישית.');
    appendTextElement(list, 'li', `בסיס המחיר: סכום רכיבים; לא מוצגת הנחת חבילה לא מאומתת.`);
    details.append(list);
    card.append(details);

    const actions = document.createElement('div');
    actions.className = 'package-card-actions';
    const mapAction = document.createElement('button');
    mapAction.type = 'button';
    mapAction.textContent = 'הציגו על המפה';
    mapAction.addEventListener('click', () => selectTripPackage(tripPackage.id, true));
    const checkout = document.createElement('button');
    checkout.type = 'button';
    configureCommercialAction(checkout, 'package', {...tripPackage.booking, id: tripPackage.id}, payload, {
      id: tripPackage.id,
      title: tripPackage.name,
      subtitle: tripPackage.stay?.name,
      commercial_ref: tripPackage.commercial_ref || tripPackage.booking?.commercial_ref,
      price_scope: tripPackage.price_scope
    });
    const saveAction = createSaveOfferButton({
      kind: 'package', external_id: tripPackage.id, title: tripPackage.name,
      subtitle: `${tripPackage.flight.airline} · ${tripPackage.stay.name} · ${payload.trip.nights} לילות${livePackage ? '' : ' · לתכנון'}`,
      destination: payload.destination?.city || '', route: `${payload.origin?.code || 'TLV'} → ${payload.destination?.code || ''}`,
      price_label: commercialPriceText(payload, tripPackage.pricing.total_party_formatted), price_amount: livePackage ? tripPackage.pricing.total_party : null,
      currency: payload.search?.currency || 'USD', data_mode: payload.meta?.data_mode || 'demo', href: `${window.traVelV2?.homeUrl || '/'}packages/`
    });
    actions.append(mapAction, saveAction, checkout);
    card.append(actions);
    container.append(card);
  });
  renderPackageMap(payload);
  selectTripPackage(payload.recommended || payload.packages[0]?.id);
  renderIcons();
}

async function searchTripPackages(form) {
  const endpoint = window.traVelV2?.packageSearchUrl;
  const status = document.querySelector('[data-package-status]');
  const submit = form.querySelector('[type="submit"]');
  if (!endpoint) return;
  const params = new URLSearchParams(new FormData(form));
  ['baggage', 'breakfast', 'free_cancellation', 'transfers', 'direct_only'].forEach(name => {
    if (!params.has(name)) params.set(name, 'false');
  });
  setExperiencePersonalCheck(form, false, 'packages');
  submit.disabled = true;
  form.dataset.state = 'loading';
  if (status) status.textContent = 'מכין חלופות לפי הטיסה, הלינה, התנאים והתקציב שבחרתם...';
  try {
    const response = await fetch(`${endpoint}?${params.toString()}`, {headers: {Accept: 'application/json'}});
    const payload = await response.json();
    if (!response.ok) throw commercialResponseError(response, payload, `Package search failed: ${response.status}`);
    renderTripPackages(payload);
    const modeLabels = {live: 'מחירים שנבדקו כעת', mixed: 'מחירי תכנון עם חלק שנבדק', demo: 'מחירי תכנון והשוואה'};
    const cacheLabels = {miss: 'עודכן עכשיו', fresh: 'תוצאה שמורה ועדכנית', stale_refreshing: 'מתעדכן ברקע', stale_error: 'תוצאה אחרונה תקינה', degraded_fallback: 'חלק מהתוצאות אינן זמינות כרגע'};
    const priceScope = payload.meta.data_mode === 'live' ? `מחיר לכל ${payload.trip.travelers} הנוסעים` : `מחיר לתכנון לכל ${payload.trip.travelers} הנוסעים`;
    const freshness = payload.meta.data_mode === 'live' ? ` · ${cacheLabels[payload.meta.cache_state] || 'עודכן'}` : '';
    const noExactResult = commercialSearchResultNeedsPersonalCheck(payload);
    setExperiencePersonalCheck(form, noExactResult, 'packages');
    if (status) status.textContent = noExactResult
      ? 'לא נמצאה כרגע התאמה מדויקת. אותם פרטים מוכנים לבדיקה אישית.'
      : `${payload.meta.result_count} חלופות · ${payload.trip.nights} לילות · ${priceScope} · ${modeLabels[payload.meta.data_mode] || modeLabels.demo}${freshness}`;
    form.dataset.state = payload.meta.data_mode;
  } catch (error) {
    document.querySelector('[data-package-results]')?.replaceChildren();
    document.querySelector('[data-package-map-pins]')?.replaceChildren();
    const personalCheckAvailable = commercialSearchNeedsPersonalCheck(error);
    setExperiencePersonalCheck(form, personalCheckAvailable, 'packages');
    if (status) status.textContent = personalCheckAvailable
      ? 'לא נמצאה כרגע התאמה מדויקת. אותם פרטים מוכנים לבדיקה אישית.'
      : 'לא הצלחנו להרכיב חלופות. בדקו תאריכים, תקציב והרכב חדרים ונסו שוב.';
    form.dataset.state = 'error';
    console.warn(error);
  } finally {
    submit.disabled = false;
  }
}

function initTripPackageSearch() {
  const form = document.querySelector('[data-package-search]');
  if (!form) return;
  const departure = form.querySelector('[name="departure_date"]');
  const returnDate = form.querySelector('[name="return_date"]');
  departure?.addEventListener('change', () => {
    syncStrictTravelEndDate(departure, returnDate, 4);
  });
  form.addEventListener('submit', event => {
    event.preventDefault();
    searchTripPackages(form);
  });
  document.querySelector('[data-package-map-reset]')?.addEventListener('click', () => selectTripPackage(packageComparisonPayload?.recommended));
  if (form.dataset.autoSearch === 'false') {
    const status = document.querySelector('[data-package-status]');
    if (status) status.textContent = form.dataset.initialStatus || 'היעד נשמר במרכיב. התחילו חיפוש כשתרצו לבדוק חלופות.';
  } else {
    searchTripPackages(form);
  }
}

function workspaceKindMeta(kind) {
  return {
    flight: {label: 'טיסה', icon: 'plane-takeoff'},
    hotel: {label: 'מלון', icon: 'hotel'},
    package: {label: 'חבילה', icon: 'package-check'},
    route: {label: 'מסלול', icon: 'route'},
    destination: {label: 'יעד', icon: 'map-pin'}
  }[kind] || {label: 'שמירה', icon: 'heart'};
}

function workspaceMoney(item, amount = item.price_amount) {
  if (!amount) return item.price_label || 'טרם נקבע';
  try {
    return new Intl.NumberFormat('en-US', {style: 'currency', currency: item.currency || 'USD', maximumFractionDigits: 0}).format(amount);
  } catch (error) {
    return `${item.currency || 'USD'} ${Math.round(amount).toLocaleString('en-US')}`;
  }
}

function safeWorkspaceHref(href) {
  try {
    const url = new URL(href || '/', window.location.origin);
    const allowedProtocol = url.protocol === 'https:' || url.protocol === 'http:';
    const sameOrigin = url.origin === window.location.origin;
    return allowedProtocol && sameOrigin && !url.username && !url.password
      ? url.href
      : new URL('/', window.location.origin).href;
  } catch (error) {
    return '/';
  }
}

function selectWorkspaceMapItem(itemId) {
  const item = travelerWorkspace?.items.find(saved => saved.id === itemId);
  if (!item) return;
  document.querySelectorAll('[data-workspace-item]').forEach(card => card.classList.toggle('is-active', card.dataset.workspaceItem === itemId));
  document.querySelectorAll('[data-workspace-map-pin]').forEach(pin => {
    const selected = pin.dataset.workspaceMapPin === itemId;
    pin.classList.toggle('is-active', selected);
    pin.setAttribute('aria-pressed', String(selected));
  });
  const title = document.querySelector('[data-workspace-map-title]');
  const copy = document.querySelector('[data-workspace-map-copy]');
  const price = document.querySelector('[data-workspace-map-price]');
  if (title) title.textContent = item.title;
  if (copy) copy.textContent = [item.route, item.subtitle].filter(Boolean).join(' · ');
  if (price) price.textContent = item.price_label || workspaceMoney(item);
}

function findWorkspaceDatasetElement(root, datasetKey, expectedValue = null) {
  if (!root) return null;
  const queue = [...(root.children || [])];
  while (queue.length) {
    const child = queue.shift();
    if (child?.dataset && Object.prototype.hasOwnProperty.call(child.dataset, datasetKey)
      && (expectedValue === null || child.dataset[datasetKey] === expectedValue)) return child;
    queue.push(...(child?.children || []));
  }
  return null;
}

function findWorkspaceClassElement(root, className) {
  if (!root) return null;
  const queue = [...(root.children || [])];
  while (queue.length) {
    const child = queue.shift();
    if (child?.classList?.contains?.(className) || String(child?.className || '').split(/\s+/).includes(className)) return child;
    queue.push(...(child?.children || []));
  }
  return null;
}

function workspaceEmptyFocusTarget(empty) {
  if (!empty) return null;
  const queue = [...(empty.children || [])];
  while (queue.length) {
    const child = queue.shift();
    if (['A', 'BUTTON', 'H2', 'H3'].includes(child?.tagName)) {
      if (!['A', 'BUTTON'].includes(child.tagName)) child.tabIndex = -1;
      return child;
    }
    queue.push(...(child?.children || []));
  }
  empty.tabIndex = -1;
  return empty;
}

function captureWorkspaceListFocus(container, cardDatasetKey, actionDatasetKey) {
  const active = document.activeElement;
  const cards = [...(container?.children || [])];
  if (!active || !cards.length) return null;
  const card = active.closest?.(`[data-${cardDatasetKey.replace(/[A-Z]/g, value => `-${value.toLowerCase()}`)}]`);
  const index = cards.indexOf(card);
  if (index < 0) return null;
  return {
    id: card.dataset?.[cardDatasetKey] || '',
    index,
    action: active.dataset?.[actionDatasetKey] || ''
  };
}

function restoreWorkspaceListFocus(container, focusSnapshot, cardDatasetKey, actionDatasetKey, emptyFallback = null) {
  if (!focusSnapshot) return false;
  const cards = [...(container?.children || [])];
  if (!cards.length) {
    const target = workspaceEmptyFocusTarget(emptyFallback);
    target?.focus?.();
    return Boolean(target);
  }
  const matchingCard = cards.find(card => card?.dataset?.[cardDatasetKey] === focusSnapshot.id);
  const targetCard = matchingCard || cards[Math.min(focusSnapshot.index, cards.length - 1)];
  const matchingAction = matchingCard && focusSnapshot.action
    ? findWorkspaceDatasetElement(matchingCard, actionDatasetKey, focusSnapshot.action)
    : null;
  const fallbackAction = findWorkspaceDatasetElement(targetCard, actionDatasetKey);
  const target = matchingAction || fallbackAction || targetCard;
  target?.focus?.();
  return Boolean(target);
}

function renderWorkspaceMap(items, preferredItemId = '') {
  const pins = document.querySelector('[data-workspace-map-pins]');
  if (!pins) return;
  const previousPins = [...(pins.children || [])];
  const focusedPin = previousPins.find(pin => pin === document.activeElement
    || document.activeElement?.closest?.('[data-workspace-map-pin]') === pin);
  const focusedPinId = focusedPin?.dataset?.workspaceMapPin || '';
  const focusedPinIndex = Math.max(0, previousPins.indexOf(focusedPin));
  const selectedPinId = previousPins.find(pin => pin?.getAttribute?.('aria-pressed') === 'true')?.dataset?.workspaceMapPin || '';
  const availableIds = new Set((Array.isArray(items) ? items : []).map(item => item.id));
  const selectedItemId = [preferredItemId, selectedPinId, items?.[0]?.id].find(itemId => itemId && availableIds.has(itemId)) || '';
  const orbit = pins.closest('[data-workspace-map]');
  if (orbit) orbit.dataset.coordinateMode = 'option-orbit';
  const orbitLabel = orbit?.querySelector('[data-workspace-orbit-label]');
  if (orbitLabel) orbitLabel.textContent = 'מסלול אפשרויות · לא מיקום גאוגרפי';
  pins.replaceChildren();
  const positions = [[64,28],[38,21],[24,43],[57,52],[73,43],[40,62],[18,67],[82,64]];
  items.slice(0, 8).forEach((item, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    const selected = item.id === selectedItemId;
    button.className = `workspace-map-pin${selected ? ' is-active' : ''}`;
    button.dataset.workspaceMapPin = item.id;
    button.style.left = `${positions[index][0]}%`;
    button.style.top = `${positions[index][1]}%`;
    button.setAttribute('aria-label', `${item.title}, ${item.price_label || workspaceMoney(item)}, נקודה במסלול האפשרויות ולא מיקום גאוגרפי`);
    button.setAttribute('aria-pressed', String(selected));
    appendTextElement(button, 'strong', item.price_label || workspaceMoney(item));
    appendTextElement(button, 'span', item.destination || item.title);
    button.addEventListener('click', () => selectWorkspaceMapItem(item.id));
    pins.append(button);
  });
  if (selectedItemId) selectWorkspaceMapItem(selectedItemId);
  if (focusedPin) {
    const nextPins = [...(pins.children || [])];
    const focusTarget = nextPins.find(pin => pin?.dataset?.workspaceMapPin === focusedPinId)
      || nextPins[Math.min(focusedPinIndex, Math.max(0, nextPins.length - 1))];
    focusTarget?.focus?.();
  }
}

async function removeWorkspaceItem(itemId) {
  const workspace = travelerWorkspace || readLocalWorkspace();
  const previousTombstones = new Set(workspaceDeletionTombstones);
  if (!rememberWorkspaceDeletion(itemId)) {
    showWorkspaceToast('האפשרות לא הוסרה כי לא ניתן היה לשמור סימון מחיקה בטוח במכשיר.', 'triangle-alert');
    return {localSaved: false, accountSynced: false};
  }
  const nextWorkspace = {...workspace, items: workspace.items.filter(item => item.id !== itemId)};
  if (!writeLocalWorkspace(nextWorkspace)) {
    writeWorkspaceDeletionTombstones(previousTombstones);
    showWorkspaceToast('האפשרות לא הוסרה כי השינוי לא נשמר בדפדפן.', 'triangle-alert');
    return {localSaved: false, accountSynced: false};
  }
  const mutationSnapshot = workspaceLocalSyncSnapshot(travelerWorkspace || nextWorkspace);
  renderWorkspaceDashboard();
  showWorkspaceToast('האפשרות הוסרה מהנסיעה', 'trash-2');
  if (window.traVelV2?.isLoggedIn && workspaceAccountAuthRequired) {
    showWorkspaceToast('האפשרות הוסרה במכשיר. התחברו מחדש או רעננו כדי לסנכרן את החשבון.', 'log-in');
    return {localSaved:true,accountSynced:false,reason:'reauth_required',correctiveScheduled:false};
  }
  try {
    const serverWorkspace = await workspaceMutationRequest(`/items/${encodeURIComponent(itemId)}`, {method: 'DELETE'});
    if (window.traVelV2?.isLoggedIn && !serverWorkspace) {
      const correctiveScheduled = scheduleWorkspaceCorrectiveSync(500, true);
      showWorkspaceToast(correctiveScheduled ? 'האפשרות הוסרה במכשיר; מתבצע סנכרון תיקון מוגבל.' : 'האפשרות הוסרה במכשיר; רעננו כדי לסנכרן את החשבון.', 'cloud-off');
      return {localSaved:true,accountSynced:false,correctiveScheduled};
    }
    if (serverWorkspace) {
      const confirmation = applyServerConfirmedWorkspace(nextWorkspace, serverWorkspace, '', mutationSnapshot);
      if (confirmation.localChanged) {
        showWorkspaceToast(
          confirmation.correctiveScheduled
            ? 'המחיקה התקבלה בחשבון, אך המכשיר השתנה בינתיים. השינוי החדש נשמר ומתבצע סנכרון תיקון מוגבל.'
            : 'המחיקה התקבלה בחשבון, אך המכשיר השתנה בינתיים. רעננו כדי לסנכרן את השינוי החדש.',
          'refresh-cw'
        );
        return {localSaved:true,accountSynced:false,reason:'local_changed',correctiveScheduled:confirmation.correctiveScheduled};
      }
      showWorkspaceToast(
        confirmation.devicePersisted
          ? 'המחיקה אושרה בחשבון ובמכשיר. סימון הבטיחות יימחק לאחר סנכרון מלא.'
          : 'המחיקה אושרה בחשבון, אך העדכון המלא לא נשמר במכשיר.',
        confirmation.devicePersisted ? 'cloud-check' : 'cloud-off'
      );
      return {localSaved:confirmation.devicePersisted,accountSynced:true,devicePersisted:confirmation.devicePersisted};
    }
    return {localSaved:true,accountSynced:false};
  } catch (error) {
    if (workspaceAuthenticationRequired(error)) {
      requireWorkspaceReauthentication();
      showWorkspaceToast('האפשרות הוסרה במכשיר. החיבור לחשבון פג, לכן לא יתבצעו ניסיונות נוספים עד שתתחברו מחדש או תרעננו.', 'log-in');
      return {localSaved:true,accountSynced:false,reason:'reauth_required',correctiveScheduled:false};
    }
    const correctiveScheduled = scheduleWorkspaceCorrectiveSync(500, true);
    showWorkspaceToast(
      workspaceRequestTimedOut(error)
        ? correctiveScheduled
          ? 'המחיקה במכשיר נשמרה, אך החשבון לא השיב בתוך 15 שניות. מתבצע סנכרון תיקון מוגבל.'
          : 'המחיקה במכשיר נשמרה, אך החשבון לא השיב בתוך 15 שניות. רעננו כדי לבדוק את מצב החשבון.'
        : correctiveScheduled
          ? 'האפשרות הוסרה במכשיר; מתבצע ניסיון סנכרון מוגבל לחשבון.'
          : 'האפשרות הוסרה במכשיר; השינוי בחשבון לא אושר. רעננו כדי לנסות שוב.',
      'cloud-off'
    );
    console.warn(error);
    return {localSaved:true,accountSynced:false,reason:workspaceRequestTimedOut(error) ? 'timeout' : 'account_unconfirmed',correctiveScheduled};
  }
}

async function toggleWorkspaceWatch(itemId) {
  const workspace = travelerWorkspace || readLocalWorkspace();
  const item = workspace.items.find(saved => saved.id === itemId);
  if (!item) return {localSaved: false, accountSynced: false};
  const enabled = !item.watch?.enabled;
  const target = enabled ? Math.max(1, Math.round((item.price_amount || 0) * .95)) : 0;
  const nextWorkspace = {
    ...workspace,
    items: workspace.items.map(saved => saved.id === itemId ? {
      ...saved,
      watch: {enabled, target_amount: target, delivery_enabled: false, status: enabled ? 'awaiting_live_supplier' : 'off'}
    } : saved)
  };
  if (!writeLocalWorkspace(nextWorkspace)) {
    showWorkspaceToast('יעד המחיר לא השתנה כי השינוי לא נשמר בדפדפן.', 'triangle-alert');
    return {localSaved: false, accountSynced: false};
  }
  const mutationSnapshot = workspaceLocalSyncSnapshot(travelerWorkspace || nextWorkspace);
  renderWorkspaceDashboard(itemId);
  showWorkspaceToast(enabled ? 'יעד המחיר נשמר. התראות יופעלו רק לאחר חיבור מקור מחירים וקבלת הסכמה.' : 'מעקב המחיר הוסר', enabled ? 'bell-ring' : 'bell-off');
  if (window.traVelV2?.isLoggedIn && workspaceAccountAuthRequired) {
    showWorkspaceToast('יעד המחיר נשמר במכשיר. התחברו מחדש או רעננו כדי לסנכרן את החשבון.', 'log-in');
    return {localSaved:true,accountSynced:false,reason:'reauth_required',correctiveScheduled:false};
  }
  try {
    const serverWorkspace = await workspaceMutationRequest(`/items/${encodeURIComponent(itemId)}/watch`, {method: 'PUT', body: JSON.stringify({enabled, target_amount: target})});
    if (window.traVelV2?.isLoggedIn && !serverWorkspace) {
      const correctiveScheduled = scheduleWorkspaceCorrectiveSync(500, true);
      showWorkspaceToast(correctiveScheduled ? 'יעד המחיר נשמר במכשיר; מתבצע סנכרון תיקון מוגבל.' : 'יעד המחיר נשמר במכשיר; רעננו כדי לסנכרן את החשבון.', 'cloud-off');
      return {localSaved:true,accountSynced:false,correctiveScheduled};
    }
    if (serverWorkspace) {
      const confirmation = applyServerConfirmedWorkspace(nextWorkspace, serverWorkspace, itemId, mutationSnapshot);
      if (confirmation.localChanged) {
        showWorkspaceToast(
          confirmation.correctiveScheduled
            ? 'יעד המחיר התקבל בחשבון, אך שמירה חדשה יותר נשמרה במכשיר ומתבצע סנכרון תיקון מוגבל.'
            : 'יעד המחיר התקבל בחשבון, אך שמירה חדשה יותר נשמרה במכשיר. רעננו כדי לסנכרן.',
          'refresh-cw'
        );
        return {localSaved:true,accountSynced:false,reason:'local_changed',correctiveScheduled:confirmation.correctiveScheduled};
      }
      if (confirmation.devicePersisted) {
        showWorkspaceToast('יעד המחיר אושר בחשבון ובמכשיר. משלוח התראות עדיין אינו פעיל.', 'cloud-check');
        return {localSaved: true, accountSynced: true, devicePersisted: true};
      }
      showWorkspaceToast('יעד המחיר אושר בחשבון ומוצג בלשונית הזאת, אך העדכון לא נשמר במכשיר.', 'cloud-off');
      return {localSaved: false, accountSynced: true, devicePersisted: false};
    }
    return {localSaved: true, accountSynced: false};
  } catch (error) {
    if (workspaceAuthenticationRequired(error)) {
      requireWorkspaceReauthentication();
      showWorkspaceToast('יעד המחיר נשמר במכשיר. החיבור לחשבון פג, לכן לא יתבצעו ניסיונות נוספים עד שתתחברו מחדש או תרעננו.', 'log-in');
      return {localSaved:true,accountSynced:false,reason:'reauth_required',correctiveScheduled:false};
    }
    const correctiveScheduled = scheduleWorkspaceCorrectiveSync(500, true);
    showWorkspaceToast(
      workspaceRequestTimedOut(error)
        ? correctiveScheduled
          ? 'יעד המחיר נשמר במכשיר, אך החשבון לא השיב בתוך 15 שניות. מתבצע סנכרון תיקון מוגבל.'
          : 'יעד המחיר נשמר במכשיר, אך החשבון לא השיב בתוך 15 שניות. רעננו כדי לבדוק את מצבו.'
        : correctiveScheduled
          ? 'יעד המחיר נשמר במכשיר; מתבצע ניסיון סנכרון מוגבל לחשבון.'
          : 'יעד המחיר נשמר במכשיר; השינוי בחשבון לא אושר. רעננו כדי לנסות שוב.',
      'cloud-off'
    );
    console.warn(error);
    return {localSaved:true,accountSynced:false,reason:workspaceRequestTimedOut(error) ? 'timeout' : 'account_unconfirmed',correctiveScheduled};
  }
}

async function runWorkspaceItemMutation(itemId, action, operation) {
  if (workspaceItemMutationRegistry.has(itemId)) return {localSaved:false,accountSynced:false,reason:'mutation_in_flight'};
  const container = document.querySelector('[data-workspace-items]');
  const focusOrigin = document.activeElement;
  const capturedFocusSnapshot = captureWorkspaceListFocus(container, 'workspaceItem', 'workspaceItemAction');
  const focusSnapshot = capturedFocusSnapshot || {
    id: itemId,
    index: Math.max(0, [...(container?.children || [])].findIndex(card => card?.dataset?.workspaceItem === itemId)),
    action
  };
  workspaceItemMutationRegistry.set(itemId, {action});
  try {
    return await operation();
  } finally {
    const activeBeforeRender = document.activeElement;
    const activeCard = activeBeforeRender?.closest?.('[data-workspace-item]');
    const shouldRestoreFocus = Boolean(capturedFocusSnapshot) && (
      activeBeforeRender === focusOrigin
      || activeBeforeRender === document.body
      || activeBeforeRender?.isConnected === false
      || activeCard?.dataset?.workspaceItem === itemId
    );
    workspaceItemMutationRegistry.delete(itemId);
    renderWorkspaceDashboard(itemId);
    if (shouldRestoreFocus) restoreWorkspaceListFocus(container, focusSnapshot, 'workspaceItem', 'workspaceItemAction');
  }
}

function renderWorkspaceCard(item) {
  const card = document.createElement('article');
  card.className = 'workspace-item-card';
  card.dataset.workspaceItem = item.id;
  const head = document.createElement('div');
  head.className = 'workspace-card-head';
  const identity = document.createElement('div');
  const kind = workspaceKindMeta(item.kind);
  const kindLine = document.createElement('span');
  kindLine.className = 'workspace-kind';
  const icon = document.createElement('i');
  icon.dataset.lucide = kind.icon;
  kindLine.append(icon, document.createTextNode(kind.label));
  identity.append(kindLine);
  appendTextElement(identity, 'h3', item.title);
  appendTextElement(identity, 'p', item.subtitle || item.route || item.destination);
  head.append(identity);
  appendTextElement(head, 'strong', item.price_label || workspaceMoney(item), 'workspace-card-price');
  card.append(head);

  const context = document.createElement('div');
  context.className = 'workspace-card-context';
  [item.destination, item.route, item.data_mode === 'live' ? 'נתון חי' : 'אומדן לא מאומת'].filter(Boolean).forEach(value => appendTextElement(context, 'span', value));
  card.append(context);

  const watchState = document.createElement('div');
  watchState.className = 'workspace-watch-state';
  if (item.watch?.enabled) {
    const bell = document.createElement('i');
    bell.dataset.lucide = 'bell-ring';
    watchState.append(bell);
    appendTextElement(watchState, 'span', `יעד ${workspaceMoney(item, item.watch.target_amount)} · התראות עדיין אינן פעילות`);
  } else {
    appendTextElement(watchState, 'span', 'אפשר להצמיד יעד מחיר בלי להפעיל משלוח');
  }
  card.append(watchState);

  const actions = document.createElement('div');
  actions.className = 'workspace-card-actions';
  const mapButton = document.createElement('button');
  mapButton.type = 'button';
  mapButton.dataset.workspaceItemAction = 'map';
  const mapIcon = document.createElement('i');
  mapIcon.dataset.lucide = 'orbit';
  mapButton.append(mapIcon, document.createTextNode('במסלול האפשרויות'));
  mapButton.addEventListener('click', () => {
    selectWorkspaceMapItem(item.id);
    document.querySelector('[data-workspace-map]')?.scrollIntoView({behavior: preferredScrollBehavior(), block: 'center'});
  });
  const watchButton = document.createElement('button');
  watchButton.type = 'button';
  watchButton.dataset.workspaceItemAction = 'watch';
  watchButton.className = item.watch?.enabled ? 'is-watching' : '';
  const watchIcon = document.createElement('i');
  watchIcon.dataset.lucide = item.watch?.enabled ? 'bell-off' : 'bell-plus';
  watchButton.append(watchIcon, document.createTextNode(item.watch?.enabled ? 'בטלו מעקב' : 'יעד מחיר'));
  const pendingMutation = workspaceItemMutationRegistry.get(item.id);
  if (pendingMutation) {
    watchButton.disabled = true;
    watchButton.setAttribute('aria-disabled', 'true');
    if (pendingMutation.action === 'watch') {
      watchButton.dataset.state = 'loading';
      watchButton.setAttribute('aria-busy', 'true');
    }
  }
  watchButton.addEventListener('click', async () => {
    if (watchButton.getAttribute('aria-busy') === 'true') return;
    watchButton.disabled = true;
    watchButton.dataset.state = 'loading';
    watchButton.setAttribute('aria-busy', 'true');
    try {
      const result = await runWorkspaceItemMutation(item.id, 'watch', () => toggleWorkspaceWatch(item.id));
      if (result?.accountSynced) watchButton.dataset.state = 'confirmed';
    } finally {
      watchButton.removeAttribute('aria-busy');
      if (watchButton.dataset.state !== 'confirmed') delete watchButton.dataset.state;
      watchButton.disabled = false;
    }
  });
  const removeButton = document.createElement('button');
  removeButton.type = 'button';
  removeButton.dataset.workspaceItemAction = 'remove';
  removeButton.className = 'workspace-delete';
  removeButton.setAttribute('aria-label', `הסרת ${item.title}`);
  const removeIcon = document.createElement('i');
  removeIcon.dataset.lucide = 'trash-2';
  removeButton.append(removeIcon);
  if (pendingMutation) {
    removeButton.disabled = true;
    removeButton.setAttribute('aria-disabled', 'true');
    if (pendingMutation.action === 'remove') {
      removeButton.dataset.state = 'loading';
      removeButton.setAttribute('aria-busy', 'true');
    }
  }
  removeButton.addEventListener('click', async () => {
    if (removeButton.getAttribute('aria-busy') === 'true') return;
    removeButton.disabled = true;
    removeButton.dataset.state = 'loading';
    removeButton.setAttribute('aria-busy', 'true');
    try {
      const result = await runWorkspaceItemMutation(item.id, 'remove', () => removeWorkspaceItem(item.id));
      if (result?.accountSynced) removeButton.dataset.state = 'confirmed';
    } finally {
      removeButton.removeAttribute('aria-busy');
      if (removeButton.dataset.state !== 'confirmed') delete removeButton.dataset.state;
      removeButton.disabled = false;
    }
  });
  actions.append(mapButton, watchButton, removeButton);
  card.append(actions);

  const open = document.createElement('a');
  open.href = safeWorkspaceHref(item.href);
  open.className = 'text-link';
  open.dataset.workspaceItemAction = 'open';
  open.textContent = 'פתחו שוב את ההשוואה ←';
  card.append(open);
  return card;
}

function renderWorkspaceDashboard(preferredItemId = '') {
  const workspace = travelerWorkspace || readLocalWorkspace();
  const items = workspace.items || [];
  const visible = activeWorkspaceFilter === 'all' ? items : items.filter(item => item.kind === activeWorkspaceFilter);
  const container = document.querySelector('[data-workspace-items]');
  const empty = document.querySelector('[data-workspace-empty]');
  if (container) {
    const focusSnapshot = captureWorkspaceListFocus(container, 'workspaceItem', 'workspaceItemAction');
    container.replaceChildren(...visible.map(renderWorkspaceCard));
    if (empty) empty.hidden = visible.length > 0;
    restoreWorkspaceListFocus(container, focusSnapshot, 'workspaceItem', 'workspaceItemAction', empty);
  }
  else if (empty) empty.hidden = visible.length > 0;
  const count = document.querySelector('[data-workspace-count]');
  const watches = document.querySelector('[data-workspace-watch-count]');
  const destinations = document.querySelector('[data-workspace-destination-count]');
  if (count) count.textContent = String(items.length);
  if (watches) watches.textContent = String(items.filter(item => item.watch?.enabled).length);
  if (destinations) destinations.textContent = String(new Set(items.map(item => item.destination).filter(Boolean)).size);
  renderWorkspaceMap(items, preferredItemId);
  const status = document.querySelector('[data-workspace-status]');
  if (status) {
    const storageLabel = workspaceLocalStorageAvailable ? 'נשמרות באופן פרטי' : 'זמינות בלשונית הזאת בלבד';
    status.textContent = items.length ? `${items.length} אפשרויות · ${storageLabel} · התראות אינן פעילות עדיין` : 'סביבת העבודה מוכנה. שמרו אפשרות אחת כדי להתחיל להשוות.';
  }
  renderIcons();
}

function hydrateWorkspacePreferences(preferences, {force = false} = {}) {
  const form = document.querySelector('[data-workspace-preferences]');
  if (!form || (workspacePreferencesDirty && !force)) return false;
  ['home_airport', 'currency', 'budget', 'max_stops', 'party_style'].forEach(name => {
    if (form.elements[name] && preferences[name] !== undefined) form.elements[name].value = String(preferences[name]);
  });
  form.querySelectorAll('[name="priorities"]').forEach(input => { input.checked = (preferences.priorities || []).includes(input.value); });
  return true;
}

function markWorkspacePreferencesDirty() {
  workspacePreferencesDirty = true;
  workspacePreferencesEditGeneration += 1;
  return workspacePreferencesEditGeneration;
}

function confirmWorkspacePreferencesSubmission(editGeneration, preferences) {
  if (workspacePreferencesEditGeneration !== editGeneration) return false;
  workspacePreferencesDirty = false;
  hydrateWorkspacePreferences(preferences, {force:true});
  return true;
}

async function saveWorkspacePreferences(form) {
  const data = new FormData(form);
  const preferences = {
    home_airport: String(data.get('home_airport') || 'TLV').toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3),
    currency: String(data.get('currency') || 'USD'),
    budget: Math.max(0, Number(data.get('budget')) || 0),
    max_stops: Math.max(0, Math.min(3, Number(data.get('max_stops')) || 0)),
    party_style: String(data.get('party_style') || 'couple'),
    priorities: data.getAll('priorities').slice(0, 6)
  };
  if (preferences.home_airport.length !== 3) {
    showWorkspaceToast('יש להזין קוד שדה תעופה בן שלוש אותיות', 'triangle-alert');
    return {localSaved: false, accountSynced: false};
  }
  const workspace = travelerWorkspace || readLocalWorkspace();
  const nextWorkspace = {...workspace, preferences};
  if (!writeLocalWorkspace(nextWorkspace)) {
    showWorkspaceToast('ההעדפות לא נשמרו בדפדפן. בדקו את הגדרות האחסון ונסו שוב.', 'triangle-alert');
    return {localSaved: false, accountSynced: false};
  }
  if (!window.traVelV2?.isLoggedIn) {
    showWorkspaceToast('ההעדפות נשמרו במכשיר הזה', 'sliders-horizontal');
    return {localSaved: true, accountSynced: false};
  }
  if (workspaceAccountAuthRequired) {
    showWorkspaceToast('ההעדפות נשמרו במכשיר. התחברו מחדש או רעננו כדי לסנכרן את החשבון.', 'log-in');
    return {localSaved:true,accountSynced:false,reason:'reauth_required',correctiveScheduled:false};
  }
  const mutationSnapshot = workspaceLocalSyncSnapshot(travelerWorkspace || nextWorkspace);
  showWorkspaceToast('ההעדפות נשמרו במכשיר. בודקים סנכרון לחשבון...', 'cloud');
  try {
    const serverWorkspace = await workspaceMutationRequest('/preferences', {method: 'PUT', body: JSON.stringify(preferences)});
    if (serverWorkspace) {
      const confirmation = applyServerConfirmedWorkspace(nextWorkspace, serverWorkspace, '', mutationSnapshot);
      if (confirmation.localChanged) {
        showWorkspaceToast(
          confirmation.correctiveScheduled
            ? 'ההעדפות התקבלו בחשבון, אך שינוי חדש יותר נשמר במכשיר ומתבצע סנכרון תיקון מוגבל.'
            : 'ההעדפות התקבלו בחשבון, אך שינוי חדש יותר נשמר במכשיר. רעננו כדי לסנכרן.',
          'refresh-cw'
        );
        return {localSaved:true,accountSynced:false,reason:'local_changed',correctiveScheduled:confirmation.correctiveScheduled};
      }
      if (confirmation.devicePersisted) {
        showWorkspaceToast('ההעדפות אושרו בחשבון ובמכשיר', 'cloud-check');
        return {localSaved: true, accountSynced: true, devicePersisted: true};
      }
      showWorkspaceToast('ההעדפות אושרו בחשבון ומוצגות בלשונית הזאת, אך העדכון לא נשמר במכשיר.', 'cloud-off');
      return {localSaved: false, accountSynced: true, devicePersisted: false};
    }
    const correctiveScheduled = scheduleWorkspaceCorrectiveSync(500, true);
    showWorkspaceToast(correctiveScheduled ? 'ההעדפות נשמרו במכשיר; מתבצע סנכרון תיקון מוגבל.' : 'ההעדפות נשמרו במכשיר; רעננו כדי לסנכרן את החשבון.', 'cloud-off');
    return {localSaved:true,accountSynced:false,correctiveScheduled};
  } catch (error) {
    if (workspaceAuthenticationRequired(error)) {
      requireWorkspaceReauthentication();
      showWorkspaceToast('ההעדפות נשמרו במכשיר. החיבור לחשבון פג, לכן לא יתבצעו ניסיונות נוספים עד שתתחברו מחדש או תרעננו.', 'log-in');
      return {localSaved:true,accountSynced:false,reason:'reauth_required',correctiveScheduled:false};
    }
    const correctiveScheduled = scheduleWorkspaceCorrectiveSync(500, true);
    showWorkspaceToast(
      workspaceRequestTimedOut(error)
        ? correctiveScheduled
          ? 'ההעדפות נשמרו במכשיר, אך החשבון לא השיב בתוך 15 שניות. מתבצע סנכרון תיקון מוגבל.'
          : 'ההעדפות נשמרו במכשיר, אך החשבון לא השיב בתוך 15 שניות. רעננו כדי לבדוק את מצבן.'
        : correctiveScheduled
          ? 'ההעדפות נשמרו במכשיר; מתבצע ניסיון סנכרון מוגבל לחשבון.'
          : 'ההעדפות נשמרו במכשיר; השינוי בחשבון לא אושר. רעננו כדי לנסות שוב.',
      'cloud-off'
    );
    console.warn(error);
    return {localSaved:true,accountSynced:false,reason:workspaceRequestTimedOut(error) ? 'timeout' : 'account_unconfirmed',correctiveScheduled};
  }
}

function setWorkspaceAccountSyncState(state, message = '') {
  const root = document.querySelector('[data-traveler-workspace]');
  const status = document.querySelector('[data-workspace-status]');
  if (root) root.dataset.syncState = state;
  if (status) {
    status.dataset.syncState = state;
    if (message) status.textContent = message;
  }
}

async function synchronizeWorkspaceAccount() {
  const local = travelerWorkspace || readLocalWorkspace();
  const deletedItemIds = [...workspaceDeletionTombstones].slice(0, 50);
  const submittedTombstones = new Set(deletedItemIds);
  const clientSnapshot = workspaceLocalSyncSnapshot(local);
  const payload = {
    items: (local.items || []).filter(item => !workspaceDeletionTombstones.has(normalizeWorkspaceItemId(item?.id))),
    preferences: local.preferences,
    deleted_item_ids: deletedItemIds
  };
  const serverWorkspace = await workspaceMutationRequest('/sync', {method: 'PUT', body: JSON.stringify(payload)});
  if (!serverWorkspace || !Array.isArray(serverWorkspace.items) || !serverWorkspace.preferences) {
    throw new Error('Workspace sync did not return a confirmed workspace.');
  }

  const currentSnapshot = workspaceLocalSyncSnapshot(travelerWorkspace || local);
  if (!workspaceLocalSyncSnapshotMatches(clientSnapshot, currentSnapshot)) {
    const sameTabChanged = clientSnapshot.generation !== currentSnapshot.generation
      || clientSnapshot.memory_workspace !== currentSnapshot.memory_workspace
      || clientSnapshot.memory_tombstones !== currentSnapshot.memory_tombstones;
    if (!sameTabChanged) {
      workspaceDeletionTombstones = readWorkspaceDeletionTombstones();
      travelerWorkspace = readLocalWorkspace();
    }
    return {workspace: travelerWorkspace, confirmed: false, reason: 'local_changed'};
  }

  const returnedIds = new Set(serverWorkspace.items.map(item => normalizeWorkspaceItemId(item?.id)).filter(Boolean));
  const unconfirmedDeletedIds = deletedItemIds.filter(itemId => returnedIds.has(itemId));
  const confirmedDeletedIds = deletedItemIds.filter(itemId => !returnedIds.has(itemId));
  const mergedWorkspace = mergeTravelerWorkspaces(local, serverWorkspace, workspaceDeletionTombstones);
  const localWriteConfirmed = writeLocalWorkspace(mergedWorkspace);
  if (!localWriteConfirmed) travelerWorkspace = mergedWorkspace;

  if (!localWriteConfirmed) {
    return {workspace: mergedWorkspace, confirmed: false, reason: 'local_write_unavailable'};
  }
  const remainingTombstones = new Set(workspaceDeletionTombstones);
  confirmedDeletedIds.forEach(itemId => {
    if (submittedTombstones.has(itemId)) remainingTombstones.delete(itemId);
  });
  if (!writeWorkspaceDeletionTombstones(remainingTombstones)) {
    return {workspace: mergedWorkspace, confirmed: false, reason: 'local_cleanup_unavailable'};
  }
  if (unconfirmedDeletedIds.length) {
    return {workspace: mergedWorkspace, confirmed: false, reason: 'deletion_not_confirmed'};
  }
  return {workspace: mergedWorkspace, confirmed: true, reason: ''};
}

function scheduleWorkspaceCorrectiveSync(delay = 250, resetBudget = false) {
  if (resetBudget && !workspaceAccountAuthRequired) workspaceCorrectiveSyncAttempts = 0;
  if (!window.traVelV2?.isLoggedIn || workspaceAccountAuthRequired || workspaceCorrectiveSyncAttempts >= workspaceCorrectiveSyncMaximumAttempts) return false;
  if (workspaceCorrectiveSyncTimer) window.clearTimeout(workspaceCorrectiveSyncTimer);
  workspaceCorrectiveSyncTimer = window.setTimeout(async () => {
    workspaceCorrectiveSyncTimer = 0;
    if (!window.traVelV2?.isLoggedIn || workspaceAccountAuthRequired) return;
    if (workspaceAccountSyncInFlight) {
      scheduleWorkspaceCorrectiveSync(500);
      return;
    }
    if (workspaceCorrectiveSyncAttempts >= workspaceCorrectiveSyncMaximumAttempts) return;
    workspaceCorrectiveSyncAttempts += 1;
    workspaceAccountSyncInFlight = true;
    setWorkspaceAccountSyncState('syncing', 'מסנכרנים שינוי חדש שנשמר במכשיר...');
    try {
      const result = await synchronizeWorkspaceAccount();
      if (document.querySelector('[data-traveler-workspace]')) {
        renderWorkspaceDashboard();
        hydrateWorkspacePreferences(travelerWorkspace.preferences);
      }
      refreshMapSaveControls();
      if (result.confirmed) {
        workspaceCorrectiveSyncAttempts = 0;
        setWorkspaceAccountSyncState('confirmed', 'השינוי החדש אושר בחשבון ובמכשיר.');
      } else if (result.reason === 'local_changed') {
        const retryScheduled = scheduleWorkspaceCorrectiveSync(Math.min(4000, 250 * (2 ** workspaceCorrectiveSyncAttempts)));
        setWorkspaceAccountSyncState(
          'local_changed',
          retryScheduled
            ? 'נשמר שינוי נוסף בזמן הסנכרון. הוא לא נדרס ומתבצע ניסיון תיקון מוגבל נוסף.'
            : 'נשמר שינוי נוסף בזמן הסנכרון. הוא לא נדרס; רעננו כדי לנסות סנכרון נוסף.'
        );
      } else {
        const retryScheduled = scheduleWorkspaceCorrectiveSync(Math.min(4000, 250 * (2 ** workspaceCorrectiveSyncAttempts)));
        setWorkspaceAccountSyncState(
          'incomplete',
          retryScheduled
            ? 'לא התקבל אישור מלא. מתבצע ניסיון תיקון מוגבל נוסף.'
            : 'לא התקבל אישור מלא. רעננו כדי לנסות סנכרון נוסף.'
        );
      }
    } catch (error) {
      if (workspaceAuthenticationRequired(error)) {
        requireWorkspaceReauthentication();
        return;
      }
      const retryScheduled = scheduleWorkspaceCorrectiveSync(Math.min(4000, 500 * (2 ** workspaceCorrectiveSyncAttempts)));
      setWorkspaceAccountSyncState(
        'unavailable',
        retryScheduled
          ? 'השינוי נשמר במכשיר. מתבצע ניסיון סנכרון מוגבל נוסף.'
          : 'השינוי נשמר במכשיר. הסנכרון לחשבון לא אושר; רעננו כדי לנסות שוב.'
      );
      console.warn(error);
    } finally {
      workspaceAccountSyncInFlight = false;
    }
  }, delay);
  return true;
}

function installWorkspaceStorageListener() {
  if (workspaceStorageListenerInstalled) return;
  workspaceStorageListenerInstalled = true;
  window.addEventListener('storage', event => {
    if (event?.key !== null && ![workspaceLocalKey, workspaceDeletedLocalKey].includes(event?.key)) return;
    workspaceLocalMutationGeneration += 1;
    workspaceDeletionTombstones = readWorkspaceDeletionTombstones();
    travelerWorkspace = readLocalWorkspace();
    if (document.querySelector('[data-traveler-workspace]')) {
      renderWorkspaceDashboard();
      hydrateWorkspacePreferences(travelerWorkspace.preferences);
    }
    refreshMapSaveControls();
    if (window.traVelV2?.isLoggedIn) scheduleWorkspaceCorrectiveSync(0, true);
  });
}

function validWorkspaceAgentRunId(value) {
  return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(String(value || ''));
}

function workspacePlanText(value, maximum = 500) {
  return typeof value === 'string' ? value.trim().slice(0, maximum) : '';
}

function workspacePlanDate(value) {
  const text = workspacePlanText(value, 40);
  return text && !Number.isNaN(new Date(text).getTime()) ? text : '';
}

function workspaceObjectHasExactKeys(value, requiredKeys) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
  const keys = Object.keys(value);
  return keys.length === requiredKeys.length && requiredKeys.every(key => keys.includes(key));
}

function workspacePlanIsoDate(value) {
  if (typeof value !== 'string') return false;
  const match = value.match(/^(\d{4})-(\d{2})-(\d{2})T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/);
  if (!match || Number.isNaN(new Date(value).getTime())) return false;
  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  return month >= 1 && month <= 12 && day >= 1 && day <= new Date(Date.UTC(year, month, 0)).getUTCDate();
}

function workspacePlanRunContractValid(rawRun) {
  const runKeys = ['run_id', 'status', 'mode', 'locale', 'summary', 'planning_context', 'readiness', 'request_revision', 'proposal_count', 'created_at', 'updated_at', 'expires_at', 'resume_available'];
  const contextKeys = ['kind', 'selection_id', 'latitude', 'longitude', 'destination', 'intent', 'scope'];
  const readinessKeys = ['status', 'blockers'];
  if (!workspaceObjectHasExactKeys(rawRun, runKeys) || !validWorkspaceAgentRunId(rawRun.run_id)) return false;
  if (!workspacePlanStatuses.has(rawRun.status)
    || !['agent', 'surprise'].includes(rawRun.mode)
    || !['he-IL', 'en-US', 'mixed'].includes(rawRun.locale)
    || typeof rawRun.summary !== 'string'
    || [...rawRun.summary].length > 500
    || typeof rawRun.resume_available !== 'boolean'
    || !Number.isInteger(rawRun.request_revision)
    || rawRun.request_revision < 0
    || !Number.isInteger(rawRun.proposal_count)
    || rawRun.proposal_count < 0
    || !workspacePlanIsoDate(rawRun.created_at)
    || !workspacePlanIsoDate(rawRun.updated_at)
    || !workspacePlanIsoDate(rawRun.expires_at)) return false;

  const planningContext = rawRun.planning_context;
  if (!workspaceObjectHasExactKeys(planningContext, contextKeys)
    || !['free_text', 'destination', 'map_point'].includes(planningContext.kind)
    || !(planningContext.selection_id === null || (typeof planningContext.selection_id === 'string' && /^[A-Za-z0-9_-]{8,80}$/.test(planningContext.selection_id)))
    || !(planningContext.latitude === null || (typeof planningContext.latitude === 'number' && Number.isFinite(planningContext.latitude) && planningContext.latitude >= -90 && planningContext.latitude <= 90))
    || !(planningContext.longitude === null || (typeof planningContext.longitude === 'number' && Number.isFinite(planningContext.longitude) && planningContext.longitude >= -180 && planningContext.longitude <= 180))
    || !(planningContext.destination === null || (typeof planningContext.destination === 'string' && /^[a-z0-9-]{1,60}$/.test(planningContext.destination)))
    || !['smart', 'value', 'easy', 'romantic', 'family', 'adventure', 'surprise'].includes(planningContext.intent)
    || !Array.isArray(planningContext.scope)
    || planningContext.scope.length > 8
    || new Set(planningContext.scope).size !== planningContext.scope.length
    || planningContext.scope.some(scope => !agentJourneyScopeKeys.has(scope))) return false;

  const readiness = rawRun.readiness;
  return workspaceObjectHasExactKeys(readiness, readinessKeys)
    && ['needs_clarification', 'ready_for_search', 'unsupported'].includes(readiness.status)
    && Array.isArray(readiness.blockers)
    && readiness.blockers.length <= 20
    && readiness.blockers.every(blocker => typeof blocker === 'string' && [...blocker].length <= 200);
}

function normalizeWorkspacePlanRun(rawRun) {
  if (!workspacePlanRunContractValid(rawRun)) return null;
  const context = rawRun.planning_context;
  return {
    run_id: String(rawRun.run_id).toLowerCase(),
    status: rawRun.status,
    mode: rawRun.mode,
    locale: rawRun.locale,
    summary: rawRun.summary,
    planning_context: {
      kind: context.kind,
      selection_id: context.selection_id || '',
      latitude: context.latitude,
      longitude: context.longitude,
      destination: context.destination || '',
      intent: context.intent,
      scope: [...context.scope]
    },
    readiness: {status: rawRun.readiness.status, blockers: [...rawRun.readiness.blockers]},
    request_revision: rawRun.request_revision,
    proposal_count: rawRun.proposal_count,
    created_at: rawRun.created_at,
    updated_at: rawRun.updated_at,
    expires_at: rawRun.expires_at,
    resume_available: rawRun.resume_available
  };
}

function normalizeWorkspacePlanPayload(payload) {
  if (!payload || typeof payload !== 'object' || !Array.isArray(payload.runs)) throw new Error('AgentRun summary response is malformed.');
  const normalizedRuns = payload.runs.map(normalizeWorkspacePlanRun);
  if (normalizedRuns.some(run => !run)) throw new Error('AgentRun summary response failed its closed contract.');
  return normalizedRuns.slice(0, 12);
}

function workspacePlanStatusLabel(status) {
  return {
    created: 'הבקשה נשמרה',
    provider_error: 'לא הצלחנו לארגן את הבקשה',
    needs_clarification: 'נדרש פרט נוסף',
    request_ready: 'פרטי החופשה מוכנים לבדיקה',
    searching: 'בדיקת האפשרויות אצל מקור המחיר התחילה',
    proposal_ready: 'התקבלו אפשרויות עם מקור ומועד בדיקה',
    approval_required: 'נדרש אישור שלכם',
    completed: 'התכנון הסתיים',
    failed: 'לא הצלחנו להשלים את התכנון',
    cancelled: 'התוכנית בוטלה'
  }[status] || 'התוכנית עודכנה';
}

function workspacePlanNextAction(status) {
  return {
    created: 'הפרטים נשמרו. השלב הבא יופיע רק לאחר עדכון מאומת.',
    provider_error: 'אפשר לנסות תוכנית חדשה. לא מוצגים חיפוש, מחיר או זמינות.',
    needs_clarification: 'חזרו לתוכנית והשלימו את הפרט החסר כדי להמשיך.',
    request_ready: 'בדקו את פרטי החופשה ושלחו אותם לבדיקה אישית אם תרצו הצעה.',
    searching: 'בדיקת האפשרויות התחילה. עדיין אין כאן מחיר או זמינות מאושרים.',
    proposal_ready: 'בדקו את המקור, זמן הבדיקה והתנאים לפני כל פעולה נוספת.',
    approval_required: 'חזרו לתוכנית ובדקו מה בדיוק דורש את אישורכם.',
    completed: 'בדקו את הרשומה האחרונה. מצב זה אינו אישור להזמנה או לתשלום.',
    failed: 'המצב האחרון נשמר. אפשר להתחיל תוכנית חדשה בלי לטעון שהפעולה הושלמה.',
    cancelled: 'התוכנית אינה פעילה. אפשר להתחיל תוכנית חדשה.'
  }[status] || 'בדקו את העדכון האחרון לפני שתמשיכו.';
}

function workspacePlanTransition(previousRun, nextRun) {
  if (!previousRun || previousRun.run_id !== nextRun?.run_id) {
    if (workspacePlanAttentionStatuses.has(nextRun?.status)) return 'attention';
    if (nextRun?.status === 'completed') return 'completed';
    if (['failed', 'cancelled'].includes(nextRun?.status)) return 'terminal';
    return 'added';
  }
  const previousRevision = Number(previousRun.request_revision) || 0;
  const nextRevision = Number(nextRun.request_revision) || 0;
  const previousUpdated = Date.parse(previousRun.updated_at || '') || 0;
  const nextUpdated = Date.parse(nextRun.updated_at || '') || 0;
  const confirmedNewer = nextRevision > previousRevision || nextUpdated > previousUpdated;
  if (!confirmedNewer) return workspacePlanAttentionStatuses.has(nextRun.status) ? 'attention' : (workspacePlanTerminalStatuses.has(nextRun.status) ? 'terminal' : 'confirmed');
  if (workspacePlanAttentionStatuses.has(previousRun.status)
    && !workspacePlanAttentionStatuses.has(nextRun.status)
    && !workspacePlanTerminalStatuses.has(nextRun.status)) return 'recovered';
  if (nextRevision > previousRevision
    && !workspacePlanAttentionStatuses.has(nextRun.status)
    && !workspacePlanTerminalStatuses.has(nextRun.status)) return 'advanced';
  if (workspacePlanAttentionStatuses.has(nextRun.status)) return 'attention';
  if (nextRun.status === 'completed') return 'completed';
  if (['failed', 'cancelled'].includes(nextRun.status)) return 'terminal';
  const lifecycleRank = {
    created: 0,
    request_ready: 1,
    searching: 2,
    proposal_ready: 3,
    approval_required: 4,
    completed: 5
  };
  if (nextRevision === previousRevision
    && nextRun.status !== previousRun.status
    && Number.isInteger(lifecycleRank[nextRun.status])
    && lifecycleRank[nextRun.status] > (lifecycleRank[previousRun.status] ?? -1)) return 'status_changed';
  return 'confirmed';
}

function workspacePlanAnnouncementDiscriminator(run) {
  const updated = formatWorkspacePlanTime(run?.updated_at);
  return updated ? `עודכן ${updated}` : 'העדכון האחרון נשמר';
}

function workspacePlanProgress(run) {
  const stages = [
    {label: 'בקשה', copy: 'הפרטים נשמרו', state: 'completed'},
    {label: 'פרטי חופשה', copy: 'ממתינים לארגון', state: 'pending'},
    {label: 'בחירה', copy: 'עדיין לא נקבעה', state: 'pending'}
  ];
  if (workspacePlanAttentionStatuses.has(run.status)) {
    stages[1] = {label: 'פרטי חופשה', copy: workspacePlanStatusLabel(run.status), state: 'attention'};
    return stages;
  }
  const hasStructuredEvidence = run.request_revision > 0 || run.readiness?.status === 'ready_for_search';
  if (hasStructuredEvidence) {
    stages[1] = {label: 'פרטי חופשה', copy: 'הפרטים אורגנו', state: 'completed'};
  } else if (workspacePlanTerminalStatuses.has(run.status)) {
    stages[1] = {label: 'פרטי חופשה', copy: 'הפרטים לא הושלמו', state: 'pending'};
  } else {
    stages[1] = {label: 'פרטי חופשה', copy: 'מסדרים את הפרטים', state: 'current'};
  }
  if (run.status === 'completed') {
    stages[2] = {label: 'בחירה', copy: 'התכנון הסתיים', state: 'completed'};
  } else if (['failed', 'cancelled'].includes(run.status)) {
    stages[2] = {label: 'בחירה', copy: workspacePlanStatusLabel(run.status), state: 'terminal'};
  } else if (!['created'].includes(run.status)) {
    stages[2] = {label: 'בחירה', copy: workspacePlanStatusLabel(run.status), state: 'current'};
  }
  return stages;
}

function workspacePlanScopeLabel(scope) {
  return {
    flights: 'טיסות', accommodation: 'לינה', transfers: 'העברות', activities: 'פעילויות',
    dining: 'אוכל', insurance: 'ביטוח', connectivity: 'תקשורת', equipment: 'ציוד'
  }[scope] || scope;
}

function workspacePlanTitle(run) {
  if (run.summary) return run.summary;
  if (run.planning_context.destination) return run.planning_context.destination;
  if (run.planning_context.intent) return run.planning_context.intent;
  return run.mode === 'surprise' ? 'תוכנית הפתעה פרטית' : 'תוכנית נסיעה פרטית';
}

function workspacePlanMapContext(run) {
  const latitude = run.planning_context.latitude;
  const longitude = run.planning_context.longitude;
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return '';
  return `מיקום במפה ${latitude.toFixed(2)}, ${longitude.toFixed(2)}`;
}

function formatWorkspacePlanTime(value) {
  if (!value) return '';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '';
  return parsed.toLocaleString('he-IL', {day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'});
}

function renderWorkspacePlanCard(run, transition = 'confirmed') {
  const card = document.createElement('article');
  card.className = `workspace-plan-card${['advanced', 'recovered', 'added', 'status_changed', 'completed'].includes(transition) ? ' is-advancing' : ''}`;
  card.dataset.workspacePlan = run.run_id;
  card.dataset.state = workspacePlanAttentionStatuses.has(run.status)
    ? 'attention'
    : run.status === 'completed'
      ? 'completed'
      : ['failed', 'cancelled'].includes(run.status)
        ? 'terminal'
        : 'confirmed';
  card.dataset.transition = transition;
  const titleId = `workspace-plan-${run.run_id}`;
  card.setAttribute('aria-labelledby', titleId);

  const head = document.createElement('header');
  head.className = 'workspace-plan-card-head';
  const identity = document.createElement('div');
  appendTextElement(identity, 'span', run.mode === 'surprise' ? 'תכנון הפתעה' : 'תכנון אישי', 'workspace-plan-kind');
  const title = appendTextElement(identity, 'h3', workspacePlanTitle(run));
  title.id = titleId;
  const status = document.createElement('span');
  status.className = 'workspace-plan-status';
  status.textContent = workspacePlanStatusLabel(run.status);
  status.dataset.state = card.dataset.state;
  head.append(identity, status);
  card.append(head);

  const meta = document.createElement('div');
  meta.className = 'workspace-plan-meta';
  appendTextElement(meta, 'span', run.request_revision > 0 ? 'פרטי החופשה מסודרים' : 'ממתינים להשלמת פרטים');
  const updated = appendTextElement(meta, 'time', run.updated_at ? `עודכן ${formatWorkspacePlanTime(run.updated_at)}` : 'ממתין לעדכון');
  updated.dateTime = run.updated_at || '';
  card.append(meta);

  const facts = [run.planning_context.destination, run.planning_context.intent, workspacePlanMapContext(run)].filter(Boolean);
  if (facts.length) {
    const factList = document.createElement('ul');
    factList.className = 'workspace-plan-facts';
    factList.setAttribute('aria-label', 'פרטי התוכנית שאושרו');
    facts.slice(0, 3).forEach(value => appendTextElement(factList, 'li', value));
    card.append(factList);
  }

  const progress = document.createElement('ol');
  progress.className = 'workspace-plan-progress';
  progress.setAttribute('aria-label', 'התקדמות מאומתת של התוכנית');
  workspacePlanProgress(run).forEach((stage, index) => {
    const item = document.createElement('li');
    item.dataset.state = stage.state;
    if (['current', 'attention'].includes(stage.state)) item.setAttribute('aria-current', 'step');
    appendTextElement(item, 'span', String(index + 1));
    const copy = document.createElement('div');
    appendTextElement(copy, 'strong', stage.label);
    appendTextElement(copy, 'small', stage.copy);
    item.append(copy);
    progress.append(item);
  });
  card.append(progress);

  if (run.planning_context.scope.length) {
    const scope = document.createElement('section');
    scope.className = 'workspace-plan-scope';
    appendTextElement(scope, 'strong', 'נכלל בבקשה, לא נבדק אצל ספקים');
    const list = document.createElement('ul');
    run.planning_context.scope.forEach(value => appendTextElement(list, 'li', workspacePlanScopeLabel(value)));
    scope.append(list);
    card.append(scope);
  }

  const next = document.createElement('aside');
  next.className = 'workspace-plan-next';
  const nextIcon = document.createElement('i');
  nextIcon.dataset.lucide = workspacePlanAttentionStatuses.has(run.status) ? 'circle-alert' : 'move-left';
  const nextCopy = document.createElement('div');
  appendTextElement(nextCopy, 'small', 'הפעולה הבאה');
  appendTextElement(nextCopy, 'strong', workspacePlanNextAction(run.status));
  next.append(nextIcon, nextCopy);
  card.append(next);

  const actions = document.createElement('div');
  actions.className = 'workspace-plan-actions';
  const action = document.createElement('button');
  action.type = 'button';
  action.dataset.workspacePlanAction = 'primary';
  const canResume = run.resume_available === true && validWorkspaceAgentRunId(run.run_id);
  const actionIcon = document.createElement('i');
  actionIcon.dataset.lucide = canResume ? 'play' : 'sparkles';
  action.append(actionIcon, document.createTextNode(canResume ? 'המשיכו את התוכנית' : 'התחילו תוכנית חדשה'));
  action.addEventListener('click', () => {
    if (canResume && !storeAgentRunSession(run.run_id)) {
      action.dataset.state = 'error';
      const announcer = document.querySelector('[data-workspace-cockpit-announcer]');
      setTextContentIfChanged(announcer, 'לא ניתן לפתוח את המשך התוכנית כי אחסון ההפעלה הפרטי אינו זמין בדפדפן. אפשרו אחסון זמני ונסו שוב.');
      return;
    }
    if (!canResume) clearAgentRunSession();
    window.location.assign(safeWorkspaceHref(`${window.traVelV2?.homeUrl || '/'}ai-planner/`));
  });
  actions.append(action);
  card.append(actions);
  appendTextElement(card, 'small', 'אין בכרטיס הזה אישור למחיר, לזמינות, לתשלום או להזמנה.', 'workspace-plan-safety');
  return card;
}

function setWorkspaceCockpitState(state, message, announce = '', retry = false) {
  const root = document.querySelector('[data-workspace-cockpit]');
  if (!root) return;
  root.dataset.state = state;
  root.setAttribute('aria-busy', String(state === 'loading'));
  const status = root.querySelector('[data-workspace-cockpit-status]');
  const announcer = root.querySelector('[data-workspace-cockpit-announcer]');
  const retryButton = root.querySelector('[data-workspace-cockpit-retry]');
  setTextContentIfChanged(status, message);
  if (announce) setTextContentIfChanged(announcer, announce);
  if (retryButton) retryButton.hidden = !retry;
}

function workspacePlanSnapshot(runs) {
  return new Map(runs.map(run => [run.run_id, {
    run_id: run.run_id,
    status: run.status,
    request_revision: run.request_revision,
    updated_at: run.updated_at,
    fingerprint: JSON.stringify(run)
  }]));
}

function workspacePlanSnapshotsEqual(left, right) {
  if (left.size !== right.size) return false;
  return [...right].every(([runId, next]) => left.get(runId)?.fingerprint === next.fingerprint);
}

function workspacePlanCardAction(card) {
  if (!card) return null;
  const queried = card.querySelector?.('[data-workspace-plan-action]');
  if (queried) return queried;
  const queue = [...(card.children || [])];
  while (queue.length) {
    const child = queue.shift();
    if (child?.dataset && Object.prototype.hasOwnProperty.call(child.dataset, 'workspacePlanAction')) return child;
    queue.push(...(child?.children || []));
  }
  return null;
}

function renderWorkspacePlans(runs, transitions = new Map(), options = {}) {
  const root = document.querySelector('[data-workspace-cockpit]');
  const list = root?.querySelector('[data-workspace-plan-list]');
  const empty = root?.querySelector('[data-workspace-plan-empty]');
  if (!root || !list) return;
  const replaceCards = options.replaceCards !== false;
  const focusSnapshot = replaceCards ? captureWorkspaceListFocus(list, 'workspacePlan', 'workspacePlanAction') : null;
  let focusRestored = false;
  if (replaceCards) {
    list.replaceChildren(...runs.map(run => renderWorkspacePlanCard(run, options.initialLoad ? 'confirmed' : (transitions.get(run.run_id) || 'confirmed'))));
    if (empty) empty.hidden = runs.length > 0;
    focusRestored = restoreWorkspaceListFocus(list, focusSnapshot, 'workspacePlan', 'workspacePlanAction', empty);
  }
  else if (empty) empty.hidden = runs.length > 0;

  const recovered = runs.find(run => transitions.get(run.run_id) === 'recovered');
  const advanced = runs.find(run => transitions.get(run.run_id) === 'advanced');
  const statusChanged = runs.find(run => transitions.get(run.run_id) === 'status_changed');
  const newlyAdded = runs.find(run => transitions.get(run.run_id) === 'added');
  const newlyCompleted = runs.find(run => transitions.get(run.run_id) === 'completed');
  const previousRuns = options.previousRuns && typeof options.previousRuns.get === 'function' && typeof options.previousRuns.values === 'function'
    ? options.previousRuns
    : new Map();
  const removed = [...previousRuns.values()].find(previousRun => !runs.some(run => run.run_id === previousRun.run_id));
  const newlyAttention = runs.find(run => workspacePlanAttentionStatuses.has(run.status) && !workspacePlanAttentionStatuses.has(previousRuns.get(run.run_id)?.status));
  const newlyTerminal = runs.find(run => workspacePlanTerminalStatuses.has(run.status) && !workspacePlanTerminalStatuses.has(previousRuns.get(run.run_id)?.status));
  let state = 'confirmed';
  let announce = '';
  if (options.initialLoad) {
    announce = runs.length
      ? `טעינת התוכניות הושלמה ואושרה. נמצאו ${runs.length} תוכניות פרטיות. ${workspacePlanAnnouncementDiscriminator(runs[0])}.`
      : 'טעינת התוכניות הושלמה ואושרה. אין כרגע תוכניות פרטיות שמורות.';
  } else if (removed) {
    announce = `התוכנית ${workspacePlanTitle(removed)} אינה מופיעה עוד בחשבון, למשל לאחר תפוגה או הסרה.${focusRestored ? ' המיקוד הועבר לפעולה הזמינה הקרובה.' : ''}`;
  } else if (newlyCompleted) {
    state = 'completed';
    announce = `תהליך התכנון של ${workspacePlanTitle(newlyCompleted)} הושלם לפי העדכון המאומת האחרון. אין בכך אישור למחיר, לתשלום או להזמנה. ${workspacePlanAnnouncementDiscriminator(newlyCompleted)}.`;
  } else if (newlyAdded) {
    state = 'advanced';
    announce = `תוכנית פרטית חדשה נוספה לחשבון: ${workspacePlanTitle(newlyAdded)}. ${workspacePlanAnnouncementDiscriminator(newlyAdded)}.`;
  } else if (recovered) {
    state = 'recovered';
    announce = `התוכנית ${workspacePlanTitle(recovered)} חזרה למצב תקין לאחר עדכון מאומת. ${workspacePlanAnnouncementDiscriminator(recovered)}.`;
  } else if (advanced) {
    state = 'advanced';
    announce = `פרטים חדשים בתוכנית ${workspacePlanTitle(advanced)} נשמרו ואושרו. ${workspacePlanAnnouncementDiscriminator(advanced)}.`;
  } else if (statusChanged) {
    announce = `מצב התוכנית ${workspacePlanTitle(statusChanged)} השתנה לפי עדכון מאומת חדש. ${workspacePlanAnnouncementDiscriminator(statusChanged)}.`;
  } else if (newlyAttention) {
    announce = `התוכנית ${workspacePlanTitle(newlyAttention)} דורשת תשומת לב לפי העדכון האחרון. ${workspacePlanAnnouncementDiscriminator(newlyAttention)}.`;
  } else if (newlyTerminal) {
    announce = `התוכנית ${workspacePlanTitle(newlyTerminal)} עברה למצב סיום מאומת. ${workspacePlanAnnouncementDiscriminator(newlyTerminal)}.`;
  }
  if (runs.some(run => workspacePlanAttentionStatuses.has(run.status))) {
    if (state === 'confirmed') state = 'attention';
  } else if (runs.length && runs.every(run => workspacePlanTerminalStatuses.has(run.status))) {
    if (state === 'confirmed') state = 'terminal';
  }
  const message = runs.length
    ? `${runs.length} תוכניות פרטיות · עודכנו כעת`
    : 'אין כרגע תוכניות פרטיות שמורות. אפשר להתחיל תוכנית חדשה.';
  setWorkspaceCockpitState(state, message, announce, false);
  renderIcons();
}

function mergeWorkspacePlanRuns(normalizedRuns) {
  return (Array.isArray(normalizedRuns) ? normalizedRuns : []).slice(0, 12).map(run => {
    const previous = workspacePlanRuntime.runs.get(run.run_id);
    if (!previous) return run;
    const lowerRevision = run.request_revision < previous.request_revision;
    const olderSameRevision = run.request_revision === previous.request_revision
      && (Date.parse(run.updated_at || '') || 0) < (Date.parse(previous.updated_at || '') || 0);
    return lowerRevision || olderSameRevision ? previous : run;
  });
}

function scheduleWorkspacePlanPoll(delay = 30000) {
  if (workspacePlanRuntime.timer) window.clearTimeout(workspacePlanRuntime.timer);
  workspacePlanRuntime.timer = 0;
  if (!document.querySelector('[data-workspace-cockpit]')
    || document.visibilityState === 'hidden'
    || workspacePlanRuntime.authRequired) return;
  workspacePlanRuntime.timer = window.setTimeout(() => loadWorkspacePlans({polling: true}), delay);
}

async function loadWorkspacePlans({polling = false, manual = false} = {}) {
  const root = document.querySelector('[data-workspace-cockpit]');
  if (!root || workspacePlanRuntime.inFlight || workspacePlanRuntime.authRequired) return;
  workspacePlanRuntime.inFlight = true;
  const controller = typeof AbortController === 'function' ? new AbortController() : null;
  workspacePlanRuntime.controller = controller;
  let timedOut = false;
  const timeoutId = controller ? window.setTimeout(() => {
    timedOut = true;
    controller.abort();
  }, 15000) : 0;
  if (!polling || manual) setWorkspaceCockpitState('loading', 'טוענים את התוכניות הפרטיות מהחשבון...', '', false);
  try {
    const payload = await agentApiRequest('/runs?limit=12', controller ? {signal: controller.signal} : {});
    const runs = mergeWorkspacePlanRuns(normalizeWorkspacePlanPayload(payload));
    const previousRuns = new Map(workspacePlanRuntime.runs);
    const previousSnapshot = workspacePlanRuntime.snapshot;
    const nextSnapshot = workspacePlanSnapshot(runs);
    const initialLoad = !workspacePlanRuntime.hasLoaded;
    const changed = initialLoad || !workspacePlanSnapshotsEqual(previousSnapshot, nextSnapshot);
    const transitions = new Map(runs.map(run => [run.run_id, workspacePlanTransition(previousRuns.get(run.run_id), run)]));
    workspacePlanRuntime.runs = new Map(runs.map(run => [run.run_id, run]));
    workspacePlanRuntime.snapshot = nextSnapshot;
    workspacePlanRuntime.hasLoaded = true;
    workspacePlanRuntime.failures = 0;
    workspacePlanRuntime.authRequired = false;
    renderWorkspacePlans(runs, transitions, {replaceCards: changed, initialLoad, previousRuns});
  } catch (error) {
    const hiddenAbort = error?.name === 'AbortError' && !timedOut && document.visibilityState === 'hidden';
    const authRequired = error?.status === 401 || error?.status === 403;
    if (authRequired) {
      workspacePlanRuntime.authRequired = true;
      workspacePlanRuntime.failures = 0;
      setWorkspaceCockpitState(
        'reauth_required',
        'החיבור הפרטי לחשבון פג. התחברו מחדש ורעננו את העמוד כדי לקבל עדכונים מאומתים.',
        'עדכוני התוכניות נעצרו כי נדרשת התחברות מחדש. לא תוצג התקדמות נוספת עד לחיבור מאומת.',
        false
      );
    } else if (!hiddenAbort) {
      workspacePlanRuntime.failures = Math.min(8, workspacePlanRuntime.failures + 1);
      const hasConfirmedPlans = workspacePlanRuntime.runs.size > 0;
      setWorkspaceCockpitState(
        hasConfirmedPlans ? 'stale' : 'error',
        hasConfirmedPlans
          ? 'העדכון החי אינו זמין. המצב האחרון שאושר נשאר מוצג.'
          : 'לא הצלחנו לטעון תוכניות פרטיות. לא הוצג מצב משוער.',
        hasConfirmedPlans ? 'העדכון נעצר. המצב האחרון שאושר נשאר מוצג ללא התקדמות חדשה.' : 'טעינת התוכניות נכשלה. לא הוצג מצב משוער.',
        true
      );
    } else {
      const hasConfirmedPlans = workspacePlanRuntime.runs.size > 0;
      setWorkspaceCockpitState(
        hasConfirmedPlans ? 'confirmed' : 'idle',
        hasConfirmedPlans
          ? `${workspacePlanRuntime.runs.size} תוכניות פרטיות · עודכנו כעת`
          : 'התוכניות הפרטיות ייטענו כשהעמוד יחזור להיות פעיל.',
        '',
        false
      );
    }
  } finally {
    if (timeoutId) window.clearTimeout(timeoutId);
    if (workspacePlanRuntime.controller === controller) workspacePlanRuntime.controller = null;
    workspacePlanRuntime.inFlight = false;
    const retryDelay = workspacePlanRuntime.failures
      ? Math.min(180000, 15000 * (2 ** Math.min(workspacePlanRuntime.failures - 1, 3)))
      : 30000;
    if (!workspacePlanRuntime.authRequired) scheduleWorkspacePlanPoll(retryDelay);
  }
}

function workspaceAssistedProposalState(caseId) {
  const normalizedCaseId = String(caseId || '').toLowerCase();
  if (!workspaceAssistedProposalRuntime.has(normalizedCaseId)) {
    workspaceAssistedProposalRuntime.set(normalizedCaseId, {
      open: false,
      loaded: false,
      loading: false,
      proposals: [],
      error: '',
      stale: false,
      loadedRequestRevision: 0,
      expanded: new Set(),
      messages: new Map(),
      confirmedUpdateId: ''
    });
  }
  return workspaceAssistedProposalRuntime.get(normalizedCaseId);
}

function workspaceAssistedProposalUuid(value) {
  const normalized = String(value || '').toLowerCase();
  return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/.test(normalized) ? normalized : '';
}

function workspaceAssistedProposalString(value, maximum = 800, allowEmpty = false) {
  if (typeof value !== 'string') return null;
  const normalized = value.trim();
  if ((!allowEmpty && !normalized) || [...normalized].length > maximum) return null;
  return normalized;
}

function workspaceAssistedProposalDate(value, nullable = false) {
  if (nullable && value === null) return null;
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/.test(value.trim())) return null;
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : parsed.toISOString();
}

function workspaceAssistedProposalStringList(value, maximumItems, maximumLength, minimumItems = 0) {
  if (!Array.isArray(value) || value.length < minimumItems || value.length > maximumItems) return null;
  const normalized = value.map(item => workspaceAssistedProposalString(item, maximumLength));
  return normalized.some(item => item === null) ? null : normalized;
}

function safeWorkspaceAssistedProposalSourceUrl(value) {
  if (value === null) return '';
  try {
    const url = new URL(String(value || ''));
    if (url.protocol !== 'https:' || url.username || url.password || url.port || url.search || url.hash) return '';
    return url.href;
  } catch (error) {
    return '';
  }
}

function normalizeWorkspaceAssistedProposalSource(rawSource) {
  if (!rawSource || typeof rawSource !== 'object' || Array.isArray(rawSource)) return null;
  if (['provider_code', 'relationship', 'source_reference', 'evidence_digest'].some(key => Object.prototype.hasOwnProperty.call(rawSource, key))) return null;
  const sourceId = workspaceAssistedProposalUuid(rawSource.source_id);
  const sourceTypes = new Set(['connected_api', 'supplier_portal', 'supplier_written_quote', 'public_supplier_page', 'official_information']);
  const publicLabel = workspaceAssistedProposalString(rawSource.public_label, 190);
  const supplierName = workspaceAssistedProposalString(rawSource.supplier_name, 190, true);
  const sellerName = workspaceAssistedProposalString(rawSource.seller_name, 190, true);
  const observedAt = workspaceAssistedProposalDate(rawSource.observed_at);
  const freshUntil = workspaceAssistedProposalDate(rawSource.fresh_until);
  const rawUrlIsNull = rawSource.source_url === null;
  const sourceUrl = safeWorkspaceAssistedProposalSourceUrl(rawSource.source_url);
  const publicSource = ['public_supplier_page', 'official_information'].includes(rawSource.source_type);
  if (!sourceId || rawSource.contract_version !== '1.0.0' || !sourceTypes.has(rawSource.source_type)
    || !publicLabel || supplierName === null || sellerName === null
    || !observedAt || !freshUntil
    || Date.parse(observedAt) >= Date.parse(freshUntil)
    || rawSource.requires_revalidation !== true || (!rawUrlIsNull && !sourceUrl)
    || (publicSource && !sourceUrl) || (!publicSource && !rawUrlIsNull)) return null;
  return {
    source_id: sourceId,
    source_type: rawSource.source_type,
    public_label: publicLabel,
    supplier_name: supplierName,
    seller_name: sellerName,
    source_url: sourceUrl,
    observed_at: observedAt,
    fresh_until: freshUntil
  };
}

function normalizeWorkspaceAssistedProposalPrice(rawPrice) {
  if (!rawPrice || typeof rawPrice !== 'object' || Array.isArray(rawPrice) || typeof rawPrice.priced !== 'boolean') return null;
  const currencies = new Set(['ILS', 'USD', 'EUR']);
  const pricedBases = new Set(['trip_total', 'stay_total', 'ticket_total', 'activity_total', 'item_total']);
  const inclusionStates = new Set(['included', 'excluded', 'unknown']);
  if (!inclusionStates.has(rawPrice.taxes) || !inclusionStates.has(rawPrice.fees)) return null;
  if (!rawPrice.priced) {
    if (rawPrice.total_for_party_minor !== null || rawPrice.currency !== null || rawPrice.basis !== 'not_priced'
      || rawPrice.taxes !== 'unknown' || rawPrice.fees !== 'unknown') return null;
    return {priced: false, total_for_party_minor: null, currency: null, basis: 'not_priced', taxes: 'unknown', fees: 'unknown'};
  }
  const total = rawPrice.total_for_party_minor;
  if (!Number.isSafeInteger(total) || total < 0 || total > 1000000000000 || !currencies.has(rawPrice.currency) || !pricedBases.has(rawPrice.basis)) return null;
  return {priced: true, total_for_party_minor: total, currency: rawPrice.currency, basis: rawPrice.basis, taxes: rawPrice.taxes, fees: rawPrice.fees};
}

function normalizeWorkspaceAssistedProposalComponent(rawComponent) {
  if (!rawComponent || typeof rawComponent !== 'object' || Array.isArray(rawComponent)) return null;
  const key = String(rawComponent.component_key || '');
  const title = workspaceAssistedProposalString(rawComponent.title, 200);
  const description = workspaceAssistedProposalString(rawComponent.description, 800);
  const price = normalizeWorkspaceAssistedProposalPrice(rawComponent.price);
  const conditions = rawComponent.conditions;
  const sourceIds = Array.isArray(rawComponent.source_ids) ? rawComponent.source_ids.map(workspaceAssistedProposalUuid) : [];
  if (!/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/.test(key) || key.length > 64
    || !workspaceAssistedProposalCategories.some(category => category.key === rawComponent.category)
    || !title || !description || !price || !conditions || typeof conditions !== 'object' || Array.isArray(conditions)
    || sourceIds.length < 1 || sourceIds.length > 8 || sourceIds.some(sourceId => !sourceId)
    || new Set(sourceIds).size !== sourceIds.length || rawComponent.requires_revalidation !== true) return null;
  const cancellation = workspaceAssistedProposalString(conditions.cancellation, 500);
  const changes = workspaceAssistedProposalString(conditions.changes, 500);
  const inclusions = workspaceAssistedProposalString(conditions.baggage_or_inclusions, 500);
  if (!cancellation || !changes || !inclusions) return null;
  return {
    component_key: key,
    category: rawComponent.category,
    title,
    description,
    price,
    conditions: {cancellation, changes, baggage_or_inclusions: inclusions},
    source_ids: sourceIds,
    requires_revalidation: true
  };
}

function normalizeWorkspaceAssistedProposal(rawProposal, expectedCaseId = '') {
  if (!rawProposal || typeof rawProposal !== 'object' || Array.isArray(rawProposal) || rawProposal.contract_version !== '1.0.0') return null;
  if (Object.prototype.hasOwnProperty.call(rawProposal, 'source_set_digest')) return null;
  const proposalId = workspaceAssistedProposalUuid(rawProposal.proposal_id);
  const caseId = workspaceAssistedProposalUuid(rawProposal.case_id);
  const expected = workspaceAssistedProposalUuid(expectedCaseId);
  const statuses = new Set(['available', 'withdrawn', 'expired', 'superseded']);
  const positions = new Set(['best_value', 'lowest_friction', 'most_flexible', 'most_memorable', 'custom']);
  const dispositions = new Set(['unavailable', 'awaiting_review', 'reviewed', 'changes_requested', 'contact_authorized', 'declined']);
  const actionSet = new Set(['review', 'request_changes', 'authorize_contact', 'decline']);
  const reference = workspaceAssistedProposalString(rawProposal.reference, 16);
  const title = workspaceAssistedProposalString(rawProposal.title, 160);
  const summary = workspaceAssistedProposalString(rawProposal.summary, 800);
  const version = rawProposal.version;
  const revision = rawProposal.revision;
  const publishedRevision = rawProposal.published_revision;
  const whyItFits = workspaceAssistedProposalStringList(rawProposal.why_it_fits, 6, 240, 1);
  const tradeOffs = workspaceAssistedProposalStringList(rawProposal.trade_offs, 6, 240, 1);
  if (!proposalId || !caseId || (expected && caseId !== expected) || !/^TVP-(?:[A-Z0-9]{8}|[A-Z0-9]{12})$/.test(reference || '')
    || !statuses.has(rawProposal.status) || !positions.has(rawProposal.position) || !dispositions.has(rawProposal.traveler_disposition)
    || !Number.isSafeInteger(version) || version < 1 || !Number.isSafeInteger(revision) || revision < 1
    || !Number.isSafeInteger(publishedRevision) || publishedRevision < 1 || !title || !summary || !whyItFits || !tradeOffs) return null;

  const addresses = rawProposal.addresses;
  const caseRevision = addresses?.case_revision;
  if (!addresses || typeof addresses !== 'object' || Object.prototype.hasOwnProperty.call(addresses, 'request_digest')
    || !Number.isSafeInteger(caseRevision) || caseRevision < 1) return null;

  const route = rawProposal.route;
  const origin = workspaceAssistedProposalString(route?.origin, 120);
  const destinations = workspaceAssistedProposalStringList(route?.destinations, 8, 80, 1);
  const routeModes = new Set(['flight', 'rail', 'road', 'ferry', 'walk', 'other']);
  const legs = Array.isArray(route?.legs) && route.legs.length <= 12 ? route.legs.map(rawLeg => {
    const sequence = rawLeg?.sequence;
    const from = workspaceAssistedProposalString(rawLeg?.from, 120);
    const to = workspaceAssistedProposalString(rawLeg?.to, 120);
    return Number.isSafeInteger(sequence) && sequence >= 1 && sequence <= 12 && from && to && routeModes.has(rawLeg?.mode)
      ? {sequence, from, to, mode: rawLeg.mode}
      : null;
  }) : null;
  if (!origin || !destinations || !legs || legs.some(leg => !leg)
    || new Set(legs.map(leg => leg.sequence)).size !== legs.length) return null;

  const itinerary = Array.isArray(rawProposal.itinerary) && rawProposal.itinerary.length >= 1 && rawProposal.itinerary.length <= 31
    ? rawProposal.itinerary.map(rawDay => {
      const day = rawDay?.day;
      const place = workspaceAssistedProposalString(rawDay?.place, 120);
      const dayTitle = workspaceAssistedProposalString(rawDay?.title, 200);
      const keys = Array.isArray(rawDay?.component_keys) && rawDay.component_keys.length <= 16
        ? rawDay.component_keys.map(key => String(key || ''))
        : null;
      return Number.isSafeInteger(day) && day >= 1 && day <= 365 && place && dayTitle && keys
        && new Set(keys).size === keys.length
        && keys.every(key => /^[a-z0-9]+(?:[-_][a-z0-9]+)*$/.test(key) && key.length <= 64)
        ? {day, place, title: dayTitle, component_keys: keys}
        : null;
    })
    : null;
  if (!itinerary || itinerary.some(day => !day)
    || new Set(itinerary.map(day => day.day)).size !== itinerary.length) return null;

  const components = Array.isArray(rawProposal.components) && rawProposal.components.length >= 1 && rawProposal.components.length <= 16
    ? rawProposal.components.map(normalizeWorkspaceAssistedProposalComponent)
    : null;
  const sources = Array.isArray(rawProposal.sources) && rawProposal.sources.length >= 1 && rawProposal.sources.length <= 32
    ? rawProposal.sources.map(normalizeWorkspaceAssistedProposalSource)
    : null;
  if (!components || components.some(component => !component) || !sources || sources.some(source => !source)
    || new Set(components.map(component => component.component_key)).size !== components.length
    || new Set(sources.map(source => source.source_id)).size !== sources.length) return null;
  const componentKeys = new Set(components.map(component => component.component_key));
  if (itinerary.some(day => day.component_keys.some(componentKey => !componentKeys.has(componentKey)))) return null;
  const sourceIds = new Set(sources.map(source => source.source_id));
  if (components.some(component => component.source_ids.some(sourceId => !sourceIds.has(sourceId)))) return null;
  const latestSourceObservedAt = Math.max(...sources.map(source => Date.parse(source.observed_at)));
  const earliestSourceFreshUntil = Math.min(...sources.map(source => Date.parse(source.fresh_until)));

  const ledger = rawProposal.ledger;
  const ledgerCurrency = ledger?.currency === null ? null : String(ledger?.currency || '');
  const pricedTotal = ledger?.priced_total_minor;
  const pricedCount = ledger?.priced_component_count;
  const unpricedKeys = Array.isArray(ledger?.unpriced_component_keys) ? ledger.unpriced_component_keys.map(key => String(key || '')) : null;
  const pricedComponents = components.filter(component => component.price.priced);
  const calculatedTotal = pricedComponents.reduce((sum, component) => sum + component.price.total_for_party_minor, 0);
  const calculatedUnpricedKeys = components.filter(component => !component.price.priced).map(component => component.component_key).sort();
	const calculatedCompletePricing = calculatedUnpricedKeys.length === 0
		&& components.every(component => component.price.priced && component.price.taxes === 'included' && component.price.fees === 'included');
  if (!ledger || ledger.contract_version !== '1.0.0' || !['ILS', 'USD', 'EUR', null].includes(ledgerCurrency)
    || !Number.isSafeInteger(pricedTotal) || pricedTotal < 0 || !Number.isSafeInteger(pricedCount) || pricedCount < 0
    || !unpricedKeys || unpricedKeys.some(key => !/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/.test(key))
    || typeof ledger.complete_pricing !== 'boolean' || Object.prototype.hasOwnProperty.call(ledger, 'calculation_digest')
    || pricedCount !== pricedComponents.length || pricedTotal !== calculatedTotal
    || JSON.stringify([...unpricedKeys].sort()) !== JSON.stringify(calculatedUnpricedKeys)
    || pricedComponents.some(component => component.price.currency !== ledgerCurrency)
    || (pricedCount === 0 && ledgerCurrency !== null) || (pricedCount > 0 && ledgerCurrency === null)
		|| ledger.complete_pricing !== calculatedCompletePricing) return null;

  const freshness = rawProposal.freshness;
  const checkedAt = workspaceAssistedProposalDate(freshness?.checked_at);
  const freshExpiresAt = workspaceAssistedProposalDate(freshness?.expires_at);
  const unresolvedCodes = new Set(['unpriced_component', 'taxes_unknown', 'fees_unknown', 'availability_revalidation', 'policy_revalidation', 'schedule_revalidation', 'other']);
  const unresolvedItems = Array.isArray(rawProposal.unresolved_items) && rawProposal.unresolved_items.length <= 16
    ? rawProposal.unresolved_items.map(item => {
      const label = workspaceAssistedProposalString(item?.label, 240);
      return label && unresolvedCodes.has(item?.code) ? {code: item.code, label} : null;
    })
    : null;
  if (!freshness || !checkedAt || !freshExpiresAt || freshness.requires_revalidation !== true
    || !unresolvedItems || unresolvedItems.some(item => !item)) return null;

  const disclosure = rawProposal.disclosure;
  if (!disclosure || disclosure.commercial_state !== 'non_binding_assisted_proposal'
    || disclosure.final_quote_required !== true || disclosure.message !== workspaceAssistedProposalDisclosure) return null;
  const createdAt = workspaceAssistedProposalDate(rawProposal.created_at);
  const publishedAt = workspaceAssistedProposalDate(rawProposal.published_at);
  const expiresAt = workspaceAssistedProposalDate(rawProposal.expires_at);
  if (!createdAt || !publishedAt || !expiresAt
    || Date.parse(createdAt) > Date.parse(publishedAt) || Date.parse(publishedAt) >= Date.parse(expiresAt)
    || Date.parse(checkedAt) >= Date.parse(freshExpiresAt)
    || Date.parse(checkedAt) !== latestSourceObservedAt
    || Date.parse(freshExpiresAt) !== Date.parse(expiresAt)
    || Date.parse(expiresAt) > earliestSourceFreshUntil
    || Date.parse(publishedAt) < Date.parse(checkedAt)) return null;
  const serverActions = Array.isArray(rawProposal.next_actions) && rawProposal.next_actions.length <= 4
    ? rawProposal.next_actions.filter(action => actionSet.has(action))
    : [];
  const nextActions = rawProposal.status === 'available' ? [...new Set(serverActions)] : [];
  return {
    contract_version: '1.0.0', proposal_id: proposalId, case_id: caseId, reference,
    status: rawProposal.status, version, revision, published_revision: publishedRevision,
    position: rawProposal.position, addresses: {case_revision: caseRevision}, title, summary,
    why_it_fits: whyItFits, trade_offs: tradeOffs,
    route: {origin, destinations, legs}, itinerary, components,
    ledger: {currency: ledgerCurrency, priced_total_minor: pricedTotal, priced_component_count: pricedCount, unpriced_component_keys: [...unpricedKeys], complete_pricing: ledger.complete_pricing},
    sources, freshness: {checked_at: checkedAt, expires_at: freshExpiresAt, requires_revalidation: true},
    unresolved_items: unresolvedItems, traveler_disposition: rawProposal.traveler_disposition,
    next_actions: nextActions, disclosure: {message: workspaceAssistedProposalDisclosure},
    created_at: createdAt, published_at: publishedAt, expires_at: expiresAt
  };
}

function normalizeWorkspaceAssistedProposalPayload(payload, expectedCaseId) {
  if (!payload || typeof payload !== 'object' || !Array.isArray(payload.proposals) || payload.proposals.length > 12) {
    throw new Error('AssistedProposal list response is malformed.');
  }
  const proposals = payload.proposals.map(proposal => normalizeWorkspaceAssistedProposal(proposal, expectedCaseId));
  if (proposals.some(proposal => !proposal)) throw new Error('AssistedProposal list response failed its closed traveler-safe contract.');
  return proposals;
}

function workspaceAssistedProposalPositionLabel(position) {
  return {
    best_value: 'התמורה הטובה ביותר',
    lowest_friction: 'הדרך הפשוטה ביותר',
    most_flexible: 'האפשרות הגמישה ביותר',
    most_memorable: 'החוויה המיוחדת ביותר',
    custom: 'מותאם לבקשה שלכם'
  }[position] || 'הצעה אישית';
}

function workspaceAssistedProposalStatusLabel(status) {
  return {
    available: 'מוכנה לעיון',
    withdrawn: 'נמשכה על ידי הצוות',
    expired: 'תוקף ההצעה הסתיים',
    superseded: 'נשמרה כהצעה קודמת'
  }[status] || 'הצעה שמורה';
}

function workspaceAssistedProposalDispositionLabel(disposition) {
  return {
    awaiting_review: 'ממתינה לבדיקה שלכם',
    reviewed: 'סומנה כנקראה',
    changes_requested: 'ביקשתם שינויים',
    contact_authorized: 'אישרתם יצירת קשר',
    declined: 'ויתרתם על ההצעה',
    unavailable: 'לקריאה בלבד'
  }[disposition] || 'לקריאה בלבד';
}

function formatWorkspaceAssistedProposalTime(value) {
  if (!value) return '';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '';
  return parsed.toLocaleString('he-IL', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'});
}

function formatWorkspaceAssistedProposalMinor(minor, currency) {
  if (!Number.isSafeInteger(minor) || minor < 0 || !['ILS', 'USD', 'EUR'].includes(currency)) return '';
  const formatter = new Intl.NumberFormat('he-IL', {style: 'currency', currency, minimumFractionDigits: 2, maximumFractionDigits: 2});
  return formatter.format(minor / 100);
}

function workspaceAssistedProposalPriceBasisLabel(basis) {
  return {
    trip_total: 'לכל הנסיעה', stay_total: 'לכל השהייה', ticket_total: 'לכל הכרטיסים',
    activity_total: 'לכל הפעילות', item_total: 'לכל הפריטים'
  }[basis] || '';
}

function workspaceAssistedProposalInclusionLabel(value, subject) {
  return {
    included: `${subject} כלולים`, excluded: `${subject} אינם כלולים`, unknown: `${subject} דורשים בדיקה`
  }[value] || `${subject} דורשים בדיקה`;
}

function workspaceAssistedProposalSourceTypeLabel(value) {
  return {
    connected_api: 'מקור שנבדק ידנית',
    supplier_portal: 'מקור שנבדק ידנית',
    supplier_written_quote: 'הצעה שנבדקה ידנית',
    public_supplier_page: 'עמוד ספק ציבורי',
    official_information: 'מידע רשמי ציבורי'
  }[value] || 'מקור מידע';
}

function workspaceAssistedProposalRouteModeLabel(value) {
  return {flight: 'טיסה', rail: 'רכבת', road: 'כביש', ferry: 'מעבורת', walk: 'הליכה', other: 'מעבר'}[value] || 'מעבר';
}

function workspaceAssistedProposalActionLabel(action) {
  return {
    review: 'קראתי את ההצעה',
    request_changes: 'אני רוצה לשנות את ההצעה',
    authorize_contact: 'אשרו פנייה במייל',
    decline: 'ההצעה לא מתאימה לי'
  }[action] || action;
}

function workspaceAssistedProposalActionIcon(action) {
  return {review: 'check', request_changes: 'pencil-line', authorize_contact: 'messages-square', decline: 'x'}[action] || 'arrow-left';
}

function workspaceAssistedProposalMutationKey(caseId, proposalId) {
  return `${String(caseId || '').toLowerCase()}:${String(proposalId || '').toLowerCase()}`;
}

function createWorkspaceAssistedProposalIdempotencyKey() {
  const generated = String(createAgentClientRequestId() || '');
  return generated.length >= 16 ? generated : `proposal-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 14)}`;
}

function workspaceAssistedProposalActionRequestBody(action, proposalVersion, idempotencyKey) {
  const body = {
    action,
    expected_version: proposalVersion,
    idempotency_key: idempotencyKey
  };
  if (action === 'authorize_contact') {
    body.contact_consent = {
      ...workspaceAssistedProposalContactConsent,
      channels: [...workspaceAssistedProposalContactConsent.channels]
    };
  }
  return body;
}

function appendWorkspaceAssistedProposalList(parent, values, className = '') {
  const list = document.createElement('ul');
  if (className) list.className = className;
  values.forEach(value => appendTextElement(list, 'li', value));
  parent.append(list);
  return list;
}

function renderWorkspaceAssistedProposalRoute(parent, proposal) {
  const section = document.createElement('section');
  section.className = 'workspace-proposal-route';
  appendTextElement(section, 'h5', 'הדרך והימים');
  const routeLine = document.createElement('p');
  routeLine.className = 'workspace-proposal-route-line';
  appendTextElement(routeLine, 'strong', proposal.route.origin);
  proposal.route.destinations.forEach(destination => {
    const icon = document.createElement('i');
    icon.dataset.lucide = 'arrow-left';
    routeLine.append(icon);
    appendTextElement(routeLine, 'strong', destination);
  });
  section.append(routeLine);
  if (proposal.route.legs.length) {
    const legs = document.createElement('ol');
    legs.className = 'workspace-proposal-route-legs';
    proposal.route.legs.forEach(leg => {
      const item = document.createElement('li');
      appendTextElement(item, 'span', String(leg.sequence));
      const copy = document.createElement('div');
      appendTextElement(copy, 'strong', `${leg.from} ← ${leg.to}`);
      appendTextElement(copy, 'small', workspaceAssistedProposalRouteModeLabel(leg.mode));
      item.append(copy);
      legs.append(item);
    });
    section.append(legs);
  }
  const itinerary = document.createElement('ol');
  itinerary.className = 'workspace-proposal-itinerary';
  proposal.itinerary.forEach(day => {
    const item = document.createElement('li');
    appendTextElement(item, 'span', `יום ${day.day}`);
    const copy = document.createElement('div');
    appendTextElement(copy, 'strong', day.title);
    appendTextElement(copy, 'small', day.place);
    item.append(copy);
    itinerary.append(item);
  });
  section.append(itinerary);
  parent.append(section);
}

function renderWorkspaceAssistedProposalLedger(parent, proposal) {
  const section = document.createElement('section');
  section.className = 'workspace-proposal-ledger';
  const head = document.createElement('header');
  const copy = document.createElement('div');
  appendTextElement(copy, 'span', 'סכום ממקורות שנבדקו');
  appendTextElement(copy, 'h5', proposal.ledger.priced_component_count > 0
    ? formatWorkspaceAssistedProposalMinor(proposal.ledger.priced_total_minor, proposal.ledger.currency)
    : 'מחירים שנבדקו יופיעו כאן', 'workspace-proposal-total');
  appendTextElement(copy, 'small', proposal.ledger.priced_component_count > 0
    ? `${proposal.ledger.priced_component_count} רכיבים מתומחרים · ${proposal.ledger.currency}`
    : 'כל מחיר יוצג עם המקור ומועד הבדיקה');
  const ledgerState = appendTextElement(head, 'strong', proposal.ledger.complete_pricing ? 'כל הרכיבים מתומחרים' : 'הסכום עדיין חלקי');
  ledgerState.dataset.state = proposal.ledger.complete_pricing ? 'complete' : 'partial';
  head.prepend(copy);
  section.append(head);
  if (proposal.ledger.unpriced_component_keys.length) {
    const gap = document.createElement('div');
    gap.className = 'workspace-proposal-price-gaps';
    appendTextElement(gap, 'strong', 'רכיבים שעדיין אינם כלולים בסכום');
    const componentByKey = new Map(proposal.components.map(component => [component.component_key, component.title]));
    appendWorkspaceAssistedProposalList(gap, proposal.ledger.unpriced_component_keys.map(key => componentByKey.get(key) || key));
    section.append(gap);
  }
  if (proposal.unresolved_items.length) {
    const unresolved = document.createElement('div');
    unresolved.className = 'workspace-proposal-unresolved';
    appendTextElement(unresolved, 'strong', 'מה עוד צריך לאשר');
    appendWorkspaceAssistedProposalList(unresolved, proposal.unresolved_items.map(item => item.label));
    section.append(unresolved);
  }
  parent.append(section);
}

function renderWorkspaceAssistedProposalComponents(parent, proposal) {
  const section = document.createElement('section');
  section.className = 'workspace-proposal-components';
  const heading = document.createElement('header');
  appendTextElement(heading, 'span', '360° לחופשה');
  appendTextElement(heading, 'h5', 'כל שמונת חלקי הנסיעה, במקום אחד');
  appendTextElement(heading, 'p', 'ראו מה כבר כלול, מה המחיר לכל חלק ומה כדאי להוסיף לפני שמחליטים.');
  section.append(heading);
  const sourceById = new Map(proposal.sources.map(source => [source.source_id, source]));
  const lanes = document.createElement('div');
  lanes.className = 'workspace-proposal-lanes';
  workspaceAssistedProposalCategories.forEach(category => {
    const lane = document.createElement('section');
    lane.className = 'workspace-proposal-lane';
    lane.dataset.category = category.key;
    const laneHead = document.createElement('header');
    const icon = document.createElement('i');
    icon.dataset.lucide = category.icon;
    const laneTitle = document.createElement('div');
    appendTextElement(laneTitle, 'h6', category.label);
    const components = proposal.components.filter(component => component.category === category.key);
    appendTextElement(laneTitle, 'small', components.length ? `${components.length} פריטים בהצעה` : 'לא נכלל בהצעה הנוכחית');
    laneHead.append(icon, laneTitle);
    lane.append(laneHead);
    if (!components.length) {
      const missing = document.createElement('p');
      missing.className = 'workspace-proposal-lane-missing';
      missing.textContent = proposal.next_actions.includes('request_changes')
        ? 'לא כלול כרגע. רוצים להוסיף אותו? בקשו שינוי בהצעה.'
        : 'לא נכלל בהצעה הזאת.';
      lane.append(missing);
    } else {
      components.forEach(component => {
        const item = document.createElement('article');
        item.className = 'workspace-proposal-component';
        appendTextElement(item, 'h6', component.title);
        appendTextElement(item, 'p', component.description);
        const price = document.createElement('div');
        price.className = 'workspace-proposal-component-price';
        if (component.price.priced) {
          appendTextElement(price, 'strong', formatWorkspaceAssistedProposalMinor(component.price.total_for_party_minor, component.price.currency));
          appendTextElement(price, 'small', workspaceAssistedProposalPriceBasisLabel(component.price.basis));
        } else {
          appendTextElement(price, 'strong', 'המחיר לא נכלל עדיין');
          appendTextElement(price, 'small', 'יושלם רק לאחר בדיקת מקור');
        }
        item.append(price);
        const conditions = document.createElement('dl');
        conditions.className = 'workspace-proposal-conditions';
        [['ביטול', component.conditions.cancellation], ['שינויים', component.conditions.changes], ['כבודה ומה כלול', component.conditions.baggage_or_inclusions],
          ['מסים', workspaceAssistedProposalInclusionLabel(component.price.taxes, 'המסים')], ['עמלות', workspaceAssistedProposalInclusionLabel(component.price.fees, 'העמלות')]].forEach(([term, description]) => {
          appendTextElement(conditions, 'dt', term);
          appendTextElement(conditions, 'dd', description);
        });
        item.append(conditions);
        const componentSources = component.source_ids.map(sourceId => sourceById.get(sourceId)).filter(Boolean);
        if (componentSources.length) {
          const sourceList = document.createElement('ul');
          sourceList.className = 'workspace-proposal-component-sources';
          componentSources.forEach(source => appendTextElement(sourceList, 'li', source.public_label));
          item.append(sourceList);
        }
        lane.append(item);
      });
    }
    lanes.append(lane);
  });
  section.append(lanes);
  parent.append(section);
}

function renderWorkspaceAssistedProposalSources(parent, proposal) {
  const section = document.createElement('section');
  section.className = 'workspace-proposal-sources';
  appendTextElement(section, 'h5', 'המקורות שמאחורי ההצעה');
  appendTextElement(section, 'p', 'לכל מקור מוצגים הספק או המוכר, מועד הבדיקה ותוקף המידע. לפני רכישה נאמת שוב מחיר, זמינות ותנאים.');
  const list = document.createElement('ul');
  proposal.sources.forEach(source => {
    const item = document.createElement('li');
    const head = document.createElement('div');
    appendTextElement(head, 'strong', source.public_label);
    appendTextElement(head, 'small', workspaceAssistedProposalSourceTypeLabel(source.source_type));
    item.append(head);
    const parties = [source.supplier_name ? `ספק: ${source.supplier_name}` : '', source.seller_name ? `מוכר: ${source.seller_name}` : ''].filter(Boolean);
    if (parties.length) appendTextElement(item, 'p', parties.join(' · '));
    const times = document.createElement('p');
    times.className = 'workspace-proposal-source-times';
    appendTextElement(times, 'span', `נבדק: ${formatWorkspaceAssistedProposalTime(source.observed_at)}`);
    appendTextElement(times, 'span', `עדכני עד: ${formatWorkspaceAssistedProposalTime(source.fresh_until)}`);
    item.append(times);
    if (source.source_url) {
      const link = document.createElement('a');
      link.href = source.source_url;
      link.target = '_blank';
      link.rel = 'nofollow noopener noreferrer';
      let sourceHost = '';
      try {
        sourceHost = new URL(source.source_url).hostname.replace(/^www\./, '');
      } catch (error) {
        sourceHost = '';
      }
      link.textContent = sourceHost ? `פתחו את המקור ב־${sourceHost}` : 'פתחו את המקור';
      item.append(link);
    }
    list.append(item);
  });
  section.append(list);
  parent.append(section);
}

function renderWorkspaceAssistedProposalHistory(parent, proposal) {
  const section = document.createElement('section');
  section.className = 'workspace-proposal-history';
  appendTextElement(section, 'h5', 'מצב ותוקף');
  const history = document.createElement('dl');
  [
    ['מצב ההצעה', workspaceAssistedProposalStatusLabel(proposal.status)],
    ['הבחירה שלכם', workspaceAssistedProposalDispositionLabel(proposal.traveler_disposition)],
    ['נוצרה', formatWorkspaceAssistedProposalTime(proposal.created_at)],
    ['נבדקה', formatWorkspaceAssistedProposalTime(proposal.freshness.checked_at)],
    ['מקורות עדכניים עד', formatWorkspaceAssistedProposalTime(proposal.freshness.expires_at)],
    ['תוקף ההצעה', formatWorkspaceAssistedProposalTime(proposal.expires_at)]
  ].forEach(([term, description]) => {
    appendTextElement(history, 'dt', term);
    appendTextElement(history, 'dd', description);
  });
  section.append(history);
  if (proposal.status !== 'available') appendTextElement(section, 'p', 'ההצעה נשמרת לעיון ולהשוואה, אך אינה פתוחה לפעולות נוספות.', 'workspace-proposal-history-note');
  parent.append(section);
}

async function recordWorkspaceAssistedProposalAction(caseData, proposal, action) {
  if (!proposal.next_actions.includes(action)) return;
  const caseId = workspaceAssistedProposalUuid(caseData?.case_id);
  const mutationKey = workspaceAssistedProposalMutationKey(caseId, proposal.proposal_id);
  if (!caseId || workspaceAssistedProposalMutations.has(mutationKey)) return;
  const state = workspaceAssistedProposalState(caseId);
  if (action === 'authorize_contact' && !window.traVelV2?.isLoggedIn) {
    state.messages.set(proposal.proposal_id, {state: 'error', text: 'כדי לאשר פנייה למייל מאומת, התחברו לחשבון וחזרו להצעה.'});
    refreshWorkspaceAssistedProposalPanel(caseId);
    return;
  }
  if (state.stale || (proposal.status === 'available' && Date.parse(proposal.expires_at) <= Date.now())) {
    state.stale = true;
    state.messages.set(proposal.proposal_id, {state: 'error', text: 'תוקף המידע השתנה. טענו את ההצעות מחדש לפני פעולה נוספת.'});
    refreshWorkspaceAssistedProposalPanel(caseId);
    return;
  }
  const retryKey = `${mutationKey}:${action}`;
  const idempotencyKey = workspaceAssistedProposalRetryKeys.get(retryKey) || createWorkspaceAssistedProposalIdempotencyKey();
  workspaceAssistedProposalRetryKeys.set(retryKey, idempotencyKey);
  workspaceAssistedProposalMutations.set(mutationKey, {action, expectedVersion: proposal.version, idempotencyKey});
  state.messages.set(proposal.proposal_id, {state: 'running', text: 'שומר את הבחירה שלכם...'});
  refreshWorkspaceAssistedProposalPanel(caseId);
  try {
    const requestBody = workspaceAssistedProposalActionRequestBody(action, proposal.version, idempotencyKey);
    const payload = await requestWithDeadline(
      signal => agentApiRequest(`/quote-cases/${encodeURIComponent(caseId)}/assisted-proposals/${encodeURIComponent(proposal.proposal_id)}/actions`, {
        method: 'POST',
        body: JSON.stringify(requestBody),
        ...(signal ? {signal} : {})
      }),
      'assisted_proposal_action_timeout'
    );
    let confirmed = normalizeWorkspaceAssistedProposal(payload?.proposal, caseId);
    if (!confirmed || confirmed.proposal_id !== proposal.proposal_id || confirmed.version < proposal.version) throw new Error('The confirmed proposal response is invalid.');
    const cached = state.proposals.find(item => item.proposal_id === confirmed.proposal_id);
    if (payload?.replayed === true) {
      const reconciled = await loadWorkspaceAssistedProposals(caseData, {force: true});
      confirmed = reconciled ? state.proposals.find(item => item.proposal_id === proposal.proposal_id) : null;
      if (!confirmed) throw new Error('The replayed action was recognized, but the current proposal state could not be confirmed.');
    } else if (cached && cached.version > confirmed.version) {
      confirmed = cached;
    }
    const higherServerVersion = confirmed.version > proposal.version;
    state.proposals = state.proposals.map(item => item.proposal_id === confirmed.proposal_id ? confirmed : item);
    state.confirmedUpdateId = higherServerVersion && !prefersReducedMotion() ? confirmed.proposal_id : '';
    workspaceAssistedProposalRetryKeys.delete(retryKey);
    const successText = action === 'authorize_contact'
      ? 'האישור נשמר. צוות הסיוע של Tra‑Vel יכול לפנות למייל המאומת בחשבון רק לגבי הצעה זו. לא בוצעה הזמנה או חיוב ולא נשלח מידע לספק.'
      : action === 'request_changes'
        ? 'בקשת השינוי התקבלה. הצעה מעודכנת תופיע כאן לאחר בדיקת המקורות.'
        : action === 'decline'
          ? 'סימנו שההצעה לא מתאימה לכם. היא נשארת כאן לקריאה בלבד.'
          : 'סימנו את ההצעה כנקראה. עכשיו אפשר לבקש שינוי, לאשר קשר או לסמן שהיא לא מתאימה.';
    state.messages.set(confirmed.proposal_id, {state: payload?.replayed ? 'reused' : 'success', text: successText});
  } catch (error) {
    if (error?.code === 'tra_vel_assisted_proposal_contact_target_unverified') {
      workspaceAssistedProposalRetryKeys.delete(retryKey);
      state.messages.set(proposal.proposal_id, {state: 'error', text: 'לא נמצא מייל חשבון מאומת. התחברו מחדש או עדכנו את המייל בחשבון לפני אישור הפנייה.'});
    } else if (error?.status === 409) {
      workspaceAssistedProposalRetryKeys.delete(retryKey);
      state.stale = true;
      state.error = 'ההצעה השתנתה במקום אחר. טענו מחדש את הרשימה לפני פעולה נוספת.';
      state.messages.set(proposal.proposal_id, {state: 'error', text: 'ההצעה השתנתה במקום אחר. רעננו את ההצעות לפני פעולה נוספת.'});
    } else if (error?.status === 401 || error?.status === 403) {
      workspaceAssistedProposalRetryKeys.delete(retryKey);
      state.stale = true;
      state.messages.set(proposal.proposal_id, {state: 'error', text: 'הגישה להצעה הפרטית פגה. התחברו מחדש ורעננו את העמוד.'});
    } else if (error?.code === 'assisted_proposal_action_timeout') {
      state.messages.set(proposal.proposal_id, {state: 'error', text: 'לא התקבל אישור בזמן. תוצאת הפעולה אינה ודאית ומזהה הניסיון נשמר כדי למנוע פעולה כפולה.'});
    } else {
      state.messages.set(proposal.proposal_id, {state: 'error', text: 'הבחירה לא אושרה. ההצעה הקודמת נשארה ללא שינוי ואפשר לנסות שוב.'});
    }
  } finally {
    workspaceAssistedProposalMutations.delete(mutationKey);
    refreshWorkspaceAssistedProposalPanel(caseId);
  }
}

function renderWorkspaceAssistedProposalActions(parent, caseData, proposal, state, existingHandoff = null) {
  const section = document.createElement('section');
  section.className = 'workspace-proposal-actions';
  const isLoggedIn = Boolean(window.traVelV2?.isLoggedIn);
  const contactAvailable = proposal.next_actions.includes('authorize_contact');
  const visibleActions = proposal.next_actions.filter(action => action !== 'authorize_contact' || isLoggedIn);
  appendTextElement(section, 'h5', visibleActions.length || contactAvailable ? 'מה תרצו לעשות עכשיו?' : 'ההצעה נשמרת לקריאה');
  const mutationKey = workspaceAssistedProposalMutationKey(caseData.case_id, proposal.proposal_id);
  const mutation = workspaceAssistedProposalMutations.get(mutationKey);
  let consentNoticeId = '';
  if (contactAvailable && isLoggedIn) {
    consentNoticeId = `workspace-proposal-consent-${proposal.proposal_id}`;
    const notice = appendTextElement(section, 'p', workspaceAssistedProposalContactNotice, 'workspace-proposal-contact-notice');
    notice.id = consentNoticeId;
  }
  if (visibleActions.length) {
    const buttons = document.createElement('div');
    visibleActions.forEach(action => {
      const button = document.createElement('button');
      button.type = 'button';
      button.dataset.workspaceProposalAction = action;
      button.disabled = Boolean(mutation);
      if (mutation) button.setAttribute('aria-busy', 'true');
      if (action === 'authorize_contact' && consentNoticeId) button.setAttribute('aria-describedby', consentNoticeId);
      const icon = document.createElement('i');
      icon.dataset.lucide = workspaceAssistedProposalActionIcon(action);
      button.append(icon, document.createTextNode(workspaceAssistedProposalActionLabel(action)));
      button.addEventListener('click', () => recordWorkspaceAssistedProposalAction(caseData, proposal, action));
      buttons.append(button);
    });
    section.append(buttons);
  }
  if (contactAvailable && !isLoggedIn) {
    const guestNotice = document.createElement('div');
    guestNotice.className = 'workspace-proposal-contact-login';
    appendTextElement(guestNotice, 'p', 'הפנייה תישלח רק לכתובת הדוא״ל המאומתת בחשבון ולא תשותף עם ספקים.');
    const login = document.createElement('a');
    login.href = String(window.traVelV2?.loginUrl || window.traVelV2?.homeUrl || '/');
    login.textContent = 'התחברו כדי לאשר פנייה במייל';
    guestNotice.append(login);
    section.append(guestNotice);
  }
  if (!visibleActions.length && !contactAvailable) {
    appendTextElement(section, 'p', proposal.status === 'available'
      ? 'הבחירה שלכם נשמרה. כדי להתקדם אפשר לפתוח שיחה מאובטחת רק אם אישרתם יצירת קשר.'
      : 'המצב ההיסטורי נשמר כאן בלי כפתורי פעולה ובלי שינוי בפרטי ההצעה.');
  }
  const message = state.messages.get(proposal.proposal_id);
  const live = document.createElement('p');
  live.className = 'workspace-proposal-action-status';
  live.dataset.state = message?.state || 'idle';
  live.setAttribute('role', 'status');
  live.setAttribute('aria-live', 'polite');
  live.setAttribute('aria-atomic', 'true');
  live.textContent = message?.text || '';
  section.append(live);
  if (proposal.traveler_disposition === 'contact_authorized' && proposal.status === 'available' && existingHandoff && !existingHandoff.hidden && !existingHandoff.disabled) {
    const handoff = document.createElement('button');
    handoff.type = 'button';
    handoff.className = 'workspace-proposal-handoff';
    handoff.dataset.workspaceProposalHandoff = 'true';
    const icon = document.createElement('i');
    icon.dataset.lucide = 'message-circle';
    handoff.append(icon, document.createTextNode('פתחו שיחה מאובטחת על ההצעה'));
    handoff.addEventListener('click', () => existingHandoff.click());
    section.append(handoff);
    appendTextElement(section, 'small', 'פתיחת השיחה היא פעולה נפרדת. היא אינה הזמנה, תשלום או שליחה לספק.');
  }
  parent.append(section);
}

function renderWorkspaceAssistedProposalCard(proposal, caseData, state, existingHandoff = null, animateConfirmed = false) {
  const article = document.createElement('article');
  article.className = `workspace-proposal${animateConfirmed ? ' is-confirmed-update' : ''}`;
  article.dataset.workspaceProposal = proposal.proposal_id;
  article.dataset.status = proposal.status;
  const titleId = `workspace-proposal-${proposal.proposal_id}`;
  const bodyId = `${titleId}-body`;
  const expanded = state.expanded.has(proposal.proposal_id);
  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'workspace-proposal-toggle';
  toggle.dataset.workspaceProposalToggle = proposal.proposal_id;
  toggle.setAttribute('aria-expanded', String(expanded));
  toggle.setAttribute('aria-controls', bodyId);
  const identity = document.createElement('span');
  appendTextElement(identity, 'small', workspaceAssistedProposalPositionLabel(proposal.position));
  const title = appendTextElement(identity, 'strong', proposal.title);
  title.id = titleId;
  const stateCopy = document.createElement('span');
  appendTextElement(stateCopy, 'b', workspaceAssistedProposalStatusLabel(proposal.status));
  appendTextElement(stateCopy, 'small', proposal.reference);
  const chevron = document.createElement('i');
  chevron.dataset.lucide = 'chevron-down';
  toggle.append(identity, stateCopy, chevron);
  article.append(toggle);
  const body = document.createElement('div');
  body.id = bodyId;
  body.className = 'workspace-proposal-body';
  body.hidden = !expanded;
  body.setAttribute('role', 'region');
  body.setAttribute('aria-labelledby', titleId);
  appendTextElement(body, 'p', proposal.summary, 'workspace-proposal-summary');
  const rationale = document.createElement('div');
  rationale.className = 'workspace-proposal-rationale';
  const fit = document.createElement('section');
  appendTextElement(fit, 'h5', 'למה זה מתאים לבקשה שלכם');
  appendWorkspaceAssistedProposalList(fit, proposal.why_it_fits);
  const tradeoffs = document.createElement('section');
  appendTextElement(tradeoffs, 'h5', 'מה חשוב לדעת לפני שבוחרים');
  appendWorkspaceAssistedProposalList(tradeoffs, proposal.trade_offs);
  rationale.append(fit, tradeoffs);
  body.append(rationale);
  renderWorkspaceAssistedProposalRoute(body, proposal);
  renderWorkspaceAssistedProposalLedger(body, proposal);
  renderWorkspaceAssistedProposalComponents(body, proposal);
  renderWorkspaceAssistedProposalSources(body, proposal);
  renderWorkspaceAssistedProposalHistory(body, proposal);
  const disclosure = document.createElement('aside');
  disclosure.className = 'workspace-proposal-disclosure';
  const shield = document.createElement('i');
  shield.dataset.lucide = 'shield-alert';
  const disclosureCopy = document.createElement('div');
  appendTextElement(disclosureCopy, 'strong', 'לפני כל רכישה נבצע אימות מחדש');
  appendTextElement(disclosureCopy, 'p', 'המחירים נבדקו במועד המצוין. לפני רכישה נאמת שוב את המחיר, הזמינות והתנאים.');
  const legalDisclosure = document.createElement('details');
  appendTextElement(legalDisclosure, 'summary', 'נוסח מסחרי מלא');
  const exactDisclosure = appendTextElement(legalDisclosure, 'p', proposal.disclosure.message);
  exactDisclosure.lang = 'en';
  exactDisclosure.dir = 'ltr';
  disclosureCopy.append(legalDisclosure);
  disclosure.append(shield, disclosureCopy);
  body.append(disclosure);
  renderWorkspaceAssistedProposalActions(body, caseData, proposal, state, existingHandoff);
  article.append(body);
  toggle.addEventListener('click', () => {
    const nowExpanded = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', String(nowExpanded));
    body.hidden = !nowExpanded;
    if (nowExpanded) state.expanded.add(proposal.proposal_id);
    else state.expanded.delete(proposal.proposal_id);
  });
  return article;
}

function renderWorkspaceAssistedProposalPanel(panel, caseData) {
  if (!panel || !caseData?.case_id) return;
  const state = workspaceAssistedProposalState(caseData.case_id);
  if (state.loaded && state.proposals.some(proposal => proposal.status === 'available' && Date.parse(proposal.expires_at) <= Date.now())) state.stale = true;
  const animateId = state.confirmedUpdateId;
  state.confirmedUpdateId = '';
  panel.replaceChildren();
  panel.hidden = !state.open;
  panel.setAttribute('aria-busy', String(state.loading));
  const heading = document.createElement('header');
  const copy = document.createElement('div');
  appendTextElement(copy, 'small', 'ההצעות האישיות שלכם');
  appendTextElement(copy, 'h4', 'השוו מחיר, מה כלול ומה חשוב לדעת');
  const close = document.createElement('button');
  close.type = 'button';
  close.dataset.workspaceProposalsClose = 'true';
  close.textContent = 'סגרו';
  close.addEventListener('click', () => {
    state.open = false;
    panel.hidden = true;
    panel.closest?.('[data-workspace-quote-case]')?.classList.remove('has-open-proposals');
    const toggle = panel.closest?.('[data-workspace-quote-case]')?.querySelector?.('[data-workspace-proposals-toggle]');
    toggle?.setAttribute('aria-expanded', 'false');
    toggle?.focus();
  });
  heading.append(copy, close);
  panel.append(heading);
  const status = document.createElement('p');
  status.className = 'workspace-proposals-status';
  status.setAttribute('role', 'status');
  status.setAttribute('aria-live', 'polite');
  status.setAttribute('aria-atomic', 'true');
  if (state.loading) status.textContent = 'טוען את ההצעות האישיות של הבקשה הזאת...';
  else if (state.error) status.textContent = state.error;
  else if (state.stale) status.textContent = 'בקשת החופשה או תוקף ההצעה השתנו. ההצעות השמורות נשארות לקריאה, אבל צריך לטעון עדכון לפני פעולה.';
  else if (state.loaded && !state.proposals.length) status.textContent = 'עדיין אין הצעה אישית. כשהמחירים ייבדקו, הם יופיעו כאן עם המקור, מועד הבדיקה והתנאים.';
  else if (state.loaded) status.textContent = `${state.proposals.length} הצעות אישיות זמינות לעיון, כולל הצעות קודמות לקריאה בלבד.`;
  else status.textContent = 'ההצעות ייטענו רק לאחר שתבחרו לפתוח אותן.';
  panel.append(status);
  if (state.error || state.stale) {
    const retry = document.createElement('button');
    retry.type = 'button';
    retry.className = 'workspace-proposals-retry';
    retry.textContent = state.stale ? 'טענו את העדכון' : 'נסו לטעון שוב';
    retry.addEventListener('click', () => loadWorkspaceAssistedProposals(caseData, {force: true}));
    panel.append(retry);
  }
  if (state.loaded && state.proposals.length) {
    const list = document.createElement('div');
    list.className = 'workspace-proposal-list';
    const existingHandoff = panel.closest?.('[data-workspace-quote-case]')?.querySelector?.('[data-workspace-quote-action="handoff"]');
    state.proposals.forEach(proposal => list.append(renderWorkspaceAssistedProposalCard(
      state.stale ? {...proposal, next_actions: []} : proposal,
      caseData,
      state,
      existingHandoff,
      proposal.proposal_id === animateId && !prefersReducedMotion()
    )));
    panel.append(list);
  }
  renderIcons();
}

function refreshWorkspaceAssistedProposalPanel(caseId) {
  const grid = document.querySelector('[data-workspace-quote-grid]');
  const normalizedCaseId = String(caseId || '').toLowerCase();
  const card = [...(grid?.children || [])].find(item => String(item?.dataset?.workspaceQuoteCase || '').toLowerCase() === normalizedCaseId);
  const panel = card?.querySelector?.('[data-workspace-proposals-panel]');
  const caseData = workspaceQuoteCaseRuntime.cases.get(normalizedCaseId)
    || [...workspaceQuoteCaseRuntime.cases.values()].find(item => String(item?.case_id || '').toLowerCase() === normalizedCaseId);
  if (panel && caseData) renderWorkspaceAssistedProposalPanel(panel, caseData);
}

async function loadWorkspaceAssistedProposals(caseData, {force = false} = {}) {
  const caseId = workspaceAssistedProposalUuid(caseData?.case_id);
  if (!caseId) return false;
  const state = workspaceAssistedProposalState(caseId);
  if (state.loading || (state.loaded && !force)) return state.loaded;
  state.loading = true;
  state.error = '';
  refreshWorkspaceAssistedProposalPanel(caseId);
  try {
    const payload = await requestWithDeadline(
      signal => agentApiRequest(`/quote-cases/${encodeURIComponent(caseId)}/assisted-proposals?per_page=12`, signal ? {signal} : {}),
      'assisted_proposal_list_timeout'
    );
    const proposals = normalizeWorkspaceAssistedProposalPayload(payload, caseId);
    state.proposals = proposals.sort((a, b) => String(b.published_at || '').localeCompare(String(a.published_at || '')));
    state.loaded = true;
    state.error = '';
    state.stale = false;
    state.loadedRequestRevision = Math.max(0, Number(caseData.source?.request_revision) || 0);
    if (state.proposals.length && !state.expanded.size) state.expanded.add(state.proposals[0].proposal_id);
    return true;
  } catch (error) {
    state.loaded = false;
    state.error = error?.status === 401 || error?.status === 403
      ? 'הגישה להצעות הפרטיות פגה. התחברו מחדש ורעננו את העמוד.'
      : 'לא הצלחנו לפתוח את ההצעות כרגע. ההצעות השמורות נשארו ללא שינוי. נסו שוב בעוד רגע.';
    return false;
  } finally {
    state.loading = false;
    refreshWorkspaceAssistedProposalPanel(caseId);
  }
}

function renderWorkspaceQuoteCaseCard(initialCase, confirmedPositive = false) {
  let caseData = initialCase;
  const proposalState = workspaceAssistedProposalState(caseData.case_id);
  const currentRequestRevision = Math.max(0, Number(caseData.source?.request_revision) || 0);
  if (proposalState.loaded && (quoteCaseTerminalStatuses.has(caseData.status)
    || (proposalState.loadedRequestRevision > 0 && currentRequestRevision > 0 && currentRequestRevision !== proposalState.loadedRequestRevision))) proposalState.stale = true;
  const card = document.createElement('article');
  card.className = `workspace-quote-card${confirmedPositive ? ' is-advancing' : ''}${proposalState.open ? ' has-open-proposals' : ''}`;
  card.dataset.status = String(caseData.status || 'queued');
  card.dataset.version = String(Math.max(0, Number(caseData.version) || 0));
  card.dataset.workspaceQuoteCase = String(caseData.case_id || '');
  const terminal = quoteCaseTerminalStatuses.has(caseData.status);
  const cardTitleId = `workspace-quote-${String(caseData.case_id || '').replace(/[^a-z0-9-]/gi, '')}`;
  card.setAttribute('aria-labelledby', cardTitleId);

  const head = document.createElement('div');
  head.className = 'workspace-quote-card-head';
  const identity = document.createElement('div');
  appendTextElement(identity, 'small', 'בדיקה אישית');
  appendTextElement(identity, 'strong', caseData.reference || caseData.case_id, 'workspace-quote-reference');
  const status = document.createElement('span');
  status.className = 'workspace-quote-card-status';
  status.textContent = quoteCaseStatusLabel(caseData);
  head.append(identity, status);
  card.append(head);

  const cardTitle = appendTextElement(card, 'h3', quoteCaseSummaryText(caseData));
  cardTitle.id = cardTitleId;
  const progress = document.createElement('ol');
  progress.className = 'workspace-quote-card-progress';
  progress.setAttribute('aria-label', 'התקדמות מאומתת של הבדיקה האישית');
  const progressState = quoteCaseProgressState(caseData.status);
  ['תוכנית', 'תור', 'בדיקה', 'המשך'].forEach((label, index) => {
    const item = document.createElement('li');
    const state = quoteCaseStepState(progressState, index);
    item.dataset.state = state;
    if (['current', 'blocked'].includes(state)) item.setAttribute('aria-current', 'step');
    appendTextElement(item, 'strong', label);
    appendTextElement(item, 'small', quoteCaseStepStateLabel(state));
    progress.append(item);
  });
  card.append(progress);

  const next = document.createElement('div');
  next.className = 'workspace-quote-card-next';
  const nextIcon = document.createElement('i');
  nextIcon.dataset.lucide = 'route';
  const nextCopy = document.createElement('div');
  appendTextElement(nextCopy, 'small', 'הפעולה הבאה');
  appendTextElement(nextCopy, 'p', quoteCaseNextAction(caseData));
  next.append(nextIcon, nextCopy);
  card.append(next);

  const meta = document.createElement('div');
  meta.className = 'workspace-quote-card-meta';
  appendTextElement(meta, 'span', 'פרטי הנסיעה נשמרו');
  appendTextElement(meta, 'time', caseData.updated_at ? `עודכן ${formatQuoteCaseTime(caseData.updated_at, true)}` : 'ממתין לעדכון');
  card.append(meta);

  const actionStatus = document.createElement('p');
  actionStatus.className = 'workspace-quote-action-status';
  actionStatus.setAttribute('role', 'status');
  actionStatus.setAttribute('aria-live', 'polite');
  card.append(actionStatus);

  const actions = document.createElement('div');
  actions.className = 'workspace-quote-card-actions';
  const open = document.createElement('button');
  open.type = 'button';
  open.dataset.workspaceQuoteAction = 'open';
  const canResume = quoteCaseCanResume(caseData);
  const openIcon = document.createElement('i');
  openIcon.dataset.lucide = canResume ? 'play' : 'sparkles';
  open.append(openIcon, document.createTextNode(canResume ? 'המשיכו את התוכנית' : 'התחילו תוכנית חדשה'));
  open.addEventListener('click', () => {
    if (quoteCaseCanResume(caseData) && !storeAgentRunSession(caseData.source.run_id)) {
      actionStatus.dataset.state = 'error';
      actionStatus.textContent = 'לא ניתן לפתוח את המשך התוכנית כי אחסון ההפעלה הפרטי אינו זמין בדפדפן. אפשרו אחסון זמני ונסו שוב.';
      return;
    }
    if (!quoteCaseCanResume(caseData)) clearAgentRunSession();
    window.location.assign(safeWorkspaceHref(`${window.traVelV2?.homeUrl || '/'}ai-planner/`));
  });
  const handoff = document.createElement('button');
  handoff.type = 'button';
  handoff.className = 'is-secondary';
  handoff.dataset.workspaceQuoteAction = 'handoff';
  const handoffIcon = document.createElement('i');
  handoffIcon.dataset.lucide = 'message-circle';
  handoff.append(handoffIcon, document.createTextNode('המשיכו בוואטסאפ · מענה מיידי 24/7'));
  const pendingMutation = workspaceQuoteCaseMutationRegistry.get(caseData.case_id);
  const retainedIdempotencyKey = pendingMutation?.idempotencyKey || workspaceQuoteCaseRetryKeys.get(caseData.case_id) || '';
  if (retainedIdempotencyKey) handoff.dataset.idempotencyKey = retainedIdempotencyKey;
  if (pendingMutation) {
    handoff.dataset.state = 'loading';
    handoff.disabled = true;
    handoff.setAttribute('aria-busy', 'true');
  }
  handoff.addEventListener('click', async () => {
    if (handoff.dataset.state === 'loading' || workspaceQuoteCaseMutationRegistry.has(caseData.case_id)) return;
    const mutationCaseId = caseData.case_id;
    const focusStartedOnHandoff = document.activeElement === handoff;
    const idempotencyKey = handoff.dataset.idempotencyKey
      || workspaceQuoteCaseRetryKeys.get(mutationCaseId)
      || createAgentClientRequestId();
    handoff.dataset.idempotencyKey = idempotencyKey;
    workspaceQuoteCaseRetryKeys.set(mutationCaseId, idempotencyKey);
    workspaceQuoteCaseMutationRegistry.set(mutationCaseId, {idempotencyKey});
    const popup = window.open('about:blank', '_blank');
    if (popup) popup.opener = null;
    handoff.dataset.state = 'loading';
    handoff.disabled = true;
    actionStatus.dataset.state = 'running';
    actionStatus.textContent = 'מכינים קישור מאובטח עם מספר הבקשה...';
    try {
      const payload = await requestQuoteCaseHandoff(caseData, handoff);
      const updatedCase = normalizeQuoteCasePayload(payload);
      if (updatedCase?.case_id === mutationCaseId) {
        caseData = updatedCase;
        rememberWorkspaceQuoteCase(updatedCase);
      }
      const url = safeQuoteCaseHandoffUrl(payload?.handoff_url);
      if (!url) throw new Error('The handoff URL is unavailable.');
      delete handoff.dataset.idempotencyKey;
      workspaceQuoteCaseRetryKeys.delete(mutationCaseId);
      const pending = workspaceQuoteCaseMutationRegistry.get(mutationCaseId);
      if (pending) pending.idempotencyKey = '';
      actionStatus.dataset.state = payload?.replayed ? 'reused' : 'success';
      actionStatus.textContent = payload?.replayed
        ? 'הקישור המאובטח הקיים נפתח מחדש ללא עדכון כפול.'
        : 'הקישור הוכן ונרשם בבקשה.';
      if (popup) popup.location.replace(url);
      else window.location.assign(url);
      await reconcileWorkspaceQuoteCases();
    } catch (error) {
      if (popup) popup.close();
      actionStatus.dataset.state = 'error';
      if (error?.code === 'quote_case_handoff_timeout') {
        const reconciled = await reconcileWorkspaceQuoteCases();
        actionStatus.textContent = reconciled
          ? 'לא התקבל אישור בתוך 15 שניות. תוצאת הפעולה אינה ודאית; הרשימה סונכרנה ומזהה הפעולה נשמר לניסיון בטוח.'
          : 'לא התקבל אישור בתוך 15 שניות. תוצאת הפעולה אינה ודאית ומזהה הפעולה נשמר; רעננו לפני ניסיון נוסף.';
      } else if (error?.status === 409) {
        delete handoff.dataset.idempotencyKey;
        workspaceQuoteCaseRetryKeys.delete(mutationCaseId);
        const pending = workspaceQuoteCaseMutationRegistry.get(mutationCaseId);
        if (pending) pending.idempotencyKey = '';
        const reconciled = await reconcileWorkspaceQuoteCases();
        actionStatus.textContent = reconciled
          ? 'הבקשה השתנתה במקום אחר. הרשימה סונכרנה והפעולה לא נשלחה; אפשר לבדוק ולנסות שוב.'
          : 'הבקשה השתנתה במקום אחר והפעולה לא נשלחה. לא הצלחנו לסנכרן את הרשימה כרגע.';
      } else {
        actionStatus.textContent = quoteCaseErrorMessage(error);
      }
    } finally {
      const activeBeforeCleanup = document.activeElement;
      const shouldRestoreFocus = focusStartedOnHandoff && (
        activeBeforeCleanup === handoff
        || activeBeforeCleanup === document.body
        || activeBeforeCleanup?.isConnected === false
      );
      workspaceQuoteCaseMutationRegistry.delete(mutationCaseId);
      delete handoff.dataset.state;
      handoff.removeAttribute('aria-busy');
      handoff.disabled = false;
      const grid = document.querySelector('[data-workspace-quote-grid]');
      const currentCard = [...(grid?.children || [])].find(item => item?.dataset?.workspaceQuoteCase === mutationCaseId);
      const currentAction = findWorkspaceDatasetElement(currentCard, 'workspaceQuoteAction', 'handoff');
      const currentStatus = findWorkspaceClassElement(currentCard, 'workspace-quote-action-status');
      if (currentAction) {
        delete currentAction.dataset.state;
        currentAction.removeAttribute('aria-busy');
        currentAction.disabled = false;
        const retryKey = workspaceQuoteCaseRetryKeys.get(mutationCaseId) || '';
        if (retryKey) currentAction.dataset.idempotencyKey = retryKey;
        else delete currentAction.dataset.idempotencyKey;
        if (shouldRestoreFocus) currentAction.focus();
      }
      if (currentStatus && currentStatus !== actionStatus && actionStatus.textContent) {
        currentStatus.dataset.state = actionStatus.dataset.state || 'error';
        currentStatus.textContent = actionStatus.textContent;
      }
    }
  });

  const proposalPanelId = `workspace-proposals-${String(caseData.case_id || '').replace(/[^a-z0-9-]/gi, '')}`;
  const proposalToggleId = `${proposalPanelId}-toggle`;
  const proposalToggle = document.createElement('button');
  proposalToggle.type = 'button';
  proposalToggle.id = proposalToggleId;
  proposalToggle.className = 'is-proposals';
  proposalToggle.dataset.workspaceQuoteAction = 'proposals';
  proposalToggle.dataset.workspaceProposalsToggle = 'true';
  proposalToggle.setAttribute('aria-expanded', String(proposalState.open));
  proposalToggle.setAttribute('aria-controls', proposalPanelId);
  const proposalIcon = document.createElement('i');
  proposalIcon.dataset.lucide = 'badge-check';
  const proposalCount = proposalState.loaded ? proposalState.proposals.length : 0;
  proposalToggle.append(proposalIcon, document.createTextNode(proposalCount > 0 ? `צפו ב-${proposalCount} הצעות אישיות` : 'צפו בהצעות האישיות'));

  const proposalPanel = document.createElement('section');
  proposalPanel.id = proposalPanelId;
  proposalPanel.className = 'workspace-proposals-panel';
  proposalPanel.dataset.workspaceProposalsPanel = 'true';
  proposalPanel.hidden = !proposalState.open;
  proposalPanel.setAttribute('role', 'region');
  proposalPanel.setAttribute('aria-labelledby', proposalToggleId);
  proposalPanel.setAttribute('aria-busy', String(proposalState.loading));
  proposalToggle.addEventListener('click', () => {
    proposalState.open = proposalToggle.getAttribute('aria-expanded') !== 'true';
    proposalToggle.setAttribute('aria-expanded', String(proposalState.open));
    proposalPanel.hidden = !proposalState.open;
    card.classList.toggle('has-open-proposals', proposalState.open);
    if (!proposalState.open) return;
    renderWorkspaceAssistedProposalPanel(proposalPanel, caseData);
    if (!proposalState.loaded && !proposalState.loading) loadWorkspaceAssistedProposals(caseData);
  });
  actions.append(open);
  if (!terminal) actions.append(handoff);
  actions.append(proposalToggle);
  card.append(actions);
  card.append(proposalPanel);
  renderWorkspaceAssistedProposalPanel(proposalPanel, caseData);
  return card;
}

function renderWorkspaceQuoteCases(cases, confirmedForwardIds = new Set(), options = {}) {
  const root = document.querySelector('[data-workspace-quote-cases]');
  const grid = root?.querySelector('[data-workspace-quote-grid]');
  const empty = root?.querySelector('[data-workspace-quote-empty]');
  const status = root?.querySelector('[data-workspace-quote-status]');
  if (!root || !grid) return;
  const displayable = (Array.isArray(cases) ? cases : [])
    .filter(caseData => caseData?.case_id && (quoteCaseActiveStatuses.has(caseData.status) || quoteCaseTerminalStatuses.has(caseData.status)))
    .sort((a, b) => String(b.updated_at || '').localeCompare(String(a.updated_at || '')))
    .slice(0, 12);
  const focusSnapshot = captureWorkspaceListFocus(grid, 'workspaceQuoteCase', 'workspaceQuoteAction');
  grid.replaceChildren(...displayable.map(caseData => renderWorkspaceQuoteCaseCard(caseData, confirmedForwardIds.has(caseData.case_id))));
  if (empty) empty.hidden = displayable.length > 0;
  restoreWorkspaceListFocus(grid, focusSnapshot, 'workspaceQuoteCase', 'workspaceQuoteAction', empty);
  const activeCount = displayable.filter(caseData => quoteCaseActiveStatuses.has(caseData.status)).length;
  const recentCount = displayable.length - activeCount;
  const confirmedCase = displayable.find(caseData => confirmedForwardIds.has(caseData.case_id));
  const attentionTransitionIds = options.attentionTransitionIds && typeof options.attentionTransitionIds.has === 'function' ? options.attentionTransitionIds : new Set();
  const terminalTransitionIds = options.terminalTransitionIds && typeof options.terminalTransitionIds.has === 'function' ? options.terminalTransitionIds : new Set();
  const attentionCase = displayable.find(caseData => attentionTransitionIds.has(caseData.case_id));
  const terminalCase = displayable.find(caseData => terminalTransitionIds.has(caseData.case_id));
  if (status) status.textContent = attentionCase
    ? `הבדיקה האישית ${attentionCase.reference || ''} דורשת כעת מידע נוסף לפי העדכון האחרון.`
    : terminalCase
      ? `הבדיקה האישית ${terminalCase.reference || ''} עברה למצב סיום מאומת.`
      : confirmedCase
        ? `הבדיקה האישית ${confirmedCase.reference || ''} קיבלה עדכון חדש שאושר.`
        : displayable.length
          ? `${activeCount} בקשות פעילות · ${recentCount} עדכונים שהסתיימו לאחרונה`
          : 'אין בקשות סיוע שמורות. תוכנית מובנית תועבר רק לאחר אישורכם.';
  root.setAttribute('aria-busy', 'false');
  renderIcons();
}

function workspaceQuoteCaseSnapshot(cases) {
  return new Map((Array.isArray(cases) ? cases : [])
    .filter(caseData => caseData?.case_id)
    .map(caseData => [caseData.case_id, {
      case_id: caseData.case_id,
      status: String(caseData.status || ''),
      version: Math.max(0, Number(caseData.version) || 0),
      resume_available: caseData.resume_available === true
    }]));
}

function workspaceQuoteCasePrecedes(nextCase, previousCase) {
  if (!nextCase || !previousCase) return false;
  const nextVersion = Math.max(0, Number(nextCase.version) || 0);
  const previousVersion = Math.max(0, Number(previousCase.version) || 0);
  if (nextVersion !== previousVersion) return nextVersion < previousVersion;
  const nextUpdatedAt = Date.parse(nextCase.updated_at || '') || 0;
  const previousUpdatedAt = Date.parse(previousCase.updated_at || '') || 0;
  return Boolean(nextUpdatedAt && previousUpdatedAt && nextUpdatedAt < previousUpdatedAt);
}

function mergeWorkspaceQuoteCases(cases) {
  const merged = new Map();
  (Array.isArray(cases) ? cases : []).forEach(caseData => {
    if (!caseData?.case_id) return;
    const previous = workspaceQuoteCaseRuntime.cases.get(caseData.case_id);
    merged.set(caseData.case_id, previous && workspaceQuoteCasePrecedes(caseData, previous) ? previous : caseData);
  });
  workspaceQuoteCaseMutationRegistry.forEach((mutation, caseId) => {
    if (!merged.has(caseId) && workspaceQuoteCaseRuntime.cases.has(caseId)) {
      merged.set(caseId, workspaceQuoteCaseRuntime.cases.get(caseId));
    }
  });
  return [...merged.values()]
    .sort((a, b) => String(b.updated_at || '').localeCompare(String(a.updated_at || '')))
    .slice(0, 12);
}

function rememberWorkspaceQuoteCase(caseData) {
  if (!caseData?.case_id) return false;
  const previous = workspaceQuoteCaseRuntime.cases.get(caseData.case_id);
  if (previous && workspaceQuoteCasePrecedes(caseData, previous)) return false;
  workspaceQuoteCaseRuntime.cases.set(caseData.case_id, caseData);
  return true;
}

function workspaceQuoteCasesChanged(nextSnapshot) {
  if (nextSnapshot.size !== workspaceQuoteCaseRuntime.snapshot.size) return true;
  return [...nextSnapshot].some(([caseId, next]) => {
    const previous = workspaceQuoteCaseRuntime.snapshot.get(caseId);
    return !previous
      || previous.version !== next.version
      || previous.status !== next.status
      || previous.resume_available !== next.resume_available;
  });
}

function scheduleWorkspaceQuoteCasePoll(delay = 20000) {
  if (workspaceQuoteCaseRuntime.timer) window.clearTimeout(workspaceQuoteCaseRuntime.timer);
  workspaceQuoteCaseRuntime.timer = 0;
  if (!document.querySelector('[data-workspace-quote-cases]')
    || document.visibilityState === 'hidden'
    || workspaceQuoteCaseRuntime.authRequired) return;
  workspaceQuoteCaseRuntime.timer = window.setTimeout(() => pollWorkspaceQuoteCases(), delay);
}

async function pollWorkspaceQuoteCases() {
  workspaceQuoteCaseRuntime.timer = 0;
  if (document.visibilityState === 'hidden') {
    scheduleWorkspaceQuoteCasePoll(60000);
    return;
  }
  await loadWorkspaceQuoteCases({polling: true});
}

function reconcileWorkspaceQuoteCases() {
  const root = document.querySelector('[data-workspace-quote-cases]');
  if (!root || workspaceQuoteCaseRuntime.authRequired) return Promise.resolve(false);
  if (!workspaceQuoteCaseRuntime.inFlight) return loadWorkspaceQuoteCases({polling:true});
  return new Promise(resolve => {
    workspaceQuoteCaseRuntime.reconcileWaiters.push(resolve);
  });
}

async function loadWorkspaceQuoteCases({polling = false} = {}) {
  const root = document.querySelector('[data-workspace-quote-cases]');
  if (!root || workspaceQuoteCaseRuntime.inFlight || workspaceQuoteCaseRuntime.authRequired) return false;
  const status = root.querySelector('[data-workspace-quote-status]');
  workspaceQuoteCaseRuntime.inFlight = true;
  const controller = typeof AbortController === 'function' ? new AbortController() : null;
  workspaceQuoteCaseRuntime.controller = controller;
  let timedOut = false;
  const timeoutId = controller ? window.setTimeout(() => {
    timedOut = true;
    controller.abort();
  }, 15000) : 0;
  if (!polling) root.setAttribute('aria-busy', 'true');
  try {
    const payload = await agentApiRequest('/quote-cases', controller ? {signal: controller.signal} : {});
    const cases = mergeWorkspaceQuoteCases(Array.isArray(payload?.cases) ? payload.cases : []);
    const nextSnapshot = workspaceQuoteCaseSnapshot(cases);
    const changed = !polling || workspaceQuoteCasesChanged(nextSnapshot);
    const confirmedForwardIds = new Set();
    const attentionTransitionIds = new Set();
    const terminalTransitionIds = new Set();
    if (polling && changed) {
      cases.forEach(caseData => {
        const previous = workspaceQuoteCaseRuntime.snapshot.get(caseData?.case_id);
        const newlyPositive = !previous && ['queued', 'in_review', 'ready_for_assistance'].includes(caseData?.status);
        if (newlyPositive || isConfirmedQuoteCaseForward(previous, caseData) || isConfirmedQuoteCaseRecovery(previous, caseData)) confirmedForwardIds.add(caseData.case_id);
        if (caseData?.status === 'needs_information' && previous?.status !== 'needs_information') attentionTransitionIds.add(caseData.case_id);
        if (quoteCaseTerminalStatuses.has(caseData?.status) && previous?.status !== caseData.status) terminalTransitionIds.add(caseData.case_id);
      });
    }
    if (changed) renderWorkspaceQuoteCases(cases, confirmedForwardIds, {polling,attentionTransitionIds,terminalTransitionIds});
    else root.setAttribute('aria-busy', 'false');
    workspaceQuoteCaseRuntime.snapshot = nextSnapshot;
    workspaceQuoteCaseRuntime.cases = new Map(cases.map(caseData => [caseData.case_id, caseData]));
    workspaceQuoteCaseRuntime.failures = 0;
    workspaceQuoteCaseRuntime.authRequired = false;
    return true;
  } catch (error) {
    root.setAttribute('aria-busy', 'false');
    const hiddenAbort = error?.name === 'AbortError' && !timedOut && document.visibilityState === 'hidden';
    const authRequired = error?.status === 401 || error?.status === 403;
    if (authRequired) {
      workspaceQuoteCaseRuntime.authRequired = true;
      workspaceQuoteCaseRuntime.failures = 0;
      root.dataset.state = 'reauth_required';
      if (status) status.textContent = 'הגישה הפרטית לבקשות הסיוע פגה. התחברו מחדש או רעננו את החיבור לפני קבלת עדכונים נוספים.';
    } else if (!hiddenAbort) {
      workspaceQuoteCaseRuntime.failures = Math.min(8, workspaceQuoteCaseRuntime.failures + 1);
      if (status && (!polling || workspaceQuoteCaseRuntime.failures >= 3)) status.textContent = error?.status === 404
        ? 'שירות בקשות הסיוע עדיין אינו זמין באתר. ננסה להתחבר שוב אוטומטית.'
        : 'העדכון החי אינו זמין כרגע. המצב האחרון שאושר נשאר מוצג וננסה שוב אוטומטית.';
      if (!polling) {
        const empty = root.querySelector('[data-workspace-quote-empty]');
        if (empty) empty.hidden = false;
      }
    }
    return false;
  } finally {
    if (timeoutId) window.clearTimeout(timeoutId);
    if (workspaceQuoteCaseRuntime.controller === controller) workspaceQuoteCaseRuntime.controller = null;
    workspaceQuoteCaseRuntime.inFlight = false;
    const retryDelay = workspaceQuoteCaseRuntime.failures
      ? Math.min(180000, 10000 * (2 ** Math.min(workspaceQuoteCaseRuntime.failures - 1, 4)))
      : 20000;
    const reconcileWaiters = workspaceQuoteCaseRuntime.reconcileWaiters.splice(0);
    if (reconcileWaiters.length) {
      const reconciliation = workspaceQuoteCaseRuntime.authRequired
        ? Promise.resolve(false)
        : loadWorkspaceQuoteCases({polling:true});
      Promise.resolve(reconciliation).then(
        result => reconcileWaiters.forEach(resolve => resolve(Boolean(result))),
        () => reconcileWaiters.forEach(resolve => resolve(false))
      );
    } else if (!workspaceQuoteCaseRuntime.authRequired) scheduleWorkspaceQuoteCasePoll(retryDelay);
  }
}

async function initTravelerWorkspace() {
  const root = document.querySelector('[data-traveler-workspace]');
  const hasMapSaveControls = Boolean(document.querySelector('[data-map-result] .save-button'));
  const authenticatedWorkspace = Boolean(window.traVelV2?.isLoggedIn);
  if (!root && !hasMapSaveControls) return;
  workspaceAccountAuthRequired = false;
  workspaceDeletionTombstones = readWorkspaceDeletionTombstones();
  travelerWorkspace = readLocalWorkspace();
  installWorkspaceStorageListener();
  if (root) {
    renderWorkspaceDashboard();
    hydrateWorkspacePreferences(travelerWorkspace.preferences);
    if (authenticatedWorkspace) {
      loadWorkspacePlans();
      root.querySelector('[data-workspace-cockpit-retry]')?.addEventListener('click', () => loadWorkspacePlans({manual: true}));
    } else {
      setWorkspaceCockpitState('local', 'האפשרויות נשמרות במכשיר. התחברו כדי לראות כאן תוכניות חופשה פרטיות מהחשבון.', '', false);
    }
    loadWorkspaceQuoteCases();
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState !== 'visible') {
        workspaceQuoteCaseRuntime.controller?.abort();
        if (authenticatedWorkspace) workspacePlanRuntime.controller?.abort();
        return;
      }
      scheduleWorkspaceQuoteCasePoll(250);
      if (authenticatedWorkspace) scheduleWorkspacePlanPoll(250);
    });
  }
  document.querySelectorAll('[data-workspace-filter]').forEach(button => button.addEventListener('click', () => {
    activeWorkspaceFilter = button.dataset.workspaceFilter;
    document.querySelectorAll('[data-workspace-filter]').forEach(filter => {
      const selected = filter === button;
      filter.classList.toggle('is-active', selected);
      filter.setAttribute('aria-pressed', String(selected));
    });
    renderWorkspaceDashboard();
  }));
  const form = document.querySelector('[data-workspace-preferences]');
  form?.addEventListener('input', markWorkspacePreferencesDirty);
  form?.addEventListener('change', markWorkspacePreferencesDirty);
  form?.addEventListener('submit', async event => {
    event.preventDefault();
    if (form.getAttribute('aria-busy') === 'true') return;
    const submittedEditGeneration = workspacePreferencesEditGeneration;
    const submit = form.querySelector('[type="submit"]');
    const status = form.querySelector('[data-workspace-preferences-status]');
    form.setAttribute('aria-busy', 'true');
    if (submit) submit.disabled = true;
    if (status) {
      status.dataset.state = 'loading';
      status.textContent = 'שומר את ההעדפות במכשיר ובודק אישור חשבון...';
    }
    try {
      const result = await saveWorkspacePreferences(form);
      const currentSubmissionConfirmed = result?.localSaved && (
        !authenticatedWorkspace
        || (result?.accountSynced && result?.devicePersisted !== false && result?.reason !== 'local_changed')
      );
      if (currentSubmissionConfirmed) confirmWorkspacePreferencesSubmission(submittedEditGeneration, travelerWorkspace?.preferences || {});
      if (status) {
        status.dataset.state = result?.accountSynced && result?.devicePersisted !== false ? 'confirmed' : result?.accountSynced ? 'account' : result?.localSaved ? 'local' : 'error';
        status.textContent = result?.accountSynced && result?.devicePersisted !== false
          ? 'ההעדפות אושרו בחשבון ובמכשיר.'
          : result?.accountSynced
            ? 'ההעדפות אושרו בחשבון ומוצגות בלשונית הזאת, אך העדכון לא נשמר במכשיר.'
          : result?.localSaved
            ? 'ההעדפות נשמרו במכשיר. לא התקבל אישור סנכרון לחשבון.'
            : 'ההעדפות לא נשמרו. בדקו את הפרטים ונסו שוב.';
      }
    } finally {
      form.setAttribute('aria-busy', 'false');
      if (submit) submit.disabled = false;
    }
  });
  form?.elements.home_airport?.addEventListener('input', event => {
    event.target.value = event.target.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3);
  });
  if (!authenticatedWorkspace) {
    setWorkspaceAccountSyncState('local', 'האפשרויות נשמרות באופן פרטי במכשיר הזה. לא נשלח מידע לחשבון.');
    return;
  }
  setWorkspaceAccountSyncState('syncing', 'מסנכרנים את המכשיר והחשבון בבקשה אחת מאובטחת...');
  workspaceAccountSyncInFlight = true;
  try {
    const syncResult = await synchronizeWorkspaceAccount();
    if (root) {
      renderWorkspaceDashboard();
      hydrateWorkspacePreferences(travelerWorkspace.preferences);
    }
    refreshMapSaveControls();
    if (syncResult.confirmed) {
      setWorkspaceAccountSyncState('confirmed', 'החשבון והמכשיר סונכרנו בהצלחה.');
    } else {
      const reason = syncResult.reason === 'local_changed'
        ? 'השמירות השתנו בזמן הסנכרון. השינוי החדש נשמר ולא נדרס. רעננו את העמוד כדי לנסות שוב.'
        : syncResult.reason === 'deletion_not_confirmed'
          ? 'אפשרות שסומנה למחיקה עדיין הופיעה בחשבון. היא נשארה מוסתרת; רעננו את העמוד כדי לנסות שוב.'
          : 'התקבלה תשובה, אך השמירה המלאה במכשיר לא אושרה. לא מוצג אישור סנכרון.';
      setWorkspaceAccountSyncState(syncResult.reason === 'local_changed' ? 'local_changed' : 'incomplete', reason);
      if (syncResult.reason === 'local_changed') scheduleWorkspaceCorrectiveSync(250, true);
    }
  } catch (error) {
    if (workspaceAuthenticationRequired(error)) {
      requireWorkspaceReauthentication('החיבור לחשבון פג. השמירות במכשיר נשארו זמינות. התחברו מחדש או רעננו לפני סנכרון נוסף.');
    } else {
      setWorkspaceAccountSyncState(
        workspaceCapacityError(error) ? 'capacity' : 'unavailable',
        workspaceCapacityError(error)
          ? 'החשבון מלא: אפשר לסנכרן עד 50 אפשרויות. הסירו אפשרות אחת מהחשבון כדי להמשיך בסנכרון.'
          : 'מוצגות השמירות במכשיר. הסנכרון לחשבון לא אושר כרגע.'
      );
      console.warn(error);
    }
  } finally {
    workspaceAccountSyncInFlight = false;
  }
}

function initNavigation() {
  const triggers = document.querySelectorAll('.nav-trigger');
  const closeAll = except => triggers.forEach(trigger => {
    if (trigger !== except) {
      trigger.setAttribute('aria-expanded', 'false');
      document.getElementById(trigger.getAttribute('aria-controls'))?.classList.remove('is-open');
    }
  });
  triggers.forEach(trigger => trigger.addEventListener('click', event => {
    event.stopPropagation();
    const menu = document.getElementById(trigger.getAttribute('aria-controls'));
    const opening = trigger.getAttribute('aria-expanded') !== 'true';
    closeAll(trigger);
    trigger.setAttribute('aria-expanded', String(opening));
    menu?.classList.toggle('is-open', opening);
  }));
  document.addEventListener('click', () => closeAll());
  const menuButton = document.querySelector('.mobile-menu-button');
  const drawer = document.querySelector('.mobile-drawer');
  const drawerClose = drawer?.querySelector('.mobile-drawer-close');
  const disclosures = drawer?.querySelectorAll('.mobile-nav-disclosure') || [];
  let drawerWasOpenedBy = null;
  const drawerInertSnapshot = new Map();

  const drawerBackgroundTargets = () => {
    if (!drawer || !document.body) return [];
    const targets = new Set();
    let branch = drawer;
    while (branch && branch !== document.body) {
      const parent = branch.parentElement;
      if (!parent) break;
      Array.from(parent.children).forEach(sibling => {
        if (sibling !== branch) targets.add(sibling);
      });
      branch = parent;
    }
    return Array.from(targets).filter(element => element !== drawer && !element.contains(drawer));
  };

  const setDrawerBackgroundInert = inert => {
    if (inert) {
      drawerInertSnapshot.clear();
      drawerBackgroundTargets().forEach(element => {
        drawerInertSnapshot.set(element, {
          attribute: element.hasAttribute('inert'),
          property: element.inert === true
        });
        element.inert = true;
        element.setAttribute('inert', '');
      });
      return;
    }
    drawerInertSnapshot.forEach((previous, element) => {
      element.inert = previous.property;
      if (previous.attribute) element.setAttribute('inert', '');
      else element.removeAttribute('inert');
    });
    drawerInertSnapshot.clear();
  };

  const drawerFocusableControls = () => drawer
    ? Array.from(drawer.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'))
      .filter(element => element.getAttribute('aria-hidden') !== 'true' && element.getClientRects().length > 0)
    : [];

  const containDrawerFocus = event => {
    if (event.key !== 'Tab' || !drawer?.classList.contains('is-open')) return false;
    const focusable = drawerFocusableControls();
    if (!focusable.length) {
      event.preventDefault();
      drawer.focus();
      return true;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = document.activeElement;
    if (event.shiftKey && (active === first || !drawer.contains(active))) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && (active === last || !drawer.contains(active))) {
      event.preventDefault();
      first.focus();
    }
    return true;
  };

  const setDrawerState = (opening, returnFocus = false) => {
    if (!menuButton || !drawer) return;
    const wasOpen = drawer.classList.contains('is-open');
    drawer.classList.toggle('is-open', opening);
    drawer.setAttribute('aria-hidden', String(!opening));
    menuButton.setAttribute('aria-expanded', String(opening));
    menuButton.setAttribute('aria-label', opening ? 'סגירת תפריט' : 'פתיחת תפריט');
    document.body.classList.toggle('mobile-navigation-open', opening);
    if (opening) {
      if (!wasOpen) {
        drawerWasOpenedBy = document.activeElement;
        setDrawerBackgroundInert(true);
      }
      window.requestAnimationFrame(() => drawerClose?.focus());
    } else {
      setDrawerBackgroundInert(false);
      if (returnFocus && drawerWasOpenedBy instanceof HTMLElement) drawerWasOpenedBy.focus();
      drawerWasOpenedBy = null;
    }
  };

  menuButton?.addEventListener('click', () => setDrawerState(!drawer?.classList.contains('is-open')));
  drawerClose?.addEventListener('click', () => setDrawerState(false, true));
  drawer?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setDrawerState(false)));

  disclosures.forEach(button => button.addEventListener('click', () => {
    const panel = document.getElementById(button.getAttribute('aria-controls'));
    const opening = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(opening));
    panel?.classList.toggle('is-open', opening);
  }));

  document.addEventListener('keydown', event => {
    if (containDrawerFocus(event)) return;
    if (event.key !== 'Escape') return;
    const openTrigger = Array.from(triggers).find(trigger => trigger.getAttribute('aria-expanded') === 'true');
    if (openTrigger) {
      closeAll();
      openTrigger.focus();
      return;
    }
    if (drawer?.classList.contains('is-open')) setDrawerState(false, true);
  });

  window.matchMedia('(min-width: 1101px)').addEventListener?.('change', event => {
    if (event.matches) setDrawerState(false);
  });
}

function initMap() {
  const filterPanel = document.querySelector('.theme-map-shell .filter-panel');
  const filterHost = document.querySelector('[data-mobile-filter-host]');
  const mapWorkspace = document.querySelector('.theme-map-shell .map-workspace');
  const mapMain = document.querySelector('.theme-map-shell .map-main-column');
  const filterToggle = document.querySelector('[data-filter-toggle]');
  const mobileFilterMedia = window.matchMedia('(max-width: 760px)');
  const syncFilterPlacement = () => {
    if (!filterPanel || !filterHost || !mapWorkspace || !mapMain) return;
    if (mobileFilterMedia.matches) {
      if (filterPanel.parentElement !== filterHost) filterHost.append(filterPanel);
    } else if (filterPanel.parentElement !== mapWorkspace) {
      mapWorkspace.insertBefore(filterPanel, mapMain);
      filterPanel.classList.remove('is-open');
      document.querySelector('[data-filter-toggle]')?.setAttribute('aria-expanded', 'false');
    }
  };
  syncFilterPlacement();
  mobileFilterMedia.addEventListener?.('change', syncFilterPlacement);

  document.querySelectorAll('.price-pin[data-destination]').forEach(bindDestinationPin);
  document.querySelectorAll('[data-discovery-globe] [data-exploration-hub]').forEach(bindExplorationHubMarker);
  document.querySelectorAll('[data-map-destination-link][data-destination]').forEach(bindMapDestinationLink);
  document.querySelectorAll('[data-map-result] .save-button').forEach(button => button.addEventListener('click', () => {
    if (button.disabled || discoveryRequestPending) return;
    const data = destinationData[activeDestination];
    if (data) saveWorkspaceItem(mapDestinationWorkspaceItem(data), button);
  }));

  document.querySelectorAll('[data-map-zoom]').forEach(button => button.addEventListener('click', () => {
    const globe = button.closest('.globe-panel, .compact-map, .world-canvas')?.querySelector('.globe');
    if (!globe) return;
    if (globe.matches('[data-globe-3d]') && window.traVelGlobe3D) {
      const handled = window.traVelGlobe3D.zoom(button.dataset.mapZoom, { root: globe });
      if (handled) return;
    }
    const current = Number(globe.dataset.scale || 1);
    const isHomepageGlobe = Boolean(globe.closest('.home-globe-stack'));
    const next = button.dataset.mapZoom === 'in'
      ? Math.min(current + (isHomepageGlobe ? .03 : .12), isHomepageGlobe ? 1.03 : 1.45)
      : Math.max(current - (isHomepageGlobe ? .08 : .12), isHomepageGlobe ? .84 : .78);
    globe.dataset.scale = next;
    globe.style.scale = next;
  }));

  document.querySelectorAll('[data-route]').forEach(card => card.addEventListener('click', () => selectRoute(card)));
  document.querySelectorAll('[data-map-layer]').forEach(button => button.addEventListener('click', () => {
    discoveryDestinationLocked = false;
    const focusedDestination = activeDestination;
    clearActiveMapEntitySelection();
    if (focusedDestination && destinationData[focusedDestination]) {
      const destination = destinationData[focusedDestination];
      setActivePlanningSelection({ latitude: destination.latitude, longitude: destination.longitude, destination: focusedDestination, kind: 'destination' });
    }
    activeLayer = button.dataset.mapLayer;
    syncDiscoveryControls();
    updatePins();
    setActiveDestination(activeDestination, null, false);
    syncDiscoveryUrl('push');
    hydrateDiscovery(discoveryRequestParams({ focus: focusedDestination }));
  }));

  const budget = document.querySelector('[data-budget]');
  const value = document.querySelector('[data-budget-value]');
  budget?.addEventListener('input', () => { if (value) value.textContent = `$${budget.value}`; });
  document.querySelector('[data-discovery-apply]')?.addEventListener('click', () => {
    discoveryDestinationLocked = false;
    activeRouteSelectionLocked = false;
    clearActiveMapEntitySelection();
    if (activeDestination && destinationData[activeDestination]) {
      const destination = destinationData[activeDestination];
      setActivePlanningSelection({ latitude: destination.latitude, longitude: destination.longitude, destination: activeDestination, kind: 'destination' });
    }
    const sort = document.querySelector('[data-filter-kind="sort"] .is-active')?.dataset.filterValue || 'smart';
    const trip = document.querySelector('[data-filter-kind="trip"] .is-active')?.dataset.filterValue || 'all';
    const direct = document.querySelector('[data-direct-filter]')?.getAttribute('aria-pressed') === 'true';
    const maxStops = document.querySelector('[data-max-stops]')?.checked ? 1 : 3;
    const maxDuration = document.querySelector('[data-max-duration]')?.checked ? 960 : 3000;
    const allowOvernight = Boolean(document.querySelector('[data-allow-overnight]')?.checked);
    discoveryQuery = {
      ...discoveryQuery,
      budget: clampDiscoveryNumber(budget?.value, 200, 1600, discoveryDefaults.budget),
      direct,
      sort,
      trip,
      max_stops: maxStops,
      max_duration: maxDuration,
      allow_overnight: allowOvernight
    };
    syncDiscoveryControls();
    syncDiscoveryUrl('push');
    hydrateDiscovery(discoveryRequestParams());
    document.querySelector('.filter-panel')?.classList.remove('is-open');
    filterToggle?.setAttribute('aria-expanded', 'false');
    if (mobileFilterMedia.matches) filterToggle?.focus();
  });

  document.addEventListener('click', event => {
    const filterButton = event.target.closest?.('[data-filter-toggle]');
    if (filterButton) {
      const opening = !filterPanel?.classList.contains('is-open');
      filterPanel?.classList.toggle('is-open', opening);
      filterButton.setAttribute('aria-expanded', String(opening));
      if (opening) window.requestAnimationFrame(() => filterPanel?.querySelector('[data-filter-close]')?.focus());
      return;
    }
    if (event.target.closest?.('[data-filter-close]')) {
      filterPanel?.classList.remove('is-open');
      filterToggle?.setAttribute('aria-expanded', 'false');
      filterToggle?.focus();
    }
  }, true);

  if (isMapWorkspacePage()) {
    window.addEventListener('popstate', event => {
      activeRouteSelectionLocked = false;
      clearActiveMapEntitySelection();
      const historySelectionRestored = restorePlanningSelectionFromHistory(event.state?.planningSelection);
      readDiscoveryStateFromUrl({ preservePlanningSelection: historySelectionRestored });
      const historyFocus = String(event.state?.focus || '').toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 60);
      if (discoveryDestinationMode !== 'anywhere' && !discoveryDestinationLocked && historyFocus && destinationData[historyFocus]) activeDestination = historyFocus;
      syncDiscoveryControls();
      updatePins();
      if (activeFreePlanningPoint()) {
        const globeRoot = document.querySelector('.theme-map-shell [data-globe-3d][data-discovery-globe]');
        renderUnsupportedGlobeSelection({
          selectionId: activePlanningSelection.selection_id,
          latitude: activePlanningSelection.latitude,
          longitude: activePlanningSelection.longitude,
          inputType: 'history'
        }, globeRoot);
        return;
      }
      if (discoveryDestinationMode === 'anywhere') renderDiscoveryEmptyState({ reason: 'open' });
      else if (destinationData[activeDestination]) setActiveDestination(activeDestination, null, false);
      hydrateDiscovery(discoveryRequestParams(!discoveryDestinationLocked && historyFocus ? { focus: historyFocus } : {}));
    });
  }
}

const homeSearchProductContracts = Object.freeze({
  package: { destination: 'destination', departure: 'departure_date', return: 'return_date', usesOrigin: true, usesRooms: true, destinationMode: 'code' },
  packages: { destination: 'destination', departure: 'departure_date', return: 'return_date', usesOrigin: true, usesRooms: true, destinationMode: 'code' },
  flights: { destination: 'destination', departure: 'departure_date', return: 'return_date', usesOrigin: true, usesRooms: false, destinationMode: 'code' },
  hotels: { destination: 'destination', departure: 'checkin', return: 'checkout', usesOrigin: false, usesRooms: true, destinationMode: 'code' },
  insurance: { destination: 'trip_destination', departure: 'start_date', return: 'end_date', usesOrigin: false, usesRooms: false, destinationMode: 'slug' }
});

function homeSearchProductContract(kind) {
  return homeSearchProductContracts[kind] || homeSearchProductContracts.package;
}

function normalizedHomeSearchCount(control, minimum, maximum) {
  if (!control || control.disabled) return null;
  const value = Number(String(control.value || '').trim());
  return Number.isInteger(value) && value >= minimum && value <= maximum ? value : null;
}

function syncHomeSearchTripContext(form) {
  if (!form) return discoveryTripContext;
  const product = homeSearchProductContracts[form.dataset.productKind] ? form.dataset.productKind : 'package';
  const contract = homeSearchProductContract(product);
  const originControl = form.querySelector('[data-home-origin-wrap] input');
  const originValue = String(originControl?.value || '').trim().toUpperCase();
  const origin = contract.usesOrigin && !originControl?.disabled && /^[A-Z]{3}$/.test(originValue) ? originValue : '';
  const departureDate = normalizeDiscoveryTripDate(form.querySelector('[data-home-departure]')?.value);
  let returnDate = normalizeDiscoveryTripDate(form.querySelector('[data-home-return]')?.value);
  const sameDayAllowed = product === 'insurance';
  if (departureDate && returnDate && (returnDate < departureDate || (!sameDayAllowed && returnDate === departureDate))) returnDate = '';
  const adults = normalizedHomeSearchCount(form.querySelector('[data-home-adults]'), 1, 6);
  const children = normalizedHomeSearchCount(form.querySelector('[data-home-children]'), 0, 4);
  const rooms = contract.usesRooms ? normalizedHomeSearchCount(form.querySelector('[data-home-rooms]'), 1, 3) : null;
  discoveryTripContext = { product, origin, departureDate, returnDate, adults, children, rooms };
  return discoveryTripContext;
}

function homeSearchFollowingDate(value) {
  return travelDateAfter(value, 1);
}

function setHomeSearchStep(progress, name, state, detail = '', animate = true) {
  const step = progress?.querySelector(`[data-home-search-step="${name}"]`);
  if (!step) return;
  const previousState = step.dataset.state || '';
  const detailElement = step.querySelector('small');
  const changed = previousState !== state || (detail && detailElement?.textContent !== detail);
  step.dataset.state = state;
  if (detail && detailElement) detailElement.textContent = detail;
  step.classList.remove('is-new');
  if (changed && animate && state === 'confirmed' && !prefersReducedMotion()) {
    void step.offsetWidth;
    step.classList.add('is-new');
    window.clearTimeout(step.traVelMotionTimer);
    step.traVelMotionTimer = window.setTimeout(() => step.classList.remove('is-new'), 760);
  }
}

function homeSearchDatesAreValid(form) {
  const departure = form?.querySelector('[data-home-departure]');
  const returning = form?.querySelector('[data-home-return]');
  if (!departure || !returning) return false;
  const sameDayAllowed = form.dataset.productKind === 'insurance';
  const ordered = Boolean(departure.value && returning.value && (sameDayAllowed ? returning.value >= departure.value : returning.value > departure.value));
  returning.setCustomValidity(ordered ? '' : (sameDayAllowed ? 'סיום הכיסוי לא יכול להיות לפני תחילת הכיסוי' : 'תאריך הסיום חייב להיות אחרי תאריך ההתחלה'));
  returning.min = sameDayAllowed ? (departure.value || returning.min) : (homeSearchFollowingDate(departure.value) || returning.min);
  return ordered;
}

function homeSearchCriteriaAreValid(form) {
  return Boolean(form && homeSearchDatesAreValid(form) && form.checkValidity());
}

function updateHomeSearchCriteriaState(form, { announce = true, animate = true } = {}) {
  const progress = document.querySelector('[data-home-search-progress]');
  const status = progress?.querySelector('[data-home-search-status]');
  const ready = homeSearchCriteriaAreValid(form);
  const departure = form?.querySelector('[data-home-departure]')?.value || '';
  const returning = form?.querySelector('[data-home-return]')?.value || '';
  const adults = Number(form?.querySelector('[data-home-adults]')?.value || 0);
  const children = Number(form?.querySelector('[data-home-children]')?.value || 0);
  const rooms = form?.querySelector('[data-home-rooms]');
  const partySize = adults + children;
  const roomDetail = rooms && !rooms.disabled ? ` · ${Number(rooms.value) || 1} חדרים` : '';
  const readyDetail = departure && returning && partySize
    ? `${departure} עד ${returning} · ${partySize} נוסעים${roomDetail}`
    : 'הפרטים מוכנים';
  if (form.dataset.state !== 'navigating') form.dataset.state = ready ? 'ready' : 'invalid';
  if (progress && form.dataset.state !== 'navigating') progress.dataset.state = ready ? 'ready' : 'invalid';
  setHomeSearchStep(progress, 'criteria', ready ? 'confirmed' : 'failed', ready ? readyDetail : 'נדרש להשלים או לתקן פרטים', animate);
  const handoff = progress?.querySelector('[data-home-search-step="handoff"]');
  if (ready && handoff?.dataset.state === 'failed') setHomeSearchStep(progress, 'handoff', 'waiting', 'תתחיל לאחר לחיצה', false);
  if (announce) {
    setTextContentIfChanged(status, ready
      ? 'הפרטים מוכנים. המחירים והזמינות ייבדקו רק בעמוד ההשוואה.'
      : 'יש להשלים את הפרטים המסומנים לפני פתיחת ההשוואה.');
  }
  return ready;
}

function syncHomeSearchProduct(form, tab, { announce = true, animate = true, focus = false } = {}) {
  if (!form || !tab) return;
  const tabs = Array.from(document.querySelectorAll('.product-tabs [role="tab"][data-product-kind]'));
  const kind = homeSearchProductContracts[tab.dataset.productKind] ? tab.dataset.productKind : 'package';
  const contract = homeSearchProductContract(kind);
  tabs.forEach(item => {
    const selected = item === tab;
    item.classList.toggle('is-active', selected);
    item.setAttribute('aria-selected', String(selected));
    item.tabIndex = selected ? 0 : -1;
  });
  form.dataset.productKind = kind;
  form.action = tab.dataset.productAction || form.action;
  form.setAttribute('aria-labelledby', tab.id);

  const originWrap = form.querySelector('[data-home-origin-wrap]');
  const origin = originWrap?.querySelector('input');
  form.dataset.usesOrigin = String(contract.usesOrigin);
  if (originWrap) originWrap.hidden = !contract.usesOrigin;
  if (origin) origin.disabled = !contract.usesOrigin;

  const destination = form.querySelector('[data-home-destination]');
  if (destination) {
    Array.from(destination.options).forEach(option => {
      option.value = contract.destinationMode === 'slug' ? option.dataset.slug : option.dataset.code;
    });
    destination.name = contract.destination;
  }
  const departure = form.querySelector('[data-home-departure]');
  const returning = form.querySelector('[data-home-return]');
  if (departure) departure.name = contract.departure;
  if (returning) returning.name = contract.return;
  const roomsWrap = form.querySelector('[data-home-rooms-wrap]');
  const rooms = form.querySelector('[data-home-rooms]');
  form.dataset.usesRooms = String(contract.usesRooms);
  if (roomsWrap) roomsWrap.hidden = !contract.usesRooms;
  if (rooms) rooms.disabled = !contract.usesRooms;
  setTextContentIfChanged(form.querySelector('[data-home-departure-label]'), tab.dataset.departureLabel || 'יציאה');
  setTextContentIfChanged(form.querySelector('[data-home-return-label]'), tab.dataset.returnLabel || 'חזרה');
  setTextContentIfChanged(form.querySelector('[data-home-search-submit] span'), tab.dataset.submitLabel || 'פתחו השוואה');

  const progress = document.querySelector('[data-home-search-progress]');
  const productLabel = tab.textContent.trim().replace(/\s+/g, ' ');
  setHomeSearchStep(progress, 'product', 'confirmed', `בחרתם: ${productLabel}`, animate);
  setHomeSearchStep(progress, 'handoff', 'waiting', 'תתחיל לאחר לחיצה', false);
  if (progress) progress.dataset.state = 'ready';
  if (announce) setTextContentIfChanged(progress?.querySelector('[data-home-search-status]'), `בחרתם: ${productLabel}. השלימו פרטים ופתחו השוואה עדכנית.`);
  syncHomeSearchTripContext(form);
  refreshHomeDestinationPlanLinks();
  updateHomeSearchCriteriaState(form, { announce: false, animate });
  if (focus) tab.focus();
}

function setHomeSearchRoutingState(form, message) {
  const progress = document.querySelector('[data-home-search-progress]');
  const submit = form.querySelector('[data-home-search-submit]');
  form.dataset.state = 'navigating';
  form.setAttribute('aria-busy', 'true');
  if (submit) submit.disabled = true;
  if (progress) progress.dataset.state = 'navigating';
  setHomeSearchStep(progress, 'handoff', 'running', 'פותחים את עמוד ההשוואה', false);
  setTextContentIfChanged(progress?.querySelector('[data-home-search-status]'), message);
}

function homeSearchNavigationUrl(form, anywhere) {
  const url = new URL(anywhere ? (form.dataset.mapAction || '/travel-map/') : form.action, window.location.origin);
  const controls = [
    form.querySelector('[data-home-origin-wrap] input'),
    form.querySelector('[data-home-destination]'),
    form.querySelector('[data-home-departure]'),
    form.querySelector('[data-home-return]'),
    form.querySelector('[data-home-adults]'),
    form.querySelector('[data-home-children]'),
    form.querySelector('[data-home-rooms]')
  ];
  // Theme 1.26.0: the tap calendar's flexible-dates choice travels with every
  // handoff, but only when the traveler actually chose it.
  const flexibility = form.querySelector('[data-trip-flexibility]');
  if (!anywhere) {
    controls.forEach(control => {
      if (!control?.disabled && control.name && control.value !== '') url.searchParams.set(control.name, control.value);
    });
    if (flexibility?.value) url.searchParams.set('flexibility', flexibility.value);
    return url;
  }
  url.searchParams.set('destination_mode', 'anywhere');
  url.searchParams.set('product', form.dataset.productKind || 'package');
  const origin = controls[0];
  const rooms = controls[6];
  if (origin && !origin.disabled) url.searchParams.set('origin', origin.value.toUpperCase());
  url.searchParams.set('departure_date', controls[2]?.value || '');
  url.searchParams.set('return_date', controls[3]?.value || '');
  url.searchParams.set('adults', controls[4]?.value || '2');
  url.searchParams.set('children', controls[5]?.value || '0');
  if (rooms && !rooms.disabled) url.searchParams.set('rooms', rooms.value || '1');
  if (flexibility?.value) url.searchParams.set('flexibility', flexibility.value);
  return url;
}

function scheduleHomeSearchNavigation(url) {
  let navigated = false;
  let fallbackTimer = 0;
  const navigate = () => {
    if (navigated) return;
    navigated = true;
    if (fallbackTimer) window.clearTimeout(fallbackTimer);
    window.location.assign(url.toString());
  };
  if (prefersReducedMotion() || typeof window.requestAnimationFrame !== 'function') {
    navigate();
    return;
  }
  fallbackTimer = window.setTimeout(navigate, 700);
  window.requestAnimationFrame(() => window.requestAnimationFrame(() => window.setTimeout(navigate, 120)));
}

function initHomeDiscoverySearch() {
  const form = document.querySelector('[data-home-search]');
  const tabs = Array.from(document.querySelectorAll('.product-tabs [role="tab"][data-product-kind]'));
  if (!form || !tabs.length) return;
  const params = new URLSearchParams(window.location.search);
  const requestedKind = String(params.get('product') || '').replace(/[^a-z]/g, '');
  const activeTab = tabs.find(tab => tab.dataset.productKind === requestedKind)
    || tabs.find(tab => tab.getAttribute('aria-selected') === 'true')
    || tabs[0];
  syncHomeSearchProduct(form, activeTab, { announce: false, animate: false });

  const origin = form.querySelector('[data-home-origin-wrap] input');
  const requestedOrigin = String(params.get('origin') || '').toUpperCase();
  if (origin && /^[A-Z]{3}$/.test(requestedOrigin)) origin.value = requestedOrigin;
  const destination = form.querySelector('[data-home-destination]');
  const requestedDestination = String(params.get('destination') || '').toLowerCase();
  if (destination && requestedDestination) {
    const option = Array.from(destination.options).find(item => item.dataset.code?.toLowerCase() === requestedDestination || item.dataset.slug === requestedDestination);
    if (option) destination.value = option.value;
  }
  const departure = form.querySelector('[data-home-departure]');
  const returning = form.querySelector('[data-home-return]');
  const requestedDeparture = normalizeDiscoveryTripDate(params.get('departure_date') || params.get('checkin') || params.get('start_date'));
  const requestedReturn = normalizeDiscoveryTripDate(params.get('return_date') || params.get('checkout') || params.get('end_date'));
  if (departure && requestedDeparture) departure.value = requestedDeparture;
  if (returning && requestedReturn) returning.value = requestedReturn;
  const adults = form.querySelector('[data-home-adults]');
  const children = form.querySelector('[data-home-children]');
  const rooms = form.querySelector('[data-home-rooms]');
  if (adults && params.has('adults')) adults.value = String(clampDiscoveryNumber(params.get('adults'), 1, 6, 2));
  if (children && params.has('children')) children.value = String(clampDiscoveryNumber(params.get('children'), 0, 4, 0));
  if (rooms && params.has('rooms')) rooms.value = String(clampDiscoveryNumber(params.get('rooms'), 1, 3, 1));
  syncHomeSearchTripContext(form);
  refreshHomeDestinationPlanLinks();
  updateHomeSearchCriteriaState(form, { announce: false, animate: false });

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => syncHomeSearchProduct(form, tab));
    tab.addEventListener('keydown', event => {
      let nextIndex = -1;
      if (event.key === 'Home') nextIndex = 0;
      if (event.key === 'End') nextIndex = tabs.length - 1;
      if (event.key === 'ArrowLeft') nextIndex = (index + 1) % tabs.length;
      if (event.key === 'ArrowRight') nextIndex = (index - 1 + tabs.length) % tabs.length;
      if (nextIndex < 0) return;
      event.preventDefault();
      syncHomeSearchProduct(form, tabs[nextIndex], { focus: true });
    });
  });

  const syncCurrentSearch = () => {
    syncHomeSearchTripContext(form);
    refreshHomeDestinationPlanLinks();
    updateHomeSearchCriteriaState(form);
  };
  form.addEventListener('input', syncCurrentSearch);
  form.addEventListener('change', syncCurrentSearch);
  form.addEventListener('submit', event => {
    if (!updateHomeSearchCriteriaState(form, { announce: false })) {
      event.preventDefault();
      const progress = document.querySelector('[data-home-search-progress]');
      setHomeSearchStep(progress, 'handoff', 'failed', 'ההשוואה לא נפתחה', false);
      setTextContentIfChanged(progress?.querySelector('[data-home-search-status]'), 'ההשוואה לא נפתחה. תקנו את השדות המסומנים ונסו שוב.');
      form.reportValidity();
      return;
    }
    const destination = form.querySelector('[data-home-destination]');
    const selectedOption = destination?.selectedOptions?.[0];
    const anywhere = !selectedOption || selectedOption.dataset.code === 'anywhere';
    setHomeSearchRoutingState(form, anywhere
      ? 'הבחירה התקבלה. פותחים את מפת האפשרויות בלי לבחור יעד במקומכם.'
      : 'הבחירה התקבלה. פותחים את עמוד ההשוואה עם הפרטים שבחרתם; שם תוכלו להתחיל בדיקה מול ספק.');
    event.preventDefault();
    scheduleHomeSearchNavigation(homeSearchNavigationUrl(form, anywhere));
  });

  window.addEventListener('pageshow', () => {
    syncHomeSearchTripContext(form);
    refreshHomeDestinationPlanLinks();
    const ready = updateHomeSearchCriteriaState(form, { announce: false, animate: false });
    form.dataset.state = ready ? 'ready' : 'invalid';
    form.setAttribute('aria-busy', 'false');
    const submit = form.querySelector('[data-home-search-submit]');
    if (submit) submit.disabled = false;
    const progress = document.querySelector('[data-home-search-progress]');
    if (progress) progress.dataset.state = form.dataset.state;
    setHomeSearchStep(progress, 'handoff', 'waiting', 'תתחיל לאחר לחיצה', false);
    setTextContentIfChanged(progress?.querySelector('[data-home-search-status]'), ready
      ? 'הפרטים מוכנים. המחירים והזמינות ייבדקו רק בעמוד ההשוואה.'
      : 'יש להשלים את הפרטים המסומנים לפני פתיחת ההשוואה.');
  });
}

function initControls() {
  document.querySelectorAll('[data-filter-kind] button').forEach(button => button.addEventListener('click', () => {
    button.closest('[data-filter-kind]').querySelectorAll('button').forEach(item => {
      const selected = item === button;
      item.classList.toggle('is-active', selected);
      item.setAttribute('aria-pressed', String(selected));
    });
  }));
  document.querySelectorAll('.filter-chips:not([data-filter-kind]) button, .experience-chips button').forEach(button => button.addEventListener('click', () => button.classList.toggle('is-active')));
  document.querySelectorAll('.toggle').forEach(toggle => toggle.addEventListener('click', () => {
    toggle.classList.toggle('is-active');
    toggle.setAttribute('aria-pressed', String(toggle.classList.contains('is-active')));
  }));
}

const agentActiveRunStorageKey = 'traVelAgent.activeRunId';
const agentRuntime = {
  runId: '',
  status: '',
  requestId: '',
  requestRevision: 0,
  lastSequence: 0,
  events: [],
  eventIds: new Set(),
  pollTimer: 0,
  pollFailures: 0,
  pollInFlight: false,
  pollController: null,
  pollGeneration: 0,
  pollRunToken: '',
  quoteCase: null,
  quoteCaseEvents: [],
  quoteCaseEventIds: new Set(),
  quoteCaseLastSequence: 0,
  quoteCaseDiscardedSequence: 0,
  quoteCasePollTimer: 0,
  quoteCasePollFailures: 0,
  quoteCasePollInFlight: false,
  quoteCasePollController: null,
  quoteCasePollGeneration: 0,
  quoteCasePollCaseToken: '',
  quoteCaseLoading: false,
  quoteCaseContact: null,
  quoteCaseContactKey: '',
  quoteCaseContactSaved: false,
  quoteCaseContactDeclined: false
};

function readAgentSessionValue(key) {
  try {
    return window.sessionStorage.getItem(key) || '';
  } catch (error) {
    return '';
  }
}

function clearAgentRunSession() {
  try {
    window.sessionStorage.removeItem(agentActiveRunStorageKey);
  } catch (error) {
    // A private run can still render its initial response without browser persistence.
  }
}

function storeAgentRunSession(runId) {
  if (!runId) return false;
  try {
    window.sessionStorage.setItem(agentActiveRunStorageKey, runId);
    return window.sessionStorage.getItem(agentActiveRunStorageKey) === runId;
  } catch (error) {
    return false;
  }
}

function isCurrentAgentPoll(runId, generation, controller) {
  return agentRuntime.runId === runId
    && agentRuntime.pollGeneration === generation
    && agentRuntime.pollRunToken === runId
    && agentRuntime.pollController === controller;
}

function invalidateAgentPoll() {
  if (agentRuntime.pollTimer) window.clearTimeout(agentRuntime.pollTimer);
  agentRuntime.pollTimer = 0;
  agentRuntime.pollController?.abort();
  agentRuntime.pollController = null;
  agentRuntime.pollGeneration += 1;
  agentRuntime.pollRunToken = '';
  agentRuntime.pollInFlight = false;
}

function isCurrentQuoteCasePoll(caseId, generation, controller) {
  return agentRuntime.quoteCase?.case_id === caseId
    && agentRuntime.quoteCasePollGeneration === generation
    && agentRuntime.quoteCasePollCaseToken === caseId
    && agentRuntime.quoteCasePollController === controller;
}

function invalidateQuoteCasePoll() {
  if (agentRuntime.quoteCasePollTimer) window.clearTimeout(agentRuntime.quoteCasePollTimer);
  agentRuntime.quoteCasePollTimer = 0;
  agentRuntime.quoteCasePollController?.abort();
  agentRuntime.quoteCasePollController = null;
  agentRuntime.quoteCasePollGeneration += 1;
  agentRuntime.quoteCasePollCaseToken = '';
  agentRuntime.quoteCasePollInFlight = false;
}

function resetAgentRuntime(runId = '') {
  invalidateAgentPoll();
  invalidateQuoteCasePoll();
  agentRuntime.runId = runId;
  agentRuntime.status = '';
  agentRuntime.requestId = '';
  agentRuntime.requestRevision = 0;
  agentRuntime.lastSequence = 0;
  agentRuntime.events = [];
  agentRuntime.eventIds.clear();
  agentRuntime.pollTimer = 0;
  agentRuntime.pollFailures = 0;
  agentRuntime.pollInFlight = false;
  agentRuntime.pollController = null;
  agentRuntime.pollRunToken = '';
  agentRuntime.quoteCase = null;
  agentRuntime.quoteCaseEvents = [];
  agentRuntime.quoteCaseEventIds.clear();
  agentRuntime.quoteCaseLastSequence = 0;
  agentRuntime.quoteCaseDiscardedSequence = 0;
  agentRuntime.quoteCasePollTimer = 0;
  agentRuntime.quoteCasePollFailures = 0;
  agentRuntime.quoteCasePollInFlight = false;
  agentRuntime.quoteCasePollController = null;
  agentRuntime.quoteCasePollCaseToken = '';
  agentRuntime.quoteCaseLoading = false;
  agentRuntime.quoteCaseContact = null;
  agentRuntime.quoteCaseContactKey = '';
  agentRuntime.quoteCaseContactSaved = false;
  agentRuntime.quoteCaseContactDeclined = false;
}

function agentRestBase() {
  return String(window.traVelV2?.agentRestUrl || '').replace(/\/+$/, '');
}

async function agentApiRequest(path, options = {}) {
  const base = agentRestBase();
  if (!base) {
    const error = new Error('שירות המתכנן הפרטי אינו זמין כרגע.');
    error.code = 'agent_unavailable';
    error.status = 0;
    throw error;
  }

  const headers = {
    Accept: 'application/json',
    'Cache-Control': 'no-store',
    ...(options.body ? {'Content-Type': 'application/json'} : {})
  };
  if (window.traVelV2?.nonce) headers['X-WP-Nonce'] = window.traVelV2.nonce;

  const response = await fetch(`${base}${path}`, {
    method: options.method || 'GET',
    body: options.body || undefined,
    credentials: 'same-origin',
    headers,
    ...(options.signal ? {signal: options.signal} : {})
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(typeof payload.message === 'string' ? payload.message : `Agent request failed: ${response.status}`);
    error.code = payload.code || 'agent_request_failed';
    error.status = response.status;
    throw error;
  }
  return payload;
}

function createAgentClientRequestId() {
  if (window.crypto?.randomUUID) return window.crypto.randomUUID();
  const values = new Uint32Array(4);
  if (window.crypto?.getRandomValues) window.crypto.getRandomValues(values);
  else values.forEach((value, index) => { values[index] = Math.floor(Math.random() * 0xffffffff); });
  return `web-${Date.now().toString(36)}-${Array.from(values, value => value.toString(36)).join('')}`;
}

function detectAgentLocale(prompt) {
  const hasHebrew = /[\u0590-\u05ff]/.test(prompt);
  const hasLatin = /[A-Za-z]/.test(prompt);
  if (hasHebrew && hasLatin) return 'mixed';
  if (hasLatin) return 'en-US';
  return 'he-IL';
}

function agentPlanningContextFromLocation() {
  const params = new URLSearchParams(window.location.search);
  const hasCoordinateParams = params.has('latitude') && params.has('longitude');
  const rawLatitude = Number(params.get('latitude'));
  const rawLongitude = Number(params.get('longitude'));
  const hasCoordinates = hasCoordinateParams && Number.isFinite(rawLatitude) && rawLatitude >= -90 && rawLatitude <= 90
    && Number.isFinite(rawLongitude) && rawLongitude >= -180 && rawLongitude <= 180;
  const destination = String(params.get('destination') || '').toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 60);
  const selectionCandidate = String(params.get('selection_id') || '');
  const requestedKind = String(params.get('selection_kind') || '');
  const inferredKind = destination ? 'destination' : (hasCoordinates ? 'map_point' : 'free_text');
  const kind = requestedKind === 'destination' && destination
    ? 'destination'
    : (requestedKind === 'map_point' && hasCoordinates ? 'map_point' : inferredKind);
  const selectionId = /^[A-Za-z0-9_-]{8,80}$/.test(selectionCandidate)
    ? selectionCandidate
    : (kind === 'free_text' ? null : createPlanningSelectionId(kind));
  const intentCandidate = String(params.get('intent') || 'smart');
  const intent = destinationPlanIntents[intentCandidate] ? intentCandidate : 'smart';
  const scope = String(params.get('scope') || '')
    .split(',')
    .map(value => value.trim())
    .filter((value, index, values) => agentJourneyScopeKeys.has(value) && values.indexOf(value) === index);
  return {
    kind,
    selection_id: selectionId,
    latitude: hasCoordinates ? rawLatitude : null,
    longitude: hasCoordinates ? rawLongitude : null,
    destination: destination || null,
    intent,
    scope
  };
}

function agentStatusLabel(status) {
  const labels = {
    created: 'התוכנית הפרטית נפתחה',
    provider_error: 'לא הצלחנו לארגן את הבקשה',
    needs_clarification: 'נדרש פרט נוסף לפני שממשיכים',
    request_ready: 'פרטי החופשה מוכנים לבדיקה',
    searching: 'ממתינים לאישור מפורט על בדיקת האפשרויות',
    proposal_ready: 'ממתינים לפרטי המקור והתנאים',
    approval_required: 'נדרש אישור מפורש לפני פעולה',
    completed: 'התכנון סומן כהושלם',
    failed: 'לא הצלחנו להשלים את התכנון',
    cancelled: 'התוכנית בוטלה'
  };
  return labels[status] || 'התוכנית עודכנה';
}

function agentWorkbenchRoot(root) {
  return root.closest('.ai-planner-column') || root;
}

function setAgentWorkbenchStatus(root, message, state = '') {
  const status = agentWorkbenchRoot(root).querySelector('[data-agent-run-state]');
  if (!status) return;
  setTextContentIfChanged(status, message);
  if (status.dataset.state !== state) status.dataset.state = state;
}

function setAgentWorkbenchError(root, message = '', options = {}) {
  const error = agentWorkbenchRoot(root).querySelector('[data-agent-error]');
  if (!error) return;
  error.replaceChildren();
  if (message) {
    const copy = document.createElement('span');
    copy.textContent = message;
    error.append(copy);
  }
  if (message && options.reconnect === true) {
    const reconnect = document.createElement('button');
    reconnect.type = 'button';
    reconnect.dataset.agentReconnect = '';
    reconnect.textContent = 'נסו להתחבר עכשיו';
    reconnect.addEventListener('click', () => reconnectAgentPolling(root));
    error.append(reconnect);
    error.dataset.hasAction = 'true';
  } else {
    delete error.dataset.hasAction;
  }
  error.hidden = !message;
}

function resetAgentWorkbench(root) {
  const view = agentWorkbenchRoot(root);
  const workbench = view.querySelector('[data-agent-workbench]');
  const log = view.querySelector('[data-agent-event-log]');
  const empty = view.querySelector('[data-agent-event-empty]');
  const tripRequest = view.querySelector('[data-agent-trip-request]');
  const facts = view.querySelector('[data-agent-request-facts]');
  const clarifications = view.querySelector('[data-agent-clarifications]');
  const questions = view.querySelector('[data-agent-question-list]');
  const assumptions = view.querySelector('[data-agent-assumptions]');
  const assumptionList = view.querySelector('[data-agent-assumption-list]');
  const supplier = view.querySelector('[data-agent-supplier-state]');
  const quoteCase = view.querySelector('[data-agent-quote-case]');
  const quoteCaseCreate = view.querySelector('[data-quote-case-create]');
  const quoteCaseActive = view.querySelector('[data-quote-case-active]');
  const quoteCaseEvents = view.querySelector('[data-quote-case-events]');
  const quoteCaseEventEmpty = view.querySelector('[data-quote-case-event-empty]');
  const quoteCaseConsent = view.querySelector('[data-quote-case-consent]');
  const quoteCaseCreateButton = view.querySelector('[data-quote-case-create-button]');
  const quoteCaseCreateStatus = view.querySelector('[data-quote-case-create-status]');
  const revisionComposer = view.querySelector('[data-agent-revision-composer]');
  const revisionForm = view.querySelector('[data-agent-revision-form]');
  const revisionStatus = view.querySelector('[data-agent-revision-status]');
  if (workbench) workbench.hidden = false;
  log?.replaceChildren();
  facts?.replaceChildren();
  questions?.replaceChildren();
  assumptionList?.replaceChildren();
  if (empty) {
    empty.hidden = false;
    empty.textContent = 'עדכונים מאומתים לתוכנית יופיעו כאן לאחר שהבקשה תיקלט.';
  }
  if (tripRequest) tripRequest.hidden = true;
  if (clarifications) clarifications.hidden = true;
  if (assumptions) assumptions.hidden = true;
  if (revisionComposer) revisionComposer.hidden = true;
  if (revisionForm) {
    revisionForm.reset();
    revisionForm.dataset.state = 'idle';
    revisionForm.setAttribute('aria-busy', 'false');
  }
  if (revisionStatus) {
    revisionStatus.textContent = 'העדכון נשאר בתוכנית הפרטית הזאת. הטקסט החופשי אינו נשמר.';
    revisionStatus.dataset.state = 'idle';
  }
  if (supplier) {
    supplier.hidden = true;
    supplier.textContent = '';
    delete supplier.dataset.state;
  }
  if (quoteCase) {
    quoteCase.hidden = true;
    delete quoteCase.dataset.status;
  }
  if (quoteCaseCreate) quoteCaseCreate.hidden = true;
  if (quoteCaseActive) quoteCaseActive.hidden = true;
  quoteCaseEvents?.replaceChildren();
  if (quoteCaseEventEmpty) quoteCaseEventEmpty.hidden = false;
  if (quoteCaseConsent) quoteCaseConsent.checked = false;
  if (quoteCaseCreateButton) {
    quoteCaseCreateButton.disabled = true;
    delete quoteCaseCreateButton.dataset.idempotencyKey;
  }
  if (quoteCaseCreateStatus) quoteCaseCreateStatus.textContent = 'לא יבוצעו חיפוש ספקים, חיוב או הזמנה בשלב הזה.';
  resetAgentJourney(root);
  setAgentWorkbenchError(root);
}

function appendAgentFact(list, label, value) {
  if (!list || !value) return;
  const row = document.createElement('div');
  const term = document.createElement('dt');
  const description = document.createElement('dd');
  term.textContent = label;
  description.textContent = value;
  row.append(term, description);
  list.append(row);
}

function formatAgentBudget(budget) {
  if (!budget || budget.amount === null || budget.amount === undefined || budget.amount === '' || !Number.isFinite(Number(budget.amount))) return '';
  const amount = Number(budget.amount);
  if (budget.currency && budget.currency !== 'UNKNOWN') {
    try {
      return new Intl.NumberFormat('he-IL', {style: 'currency', currency: budget.currency, maximumFractionDigits: 0}).format(amount);
    } catch (error) {
      return `${amount} ${budget.currency}`;
    }
  }
  return String(amount);
}

function formatAgentTravelers(travelers) {
  if (!travelers || typeof travelers !== 'object') return '';
  const values = [];
  if (Number.isInteger(travelers.adults)) values.push(`${travelers.adults} מבוגרים`);
  if (Number.isInteger(travelers.children) && travelers.children > 0) values.push(`${travelers.children} ילדים`);
  if (Number.isInteger(travelers.rooms)) values.push(`${travelers.rooms} חדרים`);
  return values.join(' · ');
}

function renderAgentTripRequest(root, request) {
  const view = agentWorkbenchRoot(root);
  const card = view.querySelector('[data-agent-trip-request]');
  const summary = view.querySelector('[data-agent-request-summary]');
  const facts = view.querySelector('[data-agent-request-facts]');
  const assumptions = view.querySelector('[data-agent-assumptions]');
  const assumptionList = view.querySelector('[data-agent-assumption-list]');
  const clarifications = view.querySelector('[data-agent-clarifications]');
  const questionList = view.querySelector('[data-agent-question-list]');
  const revisionComposer = view.querySelector('[data-agent-revision-composer]');
  const revisionHelp = view.querySelector('[data-agent-revision-help]');
  const revisionBadge = view.querySelector('[data-agent-revision-badge]');
  if (!card || !summary || !facts || !request || typeof request !== 'object') {
    if (card) card.hidden = true;
    if (assumptions) assumptions.hidden = true;
    if (clarifications) clarifications.hidden = true;
    if (revisionComposer) revisionComposer.hidden = true;
    return;
  }

  facts.replaceChildren();
  summary.textContent = request.summary || 'פרטי החופשה אורגנו לבקשה מסודרת.';
  const destinations = Array.isArray(request.destinations) ? request.destinations.filter(Boolean) : [];
  const destinationLabel = request.destination_mode === 'anywhere'
    ? 'פתוחים לכל יעד שמתאים לאילוצים'
    : destinations.join(', ');
  appendAgentFact(facts, 'יעד', destinationLabel);
  appendAgentFact(facts, 'מועד', request.date_text || (request.date_flexibility === 'flexible' ? 'תאריכים גמישים' : 'טרם נקבע'));
  appendAgentFact(facts, 'נוסעים', formatAgentTravelers(request.travelers));
  appendAgentFact(facts, 'תקציב', formatAgentBudget(request.budget));
  appendAgentFact(facts, 'אווירה', Array.isArray(request.vibes) ? request.vibes.join(', ') : '');
  appendAgentFact(facts, 'אילוצים', Array.isArray(request.hard_constraints) ? request.hard_constraints.join(', ') : '');
  appendAgentFact(
    facts,
    'מקור הבקשה',
    request.source?.input_kind === 'voice'
      ? `קול · ${request.source.transcript_confirmed ? 'התמלול אושר' : 'התמלול לא אושר'}`
      : 'הקלדה'
  );
  if (request.planning_context?.kind === 'map_point'
    && Number.isFinite(Number(request.planning_context.latitude))
    && Number.isFinite(Number(request.planning_context.longitude))) {
    appendAgentFact(facts, 'נקודת המפה', `${Number(request.planning_context.latitude).toFixed(4)}°, ${Number(request.planning_context.longitude).toFixed(4)}°`);
  } else if (request.planning_context?.destination) {
    appendAgentFact(facts, 'בחירה מהמפה', request.planning_context.destination);
  }
  card.hidden = false;

  const assumptionValues = Array.isArray(request.assumptions) ? request.assumptions.filter(Boolean) : [];
  assumptionList?.replaceChildren(...assumptionValues.map(value => {
    const item = document.createElement('li');
    item.textContent = value;
    return item;
  }));
  if (assumptions) assumptions.hidden = assumptionValues.length === 0;

  const blockerIds = new Set(Array.isArray(request.readiness?.blockers) ? request.readiness.blockers : []);
  const questions = Array.isArray(request.material_questions)
    ? request.material_questions.filter(question => question && question.status === 'open')
    : [];
  questionList?.replaceChildren(...questions.map(question => {
    const item = document.createElement('article');
    const head = document.createElement('div');
    const title = document.createElement('strong');
    title.textContent = question.question || 'נדרשת הבהרה';
    head.append(title);
    if (question.blocking || blockerIds.has(question.id)) {
      const badge = document.createElement('span');
      badge.textContent = 'נדרש לפני חיפוש';
      head.append(badge);
    }
    item.append(head);
    if (question.reason) {
      const reason = document.createElement('p');
      reason.textContent = question.reason;
      item.append(reason);
    }
    return item;
  }));
  if (clarifications) clarifications.hidden = questions.length === 0;
  if (revisionHelp) {
    revisionHelp.textContent = questions.length > 0
      ? 'ענו על השאלות הפתוחות במשפט אחד. שאר הפרטים יישמרו ונבדוק מחדש מה עדיין חסר.'
      : 'אפשר לשנות יעד, תקציב, תאריכים, נוסעים או העדפות בלי לפתוח תוכנית חדשה.';
  }
  if (revisionBadge) revisionBadge.textContent = `עדכון ${Math.max(1, Number(request.revision) || 1)}`;
  if (revisionComposer) revisionComposer.hidden = false;
}

function agentEventPhaseLabel(phase) {
  const labels = {
    intake: 'קליטת בקשה',
    understanding: 'פירוש הבקשה',
    clarification: 'הבהרה',
    supplier_search: 'חיפוש ספקים',
    proposal: 'הצעה',
    approval: 'אישור',
    execution: 'ביצוע',
    recovery: 'התאוששות'
  };
  return labels[phase] || 'עדכון תוכנית';
}

const agentJourneyStepOrder = ['intake', 'understanding', 'readiness', 'supplier_search', 'proposal', 'approval', 'execution'];
const agentJourneyStepClasses = ['is-pending', 'is-current', 'is-complete', 'is-waiting', 'is-failed', 'is-cancelled'];
const agentJourneyScopeKeys = new Set(['flights', 'accommodation', 'transfers', 'activities', 'dining', 'insurance', 'connectivity', 'equipment']);
const agentJourneyProtectedSteps = new Set(['supplier_search', 'proposal', 'approval', 'execution']);

function agentJourneyStepIsVisible(step) {
  if (!agentJourneyProtectedSteps.has(step)) return true;
  const event = latestAgentEventForPhase(step);
  if (!event) return false;
  if (step === 'supplier_search' && event.status === 'waiting') {
    return event.data?.provider_connected === true;
  }
  return ['running', 'completed', 'failed', 'cancelled'].includes(event.status);
}

function latestAgentEventForPhase(phase) {
  const events = agentRuntime.events
    .filter(event => event?.visible !== false && event?.phase === phase)
    .sort((a, b) => Number(a?.sequence || 0) - Number(b?.sequence || 0));
  return events.length ? events[events.length - 1] : null;
}

function agentJourneyEventState(event) {
  const states = {running: 'current', completed: 'complete', waiting: 'waiting', failed: 'failed', cancelled: 'cancelled'};
  return states[event?.status] || 'pending';
}

function agentJourneyStateLabel(step, state) {
  const labels = {
    intake: {pending: 'ממתין לשליחה', current: 'ממתין לאישור קבלה', complete: 'התקבלה בפועל', waiting: 'ממתין', failed: 'לא התקבלה', cancelled: 'בוטלה'},
    understanding: {pending: 'טרם נבדק', current: 'מפרשים עכשיו', complete: 'נוצרה בקשה מובנית', waiting: 'ממתין לפרט', failed: 'הפירוש נכשל', cancelled: 'בוטל'},
    readiness: {pending: 'ממתין לפרטים', current: 'בודקים אילוצים', complete: 'פרטי הבקשה מסודרים', waiting: 'נדרשת תשובה קצרה', failed: 'הבדיקה נכשלה', cancelled: 'בוטל'},
    supplier_search: {pending: 'עדיין לא נבדק מקור מחיר', current: 'בדיקת מקור המחיר התחילה', complete: 'התקבלה תשובה ממקור המחיר', waiting: 'ממתינים למקור מחיר', failed: 'בדיקת המקור לא הושלמה', cancelled: 'בוטלה'},
    proposal: {pending: 'עדיין לא התקבלו אפשרויות', current: 'מארגנים את האפשרויות שהתקבלו', complete: 'התקבלו אפשרויות עם מקור ומועד בדיקה', waiting: 'ממתינים לתוצאות', failed: 'לא נוצרה הצעה', cancelled: 'בוטלה'},
    approval: {pending: 'עדיין לא נדרש', current: 'ממתין לאישור שלכם', complete: 'האישור תועד', waiting: 'ממתין לאישור שלכם', failed: 'האישור לא הושלם', cancelled: 'בוטל'},
    execution: {pending: 'לא אושרה פעולה', current: 'פעולה מתועדת מתקדמת', complete: 'הפעולה המתועדת הושלמה', waiting: 'ממתין', failed: 'הפעולה לא הושלמה', cancelled: 'בוטלה'}
  };
  return labels[step]?.[state] || 'טרם התחיל';
}

function computeAgentJourney(run) {
  const status = String(run?.status || 'created');
  const request = run?.trip_request && typeof run.trip_request === 'object' ? run.trip_request : null;
  const steps = Object.fromEntries(agentJourneyStepOrder.map(step => [step, 'pending']));
  if (run?.run_id) steps.intake = 'complete';
  if (request && Object.keys(request).length) steps.understanding = 'complete';
  else if (status === 'created') steps.understanding = 'current';

  if (status === 'provider_error') steps.understanding = 'failed';
  if (status === 'needs_clarification') steps.readiness = 'waiting';
  if (['request_ready', 'searching', 'proposal_ready', 'approval_required', 'completed'].includes(status)) steps.readiness = 'complete';

  const phaseToStep = {
    intake: 'intake',
    understanding: 'understanding',
    clarification: 'readiness',
    supplier_search: 'supplier_search',
    proposal: 'proposal',
    approval: 'approval',
    execution: 'execution'
  };
  Object.entries(phaseToStep).forEach(([phase, step]) => {
    const event = latestAgentEventForPhase(phase);
    if (!event) return;
    const eventState = agentJourneyEventState(event);
    if (eventState === 'complete') steps[step] = 'complete';
    else if (!['complete'].includes(steps[step]) || ['failed', 'cancelled'].includes(eventState)) steps[step] = eventState;
  });

  const failedEvents = [...agentRuntime.events]
    .filter(event => event?.visible !== false && event?.status === 'failed')
    .sort((a, b) => Number(a?.sequence || 0) - Number(b?.sequence || 0));
  const failedEvent = failedEvents.length ? failedEvents[failedEvents.length - 1] : null;
  if (failedEvent && phaseToStep[failedEvent.phase]) steps[phaseToStep[failedEvent.phase]] = 'failed';
  if (status === 'failed' && !failedEvent) {
    const currentStep = agentJourneyStepOrder.find(step => steps[step] === 'current') || (request ? 'readiness' : 'understanding');
    steps[currentStep] = 'failed';
  }
  if (status === 'cancelled') {
    const currentStep = agentJourneyStepOrder.find(step => ['current', 'waiting'].includes(steps[step]));
    const nextStep = agentJourneyStepOrder.find(step => steps[step] === 'pending');
    const cancelledStep = currentStep || nextStep;
    if (cancelledStep) steps[cancelledStep] = 'cancelled';
  }
  return steps;
}

function agentJourneyNextAction(run) {
  const labels = {
    created: 'הבקשה התקבלה וממתינה לארגון הפרטים.',
    provider_error: 'הפירוש לא הושלם. אפשר לנסות שוב בלי להציג חיפוש או מחיר.',
    needs_clarification: 'ענו במשפט אחד על השאלות הפתוחות כדי להכין בקשה מסודרת.',
    request_ready: 'הבקשה מוכנה. אפשר לשלוח אותה לבדיקה אישית. עדיין לא נשלחה בקשה לספק.',
    searching: 'התקדמות תופיע רק לאחר שמקור המחיר יחזיר עדכון מתועד.',
    proposal_ready: 'בדקו מקור, זמן בדיקה, מחיר, תנאים ופערים לפני אישור.',
    approval_required: 'בדקו את סיכום הפעולה ואשרו במפורש רק אם הכול מתאים.',
    completed: 'התכנון סומן כהושלם. אין בכך אישור להזמנה או לתשלום. בדקו אסמכתאות ותנאים באזור הנסיעה.',
    failed: 'הפעולה לא הושלמה. המצב האחרון שאושר נשאר מוצג.',
    cancelled: 'התוכנית בוטלה. אפשר לעדכן את הבקשה או להתחיל תוכנית חדשה.'
  };
  return labels[String(run?.status || '')] || 'ממתינים לעדכון מאומת לפני שמתקדמים.';
}

function latestAgentScopeEvent(scopeKey) {
  const matches = agentRuntime.events.filter(event => {
    if (event?.visible === false || event?.phase !== 'supplier_search') return false;
    const data = event?.data && typeof event.data === 'object' ? event.data : {};
    const eventScopes = Array.isArray(data.scopes) ? data.scopes : [];
    return data.scope === scopeKey || data.domain === scopeKey || eventScopes.includes(scopeKey);
  }).sort((a, b) => Number(a?.sequence || 0) - Number(b?.sequence || 0));
  return matches.length ? matches[matches.length - 1] : null;
}

function renderAgentJourneyScopes(root, request, run = null) {
  const view = agentWorkbenchRoot(root);
  const requestedContextScope = Array.isArray(request?.planning_context?.scope)
    ? request.planning_context.scope.filter(key => agentJourneyScopeKeys.has(key))
    : [];
  const interpretedScope = Array.isArray(request?.search_scope)
    ? request.search_scope.filter(key => agentJourneyScopeKeys.has(key))
    : [];
  const scope = new Set(requestedContextScope.length ? requestedContextScope : interpretedScope);
  const items = [...view.querySelectorAll('[data-agent-scope]')];
  items.forEach(item => {
    const scopeKey = item.dataset.agentScope;
    const requested = scope.has(scopeKey);
    item.classList.toggle('is-requested', requested);
    item.classList.toggle('is-not-requested', !requested);
    item.classList.remove('is-running', 'is-complete', 'is-waiting', 'is-failed', 'is-cancelled');
    const state = item.querySelector('[data-agent-scope-state]');
    if (!requested) {
      setTextContentIfChanged(state, 'לא נבחר בבקשה');
      return;
    }
    const event = latestAgentScopeEvent(scopeKey);
    const eventState = event ? agentJourneyEventState(event) : '';
    const eventLabels = {
      current: 'בדיקת מקור המחיר התחילה',
      complete: 'התקבלה תשובה ממקור המחיר',
      waiting: 'ממתין למקור מחיר או לפרט',
      failed: 'הבדיקה לא הושלמה',
      cancelled: 'הבדיקה הופסקה'
    };
    if (eventState && eventState !== 'pending') {
      item.classList.add(`is-${eventState === 'current' ? 'running' : eventState}`);
      setTextContentIfChanged(state, eventLabels[eventState]);
    } else {
      setTextContentIfChanged(state, 'כלול בבקשה · עדיין לא נבדק מול מקור');
    }
  });
  const count = view.querySelector('[data-agent-scope-count]');
  setTextContentIfChanged(count, request
    ? `${scope.size} מתוך ${items.length} תחומים נכללו בבקשה`
    : 'ממתין לפירוש הבקשה');
}

function resetAgentJourney(root) {
  const view = agentWorkbenchRoot(root);
  const board = view.querySelector('[data-agent-journey]');
  if (!board) return;
  if (board.traVelJourneyTimer) window.clearTimeout(board.traVelJourneyTimer);
  board.traVelJourneyTimer = 0;
  board.traVelJourneySignature = '';
  board.traVelJourneyCompleted = 0;
  board.classList.remove('is-advancing');
  board.dataset.state = 'idle';
  board.querySelectorAll('[data-agent-journey-step]').forEach(step => {
	step.hidden = agentJourneyProtectedSteps.has(step.dataset.agentJourneyStep);
    step.classList.remove(...agentJourneyStepClasses);
    step.classList.add('is-pending');
    step.removeAttribute('aria-current');
    const state = step.querySelector('[data-agent-journey-step-state]');
    if (state) state.textContent = agentJourneyStateLabel(step.dataset.agentJourneyStep, 'pending');
  });
  const meter = board.querySelector('[data-agent-journey-meter]');
  meter?.setAttribute('aria-valuenow', '0');
  meter?.setAttribute('aria-valuetext', 'עדיין לא הושלם שלב');
  const count = board.querySelector('[data-agent-journey-count]');
  const fill = board.querySelector('[data-agent-journey-fill]');
  const next = board.querySelector('[data-agent-journey-next]');
  meter?.setAttribute('aria-valuemax', '3');
  if (count) count.textContent = '0/3';
  if (fill) fill.style.setProperty('--agent-journey-progress', '0%');
  if (next) next.textContent = 'שלחו בקשה במילים שלכם כדי להתחיל.';
  renderAgentJourneyScopes(root, null);
}

function setAgentJourneyConnecting(root) {
  const board = agentWorkbenchRoot(root).querySelector('[data-agent-journey]');
  if (!board) return;
  board.removeAttribute('data-transport');
  board.dataset.state = 'connecting';
  const intake = board.querySelector('[data-agent-journey-step="intake"]');
  if (intake) {
    intake.classList.remove(...agentJourneyStepClasses);
    intake.classList.add('is-current');
    intake.setAttribute('aria-current', 'step');
    const state = intake.querySelector('[data-agent-journey-step-state]');
    if (state) state.textContent = 'שולחים באופן פרטי';
  }
  const next = board.querySelector('[data-agent-journey-next]');
  setTextContentIfChanged(next, 'ממתינים לאישור שהבקשה נקלטה. עדיין לא הושלם שלב.');
}

function stopAgentJourneyMotion(board) {
  if (!board) return;
  if (board.traVelJourneyTimer) window.clearTimeout(board.traVelJourneyTimer);
  board.traVelJourneyTimer = 0;
  board.traVelJourneyGeneration = (Number(board.traVelJourneyGeneration) || 0) + 1;
  board.classList.remove('is-advancing');
}

function failAgentJourneyConnection(root) {
  const board = agentWorkbenchRoot(root).querySelector('[data-agent-journey]');
  if (!board) return;
  stopAgentJourneyMotion(board);
  board.dataset.state = 'transport_error';
  board.dataset.transport = 'failed';
  const intake = board.querySelector('[data-agent-journey-step="intake"]');
  if (intake) {
    intake.classList.remove(...agentJourneyStepClasses);
    intake.classList.add('is-failed');
    intake.removeAttribute('aria-current');
    const state = intake.querySelector('[data-agent-journey-step-state]');
    setTextContentIfChanged(state, 'לא התקבל אישור קבלה');
  }
  const next = board.querySelector('[data-agent-journey-next]');
  setTextContentIfChanged(next, 'החיבור לא אושר. לא נסמן התקדמות עד שתתקבל תשובה תקינה.');
}

function pauseAgentJourneyTransport(root, message) {
  const board = agentWorkbenchRoot(root).querySelector('[data-agent-journey]');
  if (!board) return;
  stopAgentJourneyMotion(board);
  board.dataset.transport = 'stale';
  if (board.dataset.state === 'connecting') {
    board.dataset.state = 'transport_stale';
    board.querySelectorAll('[data-agent-journey-step].is-current').forEach(step => {
      step.classList.remove('is-current');
      step.classList.add('is-pending');
      step.removeAttribute('aria-current');
      const state = step.querySelector('[data-agent-journey-step-state]');
      setTextContentIfChanged(state, agentJourneyStateLabel(step.dataset.agentJourneyStep, 'pending'));
    });
  }
  const next = board.querySelector('[data-agent-journey-next]');
  setTextContentIfChanged(next, message || 'העדכון החי נעצר. המצב האחרון שאושר נשאר מוצג ללא התקדמות חדשה.');
}

function renderAgentJourney(root, run) {
  const board = agentWorkbenchRoot(root).querySelector('[data-agent-journey]');
  if (!board) return;
  const steps = computeAgentJourney(run);
  const visibleSteps = agentJourneyStepOrder.filter(agentJourneyStepIsVisible);
  const completed = visibleSteps.filter(step => steps[step] === 'complete').length;
  const status = String(run?.status || 'created');
  const signature = `${status}:${run?.trip_request?.revision || 0}:${agentJourneyStepOrder.map(step => steps[step]).join(',')}`;
  const previousCompleted = Number(board.traVelJourneyCompleted) || 0;
  const previousSignature = board.traVelJourneySignature || '';
  board.removeAttribute('data-transport');
  board.dataset.state = status;
  board.querySelectorAll('[data-agent-journey-step]').forEach(step => {
    const stepName = step.dataset.agentJourneyStep;
    step.hidden = !visibleSteps.includes(stepName);
    const stateName = steps[stepName] || 'pending';
    step.classList.remove(...agentJourneyStepClasses);
    step.classList.add(`is-${stateName}`);
    step.dataset.state = stateName;
    if (['current', 'waiting'].includes(stateName)) step.setAttribute('aria-current', 'step');
    else step.removeAttribute('aria-current');
    const state = step.querySelector('[data-agent-journey-step-state]');
    if (state) state.textContent = agentJourneyStateLabel(stepName, stateName);
  });
  const meter = board.querySelector('[data-agent-journey-meter]');
  const count = board.querySelector('[data-agent-journey-count]');
  const fill = board.querySelector('[data-agent-journey-fill]');
  const next = board.querySelector('[data-agent-journey-next]');
  const visibleCount = Math.max(1, visibleSteps.length);
  const progress = Math.round((completed / visibleCount) * 100);
  meter?.setAttribute('aria-valuemax', String(visibleCount));
  meter?.setAttribute('aria-valuenow', String(completed));
  meter?.setAttribute('aria-valuetext', `${completed} מתוך ${visibleCount} שלבים שהתרחשו הושלמו בפועל`);
  if (count) count.textContent = `${completed}/${visibleCount}`;
  if (fill) fill.style.setProperty('--agent-journey-progress', `${progress}%`);
  setTextContentIfChanged(next, agentJourneyNextAction(run));
  renderAgentJourneyScopes(root, run?.trip_request, run);

  if (board.traVelJourneyTimer) window.clearTimeout(board.traVelJourneyTimer);
  board.classList.remove('is-advancing');
  const confirmedForward = signature !== previousSignature
    && completed > previousCompleted
    && !['provider_error', 'failed', 'cancelled'].includes(status);
  if (confirmedForward && !prefersReducedMotion()) {
    void board.offsetWidth;
    board.classList.add('is-advancing');
    const generation = (Number(board.traVelJourneyGeneration) || 0) + 1;
    board.traVelJourneyGeneration = generation;
    board.traVelJourneyTimer = window.setTimeout(() => {
      if (board.traVelJourneyGeneration !== generation) return;
      board.classList.remove('is-advancing');
      board.traVelJourneyTimer = 0;
    }, 1200);
  }
  board.traVelJourneySignature = signature;
  board.traVelJourneyCompleted = completed;
}

function mergeAndRenderAgentEvents(root, events) {
  if (!Array.isArray(events)) return;
  const view = agentWorkbenchRoot(root);
  const log = view.querySelector('[data-agent-event-log]');
  const empty = view.querySelector('[data-agent-event-empty]');
  if (!log) return;

  [...events].sort((a, b) => Number(a?.sequence || 0) - Number(b?.sequence || 0)).forEach(event => {
    const sequence = Number(event?.sequence || 0);
    if (sequence > agentRuntime.lastSequence) agentRuntime.lastSequence = sequence;
    const eventId = String(event?.event_id || `sequence-${sequence}`);
    if (agentRuntime.eventIds.has(eventId)) return;
    agentRuntime.eventIds.add(eventId);
    agentRuntime.events.push(event);
    if (event?.visible === false || !event?.message) return;

    const item = document.createElement('li');
    item.className = `agent-event is-${String(event.status || 'completed').replace(/[^a-z-]/g, '')}`;
    item.dataset.eventId = eventId;
    item.dataset.eventSequence = String(sequence);
    const meta = document.createElement('div');
    const phase = document.createElement('span');
    const time = document.createElement('time');
    const message = document.createElement('p');
    phase.textContent = agentEventPhaseLabel(event.phase);
    if (event.occurred_at) {
      time.dateTime = event.occurred_at;
      const parsed = new Date(event.occurred_at);
      time.textContent = Number.isNaN(parsed.getTime()) ? '' : parsed.toLocaleTimeString('he-IL', {hour: '2-digit', minute: '2-digit'});
    }
    meta.append(phase, time);
    message.textContent = event.message;
    item.append(meta, message);
    log.append(item);
  });
  const latestSequenceByPhase = new Map();
  agentRuntime.events.forEach(event => {
    if (event?.visible === false || !event?.message || !event?.phase) return;
    latestSequenceByPhase.set(event.phase, Math.max(latestSequenceByPhase.get(event.phase) || 0, Number(event.sequence || 0)));
  });
  agentRuntime.events.forEach(event => {
    if (event?.status !== 'running' || !event?.event_id) return;
    const item = log.querySelector(`[data-event-id="${CSS.escape(String(event.event_id))}"]`);
    if (!item) return;
    const resolved = (latestSequenceByPhase.get(event.phase) || 0) > Number(event.sequence || 0);
    item.classList.toggle('is-resolved', resolved);
  });
  if (empty) empty.hidden = log.childElementCount > 0;
}

function renderAgentSupplierState(root, run) {
  const supplier = agentWorkbenchRoot(root).querySelector('[data-agent-supplier-state]');
  if (!supplier) return;
  const supplierEvents = agentRuntime.events.filter(event => event?.visible !== false && event?.phase === 'supplier_search' && event?.message);
  const latest = supplierEvents[supplierEvents.length - 1];
  if (latest) {
    supplier.hidden = false;
    supplier.dataset.state = latest.status === 'waiting' ? 'not-started' : String(latest.status || 'reported');
    setTextContentIfChanged(supplier, latest.message);
    return;
  }
  if (run?.status === 'needs_clarification') {
    supplier.hidden = false;
    supplier.dataset.state = 'not-started';
    setTextContentIfChanged(supplier, 'בדיקת ספקים לא התחילה. ממתינים לתשובה על הפרטים החסרים.');
    return;
  }
  supplier.hidden = true;
  setTextContentIfChanged(supplier, '');
}

const quoteCaseActiveStatuses = new Set(['queued', 'in_review', 'needs_information', 'ready_for_assistance']);
const quoteCaseTerminalStatuses = new Set(['closed_no_quote', 'cancelled', 'expired']);
const quoteCaseEventDomLimit = 100;

function normalizeQuoteCasePayload(payload) {
  const candidate = payload?.case || payload?.quote_case || payload;
  return candidate && typeof candidate === 'object' && typeof candidate.case_id === 'string' ? candidate : null;
}

function quoteCaseStatusLabel(caseData) {
  const labels = {
    queued: 'הבקשה התקבלה וממתינה לבדיקה',
    in_review: 'הבקשה נמצאת בבדיקת מומחה',
    needs_information: 'נדרש מידע נוסף כדי להמשיך',
    ready_for_assistance: 'הבדיקה מוכנה להמשך עם מומחה',
    closed_no_quote: 'הבקשה נסגרה ללא הצעה',
    cancelled: 'הבקשה בוטלה',
    expired: 'תוקף הבקשה הסתיים'
  };
  return labels[caseData?.status]
    || (typeof caseData?.status_label === 'string' && caseData.status_label.trim() ? caseData.status_label : 'מצב הבקשה עודכן');
}

function quoteCaseNextAction(caseData) {
  if (typeof caseData?.next_action === 'string' && caseData.next_action.trim()) return caseData.next_action;
  const labels = {
    queued: 'אין צורך לעשות דבר כרגע. הבקשה ממתינה לצוות Tra-Vel.',
    in_review: 'הצוות בודק את התוכנית. עדכון חדש יופיע כאן כשהמצב ישתנה.',
    needs_information: 'פתחו את השיחה בוואטסאפ והשלימו את הפרט שביקש המומחה.',
    ready_for_assistance: 'המשיכו לשיחה עם המומחה כדי לבדוק מחיר, זמינות ותנאים.',
    closed_no_quote: 'אפשר לעדכן את התוכנית ולפתוח בדיקה אישית חדשה.',
    cancelled: 'הבקשה אינה פעילה. התוכנית הפרטית נשארה זמינה לעדכון.',
    expired: 'פתחו בדיקה אישית חדשה אם התוכנית עדיין רלוונטית.'
  };
  return labels[caseData?.status]
    || (typeof caseData?.next_action?.label === 'string' && caseData.next_action.label.trim() ? caseData.next_action.label : 'המתינו לעדכון מאומת לפני שתמשיכו.');
}

function quoteCaseSummaryText(caseData) {
  if (typeof caseData?.summary === 'string' && caseData.summary.trim()) return caseData.summary;
  if (!caseData?.summary || typeof caseData.summary !== 'object') return 'הבדיקה האישית קשורה לתוכנית שמופיעה למעלה.';
  const title = typeof caseData.summary.title === 'string' ? caseData.summary.title.trim() : '';
  const destinations = Array.isArray(caseData.summary.destinations) ? caseData.summary.destinations.filter(Boolean).join(', ') : '';
  const origin = typeof caseData.summary.origin === 'string' ? caseData.summary.origin.trim() : '';
  const route = [origin, destinations].filter(Boolean).join(' → ');
  const date = typeof caseData.summary.date_text === 'string' ? caseData.summary.date_text.trim() : '';
  return [title, route, date].filter(Boolean).join(' · ') || 'בדיקה אישית לתוכנית הנסיעה';
}

function formatQuoteCaseTime(value, includeDate = false) {
  if (!value) return '';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '';
  return parsed.toLocaleString('he-IL', includeDate
    ? {day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'}
    : {hour: '2-digit', minute: '2-digit'});
}

function setQuoteCaseError(root, message = '', source = '') {
  const error = agentWorkbenchRoot(root).querySelector('[data-quote-case-error]');
  if (!error) return;
  error.textContent = message;
  error.hidden = !message;
  if (message && source) error.dataset.source = source;
  else delete error.dataset.source;
}

function quoteCaseErrorMessage(error) {
  if (error?.code === 'quote_case_handoff_timeout') return 'לא התקבל אישור בתוך 15 שניות. תוצאת ההעברה אינה ודאית; מזהה הפעולה נשמר כדי שניסיון חוזר לא ייצור פעולה כפולה.';
  if (error?.status === 409) return 'הבקשה השתנתה במקום אחר. המצב העדכני ייטען מחדש לפני פעולה נוספת.';
  if (error?.status === 401 || error?.status === 403) return 'הגישה לבדיקה האישית פגה. פתחו את התוכנית מחדש כדי להמשיך.';
  if (error?.status === 404 || error?.code === 'agent_unavailable') return 'שירות בקשות הסיוע עדיין אינו זמין באתר.';
  if (error?.status === 429) return 'נשלחו יותר מדי פעולות בזמן קצר. המתינו רגע ונסו שוב.';
  return 'הפעולה לא אושרה. המצב הקודם נשאר ללא שינוי.';
}

function quoteCaseProgressState(status) {
  if (status === 'queued') return {current: 1, completed: 1, mode: 'active'};
  if (status === 'in_review') return {current: 2, completed: 2, mode: 'active'};
  if (status === 'needs_information') return {current: 2, completed: 2, mode: 'blocked'};
  if (status === 'ready_for_assistance') return {current: 3, completed: 3, mode: 'active'};
  if (quoteCaseTerminalStatuses.has(status)) return {current: 1, completed: 1, mode: 'terminal'};
  return {current: -1, completed: 0, mode: 'neutral'};
}

function quoteCaseStepState(progress, index) {
  if (progress.mode === 'terminal') {
    if (index < progress.completed) return 'completed';
    if (index === progress.current) return 'terminal';
    return 'pending';
  }
  if (progress.mode === 'blocked' && index === progress.current) return 'blocked';
  if (index < progress.completed) return 'completed';
  if (progress.mode === 'active' && index === progress.current) return 'current';
  return 'pending';
}

function quoteCaseStepStateLabel(state) {
  return {
    completed: 'הושלם ואושר',
    current: 'השלב הנוכחי',
    blocked: 'נדרשת תשובה',
    terminal: 'הבקשה הסתיימה',
    pending: 'טרם התחיל'
  }[state] || 'טרם התחיל';
}

function quoteCaseCanResume(caseData) {
  return caseData?.resume_available === true && validWorkspaceAgentRunId(caseData?.source?.run_id);
}

function isConfirmedQuoteCaseForward(previousCase, nextCase) {
  if (!previousCase?.case_id || previousCase.case_id !== nextCase?.case_id) return false;
  if (Number(nextCase.version) <= Number(previousCase.version)) return false;
  const rank = {queued: 1, in_review: 2, needs_information: 2, ready_for_assistance: 3};
  if (!quoteCaseActiveStatuses.has(nextCase.status) || nextCase.status === 'needs_information') return false;
  return Number(rank[nextCase.status] || 0) > Number(rank[previousCase.status] || 0);
}

function isConfirmedQuoteCaseRecovery(previousCase, nextCase) {
  if (!previousCase?.case_id || previousCase.case_id !== nextCase?.case_id) return false;
  if (Number(nextCase.version) <= Number(previousCase.version)) return false;
  return previousCase.status === 'needs_information'
    && ['in_review', 'ready_for_assistance'].includes(nextCase.status);
}

function renderQuoteCaseProgress(view, status) {
  const progress = quoteCaseProgressState(status);
  view.querySelectorAll('[data-quote-step]').forEach(step => {
    const index = Number(step.dataset.quoteStep);
    const state = quoteCaseStepState(progress, index);
    step.dataset.state = state;
    step.classList.toggle('is-completed', state === 'completed');
    step.classList.toggle('is-current', state === 'current');
    step.classList.toggle('is-blocked', state === 'blocked');
    step.classList.toggle('is-terminal', state === 'terminal');
    if (state === 'current') step.setAttribute('aria-current', 'step');
    else step.removeAttribute('aria-current');
  });
}

function quoteCaseActorLabel(event) {
  return {system: 'Tra-Vel', traveler: 'אתם', operator: 'צוות Tra-Vel'}[event?.actor_type] || 'עדכון';
}

function mergeAndRenderQuoteCaseEvents(root, events) {
  if (!Array.isArray(events)) return 0;
  const view = agentWorkbenchRoot(root);
  const log = view.querySelector('[data-quote-case-events]');
  const empty = view.querySelector('[data-quote-case-event-empty]');
  if (!log) return 0;

  let added = 0;

  [...events].sort((a, b) => Number(a?.sequence || 0) - Number(b?.sequence || 0)).forEach(event => {
    if (!event || event.visibility === 'internal' || !event.message) return;
    const sequence = Number(event.sequence || 0);
    if (sequence > 0 && sequence <= agentRuntime.quoteCaseDiscardedSequence) return;
    const eventId = String(event.event_id || `quote-sequence-${sequence}`);
    if (agentRuntime.quoteCaseEventIds.has(eventId)) return;
    agentRuntime.quoteCaseEventIds.add(eventId);
    agentRuntime.quoteCaseEvents.push(event);
    added += 1;
    if (sequence > agentRuntime.quoteCaseLastSequence) agentRuntime.quoteCaseLastSequence = sequence;

    const item = document.createElement('li');
    item.className = `agent-quote-event is-${String(event.to_status || 'queued').replace(/[^a-z_]/g, '')}`;
    item.dataset.quoteEventId = eventId;
    const meta = document.createElement('div');
    const actor = document.createElement('span');
    const time = document.createElement('time');
    const message = document.createElement('p');
    actor.textContent = quoteCaseActorLabel(event);
    if (event.occurred_at) {
      time.dateTime = event.occurred_at;
      time.textContent = formatQuoteCaseTime(event.occurred_at);
    }
    message.textContent = event.message;
    meta.append(actor, time);
    item.append(meta, message);
    log.append(item);

    while (agentRuntime.quoteCaseEvents.length > quoteCaseEventDomLimit) {
      const discarded = agentRuntime.quoteCaseEvents.shift();
      const discardedId = String(discarded?.event_id || `quote-sequence-${Number(discarded?.sequence || 0)}`);
      const discardedSequence = Number(discarded?.sequence || 0);
      if (discardedSequence > agentRuntime.quoteCaseDiscardedSequence) agentRuntime.quoteCaseDiscardedSequence = discardedSequence;
      agentRuntime.quoteCaseEventIds.delete(discardedId);
      log.firstElementChild?.remove();
    }
  });
  if (empty) empty.hidden = log.childElementCount > 0;
  return added;
}

function renderAgentQuoteCaseAvailability(root) {
  const view = agentWorkbenchRoot(root);
  const panel = view.querySelector('[data-agent-quote-case]');
  const create = view.querySelector('[data-quote-case-create]');
  const active = view.querySelector('[data-quote-case-active]');
  const status = view.querySelector('[data-quote-case-status]');
  if (!panel || agentRuntime.quoteCase) return;
  const available = agentRuntime.status === 'request_ready' && Boolean(agentRuntime.requestId) && agentRuntime.requestRevision > 0;
  panel.hidden = !available;
  if (create) create.hidden = !available;
  if (active) active.hidden = true;
  if (status) status.textContent = available ? 'מוכנה להעברה' : '';
}

function renderAgentQuoteCase(root, caseData) {
  if (!caseData?.case_id) {
    agentRuntime.quoteCase = null;
    renderAgentQuoteCaseAvailability(root);
    return false;
  }
  const currentCase = agentRuntime.quoteCase;
  const currentVersion = Number(currentCase?.version);
  const incomingVersion = Number(caseData.version);
  if (currentCase?.case_id === caseData.case_id
    && Number.isFinite(currentVersion)
    && Number.isFinite(incomingVersion)
    && incomingVersion < currentVersion) return false;
  const view = agentWorkbenchRoot(root);
  const panel = view.querySelector('[data-agent-quote-case]');
  const create = view.querySelector('[data-quote-case-create]');
  const active = view.querySelector('[data-quote-case-active]');
  if (!panel || !active) return false;

  const previousCaseId = agentRuntime.quoteCase?.case_id || '';
  const previousCase = agentRuntime.quoteCase;
  if (previousCaseId && previousCaseId !== caseData.case_id) {
    agentRuntime.quoteCaseEvents = [];
    agentRuntime.quoteCaseEventIds.clear();
    agentRuntime.quoteCaseLastSequence = 0;
    agentRuntime.quoteCaseDiscardedSequence = 0;
    view.querySelector('[data-quote-case-events]')?.replaceChildren();
  }
  agentRuntime.quoteCase = caseData;
  panel.hidden = false;
  panel.dataset.status = String(caseData.status || 'queued');
  if (create) create.hidden = true;
  active.hidden = false;

  const status = view.querySelector('[data-quote-case-status]');
  const reference = view.querySelector('[data-quote-case-reference]');
  const updated = view.querySelector('[data-quote-case-updated]');
  const summary = view.querySelector('[data-quote-case-summary]');
  const nextAction = view.querySelector('[data-quote-case-next-action]');
  const handoff = view.querySelector('[data-quote-case-handoff]');
  const cancel = view.querySelector('[data-quote-case-cancel]');
  if (status) status.textContent = quoteCaseStatusLabel(caseData);
  if (reference) reference.textContent = caseData.reference || caseData.case_id;
  if (updated) {
    updated.dateTime = caseData.updated_at || '';
    updated.textContent = caseData.updated_at ? `עודכן ${formatQuoteCaseTime(caseData.updated_at, true)}` : '';
  }
  if (summary) summary.textContent = quoteCaseSummaryText(caseData);
  if (nextAction) nextAction.textContent = quoteCaseNextAction(caseData);
  renderQuoteCaseProgress(view, caseData.status);
  mergeAndRenderQuoteCaseEvents(root, caseData.events || []);

  const terminal = quoteCaseTerminalStatuses.has(caseData.status);
  if (handoff) {
    handoff.hidden = terminal;
    handoff.disabled = handoff.dataset.state === 'loading';
  }
  if (cancel) {
    cancel.hidden = terminal;
    cancel.disabled = cancel.dataset.state === 'loading';
  }
  if (isConfirmedQuoteCaseForward(previousCase, caseData)) {
    panel.classList.remove('is-advancing');
    window.requestAnimationFrame(() => {
      panel.classList.add('is-advancing');
      window.setTimeout(() => panel.classList.remove('is-advancing'), 720);
    });
  }
  setQuoteCaseError(root);
  scheduleQuoteCasePoll(root);
  return true;
}

async function fetchQuoteCase(caseId, options = {}) {
  const payload = await agentApiRequest(`/quote-cases/${encodeURIComponent(caseId)}`, options);
  return normalizeQuoteCasePayload(payload);
}

async function claimQuoteCaseForAccount(caseData) {
  if (!window.traVelV2?.isLoggedIn || caseData?.ownership !== 'private_browser_owner' || !caseData?.case_id) return caseData;
  const payload = await agentApiRequest(`/quote-cases/${encodeURIComponent(caseData.case_id)}/claim`, {
    method: 'POST',
    body: JSON.stringify({
      expected_version: Number(caseData.version),
      idempotency_key: `quote-claim-${caseData.case_id}-${Number(caseData.version)}`
    })
  });
  return normalizeQuoteCasePayload(payload) || caseData;
}

async function loadQuoteCaseForRun(root) {
  if (!agentRuntime.runId || agentRuntime.quoteCaseLoading) return agentRuntime.quoteCase;
  const runId = agentRuntime.runId;
  agentRuntime.quoteCaseLoading = true;
  try {
    const payload = await agentApiRequest('/quote-cases');
    if (agentRuntime.runId !== runId) return null;
    const cases = Array.isArray(payload?.cases) ? payload.cases : [];
    const match = cases
      .filter(item => item?.source?.run_id === runId)
      .sort((a, b) => String(b.updated_at || '').localeCompare(String(a.updated_at || '')))[0];
    if (!match) {
      agentRuntime.quoteCase = null;
      renderAgentQuoteCaseAvailability(root);
      return null;
    }
    let caseData = match;
    try {
      caseData = await fetchQuoteCase(match.case_id) || match;
    } catch (error) {
      caseData = match;
    }
    if (window.traVelV2?.isLoggedIn && caseData?.ownership === 'private_browser_owner') {
      try {
        caseData = await claimQuoteCaseForAccount(caseData);
      } catch (error) {
        if (error?.status === 409) caseData = await fetchQuoteCase(match.case_id) || caseData;
      }
    }
    if (agentRuntime.runId === runId) renderAgentQuoteCase(root, caseData);
    return caseData;
  } catch (error) {
    if (![404, 401, 403].includes(error?.status)) setQuoteCaseError(root, quoteCaseErrorMessage(error));
    renderAgentQuoteCaseAvailability(root);
    return null;
  } finally {
    agentRuntime.quoteCaseLoading = false;
  }
}

function safeQuoteCaseHandoffUrl(value) {
  try {
    const url = new URL(value);
    return url.protocol === 'https:' && url.hostname.toLowerCase() === 'api.whatsapp.com' ? url.href : '';
  } catch (error) {
    return '';
  }
}

async function requestQuoteCaseHandoff(caseData, button) {
  if (!caseData?.case_id || !Number.isInteger(Number(caseData.version))) throw new Error('Quote case version is unavailable.');
  if (!button.dataset.idempotencyKey) button.dataset.idempotencyKey = createAgentClientRequestId();
  return requestWithDeadline(
    signal => agentApiRequest(`/quote-cases/${encodeURIComponent(caseData.case_id)}/handoffs`, {
      method: 'POST',
      body: JSON.stringify({
        channel: 'whatsapp',
        expected_version: Number(caseData.version),
        idempotency_key: button.dataset.idempotencyKey
      }),
      ...(signal ? {signal} : {})
    }),
    'quote_case_handoff_timeout'
  );
}

async function createAgentQuoteCase(root) {
  const view = agentWorkbenchRoot(root);
  const button = view.querySelector('[data-quote-case-create-button]');
  const consent = view.querySelector('[data-quote-case-consent]');
  const status = view.querySelector('[data-quote-case-create-status]');
  if (!button || !consent?.checked || button.dataset.state === 'loading') return;
  if (agentRuntime.status !== 'request_ready' || !agentRuntime.runId || !agentRuntime.requestId || agentRuntime.requestRevision < 1) {
    setQuoteCaseError(root, 'בקשת הנסיעה עדיין אינה מוכנה להעברה. השלימו את הפרטים החסרים בתוכנית.');
    return;
  }
  if (!button.dataset.idempotencyKey) button.dataset.idempotencyKey = createAgentClientRequestId();
  button.dataset.state = 'loading';
  button.disabled = true;
  if (status) status.textContent = 'פותחים בדיקה אישית ושומרים את פרטי התוכנית שבדקתם...';
  setQuoteCaseError(root);
  try {
    const acquisition = readAcquisition();
    const payload = await agentApiRequest(`/runs/${encodeURIComponent(agentRuntime.runId)}/quote-cases`, {
      method: 'POST',
      body: JSON.stringify({
        expected_request_id: agentRuntime.requestId,
        expected_revision: agentRuntime.requestRevision,
        consent: true,
        consent_version: '2026-07-17',
        idempotency_key: button.dataset.idempotencyKey,
        ...(acquisition ? {acquisition} : {})
      })
    });
    const caseData = normalizeQuoteCasePayload(payload);
    if (!caseData) throw new Error('The server did not return a quote case.');
    delete button.dataset.idempotencyKey;
    renderAgentQuoteCase(root, caseData);
    view.querySelector('[data-quote-case-reference]')?.focus?.({preventScroll: true});
  } catch (error) {
    const message = quoteCaseErrorMessage(error);
    setQuoteCaseError(root, message);
    if (status) status.textContent = message;
  } finally {
    delete button.dataset.state;
    button.disabled = !consent.checked;
  }
}

async function handoffAgentQuoteCase(root) {
  const view = agentWorkbenchRoot(root);
  const button = view.querySelector('[data-quote-case-handoff]');
  const caseData = agentRuntime.quoteCase;
  if (!button || !caseData || button.dataset.state === 'loading') return;
  if (!agentRuntime.quoteCaseContactSaved && !agentRuntime.quoteCaseContactDeclined) {
    openQuoteCaseContactStep(root, view, button);
    return;
  }
  await continueQuoteCaseWhatsappHandoff(root);
}

/**
 * Show the inline callback-contact step before the quote-case WhatsApp
 * continuation. The 0.9.0 API attaches a contact to a quote case only at
 * creation, so a contact consented here is stored through the durable
 * commercial-intent lead store, bound to the public case reference. Skipping
 * continues exactly like the pre-1.23.0 flow.
 */
function openQuoteCaseContactStep(root, view, button) {
  const actionsHost = button.closest?.('.agent-quote-actions') || null;
  const stepParent = actionsHost?.parentElement || view;
  const existing = stepParent.querySelector?.('[data-lead-contact-step]') || view.querySelector?.('[data-lead-contact-step]');
  if (existing) {
    existing.focusHeading?.();
    return;
  }
  const step = buildLeadContactStep({
    onSave: async (contact, controls) => {
      // The popup must open inside this user gesture; the contact write
      // happens before the handoff request reuses the same window.
      const popup = window.open('about:blank', '_blank');
      if (popup) popup.opener = null;
      controls.setBusy(true);
      try {
        await storeQuoteCaseLeadContact(contact);
        agentRuntime.quoteCaseContact = contact;
        agentRuntime.quoteCaseContactSaved = true;
        step.remove?.();
        button.focus?.();
        await continueQuoteCaseWhatsappHandoff(root, popup);
      } catch (error) {
        popup?.close?.();
        console.warn(error);
        controls.showError('לא הצלחנו לשמור את הפרטים כרגע. נסו שוב, או המשיכו בלי להשאיר פרטים.');
      }
    },
    onSkip: async () => {
      agentRuntime.quoteCaseContactDeclined = true;
      step.remove?.();
      button.focus?.();
      await continueQuoteCaseWhatsappHandoff(root);
    }
  });
  if (actionsHost?.insertAdjacentElement) actionsHost.insertAdjacentElement('beforebegin', step);
  else stepParent.append?.(step);
  step.focusHeading?.();
}

/**
 * Store the consented callback contact for an existing quote case. The
 * quote-case aggregate accepts a contact only on creation in the live 0.9.0
 * API, so the durable consent-gated commercial-intent store holds it instead,
 * with the public TV reference as the offer identity. The write is idempotent
 * per saved contact and repeated saves resume the same intent.
 */
async function storeQuoteCaseLeadContact(contact) {
  const endpoint = commercialIntentBaseUrl();
  const caseData = agentRuntime.quoteCase;
  if (!endpoint || !caseData?.case_id) throw new Error('Lead contact storage is unavailable.');
  if (!agentRuntime.quoteCaseContactKey) {
    agentRuntime.quoteCaseContactKey = `quote-contact-${createAgentClientRequestId()}`.slice(0, 100);
  }
  const reference = String(caseData.reference || caseData.case_id).replace(/[^A-Za-z0-9._:-]/g, '').slice(0, 80) || 'quote-case';
  const summary = caseData.summary && typeof caseData.summary === 'object' ? caseData.summary : {};
  const travelers = summary.travelers && typeof summary.travelers === 'object' ? summary.travelers : {};
  const budget = summary.budget && typeof summary.budget === 'object' ? summary.budget : {};
  const acquisition = readAcquisition();
  return commercialIntentMutation(endpoint, {
    idempotency_key: agentRuntime.quoteCaseContactKey,
    vertical: 'package',
    surface: 'quote_case_handoff',
    data_mode: 'demo',
    requested_provider: 'tra-vel-concierge',
    offer_id: reference,
    candidate: {
      id: reference,
      title: boundedCommercialString(quoteCaseSummaryText(caseData), 120),
      subtitle: '',
      commercial_ref: '',
      price_scope: 'personal_quote'
    },
    trip: {
      origin: boundedCommercialString(summary.origin || 'TLV', 80),
      destination: boundedCommercialString(Array.isArray(summary.destinations) ? summary.destinations.filter(Boolean).join(', ') : '', 80),
      depart_date: '',
      return_date: '',
      adults: boundedCommercialInteger(travelers.adults, 1, 0, 20),
      children: boundedCommercialInteger(travelers.children, 0, 0, 20),
      infants: 0,
      travelers: Math.max(1, boundedCommercialInteger(travelers.adults, 1, 0, 20) + boundedCommercialInteger(travelers.children, 0, 0, 20)),
      rooms: boundedCommercialInteger(travelers.rooms, 1, 1, 10),
      budget: boundedCommercialInteger(budget.amount, 0, 0, 1000000),
      currency: ['ILS', 'USD', 'EUR', 'GBP'].includes(budget.currency) ? budget.currency : 'ILS',
      return_path: boundedCommercialString(`${window.location.pathname}${window.location.search}`, 200)
    },
    ...(acquisition ? {acquisition} : {}),
    contact
  }, 'commercial_intent_create_timeout');
}

async function continueQuoteCaseWhatsappHandoff(root, existingPopup = null) {
  const view = agentWorkbenchRoot(root);
  const button = view.querySelector('[data-quote-case-handoff]');
  const status = view.querySelector('[data-quote-case-action-status]');
  const caseData = agentRuntime.quoteCase;
  if (!button || !caseData || button.dataset.state === 'loading') {
    existingPopup?.close?.();
    return;
  }
  const popup = existingPopup || window.open('about:blank', '_blank');
  if (popup) popup.opener = null;
  button.dataset.state = 'loading';
  button.disabled = true;
  if (status) status.dataset.state = 'running';
  if (status) status.textContent = 'מכינים קישור מאובטח עם מספר הבקשה...';
  try {
    const payload = await requestQuoteCaseHandoff(caseData, button);
    const updatedCase = normalizeQuoteCasePayload(payload);
    if (payload?.event) mergeAndRenderQuoteCaseEvents(root, [payload.event]);
    if (updatedCase) renderAgentQuoteCase(root, updatedCase);
    const url = safeQuoteCaseHandoffUrl(payload?.handoff_url);
    if (!url) throw new Error('The handoff URL is unavailable.');
    delete button.dataset.idempotencyKey;
    if (status) status.dataset.state = payload?.replayed ? 'reused' : 'success';
    if (status) status.textContent = payload?.replayed
      ? 'הקישור המאובטח הקיים נפתח מחדש ללא עדכון כפול.'
      : 'הקישור הוכן ונרשם בהיסטוריית הבקשה.';
    if (popup) popup.location.replace(url);
    else window.location.assign(url);
  } catch (error) {
    if (popup) popup.close();
    const message = quoteCaseErrorMessage(error);
    setQuoteCaseError(root, message);
    if (status) status.dataset.state = 'error';
    if (status) status.textContent = message;
    if ((error?.status === 409 || error?.code === 'quote_case_handoff_timeout') && caseData.case_id) {
      try {
        const refreshed = await fetchQuoteCase(caseData.case_id);
        if (refreshed) renderAgentQuoteCase(root, refreshed);
      } catch (refreshError) {
        // Preserve the last confirmed case when a conflict refresh is unavailable.
      }
    }
  } finally {
    delete button.dataset.state;
    button.disabled = false;
  }
}

async function cancelAgentQuoteCase(root) {
  const view = agentWorkbenchRoot(root);
  const button = view.querySelector('[data-quote-case-cancel]');
  const status = view.querySelector('[data-quote-case-action-status]');
  const caseData = agentRuntime.quoteCase;
  if (!button || !caseData || button.dataset.state === 'loading') return;
  if (!window.confirm('לבטל את הבדיקה האישית? התוכנית הפרטית תישאר זמינה, אבל הצוות יפסיק לטפל בבקשה הזאת.')) return;
  if (!button.dataset.idempotencyKey) button.dataset.idempotencyKey = createAgentClientRequestId();
  button.dataset.state = 'loading';
  button.disabled = true;
  if (status) status.dataset.state = 'running';
  if (status) status.textContent = 'שולחים את בקשת הביטול...';
  try {
    const payload = await agentApiRequest(`/quote-cases/${encodeURIComponent(caseData.case_id)}/cancel`, {
      method: 'POST',
      body: JSON.stringify({
        expected_version: Number(caseData.version),
        idempotency_key: button.dataset.idempotencyKey
      })
    });
    const updatedCase = normalizeQuoteCasePayload(payload);
    if (!updatedCase) throw new Error('The server did not confirm cancellation.');
    delete button.dataset.idempotencyKey;
    renderAgentQuoteCase(root, updatedCase);
    if (status) status.dataset.state = 'success';
    if (status) status.textContent = 'הביטול אושר ונרשם בהיסטוריית הבקשה.';
  } catch (error) {
    const message = quoteCaseErrorMessage(error);
    setQuoteCaseError(root, message);
    if (status) status.dataset.state = 'error';
    if (status) status.textContent = message;
    if (error?.status === 409) {
      try {
        const refreshed = await fetchQuoteCase(caseData.case_id);
        if (refreshed) renderAgentQuoteCase(root, refreshed);
      } catch (refreshError) {
        // Preserve the last confirmed case when a conflict refresh is unavailable.
      }
    }
  } finally {
    delete button.dataset.state;
    button.disabled = false;
  }
}

function scheduleQuoteCasePoll(root, delay = 12000) {
  if (agentRuntime.quoteCasePollTimer) window.clearTimeout(agentRuntime.quoteCasePollTimer);
  agentRuntime.quoteCasePollTimer = 0;
  if (!agentRuntime.quoteCase?.case_id || !quoteCaseActiveStatuses.has(agentRuntime.quoteCase.status)) return;
  const caseToken = agentRuntime.quoteCase.case_id;
  const generation = agentRuntime.quoteCasePollGeneration;
  const visibilityDelay = document.visibilityState === 'hidden' ? Math.max(delay, 30000) : delay;
  agentRuntime.quoteCasePollTimer = window.setTimeout(() => {
    if (agentRuntime.quoteCase?.case_id !== caseToken || agentRuntime.quoteCasePollGeneration !== generation) return;
    agentRuntime.quoteCasePollTimer = 0;
    pollAgentQuoteCase(root);
  }, visibilityDelay);
}

async function pollAgentQuoteCase(root) {
  const caseId = agentRuntime.quoteCase?.case_id;
  let hasMore = false;
  if (!caseId) return;
  if (agentRuntime.quoteCasePollInFlight) return;
  if (document.visibilityState === 'hidden') {
    scheduleQuoteCasePoll(root, 30000);
    return;
  }
  const controller = typeof AbortController === 'function' ? new AbortController() : null;
  const generation = agentRuntime.quoteCasePollGeneration + 1;
  agentRuntime.quoteCasePollGeneration = generation;
  agentRuntime.quoteCasePollCaseToken = caseId;
  agentRuntime.quoteCasePollController = controller;
  agentRuntime.quoteCasePollInFlight = true;
  const timeoutId = controller ? window.setTimeout(() => controller.abort(), 15000) : 0;
  const requestOptions = controller ? {signal: controller.signal} : {};
  try {
    const events = await agentApiRequest(`/quote-cases/${encodeURIComponent(caseId)}/events?after=${agentRuntime.quoteCaseLastSequence}&limit=50`, requestOptions);
    if (!isCurrentQuoteCasePoll(caseId, generation, controller)) return;
    hasMore = events?.has_more === true;
    const added = mergeAndRenderQuoteCaseEvents(root, events?.events || []);
    if (Number(events?.last_sequence) > agentRuntime.quoteCaseLastSequence) agentRuntime.quoteCaseLastSequence = Number(events.last_sequence);
    if (added > 0 && !hasMore) {
      const caseData = await fetchQuoteCase(caseId, requestOptions);
      if (!isCurrentQuoteCasePoll(caseId, generation, controller) || !caseData) return;
      renderAgentQuoteCase(root, caseData);
    }
    if (!isCurrentQuoteCasePoll(caseId, generation, controller)) return;
    const pollingError = agentWorkbenchRoot(root).querySelector('[data-quote-case-error][data-source="poll"]');
    if (pollingError) setQuoteCaseError(root);
    agentRuntime.quoteCasePollFailures = 0;
    scheduleQuoteCasePoll(root, hasMore ? 250 : 12000);
  } catch (error) {
    if (!isCurrentQuoteCasePoll(caseId, generation, controller)) return;
    agentRuntime.quoteCasePollFailures = Math.min(8, agentRuntime.quoteCasePollFailures + 1);
    if (agentRuntime.quoteCasePollFailures >= 3) setQuoteCaseError(root, 'העדכון החי של הבדיקה האישית אינו זמין כרגע. המצב האחרון שאושר נשאר מוצג וננסה שוב אוטומטית.', 'poll');
    const retryDelay = Math.min(120000, 12000 * (2 ** Math.min(agentRuntime.quoteCasePollFailures, 4)));
    scheduleQuoteCasePoll(root, retryDelay);
  } finally {
    if (timeoutId) window.clearTimeout(timeoutId);
    if (!isCurrentQuoteCasePoll(caseId, generation, controller)) return;
    agentRuntime.quoteCasePollInFlight = false;
    agentRuntime.quoteCasePollController = null;
    agentRuntime.quoteCasePollCaseToken = '';
  }
}

function renderAgentRun(root, run, focusWorkbench = false) {
  if (!run || typeof run !== 'object' || typeof run.run_id !== 'string') {
    throw new Error('לא התקבלה תוכנית פרטית תקינה.');
  }
  agentRuntime.runId = run.run_id;
  agentRuntime.status = String(run.status || 'created');
  agentRuntime.requestId = String(run.trip_request?.request_id || '');
  agentRuntime.requestRevision = Math.max(0, Number(run.trip_request?.revision) || 0);
  setAgentWorkbenchStatus(root, agentStatusLabel(agentRuntime.status), agentRuntime.status);
  renderAgentTripRequest(root, run.trip_request);
  mergeAndRenderAgentEvents(root, run.events || []);
  renderAgentJourney(root, run);
  renderAgentSupplierState(root, run);
  renderAgentQuoteCaseAvailability(root);
  const failedEvents = agentRuntime.events.filter(event => event?.visible !== false && event?.status === 'failed' && event?.message);
  if (['provider_error', 'failed'].includes(agentRuntime.status)) {
    const failedEvent = failedEvents[failedEvents.length - 1];
    setAgentWorkbenchError(root, failedEvent?.message || 'לא הצלחנו להשלים את התכנון. לא יוצגו חיפוש, מחיר או הצעה ללא עדכון מתועד ממקור מתאים.');
  } else {
    setAgentWorkbenchError(root);
  }
  if (focusWorkbench) agentWorkbenchRoot(root).querySelector('#agent-run-title')?.focus({preventScroll: true});
}

function agentErrorMessage(error) {
  if (error?.status === 404 || error?.code === 'agent_unavailable') {
    return 'שירות המתכנן הפרטי עדיין אינו זמין באתר. הבקשה לא הועברה למפת החיפוש ולא נחשפה בכתובת.';
  }
  if (error?.status === 429) return 'נשלחו יותר מדי בקשות בזמן קצר. המתינו כמה דקות ונסו שוב.';
  if (error?.status === 409) return 'הבקשה הזאת כבר התקבלה. המתינו לפני שליחה נוספת.';
  if (error?.status === 401 || error?.status === 403) return 'הגישה לתוכנית הפרטית פגה. פתחו תוכנית חדשה מהבקשה שמופיעה למעלה.';
  return 'לא התקבל אישור תקין לפתיחת תוכנית פרטית. לא נציג חיפוש, מחיר או הצעה בלי עדכון מתועד ממקור מתאים.';
}

function agentRevisionErrorMessage(error) {
  if (error?.code === 'tra_vel_agent_revision_limit') return 'התוכנית הגיעה למספר העדכונים המרבי. פתחו תוכנית פרטית חדשה כדי להמשיך.';
  if (error?.code === 'tra_vel_agent_revision_busy' || error?.code === 'tra_vel_agent_duplicate_revision') return 'העדכון כבר מתקדם בתוכנית הזאת. המתינו רגע לפני שליחה נוספת.';
  if (error?.status === 429) return 'המתכנן מטפל כרגע בבקשות נוספות. התוכנית הקודמת נשמרה ואפשר לנסות שוב בעוד רגע.';
  if (error?.status === 401 || error?.status === 403 || error?.status === 404) return 'הגישה לתוכנית הפרטית פגה. התוכנית לא שונתה ואפשר לפתוח תוכנית חדשה.';
  return 'העדכון לא הושלם. התוכנית הקודמת נשארה ללא שינוי ואפשר לנסות שוב.';
}

function shouldPollAgentRun(status) {
  return ['created', 'searching'].includes(status);
}

function scheduleAgentPoll(root, delay = 1800) {
  if (agentRuntime.pollTimer) window.clearTimeout(agentRuntime.pollTimer);
  agentRuntime.pollTimer = 0;
  if (!shouldPollAgentRun(agentRuntime.status) || !agentRuntime.runId) return;
  const runToken = agentRuntime.runId;
  const generation = agentRuntime.pollGeneration;
  const visibilityDelay = document.visibilityState === 'hidden' ? Math.max(delay, 30000) : delay;
  agentRuntime.pollTimer = window.setTimeout(() => {
    if (agentRuntime.runId !== runToken || agentRuntime.pollGeneration !== generation) return;
    agentRuntime.pollTimer = 0;
    pollAgentRun(root);
  }, visibilityDelay);
}

function reconnectAgentPolling(root) {
  if (!agentRuntime.runId || !shouldPollAgentRun(agentRuntime.status)) return;
  invalidateAgentPoll();
  agentRuntime.pollFailures = 0;
  setAgentWorkbenchError(root);
  setAgentWorkbenchStatus(root, 'מתחברים מחדש לעדכוני התוכנית הפרטית...', 'connecting');
  pollAgentRun(root);
}

async function pollAgentRun(root) {
  const runId = agentRuntime.runId;
  if (!runId) {
    setAgentWorkbenchError(root, 'לא ניתן להמשיך לעדכן את התוכנית בלשונית הזאת. פתחו אותה מחדש כדי להמשיך.');
    pauseAgentJourneyTransport(root, 'לא ניתן לזהות את התוכנית לעדכון. לא נסמן התקדמות חדשה.');
    return;
  }
  if (agentRuntime.pollInFlight) return;
  if (document.visibilityState === 'hidden') {
    scheduleAgentPoll(root, 30000);
    return;
  }
  const controller = typeof AbortController === 'function' ? new AbortController() : null;
  const generation = agentRuntime.pollGeneration + 1;
  agentRuntime.pollGeneration = generation;
  agentRuntime.pollRunToken = runId;
  agentRuntime.pollController = controller;
  agentRuntime.pollInFlight = true;
  let hasMore = false;
  const timeoutId = controller ? window.setTimeout(() => controller.abort(), 15000) : 0;
  const requestOptions = controller ? {signal: controller.signal} : {};
  try {
    const eventPayload = await agentApiRequest(`/runs/${encodeURIComponent(runId)}/events?after=${agentRuntime.lastSequence}&limit=50`, requestOptions);
    if (!isCurrentAgentPoll(runId, generation, controller)) return;
    hasMore = eventPayload?.has_more === true;
    mergeAndRenderAgentEvents(root, eventPayload.events || []);
    if (Number(eventPayload.last_sequence) > agentRuntime.lastSequence) agentRuntime.lastSequence = Number(eventPayload.last_sequence);
    const run = await agentApiRequest(`/runs/${encodeURIComponent(runId)}`, requestOptions);
    if (!isCurrentAgentPoll(runId, generation, controller)) return;
    if (!run || run.run_id !== runId) throw new Error('The AgentRun response did not match the active private run.');
    renderAgentRun(root, run);
    if (!isCurrentAgentPoll(runId, generation, controller)) return;
    agentRuntime.pollFailures = 0;
    scheduleAgentPoll(root, hasMore ? 250 : 1800);
  } catch (error) {
    if (!isCurrentAgentPoll(runId, generation, controller)) return;
    agentRuntime.pollFailures = Math.min(8, agentRuntime.pollFailures + 1);
    const repeated = agentRuntime.pollFailures >= 3;
    setAgentWorkbenchError(
      root,
      repeated
        ? 'עדכוני התוכנית אינם זמינים כרגע. העדכונים שכבר אושרו נשארים מוצגים, וננסה להתחבר שוב אוטומטית.'
        : 'עדכוני התוכנית נעצרו זמנית. המידע שכבר התקבל נשאר מוצג ללא שינוי.',
      {reconnect: repeated}
    );
    pauseAgentJourneyTransport(
      root,
      repeated
        ? 'החיבור לעדכונים נעצר. המצב האחרון שאושר נשאר מוצג, ללא התקדמות חדשה.'
        : 'ממתינים לחידוש העדכון החי. עדיין לא התקבל שלב חדש.'
    );
    const retryDelay = Math.min(120000, 5000 * (2 ** Math.min(agentRuntime.pollFailures - 1, 5)));
    scheduleAgentPoll(root, retryDelay);
  } finally {
    if (timeoutId) window.clearTimeout(timeoutId);
    if (!isCurrentAgentPoll(runId, generation, controller)) return;
    agentRuntime.pollInFlight = false;
    agentRuntime.pollController = null;
    agentRuntime.pollRunToken = '';
  }
}

async function createAgentRun(root) {
  const prompt = root.querySelector('[data-agent-prompt]');
  const mode = root.querySelector('input[name="mode"]');
  const transcriptConfirmed = root.querySelector('[data-ai-transcript-confirmed]');
  const submit = root.querySelector('[data-agent-submit]');
  const voiceStatus = root.querySelector('[data-ai-voice-status]');
  if (!prompt || !mode || !submit || root.dataset.state === 'loading') return;
  const message = prompt.value.trim();
  if (message.length < 4) {
    prompt.setCustomValidity('כתבו לפחות ארבעה תווים כדי להתחיל.');
    prompt.reportValidity();
    return;
  }
  prompt.setCustomValidity('');

  const inputKind = root.dataset.agentInputKind === 'voice' ? 'voice' : 'typed';
  if (inputKind === 'voice' && !transcriptConfirmed?.checked) {
    if (voiceStatus) voiceStatus.textContent = 'בדקו את התמלול וסמנו שאישרתם אותו לפני השליחה.';
    transcriptConfirmed?.focus();
    return;
  }

  clearAgentRunSession();
  resetAgentRuntime();
  resetAgentWorkbench(root);
  setAgentWorkbenchStatus(root, 'שולחים את הבקשה באופן פרטי. עדיין לא התקבל אישור.', 'connecting');
  setAgentJourneyConnecting(root);
  submit.disabled = true;
  root.dataset.state = 'loading';
  beginAgentTheater(root);
  try {
    const payload = await agentApiRequest('/runs', {
      method: 'POST',
      body: JSON.stringify({
        prompt: message,
        mode: mode.value === 'surprise' ? 'surprise' : 'agent',
        locale: detectAgentLocale(message),
        input_kind: inputKind,
        transcript_confirmed: inputKind === 'typed' || Boolean(transcriptConfirmed?.checked),
        planning_context: agentPlanningContextFromLocation(),
        client_request_id: createAgentClientRequestId()
      })
    });
    const run = {...payload};
    if (!run.run_id) throw new Error('לא התקבל אישור לפתיחת תוכנית פרטית.');
    resetAgentRuntime(run.run_id);
    const stored = storeAgentRunSession(run.run_id);
    renderAgentRun(root, run, true);
    settleAgentTheater(root);
    if (!stored) {
      setAgentWorkbenchError(root, 'התוכנית נפתחה, אבל הדפדפן חסם את שמירתה הזמנית בלשונית. העדכונים הראשונים מוצגים, אך לא יתבצע עדכון נוסף.');
      pauseAgentJourneyTransport(root, 'התוכנית נפתחה, אך עדכוני ההמשך נעצרו בדפדפן. המצב המאושר נשאר מוצג.');
      return;
    }
    scheduleAgentPoll(root);
  } catch (error) {
    collapseAgentTheater(root);
    setAgentWorkbenchStatus(root, 'לא התקבל אישור לפתיחת תוכנית פרטית', 'error');
    setAgentWorkbenchError(root, agentErrorMessage(error));
    failAgentJourneyConnection(root);
    const view = agentWorkbenchRoot(root);
    const empty = view.querySelector('[data-agent-event-empty]');
    if (empty) empty.textContent = 'לא התקבלו עדכונים מאומתים לתוכנית.';
    view.querySelector('#agent-run-title')?.focus({preventScroll: true});
  } finally {
    submit.disabled = false;
    root.dataset.state = 'idle';
  }
}

async function reviseAgentRun(root, form) {
  const view = agentWorkbenchRoot(root);
  const messageInput = form.querySelector('[data-agent-revision-message]');
  const submit = form.querySelector('[data-agent-revision-submit]');
  const status = form.querySelector('[data-agent-revision-status]');
  if (!messageInput || !submit || form.dataset.state === 'loading') return;

  const message = messageInput.value.trim();
  if (message.length < 2) {
    messageInput.setCustomValidity('כתבו תשובה או שינוי קצר כדי לעדכן את התוכנית.');
    messageInput.reportValidity();
    return;
  }
  messageInput.setCustomValidity('');
  if (!agentRuntime.runId) {
    if (status) {
      status.textContent = 'לא ניתן לזהות את התוכנית הזאת. פתחו תוכנית פרטית חדשה.';
      status.dataset.state = 'error';
    }
    return;
  }

  if (agentRuntime.pollTimer) window.clearTimeout(agentRuntime.pollTimer);
  agentRuntime.pollTimer = 0;
  form.dataset.state = 'loading';
  form.setAttribute('aria-busy', 'true');
  submit.disabled = true;
  if (status) {
    status.textContent = 'משלבים את התשובה, בודקים אילוצים ומעדכנים את אותה תוכנית...';
    status.dataset.state = 'running';
  }
  setAgentWorkbenchError(root);
  setAgentWorkbenchStatus(root, 'מעדכנים את התוכנית לפי התשובה', 'revising');

  const runId = agentRuntime.runId;
  try {
    const run = await agentApiRequest(`/runs/${encodeURIComponent(runId)}/messages`, {
      method: 'POST',
      body: JSON.stringify({
        message,
        locale: detectAgentLocale(message),
        input_kind: 'typed',
        transcript_confirmed: true,
        client_request_id: createAgentClientRequestId()
      })
    });
    if (agentRuntime.runId !== runId) return;
    renderAgentRun(root, run);
    if (agentRuntime.quoteCase) await loadQuoteCaseForRun(root);
    messageInput.value = '';
    if (status) {
      const revision = Math.max(1, Number(run.trip_request?.revision) || 1);
      status.textContent = run.status === 'needs_clarification'
        ? `עדכון ${revision} נשמר. נשאר פרט נוסף שצריך להשלים.`
        : `עדכון ${revision} נשמר. פרטי התוכנית מוכנים לבדיקה אישית, ועדיין לא נשלחה בקשה לספק.`;
      status.dataset.state = 'success';
    }
    scheduleAgentPoll(root);
  } catch (error) {
    try {
      const currentRun = await agentApiRequest(`/runs/${encodeURIComponent(runId)}`);
      if (agentRuntime.runId === runId) renderAgentRun(root, currentRun);
    } catch (refreshError) {
      // Keep the last confirmed plan visible when the status refresh is unavailable.
    }
    const messageText = agentRevisionErrorMessage(error);
    setAgentWorkbenchError(root, messageText);
    if (status) {
      status.textContent = messageText;
      status.dataset.state = 'error';
    }
  } finally {
    submit.disabled = false;
    form.dataset.state = 'idle';
    form.setAttribute('aria-busy', 'false');
  }
}

async function resumeAgentRun(root) {
  const runId = readAgentSessionValue(agentActiveRunStorageKey);
  if (!runId) return;
  resetAgentRuntime(runId);
  resetAgentWorkbench(root);
  setAgentWorkbenchStatus(root, 'טוענים את התוכנית הפרטית מהלשונית הזאת.', 'connecting');
  setAgentJourneyConnecting(root);
  try {
    const run = await agentApiRequest(`/runs/${encodeURIComponent(runId)}`);
    if (agentRuntime.runId !== runId) return;
    renderAgentRun(root, run);
    await loadQuoteCaseForRun(root);
    scheduleAgentPoll(root);
  } catch (error) {
    clearAgentRunSession();
    setAgentWorkbenchStatus(root, 'לא ניתן לחדש את התוכנית הפרטית', 'error');
    setAgentWorkbenchError(root, agentErrorMessage(error));
    pauseAgentJourneyTransport(root, 'לא ניתן לטעון את מצב התוכנית. לא נסמן התקדמות שלא התקבלה בעדכון מאומת.');
  }
}

// --- Agent theater (theme 1.29.0) -----------------------------------------
// A staged progress strip near the planner composer. It narrates only real
// milestones of the one request that is actually in flight: stage one lights
// when the request is dispatched, stage two after a modest delay while the
// request is genuinely still pending (the transport exposes completion only,
// no first-byte signal), and stage three strictly after the confirmed
// response rendered. An error collapses the strip and leaves the existing
// error surfaces untouched.
let agentTheaterTimer = 0;

function agentTheaterElements(root) {
  const theater = agentWorkbenchRoot(root).querySelector('[data-agent-theater]');
  if (!theater) return null;
  return {
    theater,
    understand: theater.querySelector('[data-agent-theater-stage="understand"]'),
    scan: theater.querySelector('[data-agent-theater-stage="scan"]'),
    ready: theater.querySelector('[data-agent-theater-stage="ready"]')
  };
}

function setAgentTheaterStage(stage, stageState) {
  if (stage) stage.dataset.state = stageState;
}

function beginAgentTheater(root) {
  const parts = agentTheaterElements(root);
  if (!parts) return;
  if (agentTheaterTimer) window.clearTimeout(agentTheaterTimer);
  parts.theater.hidden = false;
  parts.theater.dataset.state = 'running';
  setAgentTheaterStage(parts.understand, 'active');
  setAgentTheaterStage(parts.scan, 'waiting');
  setAgentTheaterStage(parts.ready, 'waiting');
  agentTheaterTimer = window.setTimeout(() => {
    agentTheaterTimer = 0;
    if (parts.theater.hidden || parts.theater.dataset.state !== 'running') return;
    setAgentTheaterStage(parts.understand, 'complete');
    setAgentTheaterStage(parts.scan, 'active');
  }, 1200);
}

function settleAgentTheater(root) {
  const parts = agentTheaterElements(root);
  if (!parts || parts.theater.hidden || parts.theater.dataset.state !== 'running') return;
  if (agentTheaterTimer) {
    window.clearTimeout(agentTheaterTimer);
    agentTheaterTimer = 0;
  }
  parts.theater.dataset.state = 'done';
  setAgentTheaterStage(parts.understand, 'complete');
  setAgentTheaterStage(parts.scan, 'complete');
  setAgentTheaterStage(parts.ready, 'complete');
}

function collapseAgentTheater(root) {
  const parts = agentTheaterElements(root);
  if (!parts) return;
  if (agentTheaterTimer) {
    window.clearTimeout(agentTheaterTimer);
    agentTheaterTimer = 0;
  }
  parts.theater.dataset.state = 'idle';
  parts.theater.hidden = true;
  setAgentTheaterStage(parts.understand, 'waiting');
  setAgentTheaterStage(parts.scan, 'waiting');
  setAgentTheaterStage(parts.ready, 'waiting');
}

// --- Voice dock arrival (theme 1.29.0) -------------------------------------
// The globe voice dock hands its reviewed text to the planner through the
// voice_prompt query parameter. The parameters are consumed once (and removed
// from the address bar) so a refresh never repeats a submission.
function consumeVoiceArrivalRequest() {
  let params;
  try {
    params = new URLSearchParams(window.location.search);
  } catch (error) {
    return null;
  }
  if (!params.has('voice_prompt')) return null;
  const text = String(params.get('voice_prompt') || '').replace(/\s+/g, ' ').trim().slice(0, 4000);
  const spoken = params.get('voice') === '1';
  params.delete('voice_prompt');
  params.delete('voice');
  const query = params.toString();
  try {
    window.history.replaceState(window.history.state, '', `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`);
  } catch (error) {
    // Leave the address bar unchanged when history is unavailable.
  }
  if (text.length < 4) return null;
  return { text, spoken };
}

function applyVoiceArrival(root, arrival, tools) {
  if (!arrival) return false;
  const { prompt, status, confirmation, confirmed } = tools;
  prompt.value = arrival.text;
  prompt.setCustomValidity('');
  root.scrollIntoView({ behavior: preferredScrollBehavior(), block: 'start' });
  if (arrival.spoken) {
    // The text came from the microphone, so the planner's existing voice
    // contract stays authoritative: the traveler reviews and confirms the
    // transcript here, and nothing is sent on their behalf.
    root.dataset.agentInputKind = 'voice';
    if (confirmed) confirmed.checked = false;
    if (confirmation) confirmation.hidden = false;
    status.textContent = 'התמלול מופיע בשדה. בדקו אותו ואשרו לפני שמתחילים.';
    (confirmed || prompt).focus({ preventScroll: true });
    return true;
  }
  root.dataset.agentInputKind = 'typed';
  if (typeof root.requestSubmit === 'function') root.requestSubmit();
  else createAgentRun(root);
  return true;
}

function initAIConversationEntry() {
  const root = document.querySelector('[data-ai-conversation-entry]');
  const button = root?.querySelector('[data-ai-voice]');
  const prompt = root?.querySelector('[data-agent-prompt]');
  const status = root?.querySelector('[data-ai-voice-status]');
  const confirmation = root?.querySelector('[data-ai-transcript-confirmation]');
  const confirmed = root?.querySelector('[data-ai-transcript-confirmed]');
  if (!root || !button || !prompt || !status) return;

  root.dataset.agentInputKind = 'typed';
  const submit = root.querySelector('[data-agent-submit]');
  if (submit) submit.disabled = false;
  const voiceArrival = consumeVoiceArrivalRequest();
  const revisionForm = agentWorkbenchRoot(root).querySelector('[data-agent-revision-form]');
  const revisionMessage = revisionForm?.querySelector('[data-agent-revision-message]');
  const quoteConsent = agentWorkbenchRoot(root).querySelector('[data-quote-case-consent]');
  const quoteCreate = agentWorkbenchRoot(root).querySelector('[data-quote-case-create-button]');
  const quoteHandoff = agentWorkbenchRoot(root).querySelector('[data-quote-case-handoff]');
  const quoteCancel = agentWorkbenchRoot(root).querySelector('[data-quote-case-cancel]');
  revisionForm?.addEventListener('submit', event => {
    event.preventDefault();
    reviseAgentRun(root, revisionForm);
  });
  revisionMessage?.addEventListener('input', () => revisionMessage.setCustomValidity(''));
  quoteConsent?.addEventListener('change', () => {
    if (quoteCreate) quoteCreate.disabled = !quoteConsent.checked || quoteCreate.dataset.state === 'loading';
  });
  quoteCreate?.addEventListener('click', () => createAgentQuoteCase(root));
  quoteHandoff?.addEventListener('click', () => handoffAgentQuoteCase(root));
  quoteCancel?.addEventListener('click', () => cancelAgentQuoteCase(root));
  root.addEventListener('submit', event => {
    event.preventDefault();
    createAgentRun(root);
  });
  prompt.addEventListener('input', () => {
    prompt.setCustomValidity('');
    if (root.dataset.agentInputKind === 'voice') {
      if (confirmed) confirmed.checked = false;
      if (confirmation) confirmation.hidden = false;
      status.textContent = 'התמלול נערך. בדקו אותו ואשרו מחדש לפני השליחה.';
    }
  });
  confirmed?.addEventListener('change', () => {
    status.textContent = confirmed.checked
      ? 'התמלול אושר ויישלח עם סימון שמקורו בקול.'
      : 'בדקו את התמלול ואשרו אותו לפני השליחה.';
  });

  document.addEventListener('visibilitychange', () => {
    const reconnectRun = Boolean(agentRuntime.runId && shouldPollAgentRun(agentRuntime.status));
    const reconnectCase = Boolean(agentRuntime.quoteCase?.case_id && quoteCaseActiveStatuses.has(agentRuntime.quoteCase.status));
    invalidateAgentPoll();
    invalidateQuoteCasePoll();
    if (document.visibilityState !== 'visible') return;
    if (reconnectRun) scheduleAgentPoll(root, 250);
    if (reconnectCase) scheduleQuoteCasePoll(root, 250);
  });

  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SpeechRecognition) {
    button.addEventListener('click', () => {
      status.textContent = 'הכתבה קולית אינה זמינה בדפדפן הזה. אפשר לכתוב את הבקשה באותו שדה.';
      prompt.focus();
    });
    if (!applyVoiceArrival(root, voiceArrival, { prompt, status, confirmation, confirmed })) resumeAgentRun(root);
    return;
  }

  const recognition = new SpeechRecognition();
  recognition.lang = 'he-IL';
  recognition.interimResults = false;
  recognition.maxAlternatives = 1;
  let listening = false;

  const setListening = value => {
    listening = value;
    button.classList.toggle('is-listening', value);
    button.setAttribute('aria-pressed', String(value));
    const label = button.querySelector('span');
    if (label) label.textContent = value ? 'עצרו את ההקלטה' : 'דברו במקום להקליד';
  };

  button.addEventListener('click', () => {
    try {
      if (listening) recognition.stop();
      else recognition.start();
    } catch (error) {
      status.textContent = 'המיקרופון כבר מקשיב. אמרו את הבקשה במילים שלכם.';
    }
  });
  recognition.addEventListener('start', () => {
    setListening(true);
    status.textContent = 'מקשיב. אמרו תקציב, אווירה, מי נוסע וכל דבר שאסור לפספס.';
  });
  recognition.addEventListener('result', event => {
    const transcript = event.results?.[0]?.[0]?.transcript?.trim();
    if (!transcript) return;
    prompt.value = transcript;
    root.dataset.agentInputKind = 'voice';
    if (confirmation) confirmation.hidden = false;
    if (confirmed) confirmed.checked = false;
    status.textContent = 'התמלול מופיע בשדה. בדקו אותו ואשרו לפני שמתחילים.';
    prompt.focus();
  });
  recognition.addEventListener('error', () => {
    status.textContent = 'לא הצלחנו לקלוט את הבקשה. אפשר לנסות שוב או לכתוב אותה.';
  });
  recognition.addEventListener('end', () => setListening(false));
  if (!applyVoiceArrival(root, voiceArrival, { prompt, status, confirmation, confirmed })) resumeAgentRun(root);
}

function initDirectory() {
  const root = document.querySelector('[data-directory-root]');
  if (!root) return;
  const form = root.querySelector('[data-directory-filter]');
  const query = root.querySelector('[data-directory-query]');
  const cards = [...root.querySelectorAll('[data-directory-card]')];
  const count = root.querySelector('[data-directory-count]');
  const empty = root.querySelector('[data-directory-empty]');
  const buttons = [...root.querySelectorAll('[data-directory-value]')];
  const urlParams = new URLSearchParams(window.location.search);
  const requestedDestination = urlParams.get('destination') || '';
  const requestedRegion = urlParams.get('region') || '';
  const requestedIntent = urlParams.get('intent') || '';
  const intentQueries = { family: 'משפחות', couples: 'זוגות' };
  let destinationFilter = directoryDestinationAliases[requestedDestination] || requestedDestination;
  let active = buttons.some(button => button.dataset.directoryValue === requestedRegion) ? requestedRegion : 'all';
  const normalize = value => String(value || '').toLocaleLowerCase('he-IL').trim();
  const apply = () => {
    const phrase = normalize(query?.value);
    let visible = 0;
    cards.forEach(card => {
      const matchesFilter = active === 'all' || card.dataset.region === active || card.dataset.experience === active;
      const matchesQuery = !phrase || normalize(card.dataset.search).includes(phrase);
      const matchesDestination = !destinationFilter || card.dataset.directoryDestination === destinationFilter;
      card.hidden = !(matchesFilter && matchesQuery && matchesDestination);
      if (!card.hidden) visible += 1;
    });
    if (count) count.textContent = String(visible);
    if (empty) empty.hidden = visible !== 0;
  };
  form?.addEventListener('submit', event => {
    event.preventDefault();
    apply();
  });
  query?.addEventListener('input', () => {
    destinationFilter = '';
    apply();
  });
  buttons.forEach(button => button.addEventListener('click', () => {
    destinationFilter = '';
    active = button.dataset.directoryValue || 'all';
    buttons.forEach(item => item.classList.toggle('is-active', item === button));
    apply();
  }));
  if (query && intentQueries[requestedIntent]) query.value = intentQueries[requestedIntent];
  buttons.forEach(button => button.classList.toggle('is-active', button.dataset.directoryValue === active));
  apply();
}

function initExperienceDecisionMap() {
  const map = document.querySelector('[data-experience-decision-map]');
  if (!map) return;
  const column = map.closest('.experience-globe-column');
  const title = column?.querySelector('[data-experience-selection-title]');
  const copy = column?.querySelector('[data-experience-selection-copy]');
  const link = column?.querySelector('[data-experience-selection-link]');
  const buttons = [...map.querySelectorAll('[data-experience-destination]')];
  const select = button => {
    const destination = String(button.dataset.experienceDestination || '').replace(/[^a-z0-9-]/g, '').slice(0, 60);
    if (!destination) return;
    map.dataset.selectedDestination = destination;
    buttons.forEach(item => {
      const selected = item === button;
      item.classList.toggle('is-active', selected);
      item.setAttribute('aria-pressed', String(selected));
    });
    if (title) title.textContent = button.dataset.experienceTitle || button.textContent.trim();
    if (copy) copy.textContent = button.dataset.experienceCopy || '';
    if (link) link.href = destinationPlanUrl('/travel-map/', {destination});
  };
  buttons.forEach(button => button.addEventListener('click', () => select(button)));
  if (map.dataset.contextSupported === 'false') return;
  const selected = buttons.find(button => button.dataset.experienceDestination === map.dataset.selectedDestination) || buttons[0];
  if (selected) select(selected);
}

// --- Globe dive store (theme 1.25.0) ----------------------------------------
// Every double-click or double-tap dive reveals the struck location's services
// below the globe. Depth model: D0 orbit (existing previews), D1 first dive or
// destination selection (hero + service chip row, globe yields height), D2
// second dive on the same focus (full service board, globe docks smaller).
// Truth rules: prices come only from existing planning data, always in the
// 'החל מ-' form with one panel-level footnote; hub and free-point surfaces
// never show a price. The dive store binds no wheel or scroll listeners.
const diveStoreFootnoteText = 'המחירים להמחשה; המחיר הסופי מאומת לפני התשלום.';
const diveStoreServiceOrder = ['flights', 'accommodation', 'transfers', 'activities', 'dining', 'insurance', 'connectivity', 'equipment'];
const diveStoreHubServiceOrder = ['flights', 'accommodation', 'connectivity', 'insurance'];
const diveStoreServiceLabels = {
  flights: 'טיסות',
  accommodation: 'מלונות',
  transfers: 'העברות',
  activities: 'פעילויות',
  dining: 'אוכל',
  insurance: 'ביטוח',
  connectivity: 'eSIM ותקשורת',
  equipment: 'ציוד'
};
const diveStoreServiceIcons = {
  flights: 'plane-takeoff',
  accommodation: 'hotel',
  transfers: 'car-taxi-front',
  activities: 'ticket-check',
  dining: 'utensils',
  insurance: 'shield-check',
  connectivity: 'wifi',
  equipment: 'luggage'
};
const diveStoreServiceCtaLabels = {
  flights: 'השוו טיסות',
  accommodation: 'פתחו חיפוש מלונות',
  transfers: 'התאימו העברות',
  activities: 'פתחו פעילויות במפת היעד',
  dining: 'פתחו אוכל והעדפות במפת היעד',
  insurance: 'בדקו נושאים לביטוח',
  connectivity: 'פתחו תקשורת במפת היעד',
  equipment: 'פתחו ציוד במפת היעד'
};
const diveStoreHubCtaLabels = {
  flights: 'חפשו טיסות',
  accommodation: 'חפשו לינה',
  connectivity: 'תכננו עם המומחה',
  insurance: 'בדקו ביטוח'
};
let globeDiveState = { depth: 0, kind: '', key: '', latitude: null, longitude: null };
let globeDiveRoot = null;

function diveStoreNextState(current = { depth: 0, kind: '', key: '' }, event = {}) {
  const closed = { depth: 0, kind: '', key: '', latitude: null, longitude: null };
  const state = {
    depth: Number(current?.depth) > 0 ? Math.min(2, Math.floor(Number(current.depth))) : 0,
    kind: typeof current?.kind === 'string' ? current.kind : '',
    key: typeof current?.key === 'string' ? current.key : '',
    latitude: Number.isFinite(Number(current?.latitude)) ? Number(current.latitude) : null,
    longitude: Number.isFinite(Number(current?.longitude)) ? Number(current.longitude) : null
  };
  if (event?.type === 'reset') return closed;
  if (event?.type === 'back') {
    const depth = Math.max(0, state.depth - 1);
    return depth === 0 ? closed : { ...state, depth };
  }
  const kind = ['destination', 'exploration_hub', 'map_point'].includes(event?.kind) ? event.kind : '';
  const key = typeof event?.key === 'string' ? event.key.slice(0, 80) : '';
  if (!kind || !key) return state;
  const next = {
    kind,
    key,
    latitude: Number.isFinite(Number(event.latitude)) ? Number(event.latitude) : null,
    longitude: Number.isFinite(Number(event.longitude)) ? Number(event.longitude) : null
  };
  if (event.type === 'dive') {
    // A second dive while the same target stays focused deepens to the board;
    // a dive on any other target flies there and swaps the panel back to D1.
    const sameFocusedTarget = state.depth >= 1 && state.kind === kind && state.key === key;
    const depth = kind === 'map_point' ? 1 : (sameFocusedTarget ? 2 : 1);
    return { ...next, depth };
  }
  if (event.type === 'select') {
    // Selecting a destination is a D1 entry; a plain tap elsewhere returns the
    // surface to the D0 orbit previews without opening the store.
    if (kind !== 'destination') return closed;
    const sameTarget = state.kind === 'destination' && state.key === key;
    return { ...next, depth: sameTarget ? Math.max(1, state.depth) : 1 };
  }
  return state;
}

function diveStorePointKind(detail = {}) {
  const destinationId = typeof detail.nearestDestination === 'string' ? detail.nearestDestination : '';
  if (detail.selectionKind === 'destination' && detail.supported && destinationId && destinationData[destinationId]) return 'destination';
  const hubId = typeof detail.hubId === 'string' ? detail.hubId.replace(/[^a-z0-9-]/g, '').slice(0, 60) : '';
  if (detail.selectionKind === 'exploration_hub' && hubId && explorationHubData[hubId]) return 'exploration_hub';
  const latitude = Number(detail.latitude);
  const longitude = Number(detail.longitude);
  if (Number.isFinite(latitude) && latitude >= -90 && latitude <= 90
    && Number.isFinite(longitude) && longitude >= -180 && longitude <= 180) return 'map_point';
  return '';
}

function diveStoreTargetKey(kind, detail = {}) {
  if (kind === 'destination') return String(detail.nearestDestination || '');
  if (kind === 'exploration_hub') return String(detail.hubId || '').replace(/[^a-z0-9-]/g, '').slice(0, 60);
  const latitude = Number(detail.latitude);
  const longitude = Number(detail.longitude);
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return '';
  return `point:${latitude.toFixed(1)}:${longitude.toFixed(1)}`;
}

function nearestCuratedDestinations(point, destinations = destinationData, count = 3) {
  const latitude = Number(point?.latitude);
  const longitude = Number(point?.longitude);
  if (!Number.isFinite(latitude) || latitude < -90 || latitude > 90
    || !Number.isFinite(longitude) || longitude < -180 || longitude > 180) return [];
  return Object.values(destinations || {})
    .filter(destination => destination && typeof destination.id === 'string'
      && Number.isFinite(Number(destination.latitude)) && Number.isFinite(Number(destination.longitude)))
    .map(destination => ({
      id: destination.id,
      city: destination.city || destination.id,
      country: destination.country || '',
      distanceKm: Math.round(explorationHubDistanceKm({ latitude, longitude }, destination))
    }))
    .sort((first, second) => first.distanceKm - second.distanceKm || first.id.localeCompare(second.id))
    .slice(0, Math.max(1, Math.min(8, Number(count) || 3)));
}

function diveBreadcrumbTrail(state = globeDiveState) {
  if (!state || !(Number(state.depth) > 0)) return ['עולם'];
  if (state.kind === 'destination') {
    const data = destinationData[state.key];
    if (data) return ['עולם', data.country, data.city].filter(Boolean);
  }
  if (state.kind === 'exploration_hub') {
    const hub = explorationHubData[state.key];
    if (hub) return ['עולם', hub.country, hub.city].filter(Boolean);
  }
  return ['עולם', 'נקודה על הגלובוס'];
}

function diveDestinationServiceLinks(data) {
  // Theme 1.26.0 one-click-first: activities, dining, connectivity, and
  // equipment open the destination's map plan with the matching module in
  // focus instead of routing the tap into the planner conversation.
  const airport = data.airportCode || '';
  return {
    flights: destinationPlanUrl('/flights/', { destination: airport }),
    accommodation: destinationPlanUrl('/hotels/', { destination: airport }),
    transfers: destinationPlanUrl('/packages/', { destination: airport, transfers: 'true' }),
    activities: destinationPlanUrl('/travel-map/', { destination: data.id, scope: 'activities' }),
    dining: destinationPlanUrl('/travel-map/', { destination: data.id, scope: 'dining' }),
    insurance: destinationPlanUrl('/travel-insurance/', { trip_destination: data.id }),
    connectivity: destinationPlanUrl('/travel-map/', { destination: data.id, scope: 'connectivity' }),
    equipment: destinationPlanUrl('/travel-map/', { destination: data.id, scope: 'equipment' })
  };
}

function diveHubServiceLinks(hub) {
  const destination = hub.iataSearchCode || hub.id;
  const q = `${hub.city}, ${hub.country}`;
  return {
    flights: destinationPlanUrl('/flights/', { q, scope: 'flights', destination }),
    accommodation: destinationPlanUrl('/hotels/', { q, scope: 'accommodation', destination }),
    connectivity: destinationPlanUrl('/ai-planner/', { q, scope: 'connectivity', destination }),
    insurance: destinationPlanUrl('/travel-insurance/', { q, scope: 'insurance', destination, trip_destination: hub.id })
  };
}

function diveServiceChips(kind, data) {
  if (kind === 'destination' && data) {
    const links = diveDestinationServiceLinks(data);
    return diveStoreServiceOrder.map(id => ({ id, icon: diveStoreServiceIcons[id], label: diveStoreServiceLabels[id], href: links[id] }));
  }
  if (kind === 'exploration_hub' && data) {
    const links = diveHubServiceLinks(data);
    return diveStoreHubServiceOrder.map(id => ({ id, icon: diveStoreServiceIcons[id], label: diveStoreServiceLabels[id], href: links[id] }));
  }
  return [];
}

function divePriceParts(value) {
  const text = typeof value === 'string' ? value.trim() : '';
  const match = text.match(/^החל מ-(.+)$/);
  if (!match) return null;
  const amount = match[1].trim();
  if (!/^[$€£₪][\d,.]+$/.test(amount)) return null;
  return { amount };
}

function diveCurrencySymbol(currency) {
  return { USD: '$', EUR: '€', GBP: '£', ILS: '₪' }[String(currency || '').toUpperCase()] || '$';
}

function divePlanningModuleHeadline(data, moduleId) {
  const headline = data?.planning?.modules?.[moduleId]?.headline;
  return typeof headline === 'string' ? headline : '';
}

function diveDestinationHeroMeta(data) {
  const parts = [];
  if (data.flightDuration) {
    parts.push(data.airportDirect
      ? `טיסה ישירה משוערת, כ-${data.flightDuration} שעות`
      : `זמן טיסה משוער כ-${data.flightDuration} שעות, כולל עצירה`);
  }
  // Weather and season lines follow the same supplier truth gates as the rest
  // of the panel: no live or last-checked snapshot means no weather claim.
  const weatherUsable = Boolean(data.liveLayers?.weather) && (discoverySnapshotIsCurrent() || discoverySnapshotIsStale());
  if (weatherUsable && data.weather) parts.push(`${data.weather}${data.weatherCondition ? ` · ${data.weatherCondition}` : ''}`);
  const seasonUsable = weatherUsable && discoverySnapshotIsCurrent() && fieldProvenanceLive(discoveryFieldProvenance, 'weather_season', data.id) && data.seasonFit;
  if (seasonUsable) parts.push(`התאמת עונה: ${data.seasonFit}`);
  if (!weatherUsable) parts.push('מזג אוויר ועונה ייבדקו לפי מועד הנסיעה');
  return parts;
}

function diveDestinationCards(data) {
  const links = diveDestinationServiceLinks(data);
  const flightFact = data.flightDuration
    ? (data.airportDirect ? `טיסה ישירה, כ-${data.flightDuration} שעות` : `כ-${data.flightDuration} שעות, כולל עצירה`)
    : '';
  const transferMinutes = Number(data.transferMinutes);
  const facts = {
    flights: flightFact,
    accommodation: data.hotelArea ? `אזור ${data.hotelArea}` : '',
    transfers: Number.isFinite(transferMinutes) && transferMinutes > 0 ? `כ-${transferMinutes} דקות מהשדה למרכז` : '',
    activities: '',
    dining: divePlanningModuleHeadline(data, 'dining'),
    insurance: '',
    connectivity: divePlanningModuleHeadline(data, 'connectivity'),
    equipment: divePlanningModuleHeadline(data, 'equipment')
  };
  const flightPrice = divePriceParts(data.price);
  const nightly = typeof data.hotelPrice === 'string' && /^[$€£₪][\d,.]+$/.test(data.hotelPrice.trim()) ? data.hotelPrice.trim() : '';
  const prices = {
    flights: flightPrice ? { amount: flightPrice.amount, suffix: '' } : null,
    accommodation: nightly ? { amount: nightly, suffix: ' ללילה' } : null
  };
  return diveStoreServiceOrder.map(id => ({
    id,
    icon: diveStoreServiceIcons[id],
    label: diveStoreServiceLabels[id],
    fact: facts[id] || '',
    price: prices[id] || null,
    cta: { label: diveStoreServiceCtaLabels[id], href: links[id] }
  }));
}

function diveStoreRoutesForDestination(key) {
  if (Array.isArray(homeRouteExamples?.[key]) && homeRouteExamples[key].length) return homeRouteExamples[key];
  if (key && key === activeDestination && Array.isArray(discoveryRoutes) && discoveryRoutes.length) return discoveryRoutes;
  return [];
}

function diveBundleCard(data, routes = []) {
  if (!data) return null;
  // The bundle sample price may come only from the destination's existing
  // planning-route insurance components; without that data no price renders.
  const insuranceCosts = (Array.isArray(routes) ? routes : [])
    .map(route => Number(route?.costs?.insurance))
    .filter(value => Number.isFinite(value) && value > 0);
  const currency = diveCurrencySymbol((Array.isArray(routes) && routes[0]?.currency) || data.currency);
  const price = insuranceCosts.length
    ? { amount: `${currency}${Math.min(...insuranceCosts)}`, suffix: '', caption: 'רכיב הביטוח מתוך מסלולי התכנון של היעד' }
    : null;
  return {
    id: 'travel-kit',
    icon: 'package',
    label: 'ערכת נסיעה',
    bundle: true,
    fact: 'ביטוח נסיעות ו-eSIM ותקשורת, מותאמים יחד לנסיעה אחת',
    price,
    cta: { label: 'תכננו ערכת נסיעה עם המומחה', href: destinationPlanUrl('/ai-planner/', { destination: data.id, scope: 'connectivity,insurance' }) }
  };
}

function diveHubCards(hub) {
  const links = diveHubServiceLinks(hub);
  const facts = {
    flights: `דרכי הגעה אל ${hub.city}`,
    accommodation: `לינה ב${hub.city} לפי אזור והרכב`,
    connectivity: 'נפח גלישה וכיסוי לפי ימי הנסיעה',
    insurance: 'נושאים לבירור לפי המסלול והנוסעים'
  };
  return diveStoreHubServiceOrder.map(id => ({
    id,
    icon: diveStoreServiceIcons[id],
    label: diveStoreServiceLabels[id],
    fact: facts[id],
    price: null,
    cta: { label: diveStoreHubCtaLabels[id], href: links[id] }
  }));
}

function diveStoreSectionFor(globeRoot) {
  const scope = globeRoot?.closest?.('.theme-map-shell') || globeRoot?.closest?.('.home-globe-stack');
  return scope?.querySelector?.('[data-dive-store]') || document.querySelector('[data-dive-store]');
}

function setGlobeDiveDepthAttributes(globeRoot, depth) {
  const targets = [globeRoot, globeRoot?.closest?.('[data-map-canvas]'), globeRoot?.closest?.('.globe-panel')];
  targets.forEach(target => {
    if (!target || !target.dataset) return;
    if (depth > 0) target.dataset.diveDepth = String(depth);
    else delete target.dataset.diveDepth;
  });
}

function appendDiveIcon(parent, name) {
  const icon = document.createElement('i');
  icon.setAttribute('data-lucide', name);
  parent.append(icon);
}

function appendDivePrice(parent, price) {
  if (!price) return false;
  const wrap = document.createElement('span');
  wrap.className = 'dive-price';
  wrap.append(document.createTextNode('החל מ-'));
  const amount = document.createElement('bdi');
  amount.setAttribute('dir', 'ltr');
  amount.textContent = price.amount;
  wrap.append(amount);
  if (price.suffix) wrap.append(document.createTextNode(price.suffix));
  parent.append(wrap);
  if (price.caption) appendTextElement(parent, 'small', price.caption, 'dive-price-caption');
  return true;
}

function renderDiveBreadcrumb(container, trail) {
  if (!container) return;
  container.replaceChildren();
  trail.forEach((part, index) => {
    if (index > 0) {
      const separator = document.createElement('span');
      separator.className = 'dive-crumb-separator';
      separator.setAttribute('aria-hidden', 'true');
      separator.textContent = '‹';
      container.append(separator);
    }
    appendTextElement(container, index === trail.length - 1 && trail.length > 1 ? 'b' : 'span', part, 'dive-crumb');
  });
}

function renderDiveChipRow(container, chips) {
  if (!container) return;
  container.replaceChildren(...chips.map(chip => {
    const link = document.createElement('a');
    link.className = 'dive-chip';
    link.setAttribute('role', 'listitem');
    link.href = chip.href;
    appendDiveIcon(link, chip.icon);
    appendTextElement(link, 'span', chip.label);
    return link;
  }));
}

function renderDiveCard(card) {
  const article = document.createElement('article');
  article.className = card.bundle ? 'dive-card is-bundle' : 'dive-card';
  article.dataset.diveCard = card.id;
  appendDiveIcon(article, card.icon);
  appendTextElement(article, 'b', card.label);
  if (card.fact) appendTextElement(article, 'em', card.fact);
  const priced = appendDivePrice(article, card.price);
  const action = document.createElement('a');
  action.className = 'dive-card-action';
  action.href = card.cta.href;
  appendTextElement(action, 'span', card.cta.label);
  appendDiveIcon(action, 'arrow-left');
  article.append(action);
  return { element: article, priced };
}

function renderDiveNearbyRow(container, point, { heading = 'יעדים קרובים' } = {}) {
  if (!container) return 0;
  container.replaceChildren();
  const nearest = nearestCuratedDestinations(point, destinationData, 3);
  if (!nearest.length) {
    container.hidden = true;
    return 0;
  }
  appendTextElement(container, 'strong', heading);
  nearest.forEach(entry => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'dive-nearby-chip';
    button.dataset.diveNearbyDestination = entry.id;
    appendTextElement(button, 'span', entry.city);
    const distance = document.createElement('small');
    const kilometers = document.createElement('bdi');
    kilometers.setAttribute('dir', 'ltr');
    kilometers.textContent = String(entry.distanceKm);
    distance.append(kilometers, document.createTextNode(' ק"מ'));
    button.append(distance);
    container.append(button);
  });
  container.hidden = false;
  return nearest.length;
}

function renderGlobeDiveStore(revealPanel = false) {
  const section = diveStoreSectionFor(globeDiveRoot);
  if (!section) return false;
  const breadcrumb = section.querySelector('[data-dive-breadcrumb]');
  const back = section.querySelector('[data-dive-back]');
  const kicker = section.querySelector('[data-dive-kicker]');
  const title = section.querySelector('[data-dive-title]');
  const meta = section.querySelector('[data-dive-meta]');
  const chips = section.querySelector('[data-dive-chips]');
  const board = section.querySelector('[data-dive-board]');
  const nearby = section.querySelector('[data-dive-nearby]');
  const footnote = section.querySelector('[data-dive-footnote]');
  const live = section.querySelector('[data-dive-live]');
  const state = globeDiveState;
  const destination = state.kind === 'destination' ? destinationData[state.key] : null;
  const hub = state.kind === 'exploration_hub' ? explorationHubData[state.key] : null;
  const resolved = state.depth > 0 && (destination || hub || state.kind === 'map_point');
  if (!resolved) {
    section.hidden = true;
    section.dataset.diveDepth = '0';
    setGlobeDiveDepthAttributes(globeDiveRoot, 0);
    return true;
  }
  section.hidden = false;
  section.dataset.diveDepth = String(state.depth);
  section.dataset.diveKind = state.kind;
  setGlobeDiveDepthAttributes(globeDiveRoot, state.depth);
  renderDiveBreadcrumb(breadcrumb, diveBreadcrumbTrail(state));
  if (back) back.hidden = false;

  let pricedLines = 0;
  let announcement = '';
  if (destination) {
    if (kicker) kicker.textContent = 'צלילה ליעד';
    if (title) title.textContent = `${destination.city}, ${destination.country}`;
    if (meta) meta.textContent = diveDestinationHeroMeta(destination).join(' · ');
    if (state.depth === 1) {
      renderDiveChipRow(chips, diveServiceChips('destination', destination));
      if (chips) chips.hidden = false;
      if (board) { board.hidden = true; board.replaceChildren(); }
      announcement = `${destination.city}: שמונה חלקי חופשה נפתחו מתחת לגלובוס.`;
    } else {
      if (chips) { chips.hidden = true; chips.replaceChildren(); }
      if (board) {
        const cards = diveDestinationCards(destination)
          .concat([diveBundleCard(destination, diveStoreRoutesForDestination(destination.id))])
          .filter(Boolean)
          .map(renderDiveCard);
        pricedLines = cards.filter(card => card.priced).length;
        board.replaceChildren(...cards.map(card => card.element));
        board.hidden = false;
      }
      announcement = `${destination.city}: לוח השירותים המלא נפתח מתחת לגלובוס.`;
    }
    if (nearby) { nearby.hidden = true; nearby.replaceChildren(); }
  } else if (hub) {
    if (kicker) kicker.textContent = 'אזור לגילוי';
    if (title) title.textContent = `${hub.city}, ${hub.country}`;
    if (meta) meta.textContent = 'נקודה מוכרת על הגלובוס · כל חלק ייבדק לפי תאריכים ונוסעים';
    if (state.depth === 1) {
      renderDiveChipRow(chips, diveServiceChips('exploration_hub', hub));
      if (chips) chips.hidden = false;
      if (board) { board.hidden = true; board.replaceChildren(); }
      if (nearby) { nearby.hidden = true; nearby.replaceChildren(); }
      announcement = `${hub.city}: ארבעה חלקי חופשה מרכזיים נפתחו מתחת לגלובוס.`;
    } else {
      if (chips) { chips.hidden = true; chips.replaceChildren(); }
      if (board) {
        const banner = document.createElement('div');
        banner.className = 'dive-banner';
        appendDiveIcon(banner, 'construction');
        appendTextElement(banner, 'span', 'היעד המלא עדיין נבנה אצלנו. דברו עם המתכנן והחופשה תורכב סביב הנקודה שבחרתם.');
        const bannerAction = document.createElement('a');
        bannerAction.className = 'dive-banner-action';
        bannerAction.href = destinationPlanUrl('/ai-planner/', {
          q: `${hub.city}, ${hub.country}`,
          destination: hub.iataSearchCode || hub.id,
          scope: fullTripPlanningScope,
          mode: 'destination',
          intent: activePlanIntent,
          ...discoveryTripContextQuery('ai')
        });
        appendTextElement(bannerAction, 'span', 'דברו עם המתכנן');
        appendDiveIcon(bannerAction, 'arrow-left');
        banner.append(bannerAction);
        const cards = diveHubCards(hub).map(renderDiveCard);
        board.replaceChildren(banner, ...cards.map(card => card.element));
        board.hidden = false;
      }
      renderDiveNearbyRow(nearby, { latitude: hub.latitude, longitude: hub.longitude });
      announcement = `${hub.city}: לוח החלקים המרכזיים נפתח מתחת לגלובוס.`;
    }
  } else {
    const latitude = Number(state.latitude);
    const longitude = Number(state.longitude);
    if (kicker) kicker.textContent = 'נקודה על הגלובוס';
    if (title) {
      title.replaceChildren();
      const coordinates = document.createElement('bdi');
      coordinates.setAttribute('dir', 'ltr');
      coordinates.textContent = `${latitude.toFixed(2)}°, ${longitude.toFixed(2)}°`;
      title.append(coordinates);
    }
    if (meta) meta.textContent = 'הנקודה נשמרה. חקרו את האזור או פתחו ממנה תכנון חופשה מלא.';
    if (chips) {
      chips.replaceChildren();
      const explore = document.createElement('button');
      explore.type = 'button';
      explore.className = 'dive-chip dive-chip-action';
      explore.dataset.diveExplore = 'true';
      explore.dataset.latitude = String(latitude);
      explore.dataset.longitude = String(longitude);
      appendDiveIcon(explore, 'scan-search');
      appendTextElement(explore, 'span', 'חקרו את האזור');
      const planner = document.createElement('a');
      planner.className = 'dive-chip dive-chip-action';
      planner.href = destinationPlanUrl('/ai-planner/', {
        ...activePlanningSelectionQuery(''),
        mode: 'map_point',
        intent: activePlanIntent,
        ...discoveryTripContextQuery('ai'),
        scope: fullTripPlanningScope
      });
      appendDiveIcon(planner, 'sparkles');
      appendTextElement(planner, 'span', 'זהו את האזור ובנו חופשה');
      chips.append(explore, planner);
      chips.hidden = false;
    }
    if (board) { board.hidden = true; board.replaceChildren(); }
    renderDiveNearbyRow(nearby, { latitude, longitude });
    announcement = 'הנקודה נפתחה מתחת לגלובוס עם היעדים הקרובים אליה.';
  }

  // One footnote per panel: it appears only when at least one sample price is
  // visible, and no card carries its own disclaimer.
  if (footnote) footnote.hidden = pricedLines === 0;
  if (live && announcement) live.textContent = announcement;
  renderIcons();
  if (revealPanel && typeof section.scrollIntoView === 'function') {
    section.scrollIntoView({ behavior: preferredScrollBehavior(), block: 'nearest' });
  }
  return true;
}

function applyGlobeDiveState(next, globeRoot, revealPanel = false) {
  const previous = globeDiveState;
  globeDiveState = next;
  if (globeRoot) globeDiveRoot = globeRoot;
  renderGlobeDiveStore(revealPanel && next.depth > 0 && (next.depth > previous.depth || next.key !== previous.key));
}

function syncGlobeDiveStoreRoutes() {
  if (globeDiveState.depth === 2 && globeDiveState.kind === 'destination') renderGlobeDiveStore(false);
}

function diveStoreStepBack() {
  if (!(globeDiveState.depth > 0)) return false;
  applyGlobeDiveState(diveStoreNextState(globeDiveState, { type: 'back' }), globeDiveRoot, false);
  if (globeDiveRoot) window.traVelGlobe3D?.zoom?.('out', { root: globeDiveRoot });
  return true;
}

function diveStoreSwapDestination(destinationId) {
  const key = String(destinationId || '').replace(/[^a-z0-9-]/g, '').slice(0, 60);
  const data = destinationData[key];
  if (!data) return false;
  const pin = globeDiveRoot?.querySelector?.(`.price-pin[data-destination="${CSS.escape(key)}"]`);
  if (pin && typeof pin.click === 'function') {
    // Reuse the exact destination-pin pipeline (selection, hydration, URL).
    pin.click();
    return true;
  }
  discoveryDestinationMode = 'recommended';
  discoveryDestinationLocked = true;
  discoverySelectedPlan = null;
  activeRouteId = '';
  activeRouteSelectionLocked = false;
  setActiveDestination(key, null, { animate: true, responseConfirmed: false, userSelected: true, globeRoot: globeDiveRoot });
  hydrateDiscovery(discoveryRequestParams({ destination: key }));
  applyGlobeDiveState(diveStoreNextState(globeDiveState, {
    type: 'select', kind: 'destination', key, latitude: data.latitude, longitude: data.longitude
  }), globeDiveRoot, false);
  return true;
}

function initGlobeDiveStore() {
  if (!document.querySelector('[data-dive-store]')) return;
  document.addEventListener('travelglobe:select', event => {
    const globeRoot = event.target?.closest?.('[data-globe-3d][data-discovery-globe]');
    if (!globeRoot) return;
    const detail = event.detail || {};
    const kind = diveStorePointKind(detail);
    if (!kind) return;
    const key = diveStoreTargetKey(kind, detail);
    if (!key) return;
    const viaDive = detail.inputType === 'dive';
    applyGlobeDiveState(diveStoreNextState(globeDiveState, {
      type: viaDive ? 'dive' : 'select',
      kind,
      key,
      latitude: detail.latitude,
      longitude: detail.longitude
    }), globeRoot, viaDive);
  });
  document.addEventListener('click', event => {
    const back = event.target?.closest?.('[data-dive-back]');
    if (back && globeDiveState.depth > 0) {
      diveStoreStepBack();
      return;
    }
    const nearbyChoice = event.target?.closest?.('[data-dive-nearby-destination]');
    if (nearbyChoice?.dataset?.diveNearbyDestination) {
      diveStoreSwapDestination(nearbyChoice.dataset.diveNearbyDestination);
      return;
    }
    const explore = event.target?.closest?.('[data-dive-explore]');
    if (explore?.dataset && explore.dataset.diveExplore === 'true') {
      const latitude = Number(explore.dataset.latitude);
      const longitude = Number(explore.dataset.longitude);
      if (Number.isFinite(latitude) && Number.isFinite(longitude)) {
        window.traVelGlobe3D?.focusPoint?.(latitude, longitude, { root: globeDiveRoot });
      }
      return;
    }
    const pin = event.target?.closest?.('.price-pin[data-destination]');
    if (!pin?.dataset?.destination) return;
    const globeRoot = pin.closest?.('[data-globe-3d][data-discovery-globe]');
    if (!globeRoot) return;
    const key = String(pin.dataset.destination).replace(/[^a-z0-9-]/g, '').slice(0, 60);
    const data = destinationData[key];
    if (!data) return;
    applyGlobeDiveState(diveStoreNextState(globeDiveState, {
      type: 'select', kind: 'destination', key, latitude: data.latitude, longitude: data.longitude
    }), globeRoot, false);
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || !(globeDiveState.depth > 0)) return;
    diveStoreStepBack();
  });
}

// --- One-click planning surfaces (theme 1.26.0) ------------------------------
// The owner's law: a tap opens a picker, never a conversation. The tap
// calendar, the party stepper, and the destination chip row let travelers
// complete the search dock and the comparison forms without typing, while the
// existing native inputs stay the only value holders so every current GET
// contract stays identical. No surface here invents a price, a date claim, or
// an availability promise.
const tripCalendarLocale = 'he-IL';
const tripCalendarMonthTitleFormat = new Intl.DateTimeFormat(tripCalendarLocale, { month: 'long', year: 'numeric', timeZone: 'UTC' });
const tripCalendarDayLabelFormat = new Intl.DateTimeFormat(tripCalendarLocale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
const tripCalendarWeekdayFormat = new Intl.DateTimeFormat(tripCalendarLocale, { weekday: 'narrow', timeZone: 'UTC' });
const tripCalendarPresets = Object.freeze({
  weekend: 'סופ״ש הקרוב',
  week: 'שבוע',
  two_weeks: 'שבועיים',
  flexible: 'גמיש ±3 ימים'
});
const tripCalendarFlexibleDays = 3;

function tripDateFromIso(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null;
  const date = new Date(`${value}T12:00:00Z`);
  return Number.isNaN(date.getTime()) ? null : date;
}

function tripDateIso(date) {
  return date instanceof Date && !Number.isNaN(date.getTime()) ? date.toISOString().slice(0, 10) : '';
}

function tripCalendarTodayIso() {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

function tripCalendarMonthStart(iso) {
  const date = tripDateFromIso(iso) || tripDateFromIso(tripCalendarTodayIso()) || new Date();
  return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1, 12));
}

function tripCalendarNextRange(range = {}, dayIso = '', { sameDayAllowed = false, minIso = '' } = {}) {
  const current = {
    start: /^\d{4}-\d{2}-\d{2}$/.test(range?.start || '') ? range.start : '',
    end: /^\d{4}-\d{2}-\d{2}$/.test(range?.end || '') ? range.end : ''
  };
  if (!/^\d{4}-\d{2}-\d{2}$/.test(dayIso) || (minIso && dayIso < minIso)) {
    return { ...current, complete: false, changed: false };
  }
  if (!current.start || (current.start && current.end)) {
    return { start: dayIso, end: '', complete: false, changed: true };
  }
  if (dayIso < current.start) return { start: dayIso, end: '', complete: false, changed: true };
  if (dayIso === current.start && !sameDayAllowed) return { start: current.start, end: '', complete: false, changed: false };
  return { start: current.start, end: dayIso, complete: true, changed: true };
}

function tripCalendarPresetRange(preset, baseIso = tripCalendarTodayIso(), currentStartIso = '') {
  if (preset === 'weekend') {
    const base = tripDateFromIso(baseIso);
    if (!base) return null;
    const offset = (4 - base.getUTCDay() + 7) % 7;
    const start = offset > 0 ? travelDateAfter(baseIso, offset) : baseIso;
    if (!start) return null;
    return { start, end: travelDateAfter(start, 2), complete: true };
  }
  const nights = preset === 'week' ? 7 : (preset === 'two_weeks' ? 14 : 0);
  if (!nights) return null;
  const start = /^\d{4}-\d{2}-\d{2}$/.test(currentStartIso || '') && currentStartIso >= baseIso ? currentStartIso : baseIso;
  const end = travelDateAfter(start, nights);
  return end ? { start, end, complete: true } : null;
}

function commitTripCalendarRange(controls = {}, range = {}) {
  const start = controls.start;
  const end = controls.end;
  if (!start || !end || range?.complete !== true) return false;
  if (!/^\d{4}-\d{2}-\d{2}$/.test(range.start || '') || !/^\d{4}-\d{2}-\d{2}$/.test(range.end || '')) return false;
  start.value = range.start;
  end.value = range.end;
  [start, end].forEach(control => {
    ['input', 'change'].forEach(type => control.dispatchEvent(new Event(type, { bubbles: true })));
  });
  return true;
}

function ensureTripFlexibilityField(form) {
  if (!form) return null;
  const existing = form.querySelector('[data-trip-flexibility]');
  if (existing) return existing;
  const field = document.createElement('input');
  field.type = 'hidden';
  field.name = 'flexibility';
  field.value = '';
  field.disabled = true;
  field.setAttribute('data-trip-flexibility', 'true');
  form.append(field);
  return field;
}

function setTripFlexibility(field, active) {
  if (!field) return false;
  field.value = active ? String(tripCalendarFlexibleDays) : '';
  field.disabled = !field.value;
  return Boolean(field.value);
}

function setupTripDateRangePicker(config = {}) {
  const form = config.form;
  const start = form?.querySelector(config.startSelector || '');
  const end = form?.querySelector(config.endSelector || '');
  if (!form || !start || !end || form.dataset.tripCalendarEnhanced === 'true') return null;
  form.dataset.tripCalendarEnhanced = 'true';
  const sameDayAllowed = typeof config.sameDayAllowed === 'function' ? () => config.sameDayAllowed(form) === true : () => false;
  const minIso = () => {
    const today = tripCalendarTodayIso();
    const declared = String(start.getAttribute('min') || '');
    return declared && declared > today ? declared : today;
  };

  const host = document.createElement('div');
  host.className = 'trip-calendar';
  host.setAttribute('data-trip-calendar', 'true');
  host.setAttribute('role', 'dialog');
  host.setAttribute('aria-modal', 'false');
  host.setAttribute('aria-label', 'בחירת טווח תאריכים');
  host.hidden = true;

  const presetsRow = document.createElement('div');
  presetsRow.className = 'trip-calendar-presets';
  presetsRow.setAttribute('role', 'group');
  presetsRow.setAttribute('aria-label', 'קיצורי טווח תאריכים');
  Object.entries(tripCalendarPresets).forEach(([key, label]) => {
    const chip = document.createElement('button');
    chip.type = 'button';
    chip.setAttribute('data-trip-preset', key);
    chip.setAttribute('aria-pressed', 'false');
    chip.textContent = label;
    presetsRow.append(chip);
  });
  host.append(presetsRow);

  const head = document.createElement('div');
  head.className = 'trip-calendar-head';
  const prevButton = document.createElement('button');
  prevButton.type = 'button';
  prevButton.className = 'trip-calendar-nav';
  prevButton.setAttribute('data-trip-nav', 'prev');
  prevButton.setAttribute('aria-label', 'החודש הקודם');
  prevButton.textContent = 'הקודם';
  const titles = document.createElement('div');
  titles.className = 'trip-calendar-titles';
  const nextButton = document.createElement('button');
  nextButton.type = 'button';
  nextButton.className = 'trip-calendar-nav';
  nextButton.setAttribute('data-trip-nav', 'next');
  nextButton.setAttribute('aria-label', 'החודש הבא');
  nextButton.textContent = 'הבא';
  head.append(prevButton, titles, nextButton);
  host.append(head);

  const monthsHost = document.createElement('div');
  monthsHost.className = 'trip-calendar-months';
  host.append(monthsHost);

  const status = document.createElement('p');
  status.className = 'trip-calendar-status';
  status.setAttribute('data-trip-status', 'true');
  status.setAttribute('role', 'status');
  status.setAttribute('aria-live', 'polite');
  status.setAttribute('aria-atomic', 'true');
  host.append(status);

  const closeButton = document.createElement('button');
  closeButton.type = 'button';
  closeButton.className = 'trip-calendar-close';
  closeButton.setAttribute('data-trip-close', 'true');
  closeButton.textContent = 'סגרו את לוח התאריכים';
  host.append(closeButton);

  if (config.appendToForm === true) {
    form.append(host);
  } else {
    const anchor = form.querySelector(config.anchorSelector || '');
    if (anchor?.parentNode) anchor.parentNode.insertBefore(host, anchor.nextSibling);
    else form.append(host);
  }

  const flexibilityField = ensureTripFlexibilityField(form);
  const state = { open: false, opener: start, pending: { start: '', end: '' }, viewIso: '', focusIso: '' };
  const announce = message => setTextContentIfChanged(status, message);

  const buildMonth = viewDate => {
    const monthWrap = document.createElement('div');
    monthWrap.className = 'trip-calendar-month';
    const weekdays = document.createElement('div');
    weekdays.className = 'trip-calendar-weekdays';
    weekdays.setAttribute('aria-hidden', 'true');
    for (let day = 0; day < 7; day += 1) {
      appendTextElement(weekdays, 'span', tripCalendarWeekdayFormat.format(new Date(Date.UTC(2026, 0, 4 + day, 12))));
    }
    monthWrap.append(weekdays);
    const grid = document.createElement('div');
    grid.className = 'trip-calendar-grid';
    const monthStart = new Date(Date.UTC(viewDate.getUTCFullYear(), viewDate.getUTCMonth(), 1, 12));
    for (let blank = 0; blank < monthStart.getUTCDay(); blank += 1) {
      const spacer = document.createElement('span');
      spacer.className = 'trip-calendar-spacer';
      spacer.setAttribute('aria-hidden', 'true');
      grid.append(spacer);
    }
    const daysInMonth = new Date(Date.UTC(viewDate.getUTCFullYear(), viewDate.getUTCMonth() + 1, 0, 12)).getUTCDate();
    const minimum = minIso();
    const todayIso = tripCalendarTodayIso();
    for (let day = 1; day <= daysInMonth; day += 1) {
      const dayDate = new Date(Date.UTC(viewDate.getUTCFullYear(), viewDate.getUTCMonth(), day, 12));
      const iso = tripDateIso(dayDate);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'trip-calendar-day';
      button.setAttribute('data-trip-day', iso);
      button.tabIndex = -1;
      button.textContent = String(day);
      button.setAttribute('aria-label', tripCalendarDayLabelFormat.format(dayDate));
      if (iso < minimum) {
        button.disabled = true;
        button.setAttribute('aria-disabled', 'true');
      }
      if (iso === todayIso) button.setAttribute('aria-current', 'date');
      const isEdge = iso === state.pending.start || iso === state.pending.end;
      if (isEdge) button.classList.add('is-edge');
      if (state.pending.start && state.pending.end && iso > state.pending.start && iso < state.pending.end) button.classList.add('is-in-range');
      button.setAttribute('aria-pressed', String(isEdge));
      grid.append(button);
    }
    monthWrap.append(grid);
    return monthWrap;
  };

  const render = () => {
    const viewDate = tripCalendarMonthStart(state.viewIso || state.pending.start || minIso());
    const secondMonth = new Date(Date.UTC(viewDate.getUTCFullYear(), viewDate.getUTCMonth() + 1, 1, 12));
    titles.replaceChildren();
    [viewDate, secondMonth].forEach(month => {
      const title = document.createElement('span');
      title.setAttribute('data-trip-month-title', 'true');
      title.textContent = tripCalendarMonthTitleFormat.format(month);
      titles.append(title);
    });
    monthsHost.replaceChildren(buildMonth(viewDate), buildMonth(secondMonth));
    prevButton.disabled = viewDate.getTime() <= tripCalendarMonthStart(tripCalendarTodayIso()).getTime();
    const flexibleChip = presetsRow.querySelector('[data-trip-preset="flexible"]');
    if (flexibleChip) {
      const flexibleActive = Boolean(flexibilityField?.value);
      flexibleChip.setAttribute('aria-pressed', String(flexibleActive));
      flexibleChip.classList.toggle('is-active', flexibleActive);
    }
    const preferred = state.focusIso || state.pending.start || minIso();
    const dayButtons = Array.from(monthsHost.querySelectorAll('[data-trip-day]')).filter(button => !button.disabled);
    const focusCandidate = dayButtons.find(button => button.dataset.tripDay === preferred) || dayButtons[0];
    if (focusCandidate) focusCandidate.tabIndex = 0;
  };

  const close = ({ restoreFocus = true } = {}) => {
    host.hidden = true;
    state.open = false;
    if (restoreFocus) state.opener?.focus?.();
  };

  const open = opener => {
    state.opener = opener || start;
    state.pending = {
      start: /^\d{4}-\d{2}-\d{2}$/.test(start.value || '') ? start.value : '',
      end: /^\d{4}-\d{2}-\d{2}$/.test(end.value || '') ? end.value : ''
    };
    state.viewIso = state.pending.start || minIso();
    state.focusIso = state.pending.start || '';
    render();
    host.hidden = false;
    state.open = true;
    announce(state.pending.start && state.pending.end
      ? 'הטווח הנוכחי מסומן. בחירת תאריך חדש מתחילה טווח אחר.'
      : 'בחרו תאריך התחלה.');
    monthsHost.querySelector('[data-trip-day][tabindex="0"]')?.focus?.();
    host.scrollIntoView({ behavior: preferredScrollBehavior(), block: 'nearest' });
  };

  host.addEventListener('click', event => {
    const preset = event.target?.closest?.('[data-trip-preset]');
    if (preset) {
      if (preset.dataset.tripPreset === 'flexible') {
        const flexibleActive = setTripFlexibility(flexibilityField, !flexibilityField?.value);
        render();
        announce(flexibleActive ? 'סומנו תאריכים גמישים בטווח של עד שלושה ימים.' : 'סימון התאריכים הגמישים הוסר.');
        return;
      }
      const presetRange = tripCalendarPresetRange(preset.dataset.tripPreset, minIso(), state.pending.start || start.value);
      if (!presetRange) return;
      state.pending = { start: presetRange.start, end: presetRange.end };
      commitTripCalendarRange({ start, end }, presetRange);
      state.viewIso = presetRange.start;
      render();
      announce(`הטווח נבחר: ${presetRange.start} עד ${presetRange.end}.`);
      close();
      return;
    }
    const nav = event.target?.closest?.('[data-trip-nav]');
    if (nav) {
      const viewDate = tripCalendarMonthStart(state.viewIso || minIso());
      const shifted = new Date(Date.UTC(viewDate.getUTCFullYear(), viewDate.getUTCMonth() + (nav.dataset.tripNav === 'next' ? 1 : -1), 1, 12));
      const todayMonth = tripCalendarMonthStart(tripCalendarTodayIso());
      state.viewIso = tripDateIso(shifted.getTime() < todayMonth.getTime() ? todayMonth : shifted);
      render();
      return;
    }
    if (event.target?.closest?.('[data-trip-close]')) {
      close();
      return;
    }
    const dayButton = event.target?.closest?.('[data-trip-day]');
    if (!dayButton || dayButton.disabled) return;
    const next = tripCalendarNextRange(state.pending, dayButton.dataset.tripDay, { sameDayAllowed: sameDayAllowed(), minIso: minIso() });
    state.pending = { start: next.start, end: next.end };
    state.focusIso = dayButton.dataset.tripDay;
    if (next.complete) {
      commitTripCalendarRange({ start, end }, next);
      render();
      announce(`הטווח נבחר: ${next.start} עד ${next.end}.`);
      close();
      return;
    }
    render();
    announce(next.start ? 'עכשיו בחרו תאריך סיום.' : 'בחרו תאריך התחלה.');
    monthsHost.querySelector(`[data-trip-day="${state.focusIso}"]`)?.focus?.();
  });

  host.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
      return;
    }
    const dayButton = event.target?.closest?.('[data-trip-day]');
    if (!dayButton) return;
    const arrowDeltas = { ArrowLeft: 1, ArrowRight: -1, ArrowUp: -7, ArrowDown: 7 };
    if (!(event.key in arrowDeltas)) return;
    event.preventDefault();
    const target = travelDateAfter(dayButton.dataset.tripDay, arrowDeltas[event.key]);
    if (!target || target < minIso()) return;
    const viewDate = tripCalendarMonthStart(state.viewIso || minIso());
    const windowStart = tripDateIso(viewDate);
    const windowEnd = tripDateIso(new Date(Date.UTC(viewDate.getUTCFullYear(), viewDate.getUTCMonth() + 2, 0, 12)));
    if (target < windowStart) {
      state.viewIso = target;
    } else if (target > windowEnd) {
      const targetDate = tripDateFromIso(target);
      state.viewIso = tripDateIso(new Date(Date.UTC(targetDate.getUTCFullYear(), targetDate.getUTCMonth() - 1, 1, 12)));
    }
    state.focusIso = target;
    render();
    monthsHost.querySelector(`[data-trip-day="${target}"]`)?.focus?.();
  });

  const openFromControl = event => {
    if (event.type === 'keydown' && !['Enter', ' ', 'ArrowDown'].includes(event.key)) return;
    event.preventDefault();
    open(event.currentTarget);
  };
  [start, end].forEach(control => {
    control.readOnly = true;
    control.setAttribute('aria-haspopup', 'dialog');
    control.setAttribute('data-trip-calendar-opener', 'true');
    control.addEventListener('click', openFromControl);
    control.addEventListener('keydown', openFromControl);
  });

  return { open, close, host };
}

function initTripDateRangePickers() {
  const pickers = [
    { form: document.querySelector('[data-home-search]'), startSelector: '[data-home-departure]', endSelector: '[data-home-return]', appendToForm: true, sameDayAllowed: form => form.dataset.productKind === 'insurance' },
    { form: document.querySelector('[data-flight-search]'), startSelector: 'input[name="departure_date"]', endSelector: 'input[name="return_date"]', anchorSelector: '.flight-date-grid' },
    { form: document.querySelector('[data-hotel-search]'), startSelector: 'input[name="checkin"]', endSelector: 'input[name="checkout"]', anchorSelector: '.hotel-date-grid' },
    { form: document.querySelector('[data-package-search]'), startSelector: 'input[name="departure_date"]', endSelector: 'input[name="return_date"]', anchorSelector: '.package-date-grid' },
    { form: document.querySelector('[data-insurance-quote]'), startSelector: 'input[name="start_date"]', endSelector: 'input[name="end_date"]', anchorSelector: '.insurance-date-grid', sameDayAllowed: () => true }
  ];
  pickers.forEach(config => {
    if (config.form) setupTripDateRangePicker(config);
  });
}

const partyStepperLimits = Object.freeze({
  adults: { min: 1, max: 6 },
  children: { min: 0, max: 4 },
  rooms: { min: 1, max: 3 }
});
const partyStepperRowLabels = Object.freeze({ adults: 'מבוגרים', children: 'ילדים', rooms: 'חדרים' });
const partyStepperStepLabels = Object.freeze({
  adults: { add: 'הוסיפו מבוגר', remove: 'הפחיתו מבוגר' },
  children: { add: 'הוסיפו ילד', remove: 'הפחיתו ילד' },
  rooms: { add: 'הוסיפו חדר', remove: 'הפחיתו חדר' }
});
let partyPanelSequence = 0;

function partyStepperClamp(kind, value) {
  const limits = partyStepperLimits[kind] || { min: 0, max: 9 };
  const numeric = Number.parseInt(String(value), 10);
  if (!Number.isInteger(numeric)) return limits.min;
  return Math.min(limits.max, Math.max(limits.min, numeric));
}

function partyStepperSummary(adults, children, rooms = null) {
  const parts = [
    adults === 1 ? 'מבוגר אחד' : `${adults} מבוגרים`,
    children === 0 ? 'ללא ילדים' : (children === 1 ? 'ילד אחד' : `${children} ילדים`)
  ];
  if (Number.isInteger(rooms) && rooms > 0) parts.push(rooms === 1 ? 'חדר 1' : `${rooms} חדרים`);
  return parts.join(' · ');
}

function renderPartyChildAges(host, count) {
  if (!host) return 0;
  const total = Math.max(0, Math.min(partyStepperLimits.children.max, Number(count) || 0));
  const previous = Array.from(host.querySelectorAll('[data-party-child-age]')).map(select => select.value);
  host.replaceChildren();
  for (let index = 0; index < total; index += 1) {
    const label = document.createElement('label');
    label.className = 'party-child-age';
    appendTextElement(label, 'span', `גיל ילד ${index + 1}`);
    const select = document.createElement('select');
    select.setAttribute('data-party-child-age', String(index + 1));
    select.setAttribute('aria-label', `גיל ילד ${index + 1}`);
    for (let age = 0; age <= 17; age += 1) {
      const option = document.createElement('option');
      option.value = String(age);
      option.textContent = String(age);
      select.append(option);
    }
    if (previous[index] !== undefined) select.value = previous[index];
    label.append(select);
    host.append(label);
  }
  host.hidden = total === 0;
  return total;
}

function syncPartyChildAgesField(form, host) {
  // Ages feed an existing form field only when one exists; the theme has no
  // child-age GET contract today, so without one the ages stay local UI.
  const field = form?.querySelector('input[name="child_ages"]');
  if (!field || !host) return false;
  field.value = Array.from(host.querySelectorAll('[data-party-child-age]')).map(select => select.value).join(',');
  return true;
}

function setupPartyStepper(form) {
  if (!form || form.dataset.partyEnhanced === 'true') return null;
  const selects = {
    adults: form.querySelector('select[name="adults"]'),
    children: form.querySelector('select[name="children"]'),
    rooms: form.querySelector('select[name="rooms"]')
  };
  if (!selects.adults || !selects.children) return null;
  const adultsLabel = selects.adults.closest('label');
  const host = adultsLabel?.parentElement;
  if (!adultsLabel || !host) return null;
  form.dataset.partyEnhanced = 'true';
  ['adults', 'children', 'rooms'].forEach(kind => {
    selects[kind]?.closest('label')?.setAttribute('data-party-hidden', 'true');
  });

  partyPanelSequence += 1;
  const panelId = `party-panel-${partyPanelSequence}`;
  const pill = document.createElement('button');
  pill.type = 'button';
  pill.className = 'party-pill';
  pill.setAttribute('data-party-pill', 'true');
  pill.setAttribute('aria-expanded', 'false');
  pill.setAttribute('aria-controls', panelId);
  const summary = document.createElement('span');
  summary.className = 'party-pill-summary';
  summary.setAttribute('data-party-summary', 'true');
  pill.append(summary);
  const caret = document.createElement('span');
  caret.className = 'party-pill-caret';
  caret.setAttribute('aria-hidden', 'true');
  caret.textContent = '▾';
  pill.append(caret);
  host.insertBefore(pill, adultsLabel);

  const panel = document.createElement('div');
  panel.className = 'party-panel';
  panel.id = panelId;
  panel.setAttribute('data-party-panel', 'true');
  panel.hidden = true;
  const rowsHost = document.createElement('div');
  rowsHost.className = 'party-rows';
  panel.append(rowsHost);
  const agesHost = document.createElement('div');
  agesHost.className = 'party-child-ages';
  agesHost.setAttribute('data-party-child-ages', 'true');
  agesHost.hidden = true;
  panel.append(agesHost);

  const rows = {};
  ['adults', 'children', 'rooms'].forEach(kind => {
    const select = selects[kind];
    if (!select) return;
    const row = document.createElement('div');
    row.className = 'party-row';
    row.setAttribute('data-party-row', kind);
    appendTextElement(row, 'span', partyStepperRowLabels[kind], 'party-row-label');
    const controlsWrap = document.createElement('div');
    controlsWrap.className = 'party-row-controls';
    const plus = document.createElement('button');
    plus.type = 'button';
    plus.setAttribute('data-party-step', kind);
    plus.setAttribute('data-step-delta', '1');
    plus.setAttribute('aria-label', partyStepperStepLabels[kind].add);
    plus.textContent = '+';
    const value = document.createElement('b');
    value.setAttribute('data-party-value', kind);
    const minus = document.createElement('button');
    minus.type = 'button';
    minus.setAttribute('data-party-step', kind);
    minus.setAttribute('data-step-delta', '-1');
    minus.setAttribute('aria-label', partyStepperStepLabels[kind].remove);
    minus.textContent = '−';
    controlsWrap.append(plus, value, minus);
    row.append(controlsWrap);
    rowsHost.append(row);
    rows[kind] = { row, value, plus, minus, select };
  });

  if (form.matches('[data-home-search]')) form.append(panel);
  else if (host.parentNode) host.parentNode.insertBefore(panel, host.nextSibling);
  else form.append(panel);

  const refresh = () => {
    const adults = partyStepperClamp('adults', selects.adults.value);
    const children = partyStepperClamp('children', selects.children.value);
    const roomsUsable = Boolean(selects.rooms) && !selects.rooms.disabled;
    const rooms = roomsUsable ? partyStepperClamp('rooms', selects.rooms.value) : null;
    setTextContentIfChanged(summary, partyStepperSummary(adults, children, rooms));
    Object.entries(rows).forEach(([kind, parts]) => {
      const usable = kind !== 'rooms' || roomsUsable;
      parts.row.hidden = !usable;
      if (!usable) return;
      const current = partyStepperClamp(kind, parts.select.value);
      setTextContentIfChanged(parts.value, String(current));
      parts.minus.disabled = current <= partyStepperLimits[kind].min;
      parts.plus.disabled = current >= partyStepperLimits[kind].max;
    });
    if (String(children) !== String(agesHost.dataset.count || '')) {
      renderPartyChildAges(agesHost, children);
      agesHost.dataset.count = String(children);
      syncPartyChildAgesField(form, agesHost);
    }
  };

  const step = (kind, delta) => {
    const select = selects[kind];
    if (!select || select.disabled) return;
    const next = partyStepperClamp(kind, partyStepperClamp(kind, select.value) + delta);
    if (String(next) === String(select.value)) return;
    select.value = String(next);
    ['input', 'change'].forEach(type => select.dispatchEvent(new Event(type, { bubbles: true })));
    refresh();
  };

  panel.addEventListener('click', event => {
    const button = event.target?.closest?.('[data-party-step]');
    if (!button || button.disabled) return;
    step(button.dataset.partyStep, Number(button.dataset.stepDelta) || 0);
  });
  panel.addEventListener('change', event => {
    if (event.target?.closest?.('[data-party-child-age]')) syncPartyChildAgesField(form, agesHost);
  });
  panel.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    event.preventDefault();
    panel.hidden = true;
    pill.setAttribute('aria-expanded', 'false');
    pill.focus();
  });
  pill.addEventListener('click', () => {
    const opening = panel.hidden;
    panel.hidden = !opening;
    pill.setAttribute('aria-expanded', String(opening));
    if (opening) refresh();
  });
  form.addEventListener('change', refresh);
  if (form.matches('[data-home-search]')) {
    document.querySelectorAll('.product-tabs [role="tab"][data-product-kind]').forEach(tab => {
      tab.addEventListener('click', refresh);
      tab.addEventListener('keydown', () => window.setTimeout(refresh, 0));
    });
  }
  refresh();
  return { pill, panel, refresh };
}

function initPartySteppers() {
  ['[data-home-search]', '[data-flight-search]', '[data-hotel-search]', '[data-package-search]', '[data-insurance-quote]']
    .forEach(selector => setupPartyStepper(document.querySelector(selector)));
}

function homeDestinationChipSelectedSlug(select) {
  if (!select) return '';
  const option = select.selectedOptions?.[0] || Array.from(select.options || []).find(item => item?.value === select.value);
  return option?.dataset?.slug || '';
}

function syncHomeDestinationChipStates(select, chips = []) {
  const slug = homeDestinationChipSelectedSlug(select);
  chips.forEach(chip => {
    const active = Boolean(slug) && chip.dataset.chipSlug === slug;
    chip.classList.toggle('is-active', active);
    chip.setAttribute('aria-pressed', String(active));
  });
  return slug;
}

function applyHomeDestinationChip(select, chip) {
  if (!select || !chip?.dataset?.chipSlug) return false;
  const option = Array.from(select.options || []).find(item => item?.dataset?.slug === chip.dataset.chipSlug);
  if (!option) return false;
  select.value = option.value;
  ['input', 'change'].forEach(type => select.dispatchEvent(new Event(type, { bubbles: true })));
  return true;
}

function initHomeDestinationChips() {
  const row = document.querySelector('[data-home-destination-chips]');
  const select = document.querySelector('[data-home-destination]');
  if (!row || !select) return;
  const chips = Array.from(row.querySelectorAll('[data-destination-chip]'));
  if (!chips.length) return;
  chips.forEach(chip => chip.addEventListener('click', () => {
    if (applyHomeDestinationChip(select, chip)) syncHomeDestinationChipStates(select, chips);
  }));
  select.addEventListener('change', () => syncHomeDestinationChipStates(select, chips));
  syncHomeDestinationChipStates(select, chips);
}

function initMapPlanScopeFocus() {
  // A dive-store chip lands on the destination's plan with the matching
  // module already open, so the tap ends on a picker instead of a chat.
  if (!isMapWorkspacePage()) return;
  const params = new URLSearchParams(window.location.search);
  const destination = String(params.get('destination') || '').trim();
  const scopeKeys = String(params.get('scope') || '').split(',').map(value => value.trim()).filter(Boolean).slice(0, 12);
  if (!destination || !scopeKeys.length || window.location.hash) return;
  const plan = document.querySelector('[data-destination-plan]');
  if (!plan) return;
  const moduleKeys = scopeKeys.filter(key => /^[a-z_-]{1,40}$/.test(key) && plan.querySelector(`[data-plan-module="${CSS.escape(key)}"]`));
  if (moduleKeys.length && scopeKeys.length <= 2) {
    plan.querySelectorAll('[data-plan-module]').forEach(module => {
      module.open = moduleKeys.includes(module.dataset.planModule);
    });
  }
  const anchor = document.getElementById('destination-plan-title') || plan;
  window.setTimeout(() => anchor.scrollIntoView({ behavior: preferredScrollBehavior(), block: 'start' }), 80);
}

function initTraVelV2() {
  if (document.documentElement.dataset.traVelV2Ready === 'true') return;
  document.documentElement.dataset.traVelV2Ready = 'true';
  captureAcquisition();
  readDiscoveryStateFromUrl();
  explorationHubData = explorationHubsFromDom();
  window.traVelGlobe3D?.setExplorationHubs(explorationHubData);
  const heroCampaign = document.querySelector('[data-hero-campaign]');
  const homeGlobe = document.querySelector('[data-home-globe]');
  const initialActivePin = document.querySelector('.price-pin.is-active[data-destination]');
  const tripIntentQuery = new URLSearchParams(window.location.search);
  const hasRequestedDestination = tripIntentQuery.has('destination');
  const hasKnownTripIntent = hasKnownTripIntentQuery(tripIntentQuery);
  const openEndedDestination = discoveryDestinationMode === 'anywhere';
  const restoredFreePoint = isMapWorkspacePage() && activeFreePlanningPoint();
  const mapGlobe = document.querySelector('.theme-map-shell [data-globe-3d][data-discovery-globe]');
  const serverHomeDestination = String(homeGlobe?.dataset.defaultDestination || '').toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 60);
  if (!restoredFreePoint && !openEndedDestination && !hasRequestedDestination && serverHomeDestination) {
    activeDestination = serverHomeDestination;
  } else if (!restoredFreePoint && !openEndedDestination && !hasRequestedDestination && heroCampaign?.dataset.mapState && destinationData[heroCampaign.dataset.mapState]) {
    activeDestination = heroCampaign.dataset.mapState;
  } else if (!restoredFreePoint && !openEndedDestination && !hasRequestedDestination && initialActivePin?.dataset.destination && destinationData[initialActivePin.dataset.destination]) {
    activeDestination = initialActivePin.dataset.destination;
  }
  renderIcons();
  initHomeRouteExamples();
  initNavigation();
  initMap();
  initGlobePointSelection();
  initGlobeDiveStore();
  if (restoredFreePoint) {
    const restoredHub = explorationHubForPoint(activePlanningSelection.latitude, activePlanningSelection.longitude);
    const restoredDetail = {
      selectionId: activePlanningSelection.selection_id,
      latitude: activePlanningSelection.latitude,
      longitude: activePlanningSelection.longitude,
      inputType: 'restore'
    };
    if (restoredHub) renderExplorationHubSelection({
      ...restoredDetail,
      supported: true,
      selectionKind: 'exploration_hub',
      hubId: restoredHub.id,
      hubCity: restoredHub.city,
      hubCountry: restoredHub.country,
      hubIataSearchCode: restoredHub.iataSearchCode,
      hubLiveSearchScopes: restoredHub.liveSearchScopes,
      hubDistanceKm: Math.round(restoredHub.distanceKm)
    }, mapGlobe);
    else renderUnsupportedGlobeSelection(restoredDetail, mapGlobe);
  } else if (openEndedDestination) renderDiscoveryEmptyState({ reason: 'open' });
  syncDiscoveryTripContext();
  initDestinationPlan();
  initHomeDiscoverySearch();
  initHomeDestinationChips();
  initTripDateRangePickers();
  initPartySteppers();
  initMapPlanScopeFocus();
  initControls();
  initAIConversationEntry();
  initDirectory();
  initExperienceDecisionMap();
  initFlightSearch();
  initHotelSearch();
  initInsuranceQuote();
  initTripPackageSearch();
  initTravelerWorkspace();
  syncDiscoveryControls();
  syncDiscoveryUrl('replace');
  const initialDiscoveryRequest = homeGlobe && activeDestination && !openEndedDestination
    ? discoveryRequestParams({ destination: activeDestination })
    : discoveryRequestParams();
  const initialHydration = !restoredFreePoint
    ? hydrateDiscovery(initialDiscoveryRequest, { allowGlobeFocus: !homeGlobe, allowConfirmedMotion: !homeGlobe })
    : Promise.resolve();
  initHomeDestinationReveal(initialHydration, {
    autoEligible: Boolean(homeGlobe) && !hasRequestedDestination && !openEndedDestination && !restoredFreePoint && !hasKnownTripIntent
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initTraVelV2, { once: true });
} else {
  initTraVelV2();
}
