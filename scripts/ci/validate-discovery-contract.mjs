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
const monetaryClaimPattern = /(?:[$€₪]\s*\d[\d,]*(?:\.\d+)?|\b(?:USD|EUR|ILS)\s+\d[\d,]*(?:\.\d+)?)/iu;
const unverifiedPriceSuperlativePattern = /(?:הכי\s+זול|הזול\s+ביותר|המחיר\s+(?:הכולל\s+)?הנמוך\s+ביותר|חיסכון\s+מובטח)/u;
const defaultRouteFilters = Object.freeze({maxStops: 3, maxDurationMinutes: 3000, allowOvernight: false});
const canonicalCostCategories = ['flight', 'baggage', 'stay', 'taxes', 'transfers', 'local_transport', 'activities', 'dining', 'insurance', 'connectivity', 'equipment', 'entry'];

function schemaValueAtPointer(root, reference) {
  if (!reference.startsWith('#/')) return undefined;
  return reference.slice(2).split('/').reduce((value, token) => (
    value?.[token.replaceAll('~1', '/').replaceAll('~0', '~')]
  ), root);
}

function matchesSchemaType(value, type) {
  if (type === 'null') return value === null;
  if (type === 'array') return Array.isArray(value);
  if (type === 'object') return value !== null && typeof value === 'object' && !Array.isArray(value);
  if (type === 'integer') return Number.isInteger(value);
  if (type === 'number') return typeof value === 'number' && Number.isFinite(value);
  return typeof value === type;
}

function validCalendarDate(value) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
  if (!match) return false;
  const [, year, month, day] = match.map(Number);
  const date = new Date(Date.UTC(year, month - 1, day));
  return date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day;
}

function validDateTime(value) {
  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/.exec(value);
  if (!match || !validCalendarDate(`${match[1]}-${match[2]}-${match[3]}`)) return false;
  return Number(match[4]) <= 23 && Number(match[5]) <= 59 && Number(match[6]) <= 59 && Number.isFinite(Date.parse(value));
}

function validateAgainstSchema(value, rule, path = '$', root = schema) {
  const errors = [];
  if (!rule || typeof rule !== 'object') return [`${path} has an invalid schema rule.`];
  if (rule.$ref) {
    const target = schemaValueAtPointer(root, rule.$ref);
    return target ? validateAgainstSchema(value, target, path, root) : [`${path} references missing schema ${rule.$ref}.`];
  }
  const types = Array.isArray(rule.type) ? rule.type : (rule.type ? [rule.type] : []);
  if (types.length && !types.some(type => matchesSchemaType(value, type))) {
    return [`${path} must have type ${types.join('|')}.`];
  }
  if ('const' in rule && JSON.stringify(value) !== JSON.stringify(rule.const)) errors.push(`${path} does not match const.`);
  if (Array.isArray(rule.enum) && !rule.enum.some(item => JSON.stringify(item) === JSON.stringify(value))) errors.push(`${path} is outside enum.`);
  if (typeof value === 'string') {
    const length = [...value].length;
    if (Number.isInteger(rule.minLength) && length < rule.minLength) errors.push(`${path} is shorter than minLength.`);
    if (Number.isInteger(rule.maxLength) && length > rule.maxLength) errors.push(`${path} exceeds maxLength.`);
    if (rule.pattern && !(new RegExp(rule.pattern, 'u')).test(value)) errors.push(`${path} does not match pattern ${rule.pattern}.`);
    if (rule.format === 'date' && !validCalendarDate(value)) errors.push(`${path} is not a valid calendar date.`);
    if (rule.format === 'date-time' && !validDateTime(value)) errors.push(`${path} is not a valid RFC3339 instant.`);
  }
  if (typeof value === 'number' && Number.isFinite(value)) {
    if (typeof rule.minimum === 'number' && value < rule.minimum) errors.push(`${path} is below minimum.`);
    if (typeof rule.maximum === 'number' && value > rule.maximum) errors.push(`${path} exceeds maximum.`);
  }
  if (Array.isArray(value)) {
    if (Number.isInteger(rule.minItems) && value.length < rule.minItems) errors.push(`${path} has too few items.`);
    if (Number.isInteger(rule.maxItems) && value.length > rule.maxItems) errors.push(`${path} has too many items.`);
    if (rule.uniqueItems && new Set(value.map(item => JSON.stringify(item))).size !== value.length) errors.push(`${path} contains duplicate items.`);
    if (rule.items) value.forEach((item, index) => errors.push(...validateAgainstSchema(item, rule.items, `${path}[${index}]`, root)));
  }
  if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
    for (const requiredKey of rule.required || []) {
      if (!Object.prototype.hasOwnProperty.call(value, requiredKey)) errors.push(`${path}.${requiredKey} is required.`);
    }
    const declared = rule.properties || {};
    for (const [key, child] of Object.entries(value)) {
      if (Object.prototype.hasOwnProperty.call(declared, key)) {
        errors.push(...validateAgainstSchema(child, declared[key], `${path}.${key}`, root));
      } else if (rule.additionalProperties === false) {
        errors.push(`${path}.${key} is not allowed.`);
      } else if (rule.additionalProperties && typeof rule.additionalProperties === 'object') {
        errors.push(...validateAgainstSchema(child, rule.additionalProperties, `${path}.${key}`, root));
      }
    }
  }
  return errors;
}

failures.push(...validateAgainstSchema(data, schema));
if (!validateAgainstSchema(999, schema?.definitions?.weather?.properties?.temperature_c, '$self.weather.temperature').length) {
  failures.push('Draft-07 walker failed its weather-range adversarial self-test.');
}
if (!validateAgainstSchema('2030-02-30T12:00:00Z', {type: 'string', format: 'date-time'}, '$self.invalid_time').length) {
  failures.push('Draft-07 walker accepted an impossible RFC3339 calendar instant.');
}
if (!validateAgainstSchema({flight: 1, baggage: 1, stay: 1, total: 3}, schema?.definitions?.routeCosts, '$self.alias_cost').length) {
  failures.push('Draft-07 walker accepted the removed stay route-cost alias.');
}

for (const key of required) {
  if (!(key in data)) failures.push(`Discovery data is missing top-level key: ${key}`);
  if (!schema?.required?.includes(key)) failures.push(`Discovery schema does not require: ${key}`);
}
if (data.contract_version !== '1.1.0' || schema?.properties?.contract_version?.const !== data.contract_version) {
  failures.push('Discovery data and schema must be pinned to contract version 1.1.0.');
}

if (data.contract_version !== '1.1.0') failures.push('Bundled discovery data must use the 1.1.0 planning contract.');
if (data.data_mode !== 'demo') failures.push('Bundled discovery data must remain explicitly in demo mode.');
if (data.currency !== 'USD') failures.push('Bundled demo contract must use USD consistently.');
if (!/^[A-Z]{3}$/.test(data.origin?.code || '')) failures.push('Origin must contain a valid IATA code.');

for (const [provider, status] of Object.entries(data.provider_status || {})) {
  if (status.connected !== false) failures.push(`${provider} must not be marked connected until a live adapter is verified.`);
  if (status.readiness !== 'contract_ready') failures.push(`${provider} must declare contract_ready readiness.`);
  if (!status.adapter) failures.push(`${provider} is missing its intended adapter.`);
}

const destinationIds = new Set();
const planningModuleIds = ['mobility', 'dining', 'entry', 'connectivity', 'accessibility', 'equipment'];
for (const destination of data.destinations || []) {
  const prefix = `Destination ${destination.id || '(missing id)'}`;
  if (!/^[a-z0-9-]{2,60}$/.test(destination.id || '') || destinationIds.has(destination.id)) failures.push(`${prefix} has a missing, malformed, or duplicate id.`);
  destinationIds.add(destination.id);
  if (![destination.city, destination.country].every(value => typeof value === 'string' && value.trim().length >= 2)) failures.push(`${prefix} needs typed city and country names.`);
  if (!/^[A-Z]{3}$/.test(destination.airport?.code || '')) failures.push(`${prefix} has an invalid airport code.`);
  if (typeof destination.airport?.name !== 'string' || destination.airport.name.trim().length < 2 || typeof destination.airport?.direct !== 'boolean' || !Number.isInteger(destination.airport?.flight_minutes) || destination.airport.flight_minutes <= 0 || !Number.isInteger(destination.airport?.transfer_minutes) || destination.airport.transfer_minutes < 0) failures.push(`${prefix} airport identity, direct state, and durations must be strongly typed.`);
  if (!(destination.geo?.latitude >= -90 && destination.geo?.latitude <= 90) || !(destination.geo?.longitude >= -180 && destination.geo?.longitude <= 180)) {
    failures.push(`${prefix} must include valid WGS84 coordinates.`);
  }
  if (![destination.position?.x, destination.position?.y].every(value => Number.isFinite(value) && value >= 0 && value <= 100)) {
    failures.push(`${prefix} map position must be between 0 and 100.`);
  }
  if (!(destination.deal?.headline_price > 0) || !(destination.deal?.total_per_person > 0)) failures.push(`${prefix} prices must be positive.`);
  if (destination.deal?.headline_price > destination.deal?.total_per_person) failures.push(`${prefix} headline price exceeds total trip price.`);
  if (!Number.isInteger(destination.deal?.nights) || destination.deal.nights < 1 || !['destination_deal', 'package_inclusive'].includes(destination.deal?.total_scope) || !Number.isFinite(destination.deal?.trend_pct) || Math.abs(destination.deal.trend_pct) > 100) failures.push(`${prefix} deal nights, scope, and trend must be valid typed values.`);
  if (destination.deal?.currency !== data.currency) failures.push(`${prefix} deal currency must match the USD contract currency.`);
  if (destination.hotel?.currency !== data.currency) failures.push(`${prefix} hotel currency must match the USD contract currency.`);
  if (![destination.hotel?.name, destination.hotel?.area].every(value => typeof value === 'string' && value.trim().length >= 2) || !Number.isFinite(destination.hotel?.rating) || destination.hotel.rating < 0 || destination.hotel.rating > 5 || !Number.isInteger(destination.hotel?.nights) || destination.hotel.nights < 1 || ![destination.hotel?.nightly, destination.hotel?.room_total, destination.hotel?.per_person_total].every(value => Number.isFinite(value) && value >= 0)) failures.push(`${prefix} hotel identity, rating, nights, and prices must be strongly typed and in range.`);
  if (monetaryClaimPattern.test(destination.deal?.insight || '')) {
    failures.push(`${prefix} insight must not make an unverified numeric money or savings claim in demo mode.`);
  }
  if (destination.deal?.bookable !== false) failures.push(`${prefix} must remain non-bookable in demo mode.`);
  if (!existsSync(join(themeRoot, 'assets', 'images', destination.image || ''))) failures.push(`${prefix} image does not exist: ${destination.image}`);
  if (!Array.isArray(destination.tags) || destination.tags.length < 2) failures.push(`${prefix} needs at least two decision tags.`);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(destination.planning?.reviewed_on || '')) failures.push(`${prefix} planning profile needs a review date.`);
  if (!destination.planning?.source_label) failures.push(`${prefix} planning profile needs a source label.`);
  for (const moduleId of planningModuleIds) {
    const module = destination.planning?.modules?.[moduleId];
    if (!module) {
      failures.push(`${prefix} is missing planning module: ${moduleId}`);
      continue;
    }
    if (!['editorial', 'needs_details', 'unknown'].includes(module.state)) failures.push(`${prefix} ${moduleId} has an invalid planning state.`);
    if (![module.headline, module.detail, module.next_action].every(value => typeof value === 'string' && value.trim().length >= 2)) {
      failures.push(`${prefix} ${moduleId} needs headline, detail, and next action copy.`);
    }
  }
  const costCategories = destination.planning?.cost_scope?.categories;
  if (!Array.isArray(costCategories) || canonicalCostCategories.some(category => !costCategories.includes(category)) || costCategories.length !== canonicalCostCategories.length) failures.push(`${prefix} must map the twelve canonical end-to-end cost categories.`);
  if (unverifiedPriceSuperlativePattern.test(destination.deal?.insight || '')) failures.push(`${prefix} contains an unverified price superlative.`);
}

const routeIds = new Set();
const demoRouteCostKeys = ['flight', 'baggage', 'hotel', 'transfers', 'insurance', 'overnight', 'total'];
for (const [destinationId, routes] of Object.entries(data.route_sets || {})) {
  if (!destinationIds.has(destinationId)) failures.push(`Route set references unknown destination: ${destinationId}`);
  if (!Array.isArray(routes) || routes.length < 2) failures.push(`Route set ${destinationId} needs at least two comparable routes.`);
  const defaultVisibleRoutes = (routes || []).filter(route => (
    route.stops <= defaultRouteFilters.maxStops
    && route.duration_minutes <= defaultRouteFilters.maxDurationMinutes
    && (defaultRouteFilters.allowOvernight || Number(route.costs?.overnight || 0) === 0)
  ));
  if (defaultVisibleRoutes.length < 2) {
    failures.push(`Route set ${destinationId} needs at least two routes visible with the default filters (max 3 stops, 3000 minutes, no overnight).`);
  }
  for (const route of routes || []) {
    const prefix = `Route ${route.id || '(missing id)'}`;
    if (!route.id || routeIds.has(route.id)) failures.push(`${prefix} has a missing or duplicate id.`);
    routeIds.add(route.id);
    if (!['low', 'medium', 'high'].includes(route.risk)) failures.push(`${prefix} has an invalid risk level.`);
    if (!(route.score >= 0 && route.score <= 100)) failures.push(`${prefix} score must be between 0 and 100.`);
    if (!Number.isInteger(route.duration_minutes) || route.duration_minutes <= 0 || !Number.isInteger(route.stops) || route.stops < 0) failures.push(`${prefix} duration and stops must be valid integers.`);
    if (!/^[A-Z]{3}$/.test(route.currency || '') || route.currency !== data.currency) {
      failures.push(`${prefix} must declare the route-level USD ISO currency.`);
    }
    if (demoRouteCostKeys.some(key => !Number.isFinite(Number(route.costs?.[key])))) {
      failures.push(`${prefix} must include every bundled planning cost component.`);
    }
    if (!Array.isArray(route.pros) || route.pros.length < 2 || !Array.isArray(route.cons) || route.cons.length < 1) {
      failures.push(`${prefix} must explain at least two advantages and one trade-off.`);
    }
    const routeDecisionCopy = [route.label, route.badge, ...(route.pros || []), ...(route.cons || [])].join(' ');
    if (monetaryClaimPattern.test(routeDecisionCopy)) {
      failures.push(`${prefix} decision copy must not present an unverified numeric money or savings claim.`);
    }
    if (unverifiedPriceSuperlativePattern.test(routeDecisionCopy)) failures.push(`${prefix} decision copy contains an unverified price superlative.`);
    const componentTotal = Object.entries(route.costs || {})
      .filter(([key]) => key !== 'total')
      .reduce((total, [, value]) => total + Number(value || 0), 0);
    if (componentTotal !== route.costs?.total) failures.push(`${prefix} total ${route.costs?.total} does not equal components ${componentTotal}.`);
  }
}

for (const destinationId of destinationIds) {
  if (!Array.isArray(data.route_sets?.[destinationId]) || data.route_sets[destinationId].length < 2) {
    failures.push(`Every supported globe destination needs at least two route candidates: ${destinationId}`);
  }
}

const destinationSchema = schema?.properties?.destinations?.items;
const routeSchema = schema?.properties?.route_sets?.additionalProperties?.items;
if (!destinationSchema?.required?.includes('planning')) failures.push('Discovery schema must require a planning profile for every destination.');
for (const definition of ['airport', 'deal', 'hotel', 'weather']) {
  if (!schema?.definitions?.[definition]) failures.push(`Discovery schema is missing the typed ${definition} definition.`);
  if (destinationSchema?.properties?.[definition]?.$ref !== `#/definitions/${definition}`) failures.push(`Destination schema does not bind ${definition} to its typed definition.`);
}
if (schema?.definitions?.airport?.properties?.direct?.type !== 'boolean' || schema?.definitions?.hotel?.properties?.rating?.maximum !== 5 || schema?.definitions?.hotel?.properties?.nights?.minimum !== 1) failures.push('Destination place schemas do not enforce airport and hotel type/range boundaries.');
if (!schema?.definitions?.planningProfile || !schema?.definitions?.planningModule) failures.push('Discovery schema is missing reusable 360-degree planning definitions.');
if (!routeSchema?.required?.includes('currency')) failures.push('Discovery schema must require an explicit currency on every route.');
if (routeSchema?.properties?.currency?.pattern !== '^[A-Z]{3}$') failures.push('Discovery schema must require an ISO currency on live route fragments.');
if (!schema?.definitions?.routeCosts) failures.push('Discovery schema is missing the typed route-cost definition.');

for (const destinationId of ['tokyo', 'lisbon']) {
  if (!(data.route_sets?.[destinationId] || []).some(route => Number(route.costs?.overnight || 0) > 0)) {
    failures.push(`${destinationId} must retain an explicit overnight alternative alongside its default-visible routes.`);
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
