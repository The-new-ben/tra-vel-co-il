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
  const query = new URLSearchParams(window.location.search).get('q') || '';
  hydrateDiscovery({ q: query, layer: activeLayer });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initTraVelV2, { once: true });
} else {
  initTraVelV2();
}
