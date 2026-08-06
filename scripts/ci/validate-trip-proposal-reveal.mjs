// Theme 1.41.0 behavioural harness: the dead pin tap repair and the
// proposal's opening live-check reveal.
//
// Part A drives the production app.js in a stub DOM and proves the P0
// repair: a destination-pin activation on the homepage arrival globe now
// publishes the same travelglobe:select event the free-tap and pillar paths
// always published, so the anchored card, its booking step, the proposal
// trigger, and the beacon can all render, while the already-run pin pipeline
// is never executed a second time by the shared selection listener.
//
// Part B drives the production trip-proposal.js with a simulated clock and
// proves the reveal's laws: only server-authored sentences are ever typed,
// an add-on verdict appears only when the row carries both the
// server-authored sentence and the real supplier link, the whole sequence
// ends inside the four second budget, one skip lands on the finished state,
// and reduced motion never sees a frame.
//
// Part C pins the source truths: gating on the server, pinned 1.40.0
// strings byte-identical, the scroll law, no requests, no navigation, no
// submissions, no commerce vocabulary, and no em or en dashes.
// Run with: node scripts/ci/validate-trip-proposal-reveal.mjs
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import vm from 'node:vm';

const repoRoot = resolve(import.meta.dirname, '..', '..');
const themeRoot = join(repoRoot, 'theme', 'tra-vel-v2');
const appSource = readFileSync(join(themeRoot, 'assets', 'js', 'app.js'), 'utf8');
const proposalJsSource = readFileSync(join(themeRoot, 'assets', 'js', 'trip-proposal.js'), 'utf8');
const globeSource = readFileSync(join(themeRoot, 'assets', 'js', 'globe-3d.js'), 'utf8');
const proposalPhpSource = readFileSync(join(themeRoot, 'inc', 'proposal.php'), 'utf8');
const panelTemplateSource = readFileSync(join(themeRoot, 'template-parts', 'proposal-panel.php'), 'utf8');
const pricesPhpSource = readFileSync(join(themeRoot, 'inc', 'prices.php'), 'utf8');

// --- Shared stub DOM -----------------------------------------------------
class StubClassList {
  constructor() { this.names = new Set(); }
  add(...names) { names.forEach(name => this.names.add(name)); }
  remove(...names) { names.forEach(name => this.names.delete(name)); }
  contains(name) { return this.names.has(name); }
  toggle(name, force) {
    const enabled = force === undefined ? !this.names.has(name) : Boolean(force);
    if (enabled) this.names.add(name); else this.names.delete(name);
    return enabled;
  }
}

class StubElement {
  constructor(tagName = 'div') {
    this.tagName = String(tagName).toUpperCase();
    this.attributes = new Map();
    this.dataset = {};
    this.children = [];
    this.parentNode = null;
    this.classList = new StubClassList();
    this.listeners = new Map();
    this.hidden = false;
    this.textContent = '';
    this.className = '';
  }
  get firstChild() { return this.children[0] || null; }
  setAttribute(name, value) {
    this.attributes.set(name, String(value));
    if (name.startsWith('data-')) {
      const key = name.slice(5).replace(/-([a-z])/g, (match, letter) => letter.toUpperCase());
      this.dataset[key] = String(value);
    }
  }
  getAttribute(name) { return this.attributes.get(name) ?? null; }
  removeAttribute(name) { this.attributes.delete(name); }
  append(...nodes) { nodes.forEach(node => { node.parentNode = this; this.children.push(node); }); }
  insertBefore(node, reference) {
    node.parentNode = this;
    const index = this.children.indexOf(reference);
    if (index < 0) this.children.push(node); else this.children.splice(index, 0, node);
    return node;
  }
  removeChild(node) {
    const index = this.children.indexOf(node);
    if (index >= 0) this.children.splice(index, 1);
    return node;
  }
  matchesSelector(selector) {
    return String(selector).split(',').some(part => {
      const trimmed = part.trim();
      const groups = trimmed.match(/\[[^\]]+\]/g);
      if (!groups || groups.join('') !== trimmed) return false;
      return groups.every(group => {
        const match = /^\[([a-z0-9-]+)(?:="([^"]*)")?\]$/i.exec(group);
        if (!match || !this.attributes.has(match[1])) return false;
        return match[2] === undefined || this.attributes.get(match[1]) === match[2];
      });
    });
  }
  descendants(out = []) {
    for (const child of this.children) {
      out.push(child);
      child.descendants?.(out);
    }
    return out;
  }
  matches(selector) { return this.matchesSelector(selector); }
  querySelector(selector) { return this.descendants().find(node => node.matchesSelector?.(selector)) || null; }
  querySelectorAll(selector) { return this.descendants().filter(node => node.matchesSelector?.(selector)); }
  closest(selector) {
    let node = this;
    while (node) {
      if (node.matchesSelector?.(selector)) return node;
      node = node.parentNode;
    }
    return null;
  }
  addEventListener(type, callback) {
    const handlers = this.listeners.get(type) || [];
    handlers.push(callback);
    this.listeners.set(type, handlers);
  }
  removeEventListener() {}
  dispatchEvent(event) {
    (this.listeners.get(event.type) || []).forEach(callback => callback(event));
    return true;
  }
  focus() { this.focused = true; }
}

// --- Part A: the P0 dead-tap repair in app.js ----------------------------
{
  let clock = 5000;
  const documentListeners = new Map();
  const documentStub = {
    readyState: 'complete',
    visibilityState: 'visible',
    documentElement: { dataset: { traVelV2Ready: 'true' } },
    addEventListener(type, callback) {
      const handlers = documentListeners.get(type) || [];
      handlers.push(callback);
      documentListeners.set(type, handlers);
    },
    dispatchEvent(event) {
      (documentListeners.get(event.type) || []).forEach(callback => callback(event));
      return true;
    },
    querySelector: () => null,
    querySelectorAll: () => [],
    getElementById: () => null,
    createElement: tagName => new StubElement(tagName),
    createTextNode: value => String(value)
  };
  const windowStub = {
    traVelV2: {},
    traVelGlobe3D: { zoom: () => true, focusPoint: () => true, focusDestination() {}, focusHub() {}, setDestinations() {}, setExplorationHubs() {}, clearSelection() {}, pulseRoute() {} },
    location: { origin: 'https://tra-vel.co.il', pathname: '/', search: '', hash: '', assign() {} },
    history: { pushState() {}, replaceState() {} },
    crypto: { randomUUID: () => '11111111-2222-4333-8444-555555555555' },
    matchMedia: () => ({ matches: false }),
    localStorage: { getItem: () => null, setItem() {}, removeItem() {}, clear() {} },
    addEventListener() {},
    setTimeout: () => 1,
    clearTimeout() {}
  };
  class StubEvent {
    constructor(type, options = {}) {
      this.type = type;
      this.bubbles = options.bubbles === true;
      this.detail = options.detail;
      this.defaultPrevented = false;
    }
    preventDefault() { this.defaultPrevented = true; }
    stopPropagation() { this.propagationStopped = true; }
    stopImmediatePropagation() { this.immediatePropagationStopped = true; }
  }
  const context = vm.createContext({
    AbortController,
    CSS: { escape: value => String(value) },
    URL,
    URLSearchParams,
    console,
    Event: StubEvent,
    CustomEvent: class CustomEvent extends StubEvent {},
    document: documentStub,
    navigator: {},
    performance: { now: () => clock },
    window: windowStub,
    setTimeout: windowStub.setTimeout,
    clearTimeout: windowStub.clearTimeout
  });
  new vm.Script(appSource, { filename: 'app.js' }).runInContext(context);

  const globeRoot = new StubElement('div');
  globeRoot.setAttribute('data-globe-3d', '');
  globeRoot.setAttribute('data-globe-arrival', 'true');
  globeRoot.setAttribute('data-home-globe', '');
  globeRoot.setAttribute('data-discovery-globe', '');
  const pin = new StubElement('button');
  pin.setAttribute('data-destination', 'budapest');
  pin.setAttribute('data-latitude', '47.4979');
  pin.setAttribute('data-longitude', '19.0402');
  pin.setAttribute('aria-label', 'BUD, טיסה הלוך ושוב מ-411 ₪');
  globeRoot.append(pin);

  const published = [];
  globeRoot.addEventListener('travelglobe:select', event => published.push(event.detail));

  context.harnessPin = pin;
  vm.runInContext('bindDestinationPin(harnessPin)', context);
  assert.equal(pin.dataset.selectionBound, 'true', 'The destination pin must bind exactly once.');

  // A pointer tap: pointerdown then the (possibly deferred, synthetic) click.
  pin.dispatchEvent(new StubEvent('pointerdown'));
  clock += 320; // the globe module replays the deferred tap ~300ms later
  pin.dispatchEvent(new StubEvent('click', { detail: 0 }));
  assert.equal(published.length, 1, 'A pin tap must publish exactly one selection event for the card surfaces.');
  assert.deepEqual({
    kind: published[0].selectionKind,
    action: published[0].planningAction,
    destination: published[0].nearestDestination,
    label: published[0].nearestLabel,
    supported: published[0].supported,
    inputType: published[0].inputType,
    pinActivated: published[0].pinActivated,
    latitude: published[0].latitude,
    longitude: published[0].longitude
  }, {
    kind: 'destination',
    action: 'open_destination',
    destination: 'budapest',
    label: 'BUD, טיסה הלוך ושוב מ-411 ₪',
    supported: true,
    inputType: 'pointer',
    pinActivated: true,
    latitude: 47.4979,
    longitude: 19.0402
  }, 'The published detail must mirror the free-tap destination selection shape.');

  // A keyboard activation long after any pointer contact reads as keyboard,
  // so the arrival card can move focus to itself.
  clock += 5000;
  pin.dispatchEvent(new StubEvent('click', { detail: 0 }));
  assert.equal(published.length, 2, 'A keyboard pin activation must also publish the selection.');
  assert.equal(published[1].inputType, 'keyboard', 'Without recent pointer contact the activation must read as keyboard.');

  // A real mouse click (detail 1) publishes as pointer even without the
  // deferred replay path.
  clock += 5000;
  pin.dispatchEvent(new StubEvent('click', { detail: 1 }));
  assert.equal(published[2].inputType, 'pointer', 'A native pointer click must read as pointer.');

  // A pin outside an arrival globe must stay silent: map-page pins keep
  // their synchronous contract with no card to feed.
  const plainGlobe = new StubElement('div');
  plainGlobe.setAttribute('data-globe-3d', '');
  plainGlobe.setAttribute('data-discovery-globe', '');
  const mapPin = new StubElement('button');
  mapPin.setAttribute('data-destination', 'athens');
  mapPin.setAttribute('data-latitude', '37.9838');
  mapPin.setAttribute('data-longitude', '23.7275');
  plainGlobe.append(mapPin);
  const mapPublished = [];
  plainGlobe.addEventListener('travelglobe:select', event => mapPublished.push(event.detail));
  context.harnessPin = mapPin;
  vm.runInContext('bindDestinationPin(harnessPin)', context);
  mapPin.dispatchEvent(new StubEvent('click', { detail: 1 }));
  assert.equal(mapPublished.length, 0, 'A pin outside the arrival globe must not publish a selection event.');

  // The shared selection listener must not re-run the pin pipeline: the
  // pinActivated discriminator short-circuits the homepage branch, so one
  // tap can never hydrate discovery twice.
  vm.runInContext(`
    globalThis.__pipelineCalls = { setActiveDestination: 0, hydrateDiscovery: 0 };
    setActiveDestination = function () { globalThis.__pipelineCalls.setActiveDestination += 1; };
    hydrateDiscovery = function () { globalThis.__pipelineCalls.hydrateDiscovery += 1; };
    destinationData = { budapest: { id: 'budapest', latitude: 47.4979, longitude: 19.0402 } };
    initGlobePointSelection();
  `, context);
  const selectionEvent = new StubEvent('travelglobe:select', {
    detail: {
      latitude: 47.4979, longitude: 19.0402, inputType: 'pointer', supported: true,
      selectionKind: 'destination', nearestDestination: 'budapest', pinActivated: true
    }
  });
  selectionEvent.target = globeRoot;
  documentStub.dispatchEvent(selectionEvent);
  let pipelineCalls = JSON.parse(vm.runInContext('JSON.stringify(globalThis.__pipelineCalls)', context));
  assert.deepEqual(pipelineCalls, { setActiveDestination: 0, hydrateDiscovery: 0 },
    'A pin-published selection must never re-run the destination pipeline.');
  const freeTapEvent = new StubEvent('travelglobe:select', {
    detail: {
      latitude: 47.4979, longitude: 19.0402, inputType: 'pointer', supported: true,
      selectionKind: 'destination', nearestDestination: 'budapest'
    }
  });
  freeTapEvent.target = globeRoot;
  documentStub.dispatchEvent(freeTapEvent);
  pipelineCalls = JSON.parse(vm.runInContext('JSON.stringify(globalThis.__pipelineCalls)', context));
  assert.deepEqual(pipelineCalls, { setActiveDestination: 1, hydrateDiscovery: 1 },
    'A free-tap destination selection must keep running the full pipeline exactly once.');
}

// --- Part B: the opening live-check reveal in trip-proposal.js ------------
function buildRevealHarness({ reducedMotion = false } = {}) {
  let clock = 0;
  let timerId = 0;
  const timers = new Map();
  const windowStub = {
    matchMedia: () => ({ matches: reducedMotion }),
    localStorage: { getItem: () => null, setItem() {}, removeItem() {} },
    setTimeout(callback, milliseconds) {
      timerId += 1;
      timers.set(timerId, { callback, at: clock + (Number(milliseconds) || 0) });
      return timerId;
    },
    clearTimeout(id) { timers.delete(id); }
  };
  const documentStub = {
    readyState: 'complete',
    addEventListener() {},
    removeEventListener() {},
    querySelectorAll: () => [],
    getElementById: () => null,
    createElement: tagName => new StubElement(tagName)
  };
  const context = vm.createContext({
    console,
    document: documentStub,
    window: windowStub,
    JSON,
    URL
  });
  new vm.Script(proposalJsSource, { filename: 'trip-proposal.js' }).runInContext(context);
  const api = windowStub.traVelTripProposal;
  assert.ok(api, 'trip-proposal.js must expose its runtime API.');
  const drainUntil = limit => {
    for (;;) {
      const due = [...timers.entries()]
        .filter(([, timer]) => timer.at <= limit)
        .sort((first, second) => first[1].at - second[1].at)[0];
      if (!due) { clock = Math.max(clock, limit); return; }
      timers.delete(due[0]);
      clock = due[1].at;
      due[1].callback();
    }
  };
  return { api, drainUntil, timers, clockNow: () => clock };
}

function buildPanelFixture({ armedAddons = 1 } = {}) {
  const panel = new StubElement('div');
  panel.setAttribute('data-trip-proposal', 'budapest');
  panel.setAttribute('data-trip-proposal-travelers', '2');
  panel.setAttribute('data-trip-proposal-min', '1');
  panel.setAttribute('data-trip-proposal-max', '6');
  panel.setAttribute('data-trip-proposal-check-prices-line', 'בודקים לך את המחירים הטובים ביותר לבודפשט...');
  panel.setAttribute('data-trip-proposal-check-dates-line', 'בודקים מתי הכי שווה לטוס...');
  panel.setAttribute('data-trip-proposal-verdict-one', 'אתם טסים. נוסע אחד לבודפשט.');
  panel.setAttribute('data-trip-proposal-verdict-many', 'אתם טסים. %s נוסעים לבודפשט.');

  const fill = new StubElement('p');
  fill.setAttribute('data-trip-proposal-fill', '');
  fill.hidden = true;
  const body = new StubElement('div');
  body.setAttribute('data-trip-proposal-body', '');
  const party = new StubElement('div');
  party.setAttribute('data-trip-proposal-party-field', '');
  const picker = new StubElement('div');
  picker.setAttribute('data-trip-proposal-tier-picker', '');
  const sectionValue = new StubElement('section');
  sectionValue.setAttribute('data-trip-proposal-tier', 'value');
  const dates = new StubElement('li');
  dates.setAttribute('data-trip-proposal-tier-dates', '');
  sectionValue.append(dates);
  const sectionFast = new StubElement('section');
  sectionFast.setAttribute('data-trip-proposal-tier', 'fast');
  sectionFast.hidden = true;
  const addonsField = new StubElement('div');
  addonsField.setAttribute('data-trip-proposal-addons-field', '');
  const addonRows = [];
  const verdictTexts = ['השגנו לך ביטוח.', 'השגנו לך eSIM.', 'השגנו לך העברה.'];
  for (let index = 0; index < 3; index += 1) {
    const row = new StubElement('li');
    row.setAttribute('data-trip-proposal-addon', ['insurance', 'esim', 'transfer'][index]);
    if (index < armedAddons) {
      // A live program: the server printed both the verdict sentence and
      // the real supplier link.
      row.setAttribute('data-trip-proposal-addon-verdict', verdictTexts[index]);
      const link = new StubElement('a');
      link.setAttribute('data-trip-proposal-addon-link', '');
      row.append(link);
    } else if (index === armedAddons) {
      // Sentence without a link: the double gate must reject it.
      row.setAttribute('data-trip-proposal-addon-verdict', verdictTexts[index]);
    }
    // The remaining row has neither, like a disabled program.
    addonsField.append(row);
    addonRows.push(row);
  }
  const total = new StubElement('p');
  total.setAttribute('data-trip-proposal-total', '');
  const exits = new StubElement('div');
  exits.setAttribute('data-trip-proposal-exits', '');
  body.append(party, picker, sectionValue, sectionFast, addonsField, total, exits);
  panel.append(fill, body);
  return { panel, fill, body, party, sectionValue, dates, picker, addonsField, addonRows, total, exits };
}

{
  // Script resolution: only server-present strings, count substituted the
  // pinned-template way, and the double-gated add-on verdicts.
  const { api } = buildRevealHarness();
  const { panel } = buildPanelFixture({ armedAddons: 1 });
  const script = api.liveCheckScript(panel);
  assert.ok(script, 'A panel carrying the server sentences must produce a script.');
  assert.equal(script.checkPrices, 'בודקים לך את המחירים הטובים ביותר לבודפשט...');
  assert.equal(script.checkDates, 'בודקים מתי הכי שווה לטוס...');
  assert.equal(script.verdict, 'אתם טסים. 2 נוסעים לבודפשט.', 'The verdict must substitute the real traveler count into the server template.');
  assert.equal(script.addons.length, 1, 'Only the row with both the server sentence and the live link may produce a verdict.');
  assert.equal(script.addons[0].text, 'השגנו לך ביטוח.');
  panel.setAttribute('data-trip-proposal-travelers', '1');
  assert.equal(api.liveCheckVerdict(panel), 'אתם טסים. נוסע אחד לבודפשט.', 'One traveler must read the singular server sentence.');
  panel.setAttribute('data-trip-proposal-travelers', '2');

  const bare = new StubElement('div');
  bare.setAttribute('data-trip-proposal', 'x');
  assert.equal(api.liveCheckScript(bare), null, 'Without the server sentences there is no script and the 1.40.0 reveal remains.');
}

{
  // Reduced motion: the finished state, instantly, with the verdict
  // transcript written and no pending timers.
  const { api, timers } = buildRevealHarness({ reducedMotion: true });
  const { panel } = buildPanelFixture({ armedAddons: 1 });
  api.runLiveCheck(panel);
  assert.equal(panel.getAttribute('data-trip-proposal-state'), 'ready', 'Reduced motion must land on the finished panel instantly.');
  assert.equal(timers.size, 0, 'Reduced motion must schedule nothing.');
  const lines = panel.querySelectorAll('[data-trip-proposal-screen-line]');
  assert.deepEqual(lines.map(line => line.textContent), ['אתם טסים. 2 נוסעים לבודפשט.', 'השגנו לך ביטוח.'],
    'Reduced motion still shows the fully written verdict transcript.');
}

{
  // The full sequence: typed letters, real fields pulsing awake in order,
  // the four second budget, and only server bytes on the screen.
  const { api, drainUntil, clockNow } = buildRevealHarness();
  const fixture = buildPanelFixture({ armedAddons: 1 });
  const { panel } = fixture;
  api.runLiveCheck(panel);
  assert.equal(panel.getAttribute('data-trip-proposal-state'), 'checking', 'The sequence must open in the checking state.');
  assert.equal(fixture.fill.hidden, true, 'The 1.40.0 staging overlay must stay hidden underneath the reveal.');

  drainUntil(90);
  const firstLine = panel.querySelector('[data-trip-proposal-screen-line="check"]');
  assert.ok(firstLine, 'The first check sentence must be typing.');
  assert.ok(firstLine.textContent.length > 0 && firstLine.textContent.length < 'בודקים לך את המחירים הטובים ביותר לבודפשט...'.length,
    'Letters must appear one at a time, not all at once.');
  assert.ok('בודקים לך את המחירים הטובים ביותר לבודפשט...'.startsWith(firstLine.textContent),
    'Typed text must be a prefix of the server sentence, never invented.');

  drainUntil(1500);
  assert.equal(fixture.party.classList.contains('is-live-armed'), true, 'The party field must pulse awake after the price sentence.');
  assert.equal(fixture.sectionValue.classList.contains('is-live-armed'), true, 'The visible record must pulse awake with its found price.');

  drainUntil(4000);
  assert.equal(panel.getAttribute('data-trip-proposal-state'), 'ready', 'The sequence must finish on its own.');
  assert.ok(clockNow() <= 4000, 'The whole sequence must end inside the four second law.');
  assert.equal(fixture.dates.classList.contains('is-live-armed'), true, 'The dates fact must pulse awake after the dates sentence.');
  assert.equal(fixture.picker.classList.contains('is-live-armed'), true, 'The tier picker must wake as a field the machine filled.');
  assert.equal(fixture.addonRows[0].classList.contains('is-live-armed'), true, 'The armed add-on row must pulse with its verdict.');
  assert.equal(fixture.addonRows[1].classList.contains('is-live-armed'), false, 'A linkless add-on must never pulse as achieved.');
  const finalLines = panel.querySelectorAll('[data-trip-proposal-screen-line]');
  assert.deepEqual(finalLines.map(line => line.textContent), ['אתם טסים. 2 נוסעים לבודפשט.', 'השגנו לך ביטוח.'],
    'The finished transcript keeps exactly the verdict sentences.');
}

{
  // Skip: one interaction mid-sequence cuts straight to the finished state
  // and leaves no timer behind.
  const { api, drainUntil, timers } = buildRevealHarness();
  const { panel } = buildPanelFixture({ armedAddons: 1 });
  api.runLiveCheck(panel);
  drainUntil(400);
  assert.equal(panel.getAttribute('data-trip-proposal-state'), 'checking');
  assert.equal(api.finishLiveCheck(), true, 'A skip must be possible mid-sequence.');
  assert.equal(panel.getAttribute('data-trip-proposal-state'), 'ready', 'A skip must land on the finished panel.');
  assert.equal(timers.size, 0, 'A skip must clear every pending timer.');
  const lines = panel.querySelectorAll('[data-trip-proposal-screen-line]');
  assert.deepEqual(lines.map(line => line.textContent), ['אתם טסים. 2 נוסעים לבודפשט.', 'השגנו לך ביטוח.'],
    'A skip must still deliver the fully written verdict.');
  assert.equal(api.finishLiveCheck(), false, 'Finishing twice must be a safe no-op.');
  assert.equal(api.runLiveCheck(panel) > 0, true, 'The reveal must be able to play again on the next open.');
  api.finishLiveCheck();
}

{
  // Long scripts compress instead of overrunning: all three programs armed
  // still ends inside the budget and the cadence never collapses below the
  // readable floor.
  const { api, drainUntil, clockNow } = buildRevealHarness();
  const { panel } = buildPanelFixture({ armedAddons: 3 });
  const script = api.liveCheckScript(panel);
  assert.equal(script.addons.length, 3, 'All three live programs must be allowed their verdicts.');
  const charMs = api.liveCheckCharMs(script);
  assert.ok(charMs >= api.timings.minCharMs && charMs <= api.timings.charMs, 'The cadence must stay between the readable floor and the dictated pace.');
  api.runLiveCheck(panel);
  drainUntil(4000);
  assert.equal(panel.getAttribute('data-trip-proposal-state'), 'ready');
  assert.ok(clockNow() <= 4000, 'Even the longest honest script must end inside the four second law.');
  const verdicts = panel.querySelectorAll('[data-trip-proposal-screen-line]').map(line => line.textContent);
  assert.deepEqual(verdicts, ['אתם טסים. 2 נוסעים לבודפשט.', 'השגנו לך ביטוח.', 'השגנו לך eSIM.', 'השגנו לך העברה.'],
    'Every armed program earns exactly its server-authored verdict, in order.');
}

// --- Part C: source truths ------------------------------------------------
const failures = [];
const requireMarkers = (source, label, markers) => {
  for (const marker of markers) {
    if (!source.includes(marker)) failures.push(`${label} is missing ${marker}`);
  }
};

// The server authors every sentence and gates add-on verdicts on the live
// registry; the template only ever prints a non-empty verdict.
requireMarkers(proposalPhpSource, 'inc/proposal.php', [
  "'verdict_line' => $is_live && isset( $verdict_lines[ $key ] ) ? $verdict_lines[ $key ] : ''",
  'בודקים לך את המחירים הטובים ביותר ל%s...',
  'בודקים מתי הכי שווה לטוס...',
  'אתם טסים. נוסע אחד ל%s.',
  'אתם טסים. %1$s נוסעים ל%2$s.',
  'השגנו לך ביטוח.',
  'השגנו לך eSIM.',
  'השגנו לך העברה.',
]);
requireMarkers(panelTemplateSource, 'template-parts/proposal-panel.php', [
  "'' !== $addon['verdict_line'] ? ' data-trip-proposal-addon-verdict=",
  'data-trip-proposal-check-prices-line',
  'data-trip-proposal-check-dates-line',
  'data-trip-proposal-verdict-one',
  'data-trip-proposal-verdict-many',
  'data-trip-proposal-party-field',
  'data-trip-proposal-tier-picker',
  'data-trip-proposal-tier-dates',
  'data-trip-proposal-addons-field',
  'data-trip-proposal-exits',
]);

// Pinned 1.40.0 strings stay byte-identical.
requireMarkers(proposalPhpSource, 'Pinned 1.40.0 proposal strings', [
  'הצעה מלאה בקליק',
  'סה"כ טיסות: %s לכל הנוסעים. תוספות נסגרות ומתומחרות אצל הספקים.',
  'טיסה: מחיר שנמצא בחיפושים אחרונים',
  'תוספות: נסגרות ומתומחרות אצל הספקים',
  'ההצעה מוכנה. הכול ניתן לעריכה.',
  'הזמינו את הטיסה עכשיו',
  'סגרו לי את הכול בוואטסאפ',
]);
requireMarkers(pricesPhpSource, 'Real tier labels only', ['הכי משתלם', "'direct' => __( 'ישיר', 'tra-vel-v2' )", 'הכי מהיר']);

// The P0 repair stays wired: publication after the pipeline, the
// discriminator before the shared pipeline, and the card listeners intact.
requireMarkers(appSource, 'app.js P0 repair', [
  'function publishDestinationPinSelection(pin, viaPointer)',
  "pin.closest('[data-globe-3d][data-globe-arrival=\"true\"]')",
  'pinActivated: true',
  'if (detail.pinActivated === true) return;',
]);
if (!/hydrateDiscovery\(discoveryRequestParams\(\{ destination: pin\.dataset\.destination \}\)\);\s*\}, 40\);\s*publishDestinationPinSelection\(pin,/.test(appSource)) {
  failures.push('The pin tap must publish its selection synchronously and stage the full existing pipeline right behind it, never drop it.');
}
if (!globeSource.includes("root.addEventListener('travelglobe:select', event => renderArrivalCard(event.detail || {}))")) {
  failures.push('The arrival card must keep rendering from the published selection event.');
}
if (!proposalJsSource.includes("document.addEventListener('travelglobe:select', handleGlobeSelection)")) {
  failures.push('The proposal trigger must keep growing from the published selection event.');
}

// The reveal's own laws: no requests, no navigation, no submission, no
// scroll traps, no commerce vocabulary, no em or en dashes, and the
// codename nowhere.
for (const [pattern, reason] of [
  [/fetch\s*\(/, 'a network request'],
  [/XMLHttpRequest/, 'a network request'],
  [/\.submit\s*\(/, 'a form submission'],
  [/location\.(?:assign|replace|href\s*=)/, 'a navigation'],
  [/window\.open/, 'a popup navigation'],
  [/addEventListener\(\s*['"](?:wheel|mousewheel|scroll|touchmove)['"]/, 'a scroll trap'],
]) {
  if (pattern.test(proposalJsSource)) failures.push(`trip-proposal.js contains ${reason}.`);
}
for (const [file, source] of [['trip-proposal.js', proposalJsSource], ['inc/proposal.php', proposalPhpSource], ['template-parts/proposal-panel.php', panelTemplateSource]]) {
  if (source.includes('—') || source.includes('–')) failures.push(`${file} contains an em or en dash.`);
  if (/movie/i.test(source)) failures.push(`${file} exposes the internal release codename.`);
  if (/schema\.org|itemtype|itemprop|"@type"/.test(source)) failures.push(`${file} carries structured commerce markup the money rules forbid.`);
}

if (failures.length) {
  console.error('Trip proposal reveal validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Trip proposal reveal harness passed (pin tap publishes the card selection once, keyboard and pointer input types, no double pipeline, server-authored typewriter only, double-gated add-on verdicts, four second budget, skip and reduced motion land finished, no requests or navigation or submission).');
