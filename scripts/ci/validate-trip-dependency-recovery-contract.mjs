import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const paths = {
  graphSchema: path.join(root, 'plugin/tra-vel-agent-core/schemas/private/trip-dependency-graph.schema.json'),
  recoverySchema: path.join(root, 'plugin/tra-vel-agent-core/schemas/private/trip-recovery-plan.schema.json'),
  taxonomy: path.join(root, 'plugin/tra-vel-agent-core/includes/vip/class-tra-vel-trip-dependency-taxonomy.php'),
  policy: path.join(root, 'plugin/tra-vel-agent-core/includes/vip/class-tra-vel-trip-dependency-policy.php'),
  planner: path.join(root, 'plugin/tra-vel-agent-core/includes/vip/class-tra-vel-trip-recovery-planner.php'),
  runtime: path.join(root, 'scripts/ci/validate-trip-dependency-recovery-runtime.php'),
};
const bootstrapPath = path.join(root, 'plugin/tra-vel-agent-core/includes/vip/bootstrap.php');
const workflowPaths = [
  path.join(root, '.github/workflows/theme-ci.yml'),
  path.join(root, '.github/workflows/deploy-agent-core.yml'),
];

let assertions = 0;
function assert(condition, message) {
  assertions += 1;
  if (!condition) {
    throw new Error(message);
  }
}

for (const [label, file] of Object.entries(paths)) {
  assert(fs.existsSync(file), `${label} is missing`);
  assert(fs.statSync(file).size > 100, `${label} is unexpectedly empty`);
}
assert(fs.existsSync(bootstrapPath), 'VIP bootstrap is missing');
for (const file of workflowPaths) assert(fs.existsSync(file), `required workflow is missing: ${file}`);

const graph = JSON.parse(fs.readFileSync(paths.graphSchema, 'utf8'));
const recovery = JSON.parse(fs.readFileSync(paths.recoverySchema, 'utf8'));
const sources = Object.fromEntries(
  ['taxonomy', 'policy', 'planner', 'runtime'].map((key) => [key, fs.readFileSync(paths[key], 'utf8')]),
);
const bootstrap = fs.readFileSync(bootstrapPath, 'utf8');
const workflows = workflowPaths.map((file) => fs.readFileSync(file, 'utf8'));

function exactMembers(actual, expected, label) {
  assert(Array.isArray(actual), `${label} must be an array`);
  assert(actual.length === expected.length, `${label} count mismatch`);
  assert(new Set(actual).size === actual.length, `${label} must be unique`);
  assert(expected.every((value) => actual.includes(value)), `${label} is missing a canonical member`);
  assert(actual.every((value) => expected.includes(value)), `${label} contains an unsupported member`);
}

function assertClosedObjects(value, trail) {
  if (!value || typeof value !== 'object') return;
  if (value.type === 'object') {
    assert(value.additionalProperties === false, `${trail} must fail closed with additionalProperties:false`);
    assert(value.properties && typeof value.properties === 'object', `${trail} must declare exact properties`);
    if (Array.isArray(value.required)) {
      exactMembers(value.required, Object.keys(value.properties), `${trail}.required`);
    }
  }
  for (const [key, child] of Object.entries(value)) {
    assertClosedObjects(child, `${trail}.${key}`);
  }
}

assert(graph.$schema === 'http://json-schema.org/draft-07/schema#', 'graph must use Draft-07');
assert(recovery.$schema === 'http://json-schema.org/draft-07/schema#', 'recovery must use Draft-07');
assert(graph.additionalProperties === false, 'graph root must be closed');
assert(recovery.additionalProperties === false, 'recovery root must be closed');
assertClosedObjects(graph, 'graph');
assertClosedObjects(recovery, 'recovery');

const verticals = ['flight', 'accommodation', 'package', 'transfer', 'activity', 'dining', 'insurance', 'connectivity', 'equipment'];
const gates = ['customer_consent', 'financial_authority', 'document_admissibility', 'guardian_or_dependent_authority', 'accessibility_supplier_ack', 'supplier_truth', 'human_operator_review'];
const events = [
  'flight.cancelled', 'flight.delayed_connection_at_risk', 'flight.missed_connection',
  'accommodation.overbooked', 'accommodation.late_arrival_risk', 'transfer.failed',
  'activity.weather_closed', 'dining.closed', 'insurance.incident_reported',
  'connectivity.outage', 'equipment.lost', 'local_tourism.official_closure',
  'traveler.document_changed', 'traveler.authority_changed', 'traveler.accessibility_ack_lost',
  'financial.partial_refund_observed', 'financial.payment_state_uncertain', 'supplier.response_stale',
];
const strategies = [
  'protected_flight_rebook', 'route_resequence_with_buffer', 'preserve_unaffected_components',
  'replacement_stay_with_arrival_protection', 'late_arrival_reconfirmation', 'replacement_transfer',
  'reschedule_weather_window', 'replacement_activity_or_local_option', 'replacement_dining',
  'emergency_assistance_handoff', 'coverage_preauthorization_review',
  'alternate_connectivity_with_offline_pack', 'emergency_equipment_replacement',
  'loss_or_damage_claim_review', 'local_closure_reroute', 'document_and_itinerary_recheck',
  'authority_reverification', 'accessibility_preserving_replan', 'financial_reconciliation',
  'supplier_reverification', 'safe_hold_and_human_review',
];

exactMembers(graph.definitions.vertical.enum, verticals, 'graph verticals');
exactMembers(recovery.definitions.vertical.enum, verticals, 'recovery verticals');
exactMembers(recovery.definitions.gateCode.enum, gates, 'recovery gates');
exactMembers(recovery.definitions.event.properties.type.enum, events, 'recovery events');
exactMembers(recovery.definitions.candidate.properties.strategy.enum, strategies, 'recovery strategies');
assert(graph.properties.nodes.minItems === 1, 'a real trip graph must allow only the services that traveler actually has');
assert(graph.properties.nodes.maxItems === 128, 'graph node bound must match runtime policy');
assert(graph.properties.edges.minItems === 0, 'a one-item trip must not require an invented dependency edge');
assert(graph.properties.edges.maxItems === 384, 'graph edge bound must match runtime policy');
assert(recovery.properties.gates.minItems === 7 && recovery.properties.gates.maxItems === 7, 'recovery requires exactly seven independent gates');
assert(recovery.definitions.eventLedger.properties.events.maxItems === 64, 'event ledger bound must match runtime policy');

const graphBoundary = graph.definitions.privateBoundary.properties;
assert(graphBoundary.server_only.const === true, 'graph must remain server-only');
assert(graphBoundary.public_serialization_allowed.const === false, 'graph cannot be publicly serialized');
assert(graphBoundary.vault_pointers_only.const === true, 'graph must use vault pointers only');
assert(graphBoundary.supplier_action_claimed.const === false, 'graph cannot claim supplier action');

const recoveryBoundary = recovery.definitions.privateBoundary.properties;
assert(recoveryBoundary.planning_only.const === true, 'recovery must remain planning-only');
assert(recoveryBoundary.execution_dispatched.const === false, 'recovery planner cannot dispatch execution');
assert(recoveryBoundary.processor_called.const === false, 'recovery planner cannot call a processor');
assert(recoveryBoundary.supplier_action_claimed.const === false, 'recovery cannot claim supplier action');
assert(recovery.definitions.eventBoundary.properties.authorization_effect.const === 'none', 'incident observation cannot authorize work');
assert(recovery.definitions.candidate.properties.authorization_effect.const === 'none', 'candidate cannot authorize work');
assert(recovery.definitions.completion.properties.authorization_effect.const === 'none', 'completion record cannot authorize work');

for (const [key, source] of Object.entries(sources)) {
  assert(!source.startsWith('\uFEFF'), `${key} must not contain a BOM`);
  assert(!/\bfn\s*\(/.test(source), `${key} must remain PHP 7.4 compatible`);
  assert(!/\bmatch\s*\(/.test(source), `${key} must not use PHP 8 match`);
  assert(!/\bwp_remote_(?:get|post|request)\s*\(/.test(source), `${key} cannot make WordPress HTTP calls`);
  assert(!/\bcurl_(?:init|exec|multi_exec)\s*\(/.test(source), `${key} cannot make cURL calls`);
  assert(!/\bfsockopen\s*\(/.test(source), `${key} cannot open sockets`);
  assert(!/\b(?:mail|wp_mail)\s*\(/.test(source), `${key} cannot send messages`);
  assert(!/\b(?:add_action|add_filter|do_action)\s*\(/.test(source), `${key} must have no hook side effects`);
}

for (const token of verticals) {
  assert(sources.taxonomy.includes(`'${token}'`), `taxonomy is missing vertical ${token}`);
}
for (const token of gates) {
  assert(sources.taxonomy.includes(`'${token}'`), `taxonomy is missing gate ${token}`);
}
for (const token of events) {
  assert(sources.taxonomy.includes(`'${token}'`), `taxonomy is missing event ${token}`);
  assert(sources.runtime.includes(`'${token}'`) || ['dining.closed', 'traveler.document_changed', 'traveler.authority_changed', 'traveler.accessibility_ack_lost'].includes(token), `runtime lacks a focused or catalog-backed ${token} path`);
}
for (const token of strategies) {
  assert(sources.taxonomy.includes(`'${token}'`), `taxonomy is missing strategy ${token}`);
}

for (const required of [
  'graph_successor', 'recovery_successor', 'derived_impact', 'has_cycle', 'event_fingerprint',
  'graph_digest', 'recovery_digest', 'customer_projection', 'event_history_rewritten',
  'recovery_state_transition_invalid', 'recovery_completion_partition_invalid',
]) {
  assert(sources.policy.includes(required), `policy is missing ${required}`);
}
for (const required of [
  'downstream', 'out_of_order_event_refs', 'stale_response_event_refs', 'duplicate_count',
  'human_escalation_required', 'safety_handoff_required', 'financial_reconciliation',
]) {
  assert(sources.planner.includes(required), `planner is missing ${required}`);
}

const runtimeScenarios = [
  'real partial itinerary without invented products',
  'cascading flight cancellation', 'missed connection', 'hotel overbooking',
  'late arrival threatens hotel', 'transfer failure', 'activity weather closure',
  'insurance incident', 'eSIM outage', 'equipment loss', 'official local-tourism closure',
  'minor dependent and accessibility constraints', 'concurrent incidents',
  'stale supplier response', 'duplicate and out-of-order observations',
  'partial refund and payment uncertainty', 'privacy and execution boundary attacks',
  'graph integrity attacks', 'immutable graph successor', 'verified completion proof',
];
for (const scenario of runtimeScenarios) {
  assert(sources.runtime.includes(`recovery_scenario( '${scenario}' )`), `runtime is missing scenario: ${scenario}`);
}

const forbiddenSchemaProperties = ['passport_number', 'identity_number', 'card_number', 'card_pan', 'cvv', 'cvc', 'medical_narrative', 'diagnosis', 'bearer_token', 'raw_supplier_payload'];
const schemaText = JSON.stringify({graph, recovery});
for (const property of forbiddenSchemaProperties) {
  assert(!schemaText.includes(`"${property}":`), `private schemas must not expose ${property}`);
}

assert(sources.runtime.includes('zero supplier dispatch'), 'runtime summary must state the execution boundary');
assert(sources.runtime.includes("$scenarios >= 20"), 'runtime must enforce at least twenty scenarios');
assert(sources.runtime.includes("count( Tra_Vel_Trip_Dependency_Taxonomy::VERTICALS ) === 9"), 'runtime must enforce all verticals');
assert(sources.runtime.includes("9 === count( array_unique( array_column( $graph['nodes'], 'vertical' ) ) )"), 'stress fixture must exercise all nine verticals without forcing them into every customer trip');
assert(sources.runtime.includes("count( Tra_Vel_Trip_Dependency_Taxonomy::GATE_CODES ) === 7"), 'runtime must enforce independent gates');

for (const filename of [
  'class-tra-vel-trip-dependency-taxonomy.php',
  'class-tra-vel-trip-dependency-policy.php',
  'class-tra-vel-trip-recovery-planner.php',
]) {
  assert(bootstrap.includes(filename), `VIP bootstrap must load ${filename}`);
}
for (const workflow of workflows) {
  assert(workflow.includes('php scripts/ci/validate-trip-dependency-recovery-runtime.php'), 'both CI workflows must run the trip recovery runtime gate');
  assert(workflow.includes('node scripts/ci/validate-trip-dependency-recovery-contract.mjs'), 'both CI workflows must run the trip recovery contract gate');
}

console.log(`Trip dependency/recovery contract passed (${assertions} assertions; closed schemas; PHP 7.4; no network or dispatch).`);
