import {readFileSync, readdirSync} from 'node:fs';
import {basename, join, resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const schemaDir = join(root, 'plugin', 'tra-vel-agent-core', 'schemas');
const fixturePath = join(root, 'plugin', 'tra-vel-agent-core', 'assets', 'fixtures', 'commerce-sandbox', 'provider-network.json');
const failures = [];

const expectedSchemas = [
  'commerce-provider.schema.json',
  'commerce-search-request.schema.json',
  'commerce-search-session.schema.json',
  'commerce-search-response.schema.json',
  'commerce-offer.schema.json',
  'commerce-package.schema.json',
  'commerce-order.schema.json',
  'commerce-event.schema.json',
  'commerce-operation.schema.json',
  'commerce-settlement.schema.json',
];
const verticals = ['flight', 'accommodation', 'package', 'transfer', 'activity', 'dining', 'insurance', 'connectivity', 'equipment'];
const capabilities = [
  'search', 'revalidate', 'reserve', 'confirm', 'fulfill', 'change', 'cancel', 'refund',
  'payment_authorize', 'payment_capture', 'payment_void', 'payment_refund',
  'webhook', 'reconcile', 'report_conversion', 'settlement_reconcile',
];
const forbiddenProperty = /^(?:card_number|card_pan|cvv|cvc|bank_account|passport|diagnosis|medical_declaration|raw_supplier_reference|supplier_booking_reference|raw_payment_data|payment_token)$/i;
const sameSet = (left, right) => left.length === right.length && [...left].sort().every((value, index) => value === [...right].sort()[index]);
const schemas = new Map();
const typedReferencePatterns = {
  'commerce-search-request.schema.json': {
    requestRef: '^tv_request_[A-Za-z0-9_-]{16,96}$',
  },
  'commerce-search-session.schema.json': {
    sessionRef: '^tv_session_[A-Za-z0-9_-]{16,96}$',
    requestRef: '^tv_request_[A-Za-z0-9_-]{16,96}$',
    providerRunRef: '^tv_run_[A-Za-z0-9_-]{16,96}$',
    offerRef: '^tv_offer_[A-Za-z0-9_-]{16,96}$',
  },
  'commerce-search-response.schema.json': {
    requestRef: '^tv_request_[A-Za-z0-9_-]{16,96}$',
    sessionRef: '^tv_session_[A-Za-z0-9_-]{16,96}$',
    providerRunRef: '^tv_run_[A-Za-z0-9_-]{16,96}$',
    offerRef: '^tv_offer_[A-Za-z0-9_-]{16,96}$',
    productRef: '^tv_product_[A-Za-z0-9_-]{16,96}$',
    placeRef: '^tv_place_[A-Za-z0-9_-]{16,96}$',
    segmentRef: '^tv_segment_[A-Za-z0-9_-]{16,96}$',
  },
  'commerce-offer.schema.json': {
    offerRef: '^tv_offer_[A-Za-z0-9_-]{16,96}$',
    sessionRef: '^tv_session_[A-Za-z0-9_-]{16,96}$',
    productRef: '^tv_product_[A-Za-z0-9_-]{16,96}$',
    placeRef: '^tv_place_[A-Za-z0-9_-]{16,96}$',
    segmentRef: '^tv_segment_[A-Za-z0-9_-]{16,96}$',
  },
  'commerce-package.schema.json': {
    packageRef: '^tv_package_[A-Za-z0-9_-]{16,96}$',
    sessionRef: '^tv_session_[A-Za-z0-9_-]{16,96}$',
    componentRef: '^tv_component_[A-Za-z0-9_-]{16,96}$',
    offerRef: '^tv_offer_[A-Za-z0-9_-]{16,96}$',
    placeRef: '^tv_place_[A-Za-z0-9_-]{16,96}$',
  },
  'commerce-order.schema.json': {
    orderRef: '^tv_order_[A-Za-z0-9_-]{16,96}$',
    orderItemRef: '^tv_order_item_[A-Za-z0-9_-]{16,96}$',
    componentRef: '^tv_component_[A-Za-z0-9_-]{16,96}$',
    sessionRef: '^tv_session_[A-Za-z0-9_-]{16,96}$',
    offerRef: '^tv_offer_[A-Za-z0-9_-]{16,96}$',
    packageRef: '^tv_package_[A-Za-z0-9_-]{16,96}$',
    settlementRef: '^tv_settlement_[A-Za-z0-9_-]{16,96}$',
    nullablePaymentRef: '^tv_payment_[A-Za-z0-9_-]{16,96}$',
    nullableOperationRef: '^tv_operation_[A-Za-z0-9_-]{16,96}$',
    nullableApprovalRef: '^tv_approval_[A-Za-z0-9_-]{16,96}$',
    pricingSourceRef: '^tv_(?:offer|package|component|order_item|payment|operation)_[A-Za-z0-9_-]{16,96}$',
  },
  'commerce-operation.schema.json': {
    operationRef: '^tv_operation_[A-Za-z0-9_-]{16,96}$',
    orderRef: '^tv_order_[A-Za-z0-9_-]{16,96}$',
    operationTargetRef: '^tv_(?:offer|package|order|order_item|payment|settlement)_[A-Za-z0-9_-]{16,96}$',
    nullableApprovalRef: '^tv_approval_[A-Za-z0-9_-]{16,96}$',
  },
  'commerce-event.schema.json': {
    eventRef: '^tv_event_[A-Za-z0-9_-]{16,96}$',
    aggregateRef: '^tv_(?:session|package|order|operation|settlement)_[A-Za-z0-9_-]{16,96}$',
    nullableOfferRef: '^tv_offer_[A-Za-z0-9_-]{16,96}$',
    nullableOrderRef: '^tv_order_[A-Za-z0-9_-]{16,96}$',
    nullableOperationRef: '^tv_operation_[A-Za-z0-9_-]{16,96}$',
    nullableSettlementRef: '^tv_settlement_[A-Za-z0-9_-]{16,96}$',
  },
  'commerce-settlement.schema.json': {
    settlementRef: '^tv_settlement_[A-Za-z0-9_-]{16,96}$',
    orderRef: '^tv_order_[A-Za-z0-9_-]{16,96}$',
    orderItemRef: '^tv_order_item_[A-Za-z0-9_-]{16,96}$',
    attributionRef: '^tv_attribution_[A-Za-z0-9_-]{16,96}$',
    nullableOperationRef: '^tv_operation_[A-Za-z0-9_-]{16,96}$',
  },
};

for (const name of expectedSchemas) {
  try {
    const schema = JSON.parse(readFileSync(join(schemaDir, name), 'utf8'));
    schemas.set(name, schema);
  } catch (error) {
    failures.push(`${name} is missing or invalid JSON: ${error.message}`);
  }
}

const ids = new Set();
const visit = (schemaName, rootSchema, value, pointer = '#') => {
  if (!value || typeof value !== 'object') return;
  if (!Array.isArray(value) && value.type === 'object' && value.additionalProperties !== false) {
    failures.push(`${schemaName} leaves object ${pointer} open to unknown fields.`);
  }
  if (!Array.isArray(value) && value.properties) {
    for (const key of Object.keys(value.properties)) {
      if (forbiddenProperty.test(key)) failures.push(`${schemaName} exposes forbidden raw property ${pointer}/properties/${key}.`);
    }
  }
  if (!Array.isArray(value) && value.pattern === '^tv_[a-z]+_[A-Za-z0-9_-]{16,96}$') {
    failures.push(`${schemaName} retains the generic opaque-reference pattern at ${pointer}.`);
  }
  if (!Array.isArray(value) && typeof value.$ref === 'string') {
    if (!value.$ref.startsWith('#/')) {
      failures.push(`${schemaName} uses a non-local schema reference at ${pointer}.`);
    } else {
      const segments = value.$ref.slice(2).split('/').map(segment => segment.replaceAll('~1', '/').replaceAll('~0', '~'));
      let target = rootSchema;
      for (const segment of segments) target = target && target[segment];
      if (!target) failures.push(`${schemaName} has an unresolved reference ${value.$ref}.`);
    }
  }
  if (Array.isArray(value)) value.forEach((item, index) => visit(schemaName, rootSchema, item, `${pointer}/${index}`));
  else Object.entries(value).forEach(([key, item]) => visit(schemaName, rootSchema, item, `${pointer}/${key}`));
};

for (const [name, schema] of schemas) {
  if (schema.$schema !== 'http://json-schema.org/draft-07/schema#') failures.push(`${name} must declare JSON Schema Draft-07.`);
  if (typeof schema.$id !== 'string' || !schema.$id.startsWith('https://tra-vel.co.il/schemas/')) failures.push(`${name} has an invalid canonical schema ID.`);
  else if (ids.has(schema.$id)) failures.push(`${name} duplicates schema ID ${schema.$id}.`);
  else ids.add(schema.$id);
  if (schema.additionalProperties !== false) failures.push(`${name} must be closed at its root.`);
  if (schema.properties?.contract_version?.const !== '1.0.0') failures.push(`${name} must pin contract version 1.0.0.`);
  if (schema.properties?.environment?.const !== 'sandbox') failures.push(`${name} must preserve the sandbox environment boundary.`);
  if (name === 'commerce-provider.schema.json') {
    if (schema.properties?.commercial_truth?.additionalProperties !== false) failures.push(`${name} must define a closed provider truth boundary.`);
  } else if (!schema.definitions?.sandboxTruth || !schema.definitions?.dataBoundary) {
    failures.push(`${name} must define closed sandbox truth and data boundaries.`);
  }
  visit(name, schema, schema);

  for (const [definitionName, expectedPattern] of Object.entries(typedReferencePatterns[name] || {})) {
    if (schema.definitions?.[definitionName]?.pattern !== expectedPattern) {
      failures.push(`${name} is missing typed reference definition ${definitionName}.`);
    }
  }
}

const searchSessionSchema = schemas.get('commerce-search-session.schema.json');
if (searchSessionSchema) {
  for (const digestField of ['provider_network_digest', 'catalog_digest']) {
    if (!searchSessionSchema.required?.includes(digestField) || searchSessionSchema.properties?.[digestField]?.$ref !== '#/definitions/digest') {
      failures.push(`commerce-search-session.schema.json must require ${digestField} as a digest.`);
    }
  }
  const rankedOffer = searchSessionSchema.properties?.ranked_offers?.items;
  const rankedKeys = ['offer_ref', 'offer_version', 'vertical', 'currency', 'price_scope', 'rank', 'score_bps', 'reasons'];
  if (!rankedOffer || !sameSet(rankedOffer.required || [], rankedKeys) || !sameSet(Object.keys(rankedOffer.properties || {}), rankedKeys) || rankedOffer.properties?.currency?.pattern !== '^[A-Z]{3}$' || rankedOffer.properties?.price_scope?.$ref !== '#/definitions/priceScope') {
    failures.push('Commerce search session ranked offers must bind rank to currency and canonical price scope.');
  }
}

const searchResponseSchema = schemas.get('commerce-search-response.schema.json');
if (searchResponseSchema) {
  const responseKeys = ['contract_version', 'environment', 'provider_network_digest', 'catalog_digest', 'session', 'groups', 'sandbox_truth', 'data_boundary'];
  const actualKeys = Object.keys(searchResponseSchema.properties || {});
  if (!sameSet(searchResponseSchema.required || [], responseKeys) || !sameSet(actualKeys, responseKeys)) {
    failures.push('Commerce search response must expose only the reviewed closed runtime envelope.');
  }
  const group = searchResponseSchema.definitions?.offerGroup;
  if (!group || !sameSet(group.required || [], ['vertical', 'currency', 'price_scope', 'offers']) || !sameSet(Object.keys(group.properties || {}), ['vertical', 'currency', 'price_scope', 'offers'])) {
    failures.push('Commerce search response must define the exact closed offer-group structure.');
  }
  const session = searchResponseSchema.definitions?.searchSession;
  for (const digestField of ['provider_network_digest', 'catalog_digest']) {
    if (!session?.required?.includes(digestField) || session.properties?.[digestField]?.$ref !== '#/definitions/digest') {
      failures.push(`Commerce search response session must require ${digestField} as a digest.`);
    }
  }
  const rankedOffer = searchResponseSchema.definitions?.rankedOffer;
  const rankedKeys = ['offer_ref', 'offer_version', 'vertical', 'currency', 'price_scope', 'rank', 'score_bps', 'reasons'];
  if (!rankedOffer || !sameSet(rankedOffer.required || [], rankedKeys) || !sameSet(Object.keys(rankedOffer.properties || {}), rankedKeys) || rankedOffer.properties?.currency?.pattern !== '^[A-Z]{3}$' || rankedOffer.properties?.price_scope?.$ref !== '#/definitions/priceScope') {
    failures.push('Commerce search response ranked offers must bind rank to currency and canonical price scope.');
  }
}

const orderSchema = schemas.get('commerce-order.schema.json');
if (orderSchema) {
  for (const digestField of ['idempotency_key_digest', 'order_digest']) {
    if (!orderSchema.required?.includes(digestField) || orderSchema.properties?.[digestField]?.$ref !== '#/definitions/digest') {
      failures.push(`commerce-order.schema.json must require ${digestField} as a digest.`);
    }
  }
}

const providerSchema = schemas.get('commerce-provider.schema.json');
if (providerSchema) {
  const providerVerticals = providerSchema.definitions?.vertical?.enum || [];
  const providerCapabilities = providerSchema.properties?.capabilities?.items?.enum || [];
  if (!sameSet(providerVerticals, verticals)) failures.push('Provider schema canonical verticals do not match Commerce Core.');
  if (!sameSet(providerCapabilities, capabilities)) failures.push('Provider schema capabilities do not match Commerce Core.');

  try {
    const fixture = JSON.parse(readFileSync(fixturePath, 'utf8'));
    const required = providerSchema.required || [];
    const allowed = new Set(Object.keys(providerSchema.properties || {}));
    const seen = new Set();
    if (fixture.contract_version !== '1.0.0' || fixture.environment !== 'sandbox' || fixture.network_id !== 'tra_vel_commerce_sandbox' || !Array.isArray(fixture.providers)) {
      failures.push('Provider network fixture has an invalid closed envelope.');
    } else {
      for (const provider of fixture.providers) {
        const keys = Object.keys(provider);
        if (required.some(key => !keys.includes(key)) || keys.some(key => !allowed.has(key))) failures.push(`Provider ${provider.provider_id || '(unknown)'} does not match the closed descriptor shape.`);
        if (seen.has(provider.provider_id)) failures.push(`Provider network repeats ${provider.provider_id}.`);
        seen.add(provider.provider_id);
        if (provider.environment !== 'sandbox' || provider.commercial_truth?.simulated !== true || provider.commercial_truth?.real_charge !== false || provider.commercial_truth?.real_booking !== false || provider.allowed_hosts?.length !== 0) failures.push(`Provider ${provider.provider_id} crosses the sandbox truth boundary.`);
        if (!provider.verticals?.every(value => verticals.includes(value)) || !provider.capabilities?.every(value => capabilities.includes(value))) failures.push(`Provider ${provider.provider_id} declares unknown verticals or capabilities.`);
        if (provider.relationship === 'affiliate' && (!provider.capabilities.includes('report_conversion') || !provider.capabilities.includes('settlement_reconcile'))) failures.push(`Affiliate provider ${provider.provider_id} lacks conversion or settlement reconciliation.`);
      }
      for (const vertical of verticals) {
        if (!fixture.providers.some(provider => provider.verticals.includes(vertical))) failures.push(`Provider network does not cover ${vertical}.`);
      }
      for (const capability of capabilities) {
        if (!fixture.providers.some(provider => provider.capabilities.includes(capability))) failures.push(`Provider network does not demonstrate ${capability}.`);
      }
    }
  } catch (error) {
    failures.push(`Provider network fixture is invalid JSON: ${error.message}`);
  }
}

const extraCommerceSchemas = readdirSync(schemaDir).filter(name => name.startsWith('commerce-') && name.endsWith('.json') && !expectedSchemas.includes(name));
if (extraCommerceSchemas.length) failures.push(`Unreviewed commerce schemas exist: ${extraCommerceSchemas.map(basename).join(', ')}.`);

if (failures.length) {
  console.error('Commerce schema contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Commerce schemas passed (${schemas.size} closed schemas; ${verticals.length} verticals; ${capabilities.length} capabilities; deterministic sandbox network covered).`);
