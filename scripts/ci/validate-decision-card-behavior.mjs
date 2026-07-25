import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

/**
 * Behavioural contract tests for the decision card client enhancement.
 *
 * The real assets/js/decision-card.js is executed against a minimal DOM, so
 * every assertion here is a statement about shipped behaviour: the stepper
 * only multiplies, it never leaves the page, it stays inside one to six
 * travelers, it announces every recalculated total, and the opening sequence
 * both finishes in under two seconds and disappears entirely when a visitor
 * asks for reduced motion.
 */

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const cardScriptPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'js', 'decision-card.js');
const cardScript = readFileSync(cardScriptPath, 'utf8');

class FakeElement {
  constructor(tag, attributes = {}) {
    this.tagName = String(tag).toUpperCase();
    this.attributes = { ...attributes };
    this.children = [];
    this.parent = null;
    this.listeners = {};
    this.textContent = '';
    this.hidden = false;
    this.disabled = false;
  }

  append(...nodes) {
    for (const node of nodes) {
      node.parent = this;
      this.children.push(node);
    }
    return this;
  }

  getAttribute(name) {
    return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : null;
  }

  setAttribute(name, value) {
    this.attributes[name] = String(value);
  }

  addEventListener(type, handler) {
    (this.listeners[type] = this.listeners[type] || []).push(handler);
  }

  click() {
    if (this.disabled) return;
    for (const handler of this.listeners.click || []) handler({ type: 'click' });
  }

  descendants() {
    return this.children.flatMap(child => [child, ...child.descendants()]);
  }

  matches(selector) {
    const exact = selector.match(/^\[([a-z0-9-]+)="([^"]*)"\]$/i);
    if (exact) return this.getAttribute(exact[1]) === exact[2];
    const present = selector.match(/^\[([a-z0-9-]+)\]$/i);
    if (present) return this.getAttribute(present[1]) !== null;
    throw new Error(`Unsupported selector in harness: ${selector}`);
  }

  querySelectorAll(selector) {
    return this.descendants().filter(node => node.matches(selector));
  }

  querySelector(selector) {
    return this.querySelectorAll(selector)[0] || null;
  }

  closest(selector) {
    let node = this;
    while (node) {
      if (node.matches && node.matches(selector)) return node;
      node = node.parent;
    }
    return null;
  }
}

function buildCard(tiers, { travelers = 2, minimum = 1, maximum = 6, symbol = '₪' } = {}) {
  const card = new FakeElement('section', {
    'data-decision-card': '',
    'data-decision-card-travelers': String(travelers),
    'data-decision-card-min': String(minimum),
    'data-decision-card-max': String(maximum),
    'data-decision-card-party-one': 'לנוסע אחד',
    'data-decision-card-party-many': 'לכל %s הנוסעים'
  });

  const fill = new FakeElement('p', { 'data-decision-card-fill': '' });
  fill.hidden = true;
  ['מתאימים לכם: 2 נוסעים', 'תאריכים גמישים', 'יעד: ורשה'].forEach((text, index) => {
    const line = new FakeElement('span', index === 0
      ? { 'data-decision-card-fill-line': '', 'data-decision-card-fill-count': String(travelers) }
      : { 'data-decision-card-fill-line': '' });
    line.textContent = text;
    fill.append(line);
  });
  card.append(fill);

  const minus = new FakeElement('button', { 'data-decision-card-step': '-1' });
  const plus = new FakeElement('button', { 'data-decision-card-step': '1' });
  const value = new FakeElement('output', { 'data-decision-card-travelers-value': '' });
  value.textContent = String(travelers);
  card.append(minus, value, plus);

  const tierNodes = tiers.map(tier => {
    const item = new FakeElement('li', { 'data-decision-tier': tier.tier });
    const label = new FakeElement('span', { 'data-decision-tier-label': '' });
    label.textContent = tier.label;
    const total = new FakeElement('bdi', {
      'data-decision-tier-total': '',
      'data-decision-tier-unit': String(tier.unit),
      'data-decision-tier-symbol': symbol
    });
    total.textContent = symbol + String(tier.unit * travelers);
    const party = new FakeElement('span', { 'data-decision-tier-party': '' });
    party.textContent = `לכל ${travelers} הנוסעים`;
    item.append(label, total, party);
    return item;
  });
  card.append(...tierNodes);

  const live = new FakeElement('p', { 'data-decision-card-live': '' });
  card.append(live);

  return { card, minus, plus, value, live, fill };
}

function run({ reducedMotion = false, tiers, options } = {}) {
  const { card, minus, plus, value, live, fill } = buildCard(tiers, options);
  const root = new FakeElement('body');
  root.append(card);

  const timers = [];
  const windowStub = {
    matchMedia: query => ({ matches: reducedMotion && query.includes('prefers-reduced-motion') }),
    setTimeout: (handler, delay) => {
      timers.push({ handler, at: Number(delay) || 0 });
      return timers.length;
    }
  };
  const documentStub = {
    readyState: 'complete',
    addEventListener() {},
    querySelectorAll: selector => root.querySelectorAll(selector)
  };

  const context = vm.createContext({ window: windowStub, document: documentStub, Number, Math, String, Boolean });
  vm.runInContext(cardScript, context);

  const drain = () => {
    let guard = 0;
    while (timers.length) {
      if (guard++ > 500) throw new Error('The opening sequence never settles.');
      timers.shift().handler();
    }
  };

  return { card, minus, plus, value, live, fill, timers, drain, api: context.window.traVelDecisionCard };
}

const threeTiers = [
  { tier: 'value', label: 'הכי משתלם', unit: 463 },
  { tier: 'direct', label: 'ישיר', unit: 640 },
  { tier: 'fast', label: 'הכי מהיר', unit: 1720 }
];

// The stepper recomputes every total by multiplication alone, for one to six.
{
  const world = run({ reducedMotion: true, tiers: threeTiers });
  for (let travelers = 1; travelers <= 6; travelers += 1) {
    world.api.applyTravelers(world.card, travelers);
    const totals = world.card.querySelectorAll('[data-decision-tier-total]');
    assert.equal(totals.length, 3, 'Every real tier must keep its own total.');
    totals.forEach((node, index) => {
      const unit = threeTiers[index].unit;
      const expected = '₪' + String(unit * travelers).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      assert.equal(node.textContent, expected, `Tier ${threeTiers[index].tier} at ${travelers} travelers must print the fare times the party size.`);
    });
    const party = travelers === 1 ? 'לנוסע אחד' : `לכל ${travelers} הנוסעים`;
    for (const node of world.card.querySelectorAll('[data-decision-tier-party]')) {
      assert.equal(node.textContent, party, `The party description must match ${travelers} travelers.`);
    }
    assert.equal(world.value.textContent, String(travelers), 'The stepper must show the party size it priced.');
    assert.ok(world.live.textContent.startsWith(party), 'Every recalculation must be announced with the party it describes.');
    for (const tier of threeTiers) {
      assert.ok(world.live.textContent.includes(tier.label), `The announcement must name the ${tier.tier} tier.`);
    }
  }
  assert.ok(world.live.textContent.includes('₪10,320'), 'Grouped thousands must survive the announcement.');
}

// The stepper is bounded, and the bounds are reflected in the controls.
{
  const world = run({ reducedMotion: true, tiers: threeTiers });
  for (let click = 0; click < 12; click += 1) world.plus.click();
  assert.equal(world.card.getAttribute('data-decision-card-travelers'), '6', 'The party size must never pass six.');
  assert.equal(world.plus.disabled, true, 'The increase control must switch off at the ceiling.');
  for (let click = 0; click < 12; click += 1) world.minus.click();
  assert.equal(world.card.getAttribute('data-decision-card-travelers'), '1', 'The party size must never fall below one.');
  assert.equal(world.minus.disabled, true, 'The decrease control must switch off at the floor.');
  assert.equal(world.value.textContent, '1', 'The visible party size must follow the bound.');
  world.plus.click();
  assert.equal(world.card.getAttribute('data-decision-card-travelers'), '2', 'The stepper must recover from the floor.');
  const totals = world.card.querySelectorAll('[data-decision-tier-total]');
  assert.equal(totals[0].textContent, '₪926', 'A recovered party size must reprice by multiplication.');
}

// A single real tier behaves exactly the same and invents no companions.
{
  const world = run({ reducedMotion: true, tiers: [{ tier: 'value', label: 'הכי משתלם', unit: 511 }] });
  world.api.applyTravelers(world.card, 4);
  const totals = world.card.querySelectorAll('[data-decision-tier-total]');
  assert.equal(totals.length, 1, 'A single real record must stay a single tier on the client too.');
  assert.equal(totals[0].textContent, '₪2,044', 'The single tier total must be the fare times the party size.');
}

// Reduced motion shows the finished card instantly and never runs a sequence.
{
  const world = run({ reducedMotion: true, tiers: threeTiers });
  assert.equal(world.card.getAttribute('data-decision-card-state'), 'ready', 'Reduced motion must land on the finished card.');
  assert.equal(world.fill.hidden, true, 'Reduced motion must never reveal the opening sequence.');
  assert.equal(world.timers.length, 0, 'Reduced motion must not schedule any animation work.');
}

// The opening sequence finishes in under two seconds and restores the card.
{
  const world = run({ reducedMotion: false, tiers: threeTiers });
  assert.equal(world.card.getAttribute('data-decision-card-state'), 'filling', 'The card must open with the completion sequence.');
  assert.equal(world.fill.hidden, false, 'The completion sequence must be visible while it runs.');
  const latest = Math.max(...world.timers.map(timer => timer.at));
  assert.ok(latest < 2000, `The completion sequence must finish in under two seconds, scheduled ${latest}ms.`);
  world.drain();
  assert.equal(world.card.getAttribute('data-decision-card-state'), 'ready', 'The card must end on the finished state.');
  assert.equal(world.fill.hidden, true, 'The completion sequence must remove itself when it ends.');
  const lines = world.fill.querySelectorAll('[data-decision-card-fill-line]');
  assert.equal(lines.length, 3, 'The completion sequence must have exactly three lines.');
  for (const line of lines) {
    assert.equal(line.getAttribute('data-decision-card-fill-shown'), 'true', 'Every completion line must have been revealed.');
  }
  assert.equal(lines[0].textContent, 'מתאימים לכם: 2 נוסעים', 'The count must settle on the real default party size.');
  assert.equal(lines[1].textContent, 'תאריכים גמישים', 'The second completion line must stay unchanged.');
  assert.equal(lines[2].textContent, 'יעד: ורשה', 'The third completion line must name the real destination.');
}

// The sequence never changes what the card is worth.
{
  const world = run({ reducedMotion: false, tiers: threeTiers });
  world.drain();
  const totals = world.card.querySelectorAll('[data-decision-tier-total]');
  assert.equal(totals[0].textContent, '₪926', 'The opening sequence must leave the server rendered totals alone.');
  assert.equal(world.card.getAttribute('data-decision-card-travelers'), '2', 'The opening sequence must leave the party size alone.');
}

// The mechanism behind the sequence is never named to a visitor.
for (const forbidden of ['ניחוש', 'מנחש', 'guess', 'Guess']) {
  assert.ok(!cardScript.includes(forbidden), `The decision card script must never name the internal mechanism: ${forbidden}.`);
}

console.log('Tra-Vel decision card behavioural harness passed (multiplication only totals for 1 to 6, bounded keyboard stepper, announced recalculations, sub two second completion sequence, reduced motion instant finish).');
