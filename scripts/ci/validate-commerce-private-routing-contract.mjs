import {readFileSync, readdirSync} from 'node:fs';
import {join, resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const commerce = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'commerce');
const schemas = join(root, 'plugin', 'tra-vel-agent-core', 'schemas');
const read = path => readFileSync(path, 'utf8');
const parse = path => JSON.parse(read(path));
const failures = [];
const sameSet = (left, right) => left.length === right.length && [...left].sort().every((value, index) => value === [...right].sort()[index]);
const requireText = (source, needle, message) => {
  if (!source.includes(needle)) failures.push(message);
};

const privateSchemaPath = join(schemas, 'private', 'commerce-private-routing-record.schema.json');
const privateSchema = parse(privateSchemaPath);
const routing = read(join(commerce, 'class-tra-vel-commerce-private-routing-registry.php'));
const catalog = read(join(commerce, 'class-tra-vel-commerce-sandbox-catalog.php'));
const search = read(join(commerce, 'class-tra-vel-commerce-search-engine.php'));
const orderFactory = read(join(commerce, 'class-tra-vel-commerce-order-factory.php'));
const bootstrap = read(join(commerce, 'bootstrap.php'));
const runtime = read(join(root, 'scripts', 'ci', 'validate-commerce-private-routing-runtime.php'));
const themeCi = read(join(root, '.github', 'workflows', 'theme-ci.yml'));
const deployCi = read(join(root, '.github', 'workflows', 'deploy-agent-core.yml'));
const orderSchema = parse(join(schemas, 'commerce-order.schema.json'));
const providerSchema = parse(join(schemas, 'commerce-provider.schema.json'));

if (privateSchema.$schema !== 'http://json-schema.org/draft-07/schema#') failures.push('Private routing schema must use JSON Schema Draft-07.');
if (privateSchema.$id !== 'https://tra-vel.co.il/schemas/private/commerce-private-routing-record.schema.json') failures.push('Private routing schema must have its canonical private ID.');
if (privateSchema.additionalProperties !== false) failures.push('Private routing schema root must be closed.');
if (!sameSet(privateSchema.required || [], Object.keys(privateSchema.properties || {}))) failures.push('Private routing schema must require exactly every root property.');
for (const field of ['owner_scope_digest', 'order_ref', 'order_item_ref', 'provider_id', 'provider_reference_digest', 'offer_digest', 'catalog_binding', 'supplier_binding', 'capability_binding', 'private_route', 'validity', 'private_boundary']) {
  if (!privateSchema.required?.includes(field)) failures.push(`Private routing schema is missing ${field}.`);
}

const visitClosedObjects = (value, pointer = '#') => {
  if (!value || typeof value !== 'object') return;
  if (!Array.isArray(value) && value.type === 'object' && value.additionalProperties !== false) failures.push(`Private routing object ${pointer} is open to unknown fields.`);
  if (Array.isArray(value)) value.forEach((item, index) => visitClosedObjects(item, `${pointer}/${index}`));
  else Object.entries(value).forEach(([key, item]) => visitClosedObjects(item, `${pointer}/${key}`));
};
visitClosedObjects(privateSchema);

const privateRouteProps = privateSchema.properties?.private_route?.properties || {};
for (const field of ['credential_ref', 'endpoint_route_ref', 'endpoint_host', 'operation_route_refs']) {
  if (!privateRouteProps[field]) failures.push(`Private route contract is missing ${field}.`);
}
const publicProjection = ['routing_binding_digest', 'supplier_profile_revision_digest', 'product_revision_digest', 'rate_revision_digest', 'availability_revision_digest', 'terms_revision_digest', 'capability_digest'];
for (const field of publicProjection) requireText(routing, `'${field}'`, `Digest-only routing projection is missing ${field}.`);

for (const needle of [
  'resolve_private_product(',
  'provider_reference_digest',
  'offer_digest',
  'profile_revision_id',
  'profile_content_digest',
  'product_revision_digest',
  'rate_revision_digest',
  'availability_revision_digest',
  'terms_revision_digest',
  'adapter_version',
  'frozen_capabilities',
  'capability_digest',
  'credential_ref',
  'endpoint_route_ref',
  'valid_until',
  'record_is_current(',
  'gate_queued_order_item_operation(',
  "'queued' !== $operation['state']",
  "'operation_capability_not_frozen'",
]) requireText(routing, needle, `Private routing registry is missing ${needle}.`);

for (const call of ['register_rest_route(', 'wp_remote_get(', 'wp_remote_post(', 'wp_remote_request(', 'curl_exec(', '->dispatch(']) {
  if (routing.includes(call)) failures.push(`Private routing preparation must not perform external work: ${call}`);
}

requireText(catalog, "'provider-reference|' . $product['provider_id'] . '|' . $product['private_product_ref']", 'Catalog reverse lookup must recompute the exact provider-reference HMAC input.');
requireText(catalog, "$secret . '|tra-vel-commerce-search-v1'", 'Catalog reverse lookup must derive the same search HMAC secret.');
requireText(search, "'provider-reference|' . $candidate['provider_id'] . '|' . $candidate['private_product_ref']", 'Search must issue the provider-reference HMAC from the same exact input.');
requireText(search, "$secret . '|tra-vel-commerce-search-v1'", 'Search must derive the same HMAC secret.');
requireText(search, "$this->provider_descriptors[ $candidate['provider_id'] ]['adapter_version'] . '-sandbox'", 'Offer adapter evidence must come from the reconciled provider network.');

requireText(orderFactory, "'offer_digest'       => $component['offer_digest']", 'Order items must preserve the selected offer digest.');
const orderItem = orderSchema.properties?.fulfillment?.properties?.items?.items;
if (!orderItem?.required?.includes('offer_digest') || orderItem.properties?.offer_digest?.$ref !== '#/definitions/digest') failures.push('Order fulfillment items must require offer_digest as a digest.');
if (providerSchema.properties?.environment?.const !== 'sandbox' || providerSchema.properties?.allowed_hosts?.maxItems !== 0) failures.push('The public sandbox provider descriptor must structurally forbid endpoint-host projection.');

requireText(bootstrap, "class-tra-vel-commerce-private-routing-registry.php", 'Commerce bootstrap must load the private routing registry.');
for (const needle of ['8 uniquely bound items', 'offer_ambiguous', 'offer_digest_invalid', 'offer_stale', 'binding_revision_stale', 'operation_capability_not_frozen', 'public_schema_violations']) {
  requireText(runtime, needle, `Private routing runtime is missing adversarial proof ${needle}.`);
}

const forbiddenPublicProperties = new Set(['private_product_ref', 'provider_locator_ref', 'credential_ref', 'endpoint_route_ref', 'endpoint_host', 'vault_secret_ref', 'supplier_booking_reference', 'raw_supplier_reference']);
const scanPublicSchema = (schemaName, value, pointer = '#') => {
  if (!value || typeof value !== 'object') return;
  if (!Array.isArray(value) && value.properties) {
    for (const property of Object.keys(value.properties)) {
      if (forbiddenPublicProperties.has(property)) failures.push(`${schemaName} exposes private property ${pointer}/properties/${property}.`);
    }
  }
  if (Array.isArray(value)) value.forEach((item, index) => scanPublicSchema(schemaName, item, `${pointer}/${index}`));
  else Object.entries(value).forEach(([key, item]) => scanPublicSchema(schemaName, item, `${pointer}/${key}`));
};
for (const name of readdirSync(schemas).filter(name => name.startsWith('commerce-') && name.endsWith('.schema.json'))) {
  const raw = read(join(schemas, name));
  const schema = JSON.parse(raw);
  scanPublicSchema(name, schema);
  for (const privatePattern of ['credref_', 'tvr_endpoint_', '"^px_', 'vault_secret_ref']) {
    if (raw.includes(privatePattern)) failures.push(`${name} contains private routing pattern ${privatePattern}.`);
  }
}

for (const workflow of [themeCi, deployCi]) {
  requireText(workflow, 'node scripts/ci/validate-commerce-private-routing-contract.mjs', 'Both CI workflows must run the private routing contract gate.');
  requireText(workflow, 'php scripts/ci/validate-commerce-private-routing-runtime.php', 'Both CI workflows must run the private routing runtime gate.');
}

if (failures.length) {
  console.error('Commerce private routing contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Commerce private routing contract passed (${publicProjection.length} safe projection digests; closed private schema; public schema boundary scanned).`);
