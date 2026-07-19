import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const schemaPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'traveler-workspace.schema.json');
const schema = JSON.parse(readFileSync(schemaPath, 'utf8'));
const controller = readFileSync(join(repoRoot, 'theme', 'tra-vel-v2', 'inc', 'workspace', 'class-traveler-workspace-controller.php'), 'utf8');
const docs = readFileSync(join(repoRoot, 'docs', 'TRAVELER_WORKSPACE.md'), 'utf8');
const failures = [];
const fail = message => failures.push(message);

for (const key of ['version', 'items', 'preferences', 'meta']) {
  if (!(schema.required || []).includes(key)) fail(`Workspace schema must require ${key}.`);
}
if (schema.properties?.version?.const !== 1) fail('Workspace contract version must remain 1.');
if (JSON.stringify(Object.keys(schema.properties || {}).sort()) !== JSON.stringify(['items', 'meta', 'preferences', 'version'])) fail('Public workspace v1 response shape changed.');
if (schema.properties?.items?.maxItems !== 50) fail('Workspace must enforce a 50-item storage ceiling.');
const item = schema.properties?.items?.items;
for (const key of ['id', 'kind', 'external_id', 'title', 'price_amount', 'currency', 'data_mode', 'href', 'saved_at', 'watch']) {
  if (!(item?.required || []).includes(key)) fail(`Saved item contract must require ${key}.`);
}
for (const kind of ['destination', 'route', 'flight', 'hotel', 'package']) {
  if (!(item?.properties?.kind?.enum || []).includes(kind)) fail(`Saved item kind is missing: ${kind}.`);
}
if (item?.properties?.watch?.properties?.delivery_enabled?.const !== false) fail('Price-watch delivery must be contractually disabled.');
if (schema.properties?.meta?.properties?.sensitive_data_allowed?.const !== false) fail('Workspace contract must reject sensitive-data storage.');
if (schema.properties?.meta?.properties?.price_watch_delivery_enabled?.const !== false) fail('Workspace metadata must not claim active alert delivery.');
if (schema.additionalProperties !== false || item?.additionalProperties !== false) fail('Workspace and saved items must reject undeclared fields.');

if (!controller.includes("'/' . $this->rest_base . '/sync'")) fail('Workspace controller is missing the bounded account sync route.');
if (!/'methods'\s*=>\s*'PUT'[\s\S]{0,160}'callback'\s*=>\s*array\( \$this, 'sync_workspace' \)/.test(controller)) fail('Workspace sync must be an exact PUT endpoint.');
if (!/'sync_workspace'[\s\S]{0,240}'permission_callback'\s*=>\s*array\( \$this, 'can_use_workspace' \)/.test(controller)) fail('Workspace sync is not protected by the account permission callback.');
for (const marker of ["'items'", "'deleted_item_ids'", "'maxItems'          => self::MAX_ITEMS", "'validate_callback' => 'rest_validate_request_arg'"]) {
  if (!controller.includes(marker)) fail(`Workspace sync route args are missing ${marker}.`);
}
if (!controller.includes("array_diff( array_keys( $input ), $allowed_keys )")) fail('Workspace sync does not reject undeclared top-level fields.');
if (!controller.includes("isset( $deleted[ $item_id ] )")) fail('Workspace sync does not apply deleted-item tombstones before merge.');
if (!controller.includes("$item['watch'] = $server[ $item_id ]['watch']")) fail('Workspace sync can overwrite an existing server watch.');
if (!controller.includes("'tra_vel_workspace_sync_capacity'")) fail('Workspace sync can evict unrelated server items when the merged cap is exceeded.');
if (!controller.includes("if ( 'live' === $data_mode )") || !controller.includes("$data_mode = 'mixed';")) fail('Browser-asserted live provenance is not downgraded.');
if (!controller.includes('sanitize_item( $stored_item, true )') || !controller.includes("sanitize_preferences( isset( $stored['preferences'] ) ? $stored['preferences'] : array(), false )")) fail('Legacy user meta is returned without item and preference revalidation.');
if (!controller.includes("'tra_vel_workspace_write_failed'")) fail('Workspace mutations do not fail closed after a user-meta write failure.');
if (!controller.includes("'tra_vel_workspace_conflict'") || !controller.includes("array( 'status' => 409 )")) fail('Workspace mutations do not expose an explicit concurrent-write conflict.');
if (!controller.includes("add_user_meta( $user_id, self::META_KEY, $workspace, true )")) fail('The first workspace write is not a unique user-meta insert.');
if (!controller.includes("update_user_meta( $user_id, self::META_KEY, $workspace, $snapshot['stored'] )")) fail('Workspace updates do not compare against the exact non-empty user-meta snapshot.');
if (!controller.includes("delete_user_meta( $user_id, self::META_KEY, $snapshot['stored'] )")) fail('Workspace clear does not compare against the exact user-meta snapshot.');
if (!controller.includes("'tra_vel_workspace_capacity'") || controller.includes("array_slice( $items, 0, self::MAX_ITEMS )")) fail('The single-item endpoint can silently evict an unrelated item at capacity.');
if (!controller.includes("'private, no-store, max-age=0'")) fail('Workspace sync responses are not private and non-cacheable.');

if (!docs.includes('PUT /wp-json/tra-vel/v2/workspace/sync')) fail('Workspace documentation is missing the account sync route.');
if (!docs.includes('deleted_item_ids') || !docs.includes('data_mode')) fail('Workspace documentation is missing tombstone or provenance rules.');
if (!docs.includes('compare-and-swap') || !docs.includes('empty legacy meta')) fail('Workspace documentation is missing concurrency or empty-legacy fail-closed rules.');

if (failures.length) {
  console.error('Tra-Vel traveler workspace contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}
console.log('Tra-Vel traveler workspace contract validation passed (v1 shape, bounded account sync, tombstones, provenance downgrade, private storage).');
