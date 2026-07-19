import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');
const operations = path.join(root, 'plugin/tra-vel-agent-core/includes/operations');
const schemaPath = path.join(root, 'plugin/tra-vel-agent-core/schemas/private/service-breadth-registry.schema.json');
const fixturePath = path.join(root, 'plugin/tra-vel-agent-core/assets/fixtures/service-breadth-registry.json');
const runtimePath = path.join(root, 'scripts/ci/validate-service-breadth-registry-runtime.php');

const taxonomy = fs.readFileSync(path.join(operations, 'class-tra-vel-service-breadth-taxonomy.php'), 'utf8');
const policy = fs.readFileSync(path.join(operations, 'class-tra-vel-service-breadth-policy.php'), 'utf8');
const runtime = fs.readFileSync(runtimePath, 'utf8');
const schemaSource = fs.readFileSync(schemaPath, 'utf8');
const fixtureSource = fs.readFileSync(fixturePath, 'utf8');
const schema = JSON.parse(schemaSource);
const fixture = JSON.parse(fixtureSource);

let assertions = 0;
function assert(condition, message) {
  assertions += 1;
  if (!condition) throw new Error(`Service breadth contract failed: ${message}`);
}
function exact(actual, expected, message) {
  assert(JSON.stringify(actual) === JSON.stringify(expected), message);
}
function sorted(values) { return [...values].sort(); }
function sameMembers(actual, expected, message) {
  exact(sorted(actual), sorted(expected), message);
}
function resolvePointer(rootSchema, ref) {
  assert(ref.startsWith('#/'), `only local schema references are allowed (${ref})`);
  return ref.slice(2).split('/').reduce((value, token) => value?.[token.replace(/~1/g, '/').replace(/~0/g, '~')], rootSchema);
}
function deepEqual(left, right) { return JSON.stringify(left) === JSON.stringify(right); }

function inspectSchema(node, pointer = '#') {
  if (!node || typeof node !== 'object') return;
  if (typeof node.$ref === 'string') assert(Boolean(resolvePointer(schema, node.$ref)), `${pointer} has unresolved ref ${node.$ref}`);
  if (node.type === 'object') {
    assert(node.additionalProperties === false, `${pointer} must be a closed object`);
    assert(node.properties && typeof node.properties === 'object', `${pointer} must declare properties`);
    sameMembers(node.required || [], Object.keys(node.properties || {}), `${pointer} must require every declared property`);
  }
  if (Array.isArray(node.enum)) assert(new Set(node.enum.map(value => JSON.stringify(value))).size === node.enum.length, `${pointer} enum contains duplicates`);
  for (const [key, child] of Object.entries(node)) {
    if (child && typeof child === 'object') inspectSchema(child, `${pointer}/${key}`);
  }
}

function validateValue(value, rule, pointer = '#') {
  if (rule.$ref) return validateValue(value, resolvePointer(schema, rule.$ref), pointer);
  if (Object.prototype.hasOwnProperty.call(rule, 'const')) assert(deepEqual(value, rule.const), `${pointer} must equal its schema const`);
  if (Array.isArray(rule.enum)) assert(rule.enum.some(candidate => deepEqual(candidate, value)), `${pointer} is outside its enum`);
  if (rule.type === 'object') {
    assert(value && typeof value === 'object' && !Array.isArray(value), `${pointer} must be an object`);
    for (const key of rule.required || []) assert(Object.prototype.hasOwnProperty.call(value, key), `${pointer} is missing ${key}`);
    if (rule.additionalProperties === false) {
      for (const key of Object.keys(value)) assert(Object.prototype.hasOwnProperty.call(rule.properties, key), `${pointer} has unexpected ${key}`);
    }
    for (const [key, childRule] of Object.entries(rule.properties || {})) {
      if (Object.prototype.hasOwnProperty.call(value, key)) validateValue(value[key], childRule, `${pointer}/${key}`);
    }
  } else if (rule.type === 'array') {
    assert(Array.isArray(value), `${pointer} must be an array`);
    if (Number.isInteger(rule.minItems)) assert(value.length >= rule.minItems, `${pointer} is below minItems`);
    if (Number.isInteger(rule.maxItems)) assert(value.length <= rule.maxItems, `${pointer} exceeds maxItems`);
    if (rule.uniqueItems) assert(new Set(value.map(item => JSON.stringify(item))).size === value.length, `${pointer} must contain unique items`);
    value.forEach((item, index) => validateValue(item, rule.items, `${pointer}/${index}`));
  } else if (rule.type === 'string') {
    assert(typeof value === 'string', `${pointer} must be a string`);
    if (rule.pattern) assert(new RegExp(rule.pattern).test(value), `${pointer} fails pattern ${rule.pattern}`);
  } else if (rule.type === 'integer') {
    assert(Number.isInteger(value), `${pointer} must be an integer`);
    if (Number.isFinite(rule.minimum)) assert(value >= rule.minimum, `${pointer} is below minimum`);
    if (Number.isFinite(rule.maximum)) assert(value <= rule.maximum, `${pointer} exceeds maximum`);
  } else if (rule.type === 'boolean') {
    assert(typeof value === 'boolean', `${pointer} must be boolean`);
  }
}

assert(schema.$schema === 'http://json-schema.org/draft-07/schema#', 'schema must use Draft-07');
assert(schema.$id === 'https://tra-vel.co.il/schemas/private/service-breadth-registry-v1.1.0.json', 'schema needs a stable hardened private v1.1 identifier');
inspectSchema(schema);
validateValue(fixture, schema);

const expectedFamilies = [
  'air_transport', 'lodging', 'dynamic_package', 'organized_tour', 'cruise', 'ferry', 'rail', 'coach_bus',
  'ground_transfer', 'car_rental', 'airport_ancillary', 'experience', 'dining', 'travel_protection',
  'connectivity', 'equipment', 'entry_document_assistance',
];
const expectedVerticals = ['flight', 'accommodation', 'package', 'transfer', 'activity', 'dining', 'insurance', 'connectivity', 'equipment'];
const expectedLifecycle = ['search', 'revalidate', 'hold_reserve', 'confirm', 'fulfill', 'change', 'cancel', 'refund', 'incident', 'reconciliation', 'settlement', 'post_service_evidence'];
const expectedLocalLodging = ['city_business_hotel', 'resort_hotel', 'boutique_hotel', 'vacation_apartment_short_term_rental', 'villa', 'hostel', 'rural_bnb_zimmer', 'kibbutz_holiday_village_guest_accommodation', 'campground_glamping'];
const expectedLocalExperiences = ['local_guide_tour', 'attraction', 'museum', 'nature_reserve_park', 'beach', 'spa_wellness', 'event'];
const expectedGroundMobility = {
  taxi_ride: 'ground_transfer', shared_shuttle: 'ground_transfer', private_driver: 'ground_transfer',
  public_local_transit: 'ground_transfer', rental_car: 'car_rental', rail: 'rail', coach: 'coach_bus', ferry: 'ferry',
};
const prioritySubtypeOperations = {
  lodging: {
    city_business_hotel: ['city_business_arrival_and_workday_fit', 'business_hotel_late_arrival_deadline_local', 'city_hotel_front_desk_handoff', 'city_hotel_public_transport_anchor'],
    resort_hotel: ['resort_facility_schedule_and_fee_scope', 'resort_facility_reservation_deadline_local', 'resort_guest_services_handoff', 'resort_reception_and_facility_anchor'],
    boutique_hotel: ['boutique_unit_variance_and_staffing', 'boutique_staffed_check_in_deadline_local', 'boutique_on_call_host_handoff', 'boutique_hotel_entrance_anchor'],
    vacation_apartment_short_term_rental: ['short_term_rental_host_key_and_registration_state', 'short_term_rental_key_release_deadline_local', 'short_term_rental_host_and_platform_handoff', 'short_term_rental_key_handoff_anchor'],
    villa: ['villa_occupancy_pool_and_whole_property_scope', 'villa_guest_manifest_deadline_utc', 'villa_property_manager_handoff', 'villa_gate_and_safety_anchor'],
    hostel: ['hostel_bed_dorm_locker_and_age_rules', 'hostel_late_arrival_deadline_local', 'hostel_reception_handoff', 'hostel_reception_and_bed_anchor'],
    rural_bnb_zimmer: ['rural_zimmer_access_shelter_and_host_handoff', 'rural_zimmer_host_reconfirmation_deadline_local', 'rural_zimmer_host_handoff', 'rural_zimmer_access_and_shelter_anchor'],
    kibbutz_holiday_village_guest_accommodation: ['kibbutz_guest_unit_gate_dining_and_holiday_access', 'kibbutz_gate_access_deadline_local', 'kibbutz_guest_house_handoff', 'kibbutz_gate_and_guest_unit_anchor'],
    campground_glamping: ['campground_pitch_utility_weather_and_fire_rules', 'campground_arrival_weather_decision_deadline_local', 'campground_operator_or_ranger_handoff', 'campground_pitch_and_emergency_anchor'],
  },
  experience: {
    local_guide_tour: ['licensed_local_guide_identity_language_and_route', 'guide_meeting_point_reconfirmation_deadline_local', 'licensed_local_guide_handoff', 'guide_meeting_point_anchor'],
    attraction: ['attraction_timed_entry_capacity_and_height_rules', 'attraction_timed_entry_deadline_local', 'attraction_admission_handoff', 'attraction_admission_gate_anchor'],
    museum: ['museum_gallery_hours_ticket_and_accessibility_route', 'museum_last_admission_deadline_local', 'museum_visitor_services_handoff', 'museum_accessible_entrance_anchor'],
    nature_reserve_park: ['nature_reserve_permit_trail_weather_and_closure_state', 'nature_reserve_entry_and_weather_decision_deadline_local', 'nature_reserve_ranger_handoff', 'nature_reserve_trailhead_and_exit_anchor'],
    beach: ['beach_lifeguard_water_condition_and_access_route', 'beach_lifeguard_service_window_deadline_local', 'beach_safety_operator_handoff', 'beach_lifeguard_and_access_anchor'],
    spa_wellness: ['spa_treatment_slot_health_and_accessibility_fit', 'spa_treatment_change_deadline_local', 'spa_reception_and_therapist_handoff', 'spa_reception_and_treatment_anchor'],
    event: ['event_seat_entry_gate_and_cancellation_state', 'event_gate_and_ticket_claim_deadline_local', 'event_box_office_and_security_handoff', 'event_gate_seat_and_exit_anchor'],
  },
  ground_transfer: {
    taxi_ride: ['taxi_license_meter_fare_basis_and_pickup_zone', 'taxi_dispatch_acceptance_deadline_utc', 'licensed_taxi_dispatch_handoff', 'licensed_taxi_pickup_zone_anchor'],
    private_driver: ['private_driver_identity_vehicle_duty_and_multistop_scope', 'private_driver_itinerary_reconfirmation_deadline_utc', 'private_driver_operations_handoff', 'private_driver_meeting_point_anchor'],
    public_local_transit: ['public_transit_agency_route_trip_and_service_calendar', 'public_transit_last_service_decision_deadline_local', 'public_transit_operations_handoff', 'public_transit_boarding_platform_anchor'],
  },
};

exact(schema.definitions.serviceFamily.enum, expectedFamilies, 'schema must enumerate the exact 17 service families');
exact(schema.definitions.canonicalVertical.enum, expectedVerticals, 'schema must use the existing nine canonical verticals');
exact(schema.definitions.lifecycle.required, expectedLifecycle, 'schema must require every lifecycle axis');
assert(schema.properties.families.minItems === 17 && schema.properties.families.maxItems === 17, 'schema must require exactly 17 family profiles');
assert(schema.properties.scenario_count.const === 34, 'schema must close the scenario count at 34');
assert(schema.properties.scenarios.minItems === 34 && schema.properties.scenarios.maxItems === 34, 'schema must require the exact 34-case matrix');
assert(schema.definitions.familyProfile.properties.critical_facts.minItems >= 5, 'schema must require critical facts');
assert(schema.definitions.familyProfile.properties.critical_deadlines.minItems >= 3, 'schema must require critical deadlines');
assert(schema.definitions.familyProfile.properties.required_handoffs.minItems >= 3, 'schema must require handoffs');
assert(schema.definitions.crosswalk.properties.equivalence_claimed.const === false, 'schema must prohibit family/vertical equivalence claims');
assert(schema.definitions.crosswalk.properties.subtype_preserved.const === true, 'schema must require subtype preservation');
assert(schema.definitions.crosswalk.properties.operation_routing.const === 'dedicated_service_family_adapter', 'schema must route operations through dedicated service-family adapters');
assert(schema.definitions.map.properties.detail_surface.const === 'attached_non_occluding_context_panel', 'schema must prohibit an occluding poster-style map detail surface');
for (const key of ['selection_to_plan_required', 'viewport_padding_required', 'rtl_mobile_safe_area_required', 'source_freshness_required', 'reduced_motion_alternative_required']) {
  assert(schema.definitions.map.properties[key].const === true, `schema map contract must force ${key}=true`);
}
for (const definition of ['registryRef', 'familyRef', 'scenarioRef', 'routeRef', 'partitionScopeRef', 'partyRef', 'serviceRef']) {
  assert(schema.definitions[definition].pattern.includes('[a-f0-9]{32}'), `${definition} must be a fixed-width opaque hex reference`);
}
assert(schema.definitions.israelLocal.properties.applicable.const === true, 'schema must require Israel-local applicability');
assert(schema.definitions.financialAxes.properties.netting_prohibited.const === true && schema.definitions.financialAxes.properties.independent_ledgers_required.const === true, 'schema must separate payment, refund, and settlement');
for (const key of ['supplier_dispatched', 'processor_called', 'order_created', 'payment_created', 'refund_created', 'settlement_created', 'raw_pii_stored', 'raw_secret_stored', 'raw_provider_payload_stored', 'real_commercial_claimed']) {
  assert(schema.definitions.scenarioBoundary.properties[key].const === false, `scenario boundary must force ${key}=false`);
}

assert(fixture.contract_version === '1.1.0' && fixture.environment === 'sandbox' && fixture.data_mode === 'synthetic_demo', 'fixture must be an explicit hardened sandbox synthetic demo');
assert(/^rg_syn_[a-f0-9]{32}$/.test(fixture.registry_ref), 'fixture registry reference must be opaque and non-name-bearing');
assert(fixture.family_count === 17 && fixture.families.length === 17, 'fixture must contain 17 families');
assert(fixture.scenario_count === 34 && fixture.scenarios.length === 34, 'fixture must contain two scenarios per family');
assert(/^[a-f0-9]{64}$/.test(fixture.fixture_digest), 'fixture must carry a deterministic digest');
exact(fixture.families.map(profile => profile.service_family), expectedFamilies, 'fixture family order must be deterministic');
exact(fixture.scenarios.map(scenario => `${scenario.service_family}:${scenario.scenario_slot}`), expectedFamilies.flatMap(family => [`${family}:1`, `${family}:2`]), 'fixture must materialize the exact ordered family-slot matrix');

const profiles = new Map();
for (const profile of fixture.families) {
  assert(!profiles.has(profile.service_family), `${profile.service_family} appears more than once`);
  profiles.set(profile.service_family, profile);
  assert(expectedVerticals.includes(profile.canonical_vertical), `${profile.service_family} must crosswalk to an existing vertical`);
  assert(profile.crosswalk.mapping_kind === 'orchestration_bucket_only', `${profile.service_family} crosswalk must remain an orchestration boundary`);
  assert(profile.crosswalk.operation_routing === 'dedicated_service_family_adapter' && profile.crosswalk.orchestration_adapter.endsWith('_orchestration_adapter_v1'), `${profile.service_family} must use a dedicated operation adapter`);
  assert(profile.crosswalk.equivalence_claimed === false && profile.crosswalk.subtype_preserved === true, `${profile.service_family} must preserve subtype without claiming equivalence`);
  exact(Object.keys(profile.lifecycle), expectedLifecycle, `${profile.service_family} must declare every lifecycle stage`);
  for (const [stage, applicability] of Object.entries(profile.lifecycle)) assert(['required', 'conditional', 'not_applicable'].includes(applicability), `${profile.service_family}:${stage} applicability is invalid`);
  assert(profile.family_subtypes.length >= 3 && new Set(profile.family_subtypes).size === profile.family_subtypes.length, `${profile.service_family} needs distinct subtypes`);
  assert(profile.critical_facts.length >= 5 && new Set(profile.critical_facts).size === profile.critical_facts.length, `${profile.service_family} needs critical facts`);
  assert(profile.critical_deadlines.length >= 3 && new Set(profile.critical_deadlines).size === profile.critical_deadlines.length, `${profile.service_family} needs critical deadlines`);
  assert(profile.required_handoffs.length >= 3 && new Set(profile.required_handoffs).size === profile.required_handoffs.length, `${profile.service_family} needs required handoffs`);
  assert(profile.israel_local.applicable === true && profile.israel_local.required_fact_codes.length >= 3, `${profile.service_family} needs Israel-local detail`);
  assert(profile.map.overview_zoom < profile.map.decision_zoom && profile.map.decision_zoom < profile.map.operational_zoom, `${profile.service_family} map zooms must deepen`);
  assert(profile.map.cluster_until_zoom >= profile.map.overview_zoom && profile.map.cluster_until_zoom < profile.map.decision_zoom, `${profile.service_family} clustering must stop before decision resolution`);
  exact(profile.map.resolution_path, ['overview', 'decision', 'operational'], `${profile.service_family} map resolution path must be closed`);
  assert(profile.map.detail_surface === 'attached_non_occluding_context_panel', `${profile.service_family} details must stay attached without occluding Earth`);
  for (const key of ['selection_to_plan_required', 'viewport_padding_required', 'rtl_mobile_safe_area_required', 'source_freshness_required', 'reduced_motion_alternative_required']) assert(profile.map[key] === true, `${profile.service_family}:${key} must be required`);
  assert(profile.map.operational_anchor_codes.length >= 3 && new Set(profile.map.operational_anchor_codes).size === profile.map.operational_anchor_codes.length, `${profile.service_family} needs unique operational map anchors`);
  for (const [subtype, [fact, deadline, handoff, anchor]] of Object.entries(prioritySubtypeOperations[profile.service_family] || {})) {
    assert(profile.family_subtypes.includes(subtype), `${profile.service_family}:${subtype} must remain explicit`);
    assert(profile.critical_facts.includes(fact), `${profile.service_family}:${subtype} needs its own operational fact`);
    assert(profile.critical_deadlines.includes(deadline), `${profile.service_family}:${subtype} needs its own deadline`);
    assert(profile.required_handoffs.includes(handoff), `${profile.service_family}:${subtype} needs its own handoff`);
    assert(profile.map.operational_anchor_codes.includes(anchor), `${profile.service_family}:${subtype} needs its own map anchor`);
  }
}

exact(profiles.get('lodging').family_subtypes, expectedLocalLodging, 'Israel-local lodging subtype fidelity is incomplete');
exact(profiles.get('experience').family_subtypes, expectedLocalExperiences, 'Israel-local experience subtype fidelity is incomplete');
for (const subtype of ['travel_insurance', 'emergency_assistance']) assert(profiles.get('travel_protection').family_subtypes.includes(subtype), `travel protection must preserve ${subtype}`);
for (const [subtype, family] of Object.entries(expectedGroundMobility)) {
  assert(profiles.has(family) && profiles.get(family).family_subtypes.includes(subtype), `${subtype} must remain explicit under its ${family} family`);
}
assert(profiles.get('car_rental').canonical_vertical === 'transfer' && profiles.get('car_rental').crosswalk.equivalence_claimed === false, 'car rental transfer routing must be explicit and non-equivalent');
assert(profiles.get('cruise').canonical_vertical === 'package' && profiles.get('cruise').crosswalk.equivalence_claimed === false, 'cruise package routing must be explicit and non-equivalent');
assert(profiles.get('entry_document_assistance').crosswalk.orchestration_adapter === 'document_assistance_orchestration_adapter_v1', 'entry documents must not route through the activity operation adapter');
assert(profiles.get('entry_document_assistance').critical_deadlines.includes('appointment_or_biometric_deadline_utc') && !fixtureSource.includes('appointment_orbiometric_deadline_utc'), 'entry-document biometric deadline code must be spelled correctly');
sameMembers([...new Set(fixture.families.map(profile => profile.canonical_vertical))], expectedVerticals, 'the 17-family registry must cover all nine canonical verticals');

const scenarioRefs = new Set();
const scenarioNumbers = new Set();
const familySlots = new Set();
const primaryRoutes = new Set();
const fallbackRoutes = new Set();
const emptyPartitionCoverage = { affectedParty: false, unaffectedParty: false, preservedService: false };
const coverage = new Map(expectedFamilies.map(family => [family, { count: 0, slots: new Set(), events: new Set(), afterHours: 0 }]));
for (const [index, scenario] of fixture.scenarios.entries()) {
  assert(!scenarioRefs.has(scenario.scenario_ref), `${scenario.scenario_ref} is duplicated`);
  assert(!scenarioNumbers.has(scenario.scenario_number), `scenario number ${scenario.scenario_number} is duplicated`);
  scenarioRefs.add(scenario.scenario_ref);
  scenarioNumbers.add(scenario.scenario_number);
  assert(scenario.scenario_number === index + 1, `${scenario.scenario_ref} number must be sequential`);
  const familySlot = `${scenario.service_family}:${scenario.scenario_slot}`;
  assert(!familySlots.has(familySlot), `${familySlot} is duplicated`);
  familySlots.add(familySlot);
  assert(/^sc_syn_[a-f0-9]{32}$/.test(scenario.scenario_ref), `${familySlot} scenario reference must be opaque`);
  const profile = profiles.get(scenario.service_family);
  assert(Boolean(profile), `${scenario.scenario_ref} family is missing`);
  assert(profile.canonical_vertical === scenario.canonical_vertical, `${scenario.scenario_ref} crosswalk drifted`);
  assert(profile.family_subtypes.includes(scenario.family_subtype), `${scenario.scenario_ref} lost family subtype`);
  assert(profile.critical_deadlines.includes(scenario.required_deadline_code), `${scenario.scenario_ref} deadline is not in its family profile`);
  assert(profile.required_handoffs.includes(scenario.required_handoff_code), `${scenario.scenario_ref} handoff is not in its family profile`);
  assert(scenario.expected_actions.length >= 3, `${scenario.scenario_ref} response is too shallow`);
  assert(scenario.preservation.preserve_unaffected === true, `${scenario.scenario_ref} must preserve unaffected components`);
  assert(!scenario.preservation.affected_party_refs.some(ref => scenario.preservation.unaffected_party_refs.includes(ref)), `${scenario.scenario_ref} party partition overlaps`);
  assert(!scenario.preservation.affected_service_refs.some(ref => scenario.preservation.preserved_service_refs.includes(ref)), `${scenario.scenario_ref} service partition overlaps`);
  sameMembers([...scenario.preservation.affected_party_refs, ...scenario.preservation.unaffected_party_refs], scenario.preservation.party_scope_refs, `${scenario.scenario_ref} party partition must exhaust its bound scope`);
  sameMembers([...scenario.preservation.affected_service_refs, ...scenario.preservation.preserved_service_refs], scenario.preservation.service_scope_refs, `${scenario.scenario_ref} service partition must exhaust its bound scope`);
  assert(/^sp_syn_[a-f0-9]{32}$/.test(scenario.preservation.partition_scope_ref) && scenario.preservation.partition_complete === true, `${scenario.scenario_ref} preservation must carry an opaque complete scope binding`);
  for (const ref of [...scenario.preservation.party_scope_refs, ...scenario.preservation.affected_party_refs, ...scenario.preservation.unaffected_party_refs]) assert(/^pt_syn_[a-f0-9]{32}$/.test(ref), `${scenario.scenario_ref} party refs must be opaque`);
  for (const ref of [...scenario.preservation.service_scope_refs, ...scenario.preservation.affected_service_refs, ...scenario.preservation.preserved_service_refs]) assert(/^sv_syn_[a-f0-9]{32}$/.test(ref), `${scenario.scenario_ref} service refs must be opaque`);
  emptyPartitionCoverage.affectedParty ||= scenario.preservation.affected_party_refs.length === 0;
  emptyPartitionCoverage.unaffectedParty ||= scenario.preservation.unaffected_party_refs.length === 0;
  emptyPartitionCoverage.preservedService ||= scenario.preservation.preserved_service_refs.length === 0;
  assert(scenario.financial_axes.netting_prohibited === true && scenario.financial_axes.independent_ledgers_required === true, `${scenario.scenario_ref} financial axes are not independent`);
  assert(scenario.after_hours.supplier_dispatched === false, `${scenario.scenario_ref} after-hours route dispatched`);
  assert(/^rt_syn_[a-f0-9]{32}$/.test(scenario.after_hours.route_ref) && /^rt_syn_[a-f0-9]{32}$/.test(scenario.after_hours.fallback_route_ref), `${scenario.scenario_ref} after-hours routes must be opaque`);
  assert(scenario.after_hours.route_ref !== scenario.after_hours.fallback_route_ref && !primaryRoutes.has(scenario.after_hours.route_ref) && !fallbackRoutes.has(scenario.after_hours.fallback_route_ref), `${scenario.scenario_ref} after-hours routes must be distinct and scenario-owned`);
  primaryRoutes.add(scenario.after_hours.route_ref);
  fallbackRoutes.add(scenario.after_hours.fallback_route_ref);
  assert(scenario.after_hours.deadline_code === scenario.required_deadline_code && scenario.after_hours.primary_handoff_code === scenario.required_handoff_code, `${scenario.scenario_ref} after-hours route must bind its deadline and handoff`);
  assert(scenario.after_hours.acknowledgement_sla_seconds <= scenario.after_hours.fallback_after_seconds && scenario.after_hours.fallback_after_seconds <= scenario.after_hours.customer_update_sla_seconds, `${scenario.scenario_ref} after-hours clocks are out of order`);
  assert(scenario.after_hours.timezone === 'Asia/Jerusalem' && scenario.after_hours.evidence_required === true, `${scenario.scenario_ref} after-hours timezone and evidence must be explicit`);
  if (scenario.after_hours.required) {
    assert(scenario.after_hours.coverage_state === 'outside_declared_window' && scenario.after_hours.escalation_tier === 2 && scenario.after_hours.safety_check_required === true && scenario.after_hours.escalation_state === 'planned', `${scenario.scenario_ref} required after-hours escalation is incomplete`);
  } else {
    assert(scenario.after_hours.coverage_state === 'within_declared_window' && scenario.after_hours.escalation_tier === 0 && scenario.after_hours.safety_check_required === false && scenario.after_hours.escalation_state === 'standby', `${scenario.scenario_ref} in-hours route must remain standby`);
  }
  for (const key of ['supplier_dispatched', 'processor_called', 'order_created', 'payment_created', 'refund_created', 'settlement_created', 'raw_pii_stored', 'raw_secret_stored', 'raw_provider_payload_stored', 'real_commercial_claimed']) {
    assert(scenario.boundary[key] === false, `${scenario.scenario_ref}:${key} must remain false`);
  }
  const familyCoverage = coverage.get(scenario.service_family);
  familyCoverage.count += 1;
  familyCoverage.slots.add(scenario.scenario_slot);
  familyCoverage.events.add(scenario.event_state);
  familyCoverage.afterHours += scenario.after_hours.required ? 1 : 0;
}
assert(familySlots.size === 34, 'the closed matrix must contain exactly 34 unique family-slot keys');
assert(Object.values(emptyPartitionCoverage).every(Boolean), 'fixture must cover legitimate empty affected, unaffected, and preserved partitions');
for (const [family, familyCoverage] of coverage) {
  assert(familyCoverage.count === 2, `${family} must have two scenarios`);
  sameMembers([...familyCoverage.slots], [1, 2], `${family} must cover both deterministic slots`);
  sameMembers([...familyCoverage.events], ['stale', 'missed'], `${family} must cover stale and missed events`);
  assert(familyCoverage.afterHours === 1, `${family} must have one after-hours escalation`);
}
const rentalSubstitution = fixture.scenarios.find(scenario => scenario.service_family === 'car_rental' && scenario.trigger === 'vehicle_substitution_missed');
assert(rentalSubstitution?.required_handoff_code === 'rental_inventory_and_desk_servicing_handoff', 'pre-pickup rental substitution must route to inventory and desk servicing, not roadside assistance');

assert(!/(?:El\s*Al|Arkia|Israir|Booking\.com|Expedia|Isracard|Fly\s*Card|FlyAll|Visa\s+Inc)/i.test(fixtureSource), 'fixture cannot contain real supplier or commercial brand identities');
assert(!/"(?:amount|price|saving|fare)_?[a-z_]*"\s*:/i.test(fixtureSource), 'fixture cannot seed numeric commercial claims');
assert(!/"(?:email|phone|address|passport_number|document_number|card_number|cvv|cvc|password|secret|api_key|access_token|provider_payload|supplier_payload|supplier_name|provider_name|property_name)"\s*:/i.test(fixtureSource), 'fixture cannot contain raw PII, secrets, payloads, or supplier identities');

for (const family of expectedFamilies) assert(taxonomy.includes(`'${family}'`), `PHP taxonomy is missing ${family}`);
for (const stage of expectedLifecycle) assert(taxonomy.includes(`'${stage}'`), `PHP taxonomy is missing lifecycle ${stage}`);
for (const subtype of [...expectedLocalLodging, ...expectedLocalExperiences, ...Object.keys(expectedGroundMobility), 'travel_insurance', 'emergency_assistance']) assert(taxonomy.includes(`'${subtype}'`), `PHP taxonomy is missing required subtype ${subtype}`);
for (const marker of ['orchestration_bucket_only', 'dedicated_service_family_adapter', 'document_assistance_orchestration_adapter_v1', 'equivalence_claimed', 'subtype_preserved', 'critical_facts', 'critical_deadlines', 'required_handoffs', 'israel_local', 'overview_zoom', 'decision_zoom', 'operational_zoom', 'attached_non_occluding_context_panel', 'priority_subtype_operations']) assert(taxonomy.includes(marker), `PHP taxonomy is missing ${marker}`);
for (const marker of ['family_scenario_depth_invalid', 'family_subtype_operation_invalid', 'scenario_preservation_invalid', 'same_ref_set', 'opaque_ref', 'scenario_financial_separation_invalid', 'sensitive_material_rejected', 'fixture_digest_invalid']) assert(policy.includes(marker), `PHP policy is missing ${marker}`);
assert(!/register_rest_route|wp_remote_(?:get|post|request)|\$wpdb|curl_(?:exec|init)|file_put_contents|update_option|do_action|apply_filters|new\s+Tra_Vel_Commerce_Order/i.test(`${taxonomy}\n${policy}`), 'registry code cannot expose routes, use storage/network hooks, or create orders');
for (const marker of [
  "17 === $fixture['family_count']", "34 === $fixture['scenario_count']", "preserve_unaffected",
  "netting_prohibited", "supplier_dispatched", "expected_local_lodging", "expected_local_experiences",
  "expected_local_ground", "priority_subtypes", "partition_complete", "attached_non_occluding_context_panel", "zero dispatch",
]) assert(runtime.includes(marker), `runtime gate is missing ${marker}`);

console.log(`Service breadth registry contracts passed (${assertions} assertions; 1 closed Draft-07 schema; 17 families; 34 scenarios).`);
