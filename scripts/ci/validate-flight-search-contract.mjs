import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const dataPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'flight-search-demo.json');
const schemaPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'flight-search.schema.json');
const data = JSON.parse(readFileSync(dataPath, 'utf8'));
const schema = JSON.parse(readFileSync(schemaPath, 'utf8'));
const failures = [];

const fail = (message) => failures.push(message);
const sum = (object, excluded = []) => Object.entries(object)
  .filter(([key, value]) => !excluded.includes(key) && typeof value === 'number')
  .reduce((total, [, value]) => total + value, 0);

for (const key of schema.required || []) {
  if (!(key in data)) fail(`Missing schema-required property: ${key}`);
}

if (!/^\d+\.\d+\.\d+$/.test(data.contract_version || '')) fail('contract_version must be semantic versioning.');
if (data.data_mode !== 'demo') fail('The bundled fallback must remain explicitly demo data.');
if (data.currency !== 'USD') fail('The flight contract currency must remain USD until currency conversion is implemented.');
if (data.provider_status?.connected !== false) fail('The bundled provider must not claim a live connection.');
if (data.provider_status?.bookable !== false) fail('The bundled provider must remain non-bookable.');

const airportCodes = Object.keys(data.airports || {});
if (airportCodes.length < 2) fail('At least two airports are required.');
for (const [code, airport] of Object.entries(data.airports || {})) {
  if (!/^[A-Z]{3}$/.test(code)) fail(`Invalid IATA airport key: ${code}`);
  if (typeof airport.latitude !== 'number' || airport.latitude < -90 || airport.latitude > 90) fail(`Invalid latitude for ${code}.`);
  if (typeof airport.longitude !== 'number' || airport.longitude < -180 || airport.longitude > 180) fail(`Invalid longitude for ${code}.`);
}

const offerIds = new Set();
for (const offer of data.offers || []) {
  if (!offer.id || offerIds.has(offer.id)) fail(`Missing or duplicate offer id: ${offer.id || '(empty)'}`);
  offerIds.add(offer.id);
  if (!Number.isInteger(offer.score) || offer.score < 0 || offer.score > 100) fail(`${offer.id}: score must be 0-100.`);
  if (!['single', 'separate'].includes(offer.ticket_mode)) fail(`${offer.id}: invalid ticket_mode.`);
  if (!['low', 'medium', 'high'].includes(offer.risk)) fail(`${offer.id}: invalid risk.`);
  for (const direction of ['outbound', 'inbound']) {
    const journey = offer[direction] || {};
    if (!Number.isInteger(journey.duration_minutes) || journey.duration_minutes <= 0) fail(`${offer.id}: ${direction} duration must be positive.`);
    if (!Number.isInteger(journey.stops) || journey.stops < 0 || journey.stops > 2) fail(`${offer.id}: ${direction} stops are invalid.`);
    if (!Array.isArray(journey.via) || journey.via.length !== journey.stops) fail(`${offer.id}: ${direction} via count must match stops.`);
    for (const via of journey.via || []) if (!airportCodes.includes(via)) fail(`${offer.id}: unknown connection airport ${via}.`);
  }
  if (sum(offer.fare, ['total']) !== offer.fare?.total) fail(`${offer.id}: fare components do not equal fare total.`);
  if (sum(offer.trip_total, ['total']) !== offer.trip_total?.total) fail(`${offer.id}: trip components do not equal full-trip total.`);
  if (offer.trip_total?.flight !== offer.fare?.total) fail(`${offer.id}: trip flight cost must equal fare total.`);
  if (offer.booking?.bookable !== false || offer.booking?.checkout_url !== null) fail(`${offer.id}: demo offer must not expose checkout.`);
  if (!Array.isArray(offer.pros) || offer.pros.length < 2) fail(`${offer.id}: at least two advantages are required.`);
  if (!Array.isArray(offer.cons) || offer.cons.length < 1) fail(`${offer.id}: at least one disadvantage is required.`);
}

const smartest = [...(data.offers || [])].sort((a, b) => b.score - a.score)[0];
const cheapest = [...(data.offers || [])].sort((a, b) => a.trip_total.total - b.trip_total.total)[0];
if (smartest?.id !== 'demo-dubai-balanced') fail('The Dubai route must remain the highest smart score fixture.');
if (cheapest?.id !== 'demo-athens-cheapest') fail('The Athens route must remain the cheapest full-trip fixture.');

if (failures.length) {
  console.error('Tra-Vel flight contract validation failed:');
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Tra-Vel flight contract validation passed (${data.offers.length} offers, ${airportCodes.length} airports).`);
