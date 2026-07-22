/**
 * Tra-Vel next-action library (theme 1.32.0).
 *
 * Three small shared capabilities that app.js wires into real moments:
 * - Beacon: one soft pulsing cue on a single element at a time, with an
 *   optional polite live-region hint. It never scrolls, never steals focus,
 *   never shifts layout, and any interaction with the marked element clears
 *   it. Reduced motion swaps the pulse for a static ring.
 * - Intent memory v1: the traVelIntent localStorage key holds at most three
 *   preference ids (party, month, budget) plus updatedAt. Device local only,
 *   unknown values are dropped on read, and reset removes the key.
 * - Chip text helpers: compose or remove a natural Hebrew fragment inside
 *   composer text so refinement chips stay reversible.
 */
(function () {
  'use strict';

  const KEY = 'traVelIntent';
  const FIELDS = {
    party: ['couple', 'couple_2', 'family', 'friends'],
    month: ['this_month', 'next_month', 'flex'],
    budget: ['b3000', 'b5000', 'treat']
  };

  const calm = () => {
    try {
      return window.matchMedia('(prefers-reduced-motion: reduce)').matches === true;
    } catch (error) {
      return false;
    }
  };

  const beacon = { el: null, fn: null, live: null };

  function clearBeacon() {
    if (beacon.el) {
      ['pointerdown', 'keydown'].forEach(type => beacon.el.removeEventListener(type, beacon.fn, true));
      beacon.el.classList.remove('next-action-beacon', 'next-action-beacon-static');
    }
    beacon.el = beacon.fn = null;
    if (beacon.live) beacon.live.textContent = '';
  }

  function showBeacon(target, hint) {
    if (!target || target.disabled === true || target.hidden === true) return false;
    clearBeacon();
    target.classList.add('next-action-beacon');
    if (calm()) target.classList.add('next-action-beacon-static');
    beacon.el = target;
    beacon.fn = () => clearBeacon();
    ['pointerdown', 'keydown'].forEach(type => target.addEventListener(type, beacon.fn, true));
    if (!beacon.live) {
      beacon.live = document.createElement('p');
      beacon.live.className = 'next-action-live';
      beacon.live.setAttribute('role', 'status');
      beacon.live.setAttribute('aria-live', 'polite');
      if (typeof document.body?.append === 'function') document.body.append(beacon.live);
    }
    const label = String(hint || target.textContent || '').trim().slice(0, 90);
    beacon.live.textContent = label ? `הצעד הבא: ${label}` : 'הצעד הבא מסומן על המסך';
    return true;
  }

  function readIntentMemory() {
    let data = null;
    try {
      data = JSON.parse(window.localStorage.getItem(KEY) || 'null');
    } catch (error) {
      return null;
    }
    if (!data || typeof data !== 'object') return null;
    const clean = {};
    for (const field in FIELDS) {
      if (FIELDS[field].includes(data[field])) clean[field] = data[field];
    }
    if (!Object.keys(clean).length) return null;
    if (typeof data.updatedAt === 'string') clean.updatedAt = data.updatedAt;
    return clean;
  }

  function writeIntentMemory(patch) {
    const next = Object.assign({}, readIntentMemory() || {});
    for (const field in FIELDS) {
      if (patch && FIELDS[field].includes(patch[field])) next[field] = patch[field];
      else if (patch && patch[field] === null) delete next[field];
    }
    next.updatedAt = new Date().toISOString();
    try {
      window.localStorage.setItem(KEY, JSON.stringify(next));
      return next;
    } catch (error) {
      return null;
    }
  }

  function resetIntentMemory() {
    try {
      window.localStorage.removeItem(KEY);
      return true;
    } catch (error) {
      return false;
    }
  }

  function composeChipText(current, fragment) {
    const base = String(current || '').trim();
    return base ? `${base}, ${fragment}` : fragment;
  }

  function removeChipText(current, fragment) {
    const source = String(current || '');
    for (const pattern of [`, ${fragment}`, `${fragment}, `, fragment]) {
      if (source.includes(pattern)) return source.replace(pattern, '').trim();
    }
    return source.trim();
  }

  window.traVelNextAction = {
    show: showBeacon,
    clear: clearBeacon,
    current: () => beacon.el,
    prefersCalm: calm,
    readIntentMemory,
    writeIntentMemory,
    resetIntentMemory,
    composeChipText,
    removeChipText
  };
})();
