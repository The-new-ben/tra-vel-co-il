(function () {
  'use strict';
  const root = document.querySelector('[data-stay-shortlist]');
  if (!root || !window.TravelStayCost) return;
  const engine = window.TravelStayCost;
  const form = root.querySelector('#tr-stay-shortlist-form');
  const hotelInputs = root.querySelector('#tr-stay-hotel-inputs');
  const results = root.querySelector('#tr-stay-results');
  const resultGrid = root.querySelector('#tr-stay-result-grid');
  const summary = root.querySelector('#tr-stay-error-summary');
  const status = root.querySelector('#tr-stay-comparison-status');
  const addButton = root.querySelector('#tr-stay-add-hotel');
  const calculateButton = root.querySelector('#tr-stay-calculate');
  let calculated = false;
  let favorite = null;
  let editTimer = null;
  const get = key => root.querySelector(`[data-field="${key}"]`);
  const value = key => get(key)?.value || '';
  const create = (tag, text = '', className = '') => {
    const element = document.createElement(tag);
    element.textContent = text;
    if (className) element.className = className;
    return element;
  };
  const currencyText = (minor, currency) => new Intl.NumberFormat('he-IL', { style: 'currency', currency, minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(minor / 100);
  const countHotels = () => hotelInputs.querySelectorAll('[data-hotel]').length;

  function syncExtraVisibility() {
    hotelInputs.querySelectorAll('[data-extra]').forEach(extra => {
      const state = extra.querySelector('[data-field$=".state"]');
      const config = extra.querySelector('[data-extra-config]');
      config.hidden = state.value !== 'extra';
      config.querySelectorAll('input,select').forEach(input => { input.disabled = config.hidden; });
      const dayField = extra.querySelector('[data-extra-days]');
      const period = extra.querySelector('[data-field$=".period"]');
      dayField.hidden = period.value !== 'day';
      dayField.querySelector('input').disabled = config.hidden || dayField.hidden;
    });
  }

  function readState() {
    return {
      currency: value('currency'), nights: value('nights'), guests: value('guests'), rooms: value('rooms'),
      hotels: Array.from(hotelInputs.querySelectorAll('[data-hotel]'), card => {
        const p = `hotels.${card.dataset.hotel}`;
        const extras = {};
        for (const key of Object.keys(engine.EXTRA_LABELS)) {
          const e = `${p}.extras.${key}`;
          extras[key] = { state: value(e + '.state'), amount: value(e + '.amount'), basis: value(e + '.basis'), period: value(e + '.period'), days: value(e + '.days') };
        }
        return { name: value(p + '.name'), quote: { amount: value(p + '.quote.amount'), basis: value(p + '.quote.basis'), period: value(p + '.quote.period') }, extras };
      })
    };
  }

  function clearErrors() {
    root.querySelectorAll('[aria-invalid]').forEach(field => { field.removeAttribute('aria-invalid'); field.removeAttribute('aria-describedby'); });
    root.querySelectorAll('.error').forEach(error => { error.hidden = true; error.textContent = ''; });
    summary.hidden = true;
    summary.replaceChildren();
  }

  function showErrors(errors, focus) {
    const list = create('ul');
    summary.append(create('p', 'עוד פרט קטן, ונוכל לראות את ההצעות זו לצד זו.'), list);
    errors.forEach(error => {
      const field = get(error.field);
      if (!field) { list.append(create('li', error.message)); return; }
      const errorElement = root.querySelector('#' + field.id + '-error');
      field.setAttribute('aria-invalid', 'true');
      if (errorElement) { errorElement.textContent = error.message; errorElement.hidden = false; field.setAttribute('aria-describedby', errorElement.id); }
      const item = create('li');
      const link = create('a', error.message);
      link.href = '#' + field.id;
      link.addEventListener('click', event => { event.preventDefault(); const details = field.closest('details'); if (details) details.open = true; field.focus(); });
      item.append(link); list.append(item);
    });
    summary.hidden = false;
    if (focus) {
      const first = get(errors[0]?.field);
      if (first) { const details = first.closest('details'); if (details) details.open = true; first.focus(); }
      else summary.focus();
    }
  }

  function formula(line, currency) {
    const unit = line.basis === 'person' ? 'אורחים' : line.basis === 'room' ? 'חדרים' : 'הרכב';
    const multiplier = line.basis === 'party' ? 'לכל ההרכב' : `${line.quantity} ${unit}`;
    const period = line.period === 'night' ? `${line.periods} לילות` : line.period === 'day' ? `${line.periods} ימים` : 'פעם אחת';
    return `${currencyText(line.unitMinor, currency)} × ${multiplier} · ${period}`;
  }

  function renderCards(calculation) {
    resultGrid.replaceChildren();
    resultGrid.classList.toggle('has-three', calculation.results.length === 3);
    calculation.results.forEach((hotel, index) => {
      const card = create('article', '', 'result-card');
      card.dataset.resultHotel = String(index);
      card.classList.toggle('is-favorite', favorite === index);
      card.append(create('h4', hotel.name));
      const total = create('div', '', 'hotel-total-grid');
      const totalText = currencyText(hotel.knownTotalMinor, calculation.currency);
      total.append(create('small', 'העלות הידועה לכל ההרכב'), create('strong', totalText, totalText.length > 14 ? 'money money-long' : 'money'), create('span', `${currencyText(hotel.averagePerGuestMinor, calculation.currency)} בממוצע לאדם`, 'per-person'));
      card.append(total);
      const breakdown = create('ul', '', 'breakdown');
      hotel.lines.filter(line => line.state === 'known').forEach(line => {
        const item = create('li');
        const label = create('span', line.label);
        label.append(create('small', formula(line, calculation.currency), 'formula'));
        item.append(label, create('strong', currencyText(line.minor, calculation.currency), 'money line-money'));
        breakdown.append(item);
      });
      card.append(breakdown);
      if (hotel.included.length) {
        const chips = create('div', '', 'hotel-policy-chips');
        hotel.included.forEach(label => chips.append(create('span', label + ' כלול בהצעה')));
        card.append(chips);
      }
      const remaining = create('div', '', 'remaining');
      remaining.append(create('strong', hotel.unknown.length ? 'לפני שמחליטים' : 'כל הסכומים שהזנתם בפנים'));
      remaining.append(create('p', hotel.unknown.length ? `עוד לברר: ${hotel.unknown.join(', ')}. הסכום אינו כולל אותם כרגע.` : 'כדאי לוודא שהחדר ותנאי ההצעה מתאימים להרכב שלכם.'));
      card.append(remaining);
      if (hotel.notPlanned.length) card.append(create('p', `לא נכלל בתכנון הזה: ${hotel.notPlanned.join(', ')}.`, 'help'));
      const choice = create('button', favorite === index ? 'זה הכיוון שלי ✓' : 'זה המלון שאני נוטה אליו', 'button secondary choice');
      choice.type = 'button';
      choice.setAttribute('aria-pressed', String(favorite === index));
      choice.addEventListener('click', () => { favorite = favorite === index ? null : index; renderCards(calculation); resultGrid.querySelector(`[data-result-hotel="${index}"] .choice`).focus(); status.textContent = favorite === null ? 'אפשר להמשיך להתלבט ולשנות את הסכומים.' : `${hotel.name} הוא הכיוון שלכם. אפשר לשנות את הבחירה מתי שתרצו.`; });
      card.append(choice);
      resultGrid.append(card);
    });
    results.hidden = false;
  }

  function calculate(focus = false) {
    clearTimeout(editTimer);
    clearErrors();
    calculated = true;
    const calculation = engine.calculateComparison(readState());
    if (!calculation.valid) {
      results.hidden = true;
      resultGrid.replaceChildren();
      showErrors(calculation.errors, focus);
      status.textContent = 'ההשוואה מחכה לפרטים החסרים; סכומים ישנים אינם מוצגים.';
      return;
    }
    renderCards(calculation);
    status.textContent = 'ההשוואה מעודכנת לפי מה שהזנתם. אפשר להמשיך לערוך ולבחור.';
    if (focus) { root.querySelector('#tr-stay-results-title').tabIndex = -1; root.querySelector('#tr-stay-results-title').focus(); }
  }

  function queueEdit(event) {
    const field = event.target;
    if (field.matches('[data-field$=".days"]')) field.dataset.manualDays = 'true';
    if (field.dataset.field === 'nights') {
      hotelInputs.querySelectorAll('[data-field$=".days"]').forEach(day => { if (day.dataset.manualDays !== 'true') day.value = value('nights'); });
    }
    syncExtraVisibility();
    if (calculated) { results.hidden = true; resultGrid.replaceChildren(); clearTimeout(editTimer); editTimer = setTimeout(() => calculate(false), 120); }
  }

  form.addEventListener('input', queueEdit);
  form.addEventListener('change', queueEdit);
  form.addEventListener('submit', event => { event.preventDefault(); calculate(true); });
  calculateButton.addEventListener('click', () => calculate(true));
  addButton.addEventListener('click', () => {
    if (countHotels() >= 3) return;
    const template = document.createElement('template');
    template.innerHTML = document.querySelector('#tr-stay-hotel-template').innerHTML.replaceAll('__INDEX__', '2');
    hotelInputs.append(template.content.cloneNode(true));
    hotelInputs.classList.add('has-three');
    addButton.disabled = true;
    syncExtraVisibility();
    get('hotels.2.name').focus();
    if (calculated) calculate(false);
  });
  hotelInputs.addEventListener('click', event => {
    const remove = event.target.closest('[data-remove-hotel]');
    if (!remove) return;
    remove.closest('[data-hotel]').remove();
    hotelInputs.classList.remove('has-three');
    addButton.disabled = false;
    if (favorite === 2) favorite = null;
    addButton.focus();
    if (calculated) calculate(false);
  });
  root.querySelector('#tr-stay-reset-shortlist').addEventListener('click', () => {
    clearTimeout(editTimer);
    form.reset();
    hotelInputs.querySelector('[data-hotel="2"]')?.remove();
    hotelInputs.classList.remove('has-three');
    hotelInputs.querySelectorAll('[data-field$=".days"]').forEach(day => { delete day.dataset.manualDays; });
    favorite = null; calculated = false;
    clearErrors(); resultGrid.replaceChildren(); results.hidden = true;
    addButton.disabled = false;
    syncExtraVisibility();
    status.textContent = 'לוח חדש, בלי לאבד את החשק לחופשה. אפשר להתחיל משני מלונות אחרים.';
    get('currency').focus();
  });
  syncExtraVisibility();
  calculateButton.disabled = false;
  addButton.disabled = false;
})();
