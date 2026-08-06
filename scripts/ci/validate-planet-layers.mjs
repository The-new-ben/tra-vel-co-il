// Planet layers contract: every dataset that may render on the planet is
// machine-checked for the truthfulness laws it was gathered under. A layer
// that fails here never ships. Designed to move to scripts/ci/ verbatim with
// only LAYERS_DIR repointed at content/planet-layers/.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const LAYERS_DIR = join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'content', 'planet-layers');
const HEBREW = /[֐-׿]/;
const EM_OR_EN_DASH = /[–—]/;

function load(name) {
  const data = JSON.parse(readFileSync(join(LAYERS_DIR, name), 'utf8'));
  assert.ok(data && typeof data === 'object' && Array.isArray(data.items), `${name}: top level must be an envelope with items[]`);
  assert.ok(typeof data.vertical === 'string' && data.vertical.length, `${name}: vertical missing`);
  assert.match(String(data.generated_at), /^\d{4}-\d{2}-\d{2}$/, `${name}: generated_at must be a date`);
  return data;
}

function commonChecks(name, data, required, httpExceptions = new Set()) {
  const ids = new Set();
  for (const item of data.items) {
    for (const key of required) {
      assert.ok(key in item, `${name}/${item.id ?? '?'}: missing ${key}`);
    }
    assert.ok(!ids.has(item.id), `${name}: duplicate id ${item.id}`);
    ids.add(item.id);
    assert.ok(Number.isFinite(item.lat) && item.lat >= -90 && item.lat <= 90, `${name}/${item.id}: lat out of range`);
    assert.ok(Number.isFinite(item.lng) && item.lng >= -180 && item.lng <= 180, `${name}/${item.id}: lng out of range`);
    assert.ok(HEBREW.test(item.name_he), `${name}/${item.id}: name_he has no Hebrew`);
    assert.ok(HEBREW.test(item.one_line_he), `${name}/${item.id}: one_line_he has no Hebrew`);
    assert.ok(!EM_OR_EN_DASH.test(item.name_he + item.one_line_he), `${name}/${item.id}: em/en dash in public copy`);
    const urls = ['source_url', 'official_url'].filter((k) => k in item).map((k) => item[k]);
    for (const url of urls) {
      if (httpExceptions.has(item.id) && url.startsWith('http://')) continue;
      assert.ok(url.startsWith('https://'), `${name}/${item.id}: non-https source ${url}`);
    }
    assert.match(String(item.checked_at), /^\d{4}-\d{2}-\d{2}$/, `${name}/${item.id}: checked_at must be a date`);
  }
}

// --- diving ---------------------------------------------------------------
{
  const data = load('diving.json');
  assert.equal(data.vertical, 'diving');
  commonChecks('diving', data, ['id', 'name_he', 'name_en', 'lat', 'lng', 'precision', 'kind', 'difficulty', 'season', 'one_line_he', 'source_url', 'checked_at']);
  assert.ok(data.items.length >= 60, `diving: ${data.items.length} < 60`);
  for (const item of data.items) {
    assert.ok(['reef', 'wreck', 'wall', 'cave', 'muck', 'pelagic'].includes(item.kind), `diving/${item.id}: kind ${item.kind}`);
    assert.ok([1, 2, 3].includes(item.difficulty), `diving/${item.id}: difficulty ${item.difficulty}`);
    assert.ok(['site', 'area'].includes(item.precision), `diving/${item.id}: precision ${item.precision}`);
  }
  const redSea = data.items.filter((i) => i.lat > 20 && i.lat < 30.2 && i.lng > 32 && i.lng < 39);
  assert.ok(redSea.length >= 8, `diving: only ${redSea.length} Red Sea sites`);
  assert.ok(data.items.some((i) => i.id.includes('zenobia')), 'diving: Zenobia missing');
}

// --- cruises --------------------------------------------------------------
{
  const data = load('cruises.json');
  assert.equal(data.vertical, 'cruises');
  commonChecks('cruises', data, ['id', 'name_he', 'name_en', 'lat', 'lng', 'precision', 'role', 'haifa_reachable', 'haifa_evidence', 'region', 'season', 'one_line_he', 'source_url', 'checked_at']);
  assert.ok(data.items.length >= 40, `cruises: ${data.items.length} < 40`);
  for (const item of data.items) {
    assert.ok(['embark', 'call', 'both'].includes(item.role), `cruises/${item.id}: role ${item.role}`);
    assert.ok(['east-med', 'west-med', 'adriatic', 'aegean', 'atlantic-islands', 'northern-europe', 'caribbean', 'asia', 'other'].includes(item.region), `cruises/${item.id}: region ${item.region}`);
    // The badge law: a Haifa claim exists only with named itinerary evidence.
    if (item.haifa_reachable) {
      assert.ok(typeof item.haifa_evidence === 'string' && item.haifa_evidence.length > 10, `cruises/${item.id}: haifa_reachable without evidence`);
    }
  }
  const haifa = data.items.find((i) => i.id === 'haifa');
  assert.ok(haifa && haifa.role !== 'call', 'cruises: Haifa must be an embark/turnaround port');
}

// --- attractions ----------------------------------------------------------
{
  const data = load('attractions.json');
  assert.equal(data.vertical, 'attractions');
  const exceptions = new Set(data.http_exceptions ?? []);
  assert.ok(exceptions.size <= 4, 'attractions: http exception list must stay small and deliberate');
  if (exceptions.size) assert.ok(typeof data.http_exceptions_note === 'string' && data.http_exceptions_note.length > 20, 'attractions: http exceptions require a written justification');
  commonChecks('attractions', data, ['id', 'map_state', 'name_he', 'name_en', 'lat', 'lng', 'kind', 'family_friendly', 'typical_hours', 'one_line_he', 'official_url', 'checked_at'], exceptions);
  const STATES = ['larnaca', 'athens', 'budapest', 'warsaw', 'crete', 'prague', 'lisbon', 'vienna', 'dubai', 'bangkok', 'tokyo'];
  const per = Object.fromEntries(STATES.map((s) => [s, 0]));
  for (const item of data.items) {
    assert.ok(STATES.includes(item.map_state), `attractions/${item.id}: unknown map_state ${item.map_state}`);
    assert.ok(['museum', 'landmark', 'park', 'family', 'viewpoint', 'market', 'religious', 'experience'].includes(item.kind), `attractions/${item.id}: kind ${item.kind}`);
    assert.ok(typeof item.family_friendly === 'boolean', `attractions/${item.id}: family_friendly not boolean`);
    per[item.map_state] += 1;
  }
  for (const state of STATES) {
    assert.ok(per[state] >= 8, `attractions: ${state} has only ${per[state]} items (minimum 8)`);
  }
}

console.log('planet layers: all datasets pass the truthfulness contract.');
