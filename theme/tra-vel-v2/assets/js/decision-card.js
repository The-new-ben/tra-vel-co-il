/**
 * Decision card behaviour (theme 1.35.0).
 *
 * Two enhancements over a card the server already finished: a short opening
 * sequence that shows the details being completed, and a traveler stepper that
 * recomputes every total by multiplying the per traveler fare already printed
 * on the page.
 *
 * There is no request here, no navigation, and no scroll, wheel or touchmove
 * listener. The only thing that leaves the page is the outbound supplier link
 * on each option, which is a plain anchor the browser owns.
 */
(function () {
  'use strict';

  var FILL_STEP_MS = 420;
  var FILL_HOLD_MS = 260;
  var COUNT_STEP_MS = 90;
  var FALLBACK_MIN = 1;
  var FALLBACK_MAX = 6;

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

  function cardBounds(card) {
    return {
      minimum: boundedTravelers(card.getAttribute('data-decision-card-min'), FALLBACK_MIN, FALLBACK_MAX),
      maximum: boundedTravelers(card.getAttribute('data-decision-card-max'), FALLBACK_MIN, FALLBACK_MAX)
    };
  }

  function partyLabel(card, travelers) {
    if (travelers === 1) return String(card.getAttribute('data-decision-card-party-one') || '');
    return String(card.getAttribute('data-decision-card-party-many') || '').replace('%s', groupAmount(travelers));
  }

  function applyTravelers(card, travelers) {
    var bounds = cardBounds(card);
    var count = boundedTravelers(travelers, bounds.minimum, bounds.maximum);
    var party = partyLabel(card, count);
    var totals = card.querySelectorAll('[data-decision-tier-total]');
    var spoken = [];
    var index;

    for (index = 0; index < totals.length; index += 1) {
      var node = totals[index];
      var unit = Number(node.getAttribute('data-decision-tier-unit'));
      var symbol = String(node.getAttribute('data-decision-tier-symbol') || '');
      if (!Number.isInteger(unit) || unit <= 0 || !symbol) continue;
      var text = symbol + groupAmount(unit * count);
      node.textContent = text;
      var tier = typeof node.closest === 'function' ? node.closest('[data-decision-tier]') : null;
      var label = tier ? tier.querySelector('[data-decision-tier-label]') : null;
      spoken.push(label ? String(label.textContent || '').trim() + ' ' + text : text);
    }

    var parties = card.querySelectorAll('[data-decision-tier-party]');
    for (index = 0; index < parties.length; index += 1) parties[index].textContent = party;

    var value = card.querySelector('[data-decision-card-travelers-value]');
    if (value) value.textContent = groupAmount(count);
    card.setAttribute('data-decision-card-travelers', String(count));

    var live = card.querySelector('[data-decision-card-live]');
    if (live && spoken.length) live.textContent = party + ': ' + spoken.join(', ');

    return count;
  }

  function initStepper(card) {
    var steps = card.querySelectorAll('[data-decision-card-step]');
    if (!steps.length) return;
    var bounds = cardBounds(card);

    for (var index = 0; index < steps.length; index += 1) {
      (function (button) {
        button.addEventListener('click', function () {
          var delta = Number(button.getAttribute('data-decision-card-step'));
          if (!Number.isInteger(delta) || delta === 0) return;
          var current = boundedTravelers(card.getAttribute('data-decision-card-travelers'), bounds.minimum, bounds.maximum);
          var next = boundedTravelers(current + delta, bounds.minimum, bounds.maximum);
          if (next === current) return;
          applyTravelers(card, next);
          syncStepperState(card);
        });
      }(steps[index]));
    }

    syncStepperState(card);
  }

  function syncStepperState(card) {
    var bounds = cardBounds(card);
    var current = boundedTravelers(card.getAttribute('data-decision-card-travelers'), bounds.minimum, bounds.maximum);
    var steps = card.querySelectorAll('[data-decision-card-step]');
    for (var index = 0; index < steps.length; index += 1) {
      var delta = Number(steps[index].getAttribute('data-decision-card-step'));
      var blocked = (delta < 0 && current <= bounds.minimum) || (delta > 0 && current >= bounds.maximum);
      steps[index].disabled = blocked;
    }
  }

  function countUp(line) {
    var target = Number(line.getAttribute('data-decision-card-fill-count'));
    if (!Number.isInteger(target) || target <= 1) return;
    var finished = String(line.textContent || '');
    var token = String(target);
    var at = finished.indexOf(token);
    if (at < 0) return;

    var current = 0;
    var paint = function (value) {
      line.textContent = finished.slice(0, at) + String(value) + finished.slice(at + token.length);
    };
    var tick = function () {
      current += 1;
      paint(current);
      if (current < target) window.setTimeout(tick, COUNT_STEP_MS);
    };
    paint(0);
    window.setTimeout(tick, COUNT_STEP_MS);
  }

  function runFill(card) {
    var fill = card.querySelector('[data-decision-card-fill]');
    var lines = fill ? fill.querySelectorAll('[data-decision-card-fill-line]') : [];
    if (!fill || !lines.length) {
      card.setAttribute('data-decision-card-state', 'ready');
      return 0;
    }

    card.setAttribute('data-decision-card-state', 'filling');
    fill.hidden = false;

    for (var index = 0; index < lines.length; index += 1) {
      (function (line, position) {
        window.setTimeout(function () {
          line.setAttribute('data-decision-card-fill-shown', 'true');
          countUp(line);
        }, position * FILL_STEP_MS);
      }(lines[index], index));
    }

    var total = lines.length * FILL_STEP_MS + FILL_HOLD_MS;
    window.setTimeout(function () {
      fill.hidden = true;
      card.setAttribute('data-decision-card-state', 'ready');
    }, total);

    return total;
  }

  function prefersReducedMotion() {
    return Boolean(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function initCard(card) {
    initStepper(card);
    if (prefersReducedMotion()) {
      card.setAttribute('data-decision-card-state', 'ready');
      return;
    }
    runFill(card);
  }

  function init() {
    var cards = document.querySelectorAll('[data-decision-card]');
    for (var index = 0; index < cards.length; index += 1) initCard(cards[index]);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

  window.traVelDecisionCard = {
    groupAmount: groupAmount,
    boundedTravelers: boundedTravelers,
    partyLabel: partyLabel,
    applyTravelers: applyTravelers,
    syncStepperState: syncStepperState,
    prefersReducedMotion: prefersReducedMotion,
    runFill: runFill,
    initCard: initCard,
    init: init,
    timings: { step: FILL_STEP_MS, hold: FILL_HOLD_MS, count: COUNT_STEP_MS }
  };
})();
