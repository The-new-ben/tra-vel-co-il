/**
 * Trip proposal panel behaviour (theme 1.40.0).
 *
 * The panel a visitor opens was finished on the server. This file adds
 * exactly five things on top of that finished markup, and nothing else:
 * a reveal (with a short honest staging sequence that reduced motion skips),
 * a traveler stepper that multiplies the fares the server printed, a switch
 * between the real flight records the server rendered, add-on toggles that
 * never touch a price, and a WhatsApp link refresh that only ever swaps
 * whole message lines the server itself authored into data attributes.
 *
 * There is no request here, no navigation, no form, and no scroll, wheel or
 * touchmove listener. The only exits are the two plain anchors the server
 * rendered: the record's tracked supplier link and the owned WhatsApp
 * concierge. Nothing is ever submitted or charged from this file, and an
 * untouched open panel only ever earns a soft pulse on its primary action
 * through the shared next-action beacon.
 */
(function () {
  'use strict';

  var FILL_STEP_MS = 520;
  var FILL_HOLD_MS = 300;
  var IDLE_BEACON_MS = 6000;
  var FALLBACK_MIN = 1;
  var FALLBACK_MAX = 6;
  // The device intent memory stores a party style, not a number. This is the
  // same mapping app.js already uses when it writes those styles from real
  // adult and child counts, read in the opposite direction.
  var INTENT_PARTY_TRAVELERS = { couple: 2, couple_2: 4, family: 5, friends: 3 };

  var open = { panel: null, trigger: null, idleTimer: 0 };

  function groupAmount(amount) {
    return String(amount).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function boundedTravelers(value, minimum, maximum) {
    var count = Number(value);
    var low = Number.isInteger(Number(minimum)) ? Number(minimum) : FALLBACK_MIN;
    var high = Number.isInteger(Number(maximum)) ? Number(maximum) : FALLBACK_MAX;
    if (!Number.isFinite(count)) return low;
    return Math.max(low, Math.min(high, Math.round(count)));
  }

  function panelBounds(panel) {
    return {
      minimum: boundedTravelers(panel.getAttribute('data-trip-proposal-min'), FALLBACK_MIN, FALLBACK_MAX),
      maximum: boundedTravelers(panel.getAttribute('data-trip-proposal-max'), FALLBACK_MIN, FALLBACK_MAX)
    };
  }

  function prefersReducedMotion() {
    try {
      return window.matchMedia('(prefers-reduced-motion: reduce)').matches === true;
    } catch (error) {
      return false;
    }
  }

  function nextActionModule() {
    return window.traVelNextAction || null;
  }

  function intentTravelers() {
    var guide = nextActionModule();
    if (!guide || typeof guide.readIntentMemory !== 'function') return 0;
    var memory = guide.readIntentMemory();
    var mapped = memory && INTENT_PARTY_TRAVELERS[memory.party];
    return Number.isInteger(mapped) ? mapped : 0;
  }

  function currentTravelers(panel) {
    var bounds = panelBounds(panel);
    return boundedTravelers(panel.getAttribute('data-trip-proposal-travelers'), bounds.minimum, bounds.maximum);
  }

  function serverTravelers(panel) {
    var bounds = panelBounds(panel);
    var stored = Number(panel.getAttribute('data-trip-proposal-server-travelers'));
    return boundedTravelers(Number.isFinite(stored) && stored > 0 ? stored : panel.getAttribute('data-trip-proposal-travelers'), bounds.minimum, bounds.maximum);
  }

  function partyLabel(panel, travelers) {
    if (travelers === 1) return String(panel.getAttribute('data-trip-proposal-party-one') || '');
    return String(panel.getAttribute('data-trip-proposal-party-many') || '').replace('%s', groupAmount(travelers));
  }

  function tierSections(panel) {
    return panel.querySelectorAll('[data-trip-proposal-tier]');
  }

  function currentTierSection(panel) {
    var sections = tierSections(panel);
    for (var index = 0; index < sections.length; index += 1) {
      if (!sections[index].hidden) return sections[index];
    }
    return sections[0] || null;
  }

  function chosenAddonLabels(panel) {
    var toggles = panel.querySelectorAll('[data-trip-proposal-addon-toggle]');
    var labels = [];
    for (var index = 0; index < toggles.length; index += 1) {
      if (toggles[index].getAttribute('aria-checked') === 'true') {
        var label = String(toggles[index].getAttribute('data-trip-proposal-addon-label') || '');
        if (label) labels.push(label);
      }
    }
    return labels;
  }

  function travelersLines(panel) {
    try {
      var lines = JSON.parse(panel.getAttribute('data-trip-proposal-wa-travelers-lines') || '[]');
      return Array.isArray(lines) ? lines : [];
    } catch (error) {
      return [];
    }
  }

  // Rebuild the WhatsApp href from the pristine server-built URL every time,
  // so edits never accumulate drift. Only three substitutions exist, and each
  // one swaps a whole line the server authored: the traveler count line, the
  // chosen record's dates line, and the ticked add-ons line. The join
  // separator for a subset of add-ons mirrors the server's own implode(', ').
  function whatsappHref(panel) {
    var chosen = chosenAddonLabels(panel);
    var base = chosen.length
      ? String(panel.getAttribute('data-trip-proposal-wa-all') || panel.getAttribute('data-trip-proposal-wa') || '')
      : String(panel.getAttribute('data-trip-proposal-wa') || '');
    if (!base) return '';

    var url;
    try {
      url = new URL(base);
    } catch (error) {
      return '';
    }
    var text = String(url.searchParams.get('text') || '');

    var lines = travelersLines(panel);
    var defaultLine = lines[serverTravelers(panel) - 1];
    var chosenLine = lines[currentTravelers(panel) - 1];
    if (defaultLine && chosenLine && defaultLine !== chosenLine) text = text.replace(defaultLine, chosenLine);

    var defaultDates = String(panel.getAttribute('data-trip-proposal-wa-dates-line') || '');
    var section = currentTierSection(panel);
    var tierDates = section ? String(section.getAttribute('data-trip-proposal-tier-wa-dates') || '') : defaultDates;
    if (defaultDates && tierDates && tierDates !== defaultDates) text = text.replace(defaultDates, tierDates);
    else if (defaultDates && !tierDates) text = text.replace('\n' + defaultDates, '');

    if (chosen.length) {
      var fullLine = String(panel.getAttribute('data-trip-proposal-wa-addons-line') || '');
      var template = String(panel.getAttribute('data-trip-proposal-wa-addons-template') || '');
      var subsetLine = template ? template.replace('%s', chosen.join(', ')) : '';
      if (fullLine && subsetLine && subsetLine !== fullLine) text = text.replace(fullLine, subsetLine);
    }

    url.searchParams.set('text', text);
    return url.toString();
  }

  function syncWhatsApp(panel) {
    var anchor = panel.querySelector('[data-trip-proposal-whatsapp]');
    if (!anchor) return;
    var href = whatsappHref(panel);
    if (href) anchor.setAttribute('href', href);
  }

  function syncTotalLine(panel) {
    var line = panel.querySelector('[data-trip-proposal-total]');
    var section = currentTierSection(panel);
    var total = section ? section.querySelector('[data-trip-proposal-tier-total]') : null;
    if (!line || !total) return;
    var unit = Number(total.getAttribute('data-trip-proposal-tier-unit'));
    var symbol = String(total.getAttribute('data-trip-proposal-tier-symbol') || '');
    var template = String(panel.getAttribute('data-trip-proposal-total-template') || '');
    if (!Number.isInteger(unit) || unit <= 0 || !symbol || !template) return;
    line.textContent = template.replace('%s', symbol + groupAmount(unit * currentTravelers(panel)));
  }

  function applyTravelers(panel, travelers) {
    var bounds = panelBounds(panel);
    var count = boundedTravelers(travelers, bounds.minimum, bounds.maximum);
    panel.setAttribute('data-trip-proposal-travelers', String(count));

    var totals = panel.querySelectorAll('[data-trip-proposal-tier-total]');
    for (var index = 0; index < totals.length; index += 1) {
      var node = totals[index];
      var unit = Number(node.getAttribute('data-trip-proposal-tier-unit'));
      var symbol = String(node.getAttribute('data-trip-proposal-tier-symbol') || '');
      if (!Number.isInteger(unit) || unit <= 0 || !symbol) continue;
      node.textContent = symbol + groupAmount(unit * count);
    }

    var party = partyLabel(panel, count);
    var parties = panel.querySelectorAll('[data-trip-proposal-tier-party]');
    for (index = 0; index < parties.length; index += 1) parties[index].textContent = party;

    var value = panel.querySelector('[data-trip-proposal-travelers-value]');
    if (value) value.textContent = groupAmount(count);

    var steps = panel.querySelectorAll('[data-trip-proposal-step]');
    for (index = 0; index < steps.length; index += 1) {
      var delta = Number(steps[index].getAttribute('data-trip-proposal-step'));
      steps[index].disabled = (delta < 0 && count <= bounds.minimum) || (delta > 0 && count >= bounds.maximum);
    }

    syncTotalLine(panel);
    syncWhatsApp(panel);
    return count;
  }

  // Switching only ever lands on a record the server rendered: an unknown key
  // fails closed and changes nothing.
  function chooseTier(panel, key) {
    var sections = tierSections(panel);
    var target = null;
    var index;
    for (index = 0; index < sections.length; index += 1) {
      if (sections[index].getAttribute('data-trip-proposal-tier') === key) target = sections[index];
    }
    if (!target) return false;

    for (index = 0; index < sections.length; index += 1) sections[index].hidden = sections[index] !== target;

    var choices = panel.querySelectorAll('[data-trip-proposal-tier-choice]');
    for (index = 0; index < choices.length; index += 1) {
      var isCurrent = choices[index].getAttribute('data-trip-proposal-tier-choice') === key;
      choices[index].setAttribute('aria-pressed', isCurrent ? 'true' : 'false');
      choices[index].classList.toggle('is-current', isCurrent);
    }

    var book = panel.querySelector('[data-trip-proposal-book]');
    var link = String(target.getAttribute('data-trip-proposal-tier-link') || '');
    if (book && link) book.setAttribute('href', link);

    syncTotalLine(panel);
    syncWhatsApp(panel);
    return true;
  }

  // Visual choice only: an add-on has no observed price, so toggling it never
  // touches the flight total. It reveals the supplier's own link when one is
  // configured and carries the wish into the WhatsApp message.
  function toggleAddon(panel, toggle) {
    var checked = toggle.getAttribute('aria-checked') === 'true';
    toggle.setAttribute('aria-checked', checked ? 'false' : 'true');
    var row = typeof toggle.closest === 'function' ? toggle.closest('[data-trip-proposal-addon]') : null;
    var link = row ? row.querySelector('[data-trip-proposal-addon-link]') : null;
    if (link) link.hidden = checked;
    syncWhatsApp(panel);
    return !checked;
  }

  function clearIdleBeacon() {
    if (open.idleTimer) {
      window.clearTimeout(open.idleTimer);
      open.idleTimer = 0;
    }
  }

  function armIdleBeacon(panel) {
    clearIdleBeacon();
    var guide = nextActionModule();
    if (!guide || typeof guide.show !== 'function') return;
    open.idleTimer = window.setTimeout(function () {
      open.idleTimer = 0;
      if (open.panel !== panel || panel.hidden) return;
      guide.show(panel.querySelector('[data-trip-proposal-book]'));
    }, IDLE_BEACON_MS);
  }

  function noteInteraction() {
    clearIdleBeacon();
  }

  function runFill(panel) {
    var fill = panel.querySelector('[data-trip-proposal-fill]');
    var lines = fill ? fill.querySelectorAll('[data-trip-proposal-fill-line]') : [];
    if (!fill || !lines.length || prefersReducedMotion()) {
      if (fill) fill.hidden = true;
      panel.setAttribute('data-trip-proposal-state', 'ready');
      return 0;
    }

    panel.setAttribute('data-trip-proposal-state', 'filling');
    fill.hidden = false;
    for (var index = 0; index < lines.length; index += 1) {
      (function (line, position) {
        window.setTimeout(function () {
          line.setAttribute('data-trip-proposal-fill-shown', 'true');
        }, position * FILL_STEP_MS);
      }(lines[index], index));
    }

    var total = lines.length * FILL_STEP_MS + FILL_HOLD_MS;
    window.setTimeout(function () {
      fill.hidden = true;
      panel.setAttribute('data-trip-proposal-state', 'ready');
    }, total);
    return total;
  }

  function handleDocumentKeydown(event) {
    if (event.key !== 'Escape' || !open.panel || open.panel.hidden) return;
    // Capture phase: this runs before the globe arrival card's own Escape
    // listener, and preventDefault tells that card to stay put so focus can
    // return to the trigger inside it.
    if (typeof event.preventDefault === 'function') event.preventDefault();
    closePanel();
  }

  function openPanel(panel, trigger) {
    if (!panel || !panel.hidden) return false;
    if (open.panel && open.panel !== panel) closePanel({ restoreFocus: false });

    open.panel = panel;
    open.trigger = trigger || null;
    if (!panel.getAttribute('data-trip-proposal-server-travelers')) {
      panel.setAttribute('data-trip-proposal-server-travelers', panel.getAttribute('data-trip-proposal-travelers') || '');
    }
    panel.hidden = false;
    if (trigger && typeof trigger.setAttribute === 'function') trigger.setAttribute('aria-expanded', 'true');

    // Device intent memory can pre-fill the party size, once, and only while
    // the traveler has not touched the stepper personally.
    if (panel.getAttribute('data-trip-proposal-touched') !== 'true') {
      var preset = intentTravelers();
      if (preset && preset !== currentTravelers(panel)) applyTravelers(panel, preset);
    }

    runFill(panel);
    document.addEventListener('keydown', handleDocumentKeydown, true);
    if (typeof panel.focus === 'function') panel.focus();
    armIdleBeacon(panel);
    return true;
  }

  function closePanel(options) {
    var restoreFocus = !options || options.restoreFocus !== false;
    var panel = open.panel;
    var trigger = open.trigger;
    if (!panel) return false;

    clearIdleBeacon();
    document.removeEventListener('keydown', handleDocumentKeydown, true);
    panel.hidden = true;
    open.panel = null;
    open.trigger = null;
    if (trigger && typeof trigger.setAttribute === 'function') trigger.setAttribute('aria-expanded', 'false');
    if (restoreFocus && trigger && !trigger.hidden && typeof trigger.focus === 'function') trigger.focus();
    return true;
  }

  function initPanel(panel) {
    if (panel.getAttribute('data-trip-proposal-ready') === 'true') return;
    panel.setAttribute('data-trip-proposal-ready', 'true');
    // Seal the server-rendered party size before anything can edit it: the
    // WhatsApp line substitution always replaces the line the server built,
    // never a line a previous edit produced.
    if (!panel.getAttribute('data-trip-proposal-server-travelers')) {
      panel.setAttribute('data-trip-proposal-server-travelers', panel.getAttribute('data-trip-proposal-travelers') || '');
    }

    panel.addEventListener('pointerdown', noteInteraction);
    panel.addEventListener('keydown', noteInteraction);

    var close = panel.querySelector('[data-trip-proposal-close]');
    if (close) {
      close.addEventListener('click', function () {
        closePanel();
      });
    }

    var steps = panel.querySelectorAll('[data-trip-proposal-step]');
    for (var index = 0; index < steps.length; index += 1) {
      (function (button) {
        button.addEventListener('click', function () {
          var delta = Number(button.getAttribute('data-trip-proposal-step'));
          if (!Number.isInteger(delta) || delta === 0) return;
          panel.setAttribute('data-trip-proposal-touched', 'true');
          applyTravelers(panel, currentTravelers(panel) + delta);
        });
      }(steps[index]));
    }

    var choices = panel.querySelectorAll('[data-trip-proposal-tier-choice]');
    for (index = 0; index < choices.length; index += 1) {
      (function (button) {
        button.addEventListener('click', function () {
          chooseTier(panel, String(button.getAttribute('data-trip-proposal-tier-choice') || ''));
        });
      }(choices[index]));
    }

    var toggles = panel.querySelectorAll('[data-trip-proposal-addon-toggle]');
    for (index = 0; index < toggles.length; index += 1) {
      (function (toggle) {
        toggle.addEventListener('click', function () {
          toggleAddon(panel, toggle);
        });
      }(toggles[index]));
    }

    applyTravelers(panel, currentTravelers(panel));
  }

  function initTriggers() {
    var triggers = document.querySelectorAll('[data-trip-proposal-trigger]');
    for (var index = 0; index < triggers.length; index += 1) {
      (function (trigger) {
        var panel = document.getElementById(String(trigger.getAttribute('aria-controls') || ''));
        if (!panel) return;
        trigger.hidden = false;
        trigger.addEventListener('click', function () {
          if (panel.hidden) openPanel(panel, trigger);
          else closePanel();
        });
      }(triggers[index]));
    }
  }

  // The globe arrival card is built by globe-3d.js before this bubbling
  // listener runs (the same ordering contract next-action.js already relies
  // on). When the selected destination has a server-rendered proposal panel
  // on this page, the card gains the proposal trigger next to its existing
  // compact booking step; when it does not, the card is left untouched.
  function ensureArrivalCardTrigger(card, panel) {
    var existing = card.querySelector('[data-trip-proposal-card-trigger]');
    if (existing) {
      existing.hidden = false;
      return existing;
    }
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'globe-arrival-card-proposal';
    button.setAttribute('data-trip-proposal-card-trigger', 'true');
    button.setAttribute('aria-expanded', 'false');
    var links = card.querySelector('.globe-arrival-card-links');
    if (links && typeof card.insertBefore === 'function') card.insertBefore(button, links);
    else card.append(button);
    return button;
  }

  function handleGlobeSelection(event) {
    var globe = event.target;
    var detail = event.detail || {};
    if (!globe || typeof globe.querySelector !== 'function') return;
    if (detail.selectionKind !== 'destination' || !detail.nearestDestination) return;
    var card = globe.querySelector('[data-globe-arrival-card]');
    if (!card || card.hidden) return;

    var panel = null;
    var panels = document.querySelectorAll('[data-trip-proposal]');
    for (var index = 0; index < panels.length; index += 1) {
      if (panels[index].getAttribute('data-trip-proposal') === String(detail.nearestDestination)) panel = panels[index];
    }
    if (!panel) {
      var stale = card.querySelector('[data-trip-proposal-card-trigger]');
      if (stale) stale.hidden = true;
      return;
    }

    var trigger = ensureArrivalCardTrigger(card, panel);
    trigger.textContent = String(panel.getAttribute('data-trip-proposal-trigger-label') || '');
    trigger.setAttribute('aria-controls', panel.id || '');
    trigger.onclick = function () {
      if (panel.hidden) openPanel(panel, trigger);
      else closePanel();
    };
  }

  function init() {
    var panels = document.querySelectorAll('[data-trip-proposal]');
    for (var index = 0; index < panels.length; index += 1) initPanel(panels[index]);
    initTriggers();
    document.addEventListener('travelglobe:select', handleGlobeSelection);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

  window.traVelTripProposal = {
    groupAmount: groupAmount,
    boundedTravelers: boundedTravelers,
    partyLabel: partyLabel,
    intentTravelers: intentTravelers,
    applyTravelers: applyTravelers,
    chooseTier: chooseTier,
    toggleAddon: toggleAddon,
    whatsappHref: whatsappHref,
    openPanel: openPanel,
    closePanel: closePanel,
    initPanel: initPanel,
    init: init,
    current: function () {
      return open.panel;
    },
    timings: { step: FILL_STEP_MS, hold: FILL_HOLD_MS, idle: IDLE_BEACON_MS }
  };
})();
