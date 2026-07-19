import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(import.meta.dirname, '../..');
const schemaPath = path.join(root, 'plugin/tra-vel-agent-core/schemas/public/customer-trip-cockpit-customer-view.schema.json');
const policyPath = path.join(root, 'plugin/tra-vel-agent-core/includes/vip/class-tra-vel-customer-trip-cockpit-customer-view-policy.php');
const factoryPath = path.join(root, 'plugin/tra-vel-agent-core/includes/vip/class-tra-vel-customer-trip-cockpit-customer-view-factory.php');
const runtimePath = path.join(root, 'scripts/ci/validate-customer-trip-cockpit-customer-view-runtime.php');

const failures = [];
const fail = message => failures.push(message);
const read = file => fs.readFileSync(file, 'utf8');
const sameMembers = (actual = [], expected = []) => actual.length === expected.length && [...actual].sort().join('\n') === [...expected].sort().join('\n');

let schema = {};
try {
  schema = JSON.parse(read(schemaPath));
} catch (error) {
  fail(`Customer-view schema does not parse: ${error.message}`);
}

function assertClosedObjects(node, pointer = '#') {
  if (!node || typeof node !== 'object') return;
  if (node.type === 'object') {
    if (node.additionalProperties !== false) fail(`${pointer} must set additionalProperties:false.`);
    const properties = Object.keys(node.properties || {});
    if (!sameMembers(node.required || [], properties)) fail(`${pointer} must require every declared property and no undeclared property.`);
  }
  for (const [key, child] of Object.entries(node)) {
    if (child && typeof child === 'object') assertClosedObjects(child, `${pointer}/${key}`);
  }
}

function assertForbiddenPropertiesAbsent(node, forbidden, pointer = '#') {
  if (!node || typeof node !== 'object') return;
  if (node.properties) {
    for (const key of Object.keys(node.properties)) {
      if (forbidden.has(key)) fail(`${pointer}/properties contains forbidden private property ${key}.`);
    }
  }
  for (const [key, child] of Object.entries(node)) {
    if (child && typeof child === 'object') assertForbiddenPropertiesAbsent(child, forbidden, `${pointer}/${key}`);
  }
}

const rootFields = [
  'contract_version', 'environment', 'audience', 'trip_headline', 'current', 'next_safe_action',
  'protections', 'changes', 'attention_items', 'service_timeline', 'customer_money', 'case_progress_disclosure', 'trip_care_cases',
  'trip_care_receipts', 'traveler_readiness_disclosure', 'traveler_readiness', 'loyalty', 'offline_pack', 'freshness', 'authority', 'data_boundary',
];
if (schema.$schema !== 'http://json-schema.org/draft-07/schema#') fail('Customer-view schema must remain Draft-07.');
if (schema.$id !== 'https://tra-vel.co.il/schemas/public/customer-trip-cockpit-customer-view.schema.json') fail('Customer-view schema ID changed.');
if (!sameMembers(schema.required, rootFields)) fail('Customer-view root field set changed.');
if (schema.properties?.environment?.const !== 'sandbox') fail('Customer view must remain sandbox-only.');
assertClosedObjects(schema);
const forbiddenProperties = new Set(['cockpit_ref', 'trip_ref', 'owner_scope_digest', 'previous_projection_digest', 'projection_digest', 'service_ref', 'traveler_ref', 'order_ref', 'event_ref', 'case_ref', 'receipt_ref', 'severity', 'settlements', 'commission', 'provider_payload', 'operator_routing']);
assertForbiddenPropertiesAbsent(schema, forbiddenProperties);

const currentFields = ['phase', 'health', 'registration_readiness', 'affected_service_count', 'unaffected_service_count', 'declared_affected_service_keys', 'partition_detail', 'action_required', 'verified_at'];
if (!sameMembers(schema.definitions?.current?.required, currentFields)) fail('Current state must preserve aggregate and individually declared impact truth.');
if (!sameMembers(Object.keys(schema.definitions?.customerMoney?.properties || {}), ['disclosure', 'payments', 'refunds'])) fail('Customer money must expose only a disclosure boundary and independent payment and refund axes.');
if (!sameMembers(schema.definitions?.customerMoney?.properties?.disclosure?.enum, ['signed_in_redacted', 'withheld_scoped_session'])) fail('Customer money disclosure vocabulary changed.');
if (!sameMembers(schema.definitions?.paymentItem?.properties?.state?.enum, ['not_started', 'pending', 'requires_action', 'authorized', 'captured', 'failed', 'voided', 'partially_refunded', 'refunded', 'uncertain', 'disputed', 'charged_back'])) fail('Payment state vocabulary changed.');
if (!sameMembers(schema.definitions?.refundItem?.properties?.state?.enum, ['not_requested', 'requested', 'pending', 'partially_refunded', 'refunded', 'failed', 'uncertain', 'disputed'])) fail('Refund state vocabulary changed.');
if (!sameMembers(schema.definitions?.audience?.properties?.mode?.enum, ['signed_in', 'scoped_session'])) fail('Customer view must support only signed-in and separately scoped session contexts.');
for (const field of ['view_allowed', 'high_impact_step_up_required']) {
  if (schema.definitions?.audience?.properties?.[field]?.const !== true) fail(`Audience.${field} must remain structurally true.`);
}
if (schema.definitions?.audience?.properties?.mutation_authorized?.const !== false) fail('Audience must structurally deny mutation authority.');
for (const field of ['report_issue_allowed', 'follow_up_allowed']) {
  if (schema.definitions?.audience?.properties?.[field]?.type !== 'boolean') fail(`Audience.${field} must derive as a boolean from verified low-risk scopes.`);
}
if (!sameMembers(schema.definitions?.action?.properties?.interaction_mode?.enum, ['view', 'follow_up', 'step_up_required'])) fail('Action interaction modes must distinguish view, follow-up, and step-up.');
for (const effect of ['issue', 'redeem', 'upgrade', 'swap', 'unknown_high_impact']) {
  if (!(schema.definitions?.action?.properties?.requested_effect?.enum || []).includes(effect)) fail(`Action effects must retain conservative ${effect} handling.`);
}
if (schema.definitions?.action?.properties?.execution_effect?.const !== 'none') fail('Customer actions must grant no execution effect.');
if (!sameMembers(schema.properties?.trip_headline?.enum, ['Trip plan', 'Upcoming trip', 'Outbound journey', 'Trip in progress', 'Return journey', 'Completed trip'])) fail('Trip headline must be a closed phase-derived template.');
if (!sameMembers(schema.properties?.traveler_readiness_disclosure?.enum, ['signed_in_redacted', 'withheld_scoped_session'])) fail('Traveler-readiness disclosure vocabulary changed.');
if (!sameMembers(schema.properties?.case_progress_disclosure?.enum, ['case_progress_redacted', 'withheld_scope_missing'])) fail('Case-progress disclosure vocabulary changed.');
if (!(schema.definitions?.current?.properties?.registration_readiness?.enum || []).includes('withheld')) fail('Scoped registration readiness requires an explicit withheld state.');
if (!(schema.definitions?.loyalty?.properties?.status?.enum || []).includes('withheld') || !(schema.definitions?.loyalty?.properties?.filter_readiness?.enum || []).includes('withheld')) fail('Scoped loyalty requires explicit withheld status and readiness states.');
if (!sameMembers(schema.definitions?.freshness?.properties?.basis?.enum, ['signed_in_redacted', 'scoped_visible_only'])) fail('Freshness must declare whether it uses signed-in redacted or scoped visible-only material.');
const schemaText = JSON.stringify(schema);
for (const marker of ['withheld_scoped_session', 'withheld_scope_missing', 'case_progress_redacted', 'scoped_visible_only']) {
  if (!schemaText.includes(marker)) fail(`Conditional disclosure schema is missing ${marker}.`);
}
if (!schema.definitions?.service?.properties?.label?.pattern?.includes('Flight|Stay|Package')) fail('Service labels must be closed vertical/phase templates.');
for (const definition of ['serviceKey', 'travelerSlot', 'purchaseKey', 'caseKey', 'receiptKey', 'attentionKey']) {
  if (!schema.definitions?.[definition]?.pattern?.includes('{32}')) fail(`${definition} must be a 128-bit HMAC alias.`);
}
for (const code of ['service.event', 'status.updated', 'issue.ticket', 'redeem.points', 'upgrade.service', 'swap.service']) {
  if (!(schema.definitions?.code?.enum || []).includes(code)) fail(`Explicit customer-code allowlist is missing ${code}.`);
}
for (const code of ['provider1.queue42.assignment', 'supplieralpha.gdsdesk.status', 'settlements.pending']) {
  if ((schema.definitions?.code?.enum || []).includes(code)) fail(`Unsafe code entered the customer allowlist: ${code}.`);
}
for (const field of ['change_started', 'cancellation_started', 'payment_started', 'refund_started', 'supplier_action_started', 'processor_action_started', 'resolution_inferred']) {
  if (schema.definitions?.authority?.properties?.[field]?.const !== false) fail(`Authority.${field} must remain structurally false.`);
}
if (schema.definitions?.authority?.properties?.authorization_effect?.const !== 'none' || schema.definitions?.authority?.properties?.view_projection_only?.const !== true) fail('Authority must remain a view-only, non-authorizing projection.');
for (const field of ['owner_scope_exposed', 'internal_refs_exposed', 'raw_identity_data_exposed', 'raw_payment_data_exposed', 'raw_medical_data_exposed', 'raw_provider_data_exposed', 'bearer_secret_exposed', 'internal_operator_routing_exposed', 'settlement_data_exposed', 'commission_data_exposed']) {
  if (schema.definitions?.dataBoundary?.properties?.[field]?.const !== false) fail(`Data boundary ${field} must remain structurally false.`);
}
if (schema.definitions?.dataBoundary?.properties?.customer_serialization_allowed?.const !== true || schema.definitions?.dataBoundary?.properties?.validated_private_read_model_only?.const !== true) fail('Customer serialization must require a validated private read model.');
if ((schema.definitions?.tripCareCase?.required || []).includes('severity')) fail('Internal incident severity must not enter the customer schema.');
const policy = read(policyPath);
const factory = read(factoryPath);
const runtime = read(runtimePath);
const codeBlock = policy.match(/const CUSTOMER_CODES = array\(([\s\S]*?)\n\t\);/);
const policyCodes = codeBlock ? [...codeBlock[1].matchAll(/'([^']+)'/g)].map(match => match[1]) : [];
if (!sameMembers(policyCodes, schema.definitions?.code?.enum || [])) fail('PHP and Draft-07 customer-code allowlists must match exactly.');

for (const marker of ['validate_view', 'validate_viewing_context', 'MAX_VIEWING_CONTEXT_LIFETIME_SECONDS', 'trip_view_redacted', 'incident_report', 'case_progress_view', 'CUSTOMER_CODES', 'partition_invalid', 'action_authority_invalid', 'money_partition_invalid', 'money_disclosure_invalid', 'traveler_disclosure_invalid', 'loyalty_disclosure_invalid', 'case_progress_scope_invalid', 'scoped_material_is_withheld', 'authority_invalid', 'boundary_exposure_invalid', 'contains_sensitive_material']) {
  if (!policy.includes(marker)) fail(`Customer-view policy is missing ${marker}.`);
}
for (const marker of ['create_view', 'Tra_Vel_Customer_Trip_Cockpit_Policy::validate_projection', 'validate_viewing_context', 'hash_hmac', 'public_alias', 'trip_template', 'service_template', 'declared_affected_refs', 'partition_detail', 'next_safe_action', 'customer_money', "'payments'", "'refunds'", 'withheld_scoped_session', 'case_progress_allowed', 'scoped_code_is_withheld', 'visible_freshness_basis', 'freshness_status', 'freshness_source_verified_at']) {
  if (!factory.includes(marker)) fail(`Customer-view factory is missing ${marker}.`);
}
const privateValidation = factory.indexOf('Tra_Vel_Customer_Trip_Cockpit_Policy::validate_projection');
const materialization = factory.indexOf('$record = array(');
if (privateValidation < 0 || materialization < 0 || privateValidation > materialization) fail('The sealed private cockpit must validate before customer materialization.');
const serializedMaterialization = factory.slice(materialization, factory.indexOf('\n\tprivate static function trip_template'));
if (serializedMaterialization.includes("'owner_scope_digest'")) fail('Factory must not serialize the private owner digest.');
if (/=>\s*\$private_projection\['trip_headline'\]/.test(factory) || /=>\s*\$service\['label'\]/.test(factory)) fail('Factory must never copy private free-form labels into the customer view.');
if (factory.includes('freshness_status( $private_projection )') || factory.includes("'source_verified_at'  => $private_projection['last_verified_at']")) fail('Freshness must derive only from customer-visible axes.');
for (const forbidden of ['register_rest_route', '$wpdb', 'wp_remote_', 'curl_', 'mysqli_', 'PDO(', 'update_option(', 'add_action(', 'random_bytes(', 'wp_generate_password(', 'JWT', 'Authorization: Bearer']) {
  if (policy.includes(forbidden) || factory.includes(forbidden)) fail(`Customer projection crossed its pure no-REST/no-DB/no-network/no-token boundary: ${forbidden}`);
}
for (const php8Only of [/\bmatch\s*\(/, /\bstr_(?:contains|starts_with|ends_with)\s*\(/, /\?->/, /\bfn\s*\(/]) {
  if (php8Only.test(policy) || php8Only.test(factory)) fail(`Customer projection must remain PHP 7.4-compatible; found ${php8Only}.`);
}

const scenarioCount = [...runtime.matchAll(/\$scenarios\['[a-z0-9_]+'\]\s*=\s*function/g)].length;
if (scenarioCount < 85) fail(`Customer-view runtime covers only ${scenarioCount} adversarial scenarios; at least 85 are required.`);
for (const scenario of [
  'scoped_session_view', 'complete_partial_trip_partition', 'aggregate_only_partition', 'partially_declared_partition',
  'change_action_requires_step_up', 'cancel_action_requires_step_up', 'payment_action_requires_step_up', 'refund_action_requires_step_up',
  'provider_routing_code_redacted', 'private_owner_digest_omitted', 'case_internal_severity_omitted',
  'captured_payment_pending_refund_distinct', 'mismatched_purchase_axes_rejected', 'resolution_inference_rejected',
  'unverified_context_rejected', 'mismatched_context_trip_rejected', 'mismatched_signed_owner_rejected', 'expired_scoped_session_rejected',
  'view_only_scope_derives_capabilities', 'private_headline_never_copied', 'private_service_label_never_copied',
  'hmac_alias_matches_owner_trip_scope', 'hmac_alias_isolated_across_trips', 'hmac_alias_isolated_across_owners',
  'provider1_code_maps_to_fallback', 'supplieralpha_code_mutation_rejected', 'settlements_code_mutation_rejected',
  'unknown_action_defaults_step_up', 'issue_action_requires_step_up', 'redeem_action_requires_step_up', 'upgrade_action_requires_step_up', 'swap_action_requires_step_up',
  'hidden_settlement_clock_does_not_change_public_freshness', 'schema_recursive_forbidden_properties', 'runtime_closes_every_object',
  'forwarded_scoped_link_withholds_money_loyalty_and_travelers', 'scoped_minor_accessibility_details_have_no_alias_side_channel',
  'scoped_hidden_axes_do_not_change_freshness_or_output', 'missing_case_scope_withholds_cases_and_receipts',
  'case_scope_releases_only_redacted_progress', 'missing_incident_scope_withholds_reporting_action',
  'incident_scope_releases_non_executing_reporting', 'scoped_commerce_loyalty_and_traveler_actions_withheld',
  'scoped_projection_has_no_mutation_authority', 'scoped_money_injection_rejected', 'scoped_traveler_injection_rejected',
  'scoped_loyalty_injection_rejected', 'case_progress_injection_without_scope_rejected',
]) {
  if (!runtime.includes(`$scenarios['${scenario}']`)) fail(`Runtime is missing required adversarial scenario ${scenario}.`);
}
for (const marker of ['The factory must revalidate the private seal', 'Settlement data must not enter', 'High-impact actions cannot be downgraded', 'Every materialized object must reject unknown field']) {
  if (!runtime.includes(marker)) fail(`Runtime is missing boundary assertion: ${marker}.`);
}

if (failures.length) {
  console.error('Customer Trip Cockpit customer-view contract failed:');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log(`Customer Trip Cockpit customer-view contract passed (${rootFields.length} root fields; ${scenarioCount} adversarial scenarios; redacted/no-authority boundary).`);
