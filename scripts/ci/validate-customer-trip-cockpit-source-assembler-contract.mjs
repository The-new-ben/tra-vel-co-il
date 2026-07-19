import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(import.meta.dirname, '../..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const failures = [];
const requireText = (source, needle, message) => {
  if (!source.includes(needle)) failures.push(message);
};

const assembler = read('plugin/tra-vel-agent-core/includes/vip/class-tra-vel-customer-trip-cockpit-source-assembler.php');
const provider = read('plugin/tra-vel-agent-core/includes/vip/interface-tra-vel-customer-trip-cockpit-source-provider.php');
const bootstrap = read('plugin/tra-vel-agent-core/includes/vip/bootstrap.php');
const plugin = read('plugin/tra-vel-agent-core/tra-vel-agent-core.php');
const controller = read('plugin/tra-vel-agent-core/includes/vip/class-tra-vel-customer-trip-cockpit-controller.php');
const runtime = read('scripts/ci/validate-customer-trip-cockpit-source-assembler-runtime.php');

requireText(provider, 'get_authoritative_snapshot', 'The source-provider interface does not expose its one closed authoritative read.');
requireText(assembler, "tra_vel_customer_trip_cockpit_authoritative_lifecycle_event", 'The server lifecycle action is missing.');
requireText(assembler, "tra_vel_customer_trip_cockpit_source_provider", 'The explicit authoritative provider filter is missing.');
requireText(assembler, "tra_vel_customer_trip_cockpit_source_write_authorized", 'The exact in-flight source authorization is missing.');
requireText(assembler, 'Tra_Vel_Traveler_Principal::account_ref', 'Account identity is not derived from the server principal.');
requireText(assembler, 'cockpit_owner_scope_digest', 'Owner scope is not derived through the keyed server principal.');
requireText(assembler, 'previous_projection_digest', 'Successor ancestry is not derived by the assembler.');
requireText(assembler, 'same_customer_truth', 'Semantic lifecycle replay suppression is missing.');
requireText(assembler, 'commit_server_source', 'The assembler is not connected to the private cockpit store.');
requireText(bootstrap, 'interface-tra-vel-customer-trip-cockpit-source-provider.php', 'VIP bootstrap does not load the authoritative provider contract.');
requireText(bootstrap, 'class-tra-vel-customer-trip-cockpit-source-assembler.php', 'VIP bootstrap does not load the source assembler.');
requireText(plugin, 'Tra_Vel_Customer_Trip_Cockpit_Source_Assembler', 'Plugin startup does not register the server source assembler.');
requireText(runtime, 'do_action( Tra_Vel_Customer_Trip_Cockpit_Source_Assembler::LIFECYCLE_ACTION', 'Runtime coverage does not drive the real lifecycle action.');
requireText(runtime, 'The authoritative lifecycle event did not produce a stored projection.', 'Runtime coverage does not prove event-to-store materialization.');

for (const forbidden of ['register_rest_route', 'WP_REST_Request', '$_GET', '$_POST', '$_REQUEST', 'get_json_params']) {
  if (assembler.includes(forbidden)) failures.push(`The server-only assembler unexpectedly contains public/request surface: ${forbidden}`);
}
for (const forbidden of ['source_provider', 'source_write_authorized', 'authoritative_lifecycle_event']) {
  if (controller.includes(forbidden)) failures.push(`The customer REST controller unexpectedly reaches the private write boundary: ${forbidden}`);
}

if (failures.length) {
  console.error('Customer Trip Cockpit source assembler contract failed:');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('Customer Trip Cockpit source assembler contract passed: payload-free lifecycle events, explicit server provider, derived owner/ancestry, exact synchronous write authorization, and no REST write surface.');
