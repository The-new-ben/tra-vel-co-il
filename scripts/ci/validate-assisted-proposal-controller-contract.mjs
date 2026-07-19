#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const controllerPath = path.join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'class-tra-vel-assisted-proposal-controller.php');
const runtimePath = path.join(root, 'scripts', 'ci', 'validate-assisted-proposal-controller-runtime.php');
const storePath = path.join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'class-tra-vel-assisted-proposal-store.php');
const policyPath = path.join(root, 'plugin', 'tra-vel-agent-core', 'includes', 'class-tra-vel-assisted-proposal-policy.php');
const failures = [];

function readRequired(file) {
  if (!fs.existsSync(file)) {
    failures.push(`Missing ${path.relative(root, file)}.`);
    return '';
  }
  return fs.readFileSync(file, 'utf8');
}

function requireMarker(text, marker, message) {
  if (!text.includes(marker)) failures.push(message);
}

const controller = readRequired(controllerPath);
const runtime = readRequired(runtimePath);
const store = readRequired(storePath);
const policy = readRequired(policyPath);

for (const marker of [
  "class Tra_Vel_Assisted_Proposal_Controller extends WP_REST_Controller",
  "'tra-vel-agent/v1'",
  "'assisted-proposals'",
  "'/schema/assisted-proposal'",
  "'/schema/assisted-proposal-source'",
  "'/schema/assisted-proposal-traveler'",
  "'/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/'",
  "'/operator/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/'",
  "'/actions'",
  "'/withdraw'",
  "'/compose'",
  "'/evidence-attestation'",
  'WP_REST_Server::READABLE',
  'WP_REST_Server::CREATABLE',
  "'permission_callback'",
  'Tra_Vel_Quote_Case_Capabilities::VIEW_CASES',
  'Tra_Vel_Quote_Case_Capabilities::PUBLISH_PROPOSALS',
  'Tra_Vel_Quote_Case_Capabilities::INGEST_PROPOSALS',
  'can_ingest_proposals',
  'record_traveler_action(',
  'publish_revision(',
  'compose_proposal(',
  'compose_proposal_revision(',
	'attest_composition_evidence(',
	'verify_evidence_attestation(',
  'Tra_Vel_Assisted_Proposal_Composer::compose(',
  'publish_composed_revision(',
  'withdraw(',
  'list_by_case(',
  'get_revision_bundle(',
]) requireMarker(controller, marker, `Controller is missing required REST/store marker ${marker}.`);

for (const marker of [
  'validate_proposal_arg',
  'reject_unknown_schema_fields',
  'project_schema_value',
  'rest_validate_value_from_schema',
  'additionalProperties',
  'tra_vel_assisted_proposal_shape_invalid',
  'tra_vel_assisted_proposal_envelope_unknown',
  'traveler_actions_for',
  "array( 'review', 'request_changes', 'authorize_contact', 'decline' )",
  'tra_vel_assisted_proposal_action_unsupported',
]) requireMarker(controller, marker, `Controller is missing closed-schema/action marker ${marker}.`);

for (const marker of [
  'rest_post_dispatch',
  'protect_private_response',
  'rest_convert_error_to_response',
  "'Cache-Control', 'private, no-store, max-age=0'",
  "'X-Robots-Tag', 'noindex, nofollow, noarchive'",
  "'Pragma', 'no-cache'",
]) requireMarker(controller, marker, `Controller is missing private-response marker ${marker}.`);

for (const marker of [
  'same_site_mutation',
  "get_header( 'Origin' )",
  "get_header( 'Referer' )",
  "! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' )",
  'home_url( \'/\' )',
  'PHP_URL_SCHEME',
  'PHP_URL_HOST',
  'PHP_URL_PORT',
  'PHP_URL_USER',
  'PHP_URL_PASS',
  'tra_vel_assisted_proposal_origin_rejected',
]) requireMarker(controller, marker, `Controller is missing same-origin marker ${marker}.`);

for (const marker of [
  'tra_vel_assisted_proposal_store_unavailable',
  'tra_vel_assisted_proposal_parent_inactive',
  'tra_vel_assisted_proposal_parent_mismatch',
  'tra_vel_assisted_proposal_request_changed',
  'tra_vel_assisted_proposal_revision_uncertain',
  'tra_vel_assisted_proposal_projection_invalid',
  'tra_vel_assisted_proposal_assignment_forbidden',
  'authorize_operator_case',
]) requireMarker(controller, marker, `Controller is missing fail-closed marker ${marker}.`);

if (controller.includes('tra_vel_manage_quote_cases')) {
  failures.push('Controller must not fall back to the generic queue-management capability.');
}

for (const signature of [
  'public function publish_revision( $verified_case, $proposal, $sources, $expected_version, $principal, $idempotency_key, $now = null )',
  'public function publish_composed_revision( $verified_case, $proposal, $sources, $expected_version, $principal, $idempotency_key, $command_basis, $now = null )',
  'public function replay_composed_revision( $verified_case, $expected_version, $principal, $idempotency_key, $command_basis )',
  'public function replay_traveler_action( $verified_case, $proposal_uuid, $expected_version, $principal, $action, $contact_consent, $idempotency_key )',
  'public function record_traveler_action( $verified_case, $proposal_uuid, $expected_version, $principal, $action, $contact_consent, $idempotency_key, $now = null )',
  'public function replay_withdrawal( $verified_case, $proposal_uuid, $expected_version, $principal, $idempotency_key )',
  'public function withdraw( $verified_case, $proposal_uuid, $expected_version, $principal, $idempotency_key, $now = null )',
  'public function list_by_case( $quote_case_id, $quote_case_uuid, $limit = 20 )',
  'public function get_revision_bundle( $proposal_uuid, $revision_no = 0 )',
]) requireMarker(store, signature, `Assisted proposal store/controller signature drift: ${signature}.`);
requireMarker(policy, 'public static function traveler_actions_for( $status, $disposition )', 'Policy-derived traveler actions are unavailable.');
if (/\$_(?:GET|POST|REQUEST)\b/.test(controller)) {
  failures.push('Controller must read REST request values only through WP_REST_Request.');
}
for (const method of [...controller.matchAll(/(?:public|private|protected) function\s+([A-Za-z0-9_]+)/g)].map(match => match[1])) {
  if (/book|pay|reserve|checkout|purchase|issue/i.test(method)) failures.push(`Controller exposes forbidden consequential method ${method}.`);
}
const routeRegistrations = [...controller.matchAll(/register_rest_route\s*\(/g)].length;
if (routeRegistrations !== 12) failures.push(`Expected 12 route registrations (13 method endpoints), found ${routeRegistrations}.`);
const permissionCallbacks = [...controller.matchAll(/'permission_callback'\s*=>/g)].length;
if (permissionCallbacks !== 13) failures.push(`Expected one permission callback per endpoint (13), found ${permissionCallbacks}.`);

for (const marker of [
  '$tra_vel_controller_assertions',
  'Unknown nested proposal fields must fail closed.',
  'Unknown nested source evidence must fail closed.',
  'Raw evidence, owner, event, and internal persistence fields must never be emitted.',
  'Traveler-safe provenance must preserve its public supplier label while suppressing private evidence URLs.',
  'Traveler proposal JSON must omit internal provider relationships, supplier lookup handles, and proposal-level integrity digests.',
  'Cross-origin traveler action must be rejected.',
  'Lookalike Referer host must be rejected.',
  'Mismatched effective port must be rejected.',
  'Credential-bearing Origin must be rejected.',
  'A different guest owner must not read the proposal.',
  'Historical request mismatch must remain readable as superseded with no actions.',
  'Mismatched parent request revision must still block mutations.',
  'A retained closed QuoteCase must remain readable by its exact owner.',
  'A proposal under a retained inactive QuoteCase must be historical and non-actionable.',
  'A retained exact owner must reach receipt reconciliation after the parent closes.',
  'A new traveler mutation under a retained closed QuoteCase must still fail closed.',
  'Traveler history must include retained expired, withdrawn, or superseded published heads.',
  'Store read uncertainty must fail closed.',
  'Scoped post-dispatch guard must convert and protect a raw WP_Error fail-closed response.',
  'Generic queue management must not grant proposal publication.',
  'Dedicated PUBLISH_PROPOSALS must gate publish and withdraw.',
  'A human proposal publisher must not gain canonical ingestion.',
  'The raw canonical endpoint must reject the normal human publisher capability.',
  'Only the separate trusted-ingestion capability may enter canonical proposals.',
  'A publisher who is not assigned to the case must fail closed.',
	'The assigned operator must receive a short-lived attestation for the exact final evidence command.',
  'The reduced composer command must produce and publish one server-owned proposal.',
  'An exact reduced-command retry must replay the original server-generated identity without a second write.',
  'Reusing a compose key for different authored data must fail closed.',
  'A revision command must preserve identity and advance aggregate version after intervening traveler actions.',
  'The latest exact revision retry may retain its coherent available lifecycle and must not append a third revision.',
  'The reduced composer command must reject unknown nested fields.',
  'Decline must remain a traveler disposition, not masquerade as operator withdrawal.',
  'An exact traveler-action retry must recover its committed historical result after the proposal evolves without appending another event.',
  'An exact committed traveler action must remain replayable as non-actionable history after the retained parent closes.',
  'Authorize contact must reject an absent consent object.',
  'Non-contact actions must reject a contact-consent payload.',
  'Contact consent must not silently authorize supplier sharing.',
  'The immutable contact event must add server time and a server-derived account digest without persisting raw contact data.',
  'Exact consent-bound contact authorization must replay as historical after the target disappears, proposal evolves, and parent closes without another event.',
  'The original operator must recover an exact committed withdrawal after head evolution, assignment reset, and retained parent closure.',
  'A composition receipt that expires before retry must project the coherent expired lifecycle and never restore actions.',
  'An old composition receipt must become non-actionable after a traveler action advances the live head.',
  'The original create receipt must remain historical after a newer commercial revision becomes live.',
  'A delayed exact retry must recover revision two as non-actionable history after a later live revision reaches the limit.',
  'An old composition receipt must remain historical after the live proposal is withdrawn.',
]) requireMarker(runtime, marker, `Runtime validator is missing ${marker}`);

for (const marker of [
  'replay_traveler_action(',
  'replay_withdrawal(',
  'normalize_contact_consent(',
  'validate_contact_target(',
  'CONTACT_CONSENT_VERSION',
  "'contact_target'        => 'account_email'",
  "return array( 'tra_vel_assistance_team' )",
]) requireMarker(controller, marker, `Controller replay/consent boundary is missing ${marker}.`);

if (failures.length) {
  console.error('Tra-Vel assisted proposal controller contract validation failed:');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Tra-Vel assisted proposal controller contract passed (case-bound ownership, closed schemas, private responses, safe mutations).');
