import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const dataPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'insurance-quote-demo.json');
const schemaPath = join(repoRoot, 'theme', 'tra-vel-v2', 'assets', 'data', 'insurance-quote.schema.json');
const data = JSON.parse(readFileSync(dataPath, 'utf8'));
const schema = JSON.parse(readFileSync(schemaPath, 'utf8'));
const failures = [];
const fail = message => failures.push(message);

for (const key of schema.required || []) if (!(key in data)) fail(`Missing schema-required property: ${key}`);
if (!/^\d+\.\d+\.\d+$/.test(data.contract_version || '')) fail('contract_version must use semantic versioning.');
if (data.data_mode !== 'demo') fail('The bundled insurance fallback must remain demo data.');
if (data.currency !== 'USD') fail('The insurance comparison contract must use USD.');
if (data.provider_status?.connected !== false || data.provider_status?.purchasable !== false) fail('Demo insurance data must remain disconnected and non-purchasable.');
if (data.destination?.id !== 'europe' || !data.destination?.policy_note) fail('Destination context or policy-wording warning is missing.');

const knownAddons = new Set(['baggage', 'cancellation', 'adventure_sports', 'winter_sports', 'electronics']);
const riskIds = new Set();
for (const context of data.risk_contexts || []) {
  if (!context.id || riskIds.has(context.id)) fail(`Missing or duplicate risk context id: ${context.id || '(empty)'}`);
  riskIds.add(context.id);
  if (!context.note || !context.trip_type) fail(`${context.id}: context explanation is incomplete.`);
  if (typeof context.position?.x !== 'number' || typeof context.position?.y !== 'number' || context.position.x < 0 || context.position.x > 100 || context.position.y < 0 || context.position.y > 100) fail(`${context.id}: map position is invalid.`);
  for (const addon of context.recommended_addons || []) if (!knownAddons.has(addon)) fail(`${context.id}: unknown add-on ${addon}.`);
}

const planIds = new Set();
const coverageFields = ['medical_limit', 'medical_deductible', 'emergency_dental_limit', 'search_rescue_limit', 'third_party_limit', 'baggage_limit', 'electronics_limit', 'cancellation_limit', 'trip_shortening_limit', 'delay_limit'];
for (const plan of data.plans || []) {
  if (!plan.id || planIds.has(plan.id)) fail(`Missing or duplicate plan id: ${plan.id || '(empty)'}`);
  planIds.add(plan.id);
  if (!Number.isInteger(plan.base_score) || plan.base_score < 0 || plan.base_score > 100) fail(`${plan.id}: base score must be 0-100.`);
  if (!Number.isInteger(plan.service_score) || plan.service_score < 0 || plan.service_score > 100) fail(`${plan.id}: service score must be 0-100.`);
  if (typeof plan.daily_base !== 'number' || plan.daily_base <= 0) fail(`${plan.id}: daily demo rate is invalid.`);
  for (const field of coverageFields) if (!Number.isFinite(plan.coverage?.[field]) || plan.coverage[field] < 0) fail(`${plan.id}: invalid coverage field ${field}.`);
  for (const addon of knownAddons) if (!Number.isFinite(plan.addon_rates?.[addon]) || plan.addon_rates[addon] < 0) fail(`${plan.id}: invalid add-on rate ${addon}.`);
  if (plan.underwriting?.medical_condition !== 'assessment_required' || plan.underwriting?.pregnancy !== 'assessment_required') fail(`${plan.id}: medical underwriting safeguards are missing.`);
  if (!Array.isArray(plan.exclusions) || plan.exclusions.length < 3) fail(`${plan.id}: exclusion checklist is incomplete.`);
  if (!Array.isArray(plan.pros) || plan.pros.length < 2 || !Array.isArray(plan.cons) || plan.cons.length < 2) fail(`${plan.id}: decision trade-offs are incomplete.`);
  if (plan.purchase?.purchasable !== false || plan.purchase?.checkout_url !== null || plan.purchase?.policy_url !== null) fail(`${plan.id}: demo plan must not expose purchase or policy links.`);
}

const smartest = [...(data.plans || [])].sort((a, b) => b.base_score - a.base_score)[0];
const cheapest = [...(data.plans || [])].sort((a, b) => a.daily_base - b.daily_base)[0];
const medical = [...(data.plans || [])].sort((a, b) => b.coverage.medical_limit - a.coverage.medical_limit)[0];
if (smartest?.id !== 'demo-assisted') fail('Assisted must remain the highest default smart-score fixture.');
if (cheapest?.id !== 'demo-essential') fail('Essential must remain the lowest daily demo fixture.');
if (medical?.id !== 'demo-extended') fail('Extended must remain the highest medical-limit fixture.');

if (failures.length) {
  console.error('Tra-Vel insurance contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}
console.log(`Tra-Vel insurance contract validation passed (${data.plans.length} fictional plans, ${data.risk_contexts.length} risk contexts).`);
