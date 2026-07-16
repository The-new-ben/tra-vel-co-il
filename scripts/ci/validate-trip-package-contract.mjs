import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const dataPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'trip-package-demo.json');
const schemaPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'trip-package.schema.json');
const data = JSON.parse(readFileSync(dataPath, 'utf8'));
const schema = JSON.parse(readFileSync(schemaPath, 'utf8'));
const failures = [];
const fail = message => failures.push(message);

for (const key of schema.required || []) if (!(key in data)) fail(`Missing schema-required property: ${key}`);
if (!/^\d+\.\d+\.\d+$/.test(data.contract_version || '')) fail('contract_version must use semantic versioning.');
if (data.data_mode !== 'demo') fail('The bundled package fallback must remain demo data.');
if (data.currency !== 'USD') fail('Package components must use one explicit USD comparison currency.');
if (data.provider_status?.connected !== false || data.provider_status?.bookable !== false) fail('Demo packages must remain disconnected and non-bookable.');
if (!['TLV', 'BUD'].every(code => /^[A-Z]{3}$/.test(code))) fail('Demo route must use valid IATA-style codes.');
if (data.origin?.code !== 'TLV' || data.destination?.code !== 'BUD') fail('Demo package route must remain coherent from TLV to Budapest.');
for (const point of [data.origin, data.destination]) {
  if (Math.abs(point?.latitude) > 90 || Math.abs(point?.longitude) > 180) fail(`${point?.code || 'route point'} has invalid coordinates.`);
  if (point?.position?.x < 0 || point?.position?.x > 100 || point?.position?.y < 0 || point?.position?.y > 100) fail(`${point?.code || 'route point'} has invalid map position.`);
}

for (const tier of ['none', 'essential', 'assisted', 'extended']) {
  const plan = data.insurance_catalog?.[tier];
  if (!plan) fail(`Insurance catalog is missing ${tier}.`);
  if (plan?.purchasable !== false) fail(`${tier}: demo insurance must not become purchasable.`);
}

const ids = new Set();
for (const tripPackage of data.packages || []) {
  if (!tripPackage.id || ids.has(tripPackage.id)) fail(`Missing or duplicate package id: ${tripPackage.id || '(empty)'}`);
  ids.add(tripPackage.id);
  if (!Number.isInteger(tripPackage.base_score) || tripPackage.base_score < 0 || tripPackage.base_score > 100) fail(`${tripPackage.id}: base score must be 0-100.`);
  if (!tripPackage.flight?.direct || tripPackage.flight?.stops !== 0) fail(`${tripPackage.id}: the initial Budapest fixture must remain a coherent direct route.`);
  if (tripPackage.flight?.adult_price <= 0 || tripPackage.flight?.child_price <= 0) fail(`${tripPackage.id}: flight pricing is invalid.`);
  if (!tripPackage.stay?.property_id || tripPackage.stay?.nightly <= 0 || tripPackage.stay?.sleeps < 1) fail(`${tripPackage.id}: stay contract is incomplete.`);
  if (tripPackage.stay?.position?.x < 0 || tripPackage.stay?.position?.x > 100 || tripPackage.stay?.position?.y < 0 || tripPackage.stay?.position?.y > 100) fail(`${tripPackage.id}: stay map position is invalid.`);
  for (const trait of ['comfort', 'flexibility', 'location', 'simplicity']) {
    if (!Number.isInteger(tripPackage.traits?.[trait]) || tripPackage.traits[trait] < 0 || tripPackage.traits[trait] > 100) fail(`${tripPackage.id}: ${trait} must be 0-100.`);
  }
  if (!Array.isArray(tripPackage.pros) || tripPackage.pros.length < 2 || !Array.isArray(tripPackage.cons) || tripPackage.cons.length < 1) fail(`${tripPackage.id}: decision trade-offs are incomplete.`);
  if (tripPackage.booking?.bookable !== false || tripPackage.booking?.checkout_url !== null) fail(`${tripPackage.id}: demo package must not expose checkout.`);
}

for (const requiredId of ['budapest-smart-demo', 'budapest-value-demo', 'budapest-flex-demo', 'budapest-family-demo']) {
  if (!ids.has(requiredId)) fail(`Required comparison archetype is missing: ${requiredId}`);
}

if (failures.length) {
  console.error('Tra-Vel trip package contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}
console.log(`Tra-Vel trip package contract validation passed (${data.packages.length} coherent package archetypes).`);
