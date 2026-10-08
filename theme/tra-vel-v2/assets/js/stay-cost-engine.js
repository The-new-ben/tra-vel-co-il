(function (root) {
  'use strict';
  const CURRENCIES = Object.freeze(['EUR', 'HUF', 'ILS', 'USD', 'GBP']);
  const LIMITS = Object.freeze({ nights: 365, guests: 50, rooms: 20, days: 366, moneyMinor: 100000000, totalMinor: 100000000000 });
  const EXTRA_LABELS = Object.freeze({ breakfast: 'ארוחת בוקר', transport: 'נסיעות', fees: 'מסים ועמלות' });

  function parseMoney(value) {
    const text = String(value ?? '').trim();
    if (!text) return { valid: false, reason: 'missing' };
    if (!/^\d+(?:[.,]\d{1,2})?$/.test(text)) return { valid: false, reason: 'format' };
    const parts = text.replace(',', '.').split('.');
    if (parts[0].length > 8) return { valid: false, reason: 'limit' };
    const minor = Number(parts[0]) * 100 + Number((parts[1] || '').padEnd(2, '0'));
    return Number.isSafeInteger(minor) && minor <= LIMITS.moneyMinor
      ? { valid: true, minor }
      : { valid: false, reason: 'limit' };
  }

  function readInteger(value, max) {
    const text = String(value ?? '').trim();
    if (!/^\d{1,4}$/.test(text)) return null;
    const number = Number(text);
    return Number.isInteger(number) && number >= 1 && number <= max ? number : null;
  }

  function calculateComparison(input) {
    const errors = [];
    const addError = (field, message) => errors.push({ field, message });
    const currency = String(input?.currency || '');
    if (!CURRENCIES.includes(currency)) addError('currency', 'בחרו מטבע אחד לכל ההצעות.');
    const nights = readInteger(input?.nights, LIMITS.nights);
    const guests = readInteger(input?.guests, LIMITS.guests);
    const rooms = readInteger(input?.rooms, LIMITS.rooms);
    if (nights === null) addError('nights', 'הזינו מספר שלם בין 1 ל־365 לילות.');
    if (guests === null) addError('guests', 'הזינו מספר שלם בין 1 ל־50 אורחים.');
    if (rooms === null) addError('rooms', 'הזינו מספר שלם בין 1 ל־20 חדרים.');
    const hotels = Array.isArray(input?.hotels) ? input.hotels : [];
    if (hotels.length < 2 || hotels.length > 3) addError('hotels', 'ההשוואה מיועדת לשניים או שלושה מלונות.');

    const readAmount = (value, field) => {
      const parsed = parseMoney(value);
      if (!parsed.valid) {
        addError(field, parsed.reason === 'limit'
          ? 'הסכום המרבי לשורה הוא 1,000,000.'
          : 'הזינו סכום מאפס ומעלה, עד שתי ספרות אחרי הנקודה, ללא מפרידי אלפים.');
        return null;
      }
      return parsed.minor;
    };
    const results = hotels.map((hotel, index) => {
      const prefix = `hotels.${index}`;
      const name = String(hotel?.name || '').trim();
      if (!name || name.length > 80 || /[\u0000-\u001f]/.test(name)) addError(`${prefix}.name`, 'כתבו שם מלון באורך של עד 80 תווים.');
      const quote = hotel?.quote || {};
      const amount = readAmount(quote.amount, `${prefix}.quote.amount`);
      if (!['room', 'person'].includes(quote.basis)) addError(`${prefix}.quote.basis`, 'בחרו אם ההצעה היא לחדר או לאדם.');
      if (!['night', 'stay'].includes(quote.period)) addError(`${prefix}.quote.period`, 'בחרו אם ההצעה היא ללילה או לכל השהייה.');
      const quantitiesReady = nights !== null && guests !== null && rooms !== null;
      const quantity = quote.basis === 'person' ? guests : rooms;
      const periods = quote.period === 'night' ? nights : 1;
      const lodgingMinor = amount !== null && quantitiesReady ? amount * quantity * periods : null;
      let knownTotalMinor = lodgingMinor ?? 0;
      const lines = [{ key: 'lodging', label: 'לינה', state: 'known', minor: lodgingMinor, unitMinor: amount, basis: quote.basis, period: quote.period, quantity, periods }];
      const included = [];
      const unknown = [];
      const notPlanned = [];

      for (const [key, label] of Object.entries(EXTRA_LABELS)) {
        const extra = hotel?.extras?.[key] || {};
        const state = extra.state || 'unknown';
        if (!['unknown', 'included', 'extra', 'not_planned'].includes(state)) {
          addError(`${prefix}.extras.${key}.state`, 'בחרו מה ידוע על התוספת.');
          continue;
        }
        if (state !== 'extra') {
          if (state === 'unknown') unknown.push(label);
          if (state === 'included') included.push(label);
          if (state === 'not_planned') notPlanned.push(label);
          lines.push({ key, label, state, minor: state === 'unknown' ? null : 0 });
          continue;
        }
        const extraPrefix = `${prefix}.extras.${key}`;
        const extraMinor = readAmount(extra.amount, `${extraPrefix}.amount`);
        if (!['party', 'person', 'room'].includes(extra.basis)) addError(`${extraPrefix}.basis`, 'בחרו למי מתייחס החיוב.');
        if (!['stay', 'day'].includes(extra.period)) addError(`${extraPrefix}.period`, 'בחרו חיוב חד־פעמי או יומי.');
        const days = extra.period === 'day' ? readInteger(extra.days, LIMITS.days) : 1;
        if (days === null) addError(`${extraPrefix}.days`, 'הזינו בין 1 ל־366 ימים לחיוב הזה.');
        const units = extra.basis === 'person' ? guests : extra.basis === 'room' ? rooms : 1;
        const minor = extraMinor !== null && quantitiesReady && days !== null ? extraMinor * units * days : null;
        if (minor !== null) knownTotalMinor += minor;
        lines.push({ key, label, state: 'known', minor, unitMinor: extraMinor, basis: extra.basis, period: extra.period, quantity: units, periods: days });
      }
      if (!Number.isSafeInteger(knownTotalMinor) || knownTotalMinor > LIMITS.totalMinor) addError(`${prefix}.quote.amount`, 'הסכום הכולל גדול מדי לחישוב הזה. בדקו את הסכומים ואת יחידות החיוב.');
      return { name, knownTotalMinor, lodgingMinor, averagePerGuestMinor: guests ? Math.round(knownTotalMinor / guests) : null, included, unknown, notPlanned, complete: unknown.length === 0, lines };
    });
    return { valid: errors.length === 0, errors, currency, nights, guests, rooms, results: errors.length ? [] : results };
  }

  const api = Object.freeze({ CURRENCIES, LIMITS, EXTRA_LABELS, parseMoney, calculateComparison });
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  if (root) root.TravelStayCost = api;
})(typeof window !== 'undefined' ? window : null);
