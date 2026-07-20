// Theme 1.26.0 one-click-first behavioral harness.
//
// Loads the production client into a stub DOM and drives the tap-first
// planning surfaces end to end: the range-calendar reducer and its commit
// into the native date inputs, preset ranges and the flexible-dates field,
// party stepper math with progressive child-age disclosure, destination
// chip-to-select synchronization, the open-ended (anywhere) handoff URL, and
// the retargeted dive-store links. It also proves the homepage hero primary
// CTA never routes into the planner conversation.
// Run with: node scripts/ci/validate-one-click-behavior.mjs
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import vm from 'node:vm';

const repoRoot = resolve(import.meta.dirname, '..', '..');
const appPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'js', 'app.js');
const frontPagePath = join(repoRoot, 'theme', 'tra-vel-v2', 'front-page.php');
const appSource = readFileSync(appPath, 'utf8');
const frontPageSource = readFileSync(frontPagePath, 'utf8');

class FakeClassList {
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

class FakeEvent {
  constructor(type, options = {}) {
    this.type = type;
    this.bubbles = options.bubbles === true;
    this.detail = options.detail;
  }
  preventDefault() { this.defaultPrevented = true; }
}

class FakeElement {
  constructor(tagName = 'div') {
    this.tagName = String(tagName).toUpperCase();
    this.dataset = {};
    this.hidden = false;
    this.disabled = false;
    this.readOnly = false;
    this.open = false;
    this.value = '';
    this.name = '';
    this.type = '';
    this.href = '';
    this.className = '';
    this.id = '';
    this.tabIndex = 0;
    this.action = '';
    this.classList = new FakeClassList();
    this.attributes = new Map();
    this.queries = new Map();
    this.children = [];
    this.options = [];
    this.selectedOptions = [];
    this.listeners = new Map();
    this.dispatched = [];
    this._textContent = '';
  }
  get textContent() { return this._textContent; }
  set textContent(value) { this._textContent = String(value); this.children = []; }
  closest() { return null; }
  focus() { this.focused = true; }
  scrollIntoView() {}
  setAttribute(name, value) {
    this.attributes.set(name, String(value));
    if (name.startsWith('data-')) {
      const key = name.slice(5).replace(/-([a-z])/g, (m, letter) => letter.toUpperCase());
      this.dataset[key] = String(value);
    }
  }
  getAttribute(name) { return this.attributes.get(name) ?? null; }
  removeAttribute(name) { this.attributes.delete(name); }
  append(...children) { this.children.push(...children); }
  replaceChildren(...children) { this.children = children; this._textContent = ''; }
  insertBefore(node, reference) {
    const index = this.children.indexOf(reference);
    if (index < 0) this.children.push(node); else this.children.splice(index, 0, node);
    return node;
  }
  addEventListener(type, callback) {
    const handlers = this.listeners.get(type) || [];
    handlers.push(callback);
    this.listeners.set(type, handlers);
  }
  dispatchEvent(event) {
    this.dispatched.push(event.type);
    (this.listeners.get(event.type) || []).forEach(callback => callback(event));
    return true;
  }
  matchesAttributeSelector(selector) {
    const match = /^\[([a-z0-9-]+)(?:="([^"]*)")?\]$/i.exec(selector.trim());
    if (!match) return false;
    if (!this.attributes.has(match[1])) return false;
    return match[2] === undefined || this.attributes.get(match[1]) === match[2];
  }
  collectDescendants(out = []) {
    for (const child of this.children) {
      if (!child || typeof child === 'string') continue;
      out.push(child);
      child.collectDescendants?.(out);
    }
    return out;
  }
  querySelector(selector) {
    if (this.queries.has(selector)) return this.queries.get(selector);
    return this.collectDescendants().find(node => node.matchesAttributeSelector?.(selector)) || null;
  }
  querySelectorAll(selector) {
    if (this.queries.has(selector)) {
      const stored = this.queries.get(selector);
      return Array.isArray(stored) ? stored : [stored];
    }
    return this.collectDescendants().filter(node => node.matchesAttributeSelector?.(selector));
  }
}

const documentQueries = new Map();
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
  querySelector(selector) { return documentQueries.get(selector) || null; },
  querySelectorAll(selector) { return documentQueries.has(selector) ? [documentQueries.get(selector)] : []; },
  getElementById() { return null; },
  createElement(tagName) { return new FakeElement(tagName); },
  createTextNode(value) { return String(value); }
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
const context = vm.createContext({
  AbortController,
  CSS: { escape: value => String(value) },
  URL,
  URLSearchParams,
  console,
  Event: FakeEvent,
  CustomEvent: class CustomEvent extends FakeEvent {},
  document: documentStub,
  navigator: {},
  window: windowStub,
  setTimeout: windowStub.setTimeout,
  clearTimeout: windowStub.clearTimeout
});
new vm.Script(appSource, { filename: appPath }).runInContext(context);
const runtime = expression => JSON.parse(vm.runInContext(`JSON.stringify(${expression})`, context));

// --- Calendar reducer: start then end, restart, minimum, same-day ----------
context.harnessRange = { start: '', end: '' };
let range = runtime(`tripCalendarNextRange({start:'', end:''}, '2026-09-01', {sameDayAllowed:false, minIso:'2026-08-01'})`);
assert.deepEqual({ start: range.start, end: range.end, complete: range.complete }, { start: '2026-09-01', end: '', complete: false },
  'The first tap must open a pending range without completing it.');
range = runtime(`tripCalendarNextRange({start:'2026-09-01', end:''}, '2026-09-05', {sameDayAllowed:false, minIso:'2026-08-01'})`);
assert.deepEqual({ start: range.start, end: range.end, complete: range.complete }, { start: '2026-09-01', end: '2026-09-05', complete: true },
  'Tapping a later day must complete the range so the picker can close.');
range = runtime(`tripCalendarNextRange({start:'2026-09-10', end:''}, '2026-09-05', {sameDayAllowed:false, minIso:'2026-08-01'})`);
assert.deepEqual({ start: range.start, end: range.end, complete: range.complete }, { start: '2026-09-05', end: '', complete: false },
  'Tapping an earlier day must restart the range at that day.');
range = runtime(`tripCalendarNextRange({start:'2026-09-01', end:'2026-09-05'}, '2026-09-20', {sameDayAllowed:false, minIso:'2026-08-01'})`);
assert.deepEqual({ start: range.start, end: range.end, complete: range.complete }, { start: '2026-09-20', end: '', complete: false },
  'Tapping after a completed range must start a fresh range.');
range = runtime(`tripCalendarNextRange({start:'2026-09-01', end:''}, '2026-08-20', {sameDayAllowed:false, minIso:'2026-08-25'})`);
assert.equal(range.changed, false, 'A day before the minimum must be rejected without changing the range.');
assert.equal(runtime(`tripCalendarNextRange({start:'2026-09-01', end:''}, '2026-09-01', {sameDayAllowed:false, minIso:''}).complete`), false,
  'Same-day ranges stay incomplete for flights, hotels, and packages.');
assert.equal(runtime(`tripCalendarNextRange({start:'2026-09-01', end:''}, '2026-09-01', {sameDayAllowed:true, minIso:''}).complete`), true,
  'Travel insurance may cover a valid same-day trip.');

// --- Preset ranges: weekend, week, two weeks ---------------------------------
assert.deepEqual(runtime(`tripCalendarPresetRange('weekend', '2026-08-03', '')`), { start: '2026-08-06', end: '2026-08-08', complete: true },
  'From a Monday the weekend preset must land on Thursday through Saturday.');
assert.deepEqual(runtime(`tripCalendarPresetRange('weekend', '2026-08-06', '')`), { start: '2026-08-06', end: '2026-08-08', complete: true },
  'On a Thursday the weekend preset must start the same day.');
assert.deepEqual(runtime(`tripCalendarPresetRange('week', '2026-08-03', '2026-08-10')`), { start: '2026-08-10', end: '2026-08-17', complete: true },
  'The week preset must keep a valid chosen start date.');
assert.deepEqual(runtime(`tripCalendarPresetRange('two_weeks', '2026-08-03', '')`), { start: '2026-08-03', end: '2026-08-17', complete: true },
  'The two-week preset must span fourteen days from the base date.');
assert.equal(runtime(`tripCalendarPresetRange('bogus', '2026-08-03', '')`), null, 'Unknown presets must fail closed.');

// --- Range commit writes the native value holders and fires their events ----
const startInput = new FakeElement('input');
startInput.name = 'departure_date';
startInput.value = '2026-10-01';
const endInput = new FakeElement('input');
endInput.name = 'return_date';
endInput.value = '2026-10-05';
context.harnessStart = startInput;
context.harnessEnd = endInput;
assert.equal(vm.runInContext(`commitTripCalendarRange({start: harnessStart, end: harnessEnd}, {start:'2026-11-02', end:'', complete:false})`, context), false,
  'An incomplete range must never write the native inputs.');
assert.equal(startInput.value, '2026-10-01', 'A rejected commit must leave the departure value untouched.');
assert.equal(startInput.dispatched.length, 0, 'A rejected commit must not fire synthetic events.');
assert.equal(vm.runInContext(`commitTripCalendarRange({start: harnessStart, end: harnessEnd}, {start:'2026-11-02', end:'2026-11-09', complete:true})`, context), true,
  'A completed range must commit into the native inputs.');
assert.equal(startInput.value, '2026-11-02');
assert.equal(endInput.value, '2026-11-09');
assert.deepEqual(startInput.dispatched, ['input', 'change'], 'The departure holder must announce its new value to the existing form logic.');
assert.deepEqual(endInput.dispatched, ['input', 'change'], 'The return holder must announce its new value to the existing form logic.');

// --- Flexible-dates field: created hidden, enabled only when chosen ---------
const flexibleForm = new FakeElement('form');
context.harnessFlexForm = flexibleForm;
vm.runInContext('harnessFlexField = ensureTripFlexibilityField(harnessFlexForm)', context);
const flexField = flexibleForm.children[0];
assert.ok(flexField, 'The flexibility field must be created on demand.');
assert.equal(flexField.type, 'hidden');
assert.equal(flexField.name, 'flexibility');
assert.equal(flexField.disabled, true, 'An unchosen flexibility field must stay disabled so it never pollutes a GET.');
assert.equal(vm.runInContext('ensureTripFlexibilityField(harnessFlexForm) === harnessFlexField', context), true,
  'The flexibility field must be created exactly once per form.');
vm.runInContext('setTripFlexibility(harnessFlexField, true)', context);
assert.deepEqual({ value: flexField.value, disabled: flexField.disabled }, { value: '3', disabled: false },
  'Choosing flexible dates must arm the hidden field with the three-day window.');
vm.runInContext('setTripFlexibility(harnessFlexField, false)', context);
assert.deepEqual({ value: flexField.value, disabled: flexField.disabled }, { value: '', disabled: true },
  'Removing the flexible choice must fully disarm the hidden field.');

// --- Anywhere flow: open-ended handoff without an invented destination ------
function homeFormFixture(destinationValue) {
  const form = new FakeElement('form');
  form.dataset = { mapAction: '/travel-map/', productKind: 'package' };
  form.action = 'https://tra-vel.co.il/packages/';
  const controls = {
    '[data-home-origin-wrap] input': { name: 'origin', value: 'TLV', disabled: false },
    '[data-home-destination]': { name: 'destination', value: destinationValue, disabled: false },
    '[data-home-departure]': { name: 'departure_date', value: '2026-10-01', disabled: false },
    '[data-home-return]': { name: 'return_date', value: '2026-10-08', disabled: false },
    '[data-home-adults]': { name: 'adults', value: '2', disabled: false },
    '[data-home-children]': { name: 'children', value: '1', disabled: false },
    '[data-home-rooms]': { name: 'rooms', value: '1', disabled: false }
  };
  Object.entries(controls).forEach(([selector, control]) => form.queries.set(selector, control));
  return form;
}
const anywhereForm = homeFormFixture('anywhere');
context.harnessHomeForm = anywhereForm;
let anywhereQuery = Object.fromEntries(new URL(vm.runInContext('homeSearchNavigationUrl(harnessHomeForm, true).toString()', context)).searchParams);
assert.equal(anywhereQuery.destination_mode, 'anywhere', 'The open-ended flow must declare itself instead of inventing a destination.');
assert.equal(anywhereQuery.product, 'package', 'The open-ended flow must carry the chosen product.');
assert.equal('destination' in anywhereQuery, false, 'The open-ended flow must never fabricate a destination value.');
assert.equal('flexibility' in anywhereQuery, false, 'Without a flexible choice no flexibility parameter may appear.');
anywhereForm.queries.set('[data-trip-flexibility]', { value: '3', disabled: false });
anywhereQuery = Object.fromEntries(new URL(vm.runInContext('homeSearchNavigationUrl(harnessHomeForm, true).toString()', context)).searchParams);
assert.equal(anywhereQuery.flexibility, '3', 'A chosen flexible window must travel with the open-ended handoff.');
const directForm = homeFormFixture('BUD');
directForm.queries.set('[data-trip-flexibility]', { value: '3', disabled: false });
context.harnessHomeForm = directForm;
const directUrl = new URL(vm.runInContext('homeSearchNavigationUrl(harnessHomeForm, false).toString()', context));
assert.equal(directUrl.pathname, '/packages/');
assert.equal(directUrl.searchParams.get('destination'), 'BUD');
assert.equal(directUrl.searchParams.get('flexibility'), '3', 'A chosen flexible window must travel with the comparison handoff.');

// --- Party stepper math and the canonical pill summary -----------------------
assert.equal(runtime(`partyStepperClamp('adults', 7)`), 6, 'Adults must clamp at six.');
assert.equal(runtime(`partyStepperClamp('adults', 0)`), 1, 'At least one adult always travels.');
assert.equal(runtime(`partyStepperClamp('children', -2)`), 0, 'Children must clamp at zero.');
assert.equal(runtime(`partyStepperClamp('children', 9)`), 4, 'Children must clamp at four.');
assert.equal(runtime(`partyStepperClamp('rooms', 'not-a-number')`), 1, 'Broken room counts must fall back to one room.');
assert.equal(runtime(`partyStepperClamp('rooms', 5)`), 3, 'Rooms must clamp at three.');
assert.equal(runtime(`partyStepperSummary(2, 0, 1)`), '2 מבוגרים · ללא ילדים · חדר 1', 'The pill must read the canonical summary.');
assert.equal(runtime(`partyStepperSummary(1, 1, null)`), 'מבוגר אחד · ילד אחד', 'Products without rooms must omit the room segment.');
assert.equal(runtime(`partyStepperSummary(3, 2, 2)`), '3 מבוגרים · 2 ילדים · 2 חדרים');

// --- Child-age disclosure appears only when children exist -------------------
const agesHost = new FakeElement('div');
agesHost.hidden = true;
context.harnessAges = agesHost;
assert.equal(vm.runInContext('renderPartyChildAges(harnessAges, 2)', context), 2, 'Two children must produce exactly two age selects.');
assert.equal(agesHost.hidden, false, 'Age selects must be disclosed while children are present.');
let ageSelects = agesHost.querySelectorAll('[data-party-child-age]');
assert.equal(ageSelects.length, 2);
assert.equal(ageSelects[0].getAttribute('aria-label'), 'גיל ילד 1', 'Every age select needs its own accessible name.');
ageSelects[0].value = '5';
ageSelects[1].value = '9';
vm.runInContext('renderPartyChildAges(harnessAges, 3)', context);
ageSelects = agesHost.querySelectorAll('[data-party-child-age]');
assert.equal(ageSelects.length, 3, 'Raising the child count must add an age select.');
assert.deepEqual([ageSelects[0].value, ageSelects[1].value], ['5', '9'], 'Existing age choices must survive a count change.');
assert.equal(vm.runInContext('renderPartyChildAges(harnessAges, 0)', context), 0);
assert.equal(agesHost.hidden, true, 'Without children the age disclosure must fully close.');
assert.equal(agesHost.querySelectorAll('[data-party-child-age]').length, 0, 'Without children no age selects may remain.');
const agesForm = new FakeElement('form');
const agesField = { value: '' };
agesForm.queries.set('input[name="child_ages"]', agesField);
context.harnessAgesForm = agesForm;
vm.runInContext('renderPartyChildAges(harnessAges, 2)', context);
agesHost.querySelectorAll('[data-party-child-age]')[0].value = '4';
agesHost.querySelectorAll('[data-party-child-age]')[1].value = '11';
assert.equal(vm.runInContext('syncPartyChildAgesField(harnessAgesForm, harnessAges)', context), true,
  'Ages must feed an existing form field when one exists.');
assert.equal(agesField.value, '4,11');
assert.equal(vm.runInContext('syncPartyChildAgesField(document.createElement("form"), harnessAges)', context), false,
  'Without an existing child-age field the ages must stay local UI and be omitted from the GET.');

// --- Destination chips synchronize the canonical select ----------------------
function optionFixture(value, slug, code) {
  return { value, dataset: { slug, code } };
}
const destinationSelect = new FakeElement('select');
destinationSelect.options = [
  optionFixture('anywhere', 'anywhere', 'anywhere'),
  optionFixture('BUD', 'budapest', 'BUD'),
  optionFixture('BKK', 'bangkok', 'BKK')
];
destinationSelect.value = 'BKK';
const chipFixtures = ['budapest', 'bangkok', 'anywhere'].map(slug => {
  const chip = new FakeElement('button');
  chip.dataset = { chipSlug: slug, chipCode: slug === 'budapest' ? 'BUD' : (slug === 'bangkok' ? 'BKK' : 'anywhere') };
  return chip;
});
context.harnessSelect = destinationSelect;
context.harnessChips = chipFixtures;
context.harnessChip = chipFixtures[0];
assert.equal(vm.runInContext('applyHomeDestinationChip(harnessSelect, harnessChip)', context), true);
assert.equal(destinationSelect.value, 'BUD', 'Tapping the Budapest chip must select the Budapest option.');
assert.deepEqual(destinationSelect.dispatched, ['input', 'change'], 'The chip tap must announce the select change to the existing dock logic.');
vm.runInContext('syncHomeDestinationChipStates(harnessSelect, harnessChips)', context);
assert.deepEqual(chipFixtures.map(chip => chip.getAttribute('aria-pressed')), ['true', 'false', 'false'],
  'Exactly the matching chip must present a selected state.');
context.harnessChip = chipFixtures[2];
vm.runInContext('applyHomeDestinationChip(harnessSelect, harnessChip)', context);
assert.equal(destinationSelect.value, 'anywhere', 'The anywhere chip must select the open-ended option.');
vm.runInContext('syncHomeDestinationChipStates(harnessSelect, harnessChips)', context);
assert.deepEqual(chipFixtures.map(chip => chip.getAttribute('aria-pressed')), ['false', 'false', 'true']);
const unknownChip = new FakeElement('button');
unknownChip.dataset = { chipSlug: 'atlantis' };
context.harnessChip = unknownChip;
assert.equal(vm.runInContext('applyHomeDestinationChip(harnessSelect, harnessChip)', context), false,
  'A chip without a matching option must fail closed.');
assert.equal(destinationSelect.value, 'anywhere', 'A failed chip tap must not move the select.');

// --- Dive-store destination chips open pickers, not the conversation --------
const diveLinks = runtime(`diveDestinationServiceLinks({id:'budapest', airportCode:'BUD'})`);
for (const scope of ['activities', 'dining', 'connectivity', 'equipment']) {
  const link = new URL(diveLinks[scope]);
  assert.equal(link.pathname, '/travel-map/', `The ${scope} dive chip must open the travel map.`);
  assert.equal(link.searchParams.get('destination'), 'budapest', `The ${scope} dive chip must carry its destination.`);
  assert.equal(link.searchParams.get('scope'), scope, `The ${scope} dive chip must carry its scope for the plan module focus.`);
  assert.equal(link.pathname.includes('ai-planner'), false);
}
assert.ok(new URL(diveLinks.flights).pathname === '/flights/' && diveLinks.flights.includes('destination=BUD'),
  'The flights dive chip must keep its existing product link.');
assert.ok(new URL(diveLinks.accommodation).pathname === '/hotels/', 'The hotels dive chip must keep its existing product link.');
assert.ok(new URL(diveLinks.transfers).pathname === '/packages/', 'The transfers dive chip must keep its existing product link.');
assert.ok(new URL(diveLinks.insurance).pathname === '/travel-insurance/', 'The insurance dive chip must keep its existing product link.');
assert.equal(Object.values(diveLinks).some(href => String(href).includes('/ai-planner/')), false,
  'No destination dive chip may route into the planner conversation.');
assert.match(appSource, /function initMapPlanScopeFocus/, 'The map must be able to open the module a dive chip asked for.');
assert.match(appSource, /module\.open = moduleKeys\.includes\(module\.dataset\.planModule\)/,
  'The map scope focus must open exactly the requested plan modules.');

// --- Homepage hero: the primary CTA never opens the planner ------------------
const heroBlock = frontPageSource.match(/<div class="hero-agent-actions">[\s\S]*?<\/div>/)?.[0] || '';
assert.ok(heroBlock, 'The hero action block must exist.');
const heroPrimary = heroBlock.match(/<a class="hero-compare-cta"[^>]*>/)?.[0] || '';
assert.ok(heroPrimary.includes('href="#search"'), 'The hero primary CTA must scroll to the search dock.');
assert.equal(heroPrimary.includes('ai-planner'), false, 'The hero primary CTA must not link to the planner.');
assert.ok(heroBlock.includes('השוו טיסה ומלון'), 'The hero primary CTA must keep its honest comparison label.');
const heroSurprise = heroBlock.match(/<a class="surprise-cta"[^>]*>/)?.[0] || '';
assert.ok(heroSurprise.includes('data-home-surprise'), 'The surprise trigger must keep its local spin controller hook.');
assert.equal(heroSurprise.includes('ai-planner'), false, 'The surprise fallback must not route into the planner.');
assert.ok(heroBlock.indexOf('hero-compare-cta') < heroBlock.indexOf('surprise-cta'),
  'The comparison CTA must be the first visible hero action.');
assert.ok(frontPageSource.includes('data-hero-planner-quiet') && frontPageSource.includes('או פשוט תארו לנו במילים'),
  'The planner entry must remain available as an honest quiet secondary link.');

console.log('Tra-Vel one-click behavioral harness passed (calendar commit, presets, flexibility, anywhere URL, stepper math, child-age disclosure, chip sync, dive retargets, hero CTA).');
