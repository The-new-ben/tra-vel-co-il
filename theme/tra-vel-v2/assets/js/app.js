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
    card.append(action);
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
    body.append(action);
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
  initFlightSearch();
  initHotelSearch();
  const query = new URLSearchParams(window.location.search).get('q') || '';
  hydrateDiscovery({ q: query, layer: activeLayer });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initTraVelV2, { once: true });
} else {
  initTraVelV2();
}
