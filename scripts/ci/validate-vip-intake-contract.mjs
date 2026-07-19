import {readFileSync} from 'node:fs';
import {join, resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const schemaPath = join(root, 'plugin', 'tra-vel-agent-core', 'schemas', 'vip-no-login-intake.schema.json');
const vipDir = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'vip');
const taxonomyPath = join(vipDir, 'class-tra-vel-vip-intake-taxonomy.php');
const policyPath = join(vipDir, 'class-tra-vel-vip-intake-policy.php');
const projectionPath = join(vipDir, 'class-tra-vel-vip-intake-state-projection.php');
const failures = [];

let schema = {};
let taxonomy = '';
let policy = '';
let projection = '';
try {
  schema = JSON.parse(readFileSync(schemaPath, 'utf8'));
  taxonomy = readFileSync(taxonomyPath, 'utf8');
  policy = readFileSync(policyPath, 'utf8');
  projection = readFileSync(projectionPath, 'utf8');
} catch (error) {
  failures.push(`VIP intake foundation is missing or invalid: ${error.message}`);
}

const visit = (value, pointer = '#') => {
  if (!value || typeof value !== 'object') return;
  if (!Array.isArray(value) && value.type === 'object' && value.additionalProperties !== false) {
    failures.push(`Object ${pointer} is open to unknown fields.`);
  }
  if (!Array.isArray(value) && typeof value.$ref === 'string') {
    if (!value.$ref.startsWith('#/')) {
      failures.push(`Non-local schema reference ${value.$ref} at ${pointer}.`);
    } else {
      const segments = value.$ref.slice(2).split('/').map(segment => segment.replaceAll('~1', '/').replaceAll('~0', '~'));
      let target = schema;
      for (const segment of segments) target = target && target[segment];
      if (!target) failures.push(`Unresolved schema reference ${value.$ref} at ${pointer}.`);
    }
  }
  if (Array.isArray(value)) value.forEach((child, index) => visit(child, `${pointer}/${index}`));
  else Object.entries(value).forEach(([key, child]) => visit(child, `${pointer}/${key}`));
};

if (schema.$schema !== 'http://json-schema.org/draft-07/schema#') failures.push('VIP intake must use JSON Schema Draft-07.');
if (schema.$id !== 'https://tra-vel.co.il/schemas/vip-no-login-intake.schema.json') failures.push('VIP intake schema has the wrong canonical ID.');
if (schema.additionalProperties !== false) failures.push('VIP intake schema root must be closed.');
if (schema.properties?.contract_version?.const !== '1.0.0') failures.push('VIP intake contract version must be pinned to 1.0.0.');
visit(schema);

const access = schema.definitions?.access?.properties || {};
if (access.authorization_effect?.const !== 'none') failures.push('VIP intake must explicitly have no authorization effect.');
if (access.executable_scopes?.maxItems !== 0) failures.push('VIP intake executable scopes must be structurally empty.');
const highImpact = ['service_reserve', 'service_change', 'service_cancel', 'payment_authorize', 'refund_destination_change', 'identity_change', 'guardian_authority_change', 'sensitive_evidence_disclose', 'recovery_channel_change', 'delegate_manage'];
const reportScopes = schema.definitions?.reportScope?.enum || [];
if (reportScopes.some(scope => highImpact.includes(scope))) failures.push('A high-impact scope leaked into no-login report capability.');
for (const scope of highImpact) {
  if (!(schema.definitions?.requestScope?.enum || []).includes(scope)) failures.push(`The intake cannot safely record high-impact request ${scope}.`);
}

const boundary = schema.definitions?.dataBoundary;
const boundaryKeys = ['raw_message_exposed', 'raw_contact_data_exposed', 'raw_attachment_exposed', 'raw_identity_data_exposed', 'raw_payment_data_exposed', 'raw_medical_data_exposed', 'raw_provider_payload_exposed', 'bearer_secret_exposed'];
if (boundary?.additionalProperties !== false || !boundaryKeys.every(key => boundary.required?.includes(key) && boundary.properties?.[key]?.const === false) || Object.keys(boundary?.properties || {}).length !== boundaryKeys.length) {
  failures.push('VIP intake must carry the exact eight-part no-raw-data boundary.');
}
if (!/Raw messages, PII, attachment bytes/i.test(schema.description || '') || !/no field.+authorizes/i.test(schema.description || '')) {
  failures.push('VIP intake schema must state both its privacy and non-authorization boundary.');
}

for (const marker of ['function intake', 'function index_accepted', 'replay_conflict', 'channel_event_conflict', 'scanner_exchange_required', 'danger_understated', 'attachment_quarantine_required', 'authorization_effect']) {
  if (!policy.includes(marker)) failures.push(`VIP intake policy is missing ${marker}.`);
}
for (const marker of ['function customer_receipt', 'function project', 'immediate_safety_help', 'request_step_up', 'retry_receipt_delivery', 'authorization_effect', "'executable_scopes'          => array()"] ) {
  if (!projection.includes(marker)) failures.push(`VIP intake projection is missing ${marker}.`);
}
for (const value of [...highImpact, ...(schema.definitions?.source?.properties?.channel?.enum || []), ...(schema.definitions?.classification?.properties?.incident_family?.enum || [])]) {
  if (!taxonomy.includes(`'${value}'`)) failures.push(`VIP intake taxonomy is missing schema value ${value}.`);
}
const forbiddenNames = /^(?:raw_message|message_body|body|text|free_text|sender|sender_address|email|email_address|phone|phone_number|contact|contact_details|file_content|attachment_body|bearer_token|token|secret|password|cvv|cvc|card_number|card_pan|pan|passport|passport_number|identity_number|diagnosis|medical_history|medical_narrative|raw_provider_payload|raw_payment_data|payment_token|activation_code|iccid)$/i;
const scanProperties = (value, pointer = '#') => {
  if (!value || typeof value !== 'object') return;
  if (!Array.isArray(value) && value.properties) {
    for (const name of Object.keys(value.properties)) if (forbiddenNames.test(name)) failures.push(`Forbidden raw property ${name} at ${pointer}.`);
  }
  if (Array.isArray(value)) value.forEach((child, index) => scanProperties(child, `${pointer}/${index}`));
  else Object.entries(value).forEach(([key, child]) => scanProperties(child, `${pointer}/${key}`));
};
scanProperties(schema);

if (failures.length) {
  console.error('VIP no-login intake contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`VIP no-login intake contract passed (1 closed schema; ${highImpact.length} recorded-but-never-executable high-impact scopes; ${boundaryKeys.length} privacy boundaries).`);
