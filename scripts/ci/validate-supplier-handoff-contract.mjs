import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const schema = JSON.parse(readFileSync(join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'supplier-handoff.schema.json'), 'utf8'));
const failures = [];
const fail = message => failures.push(message);

for (const key of ['provider', 'vertical', 'offer_id', 'handoff_url', 'rel', 'disclosure', 'price_recheck', 'booking_on_partner', 'expires_at']) {
  if (!(schema.required || []).includes(key)) fail(`Handoff contract must require ${key}.`);
}
if (schema.properties?.handoff_url?.pattern !== '^https://') fail('Handoff URL contract must require HTTPS.');
if (schema.properties?.rel?.const !== 'sponsored noopener noreferrer') fail('Handoff links must be sponsored and opener-isolated.');
if (schema.properties?.price_recheck?.const !== true) fail('Supplier handoff must force a price recheck.');
if (schema.properties?.booking_on_partner?.const !== true) fail('Handoff contract must disclose that booking happens with the partner.');
if (schema.additionalProperties !== false) fail('Handoff responses must reject undeclared fields.');

if (failures.length) {
  console.error('Tra-Vel supplier handoff contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}
console.log('Tra-Vel supplier handoff contract validation passed (HTTPS allowlist boundary, sponsored rel, price recheck).');
