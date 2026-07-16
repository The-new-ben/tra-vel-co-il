import { existsSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const themeRoot = join(repoRoot, 'theme', 'tra-vel-v2');
const dataPath = join(themeRoot, 'assets', 'data', 'discovery-demo.json');
const schemaPath = join(themeRoot, 'assets', 'data', 'discovery.schema.json');
const failures = [];

function loadJson(path, label) {
  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  } catch (error) {
    failures.push(`${label} is invalid JSON: ${error.message}`);
    return {};
  }
}

const data = loadJson(dataPath, 'Discovery data');
const schema = loadJson(schemaPath, 'Discovery schema');
const required = ['contract_version', 'data_mode', 'currency', 'origin', 'provider_status', 'destinations', 'route_sets'];

for (const key of required) {
  if (!(key in data)) failures.push(`Discovery data is missing top-level key: ${key}`);
  if (!schema?.required?.includes(key)) failures.push(`Discovery schema does not require: ${key}`);
}

if (!/^\d+\.\d+\.\d+$/.test(data.contract_version || '')) failures.push('contract_version must use semantic versioning.');
if (data.data_mode !== 'demo') failures.push('Bundled discovery data must remain explicitly in demo mode.');
if (data.currency !== 'USD') failures.push('Bundled demo contract must use USD consistently.');
if (!/^[A-Z]{3}$/.test(data.origin?.code || '')) failures.push('Origin must contain a valid IATA code.');

for (const [provider, status] of Object.entries(data.provider_status || {})) {
  if (status.connected !== false) failures.push(`${provider} must not be marked connected until a live adapter is verified.`);
  if (status.readiness !== 'contract_ready') failures.push(`${provider} must declare contract_ready readiness.`);
  if (!status.adapter) failures.push(`${provider} is missing its intended adapter.`);
}

const destinationIds = new Set();
for (const destination of data.destinations || []) {
  const prefix = `Destination ${destination.id || '(missing id)'}`;
  if (!destination.id || destinationIds.has(destination.id)) failures.push(`${prefix} has a missing or duplicate id.`);
  destinationIds.add(destination.id);
  if (!/^[A-Z]{3}$/.test(destination.airport?.code || '')) failures.push(`${prefix} has an invalid airport code.`);
  if (!(destination.geo?.latitude >= -90 && destination.geo?.latitude <= 90) || !(destination.geo?.longitude >= -180 && destination.geo?.longitude <= 180)) {
    failures.push(`${prefix} must include valid WGS84 coordinates.`);
  }
  if (![destination.position?.x, destination.position?.y].every(value => Number.isFinite(value) && value >= 0 && value <= 100)) {
    failures.push(`${prefix} map position must be between 0 and 100.`);
  }
  if (!(destination.deal?.headline_price > 0) || !(destination.deal?.total_per_person > 0)) failures.push(`${prefix} prices must be positive.`);
  if (destination.deal?.headline_price > destination.deal?.total_per_person) failures.push(`${prefix} headline price exceeds total trip price.`);
  if (destination.deal?.bookable !== false) failures.push(`${prefix} must remain non-bookable in demo mode.`);
  if (!existsSync(join(themeRoot, 'assets', 'images', destination.image || ''))) failures.push(`${prefix} image does not exist: ${destination.image}`);
  if (!Array.isArray(destination.tags) || destination.tags.length < 2) failures.push(`${prefix} needs at least two decision tags.`);
}

const routeIds = new Set();
for (const [destinationId, routes] of Object.entries(data.route_sets || {})) {
  if (!destinationIds.has(destinationId)) failures.push(`Route set references unknown destination: ${destinationId}`);
  if (!Array.isArray(routes) || routes.length < 2) failures.push(`Route set ${destinationId} needs at least two comparable routes.`);
  for (const route of routes || []) {
    const prefix = `Route ${route.id || '(missing id)'}`;
    if (!route.id || routeIds.has(route.id)) failures.push(`${prefix} has a missing or duplicate id.`);
    routeIds.add(route.id);
    if (!['low', 'medium', 'high'].includes(route.risk)) failures.push(`${prefix} has an invalid risk level.`);
    if (!(route.score >= 0 && route.score <= 100)) failures.push(`${prefix} score must be between 0 and 100.`);
    if (!(route.duration_minutes > 0) || !(route.stops >= 0)) failures.push(`${prefix} duration and stops must be valid.`);
    if (!Array.isArray(route.pros) || route.pros.length < 2 || !Array.isArray(route.cons) || route.cons.length < 1) {
      failures.push(`${prefix} must explain at least two advantages and one trade-off.`);
    }
    const componentTotal = Object.entries(route.costs || {})
      .filter(([key]) => key !== 'total')
      .reduce((total, [, value]) => total + Number(value || 0), 0);
    if (componentTotal !== route.costs?.total) failures.push(`${prefix} total ${route.costs?.total} does not equal components ${componentTotal}.`);
  }
}

const bangkokRecommended = [...(data.route_sets?.bangkok || [])].sort((a, b) => b.score - a.score)[0];
if (bangkokRecommended?.id !== 'bangkok-dubai') failures.push('Bangkok smart recommendation must be the balanced Dubai route.');

if (failures.length) {
  console.error('Tra-Vel discovery contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Tra-Vel discovery contract validation passed (${data.destinations.length} destinations, ${routeIds.size} routes).`);
