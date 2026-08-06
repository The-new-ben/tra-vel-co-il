// Theme 1.41.0/1.42.0 behavioural harness: the dead pin tap repair, the
// proposal's opening live-check reveal, and the full-screen takeover stage.
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
// every step runs behind a ring that spins and snaps to done, an add-on
// verdict appears only when the row carries both the server-authored
// sentence and the real supplier link, the whole sequence ends inside the
// four second budget, one skip lands on the finished state, and reduced
// motion never sees a frame. It then proves the 1.42.0 takeover: a pin
// activation pulses the pin and lifts the panel into the fixed stage, page
// scroll locks through one class and unlocks on close, focus is trapped
// inside the stage and returns to the pin on Escape, the panel re-docks to
// the exact spot it came from, and the synthesized tap sound exists only
// behind a user gesture with a persistent mute.
//
// Part C pins the source truths: gating on the server, pinned 1.40.0
// strings byte-identical, the scroll law, no requests, no navigation, no
// submissions, no commerce vocabulary, and no em or en dashes.
//
// Part D drives the production globe-3d.js far enough to prove the ambient
// price-pop scheduler's pause gates without a GPU.
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
const appCssSource = readFileSync(join(themeRoot, 'assets', 'css', 'app.css'), 'utf8');
const proposalPhpSource = readFileSync(join(themeRoot, 'inc', 'proposal.php'), 'utf8');
const panelTemplateSource = readFileSync(join(themeRoot, 'template-parts', 'proposal-panel.php'), 'utf8');
const pricesPhpSource = readFileSync(join(themeRoot, 'inc', 'prices.php'), 'utf8');
const frontPageSource = readFileSync(join(themeRoot, 'front-page.php'), 'utf8');

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
  get nextSibling() {
    if (!this.parentNode) return null;
    const index = this.parentNode.children.indexOf(this);
    return index >= 0 ? this.parentNode.children[index + 1] || null : null;
  }
  setAttribute(name, value) {
    this.attributes.set(name, String(value));
    if (name.startsWith('data-')) {
      const key = name.slice(5).replace(/-([a-z])/g, (match, letter) => letter.toUpperCase());
      this.dataset[key] = String(value);
    }
  }
  getAttribute(name) { return this.attributes.get(name) ?? null; }
  removeAttribute(name) {
    this.attributes.delete(name);
    if (name.startsWith('data-')) {
      const key = name.slice(5).replace(/-([a-z])/g, (match, letter) => letter.toUpperCase());
      delete this.dataset[key];
    }
  }
  append(...nodes) {
    nodes.forEach(node => {
      if (node.parentNode && typeof node.parentNode.removeChild === 'function') node.parentNode.removeChild(node);
      node.parentNode = this;
      this.children.push(node);
    });
  }
  insertBefore(node, reference) {
    if (node.parentNode && typeof node.parentNode.removeChild === 'function') node.parentNode.removeChild(node);
    node.parentNode = this;
    const index = this.children.indexOf(reference);
    if (index < 0) this.children.push(node); else this.children.splice(index, 0, node);
    return node;
  }
  removeChild(node) {
    const index = this.children.indexOf(node);
    if (index >= 0) {
      this.children.splice(index, 1);
      node.parentNode = null;
    }
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
  removeEventListener(type, callback) {
    const handlers = this.listeners.get(type) || [];
    const index = handlers.indexOf(callback);
    if (index >= 0) handlers.splice(index, 1);
  }
  dispatchEvent(event) {
    [...(this.listeners.get(event.type) || [])].forEach(callback => callback(event));
    return true;
  }
  focus() {
    this.focused = true;
    if (this.ownerDocumentStub) this.ownerDocumentStub.activeElement = this;
  }
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
function buildRevealHarness({ reducedMotion = false, storage = new Map() } = {}) {
  let clock = 0;
  let timerId = 0;
  const timers = new Map();
  const audio = { constructed: 0, starts: 0, stops: 0 };
  const audioParam = () => ({ setValueAtTime() {}, exponentialRampToValueAtTime() {} });
  class StubAudioContext {
    constructor() {
      audio.constructed += 1;
      this.currentTime = 0;
      this.state = 'running';
      this.destination = {};
    }
    resume() {}
    createOscillator() {
      return { type: '', frequency: audioParam(), connect() {}, start() { audio.starts += 1; }, stop() { audio.stops += 1; } };
    }
    createGain() {
      return { gain: audioParam(), connect() {} };
    }
  }
  const windowStub = {
    matchMedia: () => ({ matches: reducedMotion }),
    localStorage: {
      getItem: key => (storage.has(key) ? storage.get(key) : null),
      setItem: (key, value) => { storage.set(key, String(value)); },
      removeItem: key => { storage.delete(key); }
    },
    AudioContext: StubAudioContext,
    setTimeout(callback, milliseconds) {
      timerId += 1;
      timers.set(timerId, { callback, at: clock + (Number(milliseconds) || 0) });
      return timerId;
    },
    clearTimeout(id) { timers.delete(id); }
  };
  const bodyStub = new StubElement('body');
  const documentElementStub = new StubElement('html');
  const documentListeners = new Map();
  const documentStub = {
    readyState: 'complete',
    body: bodyStub,
    documentElement: documentElementStub,
    activeElement: null,
    addEventListener(type, callback) {
      const handlers = documentListeners.get(type) || [];
      handlers.push(callback);
      documentListeners.set(type, handlers);
    },
    removeEventListener(type, callback) {
      const handlers = documentListeners.get(type) || [];
      const index = handlers.indexOf(callback);
      if (index >= 0) handlers.splice(index, 1);
    },
    dispatchEvent(event) {
      [...(documentListeners.get(event.type) || [])].forEach(callback => callback(event));
      return true;
    },
    querySelector: selector => bodyStub.querySelector(selector),
    querySelectorAll: selector => bodyStub.querySelectorAll(selector),
    getElementById: id => bodyStub.descendants().find(node => node.getAttribute?.('id') === String(id)) || null,
    createElement: tagName => {
      const element = new StubElement(tagName);
      element.ownerDocumentStub = documentStub;
      return element;
    }
  };
  bodyStub.ownerDocumentStub = documentStub;
  const context = vm.createContext({
    console,
    document: documentStub,
    window: windowStub,
    JSON,
    URL,
    CSS: { escape: value => String(value) }
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
  return { api, drainUntil, timers, clockNow: () => clock, document: documentStub, body: bodyStub, documentElement: documentElementStub, audio, storage };
}

function buildPanelFixture({ armedAddons = 1, flightFound = true } = {}) {
  const panel = new StubElement('div');
  panel.setAttribute('id', 'trip-proposal-9');
  panel.id = 'trip-proposal-9';
  panel.setAttribute('data-trip-proposal', 'budapest');
  panel.setAttribute('data-trip-proposal-travelers', '2');
  panel.setAttribute('data-trip-proposal-min', '1');
  panel.setAttribute('data-trip-proposal-max', '6');
  panel.setAttribute('data-trip-proposal-check-prices-line', 'בודקים לך את המחירים הטובים ביותר לבודפשט...');
  panel.setAttribute('data-trip-proposal-check-dates-line', 'בודקים מתי הכי שווה לטוס...');
  if (flightFound) panel.setAttribute('data-trip-proposal-flight-found-line', 'נמצאה טיסה: מ-411 ₪ לנוסע, הלוך ושוב.');
  panel.setAttribute('data-trip-proposal-start-label', 'התחילו');
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

const FIXTURE_TRANSCRIPT = [
  'בודקים לך את המחירים הטובים ביותר לבודפשט...',
  'בודקים מתי הכי שווה לטוס...',
  'נמצאה טיסה: מ-411 ₪ לנוסע, הלוך ושוב.',
  'השגנו לך ביטוח.',
  'אתם טסים. 2 נוסעים לבודפשט.'
];

{
  // Script resolution: only server-present strings, count substituted the
  // pinned-template way, the double-gated add-on verdicts, and the found
  // record's sentence carried whole.
  const { api } = buildRevealHarness();
  const { panel } = buildPanelFixture({ armedAddons: 1 });
  const script = api.liveCheckScript(panel);
  assert.ok(script, 'A panel carrying the server sentences must produce a script.');
  assert.equal(script.checkPrices, 'בודקים לך את המחירים הטובים ביותר לבודפשט...');
  assert.equal(script.checkDates, 'בודקים מתי הכי שווה לטוס...');
  assert.equal(script.flightFound, 'נמצאה טיסה: מ-411 ₪ לנוסע, הלוך ושוב.', 'The found-flight sentence must be the server byte string, never assembled.');
  assert.equal(script.verdict, 'אתם טסים. 2 נוסעים לבודפשט.', 'The verdict must substitute the real traveler count into the server template.');
  assert.equal(script.addons.length, 1, 'Only the row with both the server sentence and the live link may produce a verdict.');
  assert.equal(script.addons[0].text, 'השגנו לך ביטוח.');
  panel.setAttribute('data-trip-proposal-travelers', '1');
  assert.equal(api.liveCheckVerdict(panel), 'אתם טסים. נוסע אחד לבודפשט.', 'One traveler must read the singular server sentence.');
  panel.setAttribute('data-trip-proposal-travelers', '2');

  // The step order is the machine's story: prices, dates, the found record,
  // the achieved add-ons, and the verdict last.
  const steps = api.liveCheckStepList(panel, script);
  assert.deepEqual([...steps].map(step => step.text), FIXTURE_TRANSCRIPT, 'The run must stage exactly the server sentences in the dictated order.');
  assert.deepEqual([...steps].map(step => step.kind), ['check', 'check', 'check', 'verdict', 'verdict'], 'Checks stay checks; achievements and the verdict read as verdicts.');

  // A panel without the found-flight sentence simply runs without that step:
  // the 1.41.0 script keeps working.
  const legacy = buildPanelFixture({ armedAddons: 1, flightFound: false });
  const legacyScript = api.liveCheckScript(legacy.panel);
  assert.ok(legacyScript, 'A 1.41.0-shaped panel must still produce a script.');
  assert.equal(legacyScript.flightFound, '', 'No server sentence, no found step.');
  assert.equal(api.liveCheckStepList(legacy.panel, legacyScript).length, 4, 'The found step must simply be absent, never invented.');

  const bare = new StubElement('div');
  bare.setAttribute('data-trip-proposal', 'x');
  assert.equal(api.liveCheckScript(bare), null, 'Without the server sentences there is no script and the 1.40.0 reveal remains.');
}

{
  // Narrative honesty (theme 1.42.1): the receipt is the answer, so it wakes
  // on the found-flight line and never a step before it. The first check may
  // only wake the party field, the found step wakes the tier section itself,
  // and the pinned total line stays out of every typed step so it lands in
  // the finale beside the tier cards and the exits.
  const { api } = buildRevealHarness();
  const fixture = buildPanelFixture({ armedAddons: 1 });
  const script = api.liveCheckScript(fixture.panel);
  const steps = api.liveCheckStepList(fixture.panel, script);
  assert.ok(!steps[0].targets.includes(fixture.sectionValue), 'The first check must not wake the receipt before the flight is found.');
  assert.ok(steps[0].targets.includes(fixture.party), 'The first check still wakes the party field.');
  const foundStep = steps.find(step => step.text === script.flightFound);
  assert.ok(foundStep && foundStep.targets.includes(fixture.sectionValue), 'The found-flight line is the moment the receipt materializes.');
  for (const step of steps) {
    assert.ok(!step.targets.includes(fixture.total), 'The total line belongs to the finale, never to a typed step.');
  }

  // Without the found sentence the 1.41.0 wake order remains: the receipt
  // wakes on the first check, because no later step could ever reach it.
  const legacyFixture = buildPanelFixture({ armedAddons: 1, flightFound: false });
  const legacySteps = api.liveCheckStepList(legacyFixture.panel, api.liveCheckScript(legacyFixture.panel));
  assert.ok(legacySteps[0].targets.includes(legacyFixture.sectionValue), 'A panel without the found sentence must still wake its receipt on the first check.');
}

{
  // The takeover stage may not show the docked panel's corner close button:
  // the chrome row's back pill, the backdrop and Escape own the exit there.
  assert.ok(/\.trip-takeover-stage \.trip-proposal-close \{ display: none; \}/.test(appCssSource), 'The takeover stage must hide the docked close button.');
}

{
  // Reduced motion: the finished state, instantly, with the whole step
  // transcript written, every ring done, and no pending timers.
  const { api, timers } = buildRevealHarness({ reducedMotion: true });
  const { panel } = buildPanelFixture({ armedAddons: 1 });
  api.runLiveCheck(panel);
  assert.equal(panel.getAttribute('data-trip-proposal-state'), 'ready', 'Reduced motion must land on the finished panel instantly.');
  assert.equal(timers.size, 0, 'Reduced motion must schedule nothing.');
  const lines = panel.querySelectorAll('[data-trip-proposal-screen-line]');
  assert.deepEqual(lines.map(line => line.textContent), FIXTURE_TRANSCRIPT,
    'Reduced motion still shows the fully written step transcript.');
  const rows = panel.querySelectorAll('[data-trip-proposal-step-row]');
  assert.equal(rows.length, 5, 'Every step earns its row.');
  assert.ok(rows.every(row => row.classList.contains('is-done') && !row.classList.contains('is-spinning')),
    'Reduced motion shows every ring already snapped to done.');
  assert.equal(panel.getAttribute('data-trip-proposal-landing'), 'instant', 'Reduced motion lands with no staged tier-card choreography.');
}

{
  // The full sequence: typed letters behind a spinning ring, real fields
  // pulsing awake in order, the four second budget, and only server bytes
  // on the screen.
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
  const firstRow = panel.querySelector('[data-trip-proposal-step-row]');
  assert.ok(firstRow.classList.contains('is-spinning'), 'The ring must spin while its line types.');
  assert.ok(firstRow.querySelector('[data-trip-proposal-step-ring]'), 'Every step row carries its ring.');

  drainUntil(1500);
  assert.equal(fixture.party.classList.contains('is-live-armed'), true, 'The party field must pulse awake after the price sentence.');
  // Narrative honesty (theme 1.42.1): the receipt wakes exactly when the
  // found-flight line lands - the third done ring - never a beat before, at
  // any cadence the budget compressor may choose.
  const doneRowCount = panel.querySelectorAll('[data-trip-proposal-step-row]').filter(row => row.classList.contains('is-done')).length;
  assert.equal(fixture.sectionValue.classList.contains('is-live-armed'), doneRowCount >= 3,
    'The receipt materializes with the found-flight line, never before the machine says it found one.');
  assert.equal(firstRow.classList.contains('is-done'), true, 'A completed step must snap its ring to done.');
  assert.equal(firstRow.classList.contains('is-spinning'), false, 'A snapped ring must stop spinning.');

  drainUntil(4000);
  assert.equal(panel.getAttribute('data-trip-proposal-state'), 'ready', 'The sequence must finish on its own.');
  assert.ok(clockNow() <= 4000, 'The whole sequence must end inside the four second law.');
  assert.equal(fixture.dates.classList.contains('is-live-armed'), true, 'The dates fact must pulse awake after the dates sentence.');
  assert.equal(fixture.sectionValue.classList.contains('is-live-armed'), true, 'The finished panel must have its receipt fully awake.');
  assert.equal(fixture.picker.classList.contains('is-live-armed'), true, 'The tier picker must wake as a field the machine filled.');
  assert.equal(fixture.total.classList.contains('is-live-armed'), true, 'The total line must wake in the finale.');
  assert.equal(fixture.addonRows[0].classList.contains('is-live-armed'), true, 'The armed add-on row must pulse with its verdict.');
  assert.equal(fixture.addonRows[1].classList.contains('is-live-armed'), false, 'A linkless add-on must never pulse as achieved.');
  const finalLines = panel.querySelectorAll('[data-trip-proposal-screen-line]');
  assert.deepEqual(finalLines.map(line => line.textContent), FIXTURE_TRANSCRIPT,
    'The finished transcript keeps exactly the step sentences, verdict last.');
  assert.ok(panel.querySelectorAll('[data-trip-proposal-step-row]').every(row => row.classList.contains('is-done')),
    'Natural completion leaves every ring done.');
  assert.equal(panel.getAttribute('data-trip-proposal-landing'), 'staged',
    'Natural completion earns the staged tier-card landing.');
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
  assert.deepEqual(lines.map(line => line.textContent), FIXTURE_TRANSCRIPT,
    'A skip must still deliver the fully written step transcript.');
  assert.equal(panel.getAttribute('data-trip-proposal-landing'), 'instant',
    'A skip lands instantly: the staged tier-card choreography is only for natural completion.');
  assert.equal(api.finishLiveCheck(), false, 'Finishing twice must be a safe no-op.');
  assert.equal(api.runLiveCheck(panel) > 0, true, 'The reveal must be able to play again on the next open.');
  api.finishLiveCheck();
}

{
  // Long scripts compress instead of overrunning: all three programs armed
  // still ends inside the budget. The cadence never collapses below the
  // readable floor; when the floor alone cannot fit, the step holds shrink.
  const { api, drainUntil, clockNow } = buildRevealHarness();
  const { panel } = buildPanelFixture({ armedAddons: 3 });
  const script = api.liveCheckScript(panel);
  assert.equal(script.addons.length, 3, 'All three live programs must be allowed their verdicts.');
  const charMs = api.liveCheckCharMs(script);
  assert.ok(charMs >= api.timings.minCharMs && charMs <= api.timings.charMs, 'The cadence must stay between the readable floor and the dictated pace.');
  const plan = api.liveCheckPlan(script);
  assert.ok(plan.snapMs >= api.timings.minSnap && plan.snapMs <= api.timings.snap, 'The step hold must stay between its floor and the dictated beat.');
  assert.equal(plan.steps, 7, 'Three checks, three achievements, one verdict.');
  api.runLiveCheck(panel);
  drainUntil(api.timings.budget);
  assert.equal(panel.getAttribute('data-trip-proposal-state'), 'ready', 'Even the longest honest script must end inside the dictated budget.');
  assert.ok(clockNow() <= api.timings.budget, 'The drained clock itself stays inside the budget.');
  const verdicts = panel.querySelectorAll('[data-trip-proposal-screen-line]').map(line => line.textContent);
  assert.deepEqual(verdicts, [
    'בודקים לך את המחירים הטובים ביותר לבודפשט...',
    'בודקים מתי הכי שווה לטוס...',
    'נמצאה טיסה: מ-411 ₪ לנוסע, הלוך ושוב.',
    'השגנו לך ביטוח.',
    'השגנו לך eSIM.',
    'השגנו לך העברה.',
    'אתם טסים. 2 נוסעים לבודפשט.'
  ], 'Every armed program earns exactly its server-authored verdict, in order, verdict last.');
}

// --- Part B2: the takeover stage (theme 1.42.0) ---------------------------
function buildTakeoverWorld(options = {}) {
  const world = buildRevealHarness(options);
  const fixture = buildPanelFixture({ armedAddons: 1 });
  const dock = new StubElement('div');
  dock.setAttribute('data-trip-proposal-dock', '');
  dock.append(fixture.panel);
  fixture.panel.hidden = true;
  const globe = new StubElement('div');
  globe.setAttribute('data-globe-3d', '');
  globe.setAttribute('data-globe-arrival', 'true');
  const pin = new StubElement('button');
  pin.setAttribute('data-destination', 'budapest');
  pin.setAttribute('class', 'price-pin');
  pin.ownerDocumentStub = world.document;
  globe.append(pin);
  const card = new StubElement('div');
  card.setAttribute('data-globe-arrival-card', 'true');
  const cardTitle = new StubElement('strong');
  const cardSubtitle = new StubElement('small');
  const cardLinks = new StubElement('div');
  cardLinks.setAttribute('class', 'globe-arrival-card-links');
  cardLinks.className = 'globe-arrival-card-links';
  card.append(cardTitle, cardSubtitle, cardLinks);
  globe.append(card);
  world.body.append(globe, dock);
  fixture.panel.ownerDocumentStub = world.document;
  return { ...world, fixture, dock, globe, pin, card };
}

{
  // Opening lifts the one panel node into the fixed stage, locks page
  // scroll with the overflow class, and closing re-docks the same node in
  // its original spot with the lock released.
  const world = buildTakeoverWorld();
  const { api, fixture, dock, pin } = world;
  assert.equal(world.body.querySelector('[data-trip-takeover]'), null, 'No takeover layer may exist before the first open.');
  assert.equal(api.openPanel(fixture.panel, pin), true, 'The panel must open.');
  const takeoverRoot = world.body.querySelector('[data-trip-takeover]');
  assert.ok(takeoverRoot, 'Opening must build the takeover layer once.');
  assert.equal(takeoverRoot.hidden, false, 'The takeover must be visible while open.');
  assert.equal(takeoverRoot.getAttribute('role'), 'dialog', 'The takeover is a dialog.');
  assert.equal(takeoverRoot.getAttribute('aria-modal'), 'true', 'The takeover is modal.');
  const stage = takeoverRoot.querySelector('[data-trip-takeover-stage]');
  assert.equal(fixture.panel.parentNode, stage, 'The same panel node must stand on the stage, never a copy.');
  assert.equal(dock.children.includes(fixture.panel), false, 'The dock must not keep a duplicate.');
  assert.equal(world.documentElement.classList.contains('is-trip-takeover-open'), true, 'Opening must lock page scroll through the one overflow class.');
  assert.equal(fixture.panel.hidden, false, 'The panel is visible on the stage.');
  assert.equal(fixture.panel.focused, true, 'Focus moves into the panel on open.');
  assert.ok(takeoverRoot.querySelector('[data-trip-takeover-back]'), 'The stage carries the back control.');
  assert.ok(takeoverRoot.querySelector('[data-trip-takeover-sound]'), 'The stage carries the sound toggle.');

  assert.equal(api.closePanel(), true, 'The panel must close.');
  assert.equal(takeoverRoot.hidden, true, 'Closing hides the takeover.');
  assert.equal(fixture.panel.parentNode, dock, 'Closing must re-dock the panel exactly where it came from.');
  assert.equal(fixture.panel.hidden, true, 'The docked panel is hidden again.');
  assert.equal(world.documentElement.classList.contains('is-trip-takeover-open'), false, 'Closing must release the scroll lock.');
  assert.equal(pin.focused, true, 'Closing returns focus to the trigger pin.');

  // Reopen proves the takeover layer is reused, never rebuilt.
  api.openPanel(fixture.panel, pin);
  assert.equal(world.body.querySelectorAll('[data-trip-takeover]').length, 1, 'One takeover layer, forever.');
  api.closePanel();
}

{
  // The pin flow: a published pin activation pulses the pin for the
  // acknowledgment beat, then the takeover opens with the pin as the focus
  // home, the card slims to its essentials, and its start button reads the
  // server-authored label.
  const world = buildTakeoverWorld();
  const { api, fixture, drainUntil, pin, card, globe } = world;
  const selection = {
    type: 'travelglobe:select',
    target: globe,
    detail: { selectionKind: 'destination', nearestDestination: 'budapest', pinActivated: true, inputType: 'pointer' }
  };
  world.document.dispatchEvent(selection);
  assert.equal(pin.classList.contains('is-proposal-pulse'), true, 'The tapped pin must pulse its acknowledgment.');
  assert.equal(fixture.panel.hidden, true, 'The takeover waits out the acknowledgment pulse.');
  drainUntil(api.timings.pinPulse + 20);
  assert.equal(pin.classList.contains('is-proposal-pulse'), false, 'The pulse ends when the takeover opens.');
  assert.equal(fixture.panel.hidden, false, 'The takeover opens on its own after the pulse.');
  assert.equal(api.takeoverOpen(), true, 'The takeover reports open.');
  assert.equal(fixture.panel.parentNode.getAttribute('data-trip-takeover-stage'), 'true', 'The pin flow lands the panel on the stage.');
  assert.equal(card.getAttribute('data-trip-proposal-card-mode'), 'takeover', 'The card slims while a panel exists for the selection.');
  const cardTrigger = card.querySelector('[data-trip-proposal-card-trigger]');
  assert.ok(cardTrigger, 'The card gains the start button.');
  assert.equal(cardTrigger.textContent, 'התחילו', 'The start button must carry the server-authored label.');
  drainUntil(api.timings.pinPulse + 4200);
  assert.equal(fixture.panel.getAttribute('data-trip-proposal-state'), 'ready', 'The reveal runs to its finish inside the takeover.');

  // Escape returns the whole journey to the pin.
  world.document.dispatchEvent({ type: 'keydown', key: 'Escape', preventDefault() { this.defaultPrevented = true; } });
  assert.equal(fixture.panel.hidden, true, 'Escape closes the takeover.');
  assert.equal(api.takeoverOpen(), false, 'The takeover reports closed.');
  assert.equal(world.documentElement.classList.contains('is-trip-takeover-open'), false, 'Escape releases the scroll lock.');
  assert.equal(pin.focused, true, 'Escape returns focus to the pin the journey began on.');
  assert.equal(world.timers.size, 0, 'Nothing keeps ticking after Escape.');
}

{
  // A second selection published while the takeover is already open must
  // not restart the run, and a selection without a panel resets the card.
  const world = buildTakeoverWorld();
  const { api, fixture, drainUntil, globe, card } = world;
  world.document.dispatchEvent({
    type: 'travelglobe:select',
    target: globe,
    detail: { selectionKind: 'destination', nearestDestination: 'budapest', pinActivated: true, inputType: 'pointer' }
  });
  drainUntil(api.timings.pinPulse + 4200);
  const stateBefore = fixture.panel.getAttribute('data-trip-proposal-state');
  world.document.dispatchEvent({
    type: 'travelglobe:select',
    target: globe,
    detail: { selectionKind: 'destination', nearestDestination: 'budapest', pinActivated: true, inputType: 'pointer' }
  });
  drainUntil(api.timings.pinPulse * 2 + 8400);
  assert.equal(fixture.panel.getAttribute('data-trip-proposal-state'), stateBefore, 'An open takeover ignores a repeat activation of the same pin.');
  api.closePanel();
  world.document.dispatchEvent({
    type: 'travelglobe:select',
    target: globe,
    detail: { selectionKind: 'destination', nearestDestination: 'athens', pinActivated: true, inputType: 'pointer' }
  });
  assert.equal(card.getAttribute('data-trip-proposal-card-mode'), null, 'A selection without a panel returns the card to its full self.');
  assert.equal(api.takeoverOpen(), false, 'No panel, no takeover.');
}

{
  // Keyboard focus cannot leave the open stage: Tab from the last control
  // wraps to the first, Shift+Tab from the first wraps to the last.
  const world = buildTakeoverWorld();
  const { api, fixture, pin } = world;
  api.openPanel(fixture.panel, pin);
  const takeoverRoot = world.body.querySelector('[data-trip-takeover]');
  const back = takeoverRoot.querySelector('[data-trip-takeover-back]');
  back.ownerDocumentStub = world.document;
  const focusables = [];
  (function collect(node) {
    if (!node || node.hidden) return;
    const tag = String(node.tagName || '').toUpperCase();
    if (tag === 'BUTTON' || (tag === 'A' && node.getAttribute?.('href'))) focusables.push(node);
    (node.children || []).forEach(collect);
  })(takeoverRoot);
  assert.ok(focusables.length >= 2, 'The stage must hold the chrome controls at minimum.');
  const last = focusables[focusables.length - 1];
  last.ownerDocumentStub = world.document;
  world.document.activeElement = last;
  let trapped = { type: 'keydown', key: 'Tab', shiftKey: false, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
  takeoverRoot.dispatchEvent(trapped);
  assert.equal(trapped.defaultPrevented, true, 'Tab off the end must be intercepted.');
  assert.equal(focusables[0].focused, true, 'Tab off the end wraps to the first control.');
  world.document.activeElement = focusables[0];
  trapped = { type: 'keydown', key: 'Tab', shiftKey: true, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
  takeoverRoot.dispatchEvent(trapped);
  assert.equal(trapped.defaultPrevented, true, 'Shift+Tab off the start must be intercepted.');
  assert.equal(last.focused, true, 'Shift+Tab off the start wraps to the last control.');
  api.closePanel();
}

{
  // The tap sound: nothing is ever constructed at load, the click exists
  // only behind the gesture paths, reduced motion silences it, and the mute
  // choice persists on the device across page views.
  const storage = new Map();
  const world = buildTakeoverWorld({ storage });
  const { api, fixture, pin } = world;
  assert.equal(world.audio.constructed, 0, 'Loading the script must never construct an audio context.');
  assert.equal(api.tapSound('tap'), true, 'A gesture may sound the click.');
  assert.equal(world.audio.constructed, 1, 'The first click constructs the one context.');
  assert.equal(world.audio.starts, 1, 'The click starts exactly one oscillator.');
  api.openPanel(fixture.panel, pin);
  assert.equal(world.audio.constructed, 1, 'The context is reused, never rebuilt.');
  assert.equal(world.audio.starts, 2, 'Opening the takeover sounds its own click.');
  api.closePanel();

  assert.equal(api.setSoundMuted(true), true, 'The toggle can mute.');
  assert.equal(storage.get('traVelSoundMuted'), 'true', 'The mute choice persists on the device.');
  assert.equal(api.tapSound('tap'), false, 'A muted device stays silent.');
  assert.equal(world.audio.starts, 2, 'No oscillator starts while muted.');

  const nextView = buildTakeoverWorld({ storage });
  assert.equal(nextView.api.soundMuted(), true, 'The next page view reads the persisted mute.');
  assert.equal(nextView.api.tapSound('tap'), false, 'The persisted mute silences the next page view.');
  assert.equal(nextView.audio.constructed, 0, 'A muted view never constructs an audio context.');

  const reduced = buildTakeoverWorld({ reducedMotion: true });
  assert.equal(reduced.api.tapSound('tap'), false, 'Reduced motion is an unconditional mute.');
  assert.equal(reduced.audio.constructed, 0, 'Reduced motion never constructs an audio context.');
}

// --- Part D: the ambient price-pop gates in globe-3d.js -------------------
{
  const documentStub = {
    readyState: 'complete',
    visibilityState: 'visible',
    documentElement: { classList: new StubClassList() },
    addEventListener() {},
    querySelectorAll: () => [],
    querySelector: () => null
  };
  const windowStub = {
    matchMedia: () => ({ matches: false }),
    setTimeout: () => 1,
    clearTimeout() {},
    requestAnimationFrame: () => 1,
    cancelAnimationFrame() {}
  };
  const context = vm.createContext({
    console,
    document: documentStub,
    window: windowStub,
    navigator: {},
    CSS: { escape: value => String(value) },
    Image: class {},
    CustomEvent: class {},
    IntersectionObserver: class { observe() {} },
    ResizeObserver: class { observe() {} }
  });
  new vm.Script(globeSource, { filename: 'globe-3d.js' }).runInContext(context);
  const pricePop = windowStub.traVelGlobe3D?.pricePop;
  assert.ok(pricePop, 'globe-3d.js must expose its price-pop gate for this harness.');

  const alive = { failed: false, visible: true, documentVisible: true, reducedMotion: false, takeoverOpen: false };
  assert.equal(pricePop.eligible(alive), true, 'A healthy on-screen globe may pop.');
  assert.equal(pricePop.eligible({ ...alive, takeoverOpen: true }), false, 'An open takeover pauses the pops.');
  assert.equal(pricePop.eligible({ ...alive, reducedMotion: true }), false, 'Reduced motion pauses the pops.');
  assert.equal(pricePop.eligible({ ...alive, documentVisible: false }), false, 'A hidden tab pauses the pops.');
  assert.equal(pricePop.eligible({ ...alive, visible: false }), false, 'An off-screen globe pauses the pops.');
  assert.equal(pricePop.eligible({ ...alive, failed: true }), false, 'The static fallback never pops.');

  assert.equal(pricePop.delay(0), 4000, 'The shortest pop delay is four seconds.');
  assert.equal(pricePop.delay(1), 7000, 'The longest pop delay is seven seconds.');
  assert.ok(pricePop.delay(0.5) >= 4000 && pricePop.delay(0.5) <= 7000, 'Every delay stays inside the four to seven second window.');
  assert.equal(pricePop.durationMs, 1500, 'One pop lives about a second and a half.');
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
  'נמצאה טיסה: מ-%s לנוסע, הלוך ושוב.',
  'התחילו',
  "sprintf( __( 'נמצאה טיסה: מ-%s לנוסע, הלוך ושוב.', 'tra-vel-v2' ), $tiers[0]['unit_label'] )",
]);
requireMarkers(panelTemplateSource, 'template-parts/proposal-panel.php', [
  "'' !== $addon['verdict_line'] ? ' data-trip-proposal-addon-verdict=",
  'data-trip-proposal-check-prices-line',
  'data-trip-proposal-check-dates-line',
  'data-trip-proposal-flight-found-line',
  'data-trip-proposal-start-label',
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

// The takeover's own laws in the sources: the scroll lock is one overflow
// class, the stage is fixed above the overlay header, the pops and the
// pulse are pure CSS, and reduced motion silences all of it.
requireMarkers(appCssSource, 'assets/css/app.css takeover contract', [
  'html.is-trip-takeover-open { overflow: hidden; }',
  '.trip-takeover { position: fixed; inset: 0; z-index: 400;',
  '.trip-takeover[hidden] { display: none !important; }',
  '.trip-takeover-stage .trip-proposal { width: 100%; max-width: none;',
  '.globe-webgl .price-pin.is-proposal-pulse',
  '.globe-webgl .price-pin.is-price-pop { z-index: 8; animation: tripPricePop 1.5s',
  '.trip-proposal-step-ring',
  '@keyframes tripStepRingSpin',
  '@keyframes tripStepRingSnap',
  '.trip-takeover,.trip-takeover *,.globe-webgl .price-pin.is-proposal-pulse { animation: none !important; transition: none !important; }',
  '.globe-webgl .price-pin.is-price-pop { animation: none !important; }',
]);
const takeoverZIndex = Number((/\.trip-takeover \{ position: fixed; inset: 0; z-index: (\d+);/.exec(appCssSource) || [])[1]);
const headerZIndex = Number((/\.site-header\.overlay \{ [^}]*z-index: (\d+);/.exec(appCssSource) || [])[1]);
if (!Number.isFinite(takeoverZIndex) || !Number.isFinite(headerZIndex) || takeoverZIndex <= headerZIndex) {
  failures.push('The takeover must stack above the overlay site header.');
}
requireMarkers(globeSource, 'globe-3d.js price-pop and texture contract', [
  "classList.contains('is-trip-takeover-open')",
  'takeoverOpen: proposalTakeoverOpen()',
  'stopPricePops();',
  "classList.add('is-price-pop')",
  'root.dataset.textureHd',
  'gl.MAX_TEXTURE_SIZE',
  'kept its base texture',
]);
requireMarkers(frontPageSource, 'front-page.php texture declaration', [
  "data-texture-hd=\"<?php echo esc_url( tra_vel_v2_asset_uri( 'images/earth-blue-marble-4096.jpg' ) ); ?>\"",
]);

// The reveal's own laws: no requests, no navigation, no submission, no
// scroll traps, no audio files, no commerce vocabulary, no em or en
// dashes, and the codenames nowhere.
for (const [pattern, reason] of [
  [/fetch\s*\(/, 'a network request'],
  [/XMLHttpRequest/, 'a network request'],
  [/\.submit\s*\(/, 'a form submission'],
  [/location\.(?:assign|replace|href\s*=)/, 'a navigation'],
  [/window\.open/, 'a popup navigation'],
  [/addEventListener\(\s*['"](?:wheel|mousewheel|scroll|touchmove)['"]/, 'a scroll trap'],
  [/\.(?:mp3|ogg|wav|m4a)\b/i, 'an audio file'],
  [/createElement\(\s*['"]audio['"]/i, 'an audio element'],
]) {
  if (pattern.test(proposalJsSource)) failures.push(`trip-proposal.js contains ${reason}.`);
}
if ((proposalJsSource.match(/new Context\(\)/g) || []).length !== 1 || !proposalJsSource.includes('window.AudioContext || window.webkitAudioContext')) {
  failures.push('The one audio context may be constructed in exactly one place, inside the gesture-driven click.');
}
for (const [file, source] of [['trip-proposal.js', proposalJsSource], ['inc/proposal.php', proposalPhpSource], ['template-parts/proposal-panel.php', panelTemplateSource]]) {
  if (source.includes('—') || source.includes('–')) failures.push(`${file} contains an em or en dash.`);
  if (/movie|cinema/i.test(source)) failures.push(`${file} exposes an internal release codename.`);
  if (/schema\.org|itemtype|itemprop|"@type"/.test(source)) failures.push(`${file} carries structured commerce markup the money rules forbid.`);
}

if (failures.length) {
  console.error('Trip proposal reveal validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Trip proposal reveal harness passed (pin tap publishes the card selection once, keyboard and pointer input types, no double pipeline, server-authored typewriter behind spinning rings, double-gated add-on verdicts, verdict last, budget kept, skip and reduced motion land finished and instant, takeover lifts and re-docks the one panel node, scroll lock is one class, focus trapped and returned to the pin, staged tier-card landing only on natural completion, tap sound gesture-only with persistent mute, price pops pause for takeover, reduced motion, hidden tab and fallback, no requests or navigation or submission).');
