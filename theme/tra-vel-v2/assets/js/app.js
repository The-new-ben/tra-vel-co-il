const destinationAssetBase = window.traVelV2?.assetUrl || './assets/';
const fallbackDestinations = {
  bangkok: { id: 'bangkok', city: 'בנגקוק', country: 'תאילנד', price: 'בדיקה חיה', total: 'בדיקה חיה', note: 'מחיר ותנאים יאומתו בחיפוש', image: `${destinationAssetBase}thailand.jpg`, tags: ['12 לילות', 'עצירה אחת', 'בדיקת כבודה'], airport: 'BKK · לפי המסלול', hotel: 'Siam · אזור לינה', weather: 'לפי התאריך', x: 72, y: 61 },
  athens: { id: 'athens', city: 'אתונה', country: 'יוון', price: 'בדיקה חיה', total: 'בדיקה חיה', note: 'מחיר ותנאים יאומתו בחיפוש', image: `${destinationAssetBase}athens-acropolis.jpg`, tags: ['מסלול ישיר', '3 לילות', 'ביטול גמיש'], airport: 'ATH · לפי המסלול', hotel: 'Plaka · אזור לינה', weather: 'לפי התאריך', x: 48, y: 43 },
  budapest: { id: 'budapest', city: 'בודפשט', country: 'הונגריה', price: 'בדיקה חיה', total: 'בדיקה חיה', note: 'מחיר ותנאים יאומתו בחיפוש', image: `${destinationAssetBase}city-budapest.webp`, tags: ['מסלול ישיר', '4 לילות', 'בדיקת ארוחה'], airport: 'BUD · לפי המסלול', hotel: 'District V · אזור לינה', weather: 'לפי התאריך', x: 43, y: 32 },
  dubai: { id: 'dubai', city: 'דובאי', country: 'איחוד האמירויות', price: 'בדיקה חיה', total: 'בדיקה חיה', note: 'מחיר ותנאים יאומתו בחיפוש', image: `${destinationAssetBase}hero-budapest-900.webp`, tags: ['מסלול ישיר', 'סוף שבוע', 'בדיקת כבודה'], airport: 'DXB · לפי המסלול', hotel: 'Creek · אזור לינה', weather: 'לפי התאריך', x: 59, y: 53 },
  tokyo: { id: 'tokyo', city: 'טוקיו', country: 'יפן', price: 'בדיקה חיה', total: 'בדיקה חיה', note: 'מחיר ותנאים יאומתו בחיפוש', image: `${destinationAssetBase}city-prague.webp`, tags: ['עצירה אחת', '10 לילות', 'בדיקת כבודה'], airport: 'HND · לפי המסלול', hotel: 'Shinjuku · אזור לינה', weather: 'לפי התאריך', x: 84, y: 39 },
  lisbon: { id: 'lisbon', city: 'ליסבון', country: 'פורטוגל', price: 'בדיקה חיה', total: 'בדיקה חיה', note: 'מחיר ותנאים יאומתו בחיפוש', image: `${destinationAssetBase}city-prague.webp`, tags: ['7 לילות', 'עצירה אחת', 'בחירת אזור'], airport: 'LIS · לפי המסלול', hotel: 'Baixa · אזור לינה', weather: 'לפי התאריך', x: 29, y: 43 }
};

let destinationData = { ...fallbackDestinations };
let discoveryRoutes = [];
let activeLayer = 'deals';
let activeDestination = 'bangkok';
let discoveryDataMode = 'demo';
let discoveryFieldProvenance = {};
let discoveryLiveLayers = { deals: false, hotels: false, airports: false, airportDetails: false, weather: false };
let discoveryRequestController = null;
let discoveryRequestGeneration = 0;
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
  return Object.fromEntries(fields.map(field => [field, {
    live: provenance?.[field]?.live === true,
    source: typeof provenance?.[field]?.source === 'string' ? provenance[field].source : '',
    observed_at: typeof provenance?.[field]?.observed_at === 'string' ? provenance[field].observed_at : ''
  }]));
}

function resolveDiscoveryLiveLayers(provenance = {}) {
  const fields = normalizeFieldProvenance(provenance);
  return {
    deals: fields.deals.live,
    hotels: fields.hotels.live,
    airports: fields.routes.live,
    airportDetails: fields.airports.live,
    weather: fields.weather_current.live
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
    latitude: item.geo.latitude,
    longitude: item.geo.longitude,
    x: item.position.x,
    y: item.position.y
  };
}

function pinLabel(data) {
  if (activeLayer === 'hotels') return discoveryLiveLayers.hotels ? (data.hotelPrice || data.total) : data.city;
  if (activeLayer === 'airports') return data.airportCode || data.airport;
  if (activeLayer === 'weather') return discoveryLiveLayers.weather ? data.weather : data.city;
  return discoveryLiveLayers.deals ? data.price : data.city;
}

function bindDestinationPin(pin) {
  if (!pin || pin.dataset.selectionBound === 'true') return;
  pin.dataset.selectionBound = 'true';
  pin.addEventListener('click', event => {
    event.stopPropagation();
    discoveryDestinationLocked = true;
    setActiveDestination(pin.dataset.destination, pin, false);
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

function setActiveDestination(key, pin, animatePlan = true) {
  const data = destinationData[key];
  if (!data) return;
  const hasLiveDealPrices = discoveryLiveLayers.deals;
  const hasLiveHotelPrices = discoveryLiveLayers.hotels;
  const hasLiveRouteData = discoveryLiveLayers.airports;
  const hasLiveAirportDetails = discoveryLiveLayers.airportDetails;
  const hasLiveWeather = discoveryLiveLayers.weather;
  const hasLiveSeason = discoveryFieldProvenance?.weather_season?.live === true;
  const displayTags = hasLiveDealPrices ? (data.tags || []) : ['מדריך ליעד', 'אזורי לינה', 'מסלולים אפשריים'];
  const layerStates = {
    deals: {
      label: 'מחיר וזמינות',
      total: hasLiveDealPrices ? data.total : 'בדיקה חיה',
      price: hasLiveDealPrices ? data.price : 'בדיקת מחיר',
      note: hasLiveDealPrices ? data.note : 'מחיר וזמינות יוצגו אחרי בחירת תאריכים והרכב.'
    },
    hotels: {
      label: 'לינה באזור הנכון',
      total: hasLiveHotelPrices ? (data.hotelPrice || data.total) : 'בדיקת מלונות',
      price: data.hotelArea || 'אזור לינה',
      note: hasLiveHotelPrices ? 'מחיר החדר והתנאים מעודכנים למועד הבדיקה.' : 'מחיר חדר, מסים וביטול יוצגו אחרי בחירת תאריכים.'
    },
    airports: {
      label: 'שדה ודרך',
      total: data.airportCode || 'בדיקת שדה',
      price: data.airportDirect ? 'מסלול ישיר אפשרי' : 'נדרש קונקשן',
      note: 'זמן, כבודה ותנאי כרטיס יאומתו בחיפוש טיסות.'
    },
    weather: {
      label: 'מזג אוויר ועונה',
      total: hasLiveWeather ? data.weather : 'לפי תאריך',
      price: hasLiveWeather ? (data.weatherCondition || 'בדיקה חיה') : 'בחרו מועד',
      note: hasLiveWeather
        ? (hasLiveSeason ? `התאמת עונה מעודכנת: ${data.seasonFit || 'לפי מסלול'}.` : 'התנאים הנוכחיים עודכנו. התאמת העונה תיבדק לפי תאריך הנסיעה.')
        : 'תחזית תוצג רק למועד נסיעה מוגדר.'
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
  window.traVelGlobe3D?.focusDestination(key, Boolean(pin));

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
      '[data-result-airport]': hasLiveAirportDetails ? data.airport : (data.airportCode || 'שדות תעופה'),
      '[data-result-hotel]': hasLiveHotelPrices ? data.hotel : (data.hotelArea || 'אזורי לינה'),
      '[data-result-weather]': hasLiveWeather ? `${data.weather} · ${data.weatherCondition || ''}` : 'מזג אוויר לפי תאריך'
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
      if (link) link.href = href;
    });
    const saveButton = card.querySelector('.save-button');
    if (saveButton) {
      const saved = isWorkspaceItemSaved(normalizeWorkspaceItem(mapDestinationWorkspaceItem(data)).id);
      saveButton.disabled = false;
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
  updateHomeDestinationPlan(data, animatePlan);
  updateDestinationPlan(data, animatePlan);
}

function renderDiscoveryEmptyState() {
  activeDestination = '';
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
    plan.dataset.state = 'empty';
    plan.setAttribute('aria-busy', 'false');
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
  }
}

function destinationPlanUrl(path, params = {}) {
  const url = new URL(path, window.location.origin);
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== undefined && value !== null) url.searchParams.set(key, String(value));
  });
  return url.toString();
}

function updateDestinationPlanStages(plan, responseConfirmed) {
  const currentStage = { deals: 'total', hotels: 'stay', airports: 'route', weather: 'experience' }[activeLayer] || 'route';
  const routeCount = responseConfirmed && discoveryLiveLayers.airports ? discoveryRoutes.length : 0;
  const labels = {
    destination: 'נבחר',
    route: responseConfirmed ? (routeCount ? `${routeCount} אפשרויות התקבלו` : 'מוכן להשוואה') : 'מעדכנים',
    stay: responseConfirmed ? (discoveryLiveLayers.hotels ? 'מידע עדכני התקבל' : 'מוכן לבדיקה') : 'מעדכנים',
    experience: responseConfirmed ? (discoveryLiveLayers.weather ? 'תנאי מזג אוויר התקבלו' : 'מוכן לתכנון') : 'מעדכנים',
    cover: responseConfirmed ? 'מוכן להתאמה' : 'מעדכנים',
    total: responseConfirmed ? (discoveryLiveLayers.deals ? 'נתון מחיר התקבל' : 'ממתין לחיפוש מלא') : 'מעדכנים'
  };
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
    stage.classList.toggle('is-pending', !responseConfirmed && stageName !== 'destination');
    if (isCurrent) stage.setAttribute('aria-current', 'step');
    else stage.removeAttribute('aria-current');
    const detail = stage.querySelector('small');
    if (detail && labels[stageName]) detail.textContent = labels[stageName];
  });
}

function runConfirmedPlanAnimation(container, tailSelector) {
  if (!container) return;
  container.classList.remove('is-updating');
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  void container.offsetWidth;
  container.classList.add('is-updating');
  const tail = container.querySelector(tailSelector);
  let fallbackTimer = 0;
  const finish = () => {
    container.classList.remove('is-updating');
    if (fallbackTimer) window.clearTimeout(fallbackTimer);
  };
  tail?.addEventListener('animationend', finish, { once: true });
  fallbackTimer = window.setTimeout(finish, 1400);
}

function updateDestinationPlan(data, animate = true) {
  const plan = document.querySelector('[data-destination-plan]');
  if (!plan || !data) return;
  const intent = destinationPlanIntents[activePlanIntent] || destinationPlanIntents.smart;
  const currentLayerHasLiveData = discoveryLiveLayers[activeLayer] === true;
  const anyLiveData = Object.values(discoveryLiveLayers).some(Boolean);
  const hasLiveSeason = discoveryFieldProvenance?.weather_season?.live === true;
  const airportCode = data.airportCode || '';
  const destinationId = data.id || '';
  const layerLabel = { deals: 'עלות מלאה', hotels: 'לינה', airports: 'טיסה ודרך', weather: 'מזג אוויר' }[activeLayer] || 'תכנון';
  plan.dataset.destination = destinationId;
  plan.dataset.intent = activePlanIntent;
  plan.dataset.state = currentLayerHasLiveData ? 'live' : 'planning';

  const fields = {
    '[data-plan-title]': `התוכנית ${intent.label} ל${data.city}`,
    '[data-plan-state]': `${layerLabel} במוקד · שאר השלבים נשארים מחוברים`,
    '[data-plan-summary]': `${intent.summary} כל החלטה נשארת מחוברת ל${data.city} ולבחירות שלכם.`,
    '[data-plan-flight-title]': activePlanIntent === 'easy'
      ? (data.airportDirect ? 'מתחילים במסלול הישיר' : 'מחפשים קונקשן מוגן ופשוט')
      : (activePlanIntent === 'value' ? 'משווים עלות מלאה בין דרכים' : 'ישיר מול קונקשן חכם'),
    '[data-plan-flight-detail]': [airportCode, data.flightDuration, 'כבודה ותנאי כרטיס'].filter(Boolean).join(' · '),
    '[data-plan-stay-title]': activePlanIntent === 'family'
      ? 'אזור נוח למשפחה ולמעברים קצרים'
      : (activePlanIntent === 'romantic' ? 'אזור שקט ונעים לשניים' : (data.hotelArea ? `מתחילים באזור ${data.hotelArea}` : 'בוחרים אזור לפני מלון')),
    '[data-plan-stay-detail]': [data.nights ? `${data.nights} לילות` : '', 'תחבורה', 'ביטול ועלות מלאה'].filter(Boolean).join(' · '),
    '[data-plan-experience-title]': activePlanIntent === 'adventure'
      ? 'טבע, פעילות, ציוד וימי התאוששות'
      : (activePlanIntent === 'romantic' ? 'אוכל, שקיעה וזמן חופשי' : (activePlanIntent === 'family' ? 'פעילויות לפי גיל וקצב' : 'מסלול לפי הכוונה שלכם')),
    '[data-plan-weather-title]': discoveryLiveLayers.weather && data.weather ? `${data.weather} · ${data.weatherCondition || ''}` : 'בדיקה לפי תאריך',
    '[data-plan-weather-detail]': discoveryLiveLayers.weather
      ? (hasLiveSeason ? `התאמת עונה מעודכנת: ${data.seasonFit || 'לפי מועד'}` : 'התנאים הנוכחיים עודכנו. התאמת העונה תיבדק לפי תאריך הנסיעה')
      : 'מזג אוויר יאומת לפי תאריך הנסיעה',
    '[data-plan-cover-title]': activePlanIntent === 'adventure' ? 'כיסוי פעילות וציוד ייעודי' : 'כיסוי לפי המסלול והנוסעים',
    '[data-plan-total-title]': 'עדיין לא חושבה בחבילה מלאה',
    '[data-plan-truth]': anyLiveData
      ? 'יש נתונים עדכניים לחלק מהמסע. העלות הכוללת והזמינות יאומתו לפני אישור.'
      : 'היעד נבחר. מחירים, זמינות והזמנה עדיין לא נבדקו.'
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
  plan.querySelectorAll('[data-plan-layer]').forEach(option => option.classList.toggle('is-layer-active', option.dataset.planLayer === activeLayer));
  updateDestinationPlanStages(plan, animate);

  if (animate) {
    runConfirmedPlanAnimation(plan, '.destination-plan-options > a:last-child');
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
    discoveryQuery = { ...discoveryQuery, ...(destinationPlanIntentConstraints[activePlanIntent] || {}) };
    syncDiscoveryControls();
    updateDestinationPlan(destinationData[activeDestination], false);
    syncDiscoveryUrl('push');
    hydrateDiscovery(discoveryRequestParams());
  }));
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
    if (!parsed || parsed.version !== 1 || !Array.isArray(parsed.items)) return defaultLocalWorkspace();
    return {
      ...defaultLocalWorkspace(),
      ...parsed,
      items: parsed.items.slice(0, 50),
      preferences: {...defaultLocalWorkspace().preferences, ...(parsed.preferences || {})}
    };
  } catch (error) {
    console.warn(error);
    return defaultLocalWorkspace();
  }
}

function writeLocalWorkspace(workspace) {
  travelerWorkspace = workspace;
  try {
    window.localStorage.setItem(workspaceLocalKey, JSON.stringify(workspace));
  } catch (error) {
    console.warn(error);
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
  return readLocalWorkspace().items.some(item => item.id === itemId);
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
  if (!item.external_id || !item.title) return;
  const workspace = readLocalWorkspace();
  const existing = workspace.items.find(saved => saved.id === item.id);
  if (existing?.watch) item.watch = existing.watch;
  workspace.items = [item, ...workspace.items.filter(saved => saved.id !== item.id)].slice(0, 50);
  writeLocalWorkspace(workspace);
  button?.classList.add('is-saved');
  if (button) {
    const label = button.querySelector('span');
    if (label) label.textContent = 'נשמר לנסיעה';
    button.setAttribute('aria-label', 'נשמר לנסיעה');
  }
  showWorkspaceToast(window.traVelV2?.isLoggedIn ? 'נשמר במכשיר ומסתנכרן לחשבון' : 'נשמר באופן פרטי במכשיר הזה', 'heart');
  try {
    const serverWorkspace = await workspaceRequest('/items', {method: 'POST', body: JSON.stringify(item)});
    if (serverWorkspace) {
      writeLocalWorkspace(mergeTravelerWorkspaces(workspace, serverWorkspace));
    }
  } catch (error) {
    showWorkspaceToast('נשמר במכשיר; הסנכרון לחשבון ינסה שוב בהמשך', 'cloud-off');
    console.warn(error);
  }
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
  return {
    kind: 'destination',
    external_id: data.id,
    title: `${data.city}, ${data.country}`,
    subtitle: `${data.airportCode || 'יעד'} · ${data.hotelArea || 'אזור לינה לבחירה'}`,
    destination: data.city,
    route: `TLV → ${data.airportCode || data.city}`,
    price_label: discoveryLiveLayers.deals ? data.total : 'נדרש חיפוש חי',
    price_amount: discoveryLiveLayers.deals ? data.totalAmount : 0,
    currency: data.currency || 'USD',
    data_mode: discoveryLiveLayers.deals ? 'live' : 'editorial',
    href: data.url || destinationPlanUrl('/destinations/', { destination: data.id })
  };
}

function renderRoutes(routes) {
  const list = document.querySelector('[data-route-list]');
  if (!list) return;
  list.replaceChildren();
  if (!routes.length) {
    appendTextElement(list, 'p', 'עדיין אין השוואת מסלולים מלאה ליעד הזה. אפשר להמשיך לחיפוש ולבדוק זמינות עדכנית.', 'route-empty');
    return;
  }
  routes.forEach((route, index) => {
    const hasLiveRouteData = discoveryLiveLayers.airports;
    const routePrice = hasLiveRouteData ? route.costs.total_formatted : 'בדיקת מחיר';
    const button = document.createElement('button');
    button.className = `mini-route${index === 0 ? ' is-selected' : ''}`;
    button.type = 'button';
    button.dataset.route = route.id;
    button.dataset.routeSummary = `${route.label} · ${routePrice}`;
    appendTextElement(button, 'small', route.badge);
    appendTextElement(button, 'strong', hasLiveRouteData ? `${route.label} · ${route.duration_label}` : route.label);
    appendTextElement(button, 'span', hasLiveRouteData ? `${route.stops ? `${route.stops} עצירה` : 'ישיר'} · ${route.ticket_mode === 'single' ? 'כרטיס אחד' : 'כרטיסים נפרדים'}` : 'זמן, עצירות ותנאי כרטיס יוצגו אחרי חיפוש');
    const tradeoffs = document.createElement('div');
    tradeoffs.className = 'route-tradeoffs';
    appendTextElement(tradeoffs, 'span', hasLiveRouteData ? `✓ ${route.pros[0]}` : '✓ משווים זמן, נוחות וגמישות', 'route-pro');
    appendTextElement(tradeoffs, 'span', hasLiveRouteData ? `△ ${route.cons[0]}` : '△ מחיר ותנאים יאומתו בחיפוש חי', 'route-con');
    button.append(tradeoffs);
    appendTextElement(button, 'b', routePrice);
    appendTextElement(button, 'em', hasLiveRouteData ? 'עלות מלאה לאדם' : 'מחיר סופי יוצג בחיפוש חי');
    button.addEventListener('click', () => selectRoute(button));
    list.append(button);
  });
}

function setRouteListBusy(busy) {
  const list = document.querySelector('[data-route-list]');
  if (!list) return;
  list.setAttribute('aria-busy', String(busy));
  list.querySelectorAll('button').forEach(button => { button.disabled = busy; });
}

function selectRoute(card) {
  document.querySelectorAll('[data-route]').forEach(item => item.classList.remove('is-selected'));
  card.classList.add('is-selected');
  const summary = document.querySelector('[data-route-summary]');
  if (summary) summary.textContent = card.dataset.routeSummary || 'המסלול נבחר';
}

function setDiscoveryStatus(mode, message) {
  const canvas = document.querySelector('[data-map-canvas]');
  if (canvas) canvas.dataset.dataMode = mode;
  const status = document.querySelector('[data-layer-status]');
  if (status) status.textContent = message;
  const plan = document.querySelector('[data-destination-plan]');
  if (plan) {
    plan.dataset.requestState = mode;
    plan.setAttribute('aria-busy', String(mode === 'loading'));
    const planState = plan.querySelector('[data-plan-state]');
    if (mode === 'loading') {
      plan.classList.remove('is-updating');
      updateDestinationPlanStages(plan, false);
      if (planState) planState.textContent = 'מעדכנים את התוכנית לפי הבחירה שלכם';
    }
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
    discoveryFieldProvenance = normalizeFieldProvenance(payload.field_provenance);
    discoveryLiveLayers = resolveDiscoveryLiveLayers(discoveryFieldProvenance);
    destinationData = Object.fromEntries(payload.destinations.map(item => [item.id, normalizeDestination(item)]));
    updateWeatherAttribution(payload.provider_status);
    updatePins();
    const resolvedDestination = payload.meta?.selected_destination || '';
    const selected = destinationData[resolvedDestination]
      ? resolvedDestination
      : (destinationData[requestParams.destination] ? requestParams.destination : Object.keys(destinationData)[0]);
    if (selected) {
      discoveryRoutes = resolvedDestination === selected && Array.isArray(payload.routes)
        ? payload.routes.filter(route => route.destination_id === selected)
        : [];
      setActiveDestination(selected, document.querySelector(`[data-destination="${selected}"]`));
      renderRoutes(discoveryRoutes);
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
    const modeLabel = discoveryLiveLayers[activeLayer]
      ? liveModeLabels[activeLayer]
      : verificationLabels[activeLayer];
    setDiscoveryStatus(discoveryDataMode, `${layerName} · ${payload.meta.result_count} יעדים · ${modeLabel}`);
  } catch (error) {
    if ((error?.name === 'AbortError' && !timedOut) || generation !== discoveryRequestGeneration) return;
    discoveryDataMode = 'demo';
    discoveryFieldProvenance = normalizeFieldProvenance();
    discoveryLiveLayers = { deals: false, hotels: false, airports: false, airportDetails: false, weather: false };
    destinationData = { ...fallbackDestinations };
    discoveryRoutes = [];
    updateWeatherAttribution(null);
    updatePins();
    const fallbackDestination = destinationData[requestParams.destination] ? requestParams.destination : Object.keys(destinationData)[0];
    if (fallbackDestination) {
      setActiveDestination(fallbackDestination, document.querySelector(`[data-destination="${fallbackDestination}"]`));
      syncDiscoveryUrl('replace');
    }
    renderRoutes(discoveryRoutes);
    setDiscoveryStatus('fallback', timedOut ? 'העדכון החי נעצר בזמן · 6 יעדים נשארו זמינים לתכנון' : '6 יעדים זמינים · מחירים יופיעו בחיפוש חי');
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
  submit.disabled = true;
  form.dataset.state = 'loading';
  if (status) status.textContent = 'משווה אזור, מחיר מלא, תנאים וזמן למסלול...';
  try {
    const response = await fetch(url, {headers: {Accept: 'application/json'}});
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || `Hotel search failed: ${response.status}`);
    renderHotelAreaMap(payload, form);
    renderHotelProperties(payload);
    const modeLabels = {live: 'מחירי ספקים חיים', mixed: 'נתונים חיים ואומדנים', demo: 'מחירים לאימות בחיפוש'};
    const cacheLabels = {miss: 'עודכן עכשיו', fresh: 'תוצאה שמורה ועדכנית', stale_refreshing: 'מעדכן ברקע', stale_error: 'תוצאה אחרונה תקינה', degraded_fallback: 'חלק מהתוצאות אינן זמינות כרגע'};
    if (status) status.textContent = `${payload.meta.result_count} מקומות · ${payload.search.nights} לילות · ${modeLabels[payload.meta.data_mode] || modeLabels.demo} · ${cacheLabels[payload.meta.cache_state] || 'עודכן'}`;
    form.dataset.state = payload.meta.data_mode;
  } catch (error) {
    document.querySelector('[data-hotel-results]')?.replaceChildren();
    if (status) status.textContent = 'לא הצלחנו להשלים את השוואת המלונות. בדקו את התאריכים ונסו שוב.';
    form.dataset.state = 'error';
    console.warn(error);
  } finally {
    submit.disabled = false;
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
  submit.disabled = true;
  form.dataset.state = 'loading';
  if (status) status.textContent = 'משווה גבולות, הרחבות, שירות, חריגים ומחיר משוער...';
  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {Accept: 'application/json', 'Content-Type': 'application/json'},
      body: JSON.stringify(requestBody)
    });
    const payload = await response.json();
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
    document.querySelector('[data-insurance-results]')?.replaceChildren();
    if (status) status.textContent = 'לא הצלחנו להשלים את ההשוואה. בדקו תאריכים ונסו שוב.';
    form.dataset.state = 'error';
    console.warn(error);
  } finally {
    submit.disabled = false;
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
  if (shouldFocus) document.querySelector('[data-package-map-detail]')?.scrollIntoView({behavior: 'smooth', block: 'center'});
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
  pins.replaceChildren();
  const positions = [[64,28],[38,21],[24,43],[57,52],[73,43],[40,62],[18,67],[82,64]];
  items.slice(0, 8).forEach((item, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `workspace-map-pin${index === 0 ? ' is-active' : ''}`;
    button.dataset.workspaceMapPin = item.id;
    button.style.left = `${positions[index][0]}%`;
    button.style.top = `${positions[index][1]}%`;
    button.setAttribute('aria-label', `${item.title}, ${item.price_label || workspaceMoney(item)}`);
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
  workspace.items = workspace.items.filter(item => item.id !== itemId);
  writeLocalWorkspace(workspace);
  renderWorkspaceDashboard();
  showWorkspaceToast('האפשרות הוסרה מהנסיעה', 'trash-2');
  try {
    await workspaceRequest(`/items/${encodeURIComponent(itemId)}`, {method: 'DELETE'});
  } catch (error) {
    console.warn(error);
  }
}

async function toggleWorkspaceWatch(itemId) {
  const workspace = travelerWorkspace || readLocalWorkspace();
  const item = workspace.items.find(saved => saved.id === itemId);
  if (!item) return;
  const enabled = !item.watch?.enabled;
  const target = enabled ? Math.max(1, Math.round((item.price_amount || 0) * .95)) : 0;
  item.watch = {enabled, target_amount: target, delivery_enabled: false, status: enabled ? 'awaiting_live_supplier' : 'off'};
  writeLocalWorkspace(workspace);
  renderWorkspaceDashboard(itemId);
  showWorkspaceToast(enabled ? 'יעד המחיר נשמר; המשלוח ייפתח עם ספק חי' : 'מעקב המחיר הוסר', enabled ? 'bell-ring' : 'bell-off');
  try {
    await workspaceRequest(`/items/${encodeURIComponent(itemId)}/watch`, {method: 'PUT', body: JSON.stringify({enabled, target_amount: target})});
  } catch (error) {
    console.warn(error);
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
  mapIcon.dataset.lucide = 'map-pin';
  mapButton.append(mapIcon, document.createTextNode('על המפה'));
  mapButton.addEventListener('click', () => {
    selectWorkspaceMapItem(item.id);
    document.querySelector('[data-workspace-map]')?.scrollIntoView({behavior: 'smooth', block: 'center'});
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
  if (status) status.textContent = items.length ? `${items.length} אפשרויות · נשמרות באופן פרטי · התראות אינן פעילות עדיין` : 'סביבת העבודה מוכנה. שמרו אפשרות אחת כדי להתחיל להשוות.';
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
    return;
  }
  const workspace = travelerWorkspace || readLocalWorkspace();
  workspace.preferences = preferences;
  writeLocalWorkspace(workspace);
  showWorkspaceToast(window.traVelV2?.isLoggedIn ? 'ההעדפות נשמרו ומסתנכרנות לחשבון' : 'ההעדפות נשמרו במכשיר הזה', 'sliders-horizontal');
  try {
    const serverWorkspace = await workspaceRequest('/preferences', {method: 'PUT', body: JSON.stringify(preferences)});
    if (serverWorkspace) writeLocalWorkspace(mergeTravelerWorkspaces(workspace, serverWorkspace));
  } catch (error) {
    console.warn(error);
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
    travelerWorkspace = mergeTravelerWorkspaces(local, serverWorkspace);
    writeLocalWorkspace(travelerWorkspace);
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
    const data = destinationData[activeDestination];
    if (data) saveWorkspaceItem(mapDestinationWorkspaceItem(data), button);
  }));

  document.querySelectorAll('[data-map-zoom]').forEach(button => button.addEventListener('click', () => {
    const globe = button.closest('.globe-panel, .compact-map, .world-canvas')?.querySelector('.globe');
    if (!globe) return;
    if (globe.matches('[data-globe-3d]') && window.traVelGlobe3D) {
      window.traVelGlobe3D.zoom(button.dataset.mapZoom);
      return;
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
  lastSequence: 0,
  events: [],
  eventIds: new Set(),
  pollTimer: 0,
  pollFailures: 0
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

function resetAgentRuntime(runId = '') {
  if (agentRuntime.pollTimer) window.clearTimeout(agentRuntime.pollTimer);
  agentRuntime.runId = runId;
  agentRuntime.status = '';
  agentRuntime.lastSequence = 0;
  agentRuntime.events = [];
  agentRuntime.eventIds.clear();
  agentRuntime.pollTimer = 0;
  agentRuntime.pollFailures = 0;
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
    headers
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

function setAgentWorkbenchError(root, message = '') {
  const error = agentWorkbenchRoot(root).querySelector('[data-agent-error]');
  if (!error) return;
  error.textContent = message;
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

function renderAgentRun(root, run, focusWorkbench = false) {
  if (!run || typeof run !== 'object' || typeof run.run_id !== 'string') {
    throw new Error('השרת לא החזיר חוזה ריצה תקין.');
  }
  agentRuntime.runId = run.run_id;
  agentRuntime.status = String(run.status || 'created');
  setAgentWorkbenchStatus(root, agentStatusLabel(agentRuntime.status), agentRuntime.status);
  renderAgentTripRequest(root, run.trip_request);
  mergeAndRenderAgentEvents(root, run.events || []);
  renderAgentSupplierState(root, run);
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
  if (!shouldPollAgentRun(agentRuntime.status) || !agentRuntime.runId) return;
  if (agentRuntime.pollTimer) window.clearTimeout(agentRuntime.pollTimer);
  agentRuntime.pollTimer = window.setTimeout(() => pollAgentRun(root), delay);
}

async function pollAgentRun(root) {
  agentRuntime.pollTimer = 0;
  const runId = agentRuntime.runId;
  if (!runId) {
    setAgentWorkbenchError(root, 'לא ניתן להמשיך לעדכן את הריצה כי מזהה הריצה אינו זמין בלשונית הזאת.');
    return;
  }
  try {
    const eventPayload = await agentApiRequest(`/runs/${encodeURIComponent(runId)}/events?after=${agentRuntime.lastSequence}`);
    if (agentRuntime.runId !== runId) return;
    mergeAndRenderAgentEvents(root, eventPayload.events || []);
    if (Number(eventPayload.last_sequence) > agentRuntime.lastSequence) agentRuntime.lastSequence = Number(eventPayload.last_sequence);
    const run = await agentApiRequest(`/runs/${encodeURIComponent(runId)}`);
    if (agentRuntime.runId !== runId) return;
    renderAgentRun(root, run);
    agentRuntime.pollFailures = 0;
    scheduleAgentPoll(root);
  } catch (error) {
    agentRuntime.pollFailures += 1;
    setAgentWorkbenchError(root, 'העדכון החי נעצר זמנית. האירועים שכבר התקבלו נשארים מוצגים ללא שינוי.');
    if (agentRuntime.pollFailures < 3) scheduleAgentPoll(root, 5000);
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
  revisionForm?.addEventListener('submit', event => {
    event.preventDefault();
    reviseAgentRun(root, revisionForm);
  });
  revisionMessage?.addEventListener('input', () => revisionMessage.setCustomValidity(''));
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
  initDestinationPlan();
  initControls();
  initAIConversationEntry();
  initDirectory();
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
