/**
 * Trip proposal behaviour (theme 1.40.0, opening sequence 1.41.0, takeover
 * stage 1.42.0).
 *
 * The panel a visitor opens was finished on the server. This file adds
 * behaviour on top of that finished markup, and nothing else. Since 1.42.0
 * the open panel no longer sits inside the globe frame: on open the same
 * server-rendered panel node is lifted into one full-screen takeover layer
 * over a dimmed page, and on close it returns to the exact spot it came
 * from. Nothing is duplicated, the panel is one node with one set of
 * listeners wherever it stands.
 *
 * Inside the takeover the opening sequence stages the server-authored
 * sentences as work steps: each line types letter by letter behind a small
 * spinning ring that snaps to a check when the line lands, the real fields
 * pulse awake as they are mentioned, the verdict closes the run, and then
 * the real tier cards land one by one. Any tap skips to the finished state,
 * reduced motion never sees a frame, and the 1.40.0 staging reveal remains
 * the fallback for a panel without the typed lines. The traveler stepper
 * multiplies the fares the server printed, the tier switch moves between
 * the records the server rendered, add-on toggles never touch a price, and
 * the WhatsApp link refresh only ever swaps whole message lines the server
 * authored into data attributes.
 *
 * There is no request here, no navigation, no form, and no scroll, wheel or
 * touchmove listener; the open takeover locks page scroll with one class on
 * the document element and releases it on close. The only exits are the two
 * plain anchors the server rendered: the record's tracked supplier link and
 * the owned WhatsApp concierge. Nothing is ever submitted or charged from
 * this file. The one sound this file can make is a short synthesized click,
 * generated in code at the moment of a tap, never from a file and never on
 * its own; reduced motion silences it and the one-tap toggle in the
 * takeover remembers the choice on the device.
 */
(function () {
  'use strict';

  var FILL_STEP_MS = 520;
  var FILL_HOLD_MS = 300;
  var IDLE_BEACON_MS = 6000;
  var FALLBACK_MIN = 1;
  var FALLBACK_MAX = 6;
  // Opening live-check tuning (theme 1.41.0, staged rings 1.42.0). Letters
  // land at the dictated ~30ms cadence and every completed step holds for a
  // beat so its ring snap can be seen. When the armed add-on verdicts make
  // the script long, first the cadence and then the step holds compress so
  // the whole run always ends inside the budget, and one skip tap always
  // ends it immediately.
  var CHECK_CHAR_MS = 30;
  var CHECK_MIN_CHAR_MS = 12;
  var CHECK_SNAP_MS = 260;
  var CHECK_MIN_SNAP_MS = 110;
  var CHECK_FINALE_MS = 220;
  var CHECK_TOTAL_BUDGET_MS = 3600;
  // The pin acknowledgment: a tapped pin pulses for this long before the
  // takeover opens over the planet.
  var PIN_PULSE_MS = 300;
  // The synthesized tap click: total length ~32ms, gain never above 0.06.
  var SOUND_STORAGE_KEY = 'traVelSoundMuted';
  var SOUND_TOGGLE_LABEL = 'צלילי ממשק';
  var TAKEOVER_BACK_LABEL = 'חזרה';
  // The device intent memory stores a party style, not a number. This is the
  // same mapping app.js already uses when it writes those styles from real
  // adult and child counts, read in the opposite direction.
  var INTENT_PARTY_TRAVELERS = { couple: 2, couple_2: 4, family: 5, friends: 3 };

  var open = { panel: null, trigger: null, idleTimer: 0 };
  var liveCheck = { panel: null, timers: [], natural: false };
  var takeover = { root: null, stage: null, dock: null, dockNext: null, open: false };
  var pulse = { timer: 0, pin: null };
  var sound = { context: null };

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

  // --- Tap sound (theme 1.42.0) --------------------------------------------
  // One short synthesized click, generated in code at the moment of a tap.
  // No audio file exists, nothing plays on its own: the only call sites are
  // the pin acknowledgment, the takeover opening, and the unmute toggle
  // itself, all of which sit inside a user gesture. Reduced motion silences
  // it entirely and the toggle's choice persists on the device.
  function soundMuted() {
    try {
      return window.localStorage.getItem(SOUND_STORAGE_KEY) === 'true';
    } catch (error) {
      return false;
    }
  }

  function setSoundMuted(muted) {
    try {
      window.localStorage.setItem(SOUND_STORAGE_KEY, muted ? 'true' : 'false');
    } catch (error) {
      // Storage unavailable: the toggle still works for this page view.
    }
    syncSoundToggle();
    return muted === true;
  }

  function tapSound(kind) {
    if (prefersReducedMotion() || soundMuted()) return false;
    var Context = window.AudioContext || window.webkitAudioContext;
    if (!Context) return false;
    try {
      if (!sound.context) sound.context = new Context();
      var context = sound.context;
      if (context.state === 'suspended' && typeof context.resume === 'function') context.resume();
      var now = context.currentTime;
      var oscillator = context.createOscillator();
      var gain = context.createGain();
      oscillator.type = 'sine';
      // Two-note signature: the pin answers bright and quick, the takeover
      // opens a fifth lower and a touch rounder. Both stay near 30ms.
      var frequency = kind === 'open' ? 830 : 1245;
      oscillator.frequency.setValueAtTime(frequency, now);
      oscillator.frequency.exponentialRampToValueAtTime(frequency * 0.6, now + 0.03);
      gain.gain.setValueAtTime(0.0001, now);
      gain.gain.exponentialRampToValueAtTime(0.055, now + 0.006);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.032);
      oscillator.connect(gain);
      gain.connect(context.destination);
      oscillator.start(now);
      oscillator.stop(now + 0.04);
      return true;
    } catch (error) {
      return false;
    }
  }

  function syncSoundToggle() {
    var toggle = takeover.root ? takeover.root.querySelector('[data-trip-takeover-sound]') : null;
    if (!toggle) return;
    var muted = soundMuted();
    toggle.setAttribute('aria-pressed', muted ? 'false' : 'true');
    toggle.classList.toggle('is-muted', muted);
  }

  // --- Takeover stage (theme 1.42.0) ---------------------------------------
  // One fixed full-screen layer, built once and appended to the page body.
  // Opening a proposal lifts the server-rendered panel node into this stage
  // and closing puts the same node back where it stood, so every listener,
  // every edit, and every law travels with it. Page scroll is locked with
  // one overflow class on the document element, never with a wheel or
  // touchmove listener, and focus cannot leave the stage while it is open.
  function documentRootElement() {
    return document.documentElement || null;
  }

  function lockPageScroll() {
    var rootElement = documentRootElement();
    if (rootElement && rootElement.classList) rootElement.classList.add('is-trip-takeover-open');
  }

  function unlockPageScroll() {
    var rootElement = documentRootElement();
    if (rootElement && rootElement.classList) rootElement.classList.remove('is-trip-takeover-open');
  }

  // Focusable collection that walks children directly, so keyboard trapping
  // behaves identically in the browser and in the contract harness. A hidden
  // subtree is skipped whole.
  function collectFocusables(node, out) {
    if (!node || node.hidden) return out || [];
    var list = out || [];
    var tag = String(node.tagName || '').toUpperCase();
    if (tag === 'BUTTON' && node.disabled !== true) list.push(node);
    else if (tag === 'A' && node.getAttribute && node.getAttribute('href')) list.push(node);
    else if (node.getAttribute && node.getAttribute('tabindex') !== null && Number(node.getAttribute('tabindex')) >= 0) list.push(node);
    var children = node.children || [];
    for (var index = 0; index < children.length; index += 1) collectFocusables(children[index], list);
    return list;
  }

  function handleTakeoverKeydown(event) {
    if (event.key !== 'Tab' || !takeover.open || !takeover.root) return;
    var focusables = collectFocusables(takeover.root, []);
    if (!focusables.length) return;
    var first = focusables[0];
    var last = focusables[focusables.length - 1];
    var active = document.activeElement;
    if (event.shiftKey && (active === first || active === takeover.root || (open.panel && active === open.panel))) {
      if (typeof event.preventDefault === 'function') event.preventDefault();
      if (typeof last.focus === 'function') last.focus();
    } else if (!event.shiftKey && active === last) {
      if (typeof event.preventDefault === 'function') event.preventDefault();
      if (typeof first.focus === 'function') first.focus();
    }
  }

  function ensureTakeover() {
    if (takeover.root) return takeover.root;
    if (!document.body || typeof document.body.append !== 'function') return null;

    var root = document.createElement('div');
    root.className = 'trip-takeover';
    root.setAttribute('data-trip-takeover', 'true');
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.hidden = true;

    var backdrop = document.createElement('div');
    backdrop.className = 'trip-takeover-backdrop';
    backdrop.setAttribute('data-trip-takeover-backdrop', 'true');
    backdrop.addEventListener('click', function () {
      closePanel();
    });

    var stage = document.createElement('div');
    stage.className = 'trip-takeover-stage';
    stage.setAttribute('data-trip-takeover-stage', 'true');

    var chrome = document.createElement('div');
    chrome.className = 'trip-takeover-chrome';

    var back = document.createElement('button');
    back.type = 'button';
    back.className = 'trip-takeover-back';
    back.setAttribute('data-trip-takeover-back', 'true');
    var backArrow = document.createElement('span');
    backArrow.className = 'trip-takeover-back-arrow';
    backArrow.setAttribute('aria-hidden', 'true');
    var backText = document.createElement('span');
    backText.textContent = TAKEOVER_BACK_LABEL;
    back.append(backArrow, backText);
    back.addEventListener('click', function () {
      closePanel();
    });

    var mute = document.createElement('button');
    mute.type = 'button';
    mute.className = 'trip-takeover-sound';
    mute.setAttribute('data-trip-takeover-sound', 'true');
    mute.setAttribute('aria-label', SOUND_TOGGLE_LABEL);
    var muteIcon = document.createElement('span');
    muteIcon.className = 'trip-takeover-sound-icon';
    muteIcon.setAttribute('aria-hidden', 'true');
    mute.append(muteIcon);
    mute.addEventListener('click', function () {
      var next = !soundMuted();
      setSoundMuted(next);
      if (!next) tapSound('tap');
    });

    chrome.append(back, mute);
    stage.append(chrome);
    root.append(backdrop, stage);
    root.addEventListener('keydown', handleTakeoverKeydown);
    document.body.append(root);
    takeover.root = root;
    takeover.stage = stage;
    syncSoundToggle();
    return root;
  }

  function stageTakeover(panel) {
    var root = ensureTakeover();
    if (!root) return false;
    takeover.dock = panel.parentNode || null;
    takeover.dockNext = panel.nextSibling || null;
    if (takeover.dock && typeof takeover.dock.removeChild === 'function') takeover.dock.removeChild(panel);
    takeover.stage.append(panel);
    var labelledBy = String(panel.getAttribute('aria-labelledby') || '');
    if (labelledBy) root.setAttribute('aria-labelledby', labelledBy);
    root.hidden = false;
    root.classList.add('is-open');
    takeover.open = true;
    lockPageScroll();
    syncSoundToggle();
    return true;
  }

  function releaseTakeover(panel) {
    if (!takeover.open) return false;
    takeover.open = false;
    unlockPageScroll();
    if (takeover.root) {
      takeover.root.classList.remove('is-open');
      takeover.root.hidden = true;
    }
    if (panel && takeover.stage && typeof takeover.stage.removeChild === 'function' && panel.parentNode === takeover.stage) {
      takeover.stage.removeChild(panel);
    }
    if (panel && takeover.dock && typeof takeover.dock.insertBefore === 'function') {
      var anchor = takeover.dockNext && takeover.dockNext.parentNode === takeover.dock ? takeover.dockNext : null;
      takeover.dock.insertBefore(panel, anchor);
    }
    takeover.dock = null;
    takeover.dockNext = null;
    return true;
  }

  // --- Opening live-check sequence (theme 1.41.0, staged rings 1.42.0) -----
  // The finished, server-rendered proposal is already underneath. On open the
  // panel narrates its own assembly as a run of work steps: server-authored
  // sentences typed letter by letter, one spinning ring per step snapping to
  // a check as its line lands, the real fields pulsing awake as each one is
  // mentioned, the verdict, and then the finished tier cards landing one by
  // one. Every byte that can appear came out of inc/proposal.php data
  // attributes; the only substitution is the traveler count into the verdict
  // template, exactly the way the pinned total template already substitutes
  // its amount. An add-on step types only when the row carries both the
  // server-authored sentence and the real supplier link, so nothing is ever
  // claimed that cannot be linked. Any tap or key press cuts straight to the
  // finished panel, reduced motion never sees a single frame, and every
  // timer dies with the sequence.
  function liveCheckDelay(callback, milliseconds) {
    liveCheck.timers.push(window.setTimeout(callback, milliseconds));
  }

  function stopLiveCheckTimers() {
    for (var index = 0; index < liveCheck.timers.length; index += 1) window.clearTimeout(liveCheck.timers[index]);
    liveCheck.timers = [];
  }

  function liveCheckVerdict(panel) {
    if (currentTravelers(panel) === 1) return String(panel.getAttribute('data-trip-proposal-verdict-one') || '');
    var template = String(panel.getAttribute('data-trip-proposal-verdict-many') || '');
    return template.indexOf('%s') >= 0 ? template.replace('%s', groupAmount(currentTravelers(panel))) : '';
  }

  function liveCheckAddonVerdicts(panel) {
    var rows = panel.querySelectorAll('[data-trip-proposal-addon]');
    var verdicts = [];
    for (var index = 0; index < rows.length; index += 1) {
      var text = String(rows[index].getAttribute('data-trip-proposal-addon-verdict') || '');
      if (text && rows[index].querySelector('[data-trip-proposal-addon-link]')) verdicts.push({ text: text, row: rows[index] });
    }
    return verdicts;
  }

  function liveCheckScript(panel) {
    var checkPrices = String(panel.getAttribute('data-trip-proposal-check-prices-line') || '');
    var checkDates = String(panel.getAttribute('data-trip-proposal-check-dates-line') || '');
    var verdict = liveCheckVerdict(panel);
    if (!checkPrices || !checkDates || !verdict) return null;
    return {
      checkPrices: checkPrices,
      checkDates: checkDates,
      // The found-flight step (theme 1.42.0) is optional: a panel without
      // the server sentence simply runs without that step.
      flightFound: String(panel.getAttribute('data-trip-proposal-flight-found-line') || ''),
      verdict: verdict,
      addons: liveCheckAddonVerdicts(panel)
    };
  }

  // The full ordered run: two checks, the found record, one achievement per
  // armed add-on, and the verdict last. Both the animated run and the
  // finished transcript come from this one list, so a skip can never land on
  // different lines than the run itself would have written.
  function liveCheckStepList(panel, script) {
    var section = currentTierSection(panel);
    var steps = [
      {
        text: script.checkPrices,
        kind: 'check',
        targets: [panel.querySelector('[data-trip-proposal-party-field]'), section]
      },
      {
        text: script.checkDates,
        kind: 'check',
        targets: [section ? section.querySelector('[data-trip-proposal-tier-dates]') : null]
      }
    ];
    if (script.flightFound) {
      steps.push({
        text: script.flightFound,
        kind: 'check',
        targets: [panel.querySelector('[data-trip-proposal-total]')]
      });
    }
    for (var index = 0; index < script.addons.length; index += 1) {
      steps.push({
        text: script.addons[index].text,
        kind: 'verdict',
        targets: [panel.querySelector('[data-trip-proposal-addons-field]'), script.addons[index].row]
      });
    }
    steps.push({ text: script.verdict, kind: 'verdict', targets: [] });
    return steps;
  }

  // The run must always end inside the budget. The cadence compresses first,
  // down to the readable floor, and only then the per-step holds shrink, so
  // even the longest honest script lands in time with its rhythm intact.
  function liveCheckPlan(script) {
    var totalChars = script.checkPrices.length + script.checkDates.length + script.verdict.length + script.flightFound.length;
    for (var index = 0; index < script.addons.length; index += 1) totalChars += script.addons[index].text.length;
    totalChars = Math.max(1, totalChars);
    var stepCount = 3 + (script.flightFound ? 1 : 0) + script.addons.length;
    var snapCount = Math.max(1, stepCount - 1);
    var charMs = CHECK_CHAR_MS;
    var snapMs = CHECK_SNAP_MS;
    if (totalChars * charMs + snapCount * snapMs + CHECK_FINALE_MS > CHECK_TOTAL_BUDGET_MS) {
      var textBudget = CHECK_TOTAL_BUDGET_MS - snapCount * snapMs - CHECK_FINALE_MS;
      charMs = Math.max(CHECK_MIN_CHAR_MS, Math.min(CHECK_CHAR_MS, Math.floor(textBudget / totalChars)));
      if (totalChars * charMs + snapCount * snapMs + CHECK_FINALE_MS > CHECK_TOTAL_BUDGET_MS) {
        snapMs = Math.max(CHECK_MIN_SNAP_MS, Math.floor((CHECK_TOTAL_BUDGET_MS - CHECK_FINALE_MS - totalChars * charMs) / snapCount));
      }
    }
    return { charMs: charMs, snapMs: snapMs, steps: stepCount, totalChars: totalChars };
  }

  function liveCheckCharMs(script) {
    return liveCheckPlan(script).charMs;
  }

  function ensureLiveCheckScreen(panel) {
    var screen = panel.querySelector('[data-trip-proposal-screen]');
    if (!screen) {
      screen = document.createElement('div');
      screen.className = 'trip-proposal-screen';
      screen.setAttribute('data-trip-proposal-screen', 'true');
      screen.setAttribute('aria-hidden', 'true');
      var body = panel.querySelector('[data-trip-proposal-body]');
      if (body && typeof panel.insertBefore === 'function') panel.insertBefore(screen, body);
      else panel.append(screen);
    }
    while (screen.firstChild) screen.removeChild(screen.firstChild);
    screen.hidden = false;
    return screen;
  }

  // One work step on the screen: the spinning ring and the line it narrates.
  // The ring is a sibling of the text so the typewriter can keep writing
  // plain textContent without ever touching it.
  function appendLiveCheckStep(screen, kind) {
    var row = document.createElement('div');
    row.className = 'trip-proposal-step-row';
    row.setAttribute('data-trip-proposal-step-row', kind);
    var ring = document.createElement('span');
    ring.className = 'trip-proposal-step-ring';
    ring.setAttribute('data-trip-proposal-step-ring', 'true');
    ring.setAttribute('aria-hidden', 'true');
    var line = document.createElement('p');
    line.className = 'trip-proposal-screen-line';
    line.setAttribute('data-trip-proposal-screen-line', kind);
    line.textContent = '';
    row.append(ring, line);
    screen.append(row);
    return { row: row, line: line };
  }

  function typeLiveCheckStep(screen, text, kind, charMs, done) {
    var step = appendLiveCheckStep(screen, kind);
    step.row.classList.add('is-spinning');
    step.line.classList.add('is-typing');
    var shown = 0;
    var advance = function () {
      shown += 1;
      step.line.textContent = text.slice(0, shown);
      if (shown >= text.length) {
        step.line.classList.remove('is-typing');
        step.row.classList.remove('is-spinning');
        step.row.classList.add('is-done');
        done();
        return;
      }
      liveCheckDelay(advance, charMs);
    };
    advance();
  }

  function armLiveCheckTarget(target) {
    if (target && target.classList) target.classList.add('is-live-armed');
  }

  function clearLiveCheckArms(panel) {
    var armed = panel.querySelectorAll('.is-live-armed');
    for (var index = 0; index < armed.length; index += 1) armed[index].classList.remove('is-live-armed');
  }

  // Cut to the finished state: the whole step transcript written with every
  // ring already snapped, the panel ready. This is the one landing zone
  // shared by natural completion, the skip tap, Escape, and closing the
  // panel mid-sequence. Natural completion earns the staged tier-card
  // landing; every other road in lands instantly.
  function finishLiveCheck() {
    var panel = liveCheck.panel;
    if (!panel) return false;
    var natural = liveCheck.natural === true;
    stopLiveCheckTimers();
    liveCheck.panel = null;
    liveCheck.natural = false;
    var script = liveCheckScript(panel);
    if (script) {
      var screen = ensureLiveCheckScreen(panel);
      var steps = liveCheckStepList(panel, script);
      for (var index = 0; index < steps.length; index += 1) {
        var built = appendLiveCheckStep(screen, steps[index].kind);
        built.line.textContent = steps[index].text;
        built.row.classList.add('is-done');
      }
    }
    panel.setAttribute('data-trip-proposal-landing', natural && !prefersReducedMotion() ? 'staged' : 'instant');
    panel.setAttribute('data-trip-proposal-state', 'ready');
    return true;
  }

  function runLiveCheck(panel) {
    var script = liveCheckScript(panel);
    if (!script) return runFill(panel);

    var fill = panel.querySelector('[data-trip-proposal-fill]');
    if (fill) fill.hidden = true;
    if (prefersReducedMotion()) {
      liveCheck.panel = panel;
      liveCheck.natural = false;
      finishLiveCheck();
      return 0;
    }

    finishLiveCheck();
    clearLiveCheckArms(panel);
    panel.removeAttribute('data-trip-proposal-landing');
    liveCheck.panel = panel;
    liveCheck.natural = false;
    panel.setAttribute('data-trip-proposal-state', 'checking');
    var screen = ensureLiveCheckScreen(panel);
    var steps = liveCheckStepList(panel, script);
    var plan = liveCheckPlan(script);
    var position = 0;

    var finale = function () {
      armLiveCheckTarget(panel.querySelector('[data-trip-proposal-tier-picker]'));
      armLiveCheckTarget(panel.querySelector('[data-trip-proposal-total]'));
      armLiveCheckTarget(panel.querySelector('[data-trip-proposal-exits]'));
      liveCheck.natural = true;
      liveCheckDelay(finishLiveCheck, CHECK_FINALE_MS);
    };

    var runStep = function () {
      var step = steps[position];
      position += 1;
      typeLiveCheckStep(screen, step.text, step.kind, plan.charMs, function () {
        for (var index = 0; index < step.targets.length; index += 1) armLiveCheckTarget(step.targets[index]);
        if (position >= steps.length) finale();
        else liveCheckDelay(runStep, plan.snapMs);
      });
    };

    runStep();
    return CHECK_TOTAL_BUDGET_MS;
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
    // Any tap or key press on the panel cuts the opening sequence straight
    // to the finished proposal; it never has to be watched twice.
    finishLiveCheck();
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
    cancelPinPulse();

    open.panel = panel;
    open.trigger = trigger || null;
    if (!panel.getAttribute('data-trip-proposal-server-travelers')) {
      panel.setAttribute('data-trip-proposal-server-travelers', panel.getAttribute('data-trip-proposal-travelers') || '');
    }
    // The takeover: the same panel node is lifted out of its dock into the
    // full-screen stage. When the page body is not available (a stripped
    // environment) the panel simply opens where it stands, exactly as it did
    // before the stage existed.
    tapSound('open');
    stageTakeover(panel);
    panel.hidden = false;
    if (trigger && typeof trigger.setAttribute === 'function') trigger.setAttribute('aria-expanded', 'true');

    // Device intent memory can pre-fill the party size, once, and only while
    // the traveler has not touched the stepper personally.
    if (panel.getAttribute('data-trip-proposal-touched') !== 'true') {
      var preset = intentTravelers();
      if (preset && preset !== currentTravelers(panel)) applyTravelers(panel, preset);
    }

    runLiveCheck(panel);
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
    if (liveCheck.panel === panel) finishLiveCheck();
    document.removeEventListener('keydown', handleDocumentKeydown, true);
    panel.hidden = true;
    releaseTakeover(panel);
    open.panel = null;
    open.trigger = null;
    if (trigger && typeof trigger.setAttribute === 'function') trigger.setAttribute('aria-expanded', 'false');
    if (restoreFocus && trigger && !trigger.hidden && typeof trigger.focus === 'function') trigger.focus();
    return true;
  }

  // The pin acknowledgment (theme 1.42.0): a tapped pin pulses for ~300ms,
  // then the takeover opens with the pin itself as the return-focus trigger,
  // so Escape lands the keyboard exactly where the journey began. Reduced
  // motion opens instantly with no pulse and no sound.
  function cancelPinPulse() {
    if (pulse.timer) {
      window.clearTimeout(pulse.timer);
      pulse.timer = 0;
    }
    if (pulse.pin && pulse.pin.classList) pulse.pin.classList.remove('is-proposal-pulse');
    pulse.pin = null;
  }

  function openPanelFromPin(panel, pin) {
    if (!panel || (open.panel === panel && !panel.hidden)) return false;
    cancelPinPulse();
    tapSound('tap');
    if (prefersReducedMotion() || !pin || !pin.classList) return openPanel(panel, pin || null);
    pulse.pin = pin;
    pin.classList.add('is-proposal-pulse');
    pulse.timer = window.setTimeout(function () {
      pulse.timer = 0;
      if (pulse.pin && pulse.pin.classList) pulse.pin.classList.remove('is-proposal-pulse');
      pulse.pin = null;
      openPanel(panel, pin);
    }, PIN_PULSE_MS);
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
  // on this page, the card slims to its essentials, destination, price, the
  // start button and the map link, and a direct pin activation opens the
  // takeover itself after the acknowledgment pulse. When no panel exists the
  // card is left exactly as it was.
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

  function panelStartLabel(panel) {
    return String(panel.getAttribute('data-trip-proposal-start-label') || panel.getAttribute('data-trip-proposal-trigger-label') || '');
  }

  function findDestinationPin(globe, destination) {
    var id = String(destination || '');
    if (!/^[a-z0-9_-]+$/i.test(id) || typeof globe.querySelector !== 'function') return null;
    return globe.querySelector('.price-pin[data-destination="' + id + '"]') || globe.querySelector('[data-destination="' + id + '"]');
  }

  function handleGlobeSelection(event) {
    var globe = event.target;
    var detail = event.detail || {};
    if (!globe || typeof globe.querySelector !== 'function') return;
    if (detail.selectionKind !== 'destination' || !detail.nearestDestination) return;
    var card = globe.querySelector('[data-globe-arrival-card]');

    var panel = null;
    var panels = document.querySelectorAll('[data-trip-proposal]');
    for (var index = 0; index < panels.length; index += 1) {
      if (panels[index].getAttribute('data-trip-proposal') === String(detail.nearestDestination)) panel = panels[index];
    }
    if (!panel) {
      if (card) {
        card.removeAttribute('data-trip-proposal-card-mode');
        var stale = card.querySelector('[data-trip-proposal-card-trigger]');
        if (stale) stale.hidden = true;
      }
      return;
    }

    if (card && !card.hidden) {
      card.setAttribute('data-trip-proposal-card-mode', 'takeover');
      var trigger = ensureArrivalCardTrigger(card, panel);
      trigger.textContent = panelStartLabel(panel);
      trigger.setAttribute('aria-controls', panel.id || '');
      trigger.onclick = function () {
        if (panel.hidden) openPanel(panel, trigger);
        else closePanel();
      };
    }

    // The direct pin activation is the moment the owner of the tap expects
    // the answer, so the takeover opens from it: acknowledgment pulse on the
    // pin, then the full-screen stage, with the pin as the focus home.
    if (detail.pinActivated === true) {
      var pin = findDestinationPin(globe, detail.nearestDestination);
      openPanelFromPin(panel, pin);
    }
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
    openPanelFromPin: openPanelFromPin,
    closePanel: closePanel,
    initPanel: initPanel,
    init: init,
    current: function () {
      return open.panel;
    },
    liveCheckScript: liveCheckScript,
    liveCheckVerdict: liveCheckVerdict,
    liveCheckAddonVerdicts: liveCheckAddonVerdicts,
    liveCheckStepList: liveCheckStepList,
    liveCheckPlan: liveCheckPlan,
    liveCheckCharMs: liveCheckCharMs,
    runLiveCheck: runLiveCheck,
    finishLiveCheck: finishLiveCheck,
    checking: function () {
      return liveCheck.panel;
    },
    takeoverRoot: function () {
      return takeover.root;
    },
    takeoverOpen: function () {
      return takeover.open;
    },
    tapSound: tapSound,
    soundMuted: soundMuted,
    setSoundMuted: setSoundMuted,
    timings: {
      step: FILL_STEP_MS,
      hold: FILL_HOLD_MS,
      idle: IDLE_BEACON_MS,
      charMs: CHECK_CHAR_MS,
      minCharMs: CHECK_MIN_CHAR_MS,
      snap: CHECK_SNAP_MS,
      minSnap: CHECK_MIN_SNAP_MS,
      finale: CHECK_FINALE_MS,
      budget: CHECK_TOTAL_BUDGET_MS,
      pinPulse: PIN_PULSE_MS
    }
  };
})();
