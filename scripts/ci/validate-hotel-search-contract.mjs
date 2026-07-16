import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const dataPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'hotel-search-demo.json');
const schemaPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'hotel-search.schema.json');
const data = JSON.parse(readFileSync(dataPath, 'utf8'));
const schema = JSON.parse(readFileSync(schemaPath, 'utf8'));
const failures = [];
const fail = message => failures.push(message);

for (const key of schema.required || []) if (!(key in data)) fail(`Missing schema-required property: ${key}`);
if (!/^\d+\.\d+\.\d+$/.test(data.contract_version || '')) fail('contract_version must use semantic versioning.');
if (data.data_mode !== 'demo') fail('The bundled hotel fallback must remain demo data.');
if (data.currency !== 'EUR') fail('The bundled Budapest contract must use EUR.');
if (data.provider_status?.connected !== false || data.provider_status?.bookable !== false) fail('Demo hotel inventory must remain disconnected and non-bookable.');
if (!/^[A-Z]{3}$/.test(data.destination?.code || '')) fail('Destination code must be IATA-style.');
if (Math.abs(data.destination?.latitude) > 90 || Math.abs(data.destination?.longitude) > 180) fail('Destination coordinates are invalid.');

const areaIds = new Set();
for (const area of data.areas || []) {
  if (!area.id || areaIds.has(area.id)) fail(`Missing or duplicate area id: ${area.id || '(empty)'}`);
  areaIds.add(area.id);
  if (!Array.isArray(area.best_for) || area.best_for.length < 2) fail(`${area.id}: traveler-fit tags are incomplete.`);
  if (!area.tradeoff || !area.transport) fail(`${area.id}: trade-off or transport explanation is missing.`);
  if (!Number.isInteger(area.route_minutes) || area.route_minutes < 0) fail(`${area.id}: route time is invalid.`);
  if (!Number.isInteger(area.average_nightly) || area.average_nightly <= 0) fail(`${area.id}: average nightly rate is invalid.`);
  if (typeof area.position?.x !== 'number' || typeof area.position?.y !== 'number' || area.position.x < 0 || area.position.x > 100 || area.position.y < 0 || area.position.y > 100) fail(`${area.id}: map position is invalid.`);
}

const propertyIds = new Set();
for (const property of data.properties || []) {
  if (!property.id || propertyIds.has(property.id)) fail(`Missing or duplicate property id: ${property.id || '(empty)'}`);
  propertyIds.add(property.id);
  if (!areaIds.has(property.area_id)) fail(`${property.id}: unknown area ${property.area_id}.`);
  if (!Number.isInteger(property.score) || property.score < 0 || property.score > 100) fail(`${property.id}: score must be 0-100.`);
  if (!Number.isInteger(property.stars) || property.stars < 1 || property.stars > 5) fail(`${property.id}: star rating is invalid.`);
  if (typeof property.guest_score !== 'number' || property.guest_score < 0 || property.guest_score > 10) fail(`${property.id}: guest score is invalid.`);
  if (!Number.isInteger(property.review_count) || property.review_count < 1) fail(`${property.id}: review count is invalid.`);
  if (!Number.isInteger(property.room?.sleeps) || property.room.sleeps < 1 || !Number.isInteger(property.room?.size_sqm)) fail(`${property.id}: room occupancy or size is invalid.`);
  if (Math.abs(property.location?.latitude) > 90 || Math.abs(property.location?.longitude) > 180) fail(`${property.id}: coordinates are invalid.`);
  if (property.pricing?.base !== property.pricing?.nightly * 4) fail(`${property.id}: four-night base fixture is inconsistent.`);
  if (property.pricing?.total_stay !== property.pricing?.base + property.pricing?.taxes + property.pricing?.fees) fail(`${property.id}: total stay does not equal base, taxes and fees.`);
  if (!Array.isArray(property.pros) || property.pros.length < 2 || !Array.isArray(property.cons) || property.cons.length < 1) fail(`${property.id}: decision trade-offs are incomplete.`);
  if (property.booking?.bookable !== false || property.booking?.checkout_url !== null) fail(`${property.id}: demo property must not expose checkout.`);
}

const smartest = [...(data.properties || [])].sort((a, b) => b.score - a.score)[0];
const cheapest = [...(data.properties || [])].sort((a, b) => a.pricing.total_stay - b.pricing.total_stay)[0];
const family = [...(data.properties || [])].filter(item => item.amenities.family && item.room.sleeps >= 4).sort((a, b) => b.score - a.score)[0];
if (smartest?.id !== 'danube-house-demo') fail('Danube House must remain the highest smart-score fixture.');
if (cheapest?.id !== 'quarter-social-demo') fail('Quarter Social must remain the cheapest stay fixture.');
if (family?.id !== 'park-family-demo') fail('Park Family must remain the strongest family fixture.');

if (failures.length) {
  console.error('Tra-Vel hotel contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}
console.log(`Tra-Vel hotel contract validation passed (${data.properties.length} properties, ${data.areas.length} areas).`);
