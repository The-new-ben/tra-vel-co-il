const destinationAssetBase = window.traVelV2?.assetUrl || './assets/';
const fallbackDestinations = {
  bangkok: { id: 'bangkok', city: 'בנגקוק', country: 'תאילנד', price: '$742', total: '$1,347', note: 'מחירי הדגמה בלבד', image: `${destinationAssetBase}thailand.jpg`, tags: ['12 לילות', 'עצירה אחת', 'כולל כבודה'], airport: 'BKK · 11:20', hotel: 'Siam · 4.6★', weather: '29°C', x: 72, y: 61 },
  athens: { id: 'athens', city: 'אתונה', country: 'יוון', price: '$189', total: '$534', note: 'מחירי הדגמה בלבד', image: `${destinationAssetBase}city-vienna.webp`, tags: ['טיסה ישירה', '3 לילות', 'ביטול גמיש'], airport: 'ATH · 2:15', hotel: 'Plaka · 4.2★', weather: '23°C', x: 48, y: 43 },
  budapest: { id: 'budapest', city: 'בודפשט', country: 'הונגריה', price: '$214', total: '$596', note: 'מחירי הדגמה בלבד', image: `${destinationAssetBase}city-budapest.webp`, tags: ['טיסה ישירה', '4 לילות', 'ארוחת בוקר'], airport: 'BUD · 3:30', hotel: 'District V · 4.4★', weather: '17°C', x: 43, y: 32 },
  dubai: { id: 'dubai', city: 'דובאי', country: 'איחוד האמירויות', price: '$236', total: '$690', note: 'מחירי הדגמה בלבד', image: `${destinationAssetBase}hero-budapest-900.webp`, tags: ['טיסה ישירה', 'סופ״ש', 'טרולי כלול'], airport: 'DXB · 3:25', hotel: 'Creek · 4.5★', weather: '31°C', x: 59, y: 53 },
  tokyo: { id: 'tokyo', city: 'טוקיו', country: 'יפן', price: '$918', total: '$1,810', note: 'מחירי הדגמה בלבד', image: `${destinationAssetBase}city-prague.webp`, tags: ['עצירה אחת', '10 לילות', 'כבודה כלולה'], airport: 'HND · 15:30', hotel: 'Shinjuku · 4.3★', weather: '18°C', x: 84, y: 39 },
  lisbon: { id: 'lisbon', city: 'ליסבון', country: 'פורטוגל', price: '$327', total: '$910', note: 'מחירי הדגמה בלבד', image: `${destinationAssetBase}city-prague.webp`, tags: ['7 לילות', 'עצירה אחת', 'מלון מומלץ'], airport: 'LIS · 7:50', hotel: 'Baixa · 4.5★', weather: '21°C', x: 29, y: 43 }
};

let destinationData = { ...fallbackDestinations };
let discoveryRoutes = [];
let activeLayer = 'deals';
let activeDestination = 'bangkok';

function renderIcons() {
  if (window.lucide) window.lucide.createIcons({ attrs: { 'stroke-width': 1.8 } });
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
    price: item.deal.headline_formatted,
    total: item.deal.total_formatted,
    note: item.deal.insight,
    image: item.image,
    tags: item.tags,
    airport: `${item.airport.code} · ${item.airport.flight_duration_label}`,
    airportCode: item.airport.code,
    hotel: `${item.hotel.area} · ${item.hotel.rating}★`,
    hotelPrice: item.hotel.nightly_formatted,
    weather: `${item.weather.temperature_c}°C`,
    weatherCondition: item.weather.condition,
    x: item.position.x,
    y: item.position.y
  };
}

function pinLabel(data) {
  if (activeLayer === 'hotels') return data.hotelPrice || data.total;
  if (activeLayer === 'airports') return data.airportCode || data.airport;
  if (activeLayer === 'weather') return data.weather;
  return data.price;
}

function updatePins() {
  document.querySelectorAll('.price-pin[data-destination]').forEach(pin => {
    const data = destinationData[pin.dataset.destination];
    pin.hidden = !data;
    if (!data) return;
    pin.textContent = pinLabel(data);
    pin.style.left = `${data.x}%`;
    pin.style.top = `${data.y}%`;
    pin.setAttribute('aria-label', `${data.city}, ${pinLabel(data)}`);
  });
}

function setActiveDestination(key, pin) {
  const data = destinationData[key];
  if (!data) return;
  activeDestination = key;
  document.querySelectorAll('.price-pin').forEach(item => item.classList.toggle('is-active', item.dataset.destination === key));
  pin?.classList.add('is-active');

  document.querySelectorAll('[data-map-result]').forEach(card => {
    const image = card.querySelector('[data-result-image]');
    if (image) {
      image.src = data.image;
      image.alt = `${data.city}, ${data.country}`;
    }
    const fields = {
      '[data-result-city]': `${data.city}, ${data.country}`,
      '[data-result-price]': data.price,
      '[data-result-total]': data.total,
      '[data-result-note]': data.note,
      '[data-result-airport]': data.airport,
      '[data-result-hotel]': data.hotel,
      '[data-result-weather]': `${data.weather} · ${data.weatherCondition || ''}`
    };
    Object.entries(fields).forEach(([selector, value]) => {
      const field = card.querySelector(selector);
      if (field) field.textContent = value;
    });
    replaceChildrenWithSpans(card.querySelector('[data-result-tags]'), data.tags || []);
  });

  const routeTitle = document.querySelector('[data-route-title]');
  if (routeTitle) routeTitle.textContent = `תל אביב ← ${data.city} · השוואת עלות מלאה`;
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

async function saveWorkspaceItem(rawItem, button) {
  const item = normalizeWorkspaceItem(rawItem);
  if (!item.external_id || !item.title) return;
  const workspace = readLocalWorkspace();
  const existing = workspace.items.find(saved => saved.id === item.id);
  if (existing?.watch) item.watch = existing.watch;
  workspace.items = [item, ...workspace.items.filter(saved => saved.id !== item.id)].slice(0, 50);
  writeLocalWorkspace(workspace);
  button?.classList.add('is-saved');
  if (button) button.querySelector('span').textContent = 'נשמר לנסיעה';
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

function renderRoutes(routes) {
  const list = document.querySelector('[data-route-list]');
  if (!list) return;
  list.replaceChildren();
  if (!routes.length) {
    appendTextElement(list, 'p', 'עדיין אין השוואת מסלולים מלאה ליעד הזה. שכבת הספקים תוסיף אותה.', 'route-empty');
    return;
  }
  const bestScore = Math.max(...routes.map(route => route.score));
  routes.forEach(route => {
    const button = document.createElement('button');
    button.className = `mini-route${route.score === bestScore ? ' is-selected' : ''}`;
    button.type = 'button';
    button.dataset.route = route.id;
    button.dataset.routeSummary = `${route.label} · ${route.costs.total_formatted}`;
    appendTextElement(button, 'small', route.badge);
    appendTextElement(button, 'strong', `${route.label} · ${route.duration_label}`);
    appendTextElement(button, 'span', `${route.stops ? `${route.stops} עצירה` : 'ישיר'} · ${route.ticket_mode === 'single' ? 'כרטיס אחד' : 'כרטיסים נפרדים'}`);
    const tradeoffs = document.createElement('div');
    tradeoffs.className = 'route-tradeoffs';
    appendTextElement(tradeoffs, 'span', `✓ ${route.pros[0]}`, 'route-pro');
    appendTextElement(tradeoffs, 'span', `△ ${route.cons[0]}`, 'route-con');
    button.append(tradeoffs);
    appendTextElement(button, 'b', route.costs.total_formatted);
    appendTextElement(button, 'em', 'עלות מלאה לאדם');
    button.addEventListener('click', () => selectRoute(button));
    list.append(button);
  });
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
  if (!endpoint || !document.querySelector('.price-pin[data-destination]')) return;
  const url = new URL(endpoint, window.location.origin);
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== undefined && value !== false) url.searchParams.set(key, String(value));
  });
  setDiscoveryStatus('loading', 'מעדכן מחירים, מלונות ומסלולים...');
  try {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Discovery request failed: ${response.status}`);
    const payload = await response.json();
    destinationData = Object.fromEntries(payload.destinations.map(item => [item.id, normalizeDestination(item)]));
    discoveryRoutes = payload.routes || [];
    updateWeatherAttribution(payload.provider_status);
    updatePins();
    const selected = destinationData[params.destination] ? params.destination : (destinationData[activeDestination] ? activeDestination : Object.keys(destinationData)[0]);
    if (selected) setActiveDestination(selected, document.querySelector(`[data-destination="${selected}"]`));
    renderRoutes(discoveryRoutes);
    const layerName = payload.layers?.find(layer => layer.id === activeLayer)?.label || 'מחירים';
    const modeLabel = payload.meta.data_mode === 'live' ? 'נתוני ספקים חיים' : (payload.meta.data_mode === 'mixed' ? 'שילוב נתונים חיים והדגמה' : 'נתוני הדגמה שקופים');
    setDiscoveryStatus(payload.meta.data_mode, `${layerName} · ${payload.meta.result_count} יעדים · ${modeLabel}`);
  } catch (error) {
    destinationData = { ...fallbackDestinations };
    updateWeatherAttribution(null);
    updatePins();
    setActiveDestination(activeDestination, document.querySelector(`[data-destination="${activeDestination}"]`));
    setDiscoveryStatus('fallback', 'מצב הדגמה מקומי · החיבור לספקים עדיין לא פעיל');
    console.warn(error);
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
    action.disabled = !offer.booking.bookable;
    action.textContent = offer.booking.bookable ? 'בחירת הטיסה' : 'הדגמה · הזמנה תיפתח עם ספק חי';
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
    const modeLabels = { live: 'מחירי ספקים חיים', mixed: 'נתונים חיים והערכות', demo: 'מחירי הדגמה שקופים' };
    const cacheLabels = { miss: 'עודכן עכשיו', fresh: 'תוצאה שמורה ועדכנית', stale_refreshing: 'מעדכן ברקע', stale_error: 'תוצאה אחרונה תקינה', degraded_fallback: 'ספק חלקי' };
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
  searchFlights(form);
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
    action.disabled = !property.booking.bookable;
    action.textContent = property.booking.bookable ? 'בדיקת זמינות והזמנה' : 'הדגמה · הזמנה תיפתח עם ספק חי';
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
    const modeLabels = {live: 'מחירי ספקים חיים', mixed: 'נתונים חיים והערכות', demo: 'מחירי הדגמה שקופים'};
    const cacheLabels = {miss: 'עודכן עכשיו', fresh: 'תוצאה שמורה ועדכנית', stale_refreshing: 'מעדכן ברקע', stale_error: 'תוצאה אחרונה תקינה', degraded_fallback: 'ספק חלקי'};
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
  searchHotels(form);
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
    appendTextElement(total, 'em', 'מחיר הדגמה משוער בלבד');
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
    action.disabled = !plan.purchase.purchasable;
    action.textContent = plan.purchase.purchasable ? 'מעבר להצעה ולפוליסה' : 'הדגמה · רכישה תיפתח עם מבטח מחובר';
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
    const modeLabels = {live: 'נתוני מבטחים מחוברים', mixed: 'נתונים חיים והדגמה', demo: 'תוכניות הדגמה שקופות'};
    const cacheLabels = {miss: 'עודכן עכשיו', fresh: 'תוצאה שמורה ועדכנית', stale_refreshing: 'מעדכן ברקע', stale_error: 'תוצאה אחרונה תקינה', degraded_fallback: 'ספק חלקי', bypass_sensitive: 'לא נשמר מטעמי פרטיות'};
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
    searchInsuranceQuotes(form);
  });
  document.querySelector('[data-insurance-risk-reset]')?.addEventListener('click', () => {
    form.elements.trip_type.value = 'city_break';
    form.elements.adventure_sports.checked = false;
    form.elements.winter_sports.checked = false;
    searchInsuranceQuotes(form);
  });
  searchInsuranceQuotes(form);
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
    appendPackageBreakdownRow(journey, `${tripPackage.flight.airline} · ${tripPackage.flight.departure_time}–${tripPackage.flight.return_time}`, `${tripPackage.flight.stops_label} · ${tripPackage.flight.duration_label}`, 'plane-takeoff');
    appendPackageBreakdownRow(journey, `${tripPackage.stay.name} · ${tripPackage.stay.stars}★`, `${payload.trip.nights} לילות · ${tripPackage.stay.guest_score}/10`, 'hotel');
    appendPackageBreakdownRow(journey, tripPackage.insurance.name, tripPackage.insurance.tier === 'none' ? 'לא נכלל' : 'הדגמה בלבד', 'shield-check');
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
    checkout.disabled = !tripPackage.booking.bookable;
    checkout.textContent = tripPackage.booking.bookable ? 'עברו להזמנה מאומתת' : 'הדגמה · הזמנה תיפתח עם ספקים מחוברים';
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
    const modeLabels = {live: 'נתוני ספקים מחוברים', mixed: 'נתונים חיים והדגמה', demo: 'רכיבי הדגמה שקופים'};
    const cacheLabels = {miss: 'עודכן עכשיו', fresh: 'תוצאה שמורה ועדכנית', stale_refreshing: 'מתעדכן ברקע', stale_error: 'תוצאה אחרונה תקינה', degraded_fallback: 'ספק חלקי'};
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
  searchTripPackages(form);
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
  if (!amount) return item.price_label || '—';
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
  [item.destination, item.route, item.data_mode === 'live' ? 'נתון חי' : 'הדגמה שקופה'].filter(Boolean).forEach(value => appendTextElement(context, 'span', value));
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
  if (!root) return;
  travelerWorkspace = readLocalWorkspace();
  renderWorkspaceDashboard();
  hydrateWorkspacePreferences(travelerWorkspace.preferences);
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
    renderWorkspaceDashboard();
    hydrateWorkspacePreferences(travelerWorkspace.preferences);
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
  menuButton?.addEventListener('click', () => {
    const opening = !drawer?.classList.contains('is-open');
    drawer?.classList.toggle('is-open', opening);
    menuButton.setAttribute('aria-expanded', String(opening));
  });
}

function initMap() {
  document.querySelectorAll('.price-pin[data-destination]').forEach(pin => pin.addEventListener('click', event => {
    event.stopPropagation();
    setActiveDestination(pin.dataset.destination, pin);
    hydrateDiscovery({ destination: pin.dataset.destination, layer: activeLayer });
  }));

  document.querySelectorAll('[data-map-zoom]').forEach(button => button.addEventListener('click', () => {
    const globe = button.closest('.globe-panel, .compact-map, .world-canvas')?.querySelector('.globe');
    if (!globe) return;
    const current = Number(globe.dataset.scale || 1);
    const next = button.dataset.mapZoom === 'in' ? Math.min(current + .12, 1.45) : Math.max(current - .12, .78);
    globe.dataset.scale = next;
    globe.style.scale = next;
  }));

  document.querySelectorAll('[data-route]').forEach(card => card.addEventListener('click', () => selectRoute(card)));
  document.querySelectorAll('[data-map-layer]').forEach(button => button.addEventListener('click', () => {
    activeLayer = button.dataset.mapLayer;
    document.querySelectorAll('[data-map-layer]').forEach(item => {
      const selected = item === button;
      item.classList.toggle('is-active', selected);
      item.setAttribute('aria-pressed', String(selected));
    });
    updatePins();
    setActiveDestination(activeDestination);
    hydrateDiscovery({ destination: activeDestination, layer: activeLayer });
  }));

  const budget = document.querySelector('[data-budget]');
  const value = document.querySelector('[data-budget-value]');
  budget?.addEventListener('input', () => { if (value) value.textContent = `$${budget.value}`; });
  document.querySelector('[data-discovery-apply]')?.addEventListener('click', () => {
    const sort = document.querySelector('[data-filter-kind="sort"] .is-active')?.dataset.filterValue || 'smart';
    const direct = document.querySelector('[data-direct-filter]')?.getAttribute('aria-pressed') === 'true';
    hydrateDiscovery({ budget: budget?.value || 5000, direct, sort, layer: activeLayer });
    document.querySelector('.filter-panel')?.classList.remove('is-open');
  });

  const filterPanel = document.querySelector('.filter-panel');
  document.addEventListener('click', event => {
    const filterButton = event.target.closest?.('[data-filter-toggle]');
    if (filterButton) {
      const opening = !filterPanel?.classList.contains('is-open');
      filterPanel?.classList.toggle('is-open', opening);
      filterButton.setAttribute('aria-expanded', String(opening));
      return;
    }
    if (event.target.closest?.('[data-filter-close]')) {
      filterPanel?.classList.remove('is-open');
      document.querySelector('[data-filter-toggle]')?.setAttribute('aria-expanded', 'false');
    }
  }, true);
}

function initControls() {
  document.querySelectorAll('.product-tabs button').forEach(button => button.addEventListener('click', () => {
    document.querySelectorAll('.product-tabs button').forEach(item => item.classList.remove('is-active'));
    button.classList.add('is-active');
    const searchDock = document.querySelector('.search-dock');
    if (searchDock && button.dataset.productAction) searchDock.action = button.dataset.productAction;
  }));
  document.querySelectorAll('[data-filter-kind] button').forEach(button => button.addEventListener('click', () => {
    button.closest('[data-filter-kind]').querySelectorAll('button').forEach(item => item.classList.remove('is-active'));
    button.classList.add('is-active');
  }));
  document.querySelectorAll('.filter-chips:not([data-filter-kind]) button, .experience-chips button').forEach(button => button.addEventListener('click', () => button.classList.toggle('is-active')));
  document.querySelectorAll('.toggle').forEach(toggle => toggle.addEventListener('click', () => {
    toggle.classList.toggle('is-active');
    toggle.setAttribute('aria-pressed', String(toggle.classList.contains('is-active')));
  }));
}

function initTraVelV2() {
  if (document.documentElement.dataset.traVelV2Ready === 'true') return;
  document.documentElement.dataset.traVelV2Ready = 'true';
  renderIcons();
  initNavigation();
  initMap();
  initControls();
  initFlightSearch();
  initHotelSearch();
  initInsuranceQuote();
  initTripPackageSearch();
  initTravelerWorkspace();
  const query = new URLSearchParams(window.location.search).get('q') || '';
  hydrateDiscovery({ q: query, layer: activeLayer });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initTraVelV2, { once: true });
} else {
  initTraVelV2();
}
