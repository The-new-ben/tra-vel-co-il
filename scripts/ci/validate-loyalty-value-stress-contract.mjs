import {readFileSync} from 'node:fs';
import {join, resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..', '..');
const schemaDir = join(root, 'plugin', 'tra-vel-agent-core', 'schemas', 'private');
const commerce = join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'commerce');
const names = [
  'loyalty-member-merge-record.schema.json',
  'loyalty-accrual-case.schema.json',
  'loyalty-cash-points-redemption.schema.json',
  'stored-value-voucher-ledger.schema.json',
];
const failures = [];
const schemas = new Map();
const sameSet = (left, right) => left.length === right.length && [...left].sort().every((value, index) => value === [...right].sort()[index]);

for (const name of names) {
  try { schemas.set(name, JSON.parse(readFileSync(join(schemaDir, name), 'utf8'))); }
  catch (error) { failures.push(`${name} is missing or invalid JSON: ${error.message}`); }
}

let closedObjects = 0;
const visit = (name, rootSchema, value, pointer = '#') => {
  if (!value || typeof value !== 'object') return;
  if (!Array.isArray(value) && value.type === 'object') {
    closedObjects++;
    if (value.additionalProperties !== false) failures.push(`${name} leaves ${pointer} open.`);
    const properties = Object.keys(value.properties || {});
    const required = value.required || [];
    if (!sameSet(properties, required)) failures.push(`${name} does not require every closed property at ${pointer}.`);
  }
  if (!Array.isArray(value) && typeof value.$ref === 'string') {
    if (!value.$ref.startsWith('#/')) failures.push(`${name} uses external reference ${value.$ref}.`);
    else {
      let target = rootSchema;
      for (const part of value.$ref.slice(2).split('/')) target = target && target[part.replaceAll('~1', '/').replaceAll('~0', '~')];
      if (!target) failures.push(`${name} has unresolved reference ${value.$ref}.`);
    }
  }
  if (!Array.isArray(value) && value.type === 'number') failures.push(`${name} permits floating point value at ${pointer}.`);
  if (Array.isArray(value)) value.forEach((child, index) => visit(name, rootSchema, child, `${pointer}/${index}`));
  else Object.entries(value).forEach(([key, child]) => visit(name, rootSchema, child, `${pointer}/${key}`));
};

const ids = new Set();
for (const [name, schema] of schemas) {
  if (schema.$schema !== 'http://json-schema.org/draft-07/schema#') failures.push(`${name} must use Draft-07.`);
  if (typeof schema.$id !== 'string' || !schema.$id.startsWith('https://tra-vel.co.il/schemas/private/')) failures.push(`${name} has an invalid private schema ID.`);
  else if (ids.has(schema.$id)) failures.push(`${name} duplicates schema ID ${schema.$id}.`);
  else ids.add(schema.$id);
  if (schema.properties?.contract_version?.const !== '1.1.0' || schema.properties?.environment?.const !== 'simulation') failures.push(`${name} must pin simulation contract 1.1.0.`);
  visit(name, schema, schema);
  const boundary = schema.definitions?.boundary?.properties || {};
  for (const key of ['public_serialization_allowed', 'execution_authorized', 'account_merge_authorized', 'accrual_credit_authorized', 'redemption_authorized', 'voucher_consumption_authorized', 'refund_authorized', 'supplier_dispatched', 'provider_called', 'processor_called', 'ledger_mutated', 'message_sent']) {
    if (boundary[key]?.const !== false) failures.push(`${name} does not pin ${key} false.`);
  }
}

const merge = schemas.get(names[0]);
if (merge) {
  if (merge.properties?.resolution?.properties?.double_credit_integer?.const !== 0) failures.push('Member merge must pin double credit to zero.');
  if (!merge.properties?.audit_lineage || !merge.properties?.source_snapshot_digest || !merge.properties?.target_snapshot_digest) failures.push('Member merge must bind immutable two-snapshot lineage.');
  const memberLedger = merge.definitions?.memberLedger?.properties || {};
  const ledgerLot = merge.definitions?.ledgerLot?.properties || {};
  if (!memberLedger.lots || !ledgerLot.state || !ledgerLot.amount_integer || !ledgerLot.evidence_digest) failures.push('Pre-merge ledgers must carry exact per-lot state, amount, and evidence.');
}
const accrual = schemas.get(names[1]);
if (accrual) {
  const states = accrual.definitions?.accrualState?.enum || [];
  if (!sameSet(states, ['expected', 'pending', 'disputed', 'credited', 'expired', 'rejected'])) failures.push('Accrual lifecycle vocabulary is incomplete.');
  const currentStates = accrual.definitions?.currentAccrualState?.enum || [];
  if (!sameSet(currentStates, ['pending', 'disputed', 'credited', 'expired', 'rejected'])) failures.push('Current accrual state must exclude the timeline-only expected origin.');
  if (accrual.properties?.bill?.properties?.state?.const !== 'posted' || accrual.properties?.resolution?.properties?.bill_posted_implies_credit?.const !== false || accrual.properties?.resolution?.properties?.automatic_credit_allowed?.const !== false) failures.push('Posted bill must not imply or automatically execute points credit.');
}
const redemption = schemas.get(names[2]);
if (redemption) {
  if (redemption.properties?.traveler_refs?.maxItems !== 10) failures.push('Cash plus points plan must cap explicit travelers at ten.');
  const types = redemption.definitions?.component?.properties?.component_type?.enum || [];
  if (!sameSet(types, ['base_fare', 'tax', 'carrier_fee', 'service_fee', 'ancillary'])) failures.push('Redemption components must keep fare, tax, fees, and ancillary value separate.');
  const cancellation = redemption.properties?.cancellation_scope?.properties || {};
  if (!cancellation.traveler_segment_partitions || cancellation.cross_party_reallocation_allowed?.const !== false || cancellation.silent_netting_allowed?.const !== false) failures.push('Cancellation must expose traveler-segment partitions and prohibit cross-party movement and netting.');
}
const voucher = schemas.get(names[3]);
if (voucher) {
  for (const field of ['owner_reference_digest', 'beneficiary_reference_digest', 'presented_beneficiary_reference_digest', 'fx_basis', 'value', 'expiry', 'restrictions', 'consumption', 'audit_lineage']) {
    if (!voucher.properties?.[field]) failures.push(`Voucher ledger is missing ${field}.`);
  }
  if (voucher.properties?.fx_basis?.properties?.rounding_mode?.const !== 'floor_minor_unit') failures.push('Voucher FX must declare deterministic integer rounding.');
  if (!voucher.properties?.fx_basis?.properties?.source_minor_unit_exponent || !voucher.properties?.fx_basis?.properties?.settlement_minor_unit_exponent) failures.push('Voucher FX must model source and settlement minor-unit exponents independently.');
  if (!voucher.properties?.consumption?.properties?.consumption_at) failures.push('Voucher consumption must carry an explicit nullable consumption clock.');
}

const policy = readFileSync(join(commerce, 'class-tra-vel-loyalty-value-stress-policy.php'), 'utf8');
const factory = readFileSync(join(commerce, 'class-tra-vel-loyalty-value-stress-factory.php'), 'utf8');
const runtime = readFileSync(join(root, 'scripts', 'ci', 'validate-loyalty-value-stress-runtime.php'), 'utf8');
const bootstrap = readFileSync(join(commerce, 'bootstrap.php'), 'utf8');
const themeWorkflow = readFileSync(join(root, '.github', 'workflows', 'theme-ci.yml'), 'utf8');
const deployWorkflow = readFileSync(join(root, '.github', 'workflows', 'deploy-agent-core.yml'), 'utf8');
for (const marker of ['validate_member_merge(', 'member_merge_basis_digest(', 'member_merge_transfer_lineage_mismatch', 'double_credit_integer', 'validate_accrual_case(', 'accrual_transition_invalid', 'CURRENT_ACCRUAL_STATES', 'bill_posted_implies_credit', 'validate_cash_points_redemption(', 'redemption_segment_without_components', 'cancellation_affected_traveler_without_component', 'traveler_segment_partitions', 'validate_voucher_ledger(', 'voucher_lineage_digest(', 'voucher_planned_clock_invalid', 'voucher_consumption_exceeds_purchase', 'source_minor_unit_exponent', 'settlement_minor_unit_exponent', 'cross_party_reallocation_allowed']) {
  if (!policy.includes(marker)) failures.push(`Policy is missing ${marker}.`);
}
for (const marker of ['create_member_merge(', 'create_accrual_case(', 'create_cash_points_redemption(', 'create_voucher_ledger(', 'draft_binding_digest(', 'assert_expected_record_ref(', 'idempotency_conflict', 'hash_hmac(', 'seal_record(']) {
  if (!factory.includes(marker)) failures.push(`Factory is missing ${marker}.`);
}
for (const proof of ['transfer lot must preserve exact source evidence lineage', 'same expected operation reference with changed immutable input', 'direct expected to disputed transition', 'backward disputed to pending transition', 'every listed segment must own at least one component', 'every affected traveler must own at least one affected component', 'planned evaluation creation and consumption', 'voucher settlement amount cannot exceed purchase total', 'non-transferable voucher presentation must match owner', 'different source and settlement minor-unit exponents', 'posted bill must not imply credit', 'ten-traveler componentized', 'owner and beneficiary must remain independently modeled', 'blocked voucher must preserve all available value', 'post-seal merge mutation']) {
  if (!runtime.includes(proof)) failures.push(`Runtime is missing proof: ${proof}.`);
}
const policyLoad = bootstrap.indexOf('class-tra-vel-loyalty-value-stress-policy.php');
const factoryLoad = bootstrap.indexOf('class-tra-vel-loyalty-value-stress-factory.php');
if (policyLoad < 0 || factoryLoad < 0 || policyLoad > factoryLoad) failures.push('Commerce bootstrap must load loyalty value policy before factory.');
for (const source of [policy, factory]) {
  for (const forbidden of ['register_rest_route(', 'wp_remote_get(', 'wp_remote_post(', 'wp_remote_request(', 'curl_exec(', 'update_option(', 'add_option(', '->query(', '->dispatch(', '->charge(', '->capture(', '->redeem(', '->credit(', '->refund(', 'wp_mail(']) {
    if (source.includes(forbidden)) failures.push(`Pure loyalty contract contains forbidden side effect ${forbidden}.`);
  }
}
for (const [name, workflow] of [['theme workflow', themeWorkflow], ['deploy workflow', deployWorkflow]]) {
  if ((workflow.match(/php scripts\/ci\/validate-loyalty-value-stress-runtime\.php/g) || []).length !== 2) failures.push(`${name} must run the loyalty runtime in both PHP and package gates.`);
  if ((workflow.match(/node scripts\/ci\/validate-loyalty-value-stress-contract\.mjs/g) || []).length !== 1) failures.push(`${name} must run the loyalty contract once.`);
}
if (!deployWorkflow.includes('scripts/ci/validate-loyalty-value-stress-*.mjs') || !deployWorkflow.includes('scripts/ci/validate-loyalty-value-stress-*.php')) failures.push('Agent deploy path filters must include validator-only loyalty stress changes.');

if (failures.length) {
  console.error('Loyalty value stress contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log(`Loyalty value stress contracts passed (${schemas.size} closed Draft-07 schemas; ${closedObjects} closed objects; four immutable zero-authority ledgers).`);
