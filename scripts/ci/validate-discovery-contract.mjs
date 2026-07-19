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
const required = ['contract_version', 'data_mode', 'currency', 'origin', 'airport_registry', 'provider_status', 'exploration_hubs', 'destinations', 'route_sets'];
const monetaryClaimPattern = /(?:[$€₪]\s*\d[\d,]*(?:\.\d+)?|\b(?:USD|EUR|ILS)\s+\d[\d,]*(?:\.\d+)?)/iu;
const unverifiedPriceSuperlativePattern = /(?:הכי\s+זול|הזול\s+ביותר|המחיר\s+(?:הכולל\s+)?הנמוך\s+ביותר|חיסכון\s+מובטח)/u;
const defaultRouteFilters = Object.freeze({maxStops: 3, maxDurationMinutes: 3000, allowOvernight: false});
const canonicalCostCategories = ['flight', 'baggage', 'stay', 'taxes', 'transfers', 'local_transport', 'activities', 'dining', 'insurance', 'connectivity', 'equipment', 'entry'];
const canonicalHubScopes = ['route', 'stay', 'activities', 'insurance', 'connectivity', 'equipment'];

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

function validAbsoluteHttpUrl(value) {
  try {
    const parsed = new URL(value);
    return ['http:', 'https:'].includes(parsed.protocol) && Boolean(parsed.hostname);
  } catch {
    return false;
  }
}

function validCoordinatePair(latitude, longitude) {
  return Number.isFinite(latitude) && latitude >= -90 && latitude <= 90
    && Number.isFinite(longitude) && longitude >= -180 && longitude <= 180;
}

function validateExplorationHubCollection(hubs, destinationIds = new Set()) {
  const errors = [];
  if (!Array.isArray(hubs) || hubs.length < 30 || hubs.length > 80) return ['Exploration hubs must contain between 30 and 80 entries.'];
  const ids = new Set();
  const places = new Set();
  const coordinates = new Set();
  const codes = new Set();
  const longitudeBands = [0, 0, 0, 0];
  let northern = 0;
  let southern = 0;
  for (const hub of hubs) {
    const prefix = `Exploration hub ${hub?.id || '(missing id)'}`;
    const id = typeof hub?.id === 'string' ? hub.id : '';
    const placeKey = `${String(hub?.city || '').trim().toLocaleLowerCase()}|${String(hub?.country || '').trim().toLocaleLowerCase()}`;
    const coordinateKey = `${hub?.geo?.latitude}|${hub?.geo?.longitude}`;
    if (!/^[a-z0-9-]{2,60}$/.test(id) || ids.has(id)) errors.push(`${prefix} has a missing, malformed, or duplicate id.`);
    if (destinationIds.has(id)) errors.push(`${prefix} collides with a commercial destination id.`);
    if (!hub?.city || !hub?.country || places.has(placeKey)) errors.push(`${prefix} has a missing or duplicate city/country identity.`);
    if (!validCoordinatePair(hub?.geo?.latitude, hub?.geo?.longitude) || coordinates.has(coordinateKey)) errors.push(`${prefix} has invalid or duplicate WGS84 coordinates.`);
    if (!Number.isInteger(hub?.radius_km) || hub.radius_km < 40 || hub.radius_km > 750) errors.push(`${prefix} has an invalid explicit resolution radius.`);
    if (hub?.iata_search_code !== undefined && (!/^[A-Z]{3}$/.test(hub.iata_search_code) || codes.has(hub.iata_search_code))) errors.push(`${prefix} has an invalid or duplicate IATA search code.`);
    if (!Array.isArray(hub?.live_search_scopes)
      || hub.live_search_scopes.length !== canonicalHubScopes.length
      || canonicalHubScopes.some(scope => !hub.live_search_scopes.includes(scope))) {
      errors.push(`${prefix} must expose all six contextual live-search scopes and no partial commercial promise.`);
    }
    const allowedKeys = new Set(['id', 'city', 'country', 'geo', 'radius_km', 'iata_search_code', 'live_search_scopes']);
    if (Object.keys(hub || {}).some(key => !allowedKeys.has(key))) errors.push(`${prefix} contains an undeclared field.`);
    ids.add(id);
    places.add(placeKey);
    coordinates.add(coordinateKey);
    if (hub?.iata_search_code) codes.add(hub.iata_search_code);
    if (validCoordinatePair(hub?.geo?.latitude, hub?.geo?.longitude)) {
      if (hub.geo.latitude >= 0) northern += 1;
      else southern += 1;
      const band = hub.geo.longitude < -60 ? 0 : (hub.geo.longitude < 20 ? 1 : (hub.geo.longitude < 80 ? 2 : 3));
      longitudeBands[band] += 1;
    }
  }
  if (!northern || southern < 5 || longitudeBands.some(count => count < 4)) errors.push('Exploration hubs are not distributed across both hemispheres and all four global longitude bands.');
  return errors;
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
    if (rule.format === 'uri' && !validAbsoluteHttpUrl(value)) errors.push(`${path} is not an absolute HTTP(S) URI.`);
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
	const objectKeys = Object.keys(value);
	if (Number.isInteger(rule.minProperties) && objectKeys.length < rule.minProperties) errors.push(`${path} has too few properties.`);
	if (Number.isInteger(rule.maxProperties) && objectKeys.length > rule.maxProperties) errors.push(`${path} has too many properties.`);
	if (rule.propertyNames) objectKeys.forEach(key => errors.push(...validateAgainstSchema(key, rule.propertyNames, `${path}{property:${key}}`, root)));
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
if (data.contract_version !== '1.3.0' || schema?.properties?.contract_version?.const !== data.contract_version) {
  failures.push('Discovery data and schema must be pinned to contract version 1.3.0.');
}

if (data.contract_version !== '1.3.0') failures.push('Bundled discovery data must use the 1.3.0 typed-map contract.');
if (data.data_mode !== 'demo') failures.push('Bundled discovery data must remain explicitly in demo mode.');
if (data.currency !== 'USD') failures.push('Bundled demo contract must use USD consistently.');
if (!/^[A-Z]{3}$/.test(data.origin?.code || '')) failures.push('Origin must contain a valid IATA code.');

const airportRegistry = data.airport_registry && typeof data.airport_registry === 'object' && !Array.isArray(data.airport_registry)
  ? data.airport_registry
  : {};
const referencedAirportCodes = new Set([data.origin?.code]);
for (const destination of data.destinations || []) referencedAirportCodes.add(destination.airport?.code);
for (const routes of Object.values(data.route_sets || {})) {
  for (const route of routes || []) if (route.via !== null && route.via !== undefined) referencedAirportCodes.add(route.via);
}
for (const [registryCode, airport] of Object.entries(airportRegistry)) {
  if (!/^[A-Z]{3}$/.test(registryCode) || airport?.code !== registryCode) failures.push(`Airport registry key/code mismatch: ${registryCode}`);
  if (typeof airport?.name !== 'string' || airport.name.trim().length < 2) failures.push(`Airport registry ${registryCode} needs a typed label.`);
  if (airport?.destination_id !== null && !/^[a-z0-9-]{2,60}$/.test(airport?.destination_id || '')) failures.push(`Airport registry ${registryCode} has an invalid destination binding.`);
  if (!validCoordinatePair(airport?.latitude, airport?.longitude)) failures.push(`Airport registry ${registryCode} has invalid WGS84 coordinates.`);
}
for (const code of referencedAirportCodes) {
  if (!/^[A-Z]{3}$/.test(code || '') || !airportRegistry[code]) failures.push(`Referenced airport code is missing from the canonical registry: ${code || '(missing)'}`);
}
for (const code of Object.keys(airportRegistry)) {
  if (!referencedAirportCodes.has(code)) failures.push(`Airport registry contains an unreferenced non-canonical code: ${code}`);
}
const originAirport = airportRegistry[data.origin?.code];
if (!originAirport || originAirport.destination_id !== null || data.origin?.latitude !== originAirport.latitude || data.origin?.longitude !== originAirport.longitude) {
  failures.push('Origin coordinates must exactly match its canonical airport registry entry.');
}

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
  if (!airportRegistry[destination.airport?.code]) failures.push(`${prefix} airport is missing from the canonical coordinate registry.`);
  if (airportRegistry[destination.airport?.code]?.destination_id !== destination.id) failures.push(`${prefix} airport registry binding does not match the destination id.`);
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

failures.push(...validateExplorationHubCollection(data.exploration_hubs, destinationIds));
const explorationHubSchema = schema?.definitions?.explorationHub;
if (!explorationHubSchema || schema?.properties?.exploration_hubs?.items?.$ref !== '#/definitions/explorationHub') {
  failures.push('Discovery schema must bind exploration_hubs to its closed typed definition.');
} else {
  const validHubFixture = data.exploration_hubs?.[0];
  if (!validHubFixture || validateAgainstSchema(validHubFixture, explorationHubSchema, '$self.exploration_hub').length) failures.push('A valid exploration hub does not satisfy its published schema.');
  if (!validateAgainstSchema({...validHubFixture, latitude: 91, geo: {...validHubFixture?.geo, latitude: 91}}, explorationHubSchema, '$self.invalid_hub_coordinate').length) failures.push('Exploration hub schema accepted an invalid coordinate.');
  if (!validateAgainstSchema({...validHubFixture, radius_km: 0}, explorationHubSchema, '$self.invalid_hub_radius').length) failures.push('Exploration hub schema accepted an invalid radius.');
  if (!validateAgainstSchema({...validHubFixture, iata_search_code: 'tlv'}, explorationHubSchema, '$self.invalid_hub_code').length) failures.push('Exploration hub schema accepted a malformed IATA search code.');
  if (!validateAgainstSchema({...validHubFixture, price: 199}, explorationHubSchema, '$self.hub_commercial_leak').length) failures.push('Exploration hub schema accepted an undeclared commercial field.');
  if (!validateAgainstSchema({...validHubFixture, live_search_scopes: canonicalHubScopes.slice(0, 5)}, explorationHubSchema, '$self.partial_hub_scopes').length) failures.push('Exploration hub schema accepted a partial action scope list.');
}
const duplicateHubIdFixture = JSON.parse(JSON.stringify(data.exploration_hubs || []));
if (duplicateHubIdFixture.length > 1) duplicateHubIdFixture[1].id = duplicateHubIdFixture[0].id;
if (!validateExplorationHubCollection(duplicateHubIdFixture, destinationIds).some(error => error.includes('duplicate id'))) failures.push('Exploration hub identity validation did not reject a duplicate id.');
const duplicateHubCoordinateFixture = JSON.parse(JSON.stringify(data.exploration_hubs || []));
if (duplicateHubCoordinateFixture.length > 1) duplicateHubCoordinateFixture[1].geo = {...duplicateHubCoordinateFixture[0].geo};
if (!validateExplorationHubCollection(duplicateHubCoordinateFixture, destinationIds).some(error => error.includes('duplicate WGS84'))) failures.push('Exploration hub identity validation did not reject duplicate coordinates.');

const routeIds = new Set();
const demoRouteCostKeys = ['flight', 'baggage', 'hotel', 'transfers', 'insurance', 'overnight', 'total'];
for (const [destinationId, routes] of Object.entries(data.route_sets || {})) {
  if (!destinationIds.has(destinationId)) failures.push(`Route set references unknown destination: ${destinationId}`);
  const routeDestination = (data.destinations || []).find(destination => destination.id === destinationId);
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
    if (!Number.isInteger(route.duration_minutes) || route.duration_minutes <= 0 || !Number.isInteger(route.stops) || route.stops < 0 || route.stops > 1) failures.push(`${prefix} duration and stops must be valid integers with at most one mapped connection.`);
    const hasVia = route.via !== null && route.via !== undefined;
    if ((route.stops === 0 && hasVia) || (route.stops === 1 && !hasVia)) failures.push(`${prefix} stops and via code do not describe the same geometry.`);
    if (hasVia && (!/^[A-Z]{3}$/.test(route.via) || !airportRegistry[route.via])) failures.push(`${prefix} via code is not in the canonical airport registry.`);
    if (hasVia && [data.origin?.code, routeDestination?.airport?.code].includes(route.via)) failures.push(`${prefix} via code duplicates a route endpoint.`);
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
if (routeSchema?.properties?.stops?.maximum !== 1) failures.push('Route schema must reject geometry that cannot be expressed by its single typed via code.');
if (schema?.properties?.airport_registry?.additionalProperties?.$ref !== '#/definitions/airportCoordinate') failures.push('Discovery schema must bind the canonical airport registry to typed coordinates.');
if (!schema?.definitions?.airportCoordinate?.required?.includes('destination_id')) failures.push('Airport coordinate schema must require an explicit destination binding or null connection role.');
for (const definition of ['airportCoordinate', 'mapPrice', 'mapAction', 'mapProvenance', 'mapEntity', 'mapEndpoint', 'mapSegmentTruth', 'mapSegment']) {
  if (!schema?.definitions?.[definition]) failures.push(`Discovery schema is missing typed map definition: ${definition}`);
}
const mapEntitySchema = schema?.definitions?.mapEntity;
const mapSegmentSchema = schema?.definitions?.mapSegment;
const requiredEntityKeys = ['id', 'kind', 'destination_id', 'lat', 'lng', 'label', 'summary', 'data_mode', 'truth_state', 'freshness', 'action', 'provenance', 'price'];
const requiredSegmentKeys = ['id', 'route_id', 'destination_id', 'sequence', 'from', 'to', 'truth', 'provenance'];
if (requiredEntityKeys.some(key => !mapEntitySchema?.required?.includes(key))) failures.push('Map entity schema does not require its complete typed truth/action/price contract.');
if (requiredSegmentKeys.some(key => !mapSegmentSchema?.required?.includes(key))) failures.push('Map segment schema does not require its route/sequence/endpoints/truth/provenance contract.');
if (JSON.stringify(schema?.definitions?.mapAction?.properties?.type?.enum) !== JSON.stringify(['search_packages', 'search_hotels', 'compare_routes', 'plan_for_weather'])) failures.push('Map action type enum drifted.');
if (JSON.stringify(schema?.definitions?.mapPrice?.properties?.basis?.enum) !== JSON.stringify(['per_person_total', 'per_night'])) failures.push('Map price basis enum drifted.');
if (schema?.definitions?.mapPrice?.properties?.bookable?.const !== false || schema?.definitions?.mapSegmentTruth?.properties?.bookable?.const !== false) failures.push('Map prices and route segments must remain explicitly non-bookable.');

const mapProvenanceFixture = {source: 'Tra-Vel editorial planning profile', observed_at: null, retrieved_at: null, reviewed_on: '2026-07-17'};
const mapEntityFixture = {
  id: 'deal:bangkok', kind: 'deal', destination_id: 'bangkok', lat: 13.7563, lng: 100.5018,
  label: 'Bangkok', summary: 'Planning scenario', data_mode: 'demo', truth_state: 'planning', freshness: 'current',
  action: {type: 'search_packages', label: 'Plan package', href: 'https://tra-vel.co.il/packages/?destination=bangkok', requires_live_search: true},
  provenance: mapProvenanceFixture,
  price: {amount: 1347, currency: 'USD', formatted: '$1,347', basis: 'per_person_total', state: 'planning', bookable: false}
};
const mapSegmentFixture = {
  id: 'bangkok-dubai:1', route_id: 'bangkok-dubai', destination_id: 'bangkok', sequence: 1,
  from: {code: 'TLV', label: 'Ben Gurion Airport', lat: 32.0114, lng: 34.8867},
  to: {code: 'DXB', label: 'Dubai International Airport', lat: 25.2532, lng: 55.3657},
  truth: {data_mode: 'demo', truth_state: 'planning', freshness: 'current', bookable: false}, provenance: mapProvenanceFixture
};
if (validateAgainstSchema(mapEntityFixture, mapEntitySchema, '$self.map_entity').length) failures.push('Typed map entity fixture does not satisfy the published schema.');
if (validateAgainstSchema(mapSegmentFixture, mapSegmentSchema, '$self.map_segment').length) failures.push('Typed map segment fixture does not satisfy the published schema.');
if (!validateAgainstSchema({...mapEntityFixture, lat: 91}, mapEntitySchema, '$self.invalid_map_entity').length) failures.push('Map entity schema accepted an invalid latitude.');
if (!validateAgainstSchema({...mapSegmentFixture, from: {...mapSegmentFixture.from, code: 'tlv'}}, mapSegmentSchema, '$self.invalid_map_segment').length) failures.push('Map segment schema accepted a malformed airport code.');

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

console.log(`Tra-Vel discovery contract validation passed (${data.destinations.length} destinations, ${data.exploration_hubs.length} exploration hubs, ${routeIds.size} routes).`);
