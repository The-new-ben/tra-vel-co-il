import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(import.meta.dirname, '../..');
const vip = path.join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'vip');
const paths = {
  policy: path.join(vip, 'class-tra-vel-trip-change-stress-policy.php'),
  engine: path.join(vip, 'class-tra-vel-trip-change-stress-engine.php'),
  schema: path.join(root, 'plugin', 'tra-vel-agent-core', 'schemas', 'private', 'trip-change-stress-plan.schema.json'),
  runtime: path.join(root, 'scripts', 'ci', 'validate-trip-change-stress-runtime.php'),
  themeWorkflow: path.join(root, '.github', 'workflows', 'theme-ci.yml'),
  deployWorkflow: path.join(root, '.github', 'workflows', 'deploy-agent-core.yml'),
};

let assertions = 0;
const failures = [];
const check = (condition, message) => {
  assertions += 1;
  if (!condition) failures.push(message);
};
const same = (actual, expected) => JSON.stringify(actual) === JSON.stringify(expected);

const source = {};
let schema = {};
try {
  for (const [name, file] of Object.entries(paths)) {
    check(fs.existsSync(file), `${name} file is missing`);
    check(fs.statSync(file).size > 100, `${name} file is unexpectedly empty`);
    if (name === 'schema') schema = JSON.parse(fs.readFileSync(file, 'utf8'));
    else source[name] = fs.readFileSync(file, 'utf8');
  }
} catch (error) {
  failures.push(`Trip-change stress slice is unreadable: ${error.message}`);
}

const scenarios = [
  'overlapping_connection_disruptions',
  'flight_only_change',
  'five_person_package_constraints',
  'aircraft_or_terminal_change',
  'israel_gtfs_degraded',
];
const strategies = [
  'protected_connection_resequence',
  'independent_connection_rebook_review',
  'preserve_unaffected_and_revalidate_flight',
  'split_constrained_party_scope',
  'hold_package_for_party_clearance',
  'protected_aircraft_terminal_revalidation',
  'official_channel_and_human_transit_fallback',
];
const rechecks = [
  'seat_assignment',
  'special_service_request',
  'wheelchair_assistance',
  'baggage_allowance',
  'minimum_connection_time',
];
const blockers = [
  'eligibility_not_verified',
  'consent_pending',
  'guardian_authority_missing',
  'accessibility_ack_pending',
  'official_source_stale',
  'official_source_unavailable',
  'supplier_verification_pending',
];
const fallbackChannels = [
  'israel_official_route_planner',
  'official_transport_operator_channel',
  'human_travel_agent',
];
const rootFields = [
  'contract_version', 'environment', 'plan_ref', 'plan_digest', 'scenario_ref', 'trip_ref',
  'scenario_type', 'observed_at', 'dependency_order', 'component_partition', 'traveler_partition',
  'recovery_candidates', 'selected_candidate_ref', 'actions', 'required_rechecks',
  'transit_source_gate', 'summary', 'private_boundary',
];

check(schema.$schema === 'http://json-schema.org/draft-07/schema#', 'schema must use Draft-07');
check(schema.$id === 'https://tra-vel.co.il/schemas/private/trip-change-stress-plan.schema.json', 'schema must use its canonical private ID');
check(schema.additionalProperties === false, 'schema root must reject unknown fields');
check(same(schema.required, rootFields), 'schema must require the exact root field order');
check(schema.properties?.contract_version?.const === '1.0.0', 'contract version must remain 1.0.0');
check(schema.properties?.environment?.const === 'private_simulation', 'environment must remain private simulation');
check(same(schema.definitions?.scenarioType?.enum, scenarios), 'scenario vocabulary must contain exactly five adversarial cases');
check(same(schema.definitions?.strategy?.enum, strategies), 'recovery strategy vocabulary changed');
check(same(schema.definitions?.recheckCode?.enum, rechecks), 'aircraft/terminal recheck bundle changed');
check(same(schema.definitions?.blockerCode?.enum, blockers), 'constraint/source blocker vocabulary changed');
check(same(schema.definitions?.transitSourceGate?.properties?.fallback_channels?.items?.enum, fallbackChannels), 'official/human GTFS fallbacks changed');
check(same(schema.definitions?.transitSourceGate?.properties?.source_state?.enum, ['stale', 'unavailable', 'not_applicable']), 'degraded GTFS output must close out current truth');

const visitClosedObjects = (node, pointer = '#') => {
  if (!node || typeof node !== 'object') return;
  if (node.type === 'object') {
    check(node.additionalProperties === false, `${pointer} must reject unknown fields`);
    const properties = Object.keys(node.properties || {});
    check(same(node.required || [], properties), `${pointer} must require every property in exact order`);
  }
  for (const [key, child] of Object.entries(node)) visitClosedObjects(child, `${pointer}/${key}`);
};
visitClosedObjects(schema);

for (const partition of ['componentPartition', 'travelerPartition']) {
  check(same(Object.keys(schema.definitions?.[partition]?.properties || {}), ['universe_refs', 'affected_refs', 'preserved_refs', 'blocked_refs']), `${partition} must expose only exhaustive partition fields`);
}
check(schema.properties?.recovery_candidates?.minItems === 1 && schema.properties?.recovery_candidates?.maxItems === 2, 'candidate set must stay bounded');
check(schema.definitions?.recoveryCandidate?.properties?.execution_authorized?.const === false, 'candidate cannot authorize execution');
check(schema.definitions?.recoveryCandidate?.properties?.supplier_action_claimed?.const === false, 'candidate cannot claim supplier action');
check(schema.definitions?.recoveryCandidate?.properties?.commercial_fact_claimed?.const === false, 'candidate cannot claim a commercial fact');
check(schema.definitions?.recoveryCandidate?.properties?.authorization_effect?.const === 'none', 'candidate must have no authorization effect');
check(schema.definitions?.recheck?.properties?.truth_state?.const === 'pending_supplier_verification', 'rechecks must remain pending supplier verification');
check(schema.definitions?.recheck?.properties?.execution_authorized?.const === false, 'rechecks cannot authorize execution');
check(schema.definitions?.transitSourceGate?.properties?.route_claim_allowed?.const === false, 'degraded source gate cannot allow a route claim');
check(schema.definitions?.transitSourceGate?.properties?.current_route_claim_present?.const === false, 'source gate cannot invent a current route');
check(schema.definitions?.summary?.properties?.selected_candidate_count?.const === 1, 'summary must prove one selected recovery');
check(schema.definitions?.summary?.properties?.side_effect_count?.const === 0, 'summary must prove zero side effects');

for (const field of [
  'public_serialization_allowed', 'storage_written', 'rest_route_registered', 'network_called',
  'provider_called', 'supplier_dispatched', 'payment_executed', 'booking_modified',
  'route_availability_claimed', 'commercial_authority',
]) {
  check(schema.definitions?.privateBoundary?.properties?.[field]?.const === false, `private boundary ${field} must be structurally false`);
}
check(schema.definitions?.privateBoundary?.properties?.server_only?.const === true, 'plan must remain server-only');
check(schema.definitions?.privateBoundary?.properties?.planning_only?.const === true, 'plan must remain planning-only');
check(schema.definitions?.privateBoundary?.properties?.authorization_effect?.const === 'none', 'plan must grant no authority');
check(schema.definitions?.privateBoundary?.properties?.side_effect_count?.const === 0, 'plan boundary must report zero side effects');

for (const marker of [
  'class Tra_Vel_Trip_Change_Stress_Policy',
  "const CONTRACT_VERSION = '1.0.0'",
  'function input', 'function plan', 'function topological_order', 'function dependency_order_valid',
  'function partition', "'_partition_overlap'",
  'flight_only_cross_vertical_invalid', 'party_constraint_partition_invalid',
  'overlap_partition_invalid', 'party_action_ownership_invalid', 'change_traveler_ownership_invalid',
  'recheck_bundle_incomplete', 'gtfs_source_gate_invalid', 'gtfs_truth_binding_invalid',
  'traveler_order_invalid', 'component_order_invalid', 'change_order_invalid', 'action_order_invalid',
  'checkdate(', "createFromFormat( '!Y-m-d\\TH:i:s\\Z'", "->format( 'Y-m-d\\TH:i:s\\Z' )",
  "false !== $candidate['execution_authorized']", "false !== $candidate['supplier_action_claimed']",
  "false !== $candidate['commercial_fact_claimed']", "false !== $action['supplier_action_claimed']",
  "false !== $recheck['execution_authorized']", "false !== $recheck['supplier_action_claimed']",
  "false !== $gate['route_claim_allowed']", "false !== $gate['current_route_claim_present']",
  'OFFICIAL_FALLBACK_CHANNELS', 'supplier_dispatched', 'commercial_authority',
]) check(source.policy?.includes(marker), `policy is missing ${marker}`);

for (const marker of [
  'class Tra_Vel_Trip_Change_Stress_Engine', 'function plan', 'downstream_scope',
  'selected_for_review', 'planning_alternative', 'constraint_blockers',
  'owned_components', "strcmp( $left['action_ref'], $right['action_ref'] )",
  "'route_claim_allowed'       => false", "'supplier_dispatched'        => false",
  "'booking_modified'           => false", "'commercial_authority'       => false",
  "'side_effect_count'          => 0",
]) check(source.engine?.includes(marker), `engine is missing ${marker}`);

for (const value of [...scenarios, ...strategies, ...rechecks, ...blockers, ...fallbackChannels]) {
  check(source.policy?.includes(`'${value}'`), `policy is missing closed value ${value}`);
}

const combinedPhp = `${source.policy || ''}\n${source.engine || ''}`;
for (const forbidden of [
  'register_rest_route(', 'add_action(', 'add_filter(', 'do_action(', 'apply_filters(', '$wpdb',
  'wp_remote_get(', 'wp_remote_post(', 'wp_remote_request(', 'curl_exec(', 'file_put_contents(',
  'fopen(', 'update_option(', 'add_option(', 'delete_option(', 'random_bytes(', 'wp_mail(',
  'mysqli_', 'new PDO(', '->dispatch(', '->charge(', '->refund(', '->reserve(', '->book(',
]) check(!combinedPhp.toLowerCase().includes(forbidden.toLowerCase()), `pure planner crossed its no-REST/no-storage/no-network/no-provider/no-commerce boundary: ${forbidden}`);
for (const php8Only of [/\bmatch\s*\(/, /\bstr_(?:contains|starts_with|ends_with)\s*\(/, /\?->/, /\bfn\s*\(/]) {
  check(!php8Only.test(combinedPhp), `planner must remain PHP 7.4 compatible; found ${php8Only}`);
}

for (const marker of [
  'three overlapping connection disruptions preserve viable components',
  'flight-only change leaves lodging insurance and ground untouched',
  'five-person package blocks only necessary people and components',
  'aircraft and terminal change rechecks every dependent service fact',
  'Israel GTFS {$gtfs_state} fails closed to official and human channels',
  'sealed plan rejects authority and partition attacks',
  'scenario partitions and traveler ownership reject unrelated scope',
  'false-like type confusion cannot cross zero-authority boundaries',
  'selected recovery requires the exact impacted scope',
  'canonical ordering and real UTC dates reject ambiguous seals',
  'GTFS degraded truth is exact and current state is out of scope',
  'partitions must be disjoint', 'partitions must be exhaustive',
  'exactly one recovery candidate must be selected', 'zero execution',
  'one blocked traveler cannot inherit another traveler component',
  'current GTFS is explicitly outside the degraded-source stress vocabulary',
  'February 31 must fail checkdate and UTC round-trip validation',
  '$scenarios >= 12',
]) check(source.runtime?.includes(marker), `runtime is missing proof: ${marker}`);
check(source.runtime?.includes('106 assertions') || source.runtime?.includes('$assertions'), 'runtime must count assertions');

for (const workflow of ['themeWorkflow', 'deployWorkflow']) {
  check((source[workflow]?.match(/php scripts\/ci\/validate-trip-change-stress-runtime\.php/g) || []).length === 2, `${workflow} must run the trip-change runtime in both PHP and package gates`);
  check((source[workflow]?.match(/node scripts\/ci\/validate-trip-change-stress-contract\.mjs/g) || []).length === 1, `${workflow} must run the trip-change contract once`);
}
check(source.deployWorkflow?.includes('scripts/ci/validate-trip-change-stress-*.mjs') && source.deployWorkflow?.includes('scripts/ci/validate-trip-change-stress-*.php'), 'Agent deploy path filters must include validator-only trip-change changes');

if (failures.length) {
  console.error('Trip-change stress contract validation failed:');
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Trip-change stress contract passed (${assertions} assertions; five closed cases; exhaustive partitions; PHP 7.4; zero execution).`);
