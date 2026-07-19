import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');
const schemaDir = path.join(root, 'plugin/tra-vel-agent-core/schemas');
const includeDir = path.join(root, 'plugin/tra-vel-agent-core/includes/local-tourism');
const schemaFiles = [
  'israel-local-tourism-item.schema.json',
  'israel-local-map-session.schema.json',
  'israel-local-tourism-stress-suite.schema.json',
];

const expectedInventoryTypes = [
  'hotel',
  'boutique_hotel',
  'apartment',
  'short_rental',
  'villa',
  'hostel',
  'guesthouse_bnb',
  'kibbutz_rural_lodging',
  'campsite',
  'glamping',
  'attraction',
  'tour',
  'dining',
  'mobility_transport',
];
const expectedFactGroups = ['kosher', 'shabbat', 'accessibility', 'family', 'parking', 'shelter', 'seasonal_operation'];
const expectedNavigationStates = ['world_globe', 'country_focus', 'israel_region_overview', 'local_high_res_map', 'place_or_route_detail', 'itinerary_assembly', 'revalidated_proposal'];
const expectedStressTriggers = ['missing_geo', 'stale_hours', 'conflicting_accessibility', 'conflicting_kashrut', 'unavailable_property', 'map_tile_failure', 'reduced_motion', 'offline'];

let assertions = 0;
function assert(condition, message) {
  assertions += 1;
  if (!condition) {
    throw new Error(`Local tourism contract failed: ${message}`);
  }
}
function sorted(values) {
  return [...values].sort();
}
function exact(actual, expected, message) {
  assert(JSON.stringify(sorted(actual)) === JSON.stringify(sorted(expected)), message);
}
function resolvePointer(schema, ref) {
  assert(ref.startsWith('#/'), `only local schema refs are allowed (${ref})`);
  return ref.slice(2).split('/').reduce((value, token) => value?.[token.replace(/~1/g, '/').replace(/~0/g, '~')], schema);
}
function inspectSchema(schema, value, pointer = '#') {
  if (!value || typeof value !== 'object') return;
  if (typeof value.$ref === 'string') {
    assert(Boolean(resolvePointer(schema, value.$ref)), `${pointer} has an unresolved ref ${value.$ref}`);
  }
  if (value.type === 'object') {
    assert(value.additionalProperties === false, `${pointer} must be a closed object`);
    assert(value.properties && typeof value.properties === 'object', `${pointer} object must declare properties`);
  }
  for (const [key, child] of Object.entries(value)) {
    if (child && typeof child === 'object') inspectSchema(schema, child, `${pointer}/${key}`);
  }
}

const schemas = Object.fromEntries(schemaFiles.map(file => {
  const source = fs.readFileSync(path.join(schemaDir, file), 'utf8');
  const schema = JSON.parse(source);
  assert(schema.$schema === 'http://json-schema.org/draft-07/schema#', `${file} must use Draft-07`);
  assert(/^https:\/\/tra-vel\.co\.il\/schemas\/.+-v1\.0\.0\.json$/.test(schema.$id), `${file} needs a stable v1.0.0 identifier`);
  assert(!/"(?:supplier_name|provider_name|estimated_amount|price_estimate)"\s*:/.test(source), `${file} must not expose identity or invented-price keys`);
  inspectSchema(schema, schema);
  return [file, schema];
}));

const item = schemas['israel-local-tourism-item.schema.json'];
exact(item.properties.inventory_type.enum, expectedInventoryTypes, 'inventory taxonomy must cover every agreed local sellable type exactly');
exact(item.definitions.classification.properties.official_state.enum, ['ranked', 'unranked', 'expired', 'unknown', 'conflict', 'not_applicable'], 'official accommodation ranking must preserve unranked, expired, unknown, and conflict states');
exact(item.definitions.classification.properties.scheme_applicability.enum, ['applicable', 'not_applicable', 'unknown'], 'hotel-ranking applicability must be explicit instead of inferred from the broad lodging taxonomy');
assert(item.definitions.classification.properties.scheme_code.const === 'israel_hotel_ranking', 'classification facts must name the exact official ranking scheme');
assert(item.definitions.classification.properties.quality_inference_allowed.const === false, 'official ranking state must never authorize a customer-quality inference');
assert(item.required.includes('classification'), 'every local item must state the official-classification boundary explicitly');
exact(item.definitions.fitFacts.required, expectedFactGroups, 'all high-risk local fit groups must be explicit');
exact(item.definitions.factGroup.properties.group_state.enum, ['evidence_current', 'evidence_partial', 'unknown', 'conflict', 'not_applicable'], 'group state must describe evidence coverage, not claim broad accessibility or suitability');
exact(item.definitions.geography.properties.coordinate_state.enum, ['verified', 'approximate', 'unknown', 'conflict'], 'coordinate truth must include unknown and conflict');
exact(item.definitions.availability.properties.state.enum, ['unknown', 'checking', 'available_verified', 'limited_verified', 'unavailable_verified', 'stale'], 'availability truth vocabulary changed');
exact(item.definitions.pricing.properties.state.enum, ['not_requested', 'checking', 'verified_quote', 'expired_quote', 'unavailable'], 'pricing must not have an estimate state');
assert(item.definitions.pricing.allOf[0].else.properties.payable_minor.type === 'null', 'non-live price states must prohibit payable numbers');
assert(item.definitions.pricing.allOf[0].else.properties.bookable.const === false, 'non-live price states must prohibit checkout');
assert(item.definitions.jurisdiction.properties.country_code.const === 'IL', 'local geography must stay anchored to Israel');
assert(item.definitions.openingHours.properties.timezone.const === 'Asia/Jerusalem', 'local hours must use the Israel timezone');

const map = schemas['israel-local-map-session.schema.json'];
exact(map.properties.navigation_state.enum, expectedNavigationStates, 'Earth-to-local navigation states changed');
assert(map.definitions.layout.properties.static_poster_under_canvas.const === false, 'a static poster cannot sit under the interactive Earth');
assert(map.definitions.layout.properties.requires_second_entry_click.const === false, 'Israel selection cannot require a poster click');
assert(map.definitions.layout.properties.overlay_stack_depth.maximum === 1, 'layout cannot stack multiple contextual overlays');
assert(map.definitions.layout.properties.map_controls_available.const === true, 'map controls must remain available');
assert(map.definitions.layout.properties.map_pan_available.const === true, 'map panning must remain available');
assert(map.definitions.layout.properties.logical_edge_model.const === 'logical_start_end', 'RTL/LTR layout must use logical edges');
assert(map.definitions.layout.allOf.some(rule => rule.if?.properties?.viewport_class?.const === 'desktop' && rule.then?.properties?.map_ownership_ratio?.minimum >= 2 / 3), 'desktop must reserve at least two thirds for the map');
assert(map.definitions.layout.allOf.some(rule => rule.if?.properties?.viewport_class?.const === 'mobile' && rule.then?.properties?.selected_content_mode?.const === 'mobile_bottom_sheet'), 'mobile must use a safe-area-aware bottom sheet');
assert(map.definitions.interaction.properties.keyboard_supported.const === true && map.definitions.interaction.properties.screen_reader_summary_available.const === true, 'keyboard and screen-reader operation must remain first-class');

const stress = schemas['israel-local-tourism-stress-suite.schema.json'];
exact(stress.definitions.scenario.properties.trigger.enum, expectedStressTriggers, 'required stress triggers changed');
assert(stress.properties.scenarios.minItems === expectedStressTriggers.length, 'stress suite must exercise every required trigger');
assert(stress.properties.scenarios.allOf.length === expectedStressTriggers.length, 'a valid suite must contain each required stress trigger');
assert(stress.definitions.expectedRecovery.properties.map_controls_available.const === true, 'every recovery must preserve map controls');
assert(stress.definitions.expectedRecovery.properties.trip_context_preserved.const === true, 'every recovery must preserve trip context');
assert(stress.definitions.expectedRecovery.properties.numeric_price_claim_allowed.const === false, 'stress fallbacks cannot expose numeric price claims');
assert(stress.definitions.scenario.allOf.length === expectedStressTriggers.length, 'each stress trigger requires a deterministic conditional recovery');

const phpSources = [
  'class-tra-vel-local-tourism-taxonomy.php',
  'class-tra-vel-local-tourism-policy.php',
  'class-tra-vel-local-map-state-machine.php',
].map(file => fs.readFileSync(path.join(includeDir, file), 'utf8')).join('\n');
for (const value of [...expectedInventoryTypes, ...expectedNavigationStates, ...expectedStressTriggers]) {
  assert(phpSources.includes(`'${value}'`), `PHP runtime vocabulary is missing ${value}`);
}
assert(!phpSources.includes("'open_poster'"), 'runtime must not add a poster transition');
assert(phpSources.includes("'requires_second_entry_click' => false"), 'runtime transition plan must preserve direct entry');
assert(phpSources.includes("'static_poster_required'      => false"), 'runtime transition plan must reject a static poster');

console.log(`Local tourism contracts passed (${schemaFiles.length} closed schemas; ${expectedInventoryTypes.length} inventory types; ${expectedStressTriggers.length} stress recoveries; ${assertions} assertions).`);
