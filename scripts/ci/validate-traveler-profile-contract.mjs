import {readFileSync} from 'node:fs';
import {join, resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const vip = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'vip');
const read = path => readFileSync(path, 'utf8');
const failures = [];
const requireText = (source, needle, message) => {
  if (!source.includes(needle)) failures.push(message);
};
const sameSet = (left, right) => left.length === right.length && [...left].sort().every((value, index) => value === [...right].sort()[index]);

const schemaPath = join(root, 'plugin', 'tra-vel-agent-core', 'schemas', 'private', 'traveler-profile-index.schema.json');
const schema = JSON.parse(read(schemaPath));
const taxonomy = read(join(vip, 'class-tra-vel-traveler-profile-taxonomy.php'));
const policy = read(join(vip, 'class-tra-vel-traveler-profile-policy.php'));
const bootstrap = read(join(vip, 'bootstrap.php'));
const runtime = read(join(root, 'scripts', 'ci', 'validate-traveler-profile-runtime.php'));
const workflows = [
  read(join(root, '.github', 'workflows', 'theme-ci.yml')),
  read(join(root, '.github', 'workflows', 'deploy-agent-core.yml')),
];

if (schema.$schema !== 'http://json-schema.org/draft-07/schema#') failures.push('Traveler-profile schema must use JSON Schema Draft-07.');
if (schema.$id !== 'https://tra-vel.co.il/schemas/private/traveler-profile-index.schema.json') failures.push('Traveler-profile schema must have its canonical private ID.');
if (schema.additionalProperties !== false) failures.push('Traveler-profile schema root must be closed.');
if (schema.properties?.contract_version?.const !== '1.0.0') failures.push('Traveler-profile contract version must be pinned to 1.0.0.');
if (!sameSet(schema.required || [], Object.keys(schema.properties || {}))) failures.push('Traveler-profile schema must require every root property exactly.');
if (schema.properties?.fields?.maxItems !== 96) failures.push('Traveler-profile field index must remain bounded at 96 entries.');

const resolveRef = ref => {
  if (typeof ref !== 'string' || !ref.startsWith('#/')) return null;
  let value = schema;
  for (const segment of ref.slice(2).split('/').map(part => part.replaceAll('~1', '/').replaceAll('~0', '~'))) value = value?.[segment];
  return value;
};
const forbiddenProperties = /^(?:raw_?value|first_?name|last_?name|full_?name|email|phone|mobile|postal_?address|passport|document_?number|date_?of_?birth|diagnosis|medical_?note|member_?number|loyalty_?password|api_?key|secret|password|bearer|access_?token|refresh_?token|private_?key|card_?number|pan|cvv|cvc)$/i;
const visit = (value, pointer = '#') => {
  if (!value || typeof value !== 'object') return;
  if (!Array.isArray(value) && value.type === 'object') {
    if (value.additionalProperties !== false) failures.push(`Traveler-profile object ${pointer} is open to unknown fields.`);
    if (!sameSet(value.required || [], Object.keys(value.properties || {}))) failures.push(`Traveler-profile object ${pointer} does not require its exact property set.`);
  }
  if (!Array.isArray(value) && value.properties) {
    for (const property of Object.keys(value.properties)) {
      if (forbiddenProperties.test(property)) failures.push(`Traveler-profile schema accepts raw sensitive property ${pointer}/properties/${property}.`);
    }
  }
  if (!Array.isArray(value) && typeof value.$ref === 'string' && !resolveRef(value.$ref)) failures.push(`Traveler-profile schema has unresolved or external reference ${value.$ref} at ${pointer}.`);
  if (Array.isArray(value)) value.forEach((child, index) => visit(child, `${pointer}/${index}`));
  else Object.entries(value).forEach(([key, child]) => visit(child, `${pointer}/${key}`));
};
visit(schema);

const boundary = schema.definitions?.profileBoundary;
const boundaryKeys = ['server_only', 'public_serialization_allowed', 'vault_pointers_only', 'raw_identity_data_stored', 'raw_contact_data_stored', 'raw_document_data_stored', 'raw_medical_data_stored', 'raw_loyalty_credentials_stored'];
if (!sameSet(boundary?.required || [], boundaryKeys) || boundary?.properties?.server_only?.const !== true || boundary?.properties?.public_serialization_allowed?.const !== false || boundary?.properties?.vault_pointers_only?.const !== true || boundaryKeys.slice(3).some(key => boundary?.properties?.[key]?.const !== false)) {
  failures.push('Traveler-profile root must carry the exact private vault-pointer-only boundary.');
}
const fieldBoundary = schema.definitions?.fieldBoundary;
if (!sameSet(fieldBoundary?.required || [], ['server_only', 'raw_value_stored', 'vault_pointer_only']) || fieldBoundary?.properties?.server_only?.const !== true || fieldBoundary?.properties?.raw_value_stored?.const !== false || fieldBoundary?.properties?.vault_pointer_only?.const !== true) {
  failures.push('Every traveler-profile field must carry the exact no-raw-value boundary.');
}

const fieldCodes = schema.definitions?.fieldCode?.enum || [];
if (fieldCodes.length < 50 || new Set(fieldCodes).size !== fieldCodes.length) failures.push('Traveler-profile field vocabulary must contain at least 50 unique operational fields.');
for (const code of fieldCodes) requireText(taxonomy, `'${code}'`, `Traveler-profile taxonomy is missing schema field ${code}.`);
for (const marker of [
  "const CONTRACT_VERSION = '1.0.0'",
  'const FIELD_CLASSES',
  'const RETENTION_BY_CLASS',
  'const USE_CASE_REQUIREMENTS',
  "'flight_reservation'",
  "'accommodation_reservation'",
  "'insurance_quote'",
  "'emergency_ready'",
  "'benefit_connection'",
  'minor_present',
  'dependent_adult_present',
  'accessibility_required',
  'loyalty_requested',
]) requireText(taxonomy, marker, `Traveler-profile taxonomy is missing ${marker}.`);

for (const marker of [
  'contains_sensitive_material(',
  'profile_digest(',
  'authorization_effect',
  'readiness(',
  'assert_successor(',
  'successor_field_rewritten',
  'successor_field_removed',
  'successor_lineage_invalid',
  'readiness_subject_flags_invalid',
  'field_assurance_source_invalid',
  'field_freshness_invalid',
  'public_serialization_allowed',
  'vault_pointers_only',
]) requireText(policy, marker, `Traveler-profile policy is missing ${marker}.`);
for (const call of ['register_rest_route(', 'wp_remote_get(', 'wp_remote_post(', 'wp_remote_request(', 'curl_exec(', '->dispatch(', '->reserve(', '->charge(']) {
  if (policy.includes(call) || taxonomy.includes(call)) failures.push(`Traveler-profile evidence code must not perform external work: ${call}`);
}

for (const file of ['class-tra-vel-traveler-profile-taxonomy.php', 'class-tra-vel-traveler-profile-policy.php']) {
  requireText(bootstrap, file, `VIP bootstrap does not load ${file}.`);
}
for (const proof of ['vault-pointer-only', 'authorization_effect', 'minor', 'dependent', 'accessibility', 'loyalty', 'expired after', 'sensitive_material_rejected', 'rewritten in place', 'silently remove', 'steal another field code lineage']) {
  requireText(runtime.toLowerCase(), proof.toLowerCase(), `Traveler-profile runtime is missing adversarial proof ${proof}.`);
}
for (const workflow of workflows) {
  requireText(workflow, 'php scripts/ci/validate-traveler-profile-runtime.php', 'Both CI workflows must run the traveler-profile runtime gate.');
  requireText(workflow, 'node scripts/ci/validate-traveler-profile-contract.mjs', 'Both CI workflows must run the traveler-profile contract gate.');
}

if (failures.length) {
  console.error('Traveler profile contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Traveler profile contract passed (1 closed private schema; ${fieldCodes.length} field codes; ${boundaryKeys.length} root privacy boundaries; immutable evidence lineage).`);
