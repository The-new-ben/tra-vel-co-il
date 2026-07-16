import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const schemaPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'traveler-workspace.schema.json');
const schema = JSON.parse(readFileSync(schemaPath, 'utf8'));
const failures = [];
const fail = message => failures.push(message);

for (const key of ['version', 'items', 'preferences', 'meta']) {
  if (!(schema.required || []).includes(key)) fail(`Workspace schema must require ${key}.`);
}
if (schema.properties?.version?.const !== 1) fail('Workspace contract version must remain 1.');
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

if (failures.length) {
  console.error('Tra-Vel traveler workspace contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}
console.log('Tra-Vel traveler workspace contract validation passed (private bounded storage, safe kinds, inactive delivery).');
