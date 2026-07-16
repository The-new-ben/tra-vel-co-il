const destinationData = {
  bangkok: { city: 'בנגקוק, תאילנד', price: '$742', note: 'חיסכון של $157 דרך דובאי', image: './assets/thailand.jpg', tags: ['12 לילות', 'עצירה אחת', 'כולל כבודה'] },
  athens: { city: 'אתונה, יוון', price: '$189', note: 'המחיר הנמוך ביותר השבוע', image: '../assets/img/city-vienna.webp', tags: ['טיסה ישירה', '3 לילות', 'ביטול גמיש'] },
  budapest: { city: 'בודפשט, הונגריה', price: '$214', note: 'מלון 4★ במרכז כלול', image: '../assets/img/city-budapest.webp', tags: ['טיסה + מלון', '4 לילות', 'ארוחת בוקר'] },
  dubai: { city: 'דובאי, איחוד האמירויות', price: '$236', note: 'המחיר ירד ב־11% היום', image: '../assets/img/hero-budapest-900.webp', tags: ['טיסה ישירה', 'סופ״ש', 'טרולי כלול'] },
  tokyo: { city: 'טוקיו, יפן', price: '$918', note: 'חלון מחיר טוב לחודש נובמבר', image: './assets/thailand.jpg', tags: ['עצירה אחת', '14 לילות', 'כבודה כלולה'] },
  lisbon: { city: 'ליסבון, פורטוגל', price: '$327', note: 'שילוב טיסות חכם דרך רומא', image: '../assets/img/city-prague.webp', tags: ['7 לילות', 'עצירה אחת', 'מלון מומלץ'] }
};

function renderIcons() {
  if (window.lucide) window.lucide.createIcons({ attrs: { 'stroke-width': 1.8 } });
}

function setActiveDestination(key, pin) {
  const data = destinationData[key];
  const card = document.querySelector('[data-map-result]');
  if (!data || !card) return;
  document.querySelectorAll('.price-pin').forEach(item => item.classList.remove('is-active'));
  pin?.classList.add('is-active');
  const image = card.querySelector('[data-result-image]');
  if (image) {
    image.src = data.image;
    image.alt = data.city;
  }
  card.querySelector('[data-result-city]').textContent = data.city;
  card.querySelector('[data-result-price]').textContent = data.price;
  card.querySelector('[data-result-note]').textContent = data.note;
  const tags = card.querySelector('[data-result-tags]');
  if (tags) tags.innerHTML = data.tags.map(tag => `<span>${tag}</span>`).join('');
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
  document.querySelectorAll('.price-pin[data-destination]').forEach(pin => {
    pin.addEventListener('click', event => {
      event.stopPropagation();
      setActiveDestination(pin.dataset.destination, pin);
    });
  });

  document.querySelectorAll('[data-map-zoom]').forEach(button => button.addEventListener('click', () => {
    const globe = button.closest('.globe-panel, .compact-map, .world-canvas')?.querySelector('.globe');
    if (!globe) return;
    const current = Number(globe.dataset.scale || 1);
    const next = button.dataset.mapZoom === 'in' ? Math.min(current + .12, 1.45) : Math.max(current - .12, .78);
    globe.dataset.scale = next;
    globe.style.scale = next;
  }));

  document.querySelectorAll('[data-route]').forEach(card => card.addEventListener('click', () => {
    document.querySelectorAll('[data-route]').forEach(item => item.classList.remove('is-selected'));
    card.classList.add('is-selected');
    const summary = document.querySelector('[data-route-summary]');
    if (summary) summary.textContent = card.dataset.routeSummary || 'המסלול נבחר';
  }));

  const budget = document.querySelector('[data-budget]');
  const value = document.querySelector('[data-budget-value]');
  budget?.addEventListener('input', () => {
    if (value) value.textContent = `$${budget.value}`;
    document.querySelectorAll('.price-pin').forEach(pin => {
      const amount = Number(pin.textContent.replace(/\D/g, ''));
      pin.hidden = amount > Number(budget.value);
    });
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
  document.querySelectorAll('.filter-chips button').forEach(button => button.addEventListener('click', () => button.classList.toggle('is-active')));
  document.querySelectorAll('.experience-chips button').forEach(button => button.addEventListener('click', () => button.classList.toggle('is-active')));
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
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initTraVelV2, { once: true });
} else {
	initTraVelV2();
}
