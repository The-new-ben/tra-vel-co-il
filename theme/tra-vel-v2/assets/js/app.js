const destinationAssetBase = window.traVelV2?.assetUrl || './assets/';
const fallbackDestinations = {
  bangkok: { id: 'bangkok', city: 'בנגקוק', country: 'תאילנד', price: 'בדיקה חיה', total: 'בדיקה חיה', note: 'מחיר ותנאים יאומתו בחיפוש', image: `${destinationAssetBase}thailand.jpg`, tags: ['12 לילות', 'עצירה אחת', 'בדיקת כבודה'], airport: 'BKK · לפי המסלול', hotel: 'Siam · אזור לינה', weather: 'לפי התאריך', latitude: 13.7563, longitude: 100.5018, x: 72, y: 61 },
  athens: { id: 'athens', city: 'אתונה', country: 'יוון', price: 'בדיקה חיה', total: 'בדיקה חיה', note: 'מחיר ותנאים יאומתו בחיפוש', image: `${destinationAssetBase}athens-acropolis.jpg`, tags: ['מסלול ישיר', '3 לילות', 'ביטול גמיש'], airport: 'ATH · לפי המסלול', hotel: 'Plaka · אזור לינה', weather: 'לפי התאריך', latitude: 37.9838, longitude: 23.7275, x: 48, y: 43 },
  budapest: { id: 'budapest', city: 'בודפשט', country: 'הונגריה', price: 'בדיקה חיה', total: 'בדיקה חיה', note: 'מחיר ותנאים יאומתו בחיפוש', image: `${destinationAssetBase}city-budapest.webp`, tags: ['מסלול ישיר', '4 לילות', 'בדיקת ארוחה'], airport: 'BUD · לפי המסלול', hotel: 'District V · אזור לינה', weather: 'לפי התאריך', latitude: 47.4979, longitude: 19.0402, x: 43, y: 32 },
  dubai: { id: 'dubai', city: 'דובאי', country: 'איחוד האמירויות', price: 'בדיקה חיה', total: 'בדיקה חיה', note: 'מחיר ותנאים יאומתו בחיפוש', image: `${destinationAssetBase}hero-budapest-900.webp`, tags: ['מסלול ישיר', 'סוף שבוע', 'בדיקת כבודה'], airport: 'DXB · לפי המסלול', hotel: 'Creek · אזור לינה', weather: 'לפי התאריך', latitude: 25.2048, longitude: 55.2708, x: 59, y: 53 },
  tokyo: { id: 'tokyo', city: 'טוקיו', country: 'יפן', price: 'בדיקה חיה', total: 'בדיקה חיה', note: 'מחיר ותנאים יאומתו בחיפוש', image: `${destinationAssetBase}city-prague.webp`, tags: ['עצירה אחת', '10 לילות', 'בדיקת כבודה'], airport: 'HND · לפי המסלול', hotel: 'Shinjuku · אזור לינה', weather: 'לפי התאריך', latitude: 35.6762, longitude: 139.6503, x: 84, y: 39 },
  lisbon: { id: 'lisbon', city: 'ליסבון', country: 'פורטוגל', price: 'בדיקה חיה', total: 'בדיקה חיה', note: 'מחיר ותנאים יאומתו בחיפוש', image: `${destinationAssetBase}city-prague.webp`, tags: ['7 לילות', 'עצירה אחת', 'בחירת אזור'], airport: 'LIS · לפי המסלול', hotel: 'Baixa · אזור לינה', weather: 'לפי התאריך', latitude: 38.7223, longitude: -9.1393, x: 29, y: 43 }
};

let destinationData = { ...fallbackDestinations };
let discoveryRoutes = [];
let discoverySelectedPlan = null;
let activeRouteId = '';
let activeRouteSelectionLocked = false;
let activeLayer = 'deals';
let activeDestination = 'bangkok';
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
const discoveryLayers = new Set(['deals', 'hotels', 'airports', 'weather']);
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
const destinationPlanIntents = {
  smart: { label: 'החכמה', summary: 'מאזנים זמן, נוחות, גמישות ועלות מלאה לפני שבוחרים.' },
  value: { label: 'המשתלמת', summary: 'בודקים מה מקבלים בכל שקל ולא מסתפקים במחיר הכותרת.' },
  easy: { label: 'הקלה', summary: 'מצמצמים החלפות, נסיעות וסיכון כדי לפשט את כל הדרך.' },
  romantic: { label: 'הזוגית', summary: 'בונים קצב רגוע, אזור לינה נכון וחוויות שמתאימות לשניים.' },
  family: { label: 'המשפחתית', summary: 'מתעדפים חדר נכון, מרחקים קצרים, גמישות וביטוח מתאים.' },
  adventure: { label: 'ההרפתקנית', summary: 'משלבים טבע, פעילות וציוד בלי לוותר על בטיחות ולוגיסטיקה.' },
  surprise: { label: 'המפתיעה', summary: 'נותנים לסוכן להציע חלופה יצירתית במסגרת הכוונה והתקציב.' }
};
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
const directoryDestinationIds = new Set(['budapest', 'prague', 'vienna', 'thailand', 'athens', 'tokyo']);

function renderIcons() {
  if (window.lucide) window.lucide.createIcons({ attrs: { 'stroke-width': 1.8 } });
}

function prefersReducedMotion() {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
}

function preferredScrollBehavior() {
	return prefersReducedMotion() ? 'auto' : 'smooth';
}

function discoverySnapshotIsCurrent() {
  return discoveryFreshness === 'current' && ['fresh', 'miss'].includes(discoveryCacheState);
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
  if (discoveryCacheFreshness === 'refreshing') return 'מוצג צילום קודם בזמן רענון הספק';
  if (discoveryCacheFreshness === 'stale') return 'מוצג צילום קודם לאחר כשל ברענון הספק';
  if (discoveryCacheFreshness === 'fallback') return 'נתוני ספק אינם זמינים כרגע';
  const sourceLabels = {
    stale: 'נתוני הספק התקבלו, אך התצפית ישנה ודורשת רענון',
    future: 'חותמת הזמן של הספק אינה תקינה ודורשת אימות',
    unknown: 'חסר זמן תצפית אמין; נדרש אימות מחדש'
  };
  return sourceLabels[discoverySourceFreshness] || 'נדרש אימות מחדש';
}

function budgetCoverageLabel() {
  if (!(Number(discoveryQuery.budget) > 0)) return '';
  if (discoveryBudgetCoverage === 'full' && discoveryBudgetApplied) return 'התקציב הוחל על כל היעדים עם מחיר ספק נוכחי';
  if (discoveryBudgetCoverage === 'partial' && discoveryBudgetFilterActive) return 'סינון התקציב חלקי: הוא הוחל רק על יעדים עם מחיר ספק נוכחי';
  return 'התקציב עדיין לא סינן יעדים: אין כיסוי מחירים נוכחי';
}

function updateBudgetCoverageStatus(mode = 'settled') {
  const status = document.querySelector('[data-budget-coverage]');
  if (!status) return;
  status.dataset.coverage = mode === 'loading' ? 'loading' : discoveryBudgetCoverage;
  status.textContent = mode === 'loading' ? 'בודקים על אילו יעדים אפשר להחיל את התקציב לפי מחירי ספק נוכחיים.' : budgetCoverageLabel();
}

function clampDiscoveryNumber(value, minimum, maximum, fallback) {
  const number = Number(value);
  return Number.isFinite(number) ? Math.min(maximum, Math.max(minimum, Math.round(number))) : fallback;
}

function isMapWorkspacePage() {
  return Boolean(document.querySelector('.theme-map-shell'));
}

function readDiscoveryStateFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const layer = params.get('layer');
  const intent = params.get('intent');
  const sort = params.get('sort');
  const trip = params.get('trip');
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
  const destination = String(params.get('destination') || '').toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 60);
  discoveryDestinationLocked = Boolean(destination);
  if (destination) activeDestination = destination;
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
  return directoryDestinationIds.has(directoryId)
    ? destinationPlanUrl('/guides/', { destination: directoryId, ...params })
    : destinationPlanUrl('/guides/', params);
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
}

function syncDiscoveryUrl(mode = 'push') {
  if (!isMapWorkspacePage()) return;
  const url = new URL(window.location.href);
  const keys = ['destination', 'layer', 'intent', 'q', 'budget', 'direct', 'sort', 'trip', 'max_stops', 'max_duration', 'allow_overnight'];
  keys.forEach(key => url.searchParams.delete(key));
  if (discoveryDestinationLocked && activeDestination) url.searchParams.set('destination', activeDestination);
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
  const method = mode === 'replace' ? 'replaceState' : 'pushState';
  window.history[method]({ traVelMap: true, focus: activeDestination || '' }, '', `${url.pathname}${url.search}${url.hash}`);
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
    discoveryDestinationLocked = true;
    discoverySelectedPlan = null;
    activeRouteId = '';
    activeRouteSelectionLocked = false;
    setActiveDestination(pin.dataset.destination, pin, { animate: true, responseConfirmed: false });
    syncDiscoveryUrl('push');
    hydrateDiscovery(discoveryRequestParams({ destination: pin.dataset.destination }));
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

function setActiveDestination(key, pin, motion = true) {
  const data = destinationData[key];
  if (!data) return;
  const animatePlan = typeof motion === 'object' ? motion.animate !== false : Boolean(motion);
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
      ? [`${data.nights} לילות`, 'צילום ספק קודם', 'נדרש אימות מחדש']
      : ['מדריך ליעד', 'אזורי לינה', 'מסלולים אפשריים']);
  const layerStates = {
    deals: {
      label: 'מחיר וזמינות',
      total: hasLiveDealPrices || (snapshotStale && hasDealSnapshot) ? data.total : 'בדיקה חיה',
      price: hasLiveDealPrices || (snapshotStale && hasDealSnapshot) ? data.price : 'בדיקת מחיר',
      note: hasLiveDealPrices
        ? 'מחיר ההצעה התקבל מספק מחובר. זמינות, הרכב ותנאים יאומתו לפני אישור.'
        : (snapshotStale && hasDealSnapshot ? 'זהו צילום הספק האחרון, לא הצעה נוכחית. נדרש אימות מחיר וזמינות מחדש.' : 'מחיר וזמינות יוצגו אחרי בחירת תאריכים והרכב.')
    },
    hotels: {
      label: 'לינה באזור הנכון',
      total: hasLiveHotelPrices || (snapshotStale && hasHotelSnapshot) ? (data.hotelPrice || data.total) : 'בדיקת מלונות',
      price: data.hotelArea || 'אזור לינה',
      note: hasLiveHotelPrices
        ? 'מחיר החדר התקבל מספק מחובר. מסים, מלאי, ביטול ותנאים יאומתו בהצעה מתוארכת.'
        : (snapshotStale && hasHotelSnapshot ? 'מחיר החדר הוא מתצפית ספק קודמת. מלאי, מסים וביטול דורשים רענון.' : 'מחיר חדר, מסים וביטול יוצגו אחרי בחירת תאריכים.')
    },
    airports: {
      label: 'שדה ודרך',
      total: data.airportCode || 'בדיקת שדה',
      price: data.airportDirect ? 'מסלול ישיר אפשרי' : 'נדרש קונקשן',
      note: 'זמן, כבודה ותנאי כרטיס יאומתו בחיפוש טיסות.'
    },
    weather: {
      label: 'מזג אוויר ועונה',
      total: hasLiveWeather || (snapshotStale && hasWeatherSnapshot) ? data.weather : 'לפי תאריך',
      price: hasLiveWeather || (snapshotStale && hasWeatherSnapshot) ? (data.weatherCondition || 'תצפית קודמת') : 'בחרו מועד',
      note: hasLiveWeather
        ? (hasLiveSeason ? `התאמת עונה מעודכנת: ${data.seasonFit || 'לפי מסלול'}.` : 'התנאים הנוכחיים עודכנו. התאמת העונה תיבדק לפי תאריך הנסיעה.')
        : (snapshotStale && hasWeatherSnapshot ? 'מוצגת תצפית קודמת. זו אינה תחזית למועד הנסיעה ונדרש רענון.' : 'תחזית תוצג רק למועד נסיעה מוגדר.')
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
  pin?.classList.add('is-active');
  window.traVelGlobe3D?.focusDestination(key, { animate: globeAnimate, pulse: globePulse });

  document.querySelectorAll('[data-map-result]').forEach(card => {
    card.dataset.destination = key;
    card.removeAttribute('data-empty');
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
      '[data-result-guide]': data.url || destinationPlanUrl('/destinations/', { destination: data.id }),
      '[data-result-hotels]': destinationPlanUrl('/hotels/', { destination: data.airportCode || '', area: data.hotelArea || '' }),
      '[data-result-insurance]': destinationPlanUrl('/travel-insurance/', { trip_destination: data.id })
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
  const homeRouteBoard = document.querySelector('[data-home-route-board]');
  const homeRouteEmpty = document.querySelector('[data-home-route-empty]');
  if (homeRouteBoard && homeRouteEmpty) {
    const hasHomepageComparison = key === 'bangkok';
    homeRouteBoard.hidden = !hasHomepageComparison;
    homeRouteEmpty.hidden = hasHomepageComparison;
  }
  updateHomeDestinationPlan(data, animatePlan && responseConfirmed);
  updateDestinationPlan(data, animatePlan, responseState);
  updateGlobeSelectionRail(data, { animate: animatePlan });
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
    '[data-plan-cover-title]': 'הכיסוי יותאם לאחר זיהוי המסלול',
    '[data-plan-cover] em': 'נוסעים, בריאות, פעילות וכבודה',
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
    if (title) title.textContent = moduleNames[module.dataset.planModule] || 'תחום החלטה';
  });
  const save = plan.querySelector('[data-plan-save]');
  if (save) {
    save.disabled = true;
    save.classList.remove('is-saved');
  }
}

function renderDiscoveryEmptyState() {
  activeDestination = '';
  activeRouteId = '';
  activeRouteSelectionLocked = false;
  discoverySelectedPlan = null;
  window.traVelGlobe3D?.clearSelection();
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
      '[data-result-city]': 'לא נמצא יעד שתואם לבחירות',
      '[data-result-state-label]': 'תוצאת הסינון',
      '[data-result-price]': 'נדרש שינוי סינון',
      '[data-result-total]': 'אין תוצאה',
      '[data-result-note]': 'נסו להרחיב תקציב, גמישות או יעדים.',
      '[data-result-airport]': 'שדות תעופה',
      '[data-result-hotel]': 'אזורי לינה',
      '[data-result-weather]': 'מזג אוויר'
    };
    Object.entries(fields).forEach(([selector, value]) => {
      const field = card.querySelector(selector);
      if (field) field.textContent = value;
    });
    replaceChildrenWithSpans(card.querySelector('[data-result-tags]'), ['הרחיבו תקציב', 'שנו תאריכים', 'פתחו יעדים']);
    const saveButton = card.querySelector('.save-button');
    if (saveButton) {
      saveButton.disabled = true;
      saveButton.classList.remove('is-saved');
      saveButton.setAttribute('aria-label', 'אין יעד זמין לשמירה');
    }
    const fallbackLinks = {
      '[data-result-guide]': destinationPlanUrl('/destinations/'),
      '[data-result-hotels]': destinationPlanUrl('/hotels/'),
      '[data-result-insurance]': destinationPlanUrl('/travel-insurance/')
    };
    Object.entries(fallbackLinks).forEach(([selector, href]) => {
      const link = card.querySelector(selector);
      if (link) link.href = href;
    });
  });
  const routeTitle = document.querySelector('[data-route-title]');
  if (routeTitle) routeTitle.textContent = 'אין מסלול עד שבוחרים יעד תואם';
  const homeRouteBoard = document.querySelector('[data-home-route-board]');
  const homeRouteEmpty = document.querySelector('[data-home-route-empty]');
  if (homeRouteBoard && homeRouteEmpty) {
    homeRouteBoard.hidden = true;
    homeRouteEmpty.hidden = false;
  }
  const homePlanSummary = document.querySelector('[data-home-plan-summary]');
  if (homePlanSummary) homePlanSummary.textContent = 'שנו את הבחירות כדי לפתוח תוכנית 360° ליעד מתאים.';
  document.querySelectorAll('[data-home-plan-flight],[data-home-plan-stay],[data-home-plan-guide],[data-home-plan-ai],[data-home-plan-full]').forEach(link => {
    link.setAttribute('aria-disabled', 'true');
    link.removeAttribute('href');
  });
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
    if (title) title.textContent = 'התוכנית מחכה ליעד מתאים';
    if (state) state.textContent = 'שנו את הבחירות כדי להמשיך';
    if (summary) summary.textContent = 'לא נשאיר מידע ישן מתחת למפה כאשר אין תוצאה תואמת.';
    plan.querySelectorAll('[data-plan-flight],[data-plan-stay],[data-plan-experience],[data-plan-weather],[data-plan-cover],[data-plan-total],[data-plan-guide]').forEach(link => {
      link.setAttribute('aria-disabled', 'true');
      link.tabIndex = -1;
      link.removeAttribute('href');
    });
    const ai = plan.querySelector('[data-plan-ai]');
    if (ai) ai.href = destinationPlanUrl('/ai-planner/', { mode: 'surprise' });
    const save = plan.querySelector('[data-plan-save]');
    if (save) save.disabled = true;
    const meter = plan.querySelector('[data-plan-meter]');
    if (meter) {
      meter.setAttribute('aria-valuenow', '0');
      meter.setAttribute('aria-valuetext', '0 תחומים מופו; אין יעד; אין הזמנה מאושרת');
      const count = meter.querySelector('[data-plan-meter-count]');
      const fill = meter.querySelector('[data-plan-meter-fill]');
      if (count) count.textContent = '0/12';
      if (fill) fill.style.setProperty('--plan-coverage', '0%');
    }
    const coverageCopy = plan.querySelector('[data-plan-coverage-copy]');
    if (coverageCopy) coverageCopy.textContent = 'אין כיסוי פעיל. 12 תחומי ההחלטה ייפתחו מחדש לאחר בחירת יעד.';
    plan.querySelectorAll('[data-plan-module]').forEach(module => {
      module.dataset.state = 'unknown';
      const moduleState = module.querySelector('[data-plan-module-state]');
      const moduleDetail = module.querySelector('[data-plan-module-detail]');
      const moduleAction = module.querySelector('[data-plan-module-action]');
      if (moduleState) moduleState.textContent = 'ממתין ליעד';
      if (moduleDetail) moduleDetail.textContent = 'לא נשאיר כאן פרטים מהיעד הקודם. בחרו יעד או בקשו מהסוכן להציע אחד.';
      if (moduleAction) {
        moduleAction.href = destinationPlanUrl('/ai-planner/', { mode: 'surprise', scope: module.dataset.planModule || '' });
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
    if (ledgerTruth) ledgerTruth.textContent = 'אין מחיר, חיסכון או הזמנה עד לבחירת יעד וחיפוש ספקים בר-השוואה.';
    const planTruth = plan.querySelector('[data-plan-truth]');
    if (planTruth) planTruth.textContent = 'אין מידע ישן, מחיר או הזמנה פעילה. בחרו יעד כדי להתחיל מחדש.';
  }
  const rail = document.querySelector('[data-globe-selection]');
  if (rail) {
    rail.dataset.state = 'empty';
    const kicker = rail.querySelector('[data-globe-selection-kicker]');
    const title = rail.querySelector('[data-globe-selection-title]');
    const detail = rail.querySelector('[data-globe-selection-detail]');
    const action = rail.querySelector('[data-globe-selection-action]');
    if (kicker) kicker.textContent = 'אין תוצאה פעילה';
    if (title) title.textContent = 'אין כרגע יעד שתואם לבחירות';
    if (detail) detail.textContent = 'הרחיבו תקציב, גמישות או חיפוש כדי לפתוח שוב את תוכנית 360°.';
    if (action) {
      action.href = destinationPlanUrl('/ai-planner/', { mode: 'surprise' });
      action.firstChild.textContent = 'תפתיעו אותי';
    }
  }
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
  stale: 'צילום קודם · נדרש רענון',
  editorial: 'בסיס תכנוני מוכן',
  needs_details: 'ממתין להעדפות',
  needs_search: 'מוכן לחיפוש חי',
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
      source: data?.planning?.sourceLabel || 'Tra-Vel planning profile',
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
  const editorialSource = data?.planning?.sourceLabel || 'Tra-Vel planning profile';
  const reviewedOn = data?.planning?.reviewedOn || '';
  const module = (id, state, headline, detail, nextAction) => safeSelectedPlanModule({
    id, state, headline, detail, next_action: nextAction,
    provenance: { source: editorialSource, observed_at: reviewedOn }
  });
  const modules = [
    module('route', routeState, destinationRoutes.length ? `${destinationRoutes.length} דרכים ל${data.city} מוכנות להשוואה` : `נדרש חיפוש דרך ל${data.city}`, currentRouteData ? 'מבנה הדרך והזמנים התקבלו מספק מחובר. המחיר מוצג רק לרכיבים שבבעלות הספק.' : 'מבנה ההשוואה מוכן. זמן, מחיר וכבודה יאומתו בחיפוש חי.', 'השוו טיסות ודרכים'),
    module('stay', discoveryLiveLayers.hotels ? (snapshotCurrent ? 'live' : 'stale') : 'editorial', data.hotelArea ? `מתחילים באזור ${data.hotelArea}` : 'בוחרים אזור לפני מלון', currentHotelData ? 'מחיר החדר וזהות המלון התקבלו. מסים, מלאי וביטול עדיין דורשים הצעה מתוארכת.' : 'האזור הוא בסיס תכנוני. מחיר, מלאי וביטול ייבדקו לפי תאריכים.', 'השוו אזורים ומלונות'),
    localPlanningModule(data, 'mobility', { state: 'editorial', headline: 'מחברים שדה, מלון ותחבורה מקומית', detail: 'סוג ההעברה והמחיר ייבדקו לפי שעת הנחיתה, כתובת המלון והרכב הנוסעים.', next_action: 'השוו העברות' }),
    module('activities', 'editorial', `בונים קצב ופעילויות ל${data.city}`, 'מחברים עוגנים, זמן חופשי, מרחקים וכרטיסים. זמינות ומחיר דורשים תאריך.', 'תכננו פעילויות'),
    localPlanningModule(data, 'dining', { state: 'needs_details', headline: 'אוכל וכשרות לפי ההעדפות שלכם', detail: 'מוסיפים כשרות, אלרגיות, ילדים ותקציב לפני בניית מסלול אוכל.', next_action: 'הוסיפו העדפות אוכל' }),
    module('weather', discoveryLiveLayers.weather ? (snapshotCurrent ? 'live' : 'stale') : 'editorial', currentWeatherData ? `${data.weather} עכשיו; התחזית תותאם לתאריך` : 'מזג האוויר ייבדק לפי מועד הנסיעה', currentWeatherData ? 'התנאים הנוכחיים התקבלו. עונה וציוד עדיין יותאמו לתאריכים.' : 'פרופיל עונתי עוזר לתכנון, אך תחזית אינה מוצגת בלי מועד.', 'בדקו עונה ותחזית'),
    localPlanningModule(data, 'entry', { state: 'needs_details', headline: 'כניסה, דרכון ואשרות', detail: 'אזרחות, תוקף דרכון, מסלול ותאריך נבדקים מול מקור רשמי לפני רכישה.', next_action: 'בדקו תנאי כניסה' }),
    localPlanningModule(data, 'connectivity', { state: 'editorial', headline: 'eSIM, נדידה ו-SIM מקומי', detail: 'משווים חיבור לפי ימים, נפח, כיסוי ושיתוף אינטרנט.', next_action: 'השוו חיבור' }),
    localPlanningModule(data, 'accessibility', { state: 'needs_details', headline: 'משפחה ונגישות', detail: 'גילים, עגלה, הליכה, מעלית וסיוע משנים את המלון ואת קצב היום.', next_action: 'התאימו צרכים' }),
    module('insurance', 'needs_details', 'כיסוי לפי הנוסעים והמסלול', 'גיל, מצב רפואי, פעילויות, כבודה וביטול דורשים פרטים לפני התאמה.', 'התאימו ביטוח'),
    localPlanningModule(data, 'equipment', { state: 'needs_details', headline: 'ציוד, כבודה והשכרה', detail: 'מחברים פעילויות, עונה ותנאי כבודה לפני שמחליטים מה לארוז ומה לשכור.', next_action: 'בנו רשימת ציוד' }),
    module('total', 'needs_search', 'עלות מלאה מכל הרכיבים', 'כל רכיבי הנסיעה נכנסים לאותו ספר עלויות. הסכום יוצג רק אחרי חיפוש בר-השוואה.', 'הריצו חיפוש מלא')
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
  if (['current', 'stale'].includes(responseState)) return selectedPlan;
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
  const needsInputCount = selectedPlan.modules.filter(module => ['stale', 'needs_details', 'needs_search', 'unknown', 'unavailable'].includes(module.state)).length;
  const coverage = Math.round((mappedCount / moduleCount) * 100);
  if (meter) {
    meter.setAttribute('aria-valuemax', String(moduleCount));
    meter.setAttribute('aria-valuenow', String(mappedCount));
    meter.setAttribute('aria-valuetext', `${mappedCount} תחומים מופו; ${needsInputCount} דורשים פרטים או חיפוש; אין הזמנה מאושרת`);
    const count = meter.querySelector('[data-plan-meter-count]');
    const fill = meter.querySelector('[data-plan-meter-fill]');
    if (count) count.textContent = `${mappedCount}/${moduleCount}`;
    if (fill) fill.style.setProperty('--plan-coverage', `${coverage}%`);
  }
  const coverageCopy = plan.querySelector('[data-plan-coverage-copy]');
  if (coverageCopy) coverageCopy.textContent = selectedPlan.state === 'stale'
    ? `${mappedCount} מתוך ${moduleCount} תחומי החלטה מופו ל${data.city}. נתוני ספק ישנים מסומנים ודורשים רענון.`
    : `${mappedCount} מתוך ${moduleCount} תחומי החלטה מופו ל${data.city}. זהו כיסוי תכנוני, לא אישור הזמנה.`;

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
        : (item.state === 'stale' && item.formatted ? `${item.formatted} · צילום קודם` : 'ממתין לחיפוש חי');
      appendTextElement(row, 'em', amountLabel);
      row.dataset.state = item.state;
      return row;
    }));
  }
  if (ledgerTotal) ledgerTotal.textContent = ledger.total?.formatted
    ? (['stale_complete', 'stale_partial'].includes(ledger.state) ? `${ledger.total.formatted} · צילום קודם` : ledger.total.formatted)
    : 'נדרש חיפוש חי';
  if (ledgerState) {
    const liveCosts = ledger.line_items.filter(item => item.state === 'live').length;
    const staleCosts = ledger.line_items.filter(item => item.state === 'stale').length;
    ledgerState.textContent = staleCosts
      ? `${ledger.line_items.length} רכיבי עלות במעקב · ${staleCosts} מתצפית קודמת ודורשים רענון`
      : `${ledger.line_items.length} רכיבי עלות במעקב · ${liveCosts} התקבלו מספק`;
  }
  if (ledgerTruth) {
    ledgerTruth.textContent = ['stale_complete', 'stale_partial'].includes(ledger.state)
      ? `הסכומים מסומנים כתצפית קודמת: ${discoveryFreshnessLabel()}. הם לא ישמשו כאישור מחיר, חיסכון או זמינות.`
      : (ledger.savings?.comparable_verified
      ? `הפער המאומת מול חלופה ברת-השוואה הוא ${ledger.savings.formatted}. המחיר עדיין כפוף לזמינות עד אישור.`
      : 'לא מוצג חיסכון עד שיש מחיר בסיס בר-השוואה, זמינות ומועד בדיקה.');
  }
  plan.dataset.coverageState = selectedPlan.state;
  return selectedPlan;
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
    const latitude = Number(options.latitude).toFixed(2);
    const longitude = Number(options.longitude).toFixed(2);
    if (kicker) kicker.textContent = 'הנקודה התקבלה';
    if (title) title.textContent = `נבחר אזור ב-${latitude}°, ${longitude}°`;
    if (detail) detail.textContent = 'לא נמציא יעד. הכיסוי המובנה עדיין חסר, והסוכן יכול להתחיל מהנקודה ולבקש רק את הפרטים הנחוצים.';
    if (action) {
      action.href = destinationPlanUrl('/ai-planner/', { latitude, longitude, mode: 'map_point', intent: activePlanIntent });
      action.firstChild.textContent = 'המשיכו עם הסוכן';
    }
    return;
  }
  const selectedPlan = selectedPlanForDestination(data);
  const distanceKm = Number(options.distanceKm);
  const isNearbyPoint = Number.isFinite(distanceKm) && distanceKm > 20;
  if (kicker) kicker.textContent = 'הנקודה התקבלה';
  if (title) title.textContent = isNearbyPoint
    ? `הנקודה נמצאת ${Math.round(distanceKm)} ק״מ מ${data.city}. תוכנית העיר נפתחה כבסיס.`
    : `${data.city} נבחרה. תוכנית 360° נפתחה.`;
  if (detail) detail.textContent = options.awaiting
    ? 'הבחירה נקלטה. בודקים מסלולים, שכבות ותנאים בלי להציג אישור לפני שהתגובה חוזרת.'
    : `${selectedPlan.coverage.mapped_count} תחומי החלטה מסודרים מתחת למפה. מחירים והזמנה יאומתו בחיפוש חי.`;
  if (action) {
    action.href = '#destination-plan-title';
    action.firstChild.textContent = 'צפו בתוכנית';
  }
}

function revealGlobeSelection(inputType = 'pointer') {
  const rail = document.querySelector('[data-globe-selection]');
  if (!rail) return;
  if (inputType === 'keyboard') rail.focus({ preventScroll: true });
  if (inputType === 'keyboard' || window.matchMedia('(max-width: 760px)').matches) {
    rail.scrollIntoView({ behavior: preferredScrollBehavior(), block: 'nearest' });
  }
}

function renderUnsupportedGlobeSelection(detail = {}) {
  const latitude = Number(detail.latitude);
  const longitude = Number(detail.longitude);
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;
  discoveryRequestController?.abort();
  discoveryRequestController = null;
  discoveryRequestGeneration += 1;
  setRouteListBusy(false);
  activeDestination = '';
  activeRouteId = '';
  discoverySelectedPlan = null;
  discoveryRoutes = [];
  discoveryDestinationLocked = false;
  discoveryBudgetCoverage = 'none';
  discoveryBudgetApplied = false;
  discoveryBudgetFilterActive = false;
  updateBudgetCoverageStatus();
  window.traVelGlobe3D?.clearSelection();
  renderRoutes([]);
  const routeTitle = document.querySelector('[data-route-title]');
  if (routeTitle) routeTitle.textContent = 'המסלול ייבנה לאחר זיהוי היעד והנוסעים';

  document.querySelectorAll('[data-map-result]').forEach(card => {
    card.dataset.empty = 'true';
    card.removeAttribute('data-destination');
    const image = card.querySelector('[data-result-image]');
    if (image) image.hidden = true;
    const fields = {
      '[data-result-city]': `נקודה שנבחרה · ${latitude.toFixed(2)}°, ${longitude.toFixed(2)}°`,
      '[data-result-state-label]': 'בחירה חופשית על הגלובוס',
      '[data-result-price]': 'נדרש זיהוי יעד',
      '[data-result-total]': 'הנקודה התקבלה',
      '[data-result-note]': 'לא משייכים עיר, מחיר או תנאי כניסה בלי כיסוי מאומת.',
      '[data-result-airport]': 'שדה ייבחר לפי יעד',
      '[data-result-hotel]': 'אזור לינה ייבדק',
      '[data-result-weather]': 'מזג אוויר לפי תאריך'
    };
    Object.entries(fields).forEach(([selector, value]) => {
      const element = card.querySelector(selector);
      if (element) element.textContent = value;
    });
    replaceChildrenWithSpans(card.querySelector('[data-result-tags]'), ['נקודה התקבלה', 'כיסוי בבנייה', 'המשך עם הסוכן']);
    card.querySelectorAll('[data-result-guide],[data-result-hotels],[data-result-insurance]').forEach(link => {
      link.setAttribute('aria-disabled', 'true');
      link.removeAttribute('href');
    });
    const saveButton = card.querySelector('.save-button');
    if (saveButton) {
      saveButton.disabled = true;
      saveButton.classList.remove('is-saved');
      saveButton.setAttribute('aria-label', 'נדרש יעד מזוהה לפני שמירה');
    }
  });

  const plan = document.querySelector('[data-destination-plan]');
  if (plan) {
    resetDestinationPlanTransientState(plan, { pointReceived: true });
    plan.dataset.state = 'unsupported';
    plan.dataset.coverageState = 'unknown';
    plan.setAttribute('aria-busy', 'false');
    const fields = {
      '[data-plan-title]': 'תוכנית 360° מתחילה מהנקודה שבחרתם',
      '[data-plan-state]': 'הנקודה התקבלה · הכיסוי המובנה עדיין לא זמין',
      '[data-plan-summary]': 'הסוכן יזהה את האזור יחד אתכם ולא ימציא עיר, מחיר או זמינות.',
      '[data-plan-truth]': 'הנקודה היא קלט תכנוני בלבד. אין עדיין יעד, מחיר, זמינות או הזמנה.'
    };
    Object.entries(fields).forEach(([selector, value]) => {
      const element = plan.querySelector(selector);
      if (element) element.textContent = value;
    });
    plan.querySelectorAll('[data-plan-flight],[data-plan-stay],[data-plan-experience],[data-plan-weather],[data-plan-cover],[data-plan-total],[data-plan-guide]').forEach(link => {
      link.setAttribute('aria-disabled', 'true');
      link.removeAttribute('href');
    });
    const ai = plan.querySelector('[data-plan-ai]');
    if (ai) ai.href = destinationPlanUrl('/ai-planner/', { latitude: latitude.toFixed(4), longitude: longitude.toFixed(4), mode: 'map_point', intent: activePlanIntent });
    const meter = plan.querySelector('[data-plan-meter]');
    if (meter) {
      meter.setAttribute('aria-valuenow', '0');
      meter.setAttribute('aria-valuetext', 'הנקודה התקבלה; 0 תחומים מופו; אין הזמנה מאושרת');
      const count = meter.querySelector('[data-plan-meter-count]');
      const fill = meter.querySelector('[data-plan-meter-fill]');
      if (count) count.textContent = '0/12';
      if (fill) fill.style.setProperty('--plan-coverage', '0%');
    }
    const coverage = plan.querySelector('[data-plan-coverage-copy]');
    if (coverage) coverage.textContent = 'הנקודה נקלטה. 12 תחומי ההחלטה ייפתחו לאחר זיהוי היעד.';
    plan.querySelectorAll('[data-plan-module]').forEach(module => {
      module.dataset.state = 'unknown';
      const state = module.querySelector('[data-plan-module-state]');
      const moduleDetail = module.querySelector('[data-plan-module-detail]');
      const action = module.querySelector('[data-plan-module-action]');
      if (state) state.textContent = 'ממתין לזיהוי יעד';
      if (moduleDetail) moduleDetail.textContent = 'הנקודה התקבלה. הסוכן יזהה את האזור ויבקש רק את הפרטים שנדרשים לתחום הזה.';
      if (action) action.href = ai?.href || destinationPlanUrl('/ai-planner/');
    });
    const ledgerTotal = plan.querySelector('[data-plan-ledger-total]');
    const ledgerState = plan.querySelector('[data-plan-ledger-state]');
    const ledgerList = plan.querySelector('[data-plan-ledger-list]');
    const ledgerTruth = plan.querySelector('[data-plan-ledger-truth]');
    if (ledgerTotal) ledgerTotal.textContent = 'עדיין אין יעד';
    if (ledgerState) ledgerState.textContent = 'ספר העלויות ייפתח אחרי זיהוי';
    if (ledgerList) {
      const row = document.createElement('span');
      appendTextElement(row, 'b', 'הנקודה התקבלה');
      appendTextElement(row, 'em', 'ממתין ליעד, תאריכים והרכב');
      ledgerList.replaceChildren(row);
    }
    if (ledgerTruth) ledgerTruth.textContent = 'לא מוצגים מחיר או חיסכון לפני זיהוי יעד וחיפוש בר-השוואה.';
  }
  updateGlobeSelectionRail(null, { mode: 'unsupported', latitude, longitude });
  setDiscoveryStatus('demo', 'הנקודה התקבלה · הכיסוי המובנה באזור עדיין לא זמין');
  syncDiscoveryUrl('replace');
  revealGlobeSelection(detail.inputType || 'pointer');
}

function initGlobePointSelection() {
  document.addEventListener('travelglobe:select', event => {
    if (!event.target.closest('[data-globe-3d][data-discovery-globe]')) return;
    const detail = event.detail || {};
    const destinationId = typeof detail.nearestDestination === 'string' ? detail.nearestDestination : '';
    if (!detail.supported || !destinationData[destinationId]) {
      renderUnsupportedGlobeSelection(detail);
      return;
    }
    discoveryDestinationLocked = true;
    discoverySelectedPlan = null;
    activeRouteId = '';
    activeRouteSelectionLocked = false;
    setActiveDestination(destinationId, null, { animate: true, responseConfirmed: false });
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
    route: discoveryLiveLayers.airports ? `${discoveryRoutes.length} אפשרויות מצילום קודם` : 'מוכן לחיפוש מחדש',
    stay: discoveryLiveLayers.hotels ? 'מידע קודם · נדרש רענון' : 'מוכן לבדיקה',
    experience: discoveryLiveLayers.weather ? 'תצפית קודמת · נדרש רענון' : 'מוכן לתכנון',
    cover: 'מוכן להתאמה',
    total: discoveryLiveLayers.deals ? 'מחיר קודם · נדרש רענון' : 'ממתין לחיפוש מלא'
  } : (fallbackSnapshot ? {
    destination: 'נבחר', route: 'מוכן לחיפוש', stay: 'מוכן לבדיקה', experience: 'מוכן לתכנון', cover: 'מוכן להתאמה', total: 'ממתין לחיפוש מלא'
  } : {
    destination: 'נבחר',
    route: routeCount ? `${routeCount} אפשרויות התקבלו` : 'מוכן להשוואה',
    stay: discoveryLiveLayers.hotels ? 'מידע עדכני התקבל' : 'מוכן לבדיקה',
    experience: discoveryLiveLayers.weather ? 'תנאי מזג אוויר התקבלו' : 'מוכן לתכנון',
    cover: 'מוכן להתאמה',
    total: discoveryLiveLayers.deals ? 'נתון מחיר התקבל' : 'ממתין לחיפוש מלא'
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
    '[data-plan-state]': snapshotStale && anySupplierSnapshot ? `${layerLabel} במוקד · מוצג צילום קודם ונדרש רענון` : `${layerLabel} במוקד · 12 תחומי ההחלטה נשארים מחוברים`,
    '[data-plan-summary]': `${intent.summary} כל החלטה נשארת מחוברת ל${data.city} ולבחירות שלכם.`,
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
      : (snapshotStale && discoveryLiveLayers.weather && data.weather ? `${data.weather} · תצפית קודמת` : 'בדיקה לפי תאריך'),
    '[data-plan-weather-detail]': snapshotCurrent && discoveryLiveLayers.weather
      ? (hasLiveSeason ? `התאמת עונה מעודכנת: ${data.seasonFit || 'לפי מועד'}` : 'התנאים הנוכחיים עודכנו. התאמת העונה תיבדק לפי תאריך הנסיעה')
      : (snapshotStale && discoveryLiveLayers.weather
        ? 'מוצגת תצפית מזג אוויר קודמת בלבד. נדרש רענון ותאריך נסיעה לפני החלטה.'
        : 'מזג אוויר יאומת לפי תאריך הנסיעה'),
    '[data-plan-cover-title]': activePlanIntent === 'adventure' ? 'כיסוי פעילות וציוד ייעודי' : 'כיסוי לפי המסלול והנוסעים',
    '[data-plan-total-title]': 'עדיין לא חושבה בחבילה מלאה',
    '[data-plan-truth]': snapshotStale && anySupplierSnapshot
      ? `נתוני הספק שמוצגים הם תצפית קודמת בלבד: ${discoveryFreshnessLabel()}. אין בכך אישור מחיר, זמינות או הזמנה.`
      : (anyLiveData
      ? 'יש נתונים עדכניים לחלק מהמסע. העלות הכוללת והזמינות יאומתו לפני אישור.'
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
    direct: discoveryQuery.direct ? 1 : '',
    route: activeRouteId
  };
  const links = {
    '[data-plan-flight]': destinationPlanUrl('/flights/', { destination: airportCode, ...planningContext }),
    '[data-plan-stay]': destinationPlanUrl('/hotels/', { destination: airportCode, area: data.hotelArea || '', ...planningContext }),
    '[data-plan-experience]': destinationDirectoryUrl(destinationId, planningContext),
    '[data-plan-weather]': destinationPlanUrl('/travel-map/', { destination: destinationId, layer: 'weather', intent: activePlanIntent, ...discoveryQuery }),
    '[data-plan-cover]': destinationPlanUrl('/travel-insurance/', { trip_destination: destinationId, intent: activePlanIntent }),
    '[data-plan-total]': destinationPlanUrl('/packages/', { destination: airportCode, ...planningContext }),
    '[data-plan-guide]': data.url || destinationPlanUrl('/destinations/', { destination: destinationId }),
    '[data-plan-ai]': destinationPlanUrl('/ai-planner/', {
      destination: destinationId,
      scope: 'flights,accommodation,transfers,activities,dining,insurance,connectivity,equipment',
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

  if (animate) {
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
    homePlan.setAttribute('aria-busy', 'false');
  }
  const summary = document.querySelector('[data-home-plan-summary]');
  if (summary) summary.textContent = `${data.city} נבחרה. עכשיו מחברים דרך, לינה, חוויות והגנה לתוכנית אחת.`;
  const context = { destination: destinationId, intent: activePlanIntent };
  const links = {
    '[data-home-plan-flight]': destinationPlanUrl('/flights/', { destination: airportCode, intent: activePlanIntent }),
    '[data-home-plan-stay]': destinationPlanUrl('/hotels/', { destination: airportCode, area: data.hotelArea || '', intent: activePlanIntent }),
    '[data-home-plan-guide]': data.url || destinationPlanUrl('/destinations/', { destination: destinationId }),
    '[data-home-plan-ai]': destinationPlanUrl('/ai-planner/', context),
    '[data-home-plan-full]': destinationPlanUrl('/travel-map/', context)
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
let travelerWorkspace = null;
let activeWorkspaceFilter = 'all';
let workspaceLocalStorageAvailable = true;
const workspaceQuoteCaseRuntime = {
  timer: 0,
  failures: 0,
  inFlight: false,
  controller: null,
  snapshot: new Map()
};

function defaultLocalWorkspace() {
  return {
    version: 1,
    items: [],
    preferences: {home_airport: 'TLV', currency: 'USD', budget: 0, max_stops: 1, party_style: 'couple', priorities: ['price', 'comfort']},
    meta: {storage: 'browser_local', max_items: 50, price_watch_delivery_enabled: false, sensitive_data_allowed: false}
  };
}

function readLocalWorkspace() {
  try {
    const parsed = JSON.parse(window.localStorage.getItem(workspaceLocalKey) || 'null');
    workspaceLocalStorageAvailable = true;
    if (!parsed || parsed.version !== 1 || !Array.isArray(parsed.items)) return defaultLocalWorkspace();
    return {
      ...defaultLocalWorkspace(),
      ...parsed,
      items: parsed.items.slice(0, 50),
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
    workspaceLocalStorageAvailable = true;
    return true;
  } catch (error) {
    workspaceLocalStorageAvailable = false;
    console.warn(error);
    return false;
  }
}

function mergeTravelerWorkspaces(local, server) {
  if (!server) return local;
  const byId = new Map([...(local.items || []), ...(server.items || [])].map(item => [item.id, item]));
  return {...local, ...server, items: Array.from(byId.values()).slice(0, 50)};
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
  if (!response.ok) throw new Error(payload.message || `Workspace request failed: ${response.status}`);
  return payload;
}

function commercialHandoffContext(vertical, commerce, payload = {}) {
  const query = payload.query || payload.search || {};
  const calculation = payload.calculation || {};
  const adults = Number(query.adults || 0);
  const children = Number(query.children || 0);
  const verticalLabels = {
    flight: 'טיסה', hotel: 'מלון', package: 'טיסה ומלון', insurance: 'ביטוח נסיעות'
  };
  const provider = commerce?.bookable || commerce?.purchasable
    ? String(commerce.provider || '')
    : 'tra-vel-concierge';
  return {
    provider: provider && provider !== 'demo' ? provider : 'tra-vel-concierge',
    vertical,
    offer_id: String(commerce?.id || 'search').replace(/[^A-Za-z0-9._:-]/g, '').slice(0, 80) || 'search',
    destination: String(payload.destination?.city || payload.destination?.name || query.destination || '').slice(0, 80),
    origin: String(payload.origin?.code || query.origin || 'TLV').slice(0, 80),
    depart_date: String(query.depart_date || query.check_in || query.start_date || '').slice(0, 10),
    return_date: String(query.return_date || query.check_out || query.end_date || '').slice(0, 10),
    travelers: Math.max(1, Math.min(20, Number(payload.trip?.travelers || calculation.travelers || adults + children || 1))),
    budget: Math.max(0, Math.min(1000000, Number(query.budget || query.max_total || 0))),
    currency: ['ILS', 'USD', 'EUR', 'GBP'].includes(query.currency) ? query.currency : 'ILS',
    product: verticalLabels[vertical] || vertical,
    return_path: `${window.location.pathname}${window.location.search}`.slice(0, 200)
  };
}

async function startCommercialHandoff(button, vertical, commerce, payload) {
  const endpoint = window.traVelV2?.handoffUrl;
  if (!endpoint || !button) return;
  const originalText = button.textContent;
  button.disabled = true;
  button.dataset.handoffState = 'loading';
  button.textContent = 'מכינים בקשה מאובטחת...';
  try {
    let requestBody = commercialHandoffContext(vertical, commerce, payload);
    let response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {Accept: 'application/json', 'Content-Type': 'application/json'},
      body: JSON.stringify(requestBody)
    });
    if (!response.ok && requestBody.provider !== 'tra-vel-concierge') {
      requestBody = {...requestBody, provider: 'tra-vel-concierge'};
      response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {Accept: 'application/json', 'Content-Type': 'application/json'},
        body: JSON.stringify(requestBody)
      });
    }
    const handoff = await response.json().catch(() => ({}));
    if (!response.ok || !handoff.handoff_url) throw new Error(handoff.message || `Handoff failed: ${response.status}`);
    button.dataset.handoffState = 'ready';
    button.textContent = handoff.conversion_type === 'assisted_quote' ? 'עוברים לשיחה עם Tra-Vel' : 'עוברים לספק המאומת';
    window.location.assign(handoff.handoff_url);
  } catch (error) {
    button.disabled = false;
    button.dataset.handoffState = 'error';
    button.textContent = 'לא הצלחנו לפתוח את הבקשה. נסו שוב';
    window.setTimeout(() => {
      if (button.dataset.handoffState === 'error') button.textContent = originalText;
    }, 4500);
    console.warn(error);
  }
}

function showWorkspaceToast(message, icon = 'heart') {
  let toast = document.querySelector('[data-workspace-toast]');
  if (!toast) {
    toast = document.createElement('div');
    toast.className = 'workspace-toast';
    toast.dataset.workspaceToast = '';
    toast.setAttribute('role', 'status');
    document.body.append(toast);
  }
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
    data_mode: ['demo', 'mixed', 'live', 'editorial'].includes(item.data_mode) ? item.data_mode : 'demo',
    href: String(item.href || '/'),
    saved_at: new Date().toISOString(),
    watch: {enabled: false, target_amount: 0, delivery_enabled: false, status: 'off'}
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
  if (!item.external_id || !item.title) return {localSaved: false, accountSynced: false};
  const workspace = travelerWorkspace || readLocalWorkspace();
  const existing = workspace.items.find(saved => saved.id === item.id);
  if (existing?.watch) item.watch = existing.watch;
  const nextWorkspace = {
    ...workspace,
    items: [item, ...workspace.items.filter(saved => saved.id !== item.id)].slice(0, 50)
  };
  if (!writeLocalWorkspace(nextWorkspace)) {
    showWorkspaceToast('לא הצלחנו לשמור בדפדפן. בדקו שהאחסון המקומי זמין ונסו שוב.', 'triangle-alert');
    return {localSaved: false, accountSynced: false};
  }
  button?.classList.add('is-saved');
  if (button) {
    const label = button.querySelector('span');
    if (label) label.textContent = 'נשמר לנסיעה';
    button.setAttribute('aria-label', 'נשמר לנסיעה');
  }
  if (!window.traVelV2?.isLoggedIn) {
    showWorkspaceToast('נשמר באופן פרטי במכשיר הזה', 'heart');
    return {localSaved: true, accountSynced: false};
  }
  showWorkspaceToast('נשמר במכשיר. בודקים סנכרון לחשבון...', 'cloud');
  try {
    const serverWorkspace = await workspaceRequest('/items', {method: 'POST', body: JSON.stringify(item)});
    if (serverWorkspace) {
      writeLocalWorkspace(mergeTravelerWorkspaces(nextWorkspace, serverWorkspace));
      showWorkspaceToast('השמירה אושרה בחשבון ובמכשיר', 'cloud-check');
      return {localSaved: true, accountSynced: true};
    }
    showWorkspaceToast('נשמר במכשיר; סנכרון החשבון אינו זמין כרגע', 'cloud-off');
  } catch (error) {
    showWorkspaceToast('נשמר במכשיר; הסנכרון לחשבון ינסה שוב בהמשך', 'cloud-off');
    console.warn(error);
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
  return {
    kind: 'destination',
    external_id: data.id,
    title: `${data.city}, ${data.country}`,
    subtitle: `${selectedRoute?.label || data.airportCode || 'יעד'} · ${data.hotelArea || 'אזור לינה לבחירה'} · תוכנית ${destinationPlanIntents[activePlanIntent]?.label || 'חכמה'}`,
    destination: data.city,
    route: selectedRoute ? `TLV → ${selectedRoute.label}` : `TLV → ${data.airportCode || data.city}`,
    price_label: currentDeal ? data.total : 'נדרש חיפוש חי',
    price_amount: currentDeal ? data.totalAmount : 0,
    currency: data.currency || 'USD',
    data_mode: currentDeal ? 'live' : 'editorial',
    href: data.url || destinationPlanUrl('/destinations/', { destination: data.id })
  };
}

function renderRoutes(routes, recommendedId = '') {
  const list = document.querySelector('[data-route-list]');
  if (!list) return;
  list.replaceChildren();
  if (!routes.length) {
    activeRouteId = '';
    activeRouteSelectionLocked = false;
    appendTextElement(list, 'p', 'עדיין אין השוואת מסלולים מלאה ליעד הזה. אפשר להמשיך לחיפוש ולבדוק זמינות עדכנית.', 'route-empty');
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
    const routePrice = hasLiveRouteTotal
      ? route.costs.total_formatted
      : (hasStaleRouteTotal ? `${route.costs.total_formatted} · צילום קודם` : 'בדיקת מחיר');
    const showRouteSnapshot = hasLiveRouteData || hasStaleRouteData;
    const button = document.createElement('button');
    button.className = `mini-route${route.id === activeRouteId ? ' is-selected' : ''}`;
    button.type = 'button';
    button.dataset.route = route.id;
    button.dataset.freshness = hasStaleRouteData ? 'stale' : (hasLiveRouteData ? 'current' : 'editorial');
    button.dataset.routeSummary = `${route.label} · ${routePrice}`;
    button.setAttribute('aria-pressed', String(route.id === activeRouteId));
    appendTextElement(button, 'small', route.badge);
    appendTextElement(button, 'strong', showRouteSnapshot ? `${route.label} · ${route.duration_label}` : route.label);
    appendTextElement(button, 'span', showRouteSnapshot ? `${route.stops ? `${route.stops} עצירה` : 'ישיר'} · ${route.ticket_mode === 'single' ? 'כרטיס אחד' : 'כרטיסים נפרדים'}` : 'זמן, עצירות ותנאי כרטיס יוצגו אחרי חיפוש');
    const tradeoffs = document.createElement('div');
    tradeoffs.className = 'route-tradeoffs';
    appendTextElement(tradeoffs, 'span', showRouteSnapshot ? `✓ ${route.pros[0]}` : '✓ משווים זמן, נוחות וגמישות', 'route-pro');
    appendTextElement(tradeoffs, 'span', showRouteSnapshot ? `△ ${route.cons[0]}` : '△ מחיר ותנאים יאומתו בחיפוש חי', 'route-con');
    button.append(tradeoffs);
    appendTextElement(button, 'b', routePrice);
    appendTextElement(button, 'em', hasLiveRouteTotal ? 'עלות מסלול לפי היקף הספק' : (hasStaleRouteTotal ? 'צילום ספק קודם · נדרש רענון' : 'מחיר סופי יוצג בחיפוש חי'));
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

function setDiscoveryStatus(mode, message) {
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
  if (mode === 'loading') {
    updatePins();
    const pendingData = destinationData[activeDestination];
    if (pendingData) {
      setActiveDestination(activeDestination, null, {
        animate: false,
        responseConfirmed: false,
        responseState: 'pending',
        globeAnimate: false,
        pulseRoute: false
      });
    }
  }
  const plan = document.querySelector('[data-destination-plan]');
  if (plan) {
    plan.dataset.requestState = mode;
    plan.setAttribute('aria-busy', String(mode === 'loading'));
    const planState = plan.querySelector('[data-plan-state]');
    if (mode === 'loading') {
      plan.classList.remove('is-updating');
      updateDestinationPlanStages(plan, 'pending');
      if (planState) planState.textContent = 'מעדכנים את התוכנית לפי הבחירה שלכם';
      const save = plan.querySelector('[data-plan-save]');
      if (save) save.disabled = true;
    }
  }
  if (mode === 'loading') {
    document.querySelectorAll('[data-map-result] .save-button').forEach(button => { button.disabled = true; });
  }
  const homePlan = document.querySelector('[data-home-plan]');
  if (homePlan) {
    homePlan.dataset.requestState = mode;
    homePlan.setAttribute('aria-busy', String(mode === 'loading'));
    const homeSummary = homePlan.querySelector('[data-home-plan-summary]');
    if (mode === 'loading') {
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

async function hydrateDiscovery(params = {}) {
  const endpoint = window.traVelV2?.discoveryUrl;
  if (!endpoint || !document.querySelector('[data-discovery-globe] .price-pin[data-destination]')) return;
  const requestParams = discoveryRequestParams(params);
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
  setDiscoveryStatus('loading', 'מעדכן יעדים ומסלולים...');
  setRouteListBusy(true);
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
    updateWeatherAttribution(payload.provider_status);
    updatePins();
    const resolvedDestination = payload.meta?.selected_destination || '';
    const selected = destinationData[resolvedDestination]
      ? resolvedDestination
      : (destinationData[requestParams.destination] ? requestParams.destination : Object.keys(destinationData)[0]);
    if (selected) {
      discoveryLiveLayers = destinationData[selected]?.liveLayers || resolveDiscoveryLiveLayers(discoveryFieldProvenance, selected);
      discoveryRoutes = resolvedDestination === selected && Array.isArray(payload.routes)
        ? payload.routes.filter(route => route.destination_id === selected)
        : [];
      const responseState = settledDiscoveryResponseState();
      const responseSupportsConfirmedMotion = responseState === 'current';
      const recommendedRouteId = typeof payload.recommended?.id === 'string' ? payload.recommended.id : '';
      renderRoutes(discoveryRoutes, recommendedRouteId);
      setActiveDestination(selected, document.querySelector(`[data-destination="${selected}"]`), {
        animate: responseSupportsConfirmedMotion,
        responseConfirmed: responseSupportsConfirmedMotion,
        responseState,
        globeAnimate: responseSupportsConfirmedMotion,
        pulseRoute: responseSupportsConfirmedMotion
      });
      syncDiscoveryUrl('replace');
    } else {
      renderDiscoveryEmptyState();
      syncDiscoveryUrl('replace');
    }
    const layerName = activeLayer === 'deals' && !discoveryLiveLayers.deals
      ? 'יעדים'
      : (payload.layers?.find(layer => layer.id === activeLayer)?.label || 'יעדים');
    const liveModeLabels = { deals: 'מחירי הצעה מעודכנים', hotels: 'מחירי לינה מעודכנים', airports: 'נתוני דרך מעודכנים', weather: 'תנאי מזג אוויר נוכחיים' };
    const verificationLabels = { deals: 'מחיר יוצג לאחר חיפוש', hotels: 'מחיר חדר ותנאים ייבדקו בחיפוש', airports: 'זמן, עצירות ותנאים ייבדקו בחיפוש', weather: 'תחזית תוצג לפי תאריך הנסיעה' };
    const modeLabel = discoveryFreshness !== 'current'
      ? discoveryFreshnessLabel()
      : (discoveryLiveLayers[activeLayer]
      ? liveModeLabels[activeLayer]
      : verificationLabels[activeLayer]);
    const confirmedState = discoverySnapshotIsStale()
      ? discoveryFreshness
      : (['fallback', 'error'].includes(discoveryDataMode)
      ? discoveryDataMode
      : (discoveryLiveLayers[activeLayer] && discoverySnapshotIsCurrent() ? 'live' : 'demo'));
    const budgetLabel = budgetCoverageLabel();
    setDiscoveryStatus(confirmedState, `${layerName} · ${payload.meta.result_count} יעדים · ${modeLabel}${budgetLabel ? ` · ${budgetLabel}` : ''}`);
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
    const fallbackDestination = destinationData[requestParams.destination] ? requestParams.destination : Object.keys(destinationData)[0];
    if (fallbackDestination) {
      setActiveDestination(fallbackDestination, document.querySelector(`[data-destination="${fallbackDestination}"]`), {
        animate: false,
        responseConfirmed: false,
        responseState: 'fallback',
        globeAnimate: false,
        pulseRoute: false
      });
      syncDiscoveryUrl('replace');
    }
    setDiscoveryStatus(fallbackDestination ? 'fallback' : 'error', timedOut ? 'העדכון החי נעצר בזמן · 6 יעדים נשארו זמינים לתכנון' : '6 יעדים זמינים · מחירים יופיעו בחיפוש חי');
    console.warn(error);
  } finally {
    if (timeoutId) window.clearTimeout(timeoutId);
    if (generation === discoveryRequestGeneration) {
      setRouteListBusy(false);
      if (discoveryRequestController === controller) discoveryRequestController = null;
    }
  }
}

function flightJourneyRow(label, journey) {
  const row = document.createElement('div');
  row.className = 'flight-journey-row';
  appendTextElement(row, 'span', label, 'flight-journey-direction');
  const times = document.createElement('strong');
  times.dir = 'ltr';
  times.textContent = `${journey.departure_time} → ${journey.arrival_time}`;
  row.append(times);
  appendTextElement(row, 'span', `${journey.duration_label} · ${journey.stops_label}`, 'flight-journey-meta');
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

  payload.offers.forEach(offer => {
    const card = document.createElement('article');
    card.className = `flight-offer${offer.id === payload.recommended ? ' is-recommended' : ''}`;

    const head = document.createElement('div');
    head.className = 'flight-offer-head';
    const identity = document.createElement('div');
    appendTextElement(identity, 'small', offer.badge, 'flight-offer-badge');
    appendTextElement(identity, 'h3', offer.label);
    appendTextElement(identity, 'span', `${offer.airline.name} · ${offer.ticket_mode === 'single' ? 'כרטיס אחד' : 'כרטיסים נפרדים'}`);
    head.append(identity);
    const score = appendTextElement(head, 'strong', `${offer.score}`, 'flight-score');
    score.setAttribute('aria-label', `ציון התאמה ${offer.score} מתוך 100`);
    card.append(head);

    const journeys = document.createElement('div');
    journeys.className = 'flight-journeys';
    journeys.append(flightJourneyRow('הלוך', offer.outbound), flightJourneyRow('חזור', offer.inbound));
    card.append(journeys);

    const tradeoffs = document.createElement('div');
    tradeoffs.className = 'flight-tradeoffs';
    appendTextElement(tradeoffs, 'span', `✓ ${offer.pros[0]}`, 'flight-pro');
    appendTextElement(tradeoffs, 'span', `△ ${offer.cons[0]}`, 'flight-con');
    card.append(tradeoffs);

    const totals = document.createElement('div');
    totals.className = 'flight-total-grid';
    const fare = document.createElement('div');
    appendTextElement(fare, 'small', 'טיסה עם תוספות');
    appendTextElement(fare, 'strong', offer.fare.total_formatted);
    const trip = document.createElement('div');
    appendTextElement(trip, 'small', 'עלות מסע מלאה');
    appendTextElement(trip, 'strong', offer.trip_total.total_formatted);
    totals.append(fare, trip);
    card.append(totals);

    const details = document.createElement('details');
    appendTextElement(details, 'summary', 'פירוט עלות ותנאים');
    const breakdown = document.createElement('div');
    breakdown.className = 'flight-breakdown';
    [['מחיר בסיס', offer.fare.base_formatted], ['מסים', offer.fare.taxes_formatted], ['כבודה', offer.fare.baggage_formatted], ['מושבים', offer.fare.seats_formatted], ['מלון משוער', offer.trip_total.hotel_formatted], ['ביטוח משוער', offer.trip_total.insurance_formatted]].forEach(([label, value]) => {
      const line = document.createElement('span');
      appendTextElement(line, 'i', label);
      appendTextElement(line, 'b', value);
      breakdown.append(line);
    });
    appendTextElement(breakdown, 'p', `${offer.policies.baggage} · ${offer.policies.changes}`);
    details.append(breakdown);
    card.append(details);

    const action = document.createElement('button');
    action.className = 'flight-offer-action';
    action.type = 'button';
    action.classList.toggle('is-assisted', !offer.booking.bookable);
    action.textContent = offer.booking.bookable ? 'מעבר להזמנה מאומתת' : 'קבלו מחיר חי בוואטסאפ';
    action.addEventListener('click', () => startCommercialHandoff(action, 'flight', {...offer.booking, id: offer.id}, payload));
    card.append(action, createSaveOfferButton({
      kind: 'flight', external_id: offer.id, title: offer.label,
      subtitle: `${offer.airline.name} · ${offer.outbound.duration_label} · ${offer.outbound.stops_label}`,
      destination: payload.destination?.city || payload.destination?.code || '',
      route: `${payload.origin?.code || 'TLV'} → ${payload.destination?.code || ''}`,
      price_label: offer.trip_total.total_formatted, price_amount: offer.trip_total.total,
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
  submit.disabled = true;
  form.dataset.state = 'loading';
  if (status) status.textContent = 'בודק מחיר, זמן, כבודה וסיכון...';
  try {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || `Flight search failed: ${response.status}`);
    renderFlightOffers(payload);
    const modeLabels = { live: 'מחירי ספקים חיים', mixed: 'נתונים חיים ואומדנים', demo: 'מחירים לאימות בחיפוש' };
    const cacheLabels = { miss: 'עודכן עכשיו', fresh: 'תוצאה שמורה ועדכנית', stale_refreshing: 'מעדכן ברקע', stale_error: 'תוצאה אחרונה תקינה', degraded_fallback: 'חלק מהתוצאות אינן זמינות כרגע' };
    if (status) status.textContent = `${payload.meta.result_count} אפשרויות · ${modeLabels[payload.meta.data_mode] || modeLabels.demo} · ${cacheLabels[payload.meta.cache_state] || 'עודכן'}`;
    form.dataset.state = payload.meta.data_mode;
  } catch (error) {
    document.querySelector('[data-flight-results]')?.replaceChildren();
    if (status) status.textContent = 'לא הצלחנו להשלים את ההשוואה. בדקו את התאריכים ונסו שוב.';
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
    if (returning) {
      returning.min = departure.value;
      if (returning.value <= departure.value) {
        const next = new Date(`${departure.value}T12:00:00`);
        next.setDate(next.getDate() + 7);
        returning.value = next.toISOString().slice(0, 10);
      }
    }
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

function setHotelAreaDetail(area) {
  if (!area) return;
  const name = document.querySelector('[data-hotel-area-name]');
  const profile = document.querySelector('[data-hotel-area-profile]');
  const tradeoff = document.querySelector('[data-hotel-area-tradeoff]');
  if (name) name.textContent = area.name;
  if (profile) profile.textContent = `${area.profile} · ${area.transport}`;
  if (tradeoff) tradeoff.textContent = `△ ${area.tradeoff}`;
  replaceChildrenWithSpans(document.querySelector('[data-hotel-area-tags]'), area.best_for || []);
}

function renderHotelAreaMap(payload, form) {
  const pins = document.querySelector('[data-hotel-map-pins]');
  if (!pins) return;
  pins.replaceChildren();
  const selectedArea = form.elements.area.value;
  const recommended = payload.properties?.find(property => property.id === payload.recommended);
  const detailArea = payload.areas.find(area => area.id === selectedArea) || payload.areas.find(area => area.id === recommended?.area_id) || payload.areas[0];
  setHotelAreaDetail(detailArea);
  payload.areas.forEach(area => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `hotel-area-pin${area.id === detailArea?.id ? ' is-active' : ''}${area.visible_properties === 0 ? ' is-empty' : ''}`;
    button.dataset.hotelArea = area.id;
    button.style.left = `${area.position.x}%`;
    button.style.top = `${area.position.y}%`;
    button.setAttribute('aria-label', `${area.name}, ממוצע €${area.average_nightly} ללילה`);
    appendTextElement(button, 'strong', `€${area.average_nightly}`);
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
  payload.properties.forEach(property => {
    const card = document.createElement('article');
    card.className = `hotel-offer${property.id === payload.recommended ? ' is-recommended' : ''}`;
    const media = document.createElement('div');
    media.className = 'hotel-offer-media';
    const image = document.createElement('img');
    image.loading = 'lazy';
    image.alt = `${property.name}, ${property.area?.name || payload.destination.city}`;
    const safeLocalImage = /^[a-z0-9._-]+$/i.test(property.image || '') ? property.image : 'city-budapest.webp';
    image.src = `${window.traVelV2?.assetUrl || ''}${safeLocalImage}`;
    media.append(image);
    appendTextElement(media, 'span', property.badge, 'hotel-offer-badge');
    const score = appendTextElement(media, 'strong', `${property.score}`, 'hotel-match-score');
    score.setAttribute('aria-label', `ציון התאמה ${property.score} מתוך 100`);
    card.append(media);

    const body = document.createElement('div');
    body.className = 'hotel-offer-body';
    appendTextElement(body, 'small', `${property.area?.name || ''} · ${'★'.repeat(property.stars)}`, 'hotel-area-line');
    appendTextElement(body, 'h3', property.name);
    appendTextElement(body, 'span', `${property.guest_score}/10 · ${property.review_count.toLocaleString('he-IL')} חוות דעת`, 'hotel-guest-score');
    const route = document.createElement('div');
    route.className = 'hotel-route-fit';
    appendTextElement(route, 'strong', `${property.location.route_minutes} דק׳ למסלול`);
    appendTextElement(route, 'span', property.location.transit);
    body.append(route);
    appendTextElement(body, 'p', `${property.room.name} · ${property.room.size_sqm} מ״ר · עד ${property.room.sleeps} אורחים`, 'hotel-room');

    const tradeoffs = document.createElement('div');
    tradeoffs.className = 'hotel-tradeoffs';
    appendTextElement(tradeoffs, 'span', `✓ ${property.pros[0]}`, 'hotel-pro');
    appendTextElement(tradeoffs, 'span', `△ ${property.cons[0]}`, 'hotel-con');
    body.append(tradeoffs);

    const totals = document.createElement('div');
    totals.className = 'hotel-total-grid';
    const nightly = document.createElement('div');
    appendTextElement(nightly, 'small', 'ללילה, לחדר');
    appendTextElement(nightly, 'strong', property.pricing.nightly_formatted);
    const stay = document.createElement('div');
    appendTextElement(stay, 'small', `${property.stay_nights} לילות · מחיר מלא`);
    appendTextElement(stay, 'strong', property.pricing.total_stay_formatted);
    totals.append(nightly, stay);
    body.append(totals);

    const policyChips = document.createElement('div');
    policyChips.className = 'hotel-policy-chips';
    if (property.policies.free_cancellation) appendTextElement(policyChips, 'span', 'ביטול חינם');
    if (property.policies.pay_at_property) appendTextElement(policyChips, 'span', 'תשלום במקום');
    if (property.amenities.breakfast) appendTextElement(policyChips, 'span', 'ארוחת בוקר');
    if (property.amenities.family) appendTextElement(policyChips, 'span', 'משפחות');
    body.append(policyChips);

    const details = document.createElement('details');
    appendTextElement(details, 'summary', 'מחיר מלא ותנאי החדר');
    const breakdown = document.createElement('div');
    breakdown.className = 'hotel-breakdown';
    [['חדרים ולילות', property.pricing.base_formatted], ['מסים', property.pricing.taxes_formatted], ['עמלות', property.pricing.fees_formatted], ['לאדם בשהייה', property.pricing.per_person_formatted]].forEach(([label, value]) => {
      const line = document.createElement('span');
      appendTextElement(line, 'i', label);
      appendTextElement(line, 'b', value);
      breakdown.append(line);
    });
    appendTextElement(breakdown, 'p', `${property.policies.cancellation_deadline} · כניסה ${property.policies.check_in} · יציאה ${property.policies.check_out}`);
    details.append(breakdown);
    body.append(details);

    const action = document.createElement('button');
    action.className = 'hotel-offer-action';
    action.type = 'button';
    action.classList.toggle('is-assisted', !property.booking.bookable);
    action.textContent = property.booking.bookable ? 'בדיקת זמינות והזמנה' : 'קבלו מחיר חי בוואטסאפ';
    action.addEventListener('click', () => startCommercialHandoff(action, 'hotel', {...property.booking, id: property.id}, payload));
    body.append(action, createSaveOfferButton({
      kind: 'hotel', external_id: property.id, title: property.name,
      subtitle: `${property.area?.name || ''} · ${property.stars}★ · ${property.guest_score}/10`,
      destination: payload.destination?.city || '', route: `${property.location.route_minutes} דקות למסלול`,
      price_label: property.pricing.total_stay_formatted, price_amount: property.pricing.total_stay,
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
  submit.disabled = true;
  form.dataset.state = 'loading';
  if (status) status.textContent = 'משווה אזור, מחיר מלא, תנאים וזמן למסלול...';
  try {
    const response = await fetch(url, {
      headers: {Accept: 'application/json'},
      ...(controller ? {signal: controller.signal} : {})
    });
    const payload = await response.json();
    if (generation !== hotelSearchGeneration) return;
    if (!response.ok) throw new Error(payload.message || `Hotel search failed: ${response.status}`);
    renderHotelAreaMap(payload, form);
    renderHotelProperties(payload);
    const modeLabels = {live: 'מחירי ספקים חיים', mixed: 'נתונים חיים ואומדנים', demo: 'מחירים לאימות בחיפוש'};
    const cacheLabels = {miss: 'עודכן עכשיו', fresh: 'תוצאה שמורה ועדכנית', stale_refreshing: 'מעדכן ברקע', stale_error: 'תוצאה אחרונה תקינה', degraded_fallback: 'חלק מהתוצאות אינן זמינות כרגע'};
    if (status) status.textContent = `${payload.meta.result_count} מקומות · ${payload.search.nights} לילות · ${modeLabels[payload.meta.data_mode] || modeLabels.demo} · ${cacheLabels[payload.meta.cache_state] || 'עודכן'}`;
    form.dataset.state = payload.meta.data_mode;
  } catch (error) {
    if (error?.name === 'AbortError' || generation !== hotelSearchGeneration) return;
    document.querySelector('[data-hotel-results]')?.replaceChildren();
    if (status) status.textContent = 'לא הצלחנו להשלים את השוואת המלונות. בדקו את התאריכים ונסו שוב.';
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
    if (!checkout) return;
    checkout.min = checkin.value;
    if (checkout.value <= checkin.value) {
      const next = new Date(`${checkin.value}T12:00:00`);
      next.setDate(next.getDate() + 4);
      checkout.value = next.toISOString().slice(0, 10);
    }
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
    (context.recommended_addons || []).map(addon => insuranceAddonLabels[addon] || addon)
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
    appendTextElement(button, 'span', (context.recommended_addons || []).map(addon => insuranceAddonLabels[addon] || addon).join(' · '));
    button.addEventListener('click', () => {
      form.elements.trip_type.value = context.trip_type;
      ['adventure_sports', 'winter_sports'].forEach(name => { form.elements[name].checked = false; });
      (context.recommended_addons || []).forEach(addon => {
        if (form.elements[addon]) form.elements[addon].checked = true;
      });
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

function renderInsurancePlans(payload) {
  const container = document.querySelector('[data-insurance-results]');
  if (!container) return;
  container.replaceChildren();
  if (!payload.plans?.length) {
    appendTextElement(container, 'p', 'לא נמצאו תוכניות שמתאימות להשוואה. בדקו את התאריכים והנתונים.', 'insurance-empty');
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
    appendTextElement(total, 'em', 'אומדן בלבד, המחיר הסופי אצל המבטח');
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
    action.classList.toggle('is-assisted', !plan.purchase.purchasable);
    action.textContent = plan.purchase.purchasable ? 'מעבר להצעה ולפוליסה' : 'קבלו הצעה מאומתת בוואטסאפ';
    action.addEventListener('click', () => startCommercialHandoff(action, 'insurance', {...plan.purchase, id: plan.id}, payload));
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
  submit.disabled = true;
  form.dataset.state = 'loading';
  if (status) status.textContent = 'משווה גבולות, הרחבות, שירות, חריגים ומחיר משוער...';
  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {Accept: 'application/json', 'Content-Type': 'application/json'},
      body: JSON.stringify(requestBody),
      ...(controller ? {signal: controller.signal} : {})
    });
    const payload = await response.json();
    if (generation !== insuranceSearchGeneration) return;
    if (!response.ok) throw new Error(payload.message || `Insurance comparison failed: ${response.status}`);
    renderInsuranceRiskMap(payload, form);
    renderInsurancePlans(payload);
    const policyNote = document.querySelector('[data-insurance-policy-note]');
    if (policyNote) policyNote.textContent = `${payload.destination.medical_cost_context} ${payload.destination.policy_note}`;
    const modeLabels = {live: 'נתוני מבטחים מחוברים', mixed: 'נתונים חיים ואומדנים', demo: 'כיסויים ומחירים לאימות'};
    const cacheLabels = {miss: 'עודכן עכשיו', fresh: 'תוצאה שמורה ועדכנית', stale_refreshing: 'מעדכן ברקע', stale_error: 'תוצאה אחרונה תקינה', degraded_fallback: 'חלק מהתוצאות אינן זמינות כרגע', bypass_sensitive: 'לא נשמר מטעמי פרטיות'};
    const assessment = payload.meta.medical_assessment_required ? ' · נדרש בירור רפואי' : '';
    if (status) status.textContent = `${payload.meta.result_count} חלופות · ${payload.query.trip_days} ימים · ${modeLabels[payload.meta.data_mode] || modeLabels.demo}${assessment} · ${cacheLabels[payload.meta.cache_state] || 'עודכן'}`;
    form.dataset.state = payload.meta.data_mode;
  } catch (error) {
    if (error?.name === 'AbortError' || generation !== insuranceSearchGeneration) return;
    document.querySelector('[data-insurance-results]')?.replaceChildren();
    if (status) status.textContent = 'לא הצלחנו להשלים את ההשוואה. בדקו תאריכים ונסו שוב.';
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
      if (status) status.textContent = form.dataset.initialStatus || 'ההשוואה ליעד הזה עדיין אינה זמינה.';
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
    if (status) status.textContent = form.dataset.initialStatus || 'ההשוואה ליעד הזה עדיין אינה זמינה.';
  } else {
    searchInsuranceQuotes(form);
  }
}

let packageComparisonPayload = null;

function packageRiskLabel(risk) {
  return {low: 'סיכון תפעולי נמוך', medium: 'דורש יותר תשומת לב', high: 'סיכון תפעולי גבוה'}[risk] || 'סיכון דורש בדיקה';
}

function selectTripPackage(packageId, shouldFocus = false) {
  if (!packageComparisonPayload) return;
  const tripPackage = packageComparisonPayload.packages.find(item => item.id === packageId);
  if (!tripPackage) return;
  document.querySelectorAll('[data-package-card]').forEach(card => card.classList.toggle('is-selected', card.dataset.packageCard === packageId));
  document.querySelectorAll('[data-package-pin]').forEach(pin => {
    const selected = pin.dataset.packagePin === packageId;
    pin.classList.toggle('is-active', selected);
    pin.setAttribute('aria-pressed', String(selected));
  });
  const fields = {
    '[data-package-map-title]': tripPackage.name,
    '[data-package-map-route]': `${tripPackage.flight.airline} · ${tripPackage.flight.stops_label} · ${tripPackage.stay.name} · ${tripPackage.stay.area_name}`,
    '[data-package-map-total]': tripPackage.pricing.total_party_formatted,
    '[data-package-map-nights]': String(packageComparisonPayload.trip.nights),
    '[data-package-map-score]': `${tripPackage.score}/100`
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
  payload.packages.forEach(tripPackage => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'package-map-pin';
    button.dataset.packagePin = tripPackage.id;
    button.style.left = `${tripPackage.stay.position.x}%`;
    button.style.top = `${tripPackage.stay.position.y}%`;
    button.setAttribute('aria-label', `${tripPackage.name}: ${tripPackage.pricing.total_party_formatted} לכל ההרכב, ${tripPackage.stay.area_name}`);
    button.setAttribute('aria-pressed', 'false');
    appendTextElement(button, 'strong', tripPackage.pricing.total_party_formatted);
    appendTextElement(button, 'span', tripPackage.stay.area_name);
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
  payload.packages.forEach(tripPackage => {
    const card = document.createElement('article');
    card.className = `trip-package-card${tripPackage.id === payload.recommended ? ' is-recommended' : ''}`;
    card.dataset.packageCard = tripPackage.id;

    const head = document.createElement('div');
    head.className = 'package-card-head';
    const identity = document.createElement('div');
    appendTextElement(identity, 'small', tripPackage.badge, 'package-card-badge');
    appendTextElement(identity, 'h3', tripPackage.name);
    appendTextElement(identity, 'span', `${tripPackage.stay.area_name} · ${tripPackage.stay.route_minutes} דקות למסלול`);
    head.append(identity);
    const score = appendTextElement(head, 'strong', `${tripPackage.score}`, 'package-match-score');
    score.setAttribute('aria-label', `ציון התאמה ${tripPackage.score} מתוך 100`);
    card.append(head);

    const journey = document.createElement('div');
    journey.className = 'package-journey-summary';
    appendPackageBreakdownRow(journey, `${tripPackage.flight.airline} · ${tripPackage.flight.departure_time} עד ${tripPackage.flight.return_time}`, `${tripPackage.flight.stops_label} · ${tripPackage.flight.duration_label}`, 'plane-takeoff');
    appendPackageBreakdownRow(journey, `${tripPackage.stay.name} · ${tripPackage.stay.stars}★`, `${payload.trip.nights} לילות · ${tripPackage.stay.guest_score}/10`, 'hotel');
    appendPackageBreakdownRow(journey, tripPackage.insurance.name, tripPackage.insurance.tier === 'none' ? 'לא נכלל' : 'אומדן עד אימות מבטח', 'shield-check');
    card.append(journey);

    const chips = document.createElement('div');
    chips.className = 'package-inclusion-chips';
    const inclusionLabels = [
      [tripPackage.inclusions.baggage, 'כבודה'],
      [tripPackage.inclusions.breakfast, 'ארוחת בוקר'],
      [tripPackage.inclusions.free_cancellation, 'ביטול גמיש'],
      [tripPackage.inclusions.transfers_requested, 'העברה'],
      [tripPackage.inclusions.insurance_in_calculation, 'ביטוח בחישוב']
    ];
    inclusionLabels.forEach(([included, label]) => appendTextElement(chips, 'span', `${included ? '✓' : '+'} ${label}`, included ? 'is-included' : 'is-extra'));
    card.append(chips);

    const traits = document.createElement('div');
    traits.className = 'package-trait-grid';
    [['נוחות', tripPackage.traits.comfort], ['גמישות', tripPackage.traits.flexibility], ['מיקום', tripPackage.traits.location]].forEach(([label, value]) => {
      const trait = document.createElement('span');
      appendTextElement(trait, 'small', label);
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
    appendPackageBreakdownRow(breakdown, 'טיסות', tripPackage.pricing.flight_formatted);
    appendPackageBreakdownRow(breakdown, 'לינה', tripPackage.pricing.stay_formatted);
    appendPackageBreakdownRow(breakdown, 'ביטוח', tripPackage.pricing.insurance_formatted);
    appendPackageBreakdownRow(breakdown, 'העברות', tripPackage.pricing.transfers_formatted);
    appendPackageBreakdownRow(breakdown, 'תוספות', tripPackage.pricing.addons_formatted);
    pricing.append(breakdown);
    const total = document.createElement('div');
    total.className = 'package-price-total';
    appendTextElement(total, 'small', `סך הכול ל-${payload.trip.travelers} נוסעים`);
    appendTextElement(total, 'strong', tripPackage.pricing.total_party_formatted);
    appendTextElement(total, 'span', `${tripPackage.pricing.per_person_formatted} לאדם · להמחשה`);
    pricing.append(total);
    card.append(pricing);

    const tradeoffs = document.createElement('div');
    tradeoffs.className = 'package-tradeoffs';
    appendTextElement(tradeoffs, 'span', `✓ ${tripPackage.pros[0]}`, 'package-pro');
    appendTextElement(tradeoffs, 'span', `△ ${tripPackage.cons[0]}`, 'package-con');
    appendTextElement(tradeoffs, 'small', packageRiskLabel(tripPackage.traits.risk));
    card.append(tradeoffs);

    const details = document.createElement('details');
    appendTextElement(details, 'summary', 'כל הרכיבים והתנאים לבדיקה');
    const list = document.createElement('ul');
    appendTextElement(list, 'li', `כרטיס: ${tripPackage.flight.ticket_mode} · כבודה ${tripPackage.flight.baggage_included ? 'כלולה' : 'מחושבת כתוספת'}`);
    appendTextElement(list, 'li', `מלון: ${tripPackage.stay.room} · ${tripPackage.stay.free_cancellation ? 'ביטול גמיש בדוגמה' : 'ללא ביטול גמיש'}`);
    appendTextElement(list, 'li', `ביטוח: תוכנית בדיונית ולא פוליסה · גבול רפואי מוצג ${tripPackage.insurance.medical_limit_formatted}`);
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
    checkout.classList.toggle('is-assisted', !tripPackage.booking.bookable);
    checkout.textContent = tripPackage.booking.bookable ? 'עברו להזמנה מאומתת' : 'קבלו מחיר מלא בוואטסאפ';
    checkout.addEventListener('click', () => startCommercialHandoff(checkout, 'package', {...tripPackage.booking, id: tripPackage.id}, payload));
    const saveAction = createSaveOfferButton({
      kind: 'package', external_id: tripPackage.id, title: tripPackage.name,
      subtitle: `${tripPackage.flight.airline} · ${tripPackage.stay.name} · ${payload.trip.nights} לילות`,
      destination: payload.destination?.city || '', route: `${payload.origin?.code || 'TLV'} → ${payload.destination?.code || ''}`,
      price_label: tripPackage.pricing.total_party_formatted, price_amount: tripPackage.pricing.total_party,
      currency: payload.search?.currency || 'USD', data_mode: payload.meta?.data_mode || 'demo', href: `${window.traVelV2?.homeUrl || '/'}packages/`
    });
    actions.append(mapAction, saveAction, checkout);
    card.append(actions);
    container.append(card);
  });
  renderPackageMap(payload);
  selectTripPackage(payload.recommended);
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
  submit.disabled = true;
  form.dataset.state = 'loading';
  if (status) status.textContent = 'מחבר טיסה, לינה, תנאים, העברות, כיסוי ועלות מלאה...';
  try {
    const response = await fetch(`${endpoint}?${params.toString()}`, {headers: {Accept: 'application/json'}});
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || `Package search failed: ${response.status}`);
    renderTripPackages(payload);
    const modeLabels = {live: 'נתוני ספקים מחוברים', mixed: 'נתונים חיים ואומדנים', demo: 'רכיבים לאימות בחיפוש'};
    const cacheLabels = {miss: 'עודכן עכשיו', fresh: 'תוצאה שמורה ועדכנית', stale_refreshing: 'מתעדכן ברקע', stale_error: 'תוצאה אחרונה תקינה', degraded_fallback: 'חלק מהתוצאות אינן זמינות כרגע'};
    if (status) status.textContent = `${payload.meta.result_count} חלופות · ${payload.trip.nights} לילות · מחיר לכל ${payload.trip.travelers} הנוסעים · ${modeLabels[payload.meta.data_mode] || modeLabels.demo} · ${cacheLabels[payload.meta.cache_state] || 'עודכן'}`;
    form.dataset.state = payload.meta.data_mode;
  } catch (error) {
    document.querySelector('[data-package-results]')?.replaceChildren();
    document.querySelector('[data-package-map-pins]')?.replaceChildren();
    if (status) status.textContent = 'לא הצלחנו להרכיב חלופות. בדקו תאריכים, תקציב והרכב חדרים ונסו שוב.';
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
    if (!returnDate) return;
    returnDate.min = departure.value;
    if (returnDate.value <= departure.value) {
      const next = new Date(`${departure.value}T12:00:00`);
      next.setDate(next.getDate() + 4);
      returnDate.value = next.toISOString().slice(0, 10);
    }
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
    return url.origin === window.location.origin ? url.href : window.traVelV2?.homeUrl || '/';
  } catch (error) {
    return window.traVelV2?.homeUrl || '/';
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

function renderWorkspaceMap(items) {
  const pins = document.querySelector('[data-workspace-map-pins]');
  if (!pins) return;
  const orbit = pins.closest('[data-workspace-map]');
  if (orbit) orbit.dataset.coordinateMode = 'option-orbit';
  const orbitLabel = orbit?.querySelector('[data-workspace-orbit-label]');
  if (orbitLabel) orbitLabel.textContent = 'מסלול אפשרויות · לא מיקום גאוגרפי';
  pins.replaceChildren();
  const positions = [[64,28],[38,21],[24,43],[57,52],[73,43],[40,62],[18,67],[82,64]];
  items.slice(0, 8).forEach((item, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `workspace-map-pin${index === 0 ? ' is-active' : ''}`;
    button.dataset.workspaceMapPin = item.id;
    button.style.left = `${positions[index][0]}%`;
    button.style.top = `${positions[index][1]}%`;
    button.setAttribute('aria-label', `${item.title}, ${item.price_label || workspaceMoney(item)}, נקודה במסלול האפשרויות ולא מיקום גאוגרפי`);
    button.setAttribute('aria-pressed', String(index === 0));
    appendTextElement(button, 'strong', item.price_label || workspaceMoney(item));
    appendTextElement(button, 'span', item.destination || item.title);
    button.addEventListener('click', () => selectWorkspaceMapItem(item.id));
    pins.append(button);
  });
  if (items[0]) selectWorkspaceMapItem(items[0].id);
}

async function removeWorkspaceItem(itemId) {
  const workspace = travelerWorkspace || readLocalWorkspace();
  const nextWorkspace = {...workspace, items: workspace.items.filter(item => item.id !== itemId)};
  if (!writeLocalWorkspace(nextWorkspace)) {
    showWorkspaceToast('האפשרות לא הוסרה כי השינוי לא נשמר בדפדפן.', 'triangle-alert');
    return {localSaved: false, accountSynced: false};
  }
  renderWorkspaceDashboard();
  showWorkspaceToast('האפשרות הוסרה מהנסיעה', 'trash-2');
  try {
    const serverWorkspace = await workspaceRequest(`/items/${encodeURIComponent(itemId)}`, {method: 'DELETE'});
    if (window.traVelV2?.isLoggedIn && !serverWorkspace) {
      showWorkspaceToast('האפשרות הוסרה במכשיר; סנכרון החשבון אינו זמין כרגע', 'cloud-off');
      return {localSaved: true, accountSynced: false};
    }
    return {localSaved: true, accountSynced: Boolean(serverWorkspace)};
  } catch (error) {
    showWorkspaceToast('האפשרות הוסרה במכשיר; השינוי בחשבון לא אושר', 'cloud-off');
    console.warn(error);
    return {localSaved: true, accountSynced: false};
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
  renderWorkspaceDashboard(itemId);
  showWorkspaceToast(enabled ? 'יעד המחיר נשמר; המשלוח ייפתח עם ספק חי' : 'מעקב המחיר הוסר', enabled ? 'bell-ring' : 'bell-off');
  try {
    const serverWorkspace = await workspaceRequest(`/items/${encodeURIComponent(itemId)}/watch`, {method: 'PUT', body: JSON.stringify({enabled, target_amount: target})});
    if (window.traVelV2?.isLoggedIn && !serverWorkspace) {
      showWorkspaceToast('יעד המחיר נשמר במכשיר; סנכרון החשבון אינו זמין כרגע', 'cloud-off');
      return {localSaved: true, accountSynced: false};
    }
    return {localSaved: true, accountSynced: Boolean(serverWorkspace)};
  } catch (error) {
    showWorkspaceToast('יעד המחיר נשמר במכשיר; השינוי בחשבון לא אושר', 'cloud-off');
    console.warn(error);
    return {localSaved: true, accountSynced: false};
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
    appendTextElement(watchState, 'span', `יעד ${workspaceMoney(item, item.watch.target_amount)} · ממתין לספק חי`);
  } else {
    appendTextElement(watchState, 'span', 'אפשר להצמיד יעד מחיר בלי להפעיל משלוח');
  }
  card.append(watchState);

  const actions = document.createElement('div');
  actions.className = 'workspace-card-actions';
  const mapButton = document.createElement('button');
  mapButton.type = 'button';
  const mapIcon = document.createElement('i');
  mapIcon.dataset.lucide = 'orbit';
  mapButton.append(mapIcon, document.createTextNode('במסלול האפשרויות'));
  mapButton.addEventListener('click', () => {
    selectWorkspaceMapItem(item.id);
    document.querySelector('[data-workspace-map]')?.scrollIntoView({behavior: preferredScrollBehavior(), block: 'center'});
  });
  const watchButton = document.createElement('button');
  watchButton.type = 'button';
  watchButton.className = item.watch?.enabled ? 'is-watching' : '';
  const watchIcon = document.createElement('i');
  watchIcon.dataset.lucide = item.watch?.enabled ? 'bell-off' : 'bell-plus';
  watchButton.append(watchIcon, document.createTextNode(item.watch?.enabled ? 'בטלו מעקב' : 'יעד מחיר'));
  watchButton.addEventListener('click', () => toggleWorkspaceWatch(item.id));
  const removeButton = document.createElement('button');
  removeButton.type = 'button';
  removeButton.className = 'workspace-delete';
  removeButton.setAttribute('aria-label', `הסרת ${item.title}`);
  const removeIcon = document.createElement('i');
  removeIcon.dataset.lucide = 'trash-2';
  removeButton.append(removeIcon);
  removeButton.addEventListener('click', () => removeWorkspaceItem(item.id));
  actions.append(mapButton, watchButton, removeButton);
  card.append(actions);

  const open = document.createElement('a');
  open.href = safeWorkspaceHref(item.href);
  open.className = 'text-link';
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
  if (container) container.replaceChildren(...visible.map(renderWorkspaceCard));
  if (empty) empty.hidden = visible.length > 0;
  const count = document.querySelector('[data-workspace-count]');
  const watches = document.querySelector('[data-workspace-watch-count]');
  const destinations = document.querySelector('[data-workspace-destination-count]');
  if (count) count.textContent = String(items.length);
  if (watches) watches.textContent = String(items.filter(item => item.watch?.enabled).length);
  if (destinations) destinations.textContent = String(new Set(items.map(item => item.destination).filter(Boolean)).size);
  renderWorkspaceMap(items);
  if (preferredItemId) selectWorkspaceMapItem(preferredItemId);
  const status = document.querySelector('[data-workspace-status]');
  if (status) {
    const storageLabel = workspaceLocalStorageAvailable ? 'נשמרות באופן פרטי' : 'זמינות בלשונית הזאת בלבד';
    status.textContent = items.length ? `${items.length} אפשרויות · ${storageLabel} · התראות אינן פעילות עדיין` : 'סביבת העבודה מוכנה. שמרו אפשרות אחת כדי להתחיל להשוות.';
  }
  renderIcons();
}

function hydrateWorkspacePreferences(preferences) {
  const form = document.querySelector('[data-workspace-preferences]');
  if (!form) return;
  ['home_airport', 'currency', 'budget', 'max_stops', 'party_style'].forEach(name => {
    if (form.elements[name] && preferences[name] !== undefined) form.elements[name].value = String(preferences[name]);
  });
  form.querySelectorAll('[name="priorities"]').forEach(input => { input.checked = (preferences.priorities || []).includes(input.value); });
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
  showWorkspaceToast('ההעדפות נשמרו במכשיר. בודקים סנכרון לחשבון...', 'cloud');
  try {
    const serverWorkspace = await workspaceRequest('/preferences', {method: 'PUT', body: JSON.stringify(preferences)});
    if (serverWorkspace) {
      writeLocalWorkspace(mergeTravelerWorkspaces(nextWorkspace, serverWorkspace));
      showWorkspaceToast('ההעדפות אושרו בחשבון ובמכשיר', 'cloud-check');
      return {localSaved: true, accountSynced: true};
    }
    showWorkspaceToast('ההעדפות נשמרו במכשיר; סנכרון החשבון אינו זמין כרגע', 'cloud-off');
  } catch (error) {
    showWorkspaceToast('ההעדפות נשמרו במכשיר; השינוי בחשבון לא אושר', 'cloud-off');
    console.warn(error);
  }
  return {localSaved: true, accountSynced: false};
}

function renderWorkspaceQuoteCaseCard(initialCase, confirmedForward = false) {
  let caseData = initialCase;
  const card = document.createElement('article');
  card.className = `workspace-quote-card${confirmedForward ? ' is-advancing' : ''}`;
  card.dataset.status = String(caseData.status || 'queued');
  card.dataset.version = String(Math.max(0, Number(caseData.version) || 0));

  const head = document.createElement('div');
  head.className = 'workspace-quote-card-head';
  const identity = document.createElement('div');
  appendTextElement(identity, 'small', 'בקשת סיוע');
  appendTextElement(identity, 'strong', caseData.reference || caseData.case_id, 'workspace-quote-reference');
  const status = document.createElement('span');
  status.className = 'workspace-quote-card-status';
  status.textContent = quoteCaseStatusLabel(caseData);
  head.append(identity, status);
  card.append(head);

  appendTextElement(card, 'h3', quoteCaseSummaryText(caseData));
  const progress = document.createElement('div');
  progress.className = 'workspace-quote-card-progress';
  const progressState = quoteCaseProgressState(caseData.status);
  ['תוכנית', 'תור', 'בדיקה', 'המשך'].forEach((label, index) => {
    const item = document.createElement('span');
    const state = quoteCaseStepState(progressState, index);
    item.dataset.state = state;
    item.textContent = label;
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
  appendTextElement(meta, 'span', `גרסת תוכנית ${Math.max(1, Number(caseData.source?.request_revision) || 1)}`);
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
  const openIcon = document.createElement('i');
  openIcon.dataset.lucide = 'sparkles';
  open.append(openIcon, document.createTextNode('פתחו את התוכנית'));
  open.addEventListener('click', () => {
    if (caseData.source?.run_id) storeAgentRunSession(caseData.source.run_id);
    window.location.assign(safeWorkspaceHref(`${window.traVelV2?.homeUrl || '/'}ai-planner/`));
  });
  const handoff = document.createElement('button');
  handoff.type = 'button';
  handoff.className = 'is-secondary';
  const handoffIcon = document.createElement('i');
  handoffIcon.dataset.lucide = 'message-circle';
  handoff.append(handoffIcon, document.createTextNode('המשיכו בוואטסאפ'));
  handoff.addEventListener('click', async () => {
    if (handoff.dataset.state === 'loading') return;
    const popup = window.open('about:blank', '_blank');
    if (popup) popup.opener = null;
    handoff.dataset.state = 'loading';
    handoff.disabled = true;
    actionStatus.dataset.state = 'running';
    actionStatus.textContent = 'מכינים קישור מאובטח עם מספר הבקשה...';
    try {
      const payload = await requestQuoteCaseHandoff(caseData, handoff);
      const updatedCase = normalizeQuoteCasePayload(payload);
      if (updatedCase) caseData = updatedCase;
      const url = safeQuoteCaseHandoffUrl(payload?.handoff_url);
      if (!url) throw new Error('The handoff URL is unavailable.');
      delete handoff.dataset.idempotencyKey;
      actionStatus.dataset.state = payload?.replayed ? 'reused' : 'success';
      actionStatus.textContent = payload?.replayed
        ? 'הקישור המאובטח הקיים נפתח מחדש ללא עדכון כפול.'
        : 'הקישור הוכן ונרשם בבקשה.';
      if (popup) popup.location.replace(url);
      else window.location.assign(url);
    } catch (error) {
      if (popup) popup.close();
      actionStatus.dataset.state = 'error';
      actionStatus.textContent = quoteCaseErrorMessage(error);
    } finally {
      delete handoff.dataset.state;
      handoff.disabled = false;
    }
  });
  actions.append(open, handoff);
  card.append(actions);
  return card;
}

function renderWorkspaceQuoteCases(cases, confirmedForwardIds = new Set()) {
  const root = document.querySelector('[data-workspace-quote-cases]');
  const grid = root?.querySelector('[data-workspace-quote-grid]');
  const empty = root?.querySelector('[data-workspace-quote-empty]');
  const status = root?.querySelector('[data-workspace-quote-status]');
  if (!root || !grid) return;
  const active = (Array.isArray(cases) ? cases : [])
    .filter(caseData => caseData?.case_id && quoteCaseActiveStatuses.has(caseData.status))
    .sort((a, b) => String(b.updated_at || '').localeCompare(String(a.updated_at || '')));
  grid.replaceChildren(...active.map(caseData => renderWorkspaceQuoteCaseCard(caseData, confirmedForwardIds.has(caseData.case_id))));
  if (empty) empty.hidden = active.length > 0;
  if (status) status.textContent = active.length
    ? `${active.length} בקשות פעילות עם מצב והיסטוריה מאומתים`
    : 'אין בקשות פעילות. תוכנית שמוכנה לסיוע תופיע כאן לאחר אישורכם.';
  root.setAttribute('aria-busy', 'false');
  renderIcons();
}

function workspaceQuoteCaseSnapshot(cases) {
  return new Map((Array.isArray(cases) ? cases : [])
    .filter(caseData => caseData?.case_id)
    .map(caseData => [caseData.case_id, {
      case_id: caseData.case_id,
      status: String(caseData.status || ''),
      version: Math.max(0, Number(caseData.version) || 0)
    }]));
}

function workspaceQuoteCasesChanged(nextSnapshot) {
  if (nextSnapshot.size !== workspaceQuoteCaseRuntime.snapshot.size) return true;
  return [...nextSnapshot].some(([caseId, next]) => {
    const previous = workspaceQuoteCaseRuntime.snapshot.get(caseId);
    return !previous || previous.version !== next.version || previous.status !== next.status;
  });
}

function scheduleWorkspaceQuoteCasePoll(delay = 20000) {
  if (workspaceQuoteCaseRuntime.timer) window.clearTimeout(workspaceQuoteCaseRuntime.timer);
  workspaceQuoteCaseRuntime.timer = 0;
  if (!document.querySelector('[data-workspace-quote-cases]')) return;
  const visibilityDelay = document.visibilityState === 'hidden' ? Math.max(delay, 60000) : delay;
  workspaceQuoteCaseRuntime.timer = window.setTimeout(() => pollWorkspaceQuoteCases(), visibilityDelay);
}

async function pollWorkspaceQuoteCases() {
  workspaceQuoteCaseRuntime.timer = 0;
  if (document.visibilityState === 'hidden') {
    scheduleWorkspaceQuoteCasePoll(60000);
    return;
  }
  await loadWorkspaceQuoteCases({polling: true});
}

async function loadWorkspaceQuoteCases({polling = false} = {}) {
  const root = document.querySelector('[data-workspace-quote-cases]');
  if (!root || workspaceQuoteCaseRuntime.inFlight) return;
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
    const cases = Array.isArray(payload?.cases) ? payload.cases : [];
    const nextSnapshot = workspaceQuoteCaseSnapshot(cases);
    const changed = !polling || workspaceQuoteCasesChanged(nextSnapshot);
    const confirmedForwardIds = new Set();
    if (polling && changed) {
      cases.forEach(caseData => {
        const previous = workspaceQuoteCaseRuntime.snapshot.get(caseData?.case_id);
        if (isConfirmedQuoteCaseForward(previous, caseData)) confirmedForwardIds.add(caseData.case_id);
      });
    }
    if (changed) renderWorkspaceQuoteCases(cases, confirmedForwardIds);
    else root.setAttribute('aria-busy', 'false');
    workspaceQuoteCaseRuntime.snapshot = nextSnapshot;
    workspaceQuoteCaseRuntime.failures = 0;
  } catch (error) {
    root.setAttribute('aria-busy', 'false');
    const hiddenAbort = error?.name === 'AbortError' && !timedOut && document.visibilityState === 'hidden';
    if (!hiddenAbort) {
      workspaceQuoteCaseRuntime.failures = Math.min(8, workspaceQuoteCaseRuntime.failures + 1);
      if (status && (!polling || workspaceQuoteCaseRuntime.failures >= 3)) status.textContent = error?.status === 404
        ? 'שירות בקשות הסיוע עדיין אינו זמין באתר. ננסה להתחבר שוב אוטומטית.'
        : 'העדכון החי אינו זמין כרגע. המצב האחרון שאושר נשאר מוצג וננסה שוב אוטומטית.';
      if (!polling) {
        const empty = root.querySelector('[data-workspace-quote-empty]');
        if (empty) empty.hidden = false;
      }
    }
  } finally {
    if (timeoutId) window.clearTimeout(timeoutId);
    if (workspaceQuoteCaseRuntime.controller === controller) workspaceQuoteCaseRuntime.controller = null;
    workspaceQuoteCaseRuntime.inFlight = false;
    const retryDelay = workspaceQuoteCaseRuntime.failures
      ? Math.min(180000, 10000 * (2 ** Math.min(workspaceQuoteCaseRuntime.failures - 1, 4)))
      : 20000;
    scheduleWorkspaceQuoteCasePoll(retryDelay);
  }
}

async function initTravelerWorkspace() {
  const root = document.querySelector('[data-traveler-workspace]');
  const hasMapSaveControls = Boolean(document.querySelector('[data-map-result] .save-button'));
  if (!root && !hasMapSaveControls) return;
  travelerWorkspace = readLocalWorkspace();
  if (root) {
    renderWorkspaceDashboard();
    hydrateWorkspacePreferences(travelerWorkspace.preferences);
    loadWorkspaceQuoteCases();
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState !== 'visible') {
        workspaceQuoteCaseRuntime.controller?.abort();
        return;
      }
      scheduleWorkspaceQuoteCasePoll(250);
    });
  }
  document.querySelectorAll('[data-workspace-filter]').forEach(button => button.addEventListener('click', () => {
    activeWorkspaceFilter = button.dataset.workspaceFilter;
    document.querySelectorAll('[data-workspace-filter]').forEach(filter => filter.classList.toggle('is-active', filter === button));
    renderWorkspaceDashboard();
  }));
  const form = document.querySelector('[data-workspace-preferences]');
  form?.addEventListener('submit', event => {
    event.preventDefault();
    saveWorkspacePreferences(form);
  });
  form?.elements.home_airport?.addEventListener('input', event => {
    event.target.value = event.target.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3);
  });
  if (!window.traVelV2?.isLoggedIn) return;
  try {
    const serverWorkspace = await workspaceRequest();
    const local = readLocalWorkspace();
    const mergedWorkspace = mergeTravelerWorkspaces(local, serverWorkspace);
    if (!writeLocalWorkspace(mergedWorkspace)) travelerWorkspace = mergedWorkspace;
    if (root) {
      renderWorkspaceDashboard();
      hydrateWorkspacePreferences(travelerWorkspace.preferences);
    }
    refreshMapSaveControls();
  } catch (error) {
    const status = document.querySelector('[data-workspace-status]');
    if (status) status.textContent = 'מוצגות השמירות במכשיר; הסנכרון לחשבון אינו זמין כרגע.';
    console.warn(error);
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

  const setDrawerState = (opening, returnFocus = false) => {
    if (!menuButton || !drawer) return;
    drawer.classList.toggle('is-open', opening);
    drawer.setAttribute('aria-hidden', String(!opening));
    menuButton.setAttribute('aria-expanded', String(opening));
    menuButton.setAttribute('aria-label', opening ? 'סגירת תפריט' : 'פתיחת תפריט');
    document.body.classList.toggle('mobile-navigation-open', opening);
    if (opening) {
      drawerWasOpenedBy = document.activeElement;
      window.requestAnimationFrame(() => drawerClose?.focus());
    } else if (returnFocus && drawerWasOpenedBy instanceof HTMLElement) {
      drawerWasOpenedBy.focus();
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
  document.querySelectorAll('[data-map-result] .save-button').forEach(button => button.addEventListener('click', () => {
    if (button.disabled || discoveryRequestPending) return;
    const data = destinationData[activeDestination];
    if (data) saveWorkspaceItem(mapDestinationWorkspaceItem(data), button);
  }));

  document.querySelectorAll('[data-map-zoom]').forEach(button => button.addEventListener('click', () => {
    const globe = button.closest('.globe-panel, .compact-map, .world-canvas')?.querySelector('.globe');
    if (!globe) return;
    if (globe.matches('[data-globe-3d]') && window.traVelGlobe3D) {
      const handled = window.traVelGlobe3D.zoom(button.dataset.mapZoom);
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
      readDiscoveryStateFromUrl();
      const historyFocus = String(event.state?.focus || '').toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 60);
      if (!discoveryDestinationLocked && historyFocus && destinationData[historyFocus]) activeDestination = historyFocus;
      syncDiscoveryControls();
      updatePins();
      if (destinationData[activeDestination]) setActiveDestination(activeDestination, null, false);
      hydrateDiscovery(discoveryRequestParams(!discoveryDestinationLocked && historyFocus ? { focus: historyFocus } : {}));
    });
  }
}

function initControls() {
  document.querySelectorAll('.product-tabs button').forEach(button => button.addEventListener('click', () => {
    document.querySelectorAll('.product-tabs button').forEach(item => item.classList.remove('is-active'));
    button.classList.add('is-active');
    const searchDock = document.querySelector('.search-dock');
    if (searchDock && button.dataset.productAction) searchDock.action = button.dataset.productAction;
  }));
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
  quoteCaseLoading: false
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

function agentStatusLabel(status) {
  const labels = {
    created: 'הריצה הפרטית נפתחה',
    provider_error: 'פירוש הבקשה נכשל',
    needs_clarification: 'נדרשת הבהרה לפני חיפוש',
    request_ready: 'הבקשה מובנית ומוכנה לשלב הבא',
    searching: 'השרת מדווח על חיפוש ספקים פעיל',
    proposal_ready: 'השרת החזיר הצעה לבדיקה',
    approval_required: 'נדרש אישור מפורש לפעולה מוגנת',
    completed: 'הריצה הסתיימה',
    failed: 'הריצה נכשלה',
    cancelled: 'הריצה בוטלה'
  };
  return labels[status] || 'מצב הריצה התקבל מהשרת';
}

function agentWorkbenchRoot(root) {
  return root.closest('.ai-planner-column') || root;
}

function setAgentWorkbenchStatus(root, message, state = '') {
  const status = agentWorkbenchRoot(root).querySelector('[data-agent-run-state]');
  if (!status) return;
  status.textContent = message;
  status.dataset.state = state;
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
    empty.textContent = 'אירועי הריצה יופיעו כאן לאחר שהשרת יקבל את הבקשה.';
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
    revisionStatus.textContent = 'העדכון נשאר בריצה הפרטית הזאת. הטקסט החופשי אינו נשמר.';
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
  summary.textContent = request.summary || 'השרת החזיר בקשת נסיעה מובנית.';
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
      ? 'ענו על השאלות הפתוחות במשפט אחד. הסוכן ישמור את שאר הפרטים ויבדוק מחדש מה חסר.'
      : 'אפשר לשנות יעד, תקציב, תאריכים, נוסעים או העדפות בלי לפתוח תוכנית חדשה.';
  }
  if (revisionBadge) revisionBadge.textContent = `גרסה ${Math.max(1, Number(request.revision) || 1)}`;
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
  return labels[phase] || 'עדכון שרת';
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
    supplier.textContent = latest.message;
    return;
  }
  if (run?.status === 'needs_clarification') {
    supplier.hidden = false;
    supplier.dataset.state = 'not-started';
    supplier.textContent = 'חיפוש ספקים לא התחיל. השרת ממתין לתשובה על שאלות החובה.';
    return;
  }
  supplier.hidden = true;
  supplier.textContent = '';
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
    || (typeof caseData?.status_label === 'string' && caseData.status_label.trim() ? caseData.status_label : 'מצב הבקשה התקבל מהשרת');
}

function quoteCaseNextAction(caseData) {
  if (typeof caseData?.next_action === 'string' && caseData.next_action.trim()) return caseData.next_action;
  const labels = {
    queued: 'אין צורך לעשות דבר כרגע. הבקשה ממתינה לצוות Tra-Vel.',
    in_review: 'הצוות בודק את התוכנית. עדכון חדש יופיע כאן כשהמצב ישתנה.',
    needs_information: 'פתחו את השיחה בוואטסאפ והשלימו את הפרט שביקש המומחה.',
    ready_for_assistance: 'המשיכו לשיחה עם המומחה כדי לבדוק מחיר, זמינות ותנאים.',
    closed_no_quote: 'אפשר לעדכן את התוכנית ולפתוח בקשת סיוע חדשה.',
    cancelled: 'הבקשה אינה פעילה. התוכנית הפרטית נשארה זמינה לעדכון.',
    expired: 'פתחו בקשת סיוע חדשה אם התוכנית עדיין רלוונטית.'
  };
  return labels[caseData?.status]
    || (typeof caseData?.next_action?.label === 'string' && caseData.next_action.label.trim() ? caseData.next_action.label : 'המתינו לעדכון מאומת מהשרת.');
}

function quoteCaseSummaryText(caseData) {
  if (typeof caseData?.summary === 'string' && caseData.summary.trim()) return caseData.summary;
  if (!caseData?.summary || typeof caseData.summary !== 'object') return 'בקשת הסיוע קשורה לתוכנית המובנית שמופיעה למעלה.';
  const title = typeof caseData.summary.title === 'string' ? caseData.summary.title.trim() : '';
  const destinations = Array.isArray(caseData.summary.destinations) ? caseData.summary.destinations.filter(Boolean).join(', ') : '';
  const origin = typeof caseData.summary.origin === 'string' ? caseData.summary.origin.trim() : '';
  const route = [origin, destinations].filter(Boolean).join(' → ');
  const date = typeof caseData.summary.date_text === 'string' ? caseData.summary.date_text.trim() : '';
  return [title, route, date].filter(Boolean).join(' · ') || 'בקשת סיוע לתוכנית הנסיעה';
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
  if (error?.status === 409) return 'הבקשה השתנתה במקום אחר. המצב העדכני ייטען מחדש לפני פעולה נוספת.';
  if (error?.status === 401 || error?.status === 403) return 'הגישה לבקשת הסיוע פגה. פתחו את התוכנית מחדש כדי להמשיך.';
  if (error?.status === 404 || error?.code === 'agent_unavailable') return 'שירות בקשות הסיוע עדיין אינו זמין באתר.';
  if (error?.status === 429) return 'נשלחו יותר מדי פעולות בזמן קצר. המתינו רגע ונסו שוב.';
  return 'הפעולה לא אושרה בשרת. המצב הקודם נשאר ללא שינוי.';
}

function quoteCaseProgressState(status) {
  if (status === 'queued') return {current: 1, completed: 1, mode: 'active'};
  if (status === 'in_review') return {current: 2, completed: 2, mode: 'active'};
  if (status === 'needs_information') return {current: 2, completed: 2, mode: 'blocked'};
  if (status === 'ready_for_assistance') return {current: 3, completed: 3, mode: 'active'};
  if (quoteCaseTerminalStatuses.has(status)) return {current: -1, completed: 0, mode: 'terminal'};
  return {current: -1, completed: 0, mode: 'neutral'};
}

function quoteCaseStepState(progress, index) {
  if (progress.mode === 'terminal') return 'terminal';
  if (progress.mode === 'blocked' && index === progress.current) return 'blocked';
  if (index < progress.completed) return 'completed';
  if (progress.mode === 'active' && index === progress.current) return 'current';
  return 'pending';
}

function isConfirmedQuoteCaseForward(previousCase, nextCase) {
  if (!previousCase?.case_id || previousCase.case_id !== nextCase?.case_id) return false;
  if (Number(nextCase.version) <= Number(previousCase.version)) return false;
  const rank = {queued: 1, in_review: 2, needs_information: 2, ready_for_assistance: 3};
  if (!quoteCaseActiveStatuses.has(nextCase.status) || nextCase.status === 'needs_information') return false;
  return Number(rank[nextCase.status] || 0) > Number(rank[previousCase.status] || 0);
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
  return {system: 'מערכת', traveler: 'אתם', operator: 'צוות Tra-Vel'}[event?.actor_type] || 'עדכון';
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
  return agentApiRequest(`/quote-cases/${encodeURIComponent(caseData.case_id)}/handoffs`, {
    method: 'POST',
    body: JSON.stringify({
      channel: 'whatsapp',
      expected_version: Number(caseData.version),
      idempotency_key: button.dataset.idempotencyKey
    })
  });
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
  if (status) status.textContent = 'פותחים בקשה מתועדת ושומרים את גרסת התוכנית המדויקת...';
  setQuoteCaseError(root);
  try {
    const payload = await agentApiRequest(`/runs/${encodeURIComponent(agentRuntime.runId)}/quote-cases`, {
      method: 'POST',
      body: JSON.stringify({
        expected_request_id: agentRuntime.requestId,
        expected_revision: agentRuntime.requestRevision,
        consent: true,
        consent_version: '2026-07-17',
        idempotency_key: button.dataset.idempotencyKey
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
  const status = view.querySelector('[data-quote-case-action-status]');
  const caseData = agentRuntime.quoteCase;
  if (!button || !caseData || button.dataset.state === 'loading') return;
  const popup = window.open('about:blank', '_blank');
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
    if (error?.status === 409 && caseData.case_id) {
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
  if (!window.confirm('לבטל את בקשת הסיוע? התוכנית הפרטית תישאר זמינה, אבל הצוות יפסיק לטפל בבקשה הזאת.')) return;
  if (!button.dataset.idempotencyKey) button.dataset.idempotencyKey = createAgentClientRequestId();
  button.dataset.state = 'loading';
  button.disabled = true;
  if (status) status.dataset.state = 'running';
  if (status) status.textContent = 'שולחים בקשת ביטול לשרת...';
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
    if (agentRuntime.quoteCasePollFailures >= 3) setQuoteCaseError(root, 'העדכון החי של בקשת הסיוע אינו זמין כרגע. המצב האחרון שאושר נשאר מוצג וננסה שוב אוטומטית.', 'poll');
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
    throw new Error('השרת לא החזיר חוזה ריצה תקין.');
  }
  agentRuntime.runId = run.run_id;
  agentRuntime.status = String(run.status || 'created');
  agentRuntime.requestId = String(run.trip_request?.request_id || '');
  agentRuntime.requestRevision = Math.max(0, Number(run.trip_request?.revision) || 0);
  setAgentWorkbenchStatus(root, agentStatusLabel(agentRuntime.status), agentRuntime.status);
  renderAgentTripRequest(root, run.trip_request);
  mergeAndRenderAgentEvents(root, run.events || []);
  renderAgentSupplierState(root, run);
  renderAgentQuoteCaseAvailability(root);
  const failedEvents = agentRuntime.events.filter(event => event?.visible !== false && event?.status === 'failed' && event?.message);
  if (['provider_error', 'failed'].includes(agentRuntime.status)) {
    const failedEvent = failedEvents[failedEvents.length - 1];
    setAgentWorkbenchError(root, failedEvent?.message || 'השרת דיווח שהריצה נכשלה. לא יוצגו חיפוש, מחיר או הצעה ללא אירוע תקין.');
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
  if (error?.status === 409) return 'השרת כבר קיבל את הבקשה הזאת. המתינו לפני שליחה נוספת.';
  if (error?.status === 401 || error?.status === 403) return 'הגישה לריצה הפרטית פגה. פתחו ריצה חדשה מהבקשה שמופיעה למעלה.';
  return 'לא התקבל אישור תקין לפתיחת ריצה פרטית. לא נציג חיפוש, מחיר או הצעה בלי אירוע שרת מאומת.';
}

function agentRevisionErrorMessage(error) {
  if (error?.code === 'tra_vel_agent_revision_limit') return 'התוכנית הגיעה למספר העדכונים המרבי. פתחו תוכנית פרטית חדשה כדי להמשיך.';
  if (error?.code === 'tra_vel_agent_revision_busy' || error?.code === 'tra_vel_agent_duplicate_revision') return 'העדכון כבר מתקדם בריצה הזאת. המתינו רגע לפני שליחה נוספת.';
  if (error?.status === 429) return 'המתכנן מטפל כרגע בבקשות נוספות. התוכנית הקודמת נשמרה ואפשר לנסות שוב בעוד רגע.';
  if (error?.status === 401 || error?.status === 403 || error?.status === 404) return 'הגישה לריצה הפרטית פגה. התוכנית לא שונתה ואפשר לפתוח ריצה חדשה.';
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
  setAgentWorkbenchStatus(root, 'מתחברים מחדש לעדכוני הריצה הפרטית...', 'connecting');
  pollAgentRun(root);
}

async function pollAgentRun(root) {
  const runId = agentRuntime.runId;
  if (!runId) {
    setAgentWorkbenchError(root, 'לא ניתן להמשיך לעדכן את הריצה כי מזהה הריצה אינו זמין בלשונית הזאת.');
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
        ? 'העדכון החי אינו זמין כרגע. האירועים שכבר אושרו נשארים מוצגים, וננסה להתחבר שוב אוטומטית.'
        : 'העדכון החי נעצר זמנית. האירועים שכבר התקבלו נשארים מוצגים ללא שינוי.',
      {reconnect: repeated}
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
  setAgentWorkbenchStatus(root, 'שולחים בקשה פרטית לשרת. עדיין לא התקבל אירוע ריצה.', 'connecting');
  submit.disabled = true;
  root.dataset.state = 'loading';
  try {
    const payload = await agentApiRequest('/runs', {
      method: 'POST',
      body: JSON.stringify({
        prompt: message,
        mode: mode.value === 'surprise' ? 'surprise' : 'agent',
        locale: detectAgentLocale(message),
        input_kind: inputKind,
        transcript_confirmed: inputKind === 'typed' || Boolean(transcriptConfirmed?.checked),
        client_request_id: createAgentClientRequestId()
      })
    });
    const run = {...payload};
    if (!run.run_id) throw new Error('השרת לא החזיר מזהה ריצה פרטי.');
    resetAgentRuntime(run.run_id);
    const stored = storeAgentRunSession(run.run_id);
    renderAgentRun(root, run, true);
    if (!stored) {
      setAgentWorkbenchError(root, 'הריצה נפתחה, אבל הדפדפן חסם שמירת מזהה הריצה בלשונית. האירועים הראשונים מוצגים, אך לא יתבצע עדכון נוסף.');
      return;
    }
    scheduleAgentPoll(root);
  } catch (error) {
    setAgentWorkbenchStatus(root, 'לא התקבל אישור לפתיחת ריצה', 'error');
    setAgentWorkbenchError(root, agentErrorMessage(error));
    const view = agentWorkbenchRoot(root);
    const empty = view.querySelector('[data-agent-event-empty]');
    if (empty) empty.textContent = 'לא התקבלו אירועי ריצה מהשרת.';
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
      status.textContent = 'לא נמצא מזהה לריצה הזאת. פתחו תוכנית פרטית חדשה.';
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
        ? `גרסה ${revision} נשמרה. נשאר פרט נוסף שצריך להשלים לפני חיפוש.`
        : `גרסה ${revision} נשמרה. התוכנית התקדמה והיא מוכנה לשלב החיפוש.`;
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
  setAgentWorkbenchStatus(root, 'טוענים את הריצה הפרטית מהלשונית הזאת.', 'connecting');
  try {
    const run = await agentApiRequest(`/runs/${encodeURIComponent(runId)}`);
    if (agentRuntime.runId !== runId) return;
    renderAgentRun(root, run);
    await loadQuoteCaseForRun(root);
    scheduleAgentPoll(root);
  } catch (error) {
    clearAgentRunSession();
    setAgentWorkbenchStatus(root, 'לא ניתן לחדש את הריצה הפרטית', 'error');
    setAgentWorkbenchError(root, agentErrorMessage(error));
  }
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
    resumeAgentRun(root);
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
  resumeAgentRun(root);
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
  const requestedDestination = new URLSearchParams(window.location.search).get('destination') || '';
  let destinationFilter = directoryDestinationAliases[requestedDestination] || requestedDestination;
  let active = 'all';
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
  const selected = buttons.find(button => button.dataset.experienceDestination === map.dataset.selectedDestination) || buttons[0];
  if (selected) select(selected);
}

function initTraVelV2() {
  if (document.documentElement.dataset.traVelV2Ready === 'true') return;
  document.documentElement.dataset.traVelV2Ready = 'true';
  readDiscoveryStateFromUrl();
  const heroCampaign = document.querySelector('[data-hero-campaign]');
  const initialActivePin = document.querySelector('.price-pin.is-active[data-destination]');
  const hasRequestedDestination = new URLSearchParams(window.location.search).has('destination');
  if (!hasRequestedDestination && heroCampaign?.dataset.mapState && destinationData[heroCampaign.dataset.mapState]) {
    activeDestination = heroCampaign.dataset.mapState;
  } else if (!hasRequestedDestination && initialActivePin?.dataset.destination && destinationData[initialActivePin.dataset.destination]) {
    activeDestination = initialActivePin.dataset.destination;
  }
  renderIcons();
  initNavigation();
  initMap();
  initGlobePointSelection();
  initDestinationPlan();
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
  hydrateDiscovery(discoveryRequestParams());
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initTraVelV2, { once: true });
} else {
  initTraVelV2();
}
